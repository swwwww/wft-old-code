<?php

namespace Deyi\GetCacheData;

use Application\Module;
use Deyi\BaseController;
use library\Service\System\Cache\RedCache;

class Labels
{
    use BaseController;

    private static $_instance = null;

    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }

    //从缓存获取商品标签
    private function _getLabels($special_labels)
    {
        $data = array();
        $j = json_decode($special_labels, true);

        if ($j) {

            $res = RedCache::fromCacheData('D:GL', function () {
                $db = $this->_getAdapter();
                return $db->query("select * from play_tags  order by sort DESC ", array())->toArray();
            }, 3600 * 24 * 5, true);

            if (!$j or empty($j)) {
                return array();
            } else {
                $r = array();
                foreach ($res as $v) {
                    if (in_array($v['id'], $j)) {
                        $r[] = array(
                            'name' => $v['tag_name'],
                            'img' => $this->_getConfig()['url'] . $v['img']
                        );
                    }
                }
                return $r;

            }

        }
        return $data;

    }

    private static function _getInstance()
    {
        if (NULL === static::$_instance) {
            static::$_instance = new Labels();
        }
        return static::$_instance;
    }


    public static function getLabels($special_labels)
    {
        return self::_getInstance()->_getLabels($special_labels);

    }


}



