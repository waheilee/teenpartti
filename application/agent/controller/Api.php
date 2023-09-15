<?php
namespace app\agent\controller;

use think\Controller;
use think\Lang;

class Api extends Controller
{
    public function _initialize() {
        parent::_initialize();
        //跨域处理
        header('Access-Control-Allow-Origin:*');
        Lang::setAllowLangList(['zh-cn','br']);
        cookie('think_var',$this->request->param('lang') ?: 'br');
    }

    private function retData($code = 0, $msg = 'success', $data = []){
        echo json_encode([
            'code'=>$code,
            'msg' =>lang($msg),
            'data'=>$data
        ],320);
        die();
    }

    public function login(){
        $account  = $this->request->param('account');
        $type     = $this->request->param('type') ?: 1;
        $password = $this->request->param('password');

        if ($account == '') {
            $this->retData(1,'Por favor, introduza o número da conta');
        }
        if ($password == '') {
            $this->retData(1,'Por favor, introduz uma senha');
        }
        $user_info = (new \app\model\AccountDB())->getTableObject('T_Accounts')
                        ->where('AccountName=\''.$account.'\' or AccountName=\'55'.$account.'\'')
                        ->find();
        if (empty($user_info)) {
            $this->retData(1,'A conta não existea');
        }
        $ProxyInfo = (new \app\model\UserDB())->getTableObject('T_UserProxyInfo(nolock)')->where('RoleID',$user_info['AccountID'])->find();
        if ($ProxyInfo['MobileBackgroundSwitch'] != 1) {
            $this->retData(1,'No permission');
        }
        if (md5($password) != $user_info['Password']) {
            $this->retData(1,'Erro de contrasinal');
        }
        unset($user_info['Password']);
        $user_info['token'] = md5(md5($user_info['AccountName'].'-br123'));
        $this->retData(0,'success',$user_info);
    }


    public function index(){
        $token  = $this->request->param('token');
        $uid    = $this->request->param('uid');
        if ($uid == '' || $token == '') {
            $this->retData(-1,'Parâmetro faltante');
        }

        $user_info = (new \app\model\AccountDB())->getTableObject('T_Accounts(nolock)')
                        ->where('AccountID',$uid)
                        ->find();
        if (empty($user_info)) {
            $this->retData(-1,'A conta não existea');
        }
        $ProxyInfo = (new \app\model\UserDB())->getTableObject('T_UserProxyInfo(nolock)')->where('RoleID',$uid)->find();
        if ($ProxyInfo['MobileBackgroundSwitch'] != 1) {
            $this->retData(-1,'No permission');
        }
        if (md5(md5($user_info['AccountName'].'-br123')) != $token) {
            $this->retData(-1,'Erro de sinal');
        }
        $data = [];
        
        $data['userinfo'] = [
            'AccountID'=>$user_info['AccountID'],
            'AccountName'=>$user_info['AccountName']
        ];                
        
        $data['proxy'] = $this->getTotalData($uid);

        // $now_date = date('Y-m-d H:i:s');
        // //月统计
        // $data['proxy_m'] = $this->getIndexData($uid,date('Y-m').'-01 00:00:00',$now_date);
        // //周统计
        // $w = mktime(0,0,0,date('m'),date('d')-date('w')+1,date('y'));
        // $data['proxy_w'] = $this->getIndexData($uid,date('Y-m-d',$w).' 00:00:00',$now_date);
        // //本周

        // $start_date = date('Y-m-d  00:00:00',$w-7*86400);
        // $data['proxy_sw'] = $this->getIndexData($uid,$start_date,date('Y-m-d',$w).' 00:00:00');
        // //上个月
        // $start_date = date('Y-m-01 00:00:00',strtotime('-1 month'));
        // $data['proxy_sm'] = $this->getIndexData($uid,$start_date,date('Y-m').'-01 00:00:00');

        $this->retData(0,'success',$data);
    }

