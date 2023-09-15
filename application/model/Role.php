<?php
namespace app\model;

class Role extends BaseModel
{
    protected $table = 'role';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->UserDB);
    }



}
