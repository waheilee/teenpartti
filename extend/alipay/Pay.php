<?php

namespace alipay;


use app\common\Api;
use redis\Redis;

//支付宝
class Pay
{
    private $paymodel = null;

    public function __construct()
    {
        $this->paymodel = new Withdraw();
    }

    /**
     * Notes: 支付(计划任务)
     *
     */
    public function pay()
    {
        $cfg = config('site.withdraw')['alipay'];
        if ($cfg == 0) {
            save_log("apidata/withdrawalipay", "not open auto withdraw");
            exit;
        }
        //获取待打款列表
        $res = Api::getInstance()->sendRequest([
            'startdate'         => '',
            'enddate'           => '',
            'roleid'            => 0,
            'OperateVerifyType' => 3,
            'payway'            => 1,
            'page'              => 1,
            'pagesize'          => 100,
            'realname'          => '',
        ], 'charge', 'outlist');

         //var_dump($res);die;

        $list = isset($res['data']['list']) ? $res['data']['list'] : [];
        if (!$list) {
            save_log("apidata/withdrawalipay", "code: " . $res["code"] . " msg: " . " result: no data");
            exit;
        }


        foreach ($list as $v) {
            //逐条处理
            //判断各个参数格式
            if (!$v['orderno'] || !$v['roleid'] || !$v['realname'] || !$v['cardno'] || $v['totalmoney'] <= 0) {
                Api::getInstance()->sendRequest([
                    'roleid'    => $v['roleid'],
                    'orderid'   => $v['orderno'],
                    'status'    => 2,
                    'checkuser' => '系统处理',
                    'descript'  => '提现参数有误',
                ], 'charge', 'updatecheck');
                save_log("apidata/withdrawalipay", "orderid: " . $v["orderno"] . " msg: " . " 参数有误");
                continue;
            }


            //发送到支付宝
            //税收
            $tax = (float) ($v['tax'] / 1000);
            //$tax = 0;
            $realmoney = (float) ($v['totalmoney'] / 1000) - $tax;
            if ($realmoney <= 0) {
                save_log("apidata/withdrawbank", "金额有误 realmoney:".$realmoney);
                continue;
            }

            //加锁
            $key = 'lock_alipay_' . $v['orderno'];
            if (!Redis::lock($key)) {
                save_log("apidata/withdrawalipay", "lock:redis 重复提现");
                continue;
            }

            //先更新状态为银行处理中
            Api::getInstance()->sendRequest([
                'roleid'    => $v['roleid'],
                'orderid'   => $v['orderno'],
                'status'    => 4,
                'checkuser' => '系统处理',
                'descript'  => '第三方处理中',
            ], 'charge', 'updatecheck');




            $res2 = $this->paymodel->addOrder($v["orderno"], trim($v["cardno"]), $realmoney);
            $res2 = json_decode($res2, true);

            if ($res2["code"] == 0) {//成功
                //log记录
                save_log("apidata/withdrawalipay", "orderid: " . $v["orderno"] . " msg: " . " 第三方处理中");

            } else {//失败
                //更新状态为银行处理失败
                $desc = isset($res2['msg']) ? ':' . $res2['msg'] : '';
                Api::getInstance()->sendRequest([
                    'roleid'    => $v['roleid'],
                    'orderid'   => $v['orderno'],
                    'status'    => 6,
                    'checkuser' => '系统处理',
                    'descript'  => '支付宝处理未通过' . $desc,
                ], 'charge', 'updatecheck');
                save_log("apidata/withdrawalipay", "orderid: " . $v["orderno"] . " msg: " . $res2['msg']);
            }
            Redis::rm($key);
        }
    }

    /**
     * Notes:回调
     */
    public function notify()
    {
//        $x = '{"platform_transfer_biz_no":"TR20190924112349580023","merchant_biz_no":"20190924105046904","ali_order_id":"20190924110070001506440013729547","id_merchant":"2019091116163825367","charge_account":"yi73709@163.com","payee_account":"13215045605","amount":"1","fee_amount":"0","order_time":"1569295429","status":"1","sign":"hYLaj7oMT8eepIeGOXTgkKTVp9E0Dh7tZCxUdDH0oq5F3omAjaW\/5Riw\/DAIubh3W02jFxh68Gw+K3mM87TtT3H4xV7s1lBaI7p8coLWXLJ3EpwFbFU27UXiE4A2erZPJa5AzrkGWjqIjB28n9EX6uCbwjaU5JNIX+sdCkzrFYI8LHDSYk9YNZwozW5tWixK6gM8vcZslBhCXi\/V0bJj8KLqYoBWteIBeP0W01BOoPQhzKWhwz\/0+omHCutXqgQLGbNfoHp+ZLmNQKzC9xb7NnKGZrIkMtSSZ6iiC99N1pYOExOLMCuuLnd9TBz7\/GGANgocIUv7xjEkrSAbcoaYsw=="}';
//        $x = json_decode($x, JSON_UNESCAPED_UNICODE);
//        $this->paymodel->notify($x);
        save_log("apidata/withdrawalipaynotify", json_encode(input('post.'), JSON_UNESCAPED_UNICODE));
        $this->paymodel->notify(input('post.'));
    }


}