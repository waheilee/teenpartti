<?php


namespace app\model;


class OperationLogsDB extends BaseModel
{
    private $TRoomActionStatistic = 'T_RoomActionStatistic';
    private $SystemCtrlWaterLog = 'SystemCtrlWaterLog';

    /**
     * UserDB.
     * @param string $connstr 连接的数据库
     * @param string $tableName 连接的数据表
     */
    public function __construct($connstr = '', $tableName = '')
    {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        if (IsNullOrEmpty($connstr)) {
            $connstr = $this->OperationLogsDB;
        }
        Parent::__construct($connstr);
    }

    public function TableRoomActionStatistic()
    {
        $this->table = $this->TRoomActionStatistic;
        return $this;
    }

    public function TSystemCtrlWaterLog()
    {
        $this->table = $this->SystemCtrlWaterLog;
        return $this;

    }

}