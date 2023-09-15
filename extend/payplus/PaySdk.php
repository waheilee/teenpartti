<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

namespace payplus;

use Utility\Utility;
use think\facade\Cache;

class PaySdk
{


    private $api_url = '';
    private $notify_url = '';
    private $appid = '';
    private $secret = '';


    //平台公钥
    private $publicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA0+JGzjRdFIyVQj1xIyetE/n5YBjRXTIX5yVOE9MVObdYjE6Mow0yHVr24i81c4w3cpLRbPRaHcEIZud0qTnGh1tBU0qo7MqZh0FEb0sgoPpoHVTULevQmVRgA/iPI7u2KuXQ85AwiiiQzXaiCACAA1Bz/MhtIYYq5YY0wuSPUTYZ+9z+hsLnKbUxTBibIs0gSKfggCGDMzlLQ24t6y8L6Uvw8/+dasFjTdCxlvn8XTcCU9hM19brzenZCU/EuihiamL6FREV6UjMebH3ZeQoapfD2XexDLmeIb0PKS+1QSWoKxlm+PXULru84W0Jd8PV5nH9wgcuTtEPu4xrgpip7wIDAQAB';
//商户私钥
    private $privateKey = 'MIIEwAIBADANBgkqhkiG9w0BAQEFAASCBKowggSmAgEAAoIBAQDJ/Qb75nEFxQbdfOLSbg3kIGetOmrUe1PtOWPawdgVg1XHMshGCo0EcP0xlYCNmgjlWCdB5hyrur77zCOqrFa5GDVNYItE/KvD0I8E1jLvdOXmXZrXYGazIfa2+ai6HCLfDX1/GtwEW9r2HhBdt0ArSOiyrfX0udHFBj0BBHta7mEdofWx5Tvvm68XB38nwdzCRqPUZtmVf60kgGbD3xub+hy8o2m6JU9+cTXO5S/eomzgZDVvP9RMG8tWj0Y1XcsbH/cr415+btkOJK/nZRWZUXowOr1BA/xXZ4CzpSpAtNk5FgwL07q2ong03hjdxEZGnHAt/o1liels3+P/wmx7AgMBAAECggEBAIHpWp+TVCgY09SKqTwcipSp/uSciO9GrvEJk160hC05/maTE9pwmMg9f6tvc3Ifmw8fBojM3q3Y+1LpthrkoxaDKm0s5gYl2LeloQbEWZhHgEIM/DUADK2z74E5y7p/tDHv9EJW3SF0jrzzEyWjYgM07m1Vk7al+PQWkg/geRI+0La8Y2vn4ilZlNy1p59+j/12mU/tr934kwjKN9IijLUS4Cu3zpeUHYNZ4j+lwNCe+tOnTg4QBDcgix0tSUAVeS8AGajBwI1tPY7bG/zNmFrqPCxlXBTV5G+W2x3JSEFycQbNBkwj3PF1ZMjryUs8Xga2OmWSE7S66RPQg6tWLQECgYEA7QF9X3UPECdGeAtm0XVDE6Dcx9rOhraUSHdIDvkZ8JeFXoakTxc7DkOeoV+ggGrs27JGVGm0lJT/hKh0nZIStGCwIsuFcVW4sJLSoJZ7ZT8eGcFLaj75rwtENLD2ukuhtM1feEXwuUxm7C4mUghXZ9iXF/4dgFV0qJ5l7vBI04ECgYEA2i0a9aYlxlMvP7dqsjJdEbtYrdHq3+jPdi8d0p/fGjh9SWv+yU4bm1HeZSAso1CnocsKXe5cgA5YHal61zNNK5sScnAHF9RlUK5VRH0+whC7tLEgKeVbEHzSDHkDXhdM3GtJqY0ckZh5M+MZl/cRYjQzVnOFk+yE/icSVHZdjfsCgYEA0oMnO/l6pqtsAUaHTfas3KteTzn/hVJ4xSEF5R7HNpcvRDWtjf6hWtse9FE++7F9rupbY7D8T5lEmC0UX70WVhcne9BwN6mfQV84LKFc+yIj91ZkSPukxSDptS+WBwUUncZpTSg6WCwPoyqeqPB1ymxsUEhLJelBlGAVRDUzSoECgYEA2YKoYwjOlidubo50j81IHhpx8XDbQXmAA2o7yDVcnm588Yr6S0VUnoeDObxW5EbPqKyc3EJ786rZTFEfx5Y8tGF4haCMYcR9cW8sUQiwXZeDG0SPNVWUcR6P5qFqqw59sS5BFQk61yh0hTc+19MYgJhcKi8nl+7wM9VOH+iVoqsCgYEAr5J6iOrE6SdAyDDJA6JARmzF439KIBxWDKO+zFjytlCtmM1i68xxIM5yqsSuyTZ7yG1sqNz17HO57ACIj2OkELv1fdZJBtcVIzVvoV6CgaMRKjRbrqmBmR0cpugv3rO3g2pMItbtPGkPHAAoZZ6vf7n/aV4h/xarH5gmoOWt0eU=';


