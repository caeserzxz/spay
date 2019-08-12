<?php

namespace app\member\controller\sys_admin;
use app\AdminController;
use app\vpay\model\VpayReleaseRule;
use app\vpay\model\VpayReleaseProportion;


/**
 * 释放规则
 */
class ReleaseRule extends AdminController
{
	//*------------------------------------------------------ */
	//-- 初始化
	/*------------------------------------------------------ */
   public function initialize()
   {
   		parent::initialize();
		$this->Model = new VpayReleaseRule();
    }
	/*------------------------------------------------------ */
    //--首页
    /*------------------------------------------------------ */
    public function index()
    {
		$this->getList(true);
        return $this->fetch('sys_admin/release_rule/index');
    }

	/*------------------------------------------------------ */
    //-- 获取列表
	//-- $runData boolean 是否返回模板
    /*------------------------------------------------------ */
    public function getList($runData = false) {

    	$order_by = 'grade ASC';

		$viewObj = $this->Model->order('min asc');
        $data = $this->getPageList($this->Model, $viewObj);
        foreach ($data['list'] as $k => $v) {
        	$data['list'][$k]['count'] = VpayReleaseProportion::where('rule_id', $v['id'])->count();
        }
        // dump($data);
        // exit();
		$this->assign("data", $data);
		if ($runData == false){
			$data['content']= $this->fetch('list');
			unset($data['list']);
			return $this->success('','',$data);
		}
        return true;
    }

    /**
     * 用户等级info修改，重写父类的公用info方法
     */
    public function info()
    {
        $pk = $this->Model->pk;
        if ($this->request->isPost()) {
            if (false === $data = $_POST) {
                $this->error($this->Model->getError());
            }
            if (empty($data[$pk])) {
                if (method_exists($this, 'beforeAdd')) {
                    $data = $this->beforeAdd($data);
                }
                unset($data[$pk]);
                $data['time'] = time();
                $res = $this->Model->allowField(true)->save($data);
                if ($res > 0) {
                    $data[$pk] = $this->Model->$pk;
                    if (method_exists($this->Model, 'cleanMemcache')) $this->Model->cleanMemcache($res);
                    if (method_exists($this, 'afterAdd')) {
                        $result = $this->afterAdd($data);
                        if (is_array($result)) return $this->ajaxReturn($result);
                    }
                    return $this->success('添加成功.', url('index'));
                }
            } else {
                if (method_exists($this, 'beforeEdit')) {
                    $data = $this->beforeEdit($data);
                }
                $data['update_time'] = time();
                $res = $this->Model->allowField(true)->save($data, $data[$pk]);
                if ($res > 0) {
                    if (method_exists($this->Model, 'cleanMemcache')) $this->Model->cleanMemcache($data[$pk]);
                    if (method_exists($this, 'afterEdit')) {
                        $result = $this->afterEdit($data);
                        if (is_array($result)) return $this->ajaxReturn($result);
                    }
                    return $this->success('修改成功.', url('index'));
                }
            }
            return $this->error('操作失败.');
        }
        $id = input($pk, 0, 'intval');
        $row = ($id == 0) ? $this->Model->getField() : $this->Model->find($id);
        if ($id > 0 && empty($row) == false) {
            $row = $row->toArray();
        }
        if (method_exists($this, 'asInfo')) {
            $row = $this->asInfo($row);
        }
        $this->assign("row", $row);
        $ishtml = input('ishtml', 0, 'intval');
        if ($this->request->isAjax() && $ishtml == 0) {
            $result['code'] = 1;
            $result['data'] = $this->fetch('info');
            return $this->ajaxReturn($result);
        }
        return response($this->fetch('sys_admin/release_rule/info'));
    }


	/*------------------------------------------------------ */
	//-- 添加前处理
	/*------------------------------------------------------ */
    public function beforeAdd($data) {

        if(empty($data['min']) || $data['min'] <= 0){
            return $this->error('操作失败:最小值必须大于0');
        }

        if($data['min'] >= $data['max']){
            return $this->error('操作失败:最小值不能大于等于最大值');
        }

        if(!empty($data['id'])){
            $arr = $this->Model->field('min,max')->where('id', 'neq', $data['id'])->select();
        }
        else{
            $arr = $this->Model->field('min,max')->select();
        }
        foreach ($arr as $v) {
            if($data['min'] >= $v['min'] && $data['min'] <= $v['max']){
                return $this->error('操作失败:最小值不能与其它规则有交集');
            }
            if($data['max'] <= $v['max'] &&  $data['max'] >= $v['min']){
                return $this->error('操作失败:最大值不能与其它规则有交集');
            }
            if($data['max'] > $v['max'] && $data['min'] < $v['min']){
                return $this->error('操作失败:最大值和最小值不能和包含其它规则的最大、最小值');
            }
        }
		return $data;
	}
	/*------------------------------------------------------ */
	//-- 添加后处理
	/*------------------------------------------------------ */
    public function afterAdd($data) {
		$this->_Log($data['id'],'添加释放规则:'.$data['id']);
	}
	/*------------------------------------------------------ */
	//-- 修改前处理
	/*------------------------------------------------------ */
    public function beforeEdit($data){

        $data = $this->beforeAdd($data);
		return $data;
	}
	/*------------------------------------------------------ */
	//-- 修改后处理
	/*------------------------------------------------------ */
    public function afterEdit($data) {
		$this->_Log($data['role_id'],'修改释放规则:'.$data['level_name']);
	}
	/*------------------------------------------------------ */
	//-- 删除等级
	/*------------------------------------------------------ */
	public function delete(){
		$id = input('id',0,'intval');
		if ($id < 1)  return $this->error('传参失败！');
		$res = $this->Model->where('id',$id)->delete();
		if ($res < 1) return $this->error('未知错误，删除失败！');
		$this->Model->cleanMemcache();
		return $this->success('删除成功！',url('index'));
	}
}

