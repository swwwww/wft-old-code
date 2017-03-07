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

class AuthAccessController extends BasisController
{
    use JsonResponse;

    public function indexAction()
    {
        $page = (int)$this->getQuery('p', 1);

        $pageSum = 10;
        $data = $this->_getAuthAccessTable()->fetchLimit(($page-1)*$pageSum, $pageSum, array(), array(), array('uid' => 'DESC'))->toArray();;
        $count = $this->_getAuthAccessTable()->fetchCount(array());
        $url = '/wftadlogin/authaccess';

        $group_id = [];
        foreach($data as $v){
            $group_id[] = $v['group_id'];
        }

        $group = $this->_getAuthGroupTable()->fetchLimit(($page-1)*$pageSum, $pageSum, array(), array('id'=>$group_id));
        $group = $group->toArray();
        $groups = [];
        foreach($group as $g){
            $groups[$g['id']] = $g;
        }

        $access = [];
        foreach($data as $v){
            $v['group_name'] = $groups[$v['group_id']]['title']?:'';
            $access[] = $v;
        }

        $paginator = new Paginator($page, $count, $pageSum, $url);
        $vm = new viewModel(
            array(
                'data' => $access,
                'pageData' => $paginator->getHtml(),
            )
        );

        return $vm;
    }

    public function saveAction() {

        $uid = trim($this->getPost('uid', ''));
        $groupid = trim($this->getPost('group_id', ''));
        $otherauth = trim($this->getPost('other_auth', ''));

        $data = array(
            'uid' => $uid,
            'group_id' => $groupid,
            'other_auth' => $otherauth,
        );

        $flag = $this->_getAuthAccessTable()->get(['uid'=>$uid]);
        if ( $flag) {
            $status = $this->_getAuthAccessTable()->update($data,['uid'=>$uid]);
        }else{
            $status = $this->_getAuthAccessTable()->insert($data);
        }

        if (!$status) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '保存失败'));
        }
        return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));
    }

    public function newAction() {
        $uid = (int)$this->getQuery('uid');

        $data = array();

        if ($uid) {
            $data = $this->_getAuthAccessTable()->get(array('uid' => $uid));
        }

        $vm = new ViewModel(
            array(
                'data' => $data ?: [],
            )
        );
        return $vm;
    }

}