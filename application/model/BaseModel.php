<?php
/** 公共模型类
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/11
 * Time: 10:23
 */

namespace app\model;

use PDO;
use think\Db;
use think\Model;

class BaseModel extends Model
{
    protected $pageSize = null;
    protected $page = null;
    public $AccountDB = "CD_Account";
    public $DataChangelogsDB = "CD_DataChangelogsDB";
    public $UserDB = "CD_UserDB";
    public $BankChangelogsDB = "NNT_BankChangelogsDB";
    public $SetOperatelogsDB = "NNT_SetOperatelogsDB";
    public $BankDB = "OM_BankDB";
    public $GameOC = "OM_GameOC";
    public $MasterDB = "OM_MasterDB";
    public $OperationLogsDB = "OM_OperationLogsDB";

    /*** 分页参数***/


    public function __construct($connstr,$read_from_master= false)
    {
        Parent::__construct();
        if (isset(Config('gamedata')[$connstr])) {
            $config = Config('gamedata')[$connstr];
            if ($read_from_master) {
                $hostname = $config['hostname'];
                $config['hostname'] = explode(',', $hostname)[0];
                $config['deploy'] = 0;
                $config['rw_separate'] = false; 
                $this->connection = Db::connect($config, true);
            } else {
                $this->connection = Db::connect($config, true);
            }
            
        }
    }

    //新增数据
    public function Tabladd($data)
    {
        $info = $this->connection->table($this->table)->insertGetId($data);
        return $info;
    }

    /**
     * 查询DB数据库中某张表数据
     * @param type $table
     * @param type $fields
     * @param type $filter
     * @return type
     */
    public function DBQuery($table, $fields = "", $filter = "")
    {
        if (!empty($fields)) {
            $sqlQuery = "SELECT " . $fields;
        } else {
            $sqlQuery = "SELECT * ";
        }
        $sqlQuery .= " FROM " . $table;
        if (!empty($filter)) {
            $sqlQuery .= $filter;
        }
        return $this->connection->query($sqlQuery);
    }

    /**
     * 查询DB数据库中某张表数据
     */
    public function DBOriginQuery($sql)
    {
        return $this->connection->query($sql);
    }

    //获取指定表的一行数据

    /**
     *
     * @param $table
     * @param $where
     * @param string $field
     * @param array $orderBy
     * @return array|bool|\PDOStatement|string|Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getTableRow($table, $where, $field = '*', $orderBy = [])
    {
        if (IsNullOrEmpty($table)) $table = $this->table;
        $info = $this->connection->table($table)
            ->where($where)
            ->field($field)
            ->order($orderBy)
            ->find();
        return $info;
    }


    /**
     * SQL Server 原生语句查询
     * @param $strQuery
     * @return array|false|mixed|\PDOStatement
     * @throws \think\exception\PDOException
     */
    public function getTableQuery($strQuery)
    {
        $this->connection->table = $this->table;
        return $this->connection->query($strQuery);
    }

    public function getTableEXEC($strQuery, $bind)
    {
        $this->connection->table = $this->table;
        return $this->connection->query($strQuery, $bind);
    }

    /**
     * 返回一个 thinkphp table 对象
     * @param $tblName
     * @return \think\db\Query
     */
    public function getTableObject($tblName)
    {
        return $this->connection->table($tblName);
    }

