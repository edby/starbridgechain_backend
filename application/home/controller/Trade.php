<?php

namespace app\home\controller;

use app\common\controller\AuthBase;
use app\home\model\MarketInfoModel;
use app\home\model\UserFinanceModel;
use redis\Redis;
use think\Db;
use think\Exception;
use think\facade\Env;
use think\facade\Log;
use think\helper\Hash;
use think\Request;

class Trade extends AuthBase
{
    const BCPRECISEDIGITS = 13; //小数点精确位数
    private static $buyType = 1; //挂买单类型
    private static $sellType = 2;

    private static $tradeStatus1 = 1; //挂单的状态 等待撮合
    private static $tradeStatus2 = 2; //挂单的状态 已撮合
    private static $tradeStatus3 = 3; //挂单的状态 已撤单
    private static $tradeStatus4 = 4; //挂单的状态 异常

    private static $status = 1; //对应数据的可用状态 0:不可用 1:可用

    private static $timeout = 120; //Redis锁,超时时间
    private static $roomIdMin = 1; //$roomId的随机区间
    private static $roomIdMax = 1000000; //$roomId的随机区间
    private static $order_noMin = 10000000; //$order_no的随机区间
    private static $order_noMax = 99999999; //$order_no的随机区间
    private static $usleep = 10000; //Redis锁,超时时间

    private static $setTimeLimit = 300; //脚本运行超时时间

    private static $marketKey = 'hash_market_info'; //redis存储交易市场信息的key
    private static $coinKey = 'hash_data_coinFee'; //redis存储币种手续费的key
    private static $uGroupKey = 'hash_data_userGroupFee'; //redis存储用户组手续费的key
    private static $listKey = 'list_preMatchMaking_'; //redis存储预挂单的key
    private static $restrict = 20; //预挂单队列的最大长度 超出不允许挂单

    private static $firstTrade = 1000; //交易市场 默认初始的成交价如果数据库也没有配置

    private static $hKey = 'hash_market_'; //redis存储挂单数据的key [hash_market_1_sell]
    private static $hKeyBuy = '_buy';
    private static $hKeySell = '_sell';

    private static $userType = 4; //手续费账户 的用户类型

    private static $action = 'trans';
    /**
     * 显示当前登录用户的可用币种余额
     * @param $market       : 需要查询的交易市场ID
     * @return string       : status:200表示成功,data:余额数据
     */
    public function myBalance($market)
    {
        $redis = $this->redis;
        $transactionPair = json_decode($redis->hGet(self::$marketKey,$market),true);
        if (empty($transactionPair)){
            $transactionPair = Db::table('market_info')->where('mi_id', $market)->find();
            if ($transactionPair['status'] == 0){
                return 'Disable1';//该交易市场不可用
            }
            $redis->hSet(self::$marketKey,$market,json_encode($transactionPair));
        }
        $map = [
            'ui_id'         =>   $this->uid,
            'ci_id'         =>   [$transactionPair['ci_id_first'],$transactionPair['ci_id_second']],
        ];
        $amount = UserFinanceModel::where($map)
            ->field('ci_id,amount')
            ->where('status', self::$status)
            ->select()
            ->toArray();
//            ->column('amount');
        if (count($amount) != 2){
            $result = config('code.error');
            $result['msg'] = '余额查询出错或数据库用户当前交易市场对应的2个币种信息缺失!'; //这个提示不会给用户看到!
            ouputJson($result['status'],$result['msg'],$result['data']);
        }
        if ($amount[0]['ci_id'] == $transactionPair['ci_id_second']){
            $data = array_reverse($amount);
        }else{
            $data = $amount;
        }
        $result = config('code.success');
        $result['data'] = $data;
        ouputJson($result['status'],$result['msg'],$result['data']);
    }

    /**
     * 返回显示手续费和实际手续费
     * @param $market           : 交易市场ID
     * @return mixed
     */
    public function fee($market,$type=1)
    {
        $userID = $this->uid;
        $redis = $this->redis;
        //分割交易对
        $transactionPair = json_decode($redis->hGet(self::$marketKey,$market),true);
        if (empty($transactionPair)){
            $transactionPair = Db::table('market_info')
                ->where('mi_id', $market)
                ->find();
            if ($transactionPair['status'] == 0){
                return 'Disable2';//该交易市场不可用
            }
            $redis->hSet(self::$marketKey,$market,json_encode($transactionPair));
        }

        //查询币种手续费(买:买币1得币1,手续费显示币1的)
        if ($type == 1){
            $coinInfo = json_decode($redis->hGet(self::$coinKey,$transactionPair['ci_id_first']),true);
            if (empty($coinInfo)){
                $coinInfo = Db::table('coin_info')
                    ->field('show_fee,fee')
                    ->where('ci_id', $transactionPair['ci_id_first'])
                    ->where('status', self::$status)
                    ->find();
                if (empty($coinInfo)){
                    return '数据库币1手续费信息未查到!';
                }
                $redis->hSet(self::$coinKey,$transactionPair['ci_id_first'],json_encode($coinInfo));
            }
        }elseif ($type == 2){
            $coinInfo = json_decode($redis->hGet(self::$coinKey,$transactionPair['ci_id_first']),true);
            if (empty($coinInfo)){
                $coinInfo = Db::table('coin_info')
                    ->field('show_fee,fee')
                    ->where('ci_id', $transactionPair['ci_id_second'])
                    ->where('status', self::$status)
                    ->find();
                if (empty($coinInfo)){
                    return '数据库币2手续费信息未查到!';
                }
                $redis->hSet(self::$coinKey,$transactionPair['ci_id_second'],json_encode($coinInfo));
            }
        }


        //主动方 用户 手续费!
        $userGroupFee = Db::table('user_group')->alias('ug')
            ->field('gi.show_fee,gi.fee')
            ->join('group_info gi', 'ug.gi_id = gi.gi_id', 'LEFT')
            ->where('ug.ui_id', $userID)
            ->where('gi.status',1)  //可用的分组
//            ->column('gi.fee');
            ->select();
        $marketFee = [
            'show_fee'          => $transactionPair['show_fee'],
            'fee'               => $transactionPair['fee'],
        ];
        $userGroupFee[] = (array)$coinInfo;
        $userGroupFee[] = (array)$marketFee;
        array_multisort (array_column($userGroupFee, 'fee'), SORT_ASC, array_column($userGroupFee, 'show_fee'), SORT_DESC, $userGroupFee);
        $result = config('code.success');
        $result['data'] = $userGroupFee[0];
        ouputJson($result['status'],$result['msg'],$result['data']);
    }

    /**
     * 判断是否提示过设置交易密码
     * @return string
     */
    public function tradePwdNotice()
    {
        $userID = $this->uid;
        $tradePwdNotice = Db::table('user_info')
            ->where('ui_id', $userID)
            ->field('trade_pwd_notice,trade_type')
            ->find();
        if ($tradePwdNotice['trade_pwd_notice'] === 0){
            Db::table('user_info')->where('ui_id', $userID)->setInc('trade_pwd_notice');
            $result = [
                'status'            =>   2001,
                'msg'               =>   '第一次交易,请设置交易密码!',
            ];
            ouputJson($result['status'],$result['msg']);
        }
        $result = [
            'status'            =>   2002,
            'msg'               =>   '已提示过设置密码!trade_type(1:需输密码,2:无需)',
            'data'              =>   'trade_type:'.$tradePwdNotice['trade_type'],
        ];
        ouputJson($result['status'],$result['msg'],$result['data']);
    }

    /**
     * 用户挂单提交-(买)
     * @param $limitMarket          :限价1/市价2
     * @param $price                :价格
     * @param $decimal              :数量
     * @param $market               :交易市场ID
     * @param string $payPwd        :交易密码
     * @param int $type             :买1/卖2
     * @return string|\think\response\Json|void
     */
    public function upTradeBuy($limitMarket, $price, $decimal, $market, $payPwd='')
    {
        try {
            set_time_limit(self::$setTimeLimit);
            $result = config('code.error');
            if ($price <= 0){
                $result['msg'] = lang('ERROR_NUMBER');
                ouputJson($result['status'],$result['msg'],$result['data']);
            }
            if (!preg_match('/^(1|2)$/', (string)$limitMarket)) {
                $result['msg'] = lang('PARAM_ERROR').'!:'.$limitMarket;
                ouputJson($result['status'],$result['msg'],$result['data']);
            }
            if (!preg_match('/^[1-9]+\d?$/', (string)$market)) {
                $result['msg'] = lang('PARAM_ERROR').':'.$market;
                ouputJson($result['status'],$result['msg'],$result['data']);
            }
//            $onOff = action("swoole/checkMarketStatus",[$market]);
            $onOff = Swoole::checkMarketStatus($market);
            if ($onOff === false){
                $result['msg'] = lang('NETWORK_IS_BUSY');
                ouputJson($result['status'],$result['msg'],$result['data']);
            }

            $userID = $this->uid; //继承AuthBase类 获取登录用户ID
            /**撮合交易的流程!!!**/
            //>>1.判断交易密码!有则匹配,没有不管!
            $tradeType = Db::table('user_info')->where('ui_id', $userID)->value('trade_type');
            if (empty($tradeType)){
                return 'error0';//用户交易密码提示标记未找到
            }
            if ($tradeType == 1){
                $check = $this->checkPayPwd($payPwd);
                if ($check === false){
                    $result['msg'] = lang('TRANS_PWD_ERROR');
                    ouputJson($result['status'],$result['msg'],$result['data']);
                }
            }
            $bc = bcscale(self::BCPRECISEDIGITS); //bc算法只能精算到小数点13位!
            if (!$bc)
                return 'error1';//bc默认保留位数失败!

            $redis = $this->redis;
            //分割交易对
            $transactionPair = json_decode($redis->hGet(self::$marketKey,$market),true);
            if (empty($transactionPair)){
//            $transactionPair = MarketInfoModel::where('mi_id', $market)->find();
                $transactionPair = Db::table('market_info')
                    ->where('mi_id', $market)
                    ->where('status', self::$status)
                    ->find();
                if (empty($transactionPair)){
                    return 'error2';//数据库交易市场信息未查到!
                }
                $redis->hSet(self::$marketKey,$market,json_encode($transactionPair));
            }
            if ($transactionPair['status'] == 0){
                return 'Disable';//该交易市场不可用
            }
            if ($decimal < $transactionPair['amount_input_min']){
                $result['msg'] = lang('MINIMUM_NUMBER').$transactionPair['amount_input_min'];
                ouputJson($result['status'],$result['msg'],$result['data']);
            }
            if (!preg_match('/^[0-9]+(.[0-9]{1,'.$transactionPair['amount_bit'].'})?$/', (string)$decimal)) {
                $result['msg'] = lang('LMT_DECIMAL_BIT').$transactionPair['amount_bit'];
                ouputJson($result['status'],$result['msg'],$result['data']);
            }
            if (!preg_match('/^[0-9]+(.[0-9]{1,'.$transactionPair['price_bit'].'})?$/', (string)$price)) {
                $result['msg'] = lang('LMT_PRICE_BIT').$transactionPair['price_bit'];
                ouputJson($result['status'],$result['msg'],$result['data']);
            }

            //判定队列长度 超过20 则提示繁忙
            $restrict = self::$restrict;
            $length = $redis->lLen(self::$listKey.$transactionPair['mi_id']);
            if ($length >= $restrict){
                $result['msg'] = lang('PLEASE_TRY_LATER');
                ouputJson($result['status'],$result['msg'],$result['data']);
            }

            //查询币种手续费(买:买币1得币1,手续费显示币1的)
            $coinInfo = json_decode($redis->hGet(self::$coinKey,$transactionPair['ci_id_first']),true);
            if (empty($coinInfo)){
                $coinInfo = Db::table('coin_info')
                    ->field('show_fee,fee')
                    ->where('ci_id', $transactionPair['ci_id_first'])
                    ->where('status', self::$status)
                    ->find();
                if (empty($coinInfo)){
                    return 'error3';//数据库币1手续费信息未查到!
                }
                $redis->hSet(self::$coinKey,$transactionPair['ci_id_first'],json_encode($coinInfo));
            }

            //主动方 用户 手续费!
            $userGroupFee = json_decode($redis->hGet(self::$uGroupKey,$userID),true);
            if (empty($userGroupFee)) {
                $userGroupFee = Db::table('user_group')->alias('ug')
                    ->join('group_info gi', 'ug.gi_id = gi.gi_id', 'LEFT')
                    ->where('ug.ui_id', $userID)
                    ->where('gi.status',self::$status)  //可用的分组
                    ->column('gi.fee');
                if (empty($userGroupFee)){
                    return 'error4';//数据库用户组手续费信息未查到!
                }
                $redis->hSet(self::$uGroupKey,$userID,json_encode($userGroupFee));
            }
            $fee = min(array_merge((array)$userGroupFee,(array)$coinInfo['fee'],(array)$transactionPair['fee']));

            //判断币种信息是否存在
            $where = [
                ['ci_id','in',[$transactionPair['ci_id_first'],$transactionPair['ci_id_second']]],
            ];
            $userAmount = UserFinanceModel::where('ui_id', $userID)
                ->where($where)
                ->where('status', self::$status)
                ->select();
            if (count($userAmount) != 2){
                return 'error5';//数据库用户当前交易市场对应的2个币种信息缺失!
            }
            set_user(self::$userType,0,0,$transactionPair['ci_id_first']);
            set_user(self::$userType,0,0,$transactionPair['ci_id_second']);

            $timeout = self::$timeout;
            $key = 'str_room_lock'.$transactionPair['mi_id'];
            $roomId = uniqid(mt_rand(self::$roomIdMin,self::$roomIdMax),true);
            $value = 'room_'.$roomId;  //分配一个随机的值针对问题3
            $isLock = null;
            //>>2.判断限价还是市价
            switch ($limitMarket){
                case 1:
                    //定义一个默认成交价
                    $default = $transactionPair['last_price']??self::$firstTrade;
                    //限价买入最低为成交价下浮$transactionPair['price_buy_min']/100
                    $buyLimitPrice = bcsub(1,$transactionPair['price_buy_min']/100,$transactionPair['price_bit']);

                    //>>2.1.限价买,输入的价格$price不能低于最后成交价的$buyLimitPrice倍
                    //获取最后买单的价格
                    $lastRecord = json_decode($redis->get('str_last_record_market_'.$transactionPair['mi_id']),true)['price']??$default;
                    $buyLimitPrice = bcmul($lastRecord,$buyLimitPrice,$transactionPair['price_bit']);
                    if ($price < $buyLimitPrice){
                        $result['msg'] = lang('LMT_PRICE_BUY').$buyLimitPrice;
                        ouputJson($result['status'],$result['msg'],$result['data']);
                    }

                    $buyLimitPrice = bcadd(1,$transactionPair['price_buy_max']/100,$transactionPair['price_bit']);
                    $buyLimitPrice = bcmul($lastRecord,$buyLimitPrice,$transactionPair['price_bit']);
                    if ($price > $buyLimitPrice){
                        $result['msg'] = lang('LMT_PRICE_BUY1').$buyLimitPrice;
                        ouputJson($result['status'],$result['msg'],$result['data']);
                    }

                    //买入 币1的数量$decimal 乘以 价格$decimal 等于 所需 币2的数量
                    $number = bcmul($decimal,$price);

                    //>>2.1.1.$decimal买入数量不能高于自己的账户余额
                    /*$amount = json_decode($redis->hGet('hash_data_userFinance',$userID.'_'.$transactionPair['ci_id_second']),true); //查询Redis的 余额数据 如果没有再查数据库
                    if (empty($amount)){
                        $amount = UserFinanceModel::where('ui_id', $userID)
                            ->where('ci_id', $transactionPair['ci_id_second'])
                            ->find();
                    }*/
                    //减低效率,必须查库,防止挂单前 用户提取余额
                    $amount = UserFinanceModel::where('ui_id', $userID)
                        ->where('ci_id', $transactionPair['ci_id_second'])
                        ->where('status', self::$status)
                        ->find();
                    if (empty($amount)){
                        return 'error10';//用户对应币种信息未找到或不可用
                    }
                    //买方 冻结后余额 等于 币2 减去 所需数量
                    $aft = bcsub($amount['amount'],$number);
                    if ($aft < 0){
                        $result['msg'] = lang('NOT_SUFFICIENT_FUNDS');
                        ouputJson($result['status'],$result['msg'],$result['data']);
                    }

                    //挂单冻结 币2 之前冻结数量 加上 所需数量(即冻结数量)
                    $transFrost = bcadd($amount['trans_frost'],$number);
                    //冻结前后总计
                    $aftTotal = bcadd($amount['amount'],$amount['trans_frost']);

                    $timeS = time();
                    /**限价/市价后的公共部分!后续待确认**/
                    //用户财产记录
                    $userFinance = [
                        'ui_id'               =>      $userID,
                        'ci_id'               =>      $transactionPair['ci_id_second'],
                        'amount'              =>      $aft,
                        'trans_frost'         =>      $transFrost,
                        'update_time'         =>      $timeS,
                    ];
                    //用户财产变更记录
                    $order_no = uniqid(mt_rand(self::$order_noMin,self::$order_noMax),true);
                    $userFinanceLog = [
                        'ui_id'              =>       $userID,
                        'mi_id'              =>       $transactionPair['mi_id'],
                        'ci_id'              =>       $transactionPair['ci_id_second'],
                        'bef_A'              =>       $amount['amount'],   //交易前余额
                        'bef_B'              =>       $amount['trans_frost'],  //交易前冻结
                        'bef_D'              =>       $aftTotal,           //交易前总计
                        'num'                =>       $number,           //本次变动数额
                        'type'               =>       self::$buyType,   //1是买 2是卖
                        'create_time'        =>       $timeS,
                        'aft_A'              =>       $aft,             //交易后余额
                        'aft_B'              =>       $transFrost,      //交易后冻结
                        'aft_D'              =>       $aftTotal,    //交易后总计
                        'order_no'           =>       $order_no, //交易流水号
                    ];

                    Db::startTrans();
                    try {
//                        UserFinanceModel::where('ui_id', $userFinance['ui_id'])
//                            ->where('ci_id', $userFinance['ci_id'])
//                            ->where('amount', $amount['amount'])//$amount['amount']
//                            ->where('trans_frost', $amount['trans_frost'])
//                            ->update($userFinance);
                        $param = [
                            ['field'=>'amount','type'=>'dec','val'=>$number],
                            ['field'=>'trans_frost','type'=>'inc','val'=>$number]
                        ];
                        $ret = updateUserBalance($userFinance['ui_id'],$userFinance['ci_id'],self::$action,$param);
                        if ($ret < 1){
                            $result['msg'] = 'updateUserBalance修改余额失败!';
                            return $result;
                        }

//                        UserFinanceLogModel::create($userFinanceLog);
                        $month = date('Y_m',$timeS);
                        $table = 'user_finance_log'. $month;

                        $res = Synchro::existTableFinanceLog($table);
                        if ($res !== true){
                            Log::write('数据表:'.$table.'创建失败!数据:<'.json_encode($userFinanceLog).'>','SynchroError');
                            exception('数据表:'.$table.'创建失败!数据:<'.json_encode($userFinanceLog).'>', 10006);
                        }
                        Db::table($table)
                            ->data($userFinanceLog)
                            ->insert();
                        // 提交事务
                        Db::commit();
                    } catch (\Exception $e) {
                        // 回滚事务
                        Db::rollback();
                        $result['msg'] = lang('FREEZE_DATA_FAILED');
                        ouputJson($result['status'],$result['msg'],$result['data']);
                    }
                    unset($userFinance);
                    unset($userFinanceLog);

                    //记录挂单时间
                    list($microS, $timeS) = explode(' ', microtime());
                    //$timeS时间戳的 秒 部分
                    //$microS时间戳的 微妙 部分!
                    //redis 锁 先把提交的数据 放入队列中!弹出数据(先验证锁),然后加锁!处理完成后再解锁!
                    $list = [
                        'userId'                  =>      $userID,
                        'market_c'                =>      [
                            'mi_id'                     =>      $transactionPair['mi_id'],
                            'market_ciIdFirst'          =>      $transactionPair['ci_id_first'],
                            'market_ciIdSecond'         =>      $transactionPair['ci_id_second'],
                            'market_fee'                =>      $transactionPair['fee'],
//                            'market_first_fee'          =>      $transactionPair['first_fee'],
//                            'market_second_fee'         =>      $transactionPair['second_fee'],
                        ],
                        'price_c'                 =>       $price,
                        'total_c'                 =>       $decimal,
                        'decimal_c'               =>       $decimal,
                        'fee_c'                   =>       $fee,
                        'type_c'                  =>       self::$buyType,
                        'timeS_c'                 =>       $timeS,
                        'microS_c'                =>       $microS,
                        'limit_market_c'          =>       $limitMarket,//限价/市价
//                        'userFinance_c'           =>       $userFinance,//用户财产记录
                        'order_no'                =>       $order_no, //交易流水号
                    ];

//                    if ($redis->lLen(self::$listKey.$transactionPair['mi_id']) > 15){
//                        sleep(10);
//                    }
                    $redis->rPush(self::$listKey.$transactionPair['mi_id'],json_encode($list)); //进入撮合的数据都先进消息队列.
                    unset($list);

                    //判断锁 再弹出数据
                    do { //针对问题1，使用循环

//                        Log::write('['.$key.']'.$redis->exists($key).'--'.$userID.'---'.$isLock,'tradeError');
                        $isLock = $redis->set($key, $value, ['nx', 'ex'=>$timeout]);
//                        $isLock = $redis->setnx($key,$value);
                        if ($isLock) {
//                            $redis->expire($key,$timeout);
                            if ($redis->get($key) == $value) {  //防止提前过期，误删其它请求创建的锁

                                /**开始执行内部代码**/
                                //弹出数据
                                $preData = json_decode($redis->lPop(self::$listKey.$transactionPair['mi_id']),true);
                                if (!$preData){  //如果没有数据就终止 撮合!
                                    $redis->del($key);
                                    return ;
//                                    static $t = 1;
//                                    if ($t > 20){
//                                        return ;
//                                    }
//                                    ++$t;
//                                    continue;
                                }

                                //>>2.2.(挂买单)启动撮合$this->doTrade
                                $result = $this->doTrade($preData);
                                switch ($result):
                                    case 'Exception':
                                        //出现异常!通知管理员

                                        break;
                                    case 'DataException':
                                        //出现撮合过程 Redis事务 异常!通知管理员

                                        break;
                                    default:
//                                        if (!empty($transactionPair['rule_msg'])){
//                                            $result['msg'] = $transactionPair['rule_msg'];
//                                        }
                                        $result['msg'] = lang('SUCCESS');
                                        echo $this->outputJson($result);
                                        break;
                                endswitch;
                                /**结束执行内部代码**/

                                $redis->del($key);
                                break;//执行成功删除key并跳出循环
                            }
                        } else {
                            usleep(self::$usleep); //睡眠，降低抢锁频率，缓解redis压力，针对问题2
                        }
                    } while(!$isLock);//如果没有获取到锁 usleep(2500):表示每秒循环400次
                    break;
                case 2:
                    $result['msg'] = 'Market price is not yet open';
                    echo $this->outputJson($result);
                    break;
                    //市价交易,余额充足的前提下,直接撮合他的所有数量.
                    //问题x.如何在市价进来的时候就锁定 对应的挂单数据 防止被 限价的交易?
                    //问题y.如何在市价进来的时候 读取到的 数据保证 不是正在被限价撮合?
                    do {//市价交易
                        //不存在的键时,在设置锁,并设置过期时间
//                        if ($redis->exists($key) == FALSE){
//                            $isLock = $redis->set($key, $value, $timeout);
//                        }
                        $isLock = $redis->set($key, $value, ['nx', 'ex'=>$timeout]);
//                        $isLock = $redis->setnx($key, $value);
                        if ($isLock) {
//                            $redis->expire($key, $timeout);
                            if ($redis->get($key) == $value) {//防止提前过期，误删其它请求创建的锁
                                /**开始执行内部代码**/
                                //读取数据判定余额充足 (买就是判断2号币余额)
                                $re = $this->checkMarketBalance($decimal, $market, 1, $userID,
                                    $transactionPair['ci_id_second']);
                                if ($re['result'] === false){
                                    $result['msg'] = lang('NOT_SUFFICIENT_FUNDS');
                                    ouputJson($result['status'],$result['msg'],$result['data']);
                                }
                                $re['data'];//这个就是直接进行撮合数据[如果没有挂单数据,市价买卖 怎么挂单??]
                                $re['number'];//这个就是市价撮合数据的数量
                                list($microS, $timeS) = explode(' ', microtime());
                                //弹出数量的数据进行撮合
                                $order_no = uniqid($timeS,true);
                                $buyData = [
                                    'ui_id'                 =>    $userID,
                                    'mi_id'                 =>    $market,
                                    'type'                  =>    self::$buyType,     //1是买 2是卖
                                    'price'                 =>    $price,
                                    'total'                 =>    $decimal,   //挂单总数
                                    'decimal'               =>    $decimal,   //剩余数量
                                    'fee'                   =>    $fee,   //交易后获得币种 的手续费
                                    'create_time'           =>    $timeS,
                                    'microS'                =>    $microS,
                                    'status'                =>    self::$tradeStatus1,//1:交易中2:已完成3:已撤销4:异常
                                    'order_no'              =>    $order_no,//交易流水号
                                    'limit_market'          =>    $limitMarket,//限价/市价
                                ]; //购买的市价数据
                                $market_c = [
                                    'market_ciIdFirst'          =>      $transactionPair['ci_id_first'],
                                    'market_ciIdSecond'         =>      $transactionPair['ci_id_second'],
                                ]; //交易市场的2个币种
                                $ret = $this->marketBuy($re['data'],$re['key'],$buyData,$market_c);
                                //撮合完毕需要 终止循环 Redis锁 (即下面的删除锁后的break)
                                /**结束执行内部代码**/

                                $redis->del($key);
                                break;//执行成功删除key并终止循环
                            }
                        } else {
                            usleep(self::$usleep); //睡眠，降低抢锁频率，缓解redis压力，针对问题2
                        }
                    } while(!$isLock);
                    break;
            }
        }catch (Exception $e){
            $error = $this->myGetTrace($e);
            Log::write($error,'tradeError');
            $result['msg'] = '当前挂单请求失败!请稍后重新尝试!';
            ouputJson($result['status'],$result['msg'],$result['data']);
        }

    }

