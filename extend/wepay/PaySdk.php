<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

namespace wepay;

use Utility\Utility;
use think\facade\Cache;

class PaySdk
{


    private $api_url = '';
    private $notify_url = '';
    private $appid = '';
    private $secret = '';

    public function __construct()
    {
        $this->api_url = 'https://payment.wepaygloabal.net';
        $this->merchant = '';
        $this->secretkey = '';
    }


    public function payout($OrderNo,$order,$config=[],$bccode){

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
        if($order['PayWay']==2){
            $type ='IMPS';
        }
        else{
            $result['system_ref'] = '';
            $result['status'] = false;
            $result['message'] = '该通道不支持UPI代付';
            return $result;
        }

        $postdata=[
            'mch_id' =>$this->appid,
            'mch_transferId' => $OrderNo,
            'transfer_amount' =>$order['RealMoney'],
            'apply_date'=>$order['AddTime'],
            'bank_code' => 'IDPT0001',//$bccode,
            'receive_name' => trim($order['RealName']),
            'receive_account'=>trim($order['CardNo']),
            'remark'=>trim($order['Province']),//ifsc
            'back_url'=>$config['notify_url']
        ];

        $postdata['sign'] =$this->genSign($postdata,$config['secret']);
        $postdata['sign_type'] ='MD5';
        $header = [
            'Content-Type: application/x-www-form-urlencoded;charset=utf-8',
        ];
        $result = $this->curl_post_content($config['apiurl'] .'/pay/transfer', http_build_query($postdata), $header);
        save_log('wepay','post:'.json_encode($postdata).',output:'.$result);
        $res = json_decode($result, true);
        $result =['system_ref'=>'','message'=>''];
        if ($res) {
            if($res['respCode'] == 'SUCCESS'){
                $result['system_ref'] = '';
                $result['status'] = true;
                $result['message'] = 'success';
            }
            else
            {
                $result['message'] =$res['errorMsg'];
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
        return strtolower(md5($md5str . 'key=' . $Md5key));
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