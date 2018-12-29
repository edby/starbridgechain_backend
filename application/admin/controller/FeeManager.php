<?php


namespace app\admin\controller;
use think\Config;
use app\admin\controller\Admin;

//币种出入场手续费管理
class FeeManager extends Admin{


    protected $fee_type = 2;

    function __construct(){
        parent::__construct();
    }




    //列表
    public function list(){

        $list = db("coin_feeconfig")
                ->field("cf_id,ci_id,day_totallimit,single_minlimit,single_maxlimit,day_singletotallimit,year_totallimit,pointnum,autoapprove_flag,down_limit,status,fee,fee_mode")
                ->where(["fee_type"=>$this->fee_type])
                ->select();


        if($list){

            $cilist = db("coin_info")->column("ci_id,short_name");

            foreach ($list as $key => $value) {
                $list[$key]["ci_name"] = $cilist[$list[$key]["ci_id"]];
            }

            ouputJson(200,'获取成功',$list);
        }else{
            ouputJson(201,'获取失败');
        }



    }



    //详情
    public function info(){

        $cf_id = $this->request->param("cf_id");

        if(!$cf_id) ouputJson(201,'获取失败');

        //编辑信息
        if($this->request->isPost()){

            //$uData["fee_type"] = $this->request->param("fee_type");
            $uData["fee"] = $this->request->param("fee");
            $uData["fee_mode"] = $this->request->param("fee_mode");
            $uData["autoapprove_flag"] = $this->request->param("autoapprove_flag");
            $uData["down_limit"] = $this->request->param("down_limit");
            $uData["single_maxlimit"] = $this->request->param("single_maxlimit");
            $uData["single_minlimit"] = $this->request->param("single_minlimit");
            $uData["day_singletotallimit"] = $this->request->param("day_singletotallimit");
            $uData["day_totallimit"] = $this->request->param("day_totallimit");
            $uData["year_totallimit"] = $this->request->param("year_totallimit");
            $uData["pointnum"] = $this->request->param("pointnum");
            $uData["status"] = $this->request->param("status");


            $info = db("coin_feeconfig")
                    ->where(["cf_id"=>$cf_id,"fee_type"=>$this->fee_type])
                    ->find();

            if($info){

                $list = db("coin_feeconfig")
                        ->where(["cf_id"=>$cf_id,"fee_type"=>$this->fee_type])
                        ->update($uData);

                if($list !==  false){
                    ouputJson(200,'更新成功');
                }else{
                    ouputJson(201,'更新失败');
                }

            }else{
                ouputJson(201,'查询失败');
            }


        }

    }



    //总开关
    public function switch(){

        $status = $this->request->param("status");

        if(!isset($status) || $status == "") ouputJson(201,'请求参数出错');

        $uData["status"] = $status?1:0;

        $list = db("coin_feeconfig")
                ->where(["fee_type"=>$this->fee_type])
                ->update($uData);

        $action = $status?'开启':'关闭';

        if($list !== false){
            ouputJson(200,$action.'成功');
        } else{
            ouputJson(201,$action.'操作失败');
        }    

    }






}