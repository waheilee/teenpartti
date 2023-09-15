<?php

namespace pay;


use app\common\Api;
use redis\Redis;

//同略云
class Pay
{
    private $paymodel = null;

    public function __construct()
    {
        $this->paymodel = new WithdrawBank();
    }

    /**
     * Notes: 支付
     *
     */
    public function pay()
    {
        //获取待打款列表
        $res = Api::getInstance()->sendRequest([
            'startdate'         => '',
            'enddate'           => '',
            'roleid'            => 0,
            'OperateVerifyType' => 3,
            'payway'            => 2,
            'page'              => 1,
            'pagesize'          => 1000,
            'realname'          => '',
        ], 'charge', 'outlist');

         //var_dump($res);die;
    

        $list = isset($res['data']['list']) ? $res['data']['list'] : [];
        if (!$list) {
            save_log("apidata/withdrawbank", "code: " . $res["code"] . " msg: " . $res["message"] . " result: no data");
            exit;
        }


        foreach ($list as $v) {
            //逐条处理

            //判断各个参数格式
            if (!$v['orderno'] || !$v['roleid'] || !$v['realname'] || !$v['bankcode'] || !$v['cardno'] || $v['totalmoney'] <= 0) {
                Api::getInstance()->sendRequest([
                    'roleid'    => $v['roleid'],
                    'orderid'   => $v['orderno'],
                    'status'    => 2,
                    'checkuser' => '系统处理',
                    'descript'  => '提现参数有误',
                ], 'charge', 'updatecheck');
                save_log("apidata/withdrawbank", "orderid: " . $v["orderno"] . " msg: "  . " 参数有误");
                continue;
            }

            //发送到银行
            //税收
            $tax = (float) ($v['tax'] / 1000);
            //$tax = 0;
            $realmoney = (float) ($v['totalmoney'] / 1000) - $tax;

            if ($realmoney <= 0) {
                save_log("apidata/withdrawbank", "金额有误 realmoney:".$realmoney);
                continue;
            }

            //先更新状态为银行处理中
            Api::getInstance()->sendRequest([
                'roleid'    => $v['roleid'],
                'orderid'   => $v['orderno'],
                'status'    => 4,
                'checkuser' => '系统处理',
                'descript'  => '银行处理中',
            ], 'charge', 'updatecheck');



            $res1 = $this->paymodel->addCard(trim($v["cardno"]), trim($v["realname"]), $v["bankcode"], '福州', '福建'); //新增银行卡
            $res1 = json_decode($res1,true);

            $res2 = $this->paymodel->addOrder(trim($v["cardno"]), $realmoney, trim($v["realname"]), $v["orderno"]);
            $res2 = json_decode($res2, true);
    
        
    
            if ($res2["success"] == true) {//成功
                //log记录
        
                save_log("apidata/withdrawbank", "orderid: " . $v["orderno"] . " msg: " .  " 银行处理中");
        
            } else {//失败
                //更新状态为银行处理失败
                Api::getInstance()->sendRequest([
                    'roleid'    => $v['roleid'],
                    'orderid'   => $v['orderno'],
                    'status'    => 6,
                    'checkuser' => '系统处理',
                    'descript'  => '银行处理未通过',
                ], 'charge', 'updatecheck');

                save_log("apidata/withdrawbank", "orderid: " . $v["orderno"] . " msg: ". $res2['message']);
        
            }
        }
    }

    /**
     * Notes:回调
     */
    public function notify()
    {
        $get = file_get_contents("php://input");
        save_log("apidata/withdrawnotify", $get);
        $header = $_SERVER["HTTP_TLYHMAC"];
        save_log("apidata/withdrawnotify", "header:" . $header);
        $receive = json_decode($get, true);
        $this->paymodel->notify($receive, $header, file_get_contents("php://input"));
    }


}