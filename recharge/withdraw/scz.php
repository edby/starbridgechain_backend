<?php 

$data = ['orderno'=>'71730530'];
$client = new swoole_client(SWOOLE_SOCK_TCP);
$client->connect('127.0.0.1', 9521, 0.5);
$client->send(json_encode($data));
$client->close();
