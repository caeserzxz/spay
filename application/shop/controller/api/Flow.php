<?php

namespace app\shop\controller\api;

use app\ApiController;
use think\Db;

use app\shop\model\CartModel;
use app\mainadmin\model\PaymentModel;
use app\member\model\UserAddressModel;
use app\member\model\AccountLogModel;
use app\shop\model\BonusModel;
use app\shop\model\OrderModel;
use app\shop\model\GoodsModel;
use app\shop\model\BonusListModel;
use app\member\model\UsersModel;
/*------------------------------------------------------ */
//-- 购物相关API
/*------------------------------------------------------ */

class Flow extends ApiController
{
    public $is_integral = 0;
    /*------------------------------------------------------ */
    //-- 优先执行
    /*------------------------------------------------------ */
    public function initialize()
    {
        parent::initialize();
        $this->Model = new CartModel();
         $this->Model->is_integral = $this->is_integral;
    }
    /*------------------------------------------------------ */
    //-- 添加购物车
    /*------------------------------------------------------ */
    public function addCart()
    {
        $goods_id = input('goods_id', 0, 'intval');
        $num = input('number', 1, 'intval');
		$type = input('type','','trim');
		if ($type == 'onbuy'){
			$this->checkLogin();//验证登陆
		}
        if ($num < 1) $num = 1;
        $specifications = input('specifications', '', 'trim');
        $sku_id = input('sku_id', 0, 'intval');
        if ($specifications == 'undefined') $specifications = '';
        if ($goods_id < 1) return $this->error('传值失败，请重新尝试提交！');
        $rec_id = $this->Model->addToCart($goods_id, $num, $specifications,$sku_id,$type);
        if (is_numeric($rec_id) == false) {
            return $this->error($rec_id);
        }
        if ($type != 'onbuy') {
            $return['cartInfo'] = $this->Model->getCartInfo();
            $return['msg'] = '添加购物车成功.';
        }
        $return['code'] = 1;
		$return['rec_id'] = $rec_id;
        return $this->ajaxReturn($return);
    }
    /*------------------------------------------------------ */
    //-- 获取购物车列表
    /*------------------------------------------------------ */
    public function getList()
    {
        $is_sel = input('is_sel', 0, 'intval');
		$recids = input('recids', '', 'trim');
        $return['cartInfo'] = $this->Model->getCartList($is_sel,false,$recids);
        $return['code'] = 1;
        return $this->ajaxReturn($return);
    }
    /*------------------------------------------------------ */
    //-- 获取购物车信息
    /*------------------------------------------------------ */
    public function getCartInfo()
    {
        $return['cartInfo'] = $this->Model->getCartInfo();
        $return['code'] = 1;
        return $this->ajaxReturn($return);
    }
    /*------------------------------------------------------ */
    //-- 修改商品订购数量
    /*------------------------------------------------------ */
    function editNum()
    {
        $rec_id = input('rec_id', 0, 'intval');
        $is_sel = input('is_sel', 0, 'intval');
		$recids = input('recids', '', 'trim');
        if ($rec_id < 1) return $this->error('传值失败，请重新尝试提交.');
        $num = input('num', 1, 'intval');
        if ($num < 1) return $this->error('订购数量不能小于1.');
        $where['rec_id'] = $rec_id;
        $res = $this->Model->updataGoods($rec_id, $num);
        if ($res != 1){
			if ($res === 0) return $this->error('');
			return $this->error($res);
		}
        $address_id = input('address_id', 0, 'intval');
        if ($address_id > 0) {
            $return['shippingFee'] = $this->evalShippingFee($address_id, true,$recids);
        }
        $return['cartInfo'] = $this->Model->getCartList($is_sel,true,$recids);
        $return['code'] = 1;
        return $this->ajaxReturn($return);
    }
    /*------------------------------------------------------ */
    //-- 删除购物车的商品
    /*------------------------------------------------------ */
    function delGoods()
    {
        $rec_id = input('rec_id', 0, 'intval');
        if ($rec_id < 1) return $this->error('传值失败，请重新尝试提交！');
        $this->Model->delGoods($rec_id);
        $return['cartInfo'] = $this->Model->getCartList();
        $return['code'] = 1;
        return $this->ajaxReturn($return);
    }
    /*------------------------------------------------------ */
    //-- 清除购物车无效的商品
    /*------------------------------------------------------ */
    function clearInvalid()
    {
        $this->Model->clearInvalid();
        $return['cartInfo'] = $this->Model->getCartList();
        $return['code'] = 1;
        return $this->ajaxReturn($return);
    }
    /*------------------------------------------------------ */
    //-- 设置商品是否选中
    /*------------------------------------------------------ */
    function setSel()
    {
        $rec_id = input('rec_id');
        if ($rec_id < 1 && $rec_id != 'all') return $this->error('传值失败，请重新尝试提交！');
        $is_select = input('is_select', 0, 'intval');
        $res = $this->Model->updateCart($rec_id, ['is_select' => $is_select]);
        $return['cartInfo'] = $this->Model->getCartList();
        $return['code'] = 1;
        return $this->ajaxReturn($return);
    }
    /*------------------------------------------------------ */
    //-- 删除选中的商品
    /*------------------------------------------------------ */
    function delSelGoods()
    {
        $this->Model->delGoods();
        $return['cartInfo'] = $this->Model->getCartList();
        $return['code'] = 1;
        return $this->ajaxReturn($return);
    }

