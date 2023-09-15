<?php

namespace app\admin\controller\traits;
trait getSocketRoom
{

    public function getSocketRoom($socket, $roomid)
    {
        $roomInfo = $socket->getRoomInfo($roomid);
        if ($roomInfo) {
            $roomInfo = $roomInfo[0];
        } else {
            $roomInfo = [
                'nServerID'       => $roomid,
                'nCtrlRatio'      => 0,
                'nInitStorage'    => 0,
                'nCurrentStorage' => 0,
                'szStorageRatio'  => ''
            ];
        }

//        $roomInfo['nCurrentStorage'] /= 1000;
//        $roomInfo['nInitStorage']    /= 1000;
        $roomInfo['currentwinrate']   = 0;

        $storageInfo = [];
        if (isset($roomInfo['szStorageRatio']) && trim($roomInfo['szStorageRatio']) != '') {

            $storage = explode('#', $roomInfo['szStorageRatio']);
            $info    = array_chunk($storage, 2);
//            print_r()


            foreach ($info as $k1 => $v1) {
                if (intval($roomInfo['nCurrentStorage']) < intval($v1[0])) {

                    $roomInfo['currentwinrate'] = $v1[1];
                    break;
                }
            }


            foreach ($info as $k => $v) {



                if ($k == 0) {
                    $storageInfo[$k] = [
                        'rate'    => $v[1],
                        'storage' => '<' . $v[0]
                    ];
                } else {
                    $storageInfo[$k] = [
                        'rate'    => $v[1],
                        'storage' => $info[$k - 1][0] . '~' . $info[$k][0]
                    ];
                }

            }
        }
        $roomInfo['storage'] = $storageInfo;
        return $roomInfo;
    }


    public function getSocketNum($socket, $roomid)
    {
//        $roomInfo = $socket->getRoomInfo($roomid);
        $roomInfo = $socket->getRoomNum($roomid);

        if ($roomInfo) {
            $roomInfo = $roomInfo[0];
        } else {
            $roomInfo = [
                'nServerID'       => $roomid,
                'nCtrlRatio'      => 0,
                'nInitStorage'    => 0,
                'nCurrentStorage' => 0,
                'szStorageRatio'  => ''
            ];
        }
        $roomInfo['currentwinrate']   = 0;

        $storageInfo = [];
        if (isset($roomInfo['szStorageRatio']) && trim($roomInfo['szStorageRatio']) != '') {
            $storage = explode('#', $roomInfo['szStorageRatio']);
            $info    = array_chunk($storage, 2);
            foreach ($info as $k => $v) {
                if ($k == 0) {
                    $storageInfo[$k] = [
                        'rate'    => $v[1],
                        'storage' => '<' . $v[0]
                    ];
                } else if($k < count($info)-1){

                    $storageInfo[$k] = [
                        'rate'    => $v[1],
//                        'rate'    => $v[0],
                        'storage' => $info[$k - 1][0] . '~' . $info[$k][0]
                    ];
                }else{
                    $storageInfo[$k] = [
                        'rate'    => '',
//                        'rate'    => $v[0],
                        'storage' => $info[$k - 1][0] . '~' . $info[$k][0]
                    ];
                }

            }
        }
        $roomInfo['storage'] = $storageInfo;
        return $roomInfo;
    }




}