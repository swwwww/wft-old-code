<?php

namespace library\Service\Kidsplay;

use Deyi\GetCacheData\CouponCache;
use library\Fun\M;
use library\Service\System\Cache\RedCache;

class Kidsplay {
    static  public  function getCircleByBid($bid){
        RedCache::del('D:gsbb:' . $bid);
        $circle = RedCache::fromCacheData('D:gsbb:' . $bid, function () use ($bid) {
            $data = M::getPlayExcerciseEventTable()->getSession($bid)->current();
            $shop = M::getPlayShopTable()->get(['shop_id'=>$data->shop_id]);
            return $shop->busniess_circle;
        }, 4 * 3600, true);
        return $circle;
    }


    public function getvirnumByBid($bid){
        $db  = M::getAdapter();
        $vir = $db->query("select sum(vir_number) as vir_num from play_excercise_event where bid = ? and ((sell_status in (0,1,2,3))
 or (sell_status = 3 and join_number > 0))",array($bid))->current();

        return $vir?$vir->vir_num:0;
    }

    public function getviraultByBid($bid){
        $db  = M::getAdapter();
        $vir = $db->query("select sum(vir_ault) as vir_num from play_excercise_event where bid = ? and ((sell_status in (0,1,2,3))
 or (sell_status = 3 and join_number > 0))",array($bid))->current();

        return $vir?$vir->vir_num:0;
    }

    public function getvirchildByBid($bid){
        $db  = M::getAdapter();
        $vir = $db->query("select sum(vir_child) as vir_num from play_excercise_event where bid = ? and ((sell_status in (0,1,2,3))
 or (sell_status = 3 and join_number > 0))",array($bid))->current();

        return $vir?$vir->vir_num:0;
    }

    // 获取当下可免费玩的场次数量
    public function getFreeCouponEventCount ($bid) {
        $pdo = M::getAdapter();
        $data_result = $pdo->query(
            " SELECT
	              count(*) AS free_coupon_event_count
              FROM
	              play_excercise_event
              LEFT JOIN play_excercise_price ON (
	                  play_excercise_price.eid = play_excercise_event.id
	              AND 
	                  is_other = 0
	              AND 
	                  is_close = 0
	              AND 
	                  free_coupon_need_count > 0
	              AND 
	                  (free_coupon_max_count = 0 OR free_coupon_max_count > free_coupon_join_count)
                  )
              WHERE
	              play_excercise_event.sell_status in (1,2) 
	          AND over_time > UNIX_TIMESTAMP() 
	          AND join_number < perfect_number 
	          AND play_excercise_price.id is not null
              AND play_excercise_event.bid = ? 
              GROUP BY play_excercise_event.id ",
            array($bid)
        )->count();

        return $data_result ? $data_result : 0;
    }

    // 获取某一场次下亲子游报名数量是否满员
    public static function getFreeCouponJoinIsFull ($eid) {
        $pdo = M::getAdapter();
        $data_result = $pdo->query(
            " SELECT
	              count(*) as c
              FROM
	              play_excercise_price
              WHERE
                  is_other = 0
              AND 
                  is_close = 0
              AND 
                  free_coupon_need_count > 0
              AND 
                  (free_coupon_max_count = 0 OR free_coupon_max_count > free_coupon_join_count)
              AND play_excercise_price.eid = ? 
            ",
            array($eid)
        )->current()->c;

        return $data_result > 0 ? false : true;
    }

    // 获取可免费玩的场次总数量
    public function getFreeCouponEventMax ($bid) {
        $pdo = M::getAdapter();
        $data_result = $pdo->query(
            " SELECT
	              count(*) AS free_coupon_event_max
              FROM
	              play_excercise_event
              LEFT JOIN play_excercise_price ON (
	                  play_excercise_price.eid = play_excercise_event.id
	              AND 
	                  is_other = 0
	              AND 
	                  is_close = 0
	              AND 
	                  free_coupon_need_count > 0
                  )
              WHERE
	              play_excercise_event.sell_status in (1,2)  
	          AND join_number < perfect_number 
	          AND play_excercise_price.id is not null
              AND play_excercise_event.bid = ? 
              GROUP BY play_excercise_event.id",
            array($bid)
        )->count();

        return $data_result ? $data_result : 0;
    }

