<?php

namespace app\admin\controller;


use think\Controller;
use think\Request;
use think\Validate;
use think\Db;

class Advertisement extends Controller
{

    /** 新增
     * @param Request $request
     */
    public function add(Request $request)
    {
        $data = [
            'ad_name' => $request->param('Name'),
            'content_url' => $request->param('ContentURL'),
            'has_url' => $request->param('HasURL'),
            'ad_url' => $request->param('Ad_url'),
            'status' => $request->param('AliveFlag'),
            'start_time' => strtotime($request->param('StartTime')),
            'end_time' => strtotime($request->param('EndTime')),
            'base_type' => $request->param('BaseType', '1'),
            'type' => $request->param('Type'),
            'show_time' => $request->param('ShowElapse'),
            'show_interval' => $request->param('ShowInterval'),
            'creater' => $request->param('Account'),
            'image_url' => $request->param('ImageURL'),
            'image_url2' => $request->param('ImageURL2'),
        ];
        $validate = new \app\admin\validate\Advertisement();
        if (!$validate->check($data)) {
            ouputJson('201', $validate->getError());
        }
        $res = Db::table('sys_advertisement')->insert($data);
        if ($res) {
            ouputJson('200', lang('SUCCESS'));
        } else {
            ouputJson('201', lang('SYSTEM_ERROR'));
        }
    }



    /** 列表
     * @param Request $request
     * @throws \think\exception\DbException
     */
    public function lists(Request $request)
    {
        $page = $request->param('page',1);
        $limit = $request->param('limit',10);

        $lists = Db::table('sys_advertisement')
            ->order('id', 'desc')
            ->paginate($limit, false, ['page' => $page]);

        //组装返回的数据
        foreach ($lists->items() as $item) {
            //获取广告 安卓数据
            $adnroid1 = Db::table('sys_advertisement_record')
                ->where(['ad_id' => $item['id'], 'client_type' => 1, 'record_type' => 1])
                ->count();//获取
            $adnroid1 = (int)$adnroid1;
            $adnroid2 = Db::table('sys_advertisement_record')
                ->where(['ad_id' => $item['id'], 'client_type' => 1, 'record_type' => 2])
                ->count();//点击
            $adnroid2 = (int)$adnroid2;
            if ($adnroid1 < $adnroid2 || $adnroid1 == 0) {
                $adnroid = 0;
            } else {
                $adnroid = (round($adnroid2 / $adnroid1, 2) * 100) . '%';
            }

            //获取广告ios 数据
            $ios1 = Db::table('sys_advertisement_record')
                ->where(['ad_id' => $item['id'], 'client_type' => 2, 'record_type' => 1])
                ->count();//获取
            $ios1 = (int)$ios1;
            $ios2 = Db::table('sys_advertisement_record')
                ->where(['ad_id' => $item['id'], 'client_type' => 2, 'record_type' => 2])
                ->count();//点击
            $ios2 = (int)$ios2;
            if ($ios1 < $ios2 || $ios1 == 0) {
                $ios = 0;
            } else {
                $ios = (round($ios2 / $ios1, 2) * 100) . '%';
            }

            //该广告所有点击数据
            $all1 = $ios1 + $adnroid1;//获取
            $all2 = $ios2 + $adnroid2;//点击
            if ($all1 < $all2 || $all1 == 0) {
                $all = 0;
            } else {
                $all = (round($all2 / $all1, 2) * 100) . '%';
            }

            //上次修改时间
            if (!$item['last_edit_time']) {
                $last_edit_time = '尚未修改';
            } else {
                $last_edit_time = date('Y-m-d H:i:s', $item['last_edit_time']);
            }

            $new_lists[] = [
                'ID' => $item['id'],
                'Name' => $item['ad_name'],
                'Type' => $item['type'],
                'Ad_url' => $item['ad_url'],
                'ImageURL' => $item['image_url'],
                'ImageURL2' => $item['image_url2'],
                'StartTime' => date('Y-m-d H:i:s', $item['start_time']),
                'EndTime' => date('Y-m-d H:i:s', $item['end_time']),
                'AliveFlag' => $item['status'],
                'Rate_Android' => $adnroid,
                'Rate_IOS' => $ios,
                'Rate_All' => $all,
                'ContentURL' => $item['content_url'],
                'HasURL' => $item['has_url'],
                'BaseType' => $item['base_type'],
                'ShowElapse' => $item['show_time'],
                'ShowInterval' => $item['show_interval'],
                'CreateTime' => date('Y-m-d H:i:s', $item['create_time']),
                'LastEditTIme' => $last_edit_time,
                'Account' => $item['creater'],
            ];
        }
        if (!isset($new_lists)) {
            ouputJson('201',lang('NO_DATA'));
        } else {
            $data['total'] = $lists->total();//总数
            $data['page'] = $page;//当前页数
            $data['data'] = $new_lists;//列表信息
            ouputJson('200',lang('SUCCESS'),$data);
        }

    }



