<?php
namespace app\model;

class UserWageRequire extends BaseModel
{
    protected $table = 'userwagerequire';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->UserDB);
    }



}
