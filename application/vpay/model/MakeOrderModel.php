<?php

namespace app\vpay\model;

use app\BaseModel;
use think\Db;
use phpqrcode\phpqrcode;
use think\Loader;
use app\mainadmin\model\SettingsModel;
/**
 * 匹配打款模型
 */

class MakeorderModel extends BaseModel
{
    protected $table = 'vpay_make_order';
    public  $pk = 'id';

    //添加打款订单
    public function  add_order($m_id){
        $map['m_id'] = $m_id;
        $map['create_time'] = time();
        $map['order_status'] = 1;
        $res = $this->insert($map);
        return  $res;
    }

    //寻找等待匹配的记录
    public function search_mid($money,$uid){
        //$where['user_id'] = array('neq',2);
        $where['status'] = 1;
        $where['money'] = $money;

            $m_info = Db::name('vpay_matching')
               ->where($where)
                ->where('user_id','neq',$uid)
               ->order('priority_level desc, add_time asc')
               ->find();
        return $m_info;
    }

    //匹配后更新收款订单
    public function save_matching($id,$uid){
        $map['uid'] = $uid;
        $map['status'] = 2;
        $map['matching_time'] = time();
        $res = Db::name('vpay_matching')
            ->where('id',$id)
            ->update($map);
        return $res;
    }

    //确认是否完成当天任务
    public function save_complete($id,$uid){
        $order = Db::name('vpay_make_order')->where('id',$id)->find();
        $time_section = time_section($order['create_time']);
        $start_time = $time_section['start'];
        $end_time = $time_section['end'];

        $make_order = Db::name('vpay_make_order')
                ->alias('a')
                ->join('vpay_matching b','a.m_id = b.id')
                ->where('b.uid',$uid)
                ->where('a.create_time', 'between', [$start_time, $end_time])
                ->where('a.order_status',3)
                ->where('b.status',4)
                ->count();
        return $make_order;
    }

    //查看今天在排单
    public function confirm_order($uid,$user_id,$order_status,$bstatus){
        $time_section = time_section(time());
        $start_time = $time_section['start'];
        $end_time = $time_section['end'];
        if($uid){
            $where['b.uid'] = $uid;
        }
        if($user_id){
            $where['b.user_id'] = $user_id;
        }
        if($order_status){
            $where['a.order_status'] = $order_status;
        }
        if($bstatus){
            $where['b.status'] = $bstatus;
        }

        $orders =  Db::name('vpay_make_order')
                ->field('a.*,b.status,b.uid,b.user_id,b.money,b.add_time,b.complete_time,b.examine_time,b.frozen_time')
                ->alias('a')
                ->join('vpay_matching b','a.m_id = b.id')
                ->where('a.create_time', 'between', [$start_time, $end_time])
                ->where($where)
                ->select();
        return $orders;
    }


    //更新完成任务的次数
    public function complete_task($user_id){
        $userInfo = Db::name('vpay_users')
                ->where('user_id',$user_id)
                ->find();
        $save['task_num'] = $userInfo['task_num'] + 1;
        $res =  Db::name('vpay_users')
            ->where('user_id',$user_id)
            ->update($save);
        return $res;
    }

    //更新打款订单状态
    public function save_makeorder_status($id,$status){
        $map['order_status'] = $status;

        $res =  Db::name('vpay_make_order')
            ->where('id',$id)
            ->update($map);
        return $res;
    }

    //更新收款订单状态
    public function save_matching_status($id,$status){
        $map['status'] = $status;
        if($status==5){
            $map['examine_time'] = time();
        }
        if($status==4){
            $map['complete_time'] = time();
        }
        if($status==3){
            $map['frozen_time'] = time();
        }
        if($status==2){
            $map['matching_time'] = time();
        }
        $res =  Db::name('vpay_matching')
            ->where('id',$id)
            ->update($map);
        return $res;
    }

