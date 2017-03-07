<?php

namespace Admin\Controller;


use Deyi\JsonResponse;
use library\Service\System\Cache\RedCache;
use Zend\View\Model\ViewModel;
use Deyi\GeTui\GeTui;
use Deyi\Paginator;

class GeTuiController extends BasisController
{
    use JsonResponse;

    //推送类型 记录
    public function indexAction() {
        $page = (int)$this->getQuery('p', 1);
        $show = $this->getQuery('show', 1);
        $pageSum = 10;
        if ($show == 1) {
            $data = $this->_getPlayPushTable()->fetchLimit(($page-1)*$pageSum, $pageSum, array(), array('push_type > ?' => 3), array('id' => 'DESC'));
            $url = '/wftadlogin/getui';
            $count = $this->_getPlayPushTable()->fetchCount(array('result > ?' => 0, 'push_type > ?' => 3));
        } elseif ($show == 2) {
            $data = $this->_getPlayPushTable()->fetchLimit(($page-1)*$pageSum, $pageSum, array(), array('push_type < ?' => 3));
            $url = '/wftadlogin/getui';
            $count = $this->_getPlayPushTable()->fetchCount(array('push_type < ?' => 3));
        } else {
            exit('are you kidding');
        }

        $paginator = new Paginator($page, $count, $pageSum, $url);
        $vm = new viewModel(
            array(
                'data' => $data,
                'pageData' => $paginator->getHtml(),
                'activePeople' => array('全体用户', '安卓', '苹果'),
                'show' => $show,
                'cat' => array(
                    '1' => '专题',
                    '2' => '卡券',
                    '3' => '商品',
                    '4' => '游玩地',
                    '5' => '商家 活动组织者',
                    '6' => '首页',
                    '9' => '遛娃活动',
                    '18'=> 'Html5页面URL',
                ),
                'result' => array(
                    '0' => '未推送',
                    '1' => '成功',
                    '2' => '失败',
                ),
            )
        );
        return $vm;
    }

    //通过id和类型获得推送对象
    public function getObjectTitleAction(){
        $id = $this->getQuery('id',0);
        $type = $this->getQuery('type',0);

        if(!$id or !$type){
            $this->jsonResponsePage(['status'=>0,'message'=>'idtype']);
        }
        /**
         *
        '1' => '专题',
        '2' => '卡券',
        '3' => '商品',
        '4' => '游玩地',
        '5' => '商家 活动组织者',
        '6' => '首页',
        '9' => '遛娃活动',
         */
        if($type == 1){
            $data = $this->_getPlayActivityTable()->fetchLimit(0, 1,
                ['ac_name', 'status','s_time','e_time'],
                array('id' => $id))->current();
            if(!$data or $data->status < 0){
                return $this->jsonResponsePage(array('status' => 0, 'message' => '无效id 或 '.$data->ac_name.'已下架'));
            }
            return $this->jsonResponsePage(array('status' => 1, 'message' => $data->ac_name));

        }elseif($type == 2){
            $data = $this->_getPlayCouponsTable()->fetchLimit(0, 1,
                ['coupon_name', 'coupon_status'],
                array('coupon_id' => $id))->current();
            if(!$data or $data->coupon_status < 1){
                return $this->jsonResponsePage(array('status' => 0, 'message' => "无效id 或 {$data->coupon_name}已下架"));
            }
            return $this->jsonResponsePage(array('status' => 1, 'message' => $data->coupon_name));
        }elseif($type == 3){
            $data = $this->_getPlayOrganizerGameTable()->fetchLimit(0, 1, ['title', 'status'],
                array('id' => (int)$id))->current();
            if(!$data or $data->status < 1){
                return $this->jsonResponsePage(array('status' => 0, 'message' => "无效id 或 {$data->title}已下架"));
            }
            return $this->jsonResponsePage(array('status' => 1, 'message' => $data->title));
        }elseif($type == 4){
            $data = $this->_getPlayShopTable()->fetchLimit(0, 1,
                ['shop_status', 'shop_name', ],
                array('shop_id' => $id))->current();
            if(!$data or $data->shop_status < 0){
                return $this->jsonResponsePage(array('status' => 0, 'message' => "无效id 或 {$data->shop_name}已关闭"));
            }
            return $this->jsonResponsePage(array('status' => 1, 'message' => $data->shop_name));
        }elseif($type == 5){
            $data = $this->_getPlayOrganizerTable()->fetchLimit(0, 1, ['name', 'status'],
                array('id' => (int)$id))->current();
            if(!$data or $data->status < 1){
                return $this->jsonResponsePage(array('status' => 0, 'message' => "无效id 或 {$data->name}已删除"));
            }
            return $this->jsonResponsePage(array('status' => 1, 'message' => $data->name));
        }elseif($type == 6){
            return $this->jsonResponsePage(array('status' => 0, 'message' => '你选择的推送内容为首页'));
        }elseif($type == 9){
            $data = $this->_getPlayExcerciseBaseTable()->fetchLimit(0, 1, ['name', 'release_status'],
                array('id' => $id))->current();
            if(!$data or $data->release_status < 1){
                return $this->jsonResponsePage(array('status' => 0, 'message' => "无效id 或 {$data->name}已删除"));
            }
            return $this->jsonResponsePage(array('status' => 1, 'message' => $data->name));
        }
    }

