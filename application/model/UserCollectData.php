<?php
namespace app\model;

class UserCollectData extends BaseModel
{
    protected $table = 'usercollectdata';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->UserDB);
    }



}
