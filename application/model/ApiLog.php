<?php

namespace app\model;
      
class ApiLog extends CommonModel {
    
    protected $table = 'api_log';
      
    public function __construct($connstr='') {
        Parent::__construct($connstr);
    }
    
}

