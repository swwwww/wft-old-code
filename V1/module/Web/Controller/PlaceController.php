<?php

namespace Web\Controller;

use Deyi\BaseController;
use Deyi\GetCacheData\CouponCache;
use Deyi\GetCacheData\GoodCache;
use Deyi\GetCacheData\PlaceCache;
use Deyi\JsonResponse;
use Zend\Http\Response;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class PlaceController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;


    public function indexAction()
    {
        $id = (int)$this->getQuery('id');
        $uid = (int)$this->getQuery('uid', 0) == 0 ? $_COOKIE['uid'] : (int)$this->getQuery('uid', 0);
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
            'sid' => $placeData->shop_id,
            'circle' => CouponCache::getBusniessCircle($placeData->busniess_circle, $placeData->shop_city),
            'address' => $placeData->shop_address,
            'phone' => $placeData->shop_phone,
            'open_time' => $placeData->open_time,
            'age_for' => ((int)$placeData->age_max === 100) ? ($placeData->age_min . '岁及以上') : ($placeData->age_min . '岁到' . $placeData->age_max . '岁'),
            'addr_x' => $placeData->addr_x,
            'addr_y' => $placeData->addr_y,
            'reference_price' => $placeData->reference_price,
            'good_list' => array(),
            'share_img' => $this->_getConfig()['url'] . $placeData->thumbnails,
            'share_title' => '遛娃好去处，' . $placeData->shop_name,
            'share_content' => strip_tags($placeData->editor_word),
        );

        $sql = "select information,id from play_shop_strategy where sid={$id} and status>0 order by status desc,id desc limit 1";
        $strategy = $this->query($sql)->current();
        $res['information'] = htmlspecialchars_decode($strategy['information']);
        $res['strategy_id'] = $strategy['id'];
        if ($uid) {
            $flag = $this->_getPlayUserCollectTable()->get(array('uid' => $uid, 'type' => 'shop', 'link_id' => $id));
            if ($flag) {
                $res['is_collect'] = 1;
            }
        } else {
            $res['is_collect'] = 0;
        }

        //游玩地攻略
        $res['strategy_list'] = array();

        $strategyDate = $this->_getPlayShopStrategyTable()->fetchAll(['sid' => $id, 'status > ?' => 0],
            ['status' => 'desc', 'id' => 'desc'])->toArray();

        if (count($strategyDate)) {
            foreach ($strategyDate as $s) {
                unset($s[0]);
                if ($res['strategy_id'] != $s['id']) {
                    $res['strategy_list'][] = array(
                        'strategy_id' => $s['id'],
                        'strategy_uid' => $s['give_uid'],
                        'strategy_title' => $s['title'],
                        'strategy_image' => $this->_getConfig()['url'] . $s['give_image'],
                        'suit_month' => $s['suit_month'] ? $s['suit_month'] : '任何',
                    );
                }
            }
        }
        $res['strategy_num'] = count($strategyDate);

        // 游玩地评论
        $res['whole_score'] = $placeData->star_num;
        $where = array(
            '$or' => array(
                //array('msg_type' => 2, 'object_data.object_id' => array('$in' => $game_id_list)), // 是否取出游玩地下 商品的评论
                array('msg_type' => 3, 'object_data.object_id' => $id),
                array('msg_type' => 2, 'object_data.object_shop_id' => $id)
            ),
            'status' => array('$gt' => 0)
        );
        $post_data = $this->_getMdbSocialCircleMsg()->find($where)->sort(array(
            'status' => -1,
            'like_number' => -1,
            '_id' => -1
        ))->limit(3);

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
                    'title' => $v['title'],
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
                    'link_name' => '商品名称',//$v['object_data']['object_place'],
                );
            }
            $res['post_list'] = array_slice($res['post_list'], 0, 3);
        } else {
            $res['post_list'] = array();
        }

        //点评是否有翻倍积分
        $double = $this->_getPlayWelfareIntegralTable()->get(array(
            'object_type' => 1,
            'welfare_type' => 3,
            'object_id' => $id
        ))->double;
        $res['double'] = $double;


        //猜你喜欢
        $good_where = "play_game_info.shop_id = {$id} AND  play_organizer_game.end_time >= {$time} AND  play_organizer_game.up_time < {$time} AND play_organizer_game.start_time <= {$time} AND play_organizer_game.down_time > {$time} AND play_organizer_game.status > 0";
        $good_sql = "SELECT
