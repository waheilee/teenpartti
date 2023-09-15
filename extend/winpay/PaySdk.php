<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/5
 * Time: 22:12
 */

namespace winpay;

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
        $this->secret = '';
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
        $orderId      = trim($OrderNo);
        $amount       = sprintf('%.2f',$order['RealMoney']);
        $notify_url   = trim($config['notify_url']);
        $mobile      = rand(6,9).rand(100000000,999999999);
        $email       = $mobile.'@gmail.com';
        $postdata = [
            'channelId'     =>$merchant_no,
            'channelOid'      =>$orderId,
            'amount'       =>$amount,
            'timestamp'    =>time().'000',
            'sign'        =>md5($merchant_no.$orderId.$amount.$this->secret),
            'notifyUrl'    =>$notify_url
        ];
    
        if ($type == 'UPI') {
            $postdata['mode'] = 'UPI';
            $postdata['fundAccount'] = [
                'accountType'=>'vpa',
                'contact'=>[
                    'name'        =>trim($order['RealName']),
                    'email'       =>$email,
                    'contact'     =>$mobile,
                    'type'        =>'vendor customer employee self',
                    'referenceId' =>$order['AccountID']
                ],
                'vpa'=>[
                    'address'=>trim($order['CardNo'])
                ]
            ];
        } elseif ($type == 'IMPS') {
            $postdata['mode'] = 'IMPS';
            $postdata['fundAccount'] = [
                'accountType'=>'bank_account',
                'contact'=>[
                    'name'        =>trim($order['RealName']),
                    'email'       =>$email,
                    'contact'     =>$mobile,
                    'type'        =>'vendor customer employee self',
                    'referenceId' =>$order['AccountID']
                ],
                'bankAccount'=>[
                    'name'=>trim($order['RealName']),
                    'ifsc'=>trim($order['Province']),
                    'accountNumber'=>trim($order['CardNo'])
                ]
            ];
            
        }

        $header = [
            'Content-Type:application/json;charset=UTF-8'
        ];
        
        $result =$this->curl_post_content($this->api_url,json_encode($postdata), $header);

        save_log('winpay','post:'.json_encode($postdata).',output:'.$result);
        $res = json_decode($result, true);
        $result =['system_ref'=>'','message'=>''];
        if ($res) {
            if($res['code'] == '0000'){
                $result['system_ref'] = '';
                $result['status'] = true;
                $result['message'] = 'success';
            }
            else
            {
                $result['message'] =$res['message'];
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
            if (!empty(trim($val))) {
                $md5str = $md5str . $key . '=' . $val . '&';
            }
        }
        $str =$md5str . 'key=' . $Md5key;
        return strtolower(md5($str));
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