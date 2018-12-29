<?php

namespace app\business\controller;
use think\Config;
use app\common\controller\AuthBase;
use curl\Curl;
use think\Db;
use app\business\model\rechargeAddress;
use app\business\model\coinUprecord;
use app\business\model\coinDownrecord;
use app\common\service\Email;
use think\helper\Str;

class Recharge extends AuthBase
{
	function __construct(){
        parent::__construct();
    }

    //获取推广获利记录
    public function getExtensionList(){
        $page = $this->request->param('page');
        $create_time = $this->request->param('create_time');
        $default_coin_id = config('default_coin_id');
        $ui_id = $this->uid;
        $limit = 10;
        if($page==0 || $page==""){
            $page = 1;
        }
        $where = ['ci_id'=>$default_coin_id,'benefit_uid'=>$ui_id,'status'=>1];
        $coinfo = Db::name('coin_info')->field('name,logo')->where(['ci_id'=>$default_coin_id,'status'=>1])->find();
        if(empty($coinfo)){
            ouputJson(202,lang('COIN_UNKNOW'));
        }

        if($create_time!=""){
            $firstday = date("{$create_time}-01 00:00:00");
            $lastday = date('Y-m-d 23:59:59', strtotime("$firstday +1 month -1 day"));
            $list = Db::name('user_spread_logs')->where($where)->whereTime('creatime','between',[strtotime($firstday),strtotime($lastday)])->field('amount as allocation,creatime as time,id')->order('creatime desc')->paginate($limit,false)->toArray();
        }else{
            $list = Db::name('user_spread_logs')->where($where)->field('amount as allocation,creatime as time,id')->order('creatime desc')->paginate($limit,false)->toArray();
        }
        $list['count'] = $list['total'];
        unset($list['total']);
        unset($list['per_page']);
        if(!empty($list['data'])){
            foreach ($list['data'] as $k => $v) {
                $list['data'][$k]['type'] = 3;
                $list['data'][$k]['status'] = 3;
                $list['data'][$k]['coin_name'] = $coinfo['name'];;
                $list['data'][$k]['coin_logo'] = config('admin_http_url').$coinfo['logo'];
                $list['data'][$k]['time'] = date('Y-m-d H:i:s',$v['time']);
            }
        }

        ouputJson(200,'',$list);
    }
    
