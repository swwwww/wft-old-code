<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ApiCashCoupon\Controller;

use Deyi\BaseController;
use Deyi\Coupon\Coupon;
use Deyi\GetCacheData\CouponCache;
use Deyi\GetCacheData\ExcerciseCache;
use Deyi\Invite\Invite;
use Deyi\Mcrypt;
use library\Service\System\Cache\RedCache;
use library\Service\System\Logger;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Response;
use Deyi\JsonResponse;
use Zend\Db\Sql\Expression;
use Deyi\GetCacheData\GoodCache;

class IndexController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    //领券完成后页面
    public function indexAction()
    {

        if (!$this->pass()) {
            return $this->failRequest();
        }

        $page = (int)$this->getParams('page', 1);
        $limit = (int)$this->getParams('page_num', 5);
        $page = ($page > 1) ? $page : 1;
        $cid = $this->getParams('cid'); //票券id
        $city = $this->getCity();

        $coupon = $this->_getCashCouponTable()->get(['id' => $cid]);

        if (!$coupon) {
            return $this->jsonResponse(['status' => 0, 'message' => '现金券不存在！']);
        }

        //城市范围
        if (false !== $coupon && !$coupon->is_main) {//部分城市, 如果用户不在券城市范围类，将没有票券
            $citys = $this->_getCashCouponCityTable()->fetchAll(array('cid' => $cid))->toArray();
            $cids = [];
            if ($citys) {
                foreach ($citys as $c) {
                    $cids[] = $c['city'];
                }
            } else {
                return $this->jsonResponse([]);
            }

            if (!in_array($city, $cids, true)) {
                return $this->jsonResponse([]);
            }
        }

        //商品范围
        if (1 === (int)$coupon->range) {//部分商品
            $goods = $this->_getCashCouponGoodTable()->fetchAll(array(
                'cid' => $cid,
                'object_type' => $coupon->range
            ))->toArray();

            if ($goods) {
                $ids = [];
                foreach ($goods as $g) {
                    $ids[] = $g['object_id'];
                }
            }

            $goods = $this->_getPlayOrganizerGameTable()->getCashGameWithInfo((($page - 1) * $limit), $limit, [],
                [
                    'play_organizer_game.id' => $ids,
                    'play_organizer_game.city' => $city,
                    'play_organizer_game.status > ?' => 0,
                    'excepted' => 0,
                    'play_organizer_game.large_price >= ?' => $coupon->price,
                    'play_organizer_game.start_time <= ?' => time(),
                    'play_organizer_game.end_time >= ?' => time()
                ], ['play_organizer_game.hot_number' => 'DESC'])->toArray();

        } elseif (2 === (int)$coupon->range) {//部分类别
            $types = $this->_getCashCouponGoodTable()->fetchAll(array('cid' => $cid, 'object_type' => $coupon->range));
            foreach ($types as $t) {
                $typeids[] = $t->object_id;
            }
            $good_ids = $this->_getPlayLabelLinkerTable()->fetchLimit(0, 100, ['object_id'],
                array('lid' => $typeids, 'link_type' => 2))->toArray();

            $ids = [];
            if ($good_ids) {
                foreach ($good_ids as $gi) {
                    $ids[] = $gi['object_id'];
                }
            }
            if(!$ids){
                $ids = 0;
            }
            $goods = $this->_getPlayOrganizerGameTable()->getCashGameWithInfo((($page - 1) * $limit), $limit, [],
                [
                    'play_organizer_game.id' => $ids,
                    'play_organizer_game.city' => $city,
                    'play_organizer_game.status > ?' => 0,
                    'excepted' => 0,
                    'play_organizer_game.start_time <= ?' => time(),
                    'play_organizer_game.end_time >= ?' => time(),
                    'play_organizer_game.large_price >= ?' => $coupon->price
                ], ['play_organizer_game.hot_number' => 'DESC'])->toArray();
        } elseif(3 === (int)$coupon->range ){

            $goods = $this->_getPlayExcerciseBaseTable()->fetchLimit((($page - 1) * $limit), $limit, ['*'],
                [
                    'city' => $city,
                    'release_status > ?' => 0,
                    'max_start_time <= ?' => time(),
                    'min_end_time >= ?' => time(),
                    'low_price >= ?' => $coupon->price
                ], ['max_start_time' => 'DESC'])->toArray();

        }elseif(4 === (int)$coupon->range ){
            $ccgt = $this->_getCashCouponGoodTable()->fetchLimit(0,500,[],['cid'=>$cid,'object_type'=>4])->toArray();

            if($ccgt){
                $cs = [];
                foreach($ccgt as $cc){
                    $cs[] = $cc['object_id'];
                }
            }else{
                $cs = 0;
            }

            $goods = $this->_getPlayExcerciseEventTable()->getEventList((($page - 1) * $limit), $limit, ['*'],
                [
                    'play_excercise_base.city' => $city,
                    'play_excercise_event.id' => $cs,
                    'release_status > ?' => 0,
//                    'max_start_time <= ?' => time(),
//                    'min_end_time >= ?' => time(),
                    'low_price >= ?' => $coupon->price
                ], ['max_start_time' => 'DESC']);

        } else {//全场通用
            $goods = $this->_getPlayOrganizerGameTable()->getCashGameWithInfo((($page - 1) * $limit), $limit, [],
                [
                    'play_organizer_game.city' => $city,
                    'excepted' => 0,
                    'play_organizer_game.status > ?' => 0,
                    'play_organizer_game.large_price >= ?' => $coupon->price,
                    'play_organizer_game.start_time <= ?' => time(),
                    'play_organizer_game.end_time >= ?' => time()
                ],
                ['play_organizer_game.hot_number' => 'DESC'])->toArray();
        }

        $data = [];

        if(!$goods){
            return $this->jsonResponse([]);
        }

        if(3 === (int)$coupon->range || 4 === (int)$coupon->range ){
            foreach ($goods as $g) {
                $bid = $g['id'];
                $events_num = RedCache::fromCacheData('V:Base:enum:' . $bid, function () use ($bid) {
                    $data = $this->_getPlayExcerciseEventTable()->fetchCount(['bid' => $bid,'sell_status'=>1,'end_time > ?'=>time()]);

                    return $data;
                }, 1 * 3600);
                $circle = ExcerciseCache::getCircleByBid($g['id']);
                $res = [];
                $res['id'] = (3 === (int)$coupon->range)?$g['id']:$g['bid'];
                $res['cover'] = $this->getImgUrl($g['thumb']);
                $res['title'] = $g['name'];
                $res['price'] = $g['low_price'];
                $res['editor_talk'] = $g['introduction'];
                $res['start_time'] = $g['max_start_time'];
                $res['end_time'] = $g['min_end_time'];
                $res['start_date'] = $g['max_start_time'];
                $res['end_date'] = $g['min_end_time'];
                $res['type'] = 2;//活动
                $res['num'] = $events_num;//获取场次数量
                $res['buynum'] = $g['join_number'];//报名数
                $res['circle'] = CouponCache::getBusniessCircle($circle);//区域
                $data[] = $res;
            }

        }else{
            foreach ($goods as $g) {
                $res = [];
                $res['id'] = $g['id'];
                $res['cover'] = $this->getImgUrl($g['cover']);
                $res['title'] = $g['title'];
                $res['surplus_num'] = $g['ticket_num'] - $g['buy_num']; //剩余票数
                $res['price'] = $g['low_price'];
                $res['realprice'] = $g['low_money'];
                $res['editor_talk'] = $g['editor_talk'];
                $res['start_time'] = $g['start_time'];
                $res['end_time'] = $g['end_time'];
                $res['start_date'] = $g['start_time'];
                $res['end_date'] = $g['end_time'];
                $res['tag'] = GoodCache::getGameTags($g['id'], ($g['post_award'] == 2));
                $res['type'] = 1;//商品
                $data[] = $res;
            }
        }

        return $this->jsonResponse($data);
    }

    //我的优惠券列表
    public function myAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = (int)$this->getParams('uid', 0);
        $page = (int)$this->getParams('page', 1);
        $limit = (int)$this->getParams('pagenum', 5);
        $coupon_id = (int)$this->getParams('coupon_id', 0);// 为显示全场通用
        $pay_price = (float)$this->getParams('pay_price', 0); //需要购买商品的价格

        $type = (int)$this->getParams('type', 1);//1商品 2活动

        $page = ($page > 1) ? $page : 1;

        $city = $this->getCity();

        if ($uid === 0) {
            return $this->jsonResponse(['status' => 0, 'message' => '参数错误！']);
        }

        $offset = (($page - 1) * $limit);

        $db = $this->_getAdapter();

        if ($coupon_id > 0 and $type === 1 ) {
            $link_label = array();
            if ($coupon_id) {
                $link_label_sql  = "select play_label_linker.object_id,play_label.id,play_label.pid  from play_label_linker
left join play_label on play_label.id = play_label_linker.lid where play_label_linker.object_id = ?;";
                $link_label = $db->query($link_label_sql, array($coupon_id))->toArray();
            }
            $or = '';

            if($link_label){
                foreach($link_label as $ll){
                    $or .= ('b.object_id = '.(int)$ll['id']. ' or ');
                    $or .= ('b.object_id = '.(int)$ll['pid']. ' or ');
                }
            }else{
                $or = ('b.object_id = 0 or ');
            }

            $or = rtrim($or,' or ');

            $cc = $db->query("SELECT
	a.*,d.time_type,d.description,d.new
FROM
	play_cashcoupon_user_link AS a
LEFT JOIN play_cashcoupon_good_link AS b ON a.cid = b.cid
LEFT JOIN play_cashcoupon_city AS c ON a.cid = c.cid
LEFT JOIN play_cash_coupon AS d ON a.cid = d.id
WHERE
	(
		(
			(
				b.object_type = 1
				AND b.object_id = ?
			)
			OR (
				b.object_type = 2
				AND ({$or})
			)
		)
		OR d.`range` = 0
	)
AND a.uid = ?
AND a.pay_time = 0
AND a.cid > 0
AND (
	is_main = 1
	OR (is_main = 0 AND c.city = ?)
)
AND a.use_stime <?
AND a.use_etime >?
AND a.price<=?
GROUP BY
	a.id
ORDER BY
	 a.pay_time asc,a.use_etime desc ,a.price DESC,a.create_time DESC
            ", array($coupon_id, $uid, $city, time(), time(), $pay_price))->toArray();
        } elseif($coupon_id > 0 and $type === 2) {

            $cc = $db->query("SELECT
	a.*,d.time_type,d.description,d.new
FROM
	play_cashcoupon_user_link AS a
 LEFT JOIN play_cashcoupon_good_link AS b ON a.cid = b.cid
LEFT JOIN play_cashcoupon_city AS c ON a.cid = c.cid
LEFT JOIN play_cash_coupon AS d ON a.cid = d.id
WHERE ( d.`range` = 3
OR (d.`range` = 4 and (
				b.object_type = 4
				AND b.object_id = ?
			))
)
AND a.uid = ?
AND a.pay_time = 0
AND a.cid > 0
AND (
	is_main = 1
	OR (is_main = 0 AND c.city = ?)
)
AND a.use_stime <?
AND a.use_etime >?
AND a.price<=?
GROUP BY
	a.id
ORDER BY
	a.pay_time ASC,a.use_etime desc ,a.price DESC,a.create_time DESC
            ", array($coupon_id,$uid, $city, time(), time(), $pay_price))->toArray();
        }else{
            $cc = $db->query("
SELECT
	a.*,b.time_type,b.description,b.new
FROM
	play_cashcoupon_user_link AS a
LEFT JOIN play_cash_coupon AS b ON a.cid = b.id
WHERE
  a.cid > 0
AND
  a.uid = ?
ORDER BY
	a.pay_time ASC ,a.use_etime desc,a.price DESC ,a.create_time DESC
limit ?,?
        ", array($uid, $offset, $limit))->toArray();
        }

        $data = array();
        foreach ($cc as $c) {
            $isvalid = 0;
            if ($c['use_etime'] > time() && $c['use_stime'] < time()) {
                $isvalid = 1;
            }
            if($c['pay_time'] > 0){
                $isvalid=0;
            }

            if($c['use_etime'] < time()){
                $isvalid=0;
            }


            $data[] = [
                'id' => $c['id'],
                'title' => $c['title'],
                'price' => $c['price'],
                'begin_time' => $c['use_stime'],
                'end_time' => $c['use_etime'],
                'description' => CouponCache::getCouponDesc($c['cid']),
                'time_type' => (int)$c['time_type'],
                'isvalid' => $isvalid,//0已过期，1为未使用，2为已使用
                'isnew'=>$c['new'] ? : 0,//1为新用户专享
            ];
        }
        $after = $data;
        usort($after,function($a,$b){
            if ($a['isvalid']===$b['isvalid']) return 0;
            return ($a['isvalid']>$b['isvalid'])?-1:1;
        });

        return $this->jsonResponse($after);
    }



    //领券完成后页面
    public function nindexAction()
    {

        if (!$this->pass()) {
            return $this->failRequest();
        }

        $page = (int)$this->getParams('page', 1);
        $limit = (int)$this->getParams('page_num', 5);
        $page = ($page > 1) ? $page : 1;
        $cid = $this->getParams('cid'); //票券id
        $id = $this->getParams('id'); //领券id

        $city = $this->getCity();

        $events_num = 0;//针对场次是使用


        $coupon = $this->_getCashCouponTable()->get(['id' => $cid]);

        if (!$coupon) {
//            Logger::writeLog($cid);
            return $this->jsonResponse(['status' => 0, 'message' => '现金券不存在！']);
        }


        if($id){
            $usercash = $this->_getCashCouponUserTable()->fetchLimit(0,1,[],['id'=>$id])->current();

            if(!$usercash){
                return $this->jsonResponse(['status' => 0, 'message' => '您没有这张现金券！']);
            }

            $ispass = $ispay = 0;
            $isvalid = 0;
            if ($usercash->use_etime > time() && $usercash->use_stime < time()) {
                $isvalid = 1;
            }
            if($usercash->pay_time>0){
               // $isvalid=2;
                $ispay = 1;
            }
            if($usercash->use_etime < time()){
                $ispass = 1;
            }

            if((int)$coupon->range === 4){
                $events = $this->_getPlayExcerciseEventTable()->getEventByCash($cid,10,0)->toArray();
            }else{
                $events = [];
            }

            if(in_array($coupon->range,[0,1,2])){
                $data_arr['type'] = 1;
            }else{
                $data_arr['type'] = 2;
            }

            $data_arr['title'] = $coupon->title;
            $data_arr['description'] = CouponCache::getCouponDesc($cid);
            $data_arr['price'] = $usercash->price;
            $data_arr['time_type'] = $coupon->time_type;
            $data_arr['begin_time'] = (int)$usercash->use_stime;
            $data_arr['end_time'] = (int)$usercash->use_etime;
            $data_arr['isvalid'] = $isvalid;
            $data_arr['isnew'] = $coupon->new;
            $data_arr['is_pass'] = (int)$ispass;
            $data_arr['is_pay'] = (int)$ispay;
            $data_arr['eventinfo'] = $events?:[];
        }

        //城市范围
        if (false !== $coupon && !$coupon->is_main) {//部分城市, 如果用户不在券城市范围类，将没有票券
            $citys = $this->_getCashCouponCityTable()->fetchAll(array('cid' => $cid))->toArray();
            $cids = [];
            if ($citys) {
                foreach ($citys as $c) {
                    $cids[] = $c['city'];
                }
            } else {
                return $this->jsonResponse($data_arr);
            }

            if (!in_array($city, $cids, true)) {
                return $this->jsonResponse($data_arr);
            }
        }

        //商品范围
        if (1 === (int)$coupon->range) {//部分商品
            $goods = $this->_getCashCouponGoodTable()->fetchAll(array(
                'cid' => $cid,
                'object_type' => $coupon->range
            ))->toArray();

            if ($goods) {
                $ids = [];
                foreach ($goods as $g) {
                    $ids[] = $g['object_id'];
                }
            }

            $goods = $this->_getPlayOrganizerGameTable()->getCashGameWithInfo((($page - 1) * $limit), $limit, [],
                [
				//结束购买，售空
                    'play_organizer_game.id' => $ids,
                    'play_organizer_game.city' => $city,
                    'play_organizer_game.status > ?' => 0,
                    'play_organizer_game.down_time > ?' => time(),
                    'play_organizer_game.ticket_num - play_organizer_game.buy_num > ?' => 0,
                    'excepted' => 0,
                    'play_organizer_game.end_time >= ?' => time()
                ], ['play_organizer_game.hot_number' => 'DESC'])->toArray();

        } elseif (2 === (int)$coupon->range) {//部分类别
            $types = $this->_getCashCouponGoodTable()->fetchAll(array('cid' => $cid, 'object_type' => $coupon->range));
            foreach ($types as $t) {
                $typeids[] = $t->object_id;
            }
            $good_ids = $this->_getPlayLabelLinkerTable()->fetchLimit(0, 2000, ['object_id'],
                array('lid' => $typeids, 'link_type' => 2))->toArray();

            $ids = [];
            if ($good_ids) {
                foreach ($good_ids as $gi) {
                    $ids[] = $gi['object_id'];
                }
            }
            if(!$ids){
                $ids = 0;
            }

            $goods = $this->_getPlayOrganizerGameTable()->getCashGameWithInfo((($page - 1) * $limit), $limit, [],
                [
				//结束购买，售空
                    'play_organizer_game.id' => $ids,
                    'play_organizer_game.city' => $city,
                    'play_organizer_game.status > ?' => 0,
                    'excepted' => 0,
                    'play_organizer_game.down_time > ?' => time(),
                    'play_organizer_game.ticket_num - play_organizer_game.buy_num > ?' => 0,
                    'play_organizer_game.end_time >= ?' => time()
                ], ['play_organizer_game.hot_number' => 'DESC'])->toArray();
        } elseif(3 === (int)$coupon->range ){

            $goods = $this->_getPlayExcerciseBaseTable()->getCashUseList((($page - 1) * $limit), $limit, ['*'],
                [
                    'play_excercise_base.city' => $city,
                    'play_excercise_base.release_status > ?' => 0,
                    'play_excercise_base.min_end_time >= ?' => time(),
                    'play_excercise_event.excepted'=>0,
                    'play_excercise_event.customize'=>0,
                ], ['play_excercise_base.max_start_time' => 'DESC'])->toArray();

        }elseif(4 === (int)$coupon->range ){//可以使用的场次
            $events = $this->_getPlayExcerciseEventTable()->getEventByCash($cid)->toArray();
            if($events){
                $cs = [];
                foreach($events as $e){

                    $cs[] = $e['bid'];
                }
            }else{
                $cs = 0;
            }
            $goods = $this->_getPlayExcerciseBaseTable()->getCashUseList((($page - 1) * $limit), $limit, ['*'],
                [
                    'play_excercise_base.city' => $city,
                    'play_excercise_base.id' => $cs,
                    'play_excercise_base.release_status > ?' => 0,
                    'play_excercise_base.min_end_time >= ?' => time(),
                    'play_excercise_event.excepted'=>0,
                    'play_excercise_event.customize'=>0,
                ], ['play_excercise_base.max_start_time' => 'DESC'])->toArray();

        } else {//全场通用
            $goods = $this->_getPlayOrganizerGameTable()->getCashGameWithInfo((($page - 1) * $limit), $limit, [],
                //结束购买，售空
				['city' => $city,
                    'play_organizer_game.status > ?' => 0,
                    'play_organizer_game.down_time > ?' => time(),
                    'play_organizer_game.end_time >= ?' => time(),
                    'excepted' => 0,
                    'play_organizer_game.ticket_num - play_organizer_game.buy_num <> ?' => 0
                ],
                ['play_organizer_game.hot_number' => 'DESC'])->toArray();
        }

        $data = [];

        if(!$goods){
            $data_arr['list'] = $data;
            return $this->jsonResponse($data_arr);
        }

        if(3 === (int)$coupon->range || 4 === (int)$coupon->range ){
            foreach ($goods as $g) {
				$bid = $g['id'];
				$events_num = RedCache::fromCacheData('V10:Fy:enum:' . $bid, function () use ($bid) {
					$data = (int)$this->_getPlayExcerciseEventTable()->session($bid);
					return $data;
				}, 1 * 3600, true);
                $circle = ExcerciseCache::getCircleByBid($g['id']);
                $res = [];
                $res['id'] = $g['id'];
                $res['cover'] = $this->getImgUrl($g['thumb']);
                $res['title'] = $g['name'];
                $res['price'] = $g['low_price'];
                $res['start_time'] = $g['max_start_time'];
                $res['start_date'] = $g['max_start_time'];
                $res['end_time'] = $g['min_end_time'];
                $res['end_date'] = $g['min_end_time'];
                $res['type'] = 2;//活动
                $res['num'] = $events_num;//获取场次数量
                $res['buynum'] = $g['join_number'];//报名数
                $res['circle'] = CouponCache::getBusniessCircle($circle);//区域
                $data[] = $res;
            }

        }else{
            foreach ($goods as $g) {
                $res = [];
                $res['id'] = $g['id'];
                $res['cover'] = $this->getImgUrl($g['cover']);
                $res['title'] = $g['title'];
                $res['surplus_num'] = $g['ticket_num'] - $g['buy_num']; //剩余票数
                $res['buynum'] = $g['ticket_num'] - $g['buy_num']; //剩余票数(ios)
                $res['price'] = $g['low_price'];
                $res['realprice'] = $g['low_money'];
                $res['editor_talk'] = $g['editor_talk'];
                $res['start_time'] = $g['start_time'];
                $res['start_date'] = $g['start_time'];
                $res['end_time'] = $g['end_time'];
                $res['end_date'] = $g['end_time'];
                $res['tag'] = GoodCache::getGameTags($g['id'], ($g['post_award'] == 2));
                $res['type'] = 1;//商品
                $data[] = $res;
            }
        }

        $data_arr['list'] = $data;

        return $this->jsonResponse($data_arr);
    }

    //我的优惠券列表
    public function nmyAction() {
        if (!$this->pass(false)) {
            return $this->failRequest();
        }

        $uid       = (int)$this->getParams('uid', 0);
        $page      = (int)$this->getParams('page', 1);
        $limit     = (int)$this->getParams('pagenum', 5);
        $info_id = (int)$this->getParams('info_id', 0);// 商品套系id
        $coupon_id = (int)$this->getParams('coupon_id', 0);// 商品活动id
        $pay_price = (float)$this->getParams('pay_price', 0); //需要购买商品的价格
        $type      = (int)$this->getParams('type', 0);//1商品 2活动

        $page = ($page > 1) ? $page : 1;
        $city = $this->getCity();

        if ($uid === 0) {
            return $this->jsonResponse(['status' => 0, 'message' => '参数错误！']);
        }

        $offset = (($page - 1) * $limit);

        $db = $this->_getAdapter();

        if ($coupon_id > 0 and $type === 1 ) {
            $link_label = array();
            if($info_id){
                $info_sql = "select id,excepted from play_game_info WHERE id = ?;";
                $info     = $db->query($info_sql, array($info_id))->current();
                if($info and $info->excepted){
                    return $this->jsonResponse([]);
                }
            }
            if ($coupon_id) {
                $link_label_sql = "select play_label_linker.object_id,play_label.id,play_label.pid from play_label_linker left join play_label on play_label.id = play_label_linker.lid where play_label_linker.object_id = ?;";
                $link_label     = $db->query($link_label_sql, array($coupon_id))->toArray();
            }

            $or = '';

            if($link_label){
                foreach($link_label as $ll){
                    $or .= ('b.object_id = '.(int)$ll['id']. ' or ');
                    $or .= ('b.object_id = '.(int)$ll['pid']. ' or ');
                }
            }else{
                $or = ('b.object_id = 0 or ');
            }

            $or = rtrim($or,' or ');

            $cc = $db->query("SELECT
	a.*,d.time_type,d.description,d.new,d.range
FROM
	play_cashcoupon_user_link AS a
LEFT JOIN play_cashcoupon_good_link AS b ON a.cid = b.cid
LEFT JOIN play_cashcoupon_city AS c ON a.cid = c.cid
LEFT JOIN play_cash_coupon AS d ON a.cid = d.id
WHERE
	(
		(
			(
				b.object_type = 1
				AND b.object_id = ?
			)
			OR (
				b.object_type = 2
				AND ({$or})
			)
		)
		OR d.`range` = 0
	)
AND a.uid = ?
AND a.cid > 0
AND a.pay_time = 0
AND (
	is_main = 1
	OR (is_main = 0 AND c.city = ?)
)
AND a.use_stime <?
AND a.use_etime >?
AND a.price<=?
GROUP BY
	a.id
ORDER BY
	a.price DESC,a.pay_time asc,a.use_etime desc ,a.create_time DESC
            ", array($coupon_id, $uid, $city, time(), time(), $pay_price))->toArray();
        } elseif($coupon_id > 0 and $type === 2) {
            $event_sql = "select id,excepted from play_excercise_event WHERE id = ?;";
            $info     = $db->query($event_sql, array($coupon_id))->current();
            if($info and $info->excepted){
                return $this->jsonResponse([]);
            }
            $cc = $db->query("SELECT
	a.*,d.time_type,d.description,d.new,d.range
FROM
	play_cashcoupon_user_link AS a
 LEFT JOIN play_cashcoupon_good_link AS b ON a.cid = b.cid
LEFT JOIN play_cashcoupon_city AS c ON a.cid = c.cid
LEFT JOIN play_cash_coupon AS d ON a.cid = d.id
WHERE ( d.`range` = 3
OR (d.`range` = 4 and (
				b.object_type = 4
				AND b.object_id = ?
			))
)
AND a.uid = ?
AND a.cid > 0
AND a.pay_time = 0
AND (
	is_main = 1
	OR (is_main = 0 AND c.city = ?)
)
AND a.use_stime <?
AND a.use_etime >?
AND a.price<=?
GROUP BY
	a.id
ORDER BY
	a.price DESC,a.pay_time asc,a.use_etime desc ,a.create_time DESC
            ", array($coupon_id,$uid, $city, time(), time(), $pay_price))->toArray();
        }else{
            $cc = $db->query("
SELECT
	a.*,b.time_type,b.description,b.new,b.range
FROM
	play_cashcoupon_user_link AS a
LEFT JOIN play_cash_coupon AS b ON a.cid = b.id
WHERE
  a.cid > 0
AND
  a.uid = ?
ORDER BY
	a.pay_time asc,a.use_etime desc,a.price DESC ,a.create_time DESC
limit ?,?
        ", array($uid, $offset, $limit))->toArray();
        }
        $allcids = [];//所有的现金券id
        $data = array();
        foreach ($cc as $c) {
            $ispass = $ispay = 0;
            $isvalid = 0;
            if ( $c['use_etime'] > time()) {  //wwjie 修改显示 只判断结束时间 10.31
                $isvalid = 1;
            }
            if($c['pay_time']>0){
                $ispay = 1;
                $isvalid = 0;
            }
            if($c['use_etime'] < time()){
                $ispass = 1;
                $isvalid = 0;
            }

            $allcids[] = $c['cid'];
            $data[] = [
                'id' => (int)$c['id'],
                'cid' => (int)$c['cid'],
                'title' => $c['title'],
                'price' => $c['price'],
                'begin_time' => (int)$c['use_stime'],
                'end_time' => (int)$c['use_etime'],
                'description' => CouponCache::getCouponDesc($c['cid']),
                'time_type' => (int)$c['time_type'],
                'isvalid' => $isvalid ?1: 0,
                'eventinfo' => [],
                'isnew'=>(int)$c['new'] ? : 0,//1为新用户专享
                'is_pass' => (int)$ispass,
                'is_pay' => (int)$ispay,
                'range' => (int)$c['range'],
                'type' => $c['range']>2?2:1
            ];
        }

        $event = [];
        if(count($allcids)){
            $allevent = $this->_getPlayExcerciseEventTable()->getEventByCash($allcids,0,0);
            if(count($allevent) and $isvalid){
                foreach($allevent as $a){
                    unset($a['sell_status'],$a['over_time'],$a['open_time'],$a['most_number'],
                        $a['join_number'],$a['least_number'],$a['perfect_number'],$a['shop_name']);
                    $a['start_time_wx'] = date('Y年m月d日',$a['start_time']);
                    $a['end_time_wx'] = date('Y年m月d日',$a['end_time']);
                    $event[$a['cid']][] = $a;
                }
            }
        }

        $after = $data;

        if(count($event)){
            foreach($after as $k => $f){
                if((int)$f['range']===4){
                    $after[$k]['eventinfo'] = $event[$f['cid']]?:[];
                }
            }
        }

        if($coupon_id>0){
            //金额排序
            $sort=array();
            foreach ($after as $v){
                $sort[]=(float)$v['price'];
                $sort2[]=$v['end_time'];
            }
            array_multisort($sort,SORT_DESC,$sort2,SORT_DESC,$after);
        }else{

            //失效时间排序
            $sort=array();
            $sort2=array();
            foreach ($after as $v){
                $sort[]=$v['end_time'];
                $sort2[]=$v['isvalid'];
            }

            array_multisort($sort2,SORT_DESC,$sort,SORT_DESC,$after);
        }

//        Logger::writeLog(json_encode($after,JSON_UNESCAPED_UNICODE));

        return $this->jsonResponse($after);
    }

    //现金券兑换
    public function exchangeAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }
        $city = $this->getCity();
        $code = trim($this->getParams('code', ''));
        $uid = (int)$this->getParams('uid', 0);

        if(mb_strlen($code)<1){
            return $this->jsonResponse(['status' => 0, 'message' => '请输入兑换码']);
        }

        if ('' === $code || !$uid) {
            return $this->jsonResponse(['status' => 0, 'message' => '请输入兑换码']);
        }

        $code = urlencode($code);

        $cc = new Coupon();
        $status = $cc->exchange($code, $uid, $city);

        if (!array_key_exists('status', $status)) {
            $db = $this->_getAdapter();
            $sql = "select * from play_cashcoupon_user_link where cid = {$status['id']} and uid = {$uid} order by id desc limit 1";
            $uc = $db->query($sql,array())->current();
            $linkid = (int)$uc->id;

//            Logger::writeLog(['id' => $status['id'], 'linkid'=>$linkid,'title' => $status['title'], 'status' => 1]);

            return $this->jsonResponse(['id' => $status['id'], 'linkid'=>$linkid,'title' => $status['title'], 'status' => 1]);
        } else {
            return $this->jsonResponse($status);
        }
    }

    //现金券
    public function fetchAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }
        $city = $this->getCity();
        $id = (int)($this->getParams('id', 0));
        $uid = $this->getParams('uid', 0);
        $msg = $this->getParams('msg', '');

        if($id<1){
            return $this->jsonResponse(['status' => 0, 'message' => '请输入兑换码']);
        }

        if (0 === $id || (int)$uid === 0) {
            return $this->jsonResponse(['status' => 0, 'message' => '请输入兑换码']);
        }

        $cc = new Coupon();
        $status = $cc->getByid($id, $uid, $city,$msg);

        if (!array_key_exists('status', $status)) {
            return $this->jsonResponse(['id' => $status['id'], 'title' => $status['title'], 'status' => 1]);
        } else {
            return $this->jsonResponse($status);
        }
    }

    /**
     * 分享参加遛娃页面
     * @return \Zend\View\Model\JsonModel
     */
    public function sharekidsAction(){
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $city = $this->getCity();
        $sn = (int)($this->getParams('sn', ''));
        $uid = $this->getParams('uid', 0);

        //RedCache::del('D:share_cash:' . $city);
        $options = (object)RedCache::fromCacheData('D:share_cash:1' . $city, function () use ($city) {
            $data = $this->_getPlayCashShareTable()->get(['city' => $city,'type'=>1]);
            return $data;
        }, 24 * 3600 * 7, true);

        $opt = json_decode($options->options);

        $db = $this->_getAdapter();
        //判断是否购买过当前商品
        $sql = 'select * from play_order_info where order_sn = ? and user_id = ? limit 1';

        $order = $db->query($sql, array($sn,$uid))->current();

        if ($order and ((int)$order->pay_status === 2 || (int)$order->pay_status === 5) ) {
            $ower = $order;
        } else {
            $ower = false;
        }

        $cv = 0;
        if ($opt) {
            foreach ($opt as $o) {
                $price = $o[0];
                $pay = explode('-', $price);
                $cv = 0;
                if ($ower->realpay >= $pay[0] and $ower->realpay <= $pay[1]) {
                    //分享者获得现金券
                    $share_cc = explode(',', $o[1]);
                    foreach ($share_cc as $sc) {
                        $cashv = (array)RedCache::fromCacheData('D:cashv:' . $sc, function () use ($sc) {
                            $data = $this->_getPlayAdminCashTable()->get(['id' => $sc]);
                            return $data;
                        }, 24 * 3600 * 7, true);
                        $cv += $cashv['price'];
                    }

                    break;
                }
            }
        }else{
            $cv = 0;
        }

        $view = array(
            'title' => $ower->coupon_name,
            'share_title'=>$options->title,
            'share_content'=>$options->content,
            'share_img'=>$options->shareicon,
            'share_url'=>$this->_getConfig()['url'].'/web/generalize/winner?i='.$uid.'&s='.$sn,
            'share_feedback'=>[array('image'=>$options->shareicon,'title'=>$options->title)],
            'share_type' => [1,2],
        );

        return $this->jsonResponse($view);
    }


    /**
     * 购买成功分享页面
     * @return \Zend\View\Model\JsonModel
     */
    public function afterbuyAction(){
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $city = $this->getCity();
        $sn = (int)($this->getParams('sn', ''));
        $uid = $this->getParams('uid', 0);

        $options = (object)RedCache::fromCacheData('D:share_cash:1' . $city, function () use ($city) {
            $data = $this->_getPlayCashShareTable()->get(['city' => $city,'type'=>1]);
            return $data;
        }, 24 * 3600 * 7, true);

        $opt = json_decode($options->options);

        $db = $this->_getAdapter();
        //判断是否购买过当前商品
        $sql = 'select * from play_order_info where order_sn = ? and user_id = ? limit 1';

        $order = $db->query($sql, array($sn,$uid))->current();

        if ($order and ((int)$order->pay_status === 2 || (int)$order->pay_status === 5 || (int)$order->pay_status === 6 || (int)$order->pay_status === 7) ) {
            $ower = $order;
        } else {
            $ower = false;
        }

        $cv = 0;
        if ($opt and $ower) {
            $money = bcadd($ower->real_pay,$ower->account_money, 2);

            foreach ($opt as $o) {
                $price = $o[0];
                $pay = explode('-', $price);
                if ($money >= $pay[0] and $money < $pay[1]) {
                    //分享者获得现金券
                    $share_cc = explode(',', $o[1]);
                    foreach ($share_cc as $sc) {
                        RedCache::del('D:cashv:' . $sc);
                        $cashv = (array)RedCache::fromCacheData('D:cashv:' . $sc, function () use ($sc) {
                            $data = $this->_getCashCouponTable()->get(['id' => $sc]);
                            return $data;
                        }, 24 * 3600 , true);
                        $cv += $cashv['price'];
                    }

                    break;
                }
            }
        }else{
            $cv = 0;
        }

//        if(!$cv){
//            return false;
//        }

        $view = array(
            'title' => $ower->coupon_name,
            'share_title'=>$options->title,
            'share_content'=>$options->content,
            'share_img'=>$this->_getConfig()['url'].$options->shareicon,
            'share_url'=>$this->_getConfig()['url'].'/web/generalize/winner?sid='.$sn,
            'image'=>$this->_getConfig()['url'].$options->afterbuy,
            'tips'=>'分享现金红包给你的好友你将获得'.$cv.'元现金券',
            'share_type' => [1,2],
        );

        return $this->jsonResponse($view);
    }

    //更改参加活动后
    public function afterplayAction(){
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $city = $this->getCity();
        $sn = (int)($this->getParams('order_id', ''));
        $uid = $this->getParams('uid', 0);

        $invite = new Invite();
        $view = $invite->EventShareInfo($sn,$uid,$city);

        if(!$view){
            return false;
        }

        return $this->jsonResponse($view);
    }

    /**
     * 解密uid 判断用户是否登录
     * @return bool|int
     */
    private function getUid()
    {
        if (!isset($_GET['p']) or !$_GET['p']) {
            return false;
        }
        $p = preg_replace(['/-/', '/_/'], ['+', '/'], $_GET['p']);
        $encryption = new Mcrypt();
        $data = json_decode($encryption->decrypt($p));//对象数组  uid and timestamp

        if ($data && property_exists($data, 'uid')) {
            $uid = $data->uid;
        } else {
            $uid = 0;
        }

        return $uid;
    }

    private function query($sql)
    {
        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $stmt = $db->query($sql);
        $result = $stmt->execute($stmt);
        return $result;
    }
}
