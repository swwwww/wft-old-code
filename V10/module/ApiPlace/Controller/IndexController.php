<?php

namespace ApiPlace\Controller;

use Deyi\BaseController;
use Deyi\GetCacheData\CouponCache;
use Deyi\GetCacheData\GoodCache;
use Deyi\GetCacheData\PlaceCache;
use library\Fun\M;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Response;
use Deyi\JsonResponse;

class IndexController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    //游玩地详情
    public function indexAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $id = (int)$this->getParams('id');
        $uid = (int)$this->getParams('uid', 0);
        $time = time();

        $placeData = $this->_getPlayShopTable()->get(array('shop_id' => $id));

        //记录游玩地点击数
        if (!$placeData) {
            return $this->jsonResponseError('该游玩地 不存在');
        }


        //游玩地详情
        $res = array(
            'cover' => $placeData->cover ? $this->_getConfig()['url'] . $placeData->cover : '',
            'title' => $placeData->shop_name,
            'circle' => CouponCache::getBusniessCircle($placeData->busniess_circle, $placeData->shop_city),
            'address' => $placeData->shop_address,
            'phone' => $placeData->shop_phone,
            'open_time' => $placeData->open_time,
            'age_for' => ((int)$placeData->age_max === 100) ? ($placeData->age_min . '岁及以上') : ($placeData->age_min . '岁到' . $placeData->age_max . '岁'),
            'addr_x' => $placeData->addr_x,
            'addr_y' => $placeData->addr_y,
            'reference_price' => $placeData->reference_price,
            'share_img' => $this->_getConfig()['url'] . $placeData->thumbnails,
            'share_title' => $placeData->share_title ? $placeData->share_title : '遛娃好去处，' . $placeData->shop_name,
            'share_content' => strip_tags($placeData->editor_word),
            'buy_number' => 0,
        );

        if ($uid) {
            $flag = M::getPlayUserCollectTable()->getCollect($uid,'shop',$id);
            if ($flag) {
                $res['is_collect'] = 1;
            }
        } else {
            $res['is_collect'] = 0;
        }

        //游玩地攻略
        $res['strategy_list'] = array();

        $strategyDate = $this->_getPlayShopStrategyTable()->fetchAll(['sid' => $id, 'status > ?' => 0], ['status' => 'desc', 'id' => 'desc'])->toArray();

        if (count($strategyDate)) {
            foreach ($strategyDate as $s) {
                $res['strategy_list'][] = array(
                    'strategy_id' => $s['id'],
                    'strategy_uid' => $s['give_uid'],
                    'strategy_title' => $s['title'],
                    'strategy_image' => $this->_getConfig()['url'] . $s['give_image'],
                    'suit_month' => $s['suit_month'] ? $s['suit_month'] : '任何',
                );
            }
        }
        $res['strategy_num'] = count($strategyDate);

        // 游玩地评论
        //游玩地下面的所有商品
        $game_list = $this->_getPlayGameInfoTable()->fetchAll(['shop_id'=>$id],[],100)->toArray();

        $game_id_list = [];
        if(count($game_list)){
            foreach($game_list as $gl){
                $game_id_list[] = $gl['gid'];
            }
        }else{
            $game_id_list = [];
        }

        // 游玩地评论
        $res['whole_score'] = $placeData->star_num;
        $where = array(
            '$or' => array(
                array('msg_type' => 2, 'object_data.object_id' => array('$in' => $game_id_list)), // 是否取出游玩地下 商品的评论
                array('msg_type' => 3, 'object_data.object_id' => $id),
                array('msg_type' => 2, 'object_data.object_shop_id' => $id)
            ),
            'status' => array('$gt' => 0)
        );
        $post_data = $this->_getMdbSocialCircleMsg()->find($where)->sort(array('status' => -1, 'like_number' => -1, '_id' => -1))->limit(10);

        $res['post_number'] = $this->_getMdbSocialCircleMsg()->find($where)->count();

        if ($post_data->count()) {

            foreach ($post_data as $v) {
                $is_like = 0;
                if ($uid) {
                    $like_data = $this->_getMdbSocialPrise()->findOne([
                        'uid' => (int)$uid, //用户id
                        'type' => 1, //类型 圈子消息
                        'object_id' => (string)$v['_id'],
                    ]);

                    if ($like_data) {
                        $is_like = 1;
                    }
                }
                $res['post_list'][] = array(
                    'id' => (string)$v['_id'],
                    'uid' => $v['uid'],
                    'author' => $this->hidePhoneNumber($v['username']),
                    'author_img' => $this->getImgUrl($v['img']),
                    'dateline' => $v['dateline'],
                    'message' => $v['msg'],
                    'like_number' => $v['like_number'],
                    'reply_number' => $v['replay_number'],
                    'is_like' => $is_like,
                    'score' => $v['star_num'] ? $v['star_num'] : 0,
                    'accept' => (isset($v['accept']) && $v['accept']) ? 1 : 0,// 小编采纳
                    'type' => $v['msg_type'],
                    'link_name' => $v['object_data']['object_title'],//$v['msg_type']==2?$v['object_data']['object_title']:$v['object_data']['object_title'],
                );
            }
        } else {
            $res['post_list'] = array();
        }

        //猜你喜欢
        $good_where = "play_game_info.shop_id = {$id} AND  play_organizer_game.end_time >= {$time} AND  play_organizer_game.up_time < {$time} AND play_organizer_game.start_time <= {$time} AND play_organizer_game.down_time > {$time} AND play_organizer_game.status > 0";
        $good_sql = "SELECT
