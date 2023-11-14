<?php

namespace app\merchant\controller;

use app\common\Api;
use app\common\GameLog;
use app\model\UserDB;
use app\model\MasterDB;
use think\Db;
use app\model\GameOCDB;
use think\Exception;

class My extends Main
{

    public function oplink(){
        $m = new \app\model\MasterDB();
        $InviteUrlModel = $m->getTableObject('T_GameConfig')->where('CfgType',113)->value('keyValue');
        $InviteUrlModel =  str_replace("ic=", 'ch=', $InviteUrlModel);
        $link =  str_replace("{inviteCode}", session('merchant_OperatorId'), $InviteUrlModel);
        $this->assign('link',$link);
        return $this->fetch();
    }


    //系统盈利报表
    // 总充值
    // 批量赠送
    // 手动上分
    // 邮件赠送
    // 总提现
    // 充值手续费
    // 提现手续费
    // API费用
    // 总利润
    // 汇率计算

    public function monthReport()
    {
        $date = $this->request->param('date');
        if(empty($date)){
            $date = date('Y-m');
        }
        $db = new GameOCDB();
        $where = ' OperatorId='.session('merchant_OperatorId');
        $firstdate = $date . '-01';
        $where .= " and mydate>='$firstdate' ";
        $lasttime = strtotime("$firstdate +1 month -1 day");
        $lastdate = date('Y-m-d',$lasttime);
        $where .= " and mydate<='$lastdate'";
        $field = 'sum(convert(bigint,TotalBatchMail)) TotalBatchMail,
        sum(convert(bigint,TotalGMPoint)) TotalGMPoint,
        sum(convert(bigint,TotalMailCoin)) TotalMailCoin,
        sum(convert(bigint,ppgamewin)) as ppgamewin,
        sum(convert(bigint,pggamewin)) as pggamewin,
        sum(convert(bigint,evolivewin)) as evolivewin,
        sum(convert(bigint,habawin)) as habawin,
        sum(convert(bigint,spribe)) as spribewin,     
        sum(convert(bigint,jiliwin)) as jiliwin,
        sum(convert(bigint,yesbingo)) as yesbingo        
        ';

        $total = $db->getTableObject('T_Operator_GameStatisticTotal')->where($where)->field($field)->find();
        $pay = $db->getTableObject('T_Operator_GameStatisticPay')->where($where)->field('sum(convert(bigint,totalpay)) totalpay')->find();
        $out = $db->getTableObject('T_Operator_GameStatisticPayOut')->where($where)->field('sum(convert(bigint,totalpayout)) totalpayout')->find();
        // $user = $db->getTableObject('T_Operator_GameStatisticTotal')->where($where)->find();
        $config = (new MasterDB)->getTableObject('T_OperatorLink')->where('OperatorId',session('merchant_OperatorId'))->find();
        $data = [];

        $data['total_recharge'] = FormatMoney($pay['totalpay'] ?? 0);
        $data['TotalBatchMail'] = FormatMoney($total['TotalBatchMail'] ?? 0);
        $data['TotalGMPoint'] = FormatMoney($total['TotalGMPoint'] ?? 0);
        $data['TotalMailCoin'] = FormatMoney($total['TotalMailCoin'] ?? 0);
        $data['totalpayout'] = FormatMoney($out['totalpayout'] ?? 0);

        $total['ppgamewin'] = FormatMoney($total['ppgamewin'] ?? 0);
        $total['pggamewin'] = FormatMoney($total['pggamewin'] ?? 0);
        $total['evolivewin'] = FormatMoney($total['evolivewin'] ?? 0);
        $total['spribewin'] = FormatMoney($total['spribewin'] ?? 0);
        $total['habawin'] = FormatMoney($total['habawin'] ?? 0);
        $total['jiliwin'] = FormatMoney($total['jiliwin'] ?? 0);
        $total['hacksaw'] = FormatMoney($total['hacksaw'] ?? 0);
        $total['yesbingo'] = FormatMoney($total['yesbingo'] ?? 0);

        $data['recharge_fee'] =bcmul($data['total_recharge'] , $config['RechargeFee'],3);
        $data['payout_fee'] = bcmul($data['totalpayout'] ,$config['WithdrawalFee'],3);
        $APIFee = explode(',', $config['APIFee']);
        $APIFee[0] = $APIFee[0] ?? 0; //pp
        $APIFee[1] = $APIFee[1] ?? 0; //pg
        $APIFee[2] = $APIFee[2] ?? 0; //evo
        $APIFee[3] = $APIFee[3] ?? 0; //spribe
        $APIFee[4] = $APIFee[4] ?? 0; //haba
        $APIFee[5] = $APIFee[5] ?? 0; //jiliwin
        $APIFee[6] = $APIFee[6] ?? 0; //yesbingo

        $TotalAPICost =0;
        $totalpp=bcmul($APIFee[0],$total['ppgamewin'],4);
        $totalpg=bcmul($APIFee[1],$total['pggamewin'],4);
        $totalevo=bcmul($APIFee[2],$total['evolivewin'],4);
        $spribewin=bcmul($APIFee[3],$total['spribewin'],4);
        $totalhaba=bcmul($APIFee[4],$total['habawin'],4);
//        $totalhacksaw = bcmul($APIFee[5], $total['hacksaw'], 4);
        $totaljiliwin = bcmul($APIFee[5], $total['jiliwin'], 4);
        $totalyesbingo = bcmul($APIFee[6], $total['yesbingo'], 4);



        if($totalpp<0){//系统赢算费用
            $TotalAPICost+= abs($totalpp);
        }
        if($totalpg<0){//系统赢算费用
            $TotalAPICost+= abs($totalpg);
        }
        if($totalevo<0){//系统赢算费用
            $TotalAPICost+= abs($totalevo);
        }
        if($spribewin<0){//系统赢算费用
            $TotalAPICost+= abs($spribewin);
        }
        if($totalhaba<0){//系统赢算费用
            $TotalAPICost+= abs($totalhaba);
        }
//        if ($totalhacksaw < 0) {//系统赢算费用
//            $TotalAPICost += abs($totalhacksaw);
//        }

        if ($totaljiliwin < 0) {//系统赢算费用
            $TotalAPICost += abs($totaljiliwin);
        }

        if ($totalyesbingo < 0) {//系统赢算费用
            $TotalAPICost += abs($totalyesbingo);
        }


        $data['TotalAPICost'] = round($TotalAPICost,3);
        $data['totalprofit'] = round(($data['total_recharge'])-($data['totalpayout']+$data['recharge_fee']+$data['payout_fee']+$data['TotalAPICost']),3);

        if ($this->request->isAjax()) {
            if (empty($total)) {
                return $this->failJSON(lang('该月份没有任何数据'));

            } else {
                $data['onlinedata'] = config('record_start_time');
                return $this->successJSON($data);
            }
        }

        $this->assign('onlinedata', config('record_start_time'));
        $this->assign('data', $data);
        $this->assign('thismonth', date('Y-m'));
        return $this->fetch();
    }



