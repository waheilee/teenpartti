<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2020/5/9
 * Time: 17:31
 */

namespace app\agent\controller;

use app\common\Api;
use app\model\Operatelog;
use app\model\SubAccount;
use app\model\ApiKey;
use think\Controller;
use think\config;
use app\common;
use redis\Redis;

class Agent extends Base
{

    public function index(){
        
        return $this->fetch();
    }
	
	public function bankcard(){
        
        return $this->fetch();
    }
	
	public function add_card(){
        
        return $this->fetch();
    }
	
	public function recharge(){
        
        return $this->fetch();
    }
	
	public function record(){
        
        return $this->fetch();
    }
	
	public function record_info(){
        
        return $this->fetch();
    }
	
	public function security(){
        
        return $this->fetch();
    }
	
	public function service(){
        
        return $this->fetch();
    }
}