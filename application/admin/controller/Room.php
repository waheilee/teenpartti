<?php

namespace app\admin\controller;

use app\admin\controller\traits\getSocketRoom;
use app\admin\controller\traits\search;
use app\common\Api;
use app\common\GameLog;
use app\model\GameOCDB;
use socket\QuerySocket;


class Room extends Main
{
    use getSocketRoom;
    use search;

    private $socket = null;

    public function __construct() {
        parent::__construct();
        $this->socket = new QuerySocket();
    }

    //获取房间库存概率信息
    public function getSocketRoomData() {
        $roomid = input('roomid');
        $roomsData = $this->getSocketRoom($this->socket, $roomid);
        return $this->apiReturn(0, $roomsData, 'success');
    }


    //设置房间概率信息
    public function setSocketRoomRate() {
        $roomid = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $init = intval(input('init')) ? intval(input('init')) : 0;
        $current = intval(input('current')) ? intval(input('current')) : 0;

        if (abs($init) > 2000000 || abs($current) > 2000000) {
            return $this->apiReturn(1, [], '库存值不能超过绝对值200万');
        }
        $roomsData = $this->getSocketRoom($this->socket, $roomid);

        $this->socket->setRoom($roomid, $roomsData['nCtrlRatio'], $init, $current, $roomsData['szStorageRatio']);
        $request=$this->request->request();
        unset($request['s']);
        GameLog::logData(__METHOD__, $request, 1);
        ob_clean();
        return $this->apiReturn(0, [], '修改成功');
    }


    //设置房间库存信息
    public function setSocketRoomStorage() {
        if ($this->request->isAjax()) {
            $request = $this->request->request();
            $roomid = $request['roomid'];
            $storage = json_decode($request['data'], true);
            ksort($storage);

            $storageStr = '';
            foreach ($storage as $k => $v) {
                if (abs($k) > 2000000) {
                    return $this->apiReturn(1, [], '库存值不能超过绝对值200万');
                }
                $storageStr .= $k . '#' . $v . '#';
            }
            $storageStr = rtrim($storageStr, '#');
            $roomsData = $this->getSocketRoom($this->socket, $roomid);
            $this->socket->setRoom($roomid, $roomsData['nCtrlRatio'], $roomsData['nInitStorage'], $roomsData['nCurrentStorage'], $storageStr);

            GameLog::logData(__METHOD__, $request, 1);
            ob_clean();
            return $this->apiReturn(0, [], '修改成功');
        }

        $roomid = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $roomsData = $this->getSocketRoom($this->socket, $roomid);
        $roomsData['szStorageRatio'] = trim($roomsData['szStorageRatio']);

        $array = [];
        if ($roomsData['szStorageRatio']) {

            $storage = explode('#', $roomsData['szStorageRatio']);
            $info = array_chunk($storage, 2);

            if ($info) {
                foreach ($info as $k => $v) {
                    $array[] = [
                        'rate' => $v[1],
                        'storage' => $v[0]
                    ];
                }
            }
        }


        $this->assign('lists', $array);
        $this->assign('thisroomid', $roomid);
        return $this->fetch('setstorage');
    }


    //百人场数据
    public function getHundredData() {
        $roomid = input('id');
        $res = Api::getInstance()->sendRequest(['id' => $roomid], 'room', 'draw');
        if (isset($res['data']['list'])) {
            $index = 1;
            foreach ($res['data']['list'] as &$v) {
                $v['id'] = $index++;
            }
            unset($v);
        }
        return $this->apiReturn($res['code'], isset($res['data']['list']) ? $res['data']['list'] : [], $res['message'], $res['total'], isset($res['data']['topten']) ? $res['data']['topten'] : []);
    }

    /**
     * 设置玩家胜率
     */
    public function setPlayerRate() {

        if ($this->request->isAjax()) {
            $roleid = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $ratio = intval(input('ratio')) ? intval(input('ratio')) : 0;
            $time = intval(input('time')) ? intval(input('time')) : 10000000;
//            $timeinterval = intval(input('timeinterval')) ? intval(input('timeinterval')) : 0;
            $timeinterval = 10000000;
            $InitialPersonMoney = input('InitialPersonMoney') ? input('InitialPersonMoney') : 0;
            if ($ratio<1) {
                $ratio = 1;
            }
            if ($ratio>200) {
                $ratio = 200;
            }
            $InitialPersonMoney = $InitialPersonMoney * bl;
            $res = $this->sendGameMessage('CMD_WD_SET_USER_CTRL_DATA', [$roleid, $ratio, $time, $timeinterval,$InitialPersonMoney], 'DC', 'ProcessDMSetRoomRate');
//            dump($res);
//            $socket = new QuerySocket();
//            $res= $socket->setRoleRate($roleid, $ratio, $time, $timeinterval);
            ob_clean();
            $request=$this->request->request();
            unset($request['s']);
            GameLog::logData(__METHOD__, $request,1,json_encode($res));
            return $this->apiReturn(0, [], '控制启动成功');
        }

        $roleid = intval(input('roleid')) ? intval(input('roleid')) : '';
        $ratio = intval(input('ratio')) ? intval(input('ratio')) : '';
        $time = intval(input('time')) ? intval(input('time')) : '10000000';
        $InitialPersonMoney = input('InitialPersonMoney') ? input('InitialPersonMoney') : 0;
//        $timeinterval = intval(input('timeinterval')) ? intval(input('timeinterval')) : '';
        $readonly = intval(input('readonly')) ? intval(input('readonly')) : '';
        
        $winrate =config('winrate');
        $this->assign('tigerrate', $winrate);
        $this->assign('roleid', $roleid);
        $this->assign('ratio', intval($ratio));
        $this->assign('time', intval($time));
        $this->assign('InitialPersonMoney', $InitialPersonMoney);
//        $this->assign('timeinterval', intval($timeinterval));
        $this->assign('read', intval($readonly));
        return $this->fetch();
    }


