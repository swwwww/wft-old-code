<?php
/**
 * 现金券控制模块
 * Date: 15-12-9
 * Time: 上午10:57
 */

namespace Admin\Controller;

use Deyi\JsonResponse;
use Deyi\OutPut;
use Deyi\Paginator;
use library\Service\System\Cache\RedCache;
use Zend\Db\Sql\Expression;
use Zend\View\Model\ViewModel;

class CashCouponController extends BasisController
{
    use JsonResponse;

    public function indexAction()
    {
        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;

        $code_status = (int)$this->getQuery('code_status', 0);
        $use_range = $this->getQuery('use_range', '');
        $word = trim($this->getQuery('word', ''));
        $give_start = $this->getQuery('give_start', 0); //发放时间
        $give_end = $this->getQuery('give_end', 0);
        $use_time = $this->getQuery('use_time', 0); //使用时间
        $use_time_end = $this->getQuery('use_time_end', 0);
        $city = $this->getBackCity();

        $where =  "play_cash_coupon.id > 0";

        if ($code_status === 1) {//待发放
            $where = $where. " AND play_cash_coupon.begin_time > ". time(). " AND is_close = 0";
        } elseif ($code_status === 2) {//正在发放
            $where = $where. " AND play_cash_coupon.end_time > ". time(). " AND is_close = 0 AND begin_time < ". time();
        } elseif ($code_status == 3) {//已结束
            $where = $where. " AND play_cash_coupon.end_time < ". time();
        } elseif ($code_status == 4) {//停止发放
            $where = $where. " AND play_cash_coupon.is_close = 1";
        }

        if ($word) {
            $where = $where.  ' AND (play_cash_coupon.title like "%' . $word . '%" or play_cash_coupon.id = ' . (int)$word . ' or play_cash_coupon.diffuse_code = "' . $word . '")';
        }

        if ($use_range) {
            $use_range = intval($use_range) - 1;
            $where = $where. " AND play_cash_coupon.range = ".$use_range;
        }

        if ($city) {
            $where = $where. " AND play_cash_coupon.city = '{$city}'";
        }

        if ($give_start) {
            $give_start = strtotime($give_start);
            $where = $where. " AND play_cashcoupon_user_link.create_time > ".$give_start;
        }

        if ($give_end) {
            $give_end = strtotime($give_end) + 86400;
            $where = $where. " AND play_cashcoupon_user_link.create_time < ". $give_end;
        }

        if ($use_time) {
            $use_time = strtotime($use_time);
            $where = $where. " AND play_cashcoupon_user_link.pay_time > ".$use_time;
        }

        if ($use_time_end) {
            $use_time_end = strtotime($use_time_end) + 86400;
            $where = $where. " AND play_cashcoupon_user_link.pay_time < ". $use_time_end;
        }

        $sql_list = "SELECT
	play_cash_coupon.*,
	play_admin.admin_name,
    SUM(if(play_cashcoupon_user_link.id, 1, 0)) AS give_num,
    SUM(if(play_cashcoupon_user_link.pay_time > 0, 1, 0)) AS use_num
FROM
	play_cash_coupon
INNER JOIN play_admin ON play_admin.id = play_cash_coupon.creator
LEFT JOIN play_cashcoupon_user_link ON play_cashcoupon_user_link.cid = play_cash_coupon.id
WHERE
	$where
GROUP BY
	play_cash_coupon.id
ORDER BY
    play_cash_coupon.id DESC
LIMIT {$start}, {$pageSum}";

        $data = $this->query($sql_list);

        $sql_count = "SELECT
    play_cash_coupon.id
FROM
	play_cash_coupon
INNER JOIN play_admin ON play_admin.id = play_cash_coupon.creator
LEFT JOIN play_cashcoupon_user_link ON play_cashcoupon_user_link.cid = play_cash_coupon.id
WHERE
	$where
GROUP BY
	play_cash_coupon.id";

        $count = $this->query($sql_count)->count();
        $url = '/wftadlogin/cashcoupon';

        $paging = new Paginator($page, $count, $pageSum, $url);

        $vm = new viewModel(
            array(
                'data' => $data,
                'pageData' => $paging->getHtml(),
            )
        );

        return $vm;
    }

