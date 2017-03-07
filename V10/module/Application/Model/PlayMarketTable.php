<?php

namespace Application\Model;


class PlayMarketTable extends BaseTable
{
    //获取商家列表
    public function  getMarketList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array('market_id' => 'asc'))
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
}