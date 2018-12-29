<?php
namespace app\personal\controller;


use app\common\controller\AuthBase;
use app\home\model\UserInfoModel;
use app\personal\services\QrcodeServer;
use app\common\service\Email;
use redis\Redis;
use think\helper\Hash;
use think\helper\Str;
use think\Request;
use think\Validate;
use think\Db;
use Rsa\RSA;

class Personal extends AuthBase
{

    /*
     * 设置交易密码 满足条件8-20非纯数字
     */
    public function setTradePwd(Request $request,UserInfoModel $userInfoModel)
    {
        //已经设置过交易密码
        if ($this->userinfo['trade_pwd'] != null || $this->userinfo['trade_pwd'] != ""){
            ouputJson('201',lang('PWD_HAS_SET'));
        }else{
            //解密
            $rsa = new RSA();
            $upwd_rsaiv = $this->request->param('upwd_rsaiv');//密文
            if($upwd_rsaiv==""){
                ouputJson(201,lang('FROM_ERROR'));
            }
            $param = json_decode($rsa->se_decrypt_priv($upwd_rsaiv),true);

            $pwd = $param['pwd'];
            $repwd = $param['repwd'];
            $type = $param['type'];

            //类型没有选择
            if ($type == ''){
                ouputJson('201',lang('NO_TYPE'));
            }

            $save_data = [];//保存的数组
            if ($type == 2){
                $save_data['trade_type'] = $type;
                $this->userinfo['trade_type'] = $type;

                if ($this->userinfo['trade_pwd_notice'] == '0'){
                    $save_data['trade_pwd_notice'] = 1;
                    $this->userinfo['trade_pwd_notice'] = 1;
                }

            }else{

                //两次中有一次没填写
                if ($pwd == '' ||  $repwd == ''){
                    ouputJson('201',lang('PWD_LESS_INPUT_ONCE'));
                }
                //两次交易密码不一致
                if ($pwd != $repwd){
                    ouputJson('201',lang('PWD_TWICE_DIF'));
                }

                //正则表达式验证格式
                if (!preg_match('/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{8,20}$/',$pwd)){
                    ouputJson('201',lang('PWD_FORMAL_ERROR'));
                }

                //保存交易密码 及 密码盐值
                $salt = spread_code('6');
                $save_pwd = Hash::make($pwd,'md5',['salt'=>$salt]);

                $save_data['trade_pwd'] = $save_pwd;
                $save_data['trade_salt'] = $salt;
                $save_data['trade_type'] = $type;
                $save_data['trade_pwd_notice'] = 1;

                $this->userinfo['trade_type'] = $type;
                $this->userinfo['trade_pwd'] = $save_pwd;
                $this->userinfo['trade_salt'] = $salt;

            }

            $where = ['ui_id'=>$this->userinfo['ui_id']];
            $res = $userInfoModel->save($save_data,$where);

            if ($res){//设置成功

                $this->redis->setex('userinfo_'.$this->uid,300,json_encode($this->userinfo));
                ouputJson('200',lang('PWD_SET_SUC'));

            }else{//设置失败
                ouputJson('200',lang('PWD_SET_F'));

            }
        }
    }
    
    /*
     * 是否设置交易密码
     */
    public function isSetTradeType()
    {
        if ($this->userinfo['trade_pwd'] == null){
            $type = 1;
        }else{
            $type = 2;
        }
        ouputJson('200',lang('SUCCESS'),$type);
    }

    /*
     * 个人交易密码类型
     */
    public function selfTradeType()
    {
        $type = $this->userinfo['trade_type'];

        if ($this->userinfo['trade_pwd'] == null){
            $has_pwd = 1;
        }else{
            $has_pwd = 2;
        }

        $data = [
            'type'=>$type,
            'has_pwd'=>$has_pwd
        ];

        ouputJson('200',lang('SUCCESS'),$data);
    }
    
