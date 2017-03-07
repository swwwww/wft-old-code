<?php

namespace Application\Model;


class PlayFocusMapTable extends BaseTable
{
    public function  getAdminFocusMapList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_activity', 'play_activity.id = play_focus_map.link_id', array('ac_name'), 'left');
            $select->join('play_coupons', 'play_coupons.coupon_id = play_focus_map.link_id', array('coupon_name'), 'left');
            $select->join('play_shop', 'play_shop.shop_id = play_focus_map.link_id', array('shop_name'), 'left');
            $select->where($where);
            if ($pagesum) {
                $select ->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }

}