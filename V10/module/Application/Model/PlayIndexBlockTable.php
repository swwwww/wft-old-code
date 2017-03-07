<?php

namespace Application\Model;


class PlayIndexBlockTable extends BaseTable
{
    //获取资讯列表
    public function  getAdminIndexBlockList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
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

    public function getApiBlockList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array()) {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_activity', 'play_activity.id = play_index_block.link_id', array('ac_cover', 'ac_name', 'introduce', 'like_number', 'count_number', 'a_reticket' => 'reticket', 'l_price' => 'link_price'), 'left');
            $select->join('play_shop', 'play_shop.shop_id = play_index_block.link_id', array('cover', 'shop_name', 'editor_word', 'reference_price', 'reticket', 'link_price'), 'left');
            $select->join('play_organizer_game', 'play_organizer_game.id = play_index_block.link_id', array('game_cover' => 'cover', 'game_name' => 'title',), 'left');
            $select->where($where);
            if ($pagesum) {
                $select ->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }

    public function getApiBlockLister($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array()) {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_activity', 'play_activity.id = play_index_block.link_id', array('ac_cover', 'ac_name', 'introduce', 'like_number', 'count_number', 'a_reticket' => 'reticket', 'l_price' => 'link_price'), 'left');
            $select->join('play_shop', 'play_shop.shop_id = play_index_block.link_id', array('cover', 'shop_name', 'editor_word', 'reference_price', 'reticket', 'link_price', 'addr_x', 'addr_y'), 'left');
            $select->join('play_organizer_game', 'play_organizer_game.id = play_index_block.link_id', array('game_cover' => 'cover', 'game_name' => 'title', 'game_editor_word' => 'editor_talk', 'game_price' => 'low_price','ticket_num', 'buy_num', 'is_together', 'g_buy', 'down_time'), 'left');
            $select->where($where);
            if ($pagesum) {
                $select ->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }
}