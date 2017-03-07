<?php

namespace library\Model;


class PlayActivityTable extends BaseTable
{

    public function  getActivityList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array('id' => 'asc'))
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_admin', 'play_admin.id = play_activity.uid', array('admin_name', 'image'), 'left');
            $select->where($where);
            if ($pagesum) {
                $select ->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }

    //api
    public function getList($offset = 0, $row = 20, $columns = array(), $where = array(), $order = array())
    {
        $resultSet = $this->tableGateway->select(function ($select) use ($columns, $where, $order, $offset, $row) {
            $select->join('play_admin', 'play_admin.id=play_activity.uid', array('admin_name', 'image'));
            if (!empty($columns)) {
                $select->columns($columns);
            }
            if ($row) {
                $select->limit($row)->offset($offset);
            }
            $select->where($where)->order($order);
        });
        return $resultSet;
    }
}