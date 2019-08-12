<?php

namespace app\vpay\model;
use app\BaseModel;
use think\facade\Cache;

/**
 * 释放比例 加速释放B（关系链）
 * use app\vpay\model\VpayReleaseProportion;
 */

class VpayReleaseProportion extends BaseModel
{
	protected $table = 'vpay_release_proportion';
	public  $pk = 'id';
	protected static $mkey = 'vpay_release_proportion_list';
	protected static $mkeyAll = 'vpay_release_proportion_All';

	public function cleanMemcache(){
		Cache::rm(self::$mkey);
	}

	// 获取所有的释放比例
	public static function getReleseProprotionAll(){
		$data = Cache::get(self::$mkeyAll);
		if (empty($data) == false){
			return $data;
		}
		$data = self::field('id,rule_id,generation,proportion')->select();
		Cache::set(self::$mkeyAll, $data,600);
		return $data;
	}

}