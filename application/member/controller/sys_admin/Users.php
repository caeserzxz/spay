<?php

namespace app\member\controller\sys_admin;

use app\AdminController;
use app\member\model\UsersModel;

use app\member\model\UsersLevelModel;
use app\member\model\UsersBindModel;
use app\distribution\model\DividendRoleModel;
use app\distribution\model\DividendModel;
use app\weixin\model\WeiXinUsersModel;
use app\vpay\model\VpayUsers;
use app\vpay\model\VpayTurnOutAdminOperation;

use think\Db;
use think\facade\Cache;

/**
 * 会员管理
 * Class Index
 * @package app\store\controller
 */
class Users extends AdminController
{
    public $is_ban = 0;
    /*------------------------------------------------------ */
    //-- 优先执行
    /*------------------------------------------------------ */
    public function initialize()
    {
        parent::initialize();
        $this->Model = new UsersModel();
    }
    /*------------------------------------------------------ */
    //-- 首页
    /*------------------------------------------------------ */
    public function index()
    {
        $this->assign("start_date", date('Y/m/01', strtotime("-1 months")));
        $this->assign("end_date", date('Y/m/d'));
        $this->getList(true);
        $this->assign("roleOpt", arrToSel($this->roleList, $this->search['roleId']));
        $this->assign("levelOpt", arrToSel($this->levelList, $this->search['levelId']));
        return $this->fetch('sys_admin/users/index');
    }

    public function vpay()
    {
        if(request()->isAjax() && request()->isPost()){
            Db::startTrans();
            try {

                $param = request()->param();
                $user = VpayUsers::where('user_id', $param['user_id'])->find();
                VpayTurnOutAdminOperation::increaseReduceUserAssets($user, $param);

                if($param['status'] != $user['status']){
                    $map['status'] = $param['status'];
                    if($param['status']==2){
                        $map['frozen_time'] = time();
                    }
                    VpayUsers::where('user_id', $param['user_id'])->update($map);
                }

                Db::commit();
                return $this->success('操作成功.');
            } catch (Exception $e) {
                Db::rollback();
                Log::error($e->getCode() . '：' . (string) $e);
                $this->error($e->getMessage());
            }
        }
        $user_id         = request()->param('user_id');
        $user            = VpayUsers::where('user_id', $user_id)->field('status')->find();
        $user['user_id'] = $user_id;
        $view['user']    = $user;
        return view('vpay', $view);
    }

