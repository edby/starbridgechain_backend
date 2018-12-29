<?php

namespace app\dividend\controller;

/*
 * 快照
*/

use think\Controller;
use think\Db;
use think\Request;
use curl\Curl;
use think\facade\Config;
use app\common\service\Email;

class Privated  extends Controller
{
    private $str;
    public function initialize()
    {
        parent::initialize();
        $this->str=Config::get();
    }

    /*每天14点456钱包向789转账（解封）* */
    public function transfer(){
        //获取当日应当发放的sdt数量
        $aa= date('Y-m-d H:i:s');
        $count=Db::table('u_grant')
                ->where('start_time','<=',$aa)
                ->where('end_time','>=',$aa)
                ->find();//获取当日的sdt发放量

        $num=$count['amount']*(1/3)*(1/3);

        $user=[
            41894=>41897,
            41895=>41898,
            41896=>41899,
        ];
        $data=[];
        foreach($user as $k=>$v){
            Db::name('user_finance')->where(['ui_id'=>$k,'ci_id'=>1])->setDec('amount',$num);   //456号钱包减少
            Db::name('user_finance')->where(['ui_id'=>$v,'ci_id'=>1])->setInc('amount',$num);   //789号钱包增加
            $data[]=[
                'user_id'=>$k,
                'num'=>$num,
                'type'=>1,
                'time'=>date('Y-m-d H:i:s')
            ];
            $data[]=[
                'user_id'=>$v,
                'num'=>$num,
                'type'=>2,
                'time'=>date('Y-m-d H:i:s')
            ];
        }
        Db::name('u_transfer_log')->insertAll($data);

        //$this->addSdt(); //调用sdt虚增
        return json(['status' => 200,'msg' => '456转账成功!']);

    }

    /*获取virtual虚增sdt* */
    public function addSdt(){
        set_time_limit(0);
        $re=json_decode(Curl::get($this->str['func']['add_sdt']));
        $virtual_user=Db::table('u_user_virtual')->select();
        $add_num=bcdiv($re[0]->Amount,count($virtual_user),10);
        $data=[];
        foreach($virtual_user as $item){
            Db::table('user_finance')->where(['ui_id'=>$item['ui_id'],'ci_id'=>1])->setInc('amount',$add_num);
            $data[]=[
                'user_id'=>$item['ui_id'],
                'num'=>$add_num,
                'type'=>1,
                'time'=>date('Y-m-d H:i:s')
            ];
        }
        Db::name('u_transfer_log')->insertAll($data);
    }


    /**24小时快照定时任务*/
    public function Snapshot()
    {
        set_time_limit(0);
        ini_set('memory_limit','256M');

        $setting=Db::table('u_setting_lock')->order('create_time desc')->limit(1)->select();
        $str="select ui_id,amount,lock_amount  from (select *  from user_finance where ci_id=1) a where a.amount>=".$setting[0]['sdt_base']." or a.lock_amount!=0;";
        $user_finance=Db::name('user_finance')->query($str);

        $coefficient=Db::table('u_setting_btc')->value('coefficient');
        $ins_data=[];
        foreach ($user_finance as $key => $value)
        {
            //快照额=余额+锁仓额*系数
            if($value['amount']<$setting[0]['sdt_base'])  //余额小于锁仓额,只快照锁仓的,
            {
                $ins_data[] = [
                    'user_id' => $value['ui_id'],
                    'sdt' => $value['lock_amount']*$coefficient,
                    'create_time' => date('Y-m-d H:i:s'),
                    'hour' => date('G'),
                    'days' => (int)date('d'),
                ];
            }else{                          //余额大于锁仓额,快照锁仓+余额
                $sdt_num=bcadd($value['lock_amount']*$coefficient,$value['amount'],15);
                $ins_data[] = [
                    'user_id' => $value['ui_id'],
                    'sdt' => $sdt_num,
                    'create_time' => date('Y-m-d H:i:s'),
                    'hour' => date('G'),
                    'days' => (int)date('d'),
                ];

            }

        }
        Db::name('u_24hour')->insertAll($ins_data);


    }

    /*每日24点快照手续费*/
    public function SnapshotCharge()
    {

        $create_unix=strtotime(date('Y-m-d'));
        $yestoday_unix=strtotime(date('Y-m-d',strtotime("-1 day")));

        /*查询存放手续费的用户*/
        $ui_id=Db::table('user_info')->field('ui_id')->where(['user_type'=>4])->find();
        $data=Db::table('user_finance')->where(['ui_id'=>$ui_id['ui_id']])->select();
        foreach($data as $item){
            //获取昨日手续费
            $temp=Db::table('u_service_charge')->field('amount')->where(['ci_id'=>$item['ci_id'],'create_unix'=>$yestoday_unix])->find();
            $name=Db::table('coin_info')->field('short_name')->where(['ci_id'=>$item['ci_id'],'status'=>1])->find();
            Db::table('u_service_charge')->insert([
                'ui_id'=>$item['ui_id'],
                'ci_id'=>$item['ci_id'],
                'amount'=>$item['amount'],
                'yes_amount'=>bcsub($item['amount'],$temp['amount'],5),      //今日手续费收入
                'exchange_rate'=>  get_time_price($name['short_name']),
                'create_unix'=>$create_unix,
                'create_time'=>date('Y-m-d H:i:s'),
            ]);
        }


    }

