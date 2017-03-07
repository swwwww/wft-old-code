<?php

namespace ApiUser\Controller;

use Deyi\BaseController;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Response;
use Deyi\JsonResponse;

class GroupBuyController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    //我的团
    public function indexAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }
        $uid = $this->getParams('uid');
        $status = $this->getParams('status', 1); //状态 0已解散 1等待加入 2已完成 3等待付款
        $page = $this->getParams('page', 1);
        $pagenum = $this->getParams('pagenum', 10);
        if (!$uid) {
            return $this->jsonResponseError('参数错误');
        }


        $offset = ($page - 1) * $pagenum;
        $res = $this->_getPlayOrderInfoTable()->getBroupBuyList($status, $offset, $pagenum, $uid);

        $data = array();
        foreach ($res as $v) {
            $data[] = array(
                'order_sn' => $v->order_sn,
                'group_buy_id' => $v->group_buy_id,
                'title' => $v->coupon_name,
                'description' => $v->editor_talk, //新增描述
                'img' => $this->getImgUrl($v->thumb),
                'price' => $v->large_price,
                'new_price' => $v->g_price,//现价
                'end_time' => $status == 2 ?  $v->end_time : $v->e_time,
                'coupon_id' => $v->coupon_id,
                'join_number' => $v->join_number,
                'limit_number' => $v->limit_number,
                'pay_status' => $v->pay_status,
            );
        }

        return $this->jsonResponse($data);
    }


}