    public function outAction()
    {

        $code_status = (int)$this->getQuery('code_status', 0);
        $use_range = $this->getQuery('use_range', '');
        $word = trim($this->getQuery('word', ''));
        $give_start = $this->getQuery('give_start', 0); //发放时间
        $give_end = $this->getQuery('give_end', 0);
        $use_time = $this->getQuery('use_time', 0); //使用时间
        $use_time_end = $this->getQuery('use_time_end', 0);
        $city = $this->getBackCity();

        $where =  "play_cash_coupon.id > 0";

        if ($code_status === 1) {//待发放
            $where = $where. " AND play_cash_coupon.begin_time > ". time(). " AND is_close = 0";
        } elseif ($code_status === 2) {//正在发放
            $where = $where. " AND play_cash_coupon.end_time > ". time(). " AND is_close = 0 AND begin_time < ". time();
        } elseif ($code_status == 3) {//已结束
            $where = $where. " AND play_cash_coupon.end_time < ". time();
        } elseif ($code_status == 4) {//停止发放
            $where = $where. " AND play_cash_coupon.is_close = 1";
        }

        if ($word) {
            $where = $where.  'AND (title like "%' . $word . '%" or id = ' . (int)$word . ' or diffuse_code = "' . $word . '")';
        }

        if ($use_range) {
            $use_range = intval($use_range) - 1;
            $where = $where. " AND play_cash_coupon.range = ".$use_range;
        }

        if ($city) {
            $where = $where. " AND play_cash_coupon.city = '{$city}'";
        }

        if ($give_start) {
            $give_start = strtotime($give_start);
            $where = $where. " AND play_cashcoupon_user_link.create_time > ".$give_start;
        }

        if ($give_end) {
            $give_end = strtotime($give_end) + 86400;
            $where = $where. " AND play_cashcoupon_user_link.create_time < ". $give_end;
        }

        if ($use_time) {
            $use_time = strtotime($use_time);
            $where = $where. " AND play_cashcoupon_user_link.pay_time > ".$use_time;
        }

        if ($use_time_end) {
            $use_time_end = strtotime($use_time_end) + 86400;
            $where = $where. " AND play_cashcoupon_user_link.pay_time < ". $use_time_end;
        }

        $sql_list = "SELECT
	play_cash_coupon.*,
    SUM(if(play_cashcoupon_user_link.id, 1, 0)) AS give_num,
    SUM(if(play_cashcoupon_user_link.pay_time > 0, 1, 0)) AS use_num
FROM
	play_cash_coupon
LEFT JOIN play_cashcoupon_user_link ON play_cashcoupon_user_link.cid = play_cash_coupon.id
WHERE
	$where
GROUP BY
	play_cash_coupon.id";

        $data = $this->query($sql_list);
        $file_name = time(). '现金券导出.csv';
        $head = array(
            '现金券id',
            '现金券名称',
            '现金券单价',
            '现金券总张数',
            '现金券发放数',
            '现金券使用数',
            '有效期'
        );
        $content = array();

        foreach ($data as $value) {

            if($value['time_type']){//领券后
                if($value['after_hour'] < 24 or ($value['after_hour']%24)){
                    $dw = '小时';
                    $hour = $value['after_hour'];
                }elseif(($value['after_hour']%24)==0){
                    $dw = '天';
                    $hour = $value['after_hour']/24;
                }
                $use_time =  '领券后'.$hour.$dw.'有效';
            }else{
                $use_time =  date('Y-m-d H:i',$value['use_stime']).'  到  '.date('Y-m-d H:i',$value['use_etime']);
            }

            $content[] = array(
                $value['id'],
                $value['title'],
                $value['price'],
                $value['total'],
                $value['give_num'],
                $value['use_num'],
                $use_time
            );
        }

        $outPut = new OutPut();

        $outPut->out($file_name, $head, $content);

        exit;

    }

    /**
     * 票券开启关闭
     */
    public function closeAction()
    {
        $cid = (int)($this->getQuery('id', 0));
        $isclosed = $this->getQuery('isclosed', 0);

        $where['id'] = $cid;
        if ($this->getAdminCity() != 1) {
            $where['city'] = $this->getAdminCity();
        }

        $status = $this->_getCashCouponTable()->update(['is_close' => $isclosed], $where);

        if ($status > 0) {
            RedCache::del('C:isv:' . $cid);

            return $this->_Goto('操作成功');
        } else {
            return $this->_Goto('操作失败');
        }
    }

    //删除价格套系
    public function deleteAction()
    {
        $id = (int)$this->getQuery('id');

        if (!$id) {
            return $this->_Goto('非法操作');
        }

        $cash = $this->_getCashCouponUserTable()->get(array('id' => $id));

        if(!$cash or $cash->pay_time > 0){
            return $this->_Goto('现金券已使用，不可删除');
        }

        $status = $this->_getCashCouponUserTable()->delete(array('id' => $id));

        //todo 记录

        if ($status) {
            $this->_getCashCouponTable()->update(array('residue' => new Expression('residue + 1')), array('id' => $cash->cid));
            return $this->_Goto('成功');
        } else {
            return $this->_Goto('失败');
        }
    }

    public function statusAction()
    {
        $cid = (int)($this->getQuery('id', 0));
        $status = (int)$this->getQuery('status', 0);

        $where['id'] = $cid;
        if ($this->getAdminCity() != 1) {
            $where['city'] = $this->getAdminCity();
        }

        $status = $this->_getCashCouponTable()->update(['status' => $status], $where);

        if ($status > 0) {
            RedCache::del('C:isv:' . $cid);

            return $this->_Goto('操作成功');
        } else {
            return $this->_Goto('操作失败');
        }
    }

