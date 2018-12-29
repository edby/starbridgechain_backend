<?php

namespace app\home\controller;

use app\home\model\UserFinanceModel;
use curl\Curl;
use redis\Redis;
use think\Controller;
use think\Db;
use think\Exception;
use think\exception\ErrorException;
use think\helper\Hash;
use think\facade\Log;
use think\Request;

class Test extends Controller
{
    const BCPRECISEDIGITS = 13;

    private static $hKey = 'hash_market_'; //redis存储挂单数据的key [hash_market_1_sell]
    private static $hKeyBuy = '_buy';
    private static $hKeySell = '_sell';
    /**
     * 显示资源列表
     *
     * @return \think\Response
     */

    public function index()
    {
        /**$a = pack('N',6);
        var_dump($a);die('');*/
//        try {
////            echo 1111;
////            exception("余额为负");
//
//            echo 2222;
//            trigger_error('入库出错!');
//
//            echo 3333;
//            exception('asdfasdfasfasf');
//        } catch (Exception $e){
////            var_dump($e->getTrace()[0]);
////            var_dump($e->getLine());
//            $error = $this->myGetTrace($e);
//            echo $error;
//        }
//
//        die;
//        $marketTradeLog['mt_order_ui_id'] = 1;
//        $marketTradeLog['create_time'] = 111;
//
//        $other['coin2'] = 2;
//        $disparity = 10;
//        $pairSecond_bef = 20;
//        $map1 = [
//            ['ui_id','=',$marketTradeLog['mt_order_ui_id']],
//            ['ci_id','=',$other['coin2']],
//        ];
//        $amount1 = [
//            'amount'          =>   Db::raw('amount+'.$disparity),
////            'trans_frost'     =>   Db::raw('trans_frost-'.$pairSecond_bef),
//            'trans_frost'     =>   Db::raw("CASE WHEN trans_frost>=$pairSecond_bef THEN trans_frost-$pairSecond_bef ELSE -1 END"),
//            'update_time'     =>   $marketTradeLog['create_time'],
//        ];
//        Db::table('user_finance')
//            ->where($map1)
////                        ->where('amount',$userFinances[1]['amount'])
////                        ->where('trans_frost',$userFinances[1]['trans_frost'])
//            ->update($amount1);
//
//        die('测试CASE WHEN函数');
        ini_set('memory_limit',-1);
        $redis = Redis::instance();
        $key = self::$hKey .'1';
        $suf = self::$hKeySell; //$hKeyBuy   $hKeySell

        $Buys = $redis->hVals($key .$suf);
//        var_dump($Buys);
        $effectiveBuys = [];
        if ($Buys){
            foreach ($Buys as $k => $row) {
                $row1 = json_decode($row,true);
//                $hField = $row1['ui_id'] . '_' . $row1['create_time'] . '_' . $row1['microS'];
                $hField = $row1['order_no'];
                $tradeData = $redis->hExists($key . $suf, $hField);
                if (!$tradeData){
                    $effectiveBuys[$hField] = $row1;
                }
            }
        }
        echo count($Buys);
        var_dump($effectiveBuys);

        die(PHP_EOL.'读取Redis数据查验');
        include_once dirname(dirname(dirname(__FILE__)))."/robot/server/trade/whiner.php";
        list($microS, $timeS) = explode(' ', microtime());
        $order_no = uniqid('rt'.mt_rand(100000,999999),true);
        $data1 = [
            'ui_id' => 1,
            'mi_id' => 1,
            'type' => 1,     //1是买 2是卖
            'price' => 1000,
            'total' => 1,   //挂单总数
            'decimal' => 1,   //剩余数量
            'fee' => 0.1,   //交易后获得币种 的手续费
            'create_time' => $timeS,
            'update_time' => $timeS,
            'microS' => $microS,
            'status' => 1,     //1:交易中  2:已完成  3:已撤销
            'order_no' => $order_no,//交易流水号
            'limit_market' => 1,//限价/市价
        ];
        $data2 = [
            'ui_id' => 2,
            'mi_id' => 1,
            'type' => 2,     //1是买 2是卖
            'price' => 1000,
            'total' => 1,   //挂单总数
            'decimal' => 1,   //剩余数量
            'fee' => 0.1,   //交易后获得币种 的手续费
            'create_time' => $timeS,
            'update_time' => $timeS,
            'microS' => $microS,
            'status' => 1,     //1:交易中  2:已完成  3:已撤销
            'order_no' => $order_no,//交易流水号
            'limit_market' => 1,//限价/市价
        ];
        $a = robotTrade($data1,$data2);
        var_dump($a);die('');

        die;
        $result = config('code.success');
        $result['msg'] = urlencode($result['msg']);
        return urldecode(json_encode($result));

        $coinInfo = UserFinanceModel::where('ui_id', 1)
            ->where('ci_id', 1)
            ->where('amount', 11)//$amount['amount']
            ->where('trans_frost', 11)
            ->update([
                'ui_id' => 1,
                'ci_id' => 2,
            ]);
        var_dump(UserFinanceModel::getLastSql());die('');
//        $a = array("apple", "banana");
//        $b = array(1 => "banana", "0" => "apple");
//
//        var_dump($a == $b); // bool(true)
//        var_dump($a === $b); // bool(false)
//        die;
        $transport = array('foot', 'bike', 'car', 'plane');
        $a = next($transport);
        var_dump($a);
        foreach ($transport as $k=>$value){
            var_dump($value);
//            if ($k==3){
                $mode1 = end($transport);
                $mode1 = reset($transport);
                $mode = next($transport);
                var_dump($mode1.'=='.$mode.'12');
//            }

            if ($k==3){
                die('');
            }
        }
//        $mode = current($transport); // $mode = 'foot';
        $mode = next($transport);    // $mode = 'bike';
//        $mode = next($transport);    // $mode = 'car';
//        $mode = prev($transport);    // $mode = 'bike';
//        $mode = end($transport);     // $mode = 'plane';
        var_dump($mode,$transport);die('');

//        $onOff = action("swoole/checkMarketStatus",[1]); // 会执行构造函数
        $onOff = Swoole::checkMarketStatus(1); // 不会执行构造函数
        $redis = Redis::instance();
        $redis->incr('bb');

        die('111');
        var_dump(number_format(5555555,2,'.',','));die('');
        $limitMarket = 123;
        if (!preg_match('/^(1|2)$/', (string)$limitMarket)) {
            die('xxx');
        }
        die('yyy');
//        $feeAccountId = Db::table('user_info')
//            ->where('user_type',4)
//            ->value('ui_id');
//        $map99 = [
//            ['ui_id','=',$feeAccountId],
//            ['ci_id','=',1],
//        ];
//        $feeAccount = bcsub(1,0.999,self::BCPRECISEDIGITS);
//        $amount99 = [
//            'amount'          =>   Db::raw('amount+'.$feeAccount),
//            'update_time'     =>   22222,
//        ];
//        Db::table('user_finance')
//            ->where($map99)
//            ->update($amount99);
//        var_dump(Db::table('user_finance')->getLastSql());die('111');
//        die;
////        var_dump(self::BCPRECISEDIGITS);die('');
//        $map1 = [
//            ['ui_id','=',1],
//            ['ci_id','=',1],
//        ];
//        Db::table('user_finance')
//            ->where($map1)
////            ->setInc('amount', '0.1234567891111')
//            ->update([
////                'name'		=>	Db::raw('UPPER(name)'),
//                'amount'		=>	Db::raw('amount+'.'0.1234567891111'),
//                'update_time'	=>	1111
//            ]);
////            ->setDec('amount', '0.123456789555545');
//        die('OK');
//        $key = 'key';
//        $value = 'key';
//        $timeout = 111;
//        $proRedis = new \Redis();
//        $proRedis->connect('127.0.0.1', 6379);
//        $proRedis->auth('U#rNFRkk3vuCKcZ5');
//        $proRedis->set($key,$value,'EX',$timeout,'NX');
//
//        die;
//        $redis = Redis::instance();
//        $a = $redis->get('str_deepM1')?$redis->get('str_deepM1'):0;
//        var_dump($a);die('');
//        die;
//        $table = 'market_trade';
//        $field = "`ui_id`='2',`decimal`='0.3',`order_no`='asdfastew',";
//        $field = substr ( $field, 0, - 1 );
//        $sql = "insert into `{$table}` SET {$field} on duplicate key update {$field}";
//        $a = Db::execute($sql);
//        var_dump($a);
//
//        die('测试 ON DUPLICATE KEY UPDATE');
//        $price = 555.444;
//        $transactionPair['price_bit'] = 4;
//        if (!preg_match('/^[0-9]+(.[0-9]{1,'.$transactionPair['price_bit'].'})?$/', (string)$price)) {
//            $result['msg'] = lang('LMT_PRICE_BIT');
//            return json($result);
//        }
//        var_dump(1-0.2/100);die('');
//        $sells = [
//            ['id'=>2,'create_time'=>1533332222,'microS'=>'0.55645600','price'=>160,'total'=>100,'decimal'=>0.8645,1],
//        ];
//        if ($sells){
//            array_multisort(array_column($sells,'price'), SORT_ASC,
//                array_column($sells,'create_time'), SORT_ASC,
//                array_column($sells,'microS'), SORT_ASC,
//                $sells);
//        }
//        var_dump($sells);
//
//        die('test,array_multisort');
//        $table = 'market_trade2018_1';
//        $map = [
//            'order_no'=>'15398560545bc856b651de15.56618854',
//            'decimal'=>'3.000000000000000',
//            'total'=>'100.000000000000000',
//            'ui_id'=>'102',
//        ];
//        $a = Db::table($table)->where($map)->count();
////        var_dump($a);die;
//        var_dump(Db::table($table)->getLastSql());die;
//
//
//        $table = 'market_trade2018_1';
//        $data = '{"ui_id":102,"mi_id":1,"type":2,"price":"998","total":"100","decimal":"4.000000000000000","fee":"0.100000000000000","create_time":"1539853497","microS":"0.61532100","status":1,"order_no":"15398534975bc84cb99dc4d0.90540757","limit_market":"1"}';
//        $r = Db::table($table)
//            ->where('order_no',json_decode($data,true)['order_no'])
//            ->update(json_decode($data,true));
////        $r = Db::table($table)
////            ->data(json_decode($data,true))
////            ->insert();
//        var_dump($r);die('');


        include_once dirname(__FILE__)."/../server/cfg.php";
        include_once dirname(__FILE__)."/../server/dblib.php";
//        var_dump($CFG);die('');
        $conn = db_connect($CFG->dbhost, $CFG->dbname, $CFG->dbuser, $CFG->dbpass);
        //服务器不报错!本地报错ErrorException in dblib.php line 17 未定义变量: CFG

//        $conn = new \PDO('mysql:host='.$CFG->dbhost.';dbname='.$CFG->dbname.';charset=utf8', $CFG->dbuser, $CFG->dbpass);
//        var_dump($conn);die('');
//
//
//        //>>>>>>
//        $table = 'market_info_1111';
//        $sql = <<<sql
//        CREATE TABLE `$table` (
//          `mt_id` int(11) NOT NULL AUTO_INCREMENT,
//          `ui_id` int(11) NOT NULL DEFAULT '0' COMMENT '账户表ID',
//          `mi_id` int(11) NOT NULL DEFAULT '0' COMMENT '交易市场名称ID',
//          `type` int(11) NOT NULL DEFAULT '0' COMMENT '类型1：买，2：卖',
//          `price` decimal(30,15) NOT NULL DEFAULT '0.000000000000000' COMMENT '单价',
//          `total` decimal(30,15) NOT NULL DEFAULT '0.000000000000000' COMMENT '挂单总数',
//          `decimal` decimal(30,15) NOT NULL DEFAULT '0.000000000000000' COMMENT '剩余数量',
//          `fee` decimal(30,15) NOT NULL DEFAULT '0.000000000000000' COMMENT '买入手续费',
//          `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '成交时间',
//          `microS` char(11) NOT NULL DEFAULT '' COMMENT '时间戳的微妙单位',
//          `status` int(11) NOT NULL DEFAULT '0' COMMENT '状态：1:交易中  2:已完成  3:已撤销  4:异常',
//          `order_no` varchar(255) NOT NULL DEFAULT '' COMMENT '交易流水号',
//          `limit_market` tinyint(4) NOT NULL DEFAULT '0' COMMENT '1限价/2市价',
//          PRIMARY KEY (`mt_id`) USING BTREE,
//          UNIQUE KEY `order_no` (`order_no`) USING BTREE,
//          KEY `FK_Reference_3` (`ui_id`) USING BTREE,
//          KEY `FK_Reference_4` (`mi_id`) USING BTREE
//        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='未了使页面查询速度较快，需要进行分表\r\n交易市场交易挂单表 ';
//sql;
//        $result = db_query($conn,$sql);
//        var_dump($result);die('');
//
//
//        //>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
//        $table = 'market_i21nfo';
//        $sql = "show tables like '{$table}'";
//        $qid = db_query($conn,$sql);
//        var_dump($qid);die('');
//
//
//        //>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
//        $sql = "SELECT * FROM `user_finance` WHERE ( `ui_id` = 1 AND `ci_id` = 1 ) OR ( `ui_id` = 1 AND `ci_id` = 2 ) OR ( `ui_id` = 2 AND `ci_id` = 1 ) OR ( `ui_id` = 2 AND `ci_id` = 2 )";
//        $result = db_query_array($conn,$sql);
//        var_dump($result);die('');
//
//
//        //>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
        $table = 'market_info';
        $data['order_no'] = '1';
        $sql = "SELECT * FROM `{$table}` WHERE `ci_id_first` = '{$data['order_no']}'";
//        var_dump($sql);die('');
        $qid = db_query($conn,$sql); //返回影响的数量,查询就是查询到的数量,更新就是更新的数量
        //db_query_array($conn,$sql)  //返回二维数组的结果集
        var_dump($qid);die('');
//
//        //>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
//        Db::table('user_finance')->where('amount',1)->count();
//        var_dump(Db::table('user_finance')->getLastSql());
//
//        die('');
        $marketTradeLog['mt_order_ui_id'] = 2;
        $other['market_ciIdFirst'] = 1;
        $marketTradeLog['mt_peer_ui_id'] = 1;
        $other['market_ciIdSecond'] = 2;
        $map0 = [
            ['ui_id','=',$marketTradeLog['mt_order_ui_id']],
            ['ci_id','=',$other['market_ciIdFirst']],
        ];
        $map1 = [
            ['ui_id','=',$marketTradeLog['mt_order_ui_id']],
            ['ci_id','=',$other['market_ciIdSecond']],
        ];
        $map2 = [
            ['ui_id','=',$marketTradeLog['mt_peer_ui_id']],
            ['ci_id','=',$other['market_ciIdFirst']],
        ];
        $map3 = [
            ['ui_id','=',$marketTradeLog['mt_peer_ui_id']],
            ['ci_id','=',$other['market_ciIdSecond']],
        ];
//        $userFinances = Db::table('user_finance')->whereOr([$map0,$map1,$map2,$map3])->select();
        $userFinances = Db::table('user_finance')->whereOr([$map0,$map1])->order('ci_id','asc')->select();
        $valueFinances = Db::table('user_finance')->whereOr([$map2,$map3])->order('ci_id','asc')->select();
        var_dump($userFinances,$valueFinances);

        die;
        $data = [
            ['test'=>11,'data'=>12],
            ['test'=>22,'data'=>23],
            ['test'=>33,'data'=>34],
            ['test'=>44,'data'=>45],
            ['test'=>55,'data'=>56],
            ['test'=>66,'data'=>67],
            ['test'=>77,'data'=>78],
            ['test'=>88,'data'=>89],
            ['test'=>99,'data'=>90],
            ['test'=>'00','data'=>'10'],
        ];
        $z = false;
        foreach ($data as $k=>$v){
//            if ($v['data'] == 12){
//                unset($data[$k]);
//            }
//            $a = next($data);
//            $b = next($data);
//            var_dump($a,$b,$v);die('');

            //判断是否循环
            if ($z == false){
                echo 123;
                echo '----',$v['test'],'====',$k;
                echo '</br>';
                //上层的代码逻辑
            }

            if ($v['test'] < 66){
                unset($data[$k]);
                $z = true;
                continue;
            }else{
                $z = false;
            }
//            var_dump($v);
        }

        die('测试指针移动!!');
//        try {
//            $redis = Redis::instance();
//            $redis->set('test',1,1);
//            $b = 0;
//            $redis->setnx('test',1);
//            if ($b == 0){
//                return '0000';
//            }
//            echo $a;
//            echo <<<xxx
//            Redis返回0的函数不会报错,需要自己判断!
//xxx;
//
//
//        } catch (Exception $e){
//            Log::write('错误码:'.$e->getCode().'!!错误信息:'.$e->getMessage(),'tradeError');
//            return 'Exception';
//        }
//
//        die('如果被return,还能捕获错误吗?');
//        $redis = Redis::instance();
//        $redis->getSet('test',555);
//        die('GETSET key value将给定 key 的值设为 value ，并返回 key 的旧值(old value)。');
//        $redis = Redis::instance();
//        $redis->rPush('greet','morning');
//        $redis->rPush('greet','hello');
//        $redis->rPush('greet','morning');
//        $redis->rPush('greet','hello');
//        $redis->rPush('greet','world');
//        $redis->rPush('greet','world');
//
//        $redis->multi()
//            ->lRem('greet','test',0)
//            ->rPush('greet','test')
//            ->exec();
//
//        $a = $redis->lRange('greet',0,-1);
//        var_dump($a);die('');
//
//        die('测试lrem');
//        $a = Hash::make('123','md5',['salt' => 'gehua']);
//        $b = Hash::make('123',null,['salt' => 'gehua']);
//        var_dump($a,$b);
////        $a = Hash::check('123','b3f4e9e6fe8e27fc1158d0a38dd65b9e','md5',['salt' => 'gehua']);
////        $b = Hash::check('123','$2y$10$8hZCvOOyraPqAu0jQabv8.YtOuZO99AffN.WVCqK19iV/B.uExSVK',null,['salt' => 'gehua']);
////        var_dump($a,$b);
//
//        die();
//        var_dump(ini_get('memory_limit'));die('');
//
//        //查看内存使用情况PHP内置函数
//        $a = memory_get_usage();//返回当前分配给PHP脚本的内存量，单位是字节（byte）。
//        $b = memory_get_peak_usage();//返回内存使用峰值
//        $c = getrusage();//返回CUP使用情况
//        var_dump($a,$b,$c);
//
//        die();
//        var_dump($_SERVER['argv']);
//
//        die('测试$_SERVER');
//        $a = $_SERVER['REQUEST_TIME_FLOAT'];
//        list($microS,$timeS) = explode(' ', microtime());
//        list($microS1,$timeS1) = explode(' ', microtime());
//        list($microS2,$timeS2) = explode(' ', microtime());
//        list($microS3,$timeS3) = explode(' ', microtime());
//        var_dump($a,$timeS,$microS,$timeS1,$microS1,$timeS2,$microS2,$timeS3,$microS3);
//
//        die('请求开始时的时间戳，微秒级别的精准度和服务器的时间');
//        $market_c['market_first_fee'] = "0.3";
//        $buyData['fee'] = "0.15";
//        $userId = 1;
//        $userGroupFee = Db::table('user_group')->alias('ug')
//            ->join('group_info gi','ug.gi_id = gi.gi_id','LEFT')
//            ->where('ug.ui_id',$userId)
////            ->where('gi.status',1)  //可用的分组
//            ->column('gi.fee');
//
//        $userGroupFee = min(array_merge((array)$userGroupFee,(array)$buyData['fee'],(array)$market_c['market_first_fee']));
//
//        var_dump($userGroupFee);
//
//        die('测试联表!');
//        $redis = Redis::instance();
//        $redis->watch('lianshi');  //监视一个(或多个) key ，如果在事务执行之前这个(或这些) key 被其他命令所改动，那么事务将被打断。
//        $redis->hSet('lianshi','yu2','yu2dezhi');
//        $ret = $redis->multi()
//            ->hSet('lianshi','yu1','zhi1')
//            ->rPush('list','lianshi')
//            ->rPush('list2','qwe')
//            ->hSet('lianshi','yu1','zhi2')
//            ->exec();
//        if (!$ret){
//            echo '说明事务已经取消!!';die();
////            $redis->discard();
//        }
//
////        $redis->unwatch();如果在执行 WATCH 命令之后， EXEC 命令或 DISCARD 命令先被执行了的话，那么就不需要再执行 UNWATCH 了。
//        $a = '实验证明是不可以链式操作 Redis ,除非是事务!!';
//
//
//        die('验证Redis不用事务,是否可以链式操作Redis');
////        $arr = array('z'=>'a','y'=>'b','x'=>'c','h'=>'d');
////        sort($arr);
////        print_r($arr);
////
////        die('关联数组转换索引数组');
//        $redis = Redis::instance();
//
//        $sell_keys = $redis->lRange('list_sell_keys',0,-1);
//        var_dump($sell_keys);
//        $marketId = '1';
//        $hashD = $redis->hMGet('hash_market_'.$marketId.'_sell',$sell_keys);
//        var_dump($hashD);die('');
//
//        die('验证Redis获取的数据类型!是否需要转换!');
        $data = [
            ['test'=>11,'data'=>22],
            ['test'=>33,'data'=>44],
            ['test'=>55,'data'=>66],
            ['test'=>77,'data'=>88],
            ['test'=>99,'data'=>0],
        ];
        foreach ($data as $k => $v){
//            if ($k == 2){
//                $v['test'] = 9999;
//                $data[$k+1]['test'] = 10000;
//            }
//            if ($k == 3)
//                $data[$k]['data'] = 'XXXX';
//            if ($k == 0)
//                unset($data[++$k]);
            if ($v['data'] == 22){
                unset($data[$k]);
            }

//            var_dump($k);die();
            foreach ($data as $ke => $va){
                if ($ke == 1){
                    unset($data[$ke]);break;
                }
            }

//            var_dump($data);
        }
        dump($data);

        die('遍历中修改 数组的值(传引用和数组加下标都可以修改)! 但是删除-->必须用数组加下标的方式!!');
        $data = [
            'test1',
            'test2',
            'test3',
            'test4',
            'test5',
        ];
        $a = count($data);
        var_dump($a);
        foreach ($data as $k => $v){
            if ($k == 4)
                unset($data[$k]);
//            var_dump($k);die('');

            if ($a-$k == 1){
                var_dump($k.'=>'.$v);
            }

        }
        var_dump($data);die('');

        die('判断出遍历当前的元素为 数组的最后一个!!');
        $preData = [
            'type'   =>   555,
            'num'    =>   2,
        ];

        extract($preData);

        $type = $type;
        var_dump($type);


        die('测试把一个extract后的变量赋值给自己');
        function abc($n){
            if ($n > 2)
                abc(--$n);
            echo '$n='.$n;
        }
        abc(5);


        die('测试递归');
        for ($i = 0; $i < 5; ++$i) {
            if ($i == 2)
                continue;
            var_dump($i);
        }


        die('测试continue');
        function test1(){
            static $dig=0;
            if(++$dig<10){
                echo $dig;
                test1();
            }
        }
        test1();//12345678910

        die('测试递归2');
        $a = 1;
        do {
//            $this->test();
            echo $a;
            ++$a;
        } while($a <= 3);

        die('测试do{}while();');
        $redis = Redis::instance();


        $redis->rPush('test',1);
        $redis->rPush('test',2);
        $redis->rPush('test',3);
        $redis->rPush('test',4);

        $a = $redis->lRange('test',0,-1);
        foreach ($a as $k => $v){
            var_dump($k.'_'.$v);
            if ($v == 3){
                $redis->lSet('test',$k,33);
            }
        }
        $b = $redis->lRange('test',0,-1);
        var_dump($b);

        die('测试Redis队列修改数据');
        $redis->rPush('test',11);
        $a = $redis->lRange('test',0,-1);
        foreach ($a as $ke => $va){
            var_dump($ke.'__'.$va);
        }
        var_dump($redis->lPop('test'));

        die('测试Redis队列有推入,遍历的第一个是最先推入的');
//        $a = $redis->lRange('key1',0,-1);
//        foreach ($a as $key=> $va){
//            var_dump($key,$va);
//        }
//        var_dump($a);die;

//        $market['market_ciIdFirst'] = 1;
        $list = [
            'userId'                  =>      1,
            'market'                  =>      [
                'mi_id'                     =>      11,
                'market_ciIdFirst'          =>      111,
                'market_ciIdSecond'         =>      222,
            ],
            'price_c'                 =>       333,
            'decimal_c'               =>       444,
        ];

        $redis->lPush('list_preMatchMaking',json_encode($list));

//        var_dump(json_decode($redis->lPop('preMatchMaking'),true),11);die;
//        array(2) { ["userId"]=> int(11) ["array"]=> array(3) { ["market"]=> int(1) ["first"]=> int(11) ["second"]=> int(22) } } int(11)
        $a = extract(json_decode($redis->rPop('list_preMatchMaking'),true),EXTR_PREFIX_SAME,'wddx');

        var_dump($a,$userId,$market,$price_c,$decimal_c);



        die('测试extract,后数组的键作为变量使用 获取其值');
        $a = 2;
        echo $a.'</hr>';

        $timeout = 110;
        $roomId = mt_rand(1,10001);
        $key = 'room_lock';
        $value = 'room_'.$roomId;

        $isLock = null;
        if ($redis->exists($key) === FALSE){
            $isLock = $redis->set($key, $value, $timeout);
        }
        var_dump($isLock,boolval($isLock));



        die('测试Redis锁机制');
        $a = microtime();
        $b = explode(' ',$a);
        var_dump($b[0],$b[1]);

        echo '<hr>';

        static $i = 1;

        echo $i,'<br>';

        ++$i;
        if ($i <= 6)
            self::test();

        die('测试时间戳微妙格式 分割后(unknown)');
        //>>1.不需要登录就可以展示的,挂单数据和历史交易记录!

        //test 挂单数据字段[用户,挂单时间(2个字段{时间戳和毫秒部分}),下单价格,总量,数量,限价/市价,]
        $sells = [
            ['id'=>8,'create_time'=>1533332255,'microS'=>'0.45645600','price'=>660,'total'=>100,'decimal'=>0.8645,1],
            ['id'=>2,'create_time'=>1533332222,'microS'=>'0.55645600','price'=>160,'total'=>100,'decimal'=>0.8645,1],
            ['id'=>6,'create_time'=>1533332244,'microS'=>'0.45645600','price'=>560,'total'=>100,'decimal'=>0.8645,1],
            ['id'=>1,'create_time'=>1533332222,'microS'=>'0.45645600','price'=>60,'total'=>100,'decimal'=>0.8645,1],
            ['id'=>3,'create_time'=>1533332222,'microS'=>'0.25645600','price'=>260,'total'=>100,'decimal'=>0.8645,1],
            ['id'=>10,'create_time'=>1533332200,'microS'=>'0.45645600','price'=>760,'total'=>100,'decimal'=>0.8645,1],
            ['id'=>4,'create_time'=>1533332233,'microS'=>'0.45645600','price'=>360,'total'=>100,'decimal'=>0.8645,1],
            ['id'=>7,'create_time'=>1533332211,'microS'=>'0.45645600','price'=>560,'total'=>100,'decimal'=>0.8645,1],
            ['id'=>5,'create_time'=>1533332233,'microS'=>'0.45645600','price'=>460,'total'=>100,'decimal'=>0.8645,1],
            ['id'=>9,'create_time'=>1533332200,'microS'=>'0.45645600','price'=>660,'total'=>100,'decimal'=>0.8645,1],
        ];
        $price = 60;
        $effectiveSells = [];
        foreach ($sells as $k => $row) {
//                if ($row['status'] == '3'){   //1:交易中  2:已完成  3:已撤销
//                    unset($row);
//                } //把已成交的和已撤销的数据 放在另外一个Redis中
            if ($row['price'] <= $price){
                $effectiveSells[] = $row;
                $sortPrice[$k] = $row['price'];
                $sortTimeS[$k] = $row['create_time'];
                $sortMicroS[$k] = $row['microS'];
            }
        }
        if ($effectiveSells){
            array_multisort($sortPrice, SORT_DESC,
                $sortTimeS, SORT_ASC,
                $sortMicroS, SORT_ASC,
                $effectiveSells);
        }

        echo '<pre>';
        var_dump($effectiveSells);
        var_dump(array_pop($effectiveSells),$effectiveSells);


        die('测试 多维数组 遍历后 根据某几个字段进行排序!非常好用!!array_multisort');
        $a = 3.66;
        echo ~~$a.'</br>';

        $a = 2.515;
        echo ($a|0).'</br>';

        $a = 5.312;
        echo ($a>>0).'</br>';
        die('测试数字向下取整');

    }

