<?php

namespace Admin\Controller;

use Deyi\Consult\Push;
use Deyi\GetCacheData\CityCache;
use Deyi\GeTui\GeTui;
use Deyi\JsonResponse;
use Deyi\Paginator;
use Deyi\SendMessage;
use Deyi\Social\SendSocialMessage;
use Deyi\GetCacheData\UserCache;
use library\Fun\M;
use library\Service\User\Consult;
use Zend\View\Model\ViewModel;
use Zend\Db\Sql\Expression;

class ConsultController extends BasisController
{
    use JsonResponse;
    use SendSocialMessage;

    //咨询列表
    public function indexAction()
    {
        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;

        $begin_time = $this->getQuery('begin_time', 0);
        $end_time = $this->getQuery('end_time', 0);

        $type = (int)$this->getQuery('type', 0);
        $hidden = (int)$this->getQuery('hidden', 0);

        $goods_id = (int)$this->getQuery('goods_id', 0);
        $goods = $this->getQuery('goods', '');
        $id = $this->getQuery('id');
        $is_buy = $this->getQuery('is_buy');

        $p_city = $this->getBackCity();

        $where = array();

        if ($begin_time > 0) {//
            $where['dateline'] = array('$gte' => strtotime($begin_time));
        }
        if ($end_time > 0) {//
            $where['dateline'] = array('$lte' => strtotime($end_time) + 3600 * 24);
        }
        if ($end_time > 0 && $begin_time > 0) {//正在发放
            $where['dateline'] = array('$lte' => strtotime($end_time) + 3600 * 24, '$gte' => strtotime($begin_time));
        }
        if ($goods_id !== 0) {
            $where['$or'] = array(
                array(//玩伴
                    'object_data.object_id' => (int)$goods_id,
                ),
                array(//我关注别人 别人没关注我
                    'object_data.object_bid' => (int)$goods_id,
                )
            );
        }

        if ($is_buy) {
            if ($is_buy == 1) {
                $where['is_buy'] = array('$gte' => 1);
            } elseif ($is_buy == 2) {
                $where['is_buy'] = array('$lte' => 0);
            }
        }

        if ($hidden) {
            if ($hidden == 1) {
                $where['status'] = 0;
            } elseif ($hidden == 2) {
                $where['status'] = array('$gte' => 1);
            }
        }

        if ($goods) {
            $where['object_data.object_title'] = new \MongoRegex("/{$goods}/");
        }

        if ($type) {
            if ($type == 1) {
                $where['reply.uid'] = array('$exists' => true);
            }

            if ($type == 2) {
                $where['reply.uid'] = array('$exists' => false);
            }
        }

        if ($p_city) {
            $where['city'] = $p_city;
        }

        $order = array('dateline' => -1, 'status' => -1, 'reply' => 1);

        $cursor = $this->_getMdbConsultPost()->find($where)->limit($pageSum)->skip($start)->sort($order);
        $count = $this->_getMdbConsultPost()->find($where)->count();

        //创建分页
        $url = '/wftadlogin/consult';
        $paginator = new Paginator($page, $count, $pageSum, $url);

        $users = [];
        foreach ($cursor as $cs) {
            $users[] = $cs['uid'];
        }

        if (count($users)) {
            $userDataTemp = $this->_getPlayUserTable()->fetchLimit(0, 11, ['phone', 'uid'],
                ['uid' => $users])->toArray();
        }
        $userData = [];
        foreach ($userDataTemp as $udt) {
            $userData[$udt['uid']] = $udt['phone'];
        }

        $circle = '';
        if ($id) {
            $circle = $count->_getMdbConsultPost()->findOne(array('_id' => new \MongoId($id)));
        }

        $url = '';
        foreach ($_GET as $k => $v) {
            $url .= $k;
            $url .= '=' . $v . '&';
        }

        $url = $url ? ('?' . rtrim($url, '&')) : '';
        $untime = time() + 7200;
        setcookie('consult_p', $url, $untime, '/');

        $citys = $this->getAllCities();

        return array(
            'data' => $cursor,
            'userData' => $userData,
            'pageData' => $paginator->getHtml(),
            'circle' => $circle,
            'city_select' => $citys,
            'citys' => $citys,
            'filtercity' => CityCache::getFilterCity($p_city),
        );

    }