    /**
     * @param $tablename
     * @param array $where
     * @param int $page
     * @param int $limit
     * @param string $field
     * @param array $orderBy
     * @param string $groupBy
     * @return array
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getTableList($tablename, $where = [], $page = 1, $limit = 10, $field = '*', $orderBy = [], $groupBy = '')
    {
        $info = $this->getTableObject($tablename)
            ->where($where)
            ->field($field)
            ->page($page, $limit)
            ->order($orderBy)
            ->group($groupBy)
            ->select();
        $data = ['list' => [], 'count' => $this->connection->table($tablename)->where($where)->count()];
        if (isset($info)) $data['list'] = $info;
        return $data;
    }


    /**
     * 反回分页数据 只支持全表名 Model
     * @param string $where
     * @param string $order
     * @param string $field
     * @param false $debug
     * @return array
     * @throws \think\exception\PDOException
     */
    public function GetPage($where = '', $order = '', $field = '*', $debug = false): array
    {
        if ($this->pageSize == null)
            $pageSize = (int)input('limit', 20);
        else                         $pageSize = $this->pageSize;
        $page = ((int)input('page', 1) - 1) * $pageSize;


        $table = $this->table;
        if (IsNullOrEmpty($field)) $field = '*';
        if (!IsNullOrEmpty($where)) $where = $where;
        if (IsNullOrEmpty($order)) $sqlQuery = "SELECT $field,0 AS _ORDER_ FROM $table (NOLOCK) WHERE 1=1 $where  ORDER BY _ORDER_ OFFSET  $page ROWS FETCH NEXT $pageSize ROWS ONLY";
        else   $sqlQuery = "SELECT $field FROM $table (NOLOCK) WHERE 1=1 $where  ORDER BY  $order  OFFSET $page ROWS FETCH NEXT $pageSize ROWS ONLY";
        $data['msg'] = '';
        if ($debug) $data = ["sql" => $sqlQuery, "debug" => $debug, "msg" => ""];
        $data['list'] = $this->connection->query($sqlQuery);
        if (isset($data['list']) && !empty($data['list'])) $data['msg'] = "数据查询成功";
        $countQuery = "select count(*)as ct from $table where  1=1  $where";
        $ct = $this->connection->query($countQuery);
        $data['count'] = intval($ct[0]['ct']);
        if ($data['count'] > 0) $data['code'] = 0;
        else $data['code'] = 0;
        return $data;
    }


    ///group by 获取数据
    public function getPageListByGroup($tblName, $field, $join,$strwhere, $OrderField,$group,$stardate,$endate,$PageIndex, $pagesize)
    {
        try {
            $TotalCount = 0;
            $strsql = "exec OM_GameOC.dbo.Proc_GameDailyLog :TName,:Field,:Wheres,:Orders,:IJoin,:Group,:startDate,:endDate,:Page,:PageSize";
            $res = $this->connection->query($strsql,
                [
                    'TName' => $tblName,
                    'Field' => $field,
                    'Wheres' => $strwhere,
                    'Orders' => $OrderField,
                    'IJoin' => $join,
                    'Group' => $group,
                    'startDate'=>$stardate,
                    'endDate' => $endate,
                    'Page' => $PageIndex,
                    'PageSize' => $pagesize
                ]);
        } catch (PDOException $e) {
            save_log('error', $e->getMessage());
            return false;
        }
        $data = ['list' => [], 'count' => 0];
        if (isset($res[1])) {
            if(isset($res[0][0])){
                $TotalCount =$res[0][0]['count'];
            }
            $data = ['list' => $res[1], 'count' => $TotalCount, 'sqlQuery' => $strsql];
        } else
            $data = ['list' => '', 'count' => $TotalCount];
        return $data;
    }


