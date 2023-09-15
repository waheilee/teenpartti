<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/5
 * Time: 22:12
 */

namespace toppay;

class PaySdk
{

    private $api_url = '';
    private $notify_url = '';
    private $merchant = '';
    private $secret = '';
    private $private_key = '';


    public function __construct()
    {
        $this->api_url = '';
        $this->merchant = '';
        $this->secret = '';
        $this->private_key = '';
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
        if (isset($config['private_key']) && !empty($config['private_key'])) {
            $this->private_key = $config['private_key'];
        } else {
            $this->private_key = '';
        }
        if (!isset($config['notify_url']) || empty($config['notify_url'])) {
            return array('status'=>FALSE, 'message' => 'Missing notify_url');
        }
    
        $pixtype = $order['BankName'];
        $pixkey = '';
        $CPF = $order['CardNo'];
        switch ($pixtype) {
            case 'CPF':
                $pixkey = $order['CardNo'];
                break;

            case 'CNPJ':
                $pixkey = $order['CardNo'];
                break;

            case 'PHONE':
                $pixkey = '+55' . $order['Province'];
                break;

            case 'EMAIL':
                $pixtype = 'EMAIL';
                $pixkey = $order['City'];
                break;
            default :
                $pixtype ='CPF';
                $cpf =str_replace('.','',$order['CardNo']);
                $cpf =str_replace('-','',$cpf);
                $pixkey = $cpf;
                break;
        }

        $postdata = [
            'merchant_no'  => $this->merchant,
            'out_trade_no' => $OrderNo,
            'description'  =>'pay_description',
            'title'        =>'pay_title',
            'pay_amount'   => sprintf('%.2f', $order['RealMoney']).'',
            'name'         =>'pay name',
            'cpf'          => $CPF,
            'pixtype'      => $pixtype,
            'dict_key'    => $pixkey,
            'notify_url'    => $config['notify_url'],
        ];
        $header = [
            'Content-Type:application/json;charset=UTF-8',
        ];
        $postdata['sign'] = $this->buildSign($postdata,$this->private_key);
        $result =$this->curl_post_content($this->api_url.'/api/trade/payout',json_encode($postdata), $header);
        save_log('toppay', 'post:' . json_encode($postdata) . ',output:' . $result);
        $res = json_decode($result, true);
        $result = ['system_ref' => '', 'message' => ''];
        if ($res) {
            if ($res['code'] == '0') {
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
            save_log('tgpay','query order post:'.json_encode($postdata).',output:'.$result);
            $res = json_decode($result, true);
            return $res;
        }
    }


/**
 * 加密加签
 */
public function buildSign(array $params, string $privateKey): string
{
    ksort($params);
    $str = [];
    foreach ($params as $k => $v) {
        if ($v === '') {
            continue;
        }
        $str[] = $k . '=' . $v;
    }
    $send = implode('&', $str) . '&';
    // 获取用户公钥，并格式化
    $privateKey = "-----BEGIN PRIVATE KEY-----\n"
        . wordwrap(trim($privateKey), 64, "\n", true)
        . "\n-----END PRIVATE KEY-----";
    $content = '';
    $privateKey = openssl_pkey_get_private($privateKey);
    foreach (str_split($send, 117) as $temp) {
        openssl_private_encrypt($temp, $encrypted, $privateKey);
        $content .= $encrypted;
    }
    return base64_encode($content);
}

/**
 * 解密验签
 */
public function verifySign(array $data, string $publicKey): bool
{
    if (isset($data['sign'])) {
        $sign = base64_decode($data['sign']);
        unset($data['sign']);
    } else {
        return false;
    }
    ksort($data);
    $str = [];
    foreach ($data as $k => $v) {
        if ($v === '') {
            continue;
        }
        $str[] = $k . '=' . $v;
    }
    $send = implode('&', $str) . '&';
    // 获取用户公钥，并格式化
    $publicKey = "-----BEGIN PUBLIC KEY-----\n"
        . wordwrap(trim($publicKey), 64, "\n", true)
        . "\n-----END PUBLIC KEY-----";
    $publicKey = openssl_pkey_get_public($publicKey);
    $result = '';
    foreach (str_split($sign, 128) as $value) {
        openssl_public_decrypt($value, $decrypted, $publicKey);
        $result .= $decrypted;
    }
    return $result === $send;
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