<?php

namespace app\validate;
use think\Validate;
use think\Lang;

class Account extends Validate
{

    protected $message='';
    protected $rule='';

     function __construct() {
        $this->rule = [
            'account'   =>  'email|require',
            'pwd'       =>  'require|checkPwd'
        ];
        $this->message = [
            'account.require'  =>  lang('ACC_NOT_NULL'),
            'account.email'  =>  lang('ACCOUNT_FORMAT_ERROR'),
            'pwd.require'   =>  lang('PWD_NOT_NULL')
        ];

     }
    // 自定义验证规则
    protected function checkPwd($value,$rule,$data=[])
    {
        if(strlen($value) < 8 || strlen($value) > 20){
            return lang('PWD_ET_OX');
        }
        $pattern = '/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{8,20}$/';
        if(!preg_match($pattern,$value)){
            return lang('PWD_FORMAT_ERROR');
        }
        return true;
    }

}
