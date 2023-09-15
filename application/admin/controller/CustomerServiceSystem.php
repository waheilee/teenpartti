<?php


namespace app\admin\controller;

use app\common\Config;
use app\common\GameLog;
use app\model\AccountDB;
use app\model\CountryCode;
use app\model\DataChangelogsDB;
use app\model\GameOCDB;
use app\model\Log;
use app\model\MasterDB;
use app\model\UserBatchMail;
use app\model\UserDB;
use redis\Redis;
use think\Db;
use think\response\Json;
use app\model\User as userModel;

/**
 * Class CustomerServiceSystem 客服服务系统
 * @package app\admin\controller
 */
class CustomerServiceSystem extends Main
{

    /**
     * 客服系统主页
     * @return mixed|\think\response\Json
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $page = input('page', 1);
            $limit = input('limit', 15);
            $roleId = input('roleid', 0);
            $nickname = trim(input('nickname'));
            $orderby = input('orderfield');
            $ordertype = input('ordertype');
            $MachineCode = trim(input('MachineCode')) ? trim(input('MachineCode')) : '';
            $where = "";
            if ($roleId > 0) $where .= " and  AccountID =" . $roleId;
            if (!empty($nickname)) $where .= " AND nickname like '%$nickname%' ";
            if (!empty($startTime) && !empty($endTime)) $where .= " AND RegisterTime BETWEEN '$startTime' AND '$endTime' ";
            if (!empty($MachineCode)) $where .= " and  MachineCode = '$MachineCode'";

            $field = "AccountID ID,MachineCode,countryCode,AccountName,Locked,LoginName,GmType,LastLoginTime,LastLoginIP,Money,Score,CouponCount,TotalDeposit,Email";
            $db = new UserDB();
            $list = $db->TViewIndex()->GetPage($where, "$orderby $ordertype", $field);
            return $this->apiJson($list);

        }
        $this->assign('online', json_encode($this->GetOnlineUserlist()));
        return $this->fetch();
    }

    //玩家重置密码
    public function ResetPwd()
    {
        $request = request()->request();
        $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
        $Password = input('Password') ? md5(input('Password')) : '';
        if (!$roleId || !$Password) {
            return $this->apiReturn(1, [], '必填项不能为空');
        }
        $res = $this->sendGameMessage('CMD_MA_RESET_LOGIN_PWD', [$roleId, $Password], "AC");
        $res = unpack('Lcode/', $res);
        //log记录
        GameLog::logData(__METHOD__, "密码修改 ID=$roleId 新密码=" . input('Password'), 1);
        if (isset($res['code']) && $res['code'] == 0) {
            return $this->success("更新成功");
        } else {
            $this->error("更新失败");
        }

    }

    public function BindAccount()
    {
        $account = input('Account');
        $roleid = input('roleid');
        $password = input('Password');
        if ($account != $password) {
            return $this->error('两次密码不一致');
        }
        return self::ResetPwd();
        return $this->success("更新成功");


        switch (input('Action')) {
            case 'bind':
                $db = new AccountDB();
                $rows = $db->TAccounts()->GetRow(['AccountName' => input('Account')], 'AccountID');
                if (!isset($rows)) {
                    $account = input('Account');
                    $roleid = input('roleid');
                    $rows = $db->UPData(['AccountName' => $account], "AccountID=$roleid");
                    GameLog::logData(__METHOD__, "账号绑定 ID=$roleid 新账号$account", 1);
                    if ($rows > 0) return $this->success("更新成功");
                    return $this->error("更新失败");
                }
                return $this->error('账号已存在');
                break;
            case 'pwd':
                return self::ResetPwd();
                break;
            case 'all':
                $account = input('Account');
                $roleid = input('roleid');
                $db = new AccountDB();
                $rows = $db->TAccounts()->GetRow(['AccountName' => $account], 'AccountID');
                if (!isset($rows)) {
                    $rows = $db->UPData(['AccountName' => $account], "AccountID=$roleid");
                    $res = $this->sendGameMessage('CMD_MA_RESET_LOGIN_PWD', [$roleid, md5(input('Password'))], "AC");
                    $res = unpack('Lcode/', $res);
                    if ($res['code'] == 0 && $rows > 0) return $this->success("更新成功");
                    GameLog::logData(__METHOD__, lang('账号绑定') . " ID=$roleid " . lang('新账号') . "$account", 1);
                    GameLog::logData(__METHOD__, lang('密码修改') . " ID=$roleid" . lang('新密码') . "=" . input('Password'), 1);
                }
                return $this->error('账号已存在');

                break;
        }

    }


    /**客服配置 增删改查        [OM_MasterDB] [T_CustomService_Cfg] **/
    public function CustomerServiceConfig($action = null)
    {

        $db = new MasterDB();
        //$countrycode = input('CountryCode');
//        if($countrycode=='-1')
//            $countrycode ='';
        $data = ["CustomName" => input('CustomName'), "Phone" => str_replace('\r\n', '', input('Phone')), 'VipLabel' => input('VipLabel'),
            "FaceId" => input('FaceID'), 'FaceUrl' => input('FaceUrl', ''), "Id" => input('ID'), 'CountryCode' => '', 'CustomTitle' => input('CustomTitle', ''), 'ExternalLink' => input('ExternalLink')
        ];
        switch ($action) {
            case "list":
                $result = $db->TCustomServiceCfg()->GetPage();
                return $this->apiJson($result);
                break;
            case  "add": //添加页面
                if ($this->request->isAjax()) {
                    if (empty($data['CustomName']) || empty($data['CustomTitle'])) {
                        return $this->error("表单未填写完整");
                    }
                    unset($data['Id']);
                    $rows = $db->TCustomServiceCfg()->Insert($data);
                    if ($rows > 0) {
                        Redis::rm('CustomService::LoginPage');
                        return $this->success("添加成功");
                    }
                    return $this->error("添加失败");
                }
//                $coutryconfig = new CountryCode();
//                $result = $coutryconfig->getListAll([],'','sortid');
//                foreach ($result as $k=>&$v){
//                    if(!empty($v['CountryName']))
//                        $v['CountryName'] = lang($v['CountryName']);
//                }
//                unset($v);
//                $this->assign('country',$result);
                $this->assign('info', $data);
                $this->assign('action', "add");
                return $this->fetch("customer_config_edit");
                break;
            case "edit": //编辑页面
                if ($this->request->isAjax()) {
                    unset($data['Id']);
                    $rows = $db->TCustomServiceCfg()->UPData($data, "Id=" . input('ID'));
                    if ($rows > 0) {
                        Redis::rm('CustomService::LoginPage');
                        return $this->success(lang("更新成功"));
                    }
                    return $this->error(lang("更新失败"));
                }
//                $coutryconfig = new CountryCode();
//                $result = $coutryconfig->getListAll([],'','sortid');
//                foreach ($result as $k=>&$v){
//                    if(!empty($v['CountryName']))
//                        $v['CountryName'] = lang($v['CountryName']);
//                }
//                unset($v);
//                $this->assign('country',$result);
                $this->assign('info', $data);
                $this->assign('action', "edit");
                return $this->fetch("customer_config_edit");
                break;
            case "editStatus": //编辑页面
                if ($this->request->isAjax()) {
                    $status = input('status');
                    $ID = input('id');
                    $rows = $db->TCustomServiceCfg()->UPData(['Status' => $status], "Id=" . $ID);
                    if ($rows > 0) {
                        Redis::rm('CustomService::LoginPage');
                        return $this->success(lang("更新成功"));
                    }
                    return $this->error(lang("更新失败"));
                }
                break;
            case "del":
                $rows = $db->TCustomServiceCfg()->DeleteRow("Id=" . input('ID'));
                Redis::rm('CustomService::LoginPage');
                if ($rows > 0)
                    return $this->success("删除成功");
                return $this->error("删除失败");
                break;

        }
        return $this->fetch();
    }

