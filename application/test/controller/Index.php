<?php

namespace app\test\controller;


use redis\Redis;
use think\Controller;
use think\Db;
use pushservice\Push;
use Edujugon\PushNotification\PushNotification;
use socket\QuerySocket;


class Index extends Controller
{



    public function  testtest(){

        $userid =42253888;
        $time1 =getMillisecond();
        $socket = new QuerySocket();
        $balance = $this->getBalance($userid);
        $transaction_id= $this->makeOrderId($userid);
        $socket = new QuerySocket();
        $state = $socket->downScore($userid, 100, $transaction_id,36000);
        $time2 = getMillisecond();
        save_log('socket',$time2-$time1);
        echo 1;


    }



    private function getBalance($roleid)
    {
        $roleid = intval($roleid);

        $socket = new QuerySocket();
        $m = $socket->DSQueryRoleBalance($roleid);
        $gamemoney = $m['iGameWealth'] ?? 0;
        $balance = bcdiv($gamemoney, bl, 3);
        return floor($balance*100)/100;
    }

    //创建临时订单
    private function makeOrderId($uid)
    {
        return date('YmdHis') . sprintf('%.0f', floatval(explode(' ', microtime())[0]) * 1000) . $uid;
    }






    /**
     * @param $uid
     * @param int $timeout
     * @return bool
     * 设置锁
     */
    public  function set_mutex($uid,$timeout = 2){
        $cur_time = time();
        $read_news_mutex_key = "redis:mutex:{$uid}";
        $mutex_res = Redis::lock($read_news_mutex_key,$cur_time + $timeout);
        if($mutex_res){
            return true;
        }
        //就算意外退出，下次进来也会检查key，防止死锁
        $time = Redis::get($read_news_mutex_key);
        if($cur_time > $time){
            Redis::rm($read_news_mutex_key);
            return Redis::lock($read_news_mutex_key,$cur_time + $timeout);
        }
        return false;
    }

    /**
     * @param $uid
     * 释放锁
     */
    public  function del_mutex($uid){
        $read_news_mutex_key = "redis:mutex:{$uid}";
        Redis::rm($read_news_mutex_key);
    }
    /**
     * 处理下载页跳转
     */
    public function index()
    {

        $a =session('test');
        if(empty($a)){
            $a = 1;
            session('test',$a);
        }
        else
        {
            $a =$a+1;
            session('test',$a);
        }


            echo $a;
            die;
        $a = 29064;
        $b = 4355;


        $c = sprintf('%.2f',4355/29064);

        echo  29064*($c+0.06);

        echo '<br/>';
        echo $c;
        die;

        if(!$this->set_mutex('1111')){

            echo '请求太频';
            die;
        }
        echo 'bbbb';
die;
        $this->del_mutex('1111');
        die;
        $str='43190251,41465325,75400807,72137668,84916240,90302717,69751096,93555553';
        $parent_arr = array_filter(explode(',', $str));
        $parent_arr = array_reverse($parent_arr);
        $data= array_slice($parent_arr,0,3);
        $k =1;
        $rate=30;
        foreach ($data as $vv){
            $user_rate = 30;
            if($k==1){
                $user_rate = bcdiv($rate,100,3);
            }else  if($k==2){
                $user_rate =bcdiv($rate*$rate,100*100,3);
            }else  if($k==3){
                $user_rate = bcdiv($rate*$rate*$rate,100*100*100,3);
            }
            var_dump($user_rate);
            $k++;
        }

        die;

       // $push = new PushNotification('apn');
//        $push->setConfig([
//            'apn' => [
//                'certificate' => __ROOT__ .'public/cert/apple/dev_cert.pem',
//                'passPhrase' => '123456', //Optional
//                'passFile' => 'public/cert/apple/dev_key.pem', //Optional
//                'dry_run' => true,
//            ]
//        ]);
        $message = [
            'headers'=>'',
            'aps' => [
                'alert' => [
                    'title' => '1 Notification test',
                    'body' => 'Just for testing purposes'
                ],
                'sound' => 'default'
            ]
        ];

        $push->setMessage($message)
            ->setDevicesToken([
                'a2d2c089bfa49364d55d7008abda6ff26f7809e0752f768629980b17fbf952b8'
            ]);

        $push = $push->send()->getFeedback();
        var_dump($push);
        die;


//        $configpath =config('androidpush');
//        $config = $configpath['service_account_json_file'];
//        $json = file_get_contents($config);
//        $arr = json_decode($json,true);
//        dump($arr);die;


        //$token ='dI9q9MbTT02A5Bme8AShzZ:APA91bGsR8U_33fS14iAAxWX5mAR3b0ZqUz5_bymzqu4_qNO1uTe7USr5Hab8dNIO1kbeqlo28CMhpKTX49OkpohFBdByyHx2M0f2GDK0ELskvpCDEYv2VfRyNeobNIQwhcDnliiiWZW';
        $token ='a2d2c089bfa49364d55d7008abda6ff26f7809e0752f768629980b17fbf952b8';
        $state = Push::Send(2,$token,'消息推送通知测试');
        var_dump($state);
        die;

        //
//        $a = 10526;
//        $b =intval($a/10)*10;
//        var_dump($b);
//        die;


        $url = input('url');
        $ua  = $_SERVER['HTTP_USER_AGENT'];

        if (strpos($ua, 'MicroMessenger') === false && strpos($ua, 'Windows Phone') === false) {
            //普通浏览器
            Header("HTTP/1.1 303 See Other");
            Header("Location: $url");
            exit;
        } else {
            //安卓，苹果，PC版微信，winphone版微信
            return $this->fetch('index');
        }
    }


