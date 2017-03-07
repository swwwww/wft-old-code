<?php

namespace library\Service\User;

use library\Fun\M;
use library\Service\System\Cache\KeyNames;
use library\Service\System\Cache\RedCache;
use library\Service\System\Logger;

class Member {
    public function getVipSession () {
        $param = array();
        $data_return = RedCache::fromCacheData('D:member_money_service', function () use ($param) {
            $data_member_money_service_list = M::getPlayMemberMoneyServiceTable()->fetchAll(array('money_service_status > 0'), array('money_service_price' => 'asc', 'money_service_status' => 'desc', 'money_service_id' => 'asc'))->toArray();
            return $data_member_money_service_list;
        }, 0, true);
        return $data_return;
    }

    /**
     * @param $uid
     * @param $from_uid
     * @param string $city
     * @return bool
     */
    public function activateFreeCoupon ($uid, $from_uid, $city = 'WH', $pdo, $conn) {
        if (empty($from_uid) || empty($uid) || ($from_uid == $uid && !empty($from_uid) && !empty($uid))) {
            return true;
        }

        $data_member = M::getPlayMemberTable()->get(array('member_user_id' => $from_uid));

        if (empty($data_member)) {
            // 如果from_uid用户非会员，则赠送3次亲子游资格 By 2016/11/30 Toe
            $sql = " INSERT INTO play_cashcoupon_user_link (`cid`, `uid`, `create_time`, `use_stime`, `use_etime`, `get_order_id`, `get_info`, `use_type`, `price`, `title`, `city`) VALUE ";
            $sql .= "(0, " . (int)$from_uid . "," . time() . ", 0, " . (int)(time() + 86400 * 365) . ", 0, '充值分享激活的亲子游资格券', 2, 1, '亲子游资格券', '" . $city . "')";

            $data_free_coupon_result = $pdo->query($sql, array())->count();

            if (!$data_free_coupon_result) {
                $conn->rollback();
                Logger::WriteErrorLog("uid {$from_uid} 插入亲子游资格券失败\n");
                return false;
            }

            $sql  = " INSERT INTO play_member (`member_user_id`, `member_level`, `member_score`, `member_score_now`, `member_money`, `member_free_coupon_count`, `member_free_coupon_count_now`, `member_free_activation_coupon_count`, `member_share_recharge_count`, `member_create_time`, `member_update_time`) VALUE ";
            $sql .= "(" . (int)$from_uid . ", 0, 0, 0, 0, 1, 1, 2, 1, " . time() . ", " . time() . ")";
            $data_insert_member_result = $pdo->query($sql, array())->count();

            if (!$data_insert_member_result) {
                $conn->rollback();
                Logger::WriteErrorLog("uid {$from_uid} 创建VIP会员记录失败\n");
                return false;
            } else {
                return true;
            }
        } else {
            if ($data_member['member_free_activation_coupon_count'] <= 0) {
                // 不激活赠送亲子游，只增加分享数
                $data_result_update_member = $pdo->query(" UPDATE play_member SET member_share_recharge_count = member_share_recharge_count + 1 WHERE member_user_id = ?", array($from_uid))->count();
                return $data_result_update_member;
            } else {
                // 激活赠送亲子游，增加分享数
                $sql  = " INSERT INTO play_cashcoupon_user_link (`cid`, `uid`, `create_time`, `use_stime`, `use_etime`, `get_order_id`, `get_info`, `use_type`, `price`, `title`, `city`, `adminid`) VALUE ";
                $sql .= " (0, " . (int)$from_uid . "," . time() . ", 0, " . (int)(time() + 86400 * 365) . ", 0, '充值分享激活的亲子游资格券', 2, 1, '亲子游资格券', '" . $city . "', " . (int)$uid . ") ";

                $data_free_coupon_result = $pdo->query($sql, array())->count();

                if (!$data_free_coupon_result) {
                    $conn->rollback();
                    $this->errorLog("uid {$from_uid} 插入会员套餐的免费玩资格券失败\n");
                    return false;
                }

                $data_result_update_member = $pdo->query(
                    " UPDATE play_member SET 
                      member_free_coupon_count = member_free_coupon_count + 1, 
                      member_free_coupon_count_now = member_free_coupon_count_now + 1,
                      member_free_activation_coupon_count = member_free_activation_coupon_count - 1,
                      member_share_recharge_count = member_share_recharge_count + 1
                      WHERE member_user_id = ?
                    ",
                    array($from_uid)
                )->count();

                if (!$data_result_update_member) {
                    $this->errorLog("uid {$from_uid} 更新会员资格表失败\n");
                    return false;
                } else {
                    return true;
                }
            }
        }
    }

    public function getMemberData ($uid) {
        if (empty($uid)) {
            return false;
        }

        $param = array(
            'uid' => $uid
        );

        $data_member = RedCache::fromCacheData("D:MemberData:" . $uid, function () use ($param) {
            $data_temp_member = M::getPlayMemberTable()->get(array('member_user_id' => $param['uid']));
            return $data_temp_member;
        }, 0, true);
        return $data_member;
    }

    public function updateMemberDataByUid($new_data, $uid) {
        if (empty($new_data) || empty($uid)) {
            return false;
        }

        $data_param_where = array(
            'member_user_id' => $uid,
        );

        $status = M::getPlayMemberTable()->update($new_data, $data_param_where);

        if ($status) {
            RedCache::del("D:MemberData:" . $uid);
        }
        return $status;
    }

    public function getMaxMoneyService () {
        return RedCache::fromCacheData(KeyNames::WFT_STR_MAX_PRICE_MONEY_SERVICE, function () {
            $data_temp_member_money_service_list = M::getPlayMemberMoneyServiceTable()->fetchAll(array('money_service_status > ?' => 0), array('money_service_now_price' => 'desc'), 1)->toArray();
            $data_temp_member_money_service = $data_temp_member_money_service_list[0];
            return $data_temp_member_money_service;
        }, 5 * 60, true);
    }
}