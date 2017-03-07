<?php

namespace Deyi\Coupon;

use Application\Module;
use Deyi\BaseController;
use library\Service\System\Cache\RedCache;
use Deyi\GetCacheData\CouponCache;
use Deyi\SendMessage;

class Coupon
{
    use BaseController;

    //BaseController 使用
    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }

    /**
     * 获取设置
     * @return int
     */
    public function getSetting()
    {
        $adapter = $this->_getAdapter();

        $setting = $adapter->query("SELECT * FROM play_market_setting WHERE `id` = 1", array())->current();

        return $setting;
    }

    //兑换现金券
    public function exchange($code, $uid,$city)
    {
        if(!$code){
            return ['status' => 0, 'message' => '请输入兑换码'];
        }

        $adapter = $this->_getAdapter();

        //code 是否符合第三方设置
        $tip = substr($code, 0, 3);
        $is_third = false;
        if ($tip == 'CZT') {
            $result = $adapter->query("SELECT * FROM activity_coupon_cash WHERE `cate` = ?
     and status  = 1 and code = ?", array('CZT', $code))->current();
            if ($result) {
                $is_third = $result->id;
                $code = $result->link_code;
            }

        }




        $cc = $adapter->query("SELECT * FROM play_cash_coupon WHERE `diffuse_code` = ?
     and status  = 1 and is_close = 0 and city = ? order by id desc", array($code,$city))->current();

        if (!$cc) {
            return ['status' => 0, 'message' => '抱歉，输入的兑换码错误，请重试~'];
        } else {
            if($cc->residue<=0){
                return ['status' => 0, 'message' => '来晚了，现金券已经兑完啦~'];
            }

            if($cc->end_time < time()){
                return ['status' => 0, 'message' => '抱歉,兑换码已过期'];
            }

            if($cc->new){
                $oi = $adapter->query("SELECT * FROM play_order_info WHERE `user_id` = ? and pay_status > 1 ", array($uid))->current();
                if($oi){
                    return ['status' => 0, 'message' => '此现金券为新用户专享~'];
                }
            }

            $log = $adapter->query("SELECT * FROM play_cashcoupon_user_link WHERE `cid` = ?
     and uid = ? ", array($cc->id, $uid))->current();

            if ($log) {
                return ['status' => 0, 'message' => '您已兑换过此现金券了~'];
            }

            $cp = $this->addCashcoupon($uid,$cc->id,0,1,0,'兑换码兑换',$city);

            if(!$cp){
                return ['status' => 0, 'message' => '抱歉，输入的兑换码错误，请重试~'];
            }

            if ($is_third) {
                $adapter->query("UPDATE activity_coupon_cash set status  = 2, get_time = ? WHERE status = 1 AND id = ?", array(time(), $is_third));
            }

            return ['id' => $cp->id, 'title' => $cp->title];

        }
    }

    public function getByid($id, $uid,$city,$msg='参加活动获取')
    {
        if(!$id){
            return ['status' => 0, 'message' => '请输入兑换码'];
        }
        $adapter = $this->_getAdapter();

        $cc = $adapter->query("SELECT * FROM play_cash_coupon WHERE `id` = ?
     and status  = 1 and is_close = 0 ", array($id))->current();

        if (!$cc) {
            return ['status' => 0, 'message' => '抱歉，输入的ID错误，请重试~'];
        } else {
            if($cc->residue<=0){
                return ['status' => 0, 'message' => '来晚了，现金券已经兑完啦~'];
            }

            if($cc->end_time < time()){
                return ['status' => 0, 'message' => '抱歉,现金券已过期'];
            }

            if($cc->new){
                $oi = $adapter->query("SELECT * FROM play_order_info WHERE `user_id` = ? ", array($uid))->current();
                if($oi){
                    return ['status' => 0, 'message' => '此现金券为新用户专享~'];
                }
            }

//            $log = $adapter->query("SELECT * FROM play_cashcoupon_user_link WHERE `cid` = ?
//     and uid = ? ", array($cc->id, $uid))->current();
//
//            if ($log) {
//                return ['status' => 0, 'message' => '您已兑换过此现金券了~'];
//            }

            $cp = $this->addCashcoupon($uid,$cc->id,0,4,0,$msg,$city);

            if(!$cp){
                return ['status' => 0, 'message' => '抱歉，输入的兑换码错误，请重试~'];
            }

            return ['id' => $cp->id, 'title' => $cp->title];

        }
    }

    /**
     * @param $uid 用户id
     * @param $coupon_id （票券ｉｄ）
     * @param $get_object_id （来源id，如评论id，商品id）
     * @param $get_type 领券类型参考文档 http://wftgit.greedlab.com/wft/api-document/blob/master/project-doc/dictionary/OperationTypedef.md
     * @param $adminid （如果是管理员奖励，管理员id, 如果系统自动奖励则为0）
     * @param string $get_info (获得票券的描述)
     * @param string $city
     * @param string $get_order_id 订单
     * @param string $msgid 发言id
     * @return bool
     */
    public function addCashcoupon(
        $uid,
        $coupon_id,
        $get_object_id,
        $get_type,
        $adminid,
        $get_info = '',
        $city = 'WH',
        $get_order_id = 0,
        $msgid = 0
    ) {

        if (!$uid || !$coupon_id || !$get_type ) {
            return false;
        }

        $adapter = $this->_getAdapter();
        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();

        $coupon_id = (int)$coupon_id;
        $time = time();
        $sql = "select * from play_cash_coupon where id = ? and residue > 0 and end_time > {$time} and status = 1 and is_close = 0";
        $cp = $adapter->query($sql, array($coupon_id))->current();

        if (!$cp) {
            $conn->rollback();
            return false;
        }

        //判断是否新用户专享
        if($cp->new){
            $sql = "select * from play_order_info where user_id = ? and pay_status > 1 limit 1";
            $order = $adapter->query($sql, array($uid))->current();
            if($order){
                $conn->rollback();
                return false;
            }
        }

        $get_type_arr = [1=>'兑换码兑换', 2=>'点评商品',3=>'点评游玩地',4=>'参加活动',
            5=>'商品购买',6=>'采纳攻略',7=>'好评app', 8=>'圈子发言奖励',9=>'后台评论奖励',10=>'接受邀约有礼奖励',
            11=>'使用验证', 12=>'地推活动',13=>'邀请朋友奖励',14=>'购买商品分享红包奖励',15=>'接受商品红包奖励',
            16=>'购买活动分享红包奖励',17=>'好友通过分享参加活动奖励',18=>'延期补偿',19=>'资深玩家奖励',20=>'好想你券'
        ];

        $get_info = $get_info?$get_info:$get_type_arr[$get_type];

        $s1 = $adapter->query("update play_cash_coupon set residue = residue - 1 WHERE `id` = ?
     and residue > 0 ", array($cp->id))->count();

        if (!$s1) {
            $conn->rollback();
            return false;
        }

        //判断票券使用时间类型 使用时间的类别　０固定周期　１领券后到期（统一到小时为单位）
        if ($cp->time_type) {
            $use_stime = time();
            $use_etime = time() + (int)$cp->after_hour * 3600;
        } else {
            $use_stime = $cp->use_stime;
            $use_etime = $cp->use_etime;
        }

        //票券记录表 管理员操作记录
        $sql = 'INSERT INTO play_cashcoupon_user_link ';
        $sql .= '( cid,uid, create_time, use_stime,use_etime,get_info,get_object_id ,get_type,adminid,city,title,price,get_order_id,msgid )';
        $sql .= ' VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?) ';

        $pcul = [
            $coupon_id,
            $uid,
            time(),
            (int)$use_stime,
            (int)$use_etime,
            $get_info ?: '',
            $get_object_id,
            (int)$get_type,
            (int)$adminid,
            $city,
            $cp->title,
            $cp->price,
            $get_order_id,
            $msgid
        ];

        //票券表插入一条记录
        $s2 = $adapter->query($sql, $pcul)->count();
        $data_user_link = $adapter->query(" SELECT * FROM play_cashcoupon_user_link WHERE cid = ? AND uid = ? ORDER BY create_time DESC LIMIT 1", array($coupon_id, $uid))->current();
        if (!$s2) {
            $conn->rollback();
            return false;
        }

        $conn->commit();

        if ($get_type == 15) {
            // 成功抢到红包，推送消息
            $data_message_type = 13; // 消息类型为订单使用成功
            $data_inform_type  = 13; // 红包消息推送

            // 红包源头信息
            $data_order      = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $get_order_id));

            $data_order_user = $this->_getPlayUserTable()->get(array('uid' => $data_order->user_id));

            // 抢到红包的用户信息
            $data_user       = $this->_getPlayUserTable()->get(array('uid' => $uid));

            // 成功抢到红包推送内容
            $data_inform = "【玩翻天】您已成功领取" . $this->hidePhoneNumber($data_order_user->username) . "派发的红包，快来看看吧！";

            // 成功抢到红包系统消息
            $data_title   = "成功领取现金券红包";
            $data_message = "您已成功领取：" . $this->hidePhoneNumber($data_order_user->username) . "派发的红包";

            // 链接到的内容
            $data_link_id = array(
                'id'  => $data_user_link->id,
                'cid' => $coupon_id,
                'type'=> 'cash_coupon'
            );

            // 成功抢到红包系统消息附加参数
            $data_info = $data_user_link->id;

            $class_sendMessage = new SendMessage();
            $class_sendMessage->sendMes($data_user->uid, $data_message_type, $data_title, $data_message, $data_link_id);
            $class_sendMessage->sendInform($data_user->uid, $data_user->token, $data_inform, $data_inform, $data_info, $data_inform_type, $coupon_id);
        }

        return $cp;
    }
    
    /**
     * @param $uid
     * @param $cid
     * @param int $action_type_id
     * @param int $object_id
     * @return bool
     */
    public function useCashcoupon($uid, $cid, $use_type = 1, $use_object_id = 0, $use_order_id = 0)
    {
        if ((int)$uid === 0 || (int)$cid === 0 || (int)$use_type === 0 || (int)$use_object_id === 0) {
            return false;
        }
        $adapter = $this->_getAdapter();
        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();

        $u = $adapter->query("SELECT * FROM play_cashcoupon_user_link WHERE `cid`=? and `uid`=? and `pay_time` = ? ",
            array($cid, $uid, 0))->current();

        if ($u) {
            $s1 = $adapter->query("UPDATE play_cash_coupon_user SET pay_time=?,use_order_id=?,use_object_id=?,use_type=?
            WHERE id=?", array(time(), $use_order_id, $use_object_id, $use_type, $cid))->count();
            if (!$s1) {
                $conn->rollback();

                return false;
            }
        } else {
            $conn->rollback();

            return false;
        }
        $conn->commit();

        return true;
    }

    /**
     * @param $uid
     * @return array
     */
    public function myCashCoupon($uid)
    {
        $adapter = $this->_getAdapter();
        $sql = "select * from play_cashcoupon_user_link where uid = ? and cid > 0 and pay_time = 0 and use_etime >= ?";
        $coupon = $adapter->query($sql, array($uid, time()))->toArray();

        return $coupon;
    }

    /**
     * 发放方式 1用户兑换 2邀约 3参加活动 4注册
     * @param $uid
     * @param $give_type 　活取方式
     * @param $city 　
     * @param int $integral_ratio 　积分汇率
     * @param int $pay_object_id 　使用对象的ｉｄ
     * @param int $from_object_id 除注册外来自对象的ｉｄ
     * @param int $use_order_id 如产生订单需要订单id
     * @return bool
     */
    public function addQualify(
        $uid,
        $give_type,
        $city,
        $integral_ratio = 0,
        $pay_object_id = 0,
        $from_object_id = 0,
        $use_order_id = 0
    ) {
        if ((int)$uid === 0 || (int)$give_type === 0) {
            return false;
        }
        $adapter = $this->_getAdapter();

        $setting = $this->getSetting();

        $quota_time = $setting->quota_time;
        $valid_time = time() + ((int)$quota_time * 24 * 3600);

        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();

        //票券表插入一条记录
        $s2 = $adapter->query("INSERT INTO play_qualify_coupon
(give_type,create_time, valid_time, pay_time,uid,city,integral_ratio,use_order_id,pay_object_id,from_object_id,status )
 VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            array(
                $give_type,
                time(),
                $valid_time,
                0,
                $uid,
                $city,
                $integral_ratio,
                (int)$use_order_id,
                (int)$pay_object_id,
                (int)$from_object_id,
                1
            ))->count();

        if (!$s2) {
            $conn->rollback();

            return false;
        }

        $conn->commit();

        return true;
    }

    public function myQualify($uid)
    {
        if ((int)$uid === 0) {
            return false;
        }
        $adapter = $this->_getAdapter();
        $sql = "select count(*) as c from play_qualify_coupon where valid_time > ? and uid = ? and status = 1 AND pay_time = 0";
        $q = $adapter->query($sql, array(time(), $uid))->current();

        if ($q) {
            return $q;
        }

    }

    /**
     * 消费资格券
     * @param $order_sn //订单id
     * @return bool
     */

    public function useQualify($order_sn)
    {
        return true;
    }

    /**
     * 购买获得票券
     * @param $uid
     * @param $gid
     * @param $sid
     * @param $city
     */
    public function getCashCouponByBuy($uid,$gid,$sid,$order_sn,$city,$getinfo=''){
        $adapter = $this->_getAdapter();
        $sql = "SELECT * FROM play_welfare left join play_welfare_cash ON play_welfare.welfare_link_id
= play_welfare_cash.id where gid = ? and good_info_id = ? and give_time = 1 and welfare_type = 3 and play_welfare_cash.status = 2;";
        $pw = $adapter->query($sql,array($gid,$sid))->toArray();
        if(false === $pw || count($pw) === 0){
            return false;
        }

        foreach($pw as $p){
            if($p['total_num'] > $p['give_num']){
                $this->addCashcoupon($uid,$p['cash_coupon_id'],$order_sn,5,0,$getinfo,$city);
            }
        }
    }

    public function getCashCouponByUse($uid,$gid,$sid,$order_sn,$city){
        $adapter = $this->_getAdapter();
        $sql = "SELECT * FROM play_welfare left join play_welfare_cash ON play_welfare.welfare_link_id
= play_welfare_cash.id where gid = ? and good_info_id = ? and give_time = 2 and welfare_type = 3 and play_welfare_cash.status = 2;";
        $pw = $adapter->query($sql,array($gid,$sid))->toArray();
        if(false === $pw || count($pw) === 0){
            return false;
        }

        foreach($pw as $p){
            if($p['total_num'] > $p['give_num']){
                $this->addCashcoupon($uid,$p['cash_coupon_id'],$order_sn,11,0,'',$city);
            }
        }

    }

    //评论商品
    public function getCashCouponByCommend($uid,$order_sn,$mid,$city){
        //查询订单数据
        $order_data = $this->_getPlayOrderInfoTable()->getUserBuy(array('order_sn' => $order_sn));

        $adapter = $this->_getAdapter();
        $sql = "SELECT * FROM play_welfare left join play_welfare_cash ON play_welfare.welfare_link_id
= play_welfare_cash.id where gid = ? and good_info_id = ? and give_time = 3 and welfare_type = 3 and play_welfare_cash.status = 2;";

        $pw = $adapter->query($sql,array($order_data->coupon_id, $order_data->game_info_id))->toArray();
        if(false === $pw || count($pw) === 0){
            return false;
        }
        $sql = "select * from play_cashcoupon_user_link where get_order_id = ? and uid = ? and get_type = 2";
        $hasadd = $adapter->query($sql,array($order_sn, $uid))->toArray();

        if($hasadd){ //如果一个订单评论奖励过，不再给奖励
            return false;
        }

        foreach($pw as $p){
            if($p['total_num'] > $p['give_num']){
                $this->addCashcoupon($uid,$p['cash_coupon_id'],$mid,2,0,'',$city,$order_sn,$mid);
            }
        }
    }

    /**
     * 邀请获得现金券
     */
    public function getCashCouponByInverted($uid,$obj,$city){
        $ir = RedCache::fromCacheData('I:invite:' . $city, function () use ($city) {
            return $this->_getInviteRule()->getByRuleId($city);
        }, 3600*24, true);

        if(!$ir){
            return false;
        }

        $start = strtotime(date('Y-m-d'));

        $ir = (object)$ir;

        $adapter = $this->_getAdapter();
        $count = $adapter->query("SELECT * FROM play_cashcoupon_user_link WHERE `uid`=? and `get_type` = ? and create_time > ? ",
            array($uid, 13, $start))->count();

        if((int)$ir->r_inviter_type === 0 || $count > $ir->invite_per_day){
            return false;
        }

        $diffuse_code = json_decode($ir->r_inviter_couponid, true);

        $coupon_id_arr = explode(',', $diffuse_code[0]);//随机获取10元组合
        //接口

        foreach ($coupon_id_arr as $coupon_id) {
            $this->addCashcoupon($uid, $coupon_id, $obj, 13, 0, '邀约新用户领取奖励', $city);
        }
        return true;
    }

    /**
     * 接受邀请获得现金券
     */
    public function getCashCouponByReceive($uid,$obj,$city){
        $ir = RedCache::fromCacheData('I:invite:' . $city, function () use ($city) {
            return $this->_getInviteRule()->getByRuleId($city);
        }, 3600*24, true);

        if(!$ir){
            return false;
        }

        $ir = (object)$ir;

        $adapter = $this->_getAdapter();
        $count = $adapter->query("SELECT * FROM play_cashcoupon_user_link WHERE `uid`=? and `get_type` = ?  ",
            array($uid, 10))->count();

        if((int)$ir->r_reciever_type === 0 || $count > 0){
            return false;
        }

        $diffuse_code = json_decode($ir->r_reciever_couponid,true);

        $coupon_id_arr = explode(',', $diffuse_code[0]);//随机获取10元组合

        //接口
        foreach ($coupon_id_arr as $coupon_id) {
            $this->addCashcoupon($uid, $coupon_id, $obj, 10, 0, '受邀约领取奖励', $city);
        }
        return true;
    }

    //我的优惠券列表
    public function nmy($uid,$page,$limit,$info_id,$coupon_id,$pay_price,$type) {
        $page = ($page > 1) ? $page : 1;
        $city = $this->getCity();

        $offset = (($page - 1) * $limit);

        $db = $this->_getAdapter();

        if ($coupon_id > 0 and $type === 1 ) {
            $link_label = array();
            if($info_id){
                $info_sql = "select id,excepted from play_game_info WHERE id = ?;";
                $info     = $db->query($info_sql, array($info_id))->current();
                if($info and $info->excepted){
                    return array();
                }
            }
            if ($coupon_id) {
                $link_label_sql = "select play_label_linker.object_id,play_label.id,play_label.pid from play_label_linker left join play_label on play_label.id = play_label_linker.lid where play_label_linker.object_id = ?;";
                $link_label     = $db->query($link_label_sql, array($coupon_id))->toArray();
            }

            $or = '';

            if($link_label){
                foreach($link_label as $ll){
                    $or .= ('b.object_id = '.(int)$ll['id']. ' or ');
                    $or .= ('b.object_id = '.(int)$ll['pid']. ' or ');
                }
            }else{
                $or = ('b.object_id = 0 or ');
            }

            $or = rtrim($or,' or ');

            $cc = $db->query("SELECT
	a.*,d.time_type,d.description,d.new,d.range
FROM
	play_cashcoupon_user_link AS a
LEFT JOIN play_cashcoupon_good_link AS b ON a.cid = b.cid
LEFT JOIN play_cashcoupon_city AS c ON a.cid = c.cid
LEFT JOIN play_cash_coupon AS d ON a.cid = d.id
WHERE
	(
		(
			(
				b.object_type = 1
				AND b.object_id = ?
			)
			OR (
				b.object_type = 2
				AND ({$or})
			)
		)
		OR d.`range` = 0
	)
AND a.uid = ?
AND a.cid > 0
AND a.pay_time = 0
AND (
	is_main = 1
	OR (is_main = 0 AND c.city = ?)
)
AND a.use_stime <?
AND a.use_etime >?
AND a.price<=?
GROUP BY
	a.id
ORDER BY
	a.price DESC,a.pay_time asc,a.use_etime desc ,a.create_time DESC
            ", array($coupon_id, $uid, $city, time(), time(), $pay_price))->toArray();
        } elseif($coupon_id > 0 and $type === 2) {
            $event_sql = "select id,excepted from play_excercise_event WHERE id = ?;";
            $info     = $db->query($event_sql, array($coupon_id))->current();
            if($info and $info->excepted){
                return array();
            }
            $cc = $db->query("SELECT
	a.*,d.time_type,d.description,d.new,d.range
FROM
	play_cashcoupon_user_link AS a
 LEFT JOIN play_cashcoupon_good_link AS b ON a.cid = b.cid
LEFT JOIN play_cashcoupon_city AS c ON a.cid = c.cid
LEFT JOIN play_cash_coupon AS d ON a.cid = d.id
WHERE ( d.`range` = 3
OR (d.`range` = 4 and (
				b.object_type = 4
				AND b.object_id = ?
			))
)
AND a.uid = ?
AND a.cid > 0
AND a.pay_time = 0
AND (
	is_main = 1
	OR (is_main = 0 AND c.city = ?)
)
AND a.use_stime <?
AND a.use_etime >?
AND a.price<=?
GROUP BY
	a.id
ORDER BY
	a.price DESC,a.pay_time asc,a.use_etime desc ,a.create_time DESC
            ", array($coupon_id,$uid, $city, time(), time(), $pay_price))->toArray();
        }else{
            $cc = $db->query("
SELECT
	a.*,b.time_type,b.description,b.new,b.range
FROM
	play_cashcoupon_user_link AS a
LEFT JOIN play_cash_coupon AS b ON a.cid = b.id
WHERE
  a.uid = ?
  AND a.cid > 0
ORDER BY
	a.pay_time asc,a.use_etime desc,a.price DESC ,a.create_time DESC
limit ?,?
        ", array($uid, $offset, $limit))->toArray();
        }
        $allcids = [];//所有的现金券id
        $data = array();
        foreach ($cc as $c) {
            $ispass = $ispay = 0;
            $isvalid = 0;
            if ( $c['use_etime'] > time() && $c['use_stime'] < time()) {
                $isvalid = 1;
            }
            if($c['pay_time']>0){
                $ispay = 1;
                $isvalid = 0;
            }
            if($c['use_etime'] < time()){
                $ispass = 1;
                $isvalid = 0;
            }

            $allcids[] = $c['cid'];
            $data[] = [
                'id' => (int)$c['id'],
                'cid' => (int)$c['cid'],
                'title' => $c['title'],
                'price' => $c['price'],
                'begin_time' => (int)$c['use_stime'],
                'end_time' => (int)$c['use_etime'],
                'description' => CouponCache::getCouponDesc($c['cid']),
                'time_type' => (int)$c['time_type'],
                'isvalid' => $isvalid ?1: 0,
                'eventinfo' => [],
                'isnew'=>(int)$c['new'] ? : 0,//1为新用户专享
                'is_pass' => (int)$ispass,
                'is_pay' => (int)$ispay,
                'range' => (int)$c['range'],
                'type' => $c['range']>2?2:1
            ];
        }

        $event = [];
        if(count($allcids)){
            $allevent = $this->_getPlayExcerciseEventTable()->getEventByCash($allcids,0,0);
            if(count($allevent) and $isvalid){
                foreach($allevent as $a){
                    unset($a['sell_status'],$a['over_time'],$a['open_time'],$a['most_number'],
                        $a['join_number'],$a['least_number'],$a['perfect_number'],$a['shop_name']);
                    $a['start_time_wx'] = date('Y年m月d日',$a['start_time']);
                    $a['end_time_wx'] = date('Y年m月d日',$a['end_time']);
                    $event[$a['cid']][] = $a;
                }
            }
        }

        $after = $data;

        if(empty($after)){
            return array();
        }
        if(count($event)){
            foreach($after as $k => $f){
                if((int)$f['range']===4){
                    $after[$k]['eventinfo'] = $event[$f['cid']]?:[];
                }
            }
        }

        if($coupon_id>0){
            //金额排序
            $sort=array();
            foreach ($after as $v){
                $sort[]=(float)$v['price'];
                $sort2[]=$v['end_time'];
            }
            array_multisort($sort,SORT_DESC,$sort2,SORT_DESC,$after);
        }else{

            //失效时间排序
            $sort=array();
            $sort2=array();
            foreach ($after as $v){
                $sort[]=$v['end_time'];
                $sort2[]=$v['isvalid'];
            }

            array_multisort($sort2,SORT_DESC,$sort,SORT_DESC,$after);
        }
        return $after;
    }
}



