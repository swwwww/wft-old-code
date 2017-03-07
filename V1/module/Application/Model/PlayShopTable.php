<?php

namespace Application\Model;


class PlayShopTable extends BaseTable
{
    //获取商家列表
    public function  getShopList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array('shop_id' => 'desc'))
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->where($where);
            if ($pagesum) {
                $select ->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }

    //获取商家列表
    public function getShopListAdmin($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array('shop_id' => 'desc')) {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_market', 'play_market.market_id = play_shop.shop_mid', array('market_name'), 'left');
            $select->where($where);
            if ($pagesum) {
                $select ->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }

    //关联 admin
    public function  getAdminShopList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_admin', 'play_admin.id = play_shop.editor_id', array('admin_name', 'image'), 'left');
            $select->where($where);
            if ($pagesum) {
                $select ->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }
}