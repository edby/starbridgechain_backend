<?php
/**
 * Created by PhpStorm.
 * User: gh
 * Date: 2018/5/17
 * Time: 9:59
 */
namespace app\admin\controller;
use think\Config;
use think\Controller;
use think\Db;
use think\Request;

class Dividend extends Controller{

	/*修改btc分红基数*/
	public function updateBtc(Request $request)
	{
		$btc = $request->param('num','');
		$setting=Db::name('u_setting_btc')->find();
		if($btc=='')return json(['code' => 200,'msg' => '获取成功','btc_base'=>$setting['btc_base']]);

		$re=Db::name('u_setting_btc')->where(['id'=>$setting['id']])->setField('btc_base',$btc);

		if($re){
			return json(['code' => 200,'msg' => '修改成功','btc_base'=>$btc]);
		}else{
			return json(['code' => 201,'msg' => '修改失败','btc_base'=>$setting['btc_base']]);
		}

	}

	/*修改页面显示分红上浮下午比例*/
	public function updateFloat(Request $request)
	{
		$floating_up = floatval($request->param('floating_up',''));
		$floating_down = floatval($request->param('floating_down',''));
		/*if($floating_down<0.99)return json(['code' => 201,'msg' => '下浮不能小于0.99']);
        if($floating_up>1.005)return json(['code' => 201,'msg' => '上浮不能大于1.05']);*/

		$setting=Db::table('u_setting_btc')->find();
		$info['floating_up']=$setting['floating_up'];
		$info['floating_down']=$setting['floating_down'];
		if($floating_up==''&&$floating_down=='')return json(['code' => 200,'msg' => '获取成功','floating_base'=>$info]);
		if($floating_up==''||$floating_down=='')return json(['code' => 200,'msg' => '修改的时候2个参数不能为空']);
		$re=Db::name('u_setting_btc')->where(['id'=>$setting['id']])->update(['floating_up'=>$floating_up,'floating_down'=>$floating_down]);

		if($re){
			$info['floating_up']=$floating_up;
			$info['floating_down']=$floating_down;
			return json(['code' => 200,'msg' => '修改成功','floating_base'=>$info]);
		}else{
			$info['floating_up']=$setting['floating_up'];
			$info['floating_down']=$setting['floating_down'];
			return json(['code' => 201,'msg' => '修改失败','floating_base'=>$info]);
		}

	}


	/*修改sdt分红基数*/
	public function updateSdtBase(Request $request)
	{
		$sdt = $request->param('num','');
		$setting=Db::name('u_setting_btc')->find();

		if($sdt=='')return json(['code' => 200,'msg' => '获取成功','sdt_base'=>$setting['sdt_base']]);

		$sdt=(int)$sdt;
		$re=Db::name('u_setting_btc')->where(['id'=>$setting['id']])->setField('sdt_base',$sdt);

		if($re){
			return json(['code' => 200,'msg' => '修改成功','sdt_base'=>$sdt]);
		}else{
			return json(['code' => 201,'msg' => '修改失败','sdt_base'=>$setting['sdt_base']]);
		}

	}

	/*增加入池设备数量*/
	public function addEquipmentNum(Request $request){

		$batch = $request->param('batch',1810);
		$num = $request->param('num',10);
		$setting=Db::table('u_newuser_setting')->where(['type'=>1,'batch'=>$batch])->find();
		if($num=='')return json(['code' => 200,'msg' => '获取成功','equentment_base'=>$setting['add_num']]);

		if($setting==null){
			return json(['code' => 201,'msg' => '记录不存在!']);
		}
		$num_aa=bcadd($num,$setting['add_num']);
		$re=Db::table('u_newuser_setting')->where(['type'=>1,'batch'=>$batch])->setField('add_num',$num_aa);

		$add_num=Db::table('u_newuser_setting')->field('add_num')->where(['type'=>1,'batch'=>$batch])->find();
		if($re){
			return json(['code' => 200,'msg' => '成功!','equentment_base'=>$add_num['add_num']]);
		}else{
			return json(['code' => 201,'msg' => '失败,请重试!']);
		}


	}

	/*修改新设备分红基数*/
	public function updateNewBtc(Request $request)
	{
		$btc = $request->param('num');

		$setting=Db::name('u_newuser_setting')->find();
		if($btc=='')return json(['code' => 200,'msg' => '获取成功','new_btc_base'=>$setting['btc_base']]);

		$re=Db::name('u_newuser_setting')->where(['id'=>$setting['id']])->setField('btc_base',$btc);

		if($re){
			return json(['code' => 200,'msg' => '修改成功','new_btc_base'=>$btc]);
		}else{
			return json(['code' => 201,'msg' => '修改失败','new_btc_base'=>$setting['btc_base']]);
		}

	}



