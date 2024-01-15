<?php
namespace app\Job;




use think\Log;
use think\queue\Job;
class TestJob
{
    /**
     * fire是消息队列默认调用的方法
     * @param Job $job 当前的任务对象
     * @param array|mixed $data 发布任务时自定义的数据
     */
    public function fire(Job $job, $data)
    {
        Log::channel('job')->info('一条测试日志');
        if (empty($data)) {
            Log::channel('job')->error(sprintf('[%s][%s] 队列无消息', __CLASS__, __FUNCTION__));
            return;
        }

        //有效消息到达消费者时可能已经不再需要执行了
        if (!$this->checkJob($data)) {
            $job->delete();
            Log::channel('job')->record("Job does not need to be executed");
            return;
        }
        //执行业务处理
        if ($this->doJob($data)) {
            $job->delete();//任务执行成功后删除
            Log::channel('job')->record("job has been down and deleted");
        } else {
            //检查任务重试次数
            if ($job->attempts() > 3) {
                Log::channel('job')->record("job has been retried more that 3 times");
//                $job->release(10); // 10秒后在执行
                $job->delete(); // 删除任务
            }
        }
    }

    /**
     * 消息在到达消费者时可能已经不需要执行了
     * @param array|mixed $data 发布任务时自定义的数据
     * @return boolean 任务执行的结果
     */
    private function checkJob($data)
    {
        Log::channel('job')->record('验证任务是否需要执行');
        return true;
    }

    /**
     * 根据消息中的数据进行实际的业务处理
     */
    private function doJob($data)
    {
        // 实际业务流程处理
        print_r($data['msg'] ?? '实际业务流程处理');
        Log::channel('job')->record('实际业务流程处理');
        return true;
    }

    function task1(){
        print_r("task 1");
    }


    public function failed($data)
    {
        // ...任务达到最大重试次数后，失败了
        Log::channel('job')->error('任务达到最大重试次数后，失败了');
    }

}