    private function myGetTrace(Exception $e)
    {
//        var_dump(isset($e->getTrace()[0]['args'][2]));
        if (isset($e->getTrace()[0]['args'][2]) && is_string($e->getTrace()[0]['args'][2])){
            $trace = $e->getTrace()[0]['args'][2].':'.$e->getLine();
        }elseif (isset($e->getTrace()[0]['file']) && is_string($e->getTrace()[0]['file'])){
            $trace = $e->getTrace()[0]['file'].':'.$e->getTrace()[0]['line'];
        }else {
            $trace = '';
        }
        return '['.json_encode($e->getCode()).']'.$e->getMessage().'['.$trace.']';
    }

    /**
     * 生成挂单数据!!(挂卖单数据)
     */
    public function makeData()
    {
        $redis = Redis::instance();

        $j=1000;
        for ($i=1;$i<=$j;++$i){
            //假的挂单数据
            $foo = 'sellData'.$i;
            $$foo = [
                'ui_id'                 =>    2,
                'mi_id'                 =>    1,
                'create_time'           =>    1536632520+$i,
                'microS'                =>    '0.22222200',
                'type'                  =>    2,     //1是买 2是卖
                'price'                 =>    800+$i,
                'total'                 =>    1*$i,   //挂单总数
                'decimal'               =>    1*$i,   //剩余数量
                'fee'                   =>    0.15,   //交易后获得币种 的手续费
                'status'                =>    1,     //1:交易中  2:已完成  3:已撤销
                'limit_market'          =>    1,//限价/市价
                'order_no'              =>    uniqid(time()),//交易流水号
            ];

            $key = 'hash_market_' .'1';
            $redis->hMset($key .'_sell',[
                ($$foo)['ui_id'] . '_' . ($$foo)['create_time'] . '_' . ($$foo)['microS']   =>  json_encode(($$foo)),
            ]);

            $coo = 'buyData'.$i;
            $$coo = [
                'ui_id'                 =>    3,
                'mi_id'                 =>    1,
                'create_time'           =>    1536632159+2*$i,
                'microS'                =>    '0.33331300',
                'type'                  =>    1,     //1是买 2是卖
                'price'                 =>    700-$i,
                'total'                 =>    1*$i,   //挂单总数
                'decimal'               =>    1*$i,   //剩余数量
                'fee'                   =>    0.1,   //交易后获得币种 的手续费
                'status'                =>    1,     //1:交易中  2:已完成  3:已撤销
                'limit_market'          =>    1,//限价/市价
                'order_no'              =>    uniqid(time()),//交易流水号
            ];
            $key = 'hash_market_' .'1';
            $redis->hMset($key .'_buy',[
                ($$coo)['ui_id'] . '_' . ($$coo)['create_time'] . '_' . ($$coo)['microS']   =>  json_encode(($$coo)),
            ]);
        }


        die('数据生成成功!!数据条数'.$j);
    }

