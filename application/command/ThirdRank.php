<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use redis\Redis;
use socket\QuerySocket;

class ThirdRank extends Command
{
    protected function configure()
    {
        $this->setName('ThirdRank')->setDescription('add goldmail send');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln("begin");
        //进程类型，1=动态，2=静态
        $type = 2;
        //子进程数量
        $fork_num = 10;
        while (true) {
            if ($type == 1) {

            } elseif ($type == 2) {
                // $pid  = pcntl_fork();
                $pid = 0;
                if ($pid == -1) {
                    $output->writeln('fork子进程失败!');
                } elseif ($pid > 0) {
                    $fork_num--;
                    if ($fork_num == 0) {
                        pcntl_wait($status);
                        $fork_num++;
                    }
                } else {
                    while (true) {

                        $data = Redis::brPop('third_game_rank_list',10);
                        if (empty($data)) {
                            continue;
                        }
                        $data = json_decode($data[1],true);
                        $date = date('Y-m-d');

                        $PlatformId = $data['PlatformId'];
                        $GameId = $data['GameId'];

                        $arr_data = Redis::get('thind_rank_arr_data') ?:[];
                        if (!isset($arr_data[$PlatformId.'_'.$GameId])) {
                            $arr_data[$PlatformId.'_'.$GameId] = [
                                'PlatformId'=>$data['PlatformId'],
                                'PlatformName'=>$data['PlatformName'],
                                'GameId'=>$data['GameId'],
                                'GameCount'=>0
                            ];
                        }

                        $arr_data[$PlatformId.'_'.$GameId]['GameCount'] += 1;

                        Redis::set('thind_rank_arr_data',$arr_data,86400);
                        if (time()%10 <= 1) {
                            foreach ($arr_data as $key => &$val) {
                                if (!Redis::has('third_rank_data_'.$val['PlatformId'].'_'.$val['GameId'].'_'.$date)) {
                                    (new \app\model\GameOCDB())->getTableObject('T_ThirdGameRank')->insert([
                                        'PlatformId'=>$val['PlatformId'],
                                        'PlatformName'=>$val['PlatformName'],
                                        'GameId'=>$val['GameId'],
                                        'GameCount'=>$val['GameCount'],
                                        'Date'=>$date
                                    ]);
                                    Redis::set('third_rank_data_'.$val['PlatformId'].'_'.$val['GameId'].'_'.$date,1,86400);
                                } else {
                                    (new \app\model\GameOCDB())->getTableObject('T_ThirdGameRank')->where('PlatformId='.$val['PlatformId'])->where('GameId=\''.$val['GameId'].'\'')->whereTime('Date','>=',$date)->setInc('GameCount',$val['GameCount']);
                                }
                            }
                            Redis::rm('thind_rank_arr_data');
                            sleep(1);
                        }

                    }
                    //结束子进程
                    exit();
                }
            }
        }
    }

}
