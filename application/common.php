<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\Config;
use think\Db;
use redis\Redis;
use excel\excelclass\ExcelExport;
// 应用公共文件


function _getFloatLength($num) {
    $count = 0;
    $temp = explode ( '.', $num );
    if (sizeof ( $temp ) > 1) {
        $decimal = end ( $temp );
        $count = strlen ( $decimal );
    }
    return $count;
}

function drawwith_status($status){
    //0待审核  1通过审核  2冻结拒绝审核  3返还拒绝审核 4兑付中 5兑付完成 6兑付失败 7已撤回
    $dsc = "";
    switch ($status) {
        case '0':
            $dsc = "待审核";
            break;
        case '1':
            $dsc = "通过审核";
            break;
        case '2':
            $dsc = "冻结拒绝审核";
            break;
        case '3':
            $dsc = "返还拒绝审核";
            break;
        case '4':
            $dsc = "兑付中";
            break;
        case '5':
            $dsc = "兑付完成";
            break;
        case '6':
            $dsc = "兑付失败";
            break;
        case '7':
            $dsc = "已撤回";
            break;
        default:
            $dsc = "未知";
            break;
    }
    return $dsc;
}

//导出多个excel文件
function export_excel_zip($filename,$title,$data){
    $limit = 5000;
    $filter = [];
    $count = count($data);
    $excelObj = (new ExcelExport())->filename($filename)->title($title)->filter($filter);
    for ($i=0; $i < ceil($count/$limit); $i++) { //分段查询, 一次$limit条
        $offset = $i * $limit;
        $data_item = array_slice($data, $offset,$limit);
        $excelObj->excel($data_item, $i+1);
    }
    $excelObj->fileload();
}

function inter_post_ssl($url, $vars, $second=30,$aHeader=array()){
    $ch = curl_init();
    //超时时间
    curl_setopt($ch,CURLOPT_TIMEOUT,$second);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
    //这里设置代理，如果有的话
    curl_setopt($ch,CURLOPT_URL,$url);
    curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
    curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);  
    
    if( count($aHeader) >= 1 ){
        curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
    }
 
    curl_setopt($ch,CURLOPT_POST, 1);
    curl_setopt($ch,CURLOPT_POSTFIELDS,$vars);
    $data = curl_exec($ch);
    if($data){
        curl_close($ch);
        return $data;
    }
    else { 
        $error = curl_errno($ch);
        curl_close($ch);
        return false;
    }
}

/**
 * accessToken
 * @return string
 */
function accessToken(){
    //c6d889d1-9810-4cc3-8ecc-8b2c3a40e4ab
    $accessToken = md5($_SERVER['REQUEST_TIME_FLOAT'] . uniqid('',true));
    $startNum    = 8;
    for($i = 0 ;$i < 4; ++$i){
        $accessToken = substr_replace($accessToken,'-',$startNum,0);
        $startNum   += 5;
    }
    return $accessToken;
}

/**
 * accessToken
 * @return string
 */
function decimal_format($number, $n, $isRepate = true, $type = 0){
    if ($type == 2) {//进1
        $p = pow(10, $n);
        $number = ceil($number * $p) / $p;
    } elseif ($type == 3) {//舍去
        $p = pow(10, $n);
        $number = floor($number * $p) / $p;
    } else {
        $p = pow(10, $n);
        $number = round($number * $p) / $p;
    }
    if ($isRepate == TRUE) {
        return sprintf('%.' . $n . 'f', $number);
    } else {
        if(stripos($number, "e") || stripos($number, "E")){ //处理科学计数法
            // return strval(sctonum($number,$n));
            return strval(str_replace(',', '', sctonum($number,$n)));
        }else{
            return strval($number);
        }
    }
}


function sctonum($number,$n){
    $num = number_format($number,$n);
    $nums = explode('.', $num);
    if(isset($nums[1])){
        $idx = 0;
        for($i = strlen($nums[1])-1; $i >= 0 ; $i--){
            if($nums[1][$i] > 0){
                break;
            }else{
                $idx++;
            }
        }
        return number_format($number,strlen($nums[1])-$idx);
    }else{
        return $num;
    }
}
/**
 * 创建防CSRF攻击秘钥
 * @return string
 */
