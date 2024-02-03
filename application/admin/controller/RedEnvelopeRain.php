<?php

namespace app\admin\controller;

use app\model\DataChangelogsDB;
use app\model\MasterDB;
use Ramsey\Uuid\DeprecatedUuidMethodsTrait;

class RedEnvelopeRain extends Main
{

    public function index()
    {

        return $this->fetch();
    }

    public function list()
    {
        $masterDB = new MasterDB();
        $count = $masterDB->getTableObject('T_RedPackCfg')->count();
        $lists = $masterDB->getTableObject('T_RedPackCfg')->select();

        foreach ($lists as &$list) {
            $list['DailyBeginHour'] = $list['DailyBeginHour'] . ':00:00';
            $list['DailyEndHour'] = $list['DailyEndHour'] . ':00:00';
            $list['BeginDay'] = date('Y-m-d', $list['BeginDay']);
            $list['EndDay'] = date('Y-m-d', $list['EndDay']);
            $list['RedPackTotalMoney'] = FormatMoney($list['RedPackTotalMoney']);
        }
        return $this->apiReturn(0, $lists, '', $count);
    }


    public function editRedPack()
    {

        $id = input('id');
        if ($this->request->isAjax()) {

        }
        $masterDB = new MasterDB();
        $configInfo = $masterDB->getTableObject('T_RedPackCfg')
            ->where('ID', $id)
            ->find();
        $configInfo['BeginDay'] = date('Y-m-d', $configInfo['BeginDay']);
        $configInfo['EndDay'] = date('Y-m-d', $configInfo['EndDay']);
        $configInfo['RedPackTotalMoney'] = FormatMoney($configInfo['RedPackTotalMoney']);

        $this->assign('info', $configInfo);
        return $this->fetch();
    }

    public function updateRedPack()
    {
//        dump($this->request->param());die();
        $id = input('id');
        $beginDay = input('BeginDay');//开始日期
        $endDay = input('EndDay');//结束日期
        $dailyBeginHour = input('DailyBeginHour');//开始时间
        $dailyEndHour = input('DailyEndHour');//结束时间

        $redPackNum = input('RedPackNum');//红包总数
        $redPackTotalMoney = input('RedPackTotalMoney');//红包总金额
        $getMaxCount = input('GetMaxCount');//玩家同时时段内可领取红包个数
        $checkType = input('CheckType');//可领红包的玩家类型
        $onOff = input('OnOff');//红包状态
        $setVip = $this->request->param();
        $dailyGetOne = input('DailyGetOne');

        $masterDB = new MasterDB();
        try {
            $masterDB->startTrans();
            $data = [
                'BeginDay' => strtotime($beginDay),
                'EndDay' => strtotime($endDay),
                'DailyBeginHour' => $dailyBeginHour,
                'DailyEndHour' => $dailyEndHour,
                'RedPackNum' => $redPackNum,
                'RedPackTotalMoney' => $redPackTotalMoney * bl,
                'GetMaxCount' => $getMaxCount,
                'CheckType' => $checkType,
                'OnOff' => $onOff,
                'DailyGetOne' => $dailyGetOne
            ];


            $masterDB->getTableObject('T_RedPackCfg')
                ->where('ID', $id)
                ->update($data);


            if (!isset($setVip['setVIP']) || !$setVip['setVIP']) {
                return '';
            } else {
                $masterDB->getTableObject('T_RedBackVipGetCfg')
                    ->where('ActivityId', $id)
                    ->delete();
                $setVipArray = $setVip['setVIP'];
                $setVipData = [];
                foreach ($setVipArray as $k) {
                    $item = [];
                    $item['ActivityId'] = $id;
                    $item['RedPackCellMoneyMin'] = $k['RedPackCellMoneyMin'] * bl;
                    $item['RedPackCellMoneyMax'] = $k['RedPackCellMoneyMax'] * bl;
                    $item['VipLvMin'] = $k['VipLvMin'];
                    $item['VipLvMax'] = $k['VipLvMax'];
                    $setVipData[] = $item;
                }
                $masterDB->getTableObject('T_RedBackVipGetCfg')->insertAll($setVipData);
            }

            $this->synconfig();
            $masterDB->commit();
        } catch (\Exception $e) {
            $masterDB->rollback();
            save_log('RedEnvelopeRain', '===' . $e->getMessage() . $e->getTraceAsString() . $e->getLine());
        }

        return $this->successJSON('');

    }

