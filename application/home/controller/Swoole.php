<?php


namespace app\home\controller;

use app\common\controller\Base;
use redis\Redis;
use think\Db;

class Swoole extends Base{
    
    CONST KLINE_PEX_LIST = ["1min","5min","15min","30min","60min","1D","5D","1W","1M"];
    CONST NETWORK_ERR_ARR = ["code"=>0,"msg"=>"网络错误，请稍后重试.","data"=>[]];

    protected $uid = 0;
    protected $market_ids;
    protected $record_num = 45;
    protected $rds_db_no = 2;
    protected $page_per = 10;

    protected $logo_domain = 'http://testsdtbackend.starbridgechain.com';


    protected $market_arr = [];

    function __construct(){

        error_reporting(E_ERROR | E_WARNING | E_PARSE);
        parent::__construct();
        $this->redis->select(2);
        //header('Access-Control-Allow-Origin:*');
        //
        //
        $market_arr = json_decode($this->redis->get("swoole:trade:area"),true);
        $this->market_arr = $this->fitterMarketStatus($market_arr);

    }



    public function  fitterMarketStatus($market_arr){

        $nMarr = [];

        if($market_arr){

            foreach ($market_arr as $mv){
                if($mv["status"]){
                    $nMarr[] = $mv;
                }
            }

        }

        return $nMarr;

    }




    //判断交易市场开关状态
    public static function checkMarketStatus($mid){

        $market_info = db("market_info")->where(["mi_id"=>$mid])->find();

        if( $market_info ){

            $t = time();

            $s1 = $market_info["swstatus"];
            $t1 = $market_info["swstatus_tlimit"];
            $s2 = $market_info["gswstatus"];
            $t2 = $market_info["gswstatus_tlimit"];
            $robot_lock = $market_info["robot_lock"];

            if($robot_lock) return false;


            if( ($t1 < $t && $s1 || $t1 > $t && !$s1) && ($t2 < $t && $s2 || $t2 > $t && !$s2) ){
                return true;
            }else{
                return false;
            }

        }else{
            return false;
        }

    }


    public function ahlist(){



        $headersData = get_all_headers();


        if($headersData['testapi'] || $this->request->param("testapi")){


            $this->uid = $headersData["uid"]?$headersData["uid"]:1;
            return $this->testfun();

        }

        if( $headersData['ts'] ) {

            if (isset($headersData['pcweb']) && $headersData['pcweb'] == 1) {
                $requestParams = $this->request->post();
                if (!empty($requestParams)) {
                    foreach ($requestParams as $k => $v) {
                        if ($v == "" || $v == null) {
                            unset($requestParams[$k]);
                        }
                    }
                }
                $requestParams['token'] = $headersData['token'];
                $requestParams['timestamp'] = $headersData['timestamp'];
                $requestParams['noncestr'] = $headersData['noncestr'];
                $serverSign = generateSign($requestParams, 1);

            } else {
                $signArr = [
                    'app_id' => config('auth.app_id'),
                    'token' => $headersData['token'],
                    'timestamp' => $headersData['timestamp'],
                    'noncestr' => $headersData['noncestr']
                ];
                $serverSign = generateSign($signArr);
            }

            echo "sign:{$serverSign}".PHP_EOL;
            die("");


        }








        $market_list = db("market_info")->where(["apphome"=>1])->select();
        $taid_arr = array_column($market_list,"mi_id");

        $list =[];
        if($this->market_arr){

            foreach ($this->market_arr as $key => $value) {
                $ta_id = $value["ta_id"];
                if(in_array($ta_id,$taid_arr)){
                    $list[] = $value;
                }
            }

            $list = getFormatTradeArea1($list);

        }
        ouputJson(200,lang("PER_GET_SUCCESS"),$list);

    }



