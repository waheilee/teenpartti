<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use redis\Redis;
use app\model\DeviceToken;
use app\model\AccountDB;

class Addpush extends Command
{
    protected function configure()
    {
        $this->setName('add_push')->setDescription('添加推送任务');
    }

    protected function execute(Input $input, Output $output)
    {	
        $output->writeln("开始执行");
    	while (true) {
    		$data = Redis::brPop('push_list',10);
            if (!empty($data)) {
                $data = json_decode($data[1],true);
                if ($data['SendTime'] > date('Y-m-d H:i:s')) {
                    Redis::lpush('push_list',json_encode($data));
                    //未到推送时间，等待2秒。重新入队。
                    sleep(2);
                } else {
                    //添加计划到推送队列
                    $sql = 'SELECT dt.* FROM T_DeviceToken AS dt';
                    $where = '1=1 and dt.DeviceType<>0 and dt.DeviceType is not null';
                    if ($data['SendType'] == 1 || $data['SendType'] == 2) {
                        $where .= ' and dt.DeviceType='.$data['SendType'];
                    }
                    if ($data['RoleId'] != 0) {
                        $where .= ' and dt.AccountID in ('.$data['RoleId'].')';
                    } else {
                        //活跃用户
                        if ($data['LoginDays'] != 0) {
                            $LoginDate = date('Y-m-d H:i:s',strtotime('-'.$data['LoginDays'].' days'));
                            $AccountDb = new AccountDB();
                            if ($data['LoginType'] == 1) {
                                $sql .= " INNER JOIN T_Accounts AS at on dt.AccountID=at.AccountID and at.LastLoginTime<='".$LoginDate."'";
                            } else {
                                $sql .= " INNER JOIN T_Accounts AS at on dt.AccountID=at.AccountID and at.LastLoginTime>='".$LoginDate."'";
                            }
                        }
                    }
                    $sql .= " WHERE ".$where;
                    // $output->writeln($sql);
                    $deviceToken = new DeviceToken();
                    $push_list = $deviceToken->DBOriginQuery($sql);
                    $total_count = count($push_list);

                    Redis::set('push_count_total'.$data['Id'],$total_count);
                    Redis::set('push_count_remain'.$data['Id'],$total_count);
    
                    foreach ($push_list as $key => &$val) {
                        $val['title'] = $data['Title'];
                        $val['content'] = $data['Message'];
                        $val['record_id'] = $data['Id'];
                        Redis::lpush('push_list_detail',json_encode($val));
                        unset($val);
                    }
                    $output->writeln("添加记录ID".$data['Id']."到推送队列");
                    // array_walk($push_list,function(&$val,$key)use($title,$content){
                    //     $val['content'] = $content;
                    //     // Redis::lpush('Push_list_detail',json_encode($val));
                    //     // unset($val);
                    // });
                }
            } 
    	}
    }
}
