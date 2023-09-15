<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/5
 * Time: 22:12
 */

namespace joypay;

class PaySdk
{

    private $api_url = '';
    private $notify_url = '';
    private $merchant = '';
    private $secret = '';


    public function __construct()
    {
        $this->api_url = '';
        $this->merchant = '';
        $this->secret = '';
    }





    public function payout($OrderNo,$order,$config=[]){
        if (empty($config)) {
            return array('status'=>FALSE, 'message' => 'Missing parameter');
        }
        if (isset($config['appid']) && !empty($config['appid'])) {
            $this->merchant = $config['appid'];
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
            return array('status'=>FALSE, 'message' => 'Missing notify_url');
        }
        
        $type = 'UPI';
        if($order['PayWay']==2){
            $type ='IMPS';
        }

        $merchant_no = $this->merchant;
        $orderNo     = trim($OrderNo);
        $amount      = sprintf('%.2f',$order['RealMoney']);
        $beneficiaryName   = trim($order['RealName']);
        $mobile      = rand(6,9).rand(100000000,999999999);
        $email       = $mobile.'@gmail.com';
        $remark      = $OrderNo;
        $postdata = [
            "orderNo"   => $orderNo,
            "amount"    => $amount,
            "beneficiaryName"=>$beneficiaryName,
            'beneficiaryMobile'    => $mobile,
            'beneficiaryEmail'     => $email,
            'remark'           => $remark
        ];

        if ($type == 'UPI') {
            $data = [
                'paymentType'=>'1',
                'upi'=>trim($order['CardNo']),
            ];
            $postdata = array_merge($postdata,$data);
        } elseif ($type == 'IMPS') {
            $data = [
                'paymentType'=>'0',
                'bankAccount'=>trim($order['CardNo']),
                'ifsc'=>trim($order['Province']),
            ];
            $postdata = array_merge($postdata,$data); 
        }
        $data_str = $this->getUrlStr($postdata);
        $sign = $this->sign($data_str, $this->secret);
        $header = [
            'Content-Type: application/json; charset=utf-8',
            'X-SIGN: ' . $sign,
            'X-SERVICE-CODE: ' . $config['code']
        ];
        $result =$this->curl_post_content($this->api_url .'/portal/transfer',$postdata, $header);
        save_log('joypay','post:'.json_encode($postdata).',output:'.$result);
        $res = json_decode($result, true);
        $result =['system_ref'=>'','message'=>''];
        if ($res) {
            if($res['code'] == '0000'){
                $result['system_ref'] = '';
                $result['status'] = true;
                $result['message'] = 'success';
            }
            else
            {
                $result['message'] =$res['msg'];
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
            $result = $this->httpRequestDataTest($this->api_url . '/Payment_Dfpay_query.html', $requestHttpDate);    //发送http的post请求
            save_log('tgpay','query order post:'.json_encode($postdata).',output:'.$result);
            $res = json_decode($result, true);
            return $res;
        }
    }


    private  function sign($data, $extra) {
        // 私钥
        $privateKeyBase64 = "-----BEGIN RSA PRIVATE KEY-----\n";
        $privateKeyBase64.= wordwrap($extra, 64, "\n", true);
        $privateKeyBase64.= "\n-----END RSA PRIVATE KEY-----\n";
        // 签名
        openssl_sign($data, $signature, $privateKeyBase64, OPENSSL_ALGO_SHA512);
        return base64_encode($signature);
    }

    private function getUrlStr($data) {
        ksort($data);
        $urlStr = [];
        foreach ($data as $k => $v) {
            if (strlen($v) > 0 && $k != 'sign') {
                $urlStr[] = $k . '=' . rawurlencode($v);
            }
        }
        return join('&', $urlStr);
    }    


    private function curl_post_content($url, $data = null, $header = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $output = curl_exec($ch);
        curl_close($ch);;
        return $output;
    }
}