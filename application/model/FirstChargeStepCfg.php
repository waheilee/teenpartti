<?php
namespace app\model;

class FirstChargeStepCfg extends BaseModel
{
    protected $table = 'firstchargestepcfg';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->MasterDB);
    }



}
