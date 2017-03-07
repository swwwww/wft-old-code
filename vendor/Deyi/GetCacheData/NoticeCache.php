<?php

namespace Deyi\GetCacheData;

use Application\Module;
use Deyi\BaseController;
use library\Service\System\Cache\RedCache;

class NoticeCache
{
    use BaseController;

    private static $_instance = null;

    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }

    //获取新奖励消息
    public static function getNewReward($uid)
    {
        if (RedCache::get('D:NewsReward:' . $uid)) {
            return 1;
        } else {
            return 0;
        }
    }

    //设置新奖励消息
    public static function setNewReward($uid)
    {
        return RedCache::incrby('D:NewsReward:' . $uid, 1);

    }

    //删除新奖励消息
    public static function delNewReward($uid)
    {
        return RedCache::del('D:NewsReward:' . $uid);
    }


    private static function _getInstance()
    {
        if (NULL === static::$_instance) {
            static::$_instance = new CouponCache();
        }
        return static::$_instance;
    }


}



