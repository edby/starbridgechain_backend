<?php


namespace app\admin\controller;
use think\Config;
use app\admin\controller\Admin;

class TradeareaManager extends Admin{


    function __construct(){
        parent::__construct();
    }



    //币种列表
    public function cilist(){


        $list = db("coin_info")
                ->field("ci_id,short_name")
                ->select();

        if($list){
            ouputJson(200,'获取成功',$list);
        }else{
            ouputJson(201,'获取失败');
        }



    }



    //列表
    public function list(){

        $status = $this->request->param("status");
        $q = $this->request->param("key");

        if( $status == 2){
            $map = [];
        }else{
            $map["status"] = $status;
        }

        $list = db("market_info")
                //->field("mi_id,ci_id_first,ci_id_second,fee,show_fee,price_bit,amount_bit,amount_input_min,price_buy_max,price_buy_min,price_sell_max,price_sell_max,status")
                ->field("mi_id,ci_id_first,ci_id_second,fee,show_fee,price_bit,amount_bit,amount_input_min,price_buy_max,price_buy_min,price_sell_max,price_sell_min,rule_msg,apphome")
                ->where($map)
                ->select();


        if($list){

            $cilist = db("coin_info")->column("ci_id,short_name");

            foreach ($list as $key => $value) {
                $list[$key]["name"] = $cilist[$value["ci_id_first"]] . "/" . $cilist[$value["ci_id_second"]];
                unset($list[$key]["ci_id_first"]);
                unset($list[$key]["ci_id_second"]);  
                
            }

            if(!empty($q)){

                foreach($list as $key => $val){

                    if(strstr($val["name"],$q)){
                        $nList[] = $val;
                    }

                }

            }else{
                $nList = $list;
            }

            ouputJson(200,'获取成功',$nList);
        }else{
            ouputJson(200,'暂无数据');

        }



    }



    //详情
    public function info(){

        $mi_id = $this->request->param("mi_id");

        if(!$mi_id) return json(["code"=>0,"data"=>[],"msg"=>"请求参数错误."]);

        //编辑信息
        if($this->request->isPost()){

            if($this->request->has("fee")) $uData["fee"] = $this->request->param("fee");
            if($this->request->has("show_fee")) $uData["show_fee"] = $this->request->param("show_fee");
            if($this->request->has("price_bit")) $uData["price_bit"] = $this->request->param("price_bit");
            if($this->request->has("amount_bit")) $uData["amount_bit"] = $this->request->param("amount_bit");
            if($this->request->has("amount_input_min")) $uData["amount_input_min"] = $this->request->param("amount_input_min");
            if($this->request->has("price_buy_max")) $uData["price_buy_max"] = $this->request->param("price_buy_max");
            if($this->request->has("price_buy_min"))  $uData["price_buy_min"] = $this->request->param("price_buy_min");
            if($this->request->has("price_sell_max")) $uData["price_sell_max"] = $this->request->param("price_sell_max");
            if($this->request->has("price_sell_max")) $uData["price_sell_max"] = $this->request->param("price_sell_max");
            //$uData["status"] = $this->request->param("status");
            if($this->request->has("rule_msg")) $uData["rule_msg"] = $this->request->param("rule_msg");
            if($this->request->has("apphome")) $uData["apphome"] = $this->request->param("apphome")?1:0;


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