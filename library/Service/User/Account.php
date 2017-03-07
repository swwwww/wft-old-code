<?php

namespace library\Service\User;

use library\Fun\M;
use library\Service\System\Cache\RedCache;
use library\Service\System\Logger;
use Zend\Db\Sql\Predicate\Expression;

class Account {
    /**
     * 充值
     * @param $uid
     * @param float $money |充值金额
     * @param string $desc |充值描述
     * @param int $withdrawal |是否可提现 0不可 1可以提现
     * @param int $action_type_id |充值类型 参考文档 http://wftgit.greedlab.com/wft/api-document/blob/master/project-doc/dictionary/OperationTypedef.md
     * @param int $object_id |关联对象id
     * @param bool $confirm | 是否需要确认充值,只有使用第三方充值时才使用.如果是确认充值需要再次调用successful函数后才会进入用户可提现账户
     * @param int $editor_id | 编辑id,操作对象id
     * @param string $city | 城市
     * @param $msgid 发言id
     * @param $from_uid // 分享的用户uid
     * @param $money_service_id // 充值时符合的VIP会员套餐
     * @return bool
     */
    public function recharge($uid, $money = 0.00, $withdrawal = 0, $desc = '', $action_type_id = 1, $object_id = 0, $confirm = false, $editor_id = 0, $city = 'WH',$msgid=0, $from_uid = 0, $money_service_id = 0)
    {
        $money = (float)$money;
        if (!$uid || !$action_type_id || !$desc) {
            $this->errorLog("{参数不正确}\n");
            return false;
        }
        if ($money <= 0) {
            $this->errorLog("{钱不正确}\n");
            return false;
        }

        $adapter = M::getAdapter();
        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();

        $u = $adapter->query("SELECT * FROM play_account WHERE `uid`=?", array($uid))->current();

        if ($u) {
            //冻结的账号全部可以充值
            if ($u->status == 0) {
                $this->errorLog("uid {$uid} 的账户冻结了\n");
            }
            /*if ($u->status == 0 && $action_type_id != 1) {
                return false;
            }*/
        }
        if (!$u) {
            $now_money = $money;
        } else {
            $now_money = bcadd($u->now_money, $money, 2);
        }

        if (!$confirm) {

            if (!$u) {
                //初始化用户数据
                if ($withdrawal == 1) {
                    $s1 = $adapter->query("INSERT INTO play_account (id,uid,now_money,can_back_money,total_money_flow,last_time,status) VALUES (NULL ,?,?,?,?,?,?)", array($uid, $money, $money, $money, time(), 1))->count();
                } else {
                    $s1 = $adapter->query("INSERT INTO play_account (id,uid,now_money,total_money_flow,last_time,status) VALUES (NULL ,?,?,?,?,?)", array($uid, $money, $money,time(),1))->count();
                }
                if (!$s1) {
                    $conn->rollback();
                    $this->errorLog("uid {$uid} 初始化账户失败\n");
                    return false;
                }

            } else {

                if ($withdrawal == 1) {
                    $s1 = $adapter->query("UPDATE play_account SET now_money=now_money+{$money},can_back_money=can_back_money+{$money},last_time=?,total_money_flow=total_money_flow+{$money} WHERE uid=?", array(time(), $uid))->count();
                } else {
                    $s1 = $adapter->query("UPDATE play_account SET now_money=now_money+{$money},last_time=?,total_money_flow=total_money_flow+{$money} WHERE uid=?", array(time(), $uid))->count();
                }
                if (!$s1) {
                    $conn->rollback();
                    $this->errorLog("uid {$uid} 更新账户失败\n");
                    return false;
                }
            }
        }


        if ($confirm) {
            $status = 0;
        } else {
            $status = 1;
        }

        $s2 = $adapter->query("INSERT INTO play_account_log (id,uid,action_type,action_type_id,object_id,flow_money,surplus_money,dateline,description,status,editor_id,city,withdraw,user_account,check_status,can_back_money_flow,from_uid,money_service_id,msgid) VALUES (NULL ,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            array($uid, 1, $action_type_id, $object_id, $money, $now_money, time(), $desc, $status, $editor_id, $city, $withdrawal, $uid, 0, $withdrawal ? $money : null, $from_uid, $money_service_id, $msgid))->count();
        if (!$s2) {
            $conn->rollback();
            $this->errorLog("uid {$uid} 更新账户记录失败\n");
            return false;
        }

        $log_id = $adapter->getDriver()->getLastGeneratedValue();



        $conn->commit();

        // 返利到账提醒
        if (in_array($action_type_id, array(5))) {
            $this->sendReturnMoneyMessage($uid, $action_type_id, $object_id, $money);
        }
        RedCache::del('D:UserMoney:' . $uid);
        return $log_id;
    }

    private function sendReturnMoneyMessage($uid, $action_type_id, $object_id, $money) {
        // 使用成功时，推送消息
        $data_message_type = 12; // 消息类型为订单返利到账
        $data_inform_type  = 17; // 返利到账消息推送

        // 用户信息
        $data_user = M::getPlayUserTable()->get(array('uid' => $uid));

        // 返利到账推送内容
        switch ($action_type_id) {
            case 5 :
                // 购买商品返利
                // 获取商品信息
                $data_organizer_game = M::getPlayOrganizerGameTable()->get(array('id' => $object_id, 'status > ?' => 0));

                if (empty($data_organizer_game)) {
                    return false;
                }

                // 推送消息内容
                $data_inform = "【玩翻天】你购买的\"" . $data_organizer_game->title . "\"返利" . $money . "元已发放至您的账户余额，请注意查收";

                // 系统消息内容
                $data_title   = "返利到账提醒";
                $data_message = "您购买商品\"" . $data_organizer_game->title . "\"返利成功，请查收";
                break;
        }

        // 链接到的内容
        $data_link_id = array();

        $class_sendMessage = new SendMessage();
        $class_sendMessage->sendMes($uid, $data_message_type, $data_title, $data_message, $data_link_id);
        $class_sendMessage->sendInform($uid, $data_user->token, $data_inform, $data_inform, '', $data_inform_type, $uid);
    }

    /**需要确认的充值,确认充值成功
     * @param $uid
     * @param $id
     * @param $trade_no  商户流水号或交易号
     * @param $user_account  用户支付账号 (支付宝账号 微信账号)
     * @return bool
     */
    public function successful($uid, $id, $trade_no, $user_account)
    {
        if (!$uid or !$id) {
            return false;
        }

        $adapter = M::getAdapter();
        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();

        $log = $adapter->query("SELECT * FROM  play_account_log WHERE uid=? AND id=? AND  status=0", array($uid, $id))->current();

        if (!$log) {
            $conn->rollback();
            return false;
        }
        $s1 = $adapter->query("UPDATE play_account_log SET status=?,trade_no=?, user_account = ?, object_id = ? WHERE uid=? AND id=?", array(1, $trade_no, $user_account, $id, $uid, $id))->count();
        if (!$s1) {
            $conn->rollback();
            return false;
        } else {

            $withdrawal = 1;  //需要确认的充值为可提现的充值
            $u = $adapter->query("SELECT * FROM play_account WHERE `uid`=?", array($uid))->current();
            if (!$u) {
                //初始化用户数据
                if ($withdrawal == 1) {

                    if ($user_account == '自然童趣') {
                        $s1 = $adapter->query("INSERT INTO play_account (id,uid,now_money,can_back_money,total_money_flow,last_time,status) VALUES (NULL ,?,?,?,?,?,?)", array($uid, 0.00, 0.00, 0.00, time(), 1))->count();

                    } else{
                        $s1 = $adapter->query("INSERT INTO play_account (id,uid,now_money,can_back_money,total_money_flow,last_time,status) VALUES (NULL ,?,?,?,?,?,?)", array($uid, $log->flow_money, $log->flow_money, $log->flow_money, time(), 1))->count();
                    }

                } else {
                    $s1 = $adapter->query("INSERT INTO play_account (id,uid,now_money,total_money_flow,last_time,status) VALUES (NULL ,?,?,?,?,?)", array($uid, $log->flow_money, $log->flow_money, time(), 1))->count();
                }
                if (!$s1) {
                    $conn->rollback();
                    return false;
                }
            } else {
                if ($withdrawal == 1) {
                    if ($user_account == '自然童趣') {
                        $s1 = $adapter->query("UPDATE play_account SET last_time=?,total_money_flow=total_money_flow+{$log->flow_money} WHERE uid=?", array(time(), $uid))->count();
                    } else{
                        $s1 = $adapter->query("UPDATE play_account SET now_money=now_money+{$log->flow_money},can_back_money=can_back_money+{$log->flow_money},last_time=?,total_money_flow=total_money_flow+{$log->flow_money} WHERE uid=?", array(time(), $uid))->count();
                    }
                } else {
                    $s1 = $adapter->query("UPDATE play_account SET now_money=now_money+{$log->flow_money},last_time=?,total_money_flow=total_money_flow+{$log->flow_money} WHERE uid=?", array(time(), $uid))->count();
                }
                if (!$s1) {
                    $conn->rollback();
                    return false;
                }
            }

            $money    = (float)($log->flow_money);
            $from_uid = $log->from_uid;
            $city     = $log->city;
            $money_service_id = $log->money_service_id;

            // 获取符合条件的会员套餐
            $data_money_service = $adapter->query("SELECT * FROM play_member_money_service WHERE money_service_id = ? ", array($money_service_id))->current();

            // 如果对应了一个VIP会员套餐，则进行套餐的优惠操作
            if (!empty($data_money_service) && $city == 'WH') {
                // VIP会员套餐所对应积分
                $data_member_score          = $data_money_service->money_service_score;

                // 赠送项目
                $data_money_service_present = json_decode($data_money_service->money_service_present, true);
                $data_array_money_service_present = array();
                foreach ($data_money_service_present as $key => $val) {
                    if ($val['type'] == 'numberic') {
                        $data_array_money_service_present[$val['v_name']] = $val['value'];
                    }
                }

                if (((int)$data_array_money_service_present['free_coupon']) > 0) {
                    $sql = " INSERT INTO play_cashcoupon_user_link (`cid`, `uid`, `create_time`, `use_stime`, `use_etime`, `get_order_id`, `get_info`, `use_type`, `price`, `title`, `city`) VALUE ";
                    for ($i = 0 ; $i < $data_array_money_service_present['free_coupon'] ; $i++) {
                        $sql .= "(0, " . (int)$uid . "," . time() . ", 0, " . (int)(time() + 86400 * 365) . ", 0, 'VIP会员充值发放免费玩资格券', 2, 1, '免费玩资格券', '" . $city . "'),";
                    }
                    $sql = rtrim($sql, ',');
                    $data_free_coupon_result = $adapter->query($sql, array())->count();

                    if (!$data_free_coupon_result) {
                        $conn->rollback();
                        Logger::WriteErrorLog("uid {$uid} 插入会员套餐的免费玩资格券失败\n");
                    }
                }

                $data_member = M::getPlayMemberTable()->get(array('member_user_id' => $uid));
                if ($data_member) {
                    // 更新VIP会员状态
                    $sql = " 
                        UPDATE play_member SET 
                        member_level = 1, 
                        member_score = member_score + ?, 
                        member_score_now = member_score_now + ?, 
                        member_money = member_money + ?, 
                        member_end_time = 0, 
                        member_free_coupon_count = member_free_coupon_count + ?, 
                        member_free_coupon_count_now = member_free_coupon_count_now + ?,
                        member_free_activation_coupon_count = member_free_activation_coupon_count + ?
                        WHERE
                        member_user_id = ?
                       ";
                    $data_result_update_member = $adapter->query($sql, array(
                        (int)$data_member_score,
                        (int) $data_member_score,
                        (float)$money,
                        (int)$data_array_money_service_present['free_coupon'],
                        (int)$data_array_money_service_present['free_coupon'],
                        (int)$data_array_money_service_present['activation_free_coupon'],
                        $uid))->count();

                    if (!$data_result_update_member) {
                        $conn->rollback();
                        Logger::WriteErrorLog("uid {$uid} 会员记录更新失败\n");
                    }
                } else {
                    $sql  = " INSERT INTO play_member (`member_user_id`, `member_level`, `member_score`, `member_score_now`, `member_money`, `member_free_coupon_count`, `member_free_coupon_count_now`, `member_free_activation_coupon_count`, `member_create_time`, `member_update_time`) VALUE ";
                    $sql .= "(" . (int)$uid . ", 1, " . (int)$data_member_score .  ", " . (int)$data_member_score . ", " . $money . ", " . (int)$data_array_money_service_present['free_coupon'] . ", " . (int)$data_array_money_service_present['free_coupon'] . ", " . (int)$data_array_money_service_present['activation_free_coupon'] . ", " . time() . ", " . time() . ")";
                    $data_insert_member_result = $adapter->query($sql, array())->count();

                    if (!$data_insert_member_result) {
                        $conn->rollback();
                        Logger::WriteErrorLog("uid {$uid} 创建VIP会员记录失败\n");
                    }
                }

                if ($from_uid > 0) {
                    $service_member = new Member();
                    $data_result_activate_free_coupon = $service_member->activateFreeCoupon($uid, $from_uid, $city, $adapter, $conn);
                    if (!$data_result_activate_free_coupon) {
                        $conn->rollback();
                        Logger::WriteErrorLog("uid {$uid} 的分享充值激活亲子游资格失败\n");
                    }
                }
            }
        }
        $conn->commit();

        RedCache::del('D:UserMoney:' . $uid);

        return true;
    }

    /**
     * 消费
     * @param $uid
     * @param float $money |取现金额
     * @param string $desc |描述
     * @param int $action_type_id 消费类型 1 购买商品  2 原路返回卡上 3提现 4扣除多给的临时账户的钱
     * @param int $object_id |关联对象id order_sn
     * @param int $editor_id | 编辑id,操作对象id
     * @param string $city | 城市
     * @return bool
     */

    //todo ?? play_account total_money_flow 没有更新
    public function takeCrash($uid, $money = 0.00, $desc = '', $action_type_id = 2, $object_id = 0, $editor_id = 0, $city = 'WH')
    {
        if (!$uid || !$action_type_id || $money == 0.00 || !$desc) {
            return false;
        }
        if ($money < 0) {  //消费金额为正数
            return false;
        }


        $adapter = M::getAdapter();
        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();

        $u = $adapter->query("SELECT * FROM play_account WHERE `uid`=?", array($uid))->current();
        if (!$u) {
            return false;
        } else {
            //检查状态
            if ($u['status'] == 0) {
                return false;
            }

            if ($action_type_id != 4) {
                $s1 = $adapter->query("UPDATE play_account SET now_money=now_money-{$money}, can_back_money = can_back_money-{$money}, last_time=? WHERE uid=? AND now_money>={$money}", array(time(), $uid))->count();
            } else {
                $s1 = $adapter->query("UPDATE play_account SET now_money=now_money-{$money}, last_time=? WHERE uid=? AND now_money>={$money}", array(time(), $uid))->count();
            }

            if (!$s1) {
                $conn->rollback();
                return false;
            }
        }

        $can_back_money_flow = ($action_type_id == 4) ? 0 : $money;
        $surplus_money = bcsub($u['now_money'], $money, 2);

        $s2 = $adapter->query("INSERT INTO play_account_log (id,uid,action_type,action_type_id,object_id,flow_money,surplus_money,dateline,description,status,editor_id,city, withdraw, user_account,check_status,can_back_money_flow) VALUES (NULL ,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            array($uid, 2, $action_type_id, $object_id, $money, $surplus_money, time(), $desc, 1, $editor_id, $city, 1, $uid, 1, $can_back_money_flow))->count();
        if (!$s2) {
            $conn->rollback();
            return false;
        }

        $conn->commit();

        RedCache::del('D:UserMoney' . $uid);

        return true;

    }

    /**
     * 获取用户余额
     * @param $uid
     * @return float
     */
    public function getUserMoney($uid)
    {
        $data = M::getPlayAccountTable()->get(array('uid' => $uid));
        if ($data and $data->status == 1) {  //判断状态
            $money = $data->now_money;
        } else {
            $money= "0.00";
        }
        return (string)$money;  //客户端 多个小数位问题
    }


    /**
     * 检查支付密码
     * @param $uid
     * @param $password
     * @return bool
     */
    public function checkPassword($uid, $password)
    {
        $acc = M::getPlayAccountTable()->get(array('uid' => $uid));
        if (!$acc or !$acc->password) {
            return false;
        }
        $pass = md5(md5($password) . $acc->salt);
        if ($pass === $acc->password) {
            return true;
        }
        return false;
    }


    /**
     * 初始化账号
     * @param $uid
     * @return bool
     */
    public function initAccount($uid)
    {

        $adapter = M::getAdapter();
        $u = $adapter->query("SELECT * FROM play_account WHERE `uid`=?", array($uid))->current();
        if (!$u) {
            $adapter->query("INSERT INTO play_account (id,uid,now_money,can_back_money,total_money_flow,last_time,status) VALUES (NULL ,?,?,?,?,?,?)", array($uid, 0.00, 0.00, 0.00, time(), 1))->count();
        } else {
            return true;
        }
    }


    /**
     * 检查密码是否存在
     * @param $uid
     * @return bool
     */
    public function getPassword($uid)
    {

        return RedCache::fromCacheData('D:setPass:'.$uid,function()use($uid){
            $acc = M::getPlayAccountTable()->get(array('uid' => $uid));
            if ($acc) {
                if ($acc->password) {
                    return 1;
                }
            }
            return 0;
        }, 60);

    }

    /**
     * 购买获取返利
     * @param $uid
     * @param $gid
     * @param $sid
     * @param $order_sn
     * @param $city
     * @return bool
     */
    public function getCashByBuy($uid, $gid, $sid,$order_sn, $city,$coupon_name=''){

        $adapter = M::getAdapter();

        $sql = "SELECT * FROM play_welfare left join play_welfare_rebate ON play_welfare.welfare_link_id
= play_welfare_rebate.id where gid = ? and good_info_id = ? and give_time = 1 and from_type = 1 and welfare_type = 2 and play_welfare_rebate.status = 2;";

        $pw = $adapter->query($sql,array($gid,$sid))->toArray();

        if(false === $pw || count($pw) === 0){
            return false;
        }

        $coupon_name = $coupon_name?:'商品';

        foreach($pw as $p){
            if($p['total_num'] > $p['give_num']){
                $this->recharge($uid,($p['single_rebate']),((int)$p['rebate_type']-1),'购买'.$coupon_name.'获得返利',5,$order_sn,false,$p['editor_id'],$city);
            }
        }
    }

    /**
     * 评论返利
     * @param $uid
     * @param $gid
     * @param $sid
     * @param $city
     * @return bool
     */
    public function getCashByCommend($uid, $order_sn,$mid, $city){
        $order_data = M::getPlayOrderInfoTable()->getUserBuy(array('order_sn' => $order_sn));
        $adapter = M::getAdapter();
        $sql = "SELECT * FROM play_welfare left join play_welfare_rebate ON play_welfare.welfare_link_id
= play_welfare_rebate.id where gid = ? and good_info_id = ? and from_type = 1 and give_time = 3 and welfare_type = 2 and play_welfare_rebate.status = 2;";

        $pw = $adapter->query($sql,array($order_data->coupon_id, $order_data->game_info_id))->toArray();

        if(false === $pw || count($pw) === 0){
            return false;
        }

        $coupon_name = ($order_data->coupon_name)?:'商品';

        foreach($pw as $p){
            if($p['total_num'] > $p['give_num']){
                $this->recharge($uid,($p['single_rebate']),((int)$p['rebate_type']-1),'点评'.$coupon_name.'获得返利',6,$mid,false,0,$city,$mid);
            }
        }
    }

    /**
     * 使用验证
     * @param $uid
     * @param $gid
     * @param $sid
     * @param $city
     * @return bool
     */
    public function getCashByUse($uid, $gid, $sid,$order_sn, $city,$coupon_name=''){
        $adapter = M::getAdapter();

        $sql = "SELECT * FROM play_welfare left join play_welfare_rebate ON play_welfare.welfare_link_id
= play_welfare_rebate.id where gid = ? and good_info_id = ? and from_type = 1 and give_time = 2 and welfare_type = 2 and play_welfare_rebate.status = 2;";

        $pw = $adapter->query($sql,array($gid,$sid))->toArray();

        if(false === $pw || count($pw) === 0){
            return false;
        }

        $coupon_name = ($coupon_name)?:'商品';

        foreach($pw as $p){
            if($p['total_num'] > $p['give_num']){
                $this->recharge($uid,($p['single_rebate']),((int)$p['rebate_type']-1),'使用'.$coupon_name.'获得返利',10,$order_sn,false,0,$city);
            }
        }
    }

    /**
     * 冻结用户账户 解冻用户账户
     * @param $uid
     * @param int $type //1冻结 2开启
     * @param $message  //原因
     * @return bool
     */
    public function frozenUser($uid, $type = 1, $message) {

        $result = false;

        if (!in_array($type, array(1, 2))) {
            return $result;
        }
        $status = (int)($type - 1);

        $s1 = M::getPlayAccountTable()->update(array('status' => $status), array('uid' => $uid));

        $adapter = M::getAdapter();

        $s2 = $adapter->query("INSERT INTO play_user_frozen_log (uid, dateline, frozen_type, message, admin_id) VALUES (?, ?, ?, ?, ?)", array($uid, time(), $type, $message, $_COOKIE['id']))->count();

        if ($s1 && $s2) {
            $result = true;
        }
        return $result;

    }

    private function isGoodChannels ($channels_id) {
        if ($channels_id > 20000) {
            return true;
        } else {
            return false;
        }
    }

    public function getShareRechargeCountByUid ($uid) {
        return M::getPlayAccountLogTable()->fetchCount(array(' uid <> ' . $uid, 'from_uid' => $uid, 'status' => 1, 'money_service_id > 0'));
    }
}