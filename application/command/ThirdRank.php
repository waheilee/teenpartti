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

        $db = new \app\model\GameOCDB();     
        while (true) {
            $data = Redis::brPop('third_game_rank_list',10);
            if (empty($data)) {
                continue;
            }
            $data = json_decode($data[1],true);
            $date = date('Y-m-d');


            //处理数据
            $third_rank_data = Redis::get('third_rank_data_'.$date) ?: [];
            $third_rank_data[$data['PlatformId']] = $third_rank_data[$data['PlatformId']] ?? [];
            $third_rank_data[$data['PlatformId']][$data['GameId']] = $third_rank_data[$data['PlatformId']][$data['GameId']] ?? 0;
            $third_rank_data[$data['PlatformId']][$data['GameId']] += 1;
            
            Redis::set('third_rank_data_'.$date,$third_rank_data,86400);

            //一分钟处理一次
            if (!Redis::has('third_rank_data_deal')) {
                Redis::rm('third_rank_data_'.$date);
                Redis::set('third_rank_data_deal',1,60);
                
                foreach ($third_rank_data as $key => &$val) {
                    $PlatformId = $key;
                    foreach ($val as $k => &$v) {
                        $GameId = $k;
                        if (!Redis::has('third_rank_data_newThirdRank_'.$PlatformId.'_'.$GameId.'_'.$date)) {
                            $db->getTableObject('T_ThirdGameRank')->insert([
                                'PlatformId'=>$PlatformId,
                                'PlatformName'=>$PlatformId,
                                'GameId'=>$GameId,
                                'GameCount'=>0,
                                'Date'=>$date
                            ]);
                            Redis::set('third_rank_data_newThirdRank_'.$PlatformId.'_'.$GameId.'_'.$date,1,86400);
                        }
                        $db->getTableObject('T_ThirdGameRank')->where('PlatformId='.$PlatformId)->where('GameId=\''.$GameId.'\'')->whereTime('Date','>=',$date)->setInc('GameCount',$v);
                    }
                }
            }
        }
    }

}
