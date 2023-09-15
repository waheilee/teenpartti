<?php

namespace app\merchant\controller;

use app\admin\controller\traits\getSocketRoom;
use app\admin\controller\traits\search;
use app\common\Api;
use app\common\GameLog;
use app\model\CommonModel;
use app\model\GameKind;
use app\model\GameOCDB;
use app\model\GameRoomInfo as RoomInfo;
use app\model\MasterDB;
use app\model\UserDB;
use redis\Redis;
use socket\QuerySocket;

class Gamectrl extends Main
{
    use getSocketRoom;
    use search;

    private $socket = null;

    public function __construct()
    {
        parent::__construct();
        $this->socket = new QuerySocket();
    }

    //游戏配置一览
    public function index()
    {

        $roomlist = $this->GetRoomList();
        if ($this->request->isAjax()) {
            $roomId = intval(input('roomId')) ? intval(input('roomId')) : 0;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 15;
            $orderby = intval(input('orderby')) ? intval(input('orderby')) : 0;
            $asc = intval(input('asc')) ? intval(input('asc')) : 0;

            $res = $this->socket->getProfitPercent($roomId);
//            halt($res);
//            $his = Api::getInstance()->sendRequest(['id' => 0], 'room', 'roompreperty');
            $db = new MasterDB();
            $his = $db->getTableQuery("SELECT RoomID,LuckyEggTaxRate/10 as mingtax,SysMaxLoseMoneyPerRound/1000 as goldmoney  FROM T_GameRoomInfo where Nullity=0 ");

            if ($res) {
                if ($roomlist) {
                    $search = '/^12\d{2}$/';
                    foreach ($res as $k => &$v) {
                        if (preg_match($search, $v['nRoomId'])) {
                            unset($res[$k]);
                            continue;
                        }
//                        if (in_array($v['nRoomId'], [1000, 1300,1400,1500,1600, 1702, 1800,1900,2000,2200,2300,2400,3000,3100,3200,3300,3400])) {
//                            unset($res[$k]);
//                            continue;
//                        }
                        $roomid = intval($v['nRoomId']/10)*10;
                        var_dump($roomId);
                        if (!in_array($roomid, [1100,2100,2500,2600,2700,2800,2900,3100,3400,3500,3900,4500,5600,5700,6200])) {
                            unset($res[$k]);
                            continue;
                        }

                        if ($v['nRoomId'] > 4000 || $v['nRoomId'] == 3901) {
                            unset($res[$k]);
                            continue;
                        }



                        foreach ($his as $v5) {
                            if ($v5['RoomID'] == $v['nRoomId']) {
                                $v['mingtax'] = $v5['mingtax'];
                                $v['goldmoney'] = $v5['goldmoney'];
                                break;
                            }
                        }
                        foreach ($roomlist as $v2) {
                            if ($v['nRoomId'] == $v2['RoomID']) {
                                $v['roomname'] = $v2['RoomName'];
                            }
                        }
                        $v['lTotalRunning'] /= bl;
                        $v['lTotalProfit'] /= bl;
                        $v['lTotalTax'] /= bl;
                        $v['lHistorySumRunning'] /= bl;
                        $v['lHistorySumProfile'] /= bl;
                        $v['lHistorySumTax'] /= bl;
                        $v['nAdjustValue'] /= bl;
                        $v['lTotalBlackTax'] /= bl;
                        $v['lMinStorage'] /= bl;
                        $v['lMaxStorage'] /= bl;
                        $v['nCtrlValue'] = intval($v['nCtrlValue']/2);

//                        $v['currentget'] = $v['lTotalTax'] + $v['lTotalProfit'];
//                        $v['totalget'] = $v['lHistorySumTax'] + $v['lHistorySumProfile'];
                    }
                    unset($v);
                    $res = array_values($res);
                }
            }

            if ($orderby > 0) {
                if ($orderby == 1) {
                    $orderbystr = 'nCtrlValue';
                } elseif ($orderby == 2) {
                    $orderbystr = 'lTotalProfit';
                } elseif ($orderby == 3) {
                    $orderbystr = 'lHistorySumTax';
                } else {
                    $orderbystr = 'lTotalBlackTax';
                }

                if ($asc == 0) {
                    $ascstr = 'asc';
                } else {
                    $ascstr = 'desc';
                }

                $res = arraySort($res, $orderbystr, $ascstr);
            }
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
            return $this->apiReturn(0, $result, '', $count, ['orderby' => $orderby, 'asc' => $asc]);
        }
        $this->assign('roomlist', $roomlist);
        return $this->fetch();
    }

