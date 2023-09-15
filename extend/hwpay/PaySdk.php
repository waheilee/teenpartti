<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/5
 * Time: 22:12
 */

namespace hwpay;

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
        $this->secretkey = '';
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

        $merchant_no  = $this->merchant;
        $OrderNo      = trim($OrderNo);
        $amount       = sprintf('%.2f',$order['RealMoney']*100);
        $notify_url   = trim($config['notify_url']);
        $mobile      = rand(6,9).rand(100000000,999999999);
        $email       = $mobile.'@gmail.com';

        $head = [
            "mchtId"=>$merchant_no,
            "version"=>"20",
        ];

        $body = [
            'batchOrderNo' =>$OrderNo,
            'totalNum'     =>1,
            'totalAmount'  =>$amount,
            'notifyUrl'    =>$notify_url,
            'currencyType' =>'INR',
        ];
        $detail = [
            'seq'=>$OrderNo,
            'amount'=>$amount,
            'accType'=>"0",
            'mobile'=>$mobile,
            'email'  =>$email
        ];
        if ($type == 'UPI') {
            $head['biz'] = 'df102';
            $detail['bankCode'] = trim($order['CardNo']);
            $detail['bankCardName'] = trim($order['RealName']);
        } elseif ($type == 'IMPS') {
            $head['biz'] = 'df101';
            $detail['bankCode'] = trim($order['Province']);
            $detail['bankCardNo'] = trim($order['CardNo']);
            $detail['bankCardName'] = trim($order['RealName']);
        }

        $body['detail'] = [$detail];
        $body = $this->rsaPublicEncrypt(json_encode($body),$config['public_key']);
        $header = [
            "Content-Type:text/html; charset=utf-8"
        ];
        $postdata = [
            'head'=>$head,
            'body'=>urlencode($body)
        ];
        
        $result =$this->curl_post_content($this->api_url,json_encode($postdata), $header);
        save_log('hwepay','post:'.json_encode($postdata).',output:'.$result);
        $res = json_decode($result, true);
        $result =['system_ref'=>'','message'=>''];
        if ($res) {
            if($res['head']['respCode'] == '0000'){
                $result['system_ref'] = '';
                $result['status'] = true;
                $result['message'] = 'success';
            }
            else
            {
                $result['message'] =$res['head']['respMsg'];
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


    public function rsaPublicEncrypt($data,$key)
    {
        $data = str_replace('\/','/',$data);
        $encrypted = '';
        // $pu_key = openssl_pkey_get_public($key);
        $pu_key = "-----BEGIN PUBLIC KEY-----\n";
        $pu_key.= wordwrap($key, 64, "\n", true);
        $pu_key.= "\n-----END PUBLIC KEY-----";
        $plainData = str_split($data, 117);

        foreach ($plainData as $chunk) {
            $partialEncrypted = '';
            //公钥加密
            $encryptionOk = openssl_public_encrypt($chunk, $partialEncrypted, $pu_key);
            if ($encryptionOk === false) {
                return false;
            }

            $encrypted .= $partialEncrypted;
        }

        $encrypted = base64_encode($encrypted);

        return $encrypted;
    }

    //签名函数
    protected function createSign($Md5key, $list)
    {
        ksort($list); //按照ASCII码排序
        $tempstr = "";
        foreach ($list as $key => $val) {
            if ($val !== '') {
                $tempstr = $tempstr . $val;
            }
        }
        $md5str = md5($tempstr . $Md5key);  //最后拼接上key=ApiKey(你的商户秘钥),进行md5加密
        return $md5str;
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