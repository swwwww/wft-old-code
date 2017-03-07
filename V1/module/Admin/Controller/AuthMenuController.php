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

class AuthMenuController extends BasisController
{
    use JsonResponse;

    public function indexAction()
    {
        $page = (int)$this->getQuery('p', 1);

        $pageSum = 10;

        $word = $this->getQuery('word', '');
        $where = [];
        if($word !== ''){
            $where['title like ? or url like ?'] = array('%'.$word.'%', '%'.$word.'%');
        }

        $data = $this->_getAuthMenuTable()->fetchLimit(($page-1)*$pageSum, $pageSum, array(), $where, array('id' => 'DESC'));
        $count = $this->_getAuthMenuTable()->fetchCount($where);
        $url = '/wftadlogin/authmenu';

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
        $title = trim($this->getPost('title', ''));
        $id = trim($this->getPost('id', 0));
        $pid = trim($this->getPost('pid', ''));
        $sort = trim($this->getPost('sort', ''));
        $module = trim($this->getPost('module', ''));
        $url = trim($this->getPost('url', ''));
        $hide = trim($this->getPost('hide', ''));
        $group = trim($this->getPost('group', ''));
        $is_dev = trim($this->getPost('is_dev', ''));
        $branch = (int)($this->getPost('branch', 0));

        $data = array(
            'title' => $title,
            'pid' => $pid,
            'sort' => $sort,
            'module' => $module,
            'url' => $url,
            'hide' => $hide,
            'group' => $group,
            'is_dev' => $is_dev,
            'branch' => $branch,
            'tip' => '',
        );

        $flag = $this->_getAuthMenuTable()->get(array('id' => $id));

        if ($flag) {
            $status = $this->_getAuthMenuTable()->update($data, array('id' => $id));
        } else {
            $status = $this->_getAuthMenuTable()->insert($data);
        }

        if (!$status) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '保存失败'));
        }
        return $this->jsonResponsePage(array('status' => 1, '成功'));
    }

    //访问授权
    public function accessAction(){
        $id = trim($this->getQuery('id', 0));

        $rules = $this->_getAuthGroupTable()->get(['id'=>$id]);
        $ruleIds = explode(',',$rules->rules);
        //表单选项
        $nodes = $this->returnNodes();

        $node_arr = [];
        foreach($nodes as $n){
            if(array_key_exists('child',$n) && is_array($n['child'])){
                $temp = $n; $temp['child'] = 1;
                if(in_array($temp['id'],$ruleIds)){
                    $temp['have'] = 1;
                }else{
                    $temp['have'] = 0;
                }
                $node_arr[] = $temp;
                foreach($n['child'] as $c){
                    //if(array_key_exists('operator',$c) && is_array($c['operator'])){
                        $temp = $c; $temp['operator'] = 1;
                        if(in_array($temp['id'],$ruleIds)){
                            $temp['have'] = 1;
                        }else{
                            $temp['have'] = 0;
                        }
                        $node_arr[] = $temp;
                        foreach($c['operator'] as $o){
                            if(in_array($o['id'],$ruleIds)){
                                $o['have'] = 1;
                            }else{
                                $o['have'] = 0;
                            }
                            $node_arr[] = $o;
                        }
                    //}
                }
            }else{
                if((int)$n['pid'] === 0){
                    if(in_array($n['id'],$ruleIds)){
                        $n['have'] = 1;
                    }else{
                        $n['have'] = 0;
                    }
                    $node_arr[] = $n;
                }
            }
        }

        $vm = new ViewModel(
            array(
                'node' => $node_arr,
                'gid'  => $id
            )
        );
        return $vm;
    }

    public function newAction() {
        $id = (int)$this->getQuery('id');

        $data = array();

        if ($id) {
            $data = $this->_getAuthMenuTable()->get(array('id' => $id));
        }

        $menus = $this->getMenu(-1)->toArray();
        $menus = $this->toFormatTree($menus);
        $menus = array_merge(array(0=>array('id'=>0,'title_show'=>'顶级菜单')), $menus);

        $vm = new ViewModel(
            array(
                'data' => $data ?: [],
                'menus' => $menus,
            )
        );
        return $vm;
    }


    private function getMenu($pid){
        $where['pid'] = $pid;
        if($pid === -1){
            $where = [];
        }
        $data = $this->_getAuthMenuTable()->fetchAll($where);
        return $data;
    }

    public function changeAction() {
        $type = $this->getQuery('type');
        $id = (int)$this->getQuery('id');
        if ($type === 'del' && $id !== 0 ) {
            $status = $this->_getAuthMenuTable()->delete(array('id' => $id));
            if (!$status) {
                return $this->_Goto('删除失败');
            }

            return $this->_Goto('成功');
        }
        if ($type === 'hide' && $id !== 0 ) {
            $status = $this->_getAuthMenuTable()->update(array('hide' => 1), array('id' => $id));
            if (!$status) {
                return $this->_Goto('隐藏失败');
            }

            return $this->_Goto('成功');
        }
        if ($type === 'show' && $id !== 0 ) {
            $status = $this->_getAuthMenuTable()->update(array('hide' => 0), array('id' => $id));
            if (!$status) {
                return $this->_Goto('开启失败');
            }

            return $this->_Goto('成功');
        }
        return $this->_Goto('非法操作');
    }
}