<?php

namespace app\signin\controller;
use think\Config;
use app\common\controller\Base;
use app\signin\model\SignregModel;
use Firebase\JWT\JWT;
use app\validate\Account as AccountValidate;
use think\helper\Hash;
use think\helper\Str;
use think\Db;
use app\common\service\Email;
use Rsa\RSA;
use think\Validate;

class Signinreg extends Base
{
	function __construct(){
        parent::__construct();
        $this->err_callback = config('code.error');
        $this->succ_callback = config('code.success');
        $this->accountValidate = new AccountValidate();
    }
    /**
     * 登录!
     */
    public function login(){
        $rsa = new RSA();
        $login_rsaiv = $this->request->param('login_rsaiv');
        $callback = $this->err_callback;
        $default_ci_id = config('default_coin_id');

        // {"pwd":"123","account":"test1"}
        // $login_rsaiv = $rsa->se_encrypt_pub(json_encode(['pwd'=>'123333333333123333333333123333333333','account'=>'123333333333123333333333333123333333333123333333333331233333333331233333333333123333333333123333333333312333333333312333333333','name'=>'asdasdasda35sf4a6d4f56sd456fs65f4s4fs6']));
        // $data = $rsa->se_decrypt_priv($login_rsaiv);

        // echo $login_rsaiv;
        // die;
        // dump($login_rsaiv);
        // dump($data);


        // die;
        $param = json_decode($rsa->decrypt($login_rsaiv),true);
        // dump($param);
        // die;
        if(empty($param)){
            $callback['msg'] = lang('ACCOUNT_PWD_NOTNULL');
            return json($callback);
        }
        $account = $param['account'];
        $pwd = $param['pwd'];
        if($account=="" || $pwd==""){
        	$callback['msg'] = lang('ACCOUNT_PWD_NOTNULL');
        	return json($callback);
        }
        $user = SignregModel::where('account', $account)->find();
        if(empty($user)){
        	$callback['msg'] = lang('ACCOUNT_NOT_EXITS');
        	return json($callback);
        }
        if(Hash::make($pwd,'md5',['salt' => $user['salt']]) != $user['pwd']){
        	$callback['msg'] = lang('ACCOUNT_PWD_ERROR');
        	return json($callback);
        }
        if($user['status'] == 1){
            $callback['msg'] = lang('ACCOUNT_LOCK');
            return json($callback);
        }


        //推广获利的配置及开关
        $reg_config = getSysconfig(['spread_switch','spread_first','spread_second','spread_times','spread_login_count','spread_login_count_switch']);
        $spreadFlag = false;
        $spread_login_count = $reg_config['spread_login_count']!="" ? $reg_config['spread_login_count'] : 0;

        //查询是否是用户黑名单
        // $is_spread_black = Db::name('sys_email_blacklist')->where(['email'=>$account])->count();
        //推广获利开关 -- 未发放过奖励  -- 不是推广获利黑名单
        if($reg_config['spread_switch'] == 1 &&  $user['is_make_profie']==0){
            //累积超过24小时开关打开 --  超过累计值
            if($reg_config['spread_login_count_switch']==1){
                if($user['login_24count'] >= $spread_login_count){$spreadFlag = true;}
            }else{$spreadFlag = true;}
        }

        $clip = get_client_ip();
        $tokenData = [
            'uid' => $user['ui_id'],
            'expiry_time' => time() + 86400,
            'ip' => $clip
        ];
        $jwtoken = JWT::encode($tokenData, $this->key);
        $signData = [
            'app_id' => config('auth.app_id'),
            'app_screct' => config('auth.app_screct'),
        ];
        //更新登录时间和ip
        $user->updateTime = time();
        $user->lastLoginIp = $clip;
        if($user['loginNum']=="" || $user['loginNum']==0){
            $user->first_login_time = time();
            $user->loginNum = 1;
        }else{
            $user->loginNum = $user['loginNum']+1;
        }
        //首次登录时间不为空 24小时间隔限制小鱼配置值  没有发放过注册推广奖励
        if($user['first_login_time']!="" && $user['login_24count']<$spread_login_count && $user['is_make_profie']==0){
            if( (time() - $user['first_login_time'])/3600 >= 24){
                $user->login_24count = $user['login_24count']+1;
            }
            
        }
        $user->save();

        if($spreadFlag){
            //活动时间范围内
            $evetime = explode('|', $reg_config['spread_times']);
            if(isset($evetime[0]) && isset($evetime[1]) && $evetime[0]!="" && $evetime[1]!=""){
                if(time() >= $evetime[0] && time() <= $evetime[1]){
                    $parent = false;
                    $pparent = false;
                    //发放推广奖励 - 上级推广人
                    if($user['parentrefer_code']!=""){
                        $puinfo = Db::name('user_info')
                        ->alias('ui')
                        ->join('user_finance cf','ui.ui_id=cf.ui_id','left')
                        ->field('ui.ui_id,ui.account,cf.ci_id,cf.amount')
                        ->where(['ui.refer_code'=>$user['parentrefer_code'],'cf.ci_id'=>1])
                        ->find();
                        if(!empty($puinfo)){$parent = true;}
                        // dump($puinfo);
                    }
                    //发放推广奖励 - 上上级推广人
                    if($user['pparentrefer_code']!=""){
                        $ppuinfo = Db::name('user_info')
                        ->alias('ui')
                        ->join('user_finance cf','ui.ui_id=cf.ui_id','left')
                        ->field('ui.ui_id,ui.account,cf.ci_id,cf.amount')
                        ->where(['ui.refer_code'=>$user['pparentrefer_code'],'cf.ci_id'=>1])
                        ->find();
                        if(!empty($ppuinfo)){$pparent = true;}
                        // dump($ppuinfo);
                    }
                    // $ismake = Db::name('user_info')->where(['ui_id'=>$user['ui_id']])->update(['is_make_profie'=>1]);
                    // if($ismake>0){
                    if($parent && $pparent){ //有两级推广送
                        $pui = Db::name('user_spread_logs')->where(['login_uid'=>$user['ui_id'],'benefit_uid'=>$puinfo['ui_id']])->find();
                        $ppui = Db::name('user_spread_logs')->where(['login_uid'=>$user['ui_id'],'benefit_uid'=>$ppuinfo['ui_id']])->find();
                        
                        if(!empty($pui) && !empty($ppui)){
                            Db::name('user_info')->where(['ui_id'=>$user['ui_id']])->update(['is_make_profie'=>1]);
                        }else{
                            $pblack = Db::name('sys_email_blacklist')->where(['email'=>$puinfo['account']])->count();
                            $ppblack = Db::name('sys_email_blacklist')->where(['email'=>$ppuinfo['account']])->count();
                            if($pblack<=0){
                                $this->sednregSdt($reg_config['spread_first'],$user,$puinfo,$pui);//发放一级推广账户奖励
                            }else{
                                $this->wirte_spread_logs($reg_config['spread_first'],$user,$puinfo);
                            }
                            if($ppblack<=0){
                                $this->sednregSdt($reg_config['spread_second'],$user,$ppuinfo,$ppui);//发放二级推广账户奖励
                            }else{
                                $this->wirte_spread_logs($reg_config['spread_first'],$user,$ppuinfo);
                            }
                        }
                    }elseif($parent && !$pparent){  //有一级推广送
                        $totval = decimal_format(bcadd($reg_config['spread_first'], $reg_config['spread_second'],15),15,false);
                        $pui = Db::name('user_spread_logs')->where(['login_uid'=>$user['ui_id'],'benefit_uid'=>$puinfo['ui_id']])->find();
                        if(!empty($pui)){
                            Db::name('user_info')->where(['ui_id'=>$user['ui_id']])->update(['is_make_profie'=>1]);
                        }else{
                            $pblack = Db::name('sys_email_blacklist')->where(['email'=>$puinfo['account']])->count();
                            if($pblack<=0){
                                $this->sednregSdt($totval,$user,$puinfo,$pui);//发放一级推广账户奖励
                            }else{
                                $this->wirte_spread_logs($totval,$user,$puinfo);
                            }
                            
                        }
                        
                    }
                    // }
                }
            }
        }

        $rsa = new RSA();
        $iv = $rsa->encrypt(json_encode($signData));

      	$this->succ_callback['data']['iv'] = $iv;
        $this->succ_callback['data']['token'] = $jwtoken;
        
        // $this->succ_callback['data']['app_id'] = config('auth.app_id');
        // $this->succ_callback['data']['app_screct'] = config('auth.app_screct');
        
      	$this->succ_callback['msg'] = lang('LOGIN_SUCCESS');
        if(isset($this->headersData['pcweb']) && $this->headersData['pcweb']==1){
            $this->redis->set('usertoken_web_'.$user['ui_id'],md5($jwtoken));
        }else{
            $this->redis->set('usertoken_app_'.$user['ui_id'],md5($jwtoken));
        }
      	return json($this->succ_callback);
    }

