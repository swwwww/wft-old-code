<?php
namespace library\Service\Order;

use library\Fun\M;
use library\Service\Kidsplay\Kidsplay;
use library\Service\ServiceManager;
use library\Service\System\Cache\KeyNames;
use library\Service\System\Cache\RedCache;
use library\Service\System\Logger;
use library\Service\User\Member;
use Zend\Db\Sql\Predicate\Expression;

class CancelOrder
{

    /**
     * 设置订单取消定时器
     * @param $order_sn
     */
    public static function Cancel($order_sn)
    {
        $tip = RedCache::get(RedCache::get(KeyNames::CANCEL_ORDER_ID). '_'. $order_sn);
        if (!$tip) {
            RedCache::set(RedCache::get(KeyNames::CANCEL_ORDER_ID). '_'. $order_sn, ServiceManager::getConfig('TRADE_CLOSED'));
            RedCache::lPush(RedCache::get(KeyNames::CANCEL_ORDER_LIST), $order_sn);
        }
    }

    //取消订单
    /**
     * @param $order_sn
     * @param int $uid |可选 用于验证订单所属者
     * @return array
     */
    public static function CancelOrder($order_sn,$uid=0)
    {

        $where=array('order_sn' => $order_sn, 'order_status' => 1, 'pay_status <= ?' => 1);
        if($uid){
            $where['user_id']=$uid;
        }
        $orderData = M::getPlayOrderInfoTable()->get($where);

        if (!$orderData) {
            return array('status' => 0, 'message' => '订单不存在');
        }

        $PDO = M::getAdapter();
        $conn = $PDO->getDriver()->getConnection();
        $conn->beginTransaction();

        $s1 = $PDO->query("UPDATE play_order_info SET order_status=0 WHERE order_sn={$order_sn} AND pay_status <=1 AND order_status = 1", array())->count();

        if (!$s1) {
            $conn->rollBack();
            Logger::WriteErrorLog('取消订单失败 更新状态 订单id: '. $order_sn. "\r\n");
            return array('status' => 0, 'message' => '更新订单状态失败');
        }

        if ($orderData->order_type == 2) {
            //处理数量
            if ($orderData->group_buy_id) { //团购处理

                $groupData = $PDO->query("SELECT * FROM play_group_buy WHERE id=?", array($orderData->group_buy_id))->current();
                if (!$groupData) {
                    $conn->rollBack();
                    Logger::WriteErrorLog('取消订单失败 获取团购数据 订单id: '. $order_sn. "\r\n");
                    return array('status' => 0, 'message' => '获取数据失败');
                }

                if ($groupData->index_uid == $orderData->user_id) {
                    $s2 = $PDO->query("UPDATE play_game_info SET buy=buy-{$groupData->limit_number} WHERE id={$orderData->bid}", [])->count();
                    $s3 = $PDO->query("UPDATE play_organizer_game SET buy_num=buy_num-{$groupData->limit_number} WHERE id={$orderData->coupon_id}", [])->count();

                } else {
                    $s2 = true;
                    $s3 = true;
                }

            } else {
                $s2 = (int)$PDO->query("UPDATE play_game_info SET buy=buy-{$orderData->buy_number} WHERE id={$orderData->bid}", [])->count();
                $s3 = (int)$PDO->query("UPDATE play_organizer_game SET buy_num=buy_num-{$orderData->buy_number} WHERE id={$orderData->coupon_id}", [])->count();
            }

            if (!$s2 || !$s3) {
                Logger::WriteErrorLog('取消订单失败 更新商品数量 订单id: '. $order_sn. "\r\n");
                $conn->rollBack();
                return array('status' => 0, 'message' => '更新商品数量失败');
            }

        }

        if ($orderData->order_type == 3) {

            $data_join_number = $PDO->query("SELECT sum(person_ault) as person_ault, sum(person_child) as person_child FROM `play_excercise_code` LEFT JOIN `play_excercise_price` ON (play_excercise_price.id = play_excercise_code.pid) WHERE play_excercise_code.order_sn = {$orderData->order_sn}", [])->current();
            $data_code = $PDO->query("select play_excercise_code.pid,play_excercise_code.use_free_coupon from play_excercise_code where order_sn = ? and use_free_coupon > 0", array($orderData->order_sn));

            if ($data_code->count()) {
                $data_free_coupon_count = 0;
                $data_price_item        = array();
                foreach ($data_code as $pq => $qc) {
                    $data_free_coupon_count    = $data_free_coupon_count + $qc->use_free_coupon;
                    $data_price_item[$qc->pid] = $data_price_item[$qc->pid] + 1;
                }

                $data_member_free_coupon = $PDO->query("SELECT id FROM play_cashcoupon_user_link WHERE pay_time > 0 AND cid = 0 AND uid = ? ORDER BY use_etime DESC LIMIT ? ", array($orderData->user_id, $data_free_coupon_count));

                $data_free_coupon_ids = '';
                foreach ($data_member_free_coupon as $key => $val) {
                    $data_free_coupon_ids .= $val->id . ',';
                }
                $data_free_coupon_ids = rtrim($data_free_coupon_ids, ',');

                //更新用户的 免费券状态 用户的免费数量
                $m1 = $PDO->query("UPDATE play_cashcoupon_user_link SET pay_time = 0 WHERE pay_time > 0 AND cid = 0 AND uid = ? AND id in ({$data_free_coupon_ids})", array($orderData->user_id))->count();
                $m2 = $PDO->query("UPDATE play_member SET member_free_coupon_count_now = member_free_coupon_count_now+{$data_free_coupon_count} WHERE member_user_id = ?", array($orderData->user_id))->count();

                if (!$m1 || !$m2) {
                    Logger::WriteErrorLog('取消订单失败 更新活动用户免费券失败 订单id: '. $order_sn. "\r\n");
                    $conn->rollBack();
                    return array('status' => 0, 'message' => '更新活动用户免费券失败');
                }

                $service_kidsplay = new Kidsplay();
                $data_free_coupon_event_count = $service_kidsplay->getFreeCouponEventCount($orderData->bid);

                $s2 = $PDO->query("UPDATE play_excercise_base SET `join_number`=join_number-{$orderData->people_number}, `join_ault`=join_ault-{$data_join_number->person_ault}, `join_child`=join_child-{$data_join_number->person_child}, `free_coupon_event_count` = {$data_free_coupon_event_count} WHERE `id`={$orderData->bid}", [])->count();
            } else {
                $s2 = $PDO->query("UPDATE play_excercise_base SET `join_number`=join_number-{$orderData->people_number}, `join_ault`=join_ault-{$data_join_number->person_ault}, `join_child`=join_child-{$data_join_number->person_child} WHERE `id`={$orderData->bid}", [])->count();
            }

            $s3 = (int)$PDO->query("UPDATE play_excercise_event SET `join_number`=join_number-{$orderData->people_number}, `join_ault`=join_ault-{$data_join_number->person_ault}, `join_child`=join_child-{$data_join_number->person_child} WHERE `id`={$orderData->coupon_id}", [])->count();

            if (!$s2 || !$s3) {
                $conn->rollBack();
                Logger::WriteErrorLog('取消订单失败 恢复活动数量错误 订单id: '. $order_sn. "\r\n");
                return array('status' => 0, 'message' => '恢复数量失败');
            }

            // 减去各个收费项的购买数
            $data_price = $PDO->query("SELECT play_excercise_price.id, count(*) as c_num FROM `play_excercise_code` LEFT JOIN `play_excercise_price` ON (play_excercise_price.id = play_excercise_code.pid) WHERE play_excercise_code.order_sn = {$orderData->order_sn} GROUP BY play_excercise_code.pid", []);
            $flag = false;
            foreach ($data_price as $key => $val) {
                $data_reset_count =  $data_price_item[$val->id] ? $data_price_item[$val->id] : 0;

                $s = (int)$PDO->query("UPDATE play_excercise_price SET `buy_number`=buy_number-{$val->c_num}, `free_coupon_join_count`=`free_coupon_join_count`-{$data_reset_count} WHERE `id`={$val->id}", [])->count();
                if (!$s) {
                    $flag = true;
                    break;
                }
            }

            if ($flag) {
                $conn->rollBack();
                Logger::WriteErrorLog('取消订单失败 恢复收费项购买数错误 订单id: '. $order_sn. "\r\n");
                return array('status' => 0, 'message' => '恢复收费项购买数失败');
            }
        }

        //还原代金券
        if ($orderData->voucher_id) {
            $use_cashCoupon = $PDO->query("UPDATE play_cashcoupon_user_link SET pay_time=0,use_order_id=0,use_object_id=0 WHERE id={$orderData->voucher_id} AND uid={$orderData->user_id}", [])->count();
            if (!$use_cashCoupon) {
                $conn->rollBack();
                Logger::WriteErrorLog('取消订单失败 还原代金券 订单id: '. $order_sn. "\r\n");
                return array('status' => 0, 'message' => '还原代金券失败');
            }
        }

        //还原资格券 如果有就处理
        if ($orderData->order_type == 2) {
            $PDO->query("update play_qualify_coupon set pay_time=0,use_order_id=0,pay_object_id=0 WHERE uid={$orderData->user_id} AND pay_object_id={$orderData->coupon_id} AND use_order_id={$orderData->order_sn}", [])->count();
        }

        //还原积分
        $integral_log = $PDO->query("SELECT * FROM play_integral WHERE uid = ? AND `type` = ? AND object_id = ? ", array($orderData->user_id, 102, $orderData->order_sn))->current();
        if ($integral_log) {
            $s1 = $PDO->query("DELETE FROM play_integral WHERE id={$integral_log->id}", array())->count();
            $s2 = $PDO->query("UPDATE play_integral_user SET total=total+{$integral_log->total_score} WHERE uid={$orderData->user_id}", [])->count();
            if (!$s1 or !$s2) {
                $conn->rollBack();
                Logger::WriteErrorLog('取消订单失败 还原积分 订单id: '. $order_sn. "\r\n");
                return array('status' => 0, 'message' => '还原积分失败');
            }
        }

        $conn->commit();
        return array('status' => 1, 'message' => '成功');

    }




}