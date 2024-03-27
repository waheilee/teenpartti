<?php

namespace app\model;

use think\exception\PDOException;

/**
 * Class MasterDB
 * @package app\model
 */
class MasterDB extends BaseModel
{
    /**
     * @var string 商品表
     */
    private $ShopItem = 'T_ShopItem';
    /**
     * @var string 客服表
     */
    private $CustomServiceCfg = 'T_CustomService_Cfg';
    /**
     * @var string 盘控表
     */
    private $SystemCtrlConfig = 'T_SystemCtrlConfig';
    private $GamePayChannel = 'T_GamePayChannel';
    private $BankCode = 'T_BankCode';

    const TABLE_GAME_PAY_CHANNEL = 'T_GamePayChannel';
    const OPERATING_ACTIVITY_INFO = 'T_OperatingActivityInfo';
    const CHARGE_GIFT_CFG ='T_ChargeGiftCfg';

    /**
     * UserDB.
     * @param string $tableName 连接的数据表
     */
    public function __construct($tableName = '')
    {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->MasterDB);
    }

    public function GameType()
    {
        $this->table='T_GameType';
        return $this;
    }

    /**
     * 商品表
     */
    public function TShopItem()
    {
        $this->table = $this->ShopItem;
        return $this;
    }

    /**
     * 客服配置表
     */
    public function TCustomServiceCfg()
    {
        $this->table = $this->CustomServiceCfg;
        return $this;
    }

    /**
     * 系统邮件表
     */

    public function TSystemMail()
    {
        $this->table = 'T_SystemMail';
        return $this;
    }


    /**
     * 盘控配置表
     */
    public function TSystemCtrlConfig()
    {
        $this->table = $this->SystemCtrlConfig;
        return $this;
    }

    /**
     * 银行配置
     * @return $this
     */
    public function TBankCode()
    {
        $this->table = $this->BankCode;
        return $this;
    }

    /**
     * 支付通道表
     */
    public function TGamePayChannel()
    {
        $this->table = $this->GamePayChannel;
        return $this;
    }


    public function TPackageGameConfig(){
        $this->table='T_PackageGameConfig';
        return $this;
    }

    /**
     * 系统公告
     */
    public function TSysMsgList()
    {
        $this->table = 'T_SysMsgList';
        return $this;
    }

    /**
     * 游戏防刷防爆,购买免费游戏开关
     * @return $this
     */
    public function TGameSingleBetCfg()
    {
        $this->table = 'T_GameSingleBetCfg';
        return $this;
    }

    /**
     * PK赛房间配置
     * @return $this
     */
    public function TPKMatchRoomCfg()
    {
        $this->table = 'T_PKMatchRoomCfg';
        return $this;
    }

    /**
     * PK赛房间配置 列表
     * @return array
     * @throws PDOException
     */
    public function PKmatchConfg()
    {
        return $this->TPKMatchRoomCfg()->GetPage();
    }

    /**
     * Pk赛连胜奖励配置表
     * @return $this
     */
    public function TPKContinueWinConfig(){
        $this->table='T_PKContinueWinConfig';
        return $this;
    }

    /**
     * 连胜奖励防刷
     * @return $this
     */
    public function PKWinRobotConfig()
    {
        $this->table='T_ContinusWinRobotCfg';
        return $this;
    }

    /**
     * 代理推荐奖励配置
     * @return $this
     */
    public function AgentInviteAward()
    {
        $this->table='T_ProxyInviteAwardCfg';
        return $this;
    }

    /**
     * 代理流水业绩返利配置
     * @return $this
     */
    public function AgentWaterReturn()
    {
        $this->table='T_ProxyWaterReturnCfg';
        return $this;
    }

    /**
     * 有效推荐奖励
     * @return $this
     */
    public function AgentValidInviteAward(){
        $this->table='T_ProxyValidInviteAwardCfg';
        return $this;
    }


    /**
     * 有效推荐奖励
     * @return $this
     */
    public function GameConfig(){
        $this->table='T_GameConfig';
        return $this;
    }

    public function taxFreeUser(){
        $limit          = request()->param('limit') ?: 15;
        $OperatorId     = request()->param('OperatorId');
        $RoleId     = request()->param('RoleId');
        $OpenChildTaxFree    = request()->param('OpenChildTaxFree');
        $where = '1=1';
        if (session('merchant_OperatorId') && request()->module() == 'merchant') {
            $where .= ' and b.OperatorId='.session('merchant_OperatorId');  
        }
        if ($RoleId != '') {
            $where .= ' and a.RoleId='.$RoleId;
        }
        if ($OpenChildTaxFree != '') {
            $where .= ' and a.OpenChildTaxFree='.$OpenChildTaxFree;
        }
        if ($OperatorId != '') {
            $where .= ' and b.OperatorId='.$OperatorId;
        }
        $data = $this->getTableObject('T_TaxFreeUserList(NOLOCK)')->alias('a')
            ->join('[CD_Account].[dbo].[T_Accounts](NOLOCK) b', 'b.AccountID=a.RoleId', 'left')
            ->where($where)
            ->field('a.*')
            ->paginate($limit)
            ->toArray();
        // foreach ($data['data'] as $key => &$val) {
            
        // }
        return $data;
    }

    public function disableBindWhiteList(){
        $limit          = request()->param('limit') ?: 15;
        $OperatorId     = request()->param('OperatorId');
        $RoleId     = request()->param('RoleId');
        $where = '1=1';
        if (session('merchant_OperatorId') && request()->module() == 'merchant') {
            $where .= ' and b.OperatorId='.session('merchant_OperatorId');  
        }
        if ($RoleId != '') {
            $where .= ' and a.RoleId='.$RoleId;
        }
        if ($OperatorId != '') {
            $where .= ' and b.OperatorId='.$OperatorId;
        }
        $data = $this->getTableObject('T_DisableBindWhiteList(NOLOCK)')->alias('a')
            ->join('[CD_Account].[dbo].[T_Accounts](NOLOCK) b', 'b.AccountID=a.RoleId', 'left')
            ->where($where)
            ->field('a.*')
            ->paginate($limit)
            ->toArray();
        // foreach ($data['data'] as $key => &$val) {
            
        // }
        return $data;
    }

    public function editDisableBindWhiteList(){
        $RoleID      = input('RoleID', 0);
        $ProxySwitch = input('type', 0);

        if (session('merchant_OperatorId') && request()->module() == 'merchant') {
            $RoleInfo    = (new \app\model\AccountDB())->getTableObject('T_Accounts')->where(['AccountID'=>$RoleID,'OperatorId'=>session('merchant_OperatorId')])->find();
            if(empty($RoleInfo)){
                return ['code' => 1,'msg'=>'权限不足'];
            }
        }

        $has = (new \app\model\MasterDB())->getTableObject('T_DisableBindWhiteList')->where('RoleId',$RoleID)->find();
        if ($ProxySwitch == 1) {
            if ($has) {
                return ['code' => 1,'msg'=>'已添加'];
            } else {
                $res = (new \app\model\MasterDB())->getTableObject('T_DisableBindWhiteList')->insert(['RoleId'=>$RoleID,'AddTime'=>date('Y-m-d H:i:s')]);
            }
        } 
        if ($ProxySwitch == 2) {
            if ($has) {
                $res = (new \app\model\MasterDB())->getTableObject('T_DisableBindWhiteList')->where('RoleId',$RoleID)->delete();
            } else {
                return ['code' => 1,'msg'=>'已移除'];
            }
        }
        if ($res) {
            return ['code' => 0,'msg'=>'操作成功'];
        } else {
            return ['code' => 1,'msg'=>'操作失败'];
        }
    }


    public function wageFreeWhiteList(){
        $limit          = request()->param('limit') ?: 15;
        $OperatorId     = request()->param('OperatorId');
        $RoleId     = request()->param('RoleId');
        $where = '1=1';
        if (session('merchant_OperatorId') && request()->module() == 'merchant') {
            $where .= ' and b.OperatorId='.session('merchant_OperatorId');  
        }
        if ($RoleId != '') {
            $where .= ' and a.RoleId='.$RoleId;
        }
        if ($OperatorId != '') {
            $where .= ' and b.OperatorId='.$OperatorId;
        }
        $data = $this->getTableObject('T_WageFreeWhiteList(NOLOCK)')->alias('a')
            ->join('[CD_Account].[dbo].[T_Accounts](NOLOCK) b', 'b.AccountID=a.RoleId', 'left')
            ->where($where)
            ->field('a.*')
            ->paginate($limit)
            ->toArray();
        // foreach ($data['data'] as $key => &$val) {
            
        // }
        return $data;
    }


    public function editWageFreeWhiteList(){
        $RoleID      = input('RoleID', 0);
        $ProxySwitch = input('type', 0);

        if (session('merchant_OperatorId') && request()->module() == 'merchant') {
            $RoleInfo    = (new \app\model\AccountDB())->getTableObject('T_Accounts')->where(['AccountID'=>$RoleID,'OperatorId'=>session('merchant_OperatorId')])->find();
            if(empty($RoleInfo)){
                return ['code' => 1,'msg'=>'权限不足'];
            }
        }

        $has = (new \app\model\MasterDB())->getTableObject('T_WageFreeWhiteList')->where('RoleId',$RoleID)->find();
        if ($ProxySwitch == 1) {
            if ($has) {
                return ['code' => 1,'msg'=>'已添加'];
            } else {
                $res = (new \app\model\MasterDB())->getTableObject('T_WageFreeWhiteList')->insert(['RoleId'=>$RoleID]);
            }
        } 
        if ($ProxySwitch == 2) {
            if ($has) {
                $res = (new \app\model\MasterDB())->getTableObject('T_WageFreeWhiteList')->where('RoleId',$RoleID)->delete();
            } else {
                return ['code' => 1,'msg'=>'已移除'];
            }
        }
        if ($res) {
            return ['code' => 0,'msg'=>'操作成功'];
        } else {
            return ['code' => 1,'msg'=>'操作失败'];
        }
    }
}