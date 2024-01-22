<?php

namespace app\admin\controller;

use app\admin\controller\Export\AgentWaterDailyExport;
use app\admin\controller\Export\ColorMoneyLogExport;
use app\model\DataChangelogsDB;

class ColorMoney extends Main
{
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
                ->limit($limit)
                ->page($page)
                ->select();


            foreach ($list as &$key) {
                ConVerMoney($key['ChangeMoney']);
                ConVerMoney($key['LastMoney']);
                $key['AddTime'] = date('Y-m-d', strtotime($key['AddTime']));
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