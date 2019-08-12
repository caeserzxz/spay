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
 * 计算每个用户当天释放的资产包
 * use app\vpay\command\CalculationAssetsBundle;
 */

class CalculationAssetsBundle extends Command
{
    protected function configure()
    {
        $this->setName('CalculationAssetsBundle')->setDescription('计算每个用户当天释放的资产包');
    }

    protected function execute(Input $input, Output $output)
    {

    	Db::startTrans();
		try {
    		SettingsModel::setConfig();
	    	VpayTurnOutAssetsBundle::everyDayCalculationReleaseAmount();

			Db::commit();
        } catch (Exception $e) {
        	Db::rollback();
            Log::error($e->getCode() . '：' . (string) $e);
            dump($e->getMessage());
        }

        $output->writeln("计算每个用户当天释放的资产包 end");
    }
}