<?php

namespace Admin\Controller;

use Deyi\JsonResponse;
use Deyi\Paginator;
use Deyi\Account\Account;
use Deyi\OutPut;
use library\Service\User\Member;
use Zend\View\Model\ViewModel;

class AccountController extends BasisController
{
    use JsonResponse;

    private $recodeType = array(
        '1' => '充值',
        '2' => '奖励',
        '3' => '原路返回退款',
        '4' => '退还到余额',
        '5' => '消费',
        '6' => '提现',
    );

    private $tradeWay = array(
        'weixin' => '微信',
        'union' => '银联',
        'alipay' => '支付宝',
        'jsapi' => '旧微信网页',
        'nopay' => '未付款',
        'weixinsdk' => '微信客户端',
        'account' => '用户账户',
        'new_jsapi' => '新微信网页',
    );

    public function indexAction() {
        exit;
    }

    //余额
    public function balanceAction() {
        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;
        $user = trim($this->getQuery('user',null));

        $order = 'play_user.uid DESC';
        $where = "play_user.uid > 0";

        $city = $this->chooseCity(1);
        if($city){
            $where = $where. " AND play_user.city = '{$city}'";
        }

        if($user){
            if(is_numeric($user)){
                $where .= " and play_account.uid={$user}";
            }else{
                $where .= " and play_user.username like '%{$user}%'";
            }
        }

        $sql = "SELECT
play_account.*,
play_user.username
FROM play_account
LEFT JOIN play_user ON play_user.uid = play_account.uid
 WHERE $where ORDER BY {$order} LIMIT {$start}, {$pageSum}";

        $result = $this->query($sql);
        $countData = $this->query("SELECT count(play_account.uid) as count_num FROM play_account LEFT JOIN play_user ON play_user.uid = play_account.uid WHERE $where")->current();
        $count = $countData['count_num'];
        //创建分页
        $url = '/wftadlogin/account/balance';
        $paginator = new Paginator($page, $count, $pageSum, $url);

        $data = array();

        $service_member = new Member();
        foreach ($result as $res) {
            $userData = $this->getUserInfo($res['uid']);
            $data_member = $service_member->getMemberData($res['uid']);
            $data[] = array(
                'uid' => $res['uid'],
                'username' => $res['username'],
                'can_back_money' => $res['can_back_money'],
                'now_money' => $res['now_money'],
                'coupon_cash' => $userData['coupon_cash'],
                'member_free_coupon_count_now' => $data_member['member_free_coupon_count_now'] > 0 ? : 0,
                'status' => $res['status'],
            );
        }

        return array(
            'data' => $data,
            'pageData' => $paginator->getHtml(),
        );
    }

