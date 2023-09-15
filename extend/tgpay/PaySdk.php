<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/5
 * Time: 22:12
 */

namespace tgpay;

class PaySdk
{

    private $api_url = '';
    private $notify_url = '';
    private $appid = '';
    private $secret = '';


    public function __construct()
    {
        $this->api_url = 'https://qwe188.com/';
        $this->merchant = '';
        $this->secretkey = '';
    }


    public function pay($param, $config = [])
    {
        if ($config) {
            $this->appid = $config['appid'];
            $this->secret = $config['secret'];
        }
        $requestarray = array(
            'pay_memberid' => $this->appid,                             //填写你的商户号
            'pay_orderid' => $param['orderid'],                         //商户订单
            'pay_userid' => $param['roleid'],                           //商户应用内发起支付的用户的userid，即商户那边的userid
            'pay_applydate' => $param['paytime'],                       //请求时间
            'pay_bankcode' => $config['code'],                                    //请求的支付通道ID
            'pay_notifyurl' => $config['notify_url'],                   //支付通知地址		(填写你的支付通知地址)
            'pay_callbackurl' => $config['notify_url'],                 //同步前端跳转地址	(填写你需要的前端跳转地址)
            'pay_amount' => strval($param['amount'])
        );
        $md5keysignstr = $this->createSign($this->secret, $requestarray);       //生成签名
        $requestarray['pay_md5sign'] = $md5keysignstr;                          //签名后的md5码
        $requestarray['pay_productname'] = 'ID' . $param['roleid'];          //支付订单的名字
        $requestHttpDate = http_build_query($requestarray);                     //转换为URL键值对（即key1=value1&key2=value2…）
        $curl_result = $this->httpRequestDataTest($this->api_url . '/Pay_Index.html', $requestHttpDate);    //发送http的post请求
        $res = json_decode($curl_result, true);
        if (!empty($res['status'])) {
            $res['message'] = 'Http Request Invalid';
            //exit('Http Request Invalid');
        }
        $returl = '';
        if ($res['status'] == 'success') {
            if (!empty($res['data']['payurl'])) {
                $returl = $res['data']['payurl'];
            }
        }
        return $returl;
    }


    public function payout($OrderNo, $order, $config = [])
    {
        if (empty($config)) {
            return array('status' => FALSE, 'message' => 'Missing parameter');
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
            return array('status' => FALSE, 'message' => 'Missing notify_url');
        }

        $type = 'UPI';
        if ($order['PayWay'] == 2) {
            $type = 'IMPS';
        }

        $postdata = [
            'memberid' => $this->appid,
            'out_trade_no' => $OrderNo,
            'amount' => $order['RealMoney'],
            'notifyurl' => $config['notify_url']
        ];

        if ($type == 'UPI') {
            $extend = ['bankifsc' => trim($order['Province']), 'phone' => '9774867890'];
            $data = [
                'bankname' => 'State Bank of India',
                'bankcode' => 'UPI',
                'channelcode' => $config['channelcode'],
                'subbranch' => 'branch',
                'accountname' => trim($order['RealName']),
                'cardnumber' => trim($order['CardNo']),
                'province' => 'mumbai',
                'city' => 'mumbai',
                'extends' => 'empty'
            ];
            $postdata = array_merge($postdata, $data);
        } elseif ($type == 'IMPS') {
            $extend = ['bankifsc' => trim($order['Province']), 'phone' => '9774867890'];
            $data = [
                'bankname' => $order['BankName'],
                'bankcode' => 'BANK',
                'channelcode' => $config['channelcode'],
                'subbranch' => 'branch',
                'accountname' => trim($order['RealName']),
                'cardnumber' => trim($order['CardNo']),
                'province' => 'mumbai',
                'city' => 'mumbai',
                'extends' => base64_encode(json_encode($extend))
            ];
            $postdata = array_merge($postdata, $data);
        }
        $postdata['sign'] = $this->createSign($config['secret'], $postdata);
        $requestHttpDate = http_build_query($postdata);
        $result = $this->httpRequestDataTest($this->api_url . '/Payment_Dfpay_add.html', $requestHttpDate);    //发送http的post请求
        save_log('tgpay', 'post:' . json_encode($postdata) . ',output:' . $result);
        $res = json_decode($result, true);
        $result = ['system_ref' => '', 'message' => ''];
        if ($res) {
            if ($res['status'] == 'success') {
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

    public function payoutBrazil($OrderNo, $order, $config = [])
    {
        if (empty($config)) {
            return array('status' => FALSE, 'message' => 'Missing parameter');
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
            return array('status' => FALSE, 'message' => 'Missing notify_url');
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
            'memberid' => $this->appid,
            'out_trade_no' => $OrderNo,
            'amount' => sprintf('%.2f', $order['RealMoney']),
            'cpf' => $CPF,
            'pixtype' => $pixtype,
            'pixnumber' => $pixkey,
            'accountname' => trim($order['RealName']),
            'notifyurl' => $config['notify_url'],
            'extends' => ''
        ];

        $postdata['sign'] = $this->createSign($config['secret'], $postdata);
        $requestHttpDate = http_build_query($postdata);
        $result = $this->httpRequestDataTest($this->api_url . '/Payment_Dfpay_add.html', $requestHttpDate);    //发送http的post请求
        save_log('tgpay', 'post:' . json_encode($postdata) . ',output:' . $result);
        $res = json_decode($result, true);
        $result = ['system_ref' => '', 'message' => ''];
        if ($res) {
            if ($res['status'] == 'success') {
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

    public function payoutPhp($OrderNo, $order, $config = [])
    {
        if (empty($config)) {
            return array('status' => FALSE, 'message' => 'Missing parameter');
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
            return array('status' => FALSE, 'message' => 'Missing notify_url');
        }


        $postdata = [
            'memberid' => $this->appid,
            'out_trade_no' => $OrderNo,
            'amount' => sprintf('%.2f', $order['RealMoney']),
            'accountno'=>trim($order['CardNo']),
            'mobilenumber'=>trim($order['City']),
            'accountname'=>trim($order['RealName']),
            'bankcode'=>trim($order['Province']),
            'notifyurl' => $config['notify_url'],
            'extends' => '123'
        ];

        $postdata['sign'] = $this->createSign($config['secret'], $postdata);
        $requestHttpDate = http_build_query($postdata);
        $result = $this->httpRequestDataTest($this->api_url . '/Payment_Dfpay_add.html', $requestHttpDate);    //发送http的post请求
        save_log('tgpay', 'post:' . json_encode($postdata) . ',output:' . $result);
        $res = json_decode($result, true);
        $result = ['system_ref' => '', 'message' => ''];
        if ($res) {
            if ($res['status'] == 'success') {
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
                $this->api_url = $config['apiurl'];
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
            save_log('tgpay', 'query order post:' . json_encode($postdata) . ',output:' . $result);
            $res = json_decode($result, true);
            return $res;
        }
    }


    //签名函数
    protected function createSign($Md5key, $list)
    {
        ksort($list); //按照ASCII码排序
        $tempstr = "";
        foreach ($list as $key => $val) {
            if (!empty($val)) {
                $tempstr = $tempstr . $key . "=" . $val . "&";
            }
        }
        $md5str = md5($tempstr . "key=" . $Md5key);    //最后拼接上key=ApiKey(你的商户秘钥),进行md5加密
        $sign = strtoupper($md5str);                //把字符串转换为大写，得到sign签名
        return $sign;
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
}