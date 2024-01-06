<?php

namespace app\admin\controller;

use app\admin\controller\Export\AllUserInfoExport;
use app\admin\controller\traits\getSocketRoom;
use app\admin\controller\traits\search;
use app\common\Api;
use app\common\GameLog;
use app\model;
use app\model\AccountDB;
use app\model\DataChangelogsDB;
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
     * 在线玩家
     */
    public function online()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $orderby = intval(input('orderby')) ? intval(input('orderby')) : 0;
            $asc = intval(input('asc')) ? intval(input('asc')) : 0;
            $mobile = trim(input('mobile')) ? trim(input('mobile')) : '';
            $res = Api::getInstance()->sendRequest([
                'roleid' => $roleId,
                'roomid' => $roomId,
                'orderby' => $orderby,
                'page' => $page,
                'asc' => $asc,
                'mobile' => $mobile,
                'pagesize' => $limit
            ], 'player', 'online');
            if (!empty($res) && isset($res['data']['list']) && $res['data']['list']) {
                foreach ($res['data']['list'] as &$v) {
                    //盈利
                    $v['totalget'] = $v['totalin'] - $v['totalout'];
                    //活跃度
                    $v['lastloginip'] = $v['lastloginip'] . '(' . getIPcode($v['lastloginip']) . ')';
                    $v['huoyue'] = $v['totalin'] != 0 ? round($v['totalwater'] / $v['totalin'], 2) : 0;
                    $v['TigerCtrlValue'] = $v['TigerCtrlValue'] + 2;
                }
                unset($v);
            }
            $message = '暂无在线';
            if (!empty($res) && $res['code'] == 0) {
                $message = '获取成功';
            }
            return $this->apiReturn($res['code'], isset($res['data']['list']) ? $res['data']['list'] : [], $message, $res['total'], [
                'orderby' => isset($res['data']['orderby']) ? $res['data']['orderby'] : 0,
                'asc' => isset($res['data']['asc']) ? $res['data']['asc'] : 0,
            ]);

        }
        $selectData = $this->getRoomList();
        $this->assign('selectData', $selectData);
        return $this->fetch();
    }

    public function online2()
    {
        if (config('is_portrait') == 1) {
            return $this->fetch('online2_s');
        } else {
            return $this->fetch();
        }

    }

    public function onlineGame()
    {
        $selectData = $this->getRoomList();
        $this->assign('selectData', $selectData);
        return $this->fetch();
    }

    public function onlineHall()
    {
        $selectData = $this->getRoomList();
        $this->assign('selectData', $selectData);
        return $this->fetch();
    }

    public function onlineList()
    {
        $type = $this->request->param('type') ?: 'game';
        $roleid = $this->request->param('roleid');
        $name = $this->request->param('name');
        $roomid = $this->request->param('roomid');
        $OperatorId = $this->request->param('OperatorId');
        $orderby = input('orderfield', 'LastLoginTime');
        $ordertype = input('ordertype', 'desc');
        $limit = $this->request->param('limit') ?: 15;
        $where = "1=1";
        if ($roleid != '') {
            $where .= " and  AccountID =" . $roleid;
        }
        if ($name != '') {
            $where .= " and  AccountName like '%$name%'";
        }
        if (config('is_portrait') == 1) {
            $where .= " and  GmType<>0 ";
        }
        //游戏服务器端 获取在线列表
        $onlin_data = $this->GetOnlineUserlist2();
        if (config('is_portrait') == 1) {
            $type = '';
            $online_list = $onlin_data['total'] ?? [];
        } else {
            $online_list = $onlin_data[$type] ?? [];
        }

        if (empty($online_list)) {
            return $this->apiJson($online_list);
        }
        if ($roomid > 0) {
            $online_list = $onlin_data['room'][$roomid] ?? [];
        }
        if (empty($online_list)) {
            return $this->apiJson($online_list);
        }
        $online_list = implode(',', $online_list);
        $where .= "And AccountID in($online_list)";
        if ($OperatorId != '') $where .= " AND OperatorId in ($OperatorId)";
        $field = "AccountID ID,MachineCode,Mobile,countryCode,AccountName,Locked,LoginName,GmType,RegisterTime,LastLoginIP,LastLoginTime,TotalDeposit,TotalRollOut,Money,RegIP,b.CtrlRatio ctrlratio,b.InitialPersonMoney,TigerCtrlValue,PersonCtrlMoney,(TotalRollOut-TotalDeposit) as GameProfit,ParentID,ParentIds,OperatorId";
        $db = new UserDB();
        // $online_list = $db->TViewAccount()->GetPage($where, "$orderby $ordertype", $field);
        $online_list = $db->getTableObject('View_Accountinfo')->alias('a')
            ->join('[CD_UserDB].[dbo].[T_UserCtrlData] b', 'b.RoleID=a.AccountID', 'LEFT')
            ->where($where)
            ->field($field)
            ->order("$orderby $ordertype")
            // ->fetchSql(true)
            ->paginate($limit)
            ->toArray();
        $online_list['list'] = $online_list['data'];
        $online_list['count'] = $onlin_data['total_num'];

        $gameOCDB = new GameOCDB();
        $default_ProxyId = $gameOCDB->getTableObject('T_ProxyChannelConfig')->where('isDefault', 1)->value('ProxyId') ?: '';
        $ProxyChannelConfig = (new GameOCDB())->getTableObject('T_ProxyChannelConfig')->column('*', 'ProxyChannelId');
        $db = new model\MasterDB();
        foreach ($online_list['list'] as $key => &$val) {
            $val['GameProfit'] = round($val['GameProfit'], 2);
            ConVerMoney($val['Money']);
            if ($type == 'game') {
                $val['room'] = $db->getTableQuery("select RoomID,RoomName+'-('+CONVERT(VARCHAR,RoomID)+')' RoomName from T_GameRoomInfo where RoomID=" . $onlin_data['roominfo'][$val['ID']])[0]['RoomName'];
            }
            $val['InitialPersonMoney'] = $val['InitialPersonMoney'] / 1000;
            $val['PersonCtrlMoney'] = $val['PersonCtrlMoney'] / 1000;
            if ($val['ctrlratio'] == 100) {
                $val['ctrlstatus'] = lang('已停止');
            } elseif ($val['ctrlratio'] == '') {
                $val['ctrlstatus'] = lang('未控制');
            } else {
                $val['ctrlstatus'] = lang('控制中');
                if ($val['ctrlratio'] > 100) {
                    $val['ctrlstatus'] .= '&nbsp;' . lang("放水中");
                }
                if ($val['ctrlratio'] < 100) {
                    $val['ctrlstatus'] .= '&nbsp;' . lang("收水中");
                }
            }


            if ($val['ParentID'] != 0) {
                $val['proxyId'] = $val['ParentID'];
                if ($val['ParentID'] < 10000000) {
                    $proxy = $ProxyChannelConfig[$val['ParentID']] ?? [];
                    if ($proxy) {
                        $val['proxyId'] = $proxy['ProxyId'];
                    }
                }
            } else {
                $val['proxyId'] = $default_ProxyId;
            }
        }

        return $this->apiJson($online_list);
    }

    public function online2Control()
    {
        if ($this->request->isAjax()) {
            $roleid = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $ratio = intval(input('CtrlRatio')) ? intval(input('CtrlRatio')) : 0;
            $time = intval(input('time')) ? intval(input('time')) : 10000000;
            $timeinterval = 10000000;
            $InitialPersonMoney = input('InitialPersonMoney') ? input('InitialPersonMoney') : 0;
            if ($ratio < 1) {
                $ratio = 1;
            }
            if ($ratio > 200) {
                $ratio = 200;
            }
            $InitialPersonMoney = abs($InitialPersonMoney);
            if ($ratio > 100) {
                $InitialPersonMoney = -$InitialPersonMoney;
            }
            if ($InitialPersonMoney < -30000) {
                $InitialPersonMoney = -30000;
            }
            if ($InitialPersonMoney > 30000) {
                $InitialPersonMoney = 30000;
            }
            $InitialPersonMoney = $InitialPersonMoney * bl;
            $res = $this->sendGameMessage('CMD_WD_SET_USER_CTRL_DATA', [$roleid, $ratio, $time, $timeinterval, $InitialPersonMoney], 'DC', 'ProcessDMSetRoomRate');
            ob_clean();
            $request = $this->request->request();
            unset($request['s']);
            GameLog::logData(__METHOD__, $request, 1, json_encode($res));
            return $this->apiReturn(0, [], '控制启动成功');
        }
        $roleid = $this->request->param('roleid');
        $data = [];
        if (!empty($roleid)) {
            $db = new UserDB;
            $data = $db->DBOriginQuery('select * from T_UserCtrlData where RoleID=' . $roleid);
            $data = $data ? $data[0] : [];
            if ($data) {
                $data['InitialPersonMoney'] = $data['InitialPersonMoney'] / bl;
            }
        }
        $this->assign('roleid', $roleid);
        $this->assign('data', $data);
        return $this->fetch();
    }

    public function updatebank($roleid, $username, $bankcardno, $bankname)
    {

        $request = $this->request->request();
        $updadata = [
            'roleid' => $roleid,
            'username' => $username,
            'bankcardno' => $bankcardno,
            'bankname' => $bankname,
        ];
//        var_dump($updadata);die;
        $res = Api::getInstance()->sendRequest($updadata, 'player', 'updatebank');
        //log记录
        GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);

    }

    //更新银行卡
    public function updateSocketBank()
    {
        if ($this->request->isAjax()) {
            //权限验证 
            $auth_ids = $this->getAuthIds();
            if (!in_array(10002, $auth_ids)) {
                return $this->apiReturn(2, [], '没有权限');
            }
            $roleid = input('RoleID') ? input('RoleID') : '';
            $username = input('UserName') ? input('UserName') : '';
            $bankcardno = input('BankCardNo') ? input('BankCardNo') : '';
            $Mail = input('Mail') ? input('Mail') : '';
            $IFSCCode = input('IFSCCode') ? input('IFSCCode') : '';
            $PayWayType = input('PayWayType') ? input('PayWayType') : '';
            $bankname = input('BankName') ? input('BankName') : '';
            if (!$username || !$bankcardno || !$roleid) {
                return $this->apiReturn(2, [], '输入不能为空');
            }

            $payway = new UserPayWay();
            $existscard = 1;
            if ($PayWayType == 2) {
                $existscard = $payway->getCount(" BankCardNo='$bankcardno' and RoleID<>$roleid");
//                $existifsc = $payway->getCount(['IFSCCode'=>$IFSCCode]);
                if ($existscard > 0) {
                    return $this->apiReturn(100, [], '该银行卡已经存在!');
                }
//                if($existifsc>0){
//                    return $this->apiReturn(100, [], '该IFSCCode已经存在!');
//                }
            } else if ($PayWayType == 3) {
                $existscard = $payway->getCount(" BankCardNo='$bankcardno' and RoleID<>$roleid");
//                $existifsc = $payway->getCount(['IFSCCode'=>$IFSCCode]);
                if ($existscard > 0) {
                    return $this->apiReturn(100, [], '该银行卡已经存在!');
                }
//                if($existifsc>0){
//                    return $this->apiReturn(100, [], '该IFSCCode已经存在!');
//                }
            } else if ($PayWayType == 1) {
                $existnum = $payway->getCount(" BankCardNo='$bankcardno' and RoleID<>$roleid");
                if ($existnum > 0) {
                    return $this->apiReturn(100, [], '该UPI已经存在!');
                }
            }

            $socket = new QuerySocket();
            $result = $socket->updateBank($roleid, $username, $bankcardno, $bankname, $IFSCCode, $Mail, $PayWayType);
            GameLog::logData(__METHOD__, $this->request->request(), 1);
            if ($result["iResult"] == 0) {
                ob_clean();
                return $this->apiReturn(0, [], '修改成功!');
            } else {
                return $this->apiReturn(100, [], '账号重复或者操作失败');
            }

        }

    }

    //解绑银行卡
    public function unbindSocketBank()
    {
        if ($this->request->isAjax()) {
            $auth_ids = $this->getAuthIds();
            if (!in_array(10002, $auth_ids)) {
                return $this->apiReturn(2, [], '没有权限');
            }
            $roleid = input('RoleID') ? input('RoleID') : '';
            $PayWayType = input('PayWayType') ? input('PayWayType') : 2;

            $socket = new QuerySocket();
            $result = $socket->unbindBank($roleid, $PayWayType);
            ob_clean();
            GameLog::logData(__METHOD__, $this->request->request(), 1);
            return $this->apiReturn(0, [], '解绑成功！');
        }

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

    //更新角色状态
    public function updateRoleStatus2()
    {

        if ($this->request->isAjax()) {
            $roleid = intval(input('roleid')) ? intval(input('roleid')) : '';
            $socket = new QuerySocket();
            $result = $socket->unlockRoleStatus($roleid);
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

    //设置房间概率信息
    public function setSocketRoomRate()
    {
        $roomid = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $init = intval(input('init')) ? intval(input('init')) : 0;
        $current = intval(input('current')) ? intval(input('current')) : 0;
        if (abs($init) > 2000000 || abs($current) > 2000000) {
            return $this->apiReturn(1, [], '库存值不能超过绝对值200万');
        }
        $roomsData = $this->getSocketRoom($this->socket, $roomid);
        $this->socket->setRoom($roomid, $roomsData['nCtrlRatio'], $init, $current, $roomsData['szStorageRatio']);
        GameLog::logData(__METHOD__, $this->request->request(), 1);
        ob_clean();
        return $this->apiReturn(0, [], '修改成功');
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
                //权限验证 
                $auth_ids = $this->getAuthIds();
                if (!in_array(10008, $auth_ids)) {
                    return $this->apiJson(["code" => 1, "msg" => "没有权限"]);
                }
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

    /**
     * Notes: 游戏日志（单独菜单）T_UserGameChangeLogs
     * @return mixed
     */
    public function gamelog()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $roomid = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $strartdate = input('strartdate') ? input('strartdate') : '';
            $enddate = input('enddate') ? input('enddate') : '';
            $winlost = intval(input('winlost')) >= 0 ? intval(input('winlost')) : -1;
            $mobile = input('mobile') ? input('mobile') : '';
            //拼装sql
            $where = " 1=1 ";

            if ($roleId > 0) {
                $where .= " and  RoleID =" . $roleId;
            }
            if ($strartdate != '') {
                $where .= " and addtime>= '" . $strartdate . " 0:0:0'";
            } else
                $where .= " and addtime >= '" . date('Y-m-d') . "'";
            if ($enddate != '') {
                $where .= " and addtime<= '" . $enddate . " 23:59:59'";
            }
            if ($mobile != '') {
                $where .= " and rolename like '%" . $mobile . "%' ";
            }
            $model = new model\GameLog('CD_DataChangelogsDB');
            $list = $model->getGameLog($where, 'AddTime', $page, $limit, 1);

            $res['data']['list'] = $list['list'];
            $res['code'] = 0;
            $res['message'] = '';
            $res['total'] = $list['count'];
            return $this->apiReturn($res['code'], isset($res['data']['list']) ? $res['data']['list'] : [], $res['message'], $res['total']);
            //, ['alltotal' => $sumdata]
        }
        $selectData = $this->getRoomList();
        $this->assign('selectData', $selectData);
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

            if (config('is_portrait') == 1) {
                if ($user['ProxyChannelId']) {
                    $proxy = $gameOCDB->getProxyChannelConfig()->GetRow(['ProxyChannelId' => $user['ProxyChannelId']], '*', 'ProxyChannelId desc') ?: [];
                }
            } else {
                if (!empty($ParentIds)) {
                    $proxy = $gameOCDB->getProxyChannelConfig()->GetRow(['RoleID' => $ParentIds[0]], '*', 'RoleID desc') ?: [];
                }
            }

            $lotterypoint = (new UserDB())->getTableObject('T_RoleDailyData')->where('RoleID', $roleId)->value('LotterCostRunning');
            if (!empty($lotterypoint)) {
                $lotterypoint = bcdiv($lotterypoint, bl, 0);
            } else {
                $lotterypoint = 0;
            }

            $user['lotterypoint'] = $lotterypoint;
            $channelId = $user['OperatorId'];
            $channeldata = $gameOCDB->getTableRow('T_OperatorSubAccount', ['OperatorId' => $channelId], 'OperatorName');
            $user['proxyName'] = $proxy['AccountName'] ?? 'None';
            $user['ChannelName'] = $channeldata['OperatorName'] ?? 'None';
            if (!empty($user['Mobile'])) {
                $user['Mobile'] = substr_replace($user['Mobile'], '**', -2);
            }
            $user['AccountName'] = substr_replace($user['AccountName'], '**', -4);
            ConVerMoney($user['Money']);
            ConVerMoney($user['PayOut']);
            ConVerMoney($user['PayOutAll']);
            ConVerMoney($user['TotalWin']);
            ConVerMoney($user['TotalRunning']);
            ConVerMoney($user['ProxyBonus']);

            //gm上分统计
            $user['addgm'] = $gameOCDB->getTableObject('T_GMSendMoney')->where('RoleId',$roleId)->where('status',1)->where('operatetype',1)->sum('Money')?:0;
            //邮件赠送统计
            $user['addmailsend'] = (new DataChangelogsDB())->getTableObject('T_ProxyMsgLog')->where('RoleId',$roleId)->where('VerifyState',1)->sum('Amount')?:0;
            $user['addmailsend'] = bcdiv($user['addmailsend'], bl,3);
            $user['zscoin'] = bcadd($user['addgm'], $user['addmailsend'],2);

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
            $banklist = [
                'CPF', 'CNPJ', 'EMAIL', 'PHONE', 'EVP'
            ];
        } else if (config('app_type') == 3) {
            $banklist = [

            ];
        } else {
            $banklist = $bankcode->getListAll();
        }

        $auth_ids = $this->getAuthIds();
        if (!in_array(10002, $auth_ids)) {
            $this->assign('bind_auth', '0');
        } else {
            $this->assign('bind_auth', '1');
        }


        $bankdb = new model\BankDB();
        $strsql = 'select isnull(count(1),0) as cnt from UserDrawBack(nolock) where status=100 and  AccountID=' . $roleId;
        $draw_result = $bankdb->getTableQuery($strsql);

        $strsql = 'SELECT  count(1) as cnt  FROM [CD_UserDB].[dbo].[T_UserTransactionChannel](nolock) where AccountID=' . $roleId;
        $pay_result = $db->getTableQuery($strsql);

        $ingame = (new UserDB())->getTableRow('T_UserGameWealth(nolock)', 'RoleID=' . $roleId, 'InGame');


        $coinchangeType = config('bank_change_type');
        $this->assign('changeType', $coinchangeType);
        $this->assign('drawcount', $draw_result[0]['cnt']);
        $this->assign('paytimes', $pay_result[0]['cnt']);
        $this->assign('mytip', '22');
        $this->assign('upiway', $upiway);
        $this->assign('bankway', $bankway);
        $this->assign('banklist', $banklist);
        $this->assign('pixway', $pixway);
        $this->assign('ingames', $ingame['InGame']);
        return $this->fetch();
    }


    //vip等级对应提现限制是否开启
    Public function VipDrawSwitch()
    {
        $RoleID = input('RoleID', 0);
        $ProxySwitch = input('type', 0);

//        $UserDB = new UserDB;
//        $res = $UserDB->getTableObject('T_UserGameWealth')
//            ->where('RoleID', $RoleID)
//            ->data(['InGame' => $ProxySwitch])
//            ->update();
        if (true) {
            //服务端
            $data = $this->sendGameMessage('CMD_MD_SET_WITHDRAW_VIPLIMIT_SWITCH', [$RoleID, $ProxySwitch], "DC", 'returnComm');
            if ($data['iResult'] == 0) {
                return ['code' => 0];
            } else {
                return ['code' => 1];
            }

        } else {
            return ['code' => 1];
        }

    }


    //玩家等级数据
    public function getProxyLvData()
    {
        $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
        $data = [];
        if ($roleId > 0) {
            $db = new UserDB();
            $field = 'ProxyId,Lv1PersonCount,Lv1Deposit,Lv1DepositPlayers,Lv2PersonCount,Lv2Deposit,Lv2DepositPlayers,Lv3PersonCount,Lv3Deposit,Lv3DepositPlayers,Lv1WithdrawAmount,Lv2WithdrawAmount,Lv3WithdrawAmount,Lv1WithdrawCount,Lv2WithdrawCount,Lv3WithdrawCount,ValidInviteCount,Lv2ValidInviteCount,Lv3ValidInviteCount';
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
                'ValidInviteCount' => $row['ValidInviteCount'],
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
                'ValidInviteCount' => $row['Lv2ValidInviteCount'],
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
                'ValidInviteCount' => $row['Lv3ValidInviteCount'],
            ];
            array_push($data, $levle1);
            array_push($data, $levle2);
            array_push($data, $levle3);
        }
        return $this->successJSON($data);
    }

    public function updatepayway()
    {
        $BankCardNo = input('BankCardNo', '');
        $UserName = input('UserName', '');
        //$PayWayType = input('PayWayType',0);
        $RoleID = input('RoleID', 0);
        $BankName = input('BankName', '');
        $Mail = input('Mail', '');
        $IFSCCode = input('IFSCCode', '');

        if ($UserName == '' || $RoleID == 0) {
            return $this->apiReturn(100, '', '参数错误');
        }

        $payway = new UserPayWay();
        $save_data = [
            'BankCardNo' => $BankCardNo,
            'UserName' => $UserName,
            'BankName' => $BankName,
            'Mail' => $Mail,
            'IFSCCode' => $IFSCCode,
        ];
        $state = $payway->updateByWhere(['RoleID' => $RoleID], $save_data);
        return $this->apiReturn(0, '', '保存成功');
    }

    public function setProxySwitch()
    {
        $RoleID = input('RoleID', 0);
        $ProxySwitch = input('type', 0);

        $UserDB = new UserDB;
        $res = $UserDB->getTableObject('T_UserProxyInfo')
            ->where('RoleID', $RoleID)
            ->data(['ProxySwitch' => $ProxySwitch])
            ->update();
        if ($res) {
            //服务端
            $data = $this->sendGameMessage('CMD_MD_SET_PROXY_SWITCH', [$RoleID, $ProxySwitch], "DC", 'returnComm');
            if ($data['iResult'] == 0) {
                return ['code' => 0];
            } else {
                return ['code' => 1];
            }

        } else {
            return ['code' => 1];
        }

    }

    public function updateupiway()
    {
        $BankCardNo = input('BankCardNo1', '');
        $UserName = input('UserName1', '');
        $RoleID = input('RoleID', 0);
        $Mail = input('Mail1', '');

        if ($UserName == '' || $RoleID == 0) {
            return $this->apiReturn(100, '', '参数错误');
        }

        $payway = new UserPayWay();
        $save_data = [
            'BankCardNo' => $BankCardNo,
            'UserName' => $UserName,
            'BankName' => '',
            'Mail' => $Mail,
            'IFSCCode' => '',
        ];
        $state = $payway->updateByWhere(['RoleID' => $RoleID], $save_data);
        return $this->apiReturn(0, '', '保存成功');
    }
    //封号 解封 操作
    //封号 解封 操作
    public function DiasbleByID()
    {
        if ($this->request->isAjax()) {
            $ID = input("ID");
            $type = intval(input("type")) ? intval(input("type")) : 0;
            if (!empty($ID) && $type >= 0) {
//                $account = new model\Account();
//                $ret =$account->updateByWhere(['accountID'=> $ID], ['Locked' => $type]);
//                if ($ret) {
//                    $request = $this->request->request();
//                    $typestr = $type ? "禁用" : "启用";
//                    array_push($request, $typestr);
//
//                    GameLog::logData(__METHOD__, $request, 1, $typestr);
//                    return $this->apiReturn(0, $type);
//                }
                $roleid = 0;

                $roleStatus = $this->getRoleStatus($roleid)->getdata();
                $socket = new QuerySocket();


                return $this->apiReturn(-1);
            }
        }
    }

    /**
     * 所有玩家
     */
    public function all()
    {
        if (1) {
            //save_log('ceshi_all', explode(' ', microtime())[1]);
            $startTime = input("startTime") ? input("startTime") : null;
            $endTime = input("endTime") ? input("endTime") : null;
            $roleId = input('roleid', '');
//            $nickname = trim(input('nickname'));
            $isdisable = intval(input('isdisable', -1));
            $orderby = input('orderfield', 'RegisterTime');
            $ordertype = input('ordertype', 'desc');
            $account = trim(input('account')) ? trim(input('account')) : '';
            $ipaddr = trim(input('lastIP')) ? trim(input('lastIP')) : '';
            $usertype = intval(input('usertype', -1));
            $online = intval(input('isonline', -1));
            $packID = input('PackID', -1);
            $mobile = input('mobile', '');
            $ip = input('ip', '');
            $bankcard = input('bankcard', '');
            $bankusername = input('bankusername', '');
            $upi = input('upi', '');
            $proxyId = input('proxyId', '');
            $isControll = input('iscontroll', '');
            $isbind = input('isbind', -1);

            $minrecharge = input('minrecharge', '');
            $maxrecharge = input('maxrecharge', '');
            $VipLv = input('VipLv', '');
            $isrecharge = input('isrecharge', '');

            $where = '';

            if (config('is_portrait') == 1) {
                $where .= " AND  GmType<>0";
            }
            if ($roleId != '') {
                $roleId = str_replace('，', ',', $roleId);
                $roleId = trim($roleId, ',');
                $where .= " AND  AccountID in(" . $roleId . ")";
            }
//            if (!empty($nickname)) $where .= " AND nickname like '%$nickname%' ";
            // if (!empty($startTime) && !empty($endTime)) $where .= " AND RegisterTime BETWEEN '$startTime 00:00:00' AND '$endTime 23:59:59' ";
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
            if (intval($packID) > 0) {
                $packID = intval($packID);
                $where .= " AND OperatorId in ($packID)";
            }
            if (!empty($mobile)) $where .= " and mobile like '%$mobile%'";
            if (!empty($ip)) $where .= " and regip like '%$ip%'";
            if (!empty($bankcard)) $where .= " and BankCardNo like '%$bankcard%'";
            if (!empty($bankusername)) $where .= " and UserName like '%$bankusername%'";
            if (!empty($upi)) $where .= " and UPICode like '%$upi%'";
            if (!empty($isControll)) {
                if ($isControll == 1) {
                    $isControll = 96;
                } else {
                    $isControll = 0;
                }
                $where .= " and SystemRight='$isControll' ";
            }

            if ($isbind > -1) {
                if ($isbind == 1)
                    $where .= " and mobile<>'' ";
                else if ($isbind == 0) {
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
            $gameOCDB = new GameOCDB();
            if (config('is_portrait') == 1) {
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
            } else {

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
        }
        $ItemVal = "";
        if (config('app_name') == 'ayllabet'){
            $ItemVal = ",ItemVal";
        }
        $field = "AccountID ID,MachineCode,Mobile,countryCode,AccountName,Locked,LoginName,GmType,RegisterTime,LastLoginIP,LastLoginTime,TotalDeposit,TotalRollOut,Money,RegIP,VipLv,SystemRight,ParentIds,ParentID,OperatorId,ISNULL(ProxyBonus,0) as ProxyBonus,ProxyCommiSwitch".$ItemVal;
        $file_roleid = 'RoleId';
        if (config('is_portrait') == 1) {
            $field .= ',ProxyChannelId';
            $file_roleid = 'ProxyChannelId';
        } else {
            $field .= ',UserName,BankName,BankCardNo,IFSCCode,UPICode';
        }
        $db = new UserDB();
        $gameoc = new GameOCDB();
        $ProxyChannelConfig = $gameoc->getTableObject('T_ProxyChannelConfig')->column('*', $file_roleid);
        $pggameid = [
            60,65,74,87,89,48,53,54,71,75
        ];
        //$ProxyChannel = $gameoc->getTableList('T_ProxyChannelConfig', '', 1, 1000, '');
        switch (input('Action')) {
            case 'list':
                $result = $db->TViewAccount()->GetPage($where, "$orderby $ordertype", $field);
                foreach ($result['list'] as &$item) {
                    if (isset($item['ItemVal'])){
                        if($item['ItemVal']){
                            $item['ItemVal'] = $item['ItemVal'] / bl;
                        }else{
                            $item['ItemVal'] = 0;
                        }
                    }
                    if (config('lookPhone') == 1){
                        if (!in_array($this->getGroupId(session('userid')),[1,2,3])){
                            $item['AccountName'] = substr_replace($item['AccountName'], '**', -4);
                        }
                    }else{
                        $item['AccountName'] = substr_replace($item['AccountName'], '**', -4);
                    }

                    if (!empty($item['Mobile'])) {
                        if(config('lookPhone') == 1){
                            if (!in_array($this->getGroupId(session('userid')),[1,2,3])){
                                $item['Mobile'] = substr_replace($item['Mobile'], '**', -2);
                            }
                        }else{
                            $item['Mobile'] = substr_replace($item['Mobile'], '**', -2);
                        }


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
                    $item['quhao'] = '91';
                    $item['phone'] = '';
                    $proxy = [];
                    if (config('is_portrait') == 1) {
                        $item['proxyId'] = $item['ProxyChannelId'] ?: $default_ProxyId;
                    } else {
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
                    //是否在线
                    $item['is_oline'] = $this->sendGameMessage('CMD_MD_USER_STATE', [$item['ID']], "DC", 'returnComm')['iResult'] ?? 0;


                    ConVerMoney($item['Money']);
                    ConVerMoney($item['ProxyBonus']);

                    //获取pg链接
                    $gameidkey = rand(0,9);
                    $gameid = $pggameid[$gameidkey];
                    $item['pg_link'] = config('pggame.GAME_URL').'/'.$gameid.'/index.html?btt=1&ot='.config('pggame.Operator_Token').'&l=en&ops='.$this->encry(config('platform_name').'_'.$item['ID']);
                }
                //save_log('ceshi_all', explode(' ', microtime())[1]);
                return $this->apiJson($result);

                break;
            case 'onlinelist':
                return $this->apiJson(['list' => $this->GetOnlineUserlist2()['total']]);
                break;
            case 'exec':
                $field = "AccountID ID,AccountName,LoginName,RegisterTime,LastLoginIP,TotalDeposit,TotalRollOut,Money,ProxyBonus";
                $result = $db->TViewAccount()->GetPage($where, "$orderby $ordertype", $field);
                $AgentWaterDailyExport = new AllUserInfoExport();
                $AgentWaterDailyExport->export($result['list']);
//                foreach ($result['list'] as &$item) {
//                    ConVerMoney($item['Money']);
//                    if (!empty($item['Mobile'])) {
//                        $item['Mobile'] = substr_replace($item['Mobile'], '**', -2);
//                    }
//                    if (config('accountName') == 1){
//                        $item['AccountName'] = substr_replace($item['AccountName'], '**', -4);
//                    }
////                    ConVerMoney($item['TotalRollOut']);
////                    ConVerMoney($item['TotalDeposit']);
//                }
//
//                $outAll = input('outall', false);
//                if ((int)input('exec', 0) == 0) {
//                    if ($result['count'] == 0) {
//                        $result = ["count" => 0, "code" => 1, 'msg' => lang("没有找到任何数据,换个姿势再试试?")];
//                    }
//                    if ($result['count'] >= 5000 && $outAll == false) {
//                        $result = ["code" => 2, 'msg' => lang("数据超过5000行是否全部导出?<br>数据越多速度越慢<br>当前数据一共有") . $result['count'] . lang("行")];
//                    }
//                    unset($result['list']);
//                    return $this->apiJson($result);
//                }
//                //导出表格
//                if ((int)input('exec', 0) == 1 && $outAll = true) {
//                    $header_types = [
//                        lang('ID') => 'integer',//ID
//                        //lang('机器码') => 'string',//MachineCode
//                        // lang('手机号') => 'string',//AccountName
//                        lang('账号') => 'string',//AccountName
////                        '是否禁用' => "string",//Locked
//                        lang('昵称') => 'string',//LoginName
////                        '登陆类型' => "string",//GmType
//                        lang('注册时间') => "datetime",//RegisterTime
//                        lang('最后登录IP') => "string",//LastLoginIP
//                        lang('总充值') => "0.00",//TotalDeposit
//                        lang('总转出') => '0.00',//TotalRollOut
//                        lang('剩余金币') => '0.00',//Money
//                        lang('代理账户') => '0.00'//Money
//                    ];
//                    $filename = lang('用户记录') . '-' . date('YmdHis');
//                    $this->GetExcel($filename, $header_types, $result['list']);
//                }
                break;
        }


        $usertype = config('usertype');
        unset($usertype[4]);
        $this->assign('usertype', $usertype);
        $selectData = $this->getRoomList();
        $this->assign('selectData', $selectData);
        $auth_ids = $this->getAuthIds();
        if (!in_array(10003, $auth_ids)) {
            $this->assign('add_dm_auth', '0');
        } else {
            $this->assign('add_dm_auth', '1');
        }
        if (!in_array(10004, $auth_ids)) {
            $this->assign('wipe_dm_auth', '0');
        } else {
            $this->assign('wipe_dm_auth', '1');
        }
        return $this->fetch();
    }

    public function getdmrateset(){
        //打码百分比
        $roleid = input('roleid');
        $item['win_dmrateset'] = 0;
        $item['lose_dmrateset'] = 0;

        $dmrateset = (new UserDB())->getTableObject('T_Job_UserInfo')->where('RoleID', $roleid)->where('job_key',3)->find() ?: [];
        if($dmrateset){
            $item['win_dmrateset'] = $dmrateset['value'];
        }
        $dmrateset = (new UserDB())->getTableObject('T_Job_UserInfo')->where('RoleID', $roleid)->where('job_key',4)->find() ?: [];
        if($dmrateset){
            $item['lose_dmrateset'] = $dmrateset['value'];
        }
        return json($item);
    }
    /**手机绑定列表 */
    public function MobileList()
    {
        switch (input('Action')) {
            case 'list':
                $db = new  UserDB();
                return $this->apiJson($db->getMobileList());
                break;
            case 'exec':
                $db = new  UserDB();
                $result = $db->getMobileList();
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
                        lang('玩家ID') => 'integer',//ID
                        lang('手机号') => 'string',//MachineCode
                        lang('昵称') => "string",//countryCode
                        lang('绑定时间') => "datetime",//RegisterTime
                    ];
                    $filename = lang('绑定手机号列表') . '-' . date('YmdHis');
                    $this->GetExcel($filename, $header_types, $result['list']);
                }
                break;

        }
        return $this->fetch();
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
                $result['other'] = [];//$sumdata;
                return $this->apiJson($result);
            case 'exec':
                //权限验证
                $auth_ids = $this->getAuthIds();
                if (!in_array(10008, $auth_ids)) {
                    return $this->apiJson(["code" => 1, "msg" => "没有权限"]);
                }
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
     * Notes: 金币日志（单独菜单）
     * @return mixed
     */
    public function coinlog()
    {
        $changeType = config('bank_change_type');
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $strartdate = input('strartdate') ? input('strartdate') : '';
            $enddate = input('enddate') ? input('enddate') : '';
            $changetype = intval(input('changetype')) ? intval(input('changetype')) : 0;
            $mobile = input('mobile') ? input('mobile') : '';
            //拼装sql
            $where = "a.RoleID=b.AccountID";
            if ($roleId > 0) $where .= " and  RoleID =" . $roleId;
            if ($changetype > 0) $where .= " and changetype=" . $changetype;
            if ($strartdate != '') $where .= " and addtime>= '" . $strartdate . " 0:0:0'";
            else                $where .= " and addtime>= '" . date('Y-m-d') . "'";
            if ($enddate != '') $where .= " and addtime<= '" . $enddate . " 23:59:59'";
            if ($mobile != '') $where .= " and AccountName like '%" . $mobile . "%' ";
            $model = new model\CoinLog('CD_DataChangelogsDB');
            $list = $model->getCoinLog($changeType, $where, 'AddTime', $page, $limit, 1);

            $res['data']['list'] = $list['list'];
            $res['code'] = 0;
            $res['message'] = '';
            $res['total'] = $list['count'];
            return $this->apiReturn($res['code'], isset($res['data']['list']) ? $res['data']['list'] : [], $res['message'], $res['total']);
        }

        $this->assign('changeType', $changeType);
        return $this->fetch();
    }

    /**
     * Notes: 钻石日志（单独菜单）
     * @return mixed
     */
    public function scorelog()
    {
        $changeType = config('site.score_change_type');

        if ($this->request->isAjax()) {
            //前台传参
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $strartdate = input('strartdate') ? input('strartdate') : '';
            $enddate = input('enddate') ? input('enddate') : '';
            $changetype = intval(input('changetype')) ? intval(input('changetype')) : 0;
            $mobile = input('mobile') ? input('mobile') : '';

            //拼装sql
            $where = " a.RoleID=b.AccountID ";

            if ($roleId > 0) {
                $where .= " and  RoleID =" . $roleId;
            }
            if ($changetype > 0) {
                $where .= " and changetype=" . $changetype;
            }
            if ($strartdate != '') {
                $where .= " and addtime>= '" . $strartdate . " 0:0:0'";
            } else
                $where .= " and addtime>= '" . date('Y-m-d') . "'";
            if ($enddate != '') {
                $where .= " and addtime<= '" . $enddate . " 23:59:59'";
            }
            if ($mobile != '') {
                $where .= " and AccountName like '%" . $mobile . "%' ";
            }

            $model = new model\ScoreLog('CD_DataChangelogsDB');
            $list = $model->getScoreLog($changeType, $where, 'AddTime', $page, $limit, 1);


            $res['data']['list'] = $list['list'];
            $res['code'] = 0;
            $res['message'] = '';
            $res['total'] = $list['count'];
            return $this->apiReturn($res['code'], isset($res['data']['list']) ? $res['data']['list'] : [], $res['message'] = '', $res['total']);
        }
        $this->assign('changeType', $changeType);
        return $this->fetch();
    }

    /**
     * Notes: 礼券日志（单独菜单）
     * @return mixed
     */
    public function couponlog()
    {
        $changeType = config('site.coupon_change_type');

        if ($this->request->isAjax()) {
            //前台传参
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $strartdate = input('strartdate') ? input('strartdate') : '';
            $enddate = input('enddate') ? input('enddate') : '';
            $changetype = intval(input('changetype')) ? intval(input('changetype')) : 0;
            $mobile = input('mobile') ? input('mobile') : '';

            //拼装sql
            $tableName = "";
            $where = "a.RoleID=b.AccountID";

            if ($roleId > 0) {
                $where .= " and  RoleID =" . $roleId;
            }
            if ($changetype > 0) {
                $where .= " and changetype=" . $changetype;
            }
            if ($strartdate != '') {
                $where .= " and addtime>= '" . $strartdate . " 0:0:0'";
            } else
                $where .= " and addtime>= '" . date('Y-m-d') . "'";

            if ($enddate != '') {
                $where .= " and addtime<= '" . $enddate . " 23:59:59'";
            }

            if ($mobile != '') {
                $where .= " and AccountName like '%" . $mobile . "%' ";
            }

            $model = new model\CouponLog('CD_DataChangelogsDB');
            $list = $model->getCouponLog($changeType, $where, 'AddTime', $page, $limit, 1);


            $res['data']['list'] = $list['list'];
            $res['code'] = 0;
            $res['message'] = '';
            $res['total'] = $list['count'];
            return $this->apiReturn($res['code'], isset($res['data']['list']) ? $res['data']['list'] : [], $res['message'] = '', $res['total']);
        }


        $this->assign('changeType', $changeType);
        return $this->fetch();
    }

    public function superlist()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $orderby = intval(input('orderby')) ? intval(input('orderby')) : 0;
            $asc = intval(input('asc')) ? intval(input('asc')) : 0;
            $mobile = trim(input('mobile')) ? trim(input('mobile')) : '';
            $ipaddr = input('ipaddr', '');
            $usertype = intval(input('usertype', -1));
            $res = Api::getInstance()->sendRequest([
                'roleid' => $roleId,
                'roomid' => $roomId,
                'orderby' => $orderby,
                'page' => $page,
                'asc' => $asc,
                'issuperuser' => 100,
                'ipaddr' => $ipaddr,
                'gmtype' => $usertype,
                'mobile' => $mobile,
                'pagesize' => $limit
            ], 'player', 'superall');

            if (isset($res['data']['list']) && $res['data']['list']) {
                foreach ($res['data']['list'] as &$v) {
                    //盈利
                    $v['totalget'] = $v['totalin'] - $v['totalout'];
                    $v['lastloginip'] = $v['lastloginip'] . '(' . getIPcode($v['lastloginip']) . ')';
                    //活跃度
                    $v['huoyue'] = $v['totalin'] != 0 ? round($v['totalwater'] / $v['totalin'], 2) : 0;

                    $logintype = $v['gmtype'];
                    if ($logintype == 0) {
                        $v['gmtype'] = lang('游客');
                    } else if ($logintype == 1) {
                        $v['gmtype'] = 'Google';
                    } else if ($logintype == 2) {
                        $v['gmtype'] = 'Facebook';
                    } else if ($logintype == 3) {
                        $v['gmtype'] = 'IOS';
                    } else if ($logintype == 5) {
                        $v['gmtype'] = lang('手机');
                    } else if ($logintype == 6) {
                        $v['gmtype'] = lang('邮箱');
                    }
                }
                unset($v);
            }

            return $this->apiReturn($res['code'], isset($res['data']['list']) ? $res['data']['list'] : [], $res['message'], $res['total'],
                ['orderby' => isset($res['data']['orderby']) ? $res['data']['orderby'] : 0, 'asc' => isset($res['data']['asc']) ? $res['data']['asc'] : 0,
                ]);

        }
        $usertype = config('usertype');
        $this->assign('usertype', $usertype);
        $selectData = $this->getRoomList();
        $this->assign('selectData', $selectData);
        return $this->fetch();
    }

    public function agentlist()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $orderby = intval(input('orderby')) ? intval(input('orderby')) : 0;
            $asc = intval(input('asc')) ? intval(input('asc')) : 0;
            $mobile = trim(input('mobile')) ? trim(input('mobile')) : '';
            $ipaddr = input('ipaddr', '');
            $usertype = intval(input('usertype', -1));
            $res = Api::getInstance()->sendRequest([
                'roleid' => $roleId,
                'roomid' => $roomId,
                'orderby' => $orderby,
                'page' => $page,
                'asc' => $asc,
                'issuperuser' => 1000,
                'ipaddr' => $ipaddr,
                'gmtype' => $usertype,
                'mobile' => $mobile,
                'pagesize' => $limit
            ], 'player', 'superall');
            if (isset($res['data']['list']) && $res['data']['list']) {
                foreach ($res['data']['list'] as &$v) {
                    //盈利
                    $v['totalget'] = $v['totalin'] - $v['totalout'];
                    // $v['lastloginip'] = $v['lastloginip'] . '(' . getIPcode($v['lastloginip']) . ')';
                    //活跃度
                    //$v['huoyue'] = $v['totalin'] != 0 ? round($v['totalwater'] / $v['totalin'], 2) : 0;

                }
                unset($v);
            }
            return $this->apiReturn($res['code'], isset($res['data']['list']) ? $res['data']['list'] : [], $res['message'], $res['total'],
                ['orderby' => isset($res['data']['orderby']) ? $res['data']['orderby'] : 0, 'asc' => isset($res['data']['asc']) ? $res['data']['asc'] : 0,]);

        }
        $usertype = config('usertype');
        $this->assign('usertype', $usertype);
        $selectData = $this->getRoomList();
        $this->assign('selectData', $selectData);
        return $this->fetch();

    }

    /**
     * Notes: 超级玩家列表
     * @return mixed
     */
    public function super()
    {
        //OM_MasterDB.dbo.T_SuperUser
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $mobile = input('mobile');

//            $res = Api::getInstance()->sendRequest(['roleid' => $roleId, 'accountname' => $mobile, 'page' => $page, 'pagesize' => $limit], 'SuperUser', 'list');
//
//            return $this->apiReturn($res['code'], $res['data']['ResultData']['list'], $res['message'], $res['data']['total'],
//                [
//                    $res['data']['ResultData']['totalinsum'],
//                    $res['data']['ResultData']['totaloutsum'],
//                ]);
        }
        return $this->fetch();
    }

    /**
     * Notes: 新增超级玩家
     * @return mixed
     */

    public function addSuper()
    {
        if ($this->request->isAjax()) {
            $request = $this->request->request();
            $validate = validate('Player');
            $validate->scene('addSuper');
            if (!$validate->check($request)) {
                return $this->apiReturn(1, [], $validate->getError());
            }
            $socket = new QuerySocket();
            $res1 = $socket->setSuperPlayer($request['roleid'], 100);
            if ($res1['iResult'] == 0) {
                $account = new AccountDB();
                $account->updateByWhere(['AccountID' => $request['roleid']], ['countryCode' => $request['countrycode']]);
                GameLog::logData(__METHOD__, $request, 1, lang('添加成功'));
                return $this->apiReturn(0, '', '添加成功');
            } else {
                GameLog::logData(__METHOD__, $request, 0, $res1);
                return $this->apiReturn(1, [], '添加失败');
            }
        }

        $arearight = new AreaMsgRightSwitch();
        $list = $arearight->getListAll(['isshow' => 1], 'code,country', 'country  ');
        $this->assign('areainfo', $list);
        return $this->fetch();
    }

    public function delSuperUser()
    {
        $roleid = input('roleid', 0);
        if ($roleid > 0) {
            $request = $this->request->request();
            $socket = new QuerySocket();
            $res1 = $socket->setSuperPlayer($roleid, 0);
            if ($res1['iResult'] == 0) {//
                // Api::getInstance()->sendRequest(['roleid' => $roleid], 'SuperUser', 'delete');
                $res = ['code' => 0, 'data' => '', 'message' => '删除成功'];
                //log记录
                GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
                return $this->apiReturn($res['code'], $res['data'], $res['message']);
            } else {
                GameLog::logData(__METHOD__, $request, 0, $res1);
                return $this->apiReturn(1, [], '删除失败');
            }
        }
        return $this->apiReturn(1, [], '删除失败');
    }


    public function modifyAgentSuper()
    {
        $roleid = input('roleid', 0);
        $countrycode = input('countrycode', '');

        if (empty($roleid) || empty($countrycode)) {
            return $this->apiReturn(100, [], '参数错误');
        }

        if ($this->request->isAjax()) {
            $account = new model\AccountDB();
            $ret = $account->updateByWhere(['AccountID' => $roleid], ['countryCode' => $countrycode]);
            GameLog::logData(__METHOD__, $this->request, 1, '保存成功');
            return $this->apiReturn(0, '', '保存成功');
        }


        $this->assign('roleid', $roleid);
        $this->assign('countrycode', $countrycode);
        $arearight = new AreaMsgRightSwitch();
        $list = $arearight->getListAll(['isshow' => 1], 'code,country', 'country  ');
        $this->assign('areainfo', $list);
        return $this->fetch();
    }

    public function addAgentSuper()
    {
        if ($this->request->isAjax()) {
            $request = $this->request->request();
            $validate = validate('Player');
            $validate->scene('addSuper');
            if (!$validate->check($request)) {
                return $this->apiReturn(1, [], $validate->getError());
            }

            $socket = new QuerySocket();
            $res1 = $socket->setSuperPlayer($request['roleid'], 1000);
            if ($res1['iResult'] == 0) {
                // $res = Api::getInstance()->sendRequest(['roleid' => $request['roleid'], 'rate' => 10], 'SuperUser', 'add');
                //log记录
                $account = new model\AccountDB();
                $account->updateByWhere(['AccountID' => $request['roleid']], ['countryCode' => $request['countrycode']]);
                GameLog::logData(__METHOD__, $request, 1, '添加成功');
                return $this->apiReturn(0, '', '添加成功');
            } else {
                GameLog::logData(__METHOD__, $request, 0, $res1);
                return $this->apiReturn(1, [], '添加失败');
            }

        }
        $arearight = new AreaMsgRightSwitch();
        $list = $arearight->getListAll(['isshow' => 1], 'code,country', 'country  ');
        $this->assign('areainfo', $list);
        return $this->fetch();
    }

    /**
     * 向玩家转账
     */
    public function transfer()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $start = input('start') ? input('start') : '';
            $end = input('end') ? input('end') : '';
            $classid = input('classid') ? input('classid') : -1;
            $mobile = input('mobile');

            $data = ['page' => $page, 'pagesize' => $limit];
            $data['typeid'] = 0;
            if ($roleId) {
                $data['roleid'] = $roleId;
            }
            if ($classid && $classid != -1) {
                $data['classid'] = $classid;
            }
            if ($start) {
                $data['starttime'] = $start;
                if ($end) {
                    $data['endtime'] = $end;
                }
            }
            if ($mobile) {
                $data['accountname'] = $mobile;
            }
            $res = Api::getInstance()->sendRequest($data, 'charge', 'list');

            return $this->apiReturn($res['code'], isset($res['data']['list']) ? $res['data']['list'] : [], $res['message'], $res['total'],
                isset($res['data']['total']) ? $res['data']['total'] : 0);
        }
        return $this->fetch();
    }

    /**
     * 玩家上下分记录
     */
    public function transfer2()
    {
        $strFields = "a.roomid,roomname,maxcount,robotwinweighted,robotwinmoney,servicetables";//
        $tableName = " [OM_MasterDB].[dbo].[T_RoomRobot] a, (SELECT roomid,roomname,Nullity FROM [OM_MasterDB].[dbo].[T_GameRoomInfo]) b"; //
        $where = " where a.roomid = b.roomid and  Nullity=0 ";
        $limits = "";
        $orderBy = " order by roomid desc";

        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $start = input('start') ? input('start') : '';
            $end = input('end') ? input('end') : '';
            $classid = input('classid') ? input('classid') : -1;
            $mobile = input('mobile');

            $data = ['page' => $page, 'pagesize' => $limit];
            $data['typeid'] = 0;
            if ($roleId) {
                $data['roleid'] = $roleId;
            }
            if ($classid && $classid != -1) {
                $data['classid'] = $classid;
            }
            if ($start) {
                $data['starttime'] = $start;
                if ($end) {
                    $data['endtime'] = $end;
                }
            }
            if ($mobile) {
                $data['accountname'] = $mobile;
            }
            $res = Api::getInstance()->sendRequest($data, 'charge', 'list');

            return $this->apiReturn($res['code'], isset($res['data']['list']) ? $res['data']['list'] : [], $res['message'], $res['total'],
                isset($res['data']['total']) ? $res['data']['total'] : 0);
        }
        return $this->fetch();
    }


    /**Gm充值管理*/
    public function TransferManager()
    {
        switch (input('Action')) {
            case 'list':
                $db = new  GameOCDB('',true);
                return $this->apiJson($db->GMSendMoney());
                break;
            case  'add':
                if (request()->isAjax()) {
                    //权限验证
                    $auth_ids = $this->getAuthIds();
                    if (!in_array(10001, $auth_ids)) {
                        return $this->apiReturn(2, [], '没有权限');
                    }
                    $password = input('password');
                    $user_controller = new \app\admin\controller\User();
                    $pwd = $user_controller->rsacheck($password);
                    if (!$pwd) {
                        return json(['code' => 2, 'msg' => '密码错误']);
                    }
                    $userModel = new userModel();
                    $userInfo = $userModel->getRow(['id' => session('userid')]);
                    if (md5($userInfo['salt'] . $pwd) !== $userInfo['password']) {
                        return json(['code' => 2, 'msg' => '密码有误，请重新输入']);
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
                    $db = new  GameOCDB('',true);
                    $row = $db->GMSendMoneyAdd(['RoleId' => $roleID, 'Money' => $money, 'status' => 0, 'Note' => $descript, 'checkUser' => session('username'), 'OperateType' => $operatetype]);
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
                    $id = input('ID');
                    if (isset($id)){
                        $this->GmTransferSend($id);
                    }else{
                        $ids = explode(',', input('Ids'));
                        foreach($ids as $id){
                            $this->GmTransferSend($id);
                        }
                        $this->success("审核成功");

                    }
                }
                break;
            case 'deny':
                $id = input('ID');
                if (isset($id)){
                    $db = new  GameOCDB('',true);
                    $row = $db->TGMSendMoney()->UPData(["status" => 2, "UpdateTime" => date('Y-m-d H:i:s')], "ID=".input('ID'));
                    if ($row > 0) return $this->success("成功");
                    return $this->error('失败');
                }else{
                    $ids = explode(',', input('Ids'));
                    foreach($ids as $id){
                        $db = new  GameOCDB('',true);
                        $row = $db->TGMSendMoney()->UPData(["status" => 2, "UpdateTime" => date('Y-m-d H:i:s')], "ID=" . $id);
//                        $this->GmTransferDeny($id);
                    }
                    $this->success("审核成功");
//                    $id = input('ID');

//                    var_dump($id);
//                    $this->GmTransferDeny($id);
                }
                break;
            case 'exec':
                //权限验证
                $auth_ids = $this->getAuthIds();
                if (!in_array(10008, $auth_ids)) {
                    return $this->apiJson(["code" => 1, "msg" => "没有权限"]);
                }
                $db = new  GameOCDB('',true);
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

    public function TransferReview()
    {
        return $this->fetch();
    }

    /**
     * GM充值 向玩家转账
     */
    public function addTransfer()
    {

        if ($this->request->isAjax()) {
            $request = $this->request->request();
            $validate = validate('Player');
            $validate->scene('addTransfer');

            if (!$validate->check($request)) return $this->apiReturn(1, [], $validate->getError());
            $type = 0;
            if ($request['totalmoney'] < 0) $type = 1;
            //加锁
            $key = 'lock_addtransfer_' . $request['roleid'];
            if (!Redis::lock($key)) return $this->apiReturn(2, [], '请勿重复操作');
            $data = [
                'roleid' => $request['roleid'],
//                'classid' => $request['classid'],
                'totalmoney' => $request['totalmoney'],
                'uid' => session('userid'),
                'adduser' => session('username'),
                'typeid' => 0,
                'descript' => $request['descript'] ? $request['descript'] : ''];
            $res = $this->sendGameMessage('CMD_MD_ADD_ROLE_MONERY', [$data['roleid'], $data['totalmoney'] * 1000, $type, 0, getClientIP()]);
            $res = unpack('Lcode/', $res);
            if ($res['code'] == 0) {
                $res['msg'] = "请求成功";
                if (isset($request['s'])) unset($request['s']);
                //log记录
                GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
            } else {
                $res['msg'] = "请求失败 Server code: " . $res['code'];
                ($res['code'] == 2) ? $res['msg'] .= "<br>他她它还在游戏中,请勿打扰.^v^! " : $res['msg'] .= "<br>没有找打对应的账号所以我也无能为力 O.O ";
            }

            Redis::rm($key);
            return $this->apiJson($res);
//            return $this->apiReturn($res['code'],[],$res['msg']);
        }
        return $this->fetch();
    }

    //玩家重置密码
    public function resetPwd()
    {
        $request = $this->request->request();
        $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
        $pwd = input('Password') ? input('Password') : '';
        if (!$roleId || !$pwd) {
            return $this->apiReturn(1, [], '必填项不能为空');
        }


        $socket = new QuerySocket();
        $res = $socket->setPlayerPwd($roleId, $pwd);

        //log记录
        GameLog::logData(__METHOD__, $request, (isset($res['iResult']) && $res['iResult'] == 0) ? 1 : 0, $res);
        if (isset($res['iResult']) && $res['iResult'] == 0) {
            return $this->apiReturn($res['iResult'], [], '修改成功');
        } else {
            return $this->apiReturn(1, [], '修改失败');
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

    //玩家牌型
    public function cardtype()
    {
        $roomlist = Api::getInstance()->sendRequest(['id' => 0], 'room', 'kind');

        if ($this->request->isAjax()) {
            $roomId = intval(input('roomId')) ? intval(input('roomId')) : 0;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 15;
//            $res = $this->socket->getProfitPercent($roomId);
//            if ($res) {
//                if ($roomlist['data']) {
//                    foreach ($res as &$v) {
//                        foreach ($roomlist['data'] as $v2) {
//                            if ($v['nRoomId'] == $v2['roomid']) {
//                                $v['roomname'] = $v2['roomname'];
//                            }
//                        }
//
//                        $v['lTotalRunning'] /= 1000;
//                        $v['lTotalProfit']  /= 1000;
//                    }
//                    unset($v);
//                }
//            }
            $res = Api::getInstance()->sendRequest(['id' => $roomId], 'room', 'kind');
            $res = isset($res['data']) ? $res['data'] : [];

            //假分页
            $result = [];

            $from = ($page - 1) * $limit;
            $to = $page * $limit - 1;
            $count = count($res);
            if ($count > 0 && $count >= $from) {
                for ($i = $from; $i <= $to; $i++) {
                    if (isset($res[$i])) {
                        $result[] = $res[$i];
                    }
                }
            }
            ob_clean();
            return $this->apiReturn(0, $result, '', $count);
        }
        //$roomList = Api::getInstance()->sendRequest(['id' => 0], 'room', 'kind');
        $kindList = Api::getInstance()->sendRequest(['id' => 0], 'room', 'kindlist');
        $this->assign('roomlist', $roomlist['data']);
        $this->assign('kindlist', $kindList['data']);
        return $this->fetch();
    }

    //查看玩家详情
    public function detail()
    {

        $uniqueid = input('uniqueid') ? input('uniqueid') : '';
        $userid = input('userid') ? input('userid') : 0;
        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $page = intval(input('page')) ? intval(input('page')) : 1;
        $limit = intval(input('limit')) ? intval(input('limit')) : 10;
        $res = Api::getInstance()->sendRequest([
            'uniqueid' => $uniqueid,
            'roomid' => $roomId,
            'userid' => $userid,
            'page' => $page,
            'pagesize' => $limit
        ], 'game', 'getcart');


        $res = json_decode($res['data'][0]['gamedetail'], true);
        if ($res) {
            $this->assign('bet', $res['bet']);
            $this->assign('basescore', $res['bet']['basescore']);
            $this->assign('chuntian', $res['bet']['chuntian']);
            $this->assign('totaltime', $res['bet']['totaltime']);
            $this->assign('boomtime', $res['bet']['boomtime']);
            $this->assign('callscore', $res['bet']['callscore']);
            $this->assign('qiangscore', $res['bet']['qiangscore']);
            $this->assign('boomtime', $res['bet']['boomtime']);
            if (isset($res['lose'])) {
                $this->assign('win', $res['lose']);
            } else if (isset($res['lost'])) {
                $this->assign('win', $res['lost']);
            } else {
                $this->assign('win', $res['win']);
            }

            if (isset($res['card']['player2']) && isset($res['card']['player1']) && !isset($res['card']['player0'])) {
                if ($res['bet']['player2'] == 'single') {
                    $this->assign('nplay2', '不加倍');
                } else {
                    $this->assign('nplay2', '加倍');
                }
                if ($res['bet']['player1'] == 'single') {
                    $this->assign('nplay0', '不加倍');
                } else {
                    $this->assign('nplay0', '加倍');
                }

            }
            if (isset($res['card']['player2']) && isset($res['card']['player0']) && !isset($res['card']['player1'])) {
                if ($res['bet']['player2'] == 'single') {
                    $this->assign('nplay2', '不加倍');
                } else {
                    $this->assign('nplay2', '加倍');
                }
                if ($res['bet']['player0'] == 'single') {
                    $this->assign('nplay0', '不加倍');
                } else {
                    $this->assign('nplay0', '加倍');
                }

            }
            if (isset($res['card']['player1']) && isset($res['card']['player0']) && !isset($res['card']['player2'])) {
                if ($res['bet']['player1'] == 'single') {
                    $this->assign('nplay2', '不加倍');
                } else {
                    $this->assign('nplay2', '加倍');
                }
                if ($res['bet']['player0'] == 'single') {
                    $this->assign('nplay0', '不加倍');
                } else {
                    $this->assign('nplay0', '加倍');
                }

            }

            $this->assign('dizhu', $res['host']['userid']);


            if (isset($res['card']['host1'])) {
                $res['card']['host1'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'],
                    ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['card']['host1']);
                $host1 = explode(",", $res['card']['host1']);
                $host1 = array_slice($host1, 0, 17);
                $this->assign('host1', $host1);

                if ($res['remaincard']['host1']) {
                    $res['remaincard']['host1'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'],
                        ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['remaincard']['host1']);
                    $rehost1 = explode(",", $res['remaincard']['host1']);
//                    $rehost1 =array_slice($rehost1,0,17);
                    $rehost1 = array_slice($rehost1, 0, count($rehost1) - 1);
                    $this->assign('rehost1', $rehost1);
                } else {
                    $victory = array("victory");
                    $this->assign('rehost1', $victory);
                    $this->assign('vich', 'vic');
                    $this->assign('vic0', 'vic44');
                    $this->assign('vic2', 'vic44');
                }
                $this->assign('hostname', trim($res['roleid']['host1']));
            } else if (isset($res['card']['host2'])) {
                $res['card']['host2'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'],
                    ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['card']['host2']);
                $host1 = explode(",", $res['card']['host2']);
                $host1 = array_slice($host1, 0, 17);
                $this->assign('host1', $host1);

                if ($res['remaincard']['host2']) {
                    $res['remaincard']['host2'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'],
                        ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['remaincard']['host2']);
                    $rehost1 = explode(",", $res['remaincard']['host2']);
//                    $rehost1 =array_slice($rehost1,0,17);
                    $rehost1 = array_slice($rehost1, 0, count($rehost1) - 1);
                    $this->assign('rehost1', $rehost1);
                } else {
                    $victory = array("victory");
                    $this->assign('rehost1', $victory);
                    $this->assign('vich', 'vic');
                    $this->assign('vic0', 'vic44');
                    $this->assign('vic2', 'vic44');
                }
                $this->assign('hostname', trim($res['roleid']['host2']));
            } else {
                $res['card']['host2'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'],
                    ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['card']['host0']);
                $host1 = explode(",", $res['card']['host2']);
                $host1 = array_slice($host1, 0, 17);
                $this->assign('host1', $host1);

                if ($res['remaincard']['host0']) {
                    $res['remaincard']['host2'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'],
                        ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['remaincard']['host0']);
                    $rehost1 = explode(",", $res['remaincard']['host2']);
//                    $rehost1 =array_slice($rehost1,0,17);
                    $rehost1 = array_slice($rehost1, 0, count($rehost1) - 1);
                    $this->assign('rehost1', $rehost1);
                } else {
                    $victory = array("victory");
                    $this->assign('rehost1', $victory);
                    $this->assign('vich', 'vic');
                    $this->assign('vic0', 'vic3');
                    $this->assign('vic2', 'vic5');
                }
                $this->assign('hostname', trim($res['roleid']['host0']));
            }


//var_dump($res['card']);die;
            if (isset($res['card']['player0'])) {
                $res['card']['player0'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'],
                    ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['card']['player0']);
                $player0 = explode(",", $res['card']['player0']);
                $player0 = array_slice($player0, 0, 17);
                $this->assign('player0', $player0);

                if ($res['remaincard']['player0']) {
                    $res['remaincard']['player0'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'],
                        ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['remaincard']['player0']);
                    $replayer0 = explode(",", $res['remaincard']['player0']);
//                    $replayer0 =array_slice($replayer0,0,17);
                    $replayer0 = array_slice($replayer0, 0, count($replayer0) - 1);
                    $this->assign('replayer0', $replayer0);
                } else {
                    $victory = array("victory");
                    $this->assign('replayer0', $victory);
                    $this->assign('vic0', 'vic');
                    $this->assign('vich', 'vic3');
                    $this->assign('vic2', 'vic5');
                }
                $this->assign('player0name', trim($res['roleid']['player0']));
            }

            if (isset($res['card']['player2'])) {
                $res['card']['player2'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'],
                    ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['card']['player2']);
                $player2 = explode(",", $res['card']['player2']);
                $player2 = array_slice($player2, 0, 17);
                $this->assign('player2', $player2);

                if ($res['remaincard']['player2']) {
                    $res['remaincard']['player2'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'],
                        ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['remaincard']['player2']);
                    $replayer2 = explode(",", $res['remaincard']['player2']);
//                    $replayer2 =array_slice($replayer2,0,17);
                    $replayer2 = array_slice($replayer2, 0, count($replayer2) - 1);
                    $this->assign('replayer2', $replayer2);
                } else {
                    $victory = array("victory");
                    $this->assign('replayer2', $victory);
                    $this->assign('vic2', 'vic');
                    $this->assign('vich', 'vic1');
                    $this->assign('vic0', 'vic2');
                }
                $this->assign('player2name', trim($res['roleid']['player2']));
            }
//            else{
            if (isset($res['card']['player1'])) {
                $res['card']['player2'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'],
                    ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['card']['player1']);
                $player2 = explode(",", $res['card']['player2']);
                $player2 = array_slice($player2, 0, 17);
                $this->assign('player2', $player2);

                if ($res['remaincard']['player1']) {
                    $res['remaincard']['player2'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'],
                        ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['remaincard']['player1']);
                    $replayer2 = explode(",", $res['remaincard']['player2']);
//                    $replayer2 =array_slice($replayer2,0,17);
                    $replayer2 = array_slice($replayer2, 0, count($replayer2) - 1);
                    $this->assign('replayer2', $replayer2);
                } else {
                    $victory = array("victory");
                    $this->assign('replayer2', $victory);
                    $this->assign('vic2', 'vic');
                    $this->assign('vich', 'vic1');
                    $this->assign('vic0', 'vic2');
                }
                $this->assign('player2name', trim($res['roleid']['player1']));
            }


            if (isset($res['card']['player2']) && isset($res['card']['player1']) && !isset($res['card']['player0'])) {
                $res['card']['player2'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'],
                    ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['card']['player1']);
                $player2 = explode(",", $res['card']['player2']);
                $player2 = array_slice($player2, 0, 17);
                $this->assign('player2', $player2);

                if ($res['remaincard']['player1']) {
                    $res['remaincard']['player2'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'],
                        ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['remaincard']['player1']);
                    $replayer2 = explode(",", $res['remaincard']['player2']);
//                    $replayer2 =array_slice($replayer2,0,17);
                    $replayer2 = array_slice($replayer2, 0, count($replayer2) - 1);
                    $this->assign('replayer2', $replayer2);
                } else {
                    $victory = array("victory");
                    $this->assign('replayer2', $victory);
                    $this->assign('vic2', 'vic');
                    $this->assign('vich', 'vic1');
                    $this->assign('vic0', 'vic2');
                }

                $res['card']['player2'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'],
                    ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['card']['player2']);
                $player2 = explode(",", $res['card']['player2']);
                $player2 = array_slice($player2, 0, 17);
                $this->assign('player0', $player2);

                if ($res['remaincard']['player2']) {
                    $res['remaincard']['player2'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'],
                        ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['remaincard']['player2']);
                    $replayer2 = explode(",", $res['remaincard']['player2']);
//                    $replayer2 =array_slice($replayer2,0,17);
                    $replayer2 = array_slice($replayer2, 0, count($replayer2) - 1);
                    $this->assign('replayer0', $replayer2);
                } else {
                    $victory = array("victory");
                    $this->assign('replayer0', $victory);
                    $this->assign('vic0', 'vic');
                    $this->assign('vich', 'vic1');
                    $this->assign('vic0', 'vic2');
                }

                $this->assign('player0name', trim($res['roleid']['player1']));
                $this->assign('player2name', trim($res['roleid']['player2']));
            }


//            $this->assign('hostname', trim($res['roleid']['host1']));
//            $this->assign('player0name', trim($res['roleid']['player0']));
//            $this->assign('player2name', trim($res['roleid']['player2']));


        }


        return $this->fetch();
    }

    //比倍详情
    public function detailbibei()
    {
        $uniqueid = input('uniqueid') ? input('uniqueid') : '';
        $userid = input('userid') ? input('userid') : 0;
        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $roomname = input('roomname');
        $date = input('date');
        $page = intval(input('page')) ? intval(input('page')) : 1;
        $limit = intval(input('limit')) ? intval(input('limit')) : 10;
        $res = Api::getInstance()->sendRequest([
            'uniqueid' => $uniqueid,
            'roomid' => $roomId,
            'userid' => $userid,
            'date' => $date
        ], 'game', 'GetTigerDetail');

        $result = $res['data'];
        $dragontype = ['white', 'red', 'gray', 'blue', 'gold', 'purple'];

        $cardconfig = ['方块', '梅花', '红桃', '黑桃', '红色', '黑色'];
        $tiger = json_decode($result['gamedetail'], true);
        $pictag = ['F', 'M', 'H', 'T'];
        $record = [
            'DiceAward' => 0,
            'DiceBet' => 0,
            'DiceBetResult' => 0,
            'DiceBetType' => 0,
            'UserId' => 0
        ];

        if (!empty($tiger)) {
            $record = $tiger;
            if ($tiger['DiceBetResult'] > 0) {

                $k = $tiger['DiceBetResult'] / 16;
                $j = $tiger['DiceBetResult'] % 16;
                $num = $j;
                if ($j == 11)
                    $num = 'J';
                else if ($j == 12)
                    $num = 'Q';
                else if ($j == 13)
                    $num = 'K';

                $tag = $pictag[$k] . $num . '.png';
                $record['cardpic'] = '/public/static/poker/' . $tag;

            }
            $record['cardname'] = $cardconfig[$tiger['DiceBetType']];
        }

        $record['addtime'] = $result['addtime'];
        $record['roomname'] = $roomname;

        $this->assign('game', $record);
        return $this->fetch();
    }

    public function detailtiger()
    {
        $uniqueid = input('uniqueid') ? input('uniqueid') : '';
        $userid = input('userid') ? input('userid') : 0;
        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $roomname = input('roomname');
        $date = input('date');
        $page = intval(input('page')) ? intval(input('page')) : 1;
        $limit = intval(input('limit')) ? intval(input('limit')) : 10;
        $res = Api::getInstance()->sendRequest([
            'uniqueid' => $uniqueid,
            'roomid' => $roomId,
            'userid' => $userid,
            'date' => $date
        ], 'game', 'GetTigerDetail');

        $result = $res['data'];
        $dragontype = ['white', 'red', 'gray', 'blue', 'gold', 'purple'];

        $tiger = json_decode($result['gamedetail'], true);
        $pic = [];
        if ($tiger['Scene'] <= 2) {
            $area = explode(' ', $tiger['Area']);
            $roomId = intval($roomId / 10) * 10;
            foreach ($area as $item) {
                if (!empty($item)) {
                    $dragon_icon = '';
                    if (($roomId / 10) * 10 == 4500 && $item == 12) {
                        $dragon_icon = '_' . $dragontype[$tiger['DragonType']];
                    }
                    $item = '/public/tiger/' . $roomId . '/icon_' . $item . $dragon_icon . '.png';
                    array_push($pic, $item);
                }
            }
        } else {

        }
        unset($result['tiger']);

        $linedetail = [];
        $totalscore = 0;
        $totalwin = 0;
        if (!empty($tiger['LineAwardItem'])) {
            $item = explode(' ', $tiger['LineAwardItem']);
            foreach ($item as $k) {
                $it = explode('-', $k);
                $icon_num = '';
                if ($it[0] > 0) {
                    $icon = $it[0];
                    $isline = 1;
                    if ($it[0] > 100) {
                        $icon = $it[0] - 100;
                        $icon_num = "<img src='/public/tiger/" . $roomId . "/icon_" . $icon . ".png'/>";
                        $isline = 0;
                    } else
                        $icon_num = 'Line' . $it[0];

                    $totalscore += $tiger['BaseScore'];
                    $totalwin += $it[1];
                    $linedetail[] = [
                        'icon' => $icon_num,
                        'basescore' => $tiger['BaseScore'] / 10,
                        'isline' => $isline == 1 ? '是' : '否',
                        'point' => $it[1] / 10
                    ];
                }
            }
        }
        $linedetail[] = [
            'icon' => '<b sytle="color:blue">总计</b>',
            'basescore' => '<b sytle="color:blue">' . ($totalscore / 10) . '</b>',
            'isline' => '-',
            'point' => '<b sytle="color:blue">' . ($totalwin / 10) . '</b>'
        ];
        $data = [
            'addtime' => $result['addtime'],
            'BaseScore' => $tiger['BaseScore'] / 10,
            'GoldAward' => $tiger['GoldAward'] / 10,
            'Lines' => $tiger['Lines'],
            'Multiply' => $tiger['Multiply'],
            'roomname' => $roomname,
            'TotalScore' => intval($tiger['BaseScore']) * intval($tiger['Lines']) / 10
        ];
        $this->assign('game', $data);
        $this->assign('piclist', $pic);
        $this->assign('roomid', ($roomId / 10) * 10);
        $this->assign('linedetail', $linedetail);
        return $this->fetch();
    }

    public function lookPartnerCard()
    {
        if ($this->request->isAjax()) {

            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
//            $roomId = $this->request->request('roomid');
//            var_dump($roleId );die;
            $res = Api::getInstance()->sendRequest([
                'userid' => $roleId,
                'roomid' => $roomId,
                'page' => $page,
                'pagesize' => $limit
            ], 'game', 'getcart');
            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);

        }

        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $this->assign('roomid', $roomId);
        return $this->fetch();
    }

    /**
     * 查看伙牌
     */
    public function lookPartnerCardZjh()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $res = Api::getInstance()->sendRequest([
                'userid' => $roleId,
                'roomid' => $roomId,
                'page' => $page,
                'pagesize' => $limit
            ], 'game', 'getcart');
            if (!empty($res['data'])) {
                foreach ($res['data'] as &$item) {
                    $item['card'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'],
                        ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $item['card']);
                    $item['card'] = explode(",", $item['card']);
                    $item['coinbefore'] = $item['coinbefore'] / 1000;
                    $item['changemoney'] = $item['changemoney'] / 1000;
                    $item['coinafter'] = $item['coinafter'] / 1000;
                }
                unset($item);
            }


            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);

        }

        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $this->assign('roomid', $roomId);
        return $this->fetch();
    }

    public function lookPartnerCardMpqz()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $res = Api::getInstance()->sendRequest([
                'userid' => $roleId,
                'roomid' => $roomId,
                'page' => $page,
                'pagesize' => $limit
            ], 'game', 'getcart');
            if (!empty($res['data'])) {
                foreach ($res['data'] as &$item) {
                    $item['card'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'],
                        ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $item['card']);
                    $item['card'] = explode(",", $item['card']);
                    $item['coinbefore'] = $item['coinbefore'] / 1000;
                    $item['changemoney'] = $item['changemoney'] / 1000;
                    $item['coinafter'] = $item['coinafter'] / 1000;
                }
                unset($item);
            }


            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);

        }

        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $this->assign('roomid', $roomId);
        return $this->fetch();
    }

    public function lookPartnerCardBrnn()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $uniqueid = intval(input('uniqueid')) ? intval(input('uniqueid')) : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $res = Api::getInstance()->sendRequest([
                'userid' => $roleId,
                'roomid' => $roomId,
                'uniqueid' => $uniqueid,
                'tag' => 1,
                'page' => $page,
                'pagesize' => $limit
            ], 'game', 'getcart2');
            if (!empty($res['data'])) {
                foreach ($res['data'] as &$item) {
                    $item['card'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'],
                        ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $item['card']);
                    $item['card'] = explode(" ", $item['card']);
                    $item['changemoney'] = $item['changemoney'] / 1000;
                    $item['totalbet'] = $item['totalbet'] / 1000;
                }
                unset($item);
            }


            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);

        }

        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $this->assign('roomid', $roomId);
        return $this->fetch();
    }

    public function lookPartnerCardDzpk()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $uniqueid = intval(input('uniqueid')) ? intval(input('uniqueid')) : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $res = Api::getInstance()->sendRequest([
                'userid' => $roleId,
                'roomid' => $roomId,
                'uniqueid' => $uniqueid,
                'page' => $page,
                'pagesize' => $limit
            ], 'game', 'getcart');
            if (!empty($res['data'])) {
                foreach ($res['data'] as &$item) {
                    $item['card'] = str_replace(['黑', '梅', '方', '红', '大王4', '小王'],
                        ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $item['card']);
                    $item['card'] = explode(",", $item['card']);
                    $item['tablecard'] = str_replace(['黑', '梅', '方', '红', '大王4', '小王'],
                        ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $item['tablecard']);
                    $item['tablecard'] = explode(",", $item['tablecard']);
                    $item['coinbefore'] = $item['coinbefore'] / 1000;
                    $item['changemoney'] = $item['changemoney'] / 1000;
                    $item['coinafter'] = $item['coinafter'] / 1000;
                }
                unset($item);
            }


            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);

        }

        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $this->assign('roomid', $roomId);
        return $this->fetch();
    }

    public function lookPartnerCardHlsb()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $uniqueid = intval(input('uniqueid')) ? intval(input('uniqueid')) : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $res = Api::getInstance()->sendRequest([
                'userid' => $roleId,
                'roomid' => $roomId,
                'uniqueid' => $uniqueid,
                'tag' => 1,
                'page' => $page,
                'pagesize' => $limit
            ], 'game', 'getcart2');
            if (!empty($res['data'])) {
                foreach ($res['data'] as &$item) {
//                    $item['card'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'], ['1', '2', '3', '4', '5', '6'], $item['card']);
                    $item['card'] = explode(" ", $item['card']);
                    $item['changemoney'] = $item['changemoney'] / 1000;
                    $item['totalbet'] = $item['totalbet'] / 1000;
                }
                unset($item);
            }


            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);

        }

        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $this->assign('roomid', $roomId);
        return $this->fetch();
    }

    public function lookPartnerCardMj()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $uniqueid = input('uniqueid') ? input('uniqueid') : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $res = Api::getInstance()->sendRequest([
                'userid' => $roleId,
                'roomid' => $roomId,
                'uniqueid' => $uniqueid,
                'page' => $page,
                'pagesize' => $limit
            ], 'game', 'getcart');

            if (!empty($res['data'])) {
                foreach ($res['data'] as &$item) {
//                    $item['card'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'], ['1', '2', '3', '4', '5', '6'], $item['card']);
                    $item['card'] = str_replace(['一', '二', '三', '四', '五', '六', '七', '八', '九', '万'],
                        ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'w'], $item['card']);
                    $item['card'] = str_replace(['东', '南', '西', '北', '白', '发', '中'],
                        ['df', 'nf', 'xf', 'bf', 'bb', 'fc', 'hz'], $item['card']);
                    $item['tablecard'] = str_replace(['一', '二', '三', '四', '五', '六', '七', '八', '九', '万'],
                        ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'w'], $item['tablecard']);
                    $item['tablecard'] = str_replace(['东', '南', '西', '北', '白', '发', '中'],
                        ['df', 'nf', 'xf', 'bf', 'bb', 'fc', 'hz'], $item['tablecard']);
                    $item['card'] = explode(",", $item['card']);
                    $item['tablecard'] = explode(",", $item['tablecard']);
                    $item['changemoney'] = $item['changemoney'] / 1000;
                    $item['coinbefore'] = $item['coinbefore'] / 1000;
                    $item['coinafter'] = $item['coinafter'] / 1000;
                }
                unset($item);
            }


            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);

        }

        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $this->assign('roomid', $roomId);
        return $this->fetch();
    }

    public function detailMj()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $uniqueid = input('uniqueid') ? input('uniqueid') : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $res = Api::getInstance()->sendRequest([
                'userid' => $roleId,
                'roomid' => $roomId,
                'uniqueid' => $uniqueid,
                'page' => $page,
                'pagesize' => $limit
            ], 'game', 'getcart');

            if (!empty($res['data'])) {
                foreach ($res['data'] as &$item) {

//                    $item['card'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'], ['1', '2', '3', '4', '5', '6'], $item['card']);
                    $item['card'] = str_replace(['一', '二', '三', '四', '五', '六', '七', '八', '九', '万'],
                        ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'w'], $item['card']);
                    $item['card'] = str_replace(['东', '南', '西', '北', '白', '发', '中'],
                        ['df', 'nf', 'xf', 'bf', 'bb', 'fc', 'hz'], $item['card']);
                    $item['tablecard'] = str_replace(['一', '二', '三', '四', '五', '六', '七', '八', '九', '万'],
                        ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'w'], $item['tablecard']);
                    $item['tablecard'] = str_replace(['东', '南', '西', '北', '白', '发', '中'],
                        ['df', 'nf', 'xf', 'bf', 'bb', 'fc', 'hz'], $item['tablecard']);
                    $item['card'] = explode(",", $item['card']);
                    $item['tablecard'] = explode(",", $item['tablecard']);
                    $item['changemoney'] = $item['changemoney'] / 1000;
                    $item['coinbefore'] = $item['coinbefore'] / 1000;
                    $item['coinafter'] = $item['coinafter'] / 1000;
                }
                unset($item);
            }


            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);

        }

        $uniqueid = input('uniqueid') ? input('uniqueid') : '';
        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $this->assign('uniqueid', $uniqueid);
        $this->assign('roomId', $roomId);
        return $this->fetch();
    }

    public function detailMj2()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $uniqueid = input('uniqueid') ? input('uniqueid') : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $res = Api::getInstance()->sendRequest([
                'userid' => $roleId,
                'roomid' => $roomId,
                'uniqueid' => $uniqueid,
                'page' => $page,
                'pagesize' => $limit
            ], 'game', 'getcart');

            if (!empty($res['data'])) {
                foreach ($res['data'] as &$item) {

//                    $item['card'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'], ['1', '2', '3', '4', '5', '6'], $item['card']);
                    $item['card'] = str_replace(['一', '二', '三', '四', '五', '六', '七', '八', '九', '万'],
                        ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'w'], $item['card']);
                    $item['card'] = str_replace(['东', '南', '西', '北', '白', '发', '中'],
                        ['df', 'nf', 'xf', 'bf', 'bb', 'fc', 'hz'], $item['card']);
                    $item['tablecard'] = str_replace(['一', '二', '三', '四', '五', '六', '七', '八', '九', '万'],
                        ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'w'], $item['tablecard']);
                    $item['tablecard'] = str_replace(['东', '南', '西', '北', '白', '发', '中'],
                        ['df', 'nf', 'xf', 'bf', 'bb', 'fc', 'hz'], $item['tablecard']);
                    $item['card'] = explode(",", $item['card']);
                    $item['tablecard'] = explode(",", $item['tablecard']);
                    $item['changemoney'] = $item['changemoney'] / 1000;
                    $item['coinbefore'] = $item['coinbefore'] / 1000;
                    $item['coinafter'] = $item['coinafter'] / 1000;
                }
                unset($item);
            }


            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);

        }

        $uniqueid = input('uniqueid') ? input('uniqueid') : '';
        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $this->assign('uniqueid', $uniqueid);
        $this->assign('roomId', $roomId);
        return $this->fetch();
    }

    public function lookPartnerCardBjl()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $uniqueid = intval(input('uniqueid')) ? intval(input('uniqueid')) : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $res = Api::getInstance()->sendRequest([
                'userid' => $roleId,
                'roomid' => $roomId,
                'uniqueid' => $uniqueid,
                'tag' => 1,
                'page' => $page,
                'pagesize' => $limit
            ], 'game', 'getcart2');
            if (!empty($res['data'])) {
                foreach ($res['data'] as &$item) {
                    $item['card'] = str_replace(['banker', 'playercouple', 'player', 'equal', 'bankercouple'],
                        ['庄', '闲对', '闲 ', '和', '庄对'], $item['card']);
//                    $item['card'] =explode(" ", $item['card']);
                    $item['changemoney'] = $item['changemoney'] / 1000;
                    $item['totalbet'] = $item['totalbet'] / 1000;
                }
                unset($item);
            }


            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);

        }

        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $this->assign('roomid', $roomId);
        return $this->fetch();
    }

    public function lookPartnerCardLonghudou()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $uniqueid = intval(input('uniqueid')) ? intval(input('uniqueid')) : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $res = Api::getInstance()->sendRequest([
                'userid' => $roleId,
                'roomid' => $roomId,
                'uniqueid' => $uniqueid,
                'tag' => 1,
                'page' => $page,
                'pagesize' => $limit
            ], 'game', 'getcart2');
            if (!empty($res['data'])) {
                foreach ($res['data'] as &$item) {
//                    $item['card'] = str_replace(['banker', 'playercouple', 'player', 'equal', 'bankercouple'], ['庄', '闲对', '闲 ', '和', '庄对'], $item['card']);
//                    $item['card'] =explode(" ", $item['card']);
                    $item['changemoney'] = $item['changemoney'] / 1000;
                    $item['totalbet'] = $item['totalbet'] / 1000;
                }
                unset($item);
            }

            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);

        }

        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $this->assign('roomid', $roomId);
        return $this->fetch();
    }

    public function lookPartnerCardBcbm()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $uniqueid = intval(input('uniqueid')) ? intval(input('uniqueid')) : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $res = Api::getInstance()->sendRequest([
                'userid' => $roleId,
                'roomid' => $roomId,
                'uniqueid' => $uniqueid,
                'tag' => 1,
                'page' => $page,
                'pagesize' => $limit
            ], 'game', 'getcart2');
            if (!empty($res['data'])) {
                foreach ($res['data'] as &$item) {
                    $item['changemoney'] = $item['changemoney'] / 1000;
                    $item['totalbet'] = $item['totalbet'] / 1000;
                }
                unset($item);
            }

            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);

        }

        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $this->assign('roomid', $roomId);
        return $this->fetch();
    }

    public function detailZjh()
    {
        if ($this->request->isAjax()) {
            $uniqueid = input('uniqueid') ? input('uniqueid') : '';
            $userid = input('userid ') ? input('userid ') : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $res = Api::getInstance()->sendRequest([
                'uniqueid' => $uniqueid,
                'roomid' => $roomId,
                'userid' => $userid,
                'page' => $page,
                'pagesize' => $limit
            ], 'game', 'getcart');
            if (!empty($res['data'])) {
                foreach ($res['data'] as &$item) {
                    $item['card'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'],
                        ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $item['card']);
                    $item['card'] = explode(",", $item['card']);
                    $item['coinbefore'] = $item['coinbefore'] / 1000;
                    $item['changemoney'] = $item['changemoney'] / 1000;
                    $item['coinafter'] = $item['coinafter'] / 1000;
                }
                unset($item);
            }
            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);
        }
        $uniqueid = input('uniqueid') ? input('uniqueid') : '';
        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $this->assign('uniqueid', $uniqueid);
        $this->assign('roomId', $roomId);


        return $this->fetch();
    }

    //查看玩家详情
    public function detailZjh2()
    {
        if ($this->request->isAjax()) {
            $uniqueid = input('uniqueid') ? input('uniqueid') : '';
            $userid = input('userid ') ? input('userid ') : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $res = Api::getInstance()->sendRequest([
                'uniqueid' => $uniqueid,
                'roomid' => $roomId,
                'userid' => $userid,
                'page' => $page,
                'pagesize' => $limit
            ], 'game', 'getcart');
            if (!empty($res['data'])) {
                foreach ($res['data'] as &$item) {
                    $item['card'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'],
                        ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $item['card']);
                    $item['card'] = explode(",", $item['card']);
                    $item['coinbefore'] = $item['coinbefore'] / 1000;
                    $item['changemoney'] = $item['changemoney'] / 1000;
                    $item['coinafter'] = $item['coinafter'] / 1000;
                }
                unset($item);
            }
            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);
        }
        $uniqueid = input('uniqueid') ? input('uniqueid') : '';
        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $this->assign('uniqueid', $uniqueid);
        $this->assign('roomId', $roomId);


        return $this->fetch();
    }

    public function detailMpqz()
    {
        if ($this->request->isAjax()) {
            $uniqueid = input('uniqueid') ? input('uniqueid') : '';
            $userid = input('userid ') ? input('userid ') : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $res = Api::getInstance()->sendRequest([
                'uniqueid' => $uniqueid,
                'roomid' => $roomId,
                'userid' => $userid,
                'page' => $page,
                'pagesize' => $limit
            ], 'game', 'getcart');
            if (!empty($res['data'])) {
                foreach ($res['data'] as &$item) {
                    $item['card'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'],
                        ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $item['card']);
                    $item['card'] = explode(",", $item['card']);
                    $item['coinbefore'] = $item['coinbefore'] / 1000;
                    $item['changemoney'] = $item['changemoney'] / 1000;
                    $item['coinafter'] = $item['coinafter'] / 1000;
                }
                unset($item);
            }
            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);
        }
        $uniqueid = input('uniqueid') ? input('uniqueid') : '';
        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $this->assign('uniqueid', $uniqueid);
        $this->assign('roomId', $roomId);


        return $this->fetch();
    }

    public function detailMpqz2()
    {
        if ($this->request->isAjax()) {
            $uniqueid = input('uniqueid') ? input('uniqueid') : '';
            $userid = input('userid ') ? input('userid ') : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $res = Api::getInstance()->sendRequest([
                'uniqueid' => $uniqueid,
                'roomid' => $roomId,
                'userid' => $userid,
                'page' => $page,
                'pagesize' => $limit
            ], 'game', 'getcart');
            if (!empty($res['data'])) {
                foreach ($res['data'] as &$item) {
                    $item['card'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'],
                        ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $item['card']);
                    $item['card'] = explode(",", $item['card']);
                    $item['coinbefore'] = $item['coinbefore'] / 1000;
                    $item['changemoney'] = $item['changemoney'] / 1000;
                    $item['coinafter'] = $item['coinafter'] / 1000;
                }
                unset($item);
            }
            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);
        }
        $uniqueid = input('uniqueid') ? input('uniqueid') : '';
        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $this->assign('uniqueid', $uniqueid);
        $this->assign('roomId', $roomId);


        return $this->fetch();
    }

    public function detailOther()
    {
        if ($this->request->isAjax()) {
            $uniqueid = input('uniqueid') ? input('uniqueid') : '';
            $userid = input('userid ') ? input('userid ') : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $res = Api::getInstance()->sendRequest([
                'uniqueid' => $uniqueid,
                'roomid' => $roomId,
                'userid' => $userid,
                'page' => $page,
                'pagesize' => $limit
            ], 'game', 'getcart');
            if (!empty($res['data'])) {
                foreach ($res['data'] as &$item) {
                    $item['card'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'],
                        ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $item['card']);
                    $item['card'] = explode(",", $item['card']);
                    $item['coinbefore'] = $item['coinbefore'] / 1000;
                    $item['changemoney'] = $item['changemoney'] / 1000;
                    $item['coinafter'] = $item['coinafter'] / 1000;
                }
                unset($item);
            }
            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);
        }
        $uniqueid = input('uniqueid') ? input('uniqueid') : '';
        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $this->assign('uniqueid', $uniqueid);
        $this->assign('roomId', $roomId);


        return $this->fetch();
    }

    public function detailHlsb()
    {
        if ($this->request->isAjax()) {
            $uniqueid = input('uniqueid') ? input('uniqueid') : '';
            $userid = input('userid ') ? input('userid ') : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $res = Api::getInstance()->sendRequest([
                'uniqueid' => $uniqueid,
                'roomid' => $roomId,
                'userid' => $userid,
                'tag' => 1,
                'page' => $page,
                'pagesize' => $limit
            ], 'game', 'getcart2');
            if (!empty($res['data'])) {
                foreach ($res['data'] as &$item) {
//                    $item['card'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'], ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $item['card']);

                    $item['card'] = explode(" ", $item['card']);
                    $item['changemoney'] = $item['changemoney'] / 1000;
                    $item['totalbet'] = $item['totalbet'] / 1000;
                }
                unset($item);
            }
            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);
        }
        $uniqueid = input('uniqueid') ? input('uniqueid') : '';
        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $this->assign('uniqueid', $uniqueid);
        $this->assign('roomId', $roomId);


        return $this->fetch();
    }

    public function detailHlsb2()
    {
        if ($this->request->isAjax()) {
            $uniqueid = input('uniqueid') ? input('uniqueid') : '';
            $userid = input('userid ') ? input('userid ') : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $res = Api::getInstance()->sendRequest([
                'uniqueid' => $uniqueid,
                'roomid' => $roomId,
                'userid' => $userid,
                'tag' => 1,
                'page' => $page,
                'pagesize' => $limit
            ], 'game', 'getcart2');
            if (!empty($res['data'])) {
                foreach ($res['data'] as &$item) {
//                    $item['card'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'], ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $item['card']);

                    $item['card'] = explode(" ", $item['card']);
                    $item['changemoney'] = $item['changemoney'] / 1000;
                    $item['totalbet'] = $item['totalbet'] / 1000;
                }
                unset($item);
            }
            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);
        }
        $uniqueid = input('uniqueid') ? input('uniqueid') : '';
        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $this->assign('uniqueid', $uniqueid);
        $this->assign('roomId', $roomId);


        return $this->fetch();
    }

    public function detailDzpk()
    {
        if ($this->request->isAjax()) {
            $uniqueid = input('uniqueid') ? input('uniqueid') : '';
            $userid = input('userid ') ? input('userid ') : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $res = Api::getInstance()->sendRequest([
                'uniqueid' => $uniqueid,
                'roomid' => $roomId,
                'userid' => $userid,
                'page' => $page,
                'pagesize' => $limit
            ], 'game', 'getcart');
            if (!empty($res['data'])) {
                foreach ($res['data'] as &$item) {
                    $item['card'] = str_replace(['黑', '梅', '方', '红', '大王4', '小王'],
                        ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $item['card']);
                    $item['card'] = explode(",", $item['card']);
                    $item['tablecard'] = str_replace(['黑', '梅', '方', '红', '大王4', '小王'],
                        ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $item['tablecard']);
                    $item['tablecard'] = explode(",", $item['tablecard']);
                    $item['coinbefore'] = $item['coinbefore'] / 1000;
                    $item['changemoney'] = $item['changemoney'] / 1000;
                    $item['coinafter'] = $item['coinafter'] / 1000;
                }
                unset($item);
            }
            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);
        }
        $uniqueid = input('uniqueid') ? input('uniqueid') : '';
        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $this->assign('uniqueid', $uniqueid);
        $this->assign('roomId', $roomId);


        return $this->fetch();
    }

    public function detailDzpk2()
    {
        if ($this->request->isAjax()) {
            $uniqueid = input('uniqueid') ? input('uniqueid') : '';
            $userid = input('userid ') ? input('userid ') : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $res = Api::getInstance()->sendRequest([
                'uniqueid' => $uniqueid,
                'roomid' => $roomId,
                'userid' => $userid,
                'page' => $page,
                'pagesize' => $limit
            ], 'game', 'getcart');
            if (!empty($res['data'])) {
                foreach ($res['data'] as &$item) {
                    $item['card'] = str_replace(['黑', '梅', '方', '红', '大王4', '小王'],
                        ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $item['card']);
                    $item['card'] = explode(",", $item['card']);
                    $item['tablecard'] = str_replace(['黑', '梅', '方', '红', '大王4', '小王'],
                        ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $item['tablecard']);
                    $item['tablecard'] = explode(",", $item['tablecard']);
                    $item['coinbefore'] = $item['coinbefore'] / 1000;
                    $item['changemoney'] = $item['changemoney'] / 1000;
                    $item['coinafter'] = $item['coinafter'] / 1000;
                }
                unset($item);
            }
            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);
        }
        $uniqueid = input('uniqueid') ? input('uniqueid') : '';
        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $this->assign('uniqueid', $uniqueid);
        $this->assign('roomId', $roomId);


        return $this->fetch();
    }

    public function detailBrnn2()
    {
        if ($this->request->isAjax()) {
            $uniqueid = input('uniqueid') ? input('uniqueid') : '';
            $userid = input('userid ') ? input('userid ') : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $res = Api::getInstance()->sendRequest([
                'uniqueid' => $uniqueid,
                'roomid' => $roomId,
                'userid' => $userid,
                'tag' => 1,
                'page' => $page,
                'pagesize' => $limit
            ], 'game', 'getcart2');
            if (!empty($res['data'])) {
                foreach ($res['data'] as &$item) {
                    $item['card'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'],
                        ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $item['card']);
                    $item['card'] = explode(" ", $item['card']);
                    $item['changemoney'] = $item['changemoney'] / 1000;
                    $item['totalbet'] = $item['totalbet'] / 1000;
                }
                unset($item);
            }
            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);
        }
        $uniqueid = input('uniqueid') ? input('uniqueid') : '';
        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $this->assign('uniqueid', $uniqueid);
        $this->assign('roomId', $roomId);


        return $this->fetch();
    }

    public function detailBrnn()
    {
        if ($this->request->isAjax()) {
            $uniqueid = input('uniqueid') ? input('uniqueid') : '';
            $userid = input('userid ') ? input('userid ') : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $res = Api::getInstance()->sendRequest([
                'uniqueid' => $uniqueid,
                'roomid' => $roomId,
                'userid' => $userid,
                'tag' => 1,
                'page' => $page,
                'pagesize' => $limit
            ], 'game', 'getcart2');
            if (!empty($res['data'])) {
                foreach ($res['data'] as &$item) {
                    $item['card'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'],
                        ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $item['card']);
                    $item['card'] = explode(" ", $item['card']);
                    $item['changemoney'] = $item['changemoney'] / 1000;
                    $item['totalbet'] = $item['totalbet'] / 1000;
                }
                unset($item);
            }
            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);
        }
        $uniqueid = input('uniqueid') ? input('uniqueid') : '';
        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $this->assign('uniqueid', $uniqueid);
        $this->assign('roomId', $roomId);


        return $this->fetch();
    }

    public function detailBjl()
    {
        if ($this->request->isAjax()) {
            $uniqueid = input('uniqueid') ? input('uniqueid') : '';
            $userid = input('userid ') ? input('userid ') : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $res = Api::getInstance()->sendRequest([
                'uniqueid' => $uniqueid,
                'roomid' => $roomId,
                'userid' => $userid,
                'tag' => 1,
                'page' => $page,
                'pagesize' => $limit
            ], 'game', 'getcart2');
            if (!empty($res['data'])) {
                foreach ($res['data'] as &$item) {
                    $item['card'] = str_replace(['banker', 'playercouple', 'player', 'equal', 'bankercouple'],
                        ['庄', '闲对', '闲 ', '和', '庄对'], $item['card']);

//                    $item['card'] =explode(" ", $item['card']);
                    $item['changemoney'] = $item['changemoney'] / 1000;
                    $item['totalbet'] = $item['totalbet'] / 1000;
                }
                unset($item);
            }
            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);
        }
        $uniqueid = input('uniqueid') ? input('uniqueid') : '';
        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $this->assign('uniqueid', $uniqueid);
        $this->assign('roomId', $roomId);


        return $this->fetch();
    }

    public function detailBjl2()
    {
        if ($this->request->isAjax()) {
            $uniqueid = input('uniqueid') ? input('uniqueid') : '';
            $userid = input('userid ') ? input('userid ') : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $res = Api::getInstance()->sendRequest([
                'uniqueid' => $uniqueid,
                'roomid' => $roomId,
                'userid' => $userid,
                'tag' => 1,
                'page' => $page,
                'pagesize' => $limit
            ], 'game', 'getcart2');
            if (!empty($res['data'])) {
                foreach ($res['data'] as &$item) {
                    $item['card'] = str_replace(['banker', 'playercouple', 'player', 'equal', 'bankercouple'],
                        ['庄', '闲对', '闲 ', '和', '庄对'], $item['card']);

//                    $item['card'] =explode(" ", $item['card']);
                    $item['changemoney'] = $item['changemoney'] / 1000;
                    $item['totalbet'] = $item['totalbet'] / 1000;
                }
                unset($item);
            }
            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);
        }
        $uniqueid = input('uniqueid') ? input('uniqueid') : '';
        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $this->assign('uniqueid', $uniqueid);
        $this->assign('roomId', $roomId);


        return $this->fetch();
    }

    public function detailLonghudou()
    {
        if ($this->request->isAjax()) {
            $uniqueid = input('uniqueid') ? input('uniqueid') : '';
            $userid = input('userid ') ? input('userid ') : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $res = Api::getInstance()->sendRequest([
                'uniqueid' => $uniqueid,
                'roomid' => $roomId,
                'userid' => $userid,
                'tag' => 1,
                'page' => $page,
                'pagesize' => $limit
            ], 'game', 'getcart2');
            if (!empty($res['data'])) {
                foreach ($res['data'] as &$item) {

                    $item['changemoney'] = $item['changemoney'] / 1000;
                    $item['totalbet'] = $item['totalbet'] / 1000;
                }
                unset($item);
            }
            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);
        }
        $uniqueid = input('uniqueid') ? input('uniqueid') : '';
        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $this->assign('uniqueid', $uniqueid);
        $this->assign('roomId', $roomId);


        return $this->fetch();
    }

    public function detailLonghudou2()
    {
        if ($this->request->isAjax()) {
            $uniqueid = input('uniqueid') ? input('uniqueid') : '';
            $userid = input('userid ') ? input('userid ') : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $date = input('addtime', '');


            $res = Api::getInstance()->sendRequest([
                'uniqueid' => $uniqueid,
                'roomid' => $roomId,
                'userid' => $userid,
                'tag' => 1,
                'page' => $page,
                'pagesize' => $limit
            ], 'game', 'getcart2');
            if (!empty($res['data'])) {
                foreach ($res['data'] as &$item) {

                    $item['changemoney'] = $item['changemoney'] / 1000;
                    $item['totalbet'] = $item['totalbet'] / 1000;
                }
                unset($item);
            }
            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);
        }

        $addtime = input('addtime', '');
        $this->assign('addtime', $addtime);
        $uniqueid = input('uniqueid') ? input('uniqueid') : '';
        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $this->assign('uniqueid', $uniqueid);
        $this->assign('roomId', $roomId);


        return $this->fetch();
    }

    public function detailBcbm()
    {
        if ($this->request->isAjax()) {
            $uniqueid = input('uniqueid') ? input('uniqueid') : '';
            $userid = input('userid ') ? input('userid ') : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $res = Api::getInstance()->sendRequest([
                'uniqueid' => $uniqueid,
                'roomid' => $roomId,
                'userid' => $userid,
                'tag' => 1,
                'page' => $page,
                'pagesize' => $limit
            ], 'game', 'getcart2');
            if (!empty($res['data'])) {
                foreach ($res['data'] as &$item) {

                    $item['changemoney'] = $item['changemoney'] / 1000;
                    $item['totalbet'] = $item['totalbet'] / 1000;
                }
                unset($item);
            }
            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);
        }
        $uniqueid = input('uniqueid') ? input('uniqueid') : '';
        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $this->assign('uniqueid', $uniqueid);
        $this->assign('roomId', $roomId);


        return $this->fetch();
    }

    public function detailBcbm2()
    {
        $uniqueid = input('uniqueid') ? input('uniqueid') : '';
        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $addtime = input('addtime', '');
        $this->assign('addtime', $addtime);
        $this->assign('uniqueid', $uniqueid);
        $this->assign('roomId', $roomId);


        return $this->fetch();
    }

    public function detail_fqzs()
    {
        $uniqueid = input('uniqueid') ? input('uniqueid') : '';
        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $addtime = input('addtime', '');
        $this->assign('addtime', $addtime);
        $this->assign('uniqueid', $uniqueid);
        $this->assign('roomId', $roomId);


        return $this->fetch();
    }

    //所有同场玩家情况
    public function coplayer()
    {
        if ($this->request->isAjax()) {

            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
//            $roomId = $this->request->request('roomid');
//            var_dump($roleId );die;
            $res = Api::getInstance()->sendRequest([
                'userid' => $roleId,
                'roomid' => $roomId,
                'page' => $page,
                'pagesize' => $limit
            ], 'game', 'getcart');
            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);

        }

        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $this->assign('roomid', $roomId);
        return $this->fetch();
    }

    /**
     * Notes: 游戏日志（单独菜单）
     * @return mixed
     */
    public function agentgamelog()
    {

        $agentid = intval(input('agentid')) ? intval(input('agentid')) : 0;
        if ($this->request->isAjax()) {

            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $roomid = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $strartdate = input('strartdate') ? input('strartdate') : '';
            $enddate = input('enddate') ? input('enddate') : '';
            $winlost = intval(input('winlost')) >= 0 ? intval(input('winlost')) : -1;
            $mobile = input('mobile') ? input('mobile') : '';
            $postdata = [
                'roleid' => $roleId,
                'agentid' => $agentid,
                'roomid' => $roomid,
                'strartdate' => $strartdate,
                'enddate' => $enddate,
                'accountname' => $mobile,
                'page' => $page,
                'winlost' => $winlost,
                'pagesize' => $limit
            ];
            $res = Api::getInstance()->sendRequest($postdata, 'player', 'game');

            if (isset($res['data']['list'])) {
                foreach ($res['data']['list'] as &$v) {
                    $v['premoney'] = sprintf('%.2f', $v['lastmoney'] + $v['tax'] - $v['money']);//$v['tax']
                    $v['awardmoney'] = sprintf('%.2f', $v['gameroundrunning'] + $v['money']);
                    $v['gameroundrunning'] = sprintf('%.2f', $v['gameroundrunning']);
                    $v['money'] = sprintf('%.2f', $v['money']);
                    $v['freegame'] = '否';
                    if (floatval($v['gameroundrunning']) == 0) {
                        $v['freegame'] = '是';
                    }
                    $v['lastmoney'] = sprintf('%.2f', $v['lastmoney']);//+$v['tax']
                    $v['roomname'] = str_replace('蓝光1场', '大蓝鲸', $v['roomname']);
                }
                unset($v);
            }
            $sumdata = [
                'win' => isset($res['data']['win']) ? $res['data']['win'] : 0,
                'sum' => isset($res['data']['sum']) ? $res['data']['sum'] : 0,
                'lose' => isset($res['data']['lose']) ? $res['data']['lose'] : 0,
                'escape' => isset($res['data']['escape']) ? $res['data']['escape'] : 0,
                'totaltax' => isset($res['data']['totaltax']) ? $res['data']['totaltax'] : 0,
            ];

            return $this->apiReturn($res['code'], isset($res['data']['list']) ? $res['data']['list'] : [],
                $res['message'], $res['total'], ['alltotal' => $sumdata]);
        }
        $selectData = $this->getRoomList();

        $agentname = input('accountname');
        $this->assign('agentname', $agentname);
        $this->assign('selectData', $selectData);
        $this->assign('agentid', $agentid);
        return $this->fetch();
    }


    //获取vip配置
    public function VipLevelList()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 15;
            $where = [];
            $viplevel = new Viplevel();
            $list = $viplevel->getList($where, $page, $limit, '*', 'VipLevel asc ');
            foreach ($list as $item => &$v) {
                $v['NeedPoint'] = FormatMoney($v['NeedPoint']);
                $v['UpLevelAward'] = FormatMoney($v['UpLevelAward']);
                $v['MonthAward'] = FormatMoney($v['MonthAward']);
                $v['WeekAward'] = FormatMoney($v['WeekAward']);
                $v['DayAward'] = FormatMoney($v['MonAward']) . ',' . FormatMoney($v['TuesAward']) .
                    ',' . FormatMoney($v['WedAward']) . ',' . FormatMoney($v['ThurAward']) . ','
                    . FormatMoney($v['FriAward']) . ',' . FormatMoney($v['SatAward']) . ',' . FormatMoney($v['SunAward']);

                $v['LimitUp'] = FormatMoney($v['LimitUp']);
                $v['LimitDown'] = FormatMoney($v['LimitDown']);
            }
            unset($v);
            $count = $viplevel->getCount($where);
            return $this->apiReturn(0, $list, 'success', $count);
        }
        return $this->fetch();
    }


    //竖版VIP配置
    public function VertVipLevelList()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 15;
            $where = [];
            $viplevel = new Viplevel();
            $list = $viplevel->getList($where, $page, $limit, '*', 'VipLevel asc ');
            foreach ($list as $item => &$v) {
                $v['NeedPoint'] = FormatMoney($v['NeedPoint']);
                $v['WithdrawSingleLimit'] = FormatMoney($v['WithdrawSingleLimit']);
                $v['DayWithdrawLimit'] = FormatMoney($v['DayWithdrawLimit']);

                $v['ServiceFeeRate'] = bcdiv($v['ServiceFeeRate'], 10, 2);
                $v['ChargeExtraAwardRate'] = bcdiv($v['ChargeExtraAwardRate'], 10, 2);

                $v['UpLevelAward'] = FormatMoney($v['UpLevelAward']);
                $v['MonthAward'] = FormatMoney($v['MonthAward']);
                $v['WeekAward'] = FormatMoney($v['WeekAward']);
                $v['DayAward'] = FormatMoney($v['MonAward']) . ',' . FormatMoney($v['TuesAward']) .
                    ',' . FormatMoney($v['WedAward']) . ',' . FormatMoney($v['ThurAward']) . ','
                    . FormatMoney($v['FriAward']) . ',' . FormatMoney($v['SatAward']) . ',' . FormatMoney($v['SunAward']);

                $v['OrignalRunningReturnRate'] = bcdiv($v['OrignalRunningReturnRate'], 100, 3);
                $v['JacketpotRunningReturnRate'] = bcdiv($v['JacketpotRunningReturnRate'], 100, 3);
                $v['SlotRunningReturnRate'] = bcdiv($v['SlotRunningReturnRate'], 100, 3);
                $v['LiveRunningReturnRate'] = bcdiv($v['LiveRunningReturnRate'], 100, 3);
                $v['SportRunningReturnRate'] = bcdiv($v['SportRunningReturnRate'], 100, 3);

                if(empty($v['ValidInviteCounts']))
                {
                    $v['ValidInviteCounts'] =0;
                }

                $running_return = [
                    $v['OrignalRunningReturnRate'],
                    $v['JacketpotRunningReturnRate'],
                    $v['SlotRunningReturnRate'],
                    $v['LiveRunningReturnRate'],
                    $v['SportRunningReturnRate'],
                ];
                $v['RunningReturnRate'] = substr(implode($running_return, ','), 0, -1);
                $v['LimitUp'] = FormatMoney($v['LimitUp']);
                $v['LimitDown'] = FormatMoney($v['LimitDown']);
            }
            unset($v);
            $count = $viplevel->getCount($where);
            return $this->apiReturn(0, $list, 'success', $count);
        }
        return $this->fetch();
    }

    //修改竖屏版本
    //{
    //    "VipLevel": "0",
    //    "NeedPoint": "3.00",
    //    "DayMaxWithdrawTimes": "1",
    //    "WithdrawSingleLimit": "5000.00",
    //    "DayWithdrawLimit": "5000.00",
    //    "ChargeExtraAwardRate": "0.00",
    //    "DayAward": "0.10,0.10,0.10,0.10,0.10,0.10,0.10",
    //    "RunningReturnRate": "0.20,0.20,0.20,0.00,0.0",
    //    "WeekAward": "0",
    //    "MonthAward": "0",
    //    "UpLevelAward": "0"
    //}
    public function modifyVertVipLevel()
    {
        $level_id = input('VipLevel');
        $viplevel = new Viplevel();
        if ($this->request->isAjax()) {
            try {
                $NeedPoint = input('NeedPoint');
                $DayAward = input('DayAward');
                $WeekAward = input('WeekAward');
                $MonthAward = input('MonthAward');
                $UpLevelAward = input('UpLevelAward');

                $DayMaxWithdrawTimes = input('DayMaxWithdrawTimes', 0);
                $WithdrawSingleLimit = input('WithdrawSingleLimit', 0);
                $DayWithdrawLimit = input('DayWithdrawLimit', 0);

                $ChargeExtraAwardRate = input('ChargeExtraAwardRate', 0);
                $RunningReturnRate = input('RunningReturnRate', '');
                $ServiceFeeRate = input('ServiceFeeRate', 0);
                $SevenDaysCharge = input('SevenDaysCharge', 0);
                $NeedCharge = input('NeedCharge', 0);
                $WeekLossRate = input('WeekLossRate', 0);


                $savedata = [
                    'NeedPoint' => intval($NeedPoint) * bl,
                    'WeekAward' => intval($WeekAward) * bl,
                    'MonthAward' => intval($MonthAward) * bl,
                    'UpLevelAward' => intval($UpLevelAward) * bl,
                    'DayMaxWithdrawTimes' => $DayMaxWithdrawTimes,
                    'WithdrawSingleLimit' => $WithdrawSingleLimit * bl,
                    'DayWithdrawLimit' => $DayWithdrawLimit * bl,
                    'ChargeExtraAwardRate' => $ChargeExtraAwardRate * 10,
                    'ServiceFeeRate' => $ServiceFeeRate * 10,
                    'SevenDaysCharge' => $SevenDaysCharge,
                    'NeedCharge' => $NeedCharge,
                    'WeekLossRate' => $WeekLossRate

                ];

                $weekday = explode(',', $DayAward);
                $savedata['MonAward'] = intval(floatval($weekday[0]) * bl);
                $savedata['TuesAward'] = intval(floatval($weekday[1]) * bl);
                $savedata['WedAward'] = intval(floatval($weekday[2]) * bl);
                $savedata['ThurAward'] = intval(floatval($weekday[3]) * bl);
                $savedata['FriAward'] = intval(floatval($weekday[4]) * bl);
                $savedata['SatAward'] = intval(floatval($weekday[5]) * bl);
                $savedata['SunAward'] = intval(floatval($weekday[6]) * bl);


                $RunningRate = explode(',', $RunningReturnRate);
                $savedata['OrignalRunningReturnRate'] = $RunningRate[0] * 100;
                $savedata['JacketpotRunningReturnRate'] = $RunningRate[1] * 100;
                $savedata['SlotRunningReturnRate'] = $RunningRate[2] * 100;
                $savedata['LiveRunningReturnRate'] = $RunningRate[3] * 100;
                $savedata['SportRunningReturnRate'] = $RunningRate[4] * 100;

                $result = $viplevel->updateByWhere(['VipLevel' => $level_id], $savedata);
                if ($result)
                    return $this->apiReturn(0, '', '修改成功');
                else
                    return $this->apiReturn(100, '', '修改失败');
            } catch (Exception $ex) {
                return $this->apiReturn(100, '', '修改失败，异常信息：' . $ex->getMessage());
            }
        }
        $level = $viplevel->getDataRow(['VipLevel' => $level_id], '*');
        if (!empty($level)) {
            $level['NeedPoint'] = FormatMoneyint($level['NeedPoint']);
            $level['WeekAward'] = FormatMoneyint($level['WeekAward']);
            $level['MonthAward'] = FormatMoneyint($level['MonthAward']);

            $level['WithdrawSingleLimit'] = FormatMoney($level['WithdrawSingleLimit']);
            $level['DayWithdrawLimit'] = FormatMoney($level['DayWithdrawLimit']);

            $level['ServiceFeeRate'] = bcdiv($level['ServiceFeeRate'], 10, 2);
            $level['ChargeExtraAwardRate'] = bcdiv($level['ChargeExtraAwardRate'], 10, 2);


            $level['UpLevelAward'] = FormatMoneyint($level['UpLevelAward']);

            $level['DayAward'] = FormatMoney($level['MonAward']) . ',' . FormatMoney($level['TuesAward']) . ',';
            $level['DayAward'] .= FormatMoney($level['WedAward']) . ',' . FormatMoney($level['ThurAward']) . ',';
            $level['DayAward'] .= FormatMoney($level['FriAward']) . ',' . FormatMoney($level['SatAward']) . ',';
            $level['DayAward'] .= FormatMoney($level['SunAward']);


            $level['OrignalRunningReturnRate'] = bcdiv($level['OrignalRunningReturnRate'], 100, 3);
            $level['JacketpotRunningReturnRate'] = bcdiv($level['JacketpotRunningReturnRate'], 100, 3);
            $level['SlotRunningReturnRate'] = bcdiv($level['SlotRunningReturnRate'], 100, 3);
            $level['LiveRunningReturnRate'] = bcdiv($level['LiveRunningReturnRate'], 100, 3);
            $level['SportRunningReturnRate'] = bcdiv($level['SportRunningReturnRate'], 100, 3);

            $running_return = [
                $level['OrignalRunningReturnRate'],
                $level['JacketpotRunningReturnRate'],
                $level['SlotRunningReturnRate'],
                $level['LiveRunningReturnRate'],
                $level['SportRunningReturnRate'],
            ];
            $level['RunningReturnRate'] = substr(implode($running_return, ','), 0, -1);
            $level['LimitUp'] = FormatMoney($level['LimitUp']);
            $level['LimitDown'] = FormatMoney($level['LimitDown']);

        }
        $this->assign('config', $level);
        return $this->fetch();
    }


    public function modifyVipLevel()
    {
        $level_id = input('VipLevel');
        $viplevel = new Viplevel();
        if ($this->request->isAjax()) {
            try {
                $NeedPoint = input('NeedPoint');
                $DayAward = input('DayAward');
                $WeekAward = input('WeekAward');
                $MonthAward = input('MonthAward');
                $UpLevelAward = input('UpLevelAward');
                $LimitUp = floatval(input('LimitUp')) ? floatval(input('LimitUp')) : 0;
                $LimitDown = floatval(input('LimitDown')) ? floatval(input('LimitDown')) : 0;

                if ($LimitUp <= $LimitDown) {
                    return $this->apiReturn(100, '', '随机值上限需大于下限值');
                }

                $savedata = [
                    'NeedPoint' => intval($NeedPoint) * 1000,
                    'WeekAward' => intval($WeekAward) * 1000,
                    'MonthAward' => intval($MonthAward) * 1000,
                    'UpLevelAward' => intval($UpLevelAward) * 1000,
                    'LimitUp' => intval($LimitUp * 1000),
                    'LimitDown' => intval($LimitDown * 1000),
                ];

                $weekday = explode(',', $DayAward);
                $savedata['MonAward'] = intval(floatval($weekday[0]) * 1000);
                $savedata['TuesAward'] = intval(floatval($weekday[1]) * 1000);
                $savedata['WedAward'] = intval(floatval($weekday[2]) * 1000);
                $savedata['ThurAward'] = intval(floatval($weekday[3]) * 1000);
                $savedata['FriAward'] = intval(floatval($weekday[4]) * 1000);
                $savedata['SatAward'] = intval(floatval($weekday[5]) * 1000);
                $savedata['SunAward'] = intval(floatval($weekday[6]) * 1000);

                $result = $viplevel->updateByWhere(['VipLevel' => $level_id], $savedata);
                if ($result)
                    return $this->apiReturn(0, '', '修改成功');
                else
                    return $this->apiReturn(100, '', '修改失败');
            } catch (Exception $ex) {
                return $this->apiReturn(100, '', '修改失败，异常信息：' . $ex->getMessage());
            }
        }
        $level = $viplevel->getDataRow(['VipLevel' => $level_id], '*');
        if (!empty($level)) {
            $level['NeedPoint'] = FormatMoneyint($level['NeedPoint']);
            $level['WeekAward'] = FormatMoneyint($level['WeekAward']);
            $level['MonthAward'] = FormatMoneyint($level['MonthAward']);
            $level['UpLevelAward'] = FormatMoneyint($level['UpLevelAward']);

            $level['DayAward'] = FormatMoney($level['MonAward']) . ',' . FormatMoney($level['TuesAward']) . ',';
            $level['DayAward'] .= FormatMoney($level['WedAward']) . ',' . FormatMoney($level['ThurAward']) . ',';
            $level['DayAward'] .= FormatMoney($level['FriAward']) . ',' . FormatMoney($level['SatAward']) . ',';
            $level['DayAward'] .= FormatMoney($level['SunAward']);

            $level['LimitUp'] = FormatMoney($level['LimitUp']);
            $level['LimitDown'] = FormatMoney($level['LimitDown']);


        }
        $this->assign('config', $level);
        return $this->fetch();
    }


    /*
        public function queryGold()
        {
            if ($this->request->isAjax()) {
                $roleid = intval(input('roleid')) ? intval(input('roleid')) : 0;
                $operatetype = intval(input('type')) ? intval(input('type')) : 0;

                $socket = new QuerySocket();
                if ($operatetype == 1) {
                    $out_data_array = $socket->DSQueryRoleBankInfo($roleid);
                    $lockmoney = $out_data_array['iTotalLockMoney'];
                    return $this->apiReturn(0, $lockmoney, 'success');
                } else if ($operatetype == 2) {
                    $out_data_array = $socket->DSQueryRoleBankInfo($roleid);
                    $lockmoney = $out_data_array['iTotalLockMoney'];
                    $out_data = $socket->unLockMoney($roleid, $lockmoney);
                    $result = $out_data['iResult'];
                    if (intval($result) === 0) {
                        return $this->apiReturn(0, $lockmoney, '玩家金币解冻成功');
                    } else {
                        return $this->apiReturn(100, $lockmoney, '玩家金币解冻失败');
                    }
                }
            }
        }*/


    public function getRoleWage()
    {
        $roleid = input('roleid', 0, 'intval');
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 15;
            if ($roleid > 0) {
                $UserWageRequire = new model\UserWageRequire();
                $list = $UserWageRequire->getList(['roleid' => $roleid], $page, $limit, '*', 'Id desc');
                foreach ($list as $k => &$v) {
                    $v['ChangeMoney'] = FormatMoney($v['ChangeMoney']);
                    $v['NeedWageRequire'] = FormatMoney($v['NeedWageRequire']);
                    $v['CurWageRequire'] = FormatMoney($v['CurWageRequire']);
                    $v['EnableWithdrawMoney'] = FormatMoney($v['EnableWithdrawMoney'] ?? 0);
                    $v['FreezonMoney'] = FormatMoney($v['FreezonMoney'] ?? 0);
                    $v['difference'] = bcsub($v['NeedWageRequire'], $v['CurWageRequire'], 2);
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
            ->where('GmType', '<>', 0)
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
        foreach ($data['data'] as $k=>&$v){
            $v['AccountName'] = substr_replace($v['AccountName'],'**',-2);
        }

        return $this->apiJson(['list' => $data['data'], 'count' => $data['total']]);
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
            'adminid' => session('userid'),
            'type' => 1,
            'opt_time' => date('Y-m-d H:i:s'),
            'comment' => $comment
        ]);
        if ($res) {
            GameLog::logData(__METHOD__, [$roleid, $type, $comment], 1, '操作成功');
            return $this->apiReturn(0, '', '操作成功');
        } else {
            GameLog::logData(__METHOD__, [$roleid, $type, $comment], 1, '操作失败');
            return $this->apiReturn(1, '', '操作失败');
        }
    }


    //获取打码
    public function getDm($roleid)
    {
        $data = $this->sendGameMessage('CMD_MD_QUERY_WAGED', [$roleid], "DC", 'QueryUserWaged');
        if (!isset($data['lCurWaged'])) {
            $data['lCurWaged'] = 0;
        }
        if ($data['lNeedWaged'] == $data['lCurWaged']) {
            $data['lCurWaged'] = 0;
        }
        $data['lCurWaged'] = FormatMoneyint($data['lCurWaged']);
        return $this->apiReturn(0, $data, 'success');
    }

    //设置打码
    public function setDm()
    {
        $roleid = $this->request->param('roleid');
        $dm = $this->request->param('dm');
        $type = $this->request->param('type');
        $dm = floatval($dm);
        if ($type == 2) {
            $auth_ids = $this->getAuthIds();
            if (!in_array(10003, $auth_ids)) {
                return $this->apiReturn(2, [], '没有权限');
            }
        } else {
            $auth_ids = $this->getAuthIds();
            if (!in_array(10004, $auth_ids)) {
                return $this->apiReturn(2, [], '没有权限');
            }
            //清除打码值
            //$send_dm = 0;
        }
        $send_dm = $dm * bl;
        $data = $this->sendGameMessage('CMD_MD_SET_WAGED', [$roleid, $type, $send_dm], "DC", 'returnComm');
        if ($data['iResult'] == 0) {
            if ($type == 2) {
                $comment = '新增任务：' . $dm;
            } else {
                $comment = '新增当前:' . $dm;
            }
            $db = new GameOCDB();
            $db->setTable('T_PlayerComment')->Insert([
                'roleid' => $roleid,
                'adminid' => session('userid'),
                'type' => 2,
                'opt_time' => date('Y-m-d H:i:s'),
                'comment' => $comment
            ]);

            GameLog::logData(__METHOD__, [$roleid, $dm, $type], 1, $comment);
            return $this->apiReturn(0, '', '操作成功');
        } else {
            GameLog::logData(__METHOD__, [$roleid, $dm, $type], 0, '操作失败');
            return $this->apiReturn(1, '', '操作失败');
        }
    }

    //设置打码
    public function setRateDm()
    {
        $accountId = $this->request->param('roleid/a');
        $dm = abs($this->request->param('dm'));
        $type = $this->request->param('type');
        $dm = intval($dm);
        $auth_ids = $this->getAuthIds();
        if (!in_array(10013, $auth_ids)) {
            // return $this->apiReturn(2, [], '没有权限');
        }
        if (empty($accountId)) {
            return $this->apiReturn(1, '', '请选择用户');
        }
        if ($dm < 0) {
            $dm = 0;
        }
        if ($dm > 100) {
            $dm = 100;
        }
        $success_num =0;
        $faild_num =0;
        if ($type == 0) {
            $comment = '编辑赢打码百分比：' . $dm;
        } else {
            $comment = '编辑输打码百分比:' . $dm;
        }
        $db = new GameOCDB();
        foreach ($accountId as $k => &$roleid) {
            $data = $this->sendGameMessage('CMD_MD_USER_WAGED_RATE', [$roleid, $type, $dm], "DC", 'returnComm');
            if ($data['iResult'] == 1) {
                $db->setTable('T_PlayerComment')->Insert([
                    'roleid' => $roleid,
                    'adminid' => session('userid'),
                    'type' => 4,
                    'opt_time' => date('Y-m-d H:i:s'),
                    'comment' => $comment
                ]);
                $success_num++;
            } else {
                $faild_num++;
            }
        }
        $str_msg ='处理成功'.$success_num.',失败：'.$faild_num;
        return $this->apiReturn(0, '', $str_msg);
    }

    /**
     * 批量打码百分比设置
     * @return mixed
     */
    public function multisetRateDm()
    {
        $accountId = $this->request->param('roleid/a');
        $dm = abs($this->request->param('dm'));
        $type = $this->request->param('type');
        $auth_ids = $this->getAuthIds();
        if (!in_array(10004, (array)$auth_ids)) {
            return $this->apiReturn(2, [], '没有权限');
        }
        if ($dm < 0) {
            $dm = 0;
        }
        if ($dm > 100) {
            $dm = 100;
        }
        $success_num =0;
        $faild_num =0;
        foreach ($accountId as $k => $v) {
            $data = $this->sendGameMessage('CMD_MD_USER_WAGED_RATE', [$v, $type, $dm], "DC", 'returnComm');
            if ($data['iResult'] == 1) {
                if ($type == 0) {
                    $comment = '编辑赢打码百分比：' . $dm;
                } else {
                    $comment = '编辑输打码百分比:' . $dm;
                }
                $db = new GameOCDB();
                $db->setTable('T_PlayerComment')->Insert([
                    'roleid' => $v,
                    'adminid' => session('userid'),
                    'type' => 4,
                    'opt_time' => date('Y-m-d H:i:s'),
                    'comment' => $comment
                ]);
            }
        }
        $str_msg ='处理成功'.$success_num.',失败：'.$faild_num;
        return $this->apiReturn(0, '', $str_msg);

    }
    /**
     * 批量结束控制(停止使用)
     * @return void
     */
    public function multiCancelControl()
    {
        $accountId = $this->request->param('roleid/a');
        foreach ($accountId as $k => $v) {
            $this->sendGameMessage('CMD_MD_USER_WAGED_RATE', [$v, 0, 0], "DC", 'returnComm');
            $this->sendGameMessage('CMD_MD_USER_WAGED_RATE', [$v, 1, 0], "DC", 'returnComm');
            $comment = '编辑输赢打码百分比：' . 0;
            $db = new GameOCDB();
            $db->setTable('T_PlayerComment')->Insert([
                'roleid' => $v,
                'adminid' => session('userid'),
                'type' => 4,
                'opt_time' => date('Y-m-d H:i:s'),
                'comment' => $comment
            ]);
        }
        GameLog::logData(__METHOD__, $this->request->request());
        return $this->apiReturn(0, [], '设置成功');
    }

    //取消打码
    public function cancelRateDm()
    {
        $accountId = $this->request->param('roleid/a');

        $auth_ids = $this->getAuthIds();
        if (!in_array(10013, $auth_ids)) {
            // return $this->apiReturn(2, [], '没有权限');
        }
        $db = new GameOCDB();
        foreach ($accountId as $k => &$roleid) {
            $this->sendGameMessage('CMD_MD_USER_WAGED_RATE', [$roleid, 1, 0], "DC", 'returnComm');
            $this->sendGameMessage('CMD_MD_USER_WAGED_RATE', [$roleid, 0, 0], "DC", 'returnComm');
            $db->setTable('T_PlayerComment')->Insert([
                'roleid' => $roleid,
                'adminid' => session('userid'),
                'type' => 4,
                'opt_time' => date('Y-m-d H:i:s'),
                'comment' => '取消打码百分比'
            ]);
        }
        // GameLog::logData(__METHOD__, [$roleid], 1, '取消打码百分比');
        return $this->apiReturn(0, '', '操作成功');
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
                $datarow = $db->getTableRow('T_OperatorSubAccount', 'OperatorId=' . $val['adminid'], 'OperatorName');
                if (!empty($datarow)) {
                    $val['admin_name'] = '渠道-' . $datarow['OperatorName'];
                }
            }
            if (empty($val['admin_name'])) {
                $rowdata = $db->getTableRow('T_ProxyChannelConfig', 'ProxyChannelId=' . $val['adminid'], 'AccountName');
                if (!empty($rowdata)) {
                    $val['admin_name'] = '业务员-' . $rowdata['AccountName'];
                }
            }
        }
        return $this->apiJson($data);
    }


    public function editPlayer()
    {
        $id = (int)$this->request->param('id');
        $quhao = $this->request->param('quhao') ?: '';
        $phone = $this->request->param('phone');
        $password = $this->request->param('password');

        $quhao = str_replace('+', '', $quhao);
        if (empty($quhao)) {
            return $this->apiReturn(1, '', '请输入区号');
        }
        if (empty($phone)) {
            return $this->apiReturn(1, '', '请输入手机号码');
        }
        if (empty($password)) {
            return $this->apiReturn(1, '', '请输入密码');
        }
        // $db = new \app\model\AccountDB();
        // $res = $db->getTableObject('T_Accounts')->where('AccountID',$id)->update([
        //     'Mobile'=>$quhao.$phone,
        //     'Password'=>md5($password),
        // ]);
        $data = $this->sendGameMessage('CMD_WD_ACCOUNT_BIND_PHONE', [$id, $quhao . $phone, md5($password)], "DC", 'returnComm');
        if ($data['iResult'] == 0) {
            return $this->apiReturn(0, '', '操作成功');
        } else {
            return $this->apiReturn(1, '', '操作失败');
        }
    }

    public function unbindPhone()
    {
        $id = (int)$this->request->param('id');
        $data = $this->sendGameMessage('CMD_WD_ACCOUNT_UNBIND_PHONE', [$id], "DC", 'returnComm');
        if ($data['iResult'] == 0) {
            return $this->apiReturn(0, '', '操作成功');
        } else {
            return $this->apiReturn(1, '', '操作失败');
        }
    }

    public function setUpperCode()
    {
        $roleid = $this->request->param('roleid');
        $invite_code = $this->request->param('invite_code');
        $data = $this->sendGameMessage('CMD_MD_SET_PROXY_INVITE', [$roleid, $invite_code], "DC", 'returnComm');
        if ($data['iResult'] == 0) {
            $comment = '设置上级ID：' . $invite_code;
            $db = new GameOCDB();
            $db->setTable('T_PlayerComment')->Insert([
                'roleid' => $roleid,
                'adminid' => session('userid'),
                'type' => 3,
                'opt_time' => date('Y-m-d H:i:s'),
                'comment' => $comment
            ]);
            return $this->apiReturn(0, '', '操作成功');
        } else {
            return $this->apiReturn(1, '', '操作成功');
        }
    }


    public function setControll()
    {
        $id = (int)$this->request->param('id');
        $kz = input('kz', -1);
        if ($kz == 96)
            $kz = 0;
        else
            $kz = 96;
        $socketid = new QuerySocket();
        $data = $socketid->SetRoleRight($id, 0, 0, $kz);
        if ($data['iResult'] == 0) {
            return $this->apiReturn(0, '', '操作成功');
        } else {
            return $this->apiReturn(1, '', '操作失败');
        }

    }


    ///token列表
    public function playertoken()
    {

        if ($this->request->isAjax()) {
            $page = input('page', 1, 'intval');
            $limit = input('limit', 15, 'intval');
            $devicetype = input('devicetype', -1);
            $roleid = input('roleid', 0);
            $accoutdb = new AccountDB();
            $where = [];
            if ($devicetype > -1) {
                $where['DeviceType'] = $devicetype;
            }
            if ($roleid > 0) {
                $where['AccountID'] = $roleid;
            }
            $result = $accoutdb->getTableList('T_DeviceToken', $where, $page, $limit, '*', 'id asc ');
            foreach ($result['list'] as $k => &$v) {
                $v['devicename'] = lang('未知');
                switch ($v['DeviceType']) {
                    case 0:
                        $v['devicename'] = 'H5';
                        break;
                    case 1:
                        $v['devicename'] = 'android';
                        break;
                    case 2:
                        $v['devicename'] = 'ios';
                        break;
                    case 3:
                        $v['devicename'] = 'wp';
                        break;
                }
            }
            unset($v);
            return $this->apiJson($result);
        }
        return $this->fetch();
    }


    public function playerTokenEdit()
    {
        $id = input('id');
        $db = new AccountDB();
        $datarow = $db->getTableRow('T_DeviceToken', ['id' => $id], 'DeviceType,DeviceToken');
        if ($datarow) {
            $token = trim($datarow['DeviceToken']);
            if (empty($token)) {
                return $this->failJSON('Token值为空!');
            }
        }
        $sign = md5('rummyjai3b5af0f0fe7c68ea06d4876d746e219e');
        $pushmsg = [
            'operator_id' => 'rummyjai',
            'device' => 1,
            'token' => $datarow['DeviceToken'],
            'title' => 'hello',
            'message' => 'success',
            'pushtype' => 'data',
            'sign' => $sign
        ];
        $url = 'http://18.140.253.72/api/index/push';
        $post_json = json_encode($pushmsg);
        $header = [
            'Content-Type: application/json;charset=utf-8',
        ];

        $resp = urlhttpRequest($url, $post_json, $header);
        $result = json_decode($resp, true);
        //save_log('pushmsg','input:'.$post_json.',output:'.$resp);
        if ($result) {
            if ($result['data'])
                return $this->successJSON([]);

        }
        return $this->failJSON('推送失败!');
    }

    public function batchtokenpush()
    {
        $accoutdb = new AccountDB();
        $result = $accoutdb->getTableList('T_DeviceToken', 'DeviceToken<>\'\'', 1, 100000, '');
        foreach ($result as $k => $v) {

        }
    }

    public function addPushMsg()
    {
        $id = input('id');
        if ($this->request->isAjax()) {
            $title = input('title', '');
            $content = input('content', '');

            if (empty($title) || empty($content)) {
                return $this->failJSON('标题内容为空!');
            }

            $db = new AccountDB();
            $datarow = $db->getTableRow('T_DeviceToken', ['id' => $id], 'DeviceType,DeviceToken');
            if ($datarow) {
                $token = trim($datarow['DeviceToken']);
                if (empty($token)) {
                    return $this->failJSON('Token值为空!');
                }
            }
            $sign = md5('rummyjai3b5af0f0fe7c68ea06d4876d746e219e');
            $pushmsg = [
                'operator_id' => 'rummyjai',
                'device' => 1,
                'token' => $datarow['DeviceToken'],
                'title' => $title,
                'message' => $content,
                'pushtype' => '',
                'sign' => $sign
            ];
            $url = 'http://18.140.253.72/api/index/push';
            $post_json = json_encode($pushmsg);
            $header = [
                'Content-Type: application/json;charset=utf-8',
            ];
            $resp = urlhttpRequest($url, $post_json, $header);
            $result = json_decode($resp, true);
            if ($result) {
                if ($result['data'])
                    return $this->successJSON([]);
            }
            return $this->failJSON('推送失败!');
        }
        $this->assign('id', $id);
        return $this->fetch();
    }

    public function deleteAccount()
    {
        //权限验证
        $auth_ids = $this->getAuthIds();
        if (!in_array(10007, $auth_ids)) {
            return $this->apiReturn(1, '', '没有权限');
        }
        $password = input('password');
        $user_controller = new \app\admin\controller\User();
        $pwd = $user_controller->rsacheck($password);
        if (!$pwd) {
            return $this->apiReturn(1, '', '密码错误');
        }
        $userModel = new userModel();
        $userInfo = $userModel->getRow(['id' => session('userid')]);
        if (md5($userInfo['salt'] . $pwd) !== $userInfo['password']) {
            return $this->apiReturn(1, '', '密码有误，请重新输入');
        }
        $id = (int)$this->request->param('id');
        $data = $this->sendGameMessage('CMD_MD_DELETE_ACCOUNT', [$id], "DC", 'returnComm');
        if ($data['iResult'] == 0) {
            return $this->apiReturn(0, '', '操作成功');
        } else {
            return $this->apiReturn(1, '', '操作失败');
        }
    }

    public function apitigergamelog()
    {

        if ($this->request->isAjax()) {
            $page = input('page', 1, 'intval');
            $limit = input('limit', 15, 'intval');

            $where = [];
            $gameoc = new GameOCDB();
            $result = $gameoc->getTableList('T_ApiPlayerRecord', $where, $page, $limit, '*', ['addDate' => 'desc']);
            return $this->apiJson($result);
        }
        return $this->fetch();
    }


    public function setVipRate()
    {
        $rate = input('rate');
        $roleid = input('RoleID');
        if ($this->request->isAjax()) {
            if (empty($roleid)) {
                return $this->failJSON('参数错误');
            }
            $iResult = $this->socket->SetRoleVipLevel($roleid, $rate);
            return $this->successJSON('');
        }
        $viplevel = [];
        for ($i = 0; $i <= 20; $i++) {
            array_push($viplevel, $i);
        }
        $this->assign('viplevel', $viplevel);
        $this->assign('rate', $rate);
        $this->assign('roleid', $roleid);
        return $this->fetch();
    }

    //玩家游戏日报
    public function gameDailyReport()
    {
        switch (input('Action')) {
            case 'list':
                $roleid = $this->request->param('roleid');
                $start = $this->request->param('start') ?: date('Y-m-d 00:00:00');
                $end = $this->request->param('end') ?: date('Y-m-d 23:59:59');
                $page = $this->request->param('page') ?: 1;
                $OperatorId = $this->request->param('OperatorId') ?: '';
                $limit = $this->request->param('limit') ?: 20;
                $orderby = input('orderfield');
                $ordertype = input('ordertype');
                $where = '';
                $where_total = '';
                if ($roleid != '') {
                    $where .= ' and a.RoleID=' . $roleid;
                    $where_total .= ' and RoleID=' . $roleid;
                }
                $where .= 'and a.AddTime>=\'' . $start . '\' and a.AddTime<=\'' . $end . '\'';
                $where_total .= " and AddTime>=''$start'' and AddTime<=''$end''";

                $db = new GameOCDB();
                $userProxyInfo = new UserProxyInfo();
                $ProxyChannelConfig = (new GameOCDB())->getTableObject('T_ProxyChannelConfig')->column('*', 'ProxyChannelId');
                $field = 'a.RoleId,b.ParentID ,ServerID,CONVERT(varchar(30),addtime,112) as AddTime,sum(tax) as Tax,sum(GameRoundRunning) as GameRoundRunning,sum(Money) as TotalWin,a.OperatorId';
                $join = 'left join [CD_UserDB].[dbo].[T_UserProxyInfo] as b on a.RoleID=b.RoleID';
                if ($OperatorId !== '') {
                    $where .= 'and a.OperatorId=\'' . $OperatorId . '\'';
                    $where_total .= ' and a.OperatorId=\'' . $OperatorId . '\'';
                }
                $order = 'AddTime desc';
                if ($orderby && $ordertype) {
                    $order = "$orderby $ordertype";
                }
                $group = ' ServerID,CONVERT(varchar(30),addtime,112),a.RoleID,b.ParentID,a.OperatorId';
                $startdate = date('Y-m-d', strtotime($start));
                $enddate = date('Y-m-d', strtotime($end));
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


    //积分转盘记录
    public function lotteryRecrod()
    {
        $action = $this->request->param('action');
        if ($action == 'list') {
            $limit = $this->request->param('limit') ?: 15;
            $RoleID = $this->request->param('roleid');
            $start_date = $this->request->param('start_date');
            $end_date = $this->request->param('end_date');
            $where = "1=1";

            if ($RoleID != '') {
                $where .= " and a.RoleID='" . $RoleID . "'";
            }

            if ($start_date != '') {
                $where .= " and a.AddTime>='" . $start_date . "'";
            }
            if ($end_date != '') {
                $where .= " and a.AddTime<='" . $end_date . "'";
            }
            $data = (new UserDB())->getTableObject('T_Lottery_Recrod(nolock)')->alias('a')
                ->where($where)
                ->field('a.*')
                ->order('AddTime desc')
                ->paginate($limit)
                ->toArray();

            foreach ($data['data'] as $key => &$val) {
                $val['LotteryAward'] = FormatMoney($val['LotteryAward']);
                $val['CostDiamond'] = FormatMoney($val['CostDiamond']);
            }
            $other = (new UserDB())->getTableObject('T_Lottery_Recrod(nolock)')->alias('a')
                ->where($where)
                ->field('sum(a.LotteryAward) as LotteryAward,sum(a.CostDiamond) as CostDiamond')
                ->find();
            $other['LotteryAward'] = FormatMoney($other['LotteryAward']);
            $other['CostDiamond'] = FormatMoney($other['CostDiamond']);
            return $this->apiReturn(0, $data['data'], 'success', $data['total'], $other);
        } else {
            return $this->fetch();
        }
    }

    public function DailyWageList()
    {

        $db = new UserDB();
        $result = $db->GetIndexData();
        //找下数据源
        if ($this->request->isAjax()) {
            return $this->apiJson($result);
        }
        return $this->fetch();

    }


    public function userDailyWage()
    {

        $date = $this->request->param('date', date('Y-m-d'));
        $action = $this->request->param('action');
        if ($action == 'list' || $action == 'output') {
            $limit = $this->request->param('limit') ?: 15;
            $RoleID = $this->request->param('roleid');
            $where = "1=1";
            if ($RoleID != '') {
                $where .= " and RoleID='" . $RoleID . "'";
            }
            if ($date != '') {
                $where .= " and AddDate='" . $date . "'";
            }
            if ($action == 'list') {
                $data = (new GameOCDB())->getTableObject('View_UserWageDaily')->alias('a')
                    ->where($where)
                    ->field('*')
                    ->order('AddDate desc')
                    ->paginate($limit)
                    ->toArray();
                foreach ($data['data'] as $key => &$val) {
                    $val['Water'] = FormatMoney($val['Water']);
                }
                $other = (new GameOCDB())->getTableObject('View_UserWageDaily')->alias('a')
                    ->where($where)
                    ->field('sum(Water) as TotalWater')
                    ->find();
                $other['TotalWater'] = FormatMoney($other['TotalWater']);
                return $this->apiReturn(0, $data['data'], 'success', $data['total'], $other);
            }
            if ($action == 'output') {
                $data = (new GameOCDB())->getTableObject('View_UserWageDaily')->alias('a')
                    ->where($where)
                    ->field('*')
                    ->order('AddDate desc')
                    ->select();
                if (empty($data)) {
                    $result = ["count" => 0, "code" => 1, 'msg' => lang("没有找到任何数据,换个姿势再试试?")];
                    return $this->apiJson($result);
                }
                $result = [];
                $result['list'] = $data;
                $result['count'] = 1;
                $outAll = input('outall', false);
                if ((int)input('exec', 0) == 0) {
                    if ($result['count'] >= 5000 && $outAll == false) {
                        $result = ["code" => 2, 'msg' => lang("数据超过5000行是否全部导出?<br>只能导出一部分数据.</br>请选择筛选条件,让数据少于5000行<br>当前数据一共有") . $result['count'] . lang("行")];
                    }
                    unset($result['list']);
                    return $this->apiJson($result);
                }
                //导出表格
                if ((int)input('exec', 0) == 1 && $outAll = true) {
                    $header_types = [
                        lang('玩家ID') => 'string',
                        lang('累计打码') => 'string',
                        lang('日期') => 'string',
                    ];
                    $filename = lang('玩家打码详情') . '-' . date('YmdHis');
                    $rows =& $result['list'];

                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {
                        $row['Water'] = FormatMoney($row['Water']);
                        $item = [
                            $row['RoleID'],
                            $row['Water'],
                            $row['adddate'],
                        ];
                        $writer->writeSheetRow('sheet1', $item, ['height' => 16, 'halign' => 'center',]);
                        unset($rows[$index]);
                    }
                    unset($row, $item);
                    $writer->writeToStdOut();
                    exit();
                }
            }

        } else {
            $this->assign('date', $date);
            return $this->fetch();
        }
    }

    //玩家日奖励统计
    public function playDayReward()
    {
        $action = $this->request->param('action');
        if ($action == 'list') {
            $limit = $this->request->param('limit') ?: 15;


            $RoleID = $this->request->param('RoleID');
            $start_date = $this->request->param('strartdate');
            $end_date = $this->request->param('enddate');


            $where = '1=1';
            if ($start_date != '') {
                $where .= ' and a.AddTime>=\'' . $start_date . '\'';
            }
            if ($end_date != '') {
                $where .= ' and a.AddTime<\'' . $end_date . '\'';
            }
            if ($RoleID != '') {
                $where .= ' and a.RoleID=' . $RoleID;
            }
            $order = "AddTime desc";
            $gameOCDB = new GameOCDB();
            $recharge_sql = "(SELECT AccountID,CONVERT(varchar(10),AddTime,120) as AddTime,sum(RealMoney) RealMoney from [CD_UserDB].[dbo].[T_UserTransactionChannel](NOLOCK) group by CONVERT(varchar(10),AddTime,120),AccountID) as d";

            $data = $gameOCDB->getTableObject('T_ProxyDailyBonus')->alias('a')
                //->join($recharge_sql,'d.AccountID=a.RoleID and d.AddTime=a.AddTime','LEFT')
                ->where($where)
                ->field('a.*')
                ->order($order)
                ->paginate($limit)
                ->toArray();
            foreach ($data['data'] as $key => &$val) {
                ConVerMoney($val['ReChargeAmount']);
                $val['UserFirstReChargeBonus'] = FormatMoney($val['UserFirstReChargeBonus'] ?? 0);
                $val['ReChargeBonus'] = FormatMoney($val['ReChargeBonus'] ?? 0);
                $val['DailyChargeBonus'] = FormatMoney($val['DailyChargeBonus'] ?? 0);
                $val['VipBonus'] = FormatMoney($val['VipUpBonus'] + $val['VipRechargeBonus'] + $val['VipDailySignBonus'] + $val['VipWeeklySignBonus'] + $val['VipMonthlySignBonus']);
                $val['UserBetBonus'] = FormatMoney($val['UserBetBonus'] ?? 0);
                $val['MailBonus'] = FormatMoney($val['MailWithWageBonus'] + $val['MailNoWageBonus']);
                $val['LotteryBonus'] = FormatMoney($val['LotteryBonus'] ?? 0);
                $val['Commission'] = FormatMoney($val['RunningBonus'] + $val['InviteBonus'] + $val['FirstChargeBonus']);
            }
            if (input('action') == 'list' && input('output') != 'exec') {
                $other = [];
                return $this->apiReturn(0, $data['data'], 'success', $data['total'], $other);
            }
            if (input('output') == 'exec') {
            }
        }
        return $this->fetch();
    }


    //佣金返利开关
    public function setProftSwitch(){
        $roleid = input('roleid');
        $userdb =new UserDB();
        $commiswitch = $userdb->getTableObject('T_UserProxyInfo')->where(['RoleID'=>$roleid])->value('ProxyCommiSwitch');
        $switchval = $commiswitch?0:1;
        $result = $userdb->getTableObject('T_UserProxyInfo')->where(['RoleID'=>$roleid])->update(['ProxyCommiSwitch'=>$switchval]);
        if ($result) {
            return $this->apiReturn(0, '', '操作成功');
        } else {
            return $this->apiReturn(1, '', '操作失败');
        }

    }

    //加密
    private function encry($str,$key='pggme'){
        $str = trim($str);
        return think_encrypt($str,$key);
        if (!$key) {
            return $str;
        }
        $data = openssl_encrypt($str, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        $data = base64_encode($data);
        return $data;
    }

    /**
     * 获取代理返佣奖励开关；1关，2开
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getAgentRebateSwitch()
    {
        $roleId = input('roleid');
        $userDB = new UserDB();
        $getUserAgentRebateSwitch = $userDB->getTableObject('T_Job_UserInfo')
            ->where('RoleID',$roleId)
            ->where('job_key',10008)
            ->find();
        if (isset($getUserAgentRebateSwitch) && $getUserAgentRebateSwitch->value == 2) {
            return $this->apiReturn(0, 2, '');
        } else {
            return $this->apiReturn(0, 1, '');
        }
    }

    /**
     * 设置代理返佣奖励开关；1关，2开
     * @return mixed
     */
    public function setAgentRebateSwitch()
    {
        $roleId = input('roleid');
        $status = input('status');
        $data = $this->sendGameMessage('CMD_MD_GM_UPDATE_USER_TYPE', [$roleId,10008,$status], "DC", 'returnComm');
        if ($data['iResult'] == 0) {
            return $this->apiReturn(0, '', '操作成功');
        } else {
            return $this->apiReturn(1, '', '操作失败');
        }
    }



    public function GmTransferSend($id)
    {

//        $gmid = input('ID');
        $lock_ley = 'lock_transfer_' . $id;

        $lockstatus = Redis::lock($lock_ley);
        if (!$lockstatus) {
            return $this->error('请勿重复提交!');
        }
        $db = new  GameOCDB('',true);
        $data = $db->TGMSendMoney()->GetRow("ID=" . $id);
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
        } else if ($data['OperateType'] == 3) {
            try {

                $res = $this->sendGameMessage('CMD_MD_GM_ADD_PROXY_COMMISSION', [$data['RoleId'], $data['Money'] * bl, 0]);
                $res = unpack('LiResult/', $res);
            } catch (Exception $exception) {
                return $this->error('连接服务器失败,请稍后重试!');
            }
            if ($res['iResult'] == 0) {
                $row = $db->TGMSendMoney()->UPData(["status" => 1, "UpdateTime" => date('Y-m-d H:i:s')], "ID=" . $data['ID']);
                if ($row > 0) return $this->success("审核成功");
            }

            return $this->error('审核失败');
        } else if ($data['OperateType'] == 4) {
            try {
                $res = $this->sendGameMessage('CMD_MD_GM_ADD_PROXY_COMMISSION', [$data['RoleId'], $data['Money'] * bl, 1]);
                $res = unpack('LiResult/', $res);
            } catch (Exception $exception) {
                return $this->error('连接服务器失败,请稍后重试!');
            }
            if ($res['iResult'] == 0) {
                $row = $db->TGMSendMoney()->UPData(["status" => 1, "UpdateTime" => date('Y-m-d H:i:s')], "ID=" . $data['ID']);
                if ($row > 0) return $this->success("审核成功");
            }
            return $this->error('审核失败');
        } else {
            return $this->error('不存在的上下分类型');
        }
    }


    public function GmTransferDeny($id)
    {
        $db = new  GameOCDB('',true);
        $row = $db->TGMSendMoney()->UPData(["status" => 2, "UpdateTime" => date('Y-m-d H:i:s')], "ID='$id'");
        if ($row > 0) return $this->success("成功");
        return $this->error('失败');
    }

    public function getGroupId($adminId){
        return Db::table('game_auth_group_access')->where('uid',$adminId)->value('group_id');
    }

    public function prohibitWithdrawals()
    {
        $roleId = input('role_id');
        $status = input('status');
        $userDB = new UserDB();
        $jobInfo = $userDB->getTableObject('T_Job_UserInfo')
             ->where('RoleID',$roleId)
             ->where('job_key',10100)
             ->find();
        if($status == 1){
            if(!empty($jobInfo)){
               $userDB->getTableObject('T_Job_UserInfo')
                   ->where('RoleID',$roleId)
                   ->where('job_key',10100)
                   ->update(['value' => 1]);
               return $this->apiReturn(0,[],'玩家提现状态更新为禁用');
           }else{
               $data = ['RoleID' => $roleId, 'job_key' => 10100,'value'=>1];
               $userDB->getTableObject('T_Job_UserInfo')
                   ->insert($data);
                return $this->apiReturn(0,[],'玩家提现状态新增禁用');
           }
        }else{
            if(!empty($jobInfo)){
                $userDB->getTableObject('T_Job_UserInfo')
                    ->where('RoleID',$roleId)
                    ->where('job_key',10100)
                    ->update(['value' => 0]);
                return $this->apiReturn(0,[],'玩家提现状态更新为启用');
            }else{
                $data = ['RoleID' => $roleId, 'job_key' => 10100,'value'=>0];
                $userDB->getTableObject('T_Job_UserInfo')
                    ->insert($data);
                return $this->apiReturn(0,[],'玩家提现状态新增启用');
            }
        }
    }
}
