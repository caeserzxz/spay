<?php

namespace app\vpay\controller;

use think\Controller;
use think\facade\Session;
use think\facade\Log;
use think\Db;
use think\facade\Config;
use think\Exception;
use app\ClientbaseController;

use app\vpay\model\VpayUsers;
use app\vpay\model\Users;
use app\vpay\model\VpayTurnOutActivationCode;
use app\vpay\model\VpayTurnOutCoupon;
use app\vpay\model\VpayTurnOutPassCard;

use app\mainadmin\model\ArticleModel;

use app\vpay\model\VpayLevelWhere;

class Index extends ClientbaseController
{
	private $userAssets;
	private $userId;

	public function __construct()
	{

		parent::__construct();
		$this->userId = Session::get('userId');
		if(!request()->isAjax()){
			$this->userAssets = VpayUsers::getUserAssets($this->userId);
		}
	}

	/**
	 * 首页
	 * http://vpay.project.com/vpay/index/index
	 */
	public function index()
	{
		$userId = $this->userId;
		$info   = Users::getUserMobileAndName($userId);
		$where = [
			'isdel' => 1,
			'cid'   => 1
		];
		$article = ArticleModel::where($where)->field('id,title')->order('add_time desc, id desc')->select();

		$view['info']    = $info;
		$view['assets']  = $this->userAssets;
		$view['article'] = $article;
		return view('index', $view);
	}

	/**
	 * 兑换复投
	 * http://vpay.project.com/vpay/index/convertibilityReInvestment
	 */
	public function convertibilityReInvestment()
	{
		if(request()->isPost()){
			Db::startTrans();
			try {
				$userId = $this->userId;
				$param  = request()->param();
				$amount = $param['amount'] / 100;
                if(!preg_match("/^\d+$/", $amount)) throw new Exception('100的倍数起');

				VpayUsers::validatePayPwd($userId, $param['payPwd']);

				$arr = [];
				switch ($param['type']) {
					// Sea券兑Sea通证
					case 'passCard':
						$arr = VpayTurnOutCoupon::couponTrunPassCard($userId, $param['amount']);
						break;
					// Sea通证复投资产
					case 'assets':
						$arr = VpayTurnOutPassCard::passCardTrunAssetsBundle($userId, $param['amount']);
						break;
					// Sea券兑激活码
					case 'activation':
						$arr = VpayTurnOutCoupon::couponTrunActivationCode($userId, $param['amount']);
						break;
					default:
						throw new Exception('错误操作');
						break;
				}
				Db::commit();
				$this->ajaxJson(1, '兑换成功', $arr);
	        } catch (Exception $e) {
	        	Db::rollback();
	            Log::error($e->getCode() . '：' . (string) $e);
	            $this->ajaxJson($e->getCode(), $e->getMessage());
	        }
		}
		$userId         = $this->userId;
		$view['assets'] = $this->userAssets;
		return view('convertibilityReInvestment', $view);
	}

	/**
	 * 激活
	 * http://vpay.project.com/vpay/index/activation
	 */
	public function activation()
	{
		if(request()->isPost()){
			Db::startTrans();
			try {
				$userId = $this->userId;
				$param = request()->param();
                //$friendUId = VpayUsers::getUserStatus($param['mobile']);
                $friendUId = VpayUsers::getUserStatus($param['mobile']);
				VpayUsers::validatePayPwd($userId, $param['payPwd']);
				VpayTurnOutActivationCode::activationUsers($userId, $friendUId, $param['amount']);

				Db::commit();
				$this->ajaxJson(1, '激活成功');
	        } catch (Exception $e) {
	        	Db::rollback();
	            Log::error($e->getCode() . '：' . (string) $e);
	            $this->ajaxJson($e->getCode(), $e->getMessage());
	        }
		}
		$config = [
			'min' => Config::get('activation_code_min'),
			'max' => Config::get('activation_code_max'),
		];
		$view['assets'] = $this->userAssets;
		$view['config'] = $config;
		return view('activation', $view);
	}

	/**
	 * 转账
	 * http://vpay.project.com/vpay/index/transferAccounts
	 */
	public function transferAccounts()
	{
		if(request()->isPost()){
			Db::startTrans();
			try {
				$userId = $this->userId;
				$param = request()->param();
				$friendUId = VpayUsers::getUserStatus($param['mobile']);
				VpayUsers::validatePayPwd($userId, $param['payPwd']);
				if($userId == $friendUId) throw new Exception('自己不能给自己转账');

				$arr = [];
				switch ($param['type']) {
					case 'coupon':
						$arr = VpayTurnOutCoupon::couponTrunFriends($userId, $friendUId, $param['amount']);
						break;
					case 'activation':
						$arr = VpayTurnOutActivationCode::activationTrunFriends($userId, $friendUId, $param['amount']);
						break;
					default:
						throw new Exception('错误操作');
						break;
				}

				Db::commit();
				$this->ajaxJson(1, '转账成功', $arr);
	        } catch (Exception $e) {
	        	Db::rollback();
	            Log::error($e->getCode() . '：' . (string) $e);
	            $this->ajaxJson($e->getCode(), $e->getMessage());
	        }
		}
		$view['assets'] = $this->userAssets;
		return view('transferAccounts', $view);
	}

	/**
	 * 获取用户资产信息
	 * http://vpay.project.com/vpay/index/getUserAssetsInfo
	 */
	public function getUserAssetsInfo()
	{
		if(!request()->isAjax()) $this->error('错误操作');
		$serAssets = VpayUsers::getUserAssets($this->userId);
		$this->ajaxJson(1, '获取成功', $serAssets);
	}
}
