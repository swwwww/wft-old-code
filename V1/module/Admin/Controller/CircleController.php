<?php

namespace Admin\Controller;

use Deyi\Account\Account;
use Deyi\BaseController;
use Deyi\Coupon\Coupon;
use Deyi\GetCacheData\CityCache;
use Deyi\Integral\Integral;
use Deyi\JsonResponse;
use Deyi\Paginator;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Sql\Expression;
use Deyi\ImageProcessing;

class CircleController extends BasisController
{
    use JsonResponse;
    // //use BaseController;

    //圈子 => 圈子管理(总站)
    public function mainAction()
    {
        $page = (int)$this->getQuery('p', 1);
        $type = (int)$this->getQuery('type');
        $pageSum = 10;
        $start = ($page-1)*$pageSum;
        $id = $this->getQuery('id');
        $key = $this->getQuery('k');
        $uid = (int)$this->getQuery('uid');

        //默认条件 status > 0
        $where = array(
            'status' => array('$gt' => 0),
        );

        $where['pid'] = '0';

        //圈子类型
        if ($type) {
            $where['type'] = $type;
        }

        //圈子id
        if ($id) {
            $where['_id'] = new \MongoId($id);
        }

        //圈子搜索 标题
        if ($key) {
            $where['title'] = new \MongoRegex("/{$key}/");
        }

        //用户创建的圈子
        if ($uid) {
            $where['build_id'] = $uid;
        }

        $order = array(
            'dateline' => -1,
        );

        $mongo = $this->_getMongoDB();

        $cursor = $this->_getMdbSocialCircle()->find($where)->limit($pageSum)->skip($start)->sort($order);
        $count = $mongo->social_circle->find($where)->count();

        //创建分页
        $url = '/wftadlogin/circle/main';
        $paginator = new Paginator($page, $count, $pageSum, $url);

        return array(
            'data' => $cursor,
            'pagedata' => $paginator->getHtml(),
            'type' => array('1' => '私密', '2' => '公开'),
        );

    }
    //圈子 => 圈子管理(入口)
    public function indexAction()
    {
        $page = (int)$this->getQuery('p', 1);
        $type = (int)$this->getQuery('type');
        $pageSum = 10;
        $start = ($page-1)*$pageSum;
        $id = $this->getQuery('id');
        $key = $this->getQuery('k');
        $uid = (int)$this->getQuery('uid');

        //默认条件 status > 0
        $where = array(
            'status' => array('$gt' => 0),
        );

        $where['city'] = $this->getAdminCity();

        //圈子类型
        if ($type) {
            $where['type'] = $type;
        }

        //圈子id
        if ($id) {
            $where['_id'] = new \MongoId($id);
        }

        //圈子搜索 标题
        if ($key) {
            $where['title'] = new \MongoRegex("/{$key}/");
        }

        //用户创建的圈子
        if ($uid) {
            $where['build_id'] = $uid;
        }

        $order = array(
            'dateline' => -1,
        );

        $mongo = $this->_getMongoDB();

        $cursor = $this->_getMdbSocialCircle()->find($where)->limit($pageSum)->skip($start)->sort($order);
        $count = $mongo->social_circle->find($where)->count();

        //创建分页
        $url = '/wftadlogin/circle';
        $paginator = new Paginator($page, $count, $pageSum, $url);
        return array(
            'data' => $cursor,
            'pagedata' => $paginator->getHtml(),
            'type' => array('1' => '私密', '2' => '公开'),
        );

    }

    //圈子申请列表
    public function checkAction(){
        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page-1)*$pageSum;
        $uid = (int)$this->getQuery('uid');
        //用户创建的圈子
        if ($uid) {
            $where['build_id'] = $uid;
        }

        $where['status'] = array('$lte' => 0);
        $order = array(
            'dateline' => -1,
        );

        $mongo = $this->_getMongoDB();
        $cursor = $this->_getMdbSocialCircle()->find($where)->limit($pageSum)->skip($start)->sort($order);
        $count = $mongo->social_circle->find($where)->count();

