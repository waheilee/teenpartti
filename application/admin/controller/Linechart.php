<?php

namespace app\admin\controller;

use app\model\UserDB;

class Linechart extends Main
{

    public function index()
    {

        $auth_ids = $this->getAuthIds();
        if (!in_array(10006, $auth_ids)) {
            die();
        }
        // $online = $this->sendGameMessage('CMD_MD_QUERY_ONLINE_PLAYER', [], "DC", 'getRoomUserOnlineInfo');
        $online = $this->GetOnlineUserlist2();
        // $online['RoomCount'] = count($online['game']);
        // $online['Hall'] = $online['total_num'];
        $db = new UserDB();
        $result=$db->GetIndexData();

        //找下数据源
        if ($this->request->isAjax()) {
            return $this->apiJson($result);
        }

        $result['other']['addgm'] =  (new \app\model\GameOCDB())->getTableObject('T_GMSendMoney')->whereTime('InsertTime','>=',date('Y-m-d'))->where('status',1)->where('operatetype',1)->sum('Money')?:0;
        $result['other']['addmailsend'] = (new \app\model\DataChangelogsDB())->getTableObject('T_ProxyMsgLog')->whereTime('addtime','>=',date('Y-m-d'))->where('VerifyState',1)->sum('Amount')?:0;
        $result['other']['addmailsend'] = bcdiv($result['other']['addmailsend'], bl,3);

        $result['other']['zscoin'] = bcadd($result['other']['addgm'], $result['other']['addmailsend'],2);

        $result['other']['total_addgm'] =  (new \app\model\GameOCDB())->getTableObject('T_GMSendMoney')->where('status',1)->where('operatetype',1)->sum('Money')?:0;
        $result['other']['total_addmailsend'] = (new \app\model\DataChangelogsDB())->getTableObject('T_ProxyMsgLog')->where('VerifyState',1)->sum('Amount')?:0;
        $result['other']['total_addmailsend'] = bcdiv($result['other']['total_addmailsend'], bl,3);

        $result['other']['total_zscoin'] = bcadd($result['other']['total_addgm'], $result['other']['total_addmailsend'],2);

        $result['other']['totalbalance'] = $db->getTableObject('T_UserGameWealth')
            ->where('RoleId', '>','9999999')
            ->sum('Money')?:0;
        $result['other']['totalbalance'] = bcdiv($result['other']['totalbalance'], bl,3);
        //  顶部数据  第0行
//        $result['list'][0]["online"] = count($this->GetOnlineUserlist());
//       
        // $GameOCDB = new \app\model\GameOCDB();
        // $result['list'][0]["tax"]= $GameOCDB->setTable('T_GameStatisticTotal')->getValueByTable('mydate=\''.date('Y-m-d').'\'','totaltax')?:0;
        // $result['other']["total_tax"]= $GameOCDB->setTable('T_GameStatisticTotal')->getTableSum('1=1','totaltax')?:0;
        // ConVerMoney($result['list'][0]["tax"]);
        // ConVerMoney( $result['other']["total_tax"]);
        // $this->assign('online',$online);
        $list = $result['list'][0]??[];
        $this->assign('info', $list);
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
