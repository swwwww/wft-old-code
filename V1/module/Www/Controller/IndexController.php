<?php

namespace Www\Controller;

use Deyi\ImageProcessing;
use Deyi\JsonResponse;
use Deyi\Paginator;
use Deyi\BaseController;
use library\Service\System\Cache\RedCache;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    public function indexAction() {

        $vm = new ViewModel(array(
            'news' => $this->_fetchNews(),
        ));

        return $vm;
    }

    public function businessAction() {
        $vm = new ViewModel();
        return $vm;
    }

    public function schoolAction() {
        $vm = new ViewModel(array(
            'news' => $this->_fetchNews(),
        ));
        return $vm;
    }

    public function articalAction() {

        $type = $this->getQuery('type', 1);
        $page = $this->getQuery('p', 1);
        $pageNum = 6;
        $start = ((($page - 1) * $pageNum) >= 0 ) ? ($page - 1) * $pageNum : 0;

        $start_time = time() - 1728000;
        $where = array('dateline' => array('$gt' => $start_time));
        $data = $this->_getMdbWeiPost()->find($where)->limit($pageNum)->skip($start)->sort(array('dateline' => -1));
        $count = $this->_getMdbWeiPost()->find($where)->count();
        $url = '/www/index/artical';
        $paging = new Paginator($page, $count, $pageNum, $url);
        $vm = new ViewModel(array(
            'type' => $type,
            'data' => $data,
            'page' => $paging->getHtml(),
        ));
        return $vm;
    }

    public function joinAction() {
        $vm = new ViewModel();
        return $vm;
    }

    public function saveLinkerAction() {

        $result = file_get_contents("php://input");

        $data = json_decode($result, true);

        if (!$data['market_user']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '联系人姓名'));
        }

        if (mb_strlen($data['market_user']) < 2 || mb_strlen($data['market_user']) > 15) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写正确的联系人姓名'));
        }

        if (!$data['market_phone']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '联系人电话'));
        }

        if (!preg_match("/1[34578]{1}\d{9}$/",$data['market_phone'])) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写正确的手机号码'));
        }

        $quick_click = RedCache::get('www_join_together'. $_SERVER['REMOTE_ADDR']);
        if ($quick_click) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请1分钟后再试'));
        }

        $flag = $this->_getMdbJoinTogether()->findOne(array('market_phone' => $data['market_phone']));
        if ($flag) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '您已经提交了，请等待工作人员跟您回复'));
        }

        $data['insert_time'] = date('Y-m-d H:i:s', time());

        $this->_getMdbJoinTogether()->insert($data);

        RedCache::set('www_join_together'. $_SERVER['REMOTE_ADDR'], true, 60);

        return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));

    }

    private function _fetchNews() {

        $result = array();

        $data = $this->_getMdbWeiPost()->find(array())->limit(4)->skip(0)->sort(array('status' => -1, 'dateline' => -1));

        if ($data->count()) {
            foreach ($data as $res) {
                $result[] = array(
                    'title' => $res['title'], //标题
                    'thumb_url' => $res['thumb_url'],
                    'url' => $res['url'],
                    'dateline' => $res['dateline'],
                    'type_name' => $res['type_name'],
                );
            }
        }

        //todo 缓存起来 1天

        return $result;

    }



}
