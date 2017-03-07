<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ApiCoupon\Controller;

use Deyi\BaseController;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Adapter\Platform\Mysql;
use Zend\Db\Sql\Select;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Http\Response;
use Deyi\JsonResponse;
use library\Service\System\Cache\RedCache;
use Deyi\GetCacheData\PlaceCache;
use Deyi\GetCacheData\UserCache;

class WeatherController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    public function __construct()
    {
        define('EARTH_RADIUS', 6378.137); //地球半径
        define('PI', 3.1415926);
    }

    public function getweatherName($v)
    {
        if ($v == '小到中雨转暴雨') {
            return '中雨转暴雨';
        } else if ($v == '小到中雨转阵雨') {
            return '中雨转阵雨';
        }else{
            return $v;
        }
    }

    public function indexAction()
    {

        if (!$this->pass()) {
            return $this->failRequest();
        }

        $city = $this->getCity();
        $day = (int)$this->getParams('day', 0);
        $uid = (int)$this->getParams('uid', 0);
        $addr_x = $this->getParams('addr_x', '');
        $addr_y = $this->getParams('addr_y', '');

        $day = ($day > 3) ? 3 : $day;
        $timer = time();
        $page = $this->getParams('page', 1);
        $pageNum = $this->getParams('pagenum', 5);
        $offset = $page ? ($page - 1) * $pageNum : 0;

        $placeCache = new PlaceCache();

        $data = array();
        $data['now_time'] = time();
        // 天气

        $weather = json_decode($this->getWeather(), true);

        if ($weather) {
            $data['weather'] = array(
                'img' => $weather['weather_data'][$day]['dayPictureUrl'],
                'weather' => $this->getweatherName($weather['weather_data'][0]['weather']),  //todo android 闪退
                'temperature' => $weather['weather_data'][$day]['temperature'],
                'pm25' => ($day == 0) ? $weather['pm25'] : 80,
                'cloth' => ($day == 0 && isset($weather['index'][0]['des'])) ? $weather['index'][0]['des'] : '',
            );
        }

        // 取出用户宝宝的年龄
        $userCache = new UserCache();
        $baby_max = $userCache->getBabyAge($uid)['max'];
        $baby_min = $userCache->getBabyAge($uid)['min'];

        $babyData = $this->_getPlayUserBabyTable()->fetchAll(array('uid' => $uid));
        foreach ($babyData as $baby) {
            $data['baby'][] = array(
                'name' => $baby->baby_name,
                'img' => $this->_getConfig()['url'] . $baby->img,
                'age' => ceil((time() - $baby->baby_birth) / 31536000) . '岁',
            );
        }

        $WeatherAttribute = $this->getWeatherAttribute($day);

        if ($WeatherAttribute) {
            $attr = '';
            foreach ($WeatherAttribute as $k => $at) {
                if (!$k) {
                    $attr = $attr . "'{$at}'";
                } else {
                    $attr = $attr . ",'{$at}'";
                }
            }

            $sql_fit = "select shop_id from play_shop
left JOIN play_tags_link on play_tags_link.link_id = play_shop.shop_id
left JOIN play_tags on play_tags.id = play_tags_link.tag_id
WHERE play_shop.shop_city = '{$city}' AND play_shop.shop_status >= 0 AND play_shop.age_min <= {$baby_max} AND play_shop.age_max >= {$baby_min}
AND play_tags_link.tag_type = 2 AND play_tags.tag_name in ($attr) GROUP BY play_shop.shop_id";
            $sql_res = $this->query($sql_fit);
            $fitNumber = (int)$sql_res->count();

            $shop_Ids = array();
            foreach ($sql_res as $res) {
                $shop_Ids[] = $res['shop_id'];
            }
        } else {
            $sql_fit = "select shop_id from play_shop
WHERE age_min <= {$baby_max} AND age_max >= {$baby_min} AND shop_city = '{$city}' AND shop_status >= 0";
            $sql_res = $this->query($sql_fit);
            $fitNumber = (int)$sql_res->count();
            $shop_Ids = array();
            foreach ($sql_res as $res) {
                $shop_Ids[] = $res['shop_id'];
            }
        }
        $data['fit_number'] = ($fitNumber > 500) ? 500 : $fitNumber;


        $shop_id = '';
        if (count($shop_Ids)) {
            $shop_id = implode(',', $shop_Ids);
        }

        if (!$shop_id) {
            return $this->jsonResponse($data);
        }

        $id_where = "AND shop_id in ($shop_id)";

        $dis_where = '';
        if ($addr_x && $addr_y) {
            $squares = $this->returnSquarePoint($addr_x, $addr_y, 60);
            $dis_where = "AND ((addr_x<>0 and addr_x>{$squares['right-bottom']['addr_x']} and addr_x<{$squares['left-top']['addr_x']} and addr_y<{$squares['left-top']['addr_y']} and addr_y>{$squares['right-bottom']['addr_y']}))";
        }

        $sql = "SELECT * FROM play_shop  WHERE play_shop.shop_city = '{$city}'  AND play_shop.shop_status >= 0 AND {$baby_max} >= play_shop.age_min AND
$baby_min <= play_shop.age_max {$id_where} {$dis_where}  LIMIT 0, 500";

        $res = $this->query($sql);

        $shop_data = array();

        foreach ($res as $k => $v) {
            $shop_data[$k] = array(
                'id' => $v['shop_id'],
                'title' => $v['shop_name'],
                'cover' => $this->_getConfig()['url'] . $v['thumbnails'],
                'editor_word' => $v['editor_word'],
                'circle' => $placeCache->getPlaceCircle($v['shop_id']),
                'tag' => $placeCache->getShopTags($v['shop_id']),
                'addr_x' => $v['addr_x'],
                'addr_y' => $v['addr_y'],
            );

            $shop_data[$k]['distance'] = (!$v['addr_x'] or !$v['addr_x']) ? 0 : $this->GetDistance($v['addr_y'], $v['addr_x'], $addr_y, $addr_x);
        }

        $count = count($shop_data);

        for ($i = 0; $i < $count; $i++) {
            for ($n = $i; $n < $count; $n++) {
                if ($shop_data[$i]['distance'] > $shop_data[$n]['distance']) {
                    $tmp = $shop_data[$i];
                    $shop_data[$i] = $shop_data[$n];
                    $shop_data[$n] = $tmp;
                }
            }
        }


        $data['place'] = array_slice($shop_data, $offset, $pageNum);

        if (!count($data['place'])) {
            return $this->jsonResponse($data);
        }

        $shop_i = '';
        foreach ($data['place'] as $i => $z) {
            if (!$i) {
                $shop_i = $z['id'];
            } else {
                $shop_i .= ',' . $z['id'];
            }
        }

        $good_where = "play_game_info.status = 1 AND play_game_info.shop_id in ($shop_i) AND play_organizer_game.is_together = 1 AND play_organizer_game.status > 0 and play_organizer_game.end_time >= {$timer} AND play_organizer_game.start_time <= {$timer} AND play_organizer_game.ticket_num > play_organizer_game.buy_num";

        $good_sql = "SELECT
play_organizer_game.id,
play_organizer_game.title,
play_organizer_game.low_price,
play_game_info.shop_id
FROM
play_organizer_game
LEFT JOIN play_game_info ON play_game_info.gid = play_organizer_game.id
WHERE
$good_where
GROUP BY
play_organizer_game.id";

        $good_data = $this->query($good_sql);

        $where_m = array();
        foreach ($good_data as $good) {
            $where_m[$good['shop_id']][] = array(
                'id' => $good['id'],
                'ticket_name' => $good['title'],
                'price' => $good['low_price'] ?: '0',
            );
        }

        foreach ($data['place'] as $k => $v) {
            $data['place'][$k]['ticket_list'] = array_key_exists($v['id'], $where_m) ? (array_slice($where_m[$v['id']], 0, 3)) : array();
            $data['place'][$k]['ticket_num'] = array_key_exists($v['id'], $where_m) ? (int)count($where_m[$v['id']]) : 0;

            unset($data['place'][$k]['distance']);
        }

        return $this->jsonResponse($data);
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


    function query($sql)
    {
        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $stmt = $db->query($sql);
        $result = $stmt->execute($stmt);
        return $result;
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
