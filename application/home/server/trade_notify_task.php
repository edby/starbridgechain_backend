<?php

include_once dirname(__FILE__)."/cfg.php";
include_once dirname(__FILE__)."/dblib.php";
//include_once (dirname(dirname(__FILE__)))."/lib/MysqlPool.php";
include_once dirname(__FILE__)."/DB.class.php";
include_once dirname(__FILE__)."/../../../common/common.php";

define('BCPRECISEDIGITS', 13); //小数点精确位数
define('MYACTION', 'trans');

//$redis = new Redis();
//
//$redis->connect($CFG->redis_host, $CFG->redis_port);
//if($CFG->redis_pwd != ""){
//    $redis->auth($CFG->redis_pwd);
//}

$serv = new swoole_server("127.0.0.1", 9595/*, SWOOLE_BASE*/);
$serv->set(array(
    'worker_num' => 4,
    'task_worker_num' => 1,
//    'task_ipc_mode' => 2,
//    'message_queue_key' => 2,
    'daemonize' => 1,
    'log_file' => '/www/web/swoole9595/log/'.date('Ymd').'swoole9595.log',
//    'open_eof_split'=>true,
//    'package_eof'=>'\r\n',
    'open_length_check' => true,
    'package_max_length' => 81920,
    'package_length_type' => 'N', //see php pack()
    'package_length_offset' => 0,
    'package_body_offset' => 4,
));

//注册事件回调函数-swoole服务:9595
$serv->on('start',function($server){
    swoole_set_process_name("ghm_swoole_9595");
	echo '['.date('Y-m-d H:i:s')."]启动成功\n";
});
//$serv->on('connect', function ($serv, $fd, $reactor_id) {
//    /**
//     * $fd是TCP客户端连接的标识符，在Server程序中是唯一的
//     * $reactor_id是来自于哪个reactor线程
//     */
//    echo '连接的文件描述符:'.$fd,'线程:'.$reactor_id;
//});

$atomic = new swoole_atomic();
//接收到数据时回调此函数
$serv->on('receive', function($serv, $fd, $from_id, $data) {
    $task_id = $serv->task($data);
    global $atomic;
    $num = $atomic->add();
    $atomic->cmpset(4100000000,0);
    echo "Reception times=[$num]--AsyncTask: id=$task_id\n";
});

$serv->on('task', function ($serv, $task_id, $from_id, $data) use ($CFG){
//    if (empty($data)){
//        echo '-------------------------'.PHP_EOL.PHP_EOL.PHP_EOL;
//    }
//    echo substr($data, 4).PHP_EOL;DIE;return;
    $ret = handleTask($CFG, $data);
    return $ret;

//    1.7.2以上的版本，在onTask函数中 return字符串，表示将此内容返回给worker进程。
//    worker进程中会触发onFinish函数，表示投递的task已完成。
//    return的变量可以是任意非null的PHP变量
});

$serv->on('finish',function($serv, $task_id, $data){
    $ret = json_decode($data,true);
//    if ($ret['status'] == false){
//        writeLog($ret['msg']);
//    }
    echo $task_id.'finish输出task的处理:'.$ret['msg']."\n";
});

$serv->on('close',function($serv, $fd, $reactorId){
    echo '['.date('Y-m-d H:i:s')."]关闭成功\n";
});

$serv->start();

/**
 * 生成错误详情
 * @param Exception $e
 * @return string
 */
function myGetTrace(Exception $e)
{
    if (isset($e->getTrace()[0]['args'][2]) && is_string($e->getTrace()[0]['args'][2])){
        $trace = $e->getTrace()[0]['args'][2].':'.$e->getLine();
    }elseif (isset($e->getTrace()[0]['file']) && is_string($e->getTrace()[0]['file'])){
        $trace = $e->getTrace()[0]['file'].':'.$e->getTrace()[0]['line'];
    }else {
        $trace = '';
    }
    $trace = '['.json_encode($e->getCode()).']'.$e->getMessage().'['.$trace.']';
    return $trace;
}

//解封接受的数据
function getUnserialize($data)
{
    $recv = unserialize(substr($data, 4));
    return $recv;
}
/**
 * task内执行异步任务写入数据
 * @param $CFG
 * @param $data
 * @return string
 */
function handleTask($CFG, $data){
    try {
//        $data = json_decode($data,true);
        $data = getUnserialize($data);
        if(!empty($data)){
//            $conn = db_connect($CFG->dbhost, $CFG->dbname, $CFG->dbuser, $CFG->dbpass);
            $conn = DB::getInstance($CFG);
            switch ($data['type']){
                case 'restingOrder':
                    if (!empty($data['data'])){
                        asyncMysql_ld($conn, $data['data'],$data['data']['create_time']);
                    }else{
                        $nodata = json_encode($data);
                        writeLog("@unknown-nodata | 异步任务数据为空1，data:{$nodata}。");
                    }
                    break;
                case 'trade':
                    if (!empty($data['data'])){
                        asyncMysql_td($conn, $data['data']);
                    }else{
                        $nodata = json_encode($data);
                        writeLog("@unknown-nodata | 异步任务数据为空2，data:{$nodata}。");
                    }
                    break;
                case 'marketPrice':
                    if (!empty($data['data'])){
                        asyncMysql_mtd($conn, $data['data']);
                    }else{
                        $nodata = json_encode($data);
                        writeLog("@unknown-nodata | 异步任务数据为空3，data:{$nodata}。");
                    }
                    break;
            }
            return json_encode([
                'status'        =>   true,
                'msg'           =>   'OK!以上如果有错误,会在方法内进行错误日志写入',
            ]);
//      writeLog("unkonw | hash:{$data['hash']} | ci_id:{$data['ci_id']} | 币种不存在或不可用。");
        }else{
            $nodata = json_encode($data);
            writeLog("@unknown-nodata | 异步任务数据为空4，data:{$nodata}。");
//            $ret = [
//                'status'        =>   false,
//                'msg'           =>   "unknown-nodata | 异步任务数据为空5，data:{$nodata}。",
//            ];
//            return json_encode($ret);
        }
    } catch (Exception $e) {
        $error = myGetTrace($e);
        writeLog("@unknown-error | 代码执行中出现服务器异常，error:{$error}。");
    }
    return json_encode([
        'status'        =>   false,
        'msg'           =>   'handleTask出现异常错误!',
    ]);
}

/**
 * 挂单数据写入数据库
 * @param $CFG          : 数据库和Redis的连接配置
 * @param $data         : 数据
 * @param $timeS        : 数据生成时间
 * @param int $saveType : 类型(0新增/1修改)
 */
