<?php
namespace app\admin\controller;

use app\admin\model\SysUserModel;
use app\admin\model\SysUserRoleModel;
use app\admin\validate\SysUser;
use think\Controller;
use think\Db;
use think\Exception;
use think\helper\Hash;
use think\helper\Str;
use think\Request;

class SystemUser extends Controller
{
    /*
     * 管理员列表
     */
    public function adminList()
    {
        $field = [
            'su.SU_ID AS id',
            'su.SU_Acount as acount',
            'su.SU_Name as name',
            'sr.SR_Name as role_name',
            'if(su.SU_LoginTime="","",FROM_UNIXTIME(su.SU_LoginTime,"%Y-%m-%d")) as logintime',
            'su.SU_Duty as duty',
            'su.SU_Remark as remark'
        ];
        $data = db('sys_user')->alias('su')
            ->field($field)
            ->join('sys_user_role sur','su.SU_ID = sur.SU_ID','left')
            ->join('sys_role sr','sr.SR_ID = sur.SR_ID','left')
            ->select();
        //返回数据
        ouputJson('200',lang('SUCCESS'),$data);
    }

    /*
     * 添加管理员
     */
    public function addAdmin(Request $request,SysUserModel $user)
    {
        $data = [
            'account'               => $request->param('account',''),
            'name'                  => $request->param('name',''),
            'pwd'                   => $request->param('pwd',''),
            'repwd'                 => $request->param('repwd',''),
            'duty'                  => $request->param('duty','')
        ];
        $validate = new SysUser();
        if (!$validate->check($data)){
            ouputJson('201',$validate->getError());
        }
        //个人密码 盐
        $salt = Str::random(6);
        //保存的数组
        $save_data = [
            'SU_Acount'             => $data['account'],
            'SU_Name'               => $data['name'],
            'SU_PWD'                => Hash::make($data['pwd'],'md5',['salt' => $salt]),
            'SU_Salt'               => $salt,
            'SU_Duty'               => $data['duty'],
            'SU_Remark'             => $request->param('remark',''),
        ];
        //保存数据
        $result = $user->save($save_data);
        if ($result){
            ouputJson('200',lang('SUCCESS'));
        }else{
            ouputJson('201',lang('SYSTEM_ERROR'));
        }
    }

    /*
     * 修改管理员
     */
    public function showAdmin(Request $request,SysUserModel $user)
    {
        $id = $request->param('id','');
        if ($id){
            $field = [
                'SU_Acount as acount',
                'SU_Name as name',
                'SU_Duty as duty',
                'SU_Remark as remark'
            ];
            $where = ['SU_ID'=>$id];
            $data = $user->field($field)->where($where)->find();
            output_cache_put('200',lang('SUCCESS',$data));
        }else{
            ouputJson('201',lang('ID_ERROR'));
        }
    }

    /*
     * 保存修改
     */
    public function editAdmin(Request $request,SysUserModel $user)
    {
        $id = $request->param('id','');
        if ($id){
            $save_data = [
                'SU_Acount'         => $request->param('account',''),
                'SU_Name'           => $request->param('name',''),
                'SU_Duty'           => $request->param('duty',''),
                'SU_Remark'         => $request->param('remark','')
            ];
            if ($save_data['SU_Acount'] == '' || $save_data['SU_Name'] == ''){
                ouputJson('201',lang('PARAM_ERROR'));
            }
            $where = ['SU_ID'=>$id];
            $result = $user->save($save_data,$where);
            if ($result){
                ouputJson('200',lang('SUCCESS'));
            }else{
                ouputJson('201',lang('SYSTEM_ERROR'));
            }
        }else{
            ouputJson('201',lang('ID_ERROR'));
        }
    }

    /*
     * 删除 管理员
     */
    public function delAdmin(Request $request,SysUserModel $user,SysUserRoleModel $urModel)
    {
        $id = $request->param('id','');//管理员id
        if ($id){
            //查询
            $res1 = $user->where('SU_ID','=',$id)->find();
            $res2 = $urModel->where('SU_ID','=',$id)->find();
            if (!$res1){
                ouputJson('203','NO_ADMIN');
            }
            Db::startTrans();
            try{
                if ($res1){
                    $result1 = $user->where('SU_ID','=',$id)->delete();
                }

                if ($res2){
                    $result2 = $urModel->where('SU_ID','=',$id)->delete();
                }


            }catch (Exception $e){
                ouputJson('202',lang('SYSTEM_ERROR'));
                Db::rollback();
            }
            Db::commit();

            ouputJson('200',lang('SUCCESS'));

        }else{
            ouputJson('201',lang('ID_ERROR'));
        }
    }

    
}