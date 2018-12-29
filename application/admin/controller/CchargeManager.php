<?php


namespace app\admin\controller;
use think\Config;
use app\admin\controller\Admin;
use think\Validate;

//充币管理
class CchargeManager extends Admin{



    protected $fee_type = 1;


    function __construct(){
        parent::__construct();
    }




    //列表
    public function list(){

        $list = db("coin_feeconfig")
                ->field("cf_id,ci_id,single_minlimit,status,msg")
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

            $uData["single_minlimit"] = $this->request->param("single_minlimit");
            $uData["status"] = $this->request->param("status");
            $uData["msg"] = $this->request->param("msg");

/*            $validate = new Validate('Ccharge');
            if (!$validate->check($uData)){
                ouputJson('201',$validate->getError());
            }*/

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
            ouputJson(201,$action.'失败');
        }    

    }






}