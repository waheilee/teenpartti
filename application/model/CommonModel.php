<?php
/** 公共模型类
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/11
 * Time: 10:23
 */
namespace app\model;

use think\Db;
use think\Model;

class CommonModel extends Model
{
    protected $tablePrefix = '';
    
    /**
     *  获取数据表名（包含表前缀）
     * @return type
     */
    public function getTableName() {
        $sys_database_config = config('database');
        $this->tablePrefix = $sys_database_config['prefix'];
        return $this->tablePrefix . $this->table;
    }
    
    /**
     * 执行查询 返回数据集
     * @access public
     * @param string      $sql    sql指令
     * @param array       $bind   参数绑定
     * @param boolean     $master 是否在主服务器读操作     
     * @param boolean     $sys_database 是否在平台系统数据服务器读操作     
     * @return mixed
     * @throws BindParamException
     * @throws PDOException
     */
    public function query($sql, $bind = [], $master = false, $sys_database = false) {
        return $this->db($sys_database)->query($sql, $bind, $master);
    }
    
    /**
     * 根据SQL获取列表数据
     * @param type $sql
     * @param type $pageIndex 当前页
     * @param type $pageSize 每页数量
     * @param type $order 排序
     * todo    提取到公共baseModel
     */
    public function getDataListBySQL($sql, $pageIndex, $pageSize, $order = '') {
        if (!empty($order))
            $sql .= ' order by ' . $order;
        if ($pageIndex >= 0 && $pageSize > 0) {
            $offset = 0;
            if ($pageIndex > 0)
                $offset = (intval($pageIndex) - 1) * intval($pageSize);
            $sql .= ' limit ' . $offset . ',' . $pageSize;
        }
        return $this->query($sql);
    }
    
    /*
     * 获取统计列表
     */
    public function getDataList($pageIndex = -1, $pageSize = -1, $filter = '', $fields = '', $order = 'id desc')
    {
        $sql = 'SELECT ';
        if (!empty($fields)) {
            $sql .= $fields;
        } else {
            $sql .= ' * ';
        }
        $sql .= ' from ' . $this->getTableName();
        if (!empty($filter)) {
            $sql .= $filter;
        }
        return $this->getDataListBySQL($sql, $pageIndex, $pageSize, $order);
    }
    
    /**
     * 获取记录总数
     * @param type $filter
     * @return type
     */
    public function getTotal($filter = '')
    {
        $sql = " select count(id) c from " . $this->getTableName();
        if ($filter != ''){
            $sql .= $filter;
        }
        $result = $this->query($sql);
        return $result[0]['c'];
    }
    
    public function AddData($data) {
        $res = Db::name($this->table)->insert($data);
        return $res;
    }
    
    public function UpdateData($data, $where) {
        return Db::name($this->table)->where($where)->data($data)->update();
    }
    
    
    /*
	*取sql数据并分页，参数均为str
	*	$tablename 	表名strfields 取字段 where 查询条件
	* 	分页传入数组，page和limit，使用循环剔除不在页面区间内的数组，后返回
	*	第1页，记录0-14 = i = page -1 =0 * $limit< 数组 < $limit*$page = 15
	*	第2页，记录15-29  i = page -1 =1 * $limit< 数组 < $limit*$page = 30
	*	第3页，记录30-44  i = page -1 =2 * $limit< 数组 < $limit*$page = 45
	*/
	public function getPageList($tablename,$strfields='*',$where='',$limits='',$page=1, $limit=10,$orderBy = '', $groupBy = ''){
		$db = Db::connect( config('gamedata.OM_MasterDB'));//T_ScoreChangeLogs
		$sqlstr1 = "select ".$limits.' '.$strfields." from ".$tablename.$where.$orderBy;
		$result = $db->query($sqlstr1);		
		$sqlcount = "select count(*) as count from ".$tablename.$where;
		$count = $db->query($sqlcount);
		$i = 0;
        $j = 0;
//		$limits = $page * $limit;
        $list = array();
        while ($i < $count[0]['count']) {
            if (($i >= ($page - 1) * $limit) && ($i < $limit * $page)) {
                $list[$j] = $result[$i];
                $j++;
            }
            $i++;
        }
        $re = array("count" => $count[0]['count'], "list" => $list, "sql" => $sqlstr1);
        return $re;
    }
	//获取一行数据
    public function getsql($tablename,$field='*',$where = '',$orderBy = '')
    {
        $db = Db::connect('gamedata.CD_Account');//T_ScoreChangeLogs
		$sqlstr1 = "select ".$field.' from '.$tablename." ".$where." ".$orderBy;
		$result = $db->query($sqlstr1);
		$re = array("count" => count($result),"list" => $result);
		return $re;
    }
	
