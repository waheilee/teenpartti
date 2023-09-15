<?php
namespace app\model;

class GameKind extends BaseModel
{
    protected $table = 'gamekind';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->MasterDB);
    }



}
