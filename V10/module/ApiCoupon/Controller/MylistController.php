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
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;
use Zend\Db\Adapter\Platform\Mysql;
use Zend\Db\Sql\Select;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Http\Response;
use Deyi\JsonResponse;

class MylistController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;


    //待付款 ok
    public function waitAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = $this->getParams('uid'); // 0  1  2
        //所有未付款   付款状态; 0未付款; 1付款中; 2已付款 3退款中 4退款成功 5已使用
        //  $res = $this->_getPlayOrderInfoTable()->getMylist(array('user_id' => $uid, 'order_status' => 1, 'pay_status<=1'));
        $p = $this->getParams('p', 1);

        $offset = (int)$this->getParams('pagenum', 10);
        $limit = ($p - 1) * $offset;


        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');

        /**
         * 删除ios 未付款的团购  start
         */
        $group_sql = "SELECT play_order_info.order_sn FROM play_group_buy LEFT JOIN play_order_info ON play_group_buy.id=play_order_info.group_buy_id WHERE play_group_buy.add_time > (UNIX_TIMESTAMP() - 7200) AND play_group_buy.join_number=0 AND play_group_buy.uid = ?  AND play_order_info.pay_status < ? AND play_order_info.order_status = ?";
        $groupData = $db->query($group_sql, array($uid, 2, 1));
        if ($groupData->count()) {
            foreach ($groupData as $group) {
                $this->cleanGroup($group->order_sn);
            }
        }
        /* // end */


        $res = $db->query("
SELECT
play_order_info.*,
play_order_info_game.thumb,
play_order_info_game.start_time,
play_order_info_game.end_time
FROM
play_order_info
LEFT JOIN play_order_info_game ON play_order_info.order_sn = play_order_info_game.order_sn
WHERE
	`user_id` = ?
AND `order_status` = ?
AND pay_status <= ?
AND play_order_info.order_type = 2
ORDER BY
	`dateline` DESC
LIMIT {$limit},{$offset}
", array($uid, 1, 1));


        $data = array();

        foreach ($res as $v) {

            $cash_data = false;
            if ($v->voucher_id) {
                $cash_data = $this->_getCashCouponUserTable()->get(array('id' => $v->voucher_id));
            }
            $data[] = array(
                'img' => $this->getImgUrl($v->thumb),
                'title' => $v->coupon_name,
                'coupon_id' => $v->coupon_id,
                'price' => bcadd($v->real_pay, $v->account_money, 2),
                'number' => $v->buy_number,
                'pay_status' => $v->pay_status,
                'order_sn' => $v->order_sn, //订单id
                'group_buy_id' => $v->group_buy_id,
                "s_time" => $v->start_time,//出行时间
                "e_time" => $v->end_time,//出行时间
                'cash_coupon_name' => $cash_data ? $cash_data->title : "",
                'cash_coupon_id' => $cash_data ? $cash_data->id : "",
                'cash_coupon_price' => $cash_data ? $cash_data->price : "",
            );
        }
        return $this->jsonResponse($data);
    }

    //未使用  (未使用,退款中)
    public function paidAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = (int)$this->getParams('uid', 0);
        $p = $this->getParams('p', 1);

        $offset = (int)$this->getParams('pagenum', 10);
        $limit = ($p - 1) * $offset;

        //已付款 退订中 问题   付款状态; 0未付款; 1付款中; 2已付款 3退款中 4退款成功 5已使用

        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $res = $db->query("
SELECT
play_order_info.*,
play_organizer_game.*,
play_game_info.remark as info_remark,
play_game_info.order_method  as info_method
FROM
play_order_info
LEFT JOIN play_organizer_game ON play_organizer_game.id = play_order_info.coupon_id
LEFT JOIN `play_coupon_code` ON `play_coupon_code`.`order_sn` = `play_order_info`.`order_sn`
LEFT JOIN `play_game_info` ON `play_game_info`.`id` = `play_order_info`.`bid`
WHERE
	`user_id` = ?
AND `order_status` = 1
AND (back_number + use_number) < buy_number
AND  play_order_info.pay_status !=7
AND  play_order_info.pay_status >1
AND play_order_info.order_type=2
GROUP BY
play_coupon_code.order_sn
ORDER BY
play_order_info.dateline DESC
LIMIT {$limit},{$offset}
", array($uid));

        $data = array();

        foreach ($res as $v) {
            $data[] = array(
                'img' => $this->getImgUrl($v->thumb),
                'title' => $v->coupon_name,
                'coupon_id' => $v->coupon_id,
                'price' => bcadd($v->real_pay, $v->account_money, 2),
                'number' => $v->buy_number,
                'pay_status' => $v->pay_status,
                'order_sn' => $v->order_sn, //订单id.
                'use_time' => $v->info_remark, // 使用时间说明
                'order_method' => $v->info_method, //预约方式说明
                'group_buy_id' => $v->group_buy_id, //同玩
            );

        }
        return $this->jsonResponse($data);
    }


    //已完成  (已使用/已退款/已评价)   //老的已使用
    public function overAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = $this->getParams('uid');
        $p = $this->getParams('p', 1);

        $offset = (int)$this->getParams('pagenum', 10);
        $limit = ($p - 1) * $offset;


        //已付款 并且已使用      付款状态 ;0未付款;1付款中;2已付款 3  退款中 4 退款成功 5已使用   (4 5)

        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $res = $db->query("
SELECT
play_order_info.*,

play_order_info_game.thumb
FROM
play_order_info
LEFT JOIN play_order_info_game ON play_order_info.order_sn = play_order_info_game.order_sn
LEFT JOIN play_order_otherdata ON play_order_info.order_sn = play_order_otherdata.order_sn
WHERE
	`user_id` = ?
AND `order_status` = ?
AND play_order_info.order_type = 2
AND  play_order_info.pay_status >1
AND ((back_number + use_number) = buy_number)
AND (
back_number = buy_number
OR  (
use_number>0
AND comment=1
)
)
ORDER BY
	`dateline` DESC
LIMIT {$limit},{$offset}
", array($uid, 1));


        $data = array();
        foreach ($res as $v) {

            $data[] = array(
                'img' => $this->getImgUrl($v->thumb),
                'title' => $v->coupon_name,
                'coupon_id' => $v->coupon_id,
                'price' => bcadd($v->real_pay, $v->account_money, 2),
                'number' => $v->buy_number,
                'order_sn' => $v->order_sn, //订单id.
                'pay_status' => $v->pay_status,
                'comment' => (int)$v->comment,  //是否已评论
                'group_buy_id' => $v->group_buy_id,
            );
        }
        return $this->jsonResponse($data);
    }

    //待评价 (只要有一个码使用)
    public function talkAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = $this->getParams('uid');
        $p = $this->getParams('p', 1);

        $offset = (int)$this->getParams('pagenum', 10);
        $limit = ($p - 1) * $offset;

        /*
        0 => '未付款',
        1 => '付款中',
        2 => '已付款',
        3 => '退款中',
        4 => '退款成功',
        5 => '已使用',
        6 => '已过期',
        7 => '团购中'
         */


        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $res = $db->query("
SELECT
play_order_info.*,

play_order_info_game.end_time AS coupon_close1,
play_order_info_game.thumb
FROM
play_order_info
LEFT JOIN play_order_info_game ON (play_order_info.order_sn = play_order_info_game.order_sn AND play_order_info.order_type = 2)
LEFT JOIN play_order_otherdata ON play_order_info.order_sn = play_order_otherdata.order_sn
WHERE
	`user_id` = ?
AND `order_status` = ?
AND  play_order_info.pay_status >1
AND `use_number` > 0
AND (`comment`=0 OR  ISNULL(comment))

ORDER BY
	`dateline` DESC
LIMIT {$limit},{$offset}
", array($uid, 1));


        $data = array();
        foreach ($res as $v) {

            $data[] = array(
                'img' => $this->getImgUrl($v->thumb),
                'title' => $v->coupon_name,
                'coupon_id' => $v->coupon_id,
                'price' => bcadd($v->real_pay, $v->account_money, 2),
                'number' => $v->buy_number,
                'order_sn' => $v->order_sn, //订单id.
                'pay_status' => $v->pay_status,
                'group_buy_id' => $v->group_buy_id,
            );
        }
        return $this->jsonResponse($data);
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
