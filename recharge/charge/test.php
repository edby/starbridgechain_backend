<?php 

include_once (dirname(dirname(__FILE__)))."/lib/cfg.php";
include_once (dirname(dirname(__FILE__)))."/lib/dblib.php";
include_once (dirname(dirname(__FILE__)))."/lib/common.php";
include_once (dirname(dirname(__FILE__)))."/lib/Bitcoin.php";
include_once "./Business.php";

//USDT
$rpcinfo = '{"proto":"http","host":"122.225.58.113","account":"omnicorerpc","password":"5hMTZI9iBGFqKxsWfOUF","port":"8332","wallet_password":"123456"}';


$usdtinfo = new BitcoinInfo($rpcinfo);
$hash = "1ad9210de185847c7f4bf663622338adeea17b1117bbf70832e900c6e2387bb0";
$transaction = $usdtinfo->getTransactionForOmni($hash);
dump($transaction);

