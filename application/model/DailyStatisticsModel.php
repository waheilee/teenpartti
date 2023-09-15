<?php

namespace app\model;

use think\Db;

class DailyStatisticsModel extends CommonModel
{

    protected $table = 'daily_statistics';
    protected $sqlsrv_table = 'T_DailyStatistics';

    //银行财富变化类型，！这个会写数据库，只能末端追加不能改
    const SBWCT_GET_MONEY = 1;          //取款-
    const SBWCT_GIVE_MONEY = 2;          //赠送-
    const SBWCT_LOCK = 3;              //冻结-//三方游戏转出金币前冻结 钱转移到BANK
    const SBWCT_SAVING_MONEY = 4;          //存款+
    const SBWCT_TAKE_MONEY = 5;          //收款+
    const SBWCT_UNLOCK = 6;            //解锁+ 
    const SBWCT_CHARGE = 7;            //充值+
    const SBWCT_WAGE = 8;              //工资+
    const SBWCT_TRANS_BACK = 9;          //转账返还+
    const SBWCT_SYS_SAVING_MONEY = 10;      //系统存款+
    const SBWCT_SYS_REGISTER_GIVE = 11;      //系统注册赠送
    const SBWCT_SYS_TAX = 12;            //转账扣税-
    const SBWCT_TASK_AWARD = 13;          //任务奖励+
    const SBWCT_BIND_WECHAT = 14;          //绑定微信+
    const SBWCT_DAILY_SIGN = 15;          //每日签到+
    const SBWCT_LUCKY_EGG = 16;          //房间彩蛋+
    const SBWCT_CARD_CHARGE = 17;          //实物卡充值+
    const SBWCT_WECHAT_SHARE = 18;        //微信分享+
    const SBWCT_GAME_TASK_AWARD = 19;        //游戏活动任务奖励
    const SBWCT_SYS_UPACCOUNT_GIVE = 20;      //升级账号赠送
    const SBWCT_SYS_REDUCE_BANK_MONEY = 21;    //系统扣银行分
    const SBWCT_SYS_REDUCE_GAME_MONEY = 22;    //系统扣身上分
    const SBWCT_WITHDRAW_REJECT_BACK = 23;    //提现拒绝后返还  
    const SBWCT_PROXY_AWARD_MONEY = 24;      //代理领取奖励
    const SBWCT_USER_DAY_RUNNING_RANK_AWARD = 25;  //玩家日流水排行奖励
    const SBWCT_USER_RUNNING_RETURN = 26;      //玩家流水返利奖励
    const SBWCT_USER_MIN_JACKPOT_AWARD = 27;    //奖池中奖奖励
    const SBWCT_USER_SUPER_JACKPOT_AWARD = 28;
    const SBWCT_PROXY_WHITDRAW = 29;  //代理上下分
    const SBWCT_LOTTERY_DIAMOND = 30;    //积分抽奖
    const SBWCT_TASK_COMPLETE_AWARD = 31;      //任务完成奖励
    const SBWCT_DRAW_ONLINE_AWARD = 32;
    const SBWCT_MAIL_EXTRA_GIFT_MONEY = 33;
    const SBWCT_CHAC_RANK_UP_AWARD = 34;      //升级奖励
    const SBWCT_PIGGY_BANK_CHARGE = 35;            //储蓄罐充值+
    const SBWCT_SUPER_DEAL_CHARGE = 36;            //特惠充值+
    const SBWCT_SHOP_CHARGE = 37;                //商城充值+
    const SBWCT_ACTIVITY_AWARD = 38;          //运营活动奖励
    const SBWCT_USER_LEVEL_GIFT_AWARD = 39;      //玩家等级礼包
    const SBWCT_USER_SUPRISE_GIFT_AWARD = 40;      //玩家惊喜礼包
    const SBWCT_USER_NEWER_COLLECT_AWARD = 41;    //新手领取奖励
    const SBWCT_COUPON_EXCHANGE = 42;          //优惠券兑换金币
    const SBWCT_COUPON_EXCHANGE_GOODS = 43;      //优惠券兑换物品
    const SBWCT_USER_RANK_AWARD = 44;          //日、周排行榜奖励
    const SBWCT_USER_DRAWBACK_MONEY = 45;        //金币退款
    const SBWCT_USER_DRAWBACK_MONEY_FAILED = 46;    //金币退款失败返还
    const SBWCT_USER_CHANNEL_RECHARGE = 47;      //渠道充值
    const SBWCT_USER_WATCH_AD_AWARD = 48;        //观看广告奖励
    const SBWCT_BUY_FREE_PROP = 49;          //购买免费游戏次数
    const SBWCT_USER_DIABOX_AWARD = 50;          //道具 宝箱 使用奖励
    const SBWCT_THIRD_PARTY_TRANSFER = 51;      //进入三方游戏转出金币
    const SBWCT_THIRD_PARTY_WRITE_GAME_MONEY = 52;  //退出三方游戏转回金币
    const SBWCT_CHARGE_GIFT = 53;            //首充奖励
    const SBWCT_BIND_PHONE_AWARD = 54;          //绑定手机奖励
    const SBWCT_GAME_AWARD_MONEY = 55;          //游戏金币
    const SBWCT_WEEK_SIGN = 59;          //每周签到+
    const SBWCT_MONTH_SIGN = 60;          //每月签到+
    const SBWCT_VIP_UP_AWARD = 61;      //VIP升级奖励
    const SBWCT_PROXY_VALID_INVITE_AWARD = 62;  //代理有效邀请奖励

