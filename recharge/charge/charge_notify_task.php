<?php 

include_once (dirname(dirname(__FILE__)))."/lib/cfg.php";
include_once (dirname(dirname(__FILE__)))."/lib/dblib.php";
include_once (dirname(dirname(__FILE__)))."/lib/common.php";
include_once (dirname(dirname(__FILE__)))."/lib/Bitcoin.php";
include_once dirname(__FILE__)."/Business.php";

// $redis = new Redis();

// $redis->connect($CFG->redis_host, $CFG->redis_port);
// if($CFG->redis_pwd !=""){
//     $redis->auth($CFG->redis_pwd);
// }

$serv = new swoole_server("127.0.0.1", 9501);
$serv->set(array('worker_num' => 4,'task_worker_num' => 1,'daemonize'=>1));

//启动检查redis充值队列数据
$serv->on('start',function($server){
	echo "启动成功";
});


$serv->on('receive', function($serv, $fd, $from_id, $data) {
    $task_id = $serv->task($data);
});
$serv->on('task', function ($serv, $task_id, $from_id, $data) use ($CFG){
    
    handleTask($serv,$data,$CFG);

});
$serv->on('finish',function($serv, $task_id, $data){
	
});
$serv->start();

function handleTask($serv,$data,$CFG){
    try {
        $data = json_decode($data,true);
        $conn = db_connect($CFG->dbhost,$CFG->dbname,$CFG->dbuser,$CFG->dbpass);

        //查询是否出现币种重复hash
        $isextis = Business::query_hash_isexist($conn,$data['ci_id'],$data['hash']);
        if($isextis || $isextis==true){
            wirteLog("unkonw | hash:{$data['hash']} | ci_id:{$data['ci_id']} | 重复充值，已取消充值");
        }else{
            if(!empty($data)){
                $coinfo = Business::query_coin_info($conn,$data['ci_id']);

                if(!empty($coinfo)){
                    if($coinfo['coin_type'] == 1){ //btc类型
                        btc_info($coinfo,$data['hash'],$conn);
                    }elseif($coinfo['coin_type'] == 2){ //eth类型
                        eth_info($coinfo,$data['hash'],$conn);
                    }else{
                        //不支持的充币类型
                        wirteLog("unkonw | hash:{$data['hash']} | ci_id:{$data['ci_id']} | 不支持的充币类型。");
                    }
                }else{
                    wirteLog("unkonw | hash:{$data['hash']} | ci_id:{$data['ci_id']} | 币种不存在或不可用。");
                    //未查询到币种数据
                }
            }else{
                $nodata = json_encode($data);
                wirteLog("unkonw-nodata | 异步任务数据为空，data:{$nodata}。");
            }
        }
        
    } catch (Exception $e) {
        $ers = $e->getMessage();
        wirteLog("unkonw-nodata | 任务发起失败，error:{$ers}。");
    }
    
}

