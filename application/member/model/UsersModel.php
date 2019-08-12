<?php

namespace app\member\model;

use app\BaseModel;
use think\facade\Cache;
use think\Db;

use app\weixin\model\WeiXinUsersModel;
use app\distribution\model\DividendRoleModel;

use app\vpay\model\VpayUsers;
use app\vpay\model\VpayUsersSubaccount;

//*------------------------------------------------------ */
//-- 会员表
/*------------------------------------------------------ */

class UsersModel extends BaseModel
{
    protected $table = 'users';
    protected $mkey = 'user_info_mkey_';
    public $pk = 'user_id';
    /*------------------------------------------------------ */
    //--  清除memcache
    /*------------------------------------------------------ */
    public function cleanMemcache($user_id)
    {
        Cache::rm($this->mkey . $user_id);
        Cache::rm($this->mkey . 'account_' . $user_id);
    }
    /*------------------------------------------------------ */
    //-- 会员登陆
    /*------------------------------------------------------ */
    public function login($data = array())
    {
//        $res = $this->checkPwd($data['password']);
//        if ($res !== true) return '密码不正确，格式错误. ' . $res;
        $password = f_hash(trim($data['password']));
        $mobile = trim($data['mobile']);
        //7.3号改为用user_id 登录
//        $userInfo = $this->where('mobile', $mobile)->find();

        $userInfo = $this->where('user_id', $mobile)->find();
        if(empty($userInfo)){
            $userInfo = $this->where('member_name', $mobile)->find();
        }
        if (empty($userInfo)) {
            return '用户不存在.';
        }
        if ($userInfo['is_ban'] == 1) {
            return '用户已被禁用.';
        }

        $time = time();
        if ($userInfo['login_odd_num'] >= 10) {
            if ($userInfo['login_odd_time'] > $time - 3600) {
                return '密码错误次数过多帐号封停，解封时间：' . date('Y-m-d H:i:s', $userInfo['login_odd_time'] + 3600);
            } else {
                $userInfo['login_odd_num'] = 7;//如果已到解封时间，给3次机会再登陆
            }
        }
        if ($userInfo['password'] != $password) {
            //记录异常登陆
            $this->where('user_id', $userInfo['user_id'])->update(['login_odd_time' => $time, 'login_odd_num' => $userInfo['login_odd_num'] + 1]);
            return '用户或密码不正确.';
        }
        $upData['login_odd_num'] = 0;//登陆异常清空
        $upData['login_time'] = $time;
        $upData['login_ip'] = request()->ip();
        $upData['last_login_time'] = $userInfo['login_time'];
        $upData['last_login_ip'] = $userInfo['login_ip'];
        $this->where('user_id', $userInfo['user_id'])->update($upData);
        session('userId', $userInfo['user_id']);
        $LogLoginModel = new LogLoginModel();
        $inLog['log_ip'] = $upData['login_ip'];
        $inLog['log_time'] = $time;
        $inLog['user_id'] = $userInfo['user_id'];
        $LogLoginModel->save($inLog);
        $this->userInfo = $this->info($userInfo['user_id']);//附值全局
        $wxInfo = session('wxInfo');
        if (empty($wxInfo) == false){
            (new \app\weixin\model\WeiXinUsersModel)->where('wxuid',$wxInfo['wxuid'])->update(['user_id'=>$userInfo['user_id']]);
        }
        //判断订单模块是否存在
        if (class_exists('app\shop\model\OrderModel')) {
            //执行订单自动签收
            (new \app\shop\model\OrderModel)->autoSign($userInfo['user_id']);
            (new \app\shop\model\CartModel)->loginUpCart($userInfo['user_id']);//更新购物车
        }

        if (empty($data['source']) == false){
            if ($data['source'] == 'developers'){
                $devtoken = random_str(10).date(s);
                Cache::set('devlogin_'.$devtoken,$userInfo['user_id'],86400 * 7);
                return [$data['source'],$devtoken];
            }
        }

        return ['H5',$userInfo['user_id']];
    }