    /*------------------------------------------------------ */
    //-- 获取列表
    //-- $runData boolean 是否返回模板
    /*------------------------------------------------------ */
    public function getList($runData = false, $is_ban = -1)
    {

        $this->search['roleId'] = input('rode_id', 0, 'intval');
        $this->search['levelId'] = input('level_id', 0, 'intval');
        $this->search['keyword'] = input("keyword");
        $this->search['time_type'] = input("time_type");
        $this->search['status'] = input('user_status');

        $this->assign("is_ban", $this->is_ban);
        $this->assign("search", $this->search);
        $DividendRoleModel = new DividendRoleModel();
        $this->roleList = $DividendRoleModel->getRows();
        $this->assign("roleList", $this->roleList);
        $UsersLevelModel = new UsersLevelModel();
        $this->levelList = $UsersLevelModel->getRows();
        $this->assign("levelList", $this->levelList);
        $where[] = ' is_ban = ' . $this->is_ban;

        $reportrange = input('reportrange');
        if (empty($reportrange) == false) {
            $dtime = explode('-', $reportrange);
        }
        switch ($this->search['time_type']) {
            case 'reg_time':
                $where[] = ' u.reg_time between ' . strtotime($dtime[0]) . ' AND ' . (strtotime($dtime[1]) + 86399);
                break;
            case 'login_time':
                $where[] = ' u.login_time between ' . strtotime($dtime[0]) . ' AND ' . (strtotime($dtime[1]) + 86399);
                break;
            case 'buy_time':
                $where[] = ' u.last_buy_time between ' . strtotime($dtime[0]) . ' AND ' . (strtotime($dtime[1]) + 86399);
                break;
            default:
                break;
        }

        if ($this->search['roleId'] > 0) {
            $where[] = ' u.role_id = ' . $this->search['roleId'];
        }
//        if ($this->search['levelId'] > 0) {
//            $level = $this->levelList[$this->search['levelId']];
//            $where[] = ' uc.total_integral between ' . $level['min'] . ' AND ' . $level['max'];
//        }
        if ($this->search['levelId'] > 0) {
           $where[] = 'vu.level_id =' . $this->search['levelId'];
        }

        if (empty($this->search['keyword']) == false) {
            if (is_numeric($this->search['keyword'])) {
                $where[] = "  u.user_id = '" . ($this->search['keyword']) . "' or mobile like '" . $this->search['keyword'] . "%'";
            } else {
                $where[] = " ( u.user_name like '" . $this->search['keyword'] . "%' or u.nick_name like '" . $this->search['keyword'] . "%' )";
            }
        }
        if($this->search['status']){
            $where[] = 'vu.status =' . $this->search['status'];
        }
        $sort_by = input("sort_by", 'DESC', 'trim');
        $order_by = 'u.user_id';
        $viewObj = $this->Model
                    ->alias('u')
                    ->join("users_account uc", 'u.user_id=uc.user_id', 'left')
                    ->join("vpay_users vu", 'u.user_id=vu.user_id', 'left')
                    ->join('vpay_users_vest vc','u.user_id=vc.user_id','left')
                    ->where(join(' AND ', $where))
                    ->field('u.*,uc.balance_money,uc.use_integral,uc.total_integral,vu.coupon,vu.pass_card,vu.asset_bundle,vu.activation_code,vu.cumulative_coupon,vu.cumulative_asset_bundle,vu.status,vu.level_id,vc.id as vid')
                    ->order($order_by . ' ' . $sort_by);

        $data = $this->getPageList($this->Model, $viewObj);
        $data['order_by'] = $order_by;
        $data['sort_by'] = $sort_by;
        $this->assign("data", $data);
        if ($runData == false) {
            $data['content'] = $this->fetch('sys_admin/users/list');
            return $this->success('', '', $data);
        }
        return true;
    }
    /*------------------------------------------------------ */
    //-- 重置密码
    /*------------------------------------------------------ */
    public function restPassword()
    {
        $user_id = input("user_id/d");
        if ($user_id < 0) {
            return $this->error('获取用户ID传值失败！');
        }
        $data['password'] = f_hash('Abc123456');
        $oldpassword = $this->Model->where('user_id', $user_id)->value('password');
        if ($data['password'] == $oldpassword) {
            return $this->error('当前用户密码为系统默认：Abc123456，无须修改.');
        }
        $res = $this->Model->where('user_id', $user_id)->update($data);
        if ($res < 1) {
            return $this->error('未知错误，处理失败.');
        }
//        $this->_log($user_id, '重置用户密码.', 'member');
        return $this->success('操作成功.');
    }
    /*------------------------------------------------------ */
    //-- 重置支付密码
    /*------------------------------------------------------ */
    public function restPayPassword()
    {
        $user_id = input("user_id/d");
        if ($user_id < 0) {
            return $this->error('获取用户ID传值失败！');
        }
        $pay_password = rand(100000,999999);
//        $data['pay_password'] = f_hash($pay_password.$user_id);
        $data['pay_password'] = f_hash($pay_password);
        $res = $this->Model->where('user_id', $user_id)->update($data);
        if ($res < 1) {
            return $this->error('未知错误，处理失败.');
        }
//        $this->_log($user_id, '重置用户支付密码.', 'member');
        return $this->success('操作成功,新支付密码：'.$pay_password,'',['alert'=>1]);
    }
    /*------------------------------------------------------ */
    //-- 会员管理
    /*------------------------------------------------------ */
    public function info()
    {
        $user_id = input('user_id/d');
        if ($user_id < 1) return $this->error('获取用户ID传值失败！');
        $row = $this->Model->info($user_id);
        if (empty($row)) return $this->error('用户不存在！');
        $row['wx'] = (new WeiXinUsersModel)->where('user_id', $user_id)->find();
        $this->assign("userShareStats", $this->Model->userShareStats($user_id));
        $row['user_address'] = Db::table('users_address')->where('user_id', $user_id)->select();
        $this->assign('row', $row);
        $this->assign('d_level', config('config.dividend_level'));
        $DividendRoleModel = new DividendRoleModel();
        $this->assign("roleList", $DividendRoleModel->getRows());
        $this->assign("teamCount", (new UsersBindModel)->where('pid', $user_id)->count());
        $where[] = ['dividend_uid', '=', $user_id];
        $where[] = ['status', 'in', [2, 3]];
        $DividendModel = new DividendModel();
        $dividend_amount = $DividendModel->where($where)->sum('dividend_amount');
        $dividend_bean = $DividendModel->where($where)->sum('dividend_bean');

        //zxz是否为机器人
        $is_jq = Db::name('vpay_users_vest')->where('user_id',$user_id)->find();
        $this->assign('is_jq',$is_jq);
        $this->assign("wait_money", $dividend_amount + $dividend_bean);
        $this->assign("start_date", date('Y/m/01', strtotime("-1 months")));
        $this->assign("end_date", date('Y/m/d'));

        $level = VpayUsers::where('user_id', $user_id)->value('level_id');
        $this->assign("level", $level);

        return $this->fetch('sys_admin/users/info');
    }
    /*------------------------------------------------------ */
    //-- 修改分销身份
    /*------------------------------------------------------ */
    public function editRole()
    {
        $user_id = input('user_id', 0, 'intval');
        $row = $this->Model->info($user_id);
        $DividendRoleModel = new DividendRoleModel();
        $roleList = $DividendRoleModel->getRows();
        if ($this->request->isPost()) {
            $data['role_id'] = input('role_id', 0, 'intval');
            $this->checkUpData($row, $data);
            $data['last_up_role_time'] = time();
            $res = $this->Model->upInfo($user_id, $data);
            if ($res < 1) return $this->error('操作失败,请重试.');
            $info = '后台手工操作由【' . ($row['role_id'] == 0 ? '粉丝' : $roleList[$row['role_id']]['role_name']) . '】调整为【' .($data['role_id'] < 1 ? '粉丝' : $roleList[$data['role_id']]['role_name']) . '】';
//            $this->_log($user_id, $info, 'member');
            return $this->success('修改分佣身份成功！', 'reload');
        }
        $this->assign("roleList", $roleList);
        $this->assign("row", $row);

        return response($this->fetch('sys_admin/users/edit_role'));
    }
    /*------------------------------------------------------ */
    //-- 封禁会员
    /*------------------------------------------------------ */
    public function evelBan()
    {
        $user_id = input('user_id', 0, 'intval');
        $row = $this->Model->info($user_id);
        if ($row['is_ban'] == 1) return $this->error('会员已被封禁，无须重复操作.');
        $data['is_ban'] = 1;
        $res = $this->Model->upInfo($user_id, $data);
        if ($res < 1) return $this->error();
//        $this->_log($user_id, '后台封禁会员.', 'member');
        return $this->success('禁封成功.', 'reload');
    }
    /*------------------------------------------------------ */
    //-- 解禁会员
    /*------------------------------------------------------ */
    public function reBan()
    {
        $user_id = input('user_id', 0, 'intval');
        $row = $this->Model->info($user_id);
        if ($row['is_ban'] == 0) return $this->error('会员已被解禁，无须重复操作.');
        $data['is_ban'] = 0;
        $res = $this->Model->upInfo($user_id, $data);
        if ($res < 1) return $this->error('操作失败,请重试.');
//        $this->_log($user_id, '后台解禁会员.', 'member');
        return $this->success('解禁成功.', 'reload');
    }
    /*------------------------------------------------------ */
    //-- 根据关键字查询
    /*------------------------------------------------------ */
    public function pubSearch()
    {
        $keyword = input('keyword', '', 'trim');

        if (!empty($keyword)) {
            $where = "( mobile LIKE '%" . $keyword . "%' OR user_id = '" . $keyword . "' OR nick_name LIKE '%" . $keyword . "%' OR mobile LIKE '%" . $keyword . "%')";
        }
        $_list = $this->Model->where($where)->field("user_id,mobile,nick_name,user_name")->limit(20)->select();
        foreach ($_list as $key => $row) {
            $_list[$key] = $row;
        }
        $result['list'] = $_list;
        $result['code'] = 1;
        return $this->ajaxReturn($result);
    }
    /*------------------------------------------------------ */
    //-- 下级名单
    /*------------------------------------------------------ */
    public function getChainList()
    {
        $user_id = input('user_id', 0, 'intval');
        if ($user_id < 1) {
            $result['list'] = [];
            return $this->ajaxReturn($result);
        }
        $DividendRole = (new DividendRoleModel)->getRows();
        $UsersBindModel = new UsersBindModel();
        $rows = $this->Model->field('user_id,nick_name,role_id')->where('pid', $user_id)->select();
        foreach ($rows as $key => $row) {
            $row['role_name'] = $DividendRole[$row['role_id']]['role_name'];
            $row['teamCount'] = $UsersBindModel->where('pid', $row['user_id'])->count() + 1;
            $rows[$key] = $row;
        }
        $result['list'] = $rows;
        return $this->ajaxReturn($result);
    }
    /*------------------------------------------------------ */
    //-- 上级名单
    /*------------------------------------------------------ */
    public function getSuperiorList()
    {
        $user_id = input('user_id', 0, 'intval');
        $result['code'] = 1;
        $result['list'] = $this->Model->getSuperiorList($user_id);
        return $this->ajaxReturn($result);
    }

