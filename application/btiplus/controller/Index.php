<?php

namespace app\btiplus\controller;


use app\model\AccountDB;
use socket\QuerySocket;
use think\Exception;
use redis\Redis;
use think\Request;
use think\Response;

class Index extends Base
{

    protected $socket;
    protected $reserveRedisKey;
    protected $reserveAmountBetRedisKey;
    protected $debitTotalRedisKey;
    protected $reqIdRedisKey;
    protected $moon;
    protected $day;
    protected $hour;

    public function __construct()
    {
        $this->socket = new QuerySocket();
        $this->reserveRedisKey = 'RESERVE_BET_';//投注扣款
        $this->reserveAmountBetRedisKey = 'RESERVE_AMOUNT_BET_';//投注金额
        $this->debitTotalRedisKey = 'DEBIT_TOTAL_FOR_RESERVE_ID_';//单注总额
        $this->reqIdRedisKey = 'ACCOUNT_NUMBER_OF_BETS_FOR_REQ_ID_';//单注请求标识
        $this->hour = 60 * 60;
        $this->day = $this->hour * 24;
        $this->moon = $this->day * 30;
        header('Access-Control-Allow-Origin:*');
//允许的请求头信息
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");
//允许的请求类型
        header('Access-Control-Allow-Methods: GET, POST, PUT,DELETE,OPTIONS,PATCH');
//允许携带证书式访问（携带cookie）
        header('Access-Control-Allow-Credentials:true');
        session_start();
    }


    /**
     * 客户端请求创建用户
     * @return \think\response\Json
     */
    public function createusertest()
    {
        $second = 60;
        $minute = 60 * $second;
        $hour = 24 * $minute;
        $day = 30 * $hour;//一个月

        $param = jsonRequest(['roleid', 'gameid', 'language', 'session_id', 'ip', 'time', 'sign']);
        save_log('btiplus', '===' . request()->url() . '===接口请求数据===' . json_encode($param));
        $roleId = $param['roleid'];
        $encry = $this->encry($roleId);
        $url = "https://bit.com" . '?operatorToken=' . $encry;
        Redis::set($encry, $encry, $this->moon);
        return $this->succjson($url);

    }