    /*------------------------------------------------------ */
    //-- 验证密码强度
    /*------------------------------------------------------ */
    private function checkPwd($pwd)
    {
        $pwd = trim($pwd);
        if (empty($pwd)) {
            return '密码不能为空';
        }
        if (strlen($pwd) < 8) {//必须大于8个字符
            return '密码必须大于八字符';
        }
        if (preg_match("/^[0-9]+$/", $pwd)) { //必须含有特殊字符
            return '密码不能全是数字，请包含数字，字母大小写或者特殊字符';
        }
        if (preg_match("/^[a-zA-Z]+$/", $pwd)) {
            return '密码不能全是字母，请包含数字，字母大小写或者特殊字符';
        }
        if (preg_match("/^[0-9A-Z]+$/", $pwd)) {
            return '请包含数字，字母大小写或者特殊字符';
        }
        if (preg_match("/^[0-9a-z]+$/", $pwd)) {
            return '请包含数字，字母大小写或者特殊字符';
        }
        return true;
    }
    /*------------------------------------------------------ */
    //-- 生成用户唯一标识,主要用于分享后身份识别
    /*------------------------------------------------------ */
    public function getToken()
    {
        $token = random_str(16);
        $count = $this->where('token', $token)->count('user_id');
        if ($count >= 1) return $this->getToken();
        return $token;
    }
    /*------------------------------------------------------ */
    //-- 会员注册
    /*------------------------------------------------------ */
    public function register($inArr = array(), $wxuid = 0)
    {
        if ($wxuid == 0) {
            if (empty($inArr)) {
                return '获取注册数据失败.';
            }
            if(empty($inArr['member_name'])){
                return '请填写用户名';
            }else{
                $count = $this->where('member_name', $inArr['member_name'])->count('user_id');
                if ($count > 0) return '用户名：' . $inArr['member_name'] . '，已存在.';
            }
            if (empty($inArr['user_name'])) {
                return '请填写真实姓名';
            }
            if ($inArr['password'] !== $inArr['confirmPwd']) {
                return '密码和确认密码不一致';
            }
            if (checkMobile($inArr['mobile']) == false) {
                return '手机号码不正确.';
            }
//            $count = $this->where('mobile', $inArr['mobile'])->count('user_id');
//            if ($count > 0) return '手机号码：' . $inArr['mobile'] . '，已存在.';
            if (empty($inArr['nick_name']) == false) {//昵称不为空时，判断是否已存在
                $count = $this->where('nick_name', $inArr['nick_name'])->count('user_id');
                if ($count > 0) return '昵称：' . $inArr['nick_name'] . '，已存在.';
            }
//            $res = $this->checkPwd($inArr['password']);//验证密码强度
//            if ($res !== true) {
//                return $res;
//            }
//            $res = $this->checkPwd($inArr['pay_password']);//验证密码强度
//            if ($res !== true) {
//                return $res;
//            }
            unset($inArr['confirmPwd']);
            $time                  = time();
            $inArr['password']     = f_hash($inArr['password']);
            $inArr['pay_password'] = f_hash($inArr['pay_password']);
        }

        $inArr['token']    = $this->getToken();
        $inArr['reg_time'] = $time;

        // 推荐注册
        if(!empty($inArr['pid'])){
            $count = self::where('user_id', $inArr['pid'])->count();
            if($count == 0){
                return '推荐人ID不存在，请重新输入.';
            }
        }
        else{
            //$inArr['pid'] = 0;
            return '推荐人ID不存在，请重新输入.';
        }

        //分享注册
        $share_token = session('share_token');
        if (empty($share_token) == false) {
            $pInfo = $this->getShareUser($share_token);
            if ($pInfo['is_ban'] != 1) {
                $inArr['pid'] = $pInfo['user_id'] * 1;
            }
        }//end


        if ($wxuid == 0) {//如果微信UID为0，启用事务，不为0时，外部已启用
            Db::startTrans();
        }
        $res = $this->save($inArr);
        if ($res < 1) {
            Db::rollback();
            return '未知错误-1，请尝试重新提交.';
        }
        $user_id = $this->user_id;
        if ($user_id < 29889) {
            $this->where('user_id',$user_id)->delete();
            $inArr['user_id'] = 29889;
            $res = $this->create($inArr);
            $user_id = $res->user_id;
            if ($user_id < 1) {
                Db::rollback();
                return '未知错误-2，请尝试重新提交.';
            }
        }

        // 往vpay表插入一条用户信息
        VpayUsers::addUserInfo($user_id, $inArr['pid']);

        //创建会员帐户信息
        $AccountLogModel = new AccountLogModel();
        $res = $AccountLogModel->createData(['user_id' => $user_id, 'update_time' => $time]);
        if ($res < 1) {
            Db::rollback();
            return '未知错误-2，请尝试重新提交.';
        }
        //edn

        //注册赠送积分
        $register_integral = settings('register_integral') * 1;
        if ($register_integral > 0) {
            $changedata['change_desc'] = '注册赠送积分';
            $changedata['change_type'] = 7;
            $changedata['by_id'] = $user_id;
            $changedata['use_integral'] = $register_integral;
            $changedata['total_integral'] = $register_integral;
            $res = $AccountLogModel->change($changedata, $user_id, false);
            if ($res < 1) {
                Db::rollback();
                return '未知错误-3，请尝试重新提交.';
            }
        }
        //edn

        //捆绑微信会员信息
        if ($wxuid == 0) {
            $wxInfo = session('wxInfo');
            $wxuid = $wxInfo['wxuid'];
        }
        if ($wxuid > 0) {
            $WeiXinUsersModel = new WeiXinUsersModel();
            $res = $WeiXinUsersModel->bindUserId($wxuid, $user_id);
            if ($res < 1) {
                Db::rollback();
                return '未知错误-4，请尝试重新提交.';
            }
        } //end

        Db::commit();
        $DividendInfo = settings('DividendInfo');
        if ($DividendInfo['bind_type'] < 1) {
            //写入九级关系链
            $this->regUserBind($user_id);
        }

        //红包模块存在执行
        if (class_exists('app\shop\model\BonusModel')) {
            //注册送红包
            (new \app\shop\model\BonusModel)->sendByReg($user_id);
        }

        return true;
    }

