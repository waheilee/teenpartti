<?php

namespace app\api\controller;

use think\Controller;
use app\common\GameLog;
use app\model\MasterDB;
use app\model\GameOCDB;
use app\model\BankDB;
use tgpay\PaySdk as TgPay;
use redis\Redis;
use think\Db;


use app\admin\controller\Playertrans;

class AutoWithdrawal extends Controller
{

    public function index()
    {
        $BankDB = new BankDB();
        $GameOCDB = new GameOCDB();

        $total_config = $GameOCDB->getTableObject('T_PayAutoConfig(NOLOCK)')->where('id', 1)->find();
        if ($total_config['Enable'] == 0) {
            //自动代付关闭
            die('系统已关闭自动代付');
        }
        $risk_config = $GameOCDB->getTableObject('T_PayRiskConfig(NOLOCK)')->where('RiskLevel', $total_config['RiskLevel'])->find();
        if (empty($risk_config)) {
            die('风险等级未配置');
        }
        //当日自动代付数量
        $today_draw_amount = $BankDB->getTableObject('UserDrawBack(NOLOCK)')->whereTime('UpdateTime', '>', date('Y-m-d'))->where('status', 1)->where('DrawType', 2)->sum('iMoney') ?: 0;

        save_log('auto_playertrans', '当日自动代付数量：' . $today_draw_amount);

        $record = $BankDB->getTableObject('UserDrawBack(NOLOCK)')->where('status', 0)->where('DrawType', 0)->order('AddTime asc')->limit('0,30')->select();

        save_log('auto_playertrans', '需处理数量：' . count($record));

        foreach ($record as $key => &$val) {

            $OrderNo = $val['OrderNo'];
            save_log('auto_playertrans', '开始处理：' . $OrderNo);
            echo $OrderNo;
            //加锁
            $key = 'lock_PayAgree_' . $OrderNo;
            if (!Redis::lock($key)) {
                // $BankDB->getTableObject('UserDrawBack')->where('OrderNo',$OrderNo)->data(['DrawType'=>1])->update();
                continue;
            }


            //总代付上限
            if (($today_draw_amount + $val['iMoney']) > ($total_config['MaxDailyQuote'] * bl)) {
                save_log('auto_playertrans', '当日自动代付达到上限：' . $today_draw_amount + $val['iMoney']);
                $BankDB->getTableObject('UserDrawBack')->where('OrderNo', $OrderNo)->data(['DrawType' => 1])->update();
                Redis::rm($key);
                //save_log('auto_playertrans','error1-----'.$val['AccountID'].'-----'.$OrderNo);
                continue;
            }

            //个人提款上限
            $today_person_draw_amount = $BankDB->getTableObject('UserDrawBack(NOLOCK)')->where('AccountID', $val['AccountID'])->whereTime('UpdateTime', '>', date('Y-m-d'))->where('status', 100)->where('DrawType', 2)->sum('iMoney') ?: 0;
            if (($today_person_draw_amount + $val['iMoney']) > ($total_config['UserDailyQuote'] * bl)) {
                $BankDB->getTableObject('UserDrawBack')->where('OrderNo', $OrderNo)->data(['DrawType' => 1])->update();
                Redis::rm($key);
                save_log('auto_playertrans', '个人提款上限：' . $val['AccountID'] . $today_person_draw_amount);
                //save_log('auto_playertrans','error2-----'.$val['AccountID'].'-----'.$OrderNo);
                continue;
            }


            //风险等级
            $SGD = $GameOCDB->getTableObject('T_GamePlayerWin')->where('RoleID', $val['AccountID'])->whereTime('adddate', '>=', date('Y-m-d', strtotime('-' . $risk_config['Days'] . ' days')))->sum('WinNum') ?: 0;
            if ($SGD > ($risk_config['TotalWin'] * bl)) {
                //风险系数高
                $BankDB->getTableObject('UserDrawBack')->where('OrderNo', $OrderNo)->data(['DrawType' => 1])->update();
                Redis::rm($key);
                //save_log('auto_playertrans','error3-----'.$val['AccountID'].'-----'.$OrderNo);
                save_log('auto_playertrans', '风险等级未通过：' . $val['AccountID'] . $SGD);
                continue;
            }


            //自动代付
            $admin = Db::table('game_user')->where('username', 'autodraw')->field('id,username,session_id')->find();
            $userID = $admin['id'];
            $channelid = $total_config['PayChannel'];

            $db = new MasterDB();
            $channel = $db->getTableRow('T_GamePayChannel', ['ChannelId' => $channelid], '*');

            $config = json_decode($channel['MerchantDetail'], true);
            $extra = json_encode(['channelid' => $channelid]);
            $channelcode = strtolower(trim($channel['ChannelCode']));
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
            var_dump($result);
            if ($result['status']) { //订单成功
                $today_draw_amount += $val['iMoney'];
                $bankM = new BankDB();
                $post_data = [
                    'ChannelId' => $channelid,
                    'TransactionNo' => $result['system_ref'],
                    'status' => $bankM::DRAWBACK_STATUS_THIRD_PARTY_HANDLING,
                    'IsDrawback' => $bankM::DRAWBACK_STATUS_THIRD_PARTY_HANDLING,
                    'UpdateTime' => date('Y-m-d H:i:s', time()),
                    'DrawType' => 2,
                    'checkUser' => $admin['username'],
                    'checkTime' => date('Y-m-d H:i:s', time())
                ];
                $ret = $bankM->updateTable('userdrawback', $post_data, ['OrderNo' => $OrderNo, 'status' => $bankM::DRAWBACK_STATUS_WAIT_PAY]);
                if (!$ret) {
                    save_log('auto_playertrans', '提交三方成功，更新订单状态失败：' . $OrderNo);
                    continue;
                }
                save_log('auto_playertrans', '订单提交成功：' . $OrderNo);
                GameLog::logData(__METHOD__, [$userID, $OrderNo, $channelcode, lang('提交第三方成功')], 1, lang('提交第三方成功'));
                Redis::rm($key);
                continue;
            } else {
                $BankDB->getTableObject('UserDrawBack')->where('OrderNo', $OrderNo)->data(['DrawType' => 1])->update();
                GameLog::logData(__METHOD__, [$userID, $OrderNo, $channelcode, $result['message']], 1, $result['message']);
                save_log('auto_playertrans', '订单提交失败：' . $OrderNo);
                Redis::rm($key);
                continue;
            }

            //模拟后台登陆
//            $admin = Db::table('game_user')->where('username', 'autodraw')->field('id,username,session_id')->find();
//            if (empty($admin)) {
//                Redis::rm($key);
//                continue;
//            }
//            session('username', $admin['username']);
//            session('userid', $admin['id']);
//            session('session_start_time', time());//记录会话开始时间！判断会话时间的重点！重点！重点！
//            save_log('auto_playertrans','session设置完毕：'.$val['AccountID']);
//           // session_id(session_id());
//
//            //构造ajax调用
//            request()->setParam(config('var_ajax'),true);
//            request()->setMethod('POST');
//            //构造参数
//            request()->setParam('UserID',$val['AccountID']);
//            request()->setParam('OrderNo',$val['OrderNo']);
//            request()->setParam('channelid',$total_config['PayChannel']);
//            request()->setParam('checkUser',$admin['username']);
//            save_log('auto_playertrans','设置发送数据：'.$admin['username'].'||'.$val['OrderNo'].'||'.$total_config['PayChannel']);
//            $res = (new Playertrans())->thirdPay()->getData();
//            save_log('auto_playertrans','返回数据：'.json_encode($res,320));
//            if ($res['code'] != 0) {
//                $BankDB->getTableObject('UserDrawBack')->where('OrderNo',$OrderNo)->data(['DrawType'=>1])->update();
//                Redis::rm($key);
//                continue;
//            } else {
//                $today_draw_amount += $val['iMoney'];
//                $BankDB->getTableObject('UserDrawBack')->where('OrderNo',$OrderNo)->data(['DrawType'=>2])->update();
//                Redis::rm($key);
//                continue;
//            }
            //解除锁
            Redis::rm($key);
        }
        echo('SUCCESS');
    }