    //游戏配置一览
    public function index2()
    {
        //$roomlist = Api::getInstance()->sendRequest(['id' => 0], 'room', 'kind');
        $roomlist = $this->GetRoomList();
        if ($this->request->isAjax()) {
            $roomId = intval(input('roomId')) ? intval(input('roomId')) : 0;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 15;
            $orderby = intval(input('orderby')) ? intval(input('orderby')) : 0;
            $asc = intval(input('asc')) ? intval(input('asc')) : 0;

            $res = $this->socket->getProfitPercent($roomId);

            //$his = Api::getInstance()->sendRequest(['id' => 0], 'room', 'roompreperty');
            $his = $this->getroompreperty();
            if ($res) {
                if ($roomlist) {
                    $search = '/^12\d{2}$/';
                    foreach ($res as $k => &$v) {
                        if (preg_match($search, $v['nRoomId'])) {
                            unset($res[$k]);
                            continue;
                        }
//                        if (in_array($v['nRoomId'], [1000, 1300, 1500, 1702, 1800])) {
//                            unset($res[$k]);
//                            continue;
//                        }
                        if ($v['nRoomId'] > 4000 || $v['nRoomId'] == 3901) {
                            unset($res[$k]);
                            continue;
                        }

                        foreach ($his as $v5) {
                            if ($v5['roomid'] == $v['nRoomId']) {
                                $v['mingtax'] = $v5['mingtax'];
                                $v['goldmoney'] = $v5['goldmoney'];
                                break;
                            }
                        }
                        foreach ($roomlist as $v2) {
                            if ($v['nRoomId'] == $v2['RoomID']) {
                                $v['roomname'] = $v2['RoomName'];
                            }
                        }
                        $v['lTotalRunning'] /= 1;
                        $v['lTotalProfit'] /= 1;
                        $v['lTotalTax'] /= 1;
                        $v['lHistorySumRunning'] /= 1;
                        $v['lHistorySumProfile'] /= 1;
                        $v['lHistorySumTax'] /= 1;
                        $v['nAdjustValue'] /= 1;
                        $v['lTotalBlackTax'] /= 1;
                        $v['lMinStorage'] /= 1;
                        $v['lMaxStorage'] /= 1;
                        $v['nCtrlValue'] = intval($v['nCtrlValue'] / 2);

//                        $v['currentget'] = $v['lTotalTax'] + $v['lTotalProfit'];
//                        $v['totalget'] = $v['lHistorySumTax'] + $v['lHistorySumProfile'];
                    }
                    unset($v);
                    $res = array_values($res);
                }
            }

            if ($orderby > 0) {
                if ($orderby == 1) {
                    $orderbystr = 'nCtrlValue';
                } elseif ($orderby == 2) {
                    $orderbystr = 'lTotalProfit';
                } elseif ($orderby == 3) {
                    $orderbystr = 'lHistorySumTax';
                } else {
                    $orderbystr = 'lTotalBlackTax';
                }

                if ($asc == 0) {
                    $ascstr = 'asc';
                } else {
                    $ascstr = 'desc';
                }

                $res = arraySort($res, $orderbystr, $ascstr);
            }
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
            return $this->apiReturn(0, $result, '', $count, ['orderby' => $orderby, 'asc' => $asc]);
        }
        //$roomList = Api::getInstance()->sendRequest(['id' => 0], 'room', 'kind');
        //$kindList = Api::getInstance()->sendRequest(['id' => 0], 'room', 'kindlist');
        $kindList = $this->GetRoomList();
        $this->assign('roomlist', $roomlist);
        $this->assign('kindlist', $kindList);

        $res = Api::getInstance()->sendRequest(['id' => 0], 'room', 'list');

        $this->assign('roomlist', isset($res['data']['ResultData']) ? $res['data']['ResultData'] : []);
        $this->assign('historytotal', isset($res['data']['historytotal']) ? $res['data']['historytotal'] : 0);
        $this->assign('currentscore', isset($res['data']['currentscore']) ? $res['data']['currentscore'] : 0);
        $this->assign('totalonline', isset($res['data']['totalonline']) ? $res['data']['totalonline'] : 0);

        return $this->fetch();
    }


    public function kindrate(){

        if($this->request->isAjax())
        {
            $kindlist = $this->GetKindList();
            return $this->apiReturn(0,$kindlist,'');
        }
        return $this->fetch();
    }

