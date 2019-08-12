<?php

namespace app\vpay\controller;

use think\Db;
use think\Model;
use think\Controller;
use app\ClientbaseController;
use app\vpay\model\MatchingModel;

class Matching extends controller
{
    protected $userInfo = [];
//*------------------------------------------------------ */
    //-- 初始化
    /*------------------------------------------------------ */
    public function initialize()
    {
        parent::initialize();
        $this->Model = new MatchingModel();
        $userInfo =Db::name('vpay_users')
            ->where('id',2)
            ->find();
        $this->userInfo = $userInfo;
    }


    //添加匹配机会
    public function add_matching(){
        $userInfo = $this->userInfo;
        //两种匹配情况
        $rand = $this->Model->get_Ab();
        //获取当前用户的优先级
        $priority_level = $this->Model->get_priority_level($userInfo['id']);
        //添加匹配记录
        foreach ($rand as $k=>$v){
            $a = $this->Model->add_matching($userInfo['id'],null,$v,time(),null,null,null,null,$priority_level);
        }

    }

    //匹配列表
    public function matching_list(){
        $userInfo = $this->userInfo;
        $status = input('status')?input('status'):1;
        $a = $this->Model->get_matching_list($status,$userInfo['id']);

    }
    public function  ceshi(){
        $model  = new MatchingModel();
        $a = $model->get_matching_order();
    }
}