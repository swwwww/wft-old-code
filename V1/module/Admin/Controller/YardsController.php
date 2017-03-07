<?php

namespace Admin\Controller;

use Deyi\JsonResponse;
use Deyi\OrderAction\OrderAbnormal;
use Deyi\Paginator;
use library\Service\System\Cache\RedCache;
use Deyi\OutPut;

class YardsController extends BasisController
{
    use JsonResponse;

    public $tradeWay = array(
        'weixin' => '微信',
        'union' => '银联',
        'alipay' => '支付宝',
        'jsapi' => '旧微信网页',
        'nopay' => '未付款',
        'account' => '用户账户',
        'new_jsapi' => '新微信网页',
    );

    //确定退款列表
    public function backListAction()
    {
        $pageSum = (int)$this->getQuery('page_num', 10);
        $page = (int)$this->getQuery('p', 1);
        $start = ($page - 1) * $pageSum;
        $order = "play_order_info.order_sn DESC";

        $whereData = $this->getWhere(2);
        if (!$whereData['status']) {
            return $this->_Goto($whereData['message']);
        }

        $adapter = $this->_getAdapter();
        $where = $whereData['message'];

        $is_doing = $this->query("SELECT play_alipay_refund_log.code_id FROM play_alipay_refund_log WHERE status = 3", array());
        $is_doing_list = array();
        foreach ($is_doing as $doing) {
            $is_doing_list[] = $doing['code_id'];
        }

        if (count($is_doing_list)) {
            $doing_list = trim(implode(',', $is_doing_list), ',');
            $where =  $where. " AND play_excercise_code.id NOT IN ({$doing_list})";
        }

        $sql = "SELECT
	play_excercise_code.*,
	play_order_info.dateline,
    play_order_info.trade_no,
    play_order_info.account_type,
    play_order_info.username,
    play_order_info.user_id,
    play_order_info.coupon_name,
    play_order_action.action_id
FROM
	play_excercise_code
INNER JOIN play_order_info ON  play_order_info.order_sn = play_excercise_code.order_sn
LEFT JOIN play_order_action ON play_order_action.code_id = play_excercise_code.id AND play_order_action.play_status = 102
WHERE
	 $where
GROUP BY play_excercise_code.id
ORDER BY
	$order";

        $sql_list = $sql . " LIMIT
{$start}, {$pageSum}";

        $data = $this->query($sql_list);

        $countData = $adapter->query("SELECT
	COUNT(*) AS count_num,
	COUNT(DISTINCT play_excercise_code.order_sn) AS order_num,
	SUM(if(play_excercise_code.accept_status=2,play_excercise_code.back_money,0)) AS wait_money,
    SUM(if(play_excercise_code.accept_status=3,play_excercise_code.back_money,0)) AS back_money
FROM
	play_excercise_code
LEFT  JOIN  play_order_info ON play_order_info.order_sn = play_excercise_code.order_sn
WHERE $where", array())->current();

        $count = $countData->count_num;

        //创建分页
        $url = '/wftadlogin/yards/backlist';
        $paging = new Paginator($page, $count, $pageSum, $url);

        $counter = array(
            'order_num' => $countData->order_num,
            'wait_money' => $countData->wait_money,
            'back_money' => $countData->back_money,
        );
        return array(
            'data' => $data,
            'pageData' => $paging->getHtml(),
            'tradeWay' => $this->tradeWay,
            'counter' => $counter,
            'code_minute' => RedCache::get('code_draw_back_list') ? json_decode(RedCache::get('code_draw_back_list'), true) : array(),
        );
    }

    //导出
    public function outDataAction() {
        $type = $this->getQuery('activity_type', 1);
        $whereData = $this->getWhere($type);
        if (!$whereData['status']) {
            return $this->_Goto($whereData['message']);
        }

        $where = $whereData['message'];
        $sql_list = "SELECT
    play_order_info.order_sn,
    play_order_info.trade_no,
	play_order_info.coupon_name,
	play_order_info.dateline,
	play_order_info.username,
	play_order_info.account_type,
	play_order_info.account,
    play_order_info.user_id,
    play_order_info.account_money,
    play_order_info.real_pay,
	play_order_info.buy_number,
    play_order_info.order_city,
    SUM(if(play_excercise_code.accept_status = 3, play_excercise_code.back_money, 0)) AS back_money,
    SUM(if(play_excercise_code.accept_status = 2, play_excercise_code.back_money, 0)) AS wait_back,
    MAX(play_excercise_code.back_money_time) AS back_money_time,
    play_order_info.coupon_unit_price,
    play_order_info.voucher,
    play_order_info.phone
FROM
	play_order_info
LEFT JOIN play_excercise_code ON play_excercise_code.order_sn = play_order_info.order_sn
WHERE
	 $where
GROUP BY
    play_order_info.order_sn
ORDER BY
    play_order_info.order_sn DESC";

        $data = $this->query($sql_list);


        if (!$data->count()) {
            return $this->_Goto('0条数据！');
        }

        if ($data->count() > 10000) {
            return $this->_Goto('数据太多了, 请缩小范围');
        }

        $out = new OutPut();
        $file_name = date('Y-m-d H:i:s', time()). '_活动订单列表.csv';
        $city = $this->getAllCities();

        $head = array(
            '交易渠道',
            '城市',
            '交易时间',
            '交易号',
            '订单号',
            '商家名称',
            '活动名称',
            '单价',
            '购买数量',
            '购买金额',
            '代金券金额',
            '等待退款金额',
            '已退款金额',
            '用户名',
            '手机号',
            '用户支付账号',
            '用户id',
            '类别',
            '最近退款时间'
        );

        $content = array();

        foreach ($data as $v) {

            $content[] = array(
                $this->tradeWay[$v['account_type']],
                $city[$v['order_city']],
                date('Y-m-d H:i:s', $v['dateline']),
                "\t".$v['trade_no'],
                'WFT' . (int)$v['order_sn'],
                '玩翻天遛娃学院',
                $v['coupon_name'],
                '',
                $v['buy_number'],
                bcadd($v['real_pay'], $v['account_money'], 2),
                $v['voucher'],
                $v['back_money'],
                $v['wait_back'],
                $v['username'],
                $v['phone'],
                $v['account'],
                $v['user_id'],
                '活动',
                $v['back_money_time'] ? date('Y-m-d H:i:s', $v['back_money_time']) : '',

            );
        }

        $out->out($file_name, $head, $content);
        exit;

    }

    // 退款 导出共用条件
    private function getWhere($type=1) {

        $where = 'play_order_info.order_status = 1 AND play_order_info.pay_status > 1 AND play_order_info.order_type = 3';

        if ($type != 2) {
            return array('status' => 0, 'message' => '该类型不存在');
        }

        $where = $where. " AND play_order_info.approve_status = 2";

        //活动名称
        $activity_name = $this->getQuery('activity_name', null);
        if ($activity_name) {
            $where = $where . " AND play_order_info.coupon_name like '%" . $activity_name . "%'";
        }

        //活动id
        $activity_id = (int)$this->getQuery('activity_id', null);
        if ($activity_id) {
            $where = $where. " AND play_order_info.bid = ". $activity_id;
        }

        //订单id
        $order_id = $this->getQuery('order_id', 0);
        if ($order_id) {
            $order_id = (int)preg_replace('|[a-zA-Z/]+|','',$order_id);
            $where = $where. " AND play_order_info.order_sn = ". $order_id;
        }

        //用户名称
        $user_name = $this->getQuery('user_name', null);
        if ($user_name) {
            $where = $where. " AND play_order_info.username like '%".$user_name."%'";
        }

        //用户手机
        $user_phone = $this->getQuery('user_phone', null);
        if ($user_phone) {
            $where = $where. " AND play_order_info.buy_phone like '%".$user_phone."%'";
        }

        //用户id
        $user_id = (int)$this->getQuery('user_id', null);
        if ($user_id) {
            $where = $where. " AND play_order_info.user_id = {$user_id}";
        }

        //支付方式 'weixin','union','other','jsapi','nopay','alipay', 'weixinsdk'
        $trade_way = (int)$this->getQuery('trade_way', 0);
        if ($trade_way) {
            if ($trade_way == 1) {
                $where = $where. " AND play_order_info.account_type = 'alipay'";
            } elseif ($trade_way == 2) {
                $where = $where. " AND play_order_info.account_type = 'union'";
            } elseif ($trade_way == 3) {
                $where = $where. " AND play_order_info.account_type = 'new_jsapi'";
            } elseif ($trade_way == 4) {
                $where = $where. " AND play_order_info.account_type = 'jsapi'";
            } elseif ($trade_way == 5) {
                $where = $where. " AND play_order_info.account_type = 'weixin'";
            } elseif ($trade_way == 6) {
                $where = $where. " AND play_order_info.account_type = 'account'";
            }
        }

        //购买时间
        $buy_start = $this->getQuery('buy_start', null);
        $buy_end = $this->getQuery('buy_end', null);

        if ($buy_start && $buy_end && strtotime($buy_start) > strtotime($buy_end)) {
            return array('status' => 0, 'message' => '购买时间出错');
        }

        if ($buy_start) {
            $buy_start = strtotime($buy_start);
            $where = $where. " AND play_order_info.dateline > ".$buy_start;
        }

        if ($buy_end) {
            $buy_end = strtotime($buy_end) + 86400;
            $where = $where. " AND play_order_info.dateline < ".$buy_end;
        }

        //受理退款时间
        $accept_start = $this->getQuery('accept_start', null);
        $accept_end = $this->getQuery('accept_end', null);

        if ($accept_start && $accept_end && strtotime($accept_start) > strtotime($accept_end)) {
            return array('status' => 0, 'message' => '受理退款时间出错');
        }

        if ($accept_start) {
            $accept_start = strtotime($accept_start);
            $where = $where. " AND play_excercise_code.accept_time > ".$accept_start;
        }

        if ($accept_end) {
            $accept_end = strtotime($accept_end) + 86400;
            $where = $where. " AND play_excercise_code.accept_time < ".$accept_end;
        }

        //退款时间
        $back_start = $this->getQuery('back_start', null);
        $back_end = $this->getQuery('back_end', null);

        if ($back_start && $back_end && strtotime($back_start) > strtotime($back_end)) {
            return array('status' => 0, 'message' => '提交退款时间出错');
        }

        if ($back_start) {
            $back_start = strtotime($back_start);
            $where = $where . " AND play_excercise_code.back_time > " . $back_start;
        }

        if ($back_end) {
            $back_end = strtotime($back_end) + 86400;
            $where = $where . " AND play_excercise_code.back_time < " . $back_end;
        }

        //确定退款时间
        $back_true_end = $this->getQuery('back_true_end', null); //确定退款时间
        $back_true_start = $this->getQuery('back_true_start', null);

        if ($back_true_start) {
            $back_true_start = strtotime($back_true_start);
            $where = $where. " AND play_excercise_code.back_money_time > ". $back_true_start;

        }

        if ($back_true_end) {
            $back_true_end = strtotime($back_true_end) + 86400;
            $where = $where. " AND play_excercise_code.back_money_time < ". $back_true_end;
        }

        //使用码状态
        $code_status = (int)$this->getQuery('code_status', 2);

        if (in_array($code_status, array(2, 3))) {
            $where = $where . " AND play_excercise_code.accept_status = {$code_status}";
        } else {
            $where = $where . " AND (play_excercise_code.accept_status = 2 OR play_excercise_code.accept_status = 3)";
        }


        $city = $this->chooseCity(1);

        if ($city) {
            $where = $where . " AND play_order_info.order_city = '{$city}'";
        }

        return array('status' => 1, 'message' => $where);

    }

    //退款驳回
    public function abnormalAction()
    {

        $type = (int)$this->getQuery('type', 0);
        $object_type = (int)$this->getQuery('object_type', 0);
        $code_id =  (int)$this->getQuery('code_id', 0);
        $order_sn =  (int)$this->getQuery('order_sn', 0);

        $abnormal = new OrderAbnormal();

        if ($object_type == 1) {
            $result = $abnormal->rollBackIng($code_id, $order_sn, $type);
        } elseif ($object_type == 2) {
            $result = $abnormal->rollBacked($code_id, $order_sn, $type);
        } else {
            $result = array('status' => 0, 'message' => '非法操作');
        }

        return $this->_Goto($result['message']);

    }

}