    private function wirte_spread_logs($amount,$user,$puinfo){
        Db::name('user_spread_logs')->insert([
            'ci_id'=>$puinfo['ci_id'],
            'benefit_uid'=>$puinfo['ui_id'],
            'benefit_account'=>$puinfo['account'],
            'login_uid'=>$user['ui_id'],
            'login_account'=>$user['account'],
            'amount' => $amount,
            'status' => 2,
            'fail_reason' => '受益者用户是黑名单用户',
            'creatime'=>time(),
            'creatime_format'=>date('Y/m/d H:i:s')
        ]);
    }
    /**
     * 发放推广奖励
     */
    private function sednregSdt($amount,$user,$puinfo,$hisinfo){
        //查询是否已经发放过
        if(empty($hisinfo)){
            $default_ci_id = config('default_coin_id');
            $setData = set_user(3,$amount,2,$default_ci_id);//扣除推广奖励账户资金
            Db::startTrans();
            $samut = set_user_amount("spread",$setData);//扣除推广奖励账户资金
            if($samut > 0){ //扣除成功
                // $suc = Db::name('user_finance')->where([
                //     'ci_id'=>$default_ci_id,
                //     'ui_id'=>$puinfo['ui_id']
                // ])->setInc('amount',$amount);
                $suc = updateUserBalance($puinfo['ui_id'],$default_ci_id,"spread",[['field'=>'amount','type'=>'inc','val'=>$amount]]);
                if($suc > 0){
                    $ismake = Db::name('user_spread_logs')->insert([
                        'ci_id'=>$puinfo['ci_id'],
                        'benefit_uid'=>$puinfo['ui_id'],
                        'benefit_account'=>$puinfo['account'],
                        'login_uid'=>$user['ui_id'],
                        'login_account'=>$user['account'],
                        'amount' => $amount,
                        'creatime'=>time(),
                        'creatime_format'=>date('Y/m/d H:i:s')
                    ]);
                    if($ismake > 0){
                        Db::commit();    
                    }else{
                        Db::rollback();
                    }
                }else{
                    Db::rollback();
                }
            }elseif($samut < 0){ //余额不足
                $ismake = Db::name('user_spread_logs')->insert([
                    'ci_id'=>$puinfo['ci_id'],
                    'benefit_uid'=>$puinfo['ui_id'],
                    'benefit_account'=>$puinfo['account'],
                    'login_uid'=>$user['ui_id'],
                    'login_account'=>$user['account'],
                    'amount' => $amount,
                    'status' => 2,
                    'fail_reason' => '发放失败，推广注册账户可用SDT余额不足',
                    'creatime'=>time(),
                    'creatime_format'=>date('Y/m/d H:i:s')
                ]);
                if($ismake > 0){
                    Db::commit();
                }else{
                    Db::rollback();
                }
            }else{
                Db::rollback();
            }
        }
        
    }
    /**
     * 注册
     */
    public function register(SignregModel $user){
    	$callback = $this->err_callback;

        $rsa = new RSA();

        $register_rsaiv = $this->request->param('register_rsaiv');

        // {"pwd":"123","emailid":"123"}
        // $register_rsaiv = $rsa->encrypt(json_encode(['pwd'=>'123','emailid'=>'123']));
        $param = json_decode($rsa->decrypt($register_rsaiv),true);
        if(empty($param)){
            $callback['msg'] = lang('PLASE_SEND_CODE');
            return json($callback);
        }

    	$account = $this->request->param('account'); //账号
        $emailcode = $this->request->param('emailcode'); //验证码
        $invcode = $this->request->param('invcode'); //邀请码
        
        $pwd = $param['pwd']; //密码
        $emailid = $param['emailid']; //emailid

        $default_ci_id = config('default_coin_id');
        $emidcode = $this->redis->get('emailid_'.$emailid);
        if($emailid=="" || $emidcode==""){
        	$callback['msg'] = lang('PLASE_SEND_CODE');
        	return json($callback);
        }

        //验证邮箱是否为app验证过的邮箱账号
        $e_res = Db::table('wallet_email_check')->where('email','=',$account)->count();
        if($e_res!=1){
            ouputJson(402,'邮箱未验证!!');
        }

        if($emailcode==""){$callback['msg'] = lang('INPUT_CODE');return json($callback);}
    	$validate_param = $this->accountValidate->check(['account'=>$account,'pwd'=>$pwd]);
    	if(!$validate_param){
    		$callback['msg'] = $this->accountValidate->getError();
        	return json($callback);
    	}
    	if(Str::lower($emailcode) != Str::lower($emidcode)){
    		$callback['msg'] = lang('CODE_ERROR');
        	return json($callback);
    	}

    	$userinfo = SignregModel::where('account', $account)->find();
    	if(!empty($userinfo)){
    		$callback['msg'] = lang('ACCOUNT_EXITS');
        	return json($callback);
    	}

    	//无效的邀请码
        if($invcode!=""){
            $invdata = SignregModel::where(['refer_code'=>$invcode])->field('ui_id,refer_code,parentrefer_code')->find();
            if(empty($invdata)){
                $callback['msg'] = lang('INVALID_INCODE');
                return json($callback);
            }
        }
        //用户组分组 group_info
        $ip = get_client_ip();
        $headersData = get_all_headers();
        
        if(isset($headersData['pcweb']) && $headersData['pcweb']==1){
            $user_source = 2;
        }else{
            $user_source = 1;
        }
        $refer_code = spread_code(8);//生成唯一推广码
        $salt = Str::random(6); //生成盐值
        $regData['account'] = $account;
        $regData['pwd'] = Hash::make($pwd,'md5',['salt' => $salt]);
        $regData['salt'] = $salt;
        $regData['email'] = $account;
        $regData['name'] = $account;
        $regData['status'] = 0;
        $regData['createTime'] = time();
        $regData['updateTime'] = time();
        $regData['user_source'] = $user_source;
        $regData['refer_code'] = $refer_code;
        if($invcode !=""){
            $regData['parentrefer_code'] = $invdata['refer_code'];
            if( $invdata['parentrefer_code'] != "" ){
                $regData['pparentrefer_code'] = $invdata['parentrefer_code'];
            }
        }
        $regData['lastLoginIp'] = $ip;
        $regData['registerIp'] = $ip;

        //查询是否开启注册奖励
        $regconfig = getSysconfig(['register_reward_switch','register_reward_amount']);
        Db::startTrans();
        try {
            $user->data($regData)->save();
            $userId = $user->getLastInsID();
            //用户分组
            $defaultGroup = Db::name('group_info')->where(['defaultflag'=>1])->find();
            if(empty($defaultGroup)){
                Db::rollback();
                $callback['msg'] = lang('REGERROR_SMANAGE');
                return json($callback);
            }
            Db::name('user_group')->insert([
                'ui_id' => $userId,
                'gi_id' => $defaultGroup['gi_id']
            ]);
            //创建账户币种信息
            createCoinAccount($userId);
            //添加注册奖励金额
            if($regconfig['register_reward_switch']==1){
                // Db::name('user_finance')->where(['ui_id'=>$userId,'ci_id'=>$default_ci_id])->setInc('amount',$regconfig['register_reward_amount']);
                updateUserBalance($userId,$default_ci_id,"regis",[['field'=>'amount','type'=>'inc','val'=>$regconfig['register_reward_amount']]]);
            }
            Db::commit();
            $this->succ_callback['msg'] = lang('REG_SUCCESS');
            $this->redis->del('emailid_'.$emailid);//删除验证码
            return json($this->succ_callback);
        } catch (Exception $e) {
            Db::rollback();
            $callback['msg'] = lang('REG_FAILD_TRAGAIN').$exception->getMessage();
            return json($callback);
        }
    }

