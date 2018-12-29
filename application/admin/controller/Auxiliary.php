<?php

namespace app\admin\controller;


use think\Controller;
use think\Db;
use think\Request;
use think\Validate;

class Auxiliary extends Controller
{
    /** 添加邮箱黑名单
     * @param Request $request
     */
    public function blackEmail(Request $request)
    {
        $emails = $request->param('email','');
        $emails = json_decode($emails);
        if (empty($emails)){
            ouputJson('201',lang('PARAM_ERROR'));
        }

        $rule = ['email'=>'email|unique:sys_email_blacklist'];


        //整理数据
        $data = [];
        foreach ($emails as $value){
            $rule_data = ['email'=> $value];
            $msg = [
                'email.unique'=>'已存在的邮箱:'.$value,
                'email.email' =>'邮箱格式错误:'.$value,
            ];
            $validate = new Validate($rule,$msg);
            if (!$validate->check($rule_data)){
                ouputJson('201',$validate->getError());
            }else{
                $data[] = [
                    'email'         => $value,
                    'createDate'    => time(),
                ];
            }
        }

        //批量插入
        $res = Db::table('sys_email_blacklist')->insertAll($data);
        if ($res == count($data)){
            ouputJson('200',lang('SUCCESS'));
        }else{
            ouputJson('201',lang('FAIL'));
        }
    }


}