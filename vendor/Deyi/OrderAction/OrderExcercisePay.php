<?php

namespace Deyi\OrderAction;

use Application\Module;
use Deyi\Account\Account;
use Deyi\BaseController;
use library\Service\System\Cache\RedCache;
use Deyi\Seller\Seller;
use Deyi\SendMessage;
use Deyi\Coupon\Coupon;
use Deyi\ZybPay\ZybPay;
use Deyi\Integral\Integral;
use library\Service\User\Purchase;
use library\Service\User\User;

class OrderExcercisePay
{
    use BaseController;
    use OrderBack;

    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }

    /**
     * 订单支付成功回调,后续处理,短信,返现等等
     * @param $order_info
     * @param string $trade_no |支付流水
     * @param $pay_type |union|alipay|other|account
     * @param $accountName
     * @return bool
     */
    public function paySuccess($order_info, $trade_no = '', $pay_type = 'account', $accountName) {
        $real_pay = $order_info->real_pay;  //银行卡金额
        $account_money = $order_info->account_money; //账户金额

        $adapter = $this->_getAdapter();

        $data_excercise = $adapter->query(
            "SELECT play_excercise_base.name, play_excercise_base.message_custom_content, play_excercise_event.start_time
             FROM play_excercise_event
             LEFT JOIN play_excercise_base ON play_excercise_base.id = play_excercise_event.bid
             WHERE play_excercise_event.id = ?",
            array($order_info->coupon_id)
        )->current();

        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();

        // 判断是否已经处理过
        if ($order_info->pay_status < 2) {
            //使用账户余额支付
            if ($pay_type == 'account') {
                $account_data = $adapter->query("select * from play_account where uid=?", array($order_info->user_id))->current();
                if (!$account_data || !$account_data->status) {
                    $conn->rollback();
                    return false;
                }
                //用哪个账户的钱,优先不可提现账户
                $can_back_money_flow = null;
                if (($account_data->now_money - $order_info->real_pay) < $account_data->can_back_money) {
                    $s9 = $adapter->query("UPDATE play_account SET now_money=now_money-{$order_info->real_pay},can_back_money=now_money,last_time=? WHERE uid=? AND now_money>={$order_info->real_pay}", array(time(), $order_info->user_id))->count();
                    $can_back_money_flow = $order_info->real_pay - ($account_data->now_money - $account_data->can_back_money);
                } else {
                    $s9 = $adapter->query("UPDATE play_account SET now_money=now_money-{$order_info->real_pay},last_time=? WHERE uid=? AND now_money>={$order_info->real_pay}", array(time(), $order_info->user_id))->count();
                }

                $coupon_name = '购买活动' . $order_info->coupon_name ?: '';
                $s10 = $adapter->query("INSERT INTO play_account_log (id,uid,action_type,action_type_id,object_id,flow_money,surplus_money,dateline,description,status,user_account,check_status,can_back_money_flow) VALUES (NULL ,?,?,?,?,?,?,?,?,?,?,?,?)",
                    array($order_info->user_id, 2, 1, $order_info->order_sn, $order_info->real_pay, bcsub($account_data->now_money, $order_info->real_pay, 2), time(), $coupon_name, 1, $order_info->user_id, 1, $can_back_money_flow))->count();

                if (!$s9 or !$s10) {
                    $conn->rollback();
                    return false;
                } else {
                    $account_money = $order_info->real_pay;
                    $real_pay = 0;
                    $trade_no = $adapter->getDriver()->getLastGeneratedValue(); //交易流水记录
                    //$conn->commit();
                }

            }


            //判断是否是团购订单
            $pay_status = 2;


            //支付成功
            $approve_status = $pay_type == 'account' ? 2 : 1; //用户账户付款 付款成功则审核通过
            $s = $adapter->query("update play_order_info set account_type=?,order_status=?,pay_status=?,trade_no=?,account=?,real_pay=?,account_money=?,approve_status=? WHERE order_sn=?",
                array($pay_type, 1, $pay_status, $trade_no, $accountName, $real_pay, $account_money,$approve_status, $order_info->order_sn))->count();

            if (!$s) {
                $conn->rollback();
                return false;
            }

            //记录判断是否回收后又支付,记录并邮件通知
            if($order_info->order_status==0){
                $this->errorLog("订单号{$order_info->order_sn}已被系统回收,现已支付");
            }


            // 记录操作日志
            $s = $adapter->query('INSERT INTO play_order_action (order_id,play_status,action_user,action_note,dateline,action_user_name) VALUES (?,?,?,?,?,?)', array(
                $order_info->order_sn,
                2,
                $order_info->user_id,
                '支付成功',
                time(),
                '用户' . $order_info->username
            ));

            if (!$s) {
                $conn->rollback();
                return false;
            }
            $conn->commit();  //核心事务完成

            $total_money = bcadd($account_money, $real_pay, 2);  //账户加银行卡总支付金额


            //支付完成消息

            $message = "您购买的{$order_info->coupon_name}已支付完成";
            if ($pay_status == 2) {
                //当前订单兑换码
                $code_data = $this->_getPlayExcerciseCodeTable()->fetchAll(array('order_sn' => $order_info->order_sn));

                //当前订单兑换码
                $data_code = $adapter->query(
                    "SELECT play_excercise_code.*, play_excercise_price.price_name
                     FROM play_excercise_code
                     LEFT JOIN play_excercise_price ON play_excercise_price.id = play_excercise_code.pid
                     WHERE play_excercise_code.order_sn = ?",
                    array($order_info->order_sn)
                );

                $data_str_code   = '';
                $data_price_name = array();
                foreach ($data_code as $v) {
                    if ($data_str_code) {
                        $data_str_code .= ',(' . $v->code . ')';
                    } else {
                        $data_str_code .= '(' . $v->code . ')';
                    }

                    if (!in_array($v->price_name, $data_price_name)) {
                        $data_price_name[] = $v->price_name;
                    }
                }
                $data_str_price_name = implode(" ", $data_price_name);
                $this->sendMes($order_info->user_id, $message, json_encode(array('type' => 'kidsplay', 'id' => $order_info->coupon_id, 'lid' => $order_info->order_sn)));

                $data_message_param = array(
                    'phone'          => $order_info->buy_phone,
                    'goods_name'     => $order_info->coupon_name,
                    'game_name'      => $data_str_price_name,
                    'game_time'      => $data_excercise->start_time,
                    'buy_time'       => $order_info->dateline,
                    'use_time'       => 0,
                    'end_time'       => 0,
                    'limit_number'   => 0,
                    'custom_content' => $data_excercise->message_custom_content,
                    'price'          => $total_money,
                    'code'           => $data_str_code,
                    'code_count'     => $data_code->count(),
                    'zyb_code'       => '',
                    'teacher_phone'  => '',
                    'city'           => $order_info->order_city,
                    'goods_type'     => $order_info->order_type,
                    'meeting_place'  => '',
                    'meeting_time'   => 0,
                    'message_status' => SendMessage::MESSAGE_STATUS_PAY_SUCCESS,
                    'message_type'   => SendMessage::MESSAGE_TYPE_ACTIVITY,
                );

                SendMessage::sendMessageToUser($data_message_param);

                //SendMessage::Send4($order_info->buy_phone, $order_info->coupon_name,$total_money,$code_len,$order_info->order_city);
            }

            $Seller = new Seller();
            $Seller->fission($order_info);


            //支付完成后更新咨询状态
            Purchase::updateConsultStatus($order_info);


//            //支付完成奖励积分和票券
//            $integral = new Integral();
//            $integral->buyGood($order_info->user_id, $order_info->order_sn, $total_money, $order_info->order_city, $order_info->coupon_name);
//            //奖励现金券
//            $coupon = new Coupon();
//            $coupon->getCashCouponByBuy($order_info->user_id, $order_info->coupon_id, $order_info->coupon_id, $order_info->order_sn, $order_info->order_city, $order_info->coupon_name);
//            //返利
//            $cash = new Account();
//            $cash->getCashByBuy($order_info->user_id, $order_info->coupon_id, $order_info->coupon_id, $order_info->order_sn, $order_info->order_city, $order_info->coupon_name);

            // 分享抽奖活动
            $param_data = array(
                'log_date'   => '2020-01-01',
                'lottery_id' => 5,                      // 抽奖活动id
                'user_id'    => $order_info->user_id,
                'total'      => 1,
                'op_total'   => 0,
                'may_total'  => 5,
                'created'    => date('Y-m-d H:i:s'),
            );

            $param_where = array(
                'lottery_id' => 5,                      // 抽奖活动id
                'user_id'    => $order_info->user_id,
                'log_date'   => date('Y-m-d H:i:s')
            );

            User::updateLotteryUserData($param_data, $param_where);
        } else {
            return false;
        }
        RedCache::del('D:tneedPay:' . $order_info->user_id);
        return true;
    }

    //支付完成的消息
    private function sendMes($uid, $message, $link_id)
    {
        $title = '支付完成';
        $this->_getPlayUserMessageTable()->insert(array('uid' => $uid, 'type' => 5, 'title' => $title, 'deadline' => time(), 'message' => $message, 'link_id' => $link_id));
    }


}
