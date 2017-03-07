<?php

namespace Deyi\OrderAction;

use Application\Module;
use Deyi\Account\Account;
use Deyi\BaseController;
use library\Service\System\Cache\RedCache;
use library\Service\User\Purchase;
use Deyi\Seller\Seller;
use Deyi\SendMessage;
use Deyi\Coupon\Coupon;
use Deyi\ZybPay\ZybPay;
use Deyi\Integral\Integral;
use library\Service\User\User;

class OrderPay
{
    use BaseController;
    use OrderBack;
    use UseCode;

    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }


    /**
     * todo 添加事务处理
     * 订单支付成功回调
     *
     * @param $order_info
     * @param $order_info_game
     * @param string $trade_no //支付流水
     * @param $pay_type |union|alipay|other|account
     * @param $accountName
     * @return bool
     */
    public function paySuccess($order_info, $order_info_game, $trade_no = '', $pay_type, $accountName)
    {


        $real_pay = $order_info->real_pay;  //银行卡金额
        $account_money = $order_info->account_money; //账户金额

        $data_organizer_game =$this->_getPlayOrganizerGameTable()->get(array('id' => $order_info->coupon_id));

        if (empty($data_organizer_game)) {
            return false;
        }

        $adapter = $this->_getAdapter();
        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();

        // 判断是否已经处理过
        if ($order_info->pay_status < 2) {
            //使用账户余额支付
            if ($pay_type == 'account') {
                $account_data = $adapter->query("select * from play_account where uid=?", array($order_info->user_id))->current();
                if (!$account_data || !$account_data->status) {
                    $conn->rollback();
                    return false;
                }
                //用哪个账户的钱,优先不可提现账户
                $can_back_money_flow = null;
                if (($account_data->now_money - $order_info->real_pay) < $account_data->can_back_money) {
                    $s9 = $adapter->query("UPDATE play_account SET now_money=now_money-{$order_info->real_pay},can_back_money=now_money,last_time=? WHERE uid=? AND now_money>={$order_info->real_pay}", array(time(), $order_info->user_id))->count();
                    $can_back_money_flow = $order_info->real_pay - ($account_data->now_money - $account_data->can_back_money);
                } else {
                    $s9 = $adapter->query("UPDATE play_account SET now_money=now_money-{$order_info->real_pay},last_time=? WHERE uid=? AND now_money>={$order_info->real_pay}", array(time(), $order_info->user_id))->count();
                }

                $coupon_name = '购买商品' . $order_info->coupon_name ?: '';
                $s10 = $adapter->query("INSERT INTO play_account_log (id,uid,action_type,action_type_id,object_id,flow_money,surplus_money,dateline,description,status,user_account,check_status,can_back_money_flow) VALUES (NULL ,?,?,?,?,?,?,?,?,?,?,?,?)",
                    array($order_info->user_id, 2, 1, $order_info->order_sn, $order_info->real_pay, bcsub($account_data->now_money, $order_info->real_pay, 2), time(), $coupon_name, 1, $order_info->user_id, 1, $can_back_money_flow))->count();

                if (!$s9 or !$s10) {
                    $conn->rollback();
                    return false;
                } else {
                    //更新账户付款使用码状态为 已审核
                    $this->_getPlayCouponCodeTable()->update(array('check_status' => 2), array('order_sn' => $order_info->order_sn));

                    $account_money = $order_info->real_pay;
                    $real_pay = 0;
                    $trade_no = $adapter->getDriver()->getLastGeneratedValue(); //交易流水记录
                    //$conn->commit();
                }
            }


            //判断是否是团购订单
            $pay_status = 2;
            if ($order_info->group_buy_id != 0) {
                //团购订单
                $group_data = $adapter->query("select * from play_group_buy WHERE  id=?", array($order_info->group_buy_id))->current();
//                $group_data = $this->_getPlayGroupBuyTable()->get(array('id' => $order_info->group_buy_id));
                if (!$group_data) {
                    $conn->rollback();
                    return false;
                }
                if (($group_data->join_number + 1) == $group_data->limit_number) {
                    //团购完成
//                    $this->_getPlayGroupBuyTable()->update(array('join_number' => new Expression('join_number+1'), 'status' => 2), array('id' => $order_info->group_buy_id));
                    $g1 = $adapter->query("update play_group_buy set join_number=join_number+1,status=2 WHERE id=?", array($order_info->group_buy_id))->count();
                    if (!$g1) {
                        $conn->rollback();
                        return false;
                    }
                    //更新其他订单数据
                    //$this->_getPlayOrderInfoTable()->update(array('order_status' => 1, 'pay_status' => $pay_status), array('group_buy_id' => $order_info->group_buy_id, 'pay_status' => 7));
                    $g2 = $adapter->query("update play_order_info set order_status=1,pay_status=? WHERE group_buy_id=? and pay_status=7", array($pay_status, $order_info->group_buy_id))->count();
                    if (!$g2) {
                        $conn->rollback();
                        return false;
                    }
                } else {
                    //团购中
                    $pay_status = 7;
                    //$this->_getPlayGroupBuyTable()->update(array('join_number' => new Expression('join_number+1')), array('id' => $order_info->group_buy_id));
                    $g1 = $adapter->query("update play_group_buy set join_number=join_number+1 WHERE id=?", array($order_info->group_buy_id))->count();
                    if (!$g1) {
                        $conn->rollback();
                        return false;
                    }
                }
            }

            //支付成功
            $approve_status = ($pay_type == 'account') ? 2 : 1;
            $s = $adapter->query("update play_order_info set account_type=?,order_status=?,pay_status=?,trade_no=?,account=?,real_pay=?,account_money=?,approve_status=? WHERE order_sn=?",
                array($pay_type, 1, $pay_status, $trade_no, $accountName, $real_pay, $account_money, $approve_status, $order_info->order_sn))->count();

            if (!$s) {
                $conn->rollback();
                return false;
            }
            //记录判断是否回收后又支付,记录并邮件通知
            if($order_info->order_status==0){
                $this->errorLog("订单号{$order_info->order_sn}已被系统回收,现已支付");
            }
            // 记录操作日志
            $s = $adapter->query('INSERT INTO play_order_action (order_id,play_status,action_user,action_note,dateline,action_user_name) VALUES (?,?,?,?,?,?)', array(
                $order_info->order_sn,
                2,
                $order_info->user_id,
                '支付成功',
                time(),
                '用户' . $order_info->username
            ));

            if (!$s) {
                $conn->rollback();
                return false;
            }
            $conn->commit();  //核心事务完成

            $total_money = bcadd($account_money, $real_pay, 2);  //账户加银行卡总支付金额

            $link_data = $this->_getPlayGameInfoTable()->get(array('id' => $order_info_game->game_info_id));
            $use_time = date('Y-m-d', $link_data->end_time);

            $Seller = new Seller();
            $Seller->fission($order_info);

            //判断是否 智游宝的
            $zyb_code = Null;
            if ($link_data->goods_sm) {

                $adapter = $this->_getAdapter();
                $code_data_list = $this->_getPlayCouponCodeTable()->fetchAll(array('order_sn' => $order_info->order_sn));

                $Zyb = new ZybPay();
                $result = $Zyb->pay($order_info->order_sn);
                if ($result['code'] == '0' && $result['description'] == '成功') {
                    $zyb_code = $result['orderResponse']['order']['assistCheckNo'];
                    foreach ($code_data_list as $v_code) {
                        $adapter->query('INSERT INTO play_zyb_info (order_sn, zyb_type, code_id, dateline, zyb_code, status, buy_time) VALUES (?,?,?,?,?,?,?)', array(
                            $v_code->order_sn,
                            1,
                            $v_code->id,
                            time(),
                            $zyb_code,
                            2,
                            time()
                        ));
                    }
                    // 更新正确的时间
                    $Zyb->getOrderInfo($order_info->order_sn);

                } else {
                    $adapter->query('INSERT INTO play_zyb_info (order_sn, zyb_type, dateline) VALUES (?,?,?)', array(
                        $order_info->order_sn,
                        2,
                        time(),
                    ));

                    //下单失败 记录下单失败原因
                    $this->_getPlayOrderActionTable()->insert(
                        array('order_id' => $order_info->order_sn,
                            'play_status' => 101, //智游宝 错误
                            'action_user' => 1,
                            'action_note' => "智游宝下单失败原因: ". $result['description'],
                            'dateline' => time(),
                            'action_user_name' => '系统插入',
                        )
                    );

                    //直接退款
                    foreach ($code_data_list as $v_code) {
                        $this->backIng($order_info->order_sn, $v_code->id . $v_code->password, 2);
                    }
                    return true;
                }
            }

            //支付完成消息
            $message = "您购买的{$order_info->coupon_name}已支付完成，请于{$use_time}之前使用。";

            if ($pay_status == 2) {
                if ($order_info->group_buy_id != 0) {
                    // 团购成功 群发短信  后期 异步
                    $order_list = $this->_getPlayOrderInfoTable()->fetchAll(array('group_buy_id' => $order_info->group_buy_id, 'order_status' => 1, 'pay_status' => 2));
                    foreach ($order_list as $o) {
                        $out_trade_no = $o->order_sn;
                        $code_data = $this->_getPlayCouponCodeTable()->fetchAll(array('order_sn' => $out_trade_no));
                        $data_order_info_game = $this->_getPlayOrderInfoGameTable()->get(array('order_sn' => $o->order_sn));
                        $total_money = bcadd($o->account_money, $o->real_pay, 2);
                        $code_len = '';
                        foreach ($code_data as $v) {
                            if ($code_len) {
                                $code_len .= ',(' . $v->id . $v->password . ')';
                            } else {
                                $code_len .= '(' . $v->id . $v->password . ')';
                            }
                        }

                        $data_message_param = array(
                            'phone'          => $o->buy_phone,
                            'goods_name'     => $order_info->coupon_name,
                            'game_name'      => $link_data->price_name,
                            'game_time'      => '',
                            'buy_time'       => $order_info->dateline,
                            'use_time'       => $order_info_game->start_time,
                            'end_time'       => $order_info_game->end_time,
                            'limit_number'   => $data_organizer_game->g_limit,
                            'custom_content' => $data_organizer_game->message_custom_content,
                            'price'          => $total_money,
                            'code'           => $code_len,
                            'code_count'     => $code_data->count(),
                            'zyb_code'       => $zyb_code,
                            'teacher_phone'  => '',
                            'city'           => $o->order_city,
                            'goods_type'     => $order_info->order_type,
                            'meeting_place'  => '',
                            'meeting_time'   => 0,
                            'message_status' => SendMessage::MESSAGE_STATUS_PAY_SUCCESS,
                            'message_type'   => $data_organizer_game->message_type,
                        );

                        SendMessage::sendMessageToUser($data_message_param);

                        // if ($zyb_code) {
                        //     SendMessage::Send13($o->buy_phone, $order_info->coupon_name, $code_data->count(), $zyb_code);
                        // } else {
                        //     if ($data_organizer_game['is_hotal'] == 1) {
                        //         $data_use_time = date("Y年m月d日", $data_order_info_game->start_time);
                        //         SendMessage::Send20($o->buy_phone, $o->coupon_name, $link_data->price_name, $total_money, $data_use_time, $o->order_city);
                        //     } else {
                        //         SendMessage::Send4 ($o->buy_phone, $o->coupon_name, $total_money, $code_len, $o->order_city);
                        //     }
                        // }
                    }

                    $this->sendMes($order_info->user_id, $message, json_encode(array('type' => 'group', 'id' => $order_info->coupon_id, 'lid' => (string)$order_info->order_sn, 'group_buy_id' => $order_info->group_buy_id)));
                    // 更新圈子内分享的数量
                    $this->_getMdbSocialCircleMsg()->update(array('object_data.group_buy_id' => (int)$order_info->group_buy_id), array('$set' => array('object_data.join_number' => $group_data->limit_number)), array('multiple' => true));
                    // 跟新消息数量
                    $this->_getMdbsocialChatMsg()->update(array('object_data.group_buy_id' => (int)$order_info->group_buy_id), array('$set' => array('object_data.join_number' => $group_data->limit_number)), array('multiple' => true));

                    //成团奖励
                    $res = $this->_getPlayWelfareTable()->tableGateway->select(function ($select) use ($group_data) {
                        $select->where(array('object_type' => 3, 'object_id' => $group_data->game_id, 'good_info_id' => $group_data->game_info_id));
                        $select->limit(1);
                    })->current();

                    if ($res) {
                        //奖励团长现金券
                        $coupon = new Coupon();
                        $coupon->addCashcoupon($group_data->uid, $res->welfare_link_id, $order_info->order_sn, 5, 0, '团长奖励', $order_info->order_city);
                    }
                } else {
                    //当前订单兑换码
                    $code_data = $this->_getPlayCouponCodeTable()->fetchAll(array('order_sn' => $order_info->order_sn))->toArray();

                    $code_len = '';
                    foreach ($code_data as $v) {
                        if ($code_len) {
                            $code_len .= ',(' . $v['id'] . $v['password'] . ')';
                        } else {
                            $code_len .= '(' . $v['id'] . $v['password'] . ')';
                        }
                    }

                    $this->sendMes($order_info->user_id, $message, json_encode(array('type' => (($order_info->order_type == 1) ? 'coupon' : 'game'), 'id' => $order_info->coupon_id, 'lid' => $order_info->order_sn)));

                    $data_message_param = array(
                        'phone'          => $order_info->buy_phone,
                        'goods_name'     => $order_info->coupon_name,
                        'game_name'      => $link_data->price_name,
                        'game_time'      => '',
                        'buy_time'       => $order_info->dateline,
                        'use_time'       => $order_info_game->start_time,
                        'end_time'       => $order_info_game->end_time,
                        'limit_number'   => $data_organizer_game->g_limit,
                        'custom_content' => $data_organizer_game->message_custom_content,
                        'price'          => $total_money,
                        'code'           => $code_len,
                        'code_count'     => count($code_data),
                        'zyb_code'       => $zyb_code,
                        'teacher_phone'  => '',
                        'city'           => $order_info->order_city,
                        'goods_type'     => $order_info->order_type,
                        'meeting_place'  => '',
                        'meeting_time'   => 0,
                        'message_status' => SendMessage::MESSAGE_STATUS_PAY_SUCCESS,
                        'message_type'   => $data_organizer_game->message_type,
                    );

                    SendMessage::sendMessageToUser($data_message_param);

                    // 判断是否为美团合作商品
                    if (in_array($data_organizer_game->id, array(2010, 1987, 2091))) {
                        $data_organizer_game->message_type = SendMessage::MESSAGE_TYPE_MEITUAN;
                    }

                    if ($data_organizer_game->message_type == SendMessage::MESSAGE_TYPE_MEITUAN) {
                        foreach ($code_data as $c_v) {
                            $this->UseCode('system', 3, $c_v['id']. $c_v['password']);
                        }
                    }

                    // if ($zyb_code) {
                    //     SendMessage::Send13($order_info->buy_phone, $order_info->coupon_name, $code_data->count(), $zyb_code);
                    // } else {
                    //     if ($data_organizer_game['is_hotal'] == 1) {
                    //         $data_use_time = date("Y年m月d日", $order_info_game->start_time);
                    //         SendMessage::Send20($order_info->buy_phone, $order_info->coupon_name, $link_data->price_name, $total_money, $data_use_time, $order_info->order_city);
                    //     } else {
                    //         SendMessage::Send4($order_info->buy_phone, $order_info->coupon_name, $total_money, $code_len,$order_info->order_city);
                    //     }
                    // }
                }
            } elseif ($pay_status == 7) {
                $data_message_param = array(
                    'phone'          => $order_info->buy_phone,
                    'goods_name'     => $order_info->coupon_name,
                    'game_name'      => $link_data->price_name,
                    'game_time'      => '',
                    'buy_time'       => $order_info->dateline,
                    'use_time'       => $order_info_game->start_time,
                    'end_time'       => $order_info_game->end_time,
                    'limit_number'   => $group_data->limit_number,
                    'custom_content' => $data_organizer_game->custom_content,
                    'price'          => $total_money,
                    'code'           => '',
                    'code_count'     => 0,
                    'zyb_code'       => $zyb_code,
                    'teacher_phone'  => '',
                    'city'           => $order_info->order_city,
                    'goods_type'     => $order_info->order_type,
                    'meeting_place'  => '',
                    'meeting_time'   => 0,
                    'message_status' => SendMessage::MESSAGE_STATUS_PAY_SUCCESS,
                    'message_type'   => SendMessage::MESSAGE_TYPE_GROUP,
                );

                SendMessage::sendMessageToUser($data_message_param);

                //SendMessage::Send15($order_info->buy_phone, $order_info->coupon_name,$group_data->limit_number);
                // 我的消息
                $this->sendMes($order_info->user_id, $message, json_encode(array('type' => 'group', 'id' => $order_info->coupon_id, 'lid' => (string)$order_info->order_sn, 'group_buy_id' => $order_info->group_buy_id)));
                // 更新圈子内分享的数量 +1
                $this->_getMdbSocialCircleMsg()->update(array('object_data.group_buy_id' => (int)$order_info->group_buy_id), array('$inc' => array('object_data.join_number' => 1)), array('multiple' => true));
                // 跟新消息数量 +1
                $this->_getMdbsocialChatMsg()->update(array('object_data.group_buy_id' => (int)$order_info->group_buy_id), array('$inc' => array('object_data.join_number' => 1)), array('multiple' => true));

            }

            //支付完成后更新咨询状态
            Purchase::updateConsultStatus($order_info);

            //支付完成奖励积分和票券
            $integral = new Integral();
            $integral->buyGood($order_info->user_id, $order_info->order_sn, $total_money, $order_info->order_city, $order_info->coupon_name);
            //奖励现金券
            $coupon = new Coupon();
            //更新现金券的使用状态
            if($order_info->buy_number > 1){
                for($i=0;$i<$order_info->buy_number;$i++){
                    $coupon->getCashCouponByBuy($order_info->user_id, $order_info->coupon_id, $order_info_game->game_info_id, $order_info->order_sn, $order_info->order_city, $order_info->coupon_name);
                }
            }else{
                $coupon->getCashCouponByBuy($order_info->user_id, $order_info->coupon_id, $order_info_game->game_info_id, $order_info->order_sn, $order_info->order_city, $order_info->coupon_name);
            }
            //返利
            $cash = new Account();
            if($order_info->buy_number > 1){
                for($i=0;$i<$order_info->buy_number;$i++){
                    $cash->getCashByBuy($order_info->user_id, $order_info->coupon_id, $order_info_game->game_info_id, $order_info->order_sn, $order_info->order_city, $order_info->coupon_name);
                }
            }else{
                $cash->getCashByBuy($order_info->user_id, $order_info->coupon_id, $order_info_game->game_info_id, $order_info->order_sn, $order_info->order_city, $order_info->coupon_name);
            }

            // 分享抽奖活动
            $param_data = array(
                'log_date'   => '2020-01-01',
                'lottery_id' => 5,                      // 抽奖活动id
                'user_id'    => $order_info->user_id,
                'total'      => 1,
                'op_total'   => 0,
                'may_total'  => 5,
                'created'    => date('Y-m-d H:i:s'),
            );

            $param_where = array(
                'lottery_id' => 5,                      // 抽奖活动id
                'user_id'    => $order_info->user_id,
                'log_date'   => date('Y-m-d H:i:s')
            );

            User::updateLotteryUserData($param_data, $param_where);
        } else {
            return false;
        }

        RedCache::del('D:tneedPay:' . $order_info->user_id);
        return true;
    }

    //支付完成的消息
    private function sendMes($uid, $message, $link_id) {
        $title = '支付完成';
        $this->_getPlayUserMessageTable()->insert(array('uid' => $uid, 'type' => 5, 'title' => $title, 'deadline' => time(), 'message' => $message, 'link_id' => $link_id));
    }
}
