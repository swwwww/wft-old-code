<?php

namespace Admin\Controller;

use Deyi\BaseController;
use Deyi\JsonResponse;

use Deyi\OrderAction\OrderBack;
use Deyi\Paginator;
use Deyi\SendMessage;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Deyi\OutPut;

class ClearingController extends BasisController
{
    use JsonResponse;
    use OrderBack;
    //use BaseController;

    private $tradeWay = array(
        'alipay' => '支付宝',
        'union' => '银联',
        'weixin' => '微信',
        'jsapi' => '旧微信网页',
        'account' => '账户',
        'new_jsapi' => '新微信网页',
        'nopay' => '未付款',
    );

    //订单结算列表
    public function listAction() {

        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;
        $order = "play_order_info.order_sn DESC";
        $where = $this->getWhereQuery();

        //全部已付款
        $where = $where. " AND play_order_info.pay_status >= 2 AND play_coupon_code.test_status >= 3";

        $buy_start = $this->getQuery('buy_start', null);
        $buy_end = $this->getQuery('buy_end', null);
        $check_start = $this->getQuery('check_start', null);
        $check_end = $this->getQuery('check_end', null);
        $back_start = $this->getQuery('back_start', null);
        $back_end = $this->getQuery('back_end', null);

        if (!isset($_GET['buy_start'])) {
            $buy_start = date('Y-m-d', time() - 3*24*3600);
            $buy_end = date('Y-m-d', time());
        }

        //购买时间
        if ($buy_start && $buy_end && strtotime($buy_start) > strtotime($buy_end)) {
            return $this->_Goto('购买时间出错');
        }

        //验证时间
        if ($check_start && $check_end && strtotime($check_start) > strtotime($check_end)) {
            return $this->_Goto('验证时间出错');
        }

        //退款时间
        if ($back_start && $back_end && strtotime($back_start) > strtotime($back_end)) {
            return $this->_Goto('提交退款时间出错');
        }

        //购买时间
        if ($buy_start) {
            $buy_start = strtotime($buy_start);
            $where = $where. " AND play_order_info.dateline > ".$buy_start;
        }

        if ($buy_end) {
            $buy_end = strtotime($buy_end) + 86400;
            $where = $where. " AND play_order_info.dateline < ".$buy_end;
        }

        //验证时间
        if ($check_start) {
            $check_start = strtotime($check_start);
            $where = $where. " AND play_coupon_code.use_datetime > ".$check_start;
        }

        if ($check_end) {
            $check_end = strtotime($check_end) + 86400;
            $where = $where. " AND play_coupon_code.use_datetime < ". $check_end;
        }

        //提交退款时间
        if ($back_start) {
            $back_start = strtotime($back_start);
            $where = $where. " AND play_coupon_code.back_time > ".$back_start;
        }

        if ($back_end) {
            $back_end = strtotime($back_end) + 86400;
            $where = $where. " AND play_coupon_code.back_time < ". $back_end;
        }

        $sql = "SELECT
    play_order_info.order_sn,
    play_order_info.trade_no,
	play_order_info.user_id,
	play_order_info.coupon_name,
	play_order_info.dateline,
	play_order_info.username,
	play_order_info.coupon_id,
	play_order_info.account_type,
	play_order_info.account,
    play_order_info.shop_name,
    play_order_info.account_money,
    play_order_info.real_pay,
	play_order_info.buy_number,
    play_coupon_code.back_money,
    play_coupon_code.id,
	play_coupon_code.check_status,
	play_coupon_code.status,
	play_coupon_code.test_status
FROM
	play_coupon_code
LEFT JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
WHERE
	 $where
ORDER BY $order";

        $sql_list = $sql." LIMIT
{$start}, {$pageSum}";

        $data = $this->query($sql_list);



        $count_sql = "SELECT count(*) as count_num FROM play_coupon_code
LEFT JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
WHERE $where";

        $count_res = $this->query($count_sql);
        $count = $count_res->current()['count_num'];

        //创建分页
        $url = '/wftadlogin/clearing/list';
        $paging = new Paginator($page, $count, $pageSum, $url);


        $sql_count = "SELECT
	count(*) as count_num,
	SUM(real_pay) AS real_pay_money,
	SUM(account_money) AS balance_money
FROM
(SELECT play_order_info.order_sn, play_order_info.real_pay, play_order_info.account_money FROM
	play_order_info
LEFT JOIN play_order_info_game ON play_order_info_game.order_sn = play_order_info.order_sn
LEFT JOIN play_organizer_game ON play_organizer_game.id = play_order_info.coupon_id
LEFT JOIN play_coupon_code ON play_coupon_code.order_sn = play_order_info.order_sn
WHERE
$where GROUP BY play_order_info.order_sn) AS list_order";


        $countData = $this->query($sql_count)->current();
        $count_num = $countData['count_num'];

        $back_money_sql = "SELECT
	 SUM(play_coupon_code.back_money) as back_money
FROM
	play_coupon_code
LEFT JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
WHERE
$where AND (play_coupon_code.status = 2 OR play_coupon_code.status = 3)";

        $backMoneyData = $this->query($back_money_sql)->current();
        $outSum = $backMoneyData['back_money'];

        return array(
            'data' => $data,
            'pageData' => $paging->getHtml(),
            'trade' => $this->tradeWay,
            'order_sum' => $count_num,
            'pay_sum' => $count_num,
            'income' => bcadd($countData['real_pay_money'], $countData['balance_money'], 2),
            'outSum' => $outSum,
        );
    }

