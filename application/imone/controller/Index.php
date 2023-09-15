<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/21
 * Time: 11:08
 */

namespace app\imone\controller;

use think\Exception;

class Index extends Base{

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




    public function launchGame($roleid, $gameid, $language, $productwallet){
        try{
            $check = $this->checkIp();
            if(!$check)
                return $this->errorinfo('Unauthorized access');

            $result = $this->launchThirdGame($roleid, $gameid, $language, $productwallet);
            return $this->apiReturn(0, $result, 'success');
        }
        catch (Exception $ex){
            save_log('imone',$ex->getLine().$ex->getMessage().$ex->getTraceAsString());
            return $this->apiReturn(100, false, 'success');
        }
    }



    ///创建玩家
    public function newPlayer($roleid,$currency){
        try {
            $check = $this->checkIp();
            if(!$check)
                return $this->errorinfo('Unauthorized access');

            if (empty($currency))
                $currency = "THB";
            $exist_roleid = $this->checkExist($roleid);
            $result = true;
            if(!$exist_roleid){
                $result = $this->createPlayer($roleid, $currency);
            }
            return $this->apiReturn(0, $result, 'success');
        }
        catch (Exception $ex){
            save_log('imone',$ex->getLine().$ex->getMessage().$ex->getTraceAsString());
            return $this->apiReturn(100, false, 'success');
        }

    }

    //上下分
    public function goldTransfer($roleid, $amount, $productwaller){
        try{
            $check = $this->checkIp();
            if(!$check)
                return $this->errorinfo('Unauthorized access');

            $result = $this->goldThirdTransfer($roleid, $amount, $productwaller);
            $code =100;
            if($result){
                $code =0;
            }
            return $this->apiReturn($code,$result,'success');
        }
        catch (Exception $ex){
            save_log('imone',$ex->getLine().$ex->getMessage().$ex->getTraceAsString());
            return $this->apiReturn(100, false, 'success');
        }
    }


    ///查询
    public function goldQuery($roleid,$productwaller){
        try{
            $check = $this->checkIp();
            if(!$check)
                return $this->errorinfo('Unauthorized access');

            $result = $this->getBalance($roleid, $productwaller);
            $code =100;
            if($result)
                $code =0;
            return $this->apiReturn($code,$result,'success');
        }
        catch (Exception $ex){
            save_log('imone',$ex->getLine().$ex->getMessage().$ex->getTraceAsString());
            return $this->apiReturn(100, false, 'success');
        }

    }




    ///启动游戏
    private function launchThirdGame($roleid, $gameid, $language, $productwallet)
    {
        $data = [
            'MerchantCode' => $this->merchant_code,
            'PlayerId' => $roleid,
            'GameCode' => $gameid,
            'Language' => $language,
            'ProductWallet' => $productwallet,
            'IpAddress' => getClientIP(),
            'Http' => 1,
        ];
        $result = $this->httpRequest('Game/NewLaunchMobileGame', $data, 'POST');
        if ($result) {
            if (intval($result['Code']) === 0) {
                return $result['GameUrl'];
            }

        }
        return false;
    }

    ///检查玩家是否存在
    private function checkExist($roleid)
    {
        $data = [
            "MerchantCode" => $this->merchant_code,
            "PlayerId" => $roleid
        ];
        $result = $this->httpRequest('Player/CheckExists', $data, 'POST');
        if (intval($result) === 504) {
            return false;
        }
        return true;
    }


    ///创建玩家
    private function createPlayer($roleid, $currency)
    {
        $password = geneal_random_num(8);
        $data = [
            'MerchantCode' => $this->merchant_code,
            'PlayerId' => $roleid,
            'Currency' => $currency,
            'Password' => $password
        ];
        $result = $this->httpRequest('Player/Register', $data);
        if ($result) {
            if (intval($result['Code']) === 0)
                return true;
        }
        return false;
    }


    ///资金转账  负数为下分
    private function goldThirdTransfer($roleid, $amount, $productwaller)
    {
        $data = [
            "MerchantCode" => $this->merchant_code,
            "PlayerId" => $roleid,
            'ProductWallet' => $productwaller,
            'TransactionId' => $this->makeOrderId($roleid),
            'Amount' => $amount
        ];
        $result = $this->httpRequest('Transaction/PerformTransfer', $data, 'POST');
        if ($result) {
            if (intval($result['Code']) === 0)
                return $data['TransactionId'];
        }
        return false;
    }


    ///查询转账交易状态
    private function checkTransferStatus($roleinfo, $transactionId, $productwaller)
    {
        $data = [
            "MerchantCode" => $this->merchant_code,
            "PlayerId" => $roleinfo['roleid'],
            'ProductWallet' => $productwaller,
            'TransactionId' => $transactionId
        ];
        $result = $this->httpRequest('Transaction/CheckTransferStatus', $data, 'POST');
        if ($result) {
            if (intval($result['Code']) === 0)
                return true;
        }
        return false;
    }


    ///查询玩家余额
    private function getBalance($roleid, $productwaller)
    {
        $data = [
            "MerchantCode" => $this->merchant_code,
            "PlayerId" => $roleid,
            'ProductWallet' => $productwaller
        ];
        $result = $this->httpRequest('Player/GetBalance', $data, 'POST');
        if ($result) {
            if (intval($result['Code']) === 0)
                return $result['Balance'];
        }
        return false;
    }


    private function makeOrderId($uid)
    {
        return date('YmdHis') . sprintf('%.0f', floatval(explode(' ', microtime())[0]) * 1000) . $uid;
    }



    private function httpRequest($url, $data = array(), $method = 'POST')
    {
        $config = config('lottery');
        $url = $config['ApiUrl'] . $url;
        $jsonstr = json_encode($data);
        save_log('imone','发送数据：'.$jsonstr);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json; charset=utf-8',
                'Content-Length: ' . strlen($jsonstr)
            )
        );
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        // POST数据
        curl_setopt($ch, CURLOPT_POST, 1);
        // 把post的变量加上
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonstr);
        $res = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_errno($ch);
        curl_close($ch);

        if ($err) {
            return false;
        }
        save_log('imone', '三方接口返回:' . $res);
        $res = json_decode($res, true);
        if ($httpcode != 200) {
            return false;
        }

        if (intval($res['Code']) > 0 && intval($res['Code']) !=504) {
            return false;
        }
        return $res;
    }


}