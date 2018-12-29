<?php


namespace app\admin\controller;
use think\Config;
use app\admin\controller\Admin;


//版块管理
class BlockManager extends Admin{


    function __construct(){
        parent::__construct();
    }



    //列表
    public function list(){

        $list = db("market_info")
                ->field("mi_id,ci_id_first,ci_id_second")
                ->select();

        if($list){

            $cilist = db("coin_info")->column("ci_id,short_name");

            foreach ($list as $key => $value) {
                $list[$key]["name"] = $cilist[$value["ci_id_first"]];
                $list[$key]["group"] = $cilist[$value["ci_id_second"]];
                unset($list[$key]["ci_id_first"]);
                unset($list[$key]["ci_id_second"]);
                unset($list[$key]["mi_id"]);
            }
            

            foreach ($list as $key => $value) {
                $mlist[$value["group"]][] = $value;
            }

            foreach ($mlist as $key => $value) {
                foreach ($value as $ckey => $cvalue) {
                    unset($mlist[$key][$ckey]["group"]);
                }
            }

            ouputJson(200,'获取成功',$mlist);

        }else{
            ouputJson(201,'获取失败');

        }


    }












}