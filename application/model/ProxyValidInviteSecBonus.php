<?php
namespace app\model;

use think\db;
class ProxyValidInviteSecBonus extends BaseModel
{
    protected $table = 'proxyvalidinvitesecbonus';

    public function __construct($connstr=''){
        if(empty($connstr))
        {
            $connstr = $this->MasterDB;
        }
        Parent::__construct($connstr);
    }



}