    /**
     * 发送注册邮件
     */
    public function sendregemail(){
        $email = $this->request->param('email');
        if($email==""){
            ouputJson(201,lang('PLASE_INPUT_EMAIL'));
        }

        //验证邮箱是否为app验证过的邮箱账号
        $e_res = Db::table('wallet_email_check')->where('email','=',$email)->count();
        if($e_res!=1){
            ouputJson(402,'邮箱未验证!!');
        }

        $uinfo = Db::name('user_info')->where(['account'=>$email])->find();
        if(!empty($uinfo)){
            ouputJson(201,lang('EMAIL_REGED'));
        }

        $emailConfig = getSysconfig(['verification_email_title','verification_email_content']);
        $res = Email::sendEmail($email,$emailConfig['verification_email_title'],$emailConfig['verification_email_content']);
        
        // $res = Email::sendEmail2($email,1);
        if($res['error']!=200){
            ouputJson(202,$res['msg']);
        }else{
            ouputJson(200,lang('SEND_SUCCESS'),['emailid'=>$res['emailid']]);
        }
    }



    /**
     * 注册用户时 邮箱验证
     * @param Request $request
     */
    public function accountCheck(){
        if ($this->request->isPost()) {
            $email = $this->request->param('email', '');
            $code = $this->request->param('code', '');
            if ($code == null || $email == null) {
                return json(['code' => 402, 'msg' => '参数错误!']);
            } else {
                //验证邮箱
                $check_data = ['account' => $email];
                $rule = [
                    'account' => 'email|unique:user_info',
                ];
                $msg = [
                    'account.email' => '邮箱格式错误!',
                    'account.unique' => '该邮箱已被注册!',
                ];
                $validate = new Validate($rule, $msg);

                //获取check表中数据
                //$check = Db::table('wallet_email_check')
                //    ->where('email', '=', $email)
                //    ->find();

                //未注册邮箱可进行解密操作
                if ($validate->check($check_data)) {
                    //加密方式
                    $method = 'AES-128-CBC';
                    //第一次加密参数
                    $key1 = 'gehua20181108001';
                    $iv1 = '201809011300001x';
                    //第二次加密参数
                    $key2 = 'starbridgechain1';
                    $iv2 = '201808300630002x';
                    //第一次解码
                    try{
                        $first_de = openssl_decrypt($code, $method, $key2, 2, $iv2);
                    }catch (Exception $e){
                        return json(['code' => 404, 'msg' => '验证失败3!']);
                    }
                    //第一次解密失败
                    if ($first_de === false) {
                        return json(['code' => 404, 'msg' => '验证失败3!']);
                    }
                    //处理
                    $first_arr = explode('*', $first_de);
                    if(count($first_arr)<2){
                        return json(['code' => 405, 'msg' => '验证失败21!']);
                    }
                    //$app_code = iconv('gbk','utf-8',$first_arr[0]);
                    $app_code = $first_arr[0];

                    //第二次解码
                    try{
                        $second_de = openssl_decrypt($app_code, $method, $key1, 2, $iv1);
                    }catch (Exception $e){
                        return json(['code' => 405, 'msg' => '验证失败2!']);
                    }
                    //第二次解密失败
                    if ($second_de === false) {
                        return json(['code' => 405, 'msg' => '验证失败2!']);
                    }
                    $second_de = iconv('gbk','utf-8',$second_de);

                    $first = substr(trim($second_de),0,35);
                    $second = substr(trim($first_arr[1]),0,35);

                    //两次解密成功
                    if ($first == $second) {
                        //获取配置参数
                        $um_count = Db::table('wallet_config')
                            ->where('fd_id', '=', 1)
                            ->value('fd_uuid_mac_count');
                        //mac地址出现次数
                        $mac_count = Db::table('wallet_email_check')
                            ->where('mac', '=', $first_arr[2])
                            ->count();
                        //uuid出现次数
                        $uuid_count = Db::table('wallet_email_check')
                            ->where('uuid', '=', $first_arr[1])
                            ->count();

                        //是否再配置之内
                        if ($mac_count < $um_count && $uuid_count < $um_count) {
                            $data = [
                                'email' => $email,
                                'mac' => $first_arr[2],
                                'uuid' => $first_arr[1],
                                'createDate' => time(),
                            ];

                            //获取check表中数据
                            $check = Db::table('wallet_email_check')
                                ->where('email', '=', $email)
                                ->find();

                            //新增 OR 修改  信息
                            if ($check){
                                $res = Db::table('wallet_email_check')
                                    ->where('id','=',$check['id'])
                                    ->update($data, true);
                            }else{
                                $res = Db::table('wallet_email_check')
                                    ->insert($data, true);
                            }
                            //
                            if ($res) {
                                
                                $headersData = get_all_headers();
                                if(isset($headersData['pcweb']) && $headersData['pcweb']==1){
                                    return json(['code' => 200, 'msg' => '验证成功!']);
                                }else{
                                    $emailConfig = getSysconfig(['verification_email_title','verification_email_content']);
                                    $res = Email::sendEmail($email,$emailConfig['verification_email_title'],$emailConfig['verification_email_content']);
                                    return json(['code' => 200, 'msg' => '验证成功!','data'=>['emailid'=>$res['emailid']]]);
                                }

                            } else {
                                return json(['code' => 408, 'msg' => '验证失败1!']);
                            }
                        } else {
                            return json(['code' => 407, 'msg' => '验证失败!超过次数!']);
                        }

                    } else {
                        //uuid不一致
                        return json(['code' => 406, 'msg' => '验证失败!',$first,$second]);
                    }
                } else {
                    return json(['code' => 403, 'msg' => $validate->getError()]);
                }
            }
        } else {
            return json(['code' => 401, 'msg' => '请求方式错误!']);
        }
    }

    public function getAgreement(){
        $type = $this->request->param('type');
        if($type==""){
            $type = "agreement-chinese";
        }
        $agreemenText = Db::name('wallet_agreement')->where(['fd_type'=>$type])->value('fd_text');
        ouputJson(200,'',['agreement'=>$agreemenText]);
    }
}