    /*
     * 修改/重置 交易密码
     */
    public function changeTradePwd(Request $request)
    {
        //解密
        $rsa = new RSA();
        $upwd_rsaiv = $this->request->param('upwd_rsaiv');//密文
        if($upwd_rsaiv==""){
            ouputJson(201,lang('FROM_ERROR'));
        }
        $param = json_decode($rsa->se_decrypt_priv($upwd_rsaiv),true);

        //验证邮箱
        $emailid = $param['emailid'];
        $code = $param['code'];
        $pwd = $param['pwd'];
        $repwd = $param['repwd'];

        if($emailid == "" || $code == ""){
            ouputJson(201,lang('PARAM_ERROR'));
        }

        $emcode = $this->redis->get('email_'.$emailid);
        if($emcode == ""){
            ouputJson(201,lang('PLASE_SEND_CODE'));
        }

        if($code != $emcode){
            ouputJson(201,lang('CODE_ERROR'));
        }

        //修改密码
        if ($this->userinfo['trade_pwd'] == ''){//未设置交易密码
            ouputJson('201',lang('PWD_NOT_SET'));
        }else{

            if ($pwd == '' || $repwd == ''){//参数不足
                ouputJson('201',lang('PARAM_ERROR'));
            }

            if ($pwd != $repwd){//两次不一致
                ouputJson('201',lang('PWD_TWICE_DIF'));
            }

            //正则表达式验证格式
            if (!preg_match('/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{8,20}$/',$pwd)){
                ouputJson('201',lang('PWD_FORMAL_ERROR'));
            }

            //修改密码
            $save_pwd = Hash::make($pwd,'md5',['salt'=>$this->userinfo['trade_salt']]);
            if ($save_pwd == $this->userinfo['trade_pwd']){
                ouputJson('201',lang('THE_SAME_PWD'));
            }
            $res = Db::table('user_info')
                ->where('ui_id','=',$this->uid)
                ->update(['trade_pwd'=>$save_pwd,]);

            if ($res){//修改成功
                $this->userinfo['trade_pwd'] = $save_pwd;
                $this->redis->setex('userinfo_'.$this->uid,300,json_encode($this->userinfo));
                //修改成功 删除code
                $this->redis->del('email_'.$emailid);
                ouputJson('200',lang('PWD_SET_SUC'));
            }else{
                ouputJson('201',lang('PWD_SET_F'));
            }
        }
    }
    
    /*
     * 修改 交易密码方式
     */
    public function setTradeOption(Request $request)
    {
        //解密
        $rsa = new RSA();
        $upwd_rsaiv = $this->request->param('upwd_rsaiv');//密文
        if($upwd_rsaiv==""){
            ouputJson(201,lang('FROM_ERROR'));
        }
        $param = json_decode($rsa->se_decrypt_priv($upwd_rsaiv),true);

        $pwd = $param['pwd'];
        $type = $param['type'];

        if ($pwd == '' || $type == ''){
            ouputJson('201',lang('PARAM_ERROR'));
        }else{
            //是否设置了交易密码
            if ($this->userinfo['trade_pwd'] == ''){
                ouputJson('201',lang('PWD_NOT_SET'));
            }
            if ($type == $this->userinfo['trade_type']){
                ouputJson('201',lang('REPEAT_UPDATE'));
            }
            //验证交易密码正确性
            $trade_pwd = Db::table('user_info')
                ->where('ui_id','=',$this->uid)
                ->value('trade_pwd');
            if (!Hash::check($pwd,$trade_pwd,'md5',['salt'=>$this->userinfo['trade_salt']])){
                ouputJson('201',lang('PWD_IS_ERROR'));
            }else{
                //验证成功 修改选项
                $res = Db::table('user_info')
                    ->where('ui_id','=',$this->uid)
                    ->update(['trade_type'=>$type]);
                if ($res){
                    $this->userinfo['trade_type'] = $type;
                    $this->redis->setex('userinfo_'.$this->uid,300,json_encode($this->userinfo));
                    ouputJson('200',lang('SUCCESS'));
                }else{
                    ouputJson('201',lang('SYSTEM_ERROR'));
                }
            }

        }
    }

