<?php

namespace ApiPost\Controller;

use Deyi\BaseController;
use library\Fun\M;
use library\Service\System\Cache\RedCache;
use Deyi\Social\SendSocialMessage;
use Deyi\SendMessage;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Response;
use Deyi\JsonResponse;

class ConsultController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;
    use SendSocialMessage;

    //发表咨询
    public function indexAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = (int)$this->getParams('uid');
        $object_id = (int)$this->getParams('gid');  //评论对象id
        $msg = $this->getParams('message', '',false); // 评论内容
        if (!$msg or !$object_id) {
            return $this->jsonResponseError('内容未填写');
        }

        $uid_data = $this->_getPlayUserTable()->get(array('uid' => $uid));
        if (!$uid_data or $uid_data->status != 1) {
            return $this->jsonResponseError('用户不存在或已禁用');
        } else {
            $author = $this->hidePhoneNumber($uid_data->username);
            $img = $uid_data->img;
        }

        $data = $this->_getPlayOrganizerGameTable()->get(array('id' => $object_id));
        if ($data) {
            $object_data = array(
                'object_id' => $object_id,//对象id
                'object_title' => $data->title,
                'object_img' => $this->_getConfig()['url'] . $data->thumb,
            );
        } else {
            return $this->jsonResponseError('对象不存在');
        }

        //判断是否已经购买
        $buyProof = M::getPlayOrderInfoTable()->get(array('coupon_id' => $object_id, 'user_id' => $uid, 'pay_status >= 2'));
        $is_buy = $buyProof ? 1 : 0;

        $data = array(
            'object_data' => $object_data,
            'msg' => $msg,
            'uid' => $uid,
            'img' => $img,
            'username' => $author,
            'dateline' => time(),
            'status' => 1,
            'type' => 1,
            'city'=>$data->city?:'WH',
            'reply' => array(),
            'is_buy' => $is_buy,
        );

        $social_circle_msg_post = $this->_getMdbConsultPost();
        $status = $social_circle_msg_post->save($data);

        if (!$status) {
            return $this->jsonResponseError('咨询失败');
        }

        $consult_num = $this->_getMdbConsultPost()->find(array(
            'status' => array('$gte' => 1),
            'type' => array('$ne' => 7),
            'object_data.object_id' => $object_id
        ))->count();
        $this->_getPlayOrganizerGameTable()->update(array('consult_num' => $consult_num), array('id' => $object_id));

        //清理咨询缓存
        RedCache::del('D:coupon_consult:' . $object_id);
        return $this->jsonResponse(array('status' => 1, 'message' => '咨询成功'));

    }

    //回复咨询
    public function replyAction()
    {

        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = (int)$this->getParams('uid');
        $cid = $this->getParams('cid');  //评论对象id
        $msg = $this->getParams('message', ''); // 评论内容

        if (!$msg or !$cid or !$uid) {
            return $this->jsonResponseError('内容未填写');
        }

        $uid_data = $this->_getPlayUserTable()->get(array('uid' => $uid));
        if (!$uid_data or $uid_data->status != 1) {
            return $this->jsonResponseError('用户不存在或已禁用');
        } else {
            $author = $this->hidePhoneNumber($uid_data->username);
            $img = $uid_data->img;
        }

        //  判断是否存在这个咨询


        $reply = array(
            'msg' => $msg,
            'uid' => $uid,
            'img' => $img,
            'username' => $author,
            'dateline' => time(),
        );

        $social_circle_msg_post = $this->_getMdbConsultPost();
        $status = $social_circle_msg_post->update(array('_id' => new \MongoId($cid)), array('$set' => array('reply' => $reply)));

        if (!$status) {
            return $this->jsonResponseError('回复失败');
        }

        $data_message_type = 15; // 消息类型为咨询回复
        $data_inform_type  = 11; // 咨询回复推送

        $data_consult_post = $this->_getMdbConsultPost()->findOne(array('_id' => new \MongoId($cid)));
        $data_user         = $this->_getPlayUserTable()->get(array('uid' => $data_consult_post['uid']));

        // 评论回复推送内容
        $data_name = $data_consult_post['object_data']['object_title'];
        if (!empty($data_name)) {
            $data_name = '"' . $data_name . '"';
        } else {
            $data_name = '';
        }
        $data_inform  = "【玩翻天】有小伙伴回复了您的咨询" . $data_name . "，快来看看吧！";

        // 评论回复系统消息
        $data_message = "有小伙伴回复了您" . $data_name . "的咨询，快来看看吧！";

        $data_link_id = array(
            'mid' => $cid
        );

        $class_sendMessage = new SendMessage();
        $class_sendMessage->sendMes($data_consult_post['uid'], $data_message_type, '', $data_message, $data_link_id);
        $class_sendMessage->sendInform($data_consult_post['uid'], $data_user->token, $data_inform, $data_inform, '', $data_inform_type, $cid);

        return $this->jsonResponse(array('status' => 1, 'message' => '回复成功'));
    }

    //单个商品咨询列表
    public function listAction()
    {

        if (!$this->pass()) {
            return $this->failRequest();
        }

        $coupon_id = (int)$this->getParams('coupon_id', 0);
        $last_mid = $this->getParams('last_mid');
        $pagenum = $this->getParams('pagenum', 10);
        if (!$coupon_id) {
            return $this->jsonResponseError('参数错误');
        }

        if($last_mid and !$this->checkMid($last_mid)){
            return $this->jsonResponseError('id类型错误');
        }


        $consult = $this->_getMdbConsultPost()->find(array('status' => array('$gte' => 1),'type'=>1, 'object_data.object_id' => $coupon_id, '_id' => array('$lt' => new \MongoId($last_mid))))->sort(array('status' => -1, 'dateline' => -1))->limit($pagenum);
        $data = array();
        foreach ($consult as $v) {
            $reply = [];
            if($v['reply']){
                $v['reply']['img'] = 'http://wan.wanfantian.com/uploads/2016/02/26/aca48a6ad9c735fa7edf966bbbe083b1.jpg';
                $v['reply']['username']='小玩';
                $reply = $v['reply'];
            }
            $data[] = array(
                'mid' => (string)$v['_id'],
                'uid' => $v['uid'],
                'img' => $this->getImgUrl($v['img']),
                'dateline' => $v['dateline'],
                'username' => $this->hidePhoneNumber($v['username']),
                'msg' => $v['msg'],
                'reply' => $reply ? array($reply) : array()
            );
        }

        return $this->jsonResponse($data);

    }

    public function consultReplyListAction () {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $param['uid']      = $this->getParams('uid');
        $param['page_num'] = (int)$this->getParams('page_num', 10);
        $param['page']     = (int)$this->getParams('page',1);//分页参数
        $param['offset']   = ($param['page'] - 1) * $param['pagenum'];

        $pdo       = $this->_getAdapter();

        $sql       = " SELECT * FROM play_user_message WHERE uid = ? AND type = 15 ORDER BY deadline DESC LIMIT ?,?";
        $sql_param = array(
            $param['uid'],
            $param['offset'],
            $param['page_num']
        );
        $data_consultposts = $pdo->query($sql, $sql_param);

        $sql               = " UPDATE play_user_message SET is_new = 0 WHERE uid = ? AND type = 15 ";
        $sql_param         = array(
            $param['uid'],
        );
        $data_update_status = $pdo->query($sql, $sql_param);

        if ($data_consultposts) {
            foreach ($data_consultposts as $key=>$val) {
                $data_link_id = json_decode($val->link_id);

                $mdb_param_consult_post = array(
                    '_id' => new \MongoId($data_link_id->mid)
                );
                $data_consult_post = $this->_getMdbConsultPost()->findOne($mdb_param_consult_post);

                if (empty($data_consult_post)) {
                    continue;
                }

                if (empty($data_consult_post['type']) || $data_consult_post['type'] == 1) {
                    // type不存在或为1则为商品
                    $data_object    = $this->_getPlayOrganizerGameTable()->get(array('id' => $data_consult_post['object_data']['object_id']));
                    $data_object_id = $data_consult_post['object_data']['object_id'];
                } elseif ($data_consult_post['type'] == 7) {
                    // type 为 7 则为活动
                    $data_object    = $this->_getPlayExcerciseEventTable()->get(array('id' => $data_consult_post['object_data']['object_bid']));
                    $data_object_id = $data_consult_post['object_data']['object_bid'];
                }

                $data_return[] = array(
                    "id"          => (string)$data_consult_post['_id'],
                    "uid"         => $data_consult_post['reply']['uid'],
                    "username"    => '小玩',
                    "avatar"      => 'http://wan.wanfantian.com/uploads/2016/02/26/aca48a6ad9c735fa7edf966bbbe083b1.jpg',
                    "dateline"    => $data_consult_post['dateline'],
                    "reply"       => $data_consult_post['reply']['msg'],
                    "message"     => $data_consult_post['msg'],
                    "object_type" => $data_consult_post['type'],
                    "object_data" => array(
                        "object_id"    => $data_object_id,
                        "object_title" => $data_consult_post['object_data']['object_title'],
                        "object_star"  => $data_object->star_num,
                        "object_img"   => $data_consult_post['object_data']['object_img']
                    )
                );
            }
        }

        $data_return = array(
            'reply' => $data_return ? $data_return : array()
        );

        return $this->jsonResponse($data_return);
    }

    //我的咨询列表
    public function mylistAction() {

        if (!$this->pass()) {
            return $this->failRequest();
        }
        $uid = (int)$this->getParams('uid', 0);
        $page = (int)$this->getParams('page', 1);
        $pagenum = (int)$this->getParams('pagenum', 10);

        $offset = ($page - 1) * $pagenum;


        $mdb_consult_post = $this->_getMdbConsultPost();

        $res = $mdb_consult_post->find(array('uid' => $uid, 'status' => 1))->sort(array('dateline' => -1))->skip($offset)->limit($pagenum);

        $data = array();
        foreach ($res as $v) {
            $data[] = array(
                'mid' => (string)$v['_id'],
                'uid' => $v['uid'],
                'img' => $this->getImgUrl($v['img']),
                'time' => $v['dateline'],
                'username' => $this->hidePhoneNumber($v['username']),
                'content' => $v['msg'],
                'type'=>$v['type']?:1,
                'object_data' => $v['object_data'],
                'answer' => $v['reply'] ? $v['reply'] : (object)array()
            );
        }

        return $this->jsonResponse($data);
    }


    function query($sql)
    {
        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $stmt = $db->query($sql);
        $result = $stmt->execute($stmt);
        return $result;
    }
}