	/*修改新设备面显示分红上浮下午比例*/
	public function updateNewFloat(Request $request)

	{
		$floating_up = floatval($request->param('floating_up',''));
		$floating_down = floatval($request->param('floating_down',''));
		/*if($floating_down<0.99)return json(['code' => 201,'msg' => '下浮不能小于0.99']);
        if($floating_up>1.005)return json(['code' => 201,'msg' => '上浮不能大于1.05']);*/

		$setting=Db::table('u_newuser_setting')->where(['type'=>1,'batch'=>1810])->find();
		$info['floating_up']=$setting['floating_up'];
		$info['floating_down']=$setting['floating_down'];
		if($floating_up==''&&$floating_down=='')return json(['code' => 200,'msg' => '获取成功','floating_base'=>$info]);
		if($floating_up==''||$floating_down=='')return json(['code' => 200,'msg' => '修改的时候2个参数不能为空']);
		$re=Db::name('u_newuser_setting')->where(['id'=>$setting['id']])->update(['floating_up'=>$floating_up,'floating_down'=>$floating_down]);
		if($re){
			$info['floating_up']=$floating_up;
			$info['floating_down']=$floating_down;
			return json(['code' => 200,'msg' => '修改成功','floating_base'=>$info]);
		}else{
			$info['floating_up']=$setting['floating_up'];
			$info['floating_down']=$setting['floating_down'];
			return json(['code' => 201,'msg' => '修改失败','floating_base'=>$info]);
		}

	}


	/*修改上传参数*/
	public function editUpload(Request $request)

	{
		$status = $request->param('status',''); //status=1是iOS，2是android

		if($status==''){
			$info=Db::table('u_upload_config')->select();
			return json(['code' => 200,'msg' => '获取','data'=>$info]);
		}

		$version = $request->param('version','');
		$version_code = $request->param('version_code','');
		$change_log = $request->param('change_log','');
		$url= $request->param('url','');
		$type = $request->param('type','');


		if($version=='')return json(['code' => 201,'msg' => 'version,版本号不能为空']);
		if($version_code=='')return json(['code' => 201,'msg' => 'version_code,不能为空']);
		if($change_log=='')return json(['code' => 201,'msg' => 'change_log修改日志不能为空']);
		if($type=='')return json(['code' => 201,'msg' => 'type不能为空']);

		$re=Db::table('u_upload_config')->where(['status'=>$status])->update([
			'version'=>$version,
			'version_code'=>$version_code,
			'change_log'=>$change_log,
			'url'=>$url,
			'type'=>$type,
		]);
		if($re){
			$info=Db::table('u_upload_config')->select();
			return json(['code' => 200,'msg' => '修改成功','data'=>$info]);
		}else{
			return json(['code' => 201,'msg' => '修改失败']);
		}

	}

	/*锁仓快照系数*/
	public function updateCoefficient(Request $request)
	{
		$coefficient = $request->param('coefficient','');
		$setting=Db::table('u_setting_btc')->limit(1)->select();
		if($coefficient==''){
			return json(['code' => 200,'msg' => '获取成功','data'=>$setting[0]['coefficient']]);
		}
		if((int)$coefficient<=0){
			return json(['code' => 201,'msg' => '修改失败,系数不能为空或者<=0']);
		}

		$re=Db::name('u_setting_btc')->where(['id'=>$setting[0]['id']])->setField('coefficient',(int)$coefficient);
		if($re){
			return json(['code' => 200,'msg' => '修改成功','data'=>$coefficient]);
		}else{
			return json(['code' => 201,'msg' => '修改失败','data'=>$setting[0]['coefficient']]);
		}

	}

	/*锁仓基数及生效时间*/
	public function insertLockbase(Request $request)
	{
		$sdtBase = $request->param('sdtBase','');

		if($sdtBase==''){
			$re=Db::table('u_setting_lock')->select();
			return json(['code' => 200,'data' => $re]);
		}
		if((int)$sdtBase<=0){
			return json(['code' => 201,'msg' => '锁仓基数不能<=0']);
		}
		$re=Db::table('u_setting_lock')->insert([
			'sdt_base'=>$sdtBase,
			'batch'=>$sdtBase,
			'create_time'=>date("Y-m-d H:i:s"),
		]);
		$info=Db::table('u_setting_lock')->select();
		if($re){
			return json(['code' => 200,'msg' => '添加成功','data'=>$info]);
		}else{
			return json(['code' => 201,'msg' => '添加失败']);
		}

	}

