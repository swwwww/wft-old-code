<?php

namespace Application\Model;


class PlayUserCollectTable extends BaseTable
{
    //获取商家名称
    public function getAdminCollectShopName($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array()) {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_shop', 'play_shop.shop_id = play_user_collect.link_id', array('shop_name'), 'left');
            $select->where($where);
            if ($pagesum) {
                $select ->limit($pagesum)->offset($start);
            }
            $select->order($order);
            $select->Group(array('play_user_collect.uid'));

        });
        return $data;
    }

}