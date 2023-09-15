<?php
namespace app\model;

class UserProxyInfo extends BaseModel
{
    public $table = 'userproxyinfo';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->UserDB);
    }



}
