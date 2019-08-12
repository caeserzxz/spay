<?php

namespace app\vpay\model;
use app\BaseModel;

use think\facade\Log;
use think\facade\Config;
use think\facade\Cache;
use think\Exception;

use app\member\model\UsersModel;

/**
 * vpay 用户
 * use app\vpay\model\VpayUsers;
 */

class VpayUsers extends BaseModel
{
	protected $table = 'vpay_users';
	public  $pk = 'id';

	public function user()
    {
        return $this->hasOne('app\member\model\UsersModel', 'user_id', 'user_id');
    }

    // 获取我的无限级团队  user_id必须位数相同，不要出现1 11 111 1111 11111这样的有可能重复数字
    public static function getMyInfiniteOrderTeam($userId, $param = []){
        $where[] = ['chain', 'like', '%' . $userId . '%'];
        if($param['userId']) $where[] = ['user_id', 'like', '%' . $param['userId'] . '%'];
        $lst = self::where($where)->order('user_id desc')->field('user_id,chain')->paginate(6, false);
        foreach ($lst as $k => $v) {
            $group = self::teamTotalNumber($v['user_id']);
            $user  = $v->user;
            $lst[$k]['user_name'] = $user->user_name;
            $lst[$k]['mobile']    = $user->mobile;
            $lst[$k]['reg_time']  = date('Y-m-d H:i:s', $user->reg_time);
            $lst[$k]['count']     = $group['count'];
            $lst[$k]['total']     = $group['total'] ? $group['total'] : 0;
            unset($v->user);
        }
        $lst = $lst->toArray();
        $arr = [
            'lastPage'    => $lst['last_page'],
            'currentPage' => $lst['current_page'],
            'lst'         => $lst['data'],
        ];
        return $arr;
    }

    // 团队总人数
    public static function teamTotalNumber($userId){
        $where[] = ['chain', 'like', '%' . $userId . '%'];
        $group = self::where($where)->field('count(id) as count, sum(coupon+pass_card+asset_bundle+activation_code) as total')->find();
        return $group;
    }



    // 获取用户资产
    public static function getUserAssets($u_id){
    	$info = self::where('user_id', $u_id)->field('coupon,pass_card,asset_bundle,activation_code')->find();
    	$info['current_price'] = Config::get('current_price');
    	return $info;
    }

    // 验证用户支付密码是否正确
    public static function validatePayPwd($userId, $pwd, $type = 0){
        $payPwd = UsersModel::where('user_id', $userId)->value('pay_password');
        $pwd = f_hash($pwd);
        if($payPwd !== $pwd && $type) return false;
        if($payPwd !== $pwd) throw new Exception('支付密码不正确');
        return true;
    }

    // 判断用户的状态  转账需要确认好友是否存在或被冻结
//    public static function getUserStatus($mobile, $type = 0){
//        $userId = UsersModel::where('mobile', $mobile)->value('user_id');
//        if(!$userId && $type) return false;
//        if(!$userId) throw new Exception('用户不存在');
//        return $userId;
//    }
    public static function getUserStatus($userId, $type = 0){
        $userId = UsersModel::where('user_id', $userId)->value('user_id');
        if(!$userId && $type) return false;
        if(!$userId) throw new Exception('用户不存在');
        return $userId;
    }

    // 用户是否被冻结 $vali user验证用户是否处于正常状态 activation验证用户是否处于激活
    public static function userFrozenStatus($userInfo, $vali = 'user', $type = 0){
        $str = '';
        $status = false;
        switch ($userInfo['status']) {
            case 0:
                $str = '未激活';
                if($vali == 'activation') $status = true;
                break;
            case 1:
                $str = '已激活';
                if($vali == 'user') $status = true;
                break;
            case 2:
                $str = '已冻结';
                break;
            case 3:
                $str = '进入黑名单';
                break;
        }
        if(!$status && $type) return ['status'=>$status, 'msg'=>$str];
        if(!$status) throw new Exception("ID:{$userInfo['user_id']}用户状态：" . $str);
        return ['status'=>$status];
    }


	// 添加用户
	public function addUserInfo($userId, $pid = 0)
	{
		$arr = [
            'user_id'                 => $userId,
            'level_id'                => 1,
            'coupon'                  => 0,
            'pass_card'               => 0,
            'asset_bundle'            => 0,
            'activation_code'         => 0,
            'cumulative_coupon'       => 0,
            'cumulative_asset_bundle' => 0,
            'status'                  => 0,
            'rozen_num'               => 0,
            'task_num'                => 0,
            'chain'                   => '',
		];
		// 关系链
        if(!empty($pid)){
			$arr['chain'] = self::findFatherChain($pid);
        }
		self::insert($arr);
	}

