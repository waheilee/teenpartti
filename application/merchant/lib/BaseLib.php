<?php

namespace app\admin\lib;

Class BaseLib {
    
    /**
     * 返回错误信息。
     * @param type $message
     */
    protected function failData($message = null) {
        return array('status' => false, 'msg' => $message);
    }

    /**
     * 返回成功信息
     * @param type $data
     */
    protected function successData($data = NULL) {
        return array('status' => true, 'data' => $data);
    }
    
}

