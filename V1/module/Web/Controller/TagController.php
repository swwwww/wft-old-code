<?php

namespace Web\Controller;

use Deyi\BaseController;
use Deyi\GetCacheData\GoodCache;
use Deyi\GetCacheData\PlaceCache;
use Deyi\JsonResponse;
use Deyi\WeiXinFun;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class TagController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    public function indexAction()
    {
        $id = (int)$this->getQuery('id');

        $tagData = $this->_getPlayLabelTable()->get(array('id' => $id, 'city' => 'WH'));

        if (!$tagData) {
            return $this->jsonResponseError('该标签 不存在');
        }

        //标签详情
        $res = array(
            'title' => $tagData->tag_name,
            'cover' => $tagData->cover ? $this->_getConfig()['url'] . $tagData->cover : '',
            'description' => $tagData->description,
            'coupon_list' => array(),
            'place_list' => array(),
        );

        //标签下面关联的游玩地
        $placeData = $this->_getPlayLabelLinkerTable()->getShopList(0, 20, $columns = array(), $where = array('lid' => $id), $order = array('sort' => 'desc', 'lid' => 'desc'));
        foreach ($placeData as $value) {
            $res['place_list'][] = array(
                'id' => $value->shop_id,
                'title' => $value->shop_name,
                'cover' => $value->cover ? $this->_getConfig()['url'] . $value->cover : '',
                'description' => $value->words ? $value->words : $value->editor_word,
                'price' => $value->link_price ? $value->link_price : (float)$value->reference_price,
                'coupon_have' => $value->reticket,
            );
        }

        //标签下面关联的所有卡券
        $mer = implode(',', json_decode($tagData->object_id));
        if (count(json_decode($tagData->object_id))) {
            $coupon_where = array(
                "play_coupons_linker.shop_id in ($mer)",
                'play_coupons.coupon_status = ?' => 1,
                'play_coupons.coupon_starttime <= ?' => time(),
                '(play_coupons.coupon_total - play_coupons.coupon_buy) > ?' => 0,
                'play_coupons.coupon_endtime >=?' => time(),
            );
            $couponData = $this->_getPlayCouponsLinkerTable()->getAdminTagCoupon(0, 20, $columns = array(), $coupon_where, $order = array());
            foreach ($couponData as $val) {
                $res['coupon_list'][] = array(
                    'id' => $val->coupon_id,
                    'name' => $val->coupon_name,
                    'cover' => $val->coupon_thumb ? $this->_getConfig()['url'] . $val->coupon_thumb : $this->_getConfig()['url'] . $val->coupon_cover . '.thumb.jpg',
                    'editor_word' => $val->editor_word,
                    'price' => $val->coupon_price,
                    'discount' => round($val->coupon_price / $val->coupon_originprice * 10, 1),
                );
            }
        }

        $vm = new viewModel(array(
            'res' => $res,
        ));
        $vm->setTerminal(true);
        return $vm;
    }

    //分类接口
    public function categoryAction()
    {
        $url = $this->_getConfig()['url'] . "/web/tag/category";
        $weixin = new WeiXinFun($this->getwxConfig());
        $toUrl = $weixin->getAuthorUrl($url, 'snsapi_userinfo');

        //分享
        $jswxconfig = $this->getShareInfoAction()[1];
        $share = array(
            'img'=>$this->_getConfig()['url'].'/images/80.png',
            'title'=>'【玩翻天】孩子们的游玩管家',
            'desc'=>'我发现了一个家长必备的遛娃神器！告别无趣，拯救宅神！快来带娃玩翻天！',
            'link'=>$this->_getConfig()['url'].'/web/wappay/nindex',
        );

        $vm = new ViewModel([
            'authorUrl'=>$toUrl,
            'share_type'=>'nindex',
            'share_id'=>0,
            'jsconfig'=>$jswxconfig,
            'share'=>$share,
        ]);
        $vm->setTerminal(true);
        return $vm;
    }

    //新专题
    public function infoAction()
    {
        $url = $this->_getConfig()['url'] . "/web/tag/info";
        $weixin = new WeiXinFun($this->getwxConfig());
        $toUrl = $weixin->getAuthorUrl($url, 'snsapi_userinfo');
        $id = (int)$this->getQuery("id");
        $title = $this->_getPlayActivityTable()->get(array('play_activity.id' => $id))->ac_name;
        return array('title'=>$title,'authorUrl'=>$toUrl);
    }

    //混合 游玩地优先 获取卡券
    public function getPlaceCoupon($id, $start, $limit, $order, $uid) {

        $good_ids_where = "play_activity_coupon.aid = {$id} AND play_activity_coupon.type = 'place'";
        $good_ids_sql = "SELECT
play_game_info.gid
FROM
play_activity_coupon
RIGHT JOIN play_game_info ON play_activity_coupon.cid = play_game_info.shop_id
WHERE
{$good_ids_where}
";
        $good_ids = $this->query($good_ids_sql);

        if (!$good_ids->count()) {
            return array();
        }

        $m = '(';
        $ker = array();
        foreach ($good_ids as $k=>$good) {
            if (!in_array($good['gid'], $ker)) {
                if (!$k) {
                    $m = $m.$good['gid'];
                } else {
                    $m = $m.', '.$good['gid'];
                }
            }
            $ker[] = $good['gid'];
        }
        $m = $m.')';

        $timer = time();
        $where = "play_organizer_game.status > 0 AND  play_organizer_game.end_time >= {$timer} AND play_organizer_game.start_time <= {$timer} AND play_organizer_game.id in {$m}";

        if ($order != 'for_me') {
            $order = "play_organizer_game.buy_num DESC";
        } else {
            if (!$uid) {
                //return array();
            }
            // 搜索出年龄
            $order = "play_organizer_game.buy_num DESC";
        }

        $sql = "SELECT
play_organizer_game.title,
play_organizer_game.editor_talk,
play_organizer_game.id,
play_organizer_game.low_price,
play_organizer_game.low_money,
play_organizer_game.ticket_num,
play_organizer_game.buy_num,
play_organizer_game.shop_addr,
play_organizer_game.down_time,
play_organizer_game.cover,
play_organizer_game.foot_time,
play_organizer_game.is_together,
play_organizer_game.g_buy
FROM
play_organizer_game
WHERE
{$where}
ORDER BY
{$order}
LIMIT $start, $limit
";

        $res = $this->query($sql);
        $data = array();
        foreach ($res as $val) {
            $data[] = array(
                'good_id' => $val['id'],
                'good_name' => $val['title'],
                'editor_word' => $val['editor_talk'],
                'good_price' => $val['low_price'],
                'good_cover' => $this->_getConfig()['url'].$val['cover'],
                'discount' => ($val['is_together'] == 1) ? round($val['low_price'] / $val['low_money'] * 10, 1) : 10,
                'good_have' => (($val['ticket_num'] >  $val['buy_num']) && $val['foot_time'] > $timer && $val['is_together'] == 1) ? 1 : 0,
                'circle' => $val['shop_addr'],
                'g_buy' => (($val['down_time'] - $timer) > 86400) ? $val['g_buy'] : '0',
            );
        }
        return $data;
    }

    //混合 卡券优先
    public function getCouponPlace($id, $start, $limit, $order, $addr_x, $addr_y) {

        // 先算出 shopid  再算吧

        // 活动筛选条件  状态 上架的商品
        $timer = time();
        $where = "play_activity_coupon.aid = {$id} AND play_activity_coupon.type = 'game' AND play_shop.shop_status >= 0 AND play_organizer_game.status > 0 AND play_organizer_game.start_time <= {$timer} AND play_organizer_game.end_time >= {$timer}";
        if ($order != 'close') {
            if ($order == 'hot') {
                $order = 'play_shop.hot_count DESC';
            } else {
                $order = 'play_shop.good_num DESC';
            }

            $sql = "SELECT
play_region.`name`,
play_shop.shop_id,
play_shop.shop_name,
play_shop.cover,
play_shop.reference_price,
play_shop.editor_word,
play_shop.good_num
FROM
play_activity_coupon
LEFT JOIN play_organizer_game ON play_activity_coupon.cid = play_organizer_game.id
RIGHT JOIN play_game_info ON play_game_info.gid = play_organizer_game.id
LEFT JOIN play_shop ON play_shop.shop_id = play_game_info.shop_id
LEFT JOIN play_region ON play_shop.busniess_circle = play_region.rid
WHERE
{$where}
GROUP BY
play_shop.shop_id
ORDER BY
$order
LIMIT $start, $limit";

            $res = $this->query($sql);
            $data = array();
            foreach ($res as $val) {
                $data[] = array(
                    'place_id' => $val['shop_id'],
                    'place_name' => $val['shop_name'],
                    'place_cover' => $this->_getConfig()['url'].$val['cover'],
                    'place_price' => $val['reference_price'],
                    'circle' => $val['name'],
                    'place_have' => $val['good_num'],
                    'editor_word' => $val['editor_word'],
                );
            }
            return $data;


        } else { //离我最近
            if ($addr_x && $addr_y) {
                $squares = $this->returnSquarePoint($addr_x, $addr_y, 60);
                $where = $where. " AND ((play_shop.addr_x<>0 and play_shop.addr_x>{$squares['right-bottom']['addr_x']} and play_shop.addr_x<{$squares['left-top']['addr_x']} and play_shop.addr_y<{$squares['left-top']['addr_y']} and play_shop.addr_y>{$squares['right-bottom']['addr_y']}))";
            }
            $sql = "SELECT
play_region.`name`,
play_shop.shop_id,
play_shop.shop_name,
play_shop.cover,
play_shop.reference_price,
play_shop.editor_word,
play_shop.good_num
FROM
play_activity_coupon
LEFT JOIN play_organizer_game ON play_activity_coupon.cid = play_organizer_game.id AND play_organizer_game.foot_time >= {$timer} AND play_organizer_game.is_together = 1 AND play_organizer_game.ticket_num > play_organizer_game.buy_num
RIGHT JOIN play_game_info ON play_game_info.gid = play_organizer_game.id
LEFT JOIN play_shop ON play_shop.shop_id = play_game_info.shop_id
LEFT JOIN play_region ON play_shop.busniess_circle = play_region.rid
WHERE
$where
GROUP BY
play_shop.shop_id";

            $res = $this->query($sql);
            $data = $this->OrderAddress($res, $addr_x, $addr_y, $start, $limit);
            return $data;

        }

    }


    //仅商品
    public function getCoupon($id, $start, $limit, $or, $uid) {

        // 活动筛选条件  状态 上架的商品
        $timer = time();
        $where = "play_activity_coupon.aid = $id AND play_activity_coupon.type = 'game' AND play_organizer_game.status > 0 AND play_organizer_game.start_time <= {$timer} AND play_organizer_game.end_time >= {$timer}";

        if ($or == 'for_me') {
            if (!$uid) {
                //return array();
            }
            $order = "play_organizer_game.buy_num DESC";
        } elseif ($or == 'hot') {
            $order = "play_organizer_game.buy_num DESC";
        } elseif ($or == 'ticket') {
            $order = "play_organizer_game.buy_num DESC";
        } else {
            $order = "play_organizer_game.buy_num DESC";
        }


        $sql = "SELECT
play_organizer_game.title,
play_organizer_game.editor_talk,
play_organizer_game.id,
play_organizer_game.low_price,
play_organizer_game.low_money,
play_organizer_game.ticket_num,
play_organizer_game.buy_num,
play_organizer_game.shop_addr,
play_organizer_game.cover,
play_organizer_game.down_time,
play_organizer_game.foot_time,
play_organizer_game.is_together,
play_organizer_game.g_buy
FROM
play_activity_coupon
LEFT JOIN play_organizer_game ON play_activity_coupon.cid = play_organizer_game.id
WHERE
{$where}
ORDER BY
{$order}
LIMIT $start, $limit";

        $res = $this->query($sql);
        $data = array();
        foreach ($res as $val) {
            if ($or == 'ticket') {
                if (($val['ticket_num'] >  $val['buy_num']) && $val['foot_time'] > $timer && $val['is_together'] == 1) {
                    array_unshift($data, array(
                        'good_id' => $val['id'],
                        'good_name' => $val['title'],
                        'editor_word' => $val['editor_talk'],
                        'good_price' => $val['low_price'],
                        'good_cover' => $this->_getConfig()['url'].$val['cover'],
                        'discount' => ($val['is_together'] == 1) ? (round($val['low_price'] / $val['low_money'] * 10, 1)) : 10,
                        'good_have' => (($val['ticket_num'] >  $val['buy_num']) && $val['foot_time'] > $timer && $val['is_together'] == 1) ? 1 : 0,
                        'circle' => $val['shop_addr'],
                        'g_buy' => (($val['down_time'] - $timer) > 86400) ? $val['g_buy'] : '0',
                    ));
                } else {
                    array_push($data, array(
                        'good_id' => $val['id'],
                        'good_name' => $val['title'],
                        'editor_word' => $val['editor_talk'],
                        'good_price' => $val['low_price'],
                        'good_cover' => $this->_getConfig()['url'].$val['cover'],
                        'discount' => ($val['is_together'] == 1) ? (round($val['low_price'] / $val['low_money'] * 10, 1)) : 10,
                        'good_have' => (($val['ticket_num'] >  $val['buy_num']) && $val['foot_time'] > $timer && $val['is_together'] == 1) ? 1 : 0,
                        'circle' => $val['shop_addr'],
                        'g_buy' => (($val['down_time'] - $timer) > 86400) ? $val['g_buy'] : '0',
                    ));
                }

            } else {
                $data[] = array(
                    'good_id' => $val['id'],
                    'good_name' => $val['title'],
                    'editor_word' => $val['editor_talk'],
                    'good_price' => $val['low_price'],
                    'good_cover' => $this->_getConfig()['url'].$val['cover'],
                    'discount' => ($val['is_together'] == 1) ? (round($val['low_price'] / $val['low_money'] * 10, 1)) : 10,
                    'good_have' => (($val['ticket_num'] >  $val['buy_num']) && $val['foot_time'] > $timer && $val['is_together'] == 1) ? 1 : 0,
                    'circle' => $val['shop_addr'],
                    'g_buy' => (($val['down_time'] - $timer) > 86400) ? $val['g_buy'] : '0',
                );
            }
        }

        return $data;
    }


    //仅游玩地
    public function getPlace($id, $start, $limit, $order, $addr_x, $addr_y) {
        // 活动筛选条件  状态 上架的商品
        $timer = time();
        $where = "play_activity_coupon.aid = {$id} AND play_activity_coupon.type = 'place' AND play_shop.shop_status >= 0";
        if ($order != 'close') {
            if ($order == 'hot') {
                $order = 'play_shop.hot_count DESC';
            } else {
                $order = 'play_shop.good_num DESC';
            }

            $sql = "SELECT
play_region.`name`,
play_shop.shop_id,
play_shop.shop_name,
play_shop.cover,
play_shop.reference_price,
play_shop.editor_word,
play_shop.good_num
FROM
play_activity_coupon
LEFT JOIN play_shop ON play_activity_coupon.cid = play_shop.shop_id
LEFT JOIN play_region ON play_shop.busniess_circle = play_region.rid
WHERE
{$where}
GROUP BY
play_shop.shop_id
ORDER BY
$order
LIMIT $start, $limit";

            $res = $this->query($sql);
            $data = array();
            foreach ($res as $val) {
                $data[] = array(
                    'place_id' => $val['shop_id'],
                    'place_name' => $val['shop_name'],
                    'place_cover' => $this->_getConfig()['url'].$val['cover'],
                    'place_price' => $val['reference_price'],
                    'circle' => $val['name'],
                    'place_have' => $val['good_num'],
                    'editor_word' => $val['editor_word'],
                );
            }
            return $data;


        } else { //离我最近
            if ($addr_x && $addr_y) {
                $squares = $this->returnSquarePoint($addr_x, $addr_y, 60);
                $where = $where. " AND ((play_shop.addr_x<>0 and play_shop.addr_x>{$squares['right-bottom']['addr_x']} and play_shop.addr_x<{$squares['left-top']['addr_x']} and play_shop.addr_y<{$squares['left-top']['addr_y']} and play_shop.addr_y>{$squares['right-bottom']['addr_y']}))";
            }
            $sql = "SELECT
play_region.`name`,
play_shop.shop_id,
play_shop.shop_name,
play_shop.cover,
play_shop.reference_price,
play_shop.editor_word,
play_shop.good_num
FROM
play_activity_coupon
LEFT JOIN play_shop ON play_activity_coupon.cid = play_shop.shop_id
LEFT JOIN play_region ON play_shop.busniess_circle = play_region.rid
WHERE
$where
GROUP BY
play_shop.shop_id";

            $res = $this->query($sql);
            $data = $this->OrderAddress($res, $addr_x, $addr_y, $start, $limit);
            return $data;

        }
    }

    /**
     * @param $sql
     * @return Result;
     */
    function query($sql)
    {
        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $stmt = $db->query($sql);
        $result = $stmt->execute($stmt);
        return $result;
    }

    /**
     * 计算某个经纬度的周围某段距离的正方形的四个点
     * 平均半径为EARTH_RADIUSkm
     * @param $addr_y float 经度
     * @param $addr_x float 纬度
     * @param float $distance 该点所在圆的半径，该圆与此正方形内切，默认值为0.5千米
     * @return array 正方形的四个点的经纬度坐标
     *
     *
     */
    public function returnSquarePoint($addr_x, $addr_y, $distance = 0.5)
    {
        $daddr_y = 2 * asin(sin($distance / (2 * EARTH_RADIUS)) / cos(deg2rad($addr_x)));
        $daddr_y = rad2deg($daddr_y);

        $daddr_x = $distance / EARTH_RADIUS;
        $daddr_x = rad2deg($daddr_x);

        return array(
            'left-top' => array('addr_x' => $addr_x + $daddr_x, 'addr_y' => $addr_y - $daddr_y),
            'right-top' => array('addr_x' => $addr_x + $daddr_x, 'addr_y' => $addr_y + $daddr_y),
            'left-bottom' => array('addr_x' => $addr_x - $daddr_x, 'addr_y' => $addr_y - $daddr_y),
            'right-bottom' => array('addr_x' => $addr_x - $daddr_x, 'addr_y' => $addr_y + $daddr_y)
        );
    }


    //游玩地 按距离远近排序
    public function OrderAddress($res, $addr_x, $addr_y, $offset, $sys_row)
    {
        $data = array();
        foreach ($res as $k => $val) {
            $data[$k] = array(
                'place_id' => $val['shop_id'],
                'place_name' => $val['shop_name'],
                'place_cover' => $this->_getConfig()['url'].$val['cover'],
                'place_price' => $val['reference_price'],
                'circle' => $val['name'],
                'place_have' => $val['good_num'],
                'editor_word' => $val['editor_word'],
            );

            if ($addr_x && $addr_y) {
                $data[$k]['distance'] = (!$val['addr_x'] or !$val['addr_x']) ? 0 : $this->GetDistance($val['addr_y'], $val['addr_x'], $addr_y, $addr_x);
            }

        }

        if($addr_x && $addr_y) {
            $count = count($data);
            for ($i = 0; $i < $count; $i++) {
                for ($n = $i; $n < $count; $n++) {
                    if ($data[$i]['distance'] > $data[$n]['distance']) {
                        $tmp = $data[$i];
                        $data[$i] = $data[$n];
                        $data[$n] = $tmp;
                    }
                }
            }
        }

        $data = array_slice($data, $offset, $sys_row);
        return $data;
    }

    /**
     * 计算两组经纬度坐标 之间的距离
     * params ：lat1 纬度1； lng1 经度1； lat2 纬度2； lng2 经度2； len_type （1:m or 2:km);
     * return m or km
     */
    function GetDistance($lat1, $lng1, $lat2, $lng2, $len_type = 1, $decimal = 2)
    {
        if (!$lat2 or !$lng2) {
            return 0;
        }
        $radLat1 = $lat1 * PI / 180.0;
        $radLat2 = $lat2 * PI / 180.0;
        $a = $radLat1 - $radLat2;
        $b = ($lng1 * PI / 180.0) - ($lng2 * PI / 180.0);
        $s = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2)));
        $s = $s * EARTH_RADIUS;
        $s = round($s * 1000);
        if ($len_type > 1) {
            $s /= 1000;
        }
        return round($s, $decimal);
    }

    //分类用获取游玩地
    public function getCatePlace($param)
    {

        $city = $this->getCity();
        $where = "play_label_linker.link_type = 1 && play_shop.shop_city = '{$city}' && play_shop.shop_status >= 0";

        if ($param['id']) {
            $where = $where . ' AND play_label_linker.lid = ' . $param['id'];
        }

        if ($param['age_max']) {
            $where = $where . " AND ((
                  {$param['age_min']} <= play_shop.age_min
              AND {$param['age_max']} >= play_shop.age_min) OR ({$param['age_min']} <= play_shop.age_max
              AND {$param['age_max']} >= play_shop.age_max) OR ({$param['age_min']} >= play_shop.age_min
              AND {$param['age_min']} <= play_shop.age_max) OR ({$param['age_max']} >= play_shop.age_min
              AND {$param['age_max']} <= play_shop.age_max))";
        }

        if ($param['order'] === 'hot') {
            $order = 'play_shop.hot_count DESC';
            $sql = "SELECT
play_label_linker.object_id,
play_shop.shop_name,
play_shop.thumbnails,
play_shop.reference_price,
play_shop.good_num,
play_shop.addr_x,
play_shop.addr_y,
play_shop.editor_word
FROM
play_label_linker
LEFT JOIN play_shop ON play_label_linker.object_id = play_shop.shop_id
WHERE
{$where}
GROUP BY
play_shop.shop_id
ORDER BY
{$order}
LIMIT {$param['start']}, {$param['limit']}
";
            $placeCache = new PlaceCache();
            $res = $this->query($sql);
            $data = array();
            foreach ($res as $val) {
                $data[] = array(
                    'id' => $val['object_id'],
                    'cover' => $this->_getConfig()['url'] . $val['thumbnails'],
                    'title' => $val['shop_name'],
                    'ticket_num' => $val['good_num'],
                    'circle' => $val['name'],
                    'addr_x' => $val['addr_x'],
                    'addr_y' => $val['addr_y'],
                    'editor_word' => $val['editor_word'],
                    'prise'=>$placeCache->getShopTags($val['object_id'])
                );
            }
            return $data;
        }

        if ($param['order'] === 'ticket') {
            $order = 'play_shop.good_num DESC';
            $sql = "SELECT

play_label_linker.object_id,
play_shop.shop_name,
play_shop.thumbnails,
play_shop.reference_price,
play_shop.good_num,
play_shop.addr_x,
play_shop.addr_y,
play_shop.editor_word
FROM
play_label_linker
LEFT JOIN play_shop ON play_label_linker.object_id = play_shop.shop_id
WHERE
{$where}
GROUP BY
play_shop.shop_id
ORDER BY
{$order}
LIMIT {$param['start']}, {$param['limit']}
";
            $res = $this->query($sql);
            $data = array();
            foreach ($res as $val) {
                $data[] = array(
                    'id' => $val['object_id'],
                    'cover' => $this->_getConfig()['url'] . $val['thumbnails'],
                    'title' => $val['shop_name'],
                    'ticket_num' => $val['good_num'],// 不准确
                    'circle' => $val['name'],
                    'addr_x' => $val['addr_x'],
                    'addr_y' => $val['addr_y'],
                    'editor_word' => $val['editor_word']
                );
            }
            return $data;
        }

        if ($param['order'] === 'close') {

            if ($param['addr_x'] and $param['addr_y']) {
                $squares = $this->returnSquarePoint($param['addr_x'], $param['addr_y'], 60);
                $dis_where = "((addr_x<>0 and addr_x>{$squares['right-bottom']['addr_x']} and addr_x<{$squares['left-top']['addr_x']} and addr_y<{$squares['left-top']['addr_y']} and addr_y>{$squares['right-bottom']['addr_y']}))";
                $where = $where . " AND " . $dis_where;
            }

            $sql = "SELECT
play_label_linker.object_id,
play_shop.shop_name,
play_shop.thumbnails,
play_shop.reference_price,
play_shop.addr_x,
play_shop.addr_y,
play_shop.good_num,
play_shop.editor_word
FROM
play_label_linker
LEFT JOIN play_shop ON play_label_linker.object_id = play_shop.shop_id
WHERE
{$where}
GROUP BY
play_shop.shop_id
";
            $res = $this->query($sql);
            $data = $this->OrderAddress($res, $param['addr_x'], $param['addr_y'], $param['start'], $param['limit']);

            return $data;
        }
    }

    public function getnearAction(){
        $url = $this->_getConfig()['url'].'/tag/index/near';
        $json = $this->post_curl($url,array('addr_x'=>'114.276336','addr_y'=>'30.587483'),'',$_COOKIE);
        var_dump($json);die();
    }
}