    /**
     * 反回分页数据 只支持全表名 Model 迁移后删除该方法
     * 分页查询
     * @param $table 表名 default Model->table
     * @param int $page 分页页码
     * @param int $pageSize 分页大小
     * @param string $where 查询条件 'ID=1' default=''
     * @param string $order 排序字段 default=''
     * @param string $ordertype 排序方式 DESC OR ASC default=''
     * @param string $field 查询字段 '*' default=
     * @param bool $debug true 输出sql语句
     * @return array
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function getTablePage($table, $page = 1, $pageSize = 20, $where = '', $order = '', $ordertype = '', $field = '*', $debug = false): array
    {
        if (empty($page)) $page = 1;
        if (empty($pageSize)) $pageSize = 20;
        $page = (intval($page) - 1) * $pageSize;
        if (IsNullOrEmpty($table)) $table = $this->table;
        if (IsNullOrEmpty($field)) $field = '*';
        if (!IsNullOrEmpty($where)) $where = $where;
        if (IsNullOrEmpty($order)) $sqlQuery = "SELECT $field,0 AS _ORDER_ FROM $table (NOLOCK) WHERE 1=1 $where  ORDER BY _ORDER_ OFFSET  $page ROWS FETCH NEXT $pageSize ROWS ONLY";
        else   $sqlQuery = "SELECT $field FROM $table (NOLOCK) WHERE 1=1 $where  ORDER BY  $order  $ordertype OFFSET $page ROWS FETCH NEXT $pageSize ROWS ONLY";
        $data['msg'] = '';
        if ($debug) $data = ["sql" => $sqlQuery, "debug" => $debug, "msg" => ""];
        $data['list'] = $this->connection->query($sqlQuery);
        if (isset($data['list']) && !empty($data['list'])) $data['msg'] = lang("数据查询成功");
        if (!empty($ordertype)) $data['ordertype'] = $ordertype;
        $countQuery = "select count(*)as ct from $table where  1=1  $where";
        $ct = $this->connection->query($countQuery);
        $data['count'] = intval($ct[0]['ct']);
        if ($data['count'] > 0) $data['code'] = 0;
        else $data['code'] = 0;
        return $data;
    }


    public function getProcPageList($tblName, $field, $strwhere, $OrderField, $PageIndex, $pagesize)
    {
        try {
            $TotalCount = 0;
            $strsql = "exec CD_UserDB.dbo.Pagination :TableName,:ReFieldsStr,:OrderString,:WhereString,:pageSize,:pageIndex,:TotalRecord";
//            $strsql = "exec CD_DataChangelogsDB.dbo.Proc_BigDataPager $tblName,$field,$strwhere,$OrderField,$pagesize,:$PageIndex,$ordertype, [&$TotalCount, PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 1000]";
//            $res=$this->connection->query($strsql);

            $res = $this->connection->query($strsql,
                [
                    'TableName' => $tblName,
                    'ReFieldsStr' => $field,//[$field, PDO::PARAM_STR],
                    'OrderString' => $OrderField,//[$OrderField, PDO::PARAM_STR],
                    'WhereString' => $strwhere,//[$strwhere, PDO::PARAM_STR],
                    'pageSize' => $pagesize,//[$pagesize, PDO::PARAM_INT],
                    'pageIndex' => $PageIndex,//[$PageIndex, PDO::PARAM_INT],
                    'TotalRecord' => [&$TotalCount, PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 1000]
                ]);
        } catch (PDOException $e) {
            save_log('error', $e->getMessage());
            return false;
        }
        $data = ['list' => [], 'count' => 0];
        if (isset($res[0])) {
            $data = ['list' => $res[0], 'count' => $TotalCount, 'sqlQuery' => $strsql];
        } else
            $data = ['list' => '', 'count' => $TotalCount];
        return $data;
    }

    public function GetRow($where, $field = '*', $orderBy = [])
    {
        $info = $this->getTableObject($this->table)
            ->where($where)
            ->field($field)
            ->order($orderBy)
            ->find();
        return $info;
    }

    public function UPData($data, $where)
    {
        $result = $this->getTableObject($this->table)
            ->where($where)
            ->data($data)
            ->update();
        return $result;
    }

    /**
     * @param $table
     * @param array $data 字段区分大小写
     * @param $where
     * @return int   受影响行数
     */
    public function updateTable($table, $data, $where)
    {
        if (IsNullOrEmpty($table)) $table = $this->table;
        $result = $this->getTableObject($table)
            ->where($where)
            ->data($data)
            ->update();
        return $result;
    }

