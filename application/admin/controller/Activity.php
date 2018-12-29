<?php
namespace app\admin\controller;


use app\admin\model\ContentManagementModel;
use think\Controller;
use think\Db;
use think\Request;
use think\Validate;

class Activity extends Controller
{
    /*
     * 活动列表
     */
    public function actList(ContentManagementModel $model,Request $request)
    {
        $type = $request->param('type','');
        if ($type){
            $field = [
                'cm_id as id',
                'cm_title as title',
                'cm_content as content',
                'cm_img as img',
                'cm_link as link',
                'cm_enclosure as enclosure',
                'cm_enclosure_link as enclosure_link',
                'cm_language as language',
                'cm_status as status',
                'if(createDate="","",FROM_UNIXTIME(createDate,"%Y-%m-%d")) as time',
                'cm_order as sort'
            ];
            $where= [
                'cm_type'       => $type
            ];
            $list = $model->field($field)
                ->where($where)
                ->order('cm_order','desc')
                ->select();
            //优化返回数组
            $data = [];
            foreach ($list as $v) {
                if ($v['language'] == 1){//中文
                    $data['cn'][] = $v;
                }elseif ($v['language'] == 2){//英文
                    $data['en'][] = $v;
                }
            }
            ouputJson('200',lang('SUCCESS'),$data);
        }else{
            ouputJson('201',lang('PARAM_ERROR'));
        }
    }

    /*
     * 添加活动
     */
    public function actAdd(ContentManagementModel $model,Request $request)
    {
        $data = [
            'title'         => $request->param('title',''),
            'content'       => $request->param('content',''),
            'type'          => $request->param('type',''),
            'language'      => $request->param('language',''),
            'status'        => $request->param('status','')
        ];
        //验证
        $validate = new Validate('Activity');
        if (!$validate->check($data)){
            ouputJson('201',$validate->getError());
        }
        //保存的数组
        $save_data = [
            'cm_title'          => $data['title'],
            'cm_content'        => $data['content'],
            'cm_img'            => $request->param('img',''),
            'cm_link'           => $request->param('link',''),
            'cm_enclosure'      => $request->param('enclosure',''),
            'cm_enclosure_link' => $request->param('enclosure_link',''),
            'cm_type'           => $data['type'],
            'cm_language'       => $data['language'],
            'cm_status'         => $data['status'],
            'cm_order'          => $request->param('sort','')
        ];
        $result = $model->save($save_data);
        if ($result){
            ouputJson('200',lang('SUCCESS'));
        }else{
            ouputJson('201',lang('SYSTEM_ERROR'));
        }
    }
    
    /*
     * 修改活动
     */
    public function actShow(ContentManagementModel $model,Request $request)
    {
        $id = $request->param('id','');
        if ($id){
            $field = [
                'cm_id as id',
                'cm_title as title',
                'cm_content as content',
                'cm_img as img',
                'cm_link as link',
                'cm_enclosure as enclosure',
                'cm_enclosure_link as enclosure_link',
                'cm_language as language',
                'cm_status as status',
                'if(createDate="","",FROM_UNIXTIME(createDate,"%Y-%m-%d")) as time',
                'cm_order as sort'
            ];
            $where = ['cm_id'=>$id];
            $data = $model->field($field)->where($where)->find();
            ouputJson('200',lang('SUCCESS'),$data);
        }else{
            ouputJson('201',lang('ID_ERROR'));
        }
    }