    public function changeTab(){
        $token  = $this->request->param('token');
        $uid    = $this->request->param('uid');
        if ($uid == '' || $token == '') {
            $this->retData(-1,'Parâmetro faltante');
        }

        $user_info = (new \app\model\AccountDB())->getTableObject('T_Accounts(nolock)')
                        ->where('AccountID',$uid)
                        ->find();
        if (empty($user_info)) {
            $this->retData(-1,'A conta não existea');
        }
        $ProxyInfo = (new \app\model\UserDB())->getTableObject('T_UserProxyInfo(nolock)')->where('RoleID',$uid)->find();
        if ($ProxyInfo['MobileBackgroundSwitch'] != 1) {
            $this->retData(-1,'No permission');
        }
        if (md5(md5($user_info['AccountName'].'-br123')) != $token) {
            $this->retData(-1,'Erro de sinal');
        }

        $tab = $this->request->param('tab') ?: 'total';
        switch ($tab) {
            case 'total':
                $data = $this->getTotalData($uid);
                break;
            case 'month':
                $now_date = date('Y-m-d H:i:s');
                $data = $this->getIndexData($uid,date('Y-m-d').' 00:00:00',$now_date);
                // $data = $this->getIndexData($uid,date('Y-m').'-01 00:00:00',$now_date);
                break;
            case 'lastmonth':
                $data = $this->getIndexData($uid,date('Y-m-d',strtotime('-1 days')).' 00:00:00',date('Y-m-d',strtotime('-1 days')).' 23:59:59');
                // $start_date = date('Y-m-01 00:00:00',strtotime('-1 month'));
                // $data = $this->getIndexData($uid,$start_date,date('Y-m').'-01 00:00:00');
                break;
            case 'week':
                $w = date('w');
                if ($w == 0) {
                    $w = 7;
                }
                $w = mktime(0,0,0,date('m'),date('d')-$w+1,date('y'));
                $now_date = date('Y-m-d H:i:s');
                $data = $this->getIndexData($uid,date('Y-m-d',$w).' 00:00:00',$now_date);
                break;
            case 'lastweek':
                $w = date('w');
                if ($w == 0) {
                    $w = 7;
                }
                $w = mktime(0,0,0,date('m'),date('d')-$w+1,date('y'));
                $start_date = date('Y-m-d  00:00:00',$w-7*86400);
                $data = $this->getIndexData($uid,$start_date,date('Y-m-d',$w).' 00:00:00');
                break;
            default:
                $data = $this->getTotalData($uid);
                break;
        }
        $this->retData(0,'success',$data);

    }

