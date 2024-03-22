<?php

namespace app\pggame\controller;

use app\model\AccountDB;
use app\model\MasterDB;
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
        header('Cache-Control: no-cache, no-store, must-revalidate');
    }

    public function create_guid(){
        $charid = mt_rand();
        $charid = uniqid($charid,true);
        $charid = md5($charid);
        $charid = strtolower($charid);
        $hyphen = chr(45);
        $uuid   = substr($charid, 0,8).$hyphen.substr($charid, 8,4).$hyphen.substr($charid, 12,4).$hyphen.substr($charid, 16,4).$hyphen.substr($charid, 20,12);
        return $uuid;
    }

    //游戏对外接口创建玩家
    public function createuser()
    {
        try {
            $params = request()->param() ?: json_decode(file_get_contents('php://input'),1);
            $roleid   = $params['roleid'];
            $gameid   = $params['gameid'];
            $language = $params['language'];
            $time     = $params['time'];
            $sign     = $params['sign'];


            if(empty($roleid) || empty($gameid) ||empty($time) ||empty($sign)){
                return $this->failjson('Missing parameter');
            }

            $clientkey = config('clientkey');
            $key = md5($roleid . $gameid . $language . $time . $clientkey);
            if ($key != strtolower($sign)) {
                return $this->failjson('sign is error');
            }
            $masterDB = new MasterDB();
            $isInWhiteList = $masterDB->getTableObject('T_PgWhiteConfigList')
                ->where('account_id',$roleid)
                ->find();
            //如果不再白名单中就走假PG
            if (empty($isInWhiteList)) {
                //是否走假pg
                $fake_pg_data = Redis::get('pgfake_data');
                if (!empty($fake_pg_data)) {
                    $fake_pg_data =json_decode($fake_pg_data,true);
                    if (isset($fake_pg_data[$gameid])) {
                        if ($fake_pg_data[$gameid]['status'] == 1 && strlen($roleid)==8) {
                            return (new \app\pgfake\controller\Index())->createuser($params);
                        }
                    }
                }
            }
            save_log('pggame', '==='.request()->url().'===接口请求数据===' . json_encode($params));

            if (strtoupper($language) == 'BR') {
                $language = 'pt';
            }
            $test_uidarr = config('test_uidarr') ?: [];
            if (strlen($roleid)==7 || in_array($roleid, $test_uidarr)) {
                $this->GAME_URL = config('pggame_test.GAME_URL');
                $this->API_Host = config('pggame_test.API_Host');
                $this->Operator_Token = config('pggame_test.Operator_Token');
                config('trans_url',config('test_trans_url'));
            }
            //中转站获取链接

            if(config('is_pgame_trans') == 2){
                $gameURL = $this->GAME_URL.'/'.$gameid.'/index.html?btt=1&ot='.$this->Operator_Token.'&l='.$language.'&ops='.$this->encry($roleid);
            } else {
                // $gameURL = $this->GAME_URL.'/'.$gameid.'/index.html?btt=1&ot='.$this->Operator_Token.'&l='.$language.'&ops='.$this->encry(config('platform_name').'_'.$roleid);
                $header = ['Content-Type: application/x-www-form-urlencoded'];
                $post_param = [
                    'operator_token'=>$this->Operator_Token,
                    'path'=>'/'.$gameid.'/index.html',
                    'url_type'=>'game-entry',
                    'client_ip'=>getClientIP(),
                    'ops'=>$this->encry(config('platform_name').'_'.$roleid),
                    'l'=>$language,
                    'url'=> $this->API_Host
                ];
                $gameURL = $this->curl(config('trans_url').'/pggame/index/index',$post_param);
                if(file_put_contents('./pggame/'.$this->encry($roleid).'_pg.html',$gameURL) != false){
                    $gameURL = config('pg_api_url').'/pggame/'.$this->encry($roleid).'_pg.html?pggame=true';
                    return  $this->succjson($gameURL);
                } else {
                    return  $this->succjson('');
                }
            }
            return  $this->succjson($gameURL);

        } catch (Exception $ex) {
            save_log('pggame_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjson('api error');
        }
    }

    /**
     * 提供给PG用的令牌验证
     */
    public function VerifySession(){
        try {
            $params = request()->param() ?: json_decode(file_get_contents('php://input'),1);
            // save_log('pggame', '==='.request()->url().'===接口请求数据===' . json_encode($params));
            $operator_token          = $params['operator_token'];
            $secret_key              = $params['secret_key'];
            $operator_player_session = urldecode($params['operator_player_session']);

            if ($this->Operator_Token != $operator_token || $this->Secret_Key != $secret_key) {
                return $this->apiReturn(null,"1034",'Invalid request');
            }

            $AccountID   = $this->decry($operator_player_session);
            $data = [
                'player_name' => config('platform_name').'_'.$AccountID,
                'nickname'    => config('platform_name').'_'.$AccountID,
                'currency'    => $this->Currency,
            ];
            return $this->apiReturn($data);
        }catch (Exception $ex){
            save_log('pggame_error', $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->apiReturn(null,"1200",'Internal server error');
        }
    }

    //查询余额
    public function Balance(){
        try {
            $params = request()->param() ?: json_decode(file_get_contents('php://input'),1);
            // save_log('pggame', '==='.request()->url().'===接口请求数据===' . json_encode($params));
            $operator_token          = $params['operator_token'];
            $secret_key              = $params['secret_key'];
            $player_name             = $params['player_name'];
            $operator_player_session = urldecode($params['operator_player_session']);
            if ($this->Operator_Token != $operator_token || $this->Secret_Key != $secret_key) {
                return $this->apiReturn(null,"1034",'Invalid request');
            }
            $user_id = $this->decry($operator_player_session);
            if($player_name != config('platform_name').'_'.$user_id){
                return $this->apiReturn(null,"1305" ,'Invalid player');
            }
            $balance = $this->getBalance($user_id);
            $respons = [
                "currency_code"   =>$this->Currency,
                "balance_amount" =>$balance,
                "updated_time"   =>intval(time().'000')
            ];
            return $this->apiReturn($respons);
        } catch (Exception $ex) {
            save_log('pggame_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->apiReturn(null,"1200",'Internal server error');
        }
    }


    //投付
    public function TransferInOut(){
        try {
            $params                  = request()->param() ?: json_decode(file_get_contents('php://input'),1);
            //save_log('pggame', '==='.request()->url().'===接口请求数据===' . json_encode($params));
            $operator_token          = $params['operator_token'];
            $secret_key              = $params['secret_key'];
            $operator_player_session = urldecode($params['operator_player_session']);
            $player_name             = $params['player_name'];
            $updated_time            = $params['updated_time'];
            $is_validate_bet         = $params['is_validate_bet'] ?? false;
            $is_adjustment           = $params['is_adjustment'] ?? false;
            $game_id                 = $params['game_id'];
            $parent_bet_id           = $params['parent_bet_id'];
            $bet_id                  = $params['bet_id'];
            $currency_code           = $params['currency_code'];
            $bet_amount              = $params['bet_amount'];
            $win_amount              = $params['win_amount'];
            $transfer_amount         = $params['transfer_amount'];
            $transaction_id          = $params['transaction_id'];
            $jackpot_win_amount      = $params['jackpot_win_amount'] ?? 0;

            $is_end_round            = $params['is_end_round'] ?? 0;
            if ($this->Operator_Token != $operator_token || $this->Secret_Key != $secret_key) {
                return $this->apiReturn(null,"1034",'Invalid request');
            }
            if ($currency_code != $this->Currency) {
                return $this->apiReturn(null,"1034" ,'Invalid request');
            }
            $AccountID = $this->decry($operator_player_session);
            // if ($is_validate_bet || $is_adjustment) {

            // } else {
            //     $AccountID = $this->decry($operator_player_session);
            //     $user = (new AccountDB())->getRow(['AccountID' => $AccountID,'AccountID'=>$AccountID], 'AccountID,AccountName');
            //     if (!$user) {
            //         return $this->apiReturn(null,"1034" ,'Invalid request');
            //     }
            // }

            // $user = (new AccountDB())->getRow(['AccountName' => $player_name], 'AccountID,AccountName');
            // $AccountID = $user['AccountID'];
            // if (!$user) {
            //     return $this->apiReturn(null,"1305" ,'Invalid player');
            // }
            $user_id = intval($AccountID);

            $balance = $this->getBalance($user_id);
            if (Redis::get('pggame_is_exec_TransferInOut_'.$user_id.$transaction_id)) {
                return $this->apiReturn(null,"1034",'Invalid request');
            }

            if ($bet_amount == 0 && $win_amount == 0 && $transfer_amount ==0 && $jackpot_win_amount == 0) {
                $socket = new QuerySocket();
                $respons = [
                    "currency_code"  =>$this->Currency,
                    "balance_amount" =>$balance,
                    "updated_time"   =>intval($updated_time),
                ];
                $clear_data = Redis::get('pggame_game_id_'.$user_id) ?: [];
                $clear_data[$game_id] = 1;
                if ($is_end_round == "True") {
                    unset($clear_data[$game_id]);
                    if (empty($clear_data)) {
                        $state = $socket->ClearLablel($user_id,36000);
                        Redis::rm('pggame_game_id_'.$user_id);
                    } else {
                        Redis::set('pggame_game_id_'.$user_id,$clear_data);
                    }
                } else {
                    Redis::set('pggame_game_id_'.$user_id,$clear_data);
                }
                return $this->apiReturn($respons);
            }
            if ($balance < $bet_amount) {
                $socket = new QuerySocket();
                $clear_data = Redis::get('pggame_game_id_'.$user_id) ?: [];
                $clear_data[$game_id] = 1;
                if ($is_end_round == "True") {
                    unset($clear_data[$game_id]);
                    if (empty($clear_data)) {
                        $state = $socket->ClearLablel($user_id,36000);
                        Redis::rm('pggame_game_id_'.$user_id);
                    } else {
                        Redis::set('pggame_game_id_'.$user_id,$clear_data);
                    }
                } else {
                    Redis::set('pggame_game_id_'.$user_id,$clear_data);
                }
                return $this->apiReturn(null,"3202",'Insufficient Balance');
            }
            $socket = new QuerySocket();
            if ($bet_amount > 0) {
                $gamemoney = bcmul($bet_amount,bl,0);
                $state = $socket->downScore($user_id, $gamemoney, $transaction_id,36000);
                if ($state['iResult']!=0) {
                    $clear_data = Redis::get('pggame_game_id_'.$user_id) ?: [];
                    $clear_data[$game_id] = 1;
                    if ($is_end_round == "True") {
                        unset($clear_data[$game_id]);
                        if (empty($clear_data)) {
                            $state = $socket->ClearLablel($user_id,36000);
                            Redis::rm('pggame_game_id_'.$user_id);
                        } else {
                            Redis::set('pggame_game_id_'.$user_id,$clear_data);
                        }
                    } else {
                        Redis::set('pggame_game_id_'.$user_id,$clear_data);
                    }
                    return $this->apiReturn(null,"1034" ,'Invalid request');
                }
            }
            if (($transfer_amount + $bet_amount) != $win_amount) {
                $win_amount = $transfer_amount + $bet_amount;
            }
            if ($win_amount >= 0) {
                $gamemoney = bcmul($win_amount + $jackpot_win_amount,bl,0);
                if ($jackpot_win_amount > 0) {
                    $gamemoney2 = 0;
                } else {
                    $gamemoney2 = bcmul($bet_amount,bl,0);
                }
                $state = $socket->UpScore2($user_id, $gamemoney, $bet_id,36000,$gamemoney2);
            }

            $clear_data = Redis::get('pggame_game_id_'.$user_id) ?: [];
            $clear_data[$game_id] = 1;
            if ($is_end_round == "True") {
                unset($clear_data[$game_id]);
                if (empty($clear_data)) {
                    $state = $socket->ClearLablel($user_id,36000);
                    Redis::rm('pggame_game_id_'.$user_id);
                } else {
                    Redis::set('pggame_game_id_'.$user_id,$clear_data);
                }
            } else {
                Redis::set('pggame_game_id_'.$user_id,$clear_data);
            }
            if (config('need_third_rank') == 1) {
                Redis::lpush('third_game_rank_list',json_encode([
                    'PlatformId'=>36000,
                    'PlatformName'=>'PG',
                    'GameId'=>$game_id,
                ]));
            }
            Redis::set('pggame_is_exec_TransferInOut_'.$user_id.$transaction_id,1,3600);
            $left_balance = $this->getBalance($user_id);
            $respons = [
                "currency_code"  =>$this->Currency,
                "balance_amount" =>$left_balance,
                "updated_time"   =>intval($updated_time),
            ];

            return $this->apiReturn($respons);
        } catch (Exception $ex) {
            save_log('pggame_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->apiReturn(null,"1200",'Internal server error');
        }
    }

    //投付
    public function Adjustment(){
        try {
            $params = request()->param() ?: json_decode(file_get_contents('php://input'),1);
            // save_log('pggame', '==='.request()->url().'===接口请求数据===' . json_encode($params));
            $operator_token  = $params['operator_token'];
            $secret_key      = $params['secret_key'];
            $player_name     = $params['player_name'];
            $currency_code   = $params['currency_code'];
            $transfer_amount = $params['transfer_amount'];
            $transaction_id  = $params['adjustment_transaction_id'];
            $adjustment_time  = $params['adjustment_time'] ?? time().'000';
            $operator_player_session = urldecode($params['operator_player_session']);
            if ($this->Operator_Token != $operator_token || $this->Secret_Key != $secret_key) {
                return $this->apiReturn(null,"1034",'Invalid request');
            }
            if ($currency_code != $this->Currency) {
                return $this->apiReturn(null,"1034" ,'Invalid request');
            }
            // $user = (new AccountDB())->getRow(['AccountName' => $player_name], 'AccountID,AccountName');
            // if (!$user) {
            //     return $this->apiReturn(null,"1305" ,'Invalid player');
            // }
            // $user_id = intval($user['AccountID']);
            $user_id = $this->decry($operator_player_session);

            $balance = $this->getBalance($user_id);
            if (Redis::get('pggame_is_exec_Adjustment_'.$user_id.$transaction_id)) {
                return $this->apiReturn(null,"1034",'Invalid request');
                // $respons = [
                //     "adjust_amount"  =>round($transfer_amount,2),
                //     "balance_before" =>round($balance - $transfer_amount,2),
                //     "balance_after"  =>$balance,
                //     "updated_time"   =>intval($adjustment_time),
                // ];
                // return $this->apiReturn($respons);
            }

            $socket = new QuerySocket();
            if ($transfer_amount < 0) {
                if ($balance < abs($transfer_amount)) {
                    return $this->apiReturn(null,"3202",'Insufficient player balance');
                }

                $gamemoney = bcmul(abs($transfer_amount),bl,0);
                $state = $socket->downScore($user_id, $gamemoney, $transaction_id,36000);
            } elseif ($transfer_amount > 0) {
                $gamemoney = bcmul($transfer_amount,bl,0);
                $state = $socket->UpScore2($user_id, $gamemoney, $transaction_id,36000,0);
            }

            Redis::set('pggame_is_exec_Adjustment_'.$user_id.$transaction_id,1,3600);
            $left_balance = $this->getBalance($user_id);
            $respons = [
                "adjust_amount"  =>round($transfer_amount,2),
                "balance_before" =>$balance,
                "balance_after"  =>$left_balance,
                "updated_time"   =>intval($adjustment_time),
            ];
            return $this->apiReturn($respons);
        } catch (Exception $ex) {
            save_log('pggame_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->apiReturn(null,"1200",'Internal server error');
        }
    }

    //投付
    public function UpdateBetDetail(){
        $respons = [
            "is_success"=>true,
        ];
        return $this->apiReturn($respons);
    }


    //获取玩家账号余额
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
            if (!empty(trim($val))) {
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
    private function encry($str,$key='pggme'){
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
    private function decry($str,$key='pggme'){
        return think_decrypt($str,$key);
        if (!$key) {
            return $str;
        }
        $data = base64_decode($str);
        $data = openssl_decrypt($data, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        return $data;
    }
}