<?php


namespace app\home\controller;

use app\common\controller\AuthBase;
use redis\Redis;
use think\Db;
use think\Exception;
use think\facade\Log;

class Personal extends AuthBase{


    const BCPRECISEDIGITS = 13; //小数点精确位数

    private static $marketKey = 'hash_market_info'; //redis存储交易市场信息的key
    private static $hKeyBuy = '_buy';
    private static $hKeySell = '_sell';
    private static $buyType = 1; //挂买单类型
    private static $sellType = 2;

    private static $tradeStatus1 = 1; //挂单的状态 等待撮合
    private static $tradeStatus2 = 2; //挂单的状态 已撮合
    private static $tradeStatus3 = 3; //挂单的状态 已撤单
    private static $tradeStatus4 = 4; //挂单的状态 异常
    

    protected $market_ids;
    protected $record_num = 10;
    protected $putup_num = 5;
    protected $rds_db_no = 2;
    protected $page_per = 10;
    protected $market_arr = [];



    function __construct(){
        parent::__construct();
        $this->redis->select(2);
        error_reporting(E_ERROR | E_WARNING | E_PARSE);
        //header('Access-Control-Allow-Origin:*');
        //
        
    }


    //获取委托记录(个人中心)
    public function putupinfo_bak (){

        $mi_id = $this->request->param("mid");
        $page  = (int)$this->request->param("page");
        $year = $this->request->param("year");
        if(!$year) $year = date("Y");


        if($page < 1) $page=1;

        if(isset($mi_id)){

            $map["a.ui_id"] = $this->uid;

            if($mi_id){
                $map["a.mi_id"] = $mi_id;
            }

            if($this->request->param("type")){
                $map["a.type"] = $this->request->param("type");
            }

            if($this->request->param("status")){
                $map["a.status"] = $this->request->param("status");
            }


            $table = "market_trade{$year}_{$mi_id}";
            $exist = Db::query('show tables like "'.  $table .'"');
            if(!$exist) ouputJson(209,lang('PER_NO_DATA'),[]);

            $list = db($table)
                //->field("price,total,fee,create_time,status,order_no")
                ->alias("a")
                ->field("a.create_time as time,a.type,a.price,a.total,a.decimal,a.price as ave_price,a.fee,a.status,c.short_name as name1,d.short_name as name2,a.order_no,a.mi_id as mid,b.price_bit as pbit,b.amount_bit as abit")
                ->join("market_info b","b.mi_id = a.mi_id","left")
                ->join("coin_info c","b.ci_id_first = c.ci_id","left")
                ->join("coin_info d","b.ci_id_second = d.ci_id","left")
                ->where($map)
                ->order("a.mt_id desc")
                ->select();

            $list = $this->copyChange($list,true); //复制状态

            $order_arr = array_unique(array_column($list,"order_no"));

            $table = "market_trade_log{$year}_{$mi_id}";
            $exist = Db::query('show tables like "'.  $table .'"');
            if($exist) {

                $orderg_list = db($table)
                    ->field("amount,decimal,order_no,peer_order_no,type,buy_fee,sell_fee,price")
                    ->where('order_no|peer_order_no','IN',$order_arr)
                    ->select();

                if($orderg_list){

                    foreach ($orderg_list as $key => $value) {
                        $ordergc_list[$value["order_no"]]["ap"] += $value["amount"];  //主动方总额加
                        $ordergc_list[$value["order_no"]]["ac"] += $value["decimal"]; //主动方数量加

                        $ordergc_list[$value["peer_order_no"]]["ap"] += $value["amount"]; //被动方总额加
                        $ordergc_list[$value["peer_order_no"]]["ac"] += $value["decimal"]; //被动方总额减



                        if(!isset($ordergc_list[$value["order_no"]]["afp"])) $ordergc_list[$value["order_no"]]["afp"] = 0;
                        if(!isset($ordergc_list[$value["peer_order_no"]]["afp"])) $ordergc_list[$value["peer_order_no"]]["afp"] = 0;





                        if($value["type"] == 1) { //买
                            $ordergc_list[$value["order_no"]]["afp"] = bcadd($ordergc_list[$value["order_no"]]["afp"],$value["buy_fee"]);
                            $ordergc_list[$value["peer_order_no"]]["afp"] = bcadd($ordergc_list[$value["peer_order_no"]]["afp"],$value["sell_fee"]);
                        }else{
                            $ordergc_list[$value["order_no"]]["afp"] = bcadd($ordergc_list[$value["order_no"]]["afp"],$value["sell_fee"] );
                            $ordergc_list[$value["peer_order_no"]]["afp"] = bcadd($ordergc_list[$value["peer_order_no"]]["afp"],$value["buy_fee"] );
                        }




                    }

                    foreach ($ordergc_list as $key => $value) {
                        if($value["ac"]){
                            $ordergcok_list[$key]["ave_price"] = $value["ap"] / $value["ac"];
                            $ordergcok_list[$key]["all_price"] = $value["ap"];
                            $ordergcok_list[$key]["fee"] = $value["afp"];
                        }
                    }
                }else{
                    $ordergcok_list = [];
                }
            }else{
                $ordergcok_list = [];
            }


            //if( $this->request->param("ttt") ) var_dump($ordergcok_list);


            $pageinfo["count"] = count($list);
            $pageinfo["current"] = $page;
            $pageinfo["pagecount"] = ceil($pageinfo["count"] / $this->page_per);

            $list = array_slice($list,($page-1)*$this->page_per,$this->page_per);

            foreach ($list as $key => $value) {
                $list[$key]["turnover"] = $value["total"] - $value["decimal"];
                //unset($list[$key]["decimal"]);

                $list[$key]["mi_name"] = "{$list[$key]['name1']}/{$list[$key]['name2']}";

                //$list[$key]["all_price"] = $list[$key]["turnover"] * $list[$key]["price"];
                $list[$key]["all_price"] = $ordergcok_list[$value["order_no"]]["all_price"]?$ordergcok_list[$value["order_no"]]["all_price"]:0;
                $list[$key]["ave_price"] =  $ordergcok_list[$value["order_no"]]["ave_price"]?$ordergcok_list[$value["order_no"]]["ave_price"]:0;


                //format
                $list[$key]["price"] = $list[$key]["price"];
                $list[$key]["total"] =  $list[$key]["total"];


                if($value["type"] == 1){
                    $feetag = $list[$key]['name1'];
                }else{
                    $feetag = $list[$key]['name2'];
                }

                $fp =  $ordergcok_list[$value["order_no"]]["fee"]?$ordergcok_list[$value["order_no"]]["fee"]:0;
                //$list[$key]["fee"] = decimal_format($fp/100,20) . " " . $feetag;

                $list[$key]["fee"] = $fp . " " . $feetag;

                unset($list[$key]["name1"]);
                unset($list[$key]["name2"]);

            }

            $data["list"] = $list;
            $data["pageinfo"] = $pageinfo;



            ouputJson(200,lang("PER_GET_SUCCESS"),$data);


        }else{
            ouputJson(201,lang("PER_REQUEST_FAILED"));
        }



    }




