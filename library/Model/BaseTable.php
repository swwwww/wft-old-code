<?php

namespace library\Model;

use library\Service\System\Db\Db;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Select;
use Zend\Db\TableGateway\TableGateway;


class BaseTable extends Db
{

    protected $tablename;
    static private $tables = array();

    /**
     * BaseModel constructor.
     * @param string $tablename
     * @throws \Exception
     */
    public function __construct($tablename = '')
    {
        if ($tablename) {
            $this->tablename = $tablename;
        }
        if (!$this->tablename) {
            throw new \Exception("表名不能为空2");
        } elseif (!array_key_exists($this->tablename, self::$tables)) {
            self::$tables[$this->tablename] = new TableGateway($this->tablename ?: $tablename, $this->getAdapter());
        }
    }

    public function getlastInsertValue()
    {
        return $this->getTableGateway()->lastInsertValue;
    }

    public function insert($data)
    {

        return $this->getTableGateway()->insert($data);
    }

    public function get($where)
    {
        return $this->getTableGateway()->select($where)->current();
    }

    public function update($data, $where)
    {
        return $this->getTableGateway()->update($data, $where);
    }

    /**
     * @param array $where
     * @param array $order
     * @param int $limit
     * @return ResultSet
     */

    public function fetchAll($where = array(), $order = array(), $limit = 0)
    {
        $return = $this->getTableGateway()->select(function ($select) use ($where, $order, $limit) {
            $select->where($where)->order($order);
            if ($limit) {
                $select->limit($limit);
            }
        });
        return $return;
    }

    /**
     * @param int $offset
     * @param int $row
     * @param array $columns
     * @param array $where
     * @param array $order
     * @param array $like
     * @return ResultSet
     */

    public function fetchLimit($offset = 0, $row = 20, $columns = array(), $where = array(), $order = array(), $like = array())
    {
        $resultSet = $this->getTableGateway()->select(function ($select) use ($columns, $where, $like, $order, $offset, $row) {
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
        $resultSet = $this->getTableGateway()->select(function (Select $select) use ($where, $like) {
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
        return $this->getTableGateway()->delete($where);
    }



    /**
     * @return TableGateway
     * @throws \Exception
     */
    public function getTableGateway()
    {
        if (!$this->tablename) {
            throw new \Exception("表名称未设置");
        }
        return self::$tables[$this->tablename];
    }
    
    
}