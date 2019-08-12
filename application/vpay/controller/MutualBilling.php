<?php

namespace app\vpay\controller;

use think\console\Input;
use think\Db;
use think\Controller;
use app\ClientbaseController;
use app\vpay\model\MakeOrderModel;
use think\facade\Session;
use think\Request;
use app\vpay\model\VpayUsers;
use app\mainadmin\model\SettingsModel;
use think\Log;
/**
 * 互助排队
 */

class MutualBilling extends ClientbaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function initialize(){
        $userId   = Session('userId');
        if(empty($userId)){
            $this->redirect('vpay/Login/index');
        }else{
            $userInfo =Db::name('vpay_users')
                ->where('user_id',$userId)
                ->find();
            $this->userInfo = $userInfo;
        }

    }

	/**
	 * 交易提现
	 * http://vpay.project.com/vpay/MutualBilling/index
	 */
	public function index()
	{
	    $userInfo = $this->userInfo;
	    $model = new MakeOrderModel();

        //获取今天需打款
        $output_order = $model->confirm_order($userInfo['user_id'],'','','');

        //获取今天需收款
        $entry_order = $model->confirm_order('',$userInfo['user_id'],'','');

        //获取排单记录
        $make_order = $model->get_order_info($userInfo['user_id'],'','',1);
        //获取等待收款
        $matching_order = $model->get_order_info('',$userInfo['user_id'],array(1,2),1);
        //获取完成收款记录
        $matched_order = $model->get_order_info('',$userInfo['user_id'],4,1);

        foreach ($matched_order as $k=>$v){
            $users = Db::name('users')
                ->field('user_name')
                ->where('user_id',$v['uid'])
                ->find();
            $matched_order[$k]['real_name'] = $users['user_name'];
        }

        //获取当前时间是否超过15点
        $hour = date('H',time());
        if($hour>=15){
            $display = 1;
        }else{
            $display = 2;
        }
        //获取排单金额设置
        $setModel = new SettingsModel();
        $line_set = $setModel->getRows();
//        $sea_num = $model->search_line_num($userInfo['user_id']);
//        if($sea_num<$line_set['matching_start']){
//            $is_one_make = 1;
//        }else{
//            $is_one_make = 2;
//        }
//        $this->assign('is_one_make',$is_one_make);
        $this->assign('make_order',$make_order);
        $this->assign('matching_order',$matching_order);
        $this->assign('matched_order',$matched_order);
        $this->assign('output_order',$output_order);
        $this->assign('entry_order',$entry_order);
        $this->assign('is_display',$display);
        $this->assign('user_info',$userInfo);
        $this->assign('line_set',$line_set);
		return view('MutualBilling/index');
	}

	//ajax查询排单记录
	public function ajaxmakeorder(){
        $data = input('post.');
        $model = new MakeOrderModel();

        if($data['type']==1){
            //获取排单记录
            $order = $model->get_order_info($data['uid'],'','',$data['p']);
        }else{
            $order = $model->get_order_info('',$data['user_id'],$data['status'],$data['p']);
        }

        foreach ($order as $k=>$v){
            if($data['type']==3){
                $users = Db::name('users')
                    ->field('user_name')
                    ->where('user_id',$v['uid'])
                    ->find();
                $order[$k]['real_name'] = $users['user_name'];
            }
            if($data['type']==1){
                $order[$k]['time'] = date('Y-m-d H:i:s',$v['create_time']);
                $order[$k]['str_status'] = monitoring_status($data['type'],$v['order_status']);
            }else{
                $order[$k]['time'] = date('Y-m-d H:i:s',$v['add_time']);
                $order[$k]['str_status'] = monitoring_status($data['type'],$v['status']);
            }

        }
        return  $order;

    }

	/**
	 * 今日需打款
	 * http://vpay.project.com/vpay/MutualBilling/todayReceipt
	 */
	public function todayReceipt()
	{
	    $id = input('id');
        $model = new MakeOrderModel();
	    $order  = $model->get_order_information($id,'');
        $pay_info = $model->get_user_payinfo($order['user_id']);
        $user_info = $this->userInfo;
        $user_personal = $model->user_personal($order['user_id']);
        $is_overtime = $model->is_overtime($id);//是否超过2小时

        $appType = Session::get("appType");
        $this->assign('appType',$appType);
	    $this->assign('order',$order);
        $this->assign('pay_info',$pay_info);
        $this->assign('user_info',$user_info);
        $this->assign('user_personal',$user_personal);
        $this->assign('is_overtime',$is_overtime);
		return view('MutualBilling/todayReceipt');
	}

	/**
	 * 今日需收款
	 * http://vpay.project.com/vpay/MutualBilling/todayPay
	 */
	public function todayPay()
	{
        $id = input('id');
        $model = new MakeOrderModel();
        $order  = $model->get_order_information($id,'');
//        $pay_info = $model->get_user_payinfo($order['user_id']);
        $pay_info = $model->get_user_payinfo($order['uid']);
//        $user_info = $this->userInfo;
        $user_info = Db::name('users')->where('user_id',$order['uid'])->find();
        $user_personal = $model->user_personal($order['user_id']);
        $is_overtime = $model->is_overtime($id);//是否超过2小时

        $this->assign('order',$order);
        $this->assign('pay_info',$pay_info);
        $this->assign('user_info',$user_info);
        $this->assign('user_personal',$user_personal);
        $this->assign('is_overtime',$is_overtime);
		return view('MutualBilling/todayPay');
	}

    /**
     * 更新打款的状态
     *
     */
    public function update_makeorder_status(){
        $model   = new MakeorderModel();
        $id = input('id');
        $order_status= input('order_status');
        $voucher_path = input('voucher');
        $make_info = Db::name('vpay_make_order')
            ->where('id',$id)
            ->find();

        $voucher = $_FILES['voucher'];
        if( $voucher['tmp_name']) {

            $voucher_path = $model->upload_img('voucher');
            if($voucher_path){
                $data['voucher'] = $voucher_path;
            }
        }
        if(empty($voucher['tmp_name'])&&empty($voucher_path)){
            $return['status'] = -1;
            $return['msg'] = '请上传打款凭证';
            return $return;
        }
        if($voucher_path){
            $data['voucher'] = $voucher_path;
        }
        $data['order_status'] = $order_status;
        $res =  Db::name('vpay_make_order')
            ->where('id',$id)
            ->update($data);
        $map['beat_time'] = time();
        Db::name('vpay_matching')
            ->where('id',$make_info['m_id'])
            ->update($map);
        if($res){
            $return['status'] = 1;
            $return['msg'] = '上传凭证成功';
        }else{
            $return['status'] = -1;
            $return['msg'] = '操作失败';
        }
        return  $return;
    }

    /**
     * 更新收款的状态
     *
     */
    public function update_matching_status(){

        $m_id = input('m_id');
        $status= input('status');
        $model   = new MakeorderModel();
        // 启动事务
        Db::startTrans();
        //更新收款状态
        try {
            $model->save_matching_status($m_id ,$status);
            if($status==4){
                $order = $model->get_order_information('', $m_id);
                $userInfo =Db::name('vpay_users')
                    ->where('user_id',$order['uid'])
                    ->find();
                $is_over = $model->save_complete($order['id'],$userInfo['user_id']);

                if($is_over==2){//已完成当天任务
                    $setModel = new SettingsModel();
                    $sets = $setModel->getRows();

                    if($userInfo['task_num']>=$sets['matching_day']){
                        //添加匹配机会
                        $this->add_matching($order['id'],$userInfo['user_id']);
                    }
                    //更新用户完成每日任务次数
                    $model->complete_task($userInfo['user_id']);
                }
            }
            // 更新成功 提交事务
            Db::commit();
           $return['status'] = 1;
           $return['msg'] = "确认成功";
           return $return;
        } catch (Exception $e) {
            // 更新失败 回滚事务
            Db::rollback();
            $return['status'] = -1;
            $return['msg'] = "确认成功";
            return $return;
        }


//        if($res){
//            $return['status'] = 1;
//            $return['msg'] = '操作成功';
//        }else{
//            $return['status'] = -1;
//            $return['msg'] = '操作失败';
//        }
//        return  $return;
    }

	/**
	 * 交易提现(历史打款记录)
	 * http://vpay.project.com/vpay/MutualBilling/historicalPayRecords
	 */
	public function historicalPayRecords()
	{
        $userInfo = $this->userInfo;
        $model   = new MakeorderModel();
        if(request()->isPost()){

            $p = input('p');
            $order = $model->get_order_info($userInfo['user_id'],'','',$p);
            foreach ($order as $k=>$v){
                $order[$k]['time'] = date('Y-m-d H:i:s',$v['create_time']);
                $order[$k]['str_status'] = monitoring_status(1,$v['order_status']);
            }
            return  $order;
        }else{

            $order = $model->get_order_info($userInfo['user_id'],'','',1);
            $this->assign('order',$order);
            return view('MutualBilling/historicalPayRecords');

        }
	}

    //添加匹配机会
    public function add_matching($origin_id,$user_id){
        $model = new MakeOrderModel();
        //两种匹配情况
        $rand = $model->get_Ab();
        //获取当前用户的优先级
        $priority_level = $model->get_priority_level($user_id);
        //添加匹配记录
        foreach ($rand as $k=>$v){
             $model->add_matching($user_id,null,$v,time(),null,null,null,null,$priority_level,$origin_id);
        }

    }

    //添加打款订单
    public function  add_order(){
        $userInfo =$this->userInfo;
        $model = new MakeOrderModel();
        $setModel = new SettingsModel();
        $sets = $setModel->getRows();

        $set_money = input('set_money');
        $set_sea = input('set_sea');
//        $hour = date("H");
//        if($hour<15){
//            $return['status'] = 10004;
//            $return['msg'] = "排单时间为00:00至15:00前";
//            return $return;
//        }
        if(empty($set_money)&&empty($set_sea)){
            return "请选择排单金额";
        }
        //获取排单设置
        if($userInfo['coupon']<$set_sea){
            $return['status'] = 10004;
            $return['msg'] = "sea券不足";
            return $return;
        }
        if($userInfo['asset_bundle']<$sets['asset_bundle_min']){
            $return['status'] = 10004;
            $return['msg'] = "资产包不足30万无法排单";
            return $return;
        }
        if($userInfo['status']==0){
            $return['status'] = 10004;
            $return['msg'] = "账号未激活";
            return $return;
        }
        if($userInfo['status']==2){
            $return['status'] = 10004;
            $return['msg'] = "账号冻结中,无法排单";
            return $return;
        }
        if($userInfo['status']==3){
            $return['status'] = 10004;
            $return['msg'] = "账号已经进入黑名单,无法排单";
            return $return;
        }
        $payinfo = $model->get_user_payinfo($userInfo['user_id']);
        if(empty($payinfo['wx_name'])||empty($payinfo['wx_number'])||empty($payinfo['alipay_name'])|empty($payinfo['alipay_number'])||empty($payinfo['bank_name'])||empty($payinfo['bank_user_name'])||empty($payinfo['bank_number'])||empty($payinfo['bank_branch'])||empty($payinfo['wx_code_img'])||empty($payinfo['alipay_code_img'])){
            $return['status'] = -1;
            $return['msg'] = "请完善收款信息后再排单";
            return $return;
        }

        $order_nun = $model->confirm_order($userInfo['user_id'],'','','');
        $seas = $model->search_record($userInfo['user_id']);
        if($order_nun||$seas){
            $return['status'] = 10004;
            $return['msg'] = "每天只能申请一次";
            return $return;
        }

        //获取判断第一阶段sea券排单的天数
        $sea_num = $model->search_line_num($userInfo['user_id']);
        if($sea_num<$sets['matching_start']){
            Db::startTrans();
            try{
                //更新用户sea券,记录用户sea消耗记录
                VpayUsers::userCouponChange($userInfo,$userInfo['coupon']-$set_sea,-$set_sea,'vpay_make_order','','排单消耗');
            } catch (Exception $e) {
                Db::rollback();
                $return['status'] = 10004;
                $return['msg'] = '排单失败';
                return $return;
            }
            Db::commit();
            $return['status'] = 10000;
            $return['msg'] = '排单成功';
            return $return;
            exit;
        }

        //第二阶段/第三阶段打款收款
        $dividing_line =  strtotime(date('Y-m-d 15:0:0',time()));
        $array = [$sets['make_order_one'],$sets['make_order_two']];
        Db::startTrans();
        foreach ($array as $k=>$v){
            //寻找等待匹配的记录
            $m_info = $model->search_mid($v,$userInfo['user_id']);
            if(empty($m_info)){
                //获取所有机器人
                $vest_user_id = $model->search_vest_user($userInfo['user_id']);
                if(empty($vest_user_id)){
                    $return['status'] = 10001;
                    $return['msg'] = "排单失败,待匹配不足";
                    return $return;
                }else{
                    //自动添加收款订单
                    $model->add_matching($vest_user_id,null,$v,time(),null,null,null,null,1,null);
                    $m_info = $model->search_mid($v,$userInfo['user_id']);
                }

            }
            //添加打款订单,并匹配
            $order_res = $model->add_order($m_info['id']);
            //更改收款订单信息
            $matching_res = $model->save_matching($m_info['id'],$userInfo['user_id']);
            //短信通知
            $users = $model->user_personal($userInfo['user_id']);

            $make_order_id = $order_res;
            if(empty($m_info)){
                Db::rollback();
                $return['status'] = 10001;
                $return['msg'] = "排单失败,待匹配不足";
                return $return;
            }
            if(empty($order_res)){
                Db::rollback();
                $return['status'] = 10002;
                $return['msg'] = "排单失败,添加打款失败";
                return $return;
            }
            if(empty($matching_res)){
                Db::rollback();
                $return['status'] = 10003;
                $return['msg'] = "排单失败,匹配失败";
                return $return;
            }

        }

        if(time()>$dividing_line){
            $model->send_message($users['mobile']);
        }else{
            //待发送通知,等三点后再发
            $model->add_send_message($m_info['id'],$userInfo['user_id'],$users['mobile'],1);
        }

        //更新用户消耗的sea券
        //$userModel = new VpayUsers();
        $order_info = Db::name('vpay_make_order')
                ->where('m_id',$m_info['id'])
                ->find();
        $make_order_id = $order_info['id'];
        VpayUsers::userCouponChange($userInfo,$userInfo['coupon']-$set_sea,-$set_sea,'vpay_make_order',$make_order_id,'排单消耗');

        Db::commit();
        $return['status'] = 10000;
        $return['msg'] = '排单成功';
        return $return;
    }

    //申诉
    public function complain_order(){
	    $id = input('id');
        $model  = new MakeorderModel();

        $order =$model->get_order_information($id,'');
            Db::startTrans();
        //冻结打款订单
        $res1 = $model->save_makeorder_status($id,2);
        //冻结收款订单
        $res2  = $model->save_matching_status($order['m_id'],3);
        if($res1&&$res2){
            Db::commit();
            $return['status'] = 1;
            $return['msg'] = '申诉成功';
            return $return;
        }else{
            Db::rollback();
            $return['status'] = -1;
            $return['msg'] = '申诉失败';
            return $return;
        }
    }

}
