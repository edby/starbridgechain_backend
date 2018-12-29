<?php

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