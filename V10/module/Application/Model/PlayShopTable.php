<?php

namespace Application\Model;


use Zend\Db\Sql\Predicate\In;
use Zend\Db\Sql\Select;

class PlayShopTable extends BaseTable
{
    //获取商家列表
    public function  getShopList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array('shop_id' => 'desc'))
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }



}