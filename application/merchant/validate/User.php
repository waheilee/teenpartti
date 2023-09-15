<?php
namespace app\admin\validate;

use think\Validate;

class User extends Validate
{
    protected $rule = [
        'username'       => 'require|min:5|max:30|unique:user',
        'password'       => 'require|min:5',
//        'mobile'         => 'require|regex:/^1\d{10}$/|unique:user',
        'check_password' => 'require|confirm:password',
    ];
    protected $message = [
        'mobile.unique'          => '手机号已存在',
        'mobile.require'          => '手机号必须',
        'mobile.regex'          => '手机号格式有误',
        'username.require'       => '用户名必须',
        'username.unique'        => '用户名重复',
        'username.min'           => '用户名最短6位',
        'username.max'           => '用户名最长30位',
        'password.require'       => '密码必须',
        'password.min'           => '用户名最短6位',
        'check_password.require' => '请确认密码',
        'check_password.confirm' => '输入密码不一致',
    ];
    protected $scene = [
        'login' => ['username' => 'require|min:5|max:30', 'password', 'mobile' => 'require|regex:/^1\d{10}$/'],
        'edit'  => [
            'username',
            'mobile' ,
        ],
        'editPassword'  => [
            'username',
            'mobile' ,
            'password',
            'check_password',
        ],
    ];
}
