<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/5
 * Time: 22:12
 */

namespace bpay;

use libphonenumber\PhoneNumberUtil;
use redis\Redis;

class PaySdk
{


    public function payout($orderNo, $order, $config = [])
    {
        $result = [];
        $pixType = 'CPF';
        $realName = $order['RealName'];
        $pixKey = $order['CardNo'];
        $purpose = $order['CardNo'];
        $email = $order['City'];
        $isEmail = $this->isValidEmail($order['CardNo']);
        if ($isEmail){
            $pixType = 'EMAIL';
            $pixKey = $order['City'];
        }

        if ($order['CardNo'] == $order['RealName']){
            $pixType = 'PHONE';
            $pixKey = '+55' . $order['RealName'];
        }
        $apiUrl = $config['api_url'] ?? "https://api.bpay.tv/api/v2/transfer/order/create";
        $privateKey = $config['private_key'] ?? $this->getDefaultPrivateKey();
        $merchantNo = $config['merchant'] ?? '';
        $merchantOrderNo = $orderNo;
        $countryCode = $config['code'] ?? '';
        $currencyCode = $config['currency'] ?? '';
        $transferType = $config['payment_type'] ?? '900410285001';
        $transferAmount = $order['RealMoney'];
        $feeDeduction = "1";
        $remark = "remark";
        $notifyUrl = $config['notify_url'] ?? '';
        $extendedParams = "payeeName^$realName|PIX^$pixKey|pixType^$pixType|payeePhone^$realName|payeeEmail^$email|payeeCPF^$purpose";

        save_log('bpay','提交三方订单号:'.$orderNo);

        $data = [
            'merchantNo' => $merchantNo,
            'merchantOrderNo' => $merchantOrderNo,
            'countryCode' => $countryCode,
            'currencyCode' => $currencyCode,
            'transferType' => $transferType,
            'transferAmount' => $transferAmount,
            'feeDeduction' => $feeDeduction,
            'remark' => $remark,
            'notifyUrl' => $notifyUrl,
            'extendedParams' => $extendedParams,
        ];
        $dataStr = $this->ascSort($data);//排序
        $sign = $this->sign($dataStr, $privateKey);
        $data['sign'] = $sign;
        $postData = json_encode($data);
        $header = [
            'Content-Type: application/json; charset=utf-8',
            'Content-Length:' . strlen($postData),
            'Cache-Control: no-cache',
            'Pragma: no-cache'
        ];

        save_log('bpay','提交三方参数---'.json_encode($postData));
        $resultData = $this->curlPostContent($apiUrl, json_encode($postData), $header);//发送http的post请求
        save_log('bpay','返回参数---'.json_encode($resultData));
        if(empty($resultData)){
            $result['message'] = 'error';
            $result['status'] = false;
            return $result;
        }

        save_log('bpay', 'post:' . json_encode($postData) . ',output:' . $resultData);
        $res = json_decode($resultData, true);
        if (isset($res) && $res['code'] == '200') {
            $result['system_ref'] = '';
            $result['status'] = true;
            $result['message'] = 'success';
            Redis::set('PAYOUT_ORDER_SUCCESS_'.$orderNo,$orderNo);
        } else {
            $msg = '请求失败';
            if (isset($res['message'])){
                $msg = $res['message'];
            }
            $result['message'] = $msg;
            $result['status'] = false;
        }

        return $result;
    }


    //http请求函数
    public function curlPostContent($url, $postData = null, $header = [])
    {
        //"https://api.bpay.tv/api/v2/transfer/order/create"
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($curl);
        $errorno = curl_errno($curl);
        curl_close($curl);
        save_log('bpay', 'input:' . json_encode($postData));
        save_log('bpay', 'output:' . $res);
        //这里解析
        return $res;
    }

    private function ascSort($data =[])
    {
        if (!empty($data)) {
            $p = ksort($data);
            if ($p) {
                $str = '';
                foreach ($data as $k => $val) {
                    $str .= $k . '=' . $val . '&';
                }
                $strs = rtrim($str, '&');
                return $strs;
            }
        }
        return false;
    }
    private function sign($data, $extra): string
    {
        // 私钥
        $privateKeyBase64 = "-----BEGIN RSA PRIVATE KEY-----\n";
        $privateKeyBase64 .= wordwrap($extra, 64, "\n", true);
        $privateKeyBase64 .= "\n-----END RSA PRIVATE KEY-----\n";
        // 签名
        $merchantPrivateKey = openssl_get_privatekey($privateKeyBase64);
        openssl_sign($data, $signature, $merchantPrivateKey, OPENSSL_ALGO_MD5);
        return base64_encode($signature);
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

    /**
     * 文档给的默认私钥
     * @return string
     */
    private function getDefaultPrivateKey(): string
    {
        return "MIICdwIBADANBgkqhkiG9w0BAQEFAASCAmEwggJdAgEAAoGBAJnwukGNiyJ5TJaE+xeChBav6PCm8z7CtL49WzlMyWursIeJ6hFc7ikDPUau3SwACtJ8NtrhuTIP8l/8tO0VhCvGOUSDC/7g1SBJsiuQ+j3j9X3bCweVjSM+fBpm7PnLQBOKD1yiGbN7iLVIhqNsQ9JklyPnd8JEnx6I5ykwBh+RAgMBAAECgYBaQu9DHpZVSWBh5WlA2LNQhiaEbK+1vf6yiVFi4KY9rrbcUj5fnei7PX4BYuimMwQldNXJM48eToFkTM1dMj+DbIipoaVtVokqbtKBOIVyIK3SSkQ8+fi/KJWazuuxs+JYmF0JoGOCg4jZmZffrZI6l1QZ6XAwrYq1Af7W47K6fQJBAOGs0ZvZUv5tz2GRtxMFpMjyoy+RoOIPQM6k1P11QWshGPKI5lkzcpCHV9X6EU5LQ0e/u0u0UQ36ywAea4uW0dMCQQCuoD9FTEzYRHDWc/S/klDxLJrjLZ6YAZkDUZvymDTgnfP8MmhrGC+QL8+8yzLBJnBj+upXHiGLab5rz+xVI8aLAkEAp4PHx57G61OJl4w5T+ZljkAFf77ipErcOUfDTiymlaXoxcd27QmyZbQBMDVCeVKGq5CXr7c2X2ElJH5wKBqYvwJAboURRkSiJgY6/B9reYubGujGJp4Kz93C/+y4rHNUlAykDKvClnU6NSFtcumP99riKwT1J6n0RQ3p7MYtpzz7PQJBAK7zWytx/zovntDBcarwjjFvMqwQxqfzJTL691f+hH7gPjTvOqtf2c25jruO6hnwybLukhutNhwSB0gqu45XU5w=";
    }
}