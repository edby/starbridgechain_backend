<?php


namespace app\admin\controller;
use think\Config;
use app\admin\controller\Admin;

class RobotXlsManager extends Admin{


    function __construct(){
        parent::__construct();
    }





    //列表
    public function list(){

        $list = db("coin_info")
                //->field("ci_id,name,short_name,logo,fee,show_fee,status")
                ->field("ci_id,name,short_name,intro,release_time,release_total,circulate_total,crowd_funding,white_paper,website,blockchain,logo,fee,show_fee")
                ->select();

        if($list){
            ouputJson(200,'获取成功',$list);
        }else{
            ouputJson(201,'获取失败');
        }



    }



    //详情
    public function info(){

        $ci_id = $this->request->param("ci_id");

        //编辑信息
        if($this->request->isPost()){

            $uData["name"] = $this->request->param("name");

            if( $this->checkInputArg($this->request->param("short_name"),"en","short_name") ) 
                $uData["short_name"] = $this->request->param("short_name");
            
            $uData["intro"] = $this->request->param("intro");
            $uData["release_time"] = $this->request->param("release_time");
            $uData["release_total"] = $this->request->param("release_total");
            $uData["circulate_total"] = $this->request->param("circulate_total");
            $uData["crowd_funding"] = $this->request->param("crowd_funding");

            if( $this->checkInputArg($this->request->param("white_paper"),"url","white_paper") ) 
                $uData["white_paper"] = $this->request->param("white_paper");

            if( $this->checkInputArg($this->request->param("website"),"url","website") ) 
                $uData["website"] = $this->request->param("website");

            if($this->checkInputArg($this->request->param("blockchain"),"url","blockchain"))
                $uData["blockchain"] = $this->request->param("blockchain");

            if($this->checkInputArg($this->request->param("logo"),"path","logo"))
                $uData["logo"] = $this->request->param("logo");

            $uData["fee"] = $this->request->param("fee");
            $uData["show_fee"] = $this->request->param("show_fee");

            if(!$ci_id){  //新增

                if(!$uData["short_name"]) ouputJson(206,"币种名称不能为空.");

                $info = db("coin_info")->where(["short_name"=>$uData["short_name"]])->find();
                if($info) ouputJson(205,"{$uData['short_name']}币种已经存在，请勿重复添加.");

                $list = db("coin_info")
                    ->insert($uData);

                if($list !== false){
                    ouputJson(200,'新增成功');
                }else{
                    ouputJson(201,'新增失败');
                }

            }else{   //编辑

                if(in_array($ci_id, [1,2,3,4])) ouputJson(201,'测试仅能修改5');

                $list = db("coin_info")
                    ->where(["ci_id"=>$ci_id])
                    ->update($uData);

                if($list !== false){
                    ouputJson(200,'编辑成功');
                }else{
                    ouputJson(201,'编辑失败');
                }

            }


        }



    }








}