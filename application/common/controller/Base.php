<?php
/**
 * Created by PhpStorm.
 * User: gh
 * Date: 2018/5/17
 * Time: 9:59
 */
namespace app\common\controller;
use redis\Redis;
use think\Config;
use think\Controller;
use think\facade\Lang;
class Base extends Controller
{
    protected $key;
    function __construct(){
        parent::__construct();
        $this->redis = Redis::instance();
        $this->key = config('auth.jwt_oauth_scr');
        $this->headersData = get_all_headers();
        Lang::setAllowLangList(['zh-cn','en-us',]);
    }



}