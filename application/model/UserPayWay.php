<?php
namespace app\model;

class UserPayWay extends BaseModel
{
    public $table = 'userpayway';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->UserDB);
    }



}
