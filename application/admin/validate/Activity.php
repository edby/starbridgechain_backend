<?php
namespace app\admin\validate;


use think\Validate;

class Activity extends Validate
{
    protected $rule = [
        'title'             => 'require|unique:content_management,cm_title',
        'content'           => 'require',
        'type'              => 'require',
        'language'          => 'require',
        'status'            => 'require',
    ];

    protected $message = [
        'title.require'     => '标题没有填写!',
        'title.unique'      => '标题已经存在!',
        'content.require'   => '内容不能为空!',
        'type.require'      => '类型不能为空!',
        'language.require'  => '语言未选择!',
        'status.require'    => '状态未选择!',
    ];

}