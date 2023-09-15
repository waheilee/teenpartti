<?php
namespace app\model;

class GameType extends BaseModel
{
    protected $table = 'gametype';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->MasterDB);
    }



}
