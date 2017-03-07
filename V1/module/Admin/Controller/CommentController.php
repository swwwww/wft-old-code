<?php

namespace Admin\Controller;

use Deyi\Account\Account;
use Deyi\BaseController;
use Deyi\Coupon\Coupon;
use Deyi\GetCacheData\CityCache;
use Deyi\GetCacheData\NoticeCache;
use Deyi\JsonResponse;
use Deyi\Paginator;
use Deyi\SendMessage;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Sql\Expression;
use Deyi\ImageProcessing;

use Deyi\Integral\Integral;

class CommentController extends BasisController
{
    use JsonResponse;

    //咨询列表
    public function indexAction()
    {
        $mongo = $this->_getMongoDB();
        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;
        $id = $this->getQuery('id');

        //$where = array('status' => array('$gte' => 0));
        $where['msg_type'] = array('$in' => [2, 3, 7]);

        $begin_time = $this->getQuery('begin_time', 0);
        $end_time = $this->getQuery('end_time', 0);

        $gettype = (int)$this->getQuery('gettype', 0);
        $use_status = (int)$this->getQuery('use_status', 0);

        $goods_id = (int)$this->getQuery('goods_id', 0);
        $user_id = (int)$this->getQuery('user_id', 0);
        $user = $this->getQuery('user', '');

        $shop_id = (int)$this->getQuery('shop_id', 0);
        $hd_id = (int)$this->getQuery('hd_id', 0);
        $is_vir = (int)$this->getQuery('is_vir', 0);

        $p_city = $this->chooseCity();

        if((int)$begin_time > 0 && (int)$end_time === 0){//待发放
            $where['dateline'] =  array('$gte' => strtotime($begin_time));
        }
        if((int)$end_time > 0 && (int)$begin_time === 0){//正在发放
            $where['dateline'] =  array('$lte' => (24*3600)+strtotime($end_time));
        }
        if((int)$end_time > 0 && (int)$begin_time > 0){//正在发放
            $where['dateline'] =  array('$lte' => (24*3600)+strtotime($end_time),'$gte' => strtotime($begin_time));
        }

        if($goods_id !== 0 ){
            $where['msg_type'] = 2;
            $where['object_data.object_id'] = $goods_id;
        }
        if($user_id !== 0 ){
            $where['uid'] = $user_id;
        }
        if($shop_id !== 0 ){
            $where['msg_type'] = 3;
            $where['object_data.object_id'] = $shop_id;
        }

        if($hd_id !== 0 ){
            $where['msg_type'] = 7;
            $where['object_data.object_bid'] = $hd_id;
        }

        if($user !== '' ){
            $where['username'] = new \MongoRegex("/{$user}/");
        }

        if($gettype == 1){ //是否给奖励
            $where['object_data.post_award'] = 2;
        }elseif($gettype == 2){
            $where['object_data.post_award'] = 1;
        }

        if($use_status == 1){
            $where['accept'] = 1;
        }

        if($use_status == 2){
            $where['accept'] =  array('$ne'=>1);
        }

        if(!empty($p_city)){//
            $where['city'] = array($p_city);
        }

        $where['is_vir'] = array('$exists' => $is_vir);

        $order = array('dateline' => -1,'status' => -1);

        $cursor = $mongo->social_circle_msg->find($where)->limit($pageSum)->skip($start)->sort($order);
        $count = $mongo->social_circle_msg->find($where)->count();
        $cursor = iterator_to_array($cursor);

        foreach($cursor as &$c){
            $user = $this->_getPlayUserTable()->get(['uid'=>$c['uid']]);
            $c['phone'] = $user->phone;
        }

        //创建分页
        $url = '/wftadlogin/comment';
        $paginator = new Paginator($page, $count, $pageSum, $url);

        //当前页面的点赞信息
        $cid = [];
        foreach ($cursor as $c) {
            $cid[] = (string)$c['_id'];
        }

        if(count($cid) > 0){
            $cc = $this->getCashCoupon($cid);
            $ac = $this->getAccount($cid);
            $score = $this->getIntegral($cid);
        }

        //获取所有积分
        return array(
            'data' => $cursor,
            'pagedata' => $paginator->getHtml(),
            'cc' => $cc?:[],
            'ac' => $ac?:[],
            'score' => $score?:[],
            'filtercity' => CityCache::getFilterCity($p_city),
            'citys'=>$this->getAllCities()
        );

    }

