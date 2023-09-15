<?php
namespace app\model;

class Viplevel extends BaseModel
{
    protected $table = 'Viplevel';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->MasterDB);
    }



}