function create_csrf_secret(){
    $csrToken = md5($_SERVER['REQUEST_TIME_FLOAT'] . uniqid('',true));
    $startNum    = 8;
    for($i = 0 ;$i < 4; ++$i){
        $csrToken = substr_replace($csrToken,'-',$startNum,0);
        $startNum   += 5;
    }
    return $csrToken;
}
/**
 * 获取客户端IP
 * @return string
 */
function get_client_ip($type = 0) {
    $type       =  $type ? 1 : 0;
    static $ip  =   NULL;
    if ($ip !== NULL) return $ip[$type];
    if(@$_SERVER['HTTP_X_REAL_IP']){//nginx 代理模式下，获取客户端真实IP
        $ip=$_SERVER['HTTP_X_REAL_IP'];
    }elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {//客户端的ip
        $ip     =   $_SERVER['HTTP_CLIENT_IP'];
    }elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {//浏览当前页面的用户计算机的网关
        $arr    =   explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $pos    =   array_search('unknown',$arr);
        if(false !== $pos) unset($arr[$pos]);
        $ip     =   trim($arr[0]);
    }elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip     =   $_SERVER['REMOTE_ADDR'];//浏览当前页面的用户计算机的ip地址
    }else{
        $ip=$_SERVER['REMOTE_ADDR'];
    }
    // IP地址合法验证
    $long = sprintf("%u",ip2long($ip));
    $ip   = $long ? array($ip, $long) : array('0.0.0.0', 0);
    return $ip[$type];
}
/**
 * 记录特殊用户余额变动日志
 * @return string
 */
function special_user_log($ui_id,$amount,$desc){
    $user_type = Db::name('user_info')->where(['ui_id'=>$ui_id])->value('user_type');
    Db::name('special_user_logs')->insert([
        'ui_id'=>$ui_id,
        'user_type'=>$user_type,
        'changeval'=>$amount,
        'changelog'=>$desc,
        'creatime'=>time(),
        'creatime_format'=>date('Y/m/d H:i:s')
    ]);
}

function set_user($user_type,$amount,$type,$ci_id=1){
    $usertype =  Db::name('user_info')->field('ui_id,user_type')->where(['user_type'=>$user_type])->find();
    if(empty($usertype)){
        switch ($user_type) {
            case '3':
                $account = "registerExtensionUser";
                $email = "register@starbridgechain.com";
                break;
            case '4':
                $account = "transactionFeeUser";
                $email = "transcationfee@starbridgechain.com";
                break;
            case '5':
                $account = "withdrawFeeUser";
                $email = "downfee@starbridgechain.com";
                break;
            default:
                $account = "unknown";
                $email = "unknown@starbridgechain.com";
                break;
        }
        Db::name('user_info')->insert([
            'account'=>$account,
            'name'=>$account,
            'pwd'=>'b3f4e9e6fe8e27fc1158d0a38dd65b9e',
            'salt'=>'gehua',
            'email'=>$email,
            'status'=>0,
            'createTime'=>time(),
            'user_type'=>$user_type
        ]);
        $ui_id = Db::name('user_info')->getLastInsID();
        if($ui_id > 0){
            $insfinace = Db::name('user_finance')->insert(['ui_id'=>$ui_id,'ci_id'=>$ci_id,'status'=>1,'create_time'=>time()]);
            if($insfinace <=0){return [];}
            $sysamount = 0;
        }else{return [];}
    }else{
        $ui_id = $usertype['ui_id'];
        $finance = Db::name('user_finance')->where(['ui_id'=>$ui_id,'ci_id'=>$ci_id])->find();
        if(empty($finance)){
            $insfinace = Db::name('user_finance')->insert(['ui_id'=>$ui_id,'ci_id'=>$ci_id,'status'=>1,'create_time'=>time()]);
            if($insfinace <=0){return [];}
            $sysamount = 0;
        }else{
            $sysamount = $finance['amount'];
        }
    }
    return ['ui_id'=>$ui_id,'amount'=>$amount,'type'=>$type,'ci_id'=>$ci_id,'sysamount'=>$sysamount];
}

/**
 * 设置指定用户资产(使用需要在事务之外)
 */
