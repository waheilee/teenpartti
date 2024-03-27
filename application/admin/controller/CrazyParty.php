<?php

namespace app\admin\controller;

class CrazyParty extends Main
{

    public function config()
    {
        if (input('action') == 'list') {
            $limit          = request()->param('limit') ?: 15;
            $ActiStatus         = request()->param('ActiStatus');
            
            $orderby = input('orderby', 'ActiBeginTime');
            $orderType = input('ordertype', 'desc');

            $where = '1=1';
            if ($ActiStatus != '') {
                $where .= ' and ActiStatus=' . $ActiStatus;
            }

            $data = (new \app\model\MasterDB())->getTableObject('T_ActiTemplate')
                ->where($where)
                ->order("$orderby $orderType")
                ->paginate($limit)
                ->toArray();
            return $this->apiReturn(0, $data['data'], 'success', $data['total']);
        } else {
            $data = (new \app\model\MasterDB())->getTableObject('T_GameConfig')->where('CfgType','297')->value('CfgValue');
            $data = $data/bl;
            $this->assign('changenum',$data);
            return $this->fetch();
        }
    }

    public function setConfig(){
        $amount = request()->param('changenum');
        $res = (new \app\model\MasterDB())->getTableObject('T_GameConfig')->where('CfgType','297')->data(['CfgValue'=>$amount*bl])->update();
        if ($res) {
            return $this->apiReturn(0, '', '操作成功');
        } else {
            return $this->apiReturn(1, '', '操作失败');
        }
    }

    //更改状态
    public function changeStatus(){
        $id = request()->param('id');
        $ActiStatus    = request()->param('ActiStatus');

        $res = (new \app\model\MasterDB())->getTableObject('T_ActiTemplate')->where('ActiId',$id)->data(['ActiStatus'=>$ActiStatus])->update();
        if ($res) {
            return $this->apiReturn(0, '', '操作成功');
        } else {
            return $this->apiReturn(1, '', '操作失败');
        }
    }

    //删除
    public function del(){
        $id = request()->param('id');
        $res = (new \app\model\MasterDB())->getTableObject('T_ActiTemplate')->where('ActiId',$id)->delete();
        // $res = (new \app\model\MasterDB())->getTableObject('T_OnlineActiItem')->where('ActiTemplateId',$id)->delete();
        // $res = (new \app\model\DataChangelogsDB())->getTableObject('T_CrazyPartySignRecord')->where('ActiTemplateId',$id)->delete();
        if ($res) {
            return $this->apiReturn(0, '', '操作成功');
        } else {
            return $this->apiReturn(1, '', '操作失败');
        }
    }

    //编辑
    public function edit(){
        if ($this->request->method() == 'POST') {
            $ActiId     = request()->param('ActiId');
            $IconDownloadUrl   = request()->param('IconDownloadUrl');
            $SignUpFree  = request()->param('SignUpFree');
            $ActiStartThreShold = request()->param('ActiStartThreShold');
            $ActiStatus = request()->param('ActiStatus');
            $ActiTitle = request()->param('ActiTitle');
            $CommodityLink = request()->param('CommodityLink');

            $data = [
                    'SignUpFree'=>$SignUpFree,
                    'ActiStartThreShold'=>$ActiStartThreShold,
                    'ActiStatus'=>$ActiStatus,
                    'ActiTitle'=>$ActiTitle,
                    'IconDownloadUrl'=>$IconDownloadUrl,
            ];

            if (config('app_name') == 'FOGOSLOTS') {
                $data['CommodityLink'] = $CommodityLink;
            }
            if ($ActiId) {
                $res = (new \app\model\MasterDB())->getTableObject('T_ActiTemplate')->where('ActiId',$ActiId)->data($data)->update();
            } else {
                $data['ActiBeginTime'] = date('Y-m-d H:i:s');
                $res = (new \app\model\MasterDB())->getTableObject('T_ActiTemplate')->insert($data);
            }
            if ($res) {
                return $this->apiReturn(0, '', '操作成功');
            } else {
                return $this->apiReturn(1, '', '操作失败');
            }
        }
        $id = request()->param('id')?:0;
        if ($id != '') {
            $data = (new \app\model\MasterDB())->getTableObject('T_ActiTemplate')->where('ActiId',$id)->find();
        } else {
            $data = [];
            $data['ActiStatus'] = 0;
        }
        $this->assign('data',$data);
        return $this->fetch();
    }