    public function create()
    {
        $beginDay = input('BeginDay');//开始日期
        $endDay = input('EndDay');//结束日期
        $dailyBeginHour = input('DailyBeginHour');//开始时间
        $dailyEndHour = input('DailyEndHour');//结束时间
//        $redPackCellMoneyMin = input('RedPackCellMoneyMin');//红包随机最小值
//        $redPackCellMoneyMax = input('RedPackCellMoneyMax');//红包随机最大值
        $redPackNum = input('RedPackNum');//红包总数
        $redPackTotalMoney = input('RedPackTotalMoney');//红包总金额
        $getMaxCount = input('GetMaxCount');//玩家同时时段内可领取红包个数
        $checkType = input('CheckType');//可领红包的玩家类型
        $onOff = input('OnOff');//红包状态
        $dailyGetOne = input('DailyGetOne');
        $setVip = $this->request->param();
        $masterDB = new MasterDB();
        try {
            $masterDB->startTrans();
            $data = [
                'BeginDay' => strtotime($beginDay),
                'EndDay' => strtotime($endDay),
                'DailyBeginHour' => $dailyBeginHour,
                'DailyEndHour' => $dailyEndHour,
                'RedPackNum' => $redPackNum,
                'RedPackTotalMoney' => $redPackTotalMoney * bl,
                'GetMaxCount' => $getMaxCount,
                'CheckType' => $checkType,
                'OnOff' => $onOff,
                'DailyGetOne' => $dailyGetOne
            ];

            $setVipArray = $setVip['setVIP'];
            if (!$setVipArray) {
                return $this->failJSON('VIP配置不能为空');
            }
            $callbackId = $masterDB->getTableObject('T_RedPackCfg')->insertGetId($data);
            $setVipData = [];
            foreach ($setVipArray as $k) {
                $item = [];
                $item['ActivityId'] = $callbackId;
                $item['RedPackCellMoneyMin'] = $k['RedPackCellMoneyMin'] * bl;
                $item['RedPackCellMoneyMax'] = $k['RedPackCellMoneyMax'] * bl;
                $item['VipLvMin'] = $k['VipLvMin'];
                $item['VipLvMax'] = $k['VipLvMax'];
                $setVipData[] = $item;
            }
            $masterDB->getTableObject('T_RedBackVipGetCfg')->insertAll($setVipData);
            $masterDB->getTableObject('T_RedPackCfg')
                ->where('ID', $callbackId)
                ->update(['ActivityId' => $callbackId]);
            $this->synconfig();
            $masterDB->commit();
        } catch (\Exception $e) {
            $masterDB->rollback();
            save_log('RedEnvelopeRain', '===' . $e->getMessage() . $e->getTraceAsString() . $e->getLine());
        }

        return $this->successJSON('');

    }

    public function delete()
    {
        $id = input('id', 0, 'intval');
        $masterDB = new MasterDB();
        if ($id > 0) {
            $res = $masterDB->getTableObject('T_RedPackCfg')
                ->where('ID', $id)
                ->delete();
            $masterDB->getTableObject('T_RedBackVipGetCfg')->where('ActivityId', $id)->delete();
            if ($res) {
                return $this->apiReturn(0, '', '删除成功');

            } else {
                return $this->apiReturn(100, '', '删除失败');
            }
        } else {
            return $this->apiReturn(100, '', 'ID不存在，删除失败');
        }
    }

    public function addRedPack()
    {
        return $this->fetch();
    }

    public function setStatusOnOff()
    {
        try {
            if ($this->request->isAjax()) {
                $id = input('id', 0, 'intval');
                $status = input('status', 0, 'intval');
                if (empty($id)) {
                    throw new \Exception(lang('id不能为空'));
                }
//                dump($id);dump($status);die();
                if ($status === 0) {
                    $updateStatus = '0';
                } else {
                    $updateStatus = '1';
                }
//                dump($updateStatus);die();
                $masterDB = new MasterDB();
                $masterDB->getTableObject('T_RedPackCfg')
                    ->where('ID', $id)
                    ->setField('OnOff', $updateStatus);

            } else {
                throw new \Exception(lang('请求方式错误'));
            }
            return $this->successData([], '操作成功');
        } catch (\Exception $ex) {
            return $this->failData($ex->getMessage());
        }
    }

//    public function redPackVipGetCfgList()
//    {
//        if ($this->request->isAjax()) {
//            $masterDB = new MasterDB();
//            $count = $masterDB->getTableObject('T_RedBackVipGetCfg')
//                ->count();
//            $lists = $masterDB->getTableObject('T_RedBackVipGetCfg')
//                ->select();
//
//            foreach ($lists as &$list) {
//                $list['DailyBeginHour'] = $list['DailyBeginHour'].':00:00';
//                $list['DailyEndHour'] = $list['DailyEndHour'].':00:00';
//            }
//            return $this->apiReturn(0, $lists, '', $count);
//        }
//        return $this->fetch();
//    }
//
//    public function addRedPackVipCfg()
//    {
//        if ($this->request->isAjax()) {
//
//            $dailyBeginHour = input('DailyBeginHour');//开始时间
//            $dailyEndHour = input('DailyEndHour');//结束时间
//            $redPackCellMoneyMin = input('RedPackCellMoneyMin');//红包随机最小值
//            $redPackCellMoneyMax = input('RedPackCellMoneyMax');//红包随机最大值
//            $vipLvMin = input('VipLvMin');//红包总数
//            $vipLvMax = input('VipLvMax');//红包总金额
//
//            $masterDB = new MasterDB();
//            $data = [
//                'DailyBeginHour' => $dailyBeginHour,
//                'DailyEndHour' => $dailyEndHour,
//                'RedPackCellMoneyMin' => $redPackCellMoneyMin,
//                'RedPackCellMoneyMax' => $redPackCellMoneyMax,
//                'VipLvMin' => $vipLvMin,
//                'VipLvMax' => $vipLvMax,
//            ];
//            $callback = $masterDB->getTableObject('T_RedBackVipGetCfg')->insert($data);
//            return $this->successJSON('');
//        }
//        return $this->fetch();
//    }
//
//
//    public function deleteRedPackVipCfg()
//    {
//        $id = input('id', 0, 'intval');
//        $masterDB = new MasterDB();
//        if ($id > 0) {
//            $res = $masterDB->getTableObject('T_RedBackVipGetCfg')
//                ->where('ID', $id)
//                ->delete();
//            if ($res) {
//                return $this->apiReturn(0, '', '删除成功');
//
//            } else {
//                return $this->apiReturn(100, '', '删除失败');
//            }
//        } else {
//            return $this->apiReturn(100, '', 'ID不存在，删除失败');
//        }
//    }

