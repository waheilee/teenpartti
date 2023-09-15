<?php
namespace app\model;

class UserTeamLog extends BaseModel
{
    public $table = 'userteamlog';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->UserDB);
    }



}
