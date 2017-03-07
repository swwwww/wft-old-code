<?php
namespace Application\Model;

use Zend\Db\Sql\Predicate\In;
use Zend\Db\Sql\Predicate\NotIn;
use Zend\Db\Sql\Select;

class PlayExcerciseShopTable extends BaseTable
{

    public function  getShopList($start = 0, $pagesum = 0, $columns = array(), $where = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where) {
            $select->columns($columns);
            $select->join('play_shop', 'play_excercise_shop.shopid = play_shop.shop_id', array('shop_id','shop_city','shop_name','busniess_circle'), 'left');
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
        });
        return $data;
    }
}