//BTC类型充值
function btc_info($coinfo,$hash,$conn){
    try {
        $rpcinfo = json_decode($coinfo['rpcinfo'],true);
        $btcinfo = new BitcoinInfo($coinfo['rpcinfo']);
        if($rpcinfo && isset($rpcinfo['type'])){

            if($rpcinfo['type'] == 'btc' || $rpcinfo['type'] == 'zec'){
                $transaction = $btcinfo->getTransaction($hash);
            }elseif($rpcinfo['type'] == 'usdt'){
                $result = $btcinfo->getTransactionForOmni($hash);
                if(!empty($result)){
                    $transaction['confirmations'] = $result['confirmations'];
                    $transaction['details'] = array([
                        'account'=>"",
                        'from_address'=>$result['sendingaddress'],
                        'address'=>$result['referenceaddress'],
                        'category'=>'receive',
                        'amount'=>$result['amount'],
                        'label'=>""
                    ]);
                }else{
                    $transaction = [];
                }
            }else{
                $transaction = [];
            }

            $ui_id = 0;
            $coin_name = $coinfo['coin_name'];
            if(!empty($transaction)){
                if($transaction['confirmations'] >= $coinfo['confirmations']){
                    foreach ($transaction['details'] as $detail) {
                        $chargeLog = [];
                        if($detail['category'] == 'receive'){
                            $single_minlimit = decimal_format($coinfo['single_minlimit'],$coinfo['pointnum'],false);
                            $comp = bccomp($detail['amount'],$single_minlimit,$coinfo['pointnum']);
                            if($comp==0 || $comp==1){

                                $userinfo = Business::query_address_info($conn,$detail['address'],$coinfo['ci_id']);
                                if(!empty($userinfo)){
                                    $ui_id = $userinfo['ui_id'];
                                    db_start_transaction($conn);//开启事务

                                    // $newbalance = bcadd($detail['amount'], decimal_format($userinfo['amount'],$coinfo['pointnum'],false),$coinfo['pointnum']);

                                    // $sucinx = db_update($conn,'user_finance',[$coinfo['ci_id'],$userinfo['ui_id']],['amount'=>$newbalance],['ci_id','ui_id']);

                                    $newbalance = $detail['amount'];
                                    $sucinx = updateUserBalance($userinfo['ui_id'],$coinfo['ci_id'],"recharge",[['field'=>'amount','type'=>'inc','val'=>$newbalance]],$conn);


                                    if($sucinx > 0){
                                        //添加充值记录
                                        $chargeLog['ui_id'] = $userinfo['ui_id'];
                                        $chargeLog['ci_id'] = $coinfo['ci_id'];
                                        $chargeLog['account'] = $userinfo['account'];
                                        if(isset($detail['from_address'])){
                                            $chargeLog['form_address'] = $detail['from_address'];    
                                        }
                                        $chargeLog['to_address'] = $detail['address'];
                                        $chargeLog['tx_hash'] = $hash;
                                        $chargeLog['amount'] = $detail['amount'];
                                        $chargeLog['uptime'] = time();
                                        $chargeLog['status'] = 1;
                                        $losuc = db_insert($conn,'coin_uprecord',$chargeLog);
                                        if($losuc > 0){
                                            db_commit($conn);
                                            delHashFile($coinfo['ci_id'],$hash);
                                            //充入成功 - 记录
                                            // wirteLog("btc | hash:{$hash} | ci_id:{$coinfo['ci_id']} | ui_id:{$ui_id} | 充值成功，充值数：{$transaction['details'][0]['amount']}");
                                        }else{
                                            //记录充值记录失败
                                            db_rollback($conn);
                                            wirteLog("{$coin_name} | hash:{$hash} | ci_id:{$coinfo['ci_id']} | ui_id:{$ui_id} | 充值成功记录失败");
                                            rechargeFail($conn,['ci_id'=>$coinfo['ci_id'],'ui_id'=>$ui_id,'coin_name'=>$coinfo['coin_name'],'account'=>$userinfo['account'],'to_address'=>$detail['address'],'val'=>$detail['amount'],'hash'=>$hash]);
                                        }
                                    }else{
                                        //更新数据库余额失败
                                        db_rollback($conn);
                                        wirteLog("{$coin_name} | hash:{$hash} | ci_id:{$coinfo['ci_id']} | ui_id:{$ui_id} | 账户余额更新失败,更新的余额为：{$newbalance}");
                                        rechargeFail($conn,['ci_id'=>$coinfo['ci_id'],'ui_id'=>$ui_id,'coin_name'=>$coinfo['coin_name'],'account'=>$userinfo['account'],'to_address'=>$detail['address'],'val'=>$detail['amount'],'hash'=>$hash]);
                                    }
                                }else{
                                    //未查询到地址对应的账户 - 币种关闭状态
                                    wirteLog("{$coin_name} | hash:{$hash} | ci_id:{$coinfo['ci_id']} | ui_id:{$ui_id} | 充值转入地址不存在系统用户绑定地址内。");
                                }

                            }else{
                                wirteLog("{$coin_name} | hash:{$hash} | ci_id:{$coinfo['ci_id']} | address:{$detail['address']} | 低于最低充值数量，充值数为：{$detail['amount']},要求最低充值数为：{$single_minlimit}。");
                            }
                        }
                    }
                }else{
                    //未达到确认数
                    editHashFile($coinfo['ci_id'],$hash);
                    wirteLog("{$coin_name} | hash:{$hash} | confirmations:{$transaction['confirmations']} | ci_id:{$coinfo['ci_id']} | 区块确认数未达要求。");

                }
            }else{
                //未查询到交易
                wirteLog("btc | hash:{$hash} | ci_id:{$coinfo['ci_id']} | 区块未查询到此笔交易。");
            }


        }else{
            wirteLog("unkonw-error | hash:{$hash} | ci_id:{$coinfo['ci_id']} | 配置文件错误，配置无type值。");
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        wirteLog("unkonw-error | hash:{$hash} | ci_id:{$coinfo['ci_id']} | 充值流程异常，error:{$error}。");
    }
}

function eth_info($coinfo,$hash,$conn){
    try {
        $transaction = Business::get_eth_transaction($coinfo['rpcinfo'],$hash);
        if(!empty($transaction) && isset($transaction['result']) && !empty($transaction['result'])){
            //判断区块数
            $confirmations = Business::get_block_number($coinfo['rpcinfo']) - hexdec($transaction['result']['blockNumber']);
            if($confirmations < $coinfo['confirmations']){
                editHashFile($coinfo['ci_id'],$hash);
                wirteLog("eth | hash:{$hash} | confirmations:{$confirmations} | ci_id:{$coinfo['ci_id']} | 区块确认数未达要求。");

            }else{
                $detail = $transaction['result'];
                $userinfo = Business::query_address_info($conn,$detail['to'],$coinfo['ci_id']);
                if(empty($userinfo)){
                    wirteLog("eth | hash:{$hash} | ci_id:{$coinfo['ci_id']} | 充值转入地址不存在系统用户绑定地址内。");
                }else{
                    $ui_id = $userinfo['ui_id'];
                    db_start_transaction($conn);//开启事务

                    $value = hexdec($detail['value']) / (pow(10, $coinfo['pointnum']));
                    $value = decimal_format($value,$coinfo['pointnum'],false);

                    // $amount = decimal_format($userinfo['amount'],$coinfo['pointnum'],false);
                    // $newbalance = bcadd($userinfo['amount'],$value,$coinfo['pointnum']);
                    $newbalance = $value;
                    // $sucinx = db_update($conn,'user_finance',[$coinfo['ci_id'],$userinfo['ui_id']],['amount'=>$newbalance],['ci_id','ui_id']);

                    $sucinx = updateUserBalance($userinfo['ui_id'],$coinfo['ci_id'],"recharge",[['field'=>'amount','type'=>'inc','val'=>$newbalance]],$conn);

                    if($sucinx > 0){
                        //添加充值记录
                        $chargeLog['ui_id'] = $userinfo['ui_id'];
                        $chargeLog['ci_id'] = $coinfo['ci_id'];
                        $chargeLog['account'] = $userinfo['account'];
                        $chargeLog['form_address'] = $detail['from'];
                        $chargeLog['to_address'] = $detail['to'];
                        $chargeLog['tx_hash'] = $hash;
                        $chargeLog['amount'] = $value;
                        $chargeLog['uptime'] = time();
                        $chargeLog['status'] = 1;
                        $losuc = db_insert($conn,'coin_uprecord',$chargeLog);
                        if($losuc > 0){
                            db_commit($conn);
                            delHashFile($coinfo['ci_id'],$hash);
                            intoHotWallet_ETH($coinfo,$detail['from'],$value);
                            //充入成功 - 记录
                            // wirteLog("btc | hash:{$hash} | ci_id:{$coinfo['ci_id']} | ui_id:{$ui_id} | 充值成功，充值数：{$transaction['details'][0]['amount']}");
                        }else{
                            //记录充值记录失败
                            db_rollback($conn);
                            
                            wirteLog("eth | hash:{$hash} | ci_id:{$coinfo['ci_id']} | ui_id:{$ui_id} | 充值成功记录失败");

                            rechargeFail($conn,['ci_id'=>$coinfo['ci_id'],'ui_id'=>$ui_id,'coin_name'=>$coinfo['coin_name'],'account'=>$userinfo['account'],'from_address'=>$detail['from'],'to_address'=>$detail['to'],'val'=>$value,'hash'=>$hash]);
                            
                        }
                    }else{
                        //更新数据库余额失败
                        db_rollback($conn);
                        wirteLog("eth | hash:{$hash} | ci_id:{$coinfo['ci_id']} | ui_id:{$ui_id} | 账户余额更新失败,更新的余额为：{$newbalance}");
                        rechargeFail($conn,['ci_id'=>$coinfo['ci_id'],'ui_id'=>$ui_id,'coin_name'=>$coinfo['coin_name'],'account'=>$userinfo['account'],'from_address'=>$detail['from'],'to_address'=>$detail['to'],'val'=>$value,'hash'=>$hash]);
                    }
                }
            }
        }else{
            wirteLog("eth | hash:{$hash} | ci_id:{$coinfo['ci_id']} | 区块未查询到此笔交易。");
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        wirteLog("unkonw-error | hash:{$hash} | ci_id:{$coinfo['ci_id']} | 充值流程异常，error:{$error}。");
    }
    
}

//转入热/官方钱包 - ETH
function intoHotWallet_ETH($coinfo,$from_address,$amount){
    if($coinfo['hotwallet_address']!=""){
        Business::wallet_unlock($coinfo['rpcinfo'],$from_address);//解锁钱包60s
        $val = "0x".dechex(bcmul($amount,pow(10,9),$coinfo['pointnum']));
        $trans = [
            'from'=>$from_address,
            'to'=>$coinfo['hotwallet_address'],
            'value'=>$val
        ];
        $result = Business::send_transaction($coinfo['rpcinfo'],$trans);
        if(isset($result['result']) && $result['result']!=""){
            //提现提交成功
            $data['ci_id'] = $coinfo['ci_id'];
            $data['from_address'] = $from_address;
            $data['to_address'] = $coinfo['hotwallet_address'];
            $data['amount'] = $amount;
            $data['status'] = 1;
            $data['creatime'] = time();
            $data['format_time'] = date('Y-m-d H:i:s');
            db_insert('hotwallet_intlogs',$data);
        }else{
            //提现提交失败
            $failreason = "({$result['error']['code']}){$result['error']['message']}";
            $data['ci_id'] = $coinfo['ci_id'];
            $data['from_address'] = $from_address;
            $data['to_address'] = $coinfo['hotwallet_address'];
            $data['amount'] = $amount;
            $data['status'] = 0;
            $data['fail_reason'] = $failreason;
            $data['creatime'] = time();
            $data['format_time'] = date('Y-m-d H:i:s');
            db_insert('hotwallet_intlogs',$data);
        }
    }
}
//转入热/官方钱包 - BTC
function intoHotWallet_BTC($coinfo,$from_address,$amount,$btcObj){

   
}

//充值写数据失败记录
function rechargeFail($conn,$data){
    $data['creatime'] = time();
    $data['format_time'] = date('Y/m/d H:i:s');
    $suc = db_insert($conn,'coin_uprecord_failog',$data);
}
//删除充值文件
function delHashFile($ci_id,$hash){
    $hash_file = __DIR__."/transaction/{$ci_id}_{$hash}.lock";
    if(file_exists($hash_file)){
        @unlink($hash_file);
    }
}
//修改充值文件
function editHashFile($ci_id,$hash){
    $hash_file = __DIR__."/transaction/{$ci_id}_{$hash}.lock";
    $new_hash_file = __DIR__."/transaction/{$ci_id}_{$hash}";
    if(file_exists($hash_file)){
        @rename($hash_file,$new_hash_file);
    }
}
//记录日志
function wirteLog($str){
    $date = date('Ymd');
    $filepath = dirname(dirname(dirname(__FILE__)))."/runtime/log/recharge/charge/charge_{$date}.log";
    $mkpath = dirname(dirname(dirname(__FILE__)))."/runtime/log/recharge/charge/";
    if(!is_dir($mkpath)){
        @mkdir($mkpath,0777,true);
    }
    $str = "[".date("Y-m-d H:i:s")."]".$str;
    $str .= PHP_EOL;
    file_put_contents($filepath,$str,FILE_APPEND);
}