<?php

namespace app\vpay\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use app\vpay\model\MakeOrderModel;
use app\vpay\model\VpayUsers;

use think\Db;
use think\Exception;
use think\facade\Log;

/**
 * 给15点前排单的订单发送通知短信
 * use app\vpay\command\ReleaseAssetsRundle;
 */

class SendMessage extends Command
{
    protected function configure()
    {
        $this->setName('SendMessage')->setDescription('给15点前排单的订单发送通知短信');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln("给15点前排单的订单发送通知短信 begin");
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

        $output->writeln("给15点前排单的订单发送通知短信 end");
    }
}