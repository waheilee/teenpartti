<?php
namespace app\model;

class OperatorDailyReport extends BaseModel
{
    protected $table = 'operatordailyreport';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->GameOC);
    }



}
