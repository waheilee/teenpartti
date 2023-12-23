<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/5
 * Time: 22:12
 */

namespace uwinpay;

class PaySdk
{


    public function payout($OrderNo, $order, $config = [])
    {
        //{
        //  "account_code": "KKBK0000123",
        //  "account_number": "11111111111",
        //  "account_type": "default",
        //  "amount": "100",
        //  "description": "1",
        //  "email": "admin@qq.com",
        //  "merchant_code": "100000",
        //  "merchant_order_no": "20230330013803309958",
        //  "mobile": "6456312891",
        //  "name": "test",
        //  "notify_url": "https://xxx.com/notify",
        //  "order_time": 1680111483,
        //  "sign": "LHWDFwR70PzDxlPnpFpaJFpbTGEKY4QAf6uCpXs+wju+WZErC8PDwtEaWu8cOkJMDRAbSBXekHNQRaKawcqDDgCGZ99Y2BhLwjgCX9ukAlE37vez83xOTJSw5Ivl43a+2YkKdaWYaiUNxDezro6PVQsKGGV65V4YKXJxLkpqJ90DkgC2cf0uT26yVUzx0ziuofdVg6NX0v31Pl7esCDlSD9aBwaX9569nRswelhAe65eh7DakocAumzfVEpnS839KuicxTK2j89qzhwfiwMJ0U01suwpvYEUy3ZMusVNGfoKnReLFsrBeGP+Z7bHIkJnMsKxMMYlpA/r/pbrjXOnyg=="
        //}
        if (!isset($order['PayWayType'])){
            $result['system_ref'] = '';
            $result['status'] = false;
            $result['message'] = '出款类型有误，请检查后在提交';
            return $result;
        }
        $firstname = 'pay';
        $lastName = 'honey';

        if ($order['PayWayType'] == 6) {
            //PHONE
            $accountType = 4;
            $accountNumber = '+55'.$order['CardNo'];//收款账户号码
        } elseif ($order['PayWayType'] == 7) {
            //EMAIL
            $accountType = 3;
            $accountNumber = $order['CardNo'];//收款账户号码
        } else {
            //CPF
            $accountType = 1;//收款账户类型
            $accountNumber = $order['CardNo'];//收款账户号码
        }
        $privateKey = $config['private_key'] ?? $this->getDefaultPrivateKey();
        $accountCode = $order['Province']; //收款账户编码
        $amount = sprintf('%.2f', $order['RealMoney']);
        $description = '收款账户编码:' . $order['Province'] . ';收款账户号码:' . $order['CardNo'];
        $mobile = rand(6, 9) . rand(100000000, 999999999);
        $email = $mobile . '@gmail.com';
        $merchantCode = $config['merchant'] ?? '';
        $merchantOrderNo = trim($OrderNo);
        $name = $firstname . $lastName;
        $notifyUrl = $config['notify_url'] ?? '';
        $orderTime = time();
        $apiUrl = $config['api_url'] ?? 'https://br-api.uwinpay.com';
        $data = [
            "account_code" => $accountCode,
            "account_number" => $accountNumber,
            "account_type" => $accountType,
            "amount" => $amount,
            "description" => $description,
            "email" => $email,
            "merchant_code" => $merchantCode,
            "merchant_order_no" => $merchantOrderNo,
            "mobile" => $mobile,
            "name" => $name,
            "notify_url" => $notifyUrl,
            "order_time" => $orderTime,
        ];

        $dataStr = $this->ascSort($data);
        $sign = $this->sign($dataStr, $privateKey);
        $data['sign'] = $sign;
        $postData = json_encode($data);
        $header = [
            'Content-Type:application/json;charset=UTF-8',
        ];
        $result = $this->curlPostContent($apiUrl . '/transfer', $postData, $header);

        save_log('uwinpay', '提交参数:' . $postData . ',接口返回信息：' . $result);
        $res = json_decode($result, true);
        $result = ['system_ref' => '', 'message' => ''];
        if ($res) {
            if ($res['code'] == '200') {
                $result['system_ref'] = '';
                $result['status'] = true;
                $result['message'] = 'success';
            } else {
                $result['message'] = $res['message'];
                $result['status'] = false;
            }
        } else {
            $result['system_ref'] = '';
            $result['status'] = false;
            $result['message'] = '订单同步回调失败';
        }
        return $result;
    }

    /**
     * @Notes:生成 sha256WithRSA 签名
     * 提示：SPKI（subject public key identifier，主题公钥标识符）
     * @param null $data 待签名内容
     * @param string $extra 私钥数据（如果为单行，内容需要去掉RSA的标识符）
     * @return string               签名串
     */
    private function sign($data, string $extra): string
    {
        // 私钥
        $privateKeyBase64 = "-----BEGIN RSA PRIVATE KEY-----\n";
        $privateKeyBase64 .= wordwrap($extra, 64, "\n", true);
        $privateKeyBase64 .= "\n-----END RSA PRIVATE KEY-----\n";
        // 签名
        $merchantPrivateKey = openssl_get_privatekey($privateKeyBase64);
        openssl_sign($data, $signature, $merchantPrivateKey, OPENSSL_ALGO_SHA256);
        $encryptedData = base64_encode($signature);
        openssl_free_key($merchantPrivateKey);
        return $encryptedData;
    }

