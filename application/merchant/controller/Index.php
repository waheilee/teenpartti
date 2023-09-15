<?php

namespace app\merchant\controller;
use app\model\Smslog;
use app\model\UserDB;

class Index extends Main
{
    public function __construct() {
        parent::__construct();

        $this->db2 = config('database_qmfx.database');
    }

    /**
     * 首页展示
     */
    public function index() {
        return $this->fetch();
    }

    /**
     * 桌面页
     */
    public function welcome() {
        return $this->fetch();
    }

    public function test()
    {
        $db = new  UserDB();
        $res = $db->TViewAccount()->GetPage();
        halt($res);
    }


    public function smscodelog(){
        if($this->request->isAjax()){
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 10;
            $mobile = input('mobile','');

            $smslog =new Smslog();
            $where=[];
            if(!empty($mobile)){
                $where['mobile'] = ['like','%'.$mobile.'%'];
            }

            $list =$smslog->getList($where,$page,$limit,'*','id desc');
            $count =$smslog->getCount($where);
            return $this->apiReturn(0,$list,'',$count);

        }
        return $this->fetch();


    }


    public function changePass(){
        $password = $this->request->param('password');
        $res = (new \app\model\GameOCDB)->getTableObject('T_OperatorSubAccount')
                ->where('OperatorId',session('merchant_OperatorId'))
                ->data(['PassWord'=>md5($password)])
                ->update();
        if ($res) {
            session('merchant_OperatorId',null);
            $this->successJSON([],'修改成功');
        } else {
            $this->failJSON([],'修改失败'); 
        }
    }

}