    //订单结算导出
    public function outDataAction() {
        $where = $this->getWhereQuery();
        $order = 'play_order_info.order_sn DESC';

        $buy_start = $this->getQuery('buy_start', null);
        $buy_end = $this->getQuery('buy_end', null);
        $check_start = $this->getQuery('check_start', null);
        $check_end = $this->getQuery('check_end', null);
        $back_start = $this->getQuery('back_start', null);
        $back_end = $this->getQuery('back_end', null);

        //购买时间
        if ($buy_start && $buy_end && strtotime($buy_start) > strtotime($buy_end)) {
            return $this->_Goto('购买时间出错');
        }

        //验证时间
        if ($check_start && $check_end && strtotime($check_start) > strtotime($check_end)) {
            return $this->_Goto('验证时间出错');
        }

        //退款时间
        if ($back_start && $back_end && strtotime($back_start) > strtotime($back_end)) {
            return $this->_Goto('提交退款时间出错');
        }

        //购买时间
        if ($buy_start) {
            $buy_start = strtotime($buy_start);
            $where = $where. " AND play_order_info.dateline > ".$buy_start;
        }

        if ($buy_end) {
            $buy_end = strtotime($buy_end) + 86400;
            $where = $where. " AND play_order_info.dateline < ".$buy_end;
        }

        //验证时间
        if ($check_start) {
            $check_start = strtotime($check_start);
            $where = $where. " AND play_coupon_code.use_datetime > ".$check_start;
        }

        if ($check_end) {
            $check_end = strtotime($check_end) + 86400;
            $where = $where. " AND play_coupon_code.use_datetime < ". $check_end;
        }

        //提交退款时间
        if ($back_start) {
            $back_start = strtotime($back_start);
            $where = $where. " AND play_coupon_code.back_time > ". $back_start;
        }

        if ($back_end) {
            $back_end = strtotime($back_end) + 86400;
            $where = $where. " AND play_coupon_code.back_time < ". $back_end;
        }

        $sql = "SELECT
    play_order_info.order_sn,
	play_order_info.back_number,
	play_order_info.buy_phone,
	play_order_info.coupon_name,
	play_order_info.shop_name,
	play_order_info.dateline,
	play_order_info.username,
	play_order_info.coupon_id,
	play_order_info.pay_status,
    play_order_info.trade_no,
	play_order_info.account_type,
	play_order_info.account,
	play_order_info.order_type,
	play_order_info.coupon_unit_price,
	play_order_info.buy_number,
	play_order_info.use_number,
	play_order_info.phone,
	play_order_info.user_id,
    play_order_info.real_pay,
    play_order_info.account_money as balance_money,
	play_game_info.shop_name AS game_dizhi,
	play_game_info.price_name AS game_taoxi,
	play_game_info.start_time AS game_start,
	play_game_info.end_time AS game_end,
	play_game_price.account_money,
	play_organizer.name,
	play_organizer.bank_name,
	play_organizer.bank_card,
	play_organizer_game.is_together,
	play_game_info.up_time,
	play_game_info.down_time,
	play_game_info.refund_time,
	play_organizer_game.end_time,
    play_organizer_game.foot_time,
	play_organizer_game.status as game_status,
	play_admin.admin_name
FROM
	play_order_info
LEFT JOIN play_coupon_code ON play_coupon_code.order_sn = play_order_info.order_sn
LEFT JOIN play_order_info_game ON play_order_info_game.order_sn = play_order_info.order_sn AND play_order_info.order_type = '2'
LEFT JOIN play_game_info ON play_game_info.id = play_order_info_game.game_info_id AND play_order_info.order_type = '2'
LEFT JOIN play_game_price ON play_game_info.pid = play_game_price.id AND play_order_info.order_type = '2'
LEFT JOIN play_organizer ON play_order_info.shop_id = play_organizer.id AND play_order_info.order_type = '2'
LEFT JOIN play_organizer_game ON play_order_info.coupon_id = play_organizer_game.id AND play_order_info.order_type = '2'
LEFT JOIN play_contracts ON play_contracts.id = play_organizer_game.contract_id
LEFT JOIN play_admin ON play_admin.id = play_contracts.business_id
WHERE
	 $where
GROUP BY
  play_order_info.order_sn
ORDER BY
	$order";

        $data = $this->query($sql);

        $count_sql = "SELECT
    play_order_info.order_sn
FROM
	play_order_info
LEFT JOIN play_coupon_code ON play_coupon_code.order_sn = play_order_info.order_sn
WHERE
	 $where
GROUP BY
  play_order_info.order_sn";
        $count = $this->query($count_sql)->count();

        if (!$count) {
            return $this->_Goto('0条数据！');
        }

        if ($count > 32000) {
            return $this->_Goto('超过系统最大负荷，请把时间设在1个月内，重新搜索');
        }

        $out = new OutPut();

        $file_name = date('Y-m-d H:i:s', time()). '_订单列表.csv';
        // 输出Excel列名信息
        $head = array(
            '交易时间',
            '交易渠道',
            '交易号',
            '商品订单号',
            '商家名称',
            '商品名称',
            '单价',
            '购买数量',
            '购买金额',
            '代金券金额',
            '已使用数',
            '已使用金额',
            '等待退款金额',
            '已退款金额',
            '结算单价',
            '需结算数量',
            '已结算金额',
            '收益',
            '商家开户行',
            '商家账号',
            '商品状态',
            '对方账户',
            '用户名',
            '手机号',
            '用户id',
            '活动地址 （那个游玩地）',
            '套系名称',
            '该套系的开始时间',
            '该套系的结束时间',
            '最近的使用时间',
            '最近的结算时间',
            '类别',
            '商品id',
            '订单状态',
            '是否可退款',
            '经办人',
        );

        $content = array();

        foreach ($data as $v) {

            //商品状态
            $game_stay = $this->getGameStatus($v['is_together'], $v['up_time'], $v['down_time'], $v['foot_time'], $v['game_status']);

            //使用码数据
            $code_date = $this->getBackMoney($v['order_sn'], $v['coupon_unit_price']);
            $content[] = array(
                date('Y-m-d H:i:s', $v['dateline']),
                $this->tradeWay[$v['account_type']],
                "\t".$v['trade_no'],
                'WFT' . (int)$v['order_sn'],
                $v['shop_name'],
                $v['coupon_name'],
                $v['coupon_unit_price'],
                $v['buy_number'],
                //$v['real_pay'] + $v['account_money'],
                bcadd($v['real_pay'], $v['balance_money'], 2),
                $v['voucher'],
                $v['use_number'],
                $v['use_number'] * $v['coupon_unit_price'],
                $code_date['wait'],
                $code_date['yes'],
                $v['account_money']? $v['account_money'] : $v['coupon_unit_price'],
                $code_date['account_need_num'],
                $code_date['account_have_num'] * ($v['account_money']? $v['account_money'] : $v['coupon_unit_price']),
                $v['real_pay'] -  $code_date['account_have_num'] * ($v['account_money'] ? $v['account_money'] : $v['coupon_unit_price']),
                $v['bank_name'],
                $v['bank_card'],
                $game_stay,
                $v['account'],
                $v['username'],
                $v['phone'],
                $v['user_id'],
                ($v['order_type'] == 2) ? $v['game_dizhi'] : '',
                ($v['order_type'] == 2) ? $v['game_taoxi'] : '',
                ($v['order_type'] == 2) ? date('Y-m-d H:i:s', $v['game_start']) : '',
                ($v['order_type'] == 2) ? date('Y-m-d H:i:s', $v['game_end']) : '',
                $code_date['use_time'] ? date('Y-m-d H:i:s',  $code_date['use_time']) : '',
                $code_date['account_time'] ? date('Y-m-d H:i:s',  $code_date['account_time']) : '',
                ($v['order_type'] == 2) ? '商品' : '卡券',
                $v['coupon_id'],
                ($v['pay_status'] > 1) ? $code_date['order_stu'] : '未付款',
                ($v['refund_time'] > time()) ? '可退款' : '不可退款',
                $v['admin_name'],
            );
        }


        $out->out($file_name, $head, $content);
        exit;
    }

