<?php

namespace app\member\controller\sys_admin;
use app\AdminController;
use app\vpay\model\VpayReleaseProportion;


/**
 * 释放比例
 */
class ReleaseProportion extends AdminController
{
	//*------------------------------------------------------ */
	//-- 初始化
	/*------------------------------------------------------ */
   public function initialize()
   {
   		parent::initialize();
		$this->Model = new VpayReleaseProportion();
    }
	/*------------------------------------------------------ */
    //--首页
    /*------------------------------------------------------ */
    public function index()
    {
		$this->getList(true);
        return $this->fetch('sys_admin/ReleaseProportion/index');
    }

	/*------------------------------------------------------ */
    //-- 获取列表
	//-- $runData boolean 是否返回模板
    /*------------------------------------------------------ */
    public function getList($runData = false) {

    	$order_by = 'grade ASC';

        $ruleId = request()->param('rule_id');
        $where['rule_id'] = $ruleId;

		$viewObj = $this->Model->where($where)->order('generation asc');
        $data = $this->getPageList($this->Model, $viewObj);
        // dump($data);
        // exit();
		$this->assign("data", $data);
        $this->assign("rule_id", $ruleId);

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

            $ruleId = $data['rule_id'];
            // unset($data['rule_id']);
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
                    return $this->success('添加成功.', url('index', ['rule_id'=>$ruleId]));
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
                    return $this->success('修改成功.', url('index', ['rule_id'=>$ruleId]));
                }
            }
            return $this->error('操作失败.');
        }
        $id = input($pk, 0, 'intval');
        $ruleId = request()->param('rule_id');

        $row = ($id == 0) ? $this->Model->getField() : $this->Model->find($id);
        if ($id > 0 && empty($row) == false) {
            $row = $row->toArray();
        }
        if (method_exists($this, 'asInfo')) {
            $row = $this->asInfo($row);
        }
        $this->assign("row", $row);
        $this->assign("rule_id", $ruleId);
        $ishtml = input('ishtml', 0, 'intval');
        if ($this->request->isAjax() && $ishtml == 0) {
            $result['code'] = 1;
            $result['data'] = $this->fetch('info');
            return $this->ajaxReturn($result);
        }
        return response($this->fetch('sys_admin/ReleaseProportion/info'));

    }


	/*------------------------------------------------------ */
	//-- 添加前处理
	/*------------------------------------------------------ */
    public function beforeAdd($data) {

        if ($data['proportion'] < 0 || $data['proportion'] > 100 ) return $this->error('操作失败:释放比例范围在0-100！');
        $where = [
            'generation' => $data['generation'],
            'rule_id' => $data['rule_id'],
        ];
        // dump($data);
        // exit();
        if(!empty($data['id'])){
            $count = $this->Model->where($where)->where('id', 'neq', $data['id'])->count();
        }
        else{
            $count = $this->Model->where($where)->count();
        }
        if($count > 0){
            return $this->error("操作失败:第（{$data['generation']}）代已存在");
        }
		return $data;
	}
	/*------------------------------------------------------ */
	//-- 添加后处理
	/*------------------------------------------------------ */
    public function afterAdd($data) {
		$this->_Log($data['id'],'添加释放比例:'.$data['id']);
	}
	/*------------------------------------------------------ */
	//-- 修改前处理
	/*------------------------------------------------------ */
    public function beforeEdit($data){

        $data = $this->beforeAdd($data);

		// $where[] = ['id','<>',$data['id']];
		// $count = $this->Model->where($where)->count('level_id');
		// if ($count > 0) return $this->error('操作失败:已存在相同的等级名称，不允许重复添加！');
		// unset($where);



		//判断积分等级是否冲突
		// $where[] = "level_id <> '".$data['level_id']."'";
		// $whereOr[] = $data['min']." BETWEEN  min AND max ";
		// if ($data['max'] > 0) $whereOr[] = $data['max']." BETWEEN  min AND max ";
		// $where[] = '('.join(' OR ',$whereOr).')';
		// $count = $this->Model->where(join(' AND ',$where))->count('level_id');
		// if ($count > 0) $this->error('操作失败:积分范围发生冲突！');
		// unset($where);
		// if ($data['max'] == 0){
		// 	$where[] = ['max','=',0];
		// 	$where[] = ['level_id','<>',$data['level_id']];
		// 	$count = $this->Model->where($where)->count('level_id');
		// 	if ($count > 0) $this->error('操作失败:已存在上限为0的等级，不能重复添加！');
		// }


		return $data;
	}
	/*------------------------------------------------------ */
	//-- 修改后处理
	/*------------------------------------------------------ */
    public function afterEdit($data) {
		$this->_Log($data['role_id'],'修改会员等级:'.$data['level_name']);
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

