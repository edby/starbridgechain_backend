<?php

namespace app\dividend\controller;

/*
 * 分红
 * */

use think\Db;
use think\Controller;
use think\Request;
use app\common\service\Email;
class Page extends Controller
{


    /*前台获取最低锁仓参数*/
    public function getLockNum(Request $request){
        //查询锁仓金额限制
        $lockNum=Db::table('u_setting_lock')->order('create_time desc')->limit(1)->select();
        $sdtNum=Db::table('u_setting_btc')->order('create_time desc')->limit(1)->select();
        $data['lock_base']=$lockNum[0]['sdt_base'];
        $data['sdt_base']=$sdtNum[0]['sdt_base'];
        $data['coefficient']=$sdtNum[0]['coefficient'];
        return json(['status' => 200,'msg' => '获取成功','data'=>$data]);
    }


    /*分红--------页面显示分红规则，基数 */
    public function displayCal(){
        $data=Db::table('u_btc_base')->order('create_time desc')->limit(1)->select();
        return json(['status' => 200,'msg' => '页面显示计算!','data'=>$data]);
    }


    /*分红--------分红排行显示,累计分红----旧版本* */
    public function  displayRan(){
        $info = Db::name('u_rankings')
            ->field('a.*,user.name')
            ->alias('a')
            ->join('user_info user','a.user_id=user.ui_id ','LEFT')
            ->where(['a.type'=>2])
            ->order('total','desc')
            ->limit(30)
            ->select();
        return json(['status' => 200,'msg' => '获取成功!','data'=>$info]);
    }
    /*分红--------分红排行显示,累计分红----新版本* */
    public function  displayRanNew(){
        $info = Db::name('u_rankings_new')
            ->field('a.*,user.name')
            ->alias('a')
            ->join('user_info user','a.user_id=user.ui_id ','LEFT')
            ->where(['a.type'=>2])
            ->order('total','desc')
            ->limit(30)
            ->select();
        return json(['status' => 200,'msg' => '获取成功!','data'=>$info]);
    }

    /*分红--------昨日SDT和BTC排行*/
    public function  yesRan(){

        $today = date("Y-m-d");
        $num=Db::table('u_btc_bak')
            ->where('time' ,'>=',$today." 00:00:00")
            ->where('time','<=',$today . " 23:59:59")
            ->count();

        if($num==0){
            $time = date("Y-m-d",strtotime("-1 day"));
        }else{
            $time=$today;
        }

        $info['btc']=Db::table('u_btc_bak')
            ->field('a.allocation,user.name')
            ->alias('a')
            ->join('user_info user','a.user_id=user.ui_id ','LEFT')
            ->where('time' ,'>=',$time." 00:00:00")
            ->where('time','<=',$time . " 23:59:59")
            ->where(['type'=>2])
            ->order('allocation desc')
            ->limit(30)
            ->select();

        $info['sdt']=Db::table('u_btc_bak')
            ->field('a.allocation,user.name')
            ->alias('a')
            ->join('user_info user','a.user_id=user.ui_id ','LEFT')
            ->where('time' ,'>=',$time." 00:00:00")
            ->where('time','<=',$time . " 23:59:59")
            ->where(['type'=>1])
            ->order('allocation desc')
            ->limit(30)
            ->select();
        return json(['status' => 200,'msg' =>'成功','data'=>$info]);
    }


