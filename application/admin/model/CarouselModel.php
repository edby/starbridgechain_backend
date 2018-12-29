<?php

namespace app\admin\model;


use think\Model;

class CarouselModel extends Model
{
    protected $table = 'sys_carousel';

    //创建时间
    protected $createTime = 'createDate';

    protected $updateTime = 'createDate';

    //开启自动写入时间戳
    protected $autoWriteTimestamp = true;
}