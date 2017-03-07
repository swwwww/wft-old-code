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
use library\Service\System\Cache\RedCache;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Response;
use Deyi\JsonResponse;

class InfoController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;


    public function indexAction()
    {

        //已废弃

//        if (!$this->pass()) {
//            return $this->failRequest();
//        }
//
//        /*
//         * 首页进去的
//         * id   卡券id
//         *
//         *
//         * 订单中进去的
//         * id   卡券id
//         * rid  订单id
//         * */
//
//        $id = $this->getParams('id');
//        $rid = $this->getParams('rid');
//
//        $uid = $this->getParams('uid');
//
//        $couponData = $this->_getPlayCouponsTable()->get(array('coupon_id' => $id));
//        if (!$couponData) {
//            return $this->jsonResponseError('该卡券并不存在');
//        }
//
//        /**** 每日统计该卡券对应的商家 7日内总售卖数****/
//        $this->countShopSale($couponData->coupon_shopid);
//        /************   统计END   **************/
//
//        //记录卡券点击次数
//        $this->_getPlayCouponsTable()->update(array('coupon_click'=>new Expression('coupon_click+1')),array('coupon_id'=>$id));
//
//        //卡券详情
//        $res = array(
//            'img' => $couponData->coupon_cover ? $this->_getConfig()['url'] . $couponData->coupon_cover : '',
//            'coupon_id' => $id,
//            'title' => $couponData->coupon_name,
//            'buy' => ($couponData->coupon_buy + $couponData->coupon_vir),
//            'residue' => (($couponData->coupon_total - $couponData->coupon_buy) > 0) ? ($couponData->coupon_total - $couponData->coupon_buy) : 0,
//            'start_time' => $couponData->coupon_starttime,
//            'price' => $couponData->coupon_price,
//            'originalprice' => $couponData->coupon_originprice,
//            'endtime' => $couponData->coupon_endtime,
//            'close' => $couponData->coupon_close,
//            'editor_word' => str_replace(array("\r\n", "&nbsp;", ""), array("\n", ' ', ' '), htmlspecialchars_decode($couponData->editor_word)),
//            'for_age' => ($couponData->age_max == 100) ? ($couponData->age_min . '岁及以上') : ($couponData->age_min . '岁到' . $couponData->age_max . '岁'),
//            'use_time' => htmlspecialchars_decode($couponData->use_time),
//            'attend_method' => str_replace(array("\r\n", "&nbsp;", ""), array("\n", ' ', ' '), htmlspecialchars_decode($couponData->attend_method)),
//            'use_info' => '',
//            'matters_attention' => str_replace(array("\r\n", "&nbsp;", ""), array("\n", ' ', ' '), htmlspecialchars_decode($couponData->matters_attention)),
//            'discount' => round($couponData->coupon_price / $couponData->coupon_originprice * 10, 1),
//            'description' => $this->_getConfig()['url'] . '/web/organizer/info?type=3&cid='. $id,
//            'tag' => $couponData->coupon_appointment,
//            'limitnum' => $couponData->coupon_limitnum,
//            'post_number' => $couponData->post_number,
//            'allow_post' => $couponData->allow_post,
//            //'share' => $couponData->coupon_share,
//            'coupon_join' => $couponData->coupon_join,
//            'coupon_remind' => $couponData->coupon_remind ? htmlspecialchars_decode($couponData->coupon_remind) : '',
//            'time'=>time(),// 用于统一客户端计时
//            'refund_time' => $couponData->refund_time,
//            'tips' => ($couponData->refund_time > time()) ? "您取消参加{$couponData->coupon_name}，报名费3个工作日内还给你的支付宝，下次别放我鸽子哈！" : "您想要取消参加{$couponData->coupon_name}，这个是不退款的活动，你确定不参加了？",
//        );
//
//        //分享
//        $res['share'] = ($couponData->coupon_join == 1) ? '惊爆价' . $res['price'] . '元 ' . '"' . $res['title'] . '"' . '-玩翻天' : $res['title']. '-玩翻天';
//
//        // 是否需要分享
//        if ($couponData->coupon_share == 1) {
//            $res['is_share'] = 1;
//        } else {
//            if ($uid) {
//                $status = $this->_getPlayShareTable()->get(array('uid' => $uid, 'type' => 'coupon', 'share_id' => $id));
//                if ($status) {
//                    $res['is_share'] = 1;
//                } else {
//                    $res['is_share'] = 0;
//                }
//            } else {
//                $res['is_share'] = 0;
//            }
//        }
//
//        // 是否新用户专享
//        if ($couponData->new_user == 1 && $uid) {
//            $status = $this->_getPlayOrderInfoTable()->get(array('user_id' => $uid));
//            if ($status) {
//                $res['new_user'] = 1;
//            } else {
//                $res['new_user'] = 2;
//            }
//        } else {
//            $res['new_user'] = 2;
//        }
//        // 评论
//        $post_data = $this->_getPlayPostTable()->fetchAll(array('type' => 'coupon', 'object_id' => $id, 'displayorder>0'), array('displayorder' => 'desc', 'dateline' => 'desc'), 3);
//        $res['post'] = array();
//        foreach ($post_data as $v) {
//            $img_list = array();
//            $i_list = json_decode($v->photo_list, true);
//            if ($i_list) {
//                foreach ($i_list as $i_v) {
//                    $img_list[] = $this->_getConfig()['url'] . $i_v;
//                }
//            }
//            $res['post'][] = array(
//                'uid' => $v->uid,
//                'author' => $v->author,
//                'author_img' => $this->getImgUrl($v->img),
//                'subject' => $v->subject,
//                'dateline' => $v->dateline,
//                'message' => count($v->message)<7?$v->message.'　　　　　　　':$v->message,
//                'img_list' => $img_list
//            );
//        }
//
//        //订单
//        if ($rid) {
//            $orderInfo = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $rid, 'order_status' => 1,));
//            if (!$orderInfo) {
//                $res['orderlist'] = array(
//                    array(
//                        'rid' => 0,
//                        'code' => '',
//                        'status' => 0,
//                    )
//                );
//            } else {
//                if ($orderInfo->pay_status < 2) {
//                    $res['codeType'] = 1; //未付款
//                    $res['orderlist'] = array(
//                        array(
//                            'rid' => $rid,
//                            'code' => '',
//                            'status' => $orderInfo->pay_status,
//                        )
//                    );
//                } else {
//                    $orderCode = $this->_getPlayCouponCodeTable()->fetchAll(array('order_sn' => $rid));
//                    $orderList = array();
//                    foreach ($orderCode as $v) {
//                        $orderList[] = array(
//                            'rid' => $rid,
//                            'code' => $v->id . $v->password,
//                            'status' => $v->status,
//                        );
//                    }
//                    $res['codeType'] = 2; //已付款及后续
//                    $res['orderlist'] = $orderList;
//                }
//            }
//            $res['is_share'] = 1;
//        }
//
//
//        //可用门店
//        $shopList = array();
//        $shopIds = $this->_getPlayCouponsLinkerTable()->FetchShopList(array('coupon_id' => $id));
//        foreach ($shopIds as $shopData) {
//            $shopList[] = array(
//                'shop_id' => $shopData->shop_id,
//                'shopname' => $shopData->shop_name,
//                'opentime' => date('H:i', $shopData->shop_open),
//                'endtime' => date('H:i', $shopData->shop_close),
//                'address' => $shopData->shop_address,
//                'phone' => $shopData->shop_phone,
//                'addr_x' => $shopData->addr_x,
//                'addr_y' => $shopData->addr_y,
//            );
//        }
//
//        $res['shoplist'] = $shopList;
//
//        //关联店铺下其他卡券
//        $res['coupon_list'] = $this->_getPlayCouponsTable()->getShopCouponList($couponData->coupon_shopid,$id,$this->_getConfig()['url']);
//
//        return $this->jsonResponse($res);
    }


    /**
     * @param $shop_id |jsonString
     */
    public function countShopSale($shop_id)
    {
        $shops = json_decode($shop_id);
        foreach ($shops as $v) {
            if (!RedCache::get('shop_sale' . $v)) {
                //统计最近七日
                $start_time = time() - (86400 * 7);
                $hot_count = $this->_getPlayOrderInfoTable()->fetchCount(array('order_status' => 1, 'pay_status>=2', 'dateline>' . $start_time));
                $this->_getPlayShopTable()->update(array('hot_count' => $hot_count), array('shop_id' => $v));
                RedCache::set('shop_sale' . $v, 1, 86400);
            }
        }

    }


}