    /**
     * 用户挂单提交-(卖)
     * @param $limitMarket          :限价1/市价2
     * @param $price                :价格
     * @param $decimal              :数量
     * @param $market               :交易市场ID
     * @param string $payPwd        :交易密码
     * @param int $type             :买1/卖2
     * @return string|\think\response\Json|void
     */
    public function upTradeSell($limitMarket, $price, $decimal, $market, $payPwd='')
    {
        try {
            set_time_limit(self::$setTimeLimit);
            $result = config('code.error');
            if ($price <= 0){
                $result['msg'] = lang('ERROR_NUMBER');
                ouputJson($result['status'],$result['msg'],$result['data']);
            }
            if (!preg_match('/^(1|2)$/', (string)$limitMarket)) {
                $result['msg'] = lang('PARAM_ERROR').'!:'.$limitMarket;
                ouputJson($result['status'],$result['msg'],$result['data']);
            }
            if (!preg_match('/^[1-9]+\d?$/', (string)$market)) {
                $result['msg'] = lang('PARAM_ERROR').':'.$market;
                ouputJson($result['status'],$result['msg'],$result['data']);
            }
            $onOff = Swoole::checkMarketStatus($market);
            if ($onOff === false){
                $result['msg'] = lang('NETWORK_IS_BUSY');
                ouputJson($result['status'],$result['msg'],$result['data']);
            }

            $userID = $this->uid;
            /**撮合交易的流程!!!**/
            //>>1.判断交易密码!有则匹配,没有不管!
            $tradeType = Db::table('user_info')->where('ui_id', $userID)->value('trade_type');
            if (empty($tradeType)){
                return 'error0-';//用户交易密码提示标记未找到
            }
            if ($tradeType == 1){
                $check = $this->checkPayPwd($payPwd);
                if ($check === false){
                    $result['msg'] = lang('TRANS_PWD_ERROR');
                    ouputJson($result['status'],$result['msg'],$result['data']);
                }
            }

            $bc = bcscale(self::BCPRECISEDIGITS); //bc算法只能精算到小数点13位!
            if (!$bc)
                return 'error1-';//bc默认保留位数失败!

            $redis = $this->redis;
            //分割交易对
            $transactionPair = json_decode($redis->hGet(self::$marketKey,$market),true);
            if (empty($transactionPair)){
                $transactionPair = Db::table('market_info')
                    ->where('mi_id', $market)
                    ->where('status', self::$status)
                    ->find();
                if (empty($transactionPair)){
                    return 'error2-';//数据库交易市场信息未查到!
                }
                $redis->hSet(self::$marketKey,$market,json_encode($transactionPair));
            }
            if ($transactionPair['status'] == 0){
                return 'Disable';//该交易市场不可用
            }
            if ($decimal < $transactionPair['amount_input_min']){
                $result['msg'] = lang('MINIMUM_NUMBER').$transactionPair['amount_input_min'];
                ouputJson($result['status'],$result['msg'],$result['data']);
            }
            if (!preg_match('/^[0-9]+(.[0-9]{1,'.$transactionPair['amount_bit'].'})?$/', (string)$decimal)) {
                $result['msg'] = lang('LMT_DECIMAL_BIT').$transactionPair['amount_bit'];
                ouputJson($result['status'],$result['msg'],$result['data']);
            }
            if (!preg_match('/^[0-9]+(.[0-9]{1,'.$transactionPair['price_bit'].'})?$/', (string)$price)) {
                $result['msg'] = lang('LMT_PRICE_BIT').$transactionPair['price_bit'];
                ouputJson($result['status'],$result['msg'],$result['data']);
            }

            //判定队列长度 超过50 则提示繁忙
            $restrict = self::$restrict;
            $length = $redis->lLen(self::$listKey.$transactionPair['mi_id']);
            if ($length >= $restrict){
                $result['msg'] = lang('PLEASE_TRY_LATER');
                ouputJson($result['status'],$result['msg'],$result['data']);
            }

            //币种手续费(卖:卖币1得币2,显示币2的手续费)
            $coinInfo = json_decode($redis->hGet(self::$coinKey,$transactionPair['ci_id_second']),true);
            if (empty($coinInfo)){
                $coinInfo = Db::table('coin_info')
                    ->field('show_fee,fee')
                    ->where('ci_id', $transactionPair['ci_id_second'])
                    ->where('status', self::$status)
                    ->find();
                if (empty($coinInfo)){
                    return 'error3-';//数据库币2手续费信息未查到!
                }
                $redis->hSet(self::$coinKey,$transactionPair['ci_id_second'],json_encode($coinInfo));
            }
            //主动方 用户 手续费!
            $userGroupFee = json_decode($redis->hGet(self::$uGroupKey,$userID),true);
            if (empty($userGroupFee)) {
                $userGroupFee = Db::table('user_group')->alias('ug')
                    ->join('group_info gi', 'ug.gi_id = gi.gi_id', 'LEFT')
                    ->where('ug.ui_id', $userID)
                    ->where('gi.status', self::$status)
                    ->column('gi.fee');
                if (empty($userGroupFee)){
                    return 'error4-';//数据库用户组手续费信息未查到!
                }
                $redis->hSet(self::$uGroupKey,$userID,json_encode($userGroupFee));
            }
            $fee = min(array_merge((array)$userGroupFee,(array)$coinInfo['fee'],(array)$transactionPair['fee']));

            //判断币种信息是否存在
            $where = [
                ['ci_id','in',[$transactionPair['ci_id_first'],$transactionPair['ci_id_second']]],
            ];
            $userAmount = UserFinanceModel::where('ui_id', $userID)
                ->where($where)
                ->where('status', self::$status)
                ->select();
            if (count($userAmount) != 2){
                return 'error5-';//数据库用户当前交易市场对应的2个币种信息缺失!
            }
            set_user(self::$userType,0,0,$transactionPair['ci_id_first']);
            set_user(self::$userType,0,0,$transactionPair['ci_id_second']);

            $timeout = self::$timeout;
            $key = 'str_room_lock'.$transactionPair['mi_id'];
            $roomId = uniqid(mt_rand(self::$roomIdMin,self::$roomIdMax),true);
            $value = 'room_'.$roomId;
            $isLock = null;
            //>>2.判断限价还是市价
            switch ($limitMarket){
                case 1:
                    //定义一个默认成交价
                    $default = $transactionPair['last_price']??self::$firstTrade;
                    //限价卖出最高为成交价上浮$transactionPair['price_sell_max']/100

                    $sellLimitPrice = bcadd(1,$transactionPair['price_sell_max']/100,$transactionPair['price_bit']);
                    //>>2.1.限价卖,输入的价格$price不能高于最后成交价的$sellLimitPrice倍
                    //获取最后买单的价格
                    $lastRecord = json_decode($redis->get('str_last_record_market_'.$transactionPair['mi_id']),true)['price']??$default;
                    $sellLimitPrice = bcmul($lastRecord,$sellLimitPrice,$transactionPair['price_bit']);
                    if ($price > $sellLimitPrice){
                        $result['msg'] = lang('LMT_PRICE_SELL').$sellLimitPrice;
                        ouputJson($result['status'],$result['msg'],$result['data']);
                    }

                    $sellLimitPrice = bcsub(1,$transactionPair['price_sell_min']/100,$transactionPair['price_bit']);
                    $sellLimitPrice = bcmul($lastRecord,$sellLimitPrice,$transactionPair['price_bit']);
                    if ($price < $sellLimitPrice){
                        $result['msg'] = lang('LMT_PRICE_SELL1').$sellLimitPrice;
                        ouputJson($result['status'],$result['msg'],$result['data']);
                    }

                    //卖出数量为 $decimal;
                    //>>2.1.1.$decimal卖出数量不能高于自己的账户余额
                    //直接查询数据库,防止余额有变动
                    $amount = UserFinanceModel::where('ui_id', $userID)
                        ->where('ci_id', $transactionPair['ci_id_first'])
                        ->where('status', self::$status)
                        ->find();
                    if (empty($amount)){
                        return 'error10-';//用户对应币种信息未找到或不可用
                    }
                    //卖方冻结后余额 等于 币1 减去 所需数量
                    $aft = bcsub($amount['amount'],$decimal);
                    if ($aft < 0){
                        $result['msg'] = lang('SUFFICIENT_FUNDS');
                        ouputJson($result['status'],$result['msg'],$result['data']);
                    }

                    //挂单冻结 币1 之前冻结数量 加上 所需数量(即冻结数量)
                    $transFrost = bcadd($amount['trans_frost'],$decimal);
                    //冻结前后总计
                    $aftTotal = bcadd($amount['amount'],$amount['trans_frost']);

                    $timeS = time();
                    /**限价/市价后的公共部分!后续待确认**/
                    //用户财产记录
                    $userFinance = [
                        'ui_id'               =>      $userID,
                        'ci_id'               =>      $transactionPair['ci_id_first'],
                        'amount'              =>      $aft,
                        'trans_frost'         =>      $transFrost,
                        'update_time'         =>      $timeS,
                    ];
                    //用户财产变更记录
                    $order_no = uniqid(mt_rand(self::$order_noMin,self::$order_noMax),true);
                    $userFinanceLog = [
                        'ui_id'              =>      $userID,
                        'mi_id'              =>      $transactionPair['mi_id'],
                        'ci_id'              =>      $transactionPair['ci_id_first'],
                        'bef_A'              =>      $amount['amount'],   //交易前余额
                        'bef_B'              =>      $amount['trans_frost'],  //交易前冻结
                        'bef_D'              =>      $aftTotal,           //交易前总计
                        'num'                =>      $decimal,           //本次变动数额
                        'type'               =>      self::$sellType,   //1是买 2是卖
                        'create_time'        =>      $timeS,
                        'aft_A'              =>      $aft,             //交易后余额
                        'aft_B'              =>      $transFrost,      //交易后冻结
                        'aft_D'              =>      $aftTotal,    //交易后总计
                        'order_no'           =>      $order_no, //交易流水号
                    ];

                    Db::startTrans();
                    try {
//                        UserFinanceModel::where('ui_id', $userFinance['ui_id'])
//                            ->where('ci_id', $userFinance['ci_id'])
//                            ->where('amount', $amount['amount'])//$amount['amount']
//                            ->where('trans_frost', $amount['trans_frost'])
//                            ->update($userFinance);
                        $param = [
                            ['field'=>'amount','type'=>'dec','val'=>$decimal],
                            ['field'=>'trans_frost','type'=>'inc','val'=>$decimal]
                        ];
                        $ret = updateUserBalance($userFinance['ui_id'],$userFinance['ci_id'],self::$action,$param);
                        if ($ret < 1){
                            $result['msg'] = 'updateUserBalance修改余额失败!';
                            return $result;
                        }

                        $month = date('Y_m',$timeS);
                        $table = 'user_finance_log'. $month;

                        $res = Synchro::existTableFinanceLog($table);
                        if ($res !== true){
                            Log::write('数据表:'.$table.'创建失败!数据:<'.json_encode($userFinanceLog).'>','SynchroError');
                            exception('数据表:'.$table.'创建失败!数据:<'.json_encode($userFinanceLog).'>', 10006);
                        }
                        Db::table($table)
                            ->data($userFinanceLog)
                            ->insert();
                        // 提交事务
                        Db::commit();
                    } catch (\Exception $e) {
                        // 回滚事务
                        Db::rollback();
                        $result['msg'] = lang('FREEZE_DATA_FAILED');
                        ouputJson($result['status'],$result['msg'],$result['data']);
                    }
                    unset($userFinance);
                    unset($userFinanceLog);

                    list($microS, $timeS) = explode(' ', microtime());
                    $list = [
                        'userId'                  =>      $userID,
                        'market_c'                =>      [
                            'mi_id'                     =>      $transactionPair['mi_id'],
                            'market_ciIdFirst'          =>      $transactionPair['ci_id_first'],
                            'market_ciIdSecond'         =>      $transactionPair['ci_id_second'],
                            'market_fee'                =>      $transactionPair['fee'],
                        ],
                        'price_c'                 =>       $price,
                        'total_c'                 =>       $decimal,
                        'decimal_c'               =>       $decimal,
                        'fee_c'                   =>       $fee,
                        'type_c'                  =>       self::$sellType,
                        'timeS_c'                 =>       $timeS,
                        'microS_c'                =>       $microS,
                        'limit_market_c'          =>       $limitMarket,//限价/市价
                        'order_no'                =>       $order_no, //交易流水号
                    ];

                    $redis->rPush(self::$listKey.$transactionPair['mi_id'],json_encode($list)); //进入撮合的数据都先进消息队列.
                    unset($list);

                    //判断锁 再弹出数据
                    do {  //针对问题1，使用循环

                        //不存在的键时,在设置锁,并设置过期时间
                        $isLock = $redis->set($key, $value, ['nx', 'ex'=>$timeout]);
                        if ($isLock) {
                            if ($redis->get($key) == $value) {  //防止提前过期，误删其它请求创建的锁

                                /**开始执行内部代码**/
                                //弹出数据
                                $preData = json_decode($redis->lPop(self::$listKey.$transactionPair['mi_id']),true);
                                if (!$preData){  //如果没有数据就终止 撮合!
                                    $redis->del($key);
                                    return ;
                                }
                                //>>2.2.(挂卖单)启动撮合$this->doTrade
                                $result = $this->doTrade($preData);
                                if ($result == 'Exception'){
                                    //出现代码异常! 通知管理员

                                }elseif ($result == 'DataException'){
                                    //出现撮合过程 Redis事务 异常!通知管理员

                                }else{
//                                    if (!empty($transactionPair['rule_msg'])){
//                                        $result['msg'] = $transactionPair['rule_msg'];
//                                    }
                                    $result['msg'] = lang('SUCCESS');
                                    echo $this->outputJson($result);
                                }
                                /**结束执行内部代码**/

                                $redis->del($key);
                                break;//执行成功删除key并跳出循环
                            }
                        } else {
                            usleep(self::$usleep); //睡眠，降低抢锁频率，缓解redis压力，针对问题2
                        }
                    } while(!$isLock);
                    break;
                case 2:
                    $result['msg'] = 'Market price is not yet open';
                    echo $this->outputJson($result);
                    break;
                    do {//市价交易
                        //不存在的键时,在设置锁,并设置过期时间
                        $isLock = $redis->set($key, $value, ['nx', 'ex'=>$timeout]);
                        if ($isLock) {
                            if ($redis->get($key) == $value) {//防止提前过期，误删其它请求创建的锁
                                /**开始执行内部代码**/
                                //读取数据判定余额充足 (买就是判断2号币余额)
                                $re = $this->checkMarketBalance($decimal, $market, 1, $userID,
                                    $transactionPair['ci_id_second']);
                                if ($re['result'] === false){
                                    $result['msg'] = lang('SUFFICIENT_FUNDS');
                                    ouputJson($result['status'],$result['msg'],$result['data']);
                                }
                                $re['data'];//这个就是直接进行撮合数据[如果没有挂单数据,市价买卖 怎么挂单??]
                                $re['number'];//这个就是市价撮合数据的数量
                                list($microS, $timeS) = explode(' ', microtime());
                                //弹出数量的数据进行撮合
                                $order_no = uniqid($timeS,true);
                                $sellData = [
                                    'ui_id'                 =>    $userID,
                                    'mi_id'                 =>    $market,
                                    'type'                  =>    self::$sellType,     //1是买 2是卖
                                    'price'                 =>    $price,
                                    'total'                 =>    $decimal,   //挂单总数
                                    'decimal'               =>    $decimal,   //剩余数量
                                    'fee'                   =>    $fee,   //交易后获得币种 的手续费
                                    'create_time'           =>    $timeS,
                                    'microS'                =>    $microS,
                                    'status'                =>    self::$tradeStatus1,//1:交易中2:已完成3:已撤销4:异常
                                    'order_no'              =>    $order_no,//交易流水号
                                    'limit_market'          =>    $limitMarket,//限价/市价
                                ]; //购买的市价数据
                                $market_c = [
                                    'market_ciIdFirst'          =>      $transactionPair['ci_id_first'],
                                    'market_ciIdSecond'         =>      $transactionPair['ci_id_second'],
                                ]; //交易市场的2个币种
                                $ret = $this->marketSell($re['data'],$re['key'],$sellData,$market_c);
                                //撮合完毕需要 终止循环 Redis锁 (即下面的删除锁后的break)
                                /**结束执行内部代码**/

                                $redis->del($key);
                                break;//执行成功删除key并终止循环
                            }
                        } else {
                            usleep(self::$usleep); //睡眠，降低抢锁频率，缓解redis压力，针对问题2
                        }
                    } while(!$isLock);
                    break;
            }
        }catch (Exception $e){
            $error = $this->myGetTrace($e);
            Log::write($error,'tradeError');
            $result['msg'] = '当前挂单请求失败!请稍后重新尝试!';
            ouputJson($result['status'],$result['msg'],$result['data']);
        }

    }