    /*------------------------------------------------------ */
    //-- 执行统计
    /*------------------------------------------------------ */
    public function evalStat()
    {
        $reportrange = input('reportrange', '2019/01/01 - 2019/03/21', 'trim');
        $user_id = input('user_id', '29889', 'intval');

        $dtime = explode('-', $reportrange);
        $UsersBindModel = new UsersBindModel();
        $viewObj = $UsersBindModel->alias('b')->field('o.user_id,o.order_id,o.user_id,o.order_amount,o.dividend_amount,og.goods_name,og.goods_id,og.goods_name,og.goods_number,og.shop_price');
        $viewObj->join("shop_order_info o", 'b.user_id=o.user_id AND o.order_status = 1 AND o.add_time between ' . strtotime($dtime[0]) . ' and ' . (strtotime($dtime[1]) + 86399), 'left');
        $viewObj->join("shop_order_goods og", 'og.order_id=o.order_id', 'left');
         $rows = $viewObj->where('b.pid', $user_id)->select()->toArray();
        $result['buyGoods'] = [];
        $nowUser = [];
        $result['dividend_amount'] = $nowUser['dividend_amount'] = 0;
        $result['order_amount'] = $nowUser['order_amount'] = 0;
        $order_ids = $user_order_ids = [];
        $buy_ser_ids = [];
        foreach ($rows as $row) {
            if ($row['goods_id'] < 1)  continue;
            $order_ids[$row['order_id']] = 1;
            $buy_ser_ids[$row['user_id']] = 1;
            $result['buyGoods'][$row['goods_id']]['goods_name'] = $row['goods_name'];
            $result['buyGoods'][$row['goods_id']]['num'] += $row['goods_number'];
            $result['buyGoods'][$row['goods_id']]['price'] += $row['shop_price'];
            $result['dividend_amount'] += $row['dividend_amount'];
            $result['order_amount'] += $row['order_amount'];
        }
        $viewObj = $this->Model->alias('u')->field('o.user_id,o.order_id,o.user_id,o.order_amount,o.dividend_amount,og.goods_name,og.goods_id,og.goods_name,og.goods_number,og.shop_price');
        $viewObj->join("shop_order_info o", 'u.user_id=o.user_id AND o.order_status = 1 AND o.add_time between ' . strtotime($dtime[0]) . ' and ' . (strtotime($dtime[1]) + 86399), 'left');
        $viewObj->join("shop_order_goods og", 'og.order_id=o.order_id', 'left');
        $rows = $viewObj->where('u.user_id', $user_id)->select()->toArray();
        foreach ($rows as $row) {
            if ($row['goods_id'] < 1)  continue;
            $order_ids[$row['order_id']] = 1;
            $buy_ser_ids[$row['user_id']] = 1;
            $result['buyGoods'][$row['goods_id']]['goods_name'] = $row['goods_name'];
            $result['buyGoods'][$row['goods_id']]['num'] += $row['goods_number'];
            $result['buyGoods'][$row['goods_id']]['price'] += $row['shop_price'];
            $result['dividend_amount'] += $row['dividend_amount'];
            $result['order_amount'] += $row['order_amount'];

            $nowUser['buyGoods'][$row['goods_id']]['goods_name'] = $row['goods_name'];
            $nowUser['buyGoods'][$row['goods_id']]['num'] += $row['goods_number'];
            $nowUser['buyGoods'][$row['goods_id']]['price'] += $row['shop_price'];
            $user_order_ids[$row['order_id']] = 1;
            $nowUser['dividend_amount'] += $row['dividend_amount'];
            $nowUser['order_amount'] += $row['order_amount'];

        }

        $result['code'] = 1;
        $result['reportrange'] = $reportrange;
        $result['order_num'] = count($order_ids);
        $result['buy_user_num'] = count($buy_ser_ids);
        $nowUser['order_num'] = count($user_order_ids);
        $result['nowUser'] = $nowUser;
        return $this->ajaxReturn($result);
    }
     /*------------------------------------------------------ */
    //-- 修改所属上级
    /*------------------------------------------------------ */
    public function editSuperior()
    {

        $user_id = input('user_id', 0, 'intval');
        $userInfo = $this->Model->info($user_id);
        if ($this->request->isPost()) {
            $mkey = 'evaleditSuperior';
            $cache = Cache::get($mkey);
            if (empty($cache) == false){
                return $this->error('当前正在有人操作调整，请稍后再操作.');
            }
            Cache::get($mkey,true,60);
            $select_user_id = input('select_user', 0, 'intval');
            if ($select_user_id < 1){
                return $this->error('请选择需要修改的上级.');
            }
            if ($select_user_id == $userInfo['pid']){
                return $this->error('当前选择与当前会员上级一致，请核实.');
            }
            $where[] = ['pid','=',$user_id];
            $where[] = ['user_id','=',$select_user_id];
            $count = (new UsersBindModel)->where($where)->count();
            if ($count > 0){
                return $this->error('不能选择自己的下级作为上级.');
            }
            Db::startTrans();//启动事务

            $res = $this->Model->upInfo($user_id,['pid'=>$select_user_id]);

            if ($res < 1){
                Db::rollback();
                return $this->error('修改会员所属上级失败.');
            }

            $res = $this->Model->regUserBind($user_id,$select_user_id,true);//重新绑定当前用户的关系链
            if ($res == false){
                Db::rollback();
                return $this->error('绑定当前会员系链失败.');
            }
            $this->evaleditSuperior($user_id);//执行重新生成所有下属的关系链
            Db::commit();//事务，提交
            Cache::rm($mkey);
//            $this->_log($user_id, '调整会员所属上级，原所属上级ID：'.$userInfo['pid'], 'member');
            return $this->success('修改所属上级成功！', 'reload');
        }
        if ($userInfo['pid'] > 0){
            $userInfo['puser'] = $this->Model->info($userInfo['pid']);
        }
        $this->assign("row", $userInfo);
        return response($this->fetch('sys_admin/users/edit_superior'));
    }