    /** 编辑
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function edit(Request $request)
    {
        $id = $request->param('ID','');
        $rule = [
            'ad_name'           => 'require',
            'content_url'       => 'require',
            'has_url'           => 'require',
            'status'            => 'require',
            'start_time'        => 'require',
            'end_time'          => 'require',
            'ad_url'            => 'require',
            'type'              => 'require',
            'show_time'         => 'require',
            'show_interval'     => 'require',
            'image_url'         => 'require',
            'image_url2'        => 'require',
        ];
        $msg = [
            'ad_name.require'           => '广告名称不能为空!',
            'content_url.require'       => '广告内容不能为空!',
            'has_url.require'           => '内容URL是否存在?',
            'status.require'            => '广告是否启用?',
            'start_time.require'        => '开始时间不能为空!',
            'end_time.require'          => '结束时间不能为空!',
            'ad_url.require'            => '请填写广告域名!!',
            'type.require'              => '请选择广告类型!',
            'show_time.require'         => '请填写显示持续时间!',
            'show_interval.require'     => '请填写后台间隔启用时间(分)!',
            'image_url.require'         => '缺少图片路径1!',
            'image_url2.require'        => '缺少图片路径2!'
        ];
        $data = [
            'ad_name'       =>$request->param('Name'),
            'content_url'   =>$request->param('ContentURL'),
            'has_url'       =>$request->param('HasURL'),
            'ad_url'        =>$request->param('Ad_url'),
            'status'        =>$request->param('AliveFlag'),
            'start_time'    =>strtotime($request->param('StartTime')),
            'end_time'      =>strtotime($request->param('EndTime')),
            'base_type'     =>$request->param('BaseType','1'),
            'type'          =>$request->param('Type'),
            'show_time'     =>$request->param('ShowElapse'),
            'show_interval' =>$request->param('ShowInterval'),
            'image_url'     =>$request->param('ImageURL'),
            'image_url2'    =>$request->param('ImageURL2'),
        ];

        $validate = new Validate($rule,$msg);
        if (!$validate->check($data)){
            ouputJson('201',$validate->getError());
        }

        $data['last_edit_time'] = time();

        $res = Db::table('sys_advertisement')
            ->where('id','=',$id)
            ->update($data);

        if ($res){
            ouputJson('200',lang('SUCCESS'));
        }else{
            ouputJson('201',lang('SYSTEM_ERROR'));
        }
    }



    /** 批量删除
     * @param Request $request
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function del(Request $request)
    {
        $ids = json_decode($request->param('id'));
        if (!empty($ids)){
            $res = Db::table('sys_advertisement')
                ->where('id','in',$ids)
                ->delete();
            if ($res){
                ouputJson('200',lang('SUCCESS'));
            }else{
                ouputJson('201',lang('SYSTEM_ERROR'));
            }
        }else{
            ouputJson('201',lang('ID_ERROR'));
        }
    }



    /** 上传广告图片
     * @param Request $request
     */
    public function uploadImg(Request $request)
    {
        //input file 文件
        $img = $request->file('image');
        //获取控制器名称
        $controller = $request->controller();
        $info = $img->validate(['size'=>1024*1024*20,'ext'=>'jpg,png,gif,jpeg'])
            ->move('upload/'.$controller);

        if($info){
            // 成功上传后 获取上传信息
            $path = '/upload/'.$request->controller().'/'.$info->getSaveName();
            ouputJson('200',lang('SUCCESS'),['url'=>$path]);
        }else{
            ouputJson('201',$info->getError());
        }
    }



}