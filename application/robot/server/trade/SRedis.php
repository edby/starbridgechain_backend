<?php

class SRedis
{
    private static $redis_host = "127.0.0.1";
    private static $redis_port = "6379";
    private static $redis_pwd = "U#rNFRkk3vuCKcZ5";

    private static $redis = null;

    private function __construct(){}

    private function __clone(){}

    /**
     * 获取redis对象
     * @return null|\SRedis
     */
    public static function instance()
    {
        if(!(self::$redis instanceof Redis)){
            self::$redis = new Redis();
            self::$redis->connect(self::$redis_host,self::$redis_port);
            //密码
            $password = self::$redis_pwd;
            if('' !== $password){
                self::$redis->auth($password);
            }
        }
        return self::$redis;
    }
}