    /**
     * 令牌验证
     * @param Request $request
     * @return Response|\think\response\Json|\think\response\Jsonp|\think\response\Redirect|\think\response\View|\think\response\Xml
     */
    public function ValidateToken(Request $request)
    {
        try {
            $authToken = $request->get('auth_token');
            save_log('btiplus', '===' . request()->url() . '===接口请求数据===' . json_encode($authToken));
            $getRedisToken = Redis::get($authToken);
            if (empty($authToken) || $authToken != $getRedisToken) {
                $data = 'error_code=-3' . PHP_EOL .
                    'error_message=Generic Error';
//                    $this->failjson(-3, 'auth_token error');
                return $this->setHeader($data);
            }
            $userId = $this->decry($authToken);
            if (empty($userId)) {
                $data = 'error_code=-3' . PHP_EOL .
                    'error_message=Generic Error!';

                return $this->setHeader($data);
            }
            $balance = $this->getBalance($userId);
            $data =
                "error_code=0" . PHP_EOL .
                "error_message=No error" . PHP_EOL .
                "cust_id=" . $userId . PHP_EOL .
                "balance=" . round($balance, 2) . PHP_EOL .
                "cust_login=" . $userId . PHP_EOL .
                "city=BR" . PHP_EOL .
                "country=BR" . PHP_EOL .
                "currency_code=BRL";
            save_log('btiplus', '===' . request()->url() . '===响应成功数据===' . $data);
            return $this->setHeader($data);


        } catch (Exception $ex) {
            save_log('btiplus_error', '===' . $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            $data = 'error_code=-1' . PHP_EOL .
                'error_message=api Error';
            return $this->setHeader($data);
        }
    }

    /**
     * 扣款
     * @param Request $request
     * @return mixed|Response|\think\response\Json|\think\response\Jsonp|\think\response\Redirect|\think\response\View|\think\response\Xml
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
                return $this->apiReturnText(-2, "Invalid Customer", 0, $this->makeOrderId(00));

            }
            $accountInfo = $accountDB->getTableObject('T_Accounts')
                ->where('AccountID', $accountId)
                ->select();
            save_log('btiplus', '===' . request()->url() . '===接口请求数据===' . json_encode($request->param()));
            //会员不存在
            if (empty($accountInfo)) {
                return $this->apiReturnText(-2, "Invalid", 0, $this->makeOrderId($accountId));
            }
            $balance = $this->getBalance($accountId);
            //资金不足
            if ($balance < $amount) {
                return $this->apiReturnText(-4, "Insufficient Amount", $balance, $this->makeOrderId($accountId));
            }
//            var_dump($accountInfo);die();

            $reserve = Redis::get($this->reserveRedisKey . $accountId . $reserveId);//请求重复直接返回上次请求参数
            if (!empty($reserve)) {
                save_log('btiplus', '===' . request()->url() . '===响应重复数据===' . json_encode($reserve));
                return $reserve;
            }

            $state = $socket->downScore($accountId, $amount * bl, $reserveId, 38000);
            if ($state['iResult'] != 0) {
//                $response = [
//                    "error_code" => -4,
//                    "error_message" => "Insufficient Amount",
//                    "balance" => $balance,
//                    "trx_id" => $this->makeOrderId($accountId),
//                ];
//                save_log('btiplus', '===' . request()->url() . '===响应扣款失败数据===' . json_encode($response));
                return $this->apiReturnText(-4, "Insufficient Amount", $balance, $this->makeOrderId($accountId));

            }

            Redis::set($this->reserveAmountBetRedisKey . $accountId . $reserveId, $amount, $this->moon);//记录下注数量
            $lastBalance = $this->getBalance($accountId);
//            $response = [
//                "error_code" => 0,
//                "error_message" => "No Error",
//                "balance" => $lastBalance,
//                "trx_id" => $this->makeOrderId($accountId),
//            ];
            $response = $this->apiReturnText(0, "No Error", $lastBalance, $this->makeOrderId($accountId));
            Redis::set($this->reserveRedisKey . $accountId . $reserveId, $response, $this->moon);//记录订单是否执行，防止重复
            save_log('btiplus', '===' . request()->url() . '===响应成功数据===' . json_encode($response));
            return $response;

        } catch (Exception $ex) {
            save_log('btiplus_error', '===' . $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->apiReturnText(-1, "Error", 0, $this->makeOrderId($accountId));
        }
    }

    /**
     * 注单资讯指令
     * @param Request $request
     * @return Response|\think\response\Json|\think\response\Jsonp|\think\response\Redirect|\think\response\View|\think\response\Xml
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
//            $response = [
//                "error_code" => 0,
//                "error_message" => "ReserveID Not Exist",
//                "balance" => $balance,
//                "trx_id" => $this->makeOrderId($accountId),
//            ];
            return $this->apiReturnText(0, "ReserveID Not Exist", $balance, $this->makeOrderId($accountId));

        }

        //查看相同一次投注订单是否重复提交
        $numberOfBets = Redis::get($this->reqIdRedisKey . $accountId . $reserveId . $orderId . $reqId);
        if ($numberOfBets) {
//            $response = [
//                "error_code" => 0,
//                "error_message" => "No Error",
//                "balance" => $balance,
//                "trx_id" => $this->makeOrderId($accountId),
//            ];
            return $this->apiReturnText(0, "No Error", $balance, $this->makeOrderId($accountId));
//            return $this->apiReturn($response);
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
        if ( bcadd($reserveBets, 0.01, 2) < $debitTotal) {
//            $response = [
//                "error_code" => 0,
//                "error_message" => "Total DebitReserve amount larger than",
//                "balance" => $balance,
//                "trx_id" => $this->makeOrderId($accountId),
//            ];
            return $this->apiReturnText(0, "Total DebitReserve amount larger than", $balance, $this->makeOrderId($accountId));

        }
        Redis::set($this->debitTotalRedisKey . $accountId . $reserveId . $orderId, $debitTotal, $this->moon);
        Redis::set($this->reqIdRedisKey . $accountId . $reserveId . $orderId . $reqId, $amount, $this->moon);
        //已取消预留
        $cancelDebitReserve = Redis::get('RESERVE_BET_CANCEL' . $accountId . $reserveId);
        if ($cancelDebitReserve) {
//            $response = [
//                "error_code" => 0,
//                "error_message" => "Already cancelled reserve",
//                "balance" => $balance,
//                "trx_id" => $this->makeOrderId($accountId),
//            ];
            return $this->apiReturnText(0, "Already cancelled reserve", $balance, $this->makeOrderId($accountId));

        }

        //已提交的预留
        if (Redis::has($commitReserveRedisKey)) {
//            $response = [
//                "error_code" => 0,
//                "error_message" => "Already committed reserve",
//                "balance" => $balance,
//                "trx_id" => $this->makeOrderId($accountId),
//            ];
            return $this->apiReturnText(0, "Already committed reserve", $balance, $this->makeOrderId($accountId));
//            return $this->apiReturn($response);
        }

        //debit reserve(借方准备金) 总金额小于储备金额

//        $response = [
//            "error_code" => 0,
//            "error_message" => "No Error",
//            "balance" => $balance,
//            "trx_id" => $this->makeOrderId($accountId),
//        ];
        return $this->apiReturnText(0, "No Error", $balance, $this->makeOrderId($accountId));


    }

    /**
     * 取消投注指令
     * @param Request $request
     * @return Response|\think\response\Json|\think\response\Jsonp|\think\response\Redirect|\think\response\View|\think\response\Xml
     */
    public function CancelReserve(Request $request)
    {
        $accountId = $request->param('cust_id');
        $reserveId = $request->param('reserve_id');
        $agentId = $request->param('agent_id');
        $customerId = $request->param('customer_id');
        $orderId = $request->param('purchase_id');
        $balance = $this->getBalance($accountId);
        $reserveRedisKey = $this->reserveRedisKey . $accountId . $reserveId;
        $reserveCancelRedisKey = 'RESERVE_BET_CANCEL' . $accountId . $reserveId;
        $reserveAmountBetRedisKey = 'RESERVE_AMOUNT_BET_' . $accountId . $reserveId;
        $commitReserveRedisKey = 'USER_COMMIT_FOR_RESERVE_ID_' . $accountId . $reserveId . $orderId;
        $socket = new QuerySocket();
        //已提交，不能取消
        if (Redis::has($commitReserveRedisKey)) {
//            $response = [
//                "error_code" => 0,
//                "error_message" => "Already Debitted Reserve",
//                "balance" => $balance,
//            ];
            return $this->apiReturnText(0, "Already Debitted Reserve", $balance);
//            return $this->apiReturn($response);
        }
        //不存在的reserve
        if (!Redis::has($reserveRedisKey)) {
//            $response = [
//                "error_code" => 0,
//                "error_message" => "ReserveID not exists",
//                "balance" => $balance,
//            ];
            return $this->apiReturnText(0, "ReserveID not exists", $balance);

        }
        //reserveID重复请求
        if (Redis::has($reserveCancelRedisKey)) {
//            $response = [
//                "error_code" => 0,
//                "error_message" => "No Error",
//                "balance" => $balance,
//            ];
            return $this->apiReturnText(0, "No Error", $balance);

        }
        $reserveAmount = Redis::get($reserveAmountBetRedisKey);//玩家投注扣款金额
        $socket->UpScore2($accountId, bcmul($reserveAmount, bl, 0), $reserveId, 59000, 0);
        $lastBalance = $this->getBalance($accountId);
//        $response = [
//            "error_code" => 0,
//            "error_message" => "No Error",
//            "balance" => $lastBalance,
//        ];
        $response = $this->apiReturnText(0, "No Error", $lastBalance);
        Redis::set($reserveCancelRedisKey, $response, $this->moon);
        Redis::rm($reserveRedisKey);
        return $response;
        //
    }

    /**
     * 投注确认指令
     * @param Request $request
     * @return mixed|Response|\think\response\Json|\think\response\Jsonp|\think\response\Redirect|\think\response\View|\think\response\Xml
     */
    public function CommitReserve(Request $request)
    {
        $accountId = $request->param('cust_id');
        $reserveId = $request->param('reserve_id');
        $agentId = $request->param('agent_id');
        $customerId = $request->param('customer_id');
        $orderId = $request->param('purchase_id');
        $balance = $this->getBalance($accountId);
        $reserveRedisKey = $this->reserveRedisKey . $accountId . $reserveId;
        $debitTotalRedisKey = 'DEBIT_TOTAL_FOR_RESERVE_ID_' . $accountId . $reserveId . $orderId;
        $commitReserveRedisKey = 'USER_COMMIT_FOR_RESERVE_ID_' . $accountId . $reserveId . $orderId;
        $reserveAmountBetRedisKey = 'RESERVE_AMOUNT_BET_' . $accountId . $reserveId;//投注金额
        $socket = new QuerySocket();
        //不存在的reserveID

        if (!Redis::has($reserveRedisKey)) {
//            $response = [
//                "error_code" => 0,
//                "error_message" => "ReserveID Not Exist",
//                "balance" => $balance,
//                "trx_id" => $this->makeOrderId($accountId),
//            ];
            return $this->apiReturnText(0, "ReserveID Not Exist", $balance, $this->makeOrderId($accountId));
//            return $this->apiReturn($response);
        }
        //reserveID重复请求
        $lastCommitReserveDate = Redis::get($commitReserveRedisKey);
        if ($lastCommitReserveDate) {
            return $lastCommitReserveDate;
        }
        $debitTotal = Redis::get($debitTotalRedisKey) ?? 0;//获取多次DebitReserve后的总额
        $reserveAmount = Redis::get($reserveAmountBetRedisKey);//玩家投注总额
        $amount = bcsub($reserveAmount, $debitTotal, 2);
        $lastAmount = bcmul($amount, bl, 0);
        if ($amount > 0) {
            $socket->UpScore2($accountId, $lastAmount, $reserveId, 59000, 0);
        }
        $lastBalance = $this->getBalance($accountId);
//        $response = [
//            "error_code" => 0,
//            "error_message" => "No Error",
//            "balance" => $lastBalance,
//        ];
        $response = $this->apiReturnText(0, "No Error", $lastBalance);
        Redis::set($commitReserveRedisKey, $response, $this->moon);
        return $response;

    }

    /**
     * 重新结算回收彩金指令
     * @param Request $request
     * @return mixed|Response|\think\response\Json|\think\response\Jsonp|\think\response\Redirect|\think\response\View|\think\response\Xml
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
        $response = $this->apiReturnText(0, "No Error", $result);
        Redis::set($debitCustomerRedisKey, $response, $this->moon);
        return $response;
    }

    /**
     * 结算派彩指令
     * @param Request $request
     * @return mixed|Response|\think\response\Json|\think\response\Jsonp|\think\response\Redirect|\think\response\View|\think\response\Xml
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

//        $response = [
//            "error_code" => 0,
//            "error_message" => "No Error",
//            "balance" => $result,
//        ];
        $response = $this->apiReturnText(0, "No Error", $result);
        Redis::set($creditCustomerRedisKey, $response, $this->moon);
        return $response;
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
        $str = trim($str);
        return think_decrypt($str, $key);
    }
}