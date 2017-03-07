<?php

/**
 * 使用验证码
 */
namespace Deyi\OrderAction;

use Deyi\Account\Account;
use Deyi\Account\OrganizerAccount;
use Deyi\Coupon\Coupon;
use Deyi\Integral\Integral;
use library\Service\System\Cache\RedCache;
use Deyi\Seller\Seller;
use Deyi\SendMessage;
use Zend\Db\Sql\Predicate\Expression;
use Deyi\Invite\Invite;

trait UseCode
{
    // use BaseController;

    /**
     * @param $store_id |使用者id
     * @param $type |使用者类型  1游玩地 2商家 3后台编辑使用
     * @param $code |验证码
     * @return Array    |使用情况 array('status' => 0, 'message' => "订单不存在")
     */
    public function UseCode($store_id, $type, $code)
    {
        //店铺id
//        $store_id = (int)$_COOKIE['id'];
//        $type = (int)$_COOKIE['type']; //1 店铺 2活动组织者（商家）
//        $code = $this->getPost('name');

        // 正确使用
        $id = substr($code, 0, -7);
        $password = substr($code, -7);

        //当前验证码数据
        $coupon_code_data = $this->_getPlayCouponCodeTable()->get(array('password' => $password, 'id' => $id));
        if (!$coupon_code_data) {
            return array('status' => 0, 'message' => "验证码不存在");
        }

        if ($coupon_code_data->status != 0) {
            if ($coupon_code_data->use_datetime) {
                $use_data = date('Y-m-d H:i:s', $coupon_code_data->use_datetime);
            } else {
                $use_data = '退款中';
            }
            return array('status' => 0, 'message' => "验证码已使用或已退订,使用时间为:" . $use_data);
        }

        //查询订单数据
        //$order_data = $this->_getPlayOrderInfoTable()->getUserBuy(array('order_sn' => $coupon_code_data->order_sn));
        $order_data = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $coupon_code_data->order_sn));

        $adapter = $this->_getAdapter();
        $zyb_data = $adapter->query("select * from play_zyb_info WHERE status = 5 AND order_sn=? AND code_id=?", array($coupon_code_data->order_sn, $id))->current();

        if ($zyb_data && $zyb_data->status != 5) {
            return array('status' => 0, 'message' => "非法操作");
        }

        if ($zyb_data && $type == 3) {
            return array('status' => 0, 'message' => "智游宝的暂不支持后台验证使用");
        }

        if (!$order_data) {
            return array('status' => 0, 'message' => "订单不存在");
        }

        if ($order_data->pay_status < 2) {
            return array('status' => 0, 'message' => "订单未付款");
        }

        if ($coupon_code_data->status >= 1) {
            return array('status' => 0, 'message' => "卡券" . $this->_getConfig()['coupon_code_status'][$coupon_code_data->status]);
        }

        if ($order_data->order_type == 1) {//普通票
            return array('status' => 0, 'message' => "本卡券太久远了, 请联系客服处理");
        }

        if (!in_array($type, array(2, 3))) {
            return array('status' => 0, 'message' => "请换个方式验证");
        }

        //获取活动详情数据
        $game_coupon_data = $this->_getPlayOrganizerGameTable()->get(array('id' => $order_data->coupon_id));
        if (!$game_coupon_data) {
            return array('status' => 0, 'message' => "活动不存在");
        }

        if (in_array($game_coupon_data->id, array(2010, 1987, 2091))) {
            $game_coupon_data->message_type = SendMessage::MESSAGE_TYPE_MEITUAN;
        }

        if ($type == 2) {// 商家登录

            $organizer_flag = $this->_getPlayCodeUsedTable()->get(array('organizer_id' => $store_id, 'good_info_id' => $order_data->bid));

            if (!$organizer_flag) {
                return array('status' => 0, 'message' => "订单号不属于本活动组织者");
            }

        } elseif ($type == 3) {// 后台编辑

            $organizer_flag = $this->_getPlayCodeUsedTable()->get(array('good_info_id' => $order_data->bid));
            if (!$organizer_flag) {
                return array('status' => 0, 'message' => "订单号不属于任何商家");
            }

            $store_id = $organizer_flag->organizer_id;

        }

        $timer = time();

        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();

        $s1 = $adapter->query('UPDATE play_coupon_code set `status`=?,`use_store`=?,`use_type`=?,`use_datetime`=? WHERE `id` = ?', array(1, $store_id, 2, $timer, $id))->count();
        $s2 = $adapter->query('UPDATE play_order_info set use_number=use_number+1,use_dateline=? WHERE  order_sn= ?', array($timer, $coupon_code_data->order_sn))->count();

        if ($s1 and $s2) {
            if ($game_coupon_data->message_type == SendMessage::MESSAGE_TYPE_MEITUAN) {
                $data_return_game_code = $this->meituanCoupon($order_data->bid, $order_data->user_id, $coupon_code_data->id);
                if (!$data_return_game_code) {
                    $conn->rollback();
                    return array('status' => 0, 'message' => "使用失败");
                }
            }

            $conn->commit();

            if ($order_data->buy_number == ($order_data->use_number + 1)) {
                $this->_getPlayOrderInfoTable()->update(array('pay_status' => 5), array('order_sn' => $coupon_code_data->order_sn));
            }

            $Seller = new Seller();
            $Seller->used($coupon_code_data->order_sn, $id);

            $organizerAccount = new OrganizerAccount();
            $contractData = $this->_getPlayContractsTable()->get(array('id' => $game_coupon_data->contract_id));
            $game_info = $this->_getPlayGameInfoTable()->get(array('id' => $order_data->bid));
            $tip = '无';
            $error = '';
            if ($contractData && $game_coupon_data->account_type == 1) {//验证后的商家分润
                $contractLinkPrice = $this->_getPlayContractLinkPriceTable()->get(array('id' => $game_info->contract_price_id));
                $mst = $organizerAccount->inventory($contractLinkPrice->inventory_id, 2, $order_data->coupon_unit_price, 1, $coupon_code_data->order_sn, $id, '用户消费');
                $tip = $mst ? '出库' : '出库__';
            } elseif ($contractData && $game_coupon_data->account_type == 2) {//代销充账

                if ($game_info->account_money > 0) {
                    $mst = $organizerAccount->profits($contractData->mid, $object_type = 3, $game_info->account_money, $id, $order_data->order_sn, $game_coupon_data->contract_id);
                    if ($mst['status']) {
                        $tip = '代销充账成功';
                    } else {
                        $tip = '代销充账_' . $mst['message'];
                        $error = '结算错误  code_id 是' . $id . '订单:' . $order_data->order_sn . '错误信息是' . $mst['message'];
                    }
                } else {
                    $tip = '代销充账结算价为0';
                }

            } elseif ($contractData && $game_coupon_data->account_type == 3) {//代销入商家账户 且提交结算

                if ($game_coupon_data->account_organizer == 1) {
                    $organizer_id = $contractData->mid;
                } else {
                    $organizer_id = $store_id;
                }

                if ($game_info->account_money > 0) {
                    $mst = $organizerAccount->profits($organizer_id, $object_type = 1, $game_info->account_money, $id, $order_data->order_sn, $game_coupon_data->contract_id);
                    if ($mst['status']) {
                        $tip = '代销结算账成功';
                    } else {
                        $error = '结算错误  code_id 是' . $id . '订单:' . $order_data->order_sn . '错误信息是' . $mst['message'];
                        $tip = '代销结算_' . $mst['message'];
                    }
                } else {
                    $tip = '代销结算价为0';
                }
            }

            if ($error) {
                $this->errorLog("{$error}\n");
            }

            // $date = date('Y-m-d,H:i:s', $timer);

            // $code_num = $zyb_data ? $zyb_data->zyb_code : $id. $password;


            //记录操作日志
            $this->_getPlayOrderActionTable()->insert(array(
                'order_id' => $order_data->order_sn,
                'play_status' => 5,
                'action_user' => $order_data->user_id,
                'action_note' => "使用成功,卡券密码{$id}{$password}" . '结算：' . $tip,
                'dateline' => $timer,
                'action_user_name' => ($type == 3) ? '管理员' . $_COOKIE['user'] . '_' . $organizer_flag->organizer_name : (($type == 2) ? '商家' . '_' . $organizer_flag->organizer_name : '店铺'),
                'code_id' => $id
            ));


            //查询首单记录
            $first_data = $adapter->query('SELECT * from play_order_info WHERE `user_id` = ? AND `order_status` = 1 AND `pay_status` >= 2 ORDER BY dateline ASC LIMIT 1;', array($order_data->user_id))->current();

            //查询当前订单号是否为首单订单号(使用一张票券调一次该方法，一个订单中多票券的票券为同种票券)
            if ($first_data->order_sn == $coupon_code_data->order_sn) {
                $adapter->query('update invite_member set status = 2 where phone = ? limit 1 ;', array($first_data->phone));

            }

            //奖励现金券
            $coupon = new Coupon();
            $coupon->getCashCouponByUse($order_data->user_id, $order_data->coupon_id, $order_data->bid, $order_data->order_sn, $order_data->order_city);
            //返利
            $cash = new Account();
            $cash->getCashByUse($order_data->user_id, $order_data->coupon_id, $order_data->bid, $order_data->order_sn, $order_data->order_city, $order_data->coupon_name);

            $integral = new Integral();

            //$real_pay = bcadd($order_data->real_pay,$order_data->account_money, 2);

            if (!$order_data->group_buy_id) {
                $integral->useGood($order_data->user_id, $order_data->coupon_id, $order_data->coupon_unit_price, $order_data->order_city, $order_data->coupon_name, $order_data->order_sn);
            }

            if ($game_coupon_data->message_type == SendMessage::MESSAGE_TYPE_HOTAL) {
                // 更新待预约订单缓存
                $class_orderinfo = new OrderInfo();

                $data_to_reserve_order_count = $class_orderinfo->getToReserveOrderCount();

                $data_to_reserve_order_count = $data_to_reserve_order_count - 1;

                RedCache::set('D:wft_str_orderalert', $data_to_reserve_order_count, 5 * 60);
            }

            if ($game_coupon_data->message_type != SendMessage::MESSAGE_TYPE_MEITUAN) {
                $data_message_param = array(
                    'phone' => $order_data->buy_phone,
                    'goods_name' => $order_data->coupon_name,
                    'game_name' => $game_info->price_name,
                    'game_time' => '',
                    'buy_time' => $order_data->dateline,
                    'use_time' => $game_info->start_time,
                    'end_time' => $game_info->end_time,
                    'limit_number' => $game_coupon_data->g_limit,
                    'custom_content' => $game_coupon_data->message_custom_content,
                    'price' => 0,
                    'code' => '',
                    'code_count' => 1,
                    'zyb_code' => '',
                    'teacher_phone' => '',
                    'city' => $order_data->order_city,
                    'goods_type' => $order_data->order_type,
                    'meeting_place' => '',
                    'meeting_time' => 0,
                    'message_status' => SendMessage::MESSAGE_STATUS_USE_SUCCESS,
                    'message_type' => $game_coupon_data->message_type,
                );

                SendMessage::sendMessageToUser($data_message_param);

                // 使用成功时，推送消息
                $data_message_type = 10; // 消息类型为订单使用成功
                $data_inform_type = 15; // 商品订单消息推送

                // 订单用户信息
                $data_order_user = $this->_getPlayUserTable()->get(array('uid' => $order_data->user_id));

                // 商品订单使用成功推送内容
                $data_inform = "【玩翻天】您购买的商品\"" . $order_data->coupon_name . "\"已成功使用，希望下次与您相约玩翻天，继续欢乐之旅！记得给好评哦~";

                // 商品订单使用成功系统消息
                $data_title = "订单使用成功";
                $data_message = "您购买的商品\"" . $order_data->coupon_name . "\"已成功使用";

                // 链接到的内容
                $data_link_id = array(
                    'lid' => $order_data->order_sn,
                    'id' => $order_data->coupon_id,
                    'type' => 'game'
                );

                $data_info = array(
                    'object_id' => $order_data->order_sn,
                    'object_rid' => $order_data->group_buy_id,
                );

                $class_sendMessage = new SendMessage();
                $class_sendMessage->sendMes($order_data->user_id, $data_message_type, $data_title, $data_message, $data_link_id);
                $class_sendMessage->sendInform($order_data->user_id, $data_order_user->token, $data_inform, $data_inform, $data_info, $data_inform_type, $order_data->coupon_id);
            } else {
                $data_message_param = array(
                    'phone' => $order_data->buy_phone,
                    'goods_name' => $order_data->coupon_name,
                    'game_name' => $game_info->price_name,
                    'game_time' => '',
                    'buy_time' => $order_data->dateline,
                    'use_time' => $game_info->start_time,
                    'end_time' => $game_info->end_time,
                    'limit_number' => $game_coupon_data->g_limit,
                    'custom_content' => $game_coupon_data->message_custom_content,
                    'price' => 0,
                    'code' => '',
                    'code_count' => 1,
                    'zyb_code' => $data_return_game_code->code,
                    'teacher_phone' => '',
                    'city' => $order_data->order_city,
                    'goods_type' => $order_data->order_type,
                    'meeting_place' => '',
                    'meeting_time' => 0,
                    'message_status' => SendMessage::MESSAGE_STATUS_USE_SUCCESS,
                    'message_type' => $game_coupon_data->message_type,
                );

                SendMessage::sendMessageToUser($data_message_param);
            }


            //商家发短信
            $shop_data = $this->_getPlayOrganizerTable()->get(array('id' => $store_id));
            if (preg_match("/1[34578]{1}\d{9}$/", $shop_data->phone)) {
                SendMessage::Send16($shop_data->phone, $order_data->coupon_name, $id, $password);
            }

            RedCache::del('D:unUse:' . $order_data->user_id);

            return array('status' => 1, 'message' => "使用成功");
        } else {
            $conn->rollback();
        }
        return array('status' => 0, 'message' => "使用失败");
    }

    private function meituanCoupon($bid, $user_id, $code_id)
    {
        $pdo = $this->_getAdapter();

        $data_game_code = $pdo->query(" SELECT * FROM play_game_code WHERE bid = ? AND status = 0 LIMIT 1 ", array($bid))->current();

        if (empty($data_game_code)) {
            return false;
        } else {
            $data_result_update_game_code = $pdo->query(" UPDATE play_game_code SET status = 1, code_uid = ?, code_order_id = ? WHERE id = ? AND status = 0", array($user_id, $code_id, $data_game_code->id))->count();

            if (!$data_result_update_game_code) {
                // 再尝试发一次
                $data_game_code = $pdo->query(" SELECT * FROM play_game_code WHERE bid = ? AND status = 0 LIMIT 1 ", array($bid))->current();

                if (empty($data_game_code)) {
                    return false;
                } else {
                    $data_result_update_game_code = $pdo->query(" UPDATE play_game_code SET status = 1, code_uid = ?, code_order_id = ? WHERE id = ? AND status = 0", array($user_id, $code_id, $data_game_code->id))->count();

                    if (!$data_result_update_game_code) {
                        return false;
                    }
                }
            }

            return $data_game_code;
        }
    }

    public function SendOldOrder($coupon_id)
    {
        $pdo = $this->_getAdapter();

        $data_nocheck = $pdo->query(" SELECT play_order_info.user_id, play_coupon_code.id, play_coupon_code.password FROM play_coupon_code LEFT JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn WHERE play_order_info.coupon_id = ? AND play_order_info.pay_status = 2 AND play_order_info.order_status = 1 AND play_coupon_code.status = 0 ", array($coupon_id));

        if (empty($data_nocheck)) {
            return true;
        } else {
            foreach ($data_nocheck as $val) {
                $this->UseCode('system', 3, $val->id . $val->password);
            }

            return true;
        }
    }
}
