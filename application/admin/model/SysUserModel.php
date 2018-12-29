<?php
namespace app\admin\model;


use think\Model;

class SysUserModel extends Model
{
    protected $table = 'sys_user';

    //创建时间
    protected $createTime = 'SU_CreateTime';

    protected $updateTime = false;

    //设置自动写入格式
    protected $autoWriteTimestamp = true;


}