<?php

namespace app\member\controller\sys_admin;

use app\AdminController;
use app\vpay\model\VpayUsers;
use app\vpay\model\Users;
use think\Db;
class UserTeam extends AdminController
{

    public function initialize()
    {
        parent::initialize();
        $this->Model = new VpayUsers();
    }

    public function index(){
        $this->getList(true);
        return $this->fetch('sys_admin/UserTeam/index');
    }

    /*------------------------------------------------------ */
    //-- 获取列表
    //-- $runData boolean 是否返回模板
    /*------------------------------------------------------ */
    public function getList($runData = false, $is_ban = -1){
        // $reportrange = input('reportrange');
        // if (empty($reportrange) == false) {
        //     $dtime = explode('-', $reportrange);
        // }
        // switch (request()->param('time_type')) {
        //     case 'time':
        //         $where[] = ['time', 'between', strtotime($dtime[0]) . ',' . (strtotime($dtime[1]) + 86399)];
        //         break;
        // }

        $where = [];
        $userId = request()->param('user_id');
        if($userId) $where[] = ['u.user_id', 'eq', $userId];

		$viewObj = $this->Model
                    ->alias('v')
                    ->where($where)
                    ->join('users u', 'u.user_id=v.user_id', 'inner')
                    ->field('v.user_id,v.level_id,u.pid,u.user_name')
                    ->order('u.user_id desc');

        $data = $this->getPageList($this->Model, $viewObj);


        $arr = $data['list'];
        foreach ($data['list'] as $k => $v) {
            // $info = $this->Model->where('chain', 'like', '%' . $v['user_id'] . '%')->field('user_id,level_id')->select();
            $info = $this->Model
                        ->alias('v')
                        ->join('users u', 'u.user_id=v.user_id', 'inner')
                        ->where('v.chain', 'like', '%' . $v['user_id'] . '%')
                        ->field('v.user_id,v.level_id,u.pid,u.user_name')
                        ->order('u.user_id desc')
                        ->select()
                        ->toArray();

            $arr[$k]['child'] = [];
            if($info){
                $arr[$k]['child'] = subtree($info, $v['user_id'], 1, true);
            }
        }

        $data['list'] = $arr;
        $this->assign("data", $data);

        if ($runData == false) {
            $data['content'] = $this->fetch('sys_admin/UserTeam/list');
            return $this->success('', '', $data);
        }
        return true;
    }


}