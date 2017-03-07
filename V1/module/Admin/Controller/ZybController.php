<?php

namespace Admin\Controller;

use Deyi\JsonResponse;
use Deyi\Paginator;
use Deyi\ZybPay\ZybPay;
use Deyi\OrderAction\UseCode;
use library\Fun\M;
use library\Service\System\Cache\RedCache;
use Zend\Db\Sql\Predicate\Expression;
use Zend\Db\Sql\Select;
use Zend\View\Model\ViewModel;
use Deyi\ImageProcessing;
use Deyi\SendMessage;

class ZybController extends BasisController
{
    use JsonResponse;
    use UseCode;

    //智游宝商品下单列表
    public function indexAction()
    {

        $page = $this->getQuery('page', 1);
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;

        $where = "play_order_info.order_status = 1 AND play_order_info.pay_status > 1 ";

        $sql = "SELECT
	play_zyb_info.order_sn,
	play_zyb_info.status,
	play_order_info.buy_phone,
	play_order_info.coupon_name,
	play_order_info.coupon_id,
	play_order_info.dateline,
	play_order_info.username,
	play_order_info.user_id,
	play_order_info_game.type_name
FROM
	play_zyb_info
LEFT JOIN play_order_info ON  play_zyb_info.order_sn = play_order_info.order_sn
LEFT JOIN play_order_info_game ON  play_order_info_game.order_sn = play_order_info.order_sn
WHERE $where  GROUP BY play_order_info.order_sn ORDER BY play_order_info.order_sn DESC LIMIT {$start}, {$pageSum}";

        $data = $this->query($sql);

        $count= M::getAdapter()->query("SELECT count(*) as c FROM play_zyb_info  LEFT JOIN play_order_info ON  play_zyb_info.order_sn = play_order_info.order_sn WHERE $where GROUP BY play_order_info.order_sn",array())->current()->c;
        //创建分页
        $url = '/wftadlogin/zyb';
        $pagination = new Paginator($page, $count, $pageSum, $url);

        return array(
            'data' => $data,
            'pageData' => $pagination->getHtml(),
        );
    }

    //查看订单详情
    public function viewAction() {

        $order_sn = $this->getQuery('order_sn');

        $ZybPay = new ZybPay();
        $result = $ZybPay->findOrderInfo($order_sn);

        var_dump($result['order']);
        exit;

    }

    //改签
    public function changeAction() {
        $new_date = $this->getPost('new_time');
        $code_id = $this->getPost('code_id');

        if (strtotime($new_date) < time()) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '时间不正确'));
            exit;
        }


        $ZybPay = new ZybPay();
        $result = $ZybPay->alterTicket($code_id, $new_date);


        if ($result['code'] == 0) {
            return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'message' => $result['description']));
        }

        exit;
    }

    public function infoAction() {
        $order_sn = $this->getQuery('order_sn');

        $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn, 'order_status' => 1, 'pay_status > ?' => 1));

        if (!$orderData) {
            return $this->_Goto('该订单不存在');
        }

        $gameInfoData = $this->_getPlayOrderInfoGameTable()->get(array('order_sn' => $order_sn));


        $where = "play_order_info.order_status = 1 AND play_order_info.pay_status > 1 AND play_zyb_info.order_sn = {$order_sn}";

        $sql = "SELECT
	play_zyb_info.*,
	play_coupon_code.status as code_status,
	play_coupon_code.sort,
    play_coupon_code.password
FROM
	play_zyb_info
LEFT JOIN play_coupon_code ON  play_coupon_code.id = play_zyb_info.code_id
LEFT JOIN play_order_info ON  play_zyb_info.order_sn = play_order_info.order_sn
WHERE $where";

        $codeData = $this->query($sql);
        return array(
            'orderData' => $orderData,
            'codeData' => $codeData,
            'gameInfo' => $gameInfoData,
        );
    }

    public function buyAction()
    {

        $order_sn = $this->getPost('order_sn');
        $code_data = $this->getPost('ter');

        if (!$order_sn || !$code_data) {
            return $this->_Goto("非法操作");
        }

        if (!count($code_data)) {
            return $this->_Goto("时间不正确");
        }

        $orderInfo = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));

        if (!$orderInfo) {
            return $this->_Goto("非法操作");
        }

        $code_ids = array();
        foreach ($code_data as $u=>$t) {
            if (strtotime($t) < time()) {
                return $this->_Goto("时间不正确");
                break;
            }
            $code_ids[$u] = substr($u, 0, -7);
        }

        $ZybPay = new ZybPay();
        $result = $ZybPay->pay($order_sn, $code_data);

        if($result['code'] == '0' && $result['description'] == '成功') {
            $zyb_code = $result['orderResponse']['order']['assistCheckNo'];
            $timer = time();
            foreach ($code_ids as $pass=>$code_id) {
                $this->query("update play_zyb_info set status = 2, zyb_code = {$zyb_code}, buy_time = {$timer} WHERE order_sn = {$order_sn} AND code_id = {$code_id}");
                $this->UseCode($_COOKIE['id'], 4, $pass);
            }

            //预约成功 发送短信
            SendMessage::Send13($orderInfo->buy_phone,$orderInfo->coupon_name, count($code_ids) ,$zyb_code);

        } else {
            var_dump($result);
            return $this->_Goto('失败');
        }

        return $this->_Goto('成功');

        exit;

    }




}
