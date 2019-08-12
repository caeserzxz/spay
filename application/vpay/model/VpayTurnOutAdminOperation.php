<?php


namespace app\vpay\model;
use app\BaseModel;
use think\facade\Cache;

use think\facade\Log;
use think\facade\Config;
use think\Exception;

/**
 * 马甲用户 虚拟用户 后台添加的用户
 * use app\vpay\model\VpayTurnOutAdminOperation;
 */

class VpayTurnOutAdminOperation extends BaseModel
{
	protected $table = 'vpay_turn_out_admin_operation';
	private static $tableName = 'vpay_turn_out_admin_operation';
	public  $pk = 'id';
	protected static $mkey = 'vpay_turn_out_admin_operation_list';

	public function cleanMemcache(){
		Cache::rm(self::$mkey);
	}

	// 管理员添加、减少用户的资产
	public static function increaseReduceUserAssets($user, $param){
		$arr = [
			'user_id'     => $user['user_id'],
			'time'        => time(),
		];
        if(intval($param['coupon']) && $param['coupon'] > 0){
			$arr['coupon']       = self::amountConversion($param['coupon'], $param['type_coupon']);
			$arr['coupon_after'] = $user['coupon'] + $arr['coupon'];
        }
        if(intval($param['pass_card']) && $param['pass_card'] > 0){
            $arr['pass_card']       = self::amountConversion($param['pass_card'], $param['type_pass_card']);
			$arr['pass_card_after'] = $user['pass_card'] + $arr['pass_card'];
        }
        if(intval($param['asset_bundle']) && $param['asset_bundle'] > 0){
            $arr['asset_bundle']       = self::amountConversion($param['asset_bundle'], $param['type_asset_bundle']);
			$arr['asset_bundle_after'] = $user['asset_bundle'] + $arr['asset_bundle'];
        }
        if(intval($param['activation_code']) && $param['activation_code'] > 0){
            $arr['activation_code']       = self::amountConversion($param['activation_code'], $param['type_activation_code']);
			$arr['activation_code_after'] = $user['activation_code'] + $arr['activation_code'];
        }
        if(count($arr) > 2){
			$id = self::insertGetId($arr);
			$desc = '管理员调整';
			if(!empty($arr['coupon'])){
				VpayUsers::userCouponChange($user, $arr['coupon_after'], $arr['coupon'], self::$tableName, $id, $desc);
			}
			if(!empty($arr['pass_card'])){
				VpayUsers::userPassCardChange($user, $arr['pass_card_after'], $arr['pass_card'], self::$tableName, $id, $desc);
			}
			if(!empty($arr['asset_bundle'])){
				VpayUsers::userAssetBundleChange($user, $arr['asset_bundle_after'], $arr['asset_bundle'], self::$tableName, $id, $desc);
			}
			if(!empty($arr['activation_code'])){
				VpayUsers::userActivationCodeChange($user, $arr['activation_code_after'], $arr['activation_code'], self::$tableName, $id, $desc);
			}
        }
	}

	// 金额转换
	public static function amountConversion($amount, $type){
		if($type == 'reduce') $amount = -$amount;
		return $amount;
	}


}