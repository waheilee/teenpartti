<?php
namespace app\model;

class UserProxyDailyData extends BaseModel
{
    public $table = 'userproxydailydata';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->UserDB);
    }



}
