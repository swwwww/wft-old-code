<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ApiPay\Controller;

use Deyi\Alipay\Alipay;
use Deyi\BaseController;
use Deyi\Coupon\Coupon;
use Deyi\Invite\Invite;
use Deyi\Mcrypt;
use Deyi\OrderAction\InsertOrder;
use Deyi\OrderAction\OrderBack;
use Deyi\OrderAction\OrderPay;
use Deyi\OrderAction\OrderExcercisePay;
use library\Fun\Common;
use library\Fun\M;
use library\Service\Admin\Setting\Share;
use library\Service\Kidsplay\Kidsplay;
use Deyi\WeiXinPay\WeiXinPayFun;
use library\Service\Order\Order;
use library\Service\Order\CancelOrder;
use library\Service\System\Cache\RedCache;
use Deyi\Unionpay\Unionpay;
use Deyi\WeiSdkPay\WeiPay;
use Deyi\Account\Account;
use Deyi\WriteLog;
use library\Service\System\Logger;
use library\Service\User\Member;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Select;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Response;
use Deyi\JsonResponse;

class IndexController extends AbstractActionController
{
    use JsonResponse;
    use OrderBack;
    use BaseController;

    //普通活动 生成订单
    public function indexAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = $this->getParams('uid'); //用户id
        $coupon_id = $this->getParams('coupon_id');//卡券id  or 活动id
        $name = $this->getParams("name", ' ');//获取购买者姓名
        $phone = $this->getParams("phone");//获取购买者手机号
        $address = $this->getParams('address'); //购买者地址

        $number = (int)$this->getParams("number");//获取购买数量
        $game_info_id = $this->getParams('order_id');//票系组合id
        $group_buy = (int)$this->getParams('group_buy', 0);//是否组团  组团1 还是加入团2
        $group_buy_id = (int)$this->getParams('group_buy_id', 0);//加入团id
        $client_id = $this->getParams('client_id', '');// 设备id

        $cash_coupon_id = (int)$this->getParams('cash_coupon_id', 0); //现金券id
        $use_score = $this->getParams('use_score', 0); //是否使用积分

        $message = $this->getParams('message', 0); //给卖家留言
        $pwd = $this->getParams('pay_password', 0);// 支付密码 错误返回-1
        $use_account_money = $this->getParams('use_account_money', 0); //使用账户金额付款  只有当账户金额足够时


        $associates_ids = json_decode($this->getParams('associates_ids', null, false), true);

        if ($coupon_id == '1859') {
            if (!empty($use_account_money)) {
                return $this->jsonResponse(['status' => 0, 'message' => '该商品无法用余额支付']);
            }
        }

        if (!$associates_ids or !is_array($associates_ids)) {
            $associates_ids = array();
        }

        if ($group_buy or $group_buy_id) {
            $number = 1;
        }

        $city = $this->getCity();
        $qualify_coupon_id = false;
        //  检查参数是否正确
        if (!$uid or !$coupon_id or !$number) {
            return $this->jsonResponseError('参数错误');
        }
        if (!$client_id) {
            $client_id = $uid;
        }
        if (!$game_info_id) {
            return $this->jsonResponse(['status' => 0, 'message' => '票系未选择']);
        }
        if ($number < 1) {
            return $this->jsonResponse(['status' => 0, 'message' => '购买数量错误']);
        }
        if ($group_buy_id) {
            $group_buy = 2;
        }


        // 避免同一用户并发
        if (RedCache::get('userpay' . $uid)) {
            // 间隔时间过短  sleep or return
            return $this->jsonResponse(['status' => 0, 'message' => '下单频率过高，请稍后再试']);
        } else {
            RedCache::set('userpay' . $uid, time(), 2);
        }


        // 最大购买数 999
        if ($number > 999) {
            return $this->jsonResponse(['status' => 0, 'message' => '每个订单最大购买数为999']);
        }


        //活动详情
        $game_info = $this->_getPlayOrganizerGameTable()->get(['id' => $coupon_id]);

        if (!empty($use_account_money)) {
            if ($game_info->payment_type == 1) {
                return $this->jsonResponse(['status' => 0, 'message' => '该商品无法用余额支付']);
            }
        }

        //活动组织者详情
        $organizer_info = $this->_getPlayOrganizerTable()->get(['id' => $game_info->organizer_id]);

        //套系数据
        $game_data = $this->_getPlayGameInfoTable()->get(['id' => $game_info_id]);
        $data_game_price = $this->_getPlayGamePriceTable()->get(['id' => $game_data->pid]);

        if (!$game_info) {
            return $this->jsonResponse(['status' => 0, 'message' => '商品已删除或不存在']);
        }

        // 商品已下架

        if ($game_info->status == 0) {
            return $this->jsonResponse(['status' => 0, 'message' => '商品已下架']);
        }

        // 商品已删除
        if ($game_info->status == -1) {
            return $this->jsonResponse(['status' => 0, 'message' => '商品已删除']);
        }

        // 商品上架时间判断
        if (time() < $game_info->start_time) {
            return $this->jsonResponse(['status' => 0, 'message' => '时间未到']);
        }

        // 商品下架时间判断
        if (time() > $game_info->end_time) {
            return $this->jsonResponse(['status' => 0, 'message' => '时间已结束']);
        }

        // 套系开始售卖时间
        if ($game_data->up_time > time()) {
            return $this->jsonResponse(array('status' => 0, 'message' => '该商品还未开始'));
        }

        // 套系停止售卖时间
        if ($game_data->down_time < time()) {
            return $this->jsonResponse(array('status' => 0, 'message' => '该商品已结束购买'));
        }

        // 利用要提前预约来进行购买时间的限制
        if ($game_info->need_use_time == 2) {
            $data_book_hours = (int)($data_game_price->book_hours ? $data_game_price->book_hours : 0) * 86400;
            $data_book_time = strtotime(date("H:i", $data_game_price->book_time));

            if (time() + $data_book_hours - $data_book_time > $game_data->start_time) {
                return $this->jsonResponse(array('status' => 0, 'message' => '该商品已结束购买'));
            }
        }

        //如果团购则为团购价格
        $price = $group_buy ? $game_info->g_price : $game_data->price;

        if ($use_account_money) {
            $pdo = $this->_getAdapter();
            $sql = " SELECT uid, `status` FROM play_account WHERE uid = ? AND `password` = md5(CONCAT(md5(?), salt)) ";
            $data_account = $pdo->query($sql, array($uid, $pwd))->current();

            if (empty($data_account)) {
                return $this->jsonResponse(['status' => -1, 'message' => "账户密码错误"]);
            } else {
                if ($data_account->status == 0) {
                    return $this->jsonResponse(['status' => 0, 'message' => "用户账户已冻结"]);
                }
            }
        }

