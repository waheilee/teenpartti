<?php
namespace pushservice\google;
use redis\Redis;                               //Redis 工具类
use Google_Client;
/**
1. Google FCM 工具类
 */
class AccessToken
{
    /**
     * 获取access_token的方法，并对access_token做了缓存处理
     * @param string $config_path require 下载的service-account-file.json文件存放路径
     * @date 2019-11-22
     * @return string
     * @demo AccessToken::getAccessToken('/path/to/service-account-file.json')
     * @throws \Google_Exception
     */
    public static function getAccessToken($config_path)
    {
        $cacheKey = 'lh:google:fcm:accesstoken';
        $temp = Redis::get($cacheKey);
        if (empty($temp)) {
            $temp = self::requestAccessToken($config_path);
            Redis::set($cacheKey, $temp['access_token'],$temp['expires_in']);
            //Redis:($cacheKey, );
            return $temp['access_token'];
        }
        return $temp;
    }

    /**
     * 调用google google-api-php-client 获取access_token 这个是通过google的服务账号授权(用于server端) 不是页面的OAuth授权
     * @date 2019-11-22
     * @param string $config_path option 配置文件路径
     * @throws \Google_Exception
     * @return [
     *      'access_token' => 'ya29.*****',             //访问令牌
     *      'expires_in' => 3600,                       //访问令牌过期时间
     *      'token_type' => 'Bearer',                   //token_type
     *      'created' => 1574401624,                    //token 创建时间
     * ]
     */
    protected static function requestAccessToken($config_path)
    {
        $client = new \Google_Client();
        $client->useApplicationDefaultCredentials();
        $client->setAuthConfig($config_path);
        $client->setScopes(['https://www.googleapis.com/auth/firebase.messaging']);     # 授予访问 FCM 的权限
        return $client->fetchAccessTokenWithAssertion();
    }
}
