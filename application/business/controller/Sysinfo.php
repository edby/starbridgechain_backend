<?php

namespace app\business\controller;
use think\Config;
use think\Db;
use app\common\controller\Base;
use think\facade\Request;

class Sysinfo extends Base
{
	function __construct(){
        parent::__construct();
    }
    public function coinList(){
        $list = Db::name('coin_info')->field('ci_id,name,short_name,logo')->where(['status'=>1])->select();
        if(!empty($list)){
            foreach ($list as $k => $v) {
                $list[$k]['logo'] = config('admin_http_url').$v['logo'];
                $list[$k]['name'] = urlencode($v['name']);
            }
        }
        ouputJson(200,'',$list);
    }

}
