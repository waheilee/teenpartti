<?php
namespace app\model;

use think\db;
class UserTransactionChannel extends BaseModel
{
    protected $table = 'usertransactionchannel';

    public function __construct($tableName)
    {
        if (!IsNullOrEmpty($tableName))
            $this->table = $tableName;
        Parent::__construct($this->UserDB);
    }
}