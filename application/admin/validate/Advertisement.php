<?php
namespace app\admin\validate;


use think\Validate;

class Advertisement extends Validate
{
    protected $rule = [
        'ad_name'           => 'require',
        'content_url'       => 'require',
        'has_url'           => 'require',
        'status'            => 'require',
        'start_time'        => 'require',
        'end_time'          => 'require',
        'ad_url'            => 'require',
        'type'              => 'require',
        'show_time'         => 'require',
        'show_interval'     => 'require',
        'creater'           => 'require',
        'image_url'         => 'require',
        'image_url2'        => 'require',
    ];

    protected $msg = [
        'ad_name.require'           => '广告名称不能为空!',
        'content_url.require'       => '广告内容不能为空!',
        'has_url.require'           => '内容URL是否存在?',
        'status.require'            => '广告是否启用?',
        'start_time.require'        => '开始时间不能为空!',
        'end_time.require'          => '结束时间不能为空!',
        'ad_url.require'            => '请填写广告域名!',
        'type.require'              => '请选择广告类型!',
        'show_time.require'         => '请填写显示持续时间!',
        'show_interval.require'     => '请填写后台间隔启用时间(分)!',
        'creater.require'           => '缺少创建人!',
        'image_url.require'         => '缺少图片路径1!',
        'image_url2.require'        => '缺少图片路径2!'
    ];

}