    //现金券使用列表
    public function usedAction()
    {

        $page = (int)$this->getQuery('p', 1);
        $user = $this->getQuery('user', '');
        $goods = $this->getQuery('goods', '');
        $base = $this->getQuery('base', '');
        $cash = $this->getQuery('cash', '');
        $type = $this->getQuery('type', '');
        $cash = $this->getQuery('cash', '');
        $order_id = $this->getQuery('order_sn', '');

        $pageSum = 10;
        $where = [];

        if (array_key_exists('create_stime', $_GET) && $_GET['create_stime']) {
            $where['create_time > ?'] = strtotime($_GET['create_stime']);
        }
        if (array_key_exists('create_etime', $_GET) && $_GET['create_etime']) {
            $where['create_time < ?'] = strtotime($_GET['create_etime'])+24*3600-1;
        }

        if (array_key_exists('order_stime', $_GET) && $_GET['order_stime']) {
            $where['play_order_info.dateline > ?'] = strtotime($_GET['order_stime']);
        }
        if (array_key_exists('order_etime', $_GET) && $_GET['order_etime']) {
            $where['play_order_info.dateline < ?'] = strtotime($_GET['order_etime'])+36*2400-1;
        }

        if($type==1){
            $where['order_type'] = 2;
        }elseif($type==2){
            $where['order_type'] = 3;
        }

        if (array_key_exists('pay_stime', $_GET) && $_GET['pay_stime']) {
            $where['pay_time > ?'] = (int)strtotime($_GET['pay_stime']);
        }
        if (array_key_exists('pay_etime', $_GET) && $_GET['pay_etime']) {
            $where['pay_time < ?'] = strtotime($_GET['pay_etime'])+24*3600-1;
            if(!array_key_exists('pay_time > ?',$where)){
                $where['pay_time > ?'] = 0;
            }
        }
        if(!array_key_exists('pay_time > ?', $where) and !array_key_exists('pay_time < ?', $where)){
            $where['pay_time > ?'] = 0;
        }

        if (array_key_exists('order_sn', $_GET) && $_GET['order_sn']) {
            $where['use_order_id'] = (int)$_GET['order_sn'];
        }
        if ($user !== '') {
            $where['(play_cashcoupon_user_link.uid = ? or play_user.username = ? or play_user.phone = ?)'] = array(
                (int)$user,
                $user,
                (int)$user
            );
        }
        if ($cash !== '') {
            $where['(play_cashcoupon_user_link.cid = ? or play_cashcoupon_user_link.title like ?)'] = array(
                (int)$cash,
                '%' . $cash . '%',
            );
        }
        if ($base !== '') {
            $where['order_type'] = 3;
            $where['(play_order_info.bid = ? or play_order_info.coupon_name like ?)'] = array(
                (int)$base,
                '%' . $base . '%',
            );
        }
        if ($goods !== '') {
            $where['order_type'] = 2;
            $where['(play_order_info.coupon_id = ? or play_order_info.coupon_name like ?)'] = array(
                (int)$goods,
                '%' . $goods . '%',
            );
        }
        if ($order_id !== ''){
            $where['play_order_info.order_sn'] = $order_id;
        }

        $where['play_order_info.pay_status > ?'] = 0;
        //领取情况
        $ccu = $this->_getCashCouponUserTable()->joinWithUser(($page - 1) * $pageSum, $pageSum, [], $where,
            ['create_time' => 'desc'])->toArray();

        $total_pay_money = 0;
        $order_money = 0;
        foreach ($ccu as &$v) {
            //现金券已使用
            if (!empty($v['pay_time'])) {

                $statis = $this->statis($v);

                $v['used_money'] = $statis['used_money']?:0;
                $v['back_money'] = $statis['back_money']?:0;
                $v['free_money'] = $statis['free_money']?:0;
                $v['out_money'] = $statis['out_money']?:0;
                $v['in_money'] = $statis['in_money']?:0;
                $v['cash_money'] = $statis['cash_money']?:0;
                $v['order_money'] = $statis['order_money']?:0;
                $v['order_id'] = $statis['order_id']?:0;

                //收益
                $playOrderInfoGame = $this->_getPlayOrderInfoGameTable()->get("order_sn={$v['order_id']}");
                if ($playOrderInfoGame) {
                    $playGamePrice = $this->_getPlayGamePriceTable()->get("id={$playOrderInfoGame->price_id}");
                    if ($playGamePrice) {
                        //收益价 = 销售价 - 成本价
                        $v['pay_money'] = ($playGamePrice->price - $playGamePrice->account_money) * $playOrderInfo->use_number;
                        $total_pay_money += $v['pay_money'];
                    }
                }
                //商品名称
                $v['goods_name'] = $playOrderInfo->coupon_name;
                //商品id
                $v['goods_id'] = $playOrderInfo->coupon_id;

            }
        }
        //
        $count = $this->_getCashCouponUserTable()->joinWithUserAll($where)->count();
        $url = '/wftadlogin/cashcoupon/used';

        //$cash_coupon_type = $this->_getConfig()['cash_coupon_type'];

        //领券原因类型 1兑换 2点评商品，３点评游玩地，４活动发放　５商品购买　
        //６采纳攻略　７好评app 8圈子发言奖励 9后台评论奖励 10后台邀约有礼奖励 11使用验证　12后台圈子发言奖励
        $uids = [];
        $getinfo = [];

        foreach ($ccu as $c) {
            $uids[] = $c['uid'];
            if (in_array($c['get_type'], [2, 3])) {//评论相关
                $mid[] = $c['get_object_id'];
                $msg = $this->_getMdbSocialCircleMsg()->find(array('_id' => array('$in' => $mid)));
                foreach ($msg as $m) {
                    $getinfo[$c['id']] = '评论商品：' . $m['object_data']['object_title'];
                }
            } elseif (in_array($c['get_type'], [5])) {//商品购买
                $order_sn[] = $c['get_object_id'];
                $order = $this->_getPlayOrderInfoTable()->fetchLimit(0, 20, [], ['order_sn' => $order_sn])->toArray();
                foreach ($order as $o) {
                    $getinfo[$c['id']] = '购买商品：' . $o['coupon_name'];
                }
            } elseif (in_array($c['get_type'], [4])) {//活动发放
                $getinfo[$c['id']] = $c['get_info'];
            } elseif (in_array($c['get_type'], [11])) {//活动发放
                $order_sn[] = $c['get_object_id'];
                $order = $this->_getPlayOrderInfoTable()->fetchLimit(0, 20, [], ['order_sn' => $order_sn])->toArray();
                foreach ($order as $o) {
                    $getinfo[$c['id']] = '使用验证：' . $o['coupon_name'];
                }
            } else {
                $getinfo[$c['id']] = $c['get_info'];
            }
        }

        if (!$ccu) {
            $uids = 0;
        }

        $users = $this->_getPlayUserTable()->fetchLimit(0, 20, [], ['uid' => $uids])->toArray();
        $players = [];
        if (false !== $users && count($users)) {
            foreach ($users as $u) {
                $players[$u['uid']] = $u['username'];
            }
        }
        $use_range = ['全场商品通用', '部分商品使用', '特殊类别使用', '所有活动使用', '部分活动使用'];

        $paginator = new Paginator($page, $count, $pageSum, $url);

        $out = $this->getQuery('out', 0);

        if ($out > 0) {
            $out = new OutPut();

            $file_name = date('Y-m-d H:i:s', time()) . '_使用列表.csv';
            // 输出Excel列名信息

            $head = array(
                '领券ID',
                '现金券ID',
                '领券时间',
                '领券原因',
                '领券用户',
                '使用时间',
                '订单号',
                '商品/活动名称',
                '订单金额',
                '支付金额',
                '现金券金额',
                '已使用金额',
                '退款金额',
                '推广费用',
            );

            $content = array();
            $ccu = $this->_getCashCouponUserTable()->joinWithUserAll($where);
            foreach ($ccu as $v) {
                if(!$v['pay_time']){
                    continue;
                }
                $statis = $this->statis($v);
                $v['used_money'] = $statis['used_money'];
                $v['back_money'] = $statis['back_money'];
                $v['free_money'] = $statis['free_money'];
                $v['out_money'] = $statis['out_money'];
                $v['cash_money'] = $statis['cash_money'];
                $v['order_money'] = $statis['order_money'];
                $v['order_id'] = $statis['order_id'];

                $content[] = array(
                    $v['id'],
                    $v['cid'],
                    $v['create_time'] ? date('Y-m-d H:i', $v['create_time']) : '',
                    $v['get_info'],
                    $v['username'],
                    $v['pay_time'] ? date('Y-m-d H:i', $v['pay_time']) : '--:--:--',
                    (string)$v['order_sn'],
                    $v['coupon_name'],
                    $v['order_money'] ?: 0,
                    $v['in_money'] ?: 0,
                    $v['cash_money']?:0,
                    $v['used_money']?:0,
                    $v['back_money']?:0,
                    $v['free_money']?:0,
                );
            }

            $out->out($file_name, $head, $content);
            exit;
        }

        $vm = new ViewModel(
            array(
                'data' => $data,
                'creator' => $creator->admin_name,
                'ccu' => $ccu,
                'pageData' => $paginator->getHtml(),
                //'cash_coupon_type' => $cash_coupon_type,
                'players' => $players,
                'used' => $used,
                'use_range' => $use_range,
                'getinfo' => $getinfo,
                'total_pay_money' => $total_pay_money
            )
        );

        return $vm;
    }

