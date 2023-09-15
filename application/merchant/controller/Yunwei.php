<?php

namespace app\merchant\controller;

use app\admin\controller\traits\getSocketRoom;
use app\admin\controller\traits\search;
use app\common\Api;
use app\common\GameLog;
use app\model\MasterDB;
use socket\MasterBLL;
use socket\QuerySocket;


class Yunwei extends Main
{
    use getSocketRoom;
    use search;

    private $socket = null;

    public function __construct()
    {
        parent::__construct();
        $this->socket = new QuerySocket();
    }

    public function GameRoomManager()
    {
        $isGet = request()->isGet();
        $request = request()->request();
        $roomID = intval(input('RoomID'));
        switch (input('Action')) {
            case "list":
                $KindID = input('kindid') ? input('kindid') : 0;
                $RoomName = input('roomname');
                $where = "";
                if ($KindID > 0) $where .= " AND KindID=$KindID";
                if (strlen($RoomName) > 0) $where .= " AND RoomName like '%$RoomName%'";
                $where.=' ';
                $db = new MasterDB();
                $res = $db->getTablePage('View_GameRoomInfo', input('page', 1), input('limit', 10), $where);
                foreach ($res['list'] as $key => &$value) {
                    $value['SChemeName']= lang($value['SChemeName']);
                }
                return $this->apiJson($res);
                break;
            case "edit":
                if ($isGet) {
                    $this->assign('action', "edit");
                    return $this->EditRoom($roomID, 'edit_room');
                } else {
                    $RoomID = $request['RoomID'];
                    unset($request['s'], $request['Action'], $request['RoomID']);
                    unset($request['isHundred']);
                    unset($request['RoomType']);
                    unset($request['StartForMinUser']);

                    if (!isset($request['AllowLook'])) $request['AllowLook'] = 0;//允许旁观：
                    if (!isset($request['CanJoinWhenPlaying'])) $request['CanJoinWhenPlaying'] = 0;//游戏开始后允许坐下
                    if (!isset($request['AutoRun'])) $request['AutoRun'] = 0;//自动启动客户端：
                    if (!isset($request['SetFlag'])) $request['SetFlag'] = 0;//房间禁止设置
                    if (!isset($request['RobotJoinWhenPlaying'])) $request['RobotJoinWhenPlaying'] = 0;//机器人权限
                    //halt($request);
                    $db = new  MasterDB();
                    $row = $db->updateTable('T_GameRoomInfo', $request, "RoomID=$RoomID");
                    if ($row > 0) return $this->success("修改成功");
                    return $this->error('修改失败');
                }
                break;
            case "copy":
                if ($isGet) {
                    $this->assign('action', "copy");
                    return $this->EditRoom($roomID, 'edit_room');
                } else {
                    unset($request['s'], $request['Action'], $request['RoomID']);
                    if (!isset($request['AllowLook'])) $request['AllowLook'] = 0;//允许旁观：
                    if (!isset($request['CanJoinWhenPlaying'])) $request['CanJoinWhenPlaying'] = 0;//游戏开始后允许坐下
                    if (!isset($request['AutoRun'])) $request['AutoRun'] = 0;//自动启动客户端：
                    if (!isset($request['SetFlag'])) $request['SetFlag'] = 0;//房间禁止设置
                    if (!isset($request['RobotJoinWhenPlaying'])) $request['RobotJoinWhenPlaying'] = 0;//机器人权限

                    $db = new  MasterDB();
                    $sqlRoomID = "SELECT MAX(RoomID)+1 RoomID FROM T_GameRoomInfo WHERE KindID=" . $request['KindID'];
                    $request['RoomID'] = $db->getTableQuery($sqlRoomID)[0]['RoomID'];

                    $row = $db->addrow('T_GameRoomInfo', $request);
                    if ($row > 0) return $this->success("复制成功");
                    return $this->error('复制失败');
                }

                break;
            case "switch":
                halt($request);
//                $db=new  MasterDB();
//                $db->GameType()->UPData($data, $where);
            case  'del':
                break;
        }
        $this->assign('kindlist', $this->GetKindList());
        return $this->fetch();
    }

