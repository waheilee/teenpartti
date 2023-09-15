<?php
namespace app\model;

class Account extends BaseModel
{
    protected $table = 'Accounts';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->AccountDB);
    }



}