    //设置房间千分比
    public function setProfit()
    {

        if ($this->request->isAjax()) {
            $id = intval(input('id')) ? intval(input('id')) : 0;
            $setrange = input('setrange') ? input('setrange') : 1;
            $percent = input('percent') ? input('percent') : 0;
            $ajustvalue = input('ajustvalue') ? input('ajustvalue') : 0;
            $curstorage = input('curstorage') ? input('curstorage') : 0;
            $minstorage = input('minstorage') ? input('minstorage') : 0;
            $maxstorage = input('maxstorage') ? input('maxstorage') : 0;
            $roomctrl =input('roomctrl',0);
            $type = 1;
//            if ($curstorage < $minstorage || $curstorage > $maxstorage) {
//                return $this->apiReturn(1, [], '当前库存不能小于最小值或大于最大值');
//            }
//            if ($minstorage > $maxstorage) {
//                return $this->apiReturn(2, [], '库存下限不能大于上限');
//            }

            $res = $this->socket->setProfitPercent($type, $setrange, $id, $percent, $ajustvalue,$roomctrl*2, $curstorage, $minstorage, $maxstorage);
            $code = $res['iResult'];
            GameLog::logData(__METHOD__, $this->request->request(), ($code == 0) ? 1 : 0, $res);
            return $this->apiReturn($code);
        }

        $id = intval(input('id')) ? intval(input('id')) : 0;
        $percent = input('percent') ? input('percent') : 0;
        $ajustvalue = input('ajustvalue') ? input('ajustvalue') : 0;
        $curstorage = input('curstorage') ? input('curstorage') : 0;
        $minstorage = input('minstorage') ? input('minstorage') : 0;
        $maxstorage = input('maxstorage') ? input('maxstorage') : 0;
        $roomname = input('roomname') ? input('roomname') : 0;
        $roomctrl =input('roomctrl',0);

        $this->assign('roomId', $id);
        $this->assign('roomname', $roomname);
        $this->assign('percent', $percent);
        $this->assign('ajustvalue', $ajustvalue);
        $this->assign('curstorage', $curstorage);
        $this->assign('minstorage', $minstorage);
        $this->assign('maxstorage', $maxstorage);
        $this->assign('roomctrl',$roomctrl);

        $winrate = config('winrate');
        $this->assign('winrate', $winrate);
        return $this->fetch();
    }


    ///设置房间rtp
    public function setTigerProfit()
    {
        if ($this->request->isAjax()) {
            $id = intval(input('id')) ? intval(input('id')) : 0;
            $setrange = input('setrange') ? input('setrange') : 1;
            $percent = input('percent') ? input('percent') : 0;
            $ajustvalue = input('ajustvalue') ? input('ajustvalue') : 0;
            $curstorage = input('curstorage') ? input('curstorage') : 0;
            $minstorage = input('minstorage') ? input('minstorage') : 0;
            $maxstorage = input('maxstorage') ? input('maxstorage') : 0;
            $type = 1;
//            if ($curstorage < $minstorage || $curstorage > $maxstorage) {
//                return $this->apiReturn(1, [], '当前库存不能小于最小值或大于最大值');
//            }
//            if ($minstorage > $maxstorage) {
//                return $this->apiReturn(2, [], '库存下限不能大于上限');
//            }
//            if($ajustvalue==115)
//                $ajustvalue = 100;
//
//            if ($ajustvalue == 95)
//                $ajustvalue = 100;

            $ajustvalue = 200 - $ajustvalue;
            $res = $this->socket->setProfitPercent($type, $setrange, $id, $percent, $ajustvalue, $ajustvalue,$curstorage, $minstorage, $maxstorage);
            $code = $res['iResult'];
            $request = $this->request->request();
            unset($request['s']);
            GameLog::logData(__METHOD__, $request, ($code == 0) ? 1 : 0, $res);
            return $this->apiReturn($code);
        }

        $id = intval(input('id')) ? intval(input('id')) : 0;
        $percent = input('percent') ? input('percent') : 0;
        $ajustvalue = input('ajustvalue') ? input('ajustvalue') : 0;
//        $curstorage = input('curstorage') ? input('curstorage') : 0;
//        $minstorage = input('minstorage') ? input('minstorage') : 0;
//        $maxstorage = input('maxstorage') ? input('maxstorage') : 0;
        $roomname = input('roomname') ? input('roomname') : 0;
        $winrate = config('winrate');

//        if($ajustvalue==100){
//            $ajustvalue =95;
//        }
//
//        unset($k);

        $this->assign('winrate', $winrate);
        $this->assign('roomId', $id);
        $this->assign('roomname', $roomname);
        $this->assign('percent', $percent);
        $this->assign('ajustvalue', $ajustvalue);
//        $this->assign('curstorage', $curstorage);
//        $this->assign('minstorage', $minstorage);
//        $this->assign('maxstorage', $maxstorage);
        return $this->fetch();
    }


