<?php

/**
 * 使用验证码
 */
namespace Deyi\OrderAction;

use Deyi\Account\Account;
use Deyi\BaseController;
use Deyi\Coupon\Coupon;
use Deyi\Integral\Integral;
use Deyi\Invite\Invite;
use library\Service\System\Cache\RedCache;
use Deyi\Seller\Seller;
use Deyi\SendMessage;
use Zend\Db\Sql\Predicate\Expression;
use Application\Module;


class UseExcerciseCode
{
    use BaseController;

    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }

    //多个order
    public function UseOrder($order_sn)
    {

        $db=$this->_getAdapter();
        $orders = explode(',', $order_sn);
        foreach ($orders as $v) {
            if (!is_numeric($v)) {
                continue;
            }

            $conn = $db->getDriver()->getConnection();
            $conn->beginTransaction();

            $status1=$db->query("UPDATE play_order_info SET use_number=(buy_number-back_number-backing_number),pay_status=5, use_dateline=?  WHERE order_sn=? AND pay_status>1",array(time(), $v))->count();
            if(!$status1){
                $conn->rollback();
                return array('status' => 0, 'message' => "订单未付款或已使用".$v);
            }
            $status2=$db->query("UPDATE play_excercise_code  SET status=1,use_dateline=? WHERE order_sn=? AND status=0",array(time(),$v))->count();
            if (!$status2) {
                $conn->rollback();
                return array('status' => 0, 'message' => "验证失败:" . $v);
            }else{
                $conn->commit();

//                $invite = new Invite();
//                $invite->useevent($v);
                $codeData = $this->_getPlayDistributionDetailTable()->fetchAll(array('sell_type' => 2, 'order_id' => $v, 'sell_status' => 1));
                $Seller = new Seller();
                foreach ($codeData as $code) {
                    $Seller->used($v, $code['code_id']);
                }

                //记录操作日志
                $this->_getPlayOrderActionTable()->insert(array(
                    'order_id' => $v,
                    'play_status' => 5,
                    'action_user' => 0,
                    'action_note' => "整单使用成功,订单号:{$v}",
                    'dateline' => time(),
                    'action_user_name' => $_COOKIE['user']?'管理员:'.$_COOKIE['user']:'系统操作'
                ));

                $data_order = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $v));

                //当前订单兑换码
                $data_code = $db->query(
                    "SELECT play_excercise_code.*, play_excercise_price.price_name
                     FROM play_excercise_code
                     LEFT JOIN play_excercise_price ON play_excercise_price.id = play_excercise_code.pid
                     WHERE play_excercise_code.order_sn = ?",
                    array($v)
                );

                $data_price_name = array();
                foreach ($data_code as $val) {
                    if (!in_array($val->price_name, $data_price_name)) {
                        $data_price_name[] = $val->price_name;
                    }
                }
                $data_str_price_name = implode(" ", $data_price_name);

                $data_message_param   = array(
                    'phone'          => $data_order->buy_phone,
                    'goods_name'     => $data_order->coupon_name,
                    'game_name'      => $data_str_price_name,
                    'game_time'      => '',
                    'buy_time'       => 0,
                    'use_time'       => 0,
                    'end_time'       => 0,
                    'limit_number'   => 0,
                    'custom_content' => '',
                    'price'          => 0,
                    'code'           => '',
                    'code_count'     => 0,
                    'zyb_code'       => '',
                    'teacher_phone'  => '',
                    'city'           => $data_order->order_city,
                    'goods_type'     => $data_order->order_type,
                    'meeting_place'  => '',
                    'meeting_time'   => 0,
                    'message_status' => SendMessage::MESSAGE_STATUS_USE_SUCCESS,
                    'message_type'   => SendMessage::MESSAGE_TYPE_ACTIVITY,
                );

                SendMessage::sendMessageToUser($data_message_param);

                // 使用成功时，推送消息
                $data_message_type = 10; // 消息类型为订单使用成功
                $data_inform_type  = 16; // 活动订单消息推送

                // 订单用户信息
                $data_order_user = $this->_getPlayUserTable()->get(array('uid' => $data_order->user_id));

                // 商品订单到期推送内容
                $data_inform = "【玩翻天】您购买的活动\"" . $data_order->coupon_name . "\"已成功使用，希望下次与您相约玩翻天，继续欢乐之旅！记得给好评哦~";

                // 商品订单到期系统消息
                $data_message = "您购买的活动\"" . $data_order->coupon_name . "\"已成功使用";

                // 链接到的内容
                $data_link_id = array(
                    'lid'   => $data_order->order_sn,
                    'id'    => $data_order->coupon_id,
                    'type'  => 'kidsplay'
                );

                $class_sendMessage = new SendMessage();
                $class_sendMessage->sendMes($data_order->user_id, $data_message_type, '', $data_message, $data_link_id);
                $class_sendMessage->sendInform($data_order->user_id, $data_order_user->token, $data_inform, $data_inform, $data_order->order_sn, $data_inform_type, $data_order->coupon_id);
            }
        }

        return array('status' => 1, 'message' => "验证成功");
    }

    //多个code
    public function UseCode($code)
    {

        $codes = explode(',', $code);
        $db=$this->_getAdapter();
        foreach ($codes as $v) {
            if (!is_numeric($v)) {
                continue;
            }
            $res = $this->_getPlayExcerciseCodeTable()->get(array('code' => $v));

            if (!$res) {
                return array('status' => 0, 'message' => "对应订单不存在:" . $v);
            }

            $conn = $db->getDriver()->getConnection();
            $conn->beginTransaction();

            $status1=$db->query("UPDATE play_order_info SET use_number=use_number+1,  use_dateline=?  WHERE order_sn=? AND pay_status>1",array(time(),$res->order_sn))->count();

            if(!$status1){
                $conn->rollback();
                return array('status' => 0, 'message' => "订单未付款");
            }
            $status2=$db->query("UPDATE play_excercise_code  SET status=1,use_dateline=? WHERE code=? AND status=0",array(time(),$v))->count();
            if ($status2) {
                $conn->commit();
//                $invite = new Invite();
//                $invite->useevent($res->order_sn);
                //查询是否还有未验证
                $sel = $this->_getPlayExcerciseCodeTable()->fetchAll(array('order_sn' => $res->order_sn, 'status' => 0))->count();
                if (!$sel) {
                    $this->_getPlayOrderInfoTable()->update(array('pay_status' => 5, 'use_number' => new Expression('buy_number-back_number-backing_number')), array('order_sn' => $res->order_sn));
                }

                $Seller = new Seller();
                $Seller->used($res->order_sn, $res->id);

                //记录操作日志
                $this->_getPlayOrderActionTable()->insert(array(
                    'order_id' => $res->order_sn,
                    'play_status' => 5,
                    'action_user' => 0,
                    'action_note' => "单个验证码使用成功,验证码:{$v}",
                    'dateline' => time(),
                    'action_user_name' => $_COOKIE['user']?'管理员:'.$_COOKIE['user']:'系统操作'
                ));

                $data_order           = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $res->order_sn));
                $data_excercise_price = $this->_getPlayExcercisePriceTable()->get(array('id' => $res->pid));

                $data_message_param   = array(
                    'phone'          => $data_order->buy_phone,
                    'goods_name'     => $data_order->coupon_name,
                    'game_name'      => $data_excercise_price->price_name,
                    'game_time'      => '',
                    'buy_time'       => 0,
                    'use_time'       => 0,
                    'end_time'       => 0,
                    'limit_number'   => 0,
                    'custom_content' => '',
                    'price'          => 0,
                    'code'           => '',
                    'code_count'     => 0,
                    'zyb_code'       => '',
                    'teacher_phone'  => '',
                    'city'           => $data_order->order_city,
                    'goods_type'     => $data_order->order_type,
                    'meeting_place'  => '',
                    'meeting_time'   => 0,
                    'message_status' => SendMessage::MESSAGE_STATUS_USE_SUCCESS,
                    'message_type'   => SendMessage::MESSAGE_TYPE_ACTIVITY,
                );

                SendMessage::sendMessageToUser($data_message_param);

                // 使用成功时，推送消息
                $data_message_type = 10; // 消息类型为订单使用成功
                $data_inform_type  = 16; // 活动订单消息推送

                // 订单用户信息
                $data_order_user = $this->_getPlayUserTable()->get(array('uid' => $data_order->user_id));

                // 商品订单到期推送内容
                $data_inform = "【玩翻天】您购买的活动\"" . $data_order->coupon_name . "\"已成功使用，希望下次与您相约玩翻天，继续欢乐之旅！记得给好评哦~";

                // 商品订单到期系统消息
                $data_title   = "订单使用成功";
                $data_message = "您购买的活动\"" . $data_order->coupon_name . "\"已成功使用";

                // 链接到的内容
                $data_link_id = array(
                    'lid'  => $data_order->order_sn,
                    'id'   => $data_order->coupon_id,
                    'type' => 'kidsplay'
                );

                $class_sendMessage = new SendMessage();
                $class_sendMessage->sendMes($data_order->user_id, $data_message_type, $data_title, $data_message, $data_link_id);
                $class_sendMessage->sendInform($data_order->user_id, $data_order_user->token, $data_inform, $data_inform, $data_order->order_sn, $data_inform_type, $data_order->coupon_id);
            } else {
                $conn->rollback();
                return array('status' => 0, 'message' => "验证失败");
            }
        }
        return array('status' => 1, 'message' => "验证成功");


    }


}