    /**
     * 根据配置金额判断是否自动出金
     * @return void
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function getConfigAmountAutoWithdrawal()
    {
        save_log('auto_playertrans', '----自动提现开始----');
        $bankDB = new BankDB();
        $masterDB = new MasterDB();
        $channelId = 1951; //目前支出通道有两个，1951、1953
        $adminUserId = 0;//从MySQL里game_user表里拿到信息
//        $configAmount = config('withdrawal_amount');//配置金额
        $configAmount = $masterDB->getTableObject('T_GameConfig')
            ->where('CfgType', 301)
            ->find();//配置金额
        if (!empty($configAmount['CfgValue'])) {
            $amount = bcmul($configAmount['CfgValue'], bl);
            save_log('auto_playertrans', '自动提现金额：' . $amount);
            //出金待审核订单，并且订单金额大于等于配置金额
            $waitDrawOrder = $bankDB->getTableObject('UserDrawBack')
                ->where('status', 0)
                ->where('iMoney', '<=', $amount)
                ->select();
            //获取出金通道
            $channel = $masterDB->getTableRow('T_GamePayChannel', [
                'ChannelId' => $channelId
            ], '*');
            //三方商家配置详情
            $merchantDetail = json_decode($channel['MerchantDetail'], true);
            //支付通道配置名称。目前使用的都是tgpay
            $channelCode = strtolower(trim($channel['ChannelCode']));
            foreach ($waitDrawOrder as $key => &$order) {
                $OrderNo = $order['OrderNo'];
                $key = 'lock_PayAgree_' . $OrderNo;
                if (!Redis::lock($key)) {
                    continue;
                }
                $order['RealMoney'] = FormatMoney($order['iMoney'] - $order['Tax']);//格式化金额
                save_log('auto_playertrans', '自动提现订单号：' . $OrderNo . '---订单金额：' . $order['RealMoney']);
                //参数提交三方支付
                $result = $this->commitThirdPay($channelCode, $order, $merchantDetail);

                //结果返回判断
                if ($result['status']) { //订单成功
                    $post_data = [
                        'ChannelId' => $channelId,
                        'TransactionNo' => $result['system_ref'],
                        'status' => $bankDB::DRAWBACK_STATUS_THIRD_PARTY_HANDLING,
                        'IsDrawback' => $bankDB::DRAWBACK_STATUS_THIRD_PARTY_HANDLING,
                        'UpdateTime' => date('Y-m-d H:i:s', time()),
                        'DrawType' => 2,
                        'checkUser' => 'autodraw',
                        'checkTime' => date('Y-m-d H:i:s', time())
                    ];
                    $ret = $bankDB->updateTable('userdrawback', $post_data, [
                        'OrderNo' => $OrderNo,
                        'status' => $bankDB::DRAWBACK_STATUS_WAIT_PAY
                    ]);
                    if (!$ret) {
                        save_log('auto_playertrans', '提交三方成功，更新订单状态失败：' . $OrderNo);
                        continue;
                    }
                    save_log('auto_playertrans', '订单提交成功：' . $OrderNo);

                    GameLog::logData(__METHOD__, [
                        $adminUserId, $OrderNo, $channelCode, lang('提交第三方成功')
                    ], 1, lang('提交第三方成功'));
                } else {
                    $bankDB->getTableObject('UserDrawBack')
                        ->where('OrderNo', $OrderNo)
                        ->data(['DrawType' => 1])
                        ->update();

                    GameLog::logData(__METHOD__, [
                        $adminUserId, $OrderNo, $channelCode, $result['message']
                    ], 1, $result['message']);
                    save_log('auto_playertrans', '订单提交失败：' . $OrderNo);
                }
                Redis::rm($key);
                continue;
            }
            echo('SUCCESS');
        }
        save_log('auto_playertrans', '----自动提现开始结束----');
    }

    /**
     * 提交三方支付
     * @param $channelCode
     * @param $order
     * @param $merchantDetail
     * @return array|mixed|string[]
     */
    public function commitThirdPay($channelCode, $order, $merchantDetail)
    {
        $orderNo = $order['OrderNo'];
        $result = [];
        switch ($channelCode) {
            case 'tgpay':
                $tgpay = new TgPay();
                if (config('app_type') == 2) {
                    $result = $tgpay->payoutBrazil($orderNo, $order, $merchantDetail);
                    save_log('auto_playertrans', '订单三方返回：' . json_encode($result));
                }
                break;
            default:
                $class = '\\' . strtolower($channelCode) . '\PaySdk';
                $pay = new $class();
                $result = $pay->payout($orderNo, $order, $merchantDetail);
                break;
        }
        return $result;
    }
}