<?php
namespace app\admin\validate;


use think\Validate;

class SysModule extends Validate
{

    protected $rule = [
        'name'                  => 'require|unique:sys_module,SM_Name',
        'url'                   => 'require|unique:sys_module,SM_URL',
        'status'                => 'require',
    ];

    protected $message = [
        'name.require'          => '名称不能为空!',
        'name.unique'           => '名称已经存在!',
        'url.require'           => '链接不能为空!',
        'url.unique'            => '链接已经存在!',
        'status.require'        => '状态没有确定!',
    ];

}