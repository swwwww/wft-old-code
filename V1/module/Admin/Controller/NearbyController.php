<?php

namespace Admin\Controller;

use Deyi\JsonResponse;
use Deyi\Paginator;
use Zend\View\Model\ViewModel;

class NearbyController extends BasisController
{
    use JsonResponse;

    public function indexAction() {

    }

    public function restaurantAction() {

        $page = (int)$this->getQuery('p', 1);
        $city = $_COOKIE['city'];
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;
        $where = array(
            'city' => $city,
            'type' => 1,
        );

        $order = array(
            'dateline' => -1,
        );

        $data = $this->_getMdbNearBy()->find($where)->limit($pageSum)->skip($start)->sort($order);
        $count = $this->_getMdbNearBy()->find($where)->count();

        $url = '/wftadlogin/nearby/restaurant';
        $paginator = new Paginator($page, $count, $pageSum, $url);
        $vm = new viewModel(
            array(
                'data' => $data,
                'pageData' => $paginator->getHtml(),
            )
        );

        return $vm;
    }

    public function parkAction() {

        $page = (int)$this->getQuery('p', 1);
        $city = $_COOKIE['city'];
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;
        $where = array(
            'city' => $city,
            'type' => 2,
        );

        $order = array(
             'dateline' => -1,
        );

        $data = $this->_getMdbNearBy()->find($where)->limit($pageSum)->skip($start)->sort($order);
        $count = $this->_getMdbNearBy()->find($where)->count();
        $url = '/wftadlogin/nearby/park';
        $paginator = new Paginator($page, $count, $pageSum, $url);
        $vm = new viewModel(
            array(
                'data' => $data,
                'pageData' => $paginator->getHtml(),
            )
        );

        return $vm;
    }

    public function saveAction() {

        $id =  $this->getPost('id', 0); // 对象id
        $title = trim($this->getPost('title', '')); // 名称
        $address = trim($this->getPost('address', '')); // 地址
        $addr_x =  $this->getPost('addr_x', ''); //坐标 经度
        $addr_y =  $this->getPost('addr_y', ''); //坐标 纬度
        $type = (int)$this->getPost('type', 0); //1餐厅 2 游玩地

        if (!$id && !in_array($type, array(1, 2))) {
            return $this->_Goto('非法操作');
        }

        if (!$title || !$address || !$addr_x || !$addr_y) {
            return $this->_Goto('请认真填写相关选项');
        }

        $data = array(
            'addr_x' => $addr_x,
            'addr_y' => $addr_y,
            'title' => $title,
            'address' => $address,
            'addr' => array(
                'type' => 'Point',
                'coordinates' => array((float)$addr_x, (float)$addr_y))
        );

        if (!$id) {
            $data['city'] = $_COOKIE['city'];
            $data['dateline'] = time();
            $data['type'] = $type;
            $data['editor_id'] = (int)$_COOKIE['id'];
            $data['editor'] = $_COOKIE['user'];
            $status =  $this->_getMdbNearBy()->insert($data);
            $url = '/wftadlogin/nearby/'. (($type == 1) ? 'restaurant' : 'park');
        } else {

            $status =$this->_getMdbNearBy()->update(array('_id' => new \MongoId($id)), array('$set' => $data));
            $url = '/wftadlogin/nearby/new?id='. $id;
        }

        return $this->_Goto($status ? '成功' : '失败', $url);

    }

    public function newAction() {

        $id = $this->getQuery('id','');
        $type = (int)$this->getQuery('type','');
        $data = null;

        if ($id) {
            $data = $this->_getMdbNearBy()->findOne(array('_id' => new \MongoId($id)));
            $type = $data->type;
        }

        $vm = new viewModel(
            array(
                'type' => $type,
                'data' => $data,
            )
        );
        return $vm;
    }

    public function updateAction() {
        $type =  $this->getQuery('type', '');
        $id = $this->getQuery('id', '');
        if ($type === 'del' && $id) {
            $where = array(
                'city' => $_COOKIE['city'],
                '_id' => new \MongoId($id)
            );

            $status = $this->_getMdbNearBy()->remove($where);
            return $this->_Goto($status ? '成功' : '失败');
        }

        exit('禁止入内');
    }

}
