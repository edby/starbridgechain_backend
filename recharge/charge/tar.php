<?php 

$data = ['hash'=>'ff3e4b30f03d20a8bb551ad9779045ea33771d8575b7ddf00bef793abec897a6','ci_id'=>2,'istest'=>1];
$client = new swoole_client(SWOOLE_SOCK_TCP);
$client->connect('127.0.0.1', 9501, 0.5);
$client->send(json_encode($data));
$client->close();
