<?php


namespace app\admin\controller;
use think\Config;
use app\admin\controller\Admin;


//模块开关控制
class ModuleSmanager extends Admin{


    function __construct(){
        parent::__construct();
    }



    //列表
    public function list(){

        $list = db("market_info")
                ->field("mi_id,ci_id_second,gswstatus,gswstatus_tlimit")
                ->group("ci_id_second")
                ->select();

        if($list){

            $cilist = db("coin_info")->column("ci_id,short_name");
            foreach ($list as $key => $value) {

                $list[$key]["group"] = $cilist[$value["ci_id_second"]];
                unset($list[$key]["ci_id_second"]);
                //unset($list[$key]["mi_id"]);
            }
            
             ouputJson(200,'获取成功',$list);
             
        }else{

            ouputJson(201,'获取失败');

        }


    }






    //详情
    public function info(){

        $md_name = $this->request->param("md_name");

        if(!$md_name) return json(["code"=>0,"data"=>[],"msg"=>"请求参数错误."]);

        //编辑信息
        if($this->request->isPost()){


            $gswstatus = $this->request->param("gswstatus");
            $gswstatus_tlimit = $this->request->param("gswstatus_tlimit");

            if(!$md_name){
                return json(["code"=>0,"data"=>[],"msg"=>"请求参数错误."]);
            }


            if(empty($gswstatus) && empty($gswstatus_tlimit)) return json(["code"=>0,"data"=>[],"msg"=>"请求参数错误."]);

            if(isset($gswstatus)){
                $gswstatus = $gswstatus?1:0;
                $uData["gswstatus"] = $gswstatus;
            }

            if(isset($gswstatus_tlimit)){
                $uData["gswstatus_tlimit"] = $gswstatus_tlimit;
            }


            $coin_info = db("coin_info")->where(["short_name"=>$md_name])->find();

            if(!$coin_info) return json(["code"=>0,"data"=>[],"msg"=>"获取模块信息失败."]);

            $list = db("market_info")
                ->where(["ci_id_second"=>$coin_info["ci_id"]])
                ->update($uData);

            if($list !== false){
                ouputJson(200,'更新成功');
            }else{
                ouputJson(201,'更新失败');
            }

        }



    }













}