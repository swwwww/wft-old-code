<?php
namespace Deyi\OrderAction;

use Application\Module;
use Deyi\Account\Account;
use Deyi\BaseController;
use Deyi\Integral\Integral;
use Deyi\Invite\Invite;
use Deyi\Seller\Seller;
use Deyi\SendMessage;
use Deyi\WeiSdkPay\WeiPay;
use Deyi\WeiXinPay\WeiXinPayFun;
use Deyi\Alipay\Alipay;
use Deyi\Unionpay\Unionpay;
use library\Fun\M;
use library\Service\Kidsplay\Kidsplay;
use library\Service\System\Logger;
use library\Service\User\Member;
use Zend\Db\Sql\Expression;

class OrderExcerciseBack
{

    use BaseController;


    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }


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

        //付款状态 ;0未付款;1付款中;2已付款 3  退款中 4 退款成功 5已使用 6已过期 7团购中
        //使用状态,0未使用,1已使用,2已退款,3退款中

        //订单信息
        $order_data = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));
        $code_data = $this->_getPlayExcerciseCodeTable()->get(array('code' => $code,'order_sn'=>$order_sn));
        if (!$order_data || !$code_data || $order_data->pay_status < 2) {
            return array('status' => 0, 'message' => '订单未付款');
        }

        if ($code_data->status!=0) {
            return array('status' => 0, 'message' => '验证码已退款或已使用');
        }

        $data_excercise_event = $this->_getPlayExcerciseEventTable()->get(array('id' => $order_data->coupon_id));

        //退款时间
        $refund_time = $data_excercise_event->back_time;
        if (!$refund_time || ($refund_time < time() && $group == 1)) {
            return array('status' => 0, 'message' => '退款时间已过,退款失败');
        }

        //后台退款
        if ($group == 2 && $code_data->price < $money) {
            return array('status' => 0, 'message' => '指定退款金额不能高于原先价格');
        }

        //收费项
        $data_price_name = $this->_getPlayExcercisePriceTable()->get(array('id' => $code_data->pid));
        if (!$data_price_name) {
            return array('status' => 0, 'message' => '异常');
        }

        //是否还有未使用的
        $no_use = $this->_getPlayExcerciseCodeTable()->fetchAll(array('order_sn'=>$order_sn,'status'=>0))->count();

        // 设为退款中状态
        if ($group == 2 && $money) {
            if ($code_data->use_free_coupon > 0) {
                $back_money = 0;
            } else {
                $back_money = $money;
            }
        } else {
            if ($code_data->use_free_coupon > 0) {
                $back_money = 0;
            } else {
                //是否使用代金券
                if ($order_data->voucher_id) {
                    $cash_data = $this->_getCashCouponUserTable()->get(array('id' => $order_data->voucher_id));
                    if ($cash_data and $cash_data->is_back == 0) {
                        if ($cash_data->back_money != $order_data->voucher) {
                            $order_data->voucher = bcsub($order_data->voucher, $cash_data->back_money, 2); //剩余可退金额
                        }
                        if ($code_data->price >= $order_data->voucher) {
                            //代金券小于当前金额,销毁代金券,退款扣除代金券后的剩余金额
                            $this->_getCashCouponUserTable()->update(array('is_back' => 1, 'back_money' => $order_data->voucher), array('id' => $order_data->voucher_id));
                            $back_money = bcsub($code_data->price, $order_data->voucher, 2);
                        } else {
                            //代金券大于当前金额,用户退款金额为0,记录代金券剩余金额以供下次扣除
                            $this->_getCashCouponUserTable()->update(array('back_money' => new  Expression('back_money+' . $code_data->price)), array('id' => $order_data->voucher_id));
                            $back_money = 0;
                        }

                    } else {
                        $back_money = $code_data->price;
                    }
                } else {
                    $back_money = $code_data->price;
                }
            }
        }

        $timer = time();
        $PDO = M::getAdapter();
        $conn = $PDO->getDriver()->getConnection();
        $conn->beginTransaction();

        $s1 = $PDO->query("UPDATE play_excercise_code SET status=3, back_time={$timer}, back_money={$back_money} WHERE order_sn={$order_sn} AND code={$code} AND status = 0", array())->count();
        if (!$s1) {
            $conn->rollBack();
            Logger::WriteErrorLog('退款失败 更新状态 订单id: '. $order_sn. "code :". $code ."\r\n");
            return array('status' => 0, 'message' => '退款失败');
        }

        //是否还有未使用的
        if($no_use == 1){
            $s2 = $PDO->query("UPDATE play_order_info SET pay_status=3, backing_number=backing_number+1 WHERE order_sn={$order_sn}", array())->count();
        }else{
            $s2 = $PDO->query("UPDATE play_order_info SET backing_number=backing_number+1 WHERE order_sn={$order_sn}", array())->count();
        }

        if (!$s2) {
            $conn->rollBack();
            Logger::WriteErrorLog('退款失败 更新订单状态 订单id: '. $order_sn. "code :". $code ."\r\n");
            return array('status' => 0, 'message' => '退款失败');
        }

        // 恢复卡券购买数
        if ($code_data->use_free_coupon > 0) {

            $data_member_free_coupon = $PDO->query("SELECT id FROM play_cashcoupon_user_link WHERE pay_time > 0 AND cid = 0 AND uid = ? ORDER BY use_etime DESC LIMIT {$code_data->use_free_coupon} ;", array($order_data->user_id));


            $data_free_coupon_ids = '';
            foreach ($data_member_free_coupon as $key => $val) {
                $data_free_coupon_ids .= $val->id . ',';
            }
            $data_free_coupon_ids = rtrim($data_free_coupon_ids, ',');

            //更新用户的 免费券状态 用户的免费数量
            $m1 = $PDO->query("UPDATE play_cashcoupon_user_link SET pay_time = 0 WHERE pay_time > 0 AND cid = 0 AND uid = ? AND id in ({$data_free_coupon_ids})", array($order_data->user_id))->count();
            $m2 = $PDO->query("UPDATE play_member SET member_free_coupon_count_now = member_free_coupon_count_now+{$code_data->use_free_coupon} WHERE member_user_id = ?", array($order_data->user_id))->count();

            if (!$m1 || !$m2) {
                Logger::WriteErrorLog("退款失败 更新活动用户免费券失败{$m1} {$m2} 订单id: ". $order_sn. "\r\n");
                $conn->rollBack();
                return array('status' => 0, 'message' => '更新活动用户免费券失败');
            }


            $service_kidsplay = new Kidsplay();
            $data_free_coupon_event_count = $service_kidsplay->getFreeCouponEventCount($order_data->bid);
            $s3 = $PDO->query("UPDATE play_excercise_base SET join_number=join_number-{$code_data->person}, join_ault=join_ault-{$data_price_name->person_ault}, join_child=join_child-{$data_price_name->person_child}, free_coupon_event_count = {$data_free_coupon_event_count} WHERE id={$order_data->bid}", array())->count();
            $s4 = $PDO->query("UPDATE play_excercise_price SET buy_number=buy_number-1, free_coupon_join_count=free_coupon_join_count-1 WHERE id={$code_data->pid}", array())->count();
            Logger::writeLog('临时监控：' . __DIR__ . print_r(1, 1));
        } else {
            $s3 = $PDO->query("UPDATE play_excercise_base SET join_number=join_number-{$code_data->person}, join_ault=join_ault-{$data_price_name->person_ault}, join_child=join_child-{$data_price_name->person_child} WHERE id={$order_data->bid}", array())->count();
            $s4 = $PDO->query("UPDATE play_excercise_price SET buy_number=buy_number-1 WHERE id={$code_data->pid}", array())->count();
        }

        if (!$s3 || !$s4) {
            $conn->rollBack();
            Logger::WriteErrorLog("退款失败 更新base price 数量{$s3}_{$s4}订单id: ". $order_sn. "code :". $code ."\r\n");
            return array('status' => 0, 'message' => '退款失败');
        }

        $s5 = $PDO->query("UPDATE play_excercise_event SET join_number=join_number-{$code_data->person}, join_ault=join_ault-{$data_price_name->person_ault}, join_child=join_child-{$data_price_name->person_child} WHERE id={$order_data->coupon_id}", array())->count();

        if (!$s5) {
            $conn->rollBack();
            Logger::WriteErrorLog("退款失败 更新场次失败 订单id: ". $order_sn. "code :". $code ."\r\n");
            return array('status' => 0, 'message' => '退款失败');
        }

        $conn->commit();

        //查询是否所有已退订,删除对应出行人,如果存在的话
        $back_ok = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn,'buy_number' => new Expression('backing_number+back_number')));
        if($back_ok){
            $this->_getPlayOrderInsureTable()->delete(array('order_sn' => $order_sn));
        }

        //分销
        $Seller = new Seller();
        $Seller->back($order_sn, $code_data->id);

        // 记录操作日志
        $this->_getPlayOrderActionTable()->insert(array(
            'order_id' => $order_sn,
            'play_status' => 3,
            'action_user' => $order_data->user_id,
            'action_note' => '退款中',
            'dateline' => time(),
            'action_user_name' => ($group == 1) ? '用户' . $order_data->username : '管理员' . $_COOKIE['user'],
            'code_id' => $code_data->id,
        ));

        if ($back_money <= 5) { //低于200的自动受理退款

            $s2 = $this->_getPlayExcerciseCodeTable()->update(array('accept_status' => 2, 'accept_time' => time()), array('order_sn' => $order_sn, 'code' => $code));

            if ($s2) {
                $this->_getPlayOrderActionTable()->insert(array(
                    'action_user' => $order_data->user_id,
                    'order_id' => $order_sn,
                    'play_status' => 7,
                    'action_note' => '低于5的系统自动受理退款',
                    'dateline' => time(),
                    'action_user_name' => '系统',
                    'code_id' => $code_data->id
                ));
            }

        }

        if ($back_money > 0) { //如果退款金额是0 里面确认退款成功
            if ($group == 1) {
                $data_refund_status = SendMessage::MESSAGE_STATUS_REFUND_REFUND;
            } else {
                $data_refund_status = SendMessage::MESSAGE_STATUS_ADMIN_REFUND;
            }

            $data_message_param = array(
                'phone'          => $order_data->buy_phone,
                'goods_name'     => $order_data->coupon_name,
                'game_name'      => $data_price_name->price_name,
                'game_time'      => $data_excercise_event->start_time,
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
                'message_type'   => SendMessage::MESSAGE_TYPE_ACTIVITY,
            );

            SendMessage::sendMessageToUser($data_message_param);
        } else {
            return $this->backOk($order_sn, $code, 1);
        }

        return array('status' => 1, 'message' => '退款成功');

    }

    /**
     * 成功退订
     * @param $order_sn |订单号
     * @param $code |完整的验证码
     * @param $group |谁执行的退款 1 用户 (已过退款时间) 2 管理员
     * @return array
     */
    public function backOk($order_sn, $code, $group = 1)
    {

        //付款状态 ;0未付款;1付款中;2已付款 3  退款中 4 退款成功 5已使用 6已过期 7团购中
        //使用状态,0未使用,1已使用,2已退款,3退款中

        //订单信息
        $order_data = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));
        $code_data = $this->_getPlayExcerciseCodeTable()->get(array('code' => $code, 'order_sn' => $order_sn));
        $data_price_name = $this->_getPlayExcercisePriceTable()->get(array('id' => $code_data->pid));
        $data_excercise_event = $this->_getPlayExcerciseEventTable()->get(array('id' => $code_data->eid));

        if (!$order_data or !$code_data) {
            return array('status' => 0, 'message' => '订单或验证码不存在');
        }

        if ($code_data->status == 3) { //正常退款

            // 根据订单号 成功退款
            $s1 = $this->_getPlayExcerciseCodeTable()->update(array('status' => 2, 'accept_status' => 3, 'back_money_time' => time()), array('order_sn' => $order_sn, 'code' => $code));
            if (!$s1) {
                return array('status' => 0, 'message' => '操作失败1');
            }

            //检查除此之外是否还有等待退订的
            $res = $this->_getPlayExcerciseCodeTable()->fetchAll(array('order_sn' => $order_sn, 'status' => 3))->count();
            if ($res) {
                $s2 = $this->_getPlayOrderInfoTable()->update(array('back_number' => new Expression('back_number+1'),'backing_number'=>new Expression('backing_number-1')), array('order_sn' => $order_sn));
            } else {
                $s2 = $this->_getPlayOrderInfoTable()->update(array('pay_status' => 4, 'back_number' => new Expression('back_number+1'),'backing_number'=>0), array('order_sn' => $order_sn));
            }
        } elseif ($code_data->status == 1 && $code_data->accept_status == 2) {
            $s2 = $this->_getPlayExcerciseCodeTable()->update(array('accept_status' => 3, 'back_money_time' => time()), array('order_sn' => $order_sn, 'code' => $code));
            if (!$s2) {
                return array('status' => 0, 'message' => '操作失败2');
            }
        } else {
            return array('status' => 0, 'message' => '条件不符合');
        }


        if ($s2) {

            //如果是使用账户购买
            if ($code_data->back_money > 0 and $order_data->account_money > 0) {
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
                        'code_id' => $code_data->id,
                    ));
                    return array('status' => 0, 'message' => '退款失败');
                }

                $data_message_param = array(
                    'phone'          => $order_data->buy_phone,
                    'goods_name'     => $order_data->coupon_name,
                    'game_name'      => $data_price_name->price_name,
                    'game_time'      => $data_excercise_event->start_time,
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
                    'message_type'   => SendMessage::MESSAGE_TYPE_ACTIVITY,
                );

                SendMessage::sendMessageToUser($data_message_param);
            }

            // 记录操作日志
            $this->_getPlayOrderActionTable()->insert(array(
                'order_id' => $order_data->order_sn,
                'play_status' => 4,
                'action_user' => $order_data->user_id,
                'action_note' => ($group == 1) ? "退款是0元 直接退款成功" : "退款成功,卡券密码{$code},金额{$code_data->back_money}元",
                'dateline' => time(),
                'action_user_name' => ($group == 1) ? '用户' . $order_data->username : '管理员' . $_COOKIE['user'],
                'code_id' => $code_data->id,
            ));

            // if ($code_data->status != 1) {
            //     SendMessage::Send9($order_data->buy_phone, $order_data->coupon_name,$order_data->order_city);
            // }

            $message = "兑换码为{$code}的{$order_data->coupon_name}，已经退款完成，退款已返回至您支付的账户，请查看";
            $this->_getPlayUserMessageTable()->insert(array(
                'uid' => $order_data->user_id,
                'type' => 8,
                'title' => '退款完成',
                'deadline' => time(),
                'message' => $message,
                'link_id' => json_encode(array(
                    'type' => 'kidsplay',
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
     * 确认退款
     * @param $id //使用码id
     * @return array
     */
    public function refundMoney($id) {

        $adapter = $this->_getAdapter();

        $sql = "SELECT
	play_excercise_code.id,
	play_excercise_code.code,
	play_excercise_code.order_sn,
	play_excercise_code.back_money,
	play_order_info.account_type,
	play_order_info.trade_no,
	play_order_info.real_pay,
	play_order_info.user_id,
	play_order_info.coupon_name
FROM
	play_excercise_code
LEFT JOIN play_order_info ON play_order_info.order_sn = play_excercise_code.order_sn
WHERE
	play_order_info.order_status = 1
AND play_order_info.pay_status >= 2
AND play_order_info.order_type = 3
AND play_order_info.approve_status = 2
AND play_excercise_code.accept_status = 2
AND play_excercise_code.id = ?";

        $data = $adapter->query($sql, array($id));
        if (!$data->count()) {
            return array('status' => 0, 'message' => '条件不符合');
        }

        $orderData = $data->current();

        if (!in_array($orderData->account_type, array('weixin','union', 'new_jsapi', 'alipay', 'account')) || !$orderData->back_money) {
            return array('status' => 0, 'message' => '条件不符合_支付方式');
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
             $back = array('status' => 1, 'message' => 'ok');
        } elseif ($orderData->account_type == 'alipay') { //单个支付宝退款

            $is_having = $adapter->query("SELECT * FROM play_alipay_refund_log WHERE order_type = 3 AND trade_no = ? AND order_sn = ? AND code_id = ?", array($orderData->trade_no, $orderData->order_sn, $orderData->id))->current();

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
            return $this->backOk($orderData->order_sn, $orderData->code, 2);
        }

    }

    public function aliLog($params)
    {
        $Adapter = $this->_getAdapter();

        if ($params['type'] == 'insert') {

            $s = $Adapter->query("INSERT INTO play_alipay_refund_log (trade_no, order_sn, code_id, back_money, status, batch_no, order_type) VALUES ('{$params['trade_no']}', {$params['order_sn']}, {$params['id']}, {$params['back_money']}, 0, '0', 3)", array())->count();
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

            $codeData = $this->_getPlayExcerciseCodeTable()->get(array('id' => $params['id']));
            return $this->backOk($params['order_sn'], $codeData->code, 2);

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
	SUM(
		play_excercise_code.back_money
	) AS back_account
FROM
	play_excercise_code
LEFT JOIN play_order_info ON play_excercise_code.order_sn = play_order_info.order_sn
WHERE
	play_order_info.order_status = 1
AND play_order_info.pay_status >= 2
AND play_order_info.order_type = 3
AND play_order_info.approve_status = 2
AND play_excercise_code.accept_status = 2
AND play_excercise_code.id IN ({$id})
GROUP BY
	play_order_info.order_sn";

        $up_data_r = $adapter->query($up_sql_r, array());

        if (!$up_data_r->count()) {
            return array('status' => 0, 'message' => '没有符合条件的');
        }

        $queryData = array();
        foreach ($up_data_r as $sda) {
            array_push($queryData, $sda['trade_no'] . '^' . $sda['back_account'] . '^' . '协商退款');
        }

        $aliPay = new Alipay();
        $result = $aliPay->aliRefund($queryData);

        $insertWord = '';
        $batch_no = $result['batch_no'];

        $up_sql = "SELECT
	play_excercise_code.id,
	play_excercise_code.back_money,
	play_excercise_code.order_sn,
	play_order_info.trade_no
FROM
	play_excercise_code
LEFT JOIN play_order_info ON play_order_info.order_sn = play_excercise_code.order_sn
WHERE
	play_order_info.order_status = 1
AND play_order_info.pay_status >= 2
AND play_order_info.order_type = 3
AND play_order_info.approve_status = 2
AND play_excercise_code.accept_status = 2
AND play_excercise_code.id IN ({$id})";
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
     * 支付宝确认退款 回调
     * @param $trade_no
     * @param $money
     * @param $batch_no
     * @return bool
     */
    public function aliPayRefundActivityMoney($trade_no, $money, $batch_no) {

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
            $codeData = $this->_getPlayExcerciseCodeTable()->get(array('id' => $value['code_id']));
            $this->backOk($value['order_sn'], $codeData->code, 2);
            $back_money = $back_money + $value['back_money'];
        }

        if ($back_money == $money) {
            return true;
        } else {
            return false;
        }

    }



}
