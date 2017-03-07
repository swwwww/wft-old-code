<?php

namespace Admin\Controller;

use Deyi\JsonResponse;
use Deyi\Paginator;
use Deyi\OutPut;

class ApproveController extends BasisController
{
    use JsonResponse;

    private $tradeWay = array(
        'alipay' => '支付宝',
        'union' => '银联',
        'weixin' => '微信',
        'jsapi' => '旧微信网页',
        'account' => '账户',
        'new_jsapi' => '新微信网页',
    );

    //订单审核列表
    public function indexAction()
    {
        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;
        $order = "play_order_info.order_sn DESC";
        $where = $this->getWhere();

        $sql = "SELECT
    play_order_info.order_sn,
    play_order_info.trade_no,
	play_order_info.user_id,
	play_order_info.coupon_name,
	play_order_info.dateline,
	play_order_info.username,
	play_order_info.bid,
    play_order_info.coupon_id,
	play_order_info.account_type,
	play_order_info.account,
    play_order_info.shop_name,
    play_order_info.account_money,
    play_order_info.real_pay,
	play_order_info.buy_number,
    play_order_info.order_type,
	play_order_info.voucher,
    play_order_info.approve_status
FROM
	play_order_info
WHERE
	 $where
ORDER BY $order";

        $sql_list = $sql." LIMIT
{$start}, {$pageSum}";

        $data = $this->query($sql_list);

        $count_sql = "SELECT count(*) as count_num, SUM(real_pay) as order_card_money,
  SUM(account_money) as order_account_money FROM play_order_info WHERE $where";

        $count_res = $this->query($count_sql)->current();
        $count = $count_res['count_num'];

        $counter = array(
            'total_money' =>  $count_res['order_card_money'] + $count_res['order_account_money'],
        );

        //已审批金额
        $counter_sql = "SELECT SUM(real_pay) as order_card_money,
SUM(account_money) as order_account_money FROM play_order_info WHERE $where  AND play_order_info.approve_status = 2";
        $countData = $this->query($counter_sql)->current();
        $counter['approve_money'] = $countData['order_card_money'] + $countData['order_account_money'];

        $url = '/wftadlogin/approve/index';
        $paging = new Paginator($page, $count, $pageSum, $url);

        return array(
            'count' => $count,
            'data' => $data,
            'pageData' => $paging->getHtml(),
            'trade' => $this->tradeWay,
            'counter' => $counter,
        );
    }

    //审核
    public function doneAction()
    {
        $type = (int)$this->getQuery('type', 0);

        if (!in_array($type, array(1, 2, 3))) {
            return $this->_Goto('非法操作');
        }

        $where = 'play_order_info.order_status = 1 AND play_order_info.pay_status >= 2 AND play_order_info.approve_status = 1';

        if ($type == 1) { //单个
            $order_sn = $this->getQuery('id');
            $where = $where. " AND play_order_info.order_sn = {$order_sn}";
        }

        if ($type == 2) { //单页面批量操作
            $order_sns = trim($this->getQuery('id'), ',');
            $where = $where. " AND play_order_info.order_sn IN ($order_sns)";
        }

        if ($type == 3) { //sql 条件下批量操作

            $where_query = $this->getWhere();
            $where =  $where_query. ' AND '. $where;
        }

        $up_sql = "SELECT play_order_info.order_sn FROM play_order_info WHERE $where";
        $up_data = $this->query($up_sql);
        $countNum = $up_data->count();

        if ($countNum > 3000) {
            return $this->_Goto('要处理的数据太多了');
        }

        if ($countNum < 1) {
            return $this->_Goto('没有符合的数据去处理');
        }

        $good_up_sql = "SELECT play_order_info.order_sn FROM play_order_info WHERE $where AND play_order_info.order_type = 2";
        $have_good = $this->query($good_up_sql)->count();

        $timer = time();
        $common_arr = $this->_getConfig();
        $pdo = new \PDO($common_arr['db']['dsn'], $common_arr['db']['username'], $common_arr['db']['password'], $common_arr['db']['driver_options']);

        $pdo->beginTransaction();

        if ($have_good) {
            $sql_two = "UPDATE play_coupon_code, play_order_info
SET play_coupon_code.check_status = 2
WHERE $where AND play_order_info.order_type = 2 AND play_order_info.order_sn = play_coupon_code.order_sn";

            $res_two = $pdo->exec($sql_two);

            if (!$res_two) {
                $pdo->rollBack();
                return $this->_Goto('处理失败!');
            }
        }

        $sql_one = "UPDATE play_order_info
SET play_order_info.approve_status = 2
WHERE $where";
        $res_one = $pdo->exec($sql_one);

        if (!$res_one) {
            $pdo->rollBack();
            return $this->_Goto('处理失败');
        }

        //插入更新记录记录
        $i = 0;
        $insert_sql = "INSERT play_order_action (`action_user`, `order_id`, `play_status`, `action_note`, `dateline`, `action_user_name`) VALUES ";
        foreach ($up_data as $up) {
            $action_note = '订单'. $up['order_sn'].' 审批到账';
            $action_user_name = '管理员'. $_COOKIE['user'];
            if (!$i) {
                $insert_sql = $insert_sql . "({$_COOKIE['id']}, {$up['order_sn']}, 6, '{$action_note}', {$timer}, '{$action_user_name}')";
            } else {
                $insert_sql = $insert_sql . ", ({$_COOKIE['id']}, {$up['order_sn']}, 6, '{$action_note}', {$timer}, '{$action_user_name}')";
            }
            $i ++;
        }

        $insert = $pdo->exec($insert_sql);
        if (!$insert) {
            $pdo->rollback();
            return $this->_Goto('失败');
        }

        $pdo->commit();

        return $this->_Goto('处理成功');
    }

