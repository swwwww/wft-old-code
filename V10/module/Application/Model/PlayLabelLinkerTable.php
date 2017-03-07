<?php

namespace Application\Model;


class PlayLabelLinkerTable extends BaseTable
{
    public function  getApiShopList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_shop', 'play_shop.shop_id = play_label_linker.object_id');
            $select->join('play_region', 'play_region.rid = play_shop.busniess_circle', array('shop_circle' => 'name'));
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }

    public function getApiTagList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array()) {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_label', 'play_label.id = play_label_linker.lid');
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }
}