    public function push(){
        if(defined('CURL_HTTP_VERSION_2_0')){

            $device_token   = 'a2d2c089bfa49364d55d7008abda6ff26f7809e0752f768629980b17fbf952b8';
            //$pem_file     = 'path to your pem file';
            $pem_file       = "public/cert/apple/Sandbox.pem";
            //$pem_secret   = 'your pem secret';
            $pem_secret     = '123456';
            //$apns_topic   = 'your apns topic. Can be your app bundle ID';
            $apns_topic     = 'com.zhapp.fhzxg.BPushProject';

            $data = array(
                "aps"=>array(
                    'alert'=>'这是推送标题',
                    "sound"=>"default",
                    "badge"=>0,
                ),
                'app'=>array(
                    "title"=>"这是展示标题内容",
                    "content"=>"这是自定义内容",
                ),
            );
            $url = "https://api.development.push.apple.com:443/3/device/$device_token";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $j = json_encode($data));
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "apns-topic: $apns_topic",
            ));
            curl_setopt($ch, CURLOPT_SSLCERT, $pem_file);
            curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLCERTPASSWD, $pem_secret);
            $response = curl_exec($ch);
            var_dump($response);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if($httpcode == 200){
                echo "push ok";
            }
        }else{

            echo "error http2!";
        }
    }

    public function test3()
    {
        Db::name('user')
            ->where('id', 1)
            ->update([
                'last_login_ip' => '',
            ]);
    }

    public function test()
    {
        $ValueL32 = '4294966274';
        $ValueH32 = '4294967295';
        $x        = $ValueH32 . $ValueL32;
//        var_dump($x);
//        die;


        //$a = sprintf('%d','0x'.$x);
        $a = hexdec('0x' . $x);
        var_dump($a);
        die;

        $iTempL32 = (float)sprintf('%u', ($ValueL32 & 0xFFFFFFFF));
        $iTempI64 = ($ValueH32 * pow(2, 32)) + $iTempL32;

        var_dump($iTempI64);
        die;
    }

    public function test2()
    {

        $num    = -2023;
        $num    = floatval($num);
        $numL32 = $num & 0xFFFFFFFF;

        $numH32 = floor($num / pow(2, 32));

        $data = '';
        $data .= pack('L', $numL32);
        $data .= pack('L', $numH32);


        $out_data_Ctrl_array = unpack('lnL/lnH', $data);

        $iTempL32 = (float)sprintf('%u', ($out_data_Ctrl_array['nL'] & 0xFFFFFFFF));
        $iTempI64 = ($out_data_Ctrl_array['nH'] * pow(2, 32)) + $iTempL32;

        var_dump($out_data_Ctrl_array);
        var_dump($iTempI64);
        die;

    }

    public function test5()
    {
        //获取当前的域名:
        echo $_SERVER['SERVER_NAME'];
        echo $_SERVER['HTTP_HOST'];
        $http = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
        var_dump($http . $_SERVER['SERVER_NAME'] . '/admin/user/index');
        die;
    }

    public function testx()
    {
        header("Content-Type: text/html; charset=utf-8");

        $filename = dirname(__FILE__) . "/payPublicKey.pem";

        @chmod($filename, 0777);
        @unlink($filename);

        $devPubKey        = "MFwwDQYJKoZIhvcNAQEBBQADSwAwSAJBAKK9kzY3oGoRM3YZE04tYPXspSQDbfUduAN3E89v+Gu4ZuqUqOEstb4p7a01kEj8KwtyFUywH7cncygphQXcnRsCAwEAAQ==";
        $begin_public_key = "-----BEGIN PUBLIC KEY-----\r\n";
        $end_public_key   = "-----END PUBLIC KEY-----\r\n";


        $fp = fopen($filename, 'ab');
        fwrite($fp, $begin_public_key, strlen($begin_public_key));

        $raw   = strlen($devPubKey) / 64;
        $index = 0;
        while ($index <= $raw) {
            $line = substr($devPubKey, $index * 64, 64) . "\r\n";
            if (strlen(trim($line)) > 0)
                fwrite($fp, $line, strlen($line));
            $index++;
        }
        fwrite($fp, $end_public_key, strlen($end_public_key));
        fclose($fp);
    }


    public function aaa()
    {
        $a = '{"callbacks":"CODE_SUCCESS","type":"wechat","total":"200","api_order_sn":"20191126193723321011","order_sn":"191126-544686244991955","sign":"B1DA1CFA784FFF8FF3620D5EA1312BBB"}';
        $a = json_decode($a, true);

        $md5key = "f67c1cb67786d275cc046be39a8f653b3c87c1d8b1058eaa080b12980f5ad31e";
        ksort($a);
        $md5str = "";
        foreach ($a as $key => $val) {
            if ($key != 'sign') {
                $md5str = $md5str . $key . $val;
            }
        }
        $checksign = strtoupper(md5($md5key . $md5str . $md5key));  //签名
        var_dump($checksign, $a['sign']);
        die;
    }


    public function bbb()
    {
        $priKey    = file_get_contents(__DIR__ . '/privatekey.pem');
        $privKeyId = openssl_pkey_get_private($priKey);
        $signature = '';
        $algo      = "SHA256";

        $data = 'amount=0.20&applicationID=101407557&country=CN&currency=CNY&merchantId=70086000318506966&productDesc=测试&productName=测试商品2&requestId=20191128145144656049&sdkChannel=0&url=http://order.tianji007.com/huaweipay.php&urlver=2';
        openssl_sign($data, $signature, $privKeyId, $algo);
        openssl_free_key($privKeyId);
        $bs = base64_encode($signature);
        var_dump($bs);
        die;
    }

    public function generate()
    {
        header("Content-Type: text/html; charset=utf-8");

        $filename = dirname(__FILE__) . "/privatekey.pem";

        @chmod($filename, 0777);
        @unlink($filename);

        $devPubKey        = 'MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC7xHWEf7Gk/PfKDZKfWtvrClQFeNeVr9mcvCMHFzIQMJyzPpy1tEeGXPVM7ynuvE4zwc0P1JjTqNwDdcyiq5PcGOAtgTA1owD1Rjb15rwv1ACsLUKAA9n3W2WZmjaYby/9BMpA4sUk0dSwfKZwPbqosH4xgTODTRBxlHac7kWrXyu1cB4xMaey0MkbZCGXyCOw0l03oavnBGGGY2lmsHLluCsxR6WKzLDPgD3ABPnsqybqLvl1ySL7GIFKsvs9iz75NYLJzbZDTnwg/Wax0S9p9fxytQzeQV1QNk9Jw6J2upqtXU/4JbOync4xGepaOQTRwHtf/mKUwwLBE0ZXfq3ZAgMBAAECggEAEGJDmNSllQ5ntq85gIMmlls32qRhN1P5SoZWDhvVh/kd6zwG44oABbbdxqFFyOmQb061THDSBwIAdKLWQMl05OscwIu5v6xh/ITsbcd82zWF+4AVgeMUJVPJyT3eDq4BA3RkC4ZeCmjuxJmT0k5ol6iS0lICQy45xZddRDM8TAgjFwnBsTC9i7usif3TQQaaTGU2mhyv+Y/pQDN8dSGu1THdq7IMIPTebliDxjPd0V8P/Kp5wXamcVAx+7DjrXC3ArA0uYBuid4RDZxvQobiqrh+XQw5mjTawmBoUOq9Xnqy84T+U40ZuB1TvsfrarPDLlDhvQgA4t8btVogRqzCgQKBgQDkIh/BoUfs+RaoiNnCiRjooeAjE+sdfCsMefo7LyrxX/kPU5fgRl+LnhMWDtvVRHdPg/7QfuNewK0GLSd7W7CT75YrHUKLpsuRRHo3xwUOUW5JbMxnFLmtTbZoN/l/oUhAdc9xBCywaMdKGDRwsP0uoiqE33mzoki3wY+nGDyo4QKBgQDStBFdNrIHWGqKr2YfF44FfqlkLvs/U0jD/5jONXS4TOIwlx/DV5Yznu3JoHqNEL0gLhjF1KetzTRhwo/NlT2EDMTQ+7T/2HMeRK+Nmd/inmYH4IY+OMzgXsHEw3HjNcg9sM7bahf6wC1qPU6USr632k1IARQH7VoljWNcmRTL+QKBgFY6O6yRTEFaqODM0RoBfcO4I6K+jZiYbSELHbSvEFkpgFb1rqsbjlOUTPyCYz8J4NrSNkcSHtialQuHl6u9rVFNNoJXTebBBaKDsnpQpC2UQ85G7D9uCvxhKjfcKFbAXDHZFa5O+KE5CVKNMY0CqL+ulcmhOjvWdAvYgnaS56KhAoGAXsT5Dmj8eAtPmGM91nw8t8H5pILxJNFr6CQ9cXpfrkl+bwZ6Fd1+RGeWYlrY5DwEJMY3BDwa0zR5/AKLtZcLnSo1GB4ukeikFpgkMddk+MPv9lkJaFEZ7U0RcFPMFLrq/rxYvh2g/XqUsrUyc8aOs5jvq5Q4kzwxkLRgXZTI4tkCgYEArWiUxhPSBTbBgjsBD6nI0S9Gt6CE0a+RqvCU/qU6Elxe6r+71uoK/kpJR4VoqVnOGOR+SldZxKkL18hvBTd8qpH5I5VuV/KKzxqxN1oL5fHcLjzReS4eTo3yE6KtX8BCO+cQ6Y62+0ww6gF92WAqgMykLQQ/e/uE4x+pd+gqj54=';
        $begin_public_key = "-----BEGIN PRIVATE KEY-----\r\n";
        $end_public_key   = "-----END PRIVATE KEY-----\r\n";


        $fp = fopen($filename, 'ab');
        fwrite($fp, $begin_public_key, strlen($begin_public_key));

        $raw   = strlen($devPubKey) / 64;
        $index = 0;
        while ($index <= $raw) {
            $line = substr($devPubKey, $index * 64, 64) . "\r\n";
            if (strlen(trim($line)) > 0)
                fwrite($fp, $line, strlen($line));
            $index++;
        }
        fwrite($fp, $end_public_key, strlen($end_public_key));
        fclose($fp);
    }

    public function ccc()
    {
        var_dump(config('app_status'));
        die;
    }

    public function testrsa()
    {
        $config = array(
            "digest_alg"       => "sha512",
            "private_key_bits" => 512,                     //字节数    512 1024  2048   4096 等
            "private_key_type" => OPENSSL_KEYTYPE_RSA,     //加密类型
        );
        // $res = openssl_pkey_new($config);

//        if($res == false) return false;
//        openssl_pkey_export($res, $private_key);
//        $public_key = openssl_pkey_get_details($res);
//        $public_key = $public_key["key"];

        $pri = '-----BEGIN PRIVATE KEY-----
MIIBVQIBADANBgkqhkiG9w0BAQEFAASCAT8wggE7AgEAAkEA1DAyZyfvu8wVHAIc
lmpK8I7N2WTkIQUU787Ph9YKxJZf9AKU+OSD14uRposDlS3SUgK9bP+UE9JcaYgI
8VtUlwIDAQABAkEAvzVDZkPNu6x3ZUrd2gmkyEvXYcyR6tN6f3Mc/mo6P9Uhf86o
RDT8DIG8BliE0xiAwqrdukRZ56y7uYALtuTW6QIhAPfa+zsGvesFYA0mS56L3UOW
4K6xu92G+TaTyuGA8QYLAiEA2yks1p19usTavIKwqp2om0VT8wb5xy/gio2JAodS
fyUCIQDe3ked0fggRpsR9+dzTyzMw/SQ4Tyee+nHy6lYkIsp9QIge1wU4gSqFavi
l4NUn+S4WBXQ6BXAGK9JS5PZT/QNqoUCIAXXs2w8SLvROqJskVy9oqFjuGXC2SxK
Ma5FjFT9571G
-----END PRIVATE KEY-----';

        $m = 'D430326727EFBBCC151C021C966A4AF08ECDD964E4210514EFCECF87D60AC4965FF40294F8E483D78B91A68B03952DD25202BD6CFF9413D25C698808F15B5497';
        var_dump(strlen($m));
        die;
        $e = 0x10001;


        //var_dump($public_key, $private_key);die;
    }

    public function ddd()
    {
        $a = "{\"responseCode\":\"0000\"}";
        //echo json_encode(['responseCode'=>'0000']);
    }

    public function fff()
    {
        $key = '123456test';
        $cn = Redis::get($key);
        $res = $cn->send('6666666');
        var_dump($res);
        die;
    }

    public function testcc()
    {

        $search = '/^12*/';
        if(preg_match($search,'2200fdrr',$a)) {
           var_dump($a);
        } else {
            var_dump(2);
        }
    }

    public function aabb(){
        $data = (new \app\model\UserDB)->getTableObject('T_UserProxyInfo')->where('1=1')->select();
        foreach ($data as $key => &$val) {
            $val['ParentIds'] = str_replace(',', '', $val['ParentIds']);
            $new_d = chunk_split($val['ParentIds'],8,",");
            $new_d = trim($new_d,',');
            (new \app\model\UserDB)->getTableObject('T_UserProxyInfo')->where('RoleID',$val['RoleID'])->data(['ParentIds'=>$new_d])->update();
        }
    }

    public function aaaaaa(){
        $text = file_get_contents('./abc.txt');
        str_replace(' ', ',', $text);
        var_dump($text);die();
        foreach ($text as $cont){
            // $cont2 = explode('{', $cont);
            // $cont2 = '{'.$cont2[1].'}';

            // $arr = json_decode($cont2,true);

            // $userid = explode('abc', $arr['uid'])[1];
            // $amount = $arr['amount'];
            // Db::table('tp_index')->insert([
            //     'uid'=>$userid,
            //     'amount'=>$amount,
            //     'transfer_id'=>$arr['transferId']
            // ]);
        } 
        
    }


    public function setadmin(){
//        $admin = input('admin')?:'admin03';
//        $id = input('id')?:'4';
//        $be = Db::table('game_user')->where('username',$admin)->find();
//        if ($be) {
//            $res = Db::table('game_user')->where('username',$admin)->update(['id'=>$id]);
//            $res = Db::table('game_auth_group_access')->where('uid',$be['id'])->update(['uid'=>$id]);
//            var_dump(1);die();
//        }
//        var_dump(2);die();
        if ($this->request->isAjax()){
            $admin = request()->param('admin');
            $adminId = request()->param('admin_id');
            $be = Db::table('game_user')->where('id',$adminId)->find();
            if (!empty($be)){
                return ['code'=>500 , 'msg'=> 'ID已被管理员：'.$be['username'].' 使用;请先将此管理员ID进行修改'];
            }
            $user = Db::table('game_user')->where('username',$admin)->find();
            if ($user){
                Db::table('game_user')->where('username',$admin)->update(['id'=>$adminId]);
                Db::table('game_auth_group_access')->where('uid',$user['id'])->update(['uid'=>$adminId]);
                return ['code'=>0 , 'msg'=> '管理员 '.$admin.' ID修改成功'];
            }
            return ['code'=>500 , 'msg'=> '没有查找到此管理员'];
        }else{
            return $this->fetch();
        }
        
    }
}
