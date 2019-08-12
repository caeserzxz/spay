<?php

namespace app\vpay\model;
use app\BaseModel;
use think\facade\Cache;

use think\facade\Log;
use think\facade\Config;
use think\Exception;

/**
 * sea通证转出
 * use app\vpay\model\VpayTurnOutPassCard;
 */

class VpayTurnOutPassCard extends BaseModel
{
	protected $table = 'vpay_turn_out_pass_card';
	private static $tableName = 'vpay_turn_out_pass_card';
	public  $pk = 'id';
	protected static $mkey = 'vpay_turn_out_pass_card_list';

	public function cleanMemcache(){
		Cache::rm(self::$mkey);
	}

	// sea通证转资产包
	public static function passCardTrunAssetsBundle($u_id, $amount){
		$userInfo            = VpayUsers::where('user_id', $u_id)->field('level_id,pass_card,asset_bundle,cumulative_asset_bundle,chain,status')->find();
		$userInfo['user_id'] = $u_id;
		VpayUsers::userFrozenStatus($userInfo);

		$configMultiple      = Config::get('multiple_pass_card_turn_asset_bundle');
		$getAssetsBundle     = $amount * $configMultiple;
		$getAssetsBundle     = VpayUsers::getAmountLastTwo($getAssetsBundle);
		$assetsBundleAfter   = $userInfo['asset_bundle'] + $getAssetsBundle;
		$balanceAfter        = $userInfo['pass_card'] - $amount;
		if($balanceAfter < 0) throw new Exception('sea通证不足扣减');

		$arr = [
			'out_user_id'             => $userInfo['user_id'],
			'out_amount'              => $amount,
			'out_balance_after'       => $balanceAfter,
			'get_assets_bundle'       => $getAssetsBundle,
			'get_assets_bundle_after' => $assetsBundleAfter,
			'multiple_assets_bundle'  => $configMultiple,
			'time'                    => time(),
		];
		$id = self::insertGetId($arr);
		$desc = 'EAC通证转资产包';

		// 减少通证
		VpayUsers::userPassCardChange($userInfo, $balanceAfter, -$amount, self::$tableName, $id, $desc);

		// 增加资产包
		VpayUsers::userAssetBundleChange($userInfo, $assetsBundleAfter, $getAssetsBundle, self::$tableName, $id, $desc);

		return [
			'outMobile' => $userInfo->user->mobile,
			'outAmount' => $arr['out_amount'],
			'getAmount' => $arr['get_assets_bundle'],
		];
	}

}