    //获取交易信息
    public function tradeinfo(){

        $cmd = $this->request->param("cmd");
        $mid = $this->request->param("mid");
        $coin = $this->request->param("coin");
        $tpex = $this->request->param("tpex");
        $g = $this->request->param("g");
        $q = $this->request->param("key");
        $page = $this->request->param("page");
        $limit = $this->request->param("limit");

        $lang = $this->request->header("language");


        //获取交易对信息
        if("trade_area" == $cmd){

            $cilist = db("coin_info")->column("short_name,logo");

            if($g){

                $exp = strtoupper("*{$g}");

                if($this->market_arr){

                    if($g === "*"){
                        $list = $this->market_arr;
                    }else{
                        $list = $this->getTaByExp($this->market_arr,$exp);
                    }

                    $allcount = count($list);

                    if($allcount) {

                        if (isset($page) && isset($limit)) {
                            //分页过滤
                            if ((int)$page < 1) ouputJson(204, lang("PER_REQUEST_FAILED"));
                            $start = ($page - 1) * $limit;
                            $list = array_slice($list, $start, $limit);
                        }

                        $nList = getFormatTradeArea1($list);

                        foreach ($nList as $key => $val) {
                            if ($cilist[$list[$key]["n"]]) {
                                $nList[$key]["logo"] = $this->logo_domain . $cilist[$list[$key]["n"]];
                            } else {
                                $nList[$key]["logo"] = "";
                            }
                        }

                        return json(["status" => 200, "msg" => lang("PER_GET_SUCCESS"), "data" => $nList, "all" => $allcount]);
                    }else{
                        ouputJson(201,lang("PER_REQUEST_FAILED"));
                    }

                }else{

                    ouputJson(201,lang("PER_REQUEST_FAILED"));

                }


            }else{

                ouputJson(201,lang("PER_REQUEST_FAILED"));

            }
        }



        //获取交易对信息(搜索)
        if("trade_area_search" == $cmd){

            if($g){

                $cilist = db("coin_info")->column("short_name,logo");

                $exp = strtoupper("*{$g}");

                if($this->market_arr){

                    $trade_area_list = $this->market_arr;
                    $list = $this->getTaByExp($trade_area_list,$exp);

                    $nList = [];

                    if(isset($q) && trim($q)){
                        foreach ($list as $key => $val) {
                            $name_arr = explode("_",$val["name"]);
                            if(strstr($name_arr[0],strtoupper($q))){
                                $nList[] = $val;
                            }
                        }
                    }else{
                        $nList = $list;
                    }

                    if($nList){
                        $nnlist = getFormatTradeArea1($nList);
                        foreach ($nnlist as $key => $val){
                            $nnlist[$key]["logo"] = $this->logo_domain. $cilist[$nnlist[$key]["n"]];
                        }
                    }else{
                        $nnlist = [];
                    }

                    return json(["status"=>200,"msg"=>lang("PER_GET_SUCCESS"),"data"=>$nnlist]);

                }else{

                    ouputJson(201,lang("PER_REQUEST_FAILED"));

                }

            }else{
                ouputJson(201,lang("PER_REQUEST_FAILED"));

            }
        }

        //获取交易市场头部信息
        if("trade_area_head" == $cmd){

                if($this->market_arr){





                    $list = getFormatTradeArea1($this->market_arr);
                    $heads = array_unique( array_column($list,"g") );
                    if($heads){
                        foreach ($heads as $val){
                            $nHeads[] = $val;
                        }
                    }else{
                        $nHeads = [];
                    }
                    ouputJson(200,lang("PER_GET_SUCCESS"),$nHeads);

                }else{
                    ouputJson(201,lang("PER_REQUEST_FAILED"));
                }

        }


        //获取币种信息
        if("coin_info" == $cmd){

            if($coin){

                $coinUp = strtoupper($coin);
                $coin_info = db("coin_info")->field("short_name,logo,intro,intro_en,release_time,release_total,circulate_total,crowd_funding,white_paper,website,blockchain")->where(["short_name"=>$coinUp])->find();
                if($coin_info){

                    if($lang == "en-us"){
                        $coin_info["intro"] = $coin_info["intro_en"];
                        unset($coin_info["intro_en"]);
                        $coin_info["release_total"] = $coin_info["release_total"]/10000 . "W";
                        $coin_info["circulate_total"] = $coin_info["circulate_total"]/10000 . "W";

                    }else{
                        $coin_info["release_total"] = $coin_info["release_total"]/10000 . "万";
                        $coin_info["circulate_total"] = $coin_info["circulate_total"]/10000 . "万";
                    }

                    $coin_info["logo"] = $this->logo_domain.$coin_info["logo"];

                    $list = $coin_info;
                }else{
                    $list = [];
                }

            }else{
                $list = [];
            }

            ouputJson(200,lang("PER_GET_SUCCESS"),$list);
        }


        if("cilogo_list" == $cmd){  //获取币种logo列表

            $list = db("coin_info")->field("short_name,logo")->select();

            foreach ($list as $key => $val){
                $nList[$val["short_name"]] = $this->logo_domain.$val["logo"];
            }
            ouputJson(200,lang("PER_GET_SUCCESS"),$nList);
        }


        if(!(int)$mid) ouputJson(201,lang("PER_REQUEST_FAILED"));

        $market_info = db("market_info")->where(['mi_id'=>$mid])->find();
        if(!$market_info) ouputJson(203,lang("PER_REQUEST_FAILED"));


        $pbit = $market_info["price_bit"]?$market_info["price_bit"]:10;
        $abit = $market_info["amount_bit"]?$market_info["amount_bit"]:10;

        $pbit = 10;
        $abit = 10;


        //k线图信息
        if("kline" == $cmd){

            if (!in_array($tpex, self::KLINE_PEX_LIST)) {
                ouputJson(201,lang("PER_REQUEST_FAILED"));
            }

            $tf = $this->request->param("from");
            $tt = $this->request->param("to");

            if($tf > $tt) ouputJson(205,lang("PER_REQUEST_FAILED"));

            $redis_key = "swoole:client:kline:{$mid}:{$tpex}";
            $data_json = $this->redis->get($redis_key);

            if($data_json){
                $data_arr = json_decode($data_json,true);
                $list_o = $this->getFormatKline2($data_arr,false,$pbit,$abit);
                $list = $this->fillKline1($list_o,$tpex,$tf,$tt);

            }else{

                $list = [];
            }

            ouputJson(200,lang("PER_GET_SUCCESS"),$list);

        }


        if("tkline" == $cmd){

            if (!in_array($tpex, self::KLINE_PEX_LIST)) {
                ouputJson(201,lang("PER_REQUEST_FAILED"));
            }

            $tf = $this->request->param("from");
            $tt = $this->request->param("to");

            if($tf > $tt) ouputJson(205,lang("PER_REQUEST_FAILED"));

            $redis_key = "swoole:client:kline:{$mid}:{$tpex}";
            $data_json = $this->redis->get($redis_key);

            if($data_json && $data_json != "[]"){
                $data_arr = json_decode($data_json,true);
                $list_o = $this->getFormatKline2($data_arr,false,$pbit,$abit);
                $list = $this->fillKline1($list_o,$tpex,$tf,$tt);

            }else{
                $list = [];
            }

            ouputJson(200,lang("PER_GET_SUCCESS"),$list);

        }


        //交易
        if("record" == $cmd){

            $redis_key = "swoole:client:record:{$mid}";
            $data_list = $this->redis->lrange($redis_key,0,-1);

            if($data_list){

                foreach ($data_list as $key => $value) {
                    $data_arr[] = json_decode($value,true);
                }

                $list = $this->getFormatRecord1($data_arr,false,$pbit,$abit);
                
            }else{
                $list = [];
            }

            ouputJson(200,lang("PER_GET_SUCCESS"),$list);

        
        }


        //交易记录-买
        if("putup_buy" == $cmd){

            $redis_key = "swoole:client:putup:buy:{$mid}";
            $data_json = $this->redis->get($redis_key);

            if($data_json){
                $data_arr = json_decode($data_json,true);
                $list = $this->getFormatPutup1($data_arr,false,$pbit,$abit);
                
            }else{
                $list = [];
            }

            ouputJson(200,lang("PER_GET_SUCCESS"),$list);

        }

        //交易记录-卖
        if("putup_sell" == $cmd){

            $redis_key = "swoole:client:putup:sell:{$mid}";
            $data_json = $this->redis->get($redis_key);

            if($data_json){
                $data_arr = json_decode($data_json,true);
                $list = $this->getFormatPutup1($data_arr,false,$pbit,$abit);

            }else{
                $list = [];
            }

            ouputJson(200,lang("PER_GET_SUCCESS"),$list);
        }


        if("putup" == $cmd){

            //buy
            $redis_key = "swoole:client:putup:buy:{$mid}";
            $data_json = $this->redis->get($redis_key);
            if($data_json){
                $data_arr = json_decode($data_json,true);
                $list = $this->getFormatPutup1($data_arr,false,$pbit,$abit);
            }else{
                $list = [];
            }
            $data["buy"] = $list;


            //sell
            $redis_key = "swoole:client:putup:sell:{$mid}";
            $data_json = $this->redis->get($redis_key);
            if($data_json){
                $data_arr = json_decode($data_json,true);
                $list = $this->getFormatPutup1($data_arr,false,$pbit,$abit);
            }else{
                $list = [];
            }
            $data["sell"] = $list;

            ouputJson(200,lang("PER_GET_SUCCESS"),$data);

        }





        if("deep" == $cmd){

            $redis_key = "swoole:client:deep:buy:{$mid}";
            $data_json = $this->redis->get($redis_key);
            if($data_json){
                $list = json_decode($data_json,true);

            }else{
                $list = [];
            }

            $deep["buy"] = $list;

            $redis_key = "swoole:client:deep:sell:{$mid}";
            $data_json = $this->redis->get($redis_key);
            if($data_json){
                $list = json_decode($data_json,true);
            }else{
                $list = [];
            }

            $deep["sell"] =  $list;


            ouputJson(200,lang("PER_GET_SUCCESS"),$deep);

        }



        ouputJson(201,lang("PER_REQUEST_FAILED"));
        
    }



