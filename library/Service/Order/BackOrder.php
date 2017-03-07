<?php

namespace library\Service\Order;

use library\Fun\M;

class BackOrder
{
    //提交特殊退款
    public static function SpecialBack($order_sn, $code_id, $reason, $back_money = null)
    {
        $orderData = M::getPlayOrderInfoTable()->get(array('order_sn' => $order_sn, 'pay_status >= ?' => 2, 'order_status' => 1, 'approve_status' => 2));

        if (!$orderData) {
            return array('status' => 0, 'message' => '订单状态不正确');
        }

        if ($orderData->order_type == 2) {
            //商品特殊退款
            $codeData = M::getPlayCouponCodeTable()->get(array('order_sn' => $order_sn, 'id' => $code_id));
            if (!$codeData || $codeData->force != 0 || $codeData->back_money != 0) {
                return array('status' => 0, 'message' => '商品使用码状态不正确');
            }

            if (!($codeData->status == 1 || ($codeData->status == 2 && $orderData->pay_status == 6))) {//已使用特殊退款
                return array('status' => 0, 'message' => '商品使用码状态不在特殊退款的状态');
            }

            $adapter = M::getAdapter();
            $conn = $adapter->getDriver()->getConnection();
            $conn->beginTransaction();

            if ($codeData->voucher_id) {
                $cash_data = $adapter->query("SELECT play_cashcoupon_user_link.* FROM play_cashcoupon_user_link WHERE id = ? ", array($codeData->voucher_id))->current();

                if ($cash_data and $cash_data->is_back == 0) {

                    if ($cash_data->back_money != $codeData->voucher) {
                        $codeData->voucher=bcsub($codeData->voucher,$cash_data->back_money,2); //剩余可退金额
                    }
                    if ($orderData->coupon_unit_price >= $codeData->voucher) {
                        //代金券小于当前金额,销毁代金券,退款扣除代金券后的剩余金额
                        $result1 = $adapter->query("UPDATE play_cashcoupon_user_link SET is_back = 1, back_money = ? WHERE id = ?", array($codeData->voucher, $codeData->voucher_id))->count();
                        if (!$result1) {
                            $conn->rollback();
                            return array('status' => 0, 'message' => '失败');
                        }
                        $back_money = bcsub($orderData->coupon_unit_price, $codeData->voucher, 2);

                    } else {
                        //代金券大于当前金额,用户退款金额为0,记录代金券剩余金额以供下次扣除
                        $result2 = $adapter->query("UPDATE play_cashcoupon_user_link SET back_money = back_money+ {$orderData->coupon_unit_price} WHERE id = ?", array($codeData->voucher_id))->count();
                        if (!$result2) {
                            $conn->rollback();
                            return array('status' => 0, 'message' => '失败!');
                        }
                        $back_money = 0;
                    }
                } else {
                    $back_money = $orderData->coupon_unit_price;
                }
            } else {
                $back_money = $orderData->coupon_unit_price;
            }

            $s1 = $adapter->query("UPDATE play_coupon_code SET play_coupon_code.`force` = 2, back_money = ?, back_time = ?, accept_time = ? WHERE id = ?", array($back_money, time(), time(), $code_id))->count();

            if (!$s1) {
                $conn->rollback();
                return array('status' => 0, 'message' => '提交特殊退款失败');
            }

            // 记录操作日志
            $actionData = array(
                $codeData->order_sn,
                14, //提交特殊退款
                $_COOKIE['id'], //$_COOKIE['id'], //操作人改为 使用码id
                $reason ? '提交 && 受理特殊退款： '. $reason : '提交 && 受理特殊退款',
                time(),
                $_COOKIE['user'],
                $code_id
            );

            $s2 = $adapter->query("INSERT INTO play_order_action (action_id, order_id, play_status, action_user, action_note, dateline, action_user_name, code_id) VALUES (NULL ,?, ?, ?, ?, ?, ?, ?)", $actionData)->count();

            if (!$s2) {
                $conn->rollback();
                return array('status' => 0, 'message' => '提交退款失败!');
            }

            $conn->commit();
            return array('status' => 1, 'message' => '提交特殊退款成功');
        }

        return array('status' => 0, 'message' => '订单类型不正确');

    }
}
