<?php
namespace pushservice\google;

/**
 * Google FCM 工具类
 */
class Notification
{
    /**
     * 将设备添加到主题
     * @author jeanku
     * @date 2019-11-22
     * @param array $data require 请求数据
     * @return array
     * @throws \Exception
     */
    public static function push($data)
    {
        $project =config('androidpush')['project_id'];
        $url = 'https://fcm.googleapis.com/v1/projects/'.$project.'/messages:send';
        return Curl::setParamType(1)->setHeader(self::getAccessTokenHeader())->post($url, $data);
    }

    /**
     * 推送消息的请求header
     * @date 2019-11-22
     * @return array
     * @throws \Google_Exception
     */
    protected static function getAccessTokenHeader()
    {
        return [
            'Content-Type: application/json',
            'Authorization: Bearer ' . AccessToken::getAccessToken(config('androidpush')['service_account_json_file']),
        ];
    }
}
