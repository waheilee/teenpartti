<?php

namespace app\pngame\controller;

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
    }


    //游戏对外接口创建玩家
    public function createuser()
    {
        // try {
            $param = jsonRequest(['roleid', 'gameid', 'language', 'time', 'sign']);
            $clientkey = config('clientkey');
            $key = md5($param['roleid'] . $param['gameid'] . $param['language'] . $param['time'] . $clientkey);
            if ($key != strtolower($param['sign'])) {
                // return $this->failjson('sign is error');
            }

            $roleid = $param['roleid'] ?: '22008260';
            $gameid = $param['gameid'] ?: 'bookofdead';
            $language = $param['language'] ?: 'EN';
            $accountM = new AccountDB();

            $user = $accountM->getRow(['AccountID' => $roleid], 'AccountID,AccountName');
            if (!$user) {
                return $this->failjson('the player is not exist');
            }
            //创建用户
            $post_data = [
                'playerId'   =>$roleid,
                'playerName' =>$user['AccountName'],
                'currency'   =>$this->config['Currency'],
                'country'    =>$this->config['country'],
                'language'   =>$language,
                'brandId'    =>$this->config['Merchant_ID'],
                'requestId'  =>md5(uniqid().rand(100000000,999999999))
            ];
            $hash = $this->createsign($post_data,$this->config['Secret_Key']);
            $header = [
                'content-type:application/json'
            ];
            $result = $this->curl($this->config['API_Host'].'/player/create?hash='.$hash,json_encode($post_data),$header);
            $result = json_decode($result,true);
            save_log('pngame', '第三方玩家创建===' . json_encode($result));

            if ($result['error'] == 'B_01' || $result['error'] ==0) {
                
                $token = $this->getToken($roleid);
                if ($token == false) {
                    return $this->failjson('get token error');
                }
                $socket = new QuerySocket();
                //先扣 后上分
                $balance    = $this->getBalance($roleid);
                $transferid = $this->makeOrderId($roleid);
                
                if ($balance > 0) {
                    $m = $socket->getPlayerBalance($roleid);
                    if ($m['iResult']) {
                        return $this->failjson('余额查询错误');
                    }
                    $post_data = [
                        'playerId'   =>$roleid,
                        'currency'   =>$this->config['Currency'],
                        'amount'     =>$balance,
                        'extTransId' =>$transferid,
                        'brandId'    =>$this->config['Merchant_ID'],
                        'requestId'  =>md5(uniqid().rand(100000000,999999999))
                    ];
                    $hash = $this->createsign($post_data,$this->config['Secret_Key']);
                    $header = [
                        'content-type:application/json'
                    ];
                    $result = $this->curl($this->config['API_Host'].'/payment/player/deposit?hash='.$hash,json_encode($post_data),$header);
                    $result = json_decode($result,true);

                    save_log('pngame', '第三方玩家上分===' . json_encode($result));
                    if ($result['error'] != 0) {
                        $socket->UpLotteryScore(0, $roleid, $balance*1000, 'Lessthen100');
                        return $this->failjson('upscore error');
                    }
                }

                $url = $this->config['GAME_URL'].'/launcher?token='.$token.'&gameCode='.$gameid.'&platform=web&language='.$language.'&playerId='.$roleid.'&brandId='.$this->config['Merchant_ID'];
                return $this->succjson($url); 
            }
            return $this->failjson('create play faild');
        // } catch (Exception $ex) {
        //     save_log('pngame', $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
        //     return $this->failjson('api error');
        // }
    }

    public function getGameList(){
        $post_data = [
            'brandId'    =>$this->config['Merchant_ID'],
            'requestId'  =>md5(uniqid().rand(100000000,999999999))
        ];
        $hash = $this->createsign($post_data,$this->config['Secret_Key']);
        $header = [
            'content-type:application/json'
        ];
        $result = $this->curl('http://gm-stage-data.gmgiantgold.com/game/list?hash='.$hash,json_encode($post_data),$header);

        var_dump(json_decode($result,true));
    }

    public function getToken($roleid){
        //获取玩家令牌
        $post_data = [
            'playerId'   =>$roleid,
            'brandId'    =>$this->config['Merchant_ID'],
            'requestId'  =>md5(uniqid().rand(100000000,999999999))
        ];
        $hash = $this->createsign($post_data,$this->config['Secret_Key']);
        $header = [
            'content-type:application/json'
        ];
        $result = $this->curl($this->config['API_Host'].'/player/getToken?hash='.$hash,json_encode($post_data),$header);
        $result = json_decode($result,true);
        save_log('pngame', '获取第三方玩家令牌===' . json_encode($result));
        if ($result['error'] == 0) {
            return $result['token'];
        } else {
            return false;
        }
    }


    //游戏对外接口下分
    public function downscore()
    {
        try {
            $param = jsonRequest(['roleid', 'time', 'sign']);
            $clientkey = config('clientkey');
            $key = md5($param['roleid'] . $param['time'] . $clientkey);
            if ($key != $param['sign']) {
                return $this->apiReturn(100, '', 'The Sign is check error');
            }

            $roleid = $param['roleid'];
            $accountM = new AccountDB();
            $user = $accountM->getRow(['AccountID' => $roleid], 'AccountID,AccountName');
            if (!$user) {
                return $this->failjson('the player is not exist');
            }

            //获取余额
            $post_data = [
                'playerId'   =>$roleid,
                'brandId'    =>$this->config['Merchant_ID'],
                'requestId'  =>md5(uniqid().rand(100000000,999999999))
            ];
            $hash = $this->createsign($post_data,$this->config['Secret_Key']);
            $header = [
                'content-type:application/json'
            ];
            $result = $this->curl($this->config['API_Host'].'/player/balance?hash='.$hash,json_encode($post_data),$header);
            $result = json_decode($result,true);
            $balance = $result['balance'];

            $transferid = $this->makeOrderId($roleid);
            //先扣后加
            $post_data = [
                'playerId'   =>$roleid,
                'currency'   =>$this->config['Currency'],
                'amount'     =>$balance/1,
                'extTransId' =>$transferid,
                'brandId'    =>$this->config['Merchant_ID'],
                'requestId'  =>md5(uniqid().rand(100000000,999999999))
            ];
            $hash = $this->createsign($post_data,$this->config['Secret_Key']);
            $header = [
                'content-type:application/json'
            ];
            $result = $this->curl($this->config['API_Host'].'/payment/player/withdrawal?hash='.$hash,json_encode($post_data),$header);
            $result = json_decode($result,true);
            save_log('pngame', '第三方玩家上分===' . json_encode($result));
            
            $res = $socket->downLotteryScore($roleid, $balance*1000, $transferid);
            save_log('pngame', '游服上分===' . $roleid . '===' . json_encode($res));
            if ($res['iResult'] == 0) {
                return $this->succjson('');
            } else {
                return $this->failjson('downscore error');
            }

        } catch (Exception $ex) {
            save_log('pngame', $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjson('Api Error');
        }

    }

    //获取玩家账号余额
    private function getBalance($roleid)
    {
        $roleid = intval($roleid);
        $socket = new QuerySocket();
        $m = $socket->DSQueryRoleBalance($roleid);
        $gamemoney = $m['iGameWealth'] ?? 0;
        return bcdiv($gamemoney, bl, 3)/1;
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
        save_log('pplay', '==='.request()->url().'===三方返回数据===' . $data);
        //关闭URL请求
        curl_close($curl);
        //显示获得的数据
        return $data;
    }


    //签名函数
    private function createsign($data,$Md5key)
    {
        ksort($data);
        $md5str = '';
        foreach ($data as $key => $val) {
            if ($val !== null) {
                if ($md5str) {
                    $md5str = $md5str.'&'.$key.'='.$val;
                } else {
                    $md5str = $key.'='.$val;
                }
                
            }
        }
        $str = $md5str.$Md5key;
        return md5($str);
    }


    //创建临时订单
    private function makeOrderId($uid)
    {
        return date('YmdHis') . sprintf('%.0f', floatval(explode(' ', microtime())[0]) * 1000) . $uid;
    }
}