    //列表
    public function milist(){

        $list = db("market_info")
                ->field("mi_id,ci_id_first,ci_id_second")
                ->select();

        if($list){

            $cilist = db("coin_info")->column("ci_id,short_name,logo");

            foreach ($list as $key => $value) {
                $list[$key]["name"] =  $cilist[$value["ci_id_first"]]["short_name"];
                $list[$key]["group"] = $cilist[$value["ci_id_second"]]['short_name'];
                $list[$key]["logo"] = $cilist[$value["ci_id_first"]]['logo'];
                unset($list[$key]["ci_id_first"]);
                unset($list[$key]["ci_id_second"]);
            }
            
            foreach ($list as $key => $value) {
                $mlist[$value["group"]][] = $value;
            }

            foreach ($mlist as $key => $value) {
                foreach ($value as $ckey => $cvalue) {
                    unset($mlist[$key][$ckey]["group"]);
                    $mlist[$key][$ckey]["logo"] = $this->logo_domain.$cvalue["logo"];
                }
            }


            ouputJson(200,lang("PER_GET_SUCCESS"),$mlist);

        }else{
            ouputJson(201,lang("PER_REQUEST_FAILED"));

        }


    }







    //获取委托记录(个人中心)
    public function putupinfo (){


        $mi_id = $this->request->param("mid");
        $page  = (int)$this->request->param("page");
        if($page < 1) $page=1;

        if(isset($mi_id)){

            $map = [];

            if($mi_id){
                $map["a.mi_id"] = $mi_id;
            }

            $list = db("market_trade")
                    //->field("price,total,fee,create_time,status,order_no")
                    ->alias("a")
                    ->field("a.create_time as time,a.type,a.price,a.total,a.decimal,a.price as ave_price,a.fee,a.status,c.short_name as name1,d.short_name as name2")
                    ->join("market_info b","b.mi_id = a.mi_id","left")
                    ->join("coin_info c","b.ci_id_first = c.ci_id","left")
                    ->join("coin_info d","b.ci_id_second = d.ci_id","left")
                    ->where($map)
                    ->select();


            $pageinfo["count"] = count($list);
            $pageinfo["current"] = $page;

            $list = array_slice($list,($page-1)*$this->page_per,$this->page_per);

            foreach ($list as $key => $value) {
                $list[$key]["turnover"] = $value["total"] - $value["decimal"];
                unset($list[$key]["decimal"]);

                $list[$key]["mi_name"] = "{$list[$key]['name1']}/{$list[$key]['name2']}";
                unset($list[$key]["name1"]);
                unset($list[$key]["name2"]);

                $list[$key]["all_price"] = $list[$key]["turnover"] * $list[$key]["price"];
            }

            $data["list"] = $list;
            $data["pageinfo"] = $pageinfo;

            ouputJson(200,lang("PER_GET_SUCCESS"),$data);


        }else{
            ouputJson(201,lang("PER_REQUEST_FAILED"));
        }


        
    }








