<?php

namespace app\api\controller;

use think\Controller;
use app\common\GameLog;
use app\model\MasterDB;
use app\model\GameOCDB;
use app\model\BankDB;
use peakpay\PaySdk as TgPay;
use redis\Redis;
use think\Db;


use app\admin\controller\Playertrans;

class ChannelAutoDraw extends Controller
{

    public function index()
    {
        $BankDB = new BankDB();
        $GameOCDB = new GameOCDB();
        $masterDB = new MasterDB();
        $paychannel = $masterDB->getTableObject('T_GamePayChannel(NOLOCK)')->where('type', 1)->limit(1)->select();

        if (empty($paychannel[0])) {
            die('没有支付通道');
        }

        $withedata = $GameOCDB->getTableObject('T_AutoWithdrawalWhiteList(NOLOCK)')->field('RoleId,DayTop')->select();
        if (empty($withedata)) {
            die('没有白名单玩家');
        }
        $withelist = [];
        $daytop = [];
        foreach ($withedata as $k => $v) {
            array_push($withelist, $v['RoleId']);
            $daytop[$v['RoleId']] = $v['DayTop'];
        }

        $payconfig = $paychannel[0];
        $record = $BankDB->getTableObject('UserDrawBack(NOLOCK)')
            ->where('status', 0)
            ->where('DrawType', 0)
            ->order('AddTime asc')
            ->limit('0,30')
            ->select();

        if(empty($record)){
            die('没有订单');
        }


        save_log('auto_playertrans', '需处理数量：' . count($record));
        foreach ($record as $key => &$val) {
            $OrderNo = $val['OrderNo'];
            save_log('auto_playertrans', '开始处理：' . $OrderNo);
            //echo $OrderNo;
            //加锁
            $key = 'lock_PayAgree_' . $OrderNo;
            if (!Redis::lock($key)) {
                // $BankDB->getTableObject('UserDrawBack')->where('OrderNo',$OrderNo)->data(['DrawType'=>1])->update();
                continue;
            }

            if (!in_array($val['AccountID'], $withelist)) {
                $BankDB->getTableObject('UserDrawBack')->where('OrderNo', $OrderNo)->data(['DrawType' => 1])->update();
                continue;
            }
            $channel_drawlimit = $GameOCDB->getTableObject('T_OperatorQuotaManage')->where('OperatorId', $val['OperatorId'])->value('AutoWithdrawalLimit');

            if (!$channel_drawlimit) {
                $BankDB->getTableObject('UserDrawBack')->where('OrderNo', $OrderNo)->data(['DrawType' => 1])->update();
                continue;
            }

            $paymoney = FormatMoney($val['iMoney']);
            if ($paymoney > $channel_drawlimit) {
                $BankDB->getTableObject('UserDrawBack')->where('OrderNo', $OrderNo)->data(['DrawType' => 1])->update();
                continue;
            }
            //单日出款限额
            $today_num = $BankDB->getTableObject('UserDrawBack')->where('AccountID',$val['AccountID'])->where('status',100)->whereTime('AddTime','>=',date('Y-m-d').' 00:00:00')->sum('iMoney')?:0;
            $today_num = ($today_num + $val['iMoney'])/bl;
            if($daytop[$val['AccountID']]>0 && $today_num > $daytop[$val['AccountID']]){
                $BankDB->getTableObject('UserDrawBack')->where('OrderNo', $OrderNo)->data(['DrawType' => 1])->update();
                continue;
            }

            $config = json_decode($payconfig['MerchantDetail'], true);
            $channelcode = strtolower(trim($payconfig['ChannelCode']));
            $result = [];
            $val['RealMoney'] = FormatMoney($val['iMoney'] - $val['Tax']);

            switch ($channelcode) {
                case 'tgpay':
                    $tgpay = new TgPay();
                    if (config('app_type') == 2) {
                        $result = $tgpay->payoutBrazil($OrderNo, $val, $config);
                        save_log('auto_playertrans', '订单三方返回：' . json_encode($result));
                    }
                    break;
                default:
                    $class = '\\' . strtolower($channelcode) . '\PaySdk';
                    $pay = new $class();
                    $result = $pay->payout($OrderNo, $val, $config);
                    break;
            }
           // var_dump($result);
            if ($result['status']) { //订单成功
                $bankDBB = new BankDB();
                $post_data = [
                    'ChannelId' => $payconfig['ChannelId'],
                    'TransactionNo' => $result['system_ref'],
                    'status' => $bankDBB::DRAWBACK_STATUS_THIRD_PARTY_HANDLING,
                    'IsDrawback' => $bankDBB::DRAWBACK_STATUS_THIRD_PARTY_HANDLING,
                    'UpdateTime' => date('Y-m-d H:i:s', time()),
                    'DrawType' => 2,
                    'checkUser' => 'autodraw',
                    'checkTime' => date('Y-m-d H:i:s', time())
                ];
                $ret = $bankDBB->getTableObject('userdrawback')->where(['OrderNo' => $OrderNo, 'status' => $bankDBB::DRAWBACK_STATUS_WAIT_PAY])->data($post_data)->update();
                if (!$ret) {
                    save_log('auto_playertrans', '提交三方成功，更新订单状态失败：' . $OrderNo);
                    continue;
                }
                save_log('auto_playertrans', '订单提交成功：' . $OrderNo);
                GameLog::logData(__METHOD__, [1, $OrderNo, $channelcode, lang('提交第三方成功')], 1, lang('提交第三方成功'));
                Redis::rm($key);
                continue;
            } else {
                $Bankobj = new BankDB();
                $Bankobj->getTableObject('UserDrawBack')->where('OrderNo', $OrderNo)->data(['DrawType' => 1])->update();
                continue;
                GameLog::logData(__METHOD__, [1, $OrderNo, $channelcode, $result['message']], 1, $result['message']);
                save_log('auto_playertrans', '订单提交失败：' . $OrderNo);
                Redis::rm($key);
                continue;
            }
            Redis::rm($key);
        }
        echo('SUCCESS');
    }
}