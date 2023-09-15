<?php
namespace app\model;

class PushMessage extends BaseModel
{
    protected $table = 'pushmessage';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->GameOC);
    }



}