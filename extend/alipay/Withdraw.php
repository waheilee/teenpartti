<?php

namespace alipay;


use app\common\Api;

//支付宝
class Withdraw
{
    private $merid = '2019111518452794074';
    private $url = "http://www.dahm888.com/api.php/gateway/gateway_order";
    private $notify = "https://dey6kxqf.ganzaoji99.com/admin/withdraw/alipaynotify";
    private $publicKey = "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAni8f82gMKm/itatM8KqZ
v5s804jhPn8T4/N631Wdp/FkRGjskE5YYuuO1u7W42yB48AdVx1fH5HjQR6j9fNL
rU4tdAAZ2OmGPNz9aFpDP4BnAkMD+2/4ob6/AVKlfWMqSq2r4XwYUUfbu7XxoP4U
EUfUjepK/9qdKeimfMjNXXMotUUzOj7JmdjoQfP7SB/uJgnMw6cey9uxeiE+Kpb0
aZbeaUIFyffvLo0kS1LVAvLPBcqr2DhBavC/+Zpm6mA2IPH6tkueI/eJnKpxZNcA
NrcVIghdzEw30Tz8Na218l53zVEDywCokwrXLE7ywDppFK8uawJu0PHI0NEia3x5
9wIDAQAB";


    private $priKey = "MIIEvwIBADANBgkqhkiG9w0BAQEFAASCBKkwggSlAgEAAoIBAQCeLx/zaAwqb+K1
q0zwqpm/mzzTiOE+fxPj83rfVZ2n8WREaOyQTlhi647W7tbjbIHjwB1XHV8fkeNB
HqP180utTi10ABnY6YY83P1oWkM/gGcCQwP7b/ihvr8BUqV9YypKravhfBhRR9u7
tfGg/hQRR9SN6kr/2p0p6KZ8yM1dcyi1RTM6PsmZ2OhB8/tIH+4mCczDpx7L27F6
IT4qlvRplt5pQgXJ9+8ujSRLUtUC8s8FyqvYOEFq8L/5mmbqYDYg8fq2S54j94mc
qnFk1wA2txUiCF3MTDfRPPw1rbXyXnfNUQPLAKiTCtcsTvLAOmkUry5rAm7Q8cjQ
0SJrfHn3AgMBAAECggEBAJTAYf5mQNKDZqFKFk9XTr/VPsz5sj8wB0dcRpbQjzJI
GO8P8C3/zrQvKaLK9P7mofrHRZAPSc2JRjiNlMgL44V0t9+W3LeTWq3Pbul7wDNu
DvAcjxkagaewlTOsQX15DGMvkCu5o7CDr4mEnlWzuLFLaAGQarjRHuwzIKTFvAF8
d/8JpfLOgxGKg94FFwDchL8exBKhwy873Xh4rNJILJjsMfvZ8Zk+l5YSBKcAHLT6
yGx957575ruY61fxty3+tsKDvWtDR/unqPdRa7fWEuhaHQcyZ/YCo2E5UxL7k054
RVHzOW0OhvgsddmvMhYtkUrEJi/OHEikWbeGSxeZ2akCgYEA0FIFglq9wWrr7sRC
Gk9sDmYd0OPjcnoNGyfZpZaj84DP4efg/IFe0FGDYFNYwRrdsk5uNdddKpjiqMtY
+jMZo7WhsPiJQyLkqisIKd3rKrm8CW16ZFduJhyrgRzH0vrEYTiVBjZYdO3/jdnU
h1z+/hW+LtkUrB3v3Rc5SJbzxPsCgYEAwmN/32lNn7vKOLCwKtPtFC+MEM3KiT5/
30qXGJe3ggYyLHY69bjeXujUGkHfEgU0DpVlyiOEcCkI5f/MWMhZPZRIBrfex47b
ESDuOHwXyHwZlWJ3wS+rY92dNL7ny9JQb704U8wx2VpVqWSI82N3VfnOLBzEX/7e
Cbux6wtbdjUCgYEAqnWTIHkTsELHT5az6Ed4ycdxOk5e/Hs2YjQXedFr0oJimB1f
Ef7iEF/Cun04sLpFEfPvZosVJxf2z9uksQZDQpwK9H5KAu94YG3Zvjhih5F3dddp
QNXxwanQBFQChna/XjYoau7pLrTYWcAWkbTcyhgMiNUEf1n3vdeU/frW+SUCgYEA
vXWC1TnqKSw7nWNAYI2rfUgWSO56X6els/kcKSD9/2GQl1sofgQP4AsnZuwawZKG
uUwNKKXTWAn2nUziWwnySJ10Ue/11yQ6CYjCBhWUjJe/y6RsOcL/VddSeG00uKgk
M7QF6U6Uq1ZrNS1CeY3Rat+NfdsP3swmlu/0KbUpEJ0CgYAcvxRdRLxL0Nse3wHu
RIa4jM+kCDma7h0l8g8Csd2qZuecKqg1bSJY39jBaUu0IUirOAmBg/2X5nvE0ASs
9tGKPk3PLMCF0Ddpa28po9crmOnDo2kRnfAtjnVLQwjaeN6Lc1md2OuGoqn0nxDt
AwKOa22ybqRd2Go+kJoouFfydA==";
    private $pubkey2 = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAuz8VJ2NYH86ENDhYIZ98
YxpnKMExxgwrDg+K1SSagPOGjug8ZSdUtDXJNuYY5V54PUOgYoNBfSvvWhsLSAke
m1nH7WKLQe/h2RRQQk2HwV6+ft0il+UP0ZvHpbPuy+5q6v8oBmZZljA4u2MKy7Dt
fkRrFHwNaJci4QwE6o8NovnXRmGvLXUk9g8BoA0hbXvz/UBH/fDOkbMsI/Pm3I8O
bGkfJNsvk8j7L3Hcg7j/TgE2x3ybOs+++lhxRVSat3HX6z45OU+nBFXjmG6I3NaY
dN5L1D2zboui2H/J9Ae6a71fmWIaVRvkxMG4EsGwmkKv56Wso8vkZ7JAZeQ+ziUK
nwIDAQAB';