    private function getTotalData($uid){
        $field = 'ISNULL(ReceivedIncome,0) As ReceivedIncome,ISNULL(TotalDeposit,0) AS TotalDeposit,ISNULL(TotalTax,0) AS TotalTax,ISNULL(TotalRunning,0) AS TotalRunning,ISNULL(Lv1PersonCount,0) AS Lv1PersonCount,ISNULL(Lv1Deposit,0) AS Lv1Deposit,ISNULL(Lv1Tax,0) AS Lv1Tax,ISNULL(Lv1Running,0) AS Lv1Running,ISNULL(Lv2PersonCount,0) AS Lv2PersonCount,ISNULL(Lv2Deposit,0) AS Lv2Deposit,ISNULL(Lv2Tax,0) AS Lv2Tax,ISNULL(Lv2Running,0) AS Lv2Running,ISNULL(Lv3PersonCount,0) AS Lv3PersonCount,ISNULL(Lv3Deposit,0) AS Lv3Deposit,ISNULL(Lv3Tax,0) AS Lv3Tax,ISNULL(Lv3Running,0) AS Lv3Running,Lv1DepositPlayers as Lv1RechargeCount,Lv2DepositPlayers as Lv2RechargeCount,Lv3DepositPlayers as Lv3RechargeCount';

        if (config('is_show_under4') == 1) {
            $field .= ',ISNULL(UnderLv4PersonCount,0) AS UnderLv4PersonCount,ISNULL(UnderLv4Deposit,0) AS UnderLv4Deposit,ISNULL(UnderLv4Running,0) AS UnderLv4Running,UnderLv4DepositPlayers as Lv4RechargeCount';
        }
        $proxy = (new \app\model\UserDB())->getTableObject('T_ProxyCollectData(nolock)')->where('ProxyId',$uid)->field($field)->find();

        $proxy['TotalTax'] = FormatMoney($proxy['TotalTax']);
        // $proxy['TotalDeposit'] = FormatMoney($proxy['TotalDeposit'])/1;
        $proxy['TotalRunning'] = FormatMoney($proxy['TotalRunning'])/1;

        $proxy['Lv1Tax'] = FormatMoney($proxy['Lv1Tax']);
        $proxy['Lv1Running'] = FormatMoney($proxy['Lv1Running'])/1;


        $proxy['Lv2Tax'] = FormatMoney($proxy['Lv2Tax']);
        $proxy['Lv2Running'] = FormatMoney($proxy['Lv2Running'])/1;

        $proxy['Lv3Tax'] = FormatMoney($proxy['Lv3Tax']);
        $proxy['Lv3Running'] = FormatMoney($proxy['Lv3Running'])/1;

        if (config('is_show_under4') == 1) {
            $proxy['UnderLv4Running'] = FormatMoney($proxy['UnderLv4Running'])/1;
        }

        $Lv1Reward = bcmul($proxy['Lv1Running'], config('agent_running_parent_rate')[1], 4);
        $Lv2Reward = bcmul($proxy['Lv2Running'], config('agent_running_parent_rate')[2], 4);
        $Lv3Reward = bcmul($proxy['Lv3Running'], config('agent_running_parent_rate')[3], 4);
        $rewar_amount = bcadd($Lv1Reward , $Lv2Reward,4);
        $rewar_amount = bcadd($rewar_amount, $Lv3Reward,4);
        $proxy['ReceivedIncome'] =  round($rewar_amount,2)/1;
        $proxy['TotalDeposit'] =  round($proxy['TotalDeposit'],2)/1;
        $proxy['TotalRunning'] =  round($proxy['TotalRunning'],2)/1;
        return $proxy;
    }
    
    private function getIndexData($uid,$date,$end_date){
        $table = 'dbo.T_ProxyDailyCollectData';

        $where = "";
        $where .= " and ProxyId=".$uid;
        
        $begin = substr($date,0,10);
        $end   = substr($end_date,0,10);
        $field = 'ISNULL(sum(Lv1PersonCount),0) as Lv1PersonCount,ISNULL(sum(Lv1Deposit),0) as Lv1Deposit,ISNULL(sum(Lv1Running),0) as Lv1Running,ISNULL(sum(Lv1RechargeCount),0) as Lv1RechargeCount,ISNULL(sum(Lv2PersonCount),0) as Lv2PersonCount,ISNULL(sum(Lv2Deposit),0) as Lv2Deposit,ISNULL(sum(Lv2Running),0) as Lv2Running,ISNULL(sum(Lv2RechargeCount),0) as Lv2RechargeCount,ISNULL(sum(Lv3PersonCount),0) as Lv3PersonCount,ISNULL(sum(Lv3Deposit),0) as Lv3Deposit,ISNULL(sum(Lv3Running),0) as Lv3Running,ISNULL(sum(Lv3RechargeCount),0) as Lv3RechargeCount';
        if (config('is_show_under4') == 1) {
            $field .= ',ISNULL(sum(UnderLv4PersonCount),0) AS UnderLv4PersonCount,ISNULL(sum(UnderLv4Deposit),0) AS UnderLv4Deposit,ISNULL(sum(UnderLv4Running),0) AS UnderLv4Running,ISNULL(sum(Lv4RechargeCount),0) as Lv4RechargeCount';
        }

        $tablefield = 'sum(Lv1PersonCount) as Lv1PersonCount,sum(Lv1Deposit) as Lv1Deposit,sum(Lv1Running) as Lv1Running,sum(Lv1FirstDepositPlayers) as Lv1RechargeCount,sum(Lv2PersonCount) as Lv2PersonCount,sum(Lv2Deposit) as Lv2Deposit,sum(Lv2Running) as Lv2Running,sum(Lv2FirstDepositPlayers) as Lv2RechargeCount,sum(Lv3PersonCount) as Lv3PersonCount,sum(Lv3Deposit) as Lv3Deposit,sum(Lv3Running) as Lv3Running,sum(Lv3FirstDepositPlayers) as Lv3RechargeCount';
        if (config('is_show_under4') == 1) {
            $tablefield .= ',sum(UnderLv4PersonCount) as UnderLv4PersonCount,sum(UnderLv4Deposit) as UnderLv4Deposit,sum(UnderLv4Running) as UnderLv4Running,sum(UnderLv4DepositPlayers) as Lv4RechargeCount';
        }
        
        $sqlExec = "exec Proc_GetGameLogTotal '$table','$field','$tablefield','','$where','$begin','$end'";
        
        $res = (new \app\model\GameOCDB())->getTableQuery($sqlExec);

        $result = $res[0][0];

        $result['Lv1Running'] = FormatMoney($result['Lv1Running'] ?? 0)/1;

        $result['Lv2Running'] = FormatMoney($result['Lv2Running'] ?? 0)/1;

        $result['Lv3Running'] = FormatMoney($result['Lv3Running'] ?? 0)/1; 

        if (config('is_show_under4') == 1) {
            $result['UnderLv4Running'] = FormatMoney($result['UnderLv4Running'] ?? 0)/1; 
        }
        
        return $result;
    }
    