function set_user_amount($action,$data = []){
    $resCode = 0;
    if(!empty($data)){
        if(isset($data['ui_id']) && isset($data['amount']) && isset($data['type']) && isset($data['ci_id']) && isset($data['sysamount'])){
            if($data['amount'] == 0){
                $resCode = 1;
            }else{
                $ui_id = $data['ui_id'];
                $amount = $data['amount'];
                $sysamount = $data['sysamount'];
                $type = $data['type'];
                $ci_id = $data['ci_id'];
                if($type==1){ //1添加 2减少
                    // $resCode = Db::name('user_finance')->where(['ui_id'=>$ui_id,'ci_id'=>$ci_id])->setInc('amount',$amount);
                    $resCode = updateUserBalance($ui_id,$ci_id,$action,[['field'=>'amount','type'=>'dec','val'=>$amount]]);
                }else{
                    $bijiao = bccomp($sysamount, $amount,13);
                    if( ($bijiao == 0 || $bijiao == 1) && $amount > 0){
                        // $resCode = Db::name('user_finance')->where(['ui_id'=>$ui_id,'ci_id'=>$ci_id])->setDec('amount',$amount);    

                        $resCode = updateUserBalance($ui_id,$ci_id,$action,[['field'=>'amount','type'=>'dec','val'=>$amount]]);
                    }else{
                        $resCode = -1;
                    }
                }
            }
        }
    }
    return $resCode;
}


/**
 * 获取自定义的header数据
 */
function get_all_headers(){
    $ignore = array('host','accept','content-length','content-type');
    $headers = array();
    foreach($_SERVER as $key=>$value){
        if(substr($key, 0, 5)==='HTTP_'){
            $key = substr($key, 5);
            $key = str_replace('_', ' ', $key);
            $key = str_replace(' ', '-', $key);
            $key = strtolower($key);
 
            if(!in_array($key, $ignore)){
                $headers[$key] = $value;
            }
        }
    }
    return $headers;
}

/**
 * 公钥加密
 * @return result
 */
 function data_rsa_encryption($data){
    //读取rsa公钥文件
    ini_set('error_reporting', -1);
    ini_set('display_errors', -1);
    // $private_key_path = dirname(dirname(__FILE__))."/extend/cret/rsa_private_key.pem";
    $public_key_path = dirname(dirname(__FILE__))."/extend/cret/rsa_public_key.pem";
    // $private_key = file_get_contents($private_key_path);
    $public_key = file_get_contents($public_key_path);
    $pu_key = openssl_pkey_get_public($public_key);
    openssl_public_encrypt(json_encode($data), $encrypted, $pu_key);
    $encrypted = base64_encode($encrypted);// base64传输
    return $encrypted;
 }

 /**
 * 私钥解密
 * @return array
 */
 function data_rsa_decrypt($data){
    //读取rsa公钥文件
    ini_set('error_reporting', -1);
    ini_set('display_errors', -1);
    $private_key_path = dirname(dirname(__FILE__))."/extend/cret/rsa_private_key.pem";
    $private_key = file_get_contents($private_key_path);
    $pi_key =  openssl_pkey_get_private($private_key);// 可用返回资源id
    openssl_private_decrypt(base64_decode($data), $decrypted, $pi_key);//私钥解密
    return json_decode($decrypted,true);
 }

/**
 * 
 * 拼接签名字符串
 * @param array $urlObj
 * @return 返回已经拼接好的字符串
 */
function ToUrlParams($urlObj)
{
    $buff = "";
    foreach ($urlObj as $k => $v)
    {
        if($k != "sign"){
            $buff .= $k . "=" . $v . "&";
        }
    }
    $buff = trim($buff."app_screct=".config('auth.app_screct'), "&");
    return $buff;
}

/**
 * 
 * 拼接PCWeb签名字符串
 * @param array $urlObj
 * @return 返回已经拼接好的字符串
 */
function ToUrlParamsForPcWeb($urlObj)
{
    $buff = "";
    foreach ($urlObj as $k => $v)
    {
        if($k != "sign"){
            $buff .= $k . "=" . $v . "&";
        }
    }
    $buff = trim($buff,"&");
    // echo $buff;
    // die;
    return $buff;
}
// decimal=1&limitMarket=1&market=1&noncestr=TcrEvm4PEn8Dc&price=263.59&timestamp=1544781310&token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1aWQiOjEzLCJleHBpcnlfdGltZSI6MTU0NDg2Nzc1NywiaXAiOiIxNzIuMTYuOS4yMzQifQ.TNQRISFKa4TcxogatogeIAVt-HMk8tFfakDSw8SvdNc