    //获取账户当前余额和充值地址数据
    public function getCoinInfo(rechargeAddress $recharge){
        $ci_id = $this->request->param('ci_id');
        if($ci_id=="" || $ci_id==0){
            ouputJson(201,lang('FROM_ERROR'));
        }
        $ui_id = $this->uid;
        $respData = [];

        //查询币种是否存在
        $coinfo = Db::name('coin_info')
        ->alias('ci')
        ->join('coin_feeconfig cf','ci.ci_id=cf.ci_id','left')
        ->field('ci.ci_id,ci.coin_type,ci.rpcinfo,cf.pointnum as decimal_digits,cf.single_minlimit as minimum_recharge,ci.confirmations,cf.hint')
        ->where(['ci.ci_id'=>$ci_id,'ci.status'=>1,'cf.fee_type'=>1])
        ->find();

        // dump($coinfo);
        // die;
        if(empty($coinfo)){

            ouputJson(202,lang('COIN_UNKNOW'));
        }
        //查询币种充值地址
        $addinfo = rechargeAddress::where(['ci_id'=>$ci_id,'ui_id'=>$ui_id])->find();
        if(!empty($addinfo) && $addinfo['coinaddr']!=""){
            if($addinfo['status']!=1){
                ouputJson(203,lang('ACCADDR_NOUSE'));
            }
            $respData['address'] = $addinfo['coinaddr'];
        }else{
            //创建地址
            if($coinfo['coin_type'] == 1){ //BTC类型
                $new_address = send_btc_wallet_address(json_decode($coinfo['rpcinfo'],true));
            }elseif($coinfo['coin_type'] == 2){ //ETH类型
                $new_address = send_eth_wallet_address($coinfo['rpcinfo']);
            }elseif($coinfo['coin_type'] == 3){ //自发行
                ouputJson(204,lang('CREATE_FIAL'));
            }else{ //其他
                ouputJson(204,lang('CREATE_FIAL'));
            }
            if($new_address==""){
                ouputJson(205,lang('CREATE_FIAL'));
            }
            $addrdata['ci_id'] = $coinfo['ci_id'];
            $addrdata['ui_id'] = $ui_id;
            $addrdata['coinaddr'] = $new_address;
            $addrdata['givetime'] = time();
            $addrdata['status'] = 1;
            Db::name('coin_upuserbind')->insert($addrdata);

            $respData['address'] = $new_address;
        }
        //查询可用余额
        $binance = Db::name('user_finance')->field('amount,trans_frost,out_frost')->where(['ui_id'=>$ui_id,'ci_id'=>$ci_id])->find();
        if(!empty($binance)){
            $respData['available_amount'] = decimal_format($binance['amount'],$coinfo['decimal_digits']);
            $respData['frozen_amount'] = decimal_format(bcadd($binance['trans_frost'], $binance['out_frost'],15),$coinfo['decimal_digits']);
        }else{
            $respData['available_amount'] = decimal_format(0,$coinfo['decimal_digits']);
            $respData['frozen_amount'] = decimal_format(0,$coinfo['decimal_digits']);
        }
        $respData['minimum_recharge'] = decimal_format($coinfo['minimum_recharge'],$coinfo['decimal_digits'],false);
        $respData['confirmations'] = $coinfo['confirmations'];

        //描述文案
        if($coinfo['hint']!=""){
            $header = get_all_headers();
            $hint = json_decode($coinfo['hint'],true);
            if (!isset($header['language'])) {
                $header['language'] = "zh-cn";
            }
            if($header['language'] == "zh-cn"){
                $languageData = $hint['chinese'];
            }elseif($header['language'] == "en-us"){
                $languageData = $hint['english'];
            }else{
                $languageData = $hint['chinese'];
            }
        }else{
            $languageData = [];
        }
        $respData['hint'] = $languageData;
        
        ouputJson(200,'',$respData);
    }


    //生成官方充币地址
    /*
    public function getCoinAddress(rechargeAddress $recharge){
        $ci_id = $this->request->param('ci_id');
        $ui_id = $this->uid;
        if($ci_id=="" || $ci_id==0){
            ouputJson(201,'币种参数错误');
        }
        //查询币种是否存在
        $coinfo = Db::name('coin_info')->where(['ci_id'=>$ci_id,'status'=>1])->find();
        if(empty($coinfo)){
            ouputJson(202,'币种不存在或不可用');
        }

        //查询币种是否存在
        $addinfo = rechargeAddress::where(['ci_id'=>$ci_id,'ui_id'=>$ui_id])->find();
        if(!empty($addinfo)){
            ouputJson(203,'创建失败，不能重复创建地址！');
        }

        if($coinfo['coin_type'] == 1){ //BTC类型
            $new_address = send_btc_wallet_address();
        }elseif($coinfo['coin_type'] == 2){ //ETH类型
            $new_address = send_eth_wallet_address($coinfo['rpcinfo']);
        }elseif($coinfo['coin_type'] == 3){ //自发行
            ouputJson(204,'创建失败，无法创建地址');
        }else{ //其他
            ouputJson(204,'创建失败，无法创建地址');
        }
        if($new_address==""){
            ouputJson(205,'创建失败，请稍后再试');
        }
        $data['ci_id'] = $coinfo['ci_id'];
        $data['ui_id'] = $ui_id;
        $data['coinaddr'] = $new_address;
        $data['givetime'] = time();
        $data['status'] = 1;
        $suc = $recharge->data($data)->save();
        if($suc > 0){
            ouputJson(200,'创建成功',['address'=>$new_address]);
        }else{
            ouputJson(206,'创建失败，请稍后再试');
        }
    }
    */

