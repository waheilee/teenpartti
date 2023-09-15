<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/5
 * Time: 22:12
 */

namespace tpay;

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
        $this->api_url = 'https://api.tpay9.com/app/pay/pay.php';
        $this->appid = '1862';
        $this->secret = 'dDcD9Rm2XEDRI3Zv8ApZYRsqKuuASIuU';
    }


    public function pay($param,$config=[])
    {

        if($config){
            $this->appid =$config['appid'];
            $this->secret = $config['secret'];
        }

        $post_data =[
            'appid'=> $this->appid,
            'cliIP' => $param['ip'],
            'cliNA' => 'webBrowser',
            'uid' => $param['roleid'],
            'order'=> $param['orderid'],
            'price'=> $param['amount'],
            'payBank'=> 'SCB',
            'payAcc' =>  ''
        ];
        $sn = $this->genSn($post_data,$this->secret);

        $post_data['notifyUrl'] =urlencode('http://service.crazyfunslots.com/client/notify/tPayNotify');
        $post_data['sn'] = $sn;
        $info = $this->http($this->api_url,'GET',$post_data);
        return $info;
    }



    public function payout($OrderNo,$order,$config=[]){
        if($config){
            $this->appid =$config['appid'];
            $this->secret = $config['secret'];
            $this->api_url = $config['apiurl'];
        }

        $post_data =[
            'appid'=> $this->appid,
            'orderId' => $OrderNo,
            'name' =>  $order['RealName'],
            'money' => $order['RealMoney'],
            'bankMark'=> $order['BankName'],
            'recAcc'=> $order['CardNo'],
            'remark'=> ''
        ];
        $sn = $this->genSn($post_data,$this->secret);
        $post_data['notifyUrl'] =urlencode('http://service.crazyfunslots.com/client/notify/tPayOutNotify');
        $post_data['sn'] = $sn;
        $info = $this->http_Get($this->api_url,'GET',$post_data);
        $transactionid ='RE'.$OrderNo;
        $result =['system_ref'=>$transactionid,'message'=>$info];
        if($info=='ok'){
            $result['status'] =true;
            $result['message'] ='success';
        }
        else
        {
            $result['status'] =false;
        }
        return $result;
    }


    public function  getPayOutStatus($orderno,$config=[]){
        if($config){
            $this->appid =$config['appid'];
            $this->secret = $config['secret'];
            $this->api_url = $config['apiurl'];
        }

        $post_data =[
            'appid'=> $this->appid,
            'orderId' => $orderno
        ];
        $sn = $this->genSn($post_data,$this->secret);
        $post_data['sn'] = $sn;
        $info = $this->http_Get($this->api_url,'GET',$post_data);
        $transactionid ='RE'.$orderno;
        $result =['system_ref'=>$transactionid,'message'=>'faild'];
        if($info=='ok'){
            $result['status'] =true;
            $result['message'] ='success';
        }
        else
        {
            $result['status'] =false;
        }
        return $result;
    }


    private function http($url = '', $method = 'POST', $postData = array(), $header = array()) {
        if (!empty($url)) {
            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30); //30秒超时
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

                if ($header) {
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
                }

                if (strtoupper($method) == 'POST') {
                    $curlPost = is_array($postData) ? http_build_query($postData) : $postData;
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
                }

                if (strtoupper($method) == 'GET') {
                    curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($postData));
                }
                $res      = curl_exec($ch);
                $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $err      = curl_errno($ch);
                curl_close($ch);

                if ($err) {
                    return false;
                }
                $res = json_decode($res, true);
                if ($httpcode != 200) {
                    return false;
                }
                if ($res['e']!=0){
                    return false;
                }
                return $res;
            } catch (Exception $e) {
                return false;
            }
        }
        return false;
    }


    private function http_Get($url = '', $method = 'POST', $postData = array(), $header = array()) {
        if (!empty($url)) {
            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30); //30秒超时
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

                if ($header) {
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
                }

                if (strtoupper($method) == 'POST') {
                    $curlPost = is_array($postData) ? http_build_query($postData) : $postData;
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
                }

                if (strtoupper($method) == 'GET') {
                    curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($postData));
                }
                $res      = curl_exec($ch);
                save_log('tpayorder','提交状态：'.$res);
                $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $err      = curl_errno($ch);
                curl_close($ch);

                if ($err) {
                    return false;
                }
                if ($httpcode != 200) {
                    return false;
                }
                return $res;
            } catch (Exception $e) {
                return false;
            }
        }
        return false;
    }


    private function genSn($GET, $secret)
    {
        ksort($GET);
        $arr = [];
        foreach ($GET as $k => $v) {
            $arr[] = "{$k}=" . urlencode($v);
        }
        $arr[] = "secret={$secret}";
        return md5(join('', $arr));
    }


}