    private function getIndexData2($uid,$date,$end_date){
        $proxy_one = (new \app\model\UserDB())->getTableObject('T_UserProxyInfo(nolock)')->alias('a')
                    ->join('T_ProxyCollectData(nolock) b','a.RoleId=b.ProxyId')
                    ->join('[CD_Account].[dbo].[T_Accounts](nolock) c','c.AccountID=a.RoleId')
                    ->where('RIGHT(a.ParentIds,8)=\''.$uid.'\'')
                    ->whereTime('c.RegisterTime','>=',$date)
                    ->whereTime('c.RegisterTime','<',$end_date)
                    ->where('c.GmType','<>',0)
                    ->field('count(a.RoleId) as Lv1PersonCount,ISNULL(sum(case when b.TotalDeposit>0 then 1 else 0 end),0) as Lv1RechargeCount,ISNULL(sum(b.TotalDeposit),0) as Lv1Deposit,ISNULL(sum(b.TotalRunning),0) as Lv1Running')
                    ->find();

        $proxy_two = (new \app\model\UserDB())->getTableObject('T_UserProxyInfo(nolock)')->alias('a')
                    ->join('T_ProxyCollectData(nolock) b','a.RoleId=b.ProxyId')
                    ->join('[CD_Account].[dbo].[T_Accounts](nolock) c','c.AccountID=a.RoleId')
                    ->where('LEN(a.ParentIds)>=17 and LEFT(RIGHT(a.ParentIds,17),8)=\''.$uid.'\'')
                    ->whereTime('c.RegisterTime','>=',$date)
                    ->whereTime('c.RegisterTime','<',$end_date)
                    ->where('c.GmType','<>',0)
                    ->field('count(a.RoleId) as Lv2PersonCount,ISNULL(sum(case when b.TotalDeposit>0 then 1 else 0 end),0) as Lv2RechargeCount,ISNULL(sum(b.TotalDeposit),0) as Lv2Deposit,ISNULL(sum(b.TotalRunning),0) as Lv2Running')
                    ->find();
        $proxy_three = (new \app\model\UserDB())->getTableObject('T_UserProxyInfo(nolock)')->alias('a')
                    ->join('T_ProxyCollectData(nolock) b','a.RoleId=b.ProxyId')
                    ->join('[CD_Account].[dbo].[T_Accounts](nolock) c','c.AccountID=a.RoleId')
                    ->where('LEN(a.ParentIds)>=26 and LEFT(RIGHT(a.ParentIds,26),8)=\''.$uid.'\'')
                    ->whereTime('c.RegisterTime','>=',$date)
                    ->whereTime('c.RegisterTime','<',$end_date)
                    ->where('c.GmType','<>',0)
                    ->field('count(a.RoleId) as Lv3PersonCount,ISNULL(sum(case when b.TotalDeposit>0 then 1 else 0 end),0) as Lv3RechargeCount,ISNULL(sum(b.TotalDeposit),0) as Lv3Deposit,ISNULL(sum(b.TotalRunning),0) as Lv3Running')
                    ->find();

        $proxy_one['Lv1Running'] = FormatMoney($proxy_one['Lv1Running'])/1;

        $proxy_two['Lv2Running'] = FormatMoney($proxy_two['Lv2Running'])/1;

        $proxy_three['Lv3Running'] = FormatMoney($proxy_three['Lv3Running'])/1; 
        return array_merge($proxy_one,$proxy_two,$proxy_three);
    }


