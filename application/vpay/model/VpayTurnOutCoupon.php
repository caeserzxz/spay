<?php

namespace app\vpay\model;
use app\BaseModel;
use think\facade\Cache;

use think\facade\Log;
use think\facade\Config;
use think\Exception;

/**
 * sea券转出
 * use app\vpay\model\VpayTurnOutCoupon;
 */

class VpayTurnOutCoupon extends BaseModel
{
	protected $table = 'vpay_turn_out_coupon';
	private static $tableName = 'vpay_turn_out_coupon';
	public  $pk = 'id';
	protected static $mkey = 'vpay_turn_out_coupon_list';

	public function cleanMemcache(){
		Cache::rm(self::$mkey);
	}

	// 转出人的sea券资产包情况
	public static function trunOutUserAmount($userInfo, $amount, $configMultiple){
		$configSuperior    = config::get('superior_turn_asset_bundle');
		$getAssetsBundle   = VpayUsers::calculationAssetsBundle($amount, $configSuperior, $configMultiple);
		$assetsBundleAfter = $userInfo['asset_bundle'] + $getAssetsBundle;
		$balanceAfter      = $userInfo['coupon'] - $amount;
		return [
			'get_assets_bundle'            => $getAssetsBundle,
			'get_assets_bundle_after'      => $assetsBundleAfter,
			'get_assets_bundle_proportion' => $configSuperior,
			'out_balance_after'            => $balanceAfter,
			'out_amount'                   => $amount,
			'out_user_id'                  => $userInfo['user_id'],
		];
	}


	// 接收人的sea券和资产包情况
	public static function receiveUserAmount($userInfo, $amount, $configMultiple){
		$configAssetsBundle = config::get('subordinate_turn_asset_bundle');
		$getAssetsBundle    = VpayUsers::calculationAssetsBundle($amount, $configAssetsBundle, $configMultiple);
		$assetsBundleAfter  = $userInfo['asset_bundle'] + $getAssetsBundle;

		$configCoupon       = config::get('subordinate_turn_coupon');
		$getCoupon          = VpayUsers::profitProportionAmount($amount, $configCoupon);
		$couponAfter        = $userInfo['coupon'] + $getCoupon;

		return [
			'receive_user_id'                  => $userInfo['user_id'],
			'receive_assets_bundle_proportion' => $configAssetsBundle,
			'receive_assets_bundle'            => $getAssetsBundle,
			'receive_assets_bundle_after'      => $assetsBundleAfter,
			'receive_coupon_proportion'        => $configCoupon,
			'receive_coupon'                   => $getCoupon,
			'receive_coupon_after'             => $couponAfter,
		];
	}

	// 转赠sea券给好友
	public static function couponTrunFriends($u_id, $friendUId, $amount){

		// 用户A
		$userInfo            = VpayUsers::where('user_id', $u_id)->field('level_id,coupon,asset_bundle,cumulative_asset_bundle,chain,status')->find();
		$userInfo['user_id'] = $u_id;
		$configMultiple      = Config::get('multiple_asset_bundle'); // 资产包的倍数
		VpayUsers::userFrozenStatus($userInfo);
		if($userInfo['coupon'] - $amount < 0) throw new Exception('EAC券不足扣减');

		// 用户B
		$field                     = 'level_id,coupon,cumulative_coupon,asset_bundle,cumulative_asset_bundle,chain,status';
		$friendUserInfo            = VpayUsers::where('user_id', $friendUId)->field($field)->find();
		$friendUserInfo['user_id'] = $friendUId;
		VpayUsers::userFrozenStatus($friendUserInfo);

		// 判断用户是否是上下级关系
		$rst = VpayUsers::supervisorSubordinateGuanxi($userInfo, $friendUserInfo);
		if(!$rst) throw new Exception('转账用户不在团队内');

		$trunOutUser = self::trunOutUserAmount($userInfo, $amount, $configMultiple); // 用户A减少sea券和增加资产包
		$receiveUser = self::receiveUserAmount($friendUserInfo, $amount, $configMultiple); // 用户B增加sea券和资产包

		$arr = [
			'multiple_assets_bundle' => $configMultiple,
			'time'                   => time(),
			'type'                   => 1,
		];
		$arr = array_merge($arr, $trunOutUser, $receiveUser);
		$id = self::insertGetId($arr);

		// 用户A减少sea券和增加资产包
		VpayUsers::userCouponChange($userInfo, $trunOutUser['out_balance_after'], -$amount, self::$tableName, $id, '赠送团队成员');
		VpayUsers::userAssetBundleChange($userInfo, $trunOutUser['get_assets_bundle_after'], $trunOutUser['get_assets_bundle'], self::$tableName, $id, '赠送EAC券获得');

		// 用户B增加sea券和资产包
		VpayUsers::userCouponChange($friendUserInfo, $receiveUser['receive_coupon_after'], $receiveUser['receive_coupon'], self::$tableName, $id, '团队成员赠送');
		VpayUsers::userAssetBundleChange($friendUserInfo, $receiveUser['receive_assets_bundle_after'], $receiveUser['receive_assets_bundle'], self::$tableName, $id, '团队成员赠送');

		return [
			'outMobile'        => $userInfo->user->mobile,
			'outAmount'        => $arr['out_amount'],
			'getAmount'        => $arr['get_assets_bundle'],
			'receiveMobile'    => $friendUserInfo->user->mobile,
			'receiveAmount'    => $arr['receive_coupon'],
			'receiveGetAmount' => $arr['receive_assets_bundle'],
		];
	}



