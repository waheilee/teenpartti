<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/5
 * Time: 22:12
 */

namespace brpay;

class PaySdk
{

    public function payout($OrderNo, $order, $config = [])
    {



        $merchantId = $config['merchant'] ?? '';
        $secretKey = $config['secret'] ?? '';
        $apiUrl = $config['apiurl'] ?? '';
        $orderTradeNo = trim($OrderNo);
        $amount = sprintf('%.2f', $order['RealMoney']);
        $notifyUrl = $config['notify_url'] ?? '';
        $checkBalanceData = [
            'memberid' => $merchantId,
            'time' => date('Y-m-d H:i:s')
        ];
        $checkBalanceData['pay_md5sign'] = $this->createSign($checkBalanceData, $secretKey);
        $checkBalance = $this->curl_post_content($apiUrl . '/api/pay/transactions/balance', http_build_query($checkBalanceData), []);
        $balance = json_decode($checkBalance, true);
        if (!empty($balance) && $balance['code'] == 1){
            if ($balance['data']['amount'] < $amount){
                save_log('brpay', '商户余额不足-------output:' . $checkBalance);
                $result['message'] = '商户余额不足';
                $result['status'] = false;
                $result['balance'] = true;
                return $result;
            }
        }
        if ($order['PayWayType'] == 6) {
            //PHONE
            $pixType = 'PHONE';
            $pixKey = '+55' . $order['CardNo'];

        } elseif ($order['PayWayType'] == 7) {
            //EMAIL
            $pixType = 'EMAIL';
            $pixKey = $order['CardNo'];

        } else {
            //CPF
            $pixType = 'CPF';//收款账户类型
            $pixKey = $order['CardNo'];

        }

        $postData = [
            'memberid' => $merchantId,
            'out_trade_no' => $orderTradeNo,
            'amount' => $amount,
            'notifyurl' => $notifyUrl,
            'pix_type' => $pixType,
            'pix_key' => $pixKey,
        ];

        $postData['pay_md5sign'] = $this->createSign($postData, $secretKey);
        $header = [
//             'Content-Type: application/x-www-form-urlencoded;charset=utf-8',
        ];
        $result = $this->curl_post_content($apiUrl . '/api/pay/transactions/give', http_build_query($postData), $header);

        save_log('brpay', 'post:' . json_encode($postData) . ',output:' . $result);

        $res = json_decode($result, true);
        $result = ['system_ref' => '', 'message' => ''];
        if (isset($res) && $res['code'] == 1){
            $result['system_ref'] = '';
            $result['status'] = true;
            $result['message'] = 'success';
        }else{
            save_log('brpay', '代付同步回调失败订单:' . $orderTradeNo . '状态:' . $res['code']);
            $result['message'] = $res['msg'];
            $result['status'] = false;
            $result['pay_type'] = 'brpay';
        }

        return $result;
    }


    //签名函数
    private function createSign($data, $Md5key)
    {
        ksort($data);
        $md5str = '';
        foreach ($data as $key => $val) {
            if (trim($val) !== '') {
                $md5str = $md5str . $key . '=' . $val . '&';
            }
        }
        $str = $md5str . 'key=' . $Md5key;
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
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        save_log('brpay', 'http_code:' . json_encode($httpCode));
        curl_close($ch);
        return $res;
    }
}