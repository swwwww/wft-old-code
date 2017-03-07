<?php
/**
 * Created by PhpStorm.
 * User: chyxin
 * Date: 2015/9/9
 * Time: 10:55
 */

namespace Application\Model;

use Zend\Db\Sql\Select;

class WeixinDituiLogTable extends BaseTable
{
    /**
     * 获取渠道微信关注数量
     *
     * @param int $offset
     * @param int $row
     * @param array $columns
     * @param array $where
     * @param array $group
     *
     * @return \Zend\Db\ResultSet\ResultSet
     */
    public function getSceneConcernData($offset = 0, $row = 20, $columns = [], $where = [], $group = [])
    {
        $resultSet = $this->tableGateway->select(function (Select $select) use ($offset, $row, $columns, $where, $group) {
            if (!empty($columns)) {
                $select->columns($columns);
            }
            if ($row) {
                $select->limit($row)->offset($offset);
            }
            $select->where($where)->group($group);
        });

        return $resultSet;
    }
}