    //活动列表
    public function aitem()
    {
        if (input('action') == 'list') {
            $limit       = request()->param('limit') ?: 15;
            $RoleId  = request()->param('RoleID');
            $ActivNo  = request()->param('ActivNo');
            $ActiStatus  = request()->param('ActiStatus');
            $start_date     = request()->param('start_date');
            $end_date       = request()->param('end_date');
            $start_date1     = request()->param('start_date1');
            $end_date1      = request()->param('end_date1');

            $orderby = input('orderby', 'ActivNo');
            $orderType = input('ordertype', 'desc');

            $where = '1=1';
            if ($RoleId != '') {
                $where .= ' and a.WinnerId=' . $RoleId;
            }
            if ($ActivNo != '') {
                $where .= ' and a.ActivNo=\'' . $ActivNo . '\'';
            }
            if ($ActiStatus != '') {
                $where .= ' and a.ActiStatus=' . $ActiStatus;
            }
            if ($start_date != '') {
                $where .= ' and a.ActiBeginTime>=\'' . $start_date . '\'';
            }
            if ($end_date != '') {
                $where .= ' and a.ActiBeginTime<\'' . $end_date . '\'';
            }

            if ($start_date1 != '') {
                $where .= ' and a.ActiEndTime>=\'' . $start_date1 . '\'';
            }
            if ($end_date1 != '') {
                $where .= ' and a.ActiEndTime<\'' . $end_date1 . '\'';
            }

            $data = (new \app\model\MasterDB())->getTableObject('T_OnlineActiItem')->alias('a')
                ->where($where)
                ->order("$orderby $orderType")
                ->paginate($limit)
                ->toArray();
            return $this->apiReturn(0, $data['data'], 'success', $data['total']);
        } else {
            return $this->fetch();
        }
    }

    public function delaitem(){
        $ActivNo  = request()->param('ActiNo');
        $res = (new \app\model\MasterDB())->getTableObject('T_OnlineActiItem')->where('ActivNo',$ActivNo)->where('ActiStatus',0)->data(['ActiStatus'=>2])->update();
        // $res = (new \app\model\MasterDB())->getTableObject('T_OnlineActiItem')->where('ActiTemplateId',$id)->delete();
        // $res = (new \app\model\DataChangelogsDB())->getTableObject('T_CrazyPartySignRecord')->where('ActiTemplateId',$id)->delete();
        if ($res) {
            return $this->apiReturn(0, '', '操作成功');
        } else {
            return $this->apiReturn(1, '', '操作失败');
        }
    }

    //全部记录
    public function record(){
        if (input('action') == 'list') {
            $limit       = request()->param('limit') ?: 15;
            $RoleId  = request()->param('RoleID');
            $ActiNo  = request()->param('ActiNo');
            $PrizeLabel  = request()->param('PrizeLabel');
            $ActiBonusCode  = request()->param('ActiBonusCode');
            $start_date     = request()->param('start_date');
            $end_date       = request()->param('end_date');

            $orderby = input('orderby', 'SignUpTime');
            $orderType = input('ordertype', 'desc');

            $where = '1=1';
            if ($RoleId != '') {
                $where .= ' and a.RoleId=' . $RoleId;
            }
            if ($ActiNo != '') {
                $where .= ' and a.ActiNo=\'' . $ActiNo . '\'';
            }
            if ($PrizeLabel != '') {
                $where .= ' and a.PrizeLabel=' . $PrizeLabel;
            }
            if ($ActiBonusCode != '') {
                $where .= ' and a.ActiBonusCode=\'' . $ActiBonusCode . '\'';
            }
            if ($start_date != '') {
                $where .= ' and a.SignUpTime>=\'' . $start_date . '\'';
            }
            if ($end_date != '') {
                $where .= ' and a.SignUpTime<\'' . $end_date . '\'';
            }
            $data = (new \app\model\DataChangelogsDB())->getTableObject('T_CrazyPartySignRecord')->alias('a')
                ->join('[CD_UserDB].[dbo].[T_RoleCrazyParty] b','b.RoleId=a.RoleId')
                ->where($where)
                ->field('a.*,b.PartyDiamond PartyDiamond')
                ->order("$orderby $orderType")
                ->paginate($limit)
                ->toArray();

            $data['other']['SignUpFree'] = (new \app\model\DataChangelogsDB())->getTableObject('T_CrazyPartySignRecord')->alias('a')
                ->join('[CD_UserDB].[dbo].[T_RoleCrazyParty] b','b.RoleId=a.RoleId')
                ->where($where)
                ->sum('SignUpFree');
            $sql = 'select count(*) count from (select DISTINCT RoleId from T_CrazyPartySignRecord as a where '.$where.') as b';
            $total_num = (new \app\model\DataChangelogsDB())->getTableQuery($sql);
            $data['other']['total_num'] = $total_num[0]['count'];
            foreach ($data['data'] as $key => &$val) {
                // $val['PartyDiamond'] /= bl;
            }
            return $this->apiReturn(0, $data['data'], 'success', $data['total'],$data['other']);
        } else {
            return $this->fetch();
        }
    }

