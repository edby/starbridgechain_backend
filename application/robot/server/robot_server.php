<?php
//加载自定义方法
include "./function/robot_function.php";

//定时检索满足条件的机器人
$timer = 1000;

swoole_timer_tick($timer,function (){

    //获取  定时机器人列表
    $rb_list = getRobotList();

    foreach ($rb_list as $item) {
        setRobotTime($item);
    }

});