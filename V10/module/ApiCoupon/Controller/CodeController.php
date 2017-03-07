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

class CodeController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;


    public function indexAction()
    {

        if (!$this->pass()) {
            return $this->failRequest();
        }

        /*
         * rid  订单id
         *
         * */

        $rid = $this->getParams('rid');
        $orderInfo = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $rid, 'order_status' => 1));
        if (!$orderInfo) {
            return $this->jsonResponseError('该订单不存在');
        }
        $order_type = $orderInfo->order_type;

        $attend_way = '';
        $start_time = '';
        $end_time = '';
        $attend_address = '';

        $ask_phone = '';
        $order_method = '';


        /*type => 1是卡券 2是活动
id   => 对应卡券 活动id
title => 对应卡券 活动名称
use_dateline => 最后使用时间 //卡券专属
attend_way => 活动方式, //活动专属
start_time => 开抢时间, // 活动专属
end_time =>  停止售票时间, // 活动专属
attend_address => 地点,// 活动专属
refund_status => 是否可以退款 1 可以 2不可以
refund_time => 活动截止时间
        */
        if ($order_type == 2) {//活动
            $game = $this->_getPlayGameInfoTable()->get(array('gid' => $orderInfo->coupon_id));
            $game_info = $this->_getPlayOrderInfoGameTable()->get(array('order_sn' => $rid));
            $gameData = $this->_getPlayOrganizerGameTable()->get(array('id' => $orderInfo->coupon_id));


            $attend_way = $game_info->type_name;  //活动方式
            $start_time = $game_info->start_time;
            $end_time = $game_info->end_time;
            $attend_address = $game_info->address;
            $refund_time = $game_info->refund_time;

            $shopData = $this->_getPlayShopTable()->get(array('shop_id' => $game->shop_id));
            $ask_phone = $shopData->shop_phone;
            $order_method = $game_info->order_method;
            $addr_x = $shopData->addr_x;
            $addr_y = $shopData->addr_y;
        }

        if ($order_type == 1) {//卡券
            $coupon_data = $this->_getPlayCouponsTable()->get(array('coupon_id' => $orderInfo->coupon_id));
            $refund_time = $coupon_data->refund_time;
            $end_time = $coupon_data->coupon_close;

            $ask_phone = '4008007221';
            $order_method = ($coupon_data->coupon_appointment == 1) ? '无需预约' : '需要预约';
            $addr_x = '';
            $addr_y = '';
        }

        $res = array(
            'type' => $orderInfo->order_type,
            'id' => $orderInfo->coupon_id,
            'title' => $orderInfo->coupon_name,
            'use_dateline' => $end_time,
            'refund_status' => ($refund_time > time()) ? 1 : 2,
            'refund_time' => $end_time,
            'attend_way' => $attend_way,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'attend_address' => $attend_address,
            'tips' => ($refund_time > time()) ? "您取消参加{$orderInfo->coupon_name}，报名费3个工作日内还给你的支付宝，下次别放我鸽子哈！" : "您想要取消参加{$orderInfo->coupon_name}，这个是不退款的活动，你确定不参加了？",
            'back_money' => ($order_type == 1) ? date('Y.m.d', $refund_time).'前支持退款' : (($game_info->refund_time < $game_info->up_time) ? '不支持退款' : (($game_info->refund_time > $game_info->end_time) ? '支持随时退款' : date('Y.m.d', $game_info->refund_time).'前支持退款')),
            'back' => ($refund_time > time()  && !$orderInfo->group_buy_id) ? 1 : 0,
            'ask_phone' => $ask_phone,
            'order_method' => $order_method,
            'addr_x' => $addr_x,
            'addr_y' => $addr_y,
            );

        $orderCode = $this->_getPlayCouponCodeTable()->fetchAll(array('order_sn' => $rid));
        $orderList = array();
        foreach ($orderCode as $v) {
            $orderList[] = array(
                'code' => $v->id . $v->password,
                'status' => $v->status,
            );
        }
        $res['order_list'] = $orderList;
        return $this->jsonResponse($res);

    }


}
