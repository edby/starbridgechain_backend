<?php

namespace app\signin\model;
use think\Model;

class SignregModel extends Model
{
	protected $table = "user_info";
    protected $pk = 'ui_id';


    public static function queryInvcode($invcode = "",$query){
    	$userinfo = $query->where(['refer_code'=>$invcode])->find();
    	dump($userinfo);
    }
}
