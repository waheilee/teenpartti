<?php

namespace app\merchant\controller;

use app\common\Api;
use app\model;
use app\model\GameOCDB;
use app\model\DataChangelogsDB;
use app\model\UserDB;
use app\model\AccountDB;
use think\response\Json;
use think\Db;
use function PHPSTORM_META\elementType;

/**
 * Class Statistical
 * @package app\admin\controller
 */
class Statistical extends Main
{

    /**
     * 每日游戏输赢玩家数
     */
    public function winlostnum()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $orderby = intval(input('orderby')) ? intval(input('orderby')) : 0;
            $asc = intval(input('asc')) ? intval(input('asc')) : 0;
            $date = trim(input('strartdate')) ? trim(input('strartdate')) : '';
            $kindid = trim(input('kindid')) ? trim(input('kindid')) : 0;
            $roomid = intval(input('roomid')) ? intval(input('roomid')) : 0;

            $res = Api::getInstance()->sendRequest([
                'date' => $date,
                'page' => $page,
                'pagesize' => $limit,
                'orderby' => $orderby,
                'asc' => $asc,
                'kindid' => $kindid,
                'roomid' => $roomid,
            ], 'room', 'roomdailywin');

            if (isset($res['data']['list']) && $res['data']['list']) {
                foreach ($res['data']['list'] as &$v) {
                    //盈利 sprintf("%.2f",$num);
                    $v['totalwater'] = round($v['totalwater'], 2);
                    $v['totalwin'] = round($v['totalwin'], 2);
                    $v['blacktax'] = round($v['blacktax'], 2);
                    $v['addtime'] = date('Y-m-d', strtotime($v['addtime']));
                    $v['percent2'] = $v['totalnum'] > 0 ? round($v['winnum'] / $v['totalnum'] * 100, 2) : 0;
                    $v['percent2'] .= '%';
                    //活跃度
                }
                unset($v);
            }


//            return $this->apiReturn($res['code'], isset($res['data']['ResultData']['list'])?$res['data']['ResultData']['list'] : [] , $res['message'], $res['data']['total'], [
            return $this->apiReturn($res['code'], isset($res['data']['list']) ? $res['data']['list'] : [], $res['message'], isset($res['total']) ? $res['total'] : 0, [
                'orderby' => isset($res['data']['orderby']) ? $res['data']['orderby'] : 0,
                'asc' => isset($res['data']['asc']) ? $res['data']['asc'] : 0,
            ]);

        }

        $kindList = $this->GetKindList();

        $this->assign('kindlist', $kindList);

        $selectData = $this->getRoomList();
        $this->assign('selectData', $selectData);
        return $this->fetch();
    }


    /**
     * 游戏盈亏结算
     */
    public function gamedata()
    {

        switch (input('Action')) {
            case 'list':
                if ($this->request->isAjax()) {
                    $db = new GameOCDB();
                    $result = $db->getOperatorGameRGDlist();
                    return $this->apiJson($result);
                }
            case 'exec':
                //权限验证 
                // $auth_ids = $this->getAuthIds();
                // if (!in_array(10008, $auth_ids)) {
                //     return $this->apiJson(["code"=>1,"msg"=>"没有权限"]);
                // }
                $db = new GameOCDB();
                $result = $db->getGameRGDlist();
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
                        lang('日期') => 'date',//addDate
                        lang('房间名称') => 'string',//RoleId
                        lang('流水') => "0.00",//TotalWater
                        lang('平台盈亏') => '0.00',//SGD
                        lang('游戏回报率') => "string",//ROA
                        lang('税收') => "string",//ROA
                        lang('杀率') => "string",//ROA
                        lang('总游戏人数') => "integer",//TotalDeposit
                        lang('局数') => 'integer',//TotalRollOut
                    ];
                    $filename = lang('盈亏排行') . '-' . date('YmdHis');
                    $rows =& $result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {
//                     halt($row);
//                        unset($row['id'],$row['KindID'],$row['totalwin'],$row['DailyEggTax'], $row['totallost'], $row['_ORDER_'], $row['totalScore'], $row['TotalTax']);
                        $item = [
                            $row['AddTime'],
                            $row['RoomName'],
                            $row['Water'],
                            $row['WinScore'],
                            $row['GameRate'],
                            $row['Tax'],
                            $row['KillRate'],
                            $row['TotalNum'],
                            $row['GCount'],
                        ];
//                        halt($item);
                        $writer->writeSheetRow('sheet1', $item, ['height' => 16, 'halign' => 'center',]);
                        unset($rows[$index]);
                    }
                    unset($row, $item);
                    $writer->writeToStdOut();
                    exit();
                }

        }


        $kindList['data'] = $this->GetKindList();
        $this->assign('kindlist', $kindList['data']);
        $selectData = $this->GetRoomList();
        $this->assign('selectData', $selectData);
        return $this->fetch();
    }

    /**
     * 游戏盈亏结算 查看明细
     * @return mixed|Json
     */
    public function TotalRoominfo()
    {
        $date = trim(input('date')) ? trim(input('date')) : date('Y-m-d');
        $roomid = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $roomlist = $this->getRoomList();
        $this->assign('roomid', $roomid);
        $this->assign('date', $date);
        switch (input('Action')) {
            case 'list':
                $db = new GameOCDB();
                $result = $db->GetTotalRoominfo($roomlist);
                return $this->apiJson($result);
            case 'exec':
                //权限验证 
                // $auth_ids = $this->getAuthIds();
                // if (!in_array(10008, $auth_ids)) {
                //     return $this->apiJson(["code"=>1,"msg"=>"没有权限"]);
                // }
                $db = new GameOCDB();
                $result = $db->GetTotalRoominfo($roomlist);
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
                        lang('玩家ID') => 'integer',//addDate
                        lang('局数') => 'integer',//RoleId
                        lang('总流水') => "0.000",//TotalWater
                        lang('平台盈亏') => '0.000',//SGD
                        lang('回报率') => "string",//ROA
                    ];
                    $filename = $result['other']['RoomName'] . '-' . lang('游戏明细') . '-' . $date;
                    $rows =& $result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);

                    foreach ($rows as $index => &$row) {
                        $item = [$row['RoleID'], $row['GCount'], $row['Water'], $row['WinScore'], $row['GameRate']];
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

    /**
     * 平台每日统计Platform Daily Statistics
     */
    public function platformDailyStatistics()
    {
        $date = trim(input('strartdate')) ? trim(input('strartdate')) : date("Y-m-d");
        $sqlQuery = "SELECT top 1 * FROM T_GameStatisticPay (NOLOCK)  a " .
            " left join T_GameStatisticPayOut (NOLOCK) as b on a.mydate=b.mydate " .
            " left join T_GameStatisticTotal  (NOLOCK) as c on a.mydate=c.mydate " .
            " left join T_GameStatisticUser  (NOLOCK)  as d on a.mydate=d.mydate where a.mydate='$date'";
        $db = new GameOCDB();
        $dt = $db->getTableQuery($sqlQuery);
        if (!empty($dt)) {
            $res['data'] = $dt[0];
            unset($dt);
        } else
            $res['data'] = null;


        if (isset($res['data']) && $res['data'] != null) {
            $res['data']['zcbl'] = $res['data']['zcbl'] * 100 . '%';
            $res['data']['zbbl'] = $res['data']['zbbl'] * 100 . '%';
            $res['data']['agentczzb'] = $res['data']['agentczzb'] * 100 . '%';
//            $res['data']['totalchargeoutrate'] = $res['data']['totalchargeoutrate'] * 100 . '%';
            $res['data']['chargeoutrate'] = $res['data']['chargeoutrate'] * 100 . '%';
            $res['data']['chontibinew'] = $res['data']['chontibinew'] * 100 . '%';
            $res['data']['chontibiold'] = $res['data']['chontibiold'] * 100 . '%';
            $res['data']['chongduibi'] = $res['data']['chongduibi'] * 100 . '%';
            $res['data']['totalpay'] /= bl;
            $res['data']['newuserpay'] /= bl;
            $res['data']['olduserpay'] /= bl;
            $res['data']['agentpay'] /= bl;
//            $res['data']['agentnewuserpay'] /= 1000;
//            $res['data']['agentolduserpay'] /= 1000;
            $res['data']['adminchargebmoney'] /= bl;
            $res['data']['ioschargemoney'] /= bl;
            $res['data']['androidchargemoney'] /= bl;
            $res['data']['bdmoney'] /= bl;
            $res['data']['totalpayorder'] /= bl;
//            $res['data']['totalchargeoutmoney'] /= 1000;
            $res['data']['totalyk'] /= bl;
            $res['data']['totaltax'] /= bl;
            $res['data']['totalwin'] /= bl;
            $res['data']['totalwater'] /= bl;
            $res['data']['onlineuseraccount'] /= bl;
//            $res['data']['historytotalin'] /= 1000;
//            $res['data']['historytotalout'] /= 1000;
            $res['data']['payoutnew'] /= bl;
            $res['data']['payoutold'] /= bl;
            $this->assign('data', $res['data']);
            $this->assign('mydata', $date);
            $this->assign('flag', 3);
        } else {
            $this->assign('mydata', $date);
            $this->assign('flag', 4);
        }
        if ($this->request->isAjax()) {
            if (isset($res['data']) && $res['data'] != null)
                $res['code'] = 3;//
            $res['list'] = $res['data'];
            $this->assign('mydata', $date);
//            $res['code']=4;
            return $this->apiJson($res);//
        }
        return $this->fetch();
    }

    /**
     * 玩家每日游戏房间记录
     */
    public function gamedailylog()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleid = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $roomid = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $account = input('account');
            $orderby = input('orderby');
            $orderType = input('orderType');
            $date = trim(input('date')) ? trim(input('date')) : date('Y-m-d');
            //日期不能比今天大 表都没
            if (strtotime($date) > strtotime(date('Y-m-d'))) return $this->apiJson([]);
            $order = "";
            if (!empty($orderby)) $order .= "$orderby $orderType";
            else $order = "ServerID  ASC";
            $Table = "T_UserGameChangeLogs_" . str_replace('-', '', $date);
            $where = " addDate='$date' ";
            if ($roomid > 0) $where .= " AND ServerID=$roomid";
            if ($roleid > 0) $where .= " AND A.RoleID=$roleid";
            if (!empty($account)) $where .= " AND AccountName='$account'";
            $sqlQuery = "SELECT A.*,RoomId,B.TotalTax,TotalBat,addDate,C.AccountName,C.LoginName FROM(" .
                " SELECT RoleID, (ServerID/10*10)KindID, ServerID, count(1)draw,sum(money)WinMoney FROM $Table" .
                " GROUP BY  ServerID,ServerID/10*10,RoleID )A " .
                " LEFT JOIN T_UserTotalWater as B WITH (NOLOCK) on A.ServerID=B.RoomID and A.RoleID=B.RoleID " .
                " LEFT JOIN CD_UserDB.dbo.View_Accountinfo C ON  A.RoleID=C.AccountID " .
                " WHERE $where order by  $order  OFFSET " . ($page - 1) * $limit . " ROWS FETCH NEXT $limit ROWS ONLY ";

            $coutQuery = "SELECT COUNT(1)count FROM(SELECT RoleID, (ServerID/10*10)KindID, ServerID, count(1)draw,sum(money)WinMoney FROM $Table 
            GROUP BY ServerID,ServerID/10*10,RoleID )A 
            LEFT JOIN T_UserTotalWater as B WITH (NOLOCK) on A.ServerID=B.RoomID and A.RoleID=B.RoleID
            LEFT JOIN CD_UserDB.dbo.View_Accountinfo C ON A.RoleID=C.AccountID
            WHERE $where";

            $db = new GameOCDB($Table);
            $result['list'] = $db->getTableQuery($sqlQuery);
            $result['count'] = $db->getTableQuery($coutQuery)[0]['count'];

            $roomlist = $this->getRoomList();
            if (isset($result['list']) && $result['list']) {
                foreach ($result['list'] as &$v) {
                    foreach ($roomlist as $kk => $vv) {
                        if ($vv['RoomID'] == $v['ServerID']) {
                            $v['RoomName'] = $vv['RoomName'];
                            break;
                        }
                    }
                    //盈利
                    $v['WinMoney'] = round($v['WinMoney'] / bl, 2);
                    $v['TotalTax'] = round($v['TotalTax'] / bl, 2);
                    //活跃度
                    $v['totalwater'] = round($v['TotalBat'] / bl, 2);
                    $v['date'] = $date ? $date : date("Y-m-d");

                }
                unset($v);
            }
            return $this->apiJson($result);


        }

        $selectData = $this->getRoomList();
        $this->assign('selectData', $selectData);
        return $this->fetch();
    }

    /**
     * @return Json
     */
    public function GetOnlineDatalist()
    {
        switch (input('Action')) {
            case 'list':
                $db = new GameOCDB();
                $result = $db->GetOnlinelist();
                return $this->apiJson($result);
            case  'Graph'://曲线图
                $db = new GameOCDB();
                $res = $db->GetOnlinelist(true);
                $b_result['list'][0] = array_column($res['yes'], 'addtime');
                foreach ($b_result['list'][0] as $key => &$val) {
                    $val = date('Y-m-d H:i:s',strtotime($val) + 86400);
                }
                $b_result['list'][1] = array_column($res['list'], 'hallonline');
                $b_result['list'][2] = array_column($res['list'], 'roomonline');
                $b_result['list'][3] = array_column($res['yes'], 'hallonline');
                $b_result['list'][4] = array_column($res['yes'], 'roomonline');
                //换算成一小时的。截取每60个记录,23条记
                $result = [];
                for ($i=0; $i < 24; $i++) {
                    if (isset($b_result['list'][0][$i*60])) {
                        $result['list'][0][] =  $b_result['list'][0][$i*60];
                    }
                    if (isset($b_result['list'][1][$i*60])) {
                        $result['list'][1][] =  $b_result['list'][1][$i*60];
                    }
                    if (isset($b_result['list'][2][$i*60])) {
                        $result['list'][2][] =  $b_result['list'][2][$i*60];
                    }
                    if (isset($b_result['list'][3][$i*60])) {
                        $result['list'][3][] =  $b_result['list'][3][$i*60];
                    }
                    if (isset($b_result['list'][4][$i*60])) {
                        $result['list'][4][] =  $b_result['list'][4][$i*60];
                    }
                }
                return $this->apiJson($result);
        }
    }
    /**
     * 游戏在线统计图
     * @return mixed
     */
    public function gameonlinedata()
    {
        return $this->fetch();
    }


    public function GetOnlineDatalist2()
    {
        $date = input('date') ?: date('Y-m-d');
        $num  = input('num') ?: 2;
        $db = new GameOCDB();
        $result = [];
        $result['name']   = []; 
        $result['list']   = [];
        $date_list = [];
        for ($i=0; $i <= 288; $i++) {
            $h = floor($i*5/60);
            $m = $i*5%60;
            if ($m < 10) {
                $m = '0'.$m;
            }
            $date_list[] = $h.':'.$m;
        }
        //今天 昨天 前7天
        $result['list'][] = $date_list;

        $arr = [1,2,8];
        foreach ($arr as $key => &$val) {
            $i = $val-1;
            $use_date = date('Y-m-d',strtotime($date)-$i*86400);
            $res = $db->GetOnlinelistByDate($use_date);
            $hallonline = [];
            $roomonline = [];
            for ($j=0; $j <= 288; $j++) {
                if($j == 0){
                    $hallonline[] =  $res['hallonline'][$j] ?? 0;
                    $roomonline[] =  $res['roomonline'][$j] ?? 0;
                }
                if (isset($res['hallonline'][$j*5-1])) {
                    $hallonline[] =  $res['hallonline'][$j*5-1];
                }
                if (isset($res['roomonline'][$j*5-1])) {
                    $roomonline[] =  $res['roomonline'][$j*5-1];
                }
            }
            $name[] = $use_date.lang('房间在线人数');
            $result['list'][] = $roomonline;
            $name[] = $use_date.lang('大厅在线人数');
            $result['list'][] = $hallonline;
            
        }
        $result['other'] = $name;
        return $this->apiJson($result);
    }

    /**
     * @return Json
     */
    public function GetRechargeDatalist(){

        $date = input('date') ?: date('Y-m-d');
        $num  = input('num') ?: 2;
        $db = new UserDB();
        $result = [];
        $result['name']   = []; 
        $result['list']   = [];
        $date_list = [];
        for ($i=0; $i <= 96; $i++) {
            $h = floor($i*15/60);
            $m = $i*15%60;
            if ($m < 10) {
                $m = '0'.$m;
            }
            $date_list[] = $h.':'.$m;
        }
        $result['list'][] = $date_list;
        $arr = [1,2,8];
        foreach ($arr as $key => &$val) {
            $i = $val-1;
            $use_date = date('Y-m-d',strtotime($date)-$i*86400);
            $start = $use_date.' 00:00:00';
            for ($j=0; $j <= 96; $j++) {
                $end_date = date('Y-m-d H:i:s',strtotime($use_date)+$j*900);
                if ($j == 0) {
                    $sql = "SELECT '$end_date' as ddate,ISNULL(SUM(RealMoney),0) RealMoney,COUNT(DISTINCT AccountID) r_num FROM [CD_UserDB].[dbo].[T_UserTransactionChannel](NOLOCK) WHERE AddTime >= '".$start."' and AddTime<'".$end_date."'";
                } else {
                    if (strtotime($end_date)<time()) {
                        $sql .= " UNION ALL ";
                        $sql .= "SELECT '$end_date' as ddate,ISNULL(SUM(RealMoney),0) RealMoney,COUNT(DISTINCT AccountID) r_num FROM [CD_UserDB].[dbo].[T_UserTransactionChannel](NOLOCK) WHERE AddTime >= '".$start."' and AddTime<'".$end_date."'";
                    }
                }
            }

            $res = $db->DBOriginQuery($sql);
            $name[] = $use_date.lang('充值金额');
            $result['list'][] = array_column($res, 'RealMoney');
            $name[] = $use_date.lang('充值人数');
            $result['list'][] = array_column($res, 'r_num');
        }
        $result['other'] = $name;
        return $this->apiJson($result); 
    }


    public function GetDailyRechargelist(){
        $date = input('date') ?: date('Y-m-d');
        $num  = input('num') ?: 2;
        $db = new DataChangelogsDB();
        $result = [];
        $result['name']   = []; 
        $result['list']   = [];
        $date_list = [];
        for ($i=0; $i <= 96; $i++) {
            $h = floor($i*15/60);
            $m = $i*15%60;
            if ($m < 10) {
                $m = '0'.$m;
            }
            $date_list[] = $h.':'.$m;
        }
        $result['list'][] = $date_list;
        $arr = [1,2,8];
        foreach ($arr as $key => &$val) {
            $i = $val-1;
            $use_date = date('Y-m-d',strtotime($date)-$i*86400);
            $start = $use_date.' 00:00:00';
            for ($j=0; $j <= 96; $j++) {
                $end_date = date('Y-m-d H:i:s',strtotime($use_date)+$j*900);
                if ($j == 0) {
                    $sql = "SELECT '$end_date' as ddate,ISNULL(SUM(ChangeMoney),0)/1000 ChangeMoney,COUNT(DISTINCT RoleID) r_num FROM [CD_DataChangelogsDB].[dbo].[T_UserTransactionLogs](NOLOCK) WHERE IfFirstCharge=1 and ChangeType=5 and AddTime >= '".$start."' and AddTime<'".$end_date."'";
                } else {
                    if (strtotime($end_date)<time()) {
                        $sql .= " UNION ALL ";
                        $sql .= "SELECT '$end_date' as ddate,ISNULL(SUM(ChangeMoney),0)/1000 ChangeMoney,COUNT(DISTINCT RoleID) r_num FROM [CD_DataChangelogsDB].[dbo].[T_UserTransactionLogs](NOLOCK) WHERE IfFirstCharge=1 and ChangeType=5 and AddTime >= '".$start."' and AddTime<'".$end_date."'";
                    }
                }
            }
            $res = $db->DBOriginQuery($sql);
            $name[] = $use_date.lang('首充金额');
            $result['list'][] = array_column($res, 'ChangeMoney');
            $name[] = $use_date.lang('首充人数');
            $result['list'][] = array_column($res, 'r_num');
        }
        $result['other'] = $name;
        return $this->apiJson($result); 
    }


    public function GetDailyRegisterUsers(){
        $date = input('date') ?: date('Y-m-d');
        $num  = input('num') ?: 2;
        $db = new AccountDB();
        $result = [];
        $result['name']   = []; 
        $result['list']   = [];
        $date_list = [];
        for ($i=0; $i <= 96; $i++) {
            $h = floor($i*15/60);
            $m = $i*15%60;
            if ($m < 10) {
                $m = '0'.$m;
            }
            $date_list[] = $h.':'.$m;
        }
        $result['list'][] = $date_list;
        $arr = [1,2,8];
        foreach ($arr as $key => &$val) {
            $i = $val-1;
            $use_date = date('Y-m-d',strtotime($date)-$i*86400);
            $start = $use_date.' 00:00:00';
            for ($j=0; $j <= 96; $j++) {
                $end_date = date('Y-m-d H:i:s',strtotime($use_date)+$j*900);
                if ($j == 0) {
                    $sql = "SELECT '$end_date' as ddate,count(AccountID) num FROM [CD_Account].[dbo].[T_Accounts](NOLOCK) WHERE RegisterTime >= '".$start."' and RegisterTime<'".$end_date."'";
                } else {
                    if (strtotime($end_date)<time()) {
                        $sql .= " UNION ALL ";
                        $sql .= "SELECT '$end_date' as ddate,count(AccountID) num FROM [CD_Account].[dbo].[T_Accounts](NOLOCK) WHERE RegisterTime >= '".$start."' and RegisterTime<'".$end_date."'";
                    }
                }
            }
            $res = $db->DBOriginQuery($sql);
            $name[] = $use_date.lang('注册人数');
            $result['list'][] = array_column($res, 'num');
        }
        $result['other'] = $name;
        return $this->apiJson($result); 
    }
    /**
     * 玩家统计
     */
    public function gameonlinedata2()
    {
        if ($this->request->isAjax()) {
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $start = input('start') ? input('start') : date('Y-m-d');
            $end = input('end') ? input('end') : date('Y-m-d');

            $TotalCount = 0;
            $query = [
                'startdate' => $start,
                'enddate' => $end,
                'page' => $page,
                'pagesize' => $limit,
            ];

            $tableName = " [OM_GameOC].[dbo].[Proc_RoomOnlineData_Select] "; //存储过程名称
            $strfields = ":startdate,:enddate,:page,:pagesize,:TotalCount";
            $model = new model\UserDetail('CD_DataChangelogsDB');
            $list = $model->getProcedure($tableName, $strfields, $query);
            $res['data']['list'] = $list['list'];
            $res['code'] = 0;
            $res['message'] = '';
            $res['total'] = $list['count'];
            //时间  ios  安卓
            return $this->apiReturn($res['code'], isset($res['data']['list']) ? $res['data']['list'] : [], $res['message'], $res['total']);
        }
        return $this->fetch();
    }


    /**
     * 玩家盈亏排行
     * @return mixed|Json
     */
    public function gainDeficitRank()
    {
        $start = input('strartdate', date('Y-m-d'));
        $end = input('enddate', date('Y-m-d'));
        $RoleID = input('RoleID');
        $proxyId = input('proxyId', '');
        $where = " And addDate BETWEEN '$start' AND '$end' ";
        if ($RoleID != null) $where .= " AND ProxyChannelId=$RoleID";

        $order = input('order');
        $sort = input('sort');
        $db = new GameOCDB();
        $default_ProxyId = $db->getTableObject('T_ProxyChannelConfig')->where('isDefault', 1)->value('ProxyId') ?: 0;
        if (!empty($proxyId)) {
            
            $proxy_roleid = $db->getProxyChannelConfig()->getValueByTable('ProxyId=\'' . $proxyId . '\'', 'ProxyChannelId');
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
        switch (input('Action')) {
            case 'list':
                $result = $db->getGDrank($where, "$order $sort");
                return $this->apiJson($result);
            case 'exec':
                //权限验证 
                // $auth_ids = $this->getAuthIds();
                // if (!in_array(10008, $auth_ids)) {
                //     return $this->apiJson(["code"=>1,"msg"=>"没有权限"]);
                // }
                $result = $db->getGDrank($where, "$order $sort");
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
                        lang('日期') => 'datetime',//addDate
                        lang('用户ID') => 'integer',//RoleId
                        lang('流水') => "0.00",//TotalWater
                        lang('游戏盈亏') => '0.00',//SGD
                        lang('回报率') => "string",//ROA
                        lang('充值') => "0.00",//TotalDeposit
                        lang('下分') => '0.00',//TotalRollOut
                        lang('平台盈亏') => '0.00',//TotalRollOut
                        lang('剩余金币') => '0.00',//Money
                    ];
                    $filename = lang('盈亏排行') . '-' . date('YmdHis');
                    $rows =& $result['list'];
//                    halt($rows);
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    $total = 0;
                    foreach ($rows as $index => &$row) {
                        unset($row['totalwin'], $row['totallost'], $row['_ORDER_'], $row['totalScore'], $row['TotalTax']);
                        $writer->writeSheetRow('sheet1', $row, ['height' => 16, 'halign' => 'center',]);
                        unset($rows[$index]);
                    }
                    unset($row);
                    $writer->writeToStdOut();
                    exit();
                }
        }
        return $this->fetch();
    }


    /**
     * @return mixed
     */
    public function gameYkReport()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 15;
            $roleid = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $ispay = input('ispay', '');
            $orderby = input('orderby', 'lastdeposit');
            $orderType = input('orderType', 'desc');
            $date = trim(input('date')) ? trim(input('date')) : date('Y-m-d');
            $isonline = intval(input('isonline')) ? intval(input('isonline')) : 1;
            $ctr_status = input('ctr_status', 0, 'trim');


            $strwhere = " 1=1 ";
            $logdate = date('Ymd', strtotime($date));
            if ($roleid > 0) {
                $strwhere .= ' and accountid=' . $roleid;
            }
            if ($ctr_status == 1) {
                // 控制中
                $strwhere .= " and  ctrlratio != 100 and ctrlratio is not NULL";
            } else if ($ctr_status == 2) {
                // 未控制
                $strwhere .= " and  (ctrlratio = 100 or ctrlratio is NULL)";
            }
            $order = $orderby . ' ' . $orderType;
            if (!empty($ispay)) {
                if (intval($ispay) === 2)
                    $strwhere .= ' and lastdeposit=0';
                else if (intval($ispay) == 1) {
                    $strwhere .= ' and lastdeposit>0';
                }
            }
            $online = $this->GetOnlineUserlist();
            if ($isonline) {
                /**获取在在线列表*/
                $onlinestr = implode(',', $online);
                if ($online) {
                    if ($isonline == 1) {
                        $strwhere .= " and accountid in($onlinestr)";
                    } else if ($isonline == 2) {
                        {
                            $strwhere .= " and accountid not in($onlinestr)";
                        }
                    }
                } else {
                    if ($isonline == 1) {
                        $strwhere .= " and accountid=0 ";
                    }
                }
            }

            $filed = "accountid,balance,totaldeposit,totalrollout,lastdeposit,gamertp,mailcoin,ctrlratio";
            $table = "(SELECT  a.accountid,c.Money as balance,(d.TotalDeposit+g.payorder) as TotalDeposit,d.TotalRollOut,ISNULL(e.lastdeposit, 0) lastdeposit,f.gamertp,g.mailcoin,h.ctrlratio FROM  CD_Account.dbo.T_Accounts AS a "
                . " LEFT OUTER JOIN  CD_UserDB.dbo.T_UserGameWealth AS c ON a.AccountID = c.RoleID "
                . " LEFT OUTER JOIN CD_UserDB.dbo.T_UserCollectData as d ON a.AccountID = d.RoleId "
                . " LEFT OUTER JOIN (select max(id) as id,AccountID,sum(realmoney) as lastdeposit from CD_UserDB.dbo.T_UserChannelPayOrder where status=1  GROUP BY AccountID) as e ON a.AccountID = e.AccountID  left join (SELECT  aa.roleid,(case when bb.totalbat>0 then cast(aa.freegold as float)/bb.totalbat else 0 end) as gamertp  from (SELECT RoleID,SUM(CASE WHEN changetype IN (15, 22, 26, 27, 28, 34, 48, 53, 54) and money>0 THEN money ELSE 0 END) AS freegold  FROM  OM_GameOC.dbo.T_BankWeathChangeLog_" . $logdate . " GROUP BY RoleID) aa left join (SELECT   RoleID,sum(totalbat) as totalbat FROM  OM_GameOC.dbo.T_UserTotalWater  GROUP BY RoleID) as bb on aa.roleid=bb.roleid) as f  on a.AccountID=f.roleid  left join (SELECT RoleId,SUM(Amount) as mailcoin,sum(case when PayOrder=1 and VerifyState=1 then Amount else 0 end) as payorder FROM CD_DataChangelogsDB.dbo.T_ProxyMsgLog WHERE 
