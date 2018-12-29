<?php


namespace app\dividend\controller;


use curl\Curl;
use think\Controller;
use think\Db;
use think\facade\Config;
use think\Request;
use think\Exception;


/*新设备分红*/
class NewDevice extends Controller
{

    public $str;
    public function initialize()
    {
        parent::initialize();
        $this->str=Config::get();
    }


    /*新设备------获取所有sn号*/
    public function getAllSn(Request $request){
        $mobile = $request->param('mobile','');
        $pwd = $request->param('pwd','');
        //$pwd=strtoupper(md5($pwd));
        $temp  = [
            'Key'=>$this->str['func']['Key'],
            'User'=>$this->str['func']['User'],
            'Mobile'=>$mobile,
            'Password'=>$pwd,
        ];

        //验证sn是否正确
        $rst = Curl::post($this->str['func']['bind_sn'],json_encode($temp),['Content-Type: application/json']);
        $rst = json_decode($rst,true);

        if($rst['Header']['Msg']=='ok'){       //余额提取
            $data['mobile']=$mobile;
            if($rst['Body']['Data']==[]){
                $data['ssn']=[];
                return json(['status' => 200,'msg' => '登录成功!','data'=>$data]);
            }
            $data['sn']=$rst['Body']['Data'];
            $temp=[];
            foreach($data['sn'] as $v){
                    $temp[]=$v['SN'];
            }
            $where['sn']=array('in',$temp);
            $sns=Db::table('u_newuser_checkin')->field("sn")->where('sn','in',$temp)->select();

            if(count($sns)==0){
                foreach($data['sn'] as $itm){
                    $data['ssn'][]=[
                        "SNAR_ID"=>$itm["SNAR_ID"],
                        "UUID"=>$itm["UUID"],
                        "SN"=>$itm["SN"],
                        "MAC"=>$itm["MAC"],
                        "StartTime"=>$itm["StartTime"],
                        "EndTime"=>$itm["EndTime"],
                        "No"=>$itm["No"],
                        "Status"=>$itm["Status"],
                        "CreateTime"=>$itm["CreateTime"],
                        "check"=>1,
                    ];
                }
                unset($data['sn']);
                return json(['status' => 200,'msg' => '登录成功!','data'=>$data]);
            }else{
                $sn_temp=[];
                foreach($sns as $item){
                    $sn_temp[]=$item['sn'];
                }
                foreach($data['sn'] as $kk=>$vv){
                    $data['sn'][$kk]['check']=1;
                    if(in_array($vv['SN'],$sn_temp)){
                        unset($data['sn'][$kk]);
                    }
                }
                if($data['sn']==null){
                    $data['ssn']=[];
                    unset($data['sn']);
                }else{
                    foreach($data['sn'] as $itm){
                        $data['ssn'][]=[
                            "SNAR_ID"=>$itm["SNAR_ID"],
                            "UUID"=>$itm["UUID"],
                            "SN"=>$itm["SN"],
                            "MAC"=>$itm["MAC"],
                            "StartTime"=>$itm["StartTime"],
                            "EndTime"=>$itm["EndTime"],
                            "No"=>$itm["No"],
                            "Status"=>$itm["Status"],
                            "CreateTime"=>$itm["CreateTime"],
                            "check"=>1,
                        ];
                    }
                    unset($data['sn']);
                }


                return json(['status' => 200,'msg' => '登录成功!','data'=>$data]);
            }

        }
        if($rst['Header']['Msg']==null){
            return json(['status' => 201,'msg' =>'绑定失败','e_msg'=>'Binding failure!']);
        }else{
            return json(['status' => 201,'msg' => $rst['Header']['Msg'],'e_msg'=>'Binding failure!']);
        }

    }

    /*新设备------批量绑定sn*/
    public function bindingSn(Request $request){
        $sn =$request->post('sn/a');       //获取数组
        $mobile =$request->param('mobile');
        //$user_id=$this->uid;
        $user_id=41894;

        $temp=[];
        Db::startTrans();
        try {
            foreach($sn as $k=>$v){
                $temp[]=[
                    'user_id'=>$user_id,
                    'uuid'   =>$v['UUID'],
                    'sn'   =>$v['SN'],
                    'mac'   =>$v['MAC'],
                    'mobile_num'   =>$mobile,
                    'batch'   =>$v['No'],
                    'sdt'=>1,
                    'start_time'=>$v['StartTime'],
                    'end_time'=>$v['EndTime'],
                    'create_time'=>date('Y-m-d H:i:s'),
                    'unix_time'=>strtotime(date('Y-m-d')),
                ];

            }
            Db::table('u_newuser_checkin')->insertAll($temp);
            Db::commit();
            return json(['status' => 200,'msg' => '绑定成功!','e_msg'=>'Binding success!']);
        } catch (\Exception $e){
            Db::rollback();
            return json(['status' => 201,'msg' => '批次绑定失败','error' => $e->getMessage()]);
        }



    }


    /*新设备------获取个人参加信息*/
    public function getInfo(){
        //$user_id=$this->uid;
        $user_id=41894;
        $info['count']=Db::table('u_newuser_checkin')->where(['user_id'=>$user_id,'type'=>1])->count();
        $info['SDT1810']=$info['count']*2000;
        $info['btc']=Db::table('u_newuser_btc')->where(['user_id'=>$user_id])->sum('btc');
        $info['inpool']=Db::table('u_newuser_checkin')->field('sn,mobile_num,create_time,sdt')->where(['user_id'=>$user_id,'type'=>1])->select();
        $info['dividend']=Db::table('u_newuser_btc')->where(['user_id'=>$user_id,'type'=>1])->select();
        return json(['status' => 200,'msg' => '个人信息获取成功!','data'=>$info]);
    }




