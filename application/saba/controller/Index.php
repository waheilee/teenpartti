<?php

namespace app\saba\controller;

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
        save_log('sabagame', '==='.request()->url().'===接口请求数据===' . json_encode(request()->param()));
    }


    //游戏对外接口创建玩家
    public function createuser()
    {
        try {
            $param = jsonRequest(['roleid', 'gameid', 'language','session_id', 'ip','time', 'sign']);
            $clientkey = config('clientkey');
            $key = md5($param['roleid'] . $param['gameid'] . $param['language'] . $param['time'] . $clientkey);
            if(empty($param['roleid']) || empty($param['gameid']) ||empty($param['time']) ||empty($param['sign'])){
                // return $this->failjson('Missing parameter');
            }
            if ($key != strtolower($param['sign'])) {
                // return $this->failjson('sign is error');
            }

            $roleid = $param['roleid']?:6499690;
            $test_uidarr = config('test_uidarr') ?: [];
            if ((strlen($roleid)==7) || in_array($roleid, $test_uidarr)) {
                 $this->config = config('sabagame_test');
                 config('trans_url_other',config('test_trans_url'));
            }
            $language = $param['language']?:$this->config['language'];
            if (strtoupper($language) == 'BR') {
                $language = 'pt';
            }
            $gameid = $param['gameid']?:2;
            $gameURL = $this->config['GAME_URL'];
            //创建玩家
            $params = [
                'vendor_id'=>$this->config['Merchant_ID'],
                'vendor_member_id'=>config('platform_name').'_'.$roleid,
                'operatorId'=>'ZhiFeng476',
                'username'=>config('platform_name').'_'.$roleid,
                'oddstype'=>"3",
                'currency'=>$this->config['Currency'],
                'maxtransfer'=>9999999,
                'mintransfer'=>0
            ];
            if (config('is_sabagame_trans') == 1 || true) {
                $params['url'] = $gameURL.'CreateMember';
                $res = $this->curl(config('trans_url_other').'/saba/index/createuser',$params);
            } else {
                $res = [];
            }
            $res = json_decode($res,true);
            if ($res['error_code'] != 0 && $res['error_code'] != 6) {
                return $this->failjson($res['message']);
            }
            
            $params = [
                'vendor_id'=>$this->config['Merchant_ID'],
                'platform'=>"2",
                'vendor_member_id'=>config('platform_name').'_'.$roleid,
            ];
            
            if (config('is_sabagame_trans') == 1 || true) {
                $params['url'] = $gameURL.'GetSabaUrl';
                $res = $this->curl(config('trans_url_other').'/saba/index/createuser',$params);
            } else {
                $res = [];
            }
            $res = json_decode($res,true);
            if ($res['error_code'] == 0) {
                $gameURL = $res['Data']."&lang=".$language."&sportid=".$gameid."&WebSkinType=3&skin=0&menutype=0";
            } else {
                $gameURL = '';
            }
            return $this->succjson($gameURL);

        } catch (Exception $ex) {
            save_log('sabagame_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjson('api error');
        }
    }

    public function getbalance(){
        $params = request()->param() ?: json_decode(file_get_contents('php://input'),1);
        $params = $params['message'];
        $userId  = $params['userId'];
        $user_id =  explode('_', $params['userId']);
        $user_id = $AccountID = $user_id[1]??0;
        $balance = $this->getSocketBalance($user_id);
        $respons = [
            "status"=>"0",
            "userId"=>$userId,
            'balance'=>$balance,
            'alanceTs'=>date(DATE_ISO8601),
        ];
        save_log('sabagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
        return json($respons);exit();
    }

    public function placebet(){
        try {
            $params = request()->param() ?: json_decode(file_get_contents('php://input'),1);
            $params = $params['message'];
        
            $userId  = $params['userId'];
            
            $user_id = $AccountID =  explode('_', $params['userId']);
            $user_id = $user_id[1]??0;
            $transaction_id = $params['operationId'];
            $bet_amount     = $params['debitAmount'];
            $game_id        = $params['sportType'];
            $round_id       = $params['refId'];
            if (Redis::get('sabagame_is_exec_bet_'.$round_id)) {
                $respons = [
                    "status"=>"1",
                    "msg"=>'Bet record duplicate'
                ];
                save_log('sabagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }


            $balance = $this->getSocketBalance($user_id);
 
            if ($balance < $bet_amount) {
                $respons = [
                    "status"=>"502",
                    "msg"=>"Not enough balance"
                ];
                save_log('sabagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }
            $socket = new QuerySocket();
            $bet_amount = bcmul($bet_amount,bl,0);
            if ($bet_amount > 0) {
                $state = $socket->downScore($user_id, $bet_amount, $round_id,43000);
                if ($state['iResult']!=0) {
                    $respons = [
                        "status"=>"999",
                        "msg"=>"Other error "
                    ];
                    save_log('sabagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                    return json($respons);
                }
            }

            
            if (config('need_third_rank') == 1) {
                Redis::lpush('third_game_rank_list',json_encode([
                    'PlatformId'=>43000,
                    'PlatformName'=>'SABAGAME',
                    'GameId'=>$game_id,
                ]));
            }
            //结算删除
            Redis::set('sabagame_is_exec_bet_'.$round_id,$bet_amount);
            
            //加入打码
            $clear_data = Redis::get('sabagame_game_id_'.$user_id) ?: [];
            $clear_data[$round_id] = 1;
            Redis::set('sabagame_game_id_'.$user_id,$clear_data);
            
            $respons = [
                "status"   =>"0",
                "refId"   => $params['refId'],
                "licenseeTxId"    =>$params['refId'],
            ];
            save_log('sabagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('sabagame_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            $respons = [
                "status"=>"999",
                "msg"=>"Other error "
            ];
            return json($respons);
        }
    }

    public function placebetparlay(){
        try {
            $params = request()->param() ?: json_decode(file_get_contents('php://input'),1);
            $params = $params['message'];

            $userId  = $params['userId'];
            $user_id =  explode('_', $params['userId']);
            $user_id = $AccountID = $user_id[1]??0;
            $transaction_id = $params['operationId'];
            $totalBetAmount = $params['totalBetAmount'];
            $txns    = $params['txns'];
            
            if (Redis::get('sabagame_is_exec_bet_'.$transaction_id)) {
                $respons = [
                    "status"=>"1",
                    "msg"=>'Bet record duplicate'
                ];
                save_log('sabagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            } else {
                Redis::set('sabagame_is_exec_bet_'.$transaction_id,1,3600);
            }

            $balance = $this->getSocketBalance($user_id);
 
            if ($balance < $totalBetAmount) {
                $respons = [
                    "status"=>"502",
                    "msg"=>"Not enough balance"
                ];
                save_log('sabagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }
            $refIds = [];
            $socket = new QuerySocket();
            foreach ($txns as $key => &$val) {
                $bet_amount = $val['debitAmount'];
                $bet_amount = bcmul($bet_amount,bl,0);
                if($bet_amount > 0){
                    $state = $socket->downScore($user_id, $bet_amount, $val['refId'],43000);
                    if ($state['iResult']!=0) {
                        $respons = [
                            "status"=>"999",
                            "msg"=>"Other error "
                        ];
                        save_log('sabagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                        return json($respons);
                    }
                } 
            }
            foreach ($txns as $key => &$val) {
                //加入打码
                $clear_data = Redis::get('sabagame_game_id_'.$user_id) ?: [];
                $clear_data[$val['refId']] = 1;
                Redis::set('sabagame_game_id_'.$user_id,$clear_data);
                
                $refIds[] = [
                    "refId"=>$val['refId'],
                     "licenseeTxId"=>$val['refId'],
                ];
            }
            $respons = [
                "status"   =>"0",
                "txns"   => $refIds,
            ];
            save_log('sabagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('sabagame_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            $respons = [
                "status"=>"999",
                "msg"=>"Other error "
            ];
            return json($respons);
        }
    }
    //确认下注
    public function confirmbet(){
        try {
            $params = request()->param() ?: json_decode(file_get_contents('php://input'),1);
            $params = $params['message'];
            
            $userId  = $params['userId'];
            $user_id =  explode('_', $params['userId']);
            $user_id = $AccountID = $user_id[1]??0;

            $balance = $this->getSocketBalance($user_id);
            $respons = [
                "status"   =>"0",
                "balance"  =>$balance,
            ];
            save_log('sabagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);

        } catch (Exception $ex) {
            save_log('sabagame_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            $respons = [
                "status"=>"999",
                "msg"=>"Other error "
            ];
            return json($respons);
        }
    }

    //确认下注
    public function confirmbetparlay(){
        try {
            $params = request()->param() ?: json_decode(file_get_contents('php://input'),1);
            $params = $params['message'];
            
            $userId  = $params['userId'];
            $user_id = $AccountID =  explode('_', $params['userId'])[1] ?: 0;

            $balance = $this->getSocketBalance($user_id);
            $respons = [
                "status"   =>"0",
                "balance"  =>$balance,
            ];
            save_log('sabagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);

        } catch (Exception $ex) {
            save_log('sabagame_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            $respons = [
                "status"=>"999",
                "msg"=>"Other error "
            ];
            return json($respons);
        }
    }
     //取消下注
    public function cancelbet(){
        try {
            $params = request()->param() ?: json_decode(file_get_contents('php://input'),1);
            $params = $params['message'];
            
            $userId  = $params['userId'];
            $user_id =  explode('_', $params['userId']);
            $user_id = $AccountID = $user_id[1]??0;
            $transaction_id = $params['operationId'];
            $txns    = $params['txns'];
            
            if (Redis::get('sabagame_is_exec_cancelbet_'.$transaction_id)) {
                $respons = [
                    "status"=>"1",
                    "msg"=>'record duplicate'
                ];
                save_log('sabagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            } else {
                Redis::set('sabagame_is_exec_cancelbet_'.$transaction_id,1,3600);
            }
            
            $socket = new QuerySocket();
            foreach ($txns as $key => &$val) {
                $bet_amount = Redis::get('sabagame_is_exec_bet_'.$val['refId']) ?: 0;
                if($bet_amount > 0){
                    $state = $socket->UpScore2($user_id, $bet_amount, 'cacel_'.$val['refId'],43000,0);
                }
                $this->clearData($user_id,$val['refId']);
            }
            
            $balance = $this->getSocketBalance($user_id);
            $respons = [
                "status"   =>"0",
                "balance"  =>$balance,
            ];
            save_log('sabagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);

        } catch (Exception $ex) {
            save_log('sabagame_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            $respons = [
                "status"=>"999",
                "msg"=>"Other error "
            ];
            return json($respons);
        }
    }

    //结算加钱
    public function settle(){
        try {
            $params = request()->param() ?: json_decode(file_get_contents('php://input'),1);
            $params = $params['message'];
            
            $userId  = $params['userId'];
            $user_id =  explode('_', $params['userId']);
            $user_id = $AccountID = $user_id[1]??0;
            $transaction_id = $params['operationId'];
            $txns    = $params['txns'];

            if (Redis::get('sabagame_is_exec_settle_'.$user_id.$transaction_id)) {
                $respons = [
                    "status"=>"1",
                    "msg"=>'Bet record duplicate'
                ];
                save_log('sabagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            } else {
                Redis::set('sabagame_is_exec_settle_'.$user_id.$transaction_id,1,3600);
            }
            $user_id = intval($user_id);
            $socket = new QuerySocket();
            foreach ($txns as $key => &$val) {
                $win_amount = $val['creditAmount'];
                if ($val['extraStatus'] == 'cashout' || $val['extraStatus'] == 'void ticket' || $val['extraStatus'] == 'refund match') {
                    //实时兑现不加打码
                    $bet_amount = 0;
                } else {
                    $bet_amount = Redis::get('sabagame_is_exec_bet_'.$val['refId']) ?: 0;
                }
                $win_amount = bcmul($win_amount,bl,0);
                $state = $socket->UpScore2($user_id, $win_amount, 'settle_'.$val['refId'],43000,$bet_amount);
                Redis::rm('sabagame_is_exec_bet_'.$val['refId']);
                $this->clearData($user_id,$val['refId']);
            }

            $respons = [
                "status"=>"0",
            ];
            save_log('sabagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('sabagame_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            $respons = [
                "status"=>"999",
                "msg"=>"Other error "
            ];
            return json($respons);
        }
    }

    //重新结算
    public function resettle(){
        return $this->settle();
    }

     //取消结算
    public function unsettle(){
      try {
            $params = request()->param() ?: json_decode(file_get_contents('php://input'),1);
            $params = $params['message'];
            
            $userId  = $params['userId'];
            $user_id =  explode('_', $params['userId']);
            $user_id = $AccountID = $user_id[1]??0;
            $transaction_id = $params['operationId'];
            $txns    = $params['txns'];
            
            if (Redis::get('sabagame_is_exec_unsettle_'.$transaction_id)) {
                $respons = [
                    "status"=>"1",
                    "msg"=>'record duplicate'
                ];
                save_log('sabagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            } else {
                Redis::set('sabagame_is_exec_unsettle_'.$transaction_id,1,3600);
            }
            
            $socket = new QuerySocket();
            $bet_amount = 0;
            foreach ($txns as $key => &$val) {
                $bet_amount  += $val['debitAmount'];
            }
            
            $balance = $this->getSocketBalance($user_id);
            if ($balance < $bet_amount) {
                $respons = [
                    "status"=>"502",
                    "msg"=>"Not enough balance"
                ];
                save_log('sabagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }
            foreach ($txns as $key => &$val) {
                $bet_amount = $val['debitAmount'];
                $bet_amount = bcmul($bet_amount,bl,0);
                if($bet_amount > 0){
                    $state = $socket->downScore($user_id, $bet_amount, 'unsettle'.$val['refId'],43000);
                    if ($state['iResult']!=0) {
                        $respons = [
                            "status"=>"999",
                            "msg"=>"Other error "
                        ];
                        save_log('sabagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                        return json($respons);
                    }
                }
                
            }
            foreach ($txns as $key => &$val) {
                //加入打码
                $clear_data = Redis::get('sabagame_game_id_'.$user_id) ?: [];
                $clear_data[$val['refId']] = 1;
                Redis::set('sabagame_game_id_'.$user_id,$clear_data);
            }
            $respons = [
                "status"   =>"0",
            ];
            save_log('sabagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);

        } catch (Exception $ex) {
            save_log('sabagame_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            $respons = [
                "status"=>"999",
                "msg"=>"Other error "
            ];
            return json($respons);
        }
    }

    //投付
    public function adjustbalance(){
        try {
            $params = request()->param() ?: json_decode(file_get_contents('php://input'),1);
            $params = $params['message'];
            
            $userId  = $params['userId'];
            $user_id =  explode('_', $params['userId']);
            $user_id = $AccountID = $user_id[1]??0;
            $transaction_id = $params['operationId'];
            $txns    = $params['balanceInfo'];

            if (Redis::get('sabagame_is_exec_adjustbalance_'.$transaction_id)) {
                $respons = [
                    "status"=>"1",
                    "msg"=>'record duplicate'
                ];
                save_log('sabagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            } else {
                Redis::set('sabagame_is_exec_adjustbalance_'.$transaction_id,1,3600);
            }
            $socket = new QuerySocket();
            $creditAmount = $txns['creditAmount'];
            $debitAmount = $txns['debitAmount'];
            if ($debitAmount > 0) {
                $balance = $this->getSocketBalance($user_id);
                if ($balance < $debitAmount) {
                    $respons = [
                        "status"=>"502",
                        "msg"=>"Not enough balance"
                    ];
                    save_log('sabagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                    return json($respons);
                }
                
                $gamemoney = bcmul(abs($debitAmount),bl,0);
                $state = $socket->downScore($user_id, $gamemoney, $transaction_id.'_debit',43000);
            }
            if ($creditAmount > 0) {
                $gamemoney = bcmul($creditAmount,bl,0);
                $state = $socket->UpScore2($user_id, $gamemoney, $transaction_id.'credit',43000,0);
            }
            $respons = [
                "status"   =>"0",
            ];
            $this->clearData($user_id,$params['refId']);
            save_log('sabagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);

        } catch (Exception $ex) {
            save_log('sabagame_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            $respons = [
                "status"=>"999",
                "msg"=>"Other error "
            ];
            return json($respons);
        }
    }
    
    //清除打码
    private function clearData($user_id,$round_id='')
    {
        $clear_data = Redis::get('sabagame_game_id_'.$user_id) ?: [];
        $clear_data[$round_id] = 1;
        unset($clear_data[$round_id]);
        if (empty($clear_data)) {
            $socket    = new QuerySocket();
            $state = $socket->ClearLablel($user_id,43000);
            Redis::rm('sabagame_game_id_'.$user_id);
        } else {
            Redis::set('sabagame_game_id_'.$user_id,$clear_data);
        }
        return true;
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
    
    //额外接口
    public function getreachlimittrans(){
        $this->config = config('sabagame_test');
        $params = [
            'vendor_id'=>$this->config['Merchant_ID'],
            'start_Time'=>date('Y/m/d',strtotime('-20 days')),
        ];
        $gameURL = $this->config['GAME_URL'];
        $params['url'] = $gameURL.'getreachlimittrans';
        $res = $this->curl(config('trans_url_other').'/saba/index/createuser',$params);
        $res = json_decode($res,true);
        var_dump($res);die();
        if($res['error_code'] == 0){
            $txns = $res['Data']['Txns'];
            foreach ($txns as $key => &$val) {
                $transHistory = $val['transHistory'];
                foreach ($transHistory as $k => &$v) {
                    if(($v['status']==1 || $v['status']==2) && $v['inRetry'] == false){
                        $params_2 = [
                            'vendor_id'=>$this->config['Merchant_ID'],
                            'operationId'=>$v['operationId'],
                        ];
                        $params_2['url'] = $gameURL.'retryOperation';
                        $res = $this->curl(config('trans_url_other').'/saba/index/createuser',$params_2);
                    }
                }
            }
        }
        echo 'success';
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
        save_log('habagame', '==='.request()->url().'===三方返回数据===' . $data);
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
    private function encry($str,$key='habagame'){
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
    private function decry($str,$key='habagame'){
        return think_decrypt($str,$key);
        if (!$key) {
            return $str;
        }
        $data = base64_decode($str);
        $data = openssl_decrypt($data, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        return $data;
    }
}