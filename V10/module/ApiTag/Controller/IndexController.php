<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ApiTag\Controller;

use Deyi\BaseController;
use Deyi\GetCacheData\ShopCache;
use library\Service\System\Cache\RedCache;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Response;
use Deyi\JsonResponse;
use Deyi\GetCacheData\GoodCache;

class IndexController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    public function __construct()
    {
        define('EARTH_RADIUS', 6378.137); //地球半径
        define('PI', 3.1415926);
    }

    //游玩地分类接口
    public function indexAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $param['addr_x'] = $this->getParams('addr_x'); //坐标
        $param['addr_y'] = $this->getParams('addr_y'); //坐标
        $param['age_min'] = (int)$this->getParams('age_min', 0);  //年齡小
        $param['age_max'] = (int)$this->getParams('age_max', 100);  //年齡大
        $param['age_max'] = ($param['age_max'] > 100) ? 100 : $param['age_max'];
        $param['order'] = $this->getParams('order', 'hot'); // 排序方式 close ticket hot
        $param['id'] = (int)$this->getParams('id', 0); // 分类 0 所有分类 默认为0
        $page = (int)$this->getParams('page', 1);
        $param['limit'] = (int)$this->getParams('page_num', 5);
        $param['start'] = ($page - 1) * $param['limit'];

        $data['place_list'] = $this->getPlace($param);

        //一页所有的 shopid
        $shopids = [];
        foreach ($data['place_list'] as $v) {
            $shopids[] = $v['id'];
        }

        if (count($shopids) > 0) {
            $shopids = implode(',', $shopids);
        } else {
            $shopids = 0;
        }

        $timer = time();

        $good_where = "play_game_info.shop_id in ({$shopids}) AND play_organizer_game.status > 0 and play_organizer_game.end_time >= {$timer} AND play_organizer_game.start_time <= {$timer}";
        $order = 'play_organizer_game.click_num DESC';
        $good_sql = "SELECT
play_shop.label_id,
play_shop.shop_id,
play_organizer_game.id AS gid,
play_organizer_game.thumb,
play_organizer_game.title,
play_organizer_game.low_price,
play_organizer_game.low_money,
play_organizer_game.ticket_num,
play_organizer_game.buy_num,
play_organizer_game.foot_time,
play_organizer_game.is_together,
play_organizer_game.down_time,
play_organizer_game.post_award,
play_organizer_game.g_buy,
(
		SELECT
			count(*)
		FROM
			play_game_info
		WHERE
			play_game_info.gid = play_organizer_game.id
		AND play_game_info.buy < play_game_info.total_num
		AND play_game_info.up_time < UNIX_TIMESTAMP()
		AND play_game_info.down_time > UNIX_TIMESTAMP()
	) AS buy_ing,
	(
		SELECT
			count(*)
		FROM
			play_game_info
		WHERE
			play_game_info.gid = play_organizer_game.id
		AND play_game_info.buy < play_game_info.total_num
		AND play_game_info.up_time > UNIX_TIMESTAMP()
		AND play_game_info.status = 1
	) AS not_start
	