    /*待定------绑定sn*/
    public function bindSn(Request $request){

        $mobile = $request->param('mobile','');
        $sn = $request->param('sn','');
        $pwd = $request->param('pwd','');
        //$user_id=$this->uid;
        $user_id=41894;

        $new_user=Db::table('u_newuser_checkin')->where(['sn'=>$sn])->find();

        if($new_user){
            return json(['status' => 201,'msg' => 'SN号已绑定,请确认!','e_msg'=>'The SN number  has been bound!']);
        }
        $pwd=strtoupper(md5($pwd));
        $temp  = [
            'Key'=>$this->str['func']['Key'],
            'User'=>$this->str['func']['User'],
            'Mobile'=>$mobile,
            'Password'=>$pwd,
            'SN'=>$sn,
        ];

        //验证sn是否正确
        $rst = Curl::post($this->str['func']['bind_sn'],json_encode($temp),['Content-Type: application/json']);
        $rst = json_decode($rst,true);


        if($rst['Header']['Msg']=='ok'){       //余额提取

            Db::table('u_newuser_checkin')
                ->insert([
                    'user_id'=>$user_id,
                    'uuid'=>$rst['Body']['Data'][0]['UUID'],
                    'sn'=>$rst['Body']['Data'][0]['SN'],
                    'mac'=>$rst['Body']['Data'][0]['MAC'],
                    'mobile_num'=>$mobile,
                    'batch'=>$rst['Body']['Data'][0]['No'],
                    'sdt'=>1,
                    'start_time'=>$rst['Body']['Data'][0]['StartTime'],
                    'end_time'=>$rst['Body']['Data'][0]['EndTime'],
                    'create_time'=>date('Y-m-d H:i:s'),
                    'unix_time'=>strtotime(date('Y-m-d')),

                ]);


            return json(['status' => 200,'msg' => '绑定成功!','e_msg'=>'Binding success!']);
        }

        $err=[
            '5'=>'User does not exist!',
            '101'=>'User and password do not match!',
            '203'=>'Router is not bound!',
            '204'=>'Router does not exist!',
            '652'=>'Router does not meet the conditions!',
            '653'=>'Router does not match!',
        ];

        return json(['status' => $rst['Header']['ClientErrorCode'],'msg' => $rst['Header']['Msg'],'e_msg'=>$err[$rst['Header']['ClientErrorCode']]]);


    }

    /*待定------第一次分红后7号，将btc分发到用户账号*/
    public function pushAccount(Request $request){
        $ip  = $request->ip();
        if($ip!='127.0.0.1')  return json(['status' => 202, 'msg' => 'BTC非法的IP访问!']);die;
        Db::startTrans();
        try {

            $user=Db::table('u_newuser_btc')->field('user_id,btc')->select();
            foreach($user as $v){
                $btc=Db::table('wkj_user_coinbtc')->where(['userid'=>$v['user_id']])->value('btc');
                $add_btc=bcadd($btc,$v['btc'],10);
                Db::table('wkj_user_coinbtc')->where(['userid'=>$v['user_id']])->setInc('btc',$v['btc']);
                Db::table('u_newuser_btc_pre')->insert([
                    'user_id'=>$v['user_id'],
                    'add_btc'=>$v['btc'],
                    'new_btc'=>$add_btc,
                    'create_time'=>date('Y-m-d H:i:s'),
                ]);

            }

            Db::commit();
            return json(['status' => 200, 'msg' => '分红成功!']);
        } catch (\Exception $e){
            Db::rollback();
            return json(['status' => 201,'msg' => '失败','error' => $e->getMessage()]);
        }


    }

    /*待定------增加分红批次*/
    public function addBatch(Request $request){
        $batch = $request->param('batch','');
        $start_time = $request->param('start_time','');
        $end_time = $request->param('end_time','');
        $btc_base = $request->param('btc_base','');
        if($batch=='') return json(['status' => 201,'msg' => '批次名字不能为空!']);
        if($start_time=='') return json(['status' => 201,'msg' => '开始时间不能为空!']);
        if($end_time=='') return json(['status' => 201,'msg' => '结束时间不能为空!']);
        if($btc_base=='') return json(['status' => 201,'msg' => '分红基数不能为空!']);

        Db::startTrans();
        try {
            Db::table('u_newuser_setting')->insert([
                'batch'=>$batch,
                'start_time'=>$start_time,
                'end_time'=>$end_time,
                'btc_base'=>$btc_base,
                'create_time'=>date('Y-m-d H:i:s'),
            ]);

            Db::table('u_newuser_base')->insert([
                'batch'=>$batch,
                'start_time'=>$start_time,
                'end_time'=>$end_time,
                'btc_base'=>$btc_base,
                'create_time'=>date('Y-m-d H:i:s'),
            ]);

            Db::commit();
            return json(['status' => 200,'msg' => '批次添加成功!']);


        } catch (\Exception $e){
            Db::rollback();
            return json(['status' => 201,'msg' => '批次添加失败','error' => $e->getMessage()]);
        }

    }


    /*待定------获取所有有效批次号*/
    public function getBatch(Request $request){

            $re=Db::table('u_newuser_setting')->field('batch')->where(['type'=>1])->select();
            $info=[];
            foreach($re as $v){
                $info[]=$v['batch'];
            }
            return json(['status' => 200,'msg' => '批次获取成功!','data'=>$info]);
    }


    /*待定------获取所有有效批次号*/
    public function getBatchList(Request $request){
        $info=Db::table('u_newuser_setting')->select();
        return json(['status' => 200,'msg' => '批次获取成功!','data'=>$info]);
    }






}




