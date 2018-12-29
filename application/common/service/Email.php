<?php


namespace app\common\service;
use redis\Redis;
use app\common\model\EmailSetting;
use app\common\model\EmailSend;
use think\helper\Str;
use think\Db;
//邮件发送
class Email
{



    /**
     * 发送验证类邮件
     * @param $subject          string 主题
     * @param array $to         array  收件人
     * @param $body             string 内容
     * @param $type             int 邮件类型(1验证邮件,2通知邮件)
     * @param $uu_id            string 用户ID(有则传)
     * @param string $charSet   string 默认编码
     * @param string $contentType
     * @return bool|int
     */
     public static function sendEmail($to,$subject,$body,$type=1,$uu_id="",$charSet='utf-8',$contentType='text/html'){
        $rst = filter_var($to, FILTER_VALIDATE_EMAIL);
        if (!$rst) {
            return ['error' => 201, 'msg' => lang('EMAIL_FORMAT_ERROR')];
        }
        //发送间隔60s
        $hisemlog = EmailSend::where(['email'=>$to])->order('sendtime desc')->limit(1)->find();
        if(!empty($hisemlog)){
            if(time() - $hisemlog['sendtime'] < 60){
                return ['error' => 201, 'msg' => lang('EMAIL_WAIT_SEND')];
            }
        }
        //获取邮箱发送配置
        $redis = Redis::instance();
        $configs = @unserialize($redis->get('email_config'));
        if(empty($configs) || $configs==false){
            $configs = EmailSetting::where(['status'=>1])->field('username,nickname,password,host,port,security,flag')->select();
            if(empty($configs) || count($configs)<=0){
                return ['error'=>2011,'msg'=>lang('NO_EMAIL_PARAM')];
            }
            $redis->setex('email_config',600,@serialize($configs));//10分钟更新一次缓存
        }
        $config = [];
        if(!empty($configs)){
            foreach ($configs as $k => $v) {
                if($v['flag'] == 1){
                    $config = $v;
                    break;
                }
            }
            if(empty($config)){
                $config = $configs[rand(0,count($configs)-1)];
            }
        }
        if(!empty($config)){
            if($type == 1){
                $code = Str::random(6);
                $body = str_replace('{{code}}', $code, $body);
            }
            $emailid = strtolower(Str::random(12));
            try{

                $transport  = \Swift_SmtpTransport::newInstance($config['host'],$config['port'],$config['security'])
                    ->setUsername($config['username'])
                    ->setPassword($config['password']);
                $mailer     = \Swift_Mailer::newInstance($transport);
                $message    = \Swift_Message::newInstance()
                    ->setFrom(array($config['username'] => $config['nickname']))
                    ->setTo([$to])
                    ->setSubject($subject)
                    ->setCharset($charSet)
                    ->setContentType($contentType)
                    ->setBody($body);
                if($mailer->send($message)){
                    if($type==1){
                        $redis->setex('emailid_'.$emailid,300,$code);//有效期5分钟
                        EmailSend::insert([
                            'uu_id' => $uu_id,
                            'email' => $to,
                            'emailid' => $emailid,
                            'type' => $type,
                            'ipaddr' => get_client_ip(),
                            'sendtime' => time(),
                            'code' => $code,
                            'status' => 1
                        ]);
                        return ['error'=>200,'msg'=>lang('SEND_SUCCESS'),'emailid'=>$emailid,'code'=>$code];
                    }else{
                        return ['error'=>200,'msg'=>lang('SEND_SUCCESS')];
                    }
                }

            }catch (\Exception $e){
                // dump($e);
               return ['error'=>201,'msg'=>$e->getMessage()];
            }
        }else{
            return ['error'=>201,'msg'=>lang('NO_EMAIL_PARAM')];
        }
    }



    /**
     * @param $subject          string 主题
     * @param array $to         array  收件人
     * @param $body             string 内容
     * @param string $charSet   string 默认编码
     * @param string $contentType
     * @return bool|int
     */
    
     public static function sendEmail2($to,$type=0,$uu_id="",$charSet='utf-8',$contentType='text/html'){
        if($type=="" || $type==0){
            return ['error'=>201,'msg'=>lang('EMAIL_TYPE_ERROR')];
        }
        $rst = filter_var($to, FILTER_VALIDATE_EMAIL);
        if (!$rst) {
            return ['error' => 201, 'msg' => lang('EMAIL_FORMAT_ERROR')];
        }
        if($type == 1){
            $uinfo = Db::name('user_info')->where(['account'=>$to])->find();
            if(!empty($uinfo)){
                return ['error' => 201, 'msg' => lang('EMAIL_REGED')];
            }
        }elseif($type == 2){
            $uinfo = Db::name('user_info')->where(['account'=>$to])->find();
            if(empty($uinfo)){
                return ['error' => 201, 'msg' => lang('ACCOUNT_NOT_EXITS')];
            }
        }elseif($type==3){//提现申请发送邮箱
            
        }
        $hisemlog = EmailSend::where(['email'=>$to])->order('sendtime desc')->limit(1)->find();
        if(!empty($hisemlog)){
            if(time() - $hisemlog['sendtime'] < 60){
                return ['error' => 201, 'msg' => lang('EMAIL_WAIT_SEND')];
            }
        }
        $redis = Redis::instance();
        $configs = @unserialize($redis->get('email_config'));
        if(empty($configs) || $configs==false){

            $configs = EmailSetting::where(['status'=>1])->field('username,nickname,password,host,port,security,title,verification,flag')->select();

            if(empty($configs) || count($configs)<=0){
                return ['error'=>2011,'msg'=>lang('NO_EMAIL_PARAM')];
            }
            $redis->setex('email_config',600,@serialize($configs));//10分钟更新一次缓存
        }
        $config = [];
        if(!empty($configs)){
            foreach ($configs as $k => $v) {
                if($v['flag'] == 1){
                    $config = $v;
                    break;
                }
            }
            if(empty($config)){
                $config = $configs[rand(0,count($configs)-1)];
            }
        }
        if(!empty($config)){
            $code = Str::random(6);
            $body = str_replace('{{code}}', $code, $config['verification']);
            $subject = $config['title'];
            $emailid = strtolower(Str::random(12));
            try{
                $transport  = \Swift_SmtpTransport::newInstance($config['host'],$config['port'],$config['security'])
                    ->setUsername($config['username'])
                    ->setPassword($config['password']);
                $mailer     = \Swift_Mailer::newInstance($transport);
                $message    = \Swift_Message::newInstance()
                    ->setFrom(array($config['username'] => $config['nickname']))
                    ->setTo([$to])
                    ->setSubject($subject)
                    ->setCharset($charSet)
                    ->setContentType($contentType)
                    ->setBody($body);
                if($mailer->send($message)){
                    $redis->setex('emailid_'.$emailid,300,$code);//有效期5分钟
                    EmailSend::insert([
                        'uu_id' => $uu_id,
                        'email' => $to,
                        'emailid' => $emailid,
                        'type' => $type,
                        'ipaddr' => get_client_ip(),
                        'sendtime' => time(),
                        'code' => $code,
                        'status' => 1
                    ]);
                    return ['error'=>200,'msg'=>lang('SEND_SUCCESS'),'emailid'=>$emailid,'code'=>$code];
                }
            }catch (\Exception $e){
               // dump($e->getMessage());
               return ['error'=>201,'msg'=>$e->getMessage()];
            }
        }else{
            return ['error'=>201,'msg'=>lang('NO_EMAIL_PARAM')];
        }
    }
    
}