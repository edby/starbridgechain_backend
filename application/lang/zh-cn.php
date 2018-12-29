<?php 

//登录注册
$signreg = [
	'ACCOUNT_PWD_NOTNULL' 	=>	'账号或密码不能为空!',
	'ACCOUNT_NOT_EXITS' 	=>	'账号不存在！',
	'ACCOUNT_PWD_ERROR' 	=>	'账号或密码错误！',
	'ACCOUNT_LOCK' 			=>	'您的账户已被锁定，请联系管理员处理！',
	'LOGIN_SUCCESS' 		=>	'登录成功！',
	'PLASE_SEND_CODE' 		=>	'请先发送验证码！',
	'CODE_ERROR' 			=>	'验证码错误！',
	'INPUT_CODE' 			=>	'请输入验证码！',
	'ACCOUNT_EXITS' 		=>	'该账号已经注册，可直接前往登录！',
	'INVALID_INCODE' 		=>	'无效的邀请码！',
	'REGERROR_SMANAGE' 		=>	'注册失败,请联系管理员！',
	'REG_SUCCESS' 			=>	'注册成功！',
	'REG_FAILD_TRAGAIN' 	=>	'注册失败，请稍后再试!',
	'PWD_FORMAT_ERROR'		=>	'密码必须含有字母和数字！',
	'PWD_ET_OX'				=>	'密码长度错误(8-20位)',
	'ACC_NOT_NULL'			=>	'账号不能为空！',
	'ACCOUNT_FORMAT_ERROR'	=>	'账号格式错误！',
	'PWD_NOT_NULL'			=>	'密码不能为空',
	'EMAIL_TYPE_ERROR'		=>	'类型错误',
	'NO_EMAIL_PARAM'		=>	'无邮箱配置数据',
	'SEND_SUCCESS'			=>	'发送成功',
	'EMAIL_FORMAT_ERROR'	=>	'邮箱格式错误',
	'EMAIL_REGED'			=>	'该邮箱已被注册',
	'EMAIL_WAIT_SEND'		=>	'60秒内只能发送一次!',
	'PLASE_INPUT_EMAIL'		=>  '请输入邮箱账号!',
    'PLASE_TRY_SEND'        =>  '请重新发送验证码。',
    'AUCODE_ERROR'          =>  '验证码错误',
    'ACCOUNT_UNKNOW'        =>  '该账号不存在',
    'UP_SUCC'               =>  '修改成功',
    'UP_FAIL'               =>  '修改失败，请稍后再试！',
    'PLAST_ACPWD'           =>  '请输入账号或密码',
    'ACC_BIND'              =>  '账号已被绑定！',
    'SF_ONEMIN'             =>  '发送失败！短信1分钟内只能发送1条',
    'SF_DATFI'              =>  '发送失败！短信当天最多只能发送5条',
    'FROM_ERROR'            =>  '表单参数错误！',
    'BIND_SUCCESS'          =>  '绑定成功',
    'BIND_FAIL'             =>  '绑定失败，请稍后再试！',
    'BIND_FAIL_RN'          =>  '解绑失败，记录不存在',
    'UNBIND_SUC'            =>  '解绑成功',
    'UNBIND_FAIL'           =>  '解绑失败，请稍后再试！',
    'CH_ACCOUNT'            =>  '请选择提取的账号',
    'WT_FAIL_NOBA'          =>  '提取失败，未绑定此账号',
    'WT_FAIL_NB'            =>  '提取失败，暂无提取余额！',
    'WT_FAIL_TRY'           =>  '提取失败，请稍后再试！',
    'NEWP_COM_OP'           =>  '修改失败，新密码与原密码相同！',
    'WT_SUCC'               =>  '提取成功！',
    'WT_FAIL_NOACC'         =>  '提取失败，未查询到此账户！',
    'WT_FAIL_QUBF'          =>  '提取失败，查询账户余额失败！',
    'COIN_UNKNOW'           =>  '币种不存在或不可用',
    'ACCADDR_NOUSE'         =>  '账户地址无法使用！',
    'CREATE_FIAL'           =>  '创建失败，无法创建地址',
    'SENDFAIL_ACCNOEMAIL'   =>  '发送失败，账户未绑定邮箱！',
    'INPUT_COMOUNT'         =>  '请输入正确的提现数量！',
    'ACCOUNT_UNKNOW'        =>  '账户不存在！',
    'WTCOUNT_XYFEE'         =>  '提现数量不得低于提现手续费！',
    'DCOUNT_MAX'            =>  '单次最大提现数为：',
    'DCOUNT_MIN'            =>  '单次最小提现数为：',
    'DAYMY_END'             =>  '今日提现额度已用完，请明日再试！',
    'YEAR_END'              =>  '本年度提现额度已用完！',
    'BALANCE_NOTINVA'       =>  '账户可用余额不足！',
    'WT_APPLY_SUC'          =>  '提现申请提交成功！',
    'WT_APPLY_FAIL'         =>  '提现失败，请稍后再试',
    'WT_APPLY_UNKOW'        =>  '提现申请不存在！',
    'CHING_WTAPPLY'         =>  '只能撤回审核中的提现申请',
    'CHSUCCESS'             =>  '撤回成功',
    'CHFAIL'                =>  '撤回失败，请稍后再试',
    'ACCOUNT_LOCK'          =>  '您的账户已被锁定，请联系管理员处理！',
    'ACCOUNT_ERROR'         =>  '账户异常，请重新登录',
    'TRYEND_REQUEST'        =>  '重复请求',
    'SYSTEM_ERROR'          =>  '系统错误，请联系管理员',
    'SYSOUT_SUCC'           =>  '登出成功',
    'STSTEM_BUSY'           =>  '服务器忙，请稍后再试！'
];
//密码验证
$pwdcheck = [
	'UP_LPWD_F_ERROR' 	=>	'原密码格式错误!',
	'UP_LPWD_F_NEW' 	=>	'新密码格式错误!',
	'UP_LPWD_F_NOXT' 	=>	'两次密码不一致!',
	'UP_LPWD_F_NOXD' 	=>	'不能和原密码相同!',
];

