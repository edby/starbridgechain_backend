<?php

namespace app\home\model;

use think\Model;

class UserFinanceModel extends Model
{
    protected $table = "user_finance";

    protected $autoWriteTimestamp = true;  //'datetime';

    protected $createTime = 'create_time';

    protected $updateTime = 'update_time';

//    use SoftDelete;
//    protected $deleteTime = 'delete_time';
}