    /*
     * 修改
     */
    public function actEdit(ContentManagementModel $model,Request $request)
    {
        $id = $request->param('id','');
        if ($id){
            $data = [
                'title'         => $request->param('title',''),
                'content'       => $request->param('content',''),
                'type'          => $request->param('type',''),
                'language'      => $request->param('language',''),
                'status'        => $request->param('status','')
            ];
            //验证
            $validate = new Validate('Activity');
            if (!$validate->check($data)){
                ouputJson('201',$validate->getError());
            }
            //保存的数组
            $save_data = [
                'cm_title'          => $data['title'],
                'cm_content'        => $data['content'],
                'cm_img'            => $request->param('img',''),
                'cm_link'           => $request->param('link',''),
                'cm_enclosure'      => $request->param('enclosure',''),
                'cm_enclosure_link' => $request->param('enclosure_link',''),
                'cm_type'           => $data['type'],
                'cm_language'       => $data['language'],
                'cm_status'         => $data['status'],
                'cm_order'          => $request->param('sort','')
            ];
            $where = ['cm_id'=>$id];
            $result = $model->save($save_data,$where);
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
     * 禁用 解禁
     */
    public function actDisable(ContentManagementModel $model,Request $request)
    {
        $id = $request->param('id','');
        $status = $request->param('status','');
        if ($id != '' && $status != ''){
            $where = ['cm_id'=>$id];
            if ($status == 1){
                $save = ['cm_status' => 0];
            }elseif ($status == 0){
                $save = ['cm_status' => 1];
            }
            $result = $model->save($save,$where);
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
     * 删除 
     */
    public function actDel(Request $request)
    {
        $id = $request->param('id','');
        if ($id){
            $result = db('content_management')->where('cm_id','=',$id)->delete();
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
     * 推广活动
     */
    public function spread(ContentManagementModel $model,Request $request)
    {
        $language = $request->header('language','');
        if ($language != ''){
            if ($language == 'zh-cn'){
                $language = 1;
            }elseif ($language == 'en-us'){
                $language = 2;
            }
            $field = [
                'cm_id as id',
                'cm_title as title',
                'cm_content as content',
                'cm_img as img',
                'cm_link as link',
                'cm_enclosure as enclosure',
                'cm_enclosure_link as enclosure_link',
                'cm_language as language',
                //'cm_status as status',
                'if(createDate="","",FROM_UNIXTIME(createDate,"%Y-%m-%d")) as time',
            ];
            $where = [
                'cm_type'=>4,
                'cm_language'=>$language,
                'cm_status'=>1,
            ];
            $res = $model->field($field)->where($where)->select();
            if ($res){
                ouputJson('200',lang('SUCCESS'),$res);
            }else{
                ouputJson('201',lang('NO_DATA'));
            }
        }else{
            ouputJson('201',lang('PARAM_ERROR'));
        }
    }

    /*
     * 活动列表
     */
    public function activityList(ContentManagementModel $model,Request $request)
    {

        $field = [
            'cm_id as id',
            'cm_title as title',
            'cm_status as status',
            'if(cm_startTime="","",FROM_UNIXTIME(cm_startTime,"%Y-%m-%d %H:%i:%s")) as start',
            'if(cm_endTime="","",FROM_UNIXTIME(cm_endTime,"%Y-%m-%d %H:%i:%s")) as end'
        ];
        $where = [
            'cm_type'=>4,
        ];
        $res = $model->field($field)->where($where)->select();
        //优化返回数组
        foreach ($res as $value){
            if ($value['status'] == 1){
                $time = $value['start'];
            }else{
                $time = $value['end'];
            }
            $data[] = [
                'id'    => $value['id'],
                'title' => $value['title'],
                'status'=> $value['status'],
                'time'  => $time,
            ];
        }
        if ($res){
            ouputJson('200',lang('SUCCESS'),$data);
        }else{
            ouputJson('201',lang('NO_DATA'));
        }
    }

    /*
     * 编辑活动
     */
    public function activityEdit(ContentManagementModel $model,Request $request)
    {
        $id = $request->param('id','');
        $title = $request->param('title','');
        $start = $request->param('start','');
        $end = $request->param('end','');
        $status = $request->param('status','');
        if ($title == '' || $status == '' || $id == ''){
            ouputJson('201',lang('PARAM_ERROR'));
        }
        $ids = $model->where('cm_type','=',4)->column('cm_id');
        if (!in_array($id,$ids)){
            ouputJson('201',lang('ID_ERROR'));
        }
        $where = ['cm_id'=>$id];
        $data = [
            'cm_title'  => $title,
            'cm_status' => $status,
        ];
        if ($status == '1'){
            if ($start == ''){
                ouputJson('201',lang('PARAM_ERROR'));
            }
            $data['cm_startTime'] = strtotime($start);
        }elseif ($status == '0'){
            if ($end == ''){
                ouputJson('201',lang('PARAM_ERROR'));
            }
            $data['cm_endTime'] = strtotime($end);
        }

        $res = $model->save($data,$where);
        if ($res){
            ouputJson('200',lang('SUCCESS'));
        }else{
            ouputJson('201',lang('SYSTEM_ERROR'));
        }

    }
    
}