    public function __construct()
    {
        $this->api_url = 'http://www.payplus.cash/api';
        $this->appid = '202112190';
        $this->secret = 'IylemXbMSYxojQLTgHwaEipkJBZtPRWc';
    }


    public function payout($OrderNo, $order, $config = [])
    {

        if (empty($config)) {
            return array('status' => FALSE, 'message' => 'Missing parameter');
        }
        if (isset($config['appid']) && !empty($config['appid'])) {
            $this->appid = $config['appid'];
        }
        if (isset($config['secret']) && !empty($config['secret'])) {
            $this->secret = $config['secret'];
        } else {
            $this->secret = '';
        }
        if (isset($config['apiurl']) && !empty($config['apiurl'])) {
            $this->api_url = $config['apiurl'];
        } else {
            $this->api_url = '';
        }
        if (!isset($config['notify_url']) || empty($config['notify_url'])) {
            return array('status' => FALSE, 'message' => 'Missing notify_url');
        }

        $data = [
            'merId' =>strval($this->appid),               //商户号
            'orderId' =>$OrderNo,            //订单号，值允许英文数字
            'money' =>sprintf('%.2f',$order['RealMoney']),              //订单金额,单位元保留两位小数
            'name' => strval($order['RealName']),
            'ka' => strval(trim($order['CardNo'])),
            'zhihang' => strval($order['Province']),
            'bank'=>'bankofindia',
            'notifyUrl' =>$config['notify_url'],   //异步返回地址
            'nonceStr' => Random::alnum('32')   //随机字符串不超过32位
        ];

        $data['sign'] =$this->gensign($data,$config['secret']);
        $header = [
            'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
        ];
        $result =$this->curl_post_content($this->api_url .'/pay/repay', http_build_query($data), $header);
        save_log('payplus', '提交参数:' . json_encode($data) . ',接口返回信息：' . $result);
        $res = json_decode($result, true);
        $result = ['system_ref' => '', 'message' => ''];
        if ($res && $res['code'] === 1) {
            $result['system_ref'] = $res['data']['nonceStr'];
            $result['status'] = true;
            $result['message'] = 'success';
        } else {
            $result['message'] = $res['msg'];
            $result['status'] = false;
        }
        return $result;
    }

    private function curl_post_content($url, $data = null, $header = [])
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


    public function gensign($data,$md5Key){
        ksort($data);
        reset($data);
        $arg = '';
        foreach ($data as $key => $val) {
            //空值不参与签名
            if ($val == '' || $key == 'sign') {
                continue;
            }
            $arg .= ($key . '=' . $val . '&');
        }
        $arg = $arg . 'key=' . $md5Key;
        //签名数据转换为大写
        $sig_data = strtoupper(md5($arg));
        //使用RSA签名
        $rsa = new Rsa('', $this->privateKey);
        //私钥签名
        return $rsa->sign($sig_data);
    }

    public function verify($data,$md5Key){
        //验签
        ksort($data);
        reset($data);
        $arg = '';
        foreach ($data as $key => $val) {
            //空值不参与签名
            if ($val == '' || $key == 'sign') {
                continue;
            }
            $arg .= ($key . '=' . $val . '&');
        }
        $arg = $arg . 'key=' . $md5Key;
        $signData = strtoupper(md5($arg));
        $rsa = new Rsa($this->publicKey, '');
        if ($rsa->verify($signData, $data['sign']) == 1) {
            return true;
        }
        return false;
    }



}