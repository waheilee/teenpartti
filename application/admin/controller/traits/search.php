<?php
namespace app\admin\controller\traits;
use app\common\Api;
trait search {

    public function getRoomById($kindId)
    {
        $res = Api::getInstance()->sendRequest(['id' => $kindId], 'room', 'kind');
        return $res['data'];
    }



}