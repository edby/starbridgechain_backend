<?php

class DB
{
    private static $conn = null;

    private function __construct(){}

    private function __clone(){}

    public static function getInstance($CFG)
    {
        if(self::$conn === null){
            if(empty($CFG)){
                include_once dirname(__FILE__).'/cfg.php';
//                $CFG = new cfgObject();
            }
            self::$conn = db_connect($CFG->dbhost, $CFG->dbname, $CFG->dbuser, $CFG->dbpass);
        }
        return self::$conn;
    }
}
