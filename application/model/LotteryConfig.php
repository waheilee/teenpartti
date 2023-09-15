<?php
namespace app\model;

class LotteryConfig extends BaseModel
{
    protected $table = 'lotteryconfig';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->MasterDB);
    }



}
