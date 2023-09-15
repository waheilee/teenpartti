<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

namespace dypay;
use think\facade\Cache;

class PaySdk
{


    private $api_url = '';
    private $notify_url = '';
    private $appid = '';
    private $secret = '';

//平台公钥
    private $publicKey = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDByR7wOkfnmzcQ6OdGsvIegx08mpaeT4R01XY6+FmgablZ8dZ/KVGn1Y+m5kvSfn3piIH8Ma5REu7xXut1Wrv/rHixdwQ4yaUlbQnaMO1JZwEyTe/3sePnPHePC/enghopJrWRq2nTdbVO7snFeX0/1qNIWAuFFgPOUC4qphxRrQIDAQAB';
//商户私钥
    private $privateKey = 'MIICeAIBADANBgkqhkiG9w0BAQEFAASCAmIwggJeAgEAAoGBAOQHYr5Fspw/63MmTYxlfdPw6GnpAjwRZjk0I5Z4qKGoBj1bS76Zr49XYNKyNeATRL9hsoPR3v4K++itfsz3kaIZBXP0VXH0hNSDN+tABlWqQolyXHaO3UD04eedqoOq8SaZk//3QyWgtYPmzj47McJBWZTn1q2/oQ2od/TMgmqVAgMBAAECgYEAtLSlq+PQB8Mf88EG85v6e1sO09+zxaaEPBD1ouk7ueBOEZGoFQP1/MJiGJbh2xFqCcCCl7RZ4zkRKPNU6VnILg5jp4X0gLFMgx/prpmrskr5U7b3Hx2dYs9I/WyQskTeqXQkxkQjl7S0YFoV4MHhkD3/yepj4unodJFK3To3GAECQQD2Ltl4Ve6bynVmKwunoZE7DtvHdInDy+9rm78M6EdOO5ORHbd9MxsfeBso7Kb0pqw8xNvIOJTrdtaBtu/iO6tRAkEA7R81zbkhrnguUBeveGbqptL+qW7V0d4NphS5ux6D3c8Oceo+fudd01vnzYt+jVqSoPkAhrTnEzOv2P6e/q5yBQJBAMYJigezmO7aPvahSg7feeT4XvRkWy6Wr1LxRw8rC7FzW5IxRZoBsp/uDmstdGD6czOvaN34JlQElSpj7zUeqwECQDokuwa07KNhaMnO5QH7CnLZrgRR3zBU6LfewSQ2+VK8YOhh7e0kQod/M7ndCK0UlnvOUui1FyxIMkhdNxNwJxkCQQClCDc3bVUhCRcgk/yYPoWwsUNyeEVRJXym1DpjMG5kruWJjQpnUXHI/td8g5svgkDwfN1KNYoqK008672/ifEh';


    public function __construct()
    {
        $this->api_url = 'https://sbgdt.dyb360.com';
        $this->appid = '861100000012931';
        $this->secret = '70EAFAE6E124E359A0ABA76F06533EDA';
    }


    public function payout($OrderNo,$order,$config=[]){

        if (empty($config)) {
            return array('status'=>FALSE, 'message' => 'Missing parameter');
        }
        if (isset($config['appid']) && !empty($config['appid'])) {
            $this->appid = $config['appid'];
        }
        if (isset($config['secret']) && !empty($config['secret'])) {
            $this->secret = $config['secret'];
        } else {
            $this->secret = $this->withdraw_conf['secret'];
        }
        if (isset($config['apiurl']) && !empty($config['apiurl'])) {
            $this->api_url = $config['apiurl'];
        } else {
            $this->api_url = $this->withdraw_conf['api_url'];
        }
        if (!isset($config['notify_url']) || empty($config['notify_url'])) {
            return array('status'=>FALSE, 'message' => 'Missing notify_url');
        }
        // 替换成用户选择的银行
//        if (!isset($order['BankName']) || empty($order['BankName'])) {
//            return array('status'=>FALSE, 'message' => 'Missing BankName');
//        }
        // 这里指定 支付方式 UPI/IMPS 根据支付方式确定传参对象
//        $type = 'UPI';
//        if($order['PayWay']==2){
//            $type ='IMPS';
//        }

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


        $data=[
            'mer_no'=>$this->appid,
            'mer_order_no'=>$OrderNo,
            'acc_no'=>$order['CardNo'],
            'acc_name'=>$order['RealName'],
            'ccy_no'=>'BRL',
            'order_amount'=> bcmul($order['RealMoney'],1,2),
            'bank_code'=>'PIX',
            'mobile_no'=>strval(rand(70000,90000)).strval(rand(10000,90000)),
            'identity_no'=>$pixkey,
            'identity_type'=>$pixtype,
            'notifyUrl'=>$config['notify_url'],
            'summary'=>$order['AccountID']
        ];

        $sign_data= $this->gensign($data,$config['secret']);
        $header = [
            'Content-Type: application/json; charset=utf-8',
        ];
        $result = $this->curl_post_content($config['apiurl'] .'/withdraw/singleOrder', json_encode($sign_data), $header);
        save_log('dypay','post:'.json_encode($sign_data).',output:'.$result);
        $res = json_decode($result, true);
        $result =['system_ref'=>'','message'=>''];
        if ($res) {
            if($res['status'] == 'SUCCESS'){
                $result['system_ref'] = '';
                $result['status'] = true;
                $result['message'] = 'success';
            }
            else
            {
                $result['status'] = false;
                $result['message'] =$res['err_msg'];
            }
        } else {
            $result['system_ref'] = '';
            $result['status'] = true;
            $result['message'] = 'success';
        }
        return $result;
    }



    private function random_str($length)
    {
        //生成一个包含  小写英文字母, 数字 的数组
        $arr = range('a', 'z');
        $str = '';
        $arr_len = count($arr);
        for ($i = 0; $i < $length; $i++) {
            $rand = mt_rand(0, $arr_len - 1);
            $str .= $arr[$rand];
        }
        return $str;
    }


    public function gensign($data){
        $utility =new Utility();
        $str_sign = $utility->encrypt($data);
        return $str_sign;
    }

    public function verify($data){
        //验签
        ksort($data);
        reset($data);
        $arg = '';
        foreach ($data as $key => $val) {
            //空值不参与签名
            if ($val == '' || $key == 'sign') {
                continue;
            }
            $arg .= ($key . '=' . $val . '&');
        }
        $sig_data =  substr($arg,0,strlen($arg)-1);
        $rsa = new Rsa($this->publicKey, '');
        if ($rsa->verify($sig_data, $data['sign']) == 1) {
            return true;
        }
        return false;
    }





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