        //创建分页
        $url = '/wftadlogin/circle';
        $paginator = new Paginator($page, $count, $pageSum, $url);
        return array(
            'data' => $cursor,
            'pagedata' => $paginator->getHtml(),
            'type' => array('1' => '私密', '2' => '公开'),
        );
    }

    //使用圈子
    public function useAction(){
        $id = $this->getQuery('id');
        if ($id) {
            $mongo = $this->_getMongoDB();
            $cursor = $mongo->social_circle->findOne(array('_id' => new \MongoId($id)));
        } else {
            return $this->_Goto('圈子不存在');
        }
        return array(
            'data' => $cursor,
        );

    }

    //执行使用操作
    public function douseAction(){
        $id = $this->getPost('id');
        $title = $this->getPost('title');
        $img = $this->getPost('img','');
        $thumb = $this->getPost('thumb','');
        $uid = $_COOKIE['id'];
        $mongo = null;
        if ($this->checkMid($id)) {
            $mongo = $this->_getMongoDB();
            $data = $mongo->social_circle->findOne(array('_id' => new \MongoId($id)));
        } else {
            return $this->_Goto('圈子不存在');
        }

        if($title){
            $data['title'] = $title;
        }

        if($img){
            $cover_class = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $img);
            $cover_status = $cover_class->scaleResizeImage(720, 360);
            if ($cover_status) {
                $cover_status->save($_SERVER['DOCUMENT_ROOT'] . $img);
            }
            $data['img'] = $img;
        }

        if($thumb){
            $surface_class = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $thumb);
            $surface_status = $surface_class->scaleResizeImage(360, 360);
            if ($surface_status) {
                $surface_status->save($_SERVER['DOCUMENT_ROOT'] . $thumb);
            }
            $data['thumb'] = $thumb;
        }

        $data['city'] = $this->getAdminCity();

        //判断是否已经使用过
        $isused = $this->_getMdbSocialCircle()->find(['city'=>$data['city'],'pid'=>(string)$id]);
        $isused = iterator_to_array($isused);

        if($isused){
            return $this->_Goto('圈子已经使用过');
        }

        $data['people'] = 1;
        $data['dateline'] = time();
        $data['status'] = 1;
        $data['build_id'] = $uid;
        $data['msg'] = 0;
        $data['msg_post'] = 0;
        $data['today_msg'] = 0;
        $data['today_msg_post'] = 0;
        $data['view_number'] = 0;
        $data['pid'] = (string)$id;
        unset($data['_id']);

        $mongo->social_circle->insert($data);

        $mongo->social_circle->update(array('_id' => new \MongoId($id)), array('$inc' => array('branch_number' => 1)), array('multiple' => false));

        //新增一个圈子 需要 往圈子成员加1
        $userData = $this->_getPlayUserTable()->get(array('uid' => $uid));

        $circle_users = array(
            'uid'=> $uid,
            'username' => $userData->username,
            'user_detail' => $userData->user_alias,
            'img'=> $userData->img,
            'dateline' => time(),
            'cid' => (string)$data['_id'],
            'c_name' => $title,
            'user_circles'=> json_decode($userData->user_circles),       //用户加入的其他圈子
            'status' => 1,
            'role' => 2
        );

        $mongo->social_circle_users->insert($circle_users);

        //新增一个圈子 更新用户表中user_circle
        $user_circles = array();
        $res = $mongo->social_circle_users->find(array('uid' => $uid))->sort(array('dateline' => -1))->limit(3);
        $user_circles[] = array('cid' => (string)$circle_users['_id'], 'c_name' => $title);
        foreach ($res as $v) {
            $user_circles[] = array('cid' => $v['cid'], 'c_name' => $v['c_name']);
        }
        $this->_getPlayUserTable()->update(array('user_circles' => json_encode($user_circles, JSON_UNESCAPED_UNICODE), 'join_circle' => new Expression('join_circle+1')), array('uid' => $uid));
        //更新用户之前加入的圈子数据
        $this->_getMdbsocialCircleUsers()->update(array('uid' => $uid), array('$set' => array('user_circles' => $user_circles)), array('multiple' => true));

        return $this->_Goto('成功', '/wftadlogin/circle/index');
    }

    //圈子 => 选择圈子
    public function chooseAction()
    {
        $page = (int)$this->getQuery('p', 1);
        $type = (int)$this->getQuery('type');
        $pageSum = 10;
        $start = ($page-1)*$pageSum;
        $id = $this->getQuery('id');
        $key = $this->getQuery('k');
        $uid = (int)$this->getQuery('uid');
        $city = $this->getAdminCity();

        //默认条件 status > 0
        $where = array(
            'status' => array('$gt' => 0),
        );

        $where['pid'] = '0';//主站的模板圈子

        //圈子类型
        if ($type) {
            $where['type'] = $type;
        }

        if(!$this->checkMid($id)){
           // return $this->_Goto('id 不存在');
        }

        //圈子id
        if ($id) {
            $where['_id'] = new \MongoId($id);
        }

        //圈子搜索 标题
        if ($key) {
            $where['title'] = new \MongoRegex("/{$key}/");
        }

        //用户创建的圈子
        if ($uid) {
            $where['build_id'] = $uid;
        }

        $order = array(
            'dateline' => -1,
        );

        $mongo = $this->_getMongoDB();
        $cursor = $this->_getMdbSocialCircle()->find($where)->limit($pageSum)->skip($start)->sort($order);
        $count = $mongo->social_circle->find($where)->count();

        //设置使用状态，区分是否使用过
        $isuse = $this->_getMdbSocialCircle()->find(['city'=>$city],['pid']);
        $isusearr = [];
        foreach($isuse as $i){
            if($i['pid'].$id){
                $isusearr[] = $i['pid'].$id;
            }
        }

        $canuse = [];
        foreach($cursor as $c){
            if(in_array($c['_id'].$id,$isusearr)){
                $canuse[$c['_id'].$id] = 1;
            }
        }

        //创建分页
        $url = '/wftadlogin/circle';
        $paginator = new Paginator($page, $count, $pageSum, $url);
        return array(
            'data' => $cursor,
            'canuse' => $canuse,
            'pagedata' => $paginator->getHtml(),
            'type' => array('1' => '私密', '2' => '公开'),
        );

    }

    //获取所有积分
    private function getIntegral($id){
        if(is_array($id)){
            foreach($id as $i){
                if(!$this->checkMid($i)){
                    return 0;
                }
            }
            $cc = $this->_getPlayIntegralTable()->fetchAll(['type'=>[15,16],'object_id'=>$id]);
        }elseif($this->checkMid($id)){
            $cc = $this->_getPlayIntegralTable()->fetchAll(['type'=>[15,16],'object_id'=>$id]);
        }

        $cc_info = [];
        if(false === $cc || count($cc) === 0){
            return [];
        }
        foreach($cc as $c){
            if(array_key_exists($c['object_id'],$cc_info)){
                $cc_info[$c['object_id']] += $c['total_score'];
            }else{
                $cc_info[$c['object_id']] = $c['total_score'];
            }

        }

        return $cc_info;
    }

    private function getAccount($id){
        if(is_array($id)){
            foreach($id as $i){
                if(!$this->checkMid($i)){
                    return 0;
                }
            }

            $cc = $this->_getPlayAccountLogTable()->fetchAll(['action_type_id'=>[4],'object_id'=>$id]);

        }elseif($this->checkMid($id)){
            $cc = $this->_getPlayAccountLogTable()->fetchAll(['action_type_id'=>[4],'object_id'=>$id]);
        }
        $cc_info = [];
        if(false === $cc || count($cc) === 0){
            return [];
        }
        foreach($cc as $c){
            if(array_key_exists($c['object_id'],$cc_info)){
                $cc_info[$c['object_id']] += $c['flow_money'];
            }else{
                $cc_info[$c['object_id']] = $c['flow_money'];
            }

        }

        return $cc_info;
    }

    private function getCashCoupon($id){
        if(is_array($id)){
            foreach($id as $i){
                if(!$this->checkMid($i)){
                    return 0;
                }
            }

            $cc = $this->_getCashCouponUserTable()->fetchAll(['get_type'=>[12],'get_object_id'=>$id,'city'=>$this->getAdminCity()]);

        }elseif($this->checkMid($id)){
            $cc = $this->_getCashCouponUserTable()->fetchAll(['get_type'=>[12],'get_object_id'=>$id,'city'=>$this->getAdminCity()]);
        }
        $cc_info = [];

        if(false === $cc || count($cc) === 0){
            return [];
        }
        foreach($cc as $c){
            if(array_key_exists($c['get_object_id'],$cc_info)){
                $cc_info[$c['get_object_id']] += $c['price'];
            }else{
                $cc_info[$c['get_object_id']] = $c['price'];
            }

        }

        return $cc_info;
    }

    //总站新增编辑
    public function newAction() {
        $id = $this->getQuery('id');
        $cursor = NULL;
        if ($id) {
            $mongo = $this->_getMongoDB();
            $cursor = $mongo->social_circle->findOne(array('_id' => new \MongoId($id)));
        } else {
            $right = $this->checkRight();
            if (!$right) {
                return $this->_Goto('该编辑账号 没有绑定uid', '/wftadlogin/setting/editor');
            }
        }
        return array(
            'data' => $cursor,
            'uid' => $cursor ? $cursor['build_id'] : $right,
        );
    }

    //我的申请
    public function myapplyAction(){
        $page = (int)$this->getQuery('p', 1);
        $type = (int)$this->getQuery('type');
        $pageSum = 10;
        $start = ($page-1)*$pageSum;
        $id = $this->getQuery('id');
        $key = $this->getQuery('k');
        $uid = (int)$this->getQuery('uid');

        //默认条件 status > 0
        $where = array(
            'status' => 0,
        );

        $where['city'] = $this->getAdminCity();

        //圈子类型
        if ($type) {
            $where['type'] = $type;
        }

        //圈子id
        if ($id) {
            $where['_id'] = new \MongoId($id);
        }

        //圈子搜索 标题
        if ($key) {
            $where['title'] = new \MongoRegex("/{$key}/");
        }

        //用户创建的圈子
        if ($uid) {
            $where['build_id'] = $uid;
        }

        $order = array(
            'dateline' => -1,
        );

        $mongo = $this->_getMongoDB();

        $cursor = $this->_getMdbSocialCircle()->find($where)->limit($pageSum)->skip($start)->sort($order);
        $count = $mongo->social_circle->find($where)->count();

        //创建分页
        $url = '/wftadlogin/circle';
        $paginator = new Paginator($page, $count, $pageSum, $url);
        return array(
            'data' => $cursor,
            'pagedata' => $paginator->getHtml(),
            'type' => array('1' => '私密', '2' => '公开'),
        );

    }

    public function saveAction() {
        $id = $this->getPost('id');
        $type = (int)$this->getPost('type');
        $title = $this->getPost('title');
        $introduce = $this->getPost('introduce');
        $img = $this->getPost('img');
        $thumb = $this->getPost('thumb');
        $uid = (int)$this->getPost('uid');

        $city = $this->getAdminCity();

        if (!$img || !$thumb) {
            return $this->_Goto('封面图 与缩略图');
        }

        $cover_class = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $img);
        $cover_status = $cover_class->scaleResizeImage(720, 360);
        if ($cover_status) {
            $cover_status->save($_SERVER['DOCUMENT_ROOT'] . $img);
        }

        $surface_class = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $thumb);
        $surface_status = $surface_class->scaleResizeImage(360, 360);
        if ($surface_status) {
            $surface_status->save($_SERVER['DOCUMENT_ROOT'] . $thumb);
        }

        $data = array(
            'type' => $type,
            'title' => $title,
            'introduce' => $introduce,
            'img' => $img,
            'thumb' => $thumb,
        );

        $mongo = $this->_getMongoDB();

        if ($id) {
            if(!$this->checkMid($id)){
                return $this->_Goto('参数错误');
            }
            $sc = $mongo->social_circle->find(array('_id' => new \MongoId($id)));
            $sc = iterator_to_array($sc);
            if(!$sc){
                return $this->_Goto('圈子不存在');
            }

            $c = $mongo->social_circle->find(array('title' => $title,'city'=>$sc[$id]['city'],'_id' => array('$ne'=>new \MongoId($id))));

            $c = iterator_to_array($c);
            if($c){
                return $this->_Goto('同名圈子已存在');
            }
            $where['_id'] = new \MongoId($id);
            if($city!=1){
                $where['city'] = $city;
            }
            $mongo->social_circle->update($where, array('$set' => $data));
        } else {
            if($this->getAdminCity()!=1){
                return $this->_Goto('没有权限');
            }
            $c = $mongo->social_circle->find(array('title' => $title,'build_id'=>$uid));
            $c = iterator_to_array($c);
            if($c){
                return $this->_Goto('同名圈子已存在');
            }
            $data['people'] = 1;
            $data['dateline'] = time();
            $data['status'] = 1;
            $data['build_id'] = $uid;
            $data['msg'] = 0;
            $data['msg_post'] = 0;
            $data['today_msg'] = 0;
            $data['today_msg_post'] = 0;
            $data['view_number'] = 0;
            $data['pid'] = '0';
            $data['city'] = 1;
            $mongo->social_circle->insert($data);
            //新增一个圈子 需要 往圈子成员加1
            $userData = $this->_getPlayUserTable()->get(array('uid' => $uid));

            $circle_users = array(
                'uid'=> $uid,
                'username' => $userData->username,
                'user_detail' => $userData->user_alias,
                'img'=> $userData->img,
                'dateline' => time(),
                'cid' => (string)$data['_id'],
                'c_name' => $title,
                'user_circles'=> json_decode($userData->user_circles),       //用户加入的其他圈子
                'status' => 1,
                'role' => 2,
                'city' => $city,
            );

            $mongo->social_circle_users->insert($circle_users);

            //新增一个圈子 更新用户表中user_circle
            $user_circles = array();
            $res = $mongo->social_circle_users->find(array('uid' => $uid))->sort(array('dateline' => -1))->limit(3);
            $user_circles[] = array('cid' => (string)$circle_users['_id'], 'c_name' => $title);
            foreach ($res as $v) {
                $user_circles[] = array('cid' => $v['cid'], 'c_name' => $v['c_name']);
            }
            $this->_getPlayUserTable()->update(array('user_circles' => json_encode($user_circles, JSON_UNESCAPED_UNICODE), 'join_circle' => new Expression('join_circle+1')), array('uid' => $uid));
            //更新用户之前加入的圈子数据
            $this->_getMdbsocialCircleUsers()->update(array('uid' => $uid), array('$set' => array('user_circles' => $user_circles)), array('multiple' => true));

        }
        if($city==1){
            return $this->_Goto('成功', '/wftadlogin/circle/main');
        }else{
            return $this->_Goto('成功', '/wftadlogin/circle');
        }


    }

