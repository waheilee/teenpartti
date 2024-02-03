<?php

namespace app\admin\controller\Export;

use app\admin\controller\Main;
use app\model\GameOCDB;
use think\Exception;
use XLSXWriter;
class GameLog2Export extends Main
{

    public function export()
    {
        $writer = new XLSXWriter();
        $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
        $page = intval(input('page')) ? intval(input('page')) : 1;
        $limit = intval(input('limit')) ? intval(input('limit')) : 10;
        $RoomID = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $OperatorId = input('OperatorId') ?: '';

//        $dateRange = range(strtotime($startDate), strtotime($endDate), 86400); // 86400 秒 = 1 天
//
//        $datesArray = array_map(function ($timestamp) {
//            return date('Ymd', $timestamp);
//        }, $dateRange);
        $datesArray = $this->getData($roleId,$page,$limit,$RoomID,$OperatorId);
        $rows = $this->getExcelData($datesArray['list']);

        $writer->writeSheet($rows);
        $filename = lang('代理明细') . '-' . date('YmdHis');

        header('Content-disposition: attachment; filename="' . XLSXWriter::sanitize_filename("$filename.xlsx") . '"');
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

// 将 Excel 文件输出到浏览器
        $writer->writeToStdOut();
        exit();
    }

    public function getExcelData($dataArray)
    {
//        $data = [];
        $data[] = [
            lang('玩家ID'),
            lang('房间名'),
            lang('输赢情况'),
            lang('免费游戏'),
            lang('下注金额'),
            lang('输赢金币'),
            lang('中奖金币'),
            lang('上局金币'),
            lang('当前金币'),
            lang('创建时间')
        ];
        foreach($dataArray as $k){
            $gamestate = '';
            switch ($k['ChangeType']) {
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

            $item = [];
            $item['RoleID'] = $k['RoleID'] ?? "";
            $item['RoomName'] = $this->roomName($k['ServerID']);
            $item['gameState'] = $gamestate;
            $item['FreeGame'] = lang('否');
            $item['GameRoundRunning'] =  FormatMoney($k['GameRoundRunning']);
            $item['Money'] = FormatMoney($k['Money']);
            $item['AwardMoney'] = FormatMoney($k['RoundBets'] + $k['Money']);
            $item['PreMoney'] = FormatMoney($k['PreMoney']);
            $item['LastMoney'] = FormatMoney($k['LastMoney']);
            $item['AddTime'] = substr($k['AddTime'], 0, 19);
            $data[] = $item;
        }
        return $data;

    }


    public function getData($roleId,$page,$limit,$RoomID,$OperatorId,$debug = false)
    {
        $gameODDB = new GameOCDB();

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
            $res = $gameODDB->getTableQuery($sqlExec);
        } catch (Exception $exception) {
            $res['list'] = [];
        }

        if (count($res) == 2) {
            $result['list'] = $res[1];
            $result['count'] = $res[0][0]['count'];
            unset($res);
            $result['code'] = 0;
//            foreach ($result['list'] as &$v) {
//                if ($v['ServerID'] == 37000) {
//                    $v['RoomID'] = 37000;
//                    $v['RoomName'] = 'EvoLive';
//                }
//                if ($v['ServerID'] == 38000) {
//                    $v['RoomID'] = 38000;
//                    $v['RoomName'] = 'PP';
//                }
//                if ($v['ServerID'] == 36000) {
//                    $v['RoomID'] = 36000;
//                    $v['RoomName'] = 'PG';
//                }
//                if ($v['ServerID'] == 39000) {
//                    $v['RoomID'] = 39000;
//                    $v['RoomName'] = 'JILI';
//                }
//                if ($v['ServerID'] == 39100) {
//                    $v['RoomID'] = 39100;
//                    $v['RoomName'] = 'kingmaker';
//                }
//                if ($v['ServerID'] == 39200) {
//                    $v['RoomID'] = 39200;
//                    $v['RoomName'] = 'CQ9';
//                }
//                if ($v['ServerID'] == 40000) {
//                    $v['RoomID'] = 40000;
//                    $v['RoomName'] = 'Haba';
//                }
//                if ($v['ServerID'] == 39400) {
//                    $v['RoomID'] = 39400;
//                    $v['RoomName'] = 'Spribe';
//                }
//                if ($v['ServerID'] == 41000) {
//                    $v['RoomID'] = 41000;
//                    $v['RoomName'] = 'HackSaw';
//                }
//                if ($v['ServerID'] == 42000) {
//                    $v['RoomID'] = 42000;
//                    $v['RoomName'] = 'YES!BINGO';
//                }
//                if ($v['ServerID'] == 44000) {
//                    $v['RoomID'] = 44000;
//                    $v['RoomName'] = 'FCGame';
//                }
//                if ($v['ServerID'] == 45000) {
//                    $v['RoomID'] = 45000;
//                    $v['RoomName'] = 'TaDa';
//                }
//                if ($v['ServerID'] == 46000) {
//                    $v['RoomID'] = 46000;
//                    $v['RoomName'] = 'PPLive';
//                }
//                if ($v['ServerID'] == 47000) {
//                    $v['RoomID'] = 47000;
//                    $v['RoomName'] = 'FakePgGame';
//                }
//                $v['AwardMoney'] = FormatMoney($v['RoundBets'] + $v['Money']);
//                ConVerMoney($v['Money']);
//                ConVerMoney($v['GameRoundRunning']);
//                ConVerMoney($v['LastMoney']);
//                ConVerMoney($v['PreMoney']);
//                ConVerMoney($v['RoundBets']);
//                ConVerMoney($v['Tax']);
//                $v['FreeGame'] = lang('否');
//                $v['RoomName'] = lang($v['RoomName']);
//                $v['AddTime'] = substr($v['AddTime'], 0, 19);
//                if (floatval($v['GameRoundRunning']) == 0) $v['FreeGame'] = lang('是');
//                unset($v);
//            }

        }
        return $result;
    }

    public function roomName($serverId)
    {
        if ($serverId == 37000) {

            return  'EvoLive';
        }
        if ($serverId == 38000) {

            return 'PP';
        }
        if ($serverId == 36000) {

            return 'PG';
        }
        if ($serverId == 39000) {

            return 'JILI';
        }
        if ($serverId == 39100) {

            return 'kingmaker';
        }
        if ($serverId == 39200) {

            return 'CQ9';
        }
        if ($serverId == 40000) {

            return 'Haba';
        }
        if ($serverId == 39400) {

            return 'Spribe';
        }
        if ($serverId == 41000) {

            return 'HackSaw';
        }
        if ($serverId == 42000) {

            return 'YES!BINGO';
        }
        if ($serverId == 44000) {

            return 'FCGame';
        }
        if ($serverId == 45000) {

            return 'TaDa';
        }
        if ($serverId == 46000) {

            return 'PPLive';
        }
        if ($serverId == 47000) {

            return 'FakePgGame';
        }
        return '';
    }
}