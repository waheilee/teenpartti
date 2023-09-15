<?php

namespace app\payment\controller;
use think\Controller;

class Main extends Controller
{

    public $loginid;
    public $loginname;
    public $username;
    public $laypath;
    /**
     * 初始化
     */
    public function _initialize()
    {
        $this->loginid  = session('loginid');
        $this->loginname  = session('loginname');
        $laypath ="/public/layuiAdmin/layuiadmin";
        $curr_lang = cookie('think_var');

        $date_lang ='';
        switch ($curr_lang){
            case "en-us":
                $laypath ="/public/layuiAdmin/layuiadmin_en";
                $date_lang =",lang: 'en'";
                break;
            case "thai":
                $laypath ="/public/layuiAdmin/layuiadmin_th";
                $date_lang =",lang: 'en'";
                break;
                defautl:
                $laypath ="/public/layuiAdmin/layuiadmin";
                break;
        }
        $this->laypath = $laypath;
        $this->assign('laypath',$laypath);
        $this->assign('datalang',$date_lang);
        if (empty($this->proxyid)) {
            $this->redirect('login/index');
        }
    }

    /**
     * Notes: 接口数据返回
     * @param $code
     * @param array $data
     * @param string $msg
     * @param int $count
     * @param array $other
     * @return mixed
     */
    public function apiReturn($code, $data = [], $msg = '', $count = 0, $other = [])
    {
        return json([
            'code' => $code,
            'data' => $data,
            'msg'  => $msg,
            'count' => $count,
            'other' => $other
        ]);
    }
}