    //编辑房间 GameRoomManager?Action=edit copy 有调用
    public function EditRoom($roomID, $tmp = null)
    {
        $db = new MasterDB();
        $roomInfo = $db->getTableRow('T_GameRoomInfo', "RoomID=$roomID");
        $serverInfo = $db->getTableRow('T_GameServerInfo', "ServerID=$roomID");

        $roomInfo['isExp'] = ($roomInfo['RoomType'] & 8) > 0 ? 1 : 0; //体验
        $roomInfo['isCheat'] = ($roomInfo['RoomType'] & 16) > 0 ? 1 : 0;//防作弊
        $roomInfo['isHundred'] = ($roomInfo['RoomType'] & 64) > 0 ? 1 : 0;//百人

        $this->assign('room', $roomInfo);
        $this->assign('srv', $serverInfo);
        $this->GetRoomList();
        $this->assign('tablelist', $this->gettablelist());
        $this->assign('serverlist', $this->GetServerList());
        $this->assign('kindlist', $this->GetKindList());
        return $this->fetch($tmp);
    }

//    //删除房间
//    public function deleteroom()
//    {
//        $roomId = intval(input('RoomID'));
//        $data = ['code' => 0, 'msg' => ''];
//        if (!$roomId) {
//            $data['code'] = 1;
//            $data['msg'] = '请选择要删除的房间';
//            return json($data);
//        }
//        $del = ['id' => $roomId];
//        $res = Api::getInstance()->sendRequest($del, 'room', 'delroom');
//        GameLog::logData(__METHOD__, $roomId, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
//        return json(['code' => $res['code'], 'msg' => $res['message']]);
//    }


    /**
     * 房间机器人管理
     */
    public function robotroom()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $data = ['page' => $page, 'pagesize' => $limit];
            $res = Api::getInstance()->sendRequest($data, 'room', 'roomrobot');
            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);
        }
        return $this->fetch();
    }

    /**
     * 房间机器人管理
     */
    public function robotroom2()
    {
        $strFields = "a.roomid,roomname,maxcount,robotwinweighted,robotwinmoney,servicetables";//
        $tableName = " [OM_MasterDB].[dbo].[T_RoomRobot] a, (SELECT roomid,roomname,Nullity FROM [OM_MasterDB].[dbo].[T_GameRoomInfo]) b"; //
        $where = " where a.roomid = b.roomid and  Nullity=0  ";//in (SELECT roomid FROM [OM_MasterDB].[dbo].[T_GameRoomInfo] where Nullity=0)
        $limits = "";
        $orderBy = " order by roomid desc";

        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $data = ['page' => $page, 'pagesize' => $limit];
            //拼装sql
            $limits = " top " . ($page * $limit);

            $comm = new CommonModel;
            $list = $comm->getPageList($tableName, $strFields, $where, $limits, $page, $limit, $orderBy);
            $count = $list['count'];
            $result = $list['list'];

            $res['data']['list'] = $result;
            $res['code'] = 0;
            $res['message'] = '';
            $res['total'] = $count;
            return $this->apiReturn($res['code'], $res['data']['list'], $res['message'], $res['total']);
        }
        return $this->fetch();
    }



    /**
     * Notes: 新增房间机器人管理
     * @return mixed
     */
//    public function addSuper()
    public function addRobot()
    {
        if ($this->request->isAjax()) {
            $request = $this->request->request();
            $insert = [
                'roomid' => intval($request['roomid']) ? intval($request['roomid']) : 0,
                'maxcount' => intval($request['maxcount']) ? intval($request['maxcount']) : 0,
                'robotwinweighted' => intval($request['robotwinweighted']) ? intval($request['robotwinweighted']) : '',
                'robotwinmoney' => intval($request['robotwinmoney']) ? intval($request['robotwinmoney']) : 0,
                'servicetables' => intval($request['servicetables']) ? intval($request['servicetables']) : 0,

                'addwinpre' => intval($request['victory']) ? intval($request['victory']) : 0,
                'mintakescore' => intval($request['minnum']) ? intval($request['minnum']) : 0,
                'maxtakescore' => intval($request['maxnum']) ? intval($request['maxnum']) : 0,
                'minplaydraw' => intval($request['mingame']) ? intval($request['mingame']) : 0,
                'maxplaydraw' => intval($request['maxgame']) ? intval($request['maxgame']) : 0,
                'minreposetime' => intval($request['mintime']) ? intval($request['mintime']) : 0,
                'maxreposetime' => intval($request['maxtime']) ? intval($request['maxtime']) : 0,
                'minleavepre' => intval($request['win']) ? intval($request['win']) : 0,
                'maxleavepre' => intval($request['lost']) ? intval($request['lost']) : 0,
            ];
//            var_dump($insert);die;

            $res = Api::getInstance()->sendRequest($insert, 'room', 'addroomrobot');

            //log记录
            GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
            return $this->apiReturn($res['code'], $res['data'], $res['message']);
        }
        $selectData = $this->getRoomList();
        $this->assign('selectData', $selectData);
        return $this->fetch();

    }

    /**
     * Notes: 编辑房间机器人管理
     * @return mixed
     */
