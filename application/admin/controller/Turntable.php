<?php

namespace app\admin\controller;

use app\common\GameLog;
use app\model\GameOCDB;

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

    //审核记录
    public function checkRecord()
    {
        
    }

    //奖励增加详情
    public function detailsOfRewardIncrease()
    {
        
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
}