    public function getSession ($base_id) {
        $pdo = M::getAdapter();
        $sql = " SELECT 
                    play_excercise_event.id as eid,
                    play_excercise_event.start_time,
                    play_excercise_event.end_time,
                    play_excercise_event.sell_status,
                    play_excercise_event.over_time,
                    play_excercise_event.open_time,
                    play_excercise_event.join_number,
                    play_excercise_event.join_ault,
                    play_excercise_event.join_child,
                    play_excercise_event.least_number,
                    play_excercise_event.perfect_number,
                    play_excercise_event.most_number,
                    play_excercise_event.shop_name,
                    play_excercise_event.shop_id,                    
                    play_excercise_event.vir_number,                    
                    play_excercise_event.vir_ault,                    
                    play_excercise_event.vir_child,                    
                    (select min(price) from play_excercise_price where play_excercise_price.is_close = 0 AND play_excercise_price.is_other = 0 AND play_excercise_price.eid = play_excercise_event.id) as low_price,
                    (select count(*)   from play_excercise_price where play_excercise_price.is_close = 0 AND play_excercise_price.is_other = 0 AND play_excercise_price.free_coupon_need_count > 0 AND (play_excercise_price.free_coupon_max_count = 0 OR play_excercise_price.free_coupon_max_count > play_excercise_price.free_coupon_join_count) AND play_excercise_price.eid = play_excercise_event.id) as vip_free
                 FROM play_excercise_event 
                 LEFT JOIN play_excercise_base ON (play_excercise_base.id = play_excercise_event.bid)
                 WHERE 
                    play_excercise_event.sell_status >= 0
                 AND 
                    play_excercise_event.sell_status <> 3
                 AND 
                    play_excercise_event.customize = 0
                 AND
                    play_excercise_event.bid = ?
                 AND 
                    play_excercise_base.release_status = 1
                 ORDER BY play_excercise_event.start_time ASC
               ";
        $data_return = $pdo->query($sql, array($base_id));
        return $data_return;
    }

    public function getKidsplayPrice ($param) {
        return M::getPlayExcercisePriceTable()->fetchAll($param)->toArray();
    }

    public static function getFreeJoinCountForExcercise ($bid) {
        if (empty($bid)) {
            return false;
        }

        if (is_array($bid)) {
            $bid = implode(',', $bid);
        }

        $pdo = M::getAdapter();
        $sql = "
                SELECT 
                    play_excercise_event.bid,
                    sum(play_excercise_price.free_coupon_join_count) as free_join_count
                FROM play_excercise_price 
                LEFT JOIN play_excercise_event ON (play_excercise_event.id = play_excercise_price.eid)
                WHERE play_excercise_event.bid in ({$bid})
                GROUP BY play_excercise_event.bid
               ";
        $data_free_join_count_for_excercise = $pdo->query($sql, array());
        
        return $data_free_join_count_for_excercise;
    }

    public function getCountPriceBuy ($uid, $pid) {
        $pdo = M::getAdapter();
        $data_count = $pdo->query(
            " SELECT
                 count(*) AS c
              FROM
                 play_order_info
              LEFT JOIN play_excercise_code ON play_excercise_code.order_sn = play_order_info.order_sn
              LEFT JOIN play_excercise_price ON play_excercise_price.id = play_excercise_code.pid
              WHERE
                  play_order_info.user_id = ?
              AND play_order_info.order_status = 1
              AND play_order_info.order_type = 3
              AND play_order_info.pay_status in (0,1,2,5,7)
              AND play_excercise_price.id = ?
            ",
            array($uid, $pid)
        )->current()->c;

        return $data_count;
    }

