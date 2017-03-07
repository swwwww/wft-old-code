<?php

namespace Application\Model;

use Deyi\GetCacheData\CacheUpdateList;
use Zend\Db\Sql\Select;
use Zend\Db\TableGateway\TableGateway;


class BaseTable
{
    public $tableGateway;

    public function __construct(TableGateway $tableGateway)
    {
        return $this->tableGateway = $tableGateway;
    }

    public function getlastInsertValue()
    {
        return $this->tableGateway->lastInsertValue;
    }

    public function insert($data)
    {
        CacheUpdateList::upCache($this->tableGateway->getTable(),$data);
        return $this->tableGateway->insert($data);
    }

    public function update($data, $where)
    {
        CacheUpdateList::upCache($this->tableGateway->getTable(),$where);
        return $this->tableGateway->update($data, $where);
    }

    public function get($where)
    {
        return $this->tableGateway->select(function(Select $select)use($where){
            $select->where($where)->limit(1);
        })->current();
    }

    public function fetchAll($where = array(), $order = array(), $limit = 0)
    {
        return $this->tableGateway->select(function ($select) use ($where, $order, $limit) {
            $select->where($where)->order($order);
            if ($limit) {
                $select->limit($limit);
            }
        });
    }

    public function fetchLimit($offset = 0, $row = 20, $columns = array(), $where = array(), $order = array(), $like = array())
    {
        $resultSet = $this->tableGateway->select(function (Select $select) use ($columns, $where, $like, $order, $offset, $row) {
            if (!empty($columns)) {
                $select->columns($columns);
            }
            if (!empty($like) and $like[key($like)]) {
                $select->where->like(key($like), "%{$like[key($like)]}%");
            }
            if ($row) {
                $select->limit($row)->offset($offset);
            }
            $select->where($where)->order($order);
        });
        return $resultSet;
    }

    /**
     * @param array $where
     * @param array $like
     * @return int
     */
    public function fetchCount($where = array(), $like = array())
    {
        $resultSet = $this->tableGateway->select(function (Select $select) use ($where, $like) {
            $select->columns(array('count' => new \Zend\Db\Sql\Expression('COUNT(*)')));
            if (!empty($like) and $like[key($like)]) {
                $select->where->like(key($like), "%{$like[key($like)]}%");
            }
            $select->where($where);
        });
        return (int)$resultSet->current()->count;
    }






    public function delete($where)
    {
        CacheUpdateList::upCache($this->tableGateway->getTable(),$where);
        return $this->tableGateway->delete($where);
    }
}