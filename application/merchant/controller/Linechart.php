<?php

namespace app\merchant\controller;

use app\model\UserDB;

class Linechart extends Main
{

    public function index()
    {

        // $online = $this->sendGameMessage('CMD_MD_QUERY_ONLINE_PLAYER', [], "DC", 'getRoomUserOnlineInfo');
        $operatorid = session('merchant_OperatorId');
        $db = new UserDB();
        // $online = $this->GetOnlineUserlist2();
        // $str_room = implode(',',$online['game']);
        // $online['RoomCount'] = (new \app\model\AccountDB())->getTableObject('T_Accounts')->where('OperatorId',$operatorid)->where('AccountID','in',$str_room)->count();
        // $total = implode(',',$online['total']);
        // $online['Hall'] = (new \app\model\AccountDB())->getTableObject('T_Accounts')->where('OperatorId',$operatorid)->where('AccountID','in',$total)->count();
        $result=$db->GetOperatorIndexData();
        //找下数据源
        if ($this->request->isAjax()) {
            return $this->apiJson($result);
        }
        //  顶部数据  第0行
//        $result['list'][0]["online"] = count($this->GetOnlineUserlist());
//
        
        $where = '  OperatorId='.$operatorid;

        // $GameOCDB = new \app\model\GameOCDB();
        // $result['list'][0]["tax"]= $GameOCDB->setTable('T_Operator_GameStatisticTotal')->getValueByTable('mydate=\''.date('Y-m-d').'\' and OperatorId='.$operatorid,'totaltax')?:0;
        // $result['other']["total_tax"]= $GameOCDB->setTable('T_Operator_GameStatisticTotal')->getTableSum($where,'totaltax')?:0;
        // ConVerMoney($result['list'][0]["tax"]);
        // ConVerMoney( $result['other']["total_tax"]);
        // $this->assign('online',$online);
        $this->assign('info', $result['list'][0]);
        $this->assign('Total', $result['other']);
        return $this->fetch();
    }

    /**
     * 在线折线图
     */
    public function online()
    {
        return $this->fetch();
    }

    public function ol(){
        $out_data = $this->sendGameMessage('CMD_MD_QUERY_ONLINE_PLAYER', [], "DC");
        $out_data_array = unpack('LTotalCount/LRoomCount', $out_data);
        for ($x = 0; $x < $out_data_array['RoomCount']; $x++) {
            $out_data_Room_array = unpack('x8/x' . ($x * 32) . '/LRoomID/LOnLineCount/LRobotCount/LUpdateTime/LMobileCount/LIOSCount/LAndroidCount/LBrowserOnlineCount/', $out_data);
            $out_array["RoomOnlineInfoList"][$x] = $out_data_Room_array;
        }
        dump($out_data_array);
        halt($out_array);
    }
    //大厅
//    public function hall()
//    {
//        $res = Api::getInstance()->sendRequest(['id' => 0], 'game', 'hallonline');
//        //时间  ios  安卓
//        $dates = $numbers = $numbers2 = [];
//        if (isset($res['data']) && $res['data']) {
//            foreach ($res['data'] as $v) {
//                $dates[] = $v['addtime'];
//                $numbers[] = $v['iosusercount'];
//                $numbers2[] = $v['androidusercount'];
//            }
//            return $this->apiReturn($res['code'], ['dates' => $dates, 'numbers' => $numbers,  'numbers2' => $numbers2], $res['message']);
//        }
//        return $this->apiReturn('0',['dates'=>null,'numbers'=>null,'numbers2'=>null],'接口数据获取失败,或连接超时');
//
//    }

    //游戏内
//    public function game()
//    {
//        $res = Api::getInstance()->sendRequest(['id' => 0], 'game', 'roomonline');
//        //时间  ios  安卓
//        $dates = $numbers = $numbers2 = [];
//        if (isset($res['data']) && $res['data']) {
//            foreach ($res['data'] as $v) {
//                $dates[] = $v['addtime'];
//                $numbers[] = $v['iosusercount'];
//                $numbers2[] = $v['androidusercount'];
//            }
//            return $this->apiReturn(0, ['dates' => $dates, 'numbers' => $numbers, 'numbers2' => $numbers2]);
//        }
//        return $this->apiReturn('0',['dates'=>null,'numbers'=>null,'numbers2'=>null],'接口数据获取失败,或连接超时');
//    }


}
