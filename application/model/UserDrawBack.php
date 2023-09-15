<?php
namespace app\model;

class UserDrawBack extends BaseModel
{
    public $table = 'userdrawback';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName))
            $this->table = $tableName;
        Parent::__construct($this->BankDB);
    }



}
