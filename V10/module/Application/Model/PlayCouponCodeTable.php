<?php
namespace Application\Model;

class PlayCouponCodeTable extends BaseTable
{
    //店铺订单表
    public function  getOrderList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_order_info', 'play_order_info.order_sn = play_coupon_code.order_sn', array('coupon_name', 'coupon_unit_price', 'shop_name', 'coupon_name', 'username', 'account_type', 'account'), 'left');
            $select->where($where);
            if ($pagesum) {
                $select ->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }

}