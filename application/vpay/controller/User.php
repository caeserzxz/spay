<?php

namespace app\vpay\controller;

use think\Controller;
use think\Db;
use think\facade\Session;
use app\ClientbaseController;

use app\vpay\model\Users;
use app\vpay\model\VpayUsers;
use app\vpay\model\VpayRecordCoupon;
use app\vpay\model\VpayRecordAssetsBundle;
use app\vpay\model\VpayRecordActivationCode;
use app\vpay\model\VpayRecordPassCard;

use app\mainadmin\model\ArticleModel;
use app\vpay\model\MakeOrderModel;
use app\member\model\UsersModel;

class User extends ClientbaseController
{
	public function __construct()
    {
        parent::__construct();
        $this->userId = Session::get('userId');
    }

	/**
	 * 个人中心
	 * http://vpay.project.com/vpay/User/index
	 */
	public function index()
	{
		$userId = $this->userId;
		$info   = Users::getUserMobileAndName($userId);
		if($info > 0){
			$parentInfo         = Users::getUserMobileAndName($info['pid']);
			$view['parentInfo'] = $parentInfo;
		}

		// 关于我们
		$where = [
			'isdel' => 1,
			'cid'   => 10
		];
		$article = ArticleModel::where($where)->field('id')->order('add_time desc, id desc')->find();
		$view['info']    = $info;
		$view['article'] = $article;
		$view['h_time'] = date('H');
		return view('index', $view);
	}

	/**
	 * 个人资料
	 * http://vpay.project.com/vpay/User/personalData
	 */
	public function personalData()
	{
		$model  = new MakeorderModel();
		$userId = Session::get('userId');
		$users  = $model->user_personal($userId);

        if(request()->isPost()){
			$data       = input('post.');
			$headimgurl = $_FILES['headimgurl'];

            if(empty($data['birthday'])){
                unset($data['birthday']);
            }
            if($data['sex']=='男'){
                $data['sex'] = 1;
            }else{
                $data['sex'] = 0;
            }
            try {
	            if($headimgurl['tmp_name']){
	                $headimgurl = $model->upload_img('headimgurl');
	               if($headimgurl){
	                   $data['headimgurl'] = $headimgurl;
	               }
	            }
                Db::name('users')
                    ->where('user_id',$data['user_id'])
                    ->update($data);
                $return['status'] = 1;
                $return['msg'] = '操作成功';
                return $return;

            }catch (\Exception $e) {  //如书写为（Exception $e）将无效
                $msg = $e->getMessage();
                $return['status'] = -1;
                $return['msg'] = $msg;
                return $return;
            }

        }else{
            $appType = Session::get("appType");
            $this->assign('appType',$appType);
            $this->assign('users',$users);
            return view('personalData');
        }
	}

	/**
	 * 我的收款信息
	 * http://vpay.project.com/vpay/User/myReceiptInfo
	 */
	public function myReceiptInfo()
	{
        $userId   = Session::get('userId');
        $model = new MakeorderModel();
        if(request()->isPost()){
            $data = input('post.');
            $data['uid'] = $userId;
            $wx_code_img = $_FILES['wx_code_img'];
            if( $wx_code_img['tmp_name']) {
                $wx_code_img_path = $model->upload_img('wx_code_img');
                if($wx_code_img_path){
                    $data['wx_code_img'] = $wx_code_img_path;
                }
            }
            $alipay_code_img = $_FILES['alipay_code_img'];
            if( $alipay_code_img['tmp_name']) {
                $alipay_code_img_path = $model->upload_img('alipay_code_img');
                if($alipay_code_img_path){
                    $data['alipay_code_img'] = $alipay_code_img_path;
                }
            }
            if($data['id']){
                $data['save_time'] = time();
                $res = Db::name('vpay_put_away')
                    ->where('id',$data['id'])
                    ->update($data);
            }else{
                $res = Db::name('vpay_put_away')
                    ->where('id',$data['id'])
                    ->insert($data);
            }

            if($res){
                $return['status'] = 1;
                $return['msg'] = '保存成功';
                return $return;
            }else{
                $return['status'] = -1;
                $return['msg'] = '保存失败';
                return $return;
            }
        }else{
            $payinfo = $model ->get_user_payinfo($userId);
            $appType = Session::get("appType");
            $this->assign('appType',$appType);
            $this->assign('payinfo',$payinfo);
            return view('myReceiptInfo');
        }

	}