// decimal=1&limitMarket=1&market=1&noncestr=TcrEvm4PEn8Dc&price=263.59&timestamp=1544781310&token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1aWQiOjEzLCJleHBpcnlfdGltZSI6MTU0NDg2Nzc1NywiaXAiOiIxNzIuMTYuOS4yMzQifQ.TNQRISFKa4TcxogatogeIAVt-HMk8tFfakDSw8SvdNc


/**
 * 
 * 获取系统配置参数
 * @param field $field
 * @param return key=>value
 */
 function getSysconfig($field="*"){
    $config = [];
    $list = Db::name('system_configs')->where(['key'=>$field])->select();
    if(!empty($list)){
        foreach ($list as $k => $v) {$config[$v['key']] = $v['val'];}
    }
    return $config;
 }


/**
 * 
 * 生成签名字符串
 * @param array $urlObj
 * @return String sign
 */
function generateSign($signArr,$ispc = 0)
{
    ksort($signArr);
    if($ispc==1){
        $param = ToUrlParamsForPcWeb($signArr);
    }else{
        $param = ToUrlParams($signArr);    
    }
    return strtoupper(md5($param));
}

/**
 * 按照指定位数生成唯一随机邀请码
 * @param $num
 * @return string
 */
function spread_code($num = 6){
    $code="ABCDEFGHIGKLMNOPQRSTUVWXYZ";
    $rand=$code[rand(0,25)].strtoupper(dechex(date('m')))
        .date('d').substr(time(),-5)
        .substr(microtime(),2,5).sprintf('%02d',rand(0,99));
    for(
        $a = md5( $rand, true ),
        $s = '0123456789ABCDEFGHIJKLMNOPQRSTUV',
        $d = '',
        $f = 0;
        $f < $num;
        $g = ord( $a[ $f ] ), // ord（）函数获取首字母的 的 ASCII值
        $d .= $s[ ( $g ^ ord( $a[ $f + 8 ] ) ) - $g & 0x1F ],  //按位亦或，按位与。
        $f++
    );
    return $d;
}

/**
 * 输出标准json
 * @param $num
 * @return string
 */
function ouputJson($status, $msg='',$data = []){
    header('Content-Type: application/json');
    $json_array = array(
        'status' => $status,
        'msg' => urlencode($msg),
        'data' => $data
    );
    echo urldecode(json_encode($json_array));die;
}

/**
 * 创建所有币种账号
 * @param $num
 * @return string
 */
function createCoinAccount($ui_id){
    $coinList = Db::name('coin_info')->field('ci_id,name,short_name')->where(['status'=>1])->select();
    if(!empty($coinList)){
        foreach ($coinList as $k => $v) {
            $coinStatus = Db::name('user_finance')->where(['ui_id'=>$ui_id,'ci_id'=>$v['ci_id']])->find();
            if(empty($coinStatus)){
                Db::name('user_finance')->insert([
                    'ui_id'=>$ui_id,
                    'ci_id'=>$v['ci_id'],
                    'create_time'=>time(),
                    'status'=>1
                ]);
            }
        }
    }
}


/**
 * 判断字符是否是在字符串的开头
 * @param  [type]  $string [description]
 * @param  [type]  $chart  [description]
 * @return boolean         [description]
 */
function isChartBegin($string,$chart){

    if( strpos($string, $chart) === 0){
        return true;
    }else{
        return false;
    }

}

/**
 * 判断字符是否是在字符串的末尾
 * @param  [type]  $string [description]
 * @param  [type]  $chart  [description]
 * @return boolean         [description]
 */
function isChartEnd($string,$chart){


    if(strrchr($string,$chart) == $chart){
        return true;
    }else{
        return false;
    }
}


/**
 * 查询交易对信息
 * @param  [type] $arr [description]
 * @param  [type] $exp [description]
 * @return [type]      [description]
 */