function asyncMysql_ld($conn, $data, $timeS){
    try {

        $year = date('Y', $timeS);
        $table = 'market_trade' . $year . '_' . $data['mi_id'];

        $res = existTableTrade($conn, $table);
        if ($res !== true) {
            writeLog('数据表:'.$table.'创建失败1!数据:<' . json_encode($data) . '>!');
            return false;
        }

//        $sql = "SELECT * FROM `{$table}` WHERE `order_no` = '{$data['order_no']}' LIMIT 1";
//        if (($saveType = db_query($conn,$sql))) {
//            $r = db_update($conn, $table, $data['order_no'], $data, 'order_no');
//        } else {
//            $r = db_insert($conn, $table, $data);
//        }
        $field = '';
        foreach ($data as $key=>$val){
            $field .= "`$key`='" . addslashes ( $val ) . "',";
        }
        $field = substr ( $field, 0, - 1 );
        $sql = "insert into `{$table}` SET {$field} ON DUPLICATE KEY UPDATE {$field}";
        $r = db_query($conn,$sql);
        if (!$r && $r != 0) {
            $sqlE = "SELECT * FROM `{$table}` WHERE `order_no` = '{$data['order_no']}' AND `decimal` = '{$data['decimal']}' AND `total` = '{$data['total']}' AND `ui_id` = '{$data['ui_id']}' LIMIT 1";
            if (!(db_query($conn,$sqlE))){
                writeLog('[0未变化/1新增/2更新]:' . $r . ',数据:<' . json_encode($data) . '>,未能写入->' . $table);
                return false;
            }
        }
        return true;
    } catch (Exception $e) {
        $error = myGetTrace($e);
        writeLog("unknown-error | 挂单数据写入数据库异常1，error:{$error}。");
        return false;
    }
}


/**
 * 创建挂单表-(market_trade.date('Y').$market)!!
 * @param $conn         : 数据库连接标识
 * @param $table        : 数据表
 * @return bool         : 返回值
 */
