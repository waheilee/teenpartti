<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/5
 * Time: 22:12
 */

namespace firstpay;

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
    
        $type = 'upi';
        if($order['PayWay']==2){
            $type ='bank';
        }


        $merchant_no  = $this->merchant;
        $orderId      = trim($OrderNo);
        $amount       = sprintf('%.0f',$order['RealMoney']);
        $notify_url   = trim($config['notify_url']);
        $mobile      = rand(6,9).rand(100000000,999999999);
        $email       = $mobile.'@gmail.com';
        $RealName    = trim($order['RealName']);
        $timestamp   = time();
        $postdata = [
            'app_order_id' =>$orderId,
            'amount'       =>(int)$amount,
            'user_name'     =>$RealName,
            'phone'        =>$mobile 
        ];
        $postdata['pay_type'] = $type;

        if ($type == 'upi') {
            $postdata['vpa'] = trim($order['CardNo']);
        } else {
            $postdata['ifsc'] = trim($order['Province']);
            $postdata['bank_card'] = trim($order['CardNo']);
        }
        $header = [
            'Content-Type:application/json;charset=UTF-8',
            'AppId:'.$this->merchant,
            'Signature:'.base64_encode(hash_hmac('sha256', json_encode($postdata), $this->secret))
        ];

        $result =$this->curl_post_content($this->api_url.'//v1/platform/payout ',json_encode($postdata), $header);

        save_log('firstpay','post:'.json_encode($postdata).',output:'.$result);
        $res = json_decode($result, true);
        $result =['system_ref'=>'','message'=>''];
        if ($res) {
            if($res['code'] == '200'){
                $result['system_ref'] = '';
                $result['status'] = true;
                $result['message'] = 'success';
            }
            else
            {
                $result['message'] ='fail';
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
            save_log('tgpay','query order post:'.json_encode($postdata).',output:'.$result);
            $res = json_decode($result, true);
            return $res;
        }
    }



    //签名函数
    private function createSign($data,$Md5key)
    {
        ksort($data);
        $md5str = '';
        foreach ($data as $key => $val) {
            if (trim($val) !== '') {
                $md5str = $md5str . $key . '=' . $val . '&';
            }
        }
        $str =$md5str . 'key=' . $Md5key;
        return strtolower(md5($str));
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