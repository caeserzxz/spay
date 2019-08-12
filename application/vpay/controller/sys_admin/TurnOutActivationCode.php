<?php
namespace app\vpay\controller\sys_admin;

use app\AdminController;
use app\vpay\model\VpayTurnOutActivationCode;
use think\Db;
class TurnOutActivationCode extends AdminController
{

    public function initialize()
    {
        parent::initialize();
        $this->Model = new VpayTurnOutActivationCode();
    }

    public function index(){
        $this->getList(true);
        return $this->fetch('sys_admin/TurnOutActivationCode/index');
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

        $where[] = ['type', 'eq', 1]; // 转赠
		$viewObj = $this->Model->where($where)->order('id desc');

        $data = $this->getPageList($this->Model, $viewObj);
        $this->assign("data", $data);

        if ($runData == false) {
            $data['content'] = $this->fetch('sys_admin/TurnOutActivationCode/list');
            return $this->success('', '', $data);
        }
        return true;
    }



    public function indexActivationCode(){
        $this->getListActivationCode(true);
        return $this->fetch('sys_admin/TurnOutActivationCode/indexActivationCode');
    }

    /*------------------------------------------------------ */
    //-- 获取列表
    //-- $runData boolean 是否返回模板
    /*------------------------------------------------------ */
    public function getListActivationCode($runData = false, $is_ban = -1){
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

        $where[] = ['type', 'eq', 2]; // 激活下级
		$viewObj = $this->Model->where($where)->order('id desc');

        $data = $this->getPageList($this->Model, $viewObj);
        $this->assign("data", $data);

        if ($runData == false) {
            $data['content'] = $this->fetch('sys_admin/TurnOutActivationCode/listActivationCode');
            return $this->success('', '', $data);
        }
        return true;
    }

}