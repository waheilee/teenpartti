<?php

namespace app\lottery\controller;

use app\model\AccountDB;
use app\model\Role;
use app\model\ThirdGameReport;
use redis\Redis;
use socket\QuerySocket;
use think\Exception;


class Index extends Base
{


    public function _initialize()
    {
        parent::_initialize();
        header('Access-Control-Allow-Origin:*');
//允许的请求头信息
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");
//允许的请求类型
        header('Access-Control-Allow-Methods: GET, POST, PUT,DELETE,OPTIONS,PATCH');
//允许携带证书式访问（携带cookie）
        header('Access-Control-Allow-Credentials:true');
    }


    public function createuser()
    {
        try {
            $param = jsonRequest(['roleid', 'gameid', 'language', 'time', 'sign']);
            $clientkey = config('clientkey');
            $key = md5($param['roleid'] . $param['gameid'] . $param['language'] . $param['time'] . $clientkey);
            if ($key != strtolower($param['sign'])) {
                return $this->apiReturn(100, '', 'sign is error');
            }

            $roleid = $param['roleid'];
            $productwallet = 301;//$param['productwallet'];
            $language = $param['language'];


            if (empty($productwallet)) {
                return $this->apiReturn(100, '', 'The Wallet Is Not Exist!');
            }

            $accountM = new AccountDB();
            $user = $accountM->getRow(['AccountID' => $roleid], '*');
            if (!$user) {
                return $this->errorinfo('玩家不存在');
            }
            $currency = 'THB';
            $result = $this->createPlayer($roleid, $currency);
            save_log('lottery', '第三方玩家创建===' . json_encode($result));
            if ($result) {
                $ret = $this->upscore($roleid, $productwallet);
                if ($ret) {
                    $url = $this->launchGame($roleid, $param['gameid'], $language, $productwallet);
                    return $this->apiReturn(0, $url, 'success');
                } else {
                    return $this->apiReturn(100, '', 'upscore error');
                }
            }
            return $this->apiReturn(100, '', 'api error');
        }
        catch (Exception $ex){
            save_log('lottery', $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->apiReturn(100, '', 'api error');
        }
    }


    //上分
    private function upscore($roleid, $productwallet)
    {


        try {
            $socket = new QuerySocket();
            $m = $socket->getPlayerBalance($roleid);
            if ($m['iResult']) {
                return $this->errorinfo('余额查询错误');
            }
            $gamemoney = $m['iGameMoney'];

            if($gamemoney<100*1000){
                $socket->UpLotteryScore(0,$roleid, $gamemoney,'Lessthen100');
                return $this->errorinfo('余额必须大于100');
            }

            save_log('lottery', '查询玩家余额：' . $gamemoney);
            $balance = FormatMoney($gamemoney);
            if ($balance > 0) {
                $cash_money = sprintf('%.2f', $balance);
                $result = $this->goldTransfer($roleid, $cash_money, $productwallet);
                save_log('lottery', 'lotter 上分：' . $roleid . '===状态：' . json_encode($result));
                if ($result) {
                    $state = $socket->UpLotteryScore(1,$roleid, $gamemoney,$result);
                    $keystr = 'LotteryScore' . $roleid;
                    if (Redis::has($keystr)) {
                        save_log('lottery', '下分reids 存在');
                        $thridgameM = new ThirdGameReport();
                        $order_id = Redis::get($keystr);
                        $deposit = $thridgameM->getValue(['OrderId' => $order_id], 'Deposit');
                        $thridgameM->updateByWhere(['orderid' => $order_id], ['Deposit' => $deposit + $gamemoney]);
                    } else {

                        Redis::set($keystr, $result);
                        $roleM = new Role();
                        $loginname = $roleM->getValue(['roleid'=>$roleid],'LoginName');
                        $thridgameM = new ThirdGameReport();
                        $data = [
                            'OrderId' => $result,
                            'GameType' => 20,
                            'RoleId' => $roleid,
                            'NickName' => $loginname,
                            'Deposit' => $gamemoney,
                            'PayOut' => 0,
                            'AddTime' => date('Y-m-d H:i:s', time())
                        ];
                        $ret=$thridgameM->add($data);
                        save_log('lottery', '下分状态'.$ret);
                    }
                    save_log('lottery', '游戏服通知刷分返回===玩家' . $roleid . '===状态：' . json_encode($state));
                    return 1;
                } else {
                    $socket->UpLotteryScore(0,$roleid, $gamemoney,'Lessthen100');
                    return 0;
                }
            } else {
                return 1;
            }
        } catch (Exception $ex) {
            save_log('lottery', $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return 0;
        }
    }


    public function downscore()
    {
        try {
            $param = jsonRequest(['roleid', 'time', 'sign']);
            $clientkey = config('clientkey');
            $key = md5($param['roleid'] . $param['time'] . $clientkey);
            if ($key != $param['sign']) {
                return $this->apiReturn(100, '', 'The Sign is check error');
            }
            $productwallet = 301;// $param['productwallet'];
            $roleid = $param['roleid'];
            $result = $this->doDownScore($roleid, $productwallet);
            if ($result)
                return $this->apiReturn(0, '', 'success');
            return $this->apiReturn(100, '', 'Api Error');
        } catch (Exception $ex) {
            save_log('lottery', $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->apiReturn(100, '', 'Api Error');
        }

    }


    public function doDownScore($roleid, $productwallet)
    {
        $gold = $this->getBalance($roleid, $productwallet);
        $balance = floatval($gold); //获取余额
        $balance = sprintf('%.2f', $balance);
        if ($balance > 0) {
            //第三方平台先扣除
            $result = $this->goldTransfer($roleid, -$balance, $productwallet);
            save_log('lottery', '第三方下分===' . $balance . '===' . json_encode($result));
            if ($result) {
                $socket = new QuerySocket();
                $res = $socket->downLotteryScore($roleid, $balance * 1000, $result);
                save_log('lottery', '游服上分===' . $roleid . '===' . json_encode($res));
                if ($res['iResult'] == 0) {
                    $gamemoney =$balance * 1000;
                    $keystr ='LotteryScore'.$roleid;
                    if(Redis::has($keystr)){
                        $thridgameM = new ThirdGameReport();
                        $order_id = Redis::get($keystr);
                        $deposit =$thridgameM->getValue(['OrderId'=>$order_id],'Deposit');
                        $profit = $gamemoney-$deposit;
                        $data=[
                            'PayOut'=> $gamemoney,
                            'Profit'=>$profit,
                            'UpdateTime' =>date('Y-m-d H:i:s', time())
                        ];
                        $thridgameM->updateByWhere(['orderid' => $order_id],$data);
                        Redis::rm($keystr);
                    }
                    save_log('lottery',
                        '下分成功2' . $roleid . '===' . json_encode($res['iResult']) . 'orderid:' . $result . 'money' . $balance * 1000);
                    return true;
                }
                return false;
            } else {
                save_log('lottery', $roleid . '下分第三方扣分异常2==' . json_encode($result));
                return false;
            }
        } else if($balance == 0){
            $keystr = 'LotteryScore' . $roleid;
            if (Redis::has($keystr)) {
                $thridgameM = new ThirdGameReport();
                $order_id = Redis::get($keystr);
                $deposit = $thridgameM->getValue(['OrderId' => $order_id], 'Deposit');
                $profit = 0 - $deposit;
                $data = [
                    'PayOut' => 0,
                    'Profit' => $profit,
                    'UpdateTime' => date('Y-m-d H:i:s', time())
                ];
                $thridgameM->updateByWhere(['orderid' => $order_id], $data);
                Redis::rm($keystr);
            }
            return true;
//            $socket = new QuerySocket();
//            $res = $socket->downLotteryScore($roleid, 0, 'zero_order_Id');
//            save_log('lottery', '游服上分===' . $roleid . '===' . json_encode($res));
//            if ($res['iResult'] == 0) {
//                $gamemoney = $balance * 1000;
//                $keystr = 'LotteryScore' . $roleid;
//                if (Redis::has($keystr)) {
//                    $thridgameM = new ThirdGameReport();
//                    $order_id = Redis::get($keystr);
//                    $deposit = $thridgameM->getValue(['OrderId' => $order_id], 'Deposit');
//                    $profit = $gamemoney - $deposit;
//                    $data = [
//                        'PayOut' => $gamemoney,
//                        'Profit' => $profit,
//                        'UpdateTime' => date('Y-m-d H:i:s', time())
//                    ];
//                    $thridgameM->updateByWhere(['orderid' => $order_id], $data);
//                    Redis::rm($keystr);
//                }
//                return true;
//            }
        }
        return false;
    }



    private function createPlayer($roleid, $currency){
        $data = [
            'roleid'=>$roleid,
            'currency'=> $currency
        ];

        $result = $this->httpRequest('newPlayer',$data,'POST');
        if($result)
            return $result['data'];
        else
            return false;
    }


    public function goldTransfer($roleid, $cash_money, $productwallet){
        $data =[
            'roleid'=> $roleid,
            'amount'=> $cash_money,
            'productwaller'=>$productwallet
        ];

        $result = $this->httpRequest('goldTransfer',$data,'POST');
        if($result)
            return $result['data'];
        else
            return false;
    }



    public function  getBalance($roleid, $productwallet){
        $data =[
            'roleid'=> $roleid,
            'productwaller'=>$productwallet
        ];

        $result = $this->httpRequest('goldQuery',$data,'POST');
        if($result)
            return $result['data'];
        else
            return false;
    }


    public function launchGame($roleid, $gameid, $language, $productwallet){
        $data =[
            'roleid'=> $roleid,
            'gameid'=>$gameid,
            'language'=>$language,
            'productwallet'=>$productwallet
        ];

        $result = $this->httpRequest('launchGame',$data,'POST');
        if($result)
            return $result['data'];
        else
            return false;

    }


//    ///启动游戏
//    public function launchGame($roleid, $gameid, $language, $productwallet)
//    {
//        $data = [
//            'MerchantCode' => $this->merchant_code,
//            'PlayerId' => $roleid,
//            'GameCode' => $gameid,
//            'Language' => $language,
//            'ProductWallet' => $productwallet,
//            'IpAddress' => getClientIP(),
//            'Http' => 1,
//        ];
//        $result = $this->httpRequest('Game/NewLaunchMobileGame', $data, 'POST');
//        if ($result) {
//            if (intval($result['Code']) === 0) {
//                return $result['GameUrl'];
//            }
//
//        }
//        return '';
//    }
//

    public function httpRequest($url, $data = array(), $method = 'POST')
    {
        $config = config('imone');
        $url = $config['serverapi'] . $url;
//        $jsonstr = json_encode($data);

        $ch = curl_init();
//        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
//                'Content-Type: application/json; charset=utf-8',
//                'Content-Length: ' . strlen($jsonstr)
//            )
//        );
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        // POST数据
        curl_setopt($ch, CURLOPT_POST, 1);
        // 把post的变量加上
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $res = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_errno($ch);
        curl_close($ch);

        if ($err) {
            return false;
        }
        save_log('lottery', '三方接口返回:' . $res);
        $res = json_decode($res, true);
        if ($httpcode != 200) {
            return false;
        }

        if (intval($res['code']) !=0) {
            return false;
        }
        return $res;
    }


//    ///检查玩家是否存在
//    private function checkExist($playername)
//    {
//        $data = [
//            "MerchantCode" => $this->merchant_code,
//            "PlayerId" => $playername
//        ];
//        $result = $this->httpRequest('Player/CheckExists', $data, 'POST');
//        if ($result == 504) {
//            return true;
//        }
//        return false;
//    }


//    ///创建玩家
//    private function createPlayer($roleid, $currency)
//    {
//        $password = geneal_random_num(8);
//        $data = [
//            'MerchantCode' => $this->merchant_code,
//            'PlayerId' => $roleid,
//            'Currency' => $currency,
//            'Password' => $password
//        ];
//        $result = $this->httpRequest('Player/Register', $data);
//        if ($result) {
//            if (intval($result['Code']) === 0 || intval($result['Code']) == 503)
//                return true;
//        }
//        return false;
//    }


//    ///资金转账  负数为下分
//    private function goldTransfer($roleid, $amount, $productwaller)
//    {
//        $data = [
//            "MerchantCode" => $this->merchant_code,
//            "PlayerId" => $roleid,
//            'ProductWallet' => $productwaller,
//            'TransactionId' => $this->makeOrderId($roleid),
//            'Amount' => $amount
//        ];
//        $result = $this->httpRequest('Transaction/PerformTransfer', $data, 'POST');
//        if ($result) {
//            if (intval($result['Code']) === 0)
//                return $data['TransactionId'];
//        }
//        return false;
//    }


//    ///查询转账交易状态
//    private function checkTransferStatus($roleinfo, $transactionId, $productwaller)
//    {
//        $data = [
//            "MerchantCode" => $this->merchant_code,
//            "PlayerId" => $roleinfo['roleid'],
//            'ProductWallet' => $productwaller,
//            'TransactionId' => $transactionId
//        ];
//        $result = $this->httpRequest('Transaction/CheckTransferStatus', $data, 'POST');
//        if ($result) {
//            if (intval($result['Code']) === 0)
//                return true;
//        }
//        return false;
//    }


//    ///查询玩家余额
//    private function getBalance($roleid, $productwaller)
//    {
//        $data = [
//            "MerchantCode" => $this->merchant_code,
//            "PlayerId" => $roleid,
//            'ProductWallet' => $productwaller
//        ];
//        $result = $this->httpRequest('Player/GetBalance', $data, 'POST');
//        if ($result) {
//            if (intval($result['Code']) === 0)
//                return $result['Balance'];
//        }
//        return false;
//    }


    public function makeOrderId($uid)
    {
        return date('YmdHis') . sprintf('%.0f', floatval(explode(' ', microtime())[0]) * 1000) . $uid;
    }


}