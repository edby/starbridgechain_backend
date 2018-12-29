<?php
namespace app\admin\model;


use think\Model;

class ContentManagementModel extends Model
{
    protected $table = 'content_management';

    //创建时间
    protected $createTime = 'createDate';

    protected $updateTime = 'updateDate';

    //设置自动写入格式
    protected $autoWriteTimestamp = true;
}