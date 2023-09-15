<?php
namespace app\model;

class PayNotifyLog extends BaseModel
{
    protected $table = 'paynotifylog';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->GameOC);
    }



}
