<?php
namespace app\admin\controller;

use think\Controller;
use think\Request;
use think\Db;
use app\admin\controller\Admin;
use app\admin\model\Downapply;
use app\admin\model\Coinfo;
use excel\excelclass\ExcelExport;
class Recharge extends Admin
{

    public function celtest(){
        $title = ['coin_name'=>'币种','account'=>'用户账号','from_address'=>'转出地址','to_address'=>'转入地址'];

        $filename = '登录日志';

        $data = Db::name('coin_downapply')->field('coin_name,account,from_address,to_address')->where(['ci_id'=>2])->select();

        export_excel_zip($filename,$title,$data);

    }




    //获取提现申请列表
    public function withdrawList(){
        $limit = 15;
        $status = $this->request->param('status');
        $startime = $this->request->param('startime');
        $endtime = $this->request->param('endtime');
        $account = $this->request->param('account');
        $ci_id = $this->request->param('ci_id');
        $is_export = $this->request->param('is_export');
        $is_export = $is_export=="" ? 0 : $is_export;


        if($ci_id=="" || $ci_id==0){
            ouputJson(201,'币种ID不能为空');
        } 
        $where['ci_id'] = $ci_id;
        if($status!=""){
            $where['status'] = $status;
        }

        if($account!=""){
            $where['account'] = $account;
        }
        if($is_export){
            if($startime!="" && $endtime!=""){
                $start = strtotime($startime." 00:00:00");
                $end = strtotime($endtime." 23:59:59");
                $list = Downapply::where($where)->whereTime('createtime','between',[$start,$end])->order('createtime desc,status desc')->select();
            }else{
                $list = Downapply::where($where)->order('createtime desc,status desc')->select();
            }
            if(!empty($list)){
                $exportData = [];
                foreach ($list as $k => $v) {
                    $exportData[] = [
                        'account'=>$v['account'],
                        'coin_name'=>$v['coin_name'],
                        'tx_hash'=>$v['tx_hash'],
                        'type'=>'提现',
                        'amount'=>$v['amount'],
                        'from_address'=>$v['from_address'],
                        'to_address'=>$v['to_address'],
                        'status_dec'=>drawwith_status($v['status']),
                        'updatetime'=>$v['updatetime']!="" ? date('Y/m/d H:i:s',$v['updatetime']) : "-",
                        'createtime'=>$v['createtime']!="" ? date('Y/m/d H:i:s',$v['createtime']) : "-",
                    ];
                }
                $title = [
                    'account'=>'用户名',
                    'coin_name'=>'币种',
                    'tx_hash'=>'HASH/订单号',
                    'type'=>'类型',
                    'amount'=>'来源数量',
                    'from_address'=>'来源账户',
                    'to_address'=>'去向账户',
                    'status_dec'=>'状态',
                    'updatetime'=>'去向时间',
                    'createtime'=>'创建时间',
                ];
                $filename = date('Ymd')."_提现记录";
                export_excel_zip($filename,$title,$exportData);
            }else{
                ouputJson(203,'无可导出数据');
            }
        }else{
            if($startime!="" && $endtime!=""){
                $start = strtotime($startime." 00:00:00");
                $end = strtotime($endtime." 23:59:59");
                $list = Downapply::where($where)->whereTime('createtime','between',[$start,$end])->order('createtime desc,status desc')->paginate($limit,false)->toArray();
            }else{
                $list = Downapply::where($where)->order('createtime desc,status desc')->paginate($limit,false)->toArray();
            }
            if(!empty($list['data'])){
                foreach ($list['data'] as $k => $v) {
                    $list['data'][$k]['createtime'] = $v['createtime']!="" ? date('Y/m/d H:i:s',$v['createtime']) : "-";
                    $list['data'][$k]['updatetime'] = $v['updatetime']!="" ? date('Y/m/d H:i:s',$v['updatetime']) : "-";
                    $list['data'][$k]['fee'] = decimal_format($v['fee'],$v['pointnum'],false);
                    $list['data'][$k]['amount'] = decimal_format($v['amount'],$v['pointnum'],false);
                    $list['data'][$k]['before_limit'] = decimal_format($v['before_limit'],$v['pointnum'],false);
                    $list['data'][$k]['after_limit'] = decimal_format($v['after_limit'],$v['pointnum'],false);
                    $list['data'][$k]['status_dec'] = drawwith_status($v['status']);

                    if($is_export){
                        $exportData[] = [
                            'account'=>$v['account'],
                            'coin_name'=>$v['coin_name'],
                            'tx_hash'=>$v['tx_hash'],
                            'type'=>'提现',
                            'amount'=>$v['amount'],
                            'from_address'=>$v['from_address'],
                            'to_address'=>$v['to_address'],
                            'status_dec'=>$v['status_dec'],
                            'updatetime'=>$v['updatetime'],
                            'createtime'=>$v['createtime'],
                        ];
                    }
                }
            }
            ouputJson(200,'',$list);
        }

    }