//分站申请圈子表单
    public function applyAction(){
        return array(
        );
    }

    //分站申请提交
    public function doapplyAction(){
        $id = $this->getPost('id');
        $type = (int)$this->getPost('type');
        $title = $this->getPost('title');
        $introduce = $this->getPost('introduce');
        $reason = $this->getPost('reason');
        $img = $this->getPost('img');
        $thumb = $this->getPost('thumb');
        $uid = (int)$this->getPost('uid');
        $city = $this->getAdminCity();

        if (!$img || !$thumb) {
            return $this->_Goto('封面图 与缩略图');
        }

        $cover_class = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $img);
        $cover_status = $cover_class->scaleResizeImage(720, 360);
        if ($cover_status) {
            $cover_status->save($_SERVER['DOCUMENT_ROOT'] . $img);
        }

        $surface_class = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $thumb);
        $surface_status = $surface_class->scaleResizeImage(360, 360);
        if ($surface_status) {
            $surface_status->save($_SERVER['DOCUMENT_ROOT'] . $thumb);
        }

        $data = array(
            'type' => $type,
            'title' => $title,
            'introduce' => $introduce,
            'img' => $img,
            'thumb' => $thumb,
            'status' => 0,
            'reason' => $reason,
            'city' => $city,
        );

        $mongo = $this->_getMongoDB();
        $data['people'] = 1;
        $data['dateline'] = time();
        $data['build_id'] = $uid;
        $data['msg'] = 0;
        $data['msg_post'] = 0;
        $data['today_msg'] = 0;
        $data['today_msg_post'] = 0;
        $data['view_number'] = 0;
        //$data['branch_number'] = 0;

        $mongo->social_circle->insert($data);

        return $this->_Goto('成功', '/wftadlogin/circle/myapply');
    }

    //查看带审核内容
    public function docheckAction(){
        $id = $this->getQuery('id');
        if ($id) {
            $mongo = $this->_getMongoDB();
            $cursor = $mongo->social_circle->findOne(array('_id' => new \MongoId($id)));
        } else {
            return $this->_Goto('圈子不存在');
        }
        return array(
            'data' => $cursor,
        );
    }

    //分站列表,列出某一圈子下的所有分站别名信息
    public function branchAction(){
        $id = $this->getQuery('id');
        if($id){
            $mongo = $this->_getMongoDB();
            $cursor = $mongo->social_circle->find(array('pid' => $id));
            $main = $mongo->social_circle->findOne(array('_id' => new \MongoId($id)));
        } else {
            return $this->_Goto('圈子不存在');
        }
        return array(
            'data' => $cursor,
            'main_title' => $main['title'],
            'cities' => $this->getAllCities(),
        );
    }

    //是否审核通过
    public function passAction(){
        $id = $this->getPost('id',0);
        $status = $this->getPost('status',0);
        if($this->getAdminCity()!=1){
            return $this->_Goto('非法进入');
        }
        if(!$this->checkMid($id)){
            return $this->_Goto('参数错误', '/wftadlogin/circle/check');
        }

        $mongo = $this->_getMongoDB();

        if($status == -1){
            $mongo->social_circle->update(array('_id' => new \MongoId($id)), array('$set' => array('status'=>-1)),array('multiple' => false));
            return $this->_Goto('操作成功', '/wftadlogin/circle/check');
        }

        $data = $mongo->social_circle->findOne(array('_id' => new \MongoId($id)));
        unset($data['_id']);
        $data['dateline'] = time();
        $data['status'] = 1;
        $data['city'] = $this->getAdminCity();
        $data['pid'] = '0';
        $data['branch_number'] = 1;
        $mongo->social_circle->insert($data);

        $mongo->social_circle->update(array('_id' => new \MongoId($id)), array('$set' => array('status'=>1,'pid'=>$data['_id'])),
            array('multiple' => false));

        //新增一个圈子 需要 往圈子成员加1
        $userData = $this->_getPlayUserTable()->get(array('uid' => $data->uid));

        $circle_users = array(
            'uid'=> $data->uid,
            'username' => $userData->username,
            'user_detail' => $userData->user_alias,
            'img'=> $userData->img,
            'dateline' => time(),
            'cid' => (string)$data['_id'],
            'c_name' => $data->title,
            'user_circles'=> json_decode($userData->user_circles),       //用户加入的其他圈子
            'status' => 1,
            'role' => 2
        );

        $mongo->social_circle_users->insert($circle_users);

        //新增一个圈子 更新用户表中user_circle
        $user_circles = array();
        $res = $mongo->social_circle_users->find(array('uid' => $data->uid))->sort(array('dateline' => -1))->limit(3);
        $user_circles[] = array('cid' => (string)$circle_users['_id'], 'c_name' => $data->title);
        foreach ($res as $v) {
            $user_circles[] = array('cid' => $v['cid'], 'c_name' => $v['c_name']);
        }
        $this->_getPlayUserTable()->update(array('user_circles' => json_encode($user_circles, JSON_UNESCAPED_UNICODE), 'join_circle' => new Expression('join_circle+1')), array('uid' => $data->uid));
        //更新用户之前加入的圈子数据
        $this->_getMdbsocialCircleUsers()->update(array('uid' => $data->uid), array('$set' => array('user_circles' => $user_circles)), array('multiple' => true));

        return $this->_Goto('操作成功', '/wftadlogin/circle/check');
    }

    /**
     * 取消使用
     * @return ViewModel
     */
    public function unuseAction() {

        $id = $this->getQuery('id');
        if(!$this->checkMid($id)){
            return $this->_Goto('非法操作');
        }
        $mongo = $this->_getMongoDB();

        $where['_id'] = new \MongoId($id);
        if($this->isMain()){
            $where['city'] = $this->getAdminCity();
        }

        // todo 圈子 每做次 修改 时应该 通知用户 改变用户的一些信息
        $sc = $mongo->social_circle->find($where);
        $sc = iterator_to_array($sc);
        if(!$sc){
            return $this->_Goto('圈子不存在');
        }

        $status = $mongo->social_circle->update($where, array('$set' => array('status' => 0)));
        // todo 删除圈子 把圈子里面的user 删除  user_circle 如何处理 circle  与 play_user
        $status = (array)$status;
        if(!$status['updatedExisting']){
            return $this->_Goto('操作失败');
        }

        $mongo->social_circle->update(array('_id' => new \MongoId($sc[$id]['pid'])), array('$inc' => array('branch_number' => -1)), array('multiple' => false));


        //圈子消息 消息的回复 删除
        $this->_getMdbSocialCircleMsg()->update(array('cid' => $id),array('$set' => array('status' => -1)), array('multiple' => true));
        $this->_getMdbSocialCircleMsgPost()->update(array('cid' => $id), array('$set' => array('status' => -1)), array('multiple' => true));

        $this->_getMdbSocialCircleUsers()->update(array('cid' => $id), array('$set' => array('status' => -1)), array('multiple' => true));
        //更新圈子里用户的

        $userData = $this->_getMdbSocialCircleUsers()->find(array('cid' => $id,'status' => array('$gt' => 0)));

        // todo 后面 可以 写成一个sql语句
        foreach ($userData as $user) {
            $userMessageData = $this->_getMdbSocialCircleMsg()->find(array('uid' => $user['uid'], 'status' => array('$gt' => 0)));
            $userCircleData = $this->_getMdbSocialCircleUsers()->find(array('uid' => $user['uid'],'status' => array('$gt' => 0)));
            $this->_getPlayUserTable()->update(array('circle_msg' => $userMessageData->count(), 'join_circle' => ($userCircleData->count())), array('uid' => $user['uid']));
        }

        return $this->_Goto('删除成功');
    }

    //总站删除,分站我的申请删除操作
    public function updateAction() {
        $type = $this->getQuery('type');
        $id = $this->getQuery('id');
        if(!$this->checkMid($id)){
            return $this->_Goto('非法操作');
        }
        $mongo = $this->_getMongoDB();

        $where['_id'] = new \MongoId($id);
//        if($this->getAdminCity()!=1){
//            $where['city'] = $this->getAdminCity();
//        }

        // todo 圈子 每做次 修改 时应该 通知用户 改变用户的一些信息
        if ($type == 1) {//删除

            $branchuse = $this->_getMdbSocialCircleMsg()->find(array('pid' => (string)$id, 'status' => array('$gt' => 0)));
            $branchuse = iterator_to_array($branchuse);
            if($branchuse){
                return $this->_Goto('请在所有分站取消对该圈子的使用关系再执行删除操作');
            }

            if($this->isMain()){
                $status = $mongo->social_circle->update($where, array('$set' => array('status' => 0)));
            }else{
                $status = $mongo->social_circle->update($where, array('$set' => array('status' => -1)));
                $status = (array)$status;
                if($status['updatedExisting']){
                    return $this->_Goto('操作成功');
                }else{
                    return $this->_Goto('操作失败');
                }
            }

            // todo 删除圈子 把圈子里面的user 删除  user_circle 如何处理 circle  与 play_user
            $status = (array)$status;
            if(!$status['updatedExisting']){
                return $this->_Goto('操作失败');
            }



            //圈子消息 消息的回复 删除
            $this->_getMdbSocialCircleMsg()->update(array('cid' => $id),array('$set' => array('status' => -1)), array('multiple' => true));
            $this->_getMdbSocialCircleMsgPost()->update(array('cid' => $id), array('$set' => array('status' => -1)), array('multiple' => true));

            $this->_getMdbSocialCircleUsers()->update(array('cid' => $id), array('$set' => array('status' => -1)), array('multiple' => true));
            //更新圈子里用户的

            $userData = $this->_getMdbSocialCircleUsers()->find(array('cid' => $id,'status' => array('$gt' => 0)));

            // todo 后面 可以 写成一个sql语句
            foreach ($userData as $user) {
                $userMessageData = $this->_getMdbSocialCircleMsg()->find(array('uid' => $user['uid'], 'status' => array('$gt' => 0)));
                $userCircleData = $this->_getMdbSocialCircleUsers()->find(array('uid' => $user['uid'],'status' => array('$gt' => 0)));
                $this->_getPlayUserTable()->update(array('circle_msg' => $userMessageData->count(), 'join_circle' => ($userCircleData->count())), array('uid' => $user['uid']));
            }

            return $this->_Goto('删除成功');
        }
        exit;
    }

    //圈子 => 用户管理
    /**
     *  某个圈子中的用户列表
     */
    public function userAction() {

        $mongo = $this->_getMongoDB();
        $page = (int)$this->getQuery('p', 1);
        $role = (int)$this->getQuery('role');
        $pageSum = 10;
        $start = ($page-1)*$pageSum;
        $id = $this->getQuery('id');
        $key = $this->getQuery('k');

        $where = array('cid' => $id);

        if ($role) {
            $where['role'] = $role;
        }

        if ($key) {
            $where['username'] = new \MongoRegex("/{$key}/");
        }

        $order = array('dateline' => -1);

        $cursor = $mongo->social_circle_users->find($where)->limit($pageSum)->skip($start)->sort($order);
        $count = $mongo->social_circle_users->find($where)->count();

        //创建分页
        $url = '/wftadlogin/circle/user';
        $paginator = new Paginator($page, $count, $pageSum, $url);

        $circle = $mongo->social_circle->findOne(array('_id' => new \MongoId($id)));
        return array(
            'data' => $cursor,
            'pagedata' => $paginator->getHtml(),
            'circle' => $circle,
            'role' => array('1' => '一般用户', '2' => '管理员'),
        );
    }

    public function getUserShareAction() {
        $type = $this->getQuery('type', 'word');
        $uid = (int)$this->getQuery('uid');
        $cid = $this->getQuery('cid');
        $mongo = $this->_getMongoDB();
        if ($type == 'word') {
            echo  $mongo->social_circle_msg->find(array('uid' => $uid, 'cid' => $cid))->count();
            exit;
        }

        if ($type == 'prise') {
            //todo 圈子内消息的 点赞 圈子 消息 回复 ？
            $msgData = $this->_getMdbSocialCircleMsg()->find(array('cid' => $cid, 'status' => array('$gt' => -1)));

            $mid = array();
            foreach ($msgData as $msg) {
                $mid[] = (string)$msg['_id'];
            }

            echo $this->_getMdbSocialPrise()->find(array('uid' => $uid, 'object_id' => array('$in' => $mid)))->count();
            exit;
        }

        if ($type == 'reply') {
            echo $mongo->social_circle_msg_post->find(array('uid' => $uid, 'cid' => $cid))->count();
            exit;
        }

        exit;
    }

    public function userUpdateAction() {
        $type = $this->getQuery('type');
        $id = $this->getQuery('id');
        $uid = (int)$this->getQuery('uid');
        $role = $this->getQuery('role');
        $status = $this->getQuery('status');

        $mongo = $this->_getMongoDB();
        if ($type == 'del') {//删除

            if (!$id or !$uid) {
                return $this->_Goto('非法操作');
            }

            $res = $this->_getMdbsocialCircleUsers()->findOne(array('uid' => $uid, 'cid' => $id));
            if (!$res) {
                return $this->_Goto('已经删除了');
            }

            $this->_getMdbsocialCircleUsers()->remove(array(
                'uid' => $uid,
                'cid' => $id
            ));

            // 更新圈子中 用户的数据
            $circleUserData = $this->_getMdbSocialCircleUsers()->find(array('cid' => $id));



            $user_circles = array();
            $circles = $this->_getMdbsocialCircleUsers()->find(array('uid' => $uid))->sort(array('dateline' => -1))->limit(4);

            foreach ($circles as $v) {
                $user_circles[] = array('cid' => $v['cid'], 'c_name' => $v['c_name']);
            }

            //user 表存入用户关注的前3个圈子
            $this->_getPlayUserTable()->update(array('user_circles' => json_encode($user_circles, JSON_UNESCAPED_UNICODE), 'join_circle' => new Expression('join_circle-1')), array('uid' => $uid));
            //人数-1
            $this->_getMdbSocialCircle()->update(array('_id' => new \MongoId($id)), array('$set' => array('people' => $circleUserData->count())));
            //更新用户之前加入的圈子数据
            $this->_getMdbsocialCircleUsers()->update(array('uid' => $uid), array('$set' => array('user_circles' => $user_circles)), array('multiple' => true));

            return $this->_Goto('成功了');

        }

        if ($type == 'role') {//权限 管理员
            $role = ($role == 1) ? 2 : 1;
            $mongo->social_circle_users->update(array('uid' => $uid, '_id' => new \MongoId($id)), array('$set' => array("role" => $role)));

            return $this->_Goto('修改成功');
        }

        if ($type == 'word') {
            $status = ($status == 1) ? 0 : 1;
            $mongo->social_circle_users->update(array('uid' => $uid, '_id' => new \MongoId($id)), array('$set' => array("status" => $status)));

            return $this->_Goto('修改成功');
        }
    }

    //圈子 => 发言管理
    public function wordAction() {
        $mongo = $this->_getMongoDB();
        $page = (int)$this->getQuery('p', 1);
        $status = (int)$this->getQuery('status');
        $flag = $this->getQuery('flag');
        $pageSum = 10;
        $start = ($page-1)*$pageSum;
        $id = $this->getQuery('id');
        $uid = (int)$this->getQuery('uid');
        $key = $this->getQuery('k');

        if($id && !$this->checkMid($id)){
            return $this->_Goto('无效id');
        }

        $city = $this->chooseCity();

        $where = array('status' => array('$gte' => 0), 'cid' => array('$ne' => 0), 'c_name' => array('$ne' => ''));
        if($city){
            $where['city'] =  array('$all'=>array($city));
        }


        if ($id) {
            $where['cid'] = $id;
        }

        if ($uid) {
            $where['uid'] = $uid;
        }

        if ($key) {
            $where['title'] = new \MongoRegex("/{$key}/");
            // $where['$or'] = array('title' => new \MongoRegex("/{$key}/"), 'msg' =>  new \MongoRegex("/{$key}/"));
        }

        //发言的状态
        if ($status) {
            if ($status == -1) {
                $status = 0;
            }
            $where['status'] = $status;
        }

        //焦点图
        if ($flag) {
            $block = $this->_getPlayIndexBlockTable()->fetchAll(array('link_id' => 7, 'type' => 7, 'block_city' => $this->getCity(), 'link_type' => 2));
            $mongoIds = array();
            foreach ($block as $b) {
                $mongoIds[] = new \MongoId($b->tip);
            }

            // 采用$in $$nin
            if (count($mongoIds)) {
                if ($flag == 2) {
                    $where['_id'] = array('$in' => $mongoIds);
                }
                if ($flag == 1) {
                    $where['_id'] = array('$nin' => $mongoIds);
                }
            } else {
                if ($flag == 2) {
                    $where['_id'] = '';
                }
            }
        }

        $order = array('status' => -1, 'dateline' => -1);

        $cursor = $mongo->social_circle_msg->find($where)->limit($pageSum)->skip($start)->sort($order);
        $count = $mongo->social_circle_msg->find($where)->count();

        //创建分页
        $url = '/wftadlogin/circle/word';
        $paginator = new Paginator($page, $count, $pageSum, $url);

        $circle = '';
        if ($id) {
            $circle = $mongo->social_circle->findOne(array('_id' => new \MongoId($id)));
        }

        //当前页面的点赞信息
        $cid = [];
        foreach($cursor as $c){
            $cid[] = $c['_id'];
        }

        $praise = $mongo->social_prise->find(array('object_id' => ['$in'=>$cid]));
        $praise_id = [];
        foreach($praise as $s){
            $praise_id[$s['object_id']] ++;
        }

        if(!$cid){
            $cid = 0;
        }
        //发言获得的积分 type 15,16
        $integral = $this->_getPlayIntegralTable()->getIntegerByType($cid,[15,16]);

        $integral_arr = [];
        if($integral){
            foreach($integral as $i){
                $integral_arr[$i['object_id']] = $i['total_score'];
            }
        }

        if(count($cid) > 0){
            $cc = $this->getCashCoupon($cid);
            $ac = $this->getAccount($cid);
            $score = $this->getIntegral($cid);
        }

        return array(
            'data' => $cursor,
            'pagedata' => $paginator->getHtml(),
            'circle' => $circle,
            'praise' => $praise_id,
            'integral' => $integral_arr,
            'cc' => $cc?:[],
            'ac' => $ac?:[],
            'score' => $score?:[],
            'citys' => $this->getAllCities(),
            'filtercity' => CityCache::getFilterCity($city),
        );
    }

    //分站的话题发表
    public function newWordAction() {
        $aid = $_COOKIE['id'];
        $userData = $this->_getPlayAdminTable()->get(array('id' => $aid)); //todo 编辑状态 修改

        if (!$userData || !$userData->bind_user_id) {
            return $this->_Goto('该编辑账号 没有绑定uid', '/wftadlogin/setting/editor');
        }


        $id = $this->getQuery('id');
        $mid = $this->getQuery('mid');
        $mongo = $this->_getMongoDB();
        $circle = $mongo->social_circle;
        $social = $circle->findOne(array('_id' => new \MongoId($id)));

        $msg = '';
        if ($mid) {
            $msg = $mongo->social_circle_msg->findOne(array('_id' => new \MongoId($mid)));
        }

        return array(
            'userData' => $userData,
            'circle' => $social,
            'msg' => $msg,
            'cities' => $this->getAllCities(1),
        );

    }

    //分站的保存话题
    public function saveWordAction() {

        $uid = (int)$this->getPost('uid');
        $cid = $this->getPost('cid');
        $title = htmlspecialchars_decode($this->getPost('title'), ENT_QUOTES);
        $content = $this->getPost('editorValue');

        $city = $this->getPost('city');

        $city = ($this->getAdminCity()==1)?$city:array($this->getAdminCity());

        $content = strip_tags(str_replace(array("<br/>", '&nbsp;', "<p"), array("\r\n", ' ', "\r\n<p"), htmlspecialchars_decode(trim($content), ENT_QUOTES)), '<img>');
        $content = preg_replace('/(\r\n)+/', "\r\n", $content);
        if (strpos($content, "\r\n") === 0) {
            $content = substr($content, 2);
        }
        $content = str_replace("\r\n", "\n", $content);


        //$content = strip_tags(str_replace(array("<br/>", '&nbsp;', "<p"), array("\r\n", ' ', "\r\n<p"), htmlspecialchars_decode(trim($content), ENT_QUOTES)), '<img>'); //内容

        $addr_x = '114.274895'; //经度
        $addr_y = '30.561448'; //纬度
        $mongon = $this->_getMongoDB();


        //图片
        preg_match_all('/<img[^>]*src\s*=\s*([\'"]?)([^\'" >]*)\1/isu', $content, $imgs);
        $src = array();
        foreach ($imgs[2] as $img) {
            if (stripos($img, 'http') === 0) {
                $src[] = preg_replace('/http:\\/\\/(.*?)\\//', '/', $img);
            } else {
                $src[] = $img;
            }
        }

        foreach ($src as $s) {
            $content = preg_replace('/<img(.*?) src=(.*?)>/', '$$'. $s. '$$', $content, 1);
        }

        $co = explode('$$',$content);

        $msg = array();
        foreach($co as $c) {
            if ($c) {
                if(stripos($c, '/') === 0) {
                    $msg[] = array(
                        't' => 2,
                        'val' => $c,
                    );
                } else {
                    $msg[] = array(
                        't' => 1,
                        'val' => htmlspecialchars_decode($c, ENT_QUOTES),
                    );
                }
            }
        }

        $mid = $this->getPost('mid');
        if ($mid) {
            $mongon->social_circle_msg->update(array('_id' => new \MongoId($mid)), array('$set' => array("title" => $title, 'msg' => $msg)));
            return $this->_Goto('成功');
        }

        if (!$cid) {
            return $this->_Goto('该圈子不存在');
        }
        if (!$content) {
            return $this->_Goto('内容或标题不能为空');
        }

        //用户信息
        $user_data = $this->_getPlayUserTable()->get(array('uid' => $uid));
        if (!$uid || !$user_data) {
            return $this->_Goto('该编辑账号 没有绑定uid', '/wftadlogin/setting/editor');
        }


        //todo 获取订阅用户列表
        $f_res = $mongon->social_friends->find(array('like_uid' => $uid));
        $uids = array();
        foreach ($f_res as $v) {
            $uids[] = (int)$v['uid'];
        }


        //圈子信息
        $circle_data = $mongon->social_circle->findOne(array('_id' => new \MongoId($cid)));

        // 内容处理
        $status =  $mongon->social_circle_msg->insert(array(
            'cid' => $cid,//  '圈子id',
            'c_name' => $circle_data['title'],
            'title' => $title,
            'msg' => $msg,
            'msg_type' => 1,// '消息类型 1圈子 2评论商品 3评论游玩地 4评论商家 5评论专题',
            'uid' => $uid,
            'img' => $user_data->img,
            'username' => $user_data->username,
            'child' => $user_data->user_alias,
            'view_number' => 0,
            'like_number' => 0,
            'share_number' => 0,
            'replay_number' => 0,
            'sub_uids' => $uids,//订阅用户列表
            'dateline' => time(),//时间戳
            'status' => 1,// '状态  0删除 1正常 ',
            //'img_list' => array(),
            'posts' => array( // '评论列表 只存前三条
//                array('uid' => 12, 'message' => 'asdgasg', 'dateline' => 12345, 'img' => '2134321521'),
//                array('uid' => 12, 'message' => 'asdgasg', 'dateline' => 12345, 'img' => '2134321521'),
//                array('uid' => 12, 'message' => 'asdgasg', 'dateline' => 12345, 'img' => '2134321521')
            ),
            'object_data' => array(
                'object_id' => '',//对象id
                'object_title' => '',
                'object_ticket' => 0, //'0无票  1有票',
                'object_img' => '',
            ),
            'addr' => array(
                'type' => 'Point',
                'coonrdinates' => array($addr_x, $addr_y)
            ),
            'city' => $city
        ));

        if ($status) {

            //更新圈子消息数量
            $mongon->social_circle->update(array('_id' => new \MongoId($cid)), array('$inc' => array('msg' => 1, 'today_msg' => 1)));
            $this->_getPlayUserTable()->update(array('circle_msg' => new Expression('circle_msg+1')), array('uid' => $uid));
            return $this->_Goto('成功', '/wftadlogin/circle/word?id='.$cid);

        } else {
            return $this->_Goto('失败', '/wftadlogin/circle/word?id='.$cid);
        }
    }

    public function updateWordAction() {
        $type = $this->getQuery('type');

        $id = $this->getQuery('id');
        $uid = (int)$this->getQuery('uid');
        $mongo = $this->_getMongoDB();
        $wordData = $this->_getMdbSocialCircleMsg()->findOne(array('_id' => new \MongoId($id)));

        if($wordData){//不要操作其它站点的回复
            if($this->getAdminCity()!=1 && !in_array($this->getAdminCity(),$wordData['city'])){
                return $this->_Goto('参数错误');
            }
        }else{
            return $this->_Goto('参数错误');
        }


        if ($type == 'up') { //置顶
            $count =  $mongo->social_circle_msg->find(array("status" => 2, 'cid' => $wordData['cid']))->count();
            if ($count > 2) {
                return $this->_Goto('该圈子置顶数量已经有三个了');
            }
            $mongo->social_circle_msg->update(array("uid" => $uid, '_id' => new \MongoId($id)), array('$set' => array("status" => 2)));
            $this->loadWord($wordData['cid'], $uid);
            return $this->_Goto('置顶成功');
        }

        if ($type == 'hidden') { // 隐藏
            $mongo->social_circle_msg->update(array("uid" => $uid, '_id' => new \MongoId($id)), array('$set' => array("status" => 0)));
            $this->loadWord($wordData['cid'], $uid);
            $integral = new Integral();
            $integral->circle_speak_delete($uid, new \MongoId($id),$this->getAdminCity(),$_COOKIE['id']);
            return $this->_Goto('隐藏成功');
        }

        if ($type == 'del') { //删除
            $mongo->social_circle_msg->update(array("uid" => $uid, '_id' => new \MongoId($id)), array('$set' => array("status" => -1)));
            $this->loadWord($wordData['cid'], $uid);
            $integral = new Integral();
            $integral->circle_speak_delete($uid, new \MongoId($id),$this->getAdminCity(),$_COOKIE['id']);
            return $this->_Goto('删除成功');
        }

        if ($type == 'reset') { //回到正常
            $mongo->social_circle_msg->update(array("uid" => $uid, '_id' => new \MongoId($id)), array('$set' => array("status" => 1)));
            $this->loadWord($wordData['cid'], $uid);
            $integral = new Integral();
            $integral->circle_speak_reset($uid, new \MongoId($id),$this->getAdminCity());
            return $this->_Goto('成功');
        }

        if ($type == 'action') {//置顶动态
            $count =  $mongo->social_circle_msg->find(array("status" => 3))->count();
            if ($count > 2) {
                return $this->_Goto('置顶动态数量已经有三个了');
            }
            $mongo->social_circle_msg->update(array("uid" => $uid, '_id' => new \MongoId($id)), array('$set' => array("status" => 3)));
            $this->loadWord($wordData['cid'], $uid);
            return $this->_Goto('置顶成功');
        }

    }

    public function loadWord($cid, $uid) {

        //更新圈子消息数量 及用户的发言数量
        $circleMessageData = $this->_getMdbSocialCircleMsg()->find(array('cid' => $cid, 'status' => array('$gt' => 0)));
        $userMessageData = $this->_getMdbSocialCircleMsg()->find(array('uid' => $uid, 'status' => array('$gt' => 0)));
        $this->_getMdbSocialCircle()->update(array('_id' => new \MongoId($cid)), array('$set' => array('msg' => $circleMessageData->count())));
        $this->_getPlayUserTable()->update(array('circle_msg' => $userMessageData->count() ), array('uid' => $uid));

    }

    public function getWordShareAction() {
        $type = $this->getQuery('type', 'word');
        $cid = $this->getQuery('cid');
        $mongo = $this->_getMongoDB();

        if ($type == 'prise') {
            //todo 圈子内消息的 点赞 圈子 消息 回复 ？
            echo $this->_getMdbSocialPrise()->find(array('object_id' => $cid))->count();
            exit;
        }

        if ($type == 'reply') {
            echo $mongo->social_circle_msg_post->find(array('mid' => $cid))->count();
            exit;
        }

        if ($type == 'fock') {
            $statu = $this->_getPlayIndexBlockTable()->get(array('link_id' => 7, 'type' => 7, 'tip' => $cid, 'link_type' => 2));
            echo $statu ? '焦点图' : '非焦点图';
            exit;
        }


        exit;
    }

    public function wordInfoAction() {
        $id = $this->getQuery('id');
        $page = $this->getQuery('p', 1);
        $mongo = $this->_getMongoDB();

        $msg = $mongo->social_circle_msg->findOne(array('_id' => new \MongoId($id)));
        $social = $mongo->social_circle->findOne(array('_id' => new \MongoId($msg['cid'])));

        $pageSum = 10;
        $start = ($page-1)*$pageSum;

        $where = array('mid' => $id, 'status' => array('$gt' => -1));
        $order = array('status' => -1, 'dateline' => 1);
        $post = $mongo->social_circle_msg_post->find($where)->limit($pageSum)->skip($start)->sort($order);
        $count = $mongo->social_circle_msg_post->find($where)->count();

        //创建分页
        $url = '/wftadlogin/circle/wordinfo';
        $paginator = new Paginator($page, $count, $pageSum, $url);

        return array(
            'circle' => $social,
            'msg' => $msg,
            'post' => $post,
            'pagedata' => $paginator->getHtml(),
        );
    }

    public function newReplyAction() {
        $aid = $_COOKIE['id'];
        $userData = $this->_getPlayAdminTable()->get(array('id' => $aid)); //todo 编辑状态 修改

        if (!$userData || !$userData->bind_user_id) {
            return $this->_Goto('该编辑账号 没有绑定uid', '/wftadlogin/setting/editor');
        }

        $cid = $this->getQuery('cid');
        $id = $this->getQuery('id');
        $mongo = $this->_getMongoDB();
        $circle = $mongo->social_circle;
        $social = $circle->findOne(array('_id' => new \MongoId($cid)));
        $msg = $mongo->social_circle_msg->findOne(array('_id' => new \MongoId($id)));

        return array(
            'userData' => $userData,
            'circle' => $social,
            'msg' => $msg,
        );
    }

    public function saveReplyAction() {

        $mid = $this->getPost('mid');//圈子消息id
        $cid = $this->getPost('cid');//圈子id
        $uid = (int)$this->getPost('uid');
        $info = strip_tags(htmlspecialchars_decode(trim($this->getPost('info'))), ENT_QUOTES);// 回复内容
        $addr_x = '114.274895'; //经度
        $addr_y = '30.561448';//纬度
        if (!$info) {
            return $this->_Goto('回复为空');
        }
        $mongo = $this->_getMongoDB();

        //用户信息
        $user_data = $this->_getPlayUserTable()->get(array('uid' => $uid));

        if (!$user_data) {
            return $this->_Goto('用户不存在');
        }

        //圈子信息
        $msg_data = $mongo->social_circle_msg->findOne(array('_id' => new \MongoId($mid), 'status' => array('$gt' => 0)));

        if (!$msg_data) {
            return $this->_Goto('消息不存在,或已删除');
        }
        //更新发言回复数
        $mongo->social_circle_msg->update(array('_id' => new \MongoId($mid)), array('$inc' => array('replay_number' => 1)));
        //更新圈子回复数
        $mongo->social_circle->update(array('_id' => new \MongoId($msg_data['cid'])), array('$inc' => array('msg_post' => 1, 'today_msg_post' => 1)));

        // 内容处理
        $content_arr = array(array('val' => $info, 't' => 1));

        $post_data = array(
            'mid' => $mid,//'主题消息id',
            'uid' => $uid,
            'cid' => $cid,
            'username' => $user_data->username,
            'first' => 0,// '是否主题贴',
            'title' => $msg_data['title'],
            'msg' => $content_arr,//回复内容
            'img' => $user_data->img, //用户头像
            'dateline' => time(),//时间戳
            'child' => $user_data->user_alias,
            'addr' => array(
                'type' => 'Point',
                'coonrdinates' => array($addr_x, $addr_y)
            ),
            'status' => 1,
        );


        $status = $mongo->social_circle_msg_post->insert($post_data);
        if ($status) {
            $this->loadReplay($mid);
            return $this->_Goto('回复成功', "/wftadlogin/circle/wordinfo?id={$mid}&cid={$cid}");
        } else {
            return $this->_Goto('回复失败', "/wftadlogin/circle/wordinfo?id={$mid}&cid={$cid}");
        }
    }

    public function updateReplyAction() {
        $type = $this->getQuery('type');
        $id = $this->getQuery('id');

        if(!$this->checkMid($id)){
            return $this->_Goto('无效ID');
        }

        $mongo = $this->_getMongoDB();
        $replyData = $this->_getMdbSocialCircleMsgPost()->findOne(array('_id' => new \MongoId($id)));


        if ($type == 'up') {
            $count =  $mongo->social_circle_msg_post->find(array("status" => 2, 'mid' => $replyData['mid']))->count();
            if ($count > 2) {
                return $this->_Goto('置顶数量已经有三个了');
            }
            $mongo->social_circle_msg_post->update(array('_id' => new \MongoId($id)), array('$set' => array("status" => 2)));
            $this->loadReplay($replyData['mid']);
            return $this->_Goto('置顶成功');

        }

        if ($type == 'hidden') {
            $mongo->social_circle_msg_post->update(array('_id' => new \MongoId($id)), array('$set' => array("status" => 0)));
            $this->loadReplay($replyData['mid']);
            return $this->_Goto('隐藏成功');
        }

        if ($type == 'reset') { //回到正常
            $mongo->social_circle_msg_post->update(array('_id' => new \MongoId($id)), array('$set' => array("status" => 1)));
            $this->loadReplay($replyData['mid']);
            return $this->_Goto('成功');
        }
    }

    public function loadReplay($mid) {

        $msg_post_data = $this->_getMdbSocialCircleMsgPost()->find(array('mid' => $mid, 'status' => array('$gt' => 0)))->sort(array('status' => -1, 'dateline' => -1))->limit(4);
        $msg_post = $this->_getMdbSocialCircleMsgPost()->find(array('mid' => $mid, 'status' => array('$gt' => 0)));
        $answer = array();
        foreach ($msg_post_data as $v) {
            $answer[] = array(
                'uid' => $v['uid'],
                'username' => $v['username'],
                'message' => $v['msg'],
                'dateline' => $v['dateline']
            );
        }

        $this->_getMdbSocialCircleMsg()->update(array('_id' => new \MongoId($mid), 'status' => array('$gt' => 0)), array('$set' => array('posts' => $answer, 'replay_number' => $msg_post->count())));

    }


    /*
     * 用户发言 回复 点赞 列表
     */
    public function userMsgAction() {
        $type = $this->getQuery('type', 'word');

        $id = $this->getQuery('id'); //圈子id
        $uid = (int)$this->getQuery('uid'); //用户id
        $mongo = $this->_getMongoDB();
        $social = $mongo->social_circle_users->findOne(array('cid' => $id, 'uid' => $uid));

        if ($type == 'word') {//发言
            $data = $mongo->social_circle_msg->find(array('uid' => $uid, 'cid' => $id, 'status' => array('$gt' => -1)));
        }

        if ($type == 'reply') {//回复
            $data = $mongo->social_circle_msg_post->find(array('uid' => $uid, 'cid' => $id, 'status' => array('$gt' => -1)));
        }

        if ($type == 'prise') { //点赞
            $data = $mongo->social_prise->find(array('uid' => $uid, 'cid' => $id));
        }

        return array(
            'social' => $social,
            'data' => $data,
            'type' => $type,
        );
    }

    public function getMsgShareAction() {
        $type = $this->getQuery('type', 'prise');
        $id = $this->getQuery('id');
        $mongo = $this->_getMongoDB();


        if ($type == 'prise') {
            //todo 圈子内消息的 点赞 圈子 消息 回复 ？
            $msg = $mongo->social_circle_msg->findOne(array('_id' => new \MongoId($id)));

            if($msg['title']) {
                echo $msg['title'];}
            else {
                $st = '';
                foreach($msg['msg'] as $v) {
                    if ($v['t'] == 1 && $v['val']) {
                        echo $st = $v['val'];
                        break;
                    }
                }
                if (!$st) {
                    echo '内容无';
                }
            };
            exit;
        }

        exit;
    }



    /*
     * 用户关注 被关注 玩伴
     */
    public function userPartnerAction() {
        $type = $this->getQuery('type', 'follow');
        $uid = (int)$this->getQuery('uid');
        $mongo = $this->_getMongoDB();
        $userData = $this->_getPlayUserTable()->get(array('uid' => $uid));

        /*$u = $mongo->social_friends->find();
        foreach($u as $yu) {
            echo $yu['like_uid']. '__' .$yu['uid'].'<br/>';
        }*/

        $where = array();
        $id = array();
        if ($type == 'follow') {//关注
            $uids = $mongo->social_friends->find(array('uid' => $uid));
            foreach ($uids as $val) {
                $id[] = $val['like_uid'];
            }
        }

        if ($type == 'followed') {//被关注
            $uids = $mongo->social_friends->find(array('like_uid' => $uid));
            foreach ($uids as $val) {
                $id[] = $val['uid'];
            }
        }

        if ($type == 'partner') { //玩伴 双向关注
            $uids = $mongo->social_friends->find(array('uid' => $uid, 'friends' => 1));
            foreach ($uids as $val) {
                $id[] = $val['like_uid'];
            }
        }

        if (count($id)) {
            $m = '(';
            $m = str_pad($m, count($id)*2+1,'?,');
            $m = substr($m,0,strlen($m)-1);
            $m = $m.')';
            $where['uid in '. $m] = $id;
        }

        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;

        $start = ($page - 1) * $pageSum;
        //获得分页数据
        //fetchLimit($offset = 0, $row = 20, $columns = array(), $where = array(), $order = array(), $like = array())
        //$where['uid = ? or username like ? or phone = ?'] = array($like, "%$like%", $like);
        if (count($id)) {
            $data = $this->_getPlayUserTable()->fetchLimit($start, $pageSum, array(), $where, array('uid'=>'desc'));
            $count = $this->_getPlayUserTable()->fetchCount($where);
        } else {
            $data = array();
            $count = 0;
        }

        $url = '/wftadlogin/circle/userpartner';
        $paginator = new Paginator($page, $count, $pageSum, $url);

        return array(
            'data' => $data,
            'pagedata' => $paginator->getHtml(),
            'userData' => $userData,
        );

    }


    // 主题贴推送到焦点图 页面
    public function mapAction() {

        // todo  检测焦点图是否满了
        $block_city = $this->getCity();
        $num = $this->_getPlayIndexBlockTable()->fetchCount(array('link_type' => 2, 'block_city' => $block_city));
        if ($num >= 5 ) {
            return $this->_Goto('已经有>=5个焦点图了, 请先取消别的焦点图');
        }

        //todo　检测是否已经推送了
        $id = $this->getQuery('id');
        $flag = $this->_getPlayIndexBlockTable()->get(array('type' => 7, 'link_id' => 7, 'tip' => $id));

        if ($flag) {
            return $this->_Goto('已经推送了');
        }

        return array(
            'id' => $id,
        );
    }

    // 主题贴推送到焦点图 保存
    public function saveMapAction() {
        $id = $this->getPost('id');
        $img = $this->getPost('img');

        $city = $this->getCity();
        $status = $this->_getPlayIndexBlockTable()->insert(array(
            'link_id' => 7,
            'type' => 7,
            'block_city' => $city,
            'dateline' => time(),
            'editor_image' => $img,
            'link_type' => 2, //1为列表  2 为焦点图
            'tip' => $id,
            'block_order' => 99,
        ));

        if ($status) {
            return $this->_Goto('成功', '/wftadlogin/circle/word');
        } else {
            return $this->_Goto('失败');
        }

    }

    /**
     * 评论奖励
     */
    public function awardAction(){
        $where['city'] = $this->getAdminCity();
        $id = $this->getQuery('id', 0);
        $cid = $this->getQuery('cid', 0);
        $aid = $_COOKIE['id'];
        $userData = $this->_getPlayAdminTable()->get(array('id' => $aid)); //todo 编辑状态 修改

        if (!$userData || !$userData->bind_user_id) {
            return $this->_Goto('该编辑账号 没有绑定uid', '/wftadlogin/setting/editor');
        }

        $mongo = $this->_getMongoDB();
        $circle = $mongo->social_circle;
        $social = $circle->findOne(array('_id' => new \MongoId($cid)));
        $msg = $mongo->social_circle_msg->findOne(array('_id' => new \MongoId($id)));

        $where['city'] = $_COOKIE['city'];
        $where['end_time > ?'] = time();
        $where['status > ?'] = 0;
        $where['residue > ?'] = 0;
        $where['is_close'] = 0;
        $where['new'] = 0;
        $cc = $this->_getCashCouponTable()->fetchAll($where);

        $remain = $this->getEditMoney($where['city']);

        return array(
            'cc' => $cc,
            'remain'=>$remain,
            'circle' => $social,
            'msg' => $msg,
            'msg_id' => $id,
            'uid' => $msg['uid']
        );
    }

    /**
     * 执行奖励
     */
    public function doawardAction(){
        $mid = $this->getPost('mid','');//msg id
        $cid = $this->getPost('cid','');//circle id
        $uid = $this->getPost('uid',0);
        $withdrawal = $this->getPost('withdrawal',0);
        $type = (int)$this->getPost('type',0);
        $city = $this->getAdminCity();
        $cash = $this->getPost('cash',0);
        $idcoupon = $this->getPost('coupon','');
        $coupon = explode('#',$idcoupon);
        //1现金券,2返现金
        if((int)$type === 2){
            $obj = $cash;
        }elseif((int)$type === 1){
            $obj['id'] = $coupon[0];
            $obj['cash'] = $coupon[1];
        }

        $mongo = $this->_getMongoDB();

        //评论添加奖励记录,额度使用方式,1现金券,2返现金
        $mongo->social_circle_msg->update(array('_id' => new \MongoId($mid)), array('$set' => array('award' => ['type'=>$type,'obj'=>$obj])));

        //用户现金券奖励记录
        if($type === 1){
            $cp = new Coupon();
            $status = $cp->addCashcoupon($uid,$coupon[0],$mid,12,$_COOKIE['id'],'',$city,0,$mid);
            if($status){
                $tips = '奖励成功.';
            }
        }elseif($type === 2){//用户资金＋
            //计算已经发出的奖励
            if(!$cash){
                return $this->_Goto('请选择金额');
            }

            $remain = $this->getEditMoney($city);
            if($cash > $remain){
                return $this->_Goto('您当月的返利额度已不足');
            }
            //待审核
            $data['uid'] = $uid;
            $data['gid'] = $mid;
            $data['give_type'] = 4;
            $data['get_info'] = '圈子发言小编奖励';
            $data['from_type'] = 3;
            $data['rebate_type'] = $withdrawal+1;//是否可以提现
            $data['single_rebate'] = $cash;//单笔金额
            $data['city'] = $city;
            $data['status'] = 1;
            $data['editor_id'] = $_COOKIE['id'];
            $data['editor'] = $_COOKIE['user'];
            $data['create_time'] = time();
            $data['give_num'] = 1;
            $data['total_num'] = 1;

            $status = $this->_getPlayWelfareRebateTable()->insert($data);
            if($status){
                $tips = '返利已申请，请及时联系财务审核员;审核此笔返利，奖励才会到用户账户.';
            }
        }

        $mongo->social_circle_msg->update(array('_id' => new \MongoId($mid)), array('$set' => array('accept'=>1)));

        return $this->_Goto($tips, '/wftadlogin/circle/word?id='.$cid);
    }

    /**
     * 返回编辑账号绑定uid 没有绑定返回0；
     */
    private function checkRight() {

        $aid = $_COOKIE['id'];
        $flag = 0;
        $adminData = $this->_getPlayAdminTable()->get(array('id' => $aid)); //todo 编辑状态 修改

        if ($adminData) {
            $userData = $this->_getPlayUserTable()->get(array('uid' => $adminData->bind_user_id)); //todo 用户状态
            if ($userData) {
                $flag = (int)$adminData->bind_user_id;
            }
        }

        return $flag;
    }

}