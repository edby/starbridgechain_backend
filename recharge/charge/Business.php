<?php
class Business{

	//查询币种信息
	public static function query_coin_info($conn,$ci_id){
		$sql = "SELECT ci.ci_id,ci.short_name as coin_name,ci.coin_type,ci.confirmations,ci.hotwallet_address,ci.rpcinfo,cf.single_minlimit,cf.pointnum FROM coin_info as ci LEFT JOIN coin_feeconfig as cf ON ci.ci_id=cf.ci_id WHERE ci.ci_id={$ci_id} AND cf.fee_type=1 AND ci.`status`=1;";
		$result = db_query_array($conn,$sql);
		if(!empty($result)){
			return $result[0];
		}else{
			return [];
		}
	}
	

	//查询绑的地址用户信息
	public static function query_address_info($conn,$address,$ci_id){
		$sql = "SELECT cu.ci_id,cu.coinaddr,cu.ui_id,uf.amount,ui.account FROM coin_upuserbind as cu LEFT JOIN user_finance as uf ON cu.ui_id=uf.ui_id LEFT JOIN user_info as ui ON cu.ui_id=ui.ui_id WHERE cu.coinaddr='{$address}' AND cu.ci_id={$ci_id} AND uf.ci_id={$ci_id} AND uf.`status`=1;";
		$result = db_query_array($conn,$sql);
		if(!empty($result)){
			return $result[0];
		}else{
			return [];
		}
	}

	//查询hash是否充值过
	public static function query_hash_isexist($conn,$ci_id,$hash){
		$sql = "SELECT cuu_id FROM coin_uprecord WHERE ci_id={$ci_id} AND tx_hash='{$hash}';";
		$result = db_query_array($conn,$sql);
		if(!empty($result)){
			return true;
		}else{
			return false;
		}
	}

	/**
	 * 查询eth交易记录
	 * @return string 钱包地址
	 */
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
	/**
	 * 查询eth最新区块号
	 * @return string 钱包地址
	 */
	public static function get_block_number($rpcinfo){
	    $rpcinfo = json_decode($rpcinfo,true);
	    $url = "{$rpcinfo['proto']}://{$rpcinfo['host']}:{$rpcinfo['port']}";
	    $param['jsonrpc'] = "2.0";
	    $param['method'] = "eth_blockNumber";
	    $param['params'] = array();
	    $param['id'] = rand(1,100);
	    $aHeader[] = "Content-Type:application/json";
	    $data = json_decode(inter_post_ssl($url,json_encode($param),30,$aHeader),true);
	    if(isset($data['result']) && $data['result']!=""){
	        return hexdec($data['result']);
	    }else{
	        return 0;
	    }
	}

	//ETH钱包解锁
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


}