<?php
namespace app\admin\controller;


use app\admin\model\SysModuleModel;
use app\admin\validate\SysModule;
use think\Controller;
use think\Request;
use think\Validate;

class SystemModule extends Controller
{

    //返回数组整理
    private function generateTree($data)
    {
        $tree = array();
        foreach($data as $val){
            if(isset($data[$val['pid']])){
                $data[$val['pid']]['son'][] = &$data[$val['id']];
            }else{
                $tree[] = &$data[$val['id']];
            }
        }
        return $tree;
    }
    //获取全部模块信息
    private function getList(){
        $module = new SysModuleModel();
        $mod_list = $module
            ->where('SM_Status','=',1)
            ->order('SM_Order','desc')
            ->select();
        //处理数组
        if ($mod_list != null) {
            $stack = $html = array();
            foreach ($mod_list as $key => $val) {
                if ($val['SM_ParentID'] == 0)
                    pushStack($stack, $val, 0);
            }
            do {
                $par = popStack($stack); //将栈顶元素出栈
                for ($i = 0; $i < count($mod_list); $i++) {
                    if ($mod_list[$i]['SM_ParentID'] == $par['channel']['SM_ID']) {
                        pushStack($stack, $mod_list[$i], $par['dep'] + 1);
                    }
                }
                $html[] = [
                    'id'    => $par['channel']['SM_ID'],
                    'name'  => $par['channel']['SM_Name'],
                    'dep'   => $par['dep'],
                    'url'   => $par['channel']['SM_URL'],
                    'pid'   => $par['channel']['SM_ParentID'],
                ];
            } while (count($stack) > 0);
        }
        foreach($html as $k=>$v)
        {
            $tmpArr[$v['id']] = $html[$k];
        }
        $res =  $this->generateTree($tmpArr);
        return $res;
    }

    /*
     * 模块列表
     */
    public function moduleList()
    {
        $html = $this->getList();
        ouputJson('200','success',$html);
    }
    
    /*
     * 添加模块
     */
    public function addModule(Request $request,SysModuleModel $module)
    {

        $data = [
            'name'                  => $request->param('name',''),
            'url'                   => $request->param('url',''),
            'status'                => $request->param('status',''),
        ];
        $validate = new SysModule();
        if (!$validate->check($data)){
            ouputJson('201',$validate->getError());
        }
        //保存数组
        $save_data = [
            'SM_ParentID'           => $request->param('pid',null),
            'SM_Name'               => $data['name'],
            'SM_URL'                => $data['url'],
            'SM_Order'              => $request->param('order',null),
            'SM_Status'             => $data['status'],
        ];
        //保存模块
        $result = $module->save($save_data);
        if ($result){
            ouputJson('200','success');
        }else{
            ouputJson('201','system error!');
        }

    }

    /*
     * 模块编辑
     */
    public function showModule(Request $request,SysModuleModel $module)
    {
        $list = $this->getList();
        $id = $request->param('id','');
        if ($id){
            $info = $module->get(['SM_ID'=>$id]);
            //重组数组
            $self_data = [
                'id'    => $info['SM_ID'],
                'name'  => $info['SM_Name'],
                'url'   => $info['SM_URL'],
                'pid'   => $info['SM_ParentID']
            ];

            //返回数据
            $data = [
                'list'      => $list,
                'data'      => $self_data
            ];
            ouputJson('200','success',$data);
        }
    }

    /*
     * 保存编辑
     */
    public function saveModule(Request $request,SysModuleModel $module)
    {
        $id = $request->param('id','');
        if ($id){
            $data = [
                'name'                  => $request->param('name',''),
                'url'                   => $request->param('url',''),
                'status'                => $request->param('status',''),
            ];
            $validate = new SysModule();
            if (!$validate->check($data)){
                ouputJson('201',$validate->getError());
            }

            $save_data = [
                'SM_ParentID'           => $request->param('pid',null),
                'SM_Name'               => $data['name'],
                'SM_URL'                => $data['url'],
                'SM_Order'              => $request->param('order',null),
                'SM_Status'             => $data['status'],
            ];

            $where = ['SM_ID'=>$id];
            $result = $module->save($save_data,$where);
            if ($result){
                ouputJson('200','success');
            }else{
                ouputJson('201','system error!');
            }
        }
    }

    /*
     * 删除模块
     */
    public function delModule(Request $request,SysModuleModel $module)
    {
        $id = $request->param('id','');
        if ($id){
            $data = ['SM_Status'=>0];
            $where = ['SM_ID'=>$id];
            $result = $module->save($data,$where);
            if ($result){
                ouputJson('200','success');
            }else{
                ouputJson('201','system error!');
            }
        }

    }



}