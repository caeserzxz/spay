<?php

namespace app\vpay\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use app\vpay\model\MakeOrderModel;
use app\vpay\model\VpayUsers;
use app\mainadmin\model\SettingsModel;

use think\Db;
use think\Exception;
use think\facade\Log;

/**
 * 冻结每天未打款的订单
 * use app\vpay\command\ReleaseAssetsRundle;
 */

class TaskFreezeOrders extends Command
{
    protected function configure()
    {
        $this->setName('TaskFreezeOrders')->setDescription('冻结每天未打款的订单');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln("冻结每天未打款的订单 begin");
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
        $setModel = new SettingsModel();
        $line_set = $setModel->getRows();

        $time = $line_set['make_money_hour']*60*60;
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
        $output->writeln("冻结每天未打款的订单 end");
    }
}