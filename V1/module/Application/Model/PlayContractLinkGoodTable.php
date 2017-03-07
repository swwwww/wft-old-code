<?php

namespace Application\Model;

class PlayContractLinkGoodTable extends BaseTable
{


    public function  getPriceList($start = 0, $pageSum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pageSum, $columns, $where, $order) {
            $select->join('play_contract_link_price', 'play_contract_link_price.link_good_id=play_contract_link_good.id', array('account_money', 'money', 'price', 'total_num', 'mid' => 'id', 'price_status' => 'status'), 'left');
            $select->where($where);
            if ($pageSum) {
                $select->limit($pageSum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }

}