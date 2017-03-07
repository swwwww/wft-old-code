<?php

namespace Deyi\Invite;

use Application\Module;
use Deyi\Account\Account;
use Deyi\BaseController;
use Deyi\GeTui\GeTui;
use Deyi\Integral\Integral;
use Deyi\Coupon\Coupon;
use library\Service\System\Cache\RedCache;
use Deyi\WeiXinFun;

class Invite
{
    use BaseController;

    const INVITE_CASH_COUPON = 6;//邀约现金卷类型

    //BaseController 使用
    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }


    public function __construct()
    {


    }

    private function _getInviteConf($city){
        $data = $this->_getInviteRule()->getByRuleId($city);
        return $data;
    }

    private function _getInviteConfByPhone($phone){
        $adapter = $this->_getAdapter();
        $data = $adapter->query("SELECT * FROM invite_member WHERE `phone`=? AND `status`=0", array($phone))->current();

        if($data){
            $rule = $this->_getInviteRule()->getByMyRuleId($data->ruleid);
        }

        return $rule;
    }

//注册invitor　邀请得积分(先调邀请者加积分，并不修改member状态，但记录log记录状态，之后调受邀者加票券，再改member状态)
    public function InvitorAwardByRegister($uid,$phone,$city){

        $inviteConf = $this->_getInviteConfByPhone($phone);

        $inviterResult = false;
        $adapter = $this->_getAdapter();
        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();
        $u = $adapter->query("SELECT * FROM invite_member WHERE `phone`=? AND `status`=0", array($phone))->current();

        if($u and $inviteConf) {
            $coupon_id_arr = null;
            $awardNum = $this->_getInviteInviterAwardLog()->getOrderAwardPerDay($u->sourceid, 1, $inviteConf->r_inviter_type);
            $awardNum = $awardNum->count;

            //判断次数,查过则不奖励
            if($awardNum < $inviteConf->invite_per_day) {
                if ($inviteConf->r_inviter_type == 0) {//加积分
                    //接口
                    //查询用户是否存在邀约表中，且状态status为0（未注册状态）
                    $intergral = new Integral();
                    //参数说明：添加积分用户id，所增*倍数后积分，加分倍数默认1，所加积分，奖励类型6，奖励积分来源用户手机，城市
                    $inviterResult = $intergral->addIntegral($u->sourceid, $inviteConf->r_inviter_award, 1, 6, $phone, $city);
                } elseif ($inviteConf->r_inviter_type == 1) {//加现金卷
                    //查询所对应邀约的现金券

                    $diffuse_code = json_decode($inviteConf->r_inviter_couponid, true);
//                        $rand = rand(0,2);
                    //todo 测试
                    $rand = rand(0, 0);
                    $coupon_id_arr = explode(',', $diffuse_code[$rand]);//随机获取10元组合
                    //接口
                    $coupon = new Coupon();
                    foreach ($coupon_id_arr as $coupon_id) {
                        $inviterResult = $coupon->addCashcoupon($u->sourceid, $coupon_id, $phone, 10, 0, '', $city);
                    }
                }
                //接口插入成功，插入数据修改绑定用户状态
                if ($inviterResult) {
                    $intergral = new Integral();
                    //每日任务积分
                    $intergral->days_task_integral($u->sourceid, $city);
                    //更新状态
                    $this->_getInviteMember()->update(['status' => 0], ['phone' => $phone]);//0：未注册，1：注册，2：已下首单
                    $user = $this->_getInviteToken()->getToken($inviteConf->ruleid, $u->sourceid, ['*']);
                    $data = [
                        'uid' => $u->sourceid,
                        'username' => $user->username,
                        'token' => $user->token,
                        'phone' => $phone,
                        'sourceid' => '',
                        'status' => 1,
                        'award_type' => $inviteConf->r_inviter_type,//奖励类型（0：积分，1：现金卷，2：资格卷）
                        'award' => $inviteConf->r_inviter_award,
                        'ruleid' => $inviteConf->ruleid,
                        'dateline' => time()
                    ];
                    if ($coupon_id_arr) {
                        $data['couponid'] = json_encode($coupon_id_arr);
                    }
                    //记录log
                    $this->_getInviteInviterAwardLog()->insert($data);
                }
            }
        }
        $conn->commit();
        return $inviterResult;
    }

    // 注册reciever　受邀得票券
    //1、用户注册的时候（向user表插入），需要查看用户是否在member中且status=0（仅为绑定状态），若是，需要给用户改status=1以及插入InviteRecieverAwardLog记录（十元红包）log.
    //2、为需要给邀约用户插入InviteInviterAwardLog记录log（加30积分）
    public function inviteRegister($uid,$username = '',$phone,$city){
        if(!$uid || !$username || !$phone || !$city){
            return false;
        }

        $adapter = $this->_getAdapter();
        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();
        //查询用户是否存在邀约表中，且状态status为0（未注册状态）
        $u = $adapter->query("SELECT * FROM invite_member WHERE `phone`=? AND `status`=0", array($phone))->current();
        $coupon_id_arr = null;
        //存在该邀约手机用户,且未注册,且为在扫码当天有效次数内的扫码用户
        if($u) {
            //更新用户状态
            $this->_getInviteMember()->update(['uid' => $uid, 'username' => $username, 'status' => 1, 'register_time' => time()], ['phone' => $phone]);//0：未注册，1：注册，2：已下首单
            $this->_getInviteInviterAwardLog()->update(['sourceid' => $uid, 'username' => $username], ['phone' => $phone]);//更新inviter_log表，受邀人的信息
            //$this->_getInviteRecieverAwardLog()->update(['uid' => $uid, 'username' => $username],['phone' => $phone]);//更新reciever_log表，受邀人的信息
            //查询对应站点的规则，发奖
            //$inviteConf = $this->_getInviteConf($city);
            $inviteConf = $this->_getInviteConfByPhone($phone);
            //$inviteConf->r_reciever_type;//受邀者奖励类型 0:积分 1:现金卷
            //$inviteConf->r_reciever_award;//受邀者奖励数值
            //$awardNum = $this->_getInviteRecieverAwardLog()->getOrderAwardPerDay($u->sourceid, 1, $inviteConf->r_reciever_type);
            //$awardNum = $awardNum->count;
            //判断次数,查过则不奖励
            //if ($awardNum <= $inviteConf->invite_per_day){
                //if ($u->valid == 1) {
                    //受邀者
                    if ($inviteConf->r_reciever_award > 0) {
                        $recieverResult = false;
                        if ($inviteConf->r_reciever_type == 0) {//加积分
                            //接口
                            $intergral = new Integral();
                            //参数说明：添加积分用户id，，所加积分，加分倍数默认1，奖励类型6，奖励积分来源用户id，城市
                            $recieverResult = $intergral->addIntegral($uid, $inviteConf->r_reciever_award, 1, 6, $u->sourceid, $city);

                        } elseif ($inviteConf->r_reciever_type == 1) {//加现金卷
                            //接口
                            //查询所对应邀约的现金券
                            //票券10元id组合
//                        $diffuse_code = array(array('142','143','144'),array('4','5','6'),array('3','6'));
                            $diffuse_code = json_decode($inviteConf->r_reciever_couponid, true);
//                        $rand = rand(0,2);
                            //todo 测试
                            $rand = rand(0, 0);
                            $coupon_id_arr = explode(',', $diffuse_code[$rand]);//随机获取10元组合
                            //接口
                            $coupon = new Coupon();
                            foreach ($coupon_id_arr as $coupon_id) {
                                $recieverResult[] = $coupon->addCashcoupon($uid, $coupon_id, $u->sourceid, 10, 0, '', $city);
                            }
                            $recieverResult = count($coupon_id_arr) == count($recieverResult) ? true : false;
//                    $recieverResult = true;

                        }
                        //接口插入成功，插入数据修改绑定用户状态
                        if ($recieverResult === true) {
                            $user = $this->_getInviteToken()->getToken($inviteConf->ruleid, $u->sourceid, ['*']);
                            //记录log
                            //将注册奖品写入日志(注册status为1,注册时将这个存入用户中)
                            $data = [
                                'phone' => $phone,
                                'uid' => $uid,
                                'username' => $username,
                                'token' => $user->token,
                                'sourceid' => $u->sourceid,
                                'status' => 1,
                                'award_type' => $inviteConf->r_reciever_type,//奖励类型（0：积分，1：现金卷，2：资格卷）
                                'award' => $inviteConf->r_reciever_award,
                                'ruleid' => $inviteConf->ruleid,
                                'dateline' => time()
                            ];
                            if ($coupon_id_arr) {
                                $data['couponid'] = json_encode($coupon_id_arr);
                            }
                            $this->_getInviteRecieverAwardLog()->insert($data);
                        }

                    }
            //}
        }else{
            $conn->rollback();
            return false;
        }



        $conn->commit();
        return true;
    }

    public function firstOrder($uid,$username,$phone,$city,$price,$buy_number){
        if(!$phone || !$city || !$price){
            return false;
        }

        $adapter = $this->_getAdapter();
        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();

        $u = $adapter->query("SELECT * FROM invite_member WHERE `phone`=? ", array($phone))->current();
        //存在该邀约手机用户,且刚注册
        if($u){
            $ir = $adapter->query("SELECT * FROM invite_rule WHERE `ruleid`=? ", array($u->ruleid))->current();
            if($ir->f_inviter_award == 0 or $ir->f_inviter_award_limit == 0){
                return false;
            }

            if($ir){
                //查询当天该邀约用户因首单奖励的次数
                $ir->f_inviter_type;//邀约者奖励类型 1:现金卷
                $ir->f_inviter_award;//邀请者奖励首单的百分数值

                $start = strtotime(date('Y-m-d'));
                $awardNum = $adapter->query("SELECT * FROM play_account_log WHERE `uid`=? and action_type_id = 10 and dateline > ? ", array($uid,10,$start))->count();

                //达到当天限定的次数
                if($awardNum >= $ir->invite_per_day){
                    $this->_getInviteMember()->update(['status' => 3],['phone' => $phone]);//更新当日非奖励次数内的好友状态
                    return false;
                }

                //邀约者
                if($ir->f_inviter_award > 0){
                    //计算首单百分比的现金
                    $award = bcmul($price, bcdiv($ir->f_inviter_award,100,2), 2);
                    if($award == 0){
                        return false;
                    }
                    $award = ($award > $ir->f_inviter_award_limit) ? $ir->f_inviter_award_limit : $award;//邀约者获取现金的面值
                    $price = bcdiv($award, $buy_number, 2);
                    $inviterResult = false;
                    if($ir->f_inviter_type == 3){//加现金
                        //判断用户之前有返利没（这种情况只存在于一单多票），并且返利总值做判断
                        $account = new \library\Service\User\Account();//现金返利
                        $inviterResult = $account->recharge($u->sourceid,$price,0,'邀约有礼首单返利',15,$uid,false,0,$city);
                    }
                    //接口插入成功，插入数据修改绑定用户状态
                    if($inviterResult > 0){
                        //更新状态
                        $this->_getInviteMember()->update(['status' => 2],['phone' => $phone]);//0：未注册，1：注册，2：已下首单

                    }
                }
            }
        }
    }

    //调用此几口前需判断该票据是否首单，是才调用
    // 首单
    //1、用户消费首单票券的时候，需要查看用户是否在member中且status=1（仅为注册状态），若是，则修改member中status=2（已首单），以及插入InviteInviterAwardLog中(加首单价格的30%红包，$price = first_order_price*3%;$price = $price > 30 ? 30 : $price;)//最多每日5个
    public function firstOrder_bak($uid,$username,$phone,$city,$price){
        if(!$phone || !$city || !$price){
            return false;
        }

        $adapter = $this->_getAdapter();
        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();
        //查询用户是否存在邀约表中，且状态status为0（未注册状态）,此处不检查状态，可能存在一单中几个票券，分开使用的状态
        $u = $adapter->query("SELECT * FROM invite_member WHERE `phone`=? /*AND `status` >= 1*/", array($phone))->current();
        //存在该邀约手机用户,且刚注册
        if($u){
            //查询对应站点的规则，发奖
            $inviteConf = $this->_getInviteConf($city);
            $inviteConf->f_inviter_type;//邀约者奖励类型 1:现金卷
            $inviteConf->f_inviter_award;//邀请者奖励首单的百分数值

            //查询当天该邀约用户因首单奖励的次数
//            $awardNum = $this->_getInviteInviterAwardLog()->getOrderAwardPerDay($u->sourceid,2,$inviteConf->f_inviter_type);
            $awardNum = $adapter->query('SELECT * FROM `invite_inviter_award_log` WHERE uid = ? AND award_type = ? AND `status` = 2 AND sourceid NOT IN (SELECT sourceid FROM invite_inviter_award_log WHERE award_type = 3 AND `status` = 2 AND dateline < ? GROUP BY sourceid) AND dateline BETWEEN ? AND ? GROUP BY sourceid',array($u->sourceid, $inviteConf->f_inviter_type, strtotime(date("Y-m-d")." 00:00:00"), strtotime(date("Y-m-d")." 00:00:00"), strtotime(date("Y-m-d")." 23:59:59")))->count();
            //达到当天限定的次数
            if($awardNum >= $inviteConf->invite_per_day){
                $this->_getInviteMember()->update(['status' => 3],['phone' => $phone]);//更新当日非奖励次数内的好友状态
                $conn->rollback();
                return false;
            }

            //邀约者
            if($inviteConf->f_inviter_award > 0){
                //计算首单百分比的现金
                $award = bcmul($price, bcdiv($inviteConf->f_inviter_award,100,2), 2);
                if($award == 0){
                    $conn->rollback();
                    return false;
                }
                $award = $award > $inviteConf->f_inviter_award_limit ? $inviteConf->f_inviter_award_limit : $award;//邀约者获取现金的面值
                $inviterResult = false;
                if($inviteConf->f_inviter_type == 3){//加现金
                    //判断用户之前有返利没（这种情况只存在于一单多票），并且返利总值做判断
                    $again_total = $this->_getInviteInviterAwardLog()->getAgainTotalAward($u->sourceid,$uid,2,$inviteConf->f_inviter_type);
                    if($again_total){
                        if($again_total >= $inviteConf->f_inviter_award_limit){
                            return false;
                        }else{
                            $award = bcadd($again_total , $award, 2) >= $inviteConf->f_inviter_award_limit ? bcsub($inviteConf->f_inviter_award_limit , $again_total, 2) : $award;
                        }
                    }
                    //接口
                    $account = new \library\Service\User\Account();//现金返利
                    $inviterResult = $account->recharge($u->sourceid,$award,0,'邀约有礼首单返利',10,$uid,false,0,$city);
                    //$inviterResult = true;

                }
                //接口插入成功，插入数据修改绑定用户状态
                if($inviterResult > 0){
                    //更新状态
                    $this->_getInviteMember()->update(['status' => 2],['phone' => $phone]);//0：未注册，1：注册，2：已下首单
                    $user = $this->_getInviteToken()->getToken($inviteConf->ruleid,$u->sourceid,['*']);
                    //记录log
                    $this->_getInviteInviterAwardLog()->insert([
                        'uid' => $u->sourceid,
                        'token' => $user->token,
                        'sourceid' => $uid,
                        'username' => $username,
                        'phone' => $phone,
                        'status' => 2,
                        'award_type' => $inviteConf->f_inviter_type,//奖励类型（3：现金）
                        'award' => $award,
                        'ruleid' => $inviteConf->ruleid,
                        'dateline' => time()
                    ]);
                }
            }


        }else{
            $conn->rollback();
            return false;
        }

        $conn->commit();
        return true;
    }

    //分享活动奖励
    public function shareExcercise($uid,$sn,$city){

        $cc = new Coupon();
        $db = $this->_getAdapter();

        if(!$sn){
            return false;
        }

        $sql = 'select * from play_share where share_id = ? limit 1';
        $csl = $db->query($sql, array($sn))->current();

        if($csl){
            return false;
        }

        $sql = 'select * from play_order_info where order_sn = ? limit 1';

        $order = $db->query($sql, array($sn))->current();

        if($order and ((int)$order->pay_status === 2 || (int)$order->pay_status === 5) ) {
            if($order->uid == $uid){
                $ower = $order;
            }else{
                $uid = $order->user_id;
                $ower = $order;
            }
        } else {
            return false;
        }

        //不属于分享
        $sql = 'select * from play_cash_share where city = ? and isall = 1 and type = 2 limit 1';
        $pcs = $db->query($sql, array($city))->current();
        $sql = 'select * from play_excercise_base where id = ? and share_reward = 1 limit 1';
        $peb = $db->query($sql, array($order->coupon_id))->current();
        if(!$pcs and !$peb){
            return false;
        }

        $options = (array)RedCache::fromCacheData('D:share_cash:2' . $city, function () use ($city,$db) {
            $sql = 'select * from play_cash_share where city = ? and type = 2 limit 1';
            $data = $db->query($sql, array($city))->current();
            return $data;
        }, 24 * 3600 * 7, true);

        $opt = json_decode($options['options']);
        if(!$opt){
            return false;
        }

        $total_money = bcadd($ower->account_money, $ower->realpay, 2);  //账户加银行卡总支付金额
        $has = 0;
        $in = false;
        foreach ($opt as $o) {
            $price = $o[0];
            $pay = explode('-', $price);
            if ($ower and $total_money >= $pay[0] and $total_money <= $pay[1]) {

                //分享者获得现金券
                $share_cc = explode(',', $o[1]);
                foreach ($share_cc as $sc) {
                    $in = $cc->addCashcoupon($uid, $sc, (int)$sn, 16, 0, '参加' . $ower->coupon_name . '分享现金红包', $city, $sn);
                }

                $has = 1;
                break;
            }
        }
        if(!$has){
            return false;
        }
        if(!$in){
            return false;
        }
        return true;
    }

    //购买给分享者返利//好友的订单，通过好友订单得到分享这订单
    public function useevent($fsn){
        return false;
        $db = $this->_getAdapter();

        $sql = 'select * from play_order_info where order_sn = ? limit 1';
        $forder = $db->query($sql, array($fsn))->current();

        $from_uid = $forder->user_id;

        $sql = 'select * from play_order_otherdata where order_sn = ? limit 1';
        $poo = $db->query($sql, array($fsn))->current();

        if(!$poo or !$poo->share_order_sn){
            return false;
        }else{
            $wsn = $poo->share_order_sn;
        }


        $sql = 'select * from play_share where share_id = ? limit 1';
        $csl = $db->query($sql, array($wsn))->current();

        if(!$csl){
            return false;
        }

        $sql = 'select * from play_order_info where order_sn = ? limit 1';

        $order = $db->query($sql, array($wsn))->current();

        if($order and ((int)$order->pay_status === 2 || (int)$order->pay_status === 5) ) {
            $city = $order->order_city;
            $to_uid = $order->user_id;
        } else {
            return false;
        }

        //不属于分享
        $account = new \library\Service\User\Account();
        $money = $this->getFanli($order);
        if($money){
            $in = $account->recharge($to_uid,$money,0,'好友参加'. $order->coupon_name .'获得返利'.$money.'元',16,$wsn,false,0,$city);
            if(!$in){
                return true;
            }
            $this->WeiXinMsg($from_uid,$to_uid,$money,'use');
            $this->GetuiMsg($from_uid,$to_uid,$money,'use');
        }

        return true;
    }

    //分享成功给予分享者奖励
    public function shareCash($uid,$sn,$city){

        $cc = new Coupon();
        $db = $this->_getAdapter();

        $sql = 'select * from play_cashcoupon_user_link where get_order_id = ? and get_type = 14 limit 1';
        $csl = $db->query($sql, array($sn))->current();
        $ownhas = 0;
        if($csl){
            if((int)$_COOKIE['debug']) {
                echo '分享过了,且领过奖了';
                exit;
            }
            return true;
        }

        $sql = 'select * from play_order_info where order_sn = ? limit 1';

        $order = $db->query($sql, array($sn))->current();

        if($order and ((int)$order->pay_status === 2 || (int)$order->pay_status === 5
                || (int)$order->pay_status === 6 || (int)$order->pay_status === 7) ) {
            if($order->uid == $uid){
                $ower = $order;
            }else{
                $uid = $order->user_id;
                $ower = $order;
            }
        } else {
            if((int)$_COOKIE['debug']) {
                echo '订单状态不满足';
                exit;
            }
            return false;
        }

        //商品不属于分享
        $sql = 'select * from play_cash_share where city = ? and isall = 1 and `type` = 1 limit 1';
        $pcs = $db->query($sql, array($city))->current();
        $sql = 'select * from play_organizer_game where id = ? limit 1';
        $goods = $db->query($sql, array($order->coupon_id))->current();
        if(!$pcs and  !$goods->cash_share ){
            if((int)$_COOKIE['debug']) {
                echo '没有设置可以得奖';
                exit;
            }
            return false;
        }

        $options = (array)RedCache::fromCacheData('D:share_cash:1' . $city, function () use ($city,$db) {
            $sql = 'select * from play_cash_share where city = ? and `type` = 1 limit 1';
            $data = $db->query($sql, array($city))->current();
            return $data;
        }, 24 * 3600 * 7, true);

        $opt = json_decode($options['options']);
        if(!$opt){
            if((int)$_COOKIE['debug']) {
                echo '规则不存在';
                exit;
            }
            return false;
        }

        $sql = "INSERT INTO `play_cash_share_link` (`sn`,`createtime`,`uid`,`city`,`endtime`) VALUES (?,?,?,?,0)";

        if(!$ownhas){
            $db->query($sql, array($sn, time(), $uid, $city));
        }

        $total_money = bcadd($ower->account_money, $ower->realpay, 2);  //账户加银行卡总支付金额
        $has = 0;
        $in = false;
        foreach ($opt as $o) {
            $price = $o[0];
            $pay = explode('-', $price);

            if (!$ownhas and $ower and $total_money >= $pay[0] and $total_money < $pay[1]) {

                //分享者获得现金券
                $share_cc = explode(',', $o[1]);
                foreach ($share_cc as $sc) {
                    $in = $cc->addCashcoupon($uid, $sc, (int)$sn, 14, 0, '购买' . $ower->coupon_name . '分享现金红包', $city, $sn);
                }

                //生成待领取的现金券
                $sql = 'insert into play_cash_share_item (`cid`,`resule`,`createtime`,`enttime`,`sid`) VALUES ';
                $prize_cc = explode(',', $o[2]);
                $value_str = '';
                foreach ($prize_cc as $pc) {
                    $pc_arr = explode('-', $pc);
                    $value_str .= '(' . $pc_arr[0] . ',' . $pc_arr[1] . ',' . time() . ',0,'.$sn.'),';
                }

                $sql .= rtrim($value_str, ',');

                $db->query($sql, array());
                $has = 1;
                break;
            }
        }
        if(!$has){
            if((int)$_COOKIE['debug']) {
                echo '没有发成功';
                exit;
            }
            return false;
        }
        if(!$in){
            if((int)$_COOKIE['debug']) {
                echo '添加操作没有成功';
                exit;
            }
            return false;
        }

        return true;
    }

    /**
     * 根据订单活动应有的返利
     * @param $order 分享者的订单
     * @return int
     */
    public function getFanli($order){

        $sql = 'select * from play_excercise_event where id = ? limit 1';
        $city = $order->order_city;
        $type = 2;
        $db = $this->_getAdapter();
        $item = $db->query($sql, array($order->coupon_id))->current();
        if(!$item->share_reward){
            return 0;
        }
        $options = (object)RedCache::fromCacheData('D:share_cash:' .$type. $city, function () use ($city,$type) {
            $data = $this->_getPlayCashShareTable()->get(['city' => $city,'type'=>$type]);
            return $data;
        }, 24 * 3600, true);

        if(!$options){
            return 0;
        }

        if(!$item and !$options->isall){
            return 0;
        }

        $opt = json_decode($options->options);

        $cv = 0;
        if ($opt and $order) {
            $money = bcadd($order->real_pay,$order->account_money, 2);
            foreach ($opt as $o) {
                $price = $o[0];
                $pay = explode('-', $price);
                if ($money >= $pay[0] and $money < $pay[1]) {
                    $cv = $o[3];
                    break;
                }
            }
        }else{
            $cv = 0;
        }
        return $cv;
    }

    public function middleware($order,$flag = 0){

        if ($flag or ($order and ((int)$order->pay_status === 2 || (int)$order->pay_status === 5))) {
            $ower = $order;
        } else {
            return (int)false;
        }
        $city = $order->order_city;

        if((int)$order->order_type === 1){
            $type = 1;
        }elseif((int)$order->order_type === 3){
            $type = 2;
        }else{
            $type = 1;
        }

        //是否属于分享
        $db = $this->_getAdapter();
        $items = 0;
        if($type == 1){
            $sql = 'select * from play_organizer_game where id = ? limit 1';
            $item = $db->query($sql, array($order->coupon_id))->current();
            $items = $item->cash_share;
        }elseif($type == 2){
            $sql = 'select * from play_excercise_event where id = ? limit 1';
            $item = $db->query($sql, array($order->coupon_id))->current();
            $items = $item->share_reward;
        }

        $options = (object)RedCache::fromCacheData('D:share_cash:' .$type. $city, function () use ($city,$type) {
            $data = $this->_getPlayCashShareTable()->get(['city' => $city,'type'=>$type]);
            return $data;
        }, 24 * 3600 * 7, true);

        if(!$options){
            return 0;
        }

        if(!$items and !$options->isall){
            return 0;
        }

        $opt = json_decode($options->options);

        $cv = 0;
        if ($opt and $ower) {
            $money = bcadd($ower->real_pay,$ower->account_money, 2);
            foreach ($opt as $o) {
                $price = $o[0];
                $pay = explode('-', $price);
                if ($money >= $pay[0] and $money < $pay[1]) {
                    $cv = 1;
                    break;
                }
            }
        }else{
            $cv = 0;
        }

        return $cv;
    }

