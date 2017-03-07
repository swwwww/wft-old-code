<?php

namespace Application\Model;


class PlayActivityTable extends BaseTable
{
    //获取商家列表
    public function  getActivityList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array('id' => 'asc'))
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


    //获取 推送的专题 发现里面的 编辑推荐
    public function getApiBlockActivity($start = 0, $pageSum = 0, $where = array(), $order = array()) {
        $data = $this->tableGateway->select(function ($select) use ($start, $pageSum, $where, $order) {
            $select->join('play_index_block', 'play_index_block.link_id = play_activity.id', array(), 'left');
            $select->where($where);
            if ($pageSum) {
                $select ->limit($pageSum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }

}