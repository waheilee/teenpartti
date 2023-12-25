<?php

namespace app\admin\controller;

use app\common\GameLog;

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
        $request = request()->request();
        $res = $this->synconfig();
        if ($res == 0) {
            GameLog::logData(__METHOD__, ['action' => lang('后台同步'), $request]);
            return $this->apiReturn(0, [], '同步成功');
        } else {
            return $this->apiReturn(1, [], '同步失败');
        }
    }
}