    public function comissao(){
        $token      = $this->request->param('token');
        $uid        = $this->request->param('uid');

        $button     = $this->request->param('button')?:0;
        $start_date     = $this->request->param('start_date');
        $end_date     = $this->request->param('end_date');
        if ($uid == '' || $token == '') {
            $this->retData(-1,'Parâmetro faltante');
        }
        $user_info = (new \app\model\AccountDB())->getTableObject('T_Accounts(nolock)')
                        ->where('AccountID',$uid)
                        ->find();
        if (empty($user_info)) {
            $this->retData(-1,'A conta não existea');
        }
        $ProxyInfo = (new \app\model\UserDB())->getTableObject('T_UserProxyInfo(nolock)')->where('RoleID',$uid)->find();
        if ($ProxyInfo['MobileBackgroundSwitch'] != 1) {
            $this->retData(-1,'No permission');
        }
        if (md5(md5($user_info['AccountName'].'-br123')) != $token) {
            $this->retData(-1,'Erro de sinal');
        }
        switch ($button) {
            case '0':
                $start_date = date('Y-m-d 00:00:00',strtotime($start_date));
                $end_date   = date('Y-m-d 23:59:59',strtotime($end_date));
                break;
            case '1':
                $start_date = '';
                $end_date   = '';
                break;
            case '2':
                $start_date = date('Y-m-d 00:00:00');
                $end_date   = date('Y-m-d 23:59:59');
                break;
            case '3':
                //本周开始时间戳
                $w = mktime(0,0,0,date('m'),date('d')-date('w')+1,date('y'));
                $start_date = date('Y-m-d',$w).' 00:00:00';
                $end_date   = date('Y-m-d').' 23:59:59';
                break;
            case '4':
                $w = mktime(0,0,0,date('m'),date('d')-date('w')+1,date('y'));
                $start_date = date('Y-m-d  00:00:00',$w-7*86400);
                $end_date   = date('Y-m-d  23:59:59',$w-1);
                break;
            case '5':
                $start_date = date('Y-m-01 00:00:00',strtotime('-1 month'));
                $end_date   = date('Y-m-d 23:59:59',strtotime(date('Y-m'))-1);
                break;
            default:
                $start_date = date('Y-m-d 00:00:00');
                $end_date   = date('Y-m-d 23:59:59');
                break;
        }
        
        $data = [];

        $field = 'sum([BonusAmount]) as BonusAmount,BonusType';
        $where = 'RoleId='.$uid;
        if ($start_date) {
            $where .= ' and AddTime>=\''.$start_date.'\'';
        }
        if ($end_date) {
            $where .= ' and AddTime<=\''.$end_date.'\'';
        }
        $comissao  = (new \app\model\DataChangelogsDB())->getTableObject('T_ProxyBonusLog(nolock)')
                ->where($where)
                ->group('BonusType')
                ->field($field)
                ->select();
        $data['bonus1'] = 0;
        $data['bonus2'] = 0;
        $data['bonus3'] = 0;
        $data['bonus4'] = 0;
        $data['bonus5'] = 0;
        foreach ($comissao as $key => &$val) {
            $data['bonus'.$val['BonusType']] = FormatMoney($val['BonusAmount']);
        }
        $data['total'] = round($data['bonus3']+$data['bonus4']+$data['bonus5'],3);

        if ($button == 1) {
            $field = 'ISNULL(Lv1PersonCount,0) AS Lv1PersonCount,ISNULL(Lv2PersonCount,0) AS Lv2PersonCount,ISNULL(Lv3PersonCount,0) AS Lv3PersonCount';
            $proxy = (new \app\model\UserDB())->getTableObject('T_ProxyCollectData(nolock)')->where('ProxyId',$uid)->field($field)->find();
            $data['total_num'] = $proxy['Lv1PersonCount'] + $proxy['Lv2PersonCount'] + $proxy['Lv3PersonCount'];
            $data['direct_num'] = $proxy['Lv1PersonCount'];
        } else {
            $table = 'dbo.T_ProxyDailyCollectData';
            $field = 'ISNULL(Sum(Lv1PersonCount),0) as Lv1PersonCount,ISNULL(Sum(Lv2PersonCount),0) as Lv2PersonCount,ISNULL(Sum(Lv3PersonCount),0) as Lv3PersonCount';
            $where = ' and ProxyId='.$uid;
            $begin = substr($start_date, 0,10);
            $end   = substr($end_date, 0,10);
            $sqlExec = "exec Proc_GetPageGROUP '$table','$field','$where','$begin','$end'";
            $proxy = (new \app\model\GameOCDB())->getTableQuery($sqlExec);
            if (isset($proxy[0])) {
                $list = $proxy[0][0];
                $data['total_num'] = $list['Lv1PersonCount'] + $list['Lv2PersonCount'] + $list['Lv3PersonCount'];
                $data['direct_num'] = $list['Lv1PersonCount'];
            } else {
                $data['total_num'] = 0;
                $data['direct_num'] = 0;
            }
        }
        
        $this->retData(0,'success',$data);
    }


