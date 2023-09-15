<?php

namespace app\habagame\controller;

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
        save_log('habagame', '==='.request()->url().'===接口请求数据===' . json_encode(request()->param()));
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
                $this->config = config('habagame_test');
                // config('trans_url_other',config('test_trans_url'));
            }
            $language = $param['language']?:$this->config['language'];
            if (strtoupper($language) == 'BR') {
                $language = 'pt';
            }

            $gameid = $param['gameid'];

            $gameURL = $this->config['GAME_URL'].'brandid='.$this->config['Merchant_ID'].'&keyname='.$gameid.'&token='.$this->encry(config('platform_name').'_'.$roleid).'&Mode=real&Locale='.$language;

            return $this->succjson($gameURL);

        } catch (Exception $ex) {
            save_log('habagame_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjson('api error');
        }
    }

    public function auth(){
        try {
            $params = request()->param() ?: json_decode(file_get_contents('php://input'),1);
            $playerdetailrequest = $params['playerdetailrequest']??'';
            $auth = $params['auth']??'';
            if(empty($auth) || empty($playerdetailrequest)){
                $respons = [
                    "playerdetailresponse"=>[
                        "status"=>[
                            "success"=>false,
                            "message"=>"Invalid parameters",
                            "autherror"=>true
                        ]
                    ]
                ];
                save_log('habagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }
            if ($auth['brandid'] != $this->config['Merchant_ID'] || $auth['passkey'] != $this->config['Secret_Key']) {
                $respons = [
                    "playerdetailresponse"=>[
                        "status"=>[
                            "success"=>false,
                            "message"=>"Configuration mismatch",
                            "autherror"=>true
                        ]
                    ]
                ];
                save_log('habagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }
            $token = $playerdetailrequest['token'];
            $user_id = explode('_',$this->decry($token))[1];
            if (empty($user_id)) {
                $respons = [
                    "playerdetailresponse"=>[
                        "status"=>[
                            "success"=>false,
                            "message"=>"Token error",
                            "autherror"=>true
                        ]
                    ]
                ];
                save_log('habagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }

            $balance = $this->getSocketBalance($user_id);

            $user_id = config('platform_name').'_'.$user_id;

            $respons = [
                "playerdetailresponse"=>[
                    "status"=>[
                        "success"=>true,
                        "autherror"=>false,
                        "message"=>""
                    ],
                    "accountid"=>$user_id,
                    "accountname"=>$user_id,
                    "balance"=>$balance,
                    "currencycode"=>$this->config['Currency']
                ]
            ];
            save_log('habagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('habagame_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            $respons = [
                "playerdetailresponse"=>[
                    "status"=>[
                        "success"=>false,
                        "message"=>"Api error",
                        "autherror"=>true
                    ]
                ]
            ];
            return json($respons);
        }
    }

    public function tx(){
        try {
            $params                  = request()->param() ?: json_decode(file_get_contents('php://input'),1);
            // save_log('habagame', '==='.request()->url().'===接口请求数据===' . json_encode($params));

            if ($params['type'] == 'queryrequest') {
                $transaction_id = $params['queryrequest']['transferid'];
                if (Redis::get('habagame_is_exec_tx_'.$transaction_id)) {
                    $respons = [
                        "fundtransferresponse"=>[
                            "status"=>[
                                "success"=>true,
                            ]
                        ]
                    ];
                    save_log('habagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                    return json($respons);
                } else {
                    $respons = [
                        "fundtransferresponse"=>[
                            "status"=>[
                                "success"=>false,
                            ]
                        ]
                    ];
                    save_log('habagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                    return json($respons);
                }
            }

            $fundtransferrequest = $params['fundtransferrequest']??'';
            $auth                = $params['auth']??'';
            if(empty($auth) || empty($fundtransferrequest)){
                $respons = [
                    "fundtransferresponse"=>[
                        "status"=>[
                            "success"=>false,
                            "message"=>"Invalid parameters",
                            "successdebit"=>false,
                            "successcredit"=>false,
                        ]
                    ]
                ];
                save_log('habagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }
            if ($auth['brandid'] != $this->config['Merchant_ID'] || $auth['passkey'] != $this->config['Secret_Key']) {
                $respons = [
                    "fundtransferresponse"=>[
                        "status"=>[
                            "success"=>false,
                            "message"=>"Configuration mismatch",
                            "successdebit"=>false,
                            "successcredit"=>false,
                        ]
                    ]
                ];
                save_log('habagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }

            $token = $fundtransferrequest['accountid'];
            // $user_id = explode('_',$this->decry($token))[1] ?? 0;
            $user_id = explode('_',$token)[1] ?? 0;
            if (empty($user_id)) {
                $respons = [
                    "fundtransferresponse"=>[
                        "status"=>[
                            "success"=>false,
                            "autherror"=>true,
                            "successdebit"=>false,
                            "successcredit"=>false,
                        ]
                    ]
                ];
                save_log('habagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }
            // $transaction_id = $fundtransferrequest['gameinstanceid'];


            $game_id        = $fundtransferrequest['gamedetails']['brandgameid'];
            $is_end_round   = false;
            $balance = $this->getSocketBalance($user_id);
            if($fundtransferrequest['isrefund']){
                $amount = $fundtransferrequest['funds']['refund']['amount'] ?? 0;
                $transaction_id = $fundtransferrequest['funds']['refund']['transferid'];
                $initialdebittransferid = $fundtransferrequest['funds']['refund']['originaltransferid'];
                if(!Redis::get('habagame_is_exec_tx_'.$initialdebittransferid)){
                    $respons = [
                        "fundtransferresponse"=>[
                            "status"=>[
                                "success"=>false,
                                "refundstatus"=>2
                            ],
                            "balance"=>$balance,
                            "currencycode"=>$this->config['Currency']
                        ]
                    ];
                    save_log('habagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                    return json($respons);
                }
                if ($amount >= 0) {
                    $win_amount = abs($amount);
                    $bet_amount = 0;
                } else {
                    $bet_amount = abs($amount);
                    $win_amount = 0;
                }
                $gamestatemode = $fundtransferrequest['funds']['refund']['gamestatemode'];
                if ($gamestatemode == 2 || $gamestatemode == 3) {
                    $is_end_round   = true;
                }
            } else {
                if ($fundtransferrequest['funds']['debitandcredit']) {
                    $bet_amount = abs($fundtransferrequest['funds']['fundinfo'][0]['amount']) ?? 0;
                    $transaction_id = $fundtransferrequest['funds']['fundinfo'][0]['transferid'];

                    $win_amount  = $fundtransferrequest['funds']['fundinfo'][1]['amount'] ?? 0;
                    $transaction_id2 = $fundtransferrequest['funds']['fundinfo'][1]['transferid'];

                    $gamestatemode = $fundtransferrequest['funds']['fundinfo'][0]['gamestatemode'];
                    if ($gamestatemode == 2 || $gamestatemode == 3) {
                        $is_end_round   = true;
                    }
                    $gamestatemode = $fundtransferrequest['funds']['fundinfo'][1]['gamestatemode'];
                    if ($gamestatemode == 2 || $gamestatemode == 3) {
                        $is_end_round   = true;
                    }

                } else {
                    $amount = $fundtransferrequest['funds']['fundinfo'][0]['amount'] ?? 0;
                    $transaction_id = $fundtransferrequest['funds']['fundinfo'][0]['transferid'];
                    if ($amount >= 0) {
                        $win_amount = abs($amount);
                        $bet_amount = 0;
                    } else {
                        $bet_amount = abs($amount);
                        $win_amount = 0;
                    }
                    $gamestatemode = $fundtransferrequest['funds']['fundinfo'][0]['gamestatemode'];
                    if ($gamestatemode == 2 || $gamestatemode == 3) {
                        $is_end_round   = true;
                    }
                }
            }

            if (Redis::get('habagame_is_exec_tx_'.$transaction_id)) {
                if($fundtransferrequest['isrecredit']){
                    $respons = [
                        "fundtransferresponse"=>[
                            "status"=>[
                                "success"=>true,
                            ],
                            "balance"=>$balance,
                            "currencycode"=>$this->config['Currency']
                        ]
                    ];
                } else {
                    $respons = [
                        "fundtransferresponse"=>[
                            "status"=>[
                                "success"=>false,
                                "message"=>"Request duplicate",
                                "successdebit"=>false,
                                "successcredit"=>false,
                            ]
                        ]
                    ];
                }

                save_log('habagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }
            if(isset($transaction_id2)){
                if (Redis::get('habagame_is_exec_tx_'.$transaction_id2)) {
                    $respons = [
                        "fundtransferresponse"=>[
                            "status"=>[
                                "success"=>false,
                                "message"=>"Request duplicate",
                                "successdebit"=>false,
                                "successcredit"=>false,
                            ]
                        ]
                    ];
                    save_log('habagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                    return json($respons);
                }
            }


            if ($balance < $bet_amount) {
                $respons = [
                    "fundtransferresponse"=>[
                        "status"=>[
                            "success"=>false,
                            "nofunds"=>true,
                            "successdebit"=>false,
                            "successcredit"=>false,
                        ],
                        "balance"=>$balance,
                        "currencycode"=>$this->config['Currency']
                    ]
                ];
                save_log('habagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                return json($respons);
            }
            $socket = new QuerySocket();
            if ($bet_amount > 0) {
                $gamemoney = bcmul($bet_amount,bl,0);
                $state = $socket->downScore($user_id, $gamemoney, $transaction_id,40000);
                if ($state['iResult']!=0) {
                    $respons = [
                        "fundtransferresponse"=>[
                            "status"=>[
                                "success"=>false,
                                "successdebit"=>false,
                                "successcredit"=>false
                            ]
                        ]
                    ];
                    save_log('habagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
                    return json($respons);
                }
            }

            if ($win_amount >= 0) {
                $gamemoney  = bcmul($win_amount,bl,0);
                $gamemoney2 = bcmul($bet_amount,bl,0);
                $state = $socket->UpScore2($user_id, $gamemoney, $transaction_id,40000,$gamemoney2);
            }

            $clear_data = Redis::get('habagame_game_id_'.$user_id) ?: [];
            $clear_data[$game_id] = 1;
            if ($is_end_round) {
                unset($clear_data[$game_id]);
                if (empty($clear_data)) {
                    $state = $socket->ClearLablel($user_id,40000);
                    Redis::rm('habagame_game_id_'.$user_id);
                } else {
                    Redis::set('habagame_game_id_'.$user_id,$clear_data);
                }
            } else {
                Redis::set('habagame_game_id_'.$user_id,$clear_data);
            }

            if (config('need_third_rank') == 1) {
                Redis::lpush('third_game_rank_list',json_encode([
                    'PlatformId'=>40000,
                    'PlatformName'=>'HABA',
                    'GameId'=>$game_id,
                ]));
            }

            Redis::set('habagame_is_exec_tx_'.$transaction_id,1,3600);
            if(isset($transaction_id2)){
                Redis::set('habagame_is_exec_tx_'.$transaction_id2,1,3600);
            }
            $balance = $this->getSocketBalance($user_id);
            $respons = [
                "fundtransferresponse"=>[
                    "status"=>[
                        "success"=>true,
                        "successdebit"=>true,
                        "successcredit"=>true,
                    ],
                    "balance"=>$balance,
                    "currencycode"=>$this->config['Currency']
                ]
            ];
            if($fundtransferrequest['isrefund']){
                $respons['fundtransferresponse']['status']['refundstatus'] = 1;
            }
            save_log('habagame', '==='.request()->url().'===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('habagame_error', '==='.$ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            $respons = [
                "fundtransferresponse"=>[
                    "status"=>[
                        "success"=>false,
                        "message"=>"Api error",
                        "successdebit"=>false,
                        "successcredit"=>false,
                    ]
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