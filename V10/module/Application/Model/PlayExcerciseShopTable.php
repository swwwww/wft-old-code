<?php
namespace Application\Model;

use Zend\Db\Sql\Predicate\In;
use Zend\Db\Sql\Predicate\NotIn;
use Zend\Db\Sql\Select;

class PlayExcerciseShopTable extends BaseTable
{

    //获取活动对应游玩地列表
    public function getExcerciseShop($eid, $limit = 100)
    {

        return $this->tableGateway->select(function (Select $select) use ($eid, $limit) {
            $select->join('play_shop', 'play_shop.shop_id=play_excercise_shop.shopid', array("*"), 'left');
            $select->where(array('bid' => $eid, 'play_excercise_shop.is_close' => 0));
            $select->limit($limit);
        });
    }

}