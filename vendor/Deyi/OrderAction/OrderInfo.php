<?php
/**
 * Created by PhpStorm.
 * User: dddee
 * Date: 2016/1/9
 * Time: 10:41
 */
namespace Deyi\OrderAction;

use Deyi\BaseController;
use Deyi\Paginator;
use Deyi\WeiSdkPay\WeiPay;
use Application\Module;
use Deyi\WeiXinPay\WeiXinPayFun;
use library\Fun\M;
use library\Service\System\Cache\RedCache;

class OrderInfo
{
    use BaseController;

    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }


    /**
     *
     * 获取订单状态 核心方法,勿改动  wwjie
     * @param $pay_status  支付状态
     * @param $buy_number  购买数
     * @param $backing_number 退款中数量
     * @param $back_number 退款成功数量
     * @param $use_number 使用成功数量
     * @return array
     */
    public static function getOrderStatus($pay_status, $buy_number, $backing_number, $back_number, $use_number)
    {
        /*
    0未付款;
    1付款中;
    2待使用(>2已付款)
    3退款中
    4退款成功
    5已使用
    6已过期
    7团购中
        */
        $s = array('status' => 0, 'desc' => '待付款');
        $back_desc='无';
        if(($back_number or $backing_number) and $buy_number!=($back_number+$backing_number)){
            $back_desc='有退款';
        }
        if ($buy_number < ($backing_number + $back_number + $use_number)) {
            $s = array('status' => -1, 'desc' => '订单异常','back_desc'=>$back_desc);
        }
        if ($pay_status < 2) {
            $s = array('status' => 0, 'desc' => '待付款','back_desc'=>$back_desc);
        } else {
            //待使用,只要有待使用
            if (($buy_number - $backing_number - $back_number - $use_number) > 0) {
                $s = array('status' => 2, 'desc' => '待使用','back_desc'=>$back_desc);
            } else {
                //已使用  只要有已使用
                if ($use_number > 0) {
                    $s = array('status' => 5, 'desc' => '已使用','back_desc'=>$back_desc);
                }elseif($backing_number>0){
                    //退款中 只要有退款中
                    $s = array('status' => 3, 'desc' => '退款中','back_desc'=>$back_desc);
                }elseif ($back_number > 0) {
                    //退款完成  整单退款完成
                    $s = array('status' => 4, 'desc' => '已退款','back_desc'=>$back_desc);
                }
            }
        }
        return $s;
    }

    public function getOrderList($page = 1, $pageSum = 10, $param = array(), $db = null)
    {
        $where = "play_order_info.order_status = 1";
        $order = "play_order_info.order_sn DESC";
        $start = ($page - 1) * $pageSum;

        if ($param['good_name']) {
            $where .= " AND (play_organizer_game.title like '%{$param['good_name']}%' OR play_organizer_game.editor_talk like '%{$param['good_name']}%')";
        }

        if ($param['shop_name']) {
            $where .= " AND play_order_info.shop_name like '%{$param['shop_name']}%'";
        }

        if ($param['good_id']) {
            $where .= " AND play_order_info.coupon_id ={$param['good_id']}";
        }

        if ($param['user_name']) {
            $where .= " AND play_order_info.username like '%{$param['user_name']}%'";
        }

        //购买时间筛选
        if (!empty($param["buy_start_date"]) && !empty($param["buy_end_date"])) {
            $nStartDate = strtotime($param["buy_start_date"]);
            $nEndDate = strtotime($param["buy_end_date"]);

            //容错起始时间大于结束时间
            if ($nStartDate > $nEndDate) {
                $where .= " and (play_order_info.dateline >= {$nEndDate} and play_order_info.dateline <= {$nStartDate}) ";
            } else {
                $where .= " and (play_order_info.dateline >= {$nStartDate} and play_order_info.dateline <= {$nEndDate}) ";
            }
        } else {
            if (!empty($param["buy_start_date"])) {
                $nStartDate = strtotime($param["buy_start_date"]);
                $where .= " and play_order_info.dateline >= {$nStartDate} ";
            }

            if (!empty($param["buy_end_date"])) {
                $nEndDate = strtotime($param["buy_end_date"]);
                $where .= " and play_order_info.dateline <= {$nEndDate} ";
            }
        }

        //验证时间筛选
        if (!empty($param["ver_start_date"]) && !empty($param["ver_end_date"])) {
            $nStartDate = strtotime($param["ver_start_date"]);
            $nEndDate = strtotime($param["ver_end_date"]);

            //容错起始时间大于结束时间
            if ($nStartDate > $nEndDate) {
                $where .= " and (play_order_info.use_dateline >= {$nEndDate} and play_order_info.use_dateline <= {$nStartDate}) ";
            } else {
                $where .= " and (play_order_info.use_dateline >= {$nStartDate} and play_order_info.use_dateline <= {$nEndDate}) ";
            }
        } else {
            if (!empty($param["ver_start_date"])) {
                $nStartDate = strtotime($param["ver_start_date"]);
                $where .= " and play_order_info.use_dateline >= {$nStartDate} ";
            }

            if (!empty($param["ver_end_date"])) {
                $nEndDate = strtotime($param["ver_end_date"]);
                $where .= " and play_order_info.use_dateline <= {$nEndDate} ";
            }
        }

        //提交退款时间筛选
        if (!empty($param["sub_back_start_date"]) && !empty($param["sub_back_end_date"])) {
            $nStartDate = strtotime($param["sub_back_start_date"]);
            $nEndDate = strtotime($param["sub_back_end_date"]);

            //容错起始时间大于结束时间
            if ($nStartDate > $nEndDate) {
                $where .= " and (play_coupon_code.back_time >= {$nEndDate} and play_coupon_code.back_time <= {$nStartDate}) ";
            } else {
                $where .= " and (play_coupon_code.back_time >= {$nStartDate} and play_coupon_code.back_time <= {$nEndDate}) ";
            }
        } else {
            if (!empty($param["sub_back_start_date"])) {
                $nStartDate = strtotime($param["sub_back_start_date"]);
                $where .= " and play_coupon_code.back_time >= {$nStartDate} ";
            }

            if (!empty($param["sub_back_end_date"])) {
                $nEndDate = strtotime($param["sub_back_end_date"]);
                $where .= " and play_coupon_code.back_time <= {$nEndDate} ";
            }
        }

        if ($param['user_phone']) {
            $where .= " AND play_order_info.phone='{$param['user_phone']}'";
        }

        if ($param['pay_status']) {
            if ($param['pay_status'] == 1) {
                $where .= " AND play_order_info.pay_status=0";
            } else {
                if ($param['pay_status'] == 5) {
                    $where .= " AND play_coupon_code.test_status=5";
                } else {
                    $where .= " AND play_order_info.pay_status={$param['pay_status']}";
                }
            }
        }


        if ($param['ver_status']) {
            if ($param['ver_status'] < 2) {
                $where .= " AND play_coupon_code.check_status < 2 ";
            } else {
                $where .= " AND play_coupon_code.check_status = 2 ";
            }
        }


        if ($param['good_status']) {
            $where .= " AND play_organizer_game.is_together = 1";
            if ($param['good_status'] == 1) { //未开始
                $where .= " AND play_organizer_game.status = 1 && play_organizer_game.up_time > " . time();
            } elseif ($param['good_status'] == 2) {// 在售卖
                $where .= " AND play_organizer_game.status = 1 && play_organizer_game.up_time < " . time() . " && play_organizer_game.down_time > " . time();
            } elseif ($param['good_status'] == 4) {// 停止售卖
                $where .= " AND play_organizer_game.status = 1 && play_organizer_game.foot_time > " . time() . " && play_organizer_game.down_time < " . time();
            } elseif ($param['good_status'] == 3) {// 停止使用
                $where .= " AND play_organizer_game.status = 1 && play_organizer_game.foot_time < " . time() . " && play_organizer_game.down_time < " . time();
            }
        }

        if ($param['pay_type']) {
            $where .= " AND play_order_info.account_type={$param['pay_type']} ";
        }

        $sql = "SELECT
	`play_order_info`.*,
	`play_order_action`.`play_status` AS `play_status`,
	`play_order_action`.`dateline` AS `back_dateline`,
	`play_game_info`.`price`,
	`play_game_info`.`account_money`,
	`play_game_info`.`price_name`,
	`play_organizer_game`.`status` AS `game_status`,
	`play_organizer_game`.`is_together`,
	`play_game_info`.`up_time`,
	`play_game_info`.`down_time`,
	`play_game_info`.`refund_time`,
	`play_organizer_game`.`end_time`,
	`play_organizer_game`.`foot_time`,
	`play_order_otherdata`.`message`,
	`play_coupon_code`.`check_status`
    FROM
	`play_order_info`
    LEFT JOIN `play_order_action` ON `play_order_action`.`order_id` = `play_order_info`.`order_sn`
    LEFT JOIN `play_order_otherdata` ON `play_order_otherdata`.`order_sn` = `play_order_info`.`order_sn`
    LEFT JOIN `play_coupon_code` ON `play_coupon_code`.`order_sn` = `play_order_action`.`order_id`
    LEFT JOIN `play_order_info_game` ON `play_order_info_game`.`order_sn` = `play_order_info`.`order_sn`
    AND `play_order_info`.`order_type` = '2'
    LEFT JOIN play_organizer_game ON play_order_info.coupon_id = play_organizer_game.id AND play_order_info.order_type = '2'
    LEFT JOIN `play_game_info` ON `play_game_info`.`id` = `play_order_info_game`.`game_info_id`
    AND `play_order_info`.`order_type` = '2'
    WHERE
	 $where
    GROUP BY
	`play_order_info`.`order_sn`
    ORDER BY
	$order";

        $sql_count = "SELECT
	count(*) as c
    FROM
	`play_order_info`
    LEFT JOIN `play_order_action` ON `play_order_action`.`order_id` = `play_order_info`.`order_sn`
    LEFT JOIN `play_order_otherdata` ON `play_order_otherdata`.`order_sn` = `play_order_info`.`order_sn`
    LEFT JOIN `play_coupon_code` ON `play_coupon_code`.`order_sn` = `play_order_action`.`order_id`
    LEFT JOIN `play_order_info_game` ON `play_order_info_game`.`order_sn` = `play_order_info`.`order_sn`
    AND `play_order_info`.`order_type` = '2'
    LEFT JOIN play_organizer_game ON play_order_info.coupon_id = play_organizer_game.id AND play_order_info.order_type = '2'
    LEFT JOIN `play_game_info` ON `play_game_info`.`id` = `play_order_info_game`.`game_info_id`
    AND `play_order_info`.`order_type` = '2'
    WHERE
	 $where
    GROUP BY
	`play_order_info`.`order_sn`
    ORDER BY
	$order";

        $data = $this->query($sql . " LIMIT {$start} , {$pageSum}", $db);
        $count = M::getAdapter()->query($sql_count,array())->current()->c;

        //创建分页
        $url = '/wftadlogin/finance/index';
        $paginator = new Paginator($page, $count, $pageSum, $url);

        return array('pageData' => $paginator->getHtml(), 'data' => $data);

    }


    //订单审核列表
    public function getAuditOrder($page = 1, $pageSum = 10, $param = array(), $db = null)
    {
        $where = "play_order_info.pay_status > 1";
        $order = "play_order_info.order_sn DESC";
        $start = ($page - 1) * $pageSum;

        if ($param['game_name']) {
            $where .= " AND play_order_info.coupon_name like '%" . $param['game_name'] . "%'";
        }

        if ($param['shop_id']) {
            $where .= " AND play_order_info.shop_id={$param['shop_id']}";
        }

        if ($param['user_id']) {
            $where .= " AND play_order_info.user_id={$param['user_id']}";
        }

        if ($param['order_sn']) {
            $where .= " AND play_order_info.order_sn = {$param['order_sn']}";
        }

        if ($param['city']) {
            $where .= " AND play_order_info.order_city like '%{$param['city']}%'";
        }

        if (!empty($param["sub_back_start_date"]) && !empty($param["sub_back_end_date"])) {
            $nStartDate = strtotime($param["sub_back_start_date"]);
            $nEndDate = strtotime($param["sub_back_end_date"]) + 86400;

            //容错起始时间大于结束时间
            if ($nStartDate > $nEndDate) {
                $where .= "  AND (play_coupon_code.back_time > {$nEndDate} AND play_coupon_code.back_time < {$nStartDate}) ";
            } else {
                $where .= "  AND (play_coupon_code.back_time > {$nStartDate} AND play_coupon_code.back_time < {$nEndDate}) ";
            }
        } else {
            if (!empty($param["sub_back_start_date"])) {
                $nStartDate = strtotime($param["sub_back_start_date"]);
                $where .= " AND play_coupon_code.back_time > {$nStartDate} ";
            }
            if (!empty($param["sub_back_end_date"])) {
                $nEndDate = strtotime($param["sub_back_end_date"]) + 86400;
                $where .= " AND play_coupon_code.back_time < {$nEndDate} ";
            }
        }

        //交易时间
        if (!empty($param["trade_start_time"]) && !empty($param["trade_end_time"])) {
            $nStartDate = strtotime($param["trade_start_time"]);
            $nEndDate = strtotime($param["trade_end_time"]) + 86400;

            //容错起始时间大于结束时间
            if ($nStartDate > $nEndDate) {
                $where .= " AND (play_order_info.dateline >= {$nEndDate} AND play_order_info.dateline <= {$nStartDate}) ";
            } else {
                $where .= " AND (play_order_info.dateline >= {$nStartDate} AND play_order_info.dateline <= {$nEndDate}) ";
            }
        } else {
            if (!empty($param["trade_start_time"])) {
                $nStartDate = strtotime($param["trade_start_time"]);
                $where .= " AND play_order_info.dateline >= {$nStartDate} ";
            }
            if (!empty($param["trade_end_time"])) {
                $nEndDate = strtotime($param["trade_end_time"]);
                $where .= " AND play_order_info.dateline <= {$nEndDate} ";
            }
        }

        //结算时间
        if (!empty($param["close_start_time"]) && !empty($param["close_end_time"])) {
            $nStartDate = strtotime($param["close_start_time"]);
            $nEndDate = strtotime($param["close_end_time"]);

            //容错起始时间大于结束时间
            if ($nStartDate > $nEndDate) {
                $where .= " and (play_coupon_code.account_time >= {$nEndDate} and play_coupon_code.account_time <= {$nStartDate}) ";
            } else {
                $where .= " and (play_coupon_code.account_time >= {$nStartDate} and play_coupon_code.account_time <= {$nEndDate}) ";
            }
        } else {
            if (!empty($param["close_start_time"])) {
                $nStartDate = strtotime($param["close_start_time"]);
                $where .= " and play_coupon_code.account_time >= {$nStartDate} ";
            }
            if (!empty($param["close_end_time"])) {
                $nEndDate = strtotime($param["close_end_time"]);
                $where .= " and play_coupon_code.account_time <= {$nEndDate} ";
            }
        }

        $sql = "SELECT
	`play_coupon_code`.`check_status`,
	`play_coupon_code`.`id`,
	`play_coupon_code`.`status`,
	`play_coupon_code`.`test_status`,
	`play_coupon_code`.`force`,
	`play_order_info`.`dateline`,
	`play_order_info`.`order_city`,
	`play_order_info`.`order_sn`,
	`play_order_info`.`user_id`,
	`play_order_info`.`real_pay`,
	`play_order_info`.`account_money`,
	`play_order_info`.`buy_number`,
	`play_order_info`.`account_type`,
	`play_order_info`.`account`,
	`play_order_info`.`shop_name`,
	`play_order_info`.`shop_id`,
	`play_order_info`.`coupon_name`,
	`play_order_info`.`pay_status`,
	`play_game_info`.`account_money`
    FROM
	`play_order_info`
    LEFT JOIN `play_coupon_code` ON `play_coupon_code`.`order_sn` = `play_order_info`.`order_sn`
    LEFT JOIN `play_order_info_game` ON `play_order_info_game`.`order_sn` = `play_coupon_code`.`order_sn`
    LEFT JOIN `play_game_info` ON `play_game_info`.`id` = `play_order_info_game`.`game_info_id`
    WHERE
	 $where
    GROUP BY
	`play_coupon_code`.`order_sn`
    ORDER BY
	$order";

        $count_sql = "SELECT
	count(*) as c
    FROM
	`play_order_info`
    LEFT JOIN `play_coupon_code` ON `play_coupon_code`.`order_sn` = `play_order_info`.`order_sn`
    LEFT JOIN `play_order_info_game` ON `play_order_info_game`.`order_sn` = `play_coupon_code`.`order_sn`
    LEFT JOIN `play_game_info` ON `play_game_info`.`id` = `play_order_info_game`.`game_info_id`
    WHERE
	 $where
    GROUP BY
	`play_coupon_code`.`order_sn`
    ORDER BY
	$order";


        $data = $this->query($sql . " LIMIT {$start} , {$pageSum}", $db);
        $count = (int)M::getAdapter()->query($count_sql,array())->current()->c;

        //创建分页
        $url = '/wftadlogin/finance/auditOrder';
        $paginator = new Paginator($page, $count, $pageSum, $url);

        return array('pageData' => $paginator->getHtml(), 'data' => $data, 'where' => $where);
    }


    function query($sql, $db)
    {

        $stmt = $db->query($sql);
        $result = $stmt->execute($stmt);
        return $result;
    }

    //获取第三方 订单详情

    public function getOrderInfo($order_sn)
    {

       /* $Adapter = $this->_getAdapter();
        $orderData = $Adapter->query('SELECT * FROM play_order_info where order_sn = ?', array(3))->current();

        if (!$orderData) {
            return array(
                'status' => 0,
                'message' => '订单不存在'
            );
        }

        $account_type = $orderData->account_type;
        $transaction_id = $orderData->transaction_id;*/

        $account_type = 'weixin';
        $transaction_id = '4004272001201607279879844111';

        /*$account_type = 'new_jsapi';
        $transaction_id = '4006902001201608080892504518';*/


        if ($account_type == 'weixin') {

            $WeiXin =  new WeiPay();
            $orderInfo = $WeiXin->getOrderInfo($transaction_id);
            return array(
                'status' => 1,
                'message' => $orderInfo
            );

        }

        if ($account_type == 'new_jsapi') {

            $WeiXinWap = new WeiXinPayFun($this->_getConfig()['wanfantian_weixin_r']);
            $orderInfo = $WeiXinWap->getOrderInfo($transaction_id);
            return array(
                'status' => 1,
                'message' => $orderInfo
            );

        }

        return array(
            'status' => 0,
            'message' => '该支付方式的查询接口 未放开'
        );


    }

    public function getToReserveOrderCount () {
        $count = RedCache::fromCacheData('D:wft_str_orderalert', function () {
            $apt = $this->_getAdapter();
            $city = $this->getAdminCity();

            $sql = "SELECT
    COUNT(*) as c
FROM
    play_coupon_code
        LEFT JOIN
    play_order_info ON play_coupon_code.order_sn = play_order_info.order_sn
        LEFT JOIN
    play_organizer_game ON play_organizer_game.id = play_order_info.coupon_id
WHERE
    play_order_info.order_status = 1
        AND play_order_info.pay_status > 1
        AND play_order_info.order_type = 2 
        AND play_order_info.order_city = ?
        AND play_coupon_code.`status` = 0
        and play_organizer_game.message_type = 2";

            $orders = $apt->query($sql, [$city])->current();

            $data_count = $orders->c;

            return $data_count;
        }, 5 * 60, true);

        return $count;
    }
}