    //上传客服头像
    public function uploadheadpic()
    {
        $file = $this->request->file('file');
        $info = $file->validate(['size' => 2097152, 'ext' => 'png,jpg,jpeg'])->move(ROOT_PATH . 'public' . DS . 'images' . DS . 'topshow');
        if ($info) {
            $savepath = $info->getSaveName();
            //验证文件手机号是否正确
            $filepath = ROOT_PATH . 'public' . DS . 'images' . DS . 'topshow' . DS . $savepath;
            if (!file_exists($filepath)) {
                return $this->apiReturn(2, [], '上传失败');
            }
            $headurl = config('active_img_url') . '/topshow/' . $savepath;
            return $this->apiReturn(0, ['path' => $headurl], '上传成功');
        } else {
            return $this->apiReturn(1, [], $file->getError());
        }

    }

    /**
     * 邮件管理
     * @return  Json
     */
    public function EmailManager()
    {

        switch (input('Action')) {
            case 'list':
                $db = new DataChangelogsDB();
                $result = $db->GetEmailList();
                return $this->apiJson($result);
            case 'add': //审核并发送邮件

                if ($this->request->isAjax()) {
                    if (input('form_token') != session('form_token')) {
                        return $this->apiReturn(100, '', '请勿重复提交');
                    } else {
                        session('form_token',null);
                    }
                    $rolelist = input('rolelist');
                    $mailtxt = input('mailtxt');
                    $recordtype = intval(input('recordtype', -1));
                    $extratype = intval(input('extratype', -1));
                    $sendtime = input('sendtime');
                    $sendtype = intval(input('sendtype', -1));
                    $amount = input('amount', 0);
                    $title = input('title');
                    $sendobj = (int)input('sendObj');
                    $Notice = input('Notice', '');
                    $Country = input('Country', '');
                    $isNeedAward = input('isneedaward');
                    $NeedAward = input('needaward');
                    $WageMul = input('WageMul');
                    $chargeorder = intval(input('chargeorder', 0));
                    $error = null;
                    $WageMul = $WageMul * 10;

//                    halt(request()->request());
                    if ($recordtype < 0 || $extratype < 0 || $sendtype < 0 || empty($mailtxt) || empty($title)) $error = lang('请确认输入都正确');
                    if ($extratype > 0 && empty($amount)) $error .= lang("请输入附件数量");

                    if ($extratype > 0) {
                        //额度判断
                        $db = new GameOCDB();
                        $email_db = new DataChangelogsDB();
                        $config = $db->getTableObject('T_MailExtraQuota')->where('UserId', session('userid'))->find();
                        if (empty($config)) {
                            $DailyQuota = 0;
                            $TotalQuota = 0;
                        } else {
                            $DailyQuota = $config['DailyQuota'];
                            $TotalQuota = $config['TotalQuota'];
                        }

                        // $hasQuotaToday = $email_db->getTableObject('T_ProxyMsgLog')
                        //                             ->where('uid',session('userid'))
                        //                             ->where('RecordType',8)
                        //                             ->where('ExtraType','>',0)
                        //                             ->where('VerifyState','in','0,1')
                        //                             ->whereTime('addtime','>=',date('Y-m-d'))
                        //                             ->sum('Amount')?:0;
                        // $hasQuotaTotal = $email_db->getTableObject('T_ProxyMsgLog')
                        //                             ->where('uid',session('userid'))
                        //                             ->where('RecordType',8)
                        //                             ->where('ExtraType','>',0)
                        //                             ->where('VerifyState','in','0,1')
                        //                             ->sum('Amount')?:0;
                        $hasQuotaToday = $db->getTableObject('T_AdminMailLog')
                            ->where('UserId', session('userid'))
                            ->where('extratype', 'in', '1,7')
                            ->whereTime('AddTime', '>=', date('Y-m-d'))
                            ->sum('Amount') ?: 0;
                        $hasQuotaToday /= bl;
                        $hasQuotaTotal = $db->getTableObject('T_AdminMailLog')
                            ->where('UserId', session('userid'))
                            ->where('extratype', 'in', '1,7')
                            ->sum('Amount') ?: 0;
                        $hasQuotaTotal /= bl;
                        if ($DailyQuota < ($hasQuotaToday + $amount)) {
                            return $this->apiReturn(100, '', '日额度不足');
                        }
                        if ($TotalQuota < ($hasQuotaTotal + $amount)) {
                            return $this->apiReturn(100, '', '总额度不足');
                        }
                    }
                    $amount *= bl;
                    if ($sendtype == 1 && !empty($sendtime)) {
                        $delaytime = strtotime($sendtime) - sysTime();
                        if ($delaytime <= 60) $error .= lang("定时时间须大于1分钟以上");
                    }

                    if (!empty($error)) return $this->apiReturn(100, '', $error);
                    $res = -1;
                    $VerifyState = 0; //0未审核,1已审核
                    if ($recordtype == 8 && $rolelist == 0) {
                        return $this->apiReturn(1, '', '请输入用户ID');
                    }
                    if ($sendobj == 1) {//全发
                        $delaytime = 0;
                        if ($sendtype == 1) $delaytime = strtotime($sendtime) - sysTime();
                        $user_name = session('username');
                        $res = $this->sendGameMessage('CMD_MD_SYSTEM_MAILv2', [0, 0, $recordtype, $extratype, $amount, $chargeorder, $WageMul, $delaytime, $VerifyState, $title, $mailtxt, "", $Country, $user_name]);
                        $res = unpack('Lint', $res)['int'];

                    } else {//用户列表
                        if ((int)$extratype == 1 || (int)$extratype == 7) {
                            if (config('app_name') == 'TATUWIN') {
                                $VerifyState = 1;  //需要审核0 1不需要审核
                            } else {
                                $VerifyState = 0;  //需要审核0 1不需要审核
                            }
                            
                        }
                        $delaytime = 0;
                        if ($sendtype == 1) $delaytime = strtotime($sendtime) - sysTime();
                        $user_name = session('username');
                        $res = $this->sendGameMessage('CMD_MD_SYSTEM_MAILv2', [0, $rolelist, $recordtype, $extratype, $amount, $chargeorder, $WageMul, $delaytime, $VerifyState, $title, $mailtxt, $Notice, $Country, $user_name]);
                        $res = unpack('Lint', $res)['int'];
                    }
                    if ($res == 0) {
                        
                        return $this->apiReturn(1, '', '邮件发送成功');
                        
                    } elseif ($res > 0) {
                        return $this->apiReturn(1, '', '邮件发送失败');
                    }
                }
                $mailtype = config('mailtype');
                $extratype = config('extratype');
                unset($extratype[8]);
                $this->assign('mailtype', $mailtype);
                $this->assign('extratype', $extratype);
                $form_token = time().rand(1000,9999);
                session('form_token',$form_token);
                $this->assign('form_token', $form_token);

//                $coutryconfig = new CountryCode();
//                $result = $coutryconfig->getListAll([],'','sortid');
//                foreach ($result as $k=>&$v){
//                    if(!empty($v['CountryName']))
//                        $v['CountryName'] = lang($v['CountryName']);
//                }
//                unset($v);
                //$this->assign('country',$result);
                return $this->fetch('email_add');
            case 'send'://邮件不用审核了
                //权限验证 
                $auth_ids = $this->getAuthIds();
                if (!in_array(10010, $auth_ids)) {
                    return $this->error('没有权限');
                }
                $password = input('password');
                $user_controller = new \app\admin\controller\User();
                $pwd = $user_controller->rsacheck($password);
                if (!$pwd) {
                    return $this->error('密码错误');
                }
                $userModel = new userModel();
                $userInfo = $userModel->getRow(['id' => session('userid')]);
                if (md5($userInfo['salt'] . $pwd) !== $userInfo['password']) {
                    return $this->error('密码错误');
                }
                $id = input('ID');
                if ($id > 0) {
                    $res = $this->sendGameMessage('SeMDMailStateChange', [$id, 1]);
                    $res = unpack('Lint', $res)['int'];
                    GameLog::logData(__METHOD__, lang('审核邮件') . " ID=$id" . lang('服务端状态码') . ":$res" . lang('审核人') . ":" . session('username'), 1);
                    if ($res == 0) {
                        // 执行统计
                        $gameocdb = new GameOCDB();
                        $strtoday = date('Y-m-d', time());
                        // $gameocdb->runSystemDaySum($strtoday);
                        return $this->success('审核成功');
                    } else {
                        return $this->error('审核失败');
                    }
                }
                return $this->error('参数错误');
            case 'del':
                $id = input('ID');
                $res = $this->sendGameMessage('SeMDMailStateChange', [$id, 2]);
                $res = unpack('Lint', $res)['int'];
                GameLog::logData(__METHOD__, lang('作废邮件') . "ID=$id" . lang('服务端状态码') . ":$res," . lang('审核人') . ":" . session('username'), 1);
                if ($res == 0) return $this->success('审核成功');
                else return $this->error('审核失败');
            case 'back':
                $id = (int)input('ids', 0);
                if ($id > 0) {
                    $db = new MasterDB();
                    $rows = $db->TSystemMail()->UPData(["addtime" => '2099-01-01'], "id=$id");
                    if ($rows) {
                        $db = new  DataChangelogsDB();
                        $rows = $db->TProxyMsgLog()->UPData(["addtime" => '2099-01-01'], "ids=$id");
                        if ($rows) return $this->apiReturn(0, [], '撤回成功');
                    }
                    return $this->apiReturn(100, [], '撤回失败');
                }
                return $this->apiReturn(100, [], '参数错误');
            case 'note'://修改备注
                $request = request()->request();
                $db = new DataChangelogsDB();
                $row = $db->TProxyMsgLog()->UPData(['Notice' => $request['Notice']], "id=" . $request['ID']);
                if ($row > 0) return $this->success("更新成功");
                return $this->error("更新失败");
            case 'exec':
                $db = new DataChangelogsDB();
                $result = $db->GetEmailList();
                $outAll = input('outall', false);
                if ((int)input('exec', 0) == 0) {
                    if ($result['count'] == 0) {
                        $result = ["count" => 0, "code" => 1, 'msg' => lang("没有找到任何数据,换个姿势再试试?")];
                    }
                    if ($result['count'] >= 5000 && $outAll == false) {
                        $result = ["code" => 2, 'msg' => "数据超过5000行是否全部导出?<br>数据越多速度越慢<br>当前数据一共有" . $result['count'] . "行"];
                    }
                    unset($result['list']);
                    return $this->apiJson($result);
                }
                //导出表格
                if ((int)input('exec', 0) == 1 && $outAll = true) {
                    //权限验证 
                    $auth_ids = $this->getAuthIds();
                    if (!in_array(10008, $auth_ids)) {
                        return $this->apiReturn(1, '', '没有权限');
                    }
                    $header_types = [
                        'ID' => 'integer',
                        lang('接收人') => 'integer',
                        lang('附件数量') => '0.00',
                        lang('发送时间') => 'datetime',
                        lang('状态') => 'string',
                        lang('备注') => 'string',
                        lang('标题') => 'string',
                        lang('邮件类型') => 'string',
                        lang('附件类型') => 'string',
                        lang('邮件文本') => 'string',
                        lang('操作人') => 'string',
                    ];
                    $filename = lang('邮件管理') . '-' . date('YmdHis');
                    $rows =& $result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {
                        $item = [
                            $row['id'],
                            $row['RoleId'],
                            $row['Amount'],
                            $row['addtime'],
                            $row['opertext'],
                            $row['Notice'],
                            $row['Title'],
                            $row['RecordType'],
                            $row['ExtraType'],
                            $row['SysText'],
                            $row['Operator'],
                        ];
                        $writer->writeSheetRow('sheet1', $item, ['height' => 16, 'halign' => 'center',]);
                        unset($rows[$index]);
                    }
                    unset($row, $item);
                    $writer->writeToStdOut();
                    exit();
                }

        }
        $extratype = config('extratype');
        $this->assign('extratype',$extratype);
        return $this->fetch();
    }