    /**
     * 设置玩家老虎机胜率
     */
    public function setTigerPlayerRate() {

        if ($this->request->isAjax()) {
            $roleid = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $ratio = intval(input('ratio')) ? intval(input('ratio')) : 0;
            $InitialPersonMoney = input('InitialPersonMoney') ? input('InitialPersonMoney') : 0;
            if ($ratio<1) {
                $ratio = 1;
            }
            if ($ratio>200) {
                $ratio = 200;
            }
//            $ratio =$ratio -2;
//            if($ratio==95)
//                $ratio = 100;
            $InitialPersonMoney = $InitialPersonMoney * bl;
            $socket = new QuerySocket();
            $res = $socket->setRoleRate($roleid, $ratio,0,0,$InitialPersonMoney);
            ob_clean();
            GameLog::logData(__METHOD__, $this->request->request());
            return $this->apiReturn(0, [], '修改成功');
        }

        $roleid = intval(input('roleid')) ? intval(input('roleid')) : '';
        $ratio = intval(input('ratio')) ? intval(input('ratio')) : '';
        $readonly = intval(input('readonly')) ? intval(input('readonly')) : '';
        $InitialPersonMoney = input('InitialPersonMoney') ? input('InitialPersonMoney') : 0;
        $winrate =config('winrate');

        $this->assign('tigerrate',$winrate);
        $this->assign('roleid', $roleid);
        $this->assign('ratio', intval($ratio));
        $this->assign('read', intval($readonly));
        $this->assign('InitialPersonMoney', $InitialPersonMoney);
        return $this->fetch();
    }

