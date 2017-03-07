<?php

namespace library\Model;


use Zend\Db\Sql\Select;

class PlayOrderInsureTable extends BaseTable
{
    public function getPersonList($start, $pageNum, $where = array())
    {
        $data = $this->tableGateway->select(function (Select $select) use ($start, $pageNum, $where) {
            $select->where(array($where));
            $select->join('play_order_info', 'play_order_info.order_sn=play_order_insure.order_sn', array('dateline', 'username', 'user_id', 'buy_name', 'buy_phone','phone'), 'left');
            $select->join('play_order_otherdata', 'play_order_info.order_sn=play_order_otherdata.order_sn', array('full_sssociates','message'), 'left');
            $select->group('play_order_insure.insure_id');
            if ($pageNum) {
                $select->limit($pageNum)->offset($start);
            }
            $select->order(array('order_sn' => 'desc'));
        });

        return $data;
    }

    public function outPersonList($where = array())
    {
        $data = $this->tableGateway->select(function (Select $select) use ($where) {
            $select->where(array($where));
            $select->join('play_order_info', 'play_order_info.order_sn=play_order_insure.order_sn', array('dateline', 'username', 'user_id', 'buy_name', 'buy_phone','phone'), 'left');
            $select->join('play_excercise_event', 'play_order_info.coupon_id=play_excercise_event.id', array('shop_name'), 'left');
            $select->join('play_order_otherdata', 'play_order_otherdata.order_sn=play_order_insure.order_sn', array('meeting_place'), 'left');
            $select->join('play_excercise_price', 'play_order_info.coupon_id=play_order_insure.coupon_id', array('price_name'), 'left');
            $select->group('play_order_insure.insure_id');
            $select->order(array('order_sn' => 'desc'));
        });
        return $data;
    }

    public function unname($id)
    {
        $res = $this->tableGateway->select(function ($select) use ($id) {
            $select->columns(array('order_sn'));
            $select->where(array('coupon_id' => $id,'insure_status'=>0))->group('order_sn');
        });
        $data=array();
        foreach ($res as $v) {
            $data[]=$v->order_sn;
        }

        return $data;
    }


}