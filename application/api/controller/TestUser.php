<?php

namespace app\api\controller;
use app\Job\TestJob;
use think\Queue;
class TestUser
{
    public function index(): string
    {
        return 'v1/user/index2';
    }

    /**
     * 投递消息(生产者)
     * @return string
     */
    public function push(): string
    {

        //  queue的 push方法 第一个参数可以接收字符或者对象字符串
        $job = 'app\Job\TestJob'; // 当前任务由哪个类负责处理
        $queueName = 'cron_job_queue';  // 当前队列归属的队列名称
        //  // 当前任务所需的业务数据
        $data['msg'] = 'Test queue msg,time:' . date('Y-m-d H:i:s', time());
        $data['user_id'] = 1;
//        $res = Queue::push(CronJob::class, $data, $queueName);  // 可以自动获取
        $res = Queue::push($job, $data, $queueName);   // 可以手动指定 -
        $data['msg'] = 'later Test queue msg,time:' . date('Y-m-d H:i:s', time());
        $res = Queue::later(10, $job, $data, $queueName);   // 10秒后执行
        $data['msg'] = 'task1---,time:' . date('Y-m-d H:i:s', time());
        $res = Queue::later(30, "app\Job\TestJob@task1", $data, $queueName);   // 10秒后执行
        if ($res == false) {
            return '消息投递失败';
        } else {
            return '消息投递成功';
        }
    }

}