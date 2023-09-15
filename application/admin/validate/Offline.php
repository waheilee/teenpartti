<?php

namespace app\admin\validate;

use think\Validate;

class Offline extends Validate
{
    protected $rule = [
        //线下
        'classid'   => 'require|number|gt:0',
        'classname' => 'require',
        'bank'      => 'require',
        'cardno'    => 'require|number',
        'cardname'  => 'require',

    ];
    protected $message = [
        'classid.require'   => '类别ID不能为空',
        'classid.number'    => '类别ID格式有误',
        'classname.require' => '转账名称不能为空',
        'cardno.require'    => '卡号不能为空',
        'cardno.number'     => '卡号必须为数字',
        'cardname.require'  => '收款人姓名不能为空',
    ];
    protected $scene = [
        'editOffline' => [
            'classid',
            'classname',
            'bank',
            'cardno',
            'cardname',
        ],
    ];

}
