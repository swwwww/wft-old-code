<?php

namespace Application\Model;


class PlayNewsTable extends BaseTable
{
    //获取资讯列表
    public function  getAdminNewsList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_admin', 'play_admin.id = play_news.editor_id', array('admin_name', 'image'), 'left');
            $select->where($where);
            if ($pagesum) {
                $select ->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }
}