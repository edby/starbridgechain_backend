<?php

namespace app\business\controller;
use think\Config;
use app\common\controller\AuthBase;
use curl\Curl;
use think\Db;

class Bind extends AuthBase
{
	function __construct(){
        parent::__construct();
    }

    /*发送绑定验证码*/
    public function sendBindCodeSms(){
        $mobile = $this->request->param('mobile');
        $password = $this->request->param('password');
        if($mobile=="" || $password == ""){
            ouputJson(201,lang('PLAST_ACPWD'));
        }
        if(!preg_match("/^1[345789]{1}\d{9}$/",$mobile)){
            ouputJson(202,lang('ACCOUNT_FORMAT_ERROR'));
        }

        $bindinfo = Db::name('bp_routeruser')->where(['ui_id'=>$this->uid,'mobile'=>$mobile])->find();
        if(!empty($bindinfo)){
            ouputJson(204,lang('ACC_BIND'));   
        }

        $temp  = [
            'Key'=>config('sms.key'),
            'User'=>config('sms.user'),
            'Mobile'=>$mobile,
            'Password'=> strtoupper(md5($password)),
        ];
        $rst = Curl::post(config('sms.sendCode'),json_encode($temp),['Content-Type: application/json']);

        $rst = json_decode($rst,true);
        if($rst['Header']['Msg']=='ok'){
            ouputJson(200,lang('SEND_SUCCESS'));
        }elseif($rst['Header']['ClientErrorCode']==301){
            ouputJson(201,lang('SF_ONEMIN'));
        }elseif($rst['Header']['ClientErrorCode']==302){
            ouputJson(202,lang('SF_DATFI'));
        }else{
            ouputJson(203,$rst['Header']['Msg']);
        }
    }

    /*绑定*/
    public function bindMobile(){
        $mobile = $this->request->param('mobile');
        $password = $this->request->param('password');
        $code = $this->request->param('code');
        if($mobile=="" || $code=="" || $password==""){
            ouputJson(201,lang('FROM_ERROR'));
        }
        $temp  = [
            'Key'=>config('sms.key'),
            'User'=>config('sms.user'),
            'Mobile'=>$mobile,
            'VerCode'=>$code,
        ];
        $rst = Curl::post(config('sms.checkcode'),json_encode($temp),['Content-Type: application/json']);
        $rst = json_decode($rst,true);
        if($rst['Header']['Msg']=='ok'){
            $bindinfo = Db::name('bp_routeruser')->where(['ui_id'=>$this->uid,'mobile'=>$mobile])->find();
            if(!empty($bindinfo)){
                ouputJson(204,lang('ACC_BIND'));   
            }
            $data['mobile'] = $mobile;
            $data['ui_id'] = $this->uid;
            $data['pwd'] = strtoupper(md5($password));
            $data['bindtime'] = time();
            $data['status'] = 1;
            $suc = Db::name('bp_routeruser')->insert($data);
            if($suc > 0){
                ouputJson(200,lang('BIND_SUCCESS'));
            }else{
                ouputJson(205,lang('BIND_FAIL'));
            }
        }else{
            // return jorx(['code' => 201,'msg' => $rst['Header']['Msg'],'e_msg'=>'failed']);
            ouputJson(202,$rst['Header']['Msg']);
        }
    }

    /*解绑绑*/
    public function unbindMobile(){
        $mobile = $this->request->param('mobile');
        if($mobile==""){
            ouputJson(201,lang('FROM_ERROR'));
        }
        $bindinfo = Db::name('bp_routeruser')->where(['ui_id'=>$this->uid,'mobile'=>$mobile,'status'=>1])->find();
        if(empty($bindinfo)){
            ouputJson(202,lang('BIND_FAIL_RN'));
        }
        $suc = Db::name('bp_routeruser')->where(['ui_id'=>$this->uid,'mobile'=>$mobile])->setField('status',0);
        if($suc > 0){
            ouputJson(200,lang('UNBIND_SUC'));
        }else{
            ouputJson(203,lang('UNBIND_FAIL'));
        }
    }
    /*获取绑定列表*/
    public function bindList(){
        $limit = 20;
        $page = $this->request->param('page');
        if($page == "" || $page == 0 ){
            $page = 1;
        }
        $mobile_list = [];
        $list = Db::name('bp_routeruser')->field('brr_id,mobile')->where(['ui_id'=>$this->uid,'status'=>1])->paginate($limit,false)->toArray();
        if(!empty($list['data'])){
            foreach ($list['data'] as $k => $v) {
                $mobile_list[]=$v['mobile'];
            }
            $temp = [
                'Key'=>config('sms.key'),
                'User'=>config('sms.user'),
                'MobileList'=>$mobile_list,
            ];
            $rst = Curl::post(config('sms.check_the_balance'),json_encode($temp),['Content-Type: application/json']);
            $rst = json_decode($rst,true);
            if($rst['Header']['Msg']=='ok' && !empty($rst['Body']['Data'])){
                ouputJson(200,'success',$rst['Body']['Data']);
            }else{
                ouputJson(202,'fail');
            }
        }else{
            ouputJson(200,'success',[]);
        }
        ouputJson(200,'success',[]);
    }