    public function sign($data, $pri_key, $signType = "RSA2")
    {
        $priKey = $pri_key;
        $res    = "-----BEGIN RSA PRIVATE KEY-----\r\n" .
            wordwrap($priKey, 64, "\r\n", true) .
            "\r\n-----END RSA PRIVATE KEY-----";

        ($res) or die('您使用的私钥格式错误，请检查RSA私钥配置');

        if ("RSA2" == $signType) {
            openssl_sign($data, $sign, $res, OPENSSL_ALGO_SHA256);
        } else {
            openssl_sign($data, $sign, $res);
        }
        $sign = base64_encode($sign);
        return $sign;
    }

    public function rsaSign($params, $rsaPrivateKey, $signType = "RSA2")
    {
        return self::sign(self::getSignSortContent($params), $rsaPrivateKey, $signType);
    }

    public function getSignSortContent($params)
    {
        ksort($params);
        $stringToBeSigned = "";
        $i                = 0;
        foreach ($params as $k => $v) {
            if ($v != "") {
                // 转换成目标字符集
                $v = iconv('GBK//IGNORE', 'UTF-8', $v);
                if ($i == 0) {
                    $stringToBeSigned .= "$k" . "=" . "$v";
                } else {
                    $stringToBeSigned .= "&" . "$k" . "=" . "$v";
                }
                $i++;
            }
        }
        unset ($k, $v);
        return $stringToBeSigned;
    }


