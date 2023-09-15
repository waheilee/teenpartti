<?php
namespace app\model;

class DeviceToken extends BaseModel
{
    protected $table = 'devicetoken';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->AccountDB);
    }



}

