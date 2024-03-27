<?php

namespace app\kingmakergame\controller;

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
        save_log('kingmaker', '==='.request()->url().'===接口请求数据===' . json_encode(request()->param()));
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
            $roleid = $param['roleid']?:'88712997';
            $language = $param['language']?:$this->config['language'];
            if (strtoupper($language) == 'BR') {
                $language = 'pt';
            }
            $accountM = new AccountDB();
            $user = $accountM->getRow(['AccountID' => $roleid], 'AccountID,AccountName');
            if (!$user) {
                return $this->failjson('the player is not exist');
            }

            $gameid = $param['gameid'] ?: '001';
            $gameid = 'KINGMAKER-SLOT-'.$gameid;
            $header = [
                'content-type:application/x-www-form-urlencoded'
            ];
            $post_data = [
                'cert'=>$this->config['Secret_Key'],
                'agentId'=>$this->config['Merchant_ID'],
                'userId'=>$roleid,
                'currency'=>$this->config['Currency'],
                'language'=>$language,
            ];
            $result = $this->curl($this->config['API_Host'].'wallet/createMember',$post_data,$header);

            save_log('kingmaker', '===第三方玩家创建===' . json_encode($result));
            $result = json_decode($result,1);
            if($result['status'] != '0000' && $result['status'] != '1001'){
                return $this->failjson($result['desc']);
            }
            
            $post_data = [
                'cert'=>$this->config['Secret_Key'],
                'agentId'=>$this->config['Merchant_ID'],
                'userId'=>$roleid,
                'platform'=>'KINGMAKER',
                'gameType'=>'SLOT',
                'gameCode'=>$gameid,
                'language'=>$language,
            ];

            $result = $this->curl($this->config['API_Host'].'wallet/doLoginAndLaunchGame',$post_data,$header);
            $result = json_decode($result,1);
            if($result['status'] == '0000'){
                return $this->succjson($result['url']);
            } else {
                return $this->failjson('create player faild');
            }

        } catch (Exception $ex) {
            save_log('kingmaker_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjson('api error');
        }
    }

     public function _empty()
    {
        $params  = request()->param() ?: json_decode(file_get_contents('php://input'),1);
        $key     = $params['key']??'';
        if($key != $this->config['Secret_Key']){

            $respons = [
                "status"  =>"1027",
                "desc"   =>'Invalid Certificate',
            ];
            save_log('kingmaker', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);
        }
        $message  = $params['message']??'';
        $params = json_decode($message,true);
        $action = $params['action'];

        if ($action == 'voidBet') {
            $params['winAmount'] = $params['betAmount'];
            $action = 'settle';
        }

        return $this->$action($params);
    }

    private function getBalance($params=[]){
        try {
            $params     = $params ?: request()->param() ?: json_decode(file_get_contents('php://input'),1);
            $user_id    = $params['userId']??'';
            if(empty($user_id)){
                $respons = [
                    "status"  =>1036,
                    "desc"   =>'Invalid parameters.',
                ];
                save_log('kingmaker', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }
            $balance = $this->getSocketBalance($user_id); 
            $respons = [
                'status'    =>0000,
                'desc'      =>'SUCCESS',
                "balance"   =>round($balance,2),
                "balanceTs" =>date(DATE_ISO8601),
                "userId"    =>$user_id
            ];
            save_log('kingmaker', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('kingmaker_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            $respons = [
                "status"   =>9999,
                "desc"   =>'Fail',
            ];
            return json($respons);
        }
    }


    private function bet($params=[]){
        try {
            $params  = $params ?: request()->param() ?: json_decode(file_get_contents('php://input'),1);
            $userId         = $params['userId']??'';
            $transaction_id = $params['platformTxId']??'';
            $bet_amount     = $params['betAmount']??'';
            if(empty($user_id) || empty($transaction_id) || empty($bet_amount)){
                $respons = [
                    "status"  =>"1036",
                    "desc"   =>'Invalid parameters.',
                ];
                save_log('kingmaker', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }

            if (Redis::get('kingmaker_is_exec_bet_'.$transaction_id)) {
                $respons = [
                    "status"   =>"1038",
                    "desc"   =>'Duplicate transaction.',
                ];
                save_log('kingmaker', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }
            $balance = $this->getSocketBalance($user_id);
 
            if ($balance < $bet_amount) {
                $respons = [
                    "status"   =>"1018",
                    "desc"   =>'Not Enough Balance',
                ];
                save_log('kingmaker', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }
            $socket = new QuerySocket();
            if ($bet_amount > 0) {
                $gamemoney = bcmul($bet_amount,bl,0);
                $state = $socket->downScore($user_id, $gamemoney, $transaction_id,39100);
                if ($state['iResult']!=0) {
                    $respons = [
                        "status"   =>"9999",
                        "desc"   =>'Fail',
                    ];
                    save_log('kingmaker', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                    return json($respons);
                }
            }

            Redis::set('kingmaker_is_exec_bet_'.$transaction_id,1,3600);
            $balance = $this->getSocketBalance($user_id);
            $respons = [
                'status'    =>"0000",
                'desc'      =>'SUCCESS',
                "balance"   =>round($balance,2),
                "balanceTs" =>date(DATE_ISO8601)
            ];
            save_log('kingmaker', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('kingmaker_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            $respons = [
                "status"   =>"9999",
                "desc"   =>'Fail',
            ];
            return json($respons);
        }
    }

    private function cancelBet($params=[]){
        try {
            $params     = $params ?: request()->param() ?: json_decode(file_get_contents('php://input'),1);
            $user_id    = $params['userId']??'';
            if(empty($user_id)){
                $respons = [
                    "status"  =>1036,
                    "desc"   =>'Invalid parameters.',
                ];
                save_log('kingmaker', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }
            $balance = $this->getSocketBalance($user_id); 
            $respons = [
                'status'    =>0000,
                'desc'      =>'SUCCESS',
                "balance"   =>round($balance,2),
                "balanceTs" =>date(DATE_ISO8601),
            ];
            save_log('kingmaker', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('kingmaker_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            $respons = [
                "status"   =>9999,
                "desc"   =>'Fail',
            ];
            return json($respons);
        }
    }

    private function settle($params=[]){
        try {
            $params  = $params ?: request()->param() ?: json_decode(file_get_contents('php://input'),1);
            $userId         = $params['userId']??'';
            $transaction_id = $params['platformTxId']??'';
            $bet_amount     = $params['bet_amount']??0;
            $win_amount     = $params['winAmount']??'';
            if(empty($user_id) || empty($transaction_id) || empty($win_amount)){
                $respons = [
                    "status"  =>"1036",
                    "desc"   =>'Invalid parameters.',
                ];
                save_log('kingmaker', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }

            if (Redis::get('kingmaker_is_exec_settle_'.$transaction_id)) {
                $respons = [
                    "status"   =>"1038",
                    "desc"   =>'Duplicate transaction.',
                ];
                save_log('kingmaker', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }
 
            $socket = new QuerySocket();
            if ($win_amount >= 0) {
                $gamemoney = bcmul($win_amount,bl,0);
                $gamemoney2 = bcmul($bet_amount,bl,0);
                $state = $socket->UpScore2($user_id, $gamemoney, $transaction_id,39100,$gamemoney2);
            }

            Redis::set('kingmaker_is_exec_settle_'.$transaction_id,1,3600);
            $balance = $this->getSocketBalance($user_id);
            $respons = [
                'status'    =>"0000",
                'desc'      =>'SUCCESS',
            ];
            save_log('kingmaker', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('kingmaker_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            $respons = [
                "status"   =>"9999",
                "desc"   =>'Fail',
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