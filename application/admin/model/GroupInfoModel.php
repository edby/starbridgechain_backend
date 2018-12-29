<?php
namespace app\admin\model;


use think\Model;

class GroupInfoModel extends Model
{
    protected $table = 'group_info';
    //创建时间
    protected $createTime = 'create_time';

    protected $updateTime = false;

    //设置自动写入格式
    protected $autoWriteTimestamp = true;

}