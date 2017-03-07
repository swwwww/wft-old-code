<?php
namespace Application\Model;


use Zend\Db\Sql\Predicate\In;
use Zend\Db\Sql\Select;

class PlayUserTable extends BaseTable
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

    /**
     * 返回推送用户标识列表
     * @param $uids |array
     * @return \Zend\Db\ResultSet\ResultSet
     */
    public function findGeTuiId($uids = array())
    {


        $res = $this->tableGateway->select(function (Select $select) use ($uids) {
            if (!empty($uids)) {
                $select->where(array(new In('uid', $uids)));
            }
        });

        $new_uids = array();
        foreach ($res as $v) {
            $new_uids[$v->uid] = $v->uid . '__' . substr($v->token, 0, 10);
        }
        return $new_uids;
    }

}