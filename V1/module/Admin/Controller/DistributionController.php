<?php

namespace Admin\Controller;

use Deyi\GetCacheData\CityCache;
use Deyi\JsonResponse;
use Deyi\Paginator;
use library\Service\System\Cache\RedCache;
use Deyi\Seller\Seller;
use Zend\View\Model\ViewModel;

class DistributionController extends BasisController
{
    use JsonResponse;

    //销售员列表
    public function sellerAction()
    {

        $page = (int)$this->getQuery('p', 0);
        $pageNum = (int)$this->getQuery('page_num', 10);
        $page = $page < 1 ? 1 : $page;
        $pageNum = $pageNum < 1 ? 10 : $pageNum;
        $start = ($page - 1) * $pageNum;

        $where = array(
            'is_seller >= ?' => 1,
        );

        $userName = $this->getQuery('username', '');
        $uid = (int)$this->getQuery('uid', 0);
        $phone = $this->getQuery('phone', '');

        if ($uid) {
            $where['uid'] = $uid;
        }

        if ($userName) {
            $where['username like ?'] = '%'.$userName.'%';
        }

        if ($phone) {
            $where['phone like ?'] = '%'.$phone.'%';
        }

        $data = $this->_getPlayUserTable()->fetchLimit($start, $pageNum, array(), $where, array());

        $res = array();
        $Seller = new Seller();
        $city = CityCache::getCities();
        foreach ($data as $value) {
            $Account = $Seller->getSellerAccount($value['uid']);

            $res[] = array(
                'uid' => $value['uid'],
                'is_seller' => $value['is_seller'],
                'city' => $city[$value['city']],
                'username' => $value['username'],
                'phone' => $value['phone'],
                'order_number' => $Account['order_number'],
                'total_sell' => bcsub($Account['total_sell'], $Account['total_sell_back'], 2),
                'total_arrived' => $Account['not_arrived_income'] + $Account['have_arrived_income'],
                'account_money' => $Account['account_money'],
            );
        }

        //创建分页
        $url = '/wftadlogin/distribution/seller';

        $count = $this->_getPlayUserTable()->fetchCount($where);
        $pagination = new Paginator($page, $count, $pageNum, $url);

        $vm = new ViewModel(array(
            'data' => $res,
            'pageData' => $pagination->getHtml(),
        ));

        return $vm;

    }

    //提现记录
    public function withdrawAction()
    {

        $page = (int)$this->getQuery('p', 0);
        $pageNum = (int)$this->getQuery('page_num', 10);
        $page = $page < 1 ? 1 : $page;
        $pageNum = $pageNum < 1 ? 10 : $pageNum;
        $start = ($page - 1) * $pageNum;
        $where = 'play_distribution_detail.sell_type = 3';

        $userName = $this->getQuery('username', '');
        $uid = (int)$this->getQuery('uid', 0);
        $check_status = (int)$this->getQuery('check_status', 0);
        $time_start = $this->getQuery('time_start', '');
        $time_end = $this->getQuery('time_end', '');

        if ($uid) {
            $where = $where. ' AND play_distribution_detail.sell_user_id = '. $uid;
        }

        if ($userName) {
            $where = $where. " AND play_user.username like '%". $userName. "%'";
        }

        if ($check_status) {
            $where = $where. ' AND play_distribution_detail.sell_status = '. $check_status;
        }

        if ($time_start) {
            $time_start = strtotime($time_start) + 86400;
            $where = $where. ' AND play_distribution_detail.create_time > '. $time_start;
        }

        if ($time_end) {
            $time_end = strtotime($time_end);
            $where = $where. ' AND play_distribution_detail.create_time < '. $time_end;
        }

        $sql_list = "SELECT
	play_distribution_detail.*
FROM
	play_distribution_detail
INNER JOIN play_user ON play_user.uid = play_distribution_detail.sell_user_id
WHERE $where
ORDER BY play_distribution_detail.id DESC
LIMIT {$start}, {$pageNum}";

        $data = $this->query($sql_list);

        $sql_count = "SELECT
	COUNT(*) AS count_num
FROM
	play_distribution_detail
INNER JOIN play_user ON play_user.uid = play_distribution_detail.sell_user_id
WHERE $where";
        $countData = $this->query($sql_count)->current();
        $count = $countData['count_num'];

        $res = array();
        $Seller = new Seller();
        foreach ($data as $value) {
            $Account = $Seller->getSellerAccount($value['sell_user_id']);
            $res[] = array(
                'id' => $value['id'],
                'uid' => $value['sell_user_id'],
                'create_time' => $value['create_time'],
                'price' => $value['price'],
                'account_money' => $Account['account_money'],
                'status' => $value['sell_status'],
            );
        }

        //创建分页
        $url = '/wftadlogin/distribution/withdraw';
        $pagination = new Paginator($page, $count, $pageNum, $url);

        $vm = new ViewModel(array(
            'data' => $res,
            'pageData' => $pagination->getHtml(),
        ));

        return $vm;
    }