function getTaByExp($arr,$exp){

    $nArr = [];

    $all = $exp == "*" && strlen($exp) == 1;
    $left = isChartBegin($exp,"*") && strlen($exp) > 1;
    $right = isChartEnd($exp,"*") && strlen($exp) > 1;
    $many = strpos($exp, ",") > 0;

    //var_dump($all,$left,$right,$many);

    //查询所有
    if($all) $nArr = $arr;

    if($left){  //查询结尾（开头*）  *btc
        $coin = str_replace("*","",$exp);
        foreach ($arr as $key => $val){
            $coin_redis = $val["name"];
            if(isChartEnd($coin_redis,$coin)){
                $nArr[] = $val;
            }
        }
    }else if($right){    //查询开头（结尾*）  btc*
        $coin = str_replace("*","",$exp);
        foreach ($arr as $key => $val){
            $coin_redis = $val["name"];
            if(isChartBegin($coin_redis,$coin)){
                $nArr[] = $val;
            }
        }
    }else if($many){  //查询多个（，）
        $coin_arr = explode(",",$exp);
        foreach ($arr as $key => $val){
            $coin_redis = $val["name"];
            if(in_array($coin_redis,$coin_arr)){
                $nArr[] = $val;
            }

        }
    }

    return $nArr;
}


/**
 * 格式化涨幅
 * @param  [type]  $list [description]
 * @param  boolean $last [description]
 * @return [type]        [description]
 */
function getFormatTradeArea1($list,$last=false,$pbit=2,$abit=2){

    foreach ($list as $key => $val){

        unset($list[$key]["fds"]);
        unset($list[$key]["open"]);

        $pbit = $val["price_bit"];
        $abit = $val["amount_bit"];

        $list[$key]["price"] = isdecimal_format($list[$key]["price"],$pbit);
        $list[$key]["per"] = isdecimal_format($list[$key]["per"],2);
        $list[$key]["total24"] = isdecimal_format($list[$key]["total24"],$pbit);
        $list[$key]["high"] = isdecimal_format($list[$key]["high"],$pbit);
        $list[$key]["low"] = isdecimal_format($list[$key]["low"],$pbit);
        $list[$key]["cny"] = isdecimal_format($list[$key]["cny"],2);
        $list[$key]["g"] = explode("_",$list[$key]["name"])[1];
        $list[$key]["n"] = explode("_",$list[$key]["name"])[0];


        $list[$key]["name"] = str_replace("_","/",$list[$key]["name"]);

        $nList[] = $list[$key];

    }
    
    return $nList;
        

}


function isdecimal_format($number, $n, $isRepate = true, $type = 0){
    return  $number;
}
    

/**
 * 生成BTC钱包地址
 * @return string 钱包地址
 */
function send_btc_wallet_address($config){
    require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'extend'.DIRECTORY_SEPARATOR.'bitcoin'.DIRECTORY_SEPARATOR.'easybitcoin.php');
    // $bitcoin = new Bitcoin(config('sms.btc_username'),config('sms.btc_password'),config('sms.btc_host'),config('sms.btc_port'));
    $bitcoin = new Bitcoin($config['account'],$config['password'],$config['host'],$config['port']);
    // dump($bitcoin);
    $address = $bitcoin->getnewaddress(config('sms.btc_accountname'));
    // dump($address);
    return $address;
}

/**
 * 查询交易记录
 * @return string 钱包地址
 */
function get_btc_transaction($config,$tx_id){
     require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'extend'.DIRECTORY_SEPARATOR.'bitcoin'.DIRECTORY_SEPARATOR.'easybitcoin.php');
    $bitcoin = new Bitcoin($config['account'],$config['password'],$config['host'],$config['port']);
    $transaction = $bitcoin->gettransaction($tx_id);
    return $transaction;
}


function query_address_info($address,$ci_id){
    // $sql = "SELECT cu.ci_id,cu.coinaddr,cu.ui_id,uf.amount,ui.account FROM coin_upuserbind as cu LEFT JOIN user_finance as uf ON cu.ui_id=uf.ui_id LEFT JOIN user_info as ui ON cu.ui_id=ui.ui_id WHERE cu.coinaddr='{$address}' AND cu.ci_id={$ci_id} AND uf.ci_id={$ci_id} AND uf.`status`=1;";
    return Db::name('coin_upuserbind')
    ->alias('cu')
    ->join('user_finance uf','cu.ui_id=uf.ui_id','left')
    ->join('user_info ui','cu.ui_id=ui.ui_id','left')
    ->field('cu.ci_id,cu.coinaddr,cu.ui_id,uf.amount,ui.account')
    ->where(['cu.coinaddr'=>$address,'cu.ci_id'=>$ci_id,'uf.ci_id'=>$ci_id,'uf.status'=>1])
    ->find();
}
function wirteLog($log){
    $date = date('Ymd');
    $filepath = dirname(dirname(__FILE__))."/runtime/log/recharge/charge/charge_{$date}.log";
    $mkpath = dirname(dirname(__FILE__))."/runtime/log/recharge/charge/";
    if(!is_dir($mkpath)){
        @mkdir($mkpath,0777,true);
    }
    $str = "[".date("Y-m-d H:i:s")."]".$log;
    $str .= PHP_EOL;
    file_put_contents($filepath,$str,FILE_APPEND);
}