    /*分红--------最新成交显示排行*/
    public function latestDeal(){
        $table='market_trade_log'.date("Y").'_1';//表名字
        $time = date("Y-m-d");

        $price = Db::name($table)
            ->field(['price'])
            ->where('create_time' ,'>=',strtotime($time." 00:00:00"))
            ->where('create_time','<=',strtotime($time . " 23:59:59"))
            ->order('price desc')
            ->find();

        if($price==null){
            return json(['status' => 200,'msg' => '没有sdt交易记录','data'=>[]]);
        }
        $sql="select a.mt_order_ui_id,a.price,a.decimal,b.name from ".$table."  a left join user_info b on a.mt_order_ui_id=b.ui_id  where a.type=1 and a.price=".$price['price']." and a.create_time >=".strtotime($time." 00:00:00")." and  a.create_time <=".strtotime($time . " 23:59:59");
        $all=Db::name($table)->query($sql);
        $arr=[];
        $index=[];
        $total=0;
        foreach($all as $item){
            if(!in_array($item['name'], $index)){    //新用户加入数组
                $index[]=$item['name'];
                $arr[] = [
                    'name' => $item['name'],
                    'price' =>$item['price'],      //个人成交量
                    'decimal' =>$item['decimal'],      //个人成交量
                ];
                $total+=$item['decimal'];
            } else {              //老用户交易数量叠加
                for ($i = 0 ; $i < count($arr) ; $i ++) {
                    if($arr[$i]['name'] ==  $item['name']) {
                        $arr[$i]['decimal'] = bcadd($arr[$i]['decimal'],$item['decimal'],8);
                    }
                }
                $total+=$item['decimal'];
            }
        }
        $sdtbase=Db::name('u_setting_btc')->value('sdt_base');
        foreach($arr as  $k=>$v){
            if($v['decimal']<1000){
                $total=$total-$v['decimal'];
            }
        }
        $info=[];
        foreach($arr as  $k=>$v){
            if($v['decimal']<1000) continue;

            $info[]=[
                'name'=>$v['name'],
                'price'=>$v['price'],
                'decimal'=>$v['decimal'],
                'income'=>bcdiv($v['decimal'],$total,8)*$sdtbase,
            ];
        }
        return json(['status' => 200,'msg' =>'成功','data'=>$info]);
    }




    /*新设备------页面显示分红*/
    public function showDividend(Request $request){
        $batch = $request->param('batch',1810);
        if($batch=='') return json(['status' => 201,'msg' => '批次号不能为空!']);

        $batch_setting=Db::table('u_newuser_setting')->field('batch,add_num')->where(['batch'=>$batch,'type'=>1])->find();
        if($batch_setting==null){
            return json(['status' => 201,'msg' => '批次已过期,或者不存在!']);
        }

        //累计分配,今日待分配,昨日已分配
        $info['total']=Db::table('u_newuser_base')->where(['batch'=>$batch_setting["batch"]])->sum('btc_base');  //总累计
        $re=Db::table('u_newuser_base')->field('btc_base')->where(['batch'=>$batch_setting["batch"]])->order('id desc')->limit(2)->select(); //当日待分配
        //已入矿池数量
        $info['total_equipment']=Db::table('u_newuser_checkin')->where(['batch'=>$batch,'type'=>1])->count();
        //单台收益率
        $num=$batch_setting['add_num']+$info['total_equipment'];       //增加
        $info['total_equipment']=$num;
        $now=time();
        if($num==0){
            $info['rate_of_return']=0;
        }else{
            if($now<1538290800){    //30号15:0:0之前显示累计待分配收益率，之后显示当日收益率
                $info['rate_of_return']=bcdiv($info['total'],$num,10);
            }else{
                $info['rate_of_return']=bcdiv($re[0]['btc_base'],$num,10);
            }
        }


        if($now>1538290800){       //30好之前,页面显示累计待分配,以后显示昨日待分配
            $info['type']=1;
            $info['today']=$re[0]['btc_base'];
            if($now<1538323200){      //1号之前显示累计待分配，1号之后显示昨日待分配
                $info['yesterday']=$info['total'];
            }else{
                $info['yesterday']=$re[1]['btc_base'];
            }
        }else{
            $info['today']=0;
            $info['yesterday']=0;
            $info['type']=2;
        }

        return json(['status' => 200,'msg' => '获取成功!','data'=>$info]);

    }

    /*新设备------显示增加设备数*/
    public function showAdd(){
        $setting=Db::table('u_newuser_setting')->field('add_num')->where(['type'=>1,'batch'=>1810])->find();
        return json(['status' => 200,'msg' => '成功!','data'=>$setting]);
    }

