<?php
include "./function/MySwoole.php";
include "./function/MyRedis.php";
include "./function/RSA.php";

/**
 * @return array
 */
function getConfig(){
    $config = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'password'=>'U#rNFRkk3vuCKcZ5',
        'prefix'=> 'gehua_',
    ];
    return $config;
}

/**
 * @return array
 */
function getRobotList(){
    $config = getConfig();
    $redis = MyRedis::instance($config);
    $redis->select(0);
    $robot_info = json_decode($redis->get('table_robot_autotrade'),true);

    $robot = [];
    foreach ($robot_info as $item) {
        if ($item['ram_type'] == 1 && $item['status'] == 1 && time() > $item['start_time'] && time() < $item['end_time']){
            if (checkMarketStatus($redis,$item['mi_id'])){
                $robot[] = $item;
            }
        }
    }

    return $robot;
}

/**
 * @param $redis
 * @param $mid
 * @return bool
 */
function checkMarketStatus($redis,$mid){
    $redis->select(0);
    $market_info = json_decode($redis->hget('hash_market_info',$mid),true);
    if($market_info){
        $t = time();
        $s1 = $market_info["swstatus"];
        $t1 = $market_info["swstatus_tlimit"];
        $s2 = $market_info["gswstatus"];
        $t2 = $market_info["gswstatus_tlimit"];
        $robot_lock = $market_info["robot_lock"];

        if($robot_lock) return false;

        if( ($t1 < $t && $s1 || $t1 > $t && !$s1) && ($t2 < $t && $s2 || $t2 > $t && !$s2) ){
            return true;
        }else{
            return false;
        }
    }else{
        $msg = date('Y-m-d H:i:s')."交易市场{$mid}信息不存在\n";
        save_log($msg);
        return false;
    }
}


/**
 * @param $num
 * @param $sum
 * @return array
 */
function getNum($num,$sum){
    if ($num <= 1){
        return array($sum);
    }
    for($i=0;$i<$num-1;$i++){
        $a[] = mt_rand(0,$sum);
    }
    sort($a);
    array_push($a,$sum);
    array_unshift($a,0);

    for ($i=$num;$i>0;$i--){
        if ($a[$i]-$a[$i-1] != 0){
            $b[] = $a[$i]-$a[$i-1];
        }
    }
    return $b;
}

/**
 * @param $robot_info
 */
function setRobotTime($robot_info){

    //获取redis 实例
    $config = getConfig();
    $redis = MyRedis::instance($config);

    $ram_id = $robot_info['ram_id'];//机器人ID
    $frequency = $robot_info['frequency'];//频率

    //获取设定的时间
    $redis->select(0);
    $time_info = json_decode($redis->hget('robot_timer',$ram_id),true);

    $now = time();
    if ($time_info == null || $time_info['next_update'] < $now){
        $time_info = [
            'next_update'=>$now + $frequency,
            'next_exec'=>$now + mt_rand(0,$frequency-1),
            'time'=>strtotime(date('Y-m-d H:i')),
            'robot_lock'=>1,
            'down'=>0,
            'up'=>0,
        ];
    }else{
        $time_info['next_update'] = $now + $frequency;
    }

    if ($now == $time_info['next_exec'] && $time_info['robot_lock'] == 1){

        $time_info['robot_lock'] = 0;
        $time_info['next_exec'] = $time_info['next_update'] + mt_rand(0,$frequency-1);

        try{
            $redis->hset('robot_timer',$ram_id,json_encode($time_info));
        }catch (exception $exception){
            $msg = date('Y-m-d H:i:s')."{$ram_id}:设置时间失败\n";
            save_log($msg);
        }

        //满足条件 执行方法
        $ids = json_decode($redis->get('robot_ids'),true);
        if (!empty($ids)){
            $time_info = randomData($redis,$robot_info,$time_info,$ids);
        }else{
            $msg = date('Y-m-d H:i:s')."{$ram_id}:没有获取到用户ID\n";
            save_log($msg);
        }
        //设置下次时间
        $redis->select(0);
        $time_info['robot_lock'] = 1;
        $redis->hset('robot_timer',$ram_id,json_encode($time_info));
    }

}

