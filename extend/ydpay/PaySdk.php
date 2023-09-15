<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/5
 * Time: 22:12
 */

namespace ydpay;

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

        if (isset($config['private_key']) && !empty($config['private_key'])) {
            $this->secret = $config['private_key'];
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

        $merchant_no  = $this->merchant;
        $orderId      = trim($OrderNo);
        $amount       = sprintf('%.2f',$order['RealMoney']);
        $notify_url   = trim($config['notify_url']);
        $mobile      = rand(6,9).rand(100000000,999999999);
        $email       = $mobile.'@gmail.com';
        $RealName    = trim($order['RealName']);
        $postdata = [
            'mer_no'       =>$merchant_no,
            'mer_order_no' =>$orderId,
            'order_amount' =>floor($amount),
            'cyy_no'       =>'INR',
            'acc_name'     =>$RealName,
            'phone'        =>$mobile,
            'email'        =>$email,
            'notifyurl'    =>$notify_url
        ];
    
        if ($type == 'UPI') {
            $postdata['pay_type'] = 'UPI';
            $postdata['vpa'] = trim($order['CardNo']);
        } elseif ($type == 'IMPS') {
            $postdata['pay_type'] = 'BANK';
            $postdata['acc_no'] = trim($order['CardNo']);
            $postdata['province'] = trim($order['Province']);
        }

        $header = [
            'Content-Type: application/json;charset=utf-8',
        ];

        $postdata['sign'] = $this->rsa_sign($this->secret,json_encode($postdata));
        $result =$this->curl_post_content($this->api_url,json_encode($postdata), $header);

        save_log('ydpay','post:'.json_encode($postdata).',output:'.$result);
        $res = json_decode($result, true);
        
        $result =['system_ref'=>'','message'=>''];
        if ($res) {
            if($res['code'] == 1){
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

            $requestHttpDate = http_build_query($postdata);
            $result = $this->httpRequestDataTest($this->api_url . '/Payment_Dfpay_query.html', $requestHttpDate);    //发送http的post请求
            save_log('tgpay','query order post:'.json_encode($postdata).',output:'.$result);
            $res = json_decode($result, true);
            return $res;
        }
    }



    //签名函数
    public function rsa_sign($rsaPrivateKey,$data)
    {
        //Log::record('Ybpay 签名串 = '.$data, Log::INFO);
        //Log::record('Ybpay key= '. $rsaPrivateKey, Log::INFO);
        $encrypted = '';
        //替换成自己的私钥
        $pem = chunk_split($rsaPrivateKey, 64, "\n");
        $pem = "-----BEGIN PRIVATE KEY-----\n" . $pem . "-----END PRIVATE KEY-----\n";
        $private_key = openssl_pkey_get_private($pem);
        $crypto = '';
        foreach (str_split($data, 117) as $chunk) {
          openssl_private_encrypt($chunk, $encryptData, $private_key);
          $crypto .= $encryptData;
        }
        $encrypted = base64_encode($crypto);
        //$encrypted = str_replace(array('+','/','='),array('-','_',''),$encrypted);
        //Log::record('Ybpay sign = '.$encrypted , Log::INFO);
        return $encrypted;
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