    /*新设备------计算最新分红*/
    public function calDividend(Request $request){
        $ip  = $request->ip();
        if($ip!='127.0.0.1')  return json(['status' => 202, 'msg' => 'BTC非法的IP访问!']);
        $setting=Db::table('u_newuser_setting')->where(['type'=>1,'batch'=>1810])->find();
        $temp_num=mt_rand(1,9);     //今日折合上下浮动
        if($temp_num%2==0){
            $stte_num=$setting['floating_down'];
        }else{
            $stte_num=$setting['floating_up'];
        }
        Db::table('u_newuser_base')->insert([
            'btc_base'=>$setting['btc_base']*$stte_num*floatval(0.99.$temp_num),
            'batch'=>$setting['batch'],
            'start_time'=>$setting['start_time'],
            'end_time'=>$setting['end_time'],
            'allocation_time'=>date('Y-m-d',strtotime("-1 day")),
            'create_time'=>date('Y-m-d H:i:s'),
        ]);

        return json(['status' => 200,'msg' => '成功!']);

    }

    /*新设备------活动时间倒计时*/
    public function getTime(Request $request){
        $batch = $request->param('batch','');
        if($batch=='') return json(['status' => 201,'msg' => '批次不能为空!']);
        $unix_time=Db::table('u_newuser_setting')->value('end_time');
        $now=time();
        $info['time']=strtotime($unix_time)-$now;      //2018/9/30 15:0:0
        if($info['time']<=0) $info['time']=0;

        return json(['status' => 200,'msg' => '获取倒计时成功!','data'=>$info]);
    }




    /*新设备------给所有新设备用户分红*/
    public function btcDividend(Request $request){
        $ip  = $request->ip();
        if($ip!='127.0.0.1')  return json(['status' => 202, 'msg' => 'BTC非法的IP访问!']);
        $batch=Db::table('u_newuser_setting')->where(['type'=>1])->value('batch');
        Db::startTrans();
        try {

            //sdt总额
            $device_num=Db::table('u_newuser_checkin')->where(['type'=>1])->count();
            $add_num=Db::table('u_newuser_setting')->where(['type'=>1])->value('add_num');
            $total_num=$add_num+$device_num;

            //获取所有绑定用户
            $user=Db::table('u_newuser_checkin')->field('user_id')->where(['batch'=>1810,'type'=>1])->select();

            //总共要分的btc
            $total_btc=Db::table('u_newuser_base')->where(['batch'=>$batch])->order('create_time desc')->value('btc_base');  //30号以后

            //获取所有用户
            $arr=[];
            foreach($user as $k=>$v){
                if(!in_array($v['user_id'],$arr)){
                    $arr[]=$v['user_id'];
                }
            }
            $total=[];
            foreach($arr as $v){

                $count=Db::table('u_newuser_checkin')->field('user_id')->where(['batch'=>1810,'type'=>1,'user_id'=>$v])->count();
                $re_btc=bcmul($total_btc,bcmul(bcdiv(1,$total_num,8),$count,8),8);      //分红
                $btc=Db::table('user_finance')->where(['ui_id'=>$v,'ci_id'=>2])->value('amount'); //原btc余额
                $total[]=[
                    'user_id'=>$v,
                    'count'=>$count,
                    'old_btc'=>$btc,
                    'btc'=>$re_btc,
                    'new_btc'=>bcadd($re_btc,$btc,10),
                    'type'=>1,
                    'unix_time'=>strtotime(date('Y-m-d')),
                    'create_time'=>date('Y-m-d H:i:s'),
                ];
                //直接入账户
                Db::table('user_finance')->where(['ui_id'=>$v,'ci_id'=>2])->setInc('amount',$re_btc);

            }
            //插入记录表
            Db::table('u_newuser_btc')->insertAll($total);
            Db::commit();

            $email[]='abdi1006@foxmail.com';
            $email[]='1477563131@qq.com';
            $btc_num=Db::table('u_newuser_btc')->where(['unix_time'=>strtotime(date('Y-m-d'))])->sum('btc');
            foreach($email as $v){


                $title = '新设备---BTC分红成功';                //主题
                $body =date('Y-m-d H:i:s') . '新设备---BTC分红成功！用户金额：'.$btc_num;

                /*$email=new email();
                $email->sendEmai($title,0,$body);*/


            }

            return json(['status' => 200, 'msg' => '分红成功!']);
        } catch (\Exception $e){
            Db::rollback();
            return json(['status' => 201,'msg' => '失败','error' => $e->getMessage()]);
        }

    }








}