        if (strpos($_SERVER['HTTP_USER_AGENT'], 'client/3.3.') or strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            if ($game_data->qualified == 2) {

                $qualify_id = $this->_getQualifyTable()->get(array('uid' => $uid, 'pay_time' => 0, 'status' => 1, 'valid_time>' . time()));
                if ($qualify_id) {
                    $qualify_coupon_id = $qualify_id->id;
                } else {
                    return $this->jsonResponse(['status' => 0, 'message' => '此商品需要使用资格券才能购买']);
                }
            }
        }

        //现金券是否可以使用
        if ($cash_coupon_id) {
            $db = $this->_getAdapter();

            //获取支持的分类
            $link_label = array();
            if ($coupon_id) {
                $link_label_sql = "select play_label_linker.object_id,play_label.id,play_label.pid  from play_label_linker
left join play_label on play_label.id = play_label_linker.lid where play_label_linker.object_id = ?;";
                $link_label = $db->query($link_label_sql, array($coupon_id))->toArray();
            }
            $or = '';

            if ($link_label) {
                foreach ($link_label as $ll) {
                    $or .= ('b.object_id = ' . (int)$ll['id'] . ' or ');
                    $or .= ('b.object_id = ' . (int)$ll['pid'] . ' or ');
                }
            } else {
                $or = ('b.object_id = 0 or ');
            }

            $or = rtrim($or, ' or ');

            $ticket = $db->query("SELECT
	a.*
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
AND (
	is_main = 1
	OR (is_main = 0 AND c.city = ?)
)
AND a.id=?
AND a.use_stime <?
AND a.use_etime >?
GROUP BY
	a.cid
ORDER BY
	a.create_time DESC", array($coupon_id, $uid, $city, $cash_coupon_id, time(), time()))->current();

            if (!$ticket) {
                return $this->jsonResponse(['status' => 0, 'message' => '现金券不存在或不支持此商品']);
            }
            if ($ticket->use_stime > time()) {
                return $this->jsonResponse(['status' => 0, 'message' => '使用时间未到']);
            } elseif ($ticket->use_etime < time()) {
                return $this->jsonResponse(['status' => 0, 'message' => '优惠券使用时间已截止']);
            }


        }


        // 判断用户状态
        $user_data = $this->_getPlayUserTable()->get(['uid' => $uid]);
        if (!$user_data) {
            return $this->jsonResponse(['status' => 0, 'message' => '用户已删除或不存在']);
        } else {
            if ($user_data->status == 0) {
                return $this->jsonResponse(['status' => 0, 'message' => '用户已禁用']);
            }
        }


        if ($group_buy) {
            if ($game_info->g_buy == 0) {
                return $this->jsonResponse(['status' => 0, 'message' => '本商品暂不允许参团']);
            }
            // 判断是否组团过 是否加入过
            $user_g = $this->_getPlayOrderInfoTable()->tableGateway->select(function (Select $select) use ($uid, $coupon_id, $user_data, $client_id) {
                $select->join('play_order_info_game', 'play_order_info_game.order_sn=play_order_info.order_sn');
                $select->where(array("(user_id={$uid} or client_id='{$client_id}')", 'group_buy_id>0', '(pay_status=5 or pay_status=2 or pay_status=6)', 'order_status' => 1, 'coupon_id' => $coupon_id));
                $select->limit(1);
            })->current();


            if ($user_g) {
                return $this->jsonResponse(['status' => 0, 'message' => '您已经参加过此商品的团购了']);
            }


            // 加入团,判断团主是否支付
            if ($group_buy == 2) {
                $group_data = $this->_getPlayGroupBuyTable()->get(array('id' => $group_buy_id));
                if (!$group_buy_id) {
                    return $this->jsonResponseError("团购id未传递");
                }

                if ($group_data->join_number == 0) {
                    return $this->jsonResponse(['status' => 0, 'message' => '团主还未支付哦']);
                }
                if ($group_data->join_number == $group_data->limit_number) {
                    return $this->jsonResponse(array('status' => 0, 'message' => '这个团已经满员了哦！'));
                }

                if ($group_data->uid == $uid) {
                    return $this->jsonResponse(array('status' => 0, 'message' => '不能加入自己的团哦！'));
                }
            } else {
                if ((time() + 3600 * 2) > $game_info->down_time) {
                    return $this->jsonResponse(['status' => 0, 'message' => '距离商品结束2小时禁止开团']);
                }
            }
        }


        // 检查分享后才能抢  3.0 del
//        if (!$this->isWeiXin()) {
//            if ($game_info->share == 2) {
//                $is_share = $this->_getPlayShareTable()->get(['uid' => $uid, 'type' => 'game', 'share_id' => $coupon_id]);
//                if (!$is_share) {
//                    return $this->jsonResponse(['status' => 0, 'message' => '赶紧分享后再来抢吧!']);
//                }
//            }
//        }

        // 购买方式 购买方式 1客户端 微信 2客户端 3微信端
        if ($this->isWeiXin() and $game_info->buy_way == 2) {
            return $this->jsonResponse(['status' => 0, 'message' => '这个活动只有玩翻天APP才能参加哦，赶紧来下载客户端吧！']);
        }

        if (!$this->isWeiXin() and $game_info->buy_way == 3) {
            return $this->jsonResponse(['status' => 0, 'message' => '这个活动只有微信平台才能参加哦，赶紧去关注玩翻天公众号吧！']);
        }


        // 新用户专享
        if ($game_data->for_new == 1) {
            // $first = $this->_getPlayOrderInfoTable()->get(['user_id' => $uid, 'order_status' => 1]);
            $first = $this->_getPlayOrderInfoTable()->tableGateway->select(function (Select $select) use ($uid, $user_data, $client_id) {
                $select->join('play_order_info_game', 'play_order_info_game.order_sn=play_order_info.order_sn');
                $select->where("(user_id={$uid} or phone='{$user_data->phone}' or client_id='{$client_id}') and  order_status=1");
                $select->limit(1);
            })->current();

            if ($first) {
                return $this->jsonResponse(['status' => 0, 'message' => '只有新用户才能专享哦']);
            }
        }


        // 判断用户是否绑定手机号

        if (!$user_data->phone) {
            return $this->jsonResponse(['status' => 0, 'message' => '您还未绑定手机号,请绑定手机号后再来购买吧']);
        }

        if (!$name) {
            $name = $user_data->username;
        }
        if (!$phone) {
            $phone = $user_data->phone;
        }

        // 检查每机限购数
        if ($game_data->limit_num && !$group_buy && $game_info_id) {
            $user_buy_number = 0;

            if ($this->isWeiXin()) {
                $user_order_list = $this->_getPlayOrderInfoTable()->tableGateway->select(function (Select $select) use ($user_data, $uid, $coupon_id, $client_id, $game_data) {
                    $select->join('play_order_info_game', 'play_order_info_game.order_sn=play_order_info.order_sn');
                    $select->where(['user_id' => $uid, 'coupon_id' => $coupon_id, 'price_id' => $game_data->pid, 'order_status' => 1, 'order_type' => 2, 'play_order_info.group_buy_id' => 0]);
                });
            } else {
                $user_order_list = $this->_getPlayOrderInfoTable()->tableGateway->select(function (Select $select) use ($user_data, $uid, $coupon_id, $client_id, $game_data) {
                    $select->join('play_order_info_game', 'play_order_info_game.order_sn=play_order_info.order_sn');
                    $select->where(["(phone ='{$user_data->phone}' or user_id={$uid} or client_id='{$client_id}')", 'coupon_id' => $coupon_id, 'price_id' => $game_data->pid, 'order_status' => 1, 'order_type' => 2, 'play_order_info.group_buy_id' => 0]);
                });
            }
            foreach ($user_order_list as $v) {
                $user_buy_number += $v->buy_number;
            }

            if ($number > $game_data->limit_num or ($user_buy_number + $number) > $game_data->limit_num) {
                return $this->jsonResponse(['status' => 0, 'message' => "亲,每个手机只能购买{$game_data->limit_num}张票哦"]);
            }
        }


        //判断是否有足够积分
        if ($game_data->integral) {
            $integral_data = $this->_getPlayIntegralUserTable()->get(array('uid' => $uid));
            if (!$integral_data or $integral_data->total < $use_score) {
                return $this->jsonResponse(array('status' => 0, 'message' => '积分不足'));
            }
        }

        // 判断剩余产品数量
        $surplus = $game_data->total_num - $game_data->buy; //剩余
        if ($number > $surplus and $game_info->g_buy == 0) {
            return $this->jsonResponse(['status' => 0, 'message' => "对不起，优惠数量不够了，还剩{$surplus}个"]);
        }


        if ($group_buy == 1) {//组团
            if ($game_info->g_limit > $surplus) {
                return $this->jsonResponse(['status' => 0, 'message' => "对不起，优惠数量不够了，无法开团"]);
            }
        }

        // 判断单价是否小于1元
        /*if ($this->isWeiXin()) {
            if ($game_data->price <= 1) {
                return $this->jsonResponse(['status' => 0, 'message' => '价格小于等于1元的产品只能通过app购买哦']);
            }
        }*/


        //订单区分城市
        $order_city = $game_info->city;

        $insert_order = new InsertOrder();
        $res = $insert_order->insertOrder(
            $game_data->total_num,
            $number,
            $uid,
            $coupon_id,
            $game_info_id,
            $user_data->username,
            $user_data->phone,
            $address,
            $associates_ids,
            $price,
            $game_info->title,
            $organizer_info->id,
            $organizer_info->name,
            2,
            $game_data,
            $game_info,
            $name,
            $phone,
            $group_buy,
            $group_buy_id,
            $client_id,
            $cash_coupon_id, $qualify_coupon_id, $use_score, $message, $use_account_money, $order_city
        );

        return $this->jsonResponse($res);


    }

//    public function residualAmountPayAction () {
//        if (!$this->pass()) {
//            return $this->failRequest();
//        }
//
//        $param['uid']        = $this->getParams('uid',        0);       // 用户uid
//        $param['order_sn']   = $this->getParams('order_sn',   0);       // 订单的order_sn
//        $param['order_type'] = $this->getParams('order_type', 0);       // 订单的类型，1为商品，2为活动
//        $param['pwd']        = $this->getParams('pay_password', 0);     // 支付密码 错误返回-1
//        $param['client_id']  = $this->getParams('client_id', '');       // 设备id
//
//        // 避免同一用户并发
//        if (RedCache::get('userpay' . $param['uid'])) {
//            // 间隔时间过短  sleep or return
//            return $this->jsonResponse(['status' => 0, 'message' => '点击过快，请稍后再试']);
//        } else {
//            RedCache::set('userpay' . $param['uid'], time(), 2);
//        }
//
//        if (empty($param['uid']) || empty($param['order_sn']) || empty($param['order_type']) || empty($param['pwd'])) {
//            return $this->jsonResponseError('参数错误');
//        }
//
//        $data_order_info = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $param['order_sn']));
//
//        if (empty($data_order_info)) {
//            return $this->jsonResponseError('订单不存在');
//        }
//
//        $pdo          = $this->_getAdapter();
//        $sql          = " SELECT uid, `status` FROM play_account WHERE uid = ? AND `password` = md5(CONCAT(md5(?), salt)) ";
//        $data_account = $pdo->query($sql, array($param['uid'], $param['pwd']))->current();
//
//        if (empty($data_account)) {
//            return $this->jsonResponse(['status' => -1, 'message' => "账户密码错误"]);
//        } else {
//            if ($data_account->status == 0) {
//                return $this->jsonResponse(['status' => 0, 'message' => "用户账户已冻结"]);
//            }
//        }
//
//        if ($param['order_type'] == 1) {
//            // 商品支付
//            $data_order_info_game   = $this->_getPlayOrderInfoGameTable()->get(array('order_sn' => $param['order_sn']));
//            $class_order_pay        = new OrderPay();
//            $data_return_pay_status = $class_order_pay->paySuccess($data_order_info, $data_order_info_game, '', 'account', $param['uid']);
//
//            if ($data_return_pay_status) {
//                //判断是否有分享红包奖励
//                $class_invite           = new Invite();
//                $data_return_middleware = $class_invite->middleware($data_order_info, 1);
//                return $this->jsonResponse(['status' => 1, 'order_sn' => $param['order_sn'], 'group_buy_id' => $data_order_info['group_buy_id'], 'middleware' => $data_return_middleware]);
//            } else {
//                //订单生成成功,支付过程失败
//                return $this->jsonResponse(['status' => 0, 'message' => "账户支付失败"]);
//            }
//        } elseif ($param['order_type'] == 2) {
//            // 活动支付
//            $class_order_pay        = new OrderExcercisePay();
//            $data_return_pay_status = $class_order_pay->paySuccess($data_order_info, '', 'account', $param['uid']);
//
//            if ($data_return_pay_status) {
//                $class_invite           = new Invite();
//                $data_return_middleware = $class_invite->middleware($data_order_info, 1);
//                // middleware  0:不跳转中间页直接跳订单详情(默认)， 1:先跳中间页
//                return $this->jsonResponse(['status' => 1, 'order_sn' => $param['order_sn'], 'middleware' => $data_return_middleware]);
//            } else {
//                //订单生成成功,支付过程失败
//                return $this->jsonResponse(['status' => 0, 'message' => "账户支付失败"]);
//            }
//        }
//    }


