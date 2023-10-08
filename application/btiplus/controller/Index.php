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




    /**
     * 客户端请求创建用户
     * @return \think\response\Json
     */
    public function createusertest()
    {
        $param = jsonRequest(['roleid', 'gameid', 'language', 'session_id', 'ip', 'time', 'sign']);
        save_log('btiplus', '===' . request()->url() . '===接口请求数据===' . json_encode($param));
        $roleId = $param['roleid'];
        $encry = $this->encry($roleId);
        $url = $this->API_Host.'?operatorToken='.$encry;
        Redis::set($encry, $encry, 2592000);
        return $this->succjson($url);

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
            if (!is_numeric($accountId)) {
                $response = [
                    "error_code" => -2,
                    "error_message" => "Invalid Customer",
                    "balance" => 0,
                    "trx_id" => $this->makeOrderId(00),
                ];
                return json($response);
            }
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
//            var_dump($accountInfo);die();

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
        if (!Redis::has($this->reserveRedisKey . $accountId . $reserveId)) {
            $response = [
                "error_code" => 0,
                "error_message" => "ReserveID Not Exist",
                "balance" => $balance,
                "trx_id" => $this->makeOrderId($accountId),
            ];
            return json($response);
        }

        //查看相同一次投注订单是否重复提交
        $numberOfBets = Redis::get($this->reqIdRedisKey . $accountId . $reserveId . $orderId . $reqId);
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
        $reserveBets = Redis::get($this->reserveAmountBetRedisKey . $accountId . $reserveId);//玩家reserve总金额
        if ($reserveBets < bcadd($debitTotal, 0.01, 2)) {
            $response = [
                "error_code" => 0,
                "error_message" => "Total DebitReserve amount larger than",
                "balance" => $balance,
                "trx_id" => $this->makeOrderId($accountId),
            ];
            return json($response);
        }
        Redis::set($this->debitTotalRedisKey . $accountId . $reserveId . $orderId, $debitTotal, 84600);
        Redis::set($this->reqIdRedisKey . $accountId . $reserveId . $orderId . $reqId, $amount, 84600);
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
}