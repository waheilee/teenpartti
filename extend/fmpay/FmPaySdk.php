<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */
namespace fmpay;
use Utility\Utility;
use think\facade\Cache;

class FmPaySdk {


    private $api_url = '';
    private $notify_url = '';
    private $appid = '';
    private $secret = '';

    private $withdraw_conf = [
        'api_url' => 'https://gateway.fmpay.org/withdraw/gateway',
        'secret' => '5ebfb4308a601e9717e8950dbb06b1f2',
        'notify_url' => '',
    ];

    public function __construct()
    {
        $this->api_url = 'https://gateway.fhyl.ooo/payment/gateway';
        $this->appid = '109682';
        $this->secret = '1584872887a968f4d85c6e8e0e78bcf6';
    }


    public function pay($param,$config=[])
    {
        if($config){
            $this->appid =$config['appid'];
            $this->secret = $config['secret'];
        }
        $post_data =[
            'partner_id'=> $this->appid,
            'pay_type' => '0001',
            'bank_code'=> $config['bankcode'],
            'version' => 'V1.0',
            'order_no'=> $param['orderid'],
            'amount'=> $param['amount'],
            'notify_url'=> $config['notify_url']
        ];
        $sn = $this->genSn($post_data);
        $post_data['sign'] = $sn;
        $info = $this->http($this->api_url,'POST',$post_data);
        return $info;
    }

    public function payout($OrderNo,$order,$config=[]){
        if (empty($config)) {
          return array('status'=>FALSE, 'message' => 'Missing parameter'); 
        }
        if (isset($config['appid']) && !empty($config['appid'])) {
            $this->appid = $config['appid'];
        }
        if (isset($config['secret']) && !empty($config['secret'])) {
            $this->secret = $config['secret'];
        } else {
            $this->secret = $this->withdraw_conf['secret'];
        }
        if (isset($config['apiurl']) && !empty($config['apiurl'])) {
            $this->api_url = $config['apiurl'];
        } else {
            $this->api_url = $this->withdraw_conf['api_url'];
        }
        if (!isset($config['notify_url']) || empty($config['notify_url'])) {
            return array('status'=>FALSE, 'message' => 'Missing notify_url'); 
        }
        // 替换成用户选择的银行
        if (!isset($order['BankName']) || empty($order['BankName'])) {
            return array('status'=>FALSE, 'message' => 'Missing BankName'); 
        }
        
        $post_data = [
            'version' => 'V1.0',
            'partner_id' => $this->appid,
            'order_no'=> $OrderNo,
            'amount' => $order['RealMoney'],
            
            'bank_code'=> $order['BankName'], // 银行代码
            'account_no' =>$order['CardNo'], // 银行账号
            'account_name' =>$order['RealName'], // 收款人姓名
            'notify_url' => $config['notify_url'],
        ];
        $sn = $this->genSn($post_data);
        $post_data['sign'] = $sn;
        $info = $this->http($this->api_url,'POST',$post_data);

        $result =['system_ref'=>'','message'=>$info['message']];
        if ($info && $info['code'] == "00") {
            $result['system_ref'] = $info['trade_no'];
            $result['status'] = true;
            $result['message'] = 'success';
        } else {
            $result['status'] = false;
        }
        return $result;
    }


    private function http($url = '', $method = 'POST', $postData = array(), $header = array()) {
        if (!empty($url)) {
            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30); //30秒超时
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

                if ($header) {
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
                }

                if (strtoupper($method) == 'POST') {
                    $curlPost = is_array($postData) ? http_build_query($postData) : $postData;
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
                }

                if (strtoupper($method) == 'GET') {
                    curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($postData));
                }
                $res      = curl_exec($ch);
                save_log('fmpay','curl resp:' . $res);
                $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $err      = curl_errno($ch);
                curl_close($ch);

                if ($err) {
                    throw new \Exception("curl_errno  error:".$err);
                }
                $res = json_decode($res, true);
                if ($httpcode != 200) {
                    throw new \Exception("httpcode != 200");
                }
//                if ($res['code']!='00'){
//                    return false;
//                }
//                $res = str_replace("<script type='text/javascript'>window.location.href='",'',$res);
//                $res= str_replace("'</script>",'',$res);
                return $res;
            } catch (\Exception $e) {
                return array('code'=> "99", 'message'=> $e->getMessage());
            }
        }
        return array('code'=> "99", 'message'=> 'url is empty');
    }


    private  function genSn($data)
    {
        // 按照ASCII码升序排序
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if ('sign' != $key && '' != $value) {
                $str.= $key .'=' . $value . '&';
            }
        }
        $str .= $this->secret;
        return strtolower(md5($str));
    }


}