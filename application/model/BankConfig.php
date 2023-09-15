<?php
namespace app\model;

class BankConfig extends BaseModel
{
    protected $table = 'bankConfig';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->GameOC);
    }



}
