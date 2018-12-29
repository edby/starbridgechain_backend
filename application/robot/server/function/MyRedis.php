<?php
/**
 * Class Redis
 * @package redis
 */
class MyRedis
{
    /**
     * redis对象
     * @var null
     */
    private static $redis = null;

    /**
     * Redis constructor.
     */
    private function __construct(){
        self::instance();
    }

    /**
     * clone
     */
    private function __clone(){}

    /**
     * 获取redis对象
     * @return null|\Redis
     */
    public static function instance($config)
    {
        $redis_config = $config;
        if(!self::$redis || !(self::$redis instanceof \Redis)){
            self::$redis = new \Redis();
            self::$redis->connect($redis_config['host'],$redis_config['port']);
            //密码
            $password = $redis_config['password'];
            if('' !== $password){
                self::$redis->auth($password);
            }
        }
        return self::$redis;
    }

}