<?php

namespace Admin\Controller;

use Deyi\JsonResponse;
use Deyi\Validation;
use Deyi\ImageProcessing;
use Zend\View\Model\ViewModel;

class StrategyController extends BasisController
{
    use JsonResponse;

    //新建或修改
    public function newAction()
    {

        $sid = (int)$this->getQuery('sid'); //游玩地id
        $id = (int)$this->getQuery('id'); //攻略id

        if (!$sid && !$id) {
            exit('非法操作');
        }

        $strategyData = null;
        if ($id) {
            $strategyData = $this->_getPlayShopStrategyTable()->get(array('id' => $id));
            $shopData = $this->_getPlayShopTable()->get(array('shop_id' => $strategyData->sid));
        } else {
            $shopData = $this->_getPlayShopTable()->get(array('shop_id' => $sid));
        }

        $vm = new viewModel(
            array(
                'shopData' => $shopData,
                'strategyData' => $strategyData,
            )
        );
        return $vm;
    }

    //保存
    public function saveAction()
    {
        $id = (int)$this->getPost('id');
        $sid = (int)$this->getPost('sid');
        $give_uid = (int)$this->getPost('give_uid', 0);
        $give_username = $this->getPost('give_name');
        $suit_month = $this->getPost('suit_month', '');
        $information = trim($this->getPost('editorValue'));
        $title = $this->getPost('title');
        $suit_month = $suit_month ? (int)$suit_month : '';

        $cover = $this->getPost('img');

        if (!$information) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '详情未填写'));
        }

        if (!$title) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '标题未填写'));
        }

        if ($suit_month && ($suit_month < 0 || $suit_month > 12)) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '适合月份'));
        }

        $placeData = $this->_getPlayShopTable()->get(array('shop_id' => $sid));
        if (!$placeData) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
        }

        if (!$give_uid && (!$give_username || !$cover) ) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '用户信息'));
        }

        if (!$cover) {
             if (!$give_uid) {
                 return $this->jsonResponsePage(array('status' => 0, 'message' => '图片'));
             }

            // todo 通过uid获取img
            $cover = $this->_getPlayUserTable()->get(array('uid' => $give_uid))->img;

        } else {
            $cover_class = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $cover);
            $cover_status = $cover_class->scaleResizeImage(120, 120);
            if ($cover_status) {
                $cover_status->save($_SERVER['DOCUMENT_ROOT'] . $cover);
            }
        }

        $data = array(
            'give_uid' => $give_uid,
            'give_username' => $give_username,
            'give_image' => $cover,
            'suit_month' => $suit_month,
            'title' => $title,
            'sid' => $sid,
            'information' => $information,
        );


        if ($id) {
            $status = $this->_getPlayShopStrategyTable()->update($data, array('id' => $id));
            return $this->jsonResponsePage(array('status' => 1));
        }

        $data['editor_id'] = $_COOKIE['id'];
        $data['editor'] = $_COOKIE['user'];
        $data['dateline'] = time();
        $data['status'] = 0;

        $status = $this->_getPlayShopStrategyTable()->insert($data);
        return $this->jsonResponsePage(array('status' => 1));

    }

    //更新
    public function updateAction() {
        $id = (int)$this->getQuery('sid', 0); //对象id
        $type = $this->getQuery('type', null); //操作类型

        if (!in_array($type, array('hidden', 'show', 'first'))) {
            return $this->_Goto('非法操作');
        }

        $strategyData = $this->_getPlayShopStrategyTable()->get(array('id' => $id));

        if (!$strategyData) {
            return $this->_Goto('非法操作');
        }

        if ($type == 'hidden') { //取消显示
            $count = $this->_getPlayShopStrategyTable()->fetchCount(array('status > 0', 'sid' => $strategyData->sid));
            if ($count < 2) {
                return $this->_Goto('最少一个显示');
            }
            $status = $this->_getPlayShopStrategyTable()->update(array('status' => 0), array('id' => $id));
            return $this->_Goto($status ? '成功' : '失败');
        }

        if ($type == 'show') { //显示
            $status = $this->_getPlayShopStrategyTable()->update(array('status' => 1), array('id' => $id));
            return $this->_Goto($status ? '成功' : '失败');
        }

        if ($type == 'first') { //首选
            $flag = $this->_getPlayShopStrategyTable()->fetchAll(array('status' => 2, 'sid' => $strategyData->sid));
            if ($flag->count()) {
                return $this->_Goto('已有首选的了');
            }
            $status = $this->_getPlayShopStrategyTable()->update(array('status' => 2), array('id' => $id));
            return $this->_Goto($status ? '成功' : '失败');
        }

        return $this->_Goto('穿越啦');


    }


}
