<?php

/**
 * 生成订单
 */
namespace Deyi\OrderAction;


use Deyi\Account\Account;
use Deyi\BaseController;
use Application\Module;
use Deyi\Invite\Invite;
use library\Fun\M;
use library\Service\Kidsplay\Kidsplay;
use library\Service\System\Logger;
use library\Service\User\Member;
use library\Service\User\User;
use Zend\Db\Sql\Predicate\Expression;
use Zend\Db\Sql\Predicate\In;


class InsertExcerciseOrder
{
    use BaseController;


    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }


    /**
     * 生成遛娃活动订单
     * @param $event_data |场次数据
     * @param $base_data |活动数据
     * @param $charges |购买的收费项
     * @param $user_data |用户数据
     * @param $associates_ids |购买保险用户信息
     * @param $people_number |出行人数 =保险数量
     * @param string $buyName
     * @param string $buyPhone
     * @param $buy_address
     * @param int $cash_coupon_id |现金券id
     * @param int $use_account_money |是否使用账户支付
     * @param string $message |留言 备注
     * @param int $meeting_id |集合id
     * @param string $city
     * @param string $share_order_sn 记录分享者订单
     * @return array
     */
    public function insertOrder($event_data, $base_data, $charges, $user_data,
                                $associates_ids, $people_number,
                                $buyName = '', $buyPhone = '', $buy_address,
                                $cash_coupon_id = 0, $use_account_money = 0, $message = '', $meeting_id = 0, $city = 'WH',$share_order_sn = 0

    )
    {


        // 只负责插入订单,现金券使用,积分使用,独立及支付后处理
        /*
         * 订单说明：
            订单购买数等于用户选择的所有收费项活套系数量总和 （占用的数量为 （收费项数*出行人数） ）
            验证码数等于 订单购买数包括其他收费项
            出行人数等于，排除其他收费项 (收费项数 *出行人数) +(收费项数*出行人数).....
        */
        $time = time();
        $real_pay = 0;  //银行卡需要支付的金额 实付金额
        $user_money = 0;//用户账户需要支付的金额
        $cash_money = 0;//现金券金额
        $account_type = 'nopay';
        $pay_status = 0;
        $orderType = 3;
        $total_price = 0; //订单总价
        $buy_number=0; //购买数量=验证码数量
        $ault_number = 0;
        $child_number= 0;
        $data_free_coupon_count = 0;
        foreach ($charges as $v) {
            $buy_number += $v['buy_number'];
            $real_pay = bcadd(bcmul($v['price'], (int)($v['buy_number'] - $v['free_buy_number']),2), $real_pay, 2);
            if ($v['is_other'] != 1) {
                $data_free_coupon_count = $data_free_coupon_count + $v['free_buy_number'] * $v['free_coupon_need_count'];
                $ault_number = bcadd(bcmul($v['person_ault'],$v['buy_number'],2), $ault_number, 2);
                $child_number = bcadd(bcmul($v['person_child'],$v['buy_number'],2), $child_number, 2);
            }
        }
        $total_price = $real_pay;

        $db = $this->_getAdapter();

        /***************** 计算满减金额 start *************************/
        $edata = $db->query("select * from play_excercise_event WHERE id=? ", array($event_data->id))->current();
        if ($edata and $edata->full_price and $edata->less_price) {
            if ($edata->welfare_type and $edata->full_price <= $people_number) {//人
                $real_pay = bcsub($real_pay, $edata->less_price, 2); //重新计算满减后的金额
            } elseif ($edata->full_price <= $real_pay) {//金额
                $real_pay = bcsub($real_pay, $edata->less_price, 2); //重新计算满减后的金额
            }
        }
        /***************** 计算满减金额 end *************************/

        /***************** 判断总金额,需要支付的金额 ****************/
        //判断现金券否正确
        if ($cash_coupon_id) {

            $event_sql = "select id,excepted from play_excercise_event WHERE id = ?;";
            $info     = $db->query($event_sql, array($event_data->id))->current();
            if($info and $info->excepted){
                return array('status' => 0, 'message' => '抱歉，购买特例活动不可使用现金券!');
            }

            $cash_data = $db->query("select * from play_cashcoupon_user_link WHERE id=? AND pay_time=0 AND use_stime<?  AND use_etime>? AND  uid=?", array($cash_coupon_id, $time, $time, $user_data->uid))->current();
            if (!$cash_data) {
                $this->errorLog("现金券状态异常或已使用  id:{$cash_coupon_id} uid={$user_data->uid}");
                return array('status' => 0, 'message' => '现金券状态异常或已使用!');
            }
            if ($cash_data->price > $real_pay) {
                return array('status' => 0, 'message' => '现金券金额大于账户金额!');
                // $cash_data->price = $real_pay;
            }
            $cash_money = $cash_data->price;
            $real_pay = bcsub($real_pay, $cash_data->price, 2); //重新计算银行卡需要支付的金额
        }
        /***************** 判断总金额,需要支付的金额 ****************/

        $conn = $db->getDriver()->getConnection();
        $conn->beginTransaction();


        //购买数量判断
        $surplu = $event_data->most_number - $people_number;//总数减去即将购买的数量
        $s = $db->query('UPDATE play_excercise_event SET join_number=join_number+?, join_ault=join_ault+?, join_child=join_child+? WHERE id=? AND join_number<=?', array($people_number, $ault_number, $child_number, $event_data->id, $surplu))->count();
        if (!$s) {
            $conn->rollback();
            return ['status' => 0, 'message' => "数量不够了"];
        }

        //更新为已满员状态
        $db->query('UPDATE play_excercise_event SET sell_status=2 WHERE id=? AND join_number>=perfect_number', array($event_data->id))->count();

        foreach ($charges as $v) {
            if ($v['free_buy_number'] > 0) {
                $s = $db->query('UPDATE play_excercise_price SET buy_number=buy_number+?, free_coupon_join_count=free_coupon_join_count+? WHERE id=? AND (free_coupon_max_count = 0 OR free_coupon_join_count < free_coupon_max_count)', array($v['buy_number'], $v['free_buy_number'], $v['id']))->count();
            } else {
                $s = $db->query('UPDATE play_excercise_price SET buy_number=buy_number+?, free_coupon_join_count=free_coupon_join_count+? WHERE id=? ', array($v['buy_number'], $v['free_buy_number'], $v['id']))->count();
            }
            Logger::writeLog('临时监控：' . __DIR__ . print_r(array($v['buy_number'], $v['free_buy_number'], $v['id']),1));

            if (!$s) {
                $conn->rollback();
                return ['status' => 0, 'message' => "内部错误"];
            }
        }

        // 更新亲子游场次数量
        $service_kidsplay         = new Kidsplay();
        $data_free_coupon_is_full = $service_kidsplay->getFreeCouponJoinIsFull($event_data->id);
        if ($data_free_coupon_count > 0 && $data_free_coupon_is_full) {
            $data_result_update_base = $db->query('UPDATE play_excercise_base SET free_coupon_event_count=free_coupon_event_count-1 WHERE id=? AND free_coupon_event_count > 0', array($event_data->id))->count();
            if (!$data_result_update_base) {
                $conn->rollback();
                return ['status' => 0, 'message' => "内部错误"];
            }
        }


        //插入订单主记录
        $s = $db->query("
INSERT INTO play_order_info (
	coupon_id,
	order_status,
	pay_status,
	user_id,
	username,
	phone,
	real_pay,
	account_money,
	voucher,
	voucher_id,
	coupon_unit_price,
	coupon_name,
	shop_name,
	shop_id,
	buy_number,
	use_number,
	back_number,
	account,
	account_type,
	buy_name,
	buy_phone,
	dateline,
	use_dateline,
	order_city,
	order_type,
	group_buy_id,
	buy_address,
	bid,
	total_price,
	people_number
)
VALUES	(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", array(
            $event_data->id,
            1,
            $pay_status,
            $user_data->uid,
            $user_data->username,
            $user_data->phone,
            $real_pay,  //银行卡需要支付的金额
            $user_money,  //用户账户需要支付的金额
            $cash_money,//现金券金额
            $cash_coupon_id,
            0,
            $base_data->name,
            $event_data->shop_name,
            $event_data->shop_id,
            $buy_number,
            0,
            0,
            '',
            $account_type,
            $buyName,
            $buyPhone,
            $time,
            0,
            $city,
            $orderType,
            0,
            $buy_address,
            $base_data->id,
            $total_price,
            $people_number
        ))->count();
        $order_sn = $db->getDriver()->getLastGeneratedValue();

        if (!$s) {
            $conn->rollback();
            return array('status' => 0, 'message' => '生成订单失败！');
        }

        $s = $db->query('INSERT INTO play_order_action (order_id,play_status,action_user,action_note,dateline,action_user_name) VALUES (?,?,?,?,?,?)', array(
            $order_sn,
            0,
            $user_data->uid,
            '下单成功',
            $time,
            '用户' . $user_data->username
        ))->count();

        if (!$s) {
            $conn->rollback();
            return array('status' => 0, 'message' => '插入订单记录失败！');
        }


        // 生成卡券密码 对应每一个收费项
        $orderCodes = '';
        $insurance_num = 0;  //需要购买的保险数

        $n = 0;
        foreach ($charges as $k => $v) {
            $data_temp_free_coupon_count = $v['free_buy_number'];
            for ($i = $v['buy_number']; $i > 0; $i--) {
                //验证码
                $code = $order_sn . sprintf("%02d", $n) . mt_rand(10, 99);  //验证码
                if ($v['is_other'] != 1) {
                    $insurance_num+=$v['person'];
                    if ($data_temp_free_coupon_count > 0) {
                        $use_free_coupon = $v['free_coupon_need_count'];
                    } else {
                        $use_free_coupon = 0;
                    }
                }
                $orderCodes.= "('{$event_data->id}', {$order_sn}, '{$code}', '{$user_data->uid}', '0', 0, 0, 0, '{$v['id']}', {$time},{$v['price']},{$v['person']},{$use_free_coupon}),";
                $n++;
                $data_temp_free_coupon_count--;
            }
        }

        $orderCodes = substr($orderCodes, 0, -1);

        $s = $db->query('INSERT INTO play_excercise_code (eid,order_sn,code,uid,back_time,back_money,status,use_dateline,pid,dateline,price,person,use_free_coupon) VALUES ' . $orderCodes, array())->count();
        if (!$s) {
            $conn->rollback();
            return array('status' => 0, 'message' => '生成验证码失败！');
        }


        $meeting_place = '';
        $meeting_time = 0;
        if ($meeting_id) {
            $meeting_data = $this->_getPlayExcerciseMeetingTable()->get(array('id' => $meeting_id));
            $meeting_place = $meeting_data->meeting_place;
            $meeting_time = $meeting_data->meeting_time;
        }
        $full_sssociates=0;
        if($insurance_num==count($associates_ids)){
            $full_sssociates=1;
        }

        $share_order_sn = $share_order_sn>0 ? $share_order_sn : 0;
        $s = $db->query("INSERT INTO play_order_otherdata (order_sn, message,comment,meeting_place,meeting_time,meeting_id,full_sssociates,share_order_sn) VALUES (?,?,?,?,?,?,?,?)", array($order_sn, $message, 0, $meeting_place, $meeting_time,$meeting_id,$full_sssociates,$share_order_sn));
        if (!$s) {
            $conn->rollback();
            return ['status' => 0, 'message' => "插入订单关联数据失败"];
        }

        //购买保险


        if ($insurance_num) { //是否需要购买保险
            $insure_data = '';
            //有填充数据
            for ($i = 0; $i < count($associates_ids); $i++) {

                $associates_info = $db->query("select * from play_user_associates WHERE associates_id=?", array($associates_ids[$i]))->current();
                $insure_data .= "('{$order_sn}','{$event_data->id}','{$associates_info->name}',
                            '{$associates_info->sex}','{$associates_info->birth}','{$associates_info->id_num}',1,'','','1',{$associates_ids[$i]},'{$event_data->insurance_id}'),";
            }
            //剩下无填充数据
            if (count($associates_ids) < $insurance_num) {
                for ($i = 0; $i < $insurance_num - count($associates_ids); ++$i) {

                    $insure_data .= "('{$order_sn}','{$event_data->id}','','','','',1,'','','0',0,'{$event_data->insurance_id}'),";
                }
            }
            $insure_data = substr($insure_data, 0, -1);
            if (!empty($insure_data)) {
                $stmt = $db->query('INSERT INTO play_order_insure (order_sn,coupon_id,`name`,sex,birth,id_num,insure_company_id,insure_sn,baoyou_sn,insure_status,associates_id,product_code) VALUES ' . $insure_data);
                $stmt->execute($stmt)->count();
            }
        }

        //使用现金券
        if ($cash_coupon_id) {
            $s8 = $db->query("UPDATE  play_cashcoupon_user_link SET pay_time=?,use_order_id=?,use_object_id=?,use_type=?  WHERE id=? AND pay_time=0 AND use_stime<?  AND use_etime>? AND uid=?", array($time, $order_sn, $event_data->id, 2, $cash_coupon_id, $time, $time, $user_data->uid))->count();
            if (!$s8) {
                $conn->rollback();
                return ['status' => 0, 'message' => "现金券使用失败,已使用或已过期"];
            }
        }

        // 使用亲子游资格券
        if ($data_free_coupon_count > 0) {
            $service_member = new Member();

            $data_member_free_coupon = $db->query(" SELECT id FROM play_cashcoupon_user_link WHERE pay_time = 0 AND cid = 0 AND uid = ? AND use_etime > ? ORDER BY use_etime ASC LIMIT ? ", array($user_data->uid, time(), $data_free_coupon_count));
            $data_member             = $service_member->getMemberData($user_data->uid);

            if ($data_member_free_coupon->count() != $data_free_coupon_count || $data_member['member_free_coupon_count_now'] < $data_free_coupon_count) {
                $conn->rollback();
                return ['status' => 0, 'message' => "您的亲子游次数不足，请可前往会员充值获取更多的亲子游次数"];
            }

            $data_free_coupon_ids = '';
            foreach ($data_member_free_coupon as $key => $val) {
                $data_free_coupon_ids .= $val->id . ',';
            }

            $data_free_coupon_ids = rtrim($data_free_coupon_ids, ',');

            $data_result_update_free_coupon = $db->query(" UPDATE play_cashcoupon_user_link SET pay_time = ?, use_order_id = ?, use_object_id = ?, use_type = ? WHERE pay_time = 0 AND cid = 0 AND uid = ? AND use_etime > ? AND id in ({$data_free_coupon_ids}) ", array(time(), $order_sn, $event_data->id, 2, $user_data->uid, time()))->count();

            if (!$data_result_update_free_coupon) {
                $conn->rollback();
                return ['status' => 0, 'message' => "操作过于频繁，亲子游次数使用失败，请稍后重试"];
            }

            $data_result_update_member = $db->query(" UPDATE play_member SET member_free_coupon_count_now = member_free_coupon_count_now - ? WHERE member_user_id = ? ", array($data_free_coupon_count, $user_data->uid))->count();
            if (!$data_result_update_member) {
                $conn->rollback();
                return ['status' => 0, 'message' => "您的亲子游次数不足，请可前往会员充值获取更多的亲子游次数"];
            }
        }

        $conn->commit();

        //给用户发送消息 ok
        $invite = new Invite();
        if($share_order_sn){
            $order = $this->_getPlayOrderInfoTable()->get(['order_sn'=>$share_order_sn]);
            if($order){
                $money = $invite->getFanli($order);
                if($money){
                    $to_uid = $order->user_id;
                    $account = new \library\Service\User\Account();
                    $in = $account->recharge($to_uid,$money,0,'好友购买'. $order->coupon_name .'获得返利'.$money.'元',16,$order_sn,false,0,$city);
                    if(!$in){
                        return true;
                    }
                    $invite->WeiXinMsg($user_data->uid,$to_uid,$money,'buy',$order_sn);
                    $invite->GetuiMsg($user_data->uid,$to_uid,$money,'buy');
                }
            }
        }

        //更新活动base总报名数量
        $db->query('UPDATE play_excercise_base SET join_number=join_number+?, join_ault=join_ault+?, join_child=join_child+? WHERE id=?', array($people_number, $ault_number, $child_number, $base_data->id))->count();

        /**************** 订单生成成功 ****************/
        if ($use_account_money) {//使用账户余额支付
            $order_info = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));

            $order_pay = new OrderExcercisePay();
            $pay_status = $order_pay->paySuccess($order_info, '', 'account', $user_data->uid);

            if ($pay_status) {
                $invite = new Invite();
                $middleware = $invite->middleware($order_info,1);
                // middleware  0:不跳转中间页直接跳订单详情(默认)， 1:先跳中间页
                return ['status' => 2, 'order_sn' => $order_sn, 'middleware' => $middleware];
            } else {
                //订单生成成功,支付过程失败
                return ['status' => 0, 'message' => "账户支付失败"];
            }

        } else {
            $order_info = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));
            $invite = new Invite();
            $middleware = $invite->middleware($order_info);
            return ['status' => 1, 'order_sn' => $order_sn, 'middleware' => $middleware];
        }
    }
}
