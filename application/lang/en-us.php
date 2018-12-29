<?php 

//登录注册
$signreg = [
	'ACCOUNT_PWD_NOTNULL' 	=>	'account format error',
	'ACCOUNT_NOT_EXITS' 	=>	'account not exits',
	'ACCOUNT_PWD_ERROR' 	=>	'account or password error ',
	'ACCOUNT_LOCK' 			=>	'account lock,call admin please',
	'LOGIN_SUCCESS' 		=>	'successed！',
	'PLASE_SEND_CODE' 		=>	'please send code',
	'CODE_ERROR' 			=>	'code error！',
	'INPUT_CODE' 			=>	'input code',
	'ACCOUNT_EXITS' 		=>	'account exits,please log in',
	'INVALID_INCODE' 		=>	'invitation code is invalid',
	'REGERROR_SMANAGE' 		=>	'registration failed,call the admin',
	'REG_SUCCESS' 			=>	'registration success',
	'REG_FAILD_TRAGAIN' 	=>	'registration failed，try again later!',
	'PWD_FORMAT_ERROR'		=>	'Password must contain letters and numbers',
	'PWD_ET_OX'				=>	'Password must contain letters and numbers Wrong password length(8-20位)',
	'ACC_NOT_NULL'			=>	'account cannot be null',
	'ACCOUNT_FORMAT_ERROR'	=>	'account  error',
	'PWD_NOT_NULL'			=>	'password cannot be null',
	'EMAIL_TYPE_ERROR'		=>	'ype error',
	'NO_EMAIL_PARAM'		=>	'No mailbox configuration data',
	'SEND_SUCCESS'			=>	'Sent successfully',
	'EMAIL_FORMAT_ERROR'	=>	'Incorrect mailbox format',
	'EMAIL_REGED'			=>	'The mailbox has been registered',
	'EMAIL_WAIT_SEND'		=>	'Can only be sent once in 60 seconds',
	'PLASE_INPUT_EMAIL'		=>  'Please enter your email account',
    'PLASE_TRY_SEND'        =>  'Please resend the verification code.',
    'AUCODE_ERROR'          =>  'Verification code error',
    'ACCOUNT_UNKNOW'        =>  'The account does not exist',
    'UP_SUCC'               =>  'Successfully modified',
    'UP_FAIL'               =>  'The modification failed, please try again later!',
    'PLAST_ACPWD'           =>  'Please enter an account or password',
    'ACC_BIND'              =>  'The account has been bound!',
    'SF_ONEMIN'             =>  'Failed to send! SMS can only send 1 in 1 minute',
    'SF_DATFI'              =>  'Failed to send! You can only send up to 5 messages on the same day.',
    'FROM_ERROR'            =>  'The form parameters are wrong!',
    'BIND_SUCCESS'          =>  'Binding success',
    'BIND_FAIL'             =>  'The binding failed, please try again later!',
    'BIND_FAIL_RN'          =>  'Untie failed, record does not exist',
    'UNBIND_SUC'            =>  'Untied successfully',
    'UNBIND_FAIL'           =>  'Untie failed, please try again later！',
    'CH_ACCOUNT'            =>  'Please select the extracted account',
    'WT_FAIL_NOBA'          =>  'Failed to extract, this account is not bound',
    'WT_FAIL_NB'            =>  'The extraction failed, no balance has been drawn yet!',
    'WT_FAIL_TRY'           =>  'The extraction failed, please try again later!',
    'NEWP_COM_OP'           =>  'The modification failed, the new password is the same as the original password',
    'WT_SUCC'               =>  'Successful extraction！',
    'WT_FAIL_NOACC'         =>  'Failed to extract, this account has not been queried！',
    'WT_FAIL_QUBF'          =>  'Failed to fetch, query account balance failed！',
    'COIN_UNKNOW'           =>  'Currency does not exist or is not available',
    'ACCADDR_NOUSE'         =>  'The account address is not available!',
    'CREATE_FIAL'           =>  'Creation failed, unable to create address',
    'SENDFAIL_ACCNOEMAIL'   =>  'Failed to send, not bound mail account!',
    'INPUT_COMOUNT'         =>  'Please enter the correct withdrawal amount!',
    'ACCOUNT_UNKNOW'        =>  'Account does not exist!',
    'WTCOUNT_XYFEE'         =>  'The withdrawal amount must not be less than the withdrawal fee!',
    'DCOUNT_MAX'            =>  'The maximum number of single withdrawals is:',
    'DCOUNT_MIN'            =>  'The single minimum withdrawal number is:',
    'DAYMY_END'             =>  'Today`s withdrawal amount has been used up, please try again tomorrow!',
    'YEAR_END'              =>  'The amount of withdrawals this year has run out!',
    'BALANCE_NOTINVA'       =>  'Insufficient account available balance!',
    'WT_APPLY_SUC'          =>  'Withdrawal application submitted successfully!',
    'WT_APPLY_FAIL'         =>  'Withdrawals failed, please try again later',
    'WT_APPLY_UNKOW'        =>  'The withdrawal request does not exist!',
    'CHING_WTAPPLY'         =>  'Withdrawals can only withdraw the application for review',
    'CHSUCCESS'             =>  'Withdraw successfully',
    'CHFAIL'                =>  'Withdrawal failed, please try again later',
    'ACCOUNT_LOCK'          =>  'Your account has been locked, please contact the administrator!',
    'ACCOUNT_ERROR'         =>  'Abnormal account, please sign in again',
    'TRYEND_REQUEST'        =>  'Repeat request',
    'SYSTEM_ERROR'          =>  'System error, please contact administrator',
    'SYSOUT_SUCC'           =>  'Logout successful',
    'STSTEM_BUSY'           =>  'The server is busy, please try again later!'
];
//密码验证
$pwdcheck = [
	'UP_LPWD_F_ERROR' 	=>	'Original password format error!',
	'UP_LPWD_F_NEW' 	=>	'The new password is malformed!',
	'UP_LPWD_F_NOXT' 	=>	'The new password is malformed!',
	'UP_LPWD_F_NOXD' 	=>	'Cannot be the same as the original password!',
];

