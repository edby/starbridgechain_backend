<?php

namespace app\business\controller;
use think\Config;
use curl\Curl;
use think\Db;
use think\Controller;

use app\business\model\rechargeAddress;
use app\business\model\coinUprecord;
use app\business\model\coinDownrecord;
use think\facade\App;
use Web3\Web3;

class Task extends Controller
{
	function __construct(){
        // echo json_encode(['proto'=>'http','host'=>'47.74.137.159','account'=>'starbridgeadmin1','password'=>'YCpePYEuXN9pMo1HJ21883pB','port'=>'8323']);
        // die;
        parent::__construct();
        ini_set('max_execution_time','100');
        //ff3e4b30f03d20a8bb551ad9779045ea33771d8575b7ddf00bef793abec897a6
    }


    //充值入口
    public function notifyCharge(){

        $param = [
                    ['field'=>'amount','type'=>'inc','val'=>'1'],
                    ['field'=>'trans_frost','type'=>'dec','val'=>'0'],
                ];
        echo updateUserBalance(1,1,"recharge",$param);

        die;
        
        $ci_id = $this->request->param('ci_id');
        $tx_hash = $this->request->param('tx_hash');
        if($ci_id==0 || $ci_id=="" || $tx_hash==""){
            ouputJson(201,'请求参数错误');
        }
        //查询币种是否存在
        $coinfo = Db::name('coin_info')->where(['ci_id'=>$ci_id,'status'=>1])->find();
        if(empty($coinfo)){
            ouputJson(201,'币种不存在或不可用');
        }

        //查询是否存在此项充值
        $hasinfo = Db::name('coin_uprecord')->where(['tx_hash'=>$tx_hash])->find();
        if(!empty($hasinfo)){
            ouputJson(202,'已存在此项充值');
        }
        //读取已存在的hash文件

        $rootPath = App::getRootPath();
        $hash_dir ="{$rootPath}/recharge/charge/transaction/";
        $hashs = scandir($hash_dir);
        if(!empty($hashs)){
            foreach ($hashs as $t_id) {
                $fileinfo = explode('_', $t_id);
                if(isset($fileinfo[1])){
                    $hashed = str_replace(".lock", "", $fileinfo[1]);
                    if($hashed == $tx_hash){
                        ouputJson(203,'已存在此项充值');
                    }
                }
            }
        }
        //写入hash文件
        try {
            $save_path=$hash_dir."{$ci_id}_{$tx_hash}"; 
            $fp=fopen($save_path,"w+");
            $cus = fwrite($fp,"{$ci_id}_{$tx_hash}");
            fclose($fp); 
            ouputJson(200,'success');
        } catch (\Exception $e) {
            ouputJson(200,$e->getMessage());
        }


        // try {
        //     //异步充值
        //     $data = ['hash'=>$tx_hash,'ci_id'=>$ci_id];
        //     $client = new \swoole_client(SWOOLE_SOCK_TCP);
        //     $ret = $client->connect('127.0.0.1', 9501, 0.5);
        //     if(empty($ret)){
        //         $this->syncCharge($tx_hash,$ci_id);
        //         ouputJson(202,'error!connect to swoole_server failed');
        //     }else{
        //         $client->send(json_encode($data));
        //         $client->close();
        //         ouputJson(200,'发送成功');
        //     }
        //     // ouputJson(200,'发送成功');
        // } catch (\Exception $e) {
        //     $this->syncCharge($tx_hash,$ci_id);
        // }
    }

    //同步充值
    private function syncCharge($hash,$ci_id){
        $exitsList = Db::name('coin_uprecord')->field('cuu_id')->where(['ci_id'=>$ci_id,'tx_hash'=>$hash])->find();
        if(!empty($exitsList)){
            wirteLog("unkonw:sync | hash:{$hash} | ci_id:{$ci_id} | 重复充值，已取消充值");
            ouputJson(201,'重复充值');
        }


        $coinfo = Db::name('coin_info')
        ->alias('ci')
        ->join('coin_feeconfig cf','ci.ci_id=cf.ci_id','left')
        ->field('ci.ci_id,ci.coin_type,ci.short_name as coin_name,ci.confirmations,ci.rpcinfo,cf.single_minlimit,cf.pointnum')
        ->where(['ci.ci_id'=>$ci_id,'cf.fee_type'=>1,'ci.status'=>1])
        ->find();
        if(!empty($coinfo)){
            if($coinfo['coin_type'] == 1){ //btc类型
                $this->btcSyncs($coinfo,$hash);
            }elseif($coinfo['coin_type'] == 2){ //eth类型
                $this->ethSyncs($coinfo,$hash);

            }else{
                //不支持的充币类型
                wirteLog("unkonw:sync | hash:{$hash} | ci_id:{$ci_id} | 不支持的充币类型。");
                ouputJson(201,'不支持的充币类型。');
            }
        }else{
            wirteLog("unkonw:sync | hash:{$hash} | ci_id:{$ci_id} | 币种不存在或不可用。");
            ouputJson(201,'币种不存在或不可用。');
            //未查询到币种数据
        }
    }