datediff(d,AddTime,'" . $date . "')=0 and [VerifyState] = 1 AND RoleId>0  GROUP BY RoleId ) as g on a.AccountID=g.roleid left join  [CD_UserDB].[dbo].[T_UserCtrlData] as h on a.accountid=h.roleid  where (c.money>=e.lastdeposit*5000 and  e.lastdeposit>0) or (e.lastdeposit is null and c.Money>=100000)) as t ";
            $account = new model\Account();
            $data = $account->getProcPageList($table, $filed, $strwhere, $order, $page, $limit);
            $list = [];
            $count = 0;
            if ($data) {
                if ($data['count'] > 0) {
                    $list = $data['list'];
                    foreach ($list as $item => &$v) {
                        $v['gamertp'] = sprintf('%.2f', $v['gamertp'] * 100);
                        if ($v['balance'])
                            $v['balance'] = FormatMoney($v['balance']);
                        else
                            $v['balance'] = 0;


                        if (empty($v['ctrlratio'])) {
                            $v['ctrlratio'] = lang('未设置');
                        }

                        if (empty($v['totaldeposit']))
                            $v['totaldeposit'] = 0;
                        if (empty($v['totalrollout']))
                            $v['totalrollout'] = 0;

                        if (empty($v['mailcoin']))
                            $v['mailcoin'] = 0;
                        else
                            $v['mailcoin'] = FormatMoney($v['mailcoin']);

                        if (empty($v['lastdeposit']))
                            $v['lastdeposit'] = 0;

                        $v['online'] = lang('离线');
                        if ($online) {
                            if (in_array($v['accountid'], $online)) {
                                $v['online'] = lang('在线');
                            }
                        }


                    }
                }
                unset($v);
                $count = $data['count'];
            }
            return $this->apiReturn(0, $list, '', $count);
        }
        return $this->fetch();
    }


    /**
     *购买免费游戏的明细报表
     */
    public function FreeGameRecord()
    {
        switch (input('Action')) {
            case 'list':
                $db = new GameOCDB();
                $RoleID = input('RoleID');
                $result = $db->GetFreeGameData($RoleID);
                return $this->apiJson($result);
            case 'infoView':
                $this->assign("RoleID", input('RoleID'));
                $this->assign("start", input('start'));
                $this->assign("end", input('end'));
                return $this->fetch('free_game_info');
            case 'exec':

        }
        $selectData = $this->GetRoomList();
        $this->assign('selectData', $selectData);
        return $this->fetch();
    }


    /**
     * vip报表
     */

    public function VipAwardReport()
    {

        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 15;
            $roleid = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $orderby = input('orderby', 'AccountID');
            $orderType = input('orderType', 'desc');

            $order = $orderby . ' ' . $orderType;

            $strwhere = '';
            if ($roleid > 0) {
                $strwhere .= '  accountid=' . $roleid;
            }

            $filed = "accountid,viplv,signaward,weekaward,monthaward,uplevelaward";
            $table = "[OM_GameOC].[dbo].[View_VipTotalAward]";
            $db = new model\Account();
            $data = $db->getProcPageList($table, $filed, $strwhere, $order, $page, $limit);
            $list = [];
            $count = 0;
            if ($data) {
                if ($data['count'] > 0) {
                    $list = $data['list'];
                    foreach ($list as $item => &$v) {
                        if ($v['signaward'])
                            $v['signaward'] = FormatMoney($v['signaward']);
                        else
                            $v['signaward'] = 0;

                        if ($v['weekaward'])
                            $v['weekaward'] = FormatMoney($v['weekaward']);
                        else
                            $v['weekaward'] = 0;

                        if ($v['monthaward'])
                            $v['monthaward'] = FormatMoney($v['monthaward']);
                        else
                            $v['monthaward'] = 0;

                        if ($v['uplevelaward'])
                            $v['uplevelaward'] = FormatMoney($v['uplevelaward']);
                        else
                            $v['uplevelaward'] = 0;

                    }
                }
                $count = $data['count'];
            }
            return $this->apiReturn(0, $list, '', $count);
        }

        return $this->fetch();
    }

    //支付排行榜
    public function payRanking()
    {
        $action = $this->request->param('action');
        if ($action == 'list' ||  $action == 'output') {
            $limit = $this->request->param('limit') ?: 20;
            $roleid = $this->request->param('roleid');
            $ParentId = $this->request->param('ParentId');
            $proxyId = $this->request->param('proxyId');

            $start_date = $this->request->param('start_date')?:date('Y-m-d');
            $end_date = $this->request->param('end_date');

            $reg_date1 = $this->request->param('start_register_date');
            $reg_date2 = $this->request->param('end_register_date');
            $login_date1 = $this->request->param('start_login_date');
            $login_date2 = $this->request->param('end_login_date');

            $min_recharge_num = $this->request->param('min_recharge_num');
            $max_recharge_num = $this->request->param('max_recharge_num');

            $type = $this->request->param('type') ?: 1;
            $where = '1=1 ';
            
            $db = new GameOCDB();
            $default_ProxyId = $db->getTableObject('T_ProxyChannelConfig')->where('isDefault', 1)->value('ProxyId') ?: 0;
            $ProxyChannelConfig = (new GameOCDB())->getTableObject('T_ProxyChannelConfig')->column('*','ProxyChannelId');
            if (!empty($proxyId)) {
                $proxy_roleid = $db->getProxyChannelConfig()->getValueByTable('ProxyId=\'' . $proxyId . '\'', 'ProxyChannelId');
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

            
            if ($roleid != '') {
                $where .= ' and a.accountID=' . $roleid;
            }

            if ($ParentId != '' && $type == 2) {
                $where .= ' and a.ParentID=' . $ParentId;
            }

            if ($reg_date1 != '') {
                $where .= ' and c.RegisterTime>=\'' .$reg_date1.'\'';
            }
            if ($reg_date2 != '') {
                $where .= ' and c.RegisterTime<=\'' .$reg_date1.'\'';
            }
            if ($login_date1 != '') {
                $where .= ' and c.LastLoginTime<=\'' .$login_date1.'\'';
            }
            if ($login_date2 != '') {
                $where .= ' and c.LastLoginTime<=\'' .$login_date2.'\'';
            }
            if ($min_recharge_num!='') {
                $where .= ' and a.PayMoney>=\'' . ($min_recharge_num*1000) . '\'';
            }
            if ($max_recharge_num!='') {
                $where .= ' and a.PayMoney<=\'' . ($max_recharge_num*1000) . '\'';
            }

            $order = "PayMoney desc";
            $orderby = input('orderby');
            $orderytpe = input('orderytpe');
            if (!empty($orderby)) {
                if ($orderby == 'PlatformProfit') {
                    $orderby = '(PayOut-PayMoney)';
                }
                $order = " $orderby $orderytpe";
            }

            $operatorid= session('merchant_OperatorId');

            switch ($type) {
                case '1':
                    $remark = '今日注册';
                    $table = 'View_PaymentDailyRank';
                    $where .=' and  a.OperatorId='.$operatorid;
                    $where .= ' and datediff(d,a.RegisterTime,\'' . date('Y-m-d') . '\')=0 ';
                    $where .= ' and  b.addDate=\''.date('Y-m-d') . '\'';
                    break;
                case '2':
                    $remark = '今日首冲';
                    $table = 'View_PaymentFirstRank';
                    $where .=' and  a.OperatorId='.$operatorid;
                    $where .= ' and  a.adddate=\''.$start_date . '\'';
                    break;
                case '3':
                    $remark = '支付排行榜';
                    $table = 'View_PaymentTotalRank';
                    $where .=' and  a.OperatorId='.$operatorid;
                    break;
                case '4':
                    $remark = '日充值排行榜';
                    $table = 'View_PaymentDailyRank';
                    $where .=' and  a.OperatorId='.$operatorid;
                    $where .= ' and  a.Adddate=\''.$start_date . '\'';
                    break;
                default:
                    break;
            }
            if (in_array($type, [1,2,4])) {
                $data = $db->getTableObject("[OM_GameOC].[dbo].[$table](NOLOCK)")->alias("a")
                        ->join("[OM_GameOC].[dbo].[View_TotalDayScore](NOLOCK) b","b.RoleId=a.AccountID and  a.Adddate=b.addDate",'left')
                        ->join('[CD_Account].[dbo].[T_Accounts](NOLOCK) c', 'c.AccountID=a.AccountID')
                        ->where($where)
                        ->order($order)
                        ->field("a.*,c.Mobile,c.LastLoginTime,b.TotalWater TotalRunning,b.Tax TotalTax,-b.SGD TotalWin")
                        ->paginate($limit)
                        ->toArray();
            } else if ($type == 3) {
                $data = $db->getTableObject("[OM_GameOC].[dbo].[$table](NOLOCK)")->alias('a')
                        ->join("[CD_UserDB].[dbo].[T_UserCollectData](NOLOCK) b","b.RoleId=a.AccountID",'left')
                        ->join('[CD_Account].[dbo].[T_Accounts](NOLOCK) c', 'c.AccountID=a.AccountID ')
                        ->where($where)
                        ->order($order)
                        ->field("a.*,c.LastLoginTime,b.TotalRunning TotalRunning,b.TotalTax TotalTax,b.TotalWin TotalWin")
                        ->paginate($limit)
                        ->toArray();
            }

            $data['list'] = $data['data'];
            $data['count'] = $data['total'];
            if ($action == 'list') {
                foreach ($data['list'] as $key => &$val) {
                    $val['PayMoney'] = FormatMoney($val['PayMoney']);
                    $val['PayOut'] = FormatMoney($val['PayOut']);
                    $val['PlatformProfit'] = bcsub($val['PayOut'],$val['PayMoney'],3);
                    $val['TotalRunning'] = FormatMoney($val['TotalRunning']);
                    $val['TotalTax'] = FormatMoney($val['TotalTax']);
                    $val['TotalWin'] = FormatMoney($val['TotalWin']);
                    
                    $ParentIds = array_filter(explode(',', $val['ParentIds'] ?? ''));
                    $proxy = [];
                    if (!empty($ParentIds)) {
                        $proxy = $ProxyChannelConfig[$ParentIds[0]] ?? [];
                        if ($proxy) {
                            $val['proxyId'] = $proxy['ProxyId'];
                        } else {
                            $val['proxyId'] = $val['ParentID'];
                        }
                    } else {
                        //默认系统代理
                        $val['proxyId'] = $default_ProxyId;
                    }
                }
                return $this->apiJson($data);
            }
            if ($action == 'output') {
                 //权限验证 
                // $auth_ids = $this->getAuthIds();
                // if (!in_array(10008, $auth_ids)) {
                //     return $this->apiJson(["code"=>1,"msg"=>"没有权限"]);
                // }   
                $result = $data;
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
                    switch ($type) {
                        case '1':
                            $header_types = [
                                lang('玩家ID') => 'string',
                                lang('渠道ID') => 'string',
                                lang('充值金额') => 'string',
                                lang('充值次数') => "string",
                                lang('电话号码') => "string",
                                lang('提现金额') => 'string',
                                lang('提现次数') => "string",
                                lang('流水') => "string",
                                lang('税收') => "string",
                                // lang('游戏盈亏') => "string",
                                lang('玩家盈亏') => "string",
                                lang('最后登陆时间') => "string",
                                lang('注册时间') => "string",
                            ];
                            break;
                        case '2':
                            $header_types = [
                                lang('玩家ID') => 'string',
                                lang('代理ID') => 'string',
                                lang('渠道ID') => 'string',
                                lang('首充日期') => 'string',
                                lang('充值金额') => 'string',
                                lang('充值次数') => "string",
                                lang('电话号码') => "string",
                                lang('提现金额') => 'string',
                                lang('提现次数') => "string",
                                lang('流水') => "string",
                                lang('税收') => "string",
                                // lang('游戏盈亏') => "string",
                                lang('玩家盈亏') => "string",
                                lang('最后登陆时间') => "string",
                                lang('注册时间') => "string",
                                lang('首充金额') => "string",
                            ];
                            break;
                        case '3':
                            $header_types = [
                                lang('玩家ID') => 'string',
                                lang('渠道ID') => 'string',
                                lang('充值金额') => 'string',
                                lang('充值次数') => "string",
                                lang('电话号码') => "string",
                                lang('提现金额') => 'string',
                                lang('提现次数') => "string",
                                // lang('流水') => "string",
                                lang('税收') => "string",
                                // lang('游戏盈亏') => "string",
                                lang('玩家盈亏') => "string",
                                lang('最后登陆时间') => "string",
                                lang('注册时间') => "string",
                            ];
                            break;
                        case '4':
                            $header_types = [
                                lang('玩家ID') => 'string',
                                lang('渠道ID') => 'string',
                                lang('充值金额') => 'string',
                                lang('充值次数') => "string",
                                lang('电话号码') => "string",
                                lang('提现金额') => 'string',
                                lang('提现次数') => "string",
                                lang('流水') => "string",
                                lang('税收') => "string",
                                lang('游戏盈亏') => "string",
                                lang('玩家盈亏') => "string",
                                lang('最后登陆时间') => "string",
                                lang('注册时间') => "string",
                            ];
                            break;
                        default:
                            # code...
                            break;
                    }
                    $filename = lang($remark) . '-' . date('YmdHis');
                    $rows =& $result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {
                        $row['PayMoney'] = FormatMoney($row['PayMoney']);
                        $row['PayOut'] = FormatMoney($row['PayOut']);
                        $row['PlatformProfit'] = bcsub($row['PayOut'],$row['PayMoney'],3);
                        $row['TotalRunning'] = FormatMoney($row['TotalRunning']);
                        $row['TotalTax'] = FormatMoney($row['TotalTax']);
                        $row['TotalWin'] = FormatMoney($row['TotalWin']);
                        
                        $ParentIds = array_filter(explode(',', $row['ParentIds'] ?? ''));
                        $proxy = [];
                        if (!empty($ParentIds)) {
                            $proxy = $ProxyChannelConfig[$ParentIds[0]] ?? [];
                            if ($proxy) {
                                $row['proxyId'] = $proxy['ProxyId'];
                            } else {
                                $row['proxyId'] = $row['ParentID'];
                            }
                        } else {
                            //默认系统代理
                            $row['proxyId'] = $default_ProxyId;
                        }
                        switch ($type) {
                            case '1':
                                $item = [
                                    $row['AccountID'],$row['proxyId'], $row['PayMoney'], $row['PayTimes'], $row['Mobile'], $row['PayOut'], $row['PayOutTimes'], $row['TotalRunning'],$row['TotalTax'],$row['PlatformProfit'],$row['LastLoginTime'],$row['RegisterTime']
                                ];
                                break;
                            case '2':
                                $item = [
                                    $row['AccountID'],$row['ParentID'],$row['proxyId'],$row['adddate'], $row['PayMoney'], $row['PayTimes'], $row['Mobile'], $row['PayOut'], $row['PayOutTimes'], $row['TotalRunning'],$row['TotalTax'],$row['PlatformProfit'],$row['LastLoginTime'],$row['RegisterTime'],$row['FirstMoney']
                                ];
                                break;
                            case '3':
                                $item = [
                                    $row['AccountID'],$row['proxyId'], $row['PayMoney'], $row['PayTimes'], $row['Mobile'], $row['PayOut'], $row['PayOutTimes'],$row['TotalTax'],$row['PlatformProfit'],$row['LastLoginTime'],$row['RegisterTime']
                                ];
                                break;
                            case '4':
                                $item = [
                                    $row['AccountID'],$row['proxyId'], $row['PayMoney'], $row['PayTimes'], $row['Mobile'], $row['PayOut'], $row['PayOutTimes'], $row['TotalRunning'],$row['TotalTax'],$row['TotalWin'],$row['PlatformProfit'],$row['LastLoginTime'],$row['RegisterTime']
                                ];
                                break;
                            default:
                                # code...
                                break;
                        }

                        $writer->writeSheetRow('sheet1', $item, ['height' => 16, 'halign' => 'center',]);
                        unset($rows[$index]);
                    }
                    unset($row, $item);
                    $writer->writeToStdOut();
                    exit();
                }
            }
        }
        return $this->fetch();
    }

    //数据汇总日报
    public function totalDaily()
    {
        $action = $this->request->param('action');
        if ($action == 'list') {

        } else {
            $date = $this->request->param('date') ?: date('Y-m-d');
            $action = $this->request->param('action');
            if ($action == 'check') {
                if (strtotime($date) > sysTime()) {
                    return ['code' => 1, 'msg' => 'time error'];
                } else {
                    return ['code' => 0];
                }
            }
            $db = new GameOCDB();
            $data = [];
            //在线人数
            $data['online'] = $this->GetOnlineUserlist2('onlinenum') ?: 0;
            $data['pay'] = $db->setTable('T_GameStatisticPay')->GetRow('mydate=\'' . $date . '\'', '*') ?: [];
            $data['system'] = $db->setTable('T_GameStatisticTotal')->GetRow('mydate=\'' . $date . '\'', '*') ?: [];
            $data['user'] = $db->setTable('T_GameStatisticUser')->GetRow('mydate=\'' . $date . '\'', '*') ?: [];
            $data['out'] = $db->setTable('T_GameStatisticPayOut')->GetRow('mydate=\'' . $date . '\'', '*') ?: [];
            if (!empty($data['pay'])) {
                $data['pay']['totalpay'] = FormatMoney($data['pay']['totalpay']);
                $data['pay']['manual_recharge'] = FormatMoney($data['pay']['manual_recharge']);
                $data['pay']['newuserpay'] = FormatMoney($data['pay']['newuserpay']);
                $data['pay']['paygift'] = FormatMoney($data['pay']['paygift']);
                $data['pay']['first_chargemoney'] = FormatMoney($data['pay']['first_chargemoney']);
            }
            if (!empty($data['out'])) {

                $data['out']['apply_payout'] = FormatMoney($data['out']['apply_payout']);
                $data['out']['totalpayout'] = FormatMoney($data['out']['totalpayout']);
            }
            if (!empty($data['system'])) {
                $data['system']['totalyk'] = FormatMoney($data['system']['totalyk']);
                $data['system']['totaltax'] = FormatMoney($data['system']['totaltax']);
                $data['system']['totalwage'] = FormatMoney($data['system']['totalwage']);
                $data['system']['totalwin'] = FormatMoney($data['system']['totalwin']);
                $data['system']['totalwater'] = FormatMoney($data['system']['totalwater']);
                $data['system']['TotalMailCoin'] = FormatMoney($data['system']['TotalMailCoin']);


                $data['system']['TotalTigerSlotsRuning'] = FormatMoney($data['system']['TotalTigerSlotsRuning']);
                $data['system']['TotalTigerSlotsWin'] = FormatMoney($data['system']['TotalTigerSlotsWin']);
                $data['system']['TotalGameFish'] =FormatMoney($data['system']['TotalGameFish']);
                $data['system']['TotalGameCrah'] =FormatMoney($data['system']['TotalGameCrah']);
                $data['system']['TotalGameMine'] =FormatMoney($data['system']['TotalGameMine']);
                //$data['system']['TotalGMPoint'] = FormatMoney($data['system']['TotalGMPoint']);


//                $threegamedata =['1200'=>0,'23600'=>0,'23800'=>0];
//                if(!empty($data['system']['TotalFishCrash'])){
//                    $three_data =json_decode($data['system']['TotalFishCrash'],true);
//                    if(isset($three_data)){
//                        foreach ($three_data as $k=>$v){
//                            $threegamedata[$v['roomid']] =FormatMoney($v['totalrunning']);
//                        }
//                    }
//                }
            } else {
                $data['system']['totalwin'] = 0;
                $data['system']['totaltax'] = 0;
            }

            if (isset($data['pay']['channel_payJson'])) {
                $data['recharge'] = json_decode($data['pay']['channel_payJson'], 1);
            } else {
                $data['recharge'] = [];
            }
            if (!isset($data['pay']['totalpay'])) {
                $data['pay']['totalpay'] = 0;
            }
            if (!isset($data['pay']['manual_recharge'])) {
                $data['pay']['manual_recharge'] = 0;
            }

            if (!isset($data['out']['totalpayout'])) {
                $data['out']['totalpayout'] = 0;
            }
            if (isset($data['user']['abondonuserjson'])) {
                $data['user']['abondonuser'] = json_decode($data['user']['abondonuserjson'], 1)[0];
            } else {
                $data['user']['abondonuser'] = [];
            }

            if (isset($data['user']['normaluserjson'])) {
                $data['user']['normaluser'] = json_decode($data['user']['normaluserjson'], 1)[0];
            } else {
                $data['user']['normaluser'] = [];
            }
            if (isset($data['user']['agentuserjson'])) {
                $data['user']['agentuser'] = json_decode($data['user']['agentuserjson'], 1)[0];
            } else {
                $data['user']['agentuser'] = [];
            }

            $data['activity'] = [];
            $db = new \app\model\ActivityRecord();
            // $activity_arr = [
            //     '54'=>lang('手机绑定领取'),
            //     '15,59,60'=>lang('签到领取'),
            //     '61'=>lang('VIP特权领取-升级'),
            //     '101'=>lang('vip周领取'),
            //     '102'=>lang('vip月领取'),
            //     '67'=>lang('周卡领取'),
            //     '68'=>lang('月卡领取'),
            // ];
            $fields = 'ReceiveAmt,ReceiveTimes';
            $data['activity'][54] = $db->getTableRow('View_ActivityReceiveSum','adddate=\'' . $date . '\' and ChangeType=54', $fields);

            $data['activity'][15] = $db->getTableRow('View_ActivityReceiveSum','adddate=\'' . $date . '\' and ChangeType in (15,59,60)', $fields);

            $data['activity'][30] = $db->getTableRow('View_ActivityReceiveSum','adddate=\'' . $date . '\' and ChangeType=30', $fields);

            $data['activity'][61] = $db->getTableRow('View_ActivityReceiveSum','adddate=\'' . $date . '\' and ChangeType=61', $fields) ;
            $data['activity'][101] = $db->getTableRow('View_ActivityReceiveSum','adddate=\'' . $date . '\' and ChangeType=101', $fields) ;
            $data['activity'][102] = $db->getTableRow('View_ActivityReceiveSum','adddate=\'' . $date . '\' and ChangeType=102', $fields) ;
            $data['activity'][67] = $db->getTableRow('View_ActivityReceiveSum','adddate=\'' . $date . '\' and ChangeType=67', $fields);
            $data['activity'][68] = $db->getTableRow('View_ActivityReceiveSum','adddate=\'' . $date . '\' and ChangeType=68', $fields);
            $data['activity'][77] = $db->getTableRow('View_ActivityReceiveSum','adddate=\'' . $date . '\' and ChangeType=77', $fields);

            $data['activity'][30]['ReceiveAmt'] = FormatMoney($data['activity'][30]['ReceiveAmt']);
            $data['activity'][54]['ReceiveAmt'] = FormatMoney($data['activity'][54]['ReceiveAmt']);
            $data['activity'][15]['ReceiveAmt'] = FormatMoney($data['activity'][15]['ReceiveAmt']);
            $data['activity'][61]['ReceiveAmt'] = FormatMoney($data['activity'][61]['ReceiveAmt']);
            $data['activity'][101]['ReceiveAmt'] = FormatMoney($data['activity'][101]['ReceiveAmt']);
            $data['activity'][102]['ReceiveAmt'] = FormatMoney($data['activity'][102]['ReceiveAmt']);
            $data['activity'][67]['ReceiveAmt'] = FormatMoney($data['activity'][67]['ReceiveAmt']);
            $data['activity'][68]['ReceiveAmt'] = FormatMoney($data['activity'][68]['ReceiveAmt']);
            $data['activity'][77]['ReceiveAmt'] = FormatMoney($data['activity'][77]['ReceiveAmt']);


            //彩金
            $data['cj'] = [];
            $data['cj'][11] = $db->getTableRow('View_ActivityReceiveSum','adddate=\'' . $date . '\' and ChangeType=11', $fields);
            $data['cj'][54] = $db->getTableRow('View_ActivityReceiveSum','adddate=\'' . $date . '\' and ChangeType=54', $fields);
            $data['cj'][72] = $db->getTableRow('View_ActivityReceiveSum','adddate=\'' . $date . '\' and ChangeType=72', $fields);

            $data['cj'][65] = $db->getTableRow('View_ActivityReceiveSum','adddate=\'' . $date . '\' and ChangeType=65', $fields);
            $data['cj'][66] = $db->getTableRow('View_ActivityReceiveSum','adddate=\'' . $date . '\' and ChangeType=66', $fields);
            $data['cj'][69] = $db->getTableRow('View_ActivityReceiveSum','adddate=\'' . $date . '\' and ChangeType=69', $fields);

            $data['cj'][11]['ReceiveAmt'] = FormatMoney($data['cj'][11]['ReceiveAmt']);
            $data['cj'][54]['ReceiveAmt'] = FormatMoney($data['cj'][54]['ReceiveAmt']);
            $data['cj'][72]['ReceiveAmt'] = FormatMoney($data['cj'][72]['ReceiveAmt']);

            $data['cj'][65]['ReceiveAmt'] = FormatMoney($data['cj'][65]['ReceiveAmt']);
            $data['cj'][66]['ReceiveAmt'] = FormatMoney($data['cj'][66]['ReceiveAmt']);
            $data['cj'][69]['ReceiveAmt'] = FormatMoney($data['cj'][69]['ReceiveAmt']);

            $this->assign('data', $data);
            return $this->fetch();
        }
    }

    //数据汇总月报
    public function totalMonth()
    {
        $action = $this->request->param('action');
        if ($action == 'list') {
        } else {

            $date = $this->request->param('date') ?: date('Y-m');
            $action = $this->request->param('action');
            if ($action == 'check') {
                if (strtotime($date) > sysTime()) {
                    return ['code' => 0, 'msg' => 'time error'];
                } else {
                    return ['code' => 0];
                }
            }
            $db = new GameOCDB();
            $data = [];
            //在线人数
            $data['online'] = $this->GetOnlineUserlist2('onlinenum') ?: 0;
            $data['pay'] = $db->setTable('view_GameStatisticPay')->GetRow('mydate=\'' . $date . '\'', '*') ?: [];
            $data['recharge'] = $db->setTable('View_PayChannelMonth')->getListTableAll('addtime=\'' . $date . '\'', '*') ?: [];
            $data['out'] = $db->setTable('view_GameStatisticPayOut')->GetRow('mydate=\'' . $date . '\'', '*') ?: [];
            $data['system'] = $db->setTable('view_GameStatisticTotal')->GetRow('mydate=\'' . $date . '\'', '*') ?: [];
            $data['user'] = $db->setTable('view_GameStatisticUser')->GetRow('mydate=\'' . $date . '\'', '*') ?: [];
            if (!empty($data['pay'])) {
                $data['pay']['totalpay'] = FormatMoney($data['pay']['totalpay']);
                $data['pay']['manual_recharge'] = FormatMoney($data['pay']['manual_recharge']);
                $data['pay']['newuserpay'] = FormatMoney($data['pay']['newuserpay']);
                $data['pay']['paygift'] = FormatMoney($data['pay']['paygift']);
                $data['pay']['first_chargemoney'] = FormatMoney($data['pay']['first_chargemoney']);
            }

            if (!empty($data['out'])) {
                $data['out']['apply_payout'] = FormatMoney($data['out']['apply_payout']);
                $data['out']['totalpayout'] = FormatMoney($data['out']['totalpayout']);
            }
            if (!empty($data['system'])) {
                $data['system']['totalyk'] = FormatMoney($data['system']['totalyk']);
                $data['system']['totaltax'] = FormatMoney($data['system']['totaltax']);
                $data['system']['totalwage'] = FormatMoney($data['system']['totalwage']);
                $data['system']['totalwin'] = FormatMoney($data['system']['totalwin']);
                $data['system']['totalwater'] = FormatMoney($data['system']['totalwater']);
                $data['system']['TotalMailCoin'] = FormatMoney($data['system']['TotalMailCoin']);

                $data['system']['TotalTigerSlotsRuning'] = FormatMoney($data['system']['TotalTigerSlotsRuning']);
                $data['system']['TotalTigerSlotsWin'] = FormatMoney($data['system']['TotalTigerSlotsWin']);
                $data['system']['TotalGameFish'] =FormatMoney($data['system']['TotalGameFish']);
                $data['system']['TotalGameCrah'] =FormatMoney($data['system']['TotalGameCrah']);
                $data['system']['TotalGameMine'] =FormatMoney($data['system']['TotalGameMine']);

            } else {
                $data['system']['totalwin'] = 0;
                $data['system']['totaltax'] = 0;
            }
            if (!isset($data['pay']['totalpay'])) {
                $data['pay']['totalpay'] = 0;
            }
            if (!isset($data['pay']['manual_recharge'])) {
                $data['pay']['manual_recharge'] = 0;
            }
            if (!isset($data['out']['totalpayout'])) {
                $data['out']['totalpayout'] = 0;
            }
            // $data['user']['abondonuser'] = json_decode($data['user']['abondonuserjson'],1);
            // $data['user']['normaluser'] = json_decode($data['user']['normaluserjson'],1);
            // $data['user']['agentuser'] = json_decode($data['user']['agentuserjson'],1);
            $data['activity'] = [];
            $db = new \app\model\ActivityRecord();
            // $activity_arr = [
            //     '54'=>lang('手机绑定领取'),
            //     '15,59,60'=>lang('签到领取'),
            //     '61'=>lang('VIP特权领取-升级'),
            //     '101'=>lang('vip周领取'),
            //     '102'=>lang('vip月领取'),
            //     '67'=>lang('周卡领取'),
            //     '68'=>lang('月卡领取'),
            // ];
            $fields = 'sum(ReceiveAmt) as ReceiveAmt,sum(ReceiveTimes) as ReceiveTimes';
            $end_date = date('Y-m-d', strtotime("$date +1 month -1 day"));
            $data['activity'][54] =$db->getTableRow('View_ActivityReceiveSum','adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=54', $fields);

            $data['activity'][30] = $db->getTableRow('View_ActivityReceiveSum','adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=30', $fields);

            $data['activity'][15] = $db->getTableRow('View_ActivityReceiveSum','adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType in (15,59,60)', $fields);
            $data['activity'][61] = $db->getTableRow('View_ActivityReceiveSum','adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=61', $fields);
            $data['activity'][101] = $db->getTableRow('View_ActivityReceiveSum','adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=101', $fields);
            $data['activity'][102] = $db->getTableRow('View_ActivityReceiveSum','adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=102', $fields);
            $data['activity'][67] = $db->getTableRow('View_ActivityReceiveSum','adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=67', $fields);
            $data['activity'][68] = $db->getTableRow('View_ActivityReceiveSum','adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=68', $fields);
            $data['activity'][77] = $db->getTableRow('View_ActivityReceiveSum','adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=77', $fields);

            $data['activity'][30]['ReceiveAmt'] = FormatMoney($data['activity'][30]['ReceiveAmt']);
            $data['activity'][54]['ReceiveAmt'] = FormatMoney($data['activity'][54]['ReceiveAmt']);
            $data['activity'][15]['ReceiveAmt'] = FormatMoney($data['activity'][15]['ReceiveAmt']);
            $data['activity'][61]['ReceiveAmt'] = FormatMoney($data['activity'][61]['ReceiveAmt']);
            $data['activity'][101]['ReceiveAmt'] = FormatMoney($data['activity'][101]['ReceiveAmt']);
            $data['activity'][102]['ReceiveAmt'] = FormatMoney($data['activity'][102]['ReceiveAmt']);
            $data['activity'][67]['ReceiveAmt'] = FormatMoney($data['activity'][67]['ReceiveAmt']);
            $data['activity'][68]['ReceiveAmt'] = FormatMoney($data['activity'][68]['ReceiveAmt']);
            $data['activity'][77]['ReceiveAmt'] = FormatMoney($data['activity'][77]['ReceiveAmt']);

            //彩金
            $data['cj'] = [];
            $data['cj'][11] = $db->getTableRow('View_ActivityReceiveSum','adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=11', $fields);
            $data['cj'][54] = $db->getTableRow('View_ActivityReceiveSum','adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=54', $fields);
            $data['cj'][72] = $db->getTableRow('View_ActivityReceiveSum','adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=72', $fields);

            //统计
            $data['cj'][65] = $db->getTableRow('View_ActivityReceiveSum','adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=65', $fields);
            $data['cj'][66] = $db->getTableRow('View_ActivityReceiveSum','adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=66', $fields);
            $data['cj'][69] = $db->getTableRow('View_ActivityReceiveSum','adddate>=\'' . $date . '-01\'and adddate<=\'' . $end_date . '\' and ChangeType=69', $fields);

            $data['cj'][11]['ReceiveAmt'] = FormatMoney($data['cj'][11]['ReceiveAmt']);
            $data['cj'][54]['ReceiveAmt'] = FormatMoney($data['cj'][54]['ReceiveAmt']);
            $data['cj'][72]['ReceiveAmt'] = FormatMoney($data['cj'][72]['ReceiveAmt']);

            $data['cj'][65]['ReceiveAmt'] = FormatMoney($data['cj'][65]['ReceiveAmt']);
            $data['cj'][66]['ReceiveAmt'] = FormatMoney($data['cj'][66]['ReceiveAmt']);
            $data['cj'][69]['ReceiveAmt'] = FormatMoney($data['cj'][66]['ReceiveAmt']);

            $this->assign('data', $data);

            return $this->fetch();
        }
    }



    //系统盈利报表
    public function totalProfit()
    {
        $action = $this->request->param('action');
        if ($action == 'list') {
            $start_date = $this->request->param('start_date') ?: date('Y-m-d');
            $end_date = $this->request->param('end_date') ?: date('Y-m-d');
            $end_date = date('Y-m-d', strtotime($end_date) + 86400);

            $db = new GameOCDB();
            $where = '';
            if ($start_date != '') {
                $where .= 'and mydate>=\'' . $start_date . '\'';
            }
            if ($end_date != '') {
                $where .= 'and mydate<\'' . $end_date . '\'';
            }
            $data = $db->setTable('T_GameStatisticTotal')->GetPage($where, 'mydate desc', '*', 1);
            foreach ($data['list'] as $k => &$v) {
                $v['totalyk'] = FormatMoney($v['totalwin']);
                $v['totalwage'] = FormatMoney($v['totalwage']);
                $v['totaltax'] = FormatMoney($v['totaltax']);
                $v['totalsystem'] = round($v['totaltax'] + $v['totalyk'], 3);
            }
            unset($v);
            return $this->apiJson($data);
        } else {
            return $this->fetch();
        }
    }


    public function dailyProfit(){
        $action = $this->request->param('action');
        $date = $this->request->param('date');
        if ($action == 'list') {
            $start_date = $date.'-01';
            $end_date = strtotime("$start_date +1 month -1 day");
            $end_date = date('Y-m-d',$end_date);
            $db = new GameOCDB();
            $where = " and datediff(mm,mydate,'$start_date')=0 ";
            $data = $db->setTable('T_GameStatisticTotal')->GetPage($where, 'mydate asc', '*', 1);
            $earn_rate = config('site.thirdrate');
            $earn_rate = bcdiv($earn_rate['earnrate'],100,2);
            foreach ($data['list'] as $k => &$v) {
                $v['totalyk'] = FormatMoney($v['totalwin']);
                $v['totaltax'] = FormatMoney($v['totaltax']);
                $v['totalsystem'] = round($v['totaltax'] + $v['totalyk'], 3);
                $v['totalprofit'] = bcmul($v['totalsystem'], $earn_rate, 2);
                $v['online'] = config('record_start_time');
                $v['rate'] = 1;
                $v['systemrate'] = $earn_rate;
            }
            unset($v);
            return $this->apiJson($data);
        }
        else {
            $this->assign('month',$date);
            return $this->fetch();
        }
    }


    ///盈利报表
    public function profitStatement()
    {
        $date = $this->request->param('date');
        if(empty($date))
            $date = date('Y-m');
        $db = new GameOCDB();
        $where = '  mydate=\'' . $date . '\'';
        $result = $db->getTableList('View_GameProfitStatement', $where, 1, 1);
        $earn_rate = config('site.thirdrate');
        $earn_rate = bcdiv($earn_rate['earnrate'],100,2);
        if (count($result['list']) > 0) {
            $data = $result['list'][0];
            $data['totalwin'] = FormatMoney($data['totalwin']);
            $data['totaltax'] = FormatMoney($data['totaltax']);

            $data['totalsystem'] = round($data['totaltax'] + $data['totalwin'], 3);

            $data['totalprofit'] = bcmul($data['totalsystem'], $earn_rate, 2);
            $data['online'] = config('record_start_time');
            $data['rate'] = 1;
            $data['systemrate'] = $earn_rate;
        }
        if ($this->request->isAjax()) {
            if (count($result['list']) > 0) {
                $data['onlinedata'] = config('record_start_time');
                return $this->successJSON($data);
            }
            else
            {
                return $this->failJSON(lang('该月份没有任何数据'));
            }

        }

        $this->assign('onlinedata', config('record_start_time'));
        $this->assign('data', $data);
        $this->assign('thismonth', date('Y-m'));
        return $this->fetch();
    }


    //游戏生态监控
    public function ecologMonitor(){
        $MonitorType   = $this->request->param('MonitorType');
        $action = $this->request->param('action');
        if ($action == 'list') {
            $date   = $this->request->param('date');
            $limit    = $this->request->param('limit')?:15;
            $where = '1=1';
            if ($date != '') {
                $where .= ' and date=\''.$date.'\'';
            }
            if ($MonitorType != '') {
                $where .= ' and MonitorType='.$MonitorType;
            }
            $field = "*";
            $order = "date desc";
            $m = new GameOCDB();
            $data = $m->getTableObject('T_DailyMonitor')
                ->where($where)
                ->field($field)
                ->order($order)
                ->paginate($limit)
                ->toArray();
            if (empty($data)) {
                return $this->apiReturn(0, [], 'success', 0);
            }
            foreach ($data['data'] as $key => &$val) {
                $val['GameRTP'] = $val['GameRTP']/1;
                $val['TotalDayConsume'] = bcdiv($val['TotalDayConsume'], bl,3);
                $val['GameCoinProduct'] = bcdiv($val['GameCoinProduct'], bl,3);
                $val['ColorCoinProduct'] = bcdiv($val['ColorCoinProduct'], bl,3);
                $val['TotalRecharge'] = bcdiv($val['TotalRecharge'], bl,3);
                $val['TotalWithDraw'] = bcdiv($val['TotalWithDraw'], bl,3);
                $val['TotalProfit'] = bcdiv($val['TotalProfit'], bl,3);
                $val['GameWeath'] = bcdiv($val['GameWeath'], bl,3);
                $val['GameProfitLose'] = bcdiv($val['GameProfitLose'], bl,3);
            }
            return $this->apiReturn(0, $data['data'], 'success', $data['total']);
        }
        $this->assign('MonitorType',$MonitorType);
        return $this->fetch();
    }


}