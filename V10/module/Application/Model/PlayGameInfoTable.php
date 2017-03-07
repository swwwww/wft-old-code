<?php
namespace Application\Model;

use Zend\Db\Sql\Select;

class PlayGameInfoTable extends BaseTable {

    //活动关联的店铺
    public function getApiGameShopList($start = 0, $pageSum = 0, $columns = array(), $where = array(), $order = array()) {
        $data = $this->tableGateway->select(function (Select $select) use ($start, $pageSum, $columns, $where, $order) {
            //$select->join('play_game_time', 'play_game_time.id = play_game_info.tid', array());
            $select->join('play_shop', 'play_shop.shop_id = play_game_info.shop_id', array('shop_address', 'addr_x', 'addr_y'));
            $select->join('play_region', 'play_region.rid = play_shop.busniess_circle', array('circle' => 'name'));
            $select->where($where);
            if ($pageSum) {
                $select->limit($pageSum)->offset($start);
            }
            $select->order($order);
            $select->Group('play_shop.shop_id');
        });
        return $data;
    }

    //活动可以下订单的列表
    public function getApiGameInfoList($start = 0, $pageSum = 0, $columns = array(), $where = array(), $order = array()) {
        $data = $this->tableGateway->select(function (Select $select) use ($start, $pageSum, $columns, $where, $order) {
            $select->join('play_game_price', 'play_game_price.id = play_game_info.pid', array('way' => 'name'));
            $select->where($where);
            if ($pageSum) {
                $select->limit($pageSum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }

    //店铺下的活动列表
    public function getApiShopGameInfo($start = 0, $pageSum = 0, $columns = array(), $where = array(), $order = array()) {
        $data = $this->tableGateway->select(function (Select $select) use ($start, $pageSum, $columns, $where, $order) {
            $select->join('play_organizer_game', 'play_organizer_game.id = play_game_info.gid', array('low_money', 'buy_num', 'ticket_num', 'title', 'gid' => 'id', 'thumb'));
            $select->where($where);
            if ($pageSum) {
                $select->limit($pageSum)->offset($start);
            }
            $select->order($order);
            $select->group(array('play_game_info.shop_id'));
        });
        return $data;
    }

}