    //更新用户账户状态
    public function save_user_status($id,$status){
        $user = Db::name('vpay_users')->where('user_id',$id)->find();
        $map['status'] = $status;
        if($status==2){
            $map['rozen_num'] = $user['rozen_num']+1;
            $map['frozen_time'] = time();
            //账户冻结次数超过三次后,自动进入黑名单
            $setModel = new SettingsModel();
            $sets = $setModel->getRows();
            if($map['rozen_num']>$sets['frozen_num']){
                $map['status'] = 3;

                $u_map['is_ban'] = 1;
                $res2 =  Db::name('users')
                    ->where('user_id',$id)
                    ->update($u_map);
            }
        }

        $res =  Db::name('vpay_users')
            ->where('user_id',$id)
            ->update($map);
        return $res;
    }

    //打款冻结后重新给收款账户匹配订单
    public function  new_make_order($id){
        $matching = Db::name('vpay_matching')
                ->where('id',$id)
                ->find();

        $data['user_id'] = $matching['user_id'];
        $data['money'] = $matching['money'];
        $data['status'] = 1;
        $data['add_time'] = $matching['add_time'];
        if($matching['beat_time']){
            $data['priority_level'] = 3;
        }else{
            $data['priority_level'] = $matching['priority_level'];
        }


        $res = Db::name('vpay_matching')
            ->insert($data);
        return $res;
    }

    //获取订单信息
    public function get_order_info($uid,$user_id,$status,$page){
        if($uid){
            $where['uid'] = $uid;
        }
        if($user_id){
            $where['user_id'] = $user_id;
        }
        if($status){
            $where['status'] = $status;
//            $where['status'] = array('exp','IN(1,2)');
        }

        $pagesize = 10;
        $start = ($page-1)*$pagesize;

        $order =  Db::name('vpay_make_order')
            ->field('a.*,b.status,b.uid,b.user_id,b.money,b.add_time,b.matching_time,b.complete_time,b.examine_time,b.frozen_time,b.priority_level,b.origin_id')
            ->alias('a')
            ->join('vpay_matching b','a.m_id = b.id')
            ->where($where)
//            ->where('status','in','1,2')
            ->order('create_time desc')
            ->limit($start,$pagesize)
            ->select();

        foreach ($order as $k=>$v){
            $time_section = time_section(time());
            $start_time = $time_section['start'];
            $end_time = $time_section['end'];
            if($v['create_time']>$start_time&&$v['create_time']<$end_time){
                $order[$k]['is_day'] = 1;
            }
        }
        return  $order;
    }

    //获取今日打款收款单个信息
    public function get_order_information($id,$m_id){
        $time_section = time_section(time());
        $start_time = $time_section['start'];
        $end_time = $time_section['end'];

        if($id){
            $where['a.id'] = $id;
        }
        if($m_id){
            $where['b.id'] = $m_id;
        }
        $order = Db::name('vpay_make_order')
            ->field('a.*,b.status,b.uid,b.user_id,b.money,b.add_time,b.complete_time,b.examine_time,b.frozen_time')
            ->alias('a')
            ->join('vpay_matching b','a.m_id = b.id')
//            ->where('a.create_time', 'between', [$start_time, $end_time])
            ->where($where)
            ->find();

        return  $order;
    }

    //获取打款收款单个信息
    public function get_order_information2($id,$m_id){
        if($id){
            $where['a.id'] = $id;
        }
        if($m_id){
            $where[] = 'b.id='.$m_id;
        }

        $order = Db::name('vpay_matching')
            ->alias('b')
            ->field('a.*,b.id as b_id,b.status,b.uid,b.user_id,b.money,b.add_time,b.complete_time,b.examine_time,b.frozen_time,b.priority_level,b.origin_id')
            ->leftjoin('vpay_make_order a',' b.id = a.m_id')
            ->where(join(' AND ', $where))
            ->find();

        return  $order;
    }
    //获取支付信息
    public function get_user_payinfo($user_id){
        $pay_info = Db::name('vpay_put_away')
                ->where('uid',$user_id)
                ->find();
        return $pay_info;
    }

