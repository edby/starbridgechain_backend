<?php 

/**
 * 浏览器友好的变量输出
 * @access public
 * @param  mixed         $var 变量
 * @param  boolean       $echo 是否输出 默认为true 如果为false 则返回输出字符串
 * @param  string        $label 标签 默认为空
 * @param  integer       $flags htmlspecialchars flags
 * @return void|string
 */
function dump($var, $echo = true, $label = null, $flags = ENT_SUBSTITUTE)
{
    $label = (null === $label) ? '' : rtrim($label) . ':';
    if ($var instanceof Model || $var instanceof ModelCollection) {
        $var = $var->toArray();
    }

    ob_start();
    var_dump($var);

    $output = ob_get_clean();
    $output = preg_replace('/\]\=\>\n(\s+)/m', '] => ', $output);

    if (PHP_SAPI == 'cli') {
        $output = PHP_EOL . $label . $output . PHP_EOL;
    } else {
        if (!extension_loaded('xdebug')) {
            $output = htmlspecialchars($output, $flags);
        }
        $output = '<pre>' . $label . $output . '</pre>';
    }
    if ($echo) {
        echo($output);
        return;
    }
    return $output;
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


 function installAllSql($data){
    $sql = "INSERT INTO user_finance_global_log (`ui_id`, `ci_id`, `field`, `val`, `status`, `type`, `action`, `action_dec`, `creatime`, `creatime_formart`) VALUES ";
    foreach ($data as $k => $v) {
        $sql .= "('".implode("','",$v)."'),";
    }
    $sql = substr($sql, 0,-1);
    return $sql;

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
        case 'robotrans':
            $dsc = "机器人交易";
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
 * $param = 
 *  [
 *      ['field'=>'amount','type'=>'inc','val'=>'1'], inc加 dec减
 *      ['field'=>'trans_frost','type'=>'dec','val'=>'0'] inc加 dec减
 *  ]
 * @param param action 业务(withdraw提现 recallwith撤回提现 recharge充值 trans交易 robotrans机器人交易 cents分红 regis注册奖励 spread推广获利)
 * @param param dbconn 数据库驱动
 * @return count 成功更新的行数
 */
 function updateUserBalance($ui_id,$ci_id,$action,$param = [],$dbconn){
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

    $qid = $dbconn->query($sql);
    $count = $qid->rowCount();
    if($count > 0){
        if(!empty($installAllData)){
            foreach ($installAllData as $key => $value) {$installAllData[$key]['status'] = 1;}
        }
        $dbconn->query(installAllSql($installAllData));
        return $count;
    }else{
        if(!empty($installAllData)){
            foreach ($installAllData as $key => $value) {$installAllData[$key]['status'] = 0;}
        }
        $dbconn->query(installAllSql($installAllData));
        $dbconn->query('user_finance_global_log')->insertAll($installAllData);
        return $count;
    }
 }