    /**
     * 运行撮合接口 进行数据挂单或者撮合
     */
    public function run()
    {
        set_time_limit(0);
        $j = 10000;

        $k = 0;
        //上传项目到服务器,创建数据库,添加用户,
        for ($i=1;$i<=$j;++$i){
            if ($i > $k){
                $login = [
                    'account'      =>  'test'.$i,
                    'pwd'          =>  '123',
                ];
                $user = Curl::post('http://124.116.240.197:8080/login',$login);

                $user = json_decode($user,true);
                $request_header = [
                    'token'         =>  $user['data']['token'],
                    'sign'          =>  $user['data']['sign'],
                    'timestamp'     =>  $user['data']['timestamp'],
                ];

                $data = [
//                    'token'             =>  json_decode($user,true)['token'],
                    'limitMarket'       =>  1,
                    'price'             =>  1000,
                    'decimal'           =>  2,
                    'market'            =>  1,
                    'payPwd'            =>  '',
                    'type'              =>  2,
                ];
                $a = Curl::post('http://124.116.240.197:8080/upTradeSell',$data,$request_header);
                echo $i.'</br>';
            }
        }

        var_dump($a,$j.'个挂卖单数据成功!!');die;
    }

    /**
     * 添加数据库 测试用的 用户信息和账户余额
     */
    public function dataCreate()
    {
        set_time_limit(0);
        $j = 10001;
        $l = 2254;
        $k = 10000;
        for ($i=1;$i<=$j;++$i){
            if ($i > $l){
                $r = Db::table('user_info')
                    ->where('ui_id',$i)
                    ->update([
                        'account'=>'test'.$i,
                    ]);
                if (!$r){
                    $data1 = [
                        'account'           =>  'test'.$i,
                        'pwd'               =>  'b3f4e9e6fe8e27fc1158d0a38dd65b9e',
                        'salt'              =>  'gehua',
                    ];
                    Db::table('user_info')
                        ->insert($data1);
                }
            }

            if ($i > $k){
                $data = [
                    ['ui_id'=>$i,'ci_id'=>1,'amount'=>1000000],
                    ['ui_id'=>$i,'ci_id'=>2,'amount'=>1000000]
                ];
                Db::name('user_finance')
                    ->data($data)
//                ->limit(100)
                    ->insertAll();
            }

        }
    }

