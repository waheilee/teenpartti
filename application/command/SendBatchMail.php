<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use redis\Redis;
use socket\QuerySocket;

class SendBatchMail extends Command
{
    protected function configure()
    {
        $this->setName('SendBatchMail')->setDescription('add goldmail send');
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
                        $data = Redis::rpop('batchgold:role');
                        if (empty($data)) {
                            continue;
                        }
                        $data = json_decode($data, true);
                        if (!preg_match("/^\d*$/", $data['roleid'])) {
                            save_log('BatchMail', json_encode($data) . '玩家id错误');
                            continue;
                        }

                        if($data['roleid']==0 || empty($data['roleid']) || empty($data['coin']) || $data['coin']==0){
                            continue;
                        }

                        $amount = bcmul($data['coin'], bl, 0);
                        $sendQuery = new QuerySocket();

                        $res=$sendQuery->SendSystemMailV2(0, $data['roleid'], 8, 8, $amount, 0, $data['multiple'], 0, 1, $data['title'], $data['note'], '', '','');

                        if ($res['iResult'] > 0) {
                            save_log('BatchMail', json_encode($data) . '发送成功');
                        } else {
                            save_log('BatchMail', json_encode($data) . '发送失败');
                        }

                    }
                    exit();
                }
            }
        }
    }

}
