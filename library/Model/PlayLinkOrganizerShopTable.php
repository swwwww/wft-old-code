<?php

namespace library\Model;


class PlayLinkOrganizerShopTable extends BaseTable
{
    //获取商家名称
    public function  getOrganizerList($start = 0, $pageSum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pageSum, $columns, $where, $order) {
            $select->join('play_organizer', 'play_organizer.id = play_linker_organizer_shop.organizer_id', array('organizer_name' => 'name'));
            $select->where($where);
            if ($pageSum) {
                $select->limit($pageSum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }

}