	/*查询钱包和路由账号绑定关系查询*/
	//-----未修改
	public function  selectMobile(Request $request){
		$email = $request->param('email','');
		$mobile = $request->param('mobile','');
		$type = $request->param('type','');
		$limit = $request->param('limit');
		$page = $request->param('page');
		if($limit ==''|| (int)$limit<=0)
			$limit=10;
		if($page==''|| (int)$page<=0)
			$page=1;

		$where=[];
		if($email !='')
			$where['user_info.email']=$email;
		if($mobile !='')
			$where['u_purse_mobile.mobile_num']=$mobile;
		if($type!='')
			$where['u_purse_mobile.type']=$type;

		$info=Db::table('u_purse_mobile')
			->field('user_info.email,user_finance.amount,u_purse_mobile.mobile_num,u_purse_mobile.user_id,u_purse_mobile.id')
			->join('user_info','user_info.ui_id=u_purse_mobile.user_id','left')
			->join('user_finance','user_finance.ui_id=u_purse_mobile.user_id','left')
			->where(['ci_id'=>1])
			->where($where)
			->limit(((int)$page-1)*10,(int)$limit)
			->select();
		$count=Db::table('u_purse_mobile')
			->field('user_info.email,user_finance.amount,u_purse_mobile.mobile_num,u_purse_mobile.user_id,u_purse_mobile.id')
			->join('user_info','user_info.ui_id=u_purse_mobile.user_id','left')
			->join('user_finance','user_finance.ui_id=u_purse_mobile.user_id','left')
			->where(['ci_id'=>1])
			->where($where)
			->count();
		return json(['code' => 200,'msg' => '获取成功','count'=>$count,'data'=>$info]);

	}


	/*锁仓数据展示及查询*/
	public function  showLock(Request $request){
		$email = $request->param('email','');
		$start_time = $request->param('start_time','');
		$end_time = $request->param('end_time','');
		$time_type = $request->param('time_type','');
		$type = $request->param('type','');
		$status = $request->param('status','');

		$limit = $request->param('limit');
		$page = $request->param('page');
		if($limit ==''|| (int)$limit<=0)
			$limit=10;
		if($page==''|| (int)$page<=0)
			$page=1;

		$where=[];
		if($email !='')
			$where[]=['user_info.email',$email];
		if($type !='')
			$where[]=['u_sdt_lock.type',$type];
		if($status !='')
			$where[]=['u_sdt_lock.status',$status];

		if($start_time!=''){
			if($time_type=='lock'){
				$where[] = ['u_sdt_lock.create_time','>=' ,$start_time];
			}else{
				$where[] = ['u_sdt_lock.lock_time','>=' ,$start_time];
			}

		}

		if($end_time!=''){
			if($time_type=='lock'){
				$where[] = ['u_sdt_lock.create_time','<=' ,$end_time];
			}else{
				$where[] = ['u_sdt_lock.lock_time','<=' ,$start_time];
			}
		}

		if($start_time!=''&&$end_time!=''){
			if($time_type=='lock'){
				$where[] = ['u_sdt_lock.create_time','>=' ,$start_time,$end_time];
			}else{
				$where[] = ['u_sdt_lock.lock_time','<=' ,$start_time,$end_time];
			}

		}

		$info=Db::table('u_sdt_lock')
			->field('user_info.email,u_sdt_lock.id,u_sdt_lock.user_id,u_sdt_lock.lock_nums,u_sdt_lock.lock_time,u_sdt_lock.type,u_sdt_lock.status,u_sdt_lock.batch,u_sdt_lock.create_time')
			->join('user_info','user_info.ui_id=u_sdt_lock.user_id')
			->where($where)
			->limit(((int)$page-1)*10,(int)$limit)
			->select();
		$count=Db::table('u_sdt_lock')
			->field('user_info.name,u_sdt_lock.id,u_sdt_lock.user_id,u_sdt_lock.lock_nums,u_sdt_lock.lock_time,u_sdt_lock.type,u_sdt_lock.status,u_sdt_lock.batch,u_sdt_lock.create_time')
			->join('user_info','user_info.ui_id=u_sdt_lock.user_id','left')
			->where($where)
			->count();

		return json(['code' => 200,'msg' => '获取成功','count'=>$count,'data'=>$info]);

	}

