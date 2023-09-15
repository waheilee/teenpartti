<?php


namespace app\model;


class UserFishScoreLog extends BaseModel
{
    protected $table = 'userfishscorelog';

    /**
     * UserDB.
     * @param string $tableName 连接的数据表
     */
    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->UserDB);
    }
}