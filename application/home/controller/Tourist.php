<?php
namespace app\home\controller;



use redis\Redis;
use think\App;
use think\Controller;
use think\Db;
use think\Lang;
use think\Request;

/*游客模式可以访问的接口*/
class Tourist extends Controller
{
    /*
     * 币种资料浏览
     */
    public function coinInfo(){
        $coin_info = Db::table('coin_info')
            ->field('name,short_name,logo,fee,release_time,release_total,circulate_total,crowd_funding,white_paper,website,blockchain,intro')
            ->where('status','=','1')
            ->select();

        //重组数组
        foreach($coin_info as $value){
            $data[$value['short_name']] = [
                'name'                  => $value['name'],
                'short_name'            => $value['short_name'],
                'logo'                  => $value['logo'],
                'intro'                 => $value['intro'],
                'release_time'          => $value['release_time'],
                'release_total'         => $value['release_total'],
                'circulate_total'       => $value['circulate_total'],
                'crowd_funding'         => $value['crowd_funding'],
                'white_paper'           => $value['white_paper'],
                'website'               => $value['website'],
                'blockchain'            => $value['blockchain'],
            ];
        }

        //返回数据
        if ($data != null){
            ouputJson('200',lang('SUCCESS'),$data);
        }

    }

    /*
     * 币种手续费
     */
    public function fee()
    {
        $coin_fee = Db::table('coin_info')
            ->field('short_name,fee')
            ->where('status','=','1')
            ->select();
        //重组数组
        foreach ($coin_fee as $item){
            $data[$item['short_name']]  = ['fee' => $item['fee']];
        }
        //返回数据
        if ($data != null){
            ouputJson('200',lang('SUCCESS'),$data);
        }

    }

    /*
     * 1.公告 2.帮助中心 3.常见问题  列表
     */
    public function content(Request $request)
    {
        $language = $request->param('language','');
        if ($language == ''){
            ouputJson('201',lang('PARAM_ERROR'));
        }

        //查询所有有效内容
        $field = [
            'cm_id',
            'cm_title',
            'cm_content',
            'cm_type',
            'cm_language',
            'cm_order',
            'cm_enclosure',
            'cm_enclosure_link',
            'if(createDate="","",FROM_UNIXTIME(createDate,"%Y-%m-%d")) as createDate'
        ];
        $contents = Db::table('content_management')
            ->field($field)
            ->where('cm_status','=','1')
            ->where('cm_type','in',[1,2,3])
            ->where('cm_language','=',$language)
            ->order('cm_order asc,createDate desc')
            ->select();

        //重组数组
        $data = [];//声明空数组
        $data['notice'] = [];
        $data['help'] = [];
        $data['question'] = [];
        foreach ($contents as $value){
            if ($value['cm_type'] == 1){//公告
                $data['notice'][] = [
                    'title'     => $value['cm_title'],
                    'content'   => $value['cm_content'],
                    'order'     => $value['cm_order'],
                    'enclosure' => $value['cm_enclosure'],
                    'enclosure_name' => $value['cm_enclosure_link'],
                    'id'        => $value['cm_id'],
                    'top'       => $value['cm_order']
                ];
            }elseif($value['cm_type'] == 2){//帮助中心
                $data['help'][] = [
                    'title'     => $value['cm_title'],
                    'content'   => $value['cm_content'],
                    'order'     => $value['cm_order'],
                    'enclosure' => $value['cm_enclosure'],
                    'enclosure_name' => $value['cm_enclosure_link'],
                    'id'        => $value['cm_id'],
                    'top'       => $value['cm_order']
                ];
            }elseif($value['cm_type'] == 3){//常见问题
                $data['question'][] = [
                    'title'     => $value['cm_title'],
                    'content'   => $value['cm_content'],
                    'order'     => $value['cm_order'],
                    'enclosure' => $value['cm_enclosure'],
                    'enclosure_name' => $value['cm_enclosure_link'],
                    'id'        => $value['cm_id'],
                    'top'       => $value['cm_order']
                ];
            }
        }

        //排序处理
        if (isset($data['cn']['notice'])){
            array_multisort(array_column($data['cn']['notice'],'order'),SORT_ASC,SORT_NUMERIC,$data['cn']['notice']);
        }
        if (isset($data['cn']['help'])){
            array_multisort(array_column($data['cn']['help'],'order'),SORT_ASC,SORT_NUMERIC,$data['cn']['help']);
        }
        if (isset($data['cn']['question'])){
            array_multisort(array_column($data['cn']['question'],'order'),SORT_ASC,SORT_NUMERIC,$data['cn']['question']);
        }
        if (isset($data['en']['notice'])){
            array_multisort(array_column($data['en']['notice'],'order'),SORT_ASC,SORT_NUMERIC,$data['en']['notice']);
        }
        if (isset($data['en']['help'])){
            array_multisort(array_column($data['en']['help'],'order'),SORT_ASC,SORT_NUMERIC,$data['en']['help']);
        }
        if (isset($data['en']['question'])){
            array_multisort(array_column($data['en']['question'],'order'),SORT_ASC,SORT_NUMERIC,$data['en']['question']);
        }

        //返回数据
        if ($data != null && isset($data)){
            ouputJson('200',lang('SUCCESS'),$data);
        }
    }
    