    /*------------------------------------------------------ */
    //-- 计算运费
    /*------------------------------------------------------ */
    function evalShippingFee($address_id = 0, $is_return = false,$recids = '')
    {
        if ($address_id < 0) {
            $address_id = input('address_id', '0', 'intval');
			$recids = input('recids', '', 'trim');
        }
		$return['code'] = 1;
        if ($address_id < 1) {
            $return['shipping_fee'] = sprintf("%.2f", 0);
			if ($is_return == true) return $return;
            return $this->ajaxReturn($return);
        }
		$UserAddressModel = new UserAddressModel();
        $addressList = $UserAddressModel->getRows();
        $address = $addressList[$address_id];
		$cartList = $this->Model->getCartList(1, true,$recids);
        $shippingFee = $this->Model->evalShippingFee($address,$cartList);
        $shippingFee = reset($shippingFee);//现在只返回默认快递
        $shippingFee['shipping_fee'] = sprintf("%.2f", $shippingFee['shipping_fee'] * 1);
        if ($is_return == true) return $shippingFee;
        $return['shippingFee'] = $shippingFee;
        return $this->ajaxReturn($return);
    }

    /*------------------------------------------------------ */
    //-- 执行下单
    /*------------------------------------------------------ */
    function addOrder()
    {
        $this->checkLogin();//验证登录

        $address_id = input('address_id', 0, 'intval');
        if ($address_id < 0) return $this->error('请设置收货地址后，再操作.');
        $UserAddressModel = new UserAddressModel();
        $addressList = $UserAddressModel->getRows();
        $address = $addressList[$address_id];
        if (empty($address)) return $this->error('相关收货地址不存在.');
        $used_bonus_id = input('used_bonus_id', 0, 'intval');

		$recids = input('recids', '', 'trim');
        $cartList = $this->Model->getCartList(1, true,$recids,false,false);

        if ($cartList['buyGoodsNum'] < 1) return $this->error('请选择订购商品.');

        $GoodsModel = new GoodsModel();
        $supplyer_ids = [];//供应商ID

        $inArr['give_integral'] = 0;
        $inArr['settle_price'] = 0;
        $use_integral = 0;
        // 验证购物车中的商品能否下单
        foreach ($cartList['goodsList'] as $grow) {
            $goods = $GoodsModel->info($grow['goods_id']);
            // 判断是商品能否购买或修改
            $res = $this->Model->checkGoodsOrder($goods, $grow['goods_number'], $grow['sku_val']);
            if ($res !== true) return $this->error($res);
            $price = $GoodsModel->evalPrice($goods, $grow['goods_number'], $grow['sku_val']);//计算需显示的商品价格
            if ($this->is_integral == 0 && $grow['sale_price'] != $price['min_price']){
                return $this->error('商品'.$grow['goods_name'].$grow['sku_name'].'价格发生变化，购物车价格：￥'.$grow['sale_price'].'，当前价格：￥'.$price['min_price']);
            }
            $supplyer_ids[$grow['supplyer_id']] = 1;
            if ($grow['supplyer_id'] > 0 ){//供应商商品计算供货总价
                $inArr['settle_price'] += $grow['settle_price'] * $grow['goods_number'];
            }
            if ($grow['give_integral'] > 0 ){//赠送积分总计
                $inArr['give_integral'] += $grow['give_integral'] * $grow['goods_number'];
            }
            if ($grow['use_integral'] > 0 ){//扣减积分总计,组合购买时调用
                $use_integral += $grow['use_integral'] * $grow['goods_number'];
            }
        }

        if (empty($supplyer_ids) == false){
            $supplyer_ids = array_keys($supplyer_ids);
            if (count($supplyer_ids) > 1){
                $inArr['is_split'] = 1;//供应商两个以上，需要进行拆单
            }else{
                $inArr['supplyer_id'] = reset($supplyer_ids);//获取唯一的供应商id
            }
        }

        $inArr['use_bonus'] = 0;
        if ($used_bonus_id > 0) {//优惠券验证
            $BonusModel = new BonusModel();
            $bonus = $BonusModel->binfo($used_bonus_id);
            if ($bonus['user_id'] != $this->userInfo['user_id']) {
                return $this->error('优惠券出错，请核实.');
            }
            if ($bonus['info']['stauts'] != 1) {
                return $this->error('优惠券无法使用：' . $bonus['info']['stauts_info']);
            }
            if ($cartList['totalGoodsPrice'] < $bonus['info']['min_amount']) {
                return $this->error('选择的优惠券满￥' . $bonus['info']['min_amount'] . '才可以使用，请核实.');
            }
            $inArr['use_bonus'] = $bonus['info']['type_money'];
        }
        $time = time();
        $inArr['order_status'] = 0;
        $inArr['pay_status'] = 0;
        $inArr['shipping_status'] = 0;
        $_log = '生成订单';
        //运费处理
        $shippingFee = $this->Model->evalShippingFee($address, $cartList);
        $shippingFee = reset($shippingFee);//现在只返回默认快递
        $inArr['shipping_fee'] = $shippingFee['shipping_fee'] * 1;
        $inArr['order_amount'] = $cartList['orderTotal'] + $inArr['shipping_fee'] - $inArr['use_bonus'];

        if ($inArr['order_amount'] > 0){
            $pay_id = input('pay_id', 0, 'intval');
            if ($pay_id < 0) return $this->error('请选择支付方式.');
            $PaymentModel = new PaymentModel();
            $paymentList = $PaymentModel->getRows(true);
            $payment = $paymentList[$pay_id];
        }elseif($this->is_integral == 1){//积分支付
            $payment['pay_code'] = 'integral';
            $payment['is_pay'] = 1;
            $payment['pay_name'] = '积分兑换';

        }
        if (empty($payment)) return $this->error('相关支付方式不存在或已停用.');

        if ($payment['pay_code'] == 'balance') {//如果使用余额，判断用户余额是否足够
            if ($inArr['order_amount'] > $this->userInfo['account']['balance_money']) {
                return $this->error('余额不足，请使用其它支付方式.');
            }
        }
        $inArr['order_type'] = $this->is_integral;//订单类型，0普通订单,1积分订单

        if($use_integral > 0){
            if ($use_integral > $this->userInfo['account']['use_integral']) {
                return $this->error('积分不足无法兑换，你的积分余额为：'.$this->userInfo['account']['use_integral']);
            }
            $inArr['use_integral'] = $use_integral;

        }

        $inArr['buyer_message'] = input('buy_msg', '', 'trim');
        $inArr['consignee'] = $address['consignee'];
        $inArr['address'] = $address['address'];
        $inArr['merger_name'] = $address['merger_name'];
        $inArr['province'] = $address['province'];
        $inArr['city'] = $address['city'];
        $inArr['district'] = $address['district'];
        $inArr['mobile'] = $address['mobile'];

        $inArr['add_time'] = $time;
        $inArr['user_id'] = $this->userInfo['user_id'];
        $inArr['dividend_role_id'] = $this->userInfo['role_id'];
        $inArr['pay_id'] = $payment['pay_id'];
		$inArr['pay_code'] = $payment['pay_code'];
        $inArr['pay_name'] = $payment['pay_name'];
        $inArr['discount'] = $cartList['totalDiscount'];
        $inArr['goods_amount'] = $cartList['totalGoodsPrice'];
        $inArr['buy_goods_sn'] = join(',', array_keys($cartList['allGoodsSn']));
        $inArr['ipadderss'] = request()->ip();
        $inArr['is_pay'] = $payment['is_pay'];//是否需要支付,1线上支付，0，不需要支付，
        $inArr['is_success'] = 1;//普通订单默认有效，如果拼团默认为0，须拼团成功才会为1
        //执行商品库存和销量处理，后台设置下单减库存或余额支付时执行
        $shop_reduce_stock = settings('shop_reduce_stock');
        $inArr['is_stock'] = $shop_reduce_stock;
        Db::startTrans();//启动事务
        $OrderModel = new OrderModel();
        $inArr['order_sn'] = $OrderModel->getOrderSn();
        $res = $OrderModel->save($inArr);
        if ($res < 1) {
            Db::rollback();// 回滚事务
            return $this->error('未知原因，订单写入失败.');
        }
        $order_id = $OrderModel->order_id;

        $inArr['order_id'] = $order_id;
        $res = $OrderModel->_log($inArr,$_log);
        if ($res < 1) {
            Db::rollback();// 回滚事务
            return $this->error('未知原因，订单日志写入失败.');
        }
        //使用积分，下单即扣除
        if ($use_integral > 0){
            $changedata['use_integral'] = $use_integral * -1;
            $changedata['change_desc'] = '购物抵扣积分';
            $changedata['change_type'] = 3;
            $changedata['by_id'] = $order_id;
            $res = (new AccountLogModel)->change($changedata, $this->userInfo['user_id'], false);
            if ($res !== true) {
                Db::rollback();// 回滚事务
                return $this->error('扣减积分失败失败.');
            }
        }

        //执行扣库存
        if ($inArr['is_stock'] == 1) {
            if ($inArr['order_type'] == 1) {//积分订单
                $res = (new \app\integral\model\IntegralGoodsListModel)->evalGoodsStore($cartList['goodsList']);
            } else {
                $res = $GoodsModel->evalGoodsStore($cartList['goodsList']);
            }
            if ($res != true) {
                Db::rollback();//回滚
                return '扣库存失败.';
            }
        }
        //end
        //处理优惠券
        if ($used_bonus_id > 0) {
            $upArr = array();
            $upArr['user_id'] = $this->userInfo['user_id'];
            $upArr['used_time'] = $time;
            $upArr['order_id'] = $order_id;
            $upArr['order_sn'] = $inArr['order_sn'];
            $BonusListModel = new BonusListModel();
            $res = $BonusListModel->where('bonus_id', $used_bonus_id)->update($upArr);
            if ($res < 1) {
                Db::rollback();// 回滚事务
                return $this->error('未知错误，修改优惠券失败.');
            }
        }
        //end
        $this->addOrderGoods($order_id,$recids);//写入商品
        Db::commit();// 提交事务
        $return['order_id'] = $order_id;
        $return['code'] = 1;
        return $this->ajaxReturn($return);
    }
    /*------------------------------------------------------ */
    //-- 写入订单商品
    //--- 这里可能有bug,如果用户同时执行多次，商品有可能发生错误
    /*------------------------------------------------------ */
    public function addOrderGoods($order_id,$recids)
    {
        $add_time = time();
        $sql = "INSERT INTO shop_order_goods (" .
            "order_id,brand_id,cid,supplyer_id,goods_id,sku_id,sku_val,sku_name,goods_name,pic,goods_sn,goods_number,market_price,shop_price,sale_price,settle_price,goods_weight,discount,add_time,user_id,give_integral,use_integral,is_dividend,buy_again_discount)" .
            " SELECT '$order_id',brand_id,cid,supplyer_id,goods_id,sku_id,sku_val,sku_name,goods_name,pic,goods_sn,goods_number,market_price,shop_price,sale_price,settle_price,goods_weight,discount,'$add_time',user_id,give_integral,use_integral,is_dividend,buy_again_discount" .
            " FROM  shop_cart  WHERE ";
        $sql .= " user_id = '" . $this->userInfo['user_id'] . "' AND is_select = 1 ";
        $sql .= " AND is_integral =  " . $this->is_integral;
		if (empty($recids) == false){
			 $sql .= " AND rec_id IN (".$recids.") ";
		}
        $sql .= " order by rec_id asc";
        $res = Db::execute($sql);
        if ($res < 1) {
            Db::rollback();// 回滚事务
            return $this->error('未知原因，订单商品写入失败.');
        }
        $where[] = ['user_id', '=', $this->userInfo['user_id']];
        $where[] = ['is_select', '=', 1];
        $where[] = ['is_integral', '=', $this->is_integral];
		if (empty($recids) == false){
			 $where[] = ['rec_id', 'in', explode(',',$recids)];
		}
        $this->Model->where($where)->delete();// 清理购物车的商品
        $this->Model->cleanMemcache();
        return $res;
    }
}