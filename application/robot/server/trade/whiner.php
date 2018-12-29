<?php
include_once dirname(__FILE__)."/cfg.php";
include_once dirname(__FILE__)."/dblib.php";
include_once dirname(__FILE__)."/DB.class.php";
include_once dirname(__FILE__)."/SRedis.php";
include_once dirname(__FILE__)."/../../../../common/common.php";

define('BCPRECISEDIGITS', 13); //小数点精确位数
define('MYACTION', 'robotrans');

//$redis = new \Redis();
//
//$redis->connect($CFG->redis_host, $CFG->redis_port);
//if($CFG->redis_pwd != ""){
//    $redis->auth($CFG->redis_pwd);//U#rNFRkk3vuCKcZ5
//}

//$conn = db_connect($CFG->dbhost, $CFG->dbname, $CFG->dbuser, $CFG->dbpass);

function freeze($redis, $conn, $transactionPair, $type, $price, $decimal, $timeS, $order_no, $userID){
    switch ($type) {
        case 1:
            //定义一个默认成交价
            $default = $transactionPair['last_price'] ?? 1000;
            //限价买入最低为成交价下浮$transactionPair['price_buy_min']/100
            $buyLimitPrice = bcsub(1, $transactionPair['price_buy_min'] / 100, $transactionPair['price_bit']);

            //>>2.1.限价买,输入的价格$price不能低于最后成交价的$buyLimitPrice倍
            //获取最后买单的价格
            $lastRecord = json_decode($redis->get('str_last_record_market_' . $transactionPair['mi_id']), true)['price'] ?? $default;
            $buyLimitPrice = bcmul($lastRecord, $buyLimitPrice, $transactionPair['price_bit']);
            if ($price < $buyLimitPrice) {
                return '购买价格$price不能低于:' . $buyLimitPrice;
            }

            $buyLimitPrice = bcadd(1, $transactionPair['price_buy_max'] / 100, $transactionPair['price_bit']);
            $buyLimitPrice = bcmul($lastRecord, $buyLimitPrice, $transactionPair['price_bit']);
            if ($price > $buyLimitPrice) {
                return '购买价格$price不能高于:' . $buyLimitPrice;
            }

            //买入 币1的数量$decimal 乘以 价格$decimal 等于 所需 币2的数量
            $number = bcmul($decimal, $price);

            //减低效率,必须查库,防止挂单前 用户提取余额
            $sql = "SELECT * FROM `user_finance` WHERE `ui_id` = '{$userID}' AND `ci_id` = '{$transactionPair['ci_id_second']}' AND `status` = 1 LIMIT 1";
            $amount = db_query_array($conn,$sql)[0];
            if (empty($amount)){
                return '用户对应币种信息未找到或不可用';
            }
            //买方 冻结后余额 等于 币2 减去 所需数量
            $aft = bcsub($amount['amount'], $number);
            if ($aft < 0) {
                return '您的余额不足!!用户ID'.$userID;
            }

            //挂单冻结 币2 之前冻结数量 加上 所需数量(即冻结数量)
            $transFrost = bcadd($amount['trans_frost'], $number);
            //冻结前后总计
            $aftTotal = bcadd($amount['amount'], $amount['trans_frost']);

//            $timeS = time();
            //用户财产记录
//            $userFinance = [
//                'ui_id' => $userID,
//                'ci_id' => $transactionPair['ci_id_second'],
//                'amount' => $aft,
//                'trans_frost' => $transFrost,
//                'update_time' => $timeS,
//            ];
            //用户财产变更记录
            $userFinanceLog = [
                'ui_id'             =>   $userID,
                'mi_id'             =>   $transactionPair['mi_id'],
                'ci_id'             =>   $transactionPair['ci_id_second'],
                'bef_A'             =>   $amount['amount'],   //交易前余额
                'bef_B'             =>   $amount['trans_frost'],  //交易前冻结
                'bef_D'             =>   $aftTotal,           //交易前总计
                'num'               =>   $number,           //本次变动数额
                'type'              =>   $type,   //1是买 2是卖
                'create_time'       =>   $timeS,
                'aft_A'             =>   $aft,             //交易后余额
                'aft_B'             =>   $transFrost,      //交易后冻结
                'aft_D'             =>   $aftTotal,    //交易后总计
                'order_no'          =>   $order_no, //交易流水号
            ];

            db_start_transaction($conn);
            try {
//                $sqlE = "UPDATE `user_finance` SET `amount` = '{$aft}' ,`trans_frost` = '{$transFrost}' , `update_time` = {$timeS} WHERE `ui_id` = '{$userID}' AND `ci_id` = '{$transactionPair['ci_id_second']}' AND `amount` = '{$amount['amount']}' AND `trans_frost` = '{$amount['trans_frost']}'";
//                db_query($conn,$sqlE);
                $param = [
                    ['field'=>'amount','type'=>'dec','val'=>$number],
                    ['field'=>'trans_frost','type'=>'inc','val'=>$number],
                ];
                $ret = updateUserBalance($userID,$transactionPair['ci_id_second'],MYACTION,$param,$conn);
                if ($ret < 1){
                    trigger_error('updateUserBalance修改余额失败!');
                }

                $month = date('Y_m', $timeS);
                $table = 'user_finance_log' . $month;

                $res = existTableFinanceLog($conn, $table);
                if ($res !== true) {
                    writeLog('数据表:' . $table . '创建失败!数据:<' . json_encode($userFinanceLog) . '>');
                    trigger_error('数据表:' . $table . '创建失败!数据:<' . json_encode($userFinanceLog) . '>', 10006);
                }
                db_insert($conn, $table, $userFinanceLog);
                // 提交事务
                db_commit($conn);
            } catch (\Exception $e) {
                // 回滚事务
                db_rollback($conn);
                return '冻结数据写入数据库时失败!!';
            }
            unset($userFinance);
            unset($userFinanceLog);
            break;
        case 2:
//定义一个默认成交价
            $default = $transactionPair['last_price']??1000;
            //限价卖出最高为成交价上浮$transactionPair['price_sell_max']/100

            $sellLimitPrice = bcadd(1,$transactionPair['price_sell_max']/100,$transactionPair['price_bit']);
            //>>2.1.限价卖,输入的价格$price不能高于最后成交价的$sellLimitPrice倍
            //获取最后买单的价格
            $lastRecord = json_decode($redis->get('str_last_record_market_'.$transactionPair['mi_id']),true)['price']??$default;
            $sellLimitPrice = bcmul($lastRecord,$sellLimitPrice,$transactionPair['price_bit']);
            if ($price > $sellLimitPrice){
                return '卖出价格$price不能高于:'.$sellLimitPrice;
            }

            $sellLimitPrice = bcsub(1,$transactionPair['price_sell_min']/100,$transactionPair['price_bit']);
            $sellLimitPrice = bcmul($lastRecord,$sellLimitPrice,$transactionPair['price_bit']);
            if ($price < $sellLimitPrice){
                return '卖出价格$price不能低于:'.$sellLimitPrice;
            }

            //卖出数量为 $decimal;
            //>>2.1.1.$decimal卖出数量不能高于自己的账户余额
            $sql = "SELECT * FROM `user_finance` WHERE `ui_id` = '{$userID}' AND `ci_id` = '{$transactionPair['ci_id_first']}' AND `status` = 1 LIMIT 1";
            $amount = db_query_array($conn,$sql)[0];
            if (empty($amount)){
                return '用户对应币种信息未找到或不可用-';
            }
            //卖方冻结后余额 等于 币1 减去 所需数量
            $aft = bcsub($amount['amount'],$decimal);
            if ($aft < 0){
                return '您的余额不足-!!用户ID'.$userID;
            }

            //挂单冻结 币1 之前冻结数量 加上 所需数量(即冻结数量)
            $transFrost = bcadd($amount['trans_frost'],$decimal);
            //冻结前后总计
            $aftTotal = bcadd($amount['amount'],$amount['trans_frost']);

//            $timeS = time();
            //用户财产记录
//            $userFinance = [
//                'ui_id'               =>      $userID,
//                'ci_id'               =>      $transactionPair['ci_id_first'],
//                'amount'              =>      $aft,
//                'trans_frost'         =>      $transFrost,
//                'update_time'         =>      $timeS,
//            ];
            //用户财产变更记录
            $userFinanceLog = [
                'ui_id'              =>      $userID,
                'mi_id'              =>      $transactionPair['mi_id'],
                'ci_id'              =>      $transactionPair['ci_id_first'],
                'bef_A'              =>      $amount['amount'],   //交易前余额
                'bef_B'              =>      $amount['trans_frost'],  //交易前冻结
                'bef_D'              =>      $aftTotal,           //交易前总计
                'num'                =>      $decimal,           //本次变动数额
                'type'               =>      $type,   //1是买 2是卖
                'create_time'        =>      $timeS,
                'aft_A'              =>      $aft,             //交易后余额
                'aft_B'              =>      $transFrost,      //交易后冻结
                'aft_D'              =>      $aftTotal,    //交易后总计
                'order_no'           =>      $order_no, //交易流水号
            ];

            db_start_transaction($conn);
            try {
//                $sqlE = "UPDATE `user_finance` SET `amount` = '{$aft}' ,`trans_frost` = '{$transFrost}' , `update_time` = {$timeS} WHERE `ui_id` = '{$userID}' AND `ci_id` = '{$transactionPair['ci_id_first']}' AND `amount` = '{$amount['amount']}' AND `trans_frost` = '{$amount['trans_frost']}'";
//                db_query($conn,$sqlE);
                $param = [
                    ['field'=>'amount','type'=>'dec','val'=>$decimal],
                    ['field'=>'trans_frost','type'=>'inc','val'=>$decimal],
                ];
                $ret = updateUserBalance($userID,$transactionPair['ci_id_first'],MYACTION,$param,$conn);
                if ($ret < 1){
                    trigger_error('updateUserBalance修改余额失败!');
                }

                $month = date('Y_m',$timeS);
                $table = 'user_finance_log'. $month;

                $res = existTableFinanceLog($conn, $table);
                if ($res !== true){
                    writeLog('数据表:'.$table.'创建失败!数据:<'.json_encode($userFinanceLog).'>');
                    trigger_error('数据表:'.$table.'创建失败!数据:<'.json_encode($userFinanceLog).'>', 10006);
                }
                db_insert($conn, $table, $userFinanceLog);
                // 提交事务
                db_commit($conn);
            } catch (\Exception $e) {
                // 回滚事务
                db_rollback($conn);
                return '冻结数据写入数据库时失败-!!';
            }
            unset($userFinance);
            unset($userFinanceLog);
            break;
        default:
            return '交易类型错误!';
            break;
    }
    return true;
}