    public function newWordAction()
    {
        $aid = $_COOKIE['id'];
        $userData = $this->_getPlayAdminTable()->get(array('id' => $aid)); //todo 编辑状态 修改

        $city = $this->_getPlayCityTable()->fetchAll(['is_close' => 0]);

        if (!$userData || !$userData->bind_user_id) {
            return $this->_Goto('该编辑账号 没有绑定uid', '/wftadlogin/setting/editor');
        }


        $id = $this->getQuery('id');
        $mid = $this->getQuery('mid');
        $mongo = $this->_getMongoDB();


        $msg = '';
        if ($mid && $this->checkMid($mid)) {
            $msg = $mongo->social_circle_msg->findOne(array('_id' => new \MongoId($mid)));
        }

        return array(
            'userData' => $userData,
            'msg' => $msg,
            'city' => $city
        );

    }

    public function saveWordAction()
    {

        $uid = (int)$this->getPost('uid');
        $cid = $this->getPost('cid');
        $title = htmlspecialchars_decode($this->getPost('title'), ENT_QUOTES);
        $content = $this->getPost('editorValue');

        $city = $this->getPost('city', []);

        if (empty($city)) {
            return $this->_Goto('请选择城市！');
        }

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
            $content = preg_replace('/<img(.*?) src=(.*?)>/', '$$' . $s . '$$', $content, 1);
        }

        $co = explode('$$', $content);

