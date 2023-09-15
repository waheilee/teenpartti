<?php
namespace app\model;

class ProxyAccount extends BaseModel
{
    protected $table = 'proxyaccount';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->UserDB);
    }



}
