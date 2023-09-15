<?php

namespace app\tggame\controller;

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


    //游戏对外接口创建玩家
    public function createuser()
    {
        try {
            $param = jsonRequest(['roleid', 'gameid', 'language', 'time', 'sign']);
            $clientkey = config('clientkey');
            $key = md5($param['roleid'] . $param['gameid'] . $param['language'] . $param['time'] . $clientkey);
            if ($key != strtolower($param['sign'])) {
                return $this->failjson('sign is error');
            }

            $roleid = $param['roleid'];
            $accountM = new AccountDB();
            $user = $accountM->getRow(['AccountID' => $roleid], 'AccountID,AccountName');
            if (!$user) {
                return $this->failjson('the player is not exist');
            }
            $result = $this->createPlayer($user, $param);
            save_log('tggame', '第三方玩家创建===' . json_encode($result));
            if ($result) {
                $ret = $this->upscore($user);
                if ($ret) {
                    return $this->succjson( $result['url']);
                } else {
                    return $this->failjson('upscore error');
                }
            }
            return $this->failjson('create play faild');
        } catch (Exception $ex) {
            save_log('tggame', $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjson('api error');
        }
    }


    //游戏对外接口下分
    public function downscore()
    {
        try {
            $param = jsonRequest(['roleid', 'time', 'sign']);
            $clientkey = config('clientkey');
            $key = md5($param['roleid'] . $param['time'] . $clientkey);
            if ($key != $param['sign']) {
                return $this->apiReturn(100, '', 'The Sign is check error');
            }

            $roleid = $param['roleid'];
            $accountM = new AccountDB();
            $user = $accountM->getRow(['AccountID' => $roleid], 'AccountID,AccountName');
            if (!$user) {
                return $this->failjson('the player is not exist');
            }
            $result = $this->doDownScore($user);
            if ($result)
                return $this->succjson( '');
            return $this->failjson('Api Error');
        } catch (Exception $ex) {
            save_log('tggame', $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return $this->failjson('Api Error');
        }

    }

    //////////////////////////////////////////////////////////////////////////////////////
    ///以下部分为内部调用
    //////////////////////////////////////////////////////////////////////////////////////
    //游戏内部上分
    private function upscore($user)
    {
        try {
            $roleid = $user['AccountID'];
            $socket = new QuerySocket();
            $m = $socket->getPlayerBalance($roleid);
            if ($m['iResult']) {
                return $this->failjson('余额查询错误');
            }
            $gamemoney = $m['iGameMoney'];
            save_log('tggame', '查询玩家余额：' . $gamemoney);
            $balance = bcdiv($gamemoney, bl, 3);
            if ($balance > 0) {
                $result = $this->goldTransfer(1,$user, $balance);
                save_log('tggame', 'lotter 上分：' . $roleid . '===状态：' . json_encode($result));
                if ($result) {
                    $state = $socket->UpLotteryScore(1, $roleid, $gamemoney, $result['transaction_id']);
                    save_log('tggame', '游戏服通知刷分返回===玩家' . $roleid . '===状态：' . json_encode($state));
                    return true;
                } else {
                    $socket->UpLotteryScore(0, $roleid, $gamemoney, 'Lessthen100');
                    return false;
                }
            }
            return true;
        } catch (Exception $ex) {
            save_log('tggame', $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return 0;
        }
    }






    //玩家下分
    private function doDownScore($user)
    {
        $roleid=$user['AccountID'];
        $result = $this->getBalance($user);
        if(!$result)
            return false;

        $gold = floatval($result['amount']);
        $balance = sprintf('%.3f', $gold);
        if ($balance > 0) {
            $result = $this->goldTransfer(2, $user, $balance);
            save_log('tggame', '第三方下分===' . $balance . '===' . json_encode($result));
            if ($result) {
                $socket = new QuerySocket();
                $downscore =bcmul($balance,bl,0);
                $res = $socket->downLotteryScore($roleid, $downscore, $result['transaction_id']);
                save_log('tggame', '游服上分===' . $roleid . '===' . json_encode($res));
                if ($res['iResult'] == 0) {
                    save_log('tggame',
                        '下分成功' . $roleid . '===' . json_encode($res['iResult']) .',money:' .  $balance * 1000);
                    return true;
                }
            }
            else
            {
                save_log('tggame',
                    '下分失败' . $roleid . '===' . json_encode($result) . ',money:' . $balance * 1000);
                return false;
            }
        }
        return true;
    }


    //创建玩家
    private function createPlayer($user, $param)
    {
        $signstr = $user['AccountName'] . $this->operator_id;
        $newsign = $this->createsign($signstr);
        $data = [
            'operator_account' => $user['AccountName'],
            'operator_id' => $this->operator_id,
            'room_id' => $param['gameid'],
            'lang' => $param['language'],
            'sign' => $newsign
        ];
        $result = $this->httpRequest('/Player/GetBaseInfo', $data, 'POST');
        if ($result) {
            if (empty($result['error'])) {
                $token = $result['data']['platform_token'];
                $key = 'tggame:platetoken:' . $user['AccountID'];
                Redis::set($key, $token);
                return $result['data'];
            }
        } else
            return false;
    }

    ///玩家上下分
    private function goldTransfer($type,$user, $balance)
    {
        $roleid = $user['AccountID'];
        $key = 'tggame:platetoken:' . $user['AccountID'];
        $platform_toke = Redis::get($key);
        $transferid = $this->makeOrderId($roleid);
        if (empty($platform_toke)) {
            return false;
        }

        $signstr = $user['AccountName'] . $this->operator_id . $platform_toke . $balance . $transferid;
        $newsign = $this->createsign($signstr);
        $data = [
            'operator_account' => $user['AccountName'],
            'operator_id' => $this->operator_id,
            'platform_token' => $platform_toke,
            'amount' => $balance,
            'transfer_reference' => $transferid,
            'sign' => $newsign
        ];
        save_log('tggame',
            '充值json' . $roleid . '===' . json_encode($data));
        $url = $type==1?'/cash/transferin':'/cash/transferout';
        $result = $this->httpRequest($url, $data, 'POST');
        if (!$result)
            return false;

        return $result['data'];
    }


    //获取玩家账号余额
    private function getBalance($user)
    {
        $signstr = $user['AccountName'].$this->operator_id;
        $newsign = $this->createsign($signstr);
        $data = [
            'operator_account' => $user['AccountName'],
            'operator_id' => $this->operator_id,
            'sign'=>$newsign
        ];

        $result = $this->httpRequest('/Player/GetInfo', $data, 'POST');
        if ($result)
            return $result['data'];
        else
            return false;
    }


    //发送http请求
    private function httpRequest($url, $data = array(), $method = 'POST')
    {
        $config = config('tggame');
        $url = $config['apiurl'] . $url;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/x-www-form-urlencoded; charset=utf-8'
            )
        );
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $res = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_errno($ch);
        curl_close($ch);

        if ($err) {
            return false;
        }
        save_log('tggame', '三方接口返回:' . $res);
        $res = json_decode($res, true);
        if ($httpcode != 200) {
            return false;
        }

        if (!empty($res['error'])) {
            return false;
        }
        return $res;
    }


    //签名函数
    private function createsign($signstr)
    {
        return strtolower(md5($signstr.$this->securykey));
    }


    //创建临时订单
    private function makeOrderId($uid)
    {
        return date('YmdHis') . sprintf('%.0f', floatval(explode(' ', microtime())[0]) * 1000) . $uid;
    }


}