<?php

namespace app\cq9game\controller;

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
        save_log('cq9game', '==='.request()->url().'===接口请求数据===' . json_encode(request()->param()));
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
            $language = $param['language']?:$this->config['language'];
            if (strtoupper($language) == 'BR') {
                $language = 'pt';
            }
            $accountM = new AccountDB();
            $user = $accountM->getRow(['AccountID' => $roleid], 'AccountID,AccountName');
            if (!$user) {
                return $this->failjson('the player is not exist');
            }

            $gameid = $param['gameid'] ?: "AB3";
            $header = [
                'Content-Type:application/x-www-form-urlencoded',
                'Authorization:'.$this->config['Operator_Token']
            ];
            $post_data = [
                'account'=>$roleid,
                'gamehall'=>'cq9',
                'gamecode'=>$gameid,
                'gameplat'=>'web',
                'lang'=>'en',
            ];
            $result = $this->curl($this->config['API_Host'].'gameboy/player/sw/gamelink',$post_data,$header);
            save_log('cq9game', '===第三方玩家创建===' . json_encode($result));
            $result = json_decode($result,true);
            if($result['status']['code'] != 0){
                return $this->failjson($result['status']['message']);
            }

            return $this->succjson($result['data']['url']);

        } catch (Exception $ex) {
            save_log('cq9game_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjson('api error');
        }
    }

    public function player(){
        try {
            $params     = request()->param() ?: json_decode(file_get_contents('php://input'),1);
            $user_id    = $params['check']??'';

            $wtoken     = request()->header('wtoken');
            if ($wtoken != $this->config['Operator_Token']) {
                $respons = [
                    "data"=>false,
                    "status"=>[
                        "code"=>"3",
                        "message"=>"Token invalid",
                        "datetime"=>date(DATE_RFC3339)
                    ]
                ];
                save_log('cq9game', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }

            $respons = [
                "data"=>true,
                "status"=>[
                    "code"=>"0",
                    "message"=>"Success",
                    "datetime"=>date(DATE_RFC3339)
                ]
            ];
            save_log('cq9game', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('cq9game_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            $respons = [
                "data"=>false,
                "status"=>[
                    "code"=>"100",
                    "message"=>"Something wrong",
                    "datetime"=>date(DATE_RFC3339)
                ]
            ];
            return json($respons);
        }
    }


    public function transaction(){
        try {
            $wtoken     = request()->header('wtoken');
            if ($wtoken != $this->config['Operator_Token']) {
                $respons = [
                    "data"=>false,
                    "status"=>[
                        "code"=>"3",
                        "message"=>"Token invalid",
                        "datetime"=>date(DATE_RFC3339)
                    ]
                ];
                save_log('cq9game', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }

            $params = request()->param() ?: json_decode(file_get_contents('php://input'),1);
            if (isset($params['balance'])) {
                return $this->getBalance($params['balance']);
            }
            if (isset($params['game'])) {
                if ($params['game'] == 'rollout') {
                    return $this->bet($params);
                }
                if ($params['game'] == 'rollin') {
                    return $this->settle($params);
                }

            }
        } catch (Exception $ex) {
            save_log('cq9game_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            $respons = [
                "data"=>false,
                "status"=>[
                    "code"=>"100",
                    "message"=>"Something wrong",
                    "datetime"=>date(DATE_RFC3339)
                ]
            ];
            return json($respons);
        }
    }

    private function getBalance($AccountID=0){
        try {

            $user_id    = $AccountID;
            $balance = $this->getSocketBalance($user_id);
            $respons = [
                "data"=>[
                    "balance"=>round($balance,4),
                    'currency'=>$this->config['Currency']
                ],
                "status"=>[
                    "code"=>"0",
                    "message"=>"Success",
                    "datetime"=>date(DATE_RFC3339)
                ]
            ];
            save_log('cq9game', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('cq9gamer_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            $respons = [
                "data"=>false,
                "status"=>[
                    "code"=>"100",
                    "message"=>"Something wrong",
                    "datetime"=>date(DATE_RFC3339)
                ]
            ];
            return json($respons);
        }
    }

    private function bet($params=[]){
        try {
            $params  = $params ?: request()->param() ?: json_decode(file_get_contents('php://input'),1);
            $user_id        = $params['account']??'';
            $transaction_id = $params['roundid']??'';
            $bet_amount     = $params['amount']??'';
            if(empty($user_id) || empty($transaction_id) || empty($bet_amount)){
                $respons = [
                    "data"=>false,
                    "status"=>[
                        "code"=>"5",
                        "message"=>"Bad parameters",
                        "datetime"=>date(DATE_RFC3339)
                    ]
                ];
                save_log('cq9game', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }

            if (Redis::get('cq9game_is_exec_bet_'.$transaction_id)) {
                $respons = [
                    "data"=>false,
                    "status"=>[
                        "code"=>"9",
                        "message"=>"MTCode duplicate",
                        "datetime"=>date(DATE_RFC3339)
                    ]
                ];
                save_log('cq9game', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }
            $balance = $this->getSocketBalance($user_id);

            if ($balance < $bet_amount) {
                $respons = [
                    "data"=>false,
                    "status"=>[
                        "code"=>"1",
                        "message"=>"Insufficient balance",
                        "datetime"=>date(DATE_RFC3339)
                    ]
                ];
                save_log('cq9game', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }
            $socket = new QuerySocket();
            if ($bet_amount > 0) {
                $gamemoney = bcmul($bet_amount,bl,0);
                $state = $socket->downScore($user_id, $gamemoney, $transaction_id,39200);
                if ($state['iResult']!=0) {
                    $respons = [
                        "data"=>false,
                        "status"=>[
                            "code"=>"100",
                            "message"=>"Something wrong",
                            "datetime"=>date(DATE_RFC3339)
                        ]
                    ];
                    save_log('cq9game', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                    return json($respons);
                }
            }

            Redis::set('cq9game_is_exec_bet_'.$transaction_id,1,3600);
            $balance = $this->getSocketBalance($user_id);
            $respons = [
                "data"=>[
                    "balance"=>round($balance,4),
                    'currency'=>$this->config['Currency']
                ],
                "status"=>[
                    "code"=>"0",
                    "message"=>"Success",
                    "datetime"=>date(DATE_RFC3339)
                ]
            ];
            save_log('cq9game', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('cq9game_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            $respons = [
                "data"=>false,
                "status"=>[
                    "code"=>"100",
                    "message"=>"Something wrong",
                    "datetime"=>date(DATE_RFC3339)
                ]
            ];
            return json($respons);
        }
    }

    private function settle($params=[]){
        try {
            $params  = $params ?: request()->param() ?: json_decode(file_get_contents('php://input'),1);
            $user_id        = $params['account']??'';
            $transaction_id = $params['roundid']??'';
            $bet_amount     = $params['bet']??0;
            $win_amount     = $params['amount']??'';
            if(empty($user_id) || empty($transaction_id) || empty($win_amount)){
                $respons = [
                    "data"=>false,
                    "status"=>[
                        "code"=>"5",
                        "message"=>"Bad parameters",
                        "datetime"=>date(DATE_RFC3339)
                    ]
                ];
                save_log('cq9game', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }

            if (Redis::get('cq9game_is_exec_settle_'.$transaction_id)) {
                $respons = [
                    "data"=>false,
                    "status"=>[
                        "code"=>"9",
                        "message"=>"MTCode duplicate",
                        "datetime"=>date(DATE_RFC3339)
                    ]
                ];
                save_log('cq9game', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }

            $socket = new QuerySocket();
            if ($win_amount >= 0) {
                $gamemoney = bcmul($win_amount,bl,0);
                $gamemoney2 = bcmul($bet_amount,bl,0);
                $state = $socket->UpScore2($user_id, $gamemoney, $transaction_id,39200,$gamemoney2);
            }

            Redis::set('cq9game_is_exec_settle_'.$transaction_id,1,3600);
            $balance = $this->getSocketBalance($user_id);
            $respons = [
                "data"=>[
                    "balance"=>round($balance,4),
                    'currency'=>$this->config['Currency']
                ],
                "status"=>[
                    "code"=>"0",
                    "message"=>"Success",
                    "datetime"=>date(DATE_RFC3339)
                ]
            ];
            save_log('cq9game', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('cq9game_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            $respons = [
                "data"=>false,
                "status"=>[
                    "code"=>"100",
                    "message"=>"Something wrong",
                    "datetime"=>date(DATE_RFC3339)
                ]
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