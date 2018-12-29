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
		$sql = "SELECT ct_id,ui_id,ci_id,from_address,to_address,fee,amount,pointnum,orderno FROM coin_downapply WHERE orderno='321456' AND `status`=1;";
		$result = db_query_array($conn,$sql);
		return $result;
	}
	public static function get_btc_balance($rpcinfo){
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
	    if(isset($data['result']) && $data['result']!=""){
	        return hexdec($data['result']);
	    }else{
	        return 0;
	    }
	}

}