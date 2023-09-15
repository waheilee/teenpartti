<?php

namespace app\admin\controller;

use pay\Pay;
use alipay\Pay as Alipay;

class Withdraw
{
    private $pay;
    private $alipay;


    public function __construct()
    {
        $this->pay = new Pay();
        $this->alipay = new Alipay();
    }

//同略云
    //提现
    public function index()
    {
        if (PHP_SAPI == 'cli') {
            $this->pay->pay();
        } else {
            exit('not cli');
        }
    }


    public function notify()
    {
        $this->pay->notify();
    }

    //支付宝
    public function alipay()
    {
        if (PHP_SAPI == 'cli') {
            $this->alipay->pay();
        } else {
            exit('not cli');
        }

    }

    public function alipaynotify()
    {
        $this->alipay->notify();
    }
}