    /**
     * 查询 Redis 里的数据显示在表格内 方便 测试对比
     */
    public function readData() {
        $year = date('Y');
        $month = date('Y_m');
        $redis = Redis::instance();

        /**>>1..成交记录数据**/
        $dealRecord = $redis->lRange('list_market_1_dealRecord'.$year,0,-1);
        echo '>>1..成交记录数据:','</br>';
        if ($dealRecord){
            echo '<table border="1">';
            echo <<<yyy
<th>
    <td>主动方</td>            
    <td>被动方</td>            
    <td>交易类型</td>            
    <td>市场ID</td>            
    <td>价格</td>            
    <td>数量</td>            
    <td>总额</td>            
    <td>买方手续费</td>            
    <td>卖方手续费</td>            
    <td>时间</td>            
    <td>微秒</td>            
    <td>主动流水号</td>            
    <td>被动流水号</td>            
</th>
yyy;
            foreach ($dealRecord as $value){
                $a = json_decode($value,true);
                $b = <<<xxx
<tr>
<td></td>
    <td>{$a['mt_order_ui_id']}</td>
    <td>{$a['mt_peer_ui_id']}</td>
    <td>{$a['type']}</td>
    <td>{$a['mi_id']}</td>
    <td>{$a['price']}</td>
    <td>{$a['decimal']}</td>
    <td>{$a['amount']}</td>
    <td>{$a['buy_fee']}</td>
    <td>{$a['sell_fee']}</td>
    <td>{$a['create_time']}</td>
    <td>{$a['microS']}</td>
    <td>{$a['order_no']}</td>
    <td>{$a['peer_order_no']}</td>
</tr>
xxx;

                echo $b;
            }
            echo '</table>';
            echo '-----------------------------------','</br>';
        }else{
            echo '数据1为空!';
        }

        /**>>2..用户余额数据**/
        $userFinance = $redis->hVals('hash_data_userFinance');
        echo '>>2..用户余额数据:','</br>';
        if ($userFinance){
            echo '<table border="1">';
            echo <<<yyy
<th>
    <td>用户ID</td>            
    <td>币种ID</td>            
    <td>余额</td>            
    <td>交易冻结</td>            
    <td>提现冻结</td>            
    <td>修改时间</td>            
</th>
yyy;
            foreach ($userFinance as $value){
                $a = json_decode($value,true);
                $b = <<<xxx
<tr>
<td></td>
    <td>{$a['ui_id']}</td>
    <td>{$a['ci_id']}</td>
    <td>{$a['amount']}</td>
    <td>{$a['trans_frost']}</td>
    <td>{$a['out_frost']}</td>
    <td>{$a['update_time']}</td>
</tr>
xxx;

                echo $b;
            }
            echo '</table>';
            echo '--------------------------------------------------------','</br>';
        }else{
            echo '数据2为空!';
        }

        /**>>3..用户挂买单数据**/
        $buys = $redis->hVals('hash_market_1_buy');
        echo '>>3..用户挂买单数据:','</br>';
        if ($buys){
            echo '<table border="1">';
            echo <<<yyy
<th>
    <td>用户ID</td>            
    <td>市场ID</td>            
    <td>创建时间</td>            
    <td>微秒</td>            
    <td>买/卖类型</td>            
    <td>价格</td>            
    <td>总数</td>            
    <td>剩余数量</td>            
    <td>手续费</td>            
    <td>状态(2完成)</td>            
    <td>限/市价</td>            
    <td>流水号</td>            
</th>
yyy;
            foreach ($buys as $value){
                $a = json_decode($value,true);
                $b = <<<xxx
<tr>
<td></td>
    <td>{$a['ui_id']}</td>
    <td>{$a['mi_id']}</td>
    <td>{$a['create_time']}</td>
    <td>{$a['microS']}</td>
    <td>{$a['type']}</td>
    <td>{$a['price']}</td>
    <td>{$a['total']}</td>
    <td>{$a['decimal']}</td>
    <td>{$a['fee']}</td>
    <td>{$a['status']}</td>
    <td>{$a['limit_market']}</td>
    <td>{$a['order_no']}</td>
</tr>
xxx;

                echo $b;
            }
            echo '</table>';
            echo '--------------------------------------------------------','</br>';
        }else{
            echo '数据3为空!';
        }

        /**>>4..用户挂买单数据**/
        $sells = $redis->hVals('hash_market_1_sell');
        echo '>>4..用户挂买单数据:','</br>';
        if ($sells){
            echo '<table border="1">';
            echo <<<yyy
<th>
    <td>用户ID</td>            
    <td>市场ID</td>            
    <td>创建时间</td>            
    <td>微秒</td>            
    <td>买/卖类型</td>            
    <td>价格</td>            
    <td>总数</td>            
    <td>剩余数量</td>            
    <td>手续费</td>            
    <td>状态(2完成)</td>            
    <td>限/市价</td>            
    <td>流水号</td>            
</th>
yyy;
            foreach ($sells as $value){
                $a = json_decode($value,true);
                $b = <<<xxx
<tr>
<td></td>
    <td>{$a['ui_id']}</td>
    <td>{$a['mi_id']}</td>
    <td>{$a['create_time']}</td>
    <td>{$a['microS']}</td>
    <td>{$a['type']}</td>
    <td>{$a['price']}</td>
    <td>{$a['total']}</td>
    <td>{$a['decimal']}</td>
    <td>{$a['fee']}</td>
    <td>{$a['status']}</td>
    <td>{$a['limit_market']}</td>
    <td>{$a['order_no']}</td>
</tr>
xxx;

                echo $b;
            }
            echo '</table>';
            echo '--------------------------------------------------------','</br>';
        }else{
            echo '数据4为空!';
        }

        /**>>5..财产变更日志**/
        $userFinanceLog = $redis->lRange('list_data_userFinanceLog'.$month,0,-1);
        echo '>>5..财产变更日志:','</br>';
        if ($userFinanceLog){
            echo '<table border="1">';
            echo <<<yyy
<th>
    <td>用户ID</td>            
    <td>市场ID</td>            
    <td>币种ID</td>            
    <td>前-余额</td>            
    <td>前-冻结</td>            
    <td>前-总计</td>            
    <td>变动数量</td>            
    <td>买/卖类型</td>            
    <td>变动时间</td>     
    <td>后-余额</td>            
    <td>后-冻结</td>            
    <td>后-总计</td>         
    <td>流水号</td>            
</th>
yyy;
            foreach ($userFinanceLog as $value){
                $a = json_decode($value,true);
                $b = <<<xxx
<tr>
<td></td>
    <td>{$a['ui_id']}</td>
    <td>{$a['mi_id']}</td>
    <td>{$a['ci_id']}</td>
    <td>{$a['bef_A']}</td>
    <td>{$a['bef_B']}</td>
    <td>{$a['bef_D']}</td>
    <td>{$a['num']}</td>
    <td>{$a['type']}</td>
    <td>{$a['create_time']}</td>
    <td>{$a['aft_A']}</td>
    <td>{$a['aft_B']}</td>
    <td>{$a['aft_D']}</td>
    <td>{$a['order_no']}</td>
</tr>
xxx;

                echo $b;
            }
            echo '</table>';
            echo '----------------------------------------------------------','</br>';
        }else{
            echo '数据5为空!';
        }

        /**>>6..挂单完成记录**/
        $finish = $redis->lRange('list_market_1_finish',0,-1);
        echo '>>6..挂单完成记录:','</br>';
        if ($finish){
            echo '<table border="1">';
            echo <<<yyy
<th>
    <td>用户ID</td>            
    <td>市场ID</td>            
    <td>创建时间</td>            
    <td>微秒</td>            
    <td>买/卖类型</td>            
    <td>价格</td>            
    <td>总数</td>            
    <td>剩余数量</td>            
    <td>手续费</td>            
    <td>状态(2完成)</td>            
    <td>限/市价</td>            
    <td>流水号</td>            
</th>
yyy;
            foreach ($finish as $value){
                $a = json_decode($value,true);
                $b = <<<xxx
<tr>
<td></td>
    <td>{$a['ui_id']}</td>
    <td>{$a['mi_id']}</td>
    <td>{$a['create_time']}</td>
    <td>{$a['microS']}</td>
    <td>{$a['type']}</td>
    <td>{$a['price']}</td>
    <td>{$a['total']}</td>
    <td>{$a['decimal']}</td>
    <td>{$a['fee']}</td>
    <td>{$a['status']}</td>
    <td>{$a['limit_market']}</td>
    <td>{$a['order_no']}</td>
</tr>
xxx;

                echo $b;
            }
            echo '</table>';
            echo '---------------------------------------------------','</br>';
        }else{
            echo '数据6为空!';
        }

    }