    public function  putupinfo(){



        //请求
        $mi_id = (int)$this->request->param("mid");  //交易市场id
        $page  = (int)$this->request->param("page"); //页面
        $pex = (int)$this->request->param("pex"); //1当天2本周3本月4本年
        $type = (int)$this->request->param("type"); //类型筛选，1买2卖
        $status = (int)$this->request->param("status");//状态筛选，1:交易中  2:已完成  3:已撤销

        //全局变量
        $year = date("Y");
        $tto = time();
        $tto_tag = date("Ymd-His",$tto);


        switch ($pex) {
            case 1:
                $tform = strtotime("today");
                $tform_tag = "当天";
                break;
            case 2:
                $tform = strtotime(date('Y-m-d', strtotime("this week Monday", $tto))) ;
                $tform_tag = "本周";
                break;
            case 3:
                $tform = mktime(0, 0, 0, date('m'), 1, date('Y'));
                $tform_tag = "本月";
                break;
            case 4:
                $tform = mktime(0, 0, 0, 1, 1, date('Y'));
                $tform_tag = "本年";
                break;
            default:
                $tform = strtotime("today");
                $tform_tag = "当天";
        }


        //验证
        if($page < 1) $page=1;
        $limit_start = ($page -1) * $this->page_per;
        $limit_length = $this->page_per;

        if(!$mi_id) ouputJson(201,lang("PER_REQUEST_FAILED"));


        //拼接sql
        $map[] =  ["a.ui_id" , "=" , $this->uid];
        $map[] =  ["a.mi_id" ,  "=" ,$mi_id];
        $map[] =  ["a.create_time" , ">=" ,$tform];


        if($type) $map[] = ["a.type","=",$type] ;
        if($status) $map[] = ["a.status","=",$status];




        $table = "market_trade{$year}_{$mi_id}";
        $exist = Db::query('show tables like "'.  $table .'"');
        if(!$exist) ouputJson(209,lang('PER_NO_DATA'),[]);



        if($this->request->isGet()){



            $list = db($table)
                ->alias("a")
                ->field("a.mi_id as mid,a.create_time as time,a.type,a.price,a.total,a.decimal,a.status,c.short_name as name1,d.short_name as name2,a.order_no,b.price_bit as pbit,b.amount_bit as abit")
                ->join("market_info b","b.mi_id = a.mi_id","left")
                ->join("coin_info c","b.ci_id_first = c.ci_id","left")
                ->join("coin_info d","b.ci_id_second = d.ci_id","left")
                ->where($map)
                ->order("a.mt_id desc")
                ->select();

        }else{



            $list = db($table)
                ->alias("a")
                ->field("a.mi_id as mid,a.create_time as time,a.type,a.price,a.total,a.decimal,a.status,c.short_name as name1,d.short_name as name2,a.order_no,b.price_bit as pbit,b.amount_bit as abit")
                ->join("market_info b","b.mi_id = a.mi_id","left")
                ->join("coin_info c","b.ci_id_first = c.ci_id","left")
                ->join("coin_info d","b.ci_id_second = d.ci_id","left")
                ->where($map)
                ->order("a.mt_id desc")
                ->limit($limit_start,$limit_length)
                ->select();

        }




        $all_count = db($table)
            ->alias("a")
            ->field("a.mi_id as mid,a.create_time as time,a.type,a.price,a.total,a.decimal,a.status,c.short_name as name1,d.short_name as name2,a.order_no,b.price_bit as pbit,b.amount_bit as abit")
            ->join("market_info b","b.mi_id = a.mi_id","left")
            ->join("coin_info c","b.ci_id_first = c.ci_id","left")
            ->join("coin_info d","b.ci_id_second = d.ci_id","left")
            ->where($map)
            ->order("a.mt_id desc")
            ->count();


        $order_arr = array_unique(array_column($list,"order_no"));

        $table = "market_trade_log{$year}_{$mi_id}";
        $exist = Db::query('show tables like "'.  $table .'"');
        if($exist) {

            $orderg_list = db($table)
                ->field("create_time as time,type,price,decimal,amount,order_no,peer_order_no,buy_fee,sell_fee,status")
                ->where('order_no|peer_order_no','IN',$order_arr)
                ->select();

            if($orderg_list){

                foreach ($orderg_list as $key => $value) {

                    $ordergc_list[$value["order_no"]][] = $value;
                    $ordergc_list[$value["peer_order_no"]][] = $value;

                }


            }else{
                $ordergc_list = [];
            }
        }else{
            $ordergc_list = [];
        }

        $pageinfo["count"] = $all_count;
        $pageinfo["current"] = $page;
        $pageinfo["pagecount"] = ceil($all_count / $this->page_per);


        foreach ($list as $key => $value) {

            $order_id = $value["order_no"];
            $trade_type = $value["type"];
            $list[$key]["mname"] = "{$value['name1']}/{$value['name2']}";


            if($trade_type == 1){
                $feetag = $list[$key]['name1'];
                $fcll = "buy_fee";
            }else{
                $feetag = $list[$key]['name2'];
                $fcll ="sell_fee";
            }


            unset($list[$key]["name1"]);
            unset($list[$key]["name2"]);
            //unset($list[$key]["order_no"]); //不要删除交易记录，用于撤回


            //复制委托记录


            if($value["decimal"] > 0){

                $newPlog = $list[$key];
                $newPlog["dp"] = 1; //标识，2标识交易记录，1表示委托记录

                unset($newPlog["type"]);
                unset($newPlog["pbit"]);
                unset($newPlog["abit"]);
                unset($newPlog["mname"]);

                $list[$key]["plist"][] = $newPlog;
            }



            //合并交易记录
            $plist = $ordergc_list[$order_id];
            if($plist){


                $newRlog["dp"] = 2; //标识，2标识交易记录，1表示委托记录
                $newRlog["fee"] = 0;
                $newRlog["status"] = 1;
                $newPlog["decimal"] = 0;
                $newPlog["amount"] = 0;


                foreach ($plist as $pk => $pv){

                    $fee = $pv[$fcll];
                    $newRlog["fee"] =   bcadd($newRlog["fee"],$fee);
                    $newRlog["decimal"] = bcadd($newRlog["decimal"],$pv["decimal"]);
                    $newRlog["amount"] = bcadd($newRlog["amount"],$pv["amount"]);
                    $newRlog["time"] = $pv["time"];
                }

                $newRlog["fee"] = $newRlog["fee"] . " " .$feetag;
                $newRlog["price"] = $newRlog["amount"] / $newRlog["decimal"];

                $list[$key]["plist"][] = $newRlog;

            }

        }



        if($this->request->isGet()) {





            foreach ($list as $key => $pv) {

                //加入委托记录
                $pp = [
                    'time' => date("Y.m.d h:i:s", $pv["time"]),
                    'ttype' => $pv["type"] == 1 ? '买' : '卖',
                    'price' => $pv["price"],
                    'mname' => $pv["mname"],
                    'total' => $pv["total"]
                ];

                $out_data[] = $pp;


                if ($pv["plist"]) {
                    foreach ($pv["plist"] as $cv) {


                        //子委托记录
                        if ($cv["dp"] == 1) {


                            $cpt = [
                                'time' => "",
                                'ttype' => "时间",
                                'price' => "价格",
                                'mname' => "数量",
                                "total" => "状态"
                            ];

                            if ($cv["status"] = 1) {//1:交易中  2:已完成  3:已撤销  4:异常
                                $cv_status = "交易中";
                                $cv_count = $cv["decimal"];
                            }

                            if ($cv["status"] = 2) {//1:交易中  2:已完成  3:已撤销  4:异常
                                $cv_status = "已完成";
                                $cv_count = $cv["total"];
                            }

                            if ($cv["status"] = 3) {//1:交易中  2:已完成  3:已撤销  4:异常
                                $cv_status = "已撤销";
                                $cv_count = $cv["decimal"];
                            }

                            $cpb = [
                                'time' => "",
                                'ttype' => date("Y.m.d h:i:s", $cv["time"]),
                                'price' => $cv["price"],
                                'mname' => $cv_count,
                                "total" => $cv_status

                            ];


                            $out_data[] = $cpt;
                            $out_data[] = $cpb;


                        }


                        //子交易记录
                        if ($cv["dp"] == 2) {

                            $crt = [
                                'time' => "",
                                'ttype' => "时间",
                                'price' => "价格",
                                'mname' => "数量",
                                'total' => "状态",
                                'fee' => "成交额",
                                "cc" => "手续费"
                            ];


                            if ($cv["status"] = 0) {//0：未成交，1：成交
                                $cv_status = "未成交";
                                $cv_count = $cv["decimal"];
                            }

                            if ($cv["status"] = 1) {//0：未成交，1：成交
                                $cv_status = "已成交";
                                $cv_count = $cv["decimal"];
                            }


                            $crb = [
                                'time' => "",
                                'ttype' => date("Y.m.d h:i:s", $cv["time"]),
                                'price' => $cv["price"],
                                'mname' => $cv_count,
                                'total' => $cv_status,
                                'fee' => $cv["amount"],
                                "cc" => $cv["fee"]
                            ];


                            $out_data[] = $crt;
                            $out_data[] = $crb;


                        }


                    }


                }


            }

            $title = ['time' => '时间', 'ttype' => '买卖', 'price' => '价格', 'mname' => "交易对", "total" => "委托总量", "fee" => "", "cc" => ""];
            $filename = '委托记录-' . $tform_tag . '-' . $tto_tag;


            export_excel_zip($filename, $title, $out_data);
        }else{


            $data["list"] = $list;
            $data["pageinfo"] = $pageinfo;


            ouputJson(200,lang("PER_GET_SUCCESS"),$data);
        }
    }



