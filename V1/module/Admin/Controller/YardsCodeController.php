<?php

namespace Admin\Controller;

use Deyi\JsonResponse;
use Deyi\OrderAction\OrderExcerciseBack;
use Deyi\Paginator;
use library\Service\System\Cache\RedCache;
use Deyi\OutPut;
use Zend\Db\Sql\Expression;
use Zend\View\Model\ViewModel;

class YardsCodeController extends BasisController
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

    //列表
    public function indexAction()
    {

        $pageSum = (int)$this->getQuery('page_num', 10);
        $page = (int)$this->getQuery('p', 1);
        $start = ($page - 1) * $pageSum;
        $order = "play_order_info.order_sn DESC";

        $whereData = $this->getWhere();
        if (!$whereData['status']) {
            return $this->_Goto($whereData['message']);
        }
        $adapter = $this->_getAdapter();
        $where = $whereData['message'];

        $sql = "SELECT
	play_excercise_code.*,
	play_order_info.dateline,
    play_order_info.trade_no,
    play_order_info.account_type,
    play_order_info.username,
    play_order_info.user_id,
    play_order_info.coupon_name
FROM
	play_excercise_code
LEFT JOIN play_order_info ON  play_order_info.order_sn = play_excercise_code.order_sn
WHERE $where
ORDER BY
	$order";

        $sql_list = $sql . " LIMIT
{$start}, {$pageSum}";

        $data = $this->query($sql_list);

        $countData = $adapter->query("SELECT
	COUNT(*) AS count_num
FROM
	play_excercise_code
LEFT JOIN play_order_info ON play_order_info.order_sn = play_excercise_code.order_sn WHERE $where", array())->current();

        $count = $countData->count_num;

        //创建分页
        $url = '/wftadlogin/YardsCode/index';
        $paging = new Paginator($page, $count, $pageSum, $url);

        $vm = new ViewModel(array(
            'data' => $data,
            'pageData' => $paging->getHtml(),
            'tradeWay' => $this->tradeWay
        ));

        return $vm;
    }


    //受理退款 及 导出条件
    private function getWhere() {

        $where = 'play_order_info.order_status = 1 AND play_order_info.pay_status > 1 AND play_order_info.order_type = 3';

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

        //使用码状态
        $code_status = (int)$this->getQuery('code_status', 0);

        if (!$code_status || $code_status == 1) {//已提交退款
            $where = $where . " AND ((play_excercise_code.status =3 AND play_excercise_code.accept_status = 0) OR play_excercise_code.accept_status = 1)";
        } elseif ($code_status == 2) { //已使用
            $where = $where . " AND (play_excercise_code.status =1 AND play_excercise_code.accept_status = 0)";
        } elseif ($code_status == 3) {
            $where = $where . " AND play_excercise_code.accept_status = 2";
        } else {
            return array('status' => 0, 'message' => '非法操作');
        }

        $city = $this->chooseCity(1);

        if ($city) {
            $where = $where . " AND play_order_info.order_city = '{$city}'";
        }

        return array('status' => 1, 'message' => $where);

    }

    public function outDataAction()
    {
        $whereData = $this->getWhere();
        if (!$whereData['status']) {
            return $this->_Goto($whereData['message']);
        }
        $where = $whereData['message'];

        $sql_list = "SELECT
	play_excercise_code.*,
	play_order_info.dateline,
    play_order_info.trade_no,
    play_order_info.account_type,
    play_order_info.username,
    play_order_info.user_id,
    play_order_info.coupon_name
FROM
	play_excercise_code
LEFT JOIN play_order_info ON  play_order_info.order_sn = play_excercise_code.order_sn
WHERE $where";

        $data = $this->query($sql_list);

        if (!$data->count()) {
            return $this->_Goto('0条数据！');
        }

        if ($data->count() > 10000) {
            return $this->_Goto('数据太多了, 请缩小范围');
        }

        $out = new OutPut();
        $file_name = date('Y-m-d H:i:s', time()). '_活动受理退款列表.csv';

        $head = array(
            '交易时间',
            '提交退款时间',
            '交易号',
            '订单号',
            '交易渠道',
            '验证码',
            '退款金额',
            '用户id',
            '用户名',
            '活动名称',
        );

        $content = array();

        foreach ($data as $v) {
            $content[] = array(
                date('Y-m-d H:i:s', $v['dateline']),
                $v['back_time'] ? date('Y-m-d H:i:s', $v['dateline']) : '',
                "\t".$v['trade_no'],
                'WFT' . (int)$v['order_sn'],
                $this->tradeWay[$v['account_type']],
                $v['code'],
                $v['back_money'],
                $v['user_id'],
                $v['username'],
                $v['coupon_name'],
            );
        }

        $out->out($file_name, $head, $content);
        exit;
    }

    //受理退款
    public function checkAction()
    {
        $type = (int)$this->getQuery('check_type', 0);

        if (!in_array($type, array(1, 2))) {
            return $this->_Goto('非法操作');
        }

        if ($type == 1) {
            $id = (int)$this->getQuery('id', 0);
            $result = $this->attended($id);
            return $this->_Goto($result['message']);
        }

        if ($type == 2) {
            $ids = trim($this->getPost('ids', ''), ',');
            if (!$ids) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '没选中数据去处理'));
            }

            $codeIds = explode(',', $ids);

            if (count($codeIds) < 1) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '没有符合的数据去处理!'));
            }

            $res = '';
            foreach ($codeIds as $id) {
                $result = $this->attended($id);
                $res = $res. 'id： '. $id. ' 结果 '. $result['message']. '   ';
            }
            return $this->jsonResponsePage(array('status' => 0, 'message' => $res));
        }

        exit;


    }

    private function attended($id)
    {
        $id = (int)$id;
        $codeData = $this->_getPlayExcerciseCodeTable()->get(array('id' => $id));
        $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $codeData->order_sn));

        if (!$codeData || !$orderData || !($codeData->accept_status == 1 || ($codeData->accept_status == 0 && $codeData->status == 3))) {
            return array('status' => 0, 'message' => '使用码不适合');
        }

        if ($orderData->approve_status != 2) {
            return array('status' => 0, 'message' => '订单未审批到账');
        }

        $tip = $this->_getPlayExcerciseCodeTable()->update(array('accept_status' => 2, 'accept_time' => time()), array('id' => $id));

        if (!$tip) {
            return array('status' => 0, 'message' => '失败');
        }

        //添加纪录
        $data = array(
            'action_user' => $_COOKIE['id'],
            'order_id' => $codeData->order_sn,
            'play_status' => 7,
            'action_note' => '受理退款',
            'dateline' => time(),
            'action_user_name' => '管理员'. $_COOKIE['user'],
            'code_id' => $id
        );

        $this->_getPlayOrderActionTable()->insert($data);

        //如果是账户付款 则确认退款
       /* if ($orderData->account_type == 'account') {
            $back = new OrderExcerciseBack();
            $back->refundMoney($id);
        }*/


        return array('status' => 1, 'message' => '成功');

    }

    //提交特殊退款
    public function specialAction()
    {

        $code_id = (int)$this->getPost('code_id', 0);
        $order_sn = (int)$this->getPost('order_sn', 0);
        $reason = trim($this->getPost('reason', ''));
        $money = floatval($this->getPost('money', ''));

        if ((!$code_id && !$order_sn) || ($code_id && $order_sn)) {
            return $this->_Goto('非法操作');
        }

        if (!$reason) {
            return $this->_Goto('特殊退款原因必填');
        }

        if ($code_id) {
            $result = $this->specialCode($code_id, $money, $reason);
            return $this->_Goto($result ? '成功' : '失败');

        }

        if ($order_sn) {
            $res = $this->specialOrder($order_sn, $money, $reason);

            return $this->_Goto($res['message']);
        }

        return $this->_Goto('成功');


    }


    private function specialCode($id, $back_money, $reason)
    {
        $codeData = $this->_getPlayExcerciseCodeTable()->get(array('id' => $id));

        if (!$codeData || $codeData->status != 1 || $codeData->accept_status > 0) {
            return false;
        }

        if ($back_money && $codeData->price < $back_money) {
            return false;
        }

        $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $codeData->order_sn));
        $total_pay = bcadd($orderData->real_pay, $orderData->account_money, 2);

        if ($back_money) {
            $back_money = ($total_pay > $back_money) ? $back_money : $total_pay;
        } else {
            $back_money = ($total_pay > $codeData->price) ? $codeData->price : $total_pay;
        }

        //直接按 提交退款多少 就退多少
       /* if ($orderData->voucher_id) {
            $cash_data = $this->_getCashCouponUserTable()->get(array('id' => $orderData->voucher_id));
            if ($cash_data and $cash_data->is_back == 0) {

                $codeDataFall =  $this->_getPlayExcerciseCodeTable()->fetchAll(array('order_sn' => $codeData->order_sn));

                $no_back_money = 0; //剩下 已使用且未退款的钱
                foreach ($codeDataFall as $fall) {
                    if ($fall['status'] == 1 && $fall['accept_status'] == 0 && $id != $fall['id']) {
                        $no_back_money = $no_back_money + $fall['price'];
                    }
                }

                $voucher_back = bcsub($orderData->voucher, $cash_data->back_money, 2); //剩余可退的钱

                if ($no_back_money < $voucher_back) { //剩下 已使用且未退款的钱 小于 要退现金券的钱时
                    if ($back_money >= $voucher_back) {
                        $this->_getCashCouponUserTable()->update(array('is_back' => 1, 'back_money' => $orderData->voucher), array('id' => $orderData->voucher_id));
                        $true_back_money = bcsub($back_money, $voucher_back, 2);
                    } else {
                        $this->_getCashCouponUserTable()->update(array('back_money' => new Expression('back_money+' . $back_money)), array('id' => $orderData->voucher_id));
                        $true_back_money = 0;
                    }
                } else {
                    $true_back_money = $back_money;
                }
            } else {
                $true_back_money = $back_money;
            }
        } else {
            $true_back_money = $back_money;
        }*/

        $res = $this->_getPlayExcerciseCodeTable()->update(array('accept_status' => 1, 'back_time' => time(), 'back_money' => $back_money, 'back_reason' => $reason), array('id' => $id, 'status' => 1));

        //添加纪录
        $data = array(
            'action_user' => $_COOKIE['id'],
            'order_id' => $codeData->order_sn,
            'play_status' => 14,
            'action_note' => '特殊退款 退款金额是'. $back_money,
            'dateline' => time(),
            'action_user_name' => '管理员'. $_COOKIE['user'],
            'code_id' => $id
        );

        $this->_getPlayOrderActionTable()->insert($data);

        return $res ? true : false;

    }

    private function specialOrder($order_sn, $money, $reason)
    {

        $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));

        if (!$orderData) {
            return array('status' => 0, 'message' => '订单不存在');
        }

        $total_pay = bcadd($orderData->real_pay, $orderData->account_money, 2);

        //step 1 查看整单 中已使用可退的金额
        $can_back_money = 0; //剩下 已使用且未退款的钱
        $already_back_money = 0; //已经退款了的钱
        $codeDataFall =  $this->_getPlayExcerciseCodeTable()->fetchAll(array('order_sn' => $order_sn));
        $need_code = array();
        foreach ($codeDataFall as $fall) {
            if ($fall['status'] == 1 && $fall['accept_status'] == 0) {
                $can_back_money = $can_back_money + $fall['price'];
                $need_code[] = array(
                    'id' => $fall['id'],
                    'money' => $fall['price'],
                );
            }

            if ($fall['back_money'] > 0) {
                $already_back_money = $already_back_money + $fall['back_money'];
            }
        }

        $leave_money = $total_pay - $already_back_money;

        $number = count($need_code);
        if (!$number) {
            return array('status' => 0, 'message' => '没有可退的了');
        }

        if ($can_back_money <= 0) {
            return array('status' => 0, 'message' => '可退的钱为0');
        }

        if (!$money) {
            $money = $can_back_money > $leave_money ? $leave_money : $can_back_money;
        }

        if ($money && $money > $leave_money) {
            return array('status' => 0, 'message' => '可退的钱没有这么多');
        }

        //$can_back_money = ($leave_money > $can_back_money) ? $can_back_money : $leave_money;

        $reason = $reason. ' && 提交特殊退款 退款总额为'. $money;

        $right_code = array();

        if ($money == $can_back_money) {
            $right_code = $need_code;
        } else {

            $t_code = array();
            $have_back_money = 0;
            foreach ($need_code as $val) {
                $back_money = bcdiv($val['money'] * $money, $can_back_money, 2);
                $t_code[] = array(
                    'id' => $val['id'],
                    'money' => $back_money,
                );
                $have_back_money = bcadd($have_back_money , $back_money, 2);
            }

            $surplus_money = bcsub($money, $have_back_money, 2);
            if ($surplus_money > 0) {
                foreach ($t_code as $value) {
                    $right_code[] = array(
                        'id' => $value['id'],
                        'money' => $surplus_money > 0 ? bcadd($value['money'] , 0.01, 2) : $value['money'],
                    );

                    $surplus_money = bcsub($surplus_money, 0.01, 2);
                }
            } else {
                $right_code = $t_code;
            }
        }

        $add = 0;
        $less = 0;
        foreach ($right_code as $code) {
            $z = $this->specialCode($code['id'], $code['money'], $reason);
            if ($z) {
                $add ++;
            } else {
                $less ++;
            }
        }

        if (!$less) {
            return array('status' => 1, 'message' => '成功');
        }

        return array('status' => 0, 'message' => '成功了'. $add. '个code 失败了'. $less. '个code');

    }

    public function updateSpecialAction()
    {
        //todo 检测权限
        if (!in_array($_COOKIE['id'], array(2797, 1317))) {
            return $this->_Goto('没有权限');
        }

        $id = (int)$this->getQuery('id', 0);
        $order_id = (int)$this->getQuery('order_id', 0);

        $codeData = $this->_getPlayExcerciseCodeTable()->get(array('id' => $id, 'order_sn' => $order_id));
        $orderAction = $this->_getPlayOrderActionTable()->get(array('play_status' => 14, 'order_id' => $order_id, 'code_id' => $id));

        if (!$codeData || !$orderAction) {
            return $this->_Goto('订单不存在');
        }

        if ($codeData->accept_status == 3) {
            return $this->_Goto('已经退款了');
        }

        $vm = new ViewModel(
            array(
                'code_id' => $id,
                'order_id' => $order_id,
                'codeData' => $codeData,
            )
        );

        return $vm;
    }

    public function saveUpdateSpecialAction()
    {
        //todo 检测权限
        if (!in_array($_COOKIE['id'], array(2797, 1317))) {
            return $this->_Goto('没有权限');
        }

        $id = (int)$this->getPost('code_id', 0);
        $order_id = (int)$this->getPost('order_id', 0);
        $back_money = $this->getPost('back_money', 0);

        $codeData = $this->_getPlayExcerciseCodeTable()->get(array('id' => $id, 'order_sn' => $order_id));
        $orderAction = $this->_getPlayOrderActionTable()->get(array('play_status' => 14, 'order_id' => $order_id, 'code_id' => $id));


        if (!$codeData || !$orderAction) {
            return $this->_Goto('订单不存在');
        }

        if ($codeData->accept_status == 3) {
            return $this->_Goto('已经退款了');
        }

        if ($back_money <= 0) {
            return $this->_Goto('提交特殊退款的钱不能为0');
        }

        if ($back_money == $codeData->back_money) {
            return $this->_Goto('无修改');
        }

        if ($back_money > $codeData->price) {
            return $this->_Goto('退款金额 不能大于code 本身的金额');
        }

        $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $codeData->order_sn));
        $total_pay = bcadd($orderData->real_pay, $orderData->account_money, 2);

        if ($back_money > $total_pay) {
            return $this->_Goto('退款金额 不能大于订单支付金额');
        }


        $back_reason = $codeData->back_reason. ' 金额改为'.$back_money;
        $s1 = $this->_getPlayExcerciseCodeTable()->update(array('back_money' => $back_money, 'back_reason' => $back_reason), array('id' => $id));

        if ($s1) {
            $s2 = $this->_getPlayOrderActionTable()->update(array('action_user' => $_COOKIE['id'], 'action_user_name' => '管理员'. $_COOKIE['user'], 'action_note' => '修改_提交特殊退款 退款金额是'. $back_money), array('play_status' => 14, 'order_id' => $order_id, 'code_id' => $id));

            return $this->_Goto('成功', '/wftadlogin/excercise/orderinfo?order_sn='. $order_id);
        }

        return $this->_Goto('失败');

    }





}