    /**
     * 判断字符是否是在字符串的开头
     * @param  [type]  $string [description]
     * @param  [type]  $chart  [description]
     * @return boolean         [description]
     */
    protected function isChartBegin($string,$chart){

        if( strpos($string, $chart) === 0){
            return true;
        }else{
            return false;
        }

    }

    /**
     * 判断字符是否是在字符串的末尾
     * @param  [type]  $string [description]
     * @param  [type]  $chart  [description]
     * @return boolean         [description]
     */
    protected function isChartEnd($string,$chart){

        if(strrchr($string,$chart) == $chart){
            return true;
        }else{
            return false;
        }
    }




    /**
    * 查询交易对信息
    * @param  [type] $arr [description]
    * @param  [type] $exp [description]
    * @return [type]      [description]
    */
    protected function getTaByExp($arr,$exp){

        $nArr = [];

        $all = $exp == "*" && strlen($exp) == 1;
        $left = $this->isChartBegin($exp,"*") && strlen($exp) > 1;
        $right = $this->isChartEnd($exp,"*") && strlen($exp) > 1;
        $many = strpos($exp, ",") > 0;

        //var_dump($all,$left,$right,$many);

        //查询所有
        if($all) $nArr = $arr;

        if($left){  //查询结尾（开头*）  *btc
            $coin = str_replace("*","",$exp);
            foreach ($arr as $key => $val){
                $coin_redis = $val["name"];
                if($this->isChartEnd($coin_redis,$coin)){
                    $nArr[] = $val;
                }
            }
        }else if($right){    //查询开头（结尾*）  btc*
            $coin = str_replace("*","",$exp);
            foreach ($arr as $key => $val){
                $coin_redis = $val["name"];
                //var_dump($coin_redis);
                //var_dump($coin);
                //var_dump($this->isChartBegin($coin_redis,$coin));
                if($this->isChartBegin($coin_redis,$coin)){
                    $nArr[] = $val;
                }
            }
        }else if($many){  //查询多个（，）
            $coin_arr = explode(",",$exp);
            foreach ($arr as $key => $val){
                $coin_redis = $val["name"];
                if(in_array($coin_redis,$coin_arr)){
                    $nArr[] = $val;
                }

            }
        }

        return $nArr;
    }



