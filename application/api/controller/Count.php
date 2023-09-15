<?php
namespace app\api\controller;

use socket\QuerySocket;
use think\Controller;
use app\common\Api;
use think\Db;

class Count extends Controller
{
    private $db2;
    public function __construct()
    {
        parent::__construct();
        $this->db2 = config('database_qd.database');
    }

    //统计渠道pv  uv  ip
    public function index()
    {
        save_log('api/count', json_encode($_REQUEST));
        $key = $_REQUEST['key'];
        $proxyid = $_REQUEST['proxyid'];
        $day = $_REQUEST['day'];
        $pv = $_REQUEST['pv'] ? intval($_REQUEST['pv']) : 0;
        $uv = $_REQUEST['uv'] ? intval($_REQUEST['uv']) : 0;
        $ip = $_REQUEST['ip'] ? intval($_REQUEST['ip']) : 0;

        if (!$proxyid || !$day) {
            save_log('api/count', 'wrong data');
            exit;
        }
        if ($key != 'wf123520') {
            save_log('api/count', 'wrong key');
            exit;
        }

        $countmodel = Db::connect($this->db2)->name('game_count');
        $data = [
            'proxyid' => $proxyid,
            'day' => $day,
            'pv' => $pv,
            'uv' => $uv,
            'ip' => $ip,
            'updatetime' => date('Y-m-d H:i:s')
        ];
        $find = $countmodel->where(['proxyid' => $proxyid, 'day' => $day])->find();
        if ($find) {
            $countmodel->where(['id' => $find['id']])->update($data);
        } else {
            $countmodel->insertGetId($data);
        }

    }
}