    //获取个人资料
    public function user_personal($user_id){
        $user_personal =  Db::name('users')
            ->where('user_id',$user_id)
            ->find();
        return $user_personal;
    }


    //上传付款凭证
    public function upload_img($img_name){
        $file = request()->file($img_name);
        if($file){
            $info = $file->move(UPLOAD_PATH."/$img_name");
            if($info){
                return $info->getSaveName();
            }else{
                return $info->getError();die;
            }
        }
    }


    //获取设置好的排单金额
    public function get_line_up($id){
        if($id){
            $sets = Db::name('vpay_line_set')
                ->where('id',$id)
                ->find();
        }else{
            $sets = Db::name('vpay_line_set')
                ->select();
        }

        return  $sets;
    }

    //完成任务后,添加匹配收款
    public function add_matching($user_id = 2,$uid,$money,$add_time,$matching_time,$complete_time,$examine_time,$frozen_time,$priority_level,$origin_id){
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
        $data['origin_id'] = $origin_id;
        $res = Db::name('vpay_matching')
            ->insert($data);
        //$res = $this->insert($data);
        return $res;

    }

    //两种不同模式的匹配
    public function get_Ab(){
        $rand  = mt_rand(1,10);

        $setModel = new SettingsModel();
        $sets = $setModel->getRows();
        if($rand%2==0){
            $map = [$sets['programme_b_one'],$sets['programme_b_two'],$sets['programme_b_three']];
        }else{
            $map = [$sets['programme_a_one'],$sets['programme_a_two']];
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

    //查看订单是否超过了15点
    public function is_overtime($id){

        //15点时间戳
        $order = $this->get_order_information($id,'');
        $dividing_line =  strtotime(date('Y-m-d 15:0:0',time()));

        $setModel = new SettingsModel();
        $sets = $setModel->getRows();
        $time = $sets['make_money_hour']*60*60;

        if($order['create_time']<$dividing_line){
            if((time()-$dividing_line)>=$time){
                return 1; //超过2小时
            }else{
                return 2;//未超过2小时
            }
        }else{
            if((time()-$order['create_time'])>=$time){
                return 1;//超过2小时
            }else{
                return 2;//未超过2小时
            }
        }
    }

    //添加到发送短信
    public function add_send_message($matching_id,$user_id,$mobile,$status){
        $map['matching_id'] = $matching_id;
        $map['user_id'] =  $user_id;
        $map['mobile'] = $mobile ;
        $map['status'] = $status ;
        $map['add_time'] =  time();

        $res = Db::name('vpay_send_message')
            ->insert($map);

        return $res;
    }

    //发送短信
    public function send_message($mobile){
        $url = 'http://v.juhe.cn/sms/send?mobile='.$mobile.'&tpl_id=166037&tpl_value=%23code%23%3D654654&key=396f619fdfb6dc99111947d56b83b017';
        $res = http_curl($url);
        return $res;
    }


    //获取所有的机器人,并随机返回一个机器人
    public function search_vest_user($user_id){
        $vest_user_ids = Db::name('vpay_users_vest')
            ->where('user_id','neq',$user_id)
                ->select();
        return $vest_user_ids[array_rand($vest_user_ids,1)]['user_id'];
    }

    //获取sea券排单的次数
    public function search_line_num($user_id){
        $num = Db::name('vpay_record_coupon')
            ->where('user_id',$user_id)
            ->where('surface_name','vpay_make_order')
            ->count();
        return $num;
    }

    //获取今天消耗sea券排单的记录
    public function search_record($user_id){
        $time_section = time_section(time());
        $start_time = $time_section['start'];
        $end_time = $time_section['end'];

        $sea_num = Db::name('vpay_record_coupon')
            ->where('user_id',$user_id)
            ->where('surface_name','vpay_make_order')
            ->where('time', 'between', [$start_time, $end_time])
            ->select();
        return  $sea_num;
    }
}
