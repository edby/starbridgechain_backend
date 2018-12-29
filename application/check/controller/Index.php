<?php

namespace app\check\controller;
use think\Controller;
use think\Config;

class Index extends Controller
{
	function __construct(){
        parent::__construct();
    }

    public function sign(){
        return $this->fetch();
    }
    public function signfrom(){
        $postData = $this->request->post();

        // dump($postData);
        // die;
        if($postData['type']==1){
            $signArr = [
                'app_id'=> config('auth.app_id'),
                'token'=> $postData['token'],
                'timestamp'=> $postData['timestamp'],
                'noncestr'=>$postData['noncestr']
            ];

            $serverSign = generateSign($signArr);
            ksort($signArr);
            $serverString = ToUrlParams($signArr);
        }else{

            $param = $postData['param'];
            if(!empty($param)){
                foreach ($param as $k => $v) {
                    if($v['key']!="" && $v['val']!=""){
                        $postData[$v['key']] = $v['val'];
                    }
                }
                unset($postData['param']);
            }
            unset($postData['type']);
            $serverSign = generateSign($postData,1);

            ksort($postData);
            $serverString = ToUrlParamsForPcWeb($postData);
            

        }
        echo json_encode(['code'=>0,'data'=>['sign'=>$serverSign,'str'=>$serverString]]);
    }

}
