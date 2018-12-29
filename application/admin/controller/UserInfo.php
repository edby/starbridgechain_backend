<?php
namespace app\admin\controller;


use app\admin\model\UserInfoModel;
use think\Controller;
use think\Db;
use think\Request;

class UserInfo extends Controller
{
    /*
     * 注册用户 列表
     */
    public function regUserList(UserInfoModel $user,Request $request)
    {
        $page = $request->param('page','1');
        $type = $request->param('group_id','');
        $keywords = $request->param('keywords','');

        $start = $request->param('start','');
        $end = $request->param('end','');

        $where[] = ['ui.status','=',0];
        $where[] = ['gi.status','=',1];

        if ($type != ''){
            $type = json_decode($type);
            $where[] = ['gi.gi_id','in',$type];
        }

        if ($keywords != ''){
            $where[] = ['ui.account','like','%'.$keywords.'%'];
        }

        if ($start != '' && $end != '' && $start > $end){
            ouputJson('201',lang('PARAM_ERROR'));
        }

        if ($start != ''){
            $start_str = strtotime($start);
            $where[] = ['ui.createTime','egt',$start_str];
        }

        if ($end != ''){
            $end_str = strtotime($end);
            $where[] = ['ui.createTime','elt',$end_str];
        }

        $field = [
            'ui.ui_id as id',
            'ui.account as account',
            'ui.email as email',
            'gi.name as group_name',
            'if(ui.createTime="","",FROM_UNIXTIME(ui.createTime,"%Y-%m-%d")) as createTime'
        ];

        if ($request->method() == 'GET'){
            $lists = $user
                ->alias('ui')
                ->field($field)
                ->join('user_group ug','ug.ui_id = ui.ui_id')
                ->join('group_info gi','gi.gi_id = ug.gi_id')
                ->where($where)
                ->select();
            set_time_limit(0);
            ini_set('memory_limit','-1');
        }else{
            $lists = $user
                ->alias('ui')
                ->field($field)
                ->join('user_group ug','ug.ui_id = ui.ui_id')
                ->join('group_info gi','gi.gi_id = ug.gi_id')
                ->where($where)
                ->paginate(10,false,['page'=>$page]);
        }

        //统计个人币种合计
        $data = [];
        foreach ($lists as $list) {
            $res = Db::table('user_finance')->alias('uf')
                ->join('coin_info ci','ci.ci_id = uf.ci_id')
                ->field('ci.short_name,uf.amount,uf.trans_frost,uf.out_frost')
                ->where('uf.ui_id','=',$list['id'])
                ->select();
            $list['amount'] = $list['amountd'] = 0;

            bcscale(2);
            foreach ($res as $re) {
                //获取兑换USDT
                $usdt = get_time_price($re['short_name']);
                $list['amount'] =  bcadd($list['amount'],bcmul($usdt,$re['amount']));
                $list['amountd'] = bcadd($list['amountd'],bcmul($usdt,bcadd($re['trans_frost'],$re['out_frost'])));
            }
            if ($request->method() == 'GET'){
                $data[] = [
                    'account'=>$list['account'],
                    'email'=>$list['email'],
                    'group_name'=>$list['group_name'],
                    'amount'=>$list['amount'],
                    'amountd'=>$list['amountd'],
                    'createTime'=>$list['createTime']
                ];
            }
        }
        //get 导出表单
        if ($request->method() == 'GET'){
            $title = ['account'=>'账户名','email'=>'邮箱','group_name'=>'分组名','amount'=>'可用','amountd'=>'冻结','createTime'=>'创建时间'];

            $filename = '用户列表';

            export_excel_zip($filename,$title,$data);

        }else{
            ouputJson('200',lang('SUCCESS'),$lists);
        }
    }

    /*
     * 帐号管理
     */
    public function userManage(UserInfoModel $user,Request $request)
    {
        $limit = $request->param('limit','10');
        $page = $request->param('page','1');
        $account = $request->param('account','');
        $email = $request->param('email','');
        $status = $request->param('status','');

        $where = [];
        $where[] = ['gi.status','=',1];

        if ($status != ''){
            $where[] = ['ui.status','=',$status];
        }

        if ($account != ''){
            $where[] = ['ui.account','like','%'.$account.'%'];
        }

        if ($email != ''){
            $where[] = ['ui.email','like','%'.$email.'%'];
        }

        $field = [
            'ui.ui_id as id',
            'ui.account as account',
            'ui.email as email',
            'gi.name as group_name',
            'ui.status as status'
        ];

        $lists = $user->alias('ui')
            ->field($field)
            ->join('user_group ug','ug.ui_id = ui.ui_id')
            ->join('group_info gi','gi.gi_id = ug.gi_id')
            ->where($where)
            ->paginate($limit,false,['page'=>$page]);

        ouputJson('200',lang('SUCCESS'),$lists);
    }