    // 获取对于指定收费项，用户使用免费玩资格券兑换的数量
    public function getCountPriceFreeBuy ($uid, $pid) {
        $pdo = M::getAdapter();
        $data_count = $pdo->query(
            "
             SELECT
                count(*) AS c
             FROM
                play_order_info
             LEFT JOIN play_excercise_code  ON play_excercise_code.order_sn = play_order_info.order_sn
             LEFT JOIN play_excercise_price ON play_excercise_price.id = play_excercise_code.pid
             WHERE
                 play_order_info.user_id = ?
             AND play_order_info.order_status = 1
             AND play_order_info.order_type = 3
             AND play_order_info.pay_status in (0,1,2,5,7)
             AND play_excercise_price.id = ?
             AND play_excercise_code.use_free_coupon > 0
            ",
            array($uid, $pid)
        )->current()->c;

        return $data_count;
    }

    // 在指定场次的当天，用户使用免费玩资格券兑换其他场次收费项的情况
    public function getCountPriceDayFreeBuy ($uid, $start_time, $end_time) {
        $pdo = M::getAdapter();
        $data_count = $pdo->query(
            "
             SELECT
                count(*) AS c
             FROM
                play_order_info
             LEFT JOIN play_excercise_code  ON play_excercise_code.order_sn = play_order_info.order_sn
             LEFT JOIN play_excercise_price ON play_excercise_price.id = play_excercise_code.pid
             LEFT JOIN play_excercise_event ON play_excercise_event.id = play_excercise_price.eid
             WHERE
                 play_order_info.user_id = ?
             AND play_order_info.order_status = 1
             AND play_order_info.order_type = 3
             AND play_order_info.pay_status in (0,1,2,5,7)
             AND play_excercise_code.use_free_coupon > 0
             AND (play_excercise_event.start_time > ? AND play_excercise_event.start_time < ?)
            ",
            array($uid, $start_time, $end_time)
        )->current()->c;

        return $data_count;
    }

    public function getCountPriceFreeBuyInOrder ($order_id, $pid) {
        $pdo = M::getAdapter();
        $data_count = $pdo->query(
            "
             SELECT
                count(*) AS c
             FROM
                play_order_info
             LEFT JOIN play_excercise_code  ON play_excercise_code.order_sn = play_order_info.order_sn
             LEFT JOIN play_excercise_price ON play_excercise_price.id = play_excercise_code.pid
             WHERE
                 play_order_info.order_sn = ?
             AND play_order_info.order_status = 1
             AND play_order_info.order_type = 3
             AND play_excercise_price.id = ?
             AND play_excercise_code.use_free_coupon > 0
            ",
            array($order_id, $pid)
        )->current()->c;

        return $data_count;
    }

    /**
     * 更新活动状态数据
     * @param $bid
     * @return bool
     */
    static public function updateMax($bid) {
        $time = time();
        $st = M::getPlayExcerciseEventTable()->fetchLimit(0, 1, [], ['bid' => $bid, 'sell_status > ?' => 0, 'customize' => 0, 'sell_status!=3 and join_number<perfect_number and over_time>' . time()], ['start_time' => 'asc'])->current();
        if (!$st) {
            return false;
        }
        $et = M::getPlayExcerciseEventTable()->fetchLimit(0, 1, [], ['bid' => $bid, 'sell_status > ?' => 0, 'customize' => 0, 'sell_status!=3 and join_number<perfect_number and over_time>' . time()], ['end_time' => 'desc'])->current();
        $it = M::getPlayExcerciseEventTable()->fetchLimit(0, 1, [], ['bid' => $bid, 'sell_status > ?' => 0, 'customize' => 0], ['id' => 'desc'])->current();

        $count = M::getPlayExcerciseEventTable()->fetchAll(['bid' => $bid, 'sell_status >= ?' => 1, 'customize' => 0, "(join_number<perfect_number and over_time>{$time} and sell_status!=3)"])->count();

        $shop = M::getPlayShopTable()->get(['shop_id' => $it->shop_id]);
        $busniess_circle = CouponCache::getBusniessCircle($shop->busniess_circle);

        $data['max_start_time'] = $st->start_time;
        $data['min_end_time'] = $et->end_time;
        $data['circle'] = $busniess_circle;
        $data['all_number'] = $count;
        return M::getPlayExcerciseBaseTable()->update($data, ['id' => $bid]);

    }