	//获取UNION数据
    public function getunion($sql,$page=1, $limit=10)
    {
        $db = Db::connect('gamedata.CD_Account');//T_ScoreChangeLogs
		//$sqlstr1 = "select ".$field.' from '.$tablename." ".$where." ".$orderBy;
		$result = $db->query($sql);
		$count = sizeof($result);
		$i = 0;
		$j = 0;
		$list = array();
		while($i < $count) {
			if (($i >= ($page-1)*$limit) && ($i < $limit*$page)) {
				$list[$j] = $result[$i];
				$j++;
			}
			$i++;
		}
		$re = array("count" => $count,"list" => $list);
		return $re;
    }
	/*
	*	传入表名，字段，值，完成插入数据操作，参数均为str
	*/
	public function addsql($tablename,$strfields,$value){
		$db = Db::connect('gamedata.CD_Account');//T_ScoreChangeLogs
		$sqlstr1 = "insert into".$tablename." (".$strfields.") values (".$value.")";
		$result = $db->execute($sqlstr1);
		
		return $result;
	}
	/*
	*	传入表名，条件，完成删除数据操作，参数均为str
	*/
	public function delsql($tablename,$where){
		$db = Db::connect('gamedata.CD_Account');//delete from testss.dbo.test1 where id='12';
		$sqlstr1 = "delete from ".$tablename.$where;
		$result = $db->execute($sqlstr1);
		
		return $result;
	}
	
	public function getprocedure($tablename,$strfields='*',$query='',$limits='',$page=1, $limit=10)
    {
        $db = Db::connect('gamedata.CD_Account');//T_ScoreChangeLogs [OM_GameOC].[dbo].[Proc_BigPage] 
        try{
            $TotalCount = 0;
            /*
			$res = $db->query('exec [OM_GameOC].[dbo].[Proc_RoomOnlineData_Select] :startdate,:enddate,:page,:pagesize,:TotalCount', [
                'startdate' => '2020-12-01',
                'enddate' => '2021-01-06',
                'page' => 1,
                'pagesize' => 15,
                'TotalCount' => $TotalCount
            ]);
			*/
			$res = $db->query('exec'.$tablename.$strfields,$query,false,false);
        }catch (PDOException $e)
        {
            return false;
        }
        return $res;
    }
	
	
	//获取列表(分页)
    public function getList($where = [], $page=1, $limit=10, $field='*', $orderBy = [], $groupBy = '')
    {
        $info = Db::name($this->table)
            ->where($where)
            ->field($field)
            ->page($page, $limit)
            ->order($orderBy)
            ->group($groupBy)
            ->select();
        return $info;
    }

    //获取列表所有
    public function getListAll($where = [], $field='*', $orderBy = [], $groupBy = '')
    {
        $info = Db::name($this->table)
            ->where($where)
            ->field($field)
            ->order($orderBy)
            ->group($groupBy)
            ->select();
        return $info;
    }

    //获取一行数据
    public function getRow($where, $field='*', $orderBy = [])
    {
        $info = Db::name($this->table)
            ->where($where)
            ->field($field)
            ->order($orderBy)
            ->find();
        return $info;
    }

    //获取某个字段的数据
    public function getValue($where, $field)
    {
        $info = Db::name($this->table)->where($where)->value($field);
        return $info;
    }

    //根据id获取记录
    public function getRowById($id, $field='*')
    {
        $info = Db::name($this->table)
            ->where('id',$id)
            ->field($field)
            ->find();
        return $info;
    }

    //获取总数
    public function getCount($where = [])
    {
        $info = Db::name($this->table)->where($where)->count();
        return $info;
    }

    //新增数据
    public function add($data)
    {
        $info = Db::name($this->table)->insertGetId($data);
        return $info;
    }

    //新增多条数据
    public function addAll($data)
    {
        $info = Db::name($this->table)->insertAll($data);
        return $info;
    }

    //更新数据
    public function updateByWhere($where, $data)
    {
        $info = Db::name($this->table)->where($where)->data($data)->update();
        return $info;
    }

    //根据id更新数据
    public function updateById($id, $data)
    {
        $res = Db::name($this->table)->where('id', $id)->data($data)->update();
        return $res;
    }
    //汇总数据
    public function getSum($where, $field)
    {
        $res = Db::name($this->table)->where($where)->sum($field);
        return $res;
    }

    //获取上次执行的sql语句
    public function getLastSql()
    {
        $res = Db::name($this->table)->getLastSql();
        return $res;
    }

    //获取最大值
    public function getMax($where, $field)
    {
        $res = Db::name($this->table)->where($where)->max($field);
        return $res;
    }

    //获取最小值
    public function getMmin($where, $field)
    {
        $res = Db::name($this->table)->where($where)->min($field);
        return $res;
    }
}