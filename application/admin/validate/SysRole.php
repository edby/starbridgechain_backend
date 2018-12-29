<?php
namespace app\admin\validate;


use think\Validate;

class SysRole extends Validate
{
    protected $rule = [
        'name'          => 'require|unique:sys_role,SR_Name',
        'remark'        => 'require',
    ];

    protected $message = [
        'name.require'  => '角色名未填写!',
        'name.unique'   => '角色名已经存在!',
        'remark.require'=> '备注未填写!'
    ];

}