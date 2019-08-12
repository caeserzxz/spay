<?php

namespace app\vpay\model;
use app\BaseModel;
use think\facade\Cache;

use think\facade\Log;
use think\facade\Config;
use think\Exception;

use app\member\model\UsersModel;

/**
 * 资产包转出
 * use app\vpay\model\VpayTurnOutAssetsBundle;
 */

class VpayTurnOutAssetsBundle extends BaseModel
{
	protected $table = 'vpay_turn_out_assets_bundle';
	private static $tableName = 'vpay_turn_out_assets_bundle';
	public  $pk = 'id';
	protected static $mkey = 'vpay_turn_out_assets_bundle_list';

	public function cleanMemcache(){
		Cache::rm(self::$mkey);
	}

	// 获取释放资产包的所有用户
	public static function getReleaseUser(){
		$where = [
			['status', 'eq', 1],
			['asset_bundle', 'gt', 4], // 资产包大于4，避免出现返0元的情况
		];
		$lst = VpayUsers::where($where)->field('user_id,level_id,asset_bundle,chain')->select();
		return $lst;
	}

	// 获取所有规则配置
	public static function getAllRuleConfig(){
		$config['coupon']     = Config::get('vpay_every_relesae_coupon');
		$config['passCard']   = Config::get('vpay_every_relesae_pass_card');

		$config['levelWhere'] = VpayLevelWhere::gradationReleaseProportion(); // 获取级差释放比例
		$config['ruleWhere']  = VpayReleaseRule::chainReleaseProportion(); // 获取人数释放比例
		return $config;
	}


	// 每天计算释放的资产包数量  定时任务执行该方法
	public static function everyDayCalculationReleaseAmount(){

		$configAssetBundle = Config::get('vpay_every_relesae_ratio_asset_bundle'); // 资产包释放比例
		$config            = self::getAllRuleConfig();
		$lst               = self::getReleaseUser();
		if(count($lst) == 0) return ;
		foreach ($lst as $k => $v) {
			$rst = self::insertCalculationReleaseInfo($v, $v['asset_bundle'], $configAssetBundle, $config);
			if(!empty($v['chain'])){
				$parentArr = self::findInfiniteParents($v, $lst);
				// 父级
				self::parentRebateRelese($parentArr['parent'], $rst, $config);

				// 级差
				if(count($parentArr['identityArr']) > 0){
					self::differentialRelease($parentArr['identityArr'], $rst, $config);
				}
	        }
		}
	}

	// 找父级和总统舱身份以上的无限级父级
	public static function findInfiniteParents($userInfo, $userLst){
		$identityArr = [];
		$parent      = [];
		$arr         = explode("-", $userInfo['chain']);
		foreach ($arr as $i => $j) {
			foreach ($userLst as $k => $v) {

				// 查看关系链的父级直荐人数，确定返第几代的释放，如果无限级上级所返的代数刚好符合该用户，需要返给这个上级
				if($v['user_id'] == $j && $v['level_id'] > 1){
					$v['generation'] = $i + 1; // 当前上级是该用户的第几级
					$parent[] = $v;
				}

				// 级差释放 加速释放A
				if($v['user_id'] == $j && in_array($v['level_id'], [3, 4, 5]) && empty($identityArr[$v['level_id']])){
					$identityArr[$v['level_id']] = $v;
				}
			}
			if(!empty($identityArr[5])){
				break;
			}
		}
		return [
			'parent'      => $parent,
			'identityArr' => $identityArr,
		];
	}

	// 父级分佣释放
	public static function parentRebateRelese($parentUser, $parentInfo, $config){
		$rule = $config['ruleWhere'];
		foreach ($parentUser as $v) {

			// 上级的直推会员人数
			$count = UsersModel::alias('u')
									->join('vpay_users v', 'u.user_id=v.user_id', 'inner')
									->where('v.level_id', 'gt', 0)
									->where('u.pid', $v['user_id'])
									->count();

			// 匹配与直推人数相关的释放规则
			$ruleKey = VpayReleaseRule::matchingReleaseProportion($count, $rule);
			if($ruleKey == 0) continue;

			// 找到释放比例
			$matchingRule = $rule[$ruleKey]['proportion'];

			if(empty($matchingRule)) continue;
			if(empty($matchingRule[$v['generation']])) continue; // 没有该代的释放奖励

			$releasRule = $matchingRule[$v['generation']];

			self::insertCalculationReleaseInfo($v, $parentInfo['amount'], $releasRule['proportion'], $config, $parentInfo['id'], $releasRule['id']);
		}
	}


