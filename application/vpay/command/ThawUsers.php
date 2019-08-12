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
 * 冻结48小时后自动解冻
 * use app\vpay\command\ReleaseAssetsRundle;
 */

class ThawUsers extends Command
{
    protected function configure()
    {
        $this->setName('ThawUsers')->setDescription('冻结48小时后自动解冻');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln("冻结48小时后自动解冻 begin");

            $where['status'] = 2;
            $model   = new MakeorderModel();

            $users = Db::name('vpay_users')
                ->where($where)
                ->select();

            $setModel = new SettingsModel();
            $line_set = $setModel->getRows();

            $time = $line_set['make_money_thaw']*60*60;
            foreach ($users as $k=>$v){
                if((time()-$v['frozen_time'])>=$time){
                    //给用户解冻
                    $res = $model->save_user_status($v['user_id'],1);
                    if(empty($res)){
                        file_put_contents("TaskFreezeOrders_error.log",date("Y-m-d H:i:s").'--用户解冻失败--'.'用户id:'.$v['user_id'].PHP_EOL,FILE_APPEND);//记录日志
                    }
                }
            }

        $output->writeln("冻结48小时后自动解冻 end");

    }
}