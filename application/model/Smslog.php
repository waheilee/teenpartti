<?php
namespace app\model;

class Smslog extends BaseModel
{
    protected $table = 'smscodelog';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->GameOC);
    }



}
