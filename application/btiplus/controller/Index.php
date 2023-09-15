<?php

namespace app\btiplus\controller;


use app\model\AccountDB;
use socket\QuerySocket;
use think\Exception;
use redis\Redis;
use think\Request;

class Index extends Base
{

    protected $socket;
    protected $reserveRedisKey;
    protected $reserveAmountBetRedisKey;
    protected $debitTotalRedisKey;
    protected $reqIdRedisKey;


    public function __construct()
    {
        $this->socket = new QuerySocket();
        $this->reserveRedisKey = 'RESERVE_BET_';//投注扣款
        $this->reserveAmountBetRedisKey = 'RESERVE_AMOUNT_BET_';//投注金额
        $this->debitTotalRedisKey = 'DEBIT_TOTAL_FOR_RESERVE_ID_';//单注总额
        $this->reqIdRedisKey = 'ACCOUNT_NUMBER_OF_BETS_FOR_REQ_ID_';//单注请求标识
        parent::__construct();

        header('Access-Control-Allow-Origin:*');
//允许的请求头信息
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");
//允许的请求类型
        header('Access-Control-Allow-Methods: GET, POST, PUT,DELETE,OPTIONS,PATCH');
//允许携带证书式访问（携带cookie）
        header('Access-Control-Allow-Credentials:true');
        session_start();
    }


