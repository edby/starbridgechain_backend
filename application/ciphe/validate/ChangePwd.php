<?php
/**
 * Created by PhpStorm.
 * User: gaoshichong
 * Date: 2018/4/28
 * Time: 12:25
 */
namespace app\ciphe\validate;
use think\Validate;
use think\Lang;
class ChangePwd extends Validate
{

    protected $message='';
    protected $rule='';

     function __construct() {
        $this->rule = [
            'prepwd'         => 'require|length:8,16',
            'newpwd'         => 'require|length:8,16',
            'renewpwd'       => 'require|confirm:newpwd|different:prepwd',
        ];
        $this->message = [
            'prepwd'               => lang('UP_LPWD_F_ERROR'),
            'newpwd'               => lang('UP_LPWD_F_NEW'),
            'renewpwd.require'     => lang('UP_LPWD_F_NOXT'),
            'renewpwd.confirm'     => lang('UP_LPWD_F_NOXT'),
            'renewpwd.different'   => lang('UP_LPWD_F_NOXD'),
        ];

    }
}