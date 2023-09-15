<?php

namespace app\hacksaw\controller;

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
        save_log('hacksaw', '==='.request()->url().'===接口请求数据===' . json_encode($this->params));
        
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
            if (strlen($roleid) == 7 || in_array($roleid, $test_uidarr)) {
                 $this->config = config('hacksaw_test');
                 config('trans_url_other',config('test_trans_url'));
                 
            }
            $language = $param['language'] ?: $this->config['language'];
            if (strtoupper($language) == 'BR') {
                $language = 'pt';
            }


            $gameid = $param['gameid'];

            $brand_uid = config('platform_name').'abc'.$roleid;
            $params = [
                'brand_id'     =>$this->config['Merchant_ID'],
                'sign'         =>strtoupper(md5($this->config['Merchant_ID'].$brand_uid.$this->config['Secret_Key'])),
                'brand_uid'    =>$brand_uid,
                'token'        =>$brand_uid,
                'game_id'      =>(int)$gameid,
                'currency'     =>$this->config['Currency'],
                'language'     =>$language,
                'channel'      =>'mobile',
                'country_code' =>$this->config['country'],
            ]; 
            $header = [
                'content-type:application/json'
            ];
            $gameURL = $this->config['API_Host'].'/dcs/loginGame';
            if (config('is_hacksaw_trans') == 1) {
                $params['url'] = $gameURL;
                $res = $this->curl(config('trans_url_other').'/hacksaw/index/createuser',$params);
            } else {
                $res = $this->curl($gameURL,json_encode($params),$header);
            }
            $res = json_decode($res,true);
            if($res['code'] == '1000'){
                $gameURL = $res['data']['game_url'];
            } else {
                $gameURL = '';
            }
            return $this->succjson($gameURL);
            
        } catch (Exception $ex) {
            save_log('hacksaw_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjson('api error');
        }
    }

    public function login(){
        try {
            $params    = $this->params;
            $brand_id  = $params['brand_id'];
            $hash      = $params['sign'];
            $token     = $params['token'];
            $brand_uid = $params['brand_uid'];
            

            $check_hash = strtoupper(md5($this->config['Merchant_ID'].$token.$this->config['Secret_Key']));
            if ($check_hash != $hash) {
                return $this->apiReturn(5000,'Sign error.');
            }
            $user_id = explode('abc',$brand_uid);
            if (!isset($user_id[1])) {
                return $this->apiReturn(5009,'Player not exist.');
            }
            $user_id = $user_id[1];

            $balance = $this->getSocketBalance($user_id);
            $data = [
                'brand_uid' =>$brand_uid,
                'currency'  =>$this->config['Currency'],
                'balance'   =>$balance
            ];
            return $this->apiReturn(1000,'Success.',$data);
        } catch (Exception $ex) {
            save_log('hacksaw_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->apiReturn(1001,'System error.');
        }
    }

    //下注扣钱
    public function wager(){
        try {
            $params     = $this->params;
            $brand_id   = $params['brand_id'];
            $hash       = $params['sign'];
            $brand_uid  = $params['brand_uid'];
            $amount     = $params['amount'];
            $game_id    = $params['game_id'];
            $round_id   = $params['round_id'];
            $bet_id     = $params['wager_id'];
            $is_endround = $params['is_endround'];
            
            $user_id = explode('abc',$brand_uid);
            if (!isset($user_id[1])) {
                return $this->apiReturn(5009,'Player not exist.');
            }
            $user_id = $user_id[1];
            $balance = $this->getSocketBalance($user_id);
            $data = [
                'brand_uid' =>$brand_uid,
                'currency'  =>$this->config['Currency'],
                'balance'   =>$balance
            ];
            if (Redis::get('hacksaw_is_exec_bet_'.$round_id.'_'.$bet_id)) {
                return $this->apiReturn(5043,'Bet record duplicate.',$data);
            }
            $check_hash = strtoupper(md5($this->config['Merchant_ID'].$bet_id.$this->config['Secret_Key']));
            if ($check_hash != $hash) {
                return $this->apiReturn(5000,'Sign error.',$data);
            }

            if ($balance < $amount) {
                return $this->apiReturn(5003,'Balance insufficient.',$data);
            }

            $socket    = new QuerySocket();
            $gamemoney = bcmul($amount,bl,0);
            $state = $socket->downScore($user_id, $gamemoney, $bet_id,41000);
            if ($state['iResult']!=0) {
                return $this->apiReturn(1001,'System error.');
            }
            //记录订单是否执行，防止重复
            Redis::set('hacksaw_is_exec_bet_'.$round_id.'_'.$bet_id,1,3600);
            //记录下注数量
            $gamemoney2 = Redis::get('hacksaw_amount_bet_'.$user_id.'_'.$round_id) ?: 0;
            $gamemoney = $gamemoney + $gamemoney2;
            Redis::set('hacksaw_amount_bet_'.$user_id.'_'.$round_id,$gamemoney);

            if ($is_endround == true) {
                $this->clearData($user_id,$round_id);
            }
            $balance = $this->getSocketBalance($user_id);
            $data['balance'] = $balance;
            return $this->apiReturn(1000,'Success.',$data);
        } catch (Exception $ex) {
            save_log('hacksaw_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->apiReturn(1001,'System error.');
        }
    }

    //结算加钱
    public function endWager(){
        try {
            $params    = $this->params;
            $brand_id   = $params['brand_id'];
            $hash       = $params['sign'];
            $brand_uid  = $params['brand_uid'];
            $amount     = $params['amount'];
            $round_id   = $params['round_id'];
            $bet_id     = $params['wager_id'];
            $is_endround = $params['is_endround'];
            
            $user_id = explode('abc',$brand_uid);
            if (!isset($user_id[1])) {
                return $this->apiReturn(5009,'Player not exist.');
            }
            $user_id = $user_id[1];
            $balance = $this->getSocketBalance($user_id);
            $data = [
                'brand_uid' =>$brand_uid,
                'currency'  =>$this->config['Currency'],
                'balance'   =>$balance
            ];
            if (Redis::get('hacksaw_is_exec_result_'.$round_id.'_'.$bet_id)) {
                return $this->apiReturn(5043,'Bet record duplicate.',$data);
            }
            $check_hash = strtoupper(md5($this->config['Merchant_ID'].$bet_id.$this->config['Secret_Key']));
            if ($check_hash != $hash) {
                return $this->apiReturn(5000,'Sign error.',$data);
            }

            //是否下注
            if (!Redis::has('hacksaw_amount_bet_'.$user_id.'_'.$round_id)) {
                return $this->apiReturn(5042,'Bet record does not exist.',$data);
            }
             //记录订单是否执行，防止重复
            Redis::set('hacksaw_is_exec_result_'.$round_id.'_'.$bet_id,1,3600);

            $socket    = new QuerySocket();
            $gamemoney = bcmul($amount,bl,0);
            $gamemoney2 = Redis::get('hacksaw_amount_bet_'.$user_id.'_'.$round_id);
            $state = $socket->UpScore2($user_id, $gamemoney, $bet_id,41000,$gamemoney2);
            if ($state['iResult']!=0) {
                return $this->apiReturn(1001,'System error.');
            }
           
            //记录结算数量
            Redis::set('hacksaw_amount_result_'.$user_id.'_'.$round_id,$gamemoney);

            if ($is_endround == true) {
                $this->clearData($user_id,$round_id);
            }
            $balance = $this->getSocketBalance($user_id);
            $data['balance'] = $balance;
            return $this->apiReturn(1000,'Success.',$data);
        } catch (Exception $ex) {
            save_log('hacksaw_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->apiReturn(1001,'System error.');
        }
    }

    //取消下注或取消结算
    public function cancelWager(){
        try {
            $params    = $this->params;
            $brand_id   = $params['brand_id'];
            $hash       = $params['sign'];
            $brand_uid  = $params['brand_uid'];
            $round_id   = $params['round_id'];
            $bet_id     = $params['wager_id'];
            $is_endround = $params['is_endround'];
            $wager_type  = $params['wager_type'];
            
            $user_id = explode('abc',$brand_uid);
            if (!isset($user_id[1])) {
                return $this->apiReturn(5009,'Player not exist.');
            }
            $user_id = $user_id[1];
            $balance = $this->getSocketBalance($user_id);
            $data = [
                'brand_uid' =>$brand_uid,
                'currency'  =>$this->config['Currency'],
                'balance'   =>$balance
            ];
            if (Redis::get('hacksaw_is_exec_cancelWager_'.$round_id.'_'.$bet_id)) {
                return $this->apiReturn(5043,'Bet record duplicate.',$data);
            }
            $check_hash = strtoupper(md5($this->config['Merchant_ID'].$bet_id.$this->config['Secret_Key']));
            if ($check_hash != $hash) {
                return $this->apiReturn(5000,'Sign error.',$data);
            }

            $socket = new QuerySocket();
            if ($wager_type == 1) {
                //加钱
                if (!Redis::has('hacksaw_amount_result_'.$user_id.'_'.$round_id)) {
                    return $this->apiReturn(5042,'Bet record does not exist.',$data);
                }
                //记录订单是否执行，防止重复
                Redis::set('hacksaw_is_exec_cancelWager_'.$round_id.'_'.$bet_id,1,3600);
                $gamemoney  = Redis::get('hacksaw_amount_result_'.$user_id.'_'.$round_id);
                $gamemoney2 = 0;
                $state = $socket->UpScore2($user_id, $gamemoney, $bet_id,41000,$gamemoney2);
                if ($state['iResult']!=0) {
                    return $this->apiReturn(1001,'System error.');
                }
            } else {
                //扣钱
                if ($balance < $amount) {
                    return $this->apiReturn(5003,'Balance insufficient.',$data);
                }
                if (!Redis::has('hacksaw_amount_bet_'.$user_id.'_'.$round_id)) {
                    return $this->apiReturn(5042,'Bet record does not exist.',$data);
                }
                $gamemoney  = Redis::get('hacksaw_amount_bet_'.$user_id.'_'.$round_id);
                $state = $socket->downScore($user_id, $gamemoney, $bet_id,41000);
                if ($state['iResult']!=0) {
                    return $this->apiReturn(1001,'System error.');
                }
            }
            
            if ($is_endround == true) {
                $this->clearData($user_id,$round_id);
            }
            $balance = $this->getSocketBalance($user_id);
            $data['balance'] = $balance;
            return $this->apiReturn(1000,'Success.',$data);
        } catch (Exception $ex) {
            save_log('hacksaw_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->apiReturn(1001,'System error.');
        }
    }

    //派彩加钱
    public function appendWager(){
        try {
            $params    = $this->params;
            $brand_id   = $params['brand_id'];
            $hash       = $params['sign'];
            $brand_uid  = $params['brand_uid'];
            $amount     = $params['amount'];
            $game_id    = $params['game_id'];
            $round_id   = $params['round_id'];
            $bet_id     = $params['wager_id'];
            $is_endround = $params['is_endround'];
            
            $user_id = explode('abc',$brand_uid);
            if (!isset($user_id[1])) {
                return $this->apiReturn(5009,'Player not exist.');
            }
            $user_id = $user_id[1];
            $balance = $this->getSocketBalance($user_id);
            $data = [
                'brand_uid' =>$brand_uid,
                'currency'  =>$this->config['Currency'],
                'balance'   =>$balance
            ];
            if (Redis::get('hacksaw_is_exec_appendWager_'.$round_id.'_'.$bet_id)) {
                return $this->apiReturn(5043,'Bet record duplicate.',$data);
            }
            $check_hash = strtoupper(md5($this->config['Merchant_ID'].$bet_id.$this->config['Secret_Key']));
            if ($check_hash != $hash) {
                return $this->apiReturn(5000,'Sign error.',$data);
            }

            //记录订单是否执行，防止重复
            Redis::set('hacksaw_is_exec_appendWager_'.$round_id.'_'.$bet_id,1,3600);

            $socket    = new QuerySocket();
            $gamemoney = bcmul($amount,bl,0);
            $gamemoney2 = 0;
            $state = $socket->UpScore2($user_id, $gamemoney, $bet_id,41000,$gamemoney2);
            if ($state['iResult']!=0) {
                return $this->apiReturn(1001,'System error.');
            }

            if ($is_endround == true) {
                $this->clearData($user_id,$round_id);
            }
            $balance = $this->getSocketBalance($user_id);
            $data['balance'] = $balance;
            return $this->apiReturn(1000,'Success.',$data);
        } catch (Exception $ex) {
            save_log('hacksaw_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->apiReturn(1001,'System error.');
        }
    }

    //活动投注加钱
    public function freeSpinResult(){
        try {
            $params    = $this->params;
            $brand_id   = $params['brand_id'];
            $hash       = $params['sign'];
            $brand_uid  = $params['brand_uid'];
            $amount     = $params['amount'];
            $game_id    = $params['game_id'];
            $round_id   = $params['round_id'];
            $bet_id     = $params['wager_id'];
            $is_endround = $params['is_endround'];
            
            $user_id = explode('abc',$brand_uid);
            if (!isset($user_id[1])) {
                return $this->apiReturn(5009,'Player not exist.');
            }
            $user_id = $user_id[1];
            $balance = $this->getSocketBalance($user_id);
            $data = [
                'brand_uid' =>$brand_uid,
                'currency'  =>$this->config['Currency'],
                'balance'   =>$balance
            ];
            if (Redis::get('hacksaw_is_exec_freeSpinResult_'.$round_id.'_'.$bet_id)) {
                return $this->apiReturn(5043,'Bet record duplicate.',$data);
            }
            $check_hash = strtoupper(md5($this->config['Merchant_ID'].$bet_id.$this->config['Secret_Key']));
            if ($check_hash != $hash) {
                return $this->apiReturn(5000,'Sign error.',$data);
            }

            //记录订单是否执行，防止重复
            Redis::set('hacksaw_is_exec_freeSpinResult_'.$round_id.'_'.$bet_id,1,3600);

            $socket    = new QuerySocket();
            $gamemoney = bcmul($amount,bl,0);
            $gamemoney2 = 0;
            $state = $socket->UpScore2($user_id, $gamemoney, $bet_id,41000,$gamemoney2);
            if ($state['iResult']!=0) {
                return $this->apiReturn(1001,'System error.');
            }

            if ($is_endround == true) {
                $this->clearData($user_id,$round_id);
            }
            $balance = $this->getSocketBalance($user_id);
            $data['balance'] = $balance;
            return $this->apiReturn(1000,'Success.',$data);
        } catch (Exception $ex) {
            save_log('hacksaw_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->apiReturn(1001,'System error.');
        }
    }

    //获取余额
    public function getBalance(){
        try {
            $params    = $this->params;
            $brand_id   = $params['brand_id'];
            $hash       = $params['sign'];
            $brand_uid  = $params['brand_uid'];
            $token      = $params['token'];
            
            $user_id = explode('abc',$brand_uid);
            if (!isset($user_id[1])) {
                return $this->apiReturn(5009,'Player not exist.');
            }
            $user_id = $user_id[1];
            $balance = $this->getSocketBalance($user_id);
            $data = [
                'brand_uid' =>$brand_uid,
                'currency'  =>$this->config['Currency'],
                'balance'   =>$balance
            ];
            
            $check_hash = strtoupper(md5($this->config['Merchant_ID'].$token.$this->config['Secret_Key']));
            if ($check_hash != $hash) {
                return $this->apiReturn(5000,'Sign error.',$data);
            }
            return $this->apiReturn(1000,'Success.',$data);
        } catch (Exception $ex) {
            save_log('hacksaw_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->apiReturn(1001,'System error.');
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
    private function clearData($user_id,$round_id)
    {
        $clear_data = Redis::get('hacksaw_game_id_'.$user_id) ?: [];
        $clear_data[$round_id] = 1;
        unset($clear_data[$round_id]);
        if (empty($clear_data)) {
            $socket    = new QuerySocket();
            $state = $socket->ClearLablel($user_id,41000);
            Redis::rm('hacksaw_game_id_'.$user_id);
        } else {
            Redis::set('hacksaw_game_id_'.$user_id,$clear_data);
        }
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


    //创建临时订单
    private function makeOrderId($uid)
    {
        return date('YmdHis') . sprintf('%.0f', floatval(explode(' ', microtime())[0]) * 1000) . $uid;
    }
    //加密
    private function encry($str,$key='hacksaw'){
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
    private function decry($str,$key='hacksaw'){
        return think_decrypt($str,$key);
        if (!$key) {
            return $str;
        }
        $data = base64_decode($str);
        $data = openssl_decrypt($data, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        return $data;
    }
}