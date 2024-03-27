<?php
namespace app\model;

use think\db;
class WeekSalaryCfg extends BaseModel
{
    protected $table = 'weeksalarycfg';

    public function __construct($connstr=''){
        if(empty($connstr))
        {
            $connstr = $this->MasterDB;
        }
        Parent::__construct($connstr);
    }



}
