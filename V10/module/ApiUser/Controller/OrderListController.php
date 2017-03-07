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
use library\Service\ServiceManager;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;
use Zend\Db\Adapter\Platform\Mysql;
use Zend\Db\Sql\Select;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Http\Response;
use Deyi\JsonResponse;

class OrderListController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    //订单列表接口
    public function indexAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = $this->getParams('uid'); //用户id
        $p = $this->getParams('page', 1);
        $limit = (int)$this->getParams('pagenum', 10);
        $offset = ($p - 1) * $limit;

        if (!$uid) {
            return $this->jsonResponseError('参数错误1');
        }

        $order_type = $this->getParams('order_type', 0); //订单类型  0(全部) 1(商品)  2(活动) 3(拼团)
        $order_status = $this->getParams('order_status', 0); //订单状态 0(全部) 1(待付款) 2(待使用) 3(待评价) 4(退款/返利)


        //付款状态 ;0未付款;1付款中;2已付款 3  退款中 4 退款成功 5已使用 6已过期 7团购中',
        $where1 = '';  //一级筛选判断
        $where2 = ''; //二级筛选判断
        $fields = '';//其他字段
        if ($order_type == 0) {
            $where1 = "AND play_order_info.order_type in(2,3)";
        } elseif ($order_type == 1) {
            $where1 = "AND play_order_info.order_type =2 AND play_order_info.group_buy_id=0";
        } elseif ($order_type == 2) {
            $where1 = "AND play_order_info.order_type =3";
        } elseif ($order_type == 3) {
            $where1 = "AND play_order_info.order_type =2 AND play_order_info.group_buy_id!=0";
        }

        $join = '';
        //二级
        if ($order_status == 1) {
            $timer = time() - ServiceManager::getConfig('TRADE_CLOSED');
            $where2 = "AND pay_status =0 AND play_order_info.dateline > {$timer}";
        } elseif ($order_status == 2) {
            $where2 = "AND (back_number + use_number+backing_number) < buy_number
AND  play_order_info.pay_status <6
AND  play_order_info.pay_status >1 ";
        } elseif ($order_status == 3) {
            $where2 = "AND `use_number` > 0  AND (`comment`=0 OR  ISNULL(comment))";
        } elseif ($order_status == 4) {
            //todo 退款与返利  慢查询,此类数据为空,临时注释 wwjie 11/19
//            $fields = 'play_account_log.flow_money,cash.price,';
//
            $where2 = "AND (play_order_info.backing_number >0 or play_order_info.back_number >0)";
//            $join = 'LEFT JOIN play_account_log ON (play_account_log.object_id = play_order_info.order_sn AND play_account_log.action_type=1 AND play_account_log.action_type_id=16)
//            LEFT JOIN play_cashcoupon_user_link AS cash  ON (cash.get_object_id = play_order_info.order_sn AND cash.get_type=16)
//            ';
        }


        //全部类型 全部状态

        $db = $this->_getAdapter();
        $res = $db->query("
SELECT
$fields
play_order_info.order_sn,
play_order_info.coupon_id,
play_order_info.group_buy_id,
play_order_info.coupon_name,
play_order_info.real_pay,
play_order_info.account_money,
play_order_info.coupon_id,
play_order_info.group_buy_id,
play_order_info.pay_status,
play_order_info.order_type,
play_order_info.voucher_id,
play_order_info.buy_number,
play_order_info.total_price,
play_order_info.bid,
play_order_info.real_pay,
play_order_info.backing_number,

play_group_buy.join_number,
play_group_buy.limit_number,
play_group_buy.end_time as g_end_time,

play_game_info.remark,
play_game_info.id as info_id,
play_game_info.order_method,

play_order_info_game.address,
play_order_info_game.thumb,
play_order_info_game.end_time as g_valid_time,
play_order_info_game.start_time,
play_order_info_game.end_time,


play_excercise_event.start_time as a_start_time,
play_excercise_event.end_time as a_end_time,
play_excercise_event.shop_name as a_shop_name,
play_excercise_event.join_number as a_join_number,
play_excercise_event.least_number as a_least_number,
play_excercise_event.meeting_desc,
play_excercise_event.over_time,



play_excercise_base.thumb as thumb2,
play_order_otherdata.comment,
play_order_otherdata.meeting_place as meeting_place
FROM
play_order_info
LEFT JOIN play_order_info_game ON play_order_info.order_sn = play_order_info_game.order_sn
LEFT JOIN play_group_buy ON play_order_info.group_buy_id=play_group_buy.id
LEFT JOIN play_game_info ON play_game_info.id=play_order_info.bid
LEFT JOIN play_excercise_event ON play_excercise_event.id=play_order_info.coupon_id
LEFT JOIN play_excercise_base ON play_excercise_base.id=play_excercise_event.bid
LEFT JOIN play_order_otherdata ON play_order_info.order_sn = play_order_otherdata.order_sn
{$join}
WHERE
	`user_id` = ?
AND `order_status` = 1
{$where1}
{$where2}
ORDER BY
	play_order_info.dateline DESC
LIMIT {$offset},{$limit}
", array($uid));
        $data = array();

        foreach ($res as $v) {

            $cash_data = false;

            if ($v->voucher_id) {
                $cash_data = $this->_getCashCouponUserTable()->get(array('id' => $v->voucher_id));
            }

            if ($v->order_type == 2) {
                $data_organizer_game = $this->_getPlayOrganizerGameTable()->get(array('id' => $v->coupon_id));
                if ($data_organizer_game->need_use_time == 2) {
                    $data_start_time = (int)($v->order_type==3?$v->a_start_time:$v->start_time);
                    $data_end_time = 0;
                } else {
                    $data_start_time = (int)($v->order_type==3?$v->a_start_time:$v->start_time);
                    $data_end_time = (int)($v->order_type==3?$v->a_end_time:$v->end_time);
                }
            } else {
                $data_start_time = (int)($v->order_type==3?$v->a_start_time:$v->start_time);
                $data_end_time = (int)($v->order_type==3?$v->a_end_time:$v->end_time);
            }

            $money=bcadd($v->real_pay, $v->account_money, 2);
            $data[] = array(
                'order_sn' => $v->order_sn,
                'bid' => $v->bid, //活动id
                'info_id' => $v->info_id?:0, //套系id
                'group_buy_id' => $v->group_buy_id,
                'title' => $v->coupon_name,
                'img' => $v->thumb ? $this->getImgUrl($v->thumb) : $this->getImgUrl($v->thumb2),
                'buy_number' => $this->getAllPeopleNumber($v->order_type,$v->order_sn,$v->buy_number), //购买数量
                'money' =>$money,  //金额
                'use_time' => $v->remark,  //使用时间 商品  字符串
                'coupon_id' => $v->coupon_id,  //商品id  或场次id
                'order_method' => $v->order_method, //预约方式
                'join_number' => (int)$v->join_number, //参加团的人数
                'limit_number' => (int)$v->limit_number, //成员人数
                'use_address' => $v->address, //使用地点
                'valid_time' => (int)$v->g_valid_time,  //有效时间
                'over_time' => (int)$v->over_time,  //活动订单截止报名时间
                'g_end_time' => (int)$v->g_end_time, //团解散的时间
                'rebate' =>"0.00",//sprintf("%.2f",$v->flow_money + $v->price), //返利金额 只有返利与退款界面才有
                'start_time' => $data_start_time,//活动开始时间 或商品
                'end_time' => $data_end_time,//活动截止时间     或商品
                'activity_join_number' => (int)$v->a_join_number, //活动参加人数
                'activity_least_number' => (int)$v->a_least_number, //场次接受的人数
                'activity_address' => $v->a_shop_name, //活动地址
                'activity_meeting' => $v->meeting_place, //活动集合方式
                'order_type' => $this->getordertype($v->order_type,$v->group_buy_id),//类型
                'pay_status' =>(int)$this->getPayStatus($order_type,$order_status,$v->pay_status,$v->backing_number) , //订单状态
                'is_group' => $v->group_buy_id ? 1 : 0, //是否是团
                'cash_coupon_name' => $cash_data ? $cash_data->title : "",
                'cash_coupon_id' => $cash_data ? $cash_data->id : "",
                'cash_coupon_price' => $cash_data ? $cash_data->price : "",
                'comment' => (int)$v->comment, //是否已评价
                'total_price' => $v->total_price>0?$v->total_price:$money, //总价
                'eid'=>$v->coupon_id //android bug 7/20
            );
        }
        return $this->jsonResponse($data);
    }

    public function getAllPeopleNumber($order_type,$order_sn,$buy_number){
        if($order_type==3){
            return $this->_getPlayOrderInsureTable()->fetchCount(array('order_sn'=>$order_sn));
        }else{
            return $buy_number;
        }
    }
    public function getPayStatus($order_type=0,$order_status=0,$status=0,$backing_number)
    {
        //$order_type = $this->getParams('order_type', 0); //订单类型  0(全部) 1(商品)  2(活动) 3(拼团)
        //$order_status = $this->getParams('order_status', 0); //订单状态 0(全部) 1(待付款) 2(待使用) 3(待评价) 4(退款/返利)

        //pay_status 付款状态 ;0未付款;1付款中;2已付款 3  退款中 4 退款成功 5已使用 6已过期 7团购中


        if($order_status==2){ //待使用
           if($status==7){
               return $status;
           }else{
               return 2;
           }
        }

        if($order_status==3){
            return 5;
        }
        if($order_status==4){
            //退款中 退款完成 已返利
            if($backing_number>=1){
                return 3;
            }
        }

        return $status;
    }

    //验证码页面
    public function codeinfoAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = $this->getParams('uid'); //用户id
        $order_sn = $this->getParams('order_sn');

        $order_data = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn, 'user_id' => $uid));

        if (!$order_data or $order_data->order_status == 0 or $order_data->pay_status < 2) {
            return $this->jsonResponseError('订单状态异常');
        }

        $data = array();
        if ($order_data->order_type == 2) {
            $code_data = $this->_getPlayCouponCodeTable()->fetchAll(array('order_sn' => $order_sn));
            $game = $this->_getPlayOrderInfoGameTable()->get(array('order_sn' => $order_sn));
            foreach ($code_data as $v) {
                $data[] = array(
                    'status' => $v->status, //使用状态,0未使用,1已使用,2已退款,3退款中
                    'code' => $v->id . $v->password,
                    'name' => $game->type_name, //套系名称
                    'is_other' => 0,
                );
            }
        } else {
            $code_data = $this->_getPlayExcerciseCodeTable()->getCodeList($order_sn);
            foreach ($code_data as $v) {
                $data[] = array(
                    'status' => $v->status, //使用状态,0未使用,1已使用,2已退款,3退款中
                    'code' => $v->code,
                    'name' => $v->price_name, //套系名称
                    'is_other' => (int)$v->is_other,
                );
            }
        }
        return $this->jsonResponse($data);
    }

    //返回类型  订单类型  0(全部) 1(商品)  2(活动) 3(拼团)
    private function getordertype($order_type, $is_group = false)
    {
        if ($is_group) {
            return 3;
        } elseif ($order_type == 2) {
            return 1;
        } else {
            return 2;
        }

    }

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
play_organizer_game.*

FROM
play_order_info
LEFT JOIN play_organizer_game ON play_organizer_game.id = play_order_info.coupon_id
LEFT JOIN `play_coupon_code` ON `play_coupon_code`.`order_sn` = `play_order_info`.`order_sn`
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
                'use_time' => $v->use_time, // 使用时间说明
                'order_method' => $v->order_method, //预约方式说明
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
