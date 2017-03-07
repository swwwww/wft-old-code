<?php
namespace Application\Model;

use Zend\Db\Sql\Select;

class PlayGameInfoTable extends BaseTable {

    public function getGameShopList($start = 0, $pageSum = 0, $columns = array(), $where = array(), $order = array()) {
        $data = $this->tableGateway->select(function (Select $select) use ($start, $pageSum, $columns, $where, $order) {
            $select->join('play_shop', 'play_shop.shop_id = play_game_info.shop_id');
            $select->join('play_region', 'play_region.rid = play_shop.busniess_circle', array('shop_circle' => 'name'));
            $select->where($where);
            if ($pageSum) {
                $select->limit($pageSum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }


    //活动关联的店铺
    public function getApiGameShopList($start = 0, $pageSum = 0, $columns = array(), $where = array(), $order = array()) {
        $data = $this->tableGateway->select(function (Select $select) use ($start, $pageSum, $columns, $where, $order) {
            $select->join('play_game_time', 'play_game_time.id = play_game_info.tid', array());
            $select->join('play_shop', 'play_shop.shop_id = play_game_time.sid', array('shop_name', 'shop_id','shop_address', 'addr_x', 'addr_y'));
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


    public function getShopCouponsList($start = 0, $pageSum = 0, $columns = array(), $where = array(), $order = array()){
        $data = $this->tableGateway->select(function (Select $select) use ($start, $pageSum, $columns, $where, $order) {
            $select->join('play_organizer_game', 'play_organizer_game.id = play_game_info.gid', array('cover','title','editor_talk'));
            $select->where($where);
            if ($pageSum) {
                $select->limit($pageSum)->offset($start);
            }
            $select->order($order);
            $select->Group('play_game_info.id');
        });
        return $data;
    }

    public function getGameInfo($where = array()){
        $data = $this->tableGateway->select(function (Select $select) use ( $where) {
            $select->join('play_organizer_game', 'play_organizer_game.id = play_game_info.gid', array('cover','title','editor_talk'),'left');
            $select->where($where);
        });
        return $data;
    }
}