    //审批提现
    public function eaaWithdraw(){
        $ctids = $this->request->param('ctids');
        $ci_id = $this->request->param('ci_id');
        $status = $this->request->param('status'); // 1同意 2冻结拒绝审核 3返还拒绝审核
        $fail_reason = $this->request->param('fail_reason'); // 1同意 2冻结拒绝审核 3返还拒绝审核
        if($ctids == "" || ($status != 1 && $status != 2 && $status != 3) || $ci_id==0 || $ci_id==""){
            ouputJson(201,'业务参数错误！');
        }
        $ctids = explode(',', $ctids);
        if(empty($ctids)){
            ouputJson(202,'审批项ID不能为空！');   
        }
        if(count($ctids) >=10){
            ouputJson(203,'单词审批项数量不可超过10条！');
        }
        $where['status'] = 0;
        $where['ci_id'] = $ci_id;
        $where['ct_id'] = $ctids;
        $list = Db::name('coin_downapply')->where($where)->select();
        if(empty($list)){
            ouputJson(204,'未查询到记录列表');
        }
        
        //查询币种信息
        $coin_type = Coinfo::where(['ci_id'=>$ci_id])->value('coin_type');
        if($coin_type==1 || $coin_type==2){
            //生成统一单号
            $orderno = create_orderno();
            Db::startTrans();
            $updateData['status'] = $status;
            $updateData['orderno'] = $orderno;
            if($fail_reason!=""){
                $updateData['fail_reason'] = $fail_reason;
            }
            $suc = Db::name('coin_downapply')->where(['ct_id'=>$ctids])->update($updateData);
            if($suc > 0){
                if($status == 1){
                    try {
                        Db::commit(); //提交事务
                        ouputJson(200,'审批成功');

                        //提交异步任务
                        // $data = ['orderno'=>$orderno];
                        // $client = new \swoole_client(SWOOLE_SOCK_TCP);
                        // $ret = $client->connect('127.0.0.1', 9521, 0.5); //9521端口是提现异步任务端口
                        // if(empty($ret)){
                        //     Db::rollback();
                        //     ouputJson(302,'error!connect to swoole_server failed');
                        // }else{
                        //     $client->send(json_encode($data));
                        //     $client->close();
                        //     Db::commit(); //提交事务
                        //     ouputJson(200,'审批成功');
                        // }
                    } catch (\Exception $e) {
                        Db::rollback();
                        ouputJson(303,'error!connect to swoole_server failed');
                    }
                }elseif($status == 3){ //拒绝 退还提现金额
                    Db::commit(); //提交事务
                    ouputJson(200,'审批成功');

                }else{ //拒绝 冻结提现金额
                    Db::commit(); //提交事务
                    ouputJson(200,'审批成功');
                }
            }else{
                Db::rollback();
                ouputJson(206,'审批失败，请稍后再试');
            }
        }else{
            ouputJson(205,'不支持此币种提现');
        }
    }
}