    //一键设置房间rtp
    public function setRoomTigerRate()
    {
        if ($this->request->isAjax()) {
            $id = intval(input('id')) ? intval(input('id')) : 0;
            $setrange = input('setrange') ? input('setrange') : 1;
            $ajustvalue = input('ajustvalue') ? input('ajustvalue') : 0;
            $type = 1;
//            if ($curstorage < $minstorage || $curstorage > $maxstorage) {
//                return $this->apiReturn(1, [], '当前库存不能小于最小值或大于最大值');
//            }
//            if ($minstorage > $maxstorage) {
//                return $this->apiReturn(2, [], '库存下限不能大于上限');
//            }
//            if($ajustvalue==115)
//                $ajustvalue = 100;
//
//            if ($ajustvalue == 95)
//                $ajustvalue = 100;

            $ajustvalue = 200 - $ajustvalue;
            $res = $this->socket->setProfitPercent($type, $setrange, $id, 0, $ajustvalue, 0, 0, 0);
            $code = $res['iResult'];
            $request = $this->request->request();
            unset($request['s']);
            GameLog::logData(__METHOD__, $request, ($code == 0) ? 1 : 0, $res);
            return $this->apiReturn($code);
        }
        $ajustvalue = input('ajustvalue') ? input('ajustvalue') : 0;
        $winrate = config('winrate');

//        if($ajustvalue==100){
//            $ajustvalue =95;
//        }
//
//        unset($k);

        $this->assign('winrate', $winrate);
        $this->assign('ajustvalue', $ajustvalue);
        return $this->fetch();
    }


    //在线玩家
    public function online()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $orderby = intval(input('orderby')) ? intval(input('orderby')) : 0;
            $asc = intval(input('asc')) ? intval(input('asc')) : 0;
//            $mobile     = trim(input('mobile')) ? trim(input('mobile')) : '';