function robotTrade($data1, $data2){
    try{
        if ($data1['type'] == $data2['type']){
            return '2组数据的买卖类型不能相同!';
        }
        if ($data1['decimal'] != $data2['decimal']){
            return '2组数据的买卖数量必须相同!';
        }
        $redis = SRedis::instance();
        $CFG = new cfgObject();
        $conn = DB::getInstance($CFG);
        //>>1.判断用户余额
        $transactionPair = json_decode($redis->hGet('hash_market_info',$data1['mi_id']),true);
        if (empty($transactionPair)){
//        $transactionPair = Db::table('market_info')->where('mi_id', $data1['mi_id'])->find();
            $sqlE = "SELECT * FROM `market_info` WHERE `mi_id` = '{$data1['mi_id']}' LIMIT 1";
            $transactionPair = db_query_array($conn,$sqlE)[0];
            if (empty($transactionPair)){
                return '数据库交易市场信息未查到!';
            }
            $redis->hSet('hash_market_info',$data1['mi_id'],json_encode($transactionPair));
        }
        /**根据type查询余额**/
        bcscale(BCPRECISEDIGITS);

        if ($data1['type'] == 1 && $data2['type'] == 2){
            $userID = $data1['ui_id'];
            $userID2 = $data2['ui_id'];
            $ret = freeze($redis, $conn, $transactionPair, $data1['type'], $data1['price'], $data1['decimal'], $data1['create_time'], $data1['order_no'], $userID);
            $ret1 = freeze($redis, $conn, $transactionPair, $data2['type'], $data2['price'], $data2['decimal'], $data2['create_time'], $data2['order_no'], $userID2);
        }elseif ($data1['type'] == 2 && $data2['type'] == 1){
            $userID2 = $data1['ui_id'];
            $userID = $data2['ui_id'];
            $ret = freeze($redis, $conn, $transactionPair, $data1['type'], $data1['price'], $data1['decimal'], $data1['create_time'], $data1['order_no'], $userID2);
            $ret1 = freeze($redis, $conn, $transactionPair, $data2['type'], $data2['price'], $data2['decimal'], $data2['create_time'], $data2['order_no'], $userID);
        }else{
            return '挂单type有问题!';
        }
        if ($ret !== true){
            return $ret;
        }
        if ($ret1 !== true){
            return $ret1;
        }

        //>>2.查询手续费
        //查询币种手续费(买:买币1得币1,手续费显示币1的)
        $coinInfo = json_decode($redis->hGet('hash_data_coinFee',$transactionPair['ci_id_first']),true);
        if (empty($coinInfo)){
            $sql = "SELECT `show_fee`,`fee` FROM `coin_info` WHERE `ci_id` = '{$transactionPair['ci_id_first']}' AND `status` = 1 LIMIT 1";
            $coinInfo = db_query_array($conn,$sql)[0];
            if (empty($coinInfo)){
                return '数据库币1手续费信息未查到!';
            }
            $redis->hSet('hash_data_coinFee',$transactionPair['ci_id_first'],json_encode($coinInfo));
        }
        $userGroupFee = json_decode($redis->hGet('hash_data_userGroupFee',$userID),true);
        if (empty($userGroupFee)) {
            $sql = "SELECT `gi`.`fee` FROM `user_group` `ug` LEFT JOIN `group_info` `gi` ON `ug`.`gi_id`=`gi`.`gi_id` WHERE `ug`.`ui_id` = '{$userID}' AND `gi`.`status` = '1'";
            $userGroupFee = db_query_array($conn,$sql)[0];
            if (empty($userGroupFee)){
                return '数据库['.$userID.']用户组手续费信息未查到!';
            }
            $redis->hSet('hash_data_userGroupFee',$userID,json_encode($userGroupFee));
        }
        $buy_fee = min(array_merge((array)$userGroupFee,(array)$coinInfo['fee'],(array)$transactionPair['fee']));

        //币种手续费(卖:卖币1得币2,显示币2的手续费)
        $coinInfo = json_decode($redis->hGet('hash_data_coinFee',$transactionPair['ci_id_second']),true);
        if (empty($coinInfo)){
            $sql = "SELECT `show_fee`,`fee` FROM `coin_info` WHERE `ci_id` = '{$transactionPair['ci_id_second']}' AND `status` = 1 LIMIT 1";
            $coinInfo = db_query_array($conn,$sql)[0];
            if (empty($coinInfo)){
                return '数据库币2手续费信息未查到!';
            }
            $redis->hSet('hash_data_coinFee',$transactionPair['ci_id_second'],json_encode($coinInfo));
        }
        $userGroupFee = json_decode($redis->hGet('hash_data_userGroupFee',$userID2),true);
        if (empty($userGroupFee)) {
            $sql = "SELECT `gi`.`fee` FROM `user_group` `ug` LEFT JOIN `group_info` `gi` ON `ug`.`gi_id`=`gi`.`gi_id` WHERE `ug`.`ui_id` = '{$userID2}' AND `gi`.`status` = '1'";
            $userGroupFee = db_query_array($conn,$sql)[0];
            if (empty($userGroupFee)){
                return '数据库['.$userID2.']用户组手续费信息未查到!';
            }
            $redis->hSet('hash_data_userGroupFee',$userID2,json_encode($userGroupFee));
        }
        $sell_fee = min(array_merge((array)$userGroupFee,(array)$coinInfo['fee'],(array)$transactionPair['fee']));

        if ($data1['type'] == 1 && $data2['type'] == 2){
            $data1['fee'] = $buy_fee;
            $data2['fee'] = $sell_fee;
        }elseif ($data1['type'] == 2 && $data2['type'] == 1){
            $data2['fee'] = $buy_fee;
            $data1['fee'] = $sell_fee;
        }

        //>>3.把data1写入Redis,显示挂单
        $key = 'hash_market_'.$data1['mi_id'];
        $hField = $data1['order_no'];
        if ($data1['type'] == 1){
            $redis->hSet($key .'_buy_j',$hField,json_encode($data1));
        }elseif ($data1['type'] == 2){
            $redis->hSet($key .'_sell_j',$hField,json_encode($data1));
        }

        //买卖双方手续费
        $feeAccountB = bcmul($data1['decimal'],$buy_fee/100,BCPRECISEDIGITS);
        $pairSecond = bcmul($data1['price'], $data1['decimal'], BCPRECISEDIGITS);
        $feeAccountS = bcmul($pairSecond,$sell_fee/100,BCPRECISEDIGITS);
        //>>4.生成成交数据,调用marketTradeLog
        $recordOfTransactionData = [
            'mt_order_ui_id'        =>    $data2['ui_id'],
            'mt_peer_ui_id'         =>    $data1['ui_id'],
            'type'                  =>    $data2['type'],
            'mi_id'                 =>    $data2['mi_id'],
            'price'                 =>    $data1['price'],
            'decimal'               =>    $data1['decimal'],
            'amount'                =>    $pairSecond,
            'buy_fee'               =>    $feeAccountB,   //手续费暂定为0
            'sell_fee'              =>    $feeAccountS,
            'create_time'           =>    time(),
            'microS'                =>    $data2['microS'],
            'order_no'              =>    $data2['order_no'],//交易流水号
            'peer_order_no'         =>    $data1['order_no'],//对手方流水号
        ];
        $data1['decimal'] = 0;
        $data1['status'] = 2;

        $data2['decimal'] = 0;
        $data2['status'] = 2;
        $restingOrderData = [
            'type'      =>  'robot',
            'data'      =>  [
                'marketTradeLog'    =>   $recordOfTransactionData,
                'marketTradeF'      =>   $data1,
                'marketTradeR'      =>   $data2,
                'other'             =>   [
                    'price'=>$recordOfTransactionData['price'],
                    'coin1'=>$transactionPair['ci_id_first'],
                    'coin2'=>$transactionPair['ci_id_second'],
                ],
            ],
        ];

        $r = marketTradeLog($conn,$restingOrderData['data']);
        if ($data1['type'] == 1){
            $redis->hDel($key .'_buy_j',$hField);
        }elseif ($data1['type'] == 2){
            $redis->hDel($key .'_sell_j',$hField);
        }
        if ($r === false){
            return false;
        }
        $record = json_encode($recordOfTransactionData);
        $redis->set('str_last_record_market_'.$recordOfTransactionData['mi_id'], $record);
        $redis->publish('ghm',$record);
        return true;
    } catch (Exception $e) {
        $redis = SRedis::instance();
        $key = 'hash_market_'.$data1['mi_id'];
        $hField = $data1['order_no'];
        if ($data1['type'] == 1){
            $redis->hDel($key .'_buy_j',$hField);
        }elseif ($data1['type'] == 2){
            $redis->hDel($key .'_sell_j',$hField);
        }
        return '['.json_encode($e->getCode()).']'.$e->getMessage().'['.json_encode($e->getTrace()).':'.$e->getLine().']';
    }
}