    //导出
    public function outAction()
    {

        $where = $this->getWhere();
        $sql_list = "SELECT
    play_order_info.order_sn,
    play_order_info.trade_no,
	play_order_info.coupon_name,
	play_order_info.dateline,
    play_order_info.order_type,
	play_order_info.coupon_unit_price,
	play_order_info.username,
	play_order_info.shop_name,
    play_order_info.user_id,
	play_order_info.account,
    play_order_info.account_type,
    play_order_info.account_money,
    play_order_info.real_pay,
	play_order_info.buy_number,
    play_order_info.order_city,
    play_order_info.voucher,
    play_order_info.phone
FROM
	play_order_info
WHERE $where";

        $data = $this->query($sql_list);
        $count = $data->count();

        if (!$count) {
            return $this->_Goto('0条数据！');
        }

        if ($count > 10000) {
            return $this->_Goto('数据太多了, 请缩小范围');
        }

        $out = new OutPut();
        $file_name = date('Y-m-d H:i:s', time()). '_订单审批.csv';
        $city = $this->getAllCities();
        $tradeWay = $this->tradeWay;

        $head = array(
            '交易渠道',
            '城市',
            '交易时间',
            '交易号',
            '订单号',
            '商品/活动名称',
            '商家名称',
            '类型',
            '购买数量',
            '单价',
            '支付金额',
            '代金券金额',
            '用户id',
            '用户名',
            '手机号',
            '用户支付账号',
        );

        $content = array();

        foreach ($data as $v) {
            $content[] = array(
                $tradeWay[$v['account_type']],
                $city[$v['order_city']],
                date('Y-m-d H:i:s', $v['dateline']),
                "\t".$v['trade_no'],
                'WFT' . (int)$v['order_sn'],
                $v['coupon_name'],
                $v['shop_name'],
                $v['order_type'] == 2 ? '商品' : '活动',
                $v['buy_number'],
                $v['coupon_unit_price'],
                bcadd($v['real_pay'], $v['account_money'], 2),
                $v['voucher'],
                $v['user_id'],
                $v['username'],
                $v['phone'],
                $v['account'],
            );
        }

        $out->out($file_name, $head, $content);
        exit;
    }

    //审核 及 导出的条件
    private function getWhere()
    {
        $where = 'play_order_info.order_status = 1 AND play_order_info.pay_status >= 2 AND play_order_info.order_type >= 2';

        $order_id =  $this->getQuery('order_id', ''); //订单号
        $user_id =  $this->getQuery('user_id', ''); //用户id/名称/手机号
        $coupon_id =  $this->getQuery('coupon_id', ''); //商品 活动名称/id
        $order_type = (int)$this->getQuery('order_type', ''); //订单类型

        if ($order_type) {
            $where = $where . " AND play_order_info.order_type = {$order_type}";
        }

        if ($order_id) {
            $order_id = (int)preg_replace('|[a-zA-Z/]+|','',$order_id);
            $where = $where . " AND play_order_info.order_sn = {$order_id}";
        }

        if ($user_id) {
            $where = $where.  ' AND (play_order_info.username like "%' . $user_id . '%" or play_order_info.user_id = ' . (int)$user_id . ' or play_order_info.phone = "' . $user_id . '")';
        }

        if ($coupon_id) {
            $where = $where.  ' AND (play_order_info.coupon_name like "%' . $coupon_id . '%" or play_order_info.coupon_id = ' . (int)$coupon_id. ' or play_order_info.bid = ' . (int)$coupon_id.')';
        }

        //审核状态
        $check_status = intval($this->getQuery('check_status', 1));
        if ($check_status && in_array($check_status, array(1, 2))) {
            $where = $where . " AND play_order_info.approve_status = {$check_status}";
        }

        //支付方式 'weixin','union','other','jsapi','nopay','alipay'
        $trade_way =  $this->getQuery('trade_way', 0);
        if ($trade_way) {
            if ($trade_way == 1) {
                $where = $where. " AND play_order_info.account_type = 'alipay'";
            } elseif ($trade_way == 2) {
                $where = $where. " AND play_order_info.account_type = 'union'";
            } elseif ($trade_way == 3) {//新微信网页
                $where = $where. " AND play_order_info.account_type = 'new_jsapi'";
            } elseif ($trade_way == 4) {
                $where = $where. " AND play_order_info.account_type = 'jsapi'";
            } elseif ($trade_way == 5) {//微信SDK
                $where = $where. " AND play_order_info.account_type = 'weixin'";
            } elseif ($trade_way == 6) {//余额
                $where = $where. " AND play_order_info.account_type = 'account'";
            }
        }

        $buy_start = $this->getQuery('buy_start', null);
        $buy_end = $this->getQuery('buy_end', null);

        //购买时间
        if ($buy_start) {
            $buy_start = strtotime($buy_start);
            $where = $where. " AND play_order_info.dateline > ".$buy_start;
        }

        if ($buy_end) {
            $buy_end = strtotime($buy_end) + 86400;
            $where = $where. " AND play_order_info.dateline < ".$buy_end;
        }

        $city = $this->getBackCity();
        if($city){
            $where = $where. " AND play_order_info.order_city = '{$city}'";
        }

        return $where;
    }

}