//    public function editSuper()
    public function editRobot()
    {
        if ($this->request->isAjax()) {
            $request = $this->request->request();
            $data = [
                'roomid' => intval($request['roomid']) ? intval($request['roomid']) : 0,
                'maxcount' => intval($request['maxcount']) ? intval($request['maxcount']) : 0,
                'robotwinweighted' => intval($request['robotwinweighted']) ? intval($request['robotwinweighted']) : '',
                'robotwinmoney' => intval($request['robotwinmoney']) ? intval($request['robotwinmoney']) : 0,
                'servicetables' => intval($request['servicetables']) ? intval($request['servicetables']) : 0,

                'addwinpre' => intval($request['victory']) ? intval($request['victory']) : 0,
                'mintakescore' => intval($request['minnum']) ? intval($request['minnum']) : 0,
                'maxtakescore' => intval($request['maxnum']) ? intval($request['maxnum']) : 0,
                'minplaydraw' => intval($request['mingame']) ? intval($request['mingame']) : 0,
                'maxplaydraw' => intval($request['maxgame']) ? intval($request['maxgame']) : 0,
                'minreposetime' => intval($request['mintime']) ? intval($request['mintime']) : 0,
                'maxreposetime' => intval($request['maxtime']) ? intval($request['maxtime']) : 0,
                //                'minleavepre'   => intval($request['win']) ? intval($request['win']) : 0,
                //                'maxleavepre'   => intval($request['lost']) ? intval($request['lost']) : 0,
                'maxleavepre' => intval($request['win']) ? intval($request['win']) : 0,
                'minleavepre' => intval($request['lost']) ? intval($request['lost']) : 0,

            ];
//            var_dump($data);die;
            $res = Api::getInstance()->sendRequest($data, 'room', 'addroomrobot');

            //log记录
            GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
            return $this->apiReturn($res['code'], $res['data'], $res['message']);
        }

        $roomid = intval(input('roomid'));
        $res = Api::getInstance()->sendRequest(['id' => $roomid], 'room', 'roomrobotinfo');
        $this->assign('roomid', $res['data']['roomid']);
        $this->assign('maxcount', $res['data']['maxcount']);
        $this->assign('robotwinweighted', $res['data']['robotwinweighted']);
        $this->assign('robotwinmoney', $res['data']['robotwinmoney']);
        $this->assign('servicetables', $res['data']['servicetables']);
        $this->assign('addwinpre', $res['data']['addwinpre']);
        $this->assign('mintakescore', $res['data']['mintakescore']);
        $this->assign('maxtakescore', $res['data']['maxtakescore']);
        $this->assign('minplaydraw', $res['data']['minplaydraw']);
        $this->assign('maxplaydraw', $res['data']['maxplaydraw']);
        $this->assign('minreposetime', $res['data']['minreposetime']);
        $this->assign('maxreposetime', $res['data']['maxreposetime']);
        $this->assign('minleavepre', $res['data']['minleavepre']);
        $this->assign('maxleavepre', $res['data']['maxleavepre']);
        return $this->fetch();
    }

    /**
     * 激活房间机器人。
     */
    public function activeRoomRobot()
    {
        if ($this->request->isAjax()) {
            $request = $this->request->request();
            $data = [
                'roomid' => intval($request['roomid']),
            ];
            $socket = new QuerySocket();
            $res = $socket->DCActiveRoomRobot($data['roomid']);
            GameLog::logData(__METHOD__, $this->request->request(), 1);
            if (isset($res['iResult'])) {
                return $this->apiReturn(0, [], '激活成功');
            }
            return $this->apiReturn(3, [], '激活失败');
        }
        return $this->fetch();
    }

    /**
     * Notes: 删除房间机器人
     * @return mixed
     */
    public function deleteRobot()
    {
        $request = $this->request->request();
        $res = Api::getInstance()->sendRequest(['id' => $request['roomid']], 'room', 'delroomrobot');

        //log记录
        GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
        return $this->apiReturn($res['code'], $res['data'], $res['message']);
    }


    /**
     * Notes:机器人账号管理
     */
    public function robotuser()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $userid = input('userid') ? input('userid') : 0;

            $data = [
                'userid' => $userid,
                'page' => $page,
                'pagesize' => $limit
            ];
            $res = Api::getInstance()->sendRequest($data, 'room', 'robotuser');
            if ($res['data']) {
                foreach ($res['data'] as &$v) {
                    $str = '';
                    if (($v['servicetime'] & 1)) {
                        $str .= ' 0';
                    }
                    if (($v['servicetime'] & 2)) {
                        $str .= ' 1';
                    }
                    if (($v['servicetime'] & 4)) {
                        $str .= ' 2';
                    }
                    if (($v['servicetime'] & 8)) {
                        $str .= ' 3';
                    }
                    if (($v['servicetime'] & 16)) {
                        $str .= ' 4';
                    }
                    if (($v['servicetime'] & 32)) {
                        $str .= ' 5';
                    }
                    if (($v['servicetime'] & 64)) {
                        $str .= ' 6';
                    }
                    if (($v['servicetime'] & 128)) {
                        $str .= ' 7';
                    }
                    if (($v['servicetime'] & 256)) {
                        $str .= ' 8';
                    }
                    if (($v['servicetime'] & 512)) {
                        $str .= ' 9';
                    }
                    if (($v['servicetime'] & 1024)) {
                        $str .= ' 10';
                    }
                    if (($v['servicetime'] & 2048)) {
                        $str .= ' 11';
                    }
                    if (($v['servicetime'] & 4096)) {
                        $str .= ' 12';
                    }
                    if (($v['servicetime'] & 8192)) {
                        $str .= ' 13';
                    }
                    if (($v['servicetime'] & 16384)) {
                        $str .= ' 14';
                    }
                    if (($v['servicetime'] & 32768)) {
                        $str .= ' 15';
                    }
                    if (($v['servicetime'] & 65536)) {
                        $str .= ' 16';
                    }
                    if (($v['servicetime'] & 131072)) {
                        $str .= ' 17';
                    }
                    if (($v['servicetime'] & 262144)) {
                        $str .= ' 18';
                    }
                    if (($v['servicetime'] & 524288)) {
                        $str .= ' 19';
                    }
                    if (($v['servicetime'] & 1048576)) {
                        $str .= ' 20';
                    }
                    if (($v['servicetime'] & 2097152)) {
                        $str .= ' 21';
                    }
                    if (($v['servicetime'] & 4194304)) {
                        $str .= ' 22';
                    }
                    if (($v['servicetime'] & 8388608)) {
                        $str .= ' 23';
                    }

                    $str = trim($str);
                    $v['str'] = $str;

                    $type = '';
                    if ($v['servicegender'] & 1) {
                        $type .= ' 相互模拟';
                    }
                    if ($v['servicegender'] & 2) {
                        $type .= ' 被动陪打';
                    }
                    if ($v['servicegender'] & 4) {
                        $type .= ' 主动陪玩';
                    }
                    $v['type'] = trim($type);

                }
                unset($v);
            }

            return $this->apiReturn($res['code'], isset($res['data']) ? $res['data'] : [], $res['message'], isset($res['total']) ? $res['total'] : 0);
        }

        return $this->fetch();
    }


    /**
     * Notes:删除机器人账号
     */
    public function deleterobotuser()
    {
        $request = $this->request->request();
        $res = Api::getInstance()->sendRequest(['id' => $request['userid']], 'room', 'delrobotuser');
        //log记录
        GameLog::logData(__METHOD__, $request, (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
        return $this->apiReturn($res['code'], $res['data'], $res['message']);
    }

    /**
     * Notes:编辑机器人账号
     */
    public function editrobotuser()
    {
        $this->assign('userid', input('userid'));
        $this->assign('servicetime', input('servicetime'));
        $this->assign('servicegender', input('servicegender'));
        return $this->fetch();
    }

    /**
     * Notes:新增/修改机器人账号
     */
    public function addrobotuser()
    {
        if ($this->request->isAjax()) {
            $userid = intval(input('userid'));
            $servicetime = input('servicetime');
            $servicegender = input('servicegender');

            $res = Api::getInstance()->sendRequest([
                'userid' => $userid,
                'servicetime' => $servicetime,
                'servicegender' => $servicegender,
                'roomid' => 0,
                'mintakescore' => 0,
                'maxtakescore' => 0,
                'minplaydraw' => 0,
                'maxplaydraw' => 0,
                'minreposetime' => 0,
                'maxreposetime' => 0,
            ], 'room', 'addrotbotuser');
            GameLog::logData(__METHOD__, $this->request->request(), (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
            return $this->apiReturn($res['code'], $res['data'], $res['message']);
        }

        return $this->fetch();
    }

    //批量修改机器人账号
    public function updateallrotbot()
    {
        if ($this->request->isAjax()) {
            $servicetime = input('servicetime');
            $servicegender = input('servicegender');

            $res = Api::getInstance()->sendRequest([
                'servicetime' => $servicetime,
                'servicegender' => $servicegender,
                'roomid' => 0,
                'mintakescore' => 0,
                'maxtakescore' => 0,
                'minplaydraw' => 0,
                'maxplaydraw' => 0,
                'minreposetime' => 0,
                'maxreposetime' => 0,
            ], 'room', 'updateallrotbot');
            GameLog::logData(__METHOD__, $this->request->request(), (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
            return $this->apiReturn($res['code'], $res['data'], $res['message']);
        }

        return $this->fetch();
    }

    /**
     * Notes: 激活房间机器人
     * @return mixed
     */
    public function robotroomactive()
    {

        if ($this->request->isAjax()) {
            $pageSize = intval(input('limit')) ? intval(input('limit')) : 10;
            $limit = intval(input('page')) ? intval(input('page')) : 1;
            $request = $this->request->request();
            $socket = new QuerySocket();
            $arrResult = $socket->getRoomRobotInfo($limit, $pageSize);
            $iRecordsCount = ($arrResult['iTotalPage'] - 1) * $pageSize + ($arrResult['iCurPage'] == $arrResult['iTotalPage'] ? $arrResult['iRoomCount'] : $pageSize);
            $showData = array();
            $i = 0;
            foreach ($arrResult['RoomRobotInfoList'] as $key => $val) {
                $arrResult['RoomRobotInfoList'][$key]['UpdateTime'] = date("Y-m-d H:i:s", $val['UpdateTime']);
                $roomRobot = Api::getInstance()->sendRequest(['id' => $val['RoomID']], 'room', 'activeroom');
                if ($roomRobot) {
                    $showData[$i] = $arrResult['RoomRobotInfoList'][$key];
                    $showData[$i]['MaxCount'] = $roomRobot['data']['maxcount'];
                    $showData[$i]['RobotWinWeighted'] = $roomRobot['data']['robotwinweighted'];
                    $showData[$i]['RobotNeedWinMoney'] = $roomRobot['data']['robotneedwinmoney'];
                    $showData[$i]['RoomName'] = $roomRobot['data']['roomname'];
                    $i++;
                } else {
                    $showData[$i] = $arrResult['RoomRobotInfoList'][$key];
                    $showData[$i]['MaxCount'] = 0;
                    $showData[$i]['RobotWinWeighted'] = 0;
                    $showData[$i]['RobotNeedWinMoney'] = 0;
                    $showData[$i]['RoomName'] = $roomRobot['data']['roomname'];
                    $i++;
                }

            }
            $arrResult['RoomRobotInfoList'] = $showData;
            return $this->apiReturn(0, $arrResult['RoomRobotInfoList'], 0, $iRecordsCount);
        }
        return $this->fetch();
    }

    /**
     * Notes: 机器人数量
     * @return mixed
     */

    //玩家牌型
    public function robotnum()
    {
        $roomlist = Api::getInstance()->sendRequest(['id' => 0], 'room', 'kind');
        if ($this->request->isAjax()) {
            $roomId = intval(input('roomId')) ? intval(input('roomId')) : 0;
//          $nRoomId = intval(input('roomId')) ? intval(input('roomId')) : 0;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 15;

//          $res = $this->socket->getProfitPercent($roomId);
            $res = $this->socket->getRobotNum($roomId);

            if ($res) {
                if ($roomlist['data']) {
                    foreach ($res as &$v) {
                        foreach ($roomlist['data'] as $v2) {
                            if ($v['nRoomId'] == $v2['roomid']) {
                                $v['roomname'] = $v2['roomname'];
                            }
                        }

                        $v['lTotalRunning'] /= 1000;
                        $v['lTotalProfit'] /= 1000;
                    }
                    unset($v);
                }
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
            return $this->apiReturn(0, $result, '', $count);
        }
        //$roomList = Api::getInstance()->sendRequest(['id' => 0], 'room', 'kind');
        $kindList = Api::getInstance()->sendRequest(['id' => 0], 'room', 'kindlist');
        $this->assign('roomlist', $roomlist['data']);
        $this->assign('kindlist', $kindList['data']);
        return $this->fetch();
    }

    //时间段机器人管理（棋牌）
    public function robotnum2()
    {
        $roomlist = $this->GetRoomList();
        if ($this->request->isAjax()) {
            $roomId = intval(input('roomId')) ? intval(input('roomId')) : 0;
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 15;

            $res = $this->socket->getRobotNum($roomId);

            if ($res) {
                if ($roomlist['data']) {
                    foreach ($res as &$v) {
                        foreach ($roomlist['data'] as $v2) {
                            if ($v['nRoomId'] == $v2['roomid']) {
                                $v['roomname'] = $v2['roomname'];
                            }
                        }

                        $v['lTotalRunning'] /= 1000;
                        $v['lTotalProfit'] /= 1000;
                    }
                    unset($v);
                }
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
            return $this->apiReturn(0, $result, '', $count);
        }
        $kindList = $this->GetKindList();
        $this->assign('roomlist', $roomlist);
        $this->assign('kindlist', $kindList);
        return $this->fetch();
    }

    //获取房间库存概率信息
    public function getSocketRoomData()
    {
        $roomid = input('roomid');
//        $roomsData = $this->getSocketRoom($this->socket, $roomid);
        $roomsData = $this->getSocketNum($this->socket, $roomid);
        return $this->apiReturn(0, $roomsData, 'success');
    }

    //设置房间机器人
    public function setSocketRoomStorage()
    {
        if ($this->request->isAjax()) {
            $request = $this->request->request();
            $roomid = $request['roomid'];
            $storage = json_decode($request['data'], true);
            ksort($storage);

            $storageStr = '';
            foreach ($storage as $k => $v) {
                if (abs($k) > 2000000) {
                    return $this->apiReturn(1, [], '机器人数量不能超过绝对值200万');
                }
//                $storageStr .= $k . '#' . $v . '#';
                $storageStr .= $v . '#';
            }
            $storageStr = rtrim($storageStr, '#');
//            print_r( $storageStr);die;
//
//            $roomsData = $this->getSocketRoom($this->socket, $roomid);
            $roomsData = $this->getSocketNum($this->socket, $roomid);
//            var_dump($roomsData['nCtrlRatio']);die;
//            $this->socket->setRoom($roomid, $roomsData['nCtrlRatio'], $roomsData['nInitStorage'], $roomsData['nCurrentStorage'], $storageStr);
//            $this->socket->setNum($roomid, $roomsData['nCtrlRatio'], $roomsData['nInitStorage'], $roomsData['nCurrentStorage'], $storageStr);
            $this->socket->setNum($roomid, $storageStr);

            GameLog::logData(__METHOD__, $request, 1);
            ob_clean();
            return $this->apiReturn(0, [], '修改成功');
        }
        $roomid = intval(input('roomid')) ? intval(input('roomid')) : 0;
//        $roomsData = $this->getSocketRoom($this->socket, $roomid);
        $roomsData = $this->getSocketNum($this->socket, $roomid);
        //print_r($roomsData);die();
        $roomsData['szStorageRatio'] = trim($roomsData['szStorageRatio']);
        $array = [];
        if ($roomsData['szStorageRatio']) {
            $storage = explode('#', $roomsData['szStorageRatio']);
//            $info = array_chunk($storage, 1);
            $info = $storage;
            if ($info) {
                foreach ($info as $k => $v) {

                    if ($k < 24) {
                        $array[] = [
                            'rate' => $v,
                            'storage' => $k
                        ];
                    }
                }
            }
            $this->assign('num', count($info));
        } else {
            $this->assign('num', 0);
        }
        $this->assign('lists', $array);
        $this->assign('thisroomid', $roomid);
        return $this->fetch('setstorage');
    }

    public function getSocketNum2($socket, $roomid)
    {
//        $roomInfo = $socket->getRoomInfo($roomid);
        $roomInfo = $socket->getRoomNum($roomid);
        if ($roomInfo) {
            $roomInfo = $roomInfo[0];
        } else {
            $roomInfo = [
                'nServerID' => $roomid,
                'nCtrlRatio' => 0,
                'nInitStorage' => 0,
                'nCurrentStorage' => 0,
                'szStorageRatio' => ''
            ];
        }

//        $roomInfo['nCurrentStorage'] /= 1000;
//        $roomInfo['nInitStorage']    /= 1000;
        $roomInfo['currentwinrate'] = 0;

        $storageInfo = [];
        if (isset($roomInfo['szStorageRatio']) && trim($roomInfo['szStorageRatio']) != '') {
            $storage = explode('#', $roomInfo['szStorageRatio']);
            $info = array_chunk($storage, 2);


            foreach ($info as $k1 => $v1) {
                if (intval($roomInfo['nCurrentStorage']) < intval($v1[0])) {
                    $roomInfo['currentwinrate'] = $v1[1];
                    break;
                }
            }

            foreach ($info as $k => $v) {


                if ($k == 0) {
                    $storageInfo[$k] = [
                        'rate' => $v[1],
                        'storage' => '<' . $v[0]
                    ];
                } else {
                    $storageInfo[$k] = [
                        'rate' => $v[1],
                        'storage' => $info[$k - 1][0] . '~' . $info[$k][0]
                    ];
                }

            }
        }
        $roomInfo['storage'] = $storageInfo;
        return $roomInfo;
    }


    //ip白名单add_whitelistip
    public function whitelistip()
    {
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 15;
            $ip = input('ip');
            $postdata = [
                'ip' => $ip,
                'page' => $page,
                'pagesize' => $limit
            ];
            $res = Api::getInstance()->sendRequest($postdata, 'system', 'whitelist');
            return $this->apiReturn($res['code'], $res['data'], $res['message']);
        }
        return $this->fetch();
    }


    //添加ip白名单add_whitelistip
    public function addWhite()
    {
        if ($this->request->isAjax()) {
            $request = $this->request->request();
            $ip = input('ip');

            $postdata = [
                'ip' => $ip,
                'page' => 1,
                'pagesize' => 1
            ];
            $res = Api::getInstance()->sendRequest($postdata, 'system', 'whitelist');
            if (!empty($res['data'])) {
                return $this->apiReturn(100, '', 'ip已添加到白名单');
            }

            $res = Api::getInstance()->sendRequest(['ipaddr' => $ip], 'system', 'addIPaddr');
            if ($res['data']) {
                return json(['code' => 0, 'msg' => '添加成功']);
            } else {
                return json(['code' => 5, 'msg' => '添加失败']);
            }

        }
        return $this->fetch();
    }


    public function deleteWhite()
    {
        $request = $this->request->request();
        $id = $request['id'];

        $res = Api::getInstance()->sendRequest(['id' => $id], 'system', 'DeleteIPAddr');
        if ($res['data']) {
            return 0;
        } else {
            return 3;
        }
    }




}
