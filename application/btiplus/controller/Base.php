<?php

namespace app\btiplus\controller;

use think\Controller;

class Base
{

    public function apiReturnText($errorCode, $errorMsg, $balance, $trxId = "")
    {
        header('Content-Type: text/plain');
        if (!empty($trxId)) {
            return 'error_code=' . $errorCode . PHP_EOL .
                'error_message=' . $errorMsg . PHP_EOL .
                'balance=' . $balance . PHP_EOL .
                'trx_id=' . $trxId;
        } else {
            return 'error_code=' . $errorCode . PHP_EOL .
                'error_message=' . $errorMsg . PHP_EOL .
                'balance=' . $balance . PHP_EOL;
        }

    }

}