    /*
     * 首页第一条公告
     */
    public function firstNotice()
    {
        $field = [
            'cm_title as title',
            'cm_content as content',
            'if(createDate="","",FROM_UNIXTIME(createDate,"%Y-%m-%d")) as createDate',
            'cm_language as language'
        ];
        $where = [
            ['cm_type','=','1'],    //类型
            ['cm_status','=','1'],  //状态
            ['cm_order','=','1']    //排序
        ];
        $contents = Db::table('content_management')
            ->field($field)
            ->where($where)
            ->select();
        if (empty($contents)){
            ouputJson('201',lang('NO_DATA'));
        }else{
            //处理返回数据
            foreach ($contents as $v) {
                if ($v['language'] == '1'){
                    $data['cn'] = $v;
                }elseif ($v['language'] == '2'){
                    $data['us'] = $v;
                }
            }
            //返回数据
            ouputJson('200',lang('SUCCESS'),$data);
        }
    }

    /*
     * 推广活动
     */
    public function spread_activity()
    {
        $spread = Db::table('content_management')
            ->field('cm_title,cm_content,cm_type,cm_language,cm_order,cm_img')
            ->where('cm_type','=','4')
            ->where('cm_status','=','1')
            ->select();
        //数据处理
        $data = [];
        foreach ($spread as $value) {
            if ($value['cm_language'] == 1){
                $data['cn'][] = [
                    'title'     => $value['cm_title'],
                    'content'   => $value['cm_content'],
                    'img'       => $value['cm_img'],
                ];
            }else{
                $data['en'][] = [
                    'title'     => $value['cm_title'],
                    'content'   => $value['cm_content'],
                    'img'       => $value['cm_img'],
                ];
            }
        }
        if ($spread == null){
            ouputJson('201',lang('NO_DATA'));
        }else{
            ouputJson('200',lang('SUCCESS'),$data);
        }
    }

    /*
     * 游客模式下的排行榜
     */
    public function spreadRank()
    {
        //获利排行榜
        $amount = Db::table('promote_commissionrecord')->alias('pc')
            ->field('sum(pc.amount) as amount , pc.ui_id , ui.email')
            ->join('user_info ui','pc.ui_id = ui.ui_id')
            ->where('pc.status','=','1')
            ->group('pc.ui_id')
            ->order('amount','desc')
            ->limit(0,30)
            ->select();

        //已推荐人数排行榜
        $count = Db::table('promote_commissionrecord')->alias('pc')
            ->field('count(pc.amount) as count , pc.ui_id , ui.email')
            ->join('user_info ui','pc.ui_id = ui.ui_id')
            ->where('pc.status','=','1')
            ->where('pc.type','=','0')//0 代表注册
            ->group('pc.ui_id')
            ->order('amount','desc')
            ->limit(0,30)
            ->select();
        //返回数据
        $data = [
            'amount'        => $amount,
            'count'         => $count,
        ];
        ouputJson('200',lang('SUCCESS'),$data);
    }
    
    /*
     * 定时抓取汇率 (美元)   保存在redis中
     */
    public function rateCNY()
    {
        $cny = get_exchange_rate();
        $redis = Redis::instance();
        $res = $redis->set('USD_CNY',$cny);//保存redis
    }

    /*
     * 根据币种计算 人名币价格
     */
    public function exchangeCNY(Request $request)
    {
        $coin = $request->param('coin','');
        $num = $request->param('num','');
        if ($coin == "" || $num == ""){
            ouputJson('201',lang('PARAM_ERROR'));
        }else{
            if (strtoupper($coin) == 'USDT'){
                $coin_rate = 1;
            }else{
                //获取该币种 兑 USDT 价格
                $coin_rate = get_time_price($coin);
            }
            //获取人名币汇率
            $redis = Redis::instance();
            $cny = $redis->get('USD_CNY');
            if ($cny == null){
                $cny = get_exchange_rate();
            }

            //计算价格
            $res1 = bcmul($coin_rate,$num,16);
            $res = bcmul($res1,$cny,16);
            ouputJson('200',lang('SUCCESS'),$res);
        }

    }

