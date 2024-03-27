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

class AutoWithdrawal extends Controller
{

    public function index(){
        $BankDB = new BankDB();
        $GameOCDB = new GameOCDB();
        
        $total_config = $GameOCDB->getTableObject('T_PayAutoConfig(NOLOCK)')->where('id',1)->find();
        if ($total_config['Enable'] == 0) {
            //自动代付关闭
            die('系统已关闭自动代付');
        }
        $risk_config = $GameOCDB->getTableObject('T_PayRiskConfig(NOLOCK)')->where('RiskLevel',$total_config['RiskLevel'])->find();
        if (empty($risk_config)) {
            die('风险等级未配置');
        }
        //当日自动代付数量
        $today_draw_amount = $BankDB->getTableObject('UserDrawBack(NOLOCK)')->whereTime('UpdateTime','>',date('Y-m-d'))->where('status',1)->where('DrawType',2)->sum('iMoney') ?: 0;

        save_log('auto_playertrans','当日自动代付数量：'.$today_draw_amount);

        $record =  $BankDB->getTableObject('UserDrawBack(NOLOCK)')->where('status',0)->where('DrawType',0)->order('AddTime asc')->limit('0,30')->select();

        save_log('auto_playertrans','需处理数量：'.count($record));

        foreach ($record as $key => &$val) {

            $OrderNo = $val['OrderNo'];
            save_log('auto_playertrans','开始处理：'.$OrderNo);
            echo $OrderNo;
            //加锁
            $key = 'lock_PayAgree_' . $OrderNo;
            if (!Redis::lock($key)){
                // $BankDB->getTableObject('UserDrawBack')->where('OrderNo',$OrderNo)->data(['DrawType'=>1])->update();
                continue;
            }


            //总代付上限
            if (($today_draw_amount + $val['iMoney']) > ($total_config['MaxDailyQuote'] * bl)) {
                save_log('auto_playertrans','当日自动代付达到上限：'.$today_draw_amount + $val['iMoney']);
                $BankDB->getTableObject('UserDrawBack')->where('OrderNo',$OrderNo)->data(['DrawType'=>1])->update();
                Redis::rm($key);
                //save_log('auto_playertrans','error1-----'.$val['AccountID'].'-----'.$OrderNo);
                continue;
            }

            //个人提款上限
            $today_person_draw_amount = $BankDB->getTableObject('UserDrawBack(NOLOCK)')->where('AccountID',$val['AccountID'])->whereTime('UpdateTime','>',date('Y-m-d'))->where('status',100)->where('DrawType',2)->sum('iMoney') ?: 0;
            if (($today_person_draw_amount + $val['iMoney']) > ($total_config['UserDailyQuote'] * bl)) {
                $BankDB->getTableObject('UserDrawBack')->where('OrderNo',$OrderNo)->data(['DrawType'=>1])->update();
                Redis::rm($key);
                save_log('auto_playertrans','个人提款上限：'.$val['AccountID'].$today_person_draw_amount);
                //save_log('auto_playertrans','error2-----'.$val['AccountID'].'-----'.$OrderNo);
                continue;
            }

 
            //风险等级
            $SGD = $GameOCDB->getTableObject('T_GamePlayerWin')->where('RoleID',$val['AccountID'])->whereTime('adddate','>=',date('Y-m-d',strtotime('-'.$risk_config['Days'].' days')))->sum('WinNum')?:0;
            if($SGD > ($risk_config['TotalWin'] * bl)){
                //风险系数高
                $BankDB->getTableObject('UserDrawBack')->where('OrderNo',$OrderNo)->data(['DrawType'=>1])->update();
                Redis::rm($key);
                //save_log('auto_playertrans','error3-----'.$val['AccountID'].'-----'.$OrderNo);
                save_log('auto_playertrans','风险等级未通过：'.$val['AccountID'].$SGD );
                continue;
            }


            //自动代付
            $admin = Db::table('game_user')->where('username', 'autodraw')->field('id,username,session_id')->find();
            $userID = $admin['id'];
            $channelid=$total_config['PayChannel'];

            $db = new MasterDB();
            $channel = $db->getTableRow('T_GamePayChannel', ['ChannelId' => $channelid], '*');

            $config = json_decode($channel['MerchantDetail'], true);
            $extra = json_encode(['channelid' => $channelid]);
            $channelcode =strtolower(trim($channel['ChannelCode']));
            $result = [];
            $val['RealMoney'] = FormatMoney($val['iMoney'] - $val['Tax']);

            switch ($channelcode) {
                case 'tgpay':
                    $tgpay = new TgPay();
                    if (config('app_type') == 2) {
                        $result = $tgpay->payoutBrazil($OrderNo, $val, $config);
                        save_log('auto_playertrans','订单三方返回：'.json_encode($result));
                    }
                    break;
                default:
                    $class = '\\'.strtolower($channelcode).'\PaySdk';
                    $pay = new $class();
                    $result =$pay->payout($OrderNo, $val, $config);
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
                    'DrawType'=>2,
                    'checkUser' =>$admin['username'],
                    'checkTime'=>date('Y-m-d H:i:s', time())
                ];
                $ret = $bankM->updateTable('userdrawback', $post_data, ['OrderNo' => $OrderNo, 'status' => $bankM::DRAWBACK_STATUS_WAIT_PAY]);
                if (!$ret) {
                    save_log('auto_playertrans','提交三方成功，更新订单状态失败：'.$OrderNo );
                    continue;
                }
                save_log('auto_playertrans','订单提交成功：'.$OrderNo );
                GameLog::logData(__METHOD__, [$userID, $OrderNo, $channelcode, lang('提交第三方成功')], 1, lang('提交第三方成功'));
                Redis::rm($key);
                continue;
            } else {
                $BankDB->getTableObject('UserDrawBack')->where('OrderNo',$OrderNo)->data(['DrawType'=>1])->update();
                GameLog::logData(__METHOD__, [$userID, $OrderNo, $channelcode, $result['message']], 1, $result['message']);
                save_log('auto_playertrans','订单提交失败：'.$OrderNo );
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
        echo( 'SUCCESS');
    }
}