<?php

namespace app\admin\controller;

use app\admin\controller\traits\getSocketRoom;
use app\admin\controller\traits\search;
use app\common\Api;
use app\common\GameLog;
use app\model\CommonModel;
use app\model\GameKind;
use app\model\GameOCDB;
use app\model\GameRoomInfo as RoomInfo;
use app\model\MasterDB;
use app\model\RoomWaterData;
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
            $orderby = input('orderby',-1);
            $asc = intval(input('asc')) ? intval(input('asc')) : 0;

            $res = $this->socket->getProfitPercent($roomId);
            $db = new MasterDB();
            $sql ='SELECT RoomID,LuckyEggTaxRate/10 as mingtax,SysMaxLoseMoneyPerRound/'.bl.' as goldmoney  FROM   T_GameRoomInfo  where Nullity=0 order by SortID';
            $his = $db->getTableQuery($sql);
//
            $pankong = $db->getTableQuery('select RoomId,ISNULL(CurRoomWaterIn,0) As CurRoomWaterIn,ISNULL(CurRoomWaterOut,0) As CurRoomWaterOut from T_RoomRunningCtrlData');

            $tigerliset = config('tigergame');

            if ($res) {
                if ($roomlist) {
                    $search = '/^12\d{2}$/';
                    foreach ($res as $k => &$v) {
                        $roomid = intval($v['nRoomId']/10)*10;
                        if (preg_match($search, $v['nRoomId'])) {
                            unset($res[$k]);
                            continue;
                        }

                        if(in_array($roomid,$tigerliset)){
                            unset($res[$k]);
                            continue;
                        }
//
                        $found_arr = array_column($roomlist, 'RoomID');
                        $found_key = array_search($v['nRoomId'], $found_arr);
                        if($found_key===false){
                            unset($res[$k]);
                            continue;
                        }
                        else
                        {
                            foreach ($roomlist as $v2) {
                                if ($v['nRoomId'] == $v2['RoomID']) {
                                    $v['roomname'] = $v2['RoomName'];
                                }
                            }
                        }

                        foreach ($his as $v5) {
                            if ($v5['RoomID'] == $v['nRoomId']) {
                                $v['mingtax'] = $v5['mingtax'];
                                $v['goldmoney'] = $v5['goldmoney'];
                                break;
                            }
                        }

                        $found_arr = array_column($pankong, 'RoomId');
                        $found_key = array_search($v['nRoomId'], $found_arr);
                        $v['CurRoomWaterIn']  =0;
                        $v['CurRoomWaterOut'] =0;
                        if($found_arr!==false){
                            $v['CurRoomWaterIn'] = FormatMoneyInt($pankong[$found_key]['CurRoomWaterIn']);
                            $v['CurRoomWaterOut'] =FormatMoneyInt($pankong[$found_key]['CurRoomWaterOut']);
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
                        $v['nOldRoomCtrlValue'] = intval($v['nOldRoomCtrlValue']/2);

//                        $v['currentget'] = $v['lTotalTax'] + $v['lTotalProfit'];
//                        $v['totalget'] = $v['lHistorySumTax'] + $v['lHistorySumProfile'];
                    }
                    unset($v);
                    $res = array_values($res);
                }
            }

            if ($orderby > -1) {
                if ($orderby == 0) {
                    $orderbystr = 'nRoomId';
                }else if ($orderby == 1) {
                    $orderbystr = 'nOldRoomCtrlValue';
                }
                else if ($orderby == 2) {
                    $orderbystr = 'nCtrlValue';
                }elseif ($orderby == 3) {
                    $orderbystr = 'lTotalProfit';
                } elseif ($orderby == 4) {
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


//        foreach ($roomlist as $k=>&$v) {
//            $search = '/^12\d{2}$/';
//            if (preg_match($search, $v['RoomID'])) {
//                unset($roomlist[$k]);
//                continue;
//            }
//            if ($v['RoomID'] > 4000  || $v['RoomID'] == 3901) {
//                unset($roomlist[$k]);
//                continue;
//            }
//        }
//        unset($v);
        $this->assign('roomlist', $roomlist);
        return $this->fetch();
    }



    //房间收水放水设置
    public  function  RoomWaterSet(){
        $roomid=input('roomId',0);
        $roomname=input('roomname','');
        $CurRoomWaterIn =input('CurRoomWaterIn');
        $CurRoomWaterOut =input('CurRoomWaterOut');
        $ControllType = input('ControllType',-1);
        if($this->request->isAjax())
        {
            $CurRoomWaterIn =$CurRoomWaterIn*1000;
            $res =['iResult'=>100];
            if($ControllType==0){
                $res = $this->socket->SeWDSetRoomWater(3,$roomid,$CurRoomWaterIn,0);
            }

            if($ControllType==1){
                $res = $this->socket->SeWDSetRoomWater(3,$roomid,0,$CurRoomWaterIn);
            }

            $code = $res['iResult'];
            GameLog::logData(__METHOD__, $this->request->request(), ($code == 0) ? 1 : 0, $res);
            return $this->apiReturn($code);
        }
        $param = [
            'roomId' => $roomid,
            'roomname'=>$roomname,
            'CurRoomWaterIn' =>$CurRoomWaterIn,
            'CurRoomWaterOut'=>$CurRoomWaterOut
        ];
        $this->assign('param',$param);
        return $this->fetch();
    }

     //设置范围 1所有服务器 2同一个类型 3具体游戏房间
    public function KindWaterSet(){
        $kindid=input('roomId',0);
        $CurRoomWaterIn =input('CurRoomWaterIn',0);
        $ControllType =input('ControllType',-1);

        if($this->request->isAjax())
        {
            $CurRoomWaterIn = $CurRoomWaterIn*1000;
            if($ControllType==0){
                $res = $this->socket->SeWDSetRoomWater(2,$kindid,$CurRoomWaterIn,0);
            }
            if($ControllType==1){
                $res = $this->socket->SeWDSetRoomWater(2,$kindid,0,$CurRoomWaterIn);
            }

            $code = $res['iResult'];
            GameLog::logData(__METHOD__, $this->request->request(), ($code == 0) ? 1 : 0, $res);
            return $this->apiReturn($code);
        }
        $kindlist = $this->GetKindList();
        $this->assign('kindlist',$kindlist);
        return $this->fetch();
    }


    public function GlobalSetLog(){
        $roomid=input('roomId',0);
        if($this->request->isAjax())
        {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 15;
            $roomwater= new RoomWaterData();
            $where =  ['RoomId'=>$roomid];
            $list = $roomwater->getList($where,$page,$limit,'*',['id'=>'desc']);
            foreach ($list as $k=>&$v){
                $v['CurWaterIn'] = FormatMoneyInt($v['CurWaterIn']);
                $v['CurWaterOut'] = FormatMoneyInt($v['CurWaterOut']);
            }
            unset($v);
            $count = $roomwater->getCount($where);
            return $this->apiReturn(0,$list,'success',$count);
        }
        $this->assign('roomid',$roomid);
        return $this->fetch();
    }



    public function CancelControll(){
        $roomid=input('roomId',0);
        $res = $this->socket->SeWDSetRoomWater(3,$roomid,0,0);
        $code = $res['iResult'];
        GameLog::logData(__METHOD__, $this->request->request(), ($code == 0) ? 1 : 0, $res);
        return $this->apiReturn($code);
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

//                        if ($v['nRoomId'] > 4000 || $v['nRoomId'] == 3901) {
//                            unset($res[$k]);
//                            continue;
//                        }

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

            //$goldmoney = input('goldmoney') ? input('goldmoney') : 0;
            $type = 1;
//            if ($curstorage < $minstorage || $curstorage > $maxstorage) {
//                return $this->apiReturn(1, [], '当前库存不能小于最小值或大于最大值');
//            }
//            if ($minstorage > $maxstorage) {
//                return $this->apiReturn(2, [], '库存下限不能大于上限');
//            }

            $minstorage=$minstorage*bl;
            $maxstorage = $maxstorage*bl;
            $curstorage =$curstorage*bl;
            $ajustvalue =$ajustvalue*bl;

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
        $goldmoney = input('goldmoney') ? input('goldmoney') : 0;

        $roomctrl =input('roomctrl',0);

        $this->assign('roomId', $id);
        $this->assign('roomname', $roomname);
        $this->assign('percent', $percent);
        $this->assign('ajustvalue', $ajustvalue);
        $this->assign('curstorage', $curstorage);
        $this->assign('minstorage', $minstorage);
        $this->assign('maxstorage', $maxstorage);
        $this->assign('roomctrl',$roomctrl);
        $this->assign('goldmoney',$goldmoney);
        $winrate = config('winrate');
        $this->assign('winrate', $winrate);
        return $this->fetch();
    }


    public function setProfitbykind()
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

            $minstorage=$minstorage*bl;
            $maxstorage = $maxstorage*bl;
            $curstorage =$curstorage*bl;

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

        if($id==23601){
            unset($winrate[12]);
            unset($winrate[13]);
        }
        else  if($id==23801){
            unset($winrate[11]);
        }

        $this->assign('winrate', $winrate);
        $this->assign('roomId', $id);
        $this->assign('roomname', $roomname);
        $this->assign('percent', $percent);
        $this->assign('ajustvalue', $ajustvalue);

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
            $res = $this->socket->setProfitPercent($type, $setrange, $id, 0, $ajustvalue, $ajustvalue,0, 0, 0);
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
        $strFields = " id,accountname,nickname,gamebalance,score,accountname,registertime,lastlogintime,lastloginip,totalin,totalout,totalwater,descript,mobile,locked,proxyid,gmtype,ctrlratio, ctrltimelong,TigerCtrlValue,InitialPersonMoney,PersonCtrlMoney ";
        $tableName = " [CD_UserDB].[dbo].[Vw_UserDetail] a,(select roleId,ctrlratio, ctrltimelong,TigerCtrlValue,InitialPersonMoney,PersonCtrlMoney from [CD_UserDB].[dbo].[T_UserCtrlData]) b ";
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
                    $v['InitialPersonMoney'] = $v['InitialPersonMoney']/1000;
                    $v['PersonCtrlMoney'] = $v['PersonCtrlMoney']/1000;
//                    $v['totalout'] = FormatMoney($v['totalout']);
//                    $v['totalin'] = FormatMoney($v['totalin']);
                    $v['gamebalance'] = FormatMoney($v['gamebalance']);
                    $v['lastlogintime'] = date('Y-m-d H:i:s', strtotime($v['lastlogintime']));
                    if ($v['ctrlratio'] == 100) {
                        $v['ctrlstatus'] = lang('已停止');
                    } else {
                        $v['ctrlstatus'] = lang('控制中');
                    }
                    if ($v['InitialPersonMoney'] > 0) {
                        $v['ctrlstatus'] .='&nbsp;'. lang("收水中");
                    }
                    if ($v['InitialPersonMoney'] < 0) {
                        $v['ctrlstatus'] .= '&nbsp;'.lang("放水中");
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

    /**
     * 批量结束控制(停止使用)
     * @return void
     */
//    public function multiCancelControl()
//    {
//        $accountId = $this->request->param('roleid/a');
//        foreach ($accountId as $k => $v) {
//            $socket = new QuerySocket();
//            $socket->setRoleRate($v, 100, 0, 0);
//            ob_clean();
//        }
//        GameLog::logData(__METHOD__, $this->request->request());
//        return $this->apiReturn(0, [], '设置成功');
//    }


    public function getRoomListTiger()
    {
//        Redis::rm('tRoomList');
            $tigerliset = config('tigergame');
            $room = $this->GetRoomList();
            foreach ($room as $index => &$value) {
                //if ((int)$value['RoomID'] < 4000) unset($room[$index]);
                $roomid =intval($value['RoomID']/10)*10;
                if(!in_array($roomid,$tigerliset)){
                    unset($room[$index]);
                }
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
            $tigerliset = config('tigergame');
            $res = $this->socket->getProfitPercent($roomId);

            if ($res) {
                if ($roomlist) {
                    $search = '/^12\d{2}$/';
                    foreach ($res as $k => &$v) {
                        if (preg_match($search, $v['nRoomId'])) {
                            unset($res[$k]);
                            continue;
                        }
                        $roomid =intval($v['nRoomId']/10)*10;
                        if(!in_array($roomid,$tigerliset)){
                            unset($res[$k]);
                        }

                        foreach ($roomlist as $v2) {
                            if ($v['nRoomId'] == $v2['RoomID']) {
                                $v['roomname'] = $v2['RoomName'];
                            }
                        }

                        $v['nCtrlValue'] =200 - intval($v['nCtrlValue']);
                        $v['AdjustValue'] = $v['nAdjustValue'];
                        //$ajustval = 200 - $v['nAdjustValue'];
//                        if ($ajustval == 100)
//                            $ajustval = 95;
                        //$v['nAdjustValue'] = $ajustval;

//                        $v['currentget'] = $v['lTotalTax'] + $v['lTotalProfit'];
//                        $v['totalget'] = $v['lHistorySumTax'] + $v['lHistorySumProfile'];
                    }
                    unset($v);
                    $res = array_values($res);
                    foreach ($res as $k => &$v) {
                        if( !isset($v['roomname']))
                        {
                            unset($res[$k]);
                        }
                    }
                    $res = array_merge($res);
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
//        $this->assign('historytotal', isset($res['data']['historytotal']) ? $res['data']['historytotal'] : 0);
//        $this->assign('currentscore', isset($res['data']['currentscore']) ? $res['data']['currentscore'] : 0);
//        $this->assign('totalonline', isset($res['data']['totalonline']) ? $res['data']['totalonline'] : 0);

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
                $userdb = new UserDB();
                $result = $userdb->TUserJackpotRecord()->GetPage($where);
                $roomlist = $this->GetKindList();
                foreach ($result['list'] as $k=>&$v){
                    $found_arr = array_column($roomlist, 'KindID');
                    $found_key = array_search($v['KindID'], $found_arr);
                    $v['KindName'] ='--';
                    if($found_key!==false){
                        $v['KindName'] = $roomlist[$found_key]['KindName'];
                    }
                }
                unset($v);
                $gamedb = new  MasterDB();
                $res = $gamedb->getTablePage('T_jackpotconfig', 1, 100, '', '', 'jackpottype asc ','jackpottype,virtualcaijin,realcaijin,minstock,maxstock');

                $other =['TotalJackpotAward'=>0,'minor'=>0,'major'=>0,'grand'=>0,'jackpot'=>0];
                if(!empty($res['list'])){
                    if(!empty($res['list'][1])){
                        $totalcaijin = $res['list'][1]['realcaijin'];
                        $totalcaijin = FormatMoney($totalcaijin);
                        $other['TotalJackpotAward'] = $totalcaijin;
                        $other['minor'] =bcmul($totalcaijin,0.2,2);
                        $other['major'] =bcmul($totalcaijin,0.3,2);
                        $other['grand'] =bcmul($totalcaijin,0.5,2);
                    }
                    else{
                        $other['TotalJackpotAward'] =0;
                        $other['minor'] =0;
                        $other['major'] =0;
                        $other['grand'] =0;
                    }
                    if(!empty($res['list'][0])){
                        $other['jackpot'] = FormatMoney($res['list'][0]['realcaijin']);
                    }
                    else{
                        $other['jackpot'] =0;
                    }

                }

//                $field = 'ISNULL(SUM(JackpotAward),0) TotalJackpotAward,' .
//                    'ISNULL(SUM(CASE WHEN JackpotType=87 THEN JackpotAward ELSE 0 END),0) Minor,' .
//                    'ISNULL(SUM(CASE WHEN JackpotType=88 THEN JackpotAward ELSE 0 END),0) major,' .
//                    'ISNULL(SUM(CASE WHEN JackpotType=89 THEN JackpotAward ELSE 0 END),0) grand,' .
//                    'ISNULL(SUM(CASE WHEN JackpotType=28 THEN JackpotAward ELSE 0 END),0) jackpot';
                $result['other'] =$other;// $userdb->GetRow('1=1 ' . $where, $field);
                return $this->apiJson($result);
            //当前彩金
            case 'JackNow':
                $db = new MasterDB('T_JackpotConfig');
                $result = $db->GetPage('', '', 'JackpotType,RealCaijin');
//                $field = 'SUM(CASE WHEN JackpotType>0  THEN  RealCaijinELSE 0 END)minJackpot,' .
//                    ' SUM(CASE WHEN JackpotType=0 THEN  RealCaijin	ELSE 0 END)maxJackpot';
//                $result['other'] = $db->GetRow('', $field);
                foreach ($result['list'] as $k=>&$v){
                    //$v['JackpotType'] = FormatMoney($v['JackpotType']);
                    $v['RealCaijin'] = FormatMoney($v['RealCaijin']);
                }
                unset($v);
                return $this->apiJson($result);

            case  'addView':
                return $this->fetch('jackpot_item');
            case  'addItem':
                $request['CheckUser'] = session('username');
                $db = new UserDB();
                $row = $db->TUserJackpotDistribute()->Insert($request);
                $request['JackpotType'] = $request['JackpotType'] == '27' ? lang('小彩金') : lang('超级彩金');
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
            $num = input('num') ?: 0;
            $begin_date = input('begin_date',date('Y-m-d'));
            $end_date = input('end_date',date('Y-m-d'));
            $where ='';
            if($roleid==0){
                return $this->apiReturn(0,[],'暂无数据',0);
            }

            $order ='count(1) ';
            if(!empty($ordertype)){
                $order.=$ordertype;
            } else {
                $order.=' desc ';
            }

            $days = date_diff(date_create($end_date),date_create($begin_date))->days;
            
            $table_date = date('Ymd',strtotime($begin_date));
            $sql = "SELECT RoleID, COUNT(1) as TotalUser FROM (";
            $sql2= "";
            $table = "[T_UserGameChangeLogs_".$table_date."]";
            $sql .= "SELECT * FROM ".$table.' with(nolock) WHERE RoleID<>'.$roleid.' and [SerialNumber] in (SELECT [SerialNumber]  FROM '.$table.' as b with(nolock) where RoleID='.$roleid.')';
            $sql2 .= "SELECT * FROM ".$table.' with(nolock) WHERE RoleID<>'.$roleid.' and [SerialNumber] in (SELECT [SerialNumber]  FROM '.$table.' as b with(nolock) where RoleID='.$roleid.')';
            if ($days > 0) {
                for ($i=1; $i < $days+1; $i++) {
                    $table_date = date('Ymd',strtotime($begin_date)+86400*$i);
                    $table = "[T_UserGameChangeLogs_".$table_date."]";
                    $sql .= " UNION ALL SELECT * FROM ".$table.' with(nolock) WHERE RoleID<>'.$roleid.' and [SerialNumber] in (SELECT [SerialNumber]  FROM '.$table.' as b with(nolock) where RoleID='.$roleid.')';
                    $sql2 .= " UNION ALL SELECT * FROM ".$table.' with(nolock) WHERE RoleID<>'.$roleid.' and [SerialNumber] in (SELECT [SerialNumber]  FROM '.$table.' as b with(nolock) where RoleID='.$roleid.')';
                }
            }

            $sql .= ') as A group by RoleID HAVING COUNT(1)>'.$num;
            $coutQuery = 'select count(1) as count from ('.$sql.') AS A';
            $sql  .= ' order by '.$order.'   OFFSET ' . ($page - 1) * $limit . " ROWS FETCH NEXT $limit ROWS ONLY  ";


            // $table ='[T_UserGameChangeLogs_'.$date.']';
            // $sqlQuery ='SELECT RoleID, COUNT(1) as TotalUser FROM '.$table.' with(nolock) WHERE roleid<>'.$roleid.' and [SerialNumber] in (SELECT [SerialNumber]  FROM '.$table.' as b with(nolock) where roleid='.$roleid.' )  group by Roleid order by '.$order.'   OFFSET ' . ($page - 1) * $limit . " ROWS FETCH NEXT $limit ROWS ONLY  ";

            // $coutQuery ='select count(1) as count from (SELECT  RoleID, COUNT(1) as TotalUser FROM '.$table.' WHERE roleid<>'.$roleid.' and [SerialNumber] in (SELECT [SerialNumber]  FROM '.$table.' as b  where roleid='.$roleid.' ) '.$where.'  group by Roleid) as t ';

            $db = new GameOCDB($table);
            $result['list'] = $db->getTableQuery($sql);
            $result['count'] = $db->getTableQuery($coutQuery)[0]['count'];
            
            $total = $db->getTableQuery($sql2);
            $roomoinfo = (new MasterDB)->getTableObject('T_GameRoomInfo')->column('RoomName','RoomID');
            $arr = [];
            foreach ($total as $key => &$val) {
                $RoleID = $val['RoleID'];
                $RoomID = $val['ServerID'];
                $arr[$RoleID] = $arr[$RoleID] ?? [];
                if (!isset($arr[$RoleID][$RoomID])) {
                    $arr[$RoleID][$RoomID] = 1;
                } else {
                    $arr[$RoleID][$RoomID] += 1;
                }
            }
            foreach ($result['list'] as $key => &$val) {
                $RoleID = $val['RoleID'];
                $Detail = $arr[$RoleID];
                $html = '';

                foreach ($Detail as $k => &$v) {
                    $RoomName = $roomoinfo[$k]['RoomName'];
                    $html .= $RoomName.'：'.$v.lang('局').'，<br/>';
                }
                $val['Detail'] = $html;
            }
            return $this->apiJson($result);

        }
        return $this->fetch();
    }
}