    //BTC充值
    private function btcSyncs($coinfo,$hash){
        try {
            $rpcinfo = json_decode($coinfo['rpcinfo'],true);
            $transaction = get_btc_transaction($rpcinfo,$hash);
            if(empty($transaction)){
                //未查询到交易
                wirteLog("btc:sync | hash:{$hash} | ci_id:{$coinfo['ci_id']} | btc区块未查询到此笔交易。");
                ouputJson(201,'btc区块未查询到此笔交易。');
            }
            if($transaction['confirmations'] < $coinfo['confirmations']){
                wirteLog("btc:sync | hash:{$hash} | confirmations:{$transaction['confirmations']} | ci_id:{$coinfo['ci_id']} | btc区块确认数未达要求。");
                ouputJson(201,'btc区块确认数未达要求。');
            }
            foreach ($transaction['details'] as $detail) {
                if($detail['category'] == 'receive'){
                    $single_minlimit = decimal_format($coinfo['single_minlimit'],$coinfo['pointnum'],false);
                    $comp = bccomp($detail['amount'],$single_minlimit,$coinfo['pointnum']);
                    if($comp==0 || $comp==1){
                        $userinfo = query_address_info($detail['address'],$coinfo['ci_id']);
                        if(empty($userinfo)){
                            //未查询到地址对应的账户 - 币种关闭状态
                            wirteLog("btc:sync | hash:{$hash} | ci_id:{$coinfo['ci_id']} | 充值转入地址不存在系统用户绑定地址内。");
                            ouputJson(201,'充值转入地址不存在系统用户绑定地址内。');
                        }
                        $ui_id = $userinfo['ui_id'];
                        Db::startTrans();//开启事务

                        $newbalance = bcadd($detail['amount'], decimal_format($userinfo['amount'],$coinfo['pointnum'],false),$coinfo['pointnum']);
                        $sucinx = Db::name('user_finance')->where(['ci_id'=>$coinfo['ci_id'],'ui_id'=>$userinfo['ui_id']])->update(['amount'=>$newbalance]);
                        if($sucinx <= 0){
                            Db::rollback();
                            wirteLog("btc:sync | hash:{$hash} | ci_id:{$coinfo['ci_id']} | ui_id:{$ui_id} | 账户余额更新失败,更新的余额为：{$newbalance}");
                            Db::name('coin_uprecord_failog')->insert(['ci_id'=>$coinfo['ci_id'],'ui_id'=>$ui_id,'coin_name'=>$coinfo['coin_name'],'account'=>$userinfo['account'],'to_address'=>$detail['address'],'val'=>$detail['amount'],'hash'=>$hash]);
                            ouputJson(201,'账户余额更新失败');
                        }
                        //添加充值记录
                        $chargeLog['ui_id'] = $userinfo['ui_id'];
                        $chargeLog['ci_id'] = $coinfo['ci_id'];
                        $chargeLog['account'] = $userinfo['account'];
                        $chargeLog['to_address'] = $detail['address'];
                        $chargeLog['tx_hash'] = $hash;
                        $chargeLog['amount'] = $detail['amount'];
                        $chargeLog['uptime'] = time();
                        $chargeLog['status'] = 1;
                        $losuc = Db::name('coin_uprecord')->insert($chargeLog);
                        if($losuc <= 0){
                            Db::rollback();
                            wirteLog("btc:sync | hash:{$hash} | ci_id:{$coinfo['ci_id']} | ui_id:{$ui_id} | 充值成功记录失败");

                            Db::name('coin_uprecord_failog')->insert(['ci_id'=>$coinfo['ci_id'],'ui_id'=>$ui_id,'coin_name'=>$coinfo['coin_name'],'account'=>$userinfo['account'],'to_address'=>$detail['address'],'val'=>$detail['amount'],'hash'=>$hash]);
                            die;
                        }
                        Db::commit();
                        ouputJson(200,'充值成功');
                    }else{
                        wirteLog("btc:sync | hash:{$hash} | ci_id:{$coinfo['ci_id']} | address:{$detail['address']} | 低于最低充值数量，充值数为：{$detail['amount']},要求最低充值数为：{$single_minlimit}。");
                        ouputJson(201,'低于最低充值数');
                    }
                }
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
            wirteLog("unkonw-error:btc:sync | 充值流程异常，error:{$error}。");
            ouputJson(201,'充值流程异常');
        }
    }

    //ETH充值
    private function ethSyncs($coinfo,$hash){
        try {
            $transaction = get_eth_transaction($coinfo['rpcinfo'],$hash);
            if(!empty($transaction) && isset($transaction['result']) && !empty($transaction['result'])){
                //判断区块数
                $confirmations = get_block_number($coinfo['rpcinfo']) - hexdec($transaction['result']['blockNumber']);
                if($confirmations < $coinfo['confirmations']){
                    wirteLog(":sync | hash:{$hash} | confirmations:{$confirmations} | ci_id:{$coinfo['ci_id']} | btc区块确认数未达要求。");
                    ouputJson(201,'区块确认数未达要求。');
                }
                $detail = $transaction['result'];
                $userinfo = query_address_info($detail['to'],$coinfo['ci_id']);
                if(empty($userinfo)){
                    wirteLog(":sync | hash:{$hash} | ci_id:{$coinfo['ci_id']} | 充值转入地址不存在系统用户绑定地址内。");
                    ouputJson(201,'充值转入地址不存在系统用户绑定地址内。');
                }
                $ui_id = $userinfo['ui_id'];
                Db::startTrans();//开启事务

                $value = hexdec($detail['value']) / (pow(10, $coinfo['pointnum']));
                $newbalance = bcadd($value, decimal_format($userinfo['amount'],$coinfo['pointnum'],false),$coinfo['pointnum']);

                $sucinx = Db::name('user_finance')->where(['ci_id'=>$coinfo['ci_id'],'ui_id'=>$userinfo['ui_id']])->update(['amount'=>$newbalance]);
                if($sucinx <= 0){
                    Db::rollback();
                    wirteLog("btc:sync | hash:{$hash} | ci_id:{$coinfo['ci_id']} | ui_id:{$ui_id} | 账户余额更新失败,更新的余额为：{$newbalance}");


                    Db::name('coin_uprecord_failog')->insert(['ci_id'=>$coinfo['ci_id'],'ui_id'=>$ui_id,'coin_name'=>$coinfo['coin_name'],'account'=>$userinfo['account'],'from_address'=>$detail['from'],'to_address'=>$detail['to'],'val'=>$value,'hash'=>$hash]);

                    ouputJson(201,'账户余额更新失败');
                }
                //添加充值记录
                $chargeLog['ui_id'] = $userinfo['ui_id'];
                $chargeLog['ci_id'] = $coinfo['ci_id'];
                $chargeLog['account'] = $userinfo['account'];
                $chargeLog['to_address'] = $detail['to'];
                $chargeLog['tx_hash'] = $hash;
                $chargeLog['amount'] = $value;
                $chargeLog['uptime'] = time();
                $chargeLog['status'] = 1;
                $losuc = Db::name('coin_uprecord')->insert($chargeLog);
                if($losuc <= 0){
                    Db::rollback();
                    Db::name('coin_uprecord_failog')->insert(['ci_id'=>$coinfo['ci_id'],'ui_id'=>$ui_id,'coin_name'=>$coinfo['coin_name'],'account'=>$userinfo['account'],'from_address'=>$detail['from'],'to_address'=>$detail['to'],'val'=>$value,'hash'=>$hash]);

                    wirteLog("btc:sync | hash:{$hash} | ci_id:{$coinfo['ci_id']} | ui_id:{$ui_id} | 充值成功记录失败");
                    die;
                }
                Db::commit();
                ouputJson(200,'充值成功');

            }else{
                //未查询到交易
                wirteLog("sync | hash:{$hash} | ci_id:{$coinfo['ci_id']} | 区块未查询到此笔交易。");
                ouputJson(201,'未查询到此笔交易。');
            }
            
        } catch (\Exception $e) {
            $error2 = $e->getMessage();
            wirteLog("unkonw-error:sync | 充值流程异常，error:{$error2}。");
            ouputJson(201,'充值流程异常');
        }
    }

    //自发充值
    private function otherSyncs(){

    }

}
