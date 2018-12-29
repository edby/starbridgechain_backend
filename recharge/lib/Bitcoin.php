<?php

require_once "easybitcoin.php";
class BitcoinInfo{
	public $bitcoin = null;
	public $rpcinfo = null;

	public function __construct($rpcinfo){
		$rpcinfo = json_decode($rpcinfo,true);
		$this->rpcinfo = $rpcinfo;
		$this->bitcoin = new Bitcoin($rpcinfo['account'],$rpcinfo['password'],$rpcinfo['host'],$rpcinfo['port'],$rpcinfo['proto']);
	}
	//btc zec rpc方法
	public function getTransaction($hash) {
		$transaction = $this->bitcoin->gettransaction($hash);
		return $transaction;
	}
	
	public function getBalance($address){
		$balance = $this->bitcoin->getbalance($address);
		return $balance;
	}

	public function settxfee(){
		return $this->bitcoin->settxfee($this->rpcinfo['settxfee']);
	}

	public function walletpassphrase(){
		return $this->bitcoin->walletpassphrase($this->rpcinfo['wallet_password'],60);
	}

	public function SendMany($transactionList,$comment){
		$tx_id = $this->bitcoin->sendmany("",$transactionList,1,$comment);
		return $tx_id;

	}

	//usdt rpc方法
	public function getTransactionForOmni($hash) {
		$transaction = $this->bitcoin->omni_gettransaction($hash);
		return $transaction;
	}

	public function getBalanceForOmni($address,$propertyid){
		$balance = $this->bitcoin->omni_getbalance($address,$propertyid);
		return $balance;
	}

	public function sendTransactionForomni($transaction){
		$tx_id = $this->bitcoin->omni_send($transaction['from'],$transaction['to'],$transaction['propertyid'],$transaction['val']);
		return $tx_id;
	}
}