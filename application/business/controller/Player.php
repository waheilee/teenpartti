<?php

namespace app\business\controller;

use app\admin\controller\traits\getSocketRoom;
use app\admin\controller\traits\search;
use app\common\Api;
use app\common\GameLog;
use app\model;
use app\model\AccountDB;
use app\model\AreaMsgRightSwitch;
use app\model\GameOCDB;
use app\model\UserDB;
use app\model\Viplevel;
use app\model\UserPayWay;
use app\model\UserProxyInfo;
use app\model\User as userModel;
use redis\Redis;
use socket\QuerySocket;
use socket\sendQuery;
use think\Exception;
use think\Db;
use think\log\driver\Socket;
use XLSXWriter;


class Player extends Main
{
    use getSocketRoom;
    use search;


    private $socket = null;

    public function __construct()
    {
        parent::__construct();
        $this->socket = new QuerySocket();
    }



    /**
     * 所有玩家
     */
    public function all()
    {
        if (1) {
            //save_log('ceshi_all', explode(' ', microtime())[1]);
            $startTime    = input("startTime") ? input("startTime") : null;
            $endTime      = input("endTime") ? input("endTime") : null;
            $roleId       = input('roleid', '');
            $isdisable    = intval(input('isdisable', -1));
            $orderby      = input('orderfield', 'RegisterTime');
            $ordertype    = input('ordertype', 'desc');
            $account      = trim(input('account')) ? trim(input('account')) : '';
            $ipaddr       = trim(input('lastIP')) ? trim(input('lastIP')) : '';
            $usertype     = intval(input('usertype', -1));
            $online       = intval(input('isonline', -1));
            $packID       = input('PackID', -1);
            $mobile       = input('mobile', '');
            $ip           = input('ip', '');
            $bankcard     = input('bankcard', '');
            $bankusername = input('bankusername', '');
            $upi          = input('upi', '');
            $proxyId      = input('proxyId', '');
            $isControll   = input('iscontroll', '');
            $isbind       = input('isbind',-1);

            $minrecharge  = input('minrecharge', '');
            $maxrecharge  = input('maxrecharge', '');
            $VipLv        = input('VipLv', '');
            $isrecharge   = input('isrecharge', '');

            $where = '';

            if(config('is_portrait')==1){
                $where .=" AND  GmType<>0";
            }
            if ($roleId != '') {
                $roleId = str_replace('，', ',', $roleId);
                $roleId = trim($roleId, ',');
                $where .= " AND  AccountID in(" . $roleId . ")";
            }
            if (!empty($startTime)) {
                $where .= " AND RegisterTime >='$startTime'";
            }
            if (!empty($endTime)) {
                $where .= " AND RegisterTime <='$endTime'";
            }
            if ($isdisable >= 0) $where .= " AND  locked=$isdisable";
            if (!empty($account)) $where .= " and  (AccountName like '%$account%' or mobile like '%$account%') ";
            if (!empty($ipaddr)) $where .= " and  lastloginip like '%$ipaddr%'";
            if ($usertype >= 0) $where .= " and  gmtype =$usertype";
            if (intval($packID) >0){
                $packID = intval($packID);
                $where .= " AND OperatorId in ($packID)";
            }
            if (!empty($mobile)) $where .= " and mobile like '%$mobile%'";
            if (!empty($ip)) $where .= " and regip like '%$ip%'";
            if (!empty($bankcard)) $where .= " and BankCardNo like '%$bankcard%'";
            if (!empty($bankusername)) $where .= " and UserName like '%$bankusername%'";
            if (!empty($upi)) $where .= " and UPICode like '%$upi%'";
            if (!empty($isControll)) {
                if ($isControll == 1){
                    $isControll = 96;
                }
                else{
                    $isControll = 0;
                }
                $where .= " and SystemRight='$isControll' ";
            }

            if($isbind>-1){
                if ($isbind == 1)
                    $where .= " and mobile<>'' ";
                else if ($isbind == 0){
                    $where .= " and mobile='' ";
                }
            }

            if ($minrecharge != '') {
                $where .= " and TotalDeposit>='$minrecharge' ";
            }
            if ($maxrecharge != '') {
                $where .= " and TotalDeposit<='$maxrecharge' ";
            }
            if ($isrecharge != '') {
                if ($isrecharge == 1) {
                    $where .= " and TotalDeposit>0 ";
                }
                if ($isrecharge == 0) {
                    $where .= " and TotalDeposit=0 ";
                }
            }
            if ($VipLv != '') {
                if ($VipLv == 0) {
                    $where .= " and (VipLv='$VipLv' or VipLv='') ";
                } else {
                    $where .= " and VipLv='$VipLv' ";
                }
            }
            $all_ProxyChannelId = $this->getXbusiness(session('business_ProxyChannelId'));
            $all_ProxyChannelId[] = session('business_ProxyChannelId');
            $where .= " and ProxyChannelId in (".implode(',', $all_ProxyChannelId).")";
            $gameOCDB = new GameOCDB();
            if(config('is_portrait')==1){
                $default_Proxy = $gameOCDB->getTableObject('T_ProxyChannelConfig')->where('isDefault', 1)->find() ?: [];
                $default_Proxy['ProxyChannelId'] = $default_Proxy['ProxyChannelId'] ?? '';
                $default_Proxy['AccountName'] = $default_Proxy['AccountName'] ?? '';
                $default_ProxyId = $default_Proxy['ProxyChannelId'];
                if (!empty($proxyId)) {
                    if ($default_Proxy['ProxyChannelId'] == $proxyId) {
                        $where .= " and (ProxyChannelId=0 or ProxyChannelId='$proxyId')";
                    } else {
                        $where .= " and ProxyChannelId='$proxyId'";
                    }
                }
            } else{

                $default_ProxyId = $gameOCDB->getTableObject('T_ProxyChannelConfig')->where('isDefault', 1)->value('ProxyId') ?: '';

                if (!empty($proxyId)) {
                    $proxy_roleid = $gameOCDB->getProxyChannelConfig()->getValueByTable('ProxyId=\'' . $proxyId . '\'', 'RoleID');
                    if ($default_ProxyId == $proxyId) {
                        $where .= " and (ParentID=0 or ParentIds like '%,$proxy_roleid' or ParentIds='$proxy_roleid')";
                    } else {
                        if (!empty($proxy_roleid)) {
                            $where .= " and (ParentIds like '%,$proxy_roleid' or ParentIds='$proxy_roleid')";
                        } else {
                            $where .= " and ParentID=" . $proxyId;
                        }
                    }
                }
            }
            //返回在线列表给前端
            if ($online == 0) {
                /**获取在在线列表*/
                $online = $this->GetOnlineUserlist2()['total'];
                //save_log('error',json_encode($online));
                if ($online) {
                    $online = implode(',', $online);
                    $where .= "And AccountID in($online)";
                } else {
                    $where .= "And 1=2";
                }
            }
            //save_log('ceshi_all', explode(' ', microtime())[1]);
        }
        $field = "AccountID ID,MachineCode,Mobile,countryCode,AccountName,Locked,LoginName,GmType,RegisterTime,LastLoginIP,LastLoginTime,TotalDeposit,TotalRollOut,Money,RegIP,VipLv,SystemRight,ParentIds,ParentID,OperatorId,ISNULL(ProxyBonus,0) as ProxyBonus";
        $file_roleid = 'RoleId';
        if(config('is_portrait')==1){
            $field .=',ProxyChannelId';
            $file_roleid ='ProxyChannelId';
        }
        else{
            $field.=',UserName,BankName,BankCardNo,IFSCCode,UPICode';
        }
        $db = new UserDB();
        $gameoc = new GameOCDB();
        $ProxyChannelConfig = $gameoc->getTableObject('T_ProxyChannelConfig')->column('*', $file_roleid);
        //$ProxyChannel = $gameoc->getTableList('T_ProxyChannelConfig', '', 1, 1000, '');
        switch (input('Action')) {
            case 'list':
                $result = $db->TViewAccount()->GetPage($where, "$orderby $ordertype", $field);
                foreach ($result['list'] as &$item) {
                    if (!empty($item['Mobile'])) {
                        // $item['Mobile'] = substr_replace($item['Mobile'], '**', -2);
                        if (substr($item['Mobile'], 0, 2) == '91') {
                            $item['quhao'] = '91';
                            $item['phone'] = substr($item['Mobile'], 2);
                        } else {
                            $item['quhao'] = '';
                            $item['phone'] = $item['Mobile'];
                        }
                    } else {
                        $item['quhao'] = '91';
                        $item['phone'] = '';
                    }

                    $proxy = [];
                    if(config('is_portrait')==1){
                        $item['proxyId'] = $item['ProxyChannelId'] ?: $default_ProxyId;
                    } else{
                        if ($item['ParentID'] != 0) {
                            $item['proxyId'] = $item['ParentID'];
                            if ($item['ParentID'] < 10000000) {
                                $proxy = $ProxyChannelConfig[$item['ParentID']] ?? [];
                                if ($proxy) {
                                    $item['proxyId'] = $proxy['ProxyId'];
                                }
                            }
                        } else {
                            $item['proxyId'] = $default_ProxyId;
                        }
                    }

                    ConVerMoney($item['Money']);
                    ConVerMoney($item['ProxyBonus']);
                    //是否在线
                    $item['is_oline'] = $this->sendGameMessage('CMD_MD_USER_STATE', [$item['ID']], "DC", 'returnComm')['iResult'] ?? 0;
                }
                //save_log('ceshi_all', explode(' ', microtime())[1]);
                return $this->apiJson($result);

                break;
            case 'onlinelist':
                return $this->apiJson(['list' => $this->GetOnlineUserlist2()['total']]);
                break;
            case 'exec':
                $field = "AccountID ID,Mobile,AccountName,LoginName,RegisterTime,LastLoginIP,TotalDeposit,TotalRollOut,Money,ProxyBonus";
                $result = $db->TViewAccount()->GetPage($where, "$orderby $ordertype", $field);
                foreach ($result['list'] as &$item) {
                    ConVerMoney($item['Money']);
//                    ConVerMoney($item['TotalRollOut']);
//                    ConVerMoney($item['TotalDeposit']);
                }
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
                    $header_types = [
                        lang('ID') => 'integer',//ID
                        // lang('机器码') => 'string',//MachineCode
                        lang('手机号') => 'string',//AccountName
                        lang('账号') => 'string',//AccountName
//                        '是否禁用' => "string",//Locked
                        lang('昵称') => 'string',//LoginName
//                        '登陆类型' => "string",//GmType
                        lang('注册时间') => "datetime",//RegisterTime
                        lang('最后登录IP') => "string",//LastLoginIP
                        lang('总充值') => "0.00",//TotalDeposit
                        lang('总转出') => '0.00',//TotalRollOut
                        lang('剩余金币') => '0.00',//Money
                        lang('代理账户') => '0.00'//Money
                    ];
                    $filename = lang('用户记录') . '-' . date('YmdHis');
                    $this->GetExcel($filename, $header_types, $result['list']);
                }
                break;
        }


