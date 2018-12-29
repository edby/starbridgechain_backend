<?php

namespace app\signin\controller;
use think\Config;
use app\common\controller\Base;
use think\Db;
use app\common\service\Email;
class Emailc extends Base
{
	function __construct(){
        parent::__construct();
    }

    public function sendemail(){

        $res = Email::sendEmail1('1406317364@qq.com',2);
        dump($res);
    }   

}