        $msg = array();
        foreach ($co as $c) {
            if ($c) {
                if (stripos($c, '/') === 0) {
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
            $mongon->social_circle_msg->update(array('_id' => new \MongoId($mid)), array('$set' => array("title" => $title, 'msg' => $msg, 'city' => $city)));
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
        $circle_data = $mongon->social_circle_main->findOne(array('_id' => new \MongoId($cid)));

        // 内容处理
        $status = $mongon->social_circle_msg->insert(array(
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
            'city' => $city,
            'addr' => array(
                'type' => 'Point',
                'coordinates' => array($addr_x, $addr_y)
            )
        ));

        if ($status) {

            //更新圈子消息数量
            $mongon->social_circle_main->update(array('_id' => new \MongoId($cid)), array('$inc' => array('msg' => 1, 'today_msg' => 1)));
            $this->_getPlayUserTable()->update(array('circle_msg' => new Expression('circle_msg+1')), array('uid' => $uid));
            return $this->_Goto('成功', '/wftadlogin/circle/word?id=' . $cid);

        } else {
            return $this->_Goto('失败', '/wftadlogin/circle/word?id=' . $cid);
        }
    }

    public function updateWordAction()
    {
        $type = $this->getQuery('type');

        $id = $this->getQuery('id');

        if(!$this->checkMid($id)){
            return $this->_Goto('发言不存在');
        }

        $uid = (int)$this->getQuery('uid');
        $mongo = $this->_getMongoDB();
        $wordData = $this->_getMdbSocialCircleMsg()->findOne(array('_id' => new \MongoId($id)));


        if($wordData){
            if($this->getAdminCity()!=1 && !in_array($this->getAdminCity(),$wordData['city'])){
                return $this->_Goto('参数错误');
            }
        }else{
            return $this->_Goto('参数错误');
        }


        if ($type == 'up') { //置顶
            $count = $mongo->social_circle_msg->find(array("status" => 2, 'cid' => $wordData['cid']))->count();
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
            $count = $mongo->social_circle_msg->find(array("status" => 3))->count();
            if ($count > 2) {
                return $this->_Goto('置顶动态数量已经有三个了');
            }
            $mongo->social_circle_msg->update(array("uid" => $uid, '_id' => new \MongoId($id)), array('$set' => array("status" => 3)));
            $this->loadWord($wordData['cid'], $uid);
            return $this->_Goto('置顶成功');
        }

    }

    public function loadWord($cid, $uid)
    {

        //更新圈子消息数量 及用户的发言数量
        $circleMessageData = $this->_getMdbSocialCircleMsg()->find(array('cid' => $cid, 'status' => array('$gt' => 0)));
        $userMessageData = $this->_getMdbSocialCircleMsg()->find(array('uid' => $uid, 'status' => array('$gt' => 0)));
        $this->_getPlayUserTable()->update(array('circle_msg' => $userMessageData->count()), array('uid' => $uid));

    }


    public function wordInfoAction()
    {
        $id = $this->getQuery('id');
        $page = $this->getQuery('p', 1);
        $mongo = $this->_getMongoDB();

        $msg = $mongo->social_circle_msg->findOne(array('_id' => new \MongoId($id)));

        $pageSum = 10;
        $start = ($page - 1) * $pageSum;

        $where = array('mid' => $id, 'status' => array('$gt' => -1));
        $order = array('status' => -1, 'dateline' => 1);
        $post = $mongo->social_circle_msg_post->find($where)->limit($pageSum)->skip($start)->sort($order);
        $count = $mongo->social_circle_msg_post->find($where)->count();



        //创建分页
        $url = '/wftadlogin/comment/wordinfo';
        $paginator = new Paginator($page, $count, $pageSum, $url);

        return array(
            'msg' => $msg,
            'post' => $post,
            'pagedata' => $paginator->getHtml(),
        );
    }

    public function newReplyAction()
    {
        $aid = $_COOKIE['id'];
        $userData = $this->_getPlayAdminTable()->get(array('id' => $aid)); //todo 编辑状态 修改

        if (!$userData || !$userData->bind_user_id) {
            return $this->_Goto('该编辑账号 没有绑定uid', '/wftadlogin/setting/editor');
        }


        $id = $this->getQuery('id');
        $mongo = $this->_getMongoDB();


        $msg = $mongo->social_circle_msg->findOne(array('_id' => new \MongoId($id)));

        return array(
            'userData' => $userData,

            'msg' => $msg,
        );
    }

    public function saveReplyAction() {

        $mid = $this->getPost('mid');//圈子消息id

        $uid = (int)$this->getPost('uid');
        $info = strip_tags(htmlspecialchars_decode(trim($this->getPost('info'))), ENT_QUOTES);// 回复内容
        $addr_x = '114.274895'; //经度
        $addr_y = '30.561448';//纬度
        if (!$info) {
            return $this->_Goto('回复为空');
        }
        $mongo = $this->_getMongoDB();

        // 管理员的用户信息
        $user_data = $this->_getPlayUserTable()->get(array('uid' => $uid));

        if (!$user_data) {
            return $this->_Goto('用户不存在');
        }

        //圈子信息
        $msg_data = $mongo->social_circle_msg->findOne(array('_id' => new \MongoId($mid), 'status' => array('$gt' => 0)));

        if (!$msg_data) {
            return $this->_Goto('消息不存在,或已删除');
        }

        // 评论用户的用户信息
        $data_comment_user = $this->_getPlayUserTable()->get(array('uid' => $msg_data['uid']));

        //更新发言回复数
        $mongo->social_circle_msg->update(array('_id' => new \MongoId($mid)), array('$inc' => array('replay_number' => 1)));

        // 内容处理
        $content_arr = array(array('val' => $info, 't' => 1));

        $post_data = array(
            'mid' => $mid,//'主题消息id',
            'uid' => $uid,
            'cid' => 0,
            'username' => $user_data->username,
            'first' => 0,// '是否主题贴',
            'title' => $msg_data['title'],
            'msg' => $content_arr,//回复内容
            'img' => $user_data->img, //用户头像
            'dateline' => time(),//时间戳
            'child' => $user_data->user_alias,
            'addr' => array(
                'type' => 'Point',
                'coordinates' => array($addr_x, $addr_y)
            ),
            'status' => 1,
        );

        $status = $mongo->social_circle_msg_post->insert($post_data);

        if ($status) {
            $this->loadReplay($mid);
            $data_message_type = 16; // 消息类型为评论回复
            $data_inform_type  = 12; // 评论回复推送

            // 评论回复推送内容
            $data_name = $msg_data['object_data']['object_title'];
            if (!empty($data_name)) {
                $data_name = '对' . $data_name;
            } else {
                $data_name = '';
            }
            $data_inform = "【玩翻天】您" . $data_name . "的评论有新回复啦，快来看看吧！";

            // 评论回复系统消息
            $data_message = "您" . $data_name . "的评论有新回复啦，快来看看吧！";

            $data_link_data = array(
                'mid'       => (string)$msg_data['_id'],
                'reply'     => (string)$post_data['_id'],
                'type'      => 0,                               // 0为文字回复， 1为点赞
                'reply_uid' => $uid,
                'from_uid'  => $msg_data['uid'],
            );

            $class_sendMessage = new SendMessage();
            $class_sendMessage->sendMes($msg_data['uid'], $data_message_type, '您的评论有新回复啦', $data_message, $data_link_data);
            $class_sendMessage->sendInform($msg_data['uid'], $data_comment_user->token, $data_inform, $data_inform, '', $data_inform_type, $mid);

            return $this->_Goto('回复成功', "/wftadlogin/comment/wordinfo?id={$mid}");
        } else {
            return $this->_Goto('回复失败', "/wftadlogin/comment/wordinfo?id={$mid}");
        }
    }

    public function updateReplyAction()
    {
        $type = $this->getQuery('type');
        $id = $this->getQuery('id');

        if(!$this->checkMid($id)){
            return $this->_Goto('无效ID');
        }

        $mongo = $this->_getMongoDB();
        $replyData = $this->_getMdbSocialCircleMsgPost()->findOne(array('_id' => new \MongoId($id)));


        if ($type == 'up') {
            $count = $mongo->social_circle_msg_post->find(array("status" => 2, 'mid' => $replyData['mid']))->count();
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

        if ($type == 'edit') { //回到正常
            $aid = $_COOKIE['id'];
            $userData = $this->_getPlayAdminTable()->get(array('id' => $aid)); //todo 编辑状态 修改

            if (!$userData || !$userData->bind_user_id) {
                return $this->_Goto('该编辑账号 没有绑定uid', '/wftadlogin/setting/editor');
            }

            $id = $this->getQuery('id');
            $mongo = $this->_getMongoDB();
            $msg_post = $mongo->social_circle_msg_post->findOne(array('_id' => new \MongoId($id)));

            $vm = new ViewModel(
                array(
                    'msg_post' => $msg_post,
                    'id' => $id,
                )
            );

            $vm->setTemplate('admin/comment/updatereply.phtml');

            return $vm;
        }

        if ($type === 'change') { //回到正常
            $info = strip_tags(htmlspecialchars_decode(trim($this->getPost('info'))), ENT_QUOTES);// 回复内容
            $content_arr = array(array('val' => $info, 't' => 1));
            $mongo->social_circle_msg_post->update(array('_id' => new \MongoId($id)), array('$set' => ['msg'=>$content_arr]));
            return $this->_Goto('成功', '/wftadlogin/comment/wordinfo?id='.$this->getPost('mid'));
        }
    }

    public function loadReplay($mid)
    {

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
    public function userMsgAction()
    {
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

    public function getMsgShareAction()
    {
        $type = $this->getQuery('type', 'prise');
        $id = $this->getQuery('id');
        $mongo = $this->_getMongoDB();


        if ($type == 'prise') {
            //todo 圈子内消息的 点赞 圈子 消息 回复 ？
            $msg = $mongo->social_circle_msg->findOne(array('_id' => new \MongoId($id)));

            if ($msg['title']) {
                echo $msg['title'];
            } else {
                $st = '';
                foreach ($msg['msg'] as $v) {
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

    /**
     * 评论奖励
     */
    public function awardAction()
    {
        $where['city'] = $_COOKIE['city'];

        $where['end_time > ?'] = time();
        $where['status > ?'] = 0;
        $where['residue > ?'] = 0;
        $where['is_close'] = 0;
        $where['new'] = 0;

        $id = $this->getQuery('id', 0);
        $uid = $this->getQuery('uid', 0);

        $cc = $this->_getCashCouponTable()->fetchAll($where);
        $city = $this->getAdminCity();

        $msg = '';
        if ($id && $this->checkMid($id)) {
            $msg = $this->_getMdbSocialCircleMsg()->findOne(array('_id' => new \MongoId($id)));
        }

        $remain = $this->getEditMoney($city);

        return array(
            'cc' => $cc,
            'remain' => $remain,
            'msg_id' => $id,
            'uid' => $uid,
            'msg' => $msg,
        );
    }

    /**
     * 执行奖励
     */
    public function doawardAction()
    {
        $mid = $this->getPost('mid', '');
        $uid = $this->getPost('uid', 0);
        $withdrawal = $this->getPost('withdrawal', 0);
        $type = (int)$this->getPost('type', 0);
        $city = array_key_exists('city', $_COOKIE) ? $_COOKIE['city'] : 'WH';
        $cash = $this->getPost('cash', 0);
        $idcoupon = $this->getPost('coupon', '');
        $coupon = explode('#', $idcoupon);
        //1现金券,2返现金
        if ((int)$type === 2) {
            $obj = $cash;
        } elseif ((int)$type === 1) {
            $obj['id'] = $coupon[0];
            $obj['cash'] = $coupon[1];
        }
        $start = strtotime(date('Y-m'));

        $mongo = $this->_getMongoDB();
        $msg = $mongo->social_circle_msg->findOne(array('_id' => new \MongoId($mid)));

        //wJiang
//        if ($msg['accept'] == 1) {
//            return $this->_Goto('该评论已经提交了奖励');
//        }

        //todo 判断 是否可以给予奖励
        if ($msg['msg_type'] == 2) {//商品

            $objectData = $this->_getPlayOrganizerGameTable()->get(array('id' => $msg['object_data']['object_id']));
            if (!$objectData) {
                return $this->_Goto('非法操作');
            }

            if ($objectData->post_award == 1) {//评论无奖
                return $this->_Goto('此商品评论无奖');
            }
            $give_type = 6;
            $get_info = '点评商品奖励';
        } elseif ($msg['msg_type'] == 3) {//游玩地
            $objectData = $this->_getPlayShopTable()->get(array('shop_id' => $msg['object_data']['object_id']));
            if (!$objectData) {
                return $this->_Goto('非法操作');
            }
            $give_type = 7;
            $get_info = '点评游玩地奖励';
            if ($objectData->post_award == 1) {//评论无奖
                return $this->_Goto('该游玩地评论无奖');
            }
        }elseif ($msg['msg_type'] == 7) {//活动
            return $this->_Goto('非法操作');
            $objectData = $this->_getPlayExcerciseEventTable()->get(array('id' => $msg['object_data']['object_id']));
            if (!$objectData) {
                return $this->_Goto('非法操作');
            }

            if (1) {//评论无奖
                return $this->_Goto('该游玩地评论无奖');
            }
        } else {
            return $this->_Goto('该评论类型无法给予奖励');
        }

        //用户现金券奖励记录
        if ($type === 1) {
            $cp = new Coupon();
            $status = $cp->addCashcoupon($uid,$coupon[0],$mid,9,$_COOKIE['id'],'',$city,0,$mid);
            if($status){
                $tips = '奖励成功.';
            }
        } elseif ($type === 2) {//用户资金＋
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
            $data['give_type'] = $give_type;
            $data['get_info'] = $get_info;
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

        //产生通知,存入缓存
        if($status){
            NoticeCache::setNewReward($msg['uid']);
            //评论添加奖励记录,额度使用方式,1现金券,2返现金
            $mongo->social_circle_msg->update(array('_id' => new \MongoId($mid)), array('$set' => array('accept'=>1,'award' => ['type' => $type, 'obj' => $obj])));
        }


        return $this->_Goto($status?$tips:'操作失败', '/wftadlogin/comment');
    }

    //获取所有积分
    private function getIntegral($id){
        if(is_array($id)){
            foreach($id as $i){
                if(!$this->checkMid($i)){
                    return 0;
                }
            }

            $cc = $this->_getPlayIntegralTable()->fetchAll(['type'=>[2,4,15,16,17,18,26],'msgid'=>$id]);

        }elseif($this->checkMid($id)){
            $cc = $this->_getPlayIntegralTable()->fetchAll(['type'=>[2,4,15,16,17,18,26],'msgid'=>$id]);
        }

        $subcc = $this->_getPlayIntegralTable()->fetchAll(['type'=>[103,104,106,107,108,109],'msgid'=>$id]);
        $sub_info = [];
        if($subcc){
            foreach($subcc as $c){
                if(array_key_exists($c['msgid'],$sub_info)){
                    $sub_info[$c['msgid']] += $c['total_score'];
                }else{
                    $sub_info[$c['msgid']] = $c['total_score'];
                }
            }
        }

        $cc_info = [];
        if(false === $cc || count($cc) === 0){
            return [];
        }
        foreach($cc as $c){
            if(array_key_exists($c['msgid'],$cc_info)){
                $cc_info[$c['msgid']] += $c['total_score'];
                if($sub_info[$c['msgid']]){
                    $cc_info[$c['msgid']] -= $sub_info[$c['msgid']];
                }
            }else{
                $cc_info[$c['msgid']] = $c['total_score'];
                if($sub_info[$c['msgid']]){
                    $cc_info[$c['msgid']] -= $sub_info[$c['msgid']];
                }
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

            $cc = $this->_getPlayAccountLogTable()->fetchAll(['action_type_id'=>[4,6,7,11],'msgid'=>$id]);

        }elseif($this->checkMid($id)){
            $cc = $this->_getPlayAccountLogTable()->fetchAll(['action_type_id'=>[4,6,7,11],'msgid'=>$id]);
        }
        $cc_info = [];
        if(false === $cc || count($cc) === 0){
            return [];
        }
        foreach($cc as $c){
            if(array_key_exists($c['msgid'],$cc_info)){
                $cc_info[$c['msgid']] += $c['flow_money'];
            }else{
                $cc_info[$c['msgid']] = $c['flow_money'];
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

            $cc = $this->_getCashCouponUserTable()->fetchAll(['get_type'=>[2,3,9],'msgid'=>$id]);

        }elseif($this->checkMid($id)){
            $cc = $this->_getCashCouponUserTable()->fetchAll(['get_type'=>[2,3,9],'msgid'=>$id]);
        }
        $cc_info = [];

        if(false === $cc || count($cc) === 0){
            return [];
        }
        foreach($cc as $c){
            if(array_key_exists($c['msgid'],$cc_info)){
                $cc_info[$c['msgid']] += $c['price'];
            }else{
                $cc_info[$c['msgid']] = $c['price'];
            }

        }

        return $cc_info;
    }
    /**
     * 返回编辑账号绑定uid 没有绑定返回0；
     */
    private function checkRight()
    {

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
