<?php

namespace Application\Model;


class PlayAccountLogTable extends BaseTable
{
    public function  joinUserEditorList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_user', 'play_account_log.uid = play_user.uid', array('username', 'phone'), 'left');
            $select->join('play_order_info', 'play_account_log.object_id = play_order_info.order_sn', array('coupon_name','coupon_id'), 'left');
            $select->join('play_admin', 'play_account_log.editor_id = play_admin.id',
                array('admin_name'), 'left');
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }

    public function  joinUserEditorCount($where = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($where) {
            $select->join('play_user', 'play_account_log.uid = play_user.uid', array('username', 'phone'), 'left');
            $select->join('play_order_info', 'play_account_log.object_id = play_order_info.order_sn', array('coupon_name','coupon_id'), 'left');
            $select->join('play_admin', 'play_account_log.editor_id = play_admin.id',
                array('admin_name'), 'left');
            $select->where($where);
        });
        return count($data);
    }

    //财审获取商家流水
    public function getShopLog($where=array()){
        $data = $this->tableGateway->select(function ($select) use ($where) {
            $select->join('play_organizer', 'play_organizer_account_log.oid = play_organizer.id', array('name'), 'left');
            $select->join('play_organizer_account', 'play_organizer.id = play_organizer_account.organizer_id', array('use_money'), 'left');
            $select->where($where);
        });
        return $data;
    }
}