<?php

namespace app\vpay\controller\sys_admin;

use app\AdminController;
use app\vpay\model\MakeOrderModel;
use app\vpay\model\MatchingModel;
use app\mainadmin\model\SettingsModel;
use think\Db;
class Index extends AdminController
{
    public  $Model;
    public function initialize()
    {
        parent::initialize();
    }

    public function index(){
        $this->getList(true);
        return $this->fetch('sys_admin/index/index');
    }

    /*------------------------------------------------------ */
    //-- 获取列表
    //-- $runData boolean 是否返回模板
    /*------------------------------------------------------ */
    public function getList($runData = false, $is_ban = -1){
        $this->search['status_type'] =  input('status_type');
        $this->search['time_type'] =  input('time_type');
        $this->search['keyword'] = input("keyword");
        $reportrange = input('reportrange');
        if (empty($reportrange) == false) {
            $dtime = explode('-', $reportrange);
        }
        switch ($this->search['time_type']) {
            case 'add_time':
                $where[] = ' b.add_time between ' . strtotime($dtime[0]) . ' AND ' . (strtotime($dtime[1]) + 86399);
                break;
            case 'matching_time':
                $where[] = ' b.matching_time between ' . strtotime($dtime[0]) . ' AND ' . (strtotime($dtime[1]) + 86399);
                break;
            default:
                break;
        }
        if (empty($this->search['keyword']) == false) {
            if (is_numeric($this->search['keyword'])) {
                $where[] = "  b.user_id = '" . ($this->search['keyword']) . "' or b.uid like '" . $this->search['keyword'] . "%'";
            }
        }
        if($this->search['status_type']){
            $where[] =  'b.status = '.$this->search['status_type'];
        }

        $sort_by = input("sort_by", 'DESC', 'trim');
        //$order_by = 'u.user_id';
        $model = new MatchingModel();
        $viewObj =  $model
            ->alias('b')
            ->field('a.*,b.id as b_id,b.status,b.uid,b.user_id,b.money,b.add_time,b.matching_time,b.complete_time,b.examine_time,b.frozen_time,b.priority_level,b.origin_id')
            ->leftjoin('vpay_make_order a','a.m_id = b.id')
            ->where(join(' AND ', $where))
            ->order('create_time desc');

        $data = $this->getPageList($model, $viewObj);

        //$data['order_by'] = $order_by;
        $data['sort_by'] = $sort_by;
        $this->assign("data", $data);
        if ($runData == false) {
            $data['content'] = $this->fetch('sys_admin/index/list');
            return $this->success('', '', $data);
        }
        return true;
    }

    //匹配详情
    public function order_info(){
        $model = new MakeorderModel();
        if(request()->isPost()){
            $type = input('type');
            $id = input('id');
            $order = $model->get_order_information2('',$id);
            Db::startTrans();
            if($type==1){//成功
                //更新打款信息
                $res1=$model->save_makeorder_status($order['id'],3);
                //更新收款信息
                $res2=$model->save_matching_status($order['b_id'],4);
                //查看打款者有没有完成当天任务,
                $userInfo =Db::name('vpay_users')
                    ->where('user_id',$order['uid'])
                    ->find();
                $is_over = $model->save_complete($order['id'],$userInfo['user_id']);
                if($is_over==2){//已完成当天任务
                    $setModel = new SettingsModel();
                    $sets = $setModel->getRows();

                    if($userInfo['task_num']>=$sets['matching_day']){
                        //添加匹配机会
                        $this->add_matching($order['id'],$userInfo['user_id']);
                    }
                    //更新用户完成每日任务次数
                    $model->complete_task($userInfo['user_id']);
                }

                if($res1&&$res2){
                    Db::commit();
                    $return['msg'] = '操作成功';
                    $return['status'] = 1;
                    return  $return;
                }else{
                    Db::rollback();
                    $return['msg'] = '操作失败';
                    $return['status'] = -1;
                    return  $return;
                }
            }else{//失败
                //更新打款信息
                $res3=$model->save_makeorder_status($order['id'],4);
                //更新收款信息
                $res4=$model->save_matching_status($order['b_id'],5);
                //给收款者重新匹配
                $res5=$model->new_make_order($order['b_id']);
                //冻结收款者
                $res6=$model->save_user_status($order['uid'],2);
                if($res3&&$res4&&$res5&&$res6){
                    Db::commit();
                    $return['msg'] = '操作成功';
                    $return['status'] = 1;
                    return  $return;
                }else{
                    Db::rollback();
                    $return['msg'] = '操作失败';
                    $return['status'] = -1;
                    return  $return;
                }
            }

        }else{
            $id = input('id');
            $order = $model->get_order_information2('',$id);

            $this->assign('order',$order);
            return $this->fetch('sys_admin/index/order_info');
        }

    }

