<?php
namespace app\model;

use think\db;
class SalesStatistics extends BaseModel
{
    protected $table = '[om_masterdb].[dbo].[commoditycfg]';
	
	public function __construct($connstr){
        Parent::__construct($connstr);
    }
	
	public function getSalesStatistics($strwhere, $OrderField, $PageIndex, $pagesize, $ordertype,$tblName = ''){
		$goods = config('goodstype');
		$strFields = "id,commodityname,cdytype,realmoney";		
		if ($tblName == '') {
			$tableName = $this->table;
		}else
			$tableName = $tblName;
		//取所有商品
		$commodity = $this->getQueryAll($tableName,$strFields,' 1=1');
		$commodity = $commodity['list'];
		//取会员消费记录
		$strFields = "transactionno,commodityname,accountid,realmoney,virtualgold,addtime,platformtype,cdytype,id";
		$tableName = '[cd_userdb].[dbo].[t_usertransaction]';
		$user = $this->getQueryAll($tableName,$strFields,$strwhere);
		$user = $user['list'];
				
		$strFields = " distinct accountid,commodityname ";
		$tableName = '[cd_userdb].[dbo].[t_usertransaction]';
		$buyer = $this->getQueryAll($tableName,$strFields,$strwhere,'','group by accountid,commodityname'); //去重复，取购买人数
		$buyer = $buyer['count']; //总购买人数		
		$total_cishu = 0;  	//购买次数占比
		$total_renshu = 0; 	//购买人数占比
		$total_pinci = 0; 	//购买人数占比
		if (!empty($user)){
			$buytimes = count($user); //总购买总次数
			//循环比对商品与会员消费记录，统计数据
			for($i = 0;$i<count($commodity);$i++) {
				//初始化统计
				$commodity[$i]['buytimes'] = 0; //购买次数
				$commodity[$i]['buyer'] = 0;  	//购买人数
				$commodity[$i]['cishu'] = 0;  	//购买次数占比
				$commodity[$i]['renshu'] = 0; 	//购买人数占比
				$commodity[$i]['pinci'] = 0; 	//购买人数占比
				$out = array();
				//开始统计
				for ($j = 0;$j<count($user);$j++) {
					if ($commodity[$i]['commodityname'] == $user[$j]['commodityname']) {
						$commodity[$i]['buytimes'] = $commodity[$i]['buytimes']+1; //重复出现购买商品则计算做购买次数+1
											
						if (!in_array($user[$j]['accountid'], $out)) //购买的用户ID是否重复出现，不重复则写入数组
						{
							$out[$j] = $user[$j]['accountid'];
						}	
					}
				}	
				$commodity[$i]['commodityname']		= "[".$goods[$commodity[$i]['cdytype']]."]".$commodity[$i]['commodityname'];
				$commodity[$i]['buyer'] 	= count($out); //数组内的人数为购买人数
				$commodity[$i]['cishu'] 	= round($commodity[$i]['buytimes']/$buytimes*100,2)."%";
				$commodity[$i]['renshu'] 	= round($commodity[$i]['buyer']/$buyer*100,2)."%";
				if ($commodity[$i]['buyer'] != 0) 
					$commodity[$i]['pinci'] = number_format($commodity[$i]['buytimes']/$commodity[$i]['buyer'],1);
				else
					$commodity[$i]['pinci'] = 0;
				
			}
			//汇总数据
			$commodity[$i]['commodityname'] = "合计";
			$commodity[$i]['buytimes'] = $buytimes; //购买次数
			$commodity[$i]['buyer'] = $buyer;  	//购买人数
			$commodity[$i]['cishu'] = "100%";  	//购买次数占比
			$commodity[$i]['renshu'] = "100%"; 	//购买人数占比
			$commodity[$i]['pinci'] = number_format($buytimes/$buyer,1); 	//购买人数占比
		}else{
			for($i = 0;$i<count($commodity);$i++) {
				//初始化统计
				$commodity[$i]['buytimes'] = 0; //购买次数
				$commodity[$i]['buyer'] = 0;  	//购买人数
				$commodity[$i]['cishu'] = 0;  	//购买次数占比
				$commodity[$i]['renshu'] = 0; 	//购买人数占比
				$commodity[$i]['pinci'] = 0; 	//购买人数占比
			}
		}
		//print_r($commodity);
		//exit();
		$list['list']  = $commodity;
		$list['count'] = 0;
		$list['totalsum'] = $buyer;
		return $list;
	}
}
