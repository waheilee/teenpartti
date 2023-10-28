<?php

namespace app\admin\controller;

use app\common\GameLog;
use app\model\GameOCDB;
use app\model\UserDB;

class Turntable extends Main
{

    //分享统计
    public function sharingStatistics()
    {
        //按照降序排序，可以看到该分享人分享的次数/注册人数/充值人数/充值金额/取款金额/存提差
        //后面有两个功能 一个是增加第三点的附带金额。
        //如果对方有提交领取申请，则有一个领取审核通过按钮用于发放奖金。
        //具体样式参考代理明细页面，包含筛选。
    }

    //转盘审核记录
    public function checkRecord()
    {
        $checkName = session('username');//审核人员

        if ($this->request->isAjax()) {
            $roleId = input('role_id');
            $commitStartTime = input('commit_start_time');
            $commitEndTime = input('commit_end_time');
            $passStartTime = input('pass_start_time');
            $passEndTime = input('pass_end_time');
            $checkUser = input('check_user');
            $page = input('page');
            $limit = input('limit');
            if (input('Action') == 'list') {
                $userDB = new UserDB();
//                $where['RoleId'] = $roleId ?? '';
//                $where['CommiTime'] = $roleId ?? '';
                $count = $userDB->getTableObject('T_PDDCommi')->count();
                $checkRecord = $userDB->getTableObject('T_PDDCommi')
                    ->limit($limit)
                    ->page($page)
                    ->select();
//                var_dump($checkRecord);die();
                $data['count'] = $count;
                $data['list'] = $checkRecord;
                return $this->apiJson($data);
            }
        }

        return $this->fetch();
    }

    //奖励增加详情
    public function detailsOfRewardIncrease()
    {
        //11.	后台有一个用户每笔奖励增加的明细表，对应第一点的功能，每增加一个金额 都有一个明细，以及时间。
        //可筛选用户ID 增加类型 时间区间。T_PDDDrawHistory

        if ($this->request->isAjax()) {
            $roleId = input('roleid');
            $tranType = input('tranType');
            $startTime = input('start');
            $endTime = input('end');
            $page = input('page');
            $limit = input('limit');
            if (input('Action') == 'list') {
//                $map['RoleId'] = $roleId ?? '';
//                $map['Item'] = $tranType ?? '';
                $start = strtotime($startTime) ?? '';
                $end = strtotime($endTime) ?? '';

                $userDB = new UserDB();
                $count = $userDB->getTableObject('T_PDDDrawHistory')->count();
                $checkRecord = $userDB->getTableObject('T_PDDDrawHistory')
                    ->where(function ($q) use($roleId){
                        if (!empty($roleId)){
                            $q->where('RoleId',$roleId);
                        }
                    })
                    ->where(function ($q) use($tranType){
                        if (!empty($tranType)){
                            $q->where('Item',$tranType);
                        }
                    })
                    ->where(function ($q) use($startTime,$endTime){
                        if (!empty($startTime)){
                            $q->where('ChangeTime','>',strtotime($startTime));
                        }
                        if (!empty($endTime)){
                            $q->where('ChangeTime','<',strtotime($endTime));
                        }
                        if (!empty($startTime) && !empty($endTime)){
                            $q->where('ChangeTime','between time',[strtotime($startTime),strtotime($endTime)]);
                        }
                    })
                    ->where('ChangeType',1)
                    ->whereIn('Item',[1,2,6])
//                    ->whereTime('ChangeTime', 'between', [$start, $end])
                    ->limit($limit)
                    ->page($page)
                    ->select();
                $temp = [];
                foreach($checkRecord as $record){
                    $item = [];
                    $item['RoleId'] = $record['RoleId'];
                    $item['ChangeTime'] = date('Y-m-d H:i:s',$record['ChangeTime']);
                    $recordItem = '';
                    $recordItemVal = '';
                    switch ($record['Item']){
                        case 1:
                            $recordItem = '大随机金额';
                            $recordItemVal = $record['ItemVal'] / bl;
                            break;
                        case 2:
                            $recordItem = '小随机金额';
                            $recordItemVal = $record['ItemVal'] / bl;
                            break;
                        case 6:
                            $recordItem = '获得随机次数';
                            $recordItemVal = $record['ItemVal'];
                            break;
                    }
                    $item['Item'] = $recordItem;
                    $item['ItemVal'] = $recordItemVal;
                    $temp[] = $item;
                }
                $data['count'] = $count;
                $data['list'] = $temp;
                return $this->apiJson($data);
            }
        }

        return $this->fetch();

    }

    public function handleAddReward()
    {
        $roleid = $this->request->param('roleid');
        $amount = $this->request->param('amount');
        $type = $this->request->param('type');

        $send_dm = $amount * bl;
        $data = $this->sendGameMessage('CMD_MD_GM_PDD_ADD_MONEY', [$roleid, $type, $send_dm], "DC", 'returnComm');
        if ($data['iResult'] == 1) {
            if ($type == 1) {
                $comment = '增加玩家转盘奖励金：' . $amount;
            } else {
                $comment = '扣减玩家转盘奖励金:' . $amount;
            }
            $db = new GameOCDB();
            $db->setTable('T_PlayerComment')->Insert([
                'roleid' => $roleid,
                'adminid' => session('userid'),
                'type' => 2,
                'opt_time' => date('Y-m-d H:i:s'),
                'comment' => $comment
            ]);

            GameLog::logData(__METHOD__, [$roleid, $amount, $type], 1, $comment);
            return $this->apiReturn(0, '', '操作成功');
        } else {
            GameLog::logData(__METHOD__, [$roleid, $amount, $type], 0, '操作失败');
            return $this->apiReturn(1, '', '操作失败');
        }
    }


    public function check()
    {
//        CMD_MD_GM_PDD_COMMI_SUC			= 10004,		//pdd 审批通过
//
//	int 玩家id;
        $roleId = input('role_id');
        $isPass = input('is_pass');
        if ($isPass == 1) {
            $data = $this->sendGameMessage('CMD_MD_GM_PDD_COMMI_SUC', [$roleId, 1], "DC", 'returnComm');
            if ($data['iResult'] == 1) {

                $db = new GameOCDB();
                $db->setTable('T_PlayerComment')->Insert([
                    'roleid' => $roleId,
                    'adminid' => session('userid'),
                    'type' => 2,
                    'opt_time' => date('Y-m-d H:i:s'),
                    'comment' => '玩家转盘审核'
                ]);

                GameLog::logData(__METHOD__, [$roleId,], 1, '玩家转盘审核成功');
                return $this->apiReturn(0, '', '操作成功');
            } else {
                GameLog::logData(__METHOD__, [$roleId], 0, '操作失败');
                return $this->apiReturn(1, '', '操作失败');
            }
        }
        $userDB = new UserDB();
        $update  = $userDB->getTableObject('T_PDDCommi')->where('RoleId', $roleId)->update(['GetType' => $isPass]);
        if ($update){
            return $this->apiReturn(0, '', '操作成功');

        }else{
            return $this->apiReturn(1, '', '操作失败');
        }

    }
}