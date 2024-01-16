<?php


namespace app\model;

use app\admin\controller\Main;
use redis\Redis;
use think\Exception;
use think\exception\PDOException;

class GameOCDB extends BaseModel
{
//    protected $table = 'T_GameDetail';
    protected $page;
    protected $pageSize;
    protected $start;
    protected $end;

    /**
     * UserDB.
     * @param string $tableName 连接的数据表
     */
    public function __construct($tableName = '', $read_from_master = false)
    {
        $this->page = input('page', 1);
        $this->pageSize = input('limit', 15);
        $this->start = input('strartdate', date('Y-m-d'));
        $this->end = input('enddate', date('Y-m-d'));
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->GameOC, $read_from_master);
    }

    /**
     * @return $this
     */
    public function TViewRoomTax()
    {
        $this->table = 'View_RoomTax';
        return $this;
    }

    /**
     * 游戏盈亏结算
     */
    public function getGameRGDlist(): array
    {
        $this->table = 'View_RoomTax';
        $start = input('strartdate', date('Y-m-d'));
        $end = input('enddate', date('Y-m-d'));
        $orderby = input('orderby', 'AddTime');
        $orderType = input('orderytpe', 'desc');
        $kindid = trim(input('kindid')) ? trim(input('kindid')) : 0;
        $roomid = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $where = "";
        if ($start != null) $where .= " AND AddTime >= '" . $start . "'";
        if ($end != null) $where .= " AND AddTime <= '" . $end . "'";
        // if ($start != null && $end != null) $where = "AND AddTime BETWEEN '$start' AND '$end'";
        if ($kindid > 0) $where .= " and  KindID =" . $kindid;
        if ($roomid > 0) $where .= " and RoomId=" . $roomid;
        if ($orderby == 'percent') {
            $orderby = 'WinScore';
        }
        if ($orderby == 'WinRate') {
            $orderby = 'WinNum';
        }

        $result = $this->GetPage($where, "$orderby $orderType", '*,(CASE WHEN RoundBets=0 THEN 0 ELSE (WinScore*1.00/RoundBets) END) as KillRate', true);
        $totol = $this->GetRow('1=1 ' . $where,
            " ISNULL(SUM(GCount),0) GCount,ISNULL(SUM(TotalNum),0) GNum,ISNULL(SUM(Water),0) GameWater,"
            . " ISNULL(SUM(WinScore),0)Score,ISNULL(SUM(Tax),0)Tax,SUM(ISNULL(RoundBets,0))RoundBets");
        $total_WinRate = '0%';
        $total_killRate = '0%';
        if ($totol['RoundBets'] != 0) $total_WinRate = sprintf("%.2f", (($totol['RoundBets'] - $totol['Score']) / $totol['RoundBets']) * 100) . "%";
        //if ($totol['GameWater'] != 0)
        ConVerMoney($totol['RoundBets']);
        ConVerMoney($totol['Score']);
        //ConVerMoney($totol['DETax']);
        ConVerMoney($totol['Tax']);
        $total_Win = 0;
        if (isset($result['list']) && $result['list']) {
            foreach ($result['list'] as &$v) {
                if (empty($v['RoomName'])) {
                    switch ($v['RoomID']) {
                        case 37000:
                            $v['RoomName'] = 'Evo';
                            break;
                        case 38000:
                            $v['RoomName'] = 'PP';
                            break;
                        case 36000:
                            $v['RoomName'] = 'PG';
                            break;
                        case 39000:
                            $v['RoomName'] = 'JILI';
                            break;
                        case 39100:
                            $v['RoomName'] = 'kingmaker';
                            break;
                        case 39200:
                            $v['RoomName'] = 'CQ9';
                            break;
//                        case 39300:
//                            $v['RoomName'] = 'Haba';
//                            break;
                        case 39400:
                            $v['RoomName'] = 'JDB';
                            break;
                        case 40000:
                            $v['RoomName'] = 'Haba';
                            break;
                        case 41000:
                            $v['RoomName'] = 'HackSaw';
                            break;
                        case 42000:
                            $v['RoomName'] = 'YES!BinGo';
                            break;
                        case 45000:
                            $v['RoomName'] = 'TADA';
                            break;
                    }
                }
                $gamerate = sprintf("%.2f", $v['GameRate']);
                $v['GameRate'] = sprintf("%.2f", $v['GameRate']) . "%";
                if ($v['Water'] != 0) {
                    $v['KillRate'] = sprintf("%.2f", $v['KillRate'] * 100) . "%";
                } else {
                    $v['KillRate'] = '0%';
                }


                if ($v['TotalNum'] == null) $v['TotalNum'] = 1;
                ConVerMoney($v['Water']);
                ConVerMoney($v['RoundBets']);
                ConVerMoney($v['WinScore']);
                ConVerMoney($v['Tax']);
                if ($v['TotalNum'] > 0) {
                    $v['WinRate'] = sprintf("%.2f", ($v['WinNum'] / $v['TotalNum']) * 100);
                } else {
                    $v['WinRate'] = sprintf("%.2f", 0);
                }

                $total_Win += $v['WinScore'];
                $iswarning = $this->warningset($v['TotalNum'], $v['GCount'], $gamerate);
                $v['warning'] = $iswarning;
            }
            foreach ($result['list'] as &$v) {
                if ($total_Win > 0)
                    //$v["percent"] = ($v['WinScore'] + $v['DailyEggTax']) * 100 / $total_Win;
                    $v["percent"] = ($v['WinScore'] + $v['DailyEggTax']) * 100 / $total_Win;
                else
                    $v["percent"] = 0;
            }
            unset($v);
        }
        $result['other'] = [
            "total_Water" => $totol['RoundBets'],
            "total_Win" => $totol['Score'],
            "total_Tax" => $totol['Tax'],
            "total_WinRate" => $total_WinRate,
            //'total_killRate' => $total_killRate,
            'total_SystemWin' => $totol['Score'],
            "total_GCount" => $totol['GCount'],
            'total_Gnum' => $totol['GNum']
        ];
        $where2 = "1=1";
        if ($start != null) $where2 .= " AND mydate >= '" . $start . "'";
        if ($end != null) $where2 .= "AND mydate <= '" . $end . "'";

        $totalcharge = $this->getTableObject('T_Operator_GameStatisticPay')
            ->where($where2)
            ->value("sum(totalpayuser) totalpayuser") ?: 0;

        $result['other']['total_recharge'] = $totalcharge;

        $total_killRate = 0;
        if ($totalcharge > 0) {
            $total_killRate = sprintf("%.2f", ($totol['Score'] / $totalcharge) * 100) . "%";
        }
        $result['other']['total_killRate'] = $total_killRate;
        return $result;
    }


    /**
     * 游戏盈亏结算
     */
    public function getOperatorGameRGDlist(): array
    {
        $this->table = 'View_Operator_RoomTax';
        $start = input('strartdate', date('Y-m-d'));
        $end = input('enddate', date('Y-m-d'));
        $orderby = input('orderby', 'SortID');
        $orderType = input('orderytpe', 'asc');
        $kindid = trim(input('kindid')) ? trim(input('kindid')) : 0;
        $roomid = intval(input('roomid')) ? intval(input('roomid')) : 0;

        if (session('merchant_OperatorId') && request()->module() == 'merchant') {
            $where = " AND OperatorId=" . session('merchant_OperatorId');
            // $room_data = (new \app\model\MasterDB())->getTableObject('T_OperatorGameType')->where('OperatorId', session('merchant_OperatorId'))->field('RedirectTypeId')->select() ?: [];

            // $roomid = [];
            // if (!empty($room_data)) {
            //     foreach ($room_data as $k => $v) {
            //         $roomid[] = $v['RedirectTypeId'];
            //     }
            //     $str_roomid = implode(',', $roomid);
            //     $where .= ' and RoomId in(' . $str_roomid . ')';
            // } else {
            //     $where .= ' and RoomId in(-1)';
            // }
        } else {
            $where = " ";
        }
        if ($roomid > 0) $where .= " and RoomId=" . $roomid;
        // var_dump($where);die();
        if ($start != null) $where .= " AND AddTime >= '" . $start . "'";
        if ($end != null) $where .= " AND AddTime <= '" . $end . "'";
        // if ($start != null && $end != null) $where .= "AND AddTime BETWEEN '$start' AND '$end'";
        if ($kindid > 0) $where .= " and  KindID =" . $kindid;

        if ($orderby == 'percent') {
            $orderby = 'WinScore';
        }
        if ($orderby == 'WinRate') {
            $orderby = 'WinNum';
        }
        $result = $this->GetPage($where, "$orderby $orderType", '*,(CASE WHEN Water=0 THEN 0 ELSE ((WinScore-Tax)*1.00/Water) END) as KillRate', true);
        $totol = $this->GetRow('1=1 ' . $where,
            " ISNULL(SUM(GCount),0) GCount,ISNULL(SUM(TotalNum),0) GNum,ISNULL(SUM(Water),0) GameWater,"
            . " ISNULL(SUM(WinScore),0)Score,ISNULL(SUM(Tax),0)Tax");
        $total_WinRate = '0%';
        $total_killRate = '0%';
        if ($totol['GameWater'] != 0) $total_WinRate = sprintf("%.2f", (($totol['GameWater'] - $totol['Score']) / $totol['GameWater']) * 100) . "%";
        //if ($totol['GameWater'] != 0)
        ConVerMoney($totol['GameWater']);
        ConVerMoney($totol['Score']);
        //ConVerMoney($totol['DETax']);
        ConVerMoney($totol['Tax']);
        $total_Win = 0;
        if (isset($result['list']) && $result['list']) {
            foreach ($result['list'] as &$v) {
                if (empty($v['RoomName'])) {
                    switch ($v['RoomID']) {
                        case 37000:
                            $v['RoomName'] = 'Evo';
                            break;
                        case 38000:
                            $v['RoomName'] = 'PP';
                            break;
                        case 36000:
                            $v['RoomName'] = 'PG';
                            break;
                        case 39000:
                            $v['RoomName'] = 'JILI';
                            break;
                        case 39100:
                            $v['RoomName'] = 'kingmaker';
                            break;
                        case 39200:
                            $v['RoomName'] = 'CQ9';
                            break;
//                        case 39300:
//                            $v['RoomName'] = 'Haba';
//                            break;
                        case 39400:
                            $v['RoomName'] = 'JDB';
                            break;
                        case 40000:
                            $v['RoomName'] = 'Haba';
                            break;
                        case 41000:
                            $v['RoomName'] = 'HackSaw';
                            break;
                        case 42000:
                            $v['RoomName'] = 'YES!BinGo';
                            break;
                        case 44000:
                            $v['RoomName'] = 'FCGame';
                            break;

                        case 45000:
                            $v['RoomName'] = 'TadaGame';
                            break;
                    }
                }


                if(($v['Water']-$v['WinScore'] + $v['Tax']) < 0 || $v['Water'] < 0){
                    $v['GameRate'] = 0;
                }else{
                    $v['GameRate'] = ($v['Water']-$v['WinScore'] + $v['Tax'])/$v['Water'] * 100;
                }
                $gamerate = sprintf("%.2f", $v['GameRate']);
                $v['GameRate'] = sprintf("%.2f", $v['GameRate']) . "%";
                if ($v['Water'] != 0) {
                    $v['KillRate'] = sprintf("%.2f", $v['KillRate'] * 100) . "%";
                } else {
                    $v['KillRate'] = '0%';
                }
                $v['WinScore'] = $v['WinScore'] - $v['Tax'];
                if ($v['TotalNum'] == null) $v['TotalNum'] = 1;
                ConVerMoney($v['Water']);
                ConVerMoney($v['WinScore']);
                ConVerMoney($v['Tax']);
                $v['WinRate'] = sprintf("%.2f", ($v['WinNum'] / $v['TotalNum']) * 100);
                $total_Win += $v['WinScore'];
                $iswarning = $this->warningset($v['TotalNum'], $v['GCount'], $gamerate);
                $v['warning'] = $iswarning;
            }
            foreach ($result['list'] as &$v) {
                if ($total_Win > 0)
                    //$v["percent"] = ($v['WinScore'] + $v['DailyEggTax']) * 100 / $total_Win;
                    $v["percent"] = ($v['WinScore'] + $v['DailyEggTax']) * 100 / $total_Win;
                else
                    $v["percent"] = 0;
            }
            unset($v);
        }
        $result['other'] = [
            "total_Water" => $totol['GameWater'],
            "total_Win" => $totol['Score'],
            "total_Tax" => $totol['Tax'],
            "total_WinRate" => $total_WinRate,
            //'total_killRate' => $total_killRate,
            'total_SystemWin' => $totol['Score'],
            "total_GCount" => $totol['GCount'],
            'total_Gnum' => $totol['GNum']
        ];
        $where2 = "1=1";
        if ($start != null) $where2 .= " AND addDate >= '" . $start . "'";
        if ($end != null) $where2 .= "AND addDate <= '" . $end . "'";

        $totalcharge = $this->getTableObject('View_TotalDayScore')
            ->where($where2)
            ->value("sum(payMoney) payMoney") ?: 0;

        $result['other']['total_recharge'] = $totalcharge;

        $total_killRate = 0;
        if ($totalcharge > 0) {
            $total_killRate = sprintf("%.2f", ($totol['Score'] / $totalcharge) * 100) . "%";
        }
        $result['other']['total_killRate'] = $total_killRate;
        return $result;
    }




    ///判断是否预警
    /// 1. RTP >= 500%
    //2. 总局数大于100 且 RTP >= 300 且 人数 > 5
    //3. 总局数大于500 且 RTP >= 150
    //4. 总局数大于1000 且 RTP >= 120
    //5. 总局数大于5000 且 RTP >= 105
    private function warningset($person, $drawcount, $rtp)
    {
        if ($rtp >= 500) {
            return true;
        }
        if ($drawcount >= 100 && $rtp >= 300 && $person > 5) {
            return true;
        }
        if ($drawcount >= 500 && $rtp >= 150) {
            return true;
        }
        if ($drawcount >= 1000 && $rtp >= 120) {
            return true;
        }
        if ($drawcount >= 5000 && $rtp >= 105) {
            return true;
        }
        return false;
    }

    /**
     * @param false $graph
     * @param false $debug
     * @return array
     */
    public function GetOnlinelist($graph = false, $debug = false)
    {
        $limit = intval(input('limit')) ? intval(input('limit')) : 10;
        $page = intval(input('page')) ? intval(input('page')) : 1;
        $start = input('start') ? input('start') : date('Y-m-d');
        $end = input('end') ? input('end') : $start;
        $order = "addtime desc";

        if ($graph) {
            $limit = 14400;
            $order = "addtime asc";
        }
        $table = 'T_GameOnline';
        $where = "";

        $sqlExec = "exec Proc_GetPageData '$table','*','$where', '$order','','$start','$end', $page , $limit";
        if ($debug) $result = ['sql' => $sqlExec, 'debug' => $debug];
        try {
            $res = $this->getTableQuery($sqlExec);
            $result['list'] = $res[1];
            $result['count'] = $res[0][0]['count'];
            foreach ($result['list'] as &$row) ConVerDate($row['addtime']);
            if ($graph) {
                //昨天的数据
                $start = date('Y-m-d', strtotime($start) - 86400);
                $end = date('Y-m-d', strtotime($end) - 86400);
                $sqlExec = "exec Proc_GetPageData '$table','*','$where', '$order','','$start','$end', $page , $limit";
                $res = $this->getTableQuery($sqlExec);
                $result['yes'] = $res[1];
            }
        } catch (Exception $exception) {
            $result['list'] = [];
            $result['yes'] = [];
        }

        return $result;
    }


    /**
     * @param false $graph
     * @param false $debug
     * @return array
     */
    public function GetOnlinelistByDate($date)
    {

        $start = $end = $date;
        $order = "addtime asc";
        $limit = 14400;
        $page = 1;
        $table = 'T_GameOnline';
        $where = "";

        $sqlExec = "exec Proc_GetPageData '$table','*','$where', '$order','','$start','$end', $page , $limit";
        $res = $this->getTableQuery($sqlExec);
        if (isset($res[1])) {
            $result['hallonline'] = array_column($res[1], 'hallonline');
            $result['roomonline'] = array_column($res[1], 'roomonline');
        } else {
            $result['hallonline'] = [];
            $result['roomonline'] = [];
        }

        return $result;
    }

    /**
     * 游戏日志
     * @param int $page
     * @param int $limit
     * @param string $where
     * @param string $strartdate
     * @param string $enddate
     * @param int $RoomID 在join 外添加 所以不在where里
     * @param int $bl 比率
     * @param false $debug 是否输出SQL语句
     * @return array
     */
    public function GetGameRecord($debug = false)
    {
        $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
        $page = intval(input('page')) ? intval(input('page')) : 1;
        $limit = intval(input('limit')) ? intval(input('limit')) : 10;
        $RoomID = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $OperatorId = input('OperatorId') ?: '';
        if (request()->has('strartdate')) {
            $strartdate = input('strartdate') ?: config('record_start_time');
        } else {
            $strartdate = input('strartdate') ? input('strartdate') : date("Y-m-d") . ' 00:00:00';
        }
        if (strtotime($strartdate) < strtotime(config('record_start_time'))) {
            $strartdate = config('record_start_time');
        }
        $enddate = input('enddate') ? input('enddate') : date("Y-m-d") . ' 23:59:59';
        $winlost = input('winlost');

        $Account = input('mobile') ? input('mobile') : ''; //账号
        $where = "";

        $outAll = input('outall', false);
        if (input('Action') == 'exec' && $outAll == false) {
            $this->pageSize = 1;
        }
        if (!empty($Account)) $roleId = $this->GetUserIDByAccount($Account);
        if ($roleId > 0) $where .= " AND RoleID=$roleId";
        if ($winlost > -1) $where .= " AND ChangeType=$winlost";
        $where .= " and AddTime>=''" . $strartdate . "'' and AddTime<=''" . $enddate . "'' ";

        $join = 'LEFT JOIN  OM_MasterDB.dbo.T_GameRoomInfo B WITH (NOLOCK) ON A.ServerID=B.ServerID';
        if ($RoomID > 0) {
            $where .= " and ServerID=$RoomID";
        }

        if (session('merchant_OperatorId') && request()->module() == 'merchant') {
            $OperatorId = session('merchant_OperatorId');
            if ($OperatorId !== '') {
                $join .= " WHERE A.OperatorId=" . $OperatorId;
            }
        }elseif (!empty($OperatorId)){
            $join .= " WHERE A.OperatorId=" . $OperatorId;
        }
        $business_id = '';
        if (session('business_ProxyChannelId') && request()->module() == 'business') {
            $business_id = session('business_ProxyChannelId');
            if ($business_id != '') {
                $join .= ' LEFT JOIN CD_Account.dbo.T_Accounts C WITH (NOLOCK) ON A.RoleID=C.AccountID';
                $join .= " WHERE C.ProxyChannelId=" . $business_id;
            }
        }

        $begin = date('Y-m-d', strtotime($strartdate));
        $end = date('Y-m-d', strtotime($enddate));
        $result['count'] = 0;
        $field = "A.ID,A.ServerID,RoleID,SerialNumber,ChangeType,GameRoundRunning,AddTime,Tax,A.Money+GameRoundRunning AwardMoney," .
            "RoomID/10*10 KindID,RoomID,RoomName,A.Money,LastMoney,LastMoney+Tax-A.Money PreMoney,A.OperatorId,RoundBets";
        $table = 'dbo.T_UserGameChangeLogs';


        $order = input('orderfield', "A.AddTime");
        $ordertype = input('ordertype', 'desc');
        $order = "'$order $ordertype'";
        $sqlExec = "exec Proc_GetPageData '$table','$field','$where', $order,'$join','$begin','$end', $page , $limit";
        if ($debug) $result = ['sql' => $sqlExec, 'debug' => $debug];

        try {
            $res = $this->getTableQuery($sqlExec);
        } catch (Exception $exception) {
            $res['list'] = [];
        }

        if (count($res) == 2) {
            $result['list'] = $res[1];
            $result['count'] = $res[0][0]['count'];
            unset($res);
            $result['code'] = 0;
            foreach ($result['list'] as &$v) {
                if ($v['ServerID'] == 37000) {
                    $v['RoomID'] = 37000;
                    $v['RoomName'] = 'EvoLive';
                }
                if ($v['ServerID'] == 38000) {
                    $v['RoomID'] = 38000;
                    $v['RoomName'] = 'PP';
                }
                if ($v['ServerID'] == 36000) {
                    $v['RoomID'] = 36000;
                    $v['RoomName'] = 'PG';
                }
                if ($v['ServerID'] == 39000) {
                    $v['RoomID'] = 39000;
                    $v['RoomName'] = 'JILI';
                }
                if ($v['ServerID'] == 39100) {
                    $v['RoomID'] = 39100;
                    $v['RoomName'] = 'kingmaker';
                }
                if ($v['ServerID'] == 39200) {
                    $v['RoomID'] = 39200;
                    $v['RoomName'] = 'CQ9';
                }
                if ($v['ServerID'] == 40000) {
                    $v['RoomID'] = 40000;
                    $v['RoomName'] = 'Haba';
                }
                if ($v['ServerID'] == 39400) {
                    $v['RoomID'] = 39400;
                    $v['RoomName'] = 'Spribe';
                }
                if ($v['ServerID'] == 41000) {
                    $v['RoomID'] = 41000;
                    $v['RoomName'] = 'HackSaw';
                }
                if ($v['ServerID'] == 42000) {
                    $v['RoomID'] = 42000;
                    $v['RoomName'] = 'YES!BINGO';
                }
                if ($v['ServerID'] == 44000) {
                    $v['RoomID'] = 44000;
                    $v['RoomName'] = 'FCGame';
                }
                if ($v['ServerID'] == 45000) {
                    $v['RoomID'] = 45000;
                    $v['RoomName'] = 'TaDa';
                }
                if ($v['ServerID'] == 46000) {
                    $v['RoomID'] = 46000;
                    $v['RoomName'] = 'PPLive';
                }
                $v['AwardMoney'] = FormatMoney($v['RoundBets'] + $v['Money']);
                ConVerMoney($v['Money']);
                ConVerMoney($v['GameRoundRunning']);
                ConVerMoney($v['LastMoney']);
                ConVerMoney($v['PreMoney']);
                ConVerMoney($v['RoundBets']);
                ConVerMoney($v['Tax']);
                $v['FreeGame'] = lang('否');
                $v['RoomName'] = lang($v['RoomName']);
                $v['AddTime'] = substr($v['AddTime'], 0, 19);
                if (floatval($v['GameRoundRunning']) == 0) $v['FreeGame'] = lang('是');
                unset($v);
            }

        }
        return $result;
    }


    ///游戏日志统计
    public function GetGameRecordSum($debug = false)
    {
        $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
        $page = intval(input('page')) ? intval(input('page')) : 1;
        $limit = intval(input('limit')) ? intval(input('limit')) : 10;
        $RoomID = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $OperatorId = input('OperatorId') ?: '';
        if (request()->has('strartdate')) {
            $strartdate = input('strartdate') ?: config('record_start_time');
        } else {
            $strartdate = input('strartdate') ? input('strartdate') : date("Y-m-d") . ' 00:00:00';
        }
        if (strtotime($strartdate) < strtotime(config('record_start_time'))) {
            $strartdate = config('record_start_time');
        }
        $enddate = input('enddate') ? input('enddate') : date("Y-m-d") . ' 23:59:59';
        $winlost = input('winlost', '-1');
        $Account = input('mobile') ? input('mobile') : ''; //账号
        $where = "";
        $join = 'LEFT JOIN  OM_MasterDB.dbo.T_GameRoomInfo B WITH (NOLOCK) ON A.ServerID=B.ServerID';
        $outAll = input('outall', false);
        if (session('merchant_OperatorId') && request()->module() == 'merchant') {
            $OperatorId = session('merchant_OperatorId');
        }
        $business_id = '';
        if (session('business_ProxyChannelId') && request()->module() == 'business') {
            $business_id = session('business_ProxyChannelId');
        }
        if (!empty($Account)) $roleId = $this->GetUserIDByAccount($Account);
        if ($roleId > 0) $where .= " AND RoleID=$roleId";
        if ($winlost >= 0) $where .= " AND ChangeType=$winlost";
        // if ($RoomID > 0) $join .= " where ServerID=$RoomID";
        if ($OperatorId !== '') {
            $join = "LEFT JOIN CD_Account.dbo.T_Accounts C WITH (NOLOCK) ON A.RoleID=C.AccountID WHERE C.OperatorId=" . $OperatorId;
        }
        if ($business_id !== '') {
            $join = "LEFT JOIN CD_Account.dbo.T_Accounts C WITH (NOLOCK) ON A.RoleID=C.AccountID WHERE C.ProxyChannelId=" . $business_id;
        }
        if ($RoomID > 0) $where .= " and ServerID=$RoomID";
        $where .= " and AddTime>=''" . $strartdate . "'' and AddTime<=''" . $enddate . "'' ";

        $begin = date('Y-m-d', strtotime($strartdate));
        $end = date('Y-m-d', strtotime($enddate));
        $field = "Sum(GameRoundRunning) as GameRoundRunning,sum(AwardMoney) as AwardMoney,sum(UnAwardMoney) as UnAwardMoney,sum(TotalWin) as TotalWin,sum(TotalTax) TotalTax";
        $tablefield = 'isnull(sum(RoundBets),0) as GameRoundRunning,isnull(sum([Money]+[RoundBets]),0) as AwardMoney, isnull(sum(case when ([Money]+[RoundBets])=0 then RoundBets else 0 end),0) as UnAwardMoney, isnull(sum([Money]),0) as TotalWin,isnull(sum(Tax),0) as TotalTax';

        $table = 'dbo.T_UserGameChangeLogs';
        $sqlExec = "exec Proc_GetGameLogTotal '$table','$field','$tablefield','$join','$where','$begin','$end'";
        save_log('sql', $sqlExec);
        if ($debug) $result = ['sql' => $sqlExec, 'debug' => $debug];
        $result = [];
        try {
            $res = $this->getTableQuery($sqlExec);
            if ($res) {
                $result = $res[0][0];
                if (isset($result['GameRoundRunning'])) {
                    $result['GameRoundRunning'] = FormatMoney($result['GameRoundRunning']);
                }
                if (isset($result['AwardMoney'])) {
                    $result['AwardMoney'] = FormatMoney($result['AwardMoney']);
                }

                if (isset($result['UnAwardMoney'])) {
                    $result['UnAwardMoney'] = FormatMoney($result['UnAwardMoney']);
                }

                if (isset($result['TotalWin'])) {
                    $result['TotalWin'] = FormatMoney($result['TotalWin']);
                }
                if (isset($result['TotalTax'])) {
                    $result['TotalTax'] = FormatMoney($result['TotalTax']);
                }
            }
        } catch (Exception $exception) {
            $result = [];
        }
        return $result;
    }


    /**
     * 游戏日志
     * @param int $page
     * @param int $limit
     * @param string $where
     * @param string $strartdate
     * @param string $enddate
     * @param int $RoomID 在join 外添加 所以不在where里
     * @param int $bl 比率
     * @param false $debug 是否输出SQL语句
     * @return array
     */
    public function GetPackGameRecord($page, $limit, $where, $strartdate, $enddate, $RoomID = 0, $debug = false): array
    {
        $result['count'] = 0;
        $field = "LoginName,AccountName,RoleID,SerialNumber,ChangeType,GameRoundRunning,AddTime,Tax,PackageName,OperatorId,
        RoomID/10*10 KindID,RoomID,RoomName,A.Money,LastMoney,LastMoney+Tax-A.Money PreMoney";
        $table = 'dbo.T_UserGameChangeLogs';
        $join = 'LEFT JOIN  OM_MasterDB.dbo.T_GameRoomInfo B WITH (NOLOCK) ON A.ServerID=B.ServerID 
        LEFT JOIN CD_UserDB.dbo.View_Accountinfo C ON C.AccountID=RoleID';
        if ($RoomID > 0) $join .= " WHERE RoomID=$RoomID";
        $sqlExec = "exec Proc_GetPageData '$table','$field','$where', 'AddTime DESC','$join','$strartdate','$enddate', $page , $limit";
        if ($debug) $result = ['sql' => $sqlExec, 'debug' => $debug];
        $res = $this->getTableQuery($sqlExec);

        if (count($res) == 2) {
            $result['list'] = $res[1];
            $result['count'] = $res[0][0]['count'];
            unset($res);
            $result['code'] = 0;

            foreach ($result['list'] as &$v) {
                $v['AwardMoney'] = FormatMoney($v['GameRoundRunning'] + $v['Money']);
                ConVerMoney($v['Money']);//sprintf('%.2f', $v['Money'] * 1.00 / $bl);
                ConVerMoney($v['GameRoundRunning']);;
                ConVerMoney($v['LastMoney']);//sprintf('%.2f', $v['LastMoney'] * 1.00 / $bl);
                ConVerMoney($v['PreMoney']);//sprintf('%.2f', $v['PreMoney'] * 1.00 / $bl);
                ConVerMoney($v['Tax']); //sprintf('%.2f', $v['Tax'] * 1.00 / $bl);
                //sprintf('%.2f', $v['GameRoundRunning'] + $v['Money']);
                $v['FreeGame'] = lang('否');
                if (floatval($v['GameRoundRunning']) == 0) $v['FreeGame'] = lang('是');
                unset($v);
            }

        }
        return $result;
    }

    /**
     * 金币日志
     * @param $roomlist
     * @return array
     */
    public function GetGoldRecord(&$roomlist): array
    {
        $roleId = input('roleid', 0);
        if (request()->has('strartdate')) {
            $strartdate = input('strartdate') ?: config('record_start_time');
        } else {
            $strartdate = input('strartdate') ? input('strartdate') : date("Y-m-d") . ' 00:00:00';
        }
        if (strtotime($strartdate) < strtotime(config('record_start_time'))) {
            $strartdate = config('record_start_time');
        }
        $enddate = input('enddate') ? input('enddate') : date("Y-m-d") . ' 23:59:59';
        $changetype = input('changetype') ?: -1;
        $AccountName = input('AccountName');
        $NickName = input('NickName');
        $usertype = intval(input('usertype', -1));
        $OperatorId = input('OperatorId') ?: '';
        $ServerID = input('ServerID') ?: '';
        $where = "";

        $outAll = input('outall', false);
        if (input('Action') == 'exec' && $outAll == false) {
            $this->pageSize = 1;
        }
        $join = "LEFT JOIN CD_Account.dbo.T_Accounts B WITH (NOLOCK) ON A.RoleID=B.AccountID";
        $field = 'A.ID,RoleID,ServerID,ChangeType,Money,LastMoney,AddTime,Tax,Description';

        if (session('merchant_OperatorId') && request()->module() == 'merchant') {
            $OperatorId = session('merchant_OperatorId');
        }
        if ($OperatorId !== '') {
            $join .= " where  B.OperatorId=" . $OperatorId;
        }

        $business_id = '';
        if (session('business_ProxyChannelId') && request()->module() == 'business') {
            $business_id = session('business_ProxyChannelId');
        }

        if ($business_id !== '') {
            $join .= " where B.ProxyChannelId=" . $business_id;
        }


        if ($changetype > 0) $where .= " AND ChangeType=$changetype ";
        if ($roleId > 0) $where .= " AND roleId=$roleId ";
        if (!empty($ServerID)) $where .= " AND ServerID=''$ServerID''";
        if ($usertype >= 0) $join .= " AND  gmtype=" . $usertype;
        //外联 条件
        if (!empty($AccountName)) $join .= " AND AccountName=''$AccountName''";
        if (!empty($NickName)) $join .= " AND LoginName=''$NickName''";

        $where .= " and AddTime>=''" . $strartdate . "'' and AddTime<=''" . $enddate . "'' ";
        $begin = date('Y-m-d', strtotime($strartdate));
        $end = date('Y-m-d', strtotime($enddate));

        $table = 'dbo.T_BankWeathChangeLog';

        $sqlExec = "exec Proc_GetPageData '$table','$field','$where', 'AddTime DESC','$join','$begin','$end', $this->page , $this->pageSize";
        try {
            $result = $this->getTableQuery($sqlExec);
        } catch (Exception $exception) {
            $result['list'] = [];
        }

        $res['code'] = 0;
        $res['debug'] = true;
        $res["sql"] = $sqlExec;
        $changeType = config('bank_change_type');

        if (isset($result[1]) && $result[0][0]['count'] > 0) {
            $res['count'] = $result[0][0]['count'];
            $res['list'] = $result[1];
            foreach ($res['list'] as &$v) {
                ConVerMoney($v['Money']);
                ConVerMoney($v['Tax']);
                ConVerDate($v['AddTime']);
                ConVerMoney($v['LastMoney']);
                $v['ChangeName'] = '';
                foreach ($roomlist as $k2 => $v2) {
                    $serverID =& $v['ServerID'];
                    if ($serverID == $v2['RoomID']) {
                        $v['RoomName'] = $v2['RoomName'];
                        break;
                    }

                    if ($serverID == 25000) {
                        $v['RoomName'] = lang('捕鱼游戏');
                        break;
                    } else if ($serverID == 26000) {
                        $v['RoomName'] = lang('IMONE游戏');
                        break;
                    }
                    $v['RoomName'] = '';
                }
                foreach ($changeType as $k2 => $v2) {
                    if ($v['ChangeType'] == $k2) {
                        $v['ChangeName'] = $v2;
                        break;
                    }
                }
                if (in_array($v['ChangeType'], [3, 45, 51, 76])) {//转换负数
                    $v['Money'] = 0 - $v['Money'];
                }
                $v['ChangeName'] = lang($v['ChangeName']);
                if ($v['ChangeType'] != 22) {
                    $descript = str_replace('type 0', '白银', $v['Description']);
                    $descript = str_replace('type 1', '黄金', $descript);
                    $descript = str_replace('type 2', '钻石', $descript);
                    $v['RoomName'] = $descript;
                }

            }
            unset($v);
        }
        return $res;
    }


    //金币统计
    public function GetGoldRecordSum(): array
    {
        $roleId = input('roleid', 0);
        if (request()->has('strartdate')) {
            $strartdate = input('strartdate') ?: config('record_start_time');
        } else {
            $strartdate = input('strartdate') ? input('strartdate') : date("Y-m-d") . ' 00:00:00';
        }
        if (strtotime($strartdate) < strtotime(config('record_start_time'))) {
            $strartdate = config('record_start_time');
        }
        $enddate = input('enddate') ? input('enddate') : date("Y-m-d") . ' 23:59:59';
        $changetype = input('changetype') ?: -1;
        $AccountName = input('AccountName');
        $NickName = input('NickName');
        $usertype = intval(input('usertype', -1));
        $OperatorId = input('OperatorId') ?: '';
        $where = "";

        $outAll = input('outall', false);
        $join = "LEFT JOIN CD_Account.dbo.T_Accounts B WITH (NOLOCK) ON A.RoleID=B.AccountID";
        if (session('merchant_OperatorId') && request()->module() == 'merchant') {
            $OperatorId = session('merchant_OperatorId');
        }
        if ($OperatorId !== '') {
            $join .= " where A.OperatorId=" . $OperatorId;
        }


        $business_id = '';
        if (session('business_ProxyChannelId') && request()->module() == 'business') {
            $business_id = session('business_ProxyChannelId');
        }


        if ($business_id !== '') {
            $join .= " where B.ProxyChannelId=" . $business_id;
        }

        if ($changetype > 0) $where .= "AND ChangeType=$changetype ";
        if ($roleId > 0) $where .= "AND roleId=$roleId ";
        if ($usertype >= 0) $join .= " AND  gmtype=" . $usertype;
        //外联 条件
        if (!empty($AccountName)) $join .= " AND AccountName=''$AccountName''";
        if (!empty($NickName)) $join .= " AND LoginName=''$NickName''";

        $where .= " and AddTime>=''" . $strartdate . "'' and AddTime<=''" . $enddate . "'' ";
        $begin = date('Y-m-d', strtotime($strartdate));
        $end = date('Y-m-d', strtotime($enddate));

        $table = 'dbo.T_BankWeathChangeLog';
        $field = 'Sum(TotalMoney) as TotalMoney,sum(Production) as Production,sum(Consume) as Consume';
        $tablefield = 'SUM(a.[money]) as TotalMoney,sum(case when a.[money]>0 then a.[money] else 0 end) as Production,sum(case when a.[money]<0 then [money] else 0 end) as Consume';
        $sqlExec = "exec Proc_GetGameLogTotal '$table','$field','$tablefield','$join','$where','$begin','$end'";
        $result = [];
        try {
            $res = $this->getTableQuery($sqlExec);
            if ($res) {
                $result = $res[0][0];
                if (isset($result['TotalMoney'])) {
                    $result['TotalMoney'] = FormatMoney($result['TotalMoney']);
                } else {
                    $result['TotalMoney'] = 0;
                }
                if (isset($result['Production'])) {
                    $result['Production'] = FormatMoney($result['Production']);
                } else {
                    $result['Production'] = 0;
                }

                if (isset($result['Consume'])) {
                    $result['Consume'] = FormatMoney($result['Consume']);
                } else {
                    $result['Consume'] = 0;
                }
            }
        } catch (Exception $exception) {
            $result = [];
        }
        return $result;
    }

    /**
     * 多包金币日志
     * @return array
     */
    public function GetPackGoldRecord(): array
    {
        if (1) {
            $page = input('page', 1);
            $limit = input('limit', 15);
            $roleId = input('roleid', 0);
            $strartdate = input('strartdate', date('Y-m-d'));
            $enddate = input('enddate', date('Y-m-d'));
            $changetype = intval(input('changetype', 0));
            $AccountName = input('AccountName');
            $NickName = input('NickName');
            $usertype = intval(input('usertype', -1));
            $packID = input('PackID', -1);
            $where = "";
            $join = "LEFT JOIN CD_UserDB.dbo.View_Accountinfo B ON A.RoleID=B.AccountID " .
                "LEFT JOIN CD_UserDB.dbo.T_Role C ON A.RoleID=C.RoleID " .
                "WHERE  1=1";
            if ($changetype > 0) $where .= "AND ChangeType=$changetype ";
            if ($roleId > 0) $where .= "AND roleId=$roleId ";
            if ($usertype >= 0) $join .= " AND  gmtype=" . $usertype;
            //外联 条件
            if (!empty($AccountName)) $join .= " AND AccountName=''$AccountName''";
            if (!empty($NickName)) $join .= " AND LoginName=''$NickName''";
            if ($packID >= 0) $join .= " AND OperatorId IN ($packID)";
            $table = 'dbo.T_BankWeathChangeLog';
            $field = 'distinct A.*,B.gmtype,B.AccountName,B.OperatorId,B.PackageName,C.LoginName,B.MachineCode';
            $sqlExec = "exec Proc_GetPageData '$table','$field','$where', 'AddTime DESC','$join','$strartdate','$enddate', $page , $limit";
        }
        $result = $this->getTableQuery($sqlExec);
        $res['code'] = 0;
        $res['debug'] = true;
        $res["sql"] = $sqlExec;
        if (isset($result[1]) && $result[0][0]['count'] > 0) {
            $res['count'] = $result[0][0]['count'];
            $res['list'] = $result[1];
            $changeType = config('site.bank_change_type');
            foreach ($res['list'] as &$v) {
                foreach ($changeType as $k2 => $v2) {
                    if ($v['ChangeType'] == $k2) {
                        $v['ChangeType'] = $v2;
                        break;
                    }
                }
            }
            unset($v);
        }
        return $res;


    }


    /**
     * 代理日表
     * @param $roomlist
     * @return array
     */
    public function GetAgentRecord($iswater = false): array
    {
        $startdate = input('start', date('Y-m-d', time()));
        $enddate = input('end', date('Y-m-d', time()));
        $roleid = input('roleid');
        $parentid = input('parentid', 0);
        $where = "";

        $outAll = input('outall', false);
        if (input('Action') == 'exec' && $outAll == false) {
            $this->pageSize = 1;
        }


        $join = "LEFT JOIN CD_UserDB.dbo.T_UserProxyInfo B WITH (NOLOCK) ON A.ProxyId=B.RoleID WHERE 1=1";
//        //外联 条件
        if ($parentid > 0) {
            $join .= ' and ParentID=' . $parentid;
        }
        if (!empty($AccountName)) $join .= " AND AccountName=''$AccountName''";
//        if (!empty($NickName)) $join .= " AND LoginName=''$NickName''";

        if ($roleid > 0)
            $where .= ' and proxyid=' . $roleid;

        if (session('merchant_OperatorId') && request()->module() == 'merchant') {
            $where .= ' and OperatorId=' . session('merchant_OperatorId');
        }
        $tab = input('tab') ?: '';
        switch ($tab) {
            case 'today':
                $startdate = date('Y-m-d');
                $enddate   = $startdate;
                break;
            case 'yestoday':
                $startdate = date('Y-m-d',strtotime('-1 days'));
                $enddate   = $startdate;
                break;
            case 'month':
                $startdate = date('Y-m').'-01';
                $enddate   = date('Y-m-d');

                break;
            case 'lastmonth':
                $startdate = date('Y-m-01',strtotime('-1 month'));
                $enddate   = date('Y-m-d',strtotime(date('Y-m').'-01')-1);

                break;
            case 'week':
                $w = date('w');
                if ($w == 0) {
                    $w = 7;
                }
                $w = mktime(0,0,0,date('m'),date('d')-$w+1,date('y'));
                $startdate = date('Y-m-d',$w);
                $enddate   = date('Y-m-d');

                break;
            case 'lastweek':
                $w = date('w');
                if ($w == 0) {
                    $w = 7;
                }
                $w = mktime(0,0,0,date('m'),date('d')-$w+1,date('y'));
                $startdate = date('Y-m-d',$w-7*86400);
                $enddate   = date('Y-m-d',strtotime(date('Y-m-d',$w))-1);
                break;
            case 'q_day':
                $startdate = date('Y-m-d',strtotime($startdate)-86400);
                $enddate   = $startdate;
                break;
            case 'h_day':
                $enddate = date('Y-m-d',strtotime($enddate)+86400);
                $startdate   = $enddate;
                break;
            default:

                break;
        }
        $begin = date('Y-m-d', strtotime($startdate));
        $end = date('Y-m-d', strtotime($enddate));

        $orderfield = input('orderfield', "AddTime");
        $ordertype = input('ordertype', 'desc');
        $order = "$orderfield $ordertype,proxyid asc ";

        $table = 'dbo.T_ProxyDailyCollectData';
        $field = ' AddTime,ProxyId,DailyDeposit,DailyTax,
        DailyRunning,Lv1PersonCount,Lv1Deposit,Lv1Tax,Lv1Running,
        Lv2PersonCount,Lv2Deposit,Lv2Tax,Lv2Running,Lv3PersonCount,
        Lv3Deposit,Lv3Tax,Lv3Running,Lv1FirstDepositPlayers,
        Lv2FirstDepositPlayers,Lv3FirstDepositPlayers,A.ValidInviteCount,
        Lv2ValidInviteCount,Lv3ValidInviteCount,FirstDepositMoney';
        $sqlExec = "exec Proc_GetPageData '$table','$field','$where','$order','$join','$begin','$end', $this->page , $this->pageSize";
        try {
            $result = $this->getTableQuery($sqlExec);
        } catch (Exception $exception) {
            $result['list'] = [];
            $result['count'] = 0;
        }

        $res['code'] = 0;
        $res['debug'] = true;
        $res["sql"] = $sqlExec;
        $res['list'] = [];
        $res['count'] = 0;
        if (isset($result[1]) && $result[0][0]['count'] > 0) {
            $res['count'] = $result[0][0]['count'];
            $res['list'] = $result[1];
            foreach ($res['list'] as &$v) {
                if ($iswater) {
                    $lv1rate = config('agent_running_parent_rate')[1];
                    $lv2rate = config('agent_running_parent_rate')[2];
                    $lv3rate = config('agent_running_parent_rate')[3];
                    $Lv1Reward = bcmul($v['Lv1Running'], $lv1rate, 4);
                    $Lv2Reward = bcmul($v['Lv2Running'], $lv2rate, 4);
                    $Lv3Reward = bcmul($v['Lv3Running'], $lv3rate, 4);
                    $rewar_amount = bcadd($Lv1Reward, $Lv2Reward, 4);
                    $rewar_amount = bcadd($rewar_amount, $Lv3Reward, 2);
                    $v['RewardAmount'] = $rewar_amount;
                } else {
                    $level1profit = bcmul($v['Lv1Tax'], 0.3, 3);
                    $level2profit = bcmul($v['Lv2Tax'], 0.09, 3);
                    $level3profit = bcmul($v['Lv3Tax'], 0.027, 3);
                    $v['RewardAmount'] = $level1profit + $level2profit + $level3profit;
                }
                ConVerMoney($v['RewardAmount']);
                ConVerMoney($v['DailyTax']);
                ConVerMoney($v['DailyRunning']);
                ConVerMoney($v['Lv1Tax']);
                ConVerMoney($v['Lv2Tax']);
                ConVerMoney($v['Lv3Tax']);
                ConVerMoney($v['Lv3Running']);
                ConVerMoney($v['Lv2Running']);
                ConVerMoney($v['Lv1Running']);
                if(config('AgentWaterDaily') == 1){
                    $v['Lv1FirstDepositMoney'] = 0;
                    $v['Lv2FirstDepositMoney'] = 0;
                    $v['Lv3FirstDepositMoney'] = 0;
                    $v['Lv1WithdrawalMoney'] = 0;
                    $v['Lv2WithdrawalMoney'] = 0;
                    $v['Lv3WithdrawalMoney'] = 0;
                    $time = date('Ymd',strtotime($v['AddTime']));
                    //Lv1FirstDepositMoney(一级首充金额)
                    //Lv2FirstDepositMoney(二级首充金额)
                    //Lv3FirstDepositMoney(三级首充金额)
                    //Lv1WithdrawalMoney(一级提现金额)
                    //Lv2WithdrawalMoney(二级提现金额)
                    //Lv3WithdrawalMoney(三级提现金额)
                    $agentTemDeposit = (new GameOCDB())->getTableObject('T_UserDailyDeposit')
                        ->where('DayTime',$time)
                        ->where('RoleId',$v['ProxyId'])
                        ->find();
                    if (!empty($agentTemDeposit)){
                        $v['Lv1FirstDepositMoney'] = FormatMoney($agentTemDeposit['Lv1FirstDepositMoney']);
                        $v['Lv2FirstDepositMoney'] = FormatMoney($agentTemDeposit['Lv2FirstDepositMoney']);
                        $v['Lv3FirstDepositMoney'] = FormatMoney($agentTemDeposit['Lv3FirstDepositMoney']);
                        $v['Lv1WithdrawalMoney'] = FormatMoney($agentTemDeposit['Lv1WithdrawalMoney']);
                        $v['Lv2WithdrawalMoney'] = FormatMoney($agentTemDeposit['Lv2WithdrawalMoney']);
                        $v['Lv3WithdrawalMoney'] = FormatMoney($agentTemDeposit['Lv3WithdrawalMoney']);

                    }
                }

                //团队打码
                $v['dm'] = bcadd($v['Lv1Running'], $v['Lv2Running'],3);
            }
            unset($v);
        }

        $res['startdate'] = $startdate;
        $res['enddate'] = $enddate;
        return $res;
    }

    //业务员
    public function GetBusinessAgentRecord($iswater = false): array
    {
        $startdate = input('start', date('Y-m-d', time()));
        $enddate = input('end', date('Y-m-d', time()));
        $roleid = input('roleid');
        $parentid = input('parentid', 0);
        $where = "";

        $outAll = input('outall', false);
        if (input('Action') == 'exec' && $outAll == false) {
            $this->pageSize = 1;
        }


        $join = "LEFT JOIN CD_UserDB.dbo.T_UserProxyInfo B WITH (NOLOCK) ON A.ProxyId=B.RoleID WHERE 1=1";
//        //外联 条件
        if ($parentid > 0) {
            $join .= ' and ParentID=' . $parentid;
        }
        if (!empty($AccountName)) $join .= " AND AccountName=''$AccountName''";
//        if (!empty($NickName)) $join .= " AND LoginName=''$NickName''";

        if ($roleid > 0)
            $where .= ' and proxyid=' . $roleid;

        $all_ProxyChannelId = $this->getXbusiness(session('business_ProxyChannelId'));
        $all_ProxyChannelId[] = session('business_ProxyChannelId');
        $where .= " and proxyid in(SELECT AccountID from [CD_Account].[dbo].[T_Accounts](nolock) WHERE ProxyChannelId in(" . implode(',', $all_ProxyChannelId) . "))";

        $begin = date('Y-m-d', strtotime($startdate));
        $end = date('Y-m-d', strtotime($enddate));

        $orderfield = input('orderfield', "AddTime");
        $ordertype = input('ordertype', 'desc');
        $order = "$orderfield $ordertype";

        $table = 'dbo.T_ProxyDailyCollectData';
        $field = ' AddTime,ProxyId,DailyDeposit,DailyTax,
        DailyRunning,Lv1PersonCount,Lv1Deposit,Lv1Tax,Lv1Running,
        Lv2PersonCount,Lv2Deposit,Lv2Tax,Lv2Running,Lv3PersonCount,
        Lv3Deposit,Lv3Tax,Lv3Running,Lv1FirstDepositPlayers,
        Lv2FirstDepositPlayers,Lv3FirstDepositPlayers,A.ValidInviteCount,
        Lv2ValidInviteCount,Lv3ValidInviteCount,FirstDepositMoney';
        $sqlExec = "exec Proc_GetPageData '$table','$field','$where','$order','$join','$begin','$end', $this->page , $this->pageSize";

        try {
            $result = $this->getTableQuery($sqlExec);
        } catch (Exception $exception) {
            $result['list'] = [];
            $result['count'] = 0;
        }

        $res['code'] = 0;
        $res['debug'] = true;
        $res["sql"] = $sqlExec;
        $res['list'] = [];
        $res['count'] = 0;
        if (isset($result[1]) && $result[0][0]['count'] > 0) {
            $res['count'] = $result[0][0]['count'];
            $res['list'] = $result[1];
            foreach ($res['list'] as &$v) {
                if ($iswater) {
                    $lv1rate = config('agent_running_parent_rate')[1];
                    $lv2rate = config('agent_running_parent_rate')[2];
                    $lv3rate = config('agent_running_parent_rate')[3];
                    $Lv1Reward = bcmul($v['Lv1Running'], $lv1rate, 4);
                    $Lv2Reward = bcmul($v['Lv2Running'], $lv2rate, 4);
                    $Lv3Reward = bcmul($v['Lv3Running'], $lv3rate, 4);
                    $rewar_amount = bcadd($Lv1Reward, $Lv2Reward, 4);
                    $rewar_amount = bcadd($rewar_amount, $Lv3Reward, 2);
                    $v['RewardAmount'] = $rewar_amount;
                } else {
                    $level1profit = bcmul($v['Lv1Tax'], 0.3, 3);
                    $level2profit = bcmul($v['Lv2Tax'], 0.09, 3);
                    $level3profit = bcmul($v['Lv3Tax'], 0.027, 3);
                    $v['RewardAmount'] = $level1profit + $level2profit + $level3profit;
                }
                ConVerMoney($v['RewardAmount']);
                ConVerMoney($v['DailyTax']);
                ConVerMoney($v['DailyRunning']);
                ConVerMoney($v['Lv1Tax']);
                ConVerMoney($v['Lv2Tax']);
                ConVerMoney($v['Lv3Tax']);
                ConVerMoney($v['Lv3Running']);
                ConVerMoney($v['Lv2Running']);
                ConVerMoney($v['Lv1Running']);
            }
            unset($v);
        }
        return $res;
    }

    /**
     * 代理日表
     * @param $roomlist
     * @return array
     */
    public function GetBusinessAgentRecordSum($iswater = false): array
    {
        $startdate = input('start', date('Y-m-d', time()));
        $enddate = input('end', date('Y-m-d', time()));
        $roleid = input('roleid');
        $parentid = input('parentid', 0);
        $where = "";
        $where2 = "1=1";
        $outAll = input('outall', false);
        if (input('Action') == 'exec' && $outAll == false) {
            $this->pageSize = 1;
        }


        $join = "LEFT JOIN CD_UserDB.dbo.T_UserProxyInfo B WITH (NOLOCK) ON A.ProxyId=B.RoleID WHERE 1=1";
//        //外联 条件
        if ($parentid > 0) {
            $join .= ' and ParentID=' . $parentid;
        }
        if (!empty($AccountName)) {
            $join .= " AND AccountName=''$AccountName''";
        }

        $all_ProxyChannelId = $this->getXbusiness(session('business_ProxyChannelId'));
        $all_ProxyChannelId[] = session('business_ProxyChannelId');
        $where .= " and proxyid in(SELECT AccountID from [CD_Account].[dbo].[T_Accounts](nolock) WHERE ProxyChannelId in(" . implode(',', $all_ProxyChannelId) . "))";

        $where2 .= " and c.ProxyChannelId in(" . implode(',', $all_ProxyChannelId) . ")";

        if ($roleid > 0) {
            $where .= ' and proxyid=' . $roleid;
            $where2 .= ' and a.RoleID=' . $roleid;
        }

        if (session('merchant_OperatorId') && request()->module() == 'merchant') {
            $where .= ' and OperatorId=' . session('merchant_OperatorId');
            $where2 .= ' and c.OperatorId=' . session('merchant_OperatorId');
        }
        $businessProxyChannelId = '';
        if (session('business_ProxyChannelId') && request()->module() == 'business') {
            $businessProxyChannelId = session('business_ProxyChannelId');
        }
        $begin = date('Y-m-d', strtotime($startdate));
        $end = date('Y-m-d', strtotime($enddate));

        $table = 'dbo.T_ProxyDailyCollectData';
        $field = '  ISNULL(Sum(FirstDepositPerson),0) as FirstDepositPerson,ISNULL(Sum(FirstDepositMoney),0) as FirstDepositMoney,ISNULL(Sum(Lv1PersonCount),0) as Lv1PersonCount,ISNULL(Sum(Lv1Running),0) as Lv1Running,ISNULL(Sum(Lv2Running),0) as Lv2Running,ISNULL(Sum(Lv3Running),0) as Lv3Running,ISNULL(Sum(Lv1Running+Lv2Running),0) dm,ISNULL(Sum(ValidInviteCount),0) as ValidInviteCount,ISNULL(Sum(Lv2ValidInviteCount),0) as Lv2ValidInviteCount,ISNULL(Sum(Lv3ValidInviteCount),0) as Lv3ValidInviteCount';
        $sqlExec = "exec Proc_GetPageGROUP '$table','$field','$where','$begin','$end'";
        $list = [];
        try {
            $result = $this->getTableQuery($sqlExec);
            $temp = [];
            if (isset($result[0])) {
                $list = $result[0];
                $userDB = new UserDB();
                $redisKey = 'GET_USER_ALL_LIST';
                $userList = Redis::get($redisKey);
                if (!$userList) {
                    $data = $userDB->getTableObject('T_UserProxyInfo')
                        ->field('RoleID,ParentID')
                        ->select();
                    $userList = Redis::set($redisKey, $data, 3600);
                }
                $userSubsetList = '';
                if (!empty($roleid)) {
                    $userSubsetList = Redis::get('USER_SUBSET_LIST_' . $roleid);
                    if (!$userSubsetList) {
                        $userSubsetList = sortList($userList, $roleid);
                        Redis::set('USER_SUBSET_LIST_' . $roleid, $userSubsetList, 3600);
                    }
                }



                $flippedData = '';
                if (!empty($businessProxyChannelId)) {
                    $channelIds = $this->getTableObject('T_ProxyChannelConfig')
                        ->where('pid',$businessProxyChannelId)
                        ->column('ProxyChannelId');
                    if (!empty($channelIds)){
                        $businessProxyChannelIdArray = array_flip($channelIds);
                    }
                    $businessProxyChannelIdArray[] = $businessProxyChannelId;
//                    $flippedData = Redis::get('USER_OPERATOR_SUBSET_LIST_' . $operatorId);
//                    if (!$flippedData) {
                    $operatorIdUserList = $userDB->getTableObject('View_Accountinfo')
                        ->wherein('ProxyChannelId',  $businessProxyChannelIdArray)
                        ->column('AccountID');
                    $flippedData = array_flip($operatorIdUserList);
//                        Redis::set('USER_OPERATOR_SUBSET_LIST_' . $operatorId, $flippedData, 3600);
//                    }
                }
//                $list[0]['FirstDepositMoney'] = (new \app\model\DataChangelogsDB())
//                    ->getTableObject('T_UserTransactionLogs')->alias('a')
//                    ->join('[CD_Account].[dbo].[T_Accounts](NOLOCK) c', 'c.AccountID=a.RoleID', 'left')
//                    ->where($where2)
//                    ->whereTime('a.AddTime', '>=', $begin . ' 00:00:00')
//                    ->whereTime('a.AddTime', '<=', $end . ' 23:59:59')
//                    ->where('a.ChangeType', 5)
//                    ->where('a.IfFirstCharge', 1)
//                    ->sum('TransMoney') ?: 0;
                foreach ($list as &$v) {
                    $item = [];
                    $item['Lv1Running'] = FormatMoney($v['Lv1Running']);
                    $item['Lv2Running'] = FormatMoney($v['Lv2Running']);
                    $item['Lv3Running'] = FormatMoney($v['Lv3Running']);
                    $item['dm'] = FormatMoney($v['dm']);
                    if ($roleid) {
                        //首充人数
                        $item['FirstDepositPersons'] = $this->getFirstDeposit($roleid, '', $userSubsetList, $begin, $end, 1);
                        //首充金额
                        $item['FirstDepositMoneys'] = $this->getFirstDeposit($roleid, '', $userSubsetList, $begin, $end, 2);
                    } else {
                        $item['FirstDepositPersons'] = $this->getFirstDeposit('', $businessProxyChannelId, $flippedData, $begin, $end, 1);
                        $item['FirstDepositMoneys'] = $this->getFirstDeposit('', $businessProxyChannelId, $flippedData, $begin, $end, 2);
                    }
                    $item['Lv1PersonCount'] = $v['Lv1PersonCount'];
                    $item['Lv2ValidInviteCount'] = $v['Lv2ValidInviteCount'];
                    $item['Lv3ValidInviteCount'] = $v['Lv3ValidInviteCount'];
                    $item['ValidInviteCount'] = $v['ValidInviteCount'];
                    $item['business_ProxyChannelId'] = session('business_ProxyChannelId');
                    $item['business_OperatorId'] = session('business_OperatorId');
                    $temp[] = $item;
                }
                unset($v);
            }
            return $temp;
        } catch (Exception $exception) {
            //var_dump($exception->getMessage());
            return $list;
        }
    }

    /**
     * 代理日表
     * @param $roomlist
     * @return array
     */
    public function GetAgentRecordSum($iswater = false): array
    {
        $startdate = input('start', date('Y-m-d', time()));
        $enddate = input('end', date('Y-m-d', time()));
        $roleid = input('roleid');
        $parentid = input('parentid', 0);
        $where = "";
        $where2 = "1=1";
        $outAll = input('outall', false);
        if (input('Action') == 'exec' && $outAll == false) {
            $this->pageSize = 1;
        }


        $join = "LEFT JOIN CD_UserDB.dbo.T_UserProxyInfo B WITH (NOLOCK) ON A.ProxyId=B.RoleID WHERE 1=1";
//        //外联 条件
        if ($parentid > 0) {
            $join .= ' and ParentID=' . $parentid;
        }
        if (!empty($AccountName)) $join .= " AND AccountName=''$AccountName''";
//        if (!empty($NickName)) $join .= " AND LoginName=''$NickName''";

        if ($roleid > 0){
            $where .= ' and proxyid=' . $roleid;
            $where2 .= ' and a.RoleID=' . $roleid;
        }

        if (session('merchant_OperatorId') && request()->module() == 'merchant') {
            $where .= ' and OperatorId=' . session('merchant_OperatorId');
            $where2 .= ' and c.OperatorId=' . session('merchant_OperatorId');
        }
        $tab = input('tab') ?: '';
        switch ($tab) {
            case 'today':
                $startdate = date('Y-m-d');
                $enddate   = $startdate;
                break;
            case 'yestoday':
                $startdate = date('Y-m-d',strtotime('-1 days'));
                $enddate   = $startdate;
                break;
            case 'month':
                $startdate = date('Y-m').'-01';
                $enddate   = date('Y-m-d');

                break;
            case 'lastmonth':
                $startdate = date('Y-m-01',strtotime('-1 month'));
                $enddate   = date('Y-m-d',strtotime(date('Y-m').'-01')-1);

                break;
            case 'week':

                $w = mktime(0,0,0,date('m'),date('d')-date('w')+1,date('y'));
                $startdate = date('Y-m-d',$w);
                $enddate   = date('Y-m-d');

                break;
            case 'lastweek':
                $w = mktime(0,0,0,date('m'),date('d')-date('w')+1,date('y'));
                $startdate = date('Y-m-d',$w-7*86400);
                $enddate   = date('Y-m-d',strtotime(date('Y-m-d',$w))-1);
                break;
            case 'q_day':
                $startdate = date('Y-m-d',strtotime($startdate)-86400);
                $enddate   = $startdate;
                break;
            case 'h_day':
                $enddate = date('Y-m-d',strtotime($enddate)+86400);
                $startdate   = $enddate;
                break;
            default:

                break;
        }
        $begin = date('Y-m-d', strtotime($startdate));
        $end = date('Y-m-d', strtotime($enddate));

        $table = 'dbo.T_ProxyDailyCollectData';
        $field = '  ISNULL(Sum(FirstDepositPerson),0) as FirstDepositPerson,ISNULL(Sum(FirstDepositMoney),0) as FirstDepositMoney,ISNULL(Sum(Lv1PersonCount),0) as Lv1PersonCount,ISNULL(Sum(Lv1Running),0) as Lv1Running,ISNULL(Sum(Lv2Running),0) as Lv2Running,ISNULL(Sum(Lv3Running),0) as Lv3Running,ISNULL(Sum(Lv1Running+Lv2Running+Lv3Running),0) dm,ISNULL(Sum(ValidInviteCount),0) as ValidInviteCount,ISNULL(Sum(Lv2ValidInviteCount),0) as Lv2ValidInviteCount,ISNULL(Sum(Lv3ValidInviteCount),0) as Lv3ValidInviteCount';
        $sqlExec = "exec Proc_GetPageGROUP '$table','$field','$where','$begin','$end'";
        $list = [];
        try {
            $result = $this->getTableQuery($sqlExec);
            if (isset($result[0])) {
                $list = $result[0];
                $list[0]['FirstDepositMoney'] = (new \app\model\DataChangelogsDB())
                    ->getTableObject('T_UserTransactionLogs')->alias('a')
                    ->join('[CD_Account].[dbo].[T_Accounts](NOLOCK) c', 'c.AccountID=a.RoleID', 'left')
                    ->where($where2)
                    ->whereTime('a.AddTime','>=',$begin.' 00:00:00')
                    ->whereTime('a.AddTime','<=',$end.' 23:59:59')
                    ->where('a.ChangeType',5)
                    ->where('a.IfFirstCharge',1)
                    ->sum('TransMoney')?:0;
                foreach ($list as &$v) {

                    ConVerMoney($v['Lv1Running']);
                    ConVerMoney($v['Lv2Running']);
                    ConVerMoney($v['Lv3Running']);
                    ConVerMoney($v['dm']);
                }
                unset($v);
            }
            return $list;
        } catch (Exception $exception) {
            //var_dump($exception->getMessage());
            return $list;
        }
    }

    /**
     * @param $roleId
     * @param $operatorId
     * @param $list
     * @param $beginTime
     * @param $endTime
     * @param $type
     * @return float|int|string
     * @throws Exception
     */
    public function getFirstDeposit($roleId, $operatorId, $list, $beginTime, $endTime, $type)
    {
        if (!empty($list)) {
            $batchSize = 100;
// 计算数据集的总数
            $totalRecords = count($list) ?? 0;

// 计算需要分成多少批次
            $numBatches = ceil($totalRecords / $batchSize);

// 分批处理数据
            $number = 0;
            for ($batch = 0; $batch < $numBatches; $batch++) {
                $start = $batch * $batchSize;
                $end = min(($batch + 1) * $batchSize, $totalRecords);

                // 获取当前批次的数据
                $batchData = array_slice($list, $start, $end - $start);

                // 处理当前批次的数据
                if ($type == 1) {
                    //处理首充人数
                    if ($roleId) {
                        $number += $this->getFirstDepositPerson($roleId, '', $batchData, $beginTime, $endTime);
                    } else {
                        $number += $this->getFirstDepositPerson('', $operatorId, $batchData, $beginTime, $endTime);
                    }
                } else {
                    //处理首充金额
                    if ($roleId) {
                        $number += $this->getFirstDepositMoney($roleId, '', $batchData, $beginTime, $endTime);
                    } else {
                        $number += $this->getFirstDepositMoney('', $operatorId, $batchData, $beginTime, $endTime);
                    }
                }

            }
        } else {
            $number = 0;
            if ($type == 1) {
                //处理首充人数
                if ($roleId) {
                    $number += $this->getFirstDepositPerson($roleId, '', [], $beginTime, $endTime);
                } else {
                    $number += $this->getFirstDepositPerson('', $operatorId, [], $beginTime, $endTime);
                }
            } else {
                //处理首充金额
                if ($roleId) {
                    $number += $this->getFirstDepositMoney($roleId, '', [], $beginTime, $endTime);
                } else {
                    $number += $this->getFirstDepositMoney('', $operatorId, [], $beginTime, $endTime);
                }
            }

        }

        return $number;
    }

    /**
     * 统计首充人数
     * @param $roleId
     * @param $operatorId
     * @param $list
     * @param $begin
     * @param $end
     * @return int|string
     * @throws Exception
     */
    public function getFirstDepositPerson($roleId, $operatorId, $list, $begin, $end)
    {
        return (new DataChangelogsDB())
            ->getTableObject('T_UserTransactionLogs')
            ->where('AddTime', 'between', [
                $begin . ' 00:00:00',
                $end . ' 23:59:59'
            ])
            ->where('IfFirstCharge', 1)
            ->where(function ($q) use ($roleId, $list) {
                if ($roleId) {
                    $q->whereIn('RoleID', $list);
                }
            })
            ->where(function ($q) use ($operatorId, $list) {
                if ($operatorId) {
                    $q->whereIn('RoleID', $list);
                }
            })
            ->count() ?? 0;
    }

    /**
     * 统计首充金额
     * @param $roleId
     * @param $operatorId
     * @param $list
     * @param $begin
     * @param $end
     * @return float|int|string
     */
    public function getFirstDepositMoney($roleId, $operatorId, $list, $begin, $end)
    {
        return (new DataChangelogsDB())
            ->getTableObject('T_UserTransactionLogs')
            ->where('IfFirstCharge', 1)
            ->where('AddTime', 'between', [
                $begin . ' 00:00:00',
                $end . ' 23:59:59'
            ])
            ->where(function ($q) use ($roleId, $list) {
                if ($roleId) {
                    $q->whereIn('RoleID', $list);
                }
            })
            ->where(function ($q) use ($operatorId, $list) {
                if ($operatorId) {
                    $q->whereIn('RoleID', $list);
                }
            })
            ->sum('TransMoney') ?? 0;
    }


    /**
     * 游戏盈亏结算明细
     * @param $roomlist
     * @return array
     * @throws PDOException
     */
    public function GetTotalRoominfo(&$roomlist): array
    {
        $this->table = 'View_GameRoominfo';
        $date = trim(input('date')) ? trim(input('date')) : date('Y-m-d');
        $roomid = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $orderby = input('orderby');
        $orderType = input('orderType');
        $where = " AND addDate='$date'  And RoomID=$roomid ";
        $order = "";
        if (strtotime($date) > strtotime(date('Y-m-d'))) return [];
        if (!empty($orderby)) $order .= "$orderby $orderType";
        else $order = "RoomID  ASC";
        $result = $this->GetPage($where, $order);

        $roomName = "";
        if (isset($result['list']) && $result['list']) {
            foreach ($result['list'] as &$v) {
                if (empty($roomName)) $roomName = $v['RoomName'];
                //盈利
                ConVerMoney($v['WinScore']);
                ConVerMoney($v['Water']);
                ConVerMoney($v['RoundBets']);
                if ($v['RoundBets'] > 0)
                    $v['GameRate'] = sprintf("%.2f", (($v['RoundBets'] - $v['WinScore']) / $v['RoundBets'] * 100)) . '%';
                else
                    $v['GameRate'] = 0;
                $v['date'] = $date ? $date : date("Y-m-d");

            }
            unset($v);
        }
        $result['other']['RoomName'] = $roomName;
        return $result;
    }

    //判断表是否存在 存在1  不存在 0

    /**
     * @param $TableName
     * @return mixed
     * @throws PDOException
     */
    public function IsExistTable($TableName)
    {
        return $this->getTableQuery("EXEC dbo.Proc_IsExistTable @TableName = '$TableName'")[0][0]["iResult"];
    }

    /**
     * 玩家盈亏排行
     * @param string $where
     * @param string $order
     * @return array
     * @throws PDOException
     */
    public function getGDrank(string $where, string $order)
    {
        $this->table = 'View_TotalDayScore';
        $result = $this->GetPage($where, $order, "*", true);
        if (isset($result['list']) && $result['list']) {
            $ProxyChannelConfig = $this->getTableObject('T_ProxyChannelConfig')->column('*', 'ProxyChannelId');
            $default_ProxyId = $this->getTableObject('T_ProxyChannelConfig')->where('isDefault', 1)->value('ProxyId') ?: 0;
            foreach ($result['list'] as &$v) {
                //$v['ROA'] = sprintf("%.2f", $v['ROA']) . "%";//回报率
                ConVerMoney($v['TotalWater']);
                ConVerMoney($v['SGD']);//平台输赢
                ConVerMoney($v['Money']);
                ConVerMoney($v['outMoney']);
                ConVerMoney($v['Tax']);
//              ConVerMoney($v['totalScore']);//输赢
                $v['SGD'] = -$v['SGD'];
                $v['GameRate'] = 0;
                if ($v['payMoney'] > 0) {
                    $v['GameRate'] = bcdiv($v['SGD'] * 100, $v['payMoney'], 2) . '%';//
                } else {
                    $v['GameRate'] = "0%";
                }
                $ParentIds = array_filter(explode(',', $v['ParentIds'] ?? ''));
                $proxy = [];
                if (!empty($ParentIds)) {
                    $proxy = $ProxyChannelConfig[$ParentIds[0]] ?? [];
                    if ($proxy) {
                        $v['proxyId'] = $proxy['ProxyId'];
                    } else {
                        $v['proxyId'] = $v['ParentID'];
                    }
                } else {
                    //默认系统代理
                    $v['proxyId'] = $default_ProxyId;
                }
                unset($v);
            }
        }
        return $result;
    }

    /**
     * @return $this
     */
    public function TGMSendMoney()
    {
        $this->table = 'T_GMSendMoney';
        return $this;
    }

    /**
     * Gm充值管理
     * @return array
     */
    public function GMSendMoney($is_merchant=0)
    {
        $this->table = 'T_GMSendMoney';
        $RoleId = input('RoleId', -1);
        $VerifyState = input('VerifyState', -1);
        $operatortype = input('operatortype', -1);
        $OperateId = input('OperateId');
        $Amount = input('Amount');
        $start = input('start');
        $end = input('end', date('Y-m-d'));
        $limit = (int)input('limit', 20);

        $where = "1=1";
        if (session('merchant_OperatorId') && request()->module() == 'merchant') {
            $where .= " AND checkUser LIKE 'operator:".session('merchant_OperatorId')."%'";

        } else if (session('business_ProxyChannelId') && request()->module() == 'business') {
            $where .= " AND checkUser LIKE '%-".session('business_ProxyChannelId')."'";
        } else {
            if ($is_merchant == 1) {
                $where .= " AND checkUser LIKE 'operator:%'";
            } else {
                $where .= " AND checkUser NOT LIKE 'operator:%'";
            }

        }
        if ($RoleId >= 0 && !empty($RoleId)) $where .= " AND RoleId=$RoleId";
        if ($Amount >= 0 && !empty($Amount)) $where .= " AND Money=" . (0 - $Amount);
        if ($VerifyState >= 0) $where .= " AND status=$VerifyState";
        if (!empty($start)) $where .= " AND InsertTime BETWEEN '$start 00:00:00' AND '$end 23:59:59'";
        if ($operatortype > 0) {
            $where .= " AND OperateType=" . $operatortype;
        }
        if ($OperateId != '') {
            $where .= " AND checkUser LIKE 'operator:".$OperateId."%'";
        }
        $Operators = $this->getTableObject('T_OperatorSubAccount')->where('1=1')->column('*','OperatorId');//OperatorName
        $business  = $this->getTableObject('T_ProxyChannelConfig')->where('1=1')->column('*','ProxyChannelId');//AccountName
        // $result = $this->GetPage($where, 'ID DESC');
        $result = $this->getTableObject('T_GMSendMoney')->alias('a')
            ->join('[CD_Account].[dbo].[T_Accounts] b','b.AccountID=a.RoleId','left')
            ->where($where)
            ->field('a.*,b.ProxyChannelId,b.OperatorId')
            ->order('ID DESC')
            ->paginate($limit)
            ->toArray();
        foreach ($result['data'] as $key => &$item) {
            if ($item['OperatorId']>0) {
                $item['OperatorName'] = $Operators[$item['OperatorId']]['OperatorName'];
            } else {
                $item['OperatorName'] = '';
            }
            if ($item['ProxyChannelId']>0) {
                $item['ProxyChannelName'] = $business[$item['ProxyChannelId']]['AccountName'];
            } else {
                $item['ProxyChannelName'] = '';
            }
        }
        $result['list'] =  $result['data'];
        $result['count'] =  $result['total'];

        if (empty($where)) $where = "status=1";
        $result['other'] = $this->GetRow($where, "COUNT(ID)TotalCount,ABS(SUM(Money))TotalMoney");
        return $result;
    }

    //扣款

    /**
     * @param $data
     * @return int|string
     */
    public function GMSendMoneyAdd($data)
    {
        $this->table = 'T_GMSendMoney';
        return $this->insert($data);
    }

    /**
     * 免费游戏明细数据
     * @param string $RoleID
     * @param int $type
     * @param bool $debug
     * @return array
     */
    public function GetFreeGameData($RoleID = '', $type = 1, $debug = false): array
    {
        $table = 'dbo.T_UserGameWipeoutLogs';
        $field = "ID,RecordType,RoundIndex,RoomID,RoleID,SingleBet,TotalBet,GameRunning,RoundResult,LastGold,AddTime";
        $join = '';
        $order = input('orderby');
        $ordertype = input('orderytpe');
        if (empty($order)) $order = 'ID desc';
        else $order = "$order $ordertype";
        $RoomID = input('RoomID');
        $where = " AND RecordType=$type";
        if (!empty($RoleID)) $where .= " AND RoleID=$RoleID";
        if ($RoomID > 0) $where .= " AND RoomID=$RoomID";
        $sqlExec = "exec Proc_GetPageData '$table','$field','$where', '$order','$join','$this->start','$this->end', $this->page , $this->pageSize";

        if ($debug) $result = ['sql' => $sqlExec, 'debug' => $debug];
        try {
            $res = $this->getTableQuery($sqlExec);
            //统计计算
            $field = " ISNULL(SUM(GameRunning),0)TotalWater,ISNULL(SUM(RoundResult),0)Result";
            $sqlExec = "exec Proc_GetPageGROUP '$table','$field','$where', '$this->start','$this->end'";
            $other = $this->getTableQuery($sqlExec)[0][0];

            //金币记录表计算 免费游戏消耗
//            $table = 'dbo.T_BankWeathChangeLog';
//            $field = " ISNULL(SUM(Money),0)TotalWater";
//            $sqlExec = "exec Proc_GetPageGROUP '$table','$field','AND ChangeType =49 ', '$this->start','$this->end'";
//            $other['TotalWater'] = 0 - $this->getTableQuery($sqlExec)[0][0]['TotalWater'];
            ConVerMoney($other['TotalWater']);
            ConVerMoney($other['Result']);


            $result['count'] = (int)$res[0][0]['count'];
            if ($result['count'] > 0) {
                foreach ($res[1] as &$row) {
                    ConVerMoney($row['TotalBet']);
                    ConVerMoney($row['LastGold']);
                    ConVerMoney($row['GameRunning']);
                    ConVerMoney($row['RoundResult']);
                    ConVerDate($row['AddTime']);
                }
            }
            $result['list'] = &$res[1];
        } catch (Exception $exception) {
            $result['list'] = [];
            $other = [];
        }
        $result['other'] = $other;
        return $result;
    }

    public function GetPayNotifyLogTable()
    {
        $this->table = 'T_PayNotifyLog';

        return $this;
    }

    public function GetAgentTotal()
    {
        $this->table = 'AgentTotal';
        $start = input('start');
        $end = input('end');
        $where = '';
        if (!empty($start)) $where = " And MyDate ='$start'";
        if (!empty($end)) $where = " And MyDate ='$end'";
        if (!empty($start) && !empty($end)) $where = " And MyDate BETWEEN '$start' AND '$end' ";
        $result = $this->GetPage($where, 'Mydate desc');
        $result['other'] = $this->GetRow('1=1 ' . $where, 'SUM(UserPay)TotalPay,SUM(UserOut)TotalOut');
        return $result;
    }


    public function runSystemDaySum($strdate)
    {
        try {
            $strsql = "exec OM_GameOC.dbo.Proc_SystemDataSum :date";

            $res = $this->connection->query($strsql,
                [
                    'date' => $strdate
                ]);
        } catch (PDOException $e) {
            save_log('error', $e->getMessage());
            return false;
        }
    }

    public function getProxyChannelConfig()
    {
        $this->table = 'T_ProxyChannelConfig';
        return $this;
    }

    public function setTable($tableName)
    {
        $this->table = $tableName;
        return $this;
    }


    // 获取某日活跃客户数
    public function GetDailyActiveUserCountByDay($table1)
    {
        $sqlQuery = "SELECT  count(distinct(RoleID)) as count from " . $table1;
        $data = $this->connection->query($sqlQuery);
        if (!empty($data) && isset($data[0]['count'])) {
            return $data[0]['count'];
        } else {
            return 0;
        }
    }

    //代理每日奖励
    public function GetDailyAgentRewardByDay($day)
    {
        $sqlQuery = "SELECT ISNULL(sum(RunningBonus+InviteBonus+FirstChargeBonus),0) as Total  FROM T_ProxyDailyBonus WHERE datediff(d,addtime,'$day')=0 ";
        $data = $this->connection->query($sqlQuery);
        if (!empty($data) && isset($data[0]['Total'])) {
            return $data[0]['Total'];
        } else {
            return 0;
        }
    }


    //获取uniqueid
    public function GetUniqueIDRecord($debug = false)
    {
        $roleId = input('roleid', '');
        $RoomID = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $page = 1;
        $limit = 2600;

        $initdate = config('record_start_time');
        $strartdate = input('strartdate') ? input('strartdate') : $initdate . ' 00:00:00';
        $enddate = input('enddate') ? input('enddate') : date("Y-m-d H:i:s");
        $playernum = input('playernum', -1);


        if (empty($playernum))
            $playernum = -1;

        if (empty($roleId) && $playernum == -1) {
            return false;
        }


        $where = "1=1";

        $filter_number = 0;
        if (!empty($roleId)) {
            $where .= " AND UserId in($roleId)";
            $filter_number = count(explode(',', $roleId));
        }
        if ($playernum > -1) {
            $filter_number = $playernum;
        }

        if (empty($roleId)) {
            $filter_number = $filter_number + 1;
        }

        $where .= " and AddTime>='" . $strartdate . "' and AddTime<='" . $enddate . "' ";
        if ($RoomID > 0) $where .= " and convert(int,RoomID/10)*10=$RoomID";
        $days = date_diff(date_create($enddate), date_create($strartdate))->days;
        $table_date = date('Ymd', strtotime($strartdate));

        $table = "[T_GameDetail_" . $table_date . "]";
        $sql = "SELECT UniqueId,RoomID FROM " . $table . " with(nolock) WHERE $where group by UniqueId,RoomID HAVING COUNT(1)=" . $filter_number;

        if ($days > 0) {
            for ($i = 1; $i < $days + 1; $i++) {
                $table_date = date('Ymd', strtotime($strartdate) + 86400 * $i);
                $table = "[T_GameDetail_" . $table_date . "]";
                $sql .= " UNION ALL SELECT UniqueId,RoomID FROM " . $table . " with(nolock) WHERE $where group by UniqueId,RoomID HAVING COUNT(1)=" . $filter_number;
            }
        }
        $res = $this->getTableQuery($sql);
        //print_r($res);
        return $res ?? [];
        // $result['count'] = 0;
        // $field = "UniqueId,AddTime";
        // $table = 'dbo.T_GameDetail';
        // $join = 'LEFT JOIN  OM_MasterDB.dbo.T_GameRoomInfo B WITH (NOLOCK) ON A.RoomID=B.RoomID';
        // if ($RoomID > 0) $join .= " WHERE convert(int,A.roomid/10)*10=$RoomID";
        // $order = 'addtime';
        // $ordertype = 'desc';
        // $order = "'$order $ordertype'";

        // $begin = date('Y-m-d', strtotime($strartdate));
        // $end = date('Y-m-d', strtotime($enddate));
        // try {
        //     $sqlExec = "exec Proc_GetPageData '$table','$field','$where', $order,'$join','$begin','$end', $page , $limit";
        //     if ($debug) {
        //         $result = ['sql' => $sqlExec, 'debug' => $debug];
        //         save_log('gamedetail', $roleId . '====' . json_encode($result));
        //     }
        //     $res = $this->getTableQuery($sqlExec);
        //     return $res[1] ?? [];
        // } catch (Exception $e) {
        //     save_log('gamedetail', $roleId . '====' . $e->getMessage() . $e->getTraceAsString());
        //     $res['list'] = [];
        // }
    }


    //玩家牌局详情
    public function GetGameDetailRecord($uniqueid, $debug = false)
    {
        $roleId = input('roleid', '');
        $page = intval(input('page')) ? intval(input('page')) : 1;
        $limit = intval(input('limit')) ? intval(input('limit')) : 10;
        $RoomID = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $strartdate = input('strartdate') ? input('strartdate') : '2022-04-26';
        $enddate = input('enddate') ? input('enddate') : date("Y-m-d");
        $where = " and UserId=0 ";

        $array_roleid = [];
        if (!empty($roleId))
            $array_roleid = explode(',', $roleId);

        $outAll = input('outall', false);
        if (input('Action') == 'exec' && $outAll == false) {
            $this->pageSize = 1;
        }

        if (!empty($uniqueid)) {
            $where .= ' and uniqueid in(' . $uniqueid . ')';
        }

        $where .= " and AddTime>=''" . $strartdate . "'' and AddTime<=''" . $enddate . "'' ";

        $result['count'] = 0;
        $field = "UniqueId,A.RoomID,UserId,AddTime,B.RoomName,A.GameDetail ";
        $table = 'dbo.T_GameDetail';
        $join = 'LEFT JOIN  OM_MasterDB.dbo.T_GameRoomInfo B WITH (NOLOCK) ON A.RoomID=B.RoomID';
        if ($RoomID > 0) $join .= " WHERE convert(int,A.roomid/10)*10=$RoomID";
        $order = input('orderfield', "addtime");
        $ordertype = input('ordertype', 'desc');
        $order = "'$order $ordertype'";

        $begin = date('Y-m-d', strtotime($strartdate));
        $end = date('Y-m-d', strtotime($enddate));

        $sqlExec = "exec Proc_GetPageData '$table','$field','$where', $order,'$join','$begin','$end', $page , $limit";
        if ($debug) $result = ['sql' => $sqlExec, 'debug' => $debug];

        try {
            $res = $this->getTableQuery($sqlExec);
        } catch (Exception $exception) {
            $res['list'] = [];
        }

        if (count($res) == 2) {
            $result['list'] = $res[1];
            $result['count'] = $res[0][0]['count'];
            unset($res);
            $result['code'] = 0;
            foreach ($result['list'] as &$v) {
                $gamedata = json_decode(strtolower($v['GameDetail']), true);
                $html = '';
                $roomid = intval($v['RoomID'] / 10) * 10;
                switch ($roomid) {
                    case 1100:
                        $gameresult = preg_replace('/\s+/', '', $gamedata['gameresult']);
                        $html = lang('开奖结果') . ':' . $this->iconpath($gameresult) . '<br/>';
                        break;
                    case 2100:
                        $gameresult = preg_replace('/\s+/', '', $gamedata['gameresult']);
                        $tag = mb_substr($gameresult, 0, 1);
                        $tag = '<span style=\'font-size:16px;color:red;font-weight:bold;\'>' . lang($tag) . '</span>';
                        $html = lang('开奖结果') . ':' . $tag . $this->iconpath($gameresult) . '<br/>';
                        break;
                    case 3100:
                        $jokerstr = $gamedata['card']['clown'];
                        $joker = mb_substr($jokerstr, 0, 2);
                        $html .= lang('小丑牌') . ':' . $this->iconpath($joker) . mb_substr($jokerstr, -1) . '<br/>';
                        break;

                    case 3300:
                    case 3600:  //7up7down luckydice
                        $cardstr = $gamedata['card'];
                        $card = explode(',', $cardstr);
                        $html .= lang('开奖结果') . '&nbsp;&nbsp;';
                        $html .= lang('点数') . ':';
                        foreach ($card as $k => $sazi) {
                            $html .= $this->iconpath($sazi) . $sazi . ',';
                        }
                        $strgameresult = $gamedata['gameresult'];
                        $gameresult = explode(',', $strgameresult);
                        $html .= lang('中奖区域') . ':';
                        foreach ($gameresult as $vv) {
                            if (stripos($vv, '点数', 0) !== false) {
                                if ($vv == '点数3' && $roomid == 3600) {
                                    $vv = '点数7';
                                }
                                if (mb_substr($vv, -1) == 0) {
                                    $html .= $this->iconpath('r' . mb_substr($vv, -2)) . ',';
                                } else {
                                    $html .= $this->iconpath('r' . mb_substr($vv, -1)) . ',';
                                }
                            } else if (stripos($vv, '豹子', 0) !== false) {
                                if ($vv != '任意豹子') {
                                    $html .= $this->iconpath(mb_substr($vv, -1));
                                    $html .= $this->iconpath(mb_substr($vv, -1)) . ',';
                                } else {
                                    $html .= $this->iconpath('任意豹子') . ',';
                                }
                            } else {
                                $html .= $this->iconpath($vv) . ',';
                            }
                        }
                        $html .= '<br/>';
                        break;
                    case 3400:
                        $retstr = $gamedata['card'];
                        $cardstr = str_replace('[', '', $retstr);
                        $cardstr = str_replace(']', '', $cardstr);
                        $cards = explode(',', $cardstr);
                        $firststr = mb_substr($cards[0], 0, 2);
                        $html = lang('开奖结果') . ':' . $this->iconpath($firststr) . mb_substr($cards[0], -1) . ',';
                        if (count($cards) % 2 == 0) {
                            $html .= $this->iconpath('a');
                        } else {
                            $html .= $this->iconpath('b');
                        }
                        $html .= '<br/>';
                        break;
                    case 3700://FortuneWheel
                        $gameresult = preg_replace('/\s+/', '', $gamedata['gameresult']);
                        $html = lang('开奖结果') . ':' . $this->iconpath($gameresult) . '<br/>';
                        break;

                    case 3800: //wingo
                    case 23900: //wingo
                        $gameresult = preg_replace('/\s+/', '', $gamedata['gameresult']);
                        $bet = explode(',', $gameresult);
                        $html = lang('开奖结果') . ':';
                        foreach ($bet as $key) {
                            if (isset($key)) {
                                if ($key == '蓝色') {
                                    $key = '点数9';
                                }
                                if ($key == '红色') {
                                    $html .= $this->iconpath('红色') . ',';
                                } else if ($key == '绿色') {
                                    $html .= $this->iconpath('绿色') . ',';
                                } else if ($key == '紫色') {
                                    $html .= $this->iconpath('紫色') . ',';
                                } else {
                                    if (mb_strpos($key, '点数') !== false) {
                                        $point = mb_substr($key, -1);
                                        $html .= $this->iconpath('w' . $point) . ',';
                                    }
                                }
                            }
                        }
                        $html .= '<br/>';
                        break;
                    case 20100:  //poke king
                        $gameresult = preg_replace('/\s+/', '', $gamedata['gameresult']);
                        $resultlist = explode(',', $gameresult);
                        $html = lang('开奖结果') . ':';
                        foreach ($resultlist as $key) {
                            if ($key == '红色') {
                                $html .= $this->iconpath('大王') . ',';
                            } else if ($key == '黄色') {
                                $html .= $this->iconpath('方块') . '/' . $this->iconpath('红桃') . ',';
                            } else if ($key == '蓝色') {
                                $html .= $this->iconpath('黑桃') . '/' . $this->iconpath('梅花') . ',';
                            } else {
                                $html .= $this->iconpath($key);
                            }
                        }
                        $html .= '<br/>';
                        break; //loodo

                    case 20700:
                    case 20600:
                        $animal = ['兔子', '狮子', '熊猫', '猴子'];
                        $bird = ['猫头鹰', '老鹰', '鸡', '鸵鸟'];
                        $gameresult = preg_replace('/\s+/', '', $gamedata['gameresult']);
                        if (in_array($gameresult, $animal)) {
                            $html = lang('开奖结果') . ':' . $this->iconpath($gameresult) . '&nbsp;&nbsp;' . $this->iconpath('走兽') . '<br/>';
                        } else if (in_array($gameresult, $bird)) {
                            $html = lang('开奖结果') . ':' . $this->iconpath($gameresult) . '&nbsp;&nbsp;' . $this->iconpath('飞禽') . '<br/>';
                        } else {
                            $html = lang('开奖结果') . ':' . $this->iconpath($gameresult) . '<br/>';
                        }
                        break; //飞禽走兽

                    case 20800:
                        $fruit = ['77', 'BAR', 'LUCKY', '橙子', '大77', '大BAR', '大橙子', '大铃铛', '大柠檬', '大苹果', '大西瓜', '大星星', '铃铛', '柠檬', '苹果', '西瓜', '小77', '小BAR', '小橙子', '小铃铛', '小柠檬', '小苹果', '小西瓜', '小星星', '星星'];
                        $gameresult = preg_replace('/\s+/', '', $gamedata['gameresult']);
                        $big = $tag = '<span style=\'font-size:16px;color:green;font-weight:bold;\'>' . lang('大') . '</span>';
                        $small = $tag = '<span style=\'font-size:16px;color:mediumblue;font-weight:bold;\'>' . lang('小') . '</span>';
                        if (strpos($gameresult, ',') !== false) {
                            $fr = explode(',', $gameresult);
                            $html = lang('开奖结果') . ':';
                            foreach ($fr as $f) {
                                if (mb_substr($f, 0, 1) == '大') {
                                    $f = mb_substr($f, 1);
                                    $html .= $big . $this->iconpath($f) . ',';
                                } else if (mb_substr($f, 0, 1) == '小') {
                                    $f = mb_substr($f, 1);
                                    $html .= $small . $this->iconpath($f) . ',';
                                } else {
                                    $html .= $this->iconpath($f) . ',';
                                }
                            }
                            $html .= '<br/>';
                        } else {
                            if (mb_substr($gameresult, 0, 1) == '大') {
                                $gameresult = mb_substr($gameresult, 1);
                                $html = lang('开奖结果') . ':' . $big . $this->iconpath($gameresult) . '<br/>';
                            } else if (mb_substr($gameresult, 0, 1) == '小') {
                                $gameresult = mb_substr($gameresult, 1);
                                $html = lang('开奖结果') . ':' . $small . $this->iconpath($gameresult) . '<br/>';
                            } else {
                                $html = lang('开奖结果') . ':' . $this->iconpath($gameresult) . '<br/>';
                            }
                        }
                        break; //loodo

                    case 23600:

                        break;

                    case 23700:
                        $gameresult = preg_replace('/\s+/', '', $gamedata['gameresult']);
                        $open_result = '';
                        if ($gameresult == 0) {
                            $open_result = 'redcircle';
                        } else if ($gameresult == 1) {
                            $open_result = 'blackcircle';
                        } else if ($gameresult == 2) {
                            $open_result = '火焰';
                        }
                        $html = lang('开奖结果') . ':' . $this->iconpath($open_result) . '&nbsp;&nbsp;<br/>';
                        break;

                    case 23800:
                        if (isset($gamedata['bombpaymult']))
                            $html = lang('爆破位置') . ':' . $gamedata['bombpaymult'] . '<br/>';
                        else
                            $html = lang('爆破位置') . ':<br/>';
                        break;


                }
                $drawcount = 0;
                $retdetail = $this->gamedetailhtml($v['UniqueId'], $array_roleid, $v['AddTime'], $gamedata, $roomid, $drawcount);
                if (!empty($retdetail['html']))
                    $v['detail'] = $html . $retdetail['html'];

                if (!empty($retdetail['winlost']))
                    $v['winlost'] = $retdetail['winlost'];
                $v['AddTime'] = date('Y-m-d H:i:s', strtotime($v['AddTime']));
                $v['RoomName'] = lang($v['RoomName']);
                $v['drawcount'] = $drawcount;
                $tax = 0;//$this->getDrawTaxByUniqueId($v['UniqueId'], $v['AddTime']);
                $v['Tax'] = $tax;
                //if (floatval($v['GameRoundRunning']) == 0) $v['FreeGame'] = '是';
            }
            unset($v);
        }
        return $result;
    }


    public function getDrawTaxByUniqueId($uniqueid, $datetime)
    {
        $key = 'DetailTax::' . $datetime . $uniqueid;
        $tax = Redis::get($key);
        if (empty($tax)) {
            $gameoc = new GameOCDB();
            $adddate = date('Ymd', strtotime($datetime));
            $tablename = 'T_UserGameChangeLogs_' . $adddate;
            $sql = 'SELECT ISNULL(sum(tax),0) as Tax  FROM ' . $tablename . '(nolock) where SerialNumber=\'' . $uniqueid . '\'';
            $result = $gameoc->setTable($tablename)->getTableQuery($sql);
            if (isset($result[0]['Tax'])) {
                $tax = FormatMoney($result[0]['Tax']);
                Redis::set($key, $tax);
            }
        }
        return $tax;
    }


    ///游戏详情
    public function gamedetailhtml($uniqueid, $userid, $adddate, $gamedata, $room_id, &$drawcount)
    {
        $table = 'T_GameDetail_' . date('Ymd', strtotime($adddate));
        $sql = 'select UserId,RoomID,GameDetail from  ' . $table . ' where UserId>0 and uniqueid=' . $uniqueid . ' order by UserId';
        $list = $this->getTableQuery($sql);
        if (empty($list)) return '';

        $drawcount = count($list);
        //处理排序
        if (count($list) > 1) {
            foreach ($list as $kk => &$vv) {
                $dd = json_decode($vv['GameDetail'], true);
                switch ($room_id) {
                    case 1100: //龙虎斗荣耀厅
                        $bet = $dd['bet'];
                        $totalbet = intval($bet['dragon']) + intval($bet['equal']) + intval($bet['tiger']);
                        $vv['totalbet'] = intval($totalbet);
                        break;
                    case 2100: //奔驰宝马荣耀厅
                        $bet = $dd['bet'];
                        $totalbet = intval($bet['大 宝马']) + intval($bet['大 宝时捷']) + intval($bet['大 奔驰'])
                            + intval($bet['大 大众']) + intval($bet['小 宝马']) + intval($bet['小 宝时捷'])
                            + intval($bet['小 奔驰']) + intval($bet['小 大众']);
                        $vv['totalbet'] = intval($totalbet);
                        break;

                    case 20100:  //poke king
                        $bet = $dd['bet'];
                        $totalbet = 0;
                        foreach ($bet as $key => $v3) {
                            $totalbet += $v3;
                        }
                        $vv['totalbet'] = intval($totalbet);
                        break;
                    case 20200: //QuickLudo2 01
                    case 20400:
                    case 20300:
                    case 20500:
                        $chairid = $dd['player']['chairid'];
                        $totalbet = $gamedata['bet']['player' . $chairid];
                        $vv['totalbet'] = intval($totalbet);
                        break;


                    //teepatti类游戏
                    case 2500:
                    case 2700:
                    case 2800:
                    case 2900:
                    case 3000:
                    case 3500:
                        $chairid = $dd['player']['chairid'];
                        $totalbet = $gamedata['bet']['player' . $chairid];
                        $vv['totalbet'] = intval($totalbet);
                        break;

                    case 2600: //Rummy03
                    case 3100: //10Card03
                        $vv['totalbet'] = 0;
                        break;

                    case 3200:
                        $vv['totalbet'] = 0;
                        break;
                    case 3300:
                    case 3600:
                        $card = $dd['bet'];
                        $totalbet = 0;
                        foreach ($card as $key2 => $v2) {
                            $totalbet += $v2;
                        }
                        $vv['totalbet'] = intval($totalbet);
                        break;
                    case 3400: //ab面
                        $totalbet = 0;
                        if ($dd['BAT_ANDAR'] > 0) {
                            $totalbet += $dd['BAT_ANDAR'];
                        }
                        if ($dd['BAT_BAHAR'] > 0) {
                            $totalbet += $dd['BAT_BAHAR'];
                        }
                        if ($dd['BAT_CLUB'] > 0) {
                            $totalbet += $dd['BAT_CLUB'];
                        }

                        if ($dd['BAT_DIAMOND'] > 0) {
                            $totalbet += $dd['BAT_DIAMOND'];
                        }

                        if ($dd['BAT_HEART'] > 0) {
                            $totalbet += $dd['BAT_HEART'];
                        }

                        if ($dd['BAT_SPADE'] > 0) {
                            $totalbet += $dd['BAT_SPADE'];
                        }
                        $vv['totalbet'] = intval($totalbet);
                        break;
                    case 3700: //FortuneWheel
                        $bet = $dd['bet'];
                        $totalbet = intval($bet['blue']) + intval($bet['red']) + intval($bet['yellow']);
                        $vv['totalbet'] = intval($totalbet);
                        break;
                    case 3800: //wingo
                    case 23900: //wingo
                        $totalbet = 0;
                        $bet = $dd['bet'];
                        foreach ($bet as $key => $v3) {
                            $totalbet += $v3;
                        }
                        $vv['totalbet'] = intval($totalbet);
                        break;
                    case 20700:
                    case 20600:
                        $bet = $dd['bet'];
                        $totalbet = intval($bet['猴子']) + intval($bet['鸡']) + intval($bet['老鹰'])
                            + intval($bet['猫头鹰']) + intval($bet['狮子']) + intval($bet['兔子'])
                            + intval($bet['鸵鸟']) + intval($bet['熊猫']) + intval($bet['鲨鱼']);

                        $vv['totalbet'] = intval($totalbet);
                        break;

                    case 20800:
                        $totalbet = 0;
                        if (!empty($dd['bet'])) {
                            $bet = $dd['bet'];
                            if (!empty($bet['LUCKY'])) {
                                $totalbet = intval($bet['77']) + intval($bet['BAR']) + intval($bet['LUCKY'])
                                    + intval($bet['橙子']) + intval($bet['大77']) + intval($bet['大BAR'])
                                    + intval($bet['大橙子']) + intval($bet['大铃铛']) + intval($bet['大柠檬'])
                                    + intval($bet['大苹果']) + intval($bet['大西瓜']) + intval($bet['大星星'])
                                    + intval($bet['铃铛']) + intval($bet['柠檬']) + intval($bet['苹果'])
                                    + intval($bet['西瓜']) + intval($bet['小77']) + intval($bet['小BAR']) + intval($bet['小橙子'])
                                    + intval($bet['小铃铛']) + intval($bet['小柠檬']) + intval($bet['小苹果']) + intval($bet['小西瓜'])
                                    + intval($bet['小星星']) + intval($bet['星星']);
                            }
                        }
                        $vv['totalbet'] = intval($totalbet);
                        break;
                    case 23600: //mine

                        break;

                    case 23700: //double
                        $totalbet = 0;
                        if (!empty($dd['bet'])) {
                            $bet = $dd['bet'];
                            $totalbet = intval(isset($bet['红色']) ? $bet['红色'] : 0) + intval(isset($bet['灰色']) ? $bet['灰色'] : 0) + intval(isset($bet['']) ? $bet[''] : 0);
                        }
                        $vv['totalbet'] = $totalbet;
                        break;
                    case 23800://crash
                        $bet = 0;
                        if (!empty($dd['Bet'])) {
                            $bet = $dd['Bet'];
                        }
                        $vv['totalbet'] = intval($bet);
                        break;

                    default:
                        $vv['totalbet'] = 0;
                        break;
                }
            }
            unset($vv);
            $key = array_column(array_values($list), 'totalbet');
            array_multisort($key, SORT_DESC, $list);
        }

        $realplayer = array_column($list, 'UserId');

        $html = '';
        $totalwin = 0;
        $selectwin = 0;


        //总的结算游戏详情模板
        switch ($room_id) {
            case 2500:
            case 2700:
            case 2800:
            case 2900:
            case 3000:
            case 3500:
                $bet = $gamedata['bet'];
                arsort($bet);
                $betindex = [];
                foreach ($bet as $k => $v) {
                    $index = str_replace('player', '', $k);
                    array_push($betindex, $index);
                }
                foreach ($betindex as $i) {
                    $totalbet = 0;
                    if (isset($gamedata['card']['player' . $i])) {
//                            if ($i == $chareid) {
//                                if($v['UserId']==$userid){
//                                    $html .= '<span style=\'color:red\'>';
//                                    $html .=  $v['UserId'] . '&nbsp;';
//                                }
//                                else{
//                                    $html .=  $v['UserId'] . '&nbsp;';
//                                }
//                            } else {
//                                $html .= lang('机器人') . '&nbsp;';
//                            }
                        $html .= '[BEGIN' . $i . ']  [player' . $i . '] &nbsp;';
                    }
                    if (isset($gamedata['initcard']['player' . $i])) {
                        $html .= lang('初始牌') . ':{';
                        $strcard = $gamedata['initcard']['player' . $i];
                        $strcard = str_replace('[', '', $strcard);
                        $strcard = str_replace(']', '', $strcard);
                        $cards = explode(',', $strcard);
                        foreach ($cards as $item) {
                            $icon = $this->iconpath(mb_substr($item, 0, 2));
                            if (mb_strlen($item) > 3) {
                                $html .= $icon . mb_substr($item, -2) . ',';
                            } else {
                                $html .= $icon . mb_substr($item, -1) . ',';
                            }
                        }
                        $html .= '},';
                    }

                    if (isset($gamedata['card']['player' . $i])) {
                        $strcard = $gamedata['card']['player' . $i];
                        if (stripos($strcard, 'OldCard') !== false) {

                            $html .= lang('初始牌') . ':{';
                            $strcard = str_replace('[', '', $strcard);
                            $strcard = str_replace(']', '', $strcard);
                            $cards = explode(',', $strcard);
                            $cards = array_reverse($cards);
                            $card1 = [];
                            $card2 = [];
                            for ($j = 0; $j < 3; $j++) {
                                array_push($card1, $cards[$j]);
                                array_push($card2, $cards[$j + 4]);
                            }
                            $card1 = array_reverse($card1);
                            $card2 = array_reverse($card2);
                            array_push($card1, 'oldcard');
                            $cards = array_merge($card1, $card2);
                            foreach ($cards as $item) {
                                if (strtolower($item == 'oldcard')) {
                                    $html .= '},' . lang('最终牌') . ':{';
                                } else {
                                    if (mb_strlen($item) > 3) {
                                        $icon = $this->iconpath(mb_substr($item, 0, 2));
                                        $html .= $icon . mb_substr($item, -2) . ',';
                                    } else {
                                        $icon = $this->iconpath(mb_substr($item, 0, 2));
                                        $html .= $icon . mb_substr($item, -1) . ',';
                                    }
                                }
                            }
                            $html .= '}';
                        } else {
                            $html .= lang('最终牌') . ':{';
                            $strcard = $gamedata['card']['player' . $i];
                            $strcard = str_replace('[', '', $strcard);
                            $strcard = str_replace(']', '', $strcard);
                            $cards = explode(',', $strcard);
                            foreach ($cards as $item) {
                                if (mb_strlen($item) > 3) {
                                    $icon = $this->iconpath(mb_substr($item, 0, 2));
                                    $html .= $icon . mb_substr($item, -2) . ',';
                                } else {
                                    $icon = $this->iconpath(mb_substr($item, 0, 2));
                                    $html .= $icon . mb_substr($item, -1) . ',';
                                }
                            }
                            $html .= '}';
                        }

                    }
                    if (isset($gamedata['card']['player' . $i])) {
                        $totalbet = intval($gamedata['bet']['player' . $i]);
                        $html .= '&nbsp;&nbsp;&nbsp;' . lang('总下注') . ':' . FormatMoney($totalbet) . '&nbsp;&nbsp;&nbsp;';
                        $html .= '&nbsp;&nbsp;&nbsp;' . lang('总输赢') . ': [totalwin' . $i . '] &nbsp; [END' . $i . ']';
//                        if ($i == $chareid) {
//                            $winlost = 0;
//                            if (isset($dd['win'])) {
//                                $winlost = $dd['win'];
//                            } else if (isset($dd['lost'])) {
//                                $winlost = $dd['lost'];
//                            } else if (isset($dd['WinScore'])) {
//                                $winlost = $dd['WinScore'];
//                            }
//                            $totalwin = $winlost;
//
//                            if ($userid == $v['UserId'])
//                                $html .= '</span>';
//                        }
                        $html .= '<br/>';
                    }
                }
                break;   //teenpatti
            case 2600:
            case 3100:
                $bet = $gamedata['bet'];
                arsort($bet);
                $betindex = [];
                foreach ($bet as $k => $v) {
                    $index = str_replace('player', '', $k);
                    array_push($betindex, $index);
                }
                foreach ($betindex as $i) {
                    if (isset($gamedata['card']['player' . $i])) {
                        $html .= '[BEGIN' . $i . ']  [player' . $i . '] &nbsp;';
//                    if ($i == $chareid) {
//                        if ($userid == $v['UserId']) {
//                            $html .= '<span style=\'color:red\'>' . $v['UserId'] . '&nbsp;<br/>';
//                        } else {
//                            $html .= $v['UserId'] . '&nbsp;<br/>';
//                        }
//                        $html .= lang('初始牌') . ':{';
//                    } else {
//                        $html .= lang('机器人') . '&nbsp;<br/>';
//                        $html .= lang('初始牌') . ':{';
//                    }
                        $strcard = $gamedata['card']['player' . $i];
                        $html .= lang('初始牌') . ':{';
                        $strcard = str_replace('[', '', $strcard);
                        $strcard = str_replace(']', '', $strcard);
                        $cards = explode(',', $strcard);
                        foreach ($cards as $item) {
                            $icon = $this->iconpath(mb_substr($item, 0, 2));
                            if (mb_strlen($item) > 3) {
                                $dot = mb_substr($item, -2);
                            } else {
                                $dot = mb_substr($item, -1);
                            }
                            if ($dot == '王') {
                                $dot = lang('王');
                            }
                            $html .= $icon . $dot . ',';
                        }

                        $html .= '}<br/>' . lang('摆牌') . ':{';
                        $strcard = $gamedata['arrange']['player' . $i];
                        $strcard = mb_substr($strcard, 1, strlen($strcard));
                        $pattern = '/(?<=\[).+?(?=\])/';
                        preg_match_all($pattern, $strcard, $matchs);
                        $mchlist = $matchs[0];
                        foreach ($mchlist as $m) {
                            $html .= '【';
                            if (strripos($m, ',') !== false) {
                                $cards = explode(',', $m);
                                foreach ($cards as $item) {
                                    $icon = $this->iconpath(mb_substr($item, 0, 2));
                                    if (mb_strlen($item) > 3) {
                                        $dot = mb_substr($item, -2);
                                    } else {
                                        $dot = mb_substr($item, -1);
                                    }
                                    if ($dot == '王') {
                                        $dot = lang('王');
                                    }
                                    $html .= $icon . $dot;

                                }
                            } else {
                                $icon = $this->iconpath(mb_substr($m, 0, 2));
                                if (mb_strlen($m) > 3) {
                                    $dot = mb_substr($m, -2);
                                } else {
                                    $dot = mb_substr($m, -1);
                                }
                                if ($dot == '王') {
                                    $dot = lang('王');
                                }
                                $html .= $icon . $dot;
                            }
                            $html .= '】,';
                        }
                        $html .= '}';
                        $totalbet = intval($gamedata['bet']['player' . $i]);
                        $html .= '&nbsp;&nbsp;&nbsp;' . lang('总下注') . ':' . FormatMoney($totalbet) . '&nbsp;&nbsp;&nbsp;';
                        $html .= '&nbsp;&nbsp;&nbsp;' . lang('总输赢') . ': [totalwin' . $i . '] &nbsp; [END' . $i . ']';
                        $html .= '<br/>';
                    }
                }

                break;

            case 3200:
                $bet = $gamedata['bet'];
                arsort($bet);
                $betindex = [];
                foreach ($bet as $k => $v) {
                    $index = str_replace('player', '', $k);
                    array_push($betindex, $index);
                }
                foreach ($betindex as $i) {
                    if (isset($gamedata['bet']['player' . $i])) {
                        $playerid = $gamedata['user']['player' . $i];
                        if (in_array($playerid, $userid)) {
                            $html .= '<span style=\'color:red\'>';
                        }
                        if (in_array($playerid, $realplayer)) {
                            $html .= $this->showdetail($playerid) . ' &nbsp;';
                        } else {
                            $html .= '机器人 &nbsp;';
                        }
                        $points = $gamedata['pointsdetail']['detail']['player' . $i];
                        $html .= lang('每局分数') . ':{';
                        $html .= implode(',', $points);
                        $html .= '}&nbsp;&nbsp;';
                        $totalbet = intval($gamedata['bet']['player' . $i]);
                        $html .= '&nbsp;&nbsp;&nbsp;' . lang('总下注') . ':' . FormatMoney($totalbet) . '&nbsp;&nbsp;&nbsp;';
                        $html .= '&nbsp;&nbsp;&nbsp;' . lang('总输赢') . ': ' . FormatMoney($gamedata['win']['player' . $i]) . '&nbsp;';
                        if (in_array($playerid, $userid)) {
                            $html .= '</span>';
                        }
                        $html .= '<br/>';
                    }
                }
                break;
            case 20200: //QuickLudo2 01
            case 20400:
            case 20300:
            case 20500:
                if (isset($gamedata['piece'])) {
                    $piece = $gamedata['piece'];
                    $bet = $gamedata['bet'];
                    arsort($bet);
                    $betindex = [];
                    foreach ($bet as $k => $v) {
                        $index = str_replace('player', '', $k);
                        array_push($betindex, $index);
                    }
                    foreach ($betindex as $k) {
                        if (isset($piece['player' . $k])) {
//                            if ($chareid == $k) {
//                                if ($userid == $v['UserId']) {
//                                    $html .= '<span style=\'color:red\'>' . $v['UserId'] . '&nbsp;{';
//                                } else {
//                                    $html .= $v['UserId'] . '&nbsp;{';
//                                }
//                            } else {
//                                $html .= lang('机器人') . '&nbsp;{';
//                            }
                            $html .= '[BEGIN' . $k . ']  [player' . $k . '] &nbsp;';
                            $drawdetail = $piece['player' . $k];
                            $html .= '【&nbsp;';
                            foreach ($drawdetail as $j => $item) {
                                //$html .= 'No.' . $j . '&nbsp;&nbsp;步数:' . $item . ',&nbsp;';
                                $stepname = '';
                                if ($item == 1) {
                                    $stepname = 'Born';
                                } else if ($item == 57) {
                                    $stepname = 'Born';
                                } else if ($item == 0) {
                                    $stepname = 'Home';
                                } else {
                                    $stepname = lang('步数') . $item;
                                }
                                $html .= $stepname . ',&nbsp;';
                            }
                            $html .= '】&nbsp;';
                            $greenleft = $gamedata['greenleft']['player' . $k];
                            $html .= '&nbsp;&nbsp;' . lang('超时剩余次数') . ':' . $greenleft;
                            $html .= '}';
                            $totalbet = intval($gamedata['bet']['player' . $k]);
                            $html .= '&nbsp;&nbsp;&nbsp;' . lang('总下注') . ':' . FormatMoney($totalbet) . '&nbsp;&nbsp;';
                            $html .= '&nbsp;&nbsp;&nbsp;' . lang('总输赢') . ': [totalwin' . $k . '] &nbsp; [END' . $k . ']';
                            $html .= '<br/>';
                        }
                    }
                }


                break; //


        }

        //玩家牌局详情
        foreach ($list as $k => &$v) {
            $dd = json_decode($v['GameDetail'], true);
            $winlost = 0;
            $roomid = intval($v['RoomID'] / 10) * 10;
            switch ($roomid) {
                case 1100: //龙虎斗荣耀厅
                    if (in_array($v['UserId'], $userid)) {
                        $html .= '<span style=\'color:red\'>';
                    }
                    $html .= $this->showdetail($v['UserId']) . '&nbsp;{';
                    $bet = $dd['bet'];
                    if ($bet['dragon'] > 0)
                        $html .= $this->iconpath('dragon') . FormatMoney($bet['dragon']) . '&nbsp;&nbsp;';
                    if ($bet['equal'] > 0)
                        $html .= $this->iconpath('equal') . FormatMoney($bet['equal']) . '&nbsp;&nbsp;';
                    if ($bet['tiger'] > 0)
                        $html .= $this->iconpath('tiger') . FormatMoney($bet['tiger']) . '&nbsp;&nbsp;';
                    $html .= '}';
                    $totalbet = intval($bet['dragon']) + intval($bet['equal']) + intval($bet['tiger']);
                    $html .= '&nbsp;&nbsp;&nbsp;' . lang('总下注') . ':' . FormatMoney($totalbet) . '&nbsp;&nbsp;&nbsp;';

                    break;
                case 2100: //奔驰宝马荣耀厅
                    if (in_array($v['UserId'], $userid)) {
                        $html .= '<span style=\'color:red\'>';
                    }
                    $html .= $this->showdetail($v['UserId']) . '&nbsp;{';
                    $big = $tag = '<span style=\'font-size:16px;color:green;font-weight:bold;\'>' . lang('大') . '</span>';
                    $small = $tag = '<span style=\'font-size:16px;color:mediumblue;font-weight:bold;\'>' . lang('小') . '</span>';
                    $bet = $dd['bet'];
                    if ($bet['大 宝马'] > 0) {
                        $html .= $big . $this->iconpath('大宝马') . FormatMoney($bet['大 宝马']) . '&nbsp;&nbsp;';
                    }

                    if ($bet['大 宝时捷'] > 0) {
                        $html .= $big . $this->iconpath('大宝时捷') . FormatMoney($bet['大 宝时捷']) . '&nbsp;&nbsp;';
                    }

                    if ($bet['大 奔驰'] > 0)
                        $html .= $big . $this->iconpath('大奔驰') . FormatMoney($bet['大 奔驰']) . '&nbsp;&nbsp;';
                    if ($bet['大 大众'] > 0)
                        $html .= $big . $this->iconpath('大大众') . FormatMoney($bet['大 大众']) . '&nbsp;&nbsp;';
                    if ($bet['小 宝马'] > 0)
                        $html .= $small . $this->iconpath('小宝马') . FormatMoney($bet['小 宝马']) . '&nbsp;&nbsp;';
                    if ($bet['小 宝时捷'] > 0)
                        $html .= $small . $this->iconpath('小宝时捷') . FormatMoney($bet['小 宝时捷']) . '&nbsp;&nbsp;';
                    if ($bet['小 奔驰'] > 0)
                        $html .= $small . $this->iconpath('小奔驰') . FormatMoney($bet['小 奔驰']) . '&nbsp;&nbsp;';
                    if ($bet['小 大众'] > 0)
                        $html .= $small . $this->iconpath('小大众') . FormatMoney($bet['小 大众']) . '&nbsp;&nbsp;';
                    $html .= '}';
                    $totalbet = intval($bet['大 宝马']) + intval($bet['大 宝时捷']) + intval($bet['大 奔驰'])
                        + intval($bet['大 大众']) + intval($bet['小 宝马']) + intval($bet['小 宝时捷'])
                        + intval($bet['小 奔驰']) + intval($bet['小 大众']);
                    $html .= '&nbsp;&nbsp;&nbsp;' . lang('总下注') . ':' . FormatMoney($totalbet) . '&nbsp;&nbsp;&nbsp;';
                    break;

                case 20100:  //poke king
                    if (in_array($v['UserId'], $userid)) {
                        $html .= '<span style=\'color:red\'>';
                    }
                    $html .= $this->showdetail($v['UserId']) . '&nbsp;{';
                    $bet = $dd['bet'];
                    $totalbet = 0;
                    foreach ($bet as $key => $v3) {
                        $totalbet += $v3;
                        if ($key == '红色') {
                            $html .= $this->iconpath('大王') . FormatMoney($v3) . ',';
                        } else if ($key == '黄色') {
                            $html .= $this->iconpath('方块') . '/' . $this->iconpath('红桃') . FormatMoney($v3) . ',';
                        } else if ($key == '蓝色') {
                            $html .= $this->iconpath('黑桃') . '/' . $this->iconpath('梅花') . FormatMoney($v3) . ',';
                        } else {
                            $html .= $this->iconpath($key) . FormatMoney($v3) . ',';
                        }
                    }
                    $html .= '}';
                    $html .= '&nbsp;&nbsp;&nbsp;' . lang('总下注') . ':' . FormatMoney($totalbet) . '&nbsp;&nbsp;&nbsp;';
                    break;
                case 20200: //QuickLudo2 01
                case 20400:
                case 20300:
                case 20500:

                    $chareid = $dd['player']['chairid'];
                    $winlost = 0;
                    if (isset($dd['win'])) {
                        $winlost = $dd['win'];
                    } else if (isset($dd['lost'])) {
                        $winlost = $dd['lost'];
                    } else if (isset($dd['WinScore'])) {
                        $winlost = $dd['WinScore'];
                    }
                    $totalwin += $winlost;
                    $html = str_replace('[player' . $chareid . ']', $this->showdetail($v['UserId']), $html);
                    $html = str_replace('[totalwin' . $chareid . ']', FormatMoney($winlost), $html);
                    if (in_array($v['UserId'], $userid)) {
                        $selectwin = $winlost;
                        $html = str_replace('[BEGIN' . $chareid . ']', '<span style=\'color:red\'>', $html);
                        $html = str_replace('[END' . $chareid . ']', '</span>', $html);
                    } else {

                        $html = str_replace('[BEGIN' . $chareid . ']', '', $html);
                        $html = str_replace('[END' . $chareid . ']', '', $html);
                    }

                    break;
                //teepatti类游戏
                case 2500:
                case 2700:
                case 2800:
                case 2900:
                case 3000:
                case 3500:
                    $chareid = $dd['player']['chairid'];
                    $winlost = 0;
                    if (isset($dd['win'])) {
                        $winlost = $dd['win'];
                    } else if (isset($dd['lost'])) {
                        $winlost = $dd['lost'];
                    } else if (isset($dd['WinScore'])) {
                        $winlost = $dd['WinScore'];
                    }
                    $totalwin += $winlost;
                    $html = str_replace('[player' . $chareid . ']', $this->showdetail($v['UserId']), $html);
                    $html = str_replace('[totalwin' . $chareid . ']', FormatMoney($winlost), $html);
                    if (in_array($v['UserId'], $userid)) {
                        $selectwin = $winlost;
                        $html = str_replace('[BEGIN' . $chareid . ']', '<span style=\'color:red\'>', $html);
                        $html = str_replace('[END' . $chareid . ']', '</span>', $html);
                    } else {

                        $html = str_replace('[BEGIN' . $chareid . ']', '', $html);
                        $html = str_replace('[END' . $chareid . ']', '', $html);
                    }

                    break;
                case 2600: //Rummy03
                case 3100: //10Card03
                    $chareid = $dd['player']['chairid'];
                    $winlost = 0;
                    if (isset($dd['win'])) {
                        $winlost = $dd['win'];
                    } else if (isset($dd['lost'])) {
                        $winlost = $dd['lost'];
                    } else if (isset($dd['WinScore'])) {
                        $winlost = $dd['WinScore'];
                    }
                    $html = str_replace('[player' . $chareid . ']', $this->showdetail($v['UserId']), $html);
                    $html = str_replace('[totalwin' . $chareid . ']', FormatMoney($winlost), $html);
                    $totalwin += $winlost;
                    if (in_array($v['UserId'], $userid)) {
                        $selectwin = $winlost;
                        $html = str_replace('[BEGIN' . $chareid . ']', '<span style=\'color:red\'>', $html);
                        $html = str_replace('[END' . $chareid . ']', '</span>', $html);
                    } else {
                        $html = str_replace('[BEGIN' . $chareid . ']', '', $html);
                        $html = str_replace('[END' . $chareid . ']', '', $html);
                    }

                    break;
                case 3300:
                case 3600:
                    if (in_array($v['UserId'], $userid)) {
                        $html .= '<span style=\'color:red\'>';
                    }
                    $html .= $this->showdetail($v['UserId']) . '&nbsp;{';
                    $card = $dd['bet'];
                    $totalbet = 0;
                    foreach ($card as $key2 => $v2) {
                        $totalbet += $v2;
                        $v2 = FormatMoney($v2);
                        if (stripos($key2, '点数', 0) !== false) {
                            if (mb_substr($key2, -1) == 0) {
                                $html .= $this->iconpath('r' . mb_substr($key2, -2)) . $v2 . ',';
                            } else {
                                $html .= $this->iconpath('r' . mb_substr($key2, -1)) . $v2 . ',';
                            }
                        } else if (stripos($key2, '豹子', 0) !== false) {
                            if ($key2 != '任意豹子') {
                                $html .= $this->iconpath(mb_substr($key2, -1));
                                $html .= $this->iconpath(mb_substr($key2, -1)) . $v2 . ',';
                            } else {
                                $html .= $this->iconpath('任意豹子') . $v2 . ',';
                            }
                        } else {
                            $key2 = strtolower($key2);
                            $html .= $this->iconpath($key2) . $v2 . ',';
                        }
                    }
                    $html .= '}';
                    $html .= '&nbsp;&nbsp;&nbsp;' . lang('总下注') . ':' . FormatMoney($totalbet) . '&nbsp;&nbsp;&nbsp;';
                    break; //7up7down


                case 3400:
                    $totalbet = 0;
                    if (in_array($v['UserId'], $userid)) {
                        $html .= '<span style=\'color:red\'>';
                    }
                    $html .= $this->showdetail($v['UserId']) . '&nbsp;{';
                    if ($dd['BAT_ANDAR'] > 0) {
                        $totalbet += $dd['BAT_ANDAR'];
                        $html .= $this->iconpath('a') . FormatMoney($dd['BAT_ANDAR']) . ',';
                    }
                    if ($dd['BAT_BAHAR'] > 0) {
                        $totalbet += $dd['BAT_BAHAR'];
                        $html .= $this->iconpath('b') . FormatMoney($dd['BAT_BAHAR']) . ',';
                    }
                    if ($dd['BAT_CLUB'] > 0) {
                        $totalbet += $dd['BAT_CLUB'];
                        $html .= $this->iconpath('梅花') . FormatMoney($dd['BAT_CLUB']) . ',';
                    }

                    if ($dd['BAT_DIAMOND'] > 0) {
                        $totalbet += $dd['BAT_DIAMOND'];
                        $html .= $this->iconpath('方块') . FormatMoney($dd['BAT_DIAMOND']) . ',';
                    }

                    if ($dd['BAT_HEART'] > 0) {
                        $totalbet += $dd['BAT_HEART'];
                        $html .= $this->iconpath('红桃') . FormatMoney($dd['BAT_HEART']) . ',';
                    }

                    if ($dd['BAT_SPADE'] > 0) {
                        $totalbet += $dd['BAT_SPADE'];
                        $html .= $this->iconpath('黑桃') . FormatMoney($dd['BAT_SPADE']) . ',';
                    }
                    $html .= '}';
                    $html .= '&nbsp;&nbsp;&nbsp;' . lang('总下注') . ':' . FormatMoney($totalbet) . '&nbsp;&nbsp;&nbsp;';
                    break;

                case 3700: //FortuneWheel
                    if (in_array($v['UserId'], $userid)) {
                        $html .= '<span style=\'color:red\'>';
                    }
                    $html .= $this->showdetail($v['UserId']) . '&nbsp;{';
                    $bet = $dd['bet'];
                    if ($bet['blue'] > 0)
                        $html .= $this->iconpath('blue') . FormatMoney($bet['blue']) . '&nbsp;&nbsp;';
                    if ($bet['red'] > 0)
                        $html .= $this->iconpath('red') . FormatMoney($bet['red']) . '&nbsp;&nbsp;';
                    if ($bet['yellow'] > 0)
                        $html .= $this->iconpath('yellow') . FormatMoney($bet['yellow']) . '&nbsp;&nbsp;';
                    $html .= '}';
                    $totalbet = intval($bet['blue']) + intval($bet['red']) + intval($bet['yellow']);
                    $html .= '&nbsp;&nbsp;&nbsp;' . lang('总下注') . ':' . FormatMoney($totalbet) . '&nbsp;&nbsp;&nbsp;';

                    break;
                case 3800: //wingo
                case 23900: //wingo
                    $bet = $dd['bet'];
                    $totalbet = 0;
                    if (in_array($v['UserId'], $userid)) {
                        $html .= '<span style=\'color:red\'>';
                    }
                    $html .= $this->showdetail($v['UserId']) . '&nbsp;{';
                    foreach ($bet as $key => $v3) {
                        if (isset($key)) {
                            $totalbet += $v3;
                            if ($key == '红色') {
                                $html .= $this->iconpath('红色') . '&nbsp;' . FormatMoney($v3) . ',';
                            } else if ($key == '绿色') {
                                $html .= $this->iconpath('绿色') . '&nbsp;' . FormatMoney($v3) . ',';
                            } else if ($key == '紫色') {
                                $html .= $this->iconpath('紫色') . '&nbsp;' . FormatMoney($v3) . ',';
                            } else if ($key == '蓝色') {
                                $html .= $this->iconpath('w9') . FormatMoney($v3) . ',';
                            } else {
                                if (!empty($key)) {
                                    if (mb_stripos($key, '点数') !== false) {
                                        //$html .=$key;
                                        $point = mb_substr($key, -1);
                                        $html .= $this->iconpath('w' . $point) . FormatMoney($v3) . ',';
                                    }

                                }
                            }
                        }
                    }
                    $html .= '}';
                    $html .= '&nbsp;&nbsp;&nbsp;' . lang('总下注') . ':' . FormatMoney($totalbet) . '&nbsp;&nbsp;&nbsp;';
                    break;
                case 20700:
                case 20600:
                    if (in_array($v['UserId'], $userid)) {
                        $html .= '<span style=\'color:red\'>';
                    }
                    $html .= $this->showdetail($v['UserId']) . '&nbsp;{';
                    $bet = $dd['bet'];
                    if ($bet['猴子'] > 0) {
                        $html .= $this->iconpath('猴子') . FormatMoney($bet['猴子']) . '&nbsp;&nbsp;';
                    }
                    if ($bet['鸡'] > 0) {
                        $html .= $this->iconpath('鸡') . FormatMoney($bet['鸡']) . '&nbsp;&nbsp;';
                    }
                    if ($bet['老鹰'] > 0)
                        $html .= $this->iconpath('老鹰') . FormatMoney($bet['老鹰']) . '&nbsp;&nbsp;';
                    if ($bet['猫头鹰'] > 0)
                        $html .= $this->iconpath('猫头鹰') . FormatMoney($bet['猫头鹰']) . '&nbsp;&nbsp;';
                    if ($bet['狮子'] > 0)
                        $html .= $this->iconpath('狮子') . FormatMoney($bet['狮子']) . '&nbsp;&nbsp;';
                    if ($bet['兔子'] > 0)
                        $html .= $this->iconpath('兔子') . FormatMoney($bet['兔子']) . '&nbsp;&nbsp;';
                    if ($bet['鸵鸟'] > 0)
                        $html .= $this->iconpath('鸵鸟') . FormatMoney($bet['鸵鸟']) . '&nbsp;&nbsp;';
                    if ($bet['熊猫'] > 0)
                        $html .= $this->iconpath('熊猫') . FormatMoney($bet['熊猫']) . '&nbsp;&nbsp;';
                    if ($bet['鲨鱼'] > 0)
                        $html .= $this->iconpath('鲨鱼') . FormatMoney($bet['鲨鱼']) . '&nbsp;&nbsp;';

                    if ($bet['飞禽'] > 0)
                        $html .= $this->iconpath('飞禽') . FormatMoney($bet['飞禽']) . '&nbsp;&nbsp;';

                    if ($bet['走兽'] > 0)
                        $html .= $this->iconpath('走兽') . FormatMoney($bet['走兽']) . '&nbsp;&nbsp;';

                    $html .= '}';
                    $totalbet = intval($bet['猴子']) + intval($bet['鸡']) + intval($bet['老鹰'])
                        + intval($bet['猫头鹰']) + intval($bet['狮子']) + intval($bet['兔子'])
                        + intval($bet['鸵鸟']) + intval($bet['熊猫']) + intval($bet['鲨鱼']);
                    $html .= '&nbsp;&nbsp;&nbsp;' . lang('总下注') . ':' . FormatMoney($totalbet) . '&nbsp;&nbsp;&nbsp;';
                    break; //飞禽走兽

                case 20800:
                    if (!empty($dd['bet'])) {
                        $bet = $dd['bet'];
                        if (in_array($v['UserId'], $userid)) {
                            $html .= '<span style=\'color:red\'>';
                        }
                        $html .= $this->showdetail($v['UserId']) . '&nbsp;{';

                        $big = $tag = '<span style=\'font-size:16px;color:green;font-weight:bold;\'>' . lang('大') . '</span>';
                        $small = $tag = '<span style=\'font-size:16px;color:mediumblue;font-weight:bold;\'>' . lang('小') . '</span>';
                        if (!empty($bet['LUCKY'])) {

                            if ($bet['77'] > 0) {
                                $html .= $big . $this->iconpath('77') . FormatMoney($bet['77']) . '&nbsp;&nbsp;';
                            }
                            if ($bet['BAR'] > 0) {
                                $html .= $this->iconpath('BAR') . FormatMoney($bet['BAR']) . '&nbsp;&nbsp;';
                            }
                            if (!empty($bet['LUCKY'])) {
                                if ($bet['LUCKY'] > 0)
                                    $html .= $this->iconpath('LUCKY') . FormatMoney($bet['LUCKY']) . '&nbsp;&nbsp;';
                            }
                            if ($bet['橙子'] > 0)
                                $html .= $this->iconpath('橙子') . FormatMoney($bet['橙子']) . '&nbsp;&nbsp;';
                            if (!empty($bet['大77'])) {
                                if ($bet['大77'] > 0)
                                    $html .= $big . $this->iconpath('77') . FormatMoney($bet['大77']) . '&nbsp;&nbsp;';
                            }
                            if ($bet['大BAR'] > 0)
                                $html .= $big . $this->iconpath('大BAR') . FormatMoney($bet['大BAR']) . '&nbsp;&nbsp;';
                            if ($bet['大橙子'] > 0)
                                $html .= $big . $this->iconpath('橙子') . FormatMoney($bet['大橙子']) . '&nbsp;&nbsp;';
                            if ($bet['大铃铛'] > 0)
                                $html .= $big . $this->iconpath('铃铛') . FormatMoney($bet['大铃铛']) . '&nbsp;&nbsp;';
                            if ($bet['大柠檬'] > 0)
                                $html .= $big . $this->iconpath('柠檬') . FormatMoney($bet['大柠檬']) . '&nbsp;&nbsp;';
                            if ($bet['大苹果'] > 0)
                                $html .= $big . $this->iconpath('苹果') . FormatMoney($bet['大苹果']) . '&nbsp;&nbsp;';
                            if ($bet['大西瓜'] > 0)
                                $html .= $big . $this->iconpath('西瓜') . FormatMoney($bet['大西瓜']) . '&nbsp;&nbsp;';

                            if ($bet['大星星'] > 0)
                                $html .= $big . $this->iconpath('星星') . FormatMoney($bet['大星星']) . '&nbsp;&nbsp;';

                            if ($bet['铃铛'] > 0)
                                $html .= $small . $this->iconpath('铃铛') . FormatMoney($bet['铃铛']) . '&nbsp;&nbsp;';
                            if ($bet['柠檬'] > 0)
                                $html .= $small . $this->iconpath('柠檬') . FormatMoney($bet['柠檬']) . '&nbsp;&nbsp;';
                            if ($bet['苹果'] > 0)
                                $html .= $small . $this->iconpath('苹果') . FormatMoney($bet['苹果']) . '&nbsp;&nbsp;';
                            if ($bet['西瓜'] > 0)
                                $html .= $small . $this->iconpath('西瓜') . FormatMoney($bet['西瓜']) . '&nbsp;&nbsp;';
                            if ($bet['小77'] > 0)
                                $html .= $small . $this->iconpath('77') . FormatMoney($bet['小77']) . '&nbsp;&nbsp;';
                            if ($bet['小BAR'] > 0)
                                $html .= $small . $this->iconpath('小BAR') . FormatMoney($bet['小BAR']) . '&nbsp;&nbsp;';
                            if ($bet['小橙子'] > 0)
                                $html .= $small . $this->iconpath('橙子') . FormatMoney($bet['小橙子']) . '&nbsp;&nbsp;';
                            if ($bet['小铃铛'] > 0)
                                $html .= $small . $this->iconpath('铃铛') . FormatMoney($bet['小铃铛']) . '&nbsp;&nbsp;';
                            if ($bet['小柠檬'] > 0)
                                $html .= $small . $this->iconpath('柠檬') . FormatMoney($bet['小柠檬']) . '&nbsp;&nbsp;';
                            if ($bet['小苹果'] > 0)
                                $html .= $small . $this->iconpath('苹果') . FormatMoney($bet['小苹果']) . '&nbsp;&nbsp;';

                            if ($bet['小西瓜'] > 0)
                                $html .= $small . $this->iconpath('西瓜') . FormatMoney($bet['小西瓜']) . '&nbsp;&nbsp;';

                            if ($bet['星星'] > 0)
                                $html .= $small . $this->iconpath('星星') . FormatMoney($bet['星星']) . '&nbsp;&nbsp;';


                            $html .= '}';
                            $totalbet = intval($bet['77']) + intval($bet['BAR']) + intval($bet['LUCKY'])
                                + intval($bet['橙子']) + intval($bet['大77']) + intval($bet['大BAR'])
                                + intval($bet['大橙子']) + intval($bet['大铃铛']) + intval($bet['大柠檬'])
                                + intval($bet['大苹果']) + intval($bet['大西瓜']) + intval($bet['大星星'])
                                + intval($bet['铃铛']) + intval($bet['柠檬']) + intval($bet['苹果'])
                                + intval($bet['西瓜']) + intval($bet['小77']) + intval($bet['小BAR']) + intval($bet['小橙子'])
                                + intval($bet['小铃铛']) + intval($bet['小柠檬']) + intval($bet['小苹果']) + intval($bet['小西瓜'])
                                + intval($bet['小星星']) + intval($bet['星星']);
                            $html .= '&nbsp;&nbsp;&nbsp;' . lang('总下注') . ':' . FormatMoney($totalbet) . '&nbsp;&nbsp;&nbsp;';
                        } else {
                            $html .= '}';
                        }
                    }
                    break; //飞禽走兽

                case 23600: //mine
                    if ($v['UserId'] == $userid) {
                        $html .= '<span style=\'color:red\'>';
                    }
                    $html .= $this->showdetail($v['UserId']) . '&nbsp;{';
                    $hitpos = $v['hitpos'] ?? '';
                    $HitCount = $v['hitcount'] ?? 0;
                    $v['bombcount'] = $v['bombcount'] ?? 0;
                    $BombCount = 0;
                    if (empty($v['bombcount'])) {
                        $BombCount = $v['bombcount'];
                    }
                    $html .= lang('点击区域') . ':[' . $hitpos . '],' . lang('共点击') . ':' . $HitCount;
                    $html .= '，' . lang('埋雷数') . ':' . $BombCount;
                    $html .= '}';
                    $GameDetail = json_decode($v['GameDetail'], true);
                    $totalbet = $GameDetail['BaseScore'] ?? 0;
                    $html .= '&nbsp;&nbsp;&nbsp;' . lang('总下注') . ':' . FormatMoney($totalbet) . '&nbsp;&nbsp;&nbsp;';
                    break;

                case 23700: //double
                    if (in_array($v['UserId'], $userid)) {
                        $html .= '<span style=\'color:red\'>';
                    }
                    $html .= $this->showdetail($v['UserId']) . '&nbsp;{';
                    $bet = $dd['bet'] ?? [];
                    if (isset($bet['红色'])) {
                        if ($bet['红色'] > 0) {
                            $html .= $this->iconpath('redcircle') . FormatMoney($bet['红色']) . '&nbsp;&nbsp;';
                        }
                    }
                    if (isset($bet['灰色'])) {
                        if ($bet['灰色'] > 0) {
                            $html .= $this->iconpath('blackcircle') . FormatMoney($bet['灰色']) . '&nbsp;&nbsp;';
                        }
                    }

                    if (isset($bet[''])) {
                        if ($bet[''] > 0)
                            $html .= $this->iconpath('火焰') . FormatMoney($bet['']) . '&nbsp;&nbsp;';
                    }
                    $html .= '}';
                    $totalbet = intval(isset($bet['红色']) ? $bet['红色'] : 0) + intval(isset($bet['灰色']) ? $bet['灰色'] : 0) + intval(isset($bet['']) ? $bet[''] : 0);
                    $html .= '&nbsp;&nbsp;&nbsp;' . lang('总下注') . ':' . FormatMoney($totalbet) . '&nbsp;&nbsp;&nbsp;';
                    break;

                case 23800: //crash
                    if (in_array($v['UserId'], $userid)) {
                        $html .= '<span style=\'color:red\'>';
                    }
                    $html .= $this->showdetail($v['UserId']) . '&nbsp;{';
                    $CurPayMult = 0;
                    $PredictPay = 0;
                    if (isset($dd['CurPayMult']))
                        $CurPayMult = $dd['CurPayMult'];

                    if (isset($dd['PredictPay']))
                        $PredictPay = $dd['PredictPay'];

                    $html .= '&nbsp;&nbsp;' . lang('预设位置') . ':' . $PredictPay . '&nbsp;&nbsp;,' . lang('点击位置') . ':' . $CurPayMult . '&nbsp;&nbsp;';
                    $html .= '}';

                    if (!empty($dd['Bet'])) {
                        $bet = $dd['Bet'];
                    } else {
                        $bet = 0;
                    }
                    $html .= '&nbsp;&nbsp;&nbsp;' . lang('总下注') . ':' . FormatMoney($bet) . '&nbsp;&nbsp;&nbsp;';
                    break;

            }

            if (!in_array($roomid, [2500, 2600, 2700, 2800, 2900, 3000, 3100, 3200, 3500, 20200, 20300, 20400, 20500])) {
                if (isset($dd['win'])) {
                    $winlost = $dd['win'];
                } else if (isset($dd['lost'])) {
                    $winlost = $dd['lost'];
                } else if (isset($dd['WinScore'])) {
                    $winlost = $dd['WinScore'];
                } else {
                    $winlost = 0;
                }
                if (!in_array($roomid, [23800]))
                    $html .= lang('总输赢') . ':' . FormatMoney($winlost);
                else {
                    $winlost = 0;
                    if (!empty($dd['CurPayMult']) && !empty($dd['Bet'])) {
                        $winlost = -$gamedata['llsyswin'];
                        if (intval($dd['CurPayMult']) >= intval($gamedata['bombpaymult'])) {
                            $html .= lang('总输赢') . ':-' . FormatMoney($dd['Bet']);

                        } else {
                            $total_win = (intval($dd['CurPayMult']) - 100) * $dd['Bet'] / 100;

                            $html .= lang('总输赢') . ':' . FormatMoney($total_win);
                        }
                    } else {
                        $html .= lang('总输赢') . ':0';
                    }

                }
                $totalwin += $winlost;
                if (in_array($v['UserId'], $userid)) {
                    $selectwin = $winlost;
                    $html = $html . '</span>';
                }

            }
            $html .= '<br/>';
        }
//        if (in_array($roomid, [2500, 2600, 2700, 2800, 2900, 3000, 3100, 3500, 20200, 20300, 20400, 20500])) {
//
//        }
        if (!empty($userid)) {
            $totalwin = $selectwin;
        }
        $html = preg_replace('/\[BEGIN\d+\]/', '', $html);
        $html = preg_replace('/\[END\d+\]/', '', $html);
        $html = preg_replace('/\[totalwin\d+\]/', '', $html);

        for ($i = 0; $i < 5; $i++) {
            $html = str_replace('[player' . $i . ']', lang('机器人'), $html);
        }

        $ret['winlost'] = FormatMoney($totalwin);
        $ret['html'] = $html;
        return $ret;
    }


    private function iconpath($icon)
    {
        return '<img src="/public/icon/' . $icon . '.png" height="25" />';
    }

    private function showdetail($userid)
    {
        return '<a class="layui-bg-green" onclick="userdetail(\'' . $userid . '\')">' . $userid . '</a>';
    }


    public function updateProxyOldData($roleid, $parentid)
    {
        $sqlExec = "exec Proc_ProxyOldData_Update '$roleid','$parentid'";
        $res = $this->DBOriginQuery($sqlExec);
        return 1;
    }

    public function getXbusiness($ProxyChannelIds)
    {
        static $result = [];
        $xChannelIds = (new \app\model\GameOCDB())->getTableObject('T_ProxyChannelConfig')->where('pid', 'in', $ProxyChannelIds)->field('ProxyChannelId')->select();
        if (empty($xChannelIds)) {
            return $result;
        } else {
            $xChannelIds = array_column($xChannelIds, 'ProxyChannelId');
            $result = array_unique(array_merge($result, $xChannelIds));
            return $this->getXbusiness($xChannelIds);
        }
    }

    public function getBrl($value): int
    {

        if ($value > 5000 && $value < 10000) {
            $data = 50;
        } elseif ($value > 10000 && $value < 20000) {
            $data = 80;
        } elseif ($value > 20000 && $value < 50000) {
            $data = 150;
        } elseif ($value > 50000 && $value < 100000) {
            $data = 300;
        } elseif ($value > 100000 && $value < 300000) {
            $data = 500;
        } elseif ($value > 300000 && $value < 500000) {
            $data = 800;
        } elseif ($value > 500000 && $value < 800000) {
            $data = 1200;
        } elseif ($value > 800000) {
            $data = 1500;
        } else {
            $data = 0;
        }
        return $data;

    }


    /**
     * 代理日表
     * @param $roomlist
     * @return array
     */
    public function getSharingStatistics($iswater = false): array
    {
        $startdate = input('start', date('Y-m-d', time()));
        $enddate = input('end', date('Y-m-d', time()));
        $roleid = input('roleid');
        $parentid = input('parentid', 0);
        $where = "";

        $outAll = input('outall', false);
        if (input('Action') == 'exec' && $outAll == false) {
            $this->pageSize = 1;
        }


        $join = "LEFT JOIN CD_UserDB.dbo.T_UserProxyInfo B WITH (NOLOCK) ON A.ProxyId=B.RoleID ";
//        //外联 条件
        if ($parentid > 0) {
            $join .= ' and ParentID=' . $parentid;
        }
        if (!empty($AccountName)) $join .= " AND AccountName=''$AccountName''";
//        if (!empty($NickName)) $join .= " AND LoginName=''$NickName''";

        if ($roleid > 0)
            $where .= ' and proxyid=' . $roleid;

        if (session('merchant_OperatorId') && request()->module() == 'merchant') {
            $where .= ' and OperatorId=' . session('merchant_OperatorId');
        }
        $tab = input('tab') ?: '';
        switch ($tab) {
            case 'today':
                $startdate = date('Y-m-d');
                $enddate = $startdate;
                break;
            case 'yestoday':
                $startdate = date('Y-m-d', strtotime('-1 days'));
                $enddate = $startdate;
                break;
            case 'month':
                $startdate = date('Y-m') . '-01';
                $enddate = date('Y-m-d');

                break;
            case 'lastmonth':
                $startdate = date('Y-m-01', strtotime('-1 month'));
                $enddate = date('Y-m-d', strtotime(date('Y-m') . '-01') - 1);

                break;
            case 'week':
                $w = date('w');
                if ($w == 0) {
                    $w = 7;
                }
                $w = mktime(0, 0, 0, date('m'), date('d') - $w + 1, date('y'));
                $startdate = date('Y-m-d', $w);
                $enddate = date('Y-m-d');

                break;
            case 'lastweek':
                $w = date('w');
                if ($w == 0) {
                    $w = 7;
                }
                $w = mktime(0, 0, 0, date('m'), date('d') - $w + 1, date('y'));
                $startdate = date('Y-m-d', $w - 7 * 86400);
                $enddate = date('Y-m-d', strtotime(date('Y-m-d', $w)) - 1);
                break;
            case 'q_day':
                $startdate = date('Y-m-d', strtotime($startdate) - 86400);
                $enddate = $startdate;
                break;
            case 'h_day':
                $enddate = date('Y-m-d', strtotime($enddate) + 86400);
                $startdate = $enddate;
                break;
            default:

                break;
        }
        $begin = date('Y-m-d', strtotime($startdate));
        $end = date('Y-m-d', strtotime($enddate));

        $orderfield = input('orderfield', "AddTime");
        $ordertype = input('ordertype', 'desc');
        $order = "$orderfield $ordertype,proxyid asc ";
// 注册人数 Lv1PersonCount /充值人数 Lv1FirstDepositPlayers /充值金额DailyDeposit
        $table = 'dbo.T_ProxyDailyCollectData';
        $field = ' AddTime,ProxyId,DailyDeposit,DailyTax,DailyRunning,Lv1PersonCount,Lv1Deposit,Lv1Tax,Lv1FirstDepositPlayers,Lv1Running,A.ValidInviteCount';
        $sqlExec = "exec Proc_GetPageData '$table','$field','$where','$order','$join','$begin','$end', $this->page , $this->pageSize";
        try {
            $result = $this->getTableQuery($sqlExec);
        } catch (Exception $exception) {
            $result['list'] = [];
            $result['count'] = 0;
        }
        $userDB = new UserDB();
        $res['code'] = 0;
        $res['debug'] = true;
        $res["sql"] = $sqlExec;
        $res['list'] = [];
        $res['count'] = 0;
        if (isset($result[1]) && $result[0][0]['count'] > 0) {
            $res['count'] = $result[0][0]['count'];
            $res['list'] = $result[1];
            foreach ($res['list'] as &$v) {
                $userBankDB = new BankDB();
                $takeMoney = $userBankDB->getTableObject('UserDrawBack')
                    ->where('AccountID', $v['ProxyId'])
                    ->where('AddTime', 'between time', [date('Y-m-d 00:00:00', strtotime($v['AddTime'])), date('Y-m-d 23:59:59', strtotime($v['AddTime']))])
                    ->sum('iMoney') ?? 0;
                $v['takeMoney'] = $takeMoney / bl;
                if ($v['DailyDeposit'] == 0 && $v['takeMoney'] > 0) {
                    $v['difference'] = '-' . $v['takeMoney'];
                } else {
                    $v['difference'] = bcsub($v['DailyDeposit'], $v['takeMoney'], 2);
                }
                $turntableMoney = $userDB->getTableObject('T_Job_UserInfo')
                    ->where('RoleID', $v['ProxyId'])
                    ->where('job_key', 10014)
                    ->sum('value') ?? 0;
                $v['Money'] = $turntableMoney / bl;
                $addMoney = $userDB->getTableObject('T_Job_UserInfo')
                    ->where('RoleID', $v['ProxyId'])
                    ->where('job_key', 10015)
                    ->sum('value') ?? 0;
                $v['addMoney'] = FormatMoney($addMoney);

            }
            unset($v);
        }
        $res['startdate'] = $startdate;
        $res['enddate'] = $enddate;
        return $res;
    }

}   