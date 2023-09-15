<?php

namespace app\Bti\controller;

use app\model\AccountDB;
use app\model\Role;
use app\model\ThirdGameReport;
use redis\Redis;
use socket\QuerySocket;
use think\Exception;


class Index extends Base
{


    public function _initialize()
    {
        parent::_initialize();
        
        header('Access-Control-Allow-Origin:*');
        //允许的请求头信息
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");
        //允许的请求类型
        header('Access-Control-Allow-Methods: GET, POST, PUT,DELETE,OPTIONS,PATCH');
        //允许携带证书式访问（携带cookie）
        header('Access-Control-Allow-Credentials:true');
        session_start();
        save_log('bti', '==='.$this->request->url().'===接口请求数据===' . json_encode($this->request->param()));

    }


    //游戏对外接口创建玩家
    public function createuser()
    {
        try {
            $param = jsonRequest(['roleid', 'gameid','language','time', 'sign']);
            $clientkey = config('clientkey');
            $key = md5($param['roleid'] . $param['gameid'] . $param['language'] . $param['time'] . $clientkey);
            if(empty($param['roleid']) || empty($param['gameid']) ||empty($param['time']) ||empty($param['sign'])){
                return $this->failjson('Missing parameter');
            }
            if ($key != strtolower($param['sign'])) {
                return $this->failjson('sign is error');
            }
            $roleid = $param['roleid'];
            $accountM = new AccountDB();
            $user = $accountM->getRow(['AccountID' => $roleid], 'AccountID,AccountName');
            if (!$user) {
                return $this->failjson('the player is not exist');
            }
            //创建账户
            if (empty(Redis::get('bti_'.$roleid))) {
                $ret = $this->createPlayer($roleid);
                if ($ret == false) {
                    return $this->failjson('create player faild');
                }
            }
            $lang = $param['language'] ?: 'en';
            $orderid = $this->makeOrderId($roleid);
            $balance = $this->getbalance($roleid);
            if ($balance > 0) {
                // return $this->failjson('余额不足');
                //平台下分
                $res = $this->downScore($roleid,$balance,$orderid);
                if ($res == false) {
                    return $this->failjson('平台下分失败');
                }
                //bti上分
                $res = $this->btiTransfer($roleid,$balance,$orderid,1);
                if ($res == false) {
                    //平台余额回退
                    $this->UpScore($roleid,$balance,$orderid);
                    return $this->failjson('转入第三方平台失败');
                }
            }
            
            //获取游戏登录链接
            $url = $this->getbtiUrl($roleid,$lang);
            if ($url) {
                return $this->succjson($url."&sportid=59");
            } else {
                 return $this->failjson('获取第三方平台游戏链接失败');
            }
        } catch (Exception $ex) {
            save_log('bti_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjson('api error');
        }
    }

    //退出三方游戏
    public function exitbti()
    {
        try {
            $param = jsonRequest(['roleid', 'time', 'sign']);
            $clientkey = config('clientkey');
            $key = md5($param['roleid'] . $param['time'] . $clientkey);
            if ($key != $param['sign']) {
                // return $this->apiReturn(100, '', 'The Sign is check error');
            }

            $roleid = $param['roleid']?:83998744;
            $accountM = new AccountDB();
            $user = $accountM->getRow(['AccountID' => $roleid], 'AccountID,AccountName');
            if (!$user) {
                return $this->failjson('the player is not exist');
            }

            $orderid = $this->operatorId.'_'.$this->makeOrderId($roleid);
            $balance = $this->btiBalance($roleid);
            if ($balance > 0) {
                //bti下分
                $res = $this->btiTransfer($roleid,$balance,$orderid,0);
                if ($res == false) {
                    //平台余额回退
                    return $this->failjson('转出第三方平台失败');
                }

                //平台加钱
                $this->UpScore($roleid,$balance,$orderid);
            }
            return $this->succjson( '');
        } catch (Exception $ex) {
            save_log('bti_error', $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjson('Api Error');
        }

    }
    //-------------------------------------------------------------------------------------//

    private function createPlayer($roleid){
        try {
            $post_param = [
                'AgentUserName'=>$this->AgentUserName,
                'AgentPassword'=>$this->AgentPassword,
                'MerchantCustomerCode'=>'bit_'.$roleid,
                'LoginName'=>'bit_'.$roleid,
                'CurrencyCode'=>$this->Currency,
                'CountryCode'=>$this->country,
                'City'=>$this->country,
                'FirstName'=>'bit_',
                'LastName'=>$roleid,
                'Group1ID'=>0,
                'CustomerMoreInfo'=>'',
                'CustomerDefaultLanguage'=>$this->language,
                'DomainID'=>'',
                'DateOfBirth'=>'',
            ];
            $header = [
                'content-type:application/x-www-form-urlencoded'
            ];
            $url = $this->url.'/createUserNew/';
            $result = $this->curl($url,$post_param,$header);
            save_log('bti', '===第三方玩家创建===' . $result);
            $result = json_decode($result,1);
            if ($result['ErrorCode'] == 'NoError' || $result['ErrorCode'] == 'DuplicateMerchantCustomerCode') {
                Redis::set('bti_'.$roleid, 'bti_'.$roleid);
                return true;
            } else {
                return false;
            }
        } catch (Exception $ex) {
            save_log('bti_error', $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return false;
        }
    }

    //与bti转账
    private function btiTransfer($roleid,$balance,$orderid,$type){
        //type 0: 从体育转出; 1: 转入体育
        try {
            $post_param = [
                'AgentUserName'=>$this->AgentUserName,
                'AgentPassword'=>$this->AgentPassword,
                'MerchantCustomerCode'=>'bit_'.$roleid,
                'Amount'=>$balance,
                'RefTransactionCode'=>$orderid,
            ];
            $header = [
                'content-type:application/x-www-form-urlencoded'
            ];
            if ($type == 0) {
                $url = $this->url.'/TransferFromWHL/';
            } else if ($type == 1) {
                $post_param['BonusCode'] = '';
                $url = $this->url.'/TransferToWHL/';
            }
            
            $result = $this->curl($url,$post_param,$header);
            save_log('bti', '===第三方玩家转账===' . $result);
            $result = json_decode($result,1);
            if ($result['ErrorCode'] == 'NoError') {
                return true;
            } else {
                return false;
            }
        } catch (Exception $ex) {
            save_log('bti_error', $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return false;
        }
    }

    //获取玩家在bti中的余额
    private function btiBalance($roleid){
        try {
            $post_param = [
                'AgentUserName'=>$this->AgentUserName,
                'AgentPassword'=>$this->AgentPassword,
                'MerchantCustomerCode'=>'bit_'.$roleid,
            ];
            $header = [
                'content-type:application/x-www-form-urlencoded'
            ];
            $url = $this->url.'/GetBalance/';
            $result = $this->curl($url,$post_param,$header);
            save_log('bti', '===第三方玩家查询余额===' . $result);
            $result = json_decode($result,1);
            if ($result['ErrorCode'] == 'NoError') {
                return $result['Balance'];
            } else {
                return 0;
            } 
        } catch (Exception $ex) {
            save_log('bti_error', $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return 0;
        }
    }

    //获取玩家在bti中的authentication
    private function btiAuthentication($roleid){
        try {
            $post_param = [
                'AgentUserName'=>$this->AgentUserName,
                'AgentPassword'=>$this->AgentPassword,
                'MerchantCustomerCode'=>'bit_'.$roleid,
            ];
            $header = [
                'content-type:application/x-www-form-urlencoded'
            ];
            $url = $this->url.'/GetCustomerAuthToken/';
            $result = $this->curl($url,$post_param,$header);
            save_log('bti', '===第三方玩家查询uthentication===' . $result);
            $result = json_decode($result,1);
            if ($result['ErrorCode'] == 'NoError') {
                return $result['AuthToken'];
            } else {
                return '';
            } 
        } catch (Exception $ex) {
            save_log('bti_error', $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return '';
        }
    }


    //获取bti游戏登录链接
    private function getbtiUrl($roleid,$lang){
       try {
            $authentication = $this->btiAuthentication($roleid);
            $url = "https://prod20190-108262014.1x2aaa.com/".$lang."/sports?operatorToken=".$authentication;
            return $url;
        } catch (Exception $ex) {
            save_log('bti_error', $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return '';
        } 
    }

    //获取玩家在本平台中的余额
    private function getbalance($roleid){
        try {
            $socket = new QuerySocket();
            $m = $socket->DSQueryRoleBalance($roleid);
            $gamemoney = $m['iGameWealth'] ?? 0;
            return bcdiv($gamemoney, bl, 3);
        } catch (Exception $ex) {
            save_log('bti_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return 0;
        }
    }


    //平台扣钱
    private function  downScore($roleid,$balance,$orderid){
        //type 0: 平台扣钱; 1: 平台加钱
        try {
            $socket = new QuerySocket();
            $balance = bcmul($balance, bl,0);
            $res = $socket->downScore($roleid, $balance, $orderid);
            return true;
        } catch (Exception $ex) {
            save_log('bti_error', $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return false;
        }
    }

    //平台加钱
    private function  UpScore($roleid,$balance,$orderid){
        try {
            $socket = new QuerySocket();
            $balance =  bcmul($balance,bl,0);
            $socket->UpScore($roleid, $balance, $orderid);
            return true;
        } catch (Exception $ex) {
            save_log('bti_error', $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return false;
        }
    }


    private function curl($url,$post_data='',$header=[],$type='get') {
        if ($post_data) {
            $type = 'post';
        }
        //初始化
        $curl = curl_init();
        //设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $url);
        //设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_HEADER, 0);
        //设置获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        if($type=='post'){
            //设置post方式提交
            curl_setopt($curl, CURLOPT_POST, 1);
            if(is_array($post_data)){
                $post_data = http_build_query($post_data);
            }
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
        }
        if($header){
            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        }
        //https
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        //执行命令
        $data = curl_exec($curl);
        // save_log('bti', '==='.$this->request->url().'===三方返回数据===' . $data);
        //关闭URL请求
        curl_close($curl);
        //显示获得的数据
        $data = simplexml_load_string($data);
        $data = json_encode($data);
        return $data;
    }

    //签名函数
    private function createsign($signstr)
    {
        return strtolower(md5($signstr.$this->securykey));
    }


    //创建临时订单
    private function makeOrderId($uid)
    {
        return date('YmdHis') . sprintf('%.0f', floatval(explode(' ', microtime())[0]) * 1000) . $uid;
    }
}