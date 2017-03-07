<?php
namespace library\Model;

use Zend\Db\Sql\Select;

class PlayGameTimeTable extends BaseTable {
    //活动关联的店铺
    public function getAdminGameShopList($start = 0, $pageSum = 0, $columns = [], $where = [], $order = []) {
        $data = $this->tableGateway->select(function (Select $select) use ($start, $pageSum, $columns, $where, $order) {
            $select->join('play_shop', 'play_shop.shop_id = play_game_time.sid', ['shop_name', 'shop_id', 'shop_address']);
            $select->join('play_region', 'play_region.rid = play_shop.busniess_circle', ['circle' => 'name']);
            if ($pageSum) {
                $select->limit($pageSum)->offset($start);
            }
            $select->where($where);
            $select->order($order);
            $select->Group('play_shop.shop_id');
        });
        return $data;
    }
}