<?php


namespace app\admin\controller;
use think\Config;
use app\admin\controller\Admin;


//交易市场开关控制
class TradeareaSmanager extends Admin{


    function __construct(){
        parent::__construct();
    }



    //列表
    public function list(){

        $list = db("market_info")
                ->field("mi_id,ci_id_first,ci_id_second,swstatus,swstatus_tlimit")
                ->select();

        if($list){

            $cilist = db("coin_info")->column("ci_id,short_name");

            foreach ($list as $key => $value) {
                $list[$key]["name"] = $cilist[$value["ci_id_first"]] . "/" . $cilist[$value["ci_id_second"]];
                unset($list[$key]["ci_id_first"]);
                unset($list[$key]["ci_id_second"]);
            }

            ouputJson(200,'获取成功',$list);
        }else{
            ouputJson(201,'获取失败');

        }


    }






    //详情
    public function info(){

        $mi_id = $this->request->param("mi_id");

        if(!$mi_id) return ouputJson(201,'请求参数出错');

        //获取信息
        if($this->request->isGet()){

            $list = db("market_info")
                    ->field("mi_id,ci_id_first,ci_id_second,swstatus,swstatus_tlimit")
                    ->where(["mi_id"=>$mi_id])
                    ->find();

            if($list){

                $cilist = db("coin_info")->column("ci_id,short_name");

                $list["name"] = $cilist[$list["ci_id_first"]] . "/" . $cilist[$list["ci_id_second"]];
                unset($list["ci_id_first"]);
                unset($list["ci_id_second"]);

                ouputJson(200,'获取成功',$list);
            }else{
                ouputJson(201,'获取失败');

            }


        }





        //编辑信息
        
        if($this->request->isPost()){

            $uData["swstatus"] = $this->request->param("swstatus");
            $uData["swstatus_tlimit"] = $this->request->param("swstatus_tlimit");

            $list = db("market_info")
                ->where(["mi_id"=>$mi_id])
                ->update($uData);

            if($list !== false){
                ouputJson(200,'更新成功');
            }else{
                ouputJson(201,'更新失败');
            }

        }



    }













}