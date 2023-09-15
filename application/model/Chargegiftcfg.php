<?php
namespace app\model;

class Chargegiftcfg extends BaseModel
{
    protected $table = 'chargegiftcfg';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->MasterDB);
    }



}
