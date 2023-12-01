<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/5
 * Time: 22:12
 */

namespace peakpay;

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





    public function payout($OrderNo,$order,$config=[]){
        if (empty($config)) {
            return array('status'=>FALSE, 'message' => 'Missing parameter');
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
            return array('status'=>FALSE, 'message' => 'Missing notify_url');
        }


        $merchant    = $this->merchant;
        $appid       = $this->appid;
        $orderId      = trim($OrderNo);
        $amount       = sprintf('%.2f',$order['RealMoney']);
        $notify_url   = trim($config['notify_url']);
        $username    = chr(rand(65,90)).chr(rand(97,122)).chr(rand(97,122)).chr(rand(97,122)).chr(rand(97,122)).chr(rand(97,122));

        $pixtype = $order['BankName'];
        if(empty($pixtype)){
            $pixtype ='CPF';
        }

        $cpf =str_replace('.','',$order['CardNo']);
        $cpf =str_replace('-','',$cpf);

        $email =$order['City'];
        if(empty($email)){
            $email='test@qq.com';
        }

        $postdata = [
            'amount'          =>(int)$amount*100,
            'appId'           =>$appid,
            'backUrl'         =>$notify_url,
            'cardType'        =>$pixtype,
            'notifyurl'       =>$notify_url,
            'countryCode'     =>'BR',
            'currencyCode'    =>'BRL',
            'custId'          =>$merchant,
            'email'           =>$email,
            'merchantOrderId' =>$orderId,
            'cpf'             =>$cpf,
            'phone'           =>'+55' . $order['Province'],
            'remark'          =>$username,
            'type'            =>'PIX',
            'userName'        =>trim($order['RealName']),
            'walletId'          =>trim($order['CardNo']),
            // 'payeeBankCode'   =>trim($order['Province']),
            // 'cnapsCode'       =>trim($order['BankName'])
        ];

        $postdata['sign'] = $this->createSign($postdata,$this->secret);

        $header = [
            'Content-Type:application/json;charset=UTF-8',
        ];

        $result =$this->curl_post_content($this->api_url,json_encode($postdata), $header);

        save_log('peakpay','post:'.json_encode($postdata).',output:'.$result);
        $res = json_decode($result, true);
        $result =['system_ref'=>'','message'=>''];
        if ($res) {
            if($res['code'] == '000000'){
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
        return md5($str);
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