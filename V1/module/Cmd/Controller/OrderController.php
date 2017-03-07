<?php
namespace Cmd\Controller;

use Deyi\BaseController;
use Deyi\GetCacheData\ExcerciseCache;
use Deyi\GeTui\GeTui;
use Deyi\JsonResponse;
use Deyi\OrderAction\OrderExcerciseBack;
use Deyi\OrderAction\UseExcerciseCode;
use Deyi\SendMessage;
use Deyi\WeiXinFun;
use library\Fun\M;
use library\Service\Kidsplay\Kidsplay;
use library\Service\Order\CancelOrder;
use library\Service\Order\OrderInfo;
use library\Service\ServiceManager;
use library\Service\System\Logger;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;

class OrderController extends AbstractActionController
{

    use BaseController;
    use JsonResponse;


    //自动退订脚本
    public function autoBackAction()
    {

// 只在白天运行
        $time = time();

        $start_time = strtotime(date("Y-m-d 00:00:00"));
        $am_time = $start_time + 3600 * 8;
        $pm_time = $start_time + 3600 * 22;

// 判断时间是否大于早上8点 小于晚上10点
        if ($time < $am_time or $time > $pm_time) {
            exit;
        }


        $pdo = M::getAdapter();

        $class_sendMessage = new SendMessage();


//推送


//付款状态 ;0未付款;1付款中;2已付款 3  退款中 4 退款成功 5已使用 6已过期
//卡券状态   0 => '未使用',   1 => '已使用',  2 => '已退款', 3 => '退款中'

// 查询所有距离使用期限只有三天，并且已付款，未全部使用，未退订的活动票券  back_number  buy_number

        $res = $pdo->query("
SELECT
    play_order_info.coupon_id,
	play_order_info.order_sn,
	play_order_info.order_status,
	play_order_info.pay_status,
	play_order_info.user_id,
	play_order_info.username,
	play_order_info.coupon_name,
	play_order_info.real_pay,
	play_order_info.coupon_unit_price,
	play_order_info.shop_name,
	play_order_info.shop_id,
	play_order_info.buy_number,
	play_order_info.buy_phone,
    play_order_info.voucher_id,
    play_order_info.voucher,
	play_order_info.use_number,
	play_order_info.back_number,
	play_order_info.dateline,
	play_order_info.use_dateline,
	play_order_info.group_buy_id,
	play_order_info.order_city,
	play_game_info.buy,
    play_game_info.price_name,
	play_game_info.total_num,
	play_game_info.start_time,
	play_game_info.end_time,
	play_game_info.refund_time,
	play_order_info_game.game_info_id,
	play_group_buy.join_number,
    play_group_buy.limit_number,
    play_group_buy.uid AS index_uid,
    play_organizer_game.message_type
FROM
	play_order_info
LEFT JOIN play_order_info_game ON play_order_info.order_sn=play_order_info_game.order_sn
LEFT JOIN play_game_info ON play_game_info.id = play_order_info_game.game_info_id
LEFT JOIN play_organizer_game ON play_order_info.coupon_id = play_organizer_game.id
LEFT JOIN play_group_buy ON play_order_info.group_buy_id = play_group_buy.id

WHERE
	play_order_info.order_status = 1
AND play_order_info.pay_status >= 2
AND play_order_info.pay_status <= 4
AND play_order_info.order_type = 2
AND (
	(use_number + back_number) != buy_number
)
AND (
	(
		play_game_info.end_time < (UNIX_TIMESTAMP() + 259200) -- 距离结束还有三天
        AND play_game_info.end_time - play_game_info.start_time >= 259200 -- 开始和结束时间之差为3天
		AND (
			SELECT
				count(*)
			FROM
				play_message_log
			WHERE
				play_message_log.order_sn = play_order_info.order_sn
			AND m_type <= 2
		) = 0 -- 未发送过任何短息
	)
    OR (
        play_game_info.end_time < (UNIX_TIMESTAMP() + 86400) -- 距离结束还有一天
        AND (play_game_info.end_time - play_game_info.start_time >= 0) AND (play_game_info.end_time - play_game_info.start_time < 259200) -- 开始和结束时间之差小于72小时，大于24小时
		AND (
			SELECT
				count(*)
			FROM
				play_message_log
			WHERE
				play_message_log.order_sn = play_order_info.order_sn
			AND m_type = 2
		) = 0 -- 未发送过已过使用时间短信
	)
	OR (
		(play_game_info.end_time+864000) < UNIX_TIMESTAMP() -- 或者已过使用时间
		AND (
			SELECT
				count(*)
			FROM
				play_message_log
			WHERE
				play_message_log.order_sn = play_order_info.order_sn
			AND m_type = 2
		) = 0 -- 未发送过已过使用时间短信
	)
)
", []);

        foreach ($res as $data) {
            $data->username = addslashes($data->username);
            //echo "处理卡券{$data->order_sn}\n";
            // 判断是否已过使用时间，处理过一次的订单不会重复处理(1.卡去都已设为退订中 2.订单设为了已过期)
            if (($data->end_time + 864000) < $time) {

                if ($data->group_buy_id != 0) {
                    //全部设为已过期
                    $data->refund_time = $time - 1;
                }
                if ($data->refund_time >= $time) { //可退款

                    $conn = $pdo->getDriver()->getConnection();
                    $conn->beginTransaction();

                    if ($data->voucher_id) {
                        $cash_data = $pdo->query("select * from play_cashcoupon_user_link WHERE id={$data->voucher_id}", [])->current();
                        if (!$cash_data) {
                            echo "现金券异常 失败\n";
                            $conn->rollBack();
                        }

                        if ($cash_data and $cash_data->is_back == 0) {

                            if ($cash_data->back_money != $data->voucher) {
                                $data->voucher = bcsub($data->voucher, $cash_data->back_money, 2); //剩余可退金额
                            }
                            if ($data->coupon_unit_price >= $data->voucher) {
                                //代金券小于当前金额,销毁代金券,退款扣除代金券后的剩余金额
                                $cash1 = $pdo->query("UPDATE play_cashcoupon_user_link SET `is_back`=1, `back_money`={$data->voucher} WHERE id ={$data->voucher_id}", [])->count();

                                if (!$cash1) {
                                    echo "现金券异常1 失败\n";
                                    $conn->rollBack();
                                }

                                $back_money = bcsub($data->coupon_unit_price, $data->voucher, 2);
                            } else {
                                //代金券大于当前金额,用户退款金额为0,记录代金券剩余金额以供下次扣除
                                $cash2 = $pdo->query("UPDATE play_cashcoupon_user_link SET `back_money`= back_money + {$data->coupon_unit_price} WHERE id ={$data->voucher_id}", [])->count();

                                if (!$cash2) {
                                    echo "现金券异常2 失败\n";
                                    $conn->rollBack();
                                }

                                $back_money = 0;
                            }
                        } else {
                            $back_money = $data->coupon_unit_price;
                        }
                    } else {
                        $back_money = $data->coupon_unit_price;
                    }

                    $count = $pdo->query("UPDATE play_coupon_code SET `status`=3, back_time={$time}, back_money={$back_money} WHERE order_sn ={$data->order_sn} AND `status`=0", [])->count();  //返回受影响行数

                    if ($count) {

                        $pdo->query("UPDATE  play_order_info SET `pay_status`=3,backing_number={$count} WHERE order_sn ={$data->order_sn}", [])->count();


                        //todo 恢复卡券购买数
                        $status1 = $pdo->query("UPDATE play_game_info SET `buy`=buy-{$count} WHERE id={$data->game_info_id}", [])->count();
                        $status5 = $pdo->query("UPDATE play_organizer_game SET `buy_num`=buy_num-{$count} WHERE id={$data->coupon_id}", [])->count();
                        //记录订单状态修改
                        $status2 = $pdo->query("INSERT INTO `play_order_action` VALUES (NULL , {$data->order_sn}, 3, {$data->user_id}, '未使用_可退款_自动退款中',{$time},'{$data->username}', 0);", [])->count();
                        $content = "您于" . date('Y-m-d H:i', $data->dateline) . "购买的{$data->coupon_name}商品使用期限已过，现在自动进入退款流程，退款会于3个工作日返回你的玩翻天余额账户。";
                        $send_code = 1;
                        //记录短信

                        /**** 记录站内消息 ****/
                        $dou_title = '过期了(可退)';//活动
                        $dou_mes = '订单号为' . $data->order_sn . '的' . $data->coupon_name . '，现已过期，请进行退款操作。';
                        $link_id = urlencode(json_encode(array('type' => 'game', 'id' => $data->coupon_id, 'lid' => $data->order_sn)));
                        $status4 = $pdo->query("INSERT INTO `play_user_message` VALUES (NULL ,{$data->user_id},1,6,'{$dou_title}','{$dou_mes}',{$time},1,'{$link_id}', 1)", [])->count();

                        $status3 = $pdo->query("INSERT INTO `play_message_log` VALUES (NULL ,{$data->user_id},{$data->order_sn},2,'{$content}','{$data->buy_phone}',{$send_code}, " . $time . ");", [])->count();
                        if ($status1 and $status2 and $status3 and $status5) {
                            $conn->commit();
                            SendMessage::Send($data->buy_phone, $content);
                            echo "自动退款中事务处理成功\n";
                        } else {
                            echo "自动退款中事务处理失败\n";
                            $conn->rollBack();
                        }
                    } else {
                        $conn->rollBack();
                    }
                    continue;
                } elseif ($data->refund_time < $time) {

                    // 设为已过期

                    $conn = $pdo->getDriver()->getConnection();
                    $conn->beginTransaction();

                    $count1 = $pdo->query("UPDATE play_coupon_code SET `status`=2, `test_status`=2, back_time={$time} WHERE order_sn ={$data->order_sn} AND `status`=0", [])->count();

                    //$count2 = $pdo->query("UPDATE play_coupon_code SET `status`=2 WHERE order_sn ={$data->order_sn} AND `status`=3");  //之前已进入退款中的订单直接设为已退款

                    if ($count1) {
                        if ($data->group_buy_id != 0) {
                            //todo 恢复卡券购买数
                            $status2 = $pdo->query("UPDATE play_game_info SET `buy`=buy-1 WHERE id={$data->game_info_id}", [])->count();
                            $status6 = $pdo->query("UPDATE play_organizer_game SET `buy_num`=buy_num-1 WHERE id={$data->coupon_id}", [])->count();
                        } else {
                            //todo 恢复卡券购买数
                            $status2 = $pdo->query("UPDATE play_game_info SET `buy`=buy-{$count1} WHERE id={$data->game_info_id}", [])->count();
                            $status6 = $pdo->query("UPDATE play_organizer_game SET `buy_num`=buy_num-{$count1} WHERE id={$data->coupon_id}", [])->count();
                        }
                    } else {
                        $status2 = true;
                        $status6 = true;
                    }
                    if ($count1) {
                        $status1 = $pdo->query("UPDATE play_order_info SET `pay_status`=6,back_number=back_number+{$count1} WHERE order_sn ={$data->order_sn}", [])->count();
                        //记录订单状态修改
                        $status3 = $pdo->query("INSERT INTO `play_order_action` VALUES (NULL , {$data->order_sn}, 3, {$data->user_id}, '未使用_不可退款_失效自动退款',{$time}, '{$data->username}', 0);", [])->count();
                        $content = "“玩翻天”唉，太遗憾了，您于" . date('Y-m-d H:i', $data->dateline) . "购买的\"{$data->coupon_name}\"已经过期无法使用了，特价商品，恕不接受退款申请哦，下次别放我鸽子哈！";
                        $send_code = 1;
                        //记录短信

                        /**** 记录站内消息 ****/
                        $dou_title = '过期了(不可退)';//活动
                        $dou_mes = '订单号为' . $data->order_sn . '的' . $data->coupon_name . '，现已过期。';
                        $link_id = urlencode(json_encode(array('type' => 'game', 'id' => $data->coupon_id, 'lid' => $data->order_sn)));
                        $status5 = $pdo->query("INSERT INTO `play_user_message` VALUES (NULL ,{$data->user_id},1,7,'{$dou_title}','{$dou_mes}',{$time},1,'{$link_id}', 1)", [])->count();

                        $status4 = $pdo->query("INSERT INTO `play_message_log` VALUES (NULL ,{$data->user_id},{$data->order_sn},2,'{$content}','{$data->buy_phone}',{$send_code}, " . $time . ");", [])->count();
                        if ($status1 and $status2 and $status3 and $status4 and $status6) {
                            $conn->commit();
                            SendMessage::Send($data->buy_phone, $content);
                            echo "失效自动退款事务处理成功\n";
                        } else {
                            echo "失效自动退款事务处理失败\n";
                            $conn->rollBack();
                        }
                    } else {
                        $conn->rollBack();
                    }
                    continue;
                } else {
                    echo "错误\n";
                }
            } else {
                //判断是否提醒过 是->进入下一个循环
                $m_res = $pdo->query("SELECT * FROM `play_message_log` WHERE order_sn={$data->order_sn} AND `m_type`=1 AND `status`=1", [])->current();
                if ($m_res) {
                    continue;
                }

                if ($data->refund_time > $time) {
                    if ($data->message_type == 2) {
                        $content = "温馨提示：亲爱的小玩家，在前台办理入住时，直接报预定人姓名和电话号码，能更快查到订单哟。祝您旅途愉快！遇到任何问题请联系玩翻天客服4008007221。";
                    } else {

                        $content = "亲爱的小玩家，您" . date('Y-m-d H:i', $data->dateline) . "购买的\"" . $data->coupon_name . ' ' . $data->price_name . "\"最后使用期限为" . date('Y-m-d H:i', $data->end_time) . "，请尽快使用！立即添加微信号：" . SendMessage::getWeixinName($data->order_city) . "，即可免费获取私人专享客户服务。";
                    }
                } else {
                    if ($data->message_type == 2) {
                        $content = '';
                    } else {
                        $content = "亲爱的小玩家，您" . date('Y-m-d H:i', $data->dateline) . "购买的\"" . $data->coupon_name . ' ' . $data->price_name . "\"最后使用期限为" . date('Y-m-d H:i', $data->end_time) . "，过期无效且不支持退款。立即添加微信号：" . SendMessage::getWeixinName($data->order_city) . "，即可免费获取私人专享客户服务。";
                    }
                }

                if (($data->end_time - $data->start_time) >= 259200 && ($data->end_time < time() + 259200 && $data->message_type != 2) || (($data->end_time - $data->start_time) < 259200 && ($data->end_time - $data->start_time) >= 86400 && ($data->end_time < time() + 86400) && $data->message_type != 2) || (strtotime(date('Y-m-d')) <= $data->start_time && strtotime(date('Y-m-d 23:59:59')) >= $data->start_time && $data->message_type == 2)) {
                    if ($content) {
                        $send_code = (int)SendMessage::Send($data->buy_phone, $content);
                        //记录
                        $send_code = 1;
                        $pdo->query("INSERT INTO `play_message_log` VALUES (NULL ,{$data->user_id},{$data->order_sn},1,'{$content}','{$data->buy_phone}',{$send_code}, " . $time . ");", [])->count();

                        // 获取用户信息
                        $data_user = $pdo->query("SELECT * FROM play_user WHERE uid = {$data->user_id} LIMIT 1", [])->current();

                        // 进行消息推送与
                        $data_message_type = 9;  // 消息类型为商品订单过期提醒
                        $data_inform_type = 15; // 商品订单消息推送

                        // 商品订单到期推送内容
                        $data_inform = "【玩翻天】您购买的商品\"" . $data->coupon_name . "\"即将到期啦，快去使用吧！";

                        // 商品订单到期系统消息
                        $data_title = "订单到期提醒";
                        $data_message = "您购买的商品\"" . $data->coupon_name . "\"即将到期，请尽快使用";

                        // 链接到的内容
                        $data_link_id = array(
                            'lid' => $data->order_sn,
                            'id' => $data->coupon_id,
                            'type' => 'game'
                        );

                        $data_info = array(
                            'object_id' => $data->order_sn,
                            'object_rid' => $data->group_buy_id,
                        );


                        $class_sendMessage->sendMes($data->user_id, $data_message_type, $data_title, $data_message, $data_link_id);
                        $class_sendMessage->sendInform($data->user_id, $data_user->token, $data_inform, $data_inform, $data_info, $data_inform_type, $data->coupon_id);
                    }
                } else {
                    continue;
                }
            }
        }


    }

    // 解散未满员的团
    public function disgroupAction()
    {
        $time = time();
        $pdo = M::getAdapter();
        // 1.查询已失效的团   时间 未满员 连出所有已付的订单
        $res = $pdo->query("SELECT * FROM play_group_buy LEFT JOIN play_order_info ON  play_group_buy.id=play_order_info.group_buy_id WHERE play_group_buy.end_time<UNIX_TIMESTAMP() AND play_group_buy.join_number<play_group_buy.limit_number AND play_order_info.pay_status=7", []);
        foreach ($res as $data) {
            echo "处理卡券{$data->order_sn}\n";
            // 2.退订所有订单    退钱=>退款中状态!

            $conn = $pdo->getDriver()->getConnection();
            $conn->beginTransaction();

            $count = $pdo->query("UPDATE play_coupon_code SET `status`=3, back_time={$time}, back_money={$data->coupon_unit_price} WHERE order_sn ={$data->order_sn} AND `status`=0",[])->count();  //返回受影响行数

            if ($count) {
                $pdo->query("UPDATE  play_order_info SET `pay_status`=3  WHERE order_sn ={$data->order_sn}",[])->count();


                if ($data->user_id == $data->uid) {
                    //todo 恢复卡券购买数
                    $status1 = $pdo->query("UPDATE play_game_info SET `buy`=buy-{$data->limit_number} WHERE id={$data->game_info_id}",[])->count();
                    $status5 = $pdo->query("UPDATE play_organizer_game SET `buy_num`=buy_num-{$data->limit_number} WHERE id={$data->coupon_id}",[])->count();
                    $status7 = $pdo->query("UPDATE play_group_buy SET `status`=0 WHERE id={$data->group_buy_id}",[])->count();
                    if (!$status7) {
                        $conn->rollBack();
                        exit('失败');
                    }

                } else {
                    //todo 已恢复
                    $status1 = true;
                    $status5 = true;
                }

                //记录订单状态修改
                $status2 = $pdo->query("INSERT INTO `play_order_action` VALUES (NULL , {$data->order_sn}, 3, {$data->user_id}, '团购_自动退款中',{$time},'{$data->username}', 0);",[])->count();
                $content = "亲爱的小玩家，您参加的\"{$data->coupon_name}\"由于在规定的时间内未满员,现已进入自动退款流程,详情请致电400-800-7221,立即添加微信号：" . SendMessage::getWeixinName($data->order_city) . "，即可免费获取私人专享客户服务。";
                // $content = "您于" . date('Y-m-d H:i', $data->dateline) . "的组团{$data->coupon_name}商品组团失败，现在自动进入退款流程，退款会于3个工作日返回你的玩翻天余额账户。";
                $send_code = 1; //记录短信
                /**** 记录站内消息 ****/
                $dou_title = '组团失败';//活动
                $dou_mes = '订单号为' . $data->order_sn . '的' . $data->coupon_name . '，组团失败，现已进入退款流程。';
                $link_id = urlencode(json_encode(array('type' => 'group', 'id' => $data->coupon_id, 'lid' => $data->order_sn, 'group_buy_id' => $data->group_buy_id)));
                $status4 = $pdo->query("INSERT INTO `play_user_message` VALUES (NULL ,{$data->user_id},1,6,'{$dou_title}','{$dou_mes}',{$time},1,'{$link_id}', 1)",[])->count();

                $status3 = $pdo->query("INSERT INTO `play_message_log` VALUES (NULL ,{$data->user_id},{$data->order_sn},2,'{$content}','{$data->buy_phone}',{$send_code}, " . $time . ");",[])->count();
                if ($status1 and $status2 and $status3 and $status5) {
                    $conn->commit();
                    SendMessage::Send($data->buy_phone, $content);
                    echo "自动退款中事务处理成功\n";
                } else {
                    echo "自动退款中事务处理失败\n";
//            var_dump($status1 , $status2 , $status3 , $status5);
                    $conn->rollBack();
                }
            } else {
                $conn->rollBack();
            }


        }
    }

    //对账
    public function reconciliationAction()
    {

        $ali = $this->contrastAli();

        if (!$ali['status']) {
            Logger::WriteErrorLog($ali['message']);
        }

        $union = $this->contrastUnion();

        if (!$union['status']) {
            Logger::WriteErrorLog($union['message']);
        }

        $wei = $this->contrastWei();

        if (!$wei['status']) {
            Logger::WriteErrorLog($wei['message']);
        }

        $weiPay = $this->contrastWeiWap();

        if (!$weiPay['status']) {
            Logger::WriteErrorLog($weiPay['message']);
        }


    }

    private function contrastAli()
    {
        $orderInfo = new OrderInfo();
        $start_time = strtotime(date("Y-m-d 00:00:00", time() - 24* 3600));
        $end_time = strtotime(date("Y-m-d 00:00:00", time()));

        $sql_ali_pay = "SELECT order_sn, real_pay FROM play_order_info WHERE account_type = 'alipay' AND order_status = 1 AND pay_status >= 2 AND dateline >= {$start_time} AND dateline < {$end_time}";
        $sql_ali_recharge = "SELECT id, flow_money FROM play_account_log WHERE `status` = 1 AND action_type = 1 AND action_type_id = 2 AND dateline >= {$start_time} AND dateline < {$end_time}";
        $sql_ali_refund_good = "SELECT play_coupon_code.order_sn, play_coupon_code.back_money FROM play_coupon_code INNER JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn WHERE account_type = 'alipay' AND play_coupon_code.back_money_time >= {$start_time} AND play_coupon_code.back_money_time < {$end_time}";
        $sql_ali_refund_activity = "SELECT play_excercise_code.order_sn, play_excercise_code.back_money FROM play_excercise_code INNER JOIN play_order_info ON play_order_info.order_sn = play_excercise_code.order_sn WHERE account_type = 'alipay' AND play_excercise_code.back_money_time >= {$start_time} AND play_excercise_code.back_money_time < {$end_time}";

        $ali_pay_data = M::getAdapter()->query($sql_ali_pay, array());
        $ali_recharge_data = M::getAdapter()->query($sql_ali_recharge, array());
        $ali_refund_good_data = M::getAdapter()->query($sql_ali_refund_good, array());
        $ali_refund_activity_data = M::getAdapter()->query($sql_ali_refund_activity, array());

        $contrasts = array(
            'type' => 'alipay',
            'pay' => array(),
            'recharge' => array(),
            'refund' => array(),
        );

        foreach ($ali_pay_data as $pay_data) {
            $contrasts['pay']['WFT'. intval($pay_data->order_sn)] = $pay_data->real_pay;
        }

        foreach ($ali_recharge_data as $recharge_data) {
            $contrasts['recharge']['WFTREC'. $recharge_data->id] =  $recharge_data->flow_money;
        }

        foreach ($ali_refund_good_data as $refund_good) {
            if (array_key_exists('WFT'. intval($refund_good->order_sn), $contrasts['refund'])) {
                $contrasts['refund']['WFT'. intval($refund_good->order_sn)] =  $contrasts['refund']['WFT'. intval($refund_good->order_sn)] + $refund_good->back_money;
            } else {
                $contrasts['refund']['WFT'. intval($refund_good->order_sn)] =  $refund_good->back_money;
            }
        }

        foreach ($ali_refund_activity_data as $refund_activity) {
            if (array_key_exists('WFT'. intval($refund_activity->order_sn), $contrasts['refund'])) {
                $contrasts['refund']['WFT'. intval($refund_activity->order_sn)] =  $contrasts['refund']['WFT'. intval($refund_activity->order_sn)] + $refund_activity->back_money;
            } else {
                $contrasts['refund']['WFT'. intval($refund_activity->order_sn)] =  $refund_activity->back_money;
            }
        }

        $result = $orderInfo->checkThirdPartyAccount($contrasts);

        return $result;

    }

    private function contrastUnion()
    {
        //银联的结算时间是 晚上11点到晚上11点
        $orderInfo = new OrderInfo();

        $start_time = strtotime(date("Y-m-d 00:00:00", time() - 24* 3600));
        $end_time = strtotime(date("Y-m-d 00:00:00", time()));
        $start_union_time = $start_time - 3600;
        $end_union_time = $end_time - 3600;

        $sql_union_pay = "SELECT order_sn, real_pay FROM play_order_info WHERE account_type = 'union' AND order_status = 1 AND pay_status >= 2 AND dateline >= {$start_union_time} AND dateline < {$end_union_time}";
        $sql_union_recharge = "SELECT id, flow_money FROM play_account_log WHERE `status` = 1 AND action_type = 1 AND action_type_id = 3 AND dateline >= {$start_union_time} AND dateline < {$end_union_time}";
        $sql_union_refund_good = "SELECT play_coupon_code.order_sn, play_coupon_code.back_money FROM play_coupon_code INNER JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn WHERE account_type = 'union' AND play_coupon_code.back_money_time >= {$start_union_time} AND play_coupon_code.back_money_time < {$end_union_time}";
        $sql_union_refund_activity = "SELECT play_excercise_code.order_sn, play_excercise_code.back_money FROM play_excercise_code INNER JOIN play_order_info ON play_order_info.order_sn = play_excercise_code.order_sn WHERE account_type = 'union' AND play_excercise_code.back_money_time >= {$start_union_time} AND play_excercise_code.back_money_time < {$end_union_time}";

        $union_pay_data = M::getAdapter()->query($sql_union_pay, array());
        $union_recharge_data = M::getAdapter()->query($sql_union_recharge, array());
        $union_refund_good_data = M::getAdapter()->query($sql_union_refund_good, array());
        $union_refund_activity_data = M::getAdapter()->query($sql_union_refund_activity, array());

        $contrasts = array(
            'type' => 'union',
            'pay' => array(),
            'recharge' => array(),
            'refund' => array(),
        );

        foreach ($union_pay_data as $pay_data) {
            $contrasts['pay']['WFT'. intval($pay_data->order_sn)] = $pay_data->real_pay;
        }

        foreach ($union_recharge_data as $recharge_data) {
            $contrasts['recharge']['WFTREC'. $recharge_data->id] =  $recharge_data->flow_money;
        }

        foreach ($union_refund_good_data as $refund_good) {
            if (array_key_exists('WFT'. intval($refund_good->order_sn), $contrasts['refund'])) {
                $contrasts['refund']['WFT'. intval($refund_good->order_sn)] =  $contrasts['refund']['WFT'. intval($refund_good->order_sn)] + $refund_good->back_money;
            } else {
                $contrasts['refund']['WFT'. intval($refund_good->order_sn)] =  $refund_good->back_money;
            }
        }

        foreach ($union_refund_activity_data as $refund_activity) {
            if (array_key_exists('WFT'. intval($refund_activity->order_sn), $contrasts['refund'])) {
                $contrasts['refund']['WFT'. intval($refund_activity->order_sn)] =  $contrasts['refund']['WFT'. intval($refund_activity->order_sn)] + $refund_activity->back_money;
            } else {
                $contrasts['refund']['WFT'. intval($refund_activity->order_sn)] =  $refund_activity->back_money;
            }
        }

        $result = $orderInfo->checkThirdPartyAccount($contrasts);

        return $result;
    }

    private function contrastWei()
    {

        $orderInfo = new OrderInfo();

        $start_time = strtotime(date("Y-m-d 00:00:00", time() - 24* 3600));
        $end_time = strtotime(date("Y-m-d 00:00:00", time()));

        $sql_wei_pay = "SELECT order_sn, real_pay FROM play_order_info WHERE account_type = 'weixin' AND order_status = 1 AND pay_status >= 2 AND dateline >= {$start_time} AND dateline < {$end_time}";
        $sql_wei_recharge = "SELECT id, flow_money FROM play_account_log WHERE `status` = 1 AND action_type = 1 AND action_type_id = 12 AND dateline >= {$start_time} AND dateline < {$end_time}";
        $sql_wei_refund_good = "SELECT play_coupon_code.order_sn, play_coupon_code.back_money FROM play_coupon_code INNER JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn WHERE account_type = 'weixin' AND play_coupon_code.back_money_time >= {$start_time} AND play_coupon_code.back_money_time < {$end_time}";
        $sql_wei_refund_activity = "SELECT play_excercise_code.order_sn, play_excercise_code.back_money FROM play_excercise_code INNER JOIN play_order_info ON play_order_info.order_sn = play_excercise_code.order_sn WHERE account_type = 'weixin' AND play_excercise_code.back_money_time >= {$start_time} AND play_excercise_code.back_money_time < {$end_time}";

        $wei_pay_data = M::getAdapter()->query($sql_wei_pay, array());
        $wei_recharge_data = M::getAdapter()->query($sql_wei_recharge, array());
        $wei_refund_good_data = M::getAdapter()->query($sql_wei_refund_good, array());
        $wei_refund_activity_data = M::getAdapter()->query($sql_wei_refund_activity, array());

        $contrasts = array(
            'type' => 'weiPay',
            'pay' => array(),
            'recharge' => array(),
            'refund' => array(),
        );

        foreach ($wei_pay_data as $pay_data) {
            $contrasts['pay']['WFT'. intval($pay_data->order_sn)] = $pay_data->real_pay;
        }

        foreach ($wei_recharge_data as $recharge_data) {
            $contrasts['recharge']['WFTREC'. $recharge_data->id] =  $recharge_data->flow_money;
        }

        foreach ($wei_refund_good_data as $refund_good) {
            if (array_key_exists('WFT'. intval($refund_good->order_sn), $contrasts['refund'])) {
                $contrasts['refund']['WFT'. intval($refund_good->order_sn)] =  $contrasts['refund']['WFT'. intval($refund_good->order_sn)] + $refund_good->back_money;
            } else {
                $contrasts['refund']['WFT'. intval($refund_good->order_sn)] =  $refund_good->back_money;
            }
        }

        foreach ($wei_refund_activity_data as $refund_activity) {
            if (array_key_exists('WFT'. intval($refund_activity->order_sn), $contrasts['refund'])) {
                $contrasts['refund']['WFT'. intval($refund_activity->order_sn)] =  $contrasts['refund']['WFT'. intval($refund_activity->order_sn)] + $refund_activity->back_money;
            } else {
                $contrasts['refund']['WFT'. intval($refund_activity->order_sn)] =  $refund_activity->back_money;
            }
        }

        $result = $orderInfo->checkThirdPartyAccount($contrasts);

        return $result;
    }

    private function contrastWeiWap()
    {
        $orderInfo = new OrderInfo();

        $start_time = strtotime(date("Y-m-d 00:00:00", time() - 24* 3600));
        $end_time = strtotime(date("Y-m-d 00:00:00", time()));

        $sql_wei_wap_pay = "SELECT order_sn, real_pay FROM play_order_info WHERE account_type = 'new_jsapi' AND order_status = 1 AND pay_status >= 2 AND dateline >= {$start_time} AND dateline < {$end_time}";
        $sql_wei_wap_recharge = "SELECT id, flow_money FROM play_account_log WHERE `status` = 1 AND action_type = 1 AND action_type_id = 25 AND dateline >= {$start_time} AND dateline < {$end_time}";
        $sql_wei_wap_refund_good = "SELECT play_coupon_code.order_sn, play_coupon_code.back_money FROM play_coupon_code INNER JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn WHERE account_type = 'new_jsapi' AND play_coupon_code.back_money_time >= {$start_time} AND play_coupon_code.back_money_time < {$end_time}";
        $sql_wei_wap_refund_activity = "SELECT play_excercise_code.order_sn, play_excercise_code.back_money FROM play_excercise_code INNER JOIN play_order_info ON play_order_info.order_sn = play_excercise_code.order_sn WHERE account_type = 'new_jsapi' AND play_excercise_code.back_money_time >= {$start_time} AND play_excercise_code.back_money_time < {$end_time}";

        $wei_wap_pay_data = M::getAdapter()->query($sql_wei_wap_pay, array());
        $wei_wap_recharge_data = M::getAdapter()->query($sql_wei_wap_recharge, array());
        $wei_wap_refund_good_data = M::getAdapter()->query($sql_wei_wap_refund_good, array());
        $wei_wap_refund_activity_data = M::getAdapter()->query($sql_wei_wap_refund_activity, array());

        $contrasts = array(
            'type' => 'weiWapPay',
            'pay' => array(),
            'recharge' => array(),
            'refund' => array(),
        );

        foreach ($wei_wap_pay_data as $pay_data) {
            $contrasts['pay']['WFT'. intval($pay_data->order_sn)] = $pay_data->real_pay;
        }

        foreach ($wei_wap_recharge_data as $recharge_data) {
            $contrasts['recharge']['WFTREC'. $recharge_data->id] =  $recharge_data->flow_money;
        }

        foreach ($wei_wap_refund_good_data as $refund_good) {
            if (array_key_exists(intval($refund_good->order_sn), $contrasts['refund'])) {
                $contrasts['refund'][intval($refund_good->order_sn)] =  $contrasts['refund'][intval($refund_good->order_sn)] + $refund_good->back_money;
            } else {
                $contrasts['refund'][intval($refund_good->order_sn)] =  $refund_good->back_money;
            }
        }

        foreach ($wei_wap_refund_activity_data as $refund_activity) {
            if (array_key_exists(intval($refund_activity->order_sn), $contrasts['refund'])) {
                $contrasts['refund'][intval($refund_activity->order_sn)] =  $contrasts['refund'][intval($refund_activity->order_sn)] + $refund_activity->back_money;
            } else {
                $contrasts['refund'][intval($refund_activity->order_sn)] =  $refund_activity->back_money;
            }
        }

        $result = $orderInfo->checkThirdPartyAccount($contrasts);

        return $result;
    }

    //未支付订单回收 活动和商品
    public function recoveryAction()
    {

        $start_time = time() - ServiceManager::getConfig('TRADE_CLOSED');  //15分钟
        $end_time = time()- 24*3600;

        $res = M::getPlayOrderInfoTable()->fetchAll(array('order_type >= ?' => 2, 'order_status' => 1, 'pay_status <= ?' => 1, 'dateline < ?' => $start_time, 'dateline > ?' => $end_time));
        $CancelOrder = new CancelOrder();
        foreach ($res as $value) {
            $res=$CancelOrder->CancelOrder($value->order_sn);
            print_r($res);
        }
    }

    //活动开始后验证  5分钟一次
    public function verifyAction()
    {
        error_reporting(-1);
        ini_set('display_errors', '1');
        $db = $this->_getAdapter();


        $useOrder = new UseExcerciseCode();


        //查询所有开始的活动场次  正常售卖和已满员

        $event_data = $db->query("
SELECT
	id
FROM
	play_excercise_event as e
WHERE
	e.start_time <UNIX_TIMESTAMP()
AND e.join_number >= e.least_number
AND e.sell_status IN (1, 2)
", array());

        foreach ($event_data as $e) {
            //查询活动订单
            $order_data = $db->query("
SELECT
	order_sn
FROM
	play_order_info as o
WHERE
 o.pay_status>1
AND o.order_type=3
AND  o.coupon_id=?
", array($e->id));

            foreach ($order_data as $order) {
                $res = $useOrder->UseOrder($order->order_sn);
//                var_dump($res);
            }
            $this->_getPlayExcerciseEventTable()->update(array('sell_status' => 3), array('id' => $e->id));

        }

        exit;


    }


    /**
     * 未满员的活动自动解散 5分钟执行一次
     * 活动开始前22小时短信通知
     */
    public function dissolveAction()
    {

        error_reporting(-1);
        ini_set('display_errors', '1');
        $adapter = $this->_getAdapter();

        $back = new OrderExcerciseBack();
        /********************* 超过时间,未达到完成条件的,全部退款 **************************/
        $res = $adapter->query("
SELECT
	play_order_info.order_sn,play_order_info.order_city,play_excercise_code.`code`,play_order_info.phone,play_order_info.coupon_name,play_order_info.bid
FROM
	play_excercise_event
LEFT JOIN play_order_info ON play_order_info.coupon_id = play_excercise_event.id
LEFT JOIN play_excercise_code ON play_order_info.order_sn = play_excercise_code.order_sn
WHERE
	play_order_info.order_type = 3
AND play_order_info.order_status = 1
AND play_order_info.pay_status >=2
AND play_excercise_code.`status`=0
AND  play_order_info.buy_number >(play_order_info.back_number+play_order_info.backing_number)
AND over_time < UNIX_TIMESTAMP()
AND join_number < least_number
ORDER BY order_sn ASC", array());

        $send_list = array(); //已经发送解散短信列表
        $bids = array(); //所有涉及到的活动列表

        $vir = new ExcerciseCache();
        foreach ($res as $v) {
            if ($v->order_sn) {
                //设为退款中
                $event_data = $back->backIng($v->order_sn, $v->code, 2);

                if (!isset($send_list[$v->order_sn]) and $event_data['status'] == 1) {
                    SendMessage::Send17($v->phone, $v->coupon_name, $v->order_city);
                    $send_list[$v->order_sn] = $v->order_sn;
                }
            }

            if (!in_array($v->bid, $bids)) {
                $vir_number = $vir->getvirnumByBid($v->bid);
                $this->_getPlayExcerciseBaseTable()->update(array('vir_number' => $vir_number), array('id' => $v->bid));
                $bids[] = $v->bid;
            }
        }

        /******************** 活动开始前22小时短信提醒集合 **********************/

        //参加人数达到最小人数,距离活动开始 22小时
        $res = $adapter->query("
SELECT
	play_excercise_event.id
FROM
	play_excercise_event
WHERE
	play_excercise_event.join_number >=play_excercise_event.least_number
AND play_excercise_event.send_meeting_msg=0
AND start_time < (UNIX_TIMESTAMP()+79200)
AND start_time > (UNIX_TIMESTAMP()+7200)
", array());

        foreach ($res as $v) {
            $orders = $adapter->query("
SELECT
	play_order_info.order_sn,
	play_order_info.user_id,
	play_order_info.buy_phone,
	play_order_info.coupon_name,
	play_order_info.coupon_id,
    play_order_info.order_city,
	play_order_otherdata.meeting_time,
	play_order_otherdata.meeting_place,
	play_excercise_event.start_time,
    play_excercise_event.teacher_phone,
    play_user.token
FROM
	play_order_info
LEFT JOIN play_order_otherdata ON play_order_otherdata.order_sn = play_order_info.order_sn
LEFT JOIN play_excercise_event ON play_excercise_event.id = play_order_info.coupon_id
LEFT JOIN play_user ON play_user.uid = play_order_info.user_id
WHERE
	play_order_info.order_type = 3
AND play_order_info.order_status = 1
AND play_order_info.pay_status = 2
AND play_order_info.coupon_id = ?
", array($v->id));

            foreach ($orders as $o) {
                // 集合地址不同
                if ($o->meeting_time) {  //修改ios 为空
                    $data_message_param = array(
                        'phone' => $o->buy_phone,
                        'goods_name' => $o->coupon_name,
                        'game_name' => '',
                        'game_time' => $o->start_time,
                        'buy_time' => 0,
                        'use_time' => 0,
                        'end_time' => 0,
                        'limit_number' => 0,
                        'custom_content' => '',
                        'price' => 0,
                        'code' => '',
                        'code_count' => 0,
                        'zyb_code' => '',
                        'teacher_phone' => $o->teacher_phone ? $o->teacher_phone : '4008007221',
                        'city' => $o->order_city,
                        'goods_type' => 0,
                        'meeting_place' => $o->meeting_place,
                        'meeting_time' => $o->meeting_time,
                        'message_status' => SendMessage::MESSAGE_STATUS_USE_REMIND_REFUND,
                        'message_type' => SendMessage::MESSAGE_TYPE_ACTIVITY,
                    );

                    SendMessage::sendMessageToUser($data_message_param);

                    $data_message_type = 9; // 消息类型为评论回复
                    $data_inform_type = 16; // 评论回复推送

                    $data_date = date('Y-m-d', $o->start_time);
                    $data_meeting_time = date('Y-m-d H:i:s', $o->meeting_time);
                    $data_start_time = date('Y-m-d H:i', $o->start_time);
                    $data_teacher_phone = $o->teacher_phone ? $o->teacher_phone : '4008007221';

                    // 商品订单到期推送内容
                    $data_inform = "【玩翻天】您参加的" . $data_date . "\"" . $o->coupon_name . "\"通知：请于" . $data_meeting_time . "在" . $o->meeting_place . "集合签到，活动将于" . $data_start_time . "正式开始。联系电话" . $data_teacher_phone;

                    // 商品订单到期系统消息
                    $data_title = "活动出行提醒";
                    $data_message = "您报名的活动即将开始了，请准时出行";

                    $data_link_id = array(
                        'type' => 'kidsplay',
                        'id' => $o->coupon_id,
                        'lid' => $o->order_sn,
                    );

                    $data_info = $o->order_sn;

                    $class_sendMessage = new SendMessage();
                    $class_sendMessage->sendMes($o->user_id, $data_message_type, $data_title, $data_message, $data_link_id);
                    $class_sendMessage->sendInform($o->user_id, $o->token, $data_inform, $data_inform, $data_info, $data_inform_type, $v->id);

                    //SendMessage::Send11($o->phone, $o->coupon_name, $o->meeting_time, $o->meeting_place, $o->start_time, $this->getPhone());
                }
            }

            $this->_getPlayExcerciseEventTable()->update(array('send_meeting_msg' => new  Expression('send_meeting_msg+' . 1)), array('id' => $v->id));
        }

        $res = $adapter->query("
        SELECT
        	play_excercise_event.id
        FROM
        	play_excercise_event
        WHERE
        	play_excercise_event.join_number >=play_excercise_event.least_number
        AND play_excercise_event.send_meeting_msg < 2
        AND start_time > UNIX_TIMESTAMP()
        AND start_time - 7200 < UNIX_TIMESTAMP()
", array());

        foreach ($res as $v) {
            $orders = $adapter->query("
SELECT
    play_order_info.user_id,
    play_order_info.coupon_id,
	play_order_info.order_sn,
	play_order_info.buy_phone,
	play_order_info.coupon_name,
    play_order_info.order_city,
	play_order_otherdata.meeting_time,
	play_order_otherdata.meeting_place,
	play_excercise_event.start_time,
	play_excercise_event.teacher_phone,
    play_user.token
FROM
	play_order_info
LEFT JOIN play_order_otherdata ON play_order_otherdata.order_sn = play_order_info.order_sn
LEFT JOIN play_excercise_event ON play_excercise_event.id = play_order_info.coupon_id
LEFT JOIN play_user ON play_user.uid = play_order_info.user_id
WHERE
	play_order_info.order_type = 3
AND play_order_info.order_status = 1
AND play_order_info.pay_status = 2
AND play_order_info.coupon_id = ?
", array($v->id));

            foreach ($orders as $o) {
                // 集合地址不同
                if ($o->meeting_time) {  //修改ios 为空
                    $data_message_param = array(
                        'phone' => $o->buy_phone,
                        'goods_name' => $o->coupon_name,
                        'game_name' => '',
                        'game_time' => $o->start_time,
                        'buy_time' => 0,
                        'use_time' => 0,
                        'end_time' => 0,
                        'limit_number' => 0,
                        'custom_content' => '',
                        'price' => 0,
                        'code' => '',
                        'code_count' => 0,
                        'zyb_code' => '',
                        'teacher_phone' => $o->teacher_phone ? $o->teacher_phone : '4008007221',
                        'city' => $o->order_city,
                        'goods_type' => 0,
                        'meeting_place' => $o->meeting_place,
                        'meeting_time' => $o->meeting_time,
                        'message_status' => SendMessage::MESSAGE_STATUS_USE_REMIND_REFUND,
                        'message_type' => SendMessage::MESSAGE_TYPE_ACTIVITY,
                    );

                    SendMessage::sendMessageToUser($data_message_param);

                    $data_message_type = 9;  // 消息类型为商品订单过期提醒
                    $data_inform_type = 16; // 活动订单消息推送

                    $data_date = date('Y-m-d', $o->start_time);
                    $data_meeting_time = date('Y-m-d H:i:s', $o->meeting_time);
                    $data_teacher_phone = $o->teacher_phone ? $o->teacher_phone : '4008007221';
                    // 商品订单到期推送内容
                    $data_inform = "【玩翻天】您参加的" . $data_date . "\"" . $o->coupon_name . "\"通知：早" . $data_meeting_time . "准时发车、提前10分钟签到，请在" . $o->meeting_place . "找遛娃师集合签到，遛娃师电话" . $data_teacher_phone . "";

                    // 商品订单到期系统消息
                    $data_title = "活动出行提醒";
                    $data_message = "您报名的活动即将开始了，请准时出行";

                    // 链接到的内容
                    $data_link_id = array(
                        'lid' => $o->order_sn,
                        'id' => $o->coupon_id,
                        'type' => 'kidsplay'
                    );

                    $data_info = $o->order_sn;

                    $class_sendMessage = new SendMessage();
                    $class_sendMessage->sendMes($o->user_id, $data_message_type, $data_title, $data_message, $data_link_id);
                    $class_sendMessage->sendInform($o->user_id, $o->token, $data_inform, $data_inform, $data_info, $data_inform_type, $v->id);

                    //SendMessage::Send11($o->phone, $o->coupon_name, $o->meeting_time, $o->meeting_place, $o->start_time, $this->getPhone());
                }
            }
            $this->_getPlayExcerciseEventTable()->update(array('send_meeting_msg' => new  Expression('send_meeting_msg+' . 2)), array('id' => $v->id));
        }

        $this->cashCouponTimeOut();

        exit('完成');
    }


    //每天微信内容同步到主站  每天 02点执行
    public function autoNewsAction()
    {

        error_reporting(-1);
        ini_set('display_errors', '1');


        $whWeiConfig = $this->_getConfig()['kaibanle_weixin'];
        $njWeiConfig = $this->_getConfig()['nj_weixin'];

        $configList = array(
            array(
                'appid' => $whWeiConfig['appid'],
                'secret' => $whWeiConfig['secret'],
                'type_name' => '武汉遛娃宝典',
            ),
            array(
                'appid' => $njWeiConfig['appid'],
                'secret' => $njWeiConfig['secret'],
                'type_name' => '南京遛娃宝典',
            ),
        );

        $weiXin = new WeiXinFun($this->getwxConfig());

        foreach ($configList as $conf) {
            $ACCESS_TOKEN = $weiXin->getAccessToken($conf['appid'], $conf['secret']);
            $result = $this->getNews($ACCESS_TOKEN, $conf['type_name']);
        }

        exit;

    }

    private function http_post_data($url, $data_string)
    {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json; charset=utf-8',
                'Content-Length: ' . strlen($data_string))
        );
        ob_start();
        curl_exec($ch);
        $return_content = ob_get_contents();
        ob_end_clean();

        $return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return array($return_code, $return_content);
    }

    private function getNews($ACCESS_TOKEN, $type_name)
    {
        $Url = 'https://api.weixin.qq.com/cgi-bin/material/batchget_material?access_token=' . $ACCESS_TOKEN;

        $postData = array(
            'type' => 'news',
            'offset' => 0,
            'count' => 1
        );

        $res = $this->http_post_data($Url, json_encode($postData));

        $result = json_decode($res[1], true)['item'][0]['content']['news_item'];

        if (!is_array($result) || !count($result)) {
            return false;
        }


        foreach ($result as $su) {

            $flag = $this->_getMdbWeiPost()->findOne(array('url' => $su['url']));
            if ($flag) {
                break;
            }

            $Url = 'https://api.weixin.qq.com/cgi-bin/material/get_material?access_token=' . $ACCESS_TOKEN;
            $postData = array(
                'media_id' => $su['thumb_media_id'],
            );
            $rest = $this->http_post_data($Url, json_encode($postData));

            if ($rest['0'] != 200) {
                continue;
            }

            $result = json_decode($rest['1'], true);

            if ($result) {
                continue;
            }

            $url = $this->_getConfig()['document_root'] . '/uploads/activity/www/' . date('Ym/d/');
            if (!is_dir($url)) {
                mkdir($url, 0777, true);
            }
            $ext = rand(10, 99) . time(); //唯一的一位数

            $file_up = file_put_contents($url . $ext . '.jpg', $rest[1]);

            if (!$file_up) {
                continue;
            }

            $data = array(
                'type_name' => $type_name,//玩翻天服务号
                'title' => $su['title'], //标题
                'thumb_url' => '/uploads/activity/www/' . date('Ym/d/') . $ext . '.jpg',
                'content_source_url' => $su['content_source_url'],
                'url' => $su['url'],
                'dateline' => time(),
                'status' => 0,
            );

            $this->_getMdbWeiPost()->insert($data);

        }

        return true;

    }

    // 酒店类商品，使用日期时发送短信提醒
    public function autoHotalUseRemindAction()
    {
        $data_start_time = strtotime(date("Y-m-d 0:0:0", time()));
        $data_end_time = strtotime(date("Y-m-d 23:59:59", time()));
        $adapter = $this->_getAdapter();

        $back = new OrderExcerciseBack();
        /********************* 超过时间,未达到完成条件的,全部退款 **************************/
        $data_sendmessage_phones = $adapter->query("
SELECT
	play_order_info.phone
FROM
	play_order_info
LEFT JOIN play_organizer_game ON play_organizer_game.id = play_order_info.coupon_id
LEFT JOIN play_game_info ON play_game_info.id = play_order_info.bid
LEFT JOIN play_coupon_code ON play_coupon_code.order_sn = play_order_info.order_sn
WHERE
	play_coupon_code.use_datetime is not null
AND play_organizer_game.is_hotal = 1
AND play_game_info.start_time > ?
AND play_game_info.start_time < ?
",
            array($data_start_time, $data_end_time)
        );

        if (empty($data_sendmessage_phones)) {
            return false;
        } else {
            foreach ($data_sendmessage_phones as $key => $val) {
                SendMessage::Send22($val->phone);
            }
        }
        exit(0);
    }

    private function cashCouponTimeOut()
    {
        $data_start_time = strtotime(date('Y-m-d 00:00:00'));
        $pm_start_time = $data_start_time + 3600 * 15; // 下午三点
        $pm_end_time = $data_start_time + 3600 * 22; // 晚上十点

        if (time() < $pm_start_time || time() > $pm_end_time) {
            return false;
        }

        $pdo = $this->_getAdapter();
        $sql = "SELECT
	play_cashcoupon_user_link.id,
	play_cashcoupon_user_link.cid,
	play_cash_coupon.title,
	play_user.uid,
	play_user.token,
	play_cash_coupon.time_type,
	play_cashcoupon_user_link.create_time,
	play_cash_coupon.use_stime,
	play_cash_coupon.use_etime,
	play_cash_coupon.after_hour
FROM
	play_cashcoupon_user_link
LEFT JOIN play_cash_coupon ON play_cash_coupon.id = play_cashcoupon_user_link.cid
LEFT JOIN play_user ON play_user.uid = play_cashcoupon_user_link.uid
WHERE
	play_cashcoupon_user_link.pay_time = 0
AND play_cashcoupon_user_link.is_back = 0
AND play_cashcoupon_user_link.send_past_due_message = 0
AND (
	play_cash_coupon.time_type = 1
	AND (
		(
			play_cash_coupon.after_hour >= 72
			AND play_cashcoupon_user_link.create_time + play_cash_coupon.after_hour * 3600 > UNIX_TIMESTAMP()
			AND play_cashcoupon_user_link.create_time + play_cash_coupon.after_hour * 3600 < UNIX_TIMESTAMP() + 86400 * 3
		)
		OR (
			play_cash_coupon.after_hour >= 24
			AND play_cash_coupon.after_hour < 72
			AND play_cashcoupon_user_link.create_time + play_cash_coupon.after_hour * 3600 > UNIX_TIMESTAMP()
			AND play_cashcoupon_user_link.create_time + play_cash_coupon.after_hour * 3600 < UNIX_TIMESTAMP() + 86400
		)
	)
)
OR (
	play_cash_coupon.time_type = 0
	AND (
		(
			play_cash_coupon.use_etime - play_cash_coupon.use_stime >= 3600 * 72
			AND play_cashcoupon_user_link.use_etime > UNIX_TIMESTAMP()
			AND play_cashcoupon_user_link.use_etime < UNIX_TIMESTAMP() + 86400 * 3
		)
		OR (
			play_cash_coupon.after_hour - play_cash_coupon.use_stime >= 24 * 3600
			AND play_cash_coupon.after_hour - play_cash_coupon.use_stime < 72 * 3600
			AND play_cashcoupon_user_link.use_etime > UNIX_TIMESTAMP()
			AND play_cashcoupon_user_link.use_etime < UNIX_TIMESTAMP() + 86400
		)
	)
)";

        $data_result = $pdo->query($sql, array());

        if ($data_result) {
            $data_inform_count = array();
            $data_inform_token = array();
            $data_inform_link_id = array();
            $data_inform_cashcoupon_id = array();
            $class_sendMessage = new SendMessage();
            foreach ($data_result as $key => $val) {
                $data_inform_count[$val->uid] = (int)$data_inform_count[$val->uid] + 1;
                $data_inform_token[$val->uid] = $val->token;
                $data_inform_link_id[$val->uid] = $val->id;
                $data_inform_cashcoupon_id[$val->uid] = $val->cid;

                $data_message_type = 14;  // 消息类型为商品订单过期提醒

                // 商品订单到期系统消息
                $data_title = "现金券到期提醒";
                $data_message = "您持有的现金券\"" . $val->title . "\"即将到期，请尽快使用";

                // 链接到的内容
                $data_link_id = array(
                    'id' => $val->id,
                    'cid' => $val->cid,
                    'type' => 'cash_coupon'
                );

                $class_sendMessage->sendMes($val->uid, $data_message_type, $data_title, $data_message, $data_link_id);

                $pdo->query(" UPDATE play_cashcoupon_user_link SET send_past_due_message = 1 WHERE id = ? AND uid = ? ", array($val->id, $val->uid));
            }

            foreach ($data_inform_count as $key => $val) {
                $data_inform_type = 14;  // 现金券消息推送
                $data_cashcoupon_count = $val;

                // 现金券到期推送内容
                $data_inform = "【玩翻天】您持有的{$data_cashcoupon_count}张现金券即将到期啦，快去使用吧！";

                // 推送消息链接的内容
                $data_info = $data_inform_link_id[$key];

                $class_sendMessage->sendInform($key, $data_inform_token[$key], $data_inform, $data_inform, $data_info, $data_inform_type, $data_inform_cashcoupon_id[$key]);
            }
        }

        return true;
    }
}