    /**
     * 格式化k线图数据
     * @param  [type]  $list [description]
     * @param  boolean $last [description]
     * @return [type]        [description]
     */
    function getFormatKline1($list,$last=false,$pbit=2,$abit=2){

        if(!$list) return [];

        $list_count = count($list);

        if($last && $list_count > 1){
            $last_index_1 = $list_count -1;
            $last_index_2 = $list_count -2;
            $open = $list[$last_index_2]["close"];
            $pList[0] = $list[$last_index_1];
        }else{
            $open = 0;
            $pList = $list;
        }


        $nList = [];

        foreach ($pList as $i => $value) {

            $nList_item["open"] = $open;
            $nList_item["close"] = isdecimal_format($value["close"],$pbit);
            $nList_item["high"] = isdecimal_format($value["high"],$pbit);
            $nList_item["low"] = isdecimal_format($value["low"],$pbit);
            $nList_item["volume"] = isdecimal_format($value["volume"],$abit);
            $nList_item["time"] = (int)$value["time"] * 1000;

            $nList[] = $nList_item;

            $open = isdecimal_format($value["close"],$abit);
            
        }

        return $nList;

    }


    function getFormatKline2($list,$last=false,$pbit=2,$abit=2){

        if(!$list) return [];

        $list_count = count($list);

        if($last && $list_count > 1){
            $last_index_1 = $list_count -1;
            $last_index_2 = $list_count -2;
            $open = $list[$last_index_2]["close"];
            $pList[0] = $list[$last_index_1];
        }else{
            $open = 0;
            $pList = $list;
        }

        $nList = [];

        foreach ($pList as $i => $value) {

            $nList_item["open"] = $open;
            $nList_item["close"] = $value["close"];
            $nList_item["high"] = $value["high"];
            $nList_item["low"] = $value["low"];
            $nList_item["volume"] = $value["volume"];
            $nList_item["time"] = (int)$value["time"] * 1000;
            //$nList_item["t"] = date("Y-m-d H:i:s",$value["time"]);

            //开盘价不能大于最高价
            if($open > $nList_item["high"]) $nList_item["high"] = $open;

            $nList[] = $nList_item;

            $open = $value["close"];
            
        }

        return $nList;

    }




