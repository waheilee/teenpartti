<?php

/* 
 * 扫码支付lib
 */

namespace app\admin\lib;

use app\admin\lib\BaseLib;

class ScanPayLib extends BaseLib{
    
    // 人工收款接口API_KEY
    protected $apikey = "zWnTJ63Lpwz5GL6Co79X5vQO40Thztal";
    // 三方拒单接口地址
    private $ThirdServerUrl = "";
    
    Const FTPAY_URL = "";
    public function __construct() {
        // sql server 数据库
        $app_status = config('app_status');
        $this->ThirdServerUrl = config('rgskurl');
//        if (strtolower($app_status) == 'dev') {
//
//        }
    }
    
    
    /**
     * 格式化参数格式化成url参数(字典排序后重组)
     */
    private function toUrlParams($paramData) {
        $buff = '';
        foreach ($paramData as $k => $v) {
            if ($v != '' && !is_array($v)) {
                if (preg_match('/[\x80-\xff]./', $v))
                    $buff .= $k . '=' . urldecode($v) . '&';
                else
                    $buff .= $k . '=' . $v . '&';
            }
        }
        $buff = trim($buff, '&');
        return $buff;
    }
    
    /**
     * 生成签名     
     */
    public function makeSign($paramData) {
//        return strtoupper(md5($this->paramData['t'] . $this->apikey));
        $paramData['api_key'] = $this->apikey;  //对应平台的api_key
        ksort($paramData);
//        dump($this->paramData);
        $string = $this->toUrlParams($paramData);
//        dump($string);
        $string = md5($string);
        //签名2：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }
    
    /**
     * 审核通道订单 
     * todo 添加sqlsrv事务
     * @param type $id 扫码支付订单id
     * @return type
     * @throws \Exception
     */
    public function examineChannelPayOrder($id) {
        $gameocDB = new \app\model\GameOCDB();
        $notify_table = $gameocDB->GetPayNotifyLogTable();
        $notify_log_data = [
            'Controller' => 'ScanPayLib',
            'Method' => __METHOD__,
            'AddTime' => date('Y-m-d H:i:s', time())
        ];
        $UserDB = new \app\model\UserDB();
        // 开启事务
        $query = $UserDB->getTransDBConn();
        $query->startTrans();
        try {
            // 扫码支付订单
            $order = $UserDB->GetUserScanOrderTable()->GetRow(['Id' => $id], '*');
            if ($order['Status'] != $UserDB::SCAN_ORDER_STATUS_WAIT_AUDIT) {
                throw new \Exception('【' . $order['OrderId'] . '】扫码订单非待审核状态');
            }
            $notify_log_data['OrderId'] = $order['OrderId'];
            
            // 判断T_UserChannelPayOrder订单是否存在
            $channel_order = $UserDB->GetUserChannelPayOrderTable()->GetRow(['OrderId' => $order['OrderId']]);
            if (empty($channel_order)) {
                throw new \Exception('【' . $order['OrderId'] . '】通道订单获取失败');
            }
            if ($channel_order['Status'] == $UserDB::CHANNEL_ORDER_STATUS_ORDER_COMPLETED) {
                throw new \Exception('【' . $order['OrderId'] . '】通道订单已完成');
            }
            
            // 获取通道
            $MasterDB = new \app\model\MasterDB();
            $channel = $MasterDB->getTableRow('T_GamePayChannel', ['ChannelId' => $order['ChannelID']]);
            $merchant_detail = json_decode($channel['MerchantDetail'], true);
            
            if ($order['RealMoney'] < $channel['MinMoney']) {
                throw new \Exception('【' . $order['OrderId'] . '】通道订单金额不能低于'.$channel['MinMoney']);
            }
            if ($order['RealMoney'] > $channel['MaxMoney']) {
                throw new \Exception('【' . $order['OrderId'] . '】通道订单金额不能高于'.$channel['MaxMoney']);
            }
            
            // 更新扫码订单  事务一开始审核就更新扫码订单状态保证审核只成功一次
            $result = $UserDB->GetUserScanOrderTable()->UPData(['Status' => $UserDB::SCAN_ORDER_STATUS_AUDITED], ['Id' => $order['Id'], 'Status' => $UserDB::SCAN_ORDER_STATUS_WAIT_AUDIT]);
            if (!$result) {
                throw new \Exception('【' . $order['OrderId'] . '】扫码订单信息更新失败');
            }

            // 转换充值金额对应金币比例
            $money = $order['RealMoney'] * bl; // 实际金币
            $sendQuery = new \socket\sendQuery();
            // 服务端请求参数
            $params = [$order['AccountID'], $order['ChannelID'], $order['TransactionNo'], $merchant_detail['currency'], $order['RealMoney'], $money];
            
            // 日志log记录的参数
            $log_params = [
                'order'=>$order,
                'sendQueryParams' => $params,
            ];
            $notify_log_data['Parameter'] = json_encode($log_params, 256);

            $res = $sendQuery->callback('CMD_MD_USER_CHANNEL_RECHARGE_N', $params);
            $code = unpack("LCode", $res)['Code'];
            // 通道订单更新where条件
            $update_where = ['OrderId' => $order['OrderId'], 'Status' => ['neq', $UserDB::CHANNEL_ORDER_STATUS_ORDER_COMPLETED]];
            if (intval($code) === 0) {
                // 订单充值成功
                $update_data = [
                    'PayTime' => $order['PayTime'],
                    'Status' => $UserDB::CHANNEL_ORDER_STATUS_ORDER_COMPLETED,
                    'TransactionNo' => $order['TransactionNo'],
                ];
                $ret = $UserDB->updateTable('T_UserChannelPayOrder', $update_data, $update_where);
                if (!$ret) {
                    throw new \Exception('【' . $order['OrderId'] . '】订单充值成功, T_UserChannelPayOrder表订单信息更新失败');
                }
                $notify_table->insert($notify_log_data);
                // 事务提交
                $query->commit();
                return array('status' => true);
                
            } else {
                // 上分失败
                // 1. 更新通道订单状态为上分失败
                $update_data = [
                    'PayTime' => $order['PayTime'],
                    'Status' => $UserDB::CHANNEL_ORDER_STATUS_COINS_WAIT_RELEASE,
                    'TransactionNo' => $order['TransactionNo'],
                ];
                $ret = $UserDB->updateTable('T_UserChannelPayOrder', $update_data, $update_where);
                if (!$ret) {
                    throw new \Exception('【' . $order['OrderId'] . '】上分失败, T_UserChannelPayOrder表订单信息更新失败. ERROR_CODE='. $code);
                }
                
                // 2. 更新扫码订单  将订单回退到待审核状态  (上分失败退回状态 审核人员可重新发起审核)
                // 注: 这订单状态先置为完成失败回退待审核状态一过程是为保证同一时刻多次审核同一扫码订单是只能一次请求服务器上分
                $rollback_res = $UserDB->GetUserScanOrderTable()->UPData(['Status' => $UserDB::SCAN_ORDER_STATUS_WAIT_AUDIT], ['Id' => $order['Id']]);
                if (!$rollback_res) {
                    throw new \Exception('【' . $order['OrderId'] . '】扫码订单状态回退失败');
                }
                // 记录本次审核上分失败记录
                $notify_log_data['Error'] = '上分失败, ERROR_CODE='. $code;
                $notify_table->insert($notify_log_data);
                
                // 事务提交
                $query->commit();
                return array('status' => FALSE, 'msg' => '上分失败, ERROR CODE=' . $code . ' money='.$money);
            }
        } catch (\Exception $ex) {
            // 事务回滚
            $query->rollback();
            $msg = $ex->getMessage();
            $notify_log_data['Error'] = $msg;
            $notify_table->insert($notify_log_data);
            return array('status'=>false, 'msg'=> $msg);     
        }
    }
    
    /**
     * 审核扫码订单时拒绝
     * @return type
     * @throws \Exception
     */
    public function rejectScanPayOrder($id) {
        $gameocDB = new \app\model\GameOCDB();
        $notify_table = $gameocDB->GetPayNotifyLogTable();
        $notify_log_data = [
            'Controller' => 'ScanPayLib',
            'Method' => __METHOD__,
            'AddTime' => date('Y-m-d H:i:s', time())
        ];
        $UserDB = new \app\model\UserDB();
        // 开启事务
        $query = $UserDB->getTransDBConn();
        $query->startTrans();
        try {
            // 扫码支付订单
            $order = $UserDB->GetUserScanOrderTable()->GetRow(['Id' => $id], '*');
            if ($order['Status'] != $UserDB::SCAN_ORDER_STATUS_WAIT_AUDIT) {
                throw new \Exception('【' . $order['OrderId'] . '】扫码订单非待审核状态');
            }
            $notify_log_data['OrderId'] = $order['OrderId'];
            
            // 更新扫码订单状态为反审核状态
            $result = $UserDB->GetUserScanOrderTable()->UPData(['Status' => $UserDB::SCAN_ORDER_STATUS_REVERSE_AUDIT], ['Id' => $order['Id'], 'Status' => $UserDB::SCAN_ORDER_STATUS_WAIT_AUDIT]);
            if (!$result) {
                throw new \Exception('【' . $order['OrderId'] . '】扫码订单信息更新失败');
            }

            // 通道订单更新where条件
            $update_where = ['OrderId' => $order['OrderId'], 'Status' => ['neq', $UserDB::CHANNEL_ORDER_STATUS_ORDER_COMPLETED]];
            $update_data = [
                'PayTime' => $order['PayTime'],
                'Status' => $UserDB::CHANNEL_ORDER_STATUS_REJECT_ISSUE_COINS,
                'TransactionNo' => $order['TransactionNo'],
            ];
            $ret = $UserDB->updateTable('T_UserChannelPayOrder', $update_data, $update_where);
            if (!$ret) {
                throw new \Exception('【' . $order['OrderId'] . '】,T_UserChannelPayOrder表订单信息更新失败');
            }
            // 请求三方拒绝订单
            $request_data = [
                't' => sysTime(),
                'order_code' => $order['TransactionNo'], // 三方单号
            ];
            $request_data['sign'] = $this->makeSign($request_data);
            $lib = new \app\admin\lib\HttpLib();
            if (empty($this->ThirdServerUrl)) {
                throw new \Exception("请检查是否是测试环境,或尚未配置三方接口地址");
            }
            $res = $lib->http($this->ThirdServerUrl, $request_data);
            if (!$res['status']) {
                throw new \Exception($res['msg']);
            }
            $respone_data = $res['data'];
            if (!$respone_data['success']) {
                throw new \Exception('拒绝失败, 三方返回信息: ' . $respone_data['message']);
            }
            
            $notify_table->insert($notify_log_data);
            // 事务提交
            $query->commit();
            return array('status' => true);
        } catch (\Exception $ex) {
            // 事务回滚
            $query->rollback();
            $msg = $ex->getMessage();
            $notify_log_data['Error'] = $msg;
            $notify_table->insert($notify_log_data);
            return array('status'=>false, 'msg'=> $msg);     
        }
    }
}