    //购买购补录出行人接口
    public function addAssociatesAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = $this->getParams('uid', 0);
        $order_sn = $this->getParams('order_sn', 0); //订单id
        $associates_ids = json_decode($this->getParams('associates_ids', null, false), true); //出行人

        $order_data = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn, 'user_id' => $uid));

        $order_game = $this->_getPlayOrderInfoGameTable()->get(array('order_sn' => $order_sn));

        $gameInfo = $this->_getPlayGameInfoTable()->get(array('id' => $order_game->game_info_id));


        if (!$order_data or !$order_game or !$gameInfo) {
            return $this->jsonResponseError('数据不存在');
        }
        //更新已生成订单的出行人(生成保单)
        if (is_array($associates_ids)) {
            $ins_num = $this->_getPlayOrderInsureTable()->fetchCount(array('order_sn' => $order_sn));
            $this->_getPlayOrderInsureTable()->delete(array('order_sn' => $order_sn));
            $adapter = $this->_getAdapter();
            $insure_data = '';
            for ($i = 0; $i < count($associates_ids); $i++) {
                $associates_info = $adapter->query("select * from play_user_associates WHERE associates_id=?", array($associates_ids[$i]))->current();
                $insure_status = 1;
                if (!$associates_info) {
                    $insure_status = 0;
                }

                $insure_data .= "('{$order_sn}','{$order_data->coupon_id}','{$associates_info->name}',
                            '{$associates_info->sex}','{$associates_info->birth}','{$associates_info->id_num}',1,'','','{$insure_status}',{$associates_ids[$i]},'{$gameInfo->insure_days}'),";
            }

            if ($ins_num > count($associates_ids)) {
                for ($i = 0; $i < ($ins_num - count($associates_ids)); $i++)
                    $insure_data .= "('{$order_sn}','{$order_data->coupon_id}','','','','',1,'','','0',0,'{$gameInfo->insure_days}'),";
            }

            $insure_data = substr($insure_data, 0, -1);

            if (!empty($insure_data)) {
                $stmt = $adapter->query('INSERT INTO play_order_insure (order_sn,coupon_id,`name`,sex,birth,id_num,insure_company_id,insure_sn,baoyou_sn,insure_status,associates_id,product_code) VALUES ' . $insure_data);
                $s = $stmt->execute($stmt)->count();


                if (!$s) {
                    return $this->jsonResponseError('更新失败');
                }
            }
        } else {
            return $this->jsonResponseError('参数错误');
        }


        return $this->jsonResponse(['status' => 1, 'message' => "操作成功"]);
    }

    //更新订单相关数据
    public function updateOrderAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $user_cash_id = (int)$this->getParams('cash_coupon_id', 0); //新现金券id
        $uid = $this->getParams('uid', 0);
        $order_sn = $this->getParams('order_sn', 0); //订单id

        $name = $this->getParams("name", ' ');//获取购买者姓名
        $phone = $this->getParams("phone");//获取购买者手机号
        $address = $this->getParams('address'); //购买者地址
        $associates_ids = json_decode($this->getParams('associates_ids', null, false), true); //出行人

        $use_account_money = $this->getParams('use_account_money', 0); //使用账户金额付款  只有当账户金额足够时
        $pwd = $this->getParams('pay_password', 0);// 支付密码 错误返回-1  支付成功返回2  普通返回1

        $order_type = (int)$this->getParams('order_type', 1); // 1为商品，2为活动

        $order_data = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn, 'user_id' => $uid));


        if (!$uid or !$order_sn) {
            return $this->jsonResponseError('参数错误');
        }