function existTableTrade($conn, $table)
{
    //>>判断表存不存在
//        $table = 'market_trade'. date('Y'). $transactionPair['mi_id'];
    $sql = "show tables like '{$table}'";
    $tableName = db_query($conn,$sql);
//        var_dump($tableName);
    if ($tableName != 0){
        return true;
    }

    $sql = <<<sql
        CREATE TABLE `$table` (
          `mt_id` int(11) NOT NULL AUTO_INCREMENT,
          `ui_id` int(11) NOT NULL DEFAULT '0' COMMENT '账户表ID',
          `mi_id` int(11) NOT NULL DEFAULT '0' COMMENT '交易市场名称ID',
          `type` int(11) NOT NULL DEFAULT '0' COMMENT '类型1：买，2：卖',
          `price` decimal(30,13) NOT NULL DEFAULT '0.000000000000000' COMMENT '单价',
          `total` decimal(30,13) NOT NULL DEFAULT '0.000000000000000' COMMENT '挂单总数',
          `decimal` decimal(30,13) NOT NULL DEFAULT '0.000000000000000' COMMENT '剩余数量',
          `fee` decimal(30,13) NOT NULL DEFAULT '0.000000000000000' COMMENT '买入手续费',
          `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '成交时间',
          `update_time` int(11) NOT NULL DEFAULT '0' COMMENT '更新时间',
          `microS` char(11) NOT NULL DEFAULT '' COMMENT '时间戳的微妙单位',
          `status` int(11) NOT NULL DEFAULT '0' COMMENT '状态：1:交易中  2:已完成  3:已撤销  4:异常',
          `order_no` varchar(255) NOT NULL DEFAULT '' COMMENT '交易流水号',
          `limit_market` tinyint(4) NOT NULL DEFAULT '0' COMMENT '1限价/2市价',
          PRIMARY KEY (`mt_id`) USING BTREE,
          UNIQUE KEY `order_no` (`order_no`) USING BTREE,
          KEY `FK_Reference_3` (`ui_id`) USING BTREE,
          KEY `FK_Reference_4` (`mi_id`) USING BTREE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='未了使页面查询速度较快，需要进行分表\r\n交易市场交易挂单表 ';
sql;
    $result = db_query($conn, $sql);
    if ($result == 0){ //数据库创建成功返回0
        return true;
    }else{
        $sql = "show tables like '{$table}'";
        $tableName = db_query($conn, $sql);
        if ($tableName != 0){
            return true;
        }
        return false;
    }
}

/**
 * 创建财产变动日志表-(user_finance_log.date('Y-m'))!!
 *
 * @param $table        :   表名!
 */
function existTableFinanceLog($conn, $table)
{
    $sql = "show tables like '{$table}'";
    $tableName = db_query($conn,$sql);
    if ($tableName != 0){
        return true;
    }

    $sql = <<<sql
        CREATE TABLE `$table` (
          `ufl_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `ui_id` int(11) NOT NULL DEFAULT '0' COMMENT '用户ID',
          `mi_id` int(11) NOT NULL DEFAULT '0' COMMENT '交易市场ID',
          `ci_id` int(11) NOT NULL DEFAULT '0' COMMENT '币种ID',
          `bef_A` decimal(30,13) NOT NULL DEFAULT '0.000000000000000' COMMENT '交易前余额',
          `bef_B` decimal(30,13) NOT NULL DEFAULT '0.000000000000000' COMMENT '交易前冻结',
          `bef_C` decimal(30,13) NOT NULL DEFAULT '0.000000000000000' COMMENT '提取前冻结',
          `bef_D` decimal(30,13) NOT NULL DEFAULT '0.000000000000000' COMMENT '交易前总计',
          `num` decimal(30,13) NOT NULL DEFAULT '0.000000000000000' COMMENT '本次变动数额',
          `type` tinyint(4) NOT NULL DEFAULT '0' COMMENT '类型1：买，2：卖',
          `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '成交时间',
          `aft_A` decimal(30,13) NOT NULL DEFAULT '0.000000000000000' COMMENT '交易后余额',
          `aft_B` decimal(30,13) NOT NULL DEFAULT '0.000000000000000' COMMENT '交易后冻结',
          `aft_C` decimal(30,13) NOT NULL DEFAULT '0.000000000000000' COMMENT '提取后冻结',
          `aft_D` decimal(30,13) NOT NULL DEFAULT '0.000000000000000' COMMENT '交易后总计',
          `status` int(11) NOT NULL DEFAULT '1' COMMENT '状态：（0：不可用，1：可用）',
          `order_no` varchar(50) NOT NULL DEFAULT '' COMMENT '交易流水号',
          PRIMARY KEY (`ufl_id`) USING BTREE,
          KEY `FK_Reference_23` (`ui_id`) USING BTREE,
          KEY `FK_Reference_28` (`mi_id`) USING BTREE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='用户财产变动表\r\n数据量较大，需要进行分表';
sql;

    $result = db_query($conn, $sql);
    if ($result == 0){
        return true;
    }else{
        $sql = "show tables like '{$table}'";
        $tableName = db_query($conn, $sql);
        if ($tableName != 0){
            return true;
        }
        return false;
    }
}

/**
 * 创建成交日志表-(market_trade_log.date('Y').$market)!!
 *
 * @param $table
 */
function existTableTradeLog($conn, $table)
{
    $sql = "show tables like '{$table}'";
    $tableName = db_query($conn, $sql);
    if ($tableName != 0){
        return true;
    }

    $sql = <<<sql
        CREATE TABLE `$table` (
          `mt_id` int(11) NOT NULL AUTO_INCREMENT,
          `mt_order_ui_id` int(11) NOT NULL DEFAULT '0' COMMENT '挂单方',
          `mt_peer_ui_id` int(11) NOT NULL DEFAULT '0' COMMENT '对手方',
          `type` tinyint(4) NOT NULL DEFAULT '0' COMMENT '交易类型[1:买/2:卖]',
          `mi_id` int(11) NOT NULL DEFAULT '0' COMMENT '交易市场ID',
          `price` decimal(30,13) NOT NULL DEFAULT '0.000000000000000' COMMENT '单价',
          `decimal` decimal(30,13) NOT NULL DEFAULT '0.000000000000000' COMMENT '数量',
          `amount` decimal(30,13) NOT NULL DEFAULT '0.000000000000000' COMMENT '总额',
          `buy_fee` decimal(30,13) NOT NULL DEFAULT '0.000000000000000' COMMENT '买方手续费',
          `sell_fee` decimal(30,13) NOT NULL DEFAULT '0.000000000000000' COMMENT '卖方手续费',
          `status` int(11) NOT NULL DEFAULT '1' COMMENT '状态：0：未成交，1：成交',
          `order_no` varchar(50) NOT NULL DEFAULT '' COMMENT '交易流水号',
          `peer_order_no` varchar(50) NOT NULL DEFAULT '' COMMENT '对手方流水号',
          `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
          PRIMARY KEY (`mt_id`) USING BTREE,
          KEY `FK_Reference_38` (`mi_id`) USING BTREE,
          KEY `status` (`status`),
          KEY `mi_id` (`mi_id`),
          KEY `create_time` (`create_time`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='交易记录表';
sql;

    $result = db_query($conn, $sql);
    if ($result == 0){
        return true;
    }else{
        $sql = "show tables like '{$table}'";
        $tableName = db_query($conn, $sql);
        if ($tableName != 0){
            return true;
        }
        return false;
    }
}


//撮合数据写入数据库
function asyncMysql_td($conn, $data){

    try {
        extract($data);
        $other['price'];
        $other['coin1'];
        $other['coin2'];
        $marketTradeF;
        $marketTradeR;
        $marketTradeLog;
//        $pairPrice = $marketTradeLog['price']; //实际成交价

        /*$map0 = [
            ['ui_id','=',$marketTradeLog['mt_order_ui_id']],
            ['ci_id','=',$other['coin1']],
        ];
        $map1 = [
            ['ui_id','=',$marketTradeLog['mt_order_ui_id']],
            ['ci_id','=',$other['coin2']],
        ];
        $map2 = [
            ['ui_id','=',$marketTradeLog['mt_peer_ui_id']],
            ['ci_id','=',$other['coin1']],
        ];
        $map3 = [
            ['ui_id','=',$marketTradeLog['mt_peer_ui_id']],
            ['ci_id','=',$other['coin2']],
        ];*/
        $user_type = 4;
        $sql99 = "SELECT `ui_id` FROM `user_info` WHERE  `user_type` = {$user_type} LIMIT 1";
        $feeAccountId = db_query_array($conn,$sql99);
        $feeAccountId = $feeAccountId[0]['ui_id'];

//        $conn = db_connect($CFG->dbhost,$CFG->dbname,$CFG->dbuser,$CFG->dbpass);
        $sql0 = "SELECT * FROM `user_finance` WHERE ( `ui_id` = '{$marketTradeLog['mt_order_ui_id']}' AND `ci_id` = '{$other['coin1']}' ) OR ( `ui_id` = '{$marketTradeLog['mt_order_ui_id']}' AND `ci_id` = '{$other['coin2']}' ) ORDER BY `ci_id` ASC";
        $sql1 = "SELECT * FROM `user_finance` WHERE ( `ui_id` = '{$marketTradeLog['mt_peer_ui_id']}' AND `ci_id` = '{$other['coin1']}' ) OR ( `ui_id` = '{$marketTradeLog['mt_peer_ui_id']}' AND `ci_id` = '{$other['coin2']}' ) ORDER BY `ci_id` ASC";
        $userFinances = db_query_array($conn,$sql0); //返回结果集数组
        $valueFinances = db_query_array($conn,$sql1); //返回结果集数组
        if ($userFinances[0]['ci_id'] == $other['coin2']){
            $userFinances = array_reverse($userFinances);
        }
        if ($valueFinances[0]['ci_id'] == $other['coin2']){
            $valueFinances = array_reverse($valueFinances);
        }

        db_start_transaction($conn);
        try {
            propertyChange(
                $conn, $data,
                $marketTradeF, $marketTradeR,
                $marketTradeLog, $other,
                $feeAccountId,
                $userFinances, $valueFinances
            );
            db_commit($conn);
        } catch (Exception $e) {
            db_rollback($conn);
            $error = myGetTrace($e);
//            writeLog("@unknown-error | 挂单数据写入数据库异常2，error:{$error}。");
            trigger_error($error);
        }
    } catch (Exception $e) {
        extract($data);
        $marketTradeF['status'] = 7;
        $marketTradeR['status'] = 7;
        db_insert($conn, 'exception_trade', $marketTradeF);
        db_insert($conn, 'exception_trade', $marketTradeR);
        db_insert($conn, 'exception_trade_log', $marketTradeLog);

        $error = myGetTrace($e);
        writeLog("@unknown-error | 成交记录写入数据库异常2，error:{$error}。");
    }
}

function propertyChange
(
    $conn, $data,
    $marketTradeF, $marketTradeR,
    $marketTradeLog, $other,
    $feeAccountId,
    $userFinances, $valueFinances
)
{
    $buy_fee   = $marketTradeLog['buy_fee']; //买方手续费
    $sell_fee  = $marketTradeLog['sell_fee']; //卖方手续费
    $pairFirst = $marketTradeLog['decimal']; //币1实际成交数量
    $price     = $other['price']; //主动方挂单价

    bcscale(BCPRECISEDIGITS);
    $ret = asyncMysql_ld($conn,$marketTradeF,$marketTradeF['create_time']);
    if ($ret == false){
        trigger_error('@撮合交易后,挂单完成的数据1:{'.json_encode($marketTradeF).'},写入数据库时出错!');
    }
    $ret = asyncMysql_ld($conn,$marketTradeR,$marketTradeR['create_time']);
    if ($ret == false){
        trigger_error('@撮合交易后,挂单完成的数据2:{'.json_encode($marketTradeR).'},写入数据库时出错!');
    }
    switch ($marketTradeLog['type']){
        case 1://主动方:(买)
            //主动方:
            //币1 $userFinances[0] //余额: 加上交易数量 | 减去手续费 $userActual
//                    $buy_fee = $buy_fee/100;
//                    $userActual = bcmul($pairFirst,(1-$buy_fee),BCPRECISEDIGITS);
            $userActual = bcsub($pairFirst,$buy_fee,BCPRECISEDIGITS);
//                    $amount0 = [
//                        'amount'          =>   bcadd($userFinances[0]['amount'],$userActual,BCPRECISEDIGITS),
//                        'update_time'     =>   $marketTradeLog['create_time'],
//                    ];
//                    db_update($conn,
//                        'user_finance',
//                        [$marketTradeLog['mt_order_ui_id'],$other['coin1']/*,$userFinances[0]['amount']*/],
//                        $amount0,
//                        ['ui_id','ci_id'/*,'amount'*/]);
//            $sql0 = "UPDATE `user_finance` SET `amount` = `amount`+'{$userActual}' , `update_time` = {$marketTradeLog['create_time']}  WHERE  `ui_id` = {$marketTradeLog['mt_order_ui_id']}  AND `ci_id` = {$other['coin1']}";
//            db_query($conn,$sql0);
            $param = [
                ['field'=>'amount','type'=>'inc','val'=>$userActual],
            ];
            $ret = updateUserBalance($marketTradeLog['mt_order_ui_id'],$other['coin1'],MYACTION,$param,$conn);
            if ($ret < 1){
                exception('updateUserBalance修改余额失败!');
            }

            /**手续费写入对应账户**/
//                    $feeAccount = bcsub($pairFirst,$userActual,BCPRECISEDIGITS);
//            $sql99 = "UPDATE `user_finance`  SET `amount` = `amount`+'{$buy_fee}' , `update_time` = {$marketTradeLog['create_time']}  WHERE  `ui_id` = {$feeAccountId}  AND `ci_id` = {$other['coin1']}";
//            db_query($conn,$sql99);
            $param = [
                ['field'=>'amount','type'=>'inc','val'=>$buy_fee],
            ];
            $ret = updateUserBalance($feeAccountId,$other['coin1'],MYACTION,$param,$conn);
            if ($ret < 1){
                trigger_error('updateUserBalance修改余额失败!');
            }

            $befTotal0 = bcadd($userFinances[0]['amount'],$userFinances[0]['trans_frost']);
            $aft_A0 = bcadd($userFinances[0]['amount'],$userActual);
            $aftTotal0 = bcadd($aft_A0,$userFinances[0]['trans_frost'],BCPRECISEDIGITS);
            $userFinanceLog0 = [
                'ui_id'             =>      $marketTradeLog['mt_order_ui_id'],
                'mi_id'             =>      $marketTradeLog['mi_id'],
                'ci_id'             =>      $other['coin1'],
                'bef_A'             =>      $userFinances[0]['amount'],   //交易前余额
                'bef_B'             =>      $userFinances[0]['trans_frost'],  //交易前冻结
                'bef_D'             =>      $befTotal0,           //交易前总计
                'num'               =>      $userActual,           //本次变动数额
                'type'              =>      $marketTradeLog['type'],   //1是买 2是卖
                'create_time'       =>      $marketTradeLog['create_time'],
                'aft_A'             =>      $aft_A0,         //交易后余额
                'aft_B'             =>      $userFinances[0]['trans_frost'],      //交易后冻结
                'aft_D'             =>      $aftTotal0,    //交易后总计
                'order_no'          =>      $marketTradeLog['order_no'],//交易流水号
            ];

            //币2 $userFinances[1] //余额: 加上差价 $disparity, 冻结: 减去成交量 $pairSecond_bef
            $pairSecond = $marketTradeLog['amount'];//成交价*实际成交数量
            $pairSecond_bef = bcmul($price,$pairFirst);//之前冻结数 全部取消冻结,差价返还给余额
            $disparity = bcsub($pairSecond_bef,$pairSecond);//差价
//                    $amount1 = [
//                        'amount'          =>   bcadd($userFinances[1]['amount'],$disparity,BCPRECISEDIGITS),
//                        'trans_frost'     =>   bcsub($userFinances[1]['trans_frost'],$pairSecond_bef,BCPRECISEDIGITS),
//                        'update_time'     =>   $marketTradeLog['create_time'],
//                    ];
//                    db_update($conn,
//                        'user_finance',
//                        [$marketTradeLog['mt_order_ui_id'],$other['coin2']/*,$userFinances[1]['amount'],$userFinances[1]['trans_frost']*/],
//                        $amount1,
//                        ['ui_id','ci_id'/*,'amount','trans_frost'*/]);
            $ret1 = checkFinance($userFinances[1],$pairSecond_bef);
            if ($ret1 === false){
                trigger_error("用户[{$marketTradeLog['mt_order_ui_id']},{$other['coin2']}]-$pairSecond_bef=余额为负");
            }
//            $sql1 = "UPDATE `user_finance`  SET `amount` = `amount`+'{$disparity}' , `trans_frost` = `trans_frost`-'{$pairSecond_bef}' , `update_time` = {$marketTradeLog['create_time']}  WHERE  `ui_id` = {$marketTradeLog['mt_order_ui_id']}  AND `ci_id` = {$other['coin2']}";
//            db_query($conn,$sql1);
            $param = [
                ['field'=>'amount','type'=>'inc','val'=>$disparity],
                ['field'=>'trans_frost','type'=>'dec','val'=>$pairSecond_bef],
            ];
            $ret = updateUserBalance($marketTradeLog['mt_order_ui_id'],$other['coin2'],MYACTION,$param,$conn);
            if ($ret < 1){
                trigger_error('updateUserBalance修改余额失败!');
            }

            $befTotal1 = bcadd($userFinances[1]['amount'],$userFinances[1]['trans_frost']);
            $aft_A1 = bcadd($userFinances[1]['amount'],$disparity);
            $aft_B1 = bcsub($userFinances[1]['trans_frost'],$pairSecond_bef);
            $aftTotal1 = bcadd($aft_A1,$aft_B1,BCPRECISEDIGITS);
            $userFinanceLog1 = [
                'ui_id'             =>      $marketTradeLog['mt_order_ui_id'],
                'mi_id'             =>      $marketTradeLog['mi_id'],
                'ci_id'             =>      $other['coin2'],
                'bef_A'             =>      $userFinances[1]['amount'],
                'bef_B'             =>      $userFinances[1]['trans_frost'],
                'bef_D'             =>      $befTotal1,
                'num'               =>      $pairSecond_bef,
                'type'              =>      $marketTradeLog['type'],
                'create_time'       =>      $marketTradeLog['create_time'],
                'aft_A'             =>      $aft_A1,
                'aft_B'             =>      $aft_B1,
                'aft_D'             =>      $aftTotal1,
                'order_no'          =>      $marketTradeLog['order_no'],
            ];

            //主动方 = 被动方
            if ($marketTradeLog['mt_order_ui_id'] == $marketTradeLog['mt_peer_ui_id']){
                $userFinances[0]['amount'] = $aft_A0;
                $valueFinances[0] = $userFinances[0];
                unset($userFinances[0]);
                $userFinances[1]['amount'] = $aft_A1;
                $userFinances[1]['trans_frost'] = $aft_B1;
                $valueFinances[1] = $userFinances[1];
                unset($userFinances[1]);
            }

            //被动方:
            //币1 $valueFinances[0] //冻结: 减去交易数量 $pairFirst
//                    $amount2 = [
//                        'trans_frost'     =>   bcsub($valueFinances[0]['trans_frost'],$pairFirst,BCPRECISEDIGITS),
//                        'update_time'     =>   $marketTradeLog['create_time'],
//                    ];
//                    db_update($conn,
//                        'user_finance',
//                        [$marketTradeLog['mt_peer_ui_id'],$other['coin1']/*,$valueFinances[0]['trans_frost']*/],
//                        $amount2,
//                        ['ui_id','ci_id'/*,'trans_frost'*/]);
            $ret1 = checkFinance($valueFinances[0],$pairFirst);
            if ($ret1 === false){
                trigger_error("用户[{$marketTradeLog['mt_peer_ui_id']},{$other['coin1']}]-$pairFirst=余额为负");
            }
//            $sql2 = "UPDATE `user_finance`  SET `trans_frost` = `trans_frost`-'{$pairFirst}' , `update_time` = {$marketTradeLog['create_time']}  WHERE  `ui_id` = {$marketTradeLog['mt_peer_ui_id']}  AND `ci_id` = {$other['coin1']}";
//            db_query($conn,$sql2);
            $param = [
                ['field'=>'trans_frost','type'=>'dec','val'=>$pairFirst],
            ];
            $ret = updateUserBalance($marketTradeLog['mt_peer_ui_id'],$other['coin1'],MYACTION,$param,$conn);
            if ($ret < 1){
                trigger_error('updateUserBalance修改余额失败!');
            }

            $befTotal2 = bcadd($valueFinances[0]['amount'],$valueFinances[0]['trans_frost']);
            $aft_B2 = bcsub($valueFinances[0]['trans_frost'],$pairFirst);
            $aftTotal2 = bcadd($valueFinances[0]['amount'],$aft_B2,BCPRECISEDIGITS);
            $userFinanceLog2 = [
                'ui_id'             =>      $marketTradeLog['mt_peer_ui_id'],
                'mi_id'             =>      $marketTradeLog['mi_id'],
                'ci_id'             =>      $other['coin1'],
                'bef_A'             =>      $valueFinances[0]['amount'],
                'bef_B'             =>      $valueFinances[0]['trans_frost'],
                'bef_D'             =>      $befTotal2,
                'num'               =>      $pairFirst,
                'type'              =>      $marketTradeLog['type']==1?2:1,
                'create_time'       =>      $marketTradeLog['create_time'],
                'aft_A'             =>      $valueFinances[0]['amount'],
                'aft_B'             =>      $aft_B2,
                'aft_D'             =>      $aftTotal2,
                'order_no'          =>      $marketTradeLog['peer_order_no'],
            ];

            //币2 $valueFinances[1] // 余额: 加上成交数量 | 减去手续费
//                    $sell_fee = $sell_fee/100;
//                    $valueActual = bcmul($pairSecond,(1-$sell_fee),BCPRECISEDIGITS);
            $valueActual = bcsub($pairSecond,$sell_fee,BCPRECISEDIGITS);
//                    $amount3 = [
//                        'amount'          =>   bcadd($valueFinances[1]['amount'],$valueActual,BCPRECISEDIGITS),
//                        'update_time'     =>   $marketTradeLog['create_time'],
//                    ];
//                    db_update($conn,
//                        'user_finance',
//                        [$marketTradeLog['mt_peer_ui_id'],$other['coin2']/*,$valueFinances[1]['amount']*/],
//                        $amount3,
//                        ['ui_id','ci_id'/*,'amount'*/]);
//            $sql3 = "UPDATE `user_finance`  SET `amount` = `amount`+'{$valueActual}' , `update_time` = {$marketTradeLog['create_time']}  WHERE  `ui_id` = {$marketTradeLog['mt_peer_ui_id']}  AND `ci_id` = {$other['coin2']}";
//            db_query($conn,$sql3);
            $param = [
                ['field'=>'amount','type'=>'inc','val'=>$valueActual],
            ];
            $ret = updateUserBalance($marketTradeLog['mt_peer_ui_id'],$other['coin2'],MYACTION,$param,$conn);
            if ($ret < 1){
                trigger_error('updateUserBalance修改余额失败!');
            }

            /**手续费写入对应账户**/
//                    $feeAccount = bcsub($pairSecond,$valueActual,BCPRECISEDIGITS);
//            $sql100 = "UPDATE `user_finance`  SET `amount` = `amount`+'{$sell_fee}' , `update_time` = {$marketTradeLog['create_time']}  WHERE  `ui_id` = {$feeAccountId}  AND `ci_id` = {$other['coin2']}";
//            db_query($conn,$sql100);
            $param = [
                ['field'=>'amount','type'=>'inc','val'=>$sell_fee],
            ];
            $ret = updateUserBalance($feeAccountId,$other['coin2'],MYACTION,$param,$conn);
            if ($ret < 1){
                trigger_error('updateUserBalance修改余额失败!');
            }

            $befTotal3 = bcadd($valueFinances[1]['amount'],$valueFinances[1]['trans_frost']);
            $aft_A3 = bcadd($valueFinances[1]['amount'],$valueActual);
            $aftTotal3 = bcadd($aft_A3,$valueFinances[1]['trans_frost'],BCPRECISEDIGITS);
            $userFinanceLog3 = [
                'ui_id'             =>      $marketTradeLog['mt_peer_ui_id'],
                'mi_id'             =>      $marketTradeLog['mi_id'],
                'ci_id'             =>      $other['coin2'],
                'bef_A'             =>      $valueFinances[1]['amount'],
                'bef_B'             =>      $valueFinances[1]['trans_frost'],
                'bef_D'             =>      $befTotal3,
                'num'               =>      $valueActual,
                'type'              =>      $marketTradeLog['type']==1?2:1,
                'create_time'       =>      $marketTradeLog['create_time'],
                'aft_A'             =>      $aft_A3,
                'aft_B'             =>      $valueFinances[1]['trans_frost'],
                'aft_D'             =>      $aftTotal3,
                'order_no'          =>      $marketTradeLog['peer_order_no'],
            ];

            //同步余额变更日志
            /*$logs = [
                $userFinanceLog0,
                $userFinanceLog1,
                $userFinanceLog2,
                $userFinanceLog3,
            ];
            unset(
                $userFinanceLog0,
                $userFinanceLog1,
                $userFinanceLog2,
                $userFinanceLog3
            );*/
            $month = date('Y_m',$marketTradeLog['create_time']);
            $year = date('Y',$marketTradeLog['create_time']);
            $table = 'user_finance_log'. $month;

            $res = existTableFinanceLog($conn,$table);
            if ($res !== true){
                writeLog('@数据表:'.$table.'创建失败2!数据:<'.json_encode($data).'>');
                trigger_error('@数据表:'.$table.'创建失败2!数据:<'.json_encode($data).'>');
            }
            db_insert($conn,$table,$userFinanceLog0);
            db_insert($conn,$table,$userFinanceLog1);
            db_insert($conn,$table,$userFinanceLog2);
            db_insert($conn,$table,$userFinanceLog3);
            unset($userFinanceLog0,
                $userFinanceLog1,
                $userFinanceLog2,
                $userFinanceLog3);

            //同步成交记录
            unset($marketTradeLog['microS']);
            $table = 'market_trade_log'. $year. '_'. $marketTradeLog['mi_id'];

            $res = existTableTradeLog($conn,$table);
            if ($res !== true){
                writeLog('@数据表:'.$table.'创建失败3!数据:<'.json_encode($data).'>');
                trigger_error('@数据表:'.$table.'创建失败3!数据:<'.json_encode($data).'>');
            }
//                    Db::table($table)->insert($marketTradeLog);
            db_insert($conn,$table,$marketTradeLog);

            break;
        case 2://主动方:(卖)
            //主动方:
            //币1 $userFinances[0] //冻结: 减去交易数量 $pairFirst
//                    $amount0 = [
//                        'trans_frost'     =>   bcsub($userFinances[0]['trans_frost'],$pairFirst,BCPRECISEDIGITS),
//                        'update_time'     =>   $marketTradeLog['create_time'],
//                    ];
//                    db_update($conn,
//                        'user_finance',
//                        [$marketTradeLog['mt_order_ui_id'],$other['coin1']/*,$userFinances[0]['trans_frost']*/],
//                        $amount0,
//                        ['ui_id','ci_id'/*,'trans_frost'*/]);
            $ret1 = checkFinance($userFinances[0],$pairFirst);
            if ($ret1 === false){
                trigger_error("用户[{$marketTradeLog['mt_order_ui_id']},{$other['coin1']}]-$pairFirst=余额为负");
            }
//            $sql0 = "UPDATE `user_finance`  SET `trans_frost` = `trans_frost`-'{$pairFirst}' , `update_time` = {$marketTradeLog['create_time']}  WHERE  `ui_id` = {$marketTradeLog['mt_order_ui_id']}  AND `ci_id` = {$other['coin1']}";
//            db_query($conn,$sql0);
            $param = [
                ['field'=>'trans_frost','type'=>'dec','val'=>$pairFirst],
            ];
            $ret = updateUserBalance($marketTradeLog['mt_order_ui_id'],$other['coin1'],MYACTION,$param,$conn);
            if ($ret < 1){
                trigger_error('updateUserBalance修改余额失败!');
            }

            $befTotal0 = bcadd($userFinances[0]['amount'],$userFinances[0]['trans_frost']);
            $aft_B0 = bcsub($userFinances[0]['trans_frost'],$pairFirst,BCPRECISEDIGITS);
            $aftTotal0 = bcadd($userFinances[0]['amount'],$aft_B0,BCPRECISEDIGITS);
            $userFinanceLog0 = [
                'ui_id'             =>      $marketTradeLog['mt_order_ui_id'],
                'mi_id'             =>      $marketTradeLog['mi_id'],
                'ci_id'             =>      $other['coin1'],
                'bef_A'             =>      $userFinances[0]['amount'],   //交易前余额
                'bef_B'             =>      $userFinances[0]['trans_frost'],  //交易前冻结
                'bef_D'             =>      $befTotal0,           //交易前总计
                'num'               =>      $pairFirst,           //本次变动数额
                'type'              =>      $marketTradeLog['type'],   //1是买 2是卖
                'create_time'       =>      $marketTradeLog['create_time'],
                'aft_A'             =>      $userFinances[0]['amount'],         //交易后余额
                'aft_B'             =>      $aft_B0,      //交易后冻结
                'aft_D'             =>      $aftTotal0,    //交易后总计
                'order_no'          =>      $marketTradeLog['order_no'],//交易流水号
            ];

            //币2 $userFinances[1] //余额: 加上成交量(成交价*交易数量) | 减去手续费
//                    $pairSecond = bcmul($pairPrice,$pairFirst);
            $pairSecond = $marketTradeLog['amount'];
//                    $sell_fee = $sell_fee/100;
//                    $userActual = bcmul($pairSecond,(1-$sell_fee),BCPRECISEDIGITS);
            $userActual = bcsub($pairSecond,$sell_fee,BCPRECISEDIGITS);
//                    $amount1 = [
//                        'amount'          =>   bcadd($userFinances[1]['amount'],$userActual,BCPRECISEDIGITS),
//                        'update_time'     =>   $marketTradeLog['create_time'],
//                    ];
//                    db_update($conn,
//                        'user_finance',
//                        [$marketTradeLog['mt_order_ui_id'],$other['coin2']/*,$userFinances[1]['amount']*/],
//                        $amount1,
//                        ['ui_id','ci_id'/*,'amount'*/]);
//            $sql1 = "UPDATE `user_finance` SET `amount` = `amount`+'{$userActual}' , `update_time` = {$marketTradeLog['create_time']}  WHERE  `ui_id` = {$marketTradeLog['mt_order_ui_id']}  AND `ci_id` = {$other['coin2']}";
//            db_query($conn,$sql1);
            $param = [
                ['field'=>'amount','type'=>'inc','val'=>$userActual],
            ];
            $ret = updateUserBalance($marketTradeLog['mt_order_ui_id'],$other['coin2'],MYACTION,$param,$conn);
            if ($ret < 1){
                trigger_error('updateUserBalance修改余额失败!');
            }

            /**手续费写入对应账户**/
//                    $feeAccount = bcsub($pairSecond,$userActual,BCPRECISEDIGITS);
//            $sql100 = "UPDATE `user_finance`  SET `amount` = `amount`+'{$sell_fee}' , `update_time` = {$marketTradeLog['create_time']}  WHERE  `ui_id` = {$feeAccountId}  AND `ci_id` = {$other['coin2']}";
//            db_query($conn,$sql100);
            $param = [
                ['field'=>'amount','type'=>'inc','val'=>$sell_fee],
            ];
            $ret = updateUserBalance($feeAccountId,$other['coin2'],MYACTION,$param,$conn);
            if ($ret < 1){
                trigger_error('updateUserBalance修改余额失败!');
            }

            $befTotal1 = bcadd($userFinances[1]['amount'],$userFinances[1]['trans_frost']);
            $aft_A1 = bcadd($userFinances[1]['amount'],$userActual,BCPRECISEDIGITS);
            $aftTotal1 = bcadd($aft_A1,$userFinances[1]['trans_frost'],BCPRECISEDIGITS);
            $userFinanceLog1 = [
                'ui_id'             =>      $marketTradeLog['mt_order_ui_id'],
                'mi_id'             =>      $marketTradeLog['mi_id'],
                'ci_id'             =>      $other['coin2'],
                'bef_A'             =>      $userFinances[1]['amount'],
                'bef_B'             =>      $userFinances[1]['trans_frost'],
                'bef_D'             =>      $befTotal1,
                'num'               =>      $userActual,
                'type'              =>      $marketTradeLog['type'],
                'create_time'       =>      $marketTradeLog['create_time'],
                'aft_A'             =>      $aft_A1,
                'aft_B'             =>      $userFinances[1]['trans_frost'],
                'aft_D'             =>      $aftTotal1,
                'order_no'          =>      $marketTradeLog['order_no'],
            ];

            //主动方 = 被动方
            if ($marketTradeLog['mt_order_ui_id'] == $marketTradeLog['mt_peer_ui_id']){
                $userFinances[0]['trans_frost'] = $aft_B0;
                $valueFinances[0] = $userFinances[0];
                unset($userFinances[0]);
                $userFinances[1]['amount'] = $aft_A1;
                $valueFinances[1] = $userFinances[1];
                unset($userFinances[1]);
            }

            //被动方:
            //币1 $valueFinances[0] //余额: 加上交易数量 | 减去手续费 $pairFirst
//                    $buy_fee = $buy_fee/100;
//                    $valueActual = bcmul($pairFirst,(1-$buy_fee),BCPRECISEDIGITS);
            $valueActual = bcsub($pairFirst,$buy_fee,BCPRECISEDIGITS);
//                    $amount2 = [
//                        'amount'          =>   bcadd($valueFinances[0]['amount'],$valueActual,BCPRECISEDIGITS),
//                        'update_time'     =>   $marketTradeLog['create_time'],
//                    ];
//                    db_update($conn,
//                        'user_finance',
//                        [$marketTradeLog['mt_peer_ui_id'],$other['coin1']/*,$valueFinances[0]['amount']*/],
//                        $amount2,
//                        ['ui_id','ci_id'/*,'amount'*/]);
//            $sql2 = "UPDATE `user_finance`  SET `amount` = `amount`+'{$valueActual}' , `update_time` = {$marketTradeLog['create_time']}  WHERE  `ui_id` = {$marketTradeLog['mt_peer_ui_id']}  AND `ci_id` = {$other['coin1']}";
//            db_query($conn,$sql2);
            $param = [
                ['field'=>'amount','type'=>'inc','val'=>$valueActual],
            ];
            $ret = updateUserBalance($marketTradeLog['mt_peer_ui_id'],$other['coin1'],MYACTION,$param,$conn);
            if ($ret < 1){
                trigger_error('updateUserBalance修改余额失败!');
            }

            /**手续费写入对应账户**/
//                    $feeAccount = bcsub($pairFirst,$valueActual,BCPRECISEDIGITS);
//            $sql99 = "UPDATE `user_finance` SET `amount` = `amount`+'{$buy_fee}' , `update_time` = {$marketTradeLog['create_time']}  WHERE  `ui_id` = {$feeAccountId}  AND `ci_id` = {$other['coin1']}";
//            db_query($conn,$sql99);
            $param = [
                ['field'=>'amount','type'=>'inc','val'=>$buy_fee],
            ];
            $ret = updateUserBalance($feeAccountId,$other['coin1'],MYACTION,$param,$conn);
            if ($ret < 1){
                trigger_error('updateUserBalance修改余额失败!');
            }

            $befTotal2 = bcadd($valueFinances[0]['amount'],$valueFinances[0]['trans_frost']);
            $aft_A2 = bcadd($valueFinances[0]['amount'],$valueActual,BCPRECISEDIGITS);
            $aftTotal2 = bcadd($aft_A2,$valueFinances[0]['trans_frost'],BCPRECISEDIGITS);
            $userFinanceLog2 = [
                'ui_id'             =>      $marketTradeLog['mt_peer_ui_id'],
                'mi_id'             =>      $marketTradeLog['mi_id'],
                'ci_id'             =>      $other['coin1'],
                'bef_A'             =>      $valueFinances[0]['amount'],
                'bef_B'             =>      $valueFinances[0]['trans_frost'],
                'bef_D'             =>      $befTotal2,
                'num'               =>      $valueActual,
                'type'              =>      $marketTradeLog['type']==1?2:1,
                'create_time'       =>      $marketTradeLog['create_time'],
                'aft_A'             =>      $aft_A2,
                'aft_B'             =>      $valueFinances[0]['trans_frost'],
                'aft_D'             =>      $aftTotal2,
                'order_no'          =>      $marketTradeLog['peer_order_no'],
            ];

            //币2 $valueFinances[1] // 冻结: 减去成交量 $pairSecond | 成交价*交易数量;
//                    $amount3 = [
//                        'trans_frost'     =>   bcsub($valueFinances[1]['trans_frost'],$pairSecond,BCPRECISEDIGITS),
//                        'update_time'     =>   $marketTradeLog['create_time'],
//                    ];
//                    db_update($conn,
//                        'user_finance',
//                        [$marketTradeLog['mt_peer_ui_id'],$other['coin2']/*,$valueFinances[1]['amount']*/],
//                        $amount3,
//                        ['ui_id','ci_id'/*,'amount'*/]);
            $ret1 = checkFinance($valueFinances[1],$pairSecond);
            if ($ret1 === false){
                trigger_error("用户[{$marketTradeLog['mt_peer_ui_id']},{$other['coin2']}]-$pairSecond=余额为负");
            }
//            $sql3 = "UPDATE `user_finance`  SET `trans_frost` = `trans_frost`-'{$pairSecond}' , `update_time` = {$marketTradeLog['create_time']}  WHERE  `ui_id` = {$marketTradeLog['mt_peer_ui_id']}  AND `ci_id` = {$other['coin2']}";
//            db_query($conn,$sql3);
            $param = [
                ['field'=>'trans_frost','type'=>'dec','val'=>$pairSecond],
            ];
            $ret = updateUserBalance($marketTradeLog['mt_peer_ui_id'],$other['coin2'],MYACTION,$param,$conn);
            if ($ret < 1){
                trigger_error('updateUserBalance修改余额失败!');
            }

            $befTotal3 = bcadd($valueFinances[1]['amount'],$valueFinances[1]['trans_frost']);
            $aft_B3 = bcsub($valueFinances[1]['trans_frost'],$pairSecond,BCPRECISEDIGITS);
            $aftTotal3 = bcadd($valueFinances[1]['amount'],$aft_B3,BCPRECISEDIGITS);
            $userFinanceLog3 = [
                'ui_id'             =>      $marketTradeLog['mt_peer_ui_id'],
                'mi_id'             =>      $marketTradeLog['mi_id'],
                'ci_id'             =>      $other['coin2'],
                'bef_A'             =>      $valueFinances[1]['amount'],
                'bef_B'             =>      $valueFinances[1]['trans_frost'],
                'bef_D'             =>      $befTotal3,
                'num'               =>      $pairSecond,
                'type'              =>      $marketTradeLog['type']==1?2:1,
                'create_time'       =>      $marketTradeLog['create_time'],
                'aft_A'             =>      $valueFinances[1]['amount'],
                'aft_B'             =>      $aft_B3,
                'aft_D'             =>      $aftTotal3,
                'order_no'          =>      $marketTradeLog['peer_order_no'],
            ];

            //同步余额变更日志
            /*$logs = [
                $userFinanceLog0,
                $userFinanceLog1,
                $userFinanceLog2,
                $userFinanceLog3,
            ];
            unset(
                $userFinanceLog0,
                $userFinanceLog1,
                $userFinanceLog2,
                $userFinanceLog3
            );*/
            $month = date('Y_m',$marketTradeLog['create_time']);
            $year = date('Y',$marketTradeLog['create_time']);
            $table = 'user_finance_log'. $month;

            $res = existTableFinanceLog($conn,$table);
            if ($res !== true){
                writeLog('@数据表:'.$table.'创建失败4!数据:<'.json_encode($data).'>');
                trigger_error('@数据表:'.$table.'创建失败4!数据:<'.json_encode($data).'>');
            }
            db_insert($conn,$table,$userFinanceLog0);
            db_insert($conn,$table,$userFinanceLog1);
            db_insert($conn,$table,$userFinanceLog2);
            db_insert($conn,$table,$userFinanceLog3);
            unset($userFinanceLog0,
                $userFinanceLog1,
                $userFinanceLog2,
                $userFinanceLog3);

            //同步成交记录
            unset($marketTradeLog['microS']);
            $table = 'market_trade_log'. $year. '_'. $marketTradeLog['mi_id'];

            $res = existTableTradeLog($conn,$table);
            if ($res !== true){
                writeLog('@数据表:'.$table.'创建失败5!数据:<'.json_encode($data).'>');
                trigger_error('@数据表:'.$table.'创建失败5!数据:<'.json_encode($data).'>');
            }
            db_insert($conn,$table,$marketTradeLog);

            break;
    }
}

function asyncMysql_mtd($conn, $data){
    try {
        $user_type = 4;
        $sql99 = "SELECT `ui_id` FROM `user_info` WHERE  `user_type` = {$user_type} LIMIT 1";
        $feeAccountId = db_query_array($conn,$sql99);
        $feeAccountId = $feeAccountId[0]['ui_id'];
        db_start_transaction($conn);
        foreach ($data as $value){
            extract($value['data']);
            $other['price'];
            $other['coin1'];
            $other['coin2'];
            $marketTradeF;
            $marketTradeR;
            $marketTradeLog;

            /*$map0 = [
                ['ui_id','=',$marketTradeLog['mt_order_ui_id']],
                ['ci_id','=',$other['coin1']],
            ];
            $map1 = [
                ['ui_id','=',$marketTradeLog['mt_order_ui_id']],
                ['ci_id','=',$other['coin2']],
            ];
            $map2 = [
                ['ui_id','=',$marketTradeLog['mt_peer_ui_id']],
                ['ci_id','=',$other['coin1']],
            ];
            $map3 = [
                ['ui_id','=',$marketTradeLog['mt_peer_ui_id']],
                ['ci_id','=',$other['coin2']],
            ];*/
//        $conn = db_connect($CFG->dbhost,$CFG->dbname,$CFG->dbuser,$CFG->dbpass);
            $sql0 = "SELECT * FROM `user_finance` WHERE ( `ui_id` = '{$marketTradeLog['mt_order_ui_id']}' AND `ci_id` = '{$other['coin1']}' ) OR ( `ui_id` = '{$marketTradeLog['mt_order_ui_id']}' AND `ci_id` = '{$other['coin2']}' ) ORDER BY `ci_id` ASC";
            $sql1 = "SELECT * FROM `user_finance` WHERE ( `ui_id` = '{$marketTradeLog['mt_peer_ui_id']}' AND `ci_id` = '{$other['coin1']}' ) OR ( `ui_id` = '{$marketTradeLog['mt_peer_ui_id']}' AND `ci_id` = '{$other['coin2']}' ) ORDER BY `ci_id` ASC";
            $userFinances = db_query_array($conn,$sql0); //返回结果集数组
            $valueFinances = db_query_array($conn,$sql1); //返回结果集数组
            propertyChange(
                $conn, $data,
                $marketTradeF, $marketTradeR,
                $marketTradeLog, $other,
                $feeAccountId,
                $userFinances, $valueFinances
            );
        }
        db_commit($conn);
    } catch (Exception $e) {
        db_rollback($conn);
        foreach ($data as $value) {
            extract($value['data']);
            $marketTradeF;
            $marketTradeR;
            $marketTradeLog;
            $marketTradeF['status'] = 9;
            $marketTradeR['status'] = 9;
            db_insert($conn, 'exception_trade', $marketTradeF);
            db_insert($conn, 'exception_trade', $marketTradeR);
            db_insert($conn, 'exception_trade_log', $marketTradeLog);
        }
        $error = myGetTrace($e);
        writeLog("@unknown-error | 挂单数据写入数据库异常3，error:{$error}。");
        return 'Error';
    }
}

function writeLog($str){
    $date = date('Ymd');
    $filepath = dirname(dirname(dirname(dirname(__FILE__))))."/runtime/log/trade/error/error_{$date}.log";
    $mkpath = dirname(dirname(dirname(dirname(__FILE__))))."/runtime/log/trade/error/";
    if(!is_dir($mkpath)){
        @mkdir($mkpath,0777,true);
    }
    $str = "[".date("Y-m-d H:i:s")."]".$str;
    $str .= PHP_EOL;
    file_put_contents($filepath,$str,FILE_APPEND);
}

function checkFinance($userFinance,$pair)
{
    $trans_frost = $userFinance['trans_frost'];
    if (bcsub($trans_frost,$pair,BCPRECISEDIGITS) < 0){
        return false;
    }
    return true;
}