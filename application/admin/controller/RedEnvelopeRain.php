<?php

namespace app\admin\controller;

use app\model\MasterDB;

class RedEnvelopeRain extends Main
{

    public function index()
    {

        return $this->fetch();
    }

    public function list()
    {
        $masterDB = new MasterDB();
        $count = $masterDB->getTableObject('T_RedBackCfg')->count();
        $lists = $masterDB->getTableObject('T_RedBackCfg')->select();

        foreach ($lists as &$list) {
            $list['DailyBeginHour'] = $list['DailyBeginHour'].':00:00';
            $list['DailyEndHour'] = $list['DailyEndHour'].':00:00';
            $list['BeginDay'] = date('Y-m-d',$list['BeginDay']);
            $list['EndDay'] = date('Y-m-d',$list['EndDay']);
        }
        return $this->apiReturn(0, $lists, '', $count);
    }


    public function editRedPack()
    {

        $id = input('id');
        if ($this->request->isAjax()){

        }
        $masterDB = new MasterDB();
        $configInfo = $masterDB->getTableObject('T_RedBackCfg')
            ->where('ID',$id)
            ->find();
        $configInfo['BeginDay'] = date('Y-m-d',$configInfo['BeginDay']);
        $configInfo['EndDay'] = date('Y-m-d',$configInfo['EndDay']);

        $this->assign('info',$configInfo);
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
        $masterDB = new MasterDB();
        try {
            $masterDB->startTrans();
            $data = [
                'BeginDay' => strtotime($beginDay),
                'EndDay' => strtotime($endDay),
                'DailyBeginHour' => $dailyBeginHour,
                'DailyEndHour' => $dailyEndHour,
                'RedPackNum' => $redPackNum,
                'RedPackTotalMoney' => $redPackTotalMoney,
                'GetMaxCount' => $getMaxCount,
                'CheckType' => $checkType,
                'OnOff' => $onOff
            ];

            $setVipArray = $setVip['setVIP'];
            if (!$setVipArray){
                return $this->failJSON('VIP配置不能为空');
            }
            $callbackId = $masterDB->getTableObject('T_RedBackCfg')
                ->where('ID',$id)
                ->update($data);
            $masterDB->getTableObject('T_RedBackVipGetCfg')
                ->where('ActivityId',$id)
                ->delete();
            $setVipData = [];
            foreach ($setVipArray as $k){
                $item = [];
                $item['ActivityId'] = $id;
                $item['RedPackCellMoneyMin'] = $k['RedPackCellMoneyMin'];
                $item['RedPackCellMoneyMax'] = $k['RedPackCellMoneyMax'];
                $item['VipLvMin'] = $k['VipLvMin'];
                $item['VipLvMax'] = $k['VipLvMax'];
                $setVipData[]=$item;
            }
            $masterDB->getTableObject('T_RedBackVipGetCfg')->insertAll($setVipData);
            $masterDB->commit();
        }catch (\Exception $e){
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
                'RedPackTotalMoney' => $redPackTotalMoney,
                'GetMaxCount' => $getMaxCount,
                'CheckType' => $checkType,
                'OnOff' => $onOff
            ];

            $setVipArray = $setVip['setVIP'];
            if (!$setVipArray){
                return $this->failJSON('VIP配置不能为空');
            }
            $callbackId = $masterDB->getTableObject('T_RedBackCfg')->insertGetId($data);
            $setVipData = [];
            foreach ($setVipArray as $k){
                $item = [];
                $item['ActivityId'] = $callbackId;
                $item['RedPackCellMoneyMin'] = $k['RedPackCellMoneyMin'];
                $item['RedPackCellMoneyMax'] = $k['RedPackCellMoneyMax'];
                $item['VipLvMin'] = $k['VipLvMin'];
                $item['VipLvMax'] = $k['VipLvMax'];
                $setVipData[]=$item;
            }
            $masterDB->getTableObject('T_RedBackVipGetCfg')->insertAll($setVipData);
            $masterDB->commit();
        }catch (\Exception $e){
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
            $res = $masterDB->getTableObject('T_RedBackCfg')
                ->where('ID', $id)
                ->delete();
            $masterDB->getTableObject('T_RedBackVipGetCfg')->where('ActivityId',$id)->delete();
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
                if ($status === 0) {
                    $updateStatus = 0;
                } else {
                    $updateStatus = 1;
                }
                $masterDB = new MasterDB();
                $masterDB->getTableObject('T_RedBackCfg')
                    ->where('ID', $id)
                    ->update(['OnOff' => $updateStatus]);

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
            ->where('ActivityId',$id)
            ->select();
        return $this->successJSON($vipInfo);
    }
}