    //订单结算列表 导出 条件
    private function getWhereQuery() {
        /**
         * 查询条件
         * ①商品相关 => 商品名称 商品id 商品状态 商家名
         * ③用户相关 => 用户名  用户手机号
         * ④其它 审核 订单状态 支付方式订单号 验证码状态
         */

        $where = 'play_order_info.order_status = 1';

        $good_name = $this->getQuery('good_name', null);
        $good_id = (int)$this->getQuery('good_id', 0);
        $market_name = $this->getQuery('market_name', NULL);
        $admin_name = $this->getQuery('admin_name', NULL);

        if ($admin_name) {//经办人
            $where = $where. " AND play_admin.admin_name like '%".$admin_name."%'";
        }

        //商品名称
        if ($good_name) {
            $where = $where. " AND play_order_info.coupon_name like '%".$good_name."%'";
        }

        //商品id
        if ($good_id) {
            $where = $where. " AND play_order_info.coupon_id = ". $good_id;
        }

        //商家名
        if ($market_name) {
            $where = $where. " AND play_order_info.shop_name like '%".$market_name."%'";
        }

        $user_name = $this->getQuery('user_name', null);
        $user_phone = $this->getQuery('user_phone', null);

        //用户名称
        if ($user_name) {
            $where = $where. " AND play_order_info.username like '%".$user_name."%'";
        }

        //用户手机
        if ($user_phone) {
            $where = $where. " AND play_order_info.buy_phone like '%".$user_phone."%'";
        }

        $order_id = $this->getQuery('order_id', 0);
        $code_number = $this->getQuery('code_number', 0);
        $order_status =  $this->getQuery('order_status', 0);
        $code_status = $this->getQuery('code_status', 0);
        $check_status = $this->getQuery('check_status', 0);
        $trade_way =  $this->getQuery('trade_way', 0);

        //订单id
        if ($order_id) {
            $where = $where. " AND play_order_info.order_sn = ". $order_id;
        }

        //使用码
        if ($code_number) {
            $where = $where. " AND play_coupon_code.id like '%". substr($code_number, 0, -7)."%'";
        }

        //支付方式 'weixin','union','other','jsapi','nopay','alipay'
        if ($trade_way) {
            if ($trade_way == 1) {
                $where = $where. " AND play_order_info.account_type = 'alipay'";
            } elseif ($trade_way == 2) {
                $where = $where. " AND play_order_info.account_type = 'union'";
            } elseif ($trade_way == 3) {
                $where = $where. " AND play_order_info.account_type = 'jsapi'";
            } elseif ($trade_way == 5) {//微信SDK
                $where = $where. " AND play_order_info.account_type = 'weixin'";
            } elseif ($trade_way == 6) {//余额
                $where = $where. " AND play_order_info.account_type = 'account'";
            } elseif ($trade_way == 7) {//新微信网页
                $where = $where. " AND play_order_info.account_type = 'new_jsapi'";
            }
        }

        //使用码状态
        if ($code_status) {
            if ($code_status == 1) {//待使用
                $where = $where . " AND play_coupon_code.status = 0";
            } elseif ($code_status == 2) {//已使用
                $where = $where . " AND play_coupon_code.status = 1";
            } elseif ($code_status == 3) {//已退款
                $where = $where . " AND play_coupon_code.status = 2";
            } elseif ($code_status == 4) {//退款中
                $where = $where . " AND play_coupon_code.status = 3";
            } elseif ($code_status == 5) {//已提交结算
                $where = $where . " AND play_coupon_code.test_status = 3";
            } elseif ($code_status == 6) {//已受理结算
                $where = $where . " AND play_coupon_code.test_status = 4";
            } elseif ($code_status == 7) {//已结算
                $where = $where . " AND play_coupon_code.test_status = 5";
            }
        }

        //订单状态
        if ($order_status) {
            if ($order_status == 1) {//待支付
                $where = $where. " AND play_order_info.pay_status < 2";
            } elseif ($order_status == 2) {//待使用
                $where = $where. " AND (play_order_info.pay_status = 2 || play_order_info.pay_status = 7)";
            } elseif ($order_status == 3) {//退款中
                $where = $where. " AND play_order_info.pay_status = 3";
            } elseif ($order_status == 4) {//已退款
                $where = $where. " AND play_order_info.pay_status = 4";
            } elseif ($order_status == 5) {//已使用
                $where = $where. " AND play_order_info.pay_status = 5";
            }
        }

        //使用码状态
        if ($check_status) {
            $where = $where . " AND play_coupon_code.check_status = {$check_status}";
        }

        //商品状态 未开始 在售 停止售卖 停止使用
        $good_status =  $this->getQuery('good_status', 0);
        if ($good_status) {
            $where = $where. " AND play_organizer_game.is_together = 1";
            if ($good_status == 1) { //未开始
                $where = $where. " AND play_organizer_game.status = 1 && play_organizer_game.up_time > ". time();
            } elseif ($good_status == 2) {// 在售卖
                $where = $where. " AND play_organizer_game.status = 1 && play_organizer_game.up_time < ". time(). " && play_organizer_game.down_time > ". time();
            } elseif ($good_status == 3) {// 停止售卖
                $where = $where. " AND play_organizer_game.status = 1 && play_organizer_game.foot_time > ". time(). " && play_organizer_game.down_time < ". time();
            } elseif ($good_status == 4) {// 停止使用
                $where = $where. " AND play_organizer_game.status = 1 && play_organizer_game.foot_time < ". time(). " && play_organizer_game.down_time < ". time();
            }
        }

        return $where;
    }

