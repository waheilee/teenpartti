<?php

namespace app\evolution\controller;

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
        save_log('evolution', '==='.$this->request->url().'===接口请求数据===' . json_encode($this->request->param()));

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
                $this->country = config('evolution_test.country');
                $this->Currency = config('evolution_test.Currency');
                $this->language = config('evolution_test.language');
                $this->url      = config('evolution_test.API_Host').'/ua/v1/'.config('evolution_test.Casino_Key').'/'.config('evolution_test.API_Token'); 
                config('trans_url_other',config('test_trans_url'));
            }

            $has = (new \app\model\MasterDB)->getTableObject('T_GameConfig')->where('CfgType',3000)->find();
            if (empty($has)) {
                $min_amount = (new \app\model\MasterDB)->getTableObject('T_GameConfig')->where('CfgType',300)->value('CfgValue') ?: 0;
            } else {
               $min_amount = $has['CfgValue'];
            }
            $balance = $this->getBalance($roleid);
            if ($balance < $min_amount) {
                return $this->failjson($min_amount,101);
            }

            $session_id = $param['session_id'] ?: session_id();
            $session_ip = $param['ip'] ?: $this->request->ip();

            Redis::set('evolution_'.$roleid.'session_id',$session_id);
            $gameid = $param['gameid'] ?: 1;
            switch ($gameid) {
                case '1':
                    $category  = "roulette";
                    $table_id  = "";
                    break;
                case '2':
                     $category = "baccarat";
                     $table_id  = "";
                    break;
                case '3':
                    $category  = "blackjack";
                    $table_id  = "";
                    break;
                case '4':
                     $category = "funy_live";
                     $table_id  = "";
                    break;
                case '5':
                    $category  = "game_shows";
                    $table_id  = "";
                    break;
                case '6':
                     $category = "top_games";
                     $table_id  = "";
                    break;
                case '7':
                    $category  = "dragon_tiger";
                    $table_id  = "";
                    break;
                case '8':
                     $category = "fan_tan";
                     $table_id  = "";
                    break;
                case '9':
                     $category = "poker";
                     $table_id  = "";
                    break;
                case '10':
                     $category = "";
                     $table_id  = "";
                    break;
                default:
                    $category  = "americanroulette";
                    $table_id  = "AmericanTable001";
                    break;
            }
            if(config('is_evolution_trans') == 1){
                $roleid = config('platform_name').'_'.$roleid;
            }
            $post_param = [
                "uuid"=>rand(10000000,99999999).sprintf('%.0f', floatval(explode(' ', microtime())[0]) * 1000),
                "player"=>[
                    "id"=>"$roleid",
                    "update"=>false,
                    "firstName"=>$roleid,
                    "lastName"=>$roleid,
                    "nickname"=>$roleid,
                    "country"=>$this->country,
                    "language"=>$this->language,
                    "currency"=>$this->Currency,
                    "session"=>[
                        "id"=>$session_id,
                        "ip"=>$session_ip
                    ]
                ],
                "config"=>[
                    "brand"=>[
                        'id'=>"1",
                        'skin'=>"1"
                    ],
                    "game"=>[
                        "category"=>$category,
                        "interface"=>"view1",
                        // "table"=>[
                        //     "id"=>$table_id
                        // ]
                    ],
                    "channel"=>[
                        "wrapped"=>false,
                        "mobile"=>true
                    ],
                ]
            ];
            $header = [
                'content-type:application/json'
            ];

            if(config('is_evolution_trans') == 1){
                $post_param['url'] = $this->url;    
                $result = $this->curl(config('trans_url_other').'/evolution/index/createuser',$post_param);
            } else {
                $post_param = json_encode($post_param);
                $result = $this->curl($this->url,$post_param,$header);
            }

            save_log('evolution', '===第三方玩家创建===' . json_encode($result));
            $result = json_decode($result,1);
            if (isset($result['entry']) || isset($result['entryEmbedded'])) {
                return $this->succjson($result['entry']);
            } else {
                return $this->failjson('create player faild');
            }
            
        } catch (Exception $ex) {
            save_log('evolution_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjson('api error');
        }
    }

    public function check(){
        $params = $this->request->param();
        $authToken = $params['authToken'];
        if($authToken != $this->API_Token){
            return $this->failjson('authToken error'); 
        }
        $user_id = $params['userId'];
        $uuid = $params['uuid'];
        $sid = $params['sid'];
        if (Redis::set('evolution_'.$user_id.'session_id') != $sid) {
            return $this->failjson('sid Mismatch');
        }
        $respons = [
            "status"=>'OK',
            "sid"=>$sid,
            "uuid"=>$uuid
        ];
        save_log('evolution', '==='.$this->request->url().'===响应成功数据===' . json_encode($respons));
        return json($respons);
    }
    
    public function sid(){
        $params = $this->request->param();
        $authToken = $params['authToken'];
        if($authToken != $this->API_Token){
            return $this->failjson('authToken error'); 
        }
        $user_id = $params['userId'];
        $uuid = $params['uuid'];
        $sid = $params['sid'];
        if (Redis::set('evolution_'.$user_id.'session_id') != $sid) {
            return $this->failjson('sid Mismatch');
        }
        $respons = [
            "status"=>'OK',
            "sid"=>$sid,
            "uuid"=>$uuid
        ];
        save_log('evolution', '==='.$this->request->url().'===响应成功数据===' . json_encode($respons));
        return json($respons);
    }

    //查询余额
    public function balance(){
        try {
            $params = $this->request->param();
            $authToken = $params['authToken'];
            if($authToken != $this->API_Token){
                return $this->failjson('authToken error'); 
            }

            $user_id = $params['userId'];
            $uuid = $params['uuid'];
            $sid = $params['sid'];
            if (Redis::set('evolution_'.$user_id.'session_id') != $sid) {
                return $this->failjson('sid Mismatch');
            }
            
            $balance = $this->getBalance($user_id);
            $respons = [
                "status"=>'OK',
                "balance"=>$balance,
                "bonus"=>0,
                "uuid"=>$uuid
            ];
            save_log('evolution', '==='.$this->request->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('evolution_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjson('api error');
        }
    }


    //下注扣钱
    public function debit(){
        try {
            $params = $this->request->param();
            $authToken = $params['authToken'];
            if($authToken != $this->API_Token){
                return $this->failjson('authToken error'); 
            }
            $user_id = $params['userId'];
            $uuid = $params['uuid'];
            $sid = $params['sid'];
            if (Redis::set('evolution_'.$user_id.'session_id') != $sid) {
                return $this->failjson('sid Mismatch');
            }
            $transaction = $params['transaction'];
            if (empty($transaction) || $transaction['amount'] <= 0) {
                return $this->failjson('Request data error');
            }

            $balance = $this->getBalance($user_id);
            if ($balance < $transaction['amount']) {
                return $this->failjson('Insufficient Balance');
            }
            if (Redis::get('evolution_is_exec_debit_'.$user_id.$transaction['id'])) {
                $respons = [
                    "status"=>'OK',
                    "balance"=>$balance,
                    "bonus"=>0,
                    "uuid"=>$uuid
                ];
                save_log('evolution', '==='.$this->request->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);exit();
            }

            $socket    = new QuerySocket();
            $gamemoney = bcmul($transaction['amount'],bl,0);
            $user_id   = intval($user_id);
            
            // save_log('evolution', '===下注扣钱' . $user_id . $gamemoney .$transaction['id']);
            $state = $socket->downScore($user_id, $gamemoney, $transaction['id'],37000);
            Redis::set('evolution_bet_amount_debit_'.$user_id.$transaction['refId'],$gamemoney,3600);
            Redis::set('evolution_is_exec_debit_'.$user_id.$transaction['id'],1,3600);
            // save_log('evolution', '===下注扣钱' . $user_id . '===状态：' . json_encode($state));
            $left_balance = $this->getBalance($user_id);
            $respons = [
                "status"=>'OK',
                "balance"=>$left_balance,
                "bonus"=>0,
                "uuid"=>$uuid
            ];
            save_log('evolution', '==='.$this->request->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('evolution_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjson('api error');
        }
    }


    //结算加钱
    public function credit(){
        try {
            $params = $this->request->param();
            $authToken = $params['authToken'];
            if($authToken != $this->API_Token){
                return $this->failjson('authToken error'); 
            }
            $user_id = $params['userId'];
            $uuid = $params['uuid'];
            $sid = $params['sid'];
            $gameid = $params['game']['type'] ?? '0';
            if (Redis::set('evolution_'.$user_id.'session_id') != $sid) {
                return $this->failjson('sid Mismatch');
            }
            $transaction = $params['transaction'];
            if (empty($transaction)) {
                return $this->failjson('Request data error');
            }
            if (Redis::get('evolution_is_exec_credit_'.$user_id.$transaction['id'])) {
                $balance = $this->getBalance($user_id);
                $respons = [
                    "status"=>'OK',
                    "balance"=>$balance,
                    "bonus"=>0,
                    "uuid"=>$uuid
                ];
                save_log('evolution', '==='.$this->request->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);exit();
            }
            
            if($transaction['amount'] >= 0){
                $socket = new QuerySocket();
                $gamemoney = bcmul($transaction['amount'],bl,0);
                $user_id = intval($user_id);
                if (config('is_portrait') == 1) {
                    $bet_amount = Redis::get('evolution_bet_amount_debit_'.$user_id.$transaction['refId']) ?: 0;
                    $state = $socket->UpScore2($user_id, $gamemoney, $transaction['id'],37000,$bet_amount);
                    Redis::rm('evolution_bet_amount_debit_'.$user_id.$transaction['refId']);
                } else {
                    if ($transaction['amount'] > 0) {
                        $state = $socket->UpScore($user_id, $gamemoney, $transaction['id']);
                    }
                }
                $state = $socket->ClearLablel($user_id,37000);
                Redis::set('evolution_is_exec_credit_'.$user_id.$transaction['id'],1,3600);
                save_log('evolution', '===结算加钱' . $user_id . '===状态：' . json_encode($state));
            }
            if (config('need_third_rank') == 1) {
                Redis::lpush('third_game_rank_list',json_encode([
                    'PlatformId'=>37000,
                    'PlatformName'=>'EVO',
                    'GameId'=>$gameid,
                ]));
            }
            $left_balance = $this->getBalance($user_id);
            $respons = [
                "status"=>'OK',
                "balance"=>$left_balance,
                "bonus"=>0,
                "uuid"=>$uuid
            ];
            save_log('evolution', '==='.$this->request->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('evolution_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjson('api error');
        }
    }


     //取消下注加钱
    public function cancel(){
        try {
            $params = $this->request->param();
            $authToken = $params['authToken'];
            if($authToken != $this->API_Token){
                return $this->failjson('authToken error'); 
            }
            $user_id = $params['userId'];
            $uuid = $params['uuid'];
            $sid = $params['sid'];
            if (Redis::set('evolution_'.$user_id.'session_id') != $sid) {
                return $this->failjson('sid Mismatch');
            }
            $transaction = $params['transaction'];
            if (empty($transaction) || $transaction['amount'] <= 0) {
                return $this->failjson('Request data error');
            }
            if (Redis::get('evolution_is_exec_cancel_'.$user_id.$transaction['id'])) {
                $balance = $this->getBalance($user_id);
                $respons = [
                    "status"=>'OK',
                    "balance"=>$balance,
                    "bonus"=>0,
                    "uuid"=>$uuid
                ];
                save_log('evolution', '==='.$this->request->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);exit();
            }
             if($transaction['amount'] > 0){
                $socket = new QuerySocket();
                $gamemoney = bcmul($transaction['amount'],bl,0);
                $user_id = intval($user_id);
                if (config('is_portrait') == 1) {
                    $state = $socket->UpScore2($user_id, $gamemoney, $transaction['id'],37000,0);
                } else {
                    $state = $socket->UpScore($user_id, $gamemoney, $transaction['id']);
                }
                Redis::set('evolution_is_exec_cancel_'.$user_id.$transaction['id'],1,3600);
                save_log('evolution', '===取消下注加钱' . $user_id . '===状态：' . json_encode($state));
            }
            
            $left_balance = $this->getBalance($user_id);
            $respons = [
                "status"=>'OK',
                "balance"=>$left_balance,
                "bonus"=>0,
                "uuid"=>$uuid
            ];
            
            save_log('evolution', '==='.$this->request->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('evolution_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjson('api error');
        }
    }

    //获取玩家账号余额
    private function getBalance($roleid)
    {
        $roleid = intval($roleid);
        $socket = new QuerySocket();
        $m = $socket->DSQueryRoleBalance($roleid);
        $gamemoney = $m['iGameWealth'] ?? 0;
        $balance   = bcdiv($gamemoney, bl, 3);
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
        save_log('evolution', '==='.$this->request->url().'===三方返回数据===' . $data);
        //关闭URL请求
        curl_close($curl);
        //显示获得的数据
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