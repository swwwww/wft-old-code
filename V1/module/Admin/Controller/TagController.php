<?php

namespace Admin\Controller;

use Deyi\JsonResponse;
use Deyi\Paginator;
use library\Service\System\Cache\RedCache;
use Zend\Db\Sql\Predicate\Expression;
use Zend\View\Model\ViewModel;

class TagController extends BasisController
{
    use JsonResponse;

    public function indexAction()
    {

        $page = (int)$this->getQuery('p', 1);

        $type = (int)$this->getQuery('type', 0);  //特权标签为新增


        $city = $this->getAdminCity();
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;
        $where = array(
            'tag_city' => $city,
            'type' => $type
        );

        $order = array(
            'sort' => "DESC",
        );

        $data = $this->_getPlayTagsTable()->fetchLimit($start, $pageSum, array(), $where, $order);
        $count = $this->_getPlayTagsTable()->fetchCount($where);
        $url = '/wftadlogin/tag';
        $paginator = new Paginator($page, $count, $pageSum, $url);


        $vm = new viewModel(
            array(
                'data' => $data->toArray(),
                'pageData' => $paginator->getHtml(),
            )
        );
        return $vm;
    }

    public function saveAction()
    {
        RedCache::del('D:GL');
        $id = $this->getPost('id');
        $tag_name = trim($this->getPost('tag_name', ''));
        $desc = trim($this->getPost('desc', ''));
        $img = trim($this->getPost('img', ''));
        $type = trim($this->getPost('type', 1));

        $city = $this->getAdminCity();
        $data = array(
            'tag_name' => $tag_name,
            'tag_city' => $city,
            'desc' => $desc,
            'img' => $img,
            'type' => $type,
        );


        if ($id) {
            $status = $this->_getPlayTagsTable()->update($data, array('id' => $id));
        } else {
            $flag = $this->_getPlayTagsTable()->get($data);
            if (!$tag_name || $flag) {
                return $this->_Goto('名称重复');
            }
            $status = $this->_getPlayTagsTable()->insert($data);

            $id = $this->_getPlayTagsTable()->getlastInsertValue();
            $status2 = $this->_getPlayTagsTable()->update(array('sort' => $id), array('id' => $id));

        }


        return $this->_Goto($status ? '成功' : '失败', '/wftadlogin/tag?type=1');
    }

    //排序上移动
    public function sortAction()
    {
        $id = $this->getQuery('id');
        $sort = trim($this->getQuery('sort', 1)); 
        $t=$this->getQuery('t');
        RedCache::del('D:GL');

        $status2 = $this->_getPlayTagsTable()->update(array('sort' => $t), array('sort' => $sort));

        $status = $this->_getPlayTagsTable()->update(array('sort' => $sort), array('id' => $id));


        return $this->_Goto($status ? '成功' : '失败', '/wftadlogin/tag?type=1');

    }

    public function updateAction()
    {
        RedCache::del('D:GL');
        $type = $this->getQuery('type', '');
        $id = (int)$this->getQuery('id', '');
        if ($type == 'del' && $id) {

            //todo 判断是否有关联的
            $flag = $this->_getPlayTagsLinkTable()->get(array('tag_id' => $id));
            if ($flag) {
                return $this->_Goto('该标签(属性) 有关联, 请勿删除');
            }

            $where = array(
                'tag_city' => $_COOKIE['city'],
                'id' => $id
            );

            $status = $this->_getPlayTagsTable()->delete($where);
            return $this->_Goto($status ? '成功' : '失败');
        }

        exit('禁止入内');

    }

    public function editAction()
    {
        $id = $this->getQuery('id');

        $res = array();
        if ($id) {
            $res = $this->_getPlayTagsTable()->get(array('id' => $id));
        }
        return array('row' => $res);


    }

}
