<?php

namespace app\qwan\controller;

use app\model\AccountDB;
use app\model\Role;
use app\model\UserFishScoreLog;
use app\model\ThirdGameReport;
use redis\Redis;
use socket\QuerySocket;
use think\Controller;
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


    //账号验证
    public function account_auth()
    {
        try {
            $account = input('account', '');
            $token = input('token', '');
            $time = input('time', '');
            $sign = input('sign', '');
            save_log('qwan', 'auth post:' . json_encode($this->request->param()));
            if (empty($account) || empty($token) || empty($time) || empty($sign)) {
                return $this->errorinfo('参数错误');
            }

            $strmd5 = $account . $token . $time;
            if (!$this->checksign($strmd5, $sign)) {
                return $this->errorinfo('非法访问');
            }

            $userdata = Redis::getInstance()->get($token);
            if (empty($userdata)) {
                return $this->errorinfo('令牌错误');
            }


            $Account = new AccountDB();
            $detail = $Account->TAccounts()->GetRow(['accountid' => $account], '*');
            if (empty($detail)) {
                return $this->errorinfo('玩家账号不存在');
            }


            $roleM = new Role('t_role');
            $roleM = $roleM->getRow(['roleid' => $account], 'loginname,faceid');

            $socket = new QuerySocket();
            $m = $socket->DSQueryRoleBalance($account);
            $balance = intval($m['iGameWealth']/10)*10;
            $retdata = [
                'code' => 0,
                'msg' => 'success',
                'account' => $account,
                'nick' => $roleM['loginname'],
                'icon' => $this->headurl . 'img' . $roleM['faceid'] . '.png',
                'wealth' => $balance
            ];
            save_log('qwan', 'auth return:' . json_encode($retdata));
            return json($retdata);
        } catch (Exception $ex) {
            save_log('qwan', $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return json(['code' => 102, 'error']);
        }
    }

    //兑入
    public function exchange()
    {
        try {

            save_log('qwan', 'exchange Data:' . json_encode($this->request->param()));
            $account = input('account', '');
            $uin = intval(input('uin')) ? intval(input('uin')) : 0;
            $orderid = input('orderid', '');
            $coin = intval(input('coin')) ? intval(input('coin')) : 0;
            $chip = intval(input('chip')) ? intval(input('chip')) : 0;

            $token = input('token', '');
            $time = input('time', '');
            $sign = input('sign', '');

            if (empty($account) || empty($token) || empty($time) || empty($sign) || empty($orderid) || $uin == 0) {
                return $this->errorinfo('参数错误');
            }

            $userinfo = [];
            $strmd5 = $account . $token . $uin . $orderid . $coin . $chip . $time;
            if (!$this->checksign($strmd5, $sign)) {
                return $this->errorinfo('非法访问');
            } else {
                $userinfo = json_decode(Redis::getInstance()->get($token), true);
            }

            if (empty($userinfo)) {
                return $this->errorinfo('令牌错误');
            }


            $socket = new QuerySocket();
            $m = $socket->DSQueryRoleBalance($account);
            $balance = intval($m['iGameWealth']/10)*10;

            if($coin!=$balance){
                return $this->errorinfo('金额不匹配');
            }


            $UserFishScoreLogM = new UserFishScoreLog();
            $total = $UserFishScoreLogM->getCount(['orderid' => $orderid]);
            if ($total == 0) {
                $data = [
                    'Orderid' => $orderid,
                    'TypeId' => 1,
                    'AccountName' => $account,
                    'Coin' => $coin,
                    'Chip' => $chip,
                    'Wealth' => $balance - $coin,
                    'AddTime' => date('Y-m-d H:i:s', time())
                ];
                $ret = $UserFishScoreLogM->add($data);
                save_log('qwan', 'add data:' . '添加明细完成' . $ret);
                if ($ret > 0) {
                    $keystr = 'FishScore' . $userinfo['AccountID'];
                    if (Redis::has($keystr)) {
                        save_log('qwan', 'add data:' . '存在redis key');
                        if ($coin > 0) {
                            $thridgameM = new ThirdGameReport();
                            $order_id = Redis::get($keystr);
                            $deposit = $thridgameM->getValue(['OrderId' => $order_id], 'Deposit');
                            $thridgameM->updateByWhere(['orderid' => $order_id], ['Deposit' => $deposit + $coin]);
                        }
                    } else {
                        save_log('qwan', 'add data:' . '进入添加当天记录');
                        Redis::set($keystr, $orderid);
                        $thridgameM = new ThirdGameReport();
                        $data = [
                            'OrderId' => $orderid,
                            'GameType' => 10,
                            'RoleId' => $userinfo['AccountID'],
                            'NickName' => $userinfo['nickname'],
                            'Deposit' => $coin,
                            'PayOut' => 0,
                            'AddTime' => date('Y-m-d H:i:s', time())
                        ];
                        $thridgameM->add($data);
                    }
                } else {
                    return $this->errorinfo('写入订单错误');
                }
            }

            $result = $socket->getPlayerBalance($account);
            //{"iResult":0,"iRoleID":20558019,"iGameMoneyL32":310090,"iGameMoneyH32":0,"iGameMoney":310090}
            if($result['iResult']!=0 || $result['iRoleID']==0){
                return $this->errorinfo('扣除分數異常');
            }
            $balance_new =$result['iGameMoney'];
            $retdata = [
                'code' => 0,
                'msg' => 'success',
                'uin' => $uin,
                'orderid' => $orderid,
                'wealth' => 0,
                'coin' => $balance_new,
                'chip' => $balance_new
            ];

            if($balance_new>$balance){

                $downscore = $balance_new-$balance;
                //返回分数
                save_log('qwan', '上分和扣除分数不一致，返还差额:' . $downscore);
                $socket->downFishScore($account,$downscore,$orderid);
            }

            save_log('qwan', 'exchage return data:' . json_encode($retdata));
            return json($retdata);
        } catch (Exception $ex) {
            save_log('qwan', $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return json(['code' => 102, 'error']);
        }
    }


    //兑出
    public function exchangeout()
    {
        try {
            save_log('qwan', 'exchangeout:' . json_encode($this->request->param()));
            $account = input('account', '');
            $uin = intval(input('uin')) ? intval(input('uin')) : 0;
            $orderid = input('orderid', '');
            $total_coin = input('total_coin', '');
            $coin = intval(input('coin')) ? intval(input('coin')) : 0;
            $chip = intval(input('chip')) ? intval(input('chip')) : 0;

            $token = input('token', '');
            $time = input('time', '');
            $sign = input('sign', '');


            if (empty($account) || empty($token) || empty($time) || empty($sign) || empty($orderid) || $uin == 0) {
                return $this->errorinfo('参数错误');
            }

            $userinfo = [];
            $strmd5 = $account . $token . $uin . $orderid . $total_coin . $coin . $chip . $time;
            if (!$this->checksign($strmd5, $sign)) {
                return $this->errorinfo('非法访问');
            } else {
                $userinfo = json_decode(Redis::getInstance()->get($token), true);
            }
            //$userdata = Redis::get($token);
            if (empty($userinfo)) {
                return $this->errorinfo('令牌错误');
            }

            $socket = new QuerySocket();
            $m = $socket->DSQueryRoleBalance($account);
            $gamemoney = intval($m['iGameWealth']/10)*10;

            $UserFishScoreLogM = new UserFishScoreLog();
            $total = $UserFishScoreLogM->getCount(['orderid' => $orderid]);
            if ($total == 0) {
                $data = [
                    'Orderid' => $orderid,
                    'TypeId' => 2,
                    'AccountName' => $account,
                    'Coin' => $coin,
                    'Chip' => $chip,
                    'Wealth' =>  $gamemoney+$coin,
                    'AddTime' => date('Y-m-d H:i:s', time())
                ];
                $ret = $UserFishScoreLogM->add($data);
                if ($ret) {

                    $keystr = 'FishScore' . $userinfo['AccountID'];
                    if (Redis::has($keystr)) {
                        save_log('qwan', 'add data:' . '存在redis key');
                        $thridgameM = new ThirdGameReport();
                        $order_id = Redis::get($keystr);
                        $deposit = $thridgameM->getValue(['OrderId' => $order_id], 'Deposit');
                        $profit = $coin - $deposit;
                        $data = [
                            'PayOut' => $coin,
                            'Profit' => $profit,
                            'UpdateTime' => date('Y-m-d H:i:s', time())
                        ];
                        $ret=$thridgameM->updateByWhere(['orderid' => $order_id], $data);
                        save_log('qwan', 'add data:' . '更新当天记录完成'.$ret);
                        Redis::rm($keystr);
                        Redis::rm('qwan_exchange_score'.$account);
                        save_log('qwan', 'add data:' . '移除reids key');
                    }
                } else {
                    return $this->errorinfo('写入订单错误');
                }
            }

            $result = $socket->downFishScore($userinfo['AccountID'], $coin, $orderid);
            save_log('qwan', 'exchange out socket:' . json_encode($result));

            $retdata = [
                'code' => 0,
                'msg' => 'success',
                'uin' => $uin,
                'orderid' => $orderid,
                'wealth' => $gamemoney + $coin,
                'coin' => 0,
                'chip' => 0
            ];
            if ($total_coin) {
                $retdata['total_coin'] = $total_coin;
            }
            save_log('qwan', 'exchange out return:' . json_encode($retdata));
            return json($retdata);
        } catch (Exception $ex) {
            save_log('qwan', $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return json(['code' => 102, 'error']);
        }
    }


    ///订单状态通知
    public function exchangeresult()
    {
        try {
            $account = input('account', '');
            $uin = intval(input('uin')) ? intval(input('uin')) : 0;
            $orderid = input('orderid', '');
            $time = input('time', '');
            $token = input('token', '');
            $status = input('status', '');
            $msg = input('msg', '');
            $sign = input('sign', '');

            if (empty($account) || empty($token) || empty($msg) || empty($time) || empty($sign) || empty($orderid) || $uin == 0) {
                return json(['code' => 101, '参数错误']);
            }
            $userinfo = [];
            $strmd5 = $account . $token . $uin . $orderid . $time . $status . $msg;
            if (!$this->checksign($strmd5, $sign)) {
                return json(['code' => 101, '非法访问 ']);
            } else {
                $userinfo = json_decode(Redis::getInstance()->get($token), true);
            }

            if (empty($userinfo)) {
                return json(['code' => 102, '令牌错误']);
            }

            $UserFishScoreLogM = new UserFishScoreLog();
            $detail = $UserFishScoreLogM->getRow(['Orderid' => $orderid], '*');
            $retdata = [
                'code' => 0,
                'msg' => 'success',
                'uin' => $uin,
                'orderid' => $orderid
            ];
            return json($retdata);
        } catch (Exception $ex) {
            save_log('qwan', $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return json(['code' => 102, 'error']);
        }
    }


    ///订单
    public function order()
    {
        try {
            $account = input('account', '');
            $uin = intval(input('uin')) ? intval(input('uin')) : 0;
            $orderid = input('orderid', '');
            $time = input('time', '');
            $token = input('token', '');
            $sign = input('sign', '');

            if (empty($account) || empty($token) || empty($time) || empty($sign) || empty($orderid) || $uin == 0) {
                return json(['code' => 101, '参数错误']);
            }
            $userinfo = [];
            $strmd5 = $account . $token . $uin . $orderid . $time;
            if (!$this->checksign($strmd5, $sign)) {
                return json(['code' => 102, '非法访问 ']);
            } else {
                $userinfo = json_decode(Redis::getInstance()->get($token), true);
            }

            $UserFishScoreLogM = new UserFishScoreLog();
            $detail = $UserFishScoreLogM->getRow(['Orderid' => $orderid], '*');
            if (empty($detail)) {
                return json(['code' => 103, '订单不存在']);
            }
            $retdata = [
                'code' => 0,
                'msg' => 'success',
                'uin' => $uin,
                'orderid' => $orderid,
                'coin' => 0,//$detail['coin'],
                'chip' => 0,//$detail['chip'],
                'wealth' => $detail['wealth'],
            ];
            return json($retdata);
        } catch (Exception $ex) {
            save_log('qwan', $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return json(['code' => 102, 'error']);
        }

    }

    ///http://test.h5.quwangame.com/Haiwai/f10/fish/index.html?uid=4473&token=1300379&ncode=%E5%B0%8F%E8%9C%9C%E8%9C%82&imei=af05af05af05&os=ios&timestamp=23243423&sign=543535353353453
    ///启动游戏
    /// 中文：zh-cn
    //英文：en-us
    //越南文：vi-vn
    //泰文：th-th
    public function lanchgame()
    {
        try {
            $param = jsonRequest(['roleid', 'nickname', 'lang', 'time', 'sign']);
            $account = $param['roleid'];
            $ncode = $param['nickname'];
            $lang = $param['lang'];
            $time = $param['time'];
            $sign = $param['sign'];

            if (empty($time) || empty($sign) || $account == 0) {
                return $this->errorinfo('参数错误');
            }

            $newsign = md5($account . $time . config('clientkey'));
            if ($newsign != $sign) {
                return $this->errorinfo('非法访问');
            }

//           $accountM = new Account();
//           $user = $accountM->getRow(['accountid' => $account], '*');
            $Account = new AccountDB();
            $user = $Account->GetRow(['accountid' => $account], '*');
            if (!$user) {
                return $this->errorinfo('玩家不存在');
            }
            $user['nickname'] = $ncode;

            $language = 'zh-cn';
            switch ($lang) {
                case 'cn':
                    $language = 'zh-cn';
                    break;
                case 'th':
                    $language = 'th-th';
                    break;
                case 'en':
                    $language = 'en-us';
                    break;
            }

            $jsondata = json_encode($user);
            $token = md5(random_str(18));
            Redis::getInstance()->set($token, $jsondata, 3600 * 24);
            $game_url = $this->gameurl;
            $parameter = '?account=%s&token=%s&ncode=%s&os=ios&lang=%s';
            $parameter = sprintf($parameter, $account, $token, urlencode($ncode), $language);
            save_log('qwan', 'url:' . $game_url . $parameter);
            return json(['code' => 0, 'data' => $game_url . $parameter]);
        } catch (Exception $ex) {
            save_log('qwan', $ex->getMessage() . $ex->getTraceAsString() . $ex->getLine());
            return json(['code' => 102, 'system error']);
        }

    }


    private function checksign($strmd5, $sign)
    {
        $key = $this->key;
        $newsign = md5($strmd5 . $key);
        return $newsign == $sign ? true : false;
    }





}