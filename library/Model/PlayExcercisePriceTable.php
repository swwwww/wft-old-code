<?php
namespace library\Model;

use Zend\Db\Sql\Predicate\In;
use Zend\Db\Sql\Predicate\NotIn;
use Zend\Db\Sql\Select;

class PlayExcercisePriceTable extends BaseTable
{

    public function getBackPrice($where=array()){
        $data = $this->tableGateway->select(function ($select) use ($where) {
            $select->join('play_excercise_code', 'play_excercise_code.pid = play_excercise_price.id', array('id'),'left');
            $select->where($where);
        })->toArray();
        return $data;
    }

}