<?php
namespace app\model;

class GameStatisticTotal extends BaseModel
{
    protected $table = 'gamestatistictotal';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->GameOC);
    }



}
