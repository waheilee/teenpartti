<?php

namespace app\yesbingo\controller;

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
        // $params = request()->param() ?: json_decode(file_get_contents('php://input'),1);
        // save_log('yesbingo', '==='.request()->url().'===接口请求数据===' . json_encode($params));
        
    }


    //游戏对外接口创建玩家
    public function createuser()
    {
        try {
            $param = jsonRequest(['roleid', 'gameid', 'language','session_id', 'ip','time', 'sign','gType']);
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
                 $this->config = config('yesbingo_test');
                 config('trans_url_other',config('test_trans_url'));
            }
            $language = $param['language'] ?: $this->config['language'];
            if (strtoupper($language) == 'BR') {
                $language = 'pt';
            }

            $gameid = $param['gameid'];

            $balance = $this->getSocketBalance($roleid);
            $params = [
                'action'=>21,
                'ts'=>time()*1000,
                'parent'=>$this->config['Merchant_ID'],
                'uid'=>config('platform_name').'abc'.$roleid,
                'balance'=>$balance,
                'lang'=>$language,
                'gType'=>$param['gType'] ?? "3",
                'mType'=>$gameid,
            ];
            $params = $this->encrypt(json_encode($params),$this->config['Secret_Key'],$this->config['Operator_Token']);
            $gameURL = $this->config['GAME_URL'].$params;

            if (config('is_yesbingo_trans') == 1 || true) {
                $post_param['url'] = $gameURL;
                $res = $this->curl(config('trans_url_other').'/yesbingo/index/createuser',$post_param);
            } else {
                $res = $this->curl($gameURL);
            }
            $res = json_decode($res,true);
            if($res['status'] == '0000'){
                $gameURL = $res['path'];
            } else {
                $gameURL = '';
            }
            return $this->succjson($gameURL);
            
        } catch (Exception $ex) {
            save_log('spribe_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjson('api error');
        }
    }

    public function index(){
        try {
            $params = request()->param() ?: json_decode(file_get_contents('php://input'),1);
            
            
            $x = $params['x'];

            $params = $this->decrypt($x,$this->config['Secret_Key'],$this->config['Operator_Token']);

            save_log('yesbingo', '==='.request()->url().'===接口请求数据===' . $params);
            $params = json_decode($params,true);
            $action = $params['action'];
            $user_id = $AccountID =  explode('abc', $params['uid'])[1] ?: 0;
            if (!$user_id) {
                $respons = [
                    "status"=>"7501",
                    'balance'=>0,
                    'err_text'=>''
                ];
                save_log('yesbingo', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }
            $balance = $this->getSocketBalance($user_id);
            
            if ($action == 6) {
                $respons = [
                    "status"=>"0000",
                    'balance'=>$balance,
                    'err_text'=>''
                ];
                save_log('yesbingo', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }
            
            if ($action == 3) {
                $amount = abs($params['bet']);
                $gameId = $params['mType'];
                $transaction_id = $reference = $params['transferId'];

                if (Redis::get('yesbingo_is_exec_bet_'.$user_id.$reference)) {
                    $respons = [
                        "status"=>"9999",
                        'balance'=>$balance,
                        'err_text'=>'Request duplicate'
                    ];
                    save_log('yesbingo', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                    return json($respons);exit();
                }
                

                if ($balance < $amount) {
                    $respons = [
                        "status"=>"6006",
                        'balance'=>$balance,
                        'err_text'=>'Insufficient Balance'
                    ];
                    save_log('yesbingo', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                    return json($respons);exit(); 
                }

                
                $clear_data = Redis::get('yesbingo_game_id_'.$user_id) ?: [];
                $clear_data[$reference] = 1;
                Redis::set('yesbingo_game_id_'.$user_id,$clear_data);

                $gamemoney = bcmul($amount,bl,0);
                $socket = new QuerySocket();
                $state = $socket->downScore($user_id, $gamemoney, $reference,42000);
                if ($state['iResult']!=0) {
                    $respons = [
                        "status"=>"9999",
                        'balance'=>$balance,
                        "err_text"=>"api error"
                    ];
                    save_log('yesbingo', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                    return json($respons);
                }
                //记录下注数量
                //记录订单是否执行，防止重复
                Redis::set('yesbingo_is_exec_bet_'.$user_id.$reference,$gamemoney,3600);

                $left_balance = $this->getSocketBalance($user_id);
                $respons = [
                    "status"=>"0000",
                    'balance'=>$left_balance,
                    "err_text"=>""
                ];
                save_log('yesbingo', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }

            if ($action == 4) {
                //取消下注
                // $transaction_id = $reference = $params['transferId'];

                // if (Redis::get('yesbingo_is_exec_cancel_'.$user_id.$reference)) {
                //     $respons = [
                //         "status"=>"9999",
                //         'balance'=>$balance,
                //         'err_text'=>'Request duplicate'
                //     ];
                //     save_log('yesbingo', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                //     return json($respons);exit();
                // }
                //  //不存在这个下注
                // if (!Redis::has('yesbingo_is_exec_bet_'.$user_id.$reference)) {
                //     $respons = [
                //         "status"=>"0000",
                //         'balance'=>$balance,
                //     ];
                //     save_log('yesbingo', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                //     return json($respons);
                // }
                // $socket = new QuerySocket();
                // $gamemoney = Redis::get('yesbingo_is_exec_bet_'.$user_id.$reference);
                // $state = $socket->UpScore2($user_id, $gamemoney, $transaction_id,42000,0);
                // if ($state['iResult']!=0) {
                //     $respons = [
                //         "status"=>"9999",
                //         'balance'=>$balance,
                //         "err_text"=>"api error"
                //     ];
                //     save_log('yesbingo', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                //     return json($respons);
                // }
                // //记录订单是否执行，防止重复
                // Redis::set('yesbingo_is_exec_cancel_'.$user_id.$reference,$gamemoney,3600);

                $left_balance = $this->getSocketBalance($user_id);
                $respons = [
                    "status"=>"0000",
                    'balance'=>$left_balance,
                    "err_text"=>""
                ];
                save_log('yesbingo', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }

            if ($action == 8) {
                $bet_amount = $params['bet'];
                $win_amount = $params['win'];
                $gameId = $params['mType'];
                $transaction_id = $reference = $params['transferId'];

                if (Redis::get('yesbingo_is_exec_result_'.$user_id.$reference)) {
                    $respons = [
                        "status"=>"9999",
                        'balance'=>$balance,
                        'err_text'=>'Request duplicate'
                    ];
                    save_log('yesbingo', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                    return json($respons);
                } else {
                    Redis::set('yesbingo_is_exec_result_'.$user_id.$reference,1,3600);
                }
                
                
                $socket = new QuerySocket();

                $gamemoney  = bcmul($win_amount,bl,0);
                $gamemoney2 = bcmul($bet_amount,bl,0);
                $state = $socket->UpScore2($user_id, $gamemoney, $transaction_id,42000,$gamemoney2);
   
                $clear_data = Redis::get('yesbingo_game_id_'.$user_id) ?: [];
                unset($clear_data[$transaction_id]);
                
                if (empty($clear_data)) {
                    $state = $socket->ClearLablel($user_id,42000);
                    Redis::rm('yesbingo_game_id_'.$user_id);
                } else {
                    Redis::set('yesbingo_game_id_'.$user_id,$clear_data);
                }
                if (config('need_third_rank') == 1) {
                    Redis::lpush('third_game_rank_list',json_encode([
                        'PlatformId'=>42000,
                        'PlatformName'=>'YESBINGO',
                        'GameId'=>$gameId,
                    ]));
                }
                
                $left_balance = $this->getSocketBalance($user_id);
                $respons = [
                    "status"=>"0000",
                    'balance'=>$left_balance,
                    "err_text"=>""
                ];
                save_log('yesbingo', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }

            if ($action == 9) {
                
            }
        } catch (Exception $ex) {
            save_log('yesbingo_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            $respons = [
                "status"=>"9999",
                "err_text"=>"api error"
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

    private  function encrypt($data, $key, $iv)
    {
          $data = $this->padString($data);
          $encrypted = openssl_encrypt($data, 'AES-128-CBC', $key, OPENSSL_NO_PADDING, $iv);
          $encrypted = base64_encode($encrypted);
          $encrypted = str_replace(array('+','/','=') , array('-','_','') , $encrypted);
          return $encrypted;
    }

    private  function padString($source)
    {
          $paddingChar = ' ';
          $size = 16;
          $x = strlen($source) % $size;
          $padLength = $size - $x;
          for ($i = 0;$i < $padLength;$i++)
          {
              $source .= $paddingChar;
          }
          return $source;
    }
    private function decrypt($data, $key, $iv)
    {
        $data = str_replace(array('-','_') , array('+','/') , $data);
        $data = base64_decode($data);
        $decrypted = openssl_decrypt($data, 'AES-128-CBC', $key, OPENSSL_NO_PADDING, $iv);
        return utf8_encode(trim($decrypted));
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
    private function encry($str,$key='yesbingo'){
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
    private function decry($str,$key='yesbingo'){
        return think_decrypt($str,$key);
        if (!$key) {
            return $str;
        }
        $data = base64_decode($str);
        $data = openssl_decrypt($data, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        return $data;
    }
}