    //获取币种的充值记录
    public function getRechargeRecode(){
        $limit = 10;
        $ci_id = $this->request->param('ci_id');
        $ui_id = $this->uid;
        $montime = $this->request->param('montime');
        if($ci_id=="" || $ci_id==0){
            ouputJson(201,lang('FROM_ERROR'));
        }

        $coinfo = Db::name('coin_info')
        ->alias('ci')
        ->join('coin_feeconfig cf','ci.ci_id=cf.ci_id','left')
        ->field('cf.pointnum as decimal_digits')
        ->where(['ci.ci_id'=>$ci_id,'ci.status'=>1,'cf.fee_type'=>1])
        ->find();
        if(empty($coinfo)){
            ouputJson(202,lang('COIN_UNKNOW'));
        }

        if($montime!=""){
            $firstday = date("{$montime}-01 00:00:00");
            $lastday = date('Y-m-d 23:59:59', strtotime("$firstday +1 month -1 day"));
            $list = Db::name('coin_uprecord')->field('cuu_id,account,tx_hash,to_address as address,amount,uptime,status')->where(['ci_id'=>$ci_id,'ui_id'=>$ui_id])->whereTime('uptime','between',[strtotime($firstday),strtotime($lastday)])->order('uptime desc')->paginate($limit,false)->toArray();
        }else{
            $list = Db::name('coin_uprecord')->where(['ci_id'=>$ci_id,'ui_id'=>$ui_id])->field('cuu_id,account,tx_hash,to_address as address,amount,uptime,status')->order('uptime desc')->paginate($limit,false)->toArray();
        }
        if(!empty($list['data'])){
            foreach ($list['data'] as $k => $v) {
                $list['data'][$k]['amount'] = decimal_format($v['amount'],$coinfo['decimal_digits']);
                $list['data'][$k]['uptime'] = date('Y.m.d H:i:s',$v['uptime']);
                $list['data'][$k]['time'] = date('Y.m.d H:i:s',$v['uptime']);
                $list['data'][$k]['account'] = $v['cuu_id'];//email_format($v['account']);
            }
        }
        ouputJson(200,'',$list);
    }


    //获取提现页面参数
    public function getWithdrawParam(){
        $ci_id = $this->request->param('ci_id');
        
        $ui_id = $this->uid;

        if($ci_id=="" || $ci_id==0){
            ouputJson(201,lang('FROM_ERROR'));
        }
        $respData = [];
        //查询币种是否存在
        $coinfo = Db::name('coin_info')
        ->alias('ci')
        ->join('coin_feeconfig cf','ci.ci_id=cf.ci_id','left')
        ->field('ci.ci_id,cf.pointnum as decimal_digits,cf.fee_mode,cf.single_maxlimit,cf.single_minlimit,cf.fee,cf.hint')
        ->where(['ci.ci_id'=>$ci_id,'ci.status'=>1,'cf.fee_type'=>2])
        ->find();
        if(empty($coinfo)){
            ouputJson(202,lang('COIN_UNKNOW'));
        }
        
        //查询可用余额
        $binance = Db::name('user_finance')->field('amount,trans_frost,out_frost')->where(['ui_id'=>$ui_id,'ci_id'=>$ci_id])->find();
        if(!empty($binance)){
            $respData['available_amount'] = decimal_format($binance['amount'],$coinfo['decimal_digits'],false);
            $respData['frozen_amount'] = decimal_format(bcadd($binance['trans_frost'], $binance['out_frost'],15),$coinfo['decimal_digits']);
        }else{
            $respData['available_amount'] = decimal_format(0,$coinfo['decimal_digits']);
            $respData['frozen_amount'] = decimal_format(0,$coinfo['decimal_digits']);
        }
        //最小最大提现数
        $respData['single_minlimit'] = decimal_format($coinfo['single_minlimit'],$coinfo['decimal_digits'],false);
        $respData['single_maxlimit'] = decimal_format($coinfo['single_maxlimit'],$coinfo['decimal_digits'],false);
        //手续费
        if($coinfo['fee_mode'] == 1){
            $respData['single_fee'] = decimal_format($coinfo['fee'],$coinfo['decimal_digits'],false);
        }else{
            $respData['single_fee'] = decimal_format(bcdiv($coinfo['fee'],100,$coinfo['decimal_digits']),$coinfo['decimal_digits'],false);    
        }
        
        $respData['fee_mode'] = $coinfo['fee_mode'];
        $respData['pointnum'] = $coinfo['decimal_digits'];

        //查询提现地址列表
        $respData['single_addrs'] = Db::name('coin_downuseraddr')->field('addr')->where(['ui_id'=>$ui_id,'ci_id'=>$ci_id,'status'=>1])->select();

        //描述文案
        if($coinfo['hint']!=""){
            $header = get_all_headers();
            $hint = json_decode($coinfo['hint'],true);
            if (!isset($header['language'])) {
                $header['language'] = "zh-cn";
            }
            if($header['language'] == "zh-cn"){
                $languageData = $hint['chinese'];
            }elseif($header['language'] == "en-us"){
                $languageData = $hint['english'];
            }else{
                $languageData = $hint['chinese'];
            }
        }else{
            $languageData = [];
        }
        $respData['hint'] = $languageData;
        ouputJson(200,'',$respData);
    }


