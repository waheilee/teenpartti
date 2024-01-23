<?php

namespace app\admin\controller;

use app\admin\controller\Export\AgentWaterDailyExport;
use app\admin\controller\Export\ColorMoneyLogExport;
use app\common\GameLog;
use app\model\DataChangelogsDB;
use app\model\GameOCDB;

class ColorMoney extends Main
{

    /**
     * 彩金设置
     * @return mixed
     */
    public function setColorMoney()
    {
        $roleId = $this->request->param('roleid');
        $amount = $this->request->param('amount');
        $type = $this->request->param('type');

        $sendDm = $amount * bl;
        $key = 0;
        if ($type == 1){
            $key = 10213; //增加彩金
        }elseif ($type == 2){
            $key = 10215; //增加首充彩金
        }elseif ($type == 3){
            $key = 10211; //不打码彩金
        }elseif ($type == 4){
            $key = 10210; //不打码首充彩金
        }
        $data = $this->sendGameMessage('CMD_MD_GM_SET_JOB', [$roleId, $key, $sendDm], "DC", 'returnComm');
        if ($data['iResult'] == 1) {
            if ($type == 1) {
                $comment = '增加玩家彩金：' . $amount;
            } elseif($type == 2) {
                $comment = '增加玩家首冲彩金:' . $amount;
            }elseif($type == 3) {
                $comment = '增加玩家不打码彩金:' . $amount;
            }elseif($type == 4) {
                $comment = '增加玩家不打码首充彩金:' . $amount;
            }else{
                $comment = '无效彩金增加:' . $amount;
            }
            $db = new GameOCDB();
            $db->setTable('T_PlayerComment')->Insert([
                'roleid' => $roleId,
                'adminid' => session('userid'),
                'type' => 2,
                'opt_time' => date('Y-m-d H:i:s'),
                'comment' => $comment
            ]);

            GameLog::logData(__METHOD__, [$roleId, $amount, $type], 1, $comment);
            return $this->apiReturn(0, '', '操作成功');
        } else {
            GameLog::logData(__METHOD__, [$roleId, $amount, $type], 0, '操作失败');
            return $this->apiReturn(1, '', '操作失败');
        }
    }
    public function colorMoneyCoinLog()
    {
        if ($this->request->isAjax()) {
            $page = input('page', 1);
            $limit = input('limit', 15);
            $roleId = input('roleid', '');
            $start = input('start', '');
            $end = input('end', '');
            $changeType = input('ChangeType', -1);
            $moneyKey = input('MoneyKey', -1);
            $order = input('orderby','');
            $orderType = input('ordertype');
            $m = new DataChangelogsDB();
            $count = $m->getTableObject('T_ColorMoneyLog')
                ->where(function ($q) use ($roleId) {
                    if (!empty($roleId)) {
                        $q->where('RoleId', $roleId);
                    }

                })
                ->where(function ($q) use ($changeType) {
                    if ($changeType >= 0) {
                        $q->where('ChangeType', $changeType);
                    }

                })
                ->where(function ($q) use ($moneyKey) {
                    if ($moneyKey > 0) {
                        $q->where('MoneyKey', $moneyKey);
                    }
                })
                ->where(function ($q) use ($start, $end) {

                    if (!empty($start) && !empty($end)) {
                        $q->where('AddTime', 'between time', [$start, $end]);
                    }
                })
                ->count();

            $list = $m->getTableObject('T_ColorMoneyLog')
                ->where(function ($q) use ($roleId) {
                    if (!empty($roleId)) {
                        $q->where('RoleId', $roleId);
                    }

                })
                ->where(function ($q) use ($changeType) {
                    if ($changeType >= 0) {
                        $q->where('ChangeType', $changeType);
                    }

                })
                ->where(function ($q) use ($moneyKey) {
                    if ($moneyKey > 0) {
                        $q->where('MoneyKey', $moneyKey);
                    }
                })
                ->where(function ($q) use ($start, $end) {

                    if (!empty($start) && !empty($end)) {
                        $q->where('AddTime', 'between time', [$start, $end]);
                    }
                })
                ->order($order,$orderType)
                ->limit($limit)
                ->page($page)
                ->select();


            foreach ($list as &$key) {
                ConVerMoney($key['ChangeMoney']);
                ConVerMoney($key['LastMoney']);
                $key['AddTime'] = date('Y-m-d　H:i:s', strtotime($key['AddTime']));
            }


            $result['list'] = $list;
            $result['count'] = $count;
            return $this->apiJson($result);
        }
        return $this->fetch();
    }

    public function colorMoneyCoinLogSumData()
    {
        $roleId = input('roleid', '');
        $start = input('start', '');
        $end = input('end', '');
        $changeType = input('ChangeType', -1);
        $moneyKey = input('MoneyKey', -1);
        $m = new DataChangelogsDB();
        $where = "1=1";
        if (!empty($roleId)) {
            $where .= ' AND RoleId=' . $roleId;
        }
        if ($changeType >= 0) {
            $where .= ' AND ChangeType=' . $changeType;
        }
        if ($moneyKey > 0) {
            $where .= ' AND MoneyKey=' . $moneyKey;
        }
        if (!empty($start) && !empty($end)) {

            $where .= " AND AddTime>='$start' AND AddTime<='$end'";
        }
        $sql = "exec ColorMoneyCoinLogSumData " . '"' . $where . '"';
        $result = $m->getTableQuery($sql);
        $data = $result[0][0];

        $count['firstColorMoney'] = FormatMoney($data['firstColorMoney']);
        $count['handColorMoney'] = FormatMoney($data['handColorMoney']);
        $count['firstUserCount'] = $data['firstUserCount'];
        $count['handUserCount'] = $data['handUserCount'];

        $result['other'] = $count;
        return $this->apiJson($result);
    }

    public function colorMoneyLogExport()
    {
        $export = new ColorMoneyLogExport();
        $export->export();
    }
}