    /**
     * 验证交易密码
     * @param $payPwd               :   输入的交易密码
     * @param Request $request      :   获取登录用户
     * @return bool                 :   返回验证结果
     */
    protected function checkPayPwd($payPwd)
    {
        $userInfo = $this->userinfo; //继承AuthBase类 获取登录用户ID

        //>>1.登录用户取出用户交易密码 与 他输入的密码加盐加密 进行比对
        if (empty($userInfo['trade_pwd'])){
            return true;
        }
        if (Hash::check($payPwd,$userInfo['trade_pwd'],'md5',['salt' => $userInfo['trade_salt']])){
            return true;
        }
        return false;
    }

    /**
     * 对挂单的数据进行撮合的方法!
     * @param $type_c           : 挂的是买单为1 还是 卖单为2!
     * @param $limitMarket      : 限/市价
     * @param $market_c         : 交易市场的信息!
     * @param $decimal          : 挂单的数量!
     * @param $timeS            : 挂单的时间戳!
     * @param $microS           : 挂单的时间微秒部分!
     * @param $userId           : 当前登录的用户!
     * @param $userFinance      : 用户余额
     * @param null $price       : 价格
     * @return string
     */
    protected function doTrade($preData)
    {
        try {
            $redis = $this->redis;
            $key = self::$hKey .$preData['market_c']['mi_id'];//$preData['market_c']['market_ciIdFirst'] .'_' .$preData['market_c']['market_ciIdSecond'];
            switch ($preData['type_c']) {
                case self::$buyType:
                    //>>1.挂买单,则查询现有挂起的卖单数据!进行撮合!
                    $sells = $redis->hVals($key .self::$hKeySell);//返回的是数组,每个数组元素是个json字符串
                    /**难点重点,数组遍历,获取价格时间,进行排序array_multisort,**/
                    $effectiveSells = [];
                    if ($sells){
                        foreach ($sells as $k => $row) {
    //                if ($row['state'] == '已撤销'){
    //                    unset($row);
    //                } //把已成交的和已撤销的数据 放在另外一个Redis中
                            $row1 = json_decode($row,true);
    //                        if ($row1['price'] <= $preData['price_c'] && $row1['ui_id'] != $preData['userId']){
                            if ($row1['price'] <= $preData['price_c']){
                                $effectiveSells[] = $row1;
                                $sortPrice[$k] = $row1['price'];
                                $sortTimeS[$k] = $row1['create_time'];
                                $sortMicroS[$k] = $row1['microS'];
                            }
                        }
                        if ($effectiveSells){
                            array_multisort($sortPrice, SORT_ASC,
                                $sortTimeS, SORT_ASC,
                                $sortMicroS, SORT_ASC,
                                $effectiveSells);
                        }
                        unset($sells,$sortPrice,$sortTimeS,$sortMicroS);
                    }
                    $res = $this->buyOneByOne($effectiveSells, $key, $preData);
                    return $res;

                    break;
                case self::$sellType:
                    //>>2.挂卖单,则查询现有挂起的买单数据!进行撮合!
                    $Buys = $redis->hVals($key .self::$hKeyBuy);//返回的是数组,每个数组元素是个json字符串
                    /**难点重点,数组遍历,获取价格时间,进行排序array_multisort,**/
                    $effectiveBuys = [];
                    if ($Buys){
                        foreach ($Buys as $k => $row) {
                            $row1 = json_decode($row,true);
//                        if ($row1['price'] >= $preData['price_c'] && $row1['ui_id'] != $preData['userId']){
                            if ($row1['price'] >= $preData['price_c']){
                                $effectiveBuys[] = $row1;
                                $sortPrice[$k] = $row1['price'];
                                $sortTimeS[$k] = $row1['create_time'];
                                $sortMicroS[$k] = $row1['microS'];
                            }
                        }
                        if ($effectiveBuys){
                            array_multisort($sortPrice, SORT_DESC,
                                $sortTimeS, SORT_ASC,
                                $sortMicroS, SORT_ASC,
                                $effectiveBuys);
                        }
                        unset($Buys,$sortPrice,$sortTimeS,$sortMicroS);
                    }

                    $res = $this->sellOneByOne($effectiveBuys, $key, $preData);
                    return $res;

                    break;
            }
        } catch (Exception $e) {
            $error = $this->myGetTrace($e);
            Log::write($error,'tradeError');
//            trace('日志信息','info');
//            Log::close();
            return 'Exception';
        }

    }


