<?php 

$data = [
    'ui_id'         =>   1,
    'mi_id'         =>   1,
    'create_time'   =>   1539653989,
    'microS'        =>   '0.39431600',
    'type'          =>   1,
    'price'         =>   1000,
    'total'         =>   10,
    'decimal'       =>   10,
    'fee'           =>   '0.100000000000000',
    'status'        =>   1,
    'limit_market'  =>   1,
    'order_no'      =>   '15396539895bc541657275c0.50117736',
];
$restingOrderData = [
    'type'      =>  'restingOrder',
    'data'      =>  $data,
];
$client = new swoole_client(SWOOLE_SOCK_TCP);
$client->connect('127.0.0.1', 9595, 0.5);
$client->send(json_encode($restingOrderData));
$client->close();
