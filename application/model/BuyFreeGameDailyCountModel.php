<?php

namespace app\model;
      
class BuyFreeGameDailyCountModel extends CommonModel {
    
    protected $table = 'buy_free_game_count';
    protected $sqlsrv_table = '';

    /**
     * 数据库连接配置
     * @var type 
     */
    private $GameOCDB;
    
    
//    记录类型
    const RECORD_TYPE_FREE = 1;  // 1免费
    const RECORD_TYPE_MOP_UP = 2; // 2扫荡
    
    public function __construct($connstr='') {
        Parent::__construct($connstr);
        // sql server 数据库
        $this->GameOCDB = new \app\model\GameOCDB();
    }
    
    /**
     * 获取日期为day的统计记录
     * @param type $day
     * @return type
     */
    public function getDailyCountByDay($day, $fields='') {
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

