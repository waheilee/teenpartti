<?php

namespace app\admin\controller;



use app\common\Api;

class Summary extends Main
{
    /**
     * 总账报表
     */
    public function index()
    {
        return  "数据接口表不存在 搁置";
        if ($this->request->isAjax()) {
            $page       = intval(input('page')) ? intval(input('page')) : 1;
            $limit      = intval(input('limit')) ? intval(input('limit')) : 10;
            $strartdate = input('start') ? input('start') : '';
            $enddate    = input('end') ? input('end') : '';
            $profit1    = intval(input('profit1')) ? intval(input('profit1')) : 0;
            $profit2    = intval(input('profit2')) ? intval(input('profit2')) : 0;

            $profit = '';
            if ($profit1) {
                if ($profit2 && $profit2>$profit1) {
                    $profit = $profit1.'-'.$profit2;
                } else {
                    $profit = $profit1.'-';
                }
            } else {
                if ($profit2) {
                    $profit = $profit1.'-'.$profit2;
                }
            }

//                OM_GameOC T_SystemDaySum
//            $res = Api::getInstance()->sendRequest([
//                'strartdate' => $strartdate,
//                'enddate'    => $enddate,
//                'profit'     => $profit,
//                'page'       => $page,
//                'pagesize'   => $limit
//            ], 'system', 'sumdata');

//            $data = $res['data']['list'];

            return $this->apiReturn($res['code'],
                $data, $res['total'],
                isset($res['data']['sum']) ? $res['data']['sum'] : []);
        }
//        return $this->fetch();
    }

    /**
     * 人气数据
     */
    public function userCount()
    {
        if ($this->request->isAjax()) {
            $page       = intval(input('page')) ? intval(input('page')) : 1;
            $limit      = intval(input('limit')) ? intval(input('limit')) : 10;
            $strartdate = input('strartdate') ? input('strartdate') : '';
            $enddate    = input('enddate') ? input('enddate') : '';

            $res = Api::getInstance()->sendRequest([
                'strartdate' => $strartdate,
                'enddate'    => $enddate,
                'page'       => $page,
                'pagesize'   => $limit
            ], 'system', 'userdata');

            $data = [];
            $total = 0;
            $summodel = [];
            $msg ='暂无数据';
            if(isset($res['data']['userreportlist'])){
                $data =$res['data']['userreportlist'];
//                foreach ($data as $k=>&$v){
//                    $v['totallogin'] = $v['totallogin'].'/'.$v['totaldemo'];
//                }
                unset($v);
                $total =$res['total'];
                $summodel =isset($res['data']['summodel']) ? $res['data']['summodel'] : [];
                $msg= '获取成功';
            }

            return $this->apiReturn($res['code'],
                $data,$msg,$total,$summodel

            );
        }
        return $this->fetch();
    }

}
