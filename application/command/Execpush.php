<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use redis\Redis;
use app\model\DeviceToken;
use pushservice\Push;
class Execpush extends Command
{
    protected function configure()
    {
        $this->setName('exec_push')->setDescription('添加推送任务');
    }

    protected function execute(Input $input, Output $output)
    {	
        $output->writeln("开始执行");

        //进程类型，1=动态，2=静态
        $type = 2;
        //子进程数量
        $fork_num = 10;
        $operator_id = 'rummyjai';
        $url = 'http://18.140.253.72/Api/index/push';
    	while (true) {
            if ($type == 1) {

            } elseif ($type == 2) {
                // $pid  = pcntl_fork();
                $pid = 0;
                if ($pid == -1) {
                    $output->writeln('fork子进程失败!');
                } elseif ($pid > 0) {
                    $fork_num --;
                    if ($fork_num == 0) {
                        pcntl_wait($status);
                        $fork_num ++;
                    }
                } else {
                    while (true) {
                        $data = Redis::brPop('push_list_detail',10);
                        if (empty($data)) {
                            continue;
                        }
                        $data = json_decode($data[1],true);
                        $push_data = [
                            'operator_id'=>$operator_id,
                            'device'=>$data['DeviceType'],
                            'title'=>$data['title'],
                            'message'=>$data['content'],
                            'token'=>$data['DeviceToken'],
                            'sign'=>md5($operator_id.'3b5af0f0fe7c68ea06d4876d746e219e')
                        ];
                        //推送
                        $res = $this->curlPush($url,json_encode($push_data));
                        $res = json_decode($res,true);
                        // $res = Push::Send($data['DeviceType'],$data['DeviceToken'],$data['content']);
                        if ($res['status'] != true) {
                            //失败写入日志
                            if ($data['DeviceType'] == 1) {
                                $content = '向ID'.$data['AccountID'].'用户的安卓设备'.$data['DeviceToken'].'推送消息'.$data['content'].'失败';
                            } elseif ($data['DeviceType'] == 2) {
                                $content = '向ID'.$data['AccountID'].'用户的IOS设备'.$data['DeviceToken'].'推送消息'.$data['content'].'失败';
                            } 
                            save_log('push',$content);
                        } else {
                            //更新推送记录
                            Redis::dec('push_count_remain'.$data['record_id'],1);
                        }
                        
                    }
                    //结束子进程
                    exit();
                }
            }
        }
    }

    protected function curlPush($url,$post_data='',$header=[],$type='get') {
        if ($post_data) {
            $type = 'post';
        }
        //初始化
        $curl = curl_init();
        //设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $url);
        //设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_HEADER, 0);
        //设置获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        if($type=='post'){
            //设置post方式提交
            curl_setopt($curl, CURLOPT_POST, 1);
            if(is_array($post_data)){
                $post_data = http_build_query($post_data);
            }
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
        }
        if($header){
            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        }
        //https
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        //执行命令
        $data = curl_exec($curl);
        //关闭URL请求
        curl_close($curl);
        //显示获得的数据
        return $data;
    }

}
