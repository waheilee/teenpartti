<?php

namespace app\spribe\controller;

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
            $clientkey = config('clientkey');
            $key = md5($param['roleid'] . $param['gameid'] . $param['language'] . $param['time'] . $clientkey);
            if(empty($param['roleid']) || empty($param['gameid']) ||empty($param['time']) ||empty($param['sign'])){
                return $this->failjson('Missing parameter');
            }
            if ($key != strtolower($param['sign'])) {
                return $this->failjson('sign is error');
            }
            $roleid = $param['roleid'];
            $language = $param['language'] ?: $this->config['language'];
            if (strtoupper($language) == 'BR') {
                $language = 'pt';
            }
            $accountM = new AccountDB();
            $user = $accountM->getRow(['AccountID' => $roleid], 'AccountID,AccountName');
            if (!$user) {
                return $this->failjson('the player is not exist');
            }

            $gameid = $param['gameid'];

            $token = $this->encry(config('platform_name').'_'.$user['AccountID']);
            
            $gameURL = $this->config['GAME_URL'].'/launcher?gameCode='.$gameid.'&token='.$this->encry(config('platform_name').'_'.$roleid).'&platform=web&language='.$language.'&playerId='.config('platform_name').'_'.$roleid.'&brandId='.$this->config['Merchant_ID'].'&mode=1';

            return $this->succjson($gameURL);
            
        } catch (Exception $ex) {
            save_log('spribe_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjson('api error');
        }
    }

    public function auth(){
        try {
            $params = request()->param() ?: json_decode(file_get_contents('php://input'),1);
            $hash      = $params['hash']??'';
            $requestId = $params['requestId']??'';
            $token     = $params['token']??'';
            unset($params['hash']);
            $check_hash = $this->createsign($params,$this->config['Secret_Key']);
            if ($check_hash != $hash) {
                $respons = [
                    "requestId"=>$requestId,
                    "error"   =>"P_02",
                    "message"   =>'Invalid hash',
                ];
                // save_log('spribe', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }

            $user_id = explode('_',$this->decry($token))[1];
            if (empty($user_id)) {
                $respons = [
                    "requestId"=>$requestId,
                    "error"   =>"P_04",
                    "message"   =>'Player not found',
                ];
                // save_log('spribe', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }

            $balance = $this->getSocketBalance($user_id);

            $respons = [
                "requestId"       =>$requestId,
                "playerSessionId" =>$token,
                "playerId"        =>config('platform_name').'_'.$user_id,
                "playerName"      =>config('platform_name').'_'.$user_id,
                'currency'        =>$this->config['Currency'],
                "balance"         =>round($balance,2).'',
                "country"         =>$this->config['country'],
                "error"           =>"0",
                "message"         =>"success"
            ];
            // save_log('spribe', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('spribe_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            $respons = [
                "requestId"=>$requestId,
                "error"   =>"P_00",
                "message"   =>'Server Error, internal server error',
            ];
            return json($respons);
        }
    }

    public function balance(){
        try {
            $params = request()->param() ?: json_decode(file_get_contents('php://input'),1);
            
            $hash      = $params['hash']??'';
            $requestId = $params['requestId']??'';
            $playerId  = $params['playerId']??'';
            $token     = $params['playerSessionId']??'';
            unset($params['hash']);
            $check_hash = $this->createsign($params,$this->config['Secret_Key']);
            if ($check_hash != $hash) {
                $respons = [
                    "requestId"=>$requestId,
                    "error"   =>"P_02",
                    "message"   =>'Invalid hash',
                ];
                // save_log('spribe', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }

            $user_id = explode('_',$this->decry($token))[1];
            if (empty($user_id)) {
                $respons = [
                    "requestId"=>$requestId,
                    "error"   =>"P_04",
                    "message"   =>'Player not found',
                ];
                // save_log('spribe', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }

            $balance = $this->getSocketBalance($user_id);

            $respons = [
                "requestId"    =>$requestId,
                'currency'     =>$this->config['Currency'],
                "balance"      =>round($balance,2).'',
                "bonusBalance" =>"0",
                "error"        =>"0",
                "message"      =>"success"
            ];
            // save_log('spribe', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('spribe_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
           $respons = [
                "requestId"=>$requestId,
                "error"   =>"P_00",
                "message"   =>'Server Error, internal server error',
            ];
            return json($respons);
        }
    }


    public function transaction(){
        try {
            $params  = request()->param() ?: json_decode(file_get_contents('php://input'),1);
            save_log('spribe', '==='.request()->url().'===接口请求数据===' . json_encode($params));
            $hash      = $params['hash']??'';
            $requestId = $params['requestId']??'';
            $playerId  = $params['playerId']??'';
            $token     = $params['playerSessionId']??'';
            $game_id   = $params['gameCode'];
            unset($params['hash']);
            $check_hash = $this->createsign($params,$this->config['Secret_Key']);
            if ($check_hash != $hash) {
                $respons = [
                    "requestId"=>$requestId,
                    "error"   =>"P_02",
                    "message"   =>'Invalid hash',
                ];
                save_log('spribe', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }

            $user_id = explode('_',$this->decry($token))[1];
            if (empty($user_id)) {
                $respons = [
                    "requestId"=>$requestId,
                    "error"   =>"P_04",
                    "message"   =>'Player not found',
                ];
                save_log('spribe', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }
            $transaction_id = $params['trans'][0]['transId'];
            $bet_amount = 0;
            $win_amount = 0;
            $is_bet = 0;
            $is_win = 0;
            $is_end_round = 'FALSE';
            $roundId = '';
            foreach ($params['trans'] as $key => &$val) {
                if ($val['transType'] == 'bet') {
                    $bet_amount += $val['amount'];
                    $is_bet = 1;
                    $total_bet_amount = Redis::get('spribe_amount_bet_'.$val['roundId']) ?: 0;
                    $total_bet_amount += $val['amount'];
                    Redis::set('spribe_amount_bet_'.$val['roundId'],$total_bet_amount,3600);
                }
                if ($val['transType'] == 'win' || $val['transType'] == 'cancel' || $val['transType'] == 'amend') {
                    $win_amount += $val['amount'];
                    $is_win = 1;
                    $roundId = $val['roundId'];
                }
                if($val['endRound'] == 1){
                    $is_end_round = 'TRUE';
                }
            }

            if (Redis::get('spribe_is_exec_bet_'.$transaction_id)) {
                $respons = [
                    "requestId"=>$requestId,
                    "error"   =>"T_04",
                    "message"   =>'Bet limit was exceeded',
                ];
                save_log('spribe', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }
            $balance = $this->getSocketBalance($user_id);
 
            if ($balance < $bet_amount) {
                $respons = [
                    "requestId"=>$requestId,
                    "error"   =>"T_01",
                    "message"   =>'Player Insufficient Funds',
                ];
                save_log('spribe', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }
            $socket = new QuerySocket();
            if ($bet_amount > 0) {
                $gamemoney = bcmul($bet_amount,bl,0);
                $state = $socket->downScore($user_id, $gamemoney, $transaction_id,39400);
                if ($state['iResult']!=0) {
                    $respons = [
                        "requestId"=>$requestId,
                        "error"   =>"P_00",
                        "message"   =>'Server Error, internal server error',
                    ];
                    save_log('spribe', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                    return json($respons);
                }
            }
            if ($win_amount >= 0 && $is_win == 1) {
                $gamemoney = bcmul($win_amount,bl,0);
                $bet_amount = Redis::get('spribe_amount_bet_'.$roundId) ?: 0;
                $gamemoney2 = bcmul($bet_amount,bl,0);
                $state = $socket->UpScore2($user_id, $gamemoney, $transaction_id,39400,$gamemoney2);
            }

            $clear_data = Redis::get('user_id_game_id_'.$user_id) ?: [];
            $clear_data[$game_id] = 1;
            if ($is_end_round == "True") {
                unset($clear_data[$game_id]);
                if (empty($clear_data)) {
                    $state = $socket->ClearLablel($user_id,39400);
                    Redis::rm('spribe_game_id_'.$user_id);
                } else {
                    Redis::set('spribe_game_id_'.$user_id,$clear_data);
                }
            } else {
                Redis::set('spribe_game_id_'.$user_id,$clear_data);
            }

            if (config('need_third_rank') == 1) {
                Redis::lpush('third_game_rank_list',json_encode([
                    'PlatformId'=>39400,
                    'PlatformName'=>'SPRIBE',
                    'GameId'=>$game_id,
                ]));
            }
            

            Redis::set('spribe_is_exec_bet_'.$transaction_id,1,3600);
            $balance = $this->getSocketBalance($user_id);
            $respons = [
                "requestId"    =>$requestId,
                'currency'     =>$this->config['Currency'],
                "balance"      =>round($balance,2).'',
                "bonusBalance" =>"0",
                "error"        =>"0",
                "message"      =>"success"
            ];
            save_log('spribe', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('spribe_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            $respons = [
                "requestId"=>$requestId,
                "error"   =>"P_00",
                "message"   =>'Server Error, internal server error',
            ];
            return json($respons);
        }
    }



    public function payUp(){
        try {
            $params  = request()->param() ?: json_decode(file_get_contents('php://input'),1);
            $hash      = $params['hash']??'';
            $requestId = $params['requestId']??'';
            $playerId  = $params['playerId']??'';
            $token     = $params['playerSessionId']??'';
            unset($params['hash']);
            $check_hash = $this->createsign($params,$this->config['Secret_Key']);
            if ($check_hash != $hash) {
                $respons = [
                    "requestId" =>$requestId,
                    "error"     =>"P_02",
                    "message"   =>'Invalid hash',
                ];
                // save_log('spribe', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }

            $user_id = explode('_',$this->decry($token))[1];
            if (empty($user_id)) {
                $respons = [
                    "requestId" =>$requestId,
                    "error"     =>"P_04",
                    "message"   =>'Player not found',
                ];
                // save_log('spribe', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }
            $transaction_id = $params['transId'];
            $bet_amount     = $params['bet_amount']??0;
            $win_amount = $params['amount'];

            if (Redis::get('spribe_is_exec_payUp_'.$transaction_id)) {
                $respons = [
                    "requestId" =>$requestId,
                    "error"     =>"T_04",
                    "message"   =>'Bet limit was exceeded',
                ];
                // save_log('spribe', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }
 
            if ($win_amount >= 0) {
                $gamemoney = bcmul($win_amount,bl,0);
                $gamemoney2 = bcmul($bet_amount,bl,0);
                $state = $socket->UpScore2($user_id, $gamemoney, $transaction_id,39400,$gamemoney2);
            }
            Redis::set('spribe_is_exec_payUp_'.$transaction_id,1,3600);
            $balance = $this->getSocketBalance($user_id);
            $respons = [
                "requestId"    =>$requestId,
                'currency'     =>$this->config['Currency'],
                "balance"      =>round($balance,2).'',
                "bonusBalance" =>"0",
                "error"        =>"0",
                "message"      =>"success"
            ];
            // save_log('spribe', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('spribe_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            $respons = [
                "requestId"=>$requestId,
                "error"   =>"P_00",
                "message"   =>'Server Error, internal server error',
            ];
            return json($respons);
        }
    }

    private function getSocketBalance($roleid)
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


    //创建临时订单
    private function makeOrderId($uid)
    {
        return date('YmdHis') . sprintf('%.0f', floatval(explode(' ', microtime())[0]) * 1000) . $uid;
    }
    //加密
    private function encry($str,$key='spribe'){
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
    private function decry($str,$key='spribe'){
        return think_decrypt($str,$key);
        if (!$key) {
            return $str;
        }
        $data = base64_decode($str);
        $data = openssl_decrypt($data, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        return $data;
    }
}