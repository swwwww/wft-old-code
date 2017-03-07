<?php
namespace library\Service\System\Cache;
class RedCache
{
    private static $_rd = null;


    private static function ini()
    {
        if (self::$_rd) {
            return self::$_rd;
        }
        self::$_rd = new \Redis();
        self::$_rd->connect('127.0.0.1', 6379);
        return self::$_rd;
    }

    public static function get($k)
    {
        return self::ini()->get($k);
    }

    public static function ttl($k)
    {
        return self::ini()->ttl($k);
    }

    /**
     * 添加一个键值对缓存并设置过期时间
     * @param $k
     * @param $v
     * @param int $ttl
     * @return bool
     */
    public static function set($k, $v, $ttl = 300)
    {
        return self::ini()->setex($k, $ttl, $v);

    }


    /**
     * 原子增加,如果不存在,首先将操作对象置0,然后增加
     * @param $value
     * @return int
     */
    public static function incrby($key, $value)
    {
        return self::ini()->incrBy($key, $value);
    }

    /**
     * 原子减,如果不存在,首先将操作对象置0,然后减
     * @param $value
     * @return int
     */
    public static function decrby($key, $value)
    {
        return self::ini()->decrBy($key, $value);
    }

    /**
     * 不带过期时间的缓存键值对,非特殊情况不要使用
     * @param $k
     * @param $v
     * @return bool
     */
    public static function sets($k, $v)
    {
        return self::ini()->set($k, $v);
    }

    /**
     * 删除缓存
     * @param $key
     * @return int
     */
    public static function del($key)
    {
        return self::ini()->del($key);
    }

    /**
     * 删除所有数据库,勿随意调用
     * @return bool
     */
    public static function clearAll()
    {
        return self::ini()->flushAll();
    }

    /**
     * 设置一个键值对，只有当该键不存在,如果存在返回0不做任何修改 1修改成功
     * @param $k
     * @param $v
     * @param int $ttl
     * @return bool
     */
    public static function setnx($k, $v, $ttl = 300)
    {

        $s = (int)self::ini()->setnx($k, $v);
        if ($s) {
            self::ini()->expire($k, $ttl);
        }
        return $s;
    }


    /**
     * 从缓存获取数据并生成数据方法,值不存在或无效返回false
     * @param $KeyName |键
     * @param \Closure $fun |如果数据不存在闭包操作获取新数据并存入缓存
     * @param int $ttl |超时时间
     * @param $isArray | 是否转json存入缓存,并且decode后取出数组(尽量数组进数组出,此处勿改!!)
     * @return bool|string|int|array
     */
    public static function fromCacheData($KeyName, \Closure $fun, $ttl = 120, $isArray = false)
    {


        //key => 1
        //
        //
        // 0 get key 1 if()
        //DEBUG
        //$ttl=0;  //直接返回结果
        //self::del($KeyName); //删除缓存
        //self::del("L:" . $KeyName);// 释放锁


        $valid_ttl = 1800;  //避免缓存穿透,增加冗余时间
        $data = false;
        if ($ttl == 0) {
            $data = $fun();
            RedCache::del($KeyName);
        } else {
            $unset_ttl = (int)RedCache::ttl($KeyName); //获取剩余秒数
            if ($unset_ttl >= $valid_ttl) {
                $data = self::typeResult(RedCache::get($KeyName), $isArray);
            } else { //小于
                //生成缓存
                $LockKeyName = "L:" . $KeyName;
                $lock = RedCache::setnx($LockKeyName, 1, 10);//获得一个10秒的锁
                if ($lock === 1) {
                    $data = $fun();  //获取动态数据,重新生成缓存
                    if ($isArray) {
                        if ($data and !empty($data) and is_array($data)){
                            $data['c_t'] = microtime(true);
                        }
                        RedCache::set($KeyName, json_encode($data, JSON_UNESCAPED_UNICODE), $ttl + $valid_ttl);
                    } else {
                        RedCache::set($KeyName, $data, $ttl + $valid_ttl);
                    }
                    RedCache::del($LockKeyName);// 释放锁
                } elseif ($unset_ttl > 1) {
                    //未取得锁,缓存有冗余数据
                    $data = self::typeResult(RedCache::get($KeyName), $isArray);
                } else {
                    //未取得锁,缓存也没有冗余数据, 等待缓存生成,阻止直接打入数据库,此情况只存在于长时间未访问,突然大量并发,
                    for ($i = 20; $i > 0; $i--) { //最多重试20次,每次0.5秒,最大等待时间位10秒
                        usleep(500000);
                        $unset_ttl = (int)RedCache::ttl($KeyName); //持续获取剩余秒数
                        if ($unset_ttl > 1) {
                            $data = self::typeResult(RedCache::get($KeyName), $isArray);
                            break;
                        }
                    }
                }
            }
        }

        if(isset($data['c_t'])){
            unset($data['c_t']);
        }
        return $data;
    }

    //更新缓存内数据
    public static function updateCache($KeyName, $new_data = array(), $cache_data = array())
    {
        if (!$cache_data) {
            $cache_data = array();
        }
        //当前进程时间 > 缓存时间
        if (array_diff($new_data, $cache_data) and !isset($cache_data['c_t']) or $cache_data['c_t'] < microtime(true)) { //有差集,当前操作是新数据
            if ($new_data and !empty($new_data)){
                $new_data['c_t'] = microtime(true);
                // 两个操作同时进入此处
                return RedCache::set($KeyName, json_encode(array_merge($cache_data, $new_data), JSON_UNESCAPED_UNICODE));
            }else{
                return false;
            }
        }
    }


    private static function typeResult($data, $isArray)
    {
        if ($isArray) {
            if (!$data or empty($data)) {
                false;
            } else {
               return json_decode($data, true);
            }
        } else {
            return $data;
        }
    }

    //队列获取 指定位置的值
    public static function lIndex($key, $index)
    {
        return self::ini()->lIndex($key, $index);
    }

    //队列 获取
    public static function rPop($key)
    {
        return self::ini()->rPop($key);
    }

    //队列 插入
    public static function lPush($key, $val)
    {
        return self::ini()->lPush($key, $val);
    }



}