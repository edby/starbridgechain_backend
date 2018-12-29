<?php
/**
 * Created by PhpStorm.
 * User: gh
 * Date: 2018/5/17
 * Time: 9:59
 */
namespace app\admin\controller;
use think\Config;
use think\Controller;

class Admin extends Controller{

    function __construct(){
        parent::__construct();
    }


    protected function saveBase64Img($imgdata,$type){

    	$root = PUBLIC_PATH;
    	$path = '/upload/' . $type;

	    //匹配出图片的格式
	    if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $imgdata, $result)){
	        $img_type = strtolower($result[2]);

	        if(!in_array($img_type,["jpg","jpeg","png"])) return false;

	        $all_path = $root.$path;
	        if(!file_exists($all_path)){
	            //检查是否有该文件夹，如果没有就创建，并给予最高权限
	            mkdir($all_path, 0700,true);
	        }
	        $filename = md5(substr($imgdata,0,1024)).".{$img_type}";

	        if (file_put_contents($all_path .'/'. $filename , base64_decode(str_replace($result[1], '', $imgdata)))){
	            return $path . '/'. $filename;
	        }else{
	            return false;
	        }
	    }else{
	        return false;
	    }

	}



	protected function checkInputArg($str,$type,$arg){

		if(empty($str)) return false;


		if($type == "int"){
			if(preg_match("^\d+$", $str)) return true;
		}

		if($type == "float"){
			if(preg_match("^\d+(\.\d+)?$", $str)) return true;
		}

		if($type == "en"){
			if(preg_match('/^[A-Za-z]+$/', $str)) return true;
		}

		if($type == "path"){
			if(preg_match('/[A-Za-z0-9|"\/"|.]+/', $str)) return true;
		}

		if($type == "url"){
			if(preg_match('/[a-zA-z]+:\/\/[^\s]*/', $str)) return true;
		}

		ouputJson(205,"请求参数格式错误,[{$arg}]必须为[{$type}]");

	}


















}