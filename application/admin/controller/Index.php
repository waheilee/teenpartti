<?php

namespace app\admin\controller;
use app\model\Smslog;
use app\model\UserDB;
use think\Db;
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
        // $lang = input('lang') ?: 'zh-cn';
        // $curr_lang = cookie('think_var');

        // if ($lang != $curr_lang) {
        //     cookie('think_var',$lang);
        // }
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
            $start = input('start','');
            $end = input('end','');
            $smstype = input('smstype','');
            $smslog =new Smslog();
            $where='1=1';
            if(!empty($mobile)){
                $where .= ' and mobile like \'%'.$mobile.'%\'';
                // $where['mobile'] = ['like','%'.$mobile.'%'];
            }
            if ($start != '') {
                $where .= ' and addtime >= \''.$start.'\'';
            }
            if ($end != '') {
                $where .= ' and addtime <= \''.$end.'\'';
            }
            if ($smstype != '') {
                if ($smstype == 1) {
                    $where .= ' and CHARINDEX(\'@\',mobile)=0';
                }
                if ($smstype == 2) {
                    $where .= ' and CHARINDEX(\'@\',mobile)>0';
                }
            }
            $list =$smslog->getList($where,$page,$limit,'*','id desc');

            foreach ($list as $key => &$v) {
                if (strpos($v['mobile'], '@') == false) {
                    if (config('is_usa') != 1) {
                        $v['mobile'] = substr_replace($v['mobile'],'**',-2);
                    }
                }
                
            }
            $count =$smslog->getCount($where);
            return $this->apiReturn(0,$list,'',$count);

        }
        return $this->fetch();


    }



    public function ttest(){
        $res = Db::query('call in12');
        var_dump($res);
    }


}