/**
 * @param $redis
 * @param $robot_info
 * @param $time_info
 * @param $ids
 */
function randomData($redis,$robot_info,$time_info,$ids){

    $mi_id = $robot_info['mi_id'];
    $market_info = json_decode($redis->hget('hash_market_info',$mi_id),true);
    $redis->select(2);
    $key = 'swoole:client:putup:';
    $buys = json_decode($redis->get($key .'buy:' .$mi_id),true);
    $sells = json_decode($redis->get($key .'sell:' .$mi_id),true);
    if (!empty($buys) && !empty($sells)){
        $buy1 = array_shift($buys);
        $sells1 = array_shift($sells);
        $arr_num = getNum($robot_info['count'],$robot_info['num']);
        $pow = $market_info['price_bit'];
        $down = $buy1['price']*pow(10,$pow)+1;
        $up = $sells1['price']*pow(10,$pow)-1;

        if ($up <= $down){
            $msg =  "买1:{$down},卖1:{$up}.价格错误!";
            save_log($msg);
            return $time_info;
        }

        //第一次 或者 挂单修改最高最低价
        if ($time_info['up'] > $up || $time_info['down'] < $down){
            $random_price = mt_rand($down,$up);
            $random_block = randomBlock($up,$down,$random_price);
            $time_info['up'] = $random_block['up'];
            $time_info['down'] = $random_block['down'];
        }

        //非第一次的 下一分钟的数据
        if (strtotime(date('Y-m-d H:i')) != $time_info['time']){
            $random_price = mt_rand($time_info['down'],$time_info['up']);
            $random_block = randomBlock($up,$down,$random_price);
            $time_info['up'] = $random_block['up'];
            $time_info['down'] = $random_block['down'];
            $time_info['time'] = strtotime(date('Y-m-d H:i'));
        }

        //获取随机最大值最小值
        $max = mt_rand($time_info['down'],$time_info['up']);
        $min = mt_rand($time_info['down'],$max);

        for ($i = 0;$i<count($arr_num);$i++){
            $low_price = mt_rand($min,$max)/pow(10,$pow);
            $high_price = mt_rand($low_price*pow(10,$pow),$max)/pow(10,$pow);
            $low_price = decimal_format($low_price,$pow,false);
            $high_price = decimal_format($high_price,$pow,false);
            $type1 = mt_rand(1,2);
            if ($type1 == 1){
                $type2  = 2;
                $price1 = $high_price;
                $price2 = $low_price;
            }else{
                $type2  = 1;
                $price1 = $low_price;
                $price2 = $high_price;
            }
            $id = $ids[array_rand($ids)];
            list($microS, $timeS) = explode(' ', microtime());
            $order_no = uniqid('rt'.mt_rand(100000,999999),true);
            $data1 = [
                'ui_id'=>$id,
                'mi_id'=>$mi_id,
                'type'=>$type1,
                'price'=>$price1,
                'total'=>$arr_num[$i],
                'decimal'=>$arr_num[$i],
                'fee'=>0,
                'create_time'=>$timeS,
                'update_time'=>$timeS,
                'microS'=>$microS,
                'status'=>1,
                'order_no'=>$order_no,
                'limit_market'=>1
            ];
            $id = $ids[array_rand($ids)];
            list($microS, $timeS) = explode(' ', microtime());
            $order_no = uniqid('rt'.mt_rand(100000,999999),true);
            $data2 = [
                'ui_id'=>$id,
                'mi_id'=>$mi_id,
                'type'=>$type2,
                'price'=>$price2,
                'total'=>$arr_num[$i],
                'decimal'=>$arr_num[$i],
                'fee'=>0,
                'create_time'=>$timeS,
                'update_time'=>$timeS,
                'microS'=>$microS,
                'status'=>1,
                'order_no'=>$order_no,
                'limit_market'=>1
            ];
            $data[] = ['data1'=>$data1, 'data2'=>$data2,];
        }
        $data['ram_id'] = $robot_info['ram_id'];
        //发送数据
        MySwoole::send($data);
        //返回数据
        return $time_info;
    }else{
        $msg = date('Y-m-d H:i:s').":{$robot_info['ram_id']}获取价格为空!\n";
        save_log($msg);
    }

}

