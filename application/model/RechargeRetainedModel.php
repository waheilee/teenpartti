<?php

namespace app\model;

class RechargeRetainedModel extends \app\model\CommonModel {
    protected $table = 'recharge_retained';
    protected $sqlsrv_table = '';
    
    
    public function __construct($connstr='') {
        Parent::__construct($connstr);
        // sql server 数据库
    }
    
    /**
     * 获取日期为day的日留存记录
     * @param type $day
     * @return type
     */
    public function getDailyRechargeRetainedByDay($day, $fields='') {
        if (!empty($fields)) { 
           $filter = "SELECT " . $fields;
        } else {
           $filter = "SELECT * ";
        }
        $filter .= " FROM " . $this->getTableName() . " WHERE day = '" . $day . "'";
        $data = $this->query($filter);
        if (!empty($data)) {
            return $data[0];
        } else {
            return [];
        }
    }
    
}

