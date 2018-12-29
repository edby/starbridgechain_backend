<?php

namespace app\home\model;

use think\Model;

class UserInfoModel extends Model
{
    protected $table = "user_info";

    protected $autoWriteTimestamp = true;  //'datetime';

    protected $createTime = 'create_date';

    protected $updateTime = 'update_date';

//    use SoftDelete;
//    protected $deleteTime = 'delete_time';

//    public function customer()
//    {
//        return $this->belongsTo('Customer','customerId','id');
//    }
//
//    public function invite()
//    {
//        return $this->belongsTo('Customer','inviteId','id');
//    }
}