    public function subAccount(){

        if($this->request->isAjax())
        {
            $opname = input('opname','');
            $page = intval(input('page')) ? intval(input('page')) : 1;
            $limit = intval(input('limit')) ? intval(input('limit')) : 15;
            $operatorId = session('merchant_OperatorId');
            $where=' AccountType=1 and OperatorId='.$operatorId;
            if(!empty($opname)){
                $where.=" and  OperatorName like '%$opname%'";
            }
            $db= new GameOCDB();
            $field ='Id,OperatorName,AddTime,LastLoginTime,google_verify';
            $data = $db->getTableList('T_OperatorSubAccount',$where,$page,$limit,$field,'addtime desc');
            foreach ($data['list'] as $k=>&$v){
                $v['IsGoogle'] ='否';
                if(!empty($v['google_verify'])){
                    $v['IsGoogle'] ='是';
                }
                unset($v['google_verify']);
            }
            unset($v);
            return $this->apiJson($data);
        }
        return $this->fetch();
    }


    public function addSubaccount(){
        $id= input('Id');
        $db=new GameOCDB();
        $operatorId = session('merchant_OperatorId');
        if($this->request->isAjax())
        {
            $operatorname = input('OperatorName','');
            $password =input('txtpassword','');
            $repassword =input('repassword','');

            if(empty($password)){
                return $this->failJSON(lang('请输入需修改的密码'));
            }
            if($password!='' && $password!=$repassword){
                return $this->failJSON(lang('两次输入密码不一致'));
            }

            if($id>0){
                $save_data=[
                    'PassWord' => md5($password)
                ];
                $db->updateTable('T_OperatorSubAccount',$save_data,'Id='.$id);
                return $this->successJSON([],lang('密码修改成功'));
            }
            else {
                $count = $db->getTableRow('T_OperatorSubAccount',"OperatorName='$operatorname'",'*');
                if(!empty($count)){
                    return $this->failJSON(lang('账号名称已存在，请选择其他账号'));
                }
                $save_data=[
                    'OperatorName'=>$operatorname,
                    'PassWord' => md5($password),
                    'AccountType' =>1,
                    'AddTime' => date('Y-m-d H:i:s'),
                    'LastLoginTime' => date('Y-m-d H:i:s'),
                    'OperatorId' =>$operatorId
                ];
                $db->setTable('T_OperatorSubAccount')->Insert($save_data);
                return $this->successJSON([],lang('恭喜，账号添加成功'));
            }
        }
        $data=['Id'=>0,'OperatorName'=>''];
        if($id>0){
            $where=' AccountType=1 and OperatorId='.$operatorId.' and Id='.$id;
            $data= $db->getTableRow('T_OperatorSubAccount',$where,'Id,OperatorName,OperatorId,AccountType');
        }
        $this->assign('info',$data);
        return $this->fetch();
    }


    public function delSubaccount(){
        $id= input('Id');
        if(empty($id) || $id==0){
            return $this->failJSON(lang('参数错误'));
        }
        $db=new GameOCDB();
        $operatorId = session('merchant_OperatorId');
        $where=' AccountType=1 and OperatorId='.$operatorId.' and Id='.$id;
        $status = $db->delTableRow('T_OperatorSubAccount',$where);
        if($status){
            return $this->successJSON([],lang('账号已删除'));
        }
        else{
            return $this->failJSON(lang('账号删除失败，请确认'));
        }
    }


    public function UbindSubaccount(){
        $id= input('Id');
        if(empty($id) || $id==0){
            return $this->failJSON(lang('参数错误'));
        }
        $db=new GameOCDB();
        $operatorId = session('merchant_OperatorId');
        $where=' AccountType=1 and OperatorId='.$operatorId.' and Id='.$id;
        $status = $db->updateTable('T_OperatorSubAccount',['google_verify'=>''],$where);
        if($status){
            return $this->successJSON([],lang('Google验证码已解绑'));
        }
        else{
            return $this->failJSON(lang('Google验证码解绑失败，请确认'));
        }
    }












}