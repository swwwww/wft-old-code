<?php

namespace library\Service\System\Db;

use library\Service\System\Cache\RedCache;
use library\Service\ServiceManager;
use Zend\Db\Adapter\Adapter;

class Db
{

    const DB_Master = 'db';  //主从库配置
    //const DB_Slave = 'db2';


    protected $db = self::DB_Master; //默认主库配置文件  读写分离时修改此值
    static private $adapters = array();


    /**
     * 如果使用事务需要使用此方法
     * @return Adapter
     */
    public function getAdapter()
    {
        if (isset(self::$adapters[$this->db])) {
            return self::$adapters[$this->db];
        } else {
            self::$adapters[$this->db] = new Adapter(ServiceManager::getConfig($this->db));  //读写分离 ,控制 $this->db
            return self::$adapters[$this->db];
        }
    }

    public function setDb($cName)
    {
        $this->db = $cName;
    }


    /**
     * 缓存类查询, 如果需要使用事务,需要使用 getAdapter 原生query
     * @param string $sql
     * @param array $prepare
     * @param int $cache_ttl
     * @param string $cache_key
     * @return bool|array
     * @throws \Exception
     */
    public function queryCache($sql = '', $prepare = array(), $cache_ttl = 0, $cache_key = '')
    {
        //$this->setDb(Db::DB_Master);  //设置查询数据库

        if (!$sql) {
            throw new \Exception("sql语句为空");
        } else {
            if ($cache_ttl and !$cache_key) {
                $cache_key = md5($sql . json_encode($prepare, JSON_UNESCAPED_UNICODE));
            }
            return RedCache::fromCacheData($cache_key, function () use ($sql, $prepare) {
                return $this->getAdapter()->query($sql, $prepare)->toArray();
            }, $cache_ttl, true);
        }
    }


}