play_organizer_game.id AS gid,
play_organizer_game.thumb,
play_organizer_game.title,
play_organizer_game.editor_talk,
play_organizer_game.low_price,
play_organizer_game.low_money,
play_organizer_game.shop_addr,
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
                    'shop_addr' => $g_data['shop_addr'],
                    'price' => $g_data['low_price'],
                    'g_buy' => (($g_data['down_time'] - time()) > 86400) ? $g_data['g_buy'] : '0',
                    'g_have' => 1,
                    'prise' => GoodCache::getGameTags($g_data['gid'], ($g_data['post_award'] == 2)),
                );
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

        if ((int)$good_data->count() !== (int)$placeData->good_num) {
            $this->_getPlayShopTable()->update(array('good_num' => $good_data->count()), array('shop_id' => $id));
        }

        //附近的餐厅、停车场
        $city = array_key_exists('city', $_COOKIE) ? $_COOKIE['city'] : 'WH';

        $page = (int)$this->getQuery('page', 1);
        $pagenum = (int)$this->getQuery('pagenum', 5);
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
                        )
                )

            )->skip($offset)->limit($pagenum);

            $res['near_restaurant_list'] = [];
            $res['near_place_list'] = [];
            $res['near_park_list'] = [];

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

        } catch (\Exception $e) {
        }




        //分享
        $jswxconfig = $this->getShareInfoAction()[1];
        $share = array(
            'img'=> $this->_getConfig()['url'] . $placeData->thumbnails,
            'title'=>'【玩翻天】'.$placeData->shop_name,
            'desc'=> '我发现了一个不错的遛娃地，我们一起遛娃去吧。'.strip_tags($placeData->editor_word),
            'link'=>$this->_getConfig()['url'] . "/web/place/index?id={$id}",
        );

