<?php

namespace app\vpay\model;
use app\BaseModel;
use think\facade\Cache;

/**
 * sea通证变动记录
 * use app\vpay\model\VpayRecordAssetsBundle;
 */

class VpayRecordAssetsBundle extends BaseModel
{
	protected $table = 'vpay_record_assets_bundle';
	public  $pk = 'id';
	protected static $mkey = 'vpay_record_assets_bundle_list';

	public function getTimeAttr($value)
	{
		$str = date('Y-m-d H:i:s', $value);
		return $str;
	}

	public function cleanMemcache(){
		Cache::rm(self::$mkey);
	}

	// 记录信息
	public static function recardAssetsBundle($u_id, $amount, $surfaceName, $surfaceId, $desc = ''){
		$arr = [
			'user_id'      => $u_id,
			'amount'       => $amount,
			'surface_name' => $surfaceName,
			'surface_id'   => $surfaceId,
			'desc'         => $desc,
			'time'         => time(),
		];
		self::insert($arr);
	}

	// 获取资产包记录 income收入  expenditure支出
	public static function getAssetsBundleLst($userId, $param){
		$where[] = ['user_id', 'eq', $userId];
		switch ($param['type']) {
			case 'income':
				$where[] = ['amount', 'gt', 0];
				break;
			case 'expenditure':
				$where[] = ['amount', 'lt', 0];
				break;
		}
		$lst = self::where($where)->order('id desc')->field('id,amount,desc,time,surface_name,surface_id')->paginate(10, false);
		$lst = $lst->toArray();
        $arr = [
            'lastPage'    => $lst['last_page'],
            'currentPage' => $lst['current_page'],
            'lst'         => $lst['data'],
        ];
        return $arr;
	}

}