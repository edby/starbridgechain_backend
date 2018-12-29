<?php

namespace app\business\controller;
use think\Config;
use app\business\validate\ChangePwd as CheckPwd;
use app\business\validate\ChangePwdTwo;
use app\common\controller\AuthBase;
use app\home\model\UserInfoModel;
use think\helper\Hash;
use app\common\service\Email;
use Rsa\RSA;

class Password extends AuthBase
{
	function __construct(){
        parent::__construct();
    }

    /**
     * 修改登录密码
     * @return string
     */
    public function updatepwd(UserInfoModel $user){

        $rsa = new RSA();
        $upwd_rsaiv = $this->request->param('upwd_rsaiv');//密文
        if($upwd_rsaiv==""){
            ouputJson(201,lang('FROM_ERROR'));
        }

        $param = json_decode($rsa->se_decrypt_priv($upwd_rsaiv),true);

        $prepwd = $param['prepwd'];//原密码
        $newpwd = $param['newpwd'];//新密码
        $renewpwd = $param['renewpwd']; //确认新密码

        $chekcPwd = new CheckPwd();
        $rst = $chekcPwd->check(['prepwd'=>$prepwd,'newpwd'=>$newpwd,'renewpwd'=>$renewpwd]);
        if(true !== $rst){
            ouputJson('203',$chekcPwd->getError());
        }
        $pwd = Hash::make($newpwd,'md5',['salt' => $this->userinfo['salt']]);
        $suc = UserInfoModel::where(['ui_id'=>$this->uid])->update(['pwd'=>$pwd]);
        if($suc > 0){
            $this->redis->del('usertoken:'.$this->uid);
            ouputJson(200,lang('UP_SUCC'));
        }else{
            ouputJson(202,lang('UP_FAIL'));
        }
    }
    //发送找回密码邮件
    public function sendfogemail(){
        $email = $this->request->param('email');
        if($email==""){
            ouputJson(201,lang('PLASE_INPUT_EMAIL'));
        }
        
        $emailConfig = getSysconfig(['verification_email_title','verification_email_content']);
        $res = Email::sendEmail($email,$emailConfig['verification_email_title'],$emailConfig['verification_email_content']);
        // $res = Email::sendEmai($email,2);
        if($res['error']!=200){
            ouputJson(202,$res['msg']);
        }else{
            ouputJson(200,lang('SEND_SUCCESS'),['emailid'=>$res['emailid']]);
        }
    }

    //忘记密码 - 重置密码
    public function forgetupwd(UserInfoModel $user){
        $rsa = new RSA();
        $upwd_rsaiv = $this->request->param('upwd_rsaiv');//密文
       
        if($upwd_rsaiv==""){
            ouputJson(201,lang('FROM_ERROR'));
        }

        $param = json_decode($rsa->se_decrypt_priv($upwd_rsaiv),true);
        $account = $param['account'];
        $newpwd = $param['newpwd'];//新密码
        $renewpwd = $param['renewpwd']; //确认新密码
        $emailid = $param['emailid'];
        $code = $param['code'];
        if($emailid==""){
            ouputJson(209,lang('PLASE_SEND_CODE'));
        }
        if($account=="" || $newpwd=="" || $renewpwd=="" || $emailid=="" || $code==""){
            ouputJson(202,lang('FROM_ERROR'));
        }
        $chekcPwd = new ChangePwdTwo();
        $rst = $chekcPwd->check(['account'=>$account,'newpwd'=>$newpwd,'renewpwd'=>$renewpwd]);
        if(true !== $rst){
            ouputJson('203',$chekcPwd->getError());
        }
        $emcode = $this->redis->get('emailid_'.$emailid);
        if($emcode==""){
            ouputJson(209,lang('PLASE_TRY_SEND'));
        }
        if($code != $emcode){
            ouputJson(203,lang('AUCODE_ERROR'));
        }
        $userinfo = $user->where(['account'=>$account])->field('ui_id,pwd,salt')->find();
        if(empty($userinfo)){
            ouputJson(204,lang('ACCOUNT_UNKNOW'));
        }

        $newpwd = Hash::make($newpwd,'md5',['salt' => $userinfo['salt']]);
        if($newpwd == $userinfo['pwd']){
            ouputJson(207,lang('NEWP_COM_OP'));
        }

        $userinfo->pwd = $newpwd;
        $suc = $userinfo->save();
        if($suc > 0){
            $this->redis->del('emailid_'.$emailid);
            ouputJson(200,lang('UP_SUCC'));
        }else{
            ouputJson(205,lang('UP_FAIL'));
        }
    }

    public function loginout(){
        if(isset($this->headersData['pcweb']) && $this->headersData['pcweb']==1){
            $this->redis->del('usertoken_web_'.$this->uid);
        }else{
            $this->redis->del('usertoken_app_'.$this->uid);
        }
        ouputJson(200,lang('SYSOUT_SUCC'));
    }

}
