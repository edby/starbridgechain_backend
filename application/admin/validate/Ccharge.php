<?php
namespace app\admin\validate;


use think\Validate;

class Ccharge extends Validate
{
    protected $rule = [
        'single_minlimit'   => 'number|between:0,1542090244',
        'status'           => 'number|between:0,1',
        'msg'              => 'require|max:256',

    ];

    protected $message = [
        'single_minlimit'     => '时间参数错误',
        'status'      => '状态参数错误',
        'msg'   => '提示信息参数错误',
    ];

}