    /**
     * 邮件审核
     * @return  Json
     */
    public function emailReview()
    {
        $extratype = config('extratype');
        $this->assign('extratype',$extratype);
        return $this->fetch();
    }

    /**
     * 客服日志
     * @return
     */
    public function GetServcieLog()
    {
        switch (input('Action')) {
            case 'list':
                $db = new Log();
                $result = $db->GetServcieLogList();
                foreach ($result['data'] as $key => &$val) {
                    $val['content'] = str_replace('作废 邮件', lang('作废 邮件'), $val['content']);
                    $val['content'] = str_replace('审核 邮件', lang('审核 邮件'), $val['content']);
                    $val['content'] = str_replace('服务端状态码', lang('服务端状态码'), $val['content']);
                    $val['content'] = str_replace('审核人', lang('审核人'), $val['content']);
                }
                return json($result);
            case 'exec':
                $db = new Log();
                $result = $db->GetServcieLogList();

                $outAll = input('outall', false);
                if ((int)input('exec', 0) == 0) {
                    if ($result['count'] == 0) {
                        $result = ["count" => 0, "code" => 1, 'msg' => lang("没有找到任何数据,换个姿势再试试?")];
                    }
                    if ($result['count'] >= 5000 && $outAll == false) {
                        $result = ["code" => 2, 'msg' => lang("数据超过5000行是否全部导出?<br>数据越多速度越慢<br>当前数据一共有") . $result['count'] . lang("行")];
                    }
                    unset($result['list']);
                    return $this->apiJson($result);
                }
                //导出表格
                if ((int)input('exec', 0) == 1 && $outAll = true) {
                    //权限验证 
                    $auth_ids = $this->getAuthIds();
                    if (!in_array(10008, $auth_ids)) {
                        return $this->apiReturn(1, '', '没有权限');
                    }
                    $header_types = [
                        'ID' => 'integer',
                        lang('操作账号') => 'string',
                        lang('操作方法') => 'string',
                        lang('操作内容') => 'string',
                        lang('操作时间') => 'datetime',
                    ];
                    switch (input('logType')) {
                        case 'Servcie':
                            $controller = lang("客服日志");
                            break;
                        case 'Ctrllog':
                            $controller = lang("控制日志");
                            break;
                    }
                    $filename = "$controller-" . date('YmdHis');
                    $rows =& $result['data'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {
                        $item = [
                            $row['id'],
                            $row['username'],
                            $row['method'],
                            $row['content'],
                            $row['recordtime'],
                        ];
                        $writer->writeSheetRow('sheet1', $item, ['height' => 16, 'halign' => 'center',]);
                        unset($rows[$index]);
                    }
                    unset($row, $item);
                    $writer->writeToStdOut();
                    exit();
                }

        }
        $this->assign('controller', ['Player' => lang('玩家管理'), 'CustomerServiceSystem' => lang('客服系统')]);
        return $this->fetch();
    }


    /**
     * 获取用户的国家代码
     * @param $RoleID
     */
    public function GetCountryByRoleID($RoleID)
    {
        $db = new AccountDB();
        $result = $db->GetCountryByRoleID($RoleID);
        return json($result);
    }


    //批量发送邮件
    public function batchmail()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $title = input('Title', '');
            $start = input('start');
            $end = input('end');
            $status = input('VerifyState');
            $userbatch = new UserBatchMail();

            $where = [];

            if (!empty($title)) {
                $where['title'] = ['like', '%' . $title . '%'];
            }

            if ($status > -1) {
                $where['Status'] = $status;
            }

            if (!empty($start)) {
                $start .= ' 00:00';
                $where['AddTime'] = ['>=', $start];
                if (!empty($end)) {
                    $end .= ' 23:59:59';
                    $where['AddTime'] = ['<=', $end];
                }
            } else {
                if (!empty($end)) {
                    $end .= ' 23:59:59';
                    $where['AddTime'] = ['<=', $end];
                }
            }

            $list = $userbatch->getList($where, $page, $limit, '*', ['Id' => 'desc']);
            foreach ($list as $k => &$v) {
                $v['AddTime'] = date('Y-m-d H:i:s', strtotime($v['AddTime']));
            }
            $count = $userbatch->getCount($where);
            return $this->apiReturn(0, $list, 'success', $count);
        }
        return $this->fetch();
    }


    public function addBatchMail()
    {
        if ($this->request->isAjax()) {
            $title = input('title', '');
            $multiple = input('multiple', 1);
            $descript = input('descript', '');
            $filepath = input('filepath');
            if ($title == '' || $filepath == '') {
                return $this->apiReturn(100, '', lang('请确认表单是否填写完整'));
            }
            if (!is_numeric($multiple)) {
                return $this->apiReturn(100, '', lang('打码倍数需要数值类型'));
            }
            $userbatch = new UserBatchMail();
            $savedata = [
                'Title' => $title,
                'Descript' => $descript,
                'FilePath' => $filepath,
                'Multiple' => $multiple,
                'AddTime' => date('Y-m-d H:i:s', time()),
                'UpdateTime' => date('Y-m-d H:i:s', time()),
                'Status' => 0
            ];
            $ret = $userbatch->add($savedata);
            GameLog::logData(__METHOD__, lang('添加批量彩金邮件') . ',数据:' . json_encode($savedata) . ',操作人：' . session('username'), $ret);
            if ($ret) {
                return $this->apiReturn(0, '', lang('提交成功'));
            } else {
                return $this->apiReturn(100, '', lang('提交失败，请重试'));
            }
        }
        return $this->fetch();
    }


    public function confirmPassword()
    {
        $bid = input('bid', 0);
        if ($this->request->isAjax()) {
            $password = input('password', '');
            if ($bid == 0)
                return $this->apiReturn(100, '', lang('参数错误'));

            if (trim($password) == '') {
                return $this->apiReturn(100, '', lang('请输入密码'));
            }
            $userid = session('userid');
            $user = new userModel();
            $userinfo = $user->getRow(['id' => $userid], 'password,salt');
            $newpass = md5($userinfo['salt'] . $password);
            if ($userinfo['password'] != $newpass) {
                return $this->apiReturn(100, '', '密码错误，请输入正确密码');
            }

            $userbatch = new UserBatchMail();
            $row = $userbatch->getRowById($bid);
            $path = './public' . $row['FilePath'];
            Vendor('PHPExcel.PHPExcel');
            $pos = strrpos($path, '.');
            $suffix = substr($path, $pos);

            if ($suffix == ".xlsx") {
                $objReader = \PHPExcel_IOFactory::createReader('Excel2007');
            } else {
                $objReader = \PHPExcel_IOFactory::createReader('Excel5');
            }
            $obj_PHPExcel = $objReader->load($path, $encode = 'utf-8');  //加载文件内容,编码utf-8
            $excel_array = $obj_PHPExcel->getsheet(0)->toArray();   //转换为数组格式
            array_shift($excel_array);  //删除第一个数组(标题);

            if (empty($excel_array)) {
                return $this->apiReturn(100, '', '该Excel文档未包含任何数据，请核对');
            }

            foreach ($excel_array as $k => $v) {
                $temp = [
                    'Id' => $bid,
                    'title' => $row['Title'],
                    'note' => $row['Descript'],
                    'multiple' => $row['Multiple'] * 10,
                    'roleid' => $v[0],
                    'coin' => $v[1]
                ];
                if ($v[0] > 0 && $v[1] > 0)
                    Redis::lpush('batchgold:role', json_encode($temp));
            }
            $ret = $userbatch->updateById($bid, ['Status' => 1, 'UpdateTime' => date('Y-m-d H:i:s', time())]);
            GameLog::logData(__METHOD__, lang('审核批量彩金邮件') . ',ID:' . $bid . ',' . lang('操作人') . '：' . session('username'), 1);
            return $this->apiReturn(0, '', lang('邮件已经审核成功，进入发送流程'));
        }
        $this->assign('id', $bid);
        return $this->fetch();
    }


    public function delBatchMail()
    {
        $bid = input('bid', 0);
        $userbatch = new UserBatchMail();
        if ($bid == 0)
            return $this->apiReturn(100, '', lang('参数错误'));
        $ret = $userbatch->updateById($bid, ['Status' => 2, 'UpdateTime' => date('Y-m-d H:i:s', time())]);
        GameLog::logData(__METHOD__, lang('作废邮件') . ',ID:' . $bid . ',' . lang('操作人') . '：' . session('username'), 1);
        if ($ret)
            return $this->apiReturn(0, '', lang('邮件已经作废'));
    }


    public function uploadexcel()
    {
        $file = $this->request->file('file');
        $info = $file->validate(['size' => 2097152, 'ext' => 'xls,xlsx'])->move(ROOT_PATH . 'public' . DS . 'gold');
        if ($info) {
            $savepath = $info->getSaveName();
            //验证文件手机号是否正确
            $filepath = ROOT_PATH . 'public' . DS . 'gold' . DS . $savepath;
            if (!file_exists($filepath)) {

                return $this->apiReturn(2, [], lang('Excel文件上传失败'));
            }
            $headurl = '/gold/' . $savepath;
            return $this->apiReturn(0, ['path' => $headurl], lang('上传成功'));
        } else {
            return $this->apiReturn(1, [], $file->getError());
        }
    }


    public function emailquotalist()
    {
        $action = $this->request->param('Action');
        if ($action == 'list') {
            $limit = $this->request->param('limit') ?: 20;
            $users = Db::table('game_user')->where('id', '>', 0)->field('id,username')->order('id', 'asc')->paginate($limit);
            $db = new GameOCDB();
            // $email_db = new DataChangelogsDB();
            $users = $users->toArray();
            foreach ($users['data'] as $key => &$val) {
                $config = $db->getTableObject('T_MailExtraQuota')->where('UserId', $val['id'])->find();
                if (empty($config)) {
                    $val['DailyQuota'] = 0;
                    $val['TotalQuota'] = 0;
                } else {
                    $val['DailyQuota'] = $config['DailyQuota'];
                    $val['TotalQuota'] = $config['TotalQuota'];
                }

                // $val['hasQuotaToday'] = $email_db->getTableObject('T_ProxyMsgLog')
                //                             ->where('uid',$val['id'])
                //                             ->where('RecordType',8)
                //                             ->where('ExtraType','>',0)
                //                             ->where('VerifyState','in','0,1')
                //                             ->whereTime('addtime','>=',date('Y-m-d'))
                //                             ->sum('Amount')?:0;
                // $val['hasQuotaTotal'] = $email_db->getTableObject('T_ProxyMsgLog')
                //                             ->where('uid',$val['id'])
                //                             ->where('RecordType',8)
                //                             ->where('ExtraType','>',0)
                //                             ->where('VerifyState','in','0,1')
                //                             ->sum('Amount')?:0;
                $val['hasQuotaToday'] = $db->getTableObject('T_AdminMailLog')
                    ->where('UserId', $val['id'])
                    ->where('extratype', 'in', '1,7')
                    ->whereTime('AddTime', '>=', date('Y-m-d'))
                    ->sum('Amount') ?: 0;
                $val['hasQuotaToday'] /= bl;
                $val['hasQuotaTotal'] = $db->getTableObject('T_AdminMailLog')
                    ->where('UserId', $val['id'])
                    ->where('extratype', 'in', '1,7')
                    ->sum('Amount') ?: 0;
                $val['hasQuotaTotal'] /= bl;
            }
            $data['list'] = $users['data'];
            $data['count'] = $users['total'];
            return $this->apiJson($data);

        }
        if ($action == 'edit') {
            $id = (int)$this->request->param('id');
            $DailyQuota = $this->request->param('DailyQuota') ?: 0;
            $TotalQuota = $this->request->param('TotalQuota') ?: 0;

            $db = new GameOCDB();
            $has = $db->getTableObject('T_MailExtraQuota')->where('UserId', $id)->find();
            if ($has) {
                $res = $db->getTableObject('T_MailExtraQuota')->where('Id', $has['Id'])->update([
                    'DailyQuota' => $DailyQuota,
                    'TotalQuota' => $TotalQuota,
                ]);
            } else {
                $res = $db->getTableObject('T_MailExtraQuota')->insert([
                    'UserId' => $id,
                    'DailyQuota' => $DailyQuota,
                    'TotalQuota' => $TotalQuota,
                ]);
            }

            if ($res) {
                return $this->apiReturn(0, '', '操作成功');
            } else {
                return $this->apiReturn(1, '', '操作失败');
            }
        }
        return $this->fetch();
    }


    //邮件批量审核
    public function MailMultipleCheck()
    {
        $orderno = input('orderno', '');
        $password = input('password');
        $auth_ids = $this->getAuthIds();
        if (!in_array(10010, $auth_ids)) {
            return $this->failJSON('没有权限,请确认开通审核权限');
        }

        $user_controller = new \app\admin\controller\User();
        $pwd = $user_controller->rsacheck($password);
        if (!$pwd) {
            return $this->failJSON('密码错误');
        }
        $userModel = new userModel();
        $userInfo = $userModel->getRow(['id' => session('userid')]);
        if (md5($userInfo['salt'] . $pwd) !== $userInfo['password']) {
            return $this->failJSON('密码错误');
        }

        if (empty($orderno)) {
            return $this->failJSON('订单号不存在');
        }

        $db = new DataChangelogsDB();
        $where = " id in($orderno) and VerifyState=0";
        $list = $db->getTableList('T_ProxyMsgLog', $where, 1, 100, '*');
        if (empty($list['list'])) {
            return $this->failJSON('未包含未审核订单，请重试！');
        }
        $success_num = 0;
        $faild_num = 0;
        foreach ($list['list'] as $k => $v) {
            $id = $v['id'];
            $res = $this->sendGameMessage('SeMDMailStateChange', [$id, 1]);
            $res = unpack('Lint', $res)['int'];
            GameLog::logData(__METHOD__, lang('审核邮件') . " ID=$id" . lang('服务端状态码') . ":$res" . lang('审核人') . ":" . session('username'), 1);
            if ($res == 0) {
                // 执行统计
                $success_num++;
            } else {
                $faild_num++;
            }
        }
        $gameocdb = new GameOCDB();
        $strtoday = date('Y-m-d', time());
        $gameocdb->runSystemDaySum($strtoday);
        $message = '批量审核提交成功，成功：' . $success_num . ',失败：' . $faild_num;
        return $this->successJSON([], $message);
    }


}