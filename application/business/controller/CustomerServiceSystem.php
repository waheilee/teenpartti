<?php


namespace app\business\controller;

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

    // public function BindAccount()
    // {
    //     $account = input('Account');
    //     $roleid = input('roleid');
    //     $password = input('Password');
    //     if ($account != $password) {
    //         return $this->error('两次密码不一致');
    //     }
    //     return self::ResetPwd();
    //     return $this->success("更新成功");

    //     switch (input('Action')) {
    //         case 'bind':
    //             $db = new AccountDB();
    //             $rows = $db->TAccounts()->GetRow(['AccountName' => input('Account')], 'AccountID');
    //             if (!isset($rows)) {
    //                 $account = input('Account');
    //                 $roleid = input('roleid');
    //                 $rows = $db->UPData(['AccountName' => $account], "AccountID=$roleid");
    //                 GameLog::logData(__METHOD__, "账号绑定 ID=$roleid 新账号$account", 1);
    //                 if ($rows > 0) return $this->success("更新成功");
    //                 return $this->error("更新失败");
    //             }
    //             return $this->error('账号已存在');
    //             break;
    //         case 'pwd':
    //             return self::ResetPwd();
    //             break;
    //         case 'all':
    //             $account = input('Account');
    //             $roleid = input('roleid');
    //             $db = new AccountDB();
    //             $rows = $db->TAccounts()->GetRow(['AccountName' => $account], 'AccountID');
    //             if (!isset($rows)) {
    //                 $rows = $db->UPData(['AccountName' => $account], "AccountID=$roleid");
    //                 $res = $this->sendGameMessage('CMD_MA_RESET_LOGIN_PWD', [$roleid, md5(input('Password'))], "AC");
    //                 $res = unpack('Lcode/', $res);
    //                 if ($res['code'] == 0 && $rows > 0) return $this->success("更新成功");
    //                 GameLog::logData(__METHOD__, lang('账号绑定') . " ID=$roleid " . lang('新账号') . "$account", 1);
    //                 GameLog::logData(__METHOD__, lang('密码修改') . " ID=$roleid" . lang('新密码') . "=" . input('Password'), 1);
    //             }
    //             return $this->error('账号已存在');

    //             break;
    //     }

    // }


    /**客服配置 增删改查        [OM_MasterDB] [T_CustomService_Cfg] **/
    public function CustomerServiceConfig($action = null)
    {

        $db = new MasterDB();
        //$countrycode = input('CountryCode');
//        if($countrycode=='-1')
//            $countrycode ='';
        $data = ["CustomName" => input('CustomName'), "Phone" => input('Phone'), 'VipLabel' => input('VipLabel'),
            "FaceId" => input('FaceID'), "Id" => input('ID'), 'CountryCode' => '', 'CustomTitle' => input('CustomTitle')
        ];
        switch ($action) {
            case "list":
                $result = $db->TCustomServiceCfg()->GetPage();
                return $this->apiJson($result);
                break;
            case  "add": //添加页面
                if ($this->request->isAjax()) {
                    unset($data['Id']);
                    $rows = $db->TCustomServiceCfg()->Insert($data);
                    if ($rows > 0){
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
                    $rows = $db->TCustomServiceCfg()->UPData(['Status'=>$status], "Id=" . $ID);
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
            case 'add':

                if ($this->request->isAjax()) {
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
                    $OperatorId = (new AccountDB())->getTableObject('T_Accounts')->where('AccountID','in',$rolelist)->where('ProxyChannelId','=',session('business_ProxyChannelId'))->find();
                    if (!$OperatorId) {
                        return $this->apiReturn(100, '', '该玩家ID不归属于本业务员');
                    }

                    if ($extratype > 0) {
                        //额度判断
                        $db = new GameOCDB();
                        $email_db = new DataChangelogsDB();
                        $where =' extratype in(1,7) and  replace(Operator,\'biz-\',\'\')
  in(SELECT  LoginAccount  FROM [OM_GameOC].[dbo].[T_ProxyChannelConfig] where OperatorId='.session('business_OperatorId').') or Operator in(SELECT OperatorName  FROM [OM_GameOC].[dbo].[T_OperatorSubAccount] where AccountType=0 and OperatorId='.session('business_OperatorId').')';
                        $config = $db->getTableObject('T_OperatorEmailQuota')->where('OperatorId', session('business_OperatorId'))->find();
                        if (empty($config)) {
                            $DailyQuota = 0;
                            $TotalQuota = 0;
                        } else {
                            $DailyQuota = $config['DailyQuota'];
                            $TotalQuota = $config['TotalQuota'];
                        }
                        $hasQuotaToday = $email_db->getTableObject('T_ProxyMsgLog')
                            ->where($where)
                            ->whereTime('AddTime', '>=', date('Y-m-d'))
                            ->sum('Amount') ?: 0;
                        $hasQuotaToday /= bl;
                        $hasQuotaTotal = $email_db->getTableObject('T_ProxyMsgLog')
                            ->where($where)
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
                    if ($sendobj == 1) {//全发
//                        $delaytime = 0;
//                        if ($sendtype == 1) $delaytime = strtotime($sendtime) - sysTime();
//                        $user_name=session('business_LoginAccount');
//                        $res = $this->sendGameMessage('CMD_MD_SYSTEM_MAILv2', [0, 0, $recordtype, $extratype, $amount, $chargeorder, $WageMul, $delaytime, $VerifyState, $title, $mailtxt, "", $Country,$user_name]);
//                        $res = unpack('Lint', $res)['int'];

                    } else {//用户列表
                        if ((int)$extratype == 1
                            || (int)$extratype == 7)
                            $VerifyState = 0;  //需要审核0 1不需要审核
                        $delaytime = 0;
                        if ($sendtype == 1) $delaytime = strtotime($sendtime) - sysTime();
                        $user_name= 'biz-'.session('business_LoginAccount');
                        $res = $this->sendGameMessage('CMD_MD_SYSTEM_MAILv2', [0, $rolelist, $recordtype, $extratype, $amount, $chargeorder, $WageMul, $delaytime, $VerifyState, $title, $mailtxt, $Notice, $Country,$user_name]);
                        $res = unpack('Lint', $res)['int'];

                    }
                    if ($res == 0) {
                        $db = new GameOCDB();
                        $db->getTableObject('T_AdminMailLog')->insert([
                            'UserId' => session('business_ProxyChannelId'),
                            'Amount' => $amount,
                            'RoleID' => $rolelist,
                            'ExtraType' => $extratype,
                            'AddTime' => date('Y-m-d')
                        ]);
                        return $this->apiReturn(1, '', '邮件已添加成功,等待管理员审核通过');
                    }
                }
                $mailtype = config('mailtype');
                unset($mailtype[7]);
                $extratype = config('extratype');
                unset($extratype[0]);
                unset($extratype[2]);
                unset($extratype[3]);
                unset($extratype[8]);
                unset($extratype[9]);
                unset($extratype[10]);
                $this->assign('mailtype', $mailtype);
                $this->assign('extratype', $extratype);

//                $coutryconfig = new CountryCode();
//                $result = $coutryconfig->getListAll([],'','sortid');
//                foreach ($result as $k=>&$v){
//                    if(!empty($v['CountryName']))
//                        $v['CountryName'] = lang($v['CountryName']);
//                }
//                unset($v);
                //$this->assign('country',$result);
                return $this->fetch('email_add');
            case 'send'://审核邮件
                return ;
                //权限验证
                $password = input('password');
                $userInfo = (new \app\model\GameOCDB)->getTableObject('T_OperatorSubAccount')->where('OperatorId',session('merchant_OperatorId'))->find();
                if (md5($password) !== $userInfo['PassWord']) {
                    return $this->error('密码错误');
                }
                $id = input('ID');
                if ($id > 0) {
                    $res = $this->sendGameMessage('SeMDMailStateChange', [$id, 1]);
                    $res = unpack('Lint', $res)['int'];
                    GameLog::logData(__METHOD__, lang('审核邮件') . " ID=$id" . lang('服务端状态码') . ":$res" . lang('审核人') . ":" . session('business_LoginAccount'), 1);
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
                return;
                $id = input('ID');
                $res = $this->sendGameMessage('SeMDMailStateChange', [$id, 2]);
                $res = unpack('Lint', $res)['int'];
                GameLog::logData(__METHOD__, lang('作废邮件') . "ID=$id" . lang('服务端状态码') . ":$res," . lang('审核人') . ":" . session('business_LoginAccount'), 1);
                if ($res == 0) return $this->success('审核成功');
                else return $this->error('审核失败');
            case 'back':
                retutn;
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
//                    $auth_ids = $this->getAuthIds();
//                    if (!in_array(10008, $auth_ids)) {
//                        return $this->apiReturn(1, '', '没有权限');
//                    }
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




    public function emailquotalist()
    {
        $action = $this->request->param('Action');
        if ($action == 'list') {
            $limit = $this->request->param('limit') ?: 15;
            $users = Db::table('game_user')->where('id', '>', 0)->field('id,username')->paginate($limit);
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
}