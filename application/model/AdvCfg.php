<?php
namespace app\model;

class AdvCfg extends BaseModel
{
    protected $table = 'Advcfg';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->MasterDB);
    }

}
