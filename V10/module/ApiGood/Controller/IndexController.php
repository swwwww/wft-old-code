<?php
/**
 * index 商品
 */

namespace ApiGood\Controller;

use Application\Module;
use Deyi\Account\Account;
use Deyi\BaseController;
use Deyi\GetCacheData\CouponCache;
use Deyi\GetCacheData\GoodCache;
use Deyi\Integral\Integral;
use library\Fun\Common;
use library\Fun\M;
use library\Service\System\Cache\RedCache;
use Deyi\Seller\Seller;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Predicate\In;
use Zend\Db\Sql\Select;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Response;
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

    private function getGameData($id)
    {
      return  M::getPlayOrganizerGameTable()->getGameData($id);
    }

    private function getCacheData($gameData)
    {


        return RedCache::fromCacheData('D:coupon_join_data:' . $gameData->id, function () use ($gameData) {

            $cache_data = array('shop' => array(), 'shop_num' => 0, 'shop_id_list' => array(), 'phone' => '', 'welfare' => '', 'game_list' => array());

            /*************** 绑定的手机号 ******************/
            if (!$gameData->phone) {
                $organizer_data = $this->_getPlayOrganizerTable()->get(array('id' => $gameData->organizer_id));
                $cache_data['phone'] = $organizer_data->phone;
            } else {
                $cache_data['phone'] = $gameData->phone;
            }

            /********************* 关联的游玩地**********************/
            $shop_data = $this->_getPlayGameInfoTable()->getApiGameShopList(0, 100, array(), array('play_game_info.gid' => $gameData->id, 'play_game_info.status >= ?' => 1));
            foreach ($shop_data as $sData) {
                $cache_data['shop_id_list'][] = (int)$sData->shop_id;
                $cache_data['shop'][] = array(
                    'shop_name' => $sData->shop_name,
                    'shop_id' => $sData->shop_id,
                    'circle' => $sData->circle,
                    'address' => $sData->shop_address,
                    'addr_x' => $sData->addr_x,
                    'addr_y' => $sData->addr_y,
                );
            }
            $cache_data['shop_num'] = $this->_getPlayGameInfoTable()->getApiGameShopList(0, 0, array(), array('play_game_info.gid' => $gameData->id, 'play_game_info.status >= ?' => 1))->count();

            /************* 最大返利 **************/
            $welfare = $this->_getPlayWelfareTable()->fetchAll(array('object_id' => $gameData->id, 'object_type' => 2, '(welfare_type=2 or welfare_type=3)', 'status' => 2), array('welfare_value' => 'desc'), 1)->current();
            if ($welfare) {
                $desc = $welfare->welfare_type == 2 ? '现金' : '现金券';
                $res_data = "最高返利{$welfare->welfare_value}元" . $desc;
                $cache_data['get_money'] = $res_data;
            }


            /****** 猜你喜欢 ********/

            //猜你喜欢 相同类别
            $recommend = $this->_getPlayOrganizerGameTable()->fetchAll(array('type' => $gameData->type, 'status' => 1, 'id!=' . $gameData->id, 'city' => $gameData->city), array('dateline' => 'desc'), 3);
            foreach ($recommend as $v) {
                $cache_data['game_list'][] = array(
                    'id' => $v->id,
                    'cover' => $this->_getConfig()['url'] . $v->cover,
                    'name' => $v->title,
                    'price' => $v->low_money
                );
            }

            return $cache_data;

        }, 60, true);
    }

    private function getCacheDataConsult($id)
    {
        return RedCache::fromCacheData('D:coupon_consult:' . $id, function () use ($id) {
            $cache_data = array('consult' => array());
            /****** 咨询列表 ********/
            $consult = $this->_getMdbConsultPost()->find(array('status' => array('$gte' => 1), 'object_data.object_id' => $id))->sort(array('status' => -1, '_id' => -1))->limit(10);
            $cache_data['consult'] = array();
            foreach ($consult as $v) {
                $reply = [];
                if ($v['reply']) {
                    $v['reply']['img'] = 'http://wan.wanfantian.com/uploads/2016/02/26/aca48a6ad9c735fa7edf966bbbe083b1.jpg';
                    $v['reply']['username'] = '小玩';
                    $reply = $v['reply'];
                }
                $cache_data['consult'][] = array(
                    'mid' => (string)$v['_id'],
                    'uid' => $v['uid'],
                    'img' => $this->getImgUrl($v['img']),
                    'dateline' => $v['dateline'],
                    'username' => $this->hidePhoneNumber($v['username']),
                    'msg' => $v['msg'],
                    'reply' => $reply ? array($reply) : array()
                );
            }
            return $cache_data;
        }, 60, true);
    }

    //退款说明
    private function use_time($gameData, $uid)
    {
        $orders = (array)$this->getCacheGameOrder33($gameData, $uid);

        $str_info = '';
        if (count($orders['game_order']) === 1) {
            $back_money = ($orders['game_order'][0]['refund_time'] < $orders['game_order'][0]['up_time']) ? '不支持退款' : date('Y.m.d H:i', $orders['game_order'][0]['refund_time']) . '前支持退款';
            return [$orders['game_order'][0]['remark'] . '，' . $back_money, (int)($orders['game_order'][0]['refund_time'] > $orders['game_order'][0]['up_time'])];

        }
        $sf = 1;
        foreach ($orders['game_order'] as $v) {
            if ($v['refund_time'] < $v['up_time']) {
                $sf = 0;
            }
            $back_money = ($v['refund_time'] < $v['up_time']) ? '不支持退款' : date('Y.m.d H:i', $v['refund_time']) . '前支持退款';
            $str_info .= $v['price_name'] . '：' . $v['remark'] . '，' . $back_money . '；';
        }
        return [$str_info, $sf];
    }

    //获得兑换方式
    private function order_method($gameData, $uid)
    {
        $orders = (array)$this->getCacheGameOrder33($gameData, $uid);
        $str_info = '';
        if (count($orders['game_order']) === 1) {
            return $orders['game_order'][0]['order_method'];
        }
        foreach ($orders['game_order'] as $v) {
            $str_info .= $v['price_name'] . ': ' . $v['order_method'] . '；

';
        }
        return $str_info;
    }

    private function getCacheGameOrder($gameData, $uid = 0)
    {
        if ($uid) {
            $qual = 0;
            $newuser = $this->_getPlayOrderInfoTable()->get(['user_id' => $uid, 'order_status' => 1]);
            //新用户专享 0 不是新用户专享 1 新用户专享并可以购买 2 新用户专享但是用户为老用户
        }
        //todo 临时取消缓存
        //RedCache::del('D:coupon_game_order:' . $gameData->id);
        return RedCache::fromCacheData('D:coupon_game_order:' . $gameData->id, function () use ($gameData, $qual, $newuser) {
            $res = array('surplus_num' => 0, 'game_order' => array());
            //精选套系  //所有套系 价格最小的那一个
            $db = $this->_getAdapter();

            $order_data = $db->query(
                "SELECT pgi.*, pgp.refund_before_day, pgp.refund_before_time, pgp.back_rule
                 FROM play_game_info AS pgi
                 LEFT JOIN play_game_price AS pgp ON pgi.pid = pgp.id
                 WHERE pgi.gid = ? AND status = 1 AND pgi.end_time > ? AND pgi.down_time > ? AND pgi.total_num > pgi.buy ORDER BY pgi.price ASC",
                array($gameData->id, time(), time()))->toArray();

            $order_list = array();
            foreach ($order_data as $k => $v) {
                if (!isset($order_list[$v['pid']])) {
                    $order_list[$v['pid']] = $v;
                }

                if ($order_list[$v['pid']]['surplus_num']) {
                    $order_list[$v['pid']]['surplus_num'] = ($v['total_num'] - $v['buy']);
                } else {
                    $order_list[$v['pid']]['surplus_num'] += ($v['total_num'] - $v['buy']);//当前套系
                }

                // 套系的结束时间  取套系中最晚的结束时间
                if ($order_list[$v['pid']]['end_time'] < $v['end_time']) {
                    $order_list[$v['pid']]['end_time'] = $v['end_time'];
                }

                // 套系的开始时间  取套系中最早的开始时间
                if ($order_list[$v['pid']]['start_time'] > $v['start_time'] || empty($order_list[$v['pid']]['start_time'])) {
                    $order_list[$v['pid']]['start_time'] = $v['start_time'];
                }

                if ($order_list[$v['pid']]['price'] > $v['price'] || empty($order_list[$v['pid']]['price'])) {
                    $order_list[$v['pid']]['price'] = $v['price'];
                }

                $res['surplus_num'] += $v['total_num'] - $v['buy'];  //总共
            }

            foreach ($order_list as $k => $v) {
                $v = (object)$v;

                if ($v->back_rule == 1) {
                    if ($v->refund_before_day) {
                        $back_money = "在游玩日期后第" . $v->refund_before_day . "天的" . date('H:i', $v->refund_before_time) . '前支持退款';
                    } else {
                        $back_money = "在游玩日期当天的" . date('H:i', $v->refund_before_time) . '前支持退款';
                    }
                } else {
                    $back_money = ($v->refund_time < $v->up_time) ? '不支持退款' : date('Y.m.d H:i', $v->refund_time) . '前支持退款';
                }

                // 是否能团购
                if ($gameData->g_buy) {
                    $res['game_order'][] = array(
                        'order_id' => $v->id,  //套系id  time表
                        'way' => $gameData->g_set_name ?: $v->price_name,  //参与方式 time表
                        'price' => $gameData->g_price,
                        'surplus_num' => $v->surplus_num, //剩余
                        'is_group' => 1, // 是否团购
                        'want_score' => $v->integral,//需要的积分才能购买
                        'tag' => GoodCache::getWelfareByInfo($v->id),
                        'item_info' => [
                            'get_way' => $v->order_method,
                            'back_money' => $back_money,
                            'special_info' => $v->remark
                        ],
                        'buy_qualify' => (int)$v->qualified,
                        'new_user_buy' => ((int)$v->for_new === 1) ? ($newuser ? 2 : 1) : 0,
                        'limit_num' => (int)$v->limit_num,
                        'limit_low_num' => (int)$v->limit_low_num,
                        'up_time' => (int)$v->up_time,
                        'down_time' => (int)$v->down_time,
                        'end_time' => (int)$v->end_time,
                        'refund_time' => (int)$v->refund_time,
                        'price_name' => $v->price_name,
                        'order_method' => $v->order_method,
                        'remark' => $v->remark,
                    );

                    $res['game_order'][] = array(
                        'order_id' => $v->id,  //套系id  time表
                        'way' => $v->price_name,  //参与方式 time表
                        'price' => $v->price,
                        'surplus_num' => $v->surplus_num, //剩余
                        'is_group' => 0, // 是否团购
                        'want_score' => $v->integral,//需要的积分才能购买
                        'tag' => GoodCache::getWelfareByInfo($v->id),
                        'item_info' => ['get_way' => $v->order_method, 'back_money' => $back_money, 'special_info' => $v->remark],
                        'buy_qualify' => (int)$v->qualified,
                        'new_user_buy' => ((int)$v->for_new === 1) ? ($newuser ? 2 : 1) : 0,
                        'limit_num' => (int)$v->limit_num,
                        'limit_low_num' => (int)$v->limit_low_num,
                        'up_time' => (int)$v->up_time,
                        'down_time' => (int)$v->down_time,
                        'end_time' => (int)$v->end_time,
                        'refund_time' => (int)$v->refund_time,
                        'order_method' => $v->order_method,
                        'remark' => $v->remark,
                        'price_name' => $v->price_name
                    );

                    continue;
                } else {
                    $res['game_order'][] = array(
                        'order_id' => $v->id,  //套系id  time表
                        'way' => $v->price_name,  //参与方式 time表
                        'price' => $v->price,
                        'surplus_num' => $v->surplus_num, //剩余
                        'is_group' => 0, // 是否团购
                        'want_score' => $v->integral,//需要的积分才能购买
                        'tag' => GoodCache::getWelfareByInfo($v->id),
                        'item_info' => ['get_way' => $v->order_method, 'back_money' => $back_money, 'special_info' => $v->remark],
                        'buy_qualify' => (int)$v->qualified,
                        'new_user_buy' => ((int)$v->for_new === 1) ? ($newuser ? 2 : 1) : 0,
                        'limit_num' => (int)$v->limit_num,
                        'limit_low_num' => (int)$v->limit_low_num,
                        'up_time' => (int)$v->up_time,
                        'down_time' => (int)$v->down_time,
                        'end_time' => (int)$v->end_time,
                        'refund_time' => (int)$v->refund_time,
                        'order_method' => $v->order_method,
                        'remark' => $v->remark,
                        'price_name' => $v->price_name
                    );
                }

            }

            //记录实际剩余数
            RedCache::set('D:SurplusNumber:' . $gameData->id, $res['surplus_num']);
            return $res;
        }, 60, true);
    }

    private function getCacheGameOrder33($gameData, $uid = 0)
    {
        if ($uid) {
//            $qual = $this->_getQualifyTable()->get(array('uid' => $uid, 'pay_time' => 0, 'status' => 1, 'valid_time>' . time()));
            $qual = 0;
            $newuser = $this->_getPlayOrderInfoTable()->get(['user_id' => $uid, 'order_status' => 1]);
            //新用户专享 0 不是新用户专享 1 新用户专享并可以购买 2 新用户专享但是用户为老用户
        }
        //todo 临时取消缓存
        //RedCache::del('D:coupon_game_order:' . $gameData->id);
        return RedCache::fromCacheData('D:coupon_game_order33_:' . $gameData->id, function () use ($gameData, $qual, $newuser) {
            $res = array('surplus_num' => 0, 'game_order' => array());
            //精选套系  //所有套系 价格最小的那一个
            $db = $this->_getAdapter();

            $order_data = $db->query("SELECT * FROM play_game_info
  WHERE play_game_info.gid=?  and status=1 AND play_game_info.end_time>? AND play_game_info.up_time < ?
  AND play_game_info.down_time > ? and play_game_info.total_num > play_game_info.buy ORDER BY play_game_info.price ASC",
                array($gameData->id, time(), time(), time()))->toArray();
            $order_list = array();
            foreach ($order_data as $k => $v) {
                if (!isset($order_list[$v['pid']])) {
                    $order_list[$v['pid']] = $v;
                }

                $order_list[$v['pid']]['surplus_num'] += $v['total_num'] - $v['buy'];//当前套系

                $res['surplus_num'] += $v['total_num'] - $v['buy'];  //总共
            }

            foreach ($order_data as $kk => $vv) {
                if ($order_list[$vv['pid']]['end_time'] < $vv['end_time']) {
                    $order_list[$vv['pid']]['end_time'] = $vv['end_time'];
                }
            }

            foreach ($order_list as $k => $v) {
                $v = (object)$v;
                $back_money = ($v->refund_time < $v->up_time) ? '不支持退款' : date('Y.m.d H:i', $v->refund_time) . '前支持退款';
                //是否新用户专享
                $for_new = ((int)$v->for_new === 1) ? ($newuser ? 2 : 1) : 0;
                if ($for_new == 2) {
                    continue;
                }

                if ($gameData->g_buy) {
                    $res['game_order'][] = array(
                        'order_id' => $v->id,  //套系id  time表
                        'way' => $gameData->g_set_name ?: $v->price_name,  //参与方式 time表
                        'price' => $gameData->g_price,
                        'surplus_num' => $v->surplus_num, //剩余
                        'is_group' => 1, // 是否团购
                        'want_score' => $v->integral,//需要的积分才能购买
                        'tag' => GoodCache::getWelfareByInfo($v->id),
                        'item_info' => ['get_way' => $v->order_method, 'back_money' => $back_money, 'special_info' => $v->remark],
                        'buy_qualify' => 1,
                        'new_user_buy' => ((int)$v->for_new === 1) ? ($newuser ? 2 : 1) : 0,
                        'limit_num' => (int)$v->limit_num,
                        'limit_low_num' => (int)$v->limit_low_num,
                        'up_time' => (int)$v->up_time,
                        'down_time' => (int)$v->down_time,
                        'end_time' => (int)$v->end_time,
                        'refund_time' => (int)$v->refund_time,
                        'price_name' => $v->price_name,
                        'order_method' => $v->order_method,
                        'remark' => $v->remark,
                    );

                    $res['game_order'][] = array(
                        'order_id' => $v->id,  //套系id  time表
                        'way' => $v->price_name,  //参与方式 time表
                        'price' => $v->price,
                        'surplus_num' => $v->surplus_num, //剩余
                        'is_group' => 0, // 是否团购
                        'want_score' => $v->integral,//需要的积分才能购买
                        'tag' => GoodCache::getWelfareByInfo($v->id),
                        'item_info' => ['get_way' => $v->order_method, 'back_money' => $back_money, 'special_info' => $v->remark],
                        'buy_qualify' => 1,
                        'new_user_buy' => ((int)$v->for_new === 1) ? ($newuser ? 2 : 1) : 0,
                        'limit_num' => (int)$v->limit_num,
                        'limit_low_num' => (int)$v->limit_low_num,
                        'up_time' => (int)$v->up_time,
                        'down_time' => (int)$v->down_time,
                        'refund_time' => (int)$v->refund_time,
                        'end_time' => (int)$v->end_time,
                        'order_method' => $v->order_method,
                        'remark' => $v->remark,
                        'price_name' => $v->price_name
                    );

                    continue;
                } else {
                    $res['game_order'][] = array(
                        'order_id' => $v->id,  //套系id  time表
                        'way' => $v->price_name,  //参与方式 time表
                        'price' => $v->price,
                        'surplus_num' => $v->surplus_num, //剩余
                        'is_group' => 0, // 是否团购
                        'want_score' => $v->integral,//需要的积分才能购买
                        'tag' => GoodCache::getWelfareByInfo($v->id),
                        'item_info' => ['get_way' => $v->order_method, 'back_money' => $back_money, 'special_info' => $v->remark],
                        'buy_qualify' => 1,
                        'new_user_buy' => ((int)$v->for_new === 1) ? ($newuser ? 2 : 1) : 0,
                        'limit_num' => (int)$v->limit_num,
                        'limit_low_num' => (int)$v->limit_low_num,
                        'up_time' => (int)$v->up_time,
                        'down_time' => (int)$v->down_time,
                        'refund_time' => (int)$v->refund_time,
                        'end_time' => (int)$v->end_time,
                        'order_method' => $v->order_method,
                        'remark' => $v->remark,
                        'price_name' => $v->price_name
                    );
                }
            }

            //记录实际剩余数
            RedCache::set('D:SurplusNumber:' . $gameData->id, $res['surplus_num']);
            return $res;
        }, 60, true);
    }

    private function format_age($age)
    {
        $num = explode('.', $age);
        if ((int)$num[1] === 0) {
            $age = (int)$age;
        }
        return $age;
    }

    //商品详情3.3.1
    public function nindexAction()
    {
        if (!$this->pass(false)) {
            return $this->failRequest();
        }
        $id = (int)$this->getParams('id');
        $uid = (int)$this->getParams('uid', 0);  //uid 可选
        $client_id = $this->getParams('client_id');  //设备id

        $gameData = (object)$this->getGameData($id);

        $qual = $this->_getQualifyTable()->get(array('uid' => $uid, 'pay_time' => 0, 'status' => 1, 'valid_time>' . time()));

        if (!$gameData or !$gameData->id) {
            return $this->jsonResponseError('商品已下架');
        }

        $res = array(
            'cover' => $this->_getConfig()['url'] . $gameData->cover,
            'title' => $gameData->title,
            'buy' => ($gameData->buy_num + $gameData->coupon_vir) > 0 ? $gameData->buy_num + $gameData->coupon_vir : 0,  //累计报名数
            'surplus_num' => $gameData->ticket_num - $gameData->buy_num, //剩余票数
            'whole_score' => $gameData->star_num, //整体评分
            'consult_num' => $gameData->consult_num,   //咨询数目
            'post_number' => $gameData->post_number,      //评论数
            'is_group' => $gameData->g_buy ? 2 : 1,      //是否组团1/2
            //'can_back' => $gameData->refund_time > time() ? 1 : 0,//是否支持退款
            //'back_money' => $gameData->refund_time > time() ? '支持退款' : '不支持退款',  //是否支持退款描述
            //'get_money' => '', // 金额最高 现金券与返利  所有节点
            'information' => $this->_getConfig()['url'] . '/web/organizer/info?type=1&gid=' . $id,
            //'use_time' => $gameData->use_time,
            'matters' => $gameData->matters,
            'age_for' => ($gameData->age_max == 100) ? ($this->format_age($gameData->age_min) . '岁及以上') : ($this->format_age($gameData->age_min) . '岁到' . $this->format_age($gameData->age_max) . '岁'),
            //'order_method' => $gameData->order_method,
            'phone' => $gameData->phone,
            'start_sale_time' => $gameData->up_time,//开始售卖时间
            'end_sale_time' => $gameData->down_time, //停止售卖时间
            'buy_way' => $gameData->buy_way, //允许购买平台  1允许所有 2只允许客户端  3只允许微信
            //'buy_qualify' => 0, //是否有购买资格券
            'comments_gift' => $gameData->post_award == 2 ? 1 : 0,  //点评有礼
            'share_gift' => 0, //分享有礼
            'cooperation' => $gameData->is_together == 2 ? 1 : 0,//是否合作
            'is_together' => $gameData->is_together == 2 ? 1 : 0,//是否合作(ios 兼容)
            'now_time' => time(),// 服务器当前时间
            'is_private_party' => $gameData->is_private_party,
            'new_user_buy' => 0,  //新用户专享 0 不是新用户专享  1 新用户专享并可以购买  2 新用户专享但是用户为老用户
            'g_buy' => $gameData->g_buy,
            'g_price' => $gameData->g_price,
            'g_limit' => $gameData->g_limit,
            'g_list' => array(), //已开团列表
            'g_count' => 0,   //组团中
            'g_join_info' => array(),  //是否已组团,或加入过此商品的团
            'g_ok_count' => 0, //已完成
            'is_collect' => 0,
            'select_date'=> $gameData->need_use_time == 2 ? 1 : 0,
            'low_price' => $gameData->low_price ? $gameData->low_price : 0,
        );

        //是否收藏
        if ($uid) {
            $flag = M::getPlayUserCollectTable()->getCollect($uid,'good',$id);
            if ($flag) {
                $res['is_collect'] = 1;
            }
        }

        // 是否新用户专享
        if ($gameData->for_new == 1) {
            if (!$uid) {
                $res['new_user_buy'] = 1;
            } else {
                $user_data = $this->_getPlayUserTable()->get(array('uid' => $uid));

                $first = $this->_getPlayOrderInfoTable()->tableGateway->select(function (Select $select) use ($uid, $user_data, $client_id) {
                    $select->join('play_order_info_game', 'play_order_info_game.order_sn=play_order_info.order_sn');
                    $select->where("(user_id={$uid} and order_status=1) or phone='{$user_data->phone}' or client_id='{$client_id}'");
                    $select->limit(1);
                })->current();
                if ($first) {
                    $res['new_user_buy'] = 2;
                } else {
                    $res['new_user_buy'] = 1;
                }
            }
        }

        //查询组团中和组团成功的团
        if ($gameData->g_buy) {
            $g_list = $this->_getPlayGroupBuyTable()->tableGateway->select(function (Select $select) use ($gameData) {
                $select->columns(array('limit_number', 'join_number', 'id', 'end_time', 'status'));
                $select->join('play_user', 'play_user.uid=play_group_buy.uid', array('img', 'uid'), 'left'); //关联出团主
                $select->join('play_order_info', '(play_order_info.group_buy_id=play_group_buy.id and play_group_buy.uid=play_order_info.user_id)', array('pay_status', 'order_sn'));
                $select->where(array('((play_group_buy.status=1 and play_group_buy.end_time>' . time() . ' and play_order_info.pay_status=7) or (play_group_buy.status=2 and play_order_info.pay_status>=2))', 'play_group_buy.game_id' => $gameData->id));

            });


            $i = 0;
            foreach ($g_list as $v) {
                if ($v->status == 1) { //组团中
                    $res['g_count'] += 1;
                }
                if ($v->status == 2) { //组团中
                    $res['g_ok_count'] += 1;
                }


                if ($i < 4 and $v->status == 1) {
                    $res['g_list'][] = array(
                        'img' => $this->getImgUrl($v->img),//团长图片
                        'number' => $v->limit_number - $v->join_number,
                        'g_id' => $v->id
                    );
                    $i++;
                }

                //组团成功,也返回我的订单
                if ($v->uid == $uid) {
                    $res['g_join_info'][] = array(
                        'g_id' => $v->id,
                        'order_sn' => $v->order_sn,
                        'limit_number' => $v->limit_number,
                        'join_number' => $v->join_number,
                        'end_time' => $v->end_time,
                    );
                }

            }

            /**
             * 删除ios 未付款的团购  start
             */
            $Adapter = $this->_getAdapter();
            $group_sql = "SELECT play_order_info.order_sn FROM play_group_buy LEFT JOIN play_order_info ON  play_group_buy.id=play_order_info.group_buy_id WHERE play_group_buy.add_time > (UNIX_TIMESTAMP() - 7200) AND play_group_buy.join_number=0 AND play_group_buy.uid = ?  AND play_order_info.pay_status < ? AND play_order_info.order_status = ?";
            $groupData = $Adapter->query($group_sql, array($uid, 2, 1));
            if ($groupData->count()) {
                foreach ($groupData as $group) {
                    $this->cleanGroup($group['order_sn']);
                }
            }
            /* // end */

        }


        //查询我加入的团
        if ($uid and empty($res['g_join_info'])) {
            //查询我加入过的团
            $g_list = $this->_getPlayGroupBuyTable()->tableGateway->select(function (Select $select) use ($gameData, $uid) {
                $select->columns(array('limit_number', 'join_number', 'id', 'end_time', 'status'));
                $select->join('play_order_info', 'play_order_info.group_buy_id=play_group_buy.id', array('pay_status', 'order_sn'));
                $select->where(array('play_order_info.user_id' => $uid, 'play_group_buy.end_time>' . time(), 'play_group_buy.game_id' => $gameData->id, 'play_group_buy.status>0', 'play_order_info.pay_status>=2'))->limit(1);
            })->current();
            //组团成功,也返回我的订单

            if ($g_list) {
                $res['g_join_info'][] = array(
                    'g_id' => $g_list->id,
                    'order_sn' => $g_list->order_sn,
                    'limit_number' => $g_list->limit_number,
                    'join_number' => $g_list->join_number,
                    'end_time' => $g_list->end_time,
                );
            }
        }

        //节省用户流量
        if (!empty($res['g_join_info'])) {
            $res['g_list'] = array();
        }


        $cacheData1 = (array)$this->getCacheData($gameData);

        $res['shop'] = $cacheData1['shop'];
        $res['shop_num'] = $cacheData1['shop_num'];
        $res['phone'] = $cacheData1['phone'] ? $cacheData1['phone'] : '4008007221';
        $res['welfare'] = $cacheData1['welfare'];
        $res['game_list'] = $cacheData1['game_list'];

        $res = array_merge($res, $this->getCacheDataConsult($id));

        if ($gameData->status == -2) {
            $res['game_order'] = array();
            $res['g_buy'] = 0;
        } else {
            $order_data = (array)$this->getCacheGameOrder($gameData, $uid);
            //套系排序
            $game_order = $order_data['game_order'];
            $new_order = [];
            foreach ($game_order as $kk => $go) {
                $canbuy = 0;
                if ($go['up_time'] > time()) {//未开始售卖
                    $canbuy = 9999999;
                }
                //停止售卖的 售空的
                if ($go['surplus_num'] < 1 or $go['down_time'] < time()) {//未开始售卖
                    $canbuy = 9999999;
                }
                //资格
                $go['buy_qualify'] = ((int)$go['buy_qualify'] === 2 and !$qual) ? 0 : 1;
                if ($go['buy_qualify'] == 0) {
                    $canbuy = 9999999;
                }
                //新用户
                if ($go['new_user_buy'] == 2) {
                    $canbuy = 9999999;
                }
                $go['canbuy'] = $canbuy + $go['price'];//之前排序使用
                $new_order[$kk] = $go;
            }

            $res['game_order'] = $new_order;

            $res['surplus_num'] = (int)$order_data['surplus_num']; //重新统计的数据
        }

        $share_title = $gameData->share_title ? $gameData->share_title : $gameData->title;
        $res['editor_talk'] = str_replace(array(" ", "　", "\t", "\n", "\r"), '', $gameData->editor_talk);  //小玩说

        $res['share_title'] = '【玩翻天】' . $share_title;
        $res['share_url'] = "http://play.wanfantian.com/ticket/commoditydetail?id={$id}";
        $res['share_img'] = $this->_getConfig()['url'] . $gameData->thumb;
        $res['share_content'] = '我发现了一个不错的商品，' . $res['game_order'][0]['price'] . '元起，我们一起报名吧。' . $res['editor_talk'];  //分享描述


        /******************* 评论列表 ********************/
        if (!$cacheData1['shop_id_list']) {
            $cacheData1['shop_id_list'] = array();
        }
        $all_ids = [];//所有商品的id
        if (count($cacheData1['shop_id_list'])) {
            $allinfo = $this->_getPlayGameInfoTable()->fetchAll(['shop_id' => $cacheData1['shop_id_list']], [], 300)->toArray();
            if (count($allinfo)) {
                $all_ids = [];//所有商品的id
                foreach ($allinfo as $a) {
                    if (!in_array((int)$a['gid'], $all_ids)) {
                        $all_ids[] = (int)$a['gid'];
                    }
                }
                if (!count($all_ids)) {
                    $all_ids = [];
                }
            }
        }

        $where = array(
            '$or' => array(
                array('msg_type' => 2, 'object_data.object_id' => (int)$id),
                array('msg_type' => 3, 'object_data.object_id' => array('$in' => $cacheData1['shop_id_list'])),
                array('msg_type' => 2, 'object_data.object_id' => array('$in' => $all_ids))
                //array('shop_id' => array('$in' => $cacheData1['shop_id_list']))
            ),
            'status' => array('$gt' => 0)
        );
        $post_data = $this->_getMdbSocialCircleMsg()->find($where)->sort(array('status' => -1, 'like_number' => -1, '_id' => -1))->limit(10);
        $res['post_number'] = $this->_getMdbSocialCircleMsg()->find($where)->count();
        $res['post_list'] = array();
        foreach ($post_data as $v) {
            $is_like = 0;
            if ($uid) {
                $like_data = $this->_getMdbSocialPrise()->findOne(array(
                    'uid' => (int)$uid, //用户id
                    'type' => 1, //类型 圈子消息
                    'object_id' => (string)$v['_id'],
                ));

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
                'score' => $v['star_num'] ? : 0,
                'accept' => (isset($v['accept']) && $v['accept']) ? 1 : 0,// 小编采纳
                'is_like' => $is_like,
                'like_number' => $v['like_number'],
                'reply_number' => $v['replay_number'],
                'link_name' => (isset($v['object_data']['object_place']) && $v['object_data']['object_place']) ? $v['object_data']['object_place'] : '', // 用户购买过的对应游玩地名称
            );
        }

//        echo json_encode($res['post_list']);exit;

        /********************** 评论列表 end ***************************/

        //判断资格券

        $pgpt = $this->_getPlayGamePriceTable()->fetchLimit(0, 100, [], ['gid' => $id])->toArray();
        $qualified = 0;
        foreach ($pgpt as $p) {
            if ($p['qualified'] == 2) {
                $qualified = 1;
            }
        }

        if ($qualified == 0) { //无需
            $res['buy_qualify'] = 1;
        } elseif ($qualified == 1) { //需
            if ($uid) {
                //$qual = $this->_getQualifyTable()->get(array('uid' => $uid, 'pay_time' => 0, 'status' => 1, 'valid_time>' . time()));
                if ($qual) {
                    $res['buy_qualify'] = 1;
                } else {
                    $res['buy_qualify'] = 0;
                }
            } else {
                $res['buy_qualify'] = 1;
            }

        }

        $res['share_gift'] = 0;
        $res['buy_log'] = 0;

        //用户数据
        if ($uid) {
            $inte = new Integral();
            $res['user_score'] = (int)$inte->getUserIntegral($uid); //用户的积分
            $res['share_gift'] = (int)$inte->get_share_good_integral($uid, $id);
            if ($gameData->is_together == 1) {
                $res['buy_log'] = (int)$this->_getPlayOrderInfoTable()->get(array('user_id' => $uid, 'coupon_id' => $id, 'pay_status>=2')); //是否买过
            } else { //非合作 可以评论
                $res['buy_log'] = 1;
            }
        }

        return $this->jsonResponse($res);
    }

    //商品详情3.3版本
    public function indexAction()
    {

        if (!$this->pass()) {
            return $this->failRequest();
        }
        $id = (int)$this->getParams('id');
        $uid = (int)$this->getParams('uid', 0);  //uid 可选
        $client_id = $this->getParams('client_id');  //设备id

        $gameData = (object)$this->getGameData($id);


        if (!$gameData or !$gameData->id) {
            return $this->jsonResponseError('商品已下架');
        }
        $use_time = $this->use_time($gameData, $uid);

        $res = array(
            'cover' => $this->_getConfig()['url'] . $gameData->cover,
            'title' => $gameData->title,
            'buy' => $gameData->buy_num + $gameData->coupon_vir,  //累计报名数
            'surplus_num' => $gameData->ticket_num - $gameData->buy_num, //剩余票数
            'whole_score' => $gameData->star_num, //整体评分
            'consult_num' => $gameData->consult_num,   //咨询数目
            'post_number' => $gameData->post_number,      //评论数
            'is_group' => $gameData->g_buy ? 2 : 1,      //是否组团1/2
            'can_back' => $gameData->refund_time > time() ? 1 : 0,//是否支持退款
            'back_money' => $gameData->refund_time > time() ? '支持退款' : '不支持退款',  //是否支持退款描述
            'get_money' => '', // 金额最高 现金券与返利  所有节点
            'information' => $this->_getConfig()['url'] . '/web/organizer/info?type=1&gid=' . $id,
            'use_time' => $use_time[0] ?: '',
            'matters' => $gameData->matters,
            'age_for' => ($gameData->age_max == 100) ? ($gameData->age_min . '岁及以上') : ($gameData->age_min . '岁到' . $gameData->age_max . '岁'),
            'order_method' => $this->order_method($gameData, $uid),
            'phone' => $gameData->phone,
            'start_sale_time' => $gameData->up_time,//开始售卖时间
            'end_sale_time' => $gameData->down_time, //停止售卖时间
            'buy_way' => $gameData->buy_way, //允许购买平台  1允许所有 2只允许客户端  3只允许微信
            'buy_qualify' => 1, //是否有购买资格券
            'comments_gift' => $gameData->post_award == 2 ? 1 : 0,  //点评有礼
            'share_gift' => 0, //分享有礼
//            'share_title' => $gameData->share_title ? $gameData->share_title : $gameData->title,
//            'share_url' => $this->_getConfig()['url'] . "/web/organizer/game?id={$id}",
//            'share_img' => $this->_getConfig()['url'] . $gameData->thumb,
//            'share_content' => $gameData->editor_talk,  //小玩说 也是分享描述
            'cooperation' => $gameData->is_together == 2 ? 1 : 0,//是否合作
            'is_together' => $gameData->is_together == 2 ? 1 : 0,//是否合作(ios 兼容)
            'now_time' => time(),// 服务器当前时间
            'is_private_party' => $gameData->is_private_party,
            'new_user_buy' => 0,  //新用户专享 0 不是新用户专享  1 新用户专享并可以购买  2 新用户专享但是用户为老用户
            'g_buy' => $gameData->g_buy,
            'g_price' => $gameData->g_price,
            'g_limit' => $gameData->g_limit,
            'g_list' => array(), //已开团列表
            'g_count' => 0,   //组团中
            'g_join_info' => array(),  //是否已组团,或加入过此商品的团
            'g_ok_count' => 0 //已完成
        );

        if ($res['buy'] < 0) {
            $res['buy'] = 0;
        }

        $res['new_user_buy'] = 0;
        //查询组团中和组团成功的团
        if ($gameData->g_buy) {
            $g_list = $this->_getPlayGroupBuyTable()->tableGateway->select(function (Select $select) use ($gameData) {
                $select->columns(array('limit_number', 'join_number', 'id', 'end_time', 'status'));
                $select->join('play_user', 'play_user.uid=play_group_buy.uid', array('img', 'uid'), 'left'); //关联出团主
                $select->join('play_order_info', '(play_order_info.group_buy_id=play_group_buy.id and play_group_buy.uid=play_order_info.user_id)', array('pay_status', 'order_sn'));
                $select->where(array('((play_group_buy.status=1 and play_group_buy.end_time>' . time() . ' and play_order_info.pay_status=7) or (play_group_buy.status=2 and play_order_info.pay_status>=2))', 'play_group_buy.game_id' => $gameData->id));

            });


            $i = 0;
            foreach ($g_list as $v) {
                if ($v->status == 1) { //组团中
                    $res['g_count'] += 1;
                }
                if ($v->status == 2) { //组团中
                    $res['g_ok_count'] += 1;
                }

                if ($i < 4 and $v->status == 1) {
                    $res['g_list'][] = array(
                        'img' => $this->getImgUrl($v->img),//团长图片
                        'number' => $v->limit_number - $v->join_number,
                        'g_id' => $v->id
                    );
                    $i++;
                }

                //组团成功,也返回我的订单
                if ($v->uid == $uid) {
                    $res['g_join_info'][] = array(
                        'g_id' => $v->id,
                        'order_sn' => $v->order_sn,
                        'limit_number' => $v->limit_number,
                        'join_number' => $v->join_number,
                        'end_time' => $v->end_time,
                    );
                }
            }

            /**
             * 删除ios 未付款的团购  start
             */
            $Adapter = $this->_getAdapter();
            $group_sql = "SELECT play_order_info.order_sn FROM play_group_buy LEFT JOIN play_order_info ON  play_group_buy.id=play_order_info.group_buy_id WHERE play_group_buy.add_time > (UNIX_TIMESTAMP() - 7200) AND play_group_buy.join_number=0 AND play_group_buy.uid = ?  AND play_order_info.pay_status < ? AND play_order_info.order_status = ?";
            $groupData = $Adapter->query($group_sql, array($uid, 2, 1));
            if ($groupData->count()) {
                foreach ($groupData as $group) {
                    $this->cleanGroup($group['order_sn']);
                }
            }
            /* // end */
        }


        //查询我加入的团
        if ($uid and empty($res['g_join_info'])) {
            //查询我加入过的团
            $g_list = $this->_getPlayGroupBuyTable()->tableGateway->select(function (Select $select) use ($gameData, $uid) {
                $select->columns(array('limit_number', 'join_number', 'id', 'end_time', 'status'));
                $select->join('play_order_info', 'play_order_info.group_buy_id=play_group_buy.id', array('pay_status', 'order_sn'));
                $select->where(array('play_order_info.user_id' => $uid, 'play_group_buy.end_time>' . time(), 'play_group_buy.game_id' => $gameData->id, 'play_group_buy.status>0', 'play_order_info.pay_status>=2'))->limit(1);
            })->current();
            //组团成功,也返回我的订单

            if ($g_list) {
                $res['g_join_info'][] = array(
                    'g_id' => $g_list->id,
                    'order_sn' => $g_list->order_sn,
                    'limit_number' => $g_list->limit_number,
                    'join_number' => $g_list->join_number,
                    'end_time' => $g_list->end_time,
                );
            }
        }

        //节省用户流量
        if (!empty($res['g_join_info'])) {
            $res['g_list'] = array();
        }


        $cacheData1 = (array)$this->getCacheData($gameData);

        $res['shop'] = $cacheData1['shop'];
        $res['shop_num'] = $cacheData1['shop_num'];
        $res['phone'] = $cacheData1['phone'] ? $cacheData1['phone'] : '4008007221';
        $res['welfare'] = $cacheData1['welfare'];
        $res['game_list'] = $cacheData1['game_list'];

        $res = array_merge($res, $this->getCacheDataConsult($id));

        if ($gameData->status == -2) {
            $res['game_order'] = array();
            $res['g_buy'] = 0;
        } else {
            $order_data = (array)$this->getCacheGameOrder33($gameData, $uid);
            $res['game_order'] = $order_data['game_order'];
            $res['surplus_num'] = (int)$order_data['surplus_num']; //重新统计的数据
        }


        $share_title = $gameData->share_title ? $gameData->share_title : $gameData->title;
        $res['share_title'] = '【玩翻天】' . $share_title;
        $res['share_url'] = "http://play.wanfantian.com/ticket/commoditydetail?id={$id}";
        $res['share_img'] = $this->_getConfig()['url'] . $gameData->thumb;
        /*$res['share_content'] = '我发现了一个不错的商品，'.$res['game_order'][0]['price'].'元起，我们一起报名吧。'.str_replace(array(" ","　","\t","\n","\r"),'',$gameData->editor_talk);  //小玩说 也是分享描述*/

        $res['share_content'] = str_replace(array(" ", "　", "\t", "\n", "\r"), '', $gameData->editor_talk);  //小玩说 也是分享描述

        /******************* 评论列表 ********************/
        if (!$cacheData1['shop_id_list']) {
            $cacheData1['shop_id_list'] = array();
        }
        $where = array(
            '$or' => array(
                array('msg_type' => 2, 'object_data.object_id' => (int)$id),
                array('msg_type' => 3, 'object_data.object_id' => array('$in' => $cacheData1['shop_id_list']))
                //array('shop_id' => array('$in' => $cacheData1['shop_id_list']))
            ),
            'status' => array('$gt' => 0)
        );
        $post_data = $this->_getMdbSocialCircleMsg()->find($where)->sort(array('status' => -1, 'like_number' => -1, '_id' => -1))->limit(10);
        $res['post_number'] = $post_data->count();
        $res['post_list'] = array();
        foreach ($post_data as $v) {
            $is_like = 0;
            if ($uid) {
                $like_data = $this->_getMdbSocialPrise()->findOne(array(
                    'uid' => (int)$uid, //用户id
                    'type' => 1, //类型 圈子消息
                    'object_id' => (string)$v['_id'],
                ));

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
                'score' => $v['star_num'] ? $v['star_num'] : 0,
                'accept' => (isset($v['accept']) && $v['accept']) ? 1 : 0,// 小编采纳
                'is_like' => $is_like,
                'like_number' => $v['like_number'],
                'reply_number' => $v['replay_number'],
                'link_name' => (isset($v['object_data']['object_place']) && $v['object_data']['object_place']) ? $v['object_data']['object_place'] : '', // 用户购买过的对应游玩地名称
            );
        }

//        echo json_encode($res['post_list']);exit;

        /********************** 评论列表 end ***************************/

        //判断资格券
        $res['buy_qualify'] = 1;

        $res['share_gift'] = 0;
        $res['buy_log'] = 0;
        //用户数据
        if ($uid) {
            $inte = new Integral();
            $res['user_score'] = (int)$inte->getUserIntegral($uid); //用户的积分
            $res['share_gift'] = (int)$inte->get_share_good_integral($uid, $id);
            if ($gameData->is_together == 1) {
                $res['buy_log'] = (int)$this->_getPlayOrderInfoTable()->get(array('user_id' => $uid, 'coupon_id' => $id, 'pay_status>=2')); //是否买过
            } else { //非合作 可以评论
                $res['buy_log'] = 1;
            }
        }

        $res['can_back'] = (int)$use_time[1];
        $res['back_money'] = $use_time[1] ? '支持退款' : '不支持退款';

        return $this->jsonResponse($res);
    }

    //选择商品套餐3.3
    public function selectListAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $city = $this->getCity();
        $uid = (int)$this->getParams('uid', 0);
        $coupon_id = (int)$this->getParams('coupon_id', 0);
        $order_id = (int)$this->getParams('order_id', 0);//套系id
        $g_buy = (int)$this->getParams('g_buy', 0);//是否团购
        $g_buy_id = (int)$this->getParams('g_buy_id', 0); //加入团id
        if (!$uid or !$coupon_id or !$order_id) {
            return $this->jsonResponseError('参数错误');
        }


        $db = $this->_getAdapter();
        $order_data = $db->query("SELECT play_game_info.*,play_shop.busniess_circle FROM play_game_info LEFT JOIN play_shop ON play_shop.shop_id=play_game_info.shop_id WHERE gid=? and status=1 AND play_game_info.end_time>?", array($coupon_id, time()));
        $gameData = $this->_getPlayOrganizerGameTable()->get(array('id' => $coupon_id));

        $data = array();
        $data['game_order'] = array();
        foreach ($order_data as $v) {
            $data['game_order'][] = array(
                "order_id" => $v->id,
                "way" => (($g_buy or $g_buy_id) and $gameData->g_set_name) ? $gameData->g_set_name : $v->price_name,  //参与方式 time表
                "s_time" => $v->start_time,
                "e_time" => $v->end_time,
                "price" => $g_buy ? $gameData->g_price : $v->price,
                "money" => $v->money,
                "buy" => $g_buy_id ? 0 : $v->buy,//如果加入团,不判断数量
                "total_num" => $v->total_num,
                "shop_name" => $v->shop_name,
                "shop_id" => $v->shop_id,
                "address" => CouponCache::getBusniessCircle($v->busniess_circle, 'WH'),//"汉阳 钟家村",
                "want_score" => $v->integral,   // 非0为所需要的积分
                "max_buy" => $v->limit_num,
                "min_buy" => $v->limit_low_num,
                "thumb" => $this->getImgUrl($gameData->thumb),
                'insure_num_per_order' => $v->insure_num_per_order,//购买保险时返回每单保险人数
                'has_addr' => $gameData->has_addr  //是否必填收货地址，0不用填，1必填
            );
        }

        //modify by wzxiang 2016.4.12 获取默认联系人
        $phone_data = $this->_getPlayUserLinkerTable()->get(array('user_id' => $uid, 'is_default' => 1));

        //最少有一个联系人,用户绑定的
        if ($phone_data) {
            $data['contacts'] = array(
                'id' => $phone_data->linker_id,
                'name' => $phone_data->linker_name,
                'phone' => $phone_data->linker_phone,
                'post_code' => $phone_data->linker_post_code,   //邮编
                'province' => $phone_data->province, //省份
                'city' => $phone_data->city, //城市
                'region' => $phone_data->region,     //地区
                'address' => $phone_data->linker_addr,     //地址

            );
//            if ($gameData->has_addr == 1) {
            $data['contacts']['post_code'] = $phone_data->linker_post_code;   //邮编
            $data['contacts']['province'] = $phone_data->province; //省份
            $data['contacts']['city'] = $phone_data->city; //城市
            $data['contacts']['region'] = $phone_data->region;     //地区
            $data['contacts']['address'] = $phone_data->linker_addr;     //地址
//            }
        } else {
            $uid_info = $this->_getPlayUserTable()->get(array('uid' => $uid));

            $data['contacts'] = array(
                'id' => 0,
                'name' => $uid_info->username,
                'phone' => $uid_info->phone
            );
        }

        $data['tips'] = ""; // 当前套系 所有节点最高  价格相同取购买后返利

        //获取最大返利
        $welfare = $this->_getPlayWelfareTable()->fetchAll(array('object_id' => $coupon_id, 'good_info_id' => $order_id, 'object_type' => 2, '(welfare_type=2 or welfare_type=3)', 'status' => 2), array('welfare_value' => 'desc'), 1)->current();
        if ($welfare) {
            $desc = $welfare->welfare_type == 2 ? '现金' : '现金券';
            $data['tips'] = "最高返利{$welfare->welfare_value}元" . $desc;
        }

        $account = new Account();
        $data['user_money'] = (string)$account->getUserMoney($uid); //余额
        $data['is_comments_value'] = $gameData->is_comments_value;  //备注是否必填
        $data['message'] = $gameData->comments_value; //备注


        return $this->jsonResponse($data);


    }

    //选择商品套餐3.3.1
    public function nselectListAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $city      = $this->getCity();
        $uid       = (int)$this->getParams('uid', 0);
        $coupon_id = (int)$this->getParams('coupon_id', 0);
        $order_id  = (int)$this->getParams('order_id', 0);//套系id
        $g_buy     = (int)$this->getParams('g_buy', 0);//是否团购
        $g_buy_id  = (int)$this->getParams('g_buy_id', 0); //加入团id
        if (!$uid or !$coupon_id or !$order_id) {
            return $this->jsonResponseError('参数错误');
        }

        $db = $this->_getAdapter();

        $gameData = $this->_getPlayOrganizerGameTable()->get(array('id' => $coupon_id));

        if ($gameData->need_use_time == 2) {
            //判断是否老版本
            $data_client_info = Common::getClientinfo();

            if($data_client_info['client'] === 'ios' || $data_client_info['client'] === 'android'){
                $ver = sprintf('%-03s', str_replace('.', '', $data_client_info['ver']));
                if ($ver < 334) {
                    return $this->jsonResponseError('请到应用商店或官网下载最新版本');
                }
            }
        }

        $pgi = $this->_getPlayGameInfoTable()->get(['id' => $order_id]);
        $pid = $pgi->pid;
        $price_info = $this->_getPlayGamePriceTable()->get(['id' => $pid]);

        $data = array();
        $data['get_way'] = $price_info->order_method;
        $data['special_info'] = $price_info->remark;
        $data['game_order'] = array();

        // 如果不必须选择使用日期或不确定，则会获取套系信息

        $order_data = $db->query(
            "SELECT play_game_info.*,play_shop.busniess_circle, play_game_price.back_rule, play_game_price.refund_before_day, play_game_price.refund_before_time
             FROM play_game_info
             LEFT JOIN play_game_price ON play_game_price.id = play_game_info.pid
             LEFT JOIN play_shop ON play_shop.shop_id = play_game_info.shop_id
             WHERE play_game_info.gid = ? AND status = 1 AND play_game_info.end_time > ? ",
             array($coupon_id, time())
        );

        $data_low_price        = 0;
        $data_order_start_time = 0;
        $data_order_end_time   = 0;
        foreach ($order_data as $v) {
            if ($pgi->price_name == $v->price_name) {
                if ($v->back_rule == 1) {
                    if ($v->refund_before_day) {
                        $back_money = "在游玩日期后第" . $v->refund_before_day . "天的" . date('H:i', $v->refund_before_time) . '前支持退款';
                    } else {
                        $back_money = "在游玩日期当天的" . date('H:i', $v->refund_before_time) . '前支持退款';
                    }
                } else {
                    $back_money = ($v->refund_time < $v->up_time) ? '不支持退款' : date('Y.m.d H:i', $v->refund_time) . '前支持退款';
                }

                $data_price = $g_buy ? $gameData->g_price : $v->price;
                if ($data_price < $data_low_price || $data_low_price === 0) {
                    $data_low_price = $data_price;
                }

                if ($v->start_time < $data_order_start_time || $data_order_start_time === 0) {
                    $data_order_start_time = $v->start_time;
                }

                if ($v->start_time > $data_order_end_time) {
                    $data_order_end_time = $v->start_time;
                }

                if ($gameData->need_use_time != 2) {
                    $data['game_order'][] = array(
                        'order_id' => $v->id,
                        'way' => (($g_buy or $g_buy_id) and $gameData->g_set_name) ? $gameData->g_set_name : $v->price_name,  //参与方式 time表
                        's_time' => $v->start_time,
                        'e_time' => $v->end_time,
                        'price' => $data_price,
                        'money' => $v->money,
                        'buy' => $g_buy_id ? 0 : $v->buy,//如果加入团,不判断数量
                        'total_num' => $v->total_num,
                        'shop_name' => $v->shop_name,
                        'shop_id' => $v->shop_id,
                        'address' => CouponCache::getBusniessCircle($v->busniess_circle, 'WH'),//"汉阳 钟家村",
                        'want_score' => $v->integral,   // 非0为所需要的积分
                        'max_buy' => $v->limit_num,
                        'min_buy' => $v->limit_low_num,
                        'thumb' => $this->getImgUrl($gameData->thumb),
                        'insure_num_per_order' => $v->insure_num_per_order,//购买保险时返回每单保险人数
                        'back_money' => $back_money,
                        'has_addr' => $gameData->has_addr  //是否必填收货地址，0不用填，1必填
                    );
                }
            }
        }

        //modify by wzxiang 2016.4.12 获取默认联系人
        $phone_data = $this->_getPlayUserLinkerTable()->get(array('user_id' => $uid, 'is_default' => 1));

        //最少有一个联系人,用户绑定的
        if ($phone_data) {
            $data['contacts'] = array(
                'id' => $phone_data->linker_id,
                'name' => $phone_data->linker_name,
                'phone' => $phone_data->linker_phone,
                'post_code' => $phone_data->linker_post_code,   //邮编
                'province' => $phone_data->province, //省份
                'city' => $phone_data->city, //城市
                'region' => $phone_data->region,     //地区
                'address' => $phone_data->linker_addr,     //地址

            );
//            if ($gameData->has_addr == 1) {
            $data['contacts']['post_code'] = $phone_data->linker_post_code;   //邮编
            $data['contacts']['province'] = $phone_data->province; //省份
            $data['contacts']['city'] = $phone_data->city; //城市
            $data['contacts']['region'] = $phone_data->region;     //地区
            $data['contacts']['address'] = $phone_data->linker_addr;     //地址
//            }
        } else {
            $uid_info = $this->_getPlayUserTable()->get(array('uid' => $uid));

            $data['contacts'] = array(
                'id' => 0,
                'name' => $uid_info->username,
                'phone' => $uid_info->phone
            );
        }

        $data['tips'] = ""; // 当前套系 所有节点最高  价格相同取购买后返利

        //获取最大返利
        $welfare = $this->_getPlayWelfareTable()->fetchAll(array('object_id' => $coupon_id, 'good_info_id' => $order_id, 'object_type' => 2, '(welfare_type=2 or welfare_type=3)', 'status' => 2), array('welfare_value' => 'desc'), 1)->current();
        if ($welfare) {
            $desc = $welfare->welfare_type == 2 ? '现金' : '现金券';
            $data['tips'] = "最高返利{$welfare->welfare_value}元" . $desc;
        }

        $account = new Account();
        $data['user_money']           = (string)$account->getUserMoney($uid); // 余额
        $data['is_comments_value']    = $gameData->is_comments_value;         // 备注是否必填
        $data['message']              = $gameData->comments_value;            // 备注
        $data['low_price']            = $data_low_price;                      // 一个套系中最低售价
        $data['max_buy']              = $price_info->limit_num;               // 单个用户最大购买数
        $data['min_buy']              = $price_info->limit_low_num;           // 单个用户最小购买数
        $data['insure_num_per_order'] = $price_info->insure_num_per_order;    // 每单保险人数
        $data['has_addr']             = $gameData->has_addr;                  // 是否必填收货地址
        $data['want_score']           = $price_info->integral;                // 套系对应的积分
        $data['select_date']          = $gameData->need_use_time == 2 ? 1 : 0;// 是否必选游玩日期
        $data['way']                  = (($g_buy || $g_buy_id) && $gameData->g_set_name) ? $gameData->g_set_name : $pgi->price_name;
        $data['order_start_time']     = $data_order_start_time;
        $data['order_end_time']       = $data_order_end_time;

        return $this->jsonResponse($data);
    }

    //选择日期控件
    public function calendarAction(){
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $param['id']         = (int)$this->getParams('id', 0);       //商品id
        $param['order_id']   = (int)$this->getParams('order_id', 0); //套系中价格最低场地的关联id
        $param['start_time'] = $this->getParams('start_time');       //时间戳,取指定月份的数据
        $param['end_time']   = $this->getParams('end_time');         //时间戳,取指定月份的数据

        if (empty($param['id']) || empty($param['order_id']) || empty($param['start_time']) || empty($param['end_time'])) {
            return $this->jsonResponseError('参数错误');
        }

        $data_game_info  = $this->_getPlayGameInfoTable()->get(array('id' => $param['order_id']));

        $pdo             = $this->_getAdapter();
        $data_order_info = $pdo->query(
            "SELECT pgi.*, ps.busniess_circle, pgp.back_rule, pgp.refund_before_day, pgp.refund_before_time, pgp.book_hours, pgp.book_time FROM play_game_info pgi
             LEFT JOIN play_game_price pgp ON pgp.id = pgi.pid
             LEFT JOIN play_shop ps ON ps.shop_id = pgi.shop_id
             WHERE pgi.pid = ? AND pgi.status = 1 AND pgi.start_time >= ? AND pgi.start_time <= ? ORDER BY pgi.start_time ASC",
            array(
                $data_game_info['pid'],
                $param['start_time'],
                $param['end_time']
            )
        );

        $data_return = array();
        if (!empty($data_order_info)) {
            foreach ($data_order_info as $key => $val) {
                if ($val->back_rule == 1) {
                    $data_time  = date('Y年m月d日', $val->start_time + $val->refund_before_day * 86400) ;
                    $back_money = "在" . $data_time . "的" . date('H:i', $val->refund_before_time) . '前支持退款';
                } else {
                    $back_money = ($val->refund_time < $val->up_time) ? '不支持退款' : date('Y.m.d H:i', $val->refund_time) . '前支持退款';
                }

                $data_return[] = array(
                    'order_id'             => $val->id,
                    'time'                 => $val->start_time,
                    'price'                => floatval($val->price),
                    'money'                => floatval($val->money),
                    'buy'                  => $val->buy,//购买数
                    'total_num'            => $val->total_num,
                    'shop_id'              => $val->shop_id,
                    'back_money'           => $back_money,
                    'status'               => $this->checkStatus($val)    // 0置灰（不在预定时间内或其他情况无法购买）1正常
                );
            }
        }

        return $this->jsonResponse($data_return);
    }

    // 判断当前日期是否可以购买该套系
    public function checkStatus($game_info) {
        $day_time = strtotime(date("Y-m-d", $game_info->start_time));
        $time     = strtotime(date("H:i:s", $game_info->book_time)) - strtotime(date("Y-m-d", time()));
        // 如果开始时间比现在时间晚
        if (time() < $game_info->up_time) {
            return 0;
        } elseif (time() > $game_info->down_time) {
            return 0;
        } else {
            if (time() + $game_info->book_hours * 3600 > $day_time + $time) {
                return 0;
            } else {
                return 1;
            }
        }
    }

    private function getGood($sort) {
        $timer = time();

        $where = "play_organizer_game.status > 0 AND play_label_linker.link_type = 2 AND play_organizer_game.end_time >= {$timer} AND play_organizer_game.start_time <= {$timer}";

        $where .= ' AND play_label_linker.lid = ' . $sort;

        $order = 'play_organizer_game.click_num DESC';

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
LIMIT 10
";

        $res = $this->query($sql);
        $data = array();
        foreach ($res as $val) {
            $data[] = array(
                'id' => $val['object_id'],
                'cover' => $this->_getConfig()['url'] . $val['thumb'],
                'name' => $val['title'],
                'price' => $val['low_price'],
                'have' => (((int)$val['is_together'] === 1) && ($val['down_time'] > $timer) && (($val['ticket_num'] - $val['buy_num']) > 0)) ? ($val['ticket_num'] - $val['buy_num']) : 0,
                'end_time' => $val['down_time'],
                'old_price' => $val['low_money'],
                'g_buy' => (($val['down_time'] - $timer) > 86400) ? $val['g_buy'] : '0',
            );
        }
        return $data;
    }

    //发现接口
    public function listAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $time = time();
        $come = $this->getParams('come');
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

            $topicMaps = $this->_getPlayActivityTable()->fetchLimit(0, 4, array(), $topicWhere, $order = array('discovery' => 'DESC', 'dateline' => 'DESC'));

            foreach ($topicMaps as $topic) {
                $data['topic'][] = array(
                    'id' => $topic->id,
                    'name' => $topic->ac_name,
                    'cover' => $this->_getConfig()['url'] . $topic->ac_cover,
                );
            }
        }

        $tagData = $this->_getPlayLabelTable()->fetchLimit(0, 60, $columns = array(), array('status >= ?' => 2, 'city' => $city), array('dateline' => 'desc'));

        foreach ($tagData as $tag) {
            $data['tag'][] = array(
                'id' => $tag['id'],
                'coin' => $tag['coin'] ? $this->_getConfig()['url'] . $tag['coin'] : '',
                'name' => $tag['tag_name'],
                'cover' => $tag['cover'] ? $this->_getConfig()['url'] . $tag['cover'] : '',
                'description' => $tag['description'],
                'place' => 254,
                'pay' => 258
            );
        }

        $data['total'] = 758;

        return $this->jsonResponse($data);

    }

    //商品详情 带订单3.3
    public function haveAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $id = $this->getParams('id');   //商品id
        $rid = $this->getParams('rid'); //订单id
        $gameData = $this->_getPlayOrganizerGameTable()->get(array('id' => $id));
        $orderInfo = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $rid, 'order_status' => 1));
        if (!$gameData || !$orderInfo) {
            return $this->jsonResponseError('该商品 或者订单不存在');
        }

        if ($orderInfo->pay_status < 2) {//未付款 //0未付款;1付款中; 2已付款 3 退款中 4 退款成功 5已使用
            return $this->jsonResponseError('订单未付款');
        }

        $game_order = $this->_getPlayOrderInfoGameTable()->get(array('order_sn' => $rid));
        $game_info = $this->_getPlayGameInfoTable()->get(array('id' => $game_order->game_info_id));
        $shopData = $this->_getPlayShopTable()->get(array('shop_id' => $game_info->shop_id));

        $adapter = $this->_getAdapter();
        //$price_info = $this->_getPlayGamePriceTable()->get($game_info->pid);

        //统计点击次数
        $this->_getPlayOrganizerGameTable()->update(array('click_num' => new Expression('click_num+1')), array('id' => $id));

        // 前面需判断是否智游宝商品
        $zyb_data = $adapter->query("select * from play_zyb_info WHERE order_sn=? AND status >= ?", array($rid, 2))->current();

        if ($zyb_data->zyb_type == 2) {
            return $this->jsonResponseError('服务君出状况了，如有疑问请联系4008007221');
        }

        if ($zyb_data && $zyb_data->play_end_time) {
            $attend_start_time = $zyb_data->play_start_time;
            $attend_end_time = $zyb_data->play_end_time;
        } else {
            $attend_start_time = $game_info->start_time;
            $attend_end_time = $game_info->end_time;
        }

        if ($orderInfo->group_buy_id) {
            $can_back = 0;
        } else {
            if ($zyb_data && $zyb_data->play_end_time) {
                $can_back = ($game_info->refund_time > time() && $zyb_data->play_end_time > time()) ? 1 : 0; //是否可以退款 1 可以 0 不可以
            } else {
                $can_back = ($game_info->refund_time > time()) ? 1 : 0; //是否可以退款 1 可以 0 不可以
            }
        }
        //商品相关
        $res = array(
            'title' => $gameData->title, //商品名称
            'money' => bcadd($orderInfo->real_pay, $orderInfo->account_money, 2),  // 显示支付金额
            'back' => $can_back
        );

        if ($zyb_data && $zyb_data->play_end_time) {
            $res['tips'] = ($game_info->refund_time > time() && $zyb_data->play_end_time > time()) ? "您想要取消参加{$gameData->title}, 报名费3个工作日内还给你的支付宝，下次别放我鸽子哈！" : "您想要取消参加{$gameData->title}, 这个是不退款的活动，你确定不参加了？";
        } else {
            $res['tips'] = ($game_info->refund_time > time()) ? "您想要取消参加{$gameData->title}, 报名费3个工作日内还给你的支付宝，下次别放我鸽子哈！" : "您想要取消参加{$gameData->title}, 这个是不退款的活动，你确定不参加了？";
        }

        $res['attend_way'] = $gameData->g_set_name ? $gameData->g_set_name : $game_info->price_name; //产品名称
        $res['order_id'] = $rid; //订单id
        $res['attend_start_time'] = $attend_start_time; //$game_info->start_time;//出行开始时间
        $res['attend_end_time'] = $attend_end_time; //$game_info->end_time; //出行结束时间
        $res['order_method'] = $game_info->order_method;//使用方法
        $res['ask_phone'] = $gameData->phone ? $gameData->phone : '4008007221'; //联系电话
        $res['use_time_txt'] = $game_info->remark; //使用时间
        $back_money = ($game_info->refund_time < $game_info->up_time) ? '不支持退款' : (($game_info->refund_time > $game_info->end_time) ? '支持随时退款' : date('Y.m.d H:i', $game_info->refund_time) . '前支持退款');
        $res['back_money'] = $back_money; //是否退款


        $res['attend_address'] = $game_info->shop_name;//
        $res['insure_num_per_order'] = $game_info->insure_num_per_order * $orderInfo->buy_number; //需要购买的保单份数


        $useMarket = $this->_getPlayCodeUsedTable()->get(array('good_info_id' => $game_order->game_info_id));

        //验证商家
        if ($useMarket) {
            $organizer = $this->_getPlayOrganizerTable()->get(array('id' => $useMarket->organizer_id));
            if ($organizer) {
                $res['organizer_name'] = $organizer->name;
            } else {
                $res['organizer_name'] = $orderInfo->shop_name;
            }
        } else {
            $res['organizer_name'] = $orderInfo->shop_name;//商家名称
        }


        //$res['back_money'] = ($gameData->refund_time < $gameData->start_time) ? '不支持退款' : (($gameData->refund_time > $gameData->foot_time) ? '支持随时退款' : date('Y.m.d H:i', $gameData->refund_time) . '前支持退款');

        $res['attend_addr_x'] = $shopData->addr_x; //经度
        $res['attend_addr_y'] = $shopData->addr_y; //纬度


        //联系人相关
        $res['buy_name'] = $orderInfo->buy_name; //联系人
        $res['buy_phone'] = $orderInfo->buy_phone; //联系人电话

        $orderList = array();
        //使用码
        $orderCode = $this->_getPlayCouponCodeTable()->fetchAll(array('order_sn' => $rid));

        foreach ($orderCode as $v) {

            $add_status = 0;
            if ($orderInfo->account_money > 0) { //如果使用账户付款  没有原路返回
                $add_status = 0;
            } else {
                if ($v->status == 2) {//已退款 (退款到了用户账号)
                    $s = $adapter->query("select * from play_order_back_tmp where code_id=?", array($v->id))->current();
                    if (!$s) {
                        $add_status = 0;
                    } else {
                        $add_status = $s->status;
                    }
                }
            }

            $orderList[] = array(
                'zyb_code' => ($zyb_data && $zyb_data->zyb_code) ? $zyb_data->zyb_code : '',
                'code' => $v->id . $v->password, //$v->id . $v->password,
                'status' => $v->status,
                'add_status' => $add_status //0 不显示 1.退款到支付账户(可以点击) 2.正在向支付账户退款 3.已退至支付账户
            );

        }
        $res['order_list'] = $orderList;

        $res['linker_name'] = $orderInfo->buy_name;
        $res['linker_phone'] = $orderInfo->buy_phone;
        $res['linker_addr'] = $orderInfo->buy_address;

        $orderInsure = $this->_getPlayOrderInsureTable();
        $associates_info = $orderInsure->fetchAll(array('order_sn' => $rid, 'insure_status>=1'))->toArray();
        $res['associates'] = $associates_info;
        $city = $orderInfo->order_city;

        //商品不属于分享
        $sql = 'select * from play_cash_share where city = ? and isall = 1 and `type` = 1 limit 1';
        $pcs = $adapter->query($sql, array($city))->count();

        //--商品参与购买红包分享--
        if ($gameData->cash_share > 0 or $pcs) {

            RedCache::del('D:share_cash:1' . $city);
            $options = (object)RedCache::fromCacheData('D:share_cash:1' . $city, function () use ($city) {
                $data = $this->_getPlayCashShareTable()->get(['city' => $city, 'type' => 1]);

                return $data;
            }, 24 * 3600 * 7, true);

            $opt = json_decode($options->options);

            $cv = 0;
            if ($opt) {
                $money = bcadd($orderInfo->real_pay, $orderInfo->account_money, 2);

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
                            }, 24 * 3600, true);
                            $cv += $cashv['price'];
                        }

                        break;
                    }
                }
            } else {
                $cv = 0;
            }

            $res['middleware'] = $cv ? 1 : 0;
            $res['share_title'] = '【玩翻天】' . $options->title;
            $res['share_content'] = '我发现了一个不错的商品，' . $res['money'] . '元，我们一起报名吧。' . str_replace(array(" ", "　", "\t", "\n", "\r"), '', $options->content);
            $res['share_img'] = $this->_getConfig()['url'] . $options->shareicon;
            $res['share_url'] = $this->_getConfig()['url'] . '/web/generalize/winner?sid=' . $orderInfo->order_sn;
            $res['hbimg'] = $this->_getConfig()['url'] . '/images/invite/sharehb.png';
            $res['share_tips'] = '分享现金红包给你的好友你奖获得' . $cv . '元现金券';
            $res['share_type'] = [1, 2];
        } else {
            $res['middleware'] = 0;
        }

        return $this->jsonResponse($res);
    }

    //商品详情 带订单3.3.1
    public function nhaveAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $id = $this->getParams('id');   //商品id
        $rid = $this->getParams('rid'); //订单id
        $gameData = $this->_getPlayOrganizerGameTable()->get(array('id' => $id));
        $orderInfo = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $rid, 'order_status' => 1));
        if (!$gameData || !$orderInfo) {
            return $this->jsonResponseError('该商品 或者订单不存在');
        }

        if ($orderInfo->pay_status < 2) {//未付款 //0未付款;1付款中; 2已付款 3 退款中 4 退款成功 5已使用
            return $this->jsonResponseError('订单未付款');
        }

        $game_order = $this->_getPlayOrderInfoGameTable()->get(array('order_sn' => $rid));
        $game_info = $this->_getPlayGameInfoTable()->get(array('id' => $game_order->game_info_id));
        $game_price = $this->_getPlayGamePriceTable()->get(array('id' => $game_info->pid));

        //$price_info = $this->_getPlayGamePriceTable()->get($game_info->pid);

        $shopData = $this->_getPlayShopTable()->get(array('shop_id' => $game_info->shop_id));

        $adapter = $this->_getAdapter();


        //统计点击次数
        $this->_getPlayOrganizerGameTable()->update(array('click_num' => new Expression('click_num+1')), array('id' => $id));

        // 前面需判断是否智游宝商品
        $zyb_data = $adapter->query("select * from play_zyb_info WHERE order_sn=? AND status >= ?", array($rid, 2))->current();

        if ($zyb_data->zyb_type == 2) {
            return $this->jsonResponseError('服务君出状况了，如有疑问请联系4008007221');
        }

        if ($zyb_data && $zyb_data->play_end_time) {
            $attend_start_time = $zyb_data->play_start_time;
            $attend_end_time = $zyb_data->play_end_time;
        } else {
            $attend_start_time = $game_info->start_time;
            $attend_end_time = $game_info->end_time;
        }

        if ($orderInfo->group_buy_id) {
            $can_back = 0;
        } else {
            if ($game_price->back_rule == 1) {
                $data_refund_time = strtotime(date("Y-m-d 00:00:00", $game_info->start_time)) + $data_game_price->refund_before_day * 86400 + (strtotime(date("H:i:s", $game_price->refund_before_time)) - strtotime(date('Y-m-d', time())));
            } else {
                $data_refund_time = $game_info->refund_time;
            }

            //if ($zyb_data && $zyb_data->play_end_time) {
                $can_back = ($data_refund_time > time()) ? 1 : 0; //是否可以退款 1 可以 0 不可以
            //} else {
            //    $can_back = ($data_refund_time > time()) ? 1 : 0; //是否可以退款 1 可以 0 不可以
            //}
        }
        //商品相关
        $res = array(
            'title' => $gameData->title, //商品名称
            'money' => bcadd($orderInfo->real_pay, $orderInfo->account_money, 2),  // 显示支付金额
            'back' => $can_back
        );

        if ($zyb_data && $zyb_data->play_end_time) {
            $res['tips'] = ($game_info->refund_time > time() && $zyb_data->play_end_time > time()) ? "您想要取消参加{$gameData->title}的订单么？退款经财务审核后，将于3-5个工作日原路返回。我们下次再约！" : "您想要取消参加{$gameData->title}的订单么？这个是不退款的活动，你确定不参加了？";
        } else {
            $res['tips'] = ($game_info->refund_time > time()) ? "您想要取消参加{$gameData->title}的订单么？退款经财务审核后，将于3-5个工作日原路返回。我们下次再约！" : "您想要取消参加{$gameData->title}的订单么？这个是不退款的活动，你确定不参加了？";
        }

        $res['attend_way'] = $gameData->g_set_name ?: $game_info->price_name; //产品名称
        $res['order_id'] = $rid; //订单id
        $res['attend_start_time'] = $attend_start_time; //$game_info->start_time;//出行开始时间
        $res['attend_end_time'] = $attend_end_time; //$game_info->end_time; //出行结束时间
        $res['order_method'] = $gameData->order_method;//使用方法
        $res['ask_phone'] = $gameData->phone ?: '4008007221'; //联系电话
        $res['get_way'] = $game_info->order_method; //兑换方式

        // 退款说明
        if ($game_price->back_rule == 1) {
            $data_time  = date('Y年m月d日', $game_order->start_time + $game_price->refund_before_day * 86400) ;
            $back_money = "在" . $data_time . "的" . date('H:i', $game_price->refund_before_time) . '前支持退款';
        } else {
            $back_money = ($game_info->refund_time < $game_info->up_time) ? '不支持退款' : date('Y.m.d H:i', $game_info->refund_time) . '前支持退款';
        }
        $res['back_money'] = $back_money;
        ///////////////////////////////////////////////////////////////////////////////////////////////////////////

        $res['special_info'] = $game_info->remark; //特别说明
        $res['attend_address'] = $game_info->shop_name;//
        $res['insure_num_per_order'] = $game_info->insure_num_per_order * $orderInfo->buy_number; //需要购买的保单份数
        $res['select_date'] = $gameData->need_use_time == 2 ? 1 : 0; // 是否必须选择使用日期


        $useMarket = $this->_getPlayCodeUsedTable()->get(array('good_info_id' => $game_order->game_info_id));

        //验证商家
        if ($useMarket) {
            $organizer = $this->_getPlayOrganizerTable()->get(array('id' => $useMarket->organizer_id));
            if ($organizer) {
                $res['organizer_name'] = $organizer->name;
            } else {
                $res['organizer_name'] = $orderInfo->shop_name;
            }
        } else {
            $res['organizer_name'] = $orderInfo->shop_name;//商家名称
        }


        //$res['back_money'] = ($gameData->refund_time < $gameData->start_time) ? '不支持退款' : (($gameData->refund_time > $gameData->foot_time) ? '支持随时退款' : date('Y.m.d H:i', $gameData->refund_time) . '前支持退款');

        $res['attend_addr_x'] = $shopData->addr_x; //经度
        $res['attend_addr_y'] = $shopData->addr_y; //纬度


        //联系人相关
        $res['buy_name'] = $orderInfo->buy_name; //联系人
        $res['buy_phone'] = $orderInfo->buy_phone; //联系人电话

        $orderList = array();
        //使用码
        $orderCode = $this->_getPlayCouponCodeTable()->fetchAll(array('order_sn' => $rid));
        $isallback = 1;
        foreach ($orderCode as $v) {

            $add_status = 0;
            if ($orderInfo->account_money > 0) { //如果使用账户付款  没有原路返回
                $add_status = 0;
            } else {
                if ($v->status == 2) {//已退款 (退款到了用户账号)
                    $s = $adapter->query("select * from play_order_back_tmp where code_id=?", array($v->id))->current();
                    if (!$s) {
                        $add_status = 0;
                    } else {
                        $add_status = $s->status;
                    }
                }
            }

            if ($v->status < 2) {
                $isallback = 0;
            }

            $orderList[] = array(
                'zyb_code' => ($zyb_data && $zyb_data->zyb_code) ? $zyb_data->zyb_code : '',
                'code' => $v->id . $v->password, //$v->id . $v->password,
                'status' => $v->status,
                'add_status' => $add_status //0 不显示 1.退款到支付账户(可以点击) 2.正在向支付账户退款 3.已退至支付账户
            );

        }
        $res['order_list'] = $orderList;

        $res['linker_name'] = $orderInfo->buy_name;
        $res['linker_phone'] = $orderInfo->buy_phone;
        $res['linker_addr'] = $orderInfo->buy_address;

        $orderInsure = $this->_getPlayOrderInsureTable();
        $associates_info = $orderInsure->fetchAll(array('order_sn' => $rid, 'insure_status>=1'))->toArray();
        $res['associates'] = $associates_info;
        $city = $orderInfo->order_city;

        //商品不属于分享
        $sql = 'select * from play_cash_share where city = ? and isall = 1 and `type` = 1 limit 1';
        $pcs = $adapter->query($sql, array($city))->count();

        //--商品参与购买红包分享--
        if ($gameData->cash_share > 0 or $pcs) {

            RedCache::del('D:share_cash:1' . $city);
            $options = (object)RedCache::fromCacheData('D:share_cash:1' . $city, function () use ($city) {
                $data = $this->_getPlayCashShareTable()->get(['city' => $city, 'type' => 1]);

                return $data;
            }, 24 * 3600 * 7, true);

            $opt = json_decode($options->options);

            $cv = 0;
            if ($opt) {
                $money = bcadd($orderInfo->real_pay, $orderInfo->account_money, 2);

                foreach ($opt as $o) {
                    $price = $o[0];
                    $pay = explode('-', $price);
                    if ($money >= $pay[0] and $money < $pay[1]) {
                        //分享者获得现金券
                        $share_cc = explode(',', $o[1]);
                        foreach ($share_cc as $sc) {
                            RedCache::del('D:cashv:' . $sc);
                            $cashv = (array)RedCache::fromCacheData('D:cashv:' . $sc, function () use ($sc) {
                                $data = $this->_getCashCouponTable()->get(['id' => $sc,'residue > ?'=>0,'is_close'=>0,'end_time > ?'=>time()]);
                                return $data;
                            }, 24 * 3600, true);
                            $cv += $cashv['price'];
                        }

                        break;
                    }
                }
            } else {
                $cv = 0;
            }

            if($isallback){
                $cv = 0;
            }

            $res['middleware'] = $cv ? 1 : 0;
            $res['share_title'] = $options->title;
            $res['share_content'] = str_replace(array(" ", "　", "\t", "\n", "\r"), '', $options->content);
            $res['share_img'] = $this->_getConfig()['url'] . $options->shareicon;
            $res['share_url'] = $this->_getConfig()['url'] . '/web/generalize/winner?sid=' . $orderInfo->order_sn;
            $res['hbimg'] = $this->_getConfig()['url'] . '/images/invite/sharehb.png';
            $res['share_tips'] = '分享现金红包给你的好友你奖获得' . $cv . '元现金券';
            $res['share_type'] = [1, 2];
        } else {
            $res['middleware'] = 0;
        }

        return $this->jsonResponse($res);
    }

    //团购进行中列表
    public function groupListAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }
        $id = $this->getParams('id', 0);
        $p = (int)$this->getParams('p', 1);
        $pagenum = (int)$this->getParams('pagenum', 10);
        if (!$id) {
            return $this->jsonResponseError('参数错误');
        }
        $res = array();
        $offset = ($p - 1) * $pagenum;
        $g_list = $this->_getPlayGroupBuyTable()->tableGateway->select(function (Select $select) use ($id, $offset, $pagenum) {
            $select->join('play_user', 'play_user.uid=play_group_buy.uid', array('img', 'username'), 'left');
            $select->join('play_order_info', '(play_order_info.group_buy_id=play_group_buy.id and play_group_buy.uid=play_order_info.user_id)', array('pay_status'));
            $select->where(array('play_group_buy.status' => 1, 'play_group_buy.end_time>' . time(), 'play_group_buy.game_id' => $id, 'play_order_info.pay_status' => 7));
            $select->offset($offset)->limit($pagenum);
        });

        foreach ($g_list as $v) {
            $res[] = array(
                'name' => $v->username,  //团长名称
                'img' => $this->getImgUrl($v->img),//团长图片
                'join_number' => $v->join_number,
                'limit_number' => $v->limit_number,
                'g_id' => $v->id,  //团id
                'end_time' => $v->end_time,
            );
        }
        return $this->jsonResponse($res);
    }

    //团购参加成功
    public function groupokAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }
        $group_buy_id = (int)$this->getParams('gid');//团购id
        $uid = (int)$this->getParams('uid');//uid 查询好友

        if (!$group_buy_id or !$uid) {
            return $this->jsonResponseError('参数错误');
        }

        $group_data = $this->_getPlayGroupBuyTable()->get(array('id' => $group_buy_id));

        if (!$group_data) {
            return $this->jsonResponseError('此团不存在或已被删除');
        }
        $game_data = $this->_getPlayOrganizerGameTable()->get(array('id' => $group_data->game_id));
        if (!$game_data) {
            return $this->jsonResponseError('商品不存在或已删除');
        }

        //圈子列表
        $circle = $this->_getMdbSocialCircleUsers()->find(array('uid' => $uid, 'status' => 1))->limit(3);

        $circles = array();
        foreach ($circle as $v) {
            $circles[] = array(
                'cid' => $v['cid'],
                'c_name' => $v['c_name'],
                'img' => $this->getImgUrl($v['img'])
            );
        }

        //好友列表,相关关注

        $friend = $this->_getMdbSocialFriends()->find(array('uid' => $uid, 'friends' => 1))->limit(100);
        $friends = array();
        $uids = array();
        foreach ($friend as $v) {
            $uids[] = $v['like_uid'];
        }


        $users_data = array();
        if (!empty($uids)) {
            $users_data = $this->_getPlayUserTable()->fetchAll(array(new In('uid', $uids)));
        }


        foreach ($users_data as $v) {
            $friends[] = array(
                'uid' => $v->uid,
                'username' => $v->username,
                'user_alias' => $v->user_alias,
                'user_circles' => json_decode($v->user_circles, true),
                'img' => $this->getImgUrl($v->img),

            );
        }

        return $this->jsonResponse(array(
            'title' => $game_data->title,
            'thumb' => $this->_getConfig()['url'] . $game_data->thumb,
            'coupon_id' => $game_data->id,
            'price' => $game_data->g_price,
            'g_limit' => $game_data->g_limit,
            'join_number' => $group_data->join_number,
            'end_time' => $group_data->end_time,
            'message' => "只花{$game_data->g_price}，就可以购买{$game_data->title}，我已报名，就等你了。",
            'circles' => $circles, //圈子列表
            'friends' => $friends,//所有相关关注的好友
        ));


    }

    //我参加的团 状态:组团中,组团成功,组团失败
    public function groupjoininfoAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $group_buy_id = $this->getParams('gid', 0);//团购id
        $order_sn = $this->getParams('order_sn'); //订单号
        $uid = (int)$this->getParams('uid', 0);//uid


        if (!$group_buy_id or !$order_sn or !$uid) {
            return $this->jsonResponseError('请求参数不存在');
        }
        //检查状态
        $group_data = $this->_getPlayGroupBuyTable()->get(array('id' => $group_buy_id));

        if (!$group_buy_id) {
            return $this->jsonResponseError('团购信息不存在');
        }

        $game_data = $this->_getPlayOrganizerGameTable()->get(array('id' => $group_data->game_id));
        $game_info_data = $this->_getPlayGameInfoTable()->get(array('id' => $group_data->game_info_id));
        $order_info = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));
        $shopData = $this->_getPlayShopTable()->get(array('shop_id' => $game_info_data->shop_id));


        $data = array(
            'img' => $this->getImgUrl($game_data->thumb),
            'title' => $game_data->title,
            'price' => $game_info_data->price,
            'new_price' => bcadd($order_info->real_pay, $order_info->account_money, 2),//$game_data->g_price
            'g_limit' => $game_data->g_limit,
            'join_number' => $group_data->join_number,
            'end_time' => $group_data->end_time,
            'cover' => $this->getImgUrl($game_data->cover),
            'order_sn' => $order_sn,
            'type_name' => $game_data->g_set_name ? $game_data->g_set_name : $game_info_data->price_name, //产品名称,
            'time' => date('Y-m-d H:i', $game_info_data->start_time) . '~' . date('Y-m-d H:i', $game_info_data->end_time),
            'address' => $game_info_data->shop_name,  //出行地址
            'shop_name' => $game_info_data->shop_name,  //商家名称
            'order_method' => $game_data->order_method,//兑换方式/使用方式
            //退款说明
            'back_money' => '已组团成功的团购商品，不支持退款',
            'phone' => $game_data->phone ? $game_data->phone : '4008007221', //联系电话
            'status' => 0,
            'code' => '',
            'pay_status' => $order_info->pay_status,
            'addr_x' => $shopData->addr_x,
            'addr_y' => $shopData->addr_y,
            'use_time' => date('Y-m-d H:00', $game_info_data->start_time) . '~' . date('Y-m-d H:00', $game_info_data->end_time),
            'use_time_txt' => $game_info_data->end_time,//使用时间
            'buy_name' => $order_info->buy_name,
            'buy_phone' => $order_info->buy_phone,
            'reward' => array(), //奖励的卡券
            'insure_num_per_order' => $game_info_data->insure_num_per_order * $order_info->buy_number //需要购买的保单份数
        );

        $useMarket = $this->_getPlayCodeUsedTable()->get(array('good_info_id' => $group_data->game_info_id));
        //验证商家
        if ($useMarket) {
            $organizer = $this->_getPlayOrganizerTable()->get(array('id' => $useMarket->organizer_id));
            if ($organizer) {
                $data['shop_name'] = $organizer->name;
            }
        }

        //参加的用户列表
        $res = $this->_getPlayOrderInfoTable()->groupBuyImgList($group_buy_id);
        $list = array();
        foreach ($res as $v) {

            $friend = $this->_getMdbsocialFriends()->findOne(array('uid' => $uid, 'friends' => 1, 'like_uid' => $v->user_id), array('like_uid'));
            $list[] = array('username' => $v->username, 'userimg' => $this->getImgUrl($v->img), 'uid' => $v->user_id, 'is_friend' => $friend ? 1 : 0);
        }
        $data['join_user_list'] = $list;

        if ($group_data->status == 0) {
            //已解散
            $data['status'] = 0;
        }

        if ($group_data->status == 1) {
            //等待加入
            $data['status'] = 1;
        }


        // 验证码
        $orderCode = $this->_getPlayCouponCodeTable()->get(array('order_sn' => $order_sn));

        /////////////////// 临时退回账户 ///////////////////////
        $db = $this->_getAdapter();
        $add_status = 0;
        if ($order_info->account_money > 0) { //如果使用账户付款  没有原路返回
            $add_status = 0;
        } else {
            if ($orderCode->status == 2) {//已退款 (退款到了用户账号)
                $s = $db->query("select * from play_order_back_tmp where code_id=?", array($orderCode->id))->current();
                if (!$s) {
                    $add_status = 0;
                } else {
                    $add_status = $s->status;
                }
            }
        }
        $data['add_status'] = $add_status; //0 不显示 1.退款到支付账户(可以点击) 2.正在向支付账户退款 3.已退至支付账户
        ////////////////////  临时退回账户  //////////////////////

        $data['code'] = $orderCode->id . $orderCode->password;
        $data['use_status'] = $orderCode->status; //0未使用,1已使用,2已退款,3退款中


        if ($group_data->status == 2) {
            //已完成
            $data['status'] = 2;
            //团长
            if ($group_data->uid == $uid) {

                $res = $this->_getPlayWelfareTable()->tableGateway->select(function ($select) use ($group_data) {
                    $select->join('play_cash_coupon', 'play_cash_coupon.id=play_welfare.welfare_link_id', array('title'));
                    $select->where(array('object_type' => 3, 'object_id' => $group_data->game_id, 'good_info_id' => $group_data->game_info_id));
                    $select->limit(1);
                })->current();
                if ($res) {
                    $data['reward'][] = array(
                        'title' => '已获得团长奖励 ' . $res->title,
                        'id' => $res->welfare_link_id
                    );
                }
            }
        }
        $urlType = ($data['status'] + 1);
        $data['rule_url'] = $this->_getConfig()['url'] . '/web/organizer/rule?uid=' . $uid . '&gid=' . $group_buy_id . '&type=' . $urlType;


        $data['linker_name'] = $order_info->buy_name;
        $data['linker_phone'] = $order_info->buy_phone;
        $data['linker_addr'] = $order_info->buy_address;

        $orderInsure = $this->_getPlayOrderInsureTable();
        $associates_info = $orderInsure->fetchAll(array('order_sn' => $order_sn, 'insure_status>=1'))->toArray();
        $data['associates'] = $associates_info;

        return $this->jsonResponse($data);

    }


    //支付详情页
    public function grouppayinfoAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = $this->getParams('uid');
        $gid = (int)$this->getParams('gid', 0); //组团号
        $group_data = $this->_getPlayGroupBuyTable()->get(array('id' => $gid));
        $gameData = $this->_getPlayOrganizerGameTable()->get(array('id' => $group_data->game_id, 'status > ?' => 0));

        if (!$gameData or !$group_data) {
            return $this->jsonResponseError('商品或数据不存在');
        }
        $game_info_data = $this->_getPlayGameInfoTable()->get(array('id' => $group_data->game_info_id));
        $shopData = $this->_getPlayShopTable()->get(array('shop_id' => $game_info_data->shop_id));


        //加入人数
        // $res['join_number'] = $group_data->join_number;
        //结束时间
        // $res['g_end_time'] = $group_data->end_time;

        // $res['g_info_id'] = $game_info_data['id'];
        //参与方式
        $res['way'] = $game_info_data['price_name'];
        //有效期
        $res['overdue_time'] = date('Y-m-d H:i', $game_info_data['start_time']) . '~' . date('Y-m-d H:i', $game_info_data['end_time']);
        //地点
        $res['address'] = $game_info_data['shop_name'];
        //兑换方式
        $res['order_method'] = $gameData->order_method;
        //退款说明
        $res['back_money'] = "已组团成功的团购商品，不支持退款";
        //咨询/预约电话
        $res['phone'] = $shopData->shop_phone;

        $res['addr_x'] = $shopData->addr_x;

        $res['addr_y'] = $shopData->addr_y;

        $account = new Account();

        $user_money = $account->getUserMoney($uid);


        $res['user_money'] = (string)$user_money;


        return $this->jsonResponse($res);
    }

    //商家
    public function shopAction()
    {

        if (!$this->pass()) {
            return $this->failRequest();
        }

        $gid = $this->getParams('gid');
        $page = (int)$this->getParams('page', 1);
        $limit = (int)$this->getParams('page_num', 5);
        $page = ($page > 1) ? $page : 1;
        $start = ($page - 1) * $limit;

        $res = array();
        $shop_data = $this->_getPlayGameInfoTable()->getApiGameShopList($start, $limit, array(), array('play_game_info.gid' => $gid, 'play_game_info.status >= ?' => 1));

        foreach ($shop_data as $sData) {
            $res[] = array(
                'shop_name' => $sData->shop_name,
                'shop_id' => $sData->shop_id,
                'circle' => $sData->circle,
                'addr_x' => $sData->addr_x,
                'addr_y' => $sData->addr_y,
            );
        }

        return $this->jsonResponse($res);

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

    private function query($sql)
    {
        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $stmt = $db->query($sql);
        $result = $stmt->execute($stmt);
        return $result;
    }

    /**
     * @param $shop_id |jsonString
     */
    public function countShopSale($shop_id)
    {
        foreach ($shop_id as $v) {
            if (!RedCache::get('shop_sale' . $v)) {
                //统计最近七日
                $start_time = time() - (86400 * 7);
                $hot_count = $this->_getPlayOrderInfoTable()->fetchCount(array('order_status' => 1, 'pay_status>=2', 'dateline>' . $start_time));
                $this->_getPlayShopTable()->update(array('hot_count' => $hot_count), array('shop_id' => $v));
                RedCache::set('shop_sale' . $v, 1, 86400);
            }
        }

    }

    private function cleanGroup($order_sn)
    {

        $order_data = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));

        if (!$order_data) {
            return false;
        }

        if ($order_data->pay_status != 0 or $order_data->order_status == 0) {
            //已支付或其他状态
            return false;
        }
        if ($order_data->group_buy_id == 0) {
            //非团
            return false;
        }

        //团订单

        $group_data = $this->_getPlayGroupBuyTable()->get(array('id' => $order_data->group_buy_id));

        $this->_getPlayOrderInfoTable()->update(array('order_status' => 0), array('order_sn' => $order_sn));

        if ($group_data->uid == $order_data->user_id) {

            $this->_getPlayGameInfoTable()->update(array('buy' => new Expression('buy-' . $group_data->limit_number)), array('id' => $group_data->game_info_id));
            $this->_getPlayOrganizerGameTable()->update(array('buy_num' => new Expression('buy_num-' . $group_data->limit_number)), array('id' => $order_data->coupon_id));

            return true;
        } else {
            //由主订单决定  一定存在
            return false;
        }
    }

}