//撮合交易
$tradeMsg = [
    'TRANS_PWD_ERROR'                   =>  'Your transaction password input error!',
    'LMT_PRICE_BIT'                     =>  'Enter the decimal point must be less than the price:',
    'LMT_DECIMAL_BIT'                   =>  'Decimal point must be less than the number of input:',
    'LMT_PRICE_BUY'                     =>  'Purchase price $price can not be lower than:',
    'LMT_PRICE_BUY1'                    =>  'Purchase price $price cannot be higher than:',
    'LMT_PRICE_SELL'                    =>  'Sell price $price cannot be higher than:',
    'LMT_PRICE_SELL1'                   =>  'Sell price $price can`t be lower than:',
    'SUFFICIENT_FUNDS'                  =>  'Your balance is not enough!!',
    'MINIMUM_NUMBER'                    =>  'The minimum number of inputs is:',
    'PLEASE_TRY_LATER'                  =>  'Too much data, please try later!!',
    'FREEZE_DATA_FAILED'                =>  'Failed to freeze data written to database!!',
    'ERROR_NUMBER'                      =>  'The price cannot be 0!',
    'NETWORK_IS_BUSY'                   =>  'Network is busy, please try again later!',
];

//交易密码
$tradePwd = [
    'PWD_HAS_SET'                       =>  'Transaction password has been set!',
    'PWD_NOT_SET'                       =>  'No transaction password is set!',
    'PWD_LESS_INPUT_ONCE'               =>  'Password is not complete!',
    'PWD_TWICE_DIF'                     =>  'Enter the password twice inconsistent!',
    'PWD_SET_SUC'                       =>  'Successful operation!',
    'PWD_SET_F'                         =>  'operation failed!',
    'PWD_IS_ERROR'                      =>  'incorrect password!',
    'PWD_FORMAL_ERROR'                  =>  'Password malformed!',
];

//10-24
$msg = [
    'SUCCESS'                           =>  'Successful operation!',
    'FAIL'                              =>  'operation failed!',
    'NO_DATA'                           =>  'No data!',
    'SYSTEM_ERROR'                      =>  'system error!',
    'ID_ERROR'                          =>  'Invalid id!',
    'PARAM_ERROR'                       =>  'Parameter error!',
    'RELATION_EXIST'                    =>  'The association already exists!',
    'NO_ADMIN'                          =>  'The administrator does not exist!',
    'NO_ROLE'                           =>  'This role does not exist',
    'NO_RELATION'                       =>  'Association does not exist!',
    'NO_FILE'                           =>  'file does not exist!',
    'REPEAT_UPDATE'                     =>  'Unrepeatable modification!',
    'NUMBER'                            =>  'Parameter must be a number!',
    'EMAIL_ERROR'                       =>  'E-mail error!',
    'THE_SAME_TWO_TIMES'                =>  'Modify the same value twice!',
];


//个人中心交易记录，委托记录
$personal = [
    'PER_NO_DATA'                       =>  'No data!',
    'PER_GET_SUCCESS'                   =>  'Succeed',
    'PER_GET_FAILED'                    =>  'failed',
    'PER_REQUEST_FAILED'                =>  'Request error',
    'PER_NOT_ALLOW_CANCEL'              =>  'Pending state does not allow withdrawal',
    'PER_ACTION_SUCCESS'                =>  'Successful operation',
    'PER_ACTION_FAILED'                 =>  'failed  operation',

];


return array_merge($signreg,$tradeMsg,$pwdcheck,$tradePwd,$msg,$personal);