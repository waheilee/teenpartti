<?php
namespace app\model;

class AjustConfig extends BaseModel
{
    protected $table = 'ajustconfig';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->GameOC);
    }

}
