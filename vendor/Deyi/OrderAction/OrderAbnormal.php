<?php

namespace Deyi\OrderAction;

use Deyi\BaseController;
use Application\Module;
use library\Fun\M;
use library\Service\System\Logger;
use Zend\Db\Sql\Predicate\Expression;

class OrderAbnormal{

    use BaseController;

    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }

    /**
     * 驳回订单中受理退款的操作
     *
     * @param $id //使用码id
     * @param $order_sn //订单id
     * @param $type //类型 1商品 2活动
     * @return array
     */
    public function rollBacked($id, $order_sn, $type)
    {
        $code_id = intval($id);
        $order_sn = intval($order_sn);
        $type = intval($type);
        $Adapter = $this->_getAdapter();

        if (!in_array($type, array(1, 2)) || !$code_id || !$order_sn) {
            return array('status' => 0, 'message' => '你来到了无人区');
        }

        //检查该使用码的状态;
        $stay = false;

        if ($type == 1) { //商品

            $sql = "SELECT
	play_coupon_code.id
FROM
	play_coupon_code
INNER JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
WHERE
    play_coupon_code.force = 2
AND	play_coupon_code.id = ?
AND play_order_info.order_sn = ?";

            $stay = $Adapter->query($sql, array($code_id, $order_sn))->count();
        }

        if ($type == 2) { //活动

            $sql = "SELECT
	play_excercise_code.id
FROM
	play_excercise_code
INNER JOIN play_order_info ON play_order_info.order_sn = play_excercise_code.order_sn
WHERE
    play_excercise_code.accept_status = 2
AND	play_excercise_code.id = ?
AND play_order_info.order_sn = ?";
            $stay = $Adapter->query($sql, array($code_id, $order_sn))->count();

        }

        if (!$stay) {
            return array('status' => 0, 'message' => '订单的状态不正确');
        }

        switch ($type) {
            case 1:
                $message = $this->backedGoods($code_id, $order_sn);
                break;
            case 2:
                $message = $this->backedActivity($code_id, $order_sn);
                break;
            default :
                $message = array('status' => 0, 'message' => '非法操作');
        }

        return $message;
    }

    private function backedGoods($code_id, $order_sn)
    {
        $codeData = $this->_getPlayCouponCodeTable()->get(array('id' => $code_id, 'order_sn' => $order_sn));

        if ($codeData->status == 3) {//正常受理退款驳回
            $result = $this->_getPlayCouponCodeTable()->update(array('force' => 0, 'accept_time' => 0), array('id' => $code_id));

        } elseif ($codeData->status == 1) {//已使用受理退款驳回

            //$result = false;
            return $message = array('status' => 0, 'message' => '特殊退款暂不支持驳回');


        } else {
            $result = false;
        }

        if (!$result) {
            return $message = array('status' => 0, 'message' => '驳回受理退款失败');
        }

        $this->_getPlayOrderActionTable()->insert(array(
            'order_id' => $order_sn,
            'play_status' => 103,
            'action_user' => $_COOKIE['id'],
            'action_note' => '驳回受理退款',
            'dateline' => time(),
            'action_user_name' => '管理员' . $_COOKIE['user'],
            'code_id' => $code_id,
        ));

        return $message = array('status' => 1, 'message' => '成功');

    }

    private function backedActivity($code_id, $order_sn)
    {
        $codeData = $this->_getPlayExcerciseCodeTable()->get(array('id' => $code_id, 'order_sn' => $order_sn));

        if ($codeData->status == 3) {//正常受理退款驳回

            $result = $this->_getPlayExcerciseCodeTable()->update(array('accept_status' => 0, 'accept_time' => 0), array('id' => $code_id, 'accept_status' => 2));

        } elseif ($codeData->status == 1) {//已使用受理退款驳回

            $result = $this->_getPlayExcerciseCodeTable()->update(array('accept_status' => 1, 'accept_time' => 0), array('id' => $code_id, 'accept_status' => 2));

        } else {
            $result = false;
        }

        if (!$result) {
            return $message = array('status' => 0, 'message' => '驳回受理退款失败');
        }

        $this->_getPlayOrderActionTable()->insert(array(
            'order_id' => $order_sn,
            'play_status' => 103,
            'action_user' => $_COOKIE['id'],
            'action_note' => '驳回受理退款',
            'dateline' => time(),
            'action_user_name' => '管理员' . $_COOKIE['user'],
            'code_id' => $code_id,
        ));

        return $message = array('status' => 1, 'message' => '成功');
    }


    /**
     * 驳回订单中提交退款的操作
     *
     * @param $id
     * @param $order_sn
     * @param $type
     * @return array
     */
    public function rollBackIng($id, $order_sn, $type)
    {
        $code_id = intval($id);
        $order_sn = intval($order_sn);
        $type = intval($type);
        $Adapter =  $this->_getAdapter();
        if (!in_array($type, array(1, 2)) || !$code_id || !$order_sn) {
            return array('status' => 0, 'message' => '你来到了无人区');
        }

        //检查该使用码的状态;
        $stay = false;

        if ($type == 1) { //商品 只有正常的提交退款

            $sql = "SELECT
	play_coupon_code.id
FROM
	play_coupon_code
INNER JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
WHERE
    play_coupon_code.status = 3
AND play_coupon_code.force = 0
AND	play_coupon_code.id = ?
AND play_order_info.order_sn = ?";

            $stay = $Adapter->query($sql, array($code_id, $order_sn))->count();

        }

        if ($type == 2) { //活动

            $sql = "SELECT
	play_excercise_code.id
FROM
	play_excercise_code
INNER JOIN play_order_info ON play_order_info.order_sn = play_excercise_code.order_sn
WHERE
    ((play_excercise_code.accept_status = 1 AND play_excercise_code.status = 1) OR (play_excercise_code.accept_status = 0 AND play_excercise_code.status = 3))
AND	play_excercise_code.id = ?
AND play_order_info.order_sn = ?";

            $stay = $Adapter->query($sql, array($code_id, $order_sn))->count();

        }

        if (!$stay) {
            return array('status' => 0, 'message' => '订单的状态不正确');
        }

        switch ($type) {
            case 1:
                $message = $this->backIngGoods($code_id, $order_sn);
                break;
            case 2:
                $message = $this->backIngActivity($code_id, $order_sn);
                break;
            default :
                $message = array('status' => 0, 'message' => '非法操作');
        }

        return $message;
    }


    private function backIngGoods($code_id, $order_sn)
    {

        $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));
        $codeData = $this->_getPlayCouponCodeTable()->get(array('id' => $code_id));

        if (!$codeData || !$orderData) {
            return array('status' => 0, 'message' => '非法操作');
        }

        $gameInfo = $this->_getPlayGameInfoTable()->get(array('id' => $orderData->bid));

        if (!$gameInfo || $gameInfo->total_num <= $gameInfo->buy) {
            return array('status' => 0, 'message' => '数量不够了 不允许退款');
        }

        //todo  事务处理

        $s1 = $this->_getPlayOrderInfoTable()->update(array('pay_status' => 2, 'backing_number' => new Expression('backing_number-1')), array('order_sn' => $order_sn));
        $s2 = $this->_getPlayCouponCodeTable()->update(array('status' => 0, 'back_time' => 0, 'back_money' => 0), array('id' => $code_id));
        if ($s1 && $s2) {
            $s3 = $this->_getPlayGameInfoTable()->update(array('buy' => new Expression('buy+1')), array('id' => $orderData->bid));
            $s4 = $this->_getPlayOrganizerGameTable()->update(array('buy_num' => new Expression('buy_num+1')), array('id' => $orderData->coupon_id));

        } else {
            return array('status' => 0, 'message' => '驳回退款操作失败');
        }

        //记录日志
        $this->_getPlayOrderActionTable()->insert(array(
            'order_id' => $order_sn,
            'play_status' => 100,
            'action_user' => $_COOKIE['id'],
            'action_note' => '用户异常退款, 回退到待使用的状态'.$s3.$s4,
            'dateline' => time(),
            'action_user_name' => '管理员' . $_COOKIE['user'],
            'code_id' => $code_id,
        ));

        return array('status' => 1, 'message' => '成功');
    }

    private function backIngActivity($code_id, $order_sn)
    {

        $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));
        $codeData = $this->_getPlayExcerciseCodeTable()->get(array('id' => $code_id, 'order_sn' => $order_sn));
        $eventData = $this->_getPlayExcerciseEventTable()->get(array('id' => $orderData->coupon_id));
        $priceData = M::getPlayExcercisePriceTable()->get(array('id' => $codeData->pid));

        if (!$codeData || !$orderData || !$eventData) {
            return array('status' => 0, 'message' => '非法操作');
        }

        if ($eventData->join_number >= $eventData->most_number && $codeData->status == 3) {
            return array('status' => 0, 'message' => '人数满了, 不允许驳回');
        }

        if ($codeData->use_free_coupon == 1) {
            // 判断亲子游的人数是否已满
            if ($priceData->free_coupon_join_count + 1 > $priceData->free_coupon_join_count) {
                return array('status' => 0, 'message' => '亲子游资格兑换已满, 不允许驳回');
            }
        }

        //todo  事务处理

        $data_result_s3 = true;
        $data_result_s4 = true;
        $data_result_s5 = true;
        if ($codeData->status == 3) {//正常受理退款驳回
            $pdo = M::getAdapter();
            $conn = $pdo->getDriver()->getConnection();
            $conn->beginTransaction();

            //改变订单 使用码
            $data_free_coupon_join = $codeData->use_free_coupon;
            $data_free_coupon_count= $data_free_coupon_join;
            $data_result_s1 = $pdo->query(" UPDATE play_order_info SET pay_status = 2, backing_number = backing_number - 1 WHERE order_sn = ?", array($order_sn))->count();

            $data_result_s2 = $pdo->query(" UPDATE play_excercise_code SET status = 0, back_time = 0, back_money = 0 WHERE id = ?", array($code_id))->count();

            if ($data_result_s1 && $data_result_s2) {
                // 同步亲子游场次数量
                $service_kidsplay             = new Kidsplay();
                $data_free_coupon_event_count = $service_kidsplay->getFreeCouponEventCount($orderData->bid);

                $data_result_s3 = $pdo->query(" UPDATE play_excercise_price SET buy_number = buy_number + 1, free_coupon_join_count = free_coupon_join_count + ? WHERE id = ?", array($data_free_coupon_join, $codeData->pid))->count();
                Logger::writeLog('临时监控：' . __DIR__ . print_r($data_free_coupon_join, 1));
                $data_result_s4 = $pdo->query(" UPDATE play_excercise_base SET join_number = join_number + ?, join_ault = join_ault + ?, join_child = join_child + ?, free_coupon_event_count = ? WHERE id = ?", array($codeData->person, $priceData->person_ault, $priceData->person_child, $data_free_coupon_event_count, $orderData->bid))->count();

                $data_result_s5 = $pdo->query(" UPDATE play_excercise_event SET join_number = join_number + ?, join_ault = join_ault + ?, join_child = join_child + ? WHERE id = ?", array($codeData->person, $priceData->person_ault, $priceData->person_child, $data_free_coupon_event_count, $orderData->coupon_id))->count();

                if ($data_result_s3 && $data_result_s4 && $data_result_s5) {
                    // 消耗亲子游次数
                    // 使用亲子游资格券
                    if ($data_free_coupon_count > 0) {
                        $service_member = new Member();

                        $pdo                     = M::getAdapter();
                        $data_member_free_coupon = $pdo->query(" SELECT id FROM play_cashcoupon_user_link WHERE pay_time = 0 AND cid = 0 AND uid = ? ORDER BY use_etime ASC LIMIT ? ", array($orderData->coupon_id, $data_free_coupon_count));
                        $data_member             = $service_member->getMemberData($orderData->user_id);

                        if ($data_member_free_coupon->count() != $data_free_coupon_count || $data_member['member_free_coupon_count_now'] < $data_free_coupon_count) {
                            $conn->rollback();
                            return array('status' => 0, 'message' => '亲子游次数不足，退款驳回失败');
                        }

                        $data_free_coupon_ids = '';
                        foreach ($data_member_free_coupon as $key => $val) {
                            $data_free_coupon_ids .= $val->id . ',';
                        }

                        $data_free_coupon_ids = rtrim($data_free_coupon_ids, ',');

                        $data_result_update_free_coupon = $pdo->query(" UPDATE play_cashcoupon_user_link SET pay_time = ?, use_object_id = ?, use_type = ? WHERE pay_time = 0 AND cid = 0 AND uid = ? AND use_etime > ? AND id in ({$data_free_coupon_ids}) ", array(time(), $order_sn, $eventData->id, $orderData->user_id, time()))->count();

                        if (!$data_result_update_free_coupon) {
                            $conn->rollback();
                            return ['status' => 0, 'message' => "操作过于频繁，亲子游次数使用失败，请稍后重试"];
                        }

                        $data_result_update_member = $pdo->query(" UPDATE play_member SET member_free_coupon_count_now = member_free_coupon_count_now - ? WHERE member_user_id = ? ", array($data_free_coupon_count, $orderData->user_id))->count();
                        if (!$data_result_update_member) {
                            $conn->rollback();
                            return ['status' => 0, 'message' => "您的亲子游次数不足，请可前往会员充值获取更多的亲子游次数"];
                        }
                    }

                    $conn->commit();
                } else {
                    $conn->rollback();
                    return array('status' => 0, 'message' => '失败');
                }
            } else {
                $conn->rollback();
                return array('status' => 0, 'message' => '失败');
            }

        } elseif ($codeData->status == 1) {//已使用受理退款驳回

            $result = $this->_getPlayExcerciseCodeTable()->update(array('accept_status' => 0, 'back_time' => 0, 'back_money' => 0, 'back_reason' => ''), array('id' => $code_id));
            if (!$result) {
                return array('status' => 0, 'message' => '失败');
            }

        } else {
            return array('status' => 0, 'message' => '非法操作');
        }

        $this->_getPlayOrderActionTable()->insert(array(
            'order_id' => $order_sn,
            'play_status' => 100,
            'action_user' => $_COOKIE['id'],
            'action_note' => '用户异常退款, 回退到待使用的状态'. $data_result_s3 . $data_result_s4 . $data_result_s5,
            'dateline' => time(),
            'action_user_name' => '管理员' . $_COOKIE['user'],
            'code_id' => $code_id,
        ));

        return array('status' => 1, 'message' => '成功');

    }


}