    //提交结算
    public function giveAccountAction() {

        $type = (int)$this->getQuery('type', 0);

        if (!in_array($type, array(1, 2))) {
            return $this->_Goto('非法操作');
        }

        if ($type == 1) { //单个
            $id = $this->getQuery('id');
            $where = "play_coupon_code.id = {$id}";
        }

        if ($type == 2) { //单页面批量操作
            $id = trim($this->getQuery('id'), ',');

            $where = "play_coupon_code.id IN ($id)";
        }

        //status  1已使用,2已退款, check_status 2 已审批
        $up_sql = "SELECT
	play_coupon_code.id,
	play_coupon_code.password,
	play_order_info.order_sn
FROM
	play_coupon_code
LEFT JOIN play_order_info ON  play_coupon_code.order_sn = play_order_info.order_sn
WHERE
	 $where AND play_coupon_code.check_status = 2 AND play_coupon_code.test_status < 3 AND (play_coupon_code.status = 1 OR play_coupon_code.status = 2)";

        $up_data = $this->query($up_sql);

        if ($up_data->count() > 2000) {
            return $this->_Goto('要处理的数据太多了');
        }

        if ($up_data->count() < 1) {
            return $this->_Goto('没有符合的数据去处理');
        }

        $common_arr = $this->_getConfig();
        $pdo = new \PDO($common_arr['db']['dsn'], $common_arr['db']['username'], $common_arr['db']['password'], $common_arr['db']['driver_options']);

        $pdo->beginTransaction();
        //更新
        $sql = "UPDATE play_coupon_code
LEFT JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
SET play_coupon_code.test_status = 3
WHERE
  $where AND play_coupon_code.check_status = 2 AND play_coupon_code.test_status < 3 AND (play_coupon_code.status = 1 OR play_coupon_code.status = 2)";
        $res = $pdo->exec($sql);

        if (!$res) {
            $pdo->rollBack();
            return $this->_Goto('没有符合的数据处理');
        }

        //插入更新记录记录
        $timer = time();
        $i = 0;
        $insert_sql = "INSERT play_order_action (`action_user`, `order_id`, `play_status`, `action_note`, `dateline`, `action_user_name`) VALUES ";
        foreach ($up_data as $up) {
            $action_note = '使用码'. $up['password'].' 批准结算';
            $action_user_name = '管理员'. $_COOKIE['user'];
            if (!$i) {
                $insert_sql = $insert_sql . "({$_COOKIE['id']}, {$up['order_sn']}, 8, '{$action_note}', {$timer}, '{$action_user_name}')";
            } else {
                $insert_sql = $insert_sql . ", ({$_COOKIE['id']}, {$up['order_sn']}, 8, '{$action_note}', {$timer}, '{$action_user_name}')";
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

    //受理结算 todo
    public function getAccountAction() {

        $type = (int)$this->getQuery('type', 0);

        if (!in_array($type, array(1, 2))) {
            return $this->_Goto('非法操作');
        }

        if ($type == 1) { //单个
            $id = $this->getQuery('id');
            $where = "play_coupon_code.id = {$id}";
        }

        if ($type == 2) { //单页面批量操作
            $id = trim($this->getQuery('id'), ',');
            $where = "play_coupon_code.id IN ($id)";
        }

        $up_sql = "SELECT
	play_coupon_code.id,
	play_coupon_code.password,
	play_order_info.order_sn
FROM
	play_coupon_code
LEFT JOIN play_order_info ON  play_coupon_code.order_sn = play_order_info.order_sn
WHERE
	 $where AND play_coupon_code.test_status = 3";

        $up_data = $this->query($up_sql);

        if ($up_data->count() > 2000) {
            return  $this->_Goto('要处理的数据太多了');
        }

        if ($up_data->count() < 1) {
            return  $this->_Goto('没有符合的数据去处理');
        }

        $common_arr = $this->_getConfig();
        $pdo = new \PDO($common_arr['db']['dsn'], $common_arr['db']['username'], $common_arr['db']['password'], $common_arr['db']['driver_options']);

        $pdo->beginTransaction();
        //更新
        $sql = "UPDATE play_coupon_code
LEFT JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
SET play_coupon_code.test_status = 4
WHERE
  $where AND play_coupon_code.test_status = 3";
        $res = $pdo->exec($sql);

        if (!$res) {
            $pdo->rollBack();
            return $this->_Goto('没有符合的数据处理');
        }

        //插入更新记录记录
        $timer = time();
        $i = 0;
        $insert_sql = "INSERT play_order_action (`action_user`, `order_id`, `play_status`, `action_note`, `dateline`, `action_user_name`) VALUES ";
        foreach ($up_data as $up) {
            $action_note = '使用码'. $up['password'].' 受理结算';
            $action_user_name = '管理员'. $_COOKIE['user'];
            if (!$i) {
                $insert_sql = $insert_sql . "({$_COOKIE['id']}, {$up['order_sn']}, 9, '{$action_note}', {$timer}, '{$action_user_name}')";
            } else {
                $insert_sql = $insert_sql . ", ({$_COOKIE['id']}, {$up['order_sn']}, 9, '{$action_note}', {$timer}, '{$action_user_name}')";
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

    //受理结算 todo
    public function accountAction() {
        $type = (int)$this->getQuery('type', 0);

        if (!in_array($type, array(1, 2))) {
            return $this->_Goto('非法操作');
        }

        if ($type == 1) { //单个
            $id = $this->getQuery('id');
            $where = "play_coupon_code.id = {$id}";
        }

        if ($type == 2) { //单页面批量操作
            $id = trim($this->getQuery('id'), ',');
            $where = "play_coupon_code.id IN ($id)";
        }


        $up_sql = "SELECT
	play_coupon_code.id,
	play_coupon_code.password,
	play_order_info.order_sn
FROM
	play_coupon_code
LEFT JOIN play_order_info ON  play_coupon_code.order_sn = play_order_info.order_sn
WHERE
	 $where AND play_coupon_code.test_status = 4";

        $up_data = $this->query($up_sql);

        if ($up_data->count() > 2000) {
            return  $this->_Goto('要处理的数据太多了');
        }

        if ($up_data->count() < 1) {
            return  $this->_Goto('没有符合的数据去处理');
        }

        $common_arr = $this->_getConfig();
        $pdo = new \PDO($common_arr['db']['dsn'], $common_arr['db']['username'], $common_arr['db']['password'], $common_arr['db']['driver_options']);

        $pdo->beginTransaction();
        //更新
        $sql = "UPDATE play_coupon_code
LEFT JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
SET play_coupon_code.test_status = 5
WHERE
  $where AND play_coupon_code.test_status = 4";
        $res = $pdo->exec($sql);

        if (!$res) {
            $pdo->rollBack();
            return $this->_Goto('没有符合的数据处理');
        }

        //插入更新记录记录
        $timer = time();
        $i = 0;
        $insert_sql = "INSERT play_order_action (`action_user`, `order_id`, `play_status`, `action_note`, `dateline`, `action_user_name`) VALUES ";
        foreach ($up_data as $up) {
            $action_note = '使用码'. $up['password'].' 结算成功_批量';
            $action_user_name = '管理员'. $_COOKIE['user'];
            if (!$i) {
                $insert_sql = $insert_sql . "({$_COOKIE['id']}, {$up['order_sn']}, 10, '{$action_note}', {$timer}, '{$action_user_name}')";
            } else {
                $insert_sql = $insert_sql . ", ({$_COOKIE['id']}, {$up['order_sn']}, 10, '{$action_note}', {$timer}, '{$action_user_name}')";
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

}
