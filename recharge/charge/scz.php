<?php 

$data = ['hash'=>'1ad9210de185847c7f4bf663622338adeea17b1117bbf70832e900c6e2387bb0','ci_id'=>3,'istest'=>1];
$client = new swoole_client(SWOOLE_SOCK_TCP);
$client->connect('127.0.0.1', 9501, 0.5);
$client->send(json_encode($data));
$client->close();
