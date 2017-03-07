<?php
namespace library\Model;

class AuthAccessTable extends BaseTable
{

    public function fetchLimit($offset = 0, $row = 20, $columns = array(), $where = array(), $order = array(), $like = array())
    {
        $resultSet = $this->tableGateway->select(function ($select) use ($columns, $where, $like, $order, $offset, $row) {
            if (!empty($columns)) {
                $select->columns($columns);
            }
            if (!empty($like) and $like[key($like)]) {

                if (is_numeric($like[key($like)])) {
                    $select->where->like('uid', "%{$like[key($like)]}%");
                } else {
                    $select->where->like('username', "%{$like[key($like)]}%");
                }
            }
            if ($row) {
                $select->limit($row)->offset($offset);
            }
            $select->where($where)->order($order);
        });
        return $resultSet;
    }

}