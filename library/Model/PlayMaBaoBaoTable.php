<?php

namespace library\Model;


class PlayMaBaoBaoTable extends BaseTable
{


    public function  getAdminMaBaoBaoList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_user', 'play_user.uid = play_mabaobao.uid', array('u_username' => 'username', 'phone'), 'left');
            $select->where($where);
            if ($pagesum) {
                $select ->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }


}