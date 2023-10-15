<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/5
 * Time: 22:12
 */

namespace mkcpay;

use EllipticCurve\Ecdsa;
use EllipticCurve\PrivateKey;
use EllipticCurve\PublicKey;
use EllipticCurve\Signature;
use libphonenumber\PhoneNumberUtil;
use redis\Redis;

class PaySdk
{

    private $api_url;
    private $appid;

    private $privateKey;

    public function __construct()
    {
        $this->privateKey = '';
        $this->api_url = 'https://doc.mkcpay.com/api/pay/v1/mkcPay/createPixTransfer';
        $this->appid = '';
    }


    public function payout($OrderNo, $order, $config = [])
    {
        $result = ['system_ref' => '', 'message' => ''];


        if (!empty($config['private_key'])) {
            $this->privateKey = $config['private_key'];
        }
        if (isset($config['appid']) && !empty($config['appid'])) {
            $this->appid = $config['appid'];
        }

        if (isset($config['apiurl']) && !empty($config['apiurl'])) {
            $this->api_url = $config['apiurl'];
        }
        save_log('mkcpay','提交三方订单号:'.$OrderNo);
        $pixType = 'CPF';
        $pixKey = $order['CardNo'];
        $purpose = $order['CardNo'];
        $isEmail = $this->isValidEmail($order['CardNo']);
        if ($isEmail){
            $pixType = 'EMAIL';
            $pixKey = $order['City'];
        }
//        $isPhoneNumber = $this->isBrazilianMobileNumber($order['CardNo']);
//        if ($isPhoneNumber){
//            $pixType = 'PHONE';
//            $pixKey = '+55' . $order['Province'];
//        }


        $postData = [
            'amount' => (int)($order['RealMoney']  * 100),
            'pix' => $pixKey,
            'externalOrderNo' => $OrderNo,
            'purpose' => $purpose,
            'transferType' => $pixType,
            'description' => $OrderNo,
        ];
        $dataMd5 = $this->ksrotArrayMd5($postData);
        $sign = $this->encry($dataMd5,$this->privateKey);
        $header = [
            'Content-Type: application/json; charset=utf-8',
            'sign:' . $sign,
            'appKey:' . $this->appid,
        ];
        save_log('mkcpay','提交三方参数---'.json_encode($postData));
        $resultData = $this->httpRequestDataTest($this->api_url, json_encode($postData), $header);//发送http的post请求
        save_log('mkcpay','返回参数---'.json_encode($resultData));
        if(empty($resultData)){
            $result['message'] = 'error';
            $result['status'] = false;
            return $result;
        }

        //{
        //"result": {
        //"orderNo": "881609443421192192",
        //"amount": 16,
        //"name": "xx",
        //"taxId": "xx",
        //"bankCode": "xxx",
        //"branchCode": "0001",
        //"accountNumber": "xxxx",
        //"description": "xx",
        //"fee": "15.00",
        //"externalOrderNo":"xxx",
        //"status": "created", //created-订单已创建 success-成功 failed-失败 canceled-取
        //消
        //"orderTime": 1690358536669, //下单时间戳
        //"successTime": null //成功转账时间戳
        //},
        //"success": true,
        //"message": "成功",
        //"code": 200
        //}
        save_log('mkcpay', 'post:' . json_encode($postData) . ',output:' . $resultData);
        $res = json_decode($resultData, true);
        if (isset($res) && $res['success']) {
            $result['system_ref'] = '';
            $result['status'] = true;
            $result['message'] = 'success';
            Redis::set('PAYOUT_ORDER_SUCCESS_'.$OrderNo,$OrderNo);
        } else {
            $result['message'] = $res['msg'];
            $result['status'] = false;
        }

        return $result;
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
        save_log('tgpay', 'input:' . json_encode($data));
        save_log('tgpay', 'output:' . $file_contents);
        //这里解析
        return $file_contents;
    }

    private function ksrotArrayMd5($data)
    {
        ksort($data);
        $str = json_encode($data);
        return md5($str);
    }
    public function encry($data,$privateKeyConfig)
    {
        //$key = 'MHUCAQEEIRgDFg6d7/rz9qBOiFnTyLCT4p6yw3fhQR+qKmsJpTMjxKAHBgUrgQQACqFEA0IABL7v4UTuEF9d24QPZJJVv7d+QEJXdd9JfmvFKn3ofIsqRcyPkIDK3VTrl6qEa86YAT5ZN05puDj2J689L/6wIgo=';

        $privateKey = "-----BEGIN EC PRIVATE KEY-----\n";
        $privateKey .= wordwrap($privateKeyConfig, 64, "\n", true);
        $privateKey .= "\n-----END EC PRIVATE KEY-----\n";

# Generate privateKey from PEM string
        $privateKey = PrivateKey::fromPem($privateKey);

        $signature = Ecdsa::sign($data, $privateKey);

// Generate Signature in base64. This result can be sent to Stark Bank in header as Digital-Signature parameter
        return $signature->toBase64();
    }

    public function verify($data, $sig)
    {
        $key = 'MFYwEAYHKoZIzj0CAQYFK4EEAAoDQgAEvu/hRO4QX13bhA9kklW/t35AQld130l+a8Uqfeh8iypFzI+QgMrdVOuXqoRrzpgBPlk3Tmm4OPYnrz0v/rAiCg==';
        $publicKeyPem = "-----BEGIN PUBLIC KEY-----\n";
        $publicKeyPem .= wordwrap($key, 64, "\n", true);
        $publicKeyPem .= "\n-----END PUBLIC KEY-----\n";
        $publicKey = PublicKey::fromPem($publicKeyPem);
        $signature = Signature::fromBase64($sig);
        return Ecdsa::verify(trim($data), $signature, $publicKey);
    }

    /**
     * 验证手机号码
     * @param $phone
     * @return bool
     * @throws \libphonenumber\NumberParseException
     */
    public function isBrazilianMobileNumber($phone)
    {
        $phoneNumberUtil = PhoneNumberUtil::getInstance();
        $phoneNumberObject = $phoneNumberUtil->parse($phone, 'BR');
        return $phoneNumberUtil->isValidNumber($phoneNumberObject);
    }

    /**
     * 验证是否为有效邮箱
     * @param $email
     * @return bool
     */
    function isValidEmail($email) {
        // 使用 PHP 的 filter_var 函数和 FILTER_VALIDATE_EMAIL 过滤器验证邮箱格式
//        $email = "12346878641"; // 替换为要测试的邮箱地址
        $isEmail = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        if ($isEmail) {
            return true;
        } else {
            return false;
        }
    }
}