    //获取委托记录（交易市场）
    public function mputupinfo (){


        $mi_id = $this->request->param("mid");
        $type  = $this->request->param("t");
        $year = $this->request->param("year");
        if(!$year) $year = date("Y");

        if(isset($mi_id) && in_array($type,["day","ing"]) ){

            //$this->uid = 5;

            $map[] = ["a.ui_id",'=',$this->uid];

            if($type == "day"){
                $tmin = strtotime('today');
                $map[] = ["a.update_time",">=",$tmin];
                $map[] = ["a.status",">",0];
                $map[] = ["a.status","<",4];
            }else{
            }

            if($type == "ing"){
                $map[] = ["a.status",'=',1];
            }

            $table = "market_trade{$year}_{$mi_id}";
            $exist = Db::query('show tables like "'.  $table .'"');

            if(!$exist) ouputJson(209,lang("PER_NO_DATA"),[]);

            $list = db($table)
                    //->field("price,total,fee,create_time,status,order_no")
                    ->alias("a")
                    ->field("a.create_time,a.type,a.price,a.total,a.decimal,a.fee,a.status,c.short_name as name1,d.short_name as name2,a.order_no,a.mi_id as mid,b.price_bit as pbit,b.amount_bit as abit")
                    ->join("market_info b","b.mi_id = a.mi_id","left")
                    ->join("coin_info c","b.ci_id_first = c.ci_id","left")
                    ->join("coin_info d","b.ci_id_second = d.ci_id","left")
                    ->where($map)
                    ->limit(9)
                    ->order("a.mt_id desc")
                    ->select();


            if($type == "day") {
                $list = $this->copyChange($list,false);
            }


            $order_arr = array_unique(array_column($list,"order_no"));

            $table = "market_trade_log{$year}_{$mi_id}";
            $exist = Db::query('show tables like "'.  $table .'"');
            if($exist) {

                 $orderg_list = db($table)
                               ->field("amount,decimal,order_no,peer_order_no,type,buy_fee,sell_fee,price")
                               ->where('order_no|peer_order_no','IN',$order_arr)
                               ->select();


                if($orderg_list){

                    foreach ($orderg_list as $key => $value) {
                        $ordergc_list[$value["order_no"]]["ap"] += $value["amount"];
                        $ordergc_list[$value["order_no"]]["ac"] += $value["decimal"];
                        $ordergc_list[$value["peer_order_no"]]["ap"] += $value["amount"];
                        $ordergc_list[$value["peer_order_no"]]["ac"] += $value["decimal"];

                        if(!isset($ordergc_list[$value["order_no"]]["afp"])) $ordergc_list[$value["order_no"]]["afp"] = 0;
                        if(!isset($ordergc_list[$value["peer_order_no"]]["afp"])) $ordergc_list[$value["peer_order_no"]]["afp"] = 0;


                    /* if($value["type"] == 1) { //买
                            $ordergc_list[$value["order_no"]]["afp"] = bcadd($ordergc_list[$value["order_no"]]["afp"],$value["buy_fee"] *  $value["decimal"]);
                            $ordergc_list[$value["peer_order_no"]]["afp"] = bcadd($ordergc_list[$value["peer_order_no"]]["afp"],$value["sell_fee"] * $value["decimal"] * $value["price"]);
                        }else{
                            $ordergc_list[$value["order_no"]]["afp"] = bcadd($ordergc_list[$value["order_no"]]["afp"],$value["sell_fee"] * $value["decimal"] * $value["price"] );
                            $ordergc_list[$value["peer_order_no"]]["afp"] = bcadd($ordergc_list[$value["peer_order_no"]]["afp"],$value["buy_fee"] *  $value["decimal"] );
                        }*/


                        if($value["type"] == 1) { //买
                            $ordergc_list[$value["order_no"]]["afp"] = bcadd($ordergc_list[$value["order_no"]]["afp"],$value["buy_fee"]);
                            $ordergc_list[$value["peer_order_no"]]["afp"] = bcadd($ordergc_list[$value["peer_order_no"]]["afp"],$value["sell_fee"]);
                        }else{
                            $ordergc_list[$value["order_no"]]["afp"] = bcadd($ordergc_list[$value["order_no"]]["afp"],$value["sell_fee"] );
                            $ordergc_list[$value["peer_order_no"]]["afp"] = bcadd($ordergc_list[$value["peer_order_no"]]["afp"],$value["buy_fee"] );
                        }
                    }

                    foreach ($ordergc_list as $key => $value) {
                        if($value["ac"]){
                            $ordergcok_list[$key]["ave_price"] = $value["ap"] / $value["ac"];
                            $ordergcok_list[$key]["all_price"] = $value["ap"];
                            $ordergcok_list[$key]["fee"] = $value["afp"];
                        }
                    }
                }else{
                    $ordergcok_list = [];
                }
            }else{
                $ordergcok_list = [];
            }



            foreach ($list as $key => $value) {
                $list[$key]["turnover"] = $value["total"] - $value["decimal"];
                //unset($list[$key]["decimal"]);

                $list[$key]["mi_name"] = "{$list[$key]['name1']}/{$list[$key]['name2']}";


                //format
                $list[$key]["price"] =  $list[$key]["price"];
                $list[$key]["total"] =  $list[$key]["total"];

                $list[$key]["all_price"] = $ordergcok_list[$value["order_no"]]["all_price"]?$ordergcok_list[$value["order_no"]]["all_price"]:0;
                $list[$key]["ave_price"] =  $ordergcok_list[$value["order_no"]]["ave_price"]?$ordergcok_list[$value["order_no"]]["ave_price"]:0;

                if($value["type"] == 1){
                    $feetag = $list[$key]['name1'];
                }else{
                    $feetag = $list[$key]['name2'];
                }

                $fp =  $ordergcok_list[$value["order_no"]]["fee"]?$ordergcok_list[$value["order_no"]]["fee"]:0;
                $list[$key]["fee"] = $fp . " " . $feetag;

                unset($list[$key]["name1"]);
                unset($list[$key]["name2"]);
            }

            ouputJson(200,lang("PER_GET_SUCCESS"),$list);


        }else{
            ouputJson(201,lang("PER_REQUEST_FAILED"));
        }



    }


