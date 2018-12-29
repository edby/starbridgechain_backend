<?php 
include_once (dirname(dirname(__FILE__)))."/lib/common.php";
$serv = new swoole_server("127.0.0.1",9701);
$serv->set(array('worker_num' => 1,'daemonize'=>1));

$serv->on("WorkerStart",function ($serv,$woker_id){
    if ($woker_id == 0){
        //每隔60s触发一次
        swoole_timer_tick(60000, function ($timer_id){
        	getTransaction();
        });
    }
});

$serv->on("receive",function ($serv,$fd,$from_id,$data){
    $serv->send($fd,"Server:".$data);
});

function getTransaction(){
	$transactions_dir = __DIR__."/transaction/";
	// dump($CFG);
	$transactions = scandir($transactions_dir);
	if (!$transactions) {
		return false;
	}
	foreach ($transactions as $t_id) {
		$pathinfo = pathinfo($transactions_dir.$t_id);
		if (!$t_id || $t_id == '.' || $t_id == '..' || (isset($pathinfo['extension']) && $pathinfo['extension'] == "lock")){
			continue;
		}
		$txinfo = explode('_', $t_id);
		if(!isset($txinfo[0]) || !isset($txinfo[1])){
			continue;
		}
		$ci_id = $txinfo[0];
		$tx_hash = $txinfo[1];
		if($ci_id!="" && $tx_hash!=""){
			//锁定文件
			$_path = iconv('utf-8', 'gb2312', $transactions_dir.$t_id);//旧文件名
			$_renamepath = iconv('utf-8', 'gb2312', $transactions_dir.$t_id.".lock");//新文件名
			if(rename($_path, $_renamepath)){
				$client = new swoole_client(SWOOLE_SOCK_TCP);
				$ret = $client->connect('127.0.0.1', 9501, 0.5);
				$client->send(json_encode(['hash'=>$tx_hash,'ci_id'=>$ci_id]));
				$client->close();
            }
		}
	}

}
$serv->start();