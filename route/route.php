<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

//Route::get('think', function () {
//    return 'hello,ThinkPHP5!';
//});
//

//Route::get('hello/:name', 'index/hello');

/**设定自己的路由区域(撮合交易)start**/
//>>1.显示当前登录用户的可用币种余额
Route::post('myBalance', 'home/trade/myBalance');
//>>11.返回显示手续费和实际手续费
Route::post('fee', 'home/trade/fee');
//>>12.判断是否提示过设置交易密码
Route::post('tradePwdNotice', 'home/trade/tradePwdNotice');
//>>2.挂买单撮合交易
Route::post('upTradeBuy', 'home/trade/upTradeBuy');
//>>3.挂卖单撮合交易
Route::post('upTradeSell', 'home/trade/upTradeSell');
//>>13.用户挂单提交入口
//Route::post('upTrade', 'home/trade/upTrade');


//>>5.同步数据
//Route::get('dataPerMinute', 'home/Synchro/dataPerMinute');
//>>6.买卖未撮合的定时守护任务
Route::get('watchTrade', 'home/Synchro/watchTrade');


//>>7.用户输入数量,ajax调用此接口,验证限价余额!
Route::post('checkLimitBalance', 'home/trade/checkLimitBalance');
//>>8.用户输入价格,ajax调用此接口,验证价格10%的上下浮动
Route::post('checkPrice', 'home/trade/checkPrice');

//>>9.后台修改手续费或修改用户分组的时候,调用此接口以清除Redis的手续费信息
Route::post('feeEdit', 'home/Synchro/feeEdit');

//>>10.用户输入数量,ajax调用此接口,验证市价余额!
Route::post('checkMarketBalance', 'home/trade/checkMarketBalance');

/**设定自己的路由区域(撮合交易)end**/


//登录注册方法
Route::post('signinreg/login', 'signin/Signinreg/login');//登录
Route::post('signinreg/register', 'signin/Signinreg/register');//注册
Route::post('signinreg/agreement', 'signin/Signinreg/getAgreement');//注册协议
Route::post('signinreg/email', 'signin/Signinreg/sendregemail');//发送注册邮件
Route::post('signinreg/logout', 'business/Password/loginout');//退出登录
Route::post('signinreg/check', 'signin/Signinreg/accountCheck');//注册APP邮件验证接口

//修改登录密码，忘记密码.
Route::post('userpwd/uploginpwd', 'business/Password/updatepwd');//修改登录密码
Route::post('userpwd/forgemail', 'business/Password/sendfogemail');//发送
Route::post('userpwd/forgetupwd', 'business/Password/forgetupwd');//忘记登录密码/修改密码
//绑定账号|一键提取
Route::post('extract/sendbindcode', 'business/Bind/sendBindCodeSms');//发送绑定验证码
Route::post('extract/bindaccount', 'business/Bind/bindMobile');//绑定app账号
Route::post('extract/bindnList', 'business/Bind/bindList');//获取绑定的列表
Route::post('extract/unbind', 'business/Bind/unbindMobile');//解除绑定
Route::post('extract/balance', 'business/Bind/extractBalance');//一键提取
Route::post('market/currencyList','business/Sysinfo/coinList');//获取币种列表
Route::post('extension/list','business/Recharge/getExtensionList');//获取推广佣金记录

//充值提现相关
// Route::post('recharge/sendaddress','business/Recharge/getCoinAddress');//生成充值钱包地址
Route::group('recharge',function(){
	Route::post('sendaddress','business/Recharge/getCoinAddress');//生成充值钱包地址
	Route::post('getrechinfo','business/Recharge/getCoinInfo');//获取币种充值地址
	Route::post('getrechlist','business/Recharge/getRechargeRecode');//获取充值记录
	Route::post('getwithdraw','business/Recharge/getWithdrawParam');//获取提现参数
	Route::post('getwithlist','business/Recharge/getWithdrawRecode');//获取提现记录
	Route::post('wdemail','business/Recharge/sendWtithdrawEmail');//发送提现申请验证码
	Route::post('withdraw','business/Recharge/withdrawCoin');//提现申请
	Route::post('retract','business/Recharge/retractWithdraw');//撤回提现申请
	Route::post('rewithdesc','business/Sysinfo/rewithDesc');//提现充值描述文案
});