    public function getRecord(){
        if (input('action') == 'list') {
            $limit       = request()->param('limit') ?: 15;
            $RoleId  = request()->param('RoleID');
            $ActiNo  = request()->param('ActiNo');

            $orderby = input('orderby', 'PrizeLabel');
            $orderType = input('ordertype', 'desc');

            $where = "ActiNo='$ActiNo'";
            if ($RoleId != '') {
                $where .= ' and RoleId=' . $RoleId;
            }

            $data = (new \app\model\DataChangelogsDB())->getTableObject('T_CrazyPartySignRecord')
                ->where($where)
                ->order("$orderby $orderType")
                ->paginate($limit)
                ->toArray();
            $ActiStatus = (new \app\model\MasterDB())->getTableObject('T_OnlineActiItem')->where('ActivNo',$ActiNo)->value('ActiStatus');
            foreach ($data['data'] as $key => &$val) {
                $val['ActiStatus'] = $ActiStatus;
            }
            return $this->apiReturn(0, $data['data'], 'success', $data['total']);
        } else {
            $ActiNo  = request()->param('ActiNo');
            $this->assign('ActiNo',$ActiNo);
            return $this->fetch();
        }
    }

    public function set(){
        $RoleID  = request()->param('RoleID');
        $ActiNo  = request()->param('ActiNo');
        $ActiBonusCode  = request()->param('ActiBonusCode');
        
        
        $info = (new \app\model\MasterDB())->getTableObject('T_OnlineActiItem')->where('ActivNo',$ActiNo)->find();
        if ($info['ActiStatus'] == 1) {
            return $this->apiReturn(1, '', '活动已开奖，不可设定');
        }
        $res1 = (new \app\model\MasterDB())->getTableObject('T_OnlineActiItem')->where('ActivNo',$ActiNo)->where('ActiStatus',0)->data([
            'WinnerId'=>$RoleID,
            'ActiBonusCode'=>$ActiBonusCode,
            'BonusAddress'=>'',
            'Phone'=>'',
            'RealName'=>'',
            'CEP'=>''
        ])->update();

        $res2 = (new \app\model\DataChangelogsDB())->getTableObject('T_CrazyPartySignRecord')->where('ActiNo',$ActiNo)->where('PrizeLabel',1)->data(['PrizeLabel'=>0])->update();
        $res3 = (new \app\model\DataChangelogsDB())->getTableObject('T_CrazyPartySignRecord')->where('ActiNo',$ActiNo)->where('ActiBonusCode',$ActiBonusCode)->data(['PrizeLabel'=>1])->update();

        return $this->apiReturn(0, '', '操作成功');
    }

    public function cancel(){
        $ActiNo  = request()->param('ActiNo');
        $ActiBonusCode  = request()->param('ActiBonusCode');

        $res1 = (new \app\model\MasterDB())->getTableObject('T_OnlineActiItem')->where('ActivNo',$ActiNo)->where('ActiStatus',0)->data([
            'WinnerId'=>0,
            'ActiBonusCode'=>'',
            'BonusAddress'=>'',
            'Phone'=>'',
            'RealName'=>'',
            'CEP'=>''
        ])->update();
        $res2 = (new \app\model\DataChangelogsDB())->getTableObject('T_CrazyPartySignRecord')->where('ActiNo',$ActiNo)->where('PrizeLabel',1)->data(['PrizeLabel'=>0])->update();

        if ($res1 && $res2) {
            return $this->apiReturn(0, '', '操作成功');
        } else {
            return $this->apiReturn(1, '', '操作失败');
        }
    }

