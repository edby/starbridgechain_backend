<?php
namespace app\admin\validate;


use think\Validate;

class SysUser extends Validate
{
    protected $rule = [
        'account'               => 'require|unique:sys_user,SU_Acount',
        'name'                  => 'require|unique:sys_user,SU_Name',
        'pwd'                   => 'require',
        'repwd'                 => 'require|confirm:pwd',
        'duty'                  => 'require',
    ];

    protected $message = [
        'account.require'       => '账户未填写!',
        'account.unique'        => '账户名已存在!',
        'name.require'          => '用户名未填写!',
        'name.unique'           => '用户名已存在!',
        'pwd.require'           => '密码未填写!',
        'repwd.require'         => '重复密码未填写!',
        'repwd.confirm'         => '两次密码不一致!',
        'duty.require'          => '用户职位未填写!'
    ];


}