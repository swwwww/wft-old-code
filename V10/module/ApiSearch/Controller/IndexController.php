<?php
/**
 * index 搜索
 */

namespace ApiSearch\Controller;

use Deyi\BaseController;
use Deyi\GetCacheData\CouponCache;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Response;
use Deyi\JsonResponse;
use library\Service\System\Cache\RedCache;
use Deyi\GetCacheData\PlaceCache;

class IndexController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    public function indexAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $param['uid'] = $this->getParams('uid');
        $param['key'] = $this->getParams('word');

        $page = (int)$this->getParams('page', 1);
        $param['limit'] = (int)$this->getParams('page_num', 5);
        $param['start'] = ($page - 1) * $param['limit'];

        //  插入搜索记录
        $this->_getPlaySearchLogTable()->insert(array(
            'type' => 0,
            'uid' => $param['uid'],
            'key' => $param['key'],
            'dateline' => time()
        ));

        $param['city'] = $this->getCity();//城市


        $keyname = md5('D:search:' . implode(':', $param));


        if (!array_key_exists('key', $param)) {
            return $this->jsonResponseError('类型不正确');
        }


        $data = RedCache::fromCacheData($keyname, function () use ($param) {
            return $this->getAll($param);
        }, 120, true);

        if(!$data){
            $data=array();
        }
        return $this->jsonResponse($data);


    }

    //3.3.1 新搜索
    public function newsearchAction()
    {
        //todo 缺少count
        if (!$this->pass(false)) {
            return $this->failRequest();
        }

        $param['uid'] = $this->getParams('uid');
        $param['key'] = $this->getParams('word');

        $page = (int)$this->getParams('page', 1);
        $param['limit'] = (int)$this->getParams('page_num', 5);
        $param['start'] = ($page - 1) * $param['limit'];

        //  插入搜索记录
        $this->_getPlaySearchLogTable()->insert(array(
            'type' => 0,
            'uid' => $param['uid'],
            'key' => $param['key'],
            'dateline' => time()
        ));
        $db = $this->_getAdapter();
        $res = $db->query("select * from search_tmp WHERE  city=? AND `key` LIKE ? limit ?,?", array($this->getCity(),"%{$param['key']}%", $param['start'], $param['limit']))->toArray();
        foreach ($res as $k => $v) {
            $list=json_decode($res[$k]['ticket_list'], true);
            $res[$k]['ticket_list'] = is_array($list)?$list:array();
        }
        return $this->jsonResponse($res);


    }

    //游玩地标题+商品标题
    public function getAll($param)
    {

        $timer = time();

        $where = "play_shop.shop_status=0 AND play_shop.shop_city = \"{$param['city']}\" AND (play_organizer_game.title like \"%{$param['key']}%\" OR play_shop.shop_name like \"%{$param['key']}%\" )";

        $order = 'play_organizer_game.click_num DESC';

        $sql = "SELECT
play_shop.shop_city,
play_organizer_game.id,
play_shop.shop_name,
play_shop.busniess_circle,
play_shop.shop_id,
play_shop.addr_x,
play_shop.addr_y,
play_shop.thumbnails,
play_shop.editor_word
FROM
play_shop
LEFT JOIN play_game_info ON (play_shop.shop_id =play_game_info.shop_id AND play_game_info.status >= 1)
LEFT JOIN play_organizer_game ON (play_game_info.gid = play_organizer_game.id AND play_organizer_game.status > 0 AND play_organizer_game.end_time >= {$timer} AND play_organizer_game.start_time <= {$timer})
WHERE
{$where}
GROUP BY
play_shop.shop_id
ORDER BY
{$order}
LIMIT {$param['start']}, {$param['limit']}
";

        $res = $this->query($sql);


        $placeCache = new PlaceCache();
        $places = $shopids = array();
        foreach ($res as $val) {
            $places[] = array(
                'id' => $val['shop_id'],
                'cover' => $this->_getConfig()['url'] . $val['thumbnails'],
                'circle' => CouponCache::getBusniessCircle($val['busniess_circle']),
                'title' => $val['shop_name'],
                'editor_word' => $val['editor_word'],
                'addr_x' => $val['addr_x'],
                'addr_y' => $val['addr_y'],
            );
            $shopids[] = $val['shop_id'];
        }

        $shopids = implode(',', $shopids);

        if ('' === $shopids) {
            $shopids = '1';
        }

        $good_where = "play_game_info.shop_id in ({$shopids}) AND play_organizer_game.status > 0 and play_organizer_game.end_time >= {$timer} AND play_organizer_game.start_time <= {$timer}";


        $order = 'play_organizer_game.click_num DESC';
        $good_sql = "SELECT
play_shop.label_id,
play_shop.shop_id,
play_organizer_game.id AS gid,
play_organizer_game.title,
play_organizer_game.thumb,
play_organizer_game.down_time,
play_organizer_game.foot_time,
play_organizer_game.low_price,
play_organizer_game.low_money,
play_organizer_game.ticket_num,
play_organizer_game.buy_num,
play_organizer_game.g_buy,
play_organizer_game.is_together
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

            if (isset($game_id_list[$g_data['shop_id']])) {
                foreach ($game_id_list[$g_data['shop_id']] as $v) { //检查是否出现过
                    if ($v['ticket_name'] == $g_data['title']) {
                        $log = true;
                        break;
                    }
                }
            }


            if (!$log) {
                $game_id_list[$g_data['shop_id']][] = array(
                    'id' => $g_data['gid'],
                    'ticket_name' => $g_data['title'],
                    'price' => $g_data['low_price'] ?: '0',
                    'low_money' => $g_data['low_money'] ?: '0',
                    'surplus' => ($g_data['ticket_num'] - $g_data['buy_num']),
                    'coupon_have' => (($g_data['ticket_num'] > $g_data['buy_num']) && $g_data['foot_time'] > $timer && $g_data['is_together'] === 1) ? 1 : 0,
                    'g_buy' => (($g_data['down_time'] - time()) > 86400) ? $g_data['g_buy'] : '0',
                );

                if (empty($param['key']) || strripos($g_data['title'], $param['key']) !== false) {//将包含关键字的票加入游玩地下面
                    if (count($game_id_list[$g_data['shop_id']] > 2)) {
                        //放到前面
                        $tmp_arr = $game_id_list[$g_data['shop_id']][0];
                        $game_id_list[$g_data['shop_id']][0] = $game_id_list[$g_data['shop_id']][count($game_id_list[$g_data['shop_id']]) - 1];
                        $game_id_list[$g_data['shop_id']][count($game_id_list[$g_data['shop_id']]) - 1] = $tmp_arr;
                    }
                }
            }


            //无论是否有关键字都统计
//            $game[$g_data['shop_id']] = array_key_exists($g_data['shop_id'], $game) ? ($game[$g_data['shop_id']]++) : 1;
        }

        foreach ($places as $k => $v) {
            $places[$k]['ticket_list'] = array_key_exists($v['id'], $game_id_list) ? (array_slice($game_id_list[$v['id']], 0, 3)) : array();
            $places[$k]['ticket_num'] = array_key_exists($v['id'], $game_id_list) ? (int)count($game_id_list[$v['id']]) : 0;
        }
        return array('place_list' => $places, 'count' => count($places));
    }

    //商品
    public function getGame($param)
    {
        $time = time();
        $where = array(
            'play_organizer_game.status > ?' => 0,
            'start_time < ?' => $time,
            'end_time > ?' => $time,
            'play_organizer_game.city' => $param['city'],
        );

        $where[] = "play_organizer_game.title like '%{$param['key']}%'";

        $order = array('play_organizer_game.id' => 'DESC');
        $gameData = $this->_getPlayOrganizerGameTable()->fetchLimit(($param['page'] - 1) * $param['limit'], $param['limit'], array(), $where, $order);
        $res = array();
        $timer = time();
        foreach ($gameData as $gValue) {
            $res[] = array(
                'cover' => $this->_getConfig()['url'] . $gValue->thumb,
                'id' => $gValue->id,
                'title' => $gValue->title,
                'price' => $gValue->low_price,
                'ticket_num' => ($gValue->ticket_num - $gValue->buy_num),
                'end_time' => $gValue->end_time,
                'g_buy' => (($gValue->down_time - $timer) > 86400) ? $gValue->g_buy : '0',
            );
        }
        return $res;
    }

    // 游玩地
    public function getLocation($param)
    {
        $start = ($param['page'] - 1) * $param['limit'];
        $sql = "SELECT
play_region.`name`,
play_shop.shop_id,
play_shop.shop_name,
play_shop.thumbnails,
play_shop.reference_price,
play_shop.good_num
FROM
play_shop
LEFT JOIN play_region ON play_shop.busniess_circle = play_region.rid
WHERE
shop_status >= 0 AND play_shop.shop_city = '{$param['city']}' AND play_shop.shop_name like '%{$param['key']}%'
GROUP BY
play_shop.shop_id
ORDER BY
play_shop.shop_id DESC
LIMIT {$start}, {$param['limit']}
";

        $res = $this->query($sql);
        $data = array();
        foreach ($res as $val) {
            $data[] = array(
                'id' => $val['shop_id'],
                'cover' => $this->_getConfig()['url'] . $val['thumbnails'],
                'title' => $val['shop_name'],
                'ticket_num' => $val['good_num'],
                'circle' => $val['name'],
                'reference_price' => $val['reference_price'],
                //'tags' => $val['labels'] ? json_decode($val['labels'], true) : array(),
            );
        }
        return $data;
    }


    //专题
    public function getActivity($param)
    {

        $timer = time();
        $res = array();
        $where = array(
            '(s_time = 0 OR (s_time < ? AND e_time > ?))' => array($timer, $timer),
            'status' => 0,
            'ac_city' => $param['city'],
        );
        $where[] = "play_activity.ac_name like '%{$param['key']}%'";

        $topic_data = $this->_getPlayActivityTable()->fetchLimit(($param['page'] - 1) * $param['limit'], $param['limit'], array(), $where, array('id' => 'DESC'));
        foreach ($topic_data as $topic) {
            $res[] = array(
                'id' => $topic->id,
                'title' => $topic->ac_name,
                'img' => $this->_getConfig()['url'] . $topic->ac_cover
            );
        }
        return $res;
    }

    public function hotPushAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $city = $this->getCity();
        $data = array();
        $keyData = $this->_getPlaySearchFormValueTable()->fetchLimit(0, 10, array(), array('search_type' => 2, 'status' => 1, 'city' => $city), array('dateline' => 'desc'));

        foreach ($keyData as $value) {
            $data[] = $value->val;
        }

        return $this->jsonResponse($data);
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

}
