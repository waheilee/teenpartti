<?php
namespace app\model;

class Blacklist extends BaseModel
{
    protected $table = 'blacklist';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->MasterDB);
    }



}
