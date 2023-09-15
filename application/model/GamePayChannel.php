<?php
namespace app\model;

class GamePayChannel extends BaseModel
{
    protected $table = 'gamepaychannel';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->MasterDB);
    }



}
