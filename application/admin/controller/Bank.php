<?php

namespace app\admin\controller;

use app\common\Api;
use app\common\GameLog;
use socket\QuerySocket;


class Bank extends Main
{
    //金币配置
    public function index()
    {
        if ($this->request->isAjax()) {
            $res = Api::getInstance()->sendRequest([], 'system', 'sysbank');
            $socket = new QuerySocket();
            $bank = $socket->getSystembankdata();
            $totalmoney = $socket->getRoleTotalMoney();
            $totalmoney['TotalBankMoney'] /= 1000;
            $totalmoney['TotalGameMoney'] /= 1000;
            $totalmoney['TotalMoney'] = $totalmoney['TotalBankMoney'] + $totalmoney['TotalGameMoney'];
            $capacity = Api::getInstance()->sendRequest([], 'system', 'capacity');
            $capacity = isset($capacity['data']['totalcapacity']) ?  $capacity['data']['totalcapacity']/1000 : 0;

            $other = [
                'totalmoney' => $totalmoney,
                'capacity'  => $capacity
            ];
            $config = config('site.bankType');

            if ($res['data']) {
                $sum = 0;
                foreach ($res['data'] as &$v) {
                    $v['money'] = 0;
                    $v['bankname'] = isset($config[$v['AccType']]) ? $config[$v['AccType']] : '';
                    foreach ($bank['SystemBankInfoList'] as $b) {
                        if ($v['AccNo'] != '' && $b['iBankID'] && $v['AccType'] == $b['iBankID']) {
                            $v['money'] = $b['iBalance'] / 1000;
                            $sum += $b['iBalance'] / 1000;
                            break;
                        }
                    }
                }
                unset($v);
                $res['data'][] = [
                    'bankname' => lang('总计'),
                    'money' => $sum,
                    'AccNo' => '',
                ];
            }
            return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total'], $other);
        }
        return $this->fetch();
    }


    //房间彩蛋金额
    public function luckyegg()
    {
        $res = Api::getInstance()->sendRequest([], 'system', 'LuckyEgg');
        if ($res['data']) {
            $sum = 0;
            foreach ($res['data'] as &$v) {
                $v['luckyeggmoney'] /= 1000;
                $sum += $v['luckyeggmoney'];
            }
            unset($v);
            $res['data'][] = [
                'roomname' => lang('总计'),
                'luckyeggmoney' => $sum,
            ];
        }
        return $this->apiReturn($res['code'], $res['data'], $res['message'], $res['total']);
    }


    //转账
    public function transfer()
    {
        if ($this->request->isAjax()) {
            $fromacc     = intval(input('fromacc'));
            $toacc   = intval(input('toacc'));
            $money  = input('amount') ? intval(input('amount')) : 0;

            $data = [
                'code' => 0,
                'msg'  => ''
            ];
            if (!$fromacc || !$toacc || !$money || $money<=0) {
                $data['code'] = 1;
                $data['msg'] = '参数有误';
                return json($data);
            }
            $money *= 1000;
            $socket = new QuerySocket();
            $res = $socket->sysBankDeal($fromacc, $toacc, $money);
            $code = isset($res['iResult']) ? $res['iResult'] : 10;
            if ($code == 0) {
                $msg = lang('转账成功');
            } else {
                $msg = lang('转账失败');
            }

            $data['code'] = $code;
            $data['msg']  = $msg;
            GameLog::logData(__METHOD__, $this->request->request(), (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
            return json($data);
        }
        $this->assign('banktype', config('site.bankType'));
        return $this->fetch();
    }

    //添加/修改银行账户
    public function addbank()
    {
        if ($this->request->isAjax()) {
            $account     = intval(input('account'));
            $toacc   = intval(input('toacc'));
            $res = Api::getInstance()->sendRequest([
                'AccNo' => $account,
                'AccType' => $toacc
            ], 'system', 'addsysbank');
            GameLog::logData(__METHOD__, $this->request->request(), (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
            return $this->apiReturn($res['code'], $res['data'], $res['message']);
        }
        $this->assign('banktype', config('site.bankType'));
        return $this->fetch();
    }

    public function editbank()
    {
        $accno = input('accno');
        $acctype = input('acctype');
        $this->assign('banktype', config('site.bankType'));
        $this->assign('account',$accno);
        $this->assign('type', $acctype);
        return $this->fetch();
    }


    //系统扩容
    public function addcapacity()
    {
        if ($this->request->isAjax()) {
            $money     = intval(input('money'));
           if (!$money || $money<=0) {
               return json(['code' => 1, 'msg' => '请输入正确的金额']);
           }
            $res = Api::getInstance()->sendRequest([
                'Capacity' => $money*1000,
                'typeid' => 2,
            ], 'system', 'addcapacity');
            GameLog::logData(__METHOD__, $this->request->request(), (isset($res['code']) && $res['code'] == 0) ? 1 : 0, $res);
            return $this->apiReturn($res['code'], $res['data'], $res['message']);
        }
        $this->assign('banktype', config('site.bankType'));
        return $this->fetch();
    }
}