    /*BTC分红**/
    public function btcAnalyze(Request $request){
        set_time_limit(0);
        ini_set('memory_limit','128M');
        $type_btc=Db::table('u_setting_btc')->value('type');
        if($type_btc==2){
            return json(['status' => 202,'msg' => '分红接口已经关闭!']);
        }

     /*   $ip  = $request->ip();
        if($ip!='127.0.0.1') {
            $title = '新交易市场-非本机ip访问,请确认是否是正常操作';                //主题
            $key_value=getSysconfig('ip');
            foreach($this->str['func']['email'] as $v){
                Email::sendEmail($v,$title,$key_value['ip'],2);
            }
            return json(['status' => 202, 'msg' => 'BTC非法的IP访问!']);
        }*/

        //$yesToday= date("Y-m-d",strtotime("-1 day"));//昨天的日期
        //$yes_Days= (int)date("d",strtotime("-1 day"));//昨天日期的号数
        $yesToday= date("Y-m-d");//昨天的日期
        $yes_Days= (int)date("d");//昨天日期的号数

        /***获取(真实用户+玩客家)的userid*/
        $gi_id=Db::name('group_info')->field('gi_id')->where(['type'=>1])->select();
        $gi_ids=[];
        foreach($gi_id as $item){
            $gi_ids[]=$item['gi_id'];
        }
        $where[]=['gi_id','in',$gi_ids];
        $user_ids=Db::name('user_group')->field('ui_id')->where($where)->select();

        /*获取真实用户的快照总额，和有快照的真实用户的userid*/
        $real_sdt=0;
        $real_userIds=[];
        foreach($user_ids as $item){
            $temp=[];
            $user_sdt=Db::name('u_24hour')->where(['days'=>$yes_Days,'user_id'=>$item['ui_id']])->sum('sdt');
            if($user_sdt!=0){
                $temp['ui_id']=$item['ui_id'];
                $real_userIds[]=$temp;
            }
            $real_sdt=bcadd($user_sdt,$real_sdt,15);
        }
        $real_true_sdt=bcdiv($real_sdt,24,15);      //用户真实快照24小时sdt平均总额

        /***获取(3,4,7,8,9,10)id的用户*/
        $our_str="select  ui_id from user_group where gi_id in (3,4,7,8,9,10);";
        $our_ids=Db::table('user_group')->query($our_str);
        $our_sdt=0;
        foreach($our_ids as $item){
            $our_sql="select  sum(sdt) as total_sdt from u_24hour where days=".$yes_Days." and user_id=".$item['ui_id'].";";
            $temp_sdt=Db::table('u_24hour')->query($our_sql);
            $our_sdt=bcadd($temp_sdt[0]['total_sdt'],$our_sdt,15);
        }
        $our_true_sdt=bcdiv($our_sdt,24,15);      //our快照24小时sdt平均总额


        /***除去group_id=2的所有快照总额*/
        $all_str="select  ui_id from user_group where gi_id=1 or gi_id!=2;";
        $all_re=Db::table('user_group')->query($all_str);
        $all_sdt=0;
        foreach($all_re as $item){
            $all_sql="select  sum(sdt) as total_sdt from u_24hour where days=".$yes_Days." and user_id=".$item['ui_id'].";";
            $temp_sdt=Db::table('u_24hour')->query($all_sql);
            $all_sdt=bcadd($temp_sdt[0]['total_sdt'],$all_sdt,15);
        }
        $all_true_sdt=bcdiv($all_sdt,24,15);      //出去3号官方组之前的所有,真实快照sdt总额


        /***手续费*/
        /*$table='market_trade_log'.date("Y").'_1';//表名字
        $buy_sql="select buy_fee as sdt,sell_fee as btc from ".$table." where   create_time >=".strtotime($yesToday.' 00:00:00')." and create_time<=".strtotime($yesToday.' 23:59:59');
        $fee = Db::name("market_trade_log")->query($buy_sql);
        $service_charge=$fee[0]['btc'];*/
        /*查询昨日手续费收入*/
        $yestoday_unix=strtotime(date('Y-m-d',strtotime("-1 day")));
        //$yestoday_unix=strtotime(date('Y-m-d'));
        $btc_charge=Db::table('u_service_charge')->field('amount,yes_amount,exchange_rate')->where(['ci_id'=>2,'create_unix'=>$yestoday_unix])->find();
        $service_charge=$btc_charge['yes_amount'];


        /***从数据库获取btc分红基数*/
        $btc_base=Db::table('u_setting_btc')->value('btc_base');


        /***(真实用户btc分红比例)真实btc分红数量/真实用户sdt数量*/
        $proportion=bcdiv($btc_base,$real_true_sdt,15);

        /***计算显示分红btc数量=(真实用户btc分红比例*所有用户持有sdt)+手续费*/
        $page_display=bcadd(bcmul($proportion,$all_true_sdt,15),$service_charge,15);

        /*查看昨天是否已经分红*/
        $str_temp="select * from  u_btc_base where  date_format(allocation_time,'%Y-%m-%d')='".$yesToday."';";
        $yes=Db::table('u_btc_base')->query($str_temp);

/*        if(count($yes)!=0){         //已经分红，返回邮件提示
            $title = '新交易市场-第二次访问分红红接口,请确认是否是正常操作';                //主题
            $key_value=getSysconfig('exception');
            foreach($this->str['func']['email'] as $v){
                Email::sendEmail($v,$title,$key_value['exception'],2);
            }
            return json(['status' => 202,'msg' => '一天只能访问一次!']);
        }else{*/


           $yes_temp=Db::table('u_btc_base')->order('create_time desc')->limit(1)->select();

            /***获取所有sdt流通总额,除去group中group_id=2的官方钱包,以及锁仓*/
            $sql="select sum(amount)+sum(trans_frost)+sum(out_frost)  as total_sdt  from user_finance  left join user_group  on user_group.ui_id=user_finance.ui_id  where user_finance.ci_id=1 and user_group.gi_id!=2";
            $info=Db::table('user_finance')->query($sql);
            $total_sdt=$info[0]['total_sdt'];
            Db::table('u_btc_base')->insert([
                'page_display'=>$page_display,
                'yes_accumulated'=>$yes_temp[0]['yes_accumulated'],
                'real_true_sdt'=>$real_true_sdt,
                'our_true_sdt'=>$our_true_sdt,
                'all_true_sdt'=>$all_true_sdt,
                'total_sdt'=>$total_sdt,
                'allocation_time'=>$yesToday,
                'create_time'=>date("Y-m-d H:i:s"),
            ]);
        //}
        /*正式分红*/
       /* Db::startTrans();
        try {*/

            $true_btc=bcadd($btc_base,$service_charge,15);      //true_user allocation btc
            $our_btc=bcsub($page_display,$true_btc,15);         //our_user allocation btc

            /*得到每天所有币种的手续费*/
            $all_charge=Db::table('u_service_charge')->field('ci_id,amount,yes_amount,exchange_rate')->where(['create_unix'=>$yestoday_unix])->select();

            /***获取需要分红的账户(用户组1和6)*/
            foreach($real_userIds as $k=>$v){

                /*查询每个用户24小时持有sdt的总和*/
                $user_sdt=Db::name('u_24hour')->where(['days'=>$yes_Days,'user_id'=>$v['ui_id']])->sum('sdt');
                $user_avg_sdt=bcdiv($user_sdt,24,15);       //平均每小时的sdt数量
                $user_proportion=bcdiv($user_avg_sdt,$real_true_sdt,15);   //每个人的分红比例(每个人每小时的平均值除以总额)
                /*将每个人的分红btc加入到账户*/
                $user_allocation_btc=bcmul($true_btc,$user_proportion,15);  //每个人应该分的btc数量
                $user_amount=Db::name('user_finance')->where(['ui_id'=>$v['ui_id'],'ci_id'=>2])->value('amount');
                $user_btcs = bcadd($user_allocation_btc,$user_amount,10);

                $data_user_temp=[];
                $total_usdt=0;
                foreach($all_charge as $item){
                    if($item['ci_id']!=2){
                        $user_allocation_temp=bcmul($item['yes_amount'],$user_proportion,15);  //每个人应该分的sdt数量
                        $user_amount_temp=Db::name('user_finance')->where(['ui_id'=>$v['ui_id'],'ci_id'=>$item['ci_id']])->value('amount');
                        $user_sdts = bcadd($user_allocation_temp,$user_amount_temp,10);
                        $total_usdt+=($user_allocation_temp*$item['exchange_rate']);
                        if($user_allocation_temp>0){
                            $data_user_temp[]=[
                                'user_id'=>$v['ui_id'],
                                'allocation'=>$user_allocation_temp,
                                'old'=>$user_amount_temp,
                                'new'=>$user_sdts,
                                'type'=>$item['ci_id'],
                                'status'=>2,
                                'time'=>date('Y-m-d H:i:s')
                            ];
                            //Db::name('user_finance')->where(['ui_id'=>$v['ui_id'],'ci_id'=>$item['ci_id']])->setInc('amount',$user_allocation_btc);
                            updateUserBalance($v['ui_id'],$item['ci_id'],'cents',[['field'=>'amount','type'=>'inc','val'=>$user_allocation_btc]]);
                        }

                    }else{
                        if($user_allocation_btc>0){
                            $data_user_temp[]=[
                                'user_id'=>$v['ui_id'],
                                'allocation'=>$user_allocation_btc,
                                'old'=>$user_amount,
                                'new'=>$user_btcs,
                                'type'=>2,
                                'status'=>2,
                                'time'=>date('Y-m-d H:i:s')
                            ];

                            $total_usdt+=($user_allocation_btc*$item['exchange_rate']);
                            //Db::name('user_finance')->where(['ui_id'=>$v['ui_id'],'ci_id'=>2])->setInc('amount',$user_allocation_btc);

                            updateUserBalance($v['ui_id'],2,'cents',[['field'=>'amount','type'=>'inc','val'=>$user_allocation_btc]]);

                        }
                    }
                }
                Db::name('u_btc_bak')->insertAll($data_user_temp);  //插入记录表


                /*插入累计分红,历史排行表*/
                $total_btc=Db::table('u_rankings_new')->where(['user_id'=>$v['ui_id'],'type'=>2])->value('total');
                if($total_btc!=null){
                    Db::table('u_rankings_new')->where(['user_id'=>$v['ui_id'],'type'=>2])->setInc('total',$total_usdt);
                }else{
                    if($total_usdt!=0){
                        Db::table('u_rankings_new')->insert([
                            'user_id'=>$v['ui_id'],
                            'total'=>$total_usdt,
                            'type'=>2,
                            'create_time'=>date('Y-m-d H:i:s'),
                        ]);
                    }
                }
            }


            //our_user
            foreach($our_ids as $k=>$v){
                //查询每个用户24小时持有sdt的总和
                $our_sdt=Db::name('u_24hour')->where(['days'=>$yes_Days,'user_id'=>$v['ui_id']])->sum('sdt');
                $our_avg_sdt=bcdiv($our_sdt,24,15);       //计算每个人每小时sdt的平均值
                $our_proportion=bcdiv($our_avg_sdt,$our_true_sdt,15);   //每个人的分红比例(每个人每小时的平均值除以总额)
                $our_allocation_btc=bcmul($our_btc,$our_proportion,15);  //每个人应该分的btc数量

                /*将每个人的分红btc加入到账户*/
                $our_amount=Db::name('user_finance')->where(['ui_id'=>$v['ui_id'],'ci_id'=>2])->value('amount');
                $our_new_btcs = bcadd($our_allocation_btc,$our_amount,10);

                $data_our_temp=[];
                foreach($all_charge as $item){
                    if($item['ci_id']!=2){
                        $our_allocation_temp=bcmul($item['yes_amount'],$our_proportion,15);  //每个人应该分的sdt数量
                        $our_amount_temp=Db::name('user_finance')->where(['ui_id'=>$v['ui_id'],'ci_id'=>$item['ci_id']])->value('amount');
                        $our_sdts = bcadd($our_allocation_temp,$our_amount_temp,10);
                        if($our_allocation_temp>0){
                            $data_our_temp[]=[
                                'user_id'=>$v['ui_id'],
                                'allocation'=>$our_allocation_temp,
                                'old'=>$our_amount_temp,
                                'new'=>$our_sdts,
                                'type'=>$item['ci_id'],
                                'status'=>2,
                                'time'=>date('Y-m-d H:i:s')
                            ];
                            //Db::name('user_finance')->where(['ui_id'=>$v['ui_id'],'ci_id'=>$item['ci_id']])->setInc('amount',$user_allocation_btc);
                            updateUserBalance($v['ui_id'],$item['ci_id'],'cents',[['field'=>'amount','type'=>'inc','val'=>$our_allocation_temp]]);
                        }


                    }else{
                        /*用户分红记录*/
                        if($our_allocation_btc>0) {
                            $data_our_temp[] = [
                                'user_id' => $v['ui_id'],
                                'allocation' => $our_allocation_btc,
                                'old' => $our_amount,
                                'new' => $our_new_btcs,
                                'type' => 2,
                                'status' => 2,
                                'time' => date('Y-m-d H:i:s')
                            ];
                            //Db::name('user_finance')->where(['ui_id'=>$v['ui_id'],'ci_id'=>2])->setInc('amount',$our_allocation_btc);
                            updateUserBalance($v['ui_id'], 2, 'cents',[['field' => 'amount', 'type' => 'inc', 'val' => $our_allocation_btc]]);
                        }
                    }

                }

                Db::name('u_btc_bak_our')->insertAll($data_our_temp);  //插入记录表

            }


            //提交事务
            Db::commit();

            /**分红成功后发送邮件**/
            $title = '新交易市场-BTC分红成功';                //主题
            $key_value=getSysconfig('success_btc');
            foreach($this->str['func']['email'] as $v){
                Email::sendEmail($v,$title,$key_value['success_btc'].$true_btc,2);
            }
            //销毁变量释放内存
            unset($data_user_temp);
            unset($data_our_temp);

            $this->sdtAnalyze();        //调用sdt交易分红
            $this->cal();               //页面计算
            return json(['status' => 200,'msg' => 'SDT和BTC分红成功']);

    /*    } catch (\Exception $e){
            Db::rollback();
            return json(['status' => 402,'msg' => '分红失败','error' => $e->getMessage()]);
        }*/


    }

