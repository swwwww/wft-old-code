<?php

namespace library\Service\Order;

use Deyi\Alipay\Alipay;
use Deyi\Unionpay\Unionpay;
use Deyi\WeiSdkPay\WeiPay;
use Deyi\WeiXinPay\WeiXinPayFun;
use library\Fun\M;
use library\Service\ServiceManager;
use library\Service\System\Logger;

class OrderInfo {

    static public function getExcerciseCodeInfoByOrderSn($order_sn) {
        return M::getPlayExcerciseCodeTable()->fetchAll(array(
            'order_sn' => $order_sn
        ))->toArray();
    }

    //查询第三方订单信息
    public static function CheckThirdPartyOrderInfo($order_sn)
    {

        $order_sn = intval($order_sn);
        $orderInfo = M::getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));

        if (!$order_sn || !$orderInfo || !in_array($orderInfo->account_type, array('weixin', 'union', 'new_jsapi', 'alipay'))) {
            return array('status' => 0, 'message' => '订单不存在 或者 订单类型不正确');
        }

        $accountType = $orderInfo->account_type;

        if ($accountType == 'alipay') {
            $ali = new Alipay();
            $result = $ali->getOrderInfo($order_sn);

            if (!$result['status']) {
                return $result;
            }

            $result = $result['message'];

        } elseif($accountType == 'union') {//只能查询当天的
            $union = new Unionpay();
            $result = $union->getOrderInfo($order_sn);

            if ($result['respCode'] != '00' || $result['respMsg'] != '成功[0000000]') {
                return array('status' => 0, 'message' => '提示 code '. $result['respCode']. '_'. 'mes'. $result['respMsg']);
            }

        } elseif ($accountType == 'weixin') {
            $weiPay = new WeiPay();
            $result = $weiPay->getOrderInfo($order_sn);

            if ($result['return_code'] !== 'SUCCESS' || !in_array($result['trade_state'], array('SUCCESS', 'REFUND'))) {
                return array('status' => 0, 'message' => '提示 code '. $result['return_code']. '_'. 'mes'. $result['trade_state']);
            }

        } else {
            $weiWapPay = new WeiXinPayFun(ServiceManager::getConfig('wanfantian_weixin'));
            if ($orderInfo->dateline > 1479897600) { //2016年11月23号 上线  前面给第三方的没有拼接WFT
                $out_trade_no = 'WFT'. $order_sn;
            } else {
                $out_trade_no = $order_sn;
            }
            $result = $weiWapPay->getOrderInfo($out_trade_no);

            if ($result['return_code'] !== 'SUCCESS' || !in_array($result['trade_state'], array('SUCCESS', 'REFUND'))) {
                return array('status' => 0, 'message' => '提示 code '. $result['return_code']. '_'. 'mes'. $result['trade_state']);
            }

        }

        return array('status' => 1, 'message' => $result);

    }

    /**
     * 对比后台 与 第三方 前一天的订单 充值 及 退款
     * @param $contrasts
     * @return array
     */
    public static function checkThirdPartyAccount($contrasts)
    {
        $type = $contrasts['type'];
        $pay = $contrasts['pay'];
        $recharge = $contrasts['recharge'];
        $refund = $contrasts['refund'];
        $message = '';

        if ($type == 'alipay') {

            $ali = new Alipay();
            $res = $ali->getBalanceList();

            if ($res['is_success'] === 'T' && isset($res['response']['account_page_query_result']['account_log_list']['AccountQueryAccountLogVO'])) {
                $result = $res['response']['account_page_query_result']['account_log_list']['AccountQueryAccountLogVO'];
                foreach($result as $value) {
                    if ($value['trans_code_msg'] == '在线支付') {
                        if (strpos($value['merchant_out_order_no'], 'WFTREC') !== false) {
                            if (!array_key_exists($value['merchant_out_order_no'], $recharge) || $value['income'] != $recharge[$value['merchant_out_order_no']]) {
                                $message = $message. '支付宝充值订单'. $value['merchant_out_order_no']. ' 金额：'. $value['income']. '后台不存在'. "\r\n";
                            } else {
                                self::orderApproval($value['merchant_out_order_no'], 'recharge');
                                unset($recharge[$value['merchant_out_order_no']]);
                            }

                        } elseif (strpos($value['merchant_out_order_no'], 'WFT') !== false) {
                            if (!array_key_exists($value['merchant_out_order_no'], $pay) || $value['income'] != $pay[$value['merchant_out_order_no']]) {
                                $message = $message. '支付宝购买订单'. $value['merchant_out_order_no']. ' 金额：'. $value['income']. '后台不存在'. "\r\n";
                            } else {
                                self::orderApproval($value['merchant_out_order_no'], 'buy');
                                unset($pay[$value['merchant_out_order_no']]);
                            }
                        }
                    }

                    if ($value['trans_code_msg'] == '转账' &&  $value['sub_trans_code_msg'] == '交易退款' && $value['memo'] == '协商退款') {
                        $flow_sn = $value['merchant_out_order_no'];
                        $flow_money = $value['outcome'];
                        if (array_key_exists($flow_sn, $refund)) {
                            if ($refund[$flow_sn] > $flow_money) {
                                $refund[$flow_sn] = $refund[$flow_sn] - $flow_money;
                            } elseif ($refund[$flow_sn] == $flow_money)  {
                                unset($refund[$flow_sn]);
                            } else {
                                $message = $message. '支付宝退款'. $flow_sn. ' 金额：'. $flow_money. '后台不存在'. "\r\n";
                            }
                        } else {
                            $message = $message. '支付宝退款'. $flow_sn. ' 金额：'. $flow_money. '后台不存在'. "\r\n";
                        }
                    }
                }

                if (count($pay) >= 1) {
                    $message = $message. '后台有购买订单 但是支付宝没 : '. print_r($pay, true). "\r\n";
                }

                if (count($recharge) >= 1) {
                    $message = $message. '后台有充值 但是支付宝没 : '. print_r($recharge, true). "\r\n";
                }

                if (count($refund) >= 1) {
                    $message = $message. '后台有退款 但是支付宝没 : '. print_r($refund, true). "\r\n";
                }

                if ($message) {
                    return array('status' => 0, 'message' => $message);
                } else {
                    return array('status' => 1, 'message' => '支付宝对上了');
                }
            }

            return array('status' => 0, 'message' => '支付宝未获取到数据');
        }

        if ($type == 'union') {
            $union = new Unionpay();
            $result = $union->getBalanceList();

            if ($result['respCode'] == '00' && $result['respMsg'] == '交易成功') {
                $string = $result['fileContent'];
                $base64String = base64_decode($string);
                $deflatedString = substr($base64String, 2, -4);
                $zipFile= gzinflate($deflatedString);
                $file_name = '/tmp/1'. date('y-m-d', time()). $result['fileName'];
                $fileResult = file_put_contents($file_name, $zipFile);
                if (!$fileResult) {
                    return array('status' => 0, 'message' => '银联对账 创建文件失败');
                }

                $zip = zip_open($file_name);

                if ($zip) {
                    while ($zip_entry = zip_read($zip)) {
                        if (zip_entry_open($zip, $zip_entry, "r")) {
                            $buf = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));

                            if ($buf && strpos($buf, 'WFT') !== false) {
                                $moneyArray = explode("\r\n", $buf);
                                foreach ($moneyArray as $money) {
                                    $realData = preg_split("/ {1,}/",$money);
                                    if (isset($realData[6])) {
                                        $flow_money = bcdiv(intval($realData[6]), 100);
                                        if (isset($realData[11]) && strpos($realData[11], 'WFTREC') !== false) {
                                            if (!array_key_exists($realData[11], $recharge) || $flow_money != $recharge[$realData[11]]) {
                                                $message = $message. '银联充值订单'. $realData[11]. ' 金额：'. $flow_money. '后台不存在'. "\r\n";
                                            } else {
                                                self::orderApproval($realData[11], 'recharge');
                                                unset($recharge[$realData[11]]);
                                            }
                                        } elseif (isset($realData[11]) && strpos($realData[11], 'WFT') !== false) {
                                            if (!array_key_exists($realData[11], $pay) || $flow_money != $pay[$realData[11]]) {
                                                $message = $message. '银联购买订单'. $realData[11]. ' 金额：'. $flow_money. '后台不存在'. "\r\n";
                                            } else {
                                                self::orderApproval($realData[11], 'buy');
                                                unset($pay[$realData[11]]);
                                            }
                                        } elseif (isset($realData[30]) && strpos($realData[30], 'WFT') !== false) {
                                            if (array_key_exists($realData[30], $refund)) {
                                                if ($refund[$realData[30]] > $flow_money) {
                                                    $refund[$realData[30]] = $refund[$realData[30]] - $flow_money;
                                                } elseif ($refund[$realData[30]] == $flow_money)  {
                                                    unset($refund[$realData[30]]);
                                                } else {
                                                    $message = $message. '银联退款'. $realData[30]. ' 金额：'. $flow_money. '后台不存在'. "\r\n";
                                                }
                                            } else {
                                                $message = $message. '银联退款'. $realData[30]. ' 金额：'. $flow_money. '后台不存在'. "\r\n";
                                            }
                                        }
                                    }
                                }

                            }
                            zip_entry_close($zip_entry);
                        }
                    }
                    zip_close($zip);
                }

                unlink($file_name);

                if (count($pay) >= 1) {
                    $message = $message. '后台有购买订单 但是银联没 : '. print_r($pay, true). "\r\n";
                }

                if (count($recharge) >= 1) {
                    $message = $message. '后台有充值 但是银联没 : '. print_r($recharge, true). "\r\n";
                }

                if (count($refund) >= 1) {
                    $message = $message. '后台有退款 但是银联没 : '. print_r($refund, true). "\r\n";
                }

                if ($message) {
                    return array('status' => 0, 'message' => $message);
                } else {
                    return array('status' => 1, 'message' => '银联对上了');
                }

            } else {
                return array('status' => 0, 'message' => '银联对账 获取数据失败2');
            }

        }

        if ($type == 'weiPay') {
            $weiPay = new WeiPay();
            $res = $weiPay->getBalanceList();

            if (strpos($res, '<xml>') === 0) {
                return array('status' => 0, 'message' => '微信获取数据失败：'. $res);
            }

            $moneyArray = explode("\r\n", $res);

            foreach ($moneyArray as $val) {

                $money = explode(',', $val);
                if (isset($money[6]) && isset($money[9]) && isset($money[12]) && isset($money[16]) && isset($money[21])) {
                    if ($money[9] == '`SUCCESS' && $money[21] == '`recharge') {
                        $flow_money = trim($money[12], '`');
                        $flow_sn = trim($money[6], '`');
                        if (!array_key_exists($flow_sn, $recharge) || $flow_money != $recharge[$flow_sn]) {
                            $message = $message. '微信充值订单'.$flow_sn. ' 金额：'. $flow_money. '后台不存在'. "\r\n";
                        } else {
                            self::orderApproval($flow_sn, 'recharge');
                            unset($recharge[$flow_sn]);
                        }
                    } elseif ($money[9] == '`SUCCESS' && $money[21] != '`recharge') {
                        $flow_money = trim($money[12], '`');
                        $flow_sn = trim($money[6], '`');
                        if (!array_key_exists($flow_sn, $pay) || $flow_money != $pay[$flow_sn]) {
                            $message = $message. '微信购买订单'.$flow_sn. ' 金额：'. $flow_money. '后台不存在'. "\r\n";
                        } else {
                            self::orderApproval($flow_sn, 'buy');
                            unset($pay[$flow_sn]);
                        }
                    } elseif ($money[9] == '`REFUND') {
                        $flow_money = trim($money[16], '`');
                        $flow_sn = trim($money[6], '`');
                        if (array_key_exists($flow_sn, $refund)) {
                            if ($refund[$flow_sn] > $flow_money) {
                                $refund[$flow_sn] = $refund[$flow_sn] - $flow_money;
                            } elseif ($refund[$flow_sn] == $flow_money)  {
                                unset($refund[$flow_sn]);
                            } else {
                                $message = $message. '微信退款'. $flow_sn. ' 金额：'. $flow_money. '后台不存在'. "\r\n";
                            }
                        } else {
                            $message = $message. '微信退款'. $flow_sn. ' 金额：'. $flow_money. '后台不存在'. "\r\n";
                        }
                    }
                }
            }

            if (count($pay) >= 1) {
                $message = $message. '后台有购买订单 但是微信没 : '. print_r($pay, true). "\r\n";
            }

            if (count($recharge) >= 1) {
                $message = $message. '后台有充值 但是微信没 : '. print_r($recharge, true). "\r\n";
            }

            if (count($refund) >= 1) {
                $message = $message. '后台有退款 但是微信没 : '. print_r($refund, true). "\r\n";
            }

            if ($message) {
                return array('status' => 0, 'message' => $message);
            } else {
                return array('status' => 1, 'message' => '微信对上了');
            }
        }

        if ($type == 'weiWapPay') {
            $weiPay = new WeiXinPayFun(ServiceManager::getConfig('wanfantian_weixin'));
            $res = $weiPay->getBalanceList();

            if (strpos($res, '<xml>') === 0) {
                return array('status' => 0, 'message' => '微信网页获取数据失败：'. $res);
            }

            $moneyArray = explode("\r\n", $res);

            foreach ($moneyArray as $val) {

                $money = explode(',', $val);
                if (isset($money[6]) && isset($money[9]) && isset($money[12]) && isset($money[16]) && isset($money[21])) {
                    if ($money[9] == '`SUCCESS' && $money[21] == '`recharge') {
                        $flow_money = trim($money[12], '`');
                        $flow_sn = trim($money[6], '`');
                        if (!array_key_exists($flow_sn, $recharge) || $flow_money != $recharge[$flow_sn]) {
                            $message = $message. '微信网页充值订单'.$flow_sn. ' 金额：'. $flow_money. '后台不存在'. "\r\n";
                        } else {
                            self::orderApproval($flow_sn, 'recharge');
                            unset($recharge[$flow_sn]);
                        }
                    } elseif ($money[9] == '`SUCCESS' && $money[21] != '`recharge') {
                        $flow_money = trim($money[12], '`');
                        $flow_sn = trim($money[6], '`');
                        if (!array_key_exists($flow_sn, $pay) || $flow_money != $pay[$flow_sn]) {
                            $message = $message. '微信网页购买订单'.$flow_sn. ' 金额：'. $flow_money. '后台不存在'. "\r\n";
                        } else {
                            self::orderApproval($flow_sn, 'buy');
                            unset($pay[$flow_sn]);
                        }
                    } elseif ($money[9] == '`REFUND') {
                        $flow_money = trim($money[16], '`');
                        $flow_sn = preg_replace('/\D/s', '', $money[6]);
                        if (array_key_exists($flow_sn, $refund)) {
                            if ($refund[$flow_sn] > $flow_money) {
                                $refund[$flow_sn] = $refund[$flow_sn] - $flow_money;
                            } elseif ($refund[$flow_sn] == $flow_money)  {
                                unset($refund[$flow_sn]);
                            } else {
                                $message = $message. '微信网页退款'. $flow_sn. ' 金额：'. $flow_money. '后台不存在'. "\r\n";
                            }
                        } else {
                            $message = $message. '微信网页退款'. $flow_sn. ' 金额：'. $flow_money. '后台不存在'. "\r\n";
                        }
                    }
                }
            }

            if (count($pay) >= 1) {
                $message = $message. '后台有购买订单 但是微信网页没 : '. print_r($pay, true). "\r\n";
            }

            if (count($recharge) >= 1) {
                $message = $message. '后台有充值 但是微信网页没 : '. print_r($recharge, true). "\r\n";
            }

            if (count($refund) >= 1) {
                $message = $message. '后台有退款 但是微信网页没 : '. print_r($refund, true). "\r\n";
            }

            if ($message) {
                return array('status' => 0, 'message' => $message);
            } else {
                return array('status' => 1, 'message' => '微信网页对上了');
            }

        }

        return array('status' => 0, 'message' => '类型不正确');



    }

    //审批到账
    private static function orderApproval($order_sn, $type)
    {

        $order_id = intval(preg_replace('/\D/s', '', $order_sn));
        $timer = time();

        if ($type == 'buy') {
            $orderData = M::getPlayOrderInfoTable()->get(array('order_sn' => $order_id, 'pay_status >= ?' => 2, 'order_status' => 1));
            if (!$orderData) {
                Logger::WriteErrorLog('审批到账 购买订单信息查询不到 订单是: '. $order_sn. "\r\n");
                return false;
            }

            $PDO = M::getAdapter();
            $conn = $PDO->getDriver()->getConnection();
            $conn->beginTransaction();

            if ($orderData->approve_status == 2) {
                $conn->rollBack();
                Logger::WriteErrorLog('审批到账 订单是已审批 失败 订单是: '. $order_sn. "\r\n");
                return false;
            }

            if ($orderData->order_type == 2) {
                $m1 = $PDO->query("UPDATE play_coupon_code, play_order_info SET play_coupon_code.check_status = 2 WHERE play_order_info.order_sn = play_coupon_code.order_sn  AND play_order_info.order_sn={$order_id} AND play_order_info.order_type = 2", array())->count();
                if (!$m1) {
                    $conn->rollBack();
                    Logger::WriteErrorLog('审批到账 更新play_coupon_code 失败 订单是: '. $order_sn. "\r\n");
                    return false;
                }
            }

            $m2 = $PDO->query("UPDATE play_order_info SET play_order_info.approve_status = 2 WHERE play_order_info.order_sn={$order_id}", array())->count();
            if (!$m2) {
                $conn->rollBack();
                Logger::WriteErrorLog('审批到账 更新订单审批到账状态 失败 订单是: '. $order_sn. "\r\n");
                return false;
            }

            $insert_sql = "INSERT play_order_action (`action_user`, `order_id`, `play_status`, `action_note`, `dateline`, `action_user_name`) VALUES ";
            $action_note = '订单'. $order_id.' 审批到账';
            $action_user_name = '管理员 系统自动审批';
            $insert_sql = $insert_sql . "(0, {$order_id}, 6, '{$action_note}', {$timer}, '{$action_user_name}')";

            $insert = $PDO->query($insert_sql, array())->count();
            if (!$insert) {
                $conn->rollBack();
                Logger::WriteErrorLog('审批到账 写入记录 失败 订单是: '. $order_sn. "\r\n");
                return false;
            }

            $conn->commit();

            return true;
        }

        if ($type == 'recharge') {

            $accountLog = M::getPlayAccountLogTable()->get(array('id' => $order_id));

            if (!$accountLog) {
                Logger::WriteErrorLog('审批到账 充值 购买订单信息查询不到 订单是: '. $order_sn. "\r\n");
                return false;
            }

            $PDO = M::getAdapter();
            $conn = $PDO->getDriver()->getConnection();
            $conn->beginTransaction();

            if ($accountLog->check_status == 1) {
                $conn->rollBack();
                Logger::WriteErrorLog('审批到账 充值 更新订单审批到账状态 是已审批 订单是: '. $order_sn. "\r\n");
                return false;
            }

            $s1 = $PDO->query("UPDATE play_account_log SET check_status = 1 WHERE id={$order_id}", array())->count();
            if (!$s1) {
                $conn->rollBack();
                Logger::WriteErrorLog('审批到账 充值 更新订单审批到账状态 失败 订单是: '. $order_sn. "\r\n");
                return false;
            }

            $conn->commit();

            return true;
        }

        return false;

    }
}