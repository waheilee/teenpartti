<?php

namespace app\api\controller;

Class Notify extends CheckSignBase {
    
    private $UserDB;
    public function __construct() {
        parent::__construct();
        $this->UserDB = new \app\model\UserDB();
    }
    
    
    /**
     * 创建扫码支付订单
     * @param type $params
     */
    private function CreateScanPayOrder($params) {
        try {
            if (empty($params['AccountID'])) {
                throw new \Exception('用户id不能为空');
            }
            if (empty($params['RealMoney'])) {
                throw new \Exception('金额不能为空');
            }
            if (empty($params['TransactionNo'])) {
                throw new \Exception('第三方单号不能为空');
            }
            if (empty($params['OrderId'])) {
                throw new \Exception('系统单号不能为空');
            }
            if (empty($params['NickName'])) {
                throw new \Exception('第三方审核员昵称不能为空');
            }
            if (empty($params['ChannelID'])) {
                throw new \Exception('人工收款通道ID不能为空');
            }
            $time = date('Y-m-d', time());
            $status = $this->UserDB::SCAN_ORDER_STATUS_WAIT_AUDIT;
            $order = [
                'ChannelID' => $params['ChannelID'],
                'OrderId' => $params['OrderId'],
                'Status' => $status,
                'AccountID' => $params['AccountID'],
                'RealMoney' => $params['RealMoney'],
                'TransactionNo' => $params['TransactionNo'],
                'NickName' => $params['NickName'],
                'PayTime' => $params['PayTime']? $params['PayTime']:$time,
                'UpdateTIme' => $time,
            ];
//            $UserDB = new \app\model\UserDB('T_UserScanOrder');
//            $res = $UserDB->Tabladd($order);
            $res = $this->UserDB->GetUserScanOrderTable()->Insert($order);
            if (!$res) {
                throw new \Exception('请求方式错误');
            }
            return array('status'=> true, 'msg'=> '');     
        } catch (\Exception $ex) {
           return array('status'=>false, 'msg'=> $ex->getMessage());     
        }
    }
    
    /**
     * 创建通道订单
     * @return type
     * @throws \Exception
     */
    private function createChannelPayOrder($params) {
        try {
            if (empty($params['AccountID'])) {
                throw new \Exception('用户id不能为空');
            }
            if (empty($params['RealMoney'])) {
                throw new \Exception('金额不能为空');
            }
            if (empty($params['TransactionNo'])) {
                throw new \Exception('第三方单号不能为空');
            }
            if (empty($params['OrderId'])) {
                throw new \Exception('系统单号不能为空');
            }
            if (empty($params['ChannelID'])) {
                throw new \Exception('人工收款通道ID不能为空');
            }
            $time = date('Y-m-d H:i:s', time());
            $order = [
                'ChannelID' => $params['ChannelID'],
                'OrderId' => $params['OrderId'],
                'Status' => $this->UserDB::SCAN_ORDER_STATUS_WAIT_AUDIT,
                'AccountID' => $params['AccountID'],
                'RealMoney' => $params['RealMoney'],
                'AddTime' => $time,
//                'PayTime' => $params['PayTime']? $params['PayTime']:$time,
                'TransactionNo' => $params['TransactionNo'],
//                'NickName' => $params['NickName'],
            ];
            $res = $this->UserDB->GetUserChannelPayOrderTable()->Insert($order);
            if (!$res) {
                throw new \Exception('请求方式错误');
            }
            return array('status'=> true, 'msg'=> '');     
        } catch (\Exception $ex) {
           return array('status'=>false, 'msg'=> $ex->getMessage());     
        }
    }
    
    /**
     * 扫描支付通知生成订单后台审核
     */
    public function ScanPayNotify() {
        $logM = new \app\model\ApiLog();
        $log_data = [
            'visit_date' => time(),
            'log_type' => 'api',
            'params' => json_encode($_REQUEST),
            'controller' => 'Notify',
            'action' => 'ScanPayNotify',
            'is_success' => 1,
        ];
        
        // 开启事务
        $query = $this->UserDB->getTransDBConn();
        $query->startTrans();
        try {
            if ($this->request->isPost()) {
                $log_data['params'] = json_encode($_POST, true);
                // 验证请求数据
                $params['AccountID'] = $this->_post('AccountID', 0);
                $params['RealMoney'] = $this->_post('RealMoney', 0);
                $params['TransactionNo'] = $this->_post('TransactionNo', '');
                $params['NickName'] = $this->_post('NickName', '');
                if (empty($params['AccountID'])) {
                    throw new \Exception('用户id不能为空');
                }
                if (empty($params['RealMoney'])) {
                    throw new \Exception('金额不能为空');
                }
                if (empty($params['TransactionNo'])) {
                    throw new \Exception('第三方单号不能为空');
                }
                if (empty($params['NickName'])) {
                    throw new \Exception('第三方审核员昵称不能为空');
                }
                $log_data['account_id'] = $params['AccountID'];
                // 人工收款通道id
                $params['ChannelID'] = 30;
                // 生成系统订单号
                $date = date('YmdHis');
                $randCode = sprintf("%04d", rand(0, 9999));
                $params['OrderId'] = 'CFU' . $params['ChannelID'] . $date . $randCode;
                
                // 生成未付款状态的通道订单
                $ret = $this->createChannelPayOrder($params);
                if (!$ret['status']) {
                    throw new \Exception($ret['msg']);
                }
                
                $time = date('Y-m-d H:i:s', time());
                $params['PayTime'] = $this->_post('PayTime', $time);
                // 生成扫码支付的待审核的订单
                $res = $this->CreateScanPayOrder($params);
                if (!$res['status']) {
                    throw new \Exception($res['msg']);
                }
            } else {
                throw new \Exception('请求方式错误');
            }
            // 记录log日志
            $log_data['resp_time'] = time() - $log_data['visit_date'];
            $log_data['create_time'] = date('Y-m-d H:i:s', time());
            $logM->AddData($log_data);
            $query->commit();
            return $this->successJSON();
        } catch (\Exception $ex) {
            // 事务回滚
            $query->rollback();
            $message = $ex->getMessage();
            $log_data['resp_time'] = time() - $log_data['visit_date'];
            $log_data['create_time'] = date('Y-m-d H:i:s', time());
            $log_data['error_msg'] = $message;
            $log_data['is_success'] = 0;
            $logM->AddData($log_data);
            return $this->failJSON($message);
        }
    }
    
}
