<?php 

include_once (dirname(dirname(__FILE__)))."/lib/cfg.php";
include_once (dirname(dirname(__FILE__)))."/lib/dblib.php";
include_once (dirname(dirname(__FILE__)))."/lib/common.php";
include_once (dirname(dirname(__FILE__)))."/lib/Bitcoin.php";
include_once dirname(__FILE__)."/Business.php";


$serv = new swoole_server("127.0.0.1", 9521);
$serv->set(array('worker_num' => 4,'task_worker_num' => 1,'daemonize'=>1));

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
    $data = json_decode($data,true);
    if(empty($data) || !isset($data['orderno']) || $data['orderno']==""){
        return;
    }
    $conn = db_connect($CFG->dbhost,$CFG->dbname,$CFG->dbuser,$CFG->dbpass);
    $list = Business::query_withorder($conn,$data['orderno']);
    if(!empty($list)){
        //冻结提现记录 - 更改状态兑付中
        $suc = db_update($conn,'coin_downapply',[$data['orderno']],['status'=>4],['orderno']);
        if($suc > 0){
            $coinfo = [];
            $ci_id = 0;
            $totval = 0;
            foreach ($list as $k => $v) {
                $totval = bcadd($totval, $v['amount'],$v['pointnum']);
                if($ci_id == 0){
                    $ci_id = $v['ci_id'];
                }else{
                    if($ci_id != $v['ci_id']){
                        wirteLog("orderno:{$data['orderno']} | 提现记录中包含多笔不同类型的提现 - 拒绝提现");
                        return;
                    }
                }
            }
            $coinfo = Business::query_coin_info($conn,$ci_id);
            if(empty($coinfo)){
                wirteLog("orderno:{$data['orderno']} | ci_id:{$ci_id}| 未查询到币种信息");
                return;
            }
            if($coinfo['coin_type'] == 1){ //btc类型
                btc_withdtaw($conn,$coinfo,$list,$totval,$data['orderno']);
            }elseif($coinfo['coin_type'] == 2){ //eth类型
                eth_withdtaw($conn,$coinfo,$list,$totval,$data['orderno']);
            }else{
                wirteLog("orderno:{$data['orderno']} | ci_id:{$ci_id} | 不支持其他币种提现");
            }
        }else{
            wirteLog("orderno:{$data['orderno']} | ci_id:{$ci_id}| 更改兑付中状态失败");
        }
    }else{
        wirteLog("orderno:{$data['orderno']} | 查询提现记录不存在");
    }
}

