<?php
namespace library\Model;

class QualifyTable extends BaseTable
{

    public function  joinUserGoodList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_user', 'play_qualify_coupon.uid = play_user.uid', array('username', 'phone'), 'left');
            $select->join('play_organizer_game', 'play_qualify_coupon.pay_object_id = play_organizer_game.id',
                array('title'), 'left');
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });

        return $data;
    }

    public function  joinUserGoodCount($where = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($where) {
            $select->join('play_user', 'play_qualify_coupon.uid = play_user.uid', array('username', 'phone'), 'left');
            $select->join('play_organizer_game', 'play_qualify_coupon.pay_object_id = play_organizer_game.id',
                array('title'), 'left');
            $select->where($where);
        });

        return count($data);
    }


}