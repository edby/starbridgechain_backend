<?php 
class cfgObject{
    //redis配置
    public $redis_host = "127.0.0.1";
    public $redis_port = "6379";
    public $redis_pwd = "U#rNFRkk3vuCKcZ5";//U#rNFRkk3vuCKcZ5

    //数据库配置
    public $dbhost = "127.0.0.1";
    public $dbname = "gh_market";
    public $dbuser = "root";
    public $dbpass = "gehua1108";
}
$CFG = new cfgObject();