    /*
     * 修改 重置 交易密码发送邮件
     */
    public function sendEmail(Request $request)
    {
        $email = $request->param('email');
        if ($email != $this->userinfo['email']){
            ouputJson('201',lang('EMAIL_ERROR'));
        }
        if($email==""){
            ouputJson(201,lang('PLASE_INPUT_EMAIL'));
        }
        $emailConfig = getSysconfig(['verification_email_title','verification_email_content']);
        $res = Email::sendEmail($email,$emailConfig['verification_email_title'],$emailConfig['verification_email_content']);
        if($res['error']!=200){
            ouputJson(202,$res['msg']);
        }else{
            ouputJson(200,lang('SEND_SUCCESS'),['emailid'=>$res['emailid']]);
        }
    }

    /*
     * 验证邮箱
     */
    public function validateEmali(Request $request)
    {
        //解密
        $rsa = new RSA();
        $upwd_rsaiv = $this->request->param('upwd_rsaiv');//密文
        if($upwd_rsaiv==""){
            ouputJson(201,lang('FROM_ERROR'));
        }
        $param = json_decode($rsa->se_decrypt_priv($upwd_rsaiv),true);

        $emailid = $param['emailid'];
        $code = $param['code'];

        if($emailid == "" || $code == ""){
            ouputJson(201,lang('PARAM_ERROR'));
        }

        $emcode = $this->redis->get('emailid_'.$emailid);
        if($emcode == ""){
            ouputJson(201,lang('PLASE_SEND_CODE'));
        }

        if($code != $emcode){
            ouputJson(201,lang('CODE_ERROR'));
        }else{
            //验证成功 删除code 返回新的code
            $this->redis->del('emailid_'.$emailid);
            $code = Str::random(6);
            $this->redis->set('email_'.$emailid,$code,300);
            ouputJson('200',lang('SUCCESS'),$code);
        }

    }
    
    /*
     * 我的资产
     */
    public function mineAsset()
    {
        $id = $this->uid;
        //余额数量 交易冻结 提现冻结 币种简称 锁仓数量
        $finance = Db::table('user_finance')->alias('uf')
            ->field('uf.amount,uf.trans_frost,ci.short_name,uf.out_frost,uf.lock_amount,ci.decimal_digits as coin')
            ->join('coin_info ci','uf.ci_id = ci.ci_id')
            ->where('uf.ui_id','=',$id)
            ->where('uf.status','=',1)
            ->where('ci.status','=',1)
            ->order('ci.ci_id','ASC')
            ->select();
        //获取CNY汇率
        $redis = Redis::instance();
        $cny = $redis->get('USD_CNY');
        if ($cny == null){
            $cny = get_exchange_rate();
        }
        if ($finance != null){
            //重组返回数组
            $data = [];
            foreach ($finance as $v) {
                //获取币种兑换成USDT
                $usdt = get_time_price($v['short_name']);
                $lower  = decimal_format($v['amount'],$v['coin'],false);
                $lowerd = decimal_format($v['trans_frost']+$v['out_frost'],$v['coin'],false);
                $info1  = decimal_format($v['amount'],$v['coin'],false);
                $info2  = decimal_format($v['trans_frost']+$v['out_frost'],$v['coin'],false);
                $info3  = decimal_format($v['lock_amount'],$v['coin'],false);
                $total  = decimal_format($v['amount']+$v['trans_frost']+$v['out_frost']+$v['lock_amount'],$v['coin'],false);
                $total_cny = decimal_format(($v['amount']+$v['trans_frost']+$v['out_frost'])*$usdt*$cny,2,false);
                $lock   = decimal_format($v['lock_amount'],$v['coin'],false);
                $data[$v['short_name']] = [
                    strtolower($v['short_name'])                    => "{$lower}",
                    strtolower($v['short_name']).'d'                => "{$lowerd}",
                    'info'                                          => ["{$info1}","{$info2}","{$info3}"],
                    'total'                                         => "{$total}",
                    'total_cny'                                     => "{$total_cny}",
                    'lock_'.strtolower($v['short_name']).'Nums'     => "{$lock}"
                ];
            }
            ouputJson(200,lang('SUCCESS'),$data);
        }else{
            ouputJson(201,lang('NO_DATA'));
        }

    }
    
