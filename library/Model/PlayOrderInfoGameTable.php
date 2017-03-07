<?php
namespace library\Model;

class PlayOrderInfoGameTable extends BaseTable
{

    public function getOrderLinkInfo($where=array()){
        $resultSet = $this->tableGateway->select(function ($select) use ($where) {
            $select->join('play_game_price', 'play_game_price.id=play_order_info_game.price_id', array('account_money'));
            $select->where($where);
        });

        return $resultSet;
    }


}