<?php

namespace app\admin\controller;

use think\Controller;
use think\Db;
use think\Request;
use think\Validate;

/** 内容管理
 * Class ContentManagement
 * @package app\admin\controller
 */
class ContentManagement extends Admin
{

    /** 列表
     * @param Request $request
     * @throws \think\exception\DbException
     */
    public function lists(Request $request)
    {
        $page = $request->param('page','1');//当前页数 默认第一页
        $limit = $request->param('limit','10');//每页显示条数 默认10条
        $language = $request->param('language','1');//语言类型 默认中文

        //查询数据
        $field = [
            'cm_id as id',
            'cm_order as top',
            'cm_title as title',
            'cm_content as content',
            'cm_enclosure as enclosure',
            'cm_enclosure_link as enclosure_link',
            'if(createDate="","",FROM_UNIXTIME(createDate,"%Y-%m-%d")) as createDate',
        ];
            //公告
        $list1 = Db::table('content_management')
            ->field($field)
            ->where(['cm_language'=>$language,'cm_type'=>1])
            ->order('cm_order asc,cm_id desc')
            ->paginate($limit,['page'=>$page]);
            //帮助中心
        $list2 = Db::table('content_management')
            ->field($field)
            ->where(['cm_language'=>$language,'cm_type'=>2])
            ->order('cm_order asc,cm_id desc')
            ->paginate($limit,['page'=>$page]);
            //常见问题
        $list3 = Db::table('content_management')
            ->field($field)
            ->where(['cm_language'=>$language,'cm_type'=>3])
            ->order('cm_order asc,cm_id desc')
            ->paginate($limit,['page'=>$page]);
        $data = [
            'notice'=>$list1,
            'help'=>$list2,
            'question'=>$list3
        ];

        //返回数据
        ouputJson('200',lang('SUCCESS'),$data);
    }

    

    /** 新增
     * @param Request $request
     */
    public function add(Request $request)
    {
        $rule = [
            'cm_title' => 'require',
            'cm_content' => 'require',
            'cm_type' => 'require',
            'cm_language' => 'require',
            'cm_order' => 'require',
        ];
        $msg = [
            'cm_title.require' => '标题不能为空!',
            'cm_content.require' => '内容不能为空!',
            'cm_type.require' => '没有选择分类!',
            'cm_language.require' => '请选择中英文版本!',
            'cm_order.require' => '排序不能为空!',
        ];
        $data = [
            'cm_title'=>$request->param('title'),
            'cm_content'=>$request->param('content'),
            'cm_type'=>$request->param('category'),
            'cm_language'=>$request->param('is_english'),
            'cm_order'=>$request->param('top'),
        ];
        //验证字段
        $validate   = Validate::make($rule,$msg);
        $result = $validate->check($data);
        if (!$result){
            ouputJson('201',$validate->getError());
        }

        //保存字段
        $data['createDate'] = time();
        $data['cm_enclosure'] = $request->param('enclosure_name','');
        $data['cm_enclosure_link'] = $request->param('enclosure','');

        //保存
        $res = Db::table('content_management')->insert($data);
        
        if ($res){
            ouputJson('200',lang('SUCCESS'));
        }else{
            ouputJson('201',lang('SYSTEM_ERROR'));
        }
    }

    

    /** 修改
     * @param Request $request
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function edit(Request $request)
    {
        $id = $request->param('id','');     //ID
        $enclosure = $request->param('enclosure','');   //附件名称
        $enclosure_link = $request->param('enclosure_link','');     //附件链接

        if ($id == ''){
            ouputJson('201',lang('ID_ERROR'));
        }

        $rule = [
            'cm_title' => 'require',
            'cm_content' => 'require',
            'cm_order' => 'require',
        ];
        $msg = [
            'cm_title.require' => '标题不能为空!',
            'cm_content.require' => '内容不能为空!',
            'cm_order.require' => '排序不能为空!',
        ];
        $data = [
            'cm_title'=>$request->param('title'),
            'cm_content'=>$request->param('content'),
            'cm_order'=>$request->param('top'),
        ];

        //验证字段
        $validate   = Validate::make($rule,$msg);
        $result = $validate->check($data);
        if (!$result){
            ouputJson('201',$validate->getError());
        }

        //是否修改附件
        if ($enclosure != ''){
            $data['cm_enclosure'] = $enclosure;
        }
        if ($enclosure_link != ''){
            $data['cm_enclosure_link'] = $enclosure_link;
        }

        //保存
        $where = ['cm_id'=>$id];
        $res = Db::table('content_management')->where($where)->update($data);

        if ($res){
            ouputJson('200',lang('SUCCESS'));
        }else{
            ouputJson('201',lang('SYSTEM_ERROR'));
        }
    }


    
    /** 删除
     * @param Request $request
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function del(Request $request)
    {
        $id = $request->param('id','');
        if ($id == ''){
            ouputJson('201',lang('ID_ERROR'));
        }

        $where = ['cm_id'=>$id];
        $res = Db::table('content_management')->where($where)->delete();

        if ($res){
            ouputJson('200',lang('SUCCESS'));
        }else{
            ouputJson('201',lang('SYSTEM_ERROR'));
        }
    }
    

    
    /** 上传附件
     * @param Request $request
     */
    public function uploadFiles(Request $request)
    {
        $file = $_FILES['file'];

        //判断是否是通过HTTP POST上传的
        if(!is_uploaded_file($file['tmp_name'])){
            ouputJson('201',lang());
        }
        $upload_path = "upload/".$request->controller()."files/"; //上传文件的存放路径

        if(!file_exists($upload_path)){
            //检查是否有该文件夹，如果没有就创建，并给予最高权限
            mkdir($upload_path, 0700,true);
        }

        //开始移动文件到相应的文件夹
        if(move_uploaded_file($file['tmp_name'],$upload_path.$file['name'])){
            header('Content-type:text/html;charset=utf-8');
            ouputJson('200',lang('SUCCESS'),['url'=>$upload_path.$file['name']]);
        }else{
            ouputJson('201',lang('SYSTEM_ERROR'));

        }
    }


    /** 上传图片
     * @param Request $request
     */
    public function uploadImg(Request $request)
    {
        $imagedata = $request->param('image','');
        if ($imagedata == ''){
            ouputJson('201',lang('PARAM_ERROR'));
        }
        $result = $this->saveBase64Img($imagedata,$request->controller());

        if (!$result){
            ouputJson('201',lang('SYSTEM_ERROR'));
        }else{
            ouputJson('200',lang('SUCCESS'),['url'=>$result]);
        }
    }
}