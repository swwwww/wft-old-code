<?php
namespace ApiSocial\Controller;

use Deyi\BaseController;
use Deyi\GetCacheData\CouponCache;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Response;
use Deyi\JsonResponse;

class PostController extends AbstractActionController
{
    use BaseController;
    use JsonResponse;


    /**
     * @return 发言详情
     */
    public function detailPostAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }
        $uid = (int)$this->getParams('uid');
        $last_repid = $this->getParams('last_repid');
        $pagenum = $this->getParams('pagenum', 10);
        $mid = $this->getParams('mid');


        if (!$this->checkMid($mid)) {
            return $this->jsonResponseError('数据不存在!');
        }

        $social_circle_msg = $this->_getMdbsocialCircleMsg();
        $social_circle_msg_post = $this->_getMdbsocialCircleMsgPost();
        $res = $social_circle_msg->findOne(array('_id' => new \MongoId($mid), 'status' => array('$gt' => 0)));

        $where = array('mid' => $mid, 'status' => array('$gt' => 0));
        if ($last_repid) {
            $where['_id'] = array('$gt' => new \MongoId($last_repid));
        }

        $msg_post_data = $social_circle_msg_post->find($where)->sort(array('status' => -1, '_id' => 1))->limit($pagenum);

        $answer = array();

        foreach ($msg_post_data as $v) {
            $answer[] = array(
                'repid' => (string)$v['_id'],
                'uid' => $v['uid'],
                'img' => $this->getImgUrl($v['img']),
                'username' => $this->hidePhoneNumber($v['username']),
                'message' => $v['msg'],
                'dateline' => $v['dateline']
            );

        }
        if (!$res) {
            return $this->jsonResponseError('内容不存在或已删除');
        }

        // 浏览数加1
        $social_circle_msg->update(array('_id' => new \MongoId($mid), 'status' => 1), array('$inc' => array('view_number' => 1)));

        $is_like = 0;
        if ($uid) {
            $like_data = $this->_getMdbSocialPrise()->findOne(array('type' => 1, 'object_id' => $mid, 'uid' => $uid));
            if ($like_data) {
                $is_like = 1;
            } else {
                $is_like = 0;
            }
        }
        if ($last_repid) {
            $data = array(
                'answer' => $answer  //评论
            );
            return $this->jsonResponse($data);
        }


        if (isset($res['object_data']['object_id'])) {
            $res['object_data']['star'] = CouponCache::getStar($res['object_data']['object_id'], $res['msg_type'] == 2 ? 1 : 3);
        }
        $data = array(
            'mid' => (string)$res['_id'],
            'uid' => $res['uid'],
            'img' => $this->getImgUrl($res['img']),
            'time' => $res['dateline'],
            'username' => $this->hidePhoneNumber($res['username']),
            'user_detail' => $res['child'],
            'is_like' => $is_like,
            'cid' => $res['cid'] ? $res['cid'] : '',
            'c_name' => $res['c_name'],
            'title' => $res['title'],
            'msg' => $res['msg'] ? $res['msg'] : [],
            'answer_num' => $res['replay_number'],
            'like_num' => $res['like_number'],
            'object_data' => $res['object_data'],
            'msg_type' => $res['msg_type'],
            'answer' => $answer  //评论
        );

        return $this->jsonResponse($data);
    }

    /**
     * @return 回复发言 评论 (显示发言列表)
     */
    public function answerAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }
        $uid = (int)$this->getParams('uid');
        $mid = $this->getParams('mid');
        $page = $this->getParams('page', 1);
        $limit = (int)$this->getParams('pagenum', 10);
