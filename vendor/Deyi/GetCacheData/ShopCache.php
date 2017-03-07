<?php

namespace Deyi\GetCacheData;

use Application\Module;
use Deyi\BaseController;
use library\Service\System\Cache\RedCache;

class ShopCache
{
    use BaseController;

    private static $_instance = null;

    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }


    /**
     * 获取游玩地下的卡券数
     * @param $shop_id
     * @return int
     */
    private function _getShopGoodNum($shop_id)
    {
        $key = "D:shop_good_num:{$shop_id}";


        $num = (int)RedCache::get($key);
        if (!$num) {
            $time = time();
            $db = $this->_getAdapter();


            $good_where = "play_game_info.shop_id = {$shop_id} AND  play_organizer_game.end_time >= {$time} AND play_organizer_game.start_time <= {$time} AND play_organizer_game.status > 0";
            $good_sql = "SELECT
count(*) as c
FROM
play_game_info
LEFT JOIN play_organizer_game ON play_game_info.gid = play_organizer_game.id
WHERE
{$good_where}
GROUP BY
play_organizer_game.id
";
            $num = $db->query($good_sql, array())->count();

            $db->query("update play_shop set good_num=? WHERE  shop_id=?", array($num, $shop_id));
            RedCache::set($key, (int)$num, 3600 * 24);
        }

        return $num;

    }


    private static function _getInstance()
    {
        if (NULL === static::$_instance) {
            static::$_instance = new ShopCache();
        }
        return static::$_instance;
    }

    public static function getShopGoodNum($shop_id)
    {
        return self::_getInstance()->_getShopGoodNum($shop_id);
    }

}