    //流水记录
    public function recordAction() {

        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;

        $where = $this->getOutWhere();

        $order = "play_account_log.dateline DESC";
        $sql = "SELECT
play_account_log.*,
play_user.username
FROM play_account_log
LEFT JOIN play_user ON play_user.uid = play_account_log.uid
 WHERE $where ORDER BY {$order} LIMIT {$start}, {$pageSum}";

        $res = $this->query($sql);
        $countData = $this->query("SELECT count(play_account_log.id) as count_num FROM play_account_log
LEFT JOIN play_user ON play_user.uid = play_account_log.uid WHERE $where")->current();
        $count = $countData['count_num'];


        //创建分页
        $url = '/wftadlogin/account/record';
        $paginator = new Paginator($page, $count, $pageSum, $url);

        $toal_money_sql = "SELECT
	 SUM(play_account.now_money) as total_money,
	 SUM(play_account.can_back_money) as can_back_money
FROM play_account";
        $total_money_data = $this->query($toal_money_sql)->current();

        $account = array(
            'all' => $total_money_data['total_money'],
            'yes' => $total_money_data['can_back_money'],
            'no' => bcsub($total_money_data['total_money'], $total_money_data['can_back_money'], 2),
        );

        $in_money_sql = "SELECT
	 SUM(play_account_log.flow_money) as total_money
FROM play_account_log
LEFT JOIN play_user ON play_user.uid = play_account_log.uid
WHERE $where AND play_account_log.action_type = 1";
        $in_money_data = $this->query($in_money_sql)->current();

        $out_money_sql = "SELECT
	 SUM(play_account_log.flow_money) as total_money
FROM play_account_log
LEFT JOIN play_user ON play_user.uid = play_account_log.uid
WHERE $where AND play_account_log.action_type = 2";
        $out_money_data = $this->query($out_money_sql)->current();

        $end = array(
            'count' => $count,
            'in' => $in_money_data['total_money'],
            'out' => $out_money_data['total_money'],
        );

        $data = array();

        foreach ($res as $v) {
            $shop_id = '';
            $account_type = '';
            $order_type = 0;
           // $organizer_get = ''; //商家可提现金额;
            if (($v['action_type'] == 2 && in_array($v['action_type_id'], array(1, 2))) || ($v['action_type'] == 1 && in_array($v['action_type_id'], array(1, 18)))) {
                //消费  原路返回退款 退款 活动押金退款
                $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $v['object_id']));
                $shop_id = $orderData ? $orderData->shop_id : '';
                $account_type = $this->tradeWay[$orderData->account_type];

                $order_type = $orderData->order_type;
                $v['trade_no'] = $orderData->trade_no;

            }

            if ($v['action_type'] == 1) {
                if ($v['action_type_id'] == 2) {
                    $account_type = '支付宝';
                } elseif ($v['action_type_id'] == 3) {
                    $account_type = '银联';
                } elseif ($v['action_type_id'] == 12) {
                    $account_type = '微信';
                } elseif ($v['action_type_id'] == 17) {
                    $account_type = '自然童趣';
                } elseif ($v['action_type_id'] == 25) {
                    $account_type = '微信网页';
                }

            }

            $data[] = array(
                'uid' => $v['uid'],
                'username' => $v['username'],
                'dateline' => $v['dateline'],
                'action_type' => $v['action_type'],
                'action_type_id' => $v['action_type_id'],
                'trade_no' => $v['trade_no'],
                'flow_money' => $v['flow_money'],
                'user_account' => $v['user_account'],
                'surplus_money' => $v['surplus_money'],
                'description' => $v['description'],
                'check_status' => $v['check_status'],
                'object_id' => $v['object_id'],
                'shop_id' => $shop_id,
                'account_type' => $account_type,
                'order_type' => $order_type,
                //'organizer_get' => $organizer_get,
            );
        }