Route::post('task/charge', 'business/Task/notifyCharge');//充值接口

//swoole相关接口
Route::post('swoole/tradeinfo', 'home/swoole/tradeinfo');//获取交易信息

//个人中心委托记录，成交记录
Route::post('personal/milist', 'home/swoole/milist');//获取所有交易市场列表
Route::any('personal/putupinfo', 'home/personal/putupinfo');//获取委托记录信息(个人中心)
Route::post('personal/recordinfo', 'home/personal/recordinfo');//获取交易记录信息
Route::post('personal/putupcancel', 'home/personal/putupcancel');//撤销挂单
Route::post('personal/moptional', 'home/personal/moptional');//交易市场自选
Route::post('market/putupinfo', 'home/personal/mputupinfo');//获取委托记录信息(交易市场)
Route::any('market/ahlist', 'home/swoole/ahlist');//获取app首页展示交易对




//2018.10.12
Route::group('tourist',function (){
    Route::post('first_notice','home/tourist/firstNotice');//首页  置顶公告
    Route::post('bulletinlist','home/tourist/content');//公告列表
    Route::post('spreadactivity','home/tourist/spread_activity');//推广活动
    Route::post('fee','home/tourist/fee');//币种手续费
    Route::post('spreadrank','home/tourist/spreadRank');//游客模式下的排行榜
    Route::get('cny_rate','home/tourist/rateCNY');//获取人名币 汇率
    Route::post('exchange_cny','home/tourist/exchangeCNY');//币种兑换成人名币
    Route::post('act_list','home/tourist/actList');//活动列表
});

Route::group('per',function(){
    Route::post('set_trade_pwd','personal/Personal/setTradePwd');//设置交易密码
    Route::post('update_trade_pwd','personal/Personal/changeTradePwd');//修改/重置交易密码
    Route::post('send_email','personal/Personal/sendEmail');//修改/重置发送邮箱
    Route::post('reset_trade_pwd','personal/Personal/validateEmali');//修改/重置交易密码邮箱验证
    Route::post('check_trade_pwd','personal/Personal/checkTradePwd');//验证交易密码是否正确
    Route::post('change_option','personal/Personal/setTradeOption');//修改方式
    Route::post('tradetype','personal/Personal/selfTradeType');//个人的交易密码方式
    Route::post('issettrade','personal/Personal/isSetTradeType');//是否设置过交易密码

    Route::post('asset','personal/Personal/mineAsset');//我的资产
    Route::post('coin_add','personal/Personal/coinAdd');//地址列表
    Route::post('add_coin_add','personal/Personal/addCoinAdd');//添加地址
    Route::post('del_coin_add','personal/Personal/delCoinAdd');//删除地址
    Route::post('my_spread','personal/Personal/mineSpread');//我的推广
    Route::post('my_spread_record','personal/Personal/spreadRecord');//推广 邀请明细
});

Route::get('download/pic','home/tourist/downloadPic');//下载文件
Route::get('robot_info','robot/Robot/saveRobotInfo');
Route::get('robot_del_timer','robot/Robot/delRobotRedis');

//2018.11.28 APP相关接口
Route::group('app',function (){
    Route::post('carousel_list','app/App/lists');
    Route::post('first_notice','app/App/firstNotice');
    Route::post('asset','personal/Personal/appMineAsset');
    Route::post('coin_list','personal/Personal/coin_list');
});





//>>999.测试
Route::get('test', 'home/test/index');
Route::get('make', 'home/test/makeData');
Route::get('run', 'home/test/run');
Route::get('dataCreate', 'home/test/dataCreate');
Route::get('readData', 'home/test/readData');
Route::get('test1', 'home/test/test1');