    //获取交易记录
    public function recordinfo(){

        $mi_id =  $this->request->param("mid");
        $page  = (int)$this->request->param("page");
        if($page < 1) $page=1;
        $year = $this->request->param("year");
        if(!$year) $year = date("Y");

        if(isset($mi_id)){


            $map1[] =  ['a.mt_order_ui_id', '=', $this->uid];
            $map1[] = ['a.mi_id', '=', $mi_id];

            $map2[] = ['a.mt_peer_ui_id', '=', $this->uid];
            $map2[] = ['a.mi_id', '=', $mi_id];

            $type = (int)$this->request->param("type");

            if($type == 1){
                $map1[] = ['a.type', '=', 1]; //主买
                $map2[] = ['a.type', '=', 2]; //被卖
            }
            if($type == 2){
                $map1[] = ['a.type', '=', 2]; //主卖
                $map2[] = ['a.type', '=', 1]; //被买
            }



            //$mapOr= [ $map1, $map2 ];



            $table = "market_trade_log{$year}_{$mi_id}";
            $exist = Db::query('show tables like "'.  $table .'"');

            if(!$exist) ouputJson(209,lang("PER_NO_DATA"),[]);

            $list1 = db($table)
                    ->alias("a")
                    ->field("a.create_time as time,a.type,a.price,a.decimal,a.amount,a.buy_fee,a.sell_fee,a.mt_id,c.short_name as name1,d.short_name as name2,b.price_bit as pbit,b.amount_bit as abit,a.status as rr")
                    ->join("market_info b","b.mi_id = a.mi_id","left")
                    ->join("coin_info c","b.ci_id_first = c.ci_id","left")
                    ->join("coin_info d","b.ci_id_second = d.ci_id","left")
                    ->where($map1)
                    ->order("a.mt_id desc")
                    ->select();

            if(!$list1) $list1=[];



            $list2 = db($table)
                    ->alias("a")
                    ->field("a.create_time as time,a.type,a.price,a.decimal,a.amount,a.buy_fee,a.sell_fee,a.mt_id,c.short_name as name1,d.short_name as name2,b.price_bit as pbit,b.amount_bit as abit,a.status as rr")
                    ->join("market_info b","b.mi_id = a.mi_id","left")
                    ->join("coin_info c","b.ci_id_first = c.ci_id","left")
                    ->join("coin_info d","b.ci_id_second = d.ci_id","left")
                    ->where($map2)
                    ->order("a.mt_id desc")
                    ->select();

            if($list2) {

                foreach ($list2 as $key => $val){
                    $list2[$key]["type"] = $val["type"] == 1?2:1;
                }

            }else{
                $list2 = [];
            }

            $list = array_merge($list1,$list2);

            if($list) {


                //排序
                foreach ($list as $kk => $vv){
                    $mt_id[] = $vv["mi_id"];
                    $timt_s[] = $vv["time"];
                }
                array_multisort($mt_id,SORT_DESC,$timt_s,SORT_DESC,$list);

                $list = $this->copyChange($list, false);



                $pageinfo["count"] = count($list);
                $pageinfo["current"] = $page;
                $pageinfo["pagecount"] = ceil($pageinfo["count"] / $this->page_per);
                $list = array_slice($list, ($page - 1) * $this->page_per, $this->page_per);


                foreach ($list as $key => $value) {

                    $list[$key]["price"] = $value["price"];

                    $list[$key]["all_price"] = decimal_format($value["amount"],20);

                    $list[$key]["mi_name"] = "{$list[$key]['name1']}/{$list[$key]['name2']}";

/*                    if ($value["type"] == 1) {
                        $feetag = $list[$key]['name1'];
                        $list[$key]["fee"] = decimal_format(($value["buy_fee"] / 100) * $value["decimal"], 20) . " " . $feetag;
                    } else {
                        $feetag = $list[$key]['name2'];
                        $list[$key]["fee"] = decimal_format(($value["sell_fee"] / 100) * $value["decimal"] * $value["price"], 20) . " " . $feetag;
                    }*/


                    if ($value["type"] == 1) {
                        $feetag = $list[$key]['name1'];
                        $list[$key]["fee"] = $value["buy_fee"] . " " . $feetag;
                    } else {
                        $feetag = $list[$key]['name2'];
                        $list[$key]["fee"] = $value["sell_fee"] . " " . $feetag;
                    }

                    $list[$key]["turnover"] = $value["decimal"];

                    //unset($list[$key]["decimal"]);
                    unset($list[$key]["name1"]);
                    unset($list[$key]["name2"]);
                    unset($list[$key]["buy_fee"]);
                    unset($list[$key]["sell_fee"]);

                }
            }else{
                $pageinfo["count"] = count($list);
                $pageinfo["current"] = $page;
                $pageinfo["pagecount"] = ceil($pageinfo["count"] / $this->page_per);
                $list = [];
            }

            $data["list"] = $list;
            $data["pageinfo"] = $pageinfo;
            ouputJson(200,lang('PER_GET_SUCCESS'),$data);

        }else{
            ouputJson(201,lang('PER_REQUEST_FAILED'));
        }

    }


