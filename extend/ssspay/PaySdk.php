<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

namespace ssspay;

use Utility\Utility;
use think\facade\Cache;

class PaySdk
{


    private $api_url = '';
    private $notify_url = '';
    private $appid = '';
    private $secret = '';
    private $orgno ='';

    public function __construct()
    {
        $this->api_url = 'https://api.serpayment.com';
        $this->merchant = '';
        $this->secretkey = '';
        $this->orgno ='';
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
        // 替换成用户选择的银行
//        if (!isset($order['BankName']) || empty($order['BankName'])) {
//            return array('status'=>FALSE, 'message' => 'Missing BankName');
//        }
        // 这里指定 支付方式 UPI/IMPS 根据支付方式确定传参对象
        $type = 'UPI';
        $banktype =2;
        if($order['PayWay']==2){
            $type ='IMPS';
            $banktype =1;
        }

        $postdata=[
            'userID'=>$this->appid,
            'amount' =>$order['RealMoney'],
            'payeeName'=>trim($order['RealName']),
            'payeePhone'=>'13122336688',
            'payType'=>$banktype,
            'stamp'=>time(),
            'orderID'=>$OrderNo,
            'notifyUrl'=>$config['notify_url'],

        ];
        $sign =$this->genSign($postdata,$config['secret']);
        if ($type == 'UPI') {
            $subdata=[
                'bankCardOwnerName'=>trim($order['RealName']),
                'bankCardName'=>'1',
                'accountNo'=>'1',
                'vpaAddress'=>$order['CardNo']
            ];
            $postdata = array_merge($postdata,$subdata);
        } elseif ($type == 'IMPS') {
            $subdata=[
                'bankCardOwnerName'=>trim($order['RealName']),
                'bankCardName'=>$order['Province'],
                'accountNo'=>trim($order['CardNo']),
                'vpaAddress'=>'1'
            ];
            $postdata = array_merge($postdata,$subdata);
        }
        $postdata['sign']=$sign;
        $header = [
            'Content-Type: application/x-www-form-urlencoded;charset=utf-8',
        ];
        $result = $this->curl_post_content($config['apiurl'] .'/starpay/pay/daifu', http_build_query($postdata), $header);
        save_log('ssspay','post:'.json_encode($postdata).',output:'.$result);
        $res = json_decode($result, true);
        $result =['system_ref'=>'','message'=>''];
        if ($res) {
            if($res['code'] == 0){
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




    private function genSign($data,$Md5key)
    {
        ksort($data);
        $md5str = '';
        foreach ($data as $key => $val) {
            if (trim($val)!=='') {
                $md5str = $md5str . $key . '=' . $val . '&';
            }
        }
        return strtoupper(md5($md5str . 'key=' . $Md5key));
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