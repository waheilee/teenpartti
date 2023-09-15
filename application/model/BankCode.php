<?php
namespace app\model;

class BankCode extends BaseModel
{
    protected $table = 'bankcode';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->MasterDB);
    }



}
