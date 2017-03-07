<?php

namespace Admin\Controller;

use Deyi\BaseController;
use Deyi\GetCacheData\CityCache;
use Deyi\JsonResponse;

use Deyi\OrderAction\OrderBack;
use Deyi\OrderAction\OrderInfo;
use Deyi\Paginator;
use Deyi\SendMessage;
use Deyi\Account\Account;
use Deyi\toGBK;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Deyi\OutPut;

class OrderController extends BasisController
{
    use JsonResponse;
    use OrderBack;
//    //use BaseController;

    private $tradeWay = array(
        'alipay' => '支付宝',
        'union' => '银联',
        'weixin' => '微信',
        'jsapi' => '旧微信网页',
        'account' => '账户',
        'new_jsapi' => '新微信网页',
        'nopay' => '未付款',
    );

    //订单详情
    public function infoAction() {

        $order_sn = (int)$this->getQuery('order_sn');
        $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn, 'order_status' => 1, 'pay_status > ?' => 1, 'order_type' => 2));

        if (!$orderData) {
            return $this->_Goto('该订单不存在');
        }

        $codeData = $this->_getPlayCouponCodeTable()->fetchAll(array('order_sn' => $order_sn));
        $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $orderData->coupon_id));

        $gameInfoData = $this->_getPlayOrderInfoGameTable()->get(array('order_sn' => $order_sn));
        $goodInfoData = $this->_getPlayGameInfoTable()->get(array('id' => $orderData->bid));
        $useOrganizer = $this->_getPlayCodeUsedTable()->get(array('good_id' => $orderData->coupon_id, 'good_info_id' => $gameInfoData->game_info_id))->organizer_name;
        $insureData = $this->_getPlayOrderInsureTable()->get(array('order_sn' => $order_sn));
        //退款到原支付账号
        $back_temp_sql = "SELECT
play_order_back_tmp.dateline,
play_order_back_tmp.last_dateline,
play_order_back_tmp.status,
play_coupon_code.id,
play_coupon_code.password,
play_coupon_code.back_money,
play_order_info.username,
play_order_info.user_id
FROM
	play_coupon_code
LEFT JOIN play_order_info ON  play_coupon_code.order_sn = play_order_info.order_sn
LEFT JOIN play_order_back_tmp ON  play_order_back_tmp.code_id = play_coupon_code.id
WHERE
	 play_order_info.order_sn = {$order_sn}  AND play_order_back_tmp.status >= 2";
        $back_temp_data = $this->query($back_temp_sql);

        //智游宝
        $zyb_sql = "SELECT * FROM play_zyb_info WHERE play_zyb_info.order_sn = {$order_sn}";
        $zybData = $this->query($zyb_sql);

        //现金券
        $crashData = NULL;
        if ($orderData->voucher_id) {
            $crashData = $this->_getCashCouponUserTable()->get(array('id' => $orderData->voucher_id));
        }

        //返还数据
        $backData = array(
            'back_crash' => NULL, //奖励的现金券 购买 使用 评论
            'rebate' => NULL, //返利
            'integral' => NULL, //奖励的积分 只有使用时才会有积分
        );

        //购买 使用
        $crashBackDataA = $this->_getCashCouponUserTable()->fetchAll(array('get_type IN (5,11)', 'uid' => $orderData->user_id, 'get_object_id' => $order_sn));

        //评论
        $crashBackDataB = $this->_getCashCouponUserTable()->fetchAll(array('get_type' => 2, 'uid' => $orderData->user_id, 'get_order_id' => $order_sn));

        foreach ($crashBackDataA AS $crashA) {
            $backData['back_crash'] = $backData['back_crash'] + $crashA->price;
        }

        foreach ($crashBackDataB AS $crashB) {
            $backData['back_crash'] = $backData['back_crash'] + $crashB->price;
        }

        $rebateData = $this->_getPlayAccountLogTable()->fetchAll(array('action_type' => 1, 'action_type_id IN (5, 6, 10)', 'uid' => $orderData->user_id, 'object_id' => $order_sn));

        foreach ($rebateData as $rebate) {
            $backData['rebate'] = $backData['rebate'] + $rebate->flow_money;
        }

        $integralData = $this->_getPlayIntegralTable()->get(array('type' => 23, 'object_id' => $order_sn, 'uid' => $orderData->user_id));
        $backData['integral'] = $integralData ? $integralData->total_score : NULL;

        //驳回退款 + 操作记录
        $sql_back = "SELECT
	play_order_action.*,
	play_coupon_code.`status` AS code_status,
	play_coupon_code.`force`
