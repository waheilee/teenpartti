<?php

namespace pushservice\google;

/**
 * Google FCM 工具类
 */
class Message
{
    public static $common = [
        'name' => null,
        'data' => null,
        "notification" => null,
        "android" => null,
        "webpush" => null,
        "apns" => null,
        "fcm_options" => null,
        "token" => null,
        "topic" => null,
        "condition" => null,
    ];

    /**
     * 生成topic推送数据
     * @author jeanku
     * @date 2019-11-22
     * @param string $topic required topic key
     * @param array $messgeData required message data
     * @return array
     */
    public static function formatTopicMessage($topic, $messgeData)
    {
        self::$common['topic'] = $topic;
        self::$common['webpush'] = $messgeData;
        return ['message' => array_filter(self::$common)];
    }

    /**
     * 获取web端推送的数据
     * @author jeanku
     * @date 2019-11-22
     * @param array $notification required [
     *      'title' => '',
     *      'body' => '',
     * ]
     * @param array $data option []
     * @param array $header option []
     * @param array $fcm_options option [
     *      'link' => 'http://***'          //点击跳转的链接
     * ]
     * @return array
     */
    public static function getWebMessage($notification, $data = null, $header = null, $fcm_options = null)
    {
        return array_filter([
            'header' => $header,
            'data' => $data,
            'notification' => $notification,
            'fcm_options' => $fcm_options,
        ]);
    }
}