FROM
play_shop
LEFT JOIN play_game_info ON play_shop.shop_id = play_game_info.shop_id
LEFT JOIN play_organizer_game ON play_game_info.gid = play_organizer_game.id
WHERE
{$good_where}
ORDER BY
{$order}";

        $good_data = $this->query($good_sql);

        $game_id_list = $game = array();

        foreach ($good_data as $g_data) {

            $log = false;
            foreach ($game_id_list[$g_data['shop_id']] as $v) { //检查套系是否出现过
                if ($v['ticket_name'] == $g_data['title']) {
                    $log = true;
                    break;
                }
            }


            if (!$log) {

                if($g_data['buy_ing'] > 0){
                    $status=0;
                }else{
                    if($g_data['not_start'] > 0){
                        $status=1;
                    }else{
                        $status=2;
                    }
                }


                $game_id_list[$g_data['shop_id']][] = array(
                    'id' => $g_data['gid'],
                    'shop_id' => $g_data['shop_id'],
                    'ticket_name' => $g_data['title'],
                    'price' => $g_data['low_money']?:'0',
                    'low_money' => $g_data['low_money']?:'0',
                    'surplus' => ($g_data['ticket_num'] - $g_data['buy_num']),
                    'coupon_have' => (($g_data['ticket_num'] > $g_data['buy_num']) && $g_data['foot_time'] > $timer && $g_data['is_together'] === 1) ? 1 : 0,
                    'g_buy' => (($g_data['down_time'] - time()) > 86400) ? $g_data['g_buy'] : '0',
                    'tag' => GoodCache::getGameTags($g_data['gid'], ($g_data['post_award'] == 2)),
                    "type"=>1,
                    "status"=>$status
                );
                if (!array_key_exists($g_data['shop_id'], $game)) {
                    $game[$g_data['shop_id']] = 0;
                }
                $game[$g_data['shop_id']] += ($g_data['ticket_num'] - $g_data['buy_num']);
            }
        }

        foreach ($data['place_list'] as $k => $v) {
            $data['place_list'][$k]['ticket_list'] = array_key_exists($v['id'], $game_id_list) ? (array_slice($game_id_list[$v['id']], 0, 3)) : array();
            $data['place_list'][$k]['ticket_num'] = (int)count($game_id_list[$v['id']]);
        }
        $tagData = $this->_getPlayLabelTable()->fetchLimit(0, 100, $columns = array(), array('status >= ?' => 2, 'label_type<=2','city' => 'WH','object_id is not null'), array('dateline' => 'desc'));
        foreach ($tagData as $tag) {
            $data['tag'][] = array(
                'id' => $tag['id'],
                'name' => $tag['tag_name'],
            );
        }
        return $this->jsonResponse($data);

    }

    public function getPlace($param)
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
            $res = $this->query($sql);
            $data = array();
            foreach ($res as $val) {
                $data[] = array(
                    'id' => $val['object_id'],
                    'cover' => $this->_getConfig()['url'] . $val['thumbnails'],
                    'title' => $val['shop_name'],
                    'ticket_num' => $val['good_num'],
                    'circle' => $val['name']?:'',
                    'addr_x' => $val['addr_x'],
                    'addr_y' => $val['addr_y'],
                    'editor_word' => $val['editor_word']
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

    public function getGood($param)
    {
        $timer = time();
        $city = $this->getCity();
        $where = "play_organizer_game.city = '{$city}' && play_organizer_game.status > 0 AND play_label_linker.link_type = 2 AND play_organizer_game.end_time >= {$timer} AND play_organizer_game.start_time <= {$timer}";

        if ($param['id']) {
            $where = $where . " AND play_label_linker.lid = " . $param['id'];
        }

        if ($param['age_max']) {
            $where = $where . " AND (({$param['age_min']} >= play_organizer_game.age_min AND {$param['age_min']} <= play_organizer_game.age_max) OR ({$param['age_max']} >= play_organizer_game.age_min AND {$param['age_max']} <= play_organizer_game.age_max) OR ({$param['age_min']} <= play_organizer_game.age_min AND {$param['age_max']} >= play_organizer_game.age_min) OR ({$param['age_min']} <= play_organizer_game.age_max AND {$param['age_max']} >= play_organizer_game.age_max))";
        }

        if ($param['order'] != 'close') {
            if ($param['order'] == 'hot') {
                $order = 'play_organizer_game.click_num DESC';
            } else {
                $order = '(play_organizer_game.ticket_num - play_organizer_game.buy_num) DESC';
            }

            $sql = "SELECT
play_label_linker.object_id,
play_organizer_game.title,
play_organizer_game.thumb,
play_organizer_game.down_time,
(play_organizer_game.ticket_num - play_organizer_game.buy_num),
play_organizer_game.low_price,
play_organizer_game.low_money,
play_organizer_game.ticket_num,
play_organizer_game.buy_num,
play_organizer_game.is_together,
play_organizer_game.g_buy
FROM
play_label_linker
LEFT JOIN play_organizer_game ON play_label_linker.object_id = play_organizer_game.id
WHERE
{$where}
GROUP BY
play_organizer_game.id
ORDER BY
{$order}
LIMIT {$param['start']}, {$param['limit']}
";

            $res = $this->query($sql);
            $data = array();
            foreach ($res as $val) {
                $data[] = array(
                    'id' => $val['object_id'],
                    'cover' => $this->_getConfig()['url'] . $val['thumb'],
                    'name' => $val['title'],
                    'price' => $val['low_price']?:'0',
                    'have' => (($val['is_together'] == 1) && ($val['down_time'] > $timer) && (($val['ticket_num'] - $val['buy_num']) > 0)) ? ($val['ticket_num'] - $val['buy_num']) : 0,
                    'end_time' => $val['down_time'],
                    'old_price' => $val['low_money']?:'0',
                    'g_buy' => (($val['down_time'] - $timer) > 86400) ? $val['g_buy'] : '0',
                );
            }

            return $data;

        } else {//离我最近

            if ($param['addr_x'] and $param['addr_y']) {
                $squares = $this->returnSquarePoint($param['addr_x'], $param['addr_y'], 60);
                $dis_where = "((addr_x<>0 and addr_x>{$squares['right-bottom']['addr_x']} and addr_x<{$squares['left-top']['addr_x']} and addr_y<{$squares['left-top']['addr_y']} and addr_y>{$squares['right-bottom']['addr_y']}))";
                $where = $where . " AND " . $dis_where;
            }

            $sql = "SELECT
play_label_linker.object_id,
play_organizer_game.title,
play_organizer_game.thumb,
play_organizer_game.down_time,
(play_organizer_game.ticket_num - play_organizer_game.buy_num),
play_organizer_game.low_price,
play_organizer_game.low_money,
play_organizer_game.ticket_num,
play_organizer_game.buy_num,
play_organizer.addr_x,
play_organizer.addr_y,
play_organizer_game.is_together,
play_organizer_game.g_buy
FROM
play_label_linker
LEFT JOIN play_organizer_game ON play_label_linker.object_id = play_organizer_game.id
LEFT JOIN play_organizer ON play_organizer_game.organizer_id = play_organizer.id
WHERE
{$where}
GROUP BY
play_organizer_game.id
";
            $res = $this->query($sql);
            $data = $this->OrderAddressCoupon($res, $param['addr_x'], $param['addr_y'], $param['start'],
                $param['limit']);

            return $data;

        }

    }

    //发现接口
    public function listAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }
        $time = time();
        $come = (int)$this->getParams('come');  //1 买票列表  2发现列表
        $city = $this->getCity();
        $data = array(
            'tag' => array()
        );

        if ($come == 2) {
            $data['topic'] = array();
            $topicWhere = array(
                'play_activity.status >= ?' => 0,
                'play_activity.ac_city = ?' => $city,
                "((play_activity.s_time < {$time} and play_activity.e_time > {$time}) or (play_activity.s_time = 0 and play_activity.e_time = 0))",
            );

            $topicMaps = $this->_getPlayActivityTable()->fetchLimit(0, 4, array(), $topicWhere,
                $order = array('discovery' => 'DESC', 'id' => 'DESC'));
            $topic_count = $this->_getPlayActivityTable()->fetchCount($topicWhere);
            foreach ($topicMaps as $topic) {
                $data['topic'][] = array(
                    'id' => $topic->id,
                    'name' => $topic->ac_name,
                    'cover' => $this->_getConfig()['url'] . $topic->ac_cover,
                );
            }
            $data['topic_count'] = $topic_count;


            $tagData = $this->_getPlayLabelTable()->fetchLimit(0, 100, $columns = array(), array('status > 1', 'city' => $city,'label_type<=2'), array('dateline' => 'desc'));
            //获取游玩地数目
            $place_where = "play_label_linker.link_type = 1";
            $sql = "SELECT
play_label_linker.lid,
count(play_shop.shop_name) as place_count
FROM
play_label_linker
LEFT JOIN play_shop ON play_label_linker.object_id = play_shop.shop_id
WHERE
{$place_where}
GROUP BY
play_label_linker.lid
";

            $place_res = $this->query($sql);
            if (false !== $place_res) {
                foreach ($place_res as $v) {
                    $places[$v['lid']] = $v['place_count'];
                }
            } else {
                $places = [];
            }

            //获取票券数目
            $good_where = "play_organizer_game.status > 0 AND play_organizer_game.city = '{$city}' and play_label_linker.link_type = 2 AND play_organizer_game.end_time >= {$time} AND play_organizer_game.start_time <= {$time}";
            $sql = "SELECT
play_label_linker.lid,
sum(play_organizer_game.ticket_num - play_organizer_game.buy_num) as ticket_sum
FROM
play_label_linker
LEFT JOIN play_organizer_game ON play_label_linker.object_id = play_organizer_game.id
WHERE
{$good_where}
GROUP BY
play_label_linker.lid
";

            $good_res = $this->query($sql);
            if ($good_res !== false) {
                foreach ($good_res as $v) {
                    $goods[$v['lid']] = $v['ticket_sum'];
                }
            } else {
                $goods = [];
            }
            $data['total'] = 0;

            foreach ($tagData as $tag) {
                if ($tag['status'] == 3) {
                    $data['tag'][] = array(
                        'id' => $tag['id'],
                        'coin' => $tag['coin'] ? $this->_getConfig()['url'] . $tag['coin'] : '',
                        'name' => $tag['tag_name'],
                        'cover' => $tag['cover'] ? $this->_getConfig()['url'] . $tag['cover'] : '',
                        'description' => $tag['description'],
                        'place' => array_key_exists($tag['id'], $places) ? $places[$tag['id']] : 0,
                        'pay' => array_key_exists($tag['id'], $goods) ? $goods[$tag['id']] : 0,
                    );
                }

                $data['total'] += array_key_exists($tag['id'], $places) ? $places[$tag['id']] : 0;
            }

            return $this->jsonResponse($data);
        } else {
            $tagData = $this->_getPlayLabelTable()->fetchLimit(0, 100, $columns = array(), array('status > 1', 'city' => $city,'label_type>=2'), array('dateline' => 'desc'));
            foreach ($tagData as $tag) {
                $data['tag'][] = array(
                    'id' => $tag['id'],
                    'coin' => '',
                    'name' => $tag['tag_name'],
                    'cover' => '',
                    'description' => '',
                    'place' => 0,
                    'pay' => 0,
                );
            }

            return $this->jsonResponse($data);
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
                'id' => $val['object_id'],
                'cover' => $this->_getConfig()['url'] . $val['thumbnails'],
                'title' => $val['shop_name'],
                'ticket_num' => $val['good_num'],
                'circle' => $val['name'],
                'reference_price' => $val['reference_price'],
            );

            $data[$k]['distance'] = (!$val['addr_x'] or !$val['addr_x']) ? 0 : $this->GetDistance($val['addr_y'],
                $val['addr_x'], $addr_y, $addr_x);

        }

        if ($addr_x && $addr_y) {
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

        //var_dump($data);

        $data = array_slice($data, $offset, $sys_row);

        return $data;
    }

    //卡券 按距离远近排序
    public function OrderAddressCoupon($res, $addr_x, $addr_y, $offset, $sys_row)
    {
        $data = array();
        foreach ($res as $k => $val) {
            $data[$k] = array(
                'id' => $val['object_id'],
                'cover' => $this->_getConfig()['url'] . $val['thumb'],
                'name' => $val['title'],
                'price' => $val['low_price'],
                'have' => (($val['is_together'] == 1) && ($val['down_time'] > time()) && (($val['ticket_num'] - $val['buy_num']) > 0)) ? ($val['ticket_num'] - $val['buy_num']) : 0,
                'end_time' => $val['down_time'],
                'old_price' => $val['low_money'],
                'g_buy' => (($val['down_time'] - time()) > 86400) ? $val['g_buy'] : '0',
            );

            $data[$k]['distance'] = (!$val['addr_x'] or !$val['addr_x']) ? 0 : $this->GetDistance($val['addr_y'],
                $val['addr_x'], $addr_y, $addr_x);
        }

        if ($addr_x && $addr_y) {
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

        //var_dump($data);

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

    //所有区域 3.3.1
    public function areaAction(){
        if (!$this->pass()) {
            return $this->failRequest();
        }
        $time = time();
        $city = $this->getCity();


        $data=RedCache::fromCacheData('D:areaList:'.$city,function()use($city){

            //获取商圈
            $areaData = $this->_getPlayRegionTable()->fetchAll(array('acr'=>$city,'level'=>4),array('rid'=>'asc'))->toArray();
            $data = array();
            $child = array();
            foreach($areaData as $k=>$v){
                $erid = $v['rid']+100;
                $where = "acr = 'WH'
AND LEVEL = 5
AND (
	SELECT
		count(play_organizer_game.id)
	FROM
		play_organizer_game
	LEFT JOIN play_label_linker ON play_label_linker.object_id=play_organizer_game.id
	LEFT JOIN play_game_info ON play_organizer_game.id=play_game_info.gid
	LEFT JOIN play_shop ON play_game_info.shop_id = play_shop.shop_id
WHERE
	play_shop.busniess_circle BETWEEN play_region.rid
	AND play_region.rid
	AND play_organizer_game. STATUS > 0
	AND play_organizer_game.is_together = 1
	AND play_label_linker.link_type = 2
	AND play_organizer_game.city = 'WH'
) > 0
AND rid > {$v['rid']}
AND rid < {$erid}";
                $child = $this->_getPlayRegionTable()->fetchAll($where)->toArray();
                if(empty( $areaData[$k]['child'])){
                    $areaData[$k]['child'] = array();
                }
                $areaData[$k]['child']=$child;
            }

            $data['area'] = $areaData;
            return $data;

        },3600*24,true);

        return $this->jsonResponse($data);
    }

//    //附近的商品
//    public function nearAction(){
//        if (!$this->pass(false)) {
//            return $this->failRequest();
//        }
//        $page = (int)$this->getParams('page',1);
//        $pageNum = (int)$this->getParams('page_num',10);
//        $start = ($page - 1) * $pageNum;
//        $addr_x = $this->getParams('addr_x'); //x坐标
//        $addr_y = $this->getParams('addr_y'); //y坐标
//        $squares = $this->returnSquarePoint($addr_x,$addr_y,2);
//        $rids = $this->query("select name from play_region where rid in (select busniess_circle from `play_shop` where addr_x<>0 and addr_x>{$squares['right-bottom']['addr_x']} and addr_x<{$squares['left-top']['addr_x']} and addr_y<{$squares['left-top']['addr_y']} and addr_y>{$squares['right-bottom']['addr_y']})");
//        $info=array();
//        foreach($rids as $v){
//            array_push($info,$v['name']);
//        }
//        $sql = "select
//play_organizer_game.id,
//play_organizer_game.title,
//play_organizer_game.thumb,
//play_organizer_game.down_time,
//play_organizer_game.low_price,
//play_organizer_game.low_money,
//play_organizer_game.ticket_num,
//play_organizer_game.buy_num,
//play_organizer_game.is_together,
//play_organizer_game.g_buy from play_organizer_game where shop_addr ".$this->db_create_in($info)."LIMIT {$start} , {$pageNum}";
//        $res = $this->query($sql);
//        $data = array();
//        foreach($res as $val){
//            $data['coupon_list'] = array(
//                'coupon_id' => $val['id'],
//                'cover' => $this->_getConfig()['url'] . $val['thumb'],
//                'name' => $val['title'],
//                'price' => $val['low_price'],
//                'have' => (((int)$val['is_together'] === 1) && ($val['down_time'] > time()) && (($val['ticket_num'] - $val['buy_num']) > 0)) ? ($val['ticket_num'] - $val['buy_num']) : 0,
//                'end_time' => $val['down_time'],
//                'buy' => $val['buy_num'],
//                'low_money' => $val['low_money'],
//                'g_buy' => (($val['down_time'] - time()) > 86400) ? $val['g_buy'] : '0',
//
//                'residue' =>$this->getSurplusNumber($val), //(($val['ticket_num'] - $val['buy_num']) > 0) ? ($val['ticket_num'] - $val['buy_num']) : 0,
//                'buy_num'=>$val['buy_num']
//            );
//        }
//        return $this->jsonResponse($data);
//
//    }

    //获取商品实际剩余数,过滤了已过期的
//    public function getSurplusNumber($val)
//    {
//        $num = RedCache::get('D:SurplusNumber:' .$val['id']);
//        if ($num) {
//            return $num;
//        } else {
//            return ($val['ticket_num'] - $val['buy_num']);
//        }
//
//    }

}