    public function test1($a=[],$xx=0)
    {
        die('屏蔽测试');
        set_time_limit(900);
        $data = [
            "ui_id"=>41913,
            "mi_id"=>1,
            "type"=>2,
            "price"=>'0.3466',
            "total"=>'230.7656',
            "decimal"=>'230.7656',
            "fee"=>"0.1000000000000",
            "create_time"=>1543990850,
            "update_time"=>1543990850,
            "microS"=>"0.12529300",
            "status"=>1,
            "order_no"=>"945062655c076e421c2f94.16225481",
            "limit_market"=>1
        ];
        $key = self::$hKey .'test';
        $effectiveBuys = [];
        $redis = Redis::instance();

        $suf = self::$hKeyBuy; //$hKeyBuy   $hKeySell
        for ($i=1;$i<=100000;++$i){
            list($microS, $timeS) = explode(' ', microtime());
            $data['create_time'] = $timeS;
            $data['microS'] = $microS;
//            $hField = $data['ui_id'] . '_' . $data['create_time'] . '_' . $data['microS'];
            $hField = $data['order_no'];
            $redis->hSet($key .$suf,$hField,json_encode($data));

            $tiqu = json_decode($redis->hGet($key .$suf,$hField),true);

//            $hField = $tiqu['ui_id'] . '_' . $tiqu['create_time'] . '_' . $tiqu['microS'];
            $hField = $tiqu['order_no'];
            $tradeData = $redis->hExists($key . $suf, $hField);
            if (!$tradeData){
                $effectiveBuys[$hField] = $tiqu;
            }
//            usleep(1);
        }
        var_dump($effectiveBuys);
        die('测试数据写入Redis出错!');
        $i = 0;
        foreach ($a as $k => $value){

            if ($xx == 1){
                echo $value.'|';
            }else{
                echo $value.'--';
            }
            unset($a[$k]);
            ++$i;
            if ($i == 3){
                $this->test1($a,1);
            }
        }
    }

    public function data()
    {
        $a = range(0,20);

    }
}
