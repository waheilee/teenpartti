<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/5
 * Time: 22:12
 */

namespace xpay;

class PaySdk
{

    private $api_url = '';
    private $notify_url = '';
    private $appid = '';
    private $secret = '';


    public function __construct()
    {
        $this->api_url = '';
        $this->merchant = '';
        $this->secretkey = '';
    }


    public function payout($OrderNo, $order, $config = [])
    {
        if (empty($config)) {
            return array('status' => FALSE, 'message' => 'Missing parameter');
        }
        if (isset($config['merchant']) && !empty($config['merchant'])) {
            $this->merchant = $config['merchant'];
        }

        if (isset($config['appid']) && !empty($config['appid'])) {
            $this->appid = $config['appid'];
        }

        if (isset($config['secret']) && !empty($config['secret'])) {
            $this->secret = $config['secret'];
        } else {
            $this->secret = '';
        }
        if (isset($config['apiurl']) && !empty($config['apiurl'])) {
            $this->api_url = $config['apiurl'];
        } else {
            $this->api_url = '';
        }
        if (!isset($config['notify_url']) || empty($config['notify_url'])) {
            return array('status' => FALSE, 'message' => 'Missing notify_url');
        }

        $type = 'UPI';
        if ($order['PayWay'] == 2) {
            $type = 'BANK';
        }

        $postdata = [
            'mchNo' => $this->merchant,
            'appId' => $this->appid,
            'mchOrderNo' => $OrderNo,
            'bankCode' => $type,
            'orderAmount' => $order['RealMoney'],
            'currency' => $config['currency'],
            'accountName' => trim($order['RealName']),
            'accountEmail' => 'huolong@gmail.com',
            'accountMobileNo' => '6767686543',
            'accountAddress' => 'abc',
            'reqTime' => $this->getMicroTime(),
            'notifyUrl' => $config['notify_url']
        ];

        if ($type == 'UPI') {
            $data = [
                'vpa' => trim($order['CardNo'])
            ];
            $postdata = array_merge($postdata, $data);
        } elseif ($type == 'BANK') {
            $data = [
                'accountNo' => trim($order['CardNo']),
                'ifsc' => trim($order['Province'])
            ];
            $postdata = array_merge($postdata, $data);
        }

        $postdata['sign'] = $this->createSign($config['secret'], $postdata);
        $requestHttpDate = json_encode($postdata);
        $header = [
            'Content-Type: application/json;charset=utf-8',
        ];
        $result = $this->httpRequestDataTest($this->api_url . '/api/transferOrder', $requestHttpDate, $header);    //发送http的post请求
        save_log('xpay', 'post:' . json_encode($postdata) . ',output:' . $result);
        $res = json_decode($result, true);
        $result = ['system_ref' => '', 'message' => ''];
        if ($res) {
            if ($res['code'] == 0) {
                $result['system_ref'] = '';
                $result['status'] = true;
                $result['message'] = 'success';
            } else {
                $result['message'] = $res['msg'];
                $result['status'] = false;
            }
        } else {
            $result['system_ref'] = '';
            $result['status'] = true;
            $result['message'] = 'success';
        }
        return $result;
    }


    ///查询订单
    public function queryorder($config, $orderid)
    {
        if (!empty($orderid)) {
            if ($config) {
                $this->appid = $config['appid'];
                $this->secret = $config['secret'];
            }
            $postdata = [
                'memberid' => $this->appid,
                'out_trade_no' => $orderid
            ];
            $md5sing = $this->createSign($this->secret, $postdata);
            $postdata['sign'] = $md5sing;

            $requestHttpDate = http_build_query($postdata);
            $result = $this->httpRequestDataTest($this->api_url . '/Payment_Dfpay_query.html', $requestHttpDate);    //发送http的post请求
            save_log('xpay', 'query order post:' . json_encode($postdata) . ',output:' . $result);
            $res = json_decode($result, true);
            return $res;
        }
    }


    //签名函数
    protected function createSign($Md5key, $list)
    {
        ksort($list); //按照ASCII码排序
        $tempstr = "";
        foreach ($list as $key => $val) {
            if ($val !== '') {
                $tempstr = $tempstr . $key . "=" . $val . "&";
            }
        }
        $md5str = md5($tempstr . "key=" . $Md5key);    //最后拼接上key=ApiKey(你的商户秘钥),进行md5加密
        $sign = strtoupper($md5str);                //把字符串转换为大写，得到sign签名
        return $sign;
    }

    //http请求函数
    public function httpRequestDataTest($url, $data = '', $headers = array(), $method = 'POST', $timeOut = 10, $agent = '')
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeOut);           //请求超时时间
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeOut);    //链接超时时间
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);        // https请求 不验证证书和hosts
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, $agent);
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            if ($data != '') curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        $file_contents = curl_exec($ch);
        curl_close($ch);
        //$httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
        save_log('xpay', 'input:' . json_encode($data));
        save_log('xpay', 'output:' . $file_contents);
        //这里解析
        return $file_contents;
    }


    private function getMicroTime()
    {
        list($msec, $sec) = explode(' ', microtime());
        $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        return $msectime;
    }
}