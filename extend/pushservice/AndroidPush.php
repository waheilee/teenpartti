<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2022/3/16
 * Time: 15:59
 */
namespace pushservice;
use Google_Client;
use Google_Http_REST;
use GuzzleHttp;
use redis\Redis;
class AndroidPush{

    private $apikey;
    public function __construct()
    {
        //config['auth_key_file_path']
        $this->apikey='自己应用的apikey'; //秘钥文件路径

    }

    public function getAccessToken()
    {
        try {
            $cache_key = 'push:access_token:key';
            $accessToken = Redis::get($cache_key);
            if (empty($accessToken)) {
                //国内服务器需要使用代理，不然请求不了google接口
                $proxy = 'http://XXXXX';
                $httpClient = new Client([
                    'defaults' => [
                        'proxy' => $proxy,
                        'verify' => false,
                        'timeout' => 10,
                    ]
                ]);
                $client = new Google_Client();
                $client->setHttpClient($httpClient);
                //引入配置文件
                $client->setAuthConfig($this->apikey);
                //设置权限范围
                $scope = 'https://www.googleapis.com/auth/firebase.messaging';
                $client->setScopes([$scope]);
                $client->fetchAccessTokenWithAssertion($client->getHttpClient());
                $token = $client->getAccessToken();
                if (empty($token) || empty($token['access_token']) || empty($token['expires_in'])) {
                    throw new \Exception('access_token is empty!');
                }
                //设置缓存
                Redis::set($cache_key, $token['access_token'], $token['expires_in'] - 20);
                return $token['access_token'];
            }
            return $accessToken;
        } catch (\Exception $e) {
            return '';
        }
    }

//{
//   "name":"projects/project_id/messages/0:1500415314455276%31bd1c9631bd1c96"
    public function SendMsg($token,$title,$message)
    {
        //发送push接口，project_id需要替换成自己项目的id
        $send_url = 'https://fcm.googleapis.com/v1/projects/{project_id}/messages:send';
        //推送参数
        $params = [
            "message" => [
                "token" => $token, //需要发送的设备号
                "notification" => [
                    "title" => $title,
                    "body" => $message
                ],
                "data" => ''
            ]
        ];
        //获取令牌
        $accessToken = $this->getAccessToken();
        if (empty($accessToken)) {
            return false;
        }
        //header请求头，$accessToken 就是你上面获取的令牌
        $header = [
            'Content-Type' => 'application/json; UTF-8',
            'Authorization' => 'Bearer ' . $accessToken
        ];
        $client= new Google_Client();
        $response =$this->curl_post_content($send_url,json_encode($params),$header);
        return $response;
    }


    protected function curl_post_content($url, $data = null, $header = [])
    {
        $ch = curl_init();
        if (substr_count($url, 'https://') > 0) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        if (!empty($header)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }


}