/**
 * 生成以太坊钱包地址
 * @return string 钱包地址
 */
function send_eth_wallet_address($rpcinfo){
    $rpcinfo = json_decode($rpcinfo,true);
    $address = "";
    $url = "{$rpcinfo['proto']}://{$rpcinfo['host']}:{$rpcinfo['port']}";
    $param['jsonrpc'] = "2.0";
    $param['method'] = "personal_newAccount";
    $param['params'] = array("123456");
    $param['id'] = rand(1,100);
    $aHeader[] = "Content-Type:application/json";
    $data = json_decode(inter_post_ssl($url,json_encode($param),30,$aHeader),true);
    if(isset($data['result']) && $data['result']!=""){
        $address = $data['result'];
    }
    return $address;
}

/**
 * 查询eth交易记录
 * @return string 钱包地址
 */
function get_eth_transaction($rpcinfo,$tx_id){
    $rpcinfo = json_decode($rpcinfo,true);
    $url = "{$rpcinfo['proto']}://{$rpcinfo['host']}:{$rpcinfo['port']}";
    $param['jsonrpc'] = "2.0";
    $param['method'] = "eth_getTransactionByHash";
    $param['params'] = array("{$tx_id}");
    $param['id'] = rand(1,100);
    $aHeader[] = "Content-Type:application/json";
    $data = json_decode(inter_post_ssl($url,json_encode($param),30,$aHeader),true);
    return $data;
}
/**
 * 查询eth最新区块号
 * @return string 钱包地址
 */
function get_block_number($rpcinfo){
    $rpcinfo = json_decode($rpcinfo,true);
    $url = "{$rpcinfo['proto']}://{$rpcinfo['host']}:{$rpcinfo['port']}";
    $param['jsonrpc'] = "2.0";
    $param['method'] = "eth_blockNumber";
    $param['params'] = array();
    $param['id'] = rand(1,100);
    $aHeader[] = "Content-Type:application/json";
    $data = json_decode(inter_post_ssl($url,json_encode($param),30,$aHeader),true);
    if(isset($data['result']) && $data['result']!=""){
        return hexdec($data['result']);
    }else{
        return 0;
    }
}


//php自带的dechex无法把大整型转换为十六进制
function bc_dechex($decimal,$wei)
{
    $result = [];
    while ($decimal != 0) {
        $mod = $decimal % $wei;
        $decimal = floor($decimal / $wei);
        array_push($result, dechex($mod));        
    }
    return join(array_reverse($result));
}

/**
 * 获取  CNY 汇率 早上8点
 */
function get_exchange_rate(){
    $html = \curl\Curl::get('http://www.currencydo.com/index/api/zst_huilv/hbd/USD_CNY.json');
    $arr = json_decode($html);
    $data = array_pop($arr);

    if ($data != null){
        return $data[1];
    }else{
        ouputJson('201-1','NO_DATA');
    }
}

/**
 * 获取当前成交价格 币种/USDT
 */
function get_time_price($fromcoin,$tocoin = 'usdt'){
    if (strtoupper($fromcoin) == 'USDT'){
        return '1';
    }
    $redis = Redis::instance();
    $redis->select(2);
    $str = $redis->get('swoole:trade:area');
    $arr = json_decode($str,true);
    $fromcoin = strtoupper($fromcoin);
    $tocoin = strtoupper($tocoin);
    $coin = $fromcoin.'_'.$tocoin;

    //返回合适的数据
    foreach ($arr as $item) {
        if ($item['name'] == $coin){
            return $item['price'];
        }
    }
}

/*
 * 自定义入栈函数
 */
function pushStack(&$stack,$channel,$dep){
    array_push($stack, array('channel'=>$channel,'dep'=>$dep));
}

/*
 * 自定义出栈函数
 */
function popStack(&$stack){
    return array_pop($stack);
}

