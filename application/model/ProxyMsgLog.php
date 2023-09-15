<?php
namespace app\model;

use think\db;
class ProxyMsgLog extends BaseModel
{
    protected $table = 'proxymsglog';

    public function __construct($connstr=''){
        if(empty($connstr))
        {
            $connstr = $this->DataChangelogsDB;
        }
        Parent::__construct($connstr);
    }



}