    /**
     * 回调验签
     * @param $data
     * @param $sign
     * @param $publicKey
     * @return false|int
     */
    private function verify($data, $sign, $publicKey)
    {

        $publicKeyBase64 = "-----BEGIN PUBLIC KEY-----\n";
        $publicKeyBase64 .= wordwrap($publicKey, 64, "\n", true);
        $publicKeyBase64 .= "\n-----END PUBLIC KEY-----\n";
        //验证
        $payPublicKey = openssl_get_publickey($publicKeyBase64);

        return openssl_verify($data, base64_decode($sign), $payPublicKey, OPENSSL_ALGO_SHA256);
    }

    private function ascSort($data = [])
    {
        if (!empty($data)) {
            $p = ksort($data);
            if ($p) {
                $str = '';
                foreach ($data as $k => $val) {
                    if ($val == '') {
                        continue;
                    }
                    $str .= $k . '=' . $val . '&';
                }
                return rtrim($str, '&');
            }
        }
        return false;
    }


    private function curlPostContent($url, $postData = null, $header = [])
    {
        $ch = curl_init();
        if (substr_count($url, 'https://') > 0) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        if (!empty($postData)) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
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

    /**
     * 文档给的默认私钥
     * @return string
     */
    private function getDefaultPrivateKey(): string
    {
        return "MIIEvwIBADANBgkqhkiG9w0BAQEFAASCBKkwggSlAgEAAoIBAQC2Fr43Dj/+CEmCJKgN81uUS3vyUp4BRI3Vzi2ZZlOCwmWuigfHLHW2K49qoKy7TOB84l/rj3NvU6vCioMLl2OK88nfXCmfQQCIu7ZHStNh1RglgXhP/f4L/rq0EjNulK4qD4Us3FBO+7/s0OfYXncd/RI8UAm1ptGf5V4uqAkaxhZr806aed4KwpXFZU4iReFJYT2XXO8CZKfQN2Uh14jr1b79r0bOAHEuk5+EhQgT9KjtufMJjnQMoXTpMcxzfA0qkp4X2Qc61WnwT4l4k4XpYF+erMvO5rays7VwxLgHI+QThm1tCi0gkff3o1/z/ce3XqpuyuHdwRfiuF1gfw7XAgMBAAECggEBAIepXCByqnSeQf4HR3nVVOagcoDw0q2JIM8pZEnEtfVW1iD6z56x3iVSQPClMuv888e3dNVwtAU+ZlpzjfzF1rEAvud9p7jx2e8FQ2HMOr7J38qZskSOrIbNSta8NLtvZG8LzyHEJsUhxTUv03wdrUuXb82lqAZBei5R2iCSqu3ZY187ZfjCJiNCICRWQaS0wubEnXXQa88cWF3ftb2aCqegPzwD0Ko2zaCC3367w/Yk4zJsrTxVzlyM6PZlcaJdqSg/6b6jH10ngDYFxxo+AQ4bagyRVWdQ84K8Fn0q9IjhKVtp7+82YzvGTpAa4dDK4e2/EGfUxXRL7nLxUuVca7ECgYEAzWKwi0BY41/d6E+EKTnCQRXPVACVL8a1vqf1eQ5laSHkhvjS8OgAKYgu2rbwxvz1B0vHUO5vNS5cdqOLgnLf0mHH9fpZq4TKdOjKlA7xvXgVD5Jjm9jLgSK7VVBNBKp7LnCh2aCuVfjv7Ydho162OcWQVgzJzYaxryw/TvT3GbkCgYEA4vZRrFGZC1/206XnPJSib4sq1Xgmudct6isCeogqp830I9D7Hm69yKL6UfECjDDpBupOnkNEeku4kTimaNUMxJTT79TTMEbRiKy9+xnmrgBjkjxYskBzPtAMCnMZpeNn3jrWt9/kedgiwTJlxFgvNRqWCOOwXKljljT7gMbbdQ8CgYEAnEsNreo5uk2pwK9CE10wxfai33nSDZlZlMybsJOT+H0iOtP/MfRaq0BG54lvkP3OOM8hziSj3AR7uIycDZj9WkuurzDkK/HRX0YHYsQ8kcJfxInR4zcHJi4YAMQq1/Ij6yMrB0GPaT0W19q+ImRgp3YAcHsq1ow5iuRRCPTBVYECgYAgvYO+pe6781X55h7bYF2mVZ8SOEjt2hqngxjScD4nAtDLMeRn2XXLMaeGlovViWC0PKymq/F+6tlvKYrn6IP0/7srB7qHZk/ntXOae3wJccjrWYU6AY4ea4ixITV79rgPGNHMqKGe6gzpbcm8bzQwJuup0J6qX00cZ/w38XfLBQKBgQCRGpCY37e0rm7aFfGCDjRnAhMfHCRNGW6PUaTvZinNmhf5d+PwFEbCcESNQjzz2NSExhiNGHeGh5gTE4jWN9seaxD+K7SKPjFBEy6fUu/+eqveIXCMWkbxaizeFrvf9H4Y4PZtgCQUZcMMnuxfZXfM/8SSm4mzxsOG9ruaMx7KMQ==";
    }
}