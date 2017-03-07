<?php

namespace ApiPay\Controller;

use Deyi\Alipay\Alipay;
use Deyi\BaseController;
use Deyi\OrderAction\UseCode;
use Deyi\SendMessage;
use Zend\Db\Sql\Predicate\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Response;
use Deyi\JsonResponse;


class UseCodeController extends AbstractActionController
{
    use JsonResponse;
    use UseCode;
    use BaseController;


    public function indexAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }


        // 获取绑定的店铺id或者活动组织者id

        $uid = (int)$this->getParams('uid', 0);
        $code = $this->getParams('code');
        $merchant = [
            'shop_id' => (int)$this->getParams('shop_id', 0),
            'shop_name' => $this->getParams('shop_name'),
            'organizer_id' => (int)$this->getParams('organizer_id', 0),
            'organizer_name' => $this->getParams('organizer_name')
        ];

        if (!$uid) {
            return $this->jsonResponse(['status' => 0, 'message' => '用户未登录']);
        }

        if (!$merchant['shop_id'] and !$merchant['organizer_id']) {
            return $this->jsonResponse(['status' => 0, 'message' => '未绑定商家']);
        }
        // 验证用户是否正确绑定

        $bind_user_data = $this->QueryUserBind($uid);

        if ($bind_user_data['shop_id'] && $bind_user_data['shop_id'] != $merchant['shop_id']) {
            return $this->jsonResponse(['status' => 0, 'message' => '绑定的商家与使用码不符,请联系客服']);
        }

        if ($bind_user_data['organizer_id'] && $bind_user_data['organizer_id'] != $merchant['organizer_id']) {
            return $this->jsonResponse(['status' => 0, 'message' => '绑定的商家与使用码不符,请联系客服']);
        }

        if (!is_numeric($code)) {
            return $this->jsonResponse(['status' => 0, 'message' => '验证码不存在或格式异常']);
        }

        if ($merchant['shop_id']) {
            $store_id = $merchant['shop_id'];
            $type = 1;
        } else {
            $store_id = $merchant['organizer_id'];
            $type = 2;
        }

        $data = $this->UseCode($store_id, $type, $code);
        return $this->jsonResponse($data);

    }


    //查询绑定的商家或组织者
    protected function QueryUserBind($uid)
    {
        $data = array(
            'shop_id' => 0,
            'shop_name' => '',
            'organizer_id' => 0,
            'organizer_name' => ''
        );
        $organizer_res = $this->_getPlayOrganizerTable()->get(array('bind_uid' => $uid));
        if ($organizer_res) {
            $data['organizer_id'] = $organizer_res->id;
            $data['organizer_name'] = $organizer_res->name;
        }
        $shop_res = $this->_getPlayShopTable()->get(array('bind_uid' => $uid));
        if ($shop_res) {
            $data['shop_id'] = $shop_res->shop_id;
            $data['shop_name'] = $shop_res->shop_name;
        }
        return $data;

    }


}
