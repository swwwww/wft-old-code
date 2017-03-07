<?php
/**
 * index 专题列表
 * info  专题详情
 */

namespace ApiTopic\Controller;

use Deyi\BaseController;
use Deyi\CouponCache;
use Deyi\GetCacheData\ExcerciseCache;
use Deyi\GetCacheData\Labels;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Response;
use Deyi\GetCacheData\GoodCache;
use Deyi\JsonResponse;

class IndexController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    public function __construct()
    {
        define('EARTH_RADIUS', 6378.137); //地球半径
        define('PI', 3.1415926);
    }

    public function indexAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $city = $this->getCity();
        $page = (int)$this->getParams('page', 1);
        $limit = (int)$this->getParams('page_num', 5);
        $page = ($page > 1) ? $page : 1;

        $timer = time();
        $res = array();
        $where = array(
            'status' => 0,
            'ac_city' => $city,
            '(s_time = 0 OR (s_time < ? AND e_time > ?))' => array($timer, $timer),
        );

        $topic_data = $this->_getPlayActivityTable()->fetchLimit((($page-1)*$limit), $limit, array(), $where, array('id' => 'DESC'));

        foreach($topic_data as $topic) {
            $res[] = array(
                'id' => $topic->id,
                'name' => $topic->ac_name,
                'cover' => $this->_getConfig()['url']. $topic->ac_cover
            );
        }
        return $this->jsonResponse($res);
    }

    public function infoAction()
    {
        // 专题缩略图
        if (!$this->pass(false)) {
            return $this->failRequest();
        }

        $id = (int)$this->getParams('id'); //专题id
        $order = $this->getParams('order', 'hot'); // 排序方式 最适合我 有票优先 最热门 离我最近
        $page = (int)$this->getParams('page', 1); //页数
        $limit = $this->getParams('page_num', 5); //每一页多少条
        $uid = (int)$this->getParams('uid');//用户id
        $addr_x = $this->getParams('addr_x'); //坐标
        $addr_y = $this->getParams('addr_y'); //坐标
        $show = $this->getParams('show', ''); //good place
        $start = ($page-1)*$limit;
        if (!$id) {
            return $this->jsonResponseError('参数错误');
        }

        //记录专题点击次数
        $this->_getPlayActivityTable()->update(array('activity_click' => new Expression('activity_click+1')), array('id' => $id));

        //是否点赞了
        $is_like = 0;

        if ($uid) {
            $like_data = $this->_getPlayLikeTable()->get(array('uid' => $uid, 'type' => 'activity', 'like_id' => $id));
            if ($like_data) {
                $is_like = 1;
            }
        }

        //专题数据
        $activity_data = $this->_getPlayActivityTable()->get(array('play_activity.id' => $id));

        $data = array(
            'title' => $activity_data->ac_name, //标题
            'id' => $id, //标题
            'cover' => $this->_getConfig()['url'] . $activity_data->ac_cover, //图片
            'introduce' => $activity_data->introduce, //简介
            'like_number' => $activity_data->like_number,
            'is_like' => $is_like,
            'good_list' => array(),
            'place_list' => array(),
            'share_title' => '带着孩子一起来玩吧，' . $activity_data->ac_name,
            'share_content' => $activity_data->introduce,
            'share_image' => $this->_getConfig()['url'] . $activity_data->ac_cover,
            'view_type' => $activity_data->view_type,
        );

        //'1' => '混合, 游玩地优先',  '2' => '混合, 商品优先',  '3' => '仅游玩地', '4' => '仅商品',   '5' => '混合, 活动优先','6' => '仅活动',
        if ($activity_data->view_type == 1) {
            if ($show == 'good') {
                $data['good_list'] = $this->getCoupon($id, $start, $limit, $order, $uid);
            } elseif($show=='excercise') {
                $data['excercise_list'] = $this->getExcercise($id, $start, $limit, $order);
            }else{
                $data['place_list'] = $this->getPlace($id, $start, $limit, $order, $addr_x, $addr_y);
            }
        }

        if ($activity_data->view_type == 2) {
            if ($show == 'place') {
                $data['place_list'] = $this->getPlace($id, $start, $limit, $order, $addr_x, $addr_y);
            }elseif($show=='excercise') {
                $data['excercise_list'] = $this->getExcercise($id, $start, $limit, $order);
            } else {
                $data['good_list'] = $this->getCoupon($id, $start, $limit, $order, $uid);
            }
        }

        if ($activity_data->view_type == 3) {
            $data['place_list'] = $this->getPlace($id, $start, $limit, $order, $addr_x, $addr_y);
        }

        if ($activity_data->view_type == 4) {
            $data['good_list'] = $this->getCoupon($id, $start, $limit, $order, $uid);
        }

        if ($activity_data->view_type == 5) {//混合，活动优先
            if($show=='place'){
                $data['place_list'] = $this->getPlace($id, $start, $limit, $order, $addr_x, $addr_y);
            }elseif($show=='good'){
                $data['good_list'] = $this->getCoupon($id, $start, $limit, $order, $uid);
            }else{
                $data['excercise_list'] = $this->getExcercise($id, $start, $limit, $order);
            }
        }

        if ($activity_data->view_type == 6) {
            $data['excercise_list'] = $this->getExcercise($id, $start, $limit, $order);
        }

        return $this->jsonResponse($data);
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
        $where = "play_organizer_game.status > 0 AND  play_organizer_game.down_time >= {$timer} AND play_organizer_game.start_time <= {$timer} AND play_organizer_game.id in {$m}";

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
        $where = "play_activity_coupon.aid = $id AND play_activity_coupon.type = 'game' AND play_organizer_game.status > 0 AND play_organizer_game.start_time <= {$timer} AND play_organizer_game.down_time >= {$timer}";

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
play_organizer_game.coupon_vir,
play_organizer_game.shop_addr,
play_organizer_game.cover,
play_organizer_game.down_time,
play_organizer_game.foot_time,
play_organizer_game.is_together,
play_organizer_game.g_buy,
play_organizer_game.special_labels,
play_organizer_game.post_award
FROM
play_activity_coupon
LEFT JOIN play_organizer_game ON play_activity_coupon.cid = play_organizer_game.id
WHERE
{$where}
ORDER BY
{$order}
LIMIT $start, $limit";

        $class_goodcache = new GoodCache();
        $res  = $this->query($sql);
        $data = array();
        foreach ($res as $val) {
            $class_goodcache->getLowPrice($val['id']);
            $data_low_money = sprintf('%.2f', $val['low_price']);
            if ($or == 'ticket') {
                if (($val['ticket_num'] >  $val['buy_num']) && $val['foot_time'] > $timer && $val['is_together'] == 1) {
                    array_unshift($data, array(
                        'good_id'     => $val['id'],
                        'good_name'   => $val['title'],
                        'editor_word' => $val['editor_talk'],
                        'good_price'  => $data_low_money,
                        'good_cover'  => $this->_getConfig()['url'].$val['cover'],
                        'discount'    => ($val['is_together'] == 1) ? (round($val['low_price'] / $val['low_money'] * 10, 1)) : 10,
                        'good_have'   => (($val['ticket_num'] >  $val['buy_num']) && $val['foot_time'] > $timer && $val['is_together'] == 1) ? 1 : 0,
                        'circle'      => $val['shop_addr'],
                        'g_buy'       => (($val['down_time'] - $timer) > 86400) ? $val['g_buy'] : '0',
                        'n_tags'      => Labels::getLabels($val['special_labels']),
                        'prise'       => GoodCache::getGameTags($val['id'],$val['post_award']),
                        'sold_number' => ((int)$val['buy_num'] + (int)$val['coupon_vir']),
                    ));
                } else {
                    array_push($data, array(
                        'good_id'     => $val['id'],
                        'good_name'   => $val['title'],
                        'editor_word' => $val['editor_talk'],
                        'good_price'  => $data_low_money,
                        'good_cover'  => $this->_getConfig()['url'].$val['cover'],
                        'discount'    => ($val['is_together'] == 1) ? (round($val['low_price'] / $val['low_money'] * 10, 1)) : 10,
                        'good_have'   => (($val['ticket_num'] >  $val['buy_num']) && $val['foot_time'] > $timer && $val['is_together'] == 1) ? 1 : 0,
                        'circle'      => $val['shop_addr'],
                        'g_buy'       => (($val['down_time'] - $timer) > 86400) ? $val['g_buy'] : '0',
                        "n_tags"      =>  Labels::getLabels($val['special_labels']),
                        'prise'       => GoodCache::getGameTags($val['id'],$val['post_award']),
                        'sold_number' => ((int)$val['buy_num'] + (int)$val['coupon_vir']),
                    ));
                }

            } else {
                $data[] = array(
                    'good_id'     => $val['id'],
                    'good_name'   => $val['title'],
                    'editor_word' => $val['editor_talk'],
                    'good_price'  => $data_low_money,
                    'good_cover'  => $this->_getConfig()['url'].$val['cover'],
                    'discount'    => ($val['is_together'] == 1) ? (round($val['low_price'] / $val['low_money'] * 10, 1)) : 10,
                    'good_have'   => (($val['ticket_num'] >  $val['buy_num']) && $val['foot_time'] > $timer && $val['is_together'] == 1) ? 1 : 0,
                    'circle'      => $val['shop_addr'],
                    'g_buy'       => (($val['down_time'] - $timer) > 86400) ? $val['g_buy'] : '0',
                    "n_tags"      =>  Labels::getLabels($val['special_labels']),
                    'prise'       => GoodCache::getGameTags($val['id'],$val['post_award']),
                    'sold_number' => ((int)$val['buy_num'] + (int)$val['coupon_vir']),
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

    //仅活动
    public function getExcercise($id, $start, $limit, $order){
        $timer = time();
        $where = "c.aid = {$id} AND c.type = 'excercise' AND b.release_status = 1 and e.sell_status>=1";

        if ($order == 'hot') {
            $order = '(e.start_time - UNIX_TIMESTAMP()) ASC,(e.perfect_number-e.join_number) ASC';
        } else {
            $order = '(b.most_number - b.join_number) DESC';
        }

        $sql = "SELECT
        b.name,
        b.cover,
        b.low_price,
        b.circle,
        b.id as base_id,
        b.max_start_time,
        b.min_end_time,
        b.join_number,
        b.vir_number,
        b.all_number,
        b.custom_tags,
        b.special_labels,
        b.free_coupon_event_count
        FROM play_activity_coupon as c
        LEFT JOIN play_excercise_base AS b ON c.cid = b.id
        LEFT JOIN play_excercise_event AS e ON b.id = e.bid
        WHERE {$where}
        GROUP BY
        b.id
        ORDER BY
        $order
        LIMIT $start, $limit";

        $res = $this->query($sql);
        $data = array();
        foreach ($res as $val) {


            $tags = Labels::getLabels($val['special_labels']);
            if ($val['custom_tags']) {
                $data_tags = explode(',', $val['custom_tags']);
            } else {
                $data_tags = array();
            }

            $data[] = array(
                'exc_id'      => $val['base_id'],
                'exc_name'    => $val['name'],
                'cover'       => $this->_getConfig()['url'].$val['cover'],
                'low_price'   => $val['low_price'],
                'circle'      => $val['circle'],
                'join_number' => ((int)$val['join_number'] + (int)$val['vir_number']),
                'date'        => date('m月d日', $val['max_start_time']) . '-' . date('m月d日', $val['min_end_time']),  //开始到结束的时间
                'editor_word' => $val['introduction'],
                'tags'        => $data_tags,
                "n_tags"      => Labels::getLabels($val['special_labels']),
                'session_num' => (int)$this->_getPlayExcerciseEventTable()->session($val['base_id']),
                'vip_free'    => (int)$val['free_coupon_event_count'],
            );
        }
        return $data;
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

}
