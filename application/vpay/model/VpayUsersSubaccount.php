<?php

namespace app\vpay\model;
use app\BaseModel;
use think\facade\Cache;

/**
 * 用户子账号
 * use app\vpay\model\VpayUsersSubaccount;
 */

class VpayUsersSubaccount extends BaseModel
{
	protected $table = 'vpay_users_subaccount';
	public  $pk = 'id';
	protected static $mkey = 'vpay_users_subaccount_list';
	protected static $mkeyAll = 'vpay_users_subaccount_All';

	public function getSubUserIdAttr($value)
	{
		$str = Users::where('user_id', $value)->value('mobile');
		return $str;
	}

	 /*------------------------------------------------------ */
	//-- 清除缓存
	/*------------------------------------------------------ */
	public function cleanMemcache(){
		Cache::rm(self::$mkey);
	}

	// 添加子账号
	public static function addSubaccount($userId, $subUserId, $subPwd){
		$arr = [
			'user_id'     => $userId,
			'sub_user_id' => $subUserId,
			'sub_pwd'     => $subPwd,
			'time'        => time(),
		];
		self::insert($arr);
	}

	// 删除子账号
	public static function delSubaccount($userId, $id){
		$where = [
			'id'      => $id,
			'user_id' => $userId,
		];
		$rst = self::where($where)->delete();
		return $rst;
	}

}