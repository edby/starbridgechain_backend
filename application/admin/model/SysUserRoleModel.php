<?php
namespace app\admin\model;


use think\Model;

class SysUserRoleModel extends Model
{
    protected $table = 'sys_user_role';

    //创建时间
    protected $createTime = false;

    protected $updateTime = false;

}