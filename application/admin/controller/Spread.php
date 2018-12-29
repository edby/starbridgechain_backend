<?php
namespace app\admin\controller;


use think\Controller;
use think\Db;
use think\Exception;
use think\Request;

class Spread extends Controller
{
    /*
     * 设置
     */
    public function setSpread(Request $request)
    {
        $switch = $request->param('switch','');
        $one = $request->param('one','');
        $two = $request->param('two','');
        $config = [
            'spread_switch' => $switch,
            'spread_first'  => $one,
            'spread_second' => $two
        ];
        if ($switch == '' && $one == '' && $two == ''){
            ouputJson('201',lang('PARAM_ERROR'));
        }
        Db::startTrans();
        try{
        foreach($config as $key=>$value){
            if (!is_numeric($value)){
                ouputJson('201',lang('NUMBER'));
            }
            if ($value != ''){
                db('system_configs')
                    ->where('key','=',$key)
                    ->update(['val'=>$value]);
            }
        }
        }catch (Exception $exception){
            Db::rollback();
            ouputJson('201',lang('SYSTEM_ERROR'));
        }
        Db::commit();
        ouputJson('200',lang('SUCCESS'));

    }

    /*
     * 获取推广获利配置
     */
    public function getSpread()
    {
        $config = getSysconfig(['spread_switch','spread_first','spread_second']);

        if (!empty($config)){
            $data = [
                'switch'=>$config['spread_switch'],
                'one'=>$config['spread_first'],
                'two'=>$config['spread_second']
            ];
            ouputJson('200',lang('SUCCESS'),$data);
        }else{
            ouputJson('201',lang('NO_DATA'));
        }
    }

}