    public static function getKidsplayList ($param, $start = 0, $limit = 1, $flag_count = 0) {
        $pdo = M::getAdapter();
        $sql_where = '';
        if ($param['city']) {
            if (!empty($sql_where)) {
                $sql_where .= " AND ";
            }
            $sql_where .= " play_excercise_base.release_status > -1 and play_excercise_base.city = '" . $param['city'] . "' ";
        }

        if ($param['name']) {
            if (!empty($sql_where)) {
                $sql_where .= " AND ";
            }
            $sql_where .= " play_excercise_base.name like '%" . $param['name'] . "%'";
        }

        if ($param['bid']) {
            if (!empty($sql_where)) {
                $sql_where .= " AND ";
            }
            $sql_where .= " play_excercise_base.id = " . $param['bid'] . " ";
        }

        if ($param['eid']) {
            if (!empty($sql_where)) {
                $sql_where .= " AND ";
            }
            $sql_where .= " play_excercise_base.eid = " . $param['eid'] . " ";
        }

        if ($param['start_time']) {
            if (!empty($sql_where)) {
                $sql_where .= " AND ";
            }
            $sql_where .= " play_excercise_event.start_time >= " . strtotime($param['start_time']) . " ";
        }

        if ($param['end_time']) {
            if (!empty($sql_where)) {
                $sql_where .= " AND ";
            }
            $sql_where .= " play_excercise_event.end_time <= " . (strtotime($param['start_time']) + 3600 * 24) . " ";
        }

        // 筛选免费玩的场次
        if ($param['free_coupon']) {
            if (!empty($sql_where)) {
                $sql_where .= " AND ";
            }
            $sql_where .= " play_excercise_base.free_coupon_event_max > 0 ";
        }

        if ($flag_count == 1) {
            $sql_count = "
                      SELECT play_excercise_base.id 
                      FROM play_excercise_base 
                      LEFT JOIN play_excercise_event ON play_excercise_base.id = play_excercise_event.bid 
                      WHERE {$sql_where} 
                      GROUP BY play_excercise_base.id 
                     ";

            $data_count = $pdo->query($sql_count, array())->count();

            return $data_count;
        } else {
            $sql = "
                SELECT
                    play_excercise_base.*,
                    play_excercise_event.start_time,
                    play_excercise_event.end_time
                FROM
                    play_excercise_base
                LEFT JOIN play_excercise_event ON play_excercise_base.id = play_excercise_event.bid
                WHERE
                    {$sql_where}
                GROUP BY play_excercise_base.id
                ORDER BY
                    play_excercise_base.id  DESC
                LIMIT {$start}, {$limit}
               ";

            $data_return = $pdo->query($sql, array())->toArray();

            return $data_return;
        }
    }