    public function verify($data, $sign, $rsaPublicKey, $signType = 'RSA2')
    {
        //转换为openssl格式密钥
        $pubKey = $rsaPublicKey;
        $res    = "-----BEGIN PUBLIC KEY-----\r\n" .
            wordwrap($pubKey, 64, "\r\n", true) .
            "\r\n-----END PUBLIC KEY-----";


        ($res) or die('公钥错误。请检查公钥文件格式是否正确');
        //save_log("apidata/withdrawalipaynotify", $sign . ' ' . $res . ' ' . openssl_verify($data, base64_decode($sign), $res, OPENSSL_ALGO_SHA256));

        //调用openssl内置方法验签，返回bool值

        $result = FALSE;
        if ("RSA2" == $signType) {

            $result = (openssl_verify($data, base64_decode($sign), $res, OPENSSL_ALGO_SHA256) === 1);
        } else {
            $result = (openssl_verify($data, base64_decode($sign), $res) === 1);
        }

        return $result;
    }

    public function rsaCheckV1($params, $rsaPublicKey, $signType = 'RSA2')
    {
        $sign = $params['sign'];
//        var_dump($params);
//        die;
        $params['sign_type'] = null;
        $params['sign']      = null;
        return $this->verify($this->getSignSortContent($params), $sign, $rsaPublicKey, $signType);
    }

    /**
     * curl模拟POST
     * @return string
     */
    public function curlPost($url, $var, $timeout = 120, $theHeaders = null, $isProxy = false)
    {
        $curl    = curl_init();
        $referer = '';
        if (stripos($url, 'https://') !== false) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        curl_setopt($curl, CURLOPT_URL, $url);
//        if ($isProxy) {
//            curl_setopt($curl, CURLOPT_PROXY, PROXYURL);
//        }
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);

        curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
        curl_setopt($curl, CURLOPT_NOSIGNAL, 1);
        if ($theHeaders) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $theHeaders);
        }
        if (!empty($referer)) {
            curl_setopt($curl, CURLOPT_REFERER, $referer);
        }
        //curl_setopt($curl, CURLOPT_COOKIEJAR, 'cookies.txt');
        //curl_setopt($curl, CURLOPT_COOKIEFILE, 'cookies.txt');
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $var);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $data['str']    = curl_exec($curl);
        $data['status'] = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $data['errno']  = curl_error($curl);

        return $data;
    }


    //下单
    public function addOrder($ordernumber, $account, $amount)
    {
        $data = [
            'merchant_biz_no' => $ordernumber, //商户订单号
            'id_merchant'     => $this->merid, //商户号 PID
            'amount'          => $amount,      //订单金额 元
            'payee_account'   => $account, //户号(银行卡号);  //支付宝账号
            'notify_url'      => $this->notify
        ];

        $data['sign'] = $this->rsaSign($data, $this->priKey, 'RSA2');
        $res          = $this->curlPost($this->url, $data, 20);
        $ret = $res;
        save_log('apidata/withdrawalipayadd', json_encode($res, JSON_UNESCAPED_UNICODE));
//        var_dump($res);
//        die;
        return $ret['str'];
    }

    //回调
    public function notify($get)
    {
        $flag = $this->rsaCheckV1($get, $this->pubkey2, "RSA2");
        if ($flag) {
            if ($get['code'] == 0) {
                //更新打款成功状态
                Api::getInstance()->sendRequest([
                    'roleid'    => 0,
                    'orderid'   => $get['merchant_biz_no'],
                    'status'    => 5,
                    'checkuser' => '系统处理',
                    'descript'  => '提现成功',
                ], 'charge', 'updatecheck');
                save_log('apidata/withdrawalipaynotify', 'orderid:' . $get['merchant_biz_no'] . ' SUCCESS');
                echo "success";
            } else {
                //更新打款失败状态
                Api::getInstance()->sendRequest([
                    'roleid'    => 0,
                    'orderid'   => $get['merchant_biz_no'],
                    'status'    => 6,
                    'checkuser' => '系统处理',
                    'descript'  => '支付宝处理未通过',
                ], 'charge', 'updatecheck');
                save_log('apidata/withdrawalipaynotify', 'orderid:' . $get['merchant_biz_no'] . ' fail');
                echo 'fail';
            }
        } else {
            save_log("apidata/withdrawalipaynotify", 'sign wrong ' . json_encode($flag, JSON_UNESCAPED_UNICODE));
        }
    }
}