    //咨询置顶 隐藏 回复正常
    public function updateAction()
    {
        $id = $this->getQuery('id');
        $type = $this->getQuery('type');

        if (!$this->checkMid($id)) {
            return $this->_Goto('非法操作');
        }

        if (!in_array($type, array('up', 'hidden', 'same'))) {
            return $this->_Goto('非法操作');
        }

        if ($type === 'up') {//置顶
            $this->_getMdbConsultPost()->update(array('_id' => new \MongoId($id)),
                array('$set' => array('status' => 2)));
        }

        if ($type === 'hidden') {//隐藏
            $this->_getMdbConsultPost()->update(array('_id' => new \MongoId($id)),
                array('$set' => array('status' => 0)));
        }

        if ($type === 'same') {//取消隐藏
            $this->_getMdbConsultPost()->update(array('_id' => new \MongoId($id)),
                array('$set' => array('status' => 1)));
        }

        $consultData = $this->_getMdbConsultPost()->findOne((array('_id' => new \MongoId($id))));
        if ((int)$consultData['type'] === 7) {
            $consult_num = $this->_getMdbConsultPost()->find(array(
                'status' => array('$gte' => 1),
                'type' => 7,
                'object_data.object_bid' => (int)$consultData['object_data']['object_bid']
            ))->count();
            $this->_getPlayExcerciseBaseTable()->update(array('query_number' => $consult_num),
                array('id' => (int)$consultData['object_data']['object_id']));
        } else {
            $consult_num = $this->_getMdbConsultPost()->find(array(
                'status' => array('$gte' => 1),
                'type' => array('$ne' => 7),
                'object_data.object_id' => (int)$consultData['object_data']['object_id']
            ))->count();
            $this->_getPlayOrganizerGameTable()->update(array('consult_num' => $consult_num),
                array('id' => (int)$consultData['object_data']['object_id']));
        }

        //更新待回复咨询数量缓存
        Consult::delReplayConsult($this->getBackCity());

        return $this->_Goto('操作成功');
    }

    public function getWordShareAction()
    {
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
            $statu = $this->_getPlayIndexBlockTable()->get(array(
                'link_id' => 7,
                'type' => 7,
                'tip' => $cid,
                'link_type' => 2
            ));
            echo $statu ? '焦点图' : '非焦点图';
            exit;
        }