    /**
     * 撮合方法!记录数据
     * @param $effectiveSells
     * @param $key
     * @param $recursive        : 0:外层循环 | 1:更优买单递归 | 2:更优卖单递归
     * @param $preData          : extract($preData) 得到下列参数
     * @param $limitMarket
     * @param $market_c
     * @param $decimal
     * @param $timeS
     * @param $microS
     * @param $userId
     * @param $userFinance
     * @param $price
     * @return string
     */
    protected function buyOneByOne(&$effectiveSells, $key, $preData, $recursive=0,$deep=0)
    {
        try {
//            Log::write('buyOneByOne['.json_encode($effectiveSells).']'.json_encode($preData).'['.$recursive.':'.$deep.']','tradeError');
            $redis = $this->redis;
            extract($preData);
            $userId         = $userId;
            $timeS          = $timeS_c;
            $microS         = $microS_c;
            $market_c       = $market_c;
            $price          = $price_c;
            $total          = $total_c;
            $decimal        = $decimal_c;
            $fee            = $fee_c;
            $type           = $type_c;
            $limitMarket    = $limit_market_c;
            $order_no       = $order_no;

//            $hField = $userId .'_' .$timeS .'_' .$microS;
            $hField = $order_no;
            bcscale(self::BCPRECISEDIGITS);

            $buyData = [
                'ui_id'                 =>    $userId,
                'mi_id'                 =>    $market_c['mi_id'],
                'type'                  =>    $type,     //1是买 2是卖
                'price'                 =>    $price,
                'total'                 =>    $total,   //挂单总数
                'decimal'               =>    $decimal,   //剩余数量
                'fee'                   =>    $fee,   //交易后获得币种 的手续费
                'create_time'           =>    $timeS,
                'update_time'           =>    $timeS,
                'microS'                =>    $microS,
                'status'                =>    self::$tradeStatus1,//1:交易中2:已完成3:已撤销4:异常
                'order_no'              =>    $order_no,//交易流水号
                'limit_market'          =>    $limitMarket,//限价/市价
            ];
            if ($recursive == 2){
                $buyData['type'] = self::$sellType;
            }
            if (empty($effectiveSells)){    //如果没有匹配的撮合数据,就挂买单! 进入撮合就已经冻结挂单财产了!
                $redis->hSet($key .self::$hKeyBuy,$hField,json_encode($buyData));
//                Log::write("$microS--0--$hField--0--".json_encode($buyData),'notice');
                //屏蔽异步,走同步挂单
                $r = $this->syncMysql_ld($buyData,$timeS);
                if ($r == false){
                    $buyData['status'] = 5;
                    Db::name('exception_trade')->insert($buyData);
                    $result = config('code.error');
//                    return '没有可撮合!挂单写入数据库失败!';
                    return $result;
//                    $redis->hSet('hash_exception_data', $hField, json_encode($buyData));
//                    exception('没有可撮合!挂单写入数据库失败!', 5001);
                }
//                $redis->publish('ghm',json_encode($buyData));
                unset($buyData);

                $result = config('code.success');
//                $result['msg'] = '没有可撮合数据,挂买单成功!';
                return $result;
            }

            /** //与上一笔成交价 做对比!!
            //成交价格已上一次成交的价格来衡量!在买价和卖价之间 用上一次成交价!否则 以上下线为准!
                   $default = 5930;
            $lastRecord = json_decode($redis->get('str_last_record_market_'.$market_c['mi_id']))['price']??$default;*/
            $i = 0;
//            $count = count($effectiveSells);
            $end = end($effectiveSells);
            $ret0 = $this->swooleClientNew();
            foreach ($effectiveSells as $k => &$value){
//                $sellField = $value['ui_id'] . '_' . $value['create_time'] . '_' . $value['microS'];
                $sellField = $value['order_no'];
                if ($decimal <= 0){
//                    if ($count-$i == 1){  //if (strcmp($end,$value) === 0){
//                        break;
//                    }
                    if ($end === $value){
                        break;
                    }
                    continue;
                }
                //判断如果挂单被取消,则continue
                if ($recursive != 2){
                    $tradeData = $redis->hExists($key . self::$hKeySell, $sellField);
                    if (!$tradeData){
//                        $count -= 1;
                        if ($end === $value){
                            $redis->hSet($key .self::$hKeyBuy,$hField,json_encode($buyData));
                            $r = $this->syncMysql_ld($buyData,$timeS);
                            if ($r == false){
                                $buyData['status'] = 5;
                                Db::name('exception_trade')->insert($buyData);
                                $result = config('code.error');
                                return $result;
                            }
                            unset($buyData);
                            $result = config('code.success');
                            return $result;
                        }
                        unset($effectiveSells[$k]);
                        continue;
                    }
                }

                //设置 被动方交易流水号
                $wkey = 'str_'.$value['order_no'];
                $redis->incr($wkey);

                $redis->watch($wkey);
                $redis->multi();
                try {
                    if ($value['decimal'] <= $decimal){
                        $lol = 0;
                        //以一笔撮合成功!删除数据
                        if ($recursive != 2){
                            $redis->hDel($key . self::$hKeySell, $sellField);
                        }

                        /**                if ($limitMarket == 1){
                        if ($lastRecord >= $price){
                        $value['price'] = $price;
                        }elseif ($lastRecord >= $value['price'] && $lastRecord <= $price){
                        $value['price'] = $lastRecord;
                        }
                        }*/

                        $finishData = $value; //撮合掉的挂单数据
                        unset($effectiveSells[$k]);

                        $isRecursive = 'yes'; //是否继续循环
                        $decimal = bcsub($decimal,$value['decimal'],self::BCPRECISEDIGITS);
                        $buyData['decimal'] = $decimal;

//                        if ($count-$i == 1){
//                            $isRecursive = 'no'; //是否继续循环
//                        }
                        if ($end === $value){
                            $isRecursive = 'no';
                        }
                        if ($decimal == 0){
                            $isRecursive = 'no'; //是否继续循环
                            $buyData['status'] = self::$tradeStatus2;
                        }
                        //撮合数量
                        $pairFirst = $value['decimal'];

                        $remainData = $buyData; //撮合后剩下的挂单数据
                    }else{
                        $lol = bcsub($value['decimal'], $decimal, self::BCPRECISEDIGITS);
                        $effectiveSells[$k]['decimal'] = $lol;
                        $value['decimal'] = $lol;

                        if ($recursive != 2){
                            $value['update_time'] = $buyData['create_time'];
                            $redis->hSet($key . self::$hKeySell, $sellField, json_encode($value));
//                            Log::write($value['microS']."--1--$sellField--1--".json_encode($value),'notice');
                        }

                        /*                if ($limitMarket == 1){
                                            if ($lastRecord >= $price){
                                                $buyData['price'] = $price;
                                            }elseif ($lastRecord >= $value['price'] && $lastRecord <= $price){
                                                $buyData['price'] = $lastRecord;
                                            }
                                        }*/
                        $finishData = $buyData; //撮合掉的挂单数据
                        $isRecursive = 'no';

                        //撮合数量
                        $pairFirst = $decimal; //成交数量
                        $decimal   = 0; //剩余数量为0,为了后面的循环再用到$decimal
                        $remainData = $value; //撮合后剩下的挂单数据
                    }
                    //成交价,时间优先
                    $pairPrice = $value['price'];

                    //价格和数量匹配后 对数据进行修改! 同时添加记录
                    $finishData['status'] = self::$tradeStatus2;   //1:交易中  2:已完成  3:已撤销
                    $finishData['decimal'] = 0;   //交易完成,剩余数量则为0

                    //买卖双方手续费
                    $feeAccountB = bcmul($pairFirst,$fee/100,self::BCPRECISEDIGITS);
                    $pairSecond = bcmul($pairPrice,$pairFirst,self::BCPRECISEDIGITS);
                    $feeAccountS = bcmul($pairSecond,$value['fee']/100,self::BCPRECISEDIGITS);

                    //1还需要成交记录,字段(发起方,接收方,交易类型[1:买/2:卖],交易市场,单价,数量,金额,手续费,时间[2个字段:时间戳/微秒部分],交易流水号)根据交易市场分成多个HASH,来存储!
                    $recordOfTransactionData = [
                        'mt_order_ui_id'        =>    $userId,
                        'mt_peer_ui_id'         =>    $value['ui_id'],
                        'type'                  =>    self::$buyType,
                        'mi_id'                 =>    $finishData['mi_id'],
                        'price'                 =>    $pairPrice,
                        'decimal'               =>    $pairFirst,
                        'amount'                =>    $pairSecond,
                        'buy_fee'               =>    $feeAccountB,   //手续费暂定为0
                        'sell_fee'              =>    $feeAccountS,
                        'create_time'           =>    $timeS,
//                        'microS'                =>    $microS,
                        'order_no'              =>    $order_no,//交易流水号
                        'peer_order_no'         =>    $value['order_no'],//对手方流水号
                    ];
                    if ($recursive == 2){
                        $recordOfTransactionData['type'] = self::$sellType;
                        $isRecursive = 'no';
//                        $price = $value['price'];
                    }
                    if (($buyData['create_time'] < $value['create_time']) || (($buyData['create_time'] == $value['create_time']) && ($buyData['microS'] < $value['microS']))){
                        $price = $value['price'];
                        $pairPrice = $buyData['price'];
                        $pairSecond = bcmul($pairPrice,$pairFirst,self::BCPRECISEDIGITS);
                        $feeAccountS = bcmul($pairSecond,$value['fee']/100,self::BCPRECISEDIGITS);
                        $recordOfTransactionData = [
                            'mt_order_ui_id'        =>    $value['ui_id'],
                            'mt_peer_ui_id'         =>    $userId,
                            'type'                  =>    $value['type'],
                            'mi_id'                 =>    $finishData['mi_id'],
                            'price'                 =>    $pairPrice,
                            'decimal'               =>    $pairFirst,
                            'amount'                =>    $pairSecond,
                            'buy_fee'               =>    $feeAccountS,   //手续费暂定为0
                            'sell_fee'              =>    $feeAccountB,
                            'create_time'           =>    $value['create_time'],
//                            'microS'                =>    $value['microS'],
                            'order_no'              =>    $value['order_no'],//交易流水号
                            'peer_order_no'         =>    $order_no,//对手方流水号
                        ];
                    }
                    //1.1添加一条最后一条成交记录:$redis->set('Final_matching_order_transaction_record','$成交记录');
                    $redis->set('str_last_record_market_'.$market_c['mi_id'], json_encode($recordOfTransactionData));
                }catch (Exception $e){
                    $redis->discard();
                    $error = $this->myGetTrace($e);
                    Log::write($error.'!!!新进买单-写入-异常数据表:exception_trade','tradeError');
                    //记录$buyData到异常记录
                    $buyData['status'] = 4;
                    Db::name('exception_trade')->insert($buyData);

                    return 'DataException';
                }

                $redis->exec();
                $redis->del($wkey);
                /**这里开启swoole异步 同步撮合后数据到数据库**/
                $finishData['update_time'] = $recordOfTransactionData['create_time'];
                $remainData['update_time'] = $recordOfTransactionData['create_time'];
                $restingOrderData = [
                    'type'      =>  'trade',
                    'data'      =>  [
                        'marketTradeLog'    =>   $recordOfTransactionData,
                        'marketTradeF'      =>   $finishData,
                        'marketTradeR'      =>   $remainData,
                        'other'             =>   [
                            'price'=>$price,
                            'coin1'=>$market_c['market_ciIdFirst'],
                            'coin2'=>$market_c['market_ciIdSecond'],
                        ],
                    ],
                ];
                $ret = $this->swooleClientSend($restingOrderData);
                //如果异步失败,走同步数据
                if ($ret == false){
                    $this->syncMysql_td($restingOrderData['data']);
                }

                //发布成交记录 给 继伟
                $redis->publish('ghm',json_encode($recordOfTransactionData));
                unset($recordOfTransactionData,$finishData,$restingOrderData);

                ++$i;

//                $redis->set('str_decimal',"$decimal-$isRecursive-$count-$i-$deep-".json_encode($preData));
                switch ($isRecursive) {
                    case 'yes':
                        /**读取撮合期间 是否有新的 撮合数据进来!**/
                        //获取 消息队列的数据
                        //$redis->rpush();右侧推入数据,使用lRange()获取的数据遍历出来第一个就是最先推入的数据
                        $xiaoxi_duilie = null;
                        $deeps = 9;
                        $getDeep = $redis->get('str_deepM'.$market_c['mi_id'])?$redis->get('str_deepM'.$market_c['mi_id']):0;
                        if ($getDeep < $deeps){
                            $xiaoxi_duilie = $redis->lRange(self::$listKey . $market_c['mi_id'], 0, -1);
//                        static $tally = 0; //内层撮合次数
//                        static $ableCount = 1; //内层剩余可撮合的挂单数量
                            if ($xiaoxi_duilie) {

                                foreach ($xiaoxi_duilie as $va1) {
                                    $getDeep = $redis->get('str_deepM'.$market_c['mi_id']);
                                    if ($getDeep && $getDeep >= $deeps){
                                        break;
                                    }
                                    $va = json_decode($va1,true);
                                    //挂买单 //撮合期间 新进来的挂买单 更高价格就先撮合新进的买单

                                    if ($va['type_c'] == 1 && bcsub($va['price_c'], $price, self::BCPRECISEDIGITS) > 0) {
                                        $variable = 'hash_remain_'.$market_c['mi_id'];
                                        $redis->hSet($variable,$deep,json_encode($remainData));
                                        /**这里需要判断这个是消息队列的数据,如果有剩余就需要写入消息队列**/
                                        $redis->lRem(self::$listKey . $market_c['mi_id'], $va1, 0);
//                                    $ableCount = count($effectiveSells);
                                        $redis->incr('str_deepM'.$market_c['mi_id']);
                                        $res = $this->buyOneByOne($effectiveSells, $key, $va, 1,++$deep);
                                        $mark = 1;
                                    }

                                    /**买单撮合中 新进卖单更优数据*/
                                    //挂卖单 //如果下一笔撮合的价格 大于 等于新进来的挂卖单的.就撮合新进来的挂卖单.
                                    if (isset($effectiveSells[$k + 1])){
                                        if ($va['type_c'] == 2 && bcsub($va['price_c'], $effectiveSells[$k + 1]['price'], self::BCPRECISEDIGITS) < 0) {
                                            $redis->lRem(self::$listKey . $market_c['mi_id'], $va1, 0);
                                            $sells = [$buyData];
                                            $redis->incr('str_deepM'.$market_c['mi_id']);
                                            $decimal = $this->buyOneByOne($sells, $key, $va, 2, $deep);
//                                            if ($decimal <= 0){
//                                                break;
//                                            }
                                            $buyData['decimal'] = $decimal;
                                            break;
                                        }
                                    }
                                    if (isset($mark) && $mark == 1){
                                        break;
                                    }
                                }

                            }
                        }
                        break;
                    case 'no':
                        if ($decimal > 0) { //循环完毕还有剩余的挂单数据/*$count - $i == 0 && */
                            if ($recursive == 0){ //外层循环
                                $redis->hSet($key . self::$hKeyBuy, $hField, json_encode($buyData));
//                                Log::write($buyData['microS']."--2--$hField--2--".json_encode($buyData),'notice');
//                                $redis->publish('ghm',json_encode($buyData));
                            }elseif ($recursive == 1){ //递归循环
                                $preData['decimal_c'] = $decimal;
                                $redis->rPush(self::$listKey.$preData['market_c']['mi_id'],json_encode($preData));
//                                $redis->set('deep'.$deep,$deep);
                                for($q=0;$q<$deep;++$q){
                                    $o = $q;
//                                    $redis->set('oo'.$o,$o);
                                    if ($o>=0){
                                        $variable = 'hash_remain_'.$market_c['mi_id'];
                                        $xx = $redis->hGet($variable,$o);
                                        $yy = json_decode($xx,true);
//                                        $newField = $yy['ui_id'].'_'.$yy['create_time'].'_'.$yy['microS'];
                                        $newField = $yy['order_no'];
                                        $exists = json_decode($redis->hGet($key . self::$hKeyBuy,$newField),true);
                                        if (empty($exists)){
                                            $redis->hSet($key . self::$hKeyBuy, $newField, $xx);
//                                            Log::write($yy['microS']."--3--$newField--3--".$xx,'notice');
                                        }else{
                                            if ($exists['decimal'] > $yy['decimal']){
                                                $redis->hSet($key . self::$hKeyBuy, $newField, $xx);
//                                                Log::write($key . '_buy--'.$newField.':['.$xx.']'.'['.$variable.':'.$o.']','SynchroError');
//                                                Log::write($yy['microS']."--4--$newField--4--".$xx,'notice');
                                            }
                                        }

                                    }
                                }
                                //最内层被撮合完,hash_remain_1 可能存在外层数据,被丢入预挂单的(即消息队列和挂单存在同一域的数据,消息队列为最新的数据!)
                                $redis->hDel($key . self::$hKeyBuy,$preData['userId'] .'_' .$preData['timeS_c'] .'_' .$preData['microS_c']);
                            }elseif ($recursive == 2){
                                $preData['decimal_c'] = $decimal;
                                $redis->rPush(self::$listKey.$preData['market_c']['mi_id'],json_encode($preData));
                            }

                        }
                        break 2;
                }
            }
            unset($value);

            $result = config('code.success');
            $result['msg'] =  '操作成功';//'买入完成!成功交易次数:_' . $i;
            switch ($recursive) {
                case 0:
                    $this->swooleClientClose(true);
                    $redis->del('str_deepM'.$market_c['mi_id']);
                    return $result;
                    break;
                case 1:
                    return [
                    'num'           =>  $i,
//                    'remain'        =>  $remainData,
                ];
                    break;
                case 2:
                    return $lol;
                    break;
                default:
                    return $result;
                    break;
            }

        }catch (Exception $e){
            $error = $this->myGetTrace($e);
            Log::write($error,'tradeError');
            $buyData['status'] = 4;
            Db::name('exception_trade')->insert($buyData);
            return 'Exception';
        }

    }