    /*
     *  大数据分页
     *  $tblName  表名
     *  $field    查询字段
     *  $strwhere  查询条件  不带where
     *  $OrderField 排序字段
     *  $PageIndex   当前页
     *  $pagesize    每页条数
     *  $ordertype   0位升序 1位降序
     */
    public function getBigPage($tblName, $field, $strwhere, $OrderField, $PageIndex, $pagesize, $ordertype)
    {
        try {
            $TotalCount = 0;
            $strsql = "exec CD_DataChangelogsDB.dbo.Proc_BigDataPager :tblname,:fieldname,:strwhere,:orderfield,:pagesize,:pageindex,:ordertype,:rowcount";
//            $strsql = "exec CD_DataChangelogsDB.dbo.Proc_BigDataPager $tblName,$field,$strwhere,$OrderField,$pagesize,:$PageIndex,$ordertype, [&$TotalCount, PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 1000]";
//            $res=$this->connection->query($strsql);

            $res = $this->connection->query($strsql,
                [
                    'tblname' => $tblName,
                    'fieldname' => $field,//[$field, PDO::PARAM_STR],
                    'strwhere' => $strwhere,//[$strwhere, PDO::PARAM_STR],
                    'orderfield' => $OrderField,//[$OrderField, PDO::PARAM_STR],
                    'pagesize' => $pagesize,//[$pagesize, PDO::PARAM_INT],
                    'pageindex' => $PageIndex,//[$PageIndex, PDO::PARAM_INT],
                    'ordertype' => $ordertype,//[$ordertype, PDO::PARAM_INT],
                    'rowcount' => [&$TotalCount, PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 1000]
                ]);
        } catch (PDOException $e) {
            save_log('error', $e->getMessage());
            return false;
        }
        $data = ['list' => [], 'count' => 0];
        if (isset($res[0])) {
            $data = ['list' => $res[0], 'count' => $TotalCount, 'sqlQuery' => $strsql];
        } else
            $data = ['list' => '', 'count' => $TotalCount];
        return $data;
    }

    /*
     *  调用存储过程
     *  $tblName  表名
     *  $field    查询字段
     *  $query   执行参数
     *  $hasreturn   是否有返回值 1为有返回值，0没有 ,默认有
     */
    public function getProcedure($tablename, $field = '*', $query = '', $hasreturn = 1)
    {
        $TotalCount = 0;
        if ($hasreturn == 1) {
            $query['TotalCount'] = [&$TotalCount, PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 1000];
        }
        $strsql = "exec " . $tablename . $field;
        $res = $this->connection->query($strsql, $query);
        $data = ['list' => [], 'count' => 0];
        if (isset($res[0])) {
            $data = ['list' => $res[0], 'count' => $TotalCount];
        } else
            $data = ['list' => '', 'count' => $TotalCount];
        return $data;
    }

    /*
     *  取跨表或多表关联数据（全部）
     */
    public function getQueryAll($tablename, $field = '*', $where = '', $orderBy = [], $groupBy = '')
    {
        $TotalCount = 0;
        $strsql = "Select $field from  $tablename ";
        if (!IsNullOrEmpty($where)) $strsql .= " where $where";
        $res = $this->connection->query($strsql);
        $data = ['list' => [], 'count' => 0];
        if (isset($res[0])) {
            $data = ['list' => $res, 'count' => count($res)];
        } else
            $data = ['list' => '', 'count' => $TotalCount];
        return $data;
    }

//获取列表(分页)
    public function getList($where = [], $page = 1, $limit = 10, $field = '*', $orderBy = [], $groupBy = '')
    {
        $info = $this->connection->name($this->table)
            ->where($where)
            ->field($field)
            ->page($page, $limit)
            ->order($orderBy)
            ->group($groupBy)
            ->select();
        return $info;
    }

    //获取列表所有
    public function getListAll($where = [], $field = '*', $orderBy = [], $groupBy = '')
    {
        $info = $this->connection->name($this->table)
            ->where($where)
            ->field($field)
            ->order($orderBy)
            ->group($groupBy)
            ->select();
        return $info;
    }
    public function getListTableAll($where = [], $field = '*', $orderBy = [], $groupBy = '')
    {
        $info = $this->connection->table($this->table)
            ->where($where)
            ->field($field)
            ->order($orderBy)
            ->group($groupBy)
            ->select();
        return $info;
    }

    public function delRow($where)
    {
        return $this->connection->name($this->table)->where($where)->delete();
    }

    public function DeleteRow($where)
    {
        return $this->getTableObject($this->table)->where($where)->delete();
    }

    public function delTableRow($table, $where)
    {
        return $this->connection->table($table)->where($where)->delete();
    }


