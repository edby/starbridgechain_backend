<?php 
class MysqlPool
{
    protected $pool;
    private $mysqlconf;
    public $alltime;

    public function __construct($mysqlconf)
    {
        $this->pool = new SplQueue();
        $this->mysqlconf = $mysqlconf;
        $this->alltime = 0;
    }

    public function put($mysql, $time = 0)
    {
        $this->pool->push($mysql);
        $this->alltime += $time;
    }

    public function get()
    {
        //有空闲连接
        if (count($this->pool) > 0) {
            return $this->pool->pop();
        }
		$mysql = new Swoole\Coroutine\Mysql();
        $res = $mysql->connect($this->mysqlconf);
        if ($res == false) {
            echo "\n connect error info: ".$mysql->error."\n";
            return false;
        } else {
            return $mysql;
        }
    }
}