    //订单列表
    public function orderListAction()
    {
        $page = (int)$this->getQuery('p', 0);
        $pageNum = (int)$this->getQuery('page_num', 10);

        $page = $page < 1 ? 1 : $page;
        $pageNum = $pageNum < 1 ? 10 : $pageNum;
        $start = ($page - 1) * $pageNum;

        $where = "play_distribution_detail.sell_type = 2";
        $groupHaving = " GROUP BY play_order_info.order_sn";
        $orderBy = " ORDER BY play_order_info.order_sn DESC";
        $limit = " LIMIT {$start}, {$pageNum}";

        $userName = $this->getQuery('username', '');
        $user_id = (int)$this->getQuery('uid', 0);
        $coupon_id = (int)$this->getQuery('coupon_id', 0);
        $order_id = $this->getQuery('order_id', 0);
        $phone = $this->getQuery('phone', '');
        $coupon_name = $this->getQuery('coupon_name', '');
        $seller_id = (int)$this->getQuery('seller_id', 0);
        $sell_type = (int)$this->getQuery('sell_type', 0);

        if ($user_id) {
            $where = $where. ' AND play_order_info.user_id = '. $user_id;
        }

        if ($order_id) {
            $order_id = (int)preg_replace('|[a-zA-Z/]+|','',$order_id);
            $where = $where. " AND play_order_info.order_sn = ". $order_id;
        }

        if ($userName) {
            $where = $where. " AND play_order_info.username like '%". $userName. "%'";
        }

        if ($coupon_name) {
            $where = $where. " AND play_order_info.coupon_name like '%". $coupon_name. "%'";
        }

        if ($phone) {
            $where = $where. " AND play_order_info.phone like '%". $phone. "%'";
        }

        if ($coupon_id) {
            $where = $where. ' AND play_order_info.coupon_id = '. $coupon_id;
        }

        if ($seller_id) {
            $where = $where. ' AND play_distribution_detail.sell_user_id = '. $seller_id;
        }

        if ($sell_type == 1) {//未到账
            $groupHaving = " GROUP BY play_order_info.order_sn HAVING code_use = 0 AND code_back = 0";
        } elseif ($sell_type == 2) {//已到账
            $groupHaving = " GROUP BY play_order_info.order_sn HAVING code_number = code_use";
        } elseif ($sell_type == 3) {//已扣除
            $groupHaving = " GROUP BY play_order_info.order_sn HAVING code_number = code_back";
        } elseif ($sell_type == 4) {//部分到账
            $groupHaving = " GROUP BY play_order_info.order_sn HAVING code_back = 0 AND code_use > 0 AND code_number > code_use";
        } elseif ($sell_type == 5) {//部分扣除
            $groupHaving = " GROUP BY play_order_info.order_sn HAVING code_back > 0 AND code_number > code_back";
        }

        $sql = "SELECT
	play_order_info.order_sn,
	play_order_info.dateline,
    play_order_info.order_city,
    play_order_info.user_id,
    play_order_info.username,
    play_order_info.phone,
    play_order_info.coupon_name,
    play_order_info.coupon_id,
    play_order_info.real_pay,
    play_order_info.account_money,
    play_order_info.voucher,
    play_distribution_detail.sell_user_id,
	COUNT(play_distribution_detail.id) AS code_number,
	SUM(if(play_distribution_detail.sell_status = 2, 1, 0)) AS code_use,
	SUM(if(play_distribution_detail.sell_status = 3, 1, 0)) AS code_back,
	SUM(if(play_distribution_detail.sell_status = 1 OR play_distribution_detail.sell_status = 2, play_distribution_detail.rebate, 0)) AS seller_rebate
FROM
	play_order_info
INNER JOIN play_distribution_detail ON play_order_info.order_sn = play_distribution_detail.order_id
WHERE $where $groupHaving $orderBy $limit";

        $res = $this->query($sql);
        $count = $this->query("SELECT play_order_info.order_sn,
    COUNT(play_distribution_detail.id) AS code_number,
	SUM(if(play_distribution_detail.sell_status = 2, 1, 0)) AS code_use,
	SUM(if(play_distribution_detail.sell_status = 3, 1, 0)) AS code_back
	FROM play_order_info
INNER JOIN play_distribution_detail ON play_order_info.order_sn = play_distribution_detail.order_id
WHERE $where $groupHaving")->count();

        $data = array();
        $city = CityCache::getCities();
        foreach ($res as $value) {

            $code_sql = "SELECT
	SUM(play_coupon_code.back_money) AS back_money
FROM
	play_order_info
LEFT JOIN play_coupon_code ON play_coupon_code.order_sn = play_order_info.order_sn
WHERE play_order_info.order_sn = ". $value['order_sn']. " GROUP BY play_order_info.order_sn";
            $back_money = $this->query($code_sql)->current()['back_money'];

            $userData = $this->_getPlayUserTable()->get(array('uid' => $value['sell_user_id']));
            $data[] = array(
                'order_sn' => $value['order_sn'],
                'dateline' => $value['dateline'],
                'city' => $city[$value['order_city']],
                'user_id' => $value['user_id'],
                'username' => $value['username'],
                'phone' => $value['phone'],
                'coupon_id' => $value['coupon_id'],
                'coupon_name' => $value['coupon_name'],
                'account_money' => $value['account_money'],
                'real_pay' => $value['real_pay'],
                'voucher' => $value['voucher'],
                'seller' => $userData->username,
                'back_money' => $back_money,
                'seller_rebate' => $value['seller_rebate'],
                'code_number' => $value['code_number'],
                'code_use' => $value['code_use'],
                'code_back' => $value['code_back']
            );
        }

        //创建分页
        $url = '/wftadlogin/distribution/orderList';
        $pagination = new Paginator($page, $count, $pageNum, $url);

        $vm = new ViewModel(array(
            'data' => $data,
            'pageData' => $pagination->getHtml(),
        ));

        return $vm;
    }

    //商品 或者 活动 列表
    public function goodsListAction()
    {

    }

    //统计
    public function statisticsAction()
    {

        $total_sale_sql = "SELECT
	 SUM(play_distribution_detail.price) AS total_sales
FROM
	play_distribution_detail WHERE sell_type = 1";
        $total_back_sql = "SELECT
	 SUM(play_distribution_detail.price) AS total_back
FROM
	play_distribution_detail WHERE sell_type = 2 AND sell_status = 3";

        $total_spread_sql = "SELECT
	 SUM(play_distribution_detail.rebate) AS total_spread
FROM
	play_distribution_detail WHERE sell_type = 2 AND (sell_status = 2 OR sell_status = 1)";

        $total_got_sql = "SELECT
	 SUM(play_distribution_detail.price) AS total_got
FROM
	play_distribution_detail WHERE sell_type = 3 AND sell_status = 2";

        $total_sale = $this->query($total_sale_sql)->current()['total_sales'];
        $total_back = $this->query($total_back_sql)->current()['total_back'];
        $total_spread = $this->query($total_spread_sql)->current()['total_spread'];
        $total_got = $this->query($total_got_sql)->current()['total_got'];

        $totalData = array(
            'total_sale' => bcsub($total_sale, $total_back),
            'total_spread' => $total_spread,
            'total_got' => $total_got,
        );

        $spread = RedCache::fromCacheData("count:spread", function () {
            return $this->getSpread();
        }, 86400);

        $vm = new ViewModel(array(
            'totalData' => $totalData,
            'spread' => $spread,
        ));

        return $vm;

    }

    //统计最近6个月份的推广收益
    private function getSpread()
    {
        $Adapter = $this->_getAdapter();
        $times = array();//时间段 存储每个时间段 字符串
        $now_year = date('Y', time());
        $now_month = date('m', time()) + 1;

        //最近六个月 开始结束时间
        for ($i = 0; $i < 6; $i++) {
            $now_month = $now_month - 1;
            if ($now_month > 0) {
                $top = "{$now_year}-{$now_month}-1";
                $last = "{$now_year}-{$now_month}-" . date("t", strtotime($top));
                $times['start'][] = $top;
                $times['end'][] = $last;
            } else {
                $now_year -= 1;
                $now_month = 12;
                $top = "{$now_year}-{$now_month}-1";
                $last = "{$now_year}-{$now_month}-" . date("t", strtotime($top));
                $times['start'][] = $top;
                $times['end'][] = $last;
            }
        }

        $data = array();

        $count = count($times['start']) - 1;

        for ($k = $count; $k >= 0; $k--) {
            $data['time'][] = date('Y-m', strtotime($times['start'][$k]));
            $data['data'][] = $Adapter->query("SELECT
	 SUM(play_distribution_detail.rebate) AS total_spread
FROM
	play_distribution_detail WHERE sell_type = 2 AND (sell_status = 2 OR sell_status = 1) AND  create_time > ? and create_time < ?", array(strtotime($times['start'][$k]), strtotime($times['end'][$k])))->current()->total_spread;
        }
        return json_encode($data);
    }

    //扣除收益
    public function deductAction()
    {
        $uid = (int)$this->getQuery('uid', 0);
        $Seller = new Seller();
        $isRight = $Seller->isRight($uid);

        if (!$isRight) {
            return $this->_Goto('非法操作');
        }

        $accountData = $Seller->getSellerAccount($uid);

        $vm = new ViewModel(
            array(
                'data' => $accountData,
                'uid' => $uid,
            )
        );
        return $vm;

    }

    //扣钱
    public function ductAction()
    {
        $uid = (int)$this->getPost('uid', 0);
        $ductMoney = floatval($this->getPost('duct_money', 0));
        $reason = trim($this->getPost('duct_reason', ''));

        $Seller = new Seller();
        $result = $Seller->deduct($uid, $ductMoney, $reason);

        if (!$result['status']) {
            return $this->_Goto($result['message']);
        }

        return $this->_Goto('成功', '/wftadlogin/distribution/seller');

    }


    //审批操作
    public function chargeAction()
    {
        $chargeType = (int)$this->getQuery('charge_type', 0);
        $id = (int)$this->getQuery('id', 0);

        if (!in_array($chargeType, array(1, 2))) {
            return $this->_Goto('非法操作');
        }

        $data = $this->_getPlayDistributionDetailTable()->get(array('id' => $id, 'sell_status' => 1));

        if (!$data) {
            return $this->_Goto('非法操作');
        }

        $Seller = new Seller();
        $result = $Seller->charge($id, $chargeType);

        return $this->_Goto($result ? '成功': '失败');

    }

    //取消销售员
    public function sellManAction()
    {
        $manType = (int)$this->getQuery('man_type', 0);
        $uid = (int)$this->getQuery('uid', 0);

        if (!in_array($manType, array(1, 2, 3))) {
            return $this->_Goto('非法操作');
        }

        $userData = $this->_getPlayUserTable()->get(array('uid' => $uid));

        if (!$userData) {
            return $this->_Goto('非法操作');
        }

        if ($manType == 1) {
            if (!$userData->phone) {
                return $this->_Goto('设为销售员需要绑定手机号');
            }
        }

        $Seller = new Seller();
        $result = $Seller->beMan($uid, $manType);

        return $this->_Goto($result ? '成功': '失败');
    }

}
