<?php
/**
 * Created by PhpStorm.
 * User: chyxin
 * Date: 2015/9/23
 * Time: 9:40
 */

namespace Application\Model;

class ActivityBabygogogoBatchTable extends BaseTable
{
    public function getBatch($offset = 0, $row = 20, $columns = [], $where = [], $order = [], $group = [])
    {
        $data = $this->tableGateway->select(function ($select) use ($offset, $row, $columns, $where, $order, $group) {
            if (!empty($columns)) {
                $select->columns($columns);
            }

            $select->where($where)->order($order);
            if ($row) {
                $select->limit($row)->offset($offset);
            }
            if ($group) {
                $select->group($group);
            }
        });
        return $data;
    }
}