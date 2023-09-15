<?php

namespace app\model;

use think\db;

class PlayerConsumption extends BaseModel
{
    protected $table = '[om_masterdb].[dbo].[commoditycfg]';

    public function __construct($connstr) {
        Parent::__construct($connstr);
    }

    public function getPlayerConsumption($strwhere, $OrderField, $PageIndex, $pagesize, $ordertype, $tblName = '') {
        $goods = config('goodstype');
        $strFields = "id,commodityname,cdytype,realmoney";
        if ($tblName == '') {
            $tableName = $this->table;
        } else
            $tableName = $tblName;
        //取所有商品
        $commodity = $this->getQueryAll($tableName, $strFields, '');
        $commodity = $commodity['list'];
        //取会员消费记录
        $strFields = "transactionno,commodityname,accountid,realmoney,virtualgold,addtime,platformtype,cdytype,id";
        $tableName = '[cd_userdb].[dbo].[t_usertransaction]';
        $user = $this->getQueryAll($tableName, $strFields, $strwhere);
        $user = $user['list'];

        $total_cishu = 0;    //购买次数占比
        $total_RealMoney = 0;    //购买次数占比
        if (!empty($user)) {
            $buytimes = count($user); //总购买总次数
            //循环比对商品与会员消费记录，统计数据
            for ($i = 0; $i < count($commodity); $i++) {
                //初始化统计
                $commodity[$i]['buytimes'] = 0; //购买次数
                $commodity[$i]['cishu'] = 0;    //购买次数占比
                $out = array();
                //开始统计
                for ($j = 0; $j < count($user); $j++) {
                    if ($commodity[$i]['commodityname'] == $user[$j]['commodityname']) {
                        $commodity[$i]['buytimes'] +=  1; //重复出现购买商品则计算做购买次数+1
                        $total_RealMoney += $user[$j]['realmoney'];
                    }
                }

                $cdname=$goods[$commodity[$i]['cdytype']]; //[typeName] shopName
                $commodity[$i]['commodityname'] = "[$cdname] " . $commodity[$i]['commodityname'];
                $commodity[$i]['cishu'] = round($commodity[$i]['buytimes'] / $buytimes * 100, 2) . "%";
            }
            //汇总数据
            $commodity[$i]['commodityname'] = "合计";
            $commodity[$i]['buytimes'] = $buytimes; //购买次数
            $commodity[$i]['cishu'] = "100%";    //购买次数占比
        } else {
            for ($i = 0; $i < count($commodity); $i++) {
                //初始化统计
                $commodity[$i]['buytimes'] = 0; //购买次数
                $commodity[$i]['cishu'] = 0;    //购买次数占比
            }
        }
        //print_r($commodity);
        //exit();
        $list['list'] = $commodity;
        $list['count'] = 0;
        $list['totalsum'] = $this->getConversion($total_RealMoney);
        return $list;
    }
}
