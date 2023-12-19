<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/5
 * Time: 22:12
 */

namespace vcpay;

class PaySdk
{


    public function payout($OrderNo, $order, $config = [])
    {
        if (!isset($order['PayWayType'])){
            $result['system_ref'] = '';
            $result['status'] = false;
            $result['message'] = '出款类型有误，请检查后在提交';
            return $result;
        }

        if ($order['PayWayType'] == 6) {
            //PHONE
            $identityType = 'PHONE';
            $identity = $order['CardNo'];
        } elseif ($order['PayWayType'] == 7) {
            //EMAIL
            $identityType = 'EMAIL';
            $identity = $order['CardNo'];
        } else {
            //CPF
            $identityType = 'CPF';//收款账户类型
            $identity = $order['Province'];
        }

        $appId = $config['app_id'] ?? '';
        $orderId = trim($OrderNo);
        $amount = sprintf('%.2f', $order['RealMoney']);
        $notifyUrl = $config['notify_url'] ?? '';
        $apiUrl = $config['api_url'] ?? '';
        $secret = $config['secret'] ?? '';
        $postData = [
            'app_id' => $appId,
            'nonce_str' => rand(10000000, 99999999) . sprintf('%.0f', floatval(explode(' ', microtime())[0]) * 1000),
            'trade_type' => $config['code'],
            'order_amount' => (int)$amount * 100,
            'out_trade_no' => $orderId,
            'notify_url' => $notifyUrl,
            'bank_code' => '000000',
            'bank_owner' => trim($order['RealName']),
            'bank_account' => $order['CardNo'],
            'identity_type' => $identityType,
            'identity' => $identity,
        ];

        $postData['sign'] = $this->createSign($postData, $secret);

        $header = [
            'Content-Type:application/json;charset=UTF-8',
        ];

        $result = $this->curl_post_content($apiUrl . '/wd/save', json_encode($postData), $header);

        save_log('vcpay', 'post:' . json_encode($postData) . ',output:' . $result);
        $res = json_decode($result, true);
        $result = ['system_ref' => '', 'message' => ''];
        if ($res) {
            if ($res['code'] == '200') {
                $result['system_ref'] = '';
                $result['status'] = true;
                $result['message'] = 'success';
            } else {
                $result['message'] = $res['msg'];
                $result['status'] = false;
            }
        } else {
            $result['system_ref'] = '';
            $result['status'] = false;
            $result['message'] = '订单同步回调失败';
        }
        return $result;
    }


    //签名函数
    private function createSign($data, $md5key)
    {
        ksort($data);
        $md5str = '';
        foreach ($data as $key => $val) {
            if (trim($val) !== '') {
                $md5str = $md5str . $key . '=' . $val . '&';
            }
        }
        $str = $md5str . 'key=' . $md5key;
        return strtoupper(md5($str));
    }

    //http请求函数
    private function curl_post_content($url, $data = null, $header = [])
    {
        $ch = curl_init();
        if (substr_count($url, 'https://') > 0) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        if (!empty($header)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }
}