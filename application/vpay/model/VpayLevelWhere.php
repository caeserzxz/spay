<?php

namespace app\vpay\model;
use app\BaseModel;
use think\facade\Cache;

/**
 * 等级条件设置、加速释放A设置
 * use app\vpay\model\VpayLevelWhere;
 */

class VpayLevelWhere extends BaseModel
{
	protected $table = 'vpay_level_where';
	public  $pk = 'id';
	protected static $mkey = 'vpay_level_where_list';

	public function cleanMemcache(){
		Cache::rm(self::$mkey);
	}

	// 获取升级条件
	public static function upgradeWhere($levelId){
		$where = [
			'level_id' => $levelId
		];
		$arr = [];
		switch ($levelId) {
			case 2:
				$arr = self::where($where)->field('cumulative_coupon')->find();
				break;
			case 3:
				$arr = self::where($where)->field('cumulative_asset_bundle')->find();
				break;
			case 4:
			case 5:
				$arr = self::where($where)->field('layer,individual')->find();
				break;
		}
		return $arr;
	}

	// 获取加速释放A（级差）的比例
	public static function gradationReleaseProportion(){
		$arr = self::where('level_id', 'in', '3,4,5')->field('level_id,gradation')->select();
		$arr2 = [];
		foreach ($arr as $v) {
			$arr2[$v['level_id']] = $v['gradation'];
		}
		return $arr2;
	}

	// 用户升级
	public static function upgradeLevel($userInfo, $levelId){
		VpayUsers::where('user_id', $userInfo['user_id'])->update(['level_id'=>$levelId]);

		// 伞下出现总统舱或头等舱，查看无限级上级是否满足升级条件
		if(in_array($levelId, [3, 4]) && !empty($userInfo['chain'])){
			self::superiorUpgrade($userInfo, $levelId);
		}
	}

	// 上级升级 往用户上级找，看看有没有满足升级头等舱、太空舱的上级
	public static function superiorUpgrade($userInfo, $levelId){

		// 升级条件
		$levelNum = $levelId + 1;
		$upgradeWhere = self::upgradeWhere($levelNum);
		// 查找父级所有和刚升级的用户的身份在一同等级的用户
		$arr = explode("-", $userInfo['chain']);
		$where = [
			['user_id', 'in', $arr],
			['status', 'eq', 1],
			['level_id', 'eq', $levelId],
		];

		$superior = VpayUsers::where($where)->field('user_id,chain')->select();
		if(!$superior) return false;

		// 查找伞下（）层内，不同线内出现（）个跟升级用户同一等级的用户，则往上升级
		foreach ($superior as $v) {

			// 获取所有下一级用户  直属用户
			$where = [
				['v.status', 'gt', 0],
				['u.pid', 'eq', $v['user_id']],
			];
			$directlyUnder = VpayUsers::alias('v')->where($where)->join('users u', 'u.user_id=v.user_id', 'inner')->column('v.user_id');

			// 查找线下所有的同一身份的用户
			$like  = '%' . $v['user_id'] . '%';
			$where = [
				['status', 'gt', 0],
				['level_id', 'eq', $levelId],
				['chain', 'like', $like],
			];
			$subordinate = VpayUsers::where($where)->field('user_id,chain')->select();

			// 上级满足升级身份时，判断每个下级是否不在同一条线上
			if(count($subordinate) >= $upgradeWhere['individual']){
				$rst = self::judgeRelationship($subordinate, $directlyUnder, $upgradeWhere);
				if($rst){
					self::upgradeLevel($v, $levelNum);
				}
			}
		}
	}

	// 判断关系 判断下级的关系链，该上级用户是否在（）层内
	public static function judgeRelationship($subordinate, $directlyUnder, $upgradeWhere){
		$arrWhere = [];
		foreach ($subordinate as $v) {

			if(count($arrWhere) >= $upgradeWhere['individual']) return true;

			// 下一级用户身份判断
			if(in_array($v['user_id'], $directlyUnder) && !in_array($v['user_id'], $arrWhere)){
				$arrWhere[] = $v['user_id'];
				continue;
			}

			// 下二级以下的用户身份判断
			$arr = explode("-", $v['chain']);
			foreach ($arr as $i => $j) {
				if($i > $upgradeWhere['layer']){
					break;
				}
				if(in_array($j, $directlyUnder) && !in_array($j, $arrWhere)){
					$arrWhere[] = $j;
				}
			}
		}

		// 满足条件，进行升级
		if(count($arrWhere) >= $upgradeWhere['individual']){
			return true;
		}
		return false;
	}

}