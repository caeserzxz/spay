<?php
namespace app\vpay\controller\sys_admin;

use app\AdminController;
use app\vpay\model\VpayTurnOutAssetsBundle;
use think\Db;
class TurnOutAssetsBundle extends AdminController
{

    public function initialize()
    {
        parent::initialize();
        $this->Model = new VpayTurnOutAssetsBundle();
    }

    public function index(){
        $this->getList(true);
        return $this->fetch('sys_admin/TurnOutAssetsBundle/index');
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

        $where[] = ['status', 'eq', 0];
		$viewObj = $this->Model->where($where)->order('id desc');

        $data = $this->getPageList($this->Model, $viewObj);
        $this->assign("data", $data);

        if ($runData == false) {
            $data['content'] = $this->fetch('sys_admin/TurnOutAssetsBundle/list');
            return $this->success('', '', $data);
        }
        return true;
    }


    public function indexArrivalAccount(){
        $this->getListArrivalAccount(true);
        return $this->fetch('sys_admin/TurnOutAssetsBundle/indexArrivalAccount');
    }

    /*------------------------------------------------------ */
    //-- 获取列表
    //-- $runData boolean 是否返回模板
    /*------------------------------------------------------ */
    public function getListArrivalAccount($runData = false, $is_ban = -1){
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

		$viewObj = $this->Model->where($where)->where('status', 'in', [1, 2])->order('id desc');

        $data = $this->getPageList($this->Model, $viewObj);
        $this->assign("data", $data);

        if ($runData == false) {
            $data['content'] = $this->fetch('sys_admin/TurnOutAssetsBundle/listArrivalAccount');
            return $this->success('', '', $data);
        }
        return true;
    }
}