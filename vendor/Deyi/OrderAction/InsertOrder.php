<?php

/**
 * 生成订单
 */
namespace Deyi\OrderAction;


use Deyi\BaseController;
use Application\Module;
use Deyi\Invite\Invite;


class InsertOrder
{
    use BaseController;


    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }


    /**
     * @param $countNumber //总数
     * @param $buyNumber //购买数
     * @param $uid //uid
     * @param $couponId //卡券id 或 活动id
     * @param $gameInfoId //gameinfo表id  购买套系
     * @param $userName //联系人名称
     * @param $userPhone //联系人手机号
     * @param $buy_address //联系人地址
     * @param $associates_ids //出行人id
     * @param $unitPrice //单价
     * @param $couponTitle //卡券名称
     * @param $shopId //商家id (总商家)
     * @param $shopName //商家名称
     * @param $orderType //卡券类型
     * @param array $gameInfo //info表数据
     * @param array $organizerGame //organizergame表数据
     * @param string $buyName //购买联系人名字
     * @param string $buyPhone //购买联系人电话号码
     * @param int $group_buy //是否组团 不组团为0
     * @param array $gameData //game表数据
     * @param $city //城市
     * @return array
     */
    public function insertOrder($countNumber, $buyNumber, $uid, $couponId, $gameInfoId, $userName,
                                $userPhone, $buy_address, $associates_ids, $unitPrice, $couponTitle, $shopId,
                                $shopName, $orderType, $gameInfo = array(), $organizerGame = array(),
                                $buyName = '', $buyPhone = '', $group_buy = 0, $group_buy_id = 0, $client_id,
                                $cash_coupon_id, $qualify_coupon_id, $use_score, $message = '', $use_account_money = 0, $city = 'WH'

    )
    {


        //todo 只负责插入订单,现金券使用,积分使用,独立及支付后处理

        $time = time();
        $real_pay = bcmul($buyNumber, $unitPrice, 2);  //银行卡需要支付的金额
        $user_money = 0;//用户账户需要支付的金额
        $cash_money = 0; //现金券金额
        $account_type = 'nopay';
        $pay_status = 0;


        if ($orderType == 1) {
            return array('status' => 0, 'message' => '请更新版本！');
        }

        if ($group_buy == 1) {//组团
            $buyNumber = $organizerGame->g_limit;
        } elseif ($group_buy == 2) {//加入团
            $buyNumber = 0;
        }


        //剩余数  减去去我买的,允许的最小剩余数
        $surplu = $countNumber - $buyNumber;

        $adapter = $this->_getAdapter();

        /***************** 判断总金额,需要支付的金额 ****************/
        $m = 0;//用户需要支付的金额,包含代金券和现金

        //判断现金券否正确
        if ($cash_coupon_id) {

            $info_sql = "select id,excepted from play_game_info WHERE id = ?;";
            $info     = $adapter->query($info_sql, array($gameInfoId))->current();
            if($info and $info->excepted){
                return array('status' => 0, 'message' => '抱歉，购买特例商品不可使用现金券!');
            }

            //传递过来 已判断
            $cash_data = $adapter->query("select * from play_cashcoupon_user_link WHERE id=? AND pay_time=0 AND use_stime<?  AND use_etime>? AND  uid=?", array($cash_coupon_id, $time, $time, $uid))->current();
            if (!$cash_data) {
                $this->errorLog("现金券状态异常或已使用  id:{$cash_coupon_id} uid={$uid}");
                return array('status' => 0, 'message' => '现金券状态异常或已使用!');
            }
            if ($cash_data->price > $real_pay) {
                return array('status' => 0, 'message' => '现金券金额大于支付金额!');
                // $cash_data->price = $real_pay;
            }

            $cash_money = $cash_data->price;
            $real_pay = bcsub($real_pay, $cash_data->price, 2); //银行卡需要支付的金额
        }


//
//        if ($use_account_money) {
//            $account_data = $adapter->query("select * from play_account where uid=?", array($uid))->current();
//            if ($account_data) {
//                if ($account_data->now_money >= $real_pay) {
//                    $user_money = $real_pay;
//                    $real_pay = 0;
//                } else {
//                    //账户余额必须足够.去掉此行代码就可以先扣除账户余额在使用第三方支付
//                    return array('status' => 0, 'message' => '账户余额不足！');
//
//                    //先扣剩余的全部金额
//                    $user_money = $account_data->now_money;
//                    $real_pay = bcsub($real_pay, $user_money);
//                }
//            } else {
//                return array('status' => 0, 'message' => '账户余额不足！');
//            }
//        }


        /***************** 判断总金额,需要支付的金额 ****************/


        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();


        if ($group_buy == 1) {//初始化组团数据
            $end_time = $time + 3600 * 2; //2小时后;

            $group_join_number = 0;//支付后加1
            $g1 = $adapter->query("INSERT INTO `play_group_buy` VALUES (NULL , ?,?,?, ?, ?, ?, ?,1);", array($time, $uid, $group_join_number, $organizerGame->g_limit, $end_time, $organizerGame->id, $gameInfoId));
            if ($g1->count()) {
                $group_buy_id = $adapter->getDriver()->getLastGeneratedValue();
            } else {
                $conn->rollback();
                return array('status' => 0, 'message' => '订单生成失败,团购数据异常-1！');
            }
        } elseif ($group_buy == 2) {//加入团
            $group_data = $adapter->query("select * from play_group_buy WHERE id=?", array($group_buy_id))->current();
            if (!$group_data) {
                $conn->rollback();
                return array('status' => 0, 'message' => '团购数据异常！');
            }
            if ($group_data->join_number == $group_data->limit_number) {
                $conn->rollback();
                return array('status' => 0, 'message' => '这个团已经满员了哦！');
            }
        }

        //如果是组团,提前减去需要的数量
        $s1 = $adapter->query('UPDATE play_game_info SET buy=buy+? WHERE id=? AND buy<=?', array($buyNumber, $gameInfoId, $surplu));

        if ($s1->count() or $buyNumber == 0) {

            if ($group_buy) {  //订单内显示一个
                $buyNumber = 1;
            }

            //订单记录
            $s2 = $adapter->query("
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
	bid
)
VALUES	(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", array(
                $couponId,
                1,
                $pay_status,
                $uid,
                $userName,
                $userPhone,
                $real_pay,  //银行卡需要支付的金额
                $user_money,  //用户账户需要支付的金额
                $cash_money,//现金券金额
                $cash_coupon_id,
                $unitPrice,
                $couponTitle,
                $shopName,
                $shopId,
                $buyNumber,
                0,
                0,
                '',
                $account_type,
                $buyName,
                $buyPhone,
                time(),
                0,
                $city,
                $orderType,
                $group_buy_id,
                $buy_address,
                $gameInfoId
            ));
            $order_sn = $adapter->getDriver()->getLastGeneratedValue();
            if ($s2->count() and $order_sn) {
                $s3 = $adapter->query('INSERT INTO play_order_action (order_id,play_status,action_user,action_note,dateline,action_user_name) VALUES (?,?,?,?,?,?)', array(
                    $order_sn,
                    0,
                    $uid,
                    '下单成功',
                    time(),
                    '用户' . $userName
                ))->count();

                //主表需要统计的数量
                if ($group_buy == 1) {
                    $n = $organizerGame->g_limit;
                } elseif ($group_buy == 2) {
                    $n = 0;
                } else {
                    $n = $buyNumber;
                }
                if ($n) {
                    $s4 = $adapter->query('UPDATE play_organizer_game SET buy_num=buy_num+? WHERE id=?', array($n, $couponId))->count();
                } else {
                    $s4 = true;
                }

                // 生成卡券密码
                $orderCodes = '';
                for ($i = 1; $i <= $buyNumber; $i++) {
                    $code = sprintf("%03d", $i) . mt_rand(1000, 9999);
                    $orderCodes .= "({$order_sn},  {$i},   0,'{$code}' , 0,  0),";
                }
                $orderCodes = substr($orderCodes, 0, -1);

                $stmt = $adapter->query('INSERT INTO play_coupon_code (order_sn,sort,status,password,use_store,use_datetime) VALUES ' . $orderCodes);
                $s5 = $stmt->execute($stmt)->count();
                //add by wzxiang 2016.4.13 如果订单有保险，play_order_insure要添加数据
              
                if ($gameInfo->insure_num_per_order) { //是否需要购买保险
                    $insure_data = '';
                    for ($i = 0; $i < count($associates_ids); $i++) {
                        $associates_info = $adapter->query("select * from play_user_associates WHERE associates_id=?", array($associates_ids[$i]))->current();
                        $insure_data .= "('{$order_sn}','{$couponId}','{$associates_info->name}',
                            '{$associates_info->sex}','{$associates_info->birth}','{$associates_info->id_num}',1,'','','1',{$associates_ids[$i]},'{$gameInfo->insure_days}'),";
                    }

                    if (count($associates_ids) < $buyNumber * ($gameInfo->insure_num_per_order)) {

                        for ($i = 0; $i < $buyNumber * ($gameInfo->insure_num_per_order) - count($associates_ids); ++$i) {
                            $insure_data .= "('{$order_sn}','{$couponId}','','','','',1,'','','0',0,'{$gameInfo->insure_days}'),";
                        }
                    }
                    $insure_data = substr($insure_data, 0, -1);
                    if (!empty($insure_data)) {
                        $stmt = $adapter->query('INSERT INTO play_order_insure (order_sn,coupon_id,`name`,sex,birth,id_num,insure_company_id,insure_sn,baoyou_sn,insure_status,associates_id,product_code) VALUES ' . $insure_data);
                        $stmt->execute($stmt)->count();
                    }
                }
                $s6 = $adapter->query('INSERT INTO play_order_info_game (order_sn, type_name, start_time, end_time, address, time_id, price_id, thumb, game_info_id,client_id) VALUES (?,?,?,?,?,?,?,?,?,?)', array(
                    $order_sn,
                    $gameInfo->price_name,
                    $gameInfo->start_time,
                    $gameInfo->end_time,
                    $gameInfo->shop_name,
                    $gameInfo->tid,
                    $gameInfo->pid,
                    $organizerGame->thumb,
                    $gameInfo->id,
                    $client_id
                ))->count();

                $s7 = $adapter->query("INSERT INTO play_order_otherdata (order_sn, message,comment) VALUES (?,?,?)", array($order_sn, $message, 0));
                if (!$s7) {
                    $conn->rollback();
                    return ['status' => 0, 'message' => "插入订单数据失败"];
                }


                //使用资格券,购买之前就时使用
                if(strpos($_SERVER['HTTP_USER_AGENT'], 'client/3.3.') or strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')){
                    if ($qualify_coupon_id and $gameInfo->qualified == 2) {
                        $use_aualify = $adapter->query("update  play_qualify_coupon set pay_time=?,use_order_id=?,pay_object_id=?, pay_object_name = ? WHERE uid=? AND  id=?", array($time, $order_sn, $couponId, $couponTitle, $uid, $qualify_coupon_id))->count();
                        if (!$use_aualify) {
                            $conn->rollback();
                            return ['status' => 0, 'message' => "资格券使用失败,已使用或已过期"];
                        }
                    }
                }
                //使用现金券
                if ($cash_coupon_id) {

                    $s8 = $adapter->query("UPDATE  play_cashcoupon_user_link SET pay_time=?,use_order_id=?,use_object_id=?,use_type=? WHERE id=? AND pay_time=0 AND use_stime<?  AND use_etime>? AND uid=?",
                        array($time, $order_sn, $couponId, 1,$cash_coupon_id, $time, $time, $uid))->count();

                    if (!$s8) {
                        $conn->rollback();
                        return ['status' => 0, 'message' => "现金券使用失败,已使用或已过期"];
                    }
                }


                //使用账户金额,验证密码
//                if ($user_money > 0) {
// $account_data = $adapter->query("select * from play_account where uid=?", array($uid))->current();
//                    if (!$account_data or $account_data->status == 0) {
//                        return ['status' => 0, 'message' => "用户账户已冻结"];
//                    }
//                    if (md5(md5($pwd) . $account_data->salt) !== $account_data->password) {
//                        return ['status' => -1, 'message' => "账户密码错误"];
//                    }
//                    //用哪个账户的钱,优先不可提现账户
//                    if (($account_data->now_money - $user_money) < $account_data->can_back_money) {
//                        $s9 = $adapter->query("UPDATE play_account SET now_money=now_money-{$user_money},can_back_money=now_money,last_time=? WHERE uid=? AND now_money>={$user_money}", array(time(), $uid))->count();
//                    } else {
//                        $s9 = $adapter->query("UPDATE play_account SET now_money=now_money-{$user_money},last_time=? WHERE uid=? AND now_money>={$user_money}", array(time(), $uid))->count();
//                    }
//
//                    $s10 = $adapter->query("INSERT INTO play_account_log (id,uid,action_type,action_type_id,object_id,flow_money,surplus_money,dateline,description,status) VALUES (NULL ,?,?,?,?,?,?,?,?,?)",
//                        array($uid, 2, 1, $order_sn, $user_money, bcsub($account_data->now_money, $user_money, 2), time(), '购买商品', 1))->count();
//
//                    if (!$s9 or !$s10) {
//                        $conn->rollback();
//                        return ['status' => 0, 'message' => "支付失败,账户金额不足或账户异常"];
//                    }
//                }


                //使用积分
                if ($use_score && $gameInfo->integral) {

                    $s8 = $adapter->query("UPDATE  play_integral_user SET total=total-? WHERE uid=? AND total>=?", array($gameInfo->integral, $uid, $gameInfo->integral))->count();
                    if (!$s8) {
                        $conn->rollback();
                        return ['status' => 0, 'message' => "积分不足"];
                    }

                    //积分记录表添加记录
                    $s9 = $adapter->query("INSERT INTO play_integral (id,uid,`type`,total_score,base_score,award_score,object_id,create_time,city,`desc` )
 VALUES (NULL,?,?,?,?,?,?,?,?,?)",
                        array(
                            $uid,
                            102,
                            (int)($gameInfo->integral),
                            (int)($gameInfo->integral),
                            1,
                            $order_sn,
                            time(),
                            $city,
                            '购买' . ($gameInfo->coupon_name ?: '商品') . '消耗积分'
                        ))->count();

                    if (!$s9) {
                        $conn->rollback();
                        return false;
                    }

                }

                if ($s3 and $s4 and $s5 and $s6) {

                    $conn->commit();

                    if ($use_account_money) {//使用账户余额支付
                        $order_info = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));
                        $order_info_game = $this->_getPlayOrderInfoGameTable()->get(array('order_sn' => $order_sn));
                        $order_pay = new OrderPay();
                        $pay_status = $order_pay->paySuccess($order_info, $order_info_game, '', 'account', $uid);

                        if ($pay_status) {
                            //判断是否有分享红包奖励
                            $invite = new Invite();
                            $middleware = $invite->middleware($order_info,1);
                            return ['status' => 2, 'order_sn' => $order_sn, 'group_buy_id' => $group_buy_id, 'middleware'=>$middleware];
                        } else {
                            //订单生成成功,支付过程失败
                            return ['status' => 0, 'message' => "账户支付失败"];
                        }

                    } else {
                        //判断是否有分享红包奖励
                        $order_info = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));
                        $invite = new Invite();
                        $middleware = $invite->middleware($order_info,1);
                        return ['status' => 1, 'order_sn' => $order_sn, 'group_buy_id' => $group_buy_id,'middleware' => $middleware];
                    }

                } else {
                    $conn->rollback();
                    return ['status' => 0, 'message' => "订单生成失败"];
                }
            } else {
                $conn->rollback();
                return array('status' => 0, 'message' => '生成订单失败！');
            }

        } else {
            $conn->rollback();
            return array('status' => 0, 'message' => '数量不够了！');
        }

    }


}