    public static function getEventList ($param, $start = 0, $limit = 1, $flag_count = 0) {
        $pdo = M::getAdapter();
        $sql_where = '';
        if ($param['city']) {
            if (!empty($sql_where)) {
                $sql_where .= " AND ";
            }
            $sql_where .= " play_excercise_base.release_status > -1 and play_excercise_base.city = '" . $param['city'] . "' ";
        }

        if ($param['name']) {
            if (!empty($sql_where)) {
                $sql_where .= " AND ";
            }
            $sql_where .= " play_excercise_base.name like '%" . $param['name'] . "%'";
        }

        if ($param['bid']) {
            if (!empty($sql_where)) {
                $sql_where .= " AND ";
            }
            $sql_where .= " play_excercise_base.id = " . $param['bid'] . " ";
        }

        if ($param['eid']) {
            if (!empty($sql_where)) {
                $sql_where .= " AND ";
            }
            $sql_where .= " play_excercise_event.id = " . $param['eid'] . " ";
        }

        if ($param['start_time']) {
            if (!empty($sql_where)) {
                $sql_where .= " AND ";
            }
            $sql_where .= " play_excercise_event.start_time >= " . strtotime($param['start_time']) . " ";
        }

        if ($param['end_time']) {
            if (!empty($sql_where)) {
                $sql_where .= " AND ";
            }
            $sql_where .= " play_excercise_event.end_time <= " . (strtotime($param['start_time']) + 3600 * 24) . " ";
        }

        // 筛选免费玩的场次
        if ($param['free_coupon']) {
            if (!empty($sql_where)) {
                $sql_where .= " AND ";
            }
            $sql_where .= " play_excercise_price.free_coupon_need_count > 0 ";
        }

        if ($flag_count == 1) {
            $sql_count = "
                      SELECT 
                          play_excercise_event.id 
                      FROM play_excercise_event 
                      LEFT JOIN play_excercise_base ON play_excercise_base.id = play_excercise_event.bid
                      LEFT JOIN play_excercise_price ON play_excercise_price.eid = play_excercise_event.id
                      WHERE {$sql_where} 
                      GROUP BY play_excercise_event.id 
                     ";

            $data_count = $pdo->query($sql_count, array())->count();

            return $data_count;
        } else {
            $sql = "
                SELECT 
                    play_excercise_event.id as id,
                    play_excercise_base.id as bid,
                    play_excercise_base.name,
                    play_excercise_event.no,
                    play_excercise_event.start_time,
                    play_excercise_event.end_time,
                    play_excercise_price.free_coupon_need_count,
                    play_excercise_price.free_coupon_join_count,                    
                    play_excercise_event.sell_status,                   
                    play_excercise_event.open_time,                   
                    play_excercise_event.over_time,                   
                    play_excercise_event.join_number,                   
                    play_excercise_event.perfect_number                 
                FROM play_excercise_event 
                LEFT JOIN play_excercise_base ON play_excercise_base.id = play_excercise_event.bid
                LEFT JOIN play_excercise_price ON play_excercise_price.eid = play_excercise_event.id
                WHERE {$sql_where} 
                GROUP BY play_excercise_event.id
                ORDER BY
                    play_excercise_event.id DESC
                LIMIT {$start}, {$limit}
               ";

            $data_return = $pdo->query($sql, array())->toArray();

            return $data_return;
        }
    }

    static public function cancelFreePrice ($param) {
        $pdo = M::getAdapter();

        $data_price_count = M::getPlayExcercisePriceTable()->fetchCount(array('free_coupon_need_count > 0', 'free_coupon_max_count > free_coupon_join_count'));

        if ($data_price_count > 0) {
            $data_event_count = $data_price_count > 0 ? 1 : 0;
        }

        $sql         = " UPDATE play_excercise_price SET free_coupon_need_count = 0 WHERE eid = ? ";

        $data_return = $pdo->query($sql, array($param['eid']))->count();

        if ($data_return > 0) {
            $data_event                    = M::getPlayExcerciseEventTable()->get(array('id' => $param['eid']));
            $sql                           = " UPDATE play_excercise_base SET free_coupon_event_max = free_coupon_event_max - 1 where id = ? AND free_coupon_event_max > 0 ";
            $data_result_update_free_max   = $pdo->query($sql, array($data_event['bid']))->count();

            $sql                           = " UPDATE play_excercise_base SET free_coupon_event_count = free_coupon_event_count - ? where id = ? AND free_coupon_event_count > 0 ";
            $data_result_update_free_count = $pdo->query($sql, array($data_event_count, $data_event['bid']))->count();

            $data_free_count               = self::getFreeCouponEventCount($data_event['bid']);

            if ($data_free_count <= 0) {
                $sql = " UPDATE play_index_block SET status = 0 WHERE link_type = 9 AND `type` = 16 AND link_id = ? AND block_city = ? ";
                $data_result_update_index_block = $pdo->query($sql, array($data_event['bid'], $param['city']))->count();
            }
        }

        return $data_return;
    }

    static public function getKidsplayBaseById ($base_id) {
        return M::getPlayExcerciseBaseTable()->get(array('id' => $base_id));
    }
}



