<?php
namespace Deyi\OrderAction;

use Deyi\Account\Account;
use Deyi\BaseController;
use Deyi\Integral\Integral;
use library\Service\System\Cache\RedCache;
use Deyi\Seller\Seller;
use Deyi\SendMessage;
use Deyi\ZybPay\ZybPay;
use Deyi\WeiSdkPay\WeiPay;
use Deyi\Alipay\Alipay;
use Deyi\WeiXinPay\WeiXinPayFun;
use Deyi\Unionpay\Unionpay;
use Zend\Db\Sql\Expression;

trait OrderBack
{
//    use BaseController;


    /**
     *
     * 退款中
     * @param $order_sn
     * @param $code |完整的验证码
     * @param $group |谁提交的退款 1 用户  2 管理员
     * @param $money |管理员提交的退款金额
     * @return array
     */
    public function backIng($order_sn, $code, $group = 1, $money = null)
    {

        $id = substr($code, 0, -7);

        //订单信息
        $order_data = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));

        //去掉了 后台指定退金额
        /*if ($group == 2 && $order_data->coupon_unit_price < $money) {
            return array('status' => 0, 'message' => '指定退款金额不能高于原先价格');
        }*/

        if (!$order_data || $order_data->pay_status < 2) {
            return array('status' => 0, 'message' => '异常');
        }

        if ($order_data->order_type == 1) {
            return array('status' => 0, 'message' => '时间太久远了, 请联系客服进行处理');
        }

        if ($order_data->order_type != 2) {
            return array('status' => 0, 'message' => '异常');
        }

        //获取退款时间
        $game_info       = $this->_getPlayGameInfoTable()->get(array('id' => $order_data->bid));
        $data_game_price = $this->_getPlayGamePriceTable()->get(array('id' => $game_info->pid));

        if ($data_game_price->back_rule == 0) {
            $refund_time = $game_info->refund_time;
        } else {
            $refund_time = strtotime(date("Y-m-d 00:00:00", $game_info->start_time)) + $data_game_price->refund_before_day * 86400 + strtotime(date("H:i:s", $game_info->refund_before_time));
        }

        if (!$refund_time) {
            return array('status' => 0, 'message' => '允许退款时间未设置');
        }

        if ($refund_time < time() && $group == 1) {//不可以退款,金额为0,客户端不会显示退款按钮 管理员添加退款 不受时间限制
            return array('status' => 0, 'message' => '过了退款时间');
        }

        //todo 优化 订单关联表 记录 是否是 智游宝商品
        //判断提交智游宝
        $adapter = $this->_getAdapter();
        $zyb_data = $adapter->query("select * from play_zyb_info WHERE order_sn = ? AND zyb_type = 1 AND code_id = ?", array($order_sn, $id))->current();

        if ($zyb_data) {

            if (!$zyb_data->zyb_code || $zyb_data->status != 2) {
                return array('status' => 0, 'message' => '操作失败');
            }

            $ZybPay = new ZybPay();
            $rest = $ZybPay->backPartTicket($id);

            if($rest['code'] == '0' && $rest['description'] == '成功') {
                $adapter->query("update play_zyb_info set status = 3, back_number= ? WHERE order_sn = ? AND code_id = ?", array($rest['retreatBatchNo'], $order_sn, $id))->count();
            } else {
                return array('status' => 0, 'message' => $rest['description']);
            }

        }

        //是否使用代金券
        if ($order_data->voucher_id) {
            $cash_data = $this->_getCashCouponUserTable()->get(array('id' => $order_data->voucher_id));
            if ($cash_data and $cash_data->is_back == 0) {

                if ($cash_data->back_money!=$order_data->voucher) {
                    $order_data->voucher=bcsub($order_data->voucher,$cash_data->back_money,2); //剩余可退金额
                }
                if ($order_data->coupon_unit_price >= $order_data->voucher) {
                    //代金券小于当前金额,销毁代金券,退款扣除代金券后的剩余金额
                    $this->_getCashCouponUserTable()->update(array('is_back' => 1,'back_money'=>$order_data->voucher), array('id' => $order_data->voucher_id));
                    $back_money = bcsub($order_data->coupon_unit_price, $order_data->voucher, 2);
                } else {
                    //代金券大于当前金额,用户退款金额为0,记录代金券剩余金额以供下次扣除
                    $this->_getCashCouponUserTable()->update(array('back_money' => new  Expression('back_money+'.$order_data->coupon_unit_price)), array('id' => $order_data->voucher_id));
                    $back_money = 0;
                }

            } else {
                $back_money = $order_data->coupon_unit_price;
            }
        } else {
            $back_money = $order_data->coupon_unit_price;
        }

        $s1 = $this->_getPlayCouponCodeTable()->update(array('status' => 3, 'back_time' => time(), 'back_money' => $back_money), array('order_sn' => $order_sn, 'id' => $id, 'status' => 0));

        if (!$s1) {
            return array('status' => 0, 'message' => '退款失败');
        }

        $this->_getPlayOrderInfoTable()->update(array('pay_status' => 3), array('order_sn' => $order_sn));

        // 恢复卡券购买数
        $s3 = $this->_getPlayGameInfoTable()->update(array('buy' => new Expression('buy-1')), array('id' => $order_data->bid));
        $s4 = $this->_getPlayOrganizerGameTable()->update(array('buy_num' => new Expression('buy_num-1')), array('id' => $order_data->coupon_id));

        if ($s3 and $s4) {

            //分销
            $Seller = new Seller();
            $Seller->back($order_sn, $id);

            //同玩商品 减去数量
            if ($order_data->group_buy_id) {
                $this->_getPlayGroupBuyTable()->update(array('join_number' => new Expression('join_number-1')), array('id' => $order_data->group_buy_id));
            }

            // 记录操作日志
            $this->_getPlayOrderActionTable()->insert(array(
                'order_id' => $order_sn,
                'play_status' => 3,
                'action_user' => $order_data->user_id,
                'action_note' => '退款中',
                'dateline' => time(),
                'action_user_name' => ($group == 1) ? '用户' . $order_data->username : '管理员' . $_COOKIE['user'],
                'code_id' => $id,
            ));

            $this->_getPlayOrderInfoTable()->update(array('backing_number' => new Expression('backing_number+1')), array('order_sn' => $order_sn));

            if ($back_money <= 5) { //低于5的自动受理退款

                $s2 = $this->_getPlayCouponCodeTable()->update(array('force' => 2, 'accept_time' => time()), array('id' => $id));

                if ($s2) {
                    $this->_getPlayOrderActionTable()->insert(array(
                        'order_id' => $order_sn,
                        'play_status' => 7,
                        'action_user' => $order_data->user_id,
                        'action_note' => '低于5的系统自动受理退款',
                        'dateline' => time(),
                        'action_user_name' => '系统',
                        'code_id' => $id,
                    ));
                }
            }

            if ($back_money > 0) { //如果退款金额是0 里面确认退款成功

                $data_refund_status = ($group == 1) ? SendMessage::MESSAGE_STATUS_REFUND_REFUND : SendMessage::MESSAGE_STATUS_ADMIN_REFUND;
                $data_organizer_game = $this->_getPlayOrganizerGameTable()->get(array('id' => $order_data->coupon_id));

                $data_message_param = array(
                    'phone'          => $order_data->buy_phone,
                    'goods_name'     => $data_organizer_game->title,
                    'game_name'      => $game_info->price_name,
                    'game_time'      => $game_info->start_time,
                    'buy_time'       => $order_data->dateline,
                    'use_time'       => 0,
                    'end_time'       => 0,
                    'limit_number'   => 0,
                    'custom_content' => '',
                    'price'          => 0,
                    'code'           => '',
                    'code_count'     => 0,
                    'zyb_code'       => '',
                    'teacher_phone'  => '',
                    'city'           => $order_data->order_city,
                    'goods_type'     => $order_data->order_type,
                    'meeting_place'  => '',
                    'meeting_time'   => 0,
                    'message_status' => $data_refund_status,
                    'message_type'   => $data_organizer_game->message_type,
                );

                SendMessage::sendMessageToUser($data_message_param);

            } else {
                return $this->backOk($order_sn, $code, 1);
            }

            if ($data_organizer_game->message_type == SendMessage::MESSAGE_TYPE_HOTAL) {
                // 更新待预约订单缓存
                $class_orderinfo = new OrderInfo();

                $data_to_reserve_order_count = $class_orderinfo->getToReserveOrderCount();

                $data_to_reserve_order_count = $data_to_reserve_order_count - 1;

                RedCache::set('D:wft_str_orderalert', $data_to_reserve_order_count, 5 * 60);
            }

            return array('status' => 1, 'message' => '操作成功');

        } else {
            return array('status' => 0, 'message' => '操作失败');
        }
    }

    /**
     *
     * 成功退订
     * @param $order_sn |订单号
     * @param $code |完整的验证码
     * @param $group |谁执行的退款 1 用户 (已过退款时间) 2 管理员 3管理员线下确认退款了
     * @return array
     */
    public function backOk($order_sn, $code, $group = 2)
    {

        //付款状态 ;0未付款;1付款中;2已付款 3  退款中 4 退款成功  5已使用
        //使用状态,0未使用,1已使用,2已退款,3退款中

        // 正确使用
        $id = substr($code, 0, -7);
        //订单信息
        $order_data = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));
        $code_data = $this->_getPlayCouponCodeTable()->get(array('order_sn' => $order_sn, 'id' => $id));
        if (!$order_data or !$code_data) {
            return array('status' => 0, 'message' => '订单或验证码不存在');
        }

        //智游宝  //确认退款 智游宝 会回调  status 会变为4
        $adapter = $this->_getAdapter();
        $zyb_data = $adapter->query("select * from play_zyb_info WHERE order_sn = ? AND code_id = ?", array($order_sn, $id))->current();

        if ($code_data->status == 3 && $zyb_data && $zyb_data->zyb_code && $zyb_data->status != 4 ) { //特殊退款让过 正常退款要判断
            return array('status' => 0, 'message' => '等待智游宝回调');
        }

        //检查除此之外是否还有等待退订的
        if ($code_data->status == 1 || $code_data->status == 2) { //特殊退款
            $s1 = $this->_getPlayCouponCodeTable()->update(array('test_status' => 2, 'force' => 3, 'back_money_time' => time()), array('order_sn' =>$code_data->order_sn, 'id' =>$code_data->id, 'force' => 2));
            $s2 = true;

        } elseif ($code_data->status == 3) {
            $s1 = $this->_getPlayCouponCodeTable()->update(array('test_status' => 2, 'status' => 2,'force' => 0, 'back_money_time' => time()), array('order_sn' =>$code_data->order_sn, 'id' =>$code_data->id, 'force' => 2));

            $have_back = $this->_getPlayCouponCodeTable()->fetchAll(array('order_sn' => $order_sn, 'status' => 3))->count();
            if ($have_back) {
                $s2 = $this->_getPlayOrderInfoTable()->update(array('back_number' => new Expression('back_number+1'),'backing_number' => new Expression('backing_number-1')), array('order_sn' => $order_sn));
            } else {
                $s2 = $this->_getPlayOrderInfoTable()->update(array('pay_status' => 4, 'back_number' => new Expression('back_number+1'),'backing_number' => new Expression('backing_number-1')), array('order_sn' => $order_sn));
            }
        }


        if ($s1 && $s2) {
            if ($code_data->back_money > 0 && $order_data->account_money > 0) {
                $account = new \library\Service\User\Account();
                $rec = $account->recharge($order_data->user_id, $code_data->back_money, 1, '退款' . $order_data->coupon_name, 1, $order_sn);
                if (!$rec) {
                    $this->_getPlayOrderActionTable()->insert(array(
                        'order_id' => $order_data->order_sn,
                        'play_status' => 102,
                        'action_user' => $order_data->user_id,
                        'action_note' => '确认退款到账户失败',
                        'dateline' => time(),
                        'action_user_name' => '管理员' . $_COOKIE['user'],
                        'code_id' => $id,
                    ));
                    return array('status' => 0, 'message' => '退款失败');
                }

                $data_organizer_game = $this->_getPlayOrganizerGameTable()->get(array('id' => $order_data->coupon_id));
                $data_game_info      = $this->_getPlayGameInfoTable()->get(array('id' => $order_data->bid));

                $data_message_param = array(
                    'phone'          => $order_data->buy_phone,
                    'goods_name'     => $data_organizer_game->name,
                    'game_name'      => $data_game_info->price_name,
                    'game_time'      => '',
                    'buy_time'       => $order_data->dateline,
                    'use_time'       => 0,
                    'end_time'       => 0,
                    'limit_number'   => 0,
                    'custom_content' => '',
                    'price'          => 0,
                    'code'           => '',
                    'code_count'     => 0,
                    'zyb_code'       => '',
                    'teacher_phone'  => '',
                    'city'           => $order_data->order_city,
                    'goods_type'     => $order_data->order_type,
                    'meeting_place'  => '',
                    'meeting_time'   => 0,
                    'message_status' => SendMessage::MESSAGE_STATUS_REFUND_SUCCESS,
                    'message_type'   => $data_organizer_game->message_type,
                );

                SendMessage::sendMessageToUser($data_message_param);
            }

            // 记录操作日志
            $this->_getPlayOrderActionTable()->insert(array(
                'order_id' => $order_data->order_sn,
                'play_status' => 4,
                'action_user' => $order_data->user_id,
                'action_note' => ($group == 1) ? "退款金额是0 直接退款成功" : "退款成功,卡券密码{$code},金额{$code_data->back_money}元". ($group == 3 ? '线下退款' : ''),
                'dateline' => time(),
                'action_user_name' => ($group == 1) ? '用户' . $order_data->username : '管理员' . $_COOKIE['user'],
                'code_id' => $id,
            ));

            $this->_getPlayUserMessageTable()->insert(array(
                'uid' => $order_data->user_id,
                'type' => 8,
                'title' => '退款完成',
                'deadline' => time(),
                'message' => "兑换码为{$code}的{$order_data->coupon_name}，已经退款完成，退款已返回至您的账户，请查看",
                'link_id' => json_encode(array(
                    'type' => (($order_data->order_type == 1) ? 'coupon' : 'game'),
                    'id' => $order_data->coupon_id,
                    'lid' => $order_data->order_sn
                ))
            ));

            if ($order_data) {//退款扣积分，只是记录，做任务时需要
                $integral = new Integral();
                $integral->returnGood($order_data->user_id, $order_data->order_sn, 0, $order_data->order_city);
            }

            return array('status' => 1, 'message' => '退款成功');
        } else {
            return array('status' => 0, 'message' => '退款失败');
        }
    }

    /**
     * @param $id
     * @return array
     */
    public function refundMoney($id) {

        $adapter = $this->_getAdapter();

        $sql = "SELECT
	play_coupon_code.id,
	play_coupon_code.password,
	play_coupon_code.back_money,
	play_coupon_code.order_sn,
	play_order_info.account_type,
	play_order_info.trade_no,
	play_order_info.real_pay
FROM
	play_coupon_code
INNER JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
WHERE
	play_coupon_code.id = ?
AND play_coupon_code.`force` = 2
AND play_coupon_code.check_status = 2";

        $orderData = $adapter->query($sql, array($id))->current();

        if (!$orderData) {
            return array('status' => 0, 'message' => '该使用码不能退款');
        }

        //如果金额是0 直接退款成功
        if ($orderData->back_money <= 0) {
            return $this->backOk($orderData->order_sn, $orderData->id. $orderData->password, 2);
        }

        if (!in_array($orderData->account_type, array('weixin','union', 'new_jsapi','alipay', 'account'))) {
            return array('status' => 0, 'message' => '支付方式不正确');
        }

        if ($orderData->account_type == 'weixin') {
            $WeiXinPay = new WeiPay();
            $back = $WeiXinPay->wxRefund($orderData->trade_no, 'WFT'. $orderData->order_sn. $orderData->id, $orderData->real_pay, $orderData->back_money);
        } elseif ($orderData->account_type == 'union') {
            $Union = new Unionpay();
            $back = $Union->unRefund($orderData->trade_no, $orderData->back_money);
        } elseif ($orderData->account_type == 'new_jsapi') {
            $WeiXinWap = new WeiXinPayFun($this->_getConfig()['wanfantian_weixin']);
            $back = $WeiXinWap->wxRefund($orderData->trade_no, 'WFT'. $orderData->order_sn. $orderData->id, $orderData->real_pay, $orderData->back_money);
        } elseif ($orderData->account_type == 'account') {
            $back = array('status' => 1, 'message' => 'OK!');
        } elseif ($orderData->account_type == 'alipay') {

            $is_having = $adapter->query("SELECT * FROM play_alipay_refund_log WHERE order_type = 2 AND trade_no = ? AND order_sn = ? AND code_id = ?", array($orderData->trade_no, $orderData->order_sn, $orderData->id))->current();

            if (!$is_having) {
                $result = $this->aliLog(array('type' => 'insert', 'trade_no' => $orderData->trade_no, 'back_money' => $orderData->back_money, 'order_sn' => $orderData->order_sn, 'id' => $orderData->id));

                if (!$result['status']) {
                    return $result;
                }
                $alipay = new Alipay();
                $back = $alipay->aliPwdRefund($orderData->trade_no, $orderData->back_money, date('YmdHis'). 'WFT'. intval($orderData->order_sn). 'WFT'. $orderData->id);

            } elseif ($is_having->status == 2) {
                $alipay = new Alipay();
                $back = $alipay->aliPwdRefund($orderData->trade_no, $orderData->back_money, date('YmdHis'). 'WFT'. intval($orderData->order_sn). 'WFT'. $orderData->id);
            } else {
                return array('status' => 1, 'message' => '成功');
            }
        } else {
            return array('status' => 0, 'message' => '条件不符合');
        }

        if ($back['status'] == 0) {
            $this->_getPlayOrderActionTable()->insert(array(
                'order_id' => $orderData->order_sn,
                'play_status' => 102,
                'action_user' => $orderData->user_id,
                'action_note' => '确认退款失败_:'. $back['message'],
                'dateline' => time(),
                'action_user_name' => '管理员' . $_COOKIE['user'],
                'code_id' => $id,
            ));

            return array('status' => 0, 'message' => $back['message']);
        }

        if ($orderData->account_type == 'alipay') {
            return array('status' => 1, 'message' => '成功');
        } else {
            return $this->backOk($orderData->order_sn, $orderData->id. $orderData->password, 2);
        }
    }

    public function aliLog($params)
    {
        $Adapter = $this->_getAdapter();

        if ($params['type'] == 'insert') {

            $s = $Adapter->query("INSERT INTO play_alipay_refund_log (trade_no, order_sn, code_id, back_money, status, batch_no, order_type) VALUES ('{$params['trade_no']}', {$params['order_sn']}, {$params['id']}, {$params['back_money']}, 0, '0', 2)", array())->count();
            if (!$s) {
                return array('status' => 0, 'message' => '插入支付宝退款记录失败');
            } else {
                return array('status' => 1, 'message' => '成功');
            }

        } elseif ($params['type'] == 'success') {

            $s = $Adapter->query("update play_alipay_refund_log set status=1 WHERE trade_no = ? AND order_sn = ? AND code_id = ?", array($params['trade_no'], $params['order_sn'], $params['id']))->count();

            if (!$s) {
                $this->errorLog("支付宝退款 更新成功记录失败". print_r($params, true). "\n");
                return array('status' => 0, 'message' => ' 更新成功记录失败');
            }

            $codeData = $this->_getPlayCouponCodeTable()->get(array('id' => $params['id']));

            return $this->backOk($params['order_sn'], $codeData->id.$codeData->password, 2);

        } elseif ($params['type'] == 'fail') {

            $s = $Adapter->query("update play_alipay_refund_log set status=2, reason=? WHERE trade_no = ? AND order_sn = ? AND code_id = ?", array($params['mes'], $params['trade_no'], $params['order_sn'], $params['id']))->count();

            if (!$s) {
                $this->errorLog("支付宝退款 更新失败记录失败". print_r($params, true). "\n");
            }

            return array('status' => 1, 'message' => '成功');

        } else {
            return array('status' => 0, 'message' => '非法操作');
        }

    }

    /**
     * 支付宝批量确认退款
     * @param $codeIds //使用码id 数组
     * @return array
     */
    public function backAlipay($codeIds) {

        $adapter = $this->_getAdapter();

        $id = trim(implode(',', $codeIds), ',');

        if (!$id) {
            return array('status' => 0, 'message' => '没有符合条件的id');
        }

        $up_sql_r = "SELECT
	play_order_info.trade_no,
	SUM(play_coupon_code.back_money) AS back_account
FROM
	play_order_info
LEFT JOIN play_coupon_code ON play_coupon_code.order_sn = play_order_info.order_sn
WHERE
	play_order_info.order_status = 1
AND play_order_info.pay_status >= 2
AND play_coupon_code.check_status = 2
AND play_coupon_code.`force` = 2
AND play_coupon_code.id IN ({$id})
GROUP BY
	play_order_info.order_sn";

        $up_data_r = $adapter->query($up_sql_r, array());

        if (!$up_data_r->count()) {
            return array('status' => 0, 'message' => '没有符合条件的');
        }

        $trade_no = '';
        $queryData = array();
        foreach ($up_data_r as $sda) {
            array_push($queryData, $sda['trade_no'] . '^' . $sda['back_account'] . '^' . '协商退款');
            $trade_no = $trade_no. ','. $sda['trade_no'];
        }

        $trade_no = trim($trade_no, ',');

        //如果有处理中的 暂停此操作
        $is_doing = $adapter->query("SELECT * FROM play_alipay_refund_log WHERE status = 3 AND trade_no IN ({$trade_no})", array())->current();
        if ($is_doing) {
            return array('status' => 0, 'message' => '有正在处理的, 请稍等');
        }

        $aliPay = new Alipay();
        $result = $aliPay->aliRefund($queryData);

        $insertWord = '';
        $batch_no = $result['batch_no'];

        $up_sql = "SELECT
	play_coupon_code.id,
    play_coupon_code.password,
    play_coupon_code.back_money,
	play_order_info.order_sn,
	play_order_info.trade_no
FROM
	play_coupon_code
LEFT JOIN play_order_info ON  play_coupon_code.order_sn = play_order_info.order_sn
WHERE play_coupon_code.id IN ({$id}) AND play_coupon_code.`force` = 2 AND play_coupon_code.check_status = 2";

        $up_data = $adapter->query($up_sql, array());
        foreach ($up_data as $code) {
            $trade_no = $code['trade_no'];
            $order_sn = intval($code['order_sn']);
            $code_id = intval($code['id']);
            $back_money = $code['back_money'];
            $insertWord .= "('{$trade_no}',  {$order_sn},  {$code_id}, {$back_money}, 0, '{$batch_no}'),";
        }
        $insertWord = trim($insertWord, ',');

        $s = $adapter->query("INSERT INTO play_alipay_refund_log (trade_no, order_sn, code_id, back_money, status, batch_no) VALUES $insertWord", array())->count();
        if (!$s) {
            return array('status' => 0, 'message' => '插入支付宝记录出错');
        }

        return array('status' => 1, 'message' => $result['html']);

    }

    /**
     * 支付宝回调
     * @param $trade_no
     * @param $money
     * @param $batch_no
     * @return bool
     */
    public function aliPayRefundMoney($trade_no, $money, $batch_no) {

        $adapter = $this->_getAdapter();

        $sql_back = "SELECT play_alipay_refund_log.code_id, play_alipay_refund_log.order_sn, play_alipay_refund_log.back_money FROM play_alipay_refund_log
WHERE play_alipay_refund_log.trade_no = ? AND play_alipay_refund_log.status = 3 AND play_alipay_refund_log.batch_no = ?";

        $backData = $adapter->query($sql_back, array($trade_no, $batch_no));

        if (!$backData->count()) {
            return false;
        }

        $s1 = $adapter->query("update play_alipay_refund_log set status=1 WHERE batch_no=? AND trade_no=?", array($batch_no, $trade_no))->count();
        if (!$s1) {
            return false;
        }

        $back_money = 0;

        foreach ($backData as $value) {
            $codeData = $this->_getPlayCouponCodeTable()->get(array('id' => $value['code_id']));
            $this->backOk($value['order_sn'], $codeData->id. $codeData->password, 2);
            //todo 退款失败 开始记录


            $back_money = $back_money + $value['back_money'];
        }

        if ($back_money == $money) {
            return true;
        } else {
            return false;
        }

    }



}