	/*下载绑定关系*/
	public function uploadMobile(Request $request){
		$email = $request->param('email','');
		$mobile = $request->param('mobile','');
		$type = $request->param('type','');
		$where=[];
		if($email !='')
			$where['user_info.email']=$email;
		if($mobile !='')
			$where['u_purse_mobile.mobile_num']=$mobile;
		if($type!='')
			$where['u_purse_mobile.type']=$type;

		$info=Db::table('u_purse_mobile')
			->field('user_info.email,user_finance.amount,u_purse_mobile.mobile_num,u_purse_mobile.user_id,u_purse_mobile.id')
			->join('user_info','user_info.ui_id=u_purse_mobile.user_id','left')
			->join('user_finance','user_finance.ui_id=u_purse_mobile.user_id','left')
			->where(['ci_id'=>1])
			->where($where)
			->select();


		//重组数组  处理数据
		$out_data=[];
		foreach ($info as $v) {
			$out_data[] = [
				'id'=>$v['id'],
				'email'=>$v['email'],
				'mobile_num'=>$v['mobile_num'],
				'amount'=>$v['amount'],
				'user_id'=>$v['user_id']
			];
		}

		$title = ['id'=>'id','email'=>'邮箱','mobile_num'=>'电话','amount'=>'账户余额','user_id'=>'用户id'];
		$filename = '绑定关系';

		export_excel_zip($filename,$title,$out_data);
	}



	/*下载锁仓数据*/
	public function  uploadLock(Request $request){
		$email = $request->get('email', '');
		$start_time = $request->get('start', '');
		$end_time = $request->get('end', '');
		$time_type = $request->get('type', '');

		$type = $request->param('lock_type', '');
		$status = $request->param('status', '');

		$where = [];
		if ($email != '')
			$where['user_info.email'] = $email;
		if ($type != '')
			$where['u_sdt_lock.type'] = $type;
		if ($status != '')
			$where['u_sdt_lock.status'] = $status;

		if ($start_time != '') {
			if ($time_type == 'lock') {
				$where['u_sdt_lock.create_time'] = ['>=', $start_time];
			} else {
				$where['u_sdt_lock.lock_time'] = ['>=', $start_time];
			}

		}

		if ($end_time != '') {
			if ($time_type == 'lock') {
				$where['u_sdt_lock.create_time'] = ['<=', $end_time];
			} else {
				$where['u_sdt_lock.lock_time'] = ['<=', $start_time];
			}
		}

		if ($start_time != '' && $end_time != '') {
			if ($time_type == 'lock') {
				$where['u_sdt_lock.create_time'] = ['>=', $start_time];
				$where['u_sdt_lock.create_time'] = ['<=', $end_time];
			} else {
				$where['u_sdt_lock.lock_time'] = ['>=', $start_time];
				$where['u_sdt_lock.lock_time'] = ['<=', $end_time];
			}

		}


		$info = Db::table('u_sdt_lock')
			->field('user_info.name,u_sdt_lock.id,u_sdt_lock.user_id,u_sdt_lock.lock_nums,u_sdt_lock.lock_time,u_sdt_lock.type,u_sdt_lock.status,u_sdt_lock.batch,u_sdt_lock.create_time')
			->join('user_info', 'user_info.ui_id=u_sdt_lock.user_id', 'left')
			->where($where)
			->select();

		//重组数组  处理数据
		$out_data = [];
		foreach ($info as $v) {
			$out_data[] = [
				'id'=>$v['id'],
				'name'=>$v['name'],
				'user_id'=>$v['user_id'],
				'lock_nums'=>$v['lock_nums'],
				'lock_time'=>$v['lock_time'],
				'type'=>$v['type'],
				'status'=>$v['status'],
				'batch'=>$v['batch'],
				'create_time'=>$v['create_time'],
			];
		}
		//设置头
		$title = ['id'=>'id','name'=>'姓名','user_id'=>'用户id',
			'lock_nums'=>'锁仓数量',
			'lock_time'=>'锁仓时间',
			'type'=>'是否自动续仓',
			'status'=>'状态',
			'batch'=>'批次',
			'create_time'=>'创建时间',
		];
		$filename = '锁仓数据';

		export_excel_zip($filename,$title,$out_data);
	}


}