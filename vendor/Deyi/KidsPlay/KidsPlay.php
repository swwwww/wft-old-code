<?php

/**
 * 取消活动订单&取消普通订单
 */
namespace Deyi\KidsPlay;


use Deyi\BaseController;
use Application\Module;
use Deyi\GetCacheData\CouponCache;


class KidsPlay
{
    use BaseController;


    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }

    /**
     * 更新活动状态数据
     * @param $bid
     * @return bool
     */
    private function _updateMax($bid)
    {

        $time = time();
        $st = $this->_getPlayExcerciseEventTable()->fetchLimit(0, 1, [], ['bid' => $bid, 'sell_status > ?' => 0, 'customize' => 0, 'sell_status!=3 and join_number<perfect_number and over_time>' . time()], ['start_time' => 'asc'])->current();
        if (!$st) {
            return false;
        }
        $et = $this->_getPlayExcerciseEventTable()->fetchLimit(0, 1, [], ['bid' => $bid, 'sell_status > ?' => 0, 'customize' => 0, 'sell_status!=3 and join_number<perfect_number and over_time>' . time()], ['end_time' => 'desc'])->current();
        $it = $this->_getPlayExcerciseEventTable()->fetchLimit(0, 1, [], ['bid' => $bid, 'sell_status > ?' => 0, 'customize' => 0], ['id' => 'desc'])->current();

        $count = $this->_getPlayExcerciseEventTable()->fetchCount(['bid' => $bid, 'sell_status >= ?' => 1, 'customize' => 0, "(join_number<perfect_number and over_time>{$time} and sell_status!=3)"]);

        $shop = $this->_getPlayShopTable()->get(['shop_id' => $it->shop_id]);
        $busniess_circle = CouponCache::getBusniessCircle($shop->busniess_circle);

        $data['max_start_time'] = $st->start_time;
        $data['min_end_time'] = $et->end_time;
        $data['circle'] = $busniess_circle;
        $data['all_number'] = $count;
       return $this->_getPlayExcerciseBaseTable()->update($data, ['id' => $bid]);

    }


    static function updateMax($bid)
    {
        $kidsplay = new KidsPlay();
        return $kidsplay->_updateMax($bid);
    }


}