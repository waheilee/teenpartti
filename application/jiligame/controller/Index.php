<?php

namespace app\jiligame\controller;

use app\model\AccountDB;
use app\model\Role;
use app\model\ThirdGameReport;
use redis\Redis;
use socket\QuerySocket;
use think\Exception;

class Index extends Base
{

    public function __construct()
    {
        parent::__construct();

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
        try {
            $param = jsonRequest(['roleid', 'gameid', 'language','session_id', 'ip','time', 'sign']);
            save_log('jiligame', '==='.request()->url().'===接口请求数据===' . json_encode($param));
            $clientkey = config('clientkey');
            $key = md5($param['roleid'] . $param['gameid'] . $param['language'] . $param['time'] . $clientkey);
            if(empty($param['roleid']) || empty($param['gameid']) ||empty($param['time']) ||empty($param['sign'])){
                return $this->failjson('Missing parameter');
            }
            if ($key != strtolower($param['sign'])) {
                return $this->failjson('sign is error');
            }
            $roleid = $param['roleid'];
            $language = $param['language'];
            if($language=='' || strtolower($language)=='en'){
                $language ='en-US';
            }
            if (strtoupper($language) == 'BR') {
                $language = 'pt';
            }

            $test_uidarr = config('test_uidarr') ?: [];
            if ((strlen($roleid)==7) || in_array($roleid, $test_uidarr)) {
                $this->config = config('jiligame_test');
                config('trans_url_other',config('test_trans_url'));
            }
            $language = $param['language'] ?: $this->config['language'];
            if (strtoupper($language) == 'BR') {
                $language = 'pt';
            }

            $gameid = $param['gameid'];

            $token = $this->encry(config('platform_name').'_'.$roleid);

            $header = [
                'content-type:application/x-www-form-urlencoded'
            ];
            date_default_timezone_set('America/Sao_Paulo');
            $KeyG = MD5(date('ymj').$this->config['Merchant_ID'].$this->config['Secret_Key']);

            $key = rand(100000,999999).md5('Token='.$token.'&GameId='.$gameid.'&Lang='.$language.'&AgentId='.$this->config['Merchant_ID'].$KeyG).rand(100000,999999);
            $url = $this->config['API_Host'].'singleWallet/LoginWithoutRedirect?Token='.$token.'&GameId='.$gameid.'&Lang='.$language.'&AgentId='.$this->config['Merchant_ID'].'&Key='.$key;

            if (config('is_jiligame_trans') == 1) {
                $post_param['url'] = $url;
                $result = $this->curl(config('trans_url_other').'/jiligame/index/createuser',$post_param);
            } else {
                $result = $this->curl($url);
            }
            save_log('jiligame', '===第三方玩家创建===' . json_encode($result));
            $result = json_decode($result,1);
            if ($result['ErrorCode'] == 0) {
                return $this->succjson($result['Data']);
            } else {
                return $this->failjson('create player faild');
            }

        } catch (Exception $ex) {
            save_log('jiligame_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjson('api error');
        }
    }

    public function auth(){
        try {
            $params = request()->param() ?: json_decode(file_get_contents('php://input'),1);
            save_log('jiligame', '==='.request()->url().'===接口请求数据===' . json_encode($params));
            $token = $params['token']??'';
            if(empty($token)){
                $respons = [
                    "errorCode"   =>5,
                    "message"   =>'Other error',
                ];
                save_log('jiligame', '==='.request()->url().'===响应成功数据===' . json_encode($params));
                return json($respons);
            }

            $userId = explode('_',$this->decry($token))[1];
            if (empty($userId)) {
                $respons = [
                    "errorCode"   =>5,
                    "message"   =>'Other error',
                ];
                save_log('jiligame', '==='.request()->url().'===响应成功数据===' . json_encode($params));
                return json($respons);
            }

            $balance = $this->getBalance($userId);

            $respons = [
                "errorCode"   =>0,
                "message"   =>'success',
                "username"    =>config('platform_name').'_'.$userId,
                'currency'    =>$this->config['Currency'],
                "balance"     =>round($balance,2),
            ];
            save_log('jiligame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('jiligame_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            $respons = [
                "errorCode"   =>5,
                "message"   =>'Other error ',
            ];
            return json($respons);
        }
    }


    public function bet(){
        try {
            $params                  = request()->param() ?: json_decode(file_get_contents('php://input'),1);
            save_log('jiligame', '==='.request()->url().'===接口请求数据===' . json_encode($params));
            $token          = $params['token'];
            $transaction_id = $params['round'];
            $bet_amount     = $params['betAmount'];
            $win_amount     = $params['winloseAmount'];
            $user_id = explode('_',$this->decry($token))[1];
            $game_id    = $params['game'];
            if (Redis::get('jiligame_is_exec_bet_'.$transaction_id)) {
                $respons = [
                    "errorCode"   =>1,
                    "message"   =>'Already accepted',
                ];
                save_log('jiligame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }


            $balance = $this->getBalance($user_id);

            if ($balance < $bet_amount) {
                $respons = [
                    "errorCode"   =>2,
                    "message"   =>'Not enough balance',
                ];
                save_log('jiligame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }
            $socket = new QuerySocket();
            if ($bet_amount > 0) {
                $gamemoney = bcmul($bet_amount,bl,0);
                $state = $socket->downScore($user_id, $gamemoney, $transaction_id,39000);
                if ($state['iResult']!=0) {
                    $respons = [
                        "errorCode"   =>5,
                        "message"   =>'Other error ',
                    ];
                    save_log('jiligame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                    return json($respons);
                }
            }
            if ($win_amount >= 0) {
                $gamemoney = bcmul($win_amount,bl,0);
                $gamemoney2 = bcmul($bet_amount,bl,0);
                $state = $socket->UpScore2($user_id, $gamemoney, $transaction_id,39000,$gamemoney2);
            }

            $clear_data = Redis::get('jiligame_game_id_'.$user_id) ?: [];
            $clear_data[$game_id] = 1;
            unset($clear_data[$game_id]);
            if (empty($clear_data)) {
                $state = $socket->ClearLablel($user_id,39000);
                save_log('jilidama', $user_id.'游戏'.$game_id.'打码清零==='.request()->url().'===响应成功数据===' .json_encode($state));
                Redis::rm('jiligame_game_id_'.$user_id);
            } else {
                Redis::set('jiligame_game_id_'.$user_id,$clear_data);
            }

            if (config('need_third_rank') == 1) {
                Redis::lpush('third_game_rank_list',json_encode([
                    'PlatformId'=>39000,
                    'PlatformName'=>'JILI',
                    'GameId'=>$game_id,
                ]));
            }
            Redis::set('jiligame_is_exec_bet_'.$transaction_id,1,3600);
            $balance = $this->getBalance($user_id);
            $respons = [
                "errorCode"   =>0,
                "message"   =>'success',
                "username"    =>config('platform_name').'_'.$user_id,
                'currency'    =>$this->config['Currency'],
                "balance"     =>round($balance,2),
                'txId'        =>$transaction_id
            ];
            save_log('jiligame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('jiligame_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            $respons = [
                "errorCode"   =>5,
                "message"   =>'Other error ',
            ];
            return json($respons);
        }
    }

    public function cancelBet(){
        try {
            $params                  = request()->param() ?: json_decode(file_get_contents('php://input'),1);
            $token          = $params['token'];
            $transaction_id = $params['round'];
            $bet_amount     = $params['betAmount'];
            $win_amount     = $params['winloseAmount'];
            $user_id = explode('_',$this->decry($token))[1];


            if (Redis::get('jiligame_is_exec_cancelBet_'.$transaction_id)) {
                $respons = [
                    "errorCode" =>1,
                    "message"   =>'Already canceled',
                ];
                save_log('jiligame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }
            if ($win_amount > 0) {
                $respons = [
                    "errorCode" =>6,
                    "message"   =>'Already accepted and cannot be canceled',
                ];
                save_log('jiligame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            } else {
                if ($bet_amount > 0) {
                    $gamemoney = bcmul($bet_amount,bl,0);
                    $socket = new QuerySocket();
                    $state = $socket->UpScore2($user_id, $gamemoney, $transaction_id,39000,0);
                }
                save_log('jiligame', '===取消下注加钱' . $user_id . '===状态：' . json_encode($state));
            }

            Redis::set('jiligame_is_exec_cancelBet_'.$transaction_id,1,3600);
            $balance = $this->getBalance($user_id);
            $respons = [
                "errorCode"   =>0,
                "message"     =>'success',
                "username"    =>config('platform_name').'_'.$user_id,
                'currency'    =>$this->config['Currency'],
                "balance"     =>round($balance,2),
                'txId'        =>$transaction_id
            ];
            save_log('jiligame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('jiligame_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            $respons = [
                "errorCode"   =>5,
                "message"   =>'Other error ',
            ];
            return json($respons);
        }
    }

    private function getBalance($roleid)
    {
        $roleid = intval($roleid);
        $socket = new QuerySocket();
        $m = $socket->DSQueryRoleBalance($roleid);
        $gamemoney = $m['iGameWealth'] ?? 0;
        $balance = bcdiv($gamemoney, bl, 3);
        return floor($balance*100)/100;
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
        save_log('jiligame', '==='.request()->url().'===三方返回数据===' . $data);
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
    //加密
    private function encry($str,$key='jiligame'){
        $str = trim($str);
        return think_encrypt($str,$key);
        if (!$key) {
            return $str;
        }
        $data = openssl_encrypt($str, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        $data = base64_encode($data);
        return $data;
    }

    //解密
    private function decry($str,$key='jiligame'){
        return think_decrypt($str,$key);
        if (!$key) {
            return $str;
        }
        $data = base64_decode($str);
        $data = openssl_decrypt($data, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        return $data;
    }
}