    /*------------------------------------------------------ */
    //-- 找回用户密码
    /*------------------------------------------------------ */
    public function forgetPwd($data = array(), &$obj)
    {
        if (empty($data)) {
            return '获取数据失败.';
        }
        if (empty($data['mobile'])) {
            return '请填写ID或用户名';
        }
//        if (checkMobile($data['mobile']) == false) {
//            return '手机号码不正确.';
//        }
//        $res = $this->checkPwd($data['password']);//验证密码强度
//        if ($res !== true) {
//            return $res;
//        }
        //7.3改成id登录
//        $user = $this->where('mobile', $data['mobile'])->find();
        $user = $this->where('user_id', $data['mobile'])->find();
        if(empty($user)){
            $user = $this->where('member_name', $data['mobile'])->find();
        }
        if(!$user) return '用户不存在';
        if(f_hash($data['oldpassword']) != $user['password']){
            return '旧密码输入错误,请核实.';
        }
        if (f_hash($data['password']) == $user['password']) {
            return '新密码与旧密码一致,请核实.';
        }
        $upArr['password'] = f_hash($data['password']);
        $res = $this->where('user_id', $user['user_id'])->update($upArr);
        if ($res < 1) return '未知错误，修改会员密码失败.';
//        $obj->_log($res, '用户找回密码.', 'member');

        session(null);
        cookie(null);

        return true;
    }

    // 找回支付密码
    public function forgetPayPwds($data = array(), &$obj)
    {
        if (empty($data)) {
            return '获取数据失败.';
        }
        if (empty($data['mobile'])) {
            return '请填写ID';
        }
//        if (checkMobile($data['mobile']) == false) {
//            return '手机号码不正确.';
//        }

//        $res = $this->checkPwd($data['pay_password']);//验证密码强度
//        if ($res !== true) {
//            return $res;
//        }
//        7.3改成ID登录
//        $user = $this->where('mobile', $data['mobile'])->find();
        $user = $this->where('user_id', $data['mobile'])->find();
        if(!$user) return '用户不存在';
        if(f_hash($data['old_pay_password']) != $user['pay_password']){
            return '原支付密码输入错误,请核实.';
        }
        if (f_hash($data['pay_password']) == $user['pay_password']) {
            return '新密码与旧密码一致,请核实.';
        }
        $upArr['pay_password'] = f_hash($data['pay_password']);
        $res = $this->where('user_id', $user['user_id'])->update($upArr);
        if ($res < 1) return '未知错误，修改会员密码失败.';
//        $obj->_log($res, '用户找回支付密码.', 'member');
        return true;
    }

