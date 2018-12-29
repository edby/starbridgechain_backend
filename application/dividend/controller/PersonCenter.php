<?php

namespace app\dividend\controller;



use think\Db;
use app\common\controller\AuthBase;
use think\Request;
use think\Controller;

/*锁仓 */
class PersonCenter extends AuthBase
{

    /* 锁仓* */
    public function lockSdt(Request $request){
        $num = $request->param('num');
        $type = $request->param('type');
        $user_id=$this->uid;
        //$user_id=41894;
        $arr=['1','2'];
        if(!in_array($type,$arr)){
            return json(['status' => 402,'code'=>10003,'msg' => 'type,必须为1或者2!','e_msg'=>'The type has to be 1 or 2']);
        }

        //查询锁仓金额限制
        $setting=Db::table('u_setting_lock')->order('create_time desc')->limit(1)->select();
        if($num <= 0||$num <$setting[0]['sdt_base']){
            return json(['status' => 402,'code'=>10003,'msg' => '请确认锁仓金额,金额必须>='.$setting[0]['sdt_base'],'e_msg'=>'Please confirm the amount of lock bin,the amount must be greater than 1000!']);
        }

        //查询账户可用余额
        $total_sdt = Db::name('user_finance')->where(['ui_id' =>$user_id,'ci_id'=>1])->find();
        if($num > $total_sdt['amount']) {
            return json(['status' => 402,'code'=>10003,'msg' => '账户可用余额不足!','e_msg'=>'Insufficient available balance !']);
        }
        try {

            Db::startTrans();
            /*财产表,增加锁仓数量*/
            Db::name('user_finance')->where(['ui_id' =>$user_id,'ci_id'=>1])->setInc('lock_amount',$num);
            /*减去账户里面的sdt余额*/
            Db::table('user_finance')->where(['ui_id' =>$user_id,'ci_id'=>1])->setDec('amount',$num);

            //插入锁仓记录表
            $lock_time=date("Y-m-d H:i:s",strtotime("+3 months"));

            $re=Db::name('u_sdt_lock')->insert([
                'batch'=>$setting[0]['batch'],
                'user_id'=>$user_id,
                'lock_nums'=>$num,
                'lock_time'=>$lock_time,
                'type'=> $type,
                'create_time'=>date("Y-m-d H:i:s")

            ]);

            Db::commit();
            return json(['status' => 200,'msg' => '锁仓成功!','e_msg'=>'Lock up success !','data'=>['locak_time'=>$lock_time,'lock_times'=>3]]);

        } catch (\Exception $e){
            Db::rollback();
            return json(['status' => 402,'code'=>10003,'msg' => '锁仓失败，请稍后再试!','e_msg'=>'Lock up failure !']);
        }


    }



    /*查询锁仓列表* */
    public function lockList(){
        $uid = $this->uid;
        //$uid=41894;
        $locks=Db::name('u_sdt_lock')->where(['user_id'=>$uid])->order('create_time desc')->select();
        return json(['status' => 200,'msg' => '成功','e_msg'=>'success!','data'=>$locks]);
    }

    /*锁仓续期修改* */
    public function editLockTime(Request $request){
        $type = $request->param('type');
        $id = $request->param('id');
        $arr=['1','2'];
        if(!in_array($type,$arr)){
            return json(['status' => 402,'msg' => 'type,必须为1或者2!','e_msg'=>'The type has to be 1 or 2']);
        }
        if($type==1){   //锁仓续期，到期的时候锁仓额不满足最低锁仓余额，不能续期锁仓
            $lock_nums=Db::name('u_sdt_lock')->where(['id'=>$id,'status'=>1])->value('lock_nums');
            $base=Db::name('u_setting_lock')->order('create_time desc')->limit(1)->select();

            if($lock_nums<$base[0]['sdt_base']){
                return json(['status' => 201,'msg' => '锁仓余额,不能小于最低锁仓额,无法继续锁仓！','e_msg'=>'failed!']);
            }
        }

        Db::name('u_sdt_lock')->where(['id'=>$id,'status'=>1])->setField('type',$type);
        return json(['status' => 200,'msg' => '成功','e_msg'=>'success!']);

    }

    /**分红记录*/
    public function getAlloList(Request $request)
    {

        $page = $request->param('page' , 1);
        $limit = $request->param('limit' , 10);
        $type=$request->param('type','');
        $status=$request->param('status',1);
        $create_time=$request->param('create_time','');

        $where=[];
        if($type!='')
            $where[]=['u_btc_bak.type','=',$type];
        $where[]=['u_btc_bak.status','=',$status];
        $user_id=$this->uid;
        $where[]=['u_btc_bak.user_id','=',$user_id];

        if($create_time!='')
            $where[]=['u_btc_bak.time','like',"%".$create_time."%"];


        $info = Db::name('u_btc_bak')
            ->field('u_btc_bak.allocation,u_btc_bak.time,u_btc_bak.type,u_btc_bak.status,coin_info.logo,coin_info.short_name as coin_name')
            ->join('coin_info','coin_info.ci_id=u_btc_bak.type')
            ->where($where)
            ->limit(((int)$page-1)*(int)$limit,(int)$limit)
            ->order('time desc')
            ->select();

        foreach($info as $k=>$v){
            $info[$k]['coin_logo']=config('admin_http_url').$v['logo'];
        }
        $count = Db::name('u_btc_bak')
            ->where($where)
            ->count();

        $coin_info=Db::name('coin_info')->field('ci_id,short_name,logo')->where(['status'=>1])->select();
        $coin_data=[];
        foreach($coin_info as $item){
            $temp_coin=[];
            $temp= Db::name('u_btc_bak')
            ->where(['user_id'=>$user_id,'type'=>$item['ci_id']])
            ->sum("allocation");

            $temp_coin['name']=$item['short_name'];
            $temp_coin['num']=$temp;
            $temp_coin['coin_logo']=config('admin_http_url').$item['logo'];
            $coin_data[]=$temp_coin;
        }

        return json(['status' => 200,'msg' => '成功','coin_data'=>$coin_data,'count'=>$count,'data'=>$info,'e_msg'=>'success!']);
    }


}
