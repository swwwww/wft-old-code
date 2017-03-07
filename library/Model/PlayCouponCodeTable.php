<?php
namespace library\Model;

class PlayCouponCodeTable extends BaseTable
{
    //店铺订单表
    public function  getOrderList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_order_info', 'play_order_info.order_sn = play_coupon_code.order_sn', array('coupon_name', 'coupon_unit_price', 'shop_name', 'coupon_name', 'username', 'account_type', 'account', 'pay_status','buy_phone','dateline','shop_id'), 'left');
            $select->join('play_order_info_game', 'play_order_info_game.order_sn = play_order_info.order_sn', array('price_id','address'),'left');
            $select->join('play_game_price','play_game_price.id = play_order_info_game.price_id', array('name','price'),'left');
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }

    //店铺订单表 wanjiang
    public function  getShopOrderList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_order_info', 'play_order_info.order_sn = play_coupon_code.order_sn', array('coupon_name', 'coupon_unit_price', 'shop_name', 'coupon_name', 'username', 'account_type', 'account', 'pay_status','buy_phone','dateline'), 'left');
            $select->join('play_order_info_game', 'play_order_info_game.order_sn = play_order_info.order_sn', array(),'left');
            $select->join('play_game_info','play_game_info.id = play_order_info_game.game_info_id', array('shop_name', 'price_name'),'left');
            $select->join('play_code_used', 'play_code_used.good_info_id = play_order_info.bid', array(), 'left');
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }


    //退费列表
    public function  getOrderBackList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_order_info', 'play_order_info.order_sn = play_coupon_code.order_sn', array('coupon_name', 'coupon_unit_price', 'shop_name', 'coupon_name', 'username', 'account_type', 'account','order_sn','coupon_id'), 'left');
            $select->join('play_order_action', 'play_order_action.order_id=play_coupon_code.order_sn', array('action_id'));
            $select->where($where);
            $select->group(array('play_order_info.order_sn'));


            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }

    public function getAuditOrder($offset = 0, $row = 20, $columns = array(), $where = array(), $order = array(), $like = array()){
        $resultSet = $this->tableGateway->select(function ($select) use ($columns, $where, $like, $order, $offset, $row) {
            if (!empty($columns)) {
                $select->columns($columns);
            }
            if (!empty($like) and $like[key($like)]) {
                $select->where->like('play_order_info.' . key($like), "%{$like[key($like)]}%");
            }
            if ($row) {
                $select->limit($row)->offset($offset);
            }
            $select->join('play_order_info', 'play_order_info.order_sn=play_coupon_code.order_sn',array('dateline','order_city','user_id','real_pay'=>'account_money','buy_number','account_type','account','shop_name','shop_id','coupon_name','pay_status','back_number'));
            $select->join('play_order_info_game', 'play_order_info_game.order_sn=play_coupon_code.order_sn');
            $select->join('play_game_info', 'play_game_info.id=play_order_info_game.game_info_id', array('account_money'));
            $select->where($where)->order($order);
        });

        return $resultSet;
    }


    //验证码验证流水  秦源
     public function getCodeLog($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
{
    $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
        $select->join('play_order_info', 'play_order_info.order_sn = play_coupon_code.order_sn', array('coupon_name', 'coupon_unit_price','buy_phone','dateline'), 'left');
        $select->join('play_order_info_game', 'play_order_info_game.order_sn = play_order_info.order_sn', array('type_name'),'left');
        $select->join('play_code_used', 'play_code_used.good_info_id = play_order_info.bid', array(), 'left');
        $select->join('play_game_info', 'play_game_info.id = play_order_info.bid', array('account_money'), 'left');
        $select->where($where);
        if ($pagesum) {
            $select->limit($pagesum)->offset($start);
        }
        $select->order($order);
    });
    return $data;
}

}