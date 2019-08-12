<?php

namespace app\vpay\model;
use app\BaseModel;
use think\facade\Cache;

/**
 * 释放规则
 * use app\vpay\model\VpayReleaseRule;
 */

class VpayReleaseRule extends BaseModel
{
	protected $table = 'vpay_release_rule';
	public  $pk = 'id';
	protected static $mkey = 'vpay_release_rule_list';
	protected static $mkeyAll = 'vpay_release_rule_All';

	 /*------------------------------------------------------ */
	//-- 清除缓存
	/*------------------------------------------------------ */
	public function cleanMemcache(){
		Cache::rm(self::$mkey);
	}
	/*------------------------------------------------------ */
	//-- 获取列表
	/*------------------------------------------------------ */
	public  function getRows(){
		$data = Cache::get(self::$mkey);
		if (empty($data) == false){
			return $data;
		}
		$rows = $this->select()->toArray();
		foreach ($rows as $row){
			$data[$row['count']] = $row;
		}
		Cache::set(self::$mkey,$data,600);
		return $data;
	}

	// 获取全部释放规则
	public static function getReleaseRuleAll(){
		$data = Cache::get(self::$mkeyAll);
		if (empty($data) == false){
			return $data;
		}
		$data = self::field('id,min,max')->select();
		Cache::set(self::$mkeyAll, $data,600);
		return $data;
	}


	// 获取加速释放B（关系链）的比例
	public static function chainReleaseProportion(){
		$lst = self::getReleaseRuleAll(); // 获取全部释放规则
		$lst = $lst->toArray();
		$arr = VpayReleaseProportion::getReleseProprotionAll();
		foreach ($lst as $k => $v) {
			foreach ($arr as $i => $j) {
				if($v['id'] == $j['rule_id']){
					$lst[$k]['proportion'][$j['generation']] = $j;
				}
			}
		}
		return $lst;
	}

	// 匹配释放规则
	public static function matchingReleaseProportion($num, $rule){
		$key = 0;
		foreach ($rule as $k => $v) {
			if($num >= $v['min'] && $num <= $v['max']){
				$key = $k;
			}
		}
		return $key;
	}


}