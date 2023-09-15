<?php

namespace app\merchant\controller;


use app\admin\controller\traits\getSocketRoom;
use app\common\Api;
use app\common\GameLog;
use app\model\AdvCfg;
use app\model\Chargegiftcfg;
use app\model\CommonModel;
use app\model\FirstChargeStepCfg;
use app\model\ActivityRecord;

use app\model\GameOCDB;
use app\model\GiftCardActive;
use app\model\GiftCardReceive;
use redis\Redis;
use socket\QuerySocket;

class Activity extends Main
{

    use getSocketRoom;

    private $socket = null;

    public function __construct()
    {
        parent::__construct();
        $this->socket = new QuerySocket();
    }

    public function activityinfo()
    {

        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $postdata = ["page" => $page, "pagesize" => $limit];
            $res = Api::getInstance()->sendRequest($postdata, 'System', 'ActivityInfo');
            $activetype = config('activitytype');
            $result_room = Api::getInstance()->sendRequest(['id' => 0], 'room', 'kind');
            $roomlist = $result_room['data'];
            if (!empty($res['data'])) {
                $list = $res['data'];
                foreach ($list as $k => &$v) {
                    if ($v['roomid'] > 0) {
                        $key = array_search($v['roomid'], array_column($roomlist, 'roomid'));
                        $v['roomid'] = $roomlist[$key]['roomname'];
                    } else {
                        $v['roomid'] = '不限';
                    }

                    $v['activitytype'] = $activetype[$v['activitytype']];
//                    $v['status'] = $v['status']==1?'开启':'禁用';
//                    $v['needcontinue'] = $v['needcontinue'] ==1?'是':'否';
                }
                unset($v);
                return $this->apiReturn(0, $list, 'success', $res['total']);
            }
            return $this->apiReturn(0, [], 'fail', 0);
        }
        return $this->fetch();
    }

    ///活动奖励
    public function activityaward()
    {

        $infolist = Redis::get('activityinfo');
        if (empty($infolist)) {
            $res = Api::getInstance()->sendRequest(['page' => 1, 'pagesize' => 10000], 'System', 'ActivityInfo');
            $newlist = $res['data'] ? $res['data'] : [];
            if (!empty($newlist)) {
                foreach ($newlist as $k => &$v) {
                    $infolist[] = ['activityid' => $v['activityid'], 'activityname' => $v['activityname']];
                }
                unset($v);
            }
            Redis::set('activityinfo', $infolist);
        }

        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $acivityid = input('activityid', 0);
            $postdata = ["page" => $page, "pagesize" => $limit, 'activityid' => $acivityid];
            $res = Api::getInstance()->sendRequest($postdata, 'System', 'ActivityAward');
            $activitytype = config('extratype2');
            if (!empty($res['data'])) {
                $list = $res['data'];
                foreach ($list as $k => &$v) {
                    if (!empty($infolist)) {
                        $key = array_search($v['activityid'], array_column($infolist, 'activityid'));
                        $v['activityname'] = $infolist[$key]['activityname'];
                    }
                    $v['awardid'] = $activitytype[$v['awardid']];
                }
                unset($v);
                return $this->apiReturn(0, $list, 'success', $res['total']);
            }
            return $this->apiReturn(0, [], 'fail', 0);
        }


        $this->assign('infolist', $infolist);
        return $this->fetch();
    }


    public function activitystage()
    {

        $infolist = Redis::get('activityinfo');
        if (empty($infolist)) {
            $res = Api::getInstance()->sendRequest(['page' => 1, 'pagesize' => 10000], 'System', 'ActivityInfo');
            $newlist = $res['data'] ? $res['data'] : [];
            if (!empty($newlist)) {
                foreach ($newlist as $k => &$v) {
                    $infolist[] = ['activityid' => $v['activityid'], 'activityname' => $v['activityname']];
                }
                unset($v);
            }
            Redis::set('activityinfo', $infolist);
        }


        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $acivityid = input('activityid', 0);
            $postdata = ["page" => $page, "pagesize" => $limit, 'activityid' => $acivityid];
            $res = Api::getInstance()->sendRequest($postdata, 'System', 'ActivityStage');
            if (!empty($res['data'])) {
                $list = $res['data'];
                foreach ($list as $k => &$v) {
                    if (!empty($infolist)) {
                        $key = array_search($v['activityid'], array_column($infolist, 'activityid'));
                        $v['activityname'] = $infolist[$key]['activityname'];
                    }
                }
                unset($v);
                return $this->apiReturn(0, $list, 'success', $res['total']);
            }
            return $this->apiReturn(0, [], 'fail', 0);
        }
        $this->assign('infolist', $infolist);
        return $this->fetch();
    }

    public function getstagejson()
    {

        if ($this->request->isAjax()) {
            $id = input('id', 0);
            if ($id > 0) {
                $postdata = ["page" => 1, "pagesize" => 15, 'activityid' => $id];
                $res = Api::getInstance()->sendRequest($postdata, 'System', 'ActivityStage');
                if (!empty($res['data'])) {
                    $list = $res['data'];
                    $newlist = [];
                    foreach ($list as $k => $v) {
                        $temp = ['name' => $v['stageid'], 'id' => $v['stageid']];
                        array_push($newlist, $temp);
                    }
                    return $this->apiReturn(0, $newlist, 'success');
                }
            }
            return $this->apiReturn(100, [], 'fail');
        }
    }


    public function addactivitystage()
    {
        $id = input('id', 0);
        $info = ['id' => 0, 'activityid' => -1, 'stageid' => -1, 'stagelimit' => ''];
        $infolist = Redis::get('activityinfo');
        if (empty($infolist)) {
            $res = Api::getInstance()->sendRequest(['page' => 1, 'pagesize' => 10000], 'System', 'ActivityInfo');
            $newlist = $res['data'] ? $res['data'] : [];
            foreach ($newlist as $k => &$v) {
                $infolist[] = ['activityid' => $v['activityid'], 'activityname' => $v['activityname']];
            }
            unset($v);
            Redis::set('activityinfo', $infolist);
        }

        if ($id > 0) {
            $res = Api::getInstance()->sendRequest(['id' => $id], 'System', 'getactivitystage');
            $info = $res['data'];
        }

        if ($this->request->isAjax()) {
            $activityid = input('activityid', -1);
            $stageid = input('stageid', -1);
            $stagelimit = input('stagelimit', 1);

            if ($activityid == -1 || $stageid == -1) {
                return $this->apiReturn(100, [], '请完整填写表单');
            }

            $postdata = [];
            if ($id > 0) {
                $postdata = $info;
                $postdata['stageid'] = $stageid;
                $postdata['stagelimit'] = $stagelimit;
            } else {
                $postdata = [
                    'id' => $id,
                    'activityid' => $activityid,
                    'stageid' => $stageid,
                    'stagelimit' => $stagelimit
                ];
            }
            $res = Api::getInstance()->sendRequest($postdata, 'System', 'addactivitystage');
            $result = $res['data'];
            if ($result) {
                return $this->apiReturn(0, [], '信息已保存');
            } else {
                return $this->apiReturn(100, [], '信息提交失败');
            }
        }

        $this->assign('infolist', $infolist);
        $this->assign('info', $info);
        return $this->fetch();
    }


    public function addactivityaward()
    {
        $id = input('id', 0);
        $info = ['id' => 0, 'stageid' => -1, 'sort' => 1, 'awardid' => -1, 'activityid' => -1, 'awardnum' => '', 'needvip' => 0];

        if ($id > 0) {
            $res = Api::getInstance()->sendRequest(['id' => $id], 'System', 'getactivityaward');
            $info = $res['data'];
        }

        if ($this->request->isAjax()) {

            $activityid = input('activityid', -1);
            $stageid = input('stageid', -1);
            $awardid = input('awardid', -1);
            $awardnum = input('awardnum', 0);
            $sort = input('sort', 0);
            $needvip = input('needvip', 0);

            if ($activityid == -1 || $stageid == -1 || $awardid == -1 || $awardnum == 0 || !is_numeric($awardnum)) {
                return $this->apiReturn(100, [], '请完整填写表单');
            }

            $postdata = [];
            if ($id > 0) {
                $postdata = $info;
                $postdata['activityid'] = $activityid;
                $postdata['stageid'] = $stageid;
                $postdata['awardid'] = $awardid;
                $postdata['awardnum'] = $awardnum;
                $postdata['sort'] = $sort;
                $postdata['needvip'] = $needvip;
            } else {
                $postdata = [
                    'id' => $id,
                    'activityid' => $activityid,
                    'stageid' => $stageid,
                    'awardid' => $awardid,
                    'awardnum' => $awardnum,
                    'needvip' => $needvip,
                    'sort' => $sort
                ];
            }

            $res = Api::getInstance()->sendRequest($postdata, 'System', 'addactivityaward');
            $result = $res['data'];
            if ($result) {
                return $this->apiReturn(0, [], '信息已保存');
            } else {
                return $this->apiReturn(100, [], '信息提交失败,请检查是否vip奖励设置一样');
            }
        }
        $infolist = Redis::get('activityinfo');
        if (empty($infolist)) {
            $res = Api::getInstance()->sendRequest(['page' => 1, 'pagesize' => 10000], 'System', 'ActivityInfo');
            $newlist = $res['data'] ? $res['data'] : [];
            if (!empty($newlist)) {
                foreach ($newlist as $k => &$v) {
                    $infolist[] = ['activityid' => $v['activityid'], 'activityname' => $v['activityname']];
                }
                unset($v);
            }
            Redis::set('activityinfo', $infolist);
        }

        $actid = $info['activityid'] ? $info['activityid'] : 0;
        $ret = Api::getInstance()->sendRequest(
            [
                'page' => 1,
                'pagesize' => 10000,
                'activityid' => $actid
            ],
            'System', 'ActivityStage');
        $stageinfo = $ret['data'];

        $extratype = config('extratype2');
        $this->assign('extratype', $extratype);

        $this->assign('stagelist', $stageinfo);
        $this->assign('infolist', $infolist);
        $this->assign('info', $info);
        return $this->fetch();
    }


    public function addactivityinfo()
    {

        $activityid = input('id', 0);
        $roomlist = Api::getInstance()->sendRequest(['id' => 0], 'room', 'kind');
        $activityinfo = [
            'activityid' => 0,
            'activityname' => '',
            'activitytype' => 0,
            'begintime' => '',
            'endtime' => '',
            'roomid' => 0,
            'vipawardlevel' => 0,
            'activitydesc' => ''
        ];
        if ($activityid > 0) {
            $res = Api::getInstance()->sendRequest(['id' => $activityid], 'System', 'getactivityinfo');
            $activityinfo = $res['data'];
            $activityinfo['begintime'] = str_replace('/', '-', $activityinfo['begintime']);
            $activityinfo['endtime'] = str_replace('/', '-', $activityinfo['endtime']);

        }
        if ($this->request->isAjax()) {
            $activename = input('activityname', '');
            $activitytype = input('activitytype', 0);
            $begintime = input('begintime', '');
            $endtime = input('endtime', '');
            $roomid = input('roomid', 0);
            $vipawardlevel = input('vipawardlevel', 0);
            $activitydesc = input('activitydesc', '');
            if (trim($activename) == '' || $begintime == '' || $endtime == '') {
                return $this->apiReturn(100, '', '请确认表单已填写完成');
            }
            if ($activityid == 0) {
                $postdata = [
                    'activityid' => 0,
                    'activityname' => $activename,
                    'activitytype' => $activitytype,
                    'begintime' => $begintime,
                    'endtime' => $endtime,
                    'roomid' => $roomid,
                    'status' => 0,
                    'vipawardlevel' => $vipawardlevel,
                    'activitydesc' => $activitydesc,
                    'NeedContinue' => 0
                ];
            } else {


                $activityinfo['activityname'] = $activename;
                $activityinfo['activitytype'] = $activitytype;
                $activityinfo['begintime'] = $begintime;
                $activityinfo['endtime'] = $endtime;
                $activityinfo['roomid'] = $roomid;
                $activityinfo['vipawardlevel'] = $vipawardlevel;
                $activityinfo['activitydesc'] = $activitydesc;
                $postdata = $activityinfo;
            }

            $res = Api::getInstance()->sendRequest($postdata, 'System', 'addactivityinfo');
            $result = $res['data'];
            if ($result) {
                Redis::rm('activityinfo');
                return $this->apiReturn(0, [], '信息已保存');
            } else {
                return $this->apiReturn(100, [], '信息提交失败');
            }


        }
        $this->assign('info', $activityinfo);
        $this->assign('id', $activityid);
        $this->assign('roomlist', $roomlist['data']);
        $this->assign('activitytype', config('activitytype'));
        return $this->fetch();
    }


    public function delactivity()
    {
        $activityid = input('id', 0);
        if ($activityid > 0) {
            $res = Api::getInstance()->sendRequest(['id' => $activityid], 'System', 'delactivityinfo');
            if ($res['data']) {
                return $this->apiReturn(0, [], '信息已删除');
            }
        }
        return $this->apiReturn(100, [], '记录删除失败');
    }


    public function delactivitystage()
    {
        $activityid = input('id', 0);
        if ($activityid > 0) {
            $res = Api::getInstance()->sendRequest(['id' => $activityid], 'System', 'delactivitystage');
            if ($res['data']) {
                return $this->apiReturn(0, [], '记录已删除');
            }
        }
        return $this->apiReturn(100, [], '记录删除失败');

    }


    public function delactivityaward()
    {
        $activityid = input('id', 0);
        if ($activityid > 0) {
            $res = Api::getInstance()->sendRequest(['id' => $activityid], 'System', 'delactivityaward');
            if ($res['data']) {
                return $this->apiReturn(0, [], '记录已删除');
            }
        }
        return $this->apiReturn(100, [], '记录删除失败');
    }


    public function setinfostatus()
    {
        $id = input('id', 0);
        $status = input('status', '');

        if ($id == 0 || $status == '') {
            return $this->apiReturn(100, [], '参数有误');
        }

        $res = Api::getInstance()->sendRequest(['id' => $id], 'System', 'getactivityinfo');
        $activityinfo = $res['data'];
        if (!empty($activityinfo)) {
            $activityinfo['status'] = $status;
            $res = Api::getInstance()->sendRequest($activityinfo, 'System', 'addactivityinfo');
            $result = $res['data'];
            if ($result) {
                return $this->apiReturn(0, [], '状态更新成功');
            }
        }
        return $this->apiReturn(100, [], '状态更新失败');
    }


    public function setcontinue()
    {
        $id = input('id', 0);
        $status = input('status', '');

        if ($id == 0 || $status == '') {
            return $this->apiReturn(100, [], '参数有误');
        }

        $res = Api::getInstance()->sendRequest(['id' => $id], 'System', 'getactivityinfo');
        $activityinfo = $res['data'];
        if (!empty($activityinfo)) {
            $activityinfo['needcontinue'] = $status;
            $res = Api::getInstance()->sendRequest($activityinfo, 'System', 'addactivityinfo');
            $result = $res['data'];
            if ($result) {
                return $this->apiReturn(0, [], '更新成功');
            }
        }
        return $this->apiReturn(100, [], '更新失败');
    }

    /**
     * Notes: 礼券黑名单（单独菜单）
     * @return mixed
     */
    public function couponblacklist()
    {
        $strFields = "RoleID";//
        $tableName = " [CD_UserDB].[dbo].[T_RoleExpand] ";
        $where = " where CouponExchangeDisable=1";//
        $limits = "";
        $orderBy = "";
        if ($this->request->isAjax()) {
            //前台传参
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $roleId = intval(input('roleid')) ? intval(input('roleid')) : 0;

            //拼装sql
            $limits = " top " . ($page * $limit);
            if ($roleId > 0) {
                $where .= " and  RoleID =" . $roleId;
            }
            $limits = " top " . ($page * $limit);
            $comm = new CommonModel;
            $list = $comm->getPageList($tableName, $strFields, $where, $limits, $page, $limit, $orderBy);
            $count = $list['count'];
            $result = $list['list'];

            $res['data']['list'] = $result;
            $res['code'] = 0;
            $res['message'] = '';
            $res['total'] = $count;
            return $this->apiReturn($res['code'], isset($res['data']['list']) ? $res['data']['list'] : [], $res['message'] = '', $res['total']);
        }
        //$this->assign('changeType', $changeType);
        return $this->fetch();
    }

    /**
     * Notes: 新增礼券黑名单ID
     * @return mixed
     */
    public function addcouponblacklist()
    {
        $strFields = "roleid,CouponExchangeDisable";//
        $tableName = " [CD_UserDB].[dbo].[T_RoleExpand] ";
        if ($this->request->isAjax()) {
            $RoleID = input('RoleID');

            if ($RoleID == '') {
                return $this->apiReturn(100, [], '请填写黑名单ID');
            }
            $value = $RoleID . ",1";
            $comm = new CommonModel;
            $result = $comm->addsql($tableName, $strFields, $value);
            if ($result) {
                return $this->apiReturn(0, [], '信息已保存');
            } else {
                return $this->apiReturn(100, [], '信息提交失败');
            }
        }
        return $this->fetch();
    }

    /**
     * Notes: 删除礼券黑名单ID
     * @return mixed
     */
    public function delcouponblacklist()
    {
        $RoleID = input('RoleID', 0);
        $tableName = " [CD_UserDB].[dbo].[T_RoleExpand] ";
        $where = " where RoleID=" . $RoleID;//
        if ($RoleID > 0) {
            $comm = new CommonModel;
            $result = $comm->delsql($tableName, $where);
            if ($result) {
                return $this->apiReturn(0, [], '删除成功');
            }
        }
        return $this->apiReturn(100, [], '删除失败');
    }

    /**
     * Notes: 核对礼券黑名单ID
     * @return mixed
     */
    public function checklist()
    {
        $RoleID = $_GET['RoleID'];
        $res = Api::getInstance()->getCouponBlackList($RoleID);

        return $res;
    }
