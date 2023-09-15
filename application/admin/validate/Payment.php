<?php

namespace app\admin\validate;

use think\Validate;

class Payment extends Validate
{
    protected $rule = [
        //固定金额
        'id'          => 'require|number|gt:0',
        'amount'      => 'require|number|gt:0',

        //通道
        'channelname' => 'require',
        //        'mchid' => 'require',
        //        'appid' => 'require',
        //        'noticeurl' => 'require',
        'channelid'   => 'require|number',
        'amountid'    => 'require|number',
        'classid'     => 'require|number',
        'status'      => 'require|number'
    ];
    protected $message = [
        'id.require'     => 'ID不能为空',
        'id.number'      => 'ID格式有误',
        'id.gt'          => 'ID格式有误',
        'amount.require' => '金额不能为空',
        'amount.number'  => '金额格式有误',
        'amount.gt'      => '金额格式有误',

        'channelname.require' => '通道名称不能为空',
        'channelid.require'   => '通道ID不能为空',
        'channelid.number'    => '通道ID格式有误',
        'status.require'      => '开关状态设置有误',
        'status.number'       => '开关状态设置有误',

        'amountid.require' => '金额不能为空',
        'amountid.number'  => '金额格式有误',
        'classid.require'  => '支付类型不能为空',
        'classid.number'   => '支付类型有误',
    ];
    protected $scene = [
        //固定金额
        'editAmount'       => [
            'id', 'amount'
        ],
        'addAmount'        => [
            'amount'
        ],
        'deleteAmount'     => [
            'id'
        ],

        //通道
        'addChannel'       => [
            'channelname'
        ],
        'editChannel'      => [
            'channelid', 'channelname'
        ],
        'deleteChannel'    => [
            'id'
        ],
        'setChannelStatus' => [
            'id', 'status'
        ],

        //关系
        'deletePayment'    => [
            'id'
        ],
        'addPayment'       => [
            'classid', 'channelid', 'amountid'
        ],
    ];

}
