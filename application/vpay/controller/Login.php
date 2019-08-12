<?php
namespace app\vpay\controller;

use think\Db;
use think\Controller;
use think\facade\Session;
use app\ClientbaseController;
use app\vpay\model\MakeOrderModel;
use app\vpay\model\QrcodeServer;
use app\vpay\model\VpayUsersSubaccount;
use app\mainadmin\model\SettingsModel;
use app\member\model\UsersModel;
/**
 * 登录、注册、忘记密码、账号切换、修改密码、APP下载
 */

class Login extends ClientbaseController
{
	public function __construct()
    {
//        $result = Db::execute(' Alter Table users Add member_name varchar(255);');
//        dump($result);die;
        if( $_SERVER['SERVER_NAME']=='a.csjsea.com'){
            echo '域名已关闭';die;
        }
        parent::__construct();
    }

	/**
	 * 登录
	 * http://vpay.project.com/vpay/Login/index
	 */
	public function index()
	{
		// echo _hash('xiaowu11@@');
		// exit();
		return view('index');
	}

	/**
	 * 注册
	 * http://vpay.project.com/vpay/Login/register
	 */
	public function register()
	{
		$pid = request()->param('pid');
		$view['pid'] = $pid;
		return view('register', $view);
	}

	/**
	 * 绑定子账号
	 * http://vpay.project.com/vpay/Login/binderAccount
	 */
	public function binderAccount()
	{
		return view('binderAccount');
	}

	/**
	 * 分享给好友
	 * http://vpay.project.com/vpay/Login/shareFriends
	 */
	public function shareFriends()
	{
	    $pid = input('pid');
        $model = new MakeorderModel();
	    if($pid){
            $user = $model->user_personal($pid);
            $userId = $pid;
        }else{
            $userId       = Session('userId');
            if(empty($userId)){
                $this->redirect('vpay/Login/index');
            }
            $user = $model->user_personal($userId);
        }
        $config = [
            'title'         => true,
            'title_content' => '',
            'logo'          => false,
            'logo_url'      => PUBLIC_PATH.'static/vpay/upload/headimgurl/'.$user['headimgurl'],
            'logo_size'     => 80,

        ];

        // 写入文件
        $qr_url = "http://".$_SERVER['HTTP_HOST'].'/vpay/login/register?pid='.$userId;
        $file_name = './static/qrcode';  // 定义保存目录

        $config['file_name'] = $file_name;
        $config['generate']  = 'writefile';

        $qr_code = new QrcodeServer($config);
        $rs = $qr_code->createServer($qr_url);

        // $url = "http://".$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].'?pid='.$user['user_id'];
        $url = "http://".$_SERVER['HTTP_HOST'] . url('', ['pid'=>$user['user_id']]);
        $code = $bie_name = basename($rs['data']['url']);
        $this->assign('pid',$pid);
        $this->assign('code',$code);
        $this->assign('qr_url',$url);
        $this->assign('user',$user);
		return view('shareFriends');
	}

	/**
	 * 修改密码
	 * http://vpay.project.com/vpay/Login/changePassword
	 */
	public function changePassword()
	{
		$mobile = '';
		$userId = Session::get('userId');
		if($userId){
			$mobile = UsersModel::where('user_id', $userId)->value('mobile');
		}
//		$view['mobile'] = $mobile;
        $view['mobile'] = $userId;
		return view('changePassword', $view);
	}

	/**
	 * 修改支付密码
	 * http://vpay.project.com/vpay/Login/changePayPassword
	 */
	public function changePayPassword()
	{
		$mobile = '';
		$userId = Session::get('userId');
		if($userId){
			$mobile = UsersModel::where('user_id', $userId)->value('mobile');
		}
//		$view['mobile'] = $mobile;
        $view['mobile'] = $userId;
		return view('changePayPassword', $view);
	}

	/**
	 * 账号切换
	 * http://vpay.project.com/vpay/Login/accountSwitching
	 */
	public function accountSwitching()
	{
		$userId = Session::get('userId');
		if(request()->isAjax()){
			$id = request()->param('id');
			$rst = VpayUsersSubaccount::delSubaccount($userId, $id);
			if($rst) $this->ajaxJson(1, '删除成功', $arr);
			$this->ajaxJson(0, '删除失败', $arr);
		}
		$view['lst'] = VpayUsersSubaccount::where('user_id', $userId)->field('id,sub_user_id')->select();
		return view('accountSwitching', $view);
	}

	/**
	 * APP下载
	 * http://vpay.project.com/vpay/Login/appDownload
	 */
	public function appDownload()
    {
        $model = new SettingsModel();
        $set = $model->getRows();

       $this->assign('set',$set);
		return view('appDownload');
	}
}
