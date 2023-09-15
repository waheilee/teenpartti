<?php
/**
 * Created by PhpStorm.
 * User: admin
<<<<<<< HEAD
 * Date: 2022/3/15
 * Time: 11:46

 * Date: 2022/3/16
 * Time: 14:59
 */
namespace  pushservice;
use think\exception;
class Push{
    //private static $_instance = null;

//     //公有化获取实例方法
//    public static function getInstance(){
//        if (!(self::$_instance instanceof Push)){
//            self::$_instance = new Push();
//        }
//        return self::$_instance;
//    }


    ///推送信息
    /// type 1安卓  2 ios
    public static function Send($type,$deviceToken,$message){
        $status = false;
        switch ($type){
            case  1: //安卓
                $title = mb_strlen($message)>30?mb_substr($message,0,30):$message;
                $status = self::SendAndroid($deviceToken,$title,$message);
                break;

            case 2: //ios
                $status =self::SendIOS($deviceToken,$message);
                break;
            default:

                break;
        }
        return $status;
    }


    public static function SendIOS($deviceToken,$message){
        $config = config('iospush');
        $rootpath = $config['rootpath'];  //ROOT证书地址
        $cp = $config['provider_pem'];//'push_key_dis.pem';  //provider证书地址
        $env = $config['env'];
        $password =$config['provider_password'];
        $apns = new APNS($env,$cp);  //设置推送环境
        try
        {
            $apns->setPassphrase($password);//设置证书密码
            $apns->setRCA($rootpath);  //设置ROOT证书
            $apns->connect(); //连接
            $apns->addDT($deviceToken);  //区分APNS 和 VoIP加入deviceToken
            $apns->setText($message);  //发送内容
            $apns->setBadge(1);  //设置图标数
            $apns->setSound();  //设置声音
            $apns->setExpiry(3600);  //过期时间
            // $apns->setCP('custom operation',array('type' => '1','url' => 'http://www.google.com.hk'));  //自定义操作
            $apns->send();
            return true;
        } catch (Exception $e) {
            save_log('iospush',$e->getMessage());
            return false;
        }
    }
    public static  function SendAndroid($deviceToken,$title,$message){
        try {
            $params = [
                "message" => [
                    "token" => $deviceToken, //需要发送的设备号
                    "notification" => [
                        "title" => $title,
                        "body" => $message
                    ]
//                    "data" => [
//                        'ddd'=>'google',
//                        'ggg' => 'fish'
//                    ]
                ]
            ];
            $state = google\Notification::push($params);
            save_log('push', json_encode($state));
            $message = json_decode($state, true);
            if (!empty($message['name']))
                return true;
            else
                return false;
        }catch (Exception $ex){
            return false;
        }

    }

}