    //获取币种的提现记录
    public function getWithdrawRecode(){
        $limit = 10;
        $ci_id = $this->request->param('ci_id');
        $montime = $this->request->param('montime');

        $ui_id = $this->uid;
        if($ci_id=="" || $ci_id==0){
            ouputJson(201,lang('FROM_ERROR'));
        }
        $where['ci_id'] = $ci_id;
        $where['ui_id'] = $ui_id;

        $coinfo = Db::name('coin_info')
        ->alias('ci')
        ->join('coin_feeconfig cf','ci.ci_id=cf.ci_id','left')
        ->field('cf.pointnum as decimal_digits')
        ->where(['ci.ci_id'=>$ci_id,'ci.status'=>1,'cf.fee_type'=>1])
        ->find();
        if(empty($coinfo)){
            ouputJson(202,lang('COIN_UNKNOW'));
        }
        $field = "ct_id,account,to_address as address,amount,createtime,status";
        if($montime!=""){
            $firstday = date("{$montime}-01 00:00:00");
            $lastday = date('Y-m-d 23:59:59', strtotime("$firstday +1 month -1 day"));

            $list = Db::name('coin_downapply')->field($field)->where($where)->whereTime('createtime','between',[strtotime($firstday),strtotime($lastday)])->order('ct_id desc')->paginate($limit,false)->toArray();
        }else{
            $list = Db::name('coin_downapply')->where($where)->field($field)->order('ct_id desc')->paginate($limit,false)->toArray();
        }

        if(!empty($list['data'])){
            foreach ($list['data'] as $k => $v) {
                $list['data'][$k]['amount'] = decimal_format($v['amount'],$coinfo['decimal_digits']);
                $list['data'][$k]['createtime'] = date('Y.m.d H:i:s',$v['createtime']);
                $list['data'][$k]['time'] = date('Y.m.d H:i:s',$v['createtime']);
                $list['data'][$k]['account'] = $v['ct_id'];//email_format($v['account']);

                if($v['status'] == 5){
                    $list['data'][$k]['status'] = 1; //提现成功
                }elseif($v['status'] == 6){
                    $list['data'][$k]['status'] = 2; //提现失败
                }elseif($v['status'] == 0 ){
                    $list['data'][$k]['status'] = 3;//审核中
                }elseif($v['status'] == 2 || $v['status'] == 3 ){
                    $list['data'][$k]['status'] = 4;//提现拒绝
                }elseif($v['status'] == 1 || $v['status'] == 4){
                    $list['data'][$k]['status'] = 5;//兑付中
                }elseif($v['status'] == 7){
                    $list['data'][$k]['status'] = 6;//已撤回
                }else{
                    $list['data'][$k]['status'] = 10;//未知状态
                }
            }
        }
        ouputJson(200,'',$list);
    }