	/**
	 * 我的团队
	 * http://vpay.project.com/vpay/User/myTeam
	 */
	public function myTeam()
	{
		$userId = $this->userId;
		$param  = request()->param();
		$lst    = VpayUsers::getMyInfiniteOrderTeam($userId, $param);

		if(request()->isAjax()) $this->ajaxJson(1, '获取成功', $lst);
		$view['lst'] = json_encode($lst);
		return view('myTeam', $view);
	}

	/**
	 * sea券明细
	 * http://vpay.project.com/vpay/User/couponDetails
	 */
	public function couponDetails()
	{
		$userId = $this->userId;
		$param  = request()->param();
		$lst    = VpayRecordCoupon::getCouponRecordLst($userId, $param);

		if(request()->isAjax()) $this->ajaxJson(1, '获取成功', $lst);
		$view['lst'] = json_encode($lst);
		return view('couponDetails', $view);
	}


	/**
	 * sea通证明细
	 * http://vpay.project.com/vpay/User/passCardDetails
	 */
	public function passCardDetails()
	{
		$userId = $this->userId;
		$param  = request()->param();
		$lst    = VpayRecordPassCard::getPassCardLst($userId, $param);

		if(request()->isAjax()) $this->ajaxJson(1, '获取成功', $lst);
		$view['lst'] = json_encode($lst);
		return view('passCardDetails', $view);
	}

	/**
	 * 激活码明细
	 * http://vpay.project.com/vpay/User/activationCodeDetails
	 */
	public function activationCodeDetails()
	{
		$userId = $this->userId;
		$param  = request()->param();
		$lst    = VpayRecordActivationCode::getActivationCodeLst($userId, $param);

		if(request()->isAjax()) $this->ajaxJson(1, '获取成功', $lst);
		$view['lst'] = json_encode($lst);
		return view('activationCodeDetails', $view);
	}

	/**
	 * 资产包明细
	 * http://vpay.project.com/vpay/User/assetsBundleDetails
	 */
	public function assetsBundleDetails()
	{
		$userId = $this->userId;
		$param  = request()->param();
		$lst    = VpayRecordAssetsBundle::getAssetsBundleLst($userId, $param);

		if(request()->isAjax()) $this->ajaxJson(1, '获取成功', $lst);
		$view['lst'] = json_encode($lst);
		return view('assetsBundleDetails', $view);
	}

    public function uploadimage(){
        //$base_img是获取到前端传递的src里面的值，也就是我们的数据流文件
        $base_img = $_POST['img'];
        $img_type = $_POST['img_type'];
        $base_img = str_replace('data:image/png;base64,', '', $base_img);
        //设置文件路径和文件前缀名称
        $path = UPLOAD_PATH."/".$img_type."/".date(Ymd,time()).'/';
        is_dir($path) OR mkdir($path, 0777, true);
        $prefix='nx_';
        $output_file = $prefix.time().'.png';
        $path = $path.$output_file;
        $ifp = fopen( $path, "wb" );
        fwrite( $ifp, base64_decode( $base_img) );
        fclose( $ifp );
        //return date(Ymd,time()).'/'.$output_file;
        $retrun['path'] = $path.$output_file;
        $return['image_path'] = date(Ymd,time()).'/'.$output_file;
        $return['img_type'] = $img_type;
        return $return;
    }

    //验证支付密码
    public function verification(){
        $userId = $this->userId;
        $pay_password = input('pay_password');
        if(empty($pay_password)){
            $return['msg'] = "请输入支付密码";
            $return['status'] = -1;
            return $return;
        }
        $payPwd = UsersModel::where('user_id', $userId)->value('pay_password');
        $pwd = f_hash($pay_password);

        if($payPwd!==$pwd){
            $return['msg'] = "支付密码不正确";
            $return['status'] = -1;
            return $return;
        }else{
            $return['msg'] = "密码正确";
            $return['status'] = 1;
            return $return;
        }

    }
}