//撮合交易
$tradeMsg = [
    'TRANS_PWD_ERROR'                   =>  '您的交易密码输入错误!',
    'LMT_PRICE_BIT'                     =>  '输入的价格小数点需小于:',
    'LMT_DECIMAL_BIT'                   =>  '输入的数量小数点需小于:',
    'LMT_PRICE_BUY'                     =>  '购买价格$price不能低于:',
    'LMT_PRICE_BUY1'                    =>  '购买价格$price不能高于:',
    'LMT_PRICE_SELL'                    =>  '卖出价格$price不能高于:',
    'LMT_PRICE_SELL1'                   =>  '卖出价格$price不能低于:',
    'SUFFICIENT_FUNDS'                  =>  '您的余额不足!!',
    'MINIMUM_NUMBER'                    =>  '最小输入数量为:',
    'PLEASE_TRY_LATER'                  =>  '撮合数据过多,请稍后尝试!!',
    'FREEZE_DATA_FAILED'                =>  '冻结数据写入数据库时失败!!',
    'ERROR_NUMBER'                      =>  '价格不能为0!',
    'NETWORK_IS_BUSY'                   =>  '网络繁忙,请稍后再试!',
];

//交易密码
$tradePwd = [
    'PWD_HAS_SET'                       =>  '交易密码已被设置!',
    'PWD_NOT_SET'                       =>  '没有设置交易密码!',
    'PWD_LESS_INPUT_ONCE'               =>  '密码输入不完整!',
    'PWD_TWICE_DIF'                     =>  '两次密码输入不一致!',
    'PWD_SET_SUC'                       =>  '操作成功!',
    'PWD_SET_F'                         =>  '操作失败!',
    'PWD_IS_ERROR'                      =>  '密码错误!',
    'PWD_FORMAL_ERROR'                  =>  '密码格式错误!',
];

//10-24
$msg = [
    'SUCCESS'                           =>  '操作成功!',
    'FAIL'                              =>  '操作失败!',
    'NO_DATA'                           =>  '暂无数据!',
    'SYSTEM_ERROR'                      =>  '系统错误!',
    'ID_ERROR'                          =>  '无效的id!',
    'PARAM_ERROR'                       =>  '参数错误!',
    'RELATION_EXIST'                    =>  '关联已存在!',
    'NO_ADMIN'                          =>  '该管理员不存在!',
    'NO_ROLE'                           =>  '该角色不存在',
    'NO_RELATION'                       =>  '关联不存在!',
    'NO_FILE'                           =>  '文件不存在!',
    'REPEAT_UPDATE'                     =>  '不可重复修改!',
    'NUMBER'                            =>  '参数必须是数字!',
    'EMAIL_ERROR'                       =>  '邮箱错误!',
    'THE_SAME_TWO_TIMES'                =>  '两次修改值一样!',
    'THE_SAME_PWD'                      =>  '新密码不能与旧密码相同，请重新设置!',
];


//个人中心交易记录，委托记录
$personal = [
    'PER_NO_DATA'                       =>  '暂无数据!',
    'PER_GET_SUCCESS'                   =>  '获取成功',
    'PER_GET_FAILED'                    =>  '获取失败',
    'PER_REQUEST_FAILED'                =>  '请求出错',
    'PER_NOT_ALLOW_CANCEL'              =>  '挂单状态不允许撤销',
    'PER_ACTION_SUCCESS'                =>  '操作成功',
    'PER_ACTION_FAILED'                 =>  '操作失败',

];



return array_merge($signreg,$tradeMsg,$pwdcheck,$tradePwd,$msg,$personal);