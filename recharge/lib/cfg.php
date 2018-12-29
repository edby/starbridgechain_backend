<?php 
class cfgObject{}
$CFG = new cfgObject();
//redis配置
$CFG->redis_host = "127.0.0.1";
$CFG->redis_port = "6379";
$CFG->redis_pwd = "";

//数据库配置
$CFG->dbhost = "127.0.0.1";
$CFG->dbname = "gh_market";
$CFG->dbuser = "root";
$CFG->dbpass = "gehua1108";

//钱包业务接口域名
$CFG->wallet_host = "http://apiwallet.starbridgechain.com";