    public function mermber(){
        $token  = $this->request->param('token');
        $uid    = $this->request->param('uid');
        $page   = $this->request->param('page')?:1;
        $limit   = $this->request->param('limit')?:20;
        $level  = $this->request->param('level')?:0;
        $roleid = $this->request->param('roleid')?:0;
        if ($uid == '' || $token == '') {
            $this->retData(-1,'Parâmetro faltante');
        }

        $user_info = (new \app\model\AccountDB())->getTableObject('T_Accounts(nolock)')
                        ->where('AccountID',$uid)
                        ->find();
        if (empty($user_info)) {
            $this->retData(-1,'A conta não existea');
        }
        $ProxyInfo = (new \app\model\UserDB())->getTableObject('T_UserProxyInfo(nolock)')->where('RoleID',$uid)->find();
        if ($ProxyInfo['MobileBackgroundSwitch'] != 1) {
            $this->retData(-1,'No permission');
        }
        if (md5(md5($user_info['AccountName'].'-br123')) != $token) {
            $this->retData(-1,'Erro de sinal');
        }
        $where = '1=1';
        if ($roleid != '') {
            $where .= ' and a.RoleID='.$roleid;
        }
        switch ($level) {
            case '0':
                //截取后26为存在uid '8,8,8'
                $where .= ' and RIGHT(a.ParentIds,26) like \'%'.$uid.'%\'';
                break;
            case '1':
                $where .= ' and RIGHT(a.ParentIds,8)=\''.$uid.'\'';
                break;
            case '2':
                //截取后17为存在uid '8,8'
                $where .= ' and LEN(a.ParentIds)>=17 and LEFT(RIGHT(a.ParentIds,17),8)=\''.$uid.'\'';
                break;
            case '3':
                //截取后26为存在uid '8,8,8'
                $where .= ' and LEN(a.ParentIds)>=26 and LEFT(RIGHT(a.ParentIds,26),8)=\''.$uid.'\'';
                break;
            default:
                $where .= ' and RIGHT(a.ParentIds,26) like %'.$uid.'%';
                break;
        }
        $field = 'a.RoleID,a.LoginName,a.DirectParentName,a.ParentIds,ISNULL(b.Lv1PersonCount,0) Lv1PersonCount,ISNULL(b.TotalRunning,0) as TotalRunning';
        $data = (new \app\model\UserDB())->getTableObject('T_UserProxyInfo(nolock)')->alias('a')
                    ->join('T_ProxyCollectData(nolock) b','a.RoleId=b.ProxyId','LEFT')
                    ->where($where)
                    ->field($field)
                    ->order('RoleID asc')
                    ->paginate($limit)
                    ->toArray();
        foreach ($data['data'] as $key => &$val) {
            $val['TotalRunning'] = FormatMoney($val['TotalRunning']);
            $val['level'] = 0;
            $val['comissao'] = 0;
            if(strlen($val['ParentIds'])>=26 && substr($val['ParentIds'],-26,8) == $uid){
                //三级
                $val['level'] = '3';
                $val['comissao'] = bcmul($val['TotalRunning'], config('agent_running_parent_rate')[3],2)/1;
            }
            else if(strlen($val['ParentIds'])>=17 && substr($val['ParentIds'],-17,8) == $uid){
                //二级
                $val['level'] = '2';
                $val['comissao'] = bcmul($val['TotalRunning'], config('agent_running_parent_rate')[2],2)/1;
            }
            else if(substr($val['ParentIds'],-8) == $uid){
                //一级
                $val['level'] = '1';
                $val['comissao'] = bcmul($val['TotalRunning'], config('agent_running_parent_rate')[1],2)/1;
            }
        }
        $this->retData(0,'success',$data['data']);
    }



