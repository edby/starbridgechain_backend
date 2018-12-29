<?php
class Business{
	//查询币种信息
	public static function query_coin_info($conn,$ci_id){
		$sql = "SELECT ci.ci_id,ci.short_name as coin_name,ci.withdraw_address,ci.settxfee,ci.coin_type,ci.confirmations,ci.rpcinfo,cf.single_minlimit,cf.pointnum FROM coin_info as ci LEFT JOIN coin_feeconfig as cf ON ci.ci_id=cf.ci_id WHERE ci.ci_id={$ci_id} AND cf.fee_type=2 AND ci.`status`=1;";
		$result = db_query_array($conn,$sql);
		if(!empty($result)){
			return $result[0];
		}else{
			return [];
		}
	}
	//根据订单号查询提现数据
	public static function query_withorder($conn,$orderno){
		$sql = "SELECT ct_id,ui_id,ci_id,from_address,to_address,fee,feeval as amount,pointnum,orderno FROM coin_downapply WHERE orderno='{$orderno}' AND `status`=1;";
		$result = db_query_array($conn,$sql);
		return $result;
	}

	public static function get_eth_transaction($rpcinfo,$tx_id){
		$rpcinfo = json_decode($rpcinfo,true);
	    $url = "{$rpcinfo['proto']}://{$rpcinfo['host']}:{$rpcinfo['port']}";
	    $param['jsonrpc'] = "2.0";
	    $param['method'] = "eth_getTransactionByHash";
	    $param['params'] = array("{$tx_id}");
	    $param['id'] = rand(1,100);
	    $aHeader[] = "Content-Type:application/json";
	    $data = json_decode(inter_post_ssl($url,json_encode($param),30,$aHeader),true);
	    return $data;
	}

	public static function get_eth_coinbase_balance($rpcinfo,$address){
		$rpcinfo = json_decode($rpcinfo,true);
	    $url = "{$rpcinfo['proto']}://{$rpcinfo['host']}:{$rpcinfo['port']}";
	    $param['jsonrpc'] = "2.0";
	    $param['method'] = "eth_getBalance";
	    $param['params'] = array("{$address}","latest");
	    $param['id'] = rand(1,100);
	    $aHeader[] = "Content-Type:application/json";
	    $data = json_decode(inter_post_ssl($url,json_encode($param),30,$aHeader),true);
	    dump($data);
	    if(isset($data['result']) && $data['result']!=""){
	        return hexdec($data['result']);
	    }else{
	        return 0;
	    }
	}
	//获取发币钱包地址的nonce值
	public static function get_eht_nonce($rpcinfo,$address){
		$rpcinfo = json_decode($rpcinfo,true);
	    $url = "{$rpcinfo['proto']}://{$rpcinfo['host']}:{$rpcinfo['port']}";
	    $param['jsonrpc'] = "2.0";
	    $param['method'] = "eth_getTransactionCount";
	    $param['params'] = array("{$address}","pending");
	    $param['id'] = rand(1,100);
	    $aHeader[] = "Content-Type:application/json";
	    $data = json_decode(inter_post_ssl($url,json_encode($param),30,$aHeader),true);
	    if(isset($data['result']) && $data['result']!=""){
	        return hexdec($data['result']);
	    }else{
	        return 0;
	    }
	}

	//发起eth离线交易
	public static function send_transaction($rpcinfo,$trnasaction){
		$rpcinfo = json_decode($rpcinfo,true);
	    $url = "{$rpcinfo['proto']}://{$rpcinfo['host']}:{$rpcinfo['port']}";
	    $trnasaction['gas'] = "0x".dechex($rpcinfo['gas']);
	    $trnasaction['gasPrice'] = "0x".dechex($rpcinfo['gasPrice']);
	    $param['jsonrpc'] = "2.0";
	    $param['method'] = "eth_sendTransaction";
	    $param['params'] = array($trnasaction);
	    $param['id'] = rand(1,100);
	    $aHeader[] = "Content-Type:application/json";
	    $data = json_decode(inter_post_ssl($url,json_encode($param),30,$aHeader),true);
	    return $data;
	}
	//钱包解锁
	public static function wallet_unlock($rpcinfo,$address){
		$rpcinfo = json_decode($rpcinfo,true);
	    $url = "{$rpcinfo['proto']}://{$rpcinfo['host']}:{$rpcinfo['port']}";
	    $param['jsonrpc'] = "2.0";
	    $param['method'] = "personal_unlockAccount";
	    $param['params'] = array($address,$rpcinfo['wallet_password'],60);
	    $param['id'] = rand(1,100);
	    $aHeader[] = "Content-Type:application/json";
	    inter_post_ssl($url,json_encode($param),30,$aHeader);
	    // $data = json_decode(inter_post_ssl($url,json_encode($param),30,$aHeader),true);
	    // dump($data);
	}

}