//        if (!$uid) {
//            return $this->jsonResponseError('请先登录!');
//        }
        if (!$this->checkMid($mid)) {
            return $this->jsonResponseError('数据不存在!');
        }


        $social_circle_msg = $this->_getMdbsocialCircleMsg();
        $social_circle_msg_post = $this->_getMdbsocialCircleMsgPost();

        $msg_data = $social_circle_msg->findOne(array('_id' => new \MongoId($mid)));


        $offset = ($page - 1) * $limit;
        $msg_post_data = $social_circle_msg_post->find(array('mid' => $mid, 'status' => array('$gt' => 0)))->skip($offset)->limit($limit);

        $answer = array();

        foreach ($msg_post_data as $v) {
            $answer[] = array(
                'uid' => $v['uid'],
                'img' => $this->getImgUrl($v['img']),
                'username' => $this->hidePhoneNumber($v['username']),
                'title' => $msg_data['title'],  //主题的标题
                'msg' => $v['msg'],
                'dateline' => $v['dateline']
            );
        }
        if ($page >= 2) {
            return $this->jsonResponse(array('answer' => $answer));
        }
        return $this->jsonResponse(array(
                'uid' => $msg_data['uid'],
                'img' => $this->getImgUrl($msg_data['img']),
                'time' => $msg_data['dateline'],
                'username' => $this->hidePhoneNumber($msg_data['username']),
                'user_detail' => $msg_data['child'],
                // 'location' => '2.56km',
                'content' => $msg_data['msg'],
                'img_list' => $msg_data['img_list'],
                'answer_num' => $msg_data['replay_number'],
                'answer' => $answer
            )
        );
    }


    /**
     * @return 保存回复 评论
     */
    public function saveAnswerAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $mid = $this->getParams('mid');//圈子消息id
        $uid = (int)$this->getParams('uid');
        $data = $this->getParams('data', '', false);// 回复内容 json
        $addr_x = $this->getParams('addr_x');
        $addr_y = $this->getParams('addr_y');
        if (!$mid or !$uid or !$data) {
            return $this->jsonResponseError('mid或uid或内容不存在');
        }

        if (!$this->checkMid($mid)) {
            return $this->jsonResponseError('数据不存在!');
        }

        $city = $this->getCity();
        $user_data = $this->_getPlayUserTable()->get(array('uid' => $uid));

        $social_circle_msg = $this->_getMdbsocialCircleMsg();
        $msg_data = $social_circle_msg->findOne(array('_id' => new \MongoId($mid), 'status' => array('$gt' => 0)));

        if (!$msg_data) {
            return $this->jsonResponseError('消息不存在,或已删除');
        }

        //回复 限制 禁言   user整体是否禁言
        if ($msg_data['cid']) {
            $flag = $this->_getMdbSocialCircleUsers()->findOne(array('uid' => $uid, 'cid' => $msg_data['cid']));
            if ($flag['status'] === 0) {
                return $this->jsonResponseError('你已经被禁言');
            }
        }


        $social_circle = $this->_getMdbSocialCircle();
        if ($msg_data['cid']) {
            $social_circle->update(array('_id' => new \MongoId($msg_data['cid'])), array('$inc' => array('msg_post' => 1, 'today_msg_post' => 1)));
        }


        $data = json_decode(htmlspecialchars_decode($data, ENT_QUOTES), true);

        $obj = array(
            'mid' => $mid,//'主题消息id',
            'uid' => $uid,
            'cid' => $msg_data['cid'] ? $msg_data['cid'] : 0,
            'username' => $this->hidePhoneNumber($user_data->username),
            'first' => 0,// '是否主题贴',
            'title' => $msg_data['title'],
            'msg' => $data,//回复内容
            'img' => $this->getImgUrl($user_data->img), //用户头像
            'dateline' => time(),//时间戳
            'child' => $user_data->user_alias,
            'addr' => array(
                'type' => 'Point',
                'coordinates' => array($addr_x, $addr_y)
            ),
            'status' => 1,
            'city'=>$city,
        );

        $status = $this->_getMdbSocialCircleMsgPost()->save($obj);

        //最多三条
        if (count($msg_data['posts']) < 4) {
            $msg_data['posts'][] = array('uid' => $uid, 'username' => $this->hidePhoneNumber($user_data->username), 'message' => $data, 'dateline' => time());
        }

        $social_circle_msg->update(array('_id' => new \MongoId($mid), 'status' => 1), array('$inc' => array('replay_number' => 1), '$set' => array('posts' => $msg_data['posts'])));

        if ($status) {
            //return $this->jsonResponse(array('status' => 1, 'message' => '回复成功', 'pid' => (string)$obj['_id']));
            return $this->jsonResponse(array('status' => 1, 'message' => '回复成功'));

        } else {
            return $this->jsonResponseError('回复失败');
        }


    }


}