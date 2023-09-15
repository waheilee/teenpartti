<?php
namespace app\model;

class ApiPlayerRecord extends BaseModel
{
    protected $table = 'apiplayerrecord';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->GameOC);
    }

}
