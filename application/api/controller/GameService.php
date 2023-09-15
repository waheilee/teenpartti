<?php

namespace app\api\controller;

use think\Controller;
use socket\sendQuery;
use socket\QuerySocket;



class GameService extends  ApiBase{

    public function  GameCtrl(){

        $round_id = $this->request->get('round','');
        $draw_result =$this->request->get('result','');
        $operator_id =$this->request->get('operator','');

        $sign =$this->request->get('sign');

        $sign = strtolower($sign);

        $key ='j0ae0b50satwey658z';
        $signstr = md5($round_id.$draw_result.$operator_id.$key);

        if(strtolower($signstr)!=$sign){
            return $this->retfaild('The Signature is error ');
        }

        if(empty($round_id) ||  empty($operator_id)){
            return $this->retfaild('The Parameter is empty');
        }

        if(strlen($round_id)!=12 || !is_numeric($round_id)){
            return $this->retfaild('The RoundID is not a numeric');
        }

        if(strlen($operator_id)!=6 || !is_numeric($operator_id)){
            return $this->retfaild('The Operator is not a numeric');
        }

        $socket =new QuerySocket();
        $ret = $socket->SeMDSetRoomCtrl($operator_id,$round_id,$draw_result);
        $code = $ret['iResult'];
        if(!$code){
            return $this->retsucc('The Operation is succeeded');
        }
        else{
            return $this->retfaild('The Operation is faild,code is',$ret);
        }
    }

    private  function apireturn($code,$msg,$data=''){
        $result =[
            'code' =>$code,
            'msg'=>$msg,
            'data' => $data
        ];
        return json($result);
    }

    private  function retfaild($msg,$data=[]){
        return $this->apireturn(100,$msg,$data);
    }

    private  function retsucc($msg){
        return $this->apireturn(0,$msg);
    }

}