function create_orderno(){
    @date_default_timezone_set("PRC");
    $order_date = date('Y-m-d');
    $order_id_main = date('YmdHis') . rand(10000000,99999999);
    $order_id_len = strlen($order_id_main);
    $order_id_sum = 0;
    for($i=0; $i<$order_id_len; $i++){
        $order_id_sum += (int)(substr($order_id_main,$i,1));
    }
    $order_id = $order_id_main . str_pad((100 - $order_id_sum % 100) % 100,2,'0',STR_PAD_LEFT);
    $order_id =  substr($order_id, 16) ;
    return $order_id;
}

//邮件处理@符号前三位使用*代替
function email_format($email){
    $emailEx = explode('@', $email);
    if(isset($emailEx[0]) && isset($emailEx[1])){
        if(strlen($emailEx[0]) >=3){
            return substr($emailEx[0], 0,strlen($emailEx[0])-3)."***".$emailEx[1];
        }else{
            return $emailEx[0]."***".$emailEx[1];
        }
    }else{
        return $email;
    }
}

/**
 * 生成错误详情
 * @param Exception $e
 * @return string
 */
function myGetTrace(Exception $e)
{
    if (isset($e->getTrace()[0]['args'][2]) && is_string($e->getTrace()[0]['args'][2])){
        $trace = $e->getTrace()[0]['args'][2].':'.$e->getLine();
    }elseif (isset($e->getTrace()[0]['file']) && is_string($e->getTrace()[0]['file'])){
        $trace = $e->getTrace()[0]['file'].':'.$e->getTrace()[0]['line'];
    }else {
        $trace = '';
    }
    $trace = '['.json_encode($e->getCode()).']'.$e->getMessage().'['.$trace.']';
    return $trace;
}

function upTypeDsc($type){
    $dsc = "";
    switch ($type) {
        case 'withdraw':
            $dsc = "提现";
            break;
        case 'recharge':
            $dsc = "充值";
            break;
        case 'trans':
            $dsc = "交易";
            break;
        case 'cents':
            $dsc = "分红";
            break;
        case 'regis':
            $dsc = "注册奖励";
            break;
        case 'spread':
            $dsc = "推广获利";
            break;
        case 'recallwith':
            $dsc = "提现撤回";
            break;
        default:
            $dsc = "未知";
            break;
    }
    return $dsc;
}

/**
 * 操作用户余额表
 * @param ui_id 用户ID
 * @param param 更新参数，格式如下：
 * $param = [
 *  [
 *      ['field'=>'amount','type'=>'inc','val'=>'1'], inc加 dec减
 *      ['field'=>'trans_frost','type'=>'dec','val'=>'0'] inc加 dec减
 *  ]
 * @param param action 业务(withdraw提现 recallwith撤回提现 recharge充值 trans交易 cents分红 regis注册奖励 spread推广获利)
 * @return count 成功更新的行数
 */
 function updateUserBalance($ui_id,$ci_id,$action,$param = []){
    if(empty($param) || $ci_id=="" || $ui_id=="" || $action==""){
        return 0;
    }
    $installAllData = [];

    $tablename = "user_finance";
    $sql = "UPDATE {$tablename} SET ";
    foreach ($param as $k => $v) {

        $val = $v['field'].(strtolower($v['type'])=="inc"?"+":"-").$v['val'];

        $sql .= $v['field']."=".$val.",";
        $installAllData[] = [
            'ui_id'=>$ui_id,
            'ci_id'=>$ci_id,
            'field'=>$v['field'],
            'val'=>$v['val'],
            'status'=>0,
            'type'=>strtolower($v['type']),
            'action'=>$action,
            'action_dec'=>upTypeDsc($action),
            'creatime'=>time(),
            'creatime_formart'=>date('Y/m/d H:i:s')
        ];
    }
    $sql .= "update_time=".time()." WHERE ui_id={$ui_id} AND ci_id={$ci_id}";
    
    $count = Db::execute($sql);
    if($count > 0){
        if(!empty($installAllData)){
            foreach ($installAllData as $key => $value) {$installAllData[$key]['status'] = 1;}
        }
        Db::name('user_finance_global_log')->insertAll($installAllData);
        return $count;
    }else{
        if(!empty($installAllData)){
            foreach ($installAllData as $key => $value) {$installAllData[$key]['status'] = 0;}
        }
        Db::name('user_finance_global_log')->insertAll($installAllData);
        return $count;
    }
 }

