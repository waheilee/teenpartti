<?php
namespace app\model;

class GameData extends BaseModel
{
    protected $table = '[OM_GameOC].[dbo].[Vw_DailyRoomTax]';
	
	public function __construct($connstr){
        Parent::__construct($connstr);
    }
	
	public function getGameData($strwhere, $OrderField, $PageIndex, $pagesize, $ordertype,$tblName = ''){	
		$total_in = 0;
		$total_win= 0;
		$total_tax= 0;
		$total_re = 0;		
		$percent = ",cast((cast((winscore+DailyEggTax) as  decimal(18,3))*100/(select sum(winscore+DailyEggTax) as totalwin  from [OM_GameOC].[dbo].[Vw_DailyRoomTax] where ".$strwhere.")) as decimal(18,2)) as [percent]";
		$strFields = "roomid,winscore as totalwin,addtime,dailyrunning as totalwater, DailyEggTax as blacktax,(winscore+DailyEggTax) as killpoint,kindid,roomname,kindname,winnum,totalnum".$percent;
		
		if ($tblName == '') {
			$tableName = $this->table;
		}else
			$tableName = $tblName;
		
		$list = $this->getBigPage($tableName,$strFields,$strwhere,$OrderField,$PageIndex,$pagesize,$ordertype);
		
		if (isset($list['list']) && $list['count'] > 0) {
			foreach ($list['list'] as &$v) {
				//盈利 sprintf("%.2f",$num);
                /*
                $v['totalwater'] = sprintf("%.2f", $v['totalwater'] / 1000);
                $v['totalwin'] = sprintf("%.2f", $v['totalwin'] / 1000);
                $v['blacktax'] = sprintf("%.2f", $v['blacktax'] / 1000);
                $v['killpoint'] = sprintf("%.2f", $v['killpoint'] / 1000);
                */
                $v['totalwater'] = $this->getConversion($v['totalwater']);
                $v['totalwin'] = $this->getConversion($v['totalwin']);
                $v['blacktax'] = $this->getConversion($v['blacktax']);
                $v['killpoint'] = $this->getConversion($v['killpoint']);
                $v['percent'] = $v['percent'];
                $v['addtime'] = substr($v['addtime'], 0, 10);

                //活跃度
                $v['winrate'] = 0;
                if ($v['totalwater'] != 0) {
                    $v['winrate'] = sprintf("%.2f", ( ($v['totalwater'] - $v['totalwin']) / $v['totalwater']) * 100);
                }
                if ($v['totalnum'] != 0) {
                    $v['winzhanbi'] = sprintf("%.2f", ($v['winnum'] / $v['totalnum']) * 100);
                }
                $total_in = $total_in + $v['totalwater'];
                $total_win = $total_win + $v['totalwin'];
                $total_tax = $total_tax + $v['blacktax'];
                $total_re = ($total_in-$total_win)/$total_in*100;
            }
            unset($v);
        }

        //顶部统计：总流水，游戏输赢，总明税，游戏回报率
        $list['other'] = array('total_in' => $total_in, 'total_win' => $total_win, 'total_tax' => $total_tax, 'total_re' => $total_re);
        return $list;
    }
}