    /**
     * 撮合方法!记录数据
     * @param $effectiveBuys
     * @param $key
     * @param $recursive        : 0:外层循环 | 1:更优买单递归 | 2:更优卖单递归
     * @param $preData          : extract($preData) 得到下列参数
     * @param $limitMarket
     * @param $market_c
     * @param $decimal
     * @param $timeS
     * @param $microS
     * @param $key
     * @param $userId
     * @param $userFinance
     * @param $price
     * @return string
     */
    protected function sellOneByOne(&$effectiveBuys, $key, $preData, $recursive=0,$deep=0)
    {
        try {
            $redis = $this->redis;
            extract($preData);
            $userId         = $userId;
            $timeS          = $timeS_c;
            $microS         = $microS_c;
            $market_c       = $market_c;
            $price          = $price_c;
            $total          = $total_c;
            $decimal        = $decimal_c;
            $fee            = $fee_c;
            $type           = $type_c;
            $limitMarket    = $limit_market_c;
            $order_no       = $order_no;
//            $hField = $userId .'_' .$timeS .'_' .$microS;
            $hField = $order_no;
            bcscale(self::BCPRECISEDIGITS);
            $sellData = [
                'ui_id'                 =>    $userId,
                'mi_id'                 =>    $market_c['mi_id'],
                'type'                  =>    $type,
                'price'                 =>    $price,
                'total'                 =>    $total,
                'decimal'               =>    $decimal,
                'fee'                   =>    $fee,
                'create_time'           =>    $timeS,
                'update_time'           =>    $timeS,
                'microS'                =>    $microS,
                'status'                =>    self::$tradeStatus1,//1:交易中2:已完成3:已撤销4:异常
                'order_no'              =>    $order_no,
                'limit_market'          =>    $limitMarket,
            ];
            if ($recursive == 2){
                $sellData['type'] = self::$buyType;
            }
            if (empty($effectiveBuys)){
                $r = $redis->hSet($key .self::$hKeySell,$hField,json_encode($sellData));
//                if (!$r && $r!=0){
////                    Log::write($key .self::$hKeySell.'==='.$hField.'==='.json_encode($sellData),'tradeError');
//                    exception('挂单数据写入Redis出错', 5008);
//                }
                $r = $this->syncMysql_ld($sellData,$timeS);
                if ($r == false){
                    $sellData['status'] = 5;
                    Db::name('exception_trade')->insert($sellData);
                    $result = config('code.error');
                    return $result;
//                    $redis->hSet('hash_exception_data', $hField, json_encode($sellData));
//                    exception('没有可撮合!挂单写入数据库失败!', 5005);
                }

//                $redis->publish('ghm',json_encode($sellData));
                unset($sellData);

                $result = config('code.success');
//                $result['msg'] = '没有可撮合数据,挂卖单成功!';
                return $result;
            }

            /**成交价格已上一次成交的价格来衡量!在买价和卖价之间 用上一次成交价!否则 以上下线为准!
                    $default = 5930;
            $lastRecord = json_decode($redis->get('str_last_record_market_'.$market_c['mi_id']))['price']??$default;*/
            $i = 0;
            $end = end($effectiveBuys);
            $ret0 = $this->swooleClientNew();
            foreach ($effectiveBuys as $k => &$value){
//                $buyField = $value['ui_id'] . '_' . $value['create_time'] . '_' . $value['microS'];
                $buyField = $value['order_no'];
                if ($decimal <= 0){
                    if ($end === $value){
                        break;
                    }
                    continue;
                }
                if ($recursive != 2){
                    $tradeData = $redis->hExists($key . self::$hKeyBuy, $buyField);
                    if (!$tradeData){
                        if ($end === $value){
                            $redis->hSet($key .self::$hKeySell,$hField,json_encode($sellData));
                            $r = $this->syncMysql_ld($sellData,$timeS);
                            if ($r == false){
                                $sellData['status'] = 5;
                                Db::name('exception_trade')->insert($sellData);
                                $result = config('code.error');
                                return $result;
                            }
                            unset($sellData);
                            $result = config('code.success');
                            return $result;
                        }
                        unset($effectiveBuys[$k]);
                        continue;
                    }
                }

                $wkey = 'str_'.$value['order_no'];
                $redis->incr($wkey);
                $redis->watch($wkey);
                $redis->multi();
                try {
                    if ($value['decimal'] <= $decimal){
                        $lol = 0;
                        //以一笔撮合成功!删除数据
                        if ($recursive != 2){
                            $redis->hDel($key . self::$hKeyBuy, $buyField);
                        }
                        /**                if ($limitMarket == 1){
                        if ($lastRecord >= $price){
                        $value['price'] = $price;
                        }elseif ($lastRecord >= $value['price'] && $lastRecord <= $price){
                        $value['price'] = $lastRecord;
                        }
                        }*/
                        $finishData = $value;
                        unset($effectiveBuys[$k]);
                        $isRecursive = 'yes'; //是否继续循环
                        $decimal = bcsub($decimal,$value['decimal'],self::BCPRECISEDIGITS);

                        $sellData['decimal'] = $decimal;
                        if ($end === $value){
                            $isRecursive = 'no'; //是否继续循环
                        }
                        if ($decimal == 0){
                            $isRecursive = 'no'; //是否继续循环
                            $sellData['status'] = self::$tradeStatus2;
                        }
                        //撮合数量
                        $pairFirst = $value['decimal'];
                        $remainData = $sellData;
                    }else{
                        $lol = bcsub($value['decimal'], $decimal, self::BCPRECISEDIGITS);
                        $effectiveBuys[$k]['decimal'] = $lol;
                        $value['decimal'] = $lol;
                        if ($recursive != 2){
                            $value['update_time'] = $sellData['create_time'];
                            $redis->hSet($key . self::$hKeyBuy, $buyField, json_encode($value));
                        }
                        /*                if ($limitMarket == 1){
                                            if ($lastRecord >= $price){
                                                $sellData['price'] = $price;
                                            }elseif ($lastRecord >= $value['price'] && $lastRecord <= $price){
                                                $sellData['price'] = $lastRecord;
                                            }
                                        }*/

                        $finishData = $sellData;
                        $isRecursive = 'no';
                        //撮合数量
                        $pairFirst = $decimal;
                        $decimal   = 0;
                        $remainData = $value;
                    }
                    $pairPrice = $value['price'];
                    //价格和数量匹配后 对数据进行修改! 同时添加记录
                    $finishData['status'] = self::$tradeStatus2;   //1:交易中  2:已完成  3:已撤销
                    $finishData['decimal'] = 0;   //交易完成,剩余数量则为0
                    //买卖双方手续费
                    $pairSecond = bcmul($pairPrice,$pairFirst, self::BCPRECISEDIGITS);
                    $feeAccountS = bcmul($pairSecond,$fee/100,self::BCPRECISEDIGITS);
                    $feeAccountB = bcmul($pairFirst,$value['fee']/100,self::BCPRECISEDIGITS);
                    $recordOfTransactionData = [
                        'mt_order_ui_id'       =>    $userId,
                        'mt_peer_ui_id'        =>    $value['ui_id'],
                        'type'                 =>    self::$sellType, //1是买/2是卖 (主动方卖)
                        'mi_id'                =>    $finishData['mi_id'],
                        'price'                =>    $pairPrice,
                        'decimal'              =>    $pairFirst,
                        'amount'               =>    $pairSecond,
                        'buy_fee'              =>    $feeAccountB,   //手续费暂定为0
                        'sell_fee'             =>    $feeAccountS,
                        'create_time'          =>    $timeS,
//                        'microS'               =>    $microS,
                        'order_no'             =>    $order_no,//交易流水号
                        'peer_order_no'        =>    $value['order_no'],//对手方流水号
                    ];
                    if ($recursive == 2){
                        $recordOfTransactionData['type'] = self::$buyType;
                        $isRecursive = 'no';
//                        $price = $value['price'];
                    }
                    if (($sellData['create_time'] < $value['create_time']) || (($sellData['create_time'] == $value['create_time']) && ($sellData['microS'] < $value['microS']))){
                        $price = $value['price'];
                        $pairPrice = $sellData['price'];
                        $pairSecond = bcmul($pairPrice,$pairFirst, self::BCPRECISEDIGITS);
                        $feeAccountS = bcmul($pairSecond,$fee/100,self::BCPRECISEDIGITS);
                        $recordOfTransactionData = [
                            'mt_order_ui_id'        =>    $value['ui_id'],
                            'mt_peer_ui_id'         =>    $userId,
                            'type'                  =>    $value['type'],
                            'mi_id'                 =>    $finishData['mi_id'],
                            'price'                 =>    $pairPrice,
                            'decimal'               =>    $pairFirst,
                            'amount'                =>    $pairSecond,
                            'buy_fee'               =>    $feeAccountS,   //手续费暂定为0
                            'sell_fee'              =>    $feeAccountB,
                            'create_time'           =>    $value['create_time'],
//                            'microS'                =>    $value['microS'],
                            'order_no'              =>    $value['order_no'],//交易流水号
                            'peer_order_no'         =>    $order_no,//对手方流水号
                        ];
                    }
                    //1.1添加一条最后一条成交记录:$redis->set('Final_matching_order_transaction_record','$成交记录');
                    $redis->set('str_last_record_market_'.$market_c['mi_id'], json_encode($recordOfTransactionData));
                }catch (Exception $e){
                    $redis->discard();
                    $error = $this->myGetTrace($e);
                    Log::write($error.'!!!新进卖单-写入-异常数据表:exception_trade','tradeError');
                    $sellData['status'] = 4;
                    Db::name('exception_trade')->insert($sellData);
                    return 'DataException';
                }

                $redis->exec();
                $redis->del($wkey);
                /**这里开启swoole异步 同步数据到数据库**/
                $finishData['update_time'] = $recordOfTransactionData['create_time'];
                $remainData['update_time'] = $recordOfTransactionData['create_time'];
                $restingOrderData = [
                    'type'      =>  'trade',
                    'data'      =>  [
                        'marketTradeLog'    =>   $recordOfTransactionData,
                        'marketTradeF'      =>   $finishData,
                        'marketTradeR'      =>   $remainData,
                        'other'             =>   [
                            'price'=>$price,
                            'coin1'=>$market_c['market_ciIdFirst'],
                            'coin2'=>$market_c['market_ciIdSecond'],
                        ],
                    ],
                ];
//                Log::write('['.json_encode($restingOrderData).']','tradeError');
                $ret = $this->swooleClientSend($restingOrderData);
                //如果异步失败,走同步数据
                if ($ret == false){
                    $this->syncMysql_td($restingOrderData['data']);
                }
                //发布成交记录 给 继伟
                $redis->publish('ghm',json_encode($recordOfTransactionData));
                unset($recordOfTransactionData,$finishData,$restingOrderData);

                ++$i;

                if ($isRecursive == 'yes') {
                    /**读取撮合期间 是否有新的 撮合数据进来!**/
                    $xiaoxi_duilie = null;
                    $deeps = 9;
                    $getDeep = $redis->get('str_deepM'.$market_c['mi_id'])?$redis->get('str_deepM'.$market_c['mi_id']):0;
                    if ($getDeep < $deeps){
                        $xiaoxi_duilie = $redis->lRange(self::$listKey . $market_c['mi_id'], 0, -1);
                        if ($xiaoxi_duilie) {
                            foreach ($xiaoxi_duilie as $va1) {
                                $getDeep = $redis->get('str_deepM'.$market_c['mi_id']);
                                if ($getDeep && $getDeep >= $deeps){
                                    break;
                                }
                                $va = json_decode($va1,true);
                                //挂卖单 //撮合期间 新进来的挂卖单 更低价格就先撮合新进的卖单
                                if ($va['type_c'] == 2 && bcsub($va['price_c'], $price, self::BCPRECISEDIGITS) < 0) {
//如果买单数量与下一笔要撮合的数量比较 2中情况
                                    $variable = 'hash_remain_s_'.$market_c['mi_id'];
                                    $redis->hSet($variable,$deep,json_encode($remainData));
                                    $redis->lRem(self::$listKey . $market_c['mi_id'], $va1, 0);
                                    $redis->incr('str_deepM'.$market_c['mi_id']);
                                    $res = $this->sellOneByOne($effectiveBuys, $key, $va, 1,++$deep);
                                    $mark = 1;
                                }
                                //挂买单 //如果下一笔撮合的价格 小于 等于新进来的挂买单的.就撮合新进来的挂买单.
                                if (isset($effectiveBuys[$k + 1])){
                                    if ($va['type_c'] == 1 && bcsub($va['price_c'], $effectiveBuys[$k+1]['price'], self::BCPRECISEDIGITS) > 0) {
                                        $redis->lRem(self::$listKey . $market_c['mi_id'], $va1, 0);
                                        $buys = [$sellData];
                                        $redis->incr('str_deepM'.$market_c['mi_id']);
                                        $decimal = $this->sellOneByOne($buys, $key, $va, 2,$deep);
                                        $sellData['decimal'] = $decimal;
                                        break;
                                    }
                                }
                                if (isset($mark) && $mark == 1){
                                    break;
                                }
                            }
                        }
                    }
                }elseif ($isRecursive == 'no'){
                    if (/*$count-$i == 0 && */$decimal > 0){ //循环完毕还有剩余的挂单数据
                        if ($recursive == 0){
                            $redis->hSet($key .self::$hKeySell,$hField,json_encode($sellData));
//                            $redis->publish('ghm',json_encode($sellData));
                        }elseif ($recursive == 1){
                            $preData['decimal_c'] = $decimal;
                            $redis->rPush(self::$listKey.$preData['market_c']['mi_id'],json_encode($preData));
                            for($q=0;$q<$deep;++$q){
                                $o = $q;
                                if ($o>=0){
                                    $variable = 'hash_remain_s_'.$market_c['mi_id'];
                                    $xx = $redis->hGet($variable,$o);
                                    $yy = json_decode($xx,true);
//                                    $newField = $yy['ui_id'].'_'.$yy['create_time'].'_'.$yy['microS'];
                                    $newField = $yy['order_no'];
                                    $exists = json_decode($redis->hGet($key . self::$hKeySell,$newField),true);
                                    if (empty($exists)){
                                        $redis->hSet($key . self::$hKeySell, $newField, $xx);
                                    }else{
                                        if ($exists['decimal'] > $yy['decimal']){
                                            $redis->hSet($key . self::$hKeySell, $newField, $xx);
                                        }
                                    }

                                }
                            }
                            //最内层被撮合完,hash_remain_1 可能存在外层数据,被丢入预挂单的(即消息队列和挂单存在同一域的数据,消息队列为最新的数据!)
                            $redis->hDel($key . self::$hKeySell,$preData['userId'] .'_' .$preData['timeS_c'] .'_' .$preData['microS_c']);
//                            $redis->del($variable);
                        }elseif ($recursive == 2){
                            $preData['decimal_c'] = $decimal;
                            $redis->rPush(self::$listKey.$preData['market_c']['mi_id'],json_encode($preData));
                        }
                    }
                    break;
                }
            }
            unset($value);

            $result = config('code.success');
            $result['msg'] =  '操作成功';//'卖出完成!成功交易次数:_' . $i.'---';
            switch ($recursive) {
                case 0:
                    $this->swooleClientClose(true);
                    $redis->del('str_deepM'.$market_c['mi_id']);
                    return $result;
                    break;
                case 1:
                    return [
                        'num'           =>  $i,
//                        'remain'        =>  $remainData,
                    ];
                    break;
                case 2:
                    return $lol;
                    break;
                default:
                    return $result;
                    break;
            }

        }catch (Exception $e){
            $error = $this->myGetTrace($e);
            Log::write($error,'tradeError');
            $sellData['status'] = 4;
            Db::name('exception_trade')->insert($sellData);
            return 'Exception';
        }

    }


    /**
     * 验证 市价 交易的余额 并返回对应的撮合数据
     * @param $decimal          : 交易数量
     * @param $market           : 交易市场
     * @param $b_s              : 1买/2卖
     * @param $userID           : 用户ID
     * @param $coinID           : 币种ID
     * @return array            : 返回可以撮合的数据,如果余额充足的情况下
     */
    public function checkMarketBalance($decimal, $market, $b_s, $userID, $coinID)
    {
        try {
            $redis = $this->redis;
            $key = self::$hKey .$market;
            $data = [];
            switch ($b_s) {
                case self::$buyType:
                    $data = $redis->hVals($key .self::$hKeySell);
                    if ($data){
                        $data = array_map('jsonDecode',$data);
                        array_multisort(array_column($data,'price'), SORT_ASC,
                            array_column($data,'create_time'), SORT_ASC,
                            array_column($data,'microS'), SORT_ASC,
                            $data);
                    }
                    break;
                case self::$sellType:
                    $data = $redis->hVals($key .self::$hKeyBuy);
                    if ($data){
                        $data = array_map('jsonDecode',$data);
                        array_multisort(array_column($data,'price'), SORT_DESC,
                            array_column($data,'create_time'), SORT_ASC,
                            array_column($data,'microS'), SORT_ASC,
                            $data);
                    }
                    break;
            }
            if (empty($data)){
                return [
                    'result'        =>  false,
                    'msg'           =>  'Transaction data is empty',
                ];
            }
            $sellMarkets = [];
            $need = 0;
            $i = 0;
            if ((is_array($data)) || (!empty($data))){
                foreach ($data as $value){
//                    $sellField = $value['ui_id'] . '_' . $value['create_time'] . '_' . $value['microS'];
//                    $sellField = $value['order_no'];
                    if ($decimal > 0){
//                        $redis->multi();
//                        $redis->incr('str_MarketPrice_'.$market.'num');
                        $redis->rPush('list_MarketPrice_'.$market.self::$hKeySell,json_encode($value));
//                        $redis->exec();
                        $sellMarkets = $value;
                        $decimal = bcsub($decimal,$value['decimal'],self::BCPRECISEDIGITS);
                        if ($decimal >= 0){
                            $need = bcadd($need,bcmul($value['price'],$value['decimal'],self::BCPRECISEDIGITS),self::BCPRECISEDIGITS);
                        }else{
                            //$decimal为负数,其绝对值 就是$value剩余的数量 [ ] 挂单数量减去剩余数量就是撮合数量
                            $xx = bcadd($value['decimal'],$decimal,self::BCPRECISEDIGITS);//[+负数 相当于 -]
                            $need = bcadd($need,bcmul($value['price'],$xx,self::BCPRECISEDIGITS),self::BCPRECISEDIGITS);
                        }
                    }
                    ++$i;
                }
                if ($decimal > 0){
                    $redis->del('list_MarketPrice_'.$market.self::$hKeySell);
                    return [
                        'result'        =>  false,
                        'msg'           =>  'The market price can match the shortage of transactions',
                    ];
                }
            }
            $amount = UserFinanceModel::where('ui_id', $userID)
                ->where('ci_id', $coinID)
                ->find();
            $aft = bcsub($amount['amount'],$need,self::BCPRECISEDIGITS);
            if ($aft < 0){
                $redis->del('list_MarketPrice_'.$market.self::$hKeySell);
                return [
                    'result'        =>  false,
                ];
            }
            return [
                'result'        =>  true,
                'number'        =>  $i,
                'key'           =>  $key,
                'data'          =>  $sellMarkets,
            ];
        }catch (Exception $e){
            $error = $this->myGetTrace($e);
            Log::write($error,'tradeError');
            $redis->del('list_MarketPrice_'.$market.self::$hKeySell);
            $result['msg'] = '市价撮合失败!稍后重试!';
            ouputJson($result['status'],$result['msg'],$result['data']);
        }
    }

    protected function marketBuy($sellMarkets,$key,$buyData,$market_c)
    {
        try {
            extract($buyData);
            $userId         = $ui_id;
            $timeS          = $create_time;
            $microS         = $microS;
            $market         = $mi_id;
            $price          = $price;
            $decimal        = $decimal;
            $fee            = $fee;
            $order_no       = $order_no;

            $result = config('code.error');
            bcscale(self::BCPRECISEDIGITS);

            /** //与上一笔成交价 做对比!!
            //成交价格已上一次成交的价格来衡量!在买价和卖价之间 用上一次成交价!否则 以上下线为准!
            $default = 5930;
            $lastRecord = json_decode($redis->get('str_last_record_market_'.$market_c['mi_id']))['price']??$default;*/
            $redis = $this->redis;
            $i = 1;
            $marketPriceData = [];
            $wkey = 'str_'.$market.'_b_'.$userId.'_marketPrice';
            $redis->incr($wkey);

            $redis->watch($wkey);
            $redis->multi();
            try {
                $ret0 = $this->swooleClientNew();
                foreach ($sellMarkets as $k => $value){
//                    $sellField = $value['ui_id'] . '_' . $value['create_time'] . '_' . $value['microS'];
                    $sellField = $value['order_no'];
                    if ($value['decimal'] <= $decimal){
                        //以一笔撮合成功!删除数据
                        $redis->hDel($key . self::$hKeySell, $sellField);

                        /**                if ($limitMarket == 1){
                        if ($lastRecord >= $price){
                        $value['price'] = $price;
                        }elseif ($lastRecord >= $value['price'] && $lastRecord <= $price){
                        $value['price'] = $lastRecord;
                        }
                        }*/

                        $finishData = $value; //撮合掉的挂单数据
                        unset($sellMarkets[$k]);

                        $decimal = bcsub($decimal,$value['decimal'],self::BCPRECISEDIGITS);
                        $buyData['decimal'] = $decimal;
                        if ($decimal == 0){
                            $buyData['status'] = self::$tradeStatus2;
                        }
                        //撮合数量
                        $pairFirst = $value['decimal'];

                        $remainData = $buyData; //撮合后剩下的挂单数据
                    }else{
                        $lol = bcsub($value['decimal'], $decimal, self::BCPRECISEDIGITS);
                        $sellMarkets[$k]['decimal'] = $lol;
                        $value['decimal'] = $lol;

                        $redis->hSet($key . self::$hKeySell, $sellField, json_encode($value));

                        /*                if ($limitMarket == 1){
                                            if ($lastRecord >= $price){
                                                $buyData['price'] = $price;
                                            }elseif ($lastRecord >= $value['price'] && $lastRecord <= $price){
                                                $buyData['price'] = $lastRecord;
                                            }
                                        }*/
                        $finishData = $buyData; //撮合掉的挂单数据

//                        $mark = 1;//内层撮合最后一笔撮合没有吧挂单的数量撮完
                        //撮合数量
                        $pairFirst = $decimal; //成交数量
                        $decimal   = 0; //剩余数量为0,为了后面的循环再用到$decimal
                        $remainData = $value; //撮合后剩下的挂单数据
                    }
                    $pairPrice = $value['price']; //成交价(都是以挂单数据的价格为成交价)

                    //价格和数量匹配后 对数据进行修改! 同时添加记录
                    $finishData['status'] = self::$tradeStatus2;   //1:交易中  2:已完成  3:已撤销
                    $finishData['decimal'] = 0;   //交易完成,剩余数量则为0

                    //买卖双方手续费
                    $feeAccountB = bcmul($pairFirst,$fee/100,self::BCPRECISEDIGITS);
                    $pairSecond = bcmul($pairPrice,$pairFirst, self::BCPRECISEDIGITS);
                    $feeAccountS = bcmul($pairSecond,$value['fee']/100,self::BCPRECISEDIGITS);
                    //1还需要成交记录,字段(发起方,接收方,交易类型[1:买/2:卖],交易市场,单价,数量,金额,手续费,时间[2个字段:时间戳/微秒部分],交易流水号)根据交易市场分成多个HASH,来存储!
                    $recordOfTransactionData = [
                        'mt_order_ui_id'        =>    $userId,
                        'mt_peer_ui_id'         =>    $value['ui_id'],
                        'type'                  =>    self::$buyType,
                        'mi_id'                 =>    $finishData['mi_id'],
                        'price'                 =>    $pairPrice,
                        'decimal'               =>    $pairFirst,
                        'amount'                =>    $pairSecond,
                        'buy_fee'               =>    $feeAccountB,   //手续费暂定为0
                        'sell_fee'              =>    $feeAccountS,
                        'create_time'           =>    $timeS,
                        'microS'                =>    $microS,
                        'order_no'              =>    $order_no,//交易流水号
                        'peer_order_no'         =>    $value['order_no'],//对手方流水号
                    ];

                    /**这里开启swoole异步 同步撮合后数据到数据库**/
                    $marketPriceData[] = [
                        'data'      =>  [
                            'marketTradeLog'    =>   $recordOfTransactionData,
                            'marketTradeF'      =>   $finishData,
                            'marketTradeR'      =>   $remainData,
                            'other'             =>   [
                                'price'=>$price,
                                'coin1'=>$market_c['market_ciIdFirst'],
                                'coin2'=>$market_c['market_ciIdSecond'],
                            ],
                        ],
                    ];
                    unset($recordOfTransactionData,$finishData,$remainData);

                    ++$i;
                }
            }catch (Exception $e){
                $redis->discard();
//                $eField = $buyData['ui_id'].'_'.$buyData['create_time'].'_'.$buyData['microS'];
                $eField = $buyData['order_no'];
                $error = $this->myGetTrace($e);
                Log::write($error.'!!!新进\<市价\>买单-异常数据对应的域:'.$eField,'tradeError');
                //记录$buyData到异常记录
                $redis->hSet('hash_exception_data',$eField,json_encode($buyData));

                return 'DataException';
            }
            $redis->exec();
            $redis->del($wkey);

            $restingOrderData = [
                'type'          =>   'marketPrice',
                'data'          =>   $marketPriceData,
            ];
            $ret = $this->swooleClientSend($restingOrderData);
            //如果异步失败,走同步数据
            if ($ret == false){
                $this->syncMysql_mtd($restingOrderData['data']);
            }

            //发布成交记录 给 继伟
            $redis->publish('ghm',json_encode($restingOrderData));
            unset($restingOrderData);

            $next = $i-1;
            $this->swooleClientClose(true);
            return json_encode([
                'status'        =>  200,
                'msg'           =>  '购买完成!成功交易次数:_'.$next,
                'next'          =>  $next,
//                'mark'          =>  $mark??0,
            ]);

        }catch (Exception $e){
            $error = $this->myGetTrace($e);
            Log::write($error,'tradeError');
            return 'Exception';
        }
    }