    /**
     * 现金券使用详情统计
     * @param $v
     * @return mixed
     */
    private function statis($v)
    {
        if(!$v['use_order_id']){
            return array();
        }
        $playOrderInfo = $this->_getPlayOrderInfoTable()->get("order_sn={$v['use_order_id']} and order_status = 1 and pay_status != 0 and pay_status != 1");

        if ((int)$playOrderInfo->order_type === 2) {//商品
            $v['order_money'] = $playOrderInfo->coupon_unit_price * $playOrderInfo->buy_number;
        } elseif ((int)$playOrderInfo->order_type === 3) {//活动
            $v['order_money'] = $playOrderInfo->total_price;
        }

        $order_money += $v['order_money'];
        //订单号
        $v['order_id'] = $playOrderInfo->order_sn;
        //入账 real_pay和account_money取有值的一个
        $v['in_money'] = $playOrderInfo->real_pay + $playOrderInfo->account_money;

        $v['cash_money'] = $v['order_money'] - $v['in_money'];//现金券费用
        $v['used_money'] = $v['back_money'] = $v['free_money'] = 0;
        if ($playOrderInfo->order_type == 2) {//商品
            $playCouponCode = $this->_getPlayCouponCodeTable()->fetchAll(['order_sn' => (int)$playOrderInfo->order_sn])->toArray();
            $order_status = 1;
            foreach ($playCouponCode as $k) {
                $v['out_money'] += $k['back_money'];
                if ($k['status'] == 1) {
                    $v['used_money'] += $playOrderInfo->coupon_unit_price;
                    if($k['force']==3){
                        $v['back_money'] += $k['back_money'];
                        $v['used_money'] -= $playOrderInfo->coupon_unit_price;
                    }
                }
                if ($k['status'] == 2) {
                    if($k['force']!=3) {
                        $v['back_money'] += $k['back_money'];
                    }
                }
                if ($k['status'] != 1 and $k['status'] != 2) {
                    $order_status *= 0;
                }
            }
            //用户玩的商品实际需要的钱-用户使用出的钱
            $v['free_money'] = $v['used_money'] - ($v['in_money'] - $v['back_money']);
            $v['free_money'] *= $order_status;
        } elseif ($playOrderInfo->order_type == 3) {//活动
            $playCouponCode = $this->_getPlayExcerciseCodeTable()->fetchAll(['order_sn' => $playOrderInfo->order_sn])->toArray();
            $order_status = 1;
            foreach ($playCouponCode as $k) {
                $v['out_money'] += $k['back_money'];
                if ($k['status'] == 1) {
                    $v['used_money'] += $k['price'];
                    if($k['accept_status']==3) {
                        $v['back_money'] += $k['back_money'];
                    }
                }
                if ($k['status'] == 2) {
                    $v['back_money'] += $k['back_money'];
                }
                if ($k['status'] != 1 and $k['status'] != 2) {
                    $order_status *= 0;
                }
            }
            $v['free_money'] = 0;
        }
        return $v;
    }

