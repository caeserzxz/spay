<?php
namespace app\vpay\model;
use app\BaseModel;
use think\facade\Cache;

/**
 * 等级条件设置、加速释放A设置
 * use app\vpay\model\Users;
 */

class Users extends BaseModel
{
	protected $table = 'users';
	public  $pk = 'id';
	protected static $mkey = 'users_list';
	public static $userHeader = '/static/vpay/assets/images/userHeader.png';

	public function cleanMemcache(){
		Cache::rm(self::$mkey);
	}

	// 获取用户姓名、电话
	public static function getUserMobileAndName($u_id){
		$info = self::where('user_id', $u_id)->field('pid,mobile,user_name,headimgurl,member_name')->find();
		$info['user_id'] = $u_id;
		if(empty($info['headimgurl'])){
			$info['headimgurl'] = self::$userHeader;
		}
		return $info;
	}

}