    //添加匹配机会
    public function add_matching($origin_id,$user_id){
        $model = new MakeOrderModel();
        //两种匹配情况
        $rand = $model->get_Ab();
        //获取当前用户的优先级
        $priority_level = $model->get_priority_level($user_id);
        //添加匹配记录
        foreach ($rand as $k=>$v){
            $model->add_matching($user_id,null,$v,time(),null,null,null,null,$priority_level,$origin_id);
        }

    }

    /**
     * 用户列表导出
     * 根据筛选条件：注册时间、手机号、昵称、id
     */
    public function export_user()
    {
        $data = [];
        $data['time_type'] = input("time_type") ;
        $data['keyword'] = input('keyword', 0, 'intval') ;
        $data['status_type'] = input('status_type');
        $reportrange = input('reportrange');

        if (empty($reportrange) == false) {
            $dtime = explode('-', $reportrange);
        }
        switch ($data['time_type']) {
            case 'add_time':
                $where[] = ' b.add_time between ' . strtotime($dtime[0]) . ' AND ' . (strtotime($dtime[1]) + 86399);
                break;
            case 'matching_time':
                $where[] = ' b.matching_time between ' . strtotime($dtime[0]) . ' AND ' . (strtotime($dtime[1]) + 86399);
                break;
            default:
                break;
        }
        if (empty($data['keyword']) == false) {
            if (is_numeric($data['keyword'])) {
                $where[] = "  b.user_id = '" . ($data['keyword']) . "' or b.uid like '" . $data['keyword'] . "%'";
            }
        }
        if($data['status_type']){
            $where[] =  'b.status = '.$data['status_type'];
        }

        $model = new MatchingModel();

        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">订单id</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">排单时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100">打款人id</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">收款id</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">排单金额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">打款状态</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">收款状态</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">创建匹配时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">匹配时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">完成时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">失败时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">冻结时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">优先级</td>';

        $strTable .= '</tr>';
        $count = Db::name('users')->count();
        $p = ceil($count / 5000);
        for ($i = 0; $i < $p; $i++) {
            $start = $i * 5000;
            $end = ($i + 1) * 5000;

            $orderList =   $viewObj =  $model
                ->alias('b')
                ->field('a.*,b.id as b_id,b.status,b.uid,b.user_id,b.money,b.add_time,matching_time,b.complete_time,b.examine_time,b.frozen_time,b.priority_level,b.origin_id')
                ->leftjoin('vpay_make_order a','a.m_id = b.id')
                ->where(join(' AND ', $where))
                ->order('create_time desc')
                ->select()
                ->toArray();

            if (is_array($orderList)) {
                foreach ($orderList as $k => $val) {
                    if($val['order_status']==1) $order_status='匹配中';
                    if($val['order_status']==2) $order_status='冻结';
                    if($val['order_status']==3) $order_status='完成';
                    if($val['order_status']==4) $order_status='失败';

                    if($val['status']==1) $status='待匹配';
                    if($val['status']==2) $status='匹配中';
                    if($val['status']==3) $status='冻结中';
                    if($val['status']==4) $status='完成';
                    if($val['status']==5) $status='失败';

                    if($val['priority_level']==1) $priority_level='马甲';
                    if($val['priority_level']==2) $priority_level='正常';
                    if($val['priority_level']==3) $priority_level='解冻';
                    if($val['create_time']) $create_time =date("Y-m-d H:i:s",$val['create_time']);
                    if($val['add_time']) $add_time =date("Y-m-d H:i:s",$val['add_time']);
                    if($val['matching_time']) $matching_time =date("Y-m-d H:i:s",$val['matching_time']);
                    if($val['complete_time']) $complete_time =date("Y-m-d H:i:s",$val['complete_time']);
                    if($val['examine_time']) $examine_time =date("Y-m-d H:i:s",$val['examine_time']);
                    if($val['frozen_time']) $frozen_time =date("Y-m-d H:i:s",$val['frozen_time']);

                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['b_id'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $create_time. '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val["uid"]. ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val["user_id"]. '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val["money"] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $order_status. '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $status . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $add_time. '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $matching_time . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $complete_time . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $examine_time. ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $frozen_time . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $priority_level. ' </td>';
                    $strTable .= '</tr>';
                    $level_name = '';
                    $status = '';
                }
                unset($orderList);
            }
        }
        $strTable .= '</table>';
        downloadExcel($strTable, 'vpay_order_' . $i);
        exit();
    }
}