    //发送提现验证码
    public function sendWtithdrawEmail(){
        $email = $this->userinfo['email'];
        if($email==""){
            ouputJson(201,lang('SENDFAIL_ACCNOEMAIL'));
        }

        $emailConfig = getSysconfig(['verification_email_title','verification_email_content']);
        $res = Email::sendEmail($email,$emailConfig['verification_email_title'],$emailConfig['verification_email_content']);

        
        // $res = Email::sendEmai($email,3);
        if($res['error']==200){
            ouputJson(200,lang('SEND_SUCCESS'),['emailid'=>$res['emailid']]);
        }else{
            ouputJson($res['error'],$res['msg']);
        }
    }

    //提交提现申请
    public function withdrawCoin(){
        $ci_id = $this->request->param('ci_id');
        $amount = $this->request->param('amount');
        $to_address = $this->request->param('to_address');
        $emailid = $this->request->param('emailid');
        $code = $this->request->param('code');
        $ui_id = $this->uid;
        if($ci_id==0 || $ci_id=="" || $amount=="" || $to_address=="" || $emailid=="" || $code==""){
            ouputJson(201,lang('FROM_ERROR'));
        }
        if (!preg_match('/^[-+]?(([0-9]+)([.]([0-9]+))?|([.]([0-9]+))?)$/', $amount)) {
            ouputJson(202,lang('INPUT_COMOUNT'));
        }

        //获取验证码
        $emidcode = $this->redis->get('emailid_'.$emailid);
        if($emailid=="" || $emidcode==""){
            ouputJson(202,lang('PLASE_SEND_CODE'));
        }

        if(Str::lower($code) != Str::lower($emidcode)){
            ouputJson(202,lang('CODE_ERROR'));
        }

        $coinfo = Db::name('coin_info')
        ->alias('ci')
        ->join('coin_feeconfig cf','ci.ci_id=cf.ci_id','left')
        ->field('ci.ci_id,ci.name,ci.short_name,ci.withdraw_address,cf.*')
        ->where(['ci.ci_id'=>$ci_id,'ci.status'=>1,'cf.status'=>1,'cf.fee_type'=>2])
        ->find();
        if(empty($coinfo)){
            ouputJson(203,lang('COIN_UNKNOW'));
        }
        //判断小数位数
        if(_getFloatLength($amount) > $coinfo['pointnum']){
            ouputJson(223,lang('INPUT_COMOUNT'));
        }

        //查询用户可用余额是否足够
        $userinfo = Db::name('user_info')
        ->alias('ui')
        ->join('user_finance uf','ui.ui_id=uf.ui_id','left')
        ->field('ui.ui_id,ui.account,uf.amount')
        ->where(['ui.ui_id'=>$ui_id,'uf.ui_id'=>$ui_id,'uf.ci_id'=>$coinfo['ci_id']])
        ->find();
        if(empty($userinfo)){
            ouputJson(211,lang('ACCOUNT_UNKNOW'));
        }

        if($coinfo['fee_mode'] == 1){ //固定手续费
            $fee = decimal_format($coinfo['fee'],$coinfo['pointnum'],false);
        }elseif($coinfo['fee_mode'] == 0){ //百分比手续费
            $feebf = decimal_format(bcdiv($coinfo['fee'],100,$coinfo['pointnum']),$coinfo['pointnum'],false);
            $fee = decimal_format(bcmul($amount, $feebf,$coinfo['pointnum']),$coinfo['pointnum'],false);
            // $fee = decimal_format(bcdiv($coinfo['fee'],100,$coinfo['pointnum']),$coinfo['pointnum'],false);
        }else{
            $fee = 0;
        }
        //提现数量低于手续费
        $feelimit = bccomp($amount,$fee,$coinfo['pointnum']);
        if($feelimit <= 0){
            ouputJson(210,lang('WTCOUNT_XYFEE'));
        }
        //判断单次最大最小限制
        $maxlimit = bccomp($amount,decimal_format($coinfo['single_maxlimit'],$coinfo['pointnum'],false),$coinfo['pointnum']);
        $minlimit = bccomp($amount,decimal_format($coinfo['single_minlimit'],$coinfo['pointnum'],false),$coinfo['pointnum']);
        if($maxlimit == 1){
            ouputJson(204,lang('DCOUNT_MAX').decimal_format($coinfo['single_maxlimit'],$coinfo['pointnum'],false));
        }
        if($minlimit < 0){
            ouputJson(204,lang('DCOUNT_MIN').decimal_format($coinfo['single_minlimit'],$coinfo['pointnum'],false));
        }
        
        $pday['ci_id'] = $ci_id;
        $pday['ui_id'] = $ui_id;
        $pday['status'] = [0,1,5,6];
        //判断单日人累计
        $peramount = Db::name('coin_downapply')->where($pday)->where('createtime','>=',strtotime(date('Y-m-d 00:00:00')))->where('createtime','<=',time())->sum('amount');
        $daymelimit = bccomp($peramount,decimal_format($coinfo['day_singletotallimit'],$coinfo['pointnum'],false),$coinfo['pointnum']);
        if($daymelimit == 0 || $daymelimit == 1){
            ouputJson(205,lang('DAYMY_END'));
        }
        unset($pday['ui_id']);
        //判断单日总累计
        $daytotamount = Db::name('coin_downapply')->where($pday)->where('createtime','>=',strtotime(date('Y-m-d 00:00:00')))->where('createtime','<=',time())->sum('amount');
        $datotlimit = bccomp($daytotamount,decimal_format($coinfo['day_totallimit'],$coinfo['pointnum'],false),$coinfo['pointnum']);
        if($datotlimit == 0 || $datotlimit == 1){
            ouputJson(206,lang('DAYMY_END'));
        }
        //判断年总累计
        $yearlimit['ci_id'] = $ci_id;
        $yearlimit['ui_id'] = $ui_id;
        $yearlimit['status'] = [0,1,4,5,6];
        $yeartotamount = Db::name('coin_downapply')->where($yearlimit)->where('createtime','>=',strtotime(date('Y-01-01 00:00:00')))->where('createtime','<=',time())->sum('amount');
        $yeartotlimit = bccomp($yeartotamount,decimal_format($coinfo['year_totallimit'],$coinfo['pointnum'],false),$coinfo['pointnum']);
        if($yeartotlimit == 0 || $yeartotlimit == 1){
            ouputJson(206,lang('YEAR_END'));
        }
        //判断账户余额是否足够
        $balance = bccomp($userinfo['amount'],$amount,$coinfo['pointnum']);
        if($balance < 0){
            ouputJson(207,lang('BALANCE_NOTINVA'));
        }
        $wafter = bcsub($userinfo['amount'],$amount,$coinfo['pointnum']);
        //扣除手续费
        $feeval = bcsub($amount,$fee,$coinfo['pointnum']);

        //是否开启自动审核
        if($coinfo['autoapprove_flag'] == 1){
            //判断临界值 - 状态
            $down_limit = bccomp($amount,decimal_format($coinfo['down_limit'],$coinfo['pointnum'],false),$coinfo['pointnum']);
            if($down_limit ==0 || $down_limit == 1){
                $status = 0;
            }else{
                $status = 1;
            }
        }else{
            $status = 0;
        }
        $orderno = create_orderno();
        $setData = set_user(5,$fee,1,$ci_id);//添加手续费账户
        Db::startTrans();//开启事务
        // $suc = Db::name('user_finance')->where(['ui_id'=>$ui_id,'ci_id'=>$ci_id])->setDec('amount',$amount);

        $suc = updateUserBalance($ui_id,$ci_id,"withdraw",[['field'=>'amount','type'=>'dec','val'=>$amount]]);

        $rescode = set_user_amount("withdraw",$setData); //添加手续费账户
        if($suc > 0 && $rescode > 0){
            $data['ui_id'] = $ui_id;
            $data['ci_id'] = $ci_id;
            $data['coin_name'] = $coinfo['name'];
            $data['account'] = $userinfo['account'];
            $data['from_address'] = $coinfo['withdraw_address'];
            $data['to_address'] = $to_address;
            $data['status'] = $status;
            $data['fee'] = $fee;
            $data['amount'] = $amount;
            $data['feeval'] = $feeval;
            $data['orderno'] = $orderno;
            $data['pointnum'] = $coinfo['pointnum'];
            $data['createtime'] = time();
            $data['before_limit'] = $userinfo['amount'];
            $data['after_limit'] = $wafter;
            $applysuc = Db::name('coin_downapply')->insert($data);
            if($applysuc > 0){
                $this->redis->del('emailid_'.$emailid);//删除验证码
                Db::commit();
                if($status == 1 || $status){
                    //提交异步发币请求
                    try {
                        $swdata = ['orderno'=>$orderno];
                        $client = new \swoole_client(SWOOLE_SOCK_TCP);
                        $ret = $client->connect('127.0.0.1', 9521, 0.5);
                        if(empty($ret)){
                            ouputJson(202,'error!connect to swoole_server failed');
                        }else{
                            $client->send(json_encode($swdata));
                            $client->close();
                            ouputJson(200,'send success');
                        }
                    } catch (\Exception $e) {
                        ouputJson(200,'system error!');
                    }
                }
                ouputJson(200,lang('WT_APPLY_SUC'));
            }else{
                Db::rollback();
                ouputJson(208,lang('WT_APPLY_FAIL'));
            }
        }else{
            Db::rollback();
            ouputJson(209,lang('WT_APPLY_FAIL'));
        }
    }

