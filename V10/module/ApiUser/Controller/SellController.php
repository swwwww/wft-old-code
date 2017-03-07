<?php

namespace ApiUser\Controller;

use Deyi\BaseController;
use Deyi\GetCacheData\CityCache;
use Zend\Mvc\Controller\AbstractActionController;
use Deyi\JsonResponse;
use Zend\Http\Response;
use Deyi\Seller\Seller;

class SellController extends AbstractActionController
{

    use BaseController;
    use JsonResponse;

    /**
     *销售员中心
     */
    public function infoAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = (int)$this->getParams('uid');
        $page = (int)$this->getParams('page', 0);
        $pageNum = (int)$this->getParams('page_num', 10);
        $page = $page < 1 ? 1 : $page;
        $pageNum = $pageNum < 1 ? 10 : $pageNum;
        $start = ($page - 1) * $pageNum;

        $Seller = new Seller();

        $right = $Seller->isRight($uid);
        if (!$right) {
            return $this->jsonResponseError('用户不存在');
        }

        $data = array('income_statement' => array());

        $detail = $Seller->fetchSellFlow($start, $pageNum, $uid);

        foreach ($detail AS $value) {
            $data['income_statement'][] = array(
                'object_type' => $value['sell_type'],
                'sell_status' => $value['sell_status'],
                'dateline' => $value['update_time'],
                'flow_money' => ($value['sell_type'] == 3 ||  $value['sell_type'] == 4) ? $value['price'] : $value['rebate'],
                'describe' => $value['description'],
            );
        }

        return $this->jsonResponse($data);

    }

    /**
     * 推广商品活动列表
     */
    public function goodsAction()
    {

        if (!$this->pass(false)) {
            return $this->failRequest();
        }

        $type = $this->getParams('sell_type', 'goods');
        $city = $this->getParams('city', 'WH');
        $is_seller = $this->getParams('is_seller', 0);

        if (!array_key_exists($city, CityCache::getCities())) {
            $city = 'WH';
        }

        $seller_id = intval($this->getParams('seller_id', 0));
        $page = (int)$this->getParams('page', 0);
        $pageNum = (int)$this->getParams('page_num', 10);
        $page = $page < 1 ? 1 : $page;
        $pageNum = $pageNum < 1 ? 10 : $pageNum;
        $start = ($page - 1) * $pageNum;
        if (!in_array($type, array('goods', 'activity'))) {
            return $this->jsonResponseError('类型不存在');
        }


        $Seller = new Seller();
        $result = $Seller->fetchGoodsList($start, $pageNum, $type, $city);
        $data = array(
            'list' => array(),
        );

        if (is_array($result['list'])) {

            foreach ($result['list'] AS $value) {
                $data['list'][] = array(
                    'id' => $value['id'],
                    'cover' => $value['cover'],
                    'title' => $value['title'],
                    'price' => $value['price'],
                    'low_money' => $value['low_money'],
                    'buy_number' => $value['buy_number'],
                    'start_time' => $value['start_time'],
                    'end_time' => $value['end_time'],
                    'events_num' => $value['events_num'],
                    'address' => $value['address'],
                    'seller_id' => $seller_id,
                    'pre_income' => array(
                        'min' => $value['pre_income']['min'],
                        'max' => $value['pre_income']['max'],
                    ),
                    'is_seller' => $is_seller,
                );
            }

        }

        return $this->jsonResponse($data);

    }

    public function withdrawAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = (int)$this->getParams('uid');
        $money = floatval($this->getParams('money', 0));

        $Seller = new Seller();

        if ($money < $Seller->out_money) {
            return $this->jsonResponseError('可提现的钱不足');
        }

        $res = $Seller->withdraw($uid, $money);

        return $this->jsonResponse($res);

    }

    /**
     * 规则说明
     *
     * 规则说明页面 在 web/h5/distribution
     */


}