    protected function marketSell($buyMarkets,$key,$sellData,$market_c)
    {
        try {
            extract($sellData);
            $userId         = $ui_id;
            $timeS          = $create_time;
            $microS         = $microS;
            $market         = $mi_id;
            $price          = $price;
            $decimal        = $decimal;
            $fee            = $fee;
            $order_no       = $order_no;

            $result = config('code.error');
            bcscale(self::BCPRECISEDIGITS);

            /** //与上一笔成交价 做对比!!
            //成交价格已上一次成交的价格来衡量!在买价和卖价之间 用上一次成交价!否则 以上下线为准!
            $default = 5930;
            $lastRecord = json_decode($redis->get('str_last_record_market_'.$market_c['mi_id']))['price']??$default;*/
            $redis = $this->redis;
            $i = 1;
            $marketPriceData = [];
            $wkey = 'str_'.$market.'_s_'.$userId.'_marketPrice';
            $redis->incr($wkey);
            $redis->watch($wkey);
            $redis->multi();
            try {
                $ret0 = $this->swooleClientNew();
                foreach ($buyMarkets as $k => $value){
//                    $buyField = $value['ui_id'] . '_' . $value['create_time'] . '_' . $value['microS'];
                    $buyField = $value['order_no'];
                    if ($value['decimal'] <= $decimal){
                        //以一笔撮合成功!删除数据
                        $redis->hDel($key . self::$hKeyBuy, $buyField);
                        /**                if ($limitMarket == 1){
                        if ($lastRecord >= $price){
                        $value['price'] = $price;
                        }elseif ($lastRecord >= $value['price'] && $lastRecord <= $price){
                        $value['price'] = $lastRecord;
                        }
                        }*/
                        $finishData = $value;
                        unset($buyMarkets[$k]);
                        $decimal = bcsub($decimal,$value['decimal'],self::BCPRECISEDIGITS);

                        $sellData['decimal'] = $decimal;
                        if ($decimal == 0){
                            $sellData['status'] = self::$tradeStatus2;
                        }
                        //撮合数量
                        $pairFirst = $value['decimal'];
                        $remainData = $sellData;
                    }else{
                        $lol = bcsub($value['decimal'], $decimal, self::BCPRECISEDIGITS);
                        $effectiveBuys[$k]['decimal'] = $lol;
                        $value['decimal'] = $lol;
                        $redis->hSet($key . self::$hKeyBuy, $buyField, json_encode($value));
                        /*                if ($limitMarket == 1){
                                            if ($lastRecord >= $price){
                                                $sellData['price'] = $price;
                                            }elseif ($lastRecord >= $value['price'] && $lastRecord <= $price){
                                                $sellData['price'] = $lastRecord;
                                            }
                                        }*/

                        $finishData = $sellData;
//                        $mark = 1;
                        //撮合数量
                        $pairFirst = $decimal;
                        $decimal   = 0;
                        $remainData = $value;
                    }
                    $pairPrice = $value['price'];
                    //价格和数量匹配后 对数据进行修改! 同时添加记录
                    $finishData['status'] = self::$tradeStatus2;   //1:交易中  2:已完成  3:已撤销
                    $finishData['decimal'] = 0;   //交易完成,剩余数量则为0
                    //买卖双方手续费
                    $pairSecond = bcmul($pairPrice,$pairFirst, self::BCPRECISEDIGITS);
                    $feeAccountS = bcmul($pairSecond,$fee/100,self::BCPRECISEDIGITS);
                    $feeAccountB = bcmul($pairFirst,$value['fee']/100,self::BCPRECISEDIGITS);
                    //1还需要成交记录,字段(发起方,接收方,交易类型[1:买/2:卖],交易市场,单价,数量,金额,手续费,时间[2个字段:时间戳/微秒部分])根据交易市场分成多个HASH,来存储!
                    $recordOfTransactionData = [
                        'mt_order_ui_id'       =>    $userId,
                        'mt_peer_ui_id'        =>    $value['ui_id'],
                        'type'                 =>    self::$sellType, //1是买/2是卖 (主动方卖)
                        'mi_id'                =>    $finishData['mi_id'],
                        'price'                =>    $pairPrice,
                        'decimal'              =>    $pairFirst,
                        'amount'               =>    $pairSecond,
                        'buy_fee'              =>    $feeAccountB,   //手续费暂定为0
                        'sell_fee'             =>    $feeAccountS,
                        'create_time'          =>    $timeS,
                        'microS'               =>    $microS,
                        'order_no'             =>    $order_no,//交易流水号
                        'peer_order_no'        =>    $value['order_no'],//对手方流水号
                    ];

                    /**这里开启swoole异步 同步撮合后数据到数据库**/
                    $marketPriceData[] = [
                        'data'      =>  [
                            'marketTradeLog'    =>   $recordOfTransactionData,
                            'marketTradeF'      =>   $finishData,
                            'marketTradeR'      =>   $remainData,
                            'other'             =>   [
                                'price'=>$price,
                                'coin1'=>$market_c['market_ciIdFirst'],
                                'coin2'=>$market_c['market_ciIdSecond'],
                            ],
                        ],
                    ];
                    unset($recordOfTransactionData,$finishData,$remainData);

                    ++$i;
                }
            }catch (Exception $e){
                $redis->discard();
//                $eField = $sellData['ui_id'].'_'.$sellData['create_time'].'_'.$sellData['microS'];
                $eField = $sellData['order_no'];
                $error = $this->myGetTrace($e);
                Log::write($error.'!!!新进\<市价\>卖单-异常发生的数据:'.$eField,'tradeError');
                $redis->hSet('hash_exception_data',$eField,json_encode($sellData));
                return 'DataException';
            }
            $redis->exec();
            $redis->del($wkey);

            $restingOrderData = [
                'type'          =>   'marketPrice',
                'data'          =>   $marketPriceData,
            ];
            $ret = $this->swooleClientSend($restingOrderData);
            //如果异步失败,走同步数据
            if ($ret == false){
                $this->syncMysql_mtd($restingOrderData['data']);
            }

            //发布成交记录 给 继伟
            $redis->publish('ghm',json_encode($restingOrderData));
            unset($restingOrderData);

            $next = $i-1;
            $this->swooleClientClose(true);
            return json_encode([
                'status'        =>  200,
                'msg'           =>  '购买完成!成功交易次数:_'.$next,
                'next'          =>  $next,
//                'mark'          =>  $mark??0,
            ]);

        }catch (Exception $e){
            $error = $this->myGetTrace($e);
            Log::write($error,'tradeError');
            return 'Exception';
        }
    }


    /**
     * 撤销挂单(确保没有在撮合,删除Redis的挂单同时修改数据库的挂单数据)
     * @param $market
     * @param $type
     * @param $uid
     * @param $timeS
     * @param $microS_c
     * @return mixed
     */
//    public function cancelTrade($tradeData) //该方法已放入Personal类中
//    {
//        $redis = $this->redis;
//        $transactionPair = json_decode($redis->hGet(self::$marketKey,$tradeData['mi_id']),true);
//        if (empty($transactionPair)){
////            $transactionPair = MarketInfoModel::where('mi_id', $market)->find();
//            $transactionPair = Db::table('market_info')->where('mi_id', $tradeData['mi_id'])->find();
//            $redis->hSet(self::$marketKey,$tradeData['mi_id'],json_encode($transactionPair));
//        }
//
//        $hKey = 'hash_market_' .$tradeData['mi_id'];
////        $hField = $tradeData['ui_id'] . '_' . $tradeData['create_time'] . '_' . $tradeData['microS'];
//        $hField = $tradeData['order_no'];
//
//        $result = config('code.error');
//        if ($tradeData['type'] == self::$buyType){
//            $data = json_decode($redis->hGet($hKey.self::$hKeyBuy,$hField),true);
//            if (!$data){
//                $result['msg'] = '读取买单数据为空!';
//                return $result;
//            }
//            $ci_id = $transactionPair['ci_id_second'];
//            $num = bcmul($data['decimal'],$data['price'],self::BCPRECISEDIGITS);
//        }else/*if ($tradeData['type'] == 2)*/{
//            $data = json_decode($redis->hGet($hKey.self::$hKeySell,$hField),true);
//            if (!$data){
//                $result['msg'] = '读取卖单数据为空!';
//                return $result;
//            }
//            $ci_id = $transactionPair['ci_id_first'];
//            $num = $data['decimal'];
//        }
//        $ui_id = $data['ui_id'];
//        $map = [
//            'ui_id'         =>   $ui_id,
//            'ci_id'         =>   $ci_id,
//        ];
//
//        $trans_frost = Db::table('user_finance')->where($map)->value('trans_frost');
//        if (bcsub($trans_frost,$num,self::BCPRECISEDIGITS) < 0){
//            $result['msg'] = '数据处理中或数据库冻结数据出错!';
//            return $result;
//        }
//        $year = date('Y',$data['create_time']);
//        $table = 'market_trade'. $year. '_'. $data['mi_id'];
//        $month = date('Y_m',$data['create_time']);
//        $table1 = 'user_finance_log'. $month;
//
//        if ($redis->exists('str'.$data['order_no'])){
//            $result['msg'] = '真的很巧合,数据正在撮合!';
//            return $result;
//        }
////        $redis->multi();
//        if ($data['type'] == self::$buyType) {
//            $redis->hDel($hKey . self::$hKeyBuy, $hField);
//        } else/*if ($data['type'] == 2)*/ {
//            $redis->hDel($hKey . self::$hKeySell, $hField);
//        }
//        $data['status'] = 3;
//        $redis->hSet($hKey .'_cancelTrade',$hField,json_encode($data));
////        $redis->exec();
//
//        $time = time();
//        $count = Db::table($table)->where('order_no', $data['order_no'])->count();
//        Db::startTrans();
//        try {
//            if ($count) {
//                $r = Db::table($table)
//                    ->where('order_no', $data['order_no'])
//                    ->update(['status' => self::$tradeStatus3,'update_time'=>$time]);
//                if (!$r){
//                    $result['msg'] = '撤销数据挂单数据库更新失败!';
//                    return $result;
//                }
//            } else {
//                $data['status'] = self::$tradeStatus3;
//                $data['update_time'] = $time;
//                $r = Db::table($table)
//                    ->data($data)
//                    ->insert();
//                if (!$r){
//                    $result['msg'] = '撤销数据挂单数据库新增失败!';
//                    return $result;
//                }
//            }
//            //修改余额数据!$data['decimal'];
//            $amount = [
//                'amount'          =>   Db::raw('amount+'.$num),
//                'trans_frost'     =>   Db::raw('trans_frost-'.$num),
//                'update_time'     =>   $time,
//            ];
//            Db::table('user_finance')
//                ->where($map)
////                ->inc('amount',$num)
////                ->dec('trans_frost',$num)
////                ->exp('name','UPPER(name)')
//                ->update($amount);
//            $data1 = [
//                'ui_id'             =>      $ui_id,
//                'mi_id'             =>      $data['mi_id'],
//                'ci_id'             =>      $ci_id,
////                'bef_A'             =>      $userFinances[0]['amount'],   //交易前余额
////                'bef_B'             =>      $userFinances[0]['trans_frost'],  //交易前冻结
////                'bef_D'             =>      $befTotal0,           //交易前总计
//                'num'               =>      $num,           //本次变动数额
//                'type'              =>      $data['type'],   //1是买 2是卖
//                'create_time'       =>      $time,
////                'aft_A'             =>      $amount0['amount'],         //交易后余额
////                'aft_B'             =>      $userFinances[0]['trans_frost'],      //交易后冻结
////                'aft_D'             =>      $aftTotal0,    //交易后总计
//                'order_no'          =>      $data['order_no'],//交易流水号
//            ];
//            Db::table($table1)
//                ->data($data1)
//                ->insert();
////            if (!$r){
////                $result['msg'] = '撤销数据余额日志数据库新增失败!';
////                return $result;
////            }
//            Db::commit();
//            $redis->hDel($hKey .'_cancelTrade',$hField);
//        } catch (Exception $e) {
//            Db::rollback();
//            $error = $this->myGetTrace($e);
//            Log::write($error.'!!!撤单,DB修改状态或冻结返还出错,data:'.json_encode($data),'tradeError');
//            //记录$buyData到异常记录
//            $redis->rPush('list_exception_cancelTrade',json_encode($data));
//            $result['exception'] = '数据库事务出错!查看:list_exception_cancelTrade';
//            return $result;
//        }
//        $result = config('code.success');
//        return $result;
//    }


    /**
     * 用户输入价格,ajax调用此接口,验证价格 小数点保留位数和上下浮动
     * @param $market
     * @param $price
     * @return string|\think\response\Json
     */
    public function checkPrice($market)
    {
        $redis = $this->redis;
        $transactionPair = json_decode($redis->hGet(self::$marketKey,$market),true);
        if (empty($transactionPair)){
            $transactionPair = Db::table('market_info')->where('mi_id', $market)->find();
            $redis->hSet(self::$marketKey,$market,json_encode($transactionPair));
        }

        $default = $transactionPair['last_price']??self::$firstTrade;
        $lastRecord = json_decode($redis->get('str_last_record_market_'.$transactionPair['mi_id']),true)['price']??$default;

        $result = config('code.success');
        bcscale($transactionPair['price_bit']);
        $price_buy_max = bcadd(1,$transactionPair['price_buy_max']/100);
        $price_buy_max = bcmul($lastRecord,$price_buy_max);
        $price_buy_min = bcsub(1,$transactionPair['price_buy_min']/100);
        $price_buy_min = bcmul($lastRecord,$price_buy_min);

        $price_sell_min = bcsub(1,$transactionPair['price_sell_min']/100);
        $price_sell_min = bcmul($lastRecord,$price_sell_min);
        $price_sell_max = bcadd(1,$transactionPair['price_sell_max']/100);
        $price_sell_max = bcmul($lastRecord,$price_sell_max);
        $result['data'] = [
            'price_bit'             =>   $transactionPair['price_bit'],
            'amount_bit'            =>   $transactionPair['amount_bit'],
            'last_price'            =>   $lastRecord,
            'price_buy_max'         =>   $transactionPair['price_buy_max'],
            'price_buy_min'         =>   $transactionPair['price_buy_min'],
            'price_sell_min'        =>   $transactionPair['price_sell_min'],
            'price_sell_max'        =>   $transactionPair['price_sell_max'],
            'price_buy_max1'        =>   $price_buy_max,
            'price_buy_min1'        =>   $price_buy_min,
            'price_sell_min1'       =>   $price_sell_min,
            'price_sell_max1'       =>   $price_sell_max,
        ];
        ouputJson($result['status'],$result['msg'],$result['data']);
    }

    /**
     * 用户输入数量,ajax调用此接口,验证余额!
     * @param $market
     * @param $decimal
     * @param $price
     * @param $type
     * @return string|\think\response\Json
     */
    public function checkLimitBalance($market, $decimal, $price, $type)
    {
        $userID = $this->uid;
        $result = config('code.error');
        $bc = bcscale(self::BCPRECISEDIGITS);
        if (!$bc)
            return 'bc默认保留位数失败!';
        $redis = $this->redis;
        $transactionPair = json_decode($redis->hGet(self::$marketKey,$market),true);
        if (empty($transactionPair)){
            $transactionPair = Db::table('market_info')->where('mi_id', $market)->find();
            $redis->hSet(self::$marketKey,$market,json_encode($transactionPair));
        }
        //验证decimal,最小数量限制 $transactionPair['amount_input_min']
        if ($decimal < $transactionPair['amount_input_min']){
            $result['msg'] = lang('PLEASE_ENTER_VALID_QUANTITIES');
            ouputJson($result['status'],$result['msg'],$result['data']);
        }

        switch ($type) {
            case self::$buyType:
                $amount = json_decode($redis->hGet('hash_data_userFinance',$userID.'_'.$transactionPair['ci_id_second']),true);
                if (empty($amount)){
                    $amount = UserFinanceModel::where('ui_id', $userID)
                        ->where('ci_id', $transactionPair['ci_id_second'])
                        ->find();
                }
                $number = bcmul($decimal,$price);
                $aft = bcsub($amount['amount'],$number);
                break;
            case self::$sellType:
                $amount = json_decode($redis->hGet('hash_data_userFinance',$userID.'_'.$transactionPair['ci_id_first']),true);
                if (empty($amount)){
                    $amount = UserFinanceModel::where('ui_id', $userID)
                        ->where('ci_id', $transactionPair['ci_id_first'])
                        ->find();
                }
                $aft = bcsub($amount['amount'],$decimal);
                break;
        }
        if ($aft < 0){
            $result['msg'] = lang('SUFFICIENT_FUNDS');
            ouputJson($result['status'],$result['msg'],$result['data']);
        }
        return 'OK';
    }


