<?php

namespace app\vpay\model;
use app\BaseModel;
use think\facade\Cache;

use think\facade\Log;
use think\facade\Config;
use think\Exception;
use app\member\model\UsersModel;

/**
 * 激活码转出
 * use app\vpay\model\VpayTurnOutActivationCode;
 */

class VpayTurnOutActivationCode extends BaseModel
{
	protected $table = 'vpay_turn_out_activation_code';
	private static $tableName = 'vpay_turn_out_activation_code';
	public  $pk = 'id';
	protected static $mkey = 'vpay_turn_out_activation_code_list';

	public function cleanMemcache(){
		Cache::rm(self::$mkey);
	}

	// 转赠激活码
	public static function activationTrunFriends($u_id, $friendUId, $amount){

		// 用户A减少激活码
		$userInfo            = VpayUsers::where('user_id', $u_id)->field('activation_code,chain,status')->find();
		$userInfo['user_id'] = $u_id;
		$activationCodeAfter = $userInfo['activation_code'] - $amount;
		VpayUsers::userFrozenStatus($userInfo);
		if($activationCodeAfter < 0) throw new Exception('激活码不足扣减');

    	// 用户B增加激活码
		$friendUserInfo            = VpayUsers::where('user_id', $friendUId)->field('activation_code,chain,status')->find();
		$friendUserInfo['user_id'] = $friendUId;
		$friendActivationCodeAfter = $friendUserInfo['activation_code'] + $amount;
		VpayUsers::userFrozenStatus($friendUserInfo);

		// 判断用户是否是上下级关系
		$rst = VpayUsers::supervisorSubordinateGuanxi($userInfo, $friendUserInfo);
		if(!$rst) throw new Exception('转账用户不在团队内');

		$arr = [
			'out_user_id'                   => $userInfo['user_id'],
			'out_amount'                    => $amount,
			'out_balance_after'             => $activationCodeAfter,
			'receive_user_id'               => $friendUserInfo['user_id'],
			'receive_activation_code'       => $amount,
			'receive_activation_code_after' => $friendActivationCodeAfter,
			'type'                          => 1,
			'time'                          => time(),
		];
		$id = self::insertGetId($arr);

		// 用户A减少激活码
		VpayUsers::userActivationCodeChange($userInfo, $activationCodeAfter, -$amount, self::$tableName, $id, '赠送团队成员');

		// 用户B增加激活码
		VpayUsers::userActivationCodeChange($friendUserInfo, $friendActivationCodeAfter, $amount, self::$tableName, $id, '团队成员赠送');

		return [
			'outMobile'     => $userInfo->user->mobile,
			'outAmount'     => $arr['out_amount'],
			'receiveMobile' => $friendUserInfo->user->mobile,
			'receiveAmount' => $arr['receive_activation_code'],
		];
	}

	// 父级返利 帮用户激活的人的sea券、资产包情况 一二级返利
	public static function parentRebateAmount($userInfo, $amount, $configMultiple){

		$balanceAfter           = $userInfo['activation_code'] - $amount;

		$configFirstAssetBundle = Config::get('first_floor_reward_asset_bundle');
		$getAssetsBundle        = VpayUsers::calculationAssetsBundle($amount, $configFirstAssetBundle, $configMultiple);
		$assetsBundleAfter      = $userInfo['asset_bundle'] + $getAssetsBundle;

		$configCoupon           = Config::get('first_floor_reward_coupon');
		$getCoupon              = VpayUsers::profitProportionAmount($amount, $configCoupon);
		$couponAfter            = $userInfo['coupon'] + $getCoupon;

		$arr =  [
			'out_user_id'                          => $userInfo['user_id'],
			'out_amount'                           => $amount,
			'out_balance_after'                    => $balanceAfter,

			'first_level_coupon'                   => $getCoupon,
			'first_level_coupon_proportion'        => $configCoupon,
			'first_level_coupon_after'             => $couponAfter,

			'first_level_assets_bundle'            => $getAssetsBundle,
			'first_level_assets_bundle_proportion' => $configFirstAssetBundle,
			'first_level_assets_bundle_after'      => $assetsBundleAfter,
		];

		// 是否有上级
		$twoUserId = $userInfo->user->pid;
		if($twoUserId > 0){
			$pidUserInfo            = VpayUsers::where('user_id', $twoUserId)->field('level_id,asset_bundle,cumulative_asset_bundle,chain,status')->find();
			$pidUserInfo['user_id'] = $twoUserId;
			$configTwoAssetBundle   = Config::get('two_floor_reward_asset_bundle');
			$getAssetsBundle        = VpayUsers::calculationAssetsBundle($amount, $configTwoAssetBundle, $configMultiple);
			$assetsBundleAfter      = $pidUserInfo['asset_bundle'] + $getAssetsBundle;
			$arr2 = [
				'two_level_user_id'                  => $pidUserInfo['user_id'],
				'two_level_assets_bundle'            => $getAssetsBundle,
				'two_level_assets_bundle_proportion' => $configTwoAssetBundle,
				'two_level_assets_bundle_after'      => $assetsBundleAfter,
				'pidUserInfo'                        => $pidUserInfo,
			];
			$arr = array_merge($arr, $arr2);
		}
		return $arr;
	}