//        var_dump($res);die();
        $res['uid'] = $uid;
        $view = new ViewModel([
            'data' => $res,
            'jsconfig'=>$jswxconfig,
            'share'=>$share,
            'share_type'=>'place',
            'share_id'=>$id
        ]);
        $view->setTerminal(true);

        return $view;

    }


    public function getPlace($lid, $id)
    {

        $city = $this->getCity();
        $where = "play_label_linker.link_type = 1 && play_shop.shop_city = '{$city}' && play_shop.shop_status >= 0";

        if ($lid) {
            $where = $where . ' AND play_label_linker.object_id != ' . $id . ' AND play_label_linker.lid = ' . $lid;
        }

        $order = 'play_shop.hot_count DESC';
        $sql = "SELECT
play_region.`name`,
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
LEFT JOIN play_region ON play_shop.busniess_circle = play_region.rid
WHERE
{$where}
GROUP BY
play_shop.shop_id
ORDER BY
{$order}
LIMIT 5
";
        $res = $this->query($sql);
        $data = array();

        if (false !== $res && count($res) > 0) {
            foreach ($res as $val) {
                $data[] = array(
                    'id' => $val['object_id'],
                    'cover' => $this->_getConfig()['url'] . $val['thumbnails'],
                    'title' => $val['shop_name'],
                    'circle' => $val['name'],
                    'editor_word' => $val['editor_word'],
                    'coupon_have' => 2,
                    'prise' => array('点评有礼'),
                );
            }
        }

        return $data;
    }


    //加载更多
    public function getMoreAction()
    {
        $page = (int)$this->getPost('page', 1);
        $pagenum = (int)$this->getPost('pagenum', 5);
        $key = $this->getPost('key');
        $id = $this->getPost('id');
        $offset = ($page - 1) * $pagenum;

        $placeData = $this->_getPlayShopTable()->get(array('shop_id' => $id));
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
            )
        )->skip($offset)->limit($pagenum);


        $data = array();
        if (false !== $nearby && !empty($nearby)) {
            foreach ($nearby as $p_data) {
                if ($p_data['type'] == $key) {
                    $data[] = array(
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
                        'dis' => $this->GetDistance($p_data['addr']['coordinates'][1],
                            $p_data['addr']['coordinates'][0],
                            (float)$placeData->addr_y, (float)$placeData->addr_x, 1, 2)
                    );
                }
            }
        }


        exit(json_encode($data));

    }


    private function query($sql)
    {
        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $stmt = $db->query($sql);
        $result = $stmt->execute($stmt);

        return $result;
    }


    //攻略分享页
    public function strategyAction()
    {
        $id = (int)$this->getQuery('id');
        $all = $this->getQuery('all', 0);
        $res = null;
        $data = $this->_getPlayShopStrategyTable()->get(array('id' => $id));

        if (!$data) {
            echo '不存在';
            exit;
        }
        if ($data) {
            $res = htmlspecialchars_decode($data->information);
        }

        $shop_id = $data->sid;
        $allData = array();
        if ($shop_id && $all) {

            $strategyDate = $this->_getPlayShopStrategyTable()->fetchAll([
                'sid' => $shop_id,
                'status > ?' => 0,
                'id != ?' => $id
            ], ['status' => 'desc', 'id' => 'desc'])->toArray();
            if (count($strategyDate)) {
                foreach ($strategyDate as $s) {
                    $allData[] = array(
                        'strategy_id' => $s['id'],
                        'strategy_uid' => $s['give_uid'],
                        'strategy_title' => $s['title'],
                        'strategy_image' => $this->_getConfig()['url'] . $s['give_image'],
                        'suit_month' => $s['suit_month'],
                    );
                }
            }

        }
        $city = $this->getCity();

        $data = $this->_getPlayInviteContentTable()->get(['city' => $city]);

        $vm = new viewModel(array(
            'res' => $res,
            'app' => $all,
            'all' => $allData,
            'url' => $this->_getConfig()['url'],
            'city' => $city,
            'award' => $data->award
        ));

        $vm->setTerminal(true);

        return $vm;

    }

    public function checkPhone($backUrl)
    {
        if (!isset($_COOKIE['phone']) || !$_COOKIE['phone']) {
            //临时查询用户是否已绑定手机号
            $user_data = $this->_getPlayUserTable()->get(array('uid' => (int)$_COOKIE['uid']));
            if ($user_data->phone) {
                $untime = time() + 3600 * 24 * 17;  //失效时间
                setcookie('phone', $user_data->phone, $untime, '/');

                return true;
            } else {
                $url = $this->_getConfig()['url'] . "/web/wappay/bindphone?uid={$_COOKIE['uid']}&tourl=" . urlencode($backUrl);
                header("Location: $url");
                exit;
            }

        }
    }

    //收藏
    public function collectAction()
    {
        $is_wap = strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') == true ? 0 : 1;

        if (!$is_wap) {
            if (!$this->pass()) {
                return $this->jsonResponseError('接口验证失败', Response::STATUS_CODE_403);
            }
        }

        $uid = (int)$this->getPost('uid');
        if (!$uid) {
            return $this->jsonResponseError('用户id不存在');
        }

        $type = $this->getPost('type');
        $act = $this->getPost('act');
        $link_id = (int)$this->getPost('link_id');

        if (!in_array($type, array('shop', 'organizer')) || !in_array($act, array('del', 'add')) || !$link_id) {
            return $this->jsonResponseError('参数错误');
        }


        $where = array('uid' => $uid, 'type' => $type, 'link_id' => $link_id);
        if ($act == 'del') {
            $this->_getPlayUserCollectTable()->delete($where);
        }

        if ($act == 'add') {
            $status = $this->_getPlayUserCollectTable()->get($where);
            if ($status) {
                return $this->jsonResponse(array('status' => 0, 'message' => '已经收藏过啦'));
            }
            $where['add_time'] = time();
            $this->_getPlayUserCollectTable()->insert($where);
        }

        return $this->jsonResponse(array('status' => 1, 'message' => ($act == 'add') ? '收藏成功' : '已取消收藏'));
    }

    public function ceshiAction()
    {
        if (!$this->pass()) {
            return $this->jsonResponseError('接口验证失败', Response::STATUS_CODE_403);
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
            'share_title' => '遛娃好去处，' . $placeData->shop_name,
            'share_content' => strip_tags($placeData->editor_word),
        );

        if ($uid) {
            $flag = $this->_getPlayUserCollectTable()->get(array('uid' => $uid, 'type' => 'shop', 'link_id' => $id));
            if ($flag) {
                $res['is_collect'] = 1;
            }
        } else {
            $res['is_collect'] = 0;
        }

        //游玩地攻略
        $res['strategy_list'] = array();

        $strategyDate = $this->_getPlayShopStrategyTable()->fetchAll(['sid' => $id, 'status > ?' => 0],
            ['status' => 'desc', 'id' => 'desc'])->toArray();

        if (count($strategyDate)) {
            foreach ($strategyDate as $s) {
                $res['strategy_list'][] = array(
                    'strategy_id' => $s['id'],
                    'strategy_uid' => $s['give_uid'],
                    'strategy_title' => $s['title'],
                    'strategy_image' => $this->_getConfig()['url'] . $s['give_image'],
                    'suit_month' => $s['suit_month'],
                );
            }
        }
        $res['strategy_num'] = count($strategyDate);

        // 游玩地评论
        $res['whole_score'] = $placeData->star_num;
        $where = array(
            '$or' => array(
                //array('msg_type' => 2, 'object_data.object_id' => array('$in' => $game_id_list)), // 是否取出游玩地下 商品的评论
                array('msg_type' => 3, 'object_data.object_id' => $id),
                array('msg_type' => 2, 'object_data.object_shop_id' => $id)
            ),
            'status' => array('$gt' => 0)
        );
        $post_data = $this->_getMdbSocialCircleMsg()->find($where)->sort(array(
            'status' => -1,
            'like_number' => -1,
            '_id' => -1
        ))->limit(10);

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
                    'accept' => (isset($v['accept']) && $v['accept']) ? 1 : 0,
                    // 小编采纳
                    'type' => $v['msg_type'],
                    'link_name' => $v['object_data']['object_title'],
                    //$v['msg_type']==2?$v['object_data']['object_title']:$v['object_data']['object_title'],
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
                    'price' => $g_data['low_price'],
                    'g_buy' => (($g_data['down_time'] - time()) > 86400) ? $g_data['g_buy'] : '0',
                    'g_have' => 1,
                    'prise' => GoodCache::getGameTags($g_data['gid'], ($g_data['post_award'] == 2)),
                );
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
                    )
            )

        )->skip($offset)->limit($pagenum);

        $res['near_restaurant_list'] = [];
        $res['near_place_list'] = [];
        $res['near_park_list'] = [];

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

        return $this->jsonResponse($res);

    }

}
