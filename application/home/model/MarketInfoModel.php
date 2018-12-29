<?php

namespace app\home\model;

use think\Model;

class MarketInfoModel extends Model
{
    protected $table = "market_info";

    protected $autoWriteTimestamp = true;  //'datetime';

    protected $createTime = 'create_date';

    protected $updateTime = 'update_date';

//    use SoftDelete;
//    protected $deleteTime = 'delete_time';
}