	/**
     * 查找父级关系链
     * @param  [type] $pid 父级id
     */
    public static function findFatherChain($pid){
        $curChain = $pid;
        $preChain = self::where('user_id', $pid)->value('chain');
        if(!empty($preChain)){
            $curChain = $pid . '-' . $preChain;
        }
        return $curChain;
    }

    // 判断用户是否是上下级关系
    public static function supervisorSubordinateGuanxi($userInfo, $friendUserInfo, $activation = false){
    	$statu = false;

        // 转账用户是否是接收用户的上级
        $arr1 = explode('-', $friendUserInfo['chain']);
        if(in_array($userInfo['user_id'], $arr1)){
            $statu = true;
        }

        // 转账用户是否是接收用户的下级
        if(!$statu && !$activation){
            $arr2 = explode('-', $userInfo['chain']);
            if(in_array($friendUserInfo['user_id'], $arr2)){
                $statu = true;
            }
        }
        return $statu;
    }

    // 获取金额后两位
    public static function getAmountLastTwo($amount){
        $amount = intval(floor(floatval($amount) * 100)) / 100;
        return $amount;
    }


    /**
     * 计算金额的百分比
     */
    public static function profitProportionAmount($amount, $proportion){
        $proportion /= 100;
        $amount     *= $proportion;
        $amount     = intval(floor(floatval($amount) * 100)) / 100;
        return $amount;
    }

    // 计算资产包
    public static function calculationAssetsBundle($amount, $proportion, $multiple){
    	$calculationAmount = self::profitProportionAmount($amount, $proportion);
    	$calculationAmount *= $multiple;
        $calculationAmount = self::getAmountLastTwo($calculationAmount);
    	return $calculationAmount;
    }

    // sea券增加、减少操作
    public static function userCouponChange($userInfo, $coupon, $amount, $surfaceName, $surfaceId, $desc = ''){
    	self::where('user_id', $userInfo['user_id'])->update(['coupon'=>$coupon]);
    	VpayRecordCoupon::recardCoupon($userInfo['user_id'], $amount, $surfaceName, $surfaceId, $desc);
    	if($amount > 0){
    		self::cumulativeCouponChange($userInfo, $amount);
    	}
    }

    // 累计sea券
    public static function cumulativeCouponChange($userInfo, $amount){
		self::where('user_id', $userInfo['user_id'])->setInc('cumulative_coupon', $amount);
		// 粉丝升会员
		if($userInfo['level_id'] == 1){
            $upgradeWhere     = VpayLevelWhere::upgradeWhere(2);
            $cumulativeCoupon = $userInfo['cumulative_coupon'] + $amount;
			if($cumulativeCoupon >= $upgradeWhere['cumulative_coupon']){
				VpayLevelWhere::upgradeLevel($userInfo, 2);
			}
		}
    }

    // 资产包增加、减少操作
    public static function userAssetBundleChange($userInfo, $assetBundle, $amount, $surfaceName, $surfaceId, $desc = ''){
    	self::where('user_id', $userInfo['user_id'])->update(['asset_bundle'=>$assetBundle]);
    	VpayRecordAssetsBundle::recardAssetsBundle($userInfo['user_id'], $amount, $surfaceName, $surfaceId, $desc);
    	if($amount > 0){
    		self::cumulativeAssetBundleChange($userInfo, $amount);
    	}
    }

    // 累计资产包
    public static function cumulativeAssetBundleChange($userInfo, $amount){
		self::where('user_id', $userInfo['user_id'])->setInc('cumulative_asset_bundle', $amount);
		// 会员升总统舱
		if($userInfo['level_id'] == 2){
            $upgradeWhere     = VpayLevelWhere::upgradeWhere(3);
            $cumulativeCoupon = $userInfo['cumulative_asset_bundle'] + $amount;
			if($cumulativeCoupon >= $upgradeWhere['cumulative_asset_bundle']){
				VpayLevelWhere::upgradeLevel($userInfo, 3);
			}
		}
    }

    // sea通证增加、减少操作
    public static function userPassCardChange($userInfo, $passCard, $amount, $surfaceName, $surfaceId, $desc = ''){
    	self::where('user_id', $userInfo['user_id'])->update(['pass_card'=>$passCard]);
    	VpayRecordPassCard::recardPassCard($userInfo['user_id'], $amount, $surfaceName, $surfaceId, $desc);
    }

    // 激活码增加、减少操作
    public static function userActivationCodeChange($userInfo, $activationCode, $amount, $surfaceName, $surfaceId, $desc = ''){
    	self::where('user_id', $userInfo['user_id'])->update(['activation_code'=>$activationCode]);
    	VpayRecordActivationCode::recardActivationCode($userInfo['user_id'], $amount, $surfaceName, $surfaceId, $desc);
    }

}
