<?php

class MySwoole{
    static private $client = null;
    static private $timer = null;


    /**
     * 私有的构造方法
     */
    public function __construct()
    {
    }


    /**
     * 私有的克隆方法
     * @return [type] [description]
     */
    public function __clone()
    {
    }


    /**
     * 获取cilent实例的方法
     * @return [type] [description]
     */
    public static function getInstance()
    {
        if (self::$client == null){
            self::$client = new swoole_client(SWOOLE_SOCK_TCP | SWOOLE_KEEP);
            if (!self::$client->connect('127.0.0.1',9909,1)){
                $msg = date('Y-m-d H:i:s').":连接服务失败!";
                save_log($msg);
            }else{
                //发送心跳
                if (self::$client->send('heartbeat')){
                    if (self::$timer != null){
                        swoole_timer_clear(self::$timer);
                    }
                    $client = self::$client;
                    swoole_timer_tick(5000,function($timer) use ($client){
                        $client->send('heartbeat');
                        self::$timer = $timer;
                    });
                }else{
                    $msg = date('Y-m-d H:i:s').":连接服务失败!";
                    save_log($msg);
                }
            }
        }
        return self::$client;
    }


    /**
     * 发送数据的方法
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public static function send($data)
    {
        if (!self::getInstance()->send(json_encode($data))){
            $msg = date('Y-m-d H:i:s').":发送数据失败!";
            save_log($msg);
        }
    }

    /**
     * 写入错误日志的方法
     * @param $msg
     */
    public function save_log($msg){
        $filename = "robot_status.log";
        file_put_contents($filename,$msg,FILE_APPEND);
    }

}