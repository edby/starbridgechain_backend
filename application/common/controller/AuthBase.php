<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/5/17
 * Time: 9:59
 */

namespace app\common\controller;

use think\Config;
use think\Controller;
use think\Db;
use Firebase\JWT\JWT;
use redis\Redis;
use think\facade\Cache;

class AuthBase extends Controller
{
    protected $uid;
    protected $userinfo;
    protected $redis;
    protected $key;
    protected $headersData;
    protected $signaction;

    function __construct(){
        
        parent::__construct();
//        try {

            $this->key = config('auth.jwt_oauth_scr');
            $this->redis = Redis::instance();

            //验证web or app 写接口开关验证
            $this->_verWirteApi();
            $this->signaction = ['putupinfo'];
            $action_flvale = ['sendfogemail','forgetupwd','watchTrade'];
            if(!in_array($this->request->action(), $action_flvale)){
                if($this->request->param('istest')!=1){
                    $this->_verificationSign();//签名校验    
                }else{
                    $this->testverifica();//签名校验
                }
                
            }

            //IP访问频率限制
            // $this->frequencyLimit();

//        } catch (\Exception $e) {
//            ouputJson(201,lang('SYSTEM_ERROR').'(-1002)');
//        }
    }

    private function _verWirteApi(){
        $wirteApi = [
            'upTradeBuy',//挂买单撮合交易
            'upTradeSell',//挂卖单撮合交易
            'watchTrade',//买卖未撮合的定时守护任务
            'changeTradePwd',//修改 / 重置交易密码
            'setTradeOption',//修改方式
            'addCoinAdd',//添加地址
            'delCoinAdd',//删除地址
            'setTradePwd',//设置交易密码
            'milist',//获取所有交易市场列表
            'recordinfo',//获取交易记录信息
            'putupcancel',///撤销挂单
            'moptional',//交易市场自选
            'putupinfo',//获取委托记录信息(个人中心)
            'mputupinfo',//获取委托记录信息(交易市场)
            
            'bindMobile',//一键提取绑定 - 绑定账号
            'sendBindCodeSms',//一键提取绑定 - 发送绑定验证码
            'unbindMobile',//一键提取解绑
            'extractBalance',//一键提取接口
            'getCoinAddress',//创建官方充值地址
            'withdrawCoin',//发起提现申请
            'sendWtithdrawEmail'//发送提现申请验证码
        ];

        $config = getSysconfig(['web_write_api_switch','app_write_api_switch']);
        $action = $this->request->action();
        $headersData = get_all_headers();
        if(isset($headersData['pcweb']) && $headersData['pcweb']==1){ //如果是web端
            if(in_array(strtolower($action), array_map('strtolower',$wirteApi)) && $config['web_write_api_switch']!=1){
                ouputJson(10003,'api close');
            }
        }else{ //App端
            if(in_array(strtolower($action), array_map('strtolower',$wirteApi)) && $config['app_write_api_switch']!=1){
                ouputJson(10003,'api close');
            }
        }
    }

    private function testverifica(){
        $headersData = get_all_headers();
        if(!isset($headersData['token']) || $headersData['token'] == ""){
            abort(json(['status' => 10003,'msg' => 'No token parameters.','data' => []]));
        }
        $jwtData = JWT::decode($headersData['token'], $this->key, array('HS256'));
        $userinfo = json_decode($this->redis->get('userinfo_'.$jwtData->uid),true);

        if(empty($userinfo)){
            $userinfo = Db::name('user_info')->where(['ui_id'=>$jwtData->uid])->find();

            if(empty($userinfo)){
                abort(json(['status' => 10003,'msg' => "(-205)".lang('ACCOUNT_ERROR'),'data' => []]));
            }
            $this->redis->setex('userinfo_'.$userinfo['ui_id'],300,json_encode($userinfo));
        }

        if($userinfo['status']==1){
            abort(json(['status' => 10003,'msg' => lang('ACCOUNT_LOCK'),'data' => []]));
        }
        $this->uid = $userinfo['ui_id'];
        $this->userinfo = $userinfo;

    }