function marketTrade($conn, $data, $timeS){
    try {
        $year = date('Y', $timeS);
        $table = 'market_trade' . $year . '_' . $data['mi_id'];

        $res = existTableTrade($conn, $table);
        if ($res !== true) {
            writeLog('数据表:'.$table.'创建失败1!数据:<' . json_encode($data) . '>!');
            return false;
        }

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
        $error = '['.json_encode($e->getCode()).']'.$e->getMessage().'['.json_encode($e->getTrace()).':'.$e->getLine().']';
        writeLog("unknown-error | 挂单数据写入数据库异常1，error:{$error}。");
        return false;
    }
}

function marketTradeLog($conn, $data){

    try {
        extract($data);
        $other['price'];
        $other['coin1'];
        $other['coin2'];
        $marketTradeF;
        $marketTradeR;
        $marketTradeLog;

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
            return true;
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
        trigger_error('抛出异常用于删除机器人挂单数据');
        return false;
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
    $ret = marketTrade($conn,$marketTradeF,$marketTradeF['create_time']);
    if ($ret == false){
        trigger_error('@撮合交易后,挂单完成的数据1:{'.json_encode($marketTradeF).'},写入数据库时出错!');
    }
    $ret = marketTrade($conn,$marketTradeR,$marketTradeR['create_time']);
    if ($ret == false){
        trigger_error('@撮合交易后,挂单完成的数据2:{'.json_encode($marketTradeR).'},写入数据库时出错!');
    }
    switch ($marketTradeLog['type']){
        case 1://主动方:(买)
            //主动方:
            //币1 $userFinances[0] //余额: 加上交易数量 | 减去手续费 $userActual
            $userActual = bcsub($pairFirst,$buy_fee,BCPRECISEDIGITS);
//            $sql0 = "UPDATE `user_finance`  SET `amount` = `amount`+'{$userActual}' , `update_time` = {$marketTradeLog['create_time']}  WHERE  `ui_id` = {$marketTradeLog['mt_order_ui_id']}  AND `ci_id` = {$other['coin1']}";
//            db_query($conn,$sql0);
            $param = [
                ['field'=>'amount','type'=>'inc','val'=>$userActual],
            ];
            $ret = updateUserBalance($marketTradeLog['mt_order_ui_id'],$other['coin1'],MYACTION,$param,$conn);
            if ($ret < 1){
                trigger_error('updateUserBalance修改余额失败!');
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

            $valueActual = bcsub($pairSecond,$sell_fee,BCPRECISEDIGITS);
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
            $valueActual = bcsub($pairFirst,$buy_fee,BCPRECISEDIGITS);
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
//            $sql99 = "UPDATE `user_finance`  SET `amount` = `amount`+'{$buy_fee}' , `update_time` = {$marketTradeLog['create_time']}  WHERE  `ui_id` = {$feeAccountId}  AND `ci_id` = {$other['coin1']}";
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
          KEY `FK_Reference_38` (`mi_id`) USING BTREE
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

function writeLog($str){
    $date = date('Ymd');
    $filepath = dirname(dirname(dirname(dirname(dirname(__FILE__)))))."/runtime/log/robot/error/error_{$date}.log";
    $mkpath = dirname(dirname(dirname(dirname(dirname(__FILE__)))))."/runtime/log/robot/error/";
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