            $res = Api::getInstance()->sendRequest([
                'roleid' => $roleId,
                'roomid' => $roomId,
                'orderby' => $orderby,
                'page' => $page,
                'asc' => $asc,
                //                'mobile'      => $mobile,
                'pagesize' => $limit
            ], 'player', 'online');
            if (isset($res['data']['list']) && $res['data']['list']) {
                foreach ($res['data']['list'] as &$v) {
                    //盈利
                    $v['totalget'] = $v['totalin'] - $v['totalout'];
                    //活跃度
                    $v['huoyue'] = $v['totalin'] != 0 ? round($v['totalwater'] / $v['totalin'], 2) : 0;
                    $v['TigerCtrlValue'] = $v['TigerCtrlValue'] + 2;
                    if ($v['TigerCtrlValue'] == 102)
                        $v['TigerCtrlValue'] = 97;
                }
                unset($v);
            }
            return $this->apiReturn($res['code'], isset($res['data']['list']) ? $res['data']['list'] : [], $res['message'], $res['total'], [
                'orderby' => isset($res['data']['orderby']) ? $res['data']['orderby'] : 0,
                'asc' => isset($res['data']['asc']) ? $res['data']['asc'] : 0,
            ]);

        }
        $selectData = $this->GetRoomList();
        $this->assign('selectData', $selectData);
        return $this->fetch();
    }


    //个体玩家控制列表
    public function online2()
    {
        $strFields = " id,accountname,nickname,gamebalance,score,accountname,registertime,lastlogintime,lastloginip,totalin,totalout,totalwater,descript,mobile,locked,proxyid,gmtype,ctrlratio, ctrltimelong,TigerCtrlValue ";
        $tableName = " [CD_UserDB].[dbo].[Vw_UserDetail] a,(select roleId,ctrlratio, ctrltimelong,TigerCtrlValue from [CD_UserDB].[dbo].[T_UserCtrlData]) b ";
        $where = " where a.id=b.roleId ";
        $limits = "";
        $orderBy = " order by lastlogintime desc";
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $orderby = intval(input('orderby')) ? intval(input('orderby')) : 0;
            $asc = intval(input('asc')) ? intval(input('asc')) : 0;
            $mobile = trim(input('mobile')) ? trim(input('mobile')) : '';
            $ctr_status = trim(input('ctr_status')) ? trim(input('ctr_status')) : 0;
            $has_recharge = trim(input('has_recharge')) ? trim(input('has_recharge')) : 0;
            //拼装sql
            $limits = " top " . ($page * $limit);
            if ($roleId > 0) $where .= " and  id =" . $roleId;
            if ($mobile != '') $where .= " and  AccountName like '%" . $mobile . "%'";
            if ($roomId > 0) $where .= " and  id =" . $roomId;
            if ($ctr_status == 1) {
                // 停止
                $where .= " and  ctrlratio = 100";
            } else if ($ctr_status == 2) {
                // 启动
                $where .= " and  ctrlratio != 100";
            }
            if ($has_recharge == 1) {
                $where .= " and  totalin > 0";
            } else if ($has_recharge == 2) {
                $where .= " and  totalin = 0";
            }
            $comm = new CommonModel;
            $list = $comm->getPageList($tableName, $strFields, $where, $limits, $page, $limit, $orderBy);

            $count = $list['count'];
            $result = $list['list'];
            if (isset($result)) {
                foreach ($result as &$v) {
                    //盈利
                    $v['totalget'] = $v['totalin'] - $v['totalout'];
//                    $v['totalout'] = FormatMoney($v['totalout']);
//                    $v['totalin'] = FormatMoney($v['totalin']);
                    $v['gamebalance'] = FormatMoney($v['gamebalance']);
                    $v['lastlogintime'] = date('Y-m-d H:i:s', strtotime($v['lastlogintime']));
                    if ($v['ctrlratio'] == 100) {
                        $v['ctrlstatus'] = '已停止';
                    } else {
                        $v['ctrlstatus'] = '控制中';
                    }
                    //活跃度
                    //$v['huoyue'] = $v['totalin'] != 0 ? round($v['totalwater'] / $v['totalin'], 2) : 0;
                    //$v['TigerCtrlValue']  =  $v['TigerCtrlValue'] +2;
                    //if($v['TigerCtrlValue']==102)
                    //    $v['TigerCtrlValue'] = 97;
                }
                unset($v);
            }
            $res['data']['list'] = $result;
            $res['code'] = 0;
            $res['message'] = '';
            $res['total'] = $count;
            return $this->apiReturn($res['code'], isset($res['data']['list']) ? $res['data']['list'] : [], $res['message'], $res['total'], [
                'orderby' => isset($res['data']['orderby']) ? $res['data']['orderby'] : 0,
                'asc' => isset($res['data']['asc']) ? $res['data']['asc'] : 0,
                'sql' => $list['sql']
            ]);
        }
        $selectData = $this->GetRoomList();
        $this->assign('selectData', $selectData);
        return $this->fetch();
    }


    //个体玩家恢复不控制
    public function resetPersonRate()
    {
        $roleid = intval(input('roleid')) ? intval(input('roleid')) : 0;
        $socket = new QuerySocket();
        $res = $socket->setRoleRate($roleid, 100, 0, 0);
        ob_clean();
        GameLog::logData(__METHOD__, $this->request->request());
        return $this->apiReturn(0, [], '设置成功');
    }


    public function getRoomListTiger()
    {
//        Redis::rm('tRoomList');
            $room = $this->GetRoomList();
            foreach ($room as $index => &$value) {
                if ((int)$value['RoomID'] < 4000) unset($room[$index]);
                break;
            }
            return $room;

    }

    public function getRoomListTiger2()
    {
        $room = new Kind();
        $where = [
            'roomid' => ['>', 4000],
            'nullity' => 0
        ];
        $list = $room->getListAll($where, 'roomid,roomname', ' roomid asc');
        return $list;
    }


    /**
     * Notes:捕鱼比率设置
     */
    public function fishrate()
    {
        if ($this->request->isAjax()) {
            $id = intval(input('id')) ? intval(input('id')) : 0;
            $nSysTaxRatio = intval(input('nSysTaxRatio')) ? intval(input('nSysTaxRatio')) : 0;
            $nCaiJinRatio = intval(input('nCaiJinRatio')) ? intval(input('nCaiJinRatio')) : 0;
            $nExplicitTaxRatio = intval(input('nExplicitTaxRatio')) ? intval(input('nExplicitTaxRatio')) : 0;
            $search = '/^12\d{2}$/';
            if (!$id || !preg_match($search, $id)) {//判断是否是捕鱼房间
                return $this->apiReturn(2);
            }
            $res = $this->socket->setFishrate($id, $nSysTaxRatio, $nCaiJinRatio, $nExplicitTaxRatio);
            $code = $res['iResult'];
            GameLog::logData(__METHOD__, $this->request->request(), ($code == 0) ? 1 : 0, $res);
            return $this->apiReturn($code);
        }

        $id = intval(input('id')) ? intval(input('id')) : 0;
        $roomname = input('roomname') ? input('roomname') : 0;
        $nSysTaxRatio = input('nSysTaxRatio') ? input('nSysTaxRatio') : 0;
        $nCaiJinRatio = input('nCaiJinRatio') ? input('nCaiJinRatio') : 0;
        $nExplicitTaxRatio = input('nExplicitTaxRatio') ? input('nExplicitTaxRatio') : 0;

        $this->assign('roomId', $id);
        $this->assign('roomname', $roomname);
        $this->assign('nSysTaxRatio', $nSysTaxRatio);
        $this->assign('nCaiJinRatio', $nCaiJinRatio);
        $this->assign('nExplicitTaxRatio', $nExplicitTaxRatio);
        return $this->fetch();
    }


    public function tigerset()
    {
        $roomlist = $this->getRoomListTiger();
        if ($this->request->isAjax()) {
            $roomId = intval(input('roomId')) ? intval(input('roomId')) : 0;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 15;
            $orderby = intval(input('orderby')) ? intval(input('orderby')) : 0;
            $asc = intval(input('asc')) ? intval(input('asc')) : 0;

            $res = $this->socket->getProfitPercent($roomId);
            //$his = Api::getInstance()->sendRequest(['id' => 0], 'room', 'roompreperty');

            if ($res) {
                if ($roomlist) {
                    $search = '/^12\d{2}$/';
                    foreach ($res as $k => &$v) {
                        if (preg_match($search, $v['nRoomId'])) {
                            unset($res[$k]);
                            continue;
                        }

                        if (intval($v['nRoomId']) < 4000) {
                            unset($res[$k]);
                            continue;
                        }
//
//                        foreach ($his['data'] as $v5) {
//                            if ($v5['roomid'] == $v['nRoomId']) {
//                                $v['mingtax'] = $v5['mingtax'];
//                                $v['goldmoney'] = $v5['goldmoney'];
//                                break;
//                            }
//                        }
                        foreach ($roomlist as $v2) {
                            if ($v['nRoomId'] == $v2['RoomID']) {
                                $v['roomname'] = $v2['RoomName'];
                            }
                        }

                        $v['nCtrlValue'] = intval($v['nCtrlValue'] / 2);
                        $v['AdjustValue'] = $v['nAdjustValue'];
                        $ajustval = 200 - $v['nAdjustValue'];
//                        if ($ajustval == 100)
//                            $ajustval = 95;
                        $v['nAdjustValue'] = $ajustval;

//                        $v['currentget'] = $v['lTotalTax'] + $v['lTotalProfit'];
//                        $v['totalget'] = $v['lHistorySumTax'] + $v['lHistorySumProfile'];
                    }
                    unset($v);
                    $res = array_values($res);
                }
            }

            if ($orderby > 0) {
                if ($orderby == 1) {
                    $orderbystr = 'nCtrlValue';
                } elseif ($orderby == 2) {
                    $orderbystr = 'lTotalProfit';
                } elseif ($orderby == 3) {
                    $orderbystr = 'lHistorySumTax';
                } else {
                    $orderbystr = 'lTotalBlackTax';
                }

                if ($asc == 0) {
                    $ascstr = 'asc';
                } else {
                    $ascstr = 'desc';
                }

                $res = arraySort($res, $orderbystr, $ascstr);
            }
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
            return $this->apiReturn(0, $result, '', $count, ['orderby' => $orderby, 'asc' => $asc]);
        }

        $kindList = $this->GetKindList();
        $this->assign('roomlist', $roomlist);
        $this->assign('kindlist', $kindList);

        //$res = Api::getInstance()->sendRequest(['id' => 0], 'room', 'list');
        //$this->assign('roomlist', isset($res['data']['ResultData']) ? $res['data']['ResultData'] : []);
        $this->assign('historytotal', isset($res['data']['historytotal']) ? $res['data']['historytotal'] : 0);
        $this->assign('currentscore', isset($res['data']['currentscore']) ? $res['data']['currentscore'] : 0);
        $this->assign('totalonline', isset($res['data']['totalonline']) ? $res['data']['totalonline'] : 0);

        return $this->fetch();
    }

    //彩金预分配
    public function JackpotDistribution()
    {
        $request = request()->request();
        $RoleID = input('RoleId');
        $JackpotType = input('JackpotType');
        $CheckUser = input('CheckUser');
        $start = input('start');
        $end = input('end');
        $where = '';
        if (!empty($RoleID)) $where .= " And RoleID=$RoleID";
        if (!empty($CheckUser)) $where .= " And CheckUser='$CheckUser'";
        if (!empty($JackpotType)) $where .= " And JackpotType=$JackpotType";
        if (!empty($start)) $where .= " And AddTime BETWEEN '$start' AND '$end 23:59:59'";

        unset($request['s'], $request['Action']);
        switch (input('Action')) {
            case 'list':
                $db = new UserDB();
                $where .= "AND Status=" . $request['Status'];
                $result = $db->TUserJackpotDistribute()->GetPage($where);
                return $this->apiJson($result);
            case 'Record':
                $db = new UserDB();
                $result = $db->TUserJackpotRecord()->GetPage($where);
                $field = 'SUM(JackpotAward)TotalJackpotAward,' .
                    'SUM(CASE WHEN JackpotType=27 THEN JackpotAward ELSE 0 END)minJackpot,' .
                    'SUM(CASE WHEN JackpotType=28 THEN JackpotAward ELSE 0 END)maxJackpot';
                $result['other'] = $db->GetRow('1=1 ' . $where, $field);
                return $this->apiJson($result);
            //当前彩金
            case 'JackNow':
                $db = new MasterDB('T_JackpotConfig');
                $result = $db->GetPage('', '', 'JackpotType,RealCaijin');
                $field = 'SUM(CASE WHEN JackpotType>0  THEN  RealCaijinELSE 0 END)minJackpot,' .
                    ' SUM(CASE WHEN JackpotType=0 THEN  RealCaijin	ELSE 0 END)maxJackpot';
//                $result['other'] = $db->GetRow('', $field);
                return $this->apiJson($result);

            case  'addView':
                return $this->fetch('jackpot_item');
            case  'addItem':
                $request['CheckUser'] = session('username');
                $db = new UserDB();
                $row = $db->TUserJackpotDistribute()->Insert($request);
                $request['JackpotType'] = $request['JackpotType'] == '27' ? '小彩金' : '超级彩金';
                GameLog::logData(__METHOD__, $request);
                $this->synconfig();
                if ($row > 0) return $this->success('添加成功');
                else return $this->error('添加失败');
            case 'del':
                $db = new UserDB();
                $row = $db->TUserJackpotDistribute()->DeleteRow($request);
                $this->synconfig();
                if ($row > 0) return $this->success('删除成功');
                else return $this->error('删除失败');

        }
        return $this->fetch();
    }

    public function JackpotRoomlist()
    {
        $ID = input('ID');
        switch (input('Action')) {
            case 'list':
                $db = new MasterDB('View_RoomJackpot');
                $result = $db->GetPage("AND CaiJinType=$ID");
                return $this->apiJson($result);
        }
        $this->assign('ID', $ID);
        return $this->fetch();
    }

    //老虎机控制
    public function tigerset2()
    {
        $roomlist = $this->getRoomListTiger2();
        if ($this->request->isAjax()) {
            $roomId = intval(input('roomId')) ? intval(input('roomId')) : 0;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 15;
            $orderby = intval(input('orderby')) ? intval(input('orderby')) : 0;
            $asc = intval(input('asc')) ? intval(input('asc')) : 0;

            $res = $this->socket->getProfitPercent($roomId);
            //$his = Api::getInstance()->sendRequest(['id' => 0], 'room', 'roompreperty');
            $his = $this->getroompreperty();
            if ($res) {
                if ($roomlist) {
                    $search = '/^12\d{2}$/';
                    foreach ($res as $k => &$v) {
                        if (preg_match($search, $v['nRoomId'])) {
                            unset($res[$k]);
                            continue;
                        }

                        if (intval($v['nRoomId']) < 4000) {
                            unset($res[$k]);
                            continue;
                        }

                        foreach ($his as $v5) {
                            if ($v5['roomid'] == $v['nRoomId']) {
                                $v['mingtax'] = $v5['mingtax'];
                                $v['goldmoney'] = $v5['goldmoney'];
                                break;
                            }
                        }
                        foreach ($roomlist as $v2) {
                            if ($v['nRoomId'] == $v2['roomid']) {
                                $v['roomname'] = $v2['roomname'];
                            }
                        }

                        $v['nCtrlValue'] = intval($v['nCtrlValue'] / 2);
                        $v['AdjustValue'] = $v['nAdjustValue'];
                        $ajustval = 200 - $v['nAdjustValue'];
                        if ($ajustval == 100)
                            $ajustval = 95;
                        $v['nAdjustValue'] = $ajustval;

//                        $v['currentget'] = $v['lTotalTax'] + $v['lTotalProfit'];
//                        $v['totalget'] = $v['lHistorySumTax'] + $v['lHistorySumProfile'];
                    }
                    unset($v);
                    $res = array_values($res);
                }
            }

            if ($orderby > 0) {
                if ($orderby == 1) {
                    $orderbystr = 'nCtrlValue';
                } elseif ($orderby == 2) {
                    $orderbystr = 'lTotalProfit';
                } elseif ($orderby == 3) {
                    $orderbystr = 'lHistorySumTax';
                } else {
                    $orderbystr = 'lTotalBlackTax';
                }
                if ($asc == 0) {
                    $ascstr = 'asc';
                } else {
                    $ascstr = 'desc';
                }
                $res = arraySort($res, $orderbystr, $ascstr);
            }
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
            return $this->apiReturn(0, $result, '', $count, ['orderby' => $orderby, 'asc' => $asc]);
        }

        $kindList = $this->GetKindList();
        $this->assign('roomlist', $roomlist);
        $this->assign('kindlist', $kindList);

        //$res = Api::getInstance()->sendRequest(['id' => 0], 'room', 'list');
        //$this->assign('roomlist', isset($res['data']['ResultData']) ? $res['data']['ResultData'] : []);
        $this->assign('historytotal', isset($res['data']['historytotal']) ? $res['data']['historytotal'] : 0);
        $this->assign('currentscore', isset($res['data']['currentscore']) ? $res['data']['currentscore'] : 0);
        $this->assign('totalonline', isset($res['data']['totalonline']) ? $res['data']['totalonline'] : 0);

        return $this->fetch();


    }

    //捕鱼房间
    public function fishroom()
    {
        $roomlist = array_merge($this->GetRoomList());//从新组织数组索引
        if ($this->request->isAjax()) {
            $roomId = 0;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 15;
            $orderby = intval(input('orderby')) ? intval(input('orderby')) : 0;
            $asc = intval(input('asc')) ? intval(input('asc')) : 0;
            $res = null;
            try {
                $res = $this->socket->getProfitPercent($roomId);
            } catch (Exception $exception) {
            }
            if ($res) {
                $search = '/^12\d{2}$/';
                foreach ($res as $k => &$v) {
                    if (!preg_match($search, $v['nRoomId']) || $v['nRoomId'] == 1200) {
                        unset($res[$k]);
                        continue;
                    }
                    $key = array_search($v['nRoomId'], array_column($roomlist, 'RoomID'));
                    $v['roomname'] = $roomlist[$key]['RoomName'];
                    $v['win'] = round($v['lHistorySumProfile'] - $v['lHistorySumTax'], 3);
                }
                unset($v);
                $res = array_values($res);

            }
            if ($orderby > 0) {
                if ($orderby == 1) {
                    $orderbystr = 'nCtrlValue';
                } elseif ($orderby == 2) {
                    $orderbystr = 'lTotalProfit';
                } elseif ($orderby == 3) {
                    $orderbystr = 'lHistorySumTax';
                } else {
                    $orderbystr = 'lTotalBlackTax';
                }

                if ($asc == 0) {
                    $ascstr = 'asc';
                } else {
                    $ascstr = 'desc';
                }

                $res = arraySort($res, $orderbystr, $ascstr);
            }
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
            return $this->apiReturn(0, $result, '', $count, ['orderby' => $orderby, 'asc' => $asc]);

        }
        return $this->fetch();
    }


    public function  gamecheating(){
        if($this->request->isAjax())
        {
            $roleid = input('roleid',0);
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 15;
            $orderby =input('orderby','');
            $ordertype =input('ordertype','');
            $where ='';
            if($roleid==0){
                return $this->apiReturn(0,[],'暂无数据',0);
            }

            $order ='count(1) ';
            if(!empty($ordertype)){
                $order.=$ordertype;
            }
            else
                $order.=' desc ';

            $date = date('Ymd',time());
            $table ='[T_UserGameChangeLogs_'.$date.']';
            $sqlQuery ='SELECT RoleID, COUNT(1) as TotalUser FROM '.$table.' with(nolock) WHERE roleid<>'.$roleid.' and [SerialNumber] in (SELECT [SerialNumber]  FROM '.$table.' as b with(nolock) where roleid='.$roleid.' )  group by Roleid order by '.$order.'   OFFSET ' . ($page - 1) * $limit . " ROWS FETCH NEXT $limit ROWS ONLY  ";

            $coutQuery ='select count(1) as count from (SELECT  RoleID, COUNT(1) as TotalUser FROM '.$table.' WHERE roleid<>'.$roleid.' and [SerialNumber] in (SELECT [SerialNumber]  FROM '.$table.' as b  where roleid='.$roleid.' ) '.$where.'  group by Roleid) as t ';

            $db = new GameOCDB($table);
            $result['list'] = $db->getTableQuery($sqlQuery);
            $result['count'] = $db->getTableQuery($coutQuery)[0]['count'];
            return $this->apiJson($result);

        }
        return $this->fetch();
    }
}