    //现金券详情
    public function detailAction()
    {
        $cid = (int)trim($this->getQuery('cid', ''));
        $page = (int)$this->getQuery('p', 1);
        $user = $this->getQuery('user', '');
        $pageSum = 10;
        $where = [];

        if (array_key_exists('create_stime', $_GET) && $_GET['create_stime']) {
            $where['create_time > ?'] = strtotime($_GET['create_stime']);
        }
        if (array_key_exists('create_etime', $_GET) && $_GET['create_etime']) {
            $where['create_time < ?'] = strtotime($_GET['create_etime'])+24*3600-1;
        }
        if (array_key_exists('pay_stime', $_GET) && $_GET['pay_stime']) {
            $where['pay_time > ?'] = strtotime($_GET['pay_stime']);
        }
        if (array_key_exists('pay_etime', $_GET) && $_GET['pay_etime']) {
            $where['pay_time < ?'] = strtotime($_GET['pay_etime'])+24*3600-1;
        }
        if ($user !== '') {
            $where['(play_cashcoupon_user_link.uid = ? or play_user.username = ? or play_user.phone = ?)'] = array(
                (int)$user,
                $user,
                (int)$user
            );
        }

        if (array_key_exists('use_status', $_GET) && $_GET['use_status']) {
            switch ($_GET['use_status']) {
                case 1:
                    $where['pay_time'] = 0;
                    break;
                case 2:
                    $where['pay_time > ?'] = 0;
                    $where['play_order_info.pay_status > ?'] = 0;
                    break;
                case 3://过期
                    $where['use_etime < ?'] = time();
                    break;
            }
        }

        $data = $this->_getCashCouponTable()->fetchLimit(0, 1, [], ['id' => $cid])->current();
        $used = $this->_getCashCouponUserTable()->fetchCount(['pay_time > ?' => 0, 'cid' => $cid]);
        $creator = $this->_getPlayAdminTable()->fetchLimit(0, 1, ['admin_name', 'id'],
            ['id' => $data['creator']])->current();

        $where['cid'] = $cid;

        //领取情况
        $ccu = $this->_getCashCouponUserTable()->joinWithUser(($page - 1) * $pageSum, $pageSum, [], $where,
            ['create_time' => 'desc'])->toArray();

        $total_pay_money = 0;
        $order_money = 0;
        foreach ($ccu as &$v) {
            //现金券已使用
            if (!empty($v['pay_time'])) {
                $statis = $this->statis($v);

                $v['used_money'] = $statis['used_money'] ?: 0;
                $v['back_money'] = $statis['back_money'] ?: 0;
                $v['free_money'] = $statis['free_money'] ?: 0;
                $v['out_money'] = $statis['out_money'] ?: 0;
                $v['in_money'] = $statis['in_money'] ?: 0;
                $v['cash_money'] = $statis['cash_money'] ?: 0;
                $v['order_money'] = $statis['order_money'] ?: 0;
                $v['order_id'] = $statis['order_id'] ?: 0;
                //收益
                $playOrderInfo = $this->_getPlayOrderInfoTable()->get("order_sn={$v['order_id']}");
                $playOrderInfoGame = $this->_getPlayOrderInfoGameTable()->get("order_sn={$v['order_id']}");
                if (!empty($playOrderInfoGame)) {
                    $playGamePrice = $this->_getPlayGamePriceTable()->get("id={$playOrderInfoGame->price_id}");
                    if (!empty($playGamePrice)) {
                        //收益价 = 销售价 - 成本价
                        $v['pay_money'] = ($playGamePrice->price - $playGamePrice->account_money) * $playOrderInfo->use_number;
                        $total_pay_money += $v['pay_money'];
                    }
                }
                //商品名称
                $v['goods_name'] = $playOrderInfo->coupon_name;
                //商品id
                $v['goods_id'] = $playOrderInfo->coupon_id;
            }

        }
        //
        $count = $this->_getCashCouponUserTable()->joinWithUserAll($where)->count();
        $url = '/wftadlogin/cashcoupon/detail';

        //$cash_coupon_type = $this->_getConfig()['cash_coupon_type'];

        //领券原因类型 1兑换 2点评商品，３点评游玩地，４活动发放　５商品购买　
        //６采纳攻略　７好评app 8圈子发言奖励 9后台评论奖励 10后台邀约有礼奖励 11使用验证　12后台圈子发言奖励
        $uids = [];
        $getinfo = [];

        foreach ($ccu as $c) {
            $uids[] = $c['uid'];
            if (in_array($c['get_type'], [2, 3])) {//评论相关
                $mid[] = $c['get_object_id'];
                $msg = $this->_getMdbSocialCircleMsg()->find(array('_id' => array('$in' => $mid)));
                foreach ($msg as $m) {
                    $getinfo[$c['id']] = '评论商品：' . $m['object_data']['object_title'];
                }
            } elseif (in_array($c['get_type'], [5])) {//商品购买
                $order_sn[] = $c['get_object_id'];
                $order = $this->_getPlayOrderInfoTable()->fetchLimit(0, 20, [], ['order_sn' => $order_sn])->toArray();
                foreach ($order as $o) {
                    $getinfo[$c['id']] = '购买商品：' . $o['coupon_name'];
                }
            } elseif (in_array($c['get_type'], [4])) {//活动发放
                $getinfo[$c['id']] = $c['get_info'];
            } elseif (in_array($c['get_type'], [11])) {//活动发放
                $order_sn[] = $c['get_object_id'];
                $order = $this->_getPlayOrderInfoTable()->fetchLimit(0, 20, [], ['order_sn' => $order_sn])->toArray();
                foreach ($order as $o) {
                    $getinfo[$c['id']] = '使用验证：' . $o['coupon_name'];
                }
            } else {
                $getinfo[$c['id']] = $c['get_info'];
            }
        }

        if (!$ccu) {
            $uids = 0;
        }

        $users = $this->_getPlayUserTable()->fetchLimit(0, 20, [], ['uid' => $uids])->toArray();
        $players = [];
        if (false !== $users && count($users)) {
            foreach ($users as $u) {
                $players[$u['uid']] = $u['username'];
            }
        }
        $use_range = ['全场商品通用', '部分商品使用', '特殊类别使用', '所有活动使用', '部分活动使用'];

        $paginator = new Paginator($page, $count, $pageSum, $url);

        $out = $this->getQuery('out', 0);

        if ($out > 0) {
            $out = new OutPut();

            $file_name = date('Y-m-d H:i:s', time()) . '_领券列表.csv';
            // 输出Excel列名信息

            $head = array(
                '领券ID',
                '领券时间',
                '领券原因',
                '领券用户',
                '使用状态',
                '使用时间',
                '订单号',
                '商品/活动名称',
                '订单金额',
                '支付金额',
                '现金券金额',
                '已使用金额',
                '退款金额',
                '推广费用'
            );

            $content = array();
            $ccu = $this->_getCashCouponUserTable()->joinWithUserAll($where);

            foreach ($ccu as $v) {
                if(!$v['pay_time']){
                    //continue;
                }
                $statis = $this->statis($v);
                $v['used_money'] = $statis['used_money'];
                $v['back_money'] = $statis['back_money'];
                $v['free_money'] = $statis['free_money'];
                $v['in_money'] = $statis['in_money'];
                $v['out_money'] = $statis['out_money'];
                $v['cash_money'] = $statis['cash_money'];
                $v['order_money'] = $statis['order_money'];
                $v['order_id'] = $statis['order_id'];
                $content[] = array(
                    $v['id'],
                    $v['create_time'] ? date('Y-m-d H:i', $v['create_time']) : '',
                    $v['get_info'],
                    $v['username'],
                    $v['pay_time'] ? '已使用' : '待使用',
                    $v['pay_time'] ? date('Y-m-d H:i', $v['pay_time']) : '--:--:--',
                    (string)$v['order_sn'],
                    $v['coupon_name'],
                    $v['order_money'] ?: 0,
                    $v['in_money'] ?: 0,
                    $v['cash_money']?:0,
                    $v['used_money']?:0,
                    $v['back_money'] ?: 0,
                    $v['free_money'] ?: 0,
                );
            }

            $out->out($file_name, $head, $content);
            exit;
        }

        $freemoney = $this->_getCashCouponUserTable()->addupfreemoney($where);
        $vm = new ViewModel(
            array(
                'data' => $data,
                'creator' => $creator->admin_name,
                'ccu' => $ccu,
                'pageData' => $paginator->getHtml(),
                //'cash_coupon_type' => $cash_coupon_type,
                'players' => $players,
                'used' => $used,
                'use_range' => $use_range,
                'getinfo' => $getinfo,
                'freemoney' => $freemoney,
                'total_pay_money' => $total_pay_money
            )
        );

        return $vm;
    }