	// 被上级激活的用户的sea券、资产包情况
	public static function receiveActivationUserAmount($userInfo, $amount, $configMultiple){

		$configAssetsBundle = config::get('activation_reward_asset_bundle');
		$getAssetsBundle    = VpayUsers::calculationAssetsBundle($amount, $configAssetsBundle, $configMultiple);

		$configCoupon       = config::get('activation_reward_coupon');
		$getCoupon          = VpayUsers::profitProportionAmount($amount, $configCoupon);

		return [
			'receive_user_id'                      => $userInfo['user_id'],
			'receive_coupon'                       => $getCoupon,
			'receive_coupon_proportion'            => $configCoupon,
			'receive_assets_bundle'                => $getAssetsBundle,
			'receive_assets_bundle_proportion'     => $configAssetsBundle,
		];
	}


	// 激活用户
	public static function activationUsers($u_id, $friendUId, $amount){

		if($u_id == $friendUId) throw new Exception('不能给自己激活' . $max);

		// 激活金额是否超过最大值、最小值
		$min = Config::get('activation_code_min');
		$max = Config::get('activation_code_max');
		if($amount < $min) throw new Exception('激活码不能少于' . $min);
		if($amount > $max) throw new Exception('激活码不能大于' . $max);

		// 用户A
		$field               = 'level_id,activation_code,coupon,cumulative_coupon,asset_bundle,cumulative_asset_bundle,chain,status';
		$userInfo            = VpayUsers::where('user_id', $u_id)->field($field)->find();
		$userInfo['user_id'] = $u_id;
		$configMultiple      = Config::get('multiple_asset_bundle'); // 资产包的倍数
		VpayUsers::userFrozenStatus($userInfo);
		if($userInfo['activation_code'] - $amount < 0) throw new Exception('激活码不足扣减');

    	// 用户B
    	$field                     = 'level_id,coupon,cumulative_coupon,asset_bundle,cumulative_asset_bundle,chain,status';
		$friendUserInfo            = VpayUsers::where('user_id', $friendUId)->field($field)->find();
		$friendUserInfo['user_id'] = $friendUId;
		VpayUsers::userFrozenStatus($friendUserInfo, 'activation');

		// 判断用户是否是上下级关系
		$rst = VpayUsers::supervisorSubordinateGuanxi($userInfo, $friendUserInfo, true);
		if(!$rst) throw new Exception('激活用户不是您的下级');

		$parentRebate   = self::parentRebateAmount($userInfo, $amount, $configMultiple); // 用户A减少激活码和增加sea券、资产包
		$activationUser = self::receiveActivationUserAmount($friendUserInfo, $amount, $configMultiple); // 用户B被上级激活的用户

    	// 第二层父级
    	$pidUserInfo = [];
    	if(!empty($parentRebate['pidUserInfo'])){
    		$pidUserInfo = $parentRebate['pidUserInfo'];
    		unset($parentRebate['pidUserInfo']);
    	}

		$arr = [
			'multiple_assets_bundle' => $configMultiple,
			'type'                   => 2,
			'time'                   => time(),
		];
		$arr = array_merge($arr, $activationUser, $parentRebate);
		$id = self::insertGetId($arr);

		// 被激活的用户增加sea券、资产包
		$desc = '激活账号赠送';
		$receiveCoupon = $activationUser['receive_coupon'];
		$receiveAssetBundle = $activationUser['receive_assets_bundle'];
		$updateArr = [
			'activation_time' => time(),
			'status' => 1,
		];
		VpayUsers::where('user_id', $friendUserInfo['user_id'])->update($updateArr);
		VpayUsers::userCouponChange($friendUserInfo, $receiveCoupon, $receiveCoupon, self::$tableName, $id, $desc);
		VpayUsers::userAssetBundleChange($friendUserInfo, $receiveAssetBundle, $receiveAssetBundle, self::$tableName, $id, $desc);

		// 第一层减少激活码、增加sea券和资产包
		VpayUsers::userActivationCodeChange($userInfo, $parentRebate['out_balance_after'], -$amount, self::$tableName, $id, '激活账号');
		VpayUsers::userCouponChange($userInfo, $parentRebate['first_level_coupon_after'], $parentRebate['first_level_coupon'], self::$tableName, $id, $desc);
		VpayUsers::userAssetBundleChange($userInfo, $parentRebate['first_level_assets_bundle_after'], $parentRebate['first_level_assets_bundle'], self::$tableName, $id, $desc);

		// 第二层激活奖励  增加资产包
		if(count($pidUserInfo) > 0){
			VpayUsers::userAssetBundleChange($pidUserInfo, $parentRebate['two_level_assets_bundle_after'], $parentRebate['two_level_assets_bundle'], self::$tableName, $id, $desc);
		}
	}

}