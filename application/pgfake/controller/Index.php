<?php

namespace app\pgfake\controller;

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
    public function createuser($params=[])
    {
        try {
            $roleid   = $params['roleid'];
            $gameid   = $params['gameid'];
            $language = $params['language'];
            $time     = $params['time'];
            $sign     = $params['sign'];
            save_log('pgfake', '==='.$roleid);

            if (strtoupper($language) == 'BR') {
                $language = 'pt';
            }
            $test_uidarr = config('test_uidarr') ?: [];
            if (strlen($roleid)==7 || in_array($roleid, $test_uidarr)) {
                $this->GAME_URL = config('pgfake_test.GAME_URL');
                $this->Operator_Token = config('pgfake_test.Operator_Token');
                $this->API_Host = config('pgfake_test.API_Host');
                $this->Secret_Key = config('pgfake_test.Secret_Key');
            }
            $header = [
                'Content-Type: application/json;charset=utf-8',
            ];
            //创建游戏账号
            $tokenn = Redis::get('fakepggame_token_'.$roleid);
            if (empty($tokenn)) {
                $post_params = [
                    'operator_token'=>$this->Operator_Token,
                    'user_id'=>config('platform_name').'_'.$roleid,
                    'user_name'=>config('platform_name').'_'.$roleid,
                    'ts'=>time(),
                ];
                $post_params['sign'] = $this->createSign($post_params,$this->Secret_Key);
                $header = [
                    'Content-Type: application/json;charset=utf-8',
                ];
                $data = $this->curl($this->API_Host.'api/web/user_session/',json_encode($post_params),$header);
                $data = json_decode($data,true);

                $userid = $data['data']['user_id'];
                $token  = $data['data']['token'];
                Redis::set('fakepggame_token_'.$roleid,json_encode(['token'=>$token,'user_id'=>$userid]));
            } else {
                $tokenn = json_decode($tokenn,true);
                $userid = $tokenn['user_id'];
                $token  = $tokenn['token'];
            }
            
            
            //设置玩家返奖率
            if (Redis::has('pgfake_rtp_data')) {
                $rtp = Redis::get('pgfake_rtp_data');
                if ($rtp >= 0) {
                    $post_params = [
                        'operator_token'=>$this->Operator_Token,
                        'user_id'=>config('platform_name').'_'.$roleid,
                        'rtp'=>(int)$rtp,
                        'ts'=>time()*1000,
                    ];
                    $post_params['sign'] = $this->createSign($post_params,$this->Secret_Key);
                    $header = [
                        'Content-Type: application/json;charset=utf-8',
                    ];

                    $data = $this->curl($this->API_Host.'api/web/set_rtp/',json_encode($post_params),$header);
                }
            }
            
            //生成游戏链接
            $post_params = [
                'operator_token'=>$this->Operator_Token,
                'user_id'=>$userid.'',
                'user_token'=>$token,
                'game_code'=>$gameid.'',
                'language'=>$language,
                'ts'=>time()*1000,
            ];
            $post_params['sign'] = $this->createSign($post_params,$this->Secret_Key);
            // $post_param['url'] = $this->API_Host.'api/web/game_url/';
            
            $data = $this->curl($this->API_Host.'api/web/game_url/',json_encode($post_params),$header);
            $data = json_decode($data,true);
            $gameURL = $data['url'] ?? '';
            return $this->succjson($gameURL);
        } catch (Exception $ex) {
            save_log('pgfake_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjson('api error');
        }
    }


    //获取玩家账号余额
    private function getSocketBalance($roleid)
    {
        $roleid = intval($roleid);
        $socket = new QuerySocket();
        $m = $socket->DSQueryRoleBalance($roleid);
        $gamemoney = $m['iGameWealth'] ?? 0;
        // $balance = bcdiv($gamemoney, bl, 3);
        return floor($gamemoney*100)/100;
    }


    private function createSign($data,$Md5key)
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
        $str = $md5str.'&key='.$Md5key;
        return strtolower(md5($str));
    }

    //查询余额
    public function getBalance(){
        $params = request()->param() ?: json_decode(file_get_contents('php://input'),1);
        try {
            $userId = explode('_',$params['UseID'])[1];
            $balance = $this->getSocketBalance($userId);
            return json([
                'data'=>[
                    'currency_code'=>$this->Currency,
                    'balance_amount'=>$balance,
                    'updated_time'=>time()*1000,
                ],
                'error'=>null
            ]);
        } catch (Exception $ex) {
            save_log('pgfake_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return json(['data'=>null,'error'=>1200]);
        }
    }

    //投付
    public function TransInOut(){
        $params = request()->param() ?: json_decode(file_get_contents('php://input'),1);
        try {
            if ($params['SecretStr'] != $this->Secret_Key) {
                return json(['data'=>null,'error'=>1036]);
            }
            if(Redis::get('pgfake_TransInOut_'.$params['Term'])){
                return json(['data'=>null,'error'=>1501]);
            } else {
                //10分钟过期
                Redis::set('pgfake_TransInOut_'.$params['Term'],1,600);
            }
            $userId = explode('_',$params['UseID'])[1];
            $user_id = (int)$userId;
            $balance  = $this->getSocketBalance($user_id);;
            
            if ($balance<$params['Bet']) {
                return json(['data'=>null,'error'=>3202]);
            }

            $socket = new QuerySocket();
            if ($params['Bet'] > 0) {
                $socket->downScore($user_id, $params['Bet'], 'bet_'.$params['Term'],47000);
            }
            if ($params['Award'] >= 0) {
                $socket->UpScore2($user_id, $params['Award'], 'award_'.$params['Term'],47000,$params['Bet']);
            }
            $socket->ClearLablel($user_id,47000);
            if (config('need_third_rank') == 1) {
                Redis::lpush('third_game_rank_list',json_encode([
                    'PlatformId'=>47000,
                    'PlatformName'=>'PGGAME',
                    'GameId'=>$params['GameID'],
                ]));
            }
            $balance  = $this->getSocketBalance($user_id);
            return json([
                'data'=>[
                    'currency_code'=>$this->Currency,
                    'balance_amount'=>$balance,
                    'updated_time'=>time()*1000,
                ],
                'error'=>null
            ]);
        } catch (Exception $ex) {
            save_log('pgfake_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return json(['data'=>null,'error'=>1200]);
        }     
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
}