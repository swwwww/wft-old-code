<?php

namespace Deyi\GetCacheData;

use Application\Module;
use Deyi\BaseController;
use library\Service\System\Cache\RedCache;

class CouponCache
{
    use BaseController;

    private static $_instance = null;

    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }


    /**
     * 获取 hot or new 标识
     * @param $id
     * @param $city
     * @return array
     */
    private function _getCouponLabels($id, $hot_number = 0, $city = 'WH')
    {
        //已经开始  开始时间离当前时间最近  显示new三天
        $labels = RedCache::fromCacheData("D:CouponLabels:" . $id, function () use ($hot_number, $city, $id) {
            $labels = array();
            $db = $this->_getAdapter();
            $res = $db->query("select * from play_game_info WHERE gid=? AND status=1 AND up_time<?  AND up_time>? ORDER  BY up_time DESC limit 1", array($id, time(), time() - 3600 * 24 * 3))->current();

            if ($res) {
                $labels[] = 'NEW';
            } else {
                //各分类中周销售排名前百分之二十的商品，显示HOT的标识

                $l = $this->gethotData($id, $city);

                //取出当前商品所有的分类
                $g_ls = array();
                $g_res = $db->query("select lid from play_label_linker WHERE object_id=? AND link_type=2", array($id));


                foreach ($g_res as $v) {
                    $g_ls[] = $v->lid;
                }



                //3.遍历
                foreach ($l as $v) {
                    //如果当前所属分类 包含在内
                    if (in_array($v['l_id'], $g_ls)) {

                        if ($v['min_hot_num'] == 0) {
                            if($hot_number > 0){
                                $labels[] = 'HOT';
                                break;
                            }
                        } elseif ($hot_number >= $v['min_hot_num']) {

                            $labels[] = 'HOT';
                            break;
                        }
                        
                        if ($v['min_hot_num'] == 0 and $hot_number > 0) {

                            $labels[] = 'HOT';
                            break;
                        } elseif ($hot_number > $v['min_hot_num']) {

                            $labels[] = 'HOT';
                            break;
                        }
                    }
                }
            }
            return $labels;
        }, 3600 * 24, true);
        return $labels;
    }

    //获取所有分类热门时必须达到的最小数量
    public function gethotData($id, $city)
    {
        return RedCache::fromCacheData("D:labels_min_number", function () use ($id, $city) {
            $db = $this->_getAdapter();
            $l = array();
            //0.取出所有分类
            $labels = $db->query("select *  from play_label WHERE label_type>=2  AND status>1", array());
            foreach ($labels as $ls) {
                //1.统计各分类中商品总数
                $g_count = $db->query("SELECT  count(*) as c FROM 	play_label_linker WHERE link_type = 2 AND lid=?", array($ls->id))->current()->c;
                $n = round($g_count * 0.2);

                //2.获取所有属于 20% 包含的id列表,取最低的 hot_number
                $res = $db->query("select * from play_organizer_game  WHERE `status`=1  AND city=? ORDER BY hot_number DESC  limit {$n}", array($city))->toArray();


                //取最后一条 hot_number
                $a = count($res);
                $l[] = array(
                    'l_id' => $ls->id,
                    'min_hot_num' => $res[$a - 1]['hot_number'], //只要大于这个数
                );
            }
            return $l;
        }, 3600 * 24, true);
    }


    /**从缓存获取星星数  //使用在圈子列表等
     * @param $id
     * @param int $type 1 coupon 2 shop
     * @return bool|int|string
     */
    private function _getStar($id, $type = 1)
    {

        $key = "D:coupon_star:{$type}:{$id}";

        $star = RedCache::get($key);
        if ($star) {
            return $star;
        } else {
            if ($type == 1) {

                $res = $this->_getMdbGradingRecords()->findOne(array('object_id' => $id, 'type' => 2));
                if ($res and $res['total_score']) {
                    $star = ceil($res['total_score'] / $res['total_number']);
                } else {
                    $star = 0;
                }
                RedCache::set($key, $star, 3600 * 24);
            } else {
                $res = $this->_getMdbGradingRecords()->findOne(array('object_id' => $id, 'type' => 3));
                if ($res and $res['total_score']) {
                    $star = ceil($res['total_score'] / $res['total_number']);
                } else {
                    $star = 0;
                }
                RedCache::set($key, $star, 3600 * 24);
            }
        }
        return $star;

    }


    //从缓存获取商圈  =>如 "汉阳 钟家村"
    private function _getBusniessCircle($busniess_circle_id, $city = "WH")
    {

        $rid = $busniess_circle_id;

        if ($rid % 100000000 === 0) {
            $start_id = 0;
        } elseif ($rid % 1000000 === 0) {
            $start_id = floor($busniess_circle_id / 100000000) * 100000000;
        } elseif ($rid % 10000 === 0) {
            $start_id = floor($busniess_circle_id / 1000000) * 1000000;
        } elseif ($rid % 100 === 0) {
            $start_id = floor($busniess_circle_id / 10000) * 10000;
        } else {
            $start_id = floor($busniess_circle_id / 100) * 100;
        }

        $data = RedCache::fromCacheData('D:bzns_cc:' . $busniess_circle_id, function () use ($start_id, $busniess_circle_id) {
            $data = $this->_getPlayRegionTable()->fetchLimit(0, 2, array(), array('rid' => [$start_id, $busniess_circle_id]))->toArray();
            if (isset($data[0])) {
                return $data;
            } else {
                return array();
            }
        }, 3600 * 24 * 7, true);

        $name = '';
        foreach ($data as $v) {
            if ($v['rid'] == $start_id) {
                $name = $v['name'];
            }
            if ($v['rid'] == $busniess_circle_id && ($start_id != $busniess_circle_id)) {
                $name = $name . " " . $v['name'];
            }
        }
        return $name;

    }

    /**
     * 获取现金券描述
     * @param $coupon_id
     */
    private function _getCouponDesc($coupon_id)
    {
        $key = "D:coupondesc:{$coupon_id}";

        $cache_data = RedCache::get($key);
        RedCache::del($key);
        if ($cache_data !== false) {
            return $cache_data;
        }


        $coupon_data = $this->_getCashCouponTable()->get(array('id' => $coupon_id));

        if (!$coupon_data) {
            return false;
        }

        $desc = '';
        /**
         * 1.【普通商品通用】；2.【遛娃学院活动通用】；3.【指定商品使用】；
         * 4.【指定遛娃学院活动部分场次可用】：下面附带现金券对应【活动名称】和【场
         */
        if ((int)$coupon_data->range === 1) {
            $desc = '指定商品使用';
        } elseif ((int)$coupon_data->range === 2) {
            //部分类别
            $adapter = $this->_getAdapter();
            $sql = "SELECT tag_name FROM play_cashcoupon_good_link left join play_cash_coupon ON (play_cash_coupon.id
= play_cashcoupon_good_link.cid and play_cashcoupon_good_link.object_type = 2)
 left join play_label on (play_label.id = play_cashcoupon_good_link.object_id and play_cashcoupon_good_link.object_type = 2)
 where play_cashcoupon_good_link.cid = ? and play_label.status = 2;";
            $data = $adapter->query($sql, array($coupon_id))->toArray();
            if ($data) {
                $tags = [];
                foreach ($data as $d) {
                    if (in_array($d['tag_name'], $tags)) {
                        continue;
                    }
                    $tags[] = $d['tag_name'];
                }
                $desc = implode(',', $tags);
            }
            $desc = "所有的{$desc}使用，特例商品除外";

        } elseif ((int)$coupon_data->range === 3) {
            $desc = '遛娃学院活动通用，特例活动除外';
        } elseif ((int)$coupon_data->range === 4) {
            $desc = '遛娃学院活动部分场次可用';
        } else {
            $desc = '普通商品通用，特例商品除外';
        }

        $status = RedCache::setnx($key, $desc, 604800); // 缓存7日

        if ($status) {
            return $desc;
        } else {
            $cache_data = RedCache::get($key);
            return $cache_data;
        }
    }


    /**
     *  获取卡券详情
     * @param $coupon_id
     * @return array
     */
    private function _getCouponData($coupon_id)
    {
        $key = "D:coupon:{$coupon_id}";

        $cache_data = RedCache::get($key);
        if ($cache_data !== false) {
            return json_decode($cache_data, true);
        }


        $coupon_data = $this->_getPlayOrganizerGameTable()->get(array('id' => $coupon_id));
        if (!$coupon_data) {
            return false;
        }

        $coupon_data = iterator_to_array($coupon_data);
        $status = RedCache::setnx($key, json_encode($coupon_data, JSON_UNESCAPED_UNICODE), 604800); // 缓存7日

        if ($status) {
            return $coupon_data;
        } else {
            $cache_data = RedCache::get($key);
            return json_decode($cache_data, true);
        }

    }

    /**
     *  获取活动可以使用的有效场次
     * @param $coupon_id
     * @return array
     */
    private function _getCouponEvents($coupon_id)
    {
        $events = $this->_getCashCouponGoodTable()->fetchLimit(0, 20, [], ['cid' => $coupon_id, 'object_type' => 4])->toArray();
        $eids = [];
        foreach ($events as $e) {
            $eids[] = $e['object_id'];
        }

        if (!$eids) {
            $eids = 0;
        }

        $event = $this->_getPlayExcerciseEventTable()->getEventList(0, 20, [], ['play_excercise_event.id' => $eids, 'end_time < ?' => time()]);
        $e_arr = [];
        if ($event) {

            foreach ($event as $ev) {
                $e_arr[] = '仅限' . $ev['name'] . '&nbsp;' . date('Y-m-d' . $ev['start_time']) . '&nbsp' . date('H:i' . $ev['start_time']) . '&nbsp' . date('H:i' . $ev['end_time']);
            }
        }
        return $e_arr;
    }


    /**
     *  获取卡券套系数据
     * @param $coupon_id
     * @return array
     */
    private function _getGameInfos($coupon_id)
    {
        $key = "D:gameinfos:{$coupon_id}";
        $cache_data = RedCache::get($key);
        if ($cache_data !== false) {
            return json_decode($cache_data, true);
        }
        //获取数据 二维数组
        $game_info = $this->_getPlayGameInfoTable()->fetchAll(array('gid' => $coupon_id));
        if (!$game_info) {
            return false;
        }
        $game_info = iterator_to_array($game_info);
        $status = RedCache::setnx($key, json_encode($game_info, JSON_UNESCAPED_UNICODE), 604800); // 缓存7日

        if ($status) {
            return $game_info;
        } else {
            $cache_data = RedCache::get($key);
            return json_decode($cache_data, true);
        }

    }


    /**
     * 获取套系已购买数量
     * @param $order_id
     * @return int
     */
    private function _getOrderBuyNumber($order_id)
    {

        $key = "D:OrderBuyNumber:{$order_id}";
        $cache_data = RedCache::get($key);
        if ($cache_data !== false) {
            return $cache_data;
        }
        $game_info = $this->_getPlayGameInfoTable()->get(array('id' => $order_id));
        if (!$game_info) {
            return false;
        }
        $buy_number = $game_info->buy;
        $status = RedCache::setnx($key, $buy_number, 2419200);//缓存28日
        if ($status == 1) {
            return $buy_number;
        } else {
            return RedCache::get($key);
        }
    }


    /**
     * 获取套系总数
     * @param $order_id
     * @return int
     */
    private function _getOrderTotalNumber($order_id)
    {
        $key = "D:OrderTotalNumber:{$order_id}";
        $cache_data = RedCache::get($key);
        if ($cache_data !== false) {
            return $cache_data;
        }
        $game_info = $this->_getPlayGameInfoTable()->get(array('id' => $order_id));
        if (!$game_info) {
            return false;
        }
        $number = $game_info->total_num;
        $status = RedCache::setnx($key, $number, 2419200);//缓存28日
        if ($status == 1) {
            return $number;
        } else {
            return RedCache::get($key);
        }
    }

    /**
     * 获取活动id
     * @param $eid
     * @return object
     */
    private function _getBidByEid($eid)
    {
        $bid = (int)RedCache::fromCacheData('D:gbbe:' . $eid, function () use ($eid) {
            $data = $this->_getPlayExcerciseEventTable()->get(['id' => $eid]);
            return $data->bid;
        }, 24 * 3600 * 7, true);
        return $bid;
    }

    /**
     * 购买商品,原子操作
     * @param $order_id
     * @return bool
     */
    private function _OrderNumberBuy($order_id, $buy_number)
    {

        $buy = $this->_getOrderBuyNumber($order_id); //已购买
        $total = $this->_getOrderTotalNumber($order_id); //总数

        $key = "D:OrderBuyNumber:{$order_id}";

        $new_number = RedCache::incrby($key, $buy + $buy_number);

        if ($new_number <= $total) {
            return true;
        } else {
            //还原
            RedCache::decrby($key, $buy + $buy_number);
            return false;
        }

    }

    //判断现金券是否有效
    public function isvalid($id){

        if(!$id){
            return false;
        }
RedCache::del('C:isv:' . $id);
        return (int)RedCache::fromCacheData('C:isv:' . $id, function () use ($id) {
            $adapter = $this->_getAdapter();
            $data = $adapter->query("SELECT * FROM play_welfare_cash WHERE `id` = ?
     and status  = 2 and total_num > give_num ", array((int)$id))->current();
            $data = $adapter->query("SELECT * FROM play_cash_coupon WHERE `id` = ?
     and status  = 1 and is_close = 0 order by id desc", array((int)$data->cash_coupon_id))->current();

            if (!$data) {
                return false;
            } else {
                return (($data->residue > 0) and ($data->end_time > time()));
            }

        }, 60 , true);


    }

    private function _get_qualify_type()
    {
        return array(
            1 => '用户兑换',
            2 => '邀约',
            3 => '参加活动',
            4 => '注册'
        );
    }

    private static function _getInstance()
    {
        if (NULL === static::$_instance) {
            static::$_instance = new CouponCache();
        }
        return static::$_instance;
    }

    public static function get_qualify_type()
    {
        return self::_getInstance()->_get_qualify_type();
    }

    public static function getCouponInfo($coupon_id)
    {
        return self::_getInstance()->_getCouponData($coupon_id);
    }

    public static function getCouponDesc($coupon_id)
    {
        return self::_getInstance()->_getCouponDesc($coupon_id);
    }

    public static function getCouponEvents($coupon_id)
    {
        return self::_getInstance()->_getCouponEvents($coupon_id);
    }


    public static function getGameInfos($coupon_id)
    {
        return self::_getInstance()->_getGameInfos($coupon_id);
    }

    public static function getBusniessCircle($busniess_circle_id, $city = "WH")
    {
        return self::_getInstance()->_getBusniessCircle($busniess_circle_id);
    }

    public static function getBidByEid($eid)
    {
        return self::_getInstance()->_getBidByEid($eid);
    }

    public static function OrderNumberBuy($coupon_id)
    {
        return self::_getInstance()->_OrderNumberBuy($coupon_id);
    }

    public static function getCouponLabels($id, $hot_number = 0, $city = 'WH')
    {
        return self::_getInstance()->_getCouponLabels($id, $hot_number, $city);
    }

    /**
     * @param $coupon_id
     * @return int
     */

    /**
     * 获取星星数
     * @param $id
     * @param $type 1coupon 2 shop
     * @return int
     */
    public static function getStar($id, $type = 1)
    {
        return self::_getInstance()->_getStar($id, $type);
    }


}



