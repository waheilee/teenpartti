<?php
namespace app\model;

class HttpUrlBase extends BaseModel
{
    protected $table = 'httpurlbase';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->MasterDB);
    }



}
