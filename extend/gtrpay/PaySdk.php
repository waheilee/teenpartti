<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/5
 * Time: 22:12
 */

namespace gtrpay;

class PaySdk
{

    private $api_url = '';
    private $notify_url = '';
    private $merchant = '';
    private $appid = '';
    private $secret = '';


    public function __construct()
    {
        $this->api_url = '';
        $this->merchant = '';
        $this->appid = '';
        $this->secret = '';
    }


    public function payout($OrderNo, $order, $config = [])
    {
        if (empty($config)) {
            return array('status' => FALSE, 'message' => 'Missing parameter');
        }
        if (!empty($config['merchant'])) {
            $this->merchant = $config['merchant'];
        }
        if (!empty($config['appid'])) {
            $this->appid = $config['appid'];
        }
        if (!empty($config['secret'])) {
            $this->secret = $config['secret'];
        } else {
            $this->secret = '';
        }
        if (!empty($config['apiurl'])) {
            $this->api_url = $config['apiurl'];
        } else {
            $this->api_url = '';
        }
        if (empty($config['notify_url'])) {
            return array('status' => FALSE, 'message' => 'Missing notify_url');
        }


        $merchant = $this->merchant;
        $appid = $this->appid;
        $orderId = trim($OrderNo);
        $amount = sprintf('%.2f', $order['RealMoney']);
        $notify_url = trim($config['notify_url']);
        $mobile = rand(6, 9) . rand(100000000, 999999999);
        $username = chr(rand(65, 90)) . chr(rand(97, 122)) . chr(rand(97, 122)) . chr(rand(97, 122)) . chr(rand(97, 122)) . chr(rand(97, 122));
        $email = $mobile . '@gmail.com';
        $RealName = trim($order['RealName']);
        $timestamp = time();

        $postData = [
            'mchId' => $merchant,
            'passageId' => 101,
            'orderAmount' => (float)$amount,
            'orderNo' => $orderId,
            'account' => $order['CardNo'] ?? '',
            'userName' => $RealName,
            'remark' => 'CPF',//（CPF.个人代付,PHONE.手机号代付,EMAIL.邮箱代付）
            'number' => $order['CardNo'] ?? '',
            'email' => $order['City'] ?? '',
            'notifyUrl' => $notify_url,
        ];

        $postData['sign'] = $this->createSign($postData, $this->secret);
        $header = [
            'Content-Type: application/json',
        ];

        $result = $this->curlPostContent($this->api_url . '/pay/create', json_encode($postData), $header);
        save_log('gtrpay', 'post:' . json_encode($postData) . ',output:' . $result);
        $res = json_decode($result, true);

        $result = [];
        $result['system_ref'] = '';
        if (isset($res) && $res['code'] == 200 && $res['msg'] == 'success') {
            $result['status'] = true;
            $result['message'] = 'success';
        } else {
            $result['status'] = false;
            $result['message'] = 'error';
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
        return md5($str);
    }

    //http请求函数
    private function curlPostContent($url, $data = null, $header = [])
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