<?php

namespace app\vpay\model;
use app\BaseModel;
use think\Db;

/**
 * 匹配收款模型
 */

class MatchingModel extends BaseModel
{
    protected $table = 'vpay_matching';
    public  $pk = 'id';

    //完成任务后,添加匹配收款
    public function add_matching($user_id = 2,$uid,$money,$add_time,$matching_time,$complete_time,$examine_time,$frozen_time,$priority_level){
//        $rand = $this->get_Ab();
//        foreach ($rand as $k=>$v){
            $data['uid'] = $uid;
            $data['user_id'] = $user_id;
            $data['money'] = $money;
            $data['status'] = 1;
            $data['add_time'] = $add_time;
            $data['matching_time'] = $matching_time;
            $data['priority_level'] = $priority_level;
            $data['complete_time'] =$complete_time;
            $data['examine_time'] =$examine_time ;
            $data['frozen_time'] = $frozen_time;
            $res = $this->insert($data);
            return $res;
//        }
    }

    //两种不同模式的匹配
    public function get_Ab(){
        $rand  = mt_rand(1,10);
        if($rand%2==0){
            $map = [800,800,800];
        }else{
            $map = [1200,1200];
        }
        return $map;
    }

    //获取当前用户的优先级
    public function  get_priority_level($user_id){
        $user_vest = Db::name('vpay_users_vest')
                ->where('user_id',$user_id)
                ->find();
        if($user_vest){
            return 1;
        }else{
            return 2;
        }
    }

    //获取匹配列表
    public  function get_matching_list($status,$user_id){
        $where['status'] = $status;
        if($user_id){
            $where['user_id'] = $user_id;
        }
        $where['user_id'] = $user_id;
        $list = Db::name('vpay_matching')
            ->where($where)
            ->select();
        return $list;
    }

    //获取打款订单信息;
    public function  get_matching_order($id){
        $matching_order = Db::name('vpay_matching')
                ->where('id',$id)
                ->find();
        return $matching_order;
    }

    //冻结订单
    public function  frozen_order($id){

    }


}