        return array(
            'data' => $data,
            'pageData' => $paginator->getHtml(),
            'recodeType' => $this->recodeType,
            'account' => $account,
            'end' => $end,
        );

    }

    //冻结余额
    public function frozenAction() {
        $type = (int)$this->getQuery('type', 0);
        $uid = (int)$this->getQuery('uid', 0);
        $message = $this->getPost('mess', '');

        if (!in_array($type, array(1, 2)) || !$uid) {
            return $this->_Goto('非法操作');
        }

        if (mb_strlen($message) < 5) {
            return $this->_Goto('原因太短');
        }

        $account = new Account();
        $result = $account->frozenUser($uid, $type, $message);

        return $this->_Goto($result ? '成功' : '失败');

    }

    //导出
    public function outDataAction() {
        $type = $this->getQuery('type');

        if (!in_array($type,array('record', 'balance'))) {
            return $this->_Goto('无');
        }

        if ($type == 'record') {
            $fileName = date('Y-m-d H:i:s', time()). '_流水记录.csv';

            $where = $this->getOutWhere();
            $sql = "SELECT
play_account_log.*,
play_user.username
FROM play_account_log
LEFT JOIN play_user ON play_user.uid = play_account_log.uid
 WHERE $where 
 ORDER BY play_account_log.id DESC";

            $data = $this->query($sql);
            $head = array(
                '用户uid',
                '用户名',
                '时间',
                '类型',
                '交易号',
                '交易渠道',
                '订单号',
                '交易金额',
                '商家可提现金额',
                '账户余额',
                '事项',
                '充值是否审核',
                '商家id',
                '现金券金额',
            );

            $content = array();

            foreach ($data as $value) {
                $check_status = '';
                $shop_id = '';
                $ac_type = '';
                $account_type = '';
                $organizer_get = ''; //商家可提现金额;
                $sign = '';
                $voucher = 0;
                if ($value['action_type'] == 2) {
                    if ($value['action_type_id'] == 1) {
                        $ac_type = '消费';
                    } elseif ($value['action_type_id'] == 2) {
                        $ac_type = '原路返回卡上';
                    } elseif ($value['action_type_id'] == 3) {
                        $ac_type = '提现';
                    } elseif ($value['action_type_id'] == 4) {
                        $ac_type = '提现';
                    }
                    $sign = '-';
                } elseif ($value['action_type'] == 1) {
                    if ($value['action_type_id'] == 1 || $value['action_type_id'] == 18) {
                        $ac_type = '退款';
                    } elseif (in_array($value['action_type_id'], array(2, 3, 12, 17, 25))) {
                        $ac_type = '充值';
                    } elseif (in_array($value['action_type_id'], array(4, 5, 6, 7, 8, 9, 10, 11, 14, 15, 16, 19, 20, 21, 26, 99))) {
                        $ac_type = '奖励';
                    }
                    $sign = '+';
                }


                if ($value['action_type'] == 1 && in_array($value['action_type_id'], array(2, 3, 12, 17, 25))) {
                    $check_status = $value['check_status'] ? '已审核' : '未审核';
                }

                if (($value['action_type'] == 2 && in_array($value['action_type_id'], array(1, 2))) || ($value['action_type'] == 1 && in_array($value['action_type_id'], array(1)))) {
                        //消费  原路返回退款 退还到余额 就会有商家id
                    $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $value['object_id']));
                    $shop_id = $orderData ? $orderData->shop_id : '';

                    $account_type = $this->tradeWay[$orderData->account_type];

                    if ($value['action_type'] == 2 && $value['action_type_id'] == 1) {

                        $a_money = $this->_getPlayGameInfoTable()->get(array('id' => $orderData->bid))->account_money;
                        $organizer_get = bcmul($a_money, $orderData->buy_number, 2);
                        $voucher = $orderData->voucher;
                    }

                    $value['trade_no'] = $orderData->trade_no;
                }

                if ($value['action_type'] == 1) {
                    if ($value['action_type_id'] == 2) {
                        $account_type = '支付宝';
                    } elseif ($value['action_type_id'] == 3) {
                        $account_type = '银联';
                    } elseif ($value['action_type_id'] == 12) {
                        $account_type = '微信';
                    } elseif ($value['action_type_id'] == 17) {
                        $account_type = '自然童趣';
                    } elseif ($value['action_type_id'] == 25) {
                        $account_type = '微信网页';
                    }

                }

                $content[] = array(
                    $value['uid'],
                    $value['username'],
                    date('Y-m-d H:i:s', $value['dateline']),
                    $ac_type,
                    "\t".$value['trade_no'],
                    $account_type,
                    $value['object_id'],
                    $sign.$value['flow_money'],
                    $organizer_get,
                    $value['surplus_money'],
                    $value['description'],
                    $check_status,
                    $shop_id,
                    $voucher,
                );
            }

        }

        if ($type == 'balance') {

            $where = "play_user.uid > 0";
            $user = trim($this->getQuery('user',null));
            if($user){
                if(is_numeric($user)){
                    $where .= " and play_account.uid={$user}";
                }else{
                    $where .= " and play_user.username like '%{$user}%'";
                }
            }

            $city = $this->chooseCity(1);
            if($city){
                $where = $where. " AND play_user.city = '{$city}'";
            }

            $sql = "SELECT
play_account.*,
play_user.username
FROM play_account
LEFT JOIN play_user ON play_user.uid = play_account.uid
 WHERE $where";

            $data = $this->query($sql);
            $fileName = date('Y-m-d H:i:s', time()). '_账户余额.csv';
            $head = array(
                '用户uid',
                '用户名',
                '可提现余额',
                '不可提现余额',
                '账户总余额',
                '现金券余额',
                '状态',
            );

            $content = array();

            foreach ($data as $value) {
                $userData = $this->getUserInfo($value['uid']);
                $content[] = array(
                    $value['uid'],
                    $value['username'],
                    $value['can_back_money'],
                    $value['now_money'] - $value['can_back_money'],
                    $value['now_money'],
                    $userData['coupon_cash'],
                    $value['status'] == 0 ? '冻结' : '正常',
                );
            }
        }

        \library\Fun\OutPut::out($fileName, $head, $content);
        exit;
    }


    //提现
    public function giveCashAction() {

        $uid = (int)$this->getQuery('uid', 0);
        $money = $this->getPost('money', 0);
        $reason = trim($this->getPost('reason', ''));

        if (!$reason) {
            return $this->_Goto('原因');
        }

        $userInfo = $this->_getPlayAccountTable()->get(array('uid' => $uid));

        if (!$userInfo || !$userInfo->status) {
            return $this->_Goto('用户不存在 或异常');
        }

        if ($money > $userInfo->can_back_money) {
            return $this->_Goto('用户可提现的金额不足');
        }

        $Adapter = $this->_getAdapter();

        $charge_sql = "SELECT SUM(flow_money) AS charge_money FROM play_account_log WHERE uid = ? AND check_status = 0 AND status = 1 AND action_type = 1 AND action_type_id IN (2, 3, 12, 17)";

        $chargeMoney = $Adapter->query($charge_sql, array($uid))->current();

        if ($chargeMoney->charge_money > $userInfo->can_back_money) {
            return $this->_Goto('异常');
        }

        if ($money > bcsub($userInfo->can_back_money, $chargeMoney->charge_money, 2)) {
            return $this->_Goto('用户充值未审批的钱不能取出来');
        }

        $Account = new Account();
        $result = $Account->takeCrash($uid, $money, $reason, 3, $object_id = 0, $editor_id = $_COOKIE['id']);

        return $this->_Goto($result ? '提现成功' : '提现失败');
    }

    //消耗不可提现的钱
    public function getTemporaryMoneyAction()
    {
        $uid = (int)$this->getQuery('uid', 0);
        $money = $this->getPost('money', 0);
        $reason = trim($this->getPost('reason', ''));

        if (!$reason) {
            return $this->_Goto('原因');
        }

        if (!$money) {
            return $this->_Goto('钱');
        }

        $userInfo = $this->_getPlayAccountTable()->get(array('uid' => $uid));

        if (!$userInfo || !$userInfo->status) {
            return $this->_Goto('用户不存在 或异常');
        }

        if ($money > bcsub($userInfo->now_money, $userInfo->can_back_money, 2)) {
            return $this->_Goto('不可提现的金额不足');
        }

        $Account = new Account();
        $result = $Account->takeCrash($uid, $money, $reason, 4, $object_id = 0, $editor_id = $_COOKIE['id']);

        return $this->_Goto($result ? '取出不可提现的钱成功' : '取出不可提现的钱失败');
    }



    /**
     *  流水记录 和 导出 的条件
     * @return string
     */
    private function getOutWhere() {

        $username = $this->getQuery('username', '');
        $uid = (int)$this->getQuery('uid');
        $time_start = $this->getQuery('time_start', '');
        $time_end = $this->getQuery('time_end', '');
        $trade_no = $this->getQuery('trade_no');
        $order_id = (int)$this->getQuery('order_id');
        $action_type = $this->getQuery('action_type');

        $where = 'play_account_log.id > 0 AND play_account_log.status = 1';

        if ($username) {
            $where = $where. " AND play_user.username = '{$username}'";
        }

        if ($uid) {
            $where = $where. " AND play_account_log.uid = ". $uid;
        }

        if ($order_id) {
            $where = $where. " AND play_account_log.object_id = ". $order_id;
        }

        if ($action_type) {
            //action_type 1 充值  1普通退款 2支付宝充值 3银联充值  4圈子发言奖励 5购买商品奖励　6点评商品奖励　7点评游玩地奖励　8app好评奖励 9采纳攻略 10使用验证返利 11 后台评论管理奖励　12微信充值
            //action_type 2 使用  1 购买商品  2 原路返回卡上

            if ($action_type == 1) {//充值action_type 1   2支付宝充值 3银联充值 12微信充值
                $where = $where. " AND play_account_log.action_type = 1 AND play_account_log.action_type_id IN (2, 3, 12, 17, 25)";
            } elseif ($action_type == 2) {//奖励action_type 1 4圈子发言奖励 5购买商品奖励 6点评商品奖励 7点评游玩地奖励 8app好评奖励 9采纳攻略 10使用验证返利 11 后台评论管理奖励
                $where = $where. " AND play_account_log.action_type = 1 AND play_account_log.action_type_id IN (4, 5, 6, 7, 8, 9, 10, 11, 19, 20, 26)";
            } elseif ($action_type == 3) {//退还原账户action_type 2  2原路返回卡上
                $where = $where. " AND play_account_log.action_type = 2 AND play_account_log.action_type_id = 2";
            } elseif ($action_type == 4) {//退款action_type 1  1普通退款
                $where = $where. " AND play_account_log.action_type = 1 AND play_account_log.action_type_id = 1";
            } elseif ($action_type == 5) {//消费action_type 2  1 购买商品
                $where = $where. " AND play_account_log.action_type = 2 AND play_account_log.action_type_id = 1";
            } elseif ($action_type == 6) {//提现 action_type 2  action_type_id 3  提现
                $where = $where. " AND play_account_log.action_type = 2 AND play_account_log.action_type_id = 3";
            }
        }

        if ($trade_no) {
            $where = $where. " AND play_account_log.trade_no = '{$trade_no}'";
        }

        if ($time_start) {
            $where = $where. " AND play_account_log.dateline > ". strtotime($time_start);
        }

        if ($time_end) {
            $where = $where. " AND play_account_log.dateline < ". (strtotime($time_end) + 86400);
        }

        $city = $this->chooseCity(1);
        if($city){
            if ($city == 'WH') {
                $where = $where. " AND (play_user.city = '{$city}' OR play_user.city = '')";
            } else {
                $where = $where. " AND play_user.city = '{$city}'";
            }
        }

        return $where;
    }


    private function getUserInfo($uid) {
        $data = array(
            'coupon_cash' => 0,
        );
        $timer = time();
        $sql = "SELECT SUM(price) as price FROM play_cashcoupon_user_link WHERE uid = {$uid} AND use_etime > {$timer} AND pay_time < 1";
        $result = $this->query($sql)->current();

        if (floatval($result['price']) > 0) {
            $data['coupon_cash'] = floatval($result['price']);
        }

        return $data;

    }


    public function frozenLogAction() {
        $uid = $this->getQuery('uid');

        $adapter = $this->_getAdapter();
        $forzenLog = $adapter->query("SELECT * FROM play_user_frozen_log WHERE uid = ? ORDER BY dateline DESC", array($uid));

        foreach ($forzenLog as $log) {
            echo '时间'.  date('Y-m-d, H:i:s', $log['dateline']). '    ';
            echo ($log['frozen_type'] == 1) ? '冻结' : ($log['frozen_type'] == 2 ? '解冻' : '异常') . '  ';
            echo '是因为 ：'. $log['message'];
            echo '<br />';
            echo '<br />';
            echo '<br />';
        }

        exit;

    }
}