    /**
     * 查看伙牌
     */
    public function lookPartnerCard() {
        if ($this->request->isAjax()) {

            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $accountname = input('accountname');
//            $roomId = $this->request->request('roomid');
//            var_dump($roleId );die;
            $res = Api::getInstance()->sendRequest([
//                'roleid'   => $roleId,
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


    //查看玩家详情
    public function detail() {

//        $uniqueid = input('uniqueid') ? input('uniqueid') : '';
//        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
//        $res = Api::getInstance()->sendRequest([
//            'uniqueid' => $uniqueid,
//            'roomid' => $roomId,
//        ], 'game', 'drawinfo');
        //        $res = json_decode($res['data']['gamedetail'], true);
        $uniqueid = input('uniqueid') ? input('uniqueid') : 0;
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
                $res['card']['host1'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'], ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['card']['host1']);
                $host1 = explode(",", $res['card']['host1']);
                $host1 = array_slice($host1, 0, 17);
                $this->assign('host1', $host1);

                if ($res['remaincard']['host1']) {
                    $res['remaincard']['host1'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'], ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['remaincard']['host1']);
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
                $res['card']['host2'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'], ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['card']['host2']);
                $host1 = explode(",", $res['card']['host2']);
                $host1 = array_slice($host1, 0, 17);
                $this->assign('host1', $host1);

                if ($res['remaincard']['host2']) {
                    $res['remaincard']['host2'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'], ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['remaincard']['host2']);
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
                $res['card']['host2'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'], ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['card']['host0']);
                $host1 = explode(",", $res['card']['host2']);
                $host1 = array_slice($host1, 0, 17);
                $this->assign('host1', $host1);

                if ($res['remaincard']['host0']) {
                    $res['remaincard']['host2'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'], ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['remaincard']['host0']);
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
                $res['card']['player0'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'], ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['card']['player0']);
                $player0 = explode(",", $res['card']['player0']);
                $player0 = array_slice($player0, 0, 17);
                $this->assign('player0', $player0);

                if ($res['remaincard']['player0']) {
                    $res['remaincard']['player0'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'], ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['remaincard']['player0']);
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
                $res['card']['player2'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'], ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['card']['player2']);
                $player2 = explode(",", $res['card']['player2']);
                $player2 = array_slice($player2, 0, 17);
                $this->assign('player2', $player2);

                if ($res['remaincard']['player2']) {
                    $res['remaincard']['player2'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'], ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['remaincard']['player2']);
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
                $res['card']['player2'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'], ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['card']['player1']);
                $player2 = explode(",", $res['card']['player2']);
                $player2 = array_slice($player2, 0, 17);
                $this->assign('player2', $player2);

                if ($res['remaincard']['player1']) {
                    $res['remaincard']['player2'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'], ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['remaincard']['player1']);
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
                $res['card']['player2'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'], ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['card']['player1']);
                $player2 = explode(",", $res['card']['player2']);
                $player2 = array_slice($player2, 0, 17);
                $this->assign('player2', $player2);

                if ($res['remaincard']['player1']) {
                    $res['remaincard']['player2'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'], ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['remaincard']['player1']);
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

                $res['card']['player2'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'], ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['card']['player2']);
                $player2 = explode(",", $res['card']['player2']);
                $player2 = array_slice($player2, 0, 17);
                $this->assign('player0', $player2);

                if ($res['remaincard']['player2']) {
                    $res['remaincard']['player2'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'], ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $res['remaincard']['player2']);
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


    /**
     * 房间总览
     */
    public function index() {
        $res = Api::getInstance()->sendRequest(['id' => 0], 'room', 'list');
        $this->assign('roomlist', isset($res['data']['ResultData']) ? $res['data']['ResultData'] : []);
        $this->assign('historytotal', isset($res['data']['historytotal']) ? $res['data']['historytotal'] : 0);
        $this->assign('currentscore', isset($res['data']['currentscore']) ? $res['data']['currentscore'] : 0);
        $this->assign('totalonline', isset($res['data']['totalonline']) ? $res['data']['totalonline'] : 0);
        return $this->fetch();
    }

    /**
     * 捕鱼
     */
    public function buyu() {
        $kindId = 2223;
        if ($this->request->isAjax()) {
        }
        $roomList = $this->getRoomById($kindId);
        $this->assign('roomlist', $roomList);
        return $this->fetch('danren');
    }


    /**
     * 经典牛牛
     */

    public function jdniuniu() {
        $kindId = 1140;
        if ($this->request->isAjax()) {
        }
        $roomList = $this->getRoomById($kindId);
        $this->assign('roomlist', $roomList);
//        return $this->fetch('danren');
//        return $this->fetch('zjh');
        return $this->fetch('mpqz');
    }

    /**
     * 二人麻将
     */
    public function majiang() {
        $kindId = 9006;
        if ($this->request->isAjax()) {
        }
        $roomList = $this->getRoomById($kindId);
        $this->assign('roomlist', $roomList);
        return $this->fetch();
    }

    /**
     * 斗地主
     */
    public function doudizhu() {
        $kindId = 1072;
        if ($this->request->isAjax()) {

        }
        $roomList = $this->getRoomById($kindId);
        $this->assign('roomlist', $roomList);
//        return $this->fetch('danren');
        return $this->fetch();
    }

    /**
     * 获取房间数据
     * @return mixed
     */
    public function roomData() {
        $roomid = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $res = Api::getInstance()->sendRequest([
            'id' => $roomid
        ], 'room', 'list');
        return $this->apiReturn(
            $res['code'],
            isset($res['data']['ResultData'][0]) ? $res['data']['ResultData'][0] : [],
            $res['message'],
            $res['total']);
    }

    /**
     * 龙虎斗
     */
    public function longhudou() {
        $kindId = 1100;
        if ($this->request->isAjax()) {

        }
        $roomList = $this->getRoomById($kindId);
        $this->assign('roomlist', $roomList);
//        return $this->fetch('bairen');
        return $this->fetch();
    }

    /**
     * 百家乐
     */
    public function bjl() {
        $kindId = 1150;
        if ($this->request->isAjax()) {
        }
        $roomList = $this->getRoomById($kindId);
        $this->assign('roomlist', $roomList);
//        return $this->fetch('bairen');
        return $this->fetch();
    }

    /**
     * 奔驰宝马
     */
    public function bcbm() {
        $kindId = 500;
        if ($this->request->isAjax()) {
        }
        $roomList = $this->getRoomById($kindId);
        $this->assign('roomlist', $roomList);
//        return $this->fetch('bairen');
        return $this->fetch();
    }

    /**
     * 飞禽走兽
     */
    public function fqzs() {
        $kindId = 1109;
        if ($this->request->isAjax()) {
        }
        $roomList = $this->getRoomById($kindId);
        $this->assign('roomlist', $roomList);
//        return $this->fetch('bairen');
        return $this->fetch('bcbm');
    }

    /**
     * 红黑大战
     */
    public function hhdz() {
        $kindId = 9005;
        if ($this->request->isAjax()) {
        }
        $roomList = $this->getRoomById($kindId);
        $this->assign('roomlist', $roomList);
//        return $this->fetch('bairen');
        return $this->fetch('longhudou');
    }

    /**
     * 百人牛牛
     */
    public function brnn() {
        $kindId = 9000;
        if ($this->request->isAjax()) {
        }
        $roomList = $this->getRoomById($kindId);
        $this->assign('roomlist', $roomList);
//        return $this->fetch('bairen');
        return $this->fetch();
    }

    /**
     * Notes:红包扫雷
     */
    public function hbsl() {
        $kindId = 9004;
        if ($this->request->isAjax()) {
        }
        $roomList = $this->getRoomById($kindId);
        $this->assign('roomlist', $roomList);
        return $this->fetch('danren');
    }


    /**
     * Notes:水果拉霸
     */
    public function sglb() {
        $kindId = 3224;
        if ($this->request->isAjax()) {
        }
        $roomList = $this->getRoomById($kindId);
        $this->assign('roomlist', $roomList);
//        return $this->fetch('bairen');
        return $this->fetch('danren');
    }

    /**
     * Notes:欢乐骰宝
     */
    public function hlsb() {
        $kindId = 5007;
        if ($this->request->isAjax()) {
        }
        $roomList = $this->getRoomById($kindId);
        $this->assign('roomlist', $roomList);
//        return $this->fetch('bairen');
        return $this->fetch();
    }

    /**
     * Notes:炸金花
     */


    public function zjh() {
        $kindId = 9008;
        if ($this->request->isAjax()) {

        }
        $roomList = $this->getRoomById($kindId);
        $this->assign('roomlist', $roomList);
//        return $this->fetch('danren');
        return $this->fetch();
    }


    /**
     * 明牌抢庄
     */
    public function qzniuniu()
//    public function mpqz()
    {
        $kindId = 1010;
        if ($this->request->isAjax()) {

        }
        $roomList = $this->getRoomById($kindId);
        $this->assign('roomlist', $roomList);
//        return $this->fetch('danren');
//        return $this->fetch('zjh');
        return $this->fetch('mpqz');
    }


    /**
     * 查看伙牌
     */
    public function lookPartnerCardZjh() {
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
                    $item['card'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'], ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $item['card']);
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

    public function lookPartnerCardMpqz() {
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
                    $item['card'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'], ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $item['card']);
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

    public function lookPartnerCardBrnn() {
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
                    $item['card'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'], ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $item['card']);
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

    public function lookPartnerCardDzpk() {
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
                    $item['card'] = str_replace(['黑', '梅', '方', '红', '大王4', '小王'], ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $item['card']);
                    $item['card'] = explode(",", $item['card']);
                    $item['tablecard'] = str_replace(['黑', '梅', '方', '红', '大王4', '小王'], ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $item['tablecard']);
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

    public function lookPartnerCardHlsb() {
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


    public function lookPartnerCardMj() {
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
                    $item['card'] = str_replace(['一', '二', '三', '四', '五', '六', '七', '八', '九', '万', '条'], ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'w', 't'], $item['card']);
                    $item['card'] = str_replace(['东', '南', '西', '北', '白', '发', '中'], ['df', 'nf', 'xf', 'bf', 'bb', 'fc', 'hz'], $item['card']);
                    $item['tablecard'] = str_replace(['一', '二', '三', '四', '五', '六', '七', '八', '九', '万', '条'], ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'w', 't'], $item['tablecard']);
                    $item['tablecard'] = str_replace(['东', '南', '西', '北', '白', '发', '中'], ['df', 'nf', 'xf', 'bf', 'bb', 'fc', 'hz'], $item['tablecard']);
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

    public function detailMj() {
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
                    $item['card'] = str_replace(['一', '二', '三', '四', '五', '六', '七', '八', '九', '万', '条'], ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'w', 't'], $item['card']);
                    $item['card'] = str_replace(['东', '南', '西', '北', '白', '发', '中'], ['df', 'nf', 'xf', 'bf', 'bb', 'fc', 'hz'], $item['card']);
                    $item['tablecard'] = str_replace(['一', '二', '三', '四', '五', '六', '七', '八', '九', '万', '条'], ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'w', 't'], $item['tablecard']);
                    $item['tablecard'] = str_replace(['东', '南', '西', '北', '白', '发', '中'], ['df', 'nf', 'xf', 'bf', 'bb', 'fc', 'hz'], $item['tablecard']);
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

    public function lookPartnerCardBjl() {
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
                    $item['card'] = str_replace(['banker', 'playercouple', 'player', 'equal', 'bankercouple'], ['庄', '闲对', '闲 ', '和', '庄对'], $item['card']);
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

    public function lookPartnerCardLonghudou() {
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

    public function lookPartnerCardBcbm() {
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


    //查看玩家详情
    public function detailZjh() {
        if ($this->request->isAjax()) {
            $uniqueid = input('uniqueid') ? input('uniqueid') : 0;
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
                    $item['card'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'], ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $item['card']);
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

    public function detailMpqz() {
        if ($this->request->isAjax()) {
            $uniqueid = input('uniqueid') ? input('uniqueid') : 0;
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
                    $item['card'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'], ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $item['card']);
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

    public function detailOther() {
        if ($this->request->isAjax()) {
            $uniqueid = input('uniqueid') ? input('uniqueid') : 0;
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
                    $item['card'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'], ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $item['card']);
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

    public function detailHlsb() {
        if ($this->request->isAjax()) {
            $uniqueid = input('uniqueid') ? input('uniqueid') : 0;
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


    public function detailDzpk() {
        if ($this->request->isAjax()) {
            $uniqueid = input('uniqueid') ? input('uniqueid') : 0;
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
                    $item['card'] = str_replace(['黑', '梅', '方', '红', '大王4', '小王'], ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $item['card']);
                    $item['card'] = explode(",", $item['card']);
                    $item['tablecard'] = str_replace(['黑', '梅', '方', '红', '大王4', '小王'], ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $item['tablecard']);
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

    public function detailBrnn() {
        if ($this->request->isAjax()) {
            $uniqueid = input('uniqueid') ? input('uniqueid') : 0;
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
                    $item['card'] = str_replace(['黑桃', '梅花', '方块', '红桃', '大王4', '小王'], ['T', 'M', 'F', 'H', 'bg_brand_king_01', 'bg_brand_wang_01'], $item['card']);
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

    public function detailBjl() {
        if ($this->request->isAjax()) {
            $uniqueid = input('uniqueid') ? input('uniqueid') : 0;
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
                    $item['card'] = str_replace(['banker', 'playercouple', 'player', 'equal', 'bankercouple'], ['庄', '闲对', '闲 ', '和', '庄对'], $item['card']);

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

    public function detailLonghudou() {
        if ($this->request->isAjax()) {
            $uniqueid = input('uniqueid') ? input('uniqueid') : 0;
            $userid = input('userid ') ? input('userid ') : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $date = input('addtime','');
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;

            if(empty($date)){
                return $this->apiReturn(100, [], '请求失败');
            }
            $table = "T_GameDetail_$date as a ";
            $db = new GameOCDB();
            $field ='uniqueid,userid as player,gamedetail as usergd,addtime';
            $res = $db->getTablePage($table, $page, $limit, "And UniqueId=$uniqueid and UserId>0 ", "ID", "ASC", $field, true);


            $res2 = $db->getTablePage($table, $page, $limit, "And UniqueId=$uniqueid and UserId=0 ", "ID", "ASC", $field, false);

            $gameresult ='';

            $detail =[];
            if($res2){
                $detail= $res2['list'][0];
            }
            //var_dump($detail['usergd']);
           if(!empty($detail['usergd'])){
               $detail =json_decode($detail['usergd'],true);
                if(!empty($detail)){
                    $gameresult =$detail['gameresult'];
                }
           }



//            $res = Api::getInstance()->sendRequest([
//                'uniqueid' => $uniqueid,
//                'roomid' => $roomId,
//                'userid' => $userid,
//                'tag' => 1,
//                'page' => $page,
//                'pagesize' => $limit
//            ], 'game', 'getcart2');

            $list=[];
            if (!empty($res['list'])) {
                $list =$res['list'];
                foreach ($list as $key=>&$item) {
                    $json = preg_replace('/\s/', '',$item['usergd']);
                    $json = str_replace('\n','',$json);
                    $json = str_replace('\\','',$json);
                    $cardata= json_decode('[' .  $json.']',true);
                    if(!empty($cardata[0])){
                        $cardata = $cardata[0];
                    }
                    if(!empty($cardata['lost'])){
                        $item['changemoney'] = $cardata['lost'];
                    }
                    else if(!empty($cardata['win'])){
                        $item['changemoney'] = $cardata['win'];
                    }
                    else
                    {
                        $item['changemoney'] =0;
                    }
                    $item['changemoney'] = FormatMoney($item['changemoney']);
                    $betdetail = '';
                    if($cardata['bet']['dragon']>0){
                        $betdetail.= lang('龙').'：'.FormatMoney($cardata['bet']['dragon']).'&nbsp;&nbsp;';
                    }
                    if($cardata['bet']['equal']>0){
                        $betdetail.=lang('和').'：'.FormatMoney($cardata['bet']['equal']).'&nbsp;&nbsp;';
                    }

                    if($cardata['bet']['tiger']>0){
                        $betdetail.=lang('虎').'：'.FormatMoney($cardata['bet']['tiger']).'&nbsp;&nbsp;';
                    }
                    $betdetail = '{'.$betdetail.'}&nbsp;'.lang('总下注').':';
                    $item['totalbet'] =$cardata['bet']['dragon']+ $cardata['bet']['equal']+$cardata['bet']['tiger'];
                    $item['totalbet']= FormatMoney($item['totalbet']);
                    $betdetail.= $item['totalbet'];

                    $item['card'] =$gameresult;
                    $item['betdetail'] = $betdetail;
                }
                unset($item);
            }
            return $this->apiReturn(0, $list, 'success', $res['count'],lang($gameresult));
        }
        $uniqueid = input('uniqueid') ? input('uniqueid') : '';
        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $addtime = input('addtime','');
        $this->assign('addtime',$addtime);
        $this->assign('uniqueid', $uniqueid);
        $this->assign('roomId', $roomId);


        return $this->fetch();
    }




    public function detailBcbm() {
        if ($this->request->isAjax()) {
            $uniqueid = input('uniqueid') ? input('uniqueid') : 0;
            $userid = input('userid ') ? input('userid ') : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $date = input('addtime','');
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;

            if(empty($date)){
                return $this->apiReturn(100, [], lang('请求失败'));
            }
            $table = "T_GameDetail_$date as a ";
            $db = new GameOCDB();
            $field ='uniqueid,userid as player,gamedetail as usergd,addtime';
            $res = $db->getTablePage($table, $page, $limit, "And UniqueId=$uniqueid and UserId>0 ", "ID", "ASC", $field, true);


            $res2 = $db->getTablePage($table, $page, $limit, "And UniqueId=$uniqueid and UserId=0 ", "ID", "ASC", $field, false);

            $gameresult ='';

            $detail =[];
            if($res2){
                $detail= $res2['list'][0];
            }
            //var_dump($detail['usergd']);
            if(!empty($detail['usergd'])){
                $detail =json_decode($detail['usergd'],true);
                if(!empty($detail)){
                    $gameresult =$detail['gameresult'];
                }
            }

            $list=[];
            if (!empty($res['list'])) {
                $list =$res['list'];
                foreach ($list as $key=>&$item) {
                    $json = preg_replace('/\s/', '',$item['usergd']);
                    $json = str_replace('\n','',$json);
                    $json = str_replace('\\','',$json);
                    $cardata= json_decode('[' .  $json.']',true);
                    if(!empty($cardata[0])){
                        $cardata = $cardata[0];
                    }
                    if(!empty($cardata['lost'])){
                        $item['changemoney'] = $cardata['lost'];
                    }
                    else if(!empty($cardata['win'])){
                        $item['changemoney'] = $cardata['win'];
                    }
                    else
                    {
                        $item['changemoney'] =0;
                    }
                    $item['changemoney'] = FormatMoneyint($item['changemoney']);

                    $betdetail='';
                    if($cardata['bet']['大宝马']>0){
                        $betdetail.=lang('大宝马').'：'.FormatMoneyint($cardata['bet']['大宝马']).'<br/>';
                    }
                    if($cardata['bet']['大宝时捷']>0){
                        $betdetail.=lang('大宝时捷').'：'.FormatMoneyint($cardata['bet']['大宝时捷']).'<br/>';

                    }
                    if($cardata['bet']['大奔驰']>0){
                        $betdetail.=lang('大奔驰').'：'.FormatMoneyint($cardata['bet']['大奔驰']).'<br/>';
                    }
                    if($cardata['bet']['大大众']>0){
                        $betdetail.=lang('大大众').'：'.FormatMoneyint($cardata['bet']['大大众']).'<br/>';
                    }

                    if($cardata['bet']['小宝马']>0){
                        $betdetail.=lang('小宝马').'：'.FormatMoneyint($cardata['bet']['小宝马']).'<br/>';
                    }

                    if($cardata['bet']['小宝时捷']>0){
                        $betdetail.=lang('小宝时捷').'：'.FormatMoneyint($cardata['bet']['小宝时捷']).'<br/>';
                    }

                    if($cardata['bet']['小奔驰']>0){
                        $betdetail.=lang('小奔驰').'：'.FormatMoneyint($cardata['bet']['小奔驰']).'<br/>';
                    }

                    if($cardata['bet']['小大众']>0){
                        $betdetail.=lang('小大众').'：'.FormatMoneyint($cardata['bet']['小大众']).'<br/>';
                    }

                    $item['betdetail'] = $betdetail;
                    $item['totalbet'] = $cardata['bet']['大宝马'] + $cardata['bet']['大宝时捷']+ $cardata['bet']['大奔驰']
                                        +$cardata['bet']['大大众']+$cardata['bet']['小宝马']+$cardata['bet']['小宝时捷']
                                        +$cardata['bet']['小奔驰']+$cardata['bet']['小大众'];
                    $item['totalbet']= FormatMoneyint($item['totalbet']);
                    $item['card'] = lang(trim($gameresult));
                }
                unset($item);
            }
            return $this->apiReturn(0, $list, 'success', $res['count'],lang(trim($gameresult)));
        }
        $uniqueid = input('uniqueid') ? input('uniqueid') : '';
        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $addtime = input('addtime','');
        $this->assign('addtime',$addtime);
        $this->assign('uniqueid', $uniqueid);
        $this->assign('roomId', $roomId);


        return $this->fetch();
    }

    public function detailFqzs(){
        if ($this->request->isAjax()) {
            $uniqueid = input('uniqueid') ? input('uniqueid') : 0;
            $userid = input('userid ') ? input('userid ') : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $date = input('addtime','');
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;

            if(empty($date)){
                return $this->apiReturn(100, [], lang('请求失败'));
            }
            $table = "T_GameDetail_$date as a ";
            $db = new GameOCDB();
            $field ='uniqueid,userid as player,gamedetail as usergd,addtime';
            $res = $db->getTablePage($table, $page, $limit, "And UniqueId=$uniqueid and UserId>0 ", "ID", "ASC", $field, true);


            $res2 = $db->getTablePage($table, $page, $limit, "And UniqueId=$uniqueid and UserId=0 ", "ID", "ASC", $field, false);

            $gameresult ='';

            $detail =[];
            if($res2){
                $detail= $res2['list'][0];
            }
            //var_dump($detail['usergd']);
            if(!empty($detail['usergd'])){
                $detail =json_decode($detail['usergd'],true);
                if(!empty($detail)){
                    $gameresult =$detail['gameresult'];
                }
            }

            $list=[];
            if (!empty($res['list'])) {
                $list =$res['list'];
                foreach ($list as $key=>&$item) {
                    $json = preg_replace('/\s/', '',$item['usergd']);
                    $json = str_replace('\n','',$json);
                    $json = str_replace('\\','',$json);
                    $cardata= json_decode('[' .  $json.']',true);
                    if(!empty($cardata[0])){
                        $cardata = $cardata[0];
                    }
                    if(!empty($cardata['lost'])){
                        $item['changemoney'] = $cardata['lost'];
                    }
                    else if(!empty($cardata['win'])){
                        $item['changemoney'] = $cardata['win'];
                    }
                    else
                    {
                        $item['changemoney'] =0;
                    }
                    $item['changemoney'] = FormatMoneyint($item['changemoney']);

                    $betdetail='';
                    if($cardata['bet']['飞禽']>0){
                        $betdetail.=lang('飞禽').'：'.FormatMoneyint($cardata['bet']['飞禽']).'<br/>';
                    }
                    if($cardata['bet']['猴子']>0){
                        $betdetail.=lang('猴子').'：'.FormatMoneyint($cardata['bet']['猴子']).'<br/>';

                    }
                    if($cardata['bet']['鸡']>0){
                        $betdetail.=lang('鸡').'：'.FormatMoneyint($cardata['bet']['鸡']).'<br/>';
                    }
                    if($cardata['bet']['老鹰']>0){
                        $betdetail.=lang('老鹰').'：'.FormatMoneyint($cardata['bet']['老鹰']).'<br/>';
                    }

                    if($cardata['bet']['猫头鹰']>0){
                        $betdetail.=lang('猫头鹰').'：'.FormatMoneyint($cardata['bet']['猫头鹰']).'<br/>';
                    }

                    if($cardata['bet']['狮子']>0){
                        $betdetail.=lang('狮子').'：'.FormatMoneyint($cardata['bet']['狮子']).'<br/>';
                    }

                    if($cardata['bet']['兔子']>0){
                        $betdetail.=lang('兔子').'：'.FormatMoneyint($cardata['bet']['兔子']).'<br/>';
                    }

                    if($cardata['bet']['鸵鸟']>0){
                        $betdetail.=lang('鸵鸟').'：'.FormatMoneyint($cardata['bet']['鸵鸟']).'<br/>';
                    }
                    if($cardata['bet']['熊猫']>0){
                        $betdetail.=lang('熊猫').'：'.FormatMoneyint($cardata['bet']['熊猫']).'<br/>';
                    }
                    if($cardata['bet']['走兽']>0){
                        $betdetail.=lang('走兽').'：'.FormatMoneyint($cardata['bet']['走兽']).'<br/>';
                    }
                    if($cardata['bet']['鲨鱼']>0){
                        $betdetail.=lang('鲨鱼').'：'.FormatMoneyint($cardata['bet']['鲨鱼']).'<br/>';
                    }
                    $item['betdetail'] = $betdetail;
                    $item['totalbet'] = $cardata['bet']['飞禽'] + $cardata['bet']['猴子']+ $cardata['bet']['鸡']
                                        +$cardata['bet']['老鹰']+$cardata['bet']['猫头鹰']+$cardata['bet']['狮子']
                                        +$cardata['bet']['兔子']+$cardata['bet']['鸵鸟']+$cardata['bet']['熊猫']+$cardata['bet']['走兽']+$cardata['bet']['鲨鱼'];
                    $item['totalbet']= FormatMoneyint($item['totalbet']);
                    $item['card'] = lang(trim($gameresult));
                }
                unset($item);
            }
            return $this->apiReturn(0, $list, 'success', $res['count'],lang(trim($gameresult)));
        }
        $uniqueid = input('uniqueid') ? input('uniqueid') : '';
        $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
        $addtime = input('addtime','');
        $this->assign('addtime',$addtime);
        $this->assign('uniqueid', $uniqueid);
        $this->assign('roomId', $roomId);
        return $this->fetch();
    }
    
    //FortuneWheel
    public function detailbairen() {
        if ($this->request->isAjax()) {
            $uniqueid = input('uniqueid') ? input('uniqueid') : 0;
            $userid = input('userid ') ? input('userid ') : 0;
            $roomId = intval(input('roomid')) ? intval(input('roomid')) : 0;
            $date = input('addtime','');
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;

            if(empty($date)){
                return $this->apiReturn(100, [], '请求失败');
            }
            $table = "T_GameDetail_$date as a ";
            $db = new GameOCDB();
            $field ='uniqueid,userid as player,gamedetail as usergd,addtime';
            $res = $db->getTablePage($table, $page, $limit, "And UniqueId=$uniqueid", "userId", "asc", $field, true);

            $gameresult ='';
            $detail =[];
            if($res){
                $detail= $res['list'][0];
            }
            //var_dump($detail['usergd']);
            if(!empty($detail['usergd'])){
                $detail =json_decode($detail['usergd'],true);
                if(!empty($detail)){
                    $gameresult =$detail['gameresult'];
                }
            }

            $list=[];
            if (!empty($res['list'])) {
                $list =$res['list'];
                unset($list[0]);
                foreach ($list as $key=>&$item) {
                    $json = preg_replace('/\s/', '',$item['usergd']);
                    $json = str_replace('\n','',$json);
                    $json = str_replace('\\','',$json);
                    $cardata= json_decode('[' .  $json.']',true);
                    if(!empty($cardata[0])){
                        $cardata = $cardata[0];
                    }
                    if(!empty($cardata['lost'])){
                        $item['changemoney'] = $cardata['lost'];
                    }
                    else if(!empty($cardata['win'])){
                        $item['changemoney'] = $cardata['win'];
                    }
                    else
                    {
                        $item['changemoney'] =0;
                    }
                    $item['changemoney'] = FormatMoneyint($item['changemoney']);

                    $betdetail='';
                    $totalbet =0;
                    $roomId = intval($roomId/10.0)*10;
                    switch ($roomId){
                        case 3700:
                            if($cardata['bet']['blue']>0){
                                $betdetail.='blue：'.FormatMoneyint($cardata['bet']['blue']).'<br/>';
                            }
                            if($cardata['bet']['red']>0){
                                $betdetail.='red：'.FormatMoneyint($cardata['bet']['red']).'<br/>';

                            }
                            if($cardata['bet']['yellow']>0){
                                $betdetail.='yellow：'.FormatMoneyint($cardata['bet']['yellow']).'<br/>';
                            }
                            $totalbet =$cardata['bet']['blue'] + $cardata['bet']['red']+ $cardata['bet']['yellow'];
                            break;
                        case 20000:

                            break;
                        case 20100:
                            if(!empty($cardata['bet']['蓝色'])){
                                $betdetail.=lang('蓝色').'：'.FormatMoneyint($cardata['bet']['蓝色']).'<br/>';
                                $totalbet += $cardata['bet']['蓝色'];
                            }
                            if(!empty($cardata['bet']['方块'])){
                                $betdetail.=lang('方块').'：'.FormatMoneyint($cardata['bet']['方块']).'<br/>';
                                $totalbet += $cardata['bet']['方块'];
                            }
                            if(!empty($cardata['bet']['黑桃'])){
                                $betdetail.=lang('黑桃').'：'.FormatMoneyint($cardata['bet']['黑桃']).'<br/>';
                                $totalbet += $cardata['bet']['黑桃'];
                            }
                            if(!empty($cardata['bet']['红色'])){
                                $betdetail.=lang('红色').'：'.FormatMoneyint($cardata['bet']['红色']).'<br/>';
                                $totalbet += $cardata['bet']['红色'];
                            }
                            if(!empty($cardata['bet']['红桃'])){
                                $betdetail.=lang('红桃').'：'.FormatMoneyint($cardata['bet']['红桃']).'<br/>';
                                $totalbet += $cardata['bet']['红桃'];
                            }
                            if(!empty($cardata['bet']['黄色'])){
                                $betdetail.=lang('黄色').'：'.FormatMoneyint($cardata['bet']['黄色']).'<br/>';
                                $totalbet += $cardata['bet']['黄色'];
                            }
                            if(!empty($cardata['bet']['梅花'])){
                                $betdetail.=lang('梅花').'：'.FormatMoneyint($cardata['bet']['梅花']).'<br/>';
                                $totalbet += $cardata['bet']['梅花'];
                            }
                            break;

                    }
                    $item['totalbet'] =FormatMoneyint($totalbet);
                    $item['betdetail'] = $betdetail;
                    $item['card'] = lang($gameresult);
                }
                unset($item);
            }
            return $this->apiReturn(0, $list, 'success', $res['count'],lang($gameresult));
        }
        $uniqueid = input('UniqueId') ? input('UniqueId') : '';
        $roomId = intval(input('RoomID')) ? intval(input('RoomID')) : 0;
        $addtime = input('date','');

        $this->assign('addtime',$addtime);
        $this->assign('uniqueid', $uniqueid);
        $this->assign('roomId', $roomId);
        return $this->fetch();
    }




    /**
     * Notes:抢庄牌九
     */
    public function qzpj() {
        $kindId = 10000;
        if ($this->request->isAjax()) {
        }
        $roomList = $this->getRoomById($kindId);
        $this->assign('roomlist', $roomList);
        return $this->fetch('bairen');
    }




    ///玩家详情页
    public function  gamedetaillist(){

        $roleId = input('roleid','');
        $playernum = input('playernum',0);
        $this->assign('roleid', $roleId);
        $this->assign('start', input('strartdate') ?: date('Y-m-d').' 00:00:00');
        $this->assign('end', input('enddate')  ?: date('Y-m-d').' 23:23:59');
        switch (input('Action')) {
            case 'list':
                $unistr = '';
                $db = new GameOCDB();

                if($roleId || $playernum) {
                    $retdata = $db->GetUniqueIDRecord(true);
                    if ($retdata) {
                        $uniqueid = array_column($retdata, 'UniqueId');
                        $unistr = implode(',', $uniqueid);
                    } else {
                        $unistr = '0,0';
                    }
                }
                $result = $db->GetGameDetailRecord($unistr,true);
                return $this->apiJson($result, true);
            case 'exec':
                //权限验证 
                $auth_ids = $this->getAuthIds();
                if (!in_array(10008, $auth_ids)) {
                    return $this->apiJson(["code"=>1,"msg"=>"没有权限"]);
                }
                $db = new  GameOCDB();
                $result = $db->GetGameDetailRecord();
                $outAll = input('outall', false);
                if ((int)input('exec', 0) == 0) {
                    if ($result['count'] == 0) {
                        $result = ["count" => 0, "code" => 1, 'msg' => "没有找到任何数据,换个姿势再试试?"];
                    }
                    if ($result['count'] >= 5000 && $outAll == false) {
                        $result = ["code" => 2, 'msg' => "数据超过5000行是否全部导出?<br>只能导出一部分数据.</br>请选择筛选条件,让数据少于5000行<br>当前数据一共有" . $result['count'] . "行"];
                    }
                    unset($result['list']);
                    return $this->apiJson($result);
                }
                //导出表格
                if ((int)input('exec', 0) == 1 && $outAll = true) {
                    $header_types = ['玩家ID' => 'integer', '房间名' => 'string', '输赢情况' => "0.00",
                        '免费游戏' => 'string', '下注金额' => "0.00", '输赢金币' => "0.00", '中奖金币' => "0.00", '上局金币' => "0.00",
                        '当前金币' => "0.00", '创建时间' => 'datetime'];
                    $filename = '游戏日志-' . date('YmdHis');
                    $rows =& $result['list'];
                    $writer = $this->GetExcel($filename, $header_types, $rows, true);
                    foreach ($rows as $index => &$row) {
                        $gamestate = '';
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
        //$selectData = $this->getRoomList();
        $kindlist = $this->GetKindList();
        foreach ($kindlist as $k=>&$v){
            $v['KindName'] =lang($v['KindName']);
        }
        unset($v);
        $this->assign('selectData', $kindlist);
        return $this->fetch();
    }

















}
