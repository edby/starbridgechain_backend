<?php
namespace app\admin\validate;


use think\Validate;

class GroupInfo extends Validate
{
    protected $rule = [
        'name'              => 'require|unique:group_info',
        'fee'               => 'require|number',
        'show_fee'          => 'require|number',
    ];

    protected $message = [
        'name.require'      => '用户组名不能为空!',
        'name.unique'       => '组名已经存在!',
        'fee.require'       => '费率不能为空!',
        'show_fee.require'  => '展示费率不能位空!',
        'fee.number'        => '费率必须为数字',
        'show_fee.number'   => '展示费率不能为空!'
    ];

}