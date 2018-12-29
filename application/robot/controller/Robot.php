<?php

namespace app\robot\controller;


use redis\Redis;
use think\Controller;
use think\Db;
use think\Exception;
use think\Request;

class Robot extends Controller
{
    /**获取定时式机器人ID
     * @return string
     */
    public function saveRobotInfo()
    {
        $ids = Db::table('user_info')
            ->where('status','=',0)
            ->where('user_type','=',6)
            ->column('ui_id');

        if (empty($ids)){
            return "201";
        }else{
            $redis = Redis::instance();
            try{
                $redis->set('robot_ids',json_encode($ids));
            }catch (Exception $exception){
                return "202";
            }
            return "200";
        }

    }

    /**删除定时式机器人时间
     *
     */
    public function delRobotRedis()
    {
        $redis = Redis::instance();
        $redis->del('robot_timer');
    }

    public function excelRobot(Request $request)
    {
        $time = $request->param('time','');
        if ($request->ip() == '127.0.0.1' && $time != ''){//只允许本地请求接口
            $redis = Redis::instance();
            $redis->select(0);
            //获取redis中满足条件的机器人信息
            $robot_info = $redis->hget('excel_robot',$time);
            //根据机器人信息调用挂单方法

        }
    }

}