FROM
	play_order_action
LEFT JOIN play_coupon_code ON play_coupon_code.id = play_order_action.code_id
WHERE
	play_order_action.order_id = {$order_sn}";

        $actionData = array();
        $map = array('right' => array(), 'middle' => array(), 'left' => array());
        $resBack = $this->_getAdapter()->query($sql_back, array());

        foreach (array_reverse($resBack->toArray()) AS $v) {
            $flag = 0;
            if ($v['play_status'] == 3 && $v['code_status'] == 3 && $v['force'] == 0) {
                if (!in_array($v['code_id'], $map['right'])) {
                    array_push($map['right'], $v['code_id']);
                    $flag = 1;
                }
            }

            if ($v['play_status'] == 7 && $v['force'] == 2) {
                if (!in_array($v['code_id'], $map['middle'])) {
                    array_push($map['middle'], $v['code_id']);
                    $flag = 2;
                }
            }

            if ($v['play_status'] == 14 && $v['force'] == 2) {
                if (!in_array($v['code_id'], $map['left'])) {
                    array_push($map['left'], $v['code_id']);
                    $flag = 3;
                }
            }
            $actionData[] = array(
                'dateline' => $v['dateline'],
                'action_note' => $v['action_note'],
                'play_status' => $v['play_status'],
                'action_user_name' => $v['action_user_name'],
                'code_id' => $v['code_id'],
                'order_id' => $v['order_id'],
                'back_flag' => $flag,
            );
        }

        return array(
            'orderData' => $orderData,
            'codeData' => $codeData,
            'goodData' => $goodData,
            'actionData' => array_reverse($actionData),
            'gameInfo' => $gameInfoData,
            'code_status' => $this->_getConfig()['coupon_code_status'],
            'useOrganizer' => $useOrganizer,
            'backTemp' => $back_temp_data,
            'insureData' => $insureData,
            'zybData' => $zybData,
            'crashData' => $crashData,
            'backData' => $backData,
            'goodInfoData' => $goodInfoData,
        );
    }

    //单个商品订单
    public function goodOrderAction() {

        $id = (int)$this->getQuery('good_id', 0);
        $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $id));

        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;
        $order = "play_order_info.order_sn DESC";

        $good_id = $id;
        $user_name = $this->getQuery('user_name', null);
        $user_phone = $this->getQuery('user_phone', null);
        $buy_start = $this->getQuery('buy_start', null);
        $buy_end = $this->getQuery('buy_end', null);
        $check_start = $this->getQuery('check_start', null);
        $check_end = $this->getQuery('check_end', null);

        $where = "play_order_info.order_status = 1 AND play_order_info.coupon_id = ". $good_id;

        //用户名称
        if ($user_name) {
            $where = $where. " AND play_order_info.username like '%".$user_name."%'";
        }

        //用户手机
        if ($user_phone) {
            $where = $where. " AND play_order_info.buy_phone like '%".$user_phone."%'";
        }

        if ($buy_start && $buy_end && strtotime($buy_start) > strtotime($buy_end)) {
            return $this->_Goto('购买时间出错');
        }

        if ($check_start && $check_end && strtotime($check_start) > strtotime($check_end)) {
            return $this->_Goto('验证时间出错');
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

        $sql = "SELECT
	play_coupon_code.*,
	play_order_info.buy_phone,
	play_order_info.coupon_name,
	play_order_info.dateline,
	play_order_info.username,
	play_order_info.pay_status,
	play_order_info.coupon_unit_price,
    play_game_info.price_name
FROM
	play_coupon_code
LEFT JOIN play_order_info ON  play_coupon_code.order_sn = play_order_info.order_sn
LEFT JOIN play_order_info_game ON play_order_info_game.order_sn = play_order_info.order_sn
LEFT JOIN play_game_info ON play_game_info.id = play_order_info_game.game_info_id AND play_order_info.order_type = '2'
WHERE
	 $where
ORDER BY
	$order";
        $sql_list = $sql." LIMIT
{$start}, {$pageSum}";
        $data = $this->query($sql_list);
        $count = $this->query($sql)->count();

        //创建分页
        $url = '/wftadlogin/order/goodOrder';
        $paging = new Paginator($page, $count, $pageSum, $url);

        $backData =  $this->query($sql);

        $count = array(
            'sale_num' => $count,
            'sale_money' => 0,
            'back_num' => 0,
            'back_money' => 0,
            'out_num' => $goodData->ticket_num - $goodData->buy_num,
        );

        foreach($backData as $vim) {
            if ($vim['pay_status'] > 1) {
                $count['sale_money'] = $count['sale_money'] + $vim['coupon_unit_price'];
            }

            if ($vim['status'] == 2 || $vim['status'] == 3) {
                $count['back_num'] = $count['back_num'] + 1;
                $count['back_money'] = $count['back_money'] +  ($vim['back_money'] ? $vim['back_money']: $vim['coupon_unit_price']);
            }
        }

        return array(
            'goodData' => $goodData,
            'data' => $data,
            'pageData' => $paging->getHtml(),
            'code_status' => $this->_getConfig()['coupon_code_status'],
            'count' => $count,
        );
    }

    //导出数据 => object order
    public function outaccountAction()
    {
        $where = $this->getExcelWhere();
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
    play_order_info.voucher,
	play_order_info.account_type,
	play_order_info.account,
	play_order_info.order_type,
	play_order_info.coupon_unit_price,
	play_order_info.buy_number,
    play_order_info.use_number AS total_use_number,
	SUM(if(play_coupon_code.status = 1, 1, 0)) AS use_number,
    SUM(if(play_coupon_code.force = 3, 1, 0)) AS special_use_number,
	play_order_info.phone,
    play_order_info.order_city,
	play_order_info.user_id,
    play_order_info.real_pay,
    play_order_info.account_money as balance_money,
	play_game_info.shop_name AS game_dizhi,
	play_game_info.price_name AS game_taoxi,
	play_game_info.start_time AS game_start,
	play_game_info.end_time AS game_end,
	play_game_info.insure_num_per_order,
	play_game_info.insure_price,
	play_game_info.account_money,
	play_organizer_game.is_together,
	play_game_info.up_time,
	play_game_info.down_time,
	play_game_info.refund_time,
	play_organizer_game.end_time,
    play_organizer_game.foot_time,
	play_organizer_game.status as game_status,
	play_admin.admin_name,
	play_organizer_account.bank_user,
	play_organizer_account.bank_name,
	play_organizer_account.bank_card,
	play_organizer_account.bank_address,
	play_order_otherdata.message as order_note
FROM
	play_order_info
LEFT JOIN play_coupon_code ON play_coupon_code.order_sn = play_order_info.order_sn
LEFT JOIN play_game_info ON play_game_info.id = play_order_info.bid AND play_order_info.order_type = '2'
LEFT JOIN play_organizer_game ON play_order_info.coupon_id = play_organizer_game.id AND play_order_info.order_type = '2'
LEFT JOIN play_contracts ON play_contracts.id = play_organizer_game.contract_id
LEFT JOIN play_admin ON play_admin.id = play_contracts.business_id
LEFT JOIN play_organizer_account ON play_organizer_account.organizer_id = play_order_info.shop_id
LEFT JOIN play_order_otherdata ON  play_order_info.order_sn=play_order_otherdata.order_sn
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
LEFT JOIN play_organizer_game ON play_order_info.coupon_id = play_organizer_game.id AND play_order_info.order_type = '2'
LEFT JOIN play_contracts ON play_contracts.id = play_organizer_game.contract_id
LEFT JOIN play_admin ON play_admin.id = play_contracts.business_id
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

        if ($_COOKIE['group'] == 4) {
            $head = array(
                '交易时间',
                '交易渠道',
                '交易号',
                '城市',
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
                '商家开户人',
                '商家开户行',
                '商家开户支行',
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
                '订单备注',
                '保险人数',
                '保险金额',
            );
        } else {
            $head = array(
                '交易时间',
                '交易渠道',
                '交易号',
                '城市',
                '商品订单号',
                '商家名称',
                '商品名称',
                '单价',
                '购买数量',
                '购买金额',
                '代金券金额',
                '总已使用数',
                '条件下特殊退款数',
                '已使用数',
                '已使用金额',
                '等待退款金额',
                '已退款金额',
                '结算单价',
                '需结算数量',
                '已结算金额',
                '收益',
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
                '经办人',
                '订单备注',
                '保险人数',
                '保险金额',
            );
        }



        $content = array();
        $city = $this->getAllCities();

        foreach ($data as $v) {

            //商品状态
            $game_stay = $this->getGameStatus($v['is_together'], $v['up_time'], $v['down_time'], $v['foot_time'], $v['game_status']);

            //使用码数据
            $code_date = $this->getBackMoney($v['order_sn'], $v['coupon_unit_price']);


            if ($_COOKIE['group'] == 4) {
                $content[] = array(
                    date('Y-m-d H:i:s', $v['dateline']),
                    $this->tradeWay[$v['account_type']],
                    "\t".$v['trade_no'],
                    $city[$v['order_city']],
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
                    $v['use_number'] ? bcdiv(($v['real_pay'] + $v['balance_money'] - $v['buy_number'] * $v['account_money']) * $v['use_number'], $v['buy_number'] , 2) : 0,
                    $v['bank_user'],
                    $v['bank_name'],
                    $v['bank_address'],
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
                    $v['order_note'],
                    $v['buy_number'] * $v['insure_num_per_order'],
                    $v['buy_number'] * $v['insure_num_per_order'] * $v['insure_price'],
                );
            } else {
                $content[] = array(
                    date('Y-m-d H:i:s', $v['dateline']),
                    $this->tradeWay[$v['account_type']],
                    "\t".$v['trade_no'],
                    $city[$v['order_city']],
                    'WFT' . (int)$v['order_sn'],
                    $v['shop_name'],
                    $v['coupon_name'],
                    $v['coupon_unit_price'],
                    $v['buy_number'],
                    //$v['real_pay'] + $v['account_money'],
                    bcadd($v['real_pay'], $v['balance_money'], 2),
                    $v['voucher'],
                    $v['total_use_number'],
                    $v['special_use_number'],
                    $v['use_number'],
                    $v['use_number'] * $v['coupon_unit_price'],
                    $code_date['wait'],
                    $code_date['yes'],
                    $v['account_money']? $v['account_money'] : $v['coupon_unit_price'],
                    $code_date['account_need_num'],
                    $code_date['account_have_num'] * ($v['account_money']? $v['account_money'] : $v['coupon_unit_price']),
                    $v['use_number'] ? ($v['coupon_unit_price'] - $v['account_money']) * ($v['use_number'] - $v['special_use_number']): 0,
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
                    $v['admin_name'],
                    $v['order_note'],
                    $v['buy_number'] * $v['insure_num_per_order'],
                    $v['buy_number'] * $v['insure_num_per_order'] * $v['insure_price'],
                );
            }
        }


        $out->out($file_name, $head, $content);
        exit;
    }


    private function getExcelWhere() {

        /**
         * 查询条件
         * ①商品相关 => 商品名称 商品id 商品状态 商家名
         * ③用户相关 => 用户名  用户手机号
         * ④其它 审核 订单状态 支付方式订单号 验证码状态
         */

        $where = 'play_order_info.order_status = 1 AND play_order_info.order_type < 3';

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
            $order_id = (int)preg_replace('|[a-zA-Z/]+|','',$order_id);
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
                $where = $where. " AND (play_order_info.pay_status = 4  || play_order_info.pay_status = 6)";
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

        $city = $this->chooseCity(1);
        if($city){
            $where = $where. " AND play_order_info.order_city = '{$city}'";
        }

        return $where;
    }


    private function getGameStatus($is_together, $up_time, $down_time, $foot_time, $game_status) {
        $game_stay = '';

        if ($is_together == 1 && $game_status == 1 && $up_time > time()) { //未开始
            $game_stay = '未开始';
        } elseif ($is_together == 1 && $game_status == 1 && $up_time < time() && $down_time > time()) {// 在售卖
            $game_stay = '在售卖';
        } elseif ($is_together == 1 && $game_status == 1 && $foot_time > time() && $down_time < time()) {// 停止售卖
            $game_stay = '停止售卖';
        } elseif ($is_together == 1 && $game_status == 1 && $foot_time < time() && $down_time < time()) {
            $game_stay = '停止使用';
        } else {
            $game_stay = '停止使用';
        }

        return $game_stay;
    }

    /**
     *  单个订单 退款金额 及 等等退款金额
     * @param $order_sn
     * @param $unit_price
     * @return array
     */
    private function getBackMoney($order_sn, $unit_price) {
        $data = array(
            'wait' => 0, //等待退款金额
            'yes' => 0, // 已退款金额
            'account_need_num' => 0, // 需结算数量
            'account_have_num' => 0, // 已经结算数量
            'account_time' => '', //最近的结算时间
            'use_time' => '', //最近的使用时间
            'order_stu' => '', //订单状态
        );
        $codeData =  $this->_getPlayCouponCodeTable()->fetchAll(array('order_sn' => $order_sn));

        foreach($codeData as $code) {

            //已使用 等待退款
            if ($code->force == 2) {
                $data['wait'] =  $data['wait'] + $code->back_money;
            }

            if ($code->status == 2) {
                $data['yes'] = $data['yes'] + (floatval($code->back_money) ? $code->back_money : $unit_price);
            }

            //已使用 确认退款
            if ($code->force == 3 && $code->status == 1) {
                $data['yes'] = $data['yes'] + $code->back_money;
            }

            if ($code->status == 1 || $code->status == 2) {
                if ($code->test_status == 5) {
                    $data['account_have_num'] = $data['account_have_num'] + 1;
                } elseif($code->test_status>2){
                    $data['account_need_num'] = $data['account_need_num'] + 1;
                }
            }

            if ($code->use_datetime) {
                $data['use_time'] = $code->use_datetime;
            }

            if ($code->account_time && $code->test_status == 5) {
                $data['account_time'] = $code->account_time;
            }

            $data['order_stu'] =  $data['order_stu'] . $this->getOrderStatus($code->check_status, $code->test_status, 2 , $code->status);

        }

        return $data;
    }

    private function getOrderStatus ($check_status, $test_status, $pay_status, $status) {
        $order_stay = '';
        if ($pay_status < 1) {
            $order_stay = '未支付';
        } else {
            if ($check_status == 1) {
                $order_stay = $this->_getConfig()['coupon_code_status'][$status];
            } else {
                if ($test_status == 3) {
                    $order_stay = '已提交结算';
                } elseif ($test_status == 4) {
                    $order_stay = '已受理结算';
                } elseif ($test_status == 5) {
                    $order_stay = '已结算';
                } else {
                    if ($status == 3) {
                        if ($test_status == 0) {
                            $order_stay = '已提交退款';
                        } elseif ($test_status == 1) {
                            $order_stay = '已受理退款';
                        }
                    } elseif($status == 1) {
                        $order_stay = '已使用';
                    } elseif ($status == 2) {
                        $order_stay = '已退款';
                    } elseif ($status == 0)  {
                        $order_stay = '待使用';
                    }
                }
            }
        }

        return $order_stay;
    }

    /**
     * 订单提醒
     */
    public function orderalertAction() {
        $class_orderinfo = new OrderInfo();
        $count = $class_orderinfo->getToReserveOrderCount();

        if ($count) {
            return $this->jsonResponsePage(array('status' => $count, 'message' => '有预约了！     '));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '没有预约订单！       '));
        }
        exit;
    }



    /**
     * anthor wjiang
     * 3.0 订单列表
     */
    public function listAction() {

        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;
        $order = "play_order_info.order_sn DESC";
        $where = $this->getExcelWhere();

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
	play_order_info.back_number,
	play_order_info.buy_phone,
    play_order_info.backing_number,
    play_order_info.use_number,
	play_order_info.coupon_name,
	play_order_info.dateline,
	play_order_info.use_dateline,
	play_order_info.username,
	play_order_info.coupon_id,
    play_order_info.pay_status,
	play_order_info.account_type,
	play_order_info.account,
    play_order_info.shop_name,
    play_order_info.account_money,
    play_order_info.real_pay,
    SUM(if(play_coupon_code.back_money, play_coupon_code.back_money, 0)) AS back_money,
	play_order_info.buy_number,
	play_organizer_game.is_together,
	play_organizer_game.up_time,
	play_organizer_game.down_time,
	play_organizer_game.end_time,
    play_organizer_game.foot_time,
	play_organizer_game.status as game_status,
	play_order_info_game.type_name,
	play_order_info_game.game_info_id,
	play_admin.admin_name,
	play_game_info.insure_num_per_order,
	play_game_info.insure_price,
    play_order_otherdata.message
FROM
	play_order_info
LEFT JOIN play_coupon_code ON play_coupon_code.order_sn = play_order_info.order_sn
INNER JOIN play_game_info ON play_game_info.id = play_order_info.bid
LEFT JOIN play_order_info_game ON play_order_info_game.order_sn = play_order_info.order_sn
LEFT JOIN play_order_otherdata ON play_order_otherdata.order_sn = play_order_info.order_sn
LEFT JOIN play_organizer_game ON play_organizer_game.id = play_order_info.coupon_id
LEFT JOIN play_contracts ON play_contracts.id = play_organizer_game.contract_id
LEFT JOIN play_admin ON play_admin.id = play_contracts.business_id
WHERE
	 $where
GROUP BY play_order_info.order_sn
ORDER BY $order";

        $sql_list = $sql." LIMIT
{$start}, {$pageSum}";

        $res = $this->query($sql_list);

        $sql_count = "SELECT
	count(*) as count_num,
	SUM(real_pay) AS real_pay_money,
	SUM(account_money) AS balance_money
FROM
(SELECT play_order_info.order_sn, play_order_info.real_pay, play_order_info.account_money FROM
	play_order_info
LEFT JOIN play_order_info_game ON play_order_info_game.order_sn = play_order_info.order_sn
LEFT JOIN play_organizer_game ON play_organizer_game.id = play_order_info.coupon_id
LEFT JOIN play_contracts ON play_contracts.id = play_organizer_game.contract_id
LEFT JOIN play_admin ON play_admin.id = play_contracts.business_id
LEFT JOIN play_coupon_code ON play_coupon_code.order_sn = play_order_info.order_sn
WHERE
$where GROUP BY play_order_info.order_sn) AS list_order";

        $data = array();
        $timer = time();

        foreach ($res as $row) {
            if ($row['game_status'] == 1 && $row['up_time'] > $timer)  {
                $good_status = '未开始';
            } elseif ($row['game_status'] == 1 && $row['up_time'] < $timer && $row['down_time'] > $timer) {
                $good_status = '在售卖';
            } elseif ($row['game_status'] == 1 && $row['foot_time'] > $timer && $row['down_time'] < $timer) {
                $good_status = '停止售卖';
            } else {
                $good_status = '已下架';
            }

            $data[] = array(
                'type_name' => $row['type_name'],
                'order_sn' => $row['order_sn'],
                'username' => $row['username'],
                'dateline' => $row['dateline'],
                'buy_phone' => $row['buy_phone'],
                'coupon_id' => $row['coupon_id'],
                'admin_name' => $row['admin_name'],
                'coupon_name' => $row['coupon_name'],
                'buy_number' => $row['buy_number'],
                'message' => $row['message'],
                'real_pay' => $row['real_pay'],
                'account_money' => $row['account_money'],
                'account_type' => $this->tradeWay[$row['account_type']],
                'account' => $row['account'],
                'shop_name' => $row['shop_name'],
                'back_money' => $row['back_money'],
                'good_status' => $good_status,
                'order_status' => OrderInfo::getOrderStatus($row['pay_status'], $row['buy_number'], $row['backing_number'], $row['back_number'], $row['use_number'])['desc'],
                'use_datetime' => $row['use_dateline'] ? date('Y-m-d H:i:s', $row['use_dateline']) : '',
                'insure_people' => $row['insure_num_per_order'] ? $row['buy_number'] * $row['insure_num_per_order'] : '',
                'insure_money' => $row['insure_num_per_order'] ? $row['buy_number'] * $row['insure_price'] *  $row['insure_num_per_order'] : '',
            );
        }

        $countData = $this->query($sql_count)->current();
        $count = $countData['count_num'];

        //创建分页
        $url = '/wftadlogin/order/list';
        $paging = new Paginator($page, $count, $pageSum, $url);


        $sql_count_no_pay = "SELECT
	count(DISTINCT play_order_info.order_sn) as count_num
FROM
	play_order_info
LEFT JOIN play_order_info_game ON play_order_info_game.order_sn = play_order_info.order_sn
LEFT JOIN play_organizer_game ON play_organizer_game.id = play_order_info.coupon_id
LEFT JOIN play_contracts ON play_contracts.id = play_organizer_game.contract_id
LEFT JOIN play_admin ON play_admin.id = play_contracts.business_id
LEFT JOIN play_coupon_code ON play_coupon_code.order_sn = play_order_info.order_sn
WHERE
$where AND play_order_info.pay_status< 2";

        $noPayCountData = $this->query($sql_count_no_pay)->current();
        $noPayNum = $noPayCountData['count_num'];

        $back_money_sql = "SELECT
	 SUM(play_coupon_code.back_money) as back_money
FROM
	play_coupon_code
LEFT JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
LEFT JOIN play_order_info_game ON play_order_info_game.order_sn = play_order_info.order_sn
LEFT JOIN play_organizer_game ON play_organizer_game.id = play_order_info.coupon_id
LEFT JOIN play_contracts ON play_contracts.id = play_organizer_game.contract_id
LEFT JOIN play_admin ON play_admin.id = play_contracts.business_id
WHERE
$where AND (play_coupon_code.status = 2 OR play_coupon_code.status = 3)";

        $backMoneyData = $this->query($back_money_sql)->current();
        $outSum = $backMoneyData['back_money'];
        return array(
            'data' => $data,
            'pageData' => $paging->getHtml(),
            'order_sum' => $count,
            'pay_sum' => $count - $noPayNum,
            'income' => bcadd($countData['real_pay_money'], $countData['balance_money'], 2),
            'outSum' => $outSum ? $outSum : 0,
        );
    }

    public function backMoneyAction() {

        $order_sn = $this->getQuery('order_sn', '');
        $id = $this->getQuery('id', '');

        if (!$order_sn or !$id) {
            return $this->_Goto('请求参数错误');
        }

        $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));
        $codeData = $this->_getPlayCouponCodeTable()->get(array('id' => $id));

        if (!$orderData || !$codeData) {
            return $this->_Goto('出现了异常, 请询问客服');
        }

        $db = $this->_getAdapter();

        $s = $db->query("select * from play_order_back_tmp where order_sn=? AND code_id=?", array($order_sn, $id))->current();

        if (!$s || $s->status != 1) {
            return $this->_Goto('异常');
        }

        //判断用户账号可提现金额 是否大于 退款金额
        $userAccountData = $this->_getPlayAccountTable()->get(array('uid' => $orderData->user_id));

        if (!$userAccountData || $userAccountData->can_back_money < $codeData->back_money) {
            return $this->_Goto('账户中余额不足');
        }

        $account = new Account();

        $chargeStatus = $account->takeCrash($orderData->user_id, $codeData->back_money, $desc = '原路返回支付账户', 2, $order_sn, $orderData->user_id, $orderData->order_city);

        if (!$chargeStatus) {
            return $this->_Goto('失败');
        }

        $res = $db->query('UPDATE play_order_back_tmp SET status=?, dateline = ? WHERE order_sn=? AND code_id=?', array(2, time(), $order_sn, $id))->count();

        if ($res) {
            return $this->_Goto('成功');
        } else {
            return $this->_Goto('失败啊');
        }


    }

}