	// 级差释放A
	public static function differentialRelease($levelUser, $parentInfo, $config){
		$levelWhere = $config['levelWhere'];
		$level5     = $levelWhere[5];
		$level4     = $levelWhere[4];
		$level3     = $levelWhere[3];
		$num        = $levelWhere[3];
		foreach ($levelUser as $k => $v) {
			$proportion = $levelWhere[$k];
			if($k == 3){
				if($level3 == 0) continue;
				$level5 -= $proportion;
				$level4 -= $proportion;
			}
			if($k == 4){
				if($level4 == 0) continue;
				$num    = $level4;
				$level3 = 0;
				$level5 -= $proportion; // 级差还没被拿
				if($level4 != $levelWhere[4]) $level5 = $levelWhere[5] - $proportion; // 级差已被拿
			}
			if($k == 5){
				if($level5 == 0) continue;
				$num    = $level5;
				$level4 = 0;
				$level3 = 0;
			}
			self::insertCalculationReleaseInfo($v, $parentInfo['amount'], $num, $config, $parentInfo['id']);
		}
	}


	// 添加每天计算释放的资产包记录
	public static function insertCalculationReleaseInfo($userInfo, $assetBundle, $proportion, $config, $pid = 0, $releaseId = 0){

		// 资产包释放数量
		$amount   = VpayUsers::profitProportionAmount($assetBundle, $proportion);
		$coupon   = VpayUsers::profitProportionAmount($amount, $config['coupon']);
		$passCard = VpayUsers::profitProportionAmount($amount, $config['passCard']);

		// 定时任务存在很大的延时，需要更新用户的资产包
		$userBalance = VpayUsers::where('user_id', $userInfo['user_id'])->value('asset_bundle');
		$assetsBundleAfter   = $userBalance - $amount;

		$arr = [
			'out_user_id'              => $userInfo['user_id'],
			'out_amount'               => $amount,
			'out_proportion'           => $proportion,
			'out_balance_after'        => $assetsBundleAfter,
			'get_coupon'               => $coupon,
			'get_coupon_proportion'    => $config['coupon'],
			'get_pass_card'            => $passCard,
			'get_pass_card_proportion' => $config['passCard'],
			'time'                     => time(),
			'status'                   => 0,
			'type'                     => 1,
		];
		if($pid > 0){
			$arr['pid']  = $pid;
			$arr['type'] = 2;
		}
		if($releaseId > 0){
			$arr['release_proportion_id'] = $releaseId;
			$arr['type']                  = 3;
		}
		$id = self::insertGetId($arr);

		// 减少用户的资产包
		VpayUsers::userAssetBundleChange($userInfo, $assetsBundleAfter, -$amount, self::$tableName, $id, '资产包释放');

		return [
			'id'     => $id,
			'amount' => $amount,
		];
	}


	// 获取前一天计算出来的资产包
	public static function getReleaseAssetsBundleLst(){
		// $time = self::getLastTime();
		$where = [
			['status', 'eq', 0],
			// ['time', 'between', $time['star'] . ',' . $time['end']],
		];
		$lst = self::where($where)->field('id,out_user_id,get_coupon,get_pass_card,type')->select();
		return $lst;
	}

	// 每天到账 前一天释放出来的资产包   定时任务执行该方法
	public static function arriveEveryDay(){
		$lst = self::getReleaseAssetsBundleLst();
		if(count($lst) == 0) return ;
		foreach ($lst as $k => $v) {
			self::assetsBundleTrunPassCardAndCoupon($v);
		}
	}


	// 资产包转sea通证和sea券
	public static function assetsBundleTrunPassCardAndCoupon($info){
		$userInfo = VpayUsers::where('user_id', $info['out_user_id'])->field('level_id,coupon,pass_card,cumulative_coupon,status')->find();
		if($userInfo['status'] != 1){
			$rst = VpayUsers::userFrozenStatus($userInfo, 'user', 1);
			$arr = [
				'status' => 2,
				'settlement_time' => time(),
				'desc' => '用户：' . $rst['msg'],
			];
			self::where('id', $info['id'])->update($arr);
			return false;
		}

		$userInfo['user_id'] = $info['out_user_id'];
		$couponAfter         = $userInfo['coupon'] + $info['get_coupon'];
		$passCardAfter       = $userInfo['pass_card'] + $info['get_pass_card'];

		$arr = [
			'get_coupon_after'    => $couponAfter,
			'get_pass_card_after' => $passCardAfter,
			'settlement_time'     => time(),
			'status'              => 1,
		];
		self::where('id', $info['id'])->update($arr);

		$desc = [
			1 => '静态释放',
			2 => '级差加速释放',
			3 => '关系链加速释放',
		];

		// 增加用户的sea券、sea通证
		VpayUsers::userCouponChange($userInfo, $couponAfter, $info['get_coupon'], self::$tableName, $info['id'], $desc[$info['type']]);
		VpayUsers::userPassCardChange($userInfo, $passCardAfter, $info['get_pass_card'], self::$tableName, $info['id'], $desc[$info['type']]);
	}


	/*
	 * 获取前一天的开始和结束时间
	 */
	public static function getLastTime(){
		$star         = date("Y-m-d", strtotime("-1 day")) . " 0:0:0";
		$end          = date("Y-m-d", strtotime("-1 day")) . " 23:59:59";
		$data["star"] = strtotime($star);
		$data["end"]  = strtotime($end);
	    return $data;
	}
}