    /*
     * APP 我的资产
     */
    public function appMineAsset()
    {
        $id = $this->uid;
        //余额数量 交易冻结 提现冻结 币种简称 锁仓数量
        $finance = Db::table('user_finance')->alias('uf')
            ->field('ci.ci_id,uf.amount,uf.trans_frost,ci.short_name,uf.out_frost,uf.lock_amount')
            ->join('coin_info ci','uf.ci_id = ci.ci_id')
            ->where('uf.ui_id','=',$id)
            ->where('uf.status','=',1)
            ->where('ci.status','=',1)
            ->order('ci.ci_id','ASC')
            ->select();
        //获取CNY汇率
        $redis = Redis::instance();
        $cny = $redis->get('USD_CNY');
        if ($cny == null){
            $cny = get_exchange_rate();
        }
        if ($finance != null){
            //USDT总额
            $usdt_total = 0;
            $cny_total = 0;

            //重组返回数组
            $data = [];
            foreach ($finance as $v) {
                //获取币种兑换成USDT
                $usdt = get_time_price($v['short_name']);
                $arr = [
                    'id'=>$v['ci_id'],
                    'name'=>$v['short_name'],
                    'usable' => $v['amount'] + 0,
                    'freeze' => $v['trans_frost'] + $v['out_frost'] + 0,
                    'lock' => $v['lock_amount'] + 0,
                    'info' => [$v['amount']+0,$v['trans_frost'] + $v['out_frost'],$v['lock_amount']+0],
                    'total' => $v['amount']+$v['trans_frost']+$v['out_frost']+$v['lock_amount']+0,
                    'total_cny' => ($v['amount']+$v['trans_frost']+$v['out_frost']+$v['lock_amount'])*$usdt*$cny,
                ];
                $data[] = $arr;
                //总额
                $usdt_total += ($v['amount']+$v['trans_frost']+$v['out_frost']+$v['lock_amount']+0)*$usdt;
                $cny_total += ($v['amount']+$v['trans_frost']+$v['out_frost']+$v['lock_amount']+0)*$usdt*$cny;
            }

            //BTC换算USDT
            $btc_usdt = get_time_price('btc');
            //总额换算成BTC
            $btc_total = round($usdt_total/$btc_usdt,8);//四舍五入8位小数

            $total = [
                'btc_total'=>$btc_total,
                'cny_total'=>$cny_total,
            ];
            $data[] = $total;
            ouputJson(200,lang('SUCCESS'),$data);
        }else{
            ouputJson(201,lang('NO_DATA'));
        }
    }

    /*
     * 币种选择 地址管理 列表
     */
    public function coinAdd(Request $request)
    {
        $id = $this->uid;
        $listRows = $request->param('rows',10);//每页页数
        $page = $request->param('page',1);//当前页
        $type = $request->param('type','');//币种ID

        //查询条件
        $where[] = ['cd.ui_id','=',$id];
        $where[] = ['cd.status','=','1'];
        $where[] = ['ci.status','=','1'];

        if ($type != ''){
            $type = explode('-',$type);
            $where[] = ['cd.ci_id','in',$type];
        }

        //配置页数
        $config = ['page'=>$page];

        //查询的字段
        $field = [
            'cd.cdua_id as id',
            'cd.addr as addr',
            'ci.short_name as name',
            'cd.name as remark'
        ];

        $addr = Db::table('coin_downuseraddr')->alias('cd')
            ->field($field)
            ->join('coin_info ci','ci.ci_id = cd.ci_id')
            ->where($where)
            ->order('cdua_id desc')
            ->paginate($listRows,false,$config);
        //返回数据
        ouputJson(200,lang('SUCCESS'),$addr);
    }