//    public function setvip(){
//        $id = input('id',0);
//        $status = input('status','');
//
//        if($id==0 || $status==''){
//            return $this->apiReturn(100,[],'参数有误');
//        }
//
//        $res = Api::getInstance()->sendRequest(['id'=>$id], 'System', 'getactivityaward');
//        $activityinfo = $res['data'];
//
//        if(!empty($activityinfo)){
//            $activityinfo['needvip'] =intval($status);
//            $res = Api::getInstance()->sendRequest($activityinfo, 'System', 'addactivityaward');
//            $result = $res['data'];
//            if($result){
//                return $this->apiReturn(0,[],'更新成功');
//            }
//        }
//        return $this->apiReturn(100,[],'已有该条件的记录了,无法设置');
//    }

    /**
     * 实物活动列表
     * @return type
     */
    public function ActivityConfig()
    {
        if ($this->request->isAjax()) {

            $start = input('start', '');
            $end = input('end', '');

            $activity_name = input('activity_name', '', 'trim');
            $activity_type = input('activity_type', '', 'strval');
            $status = input('status', '', 'strval');

            $filter = ' where 1=1 ';
            // todo 调整时间过滤
            if (!empty($start)) {
                $start = date('Y-m-d 00:00:00', strtotime($start));
                $filter .= ' and PayTime >= \'' . $start . '\'';
            }
            if (!empty($end)) {
                $end = date('Y-m-d 00:00:00', strtotime('+1 day', strtotime($end)));
                $filter .= ' and PayTime < \'' . $end . ' \'';
            }

            if (!empty($activity_name)) {
                $filter .= ' and ActivityName like \'%' . $activity_name . '%\'';
            }
            if (!empty($activity_type) || $activity_type === '0') {
                $filter .= ' and ActivityType = ' . $activity_type;
            }
            if (!empty($status) || $status === '0') {
                $filter .= ' and Status = ' . $status;
            }
            $DB = new \app\model\MasterDB();
            $count = $DB->getTotalFromSqlSrv($DB::OPERATING_ACTIVITY_INFO, $filter);
            if (!$count) {
                return $this->toDataGrid($count);
            }
            $list = $DB->getDataListFromSqlSrv($DB::OPERATING_ACTIVITY_INFO, $this->getPageIndex(), $this->getPageLimit(), $filter, '', ' ActivityID DESC ');
            return $this->toDataGrid($count, $list);
        }
        return $this->fetch();
    }

    /**
     * 改变活动状态
     */
    public function ChangeActivityStatus()
    {
        try {
            if ($this->request->isPost()) {
                $id = input('id', 0, 'intval');
                $status = input('status', 0, 'intval');
                if (empty($id)) {
                    throw new \Exception('id不能为空');
                }
                if ($status === 0) {
                    $update_status = 0;
                } else {
                    $update_status = 1;
                }
                $DB = new \app\model\MasterDB();
                $res = $DB->updateTable($DB::OPERATING_ACTIVITY_INFO, ['Status' => $status], ['ActivityID' => $id]);
                if (!$res) {
                    throw new \Exception('记录更新失败');
                }
            } else {
                throw new \Exception('请求方式错误');
            }
            return $this->successData([], '操作成功');
        } catch (\Exception $ex) {
            return $this->failData($ex->getMessage());
        }
    }

    /**
     * 活动编辑界面
     */
    public function ActivityConfigEdit()
    {
        if ($this->request->isPost()) {
            try {
                $data = [];
                $ActivityID = input('ActivityID', 0);
                $data['ActivityName'] = input('ActivityName', '', 'trim');
                $data['BeginTime'] = input('BeginTime', '');
                $data['EndTime'] = input('EndTime', '');
                $data['ShowTime'] = input('ShowTime', '');
                $data['ActivityDesc'] = input('ActivityDesc', '', 'trim');
                $data['Status'] = input('Status', 0, 'intval');

                $data['MinRecharge'] = input('MinRecharge', 0);
                $data['MinWater'] = input('MinWater', 0);

                if (empty($data['ActivityName'])) {
                    throw new \Exception('活动名称不能为空');
                }
                if (empty($data['BeginTime'])) {
                    throw new \Exception('活动开始时间不能为空');
                }
                if (empty($data['EndTime'])) {
                    throw new \Exception('活动结束不能为空');
                }
                if (empty($data['ShowTime'])) {
                    throw new \Exception('活动领奖截止不能为空');
                }
                if (strtotime($data['EndTime']) <= strtotime($data['BeginTime'])) {
                    throw new \Exception('活动结束时间不能早于活动开始时间');
                }
                if (strtotime($data['ShowTime']) <= strtotime($data['EndTime'])) {
                    throw new \Exception('活动领奖截止不能早于活动结束时间');
                }

                $DB = new \app\model\MasterDB();
                if (empty($ActivityID)) {
                    $msg = '数据新增失败';
                    $ret = $DB->getTableObject($DB::OPERATING_ACTIVITY_INFO)->insert($data);
                } else {
                    $msg = '数据更新失败';
                    $ret = $DB->updateTable($DB::OPERATING_ACTIVITY_INFO, $data, ['ActivityID' => $ActivityID]);
                }
                if (!$ret) {
                    throw new \Exception($msg);
                }
                return $this->successData();
            } catch (\Exception $ex) {
                return $this->failData($ex->getMessage());
            }

        } else {
            $id = input('ActivityID', 0);
            if ($id > 0) {
                $DB = new \app\model\MasterDB();
                $data = $DB->getTableRow($DB::OPERATING_ACTIVITY_INFO, ['ActivityID' => $id]);
                $data['BeginTime'] = date('Y-m-d H:i:s', strtotime($data['BeginTime']));
                $data['EndTime'] = date('Y-m-d H:i:s', strtotime($data['EndTime']));
                $data['ShowTime'] = date('Y-m-d H:i:s', strtotime($data['ShowTime']));
            } else {
                $data = [
                    'ActivityID' => '',
                    'ActivityType' => 0,
                    'ActivityName' => '',
                    'BeginTime' => '',
                    'EndTime' => '',
                    'ShowTime' => '',
                    'MinRecharge' => null,
                    'MinWater' => null,
                    'Status' => 0,
                    'ActivityDesc' => '',
                ];
            }
            $this->assign('data', $data);
            return $this->fetch();
        }
    }

    /**
     * 活动领奖列表
     */
    public function ActivityReward()
    {
        if ($this->request->isAjax()) {

            $start = input('start', '');
            $end = input('end', '');

            $phone = input('phone', '', 'strval');
            $name = input('name', '', 'strval');
            $status = input('status', '', 'strval');

            $filter = ' where 1=1 ';
            if (!empty($start)) {
                $start = date('Y-m-d 00:00:00', strtotime($start));
                $filter .= ' and AddTime >= \'' . $start . '\'';
            }
            if (!empty($end)) {
                $end = date('Y-m-d 00:00:00', strtotime('+1 day', strtotime($end)));
                $filter .= ' and AddTime < \'' . $end . ' \'';
            }

            if (!empty($phone)) {
                $filter .= ' and Phone like \'%' . $phone . '%\'';
            }
            if (!empty($name)) {
                $filter .= ' and Name like \'%' . $name . '%\'';
            }
            if (!empty($status) || $status === '0') {
                $filter .= ' and Status = ' . $status;
            }
            $DB = new \app\model\UserDB();
            $count = $DB->getTotalFromSqlSrv($DB::USER_ACTIVITY_REWARD, $filter);
            if (!$count) {
                return $this->toDataGrid($count);
            }
            $list = $DB->getDataListFromSqlSrv($DB::USER_ACTIVITY_REWARD, $this->getPageIndex(), $this->getPageLimit(), $filter, '', ' ActivityID DESC ');
            return $this->toDataGrid($count, $list);
        }
        return $this->fetch();
    }

    /**
     * 奖励发放
     */
    public function RewardGrant()
    {
        $DB = new \app\model\UserDB();
        $where = [];
        $where['ActivityID'] = input('ActivityID', 0, 'intval');
        $where['RoleId'] = input('RoleId', 0, 'intval');
        $where['Rank'] = input('Rank', 0, 'intval');
        if (empty($where['ActivityID']) || empty($where['RoleId']) || empty($where['Rank'])) {
            return $this->failData("缺少请求参数");
        }
        if ($this->request->isPost()) {
            try {
                $update_data = [
                    'GrantTime' => date("Y-m-d H:i:s", time()),
                ];
                $update_data['GrantRemark'] = input("GrantRemark");
                $update_data['Status'] = $DB::ACTIVITY_REWARD_STATUS_GRANTED;

                if (empty($update_data['GrantRemark'])) {
                    throw new \Exception("发放备注不能为空");
                }
                $res = $DB->updateTable($DB::USER_ACTIVITY_REWARD, $update_data, $where);
                if (!$res) {
                    throw new \Exception("发放失败");
                }
                return $this->successData(null, "发放成功");
            } catch (\Exception $ex) {
                return $this->failData($ex->getMessage());
            }
        } else {
            $DB = new \app\model\UserDB();
            $record = $DB->getTableRow($DB::USER_ACTIVITY_REWARD, $where);
            if (empty($record)) {
                return $this->failData("数据获取失败");
            }
            $this->assign('data', $record);
            return $this->fetch();
        }
    }

    /**
     * 拒绝发送奖励
     */
    public function RejectGrant()
    {
        $DB = new \app\model\UserDB();
        $where = [];
        $where['ActivityID'] = input('ActivityID', 0, 'intval');
        $where['RoleId'] = input('RoleId', 0, 'intval');
        $where['Rank'] = input('Rank', 0, 'intval');
        if (empty($where['ActivityID']) || empty($where['RoleId']) || empty($where['Rank'])) {
            return $this->failData("缺少请求参数");
        }
        if ($this->request->isPost()) {
            try {
                $update_data['Status'] = $DB::ACTIVITY_REWARD_STATUS_REJECT_GRANT;

                $res = $DB->updateTable($DB::USER_ACTIVITY_REWARD, $update_data, $where);
                if (!$res) {
                    throw new \Exception("操作失败");
                }
                return $this->successData(null, "操作成功");
            } catch (\Exception $ex) {
                return $this->failData($ex->getMessage());
            }
        } else {
            return $this->failData("请求方式错误");
        }
    }

    /**
     * 活动领奖详情
     */
    public function ActivityRewardDetail()
    {
        try {
            $where = [];
            $where['ActivityID'] = input('ActivityID', 0, 'intval');
            $where['RoleId'] = input('RoleId', 0, 'intval');
            $where['Rank'] = input('Rank', 0, 'intval');

            if (empty($where['ActivityID']) || empty($where['RoleId']) || empty($where['Rank'])) {
                throw new \Exception("缺少请求参数");
            }
            $DB = new \app\model\UserDB();
            $record = $DB->getTableRow($DB::USER_ACTIVITY_REWARD, $where);
            if (empty($record)) {
                throw new \Exception("获取详情失败");
            }
            $this->assign("data", $record);
            return $this->fetch();
        } catch (\Exception $ex) {
            return $this->failData($ex->getMessage());
        }
    }

    /**
     * 活动排行榜
     */
    public function ActivityRanking()
    {
        $MasterDB = new \app\model\MasterDB();
        if ($this->request->isAjax()) {
            $ActivityID = input('ActivityID', '');
            if (empty($ActivityID)) {
                $this->toDataGrid(0);
            }
            $activity = $MasterDB->getTableRow($MasterDB::OPERATING_ACTIVITY_INFO, ['ActivityID' => $ActivityID]);
            $DB = new \app\model\UserDB();
            $filter = ' where TotalRecharge >= ' . $activity['MinRecharge']
                . ' and TotalWater >= ' . $activity['MinWater'];

            $count = $DB->getTotalFromSqlSrv($DB::USER_ACTIVITY_PROGRESS, $filter);
            if (!$count) {
                return $this->toDataGrid($count);
            }
            $orderBy = ' TotalWater DESC';
            $list = $DB->getDataListFromSqlSrv($DB::USER_ACTIVITY_PROGRESS, $this->getPageIndex(), $this->getPageLimit(), $filter, '', $orderBy);
            return $this->toDataGrid($count, $list);
        }
        $activitys = $MasterDB->getDataListFromSqlSrv($MasterDB::OPERATING_ACTIVITY_INFO, -1, -1, '', 'ActivityID, ActivityName', 'ActivityID DESC');
        $this->assign('activitys', $activitys);

        $time = date('Y-m-d H:i:s', time());
        // 获取最近一个将要结束的活动的id
        $act_filter = ' where Status = 1 '
            . ' and BeginTime <= \'' . $time . '\' and EndTime > \'' . $time . '\'';
        $act = $MasterDB->getDataListFromSqlSrv($MasterDB::OPERATING_ACTIVITY_INFO, 0, 1, $act_filter, 'ActivityID', 'EndTime DESC');
        if (!empty($act)) {
            $active_id = $act[0]['ActivityID'];
        } else {
            $active_id = 1;
        }
        $this->assign('active_id', $active_id);
        return $this->fetch();
    }


    public function stagegftlist()
    {

        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $start = input('start', '');
            $end = input('end', '');
            $chargetype = intval(input('chargetype')) ? intval(input('chargetype')) : 0;
            $status = intval(input('status'));

            $chargegift = new Chargegiftcfg();
            $where = [];
            if ($chargetype > 0) {
                $where['chargetype'] = $chargetype;
            }
            if (!empty($start)) {
                $start = $start . ' 00:00:00';
                $where['BeginTime'] = ['egt', $start];
            }

            if (!empty($end)) {
                $end = $end . ' 23:59:59';
                $where['EndTime'] = ['elt', $end];
            }

            if ($status > -1) {
                $where['Status'] = $status;
            }

            $list = $chargegift->getList($where, $page, $limit, '*', 'id desc');
            $chargetype = [lang('首充礼包'), lang('充值返利'), lang('商店充值'), lang('客服充值'), lang('周卡'), lang('月卡')];
            foreach ($list as $k => &$v) {
                $v['ChargeTypeName'] = $chargetype[intval($v['ChargeType']) - 1];
                $v['BeginTime'] = date('Y-m-d H:i:s', strtotime($v['BeginTime']));
                $v['EndTime'] = date('Y-m-d H:i:s', strtotime($v['EndTime']));
                $v['OriginalVirtualMoney'] = FormatMoneyint($v['OriginalVirtualMoney']);
                $v['GitfVirtualMoney'] = FormatMoneyint($v['GitfVirtualMoney']);
                $v['parentAward'] = FormatMoneyint($v['parentAward']);
                $v['WageRequiredMul'] = sprintf('%.2f', $v['WageRequiredMul'] / 10);
            }
            unset($v);
            $count = $chargegift->getCount($where);
            return $this->apiReturn(0, $list, '', $count);

        }
        return $this->fetch();
    }


    public function setstagegftstatus()
    {
        try {
            if ($this->request->isAjax()) {
                $id = input('id', 0, 'intval');
                $status = input('status', 0, 'intval');
                if (empty($id)) {
                    throw new \Exception(lang('id不能为空'));
                }
                if ($status === 0) {
                    $update_status = 0;
                } else {
                    $update_status = 1;
                }
                $DB = new \app\model\MasterDB();
                $res = $DB->updateTable($DB::CHARGE_GIFT_CFG, ['Status' => $status], ['id' => $id]);
                if (!$res) {
                    throw new \Exception(lang('记录更新失败'));
                }
            } else {
                throw new \Exception(lang('请求方式错误'));
            }
            return $this->successData([], '操作成功');
        } catch (\Exception $ex) {
            return $this->failData($ex->getMessage());
        }
    }


    public function addstagegft()
    {
        $id = input('Id', 0, 'intval');
        $chargegift = new Chargegiftcfg();
        if ($this->request->isAjax()) {

            $ChargeMoney = input('ChargeMoney', 0, 'intval');
            $ChargeType = input('ChargeType', 0, 'intval');
            $BeginTime = input('BeginTime', '');
            $EndTime = input('EndTime', '');
            $OriginalVirtualMoney = input('OriginalVirtualMoney', 0, 'intval');
            $GitfVirtualMoney = input('GitfVirtualMoney', 0, 'intval');
            $WageRequiredMul = input('WageRequiredMul', 0, 'floatval');
            $parentAward = input('parentAward', 0, 'intval');
            if (empty($BeginTime))
                return $this->apiReturn(100, '', '请输入活动开始时间');

            if (empty($EndTime))
                return $this->apiReturn(100, '', '请输入活动结束时间');


            if ($OriginalVirtualMoney == 0)
                return $this->apiReturn(100, '', '请输入虚拟币金额');

            if ($GitfVirtualMoney == 0)
                return $this->apiReturn(100, '', '请输入虚拟币奖励金额');

            if ($ChargeMoney == 0)
                return $this->apiReturn(100, '', '请输入实际充值金额');


            $post_data = [
                'ChargeMoney' => $ChargeMoney,
                'ChargeType' => $ChargeType,
                'BeginTime' => $BeginTime,
                'EndTime' => $EndTime,
                'OriginalVirtualMoney' => $OriginalVirtualMoney * 1000,
                'GitfVirtualMoney' => $GitfVirtualMoney * 1000,
                'parentAward' => $parentAward * 1000,
                'WageRequiredMul' => $WageRequiredMul * 10
            ];
            $ret = 0;
            if ($id > 0) {
                $ret = $chargegift->updateById($id, $post_data);
            } else {
                $ret = $chargegift->add($post_data);
            }
            if ($ret) {
                return $this->apiReturn(0, '', '设置成功');
            } else {
                return $this->apiReturn(100, '', '设置失败');
            }
        }

        $charge = ['Id' => 0, 'ChargeType' => 1, 'ChargeMoney' => '', 'OriginalVirtualMoney' => '', 'GitfVirtualMoney' => '', 'parentAward' => 0, 'BeginTime' => date('Y-m-d H:i:s', time()), 'EndTime' => date("Y-m-d H:i:s", strtotime("+1 month")), 'WageRequiredMul' => '0'];
        if ($id > 0) {
            $charge = $chargegift->getRowById($id);
            $charge['WageRequiredMul'] = sprintf('%.2f', $charge['WageRequiredMul'] / 10);
            $charge['OriginalVirtualMoney'] = FormatMoneyint($charge['OriginalVirtualMoney']);
            $charge['GitfVirtualMoney'] = FormatMoneyint($charge['GitfVirtualMoney']);
            $charge['parentAward'] = FormatMoneyint($charge['parentAward']);
        }
        $charge['BeginTime'] = date('Y-m-d H:i:s', strtotime($charge['BeginTime']));
        $charge['EndTime'] = date('Y-m-d H:i:s', strtotime($charge['EndTime']));

        $this->assign('info', $charge);
        return $this->fetch();
    }


    public function delstagegft()
    {
        $id = input('Id', 0, 'intval');
        $chargegift = new Chargegiftcfg();
        if ($id > 0) {
            $chargegift->delRow(['id' => $id]);
            return $this->apiReturn(0, '', '删除成功');
        } else {
            return $this->apiReturn(100, '', '删除失败');
        }
    }


    public function advcfglist()
    {
        $advcfg = new AdvCfg();
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $status = input('status');
            $stype = input('type');
            $where = [];
            if ($status > -1)
                $where['Nullity'] = $status;
            if($stype>-1){
                $where['type'] = $stype;
            }
            $list = $advcfg->getList($where, $page, $limit, '*', 'id desc');
            $count = $advcfg->getCount($where);
            return $this->apiReturn(0, $list, '', $count);
        }
        return $this->fetch();

    }


    public function setadvcfglist()
    {
        try {
            if ($this->request->isAjax()) {
                $id = input('id', 0, 'intval');
                $status = input('status', 0, 'intval');
                if (empty($id)) {
                    throw new \Exception('id不能为空');
                }
                if ($status === 0) {
                    $update_status = 0;
                } else {
                    $update_status = 1;
                }
                $status = !$status;
                $adv = new AdvCfg();
                $res = $adv->updateById($id, ['Nullity' => $status]);
                if (!$res) {
                    throw new \Exception('记录更新失败');
                }
            } else {
                throw new \Exception('请求方式错误');
            }
            return $this->successData([], '操作成功');
        } catch (\Exception $ex) {
            return $this->failData($ex->getMessage());
        }

    }


    public function deladvcfg()
    {
        $id = input('id', 0, 'intval');
        $advcfg = new AdvCfg();
        if ($id > 0) {
            $advcfg->delRow(['id' => $id]);
            return $this->apiReturn(0, '', '删除成功');
        } else {
            return $this->apiReturn(100, '', '删除失败');
        }
    }


    public function uploadimg()
    {
        $file = $this->request->file('file');
        $info = $file->validate(['size' => 2097152, 'ext' => 'png,jpg,jpeg'])->move(ROOT_PATH . 'public' . DS . 'images' . DS . 'topshow');
        if ($info) {
            $savepath = $info->getSaveName();
            //验证文件手机号是否正确
            $filepath = ROOT_PATH . 'public' . DS . 'images' . DS . 'topshow' . DS . $savepath;
            if (!file_exists($filepath)) {
                return $this->apiReturn(2, [], '上传失败');
            }
            $headurl = config('active_img_url') . '/topshow/' . $savepath;
            return $this->apiReturn(0, ['path' => $headurl], '上传成功');
        } else {
            return $this->apiReturn(1, [], $file->getError());
        }
    }


    public function addadvcfg()
    {
        $id = intval(input('id', 0));
        $adv = new AdvCfg();

        if ($this->request->isAjax()) {
            $AdvTitle = input('AdvTitle', '');
            $SortId = intval(input('SortId', ''));
            $AdvUrl = input('AdvUrl', '');
            $AdvShareUrl = input('AdvShareUrl');
            $type = input('stype');
            if ($AdvTitle == '' || $AdvUrl == '')
                return $this->apiReturn(100, '', '请填写完整表单内容并上传图片');

            $save_data = [
                'AdvTitle' => $AdvTitle,
                'SortId' => $SortId,
                'AdvUrl' => $AdvUrl,
                'type' =>$type,
                'AdvShareUrl' =>$AdvShareUrl
            ];
            if ($id > 0) {
                $adv->updateById($id, $save_data);
            } else
                $adv->add($save_data);

            return $this->apiReturn(0, '', '保存成功');
        }
        $data = [
            'ID' => 0,
            'AdvTitle' => '',
            'AdvUrl' => '',
            'SortId' => '',
            'type' =>'',
            'AdvShareUrl'=>''
        ];
        if ($id > 0) {
            $data = $adv->getRowById($id);
        }
        $this->assign('info', $data);
        return $this->fetch();


    }


    public function addchargestep()
    {

        $firstcharge = new FirstChargeStepCfg();
        if ($this->request->isAjax()) {
            $data = input('stepval/a', array());
            foreach ($data as $k => $v) {
                //$d = bcdiv($v,10,0);
                $k = $k + 1;
                $firstcharge->updateByWhere(['StepId' => $k], ['WaterMultiply' => $v]);
            }
            GameLog::logData(__METHOD__, json_encode($data), 1);
            return $this->apiReturn(0, '', '修改成功');
        }

        $list = $firstcharge->getListAll([], '*', 'StepId');
        $this->assign('list', $list);
        return $this->fetch();
    }

    public function activityRecord()
    {
        $activity_arr = [
            '54' => lang('手机绑定领取'),
            '11' => lang('注册领取'),
            '15' => lang('每日领取'),
            '59' => lang('周签到领取'),
            '60' => lang('月签到领取'),
            '61' => lang('VIP特权领取-升级'),
            '101' => lang('vip周领取'),
            '102' => lang('vip月领取'),
            '67' => lang('周卡领取'),
            '68' => lang('月卡领取'),
        ];
        $action = $this->request->param('action');
        if ($action == 'list') {
            $limit = $this->request->param('limit') ?: 20;
            $roleid = $this->request->param('roleid');
            $start_date = $this->request->param('start_date') ?: date('Y-m-d');
            $end_date = $this->request->param('end_date') ?: date('Y-m-d');
            $end_date = date('Y-m-d', strtotime($end_date) + 86400);
            $hd_name = $this->request->param('hd_name');
            $db = new ActivityRecord();
            $where = '';
            if ($roleid != '') {
                $where .= ' and RoleID=' . $roleid;
            }
            if ($start_date != '') {
                $where .= ' and AddTime>=\'' . $start_date . '\'';
            }
            if ($end_date != '') {
                $where .= ' and AddTime<\'' . $end_date . '\'';
            }
            if ($hd_name != '') {
                $where .= ' and ChangeType in (' . $hd_name . ')';
            } else {
                $where .= ' and ChangeType in (54,11,15,59,60,61,101,102,67,68)';
            }
            $data = $db->GetPage($where, 'AddTime desc', '*');
            foreach ($data['list'] as $key => &$val) {
                $val['hd_name'] = $activity_arr[$val['ChangeType']] ?? lang('签到领取');
                $val['ReceiveAmt'] = FormatMoney($val['ReceiveAmt']);
            }
            return $this->apiJson($data);
        } else {
            $this->assign('activity_arr', $activity_arr);
            return $this->fetch();
        }
    }

    public function statistics()
    {
        $activity_arr = [
            '54' => lang('手机绑定领取'),
            '11' => lang('注册领取'),
            '15' => lang('每日领取'),
            '59' => lang('周签到领取'),
            '60' => lang('月签到领取'),
            '61' => lang('VIP特权领取-升级'),
            '101' => lang('vip周领取'),
            '102' => lang('vip月领取'),
            '67' => lang('周卡领取'),
            '68' => lang('月卡领取'),
        ];
        $action = $this->request->param('action');
        if ($action == 'list') {
            $limit = $this->request->param('limit') ?: 20;
            $roleid = $this->request->param('roleid');
            $start_date = $this->request->param('start_date') ?: date('Y-m-d');
            $end_date = $this->request->param('end_date') ?: date('Y-m-d');
            $end_date = date('Y-m-d', strtotime($end_date) + 86400);
            $hd_name = $this->request->param('hd_name');
            $db = new ActivityRecord();
            $where = '';
            if ($roleid != '') {
                $where .= ' and RoleID=' . $roleid;
            }
            if ($start_date != '') {
                $where .= ' and adddate>=\'' . $start_date . '\'';
            }
            if ($end_date != '') {
                $where .= ' and adddate<\'' . $end_date . '\'';
            }
            if ($hd_name != '') {
                $where .= ' and ChangeType in (' . $hd_name . ')';
            } else {
                $where .= ' and ChangeType in (54,11,15,59,60,61,101,102,67,68)';
            }
            $data = $db->getActivityReceiveSum()->GetPage($where, 'adddate desc', '*');
            foreach ($data['list'] as $key => &$val) {
                $val['hd_name'] = $activity_arr[$val['ChangeType']] ?? lang('签到领取');
                $val['ReceiveAmt'] = FormatMoney($val['ReceiveAmt']);
            }
            return $this->apiJson($data);
        } else {
            $this->assign('activity_arr', $activity_arr);
            return $this->fetch();
        }
    }


    //礼品卡列表
    public function giftCardList()
    {

        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $title = input('title', '');
            $giftcard = new GiftCardActive();
            $where = [];
            if (!empty($title)) {
                $where['ActiveName'] = ['like', '%' . $title . '%'];
            }
            $list = $giftcard->getList($where, $page, $limit, '*', 'id desc');
            $count = $giftcard->getCount($where);
            $active_domain = config('giftcard_url');
            foreach ($list as $k => &$v) {
                $v['AddTime'] = date('Y-m-d H:i:s', strtotime($v['AddTime']));
                $v['UrlLink'] = $active_domain.'/?activeid='.$v['Id'];
            }
            return $this->apiReturn(0, $list, '', $count);
        }
        return $this->fetch();
    }


    public function addGiftCard()
    {
        $id = input('id', 0);
        $giftcard = new GiftCardActive();
        if ($this->request->isAjax()) {
            $data = $_POST;
            $id = $data['id'];
            unset($data['id']);
            if ($id > 0) {
                $result = $giftcard->updateById($id, $data);
            } else {
                $result = $giftcard->add($data);
            }
            return $this->successJSON('');
        }
        if ($id > 0) {

            $info = $giftcard->getRowById($id);
            $this->assign('info', $info);
        }
        return $this->fetch();
    }

    public function  setGiftCardStatus(){

        try {
            if ($this->request->isAjax()) {
                $id = input('id', 0, 'intval');
                $status = input('status', 0, 'intval');
                if (empty($id)) {
                    throw new \Exception(lang('id不能为空'));
                }
                if ($status === 0) {
                    $update_status = 0;
                } else {
                    $update_status = 1;
                }
                $giftcard = new GiftCardActive();
                $giftcard->updateById($id,['Status'=>$update_status]);

            } else {
                throw new \Exception(lang('请求方式错误'));
            }
            return $this->successData([], '操作成功');
        } catch (\Exception $ex) {
            return $this->failData($ex->getMessage());
        }


    }


    public function delgiftcard()
    {
        $id = input('id', 0, 'intval');
        $giftcard = new GiftCardActive();
        if ($id > 0) {
            $giftcard->delRow(['id' => $id]);
            return $this->apiReturn(0, '', '删除成功');
        } else {
            return $this->apiReturn(100, '', '删除失败');
        }
    }


    public function getGiftReceive(){
        $id = input('id', '');
        if ($this->request->isAjax()) {
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;

            $giftcard = new GiftCardReceive();
            $where = [];
            if ($id>0) {
                $where['ActiveId'] =$id;
            }
            $list = $giftcard->getList($where, $page, $limit, '*', 'id desc');
            $count = $giftcard->getCount($where);
            foreach ($list as $k => &$v) {
                $v['Amount'] = FormatMoney($v['Amount']);
                $v['AddTime'] = date('Y-m-d H:i:s', strtotime($v['AddTime']));
            }
            return $this->apiReturn(0, $list, '', $count);
        }
        $this->assign('id',$id);
        return $this->fetch();
    }


}