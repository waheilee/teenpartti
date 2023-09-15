<?php
namespace app\model;

class CountryCode extends BaseModel
{
    protected $table = 'countryconfig';

    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->MasterDB);
    }



}