    //撤销委托
    public function putupcancel(){

        $oid = $this->request->param("oid");
        $mid = $this->request->param("mid");
        $year = $this->request->param("year");
        if(!$year) $year = date("Y");


        if(!$oid || !$mid) ouputJson(201,lang("PER_REQUEST_FAILED"));

        $map["ui_id"] = $this->uid;
        $map["order_no"] = $oid;

        $info = db("market_trade{$year}_{$mid}")
                ->where($map)
                ->find();

        if($info){

            if($info["status"] == 1){

                $this->redis->select(0);

                $result =  $this->cancelTrade($info);

                ouputJson($result['status'],$result['msg']);
            }else{
                ouputJson(302,lang("PER_NOT_ALLOW_CANCEL"));
            }
            
        }else{
            ouputJson(301,lang("PER_GET_FAILED"));
        }

        
    }


    /**
     * 撤销挂单(确保没有在撮合,删除Redis的挂单同时修改数据库的挂单数据)
     * @param $market
     * @param $type
     * @param $uid
     * @param $timeS
     * @param $microS_c
     * @return mixed
     */
    public function cancelTrade($tradeData)
    {
        $redis = $this->redis;
        $transactionPair = json_decode($redis->hGet(self::$marketKey,$tradeData['mi_id']),true);
        if (empty($transactionPair)){
//            $transactionPair = MarketInfoModel::where('mi_id', $market)->find();
            $transactionPair = Db::table('market_info')->where('mi_id', $tradeData['mi_id'])->find();
            $redis->hSet(self::$marketKey,$tradeData['mi_id'],json_encode($transactionPair));
        }

        $hKey = 'hash_market_' .$tradeData['mi_id'];
//        $hField = $tradeData['ui_id'] . '_' . $tradeData['create_time'] . '_' . $tradeData['microS'];
        $hField = $tradeData['order_no'];

        $result = config('code.error');
        if ($tradeData['type'] == self::$buyType){
            $data = json_decode($redis->hGet($hKey.self::$hKeyBuy,$hField),true);
            if (!$data){
                $result['msg'] = '读取买单数据为空!';
                return $result;
            }
            $ci_id = $transactionPair['ci_id_second'];
            $num = bcmul($data['decimal'],$data['price'],self::BCPRECISEDIGITS);
        }else/*if ($tradeData['type'] == 2)*/{
            $data = json_decode($redis->hGet($hKey.self::$hKeySell,$hField),true);
            if (!$data){
                $result['msg'] = '读取卖单数据为空!';
                return $result;
            }
            $ci_id = $transactionPair['ci_id_first'];
            $num = $data['decimal'];
        }
        $ui_id = $data['ui_id'];
        $map = [
            'ui_id'         =>   $ui_id,
            'ci_id'         =>   $ci_id,
        ];

        $trans_frost = Db::table('user_finance')->where($map)->value('trans_frost');
        if (bcsub($trans_frost,$num,self::BCPRECISEDIGITS) < 0){
            $result['msg'] = '数据处理中或数据库冻结数据出错!';
            return $result;
        }
        $year = date('Y',$data['create_time']);
        $table = 'market_trade'. $year. '_'. $data['mi_id'];
        $month = date('Y_m',$data['create_time']);
        $table1 = 'user_finance_log'. $month;

        if ($redis->exists('str'.$data['order_no'])){
            $result['msg'] = '真的很巧合,数据正在撮合!';
            return $result;
        }
//        $redis->multi();
        if ($data['type'] == self::$buyType) {
            $redis->hDel($hKey . self::$hKeyBuy, $hField);
        } else/*if ($data['type'] == 2)*/ {
            $redis->hDel($hKey . self::$hKeySell, $hField);
        }
        $data['status'] = 3;
        $redis->hSet($hKey .'_cancelTrade',$hField,json_encode($data));
//        $redis->exec();

        $time = time();
        $count = Db::table($table)->where('order_no', $data['order_no'])->count();
        Db::startTrans();
        try {
            if ($count) {
                $r = Db::table($table)
                    ->where('order_no', $data['order_no'])
                    ->update(['status' => self::$tradeStatus3,'update_time'=>$time]);
                if (!$r){
                    $result['msg'] = '撤销数据挂单数据库更新失败!';
                    return $result;
                }
            } else {
                $data['status'] = self::$tradeStatus3;
                $data['update_time'] = $time;
                $r = Db::table($table)
                    ->data($data)
                    ->insert();
                if (!$r){
                    $result['msg'] = '撤销数据挂单数据库新增失败!';
                    return $result;
                }
            }
            //修改余额数据!$data['decimal'];
//            $amount = [
//                'amount'          =>   Db::raw('amount+'.$num),
//                'trans_frost'     =>   Db::raw('trans_frost-'.$num),
//                'update_time'     =>   $time,
//            ];
//            Db::table('user_finance')
//                ->where($map)
//                ->update($amount);
            $action = 'trans';
            $param = [
                ['field'=>'amount','type'=>'inc','val'=>$num],
                ['field'=>'trans_frost','type'=>'dec','val'=>$num]
            ];
            $ret = updateUserBalance($map['ui_id'],$map['ci_id'],$action,$param);
            if ($ret < 1){
                $result['msg'] = 'updateUserBalance修改余额失败!';
                return $result;
            }
            $data1 = [
                'ui_id'             =>      $ui_id,
                'mi_id'             =>      $data['mi_id'],
                'ci_id'             =>      $ci_id,
//                'bef_A'             =>      $userFinances[0]['amount'],   //交易前余额
//                'bef_B'             =>      $userFinances[0]['trans_frost'],  //交易前冻结
//                'bef_D'             =>      $befTotal0,           //交易前总计
                'num'               =>      $num,           //本次变动数额
                'type'              =>      $data['type'],   //1是买 2是卖
                'create_time'       =>      $time,
//                'aft_A'             =>      $amount0['amount'],         //交易后余额
//                'aft_B'             =>      $userFinances[0]['trans_frost'],      //交易后冻结
//                'aft_D'             =>      $aftTotal0,    //交易后总计
                'order_no'          =>      $data['order_no'],//交易流水号
            ];
            Db::table($table1)
                ->data($data1)
                ->insert();
//            if (!$r){
//                $result['msg'] = '撤销数据余额日志数据库新增失败!';
//                return $result;
//            }
            Db::commit();
            $redis->hDel($hKey .'_cancelTrade',$hField);
        } catch (Exception $e) {
            Db::rollback();
            $error = myGetTrace($e);
            Log::write($error.'!!!撤单,DB修改状态或冻结返还出错,data:'.json_encode($data),'tradeError');
            //记录$buyData到异常记录
            $redis->rPush('list_exception_cancelTrade',json_encode($data));
            $result['exception'] = '数据库事务出错!查看:list_exception_cancelTrade';
            return $result;
        }
        $result = config('code.success');
        return $result;
    }