        $usertype = config('usertype');
        unset($usertype[4]);
        $this->assign('usertype', $usertype);
        $selectData = $this->getRoomList();
        $this->assign('selectData', $selectData);

        $this->assign('add_dm_auth', '0');
        $this->assign('wipe_dm_auth', '0');
        return $this->fetch();
    }

    //查询角色是否锁定
    public function getRoleStatus()
    {
        $RoleID = input('roleid') ? input('roleid') : '';
        $socket = new QuerySocket();
        $result = $socket->searchRoleStatus($RoleID);
        //4锁定
        if ($result === 3) {
//                ob_clean();
            return $this->apiReturn(3, [], '用户未被锁定!');
        } else {
            return $this->apiReturn(4, [], '用户已被锁定');
        }
    }

    //更新角色状态
    public function updateRoleStatus()
    {
        if ($this->request->isAjax()) {
            $roleid = intval(input('roleid')) ? intval(input('roleid')) : '';
            $day = intval(input('day')) ? intval(input('day')) : 300;
            $roleStatus = $this->getRoleStatus()->getdata();
            $socket = new QuerySocket();
            if ($roleStatus['code'] === 4) {
                //解锁角色
                $result = $socket->unlockRoleStatus($roleid);
                if ($result["iResult"] == 0) {
                    ob_clean();
                    GameLog::logData(__METHOD__, $this->request->request(), 1);
                    return $this->apiReturn(0, [], '角色解锁成功!');
                } else {
                    GameLog::logData(__METHOD__, $this->request->request(), 0);
                    return $this->apiReturn(1, [], '角色解锁失败');
                }
            } else {
                //锁定角色lockRoleStatus

                $result = $socket->lockRoleStatus($roleid, $day);
                if ($result["iResult"] == 0) {
                    ob_clean();
                    GameLog::logData(__METHOD__, $this->request->request(), 1);
                    return $this->apiReturn(0, [], '角色锁定成功!');
                } else {
                    GameLog::logData(__METHOD__, $this->request->request(), 0);
                    return $this->apiReturn(1, [], '角色锁定失败');
                }

            }

        }

    }

    //玩家强退
    public function forceQuit()
    {
        if ($this->request->isAjax()) {
            $roleId = input('roleid') ? input('roleid') : '';
            if (!$roleId) {
                return json(['code' => 1, 'msg' => '请输入正确的玩家ID']);
            }
            //log记录
            $res = unpack('LiResult/', $this->sendGameMessage('CMD_MD_FORCE_QUIT_ROL', [$roleId]));
            $request = $this->request->request();
            array_push($request, $res);
//            $res = $this->socket->setForceQuit($roleId);
            GameLog::logData(__METHOD__, $request, (isset($res['iResult']) && $res['iResult'] == 0) ? 1 : 0, $res);
            if (isset($res['iResult']) && $res['iResult'] == 0) {
                return $this->apiReturn($res['iResult'], [], '操作成功');
            } else {
                return $this->apiReturn(1, [], '操作失败');
            }
        }
        return $this->fetch();
    }


    /**
     * 玩家详情(玩家列表点击)
     */
    public function playerDetail()
    {
        $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
        if ($roleId > 0) {
            //账号表数据
            $db = new model\UserDB();
            $user = $db->TViewAccount()->GetRow(["AccountID" => $roleId]);
            if (empty($user)) {
                echo lang("用户不存在");
                return;
            }
//            halt($user);

//            $user['PayOutAll'] = ConverMoney($user['PayOutAll']);
//            $user['PayOut'] = ConverMoney($user['PayOut']);

            $userProxyInfo = new UserProxyInfo();
            $proxyinfo = $userProxyInfo->getDataRow(['RoleID' => $roleId], 'ParentID,ParentIds,AbleProfit,TotalProfit,RoleID,ProxySwitch');

            $user['parentId'] = $proxyinfo['ParentID'] ?: 0;
            $gameOCDB = new GameOCDB();
            $ParentIds = array_filter(explode(',', $proxyinfo['ParentIds']));
            $proxy = [];

            if(config('is_portrait')==1){
                if ($user['ProxyChannelId']) {
                    $proxy = $gameOCDB->getProxyChannelConfig()->GetRow(['ProxyChannelId' => $user['ProxyChannelId']], '*', 'ProxyChannelId desc') ?: [];
                }
            } else {
                if (!empty($ParentIds)) {
                    $proxy = $gameOCDB->getProxyChannelConfig()->GetRow(['RoleID' => $ParentIds[0]], '*', 'RoleID desc') ?: [];
                }
            }

            $lotterypoint = (new UserDB())->getTableObject('T_RoleDailyData')->where('RoleID',$roleId)->value('LotterCostRunning');
            if(!empty($lotterypoint)){
                $lotterypoint= bcdiv($lotterypoint,bl,0);
            }
            else{
                $lotterypoint =0;
            }

            $user['lotterypoint'] =$lotterypoint;
            $channelId = $user['OperatorId'];
            $channeldata = $gameOCDB->getTableRow('T_OperatorSubAccount',['OperatorId'=>$channelId],'OperatorName');
            $user['proxyName'] = $proxy['AccountName'] ?? 'None';
            $user['ChannelName'] = $channeldata['OperatorName'] ?? 'None';
            if (!empty($user['Mobile'])) {
                $user['Mobile'] = substr_replace($user['Mobile'], '**', -2);
            }
            ConVerMoney($user['Money']);
            ConVerMoney($user['PayOut']);
            ConVerMoney($user['PayOutAll']);
            ConVerMoney($user['TotalWin']);
            ConVerMoney($user['TotalRunning']);
            ConVerMoney($user['ProxyBonus']);

            if ($user['VipLv'] == null || $user['VipLv'] == '') {
                $user['VipLv'] = 0;
            }

            if (!empty($user)) {
                $this->assign('usreid', $roleId);
                $this->assign("user", $user);
            }

            if (!empty($proxyinfo)) {
                conVerMoney($proxyinfo['AbleProfit']);
                conVerMoney($proxyinfo['TotalProfit']);

            } else {
                $proxyinfo['AbleProfit'] = 0;
                $proxyinfo['TotalProfit'] = 0;
                $proxyinfo['RoleID'] = $roleId;
            }
            $proxy_switech = 1;
            if (isset($proxyinfo['ProxySwitch'])) {
                $proxy_switech = $proxyinfo['ProxySwitch'];
            }
            $proxyinfo['ProxySwitch'] = $proxy_switech;
            $this->assign('proxyinfo', $proxyinfo);

        }


        $userpayway = new UserPayWay();
        $payway = ['UserName' => '', 'BankCardNo' => '', 'roleid' => $roleId, 'BankName' => '', 'IFSCCode' => '', 'Mail' => ''];
        $upiway = $userpayway->getDataRow(['roleid' => $roleId, 'PayWayType' => 1], '*');
        $bankway = $userpayway->getDataRow(['roleid' => $roleId, 'PayWayType' => 2], '*');
        $pixway = $userpayway->getDataRow(['roleid' => $roleId, 'PayWayType' => 3], '*');
        if (empty($upiway)) {
            $upiway = $payway;
        }
        if (empty($bankway)) {
            $bankway = $payway;
        }
        if (empty($pixway)) {
            $pixway = $payway;
        }
        $bankcode = new model\BankCode();
        if (config('app_type') == 2) {
            $banklist=[
                'CPF','CNPJ','EMAIL','PHONE','EVP'
            ];
        } else if(config('app_type') == 3){
            $banklist=[

            ];
        } else {
            $banklist = $bankcode->getListAll();
        }

        $this->assign('bind_auth', '0');


        $bankdb = new model\BankDB();
        $strsql = 'select isnull(count(1),0) as cnt from UserDrawBack(nolock) where status=100 and  AccountID=' . $roleId;
        $draw_result = $bankdb->getTableQuery($strsql);

        $strsql = 'SELECT  count(1) as cnt  FROM [CD_UserDB].[dbo].[T_UserTransactionChannel](nolock) where AccountID=' . $roleId;
        $pay_result = $db->getTableQuery($strsql);


        $coinchangeType = config('bank_change_type');
        $this->assign('changeType', $coinchangeType);
        $this->assign('drawcount', $draw_result[0]['cnt']);
        $this->assign('paytimes', $pay_result[0]['cnt']);
        $this->assign('mytip', '22');
        $this->assign('upiway', $upiway);
        $this->assign('bankway', $bankway);
        $this->assign('banklist', $banklist);
        $this->assign('pixway',$pixway);


        if (config('app_name') == 'UWPLAY' || config('app_name') == 'evgwin') {
            return $this->fetch('player_detail_s');
        } else {
            return $this->fetch();
        }
    }

    public function getRoleWage()
    {
        $roleid = input('roleid', 0, 'intval');
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 15;
            if ($roleid > 0) {
                $UserWageRequire = new model\UserWageRequire();
                $list = $UserWageRequire->getList(['roleid' => $roleid], $page, $limit, '', 'Id desc');
                foreach ($list as $k => &$v) {
                    $v['ChangeMoney'] = FormatMoney($v['ChangeMoney']);
                    $v['NeedWageRequire'] = FormatMoney($v['NeedWageRequire']);
                    $v['CurWageRequire'] = FormatMoney($v['CurWageRequire']);
                    $v['EnableWithdrawMoney'] = FormatMoney($v['EnableWithdrawMoney']??0);
                    $v['FreezonMoney'] = FormatMoney($v['FreezonMoney']??0);
                    $v['difference'] =bcsub($v['NeedWageRequire'], $v['CurWageRequire'],2);
                    $v['AddTime'] = date('Y-m-d H:i:s', strtotime($v['AddTime']));
                }
                unset($v);
                $count = $UserWageRequire->getCount(['roleid' => $roleid]);
                return $this->apiReturn(0, $list, 'success', $count);
            } else {
                return $this->apiReturn(0, [], 'success', 0);
            }
        }


    }


    public function getloginLog()
    {
        $roleid = input('roleid', 0, 'intval');
        $page = input('page', 1, 'intval');
        $limit = input('limit', 15, 'intval');
        $db = new UserDB();
        $data = $db->getTableList("[CD_DataChangelogsDB].[dbo].[T_LoginLogs]", "RoleID=" . $roleid, $page, $limit, '*', 'AddTime desc');
        return $this->apiJson($data);
    }

    public function getSameIp()
    {
        $limit = input('limit') ?: 20;
        $roleid = input('roleid', 0, 'intval');
        $db = new UserDB();
        $where = 'AccountID=' . $roleid;
        $RegIP = $db->getTableObject('[CD_Account].[dbo].[T_Accounts]')
            ->where('AccountID', $roleid)
            ->value('RegIP');
        $data = $db->getTableObject('[CD_Account].[dbo].[T_Accounts]')
            ->where('RegIP', $RegIP)
            ->where('GmType','<>', 0)
            ->field('AccountID,AccountName,RegisterTime,RegIP,LastLoginIP,LastLoginTime')
            ->order('RegisterTime desc')
            ->paginate($limit)
            ->toArray();
        // $loginips = array_column($loginips, 'ClientIP');
        // $subQuery = $db->getTableObject('[CD_DataChangelogsDB].[dbo].[T_LoginLogs](NOLOCK)')
        //     ->where('ClientIP', 'in', $loginips)
        //     ->field('RoleID,ClientIP,max(addtime) addtime')
        //     ->group('RoleID,ClientIP')
        //     ->buildSql();
        // $data = $db->getTableObject('[CD_UserDB].[dbo].[View_Accountinfo](NOLOCK)')->alias('b')
        //     ->join("$subQuery a",'b.AccountID=a.RoleID')
        //     ->where('a.ClientIP', 'in', $loginips)
        //     ->field('b.AccountID,b.AccountName,b.RegisterTime,b.RegIP,a.ClientIP LastLoginIP,a.addtime LastLoginTime')
        //     ->order('LastLoginTime desc')
        //     ->paginate($limit)
        //     ->toArray();
        return $this->apiJson(['list' => $data['data'], 'count' => $data['total']]);
    }

    public function addCommnet()
    {
        $roleid = input('roleid', 0, 'intval');
        $type = input('type', 1, 'intval');
        $comment = input('comment');
        if (empty($comment)) {
            return $this->apiReturn(1, '', '备注不能为空');
        }
        $db = new GameOCDB();
        $res = $db->setTable('T_PlayerComment')->Insert([
            'roleid' => $roleid,
            'adminid' => session('business_ProxyChannelId'),
            'type' => 1,
            'opt_time' => date('Y-m-d H:i:s'),
            'comment' => $comment
        ]);
        if ($res) {
            return $this->apiReturn(0, '', '操作成功');
        } else {
            return $this->apiReturn(1, '', '操作成功');
        }
    }

    public function getCommentList()
    {
        $roleid = input('roleid', 0, 'intval');
        $page = input('page', 1, 'intval');
        $limit = input('limit', 15, 'intval');
        $type = input('type', 1, 'intval');
        $db = new GameOCDB();
        // $where = '1=1';
        // if ($type == 1) {
        //     $where = "RoleID=".$roleid.' and type='.$type;
        // } elseif ($type == 3 || ) {
        if (empty($roleid)) {
            $where = 'type=' . $type;
        } else {
            $where = "RoleID=" . $roleid . ' and type=' . $type;
        }
        // }
        $data = $db->getTableList("T_PlayerComment", $where, $page, $limit, '*', 'opt_time desc');
        foreach ($data['list'] as $key => &$val) {
            $val['comment'] = str_replace('设置上级ID', lang('设置上级ID'), $val['comment']);
            $val['comment'] = str_replace('解绑', lang('解绑'), $val['comment']);
            $val['comment'] = str_replace('新增打码值', lang('新增打码值'), $val['comment']);
            $val['comment'] = str_replace('设置上级邀请码', lang('设置上级邀请码'), $val['comment']);
            $val['admin_name'] = Db::table('game_user')->where('id', $val['adminid'])->value('username');
            if (empty($val['admin_name'])) {
                $datarow = $db->getTableRow('T_OperatorSubAccount','OperatorId='.$val['adminid'],'OperatorName');
                if(!empty($datarow)){
                    $val['admin_name'] = '渠道-'.$datarow['OperatorName'];
                }
            }
            if (empty($val['admin_name'])) {
                $rowdata = $db->getTableRow('T_ProxyChannelConfig','ProxyChannelId='.$val['adminid'],'AccountName');
                if(!empty($rowdata)){
                    $val['admin_name'] = '业务员-'.$rowdata['AccountName'];
                }
            }
        }
        return $this->apiJson($data);
    }

    //玩家等级数据
    public function getProxyLvData()
    {
        $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
        $data = [];
        if ($roleId > 0) {
            $db = new UserDB();
            $field = 'ProxyId,Lv1PersonCount,Lv1Deposit,Lv1DepositPlayers,Lv2PersonCount,Lv2Deposit,Lv2DepositPlayers,Lv3PersonCount,Lv3Deposit,Lv3DepositPlayers,Lv1WithdrawAmount,Lv2WithdrawAmount,Lv3WithdrawAmount,Lv1WithdrawCount,Lv2WithdrawCount,Lv3WithdrawCount';
            $row = $db->getTableRow('T_ProxyCollectData', 'ProxyId=' . $roleId, $field);

            $avarage1 = 0;
            $avarage2 = 0;
            $avarage3 = 0;
            if ($row['Lv1DepositPlayers'] > 0) {
                $avarage1 = bcdiv($row['Lv1Deposit'], $row['Lv1DepositPlayers'], 2);
            }
            $avarage1 =
            $levle1 = [
                'level' => lang('等级1'),
                'person' => $row['Lv1PersonCount'],
                'chargenum' => $row['Lv1DepositPlayers'],
                'amount' => $row['Lv1Deposit'],
                'avarage' => $avarage1,
                'withdrawCount' => $row['Lv1WithdrawCount'],
                'withdrawAmount' => $row['Lv1WithdrawAmount'] / bl,
            ];
            if ($row['Lv2DepositPlayers'] > 0) {
                $avarage2 = bcdiv($row['Lv2Deposit'], $row['Lv2DepositPlayers'], 2);
            }
            $levle2 = [
                'level' => lang('等级2'),
                'person' => $row['Lv2PersonCount'],
                'chargenum' => $row['Lv2DepositPlayers'],
                'amount' => $row['Lv2Deposit'],
                'avarage' => $avarage2,
                'withdrawCount' => $row['Lv2WithdrawCount'],
                'withdrawAmount' => $row['Lv2WithdrawAmount'] / bl,
            ];
            if ($row['Lv3DepositPlayers'] > 0) {
                $avarage3 = bcdiv($row['Lv3Deposit'], $row['Lv3DepositPlayers'], 2);
            }
            $levle3 = [
                'level' => lang('等级3'),
                'person' => $row['Lv3PersonCount'],
                'chargenum' => $row['Lv3DepositPlayers'],
                'amount' => $row['Lv3Deposit'],
                'avarage' => $avarage3,
                'withdrawCount' => $row['Lv3WithdrawCount'],
                'withdrawAmount' => $row['Lv3WithdrawAmount'] / bl,
            ];
            array_push($data, $levle1);
            array_push($data, $levle2);
            array_push($data, $levle3);
        }
        return $this->successJSON($data);
    }


    public function getXbusiness($ProxyChannelIds){
        static $result = [];
        $xChannelIds = (new \app\model\GameOCDB())->getTableObject('T_ProxyChannelConfig')->where('pid','in',$ProxyChannelIds)->field('ProxyChannelId')->select();
        if (empty($xChannelIds)) {
            return $result;
        } else {
            $xChannelIds = array_column($xChannelIds, 'ProxyChannelId');
            $result = array_unique(array_merge($result,$xChannelIds));
            return $this->getXbusiness($xChannelIds);
        }
    }



    /**
     * Notes: 金币日志
     * @return mixed
     */
    public function coinlog2()
    {
        $changeType = config('bank_change_type');
        $gameRoomList = $this->GetRoomList();
        switch (input('Action')) {
            case 'list':
                $db = new GameOCDB();
                $result = $db->GetGoldRecord($gameRoomList);
                //$sumdata = $db->GetGoldRecordSum();
                $result['other'] =[];// $sumdata;
                return $this->apiJson($result);
            case 'exec':
                $db = new  GameOCDB();
                $result = $db->GetGoldRecord($gameRoomList);
                $outAll = input('outall', false);
                if ((int)input('exec', 0) == 0) {
                    if ($result['count'] == 0) {
                        $result = ["count" => 0, "code" => 1, 'msg' => lang("没有找到任何数据,换个姿势再试试?")];
                    }
                    if ($result['count'] >= 5000 && $outAll == false) {
                        $result = ["code" => 2, 'msg' => lang("数据超过5000行是否全部导出?<br>只能导出一部分数据.</br>请选择筛选条件,让数据少于5000行<br>当前数据一共有") . $result['count'] . lang("行")];
                    }
                    unset($result['list']);
                    return $this->apiJson($result);
                }
                //导出表格
                if ((int)input('exec', 0 == 1 && $outAll = true)) {
                    $header_types = [
                        lang('玩家ID') => 'integer',//RoleID
                        lang('类型') => 'string',//ChangeName
                        lang('操作金币') => "0.00",//Money
                        lang('操作后金币数') => "0.00",//LastMoney
                        lang('时间') => "datetime",//AddTime
                        lang('房间名') => "string",//AddTime
                        lang('机器码') => "string",//AddTime
                    ];
                    $filename = lang('金币日志') . '-' . date('YmdHis');
                    $rows =& $result['list'];
//                    halt($rows[0]);
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {
                        $item = [
                            $row['RoleID'],
                            $row['ChangeName'],
                            $row['Money'],
                            $row['LastMoney'],
                            $row['AddTime'],
                            $row['RoomName'],
                            '',
                        ];
                        $writer->writeSheetRow('sheet1', $item, ['height' => 16, 'halign' => 'center',]);
                        unset($rows[$index]);
                    }
                    unset($row, $item);
                    $writer->writeToStdOut();
                    exit();
                }

                break;
        }

        $usertype = config('usertype');
        $this->assign('usertype', $usertype);
        $this->assign('changeType', $changeType);
        $this->assign('RoomList', json_encode($this->GetRoomList()));
        return $this->fetch();
    }


    /**
     * Notes: 游戏日志（单独菜单）
     * @return mixed
     */
    public function gamelog2()
    {
        $roleId = input('roleid', '');
        $this->assign('roleid', $roleId);
        switch (input('Action')) {
            case 'list':
                $db = new GameOCDB();
                $result = $db->GetGameRecord(true);
                $sumdata = $db->GetGameRecordSum(true);
                $result['other'] = $sumdata;
                return $this->apiJson($result, true);
            case 'exec':

                $db = new  GameOCDB();
                $result = $db->GetGameRecord();
                $outAll = input('outall', false);
                if ((int)input('exec', 0) == 0) {
                    if ($result['count'] == 0) {
                        $result = ["count" => 0, "code" => 1, 'msg' => lang("没有找到任何数据,换个姿势再试试?")];
                    }
                    if ($result['count'] >= 5000 && $outAll == false) {
                        $result = ["code" => 2, 'msg' => lang("数据超过5000行是否全部导出?<br>只能导出一部分数据.</br>请选择筛选条件,让数据少于5000行<br>当前数据一共有") . $result['count'] . lang("行")];
                    }
                    unset($result['list']);
                    return $this->apiJson($result);
                }
                //导出表格
                if ((int)input('exec', 0) == 1 && $outAll = true) {
                    $header_types = [
                        lang('玩家ID') => 'integer',
                        lang('房间名') => 'string',
                        lang('输赢情况') => "0.00",
                        lang('免费游戏') => 'string',
                        lang('下注金额') => "0.00",
                        lang('输赢金币') => "0.00",
                        lang('中奖金币') => "0.00",
                        lang('上局金币') => "0.00",
                        lang('当前金币') => "0.00",
                        lang('创建时间') => 'datetime'
                    ];
                    $filename = lang('游戏日志') . '-' . date('YmdHis');
                    $rows =& $result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {
                        $gamestate = '';
                        switch ($row['ChangeType']) {
                            case 0:
                                $gamestate = lang('赢');
                                break;
                            case 1:
                                $gamestate = lang('输');
                                break;
                            case 2:
                                $gamestate = lang('和');
                                break;
                            case 3:
                                $gamestate = lang('逃');
                                break;
                        }
                        $item = [
                            $row['RoleID'], $row['RoomName'], $gamestate, $row['FreeGame'],
                            $row['GameRoundRunning'], $row['Money'], $row['AwardMoney'],
                            $row['PreMoney'], $row['LastMoney'], $row['AddTime']
                        ];
                        $writer->writeSheetRow('sheet1', $item, ['height' => 16, 'halign' => 'center',]);
                        unset($rows[$index]);
                    }
                    unset($row, $item);
                    $writer->writeToStdOut();
                    exit();
                }
        }
        $selectData = $this->getRoomList();
        $this->assign('selectData', $selectData);
        return $this->fetch();
    }

    public function gameDailyReport()
    {
        switch (input('Action')) {
            case 'list':
                $roleid = $this->request->param('roleid');
                $start = $this->request->param('start') ?: date('Y-m-d 00:00:00');
                $end = $this->request->param('end') ?: date('Y-m-d 23:59:59');
                $page = $this->request->param('page') ?: 1;
                $limit = $this->request->param('limit') ?: 20;
                $orderby = input('orderfield');
                $ordertype = input('ordertype');
                $where = '';
                $where_total = '';
                if ($roleid != '') {
                    $where .= ' and a.RoleID=' . $roleid;
                    $where_total .= ' and RoleID=' . $roleid;
                }
                $where .= ' and a.AddTime>=\'' . $start . '\' and a.AddTime<=\'' . $end . '\'';
                $where_total .= " and AddTime>=''$start'' and AddTime<=''$end''";


                $db = new GameOCDB();
                $userProxyInfo = new UserProxyInfo();
                $ProxyChannelConfig = (new GameOCDB())->getTableObject('T_ProxyChannelConfig')->column('*', 'ProxyChannelId');
                $field = 'a.RoleId,b.ParentID ,ServerID,CONVERT(varchar(30),addtime,112) as AddTime,sum(tax) as Tax,sum(GameRoundRunning) as GameRoundRunning,sum(Money) as TotalWin';
                $join = 'left join [CD_UserDB].[dbo].[T_UserProxyInfo] as b on a.RoleID=b.RoleID';
                $all_ProxyChannelId = $this->getXbusiness(session('business_ProxyChannelId'));
                $all_ProxyChannelId[] = session('business_ProxyChannelId');

                $join .= " left join [CD_Account].[dbo].[T_Accounts](nolock) as C on C.AccountID=a.RoleId  and C.ProxyChannelId in(".implode(',',$all_ProxyChannelId).")";
                $group = ' ServerID,CONVERT(varchar(30),addtime,112),a.RoleID,b.ParentID';
                $startdate = date('Y-m-d', strtotime($start));
                $enddate = date('Y-m-d', strtotime($end));
                $order = 'AddTime desc';
                if ($orderby && $ordertype) {
                    $order = "$orderby $ordertype";
                }
                // var_dump($join);die();
                $result = $db->getPageListByGroup('T_UserGameChangeLogs', $field, $join, $where, $order, $group, $startdate, $enddate, $page, $limit);
                $list = $result['list'];
                if ($result['count'] == 0) {
                    $result['list'] = [];
                    $result['count'] = 0;
                    $result['other'] = [
                        'TotalPay' => 0,
                        'TotalPayOut' => 0,
                        'TotalWater' => 0,
                        'Tax' => 0,
                        'TotalWin' => 0,
                    ];
                    return $this->apiJson($result);
                }
                $roomlist = $this->GetRoomList();
                $roomlist = array_values($roomlist);
                $default_ProxyId = $db->getTableObject('T_ProxyChannelConfig')->where('isDefault', 1)->value('ProxyId') ?: 0;
                if (isset($list)) {
                    foreach ($list as $k => &$v) {
                        $v['AddTime'] = date('Y-m-d', strtotime($v['AddTime']));
                        //$ParentIds = array_filter(explode(',', $v['ParentIds'] ?? ''));

                        $proxy = [];
                        if (!empty($v['ParentID'])) {
//                            $proxy = $ProxyChannelConfig[$ParentIds[0]] ?? [];
//                            if ($proxy) {
//                                $v['proxyId'] = $proxy['ProxyId'];
//                            } else {
//                                $v['proxyId'] = $v['ParentID'];
//                            }
                            $v['proxyId'] = $v['ParentID'];
                        } else {
                            //默认系统代理
                            $v['proxyId'] = $default_ProxyId;
                        }
                        $v['RoomName'] = '-';
                        $roomids = array_column($roomlist, 'RoomID');
                        $findkey = array_search($v['ServerID'], $roomids);
                        if ($findkey !== false) {
                            $v['RoomName'] = lang($roomlist[$findkey]['RoomName']);
                        }
                    }
                }
                $result['list'] = $list;
                $result['count'] = $result['count'];
                $sqlExec = "exec Proc_GameDailyLogSum '$where_total','$startdate','$enddate'";
                $res = $db->getTableQuery($sqlExec);
                if (isset($res[0][0])) {
                    $data = $res[0][0];
                    $result['other'] = $data;
                } else {
                    $result['other'] = [
                        'TotalPay' => 0,
                        'TotalPayOut' => 0,
                        'TotalWater' => 0,
                        'Tax' => 0,
                        'TotalWin' => 0,
                    ];
                }
                return $this->apiJson($result);
        }
        return $this->fetch();
    }

    /**Gm充值管理*/
    public function TransferManager()
    {
        switch (input('Action')) {
            case 'list':
                $db = new  GameOCDB();
                return $this->apiJson($db->GMSendMoney());
                break;
            case  'add':
                if (request()->isAjax()) {
                    //权限验证
//                    $auth_ids = $this->getAuthIds();
//                    if (!in_array(10001, $auth_ids)) {
//                        return $this->apiReturn(2, [], '没有权限');
//                    }
                    $username = session('business_LoginAccount');
                    $password = input('password');
                    $userInfo = (new \app\model\GameOCDB)
                        ->getTableObject('T_ProxyChannelConfig')
                        ->where('LoginAccount',$username)
                        ->find();
                    if (md5($password) !== $userInfo['PassWord']) {
                        return json(['code' => 3, 'msg' => lang('密码错误')]);
                    }

                    $money = (int)input('Money');
                    $roleID = input('RoleID');
                    $operatetype = input('operatetype');
                    $descript = input('descript');

                    if ($money <= 0)
                        $this->error('金额不能为0或者负数!');

                    if ($money > 0 && $operatetype == 2) {
                        $money = 0 - $money;
                    }
                    if ($money > 0 && $operatetype == 4) {
                        $money = 0 - $money;
                    }
                    $db = new  GameOCDB();
                    $row = $db->GMSendMoneyAdd(['RoleId' => $roleID, 'Money' => $money, 'status' => 0, 'Note' => $descript, 'checkUser' => session('business_LoginAccount'), 'OperateType' => $operatetype]);
                    if ($row > 0) {
                        $res = $db->setTable('T_PlayerComment')->Insert([
                            'roleid' => $roleID,
                            'adminid' => session('userid'),
                            'type' => 1,
                            'opt_time' => date('Y-m-d H:i:s'),
                            'comment' => $descript
                        ]);
                        return $this->success("添加扣款成功,进入审核状态");
                    }
                    return $this->error('添加失败');
                }
                return $this->fetch('transfer_item');
                break;
            case 'send':
                if (request()->isAjax()) {
                    $db = new  GameOCDB();
                    $data = $db->TGMSendMoney()->GetRow("ID=" . input('ID'));
                    if ($data['OperateType'] == 1) {
                        try {
                            $res = $this->sendGameMessage('CMD_WD_BUY_HAPPYBEAN', [$data['RoleId'], $data['Money']]);
                            $res = unpack('Lcode/', $res);
                        } catch (Exception $exception) {
                            return $this->error('连接服务器失败,请稍后重试!');
                        }
                        if ($res['code'] == 0) {
                            $row = $db->TGMSendMoney()->UPData(["status" => 1, "UpdateTime" => date('Y-m-d H:i:s')], "ID=" . $data['ID']);
                            if ($row > 0) return $this->success("审核成功");
                        }
                        return $this->error('审核失败');
                    } else if ($data['OperateType'] == 2) {
                        try {
                            $res = $this->sendGameMessage('CMD_MD_ADD_ROLE_MONERY', [$data['RoleId'], $data['Money'] * bl, 1, 0, getClientIP()]);
                            $res = unpack('Lcode/', $res);
                        } catch (Exception $exception) {
                            return $this->error('连接服务器失败,请稍后重试!');
                        }
                        if ($res['code'] == 0) {
                            $row = $db->TGMSendMoney()->UPData(["status" => 1, "UpdateTime" => date('Y-m-d H:i:s')], "ID=" . $data['ID']);
                            if ($row > 0) return $this->success("审核成功");
                        }
                        return $this->error('审核失败');
                    } else {
                        return $this->error('不存在的上下分类型');
                    }
                }
                break;
            case 'deny':
                $db = new  GameOCDB();
                $row = $db->TGMSendMoney()->UPData(["status" => 2, "UpdateTime" => date('Y-m-d H:i:s')], "ID=" . input('ID'));
                if ($row > 0) return $this->success("成功");
                return $this->error('失败');
            case 'exec':
                //权限验证
                // $auth_ids = $this->getAuthIds();
                // if (!in_array(10008, $auth_ids)) {
                //     return $this->apiJson(["code"=>1,"msg"=>"没有权限"]);
                // }
                $db = new  GameOCDB();
                $result = $db->GMSendMoney();
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
                    $header_types = [
                        'ID' => 'integer',
                        lang('用户ID') => 'integer',
                        lang('扣款金币') => '0.00',
                        lang('备注') => 'string',
                        lang('状态') => 'string',
                        lang('操作时间') => 'datetime',
                        lang('更新时间') => 'datetime',
                        lang('操作人员') => 'string',
                    ];
                    $filename = lang('邮件管理') . '-' . date('YmdHis');
                    $rows =& $result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    $str = "";
                    foreach ($rows as $index => &$row) {
                        switch ((int)$row['status']) {
                            case  0:
                                $str = "未审核";
                                break;
                            case  1:
                                $str = "已审核";
                                break;
                            case  2:
                                $str = "已拒绝";
                                break;
                        }
                        $item = [$row['ID'], $row['RoleId'], $row['Money'], $row['Note'],
                            $str, $row['InsertTime'], $row['UpdateTime'], $row['checkUser'],];
                        $writer->writeSheetRow('sheet1', $item, ['height' => 16, 'halign' => 'center',]);
                        unset($rows[$index]);
                    }
                    unset($row, $item);
                    $writer->writeToStdOut();
                    exit();
                }


        }
        return $this->fetch();
    }
}
