<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/21
 * Time: 11:15
 */
namespace app\imone\controller;

use think\Controller;

class Base extends  Controller{

    public $merchant_code='';
    public $config =[];
    public function _initialize()
    {
        $cfg = config('lottery');
        $this->merchant_code = $cfg['MerchantCode'];
        $this->config = $cfg;

    }


    public function checkIp(){
        if($this->config['ipcheck']){
            $ip_addr = getClientIP();
            $config_ip = $this->config['whiteip'];
            if($ip_addr!=$config_ip){
                return false;
            }
        }
        return true;
    }


    public function errorinfo($msg){
        return $this->apiReturn(0,'',$msg);
    }



    public function apiReturn($code, $data = [], $msg = '', $count = 0, $other = [])
    {
        return json([
            'code' => $code,
            'data' => $data,
            'msg' => $msg
        ]);
    }


}