    /*------------------------------------------------------ */
    //-- 修改用户密码
    /*------------------------------------------------------ */
    public function editPwd($data = array(), &$obj)
    {
        if (empty($data)) {
            return '获取数据失败.';
        }
//        $res = $this->checkPwd($data['password']);//验证密码强度
//        if ($res !== true) {
//            return $res;
//        }
        $user = $this->where('user_id', $this->userInfo['user_id'])->find();
        $oldPwd = f_hash($data['old_password']);
        if ($oldPwd != $user['password']) {
            return '旧密码错误.';
        }
        $upArr['password'] = f_hash($data['password']);
        if ($upArr['password'] == $user['password']) {
            return '新密码与旧密码一致无须修改.';
        }
        $res = $this->where('user_id', $user['user_id'])->update($upArr);
        if ($res < 1) return '未知错误，修改会员密码失败.';
//        $obj->_log($user['user_id'], '用户修改密码.', 'member');
        return true;
    }
    /*------------------------------------------------------ */
    //-- 绑定会员手机
    /*------------------------------------------------------ */
    public function bindMobile($data = array(), &$obj)
    {
        if (empty($data)) {
            return '获取数据失败.';
        }
//        $res = $this->checkPwd($data['password']);//验证密码强度
//        if ($res !== true) {
//            return $res;
//        }
        if (is_numeric($data['pay_password']) == false){
            return '请填写6位数字的支付密码.';
        }
        $count = $this->where('mobile', $data['mobile'])->count('user_id');
        if ($count > 0) {
            return $data['mobile'] . '此手机号码已绑定其它帐号.';
        }
        $upArr['mobile'] = $data['mobile'];
        $upArr['password'] = f_hash($data['password']);
        $upArr['pay_password'] = f_hash($data['pay_password'].$this->userInfo['user_id']);
        $res = $this->where('user_id', $this->userInfo['user_id'])->update($upArr);
        if ($res < 1) return '未知错误，绑定手机失败.';
//        $obj->_log($this->userInfo['user_id'], '用户绑定手机号码.', 'member');
        return true;
    }
    /*------------------------------------------------------ */
    //-- 获取用户信息
    //-- val 查询值
    //-- type 查询类型
    //-- isCache 是否调用缓存
    /*------------------------------------------------------ */
    public function info($val, $type = 'user_id', $isCache = true)
    {
        if (empty($val)) return false;
        if ($isCache == true) $info = Cache::get($this->mkey . $val);
        if (empty($info) == false) return $info;
        if ($type == 'token') {
            $info = $this->where('token', $val)->find();
            if (empty($info)){
                return [];
            }
            $info = $info->toArray();
        } else {
            $info = $this->where('user_id', $val)->find();
            if (empty($info)){
                return [];
            }
            $info = $info->toArray();
            $AccountModel = new AccountModel();
            $account = $AccountModel->where('user_id', $val)->find();
            if (empty($account) == true) {
                //创建会员帐户信息
                $AccountModel->createData(['user_id' => $val, 'update_time' => time()]);
                $account = $AccountModel->where('user_id', $val)->find();
            }
            $info['account'] = $account->toArray();
        }
        unset($info['password']);
        $info['shareUrl'] = config('config.host_path') . '/?share_token=' . $info['token'];//分享链接
        $info['level'] = userLevel($info['account']['total_integral'], false);//获取等级信息
        if ($info['role_id'] > 0) {
            $info['role'] = (new DividendRoleModel)->info($info['role_id']);
        }else{
            $info['role']['role_id'] = 0;
            $info['role']['role_name'] = '粉丝';
        }
        Cache::set($this->mkey . $val, $info, 30);
        return $info;
    }
    /*------------------------------------------------------ */
    //--获取上级信息
    /*------------------------------------------------------ */
    public function getSuperior($pid)
    {
        if ($pid < 1) return [];
        $info = $this->info($pid);
        unset($info['password']);//销毁不需要的字段
        return $info;
    }
    /*------------------------------------------------------ */
    //--获取会员帐户
    /*------------------------------------------------------ */
    public function getAccount($user_id, $isCache = true)
    {
        $user_id = $user_id * 1;
        if ($user_id < 1) return array();
        $mkey = $this->mkey . 'account_' . $user_id;
        if ($isCache == true) $info = Cache::get($mkey);
        if (empty($info) == false) return $info;
        $info = $this->where('u.user_id', $user_id)->alias('u')->field('u.user_id,u.mobile,ua.*')->join('users_account ua', 'u.user_id = ua.user_id', 'left')->find();
        if (empty($info) == false){
            $info = $info->toArray();
        }
        Cache::set($mkey, $info, 60);
        return $info;
    }
    /*------------------------------------------------------ */
    //-- 更新会员信息
    /*------------------------------------------------------ */
    public function upInfo($user_id, $data)
    {
        $user_id = $user_id * 1;
        $res = $this->where('user_id', $user_id)->update($data);
        $this->cleanMemcache($user_id);
        return $res;
    }
    /*------------------------------------------------------ */
    //-- 根据token获取分享者进行关联
    /*------------------------------------------------------ */
    public function getShareUser($token = '')
    {
        if (empty($token)) return array();
        return $this->where('token', $token)->find();
    }
    /*------------------------------------------------------ */
    //-- 获取会员下级汇总
    /*------------------------------------------------------ */
    public function userShareStats($user_id = 0, $isCache = true)
    {
        $info = Cache::get($this->mkey . '_us_' . $user_id);
        if ($isCache == true && empty($info) == false) return $info;
        $user_id = $user_id * 1;
        $UsersBind = new UsersBindModel();
        $rows = $UsersBind->field("count('user_id') as num,level")->where('pid', $user_id)->group('level')->select();
        $d_level = config('config.dividend_level');
        $info['all'] = 0;
        foreach ($d_level as $key => $val) {
            $info[$key] = 0;
        }
        foreach ($rows as $row) {
            $info['all'] += $row['num'];
            $info[$row['level']] = $row['num'];
        }
        Cache::set($this->mkey . '_us_' . $user_id, $info, 30);
        return $info;
    }
    /*------------------------------------------------------ */
    //-- 操作等级关联
    // -- user_id int 会员ID
    // -- pid  int  所属上级ID
    // -- is_edit boolean 是否重新修改，不是修改发送绑定消息通知
    /*------------------------------------------------------ */
    public function regUserBind($user_id = 0, $pid = 0, $is_edit = false)
    {
        static $UsersBindModel;
        if ($user_id < 1) return true;
        if ($is_edit == false){
            $DividendSatus = settings('DividendSatus');
            if ($DividendSatus == 0) return true;//不开启推荐，不执行
            $userInfo = $this->where('user_id', $user_id)->field('is_bind,pid')->find();
            if ($userInfo['is_bind'] > 0) return false;//已执行绑定不再执行
        }

        if ($pid == 0) {
            $share_token = session('share_token');
            if (empty($share_token) == false) {
                $pInfo = $this->getShareUser($share_token);
                if ($pInfo['is_ban'] != 1) {
                    $pid = $pInfo['user_id'] * 1;
                }
            } else {
                $pid = $userInfo['pid'];
            }
        }
        if ($pid < 1){
            return true;
        }

        if (isset($UsersBindModel) == false){
            $UsersBindModel = new UsersBindModel();
        }

        if ($is_edit == true) {//如果重新修改会员上级，清理原来的记录
            $UsersBindModel->where('user_id',$user_id)->delete();
        }
        $dividend_level = config('config.dividend_level');
        $bind_max_level = config('config.bind_max_level');//后台记录50层的关系链config('config.dividend_level');
        $_pid = $pid;
        for ($level=1;$level<=$bind_max_level;$level++) {
            if ($_pid < 1) break;
            if ($level <= 2) {//只记录前两级发送通知
                $sendUids[$_pid] = $dividend_level[$level];
            }
            $inArr['level'] = $level;
            $inArr['user_id'] = $user_id;
            $inArr['pid'] = $_pid;
            $res = $UsersBindModel::create($inArr);
            if ($is_edit == true && $res < 1) return false;
            $_pid = $this->where('user_id', $_pid)->value('pid');
        }

        if ($is_edit == false) {
            //发送模板消息
            $WeiXinMsgTplModel = new \app\weixin\model\WeiXinMsgTplModel();
            $WeiXinUsersModel = new \app\weixin\model\WeiXinUsersModel();
            $wxInfo = $WeiXinUsersModel->info($user_id);

            $data['user_id'] = $user_id;
            $data['nickname'] = $wxInfo['wx_nickname'];
            $data['sex'] = $wxInfo['sex'] == 1 ? '男' : '女';
            $data['region'] = $wxInfo['wx_province'] . $wxInfo['wx_city'];
            $data['send_scene'] = 'bind_user_msg';
            unset($wxInfo);
            foreach ($sendUids as $uid => $val) {
                $data['level'] = $val;
                $data['openid'] = $WeiXinUsersModel->where('user_id', $uid)->value('wx_openid');
                $WeiXinMsgTplModel->send($data);
            }
        }
        return true;
    }
    /*------------------------------------------------------ */
    //-- 获取会员的上级关联链
    /*------------------------------------------------------ */
    public function getSuperiorList($user_id = 0)
    {
        if ($user_id < 1) return array();
        $chain = Cache::get('userSuperior_' . $user_id);
        if ($chain) return $chain;
        $dividendRole = (new DividendRoleModel)->getRows();
        $i = 1;
        $user_id = $this->where('user_id', $user_id)->value('pid');
        if ($user_id < 1) return [];
        do {
            $info = $this->where('user_id', $user_id)->field('user_id,nick_name,pid,role_id,reg_time')->find();
            $chain[$i]['level'] = $i;
            $chain[$i]['user_id'] = $info['user_id'];
            $chain[$i]['reg_time'] = dateTpl($info['reg_time']);
            $chain[$i]['nick_name'] = empty($info['nick_name']) ? '未填写' : $info['nick_name'];
            $chain[$i]['role_name'] = $info['role_id'] > 0 ? $dividendRole[$info['role_id']]['role_name'] : '无身份';
            $user_id = $info['pid'];
            $i++;
        } while ($user_id > 0);

        Cache::set('userSuperior_' . $user_id, $chain, 300);
        return $chain;
    }