    /*
     * 添加地址
     */
    public function addCoinAdd(Request $request)
    {
        $rules = [
            'name'          => 'require|length:0,50',
            'ci_id'         => 'require',
            'addr'          => 'require|length:0,50',
        ];
        $msg = [
            'name.require'          => '备注未填写!',
            'name.length'           => '备注长度过长!',
            'ci_id.require'         => '未选择币种!',
            'addr.require'          => '地址未填写!',
            'addr.length'           => '地址长度过长!',
        ];
        $data = [
            'name'                  => $request->param('name',''),
            'ci_id'                 => $request->param('coin',''),
            'addr'                  => $request->param('add',''),
        ];

        $where = [
            ['ui_id','=',$this->uid],
            ['ci_id','=',$data['ci_id']],
            ['addr','=',$data['addr']]
        ];
        $result = db('coin_downuseraddr')
            ->where($where)
            ->count('cdua_id');

        if ($result > 0){
            ouputJson('201','该地址已经存在!');
        }
        $validate = new Validate($rules,$msg);
        if (!$validate->check($data)){
            ouputJson(201,$validate->getError());
        }else{
            //验证通过 新增地址
            $save_data = [
                'name'          => $data['name'],
                'ui_id'         => $this->uid,
                'status'        => '1',
                'ci_id'         => $data['ci_id'],
                'addr'          => $data['addr']
            ];
            $res = Db::table('coin_downuseraddr')->insert($save_data);
            if ($res){
                ouputJson(200,lang('SUCCESS'));
            }else{
                ouputJson(201,lang('SYSTEM_ERROR'));
            }
        }
    }

    /*
     * 删除地址
     */
    public function delCoinAdd(Request $request)
    {
        $id = $request->param('id','');
        if ($id == ''){
            ouputJson(201,lang('ID_ERROR'));
        }
        $res = Db::table('coin_downuseraddr')
            ->where('cdua_id','=',$id)
            ->where('ui_id','=',$this->uid)
            ->delete();
        if ($res){
            ouputJson(200,lang('SUCCESS'));
        }else{
            ouputJson(201,lang('SYSTEM_ERROR'));
        }
    }
    
    /*
     * 我的推广
     */
    public function mineSpread()
    {
        //我的推广人数
        $code = $this->userinfo['refer_code'];
        $id = $this->uid;

        $count = Db::table('user_info')
            ->field('count(ui_id) as count')
            ->where('pparentrefer_code','=',$code)
            ->whereOr('parentrefer_code','=',$code)
            ->select();
        $count = $count[0]['count'];
        //我的获利
        $amount = Db::table('user_spread_logs')
            ->where('benefit_uid','=',$id)
            ->where('status','=',1)
            ->sum('amount');
        //sdt换算成btc
        $btc = get_time_price('sdt','btc');
        $amount = $amount*$btc;
        //获取二维码海报
        $path = $this->getQrcode($code);
        //返回数据
        $data = [
            'count'     =>  $count,
            'amount'    =>  $amount,
            'spread'    =>  $code,
            'path'      =>  $path,
        ];
        ouputJson('200',lang('SUCCESS'),$data);
    }
    
    /*
     * 邀请记录  明细
     */
    public function spreadRecord()
    {
        $code = $this->userinfo['refer_code'];
        //邀请总人数
        $total = Db::table('user_info')
            ->where('parentrefer_code','=',$code)
            ->whereOr('pparentrefer_code','=',$code)
            ->count();
        //一级邀请
        $first = Db::table('user_info')
            ->where('parentrefer_code','=',$code)
            ->count();
        //二级邀请
        $second = Db::table('user_info')
            ->where('pparentrefer_code','=',$code)
            ->count();
        //邀请明细  top30
        $data = Db::table('user_info')
            ->alias('ui1')
            ->field('ui2.ui_id as id,ui1.parentrefer_code as code,count(ui1.parentrefer_code) as count,ui2.email,if(ui2.createTime="","",FROM_UNIXTIME(ui2.createTime,"%Y-%m-%d")) as createTime')
            ->join('user_info ui2','ui2.refer_code = ui1.parentrefer_code')
            ->where('ui1.pparentrefer_code','=',$code)
            ->group('ui1.parentrefer_code')
            ->order('count','desc')
            ->limit(0,30)
            ->select();
        foreach ($data as $k => $v) {
            $second_data = Db::table('user_info')
                ->field('ui_id as id,email,if(createTime="","",FROM_UNIXTIME(createTime,"%Y-%m-%d")) as createTime')
                ->where('pparentrefer_code','=',$code)
                ->where('parentrefer_code','=',$v['code'])
                ->select();
            $data[$k]['data'] = $second_data;
        }
        $ids = array_column($data,'id');
        $num = count($data);
        if ($num < 30){
            $start = 30 - $num;
            $one_data = Db::table('user_info')
                ->field('ui_id as id,email,if(createTime="","",FROM_UNIXTIME(createTime,"%Y-%m-%d")) as createTime')
                ->where('parentrefer_code','=',$code)
                ->whereNull('pparentrefer_code')
                ->whereNotIn('ui_id',$ids)
                ->limit(0,$start)
                ->select();
            $data = array_merge($data,$one_data);
        }
        $out_data['total'] = $total;
        $out_data['first'] = $first;
        $out_data['second'] = $second;
        $out_data['data'] = $data;
        //返回数据
        ouputJson('200',lang('SUCCESS'),$out_data);
    }
    
