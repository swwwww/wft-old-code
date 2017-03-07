<?php

namespace Application\Model;

use Deyi\GetCacheData\CacheUpdateList;
use Deyi\Paginator;
use Zend\Db\Sql\Select;
use Zend\Db\TableGateway\TableGateway;


class BaseTable
{
    public $tableGateway;

    public function __construct(TableGateway $tableGateway)
    {
        return $this->tableGateway = $tableGateway;
    }

    public function  getlastInsertValue()
    {
        return $this->tableGateway->lastInsertValue;
    }

    public function insert($data)
    {
        CacheUpdateList::upCache($this->tableGateway->getTable(),$data);
        return $this->tableGateway->insert($data);
    }

    public function get($where)
    {
        return $this->tableGateway->select($where)->current();
    }

    public function update($data, $where)
    {
        CacheUpdateList::upCache($this->tableGateway->getTable(),$where);
        return $this->tableGateway->update($data, $where);
    }

    public function fetchAll($where = array(), $order = array(), $limit = 0)
    {
        $return = $this->tableGateway->select(function ($select) use ($where, $order, $limit) {
            $select->where($where)->order($order);
            if ($limit) {
                $select->limit($limit);
            }
        });
        return $return;
    }

    public function fetchLimit($offset = 0, $row = 20, $columns = array(), $where = array(), $order = array(), $like = array())
    {
        $resultSet = $this->tableGateway->select(function ($select) use ($columns, $where, $like, $order, $offset, $row) {
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

    //精简分页
    public function paging($page = 1, $row = 20, $columns = array(), $where = array(), $order = array())
    {
        $page = $page < 1 ? 1 : $page;
        $offset = ($page - 1) * $row;
        $limit_data = $this->tableGateway->select(function (Select $select) use ($columns, $offset, $where, $order, $row) {
            if (!empty($columns)) {
                $select->columns($columns);
            }
            $select->where($where)->order($order);
            if ($row) {
                $select->limit($row)->offset($offset);
            }
        });

        $count = $this->tableGateway->select(function (Select $select) use ($columns, $where) {
            if (!empty($columns)) {
                $select->columns($columns);
            }
            $select->where($where);
        })->count();

        return new Paginator($page, $count, $row, '', $limit_data);

    }


    public function delete($where)
    {
        CacheUpdateList::upCache($this->tableGateway->getTable(),$where);
        return $this->tableGateway->delete($where);
    }
}