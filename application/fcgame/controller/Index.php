<?php

namespace app\fcgame\controller;

use app\model\AccountDB;
use app\model\Role;
use app\model\ThirdGameReport;
use redis\Redis;
use socket\QuerySocket;
use think\Exception;

class Index extends Base
{

    private $params = [];

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
        $this->params = request()->param() ?: json_decode(file_get_contents('php://input'),1);
        save_log('fcgame', '==='.request()->url().'===接口请求数据===' . json_encode($this->params));
        
    }

    //游戏对外接口创建玩家
    public function createuser()
    {
        try {
            $param = jsonRequest(['roleid', 'gameid', 'language','session_id', 'ip','time', 'sign']);
            $clientkey = config('clientkey');
            $key = md5($param['roleid'] . $param['gameid'] . $param['language'] . $param['time'] . $clientkey);
            if(empty($param['roleid']) || empty($param['gameid']) ||empty($param['time']) ||empty($param['sign'])){
                return $this->failjson('Missing parameter');
            }
            if ($key != strtolower($param['sign'])) {
                return $this->failjson('sign is error');
            }
            $roleid = $param['roleid'];
            $test_uidarr = config('test_uidarr') ?: [];
            if ((strlen($roleid)==7) || in_array($roleid, $test_uidarr)) {
                 $this->config = config('fcgame_test');
                 config('trans_url_other',config('test_trans_url'));
                 
            }
            $language = $param['language'] ?: $this->config['language'];
            if (strtoupper($language) == 'BR') {
                $language = 'pt';
            }


            $gameid = $param['gameid'];

            $MemberAccount = config('platform_name').'_'.$roleid;
            $Params = [
                'MemberAccount'=>$MemberAccount,
                'GameID'=>(int)$gameid
            ];
            $Params_json = json_encode($Params);
            $params = [
                'AgentCode' =>$this->config['Merchant_ID'],
                'Currency'  =>$this->config['Currency'],
                'Params'    =>$this->AESencode($Params_json,$this->config['Secret_Key']),
                'Sign'      =>md5($Params_json)
            ]; 
            $header = [
                'content-type:application/x-www-form-urlencoded'
            ];
            $gameURL = $this->config['API_Host'].'/Login';
            if (config('is_fcgame_trans') == 1 || true) {
                $params['url'] = $gameURL;
                $res = $this->curl(config('trans_url_other').'/fcgame/index/createuser',$params);
            } else {
                $res = '';
            }
            $res = json_decode($res,true);
            if($res['Result'] == '0'){
                $gameURL = $res['Url'];
            } else {
                $gameURL = '';
            }
            return $this->succjson($gameURL);
            
        } catch (Exception $ex) {
            save_log('fcgame_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjson('api error');
        }
    }

    //获取余额
    public function getBalance(){
        try {
            $params  = $this->params;
            $token   = $params['MemberAccount'];

            $user_id = explode('_',$token);
            if (!isset($user_id[1])) {
                $respons = [
                    'Result ' => 999,
                    'ErrorText' => 'Player not exist.',
                ];
                return json($respons);
            }

            $user_id = $user_id[1];
            $balance = $this->getSocketBalance($user_id);
            $data = [
                'Result' =>0,
                'MainPoints'  =>$balance
            ];
            return json($data);
        } catch (Exception $ex) {
            save_log('hacksaw_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return json([
                'Result ' => 999,
                'ErrorText'  => 'api error',
            ]);
        }
    }

    public function CancelBetInfo(){
        try {
            $params  = $this->params;
            $token   = $params['MemberAccount'];

            $user_id = explode('_',$token);
            if (!isset($user_id[1])) {
                $respons = [
                    'Result ' => 999,
                    'ErrorText' => 'Player not exist.',
                ];
                return json($respons);
            }

            $user_id = $user_id[1];
            $balance = $this->getSocketBalance($user_id);
            $data = [
                'Result' =>0,
                'MainPoints'  =>$balance
            ];
            $this->clearData($user_id);
            return json($data);
        } catch (Exception $ex) {
            save_log('hacksaw_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return json([
                'Result ' => 999,
                'ErrorText'  => 'api error',
            ]);
        }
    }

    //下注扣钱
    public function BetInfo(){
        try {
            $params     = $this->params;
            $token      = $params['MemberAccount'];
            $game_id    = $params['GameID'];
            $round_id   = $params['RecordID'];
            $bet_id     = $params['BankID'];
            $bet_amount = $params['Bet'];
            $win_amount = $params['Win'];    

            $user_id = explode('_',$token);
            if (!isset($user_id[1])) {
                $respons = [
                    'Result ' => 999,
                    'ErrorText' => 'Player not exist.',
                ];
                return json($respons);
            }

            $user_id = $user_id[1];
            if (Redis::has('fcgame_is_exec_bet_'.$bet_id)) {
                $respons = [
                    'Result ' => 999,
                    'ErrorText' => 'Bet record duplicate.',
                ];
                return json($respons);
            }
            
            $balance = $this->getSocketBalance($user_id);
            if ($balance < $bet_amount) {
                $respons = [
                    'Result ' => 999,
                    'ErrorText' => 'Balance insufficient.',
                ];
                return json($respons);
            }

            $socket    = new QuerySocket();
            
            $bet_amount = bcmul($bet_amount,bl,0);
            $state = $socket->downScore($user_id, $bet_amount, $bet_id,44000);
            if ($state['iResult']!=0) {
                $respons = [
                    'Result ' => 999,
                    'ErrorText' => 'System error.',
                ];
                return json($respons);
            }
            //记录订单是否执行，防止重复
            Redis::set('fcgame_is_exec_bet_'.$bet_id,$bet_amount,3600);
           
            $win_amount  = bcmul($win_amount,bl,0);
            $state = $socket->UpScore2($user_id, $win_amount, $bet_id,44000,$bet_amount);
            $this->clearData($user_id,$game_id);
            if (config('need_third_rank') == 1) {
                Redis::lpush('third_game_rank_list',json_encode([
                    'PlatformId'=>44000,
                    'PlatformName'=>'FCGAME',
                    'GameId'=>$game_id,
                ]));
            }
            $balance = $this->getSocketBalance($user_id);
            $data = [
                'Result' =>0,
                'MainPoints'  =>$balance
            ];
            return json($data);
        } catch (Exception $ex) {
            save_log('fcgame_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return json([
                'Result ' => 999,
                'ErrorText'  => 'api error',
            ]);
        }
    }
    
    //获取余额
    private function getSocketBalance($roleid)
    {
        $roleid = intval($roleid);
        $socket = new QuerySocket();
        $m = $socket->DSQueryRoleBalance($roleid);
        $gamemoney = $m['iGameWealth'] ?? 0;
        $balance = bcdiv($gamemoney, bl, 3);
        return floor($balance*100)/100;
    }

    //清除打码
    private function clearData($user_id,$round_id='')
    {
        $socket = new QuerySocket();
        $state = $socket->ClearLablel($user_id,44000);
        // $clear_data = Redis::get('fcgame_game_id_'.$user_id) ?: [];
        // $clear_data[$round_id] = 1;
        // unset($clear_data[$round_id]);
        // if (empty($clear_data)) {
        //     $socket    = new QuerySocket();
        //     $state = $socket->ClearLablel($user_id,44000);
        //     Redis::rm('fcgame_game_id_'.$user_id);
        // } else {
        //     Redis::set('fcgame_game_id_'.$user_id,$clear_data);
        // }
        return true;
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
            if(is_array($val)){
                $val = json_encode($val);
            }
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

    public function AESencode($_values,$key) 
     { 
        $data = openssl_encrypt($_values, 'AES-128-ECB', $key, OPENSSL_RAW_DATA); 
        $data = base64_encode($data); 
        return $data; 
     } 

    //AES 解密 ECB 模式 
     public function AESdecode($_values,$key) 
     { 
         $data = null; 
         $data = openssl_decrypt(base64_decode($_values), 'AES-128-ECB', $key, OPENSSL_RAW_DATA); 
         return $data; 
     } 

    //创建临时订单
    private function makeOrderId($uid)
    {
        return date('YmdHis') . sprintf('%.0f', floatval(explode(' ', microtime())[0]) * 1000) . $uid;
    }
    //加密
    private function encry($str,$key='fcgame'){
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
    private function decry($str,$key='fcgame'){
        return think_decrypt($str,$key);
        if (!$key) {
            return $str;
        }
        $data = base64_decode($str);
        $data = openssl_decrypt($data, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        return $data;
    }
}