    public function bonus(){
        $token = $this->request->param('token');
        $uid   = $this->request->param('uid');
        $type  = $this->request->param('type')?:0;
        $page  = $this->request->param('page')?:1;
        $limit = $this->request->param('limit')?:20;
        if ($uid == '' || $token == '') {
            $this->retData(-1,'Parâmetro faltante');
        }
        $user_info = (new \app\model\AccountDB())->getTableObject('T_Accounts(nolock)')
                        ->where('AccountID',$uid)
                        ->find();
        if (empty($user_info)) {
            $this->retData(-1,'A conta não existea');
        }
        $ProxyInfo = (new \app\model\UserDB())->getTableObject('T_UserProxyInfo(nolock)')->where('RoleID',$uid)->find();
        if ($ProxyInfo['MobileBackgroundSwitch'] != 1) {
            $this->retData(-1,'No permission');
        }
        if (md5(md5($user_info['AccountName'].'-br123')) != $token) {
            $this->retData(-1,'Erro de sinal');
        }
        $where = 'RoleId=\''.$uid.'\'';
        if ($type != '') {
            $where .= 'and BonusType=\''.$type.'\'';
        }
        $data  = (new \app\model\DataChangelogsDB())->getTableObject('T_ProxyBonusLog(nolock)')
                ->where($where)
                ->where('BonusType in(1,2,3,4,5)')
                ->order('AddTime desc')
                ->paginate($limit)
                ->toArray();
        foreach ($data['data'] as $key => &$val) {
           $val['BonusAmount'] = FormatMoney($val['BonusAmount'])/1;
           $val['AddTime'] = substr($val['AddTime'],0,19);
           switch ($val['BonusType']) {
                case '1':
                   $val['BonusType'] = 'Retirada da comissão';
                   break;
                case '2':
                   $val['BonusType'] = 'Transferência de comissão';
                   break;
                case '3':
                   $val['BonusType'] = 'Comissão  da apostas';
                   break;
                case '4':
                   $val['BonusType'] = 'Conquista de convite';
                   break;
                case '5':
                   $val['BonusType'] = 'Bônus de convite';
                   break;
                default:
                   # code...
                   break;
           }
        }
        $this->retData(0,'success',$data['data']);
    }


    public function getConfig(){
        $config = config('agent_running_parent_rate');
        $data['level1'] = $config[1] * 100;
        $data['level2'] = $config[2] * 100;
        $data['level3'] = $config[3] * 100;
        $this->retData(0,'success',$data);
    }
}
