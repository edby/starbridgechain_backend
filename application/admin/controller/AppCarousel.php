<?php

namespace app\admin\controller;


use app\admin\model\CarouselModel;
use think\Request;

class AppCarousel extends Admin
{
    /** 列表
     * @param CarouselModel $carouselModel
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function carouselList(CarouselModel $carouselModel)
    {
        $field = [
            'ca_id as id',
            'name',
            'url',
            'status'
        ];
        $data = $carouselModel->field($field)->select();

        ouputJson('200',lang('SUCCESS'),$data);
    }

    /** 上传
     * @param Request $request
     * @param CarouselModel $carouselModel
     */
    public function uploadCarousel(Request $request,CarouselModel $carouselModel)
    {
        $id = $request->param('id','');
        $img = $request->param('img','');

        if ($id == '' || $img == ''){
            ouputJson('201',lang('PARAM_ERROR'));
        }else{
            $img_src = $this->saveBase64Img($img,"Carousel");
            //保存入库
            $where = ['ca_id'=>$id];
            $save = ['url'=>$img_src];
            $res = $carouselModel->save($save,$where);
            if ($res){
                $data = ['url'=>$img_src];
                ouputJson('200',lang('SUCCESS'),$data);
            }else{
                ouputJson('201',lang('SYSTEM_ERROR'));
            }
        }
    }

    /** 修改状态
     * @param Request $request
     * @param CarouselModel $carouselModel
     */
    public function changeStatus(Request $request,CarouselModel $carouselModel)
    {
        $id = $request->param('id','');
        $status = $request->param('status','');
        if ($id == '' || $status == ''){
            ouputJson('201',lang('PARAM_ERROR'));
        }
        $where = ['ca_id'=>$id];
        $save = ['status'=>$status];
        $res = $carouselModel->save($save,$where);
        if ($res){
            ouputJson('200',lang('SUCCESS'));
        }else{
            ouputJson('201',lang('SYSTEM_ERROR'));
        }
    }

}