    /**
    * 填充k线图数据
    **/
    function fillKline1($list,$tpex,$tf=0,$tt=0){

        $now = $this->getNearTime(time(),$tpex);
        $groupDataArr = $this->getTimeGroupDataArr($now);
        $groupData = $groupDataArr[$tpex];

        $from = $groupData["form"] * 1000;

        $second = $groupData["second"] * 1000;
        $to = $now * 1000;

        foreach ($list as $key => $value) {
            $nList[$value["time"]] = $value;
        }

        $start = 0;
        $last = [];


        if($tf){
            $ttf = (int)($tf/1000);
            $from = $this->getNearTime($ttf,$tpex) * 1000;
        }
        if($tt) $to = (int)$tt;

/*      var_dump($tf.date("Y-m-d H:i:s",$tf/1000));
        var_dump($tt.date("Y-m-d H:i:s",$tt/1000));*/


        $ac = ($to - $from) / $second;

        if($ac > 1500) ouputJson(207,lang("PER_REQUEST_FAILED").$ac);

        $last_index = count($list) - 1;
        $last = $list[$last_index];

        for($i=$from;$i<=$to;$i = $i + $second){

            if(isset($nList[$i])){
                $nnList[] = $nList[$i];
                //$start = 1;
                $last = $nList[$i];
            }else{
                $last["open"] = $last["close"];
                $last["close"] = $last["close"];
                $last["high"] = $last["close"];
                $last["low"] = $last["close"];
                $last["time"] = $i;
                $last["volume"] = 0;
                $nnList[] = $last;
            }

        }

        return $nnList;

    }


    //时间分辨率相关
    function getTimeGroupDataArr($to){



        return  [
            "1min"=>[
                "form"=>$to-86400, //60*60*24=86400
                "second"=>60,
                "day_ca"=>0
            ],
            "5min"=>[
                "form"=>$to-432000, //60*60*24*5
                "second"=>60*5,
                "day_ca"=>0
            ],
            "15min"=>[
                "form"=>$to-1296000,//60*60*24*15
                "second"=>60*15,
                "day_ca"=>0
            ],
            "30min"=>[
                "form"=>$to-2592000,//60*60*24*30
                "second"=>60*30,
                "day_ca"=>0
            ],
            "60min"=>[
                "form"=>$to-5184000,//60*60*24*30*2
                "second"=>60*60,
                "day_ca"=>0
            ],
            "1D"=>[
                "form"=>$to-31536000,//60*60*24*365
                "second"=>60*60*24,
                "day_ca"=>28800
            ],
            "5D"=>[
                "form"=>$to-157680000,//60*60*24*365*5
                "second"=>60*60*24*5,
                "day_ca"=>28800
            ],
            "1W"=>[
                "form"=>$to-220752000,//60*60*24*365*7
                "second"=>60*60*24*7,
                "day_ca"=>28800
            ],
            "1M"=>[
                "form"=>$to-220752000,//60*60*24*365*7
                "second"=>60*60*24*30,
                "day_ca"=>28800
            ],
        ];



    }


    //获取最近的时间戳
    function getNearTime($time,$timeT){

        $timeGroupArr = $this->getTimeGroupDataArr($time);

        $seconds = $timeGroupArr[$timeT]["second"];
        $day_ca = $timeGroupArr[$timeT]["day_ca"];

        $yu  = ($time + $day_ca) % $seconds;

        return $time - $yu;
    }





