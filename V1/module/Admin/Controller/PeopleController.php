<?php

namespace Admin\Controller;

use Deyi\JsonResponse;
use Deyi\Paginator;
use Deyi\Validation;
use Deyi\ImageProcessing;
use Zend\View\Model\ViewModel;

class PeopleController extends BasisController
{
    use JsonResponse;

    //商务组列表
    public function businessAction() {
        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;

        $where = array(
            'city' => $_COOKIE['city'],
        );

        $businessData = $this->_getPlayBusinessGroupTable()->fetchLimit($start,$pageSum,array(),$where);

        $count =$this->_getPlayBusinessGroupTable()->fetchCount($where);
        $url = '/wftadlogin/people/business';
        $paging = new Paginator($page, $count, $pageSum, $url);

        $vm = new ViewModel(array(
            'data'=>$businessData,
            'pageData'=>$paging->getHtml(),
            'city' => $this->getAllCities(),
        ));

        return $vm;
    }

    //添加商务组
    public function newBusinessAction() {
        //
    }

    //商务组保存
    public function saveBusinessAction() {
        $name = trim($this->getPost('name'));
        $data = array(
            'name' => $name,
            'city' => $_COOKIE['city'],
        );

        if (!$name) {
            return $this->_Goto('不能为空');
        }

        $flag = $this->_getPlayBusinessGroupTable()->get($data);
        if ($flag) {
            return $this->_Goto('该成员已经存在哦');
        }

        $this->_getPlayBusinessGroupTable()->insert($data);

        return $this->_Goto('成功', '/wftadlogin/people/business');
    }





}