    //获取某个字段的数据
    public function getValue($where, $field)
    {
        $info = $this->connection->name($this->table)->where($where)->value($field);
        return $info;
    }

    //获取某个字段的数据
    public function getValueByTable($where, $field)
    {
        $info = $this->connection->table($this->table)->where($where)->value($field);
        return $info;
    }


    //获取一行数据
    public function getDataRow($where, $field='*', $orderBy = [])
    {
        $info = $this->connection->name($this->table)
            ->where($where)
            ->field($field)
            ->order($orderBy)
            ->find();
        return $info;
    }


    //根据id获取记录
    public function getRowById($id, $field = '*')
    {
        $info = $this->connection->name($this->table)
            ->where('id', $id)
            ->field($field)
            ->find();
        return $info;
    }

    //获取总数
    public function getCount($where = [])
    {
        $info = $this->connection->name($this->table)->where($where)->count();
        return $info;
    }

    public function Insert(array $data)
    {
        return $this->getTableObject($this->table)->insert($data);
//        return $this->connection->table($this->table)->insert($data);
    }

    //Table 新增行记录
    public function addrow(string $table, array $data)
    {
        if (IsNullOrEmpty($table)) $table = $this->table;
        return $this->connection->table($table)->insert($data);
    }

    //新增数据
    public function add($data)
    {
        $info = $this->connection->name($this->table)->insertGetId($data);
        return $info;
    }

    //新增多条数据
    public function addAll($data)
    {
        $info = $this->connection->name($this->table)->insertAll($data);
        return $info;
    }

    //更新数据
    public function updateByWhere($where, $data)
    {
        $info = $this->connection->name($this->table)->where($where)->data($data)->update();
        return $info;
    }

    //根据id更新数据
    public function updateById($id, $data)
    {
        $res = $this->connection->name($this->table)->where('id', $id)->data($data)->update();
        return $res;
    }

    //汇总数据
    public function getSum($where, $field)
    {
        $res = $this->connection->name($this->table)->where($where)->sum($field);
        return $res;
    }

    public function getTableSum($where, $field)
    {
        $res = $this->connection->table($this->table)->where($where)->sum($field);
        return $res;
    }
    //获取上次执行的sql语句
    public function getLastSql()
    {
        $res = $this->connection->name($this->table)->getLastSql();
        return $res;
    }

    //获取最大值
    public function getMax($where, $field)
    {
        $res = $this->connection->name($this->table)->where($where)->max($field);
        return $res;
    }

    //获取最小值
    public function getMmin($where, $field)
    {
        $res = $this->connection->name($this->table)->where($where)->min($field);
        return $res;
    }

    //金额换算 目前暂时不除以1000，以1:1显示
    public function getConversion($money)
    {
        return $money;
    }
    
    /*
     * 从SqlSrv获取列表
     */
    public function getDataListFromSqlSrv($table, $pageIndex = -1, $pageSize = -1, $filter = '', $fields = '', $order = '') {
        if (!empty($order)) {
            $filter .= ' order by ' . $order;
        }
        if ($pageIndex >= 0 && $pageSize > 0) {
            $offset = 0;
            if ($pageIndex > 0)
                $offset = (intval($pageIndex) - 1) * intval($pageSize);
            $filter .= ' OFFSET '.$offset.'  ROWS FETCH NEXT '.$pageSize.' ROWS ONLY ';
        }
        
        return $this->DBQuery($table, $fields, $filter);
    }
    
    /**
     * 从SqlSrv获取记录总数
     * @param type $filter
     * @return type
     */
    public function getTotalFromSqlSrv($table, $filter = '') {
        $fields = " count(*) as count ";
        $data = $this->DBQuery($table, $fields, $filter);
        if (!empty($data) && isset($data[0]['count'])) {
            return $data[0]['count'];
        } else {
            return 0;
        }
    }

    /**
     * 获取执行事务的db连接实例
     * @return type
     */
    public function getTransDBConn() {
//        $db = Config('gamedata')[$this->UserDB];
        return $this->connection;
    }
    
}