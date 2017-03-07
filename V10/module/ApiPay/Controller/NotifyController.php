<?php

namespace ApiPay\Controller;


use Deyi\Alipay\Alipay;
use Deyi\BaseController;
use Deyi\SendMessage;
use Zend\Db\Sql\Predicate\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Response;
use Deyi\JsonResponse;


class NotifyController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;


    //客户端支付成功通知 已废弃
    public function indexAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        return $this->jsonResponse(array('status' => 1, 'message' => '操作成功'));
        //订单号
        $order_sn = $this->getParams('order_sn');
        $pay_status = $this->getParams('pay_status', 0);  //1已支付, 0未支付

        if (!$order_sn) {
            return $this->jsonResponseError('订单号或交易号不存在');
        }

        $data = $status = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));

        if ($data->trade_no or $data->pay_status > 0) {
            return $this->jsonResponse(array('status' => 1, 'message' => '操作成功.'));
        }

        if ($pay_status) {
            //付款状态 ;0未付款;1付款中;2已付款 3  退款中 4 退款成功 5已使用
            $status = $this->_getPlayOrderInfoTable()->update(array('pay_status' => 1), array('order_sn' => $order_sn));
            return $this->jsonResponse(array('status' => 1, 'message' => '操作成功'));
        } else {
            return $this->jsonResponse(array('status' => 0, 'message' => '用户未付款'));
        }


    }

}