    /**
     * 异步客户端请求 swoole 服务去同步数据到数据库
     * @param $data
     * @return bool
     */
    private $client = null;
    private function swooleClientNew()
    {
        try{
            if (!($this->client instanceof \swoole_client)){
                $this->client = new \swoole_client(SWOOLE_SOCK_TCP | SWOOLE_KEEP/*,SWOOLE_SOCK_SYNC,'2'*/);
                $this->client->set(array(
                    'socket_buffer_size'     => 1024*1024*8, //8M缓存区
                ));
                if (!$this->client->connect('127.0.0.1', 9595, -1)){
                    return false;
                }
            }
//            if (!$this->client->send(json_encode($data))){
//                return false;
//            }
//            $this->client->close();
//            $this->client = null;
            return true;
        }catch (Exception $e){
            $error = $this->myGetTrace($e);
            Log::write($error.'连接swoole服务出错!','tradeError');
            //发送通知 (这里可能是swoole服务没有开启,等错误)
            return false;
        }
    }
    //封装要发送的数据
    private static function getSerialize($data)
    {
        $sendstr = serialize($data);
        $sendData = pack('N',strlen($sendstr)).$sendstr;
        return $sendData;
    }
    private function swooleClientSend($data)
    {
        if (!empty($this->client)){
            if (!$this->client->isConnected()){
                return false;
            }
//            $data = json_encode($data)."\r\n";
            $data = self::getSerialize($data);
            if (!$this->client->send($data)){
                return false;
            }
            return true;
        }else{
            return false;
        }
    }
    private function swooleClientClose($bool=false)
    {
        if (!empty($this->client)){
            $this->client->close($bool);
            $this->client = null;
        }
    }

    /**
     * 异步客户端连接失败,调用此方法去同步 挂单 数据到数据库
     * @param $data
     * @param $timeS
     * @param $saveType  : 保存数据的类型 (新增0/更新1)
     */
    private function syncMysql_ld($data, $timeS)
    {
        try {
//            Log::write('数据:<'.json_encode($data).'>','SynchroError');
            $year = date('Y',$timeS);
            $table = 'market_trade'. $year. '_'. $data['mi_id'];

            $res = Synchro::existTableTrade($table);
            if ($res !== true){
                Log::write('数据表:'.$table.'创建失败!数据:<'.json_encode($data).'>','SynchroError');
                return false;
            }

//            if (($saveType = Db::table($table)->where('order_no',$data['order_no'])->count())){
//                $r = Db::table($table)
//                    ->where('order_no',$data['order_no'])
//                    ->update($data);
//            }else{
//                $r = Db::table($table)
//                    ->data($data)
//                    ->insert();
//            }
            $field = '';
            foreach ($data as $key=>$val){
                $field .= "`$key`='" . addslashes ( $val ) . "',";
            }
            $field = substr ( $field, 0, - 1 );
            $sql = "insert into `{$table}` SET {$field} ON DUPLICATE KEY UPDATE {$field}";
            $r = Db::execute($sql);
            if (!$r && $r != 0){
                $map = [
                    'order_no'=>$data['order_no'],
                    'decimal'=>$data['decimal'],
                    'total'=>$data['total'],
                    'ui_id'=>$data['ui_id'],
                ];
                if (!(Db::table($table)->where($map)->count())){
                    Log::write('[0未变化/1新增/2更新]:'.$r.',数据:<'.json_encode($data).'>,未能写入->'.$table,'SynchroError');
                    return false;
                }
            }
            return true;
        } catch (Exception $e) {
            $error = $this->myGetTrace($e);
            Log::write("unknown-error | 挂单数据写入数据库异常1，error:{$error}。",'SynchroError');
            return false;
        }

    }

    /**
     * 异步客户端连接失败,调用此方法去同步 交易 数据到数据库
     */
    private function syncMysql_td($data)
    {
        try {
            extract($data);
            $other['price'];
            $other['coin1'];
            $other['coin2'];
            $marketTradeF;
            $marketTradeR;
            $marketTradeLog;
//            $pairPrice = $marketTradeLog['price']; //实际成交价

            $map0 = [
                ['ui_id','=',$marketTradeLog['mt_order_ui_id']],
                ['ci_id','=',$other['coin1']],
            ];
            $map1 = [
                ['ui_id','=',$marketTradeLog['mt_order_ui_id']],
                ['ci_id','=',$other['coin2']],
            ];
            $map2 = [
                ['ui_id','=',$marketTradeLog['mt_peer_ui_id']],
                ['ci_id','=',$other['coin1']],
            ];
            $map3 = [
                ['ui_id','=',$marketTradeLog['mt_peer_ui_id']],
                ['ci_id','=',$other['coin2']],
            ];

            $user_type = 4;
            $feeAccountId = Db::table('user_info')
                ->where('user_type',$user_type)
                ->value('ui_id');
            $map99 = [
                ['ui_id','=',$feeAccountId],
                ['ci_id','=',$other['coin1']],
            ];
            $map100 = [
                ['ui_id','=',$feeAccountId],
                ['ci_id','=',$other['coin2']],
            ];

            $userFinances = Db::table('user_finance')
                ->whereOr([$map0,$map1])
                ->order('ci_id','asc')
                ->select();
            $valueFinances = Db::table('user_finance')
                ->whereOr([$map2,$map3])
                ->order('ci_id','asc')
                ->select();
            if ($userFinances[0]['ci_id'] == $other['coin2']){
                $userFinances = array_reverse($userFinances);
            }
            if ($valueFinances[0]['ci_id'] == $other['coin2']){
                $valueFinances = array_reverse($valueFinances);
            }
            Db::startTrans();
            try {
                $this->propertyChange(
                    $data,
                    $marketTradeF,
                    $marketTradeR,
                    $marketTradeLog,
                    $other,
                    $map0, $map1, $map2, $map3,
                    $map99, $map100,
                    $userFinances, $valueFinances
                );
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $error = $this->myGetTrace($e);
//                Log::write("unknown-error2 | 挂单数据写入数据库异常2，error:{$error}。",'SynchroError');
                exception($error);
            }
        } catch (Exception $e) {
            extract($data);
            $marketTradeF['status'] = 6;
            $marketTradeR['status'] = 6;
            $marketTrade = [$marketTradeF,$marketTradeR];
            Db::name('exception_trade')->insertAll($marketTrade);
            Db::name('exception_trade_log')->insert($marketTradeLog);

            $error = $this->myGetTrace($e);
            Log::write("unknown-error2 | 成交记录写入数据库异常2，error:{$error}。",'SynchroError');
        }


    }

