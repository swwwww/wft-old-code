<?php

namespace Deyi\Rebate;

use Application\Module;
use Deyi\BaseController;
use Deyi\Account\Account;

class Rebate
{
    use BaseController;

    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }

    public function getRebate($uid, $object_id, $rebate_type) {

        $res = null;
        if ($rebate_type == 1) {//商品购买完成
             $res = $this->checkBuy($uid, $object_id);
        }

        if ($rebate_type == 2) {//商品使用验证
            $res = $this->checkUse($uid, $object_id);
        }

        if ($rebate_type == 3) {//提交评论
            $res = $this->checkPost($uid, $object_id);
        }

        if (!$res) {
            return false;
        }

        return true;

    }

    /**
     * @param $uid
     * @param $order_id
     * @return bool
     */
    private function checkBuy($uid, $order_id) {

        $good_info_data = $this->_getPlayOrderInfoGameTable()->get(array('order_sn' => $order_id, 'pay_status > 1'));
        $good_order_data = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_id, 'user_id' => $uid));

        if (!$good_info_data || !$good_order_data) {
            return false;
        }

        $rebate = $this->_getPlayWelfareRebateTable()->get(array(
            'status' => 2,
            'give_type' => 1,
            'from_type' => 1,
            'gid' => $good_order_data->coupon_id,
            'total_num > give_num',
        ));

        if (!$rebate) {
            return false;
        }

        $rebate_link = $this->_getPlayWelfareTable()->get(array(
            'status' => 2,
            'object_type' => 2, //商品
            'object_id' => $good_order_data->coupon_id,
            'good_info_id' => $good_info_data->game_info_id,
            'welfare_type' => 2, //现金返利
            'welfare_link_id' => $rebate->id,
        ));

        if (!$rebate_link) {
            return false;
        }

        $adapter = $this->_getAdapter();
        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();

        $coupon_name = ($good_order_data->coupon_name)?:'';

        $s1 = $adapter->query("UPDATE play_welfare_rebate SET give_num=give_num+1 WHERE id=? AND give_num < total_num", array($rebate->id))->count();

        if (!$s1) {
            $conn->rollback();
            return false;
        }

        $s2 = $adapter->query("INSERT INTO play_rebate_log (id,rebate_id,create_time,rebate_money,rebate_type,rebate_info,uid,object_id) VALUES (NULL ,?,?,?,?,?,?,?)",
            array($rebate->id, time(), $rebate->single_rebate, 1, '购买'.$coupon_name.'获得返利', $uid, $order_id))->count();
        if (!$s2) {
            $conn->rollback();
            return false;
        }

        $conn->commit();

        $Account = new \library\Service\User\Account();

        $Account->recharge($uid, $rebate->single_rebate, ($rebate->rebate_type == 1) ? 0 : 1, '评论'.$coupon_name.'返现', 10, $rebate->id, false, $rebate->editor_id, $rebate->city);

        return true;
    }

    /**
     * @param $uid
     * @param $order_id
     * @return bool
     */
    private function checkUse($uid, $order_id) {
        $good_info_data = $this->_getPlayOrderInfoGameTable()->get(array('order_sn' => $order_id, 'pay_status > 1'));
        $good_order_data = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_id, 'user_id' => $uid));

        if (!$good_info_data || !$good_order_data) {
            return false;
        }

        $rebate = $this->_getPlayWelfareRebateTable()->get(array(
            'status' => 2,
            'give_type' => 2,
            'from_type' => 1,
            'gid' => $good_order_data->coupon_id,
            'total_num > give_num',
        ));

        if (!$rebate) {
            return false;
        }

        $rebate_link = $this->_getPlayWelfareTable()->get(array(
            'status' => 2,
            'object_type' => 2, //商品
            'object_id' => $good_order_data->coupon_id,
            'good_info_id' => $good_info_data->game_info_id,
            'welfare_type' => 2, //现金返利
            'welfare_link_id' => $rebate->id,
        ));

        if (!$rebate_link) {
            return false;
        }

        $adapter = $this->_getAdapter();
        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();

        $coupon_name = $good_order_data->coupon_name?:'';

        $s1 = $adapter->query("UPDATE play_welfare_rebate SET give_num=give_num+1 WHERE id=? AND give_num < total_num", array($rebate->id))->count();

        if (!$s1) {
            $conn->rollback();
            return false;
        }

        $s2 = $adapter->query("INSERT INTO play_rebate_log (id,rebate_id,create_time,rebate_money,rebate_type,rebate_info,uid,object_id) VALUES (NULL ,?,?,?,?,?,?,?)",
            array($rebate->id, time(), $rebate->single_rebate, 2, '使用'.$coupon_name.'获得返利', $uid, $order_id))->count();
        if (!$s2) {
            $conn->rollback();
            return false;
        }

        $conn->commit();
        $Account = new \library\Service\User\Account();
        $Account->recharge($uid, $rebate->single_rebate, ($rebate->rebate_type == 1) ? 0 : 1, '评论'.$coupon_name.'返现', 10, $rebate->id, false, $rebate->editor_id, $rebate->city);

        return true;

    }

    /**
     *
     */
    private function checkPost($uid, $good_id) {

        $rebate = $this->_getPlayWelfareRebateTable()->get(array(
            'status' => 2,
            'give_type' => 3,
            'from_type' => 1,
            'gid' => $good_id,
            'total_num > give_num',
        ));

        if (!$rebate) {
            return false;
        }

        $adapter = $this->_getAdapter();

        $sql = "SELECT
play_order_info.order_sn,play_order_info.coupon_name
FROM
play_order_info
LEFT JOIN play_order_info_game ON play_order_info.order_sn = play_order_info_game.order_sn
where
play_order_info.user_id = {$uid} AND
play_order_info.pay_status > 1 AND
play_order_info.coupon_id = {$good_id} AND
play_order_info_game.game_info_id in (
SELECT good_info_id from play_welfare WHERE status = 2 AND object_type = 2 AND object_id = {$good_id} AND welfare_type = 2 AND welfare_link_id = {$rebate->id}
) AND play_order_info.order_sn NOT IN (
SELECT object_id FROM play_rebate_log WHERE rebate_type = 3 AND uid = $uid AND rebate_type = 3
)";
        $res = $adapter->query($sql, 'execute')->current();

        if (!$res) {
            return false;
        }

        $adapter = $this->_getAdapter();
        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();

        $s1 = $adapter->query("UPDATE play_welfare_rebate SET give_num=give_num+1 WHERE id=? AND give_num < total_num", array($rebate->id))->count();

        if (!$s1) {
            $conn->rollback();
            return false;
        }

        $coupon_name = $res->$coupon_name?:'商品';

        $s2 = $adapter->query("INSERT INTO play_rebate_log (id,rebate_id,create_time,rebate_money,rebate_type,rebate_info,uid,object_id) VALUES (NULL ,?,?,?,?,?,?,?)",
            array($rebate->id, time(), $rebate->single_rebate, 3, '评论'.$coupon_name.'获得返利', $uid, $res->order_sn))->count();
        if (!$s2) {
            $conn->rollback();
            return false;
        }

        $conn->commit();

        $Account = new \library\Service\User\Account();
        $Account->recharge($uid, $rebate->single_rebate, ($rebate->rebate_type == 1) ? 0 : 1, '评论'.$coupon_name.'获得返利', 10, $rebate->id, false, $rebate->editor_id, $rebate->city);

        return true;

    }



}