    // 添加绑定子账号
    public function addBinderAccount($data = array())
    {
//        $res = $this->checkPwd($data['password']);
//        if ($res !== true) return '密码不正确，格式错误. ' . $res;
        $password = f_hash($data['password']);
        $mobile   = $data['mobile'] * 1;
        //7.3号改成用户ID登录
//        $userInfo = $this->where('mobile', $mobile)->find();
        $userInfo = $this->where('user_id', $mobile)->find();
        $userId   = session('userId');

        if($userInfo['user_id'] == $userId) return '自己的账号不能绑定自己的账号';
        if (empty($userInfo)) return '用户不存在.';
        if ($userInfo['is_ban'] == 1) return '用户已被禁用.';

        $time = time();
        if ($userInfo['login_odd_num'] >= 10) {
            if ($userInfo['login_odd_time'] > $time - 3600) {
                return '密码错误次数过多帐号封停，解封时间：' . date('Y-m-d H:i:s', $userInfo['login_odd_time'] + 3600);
            } else {
                $userInfo['login_odd_num'] = 7;//如果已到解封时间，给3次机会再登陆
            }
        }
        if ($userInfo['password'] != $password) {
            //记录异常登陆
            $this->where('user_id', $userInfo['user_id'])->update(['login_odd_time' => $time, 'login_odd_num' => $userInfo['login_odd_num'] + 1]);
            return '用户或密码不正确.';
        }

        VpayUsersSubaccount::addSubaccount($userId, $userInfo['user_id'], $password);
        return true;
    }


