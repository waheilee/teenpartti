<?php
namespace app\model;

use think\db;
class DisableBindCfg extends BaseModel
{
    protected $table = 'disablebindcfg';

    public function __construct($connstr=''){
        if(empty($connstr))
        {
            $connstr = $this->MasterDB;
        }
        Parent::__construct($connstr);
    }



}
