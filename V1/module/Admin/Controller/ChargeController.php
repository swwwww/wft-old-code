<?php

namespace Admin\Controller;

use Deyi\JsonResponse;
use Deyi\Paginator;
use Deyi\Account\Account;
use Deyi\OutPut;
use Zend\View\Model\ViewModel;

class ChargeController extends BasisController
{
    use JsonResponse;

    public function indexAction() {
        exit;
    }

    //充值审批列表
    public function listAction() {
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

        $data = $this->query($sql);
        $countData = $this->query("SELECT
	COUNT(*) AS count_num,
	SUM(if(play_account_log.check_status=0, play_account_log.flow_money, 0)) AS wait_money,
    SUM(if(play_account_log.check_status=1, play_account_log.flow_money, 0)) AS have_money
FROM
	play_account_log
LEFT JOIN play_user ON play_user.uid = play_account_log.uid
WHERE
	$where")->current();
        $count = $countData['count_num'];

        $counter = array(
            'count_num' => $countData['count_num'],
            'wait_money' => $countData['wait_money'],
            'have_money' => $countData['have_money'],
        );
        //创建分页
        $url = '/wftadlogin/charge/list';
        $paginator = new Paginator($page, $count, $pageSum, $url);

        return array(
            'data' => $data,
            'pageData' => $paginator->getHtml(),
            'counter' => $counter,
        );
    }

    //充值审批到账
    public function chargeAction() {
        $type = (int)$this->getQuery('type', 0);

        if ($type == 1) {
            $id = (int)$this->getQuery('id', 0);

            $accountLog =  $this->_getPlayAccountLogTable()->get(array('id' => $id));

            if (!$accountLog) {
                return $this->_Goto('失败');
            }

            $status = $this->_getPlayAccountLogTable()->update(array('check_status' => 1), array('id' => $id));

            return $this->_Goto($status ? '成功!' : '失败');
        }

        if ($type == 2) { //单页面批量操作
            $ids = $this->getPost('ids');
            $ids =   trim($ids, ',');
            if (!$ids) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => 'wow'));
            }

            $accountLog = $this->_getPlayAccountLogTable()->fetchAll(array("id in ($ids)"));

            if (!$accountLog->count()) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '没有选中'));
            }

            $i = 0;
            foreach ($accountLog as $log) {
                if ($log->check_status == 0) {

                    $status = $this->_getPlayAccountLogTable()->update(array('check_status' => 1), array('id' => $log->id));
                    if (!$status ) {
                        continue;
                    }
                    $i++;
                }
            }

            return $this->jsonResponsePage(array('status' => $i ? 1 : 0, 'message' => $i ? '成功' : '失败'));
        }

        if ($type == 3) { //sql 条件下批量操作

            $adapter = $this->_getAdapter();
            $where = $this->getOutWhere();

            $account_sql = "SELECT play_account_log.* FROM play_account_log
LEFT JOIN play_user ON play_user.uid = play_account_log.uid WHERE $where AND play_account_log.id > ?";

            $accountLog = $adapter->query($account_sql, array(1));

            if (!$accountLog->count()) {
                return $this->_Goto('没有选中');
            }

            $i = 0;
            foreach ($accountLog as $log) {
                if ($log->check_status == 0) {

                    $status = $this->_getPlayAccountLogTable()->update(array('check_status' => 1), array('id' => $log->id));
                    if (!$status ) {
                        continue;
                    }
                    $i++;
                }
            }

            return $this->_Goto($i ? '成功' : '失败');

        }

        exit('非法操作');
    }

    public function outDataAction() {
        $where = $this->getOutWhere();
        $sql = "SELECT
play_account_log.*,
play_user.username
FROM play_account_log
LEFT JOIN play_user ON play_user.uid = play_account_log.uid
 WHERE $where";
        $data = $this->query($sql);

        $out = new OutPut();

        $file_name = date('Y-m-d H:i:s', time()). '_用户充值列表.csv';

        $action_type_id = $this->getQuery('action_type_id');

        $head = array(
            '用户uid',
            '用户名',
            '交易时间',
            '交易号',
            '订单号',
            '交易金额',
            '账户余额',
            '交易账号',
            '支付渠道',
            '审批状态',
            ($action_type_id == 17) ? '现金券相关' : ''
        );

        $content = array();

        foreach ($data as $v) {

            if ($v['action_type_id'] == 2) {
                $trader_way = '支付宝';
            }elseif ($v['action_type_id'] == 3) {
                $trader_way ='银联';
            } elseif ($v['action_type_id'] == 12) {
                $trader_way = '微信';
            }

            $cashData = '';

            if ($action_type_id == 17) {
                $cash_sql = "SELECT cid, price FROM play_cashcoupon_user_link WHERE get_info = '自然童趣奖励' AND uid = {$v['uid']} AND cid IN (84, 85, 86)";
                $cash_data = $this->query($cash_sql);
                $cash_money = '';
                $i = 0;
                foreach ($cash_data as $cash) {
                    $cashData = $cashData. $cash['cid']. '-'. $cash['price']. '　';
                    $i ++;
                    $cash_money = $cash_money + $cash['price'];
                }

                $cashData = $cashData. "张数{$i}张 ". "总金额{$cash_money}";
            }


            $content[] = array(
                $v['uid'],
                "\t".$v['username'],
                date('Y-m-d H:i:s', $v['dateline']),
                "\t".$v['trade_no'],
                'WFTREC' . (int)$v['id'],
                $v['flow_money'],
                $v['surplus_money'],
                $v['user_account'],
                $trader_way,
                $v['check_status'] ? '已审批' : '未审批',
                ($action_type_id == 17) ? $cashData : ''
            );
        }

        $out->out($file_name, $head, $content);
        exit;

    }

    /**
     *  充值记录 和 导出的条件
     * @return string
     */
    private function getOutWhere() {

        $username = $this->getQuery('username', '');
        $uid = (int)$this->getQuery('uid');
        $time_start = $this->getQuery('time_start', '');
        $time_end = $this->getQuery('time_end', '');
        $trade_no = $this->getQuery('trade_no');
        $order_id = $this->getQuery('order_id');
        $action_type_id = $this->getQuery('action_type_id');
        $check_status = intval($this->getQuery('check_status', 1));

        $where = 'play_account_log.id > 0 AND play_account_log.status = 1 AND play_account_log.action_type = 1';

        if ($action_type_id) {
            $where = $where. " AND play_account_log.action_type_id = {$action_type_id}";
        } else {
            $where = $where. " AND play_account_log.action_type_id in (2,3,12,17,25)";
        }

        if ($username) {
            $where = $where. " AND play_user.username = '{$username}'";
        }

        if ($uid) {
            $where = $where. " AND play_account_log.uid = ". $uid;
        }


        if ($order_id) {
            $order_id = (int)preg_replace('|[a-zA-Z/]+|', '', $order_id);

            $where = $where. " AND play_account_log.id = ". $order_id;
        }

        if ($trade_no) {
            $where = $where. " AND play_account_log.trade_no = '{$trade_no}'";
        }

        if ($time_start) {
            $where = $where. " AND play_account_log.dateline > ". strtotime($time_start);
        }

        if ($time_end) {
            $where = $where. " AND play_account_log.dateline < ". (strtotime($time_end) + 24 * 3600);
        }

        if ($check_status && in_array($check_status, array(1, 2))) {
            $check_status = $check_status - 1;
            $where = $where. " AND play_account_log.check_status = {$check_status}";
        }

        $city = $this->chooseCity(1);
        if($city){
            $where = $where. " AND play_user.city = '{$city}'";
        }

        return $where;
    }


}