/**
 * @param $number
 * @param $n
 * @param bool $isRepate
 * @param int $type
 * @return string
 */
function decimal_format($number, $n, $isRepate = true, $type = 0){
    if ($type == 2) {//进1
        $p = pow(10, $n);
        $number = ceil($number * $p) / $p;
    } elseif ($type == 3) {//舍去
        $p = pow(10, $n);
        $number = floor($number * $p) / $p;
    } else {
        $p = pow(10, $n);
        $number = round($number * $p) / $p;
    }
    if ($isRepate == TRUE) {
        return sprintf('%.' . $n . 'f', $number);
    } else {
        if(stripos($number, "e") || stripos($number, "E")){ //处理科学计数法
            return strval(str_replace(',', '', sctonum($number,$n)));
        }else{
            return strval($number);
        }
    }
}

/**
 * @param $msg
 */
function save_log($msg){
    $filename = "robot_status.log";//robot_status.log
    file_put_contents($filename,$msg,FILE_APPEND);
}

function get_excel_robot(){
    $config = getConfig();
    $redis = MyRedis::instance($config);
    $redis->select(0);
    $excel_info = $redis->hget('hash_robot_autotrade',time().":*");
}

function getToken($str){

    $rsa = new MyRsa\RSA();
    //加密
    $res = $rsa->encrypt_pub($str);

    if ($res != false){

        //调用登录接口 获取token
        $url = 'https://apissl.starbridgechain.com:9001/signinreg/login';

        $result = curl_post($url,['login_rsaiv'=>$res]);

        $result = json_decode($result);
        if ($result->status == 200){
            return $result->data->token;
        }else{
            return false;
        }
    }

}


/**
 * post方式发送
 * @param $url
 * @param $data
 * @param array $request_header
 * @return mixed
 */
function curl_post($url,$data,array $request_header = [])
{
    $ch = curl_init();

    if(substr($url,0,5)=='https') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);  // 从证书中检查SSL加密算法是否存在
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_URL, $url);

    if( count($request_header) >= 1 ){
        curl_setopt($ch, CURLOPT_HTTPHEADER, $request_header);
    }

    if (!empty($data)){
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    $output = curl_exec($ch);
    $error  = curl_errno($ch);
    curl_close($ch);
    if(0 !== $error){
        return false;
    }
    return $output;
}

//随机区间数据
function randomBlock($up,$down,$price){
    $mid = $up-$down;
    $proportion1 = randomProportion();
    $proportion2 = randomProportion();
    $block1 = ceil($mid/$proportion1);
    $block2 = ceil($mid/$proportion2);
    $block1 >= $block2 ? $block = mt_rand($block2,$block1) : $block = mt_rand($block1,$block2);

    $block_up = mt_rand($price,$price+$block);

    if ($block_up > $up){
        $block_up = $up;
    }

    $block_up - $block > $down ? $block_down = $block_up - $block : $block_down = $down;

    if ($block_up >= $block_down){
        $b_up = $block_up;
        $b_down = $block_down;
    }else{
        $b_up = $block_down;
        $b_down = $block_up;
    }

    //返回数据
    return ['up'=>$b_up,'down'=>$b_down];
}


//随机比例
function randomProportion(){
    $pro = [
        '2' =>10,
        '3' =>20,
        '4' =>30,
        '5' =>40,
        '6' =>50,
        '7' =>60,
        '8' =>70,
        '9' =>80,
        '10' =>90,
    ];
    $ret = '';
    $sum = array_sum($pro);
    foreach($pro as $k=>$v)
    {
        $r = mt_rand(1, $sum);
        if($r <= $v)
        {
            $ret = $k;
            break;
        }else{
            $sum = max(0, $sum - $v);
        }
    }
    return $ret;
}