<?php

namespace app\business\controller;
use app\model\Smslog;
use app\model\UserDB;

class Index extends Main
{
    public function __construct() {
        parent::__construct();
    }

    /**
     * 首页展示
     */
    public function index() {
        return $this->fetch();
    }

    public function welcome() {
        return $this->fetch();
    }

    public function changePass(){
        $password = $this->request->param('password');
        $res = (new \app\model\GameOCDB)->getTableObject('T_ProxyChannelConfig')
                ->where('ProxyChannelId',session('business_ProxyChannelId'))
                ->data(['PassWord'=>md5($password)])
                ->update();
        if ($res) {
            session('business_ProxyChannelId',null);
            $this->successJSON([],'修改成功');
        } else {
            $this->failJSON([],'修改失败'); 
        }
    }
}