/**分红**/
Route::group('privated',function(){

	Route::get('transfer', 'dividend/Privated/transfer');	/*每天456箱789号钱包转账*/
	Route::get('addSdt', 'dividend/Privated/addSdt');	/*用户虚增*/

	Route::get('snapshot', 'dividend/Privated/Snapshot');	/*快照*/
	Route::get('snapshotCharge', 'dividend/Privated/SnapshotCharge');	/*每日24点手续费快照*/

	Route::get('btcAnalyze', 'dividend/Privated/btcAnalyze');/*btc分红*/
	Route::get('preBtcAnalyze', 'dividend/Privated/preBtcAnalyze');/*预备--btc分红*/
	Route::get('sdtAnalyze', 'dividend/Privated/sdtAnalyze');	/*sdt分红*/
	Route::get('stopBtcAnalyze', 'dividend/Privated/stopBtcAnalyze');	/*禁止btc分红*/
	Route::get('unlockSdt', 'dividend/Privated/unlockSdt');	/*到期解仓*/
	Route::get('sendExpireLock', 'dividend/Privated/sendExpireLock');	/*提前2天给锁仓到期用户发email*/
	Route::get('cal', 'dividend/Privated/cal');	/*页面计算*/
	Route::get('editYesAcc', 'dividend/Privated/editYesAcc');	/*每天获取23:59分获取今日待分配(today_allocation)的数量*/

	Route::get('displayRankings', 'dividend/Privated/displayRankings');	/*每天获取23:59分获取今日待分配(today_allocation)的数量*/

});


/*页面调用*/
Route::group('page',function(){

	Route::get('displayCal', 'dividend/Page/displayCal');	/*页面显示*/
	Route::get('displayRan', 'dividend/Page/displayRan');	/*分红排行显示,累计分红---旧版本**/
	Route::get('displayRanNew', 'dividend/Page/displayRanNew');	/*分红排行显示,累计分红---新版本**/
	Route::get('latestDeal', 'dividend/Page/latestDeal');	/*最新成交显示排行*/
	Route::get('yesRan', 'dividend/Page/yesRan');	/*获取昨日SDT和BTC排行*/
	Route::post('getLockNum', 'dividend/Page/getLockNum');	/*锁仓最低系数*/

	Route::post('getTime', 'dividend/Page/getTime');	/*活动时间倒计时*/
	Route::get('showDividend', 'dividend/Page/showDividend');	/*显示分红*/


});


/*页面调用,个人中心*/
Route::group('user',function(){
	Route::post('lockSdt', 'dividend/PersonCenter/lockSdt');	/*锁仓*/
	Route::post('lockList', 'dividend/PersonCenter/lockList');	/*查询锁仓列表*/
	Route::post('editLockTime', 'dividend/PersonCenter/editLockTime');	/*锁仓续期修改*/
	Route::post('getAlloList', 'dividend/PersonCenter/getAlloList');	/*分红记录*/
});

/*页面调用,新设备*/
Route::group('newdevice',function(){
	Route::post('bindSn', 'dividend/NewDevice/bindSn');	/*绑定sn*/
	Route::post('getAllSn', 'dividend/NewDevice/getAllSn');	/*登录*/
	Route::post('bindingSn', 'dividend/NewDevice/bindingSn');	/*绑定数据*/


	Route::get('showAdd', 'dividend/NewDevice/showAdd');	/*显示增加设备数*/
	Route::get('calDividend', 'dividend/NewDevice/calDividend');	/*计算最新分红*/
	Route::get('getInfo', 'dividend/NewDevice/getInfo');	/*获取个人参加信息*/
	Route::get('btcDividend', 'dividend/NewDevice/btcDividend');	/*给所有新设备用户分红*/
});

//工具
Route::group('tool',function(){
	Route::any('check/sign', 'check/Index/sign');	/*绑定sn*/
	Route::any('send/sign', 'check/Index/signfrom');	/*生成签名*/
});


return [

];
