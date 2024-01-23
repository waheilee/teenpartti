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
            $list['DailyBeginHour'] = date('H:i:s', strtotime($list['DailyBeginHour']));
            $list['DailyEndHour'] = date('H:i:s', strtotime($list['DailyEndHour']));
        }
        return $this->apiReturn(0, $lists, '', $count);
    }


    public function edit()
    {

    }

    public function create()
    {
        $beginDay = input('BeginDay');//开始日期
        $endDay = input('EndDay');//结束日期
        $dailyBeginHour = input('DailyBeginHour');//开始时间
        $dailyEndHour = input('DailyEndHour');//结束时间
        $redPackCellMoneyMin = input('RedPackCellMoneyMin');//红包随机最小值
        $redPackCellMoneyMax = input('RedPackCellMoneyMax');//红包随机最大值
        $redPackNum = input('RedPackNum');//红包总数
        $redPackTotalMoney = input('RedPackTotalMoney');//红包总金额
        $getMaxCount = input('GetMaxCount');//玩家同时时段内可领取红包个数
        $checkType = input('CheckType');//可领红包的玩家类型
        $onOff = input('OnOff');//红包状态
        $masterDB = new MasterDB();
        $data = [
            'BeginDay' => $beginDay,
            'EndDay' => $endDay,
            'DailyBeginHour' => $dailyBeginHour,
            'DailyEndHour' => $dailyEndHour,
            'RedPackCellMoneyMin' => $redPackCellMoneyMin,
            'RedPackCellMoneyMax' => $redPackCellMoneyMax,
            'RedPackNum' => $redPackNum,
            'RedPackTotalMoney' => $redPackTotalMoney,
            'GetMaxCount' => $getMaxCount,
            'CheckType' => $checkType,
            'OnOff' => $onOff
        ];
        $callback = $masterDB->getTableObject('T_RedBackCfg')->insert($data);
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
            if ($res){
                return $this->apiReturn(0, '', '删除成功');

            }else{
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
}