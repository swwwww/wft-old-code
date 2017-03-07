<?php

namespace Cmd\Controller;

use Deyi\BaseController;
use Deyi\GetCacheData\CouponCache;
use Deyi\JsonResponse;
use library\Fun\M;
use Zend\Mvc\Controller\AbstractActionController;


class StatController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;


    /**
     * 商品的访问统计
     * @param $mongoDB
     * @param int $start_time //不为0时设置具体测试时间
     */
    private function tjgoods($mongoDB, $start_time = 0)
    {

        $log_data = $mongoDB->log_data;

        if (!$start_time) {
            $start_time = strtotime(date('Y-m-d')) - 3600 * 24;
        }

        $end_time = strtotime(date('Y-m-d'));

        $goods_log = $mongoDB->good_data;
        $goods_log->remove(array('dateline' => array('$gte' => $end_time)));

        //ios android 统计到安卓和ios不同商品当天的访问数
        $keys = array('decode_p.id' => 1);
        $initial = array('ios' => 0, 'android' => 0, 'weixin' => 0);
        $reduce = "function(obj,prev) {
            if(obj.client=='ios'){prev.ios++;};
            if(obj.client=='android'){prev.android++;};
         }";

        $conditions = array(
            'dateline' => array('$gte' => $start_time, '$lt' => $end_time),
            '$or' => array(
                array('url' => '/good/index'),
                array('url' => '/good/index/index'),
                array('url' => '/good/index/nindex')
            )
        );
        $grouped = $log_data->group($keys, $initial, $reduce, array('condition' => $conditions));
        $good_retval = $grouped['retval'];
        $good_arr = [];
        foreach ($good_retval as $gr) {

            $id = (int)$gr['decode_p.id'];
            if (!$id) {
                continue;
            }
            $good_arr[$id]['ios'] = (int)$gr['ios'];
            $good_arr[$id]['android'] = (int)$gr['android'];
            $good_arr[$id]['weixin'] = (int)$gr['weixin'];
        }


        //微信的商品详情请求接口
        $keys = array('post_array.id' => 1);
        $initial = array('ios' => 0, 'android' => 0, 'weixin' => 0);
        $reduce = "function(obj,prev) {
            if(obj.client != 'android' && obj.client != 'ios'){prev.weixin++;};
         }";

        $conditions = array(
            'dateline' => array('$gte' => $start_time, '$lt' => $end_time),
            '$or' => array(
                array('url' => '/good/index'),
                array('url' => '/good/index/index'),
                array('url' => '/good/index/nindex')
            )
        );
        $grouped = $log_data->group($keys, $initial, $reduce, array('condition' => $conditions));
        $wx_retval = $grouped['retval'];

        foreach ($wx_retval as $gr) {
            $id = (int)$gr['post_array.id'];
            if (!$id) {
                continue;
            }
            if (array_key_exists($id, $good_arr)) {
                $good_arr[$id]['ios'] += (int)$gr['ios'];
                $good_arr[$id]['android'] += (int)$gr['android'];
                $good_arr[$id]['weixin'] += (int)$gr['weixin'];
            } else {
                $good_arr[$id]['ios'] = (int)$gr['ios'];
                $good_arr[$id]['android'] = (int)$gr['android'];
                $good_arr[$id]['weixin'] = (int)$gr['weixin'];
            }
        }

        //当天的所有订单
        $order_sn = $this->_getPlayOrderInfoTable()->fetchLimit(0, 10000, ['order_sn', 'coupon_id'], [
            'order_type' => 2,
            'order_status' => 1,
            'pay_status > ?' => 1,
            'dateline >= ?' => $start_time,
            'dateline < ?' => $end_time
        ], ['dateline' => 'desc'])->toArray();

        $all_sn = [];
        foreach ($order_sn as $v) {
            $all_sn[] = (int)$v['order_sn'];
        }

        $where = array(
            'dateline' => array('$gte' => $start_time, '$lt' => $end_time),
            '$or' => array(
                array('url' => '/good/index/have'),
                array('url' => '/good/index/nhave')
            )
        );

        $order_retval = $log_data->find($where,
            [
                'client' => 1,
                'dateline' => 1,
                'decode_p.rid' => 1,
                'post_array.rid' => 1,
                'cookie.g_id' => 1
            ])->sort(array('dateline' => -1));

        $all_retval = [];//所有下单sn
        foreach ($order_retval as $vv) {
            if (in_array((int)$vv['decode_p']['rid'], $all_sn)) {
                if (!array_key_exists((int)$vv['decode_p']['rid'],
                        $all_retval) or $all_retval[(int)$vv['decode_p']['rid']]['dateline'] > $vv['dateline']
                ) {
                    $all_retval[$vv['decode_p']['rid']] = $vv;
                }
            } else {
                if (in_array((int)$vv['post_array']['rid'], $all_sn)) {
                    if (!array_key_exists((int)$vv['post_array']['rid'],
                            $all_retval) or $all_retval[(int)$vv['post_array']['rid']]['dateline'] > $vv['dateline']
                    ) {
                        $all_retval[$vv['post_array']['rid']] = $vv;
                    }
                }
            }
        }

        foreach ($good_arr as $k => $gr) {
            $coupon_data = $this->_getPlayOrganizerGameTable()->fetchLimit(0, 1, ['title', 'city'],
                array('id' => (int)$k))->current();
            if (!$coupon_data or !$coupon_data->title) {
                continue;
            }
            $id = (int)$k;
            $order_sn_arr = [];
            foreach ($order_sn as $os) {
                if ($os['coupon_id'] == $id) {
                    $sn = ((int)$os['order_sn']);
                    $order_sn_arr[] = (string)$sn;
                }
            }
            $order_info = [];

            foreach ($order_sn_arr as $osa) {
                if ($all_retval[$osa]['client'] === 'ios') {
                    $order_info['ios']++;
                } elseif ($all_retval[$osa]['client'] === 'android') {
                    $order_info['android']++;
                } elseif ($all_retval[$osa]['cookie']['g_id'] == $id) {
                    $order_info['share']++;
                } else {
                    $order_info['wx']++;
                }
            }

            $g_view = [];

            $g_view['id'] = $k;//商品id
            $g_view['title'] = ($coupon_data && $coupon_data->title) ? $coupon_data->title : '';
            $g_view['android_view'] = array_key_exists('android', $gr) ? (int)$gr['android'] : 0;//安卓浏览数
            $g_view['ios_view'] = array_key_exists('ios', $gr) ? (int)$gr['ios'] : 0;//IOS浏览数
            $g_view['wx_view'] = array_key_exists('weixin', $gr) ? (int)$gr['weixin'] : 0;//网页浏览数
            //$g_view['share_view'] = (int)$gr['share']?:0;//分享链接浏览数
            $g_view['android_order'] = array_key_exists('android',
                $order_info) ? (int)$order_info['android'] : 0;//安卓下单量
            $g_view['ios_order'] = array_key_exists('ios', $order_info) ? (int)$order_info['ios'] : 0;//安卓下单量
            $g_view['wx_order'] = array_key_exists('wx', $order_info) ? (int)$order_info['wx'] : 0;//网页下单量
            $g_view['share_order'] = array_key_exists('share', $order_info) ? (int)$order_info['share'] : 0;//外链接下单量
            $g_view['dateline'] = $end_time;
            $g_view['city'] = ($coupon_data && $coupon_data->city) ? $coupon_data->city : 'WH';

            $goods_log->save($g_view);

            $together_log = $mongoDB->t_goods_log;
            $this->tg_goods($together_log, $g_view);

        }

    }

    /**
     * 活动的访问统计
     */
    private function tjexcercise($mongoDB, $start_time = 0)
    {
        $log_data = $mongoDB->log_data;

        if (!$start_time) {
            $start_time = strtotime(date('Y-m-d')) - 3600 * 24;
        }

        $end_time = strtotime(date('Y-m-d'));
        $excercise_log = $mongoDB->excercise_data;
        $excercise_log->remove(array('dateline' => array('$gte' => $end_time)));

        $keys = array('decode_p.id' => 1);
        $initial = array('ios' => 0, 'android' => 0, 'weixin' => 0);
        $reduce = "function(obj,prev) {
            if(obj.client=='ios'){prev.ios++;};
            if(obj.client=='android'){prev.android++;};
         }";
        $conditions = array(
            'dateline' => array('$gte' => $start_time, '$lt' => $end_time),
            '$or' => array(array('url' => '/kidsplay/index/detail'))
        );
        $grouped = $log_data->group($keys, $initial, $reduce, array('condition' => $conditions));
        $base_retval = $grouped['retval'];

        $base_arr = [];
        foreach ($base_retval as $gr) {
            $id = (int)$gr['decode_p.id'];
            if (!$id) {
                continue;
            }
            $base_arr[$id]['ios'] = (int)$gr['ios'];
            $base_arr[$id]['android'] = (int)$gr['android'];
            $base_arr[$id]['weixin'] = (int)$gr['weixin'];
        }

        //weixin
        $keys = array('post_array.id' => 1);
        $initial = array('ios' => 0, 'android' => 0, 'weixin' => 0);
        $reduce = "function(obj,prev) {
            if(obj.client=='ios'){prev.ios++;};
            if(obj.client=='android'){prev.android++;};
            if(obj.client=='weixin' || obj.client==0){prev.weixin++;};
         }";
        $conditions = array(
            'dateline' => array('$gte' => $start_time, '$lt' => $end_time),
            '$or' => array(array('url' => '/kidsplay/index/detail'))
        );
        $grouped = $log_data->group($keys, $initial, $reduce, array('condition' => $conditions));
        $wx_retval = $grouped['retval'];

        foreach ($wx_retval as $gr) {
            $id = (int)$gr['post_array.id'];
            if (!$id) {
                continue;
            }
            if (array_key_exists($id, $base_arr)) {
                $base_arr[$id]['ios'] += (int)$gr['ios'];
                $base_arr[$id]['android'] += (int)$gr['android'];
                $base_arr[$id]['weixin'] += (int)$gr['weixin'];
            } else {
                $base_arr[$id]['ios'] = (int)$gr['ios'];
                $base_arr[$id]['android'] = (int)$gr['android'];
                $base_arr[$id]['weixin'] = (int)$gr['weixin'];
            }
        }

        //当天的所有订单
        $order_sn = $this->_getPlayOrderInfoTable()->fetchLimit(0, 10000, ['order_sn', 'bid'], [
            'order_type' => 3,
            'order_status' => 1,
            'pay_status > ?' => 1,
            'dateline >= ?' => $start_time,
            'dateline < ?' => $end_time
        ], ['dateline' => 'desc'])->toArray();

        $all_sn = [];
        foreach ($order_sn as $v) {
            $all_sn[] = (int)$v['order_sn'];
        }

        $where = array(
            'dateline' => array('$gte' => $start_time, '$lt' => $end_time),
            '$or' => array(array('url' => '/kidsplay/apply/order'))
        );

        $order_retval = $log_data->find($where, [
            'client' => 1,
            'dateline' => 1,
            'decode_p.order_sn' => 1,
            'post_array.order_sn' => 1,
            'cookie.b_id' => 1
        ])->sort(array('dateline' => -1));

        $all_retval = [];//所有下单sn
        foreach ($order_retval as $vv) {
            if (in_array((int)$vv['decode_p']['order_sn'], $all_sn)) {
                if (!array_key_exists((int)$vv['decode_p']['order_sn'],
                        $all_retval) or $all_retval[(int)$vv['decode_p']['order_sn']]['dateline'] > $vv['dateline']
                ) {
                    $all_retval[$vv['decode_p']['order_sn']] = $vv;
                }
            } else {
                if (in_array((int)$vv['post_array']['order_sn'], $all_sn)) {
                    if (!array_key_exists((int)$vv['post_array']['order_sn'],
                            $all_retval) or $all_retval[(int)$vv['post_array']['order_sn']]['dateline'] > $vv['dateline']
                    ) {
                        $all_retval[$vv['post_array']['order_sn']] = $vv;
                    }
                }
            }
        }

        foreach ($base_arr as $k => $gr) {
            $base_data = $this->_getPlayExcerciseBaseTable()->fetchLimit(0, 1, ['name', 'city'],
                array('id' => $k))->current();

            if (!$base_data or !$base_data->name) {
                continue;
            }

            $bid = (int)$k;
            $order_sn_arr = [];
            foreach ($order_sn as $os) {
                if ($os['bid'] == $bid) {
                    $sn = ((int)$os['order_sn']);
                    $order_sn_arr[] = (string)$sn;
                }
            }
            $order_info = [];

            foreach ($order_sn_arr as $osa) {
                if ($all_retval[$osa]['client'] === 'ios') {
                    $order_info['ios']++;
                } elseif ($all_retval[$osa]['client'] === 'android') {
                    $order_info['android']++;
                } elseif ($all_retval[$osa]['cookie']['g_id'] == $bid) {
                    $order_info['share']++;
                } else {
                    $order_info['wx']++;
                }
            }


            $e_view = [];
            $e_view['id'] = (int)$bid;//活动id
            $e_view['title'] = ($base_data && $base_data->name) ? $base_data->name : '';
            $e_view['android_view'] = array_key_exists('android', $gr) ? (int)$gr['android'] : 0;//安卓浏览数
            $e_view['ios_view'] = array_key_exists('ios', $gr) ? (int)$gr['ios'] : 0;//IOS浏览数
            $e_view['wx_view'] = array_key_exists('weixin', $gr) ? (int)$gr['weixin'] : 0;//网页浏览数
            //$e_view['share_view'] = (int)$gr['share']?:0;//分享链接浏览数
            $e_view['android_order'] = array_key_exists('android',
                $order_info) ? (int)$order_info['android'] : 0;//安卓下单量
            $e_view['ios_order'] = array_key_exists('ios', $order_info) ? (int)$order_info['ios'] : 0;//安卓下单量
            $e_view['wx_order'] = array_key_exists('wx', $order_info) ? (int)$order_info['wx'] : 0;//网页下单量
            $e_view['share_order'] = array_key_exists('share', $order_info) ? (int)$order_info['share'] : 0;//外链接下单量
            $e_view['dateline'] = $end_time;
            $e_view['city'] = ($base_data && $base_data->city) ? $base_data->city : 'WH';

            $excercise_log->save($e_view);

            $together_log = $mongoDB->t_excercise_log;
            $this->tg_excercise($together_log, $e_view);
        }

    }

    /**
     * 专题的访问统计
     */
    private function tjzt($mongoDB, $start_time = 0)
    {

        $log_data = $mongoDB->log_data;

        if (!$start_time) {
            $start_time = strtotime(date('Y-m-d')) - 3600 * 24;
        }

        $end_time = strtotime(date('Y-m-d'));

        $act_log = $mongoDB->activity_data;
        $act_log->remove(array('dateline' => array('$gte' => $end_time)));

        $keys = array('decode_p.id' => 1);
        $initial = array('ios' => 0, 'android' => 0, 'weixin' => 0);
        $reduce = "function(obj,prev) {
            if(obj.client=='ios'){prev.ios++;};
            if(obj.client=='android'){prev.android++;};
         }";
        $conditions = array(
            'dateline' => array('$gte' => $start_time, '$lt' => $end_time),
            'decode_p.show' => array('$exists' => false),
            '$or' => array(array('url' => '/topic/index/info'))
        );
        $grouped = $log_data->group($keys, $initial, $reduce, array('condition' => $conditions));
        $good_retval = $grouped['retval'];

        $zt_arr = [];
        foreach ($good_retval as $gr) {
            $id = (int)$gr['decode_p.id'];
            if (!$id) {
                continue;
            }
            $zt_arr[$id]['ios'] = (int)$gr['ios'];
            $zt_arr[$id]['android'] = (int)$gr['android'];
            $zt_arr[$id]['weixin'] = (int)$gr['weixin'];
        }

        $keys = array('query_array.id' => 1);
        $initial = array('ios' => 0, 'android' => 0, 'weixin' => 0);
        $reduce = "function(obj,prev) {
            if(obj.client != 'android' || obj.client != 'ios'){prev.weixin++;};
         }";
        $conditions = array(
            'dateline' => array('$gte' => $start_time, '$lt' => $end_time),
            'url' => array('$regex' => '^/web/tag/info?')
        );
        $grouped = $log_data->group($keys, $initial, $reduce, array('condition' => $conditions));
        $wx_retval = $grouped['retval'];

        foreach ($wx_retval as $gr) {
            $id = (int)$gr['query_array.id'];
            if (!$id) {
                continue;
            }
            if (array_key_exists($id, $zt_arr)) {
                $zt_arr[$id]['ios'] += (int)$gr['ios'];
                $zt_arr[$id]['android'] += (int)$gr['android'];
                $zt_arr[$id]['weixin'] += (int)$gr['weixin'];
            } else {
                $zt_arr[$id]['ios'] = (int)$gr['ios'];
                $zt_arr[$id]['android'] = (int)$gr['android'];
                $zt_arr[$id]['weixin'] = (int)$gr['weixin'];
            }
        }

        foreach ($zt_arr as $k => $gr) {
            $ztid = (int)$k;
            $data = $this->_getPlayActivityTable()->fetchLimit(0, 1,
                ['e_time', 's_time', 'ac_name', 'ac_type', 'ac_city', 'status', 'view_type'],
                array('id' => $ztid))->current();

            if (!$data) {
                continue;
            }


            $g_view = [];
            $g_view['id'] = $ztid;//专题ID
            $g_view['ac_name'] = ($data && $data->ac_name) ? $data->ac_name : '';//专题名称
            $g_view['ac_type'] = ($data && $data->ac_type) ? $data->ac_type : '';//专题类别
            $g_view['status'] = $data->status;//专题状态
            $g_view['view_type'] = ($data && $data->view_type) ? $data->view_type : '';//展示类型

            $g_view['linkg'] = (int)$this->_getPlayActivityCouponTable()->fetchAll(array(
                'aid' => $g_view['id'],
                'type' => 'game'
            ), [], 100)->count();
            $g_view['linkp'] = (int)$this->_getPlayActivityCouponTable()->fetchAll(array(
                'aid' => $g_view['id'],
                'type' => 'place'
            ), [], 100)->count();
            $g_view['linke'] = (int)$this->_getPlayActivityCouponTable()->fetchAll(array(
                'aid' => $g_view['id'],
                'type' => 'excercise'
            ), [], 100)->count();

            $apt = $this->_getAdapter();
            $sql = 'select count(*) as c from play_share where `type`= ? and share_id = ?';
            $c = $apt->query($sql, ['activity', $g_view['id']])->current();
            $count = $c->c;
            $g_view['share'] = $count;

            $g_view['android_view'] = (int)$gr['android'] ?: 0;//安卓浏览数
            $g_view['ios_view'] = (int)$gr['ios'] ?: 0;//IOS浏览数
            $g_view['wx_view'] = (int)$gr['weixin'] ?: 0;//网页浏览数

            $g_view['dateline'] = $end_time;
            $g_view['city'] = ($data && $data->ac_city) ? $data->ac_city : 'WH';

            $act_log->save($g_view);
            $together_log = $mongoDB->t_activity_log;
            $this->tg_zt($together_log, $g_view);
        }

    }

    /**
     * 游玩地的访问统计
     */
    private function tjplace($mongoDB, $start_time = 0)
    {

        $log_data = $mongoDB->log_data;

        if (!$start_time) {
            $start_time = strtotime(date('Y-m-d')) - 3600 * 24;
        }
        $end_time = strtotime(date('Y-m-d'));

        $p_log = $mongoDB->place_data;
        $p_log->remove(array('dateline' => array('$gte' => $end_time)));

        $keys = array('decode_p.id' => 1);
        $initial = array('ios' => 0, 'android' => 0, 'weixin' => 0);
        $reduce = "function(obj,prev) {
            if(obj.client=='ios'){prev.ios++;};
            if(obj.client=='android'){prev.android++;};
         }";
        $conditions = array(
            'dateline' => array('$gte' => $start_time, '$lt' => $end_time),
            '$or' => array(
                array('url' => '/place/index'),
                array('url' => '/place/index/newindex'),
                array('url' => '/place/index/index')
            )
        );
        $grouped = $log_data->group($keys, $initial, $reduce, array('condition' => $conditions));
        $place_retval = $grouped['retval'];

        $place_arr = [];
        foreach ($place_retval as $gr) {
            $id = (int)$gr['decode_p.id'];
            if (!$id) {
                continue;
            }
            $place_arr[$id]['ios'] = (int)$gr['ios'];
            $place_arr[$id]['android'] = (int)$gr['android'];
            $place_arr[$id]['weixin'] = (int)$gr['weixin'];
        }


        //微信非接口统计
        $keys = array('query_array.id' => 1);
        $initial = array('ios' => 0, 'android' => 0, 'weixin' => 0);
        $reduce = "function(obj,prev) {
            if(obj.client != 'android' || obj.client != 'ios'){prev.weixin++;};
         }";
        $conditions = array(
            'dateline' => array('$gte' => $start_time, '$lt' => $end_time),
            'url' => array('$regex' => '^/web/place/index?')
        );
        $grouped = $log_data->group($keys, $initial, $reduce, array('condition' => $conditions));
        $wx_retval = $grouped['retval'];

        foreach ($wx_retval as $gr) {
            $id = (int)$gr['query_array.id'];
            if (!$id) {
                continue;
            }
            if (array_key_exists($id, $wx_retval)) {
                $place_arr[$id]['ios'] += (int)$gr['ios'];
                $place_arr[$id]['android'] += (int)$gr['android'];
                $place_arr[$id]['weixin'] += (int)$gr['weixin'];
            } else {
                $place_arr[$id]['ios'] = (int)$gr['ios'];
                $place_arr[$id]['android'] = (int)$gr['android'];
                $place_arr[$id]['weixin'] = (int)$gr['weixin'];
            }
        }

        foreach ($place_arr as $k => $gr) {
            $id = (int)$k;//专题ID
            $data = $this->_getPlayShopTable()->fetchLimit(0, 1,
                ['shop_city', 'shop_name', 'busniess_circle', 'post_number', 'good_num', 'star_num'],
                array('shop_id' => $id))->current();

            if (!$data) {
                continue;
            }

            $g_view = [];
            $g_view['id'] = $id;//专题ID
            $g_view['shop_name'] = ($data && $data->shop_name) ? $data->shop_name : '';//游玩地名称
            $g_view['busniess_circle'] = ($data && $data->busniess_circle) ? $data->busniess_circle : '';//商圈
            $g_view['circle'] = CouponCache::getBusniessCircle($g_view['busniess_circle'], $data->shop_city);//商圈
            $g_view['post_number'] = (int)($data && $data->post_number) ? $data->post_number : 0;//评论数
            $g_view['good_num'] = (int)($data && $data->good_num) ? $data->good_num : '';//商品数
            $g_view['star_num'] = ($data && $data->star_num) ? $data->star_num : '';//评分数

            $g_view['share'] = (int)$this->_getPlayShareTable()->fetchAll(
                array(
                    'dateline >= ?' => $start_time,
                    'dateline < ?' => $end_time,
                    'type' => 'shop',
                    'share_id' => $g_view['id']
                ), [], 1000)->count();

            $g_view['android_view'] = (int)$gr['android'] ?: 0;//安卓浏览数
            $g_view['ios_view'] = (int)$gr['ios'] ?: 0;//IOS浏览数
            $g_view['wx_view'] = (int)$gr['weixin'] ?: 0;//网页浏览数

            $g_view['dateline'] = $end_time;
            $g_view['city'] = ($data && $data->shop_city) ? $data->shop_city : 'WH';

            $p_log->save($g_view);

            $together_log = $mongoDB->t_places_log;
            $this->tg_places($together_log, $g_view);
        }
    }

    /**
     * 累计商品
     * @param $together_log //累积统计的商品集合
     * @param $v //单个商品
     */
    private function tg_goods($together_log, $v)
    {
        $together_log->update(array('_id' => (int)$v['id']),
            array(
                '$set' => array(
                    '_id' => (int)$v['id'],
                    'title' => $v['title'],
                    'city' => $v['city'],
                ),
                '$inc' => array(
                    'android_view' => (int)$v['android_view'],
                    'ios_view' => (int)$v['ios_view'],
                    'wx_view' => (int)$v['wx_view'],
                    'android_order' => (int)$v['android_order'],
                    'ios_order' => (int)$v['ios_order'],
                    'wx_order' => (int)$v['wx_order'],
                    'share_order' => (int)$v['share_order'],
                )
            ), array('upsert' => true));

    }

    /**
     * 累计游玩地
     * @param $together_log
     * @param $v
     */
    private function tg_places($together_log, $v)
    {
        $together_log->update(array('_id' => (int)$v['id']),
            array(
                '$set' => array(
                    '_id' => (int)$v['id'],
                    'shop_name' => $v['shop_name'],
                    'busniess_circle' => $v['busniess_circle'],
                    'circle' => $v['circle'],
                    'post_number' => (int)$v['post_number'],
                    'good_num' => (int)$v['good_num'],
                    'star_num' => $v['star_num'],
                    'city' => $v['city'],
                ),
                '$inc' => array(
                    'share' => (int)$v['share'],
                    'android_view' => (int)$v['android_view'],
                    'ios_view' => (int)$v['ios_view'],
                    'wx_view' => (int)$v['wx_view'],
                )
            ), array('upsert' => true));

    }

    /**
     * @param $together_log
     * @param $v
     */
    private function tg_excercise($together_log, $v)
    {
        $together_log->update(array('_id' => (int)$v['id']),
            array(
                '$set' => array(
                    '_id' => (int)$v['id'],
                    'title' => $v['title'],
                    'city' => $v['city'],
                ),
                '$inc' => array(
                    'android_view' => (int)$v['android_view'],
                    'ios_view' => (int)$v['ios_view'],
                    'wx_view' => (int)$v['wx_view'],
                    'android_order' => (int)$v['android_order'],
                    'ios_order' => (int)$v['ios_order'],
                    'wx_order' => (int)$v['wx_order'],
                    'share_order' => (int)$v['share_order'],
                )
            ), array('upsert' => true));

    }

    /**
     * @param $together_log
     * @param $v
     */
    private function tg_zt($together_log, $v)
    {
        $together_log->update(array('_id' => (int)$v['id']),
            array(
                '$set' => array(
                    '_id' => (int)$v['id'],
                    'ac_name' => $v['ac_name'],
                    'ac_type' => (int)$v['ac_type'],
                    'status' => (int)$v['status'],
                    'view_type' => (int)$v['view_type'],
                    'linkg' => (int)$v['linkg'],
                    'linkp' => (int)$v['linkp'],
                    'linke' => (int)$v['linke'],
                    'city' => $v['city'],
                    'share' => (int)$v['share'],
                ),
                '$inc' => array(
                    'android_view' => (int)$v['android_view'],
                    'ios_view' => (int)$v['ios_view'],
                    'wx_view' => (int)$v['wx_view'],
                )
            ), array('upsert' => true));
    }

    public function togetherAction()
    {

        $m = new \MongoClient('mongodb://127.0.0.1:27017');
        $mongoDB = $m->wft_accesslog;

        $this->tjexcercise($mongoDB, 0);
        $this->tjgoods($mongoDB, 0);
        $this->tjplace($mongoDB, 0);
        $this->tjzt($mongoDB, 0);


    }


    public function historyAction()
    {
        $start_time = strtotime(date('Y-m-d')) - 3600 * 24;
        $apt = $this->_getAdapter();
        $sql = 'select count(*) as c from play_order_info where pay_status > 1 and dateline < ' . $start_time;
        $count = $apt->query($sql, [])->current();
        $total = (int)$count['c'];
        $m = new \MongoClient('mongodb://127.0.0.1:27017');
        $mongoDB = $m->wft_accesslog;
        $i = $offset = 0;
        $row = 500;

        while ($offset < $total) {
            $orders = $this->_getPlayOrderInfoTable()->getUserOrder($offset, $row, ['order_type', 'bid', 'coupon_id'],
                ['pay_status > ?' => 1, 'play_order_info.dateline < ?' => $start_time], ['order_sn' => 'asc'])->toArray();
            foreach ($orders as $v) {
                if ((int)$v['order_type'] === 2) {//good
                    $together_log = $mongoDB->t_goods_log;
                    $v['android_order'] = ($v['device_type'] === 'android') ? 1 : 0;
                    $v['ios_order'] = ($v['device_type'] === 'ios') ? 1 : 0;
                    $v['wx_order'] = $v['device_type'] ? 0 : 1;
                    $together_log->update(array('_id' => (int)$v['coupon_id']),
                        array(
                            '$set' => array(
                                '_id' => (int)$v['coupon_id'],
                            ),
                            '$inc' => array(
                                'android_order' => (int)$v['android_order'],
                                'ios_order' => (int)$v['ios_order'],
                                'wx_order' => (int)$v['wx_order'],
                                'share_order' => (int)$v['share_order'],
                            )
                        ), array('upsert' => true));
                } elseif ((int)$v['order_type'] === 3) {//活动
                    $together_log = $mongoDB->t_excercise_log;
                    $v['android_order'] = ($v['device_type'] === 'android') ? 1 : 0;
                    $v['ios_order'] = ($v['device_type'] === 'ios') ? 1 : 0;
                    $v['wx_order'] = $v['device_type'] ? 0 : 1;
                    $together_log->update(array('_id' => (int)$v['bid']),
                        array(
                            '$set' => array(
                                '_id' => (int)$v['bid'],
                            ),
                            '$inc' => array(
                                'android_order' => (int)$v['android_order'],
                                'ios_order' => (int)$v['ios_order'],
                                'wx_order' => (int)$v['wx_order'],
                                'share_order' => (int)$v['share_order'],
                            )
                        ), array('upsert' => true));
                }
            }
            $i++;
            $offset = $i * $row;
        }
    }

    //统计渠道vip 统计当天通过渠道成为会员
    public function vipAction()
    {
        $db = M::getAdapter();

        $start_date=date('Y-m-d 00:00:00');
        $end_date=date('Y-m-d 23:59:59');
        $start_time = strtotime($start_date);
        $end_time = strtotime($end_date);

        $res = $db->query("SELECT
	uid,
	flow_money,
	money_service_id,
	ps_lottery_login_record.*
FROM
	ps_lottery_login_record
LEFT JOIN play_account_log ON ps_lottery_login_record.user_id = play_account_log.uid
WHERE
	play_account_log.action_type = 1
AND play_account_log. STATUS = 1
AND play_account_log.money_service_id != 0
AND play_account_log.dateline >= ?
AND play_account_log.dateline <= ?
AND play_account_log.from_uid=0
AND ps_lottery_login_record.created >= ?
AND ps_lottery_login_record.created <= ?


GROUP BY
	play_account_log.uid", array($start_time,$end_time,$start_date,$end_date))->toArray();

        $count_array = array();
        foreach ($res as $v) {
            if (!isset($count_array[$v['channel']])) {
                $count_array[$v['channel']]['count'] = 1;
                $count_array[$v['channel']]['vip_688']=0;
                $count_array[$v['channel']]['vip_988']=0;
                $count_array[$v['channel']]['vip_1688']=0;


                if($v['money_service_id']==1){
                    $count_array[$v['channel']]['vip_688'] = 1;
                }elseif($v['money_service_id']==2){
                    $count_array[$v['channel']]['vip_988'] = 1;
                }elseif($v['money_service_id']==3){
                    $count_array[$v['channel']]['vip_1688'] = 1;
                }

            } else {
                $count_array[$v['channel']]['count'] += 1;
                if($v['money_service_id']==1){
                    $count_array[$v['channel']]['vip_688'] += 1;
                }elseif($v['money_service_id']==2){
                    $count_array[$v['channel']]['vip_988'] += 1;
                }elseif($v['money_service_id']==3){
                    $count_array[$v['channel']]['vip_1688'] += 1;
                }
            }
        }

        $db->query("delete from  play_vip_channel_count WHERE dateline>=?", array($start_time));


        foreach ($count_array as $k => $v) {
//            var_dump("insert into play_vip_channel_count VALUES (NULL ,'{$k}',{$v['count']},{$time},{$v['vip_688']},{$v['vip_988']},{$v['vip_1688']})");die;
            $db->query("insert into play_vip_channel_count VALUES (NULL ,'{$k}',{$v['count']},{$start_time},{$v['vip_688']},{$v['vip_988']},{$v['vip_1688']})", array());
        }

    }
}
