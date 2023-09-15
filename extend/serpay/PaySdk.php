<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

namespace serpay;

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


    public function payout($OrderNo,$order,$config=[],$bccode){

        if (empty($config)) {
            return array('status'=>FALSE, 'message' => 'Missing parameter');
        }
        if (isset($config['appid']) && !empty($config['appid'])) {
            $this->appid = $config['appid'];
        }

        if (isset($config['orgno']) && !empty($config['orgno'])) {
            $this->orgno = $config['orgno'];
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
        if($order['PayWay']==2){
            $type ='IMPS';
        }

        $postdata=[
            'version'=>'2.1',
            'orgNo'=>$this->orgno,
            'custId' =>$this->appid,
            'custOrdNo' => $OrderNo,
            'casType'=>'00',
            'country'=>'IN',
            'currency'=>'INR',
            'casAmt' =>$order['RealMoney']*100,
            'deductWay'=>'02',
            'callBackUrl'=>$config['notify_url'],
            'account'=>'211225000023810214',
        ];
        $data=[];

        $rand = rand(6,15);
        $rand_ext= rand(0,2);
        $mailext =['@gmail.com','@hotmail.com','@mail.yahoo.com'];
        $mailname=$this->random_str($rand);
        $usermail = $mailname.$mailext[$rand_ext];


        if ($type == 'UPI') {
            $data = [
                'payoutType' =>'UPI',
                'accountName' => trim($order['RealName']),
                'upiId' =>  trim($order['CardNo']),
                'phone'=>'9774867890',
                'email' =>$usermail
            ];
            $postdata = array_merge($postdata,$data);
        } elseif ($type == 'IMPS') {
            $data = [
                'payoutType' =>'Card',
                'accountName' => trim($order['RealName']),
                'cardType' => 'IMPS',
                'payeeBankCode'=>$bccode,
                'cnapsCode'=>trim($order['Province']),//ifsc
                'cardNo'=>trim($order['CardNo']),
                'phone'=>'9774867890',
                'email' => $usermail
            ];
            $postdata = array_merge($postdata,$data);
        }
        $postdata['sign'] =$this->genSign($postdata,$config['secret']);
        $header = [
            'Content-Type: application/x-www-form-urlencoded;charset=utf-8',
        ];
        $result = $this->curl_post_content($config['apiurl'] .'/cashier/TX0001.ac', http_build_query($postdata), $header);
        save_log('serpay','post:'.json_encode($postdata).',output:'.$result);
        $res = json_decode($result, true);
        $result =['system_ref'=>'','message'=>''];
        if ($res) {
            if($res['code'] == '000000'){
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

    private function genSign($data,$Md5key)
    {
        ksort($data);
        $md5str = '';
        foreach ($data as $key => $val) {
            if (!empty(trim($val))) {
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