<?php

namespace app\vpay\model;
use app\BaseModel;
use think\facade\Cache;

use think\facade\Log;
use think\facade\Config;
use think\Exception;

/**
 * 马甲用户 虚拟用户 后台添加的用户
 * use app\vpay\model\VpayUsersVest;
 */

class VpayUsersVest extends BaseModel
{
	protected $table = 'vpay_users_vest';
	public  $pk = 'id';
	protected static $mkey = 'vpay_users_vest_list';

	public function cleanMemcache(){
		Cache::rm(self::$mkey);
	}

}