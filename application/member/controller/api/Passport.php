<?php

namespace app\member\controller\api;

use app\ApiController;

use app\member\model\UsersModel;
/*------------------------------------------------------ */
//-- 会员登陆、注册、找回密码相关API
/*------------------------------------------------------ */

class Passport extends ApiController
{
    /*------------------------------------------------------ */
    //-- 优先执行
    /*------------------------------------------------------ */
    public function initialize()
    {
        parent::initialize();
        $this->Model = new UsersModel();
    }

    /*------------------------------------------------------ */
    //-- 用户登陆
    /*------------------------------------------------------ */
    public function login()
    {
        $this->checkPostLimit('login');//验证请求次数
//        $this->checkCode('login',input('mobile'),input('code'));//验证短信验证
        $res = $this->Model->login(input());
        if (is_array($res) == false) return $this->error($res);
        $data['code'] = 1;
        $data['msg'] = '登录成功.';
        if ($res[0] == 'developers'){
            $data['developers'] = $res[1];
        }
        if(session('REQUEST_URI')){
            $data['url'] = session('REQUEST_URI');
        }
        return $this->ajaxReturn($data);
    }

    /*------------------------------------------------------ */
    //-- 注册用户
    /*------------------------------------------------------ */
    public function register()
    {
		$register_status = settings('register_status');
		if ($register_status != 1){
			return $this->error('暂不开放注册.');
		}
//        $this->checkCode('register',input('mobile'),input('code'));//验证短信验证
        $res = $this->Model->register(input());
        if ($res !== true) return $this->error($res);
        return $this->success('注册成功.');
    }
	/*------------------------------------------------------ */
    //-- 找回用户密码
    /*------------------------------------------------------ */
    public function forgetPwd()
    {
//        $this->checkCode('forget_password',input('mobile'),input('code'));//验证短信验证
        $res = $this->Model->forgetPwd(input(),$this);
        if ($res !== true) return $this->error($res);
        return $this->success('密码已重置，请用新密码登陆.');
    }

    // 找回支付密码
    public function forgetPayPwd()
    {
//        $this->checkCode('forget_password',input('mobile'),input('code'));//验证短信验证
        $res = $this->Model->forgetPayPwds(input(),$this);
        if ($res !== true) return $this->error($res);
        return $this->success('支付密码已重置.');
    }


    // 绑定子账号
    public function binderAccount()
    {
//        $this->checkCode('register',input('mobile'),input('code'));//验证短信验证
        $res = $this->Model->addBinderAccount(input());
        if ($res !== true) return $this->error($res);
        return $this->success('绑定成功.');
    }

    // 登录子账号
    public function loginSubaccount()
    {
        $res = $this->Model->loginBinderAccount(input());
        if (is_array($res) == false) return $this->error($res);
        $data['code'] = 1;
        $data['msg'] = '切换成功.';
        if ($res[0] == 'developers'){
            $data['developers'] = $res[1];
        }
        if(session('REQUEST_URI')){
            $data['url'] = session('REQUEST_URI');
            session('REQUEST_URI', null);
        }
        return $this->ajaxReturn($data);
    }
}