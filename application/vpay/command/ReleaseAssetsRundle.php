<?php

namespace app\vpay\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use app\vpay\model\VpayTurnOutAssetsBundle;
use app\mainadmin\model\SettingsModel;

use think\Db;
use think\Exception;
use think\facade\Log;

/**
 * 释放每个用户前一天待释放的资产包
 * use app\vpay\command\ReleaseAssetsRundle;
 */

class ReleaseAssetsRundle extends Command
{
    protected function configure()
    {
        $this->setName('ReleaseAssetsRundle')->setDescription('释放每个用户前一天待释放的资产包');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln("释放每个用户前一天待释放的资产包 begin");

    	Db::startTrans();
		try {
			SettingsModel::setConfig();
	    	VpayTurnOutAssetsBundle::arriveEveryDay();

			Db::commit();
        } catch (Exception $e) {
        	Db::rollback();
            Log::error($e->getCode() . '：' . (string) $e);
            dump($e->getMessage());
        }

        $output->writeln("释放每个用户前一天待释放的资产包 end");
    }
}