<?php
namespace app\model;

class Announcement extends BaseModel
{
    protected $table = 'Announcement';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->MasterDB);
    }

}
