<?php

namespace Admin\Controller;

use Deyi\JsonResponse;
use Deyi\Paginator;
use Deyi\OutPut;
use Zend\View\Model\ViewModel;

class OrganizerRecordController extends BasisController
{
    use JsonResponse;

    //流水记录
    public function recordAction() {
        

        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;

        $where = $this->getOutWhere();
        $order = "play_organizer_account_log.id DESC";

        $sql = "SELECT
play_organizer_account_log.*,
play_contracts.contract_no,
play_coupon_code.use_datetime
FROM play_organizer_account_log
LEFT JOIN play_coupon_code ON play_coupon_code.id = play_organizer_account_log.code_id
LEFT JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
LEFT JOIN play_contracts ON play_contracts.id = play_organizer_account_log.contract_id
WHERE $where ORDER BY {$order} LIMIT {$start}, {$pageSum}";

        $data = $this->query($sql);
        $countData = $this->query("SELECT count(play_organizer_account_log.id) as count_num FROM play_organizer_account_log
LEFT JOIN play_coupon_code ON play_coupon_code.id = play_organizer_account_log.code_id
LEFT JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
LEFT JOIN play_contracts ON play_contracts.id = play_organizer_account_log.contract_id WHERE $where")->current();
        $count = $countData['count_num'];

        //创建分页
        $url = '/wftadlogin/organizerrecord/record';
        $paginator = new Paginator($page, $count, $pageSum, $url);

        return array(
            'data' => $data,
            'pageData' => $paginator->getHtml(),
        );

    }


    //导出
    public function outDataAction() {

        $fileName = date('Y-m-d H:i:s', time()). '商家账户交易流水.csv';

        $where = $this->getOutWhere();
        $sql = "SELECT
play_organizer_account_log.*,
play_organizer.name,
play_order_info.user_id,
play_order_info.account_type,
play_order_info.coupon_unit_price,
play_contracts.contract_no
FROM play_organizer_account_log
LEFT JOIN play_order_info ON play_order_info.order_sn = play_organizer_account_log.object_id
LEFT JOIN play_organizer ON play_organizer.id = play_organizer_account_log.oid
LEFT JOIN play_contracts ON play_contracts.id = play_organizer_account_log.contract_id
WHERE $where ORDER BY play_organizer_account_log.id DESC";

        $data = $this->query($sql);
        $tradeWay = array(
            'weixin' => '微信',
            'union' => '银联',
            'alipay' => '支付宝',
            'jsapi' => '旧微信网页',
            'nopay' => '未付款',
            'account' => '用户账户',
            'new_jsapi' => '新微信网页',
        );
        $head = array(
            'id',
            '交易时间',
            '交易类型',
            '商家id',
            '合同编号',
            '商家名称',
            '订单id',
            '订单金额',
            '商家提现金额',
            '收益',
            '商家账户余额',
            '用户id',
            '支付方式',
        );

        $content = array();

        foreach ($data as $value) {

            $ac_type = '';
            $order_id = '';
            $order_money = 0;
            $user_id = 0;
            $way = '';
            $tip = '';
            if ($value['action_type'] == 1) {
                if ($value['object_type'] == 1) {
                    $ac_type = '订单分润';
                    $order_id = 'WFT'. $value['object_id'];
                    $order_money = $value['coupon_unit_price'];
                    $user_id = $value['user_id'];
                    $way = $tradeWay[$value['account_type']];
                } elseif ($value['object_type'] == 2) {
                    $ac_type = '预付款';
                } elseif ($value['object_type'] == 3) {
                    $ac_type = '订单冲账';
                    $order_id = 'WFT'. $value['object_id'];
                    $order_money = $value['coupon_unit_price'];
                    $user_id = $value['user_id'];
                    $way = $tradeWay[$value['account_type']];
                }

                $tip = '+';
            } elseif ($value['action_type'] == 2) {
                if ($value['object_type'] == 1) {
                    $ac_type = '预付款提现';
                } elseif ($value['object_type'] == 2) {
                    $ac_type = '商家提现';
                } elseif ($value['object_type'] == 3) {
                    $ac_type = '特殊退款';
                    $order_id = 'WFT'. $value['object_id'];
                    $user_id = $value['user_id'];
                    $way = $tradeWay[$value['account_type']];
                }

                $tip = '-';
            }

            $content[] = array(
                $value['id'],
                date('Y-m-d H:i:s', $value['dateline']),
                $ac_type,
                $value['oid'],
                "\t".$value['contract_no'],
                $value['name'],
                $order_id,
                $order_money,
                $tip.$value['flow_money'],
                $order_money - $value['flow_money'],
                $value['surplus_money'],
                $user_id,
                $way,
            );
        }
        $out = new OutPut();
        $out->out($fileName, $head, $content);
        exit;
    }


    /**
     *  流水记录 和 导出 的条件
     * @return string
     */
    private function getOutWhere() {

        $organizer_name = $this->getQuery('organizer_name', '');
        $organizer_id = (int)$this->getQuery('organizer_id');
        $time_start = $this->getQuery('time_start', '');
        $time_end = $this->getQuery('time_end', '');
        $action_type = $this->getQuery('action_type');
        $contract_no = $this->getQuery('contract_no');

        $good_id = (int)$this->getQuery('good_id', 0);
        $code_number = $this->getQuery('code_number', '');
        $good_name = $this->getQuery('good_name', '');

        $where = 'play_organizer_account_log.status > 0';

        if ($contract_no) {
            $where = $where. " AND play_contracts.contract_no = '{$contract_no}'";
        }

        if ($organizer_name) {
            $where = $where. " AND play_organizer_account_log.organizer_name = '{$organizer_name}'";
        }

        if ($organizer_id) {
            $where = $where. " AND play_organizer_account_log.oid = ". $organizer_id;
        }

        if ($good_id) {
            $where = $where. " AND play_order_info.coupon_id = ". $good_id;
        }

        if ($good_name) {
            $where = $where. " AND play_order_info.coupon_name = '{$good_name}'";
        }

        if ($code_number) {
            $code_id = intval(substr($code_number, 0, -7));
            if ($code_id) {
                $where = $where. " AND play_organizer_account_log.code_id = ". $code_id;
            }
        }

        if ($action_type) {
            //action_type 1 收益  1 订单分润 2 预付款 3 订单冲账
            //action_type 2 取钱  2 商家提现 1预付款提现 3已使用退款

            if ($action_type == 1) {//订单分润
                $where = $where. " AND play_organizer_account_log.action_type = 1 AND play_organizer_account_log.object_type = 1";
            } elseif ($action_type == 2) {//商家提现
                $where = $where. " AND play_organizer_account_log.action_type = 2 AND play_organizer_account_log.object_type = 2";
            } elseif ($action_type == 3) {//预付款
                //$where = $where. " AND play_organizer_account_log.action_type = 1 AND play_organizer_account_log.object_type = 2";
            } elseif ($action_type == 4) {//预付款提现
                $where = $where. " AND play_organizer_account_log.action_type = 2 AND play_organizer_account_log.object_type = 1";
            } elseif ($action_type == 5) {//特殊退款
                $where = $where. " AND play_organizer_account_log.action_type = 2 AND play_organizer_account_log.object_type = 3";
            } elseif ($action_type == 6) {//订单充帐
                $where = $where. " AND play_organizer_account_log.action_type = 1 AND play_organizer_account_log.object_type = 3";
            }
        }

        if ($time_start) {
            $where = $where. " AND play_organizer_account_log.dateline > ". strtotime($time_start);
        }

        if ($time_end) {
            $where = $where. " AND play_organizer_account_log.dateline < ". (strtotime($time_end) + 86400);
        }

        return $where;
    }


}