    /*------------------------------------------------------ */
    //-- 循执行调用更新关系链
    /*------------------------------------------------------ */
    protected function evaleditSuperior($pid,$level = 1){
        $bind_max_level = config('config.bind_max_level');
        if ($level > $bind_max_level){//循环到指定绑定层级跳出
            return true;
        }
        $users = $this->Model->where('pid',$pid)->field('user_id,pid')->select();
        if (empty($users)){
            return true;//没有找到下级不执行
        }
        foreach ($users as $user){
            $this->Model->regUserBind($user['user_id'],$pid,true);
            $this->evaleditSuperior($user['user_id'],$level + 1);
        }
        return true;
    }

    //设置用户为机器人
    public function set_user_vest(){
        $user_id  = input('post.user_id');
        $data['user_id'] = $user_id;
        $res = Db::name('vpay_users_vest')
            ->insert($data);
        return $res;
    }

    //取消用户为机器人
    public function quxiao_user_vest(){
        $user_id  = input('post.user_id');
        $res = Db::name('vpay_users_vest')
            ->where('user_id',$user_id)
            ->delete();
        return $res;
    }

    //添加待匹配记录
    public function matching_info(){
        if ($this->request->isPost()) {
            $data = input('post.');
            $vest =  Db::name('vpay_users_vest')
                ->where('user_id',$data['user_id'])
                ->find();
            if(empty($vest)){
                $this->error('该用户不是机器人用户,无法添加待匹配用户');
            }
            $map['user_id'] = $data['user_id'];
            $map['money'] = $data['money'];
            $map['add_time'] = time();
            $map['priority_level'] = 1;
            $res = Db::name('vpay_matching')
                ->insert($map);
            if($res){
                return $this->success('添加成功.');
            }else{
                return $this->error('操作失败.');
            }
        }else{
            $user_id = input('user_id', 0, 'intval');
            $userInfo = $this->Model->info($user_id);

            $this->assign('userInfo',$userInfo);
            return response($this->fetch('matching_info'));
        }
    }
    /**
     * 用户列表导出
     * 根据筛选条件：注册时间、手机号、昵称、id
     */
    public function export_user()
    {
        $data = [];
        $data['levelId']=input('level_id', 0, 'intval');
        $data['time_type'] = input("time_type") ;
        $data['user_status'] = input('user_status', 0, 'intval') ;
        $data['keyword'] = input('keyword', 0, 'intval') ;

        $reportrange = input('reportrange');
        if (empty($reportrange) == false) {
            $dtime = explode('-', $reportrange);
        }
        switch ($data['time_type']) {
            case 'reg_time':
                $where[] = ' u.reg_time between ' . strtotime($dtime[0]) . ' AND ' . (strtotime($dtime[1]) + 86399);
                break;
            case 'login_time':
                $where[] = ' u.login_time between ' . strtotime($dtime[0]) . ' AND ' . (strtotime($dtime[1]) + 86399);
                break;
            case 'buy_time':
                $where[] = ' u.last_buy_time between ' . strtotime($dtime[0]) . ' AND ' . (strtotime($dtime[1]) + 86399);
                break;
            default:
                break;
        }

        if ($data['levelId'] > 0) {
            $where[] = 'vu.level_id =' . $data['levelId'];
        }
        if (empty($data['keyword']) == false) {
            if (is_numeric($where['keyword'])) {
                $where[] = "  u.user_id = '" . ($data['keyword']) . "' or mobile like '" . $data['keyword'] . "%'";
            } else {
                $where[] = " ( u.user_name like '" . $data['keyword'] . "%' or u.nick_name like '" . $data['keyword'] . "%' )";
            }
        }

        $sort_by = input("sort_by", 'DESC', 'trim');
        $order_by = 'u.user_id';
        $viewObj = $this->Model
            ->alias('u')
            ->join("users_account uc", 'u.user_id=uc.user_id', 'left')
            ->join("vpay_users vu", 'u.user_id=vu.user_id', 'left')
            ->join('vpay_users_vest vc','u.user_id=vc.user_id','left')
            ->where(join(' AND ', $where))
            ->field('u.*,uc.balance_money,uc.use_integral,uc.total_integral,vu.coupon,vu.pass_card,vu.asset_bundle,vu.activation_code,vu.cumulative_coupon,vu.cumulative_asset_bundle,vu.status,vu.level_id,vc.id as vid')
            ->order($order_by . ' ' . $sort_by);

        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">会员ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">注册手机</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100">推荐人</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">等级身份</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">用户名</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">sea券</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">sea通行证</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">资产包</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">激活码</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">累积sea券</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">累积资产包</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">真实姓名</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">用户状态</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">真实身份</td>';


        $strTable .= '</tr>';
        $count = Db::name('users')->count();
        $p = ceil($count / 5000);
        for ($i = 0; $i < $p; $i++) {
            $start = $i * 5000;
            $end = ($i + 1) * 5000;
            $userList =  $viewObj = $this->Model
                ->alias('u')
                ->join("users_account uc", 'u.user_id=uc.user_id', 'left')
                ->join("vpay_users vu", 'u.user_id=vu.user_id', 'left')
                ->join('vpay_users_vest vc','u.user_id=vc.user_id','left')
                ->where(join(' AND ', $where))
                ->field('u.*,uc.balance_money,uc.use_integral,uc.total_integral,vu.coupon,vu.pass_card,vu.asset_bundle,vu.activation_code,vu.cumulative_coupon,vu.cumulative_asset_bundle,vu.status,vu.level_id,vc.id as vid')
                ->order($order_by . ' ' . $sort_by)
                ->limit($start,$end)
                ->select()
                ->toArray();

            if (is_array($userList)) {
                foreach ($userList as $k => $val) {
                    if($val['level_id']==0) $level_name='粉丝';
                    if($val['level_id']==1) $level_name='会员';
                    if($val['level_id']==2) $level_name='总统舱';
                    if($val['level_id']==3) $level_name='头等舱';
                    if($val['level_id']==4) $level_name='太空舱';

                    if($val['status']==0) $status='未激活';
                    if($val['status']==1) $status='正常';
                    if($val['status']==2) $status='冻结';
                    if($val['status']==3) $status='黑名单';

                    if($val['vid']){
                        $userv_vest = "机器人";
                    }else{
                        $userv_vest = "正常用户";
                    }
                    if($val['pid']){
                        $pid =  Db::name('users')->where('pid', $val['user_id'])->value("user_name");
                    }else{
                        $pid = "没有上级";
                    }

                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['user_id'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['mobile'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $pid. ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $level_name. '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val["member_name"] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val["coupon"]. '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['pass_card'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['asset_bundle'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['activation_code'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['cumulative_coupon'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['cumulative_asset_bundle'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['user_name'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $status. ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $userv_vest . ' </td>';
                    $strTable .= '</tr>';
                    $level_name = '';
                    $status = '';
                }
                unset($userList);
            }
        }
        $strTable .= '</table>';
        downloadExcel($strTable, 'users_' . $i);
        exit();
    }
}
{
}
