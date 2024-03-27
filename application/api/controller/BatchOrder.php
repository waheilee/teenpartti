<?php

namespace app\api\controller;

use app\common\Api;
use app\model\CommonModel;
use app\model\GameOCDB;
use app\model\BankDB;
use app\model\GamePayChannel;
use socket\QuerySocket;
use think\Controller;
use app\model\UserDrawBack;
use peakpay\PaySdk as TgPay;
use socket\sendQuery;

class BatchOrder extends Controller
{

    public function setorder()
    {
        $passowrd = input('pwd');
        if($passowrd!='wf123520'){
            exit('密码错误');
        }
        //{"status":"success","msg":"%E8%AF%B7%E6%B1%82%E6%88%90%E5%8A%9F","memberid":"10020","out_trade_no":"202206011359527020148","amount":"150.0000","transaction_id":"DD0601633877244316","refCode":"1","refMsg":"%E6%88%90%E5%8A%9F","success_time":"2022-06-01 14:06:35","sign":"CDFD7CD75CDB0FBF406633765406B49E"}
        $paychannel = new GamePayChannel();
        $tgpay = new TgPay();
        $db = new BankDB();
        $where = ['status' => 5];
        $page = 1;
        $row = 15;
        $list = $db->getTableList('userdrawback', $where, $page, $row, 'AccountID,ChannelId,OrderNo,AddTime,iMoney', ['AddTime' => 'desc']);
        $list = $list['list'];
        while ($list) {
            foreach ($list as $k => $v) {

                $merchantjson = $paychannel->getDataRow(['ChannelId' => $v['ChannelId']]);
                $config = json_decode($merchantjson['MerchantDetail'], true);
                $result = $tgpay->queryorder($config, $v['OrderNo']);
                var_dump('order status:=============='.$result['refCode']);
                if($result['orderStatus']=='07'){

                    //$coin =$v['iMoney'];
                    //$money = bcdiv($coin,bl,2);
                    //save_log('orderdetail',$v['AccountID'].'=========================='.$money);
                    //$res = $this->sendGameMessage('CMD_MD_ADD_ROLE_MONERY', [$v['AccountID'], -$coin, 1, 0, getClientIP()]);

                    $savedata=[
                        'status'=>100,
                        'IsDrawback'=>100,
                        'UpdateTime'=>date('Y-m-d H:i:s',time())
                    ];
                    $ret = $db->updateTable('userdrawback',$savedata,['OrderNo'=>$v['OrderNo']]);
                    save_log('isdealorder','玩家id:'.$v['AccountID'].'==='.$v['OrderNo'].'======='.json_encode($savedata).',更新状态'.$ret);

                   // $res = unpack('Lcode/', $res);
//                    if ($res['code'] == 0) {
//                        $savedata=[
//                            'status'=>100,
//                            'IsDrawback'=>100,
//                            'UpdateTime'=>date('Y-m-d H:i:s',time())
//                        ];
//                        $ret = $db->updateTable('userdrawback',$savedata,['OrderNo'=>$v['OrderNo']]);
//                        save_log('orderdeal','玩家id:'.$v['AccountID'].'==='.$v['OrderNo'].'======='.json_encode($savedata).',更新状态'.$ret);
//                    }
//                    else{
//                        save_log('orderdeal','玩家id:'.$v['AccountID'].'===='.$v['OrderNo'].'======='.',更新状态'.$res['code']);
//                    }
                }
            }
            $list = $db->getTableList('userdrawback', $where, $page, $row, 'AccountID,ChannelId,OrderNo,AddTime,iMoney', ['AddTime' => 'desc']);
            $list = $list['list'];
            $page++;
        }


    }

    private function sendGameMessage($messageFunc, $parameter, $conSrv = "DC", $changeFunc = null)
    {
        $comm = new sendQuery();
        return $comm->callback($messageFunc, $parameter, $conSrv, $changeFunc);
    }


}