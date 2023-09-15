<?php
namespace app\model;

class GameRoomInfo extends BaseModel
{
    protected $table = 'gameroominfo';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->MasterDB);
    }



}
