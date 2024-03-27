<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2022/7/19
 * Time: 11:45
 */

namespace app\api\controller;

use app\common\Api;
use app\model\BankDB;
use app\model\CommonModel;
use app\model\GameOCDB;
use app\model\GameStatisticTotal;
use app\model\GameStatisticUser;
use app\model\MasterDB;
use app\model\UserDB;
use redis\Redis;
use socket\sendQuery;
use think\Controller;

class ServiceRechargeRewad extends Controller
{

     public function  runJob(){

         $activetitle ='Rewards for recharging and returning activities';
         $descript ='Dear player. You have obtained a recharge reward, please get it';
         $db= new UserDB();
         $yestoday = date('Y-m-d',strtotime('-1 day'));
         $where = " datediff(d,addtime,'$yestoday')=0 ";


         $masterdb =new MasterDB();
         $config = $masterdb->getTableObject('T_GameConfig(nolock)')->where('CfgType','in','194,195')->select();
         if(empty($config)){
             save_log('rechargereturn', '读取配置出错');
             return ;
         }

         $min_recharge =bcdiv($config[0]['CfgValue'],bl,0); //充值最小金额  1000
         $return_rate =bcdiv($config[1]['CfgValue'],100,3); //返还比例  5

         if($min_recharge<=100 || $return_rate==0){
             return ;
         }

         $page = 1;
         $row = 15;

         $list= $db->getTableList('T_UserTransactionChannel',$where,$page,$row,'AccountID,sum(RealMoney) as RealMoney',['AccountID'=>'asc'],'AccountID');
         $list = $list['list'];
         while ($list) {
             foreach ($list as $k => $v) {
                 if($v['RealMoney']>=$min_recharge){
                     $key = $yestoday.$v['AccountID'];
                     if(!Redis::has($key)) {
                         $rewardcoin = bcmul($v['RealMoney'], $return_rate, 3);
                         $rewardcoin =$rewardcoin*1000;
                         $sendQuery = new sendQuery();
                         $res = $sendQuery->callback("CMD_MD_SYSTEM_MAILv2", [0, $v['AccountID'], 8, 10, $rewardcoin, 0, 10, 0, 1, $activetitle, $descript, '', '', '']);
                         $retcode = unpack('cint', $res)['int'];
                         save_log('rechargereturn', '玩家id:' . $v['AccountID'] . '========金额:' . $rewardcoin . ',更新状态' . json_encode($retcode));
                         Redis::set($key,100);
                     }
                 }
             }
             $list= $db->getTableList('T_UserTransactionChannel',$where,$page,$row,'AccountID,sum(RealMoney) as RealMoney',['AccountID'=>'asc'],'AccountID');
             $list = $list['list'];
             $page++;
         }
        echo $yestoday.'任务已完成';
     }




}