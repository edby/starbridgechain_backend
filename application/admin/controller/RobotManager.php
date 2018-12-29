<?php


namespace app\admin\controller;
use think\Config;
use app\admin\controller\Admin;

class RobotManager extends Admin{



    protected $column_ram_type =  [
                                    1 => "定时式",
                                    2 => "触发式",
                                    3 => "差价式",
                                    4 => "导入式"
                                ];

    protected $column_frequency =  [
                                    1 => "分钟",
                                    2 => "小时",
                                    3 => "天"
                                ];



    function __construct(){
        parent::__construct();
    }





    //列表
    public function list(){

        $list = db("robot_automotion")
                ->alias("a")
                //->field("ci_id,name,short_name,logo,fee,show_fee,status")
                ->field("a.ram_id,a.ram_name,a.ram_type,a.status,a.start_time,a.frequency,a.num,a.count,a.interval_time,a.interval_price,a.end_time,a.ram_remark,c.short_name as name1,d.short_name as name2")
                ->join("market_info b","b.mi_id = a.mi_id","left")
                ->join("coin_info c","b.ci_id_first = c.ci_id","left")
                ->join("coin_info d","b.ci_id_second = d.ci_id","left")
                ->select();

        foreach ($list as $key => $value) {
            # code...
            $list[$key]["ram_type_n"] = $this->column_ram_type[$value["ram_type"]];
            $list[$key]["ram_frequency_n"] = $this->column_frequency[$value["frequency"]];

            $list[$key]["market_name"] = "{$value['name1']}/{$value['name1']}";
        }


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