    public function fixresidueAction()
    {
        $cid = (int)trim($this->getQuery('cid', ''));

        $used = $this->_getCashCouponUserTable()->fetchCount(['cid' => $cid]);

        $adapter = $this->_getAdapter();
        $s1 = $adapter->query("update play_cash_coupon set residue = total - ? WHERE `id` = ? ",
            array($used, $cid))->count();

        echo $s1;
        exit;
    }

    public function saveAction()
    {
        $cid = (int)trim($this->getPost('id', 0));

        $title = trim($this->getPost('title', ''));
        $diffuse_code = trim($this->getPost('diffuse_code', ''));
        $price = trim($this->getPost('price', ''));
        $description = trim($this->getPost('description', ''));
        $total = (int)trim($this->getPost('total', 0));
        $source = trim($this->getPost('source', ''));
        $begin_time = trim($this->getPost('begin_time', '') . $this->getPost('begin_timel', ''));
        $end_time = trim($this->getPost('end_time', '') . $this->getPost('end_timel', ''));
        $time_type = trim($this->getPost('time_type', ''));
        $use_stime = trim($this->getPost('use_stime', '') . $this->getPost('use_stimel', ''));
        $use_etime = trim($this->getPost('use_etime', '') . $this->getPost('use_etimel', ''));
        $after_hour = (int)($this->getPost('after_hour', 0));
        $unit = trim($this->getPost('unit'));
        $city = $this->getPost('city', []);
        $range = ($this->getPost('range', 0));
        $new = ($this->getPost('new', 0));
        $goods = $this->getPost('goods', []);
        $events = $this->getPost('events', []);
        $types = $this->getPost('types', []);
        $is_main = (int)($this->getPost('is_main', 0));

        $end_time = strtotime($end_time);
        $begin_time = strtotime($begin_time);
        $use_stime = strtotime($use_stime);
        $use_etime = strtotime($use_etime);

        $diffuse_code = urlencode($diffuse_code);

        if (strlen($diffuse_code) > 200) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '传播码长度太长'));
        }

        if (!$title || !$price) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '标题或金额不能为空'));
        }

        if (($end_time < $begin_time) || ($use_stime > $use_etime)) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '现金券时间设置不正确'));
        }
        if (!$time_type && $end_time > $use_etime) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '现金券发放的结束时间要比使用的结束时间早'));
        }

        if ((int)$range === 1) {
            if (!$goods) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '请选择商品'));
            }
        } elseif ((int)$range === 2) {
            if (!$types) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '请选择类别'));
            }
        }

        if ($unit === 'd') {
            $after_hour = $after_hour * 24;
        }

        $data = array(
            'creator' => $_COOKIE['id'],
            'title' => $title,
            'diffuse_code' => $diffuse_code,
            'price' => $price,
            'description' => $description,
            'total' => $total,
            'residue' => $total,
            'source' => $source,
            'begin_time' => $begin_time,
            'end_time' => $end_time,
            'time_type' => $time_type,
            'use_stime' => $use_stime,
            'use_etime' => $use_etime,
            'after_hour' => $after_hour,
            'is_main' => $is_main,
            'new' => $new,
            'range' => $range,
            'createtime' => time(),
            'used_num' => 0,
            'status' => 0, //0 未审核 1 审核
            'is_close' => 0
        );

        if ($this->getAdminCity() != 1) {
            $city = [$this->getAdminCity()];
        }

        //判断同城市是否有重名现金券
        if ($this->hasThisNeame($cid, $title, $city)) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '现金券名称请不要重复'));
        }

        //TODO code的唯一
        if ($cid) {
            //判断是否是本站创建
            $cc = $this->_getCashCouponTable()->get(['id' => $cid]);

            if ($cc->city != $this->getAdminCity() && $this->getAdminCity() != 1) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '参数错误,可能不当前城市的现金券'));
            }


            //有人领券，不可编辑,未公开可以编辑
            $ispay = $this->_getCashCouponUserTable()->fetchLimit(0, 1, [], ['cid' => $cid])->toArray();

            if (false !== $ispay && count($ispay) > 0) {
                //$this->jsonResponsePage(array('status' => 0, 'message' => '不可编辑'));
            }

            //判断code是否已经存在
            $same = $this->isRepeat($diffuse_code, $is_main, $city, $cid);
            if ($same) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '传播码已被使用'));
            }
            unset($data['createtime'], $data['used_num'], $data['is_close'], $data['status']);
            $data['residue'] = $total - ($cc->total - $cc->residue);
            $status = $this->_getCashCouponTable()->update($data, ['id' => $cid]);
            if ($status) {
                $this->_getCashCouponTable()->update(['status' => 0], ['id' => $cid]);
            }

            //判断code是否已经存在
            $same = $this->isRepeat($diffuse_code, $is_main, $city, $cid);
            if ($same) {
                $status = $this->_getCashCouponTable()->update(['is_close' => 1], ['id' => $cid]);

                return $this->jsonResponsePage(array('status' => 0, 'message' => '传播码已被抢用，请更换邀约码'));
            }
            $d_g = $d_c = 0;
            if ($status) {
                $d_g = $this->_getCashCouponGoodTable()->delete(['cid' => $cid, 'id > ?' => 0]);
                $d_c = $this->_getCashCouponCityTable()->delete(['cid' => $cid, 'id > ?' => 0]);
            }

        } else {
            $data['city'] = $this->getAdminCity();
            //判断code是否已经存在
            $same = $this->isRepeat($diffuse_code, $is_main, $city);
            if ($same) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '传播码已被使用'));
            }

            $status = $this->_getCashCouponTable()->insert($data);

            if ($status) {
                $cid = $this->_getCashCouponTable()->getlastInsertValue();
            }

            //判断code是否已经存在
            $same = $this->isRepeat($diffuse_code, $is_main, $city, $cid);
            if ($same) {
                $status = $this->_getCashCouponTable()->update(['is_close' => 1], ['id' => $cid]);

                return $this->jsonResponsePage(array('status' => 0, 'message' => '传播码已被抢用，请更换邀约码'));
            }
        }

        //关联的城市
        $value = '';
        if (0 === (int)$is_main) { //不是所有城市
            $sql = 'insert into play_cashcoupon_city (`cid`,`city`) VALUE ';
            foreach ($city as $c) {
                $value .= "({$cid}, '{$c}'),";
            }
            $sql .= $value;
            $sql = substr($sql, 0, -1);

            $this->query($sql);
        }

        //关联的类别或类别
        $value = $sql = '';

        if (1 === (int)$range) {//商品
            $sql = 'insert into play_cashcoupon_good_link (`cid`,`object_type`,`object_id`) VALUE ';
            foreach ($goods as $t) {
                $value .= "({$cid},{$range}, {$t}),";
            }
        } elseif (2 === (int)$range) {//类别
            $sql = 'insert into play_cashcoupon_good_link (`cid`,`object_type`,`object_id`) VALUE ';
            foreach ($types as $t) {
                $value .= "({$cid},{$range}, {$t}),";
            }
        } elseif (4 === (int)$range) {
            $sql = 'insert into play_cashcoupon_good_link (`cid`,`object_type`,`object_id`) VALUE ';
            foreach ($events as $t) {
                $value .= "({$cid},{$range}, {$t}),";
            }
        }

        if ((int)$range > 0 and (int)$range !== 3) {
            $sql .= $value;
            $sql = substr($sql, 0, -1);
            $this->query($sql);
        }
        if ($cid) {
            RedCache::del('C:isv:' . $cid);
        }

        return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));
    }
    //
    /**
     * 判断code的唯一（同一城市不能有重复）
     * @param $diffuse_code
     * @param $is_main
     * @param $city
     * @param $cid
     * @return bool
     */
    public function isRepeat($diffuse_code, $is_main, $city, $cid = 0)
    {
        if (!$diffuse_code) {
            return false;
        }
        $cc = $this->_getCashCouponTable()->fetchAll([
            'diffuse_code' => $diffuse_code,
            'id <> ?' => $cid,
            'end_time > ?' => time()
        ])->toArray();
        if (!$cc) {
            return false;
        }
        if ($cc && $is_main) {
            return true;
        }
        $cities = [];
        foreach ($cc as $c) {
            if ($c['city'] == 1) {
                if ($c['is_main'] == 1) {//如果使用一样ｃｏｄｅ的现金券设置是全场通用，则重复
                    return true;
                }
                $ccct = $this->_getCashCouponCityTable()->fetchAll(['cid' => $c['id']])->toArray();
                if ($ccct) {
                    foreach ($ccct as $cc) {
                        $cities[] = $cc['city'];
                    }
                }
            } else {
                $cities[] = $c['city'];
            }
        }


        $ai = array_intersect($city, $cities);
        if ($ai) {
            return true;
        }

        return false;

    }

    function query($sql)
    {
        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $stmt = $db->query($sql);
        $result = $stmt->execute($stmt);

        return $result;
    }

    public function editAction()
    {
        $cid = (int)$this->getQuery('id', 0);
        $city = $_COOKIE['city'];
        $city = $city ?: 'WH';
        $cities = $this->getAllCities();
        $types = $this->_getPlayLabelTable()->fetchAll(array(
            'status >= ?' => 1,
            'label_type >= ?' => 2,
            'city' => $city
        ))->toArray();

        $cc = $this->_getCashCouponTable()->get(['id' => $cid]);

        //获取关联的城市
        $city = $this->_getCashCouponCityTable()->fetchAll(['cid' => $cid]);
        if ($city) {
            foreach ($city as $c) {
                $c_arr[] = $c['city'];
            }
        } else {
            $c_arr = [];
        }

        //获取关联的商品类型等
        $goods = $this->_getCashCouponGoodTable()->fetchAll(['cid' => $cid, 'object_type' => $cc->range]);
        if ($goods) {
            foreach ($goods as $g) {
                $g_arr[] = $g['object_id'];
            }
        } else {
            $g_arr = [];
        }

        if ((int)$cc->range === 2) {//类别
            $gs = $this->_getPlayLabelTable()->fetchAll(['id' => $g_arr]);
        } elseif ((int)$cc->range === 1) {//商品
            $gs = $this->_getPlayOrganizerGameTable()->fetchAll(['id' => $g_arr]);
        } elseif ((int)$cc->range === 4) {//商品
            //$gs = $this->_getPlayOrganizerGameTable()->fetchAll(['id'=>$g_arr]);
            $gs = $this->_getPlayExcerciseEventTable()->getEventList(0, 500, ['*'],
                ['play_excercise_event.id' => $g_arr], array());
        }

        $vm = new ViewModel(
            array(
                'cities' => $cities ?: [],
                'types' => $types ?: [],
                'data' => $cc,
                'city' => $c_arr,
                'g_arr' => $g_arr,
                'good' => $gs
            )
        );

        return $vm;
    }

    private function hasThisNeame($cid, $title, $city)
    {
        $cash = $this->_getCashCouponTable()->fetchLimit(0, 1, [],
            ['title' => $title, 'city' => [$city, 1]])->current();
        if ($cash and $cash->id != $cid) {
            return true;
        } else {
            return false;
        }
    }

    public function newAction()
    {
        $cid = (int)$this->getQuery('cid');
        $city = $this->getQuery('city');
        $city = $city ?: 'WH';
        $cities = $this->getAllCities();
        $types = $this->_getPlayLabelTable()->fetchAll(array(
            'status >= ?' => 1,
            'label_type >= ?' => 2,
            'city' => $city
        ))->toArray();

        $str = md5(uniqid('', true));
        $diffuse_code = substr($str, -6);

        if ($cid === 0) {
            $data = new \stdClass();
            $data->is_main = 1;
            $data->diffuse_code = $diffuse_code;
        } else {
            $data = [];
        }

        $vm = new ViewModel(
            array(
                'cities' => $cities ?: [],
                'types' => $types ?: [],
                'data' => $data
            )
        );

        return $vm;
    }

}