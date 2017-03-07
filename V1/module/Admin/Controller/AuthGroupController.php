<?php
/**
 * 权限控制模块
 * Date: 15-12-9
 * Time: 上午10:57
 */

namespace Admin\Controller;

use Deyi\JsonResponse;
use Deyi\Paginator;
use Zend\View\Model\ViewModel;
use Deyi\BaseController;

class AuthGroupController extends BasisController
{
    use JsonResponse;

    public function indexAction()
    {
        $page = (int)$this->getQuery('p', 1);
        $show = $this->getQuery('show', 1);
        $pageSum = 10;
        $data = $this->_getAuthGroupTable()->fetchLimit(($page-1)*$pageSum, $pageSum, array(), array(), array('id' => 'DESC'));
        $count = $this->_getAuthGroupTable()->fetchCount(array());
        $url = '/wftadlogin/authgroup';

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

        $module = trim($this->getPost('module', ''));
        $id = trim($this->getPost('id', 0));
        $type = trim($this->getPost('type', ''));
        $title = trim($this->getPost('title', ''));
        $description = trim($this->getPost('description', ''));
        $status = trim($this->getPost('status', ''));
//        $rules = trim($this->getPost('rules', ''));

        $data = array(
            'module' => $module,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'status' => $status,
//            'rules' => $rules,
        );

        $flag = $this->_getAuthGroupTable()->get(['id'=>$id]);
        if ( $flag) {
            $status = $this->_getAuthGroupTable()->update($data,['id'=>$id]);
        }else{
            $status = $this->_getAuthGroupTable()->insert($data);
        }

        if (!$status) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '保存失败'));
        }
        return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));

    }

    public function rulesAction() {
        $id = trim($this->getPost('id', 0));
        $rules = $this->getPost('rule', []);
        $status = false;

        if(count($rules) === 0){
            return $this->jsonResponsePage(array('status' => 0, 'message' => '权限设置不能为空'));
        }

        $data = array(
            'rules' => implode(',',$rules)
        );

        $flag = $this->_getAuthGroupTable()->get(['id'=>$id]);
        if ( $flag) {
            $status = $this->_getAuthGroupTable()->update($data,['id'=>$id]);
        }else{
            return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
        }

        if (!$status) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '保存失败'));
        }
        return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));
    }

    public function newAction() {
        $id = (int)$this->getQuery('id');

        $data = array();

        if ($id) {
            $data = $this->_getAuthGroupTable()->get(array('id' => $id));
        }

        $vm = new ViewModel(
            array(
                'data' => $data ?: [],
                'id' => $id ?: 0,
            )
        );
        return $vm;
    }

    public function changeAction() {
        $type = $this->getQuery('type');
        $id = (int)$this->getQuery('id');
        if ($type === 'del' && $id !== 0 ) {
            $status = $this->_getAuthGroupTable()->update(array('status' => 0), array('id' => $id));
            if (!$status) {
                return $this->_Goto('删除失败');
            }

            return $this->_Goto('成功');
        }
        return $this->_Goto('非法操作');
    }
}