play_organizer_game.id AS gid,
play_organizer_game.thumb,
play_organizer_game.title,
play_organizer_game.editor_talk,
play_organizer_game.low_price,
play_organizer_game.low_money,
play_organizer_game.ticket_num,
play_organizer_game.buy_num,
play_organizer_game.foot_time,
play_organizer_game.is_together,
play_organizer_game.down_time,
play_organizer_game.g_buy,
play_organizer_game.post_award
FROM
play_organizer_game
LEFT JOIN play_game_info ON play_game_info.gid = play_organizer_game.id
WHERE
{$good_where}
GROUP BY
play_organizer_game.id";

        $good_data = $this->query($good_sql);

        $res['place_list'] = array();
        $res['good_list'] = array();
        $placeCache = new PlaceCache();

        if ($good_data->count()) {
            foreach ($good_data as $g_data) {
                $res['good_list'][] = array(
                    'id' => $g_data['gid'],
                    'cover' => $this->_getConfig()['url'] . $g_data['thumb'],
                    'name' => $g_data['title'],
                    'editor_talk' => $g_data['editor_talk'],
                    'price' => $g_data['low_price'] ?: '0',
                    'g_buy' => (($g_data['down_time'] - time()) > 86400) ? $g_data['g_buy'] : '0',
                    'g_have' => ($g_data['down_time'] > time() && ($g_data['ticket_num'] > $g_data['buy_num'])) ? 1 : 0,
                    'prise' => GoodCache::getGameTags($g_data['gid'], ($g_data['post_award'] == 2)),
                    'end_time' => $g_data['down_time'],
                    'surplus_num' => $g_data['ticket_num'] - $g_data['buy_num'],
                );
                $res['buy_number'] = $res['buy_number'] + $g_data['buy_num'];
            }
        } else { //如果无商品 推荐同类型的游玩地

            $place_sql = "SELECT
	play_shop.shop_id,
	play_shop.shop_name,
	play_shop.thumbnails,
	play_shop.reference_price,
	play_shop.good_num,
	play_shop.editor_word
FROM
	play_shop
LEFT JOIN play_label_linker ON (play_label_linker.object_id = play_shop.shop_id AND play_label_linker.link_type = 1)
WHERE
	play_shop.shop_city = '{$placeData->shop_city}' AND
	play_shop.shop_status >= 0 AND
	play_label_linker.lid = {$placeData->label_id}
GROUP BY
	play_shop.shop_id
ORDER BY
    play_shop.good_num DESC,
	play_shop.hot_count DESC
LIMIT 5";

            $place_data = $this->query($place_sql);
            if ($place_data->count()) {
                foreach ($place_data as $p_data) {
                    $res['place_list'][] = array(
                        'id' => $p_data['shop_id'],
                        'cover' => $this->_getConfig()['url'] . $p_data['thumbnails'],
                        'title' => $p_data['shop_name'],
                        'circle' => $placeCache->getPlaceCircle($p_data['shop_id']),
                        'editor_word' => $p_data['editor_word'],
                        'coupon_have' => $p_data['good_num'] ? 1 : 0,
                        'prise' => $placeCache->getShopTags($p_data['shop_id']),
                    );
                }
            }
        }


        if ($good_data->count() != $placeData->good_num) {
            $this->_getPlayShopTable()->update(array('good_num' => $good_data->count()), array('shop_id' => $id));
        }

        //附近的餐厅、停车场、
        $page = (int)$this->getParams('page', 1);
        $pagenum = (int)$this->getParams('pagenum', 50);
        $offset = ($page - 1) * $pagenum;

        try {
        $nearby = $this->_getMdbNearBy()->find(
            array(
                'addr' =>
                    array(
                        '$nearSphere' =>
                            array(
                                '$geometry' =>
                                    array(
                                        'type' => 'Point',
                                        'coordinates' =>
                                            array((float)$placeData->addr_x, (float)$placeData->addr_y)
                                    ),
                                '$maxDistance' => 3000
                            )
                    ),
                'shop_id' => array('$ne' => $id)
            )
        )->skip($offset)->limit($pagenum);
        } catch (\Exception $e) {
        }

        $res['near_restaurant_list'] = [];
        $res['near_place_list'] = [];
        $res['near_park_list'] = [];

        try{
            if (false !== $nearby && !empty($nearby)) {
                foreach ($nearby as $p_data) {
                    if (!isset($p_data['type'])) {
                        continue;
                    }

                    if (0 === (int)($p_data['type'])) {
                        $type = 'place';
                    } elseif (1 === (int)($p_data['type'])) {
                        $type = 'restaurant';
                    } elseif (2 === (int)($p_data['type'])) {
                        $type = 'park';
                    } else {
                        continue;
                    }
                    $res['near_' . $type . '_list'][] = array(
                        //'id' => $p_data['_id'] ,
                        'city' => $this->_getConfig()['city'][$p_data['city']],
                        'shop_id' => array_key_exists('shop_id', $p_data) ? $p_data['shop_id'] : 0,
                        'shop_name' => $p_data['title'],
                        'shop_address' => $p_data['address'],
                        'addr_x' => $p_data['addr']['coordinates'][0] . '',
                        'addr_y' => $p_data['addr']['coordinates'][1] . '',
                        'o_addr_x' => (float)$placeData->addr_x,
                        'o_addr_y' => (float)$placeData->addr_y,
                        'type' => $p_data['type'],
                        'dis' => $this->GetDistance($p_data['addr']['coordinates'][1], $p_data['addr']['coordinates'][0],
                            (float)$placeData->addr_y, (float)$placeData->addr_x, 1, 2)
                    );
                }
            }
        }catch (\Exception $e){
        }




        return $this->jsonResponse($res);

    }



    //新游玩地详情 v3.3.1
    public function newindexAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $id = (int)$this->getParams('id');
        $uid = (int)$this->getParams('uid', 0);
        $time = time();

        $placeData = $this->_getPlayShopTable()->get(array('shop_id' => $id));

        //记录游玩地点击数
        if (!$placeData) {
            return $this->jsonResponseError('该游玩地 不存在');
        }


        //游玩地详情
        $res = array(
            'cover' => $placeData->cover ? $this->_getConfig()['url'] . $placeData->cover : '',
            'title' => $placeData->shop_name,
            'circle' => CouponCache::getBusniessCircle($placeData->busniess_circle, $placeData->shop_city),
            'address' => $placeData->shop_address,
            'phone' => $placeData->shop_phone,
            'open_time' => $placeData->open_time,
            'age_for' => ((int)$placeData->age_max === 100) ? ($placeData->age_min . '岁及以上') : ($placeData->age_min . '岁到' . $placeData->age_max . '岁'),
            'addr_x' => $placeData->addr_x,
            'addr_y' => $placeData->addr_y,
            'reference_price' => $placeData->reference_price,
            'share_img' => $this->_getConfig()['url'] . $placeData->thumbnails,
            'share_title' => $placeData->share_title ? $placeData->share_title : '遛娃好去处，' . $placeData->shop_name,
            'share_content' => strip_tags($placeData->editor_word),
            'buy_number' => 0,
        );

        if ($uid) {
            $flag = M::getPlayUserCollectTable()->getCollect($uid,'shop',$id);
            if ($flag) {
                $res['is_collect'] = 1;
            }
        } else {
            $res['is_collect'] = 0;
        }

        //游玩地攻略
        $res['strategy_list'] = array();

        $strategyDate = $this->_getPlayShopStrategyTable()->fetchAll(['sid' => $id, 'status > ?' => 0], ['status' => 'desc', 'id' => 'desc'])->toArray();

        if (count($strategyDate)) {
            foreach ($strategyDate as $s) {
                $res['strategy_list'][] = array(
                    'strategy_id' => $s['id'],
                    'strategy_uid' => $s['give_uid'],
                    'strategy_title' => $s['title'],
                    'strategy_image' => $this->_getConfig()['url'] . $s['give_image'],
                    'suit_month' => $s['suit_month'] ? $s['suit_month'] : '任何',
                );
            }
        }
        $res['strategy_num'] = count($strategyDate);

        //游玩地下面的所有商品
        $game_list = $this->_getPlayGameInfoTable()->fetchAll(['shop_id'=>$id],[],100)->toArray();

        $game_id_list = [];
        if(count($game_list)){
            foreach($game_list as $gl){
                $game_id_list[] = $gl['gid'];
            }
        }else{
            $game_id_list = [];
        }



        // 游玩地评论
        $res['whole_score'] = $placeData->star_num;
        $where = array(
            '$or' => array(
                array('msg_type' => 2, 'object_data.object_id' => array('$in' => $game_id_list)), // 是否取出游玩地下 商品的评论
                array('msg_type' => 3, 'object_data.object_id' => $id),
                array('msg_type' => 2, 'object_data.object_shop_id' => $id)
            ),
            'status' => array('$gt' => 0)
        );
        $post_data = $this->_getMdbSocialCircleMsg()->find($where)->sort(array('status' => -1, 'like_number' => -1, '_id' => -1))->limit(10);

        $res['post_number'] = $this->_getMdbSocialCircleMsg()->find($where)->count();

        if ($post_data->count()) {

            foreach ($post_data as $v) {
                $is_like = 0;
                if ($uid) {
                    $like_data = $this->_getMdbSocialPrise()->findOne([
                        'uid' => (int)$uid, //用户id
                        'type' => 1, //类型 圈子消息
                        'object_id' => (string)$v['_id'],
                    ]);

                    if ($like_data) {
                        $is_like = 1;
                    }
                }
                $res['post_list'][] = array(
                    'id' => (string)$v['_id'],
                    'uid' => $v['uid'],
                    'author' => $this->hidePhoneNumber($v['username']),
                    'author_img' => $this->getImgUrl($v['img']),
                    'dateline' => $v['dateline'],
                    'message' => $v['msg'],
                    'like_number' => $v['like_number'],
                    'reply_number' => $v['replay_number'],
                    'is_like' => $is_like,
                    'score' => $v['star_num'] ? $v['star_num'] : 0,
                    'accept' => (isset($v['accept']) && $v['accept']) ? 1 : 0,// 小编采纳
                    'type' => $v['msg_type'],
                    'link_name' => $v['object_data']['object_title'],//$v['msg_type']==2?$v['object_data']['object_title']:$v['object_data']['object_title'],
                );
            }
        } else {
            $res['post_list'] = array();
        }
        $res['good_list']=array();

        //查询活动列表 去买票活动
        $db=$this->_getAdapter();
        $activity_list = $db->query("SELECT
	b.join_number,b.low_price,b.all_number,b.name,b.id AS bbid,b.min_end_time,max_start_time,thumb,cover,circle,b.introduction,b.low_price,b.min_end_time,b.most_number,b.join_number,
	e.id as eid
	, (
		SELECT
			COUNT(*)
		FROM
			play_excercise_event
		WHERE
			play_excercise_event.sell_status > 0
		AND play_excercise_event.join_number < perfect_number
		AND play_excercise_event.sell_status != 3
		AND play_excercise_event.bid = b.id
		AND play_excercise_event.over_time > UNIX_TIMESTAMP()
		AND play_excercise_event.open_time < UNIX_TIMESTAMP()
		
	) AS buy_ing,
	(
		SELECT
			COUNT(*)
		FROM
			play_excercise_event
		WHERE
			play_excercise_event.sell_status > 0
		AND play_excercise_event.join_number < perfect_number
		AND play_excercise_event.sell_status != 3
		AND play_excercise_event.bid = b.id
		AND play_excercise_event.open_time > UNIX_TIMESTAMP()
	) AS not_start
	
FROM
	play_excercise_base AS b
LEFT JOIN play_excercise_event AS e ON e.bid = b.id
WHERE e.shop_id=? AND  b.release_status=1 AND e.sell_status>=1 AND e.sell_status != 3 AND e.over_time > UNIX_TIMESTAMP()
GROUP BY bid", array($id))->toArray();


        foreach ($activity_list as $ac) {

            if($ac['buy_ing'] > 0){
                $status=0;
            }else{
                if($ac['not_start'] > 0){
                    $status=1;
                }else{
                    $status=2;
                }
            }
            if($status!=2){
                $res['good_list'][] = array(
                    'id' => $ac['bbid'],
                    'cover' => $this->getImgUrl($ac['cover']),
                    'name' => $ac['name'],
                    'editor_talk' => $ac['introduction'],
                    'price' => $ac['low_price'] ?: '0',
                    'g_buy' => '0',
                    'g_have' =>  1,
                    'prise' => array(),
                    'end_time' => $ac['min_end_time'],
                    'surplus_num' => ($ac['most_number'] - $ac['join_number'])>0?($ac['most_number'] - $ac['join_number']):0,
                    'type' => 2, //1普通商品 2活动
                    'status' =>$status, // 售卖状态  0正在售卖 1未开始 2已售罄
                    'session_str' => date('m月d日',$ac['max_start_time']).'-'.date('m月d日',$ac['min_end_time']).'　'.(int)$ac['all_number'].'场可选', //活动专用字段
                );
            }
        }


        //去买票商品
        $good_sql = "SELECT
play_organizer_game.id AS gid,
play_organizer_game.thumb,
play_organizer_game.title,
play_organizer_game.editor_talk,
play_organizer_game.low_price,
play_organizer_game.low_money,
play_organizer_game.ticket_num,
play_organizer_game.buy_num,
play_organizer_game.foot_time,
play_organizer_game.is_together,
play_organizer_game.down_time,
play_organizer_game.g_buy,
play_organizer_game.post_award
, (
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
play_organizer_game
LEFT JOIN play_game_info ON play_game_info.gid = play_organizer_game.id
WHERE
play_game_info.shop_id = {$id}
 AND  play_organizer_game.end_time >= {$time} 
 AND play_organizer_game.start_time <= {$time} 
 AND play_organizer_game.status =1
GROUP BY
play_organizer_game.id";
        $good_data = $this->query($good_sql);
        $res['place_list'] = array();

        $placeCache = new PlaceCache();

        if ($good_data->count()) {
            foreach ($good_data as $g_data) {



                if($g_data['buy_ing'] > 0){
                    $status=0;
                }else{
                    if($g_data['not_start'] > 0){
                        $status=1;
                    }else{
                        $status=2;
                    }
                }

                if($status!=2){
                    $res['good_list'][] = array(
                        'id' => $g_data['gid'],
                        'cover' => $this->_getConfig()['url'] . $g_data['thumb'],
                        'name' => $g_data['title'],
                        'editor_talk' => $g_data['editor_talk'],
                        'price' => $g_data['low_price'] ?: '0',
                        'g_buy' => (($g_data['down_time'] - time()) > 86400) ? $g_data['g_buy'] : '0',
                        'g_have' => ($g_data['down_time'] > time() && ($g_data['ticket_num'] > $g_data['buy_num'])) ? 1 : 0,
                        'prise' => GoodCache::getGameTags($g_data['gid'], ($g_data['post_award'] == 2)),
                        'end_time' => $g_data['down_time'],
                        'surplus_num' => ($g_data['ticket_num'] - $g_data['buy_num'])>0?($g_data['ticket_num'] - $g_data['buy_num']):0,
                        'type' => 1, //1普通商品 2活动
                        'status' => $status,//   售卖状态  0正在售卖 1未开始 2已售罄
                    );
                    $res['buy_number'] = $res['buy_number'] + $g_data['buy_num'];
                }

            }
        }
        if(empty($res['good_list'])) { //如果无商品 推荐同类型的游玩地

            $place_sql = "SELECT
	play_shop.shop_id,
	play_shop.shop_name,
	play_shop.thumbnails,
	play_shop.reference_price,
	play_shop.good_num,
	play_shop.editor_word
FROM
	play_shop
LEFT JOIN play_label_linker ON (play_label_linker.object_id = play_shop.shop_id AND play_label_linker.link_type = 1)
WHERE
	play_shop.shop_city = '{$placeData->shop_city}' AND
	play_shop.shop_status >= 0 AND
	play_label_linker.lid = {$placeData->label_id}
GROUP BY
	play_shop.shop_id
ORDER BY
    play_shop.good_num DESC,
	play_shop.hot_count DESC
LIMIT 5";

            $place_data = $this->query($place_sql);
            if ($place_data->count()) {
                foreach ($place_data as $p_data) {
                    $res['place_list'][] = array(
                        'id' => $p_data['shop_id'],
                        'cover' => $this->_getConfig()['url'] . $p_data['thumbnails'],
                        'title' => $p_data['shop_name'],
                        'circle' => $placeCache->getPlaceCircle($p_data['shop_id']),
                        'editor_word' => $p_data['editor_word'],
                        'coupon_have' => $p_data['good_num'] ? 1 : 0,
                        'prise' => $placeCache->getShopTags($p_data['shop_id']),
                    );
                }
            }
        }


        

        if ($good_data->count() != $placeData->good_num) {
            $this->_getPlayShopTable()->update(array('good_num' => $good_data->count()), array('shop_id' => $id));
        }

        //附近的餐厅、停车场、
        $page = (int)$this->getParams('page', 1);
        $pagenum = (int)$this->getParams('pagenum', 50);
        $offset = ($page - 1) * $pagenum;

        try {
            $nearby = $this->_getMdbNearBy()->find(
                array(
                    'addr' =>
                        array(
                            '$nearSphere' =>
                                array(
                                    '$geometry' =>
                                        array(
                                            'type' => 'Point',
                                            'coordinates' =>
                                                array((float)$placeData->addr_x, (float)$placeData->addr_y)
                                        ),
                                    '$maxDistance' => 3000
                                )
                        ),
                    'shop_id' => array('$ne' => $id)
                )
            )->skip($offset)->limit($pagenum);
        } catch (\Exception $e) {
        }

        $res['near_restaurant_list'] = [];
        $res['near_place_list'] = [];
        $res['near_park_list'] = [];

        try{
            if (false !== $nearby && !empty($nearby)) {
                foreach ($nearby as $p_data) {
                    if (!isset($p_data['type'])) {
                        continue;
                    }

                    if (0 === (int)($p_data['type'])) {
                        $type = 'place';
                    } elseif (1 === (int)($p_data['type'])) {
                        $type = 'restaurant';
                    } elseif (2 === (int)($p_data['type'])) {
                        $type = 'park';
                    } else {
                        continue;
                    }
                    $res['near_' . $type . '_list'][] = array(
                        //'id' => $p_data['_id'] ,
                        'city' => $this->_getConfig()['city'][$p_data['city']],
                        'shop_id' => array_key_exists('shop_id', $p_data) ? $p_data['shop_id'] : 0,
                        'shop_name' => $p_data['title'],
                        'shop_address' => $p_data['address'],
                        'addr_x' => $p_data['addr']['coordinates'][0] . '',
                        'addr_y' => $p_data['addr']['coordinates'][1] . '',
                        'o_addr_x' => (float)$placeData->addr_x,
                        'o_addr_y' => (float)$placeData->addr_y,
                        'type' => $p_data['type'],
                        'dis' => $this->GetDistance($p_data['addr']['coordinates'][1], $p_data['addr']['coordinates'][0],
                            (float)$placeData->addr_y, (float)$placeData->addr_x, 1, 2)
                    );
                }
            }
        }catch (\Exception $e){
        }




        return $this->jsonResponse($res);

    }


    private function query($sql)
    {
        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $stmt = $db->query($sql);
        $result = $stmt->execute($stmt);

        return $result;
    }

}
