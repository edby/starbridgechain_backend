<?php

namespace app\home\controller;

use app\home\model\MarketInfoModel;
use redis\Redis;
use think\Controller;
use think\Db;
use think\facade\Log;
use think\Request;

class Synchro extends Controller
{
    const BCPRECISEDIGITS = 13; //小数点精确位数

    /**
     * 创建挂单表-(market_trade.date('Y').$market)!!
     *
     * @param $table     : 传入表名
     */
    public static function existTableTrade($table)
    {
        //>>判断表存不存在
//        $table = 'market_trade'. date('Y'). $transactionPair['mi_id'];
        $sql = "show tables like '{$table}'";
        $tableName = Db::execute($sql);
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
        $result = Db::execute($sql);
        if ($result == 0){ //数据库创建成功返回0
            return true;
        }else{
            $sql = "show tables like '{$table}'";
            $tableName = Db::execute($sql);
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
    public static function existTableFinanceLog($table)
    {
        $sql = "show tables like '{$table}'";
        $tableName = Db::execute($sql);
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

        $result = Db::execute($sql);
        if ($result == 0){
            return true;
        }else{
            $sql = "show tables like '{$table}'";
            $tableName = Db::execute($sql);
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
    public static function existTableTradeLog($table)
    {
        $sql = "show tables like '{$table}'";
        $tableName = Db::execute($sql);
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

        $result = Db::execute($sql);
        if ($result == 0){
            return true;
        }else{
            $sql = "show tables like '{$table}'";
            $tableName = Db::execute($sql);
            if ($tableName != 0){
                return true;
            }
            return false;
        }
    }



    /**
     * 数据同步!读取Redis的撮合数据 存储到 MySQL
     */
//    public function dataPerMinute()
//    {
//        $bef_five_m = time()-60*1;  //1分钟同步一次数据(Redis到MySQL)!!
//        $year = date('Y');
//        $month = date('Y_m');
//        $aMinuteAgo_y = date('Y',$bef_five_m);
//        $aMinuteAgo_m = date('Y_m',$bef_five_m);
//
//        $redis = Redis::instance();
//        //判断要读那些Redis数据,可能出现的时间节点问题
//        $marketInfo = MarketInfoModel::column('mi_id');
//        if ($year != $aMinuteAgo_y){
//            $this->execSync($marketInfo,$aMinuteAgo_y);
//        }
//        if ($month != $aMinuteAgo_m){
//            $userFinanceLog_b = $redis->lRange('list_data_userFinanceLog'.$aMinuteAgo_m,0,-1);
//            $table_b = 'user_finance_log'. $aMinuteAgo_m;//date('Y-m');
//            $res = $this->existTableFinanceLog($table_b);
//            if ($res !== true)
//                echo '数据表创建失败';
//
////        var_dump($res);die('说明表存在!或者创建成功!!');
//            if ($userFinanceLog_b) {
//                $r = $this->dataInsert($table_b,$userFinanceLog_b);
//
//                if ($r) {
//                    $redis->lTrim('list_data_userFinanceLog' . $month, $r, -1);
//                }
//            }
//        }
//
//        //同步第一个表-用户余额变更日志表  'user_finance_log'. $month
//        $userFinanceLog = $redis->lRange('list_data_userFinanceLog'.$month,0,-1);
//
////        var_dump(json_decode($userFinanceLog[0],true));die('');
//        $table = 'user_finance_log'. $month;//date('Y-m');
//        $res = $this->existTableFinanceLog($table);
//        if ($res !== true)
//            echo '数据表创建失败';
//
////        var_dump($res);die('说明表存在!或者创建成功!!');
//        if ($userFinanceLog){
//            $r = $this->dataInsert($table,$userFinanceLog);
//
//            if ($r){
//                $redis->lTrim('list_data_userFinanceLog'.$month,$r,-1);
//            }
//
//            unset($userFinanceLog);
//        }
//
//        //同步第二个表-用户余额表
//        $userFinanceS = $redis->lRange('list_data_userFinance_keys',0,-1);
//        if ($userFinanceS){
//            $sum = count($userFinanceS);
//            $userFinanceS = array_unique($userFinanceS);
//            $userFinanceData = $redis->hMGet('hash_data_userFinance',$userFinanceS);  //关联数组,值为json;
//            $re = $this->userFinanceUpdate($userFinanceData);
//
//            if ($re == $sum){
//                $redis->lTrim('list_data_userFinance_keys',$sum,-1);
//            }elseif ($re < $sum){
//                $redis->lTrim('list_data_userFinance_keys',$re,-1);
//            }
//
//            unset($userFinanceS);
//        }
//
//        $this->execSync($marketInfo,$year);
////        foreach ($marketInfo as $val){
////
////            //同步第二个表-交易记录表!
////            $listD =  'list_market_'.$val. '_dealRecord'.$year;
////
////            $dealRecord = $redis->lRange($listD,0,-1);//索引数组,值为json;
////            $table = 'market_trade_log'. $year. '_'. $val;
////
////            $res = $this->existTableTradeLog($table);
////            if ($res !== true)
////                echo '数据表创建失败';
////
////            if ($dealRecord){
////                $r = $this->dataInsert($table,$dealRecord);
////
////                if ($r){
////                    $redis->lTrim($listD,$r,-1);
////                }
////            }
////
////
////            //同步第三个表-用户挂单数据表(包括 完成交易的挂单,成交部分的挂单,第一次挂单的数据如何同步?)
////            $table = 'market_trade'. $year. '_'. $val;
////
////            $res = $this->existTableTrade($table);
////            if ($res !== true)
////                echo '数据表创建失败';
////
////
////            //>>1.判断挂单相关的数据
////            $finish_keys = $redis->lRange('list_finish_keys'. $val,0,-1);
////
////            $sell_keys1 = $redis->lRange('list_sell_keys_edit'. $val,0,-1);
////            $sell_count1 = count($sell_keys1);
////            $buy_keys1 = $redis->lRange('list_buy_keys_edit'. $val,0,-1);
////            $buy_count1 = count($buy_keys1);
////            //>>2.成交了部分的挂单(即修改的)
////            /**还需验证数据的类型**///关联数组,值为json;
////            //添加的数据
////            $sell_keys2 = $redis->lRange('list_sell_keys'. $val,0,-1);
////            $sell_count2 = count($sell_keys2);
////            if ($sell_keys2){
////                $sell_keys = array_diff($sell_keys2,$sell_keys1,$finish_keys);
////                if ($sell_keys){
////                    $hashSellD = $redis->hMGet('hash_market_'.$val.'_sell',$sell_keys);  //关联数组,值为json;
////                    $r = $this->dataInsert($table,$hashSellD);
////
////                    if ($r){
////                        $redis->lTrim('list_sell_keys'. $val,$sell_count2,-1);
////                    }
////                }
////                //修改的数据
////                $sell_keys = array_diff($sell_keys1,$finish_keys);
////                if ($sell_keys){
////                    $hashSellD = $redis->hMGet('hash_market_'.$val.'_sell',$sell_keys);  //关联数组,值为json;
////                    $r = $this->dataUpdate($table,$hashSellD);
////
////                    if ($r){
////                        $redis->lTrim('list_sell_keys_edit'. $val,$sell_count1,-1);
////                    }
////                }
////            }
////
////            $buy_keys2 = $redis->lRange('list_buy_keys'. $val,0,-1);
////            $buy_count2 = count($buy_keys2);
////            if ($buy_keys2){
////                $buy_keys = array_diff($buy_keys2,$buy_keys1,$finish_keys);
////                if ($buy_keys){
////                    $hashBuyD = $redis->hMGet('hash_market_'.$val.'_buy',$buy_keys);  //关联数组,值为json;
////                    $r = $this->dataInsert($table,$hashBuyD);
////
////                    if ($r){
////                        $redis->lTrim('list_buy_keys'. $val,$buy_count2,-1);
////                    }
////                }
////                //修改的数据
////                $buy_keys = array_diff($buy_keys1,$finish_keys);
////                if ($buy_keys){
////                    $hashBuyD = $redis->hMGet('hash_market_'.$val.'_buy',$buy_keys);  //关联数组,值为json;
////                    $r = $this->dataUpdate($table,$hashBuyD);
////
////                    if ($r){
////                        $redis->lTrim('list_buy_keys_edit'. $val,$buy_count1,-1);
////                    }
////                }
////            }
////
////            //完成交易的挂单数据
////            if ($finish_keys){
////                $finish_count = count($finish_keys);
////                $tradeFinishD = $redis->lRange('list_market_'.$val.'_finish',0,$finish_count-1);
////                if ($tradeFinishD){
////                    $r = $this->dataUpdate($table,$tradeFinishD);
////
////                    if ($r){
////                        $redis->multi()
////                            ->lTrim('list_market_'.$val.'_finish',$r,-1)
////                            ->lTrim('list_finish_keys'. $val,$finish_count,-1)
////                            ->exec();
////                    }
////                }
////            }
////
////        }/**循环 交易市场表 结束!**/
//
//        echo '同步完成!';
//
//    }

    /**
     * 执行同步数据到数据库
     * @param $marketInfo
     * @param $aMinuteAgo_y
     */
//    private function execSync($marketInfo, $year)
//    {
//        $redis = Redis::instance();
//        foreach ($marketInfo as $val){
//
//            //同步第三个表-交易记录表!
//            $listD =  'list_market_'.$val. '_dealRecord'.$year;
//
//            $dealRecord = $redis->lRange($listD,0,-1);//索引数组,值为json;
//            $table = 'market_trade_log'. $year. '_'. $val;
//
//            $res = $this->existTableTradeLog($table);
//            if ($res !== true)
//                echo '数据表创建失败';
//
//            if ($dealRecord){
//                $r = $this->dataInsert($table,$dealRecord);
//
//                if ($r){
//                    $redis->lTrim($listD,$r,-1);
//                }
//
//                unset($dealRecord);
//            }
//
//
//            //同步第四个表-用户挂单数据表(包括 完成交易的挂单,成交部分的挂单,第一次挂单的数据如何同步?)
//            $table = 'market_trade'. $year. '_'. $val;
//
//            $res = $this->existTableTrade($table);
//            if ($res !== true)
//                echo '数据表创建失败';
//
//
//            //>>1.判断挂单相关的数据
//            $finish_keys = $redis->lRange('list_finish_keys'. $val,0,-1);
//
//            $sell_keys1 = $redis->lRange('list_sell_keys_edit'. $val,0,-1);
//            $sell_count1 = count($sell_keys1);
//            $buy_keys1 = $redis->lRange('list_buy_keys_edit'. $val,0,-1);
//            $buy_count1 = count($buy_keys1);
//            //>>2.成交了部分的挂单(即修改的)
//            /**还需验证数据的类型**///关联数组,值为json;
//            //添加的数据
//            $sell_keys2 = $redis->lRange('list_sell_keys'. $val,0,-1);
//            $sell_count2 = count($sell_keys2);
//            if ($sell_keys2){
//                $sell_keys = array_diff($sell_keys2,$sell_keys1,$finish_keys);
//                if ($sell_keys){
//                    $hashSellD = $redis->hMGet('hash_market_'.$val.'_sell',$sell_keys);  //关联数组,值为json;
//                    $r = $this->dataInsert($table,$hashSellD);
//
//                    if ($r){
//                        $redis->lTrim('list_sell_keys'. $val,$sell_count2,-1);
//                    }
//                }
//                //修改的数据
//                $sell_keys = array_diff($sell_keys1,$finish_keys);
//                if ($sell_keys){
//                    $hashSellD = $redis->hMGet('hash_market_'.$val.'_sell',$sell_keys);  //关联数组,值为json;
//                    $r = $this->dataUpdate($table,$hashSellD);
//
//                    if ($r){
//                        $redis->lTrim('list_sell_keys_edit'. $val,$sell_count1,-1);
//                    }
//                }
//            }
//
//            $buy_keys2 = $redis->lRange('list_buy_keys'. $val,0,-1);
//            $buy_count2 = count($buy_keys2);
//            if ($buy_keys2){
//                $buy_keys = array_diff($buy_keys2,$buy_keys1,$finish_keys);
//                if ($buy_keys){
//                    $hashBuyD = $redis->hMGet('hash_market_'.$val.'_buy',$buy_keys);  //关联数组,值为json;
//                    $r = $this->dataInsert($table,$hashBuyD);
//
//                    if ($r){
//                        $redis->lTrim('list_buy_keys'. $val,$buy_count2,-1);
//                    }
//                }
//                //修改的数据
//                $buy_keys = array_diff($buy_keys1,$finish_keys);
//                if ($buy_keys){
//                    $hashBuyD = $redis->hMGet('hash_market_'.$val.'_buy',$buy_keys);  //关联数组,值为json;
//                    $r = $this->dataUpdate($table,$hashBuyD);
//
//                    if ($r){
//                        $redis->lTrim('list_buy_keys_edit'. $val,$buy_count1,-1);
//                    }
//                }
//            }
//
//            //完成交易的挂单数据
//            if ($finish_keys){
//                $finish_count = count($finish_keys);
//                $tradeFinishD = $redis->lRange('list_market_'.$val.'_finish',0,$finish_count-1);
//                if ($tradeFinishD){
//                    $r = $this->dataUpdate($table,$tradeFinishD);
//
//                    if ($r){
//                        $redis->multi()
//                            ->lTrim('list_market_'.$val.'_finish',$r,-1)
//                            ->lTrim('list_finish_keys'. $val,$finish_count,-1)
//                            ->exec();
//                    }
//                }
//            }
//
//        }/**循环 交易市场表 结束!**/
//
//    }


    /**
     * 把数据写入数据表中(用于新增数据)
     * @param $table
     * @param $jsonData     :数据必须是数组形式,值是json字符串
     * @return int|string
     */
//    private function dataInsert($table,$jsonData)
//    {
//        $count = count($jsonData);
//        $r = 0;
//        if ($count > 1){
//            $data = [];
//            foreach ($jsonData as $value){
//                $data[] = json_decode($value,true);
//            }
//            $r = Db::table($table)->data($data)
//                ->limit(100)
//                ->insertAll();
//        }elseif ($count == 1){
//            $r = Db::table($table)
//                ->data(json_decode($jsonData[0],true))
//                ->insert();
//        }
//        return $r;
//    }

//    private function dataUpdate($table,$jsonData)
//    {
//        $re = 0;
//        foreach ($jsonData as $value){
//            $value1 = json_decode($value,true);
//            if(Db::table($table)->where('order_no',$value1['order_no'])->count()){
//                $r = Db::table($table)
//                    ->where('order_no',$value1['order_no'])
//                    ->update($value1);
//
//                if ($r){
//                    $re += $r;
//                }
//            }else{
//                $r = Db::table($table)
//                    ->data($value1)
//                    ->insert();
//
//                if ($r){
//                    $re += $r;
//                }
//            }
//
//        }
//        return $re;
//    }

//    private function userFinanceUpdate($userFinanceData)
//    {
//        $re = 0;
//        foreach ($userFinanceData as $value){
//            $value1 = json_decode($value,true);
//            $r = Db::table('user_finance')
//                ->where([
//                    ['ui_id'=>$value['ui_id']],
//                    ['ci_id'=>$value['ci_id']],
//                ])
//                ->update($value1);
//
//            if ($r){
//                $re += $r;
//            }else{
//                break;
//            }
//        }
//        return $re;
//    }



    /**
     * 买卖未撮合的定时守护任务
     * 必须走公共的撮合方法,防止用户正在撮合过程中 这边 调用撮合
     */
//    public function watchTrade()
//    {
//        $marketInfo = MarketInfoModel::column('mi_id');
//        $redis = Redis::instance();
//        foreach ($marketInfo as $value){
//            $key = 'hash_market_' .$value;
//            $sells = $redis->hVals($key .'_sell');//返回的是数组,每个数组元素是个json字符串
//            $buys  = $redis->hVals($key .'_buy'); //返回的是数组,每个数组元素是个json字符串
//            $effectiveSells = [];
//            $effectiveBuys  = [];
//            if ($sells && $buys){
//                foreach ($sells as $k => $row) {
//                    $row1 = json_decode($row,true);
////                    if ($row1['price'] <= $preData['price_c']){
//                    $effectiveSells[] = $row1;
//                    $sortPrice[$k] = $row1['price'];
//                    $sortTimeS[$k] = $row1['create_time'];
//                    $sortMicroS[$k] = $row1['microS'];
////                    }
//                }
//                if ($effectiveSells){
//                    array_multisort($sortPrice, SORT_ASC,
//                        $sortTimeS, SORT_ASC,
//                        $sortMicroS, SORT_ASC,
//                        $effectiveSells);
//                }
//
//                foreach ($buys as $k => $row) {
//                    $row1 = json_decode($row,true);
////                    if ($row1['price'] >= $preData['price_c']){
//                    $effectiveBuys[] = $row1;
//                    $sortPrice[$k] = $row1['price'];
//                    $sortTimeS[$k] = $row1['create_time'];
//                    $sortMicroS[$k] = $row1['microS'];
////                    }
//                }
//                if ($effectiveBuys){
//                    array_multisort($sortPrice, SORT_DESC,
//                        $sortTimeS, SORT_ASC,
//                        $sortMicroS, SORT_ASC,
//                        $effectiveBuys);
//                }
//
//                $valueBuy = array_shift($effectiveBuys);
//                if (!$valueBuy){
//                    continue;
//                }
////                $count = min(count($effectiveSells),count($effectiveBuys));
//                foreach ($effectiveSells as $valueSell){
//                    //卖单价格小于等于买单价格(说明数据有问题)
//                    if ($valueSell['price'] <= $valueBuy['price']){
//                        //把后挂单的数据丢入异常
//                        $buyKeys = $valueBuy['ui_id'].'_'.$valueBuy['create_time'].'_'.$valueBuy['microS'];
//                        $sellKeys = $valueSell['ui_id'].'_'.$valueSell['create_time'].'_'.$valueSell['microS'];
//                        if ($valueSell['create_time'] < $valueBuy['create_time'] || ($valueSell['create_time'] == $valueBuy['create_time'] && $valueSell['microS'] < $valueBuy['microS'])){
//                            $ret = $this->writeExData($key .'_buy',$buyKeys,$valueBuy);
//                            $valueBuy = array_shift($effectiveBuys);
//                            if (!$valueBuy){
//                                continue 2;
//                            }
//                        }else{
//                            $ret = $this->writeExData($key .'_sell',$sellKeys,$valueSell);
//                        }
//                        if ($ret === true){
//                            Log::write('错误信息:挂单数据出现了未撮合的异常情况!!对应数据的域为:'.$buyKeys,'tradeError');
//                        }
//                    }else{
//                        break;
//                    }
//                }
//
//            } //有买卖挂单数据的分支 结束
//        } //循环 交易市场 结束
//
//    }
    public function watchTrade(){
        $marketInfo = MarketInfoModel::column('mi_id');
        $redis = Redis::instance();
//        swoole:client:putup:buy:1
        $key = 'swoole:client:putup:';
        foreach ($marketInfo as $value){
            $redis->select(2);
            $buys = json_decode($redis->get($key .'buy:' .$value),true);//继伟 由大到小
            $sells = json_decode($redis->get($key .'sell:' .$value),true);//由小到大
            if (empty($buys) || empty($sells)){
                continue;
            }
//            var_dump($buys);die('');
            $buy1 = array_shift($buys);
            $sell1 = array_shift($sells);
            if ($buy1['price'] >= $sell1['price']){
                //修改挂单数据
                $redis->select(0);
                $keySelect0 = 'hash_market_' .$value;
                $sellsSelect0 = $redis->hVals($keySelect0 .'_sell');//返回的是数组,每个数组元素是个json字符串
//                $buysSelect0  = $redis->hVals($key .'_buy'); //返回的是数组,每个数组元素是个json字符串
                foreach ($sellsSelect0 as $val){
                    $val1 = json_decode($val,true);
                    if ($val1['price'] == $sell1['price']){
                        $field = $val1['ui_id'].'_'.$val1['create_time'].'_'.$val1['microS'];
                        $res = $this->writeExData($keySelect0 .'_sell',$field,$val1);
                        if ($res === false){
                            Db::name('exception_trade')->insert($val1);
                        }
                    }
                }
            }


        }
    }

    /**
     * 写入异常数据
     * @param $key
     * @param $field
     * @param $data
     */
    private function writeExData($key, $field, $data)
    {
        $redis = Redis::instance();
        if ($redis->exists('str' . $data['order_no'])) {
            $result['msg'] = '真的很巧合,数据正在撮合!';
            return false;
        }
        if ($data['type'] == 1) {
            $r = $redis->hDel($key, $field);
        } elseif ($data['type'] == 2) {
            $r = $redis->hDel($key, $field);
        }
        if ($r != 1){
            return false;
        }
        $data['status'] = 10;
        $r = Db::name('exception_trade')->insert($data);
        if (!$r){
            return false;
        }
        return true;
    }

    /**
     * 后台修改手续费或修改用户分组的时候,调用此接口以清除Redis的手续费信息
     * @param $table        : coin_info | market_info | user_group 3个表配置有手续费
     * @param $id           : 币种ID    | 交易市场ID  | 用户ID     3个ID
     */
    public function feeEdit($id,$table='')
    {
        $redis = Redis::instance();
        switch ($table){
            case 'coin_info':
                $key = 'hash_data_coinFee';
                break;
            case 'market_info':
                $key = 'hash_market_info';
                break;
            case 'user_group':
                $key = 'hash_data_userGroupFee';
                break;
            default:
                $key = 'hash_data_userGroupFee';
                break;
        }
        $r = $redis->hDel($key,$id);
        if ($r == 0){
            if ($redis->hExists($key,$id) != 0){
                return false;
            }
        }
        return true;
    }
}
