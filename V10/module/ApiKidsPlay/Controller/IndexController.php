<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ApiKidsPlay\Controller;

use Deyi\Account\Account;
use Deyi\BaseController;
use Deyi\GetCacheData\CouponCache;
use Deyi\GetCacheData\ExcerciseCache;
use Deyi\GetCacheData\Labels;
use Deyi\GetCacheData\UserCache;
use Deyi\KidsPlay\KidsPlay;
use library\Fun\M;
use library\Service\System\Cache\RedCache;

use Deyi\Seller\Seller;
use library\Service\User\Member;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Response;
use Deyi\JsonResponse;
use Zend\Db\Sql\Expression;


class IndexController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    // 遛娃活动列表
    public function listAction()
    {

        if (!$this->pass(false)) {
            return $this->failRequest();
        }

        $sort = $this->getParams('sort', 1); //1(hot),2(new),3(history)
        $page = $this->getParams('page', 1);
        $page_num = $this->getParams('page_num', 10);

        if (!$sort) {
            $sort = 1;
        }

        $city = $this->getCity();

        $offset = ($page - 1) * $page_num;

        //热门 就按照活动场次开始时间距实际时间降序加上缺口人数降序，双重规则 ,显示 开始到结束的时间

        $res = $this->_getPlayExcerciseBaseTable()->getList($offset, $page_num, $sort, $city);

        $data = array();

        foreach ($res as $v) {
            $circle = ExcerciseCache::getCircleByBid($v['bbid']);
            $session = $this->_getPlayExcerciseEventTable()->getSession($v['bbid']);
            $p = 0;
            foreach ($session as $vv) {
                if ($vv['low_price'] < $p or $p == 0) {
                    $p = $vv['low_price'];
                }
            }

            $tags = Labels::getLabels($v['special_labels']);
            $data_tags = array();
            if (empty($v['custom_tags'])) {
                $data_tags = array();
            } else {
                $data_tags = explode(',', $v['custom_tags']);
            }

            $data[] = array(
                "id" => $v['bbid'],  //活动id  不是场次id
                "title" => $v['name'],
                "low_money" => $p,
                "image" => $this->getImgUrl($v['cover']),
                "circle" => CouponCache::getBusniessCircle($circle),
                "joined_num" => (int)($v['join_number'] + $v['vir_number']), //已有多少人报名
                "session_num" => (int)$this->_getPlayExcerciseEventTable()->session($v['bbid']),
                "tags" => $data_tags,
                "n_tags" => $tags,
                "date" => date('m月d日', $v['max_start_time']) . '-' . date('m月d日', $v['min_end_time']),  //开始到结束的时间
                "vip_free" => (int)$v['free_coupon_event_count'],
            );
        }
        return $this->jsonResponse($data);
    }


    // 遛娃活动详情
    public function detailAction()
    {

        //todo 添加缓存
        if (!$this->pass(false)) {
            return $this->failRequest();
        }

        $id = (int)$this->getParams('id');  //活动id
        $uid = (int)$this->getParams('uid');

        //半天更新一次活动相关数据
        RedCache::fromCacheData('D:kidsplay_update_base:' . $id, function () use ($id) {
            return (int)KidsPlay::updateMax($id);
        }, 21600);
        $base_data = $this->_getPlayExcerciseBaseTable()->get(array('id' => $id));


        $schedule = $this->_getPlayExcerciseScheduleTable()->fetchAll(array('bid' => $id, 'is_close' => 0), array('schedule_name' => 'asc', 'start_time' => 'asc'), 100)->toArray();

        //$price = $this->_getPlayExcercisePriceTable()->fetchAll(array('bid' => $id), array('price' => 'desc'))->toArray();
        $price = $this->_getPlayExcerciseEventTable()->getSession($id);
        $shops = $this->_getPlayExcerciseShopTable()->getExcerciseShop($id);
        $customize = $this->_getPlayExcerciseEventTable()->fetchAll(array('id' => $id, 'customize' => 1))->toArray();//查询定制的团

        $this->_getPlayExcerciseBaseTable()->update(array('view_number' => new Expression('view_number+1')), array('id' => $id));

        $schedule_list = array();
        $min_time = 0;
        $max_time = 0;
        foreach ($schedule as $v) {
            if ($v['start_time'] < $min_time or $min_time == 0) {
                $min_time = $v['start_time'];
            }
            if ($v['end_time'] > $max_time) {
                $max_time = $v['end_time'];
            }
            $schedule_list[] = array(
                "day" => (int)$v['schedule_name'],
                "dateline" => (int)$v['start_time'],
                "end_dateline" => (int)$v['end_time'],
                "content" => $v['schedule'],
            );
        }
        if ($uid) {
            $buy_log = (int)$this->_getPlayOrderInfoTable()->get(array('user_id' => $uid, 'bid' => $id, 'order_status' => 1, 'pay_status' => 5, 'order_type' => 3));
        } else {
            $buy_log = 0;
        }
        $cus = (int)count($customize);
        $cus_ing = 0;
        foreach ($customize as $v) {
            if ($v['sell_status'] == 1 and $v['total_number'] >= $v['join_number']) {
                $cus_ing += 1;
            }
        }

        if (!$base_data) {
            return $this->jsonResponseError("活动不存在!");
        }

        $place_list = array();
        $places = [];
        foreach ($shops as $v) {
            if (in_array($v->shop_id, $places)) {
                continue;
            }
            $places[] = $v->shop_id;
            $place_list[] = array(
                "id" => $v->shop_id,
                "name" => $v->shop_name,
                "desc" => $v->shop_address,
                "addr_x" => $v->addr_x,
                "addr_y" => $v->addr_y
            );
        }

        $orders_array = $this->_getPlayOrderInfoTable()->getExcerciseMembers($id);

        //第一个为团长
        $members = array();
        foreach ($orders_array as $v) {
            if ($uid) {
                $friend = (int)$this->_getMdbsocialFriends()->findOne(array('uid' => $uid, 'friends' => 1, 'like_uid' => $v->user_id), array('like_uid'));
            } else {
                $friend = 0;
            }
            $members[] = array(
                "image" => $this->getImgUrl($v->img),
                "is_friend" => $friend
            );
        }

        //虚拟用户
        $uc = new UserCache();
        $vir_numbers = $uc->getVirUser($id, $base_data->vir_number);

        if (count($vir_numbers)) {
            foreach ($vir_numbers as $vs) {
                $members[] = array(
                    "image" => $this->getImgUrl($vs['img']),
                    "is_friend" => 0
                );
            }
        }

        //我参加过的历史活动场次id
        $history_eid = 0;
        if ($uid) {
            $h = $this->_getPlayOrderInfoTable()->fetchLimit(0, 1, array(), array('user_id' => $uid, 'bid' => $id, 'pay_status >=2', 'order_type' => 3), array('dateline' => 'desc'))->current();
            $history_eid = (int)$h->coupon_id;
        }

        $p = 0;
        foreach ($price as $v) {
            if ($v['low_price'] < $p or $p == 0) {
                $p = $v['low_price'];
            }
        }


        $where = array(
            'msg_type' => 7,
            'object_data.object_bid' => (int)$id,
            'status' => array('$gt' => 0)
        );
        $post_data = $this->_getMdbSocialCircleMsg()->find($where);
        $post_number = $post_data->count();

        $age_for = $base_data->end_age == 100 ? "适合{$base_data->start_age}岁及以上" : "适合{$base_data->start_age}-{$base_data->end_age}岁";

        //售卖状态  1：参加、2：即将开始、3：停止

        $s = $this->getSessions($id);
        $one_session = -1;
        $btn_1 = 0;
        $btn_2 = 0;
        $btn_3 = 0;

        foreach ($s as $v1) {
            if ($v1['status'] == 1) {
                if ($one_session == -1) {
                    $one_session = $v1['id'];
                } else {
                    $one_session = 0;
                }
            }
            if ($v1['status'] == 1) {
                $btn_1 = 1;
            }
            if ($v1['status'] == 3) {
                $btn_2 = 2;
            }
            if ($v1['status'] == 0 or $v1['status'] == 2) {
                $btn_3 = 3;
            }
        }

        $is_collect = 0;
        //是否收藏
        if ($uid) {

            $flag = M::getPlayUserCollectTable()->getCollect($uid,'kidsplay',$id);
            if ($flag) {
                $is_collect = 1;
            }
        }


        //最近场次已报名人数
        $db = $this->_getAdapter();
        $recently_res = $db->query("select * from play_excercise_event as e WHERE
e.bid=?
AND e.sell_status>=1
AND e.sell_status!=3
AND e.open_time < UNIX_TIMESTAMP()
AND e.over_time>UNIX_TIMESTAMP()
AND e.customize = 0
AND e.join_number<e.perfect_number ORDER BY e.start_time ASC limit 1", array($id))->current();

        $Seller = new Seller();
        $is_right = $Seller->isRight($uid);
        $is_sell = $Seller->judge($id, 'activity');
        if ($is_right && $is_sell) {
            $Url = "http://play.wanfantian.com/play/playActivity?id={$id}&seller_id={$uid}";
        } else {
            $Url = "http://play.wanfantian.com/play/playActivity?id={$id}";
        }

        $re_data = array(
            "id" => $id,
            "cover" => $this->getImgUrl($base_data->cover),
            "player_level" => $this->_getConfig()['url'] . '/images/star_level/' . $base_data->teacher_type . '.png',
            "title" => $base_data->name,
            "desc" => $base_data->introduction,
            "date" => date('Y年m月d日', $base_data->max_start_time) . '-' . date('Y年m月d日', $base_data->min_end_time),
            "time" => date('H:i', $min_time) . '-' . date('H:i', $max_time),//"15:00—17:00",
            "price" => $p,
            "session" => $base_data->all_number,
            "man_num" => (int)$base_data->join_ault + (int)$base_data->vir_ault,      // 累计报名成人数
            "kids_num" => (int)$base_data->join_child + (int)$base_data->vir_child,   // 累计报名儿童数
            "recently_num" => ((int)$recently_res->join_ault + (int)$recently_res->join_child + (int)$recently_res->vir_ault + (int)$recently_res->vir_child),  //最近场次报名数
            "recently_man_num" => ((int)$recently_res->join_ault + (int)$recently_res->vir_ault),     //最近场次报名成人数
            "recently_kids_num" => ((int)$recently_res->join_child + (int)$recently_res->vir_child),  //最近场次报名数
            "members" => $members ?: [],  // 已参团人数
            "age_for" => $age_for,
            "gather_method" => $base_data->meeting_desc,
            "attention" => $base_data->attention,  //注意事项
            "phone" => $this->getPhone($base_data->phone),
            "place_num" => count($place_list),
            "place_list" => $place_list,
            "custom" => isset($customize[0]) ? 1 : 0,
            "custom_value" => "已定制团{$cus}个， 定制中团{$cus_ing}个",
            "post_num" => $post_number,//$base_data->comment_number,
            "consult_num" => $base_data->query_number,
            "buy_log" => $buy_log,  //是否购买过
            "share_title" => '【玩翻天】' . $base_data->name,
            "share_url" => $Url,
            "share_img" => $this->getImgUrl($base_data->thumb),
            "share_content" => '我发现了一个不错的活动，' . $p . '元起，我们一起报名吧。' . str_replace(array(" ", "　", "\t", "\n", "\r"), '', $base_data->introduction),//"小玩说"
            "information" => $this->_getConfig()['url'] . '/web/organizer/info?type=5&eid=' . $id,  // 为json
            "itinerary" => $schedule_list,
            "history" => $history = $this->gethistoryData($id, $uid, $this->getCity()),
            "my_history_eid" => $history_eid,
            "one_session" => $one_session >= 0 ? $one_session : 0,  //如果只有一个场次返回对应场次
            "btn_status" => $btn_1 ?: $btn_2 ?: $btn_3 ?: 3,
            "is_collect" => $is_collect,
            "vip_free" => $base_data->free_coupon_event_count,
            "labels" => array('挖花生'), //todo 3.3.4 add
        );

        return $this->jsonResponse($re_data);
    }

    // 往期回顾 下拉分页
    public function historyAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }
        $id = (int)$this->getParams('id'); //活动id
        $uid = (int)$this->getParams('uid', 0); //活动id
        $last_id = $this->getParams('last_id', 0);
        $page_num = $this->getParams('page_num', 10);
        $history = $this->gethistoryData($id, $uid, $this->getCity(), $last_id, $page_num);
        return $this->jsonResponse($history);

    }


    // 获取场次
    public function sessionAction()
    {
        if (!$this->pass(false)) {
            return $this->failRequest();
        }

        $id = $this->getParams('id', 0);  //活动id
        return $this->jsonResponse($this->getSessions($id));

    }

    public function getSessions($bid)
    {
        $service_kidsplay = new \library\Service\Kidsplay\Kidsplay();
        $res              = $service_kidsplay->getSession($bid);

        //售卖状态 0停止售卖 1正常售卖 2已满员 3未到开始售卖时间
        $data = array();
        foreach ($res as $v) {
            if (!$v->eid) {
                continue;
            }
            $status = 1;
            if ($v->sell_status < 1) {
                $status = 0;
            }
            if ($v->open_time > time()) {
                $status = 3;
            }

            if ($v->over_time < time()) {
                $status = 0;
            }

            if ($v->join_number >= $v->perfect_number) {
                $status = 2;
            }
            $data[] = array(
                "id"          => $v->eid,
                "name"        => $v->shop_name,
                "datetime"    => date("Y年m月d日", $v->start_time) . '至' . date('m月d日', $v->end_time) . ' ' . date("H:i", $v->start_time),
                "status"      => $status,
                'low_price'   => (float)$v->low_price,
                'join_number' => (int)($v->join_number + $v->vir_number),
                'man_num'     => (int)($v->join_ault + $v->vir_ault),
                'kids_num'    => (int)($v->join_child + $v->vir_child),
                'vip_free'    => (int)$v->vip_free > 0 ? 1 : 0,
            );
        }
        return $data;
    }

    // 选择报名人数
    public function infoAction()
    {
        if (!$this->pass(false)) {
            return $this->failRequest();
        }

        $uid = $this->getParams('uid');

        $db = $this->_getAdapter();

        $session_id = $this->getParams('session_id'); //场次id

        $data = $this->_getPlayExcerciseEventTable()->get(array('id' => $session_id));

        $base_data = $this->_getPlayExcerciseBaseTable()->get(array('id' => $data->bid));
        $shop_data = $this->_getPlayShopTable()->get(array('shop_id' => $data->shop_id));

        $phone_data = $this->_getPlayUserLinkerTable()->get(array('user_id' => $uid, 'is_default' => 1));

        $service_member = new Member();
        $data_member    = $service_member->getMemberData($uid);

        $service_kidsplay = new \library\Service\Kidsplay\Kidsplay();
        if ($data_member) {
            $data_my_free_coupon_number = $data_member['member_free_coupon_count_now'];
        } else {
            $data_my_free_coupon_number = 0;
        }

        //联系人
        if ($phone_data) {
            $contacts[] = array(
                'id' => $phone_data->linker_id,
                'name' => $phone_data->linker_name,
                'phone' => $phone_data->linker_phone,
                'post_code' => $phone_data->linker_post_code,   //邮编
                'province' => $phone_data->province, //省份
                'city' => $phone_data->city, //城市
                'region' => $phone_data->region,     //地区
                'address' => $phone_data->linker_addr,     //地址
            );
        } else {
            $uid_info = $this->_getPlayUserTable()->get(array('uid' => $uid));
            $contacts[] = array(
                'id' => 0,
                'name' => $uid_info->username,
                'phone' => $uid_info->phone
            );
        }

        //查询收费项
        $prices = $this->_getPlayExcercisePriceTable()->fetchAll(array('eid' => $session_id, 'is_close' => 0));
        $member = array();
        $other = array();
        $all_least = 0;
        //最大剩余数量
        $surplus = $data->most_number - $data->join_number;
        foreach ($prices as $v) {
            if ($v->is_other == 0) {
                $my_buy = 0; //用户购买的数量
                if ($v->most and $uid) {
                    $my_buy = $service_kidsplay->getCountPriceBuy($uid, $v['id']);
                }

                $my_buy_use_free_coupon = 0;
                $my_buy_use_free_coupon_day = 0;
                if ($uid) {
                    $my_buy_use_free_coupon     = $service_kidsplay->getCountPriceFreeBuy($uid, $v['id']);
                    $my_buy_use_free_coupon_day = $service_kidsplay->getCountPriceDayFreeBuy($uid, strtotime(date("Y-m-d", $data->start_time)), (strtotime(date("Y-m-d", $data->start_time)) + 86399));
                }

                $member[] = array(
                    "id" => (string)$v->id,
                    "title" => $v->price_name,
                    "joined_num" => (int)$v->buy_number,
                    "residue_num" => ($v->least - $v->buy_number) < 0 ? ($v->least - $v->buy_number) : 0,
                    "price" => $v->price,
                    "max_buy" => $v->most == 0 ? 999 : $v->most,   //最多购买数量  8/31 ios bug
                    "min_buy" => $v->least, //最少购买数量
                    "people_number" => $v->person ?: 1, //每张票对应的出行人数 最少为1
                    "my_buy" => $my_buy,    //用户已购买的数量
                    "free_number"      => $v->free_coupon_max_count,
                    "free_used_number" => $v->free_coupon_join_count,
                    "need_free_coupon_number" => $v->free_coupon_need_count,
                    "most_free_buy_number" => 1,
                    "my_free_buy_number"   => $my_buy_use_free_coupon,
                    "my_free_buy_same_day_number" => $my_buy_use_free_coupon_day,
                );
                $all_least += ($v->least * $v->person);
            } else {
                $other[] = array(

                    "id" => (string)$v->id,
                    "title" => $v->price_name,
                    "min_num" => 0,
                    "max_num" => 0,
                    "price" => $v->price
                );
            }

        }
        foreach ($member as $k => $v) {
            if ($all_least > $surplus) {
                $member[$k]['min_buy'] = 0;
            }
        }

        //集合方式 //todo 3.3.1 正式上线后使用下面的  wwjie
        $meeting = $this->_getPlayExcerciseMeetingTable()->fetchLimit(0, 100, array('id', 'meeting_place', 'meeting_time'), array('eid' => $session_id, 'is_close' => 0))->toArray();

//        $meeting = $this->_getPlayExcerciseMeetingTable()->fetchLimit(0, 100, array('id', 'meeting_place', 'meeting_time'), array('eid' => $session_id, 'is_close' => 0,"meeting_place!='游玩地点'"))->toArray();

        $account = new Account();
        $res = [
            'title' => $base_data->name,
            'start_time' => (int)$data->start_time,
            'end_time' => (int)$data->end_time,
            'play_address' => $shop_data->shop_name,
            'address' => $contacts,
            'members' => $member,
            'other' => $other,
            'save_number' => $data->full_price,
            'save_money' => $data->less_price,
            'welfare_type' => $data->welfare_type,
            'disclaimer' => "https://wan.wanfantian.com/web/h5/disclaimer", //todo 修改url
            'user_money' => (string)$account->getUserMoney($uid), //余额
            'meeting_desc' => $data->meeting_desc, //集合说明
            'meeting' => $meeting, //集合方式
            'join_number' => (int)$data->join_ault + (int)$data->join_child, //已参加名额
            'least_number' => (int)$data->least_number,  //最少数量
            'perfect_number' => (int)$data->perfect_number, //完美数量
            'most_number' => $data->most_number, //最多数量
            'most_free_buy_same_day_number' => 1,
            'my_free_coupon_number' => $data_my_free_coupon_number,
        ];

        return $this->jsonResponse($res);
    }

    //获取往期回顾数据
    public function gethistoryData($id, $uid, $city = 'WH', $last_id = 0, $page_num = 10)
    {
        $history_lsit = array();
        $where = array('play_excercise_event.bid' => $id, 'play_excercise_event.city' => $city);
        if ($last_id) {
            $where[] = "play_excercise_event.id<($last_id)";
        }
        $where[] = "end_time<UNIX_TIMESTAMP()";
        $where[] = "((join_number>0 and sell_status=3) or sell_status IN(0,1,2))"; //不是结束场次
        $history = $this->_getPlayExcerciseEventTable()->fetchAll($where, array('start_time' => 'desc'), $page_num);
        $title = $this->_getPlayExcerciseBaseTable()->get(array('id' => $id))->name;

        $order = $this->_getPlayOrderInfoTable()->fetchAll(['user_id' => $uid, 'bid' => $id, 'pay_status' => 5], [], $page_num)->toArray();

        $hasbuy = [];
        foreach ($order as $o) {
            $hasbuy[$o['eid']] = 1;
        }

        foreach ($history as $v) {

            $shop2 = $this->_getPlayShopTable()->get(array('shop_id' => $v->shop_id));
            $where = array(
                'msg_type' => 7,
                'object_data.object_id' => (int)$v->id,
                'status' => array('$gt' => 0)
            );
            $post_data = $this->_getMdbSocialCircleMsg()->find($where)->sort(array('status' => -1, 'like_number' => -1, '_id' => -1))->limit(10);

            $post_list = $images = array();
            $num = 0;
            foreach ($post_data as $p) {
                foreach ($p['msg'] as $m) {
                    if ($m['t'] == 2) {
                        $num++;
                        if (count($images) <= 6) {
                            $images[] = $m['val'];
                        }
                    }
                }

                $post_list[] = array(
                    'id' => (string)$p['_id'],
                    'uid' => $p['uid'],
                    'author' => $this->hidePhoneNumber($p['username']),
                    'author_img' => $this->getImgUrl($p['img']),
                    'dateline' => $p['dateline'],
                    'message' => $p['msg'],
                );
            }


            //是否可以评论

            $history_lsit[] = array(
                "history_eid" => $v->id,
                "buy_log" => $hasbuy[$v->id] ?: 0,
                "dateline" => $v->start_time,
                "title" => $title . '第' . $v->no . '期',
                "num" => $num,
                "images" => $images,
                "place" => [
                    "id" => (string)$shop2->shop_id,
                    "name" => $shop2->shop_name,
                    "desc" => $shop2->shop_address,
                    "addr_x" => $shop2->addr_x,
                    "addr_y" => $shop2->addr_y
                ],
                "post_num" => $v->comment_number,
                "post_list" => $post_list,
                // "if_comment" => $if_comment,
            );

        }

        return $history_lsit;
    }


}
