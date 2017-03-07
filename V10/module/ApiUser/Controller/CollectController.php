<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Apiuser\Controller;

use Deyi\BaseController;
use Deyi\GetCacheData\CouponCache;
use Deyi\GetCacheData\ExcerciseCache;
use Deyi\GetCacheData\GoodCache;
use library\Fun\M;
use library\Service\System\Cache\RedCache;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Http\Response;
use Deyi\JsonResponse;
use Deyi\Mcrypt;

class CollectController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    public function __construct()
    {

    }

    //个人关注
    public function indexAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = (int)$this->getParams('uid');
        if (!$uid) {
            return $this->jsonResponseError('用户id不存在');
        }

        $page = $this->getParams('page', 1);// 页码
        $pageNum = $this->getParams('limit', 5);// 每页多少条
        $start = ($page - 1) * $pageNum;


        $sql = "SELECT
S.labels,
S.shop_name,
S.thumbnails,
S.reference_price,
O.`name` as organizer_name,
O.thumb,
T.link_id,
T.type,
R.`name` as shop_circle,
count(DISTINCT I.gid) as count1,
count(DISTINCT G.id) as count3
FROM
play_user_collect AS T
LEFT JOIN play_shop AS S ON T.link_id = S.shop_id AND T.type = 'shop'
LEFT JOIN play_region AS R ON S.busniess_circle = R.rid
LEFT JOIN play_game_info AS I ON T.link_id = I.shop_id AND T.type = 'shop'
LEFT JOIN play_coupons_linker AS C ON T.link_id = C.shop_id AND T.type = 'shop'
LEFT JOIN play_organizer AS O ON T.link_id = O.id AND T.type = 'organizer'
LEFT JOIN play_organizer_game AS G ON T.link_id = G.organizer_id AND T.type = 'organizer'
WHERE
T.uid = {$uid}
GROUP BY
T.link_id
LIMIT {$start}, {$pageNum}
";
        $result = $this->query($sql);
        $res = array();
        foreach ($result as $data) {
            $res[] = array(
                'id' => $data['link_id'],
                'type' => $data['type'],
                'cover' => ($data['type'] == 'shop') ? $this->_getConfig()['url'] . $data['thumbnails'] : $this->_getConfig()['url'] . $data['thumb'],
                'title' => ($data['type'] == 'shop') ? $data['shop_name'] : $data['organizer_name'],
                'circle' => ($data['type'] == 'shop') ? $data['shop_circle'] : '',
                'number' => ($data['type'] == 'shop') ? $data['count1'] : $data['count3'],
                'reference_price' => ($data['type'] == 'shop') ? $data['reference_price'] : '',
            );
        }
        return $this->jsonResponse($res);
    }


    //收藏列表 只有游玩地
    public function shopListAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = $this->getParams('uid');
        $p = $this->getParams('p', 1);

        $type = $this->getParams('type', 'shop'); //默认游玩地  `shop` 收藏游玩地 `good`商品 `kidsplay` 活动

        $pagenum = $this->getParams('pagenum', 10);

        $offset = ($p - 1) * $pagenum;

        if (!in_array($type, array('shop', 'good', 'kidsplay'))) {
            return $this->jsonResponseError('参数错误');
        }

        if ($type == 'shop') {
            $data = $this->getshops($offset, $pagenum, $uid);
        } elseif ($type == 'good') {
            $data = $this->getgoods($offset, $pagenum, $uid);
        } elseif ($type == 'kidsplay') {
            $data = $this->getkidsplays($offset, $pagenum, $uid);
        }

        return $this->jsonResponse($data);

    }

    //获取收藏的游玩地
    public function getshops($offset, $pagenum, $uid)
    {
        $db = $this->_getAdapter();
        $res = $db->query("select * from play_user_collect left join play_shop on play_shop.shop_id=play_user_collect.link_id WHERE play_user_collect.uid=? and `type`='shop' AND  play_shop.shop_status>=0 ORDER BY id DESC limit {$offset},{$pagenum}", array($uid));
        $data = array();
        foreach ($res as $v) {
            $data[] = array(
                "cover" => $this->getImgUrl($v->thumbnails),
                "id" => $v->link_id,
                "title" => $v->shop_name,
                "price" => $v->reference_price,  //参考价格
                "editor_talk" => $v->editor_word,
                "tag" => GoodCache::getGameTags($v->link_id, ($v['post_award'] == 2)),
                "object_ticket" => $v->link_id,
                "address" => CouponCache::getBusniessCircle($v->busniess_circle)
            );
        }
        return $data;
    }

    //获取收藏的商品
    public function getgoods($offset, $pagenum, $uid)
    {
        $db = $this->_getAdapter();
        $res = $db->query("select * from play_user_collect left join play_organizer_game on play_organizer_game.id=play_user_collect.link_id WHERE play_user_collect.uid=? and play_user_collect.`type`='good' AND  play_organizer_game.start_time<? AND play_organizer_game.status=1 ORDER BY play_user_collect.id DESC limit {$offset},{$pagenum}", array($uid, time()))->toArray();
        $data = array();
        foreach ($res as $val) {
            $data[] = array(
                'coupon_id' => $val['link_id'],
                'cover' => $this->_getConfig()['url'] . $val['thumb'],
                'name' => $val['title'],
                'price' => $val['low_price'] > 0 ? $val['low_price'] : '0.00',
                'have' => (((int)$val['is_together'] === 1) && ($val['down_time'] > time()) && (($val['ticket_num'] - $val['buy_num']) > 0)) ? ($val['ticket_num'] - $val['buy_num']) : 0,
                'end_time' => (int)$val['foot_time'],
                'buy' => $val['buy_num'],
                'low_money' => $val['low_money'] > 0 ? $val['low_money'] : '0:00',
                'g_buy' => (($val['down_time'] - time()) > 86400) ? (int)$val['g_buy'] : 0,

                'residue' => $this->getSurplusNumber($val), //(($val['ticket_num'] - $val['buy_num']) > 0) ? ($val['ticket_num'] - $val['buy_num']) : 0,
                'buy_num' => $val['buy_num'] + $val['coupon_vir'],
                'circle' => $val['shop_addr'],
                'labels' => CouponCache::getCouponLabels($val['link_id'], $val['hot_number'], $val['city']),
            );
        }

        return $data;
    }

    //获取商品实际剩余数,过滤了已过期的
    public function getSurplusNumber($val)
    {
        $num = RedCache::get('D:SurplusNumber:' . $val['link_id']);
        if ($num) {
            return $num;
        } else {
            return ($val['ticket_num'] - $val['buy_num']);
        }

    }

    //获取收藏的活动
    public function getkidsplays($offset, $pagenum, $uid)
    {
        $db = $this->_getAdapter();
        $res = $db->query("select * from play_user_collect left join play_excercise_base on play_excercise_base.id=play_user_collect.link_id WHERE play_user_collect.uid=? and play_user_collect.`type`='kidsplay' AND  release_status=1  ORDER BY play_user_collect.id DESC limit {$offset},{$pagenum}", array($uid))->toArray();
        $data = array();

        foreach ($res as $g) {
            $bid = $g['link_id'];
            $circle = ExcerciseCache::getCircleByBid($bid);
            $events_num = RedCache::fromCacheData('V:Base:enum:' . $bid, function () use ($bid) {
                $data = $this->_getPlayExcerciseEventTable()->fetchCount(['bid' => $bid, 'sell_status' => 1, 'end_time > ?' => time()]);
                return $data;
            }, 1 * 3600);

            $data[] = array(
                "id" => $bid,
                'cover' => $this->getImgUrl($g['thumb']),
                'title' => $g['name'],
                'price' => $g['low_price'],
                'editor_talk' => $g['introduction'],
                'start_time' => $g['max_start_time'],
                'end_time' => $g['min_end_time'],
                'start_date' => $g['max_start_time'],
                'end_date' => $g['min_end_time'],
                'type' => 2,//活动
                'num' => $events_num,//获取场次数量
                'buynum' => $g['join_number'],//报名数
                'circle' => CouponCache::getBusniessCircle($circle),//区域
            );
        }
        return $data;
    }

    function query($sql)
    {
        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $stmt = $db->query($sql);
        $result = $stmt->execute($stmt);
        return $result;
    }

    // 添加收藏
    public function updateAction()
    {

        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = (int)$this->getParams('uid');
        if (!$uid) {
            return $this->jsonResponseError('用户id不存在');
        }

        $type = $this->getParams('type');
        $act = $this->getParams('act');
        $link_id = (int)$this->getParams('link_id');

        if (!in_array($type, array('shop', 'good', 'kidsplay')) || !in_array($act, array('del', 'add')) || !$link_id) {
            return $this->jsonResponseError('参数错误');
        }


        $where = array('uid' => $uid, 'type' => $type, 'link_id' => $link_id);
        if ($act == 'del') {
            $status = M::getPlayUserCollectTable()->unCollect($uid, $type, $link_id);
        }

        if ($act == 'add') {
            $status = M::getPlayUserCollectTable()->getCollect($uid, $type, $link_id);
            if ($status) {
                return $this->jsonResponse(array('status' => 0, 'message' => '已经收藏过啦'));
            }
            $where['add_time'] = time();
            $status = M::getPlayUserCollectTable()->collect($uid,$type,$link_id);
        }

        return $this->jsonResponse(array('status' => 1, 'message' => ($act == 'add') ? '收藏成功' : '已取消收藏'));

    }
}