    /**
     * 格式化交易记录数据
     * @param  [type]  $list [description]
     * @param  boolean $last [description]
     * @return [type]        [description]
     */
    function getFormatRecord1($list,$last=false,$pbit=2,$abit=2){

        /*var_dump($abit);
        die("555");*/

        if(!$list) return [];

        foreach ($list as $key => $val){

            $list[$key]["price"] = isdecimal_format($list[$key]["price"],$pbit);
            $list[$key]["count"] = isdecimal_format($list[$key]["count"],$abit);
            $list[$key]["t"] = date("Y-m-d H:i:s",$list[$key]["time"]);
            $list[$key]["time"] = (int)$list[$key]["time"]*1000;
            $list[$key]["direction"] = (int)$list[$key]["direction"];;

        }


        if($last){
            return $list[count($list)-1];
        }else{

            $count = count($list);
            $last_index = $count-1;

            $list_r = array_reverse($list);
            $list_r_limit =  array_slice($list_r,0,$this->record_num);
            $list = array_reverse($list_r_limit);
            
            return  $list;

        }
    }



    /**
     * 格式化挂单记录
     * @param  [type]  $list [description]
     * @param  boolean $last [description]
     * @return [type]        [description]
     */
    function getFormatPutup1($list,$last=false,$pbit=2,$abit=2){


        if(!$list) return [];

        foreach ($list as $key => $val){

            $list[$key]["price"] = isdecimal_format($list[$key]["price"],$pbit);
            $list[$key]["count"] = isdecimal_format($list[$key]["count"],$abit);
            //$list[$key]["time"] = (int)$list[$key]["time"]*1000;
            //$list[$key]["per"] = $list[$key]["per"];

        }

        if($last){
            return $list[count($list)-1];
        }else{
            return $list;

        }

    }


    /**
     * 格式化挂单记录
     * @param  [type]  $list [description]
     * @param  boolean $last [description]
     * @return [type]        [description]
     */
    function getFormatPutup2($list,$last=false,$pbit=2,$abit=2){

        if(!$list) return [];

        foreach ($list as $key => $val){

            $list[$key]["price"] = isdecimal_format($list[$key]["price"],$pbit);
            $list[$key]["count"] = isdecimal_format($list[$key]["decimal"],$pbit);
            $list[$key]["time"] = (int)$list[$key]["create_time"]*1000;
            //$list[$key]["per"] = $list[$key]["per"];

        }

        if($last){
            return $list[count($list)-1];
        }else{
            return $list;

        }

    }


    /**
     * 格式化深度信息
     * @param $list
     */
    function getFormatDeep($list,$pbit=2,$abit=2){

        if(!$list) return [];

        //$all_v = array_column($list,"decimal");
        //$total = array_sum($all_v);

        foreach ($list as $key => $val){

            $price = isdecimal_format($val["price"],2);
            $count = isdecimal_format($val["decimal"],2);

            //$per = sprintf("%.2f",$count / $total);

            $nn["price"] = $price;
            $nn["count"] = $count;

            $nnList[] = $nn;

        }

        return $nnList;

    }



    public function  testfun(){


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
                ->field("a.create_time as time,a.type,a.price,a.total,a.decimal,a.status,c.short_name as name1,d.short_name as name2,a.order_no,b.price_bit as pbit,b.amount_bit as abit")
                ->join("market_info b","b.mi_id = a.mi_id","left")
                ->join("coin_info c","b.ci_id_first = c.ci_id","left")
                ->join("coin_info d","b.ci_id_second = d.ci_id","left")
                ->where($map)
                ->order("a.mt_id desc")
                ->select();

        }else{

            $list = db($table)
                ->alias("a")
                ->field("a.create_time as time,a.type,a.price,a.total,a.decimal,a.status,c.short_name as name1,d.short_name as name2,a.order_no,b.price_bit as pbit,b.amount_bit as abit")
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
            ->field("a.create_time as time,a.type,a.price,a.total,a.decimal,a.status,c.short_name as name1,d.short_name as name2,a.order_no,b.price_bit as pbit,b.amount_bit as abit")
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













}