        exit;
    }

    public function newReplyAction()
    {
        $aid = $_COOKIE['id'];
        $userData = $this->_getPlayAdminTable()->get(array('id' => $aid)); //todo 编辑状态 修改

        $id = $this->getQuery('id');

        $msg = $this->_getMdbConsultPost()->findOne(array('_id' => new \MongoId($id)));

        return array(
            'userData' => $userData,
            'msg' => $msg,
        );
    }

    //咨询回复
    public function replyAction()
    {
        $message = trim($this->getPost('info'));
        $id = $this->getPost('mid');
        if (!$id) {
            return $this->_Goto('非法操作');
        }
        if (!$message) {
            return $this->_Goto('回复内容不能为空');
        }

        $data = $this->_getMdbConsultPost()->findOne(array('_id' => new \MongoId($id)));
        $user_data = $this->_getPlayUserTable()->get(array('uid' => $data['uid']));
        if (!$data) {
            return $this->_Goto("咨询不存在");
        }

        $save_data = array(
            'msg' => $message,
            'uid' => (int)$_COOKIE['id'],
            'username' => $_COOKIE['user'], //$this->hidePhoneNumber($userData->username),
            'dateline' => time(),
            'img' => "",
        );

        $status = $this->_getMdbConsultPost()->update(array('_id' => new \MongoId($id)), array('$set' => array('reply' => $save_data)));

        // $type=9;
        // if(!$data['type'] or $data['type']==1){
        //     $type=3;
        // }
        // $id= $type==3?($data['object_data']['object_id']):($data['object_data']['object_bid']);
        // $push=new Push();
        // $push->consult_push($user_data->uid,$user_data->token,"小玩回复了你的咨询【{$data['object_data']['object_title']}】,快来查看吧！",$type,$id);

        $data_message_type = 15; // 消息类型为咨询回复
        $data_inform_type  = 11; // 咨询回复推送

        // 评论回复推送内容
        $data_name = $data['object_data']['object_title'];
        if (!empty($data_name)) {
            $data_name = '"' . $data_name . '"';
        } else {
            $data_name = '';
        }
        $data_inform  = "【玩翻天】小玩回复了您的咨询" . $data_name . "，快来看看吧！";

        // 评论回复系统消息
        $data_message = "小玩家回复了您" . $data_name . "的咨询，快来看看吧！";

        $data_link_id = array(
            'mid' => $id
        );
        $class_sendMessage = new SendMessage();
        $class_sendMessage->sendMes($data['uid'], $data_message_type, '', $data_message, $data_link_id);
        $class_sendMessage->sendInform($data['uid'], $user_data->token, $data_inform, $data_inform, '', $data_inform_type, $id);

        //更新待回复咨询数量缓存
        Consult::delReplayConsult($this->getBackCity());

        return $this->_Goto($status ? '成功' : '失败', '/wftadlogin/consult' . $_COOKIE['consult_p']);
    }

    public function delAction()
    {

        $id = $this->getQuery('id');
        if (!$id) {
            return $this->_Goto('非法操作');
        }


        $data = array();
        $status = $this->_getMdbConsultPost()->update(array('_id' => new \MongoId($id)),
            array('$set' => array('reply' => $data)));

        return $this->_Goto($status ? '删除成功' : '删除失败', '/wftadlogin/consult');
    }

    //咨询详情
    public function consultInfoAction()
    {
        $id = $this->getQuery('id');
        $data = $this->_getMdbConsultPost()->findOne(array('_id' => new \MongoId($id)));

        return array(
            'data' => $data,
        );
    }

    public function wordInfoAction()
    {
        $id = $this->getQuery('id');
        $msg = $this->_getMdbConsultPost()->findOne(array('_id' => new \MongoId($id)));

        return array(
            'msg' => $msg,
            'post' => $msg['reply'],
        );
    }

    //虚拟用户添加评论
    public function virPostAction()
    {

        $type = $this->getQuery('type', '');
        $object_id = (int)$this->getQuery('id', 0);
        if (!in_array($type, array('good', 'activity'))) {
            return $this->_Goto('非法操作');
        }

        $userCache = new UserCache();
        $virUser = $userCache->getVirUserCache();

        if (!$virUser) {
            return $this->_Goto('虚拟用户不存在, 请添加');
        }

        $where = array();
        $age = array(
            'min' => 0,
            'max' => 100
        );

        $eventId = 0;
        if ($type == 'good') {
            $where['msg_type'] = 2;
            $where['object_data.object_id'] = $object_id;
            $objectData = $this->_getPlayOrganizerGameTable()->get(array('id' => $object_id));

            if (!$objectData) {
                return $this->_Goto('评论对象不存在');
            }

            $age['min'] = $objectData->age_min;
            $age['max'] = $objectData->age_max;
            //报名数不为0
            if (!$objectData->buy_num && !$objectData->coupon_vir) {
                return $this->_Goto('该商品无报名, 不能评论');
            }

        } else {
            $where['msg_type'] = 7;
            $where['object_data.object_bid'] = $object_id;
            $objectData = $this->_getPlayExcerciseBaseTable()->get(array('id' => $object_id));

            if (!$objectData) {
                return $this->_Goto('评论对象不存在');
            }

            $age['min'] = $objectData->start_age;
            $age['max'] = $objectData->end_age;

            //报名数不为0
            if (!$objectData->join_number) {
                return $this->_Goto('该活动无报名, 不能评论');
            }

            $adaptor = $this->_getAdapter();

            $sql = "SELECT
	coupon_id
FROM
	play_order_info
WHERE
	order_type = ?
AND order_status = 1
AND pay_status > 1
AND bid = {$object_id}
GROUP BY
	coupon_id";

            $res = $adaptor->query($sql, array(3));
            $eventIds = array();
            foreach ($res as $r) {
                $eventIds[] = $r['coupon_id'];
            }
            $eventId = $eventIds[array_rand($eventIds, 1)];

            if (!$eventId) {
                return $this->_Goto('出现异常');
            }

        }

        /**
         * 该虚拟用户最近一周没有评论 活动或者商品
         * 该虚拟用户从来都没有评论过该商品或者活动
         * 该虚拟用户baby 适合 该活动或者商品的适玩年龄
         */

        //该虚拟用户最近一周没有评论 活动或者商品
        $nonPostUser = $this->_getMdbSocialCircleMsg()->find(array(
            'msg_type' => array('$in' => array(2, 7)),
            'uid' => array('$in' => $virUser),
            'dateline' => array('$gt' => time() - 604800)
        ));

        $userNoPost = array();
        foreach ($nonPostUser as $post) {
            if (!in_array($post['uid'], $userNoPost)) {
                $userNoPost[] = $post['uid'];
            }
        }

        $virUserOne = array_diff($virUser, $userNoPost);

        //该虚拟用户从来都没有评论过该商品或者活动
        shuffle($virUserOne);
        $where['uid'] = array('$in' => $virUserOne);

        $nonObjectPostUser = $this->_getMdbSocialCircleMsg()->find($where);

        $userObjectPost = array();
        foreach ($nonObjectPostUser as $objectPost) {
            if (!in_array($objectPost['uid'], $userObjectPost)) {
                $userObjectPost[] = $objectPost['uid'];
            }
        }

        $virUserTwo = array_diff($virUserOne, $userObjectPost);
        //该虚拟用户baby 适合 该活动或者商品的适玩年龄
        $virUserThree = array();
        foreach ($virUserTwo as $vir) {
            $babyAge = $userCache->getBabyAge($vir);
            if (!($babyAge['min'] > $age['max'] || $babyAge['max'] < $age['min'])) {
                $virUserThree[] = $vir;
            }
        }

        if (!count($virUserThree)) {
            return $this->_Goto('没有满足条件的虚拟用户，请添加满足条件的虚拟用户再评价');
        }

        $uid = $virUserThree[array_rand($virUserThree, 1)];

        $vm = new ViewModel(array(
            'uid' => $uid,
            'objectData' => $objectData,
            'type' => ($type == 'good') ? 1 : 2,
            'eventId' => $eventId //场次id
        ));

        return $vm;

    }

    //保存虚拟用户评论
    public function saveVirPostAction()
    {
        $uid = (int)$this->getPost('uid');
        $object_id = (int)$this->getPost('object_id');
        $eid = (int)$this->getPost('eid', 0);
        $city = $this->getPost('city', 'WH');
        $star = (int)$this->getPost('star', 5);
        $content = $this->getPost('editorValue');
        $type = (int)$this->getQuery('type', 1);

        if (!$content) {
            return $this->_Goto('内容');
        }

        if (!in_array($type, array(1, 2))) {
            return $this->_Goto('非法操作');
        }

        if ($star < 0 || $star > 5) {
            return $this->_Goto('评分不正确');
        }

        $userCache = new UserCache();
        $virUser = $userCache->getVirUserCache();

        if (!in_array($uid, $virUser)) {
            return $this->_Goto('非法操作');
        }

        if ($type == 2) {
            $objectData = $this->_getPlayExcerciseEventTable()->get(array('bid' => $object_id, 'id' => $eid));
        } else {
            $objectData = $this->_getPlayOrganizerGameTable()->get(array('id' => $object_id));
        }

        if (!$objectData) {
            return $this->_Goto('非法操作');
        }

        $msg_type = ($type == 1) ? 2 : 7;

        //判断该用户评论没 一周内有没有评论
        $where = array(
            'msg_type' => $msg_type,
            'uid' => $uid,
            'dateline' => array('$gt' => time() - 604800)
        );

        $stand = $this->_getMdbSocialCircleMsg()->findOne($where);
        if ($stand) {
            return $this->_Goto('该用户评论过了');
        }


        $content = strip_tags(str_replace(array("<br/>", '&nbsp;', "<p"), array("\r\n", ' ', "\r\n<p"),
            htmlspecialchars_decode(trim($content), ENT_QUOTES)), '<img>');
        $content = preg_replace('/(\r\n)+/', "\r\n", $content);
        if (strpos($content, "\r\n") === 0) {
            $content = substr($content, 2);
        }
        $content = str_replace("\r\n", "\n", $content);

        //$content = strip_tags(str_replace(array("<br/>", '&nbsp;', "<p"), array("\r\n", ' ', "\r\n<p"), htmlspecialchars_decode(trim($content), ENT_QUOTES)), '<img>'); //内容

        $addr_x = '114.274895'; //经度
        $addr_y = '30.561448'; //纬度

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
                    if (trim(htmlspecialchars_decode($c, ENT_QUOTES))) {
                        $msg[] = array(
                            't' => 1,
                            'val' => htmlspecialchars_decode($c, ENT_QUOTES),
                        );
                    }
                }
            }
        }

        $ob_id = ($type == 2) ? $eid : $object_id;
        $result = $this->SendSocialMessage($uid, 0, '', json_encode($msg), $msg_type, $ob_id, $addr_x, $addr_y, 0, 0,
            array(), $star, $city, 0, 'vir_post');

        if ($result['status']) {

            if ($type == 2) { //活动

                $this->_getMdbGradingRecords()->update(array('object_id' => $object_id, 'type' => 7),
                    array('$inc' => array('total_score' => $star, 'total_number' => 1)), array('upsert' => true));
                $res = $this->_getMdbGradingRecords()->findOne(array('object_id' => $object_id, 'type' => 7));
                if ($res and $res['total_score']) {
                    $ave = bcdiv($res['total_score'], $res['total_number'], 1);
                    $this->_getPlayOrganizerGameTable()->update(array('star_num' => $ave), array('id' => $object_id));
                }

                $post_num_base = $this->_getMdbSocialCircleMsg()->find(array(
                    'status' => array('$gte' => 1),
                    'msg_type' => 7,
                    'object_data.object_bid' => $object_id
                ))->count();
                $post_num_event = $this->_getMdbSocialCircleMsg()->find(array(
                    'status' => array('$gte' => 1),
                    'msg_type' => 7,
                    'object_data.object_id' => $object_id
                ))->count();
                $this->_getPlayExcerciseBaseTable()->update(array('comment_number' => $post_num_base),
                    array('id' => $object_id));
                $this->_getPlayExcerciseEventTable()->update(array('comment_number' => $post_num_event),
                    array('id' => $eid));
            } elseif ($type == 1) { //商品

                $this->_getMdbGradingRecords()->update(array('object_id' => $object_id, 'type' => 2),
                    array('$inc' => array('total_score' => $star, 'total_number' => 1)), array('upsert' => true));
                $res = $this->_getMdbGradingRecords()->findOne(array('object_id' => $object_id, 'type' => 2));
                if ($res and $res['total_score']) {
                    $ave = bcdiv($res['total_score'], $res['total_number'], 1);
                    $this->_getPlayOrganizerGameTable()->update(array('star_num' => $ave), array('id' => $object_id));
                }
                $post_num = $this->_getMdbSocialCircleMsg()->find(array(
                    'status' => array('$gte' => 1),
                    'msg_type' => 2,
                    'object_data.object_id' => $object_id
                ))->count();
                $this->_getPlayOrganizerGameTable()->update(array('post_number' => $post_num),
                    array('id' => $object_id));
            }

            return $this->_Goto('成功');
        } else {
            return $this->_Goto($result['message']);
        }
    }

    // 获取咨询的数量
    public function alertConsultAction()
    {

        $city = $this->getBackCity();
        $count = Consult::getReplyConsult($city);

        if ($count) {
            return $this->jsonResponsePage(array('status' => $count, 'message' => '有咨询！     '));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '没有咨询！       '));
        }

        exit;
    }
}
