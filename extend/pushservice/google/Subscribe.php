<?php
namespace pushservice\google;

/**
 * Google FCM 工具类
 */
class Subscribe
{

    /**
     * 将设备添加到主题
     * @author jeanku
     * @date 2019-11-22
     * @param string $topic_name require 根据业务自定义的主题名称
     * @param string $register_token require 前端授权得到的REGISTRATION_TOKEN
     * @return array
     * @throws \Exception
     */
    public static function addTopic($topic_name, $register_token)
    {
        $url = sprintf('https://iid.googleapis.com/iid/v1/%s/rel/topics/%s', $register_token, $topic_name);
        return Curl::setHeader(self::getCommonHeader())->post($url);
    }

    /**
     * 非推送消息的请求header
     * @date 2019-11-22
     * @return array
     */
    protected static function getCommonHeader()
    {
        return [
            'Content-Type: application/json',
            'Authorization: key=' . config('androidpush')['fcm_server_key'],		//env('FCM_SERVER_KEY')旧版服务器密钥
        ];
    }
}
