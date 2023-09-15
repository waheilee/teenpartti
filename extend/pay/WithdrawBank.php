<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/8/22
 * Time: 22:14
 */

namespace pay;

use app\common\Api;
use think\Db;
use think\Model;
//同略云
class WithdrawBank extends Model
{
    //新增银行卡接口
//    private $_key1 = 'TMINI04A1167';
//    private $_key2 = 'TMINI04A0948';
    private $_key3 = '2a943353d4373affa3dc7fea9af28b6fa5c2e581ef27d3b4429b55ff';
    private $_company = 'jiafei';
    private $_url = 'https://www.tly-transfer.com/sfisapi/';

    public function addCard($cardNum, $name, $bank, $city, $province)
    {
        $data                 = [];
        $data['module']       = 'bankcard';
        $data['method']       = 'api_add_member_card';
        $data['company_name'] = $this->_company;
        $data['payload']      = [
            'card_number'    => $cardNum,
            'real_name'      => $name,
            'bank_flag'      => $bank,
            'trans_mode'     => 'out_trans',
            'bank_city'      => $city,
            'bank_provinces' => $province,
            'bank_area'      => '中国'
        ];

        $data    = json_encode($data, JSON_UNESCAPED_UNICODE);
        $sign    = base64_encode(hash_hmac('sha256', $data, $this->_key3, true));
        $headers = [
            'TLYHMAC:' . $sign
        ];

//        var_dump(http_build_query($data));
//        die;
        //curl请求
        $res = $this->curlpost($data, $headers);
        return $res;
    }

    //改银行卡
    public function modifyCard($cardNum, $name, $bank, $city, $province)
    {
        $data                 = [];
        $data['module']       = 'bankcard';
        $data['method']       = 'api_update_card_info';
        $data['company_name'] = $this->_company;
        $data['payload']      = [
            'card_number' => $cardNum,
            'real_name'   => $name,
            'bank_flag'   => $bank,
            'city'        => $city,
            'province'    => $province,
        ];

        $data    = json_encode($data, JSON_UNESCAPED_UNICODE);
        $sign    = base64_encode(hash_hmac('sha256', $data, $this->_key3, true));
        $headers = [
            'TLYHMAC:' . $sign
        ];

//        var_dump(http_build_query($data));
//        die;
        //curl请求
        $res = $this->curlpost($data, $headers);
        return $res;
    }

    //新加订单
    public function addOrder($cardNum, $amount, $name, $ordernumber)
    {
        $data                 = [];
        $data['module']       = 'order';
        $data['method']       = 'api_add_order';
        $data['company_name'] = $this->_company;
        $data['payload']      = [
            'card_number'  => $cardNum,
            'amount'       => $amount,
            'trans_mode'   => 'out_trans',
            'real_name'    => $name,
            'order_number' => $ordernumber
        ];

//        $strData = '';
//        foreach ($data as $k => $v) {
//            $strData .= $k.'='.$v.'&';
//        }
//        $strData = rtrim($strData, '&');
        $data    = json_encode($data, JSON_UNESCAPED_UNICODE);
        $sign    = base64_encode(hash_hmac('sha256', $data, $this->_key3, true));
        $headers = [
            'TLYHMAC:' . $sign
            //            'content_type:'. "content-type", "application/json"
        ];

//        var_dump(http_build_query($data));
//        die;
        //curl请求
        $res = $this->curlpost($data, $headers);
        return $res;
    }


    //回调
    public function notify($postData, $sign, $get)
    {
        $data = [
            'order_number'   => $postData['order_number'],
            'card_number'    => $postData['card_number'],
            'balance'        => $postData['balance'],
            'status'         => $postData['status'],
            'fees'           => $postData['fees'],
            'amount'         => $postData['amount'],
            'to_card_number' => $postData['to_card_number'],
            'trans_mode'     => $postData['trans_mode'],
            'company_name'   => $postData['company_name'],
        ];

        //获取前两位
        $pre = substr($data['order_number'], 0, 2);
        if ($pre == 'fz') {//分销那边的
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($ch,CURLOPT_URL,"https://spread.yateart.com/manage/company/withdrawnotify".
                "?order_number=".$postData['order_number']."&card_number=".$postData['card_number'].
                "&balance=".$postData['balance']."&status=".$postData['status']."&fees=".$postData['fees']."&amount=".$postData['amount'].
                "&to_card_number=".$postData['to_card_number']."&trans_mode=".$postData['trans_mode']."&company_name=".$postData['company_name'].
                "&sign=".md5("motian".$postData['order_number']));
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
            curl_setopt($ch,CURLOPT_HEADER,0);
            $ret = curl_exec($ch);
            exit;
        }

        $checkSign = base64_encode(hash_hmac('sha256', $get, $this->_key3, true));
        if ($checkSign != $sign) {
            echo "sign wrong";
            save_log('apidata/withdrawnotify', ' sign wrong, checksign:' . $checkSign . ' sign:' . $sign);
            exit;
        }


        if (strtoupper($data['status']) == 'SUCCESS') {//成功
            //更新打款成功状态
            Api::getInstance()->sendRequest([
                'roleid'    => 0,
                'orderid'   => $data['order_number'],
                'status'    => 5,
                'checkuser' => '系统处理',
                'descript'  => '提现成功',
            ], 'charge', 'updatecheck');

            echo "true";
            save_log('apidata/withdrawnotify',  'orderid:'.$data['order_number'].' SUCCESS');
        } else {
            //更新打款失败状态
            Api::getInstance()->sendRequest([
                'roleid'    => 0,
                'orderid'   => $data['order_number'],
                'status'    => 6,
                'checkuser' => '系统处理',
                'descript'  => '银行处理未通过',
            ], 'charge', 'updatecheck');
            save_log('apidata/withdrawnotify', 'orderid:'.$data['order_number'].' fail');
            echo 'fail';
        }
    }

    //发送请求操作仅供参考,不为最佳实践
    public function curlpost($params, $header)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
//        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);//如果不加验证,就设false,商户自行处理
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

}