    public function newAction() {
        $data = array();
        $downtown = $_COOKIE['city'];

        if (!in_array($downtown, array_flip($this->getAllCities()))) {
            return $this->_Goto('非法操作');
        }

        $cityData = $this->_getPlayCityTable()->get(array('city' => $downtown));

        if (!$cityData || !$cityData->city_number) {
            return $this->_Goto('请先添加该城市的编号');
        }

        $city = array(
            $cityData->city_number => $this->getAllCities()[$downtown],
        );

        $vm = new viewModel(
            array(
                'data' => $data,
                'city' => $city
            )
        );
        return $vm;
    }

    public function saveAction()
    {

        $title = $this->getPost('title') ? $this->getPost('title') : '玩翻天';
        $info = $this->getPost('info');
        $object_title = $this->getPost('link_title','');
        $link_type = (int)$this->getPost('link_type');
        $link_id = $this->getPost('link_id');
        $link_people = (int)$this->getPost('link_people');
        $city = $this->getPost('city');
        $area = $this->getPost('area');
        $url  = $this->getPost('url');
        $send_time = strtotime($this->getPost('time') . $this->getPost('timel')); //推送时间
        $timer = time();
        $duration = (int)$this->getPost('duration');

        if ($duration < 1 || $duration > 12) {
            return $this->_Goto('请重新设置持续时间');
        }

        if (strlen($info) > 105 || strlen($info) < 1) {
            return $this->_Goto('推送正文字数未达要求');
        }

        if (!$title || strlen($title) > 40) {
            return $this->_Goto('标题不符合字数限制');
        }

        if (!in_array($link_type, array(1, 2, 3, 4, 5, 6, 9, 18))) {
            return $this->_Goto('非法操作');
        }

        //防止多次提交
        if (RedCache::get('tui_data')) {
            return $this->_Goto('已推送 请等待结果');
        } else {
            RedCache::set('tui_data', 1, 240);
        }

        switch ($link_type) {
            case 1:
                $status = $this->_getPlayActivityTable()->get(array('id' => $link_id));
                break;
            case 2:
                $status = $this->_getPlayCouponsTable()->get(array('coupon_id' => $link_id));
                break;
            case 3:
                $status = $this->_getPlayOrganizerGameTable()->get(array('id' => $link_id));
                break;
            case 4:
                $status = $this->_getPlayShopTable()->get(array('shop_id' => $link_id));
                break;
            case 5:
                $status = $this->_getPlayOrganizerTable()->get(array('id' => $link_id));
                break;
            case 6:
                $link_id = 0;
                $status = 1;
                break;
            case 9:
                $status = $this->_getPlayExcerciseBaseTable()->get(array('id' => $link_id));
                break;
            case 18:
                $status = 1;
                break;
            default:
                return $this->_Goto('非法操作');

        }

        if (!$status) {
            return $this->_Goto('请确认推送相关的存在');
        }

        $content = array(
            'title' => htmlspecialchars_decode($title, ENT_QUOTES),
            'info' => htmlspecialchars_decode($info, ENT_QUOTES),
            'type' => $link_type,
            'id' => $link_id,
            'time' => $timer,
            'url'  => $url,
        );


        if ($link_people) {
            $geTui = new GeTui();
            $uid = (int)$link_people;
            $user_data = $this->_getPlayUserTable()->get(array('uid' => $uid));
            $token = $user_data->token;
            $str = substr($token, 0, 10);
            $geTui->Push($uid . '__' . $str, htmlspecialchars_decode($info, ENT_QUOTES), json_encode($content, JSON_UNESCAPED_UNICODE));
            return $this->_Goto('已发送, 请等待');

        } else {

            if (($send_time - $timer) > 60) {
                $this->_getPlayPushTable()->insert(array(
                    'title' => htmlspecialchars($title),
                    'info' => htmlspecialchars($info),
                    'link_title' => htmlspecialchars($object_title),
                    'link_type' => $link_type,
                    'push_type' => 4,
                    'push_time' => $send_time,
                    'uptime' => $timer,
                    'link_id' => $link_id,
                    'result' => 0,
                    'city' => $city,
                    'area' => $area,
                    'url'  => $url,
                ));
                return $this->_Goto('已记录，请等待', '/wftadlogin/getui');

            } else {
                $task = date('Y_m_d_H', $timer);
                $geTui = new GeTui();
                $geTui->pushMessageToApp(htmlspecialchars_decode($info, ENT_QUOTES), json_encode($content, JSON_UNESCAPED_UNICODE), $task, $duration*3600*1000, $city);

                $this->_getPlayPushTable()->insert(array(
                    'title' => htmlspecialchars($title),
                    'info' => htmlspecialchars($info),
                    'link_title' => htmlspecialchars($object_title),
                    'link_type' => $link_type,
                    'push_type' => 4,
                    'push_time' => $timer,
                    'uptime' => $timer,
                    'link_id' => $link_id,
                    'result' => 1,
                    'city' => $city,
                    'area' => $area,
                    'url'  => $url,
                    'log' => '异步',
                ));

                return $this->_Goto('已发送, 请等待');
            }
        }
    }


    //删除
    public function updateAction() {

        $id = (int)$this->getQuery('id', 0);
        $type = $this->getQuery('type');
        $status = 0;
        if ($type == 'del') {
            $status = $this->_getPlayPushTable()->delete(array('id' => $id));
        }

        return $this->_Goto($status ? '成功' : '失败', '/wftadlogin/getui');
    }

    //停止推送任务
    public function stopAction() {

        $id = (int)$this->getQuery('id', 0);
        $pushData = $this->_getPlayPushTable()->get(array('id' => $id));

        if (!$pushData || !$pushData->log) {
            return $this->_Goto('该推送任务暂时无法停止');
        }

        $geTui = new GeTui();
        $geTui->stoptask($pushData->log);
        return $this->_Goto('成功');
    }



}