    //自选
    public function moptional(){

        $cmd = $this->request->param("cmd");
        $page = $this->request->param("page");
        $limit = $this->request->param("limit");
        $q = $this->request->param("key");

        if($cmd == "set"){

            $mids = $this->request->param("mid");

            //获取marr
            $info = db("user_info")->field("tas")->where(["ui_id"=>$this->uid])->find();
            $tas = $info["tas"];
            if($tas){
                $marr = explode(",", $tas);
            }else{
                $marr = [];
            }

            $mids_arr = explode(",",$mids);
            $marr_all = array_unique( array_merge($mids_arr,$marr) );

            $marr_all_str = implode(",",$marr_all);

            $status = db("user_info")->where(["ui_id"=>$this->uid])->update(["tas"=>$marr_all_str]);

            if($status === false){
                ouputJson(203,lang("PER_ACTION_FAILED"));
            }else{
                ouputJson(200,lang("PER_ACTION_SUCCESS"));
            }

        }

        if($cmd == "get"){
            
            //获取marr
            $info = db("user_info")->field("tas")->where(["ui_id"=>$this->uid])->find();
            $tas = $info["tas"];
            if($tas){
                $marr = explode(",", $tas);
            }else{
                $marr = [];
            }

            $tadata = $this->redis->get("swoole:trade:area");
            $tadata_arr = json_decode($tadata,true);

            $list = [];
            foreach ($tadata_arr as $key => $value) {
                if(in_array($value["ta_id"],$marr)){
                    $list[] = $value;
                }
            }

            $nList = [];
            if($list){

                if(isset($q) && $q != ""){

                    foreach ($list as $key => $val){
                        if(strstr($val["name"],strtoupper($q))){
                            $nList[] = $val;
                        }
                    }

                }else{
                    $nList = $list;
                }


            }
            $list = $nList;


            //分页
            $allcount = count($list);
            if(isset($page) && isset($limit) && $allcount > 0){
                //分页过滤
                if((int)$page < 1) ouputJson(201,lang("PER_REQUEST_FAILED"));
                $start = ($page - 1) * $limit;
                $list = array_slice($list,$start,$limit);

            }

            if($allcount){
                $list = getFormatTradeArea1($list);
            }

             return json(["status"=>200,"msg"=>lang("PER_GET_SUCCESS"),"data"=>$list,"all"=>$allcount]);


        }




        if($cmd == "del"){

             $mids = $this->request->param("mid");
            
            //获取marr
            $info = db("user_info")->field("tas")->where(["ui_id"=>$this->uid])->find();
            $tas = $info["tas"];
            if($tas){
                $marr = explode(",", $tas);
            }else{
                $marr = [];
            }

            $mids_arr = explode(",",$mids);

            $marr_all = [];
            foreach ($marr as $item) {
                if(!in_array($item,$mids_arr))  $marr_all[] = $item;
            }

            $marr_all_str = implode(",",$marr_all);

            $status = db("user_info")->where(["ui_id"=>$this->uid])->update(["tas"=>$marr_all_str]);

            if($status === false){
                ouputJson(203,lang("PER_ACTION_FAILED"));
            }else{
                ouputJson(200,lang("PER_ACTION_SUCCESS"));
            }


        }

        ouputJson(201,lang("PER_REQUEST_FAILED"));
        
    }


    /**
     *  撤销一半的复杂一条交易成功记录
     * @param $list
     * @param bool $has1
     * @return array
     */
    protected  function  copyChange($list,$need1 = true){

        if(!$list) return [];

        foreach ($list as $key => $val){

            if($val["status"] == 3 && $val["decimal"] != $val["total"]){

                $nList[] = $val;
                $val["status"] = 2;
                //$val["total"] = $val["total"];
                //$val["decimal"] = $val["total"] - $val["decimal"];
                //$val["order_no"] = "";
                //加入数组
                $nList[] = $val;

            }else if($val["status"] == 1 && $val["decimal"] != $val["total"]){

                if($need1) $nList[] = $val;
                $val["status"] = 2;
                //$val["total"] = $val["total"];
                //$val["decimal"] = ;
                //$val["order_no"] = "";
                //加入数组
                $nList[] = $val;

            }else{
                $nList[] = $val;
            }

        }

        return $nList;


    }





}
