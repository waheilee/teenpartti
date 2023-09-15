<?php
namespace app\model;

class GameConfig extends BaseModel
{
    protected $table = 'gameconfig';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->MasterDB);
    }

}
