<?php
namespace app\model;

class RoomWaterData extends BaseModel
{
    protected $table = 'roomwaterdata';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->UserDB);
    }



}