    const SBWCT_USER_RECHARGE_GIFT = 70;  //代理有效邀请奖励
    const SBWCT_USER_SHOP_GIFT = 71;  //代理有效邀请奖励

    const SBWCT_USER_DAY_RECHARGE_BONUS =74;
    const ACTT_FIRST_DEPOSIT_BONUS =82;
    const ACTT_DAY_DEPOSIT_BONUS  =  84;

    const ACTT_DAY_LOTTERY_BONUS = 30;


    /**
     * 数据库连接配置
     * @var type
     */
    protected $database_config = '';
    private $GameOCDB;


    public function __construct($connstr = '')
    {
        Parent::__construct($connstr);
        // sql server 数据库
        $this->GameOCDB = new \app\model\GameOCDB();
    }

    /*
     * 从SqlSrv获取日况统计列表
     */
    public function getDataListFromSqlSrv($pageIndex = -1, $pageSize = -1, $filter = '', $fields = '', $order = 'id desc')
    {
        if (!empty($order))
            $filter .= ' order by ' . $order;
        if ($pageIndex >= 0 && $pageSize > 0) {
            $offset = 0;
            if ($pageIndex > 0)
                $offset = (intval($pageIndex) - 1) * intval($pageSize);
            $filter .= ' limit ' . $offset . ',' . $pageSize;
        }
        return $this->GameOCDB->DBQuery($this->sqlsrv_table, $fields, $filter);
    }

    /**
     * 从SqlSrv获取记录总数
     * @param type $filter
     * @return type
     */
    public function getTotalFromSqlSrv($filter = '')
    {
        $fields = " count(id) as count ";
        $data = $this->GameOCDB->DBQuery($this->sqlsrv_table, $fields, $filter);
        if (!empty($data) && isset($data[0]['count'])) {
            return $data[0]['count'];
        } else {
            return 0;
        }
    }

    /**
     * 获取参数day之前最近一条日况统计概要记录
     * @param type $day
     * @return type
     */
    public function getLastDailyStatisticsByDay($day, $fields = '')
    {
        // 获取当前日期之前最近一条日况统计概要记录
        if (!empty($fields)) {
            $filter = "SELECT " . $fields;
        } else {
            $filter = "SELECT * ";
        }
        $filter .= " FROM " . $this->getTableName() . " WHERE day < '" . $day . "' ORDER BY day desc LIMIT 1;";
        $data = $this->query($filter);
        if (!empty($data)) {
            return $data[0];
        } else {
            return [];
        }
    }

    /**
     * 获取日期为day的日况统计概要记录
     * @param type $day
     * @return type
     */
    public function getDailyStatisticsByDay($day, $fields = '')
    {
        if (!empty($fields)) {
            $filter = "SELECT " . $fields;
        } else {
            $filter = "SELECT * ";
        }
        $filter .= " FROM " . $this->getTableName() . " WHERE day = '" . $day . "'";
        $data = $this->query($filter);
        if (!empty($data)) {
            return $data[0];
        } else {
            return [];
        }
    }
}

