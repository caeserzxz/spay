<?php

namespace app\vpay\controller\sys_admin;

use app\AdminController;
use app\vpay\model\VpayTurnOutAdminOperation;
use think\Db;
class TurnOutAdminOperation extends AdminController
{

    public function initialize()
    {
        parent::initialize();
        $this->Model = new VpayTurnOutAdminOperation();
    }

    public function index(){
        $this->getList(true);
        return $this->fetch('sys_admin/TurnOutAdminOperation/index');
    }

    /*------------------------------------------------------ */
    //-- 获取列表
    //-- $runData boolean 是否返回模板
    /*------------------------------------------------------ */
    public function getList($runData = false, $is_ban = -1){
        $reportrange = input('reportrange');
        if (empty($reportrange) == false) {
            $dtime = explode('-', $reportrange);
        }
        switch (request()->param('time_type')) {
            case 'time':
                $where[] = ['time', 'between', strtotime($dtime[0]) . ',' . (strtotime($dtime[1]) + 86399)];
                break;
        }

        $userId = request()->param('out_user_id');
        if($userId) $where[] = ['out_user_id', 'eq', $userId];

		$viewObj = $this->Model->where($where)->order('id desc');

        $data = $this->getPageList($this->Model, $viewObj);
        $this->assign("data", $data);

        if ($runData == false) {
            $data['content'] = $this->fetch('sys_admin/TurnOutAdminOperation/list');
            return $this->success('', '', $data);
        }
        return true;
    }


}