function btc_withdtaw($conn,$coinfo,$list,$totval,$orderno){
    //查询btc账户余额是否足够
    $rpcinfo = json_decode($coinfo['rpcinfo'],true);
    if(isset($coinfo['settxfee']) && $coinfo['settxfee']!=""){
        $rpcinfo['settxfee'] = decimal_format($coinfo['settxfee'],$coinfo['pointnum'],false);
    }
    if($coinfo['withdraw_address']!=""){
        $btcinfo = new BitcoinInfo(json_encode($rpcinfo));
        if($rpcinfo['type'] == 'usdt'){
            $balanceInfo = $btcinfo->getBalanceForOmni($coinfo['withdraw_address'],intval($rpcinfo['propertyid']));
            // dump($balanceInfo);
            if(!empty($balanceInfo) && isset($balanceInfo['balance'])){
                $btc_balance = $balanceInfo['balance'];
            }else{
                $btc_balance = 0;
            }
        }else{
            $btc_balance = $btcinfo->getBalance($coinfo['withdraw_address']);    
        }

        $balanceLimit = bccomp($btc_balance, $totval,$coinfo['pointnum']);
        if($balanceLimit == 0 || $balanceLimit == 1){
            if($rpcinfo['type'] == 'usdt'){
                foreach ($list as $k => $v) {
                    $transaction_omni = [
                        'from'=>$coinfo['withdraw_address'],
                        'to'=>$v['to_address'],
                        'propertyid'=>intval($rpcinfo['propertyid']),
                        'val'=>strval(floatval($totval))
                    ];
                    $result = $btcinfo->sendTransactionForomni($transaction_omni);
                    if(isset($result['error']) || $result==""){
                        //交易失败
                        $failreason = "({$result['error']['code']}){$result['error']['message']}";
                        db_update($conn,'coin_downapply',[$orderno],['status'=>'6','fail_reason'=>$failreason,'updatetime'=>time()],['orderno']);
                    }else{
                        //兑付完成
                        db_update($conn,'coin_downapply',[$orderno],['status'=>'5','tx_hash'=>$result,'updatetime'=>time()],['orderno']);
                    }
                    // dump($result);
                }
                
            }else{
                //打包btc交易
                $trans = [];
                foreach ($list as $k => $v) {
                    $trans[$v['to_address']] = floatval($totval);
                }
                $btcinfo->settxfee();//设置交易手续费
                $btcinfo->walletpassphrase();//解锁账号
                //发起转账交易
                $result = $btcinfo->SendMany(json_decode(json_encode($trans)),$orderno);
                if(isset($result['error']) || $result==""){
                    //交易失败
                    $failreason = "({$result['error']['code']}){$result['error']['message']}";
                    db_update($conn,'coin_downapply',[$orderno],['status'=>'6','fail_reason'=>$failreason,'updatetime'=>time()],['orderno']);
                }else{
                    //兑付完成
                    db_update($conn,'coin_downapply',[$orderno],['status'=>'5','tx_hash'=>$result,'updatetime'=>time()],['orderno']);
                }
            }
            
        }else{
            //btc发币钱包账户余额不足
            db_update($conn,'coin_downapply',[$orderno],['status'=>'6','fail_reason'=>"发币钱包账户余额不足",'updatetime'=>time()],['orderno']);
        }
    }else{
        wirteLog("{$coinfo['coin_name']} | orderno:{$data['orderno']} | 未配置提现官方钱包");
    }
    
}

//eth提现/发币
function eth_withdtaw($conn,$coinfo,$list,$totval,$orderno){
    //获取eth发币钱包余额
    $eth_balance = Business::get_eth_coinbase_balance($coinfo['rpcinfo'],$coinfo['withdraw_address']);
    $eth_balance = decimal_format($eth_balance,$coinfo['pointnum'],false);
    $balanceLimit = bccomp($eth_balance, $totval,$coinfo['pointnum']);
    if($balanceLimit == 0 || $balanceLimit == 1){
        Business::wallet_unlock($coinfo['rpcinfo'],$coinfo['withdraw_address']);//解锁钱包60s
        foreach ($list as $k => $v) {
            $val = "0x".dechex(bcmul($totval,pow(10,9),$coinfo['pointnum']));
            $trans = [
                'from'=>$coinfo['withdraw_address'],
                'to'=>$v['to_address'],
                'value'=>$val
            ];
            $result = Business::send_transaction($coinfo['rpcinfo'],$trans);
            if(isset($result['result']) && $result['result']!=""){
                //提现提交成功
                db_update($conn,'coin_downapply',[$orderno],['status'=>'5','tx_hash'=>$result['result'],'updatetime'=>time()],['orderno']);
            }else{
                //提现提交失败
                $failreason = "({$result['error']['code']}){$result['error']['message']}";
                db_update($conn,'coin_downapply',[$orderno],['status'=>'6','fail_reason'=>$failreason,'updatetime'=>time()],['orderno']);
            }
        }
    }else{
        db_update($conn,'coin_downapply',[$orderno],['status'=>'6','fail_reason'=>"发币钱包账户余额不足",'updatetime'=>time()],['orderno']);
    }
}


function wirteLog($str){
    $date = date('Ymd');
    $filepath = dirname(dirname(dirname(__FILE__)))."/runtime/log/recharge/withdraw/withdraw_{$date}.log";
    $mkpath = dirname(dirname(dirname(__FILE__)))."/runtime/log/recharge/withdraw/";
    if(!is_dir($mkpath)){
        @mkdir($mkpath,0777,true);
    }
    $str = "[".date("Y-m-d H:i:s")."]".$str;
    $str .= PHP_EOL;
    file_put_contents($filepath,$str,FILE_APPEND);
}