    /*
     * 下载海报
     */
    public function downloadPic(Request $request){
        $file_url = $request->param('url','');

        if (!isset($file_url) || trim($file_url) == ''){
            ouputJson('201',lang('PARAM_ERROR'));
        }

        if (!file_exists($file_url)){
            ouputJson('404',lang('NO_FILE'));
        }

        //设置头信息
        header('Content-Disposition:attachment;filename=' . basename($file_url));
        header('Content-Length:' . filesize($file_url));
        //读取文件并写入到输出缓冲
        readfile($file_url);
    }

    /*
     * 广告--获取最新一条广告
     */
    public function getAd(Request $request)
    {
        $client_type = $request->param('client_type','');//设备 安卓or苹果
        if ($client_type == ''){
            ouputJson('401',lang('PARAM_ERROR'));
        }
        $field = [
            'id',
            'ad_name',
            'content_url',
            'ad_url,image_url',
            'image_url2',
            'show_time',
            'show_interval'
        ];
        $time = time();
        $where = [
            ['start_time','<=',$time],
            ['end_time','>=',$time],
            ['status','=',1],
        ];
        $res = db('sys_advertisement')
            ->field($field)
            ->where($where)
            ->order('id','DESC')
            ->find();
        if ($res){
            $data = [
                'ad_id'         => $res['id'],
                'user_id'       => $request->param('user_id',''),
                'client_type'   => $client_type,
                'record_type'   => 1,//获取类型
                'create_time'   => $time,
            ];
            //保存入库
            db('sys_advertisement')->insert($data);
            //拼接的域名
            $url = $res['ad_url']."/";
            //优化返回数组
            $result = [
                'id'                => $res['id'],
                'ad_name'           => $res['ad_name'],
                'content_url'       => $res['content_url'],
                'image_url'         => $url.$res['image_url'],
                'image_url2'        => $url.$res['image_url2'],
                'show_time'         => $res['show_time'],
                'show_interval'     => $res['show_interval'],
            ];
            ouputJson('200',lang('SUCCESS'),[$result]);
        }else{
            ouputJson('401',lang('NO_DATA'));
        }
    }

    /*
     * 广告--保存点击信息
     */
    public function clickAd(Request $request)
    {
        $ad_id = $request->param('ad_id','');//广告ID
        $client_type = $request->param('client_type','');//设备 安卓or苹果
        if ($ad_id != '' && $client_type != ''){
            $data = [
                'ad_id'         => $ad_id,
                'user_id'       => $request->param('user_id',''),
                'client_type'   => $client_type,
                'record_type'   => 2,//点击类型
                'create_time'   => time(),
            ];
            //保存点击信息
            db('sys_advertisement_record')->insert($data);
            ouputJson('200',lang('SUCCESS'));
        }else{
            ouputJson('401',lang('PARAM_ERROR'));
        }
    }

    /*
     * 获取活动列表
     */
    public function actList(Request $request)
    {
        $language = $request->header('language','');
        if ($language == ''){
            ouputJson('201',lang('PARAM_ERROR'));
        }

        $redis = Redis::instance();
        $json = $redis->get('act_list');

        //如果redis中没有数据
        if ($json == null){
            $field = [
                'cm_id as id',
                'cm_title as title',
                'cm_content as content',
                'cm_language as language',
                'cm_route as route',
            ];
            $where = [
                'cm_type'=>4,
                'cm_status'=>1,
            ];
            $res = Db::table('content_management')
                ->field($field)
                ->where($where)
                ->select();

            //优化存储数组
            foreach ($res as $value){
                //中文
                if ($value['language'] == 1){
                    $data['zh_cn'][] = [
                        'id' => $value['id'],
                        'title' => $value['title'],
                        'route' => $value['route'],
                        'content' => $value['content']
                    ];
                }
                //英文
                elseif ($value['language'] == 2){
                    $data['en_us'][] = [
                        'id' => $value['id'],
                        'title' => $value['title'],
                        'route' => $value['route'],
                        'content' => $value['content']
                    ];
                }
            }

            //设置redis数据
            try{
                $redis->set('act_list',json_encode($data));
            }catch(Exception $exception){
                ouputJson('201',lang('SYSTEM_ERROR_RS'));
            }

            //再次获取内容
            $json = $redis->get('act_list');
        }

        //获取内容转换数组
        $data = json_decode($json,true);

        //根据语言返回列表
        if ($language == 'zh-cn'){
            if (isset($data['zh_cn'])){
                ouputJson('200',lang('SUCCESS'),$data['zh_cn']);
            }else{
                ouputJson('201',lang('NO_DATA'));
            }

        }elseif ($language == 'en-us'){
            if (isset($data['en_us'])){
                ouputJson('200',lang('SUCCESS'),$data['en_us']);
            }else{
                ouputJson('201',lang('NO_DATA'));
            }
        }
    }


}