    //撤回提现申请
    public function retractWithdraw(){
        $ct_id = $this->request->param('ct_id');
        $ci_id = $this->request->param('ci_id');
        $ui_id = $this->uid;
        if($ct_id=="" || $ci_id==""){
            ouputJson(201,lang('FROM_ERROR'));
        }
        $applyinfo = Db::name('coin_downapply')->where(['ci_id'=>$ci_id,'ct_id'=>$ct_id,'ui_id'=>$ui_id])->find();
        if(empty($applyinfo)){
            ouputJson(202,lang('WT_APPLY_UNKOW'));
        }
        if($applyinfo['status']!=0){
            ouputJson(203,lang('CHING_WTAPPLY'));
        }
        $feelimit = bccomp($applyinfo['fee'],0,$applyinfo['pointnum']);
        if($feelimit['fee'] == 1){
            $setData = set_user(5,$applyinfo['fee'],2,$ci_id);
            Db::startTrans();
            $resCode = set_user_amount("recallwith",$setData); //扣除手续费账户余额
        }else{
            Db::startTrans();
            $resCode = 1;
        }
        // $setData = set_user(5,$applyinfo['fee'],2,$ci_id);
        // $resCode = set_user_amount("recallwith",$setData); //扣除手续费账户余额
        if($resCode > 0){
            $pointnum = $applyinfo['pointnum']=="" ? 13 : $applyinfo['pointnum'];
            $val = decimal_format(bcadd($applyinfo['fee'], $applyinfo['amount'],$pointnum),$pointnum,false);
            // $suc = Db::name('user_finance')->where(['ui_id'=>$ui_id,'ci_id'=>$ci_id])->setInc('amount',$val);

            $suc = updateUserBalance($ui_id,$ci_id,"recallwith",[['field'=>'amount','type'=>'inc','val'=>$amount]]);

            if($suc > 0){
                //更新状态
                $applystatus = Db::name('coin_downapply')->where(['ci_id'=>$ci_id,'ct_id'=>$ct_id,'ui_id'=>$ui_id])->update(['status'=>7,'updatetime'=>time()]);
                if($applystatus >0){
                    Db::commit();
                    ouputJson(200,lang('CHSUCCESS'));
                }else{
                    Db::rollback();
                    ouputJson(207,lang('CHFAIL').'(-102)');
                }
            }else{
                Db::rollback();
                ouputJson(206,lang('CHFAIL').'(-103)');
            }
        }elseif($resCode < 0){
            Db::rollback();
            ouputJson(204,lang('CHFAIL').'(-101)');
        }else{
            Db::rollback();
            ouputJson(205,lang('CHFAIL').'(-105)');
        }
    }
}
