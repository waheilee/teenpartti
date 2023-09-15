<?php
namespace app\model;

class UserWeeklyBonus extends BaseModel
{
    protected $table = 'userweeklybonus';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->GameOC);
    }



}
