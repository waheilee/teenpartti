<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/5
 * Time: 22:12
 */

namespace hwepay;

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
        $out_order_no = trim($OrderNo);
        $amount       = sprintf('%.2f',$order['RealMoney']);
        $pay_type     = $config['code'];
        $notify_url   = trim($config['notify_url']);
        $postdata = [
            'merchant_no'  =>$merchant_no,
            'out_order_no' =>$out_order_no,
            'amount'       =>$amount,
            'pay_type'     =>$pay_type,
            'notify_url'   =>$notify_url
        ];

        if ($type == 'UPI') {
            return [
                'status'=>false,
                'message'=>'不支持UPI'
            ];
            $data = [
                'bankType'=>'2',
                'bankCardNo'=>trim($order['CardNo']),
            ];
            $postdata = array_merge($postdata,$data);
        } elseif ($type == 'IMPS') {
            // $data = [
            //     'pay_account'=>trim($order['CardNo']),
            //     'pay_name'=>trim($order['RealName']),
            //     'pay_ifsc'=>trim($order['Province'])
            // ];
            $data = [
                'accountname'=>trim($order['RealName']),
                'cardnumber'=>trim($order['CardNo']),
                'ifsc'=>trim($order['Province']),
                'bankname'=>trim($order['BankName'])
            ];
            $postdata = array_merge($postdata,$data);
        }
        $postdata['sign'] =$this->createSign($config['secret'],$postdata);

        $header = [
            
        ];
        $result =$this->curl_post_content($this->api_url .'/api/cashOut',http_build_query($postdata), $header);
        save_log('hwepay','post:'.json_encode($postdata).',output:'.$result);
        $res = json_decode($result, true);
        $result =['system_ref'=>'','message'=>''];
        if ($res) {
            if($res['code'] == '2'){
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