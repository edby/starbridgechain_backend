<?php

namespace app\app\controller;


use think\Controller;
use think\Db;
use think\Request;

class App extends Controller
{
    //APP轮播图
    public function lists()
    {
        //查询可用轮播图
        $field = [
            'ca_id as id',
            'name',
            'url',
            'path',
        ];
        $data = Db::table('sys_carousel')
            ->field($field)
            ->where('status','=',1)
            ->select();
        //优化数组
        $out_data = [];
        foreach ($data as $v) {
            $arr = [];
            $arr['name'] = urlencode($v['name']);
            if ($v['url'] != null){
                $arr['url'] = urlencode($v['url'].$v['path']);
            }

            $out_data[] = $arr;
        }

        //返回数据
        ouputJson('200',lang('SUCCESS'),$out_data);

    }

    //置顶公告
    public function firstNotice(Request $request)
    {
        //语言类型
        $language = $request->param('language','1');

        $field = [
            'cm_title as title',
            'cm_content as content',
        ];
        $where = [
            ['cm_type','=','1'],    //类型
            ['cm_status','=','1'],  //状态
            ['cm_order','=','1'],   //排序
            ['cm_language','=',$language]
        ];
        $contents = Db::table('content_management')
            ->field($field)
            ->where($where)
            ->find();

        if (empty($contents)){

            ouputJson('201',lang('NO_DATA'));

        }else{

            //返回数据

            ouputJson('200',lang('SUCCESS'),$contents);
        }
    }
}