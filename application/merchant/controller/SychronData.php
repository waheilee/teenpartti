<?php

namespace app\merchant\controller;

class SychronData extends Main
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 首页
     */
    public function index()
    {
        $res = $this->synconfig();
        if ($res == 0) {
            return $this->apiReturn(0, [], '同步成功');
        } else {
            return $this->apiReturn(1, [], '同步失败');
        }
    }
}