	// sea券转sea通证
	public static function couponTrunPassCard($u_id, $amount){
		$userInfo            = VpayUsers::where('user_id', $u_id)->field('level_id,pass_card,coupon,cumulative_coupon,asset_bundle,status')->find();
		$userInfo['user_id'] = $u_id;
		VpayUsers::userFrozenStatus($userInfo);

		$configMultiple      = Config::get('multiple_pass_card');
		$getPassCard         = $amount * $configMultiple;
		$getPassCard         = VpayUsers::getAmountLastTwo($getPassCard);
		$passCardAfter       = $userInfo['pass_card'] + $getPassCard;
		$balanceAfter        = $userInfo['coupon'] - $amount;
		if($balanceAfter < 0) throw new Exception('EAC券不足扣减');

		$arr = [
			'out_user_id'         => $userInfo['user_id'],
			'out_amount'          => $amount,
			'out_balance_after'   => $balanceAfter,
			'get_pass_card'       => $getPassCard,
			'get_pass_card_after' => $passCardAfter,
			'multiple_pass_card'  => $configMultiple,
			'time'                => time(),
			'type'                => 2,
		];
		$id = self::insertGetId($arr);
		$desc = 'EAC券转EAC通证';

		// 减少sea券
		VpayUsers::userCouponChange($userInfo, $balanceAfter, -$amount, self::$tableName, $id, $desc);

		// 增加通证
		VpayUsers::userPassCardChange($userInfo, $passCardAfter, $getPassCard, self::$tableName, $id, $desc);

		return [
			'outMobile' => $userInfo->user->mobile,
			'outAmount' => $arr['out_amount'],
			'getAmount' => $arr['get_pass_card'],
		];
	}

	// Sea券兑激活码
	public static function couponTrunActivationCode($u_id, $amount){
		$userInfo            = VpayUsers::where('user_id', $u_id)->field('level_id,coupon,activation_code,status')->find();
		$userInfo['user_id'] = $u_id;
		VpayUsers::userFrozenStatus($userInfo);

		$activationCodeAfter = $userInfo['activation_code'] + $amount;
		$balanceAfter        = $userInfo['coupon'] - $amount;
		if($balanceAfter < 0) throw new Exception('EAC券不足扣减');

		$arr = [
			'out_user_id'               => $userInfo['user_id'],
			'out_amount'                => $amount,
			'out_balance_after'         => $balanceAfter,
			'get_activation_code'       => $amount,
			'get_activation_code_after' => $activationCodeAfter,
			'time'                      => time(),
			'type'                      => 3,
		];
		$id = self::insertGetId($arr);
		$desc = 'EAC券兑激活码';

		// 减少sea券
		VpayUsers::userCouponChange($userInfo, $balanceAfter, -$amount, self::$tableName, $id, $desc);

		// 增加激活码
		VpayUsers::userActivationCodeChange($userInfo, $activationCodeAfter, $amount, self::$tableName, $id, $desc);

		return [
			'outMobile' => $userInfo->user->mobile,
			'outAmount' => $arr['out_amount'],
			'getAmount' => $arr['get_activation_code'],
		];
	}
}