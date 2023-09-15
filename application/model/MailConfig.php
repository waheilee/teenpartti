<?php
namespace app\model;

class MailConfig extends BaseModel
{
    protected $table = 'mailconfig';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->GameOC);
    }



}

