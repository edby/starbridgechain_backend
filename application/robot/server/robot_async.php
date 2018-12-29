<?php
include_once "./trade/cfg.php";
include_once "./trade/dblib.php";
include_once "./trade/whiner.php";

$serv = new swoole_server("127.0.0.1", 9909, SWOOLE_BASE);

$serv->set(array(
    'worker_num' => 1,
    'task_worker_num' => 1,
    'daemonize'=>1,
    'heartbeat_check_interval'=>5,
    'heartbeat_idle_time'=>10,
));

$serv->on('Receive', function(swoole_server $serv, $fd, $from_id, $data) {
    if ($data != '' && substr($data,0,9) != 'heartbeat'){
        $serv->task($data);
    }else{
        $msg = date('Y-m-d H:i:s').':获取数据为空!\n'.$data;
        save_log($msg);
    }
});

$serv->on('Task', function (swoole_server $serv, $task_id, $from_id, $data) {
    $data = json_decode($data,true);
    if (!empty($data)){
        $success_num = 0;
        $success_sum = 0;
        $fail_num = 0;
        $fail_sum = 0;
        $exception_num = 0;
        $exception_sum = 0;
        $fail_info = '';
        $ids = '';
        $ram_id = $data['ram_id'];
        unset($data['ram_id']);
        $count = count($data);
        foreach ($data as $datum) {
            $res = handleTask($datum['data1'],$datum['data2']);
            $ids .= "{$datum['data1']['ui_id']}-{$datum['data2']['ui_id']}-{$datum['data1']['total']} ";
            if ($res == 1){
                $success_num += 1;
                $success_sum += $datum['data1']['total'];
            }elseif ($res == 2){
                $exception_num += 1;
                $exception_sum += $datum['data1']['total'];
            }else{
                $fail_num += 1;
                $fail_sum += $datum['data1']['total'];
                $fail_info .= $res;
            }
        }
        $success_data = [
            'ram_id'        => $ram_id,
            'create_time'   => time(),
            'num'           => $success_num,
            'sum'           => $success_sum,
            'type'          => 1,
            'remark'        => "预计交易:{$count}笔,实际交易:{$success_num},交易总量:{$success_sum},参与人ID:{$ids}"
        ];
        save($success_data);
        if ($exception_num > 0){
            $exception_data = [
                'ram_id'        => $ram_id,
                'create_time'   => time(),
                'num'           => $exception_num,
                'sum'           => $exception_sum,
                'type'          => 2,
                'remark'        => "写入错误笔数:{$fail_num}笔,总量:{$success_sum}"
            ];
            save($exception_data);
        }
        if ($fail_num > 0){
            $fail_data = [
                'ram_id'        => $ram_id,
                'create_time'   => time(),
                'num'           => $fail_num,
                'sum'           => $fail_sum,
                'type'          => 3,
                'remark'        => $fail_info
            ];
            save($fail_data);
        }
        $serv->finish($data);
    }else{
        $msg = date('Y-m-d H:i:s')."接收数据为空\n".$data;
        save_log($msg);
    }
});
$serv->on('Finish', function (swoole_server $serv, $task_id, $data) {

});

$serv->on('workerStart',function($serv){
    swoole_set_process_name("robot_9909");
    $msg = date('Y-m-d H:i:s').":异步服务开启成功!\n";
    save_log($msg);
});

$serv->on('close',function (swoole_server $serv, $fd){
    $msg =  date('Y-m-d H:i:s').":客户端连接中断!\n";
    save_log($msg);
});


$serv->start();


/**
 * @param $data1
 * @param $data2
 * @return bool|int|string
 */
function handleTask($data1, $data2){
    $res = robotTrade($data1, $data2);
    if ($res === true){
        return 1;
    }elseif ($res == false){
        return 2;
    }else{
        return $res;
    }
}


/**
 * @param $msg
 */
function save_log($msg){
    $filename = "robot_status.log";
    file_put_contents($filename,$msg,FILE_APPEND);
}

/**
 * @param $data
 */
function save($data){
    $CFG = new cfgObject();
    $conn = DB::getInstance($CFG);
    $table = 'robot_record';
    db_insert($conn,$table,$data);

}