    /*一键提取*/
    public function extractBalance(){
        $mobile = $this->request->param('mobile');
        if($mobile==""){
            ouputJson(202,lang('CH_ACCOUNT'));
        }
        $bindinfo = Db::name('bp_routeruser')->where(['ui_id'=>$this->uid,'mobile'=>$mobile,'status'=>1])->find();
        if(empty($bindinfo)){
            ouputJson(202,lang('WT_FAIL_NOBA'));
        }

        //查询此账号余额
        $temp = [
            'Key'=>config('sms.key'),
            'User'=>config('sms.user'),
            'MobileList'=>[$mobile],
        ];
        $rst = Curl::post(config('sms.check_the_balance'),json_encode($temp),['Content-Type: application/json']);
        $rst = json_decode($rst,true);
        if($rst['Header']['Msg']=='ok'){
            // $rst['Body']['Data'][0]['TotalValue'] = 1;/////////////////////////

            if(!empty($rst['Body']['Data'])){
                $balance = $rst['Body']['Data'][0]['TotalValue'];
                if($balance <= 0){
                    ouputJson(205,lang('WT_FAIL_NB'));   
                }

                //提取 - 调用提取接口
                $temp_extract = [
                    'Key'=>config('sms.key'),
                    'User'=>config('sms.user'),
                    'Mobile'=>$mobile,
                ];
                $extractStatus = Curl::post(config('sms.extract_balance'),json_encode($temp_extract),['Content-Type: application/json']);
                $extractStatus = json_decode($extractStatus,true);
                if($extractStatus['Header']['Msg']=='ok'){
                    // $extractStatus['Body']['Data']['DetailList'] = array(array('TotalValue'=>1));/////////////

                    $order_no=$extractStatus['Body']['Data']['OrderID'];//order_no
                    if($extractStatus['Body']['Data']['DetailList']==[]){
                        $sdt_add=0;
                    }else{
                        $sdt_add=$extractStatus['Body']['Data']['DetailList'][0]['TotalValue']; //提现数量
                    }
                    Db::startTrans();
                    try {
                        $finaceinfo = Db::name('user_finance')->where(['ui_id'=>$this->uid,'ci_id'=>1])->find();
                        if(empty($finaceinfo)){
                            //不存在此用户的币种数据则创建
                            createCoinAccount($this->uid);
                            $fre_balance = 0;
                        }else{
                            $fre_balance = $finaceinfo['amount'];
                        }
                        $newBalance = bcadd($fre_balance, $sdt_add,15);
                        $suc = Db::name('user_finance')->where(['ui_id'=>$this->uid,'ci_id'=>1])->setField('amount',$newBalance);
                        if($suc <=0){
                            Db::rollback();
                            //提取失败-回调
                            $this->extractBalanceRollback($order_no);

                            ouputJson(208,lang('WT_FAIL_TRY'));
                        }
                        //记录日志
                        Db::name('bp_extractrecords')->insert([
                            'brr_id'    => $bindinfo['brr_id'],
                            'ui_id'     => $this->uid,
                            'mobile'    => $mobile,
                            'extract_time'  => time(),
                            'extract_amounct'   => $sdt_add,
                            'orderid'   => $order_no,
                            'status'    => 1
                        ]);
                        Db::commit();
                        ouputJson(200,lang('WT_SUCC'));
                    } catch (\Exception $e) {
                        Db::rollback();
                        //提取失败-回调
                        $this->extractBalanceRollback($order_no);

                        ouputJson(207,lang('WT_FAIL_TRY'));
                    }
                }else{
                    ouputJson(206,lang('WT_FAIL_TRY'));
                }
            }else{
                ouputJson(204,lang('WT_FAIL_NOACC'));
            }
        }else{
            ouputJson(203,lang('WT_FAIL_QUBF'));
        }
    }


    /**提取失败回调*/
    private function extractBalanceRollback($order_no){
        echo $order_no;
    }



}