    private function propertyChange
    (
        $data,
        $marketTradeF,
        $marketTradeR,
        $marketTradeLog,
        $other,
        $map0, $map1, $map2, $map3,
        $map99, $map100,
        $userFinances, $valueFinances
    )
    {
        $buy_fee   = $marketTradeLog['buy_fee']; //买方手续费
        $sell_fee  = $marketTradeLog['sell_fee']; //卖方手续费
        $pairFirst = $marketTradeLog['decimal']; //币1实际成交数量
        $price     = $other['price']; //主动方挂单价

        bcscale(self::BCPRECISEDIGITS);
        $ret = $this->syncMysql_ld($marketTradeF,$marketTradeF['create_time']);
        if ($ret == false){
            exception('撮合交易后,挂单完成的数据1:{'.json_encode($marketTradeF).'},写入数据库时出错1!');
        }
        $ret = $this->syncMysql_ld($marketTradeR,$marketTradeR['create_time']);
        if ($ret == false){
            exception('撮合交易后,挂单完成的数据2:{'.json_encode($marketTradeR).'},写入数据库时出错2!');
        }
        switch ($marketTradeLog['type']){
            case self::$buyType://主动方:(买)
                //主动方:
                //币1 $userFinances[0] //余额: 加上交易数量 | 减去手续费 $userActual
//                        $buy_fee = $buy_fee/100;
//                        $userActual = bcmul($pairFirst,(1-$buy_fee),self::BCPRECISEDIGITS);
                $userActual = bcsub($pairFirst,$buy_fee,self::BCPRECISEDIGITS);
//                $amount0 = [
//                    'amount'          =>   Db::raw('amount+'.$userActual),
//                    'update_time'     =>   $marketTradeLog['create_time'],
//                ];
//                Db::table('user_finance')
//                    ->where($map0)
//                    ->update($amount0);
                $param = [
                    ['field'=>'amount','type'=>'inc','val'=>$userActual],
                ];
                $ret = updateUserBalance($map0[0][2],$map0[1][2],self::$action,$param);
                if ($ret < 1){
                    exception('updateUserBalance修改余额失败!');
                }

//                  ->where('amount',$userFinances[0]['amount'])
//                  ->setInc('amount', $userActual)
//                  ->setDec('amount', '0.123456789555545');

                /**手续费写入对应账户**/
//              $feeAccount = bcsub($pairFirst,$userActual,self::BCPRECISEDIGITS);
//                $amount99 = [
//                    'amount'          =>   Db::raw('amount+'.$buy_fee),
//                    'update_time'     =>   $marketTradeLog['create_time'],
//                ];
//                Db::table('user_finance')
//                    ->where($map99)
//                    ->update($amount99);
                $param = [
                    ['field'=>'amount','type'=>'inc','val'=>$buy_fee],
                ];
                $ret = updateUserBalance($map99[0][2],$map99[1][2],self::$action,$param);
                if ($ret < 1){
                    exception('updateUserBalance修改余额失败!');
                }

                $befTotal0 = bcadd($userFinances[0]['amount'],$userFinances[0]['trans_frost']);
                $aft_A0 = bcadd($userFinances[0]['amount'],$userActual);
                $aftTotal0 = bcadd($aft_A0,$userFinances[0]['trans_frost']);
                $userFinanceLog0 = [
                    'ui_id'             =>      $marketTradeLog['mt_order_ui_id'],
                    'mi_id'             =>      $marketTradeLog['mi_id'],
                    'ci_id'             =>      $other['coin1'],
                    'bef_A'             =>      $userFinances[0]['amount'],   //交易前余额
                    'bef_B'             =>      $userFinances[0]['trans_frost'],  //交易前冻结
                    'bef_D'             =>      $befTotal0,           //交易前总计
                    'num'               =>      $userActual,           //本次变动数额
                    'type'              =>      $marketTradeLog['type'],   //1是买 2是卖
                    'create_time'       =>      $marketTradeLog['create_time'],
                    'aft_A'             =>      $aft_A0,     //交易后余额
                    'aft_B'             =>      $userFinances[0]['trans_frost'],      //交易后冻结
                    'aft_D'             =>      $aftTotal0,    //交易后总计
                    'order_no'          =>      $marketTradeLog['order_no'],//交易流水号
                ];

                //币2 $userFinances[1] //余额: 加上差价 $disparity, 冻结: 减去成交量 $pairSecond_bef
                $pairSecond = $marketTradeLog['amount'];//成交价*实际成交数量
                $pairSecond_bef = bcmul($price,$pairFirst);//之前冻结数 全部取消冻结,差价返还给余额
                $disparity = bcsub($pairSecond_bef,$pairSecond);//差价
                $ret1 = $this->checkFinance($userFinances[1],$pairSecond_bef);
                if ($ret1 === false){
                    exception(json_encode($userFinances[1])."-$pairSecond_bef=余额为负");
                }
//                $amount1 = [
//                    'amount'          =>   Db::raw('amount+'.$disparity),
//                    'trans_frost'     =>   Db::raw('trans_frost-'.$pairSecond_bef),
//                    'update_time'     =>   $marketTradeLog['create_time'],
//                ];
//                Db::table('user_finance')
//                    ->where($map1)
////                  ->where('amount',$userFinances[1]['amount'])
////                  ->where('trans_frost',$userFinances[1]['trans_frost'])
//                    ->update($amount1);
                $param = [
                    ['field'=>'amount','type'=>'inc','val'=>$disparity],
                    ['field'=>'trans_frost','type'=>'dec','val'=>$pairSecond_bef],
                ];
                $ret = updateUserBalance($map1[0][2],$map1[1][2],self::$action,$param);
                if ($ret < 1){
                    exception('updateUserBalance修改余额失败!');
                }
                $befTotal1 = bcadd($userFinances[1]['amount'],$userFinances[1]['trans_frost']);
                $aft_A1 = bcadd($userFinances[1]['amount'],$disparity);
                $aft_B1 = bcsub($userFinances[1]['trans_frost'],$pairSecond_bef);
                $aftTotal1 = bcadd($aft_A1,$aft_B1,self::BCPRECISEDIGITS);
                $userFinanceLog1 = [
                    'ui_id'             =>      $marketTradeLog['mt_order_ui_id'],
                    'mi_id'             =>      $marketTradeLog['mi_id'],
                    'ci_id'             =>      $other['coin2'],
                    'bef_A'             =>      $userFinances[1]['amount'],
                    'bef_B'             =>      $userFinances[1]['trans_frost'],
                    'bef_D'             =>      $befTotal1,
                    'num'               =>      $pairSecond_bef,
                    'type'              =>      $marketTradeLog['type'],
                    'create_time'       =>      $marketTradeLog['create_time'],
                    'aft_A'             =>      $aft_A1,
                    'aft_B'             =>      $aft_B1,
                    'aft_D'             =>      $aftTotal1,
                    'order_no'          =>      $marketTradeLog['order_no'],
                ];

                //主动方 = 被动方
                if ($marketTradeLog['mt_order_ui_id'] == $marketTradeLog['mt_peer_ui_id']){
                    $userFinances[0]['amount'] = $aft_A0;
                    $valueFinances[0] = $userFinances[0];
                    unset($userFinances[0]);
                    $userFinances[1]['amount'] = $aft_A1;
                    $userFinances[1]['trans_frost'] = $aft_B1;
                    $valueFinances[1] = $userFinances[1];
                    unset($userFinances[1]);
                }

                //被动方:
                //币1 $valueFinances[0] //冻结: 减去交易数量 $pairFirst
                $ret1 = $this->checkFinance($valueFinances[0],$pairFirst);
                if ($ret1 === false){
                    exception(json_encode($valueFinances[0])."-$pairFirst=余额为负");
                }
//                $amount2 = [
//                    'trans_frost'     =>   Db::raw('trans_frost-'.$pairFirst),
//                    'update_time'     =>   $marketTradeLog['create_time'],
//                ];
//                Db::table('user_finance')
//                    ->where($map2)
////                        ->where('trans_frost',$valueFinances[0]['trans_frost'])
//                    ->update($amount2);
                $param = [
                    ['field'=>'trans_frost','type'=>'dec','val'=>$pairFirst],
                ];
                $ret = updateUserBalance($map2[0][2],$map2[1][2],self::$action,$param);
                if ($ret < 1){
                    exception('updateUserBalance修改余额失败!');
                }
                $befTotal2 = bcadd($valueFinances[0]['amount'],$valueFinances[0]['trans_frost']);
                $aft_B2 = bcsub($valueFinances[0]['trans_frost'],$pairFirst);
                $aftTotal2 = bcadd($valueFinances[0]['amount'],$aft_B2,self::BCPRECISEDIGITS);
                $userFinanceLog2 = [
                    'ui_id'             =>      $marketTradeLog['mt_peer_ui_id'],
                    'mi_id'             =>      $marketTradeLog['mi_id'],
                    'ci_id'             =>      $other['coin1'],
                    'bef_A'             =>      $valueFinances[0]['amount'],
                    'bef_B'             =>      $valueFinances[0]['trans_frost'],
                    'bef_D'             =>      $befTotal2,
                    'num'               =>      $pairFirst,
                    'type'              =>      $marketTradeLog['type']==self::$buyType?self::$sellType:self::$buyType,
                    'create_time'       =>      $marketTradeLog['create_time'],
                    'aft_A'             =>      $valueFinances[0]['amount'],
                    'aft_B'             =>      $aft_B2,
                    'aft_D'             =>      $aftTotal2,
                    'order_no'          =>      $marketTradeLog['peer_order_no'],
                ];

                //币2 $valueFinances[1] // 余额: 加上成交数量 | 减去手续费
//                        $sell_fee = $sell_fee/100;
//                        $valueActual = bcmul($pairSecond,(1-$sell_fee),self::BCPRECISEDIGITS);
                $valueActual = bcsub($pairSecond,$sell_fee,self::BCPRECISEDIGITS);
//                $amount3 = [
//                    'amount'          =>   Db::raw('amount+'.$valueActual),
//                    'update_time'     =>   $marketTradeLog['create_time'],
//                ];
//                Db::table('user_finance')
//                    ->where($map3)
////                        ->where('amount',$valueFinances[1]['amount'])
//                    ->update($amount3);
                $param = [
                    ['field'=>'amount','type'=>'inc','val'=>$valueActual],
                ];
                $ret = updateUserBalance($map3[0][2],$map3[1][2],self::$action,$param);
                if ($ret < 1){
                    exception('updateUserBalance修改余额失败!');
                }

                /**手续费写入对应账户**/
//                        $feeAccount = bcsub($pairSecond,$valueActual,self::BCPRECISEDIGITS);
//                $amount100 = [
//                    'amount'          =>   Db::raw('amount+'.$sell_fee),
//                    'update_time'     =>   $marketTradeLog['create_time'],
//                ];
//                Db::table('user_finance')
//                    ->where($map100)
//                    ->update($amount100);
                $param = [
                    ['field'=>'amount','type'=>'inc','val'=>$sell_fee],
                ];
                $ret = updateUserBalance($map100[0][2],$map100[1][2],self::$action,$param);
                if ($ret < 1){
                    exception('updateUserBalance修改余额失败!');
                }

                $befTotal3 = bcadd($valueFinances[1]['amount'],$valueFinances[1]['trans_frost']);
                $aft_A3 = bcadd($valueFinances[1]['amount'],$valueActual);
                $aftTotal3 = bcadd($aft_A3,$valueFinances[1]['trans_frost'],self::BCPRECISEDIGITS);
                $userFinanceLog3 = [
                    'ui_id'             =>      $marketTradeLog['mt_peer_ui_id'],
                    'mi_id'             =>      $marketTradeLog['mi_id'],
                    'ci_id'             =>      $other['coin2'],
                    'bef_A'             =>      $valueFinances[1]['amount'],
                    'bef_B'             =>      $valueFinances[1]['trans_frost'],
                    'bef_D'             =>      $befTotal3,
                    'num'               =>      $valueActual,
                    'type'              =>      $marketTradeLog['type']==self::$buyType?self::$sellType:self::$buyType,
                    'create_time'       =>      $marketTradeLog['create_time'],
                    'aft_A'             =>      $aft_A3,
                    'aft_B'             =>      $valueFinances[1]['trans_frost'],
                    'aft_D'             =>      $aftTotal3,
                    'order_no'          =>      $marketTradeLog['peer_order_no'],
                ];

                //同步余额变更日志
                $logs = [
                    $userFinanceLog0,
                    $userFinanceLog1,
                    $userFinanceLog2,
                    $userFinanceLog3,
                ];
                unset(
                    $userFinanceLog0,
                    $userFinanceLog1,
                    $userFinanceLog2,
                    $userFinanceLog3
                );
                $month = date('Y_m',$marketTradeLog['create_time']);
                $year = date('Y',$marketTradeLog['create_time']);
                $table = 'user_finance_log'. $month;

                $res = Synchro::existTableFinanceLog($table);
                if ($res !== true){
                    Log::write('@数据表:'.$table.'创建失败!数据:<'.json_encode($data).'>','SynchroError');
                    exception('@数据表:'.$table.'创建失败!数据:<'.json_encode($data).'>', 10006);
                }
                Db::table($table)
                    ->insertAll($logs);
                unset($logs);

                //同步成交记录
                unset($marketTradeLog['microS']);
                $table = 'market_trade_log'. $year. '_'. $marketTradeLog['mi_id'];

                $res = Synchro::existTableTradeLog($table);
                if ($res !== true){
                    Log::write('@数据表:'.$table.'创建失败!数据:<'.json_encode($data).'>','SynchroError');
                    exception('@数据表:'.$table.'创建失败!数据:<'.json_encode($data).'>', 10006);
                }
                Db::table($table)->insert($marketTradeLog);

                break;
            case self::$sellType://主动方:(卖)
                //主动方:
                //币1 $userFinances[0] //冻结: 减去交易数量 $pairFirst
                $ret1 = $this->checkFinance($userFinances[0],$pairFirst);
                if ($ret1 === false){
                    exception(json_encode($userFinances[0])."-$pairFirst=余额为负");
                }
//                $amount0 = [
//                    'trans_frost'     =>   Db::raw('trans_frost-'.$pairFirst),
//                    'update_time'     =>   $marketTradeLog['create_time'],
//                ];
//                Db::table('user_finance')
//                    ->where($map0)
////                        ->where('trans_frost',$userFinances[0]['trans_frost'])
//                    ->update($amount0);
                $param = [
                    ['field'=>'trans_frost','type'=>'dec','val'=>$pairFirst],
                ];
                $ret = updateUserBalance($map0[0][2],$map0[1][2],self::$action,$param);
                if ($ret < 1){
                    exception('updateUserBalance修改余额失败!');
                }
                $befTotal0 = bcadd($userFinances[0]['amount'],$userFinances[0]['trans_frost']);
                $aft_B0 = bcsub($userFinances[0]['trans_frost'],$pairFirst,self::BCPRECISEDIGITS);
                $aftTotal0 = bcadd($userFinances[0]['amount'],$aft_B0,self::BCPRECISEDIGITS);
                $userFinanceLog0 = [
                    'ui_id'             =>      $marketTradeLog['mt_order_ui_id'],
                    'mi_id'             =>      $marketTradeLog['mi_id'],
                    'ci_id'             =>      $other['coin1'],
                    'bef_A'             =>      $userFinances[0]['amount'],   //交易前余额
                    'bef_B'             =>      $userFinances[0]['trans_frost'],  //交易前冻结
                    'bef_D'             =>      $befTotal0,           //交易前总计
                    'num'               =>      $pairFirst,           //本次变动数额
                    'type'              =>      $marketTradeLog['type'],   //1是买 2是卖
                    'create_time'       =>      $marketTradeLog['create_time'],
                    'aft_A'             =>      $userFinances[0]['amount'],         //交易后余额
                    'aft_B'             =>      $aft_B0,      //交易后冻结
                    'aft_D'             =>      $aftTotal0,    //交易后总计
                    'order_no'          =>      $marketTradeLog['order_no'],//交易流水号
                ];

                //币2 $userFinances[1] //余额: 加上成交量(成交价*交易数量) | 减去手续费
//                        $pairSecond = bcmul($pairPrice,$pairFirst);
                $pairSecond = $marketTradeLog['amount'];
//                        $sell_fee = $sell_fee/100;
//                        $userActual = bcmul($pairSecond,(1-$sell_fee),self::BCPRECISEDIGITS);
                $userActual = bcsub($pairSecond,$sell_fee,self::BCPRECISEDIGITS);
//                $amount1 = [
//                    'amount'          =>   Db::raw('amount+'.$userActual),
//                    'update_time'     =>   $marketTradeLog['create_time'],
//                ];
//                Db::table('user_finance')
//                    ->where($map1)
////                        ->where('amount',$userFinances[1]['amount'])
//                    ->update($amount1);
                $param = [
                    ['field'=>'amount','type'=>'inc','val'=>$userActual],
                ];
                $ret = updateUserBalance($map1[0][2],$map1[1][2],self::$action,$param);
                if ($ret < 1){
                    exception('updateUserBalance修改余额失败!');
                }

                /**手续费写入对应账户**/
//                        $feeAccount = bcsub($pairSecond,$userActual,self::BCPRECISEDIGITS);
//                $amount100 = [
//                    'amount'          =>   Db::raw('amount+'.$sell_fee),
//                    'update_time'     =>   $marketTradeLog['create_time'],
//                ];
//                Db::table('user_finance')
//                    ->where($map100)
//                    ->update($amount100);
                $param = [
                    ['field'=>'amount','type'=>'inc','val'=>$sell_fee],
                ];
                $ret = updateUserBalance($map100[0][2],$map100[1][2],self::$action,$param);
                if ($ret < 1){
                    exception('updateUserBalance修改余额失败!');
                }

                $befTotal1 = bcadd($userFinances[1]['amount'],$userFinances[1]['trans_frost']);
                $aft_A1 = bcadd($userFinances[1]['amount'],$userActual,self::BCPRECISEDIGITS);
                $aftTotal1 = bcadd($aft_A1,$userFinances[1]['trans_frost'],self::BCPRECISEDIGITS);
                $userFinanceLog1 = [
                    'ui_id'             =>      $marketTradeLog['mt_order_ui_id'],
                    'mi_id'             =>      $marketTradeLog['mi_id'],
                    'ci_id'             =>      $other['coin2'],
                    'bef_A'             =>      $userFinances[1]['amount'],
                    'bef_B'             =>      $userFinances[1]['trans_frost'],
                    'bef_D'             =>      $befTotal1,
                    'num'               =>      $userActual,
                    'type'              =>      $marketTradeLog['type'],
                    'create_time'       =>      $marketTradeLog['create_time'],
                    'aft_A'             =>      $aft_A1,
                    'aft_B'             =>      $userFinances[1]['trans_frost'],
                    'aft_D'             =>      $aftTotal1,
                    'order_no'          =>      $marketTradeLog['order_no'],
                ];

                //主动方 = 被动方
                if ($marketTradeLog['mt_order_ui_id'] == $marketTradeLog['mt_peer_ui_id']){
                    $userFinances[0]['trans_frost'] = $aft_B0;
                    $valueFinances[0] = $userFinances[0];
                    unset($userFinances[0]);
                    $userFinances[1]['amount'] = $aft_A1;
                    $valueFinances[1] = $userFinances[1];
                    unset($userFinances[1]);
                }

                //被动方:
                //币1 $valueFinances[0] //余额: 加上交易数量 | 减去手续费 $pairFirst
//                        $buy_fee = $buy_fee/100;
//                        $valueActual = bcmul($pairFirst,(1-$buy_fee),self::BCPRECISEDIGITS);
                $valueActual = bcsub($pairFirst,$buy_fee,self::BCPRECISEDIGITS);
//                $amount2 = [
//                    'amount'          =>   Db::raw('amount+'.$valueActual),
//                    'update_time'     =>   $marketTradeLog['create_time'],
//                ];
//                Db::table('user_finance')
//                    ->where($map2)
////                        ->where('amount',$valueFinances[0]['amount'])
//                    ->update($amount2);
                $param = [
                    ['field'=>'amount','type'=>'inc','val'=>$valueActual],
                ];
                $ret = updateUserBalance($map2[0][2],$map2[1][2],self::$action,$param);
                if ($ret < 1){
                    exception('updateUserBalance修改余额失败!');
                }

                /**手续费写入对应账户**/
//                        $feeAccount = bcsub($pairFirst,$valueActual,self::BCPRECISEDIGITS);
//                $amount99 = [
//                    'amount'          =>   Db::raw('amount+'.$buy_fee),
//                    'update_time'     =>   $marketTradeLog['create_time'],
//                ];
//                Db::table('user_finance')
//                    ->where($map99)
//                    ->update($amount99);
                $param = [
                    ['field'=>'amount','type'=>'inc','val'=>$buy_fee],
                ];
                $ret = updateUserBalance($map99[0][2],$map99[1][2],self::$action,$param);
                if ($ret < 1){
                    exception('updateUserBalance修改余额失败!');
                }

                $befTotal2 = bcadd($valueFinances[0]['amount'],$valueFinances[0]['trans_frost']);
                $aft_A2 = bcadd($valueFinances[0]['amount'],$valueActual,self::BCPRECISEDIGITS);
                $aftTotal2 = bcadd($aft_A2,$valueFinances[0]['trans_frost'],self::BCPRECISEDIGITS);
                $userFinanceLog2 = [
                    'ui_id'             =>      $marketTradeLog['mt_peer_ui_id'],
                    'mi_id'             =>      $marketTradeLog['mi_id'],
                    'ci_id'             =>      $other['coin1'],
                    'bef_A'             =>      $valueFinances[0]['amount'],
                    'bef_B'             =>      $valueFinances[0]['trans_frost'],
                    'bef_D'             =>      $befTotal2,
                    'num'               =>      $valueActual,
                    'type'              =>      $marketTradeLog['type']==self::$buyType?self::$sellType:self::$buyType,
                    'create_time'       =>      $marketTradeLog['create_time'],
                    'aft_A'             =>      $aft_A2,
                    'aft_B'             =>      $valueFinances[0]['trans_frost'],
                    'aft_D'             =>      $aftTotal2,
                    'order_no'          =>      $marketTradeLog['peer_order_no'],
                ];

                //币2 $valueFinances[1] // 冻结: 减去成交量 $pairSecond | 成交价*交易数量;
                $ret1 = $this->checkFinance($valueFinances[1],$pairSecond);
                if ($ret1 === false){
                    exception(json_encode($valueFinances[1])."-$pairSecond=余额为负");
                }
//                $amount3 = [
//                    'trans_frost'     =>   Db::raw('trans_frost-'.$pairSecond),
//                    'update_time'     =>   $marketTradeLog['create_time'],
//                ];
//                Db::table('user_finance')
//                    ->where($map3)
////                        ->where('trans_frost',$userFinances[1]['trans_frost'])
//                    ->update($amount3);
                $param = [
                    ['field'=>'trans_frost','type'=>'dec','val'=>$pairSecond],
                ];
                $ret = updateUserBalance($map3[0][2],$map3[1][2],self::$action,$param);
                if ($ret < 1){
                    exception('updateUserBalance修改余额失败!');
                }
                $befTotal3 = bcadd($valueFinances[1]['amount'],$valueFinances[1]['trans_frost']);
                $aft_B3 = bcsub($valueFinances[1]['trans_frost'],$pairSecond,self::BCPRECISEDIGITS);
                $aftTotal3 = bcadd($valueFinances[1]['amount'],$aft_B3,self::BCPRECISEDIGITS);
                $userFinanceLog3 = [
                    'ui_id'             =>      $marketTradeLog['mt_peer_ui_id'],
                    'mi_id'             =>      $marketTradeLog['mi_id'],
                    'ci_id'             =>      $other['coin2'],
                    'bef_A'             =>      $valueFinances[1]['amount'],
                    'bef_B'             =>      $valueFinances[1]['trans_frost'],
                    'bef_D'             =>      $befTotal3,
                    'num'               =>      $pairSecond,
                    'type'              =>      $marketTradeLog['type']==self::$buyType?self::$sellType:self::$buyType,
                    'create_time'       =>      $marketTradeLog['create_time'],
                    'aft_A'             =>      $valueFinances[1]['amount'],
                    'aft_B'             =>      $aft_B3,
                    'aft_D'             =>      $aftTotal3,
                    'order_no'          =>      $marketTradeLog['peer_order_no'],
                ];

                //同步余额变更日志
                $logs = [
                    $userFinanceLog0,
                    $userFinanceLog1,
                    $userFinanceLog2,
                    $userFinanceLog3,
                ];
                unset(
                    $userFinanceLog0,
                    $userFinanceLog1,
                    $userFinanceLog2,
                    $userFinanceLog3
                );
                $month = date('Y_m',$marketTradeLog['create_time']);
                $year = date('Y',$marketTradeLog['create_time']);
                $table = 'user_finance_log'. $month;

                $res = Synchro::existTableFinanceLog($table);
                if ($res !== true){
                    Log::write('@数据表:'.$table.'创建失败!数据:<'.json_encode($data).'>','SynchroError');
                    exception('@数据表:'.$table.'创建失败!数据:<'.json_encode($data).'>', 10006);
                }
                Db::table($table)
                    ->insertAll($logs);
                unset($logs);

                //同步成交记录
                unset($marketTradeLog['microS']);
                $table = 'market_trade_log'. $year. '_'. $marketTradeLog['mi_id'];

                $res = Synchro::existTableTradeLog($table);
                if ($res !== true){
                    Log::write('@数据表:'.$table.'创建失败!数据:<'.json_encode($data).'>','SynchroError');
                    exception('@数据表:'.$table.'创建失败!数据:<'.json_encode($data).'>', 10006);
                }
                Db::table($table)->insert($marketTradeLog);

                break;
        }
    }

    /**
     * 市价交易框架!
     * @param $data
     * @return string
     */
    private function syncMysql_mtd($data)
    {
        try {
            $user_type = self::$userType;
            $feeAccountId = Db::table('user_info')
                ->where('user_type',$user_type)
                ->value('ui_id');
            Db::startTrans();
            foreach ($data as $value){
                extract($value['data']);
                $other['price'];
                $other['coin1'];
                $other['coin2'];
                $marketTradeF;
                $marketTradeR;
                $marketTradeLog;
//                $pairPrice = $marketTradeLog['price']; //实际成交价

                $map0 = [
                    ['ui_id','=',$marketTradeLog['mt_order_ui_id']],
                    ['ci_id','=',$other['coin1']],
                ];
                $map1 = [
                    ['ui_id','=',$marketTradeLog['mt_order_ui_id']],
                    ['ci_id','=',$other['coin2']],
                ];
                $map2 = [
                    ['ui_id','=',$marketTradeLog['mt_peer_ui_id']],
                    ['ci_id','=',$other['coin1']],
                ];
                $map3 = [
                    ['ui_id','=',$marketTradeLog['mt_peer_ui_id']],
                    ['ci_id','=',$other['coin2']],
                ];

                $map99 = [
                    ['ui_id','=',$feeAccountId],
                    ['ci_id','=',$other['coin1']],
                ];
                $map100 = [
                    ['ui_id','=',$feeAccountId],
                    ['ci_id','=',$other['coin2']],
                ];

                $userFinances = Db::table('user_finance')
                    ->whereOr([$map0,$map1])
                    ->order('ci_id','asc')
                    ->select();
                $valueFinances = Db::table('user_finance')
                    ->whereOr([$map2,$map3])
                    ->order('ci_id','asc')
                    ->select();

                $this->propertyChange(
                    $data,
                    $marketTradeF,
                    $marketTradeR,
                    $marketTradeLog,
                    $other,
                    $map0, $map1, $map2, $map3,
                    $map99, $map100,
                    $userFinances, $valueFinances
                );
            }
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            foreach ($data as $value) {
                extract($value['data']);
                $marketTradeF;
                $marketTradeR;
                $marketTradeLog;
                $marketTradeF['status'] = 8;
                $marketTradeR['status'] = 8;
                $marketTrade = [$marketTradeF,$marketTradeR];
                Db::name('exception_trade')->insertAll($marketTrade);
                Db::name('exception_trade_log')->insert($marketTradeLog);
            }
            $error = $this->myGetTrace($e);
            Log::write("unknown-error2 | 挂单数据写入数据库异常3，error:{$error}。",'SynchroError');
            return 'Error';
        }

    }

    /**
     * 为了保证更优撮合后 重新放入队列的 数据可以继续走撮合
     * @param $key
     * @param $value
     * @param $timeout
     * @param $isLock
     * @param $marketId
     */
//    protected function doWhile($key, $value, $timeout, $isLock, $marketId)
//    {
//        do {
//            $redis = $this->redis;
//            $isLock = $redis->set($key, $value, ['nx', 'ex'=>$timeout]);
//            if ($isLock) {
//                if ($redis->get($key) == $value) {  //防止提前过期，误删其它请求创建的锁
//
//                    /**开始执行内部代码**/
//                    //弹出数据
//                    $preData = json_decode($redis->lPop(self::$listKey.$marketId),true);
//                    if (!$preData){  //如果没有数据就终止 撮合!
//                        static $t = 1;
//                        if ($t > 10000){
//                            return ;
//                        }
//                        ++$t;
//                        continue;
//                    }
//
//                    //>>2.2.(挂买单)启动撮合$this->doTrade
//                    $re = $this->doTrade($preData);
//                    switch ($re):
//                        case 'Exception':
//                            //出现异常!通知管理员
//
//                            break;
//                        case 'DataException':
//                            //出现撮合过程 Redis事务 异常!通知管理员
//
//                            break;
//                        default:
//                            echo json_encode($re);
//                            break;
//                    endswitch;
//                    /**结束执行内部代码**/
//
//                    $redis->del($key);
//                    continue;//执行成功删除key并跳出循环
//                }
//            } else {
//                usleep(self::$usleep); //睡眠，降低抢锁频率，缓解redis压力，针对问题2
//            }
//        } while(!$isLock);//如果没有获取到锁 usleep(2500):表示每秒循环400次
//    }

    private function checkFinance($userFinance,$pair)
    {
        $trans_frost = $userFinance['trans_frost'];
        if (bcsub($trans_frost,$pair,self::BCPRECISEDIGITS) < 0){
            return false;
        }
        return true;
    }

    private function myGetTrace(Exception $e)
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

    private function outputJson($result){
        header('Content-Type: application/json');
        $json_array = array(
            'status' => $result['status'],
            'msg' => urlencode($result['msg']),
            'data' => $result['data']
        );
        return urldecode(json_encode($json_array));
    }
}
