<?php

namespace Deyi\GetCacheData;

use Application\Module;
use Deyi\BaseController;
use library\Service\System\Cache\RedCache;

class ExcerciseCache
{
    use BaseController;

    private static $_instance = null;

    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }

    private static function _getInstance()
    {
        if (NULL === static::$_instance) {
            static::$_instance = new ExcerciseCache();
        }
        return static::$_instance;
    }

    private function _getCircleByBid($bid){
        RedCache::del('D:gsbb:' . $bid);
        $circle = RedCache::fromCacheData('D:gsbb:' . $bid, function () use ($bid) {
            $data = $this->_getPlayExcerciseEventTable()->getSession($bid)->current();
            $shop = $this->_getPlayShopTable()->get(['shop_id'=>$data->shop_id]);
            return $shop->busniess_circle;
        }, 4 * 3600, true);
        return $circle;
    }

    public static function getCircleByBid($bid)
    {
        return self::_getInstance()->_getCircleByBid($bid);
    }

    public function getvirnumByBid($bid){
        $db = $this->_getAdapter();
        $vir = $db->query("select sum(vir_number) as vir_num from play_excercise_event where bid = ? and ((sell_status in (0,1,2,3))
 or (sell_status = 3 and join_number > 0))",array($bid))->current();

        return $vir?$vir->vir_num:0;
    }

    public function getviraultByBid($bid){
        $db = $this->_getAdapter();
        $vir = $db->query("select sum(vir_ault) as vir_num from play_excercise_event where bid = ? and ((sell_status in (0,1,2,3))
 or (sell_status = 3 and join_number > 0))",array($bid))->current();

        return $vir?$vir->vir_num:0;
    }

    public function getvirchildByBid($bid){
        $db = $this->_getAdapter();
        $vir = $db->query("select sum(vir_child) as vir_num from play_excercise_event where bid = ? and ((sell_status in (0,1,2,3))
 or (sell_status = 3 and join_number > 0))",array($bid))->current();

        return $vir?$vir->vir_num:0;
    }

}