//根据城市活动邀约分享提示
    public function getInviteInfo($city){
        RedCache::del('I:invite:' . $city);
        $ir = RedCache::fromCacheData('I:invite:' . $city, function () use ($city) {
            return $this->_getInviteRule()->getByRuleId($city);
        }, 3600*12, true);

        if(!$ir){
            $ir = $this->_getInviteRule()->getByRuleId('WH');
        }
        return (object)$ir;
    }

    public function getsharelink($ir,$uid){
        //初始化用户邀约码token
        $user = $this->_getPlayUserTable()->get(['uid' => $uid]);
        $inviteToken = $this->_getInviteToken()->getToken($ir->ruleid, $uid);//判断account_token是否有这个用户，有就取shareToken
        if (!$inviteToken) {//没就插入并生成inviteToken
            $inviteToken = $this->_getInviteToken()->setToken($ir->ruleid, $uid, $user->username,$user->img);
        }
        //邀请码
        $inviteUrl = $this->_getConfig()['url'].'/webinvite/index/recieve?token='.$inviteToken.'&city='.$ir->city;
        return $inviteUrl;
    }

    public function GetuiMsg($from_uid,$to_uid,$money,$msg){
        $users = $this->_getPlayUserWeiXinTable()->get(['uid'=>[$to_uid,$from_uid]]);
        if(count($users) < 2){
            return false;
        }
        $nickname = '';$uid = 0;
        foreach($users as $u){
            if($u['uid'] === $from_uid){
                $nickname = $u['nickname'];
            }
            if($u['uid'] === $to_uid){
                $uid = $u['open_id'];
            }
        }
        $msgstr = '';
        if($msg === 'buy'){
            $msgstr = "【{$nickname}】已购买玩翻天活动，{$money}元现金将在Ta参加活动后返至您的玩翻天账户";
        }elseif($msg === 'use'){
            $msgstr = "【{$nickname}】已成功参加玩翻天活动，{$money}元现金已返至您的玩翻天账户";
        }elseif($msg === 'back'){
            $msgstr = "【{$nickname}】退订了玩翻天活动，您未能获得{$money}元分享奖励。再介绍多的朋友来玩翻天遛娃吧~";
        }
        $content = array(
            'title' => htmlspecialchars_decode('分享活动得返利', ENT_QUOTES),
            'info' => htmlspecialchars_decode($msgstr, ENT_QUOTES),
            'type' => 10,
            'id' => 0,
            'time' => time()+10,
        );


        if ($uid) {
            $geTui = new GeTui();
            $user_data = $this->_getPlayUserTable()->get(array('uid' => $uid));
            $token = $user_data->token;
            $str = substr($token, 0, 10);
            $mer = (array)$geTui->Push($uid . '__' . $str, htmlspecialchars_decode($msgstr, ENT_QUOTES), json_encode($content, JSON_UNESCAPED_UNICODE));
            if ($mer['result'] === 'ok') {
                return $this->_Goto('成功', '/wftadlogin/getui');
            }
            exit;
        }
    }

    public function WeiXinMsg($from_uid,$to_uid,$money,$type,$sn){
        $users = $this->_getPlayUserWeiXinTable()->fetchAll(['uid'=>[$to_uid,$from_uid],'appid'=>'wx8e4046c01bf8fff3'],[],2)->toArray();

        if(count($users) < 1){
            return false;
        }
        $nickname = '';$open_id = 0;
        $users = (array)$users;

        foreach($users as $u){
            if((int)$u['uid'] === $from_uid){
                $nickname = $u['nickname'];
            }
            if((int)$u['uid'] === $to_uid){
                $open_id = $u['open_id'];
            }
        }

        $weixin = new WeiXinFun($this->getwxConfig());
        $msgstr = '';
        $data['touser'] = $open_id;
        if($type === 'buy'){
            $msgstr = "【{$nickname}】已购买玩翻天活动，{$money}元现金返至您的玩翻天账户";
        }elseif($type === 'use'){
            $msgstr = "【{$nickname}】已成功参加玩翻天活动，{$money}元现金已返至您的玩翻天账户";
        }elseif($type === 'back'){
            $msgstr = "【{$nickname}】退订了玩翻天活动，您未能获得{$money}元分享奖励。再介绍多的朋友来玩翻天遛娃吧~";
        }
        $data['cash'] = $money;
        $data['order_sn'] = $sn;
        $data['open_id'] = $open_id;

        $tpm = $weixin->getTemplate('use',$data,$msgstr);

        $weixin->set_http_message($tpm);
    }

    //活动分享规则提示
    public function EventShareInfo($sn,$uid,$city){

        $options = (object)RedCache::fromCacheData('D:share_cash:2' . $city, function () use ($city) {
            $data = $this->_getPlayCashShareTable()->get(['city' => $city,'type'=>2]);
            return $data;
        }, 24 * 3600 * 7, true);

        $opt = json_decode($options->options);

        $db = $this->_getAdapter();
        //判断是否购买过当前商品
        $sql = 'select * from play_order_info where order_sn = ? and user_id = ? limit 1';

        $order = $db->query($sql, array($sn,$uid))->current();

        if ($order and ((int)$order->pay_status === 2 || (int)$order->pay_status === 5) ) {
            $ower = $order;
        } else {
            $ower = false;
        }

        $cv = 0;
        $event = [];$cvinfo = '';
        if ($opt and $ower) {

            $event = (array)RedCache::fromCacheData('E:event:' . $ower->coupon_id, function () use ($ower) {
                $data = $this->_getPlayExcerciseEventTable()->getEventInfo(['play_excercise_event.id' => $ower->coupon_id]);
                return $data;
            }, 24 * 3600 * 7, true);

            $money = bcadd($ower->real_pay,$ower->account_money, 2);

            foreach ($opt as $o) {
                $price = $o[0];
                $pay = explode('-', $price);
                if ($money >= $pay[0] and $money <= $pay[1]) {
                    //分享者获得现金券
                    $share_cc = explode(',', $o[1]);
                    $join_t = $o[2];
                    $join_cc = $o[3];
                    foreach ($share_cc as $sc) {
                        RedCache::del('D:cashv:' . $sc);
                        $cashv = (array)RedCache::fromCacheData('D:cashv:' . $sc, function () use ($sc) {
                            $data = $this->_getCashCouponTable()->get(['id' => $sc]);
                            return $data;
                        }, 24 * 3600 , true);
                        $cv += $cashv['price'];
                    }
                    if($join_t == 1){
                        $cashv = (array)RedCache::fromCacheData('D:cashv:' . $join_cc, function () use ($join_cc) {
                            $data = $this->_getCashCouponTable()->get(['id' => $join_cc]);
                            return $data;
                        }, 24 * 3600 , true);
                        $cvinfo = $cashv['price'].'元现金券';
                    }elseif($join_t == 2){
                        $cvinfo = '返利'.$join_cc.'元';
                    }
                    break;
                }
            }
        }else{
            $cv = 0;
        }

        $share_feedback[] = ['image'=>'/images/invite/hd1.png','title'=>'分享赢'.$cv.'元现金券红包'];
        $share_feedback[] = ['image'=>'/images/invite/hd3.png','title'=>'分享活动给好友你奖获得'.$cv.'元现金券'];
        $share_feedback[] = ['image'=>'/images/invite/hd2.png','title'=>'好友通过你的分享成功报名参加活动你将'.$cvinfo];

        $view = array(
            'title' => $ower->coupon_name,
            'share_title'=>$ower->coupon_name,
            'share_content'=>$event['introduction'],
            'share_img'=>$this->_getConfig()['url'].$event['thumb'],
            'share_url'=>$this->_getConfig()['url'].'/web/kidsplay/info?id='.$event['bid'].'&sid='.$sn,

            'share_feedback'=> $share_feedback,

            'share_type' => [1,2],
        );

        return $view;
    }

}