    /**sdt分红*/
    public function sdtAnalyze()
    {
        $table='market_trade_log'.date("Y").'_1';//表名字
        //$time = date("Y-m-d",strtotime("-1 day"));
        $time = date("Y-m-d");
        $buy_sql="select price from ".$table." where create_time >=".strtotime($time.' 00:00:00')." and create_time<=".strtotime($time.' 23:59:59')." order by price desc limit 1;";
        $price = Db::name($table)->query($buy_sql);
        if(count($price)==null){
            $title = '新交易市场-SDT没有交易记录';                //主题
            $key_value=getSysconfig('no_transaction');
            foreach($this->str['func']['email'] as $v){
                Email::sendEmail($v,$title,$key_value['no_transaction'],2);
            }
            return json(['status' => 200,'msg' => '昨日没有sdt交易记录']);
        }

        $buys = Db::name($table)
            ->whereBetween('create_time' , [strtotime($time." 00:00:00") , strtotime($time . " 23:59:59")])
            ->where(['price'=>$price[0]['price']])
            ->select();
        $arr = [];
        $index = [];
        //最高价格购入的用用户
        foreach ($buys as $key => $value)
        {
            if($value['type']==1){          //type=1,mt_order_ui_id是买方
                if(!in_array($value['mt_order_ui_id'] , $index)){    //新用户加入数组
                    $index[] = $value['mt_order_ui_id'];
                    $arr[] = [
                        'user_id' => $value['mt_order_ui_id'],
                        'decimal' => $value['decimal'],      //个人成交量
                    ];
                } else {              //老用户交易数量叠加
                    for ($i = 0 ; $i < count($arr) ; $i ++) {
                        if($arr[$i]['user_id'] == $value['mt_order_ui_id']) {
                            $arr[$i]['decimal'] = bcadd($arr[$i]['decimal'],$value['decimal'],8);
                        }
                    }
                }
            }else{      //type=2,mt_peer_ui_id是买方
                if(!in_array($value['mt_peer_ui_id'] , $index)){    //新用户加入数组
                    $index[] = $value['mt_peer_ui_id'];
                    $arr[] = [
                        'user_id' => $value['mt_peer_ui_id'],
                        'decimal' => $value['decimal'],      //个人成交量
                    ];
                } else {              //老用户交易数量叠加
                    for ($i = 0 ; $i < count($arr) ; $i ++) {
                        if($arr[$i]['user_id'] == $value['mt_peer_ui_id']) {
                            $arr[$i]['decimal'] = bcadd($arr[$i]['decimal'],$value['decimal'],8);
                        }
                    }
                }
            }

        }
        $setting = Db::name('u_setting_btc')->find();

        //删除交易数量不够1000的用户,统计交易总额
        $total_fh=0;
        foreach($arr as $k=>$v){
            if($v['decimal']<$setting['sdt_num']){
                unset($arr[$k]);
                continue;
            }
            $total_fh=bcadd($v['decimal'],$total_fh,8);
        }

        if(count($arr)==0){
            $title = '新交易市场-SDT没有交易数量达到1000的用户';                //主题
            $key_value=getSysconfig('no_transaction');
            foreach($this->str['func']['email'] as $v){
                Email::sendEmail($v,$title,$key_value['no_transaction'],2);
            }
            return json(['status' => 200,'msg' => 'SDT没有交易数量达到1000的用户']);
        }


        //减去9号钱包，SDT账户里面的,3w数量,用于SDT交易分红
        $re=Db::name('user_finance')->where(['ui_id'=>3672,'ci_id'=>1])->setDec('amount',$setting['sdt_base']);
        if($re){            //写入记录
            Db::name('u_transfer_log')->insert([
                'user_id'=>3672,
                'num'=>$setting['sdt_base'],
                'type'=>2,
                'time'=>date('Y-m-d H:i:s')
            ]);
        }

        foreach ($arr as $key => $value)
        {

            //个人分红应得sdt=(个人交易额/总交易额)*当天分红3w  sdt
            $add_sdt = bcmul(bcdiv($value['decimal'] , $total_fh , 15) , $setting['sdt_base']);

            //对用户的sdt账户进行数据操作
            $old_sdt = Db::name('user_finance')->where(['ui_id'=>$value['user_id'],'ci_id'=>1])->value('amount');
            $new_sdt = bcadd($old_sdt , $add_sdt , 15);
            //Db::name('user_finance')->where(['ui_id'=>$value['user_id'],'ci_id'=>1])->setInc('amount' , $add_sdt);

            updateUserBalance($value['user_id'],1,'cents',[['field'=>'amount','type'=>'inc','val'=>$add_sdt]]);
            //插入记录表
            $data_user_temp=[
                'user_id'=>$value['user_id'],
                'allocation'=>$add_sdt,
                'old'=>$old_sdt,
                'new'=>$new_sdt,
                'type'=>1,
                'status'=>1,
                'time'=>date('Y-m-d H:i:s')
            ];
            Db::name('u_btc_bak')->insert($data_user_temp);  //插入记录表

            //插入排行表
            $total_sdt=Db::table('u_rankings_new')->where(['user_id'=>$value['user_id'],'type'=>1])->value('total');
            if($total_sdt!=null){
                Db::table('u_rankings_new')->where(['user_id'=>$value['user_id'],'type'=>1])->setInc('total',$add_sdt);
            }else{
                if($add_sdt!=0){
                    Db::table('u_rankings_new')->insert([
                        'user_id'=>$value['user_id'],
                        'total'=>$add_sdt,
                        'type'=>1,
                        'create_time'=>date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
        //分红成功后发送邮件
        $title = '新交易市场-SDT分红成功';                //主题
        $key_value=getSysconfig('success_sdt');
        foreach($this->str['func']['email'] as $v){
            Email::sendEmail($v,$title,$key_value['success_sdt'].$setting['sdt_base'],2);
        }
    }




    /*btc分红预执行* */
    public function preBtcAnalyze(){


        set_time_limit(0);
        ini_set('memory_limit','128M');
        $type_btc=Db::table('u_setting_btc')->value('type');
        if($type_btc==2){
            return json(['status' => 202,'msg' => '分红接口已经关闭!']);
        }

        /*   $ip  = $request->ip();
           if($ip!='127.0.0.1') {
               $title = '新交易市场-非本机ip访问,请确认是否是正常操作';                //主题
               $key_value=getSysconfig('ip');
               foreach($this->str['func']['email'] as $v){
                   Email::sendEmail($v,$title,$key_value['ip'],2);
               }
               return json(['status' => 202, 'msg' => 'BTC非法的IP访问!']);
           }*/

        //$yesToday= date("Y-m-d",strtotime("-1 day"));//昨天的日期
        //$yes_Days= (int)date("d",strtotime("-1 day"));//昨天日期的号数
        $yesToday= date("Y-m-d");//昨天的日期
        $yes_Days= (int)date("d");//昨天日期的号数

        /***获取(真实用户+玩客家)的userid*/
        $gi_id=Db::name('group_info')->field('gi_id')->where(['type'=>1])->select();
        $gi_ids=[];
        foreach($gi_id as $item){
            $gi_ids[]=$item['gi_id'];
        }
        $where[]=['gi_id','in',$gi_ids];
        $user_ids=Db::name('user_group')->field('ui_id')->where($where)->select();

        /*获取真实用户的快照总额，和有快照的真实用户的userid*/
        $real_sdt=0;
        $real_userIds=[];
        foreach($user_ids as $item){
            $temp=[];
            $user_sdt=Db::name('u_24hour')->where(['days'=>$yes_Days,'user_id'=>$item['ui_id']])->sum('sdt');
            if($user_sdt!=0){
                $temp['ui_id']=$item['ui_id'];
                $real_userIds[]=$temp;
            }
            $real_sdt=bcadd($user_sdt,$real_sdt,15);
        }
        $real_true_sdt=bcdiv($real_sdt,24,15);      //用户真实快照24小时sdt平均总额

        /***获取(3,4,7,8,9,10)id的用户*/
        $our_str="select  ui_id from user_group where gi_id in (3,4,7,8,9,10);";
        $our_ids=Db::table('user_group')->query($our_str);
        $our_sdt=0;
        foreach($our_ids as $item){
            $our_sql="select  sum(sdt) as total_sdt from u_24hour where days=".$yes_Days." and user_id=".$item['ui_id'].";";
            $temp_sdt=Db::table('u_24hour')->query($our_sql);
            $our_sdt=bcadd($temp_sdt[0]['total_sdt'],$our_sdt,15);
        }
        $our_true_sdt=bcdiv($our_sdt,24,15);      //our快照24小时sdt平均总额


        /***除去group_id=2的所有快照总额*/
        $all_str="select  ui_id from user_group where gi_id=1 or gi_id!=2;";
        $all_re=Db::table('user_group')->query($all_str);
        $all_sdt=0;
        foreach($all_re as $item){
            $all_sql="select  sum(sdt) as total_sdt from u_24hour where days=".$yes_Days." and user_id=".$item['ui_id'].";";
            $temp_sdt=Db::table('u_24hour')->query($all_sql);
            $all_sdt=bcadd($temp_sdt[0]['total_sdt'],$all_sdt,15);
        }
        $all_true_sdt=bcdiv($all_sdt,24,15);      //出去3号官方组之前的所有,真实快照sdt总额

        /*查询昨日手续费收入*/
        //$yestoday_unix=strtotime(date('Y-m-d',strtotime("-1 day")));
        $yestoday_unix=strtotime(date('Y-m-d'));
        $btc_charge=Db::table('u_service_charge')->field('amount,yes_amount,exchange_rate')->where(['ci_id'=>2,'create_unix'=>$yestoday_unix])->find();
        $service_charge=$btc_charge['yes_amount'];


        /***从数据库获取btc分红基数*/
        $btc_base=Db::table('u_setting_btc')->value('btc_base');


        /***(真实用户btc分红比例)真实btc分红数量/真实用户sdt数量*/
        $proportion=bcdiv($btc_base,$real_true_sdt,15);

        /***计算显示分红btc数量=(真实用户btc分红比例*所有用户持有sdt)+手续费*/
        $page_display=bcadd(bcmul($proportion,$all_true_sdt,15),$service_charge,15);

        /*查看昨天是否已经分红*/
        $str_temp="select * from  u_btc_base where  date_format(allocation_time,'%Y-%m-%d')='".$yesToday."';";
        $yes=Db::table('u_btc_base')->query($str_temp);

        /*        if(count($yes)!=0){         //已经分红，返回邮件提示
                    $title = '新交易市场-第二次访问分红红接口,请确认是否是正常操作';                //主题
                    $key_value=getSysconfig('exception');
                    foreach($this->str['func']['email'] as $v){
                        Email::sendEmail($v,$title,$key_value['exception'],2);
                    }
                    return json(['status' => 202,'msg' => '一天只能访问一次!']);
                }else{*/


        $yes_temp=Db::table('u_btc_base')->order('create_time desc')->limit(1)->select();

        /***获取所有sdt流通总额,除去group中group_id=2的官方钱包,以及锁仓*/
        $sql="select sum(amount)+sum(trans_frost)+sum(out_frost)  as total_sdt  from user_finance  left join user_group  on user_group.ui_id=user_finance.ui_id  where user_finance.ci_id=1 and user_group.gi_id!=2";
        $info=Db::table('user_finance')->query($sql);
        $total_sdt=$info[0]['total_sdt'];
        Db::table('u_btc_base_pre')->insert([
            'page_display'=>$page_display,
            'yes_accumulated'=>$yes_temp[0]['yes_accumulated'],
            'real_true_sdt'=>$real_true_sdt,
            'our_true_sdt'=>$our_true_sdt,
            'all_true_sdt'=>$all_true_sdt,
            'total_sdt'=>$total_sdt,
            'allocation_time'=>$yesToday,
            'create_time'=>date("Y-m-d H:i:s"),
        ]);
        //}
        /*正式分红*/
        /* Db::startTrans();
         try {*/

        $true_btc=bcadd($btc_base,$service_charge,15);      //true_user allocation btc
        $our_btc=bcsub($page_display,$true_btc,15);         //our_user allocation btc

        /*得到每天所有币种的手续费*/
        $all_charge=Db::table('u_service_charge')->field('ci_id,amount,yes_amount,exchange_rate')->where(['create_unix'=>$yestoday_unix])->select();

        /***获取需要分红的账户(用户组1和6)*/
        foreach($real_userIds as $k=>$v){

            /*查询每个用户24小时持有sdt的总和*/
            $user_sdt=Db::name('u_24hour')->where(['days'=>$yes_Days,'user_id'=>$v['ui_id']])->sum('sdt');
            $user_avg_sdt=bcdiv($user_sdt,24,15);       //平均每小时的sdt数量
            $user_proportion=bcdiv($user_avg_sdt,$real_true_sdt,15);   //每个人的分红比例(每个人每小时的平均值除以总额)
            /*将每个人的分红btc加入到账户*/
            $user_allocation_btc=bcmul($true_btc,$user_proportion,15);  //每个人应该分的btc数量
            $user_amount=Db::name('user_finance')->where(['ui_id'=>$v['ui_id'],'ci_id'=>2])->value('amount');
            $user_btcs = bcadd($user_allocation_btc,$user_amount,10);


            $data_user_temp=[];
            foreach($all_charge as $item){

                if($item['ci_id']!=2){
                    $user_allocation_temp=bcmul($item['yes_amount'],$user_proportion,15);  //每个人应该分的sdt数量
                    $user_amount_temp=Db::name('user_finance')->where(['ui_id'=>$v['ui_id'],'ci_id'=>$item['ci_id']])->value('amount');
                    $user_sdts = bcadd($user_allocation_temp,$user_amount_temp,10);

                    if($user_allocation_temp>0) {
                        $data_user_temp[] = [
                            'user_id' => $v['ui_id'],
                            'allocation' => $user_allocation_temp,
                            'old' => $user_amount_temp,
                            'new' => $user_sdts,
                            'type' => $item['ci_id'],
                            'time' => date('Y-m-d H:i:s')
                        ];
                    }

                }else{
                    if($user_allocation_btc>0) {
                        $data_user_temp[] = [
                            'user_id' => $v['ui_id'],
                            'allocation' => $user_allocation_btc,
                            'old' => $user_amount,
                            'new' => $user_btcs,
                            'type' => 2,
                            'time' => date('Y-m-d H:i:s')
                        ];
                    }
                }
            }
            Db::name('u_btc_bak_pre')->insertAll($data_user_temp);  //插入记录表

        }


        //our_user
        foreach($our_ids as $k=>$v){
            //查询每个用户24小时持有sdt的总和
            $our_sdt=Db::name('u_24hour')->where(['days'=>$yes_Days,'user_id'=>$v['ui_id']])->sum('sdt');
            $our_avg_sdt=bcdiv($our_sdt,24,15);       //计算每个人每小时sdt的平均值
            $our_proportion=bcdiv($our_avg_sdt,$our_true_sdt,15);   //每个人的分红比例(每个人每小时的平均值除以总额)
            $our_allocation_btc=bcmul($our_btc,$our_proportion,15);  //每个人应该分的btc数量

            /*将每个人的分红btc加入到账户*/
            $our_amount=Db::name('user_finance')->where(['ui_id'=>$v['ui_id'],'ci_id'=>2])->value('amount');
            $our_new_btcs = bcadd($our_allocation_btc,$our_amount,10);

            $data_our_temp=[];
            foreach($all_charge as $item){
                if($item['ci_id']!=2){
                    $our_allocation_temp=bcmul($item['yes_amount'],$our_proportion,15);  //每个人应该分的sdt数量
                    $our_amount_temp=Db::name('user_finance')->where(['ui_id'=>$v['ui_id'],'ci_id'=>$item['ci_id']])->value('amount');
                    $our_sdts = bcadd($our_allocation_temp,$our_amount_temp,10);
                    if($our_allocation_temp>0){
                        $data_our_temp[]=[
                            'user_id'=>$v['ui_id'],
                            'allocation'=>$our_allocation_temp,
                            'old'=>$our_amount_temp,
                            'new'=>$our_sdts,
                            'type'=>$item['ci_id'],
                            'time'=>date('Y-m-d H:i:s')
                        ];
                    }
                }else{
                    /*用户分红记录*/
                    if($our_allocation_btc>0) {
                        $data_our_temp[] = [
                            'user_id' => $v['ui_id'],
                            'allocation' => $our_allocation_btc,
                            'old' => $our_amount,
                            'new' => $our_new_btcs,
                            'type' => 2,
                            'time' => date('Y-m-d H:i:s')
                        ];
                    }
                }
            }

            Db::name('u_btc_bak_pre')->insertAll($data_our_temp);  //插入记录表

        }

        Db::commit();

        /**预--分红成功后发送邮件**/
        $title = '新交易市场-BTC--预分红成功';                //主题
        $key_value=getSysconfig('success_btc_pre');
        foreach($this->str['func']['email'] as $v){
            Email::sendEmail($v,$title,$key_value['success_btc_pre'].$true_btc.'。如需停止分红，点击：http://new.com/privated/stopBtcAnalyze',2);
        }
        //销毁变量释放内存


            return json(['status' => 200,'msg' => '预备分红-BTC分红成功']);

    /*  } catch (\Exception $e){
            Db::rollback();
            return json(['status' => 402,'msg' => '分红失败','error' => $e->getMessage()]);
        }*/

    }


    /* 每天获取23:59分获取今日待分配(today_accumulated)的数量,修改记录中的昨日待分配 */
    public function editYesAcc(){
        $id=Db::table('u_btc_base')->field('id,today_allocation')->order('create_time desc')->limit(1)->find();
        $re=Db::table('u_btc_base')->where(['id'=>$id['id']])->update([
            'yes_accumulated'=>$id['today_allocation'],
        ]);
    }


    /*页面计算*/
    public function cal(){
        set_time_limit(0);
        //昨日总的释放数量
        $yesToday= date("Y-m-d H:i:s",strtotime("-1 day"));
        $num=Db::table('u_grant')
            ->where('start_time','<=',$yesToday)
            ->where('end_time','>=',$yesToday)
            ->value("amount");
        $info['yes_freed']=$num;  //获取当日的sdt发放量
        //查询昨天的数据页面数据
        $data=Db::table('u_btc_base')->order('create_time desc')->limit(1)->select();

        //平台总流通量（所有sdt流通总额,除去官方钱包,以及锁仓）
        $info['total_sdt']=$data[0]['total_sdt'];

        //SDT二级市场流通量(除锁仓平台总流通量)
        $total=Db::table('user_finance')->where(['uf_id'=>41897,'ci_id'=>1])->sum('amount');
        $info['second_market']=bcsub($info['total_sdt'],$total,15);

        $setting=Db::table('u_setting_btc')->find();

        //今日待分配收入累积折合（待分配折合数量）
        $time=(int)date("H")+1;            //当前请求时间
        $yes_today_avg=bcdiv($data[0]['yes_accumulated'],24,15);       //昨日分红总额/24
        $temp_num=mt_rand(1,9);     //今日折合上下浮动
        if($temp_num%2==0){
            $stte_num=$setting['floating_down'];
        }else{
            $stte_num=$setting['floating_up'];
        }

        //今日待分配,每个小时变化一次
        $info['today_allocation']=$yes_today_avg*$time*$stte_num;

        //锁仓系数,用于计算收益率
        $coefficient=Db::table('u_setting_btc')->value('coefficient');


        //非锁仓,持有SDT每万份收益
        $info['yes_million_btc']=bcdiv(10000,$data[0]['all_true_sdt'],15)*$data[0]['page_display'];

        //锁仓,持有SDT每万份收益,
        $info['lock_yes_million_btc']=$info['yes_million_btc']*$coefficient;



        //最新币价
        $url='http://webmarket.starbridgechain.com/Ajax/getJsonTops?market=sbc_btc';//当前每SDT可兑换BTC
        $new_price=json_decode(Curl::get($url));
        $sdt_price=$new_price->info->new_price;

        //非锁仓,动态收益率
        $info['yes_static_sdt']=bcdiv(bcmul($data[0]['page_display'],bcdiv(1,$data[0]['all_true_sdt'],20),20),$sdt_price,5)*1000;
        //锁仓,动态收益率
        $info['lock_yes_static_sdt']=bcmul($info['yes_static_sdt'],$coefficient,15);



        //矿机设备含有数量（不参与分红）
        $urls='https://apiportal.cmbcrouter.com:8020/api/Management/GetTodayCollect';
        $equipment_nums=json_decode(Curl::post($urls,json_encode([]),['Content-Type: application/json']));


        $info['equipment_num']=$equipment_nums->Body->Data->AllRouter;
        //已释放数量
        $info['all_total_sdt']=bcadd($info['total_sdt'],$info['equipment_num'],15);


        /*修改数据库*/
        $id=Db::table('u_btc_base')->field('id')->order('create_time desc')->limit(1)->find();
        $re=Db::table('u_btc_base')->where(['id'=>$id['id']])->update([
            'yes_freed'=>$info['yes_freed'],
            'today_allocation'=>$info['today_allocation'],
            'second_market'=>$info['second_market'],
            'all_total_sdt'=>$info['all_total_sdt'],
            'equipment_num'=>$info['equipment_num'],
            'yes_million_btc'=>$info['yes_million_btc'],
            'yes_static_sdt'=>$info['yes_static_sdt'],
            'lock_yes_static_sdt'=>$info['lock_yes_static_sdt'],
            'lock_yes_million_btc'=>$info['lock_yes_million_btc'],
        ]);
        $info=[];
        //每小时查看是否有解仓数据
        $this->unlockSdt();
        return json(['status' => 200,'msg' => '计算成功']);
    }


    /*禁止分红* */
    public function stopBtcAnalyze()
    {
        $info=Db::table('u_setting_btc')->where(['id'=>1])->setField('type',2);
        if($info==1){
            return json(['status' => 200,'msg' => '分红停止--成功']);
        }else{
            return json(['status' => 200,'msg' => '分红停止--失败']);
        }
    }


    /*提前两天给锁仓到期的账户发提醒邮件*/
    public function sendExpireLock(){
        $time = date("Y-m-d",strtotime("+2 day"));
        $re = Db::table('u_sdt_lock')
            ->field('user_id')
            ->where('lock_time' , '<=',$time . " 23:59:59")
            ->where('lock_time' , '>=',$time . " 00:00:00")
            ->select();

        foreach($re as $item){
            $email=Db::table('user_info')->where(['ui_id'=>$item['user_id']])->value('email');

            $title = '服务－星桥链SDT锁仓到期提醒';                //主题
            $key_value=getSysconfig('lock_sdt');
            Email::sendEmail($email,$title,$key_value['lock_sdt'],2);
        }
        $this->unlockSdt();
        return json(['status' => 200,'msg' =>'成功']);
    }

    /*解仓*/
    public function unlockSdt(){
        $time=date('Y-m-d H:i:s');
        $setting=Db::table('u_setting_lock')->order('create_time desc')->limit(1)->select();
        $locks=Db::name('u_sdt_lock')
            ->where('status',1)
            ->where('lock_time','<=',$time)->select();
        foreach($locks as $k=>$v){
           /* Db::startTrans();
            try {*/
                if($v['type']==1&&$v['lock_nums']>=$setting[0]['sdtBase']){       //到期继续锁仓
                    $lock_time=date("Y-m-d H:i:s",strtotime("+".$v['time_num']." months"));
                    Db::name('u_setting_lock')->where(['id'=>$v['id'],'status'=>1])->setField('lock_time',$lock_time);

                }else{          //到期解仓
                    //扣去相应的锁仓数量
                    $user_balance = Db::name('user_finance')->where(['uf_id'=>$v['user_id'],'ci_id'=>1])->find();

                    if($v['lock_nums']>$user_balance['lock_amount']){
                        return json(['status' => 402,'msg' => '解仓失败,解仓数量大于锁仓数量,请联系管理!','e_msg'=>'Lock up failure !']);
                    }


                    $unlock_num=bcsub($user_balance['lock_amount'],$v['lock_nums'],15);

                    Db::name('user_finance')->where(['uf_id'=>$v['user_id'],'ci_id'=>1])->setDec('lock_amount',$v['lock_nums']);

                    //相应的账户中，补充相应的sdt数量
                    $add_num=bcadd($v['lock_nums'],$user_balance['amount'],15);
                    Db::name('user_finance')->where(['uf_id'=>$v['user_id'],'ci_id'=>1])->setInc('amount',$v['lock_nums']);

                    //修改锁仓记录状态
                    Db::name('u_sdt_lock')->where(['id'=>$v['id']])->setField('status',2);

                    /*解仓后发送邮件*/
                    $email=Db::table('user_info')->where(['ui_id'=>$v['user_id']])->value('email');

                    $title = '服务－星桥链SDT到期解仓提醒';                //主题
                    $key_value=getSysconfig('unloading_sdt');
                    Email::sendEmail($email,$title,$key_value['unloading_sdt'],2);
                }

           /*     Db::commit();

            } catch (\Exception $e){
                Db::rollback();
            }*/
        }


    }


}