    //游戏对外接口创建玩家
    public function createuser()
    {
        try {
            $param = jsonRequest(['roleid', 'gameid', 'language', 'session_id', 'ip', 'time', 'sign']);
            save_log('pplay', '===' . request()->url() . '===接口请求数据===' . json_encode($param));
            $clientkey = config('clientkey');
            $key = md5($param['roleid'] . $param['gameid'] . $param['language'] . $param['time'] . $clientkey);
            if (empty($param['roleid']) || empty($param['gameid']) || empty($param['time']) || empty($param['sign'])) {
                return $this->failjson('Missing parameter');
            }
            if ($key != strtolower($param['sign'])) {
                return $this->failjson('sign is error');
            }
            $roleid = $param['roleid'];

            $gameid = $param['gameid'] ?: 1;
            switch ($gameid) {
                case '1':
                    $table_id = "vs20porbs";
                    break;
                default:
                    $table_id = $gameid;
                    break;
            }
            if (strtoupper($param['language']) == 'BR') {
                $param['language'] = 'pt';
            }
            $test_uidarr = config('test_uidarr') ?: [];
            if (($roleid >= 8888100 && $roleid <= 8888200) || in_array($roleid, $test_uidarr)) {
                $this->Merchant_ID = config('pplay_test.Merchant_ID');
                $this->API_Token = config('pplay_test.API_Token');
                $this->Currency = config('pplay_test.Currency');
                $this->url = config('pplay_test.API_Host');
                config('trans_url_other', config('test_trans_url'));
            }
            $token = $this->encry($roleid);
            $post_param = [
                'secureLogin' => $this->Merchant_ID,
                'symbol' => $table_id,
                'language' => $param['language'],
                'token' => $token,
                'currency' => $this->Currency,
                'technology' => 'H5',
                'lobbyUrl' => config('domain_url')
            ];

            $header = [
                'content-type:application/x-www-form-urlencoded'
            ];
            if (config('is_pplay_trans') == 2) {
                $post_param['token'] = $this->encry(config('platform_name') . '_' . $roleid);
                $post_param['hash'] = $this->createsign($post_param, $this->API_Token);
                $result = $this->curl($this->url . '/game/url/', $post_param, $header);

            } else {
                $post_param['platform'] = config('platform_name');
                $post_param['url'] = $this->url . '/game/url/';
                $post_param['AccountID'] = $roleid;
                $post_param['key'] = $this->API_Token;
                $result = $this->curl(config('trans_url_other') . '/pplay/index/createuser', $post_param);
            }
            save_log('pplay', '===第三方玩家创建===' . json_encode($result));
            $result = json_decode($result, 1);
            if ($result['error'] == 0) {
                return $this->succjson($result['gameURL']);
            } else {
                return $this->failjson('create player faild');
            }

        } catch (Exception $ex) {
            save_log('pplay_error', '===' . $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjson('api error');
        }
    }

    public function Authenticate()
    {
        try {
            $params = request()->param() ?: json_decode(file_get_contents('php://input'), 1);
            save_log('pplay', '===' . request()->url() . '===接口请求数据===' . json_encode($params));
            $hash = $params['hash'] ?? '';
            $token = $params['token'] ?? '';
            $providerId = $params['providerId'] ?? '';
            if (empty($hash) || empty($token) || empty($providerId)) {
                return $this->failjsonpp('parameters error');
            }
            unset($params['hash']);
            $check_hash = $this->createsign($params, $this->API_Token);
            if ($check_hash != $hash) {
                return $this->failjsonpp('hash error');
            }
            $userId = explode('_', $this->decry($token))[1];
            if (empty($userId)) {
                return $this->failjsonpp('token error');
            }

            $balance = $this->getBalance($userId);
            if (config('is_pplay_trans') == 2) {
                $userId = config('platform_name') . '_' . $userId;
            }
            $respons = [
                "userId" => $userId,
                'currency' => $this->Currency,
                "cash" => round($balance, 2),
                "bonus" => 0,
                "error" => 0,
                "description" => "Success"
            ];
            save_log('pplay', '===' . request()->url() . '===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('pplay_error', '===' . $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjsonpp('api error');
        }
    }

    //查询余额
    public function Balance()
    {
        try {
            $params = request()->param() ?: json_decode(file_get_contents('php://input'), 1);
            save_log('pplay', '===' . request()->url() . '===接口请求数据===' . json_encode($params));

            $hash = $params['hash'] ?? '';
            $user_id = $params['userId'] ?? '';
            $providerId = $params['providerId'] ?? '';

            if (empty($hash) || empty($user_id) || empty($providerId)) {
                return $this->failjsonpp('parameters error');
            }
            $user_id = explode('_', $user_id)[1];
            unset($params['hash']);
            $check_hash = $this->createsign($params, $this->API_Token);
            if ($check_hash != $hash) {
                return $this->failjsonpp('hash error');
            }
            $balance = $this->getBalance($user_id);
            $respons = [
                "currency" => $this->Currency,
                "cash" => round($balance, 2),
                "bonus" => 0,
                "error" => 0,
                "description" => "Success"
            ];
            save_log('pplay', '===' . request()->url() . '===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('pplay_error', '===' . $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjsonpp('api error');
        }
    }


    //下注扣钱
    public function bet()
    {
        try {
            $params = request()->param() ?: json_decode(file_get_contents('php://input'), 1);
            save_log('pplay', '===' . request()->url() . '===接口请求数据===' . json_encode($params));
            $hash = $params['hash'] ?? '';
            $user_id = $params['userId'] ?? '';
            $gameId = $params['gameId'] ?? '';
            $roundId = $params['roundId'] ?? '';
            $amount = $params['amount'] ?? '';
            $reference = $params['reference'] ?? '';
            $providerId = $params['providerId'] ?? '';
            if (empty($hash) || empty($user_id) || empty($reference)) {
                return $this->failjsonpp('parameters error');
            }
            $user_id = explode('_', $user_id)[1];
            unset($params['hash']);
            $check_hash = $this->createsign($params, $this->API_Token);
            if ($check_hash != $hash) {
                return $this->failjsonpp('hash error');
            }
            $socket = new QuerySocket();
            $user_id = intval($user_id);

            $clear_data = Redis::get('pplay_game_id_' . $user_id) ?: [];
            $clear_data[$gameId] = 1;
            Redis::set('pplay_game_id_' . $user_id, $clear_data);

            $balance = $this->getBalance($user_id);
            if ($balance < $amount) {
                $respons = [
                    "transactionId" => substr($user_id . '_' . $reference, 0, 30),
                    "currency" => $this->Currency,
                    "cash" => round($balance, 2),
                    "bonus" => 0,
                    "usedPromo" => 0,
                    "error" => 100,
                    "description" => "fail"
                ];
                save_log('pplay', '===' . request()->url() . '===响应成功数据===' . json_encode($respons));
                return json($respons);
                exit();
            }

            if (Redis::get('pplay_is_exec_bet_' . $user_id . $reference)) {
                $respons = [
                    "transactionId" => substr($user_id . '_' . $reference, 0, 30),
                    "currency" => $this->Currency,
                    "cash" => round($balance, 2),
                    "bonus" => 0,
                    "usedPromo" => 0,
                    "error" => 100,
                    "description" => "fail"
                ];
                save_log('pplay', '===' . request()->url() . '===响应成功数据===' . json_encode($respons));
                return json($respons);
                exit();
            }

            $gamemoney = bcmul($amount, bl, 0);


            // save_log('pplay', '===下注扣钱' . $user_id . $gamemoney .$reference);
            $state = $socket->downScore($user_id, $gamemoney, $reference, 38000);
            if ($state['iResult'] != 0) {
                if ($state['iResult'] == 2) {
                    $code = 1;
                } else {
                    $code = 100;
                }
                $respons = [
                    "transactionId" => substr($user_id . '_' . $reference, 0, 30),
                    "currency" => $this->Currency,
                    "cash" => round($balance, 2),
                    "bonus" => 0,
                    "usedPromo" => 0,
                    "error" => $code,
                    "description" => "fail"
                ];
                save_log('pplay', '===' . request()->url() . '===响应成功数据===' . json_encode($respons));
                return json($respons);
            }
            //记录订单是否执行，防止重复
            Redis::set('pplay_is_exec_bet_' . $user_id . $reference, 1, 3600);
            //记录下注数量
            Redis::set('pplay_amount_bet_' . $gameId . '_' . $user_id, $gamemoney, 3600);

            // save_log('pplay', '===下注扣钱' . $user_id . '===状态：' . json_encode($state));

            $left_balance = $this->getBalance($user_id);
            $respons = [
                "transactionId" => substr($user_id . '_' . $reference, 0, 30),
                "currency" => $this->Currency,
                "cash" => round($left_balance, 2),
                "bonus" => 0,
                "usedPromo" => 0,
                "error" => 0,
                "description" => "Success"
            ];
            save_log('pplay', '===' . request()->url() . '===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('pplay_error', '===' . $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjsonpp('api error');
        }
    }


    //结算加钱
    public function result()
    {
        try {
            $params = request()->param() ?: json_decode(file_get_contents('php://input'), 1);
            save_log('pplay', '===' . request()->url() . '===接口请求数据===' . json_encode($params));
            $hash = $params['hash'] ?? '';
            $user_id = $params['userId'] ?? '';
            $gameId = $params['gameId'] ?? '';
            $roundId = $params['roundId'] ?? '';
            $amount = $params['amount'] ?? '';
            $reference = $params['reference'] ?? '';
            $providerId = $params['providerId'] ?? '';
            $promoWinAmount = $params['promoWinAmount'] ?? 0;
            if (empty($hash) || empty($user_id) || empty($reference)) {
                return $this->failjsonpp('parameters error');
            }
            $user_id = explode('_', $user_id)[1];
            unset($params['hash']);
            $check_hash = $this->createsign($params, $this->API_Token);
            if ($check_hash != $hash) {
                return $this->failjsonpp('hash error');
            }

            if (Redis::get('pplay_is_exec_result_' . $user_id . $reference)) {
                $balance = $this->getBalance($user_id);
                $respons = [
                    "transactionId" => substr($user_id . '_' . $reference, 0, 30),
                    "currency" => $this->Currency,
                    "cash" => round($balance, 2),
                    "bonus" => 0,
                    // "usedPromo"     =>0,
                    "error" => 100,
                    "description" => "fail"
                ];
                save_log('pplay', '===' . request()->url() . '===响应成功数据===' . json_encode($respons));
                return json($respons);
                exit();
            }

            if ($amount >= 0 || $promoWinAmount > 0) {
                $socket = new QuerySocket();
                $gamemoney = bcmul($amount + $promoWinAmount, bl, 0);
                $user_id = intval($user_id);

                if (config('is_portrait') == 1) {
                    $bet_amount = Redis::get('pplay_amount_bet_' . $gameId . '_' . $user_id) ?: 0;
                    $state = $socket->UpScore2($user_id, $gamemoney, $reference, 38000, $bet_amount);
                    //清除下注数量
                    Redis::rm('pplay_amount_bet_' . $gameId . '_' . $user_id);
                } else {
                    if ($amount > 0) {
                        $state = $socket->UpScore($user_id, $gamemoney, $reference);
                    }
                }
                Redis::set('pplay_is_exec_result_' . $user_id . $reference, 1, 3600);
                // save_log('pplay', '===结算加钱' . $user_id . '===状态：' . json_encode($state));
            }
            // $clear_data = Redis::get('pplay_game_id_'.$user_id) ?: [];
            // $clear_data[$gameId] = 1;
            // unset($clear_data[$gameId]);
            // if (empty($clear_data)) {
            //     $state = $socket->ClearLablel($user_id,38000);
            //     Redis::rm('pplay_game_id_'.$user_id);
            // } else {
            //     Redis::set('pplay_game_id_'.$user_id,$clear_data);
            // }

            $left_balance = $this->getBalance($user_id);
            $respons = [
                "transactionId" => substr($user_id . '_' . $reference, 0, 30),
                "currency" => $this->Currency,
                "cash" => round($left_balance, 2),
                "bonus" => 0,
                "error" => 0,
                "description" => "Success"
            ];
            save_log('pplay', '===' . request()->url() . '===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('pplay_error', '===' . $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjsonpp('api error');
        }
    }

    //结算加钱
    public function bonusWin()
    {
        try {
            $params = request()->param() ?: json_decode(file_get_contents('php://input'), 1);
            save_log('pplay', '===' . request()->url() . '===接口请求数据===' . json_encode($params));
            $hash = $params['hash'] ?? '';
            $user_id = $params['userId'] ?? '';
            $gameId = $params['gameId'] ?? '';
            $roundId = $params['roundId'] ?? '';
            $amount = $params['amount'] ?? '';
            $reference = $params['reference'] ?? '';
            $providerId = $params['providerId'] ?? '';
            if (empty($hash) || empty($user_id) || empty($reference)) {
                return $this->failjsonpp('parameters error');
            }
            $user_id = explode('_', $user_id)[1];
            unset($params['hash']);
            $check_hash = $this->createsign($params, $this->API_Token);
            if ($check_hash != $hash) {
                return $this->failjsonpp('hash error');
            }
            $balance = $this->getBalance($user_id);
            $respons = [
                "transactionId" => substr($user_id . '_' . $reference, 0, 30),
                "currency" => $this->Currency,
                "cash" => round($balance, 2),
                "bonus" => 0,
                // "usedPromo"     =>0,
                "error" => 0,
                "description" => "Success"
            ];
            save_log('pplay', '===' . request()->url() . '===响应成功数据===' . json_encode($respons));
            return json($respons);
            exit();

        } catch (Exception $ex) {
            save_log('pplay_error', '===' . $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjsonpp('api error');
        }
    }

    public function jackpotWin()
    {
        try {
            $params = request()->param() ?: json_decode(file_get_contents('php://input'), 1);
            save_log('pplay', '===' . request()->url() . '===接口请求数据===' . json_encode($params));
            $hash = $params['hash'] ?? '';
            $user_id = $params['userId'] ?? '';
            $gameId = $params['gameId'] ?? '';
            $roundId = $params['roundId'] ?? '';
            $amount = $params['amount'] ?? '';
            $reference = $params['reference'] ?? '';
            $providerId = $params['providerId'] ?? '';
            if (empty($hash) || empty($user_id) || empty($reference)) {
                return $this->failjsonpp('parameters error');
            }
            $user_id = explode('_', $user_id)[1];
            unset($params['hash']);
            $check_hash = $this->createsign($params, $this->API_Token);
            if ($check_hash != $hash) {
                return $this->failjsonpp('hash error');
            }
            if (Redis::get('pplay_is_exec_jackpotWin_' . $user_id . $reference)) {
                $balance = $this->getBalance($user_id);
                $respons = [
                    "transactionId" => substr($user_id . '_' . $reference, 0, 30),
                    "currency" => $this->Currency,
                    "cash" => round($balance, 2),
                    "bonus" => 0,
                    // "usedPromo"     =>0,
                    "error" => 100,
                    "description" => "fail"
                ];
                save_log('pplay', '===' . request()->url() . '===响应成功数据===' . json_encode($respons));
                return json($respons);
                exit();
            }
            if ($amount > 0) {
                $socket = new QuerySocket();
                $gamemoney = bcmul($amount, bl, 0);
                $user_id = intval($user_id);
                if (config('is_portrait') == 1) {
                    $state = $socket->UpScore2($user_id, $gamemoney, $reference, 38000, 0);
                } else {
                    $state = $socket->UpScore($user_id, $gamemoney, $reference);
                }
                Redis::set('pplay_is_exec_jackpotWin_' . $user_id . $reference, 1, 3600);
                // save_log('pplay', '===jackpotWin结算加钱' . $user_id . '===状态：' . json_encode($state));
            }
            $left_balance = $this->getBalance($user_id);
            $respons = [
                "transactionId" => substr($user_id . '_' . $reference, 0, 30),
                "currency" => $this->Currency,
                "cash" => round($left_balance, 2),
                "bonus" => 0,
                "error" => 0,
                "description" => "Success"
            ];
            save_log('pplay', '===' . request()->url() . '===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('pplay_error', '===' . $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjsonpp('api error');
        }
    }


    public function promoWin()
    {
        try {
            $params = request()->param() ?: json_decode(file_get_contents('php://input'), 1);
            save_log('pplay', '===' . request()->url() . '===接口请求数据===' . json_encode($params));
            $hash = $params['hash'] ?? '';
            $user_id = $params['userId'] ?? '';
            $gameId = $params['gameId'] ?? '';
            $roundId = $params['roundId'] ?? '';
            $amount = $params['amount'] ?? '';
            $reference = $params['reference'] ?? '';
            $providerId = $params['providerId'] ?? '';
            if (empty($hash) || empty($user_id) || empty($reference)) {
                return $this->failjsonpp('parameters error');
            }
            $user_id = explode('_', $user_id)[1];
            unset($params['hash']);
            $check_hash = $this->createsign($params, $this->API_Token);
            if ($check_hash != $hash) {
                return $this->failjsonpp('hash error');
            }
            if (Redis::get('pplay_is_exec_promoWin_' . $user_id . $reference)) {
                $balance = $this->getBalance($user_id);
                $respons = [
                    "transactionId" => substr($user_id . '_' . $reference, 0, 30),
                    "currency" => $this->Currency,
                    "cash" => round($balance, 2),
                    "bonus" => 0,
                    // "usedPromo"     =>0,
                    "error" => 100,
                    "description" => "fail"
                ];
                save_log('pplay', '===' . request()->url() . '===响应成功数据===' . json_encode($respons));
                return json($respons);
                exit();
            }
            if ($amount > 0) {
                $socket = new QuerySocket();
                $gamemoney = bcmul($amount, bl, 0);
                $user_id = intval($user_id);
                if (config('is_portrait') == 1) {
                    $state = $socket->UpScore2($user_id, $gamemoney, $reference, 38000, 0);
                } else {
                    $state = $socket->UpScore($user_id, $gamemoney, $reference);
                }
                Redis::set('pplay_is_exec_promoWin_' . $user_id . $reference, 1, 3600);
                // save_log('pplay', '===jpromoWin结算加钱' . $user_id . '===状态：' . json_encode($state));
            }
            $left_balance = $this->getBalance($user_id);
            $respons = [
                "transactionId" => substr($user_id . '_' . $reference, 0, 30),
                "currency" => $this->Currency,
                "cash" => round($left_balance, 2),
                "bonus" => 0,
                "error" => 0,
                "description" => "Success"
            ];
            save_log('pplay', '===' . request()->url() . '===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('pplay_error', '===' . $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjsonpp('api error');
        }
    }

    //取消下注加钱
    public function refund()
    {
        try {
            $params = request()->param() ?: json_decode(file_get_contents('php://input'), 1);
            save_log('pplay', '===' . request()->url() . '===接口请求数据===' . json_encode($params));
            $hash = $params['hash'] ?? '';
            $user_id = $params['userId'] ?? '';
            $gameId = $params['gameId'] ?? '';
            $roundId = $params['roundId'] ?? '';
            $amount = $params['amount'] ?? '';
            $reference = $params['reference'] ?? '';
            $providerId = $params['providerId'] ?? '';
            if (empty($hash) || empty($user_id) || empty($reference)) {
                return $this->failjsonpp('parameters error');
            }
            $user_id = explode('_', $user_id)[1];
            unset($params['hash']);
            $check_hash = $this->createsign($params, $this->API_Token);
            if ($check_hash != $hash) {
                return $this->failjsonpp('hash error');
            }
            if (Redis::get('pplay_is_exec_refund_' . $user_id . $reference) || !Redis::get('pplay_is_exec_bet_' . $user_id . $reference)) {
                $respons = [
                    "transactionId" => substr($user_id . '_' . $reference, 0, 30),
                    "error" => 100,
                    "description" => "fail"
                ];
                save_log('pplay', '===' . request()->url() . '===响应成功数据===' . json_encode($respons));
                return json($respons);
                exit();
            }
            if ($amount > 0) {
                $socket = new QuerySocket();
                $gamemoney = bcmul($amount, bl, 0);
                $user_id = intval($user_id);
                if (config('is_portrait') == 1) {
                    $state = $socket->UpScore2($user_id, $gamemoney, $reference, 38000, 0);
                } else {
                    $state = $socket->UpScore($user_id, $gamemoney, $reference);
                }
                Redis::set('pplay_is_exec_refund_' . $user_id . $reference, 1, 3600);
                // Redis::rm('pplay_'.$user_id.$reference);
                // save_log('pplay', '===取消下注加钱' . $user_id . '===状态：' . json_encode($state));
            }

            $left_balance = $this->getBalance($user_id);
            $respons = [
                "transactionId" => substr($user_id . '_' . $reference, 0, 30),
                "error" => 0,
                "description" => "Success"
            ];
            save_log('pplay', '===' . request()->url() . '===响应成功数据===' . json_encode($respons));
            return json($respons);
        } catch (Exception $ex) {
            save_log('pplay_error', '===' . $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjsonpp('api error');
        }
    }

    //结算加钱
    public function endRound()
    {
        try {
            $params = request()->param() ?: json_decode(file_get_contents('php://input'), 1);
            save_log('pplay', '===' . request()->url() . '===接口请求数据===' . json_encode($params));
            $hash = $params['hash'] ?? '';
            $user_id = $params['userId'] ?? '';
            $gameId = $params['gameId'] ?? '';
            $roundId = $params['roundId'] ?? '';
            $amount = $params['amount'] ?? '';
            $reference = $params['reference'] ?? '';
            $providerId = $params['providerId'] ?? '';
            if (empty($hash) || empty($user_id)) {
                return $this->failjsonpp('parameters error');
            }
            $user_id = explode('_', $user_id)[1];
            unset($params['hash']);
            $check_hash = $this->createsign($params, $this->API_Token);
            if ($check_hash != $hash) {
                return $this->failjsonpp('hash error');
            }
            $clear_data = Redis::get('pplay_game_id_' . $user_id) ?: [];
            $clear_data[$gameId] = 1;
            unset($clear_data[$gameId]);
            if (empty($clear_data)) {
                $socket = new QuerySocket();
                $state = $socket->ClearLablel($user_id, 38000);
                Redis::rm('pplay_game_id_' . $user_id);
            } else {
                Redis::set('pplay_game_id_' . $user_id, $clear_data);
            }
            if (config('need_third_rank') == 1) {
                Redis::lpush('third_game_rank_list', json_encode([
                    'PlatformId' => 38000,
                    'PlatformName' => 'PP',
                    'GameId' => $gameId,
                ]));
            }

            $balance = $this->getBalance($user_id);
            $respons = [
                "cash" => round($balance, 2),
                "bonus" => 0,
                // "usedPromo"     =>0,
                "error" => 0,
                "description" => "Success"
            ];
            save_log('pplay', '===' . request()->url() . '===响应成功数据===' . json_encode($respons));
            return json($respons);
            exit();
        } catch (Exception $ex) {
            save_log('pplay_error', '===' . $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjsonpp('api error');
        }
    }

    //获取玩家账号余额
    private function getBalance($roleid)
    {
        sleep(0.5);
        $roleid = intval($roleid);
        $socket = new QuerySocket();
        $m = $socket->DSQueryRoleBalance($roleid);
        $gamemoney = $m['iGameWealth'] ?? 0;
        $balance = bcdiv($gamemoney, bl, 3);
        return floor($balance * 100) / 100;
    }

    private function curl($url, $post_data = '', $header = [], $type = 'get')
    {
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
        if ($type == 'post') {
            //设置post方式提交
            curl_setopt($curl, CURLOPT_POST, 1);
            if (is_array($post_data)) {
                $post_data = http_build_query($post_data);
            }
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
        }
        if ($header) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        }
        //https
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        //执行命令
        $data = curl_exec($curl);
        save_log('pplay', '===' . request()->url() . '===三方返回数据===' . $data);
        //关闭URL请求
        curl_close($curl);
        //显示获得的数据
        return $data;
    }

    //签名函数
    private function createsign($data, $Md5key)
    {
        ksort($data);
        $md5str = '';
        foreach ($data as $key => $val) {
            if ($val !== null) {
                if ($md5str) {
                    $md5str = $md5str . '&' . $key . '=' . $val;
                } else {
                    $md5str = $key . '=' . $val;
                }

            }
        }
        $str = $md5str . $Md5key;
        return md5($str);
    }


    //创建临时订单
    private function makeOrderId($uid)
    {
        return date('YmdHis') . sprintf('%.0f', floatval(explode(' ', microtime())[0]) * 1000) . $uid;
    }

    //加密
    private function encry($str, $key = 'btiplus')
    {
        $str = trim($str);
        return think_encrypt($str, $key);

    }

    //解密
    private function decry($str, $key = 'btiplus')
    {
        return think_decrypt($str, $key);
    }

    /**
     * 客户端请求创建用户
     * @return array|string|string[]
     */
    public function createusertest()
    {
        $reserveIdRedisKey = '12312312312';
//        Redis::set($reserveIdRedisKey,100);
        Redis::rm($reserveIdRedisKey);
        $betsTotal = Redis::get($reserveIdRedisKey) ?? 0;
        var_dump((int)$betsTotal);
        die();
        $roleId = 110;
        $encry = $this->encry($roleId);
        Redis::set($encry, $encry, 600);
        return $encry;
    }

    /**
     * 令牌验证
     * @param Request $request
     * @return \think\response\Json
     */
    public function ValidateToken(Request $request): \think\response\Json
    {
        try {
            $authToken = $request->get('auth_token');
            save_log('bti_plus', '===' . request()->url() . '===接口请求数据===' . json_encode($authToken));
            $getRedisToken = Redis::get($authToken);
            if (empty($authToken) || $authToken != $getRedisToken) {
                return $this->failjson(-3, 'auth_token error');
            }
            $userId = $this->decry($authToken);
            if (empty($userId)) {
                return $this->failjson(-3, 'auth_token error');
            }
            $balance = $this->getBalance($userId);
            $response = [
                "error_code" => 0,
                "error_message" => "Success",
                "cust_id" => $userId,
                "balance" => round($balance, 2),
                "cust_login" => "",
                "city" => "",
                "country" => "",
                "currency_code" => "",
                "extSessionID" => "",
                "data" => ""
            ];
            save_log('bti_plus', '===' . request()->url() . '===响应成功数据===' . json_encode($response));
            return json($response);
        } catch (Exception $ex) {
            save_log('bti_plus_error', '===' . $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjsonpp('api error');
        }
    }

    /**
     * 扣款
     * @param Request $request
     * @return mixed|\think\response\Json
     */
    public function Reserve(Request $request)
    {
        $accountId = $request->param('cust_id');
        $reserveId = $request->param('reserve_id');
        $amount = $request->param('amount');
        $extSessionId = $request->param('extsessionID');
        $agentId = $request->param('agent_id');
        $customerId = $request->param('customer_id');
        $socket = new QuerySocket();
        $accountDB = new AccountDB();
        try {
            $accountInfo = $accountDB->getTableObject('T_Accounts')
                ->where('AccountID', $accountId)
                ->select();
            save_log('btiplus', '===' . request()->url() . '===接口请求数据===' . json_encode($request->param()));
            //会员不存在
            if (empty($accountInfo)) {
                return [
                    "error_code" => -2,
                    "error_message" => "Invalid Customer",
                    "balance" => 0,
                    "trx_id" => $this->makeOrderId($accountId),
                ];
            }
            $balance = $this->getBalance($accountId);
            //资金不足
            if ($balance < $amount || $amount !== 0) {
                $response = [
                    "error_code" => -4,
                    "error_message" => "Insufficient Amount",
                    "balance" => $balance,
                    "trx_id" => $this->makeOrderId($accountId),
                ];
                save_log('btiplus', '===' . request()->url() . '===响应资金不足数据===' . json_encode($response));
                return json($response);
            }

            $reserve = Redis::get($this->reserveRedisKey . $accountId . $reserveId);//请求重复直接返回上次请求参数
            if (!empty($reserve)) {
                save_log('btiplus', '===' . request()->url() . '===响应重复数据===' . json_encode($reserve));
                return $reserve;
            }

            $state = $socket->downScore($accountId, $amount, $reserveId, 38000);
            if ($state['iResult'] != 0) {
                $response = [
                    "error_code" => -4,
                    "error_message" => "Insufficient Amount",
                    "balance" => $balance,
                    "trx_id" => $this->makeOrderId($accountId),
                ];
                save_log('btiplus', '===' . request()->url() . '===响应扣款失败数据===' . json_encode($response));
                return json($response);
            }

            Redis::set($this->reserveAmountBetRedisKey . $accountId . $reserveId, $amount, 86400);//记录下注数量
            $lastBalance = $this->getBalance($accountId);
            $response = [
                "error_code" => 0,
                "error_message" => "No Error",
                "balance" => $lastBalance,
                "trx_id" => $this->makeOrderId($accountId),
            ];

            Redis::set($this->reserveRedisKey . $accountId . $reserveId, $response, 86400);//记录订单是否执行，防止重复
            save_log('btiplus', '===' . request()->url() . '===响应成功数据===' . json_encode($response));
            return json($response);
        } catch (Exception $ex) {
            save_log('btiplus_error', '===' . $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjson(-1, 'api error');
        }
    }

    /**
     * 注单资讯指令
     * @param Request $request
     * @return \think\response\Json|void
     */
    public function DebitReserve(Request $request)
    {
        $accountId = $request->param('cust_id');
        $reserveId = $request->param('reserve_id');
        $amount = $request->param('amount');
        $reqId = $request->param('req_id');
        $agentId = $request->param('agent_id');
        $customerId = $request->param('customer_id');
        $orderId = $request->param('purchase_id');
        $balance = $this->getBalance($accountId);

        $commitReserveRedisKey = 'USER_COMMIT_FOR_RESERVE_ID_' . $accountId . $reserveId . $orderId;
        //查看玩家reserve是否存在
        if (!Redis::has($this->reserveRedisKey. $accountId . $reserveId)) {
            $response = [
                "error_code" => 0,
                "error_message" => "ReserveID Not Exist",
                "balance" => $balance,
                "trx_id" => $this->makeOrderId($accountId),
            ];
            return json($response);
        }

        //查看相同一次投注订单是否重复提交
        $numberOfBets = Redis::get($this->reqIdRedisKey. $accountId . $reserveId . $orderId . $reqId);
        if ($numberOfBets) {
            $response = [
                "error_code" => 0,
                "error_message" => "No Error",
                "balance" => $balance,
                "trx_id" => $this->makeOrderId($accountId),
            ];
            return json($response);
        }

        //相同reserve的多次投注
        $debitTotal = Redis::get($this->debitTotalRedisKey . $accountId . $reserveId . $orderId) ?? 0;
        if ($debitTotal) {
            (int)$debitTotal += $amount;
        } else {
            (int)$debitTotal = $amount;
        }
        //debit reserve(借方准备金) 总金额大于储备金额
        //Reserve 中的 URL 传入&amount=10，DebitRserve 中
        //的 url 可以传&amount=10.01，只需读取 Reserve
        //URL 中的金额即可。
        $reserveBets = Redis::get($this->reserveAmountBetRedisKey. $accountId . $reserveId);//玩家reserve总金额
        if ($reserveBets < bcadd($debitTotal, 0.01, 2)) {
            $response = [
                "error_code" => 0,
                "error_message" => "Total DebitReserve amount larger than",
                "balance" => $balance,
                "trx_id" => $this->makeOrderId($accountId),
            ];
            return json($response);
        }
        Redis::set($this->debitTotalRedisKey. $accountId . $reserveId . $orderId, $debitTotal, 84600);
        Redis::set($this->reqIdRedisKey. $accountId . $reserveId . $orderId . $reqId, $amount, 84600);
        //已取消预留
        $cancelDebitReserve = Redis::get('RESERVE_BET_CANCEL' . $accountId . $reserveId);
        if ($cancelDebitReserve) {
            $response = [
                "error_code" => 0,
                "error_message" => "Already cancelled reserve",
                "balance" => $balance,
                "trx_id" => $this->makeOrderId($accountId),
            ];
            return json($response);
        }

        //已提交的预留
        if (Redis::has($commitReserveRedisKey)) {
            $response = [
                "error_code" => 0,
                "error_message" => "Already committed reserve",
                "balance" => $balance,
                "trx_id" => $this->makeOrderId($accountId),
            ];
            return json($response);
        }

        //debit reserve(借方准备金) 总金额小于储备金额

        $response = [
            "error_code" => 0,
            "error_message" => "No Error",
            "balance" => $balance,
            "trx_id" => $this->makeOrderId($accountId),
        ];
        return json($response);


    }

    /**
     * 取消投注指令
     * @param Request $request
     * @return \think\response\Json
     */
    public function CancelReserve(Request $request)
    {
        $accountId = $request->param('cust_id');
        $reserveId = $request->param('reserve_id');
        $agentId = $request->param('agent_id');
        $customerId = $request->param('customer_id');
        $orderId = $request->param('purchase_id');
        $balance = $this->getBalance($accountId);
        $reserveRedisKey = 'RESERVE_BET_' . $accountId . $reserveId;
        $reserveCancelRedisKey = 'RESERVE_BET_CANCEL' . $accountId . $reserveId;
        $reserveAmountBetRedisKey = 'RESERVE_AMOUNT_BET_' . $accountId . $reserveId;
        $commitReserveRedisKey = 'USER_COMMIT_FOR_RESERVE_ID_' . $accountId . $reserveId . $orderId;
        $socket = new QuerySocket();
        //已提交，不能取消
        if (Redis::has($commitReserveRedisKey)) {
            $response = [
                "error_code" => 0,
                "error_message" => "Already Debitted Reserve",
                "balance" => $balance,
            ];
            return json($response);
        }
        //不存在的reserve
        if (!Redis::has($reserveRedisKey)) {
            $response = [
                "error_code" => 0,
                "error_message" => "ReserveID not exists",
                "balance" => $balance,
            ];
            return json($response);
        }
        //reserveID重复请求
        if (Redis::has($reserveCancelRedisKey)) {
            $response = [
                "error_code" => 0,
                "error_message" => "No Error",
                "balance" => $balance,
            ];
            return json($response);
        }
        $reserveAmount = Redis::get($reserveAmountBetRedisKey);//玩家投注扣款金额
        $socket->UpScore2($accountId, bcmul($reserveAmount, bl, 0), $reserveId, 59000, 0);
        $lastBalance = $this->getBalance($accountId);
        $response = [
            "error_code" => 0,
            "error_message" => "No Error",
            "balance" => $lastBalance,
        ];
        Redis::set($reserveCancelRedisKey, $response, 86400);
        Redis::rm($reserveRedisKey);
        return json($response);
        //
    }

    /**
     * 投注确认指令
     * @param Request $request
     * @return \think\response\Json
     */
    public function CommitReserve(Request $request)
    {
        $accountId = $request->param('cust_id');
        $reserveId = $request->param('reserve_id');
        $agentId = $request->param('agent_id');
        $customerId = $request->param('customer_id');
        $orderId = $request->param('purchase_id');
        $balance = $this->getBalance($accountId);
        $reserveRedisKey = 'RESERVE_BET_' . $accountId . $reserveId;
        $debitTotalRedisKey = 'DEBIT_TOTAL_FOR_RESERVE_ID_' . $accountId . $reserveId . $orderId;
        $commitReserveRedisKey = 'USER_COMMIT_FOR_RESERVE_ID_' . $accountId . $reserveId . $orderId;
        $reserveAmountBetRedisKey = 'RESERVE_AMOUNT_BET_' . $accountId . $reserveId;//投注金额
        $socket = new QuerySocket();
        //不存在的reserveID
        if (Redis::has($reserveRedisKey)) {
            $response = [
                "error_code" => 0,
                "error_message" => "ReserveID Not Exist",
                "balance" => $balance,
                "trx_id" => $this->makeOrderId($accountId),
            ];
            return json($response);
        }
        //reserveID重复请求
        $lastCommitReserveDate = Redis::get($commitReserveRedisKey);
        if ($lastCommitReserveDate) {
            return json($lastCommitReserveDate);
        }
        $debitTotal = Redis::get($debitTotalRedisKey) ?? 0;//获取多次DebitReserve后的总额
        $reserveAmount = Redis::get($reserveAmountBetRedisKey);//玩家投注总额
        $amount = bcsub($reserveAmount, $debitTotal, 2);
        $lastAmount = bcmul($amount, bl, 0);
        if ($amount > 0) {
            $socket->UpScore2($accountId, $lastAmount, $reserveId, 59000, 0);
        }
        $lastBalance = $this->getBalance($accountId);
        $response = [
            "error_code" => 0,
            "error_message" => "No Error",
            "balance" => $lastBalance,
        ];
        Redis::set($commitReserveRedisKey, $response, 86400);
        return json($response);
    }

    /**
     * 重新结算回收彩金指令
     * @param Request $request
     * @return mixed|\think\response\Json
     */
    public function DebitCustomer(Request $request)
    {
        $accountId = $request->param('cust_id');
        $amount = $request->param('amount');
        $req_id = $request->param('req_id');
        $agentId = $request->param('agent_id');
        $customerId = $request->param('customer_id');
        $orderId = $request->param('purchase_id');
        $balance = $this->getBalance($accountId);
        $result = bcsub($balance, $amount, 2);
        $socket = new QuerySocket();
        $debitCustomerRedisKey = 'DEBIT_CUSTOMER_' . $accountId . $req_id;
        $hasDebitCustomer = Redis::get($debitCustomerRedisKey);
        if ($hasDebitCustomer) {
            return $hasDebitCustomer;
        }
        if ($result < 0) {
            $lastAmount = bcmul($balance, bl, 0);
        } else {
            $lastAmount = bcmul($amount, bl, 0);
        }
        $socket->downScore($accountId, $lastAmount, $req_id, 59000, 0);

        $response = [
            "error_code" => 0,
            "error_message" => "No Error",
            "balance" => $result,
        ];
        Redis::set($debitCustomerRedisKey, $response, 86400);
        return json($response);
    }

    /**
     * 结算派彩指令
     * @param Request $request
     * @return mixed|\think\response\Json
     */
    public function CreditCustomer(Request $request)
    {
        $accountId = $request->param('cust_id');
        $amount = $request->param('amount');
        $req_id = $request->param('req_id');
        $agentId = $request->param('agent_id');
        $customerId = $request->param('customer_id');
        $orderId = $request->param('purchase_id');
        $balance = $this->getBalance($accountId);
        $result = bcsub($balance, $amount, 2);
        $socket = new QuerySocket();
        $creditCustomerRedisKey = 'CREDIT_CUSTOMER_' . $accountId . $req_id;
        $hasCreditCustomer = Redis::get($creditCustomerRedisKey);
        if ($hasCreditCustomer) {
            return $hasCreditCustomer;
        }
        $lastAmount = bcmul(bcadd($balance, $amount, 0), bl, 0);
        $socket->UpScore2($accountId, $lastAmount, $req_id, 59000, 0);

        $response = [
            "error_code" => 0,
            "error_message" => "No Error",
            "balance" => $result,
        ];
        Redis::set($creditCustomerRedisKey, $response, 86400);
        return json($response);
    }
}