    // 登录绑定的子账号
    public function loginBinderAccount($data = []){

        $id = $data['id'];
        $rst = VpayUsersSubaccount::where('id', $id)->find();
        if(!$rst) return '错误操作';

        $userInfo = $this->where('user_id', $rst->getData('sub_user_id'))->find();

        $upData['login_odd_num'] = 0;//登陆异常清空
        $upData['login_time'] =  time();
        $upData['login_ip'] = request()->ip();
        $upData['last_login_time'] = $userInfo['login_time'];
        $upData['last_login_ip'] = $userInfo['login_ip'];
        $this->where('user_id', $userInfo['user_id'])->update($upData);

        session(null);
        cookie(null);

        session('userId', $userInfo['user_id']);

        $LogLoginModel     = new LogLoginModel();
        $inLog['log_ip']   = $upData['login_ip'];
        $inLog['log_time'] = $time;
        $inLog['user_id']  = $userInfo['user_id'];
        $LogLoginModel->save($inLog);

        $this->userInfo = $this->info($userInfo['user_id']);//附值全局

        $wxInfo = session('wxInfo');
        if (empty($wxInfo) == false){
            (new \app\weixin\model\WeiXinUsersModel)->where('wxuid',$wxInfo['wxuid'])->update(['user_id'=>$userInfo['user_id']]);
        }
        //判断订单模块是否存在
        if (class_exists('app\shop\model\OrderModel')) {
            //执行订单自动签收
            (new \app\shop\model\OrderModel)->autoSign($userInfo['user_id']);
            (new \app\shop\model\CartModel)->loginUpCart($userInfo['user_id']);//更新购物车
        }

        if (empty($data['source']) == false){
            if ($data['source'] == 'developers'){
                $devtoken = random_str(10).date(s);
                Cache::set('devlogin_'.$devtoken,$userInfo['user_id'],86400 * 7);
                return [$data['source'],$devtoken];
            }
        }

        return ['H5',$userInfo['user_id']];
    }

}