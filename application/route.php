<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

return [
    '__pattern__' => [
        'name' => '\w+',
    ],
    '[hello]'     => [
        ':id'   => ['index/hello', ['method' => 'get'], ['id' => '\d+']],
        ':name' => ['index/hello', ['method' => 'post']],
    ],

    '__domain__'=>[
        'merchant' => [':m/:c/:a'=>'merchant/:c/:a']
    ],
    '/VerifySession'=>'pggame/index/VerifySession',
    '/Cash/Get'=>'pggame/index/balance',
    '/Cash/TransferInOut'=>'pggame/index/TransferInOut',
    '/Cash/Adjustment'=>'pggame/index/Adjustment',
    '/Cash/UpdateBetDetail'=>'pggame/index/UpdateBetDetail',
];


