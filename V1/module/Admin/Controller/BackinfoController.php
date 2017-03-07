<?php

namespace Admin\Controller;

use Deyi\GetCacheData\CityCache;
use Deyi\JsonResponse;
use Deyi\Paginator;

class BackinfoController extends BasisController
{
    use JsonResponse;

    public function indexAction()
    {
        $page = (int)$this->getQuery('p', 1);
        $pagesum = 10;
        $where = array();
        $start = ($page - 1) * $pagesum;

        $city = $this->chooseCity();
        $where['city'] = $city;

        //获得分页数据
        $data = $this->_getPlayFeedbackTable()->getFeedbackList($where, $start, $pagesum);
        //获得总数量
        $count = $this->_getPlayFeedbackTable()->getFeedbackList($where, 0, 0)->count();
        //创建分页
        $url = '/wftadlogin/backinfo';
        $paginator = new Paginator($page, $count, $pagesum, $url);

        $cities = $this->getAllCities();

        return array(
            'data' => $data,
            'pagedata' => $paginator->getHtml(),
            'filtercity' => CityCache::getFilterCity($city,1),
            'city' => $cities
        );
    }

    public function doAction()
    {
        $id = (int)$this->getQuery('id');
        $statu = $this->_getPlayFeedbackTable()->fetchLimit(0, 1, array('is_ok'), array('id'=>$id))->current();
        if ($statu) {
            $this->_getPlayFeedbackTable()->update(array('is_ok' => 1), array('id' => $id));
            return $this->jsonResponsePage(array('status' => 1, 'message' => '操作成功'));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '该条数据有误'));
        }
    }

    //获取商家入驻记录
    public function joinMesAction() {

        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $where = array();
        $start = ($page - 1) * $pageSum;

        $order = array(
            '_id' => -1
        );

        //获得分页数据
        $data = $this->_getMdbJoinTogether()->find($where)->limit($pageSum)->skip($start)->sort($order);
        //获得总数量
        $count = $this->_getMdbJoinTogether()->find($where)->count();
        //创建分页
        $url = '/wftadlogin/backinfo/joinMes';
        $paging = new Paginator($page, $count, $pageSum, $url);

        return array(
            'data' => $data,
            'pageData' => $paging->getHtml(),
        );
    }

    public function doJoinAction()
    {

        $id = $this->getQuery('id');
        $result = $this->_getMdbJoinTogether()->update(array('_id' => new \MongoId($id)), array('$set' => array("is_ok" => 1)));
        return $this->jsonResponsePage(array('status' => 1, 'message' => '操作成功'));

    }

}
