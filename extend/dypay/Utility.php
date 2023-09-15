<?php
namespace dypay;

/**
 * RSA签名类
 */
class Utility
{

    public function decrypt($data){
        ksort($data);
        $toSign ='';
        foreach($data as $key=>$value){
            if(strcmp($key, 'sign')!= 0  && $value!=''){
                $toSign .= $key.'='.$value.'&';
            }
        }

        $str = rtrim($toSign,'&');

        $encrypted = '';
        //替换自己的公钥
        $pem = chunk_split('MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDkB2K+RbKcP+tzJk2MZX3T8Ohp6QI8EWY5NCOWeKihqAY9W0u+ma+PV2DSsjXgE0S/YbKD0d7+CvvorX7M95GiGQVz9FVx9ITUgzfrQAZVqkKJclx2jt1A9OHnnaqDqvEmmZP/90MloLWD5s4+OzHCQVmU59atv6ENqHf0zIJqlQIDAQAB', 64, "\n");
        $pem = "-----BEGIN PUBLIC KEY-----\n" . $pem . "-----END PUBLIC KEY-----\n";
        $publickey = openssl_pkey_get_public($pem);

        $base64=str_replace(array('-', '_'), array('+', '/'), $data['sign']);

        $crypto = '';
        foreach(str_split(base64_decode($base64), 128) as $chunk) {
            openssl_public_decrypt($chunk,$decrypted,$publickey);
            $crypto .= $decrypted;
        }

        return  $str == $crypto?true:false;
    }

    //加密
    public function encrypt($data){
        ksort($data);
        $str = '';
        foreach ($data as $k => $v){
            if(!empty($v)){
                $str .=(string) $k.'='.$v.'&';
            }
        }
        $str = rtrim($str,'&');
        $encrypted = '';
        //替换成自己的私钥
        $pem = chunk_split('MIICeAIBADANBgkqhkiG9w0BAQEFAASCAmIwggJeAgEAAoGBAOQHYr5Fspw/63MmTYxlfdPw6GnpAjwRZjk0I5Z4qKGoBj1bS76Zr49XYNKyNeATRL9hsoPR3v4K++itfsz3kaIZBXP0VXH0hNSDN+tABlWqQolyXHaO3UD04eedqoOq8SaZk//3QyWgtYPmzj47McJBWZTn1q2/oQ2od/TMgmqVAgMBAAECgYEAtLSlq+PQB8Mf88EG85v6e1sO09+zxaaEPBD1ouk7ueBOEZGoFQP1/MJiGJbh2xFqCcCCl7RZ4zkRKPNU6VnILg5jp4X0gLFMgx/prpmrskr5U7b3Hx2dYs9I/WyQskTeqXQkxkQjl7S0YFoV4MHhkD3/yepj4unodJFK3To3GAECQQD2Ltl4Ve6bynVmKwunoZE7DtvHdInDy+9rm78M6EdOO5ORHbd9MxsfeBso7Kb0pqw8xNvIOJTrdtaBtu/iO6tRAkEA7R81zbkhrnguUBeveGbqptL+qW7V0d4NphS5ux6D3c8Oceo+fudd01vnzYt+jVqSoPkAhrTnEzOv2P6e/q5yBQJBAMYJigezmO7aPvahSg7feeT4XvRkWy6Wr1LxRw8rC7FzW5IxRZoBsp/uDmstdGD6czOvaN34JlQElSpj7zUeqwECQDokuwa07KNhaMnO5QH7CnLZrgRR3zBU6LfewSQ2+VK8YOhh7e0kQod/M7ndCK0UlnvOUui1FyxIMkhdNxNwJxkCQQClCDc3bVUhCRcgk/yYPoWwsUNyeEVRJXym1DpjMG5kruWJjQpnUXHI/td8g5svgkDwfN1KNYoqK008672/ifEh', 64, "\n");
        $pem = "-----BEGIN PRIVATE KEY-----\n" . $pem . "-----END PRIVATE KEY-----\n";
        $private_key = openssl_pkey_get_private($pem);
        $crypto = '';
        foreach (str_split($str, 117) as $chunk) {
            openssl_private_encrypt($chunk, $encryptData, $private_key);
            $crypto .= $encryptData;
        }
        $encrypted = base64_encode($crypto);
        $encrypted = str_replace(array('+','/','='),array('-','_',''),$encrypted);

        $data['sign']=$encrypted;
        return $data;
    }
    public static function globalpay_http_post_res_json($url, $postData)
    {
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-type:application/json',
                'content' => $postData,
                'timeout' => 15 * 60 // 超时时间（单位:s）
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        return $result;
    }

}