    //中奖记录
    public function lottery(){
        if (input('action') == 'list') {
            $limit       = request()->param('limit') ?: 15;
            $ActiTitle  = request()->param('ActiTitle');
            $roleid = request()->param('RoleID');

            $orderby = input('orderby', 'ActivNo');
            $orderType = input('ordertype', 'desc');

            $where = 'ActiStatus=1 and WinnerId>0';

            if ($ActiTitle != '') {
                $where .= ' and ActiTitle=' . $ActiTitle;
            }

            if($roleid!=''){
                $where .= ' and WinnerId=' . $roleid;
            }

            $data = (new \app\model\MasterDB())->getTableObject('T_OnlineActiItem')
                ->where($where)
                ->order("$orderby $orderType")
                ->paginate($limit)
                ->toArray();
            return $this->apiReturn(0, $data['data'], 'success', $data['total']);
        } else {
            return $this->fetch();
        }
    }

    //审核
    public function check(){
        $ActivNo = input('ActivNo');
        $WinnerStatus = input('WinnerStatus');

        $res = (new \app\model\MasterDB())->getTableObject('T_OnlineActiItem')->where('ActivNo',$ActivNo)->data([
            'WinnerStatus'=>$WinnerStatus,
            'CheckUser'=>session('username'),
            'CheckTime'=>date('Y-m-d H:i:s')

        ])->update();

        if ($res) {
            return $this->apiReturn(0, '', '操作成功');
        } else {
            return $this->apiReturn(1, '', '操作失败');
        }
    }

    //活动监控
    public function monitor(){
        if (input('action') == 'list') {
            $limit       = request()->param('limit') ?: 15;
            $RoleId  = request()->param('RoleID');

            $orderby = input('orderby', 'PartyDiamond');
            $orderType = input('ordertype', 'desc');

            $where = 'len(a.AccountID)>7';

            if ($RoleId != '') {
                $where .= ' and a.AccountID=' . $RoleId;
            }

            $data = (new \app\model\AccountDB())->getTableObject('T_Accounts')->alias('a')
                ->join('[CD_UserDB].[dbo].[T_RoleCrazyParty] c','c.RoleId=a.AccountID','left')
                ->join('[CD_UserDB].[dbo].[T_UserCrazyPartyStatistic] b','b.RoleId=a.AccountID','left')
                ->where($where)
                ->field('a.AccountID RoleId,isnull(c.PartyDiamond,0) PartyDiamond,isnull(b.AllSignUpFree,0) AllSignUpFree,isnull(b.JoinInActi,0) JoinInActi,isnull(b.BonusActi,0) BonusActi')
                ->order("$orderby $orderType")
                ->paginate($limit)
                ->toArray();
            $data['other']['AllSignUpFree'] = (new \app\model\AccountDB())->getTableObject('T_Accounts')->alias('a')
                ->join('[CD_UserDB].[dbo].[T_RoleCrazyParty] c','c.RoleId=a.AccountID','left')
                ->join('[CD_UserDB].[dbo].[T_UserCrazyPartyStatistic] b','b.RoleId=a.AccountID','left')
                ->where($where)
                ->sum('AllSignUpFree')?:0;
            $data['other']['PartyDiamond'] = (new \app\model\AccountDB())->getTableObject('T_Accounts')->alias('a')
                ->join('[CD_UserDB].[dbo].[T_RoleCrazyParty] c','c.RoleId=a.AccountID','left')
                ->join('[CD_UserDB].[dbo].[T_UserCrazyPartyStatistic] b','b.RoleId=a.AccountID','left')
                ->where($where)
                ->sum('PartyDiamond')?:0;   
            return $this->apiReturn(0, $data['data'], 'success', $data['total'],$data['other']);
        } else {
            return $this->fetch();
        }
    }

    public function opt(){
        $RoleId  = request()->param('RoleId');
        $amount  = request()->param('amount');
        $type  = request()->param('type');

        $data = $this->sendGameMessage('CMD_MD_SET_ACTI_DIAMOND', [$type,$RoleId,$amount], "DC", 'returnComm');
        if ($data['iResult'] == 0) {
            return $this->apiReturn(0, '', '操作成功');
        } else {
            return $this->apiReturn(1, '', '操作失败');
        }
    }
}