    /*
     * 生成二维码
     */
    private function getQrcode($code,$x=510,$y=1100)
    {
        // 二维码链接
        $qr_url = 'http://starbridgechain.io/#/forget_psd?type=reg&spreadCode='.$code;
        // 定义保存目录
        $file_name = './static/qrcode';
        //判断文件是否存在
        $path = 'static/qrcode/qrcode'.$code.'.png';
        $result = file_exists($path);
        if (!$result){
            $config['file_name'] = $file_name;
            $config['generate']  = 'writefile';
            $config['name']      = $code;
            $qr_code = new QrcodeServer($config);
            $rs = $qr_code->createServer($qr_url);
            //背景图片path
            $path_1 = './static/qrcode/background.jpg';
            $suffix_1 = substr(strrchr($path_1, '.'), 1);
            if ($suffix_1 == 'jpg'){
                $suffix_1 = 'jpeg';
            }
            //二维码图片path
            $path_2 = $rs['data']['url'];
            $suffix_2 = substr(strrchr($path_2, '.'), 1);
            if ($suffix_2 == 'jpg'){
                $suffix_2 = 'jpeg';
            }
            //获取图片资源
            $create_1 = 'imagecreatefrom'.$suffix_1;
            $create_2 = 'imagecreatefrom'.$suffix_2;
            $image_1 = $create_1($path_1);
            $image_2 = $create_2($path_2);
            $image_3 = imageCreatetruecolor(imagesx($image_1),imagesy($image_1));
            $color = imagecolorallocate($image_3, 110, 110, 110);//白色的背景色
            imagefill($image_3, 0, 0, $color);
            imageColorTransparent($image_3, $color);
            imagecopyresampled($image_3, $image_1, 0, 0, 0, 0, imagesx($image_1), imagesy($image_1), imagesx($image_1), imagesy($image_1));
            imagecopymerge($image_3, $image_2, $x, $y,0, 0, imagesx($image_2), imagesy($image_2), 100);
            //合成二维码图片 跟 模板图片--------------end
            //输出到本地文件夹
            header('content-type:image/jpeg');
            $path='static/qrcode/qrcode'.$code.'.png';
            imagepng($image_3,$path);
            //消除资源
            imagedestroy($image_3);
        }
        //返回路径
        return $path;
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
     * 验证交易密码是否正确
     */
    public function checkTradePwd(Request $request)
    {
        //接收密码
        $pwd = $request->param('pwd','');
        if ($this->userinfo['trade_pwd'] == ''){//未设置交易密码
            ouputJson('201',lang('PWD_NOT_SET'));
        }
        if ($pwd == ''){
            ouputJson('201',lang('PARAM_ERROR'));
        }
        //验证密码
        if (!Hash::check($pwd,$this->userinfo['trade_pwd'],'md5',['salt'=>$this->userinfo['trade_salt']])){
            ouputJson('201',lang('PWD_IS_ERROR'));
        }else{
            ouputJson('200',lang('SUCCESS'));
        }
    }

    /*
     * 获取币种列表+地址
     */
    public function coin_list()
    {
        $list = Db::name('coin_info')->field('ci_id,short_name')->where(['status'=>1])->select();
        if(!empty($list)){
            foreach ($list as $k => $v) {
                $where = [
                    'status'=>1,
                    'ui_id'=>$this->uid,
                    'ci_id'=>$v['ci_id']
                ];
                $count = Db::table('coin_downuseraddr')
                    ->where($where)
                    ->count();
                $list[$k]['ci_id'] = $v['ci_id'];
                $list[$k]['short_name'] = urlencode($v['short_name']);
                $list[$k]['count'] = $count;
            }
        }
        ouputJson(200,'',$list);
    }
    
}