    /*
     * 修改账户状态
     */
    public function changeStatus(UserInfoModel $user,Request $request)
    {
        $id = $request->param('id','');
        $status = $request->param('status','');
        if ($id == '' || $status == ''){
            ouputJson('201',lang('PARAM_ERROR'));
        }

        $where = ['ui_id'=>$id];
        $data = ['status'=>$status];

        $oldstatus = $user->where($where)->value('status');
        if ($oldstatus == $status){
            ouputJson('201',lang('THE_SAME_TWO_TIMES'));
        }

        $res = $user->where($where)->update($data);
        if ($res){
            ouputJson('200',lang('SUCCESS'));
        }else{
            ouputJson('201',lang('SYSTEM_ERROR'));
        }

    }

    /*
     * 平台资产
     */
    public function platformAssets()
    {
        $sql = "SELECT ci.short_name AS name,SUM(uf.amount) AS amount,(SUM(uf.trans_frost)+SUM(uf.out_frost)) AS amountd FROM user_finance AS uf JOIN coin_info AS ci ON ci.ci_id = uf.ci_id GROUP BY uf.ci_id";
        $lists = Db::query($sql);
        if (empty($lists)){
            ouputJson('201',lang('NO_DATA'));
        }else{
            $data = [];
            foreach ($lists as $list) {
                //获取USDT
                $usdt = get_time_price($list['name']);
                $data[] = [
                    'name'=>$list['name'],
                    'amount'=>$list['amount']+0,
                    'amountd'=>$list['amountd']+0,
                    'amount_usdt'=>$list['amount']*$usdt,
                    'amount_usdt'=>$list['amountd']*$usdt,
                    'total'=>$list['amount'] + $list['amountd'],
                    'total_usdt'=>$list['amount']*$usdt + $list['amountd']*$usdt
                ];
            }

            ouputJson('200',lang('SUCCESS'),[$data]);
        }
    }

    /*
     * 用户资产
     */
    public function userAssets(Request $request,UserInfoModel $userInfoModel)
    {

        $page = $request->param('page','1');
        $coin = $request->param('coin','');
        $sort = $request->param('sort','');//排序
        $status = $request->param('status','0');

        $where = [];
        //状态（0正常，1：冻结，2：操作锁定）
        if ($status == '0'){
            $where[] = ['ui.status','=',0];
        }elseif($status == '1'){
            $where[] = ['ui.status','=',1];
        }elseif($status == '2'){
            $where[] = ['ui.status','=',2];
        }

        $sort = $sort ? 'desc' : 'asc';

        //获取所有用户id
        $field = [
            'ui.ui_id as id',
            'ui.account as account',
            'ui.status as status'
        ];
        if ($coin != '' && $sort != ''){

            $where[] = ['uf.ci_id','=',$coin];

            $ids = $userInfoModel->alias('ui')
                ->join('user_finance uf','uf.ui_id = ui.ui_id')
                ->join('coin_info ci','ci.ci_id = uf.ci_id')
                ->field($field)
                ->order("uf.amount $sort,id asc")
                ->where($where)
                ->paginate(10,false,['page'=>$page]);
        }else{

            $ids = $userInfoModel->alias('ui')
                ->field($field)
                ->where($where)
                ->paginate(10,false,['page'=>$page]);
        }

        $field_f = [
            'ci.short_name as short_name',
            'uf.amount as amount',
            '(uf.trans_frost + uf.out_frost) as amountd'
        ];

        foreach ($ids->items() as $id) {

            $where = ['uf.ui_id'=>$id->id];
            $coin = Db::table('user_finance')->alias('uf')
                ->field($field_f)
                ->join('coin_info ci','ci.ci_id = uf.ci_id')
                ->where($where)
                ->select();
            $id->coin = $coin;
        }

        foreach ($ids->items() as $item) {
            foreach ($item->coin as $val) {
                $amount = $val['short_name'].'可用余额';
                $amountd = $val['short_name'].'冻结余额';
                $item->$amount = $val['amount'];
                $item->$amountd = $val['amountd'];
            }
            unset($item->coin);
        }

        ouputJson('200',lang('SUCCESS'),$ids);
    }
}