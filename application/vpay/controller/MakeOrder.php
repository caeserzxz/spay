<?php

namespace app\vpay\controller;

use think\Db;
use think\Model;
use think\Controller;
use app\ClientbaseController;
use app\vpay\model\MakeOrderModel;
use app\vpay\model\VpayUsers;
use think\Log;

class MakeOrder extends controller
{
    protected $userInfo = [];

    public function initialize(){
        $this->Model = new MakeOrderModel();
        $userInfo =Db::name('vpay_users')
                 ->where('id',2)
                 ->find();
        $this->userInfo = $userInfo;
    }

    public function index()
    {
        $this->fetch();
    }


    //添加打款订单
    public function  add_order(){
        $userInfo =$this->userInfo;
        $model = new MakeOrderModel();
        $set_id = input('set_id');
        if(empty($set_id)){
            return "请选择排单金额";
        }

        //获取排单设置
        $sets = $model->get_line_up($set_id);
        if($userInfo['coupon']<$sets['set_sea']){
            return "sea券不足";
        }
        if($userInfo['status']==0){
            dump('账号未激活');die;
        }
        if($userInfo['status']==2){
            dump('账号冻结中,无法排单');die;
        }
        if($userInfo['status']==3){
            dump('账号已经进入黑名单,无法排单');die;
        }
        $order_nun = $model->confirm_order($userInfo['id'],'','','');
        if($order_nun){
            dump('每天只能申请一次');die;
        }

        $array = [800,1200];
        Db::startTrans();
        foreach ($array as $k=>$v){
            //寻找等待匹配的记录
            $m_info = $model->search_mid($v,$userInfo['user_id']);
            //添加打款订单,并匹配
            $order_res = $model->add_order($m_info['id']);
            //更改收款订单信息
            $matching_res = $model->save_matching($m_info['id'],$userInfo['user_id']);

            if(empty($m_info)){
                $return['status'] = 10001;
                $return['msg'] = "排单失败";
                return $return;
            }
            if(empty($order_res)){
                $return['status'] = 10002;
                $return['msg'] = "排单失败";
                return $return;
            }
            if(empty($matching_res)){
                $return['status'] = 10003;
                $return['msg'] = "排单失败";
                return $return;
            }

        }

        //更新用户消耗的sea券
        $userModel = new VpayUsers();
        $userModel->userCouponChange($userInfo,$userInfo['coupon']-100,-100,'vpay_make_order',91,'排单消耗');

        Db::commit();
        $return['status'] = 10000;
        $return['msg'] = '排单成功';
        return $return;
    }

    //冻结每天未打款的订单
    public  function TaskFreezeOrders(){
        $time_section = time_section(time());
        $start_time = $time_section['start'];
        $end_time = $time_section['end'];
        //15点时间戳
        $dividing_line =  strtotime(date('Y-m-d 15:0:0',time()));
        $where['status'] = array('in','2');
        $where['order_status'] = array('in','1');

        $order =  Db::name('vpay_make_order')
            ->field('a.*,b.status,b.uid,b.user_id,b.money')
            ->alias('a')
            ->join('vpay_matching b','a.m_id = b.id')
            ->where($where)
            ->where('a.create_time', 'between', [$start_time, $end_time])
            ->select();

        $time = 2*60*60;
        $model   = new MakeorderModel();
        foreach ($order as $k=>$v){
            //匹配时间超过15点的,就按匹配后2小时算,不超过的按照15点后两个小时算
            if($v['create_time']<$dividing_line){
                if((time()-$dividing_line)>=$time){
                    Db::startTrans();
                    //更新打款订单---失败
                    $res1 = $model->save_makeorder_status($v['id'],4);
                    //更新收款订单---失败
                    $res2 = $model->save_matching_status($v['m_id'],5);
                    //给收款订单重新匹配订单
                    $res3 = $model->new_make_order($v['m_id']);
                    //冻结账户
                    $res4 = $model->save_user_status($v['uid'],2);

                    if($res1&&$res2&&$res3&&$res4){
                        Db::commit();
                        Log::error("提交成功：".time()."_订单号：");
                        dump('提交成功');
                    }else{
                        Db::rollback();
                        file_put_contents("TaskFreezeOrders_error.log",date("Y-m-d H:i:s").'--冻结用户出错--'.'打款id='.$v['id'].';收款订单id='.$v['m_id'].';打款人user_id='.$v['uid'].PHP_EOL,FILE_APPEND);//记录日志
                        dump('提交失败');
                    }
                }
            }else{
                if((time()-$v['create_time'])>=$time){
                    Db::startTrans();
                    //更新打款订单---失败
                    $res1 = $model->save_makeorder_status($v['id'],4);
                    //更新收款订单---失败
                    $res2 = $model->save_matching_status($v['m_id'],5);
                    //给收款订单重新匹配订单
                    $res3 = $model->new_make_order($v['m_id']);
                    //冻结账户
                    $res4 = $model->save_user_status($v['uid'],2);

                    if($res1&&$res2&&$res3&&$res4){
                        Db::commit();
                        dump('提交成功');
                    }else{
                        Db::rollback();
                        file_put_contents("TaskFreezeOrders_error.log",date("Y-m-d H:i:s").'--冻结用户出错--'.'打款id='.$v['id'].';收款订单id='.$v['m_id'].';打款人user_id='.$v['uid'].PHP_EOL,FILE_APPEND);//记录日志
                        dump('提交失败');
                    }
                }
            }
        }

    }


    //冻结48小时后自动解冻
    public function ThawUsers(){
        $where['status'] = 2;
        $model   = new MakeorderModel();

        $users = Db::name('vpay_users')
            ->where($where)
            ->select();

        $time = 48*60*60;
        foreach ($users as $k=>$v){
            if((time()-$v['frozen_time'])>=$time){
                //给用户解冻
                $res = $model->save_user_status($v['id'],1);
                if(empty($res)){
                    file_put_contents("TaskFreezeOrders_error.log",date("Y-m-d H:i:s").'--用户解冻失败--'.'用户id:'.$v['id'].PHP_EOL,FILE_APPEND);//记录日志
                }
            }
        }
    }

    //15点给15点排单的订单发送通知短信
    public function SendMessage(){
        $model = new MakeorderModel();
        $list = Db::name('vpay_send_message')
            ->where('status',1)
            ->select();

        foreach ($list as $k=>$v){
            $res = $model->send_message($v['mobile']);
             if($res['error_code']==0){
                 $map['status'] = 2;
             }else{
                 $map['status'] = 3;
             }
            $map['reason'] = $res['reason'];

            Db::name('vpay_send_message')
                ->where('id',$v['id'])
                ->update($map);
        }
    }

    public function ceshi(){
        $model = new MakeorderModel();
        $model->save_user_status(1,2);
    }
}