    public function getSetVipInfo()
    {
        $id = input('id');
        $masterDB = new MasterDB();
        $vipInfo = $masterDB->getTableObject('T_RedBackVipGetCfg')
            ->where('ActivityId', $id)
            ->select();
        foreach ($vipInfo as &$info) {
            ConVerMoney($info['RedPackCellMoneyMin']);
            ConVerMoney($info['RedPackCellMoneyMax']);
        }
        return $this->successJSON($vipInfo);
    }


    public function redPackLogList()
    {
        if ($this->request->isAjax()) {
            $activityId = input('activityId', '');
            $roleId = input('roleId', '');
            $start = input('start', '');
            $end = input('end', '');
            $page = input('page');
            $limit = input('limit');
            $where = '1=1';
            if (!empty($activityId)) {
                $where .= ' and ActivityId=' . "'$activityId'";
            }
            if (!empty($roleId)) {
                $where .= ' and RoleId=' . $roleId;
            }

            if (!empty($start) && !empty($end)) {
                $startDate = strtotime($start . '00:00:00');
                $endDate = strtotime($end . '23:59:59');
                $where .= ' and AddTime>=' . "'$startDate'" . ' and AddTime<=' . "'$endDate'";

            }

            $changeLogDB = new DataChangelogsDB();
            $count = $changeLogDB->getTableObject('T_RedPackHistory')
                ->where($where)
                ->count();
            $lists = $changeLogDB->getTableObject('T_RedPackHistory')
                ->where($where)
                ->limit($limit)
                ->page($page)
                ->select();

            foreach ($lists as &$list) {
                $list['Money'] = FormatMoney($list['Money']);
                $list['AddTime'] = date('Y-m-d', $list['AddTime']);
            }
            return $this->apiReturn(0, $lists, '', $count);
        }
        return $this->fetch();
    }


    public function redPackSumData()
    {
        $Id = input('activityId', '');
        $roleId = input('roleId', '');
        $start = input('start', '');
        $end = input('end', '');
        $masterDB = new MasterDB();
        $newActivityKey = $masterDB->getTableObject('T_GlobalCache')
            ->whereIn('GlobalKey', [5, 6, 9])
            ->select();
        $activityId = 0;
        $residueRedPackNum = 0;
        $residuePrice = 0;
        foreach ($newActivityKey as $k) {
            if ($k['GlobalKey'] == 5) {
                $residueRedPackNum = $k['GlobalValue'];
            }
            if ($k['GlobalKey'] == 6) {
                $residuePrice = $k['GlobalValue'];
            }
            if ($k['GlobalKey'] == 9) {
                $activityId = $k['GlobalValue'];
            }
        }

        $activity = $masterDB->getTableObject('T_RedPackCfg')
            ->where('ID', $activityId)
            ->find();

        $where = '1=1';
        if (!empty($Id)) {
            $where .= ' and ActivityId=' . "'$Id'";
        }
        if (!empty($roleId)) {
            $where .= ' and RoleId=' . $roleId;
        }

        if (!empty($start) && !empty($end)) {
            $startDate = strtotime($start . '00:00:00');
            $endDate = strtotime($end . '23:59:59');
            $where .= ' and AddTime>=' . "'$startDate'" . ' and AddTime<=' . "'$endDate'";

        }
        $changeLogDB = new DataChangelogsDB();
        $count = $changeLogDB->getTableObject('T_RedPackHistory')
            ->where($where)
            ->field('count(*) as packNum,SUM(Money) as totalMoney')
            ->find();

        if (!empty($activity)){
            $residueRedPack = $activity['RedPackNum'] - $residueRedPackNum . '/' . $activity['RedPackNum'];
            $residueMoney = bcsub($activity['RedPackTotalMoney'], $residuePrice, 2) / bl . '/' . $activity['RedPackTotalMoney'] / bl;
        }

        $data = [
            'residueRedPack' => $residueRedPack ?? 0,
            'residueMoney' => $residueMoney ?? 0,
            'packNum' => $count['packNum'] ?? 0,
            'totalMoney' => FormatMoney($count['totalMoney'])
        ];
        $result['other'] = $data;
        return $this->apiJson($result);
    }
}