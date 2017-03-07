<?php
/**
 * Created by PhpStorm.
 * User: fandy
 * Date: 16-6-15
 * Time: 下午5:41
 */

namespace ApiKidsPlay\Controller;

use Deyi\BaseController;
use library\Fun\M;
use library\Service\System\Cache\RedCache;
use Deyi\Social\SendSocialMessage;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Response;
use Deyi\JsonResponse;

class ConsultController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;
    use SendSocialMessage;

    public function indexAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = (int)$this->getParams('uid');
        $bid = (int)$this->getParams('play_id',0);  //活动id
        $eid = (int)$this->getParams('event_id',0);  //场次id
        $msg = $this->getParams('message', '', false); // 内容
        if (!$msg or (!$bid and !$eid)) {
            return $this->jsonResponseError('内容未填写');
        }

        $uid_data = $this->_getPlayUserTable()->get(array('uid' => $uid));

        if (!$uid_data or $uid_data->status != 1) {
            return $this->jsonResponseError('用户不存在或已禁用');
        } else {
            $author = $this->hidePhoneNumber($uid_data->username);
            $img = $uid_data->img;
        }

        $data = [];
        if($eid){
            $data = $this->_getPlayExcerciseEventTable()->getEventInfo(array('play_excercise_event.id' => $eid));
            $bid=$data->bid;
        }elseif($bid){
            $data = $this->_getPlayExcerciseBaseTable()->get(array('id' => $bid));
        }

        if ($data) {
            $object_data = array(
                'object_id' => $eid,//对象id
                'object_bid' => $bid,
                'object_title' => $data->name,
                'object_img' => $this->_getConfig()['url'] . $data->thumb,
            );
        } else {
            return $this->jsonResponseError('对象不存在');
        }

        //判断是否已经购买
        $buyProof = M::getPlayOrderInfoTable()->get(array('coupon_id' => $bid, 'user_id' => $uid, 'pay_status >= 2'));
        $is_buy = $buyProof ? 1 : 0;

        $data_arr = array(
            'object_data' => $object_data,
            'msg' => $msg,
            'uid' => (int)$uid,
            'img' => $img,
            'username' => $author,
            'dateline' => time(),
            'status' => 1,
            'city' => $data->city ?: 'WH',
            'type' => 7,//活动
            'reply' => array(),
            'is_buy' => $is_buy,
        );

        $social_circle_msg_post = $this->_getMdbConsultPost();
        $status = $social_circle_msg_post->save($data_arr);

        if (!$status) {
            return $this->jsonResponseError('咨询失败');
        }

        $consult_num = $this->_getMdbConsultPost()->find(array(
            'status' => array('$gte' => 1),
            'type' => 7,
            '$or' => array(array('object_data.object_id' => $eid,'object_data.object_bid' => $bid))
        ))->count();
        $this->_getPlayExcerciseEventTable()->update(array('query_number' => $consult_num), array('id' => $eid));

        $consult_num = $this->_getMdbConsultPost()->find(array(
            'status' => array('$gte' => 1),
            'type' => 7,
            'object_data.object_bid' => (int)$object_data['object_bid']
        ))->count();
        $this->_getPlayExcerciseBaseTable()->update(array('query_number' => $consult_num), array('id' => (int)$object_data['object_bid']));

        //清理咨询缓存
        RedCache::del('D:event_consult:' . $eid);

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

        return $this->jsonResponse(array('status' => 1, 'message' => '回复成功'));

    }

    //单个活动咨询列表
    public function listAction()
    {

        if (!$this->pass()) {
            return $this->failRequest();
        }

        $bid = (int)$this->getParams('play_id', 0);
        $eid = (int)$this->getParams('event_id', 0);
        $last_mid = $this->getParams('last_id');
        $pagenum = $this->getParams('page_num', 10);

        if (!$bid and !$eid) {
            return $this->jsonResponseError('参数错误');
        }

        if($last_mid and !$this->checkMid($last_mid)){
            return $this->jsonResponseError('id类型错误');
        }

        $consult = [];

        if($eid) {//如果提供了场次，就取单个场次的
            $consult = $this->_getMdbConsultPost()->find(array(
                'status' => array('$gte' => 1),
                'type' => 7,
                'object_data.object_id' => (int)$eid,
                '_id' => array('$lt' => new \MongoId($last_mid))
            ))->sort(array('status' => -1, 'dateline' => -1))->limit($pagenum);
        }elseif($bid) {
            $consult = $this->_getMdbConsultPost()->find(array(
                'status' => array('$gte' => 1),
                'type' => 7,
                'object_data.object_bid' => $bid,
                '_id' => array('$lt' => new \MongoId($last_mid))
            ))->sort(array('status' => -1, 'dateline' => -1))->limit($pagenum);
        }

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
}
