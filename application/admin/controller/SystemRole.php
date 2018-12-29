<?php
namespace app\admin\controller;


use app\admin\model\SysRoleModel;
use app\admin\model\SysUserRoleModel;
use app\admin\validate\SysRole;
use think\Controller;
use think\Db;
use think\Exception;
use think\Request;

class SystemRole extends Controller
{

    /*
     * 角色列表
     */
    public function listRole(SysRoleModel $role)
    {
        $field = [
            'SR_ID as id',
            'SR_Name as name',
            'SR_Remark as remark'
        ];
        $data = $role->field($field)->select();
        if ($data != null){
            ouputJson('200',lang('SUCCESS'),$data);
        }else{
            ouputJson('201',lang('NO_DATA'));
        }
    }

    /*
     * 添加角色
     */
    public function addRole(Request $request,SysRoleModel $role)
    {
        $data = [
            'name'          => $request->param('name',''),
            'remark'        => $request->param('remark',''),
        ];
        $validate = new SysRole();
        if (!$validate->check($data)){
            ouputJson('201',$validate->getError());
        }
        //保存的数组
        $save_data = [
            'SR_Name'       => $data['name'],
            'SR_Remark'     => $data['remark']
        ];
        $result = $role->save($save_data);
        if ($result){
            ouputJson('200',lang('SUCCESS'));
        }else{
            ouputJson('201',lang('SYSTEM_ERROR'));
        }
    }

    /*
     * 编辑角色
     */
    public function showRole(Request $request,SysRoleModel $role)
    {
        $id = $request->param('id','');
        if ($id){
            $where = ['SR_ID'=>$id];
            $data = $role->where($where)->find();
            if ($data != null){
                ouputJson('200',lang('SUCCESS'),$data);
            }else{
                ouputJson('201',lang('NO_DATA'));
            }
        }else{
            ouputJson('201',lang('ID_ERROR'));
        }
    }

    /*
     * 编辑角色
     */
    public function editRole(Request $request,SysRoleModel $role)
    {
        $id = $request->param('id','');
        if ($id != ''){
            $data = [
                'name'          => $request->param('name',''),
                'remark'        => $request->param('remark',''),
            ];
            $validate = new SysRole();
            if (!$validate->check($data)){
                ouputJson('201',$validate->getError());
            }
            //保存的数组
            $save_data = [
                'SR_Name'       => $data['name'],
                'SR_Remark'     => $data['remark']
            ];
            $result = $role->save($save_data,['SR_ID'=>$id]);
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
     * 删除角色
     */
    public function delRole(Request $request,SysRoleModel $role,SysUserRoleModel $urmodel)
    {
        $id = $request->param('id','');//角色id
        if ($id){
            //查询
            $res1 = $role->where('SR_ID','=',$id)->find();
            $res2 = $urmodel->where('SR_ID','=',$id)->find();
            if (!$res1){
                ouputJson('203','NO_ROLE');
            }
        Db::startTrans();

        try{

            if ($res1){
                $result1 = $role->where('SR_ID','=',$id)->delete();
            }

            if ($res2){
                $result2 = $urmodel->where('SR_ID','=',$id)->delete();
            }

        }catch(Exception $e){
            ouputJson('202',lang('SYSTEM_ERROR'));
            Db::rollback();

        }
        Db::commit();

        ouputJson('200',lang('SUCCESS'));

        }else{
            ouputJson('201',lang('ID_ERROR'));
        }
    }

    /*
     * 用户关联角色
     */
    public function admin_role(Request $request,SysUserRoleModel $urmodel)
    {
        $admin_id = $request->param('adminid','');
        $role_id = $request->param('roleid','');
        if ($admin_id != '' && $role_id != ''){

            //新增或者更新
            $where = [
                'SU_ID'=>$admin_id,
            ];

            //查询是否存在关联
            $id = $urmodel->where($where)->value('SUR_ID');//->field('SUR_ID')
            $data = [
                'SU_ID'=>$admin_id,
                'SR_ID'=>$role_id
            ];

            if ($id){//修改
                $result = $urmodel->save($data,['SUR_ID'=>$id]);
            }else{//新增
                $result = $urmodel->save($data);
            }

            if ($result){
                ouputJson('200',lang('SUCCESS'));
            }else{
                ouputJson('201',lang('SYSTEM_ERROR'));
            }
        }else{
            ouputJson('201',lang('PARAM_ERROR'));
        }
    }
    
    /*
     * 角色下admin列表
     */
    public function roleAdminList(Request $request)
    {
        $roleid = $request->param('id','');
        if ($roleid){
            //查询该角色下的所有管理员
            $where = ['sr.SR_ID'=>$roleid];
            $field = [
                'su.SU_ID as id',
                'su.SU_Acount as acount',
                'su.SU_Name as name',
            ];
            $data = db('sys_role')->alias('sr')
                ->field($field)
                ->join('sys_user_role sur','sr.SR_ID = sur.SR_ID')
                ->join('sys_user su','su.SU_ID = sur.SU_ID')
                ->where($where)
                ->select();
            ouputJson('200',lang('SUCCESS'),$data);

        }else{
            ouputJson('201',lang('ID_ERROR'));
        }
    }

    /*
     * 删除角色下admin
     */
    public function delRoleAdmin(Request $request)
    {

        $roleid = $request->param('roleid','');
        $adminid = $request->param('adminid','');

        if ($roleid != '' && $adminid != ''){
            $where = [
                'SU_ID'     => $adminid,
                'SR_ID'     => $roleid,
            ];
            //查询
            $res = db('sys_user_role')->where($where)->find();
            if (!$res){
                ouputJson('203',lang('NO_RELATION'));
            }
            $result = db('sys_user_role')->where($where)->delete();
            if ($result){
                ouputJson('200',lang('SUCCESS'));
            }else{
                ouputJson('200',lang('SYSTEM_ERROR'));
            }
        }else{
            ouputJson('201',lang('ID_ERROR'));
        }
    }
    
}