//        //todo  处理ios bug
//        if ($user_cash_id and $order_data->voucher_id == $user_cash_id) {
//            return $this->jsonResponse(array('status' => 1, 'message' => '操作成功', 'order_sn' => $order_sn, 'group_buy_id' => $order_data->group_buy_id));
//        }

        // 更新已生成订单的联系人(寄包裹等)
        if ($name and $phone and $address) {
            $s = $this->_getPlayOrderInfoTable()->update(array('buy_name' => $name, 'buy_phone' => $phone, 'buy_address' => $address), array('order_sn' => $order_sn));
            if (!$s) {
                return $this->jsonResponseError('更新联系人失败');
            }
        }

        //更新已生成订单的出行人(生成保单)
        if (is_array($associates_ids) and !empty($associates_ids)) {
            $ins_num = $this->_getPlayOrderInsureTable()->fetchCount(array('order_sn' => $order_sn));
            $this->_getPlayOrderInsureTable()->delete(array('order_sn' => $order_sn));
            $adapter = $this->_getAdapter();
            $insure_data = '';


            $order_game = $this->_getPlayOrderInfoGameTable()->get(array('order_sn' => $order_sn));
            $gameInfo = $this->_getPlayGameInfoTable()->get(array('id' => $order_game->game_info_id));


            for ($i = 0; $i < count($associates_ids); $i++) {
                $associates_info = $adapter->query("select * from play_user_associates WHERE associates_id=?", array($associates_ids[$i]))->current();
                $insure_data .= "('{$order_sn}','{$order_data->coupon_id}','{$associates_info->name}',
                            '{$associates_info->sex}','{$associates_info->birth}','{$associates_info->id_num}',1,'','','1',{$associates_ids[$i]},'{$gameInfo->insure_days}'),";
            }

            if ($ins_num > count($associates_ids)) {
                for ($i = 0; $i < ($ins_num - count($associates_ids)); $i++)
                    $insure_data .= "('{$order_sn}','{$order_data->coupon_id}','','','','',1,'','','0',0,'{$gameInfo->insure_days}'),";
            }

            $insure_data = substr($insure_data, 0, -1);
            if (!empty($insure_data)) {
                //order_sn,coupon_id,`name`,sex,birth,id_num,insure_company_id,insure_sn,baoyou_sn,insure_status,associates_id,product_code
                $stmt = $adapter->query('INSERT INTO play_order_insure (order_sn,coupon_id,`name`,sex,birth,id_num,insure_company_id,insure_sn,baoyou_sn,insure_status,associates_id,product_code) VALUES ' . $insure_data);
                $s = $stmt->execute($stmt)->count();
                if (!$s) {
                    return $this->jsonResponseError('还原失败');
                }
            }
        }


        //订单原金额
        $real_pay = bcadd($order_data->real_pay, $order_data->voucher, 2); //订单金额

        $cash_price = 0;

        if ($user_cash_id and $order_data->voucher_id == $user_cash_id) {
            //与原现金券相同
        } elseif ($user_cash_id) {
            //新现金券

            //放弃已选择现金券则
            if ($order_data->voucher_id) {
                $use_cashCoupon = $this->_getCashCouponUserTable()->update(array('pay_time' => 0, 'use_order_id' => 0, 'use_object_id' => 0), array('id' => $order_data->voucher_id, 'uid' => $uid));
                if (!$use_cashCoupon) {
                    return $this->jsonResponseError('还原失败');
                }
            }

            //更新现金券
            $cash_info = $this->_getCashCouponUserTable()->get(array('id' => $user_cash_id));

            if (!$order_sn or !$cash_info) {
                return $this->jsonResponseError('数据不存在');
            }

            if ($order_data->pay_status >= 2 or $order_data->order_status == 0) {
                return $this->jsonResponseError('订单状态错误');
            }
            if ($order_data->account_money > 0) {
                return $this->jsonResponseError('请联系管理员'); //  暂时只针对完全使用账户支付的订单,后期有改动需修改底部计算订单金额,建议直接添加取消订单按钮
            }

            //判断新现金券是否可以使用  金额问题
            if ($cash_info->pay_time != 0) {
                return $this->jsonResponseError('现金券已使用');
            } elseif ($cash_info->use_stime > time() and $cash_info->use_etime < time()) {
                return $this->jsonResponseError('使用时间未开始或已过期');
            }
            if ($real_pay <= $cash_info->price) {
                return $this->jsonResponseError('现金券金额不能超过订单金额');
            }

            //更新现金券为已使用
            $use_cashCoupon = $this->_getCashCouponUserTable()->update(array('pay_time' => time(), 'use_order_id' => $order_sn, 'use_object_id' => $order_data->coupon_id), array('id' => $user_cash_id, 'uid' => $uid));
            if (!$use_cashCoupon) {
                return $this->jsonResponseError('现金券使用失败');
            }
            $cash_price = $cash_info->price;

            //更新订单金额
            $order = $this->_getPlayOrderInfoTable()->update(array('voucher_id' => $user_cash_id, 'voucher' => $cash_price, 'real_pay' => bcsub($real_pay, $cash_price, 2)), array('order_sn' => $order_sn));

            if (!$order) {
                return $this->jsonResponseError('现金券更新失败');
            }


        } else {

            //放弃已选择现金券则
            if ($order_data->voucher_id) {
                $use_cashCoupon = $this->_getCashCouponUserTable()->update(array('pay_time' => 0, 'use_order_id' => 0, 'use_object_id' => 0), array('id' => $order_data->voucher_id, 'uid' => $uid));
                if (!$use_cashCoupon) {
                    return $this->jsonResponseError('还原失败');
                }
            }

            //更新订单金额
            $order = $this->_getPlayOrderInfoTable()->update(array('voucher_id' => $user_cash_id, 'voucher' => $cash_price, 'real_pay' => bcsub($real_pay, $cash_price, 2)), array('order_sn' => $order_sn));

            if (!$order && $order_data->voucher_id) {
                return $this->jsonResponseError('现金券更新失败');
            }
        }

        //账户支付
        if ($use_account_money) {//使用账户余额支付

//            //验证密码
//            $account_data = $this->_getPlayAccountTable()->get(array('uid' => $uid));
//            if (!$account_data or $account_data->status == 0) {
//                return $this->jsonResponse(['status' => 0, 'message' => "用户账户已冻结"]);
//            }
//            if (md5(md5($pwd) . $account_data->salt) !== $account_data->password) {
//                return $this->jsonResponse(['status' => -1, 'message' => "账户密码错误"]);
//            }
//
//
//            $order_info = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));
//            $order_info_game = $this->_getPlayOrderInfoGameTable()->get(array('order_sn' => $order_sn));
//            $order_pay = new OrderPay();
//            $pay_status = $order_pay->paySuccess($order_info, $order_info_game, '', 'account', $uid);
//
//            if ($pay_status) {
//                return $this->jsonResponse(['status' => 2, 'order_sn' => $order_sn, 'group_buy_id' => $order_data->group_buy_id]);
//            } else {
//                return $this->jsonResponse(['status' => 0, 'message' => "账户支付失败"]);
//            }

            $pdo = $this->_getAdapter();
            $sql = " SELECT uid, `status` FROM play_account WHERE uid = ? AND `password` = md5(CONCAT(md5(?), salt)) ";
            $data_account = $pdo->query($sql, array($uid, $pwd))->current();

            if (empty($data_account)) {
                return $this->jsonResponse(['status' => -1, 'message' => "账户密码错误"]);
            } else {
                if ($data_account->status == 0) {
                    return $this->jsonResponse(['status' => 0, 'message' => "用户账户已冻结"]);
                }
            }

            if ($order_type == 1) {
                // 商品支付
                $data_order_info_game = $this->_getPlayOrderInfoGameTable()->get(array('order_sn' => $order_sn));
                $class_order_pay = new OrderPay();
                $data_return_pay_status = $class_order_pay->paySuccess($order_data, $data_order_info_game, '', 'account', $uid);

                if ($data_return_pay_status) {
                    //判断是否有分享红包奖励
                    $class_invite = new Invite();
                    $data_return_middleware = $class_invite->middleware($order_data, 1);
                    return $this->jsonResponse(['status' => 2, 'order_sn' => $order_sn, 'group_buy_id' => $order_data->group_buy_id, 'middleware' => $data_return_middleware]);
                } else {
                    //订单生成成功,支付过程失败
                    return $this->jsonResponse(['status' => 0, 'message' => "账户支付失败"]);
                }
            } elseif ($order_type == 2) {
                // 活动支付
                $class_order_pay = new OrderExcercisePay();
                $data_return_pay_status = $class_order_pay->paySuccess($order_data, '', 'account', $uid);

                if ($data_return_pay_status) {
                    $class_invite = new Invite();
                    $data_return_middleware = $class_invite->middleware($order_data, 1);
                    // middleware  0:不跳转中间页直接跳订单详情(默认)， 1:先跳中间页
                    return $this->jsonResponse(['status' => 2, 'order_sn' => $order_sn, 'middleware' => $data_return_middleware]);
                } else {
                    //订单生成成功,支付过程失败
                    return $this->jsonResponse(['status' => 0, 'message' => "账户支付失败"]);
                }
            }

        }

        return $this->jsonResponse(array('status' => 1, 'message' => '操作成功', 'order_sn' => $order_sn, 'group_buy_id' => $order_data->group_buy_id));
    }

    //生成参数调起支付
    public function alipayAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }
        $order_sn = (int)$this->getParams('order_sn');


        $pay_type = (int)$this->getParams('paytype', '1'); //支付类型  1 alipay  2 union


        $order_info = $this->_getPlayOrderInfoTable()->get(['order_sn' => $order_sn]);

        if (!$order_info) {
            return $this->jsonResponse(['status' => 0, 'message' => '订单不存在']);
        }
        if ($order_info->pay_status >= 2) {
            return $this->jsonResponse(['status' => 0, 'message' => '订单已付款，请刷新订单列表']);
        }
        if ($order_info->order_status != 1) {
            return $this->jsonResponse(['status' => 0, 'message' => '你已经超过付款时间哦,请重新下单后再来付款']);
        }

        if ($order_info->group_buy_id != 0) {
            //利用mysql原子操作
            $group_data = $this->_getPlayGroupBuyTable()->get(array('id' => $order_info->group_buy_id));
            if ($group_data['join_number'] >= $group_data['limit_number']) {
                return $this->jsonResponse(array('status' => 0, 'message' => '这个团已经满员了哦！'));
            }
            if ($group_data['status'] != 1) {
                return $this->jsonResponse(array('status' => 0, 'message' => '这个团已过期或满员了哦！'));
            }
        }
        //测试服务器
        /*if (WriteLog::isUp()) {
            $pay_order_sn = 'WFT' . $order_sn;
            $order_name = '玩翻天-' . $order_info->coupon_name . "-{$order_info->coupon_unit_price}";
        } else {
            $pay_order_sn = 'TESTWFT' . $order_sn;
            $order_name = 'TEST_玩翻天-' . $order_info->coupon_name . "-{$order_info->coupon_unit_price}";
        }*/

        if (!Common::isUp()) {
            $pay_order_sn = 'TESTwanWFT' . $order_sn;
            $order_name = 'TEST玩翻天-' . $order_info->coupon_name . "-{$order_info->real_pay}";
        } else {
            $pay_order_sn = 'WFT' . $order_sn;
            $order_name = '玩翻天-' . $order_info->coupon_name . "-{$order_info->real_pay}";
        }

        $invite = new Invite();
        $middleware = $invite->middleware($order_info, 1);
        if ($pay_type == 1) {

            $alipay = new Alipay();
            $params = $alipay->alipay($pay_order_sn, $order_name, $order_info->real_pay, $order_info->coupon_name);
            CancelOrder::Cancel($order_sn);
            return $this->jsonResponse(['status' => 1, 'params' => $params, 'order_sn' => $order_sn, 'middleware' => $middleware]);

        } elseif ($pay_type == 2) {
            $union = new Unionpay();
            //回调时原样返回json  产品id 产品名称 数量 单价
            $reqReserved_json = json_encode([
                'coupon_id' => $order_info->coupon_id,
                'coupon_name' => $order_info->coupon_name,
                'buy_number' => $order_info->buy_number,
            ], JSON_UNESCAPED_UNICODE);
            $params = $union->unionpay($pay_order_sn, $order_info->real_pay, $reqReserved_json);

            if (!isset($params['tn'])) {
                if (!$params) {
                    return $this->jsonResponse(['status' => 0, 'message' => '请求失败，请稍候再试！']);
                }
                return $this->jsonResponse(['status' => 0, 'message' => $params['respMsg']]);
            }

            //CancelOrder::Cancel($order_sn);
            return $this->jsonResponse(['status' => 1, 'params' => ['tn' => $params['tn']], 'order_sn' => $order_sn, 'middleware' => $middleware]);
        } elseif ($pay_type == 3) {

            $weiPay = new weiPay();
            $params = $weiPay->weiPay($pay_order_sn, $order_info->real_pay, $order_name);

            if (!$params) {
                return $this->jsonResponse(['status' => 0, 'message' => '请求失败，请稍候再试！']);
            }
            //CancelOrder::Cancel($order_sn);
            return $this->jsonResponse(['status' => 1, 'params' => $params, 'order_sn' => $order_sn, 'middleware' => $middleware]);

        } elseif ($pay_type == 4) {

            $weiXinWap = new WeiXinPayFun($this->getwxConfig());
            $open_id = $this->getParams('open_id');
            $respOb = $weiXinWap->weixinPay($open_id, $order_name, $pay_order_sn, $order_info->real_pay);

            if (!$respOb->prepay_id) {
                Logger::writeLog('微信wap支付测试：' . print_r($respOb, true) . "\r\n");
                return $this->jsonResponse(['status' => 0, 'message' => '生成预支付订单失败']);
            }

            $timer = time();
            $payData = array(
                'appId' => $this->getwxConfig()['appid'],
                'timeStamp' => "$timer",
                'nonceStr' => $weiXinWap::getNonceStr(),//随机字符串
                'package' => 'prepay_id=' . $respOb->prepay_id,
                'signType' => "MD5",
            );
            $payData['paySign'] = $weiXinWap->getPaySignature($payData);
            // CancelOrder::Cancel($order_sn);
            return $this->jsonResponse(array('pay_data' => $payData, 'orderData' => $order_info, 'status' => 1, 'middleware' => $middleware));

        } else {
            return $this->jsonResponse(['status' => 0, 'message' => '支付类型错误']);
        }

    }

    //待支付订单进入收银台页面
    public function noPayInfoAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }
        $order_sn = $this->getParams('order_sn');


        if (!$order_sn) {
            return $this->jsonResponseError('参数错误');
        }


        $order_data = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));

        $game_data = $this->_getPlayOrderInfoGameTable()->get(array('order_sn' => $order_sn));

        $game_info = $this->_getPlayGameInfoTable()->get(array('id' => $game_data->game_info_id));
        if (!$order_data) {
            return $this->jsonResponseError('订单不存在');
        }
        if ($order_data->pay_status > 1 or $order_data->order_status == 0) {
            //已支付或其他状态
            return $this->jsonResponse(array('status' => 0, 'message' => '订单状态异常'));
        }


        $res = array(
            'order_sn' => $order_sn,
            'money' => $order_data->real_pay,
            'title' => $order_data->coupon_name,
            'attend_start_time' => $game_info->start_time,//出行开始时间
            'attend_end_time' => $game_info->end_time,//出行结束时间
            'attend_address' => $game_info->shop_name, //游玩地址
        );

        //联系人
        $res['linker_name'] = empty($order_data->buy_name) ? $order_data->username : $order_data->buy_name;
        $res['linker_phone'] = empty($order_data->buy_phone) ? $order_data->phone : $order_data->buy_phone;
        $res['linker_addr'] = empty($order_data->buy_address) ? '' : $order_data->buy_address;

        //出行人
        $orderInsure = $this->_getPlayOrderInsureTable();
        $associates_info = $orderInsure->fetchAll(array('order_sn' => $order_sn, 'insure_status>=1'))->toArray();
        $res['associates'] = $associates_info;


        return $this->jsonResponse($res);

    }


    //提交退款
    public function backpayAction()
    {

        if (!$this->pass()) {
            return $this->failRequest();
        }


        $order_sn = $this->getParams('order_sn');
        $password = $this->getParams('password');

        if (!$order_sn or !$password) {
            return $this->jsonResponseError('请求参数错误');
        }

        $id = substr($password, 0, -7);

        if (!$id || strlen($id) < 4) {
            $codeData = $this->_getPlayCouponCodeTable()->get(array('order_sn' => $order_sn, 'status' => 0));

            if (!$codeData) {
                return $this->jsonResponse(array('status' => 0, 'message' => '退款失败'));
            }
            $password = $codeData->id . $codeData->password;
        }

        $data = $this->backIng($order_sn, $password);

        return $this->jsonResponse($data);

    }

    //原路返回到原支付账户 //临时
    public function backcardAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $order_sn = $this->getParams('order_sn');
        $password = $this->getParams('password');

        if (!$order_sn or !$password) {
            return $this->jsonResponseError('请求参数错误');
        }

        $id = (int)substr($password, 0, -7);

        $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));
        $codeData = $this->_getPlayCouponCodeTable()->get(array('id' => $id));

        if (!$orderData || !$codeData) {
            return $this->jsonResponse(array('status' => 0, 'message' => '出现了异常, 请询问客服'));
        }

        $db = $this->_getAdapter();

        $s = $db->query("select * from play_order_back_tmp where order_sn=? AND code_id=?", array($order_sn, $id))->current();

        if (!$s || $s->status != 1) {
            return $this->jsonResponse(array('status' => 0, 'message' => '异常'));
        }

        //判断用户账号可提现金额 是否大于 退款金额
        $userAccountData = $this->_getPlayAccountTable()->get(array('uid' => $orderData->user_id));

        if (!$userAccountData || $userAccountData->can_back_money < $codeData->back_money) {
            return $this->jsonResponse(array('status' => 0, 'message' => '账户中余额不足'));
        }

        $account = new Account();

        $chargeStatus = $account->takeCrash($orderData->user_id, $codeData->back_money, $desc = '原路返回支付账户', 2, $order_sn, $orderData->user_id, $orderData->order_city);

        if (!$chargeStatus) {
            return $this->jsonResponse(array('status' => 0, 'message' => '失败'));
        }

        $res = $db->query('UPDATE play_order_back_tmp SET status=?, dateline = ? WHERE order_sn=? AND code_id=?', array(2, time(), $order_sn, $id))->count();

        if ($res) {
            return $this->jsonResponse(array('status' => 1, 'message' => '资金会在7个工作日内返回您的支付账户'));
        } else {
            return $this->jsonResponse(array('status' => 0, 'message' => '失败啊'));
        }
    }

    //未支付,直接取消订单
    public function cleanorderAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }
        $order_sn = $this->getParams('order_sn');


        if (!$order_sn) {
            return $this->jsonResponseError('参数错误');
        }

        $data = \library\Service\Order\CancelOrder::CancelOrder($order_sn);
        return $this->jsonResponse($data);

    }

    //支付方式+现金券
    public function checkstandAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $param['uid'] = (int)$this->getParams('uid', '');         // 用户uid
        $param['page'] = (int)$this->getParams('page', 1);         // 现金券页数
        $param['limit'] = (int)$this->getParams('pagenum', 5);      // 单页条数
        $param['coupon_id'] = (int)$this->getParams('coupon_id', '');   // 卡券id，活动id
        $param['info_id'] = (int)$this->getParams('info_id', 0);      // 套系id，场次id
        $param['type'] = (int)$this->getParams('type', 1);         // 商品类别 1商品 2活动
        $param['pay_price'] = (float)$this->getParams('pay_price', 0);  // 要支付的金额
        $param['need_cashcoupon_list'] = (int)$this->getParams('need_cashcoupon_list', 1);  // 是否需要现金券列表
        $param['need_method'] = (int)$this->getParams('need_method', 1);           // 是否需要支付方式
        $param['need_residual_amount'] = (int)$this->getParams('need_residual_amount', 1);  // 是否需要获取余额
        $param['order_id'] = (int)$this->getParams('order_id', 0);              // 待支付的订单

        if (empty($param['coupon_id'])) {
            if ($param['type'] == 1) {
                return $this->jsonResponseError('商品不存在');
            } elseif ($param['type'] == 2) {
                return $this->jsonResponseError('活动不存在');
            }
        }

        if (empty($param['info_id']) && $param['need_cashcoupon_list'] == 1) {
            if ($param['type'] == 1) {
                return $this->jsonResponseError('商品套系不存在');
            } elseif ($param['type'] == 2) {
                return $this->jsonResponseError('活动场次不存在');
            }
        }

        // 现金券列表
        if ($param['need_cashcoupon_list'] == 1) {
            // 现金券列表参数初始化
            $param_cashcoupon['uid'] = $param['uid'];
            $param_cashcoupon['page'] = $param['page'];
            $param_cashcoupon['limit'] = $param['limit'];
            $param_cashcoupon['coupon_id'] = $param['type'] == 1 ? $param['coupon_id'] : $param['info_id'];
            $param_cashcoupon['info_id'] = $param['info_id'];
            $param_cashcoupon['pay_price'] = $param['pay_price'];
            $param_cashcoupon['type'] = $param['type'];

            $data_return = array();

            $class_coupon = new Coupon();
            $data_return['cashcoupon_list'] = $class_coupon->nmy($param_cashcoupon['uid'], $param_cashcoupon['page'], $param_cashcoupon['limit'], $param_cashcoupon['info_id'], $param_cashcoupon['coupon_id'], $param_cashcoupon['pay_price'], $param_cashcoupon['type']);
        }

        // 支付方式限制
        if ($param['need_method'] == 1) {
            if ($param['type'] == 2) {
                $data_return['method'] = 0;
            } else {
                $data_organizer_game = $this->_getPlayOrganizerGameTable()->get(array('id' => $param['coupon_id']));

                $data_return['method'] = $data_organizer_game['payment_type'];
            }
        }

        if ($param['need_residual_amount'] == 1) {
            $class_account = new Account();
            $data_residual_amount = $class_account->getUserMoney($param['uid']);

            $data_return['residual_amount'] = $data_residual_amount;
        }

        if ($param['type'] == 2) {
            $service_kidsplay = new Kidsplay();
            if ($param['order_id']) {
                $pdo = M::getAdapter();
                $data_temp_kidsplay_price = $pdo->query('SELECT play_excercise_price.*,count(play_excercise_code.pid) as buy_num FROM play_excercise_code LEFT JOIN play_excercise_price ON play_excercise_code.pid = play_excercise_price.id WHERE play_excercise_code.order_sn = ? GROUP BY play_excercise_code.pid', array($param['order_id']))->toArray();
            } else {
                $data_temp_kidsplay_price = $service_kidsplay->getKidsplayPrice(array(
                    'free_coupon_need_count > 0',
                    '(free_coupon_max_count = 0 OR free_coupon_max_count > free_coupon_join_count)',
                    'eid' => $param['info_id'],
                    'is_other' => 0,
                    'is_close' => 0
                ));
            }

            $data_kidsplay_price = array();
            $data_event          = M::getPlayExcerciseEventTable()->get(array('id' => $param['info_id']));

            foreach ($data_temp_kidsplay_price as $key => $val) {
                $data_count_price_user_buy = $service_kidsplay->getCountPriceBuy($param['uid'], $val['id']);
                $data_count_price_user_free = $service_kidsplay->getCountPriceFreeBuy($param['uid'], $val['id']);
                $data_count_other_price_user_free = $service_kidsplay->getCountPriceDayFreeBuy($param['uid'], strtotime(date('Y-m-d 00:00:00', $data_event['start_time'])), strtotime(date('Y-m-d 23:59:59', $data_event['end_time'])));
                $data_count_price_user_free_in_order = $service_kidsplay->getCountPriceFreeBuyInOrder($param['order_id'], $val['id']);

                $data_kidsplay_price[] = array(
                    'id'                          => $val['id'],
                    'title'                       => $val['price_name'],
                    'joined_num'                  => $val['buy_number'],
                    'residue_num'                 => ($val['least'] - $val['buy_number']) < 0 ? $val['least'] - $val['buy_number'] : 0,
                    'price'                       => $val['price'],
                    'max_buy'                     => $val['most'] == 0 ? 999 : $val['most'],
                    'min_buy'                     => $val['least'],
                    'people_number'               => (int)($val['person_ault'] + $val['person_child']) ? : 1,
                    'my_buy'                      => (int)$data_count_price_user_buy,
                    'free_number'                 => (int)$val['free_coupon_max_count'],
                    'free_used_number'            => (int)$val['free_coupon_join_count'],
                    'need_free_coupon_number'     => (int)$val['free_coupon_need_count'],
                    'most_free_buy_number'        => 1,
                    'my_free_buy_number'          => (int)$data_count_price_user_free,
                    'my_free_buy_same_day_number' => (int)$data_count_other_price_user_free,
                    'free_buy_number'             => (int)$data_count_price_user_free_in_order,
                    'buy_number'                  => (int)$val['buy_num'],
                );
            }

            // 获取可以免费玩的收费项
            $data_return['members'] = $data_kidsplay_price;

            // 获取用户剩余免费玩资格券数量
            $service_member = new Member();
            $data_member = $service_member->getMemberData($param['uid']);
            $data_return['my_free_coupon_number'] = (int)$data_member['member_free_coupon_count_now'];

            // 活动参加当天，每个用户最多免费玩次数
            $data_return['most_free_buy_same_day_number'] = 1;
        }

        return $this->jsonResponse($data_return);
    }

    public function shareAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $param['uid'] = (int)$this->getParams('uid', 0); // 用户uid


        $service_member = new Member();
        $data_member_param = array(
            'member_user_id' => $param['uid'],
        );
        $data_member = $service_member->getMemberData($data_member_param['member_user_id']);

        $data_share_param['share_city'] = $this->getCity();
        $data_share = Share::getShareData($data_share_param);

        if ($data_share['share_title']) {
            $data_share['share_title'] = json_decode($data_share['share_title'], true);
            $data_share['share_content'] = json_decode($data_share['share_content'], true);

            $data_content_count = count($data_share['share_title']);
        }

        if ($data_content_count > 0) {
            $n = time() % $data_content_count;
        } else {
            $n = 0;
        }

        $data_return = array(
            'share' => (int)$data_share['share_status'],
            'lottery_number' => 5,
            'free_coupon_number' => (int)$data_member['member_free_activation_coupon_count'],
            'image_normal_recharge' => $this->getImgUrl('/uploads/2016/11/17/97f7927ab430fe512a4dfe93f4f7b10b.jpg'),
            'image_member_recharge' => $this->getImgUrl('/uploads/2016/11/17/c3fc7ce7d20b0db616466d0b8f574939.jpg'),   // 会员充值的分享图片
            'image_pay_success' => $this->getImgUrl('/uploads/2016/11/17/97f7927ab430fe512a4dfe93f4f7b10b.jpg'),   // 支付成功和普通充值的分享图片
            'jump_url' => $this->getShareUrl($data_share['share_url'], $param['uid']),
            'share_title' => $data_share['share_title'][$n],
            'share_content' => $data_share['share_content'][$n],
            'share_img' => $this->getImgUrl($data_share['share_img']),
            'share_url' => $this->getShareUrl($data_share['share_url'], $param['uid']),
            'lottery_id' => 5,
        );

        return $this->jsonResponse($data_return);
    }

    private function getShareUrl($url, $uid)
    {
        $data_array_share_url = explode('?', $url);

        if ($data_array_share_url[1]) {
            $data_array_share_url[1] .= '&';
        } else {
            $data_array_share_url[1] = '';
        }

        $data_array_share_url[1] .= 'share_user_id=' . $uid;


        return $data_array_share_url[0] . '?' . $data_array_share_url[1];
    }

    // 支付方式限制
    public function methodAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $param['id'] = $this->getParams('id', '');   // 商品id 或 活动id
        $param['type'] = $this->getParams('type', 0);  // id的类型 0为商品，1为活动
        $param['uid'] = $this->getParams('uid', '');  // 用户uid

        if (empty($param['id'])) {
            if ($param['type'] == 0) {
                return $this->jsonResponseError('商品不存在');
            } elseif ($param['type'] == 1) {
                return $this->jsonResponseError('活动不存在');
            }
        }

        if ($param['type'] == 1) {
            $data_return = array(
                'id' => $param['id'],
                'type' => $param['type'],
                'method' => 0,
            );
            return $this->jsonResponse($data_return);
        }

        $data_organizer_game = $this->_getPlayOrganizerGameTable->get(array('id' => $param['id']));

        $data_return = array(
            'id' => $param['id'],
            'type' => $param['type'],
            'method' => $data_organizer_game['payment_type'],
        );
        return $this->jsonResponse($data_return);
    }

    /**
     * 判断是否微信请求
     * @return bool
     */
    public function isWeiXin()
    {
        if (isset($_COOKIE['open_id']) and $_COOKIE['open_id']) {
            return true;
        } else {
            return false;
        }
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
}
