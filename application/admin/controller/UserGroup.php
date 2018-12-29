<?php
namespace app\admin\controller;


use app\admin\model\GroupInfoModel;
use app\admin\model\UserGroupModel;
use app\admin\validate\GroupInfo;
use think\Controller;
use think\Db;
use think\Request;

class UserGroup extends Controller
{
    /*
     * 用户类型列表
     */
    public function groupList(Request $request,GroupInfoModel $groupinfo)
    {
        //类型列表
        $grouplist = $groupinfo
            ->field('gi_id,fee,show_fee,name')
            ->where('status','=','1')
            ->select();

        //统计类型 余额总数
        foreach ($grouplist as $item) {
            $ids = Db::table('user_group')
                ->where('gi_id','=',$item['gi_id'])
                ->column('ui_id');
            if (!empty($ids)){
                $ids = implode(',',$ids);
            }else{
                $ids = 0;
            }
            $sql = "SELECT ci.short_name,SUM(uf.amount) AS amount,(SUM(uf.trans_frost)+SUM(uf.out_frost)) AS amountd FROM user_finance AS uf JOIN coin_info AS ci ON ci.ci_id = uf.ci_id WHERE uf.ui_id in ($ids) GROUP BY uf.ci_id";
            $item['amount'] = Db::query($sql);
        }

        foreach($grouplist as $item){
            if (!empty($item['amount'])){
                foreach($item['amount'] as $value){
                    $price = get_time_price($value['short_name']);
                    $value['amount'] = $value['amount']*$price;
                    $value['amountd'] = $value['amountd']*$price;
                }
            }
        }
        $data = [];
        foreach ($grouplist as $item) {
            $arr = [];
            $arr['gi_id'] = $item['gi_id'];
            $arr['name'] = $item['name'];
            $arr['fee'] = $item['fee'];
            $arr['show_fee'] = $item['show_fee'];
            if (!empty($item['amount'])){
                $arr['amount_sum'] = array_sum(array_column($item['amount'],'amount'));
                $arr['amountd_sum'] = array_sum(array_column($item['amount'],'amountd'));
                $item['amount_sum'] = array_sum(array_column($item['amount'],'amount'));
                $item['amountd_sum'] = array_sum(array_column($item['amount'],'amountd'));
            }
            unset($item['amount']);
            $data[] = $arr;
        }

        //导出表格
        if ($request->method() == 'GET'){

            $title = ['name'=>'类型名称','fee'=>'手续费','show_fee'=>'折扣','amount_sum'=>'可用(换算USDT)','amountd_sum'=>'冻结(换算USDT)'];

            $filename = '类型列表';

            export_excel_zip($filename,$title,$data);
            die;
        }

        ouputJson('200',lang('SUCCESS'),$grouplist);
    }

    /*
     * 添加用户类型
     */
    public function addGroup(Request $request,GroupInfoModel $groupinfo)
    {
        $data = [
            'name'              => $request->param('name',''),
            'fee'               => $request->param('fee',''),
            'show_fee'          => $request->param('show_fee',''),
        ];

        $validate = new GroupInfo();
        if (!$validate->check($data)){
            ouputJson('201',$validate->getError());
        }
        //保存数组
        $save_data = [
            'fee'               => $data['fee'],
            'show_fee'          => $data['show_fee'],
            'name'              => $data['name'],
            'remark'            => $request->param('remark',''),
        ];
        //保存信息
        $res = $groupinfo->save($save_data);
        if ($res){
            ouputJson('200',lang('SUCCESS'));
        }else{
            ouputJson('201',lang('SYSTEM_ERROR'));
        }
    }
    
    /*
     * 修改用户组
     */
    public function showGroup(Request $request,GroupInfoModel $groupinfo)
    {
        $id = $request->param('id','');
        if ($id){
            $data = $groupinfo->field('name,fee,show_fee,remark')
                ->where(['status'=>1,'gi_id'=>$id])
                ->find();
            if ($data){
                ouputJson('200','success',$data);
            }
        }
    }

    /*
     * 修改保存
     */
    public function editGroup(Request $request,GroupInfoModel $groupinfo)
    {
        $id = $request->param('id','');
        if ($id == ''){
            ouputJson('201',lang('ID_ERROR'));
        }else{
            $name = $request->param('name','');
            $fee = $request->param('fee','');
            $show_fee = $request->param('show_fee','');
            if ($name == '' && $fee == '' && $show_fee == ''){
                ouputJson('201',lang('PARAM_ERROR'));
            }
            if ($name != ''){
                $data['name'] = $name;
            }
            if ($fee != ''){
                $data['fee'] = $fee;
            }
            if ($show_fee){
                $data['show_fee'] = $show_fee;
            }
            $data['remark'] = $request->param('remark','');

            //保存信息
            $where = ['gi_id'=>$id];
            $res = $groupinfo->save($data,$where);
            if ($res){
                ouputJson('200',lang('SUCCESS'));
            }else{
                ouputJson('201',lang('SYSTEM_ERROR'));
            }
        }
    }

    /*
     * 删除用户组
     */
    public function delGroup(Request $request,GroupInfoModel $groupinfo)
    {
        $id = $request->param('id','');
        if ($id != ''){
            $where = ['gi_id'=>$id];
            $res = $groupinfo->where($where)->delete();
            if ($res){
                ouputJson('200',lang('SUCCESS'));
            }else{
                ouputJson('201',lang('SYSTEM_ERROR'));
            }
        }else{
            ouputJson('201',lang('ID_ERROR'));
        }
    }

    /*
     * 修改 用户与用户类型关联
     */
    public function user_groupChange(Request $request,UserGroupModel $groupModel)
    {
        $group_id = $request->param('group_id','');
        $user_id = $request->param('user_id','');
        if ($group_id != '' && $user_id != ''){

            $where = ['ui_id'=>$user_id];
            $save_data = ['gi_id'=>$group_id];

            $res = $groupModel->update($save_data,$where);

            if ($res){
                ouputJson('200',lang('SUCCESS'));
            }else{
                ouputJson('201',lang('SYSTEM_ERROR'));
            }
        }else{
            ouputJson('201',lang('ID_ERROR'));
        }

    }

    /*
     * 获取用户组列表
     */
    public function getGroupList(GroupInfoModel $groupinfo)
    {
        //类型列表
        $grouplist = $groupinfo
            ->field('gi_id as id,name')
            ->where('status','=','1')
            ->select();
        ouputJson('200',lang('SUCCESS'),$grouplist);
    }

}