<?php
namespace app\model;

use think\db;
class AccuDepositGradientBonusCfg extends BaseModel
{
    protected $table = 'accudepositgradientbonuscfg';

    public function __construct($connstr=''){
        if(empty($connstr))
        {
            $connstr = $this->MasterDB;
        }
        Parent::__construct($connstr);
    }



}