    private function _verificationSign(){
          
            $headersData = get_all_headers();
            $headersData = array_merge($headersData,$this->request->get());
            $this->headersData = $headersData;
//        try {


            $action = $this->request->action();
            $isrobot = $this->request->param('isrobot');
            if(($action=="uptradebuy" || $action=="uptradesell") && $isrobot == 1){
                // $cip = md5(get_client_ip());
                // if($cip!=md5("127.0.0.1")){
                //     abort(json(['status' => 10003,'msg' => 'ip auth error.','data' => []]));
                // }
                if(!isset($headersData['token']) || $headersData['token'] == ""){
                    abort(json(['status' => 10003,'msg' => 'No token parameters.','data' => []]));
                }
                $jwtData = JWT::decode($headersData['token'], $this->key, array('HS256'));

                $userinfo = json_decode($this->redis->get('userinfo_'.$jwtData->uid),true);
                if(empty($userinfo)){
                    $userinfo = Db::name('user_info')->where(['ui_id'=>$jwtData->uid])->find();

                    if(empty($userinfo)){
                        abort(json(['status' => 10003,'msg' => "(-205)".lang('ACCOUNT_ERROR'),'data' => []]));
                    }
                    $this->redis->setex('userinfo_'.$userinfo['ui_id'],300,json_encode($userinfo));
                }

                if($userinfo['status']==1){
                    abort(json(['status' => 10003,'msg' => lang('ACCOUNT_LOCK'),'data' => []]));
                }
                $this->uid = $userinfo['ui_id'];
                $this->userinfo = $userinfo;

            }else{

                if(!isset($headersData['token']) || $headersData['token'] == ""){
                    abort(json(['status' => 10003,'msg' => 'No token parameters.','data' => []]));
                }
                if(!isset($headersData['noncestr']) || $headersData['noncestr'] == ""){
                    abort(json(['status' => 10003,'msg' => 'No nonceStr parameters.','data' => []]));
                }

                if(!isset($headersData['sign']) || $headersData['sign'] == ""){
                    abort(json(['status' => 10003,'msg' => 'No sign parameters.','data' => []]));
                }
                if(!isset($headersData['timestamp']) || $headersData['timestamp'] == ""){
                    abort(json(['status' => 10003,'msg' => 'No timestamp parameters.','data' => []]));
                }

                $minc = (time() - $headersData['timestamp'])/60;
                if( $minc >= 5){
                    abort(json(['status' => 10003,'msg' => "(-201)".lang('ACCOUNT_ERROR'),'data' => []]));
                }
                $jwtData = JWT::decode($headersData['token'], $this->key, array('HS256'));
                if(get_client_ip() != $jwtData->ip){
                    abort(json(['status' => 10003,'msg' => "(-202)".lang('ACCOUNT_ERROR'),'data' => []]));
                }
                if(time() >= $jwtData->expiry_time){
                    abort(json(['status' => 10003,'msg' => "(-203)".lang('ACCOUNT_ERROR'),'data' => []]));
                }
                if(isset($headersData['pcweb']) && $headersData['pcweb']==1){
                    $usertoken = $this->redis->get('usertoken_web_'.$jwtData->uid);
                }else{
                    $usertoken = $this->redis->get('usertoken_app_'.$jwtData->uid);
                }
                // $usertoken = $this->redis->get('usertoken:'.$jwtData->uid);
                if( strtolower($usertoken)  != strtolower( md5($headersData['token']))){
                    abort(json(['status' => 10003,'msg' => "(-204)".lang('ACCOUNT_ERROR'),'data' => []]));
                }

                //pc app不同的签名方式 - 所有请求的数据参与签名
                if(isset($headersData['pcweb']) && $headersData['pcweb']==1){
                    if(!in_array($action,$this->signaction)){//过滤导出action
                        $requestParams = $this->request->post();
                        if(!empty($requestParams)){
                            foreach ($requestParams as $k => $v) {
                                if($v=="" || $v==null){
                                    unset($requestParams[$k]);
                                }
                            }
                        }
                        $requestParams['token'] = $headersData['token'];
                        $requestParams['timestamp'] = $headersData['timestamp'];
                        $requestParams['noncestr'] = $headersData['noncestr'];
                        $serverSign = generateSign($requestParams,1);
                    }else{
                        $serverSign = $headersData['sign'];
                    }
                    $sign_key = "user_{$jwtData->uid}_pcweb_sign";
                }else{
                    $signArr = [
                        'app_id'=> config('auth.app_id'),
                        'token'=> $headersData['token'],
                        'timestamp'=> $headersData['timestamp'],
                        'noncestr'=>$headersData['noncestr']
                    ];
                    $serverSign = generateSign($signArr);
                    $sign_key = "user_{$jwtData->uid}_app_sign";
                }
                // echo $serverSign;
                // die;
                
                if($headersData['sign'] != $serverSign){
                    abort(json(['status' => 10003,'msg' => 'Signature error!','data' => []]));
                }

                //判断请求sign是否重复
                $request_sign = Cache::get($sign_key);
                if($headersData['sign'] == $request_sign){
                    abort(json(['status' => 10003,'msg' => lang('TRYEND_REQUEST'),'data' => []]));
                }else{
                    Cache::set($sign_key,$headersData['sign']);
                }

                $this->uid = $jwtData->uid;

                $userinfo = json_decode($this->redis->get('userinfo_'.$jwtData->uid),true);

                if(empty($userinfo)){
                    $userinfo = Db::name('user_info')->where(['ui_id'=>$jwtData->uid])->find();

                    if(empty($userinfo)){
                        abort(json(['status' => 10003,'msg' => "(-205)".lang('ACCOUNT_ERROR'),'data' => []]));
                    }
                    $this->redis->setex('userinfo_'.$userinfo['ui_id'],300,json_encode($userinfo));
                }

                if($userinfo['status']==1){
                    abort(json(['status' => 10003,'msg' => lang('ACCOUNT_LOCK'),'data' => []]));
                }
                $this->uid = $userinfo['ui_id'];
                $this->userinfo = $userinfo;
            }
            

//        } catch (\Exception $e) {
//            ouputJson(201,lang('SYSTEM_ERROR').'(-1001)');
//        }
        
    }

    //访问频率限制 - ip限制 用户限制
    private function frequencyLimit (){
        $limitconfig = config('frequency_limit');

        if($limitconfig['iplimit']){
            $clientip = get_client_ip();
            $ipkey = "frequency_".md5($clientip);
            $ip_limit = Cache::get($ipkey);
            if($ip_limit=="" || $ip_limit==null){
                Cache::set($ipkey,1,$limitconfig['iplimit_second']);
                $ip_limit = 1;
            }else{
                Cache::inc($ipkey);
            }
            if($ip_limit >= $limitconfig['iplimit_count']){
                abort(json(['status' => 10021,'msg' => lang('STSTEM_BUSY'),'data' => []]));
            }
        }

        if($limitconfig['userlimit']){
            $userkey = "frequency_".$this->uid;
            $user_limit = Cache::get($userkey);
            if($ip_limit=="" || $ip_limit==null){
                Cache::set($user_limit,1,$limitconfig['userlimit_second']);
                $user_limit = 1;
            }else{
                Cache::inc($user_limit);
            }
            if($user_limit >= $limitconfig['userlimit_count']){
                abort(json(['status' => 10022,'msg' => lang('STSTEM_BUSY'),'data' => []]));
            }

        }
    }
}


