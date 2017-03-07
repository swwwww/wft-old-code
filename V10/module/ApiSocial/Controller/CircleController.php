<?php
namespace ApiSocial\Controller;

use Deyi\Account\Account;
use Deyi\BaseController;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Predicate\In;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Response;
use Deyi\JsonResponse;

class CircleController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;


    /**
     * @return 我的圈子
     */
    public function indexAction()
    {


        if (!$this->pass()) {
            return $this->failRequest();
        }

        $myuid = (int)$this->getParams('uid');// 用户id 可选

        $uid = (int)$this->getParams('to_uid');
        $page = (int)$this->getParams('page', 1);
        $pagenum = (int)$this->getParams('pagenum', 10);
        $offset = ($page - 1) * $pagenum;
        $city = $this->getCity();


        if(!$uid and !$myuid){
            return $this->jsonResponseError('请先登录!');
        }
        /*else{
            //兼容ios错误
            if (!$uid) {
                $uid=$myuid;
            }
        }*/


        $social_circle_users = $this->_getMdbsocialCircleUsers();

        //主动统计圈子 临时
        $num = $social_circle_users->find(array('uid' => $uid), array('cid'))->count();
        $this->_getPlayUserTable()->update(array('join_circle' => $num), array('uid' => $uid));

        $res = $social_circle_users->find(array('uid' => $uid), array('cid'))->skip($offset)->limit($pagenum);

        $cids = array();
        foreach ($res as $v) {
            $cids[] = new \MongoId($v['cid']);
        }

        $social_circle = $this->_getMdbSocialCircle();

        $res = $social_circle->find(array(
            '_id' => array('$in' => $cids),
            'city' => $city,
            'status' => 1
        ));



        $data = array();
        foreach ($res as $v) {

            $is_join = 0;
            if ($myuid) {
                $res = $social_circle_users->findOne(array('uid' => $myuid, 'cid' => (string)$v['_id']));
                if ($res) {
                    $is_join = 1;
                }
            }

            $data[] = array(
                'cid' => (string)$v['_id'],
                'img' => $this->getImgUrl($v['thumb']),
                'c_name' => $v['title'],
                'c_member' => $v['people'],
                'message_num' => $v['msg'] + $v['msg_post'],
                'new_post' => $v['today_msg'] + $v['today_msg_post'],
                'is_join' => $is_join
            );
        }

        return $this->jsonResponse($data);

    }

    /**
     * @return 所有圈子
     */
    public function allCircleAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = (int)$this->getParams('uid');  //可选
        $page = (int)$this->getParams('page', 1);
        $pagenum = (int)$this->getParams('pagenum', 10);
        $offset = ($page - 1) * $page;
        $city = $this->getCity();
        $is_join = 0;

        $social_circle = $this->_getMdbSocialCircle();
        $social_circle_users = $this->_getMdbsocialCircleUsers();
        $res = $social_circle->find(array('status' => 1,'city'=>$city))->skip($offset)->limit($pagenum);

        $data = array();
        $cids = array();
        foreach ($res as $v) {
            $cids[] = (string)$v['_id'];
            $data[] = array(
                'cid' => (string)$v['_id'],
                'img' => $this->getImgUrl($v['thumb']),
                'c_name' => $v['title'],
                'c_member' => $v['people'],
                'message_num' => $v['today_msg'] + $v['today_msg_post'],
                'is_join' => $is_join
            );
        }

        if ($uid) {

            $s_u = $social_circle_users->find(array('uid' => $uid, 'cid' => array('$in' => $cids)), array('uid', 'cid'));
            $data = $this->mergeArray(iterator_to_array($s_u), $data, 'cid');
            foreach ($data as $k => $v) {
                if (isset($v['uid'])) {
                    $data[$k]['is_join'] = 1;
                }
                unset($data[$k]['_id'], $data[$k]['uid']);
            }
        }
        return $this->jsonResponse($data);
    }

    /**
     * @return 圈子详情
     */
    function detailAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = (int)$this->getParams('uid');
        $cid = $this->getParams('cid');
        $mid = $this->getParams('last_mid', 0);  //最后一条数据id
        $pagenum = (int)$this->getParams('pagenum', 10);
        $city = $this->getCity();
        if (!$cid) {
            return $this->jsonResponseError('操作失败!圈子不存在');
        }

        if($mid && !$this->checkMid($mid)){
            return $this->jsonResponseError('数据不存在!');
        }

        if(!$this->checkMid($cid)){
            return $this->jsonResponseError('数据不存在!');
        }

        $is_join = 0;

        $social_circle = $this->_getMdbSocialCircle();
        $result = $social_circle->findOne(array('_id' => new \MongoId($cid)));

        if (!$result) {
            return $this->jsonResponseError('数据不存在');
        }

        //浏览数加1
        $social_circle->update(array('_id' => new \MongoId($cid)), array('$inc' => array('view_number' => 1)));

        $res = array();
        $social_circle_msg = $this->_getMdbsocialCircleMsg();

        //$where = array('cid' => $cid , 'status' => array('$gt' => 0));

        $where = array('$or' => array(array("cid" => $cid), array("cid" => (string)$result['pid'])),'city'=>array('$all'=>[$city]),'status' => array('$gt' => 0));
        if ($mid) {
            $where['_id'] = array('$lt' => new \MongoId($mid));
        }

        $result1 = $social_circle_msg->find($where)->sort(array('status' => -1, '_id' => -1))->limit($pagenum);
        foreach ($result1 as $data) {
            if ($uid) {
                $like_status = $this->_getMdbsocialPrise()->findOne(array('uid' => $uid, 'object_id' => (string)$data['_id'])) ? 1 : 0;
            } else {
                $like_status = 0;
            }

            $res[] = array(
                'uid' => $data['uid'],
                'mid' => (string)$data['_id'],
                'img' => $this->getImgUrl($data['img']),
                'time' => $data['dateline'],
                'username' => $this->hidePhoneNumber($data['username']),
                'user_detail' => $data['child'],
                'title' => $data['title'],
                'content' => $data['msg'],
                'img_list' => isset($data['img_list'])?$data['img_list']:[],
                'answer_num' => $data['replay_number'],
                'like_num' => $data['like_number'],
                'msg_type' => $data['msg_type'],
                'object_data' => $data['object_data'],
                'answer' => $data['posts'],
                'is_like' => $like_status,
            );
        }

        if ($uid) {
            //是否已加入
            $social_circle_users = $this->_getMdbsocialCircleUsers();
            $u_s = $social_circle_users->findOne(array('uid' => $uid, 'cid' => $cid));
            if ($u_s) {
                $is_join = 1;
            }
        }

        return $this->jsonResponse(array(
            'circle' => array(
                'cid' => $cid,
                'img' => $this->getImgUrl($result['img']),
                'c_name' => $result['title'],
                'c_member' => $result['people'],
                'message_num' => $result['msg'] + $result['msg_post'],
                'is_join' => $is_join
            ),
            'message' => $res,
        ));
    }

    /**
     * @return 添加新成员 //用户好友
     */
    function addMemberAction()
    {

        if (!$this->pass()) {
            return $this->failRequest();
        }
        $uid = (int)$this->getParams('uid');
        $cid = $this->getParams('cid');
        $last_uid = (int)$this->getParams('last_uid', 0);//每页的最后一个uid
        $pagenum = (int)$this->getParams('pagenum', 10);

        if (!$uid) {
            return $this->jsonResponseError('请先登录!');
        }
        if (!$cid) {
            return $this->jsonResponseError('操作失败!圈子不存在');
        }

        $social_circle_users = $this->_getMdbsocialCircleUsers();
        $social_friends = $this->_getMdbsocialFriends();


        //获取好友列表和好友关系 1相互关注 2我关注的 3关注我的
        $user_friend_data = $social_friends->find(array('uid' => $uid, 'friends' => 1))->sort(array('uid' => 1))->limit(1000);


        $user_friends = array();
        foreach ($user_friend_data as $v) {
            $user_friends[] = $v;
        }


        $data = array();
        $uids = array();
        $i = 0;

        foreach ($user_friends as $k => $v) {
            if ($v['friends'] == 1) {
                $user_friends[$k]['status'] = 1;
            } elseif ($v['uid'] == $uid) {
                $user_friends[$k]['status'] = 2;
            } else {
                $user_friends[$k]['status'] = 3;
            }

            $like_uid = $v['uid'];
            if ($v['like_uid'] != $uid) {
                $like_uid = $v['like_uid'];
            }

            //从last_uid开始 过滤已经加入圈子的用户  取出指定数量
            if (!$uid or $v['uid'] > $last_uid) {
                if (!$social_circle_users->findOne(array('uid' => $like_uid, 'cid' => $cid))) {
                    $user_friends[$k]['uid'] = $like_uid;
                    $i++;
                    $uids[] = $like_uid;
                    $data[] = $user_friends[$k];
                    if ($i == 10) {
                        break;
                    }
                }
            }
        }
        $where = array(
            count($uids) ? new In('uid', $uids) : 0,
        );

        $result = $this->_getPlayUserTable()->fetchLimit(0, $pagenum, array(), $where, array(), array())->toArray();

        $merge_data = $this->mergeArray($result, $data, 'uid');

        $data = array();
        foreach ($merge_data as $v) {
            $data[] = array(
                'uid' => (int)$v['uid'],
                'img' => $this->getImgUrl($v['img']),
                'username' => $this->hidePhoneNumber($v['username']),
                'user_detail' => $v['user_alias'],
                'concen' => $v['status'],
                'user_circles' => $v['user_circles'] ? json_decode($v['user_circles'], true) : array()
            );
        }

        return $this->jsonResponse($data);
    }


    /**
     * @return 保存添加的成员
     */
    function saveMemberAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uids = $this->getParams('uids');  //json
        $cid = $this->getParams('cid');

        if (!$cid or !$uids) {
            return $this->jsonResponse(array('status' => 0, 'message' => '操作失败!参数错误：' . $uids));
        }


        $social_circle = $this->_getMdbSocialCircle();
        $social_circle_users = $this->_getMdbsocialCircleUsers();

        $circle_data = $social_circle->findOne(array('_id' => new \MongoId($cid)));


        $uids = json_decode(htmlspecialchars_decode($uids), true);

        if (!is_array($uids)) {
            return $this->jsonResponseError('参数错误，请传递uid数组');
        }
        $count = 0;
        foreach ($uids as $add_uid) {
            $add_uid = (int)$add_uid;
            $res = $social_circle_users->findOne(array('uid' => $add_uid, 'cid' => $cid));
            if ($res) {
                //return $this->jsonResponse(array('status' => 1, 'message' => '已添加'));
            } else {
                $user_circles = array();
                $res = $social_circle_users->find(array('uid' => $add_uid))->sort(array('dateline' => 1))->limit(2);
                $user_circles[] = array('cid' => $cid, 'c_name' => $circle_data['title']);
                foreach ($res as $v) {
                    $user_circles[] = array('cid' => $v['cid'], 'c_name' => $v['c_name']);
                }

                $user_data = $this->_getPlayUserTable()->get(array('uid' => $add_uid));
                $status = $social_circle_users->insert(array(
                    'uid' => $add_uid,
                    'username' => $this->hidePhoneNumber($user_data->username),
                    'user_detail' => $user_data->user_alias,
                    'img' => $this->getImgUrl($user_data->img),
                    'cid' => $cid,
                    'c_name' => $circle_data['title'],
                    'user_circles' => $user_circles,
                    'role' => 1,
                    'status' => 1,
                    'dateline' => time(),
                ));

                //user 表存入用户关注的前三个圈子
                $this->_getPlayUserTable()->update(array('user_circles' => json_encode($user_circles, JSON_UNESCAPED_UNICODE), 'join_circle' => new Expression('join_circle+1')), array('uid' => $add_uid));

                //人数加1
                $a = $social_circle->update(array('_id' => new \MongoId($cid)), array('$inc' => array('people' => 1)));

                //更新用户之前加入的圈子数据
                $social_circle_users->update(array('uid' => $add_uid), array('$set' => array('user_circles' => $user_circles)));


                if ($status) {
                    $count++;
                    //return $this->jsonResponse(array('status' => 1, 'message' => '添加成功'));
                } else {
                    //return $this->jsonResponseError('操作失败');
                }
            }
        }

        return $this->jsonResponse(array('status' => 1, 'message' => '添加成功', 'count' => $count));
    }

    /**
     * @return 删除成员
     */
    function deleteMemberAction()
    {

        if (!$this->pass()) {
            return $this->failRequest();
        }

        $add_uid = (int)$this->getParams('uid');
        $cid = $this->getParams('cid');

        if (!$cid or !$add_uid) {
            return $this->jsonResponseError('操作失败!参数错误');
        }


        $social_circle = $this->_getMdbSocialCircle();
        $social_circle_users = $this->_getMdbsocialCircleUsers();

        $res = $social_circle_users->findOne(array('uid' => $add_uid, 'cid' => $cid));


        if (!$res) {
            return $this->jsonResponse(array('status' => 1, 'message' => '已删除'));
        } else {

            $status = $social_circle_users->remove(array(
                'uid' => $add_uid,
                'cid' => $cid
            ));

            $user_circles = array();
            $res = $social_circle_users->find(array('uid' => $add_uid))->sort(array('dateline' => 1))->limit(4);

            foreach ($res as $v) {
                $user_circles[] = array('cid' => $v['cid'], 'c_name' => $v['c_name']);
            }


            //user 表存入用户关注的前四个圈子
            $this->_getPlayUserTable()->update(array('user_circles' => json_encode($user_circles, JSON_UNESCAPED_UNICODE), 'join_circle' => new Expression('join_circle-1')), array('uid' => $add_uid));
            //人数-1
            $social_circle->update(array('_id' => new \MongoId($cid)), array('$inc' => array('people' => -1)));
            //更新用户之前加入的圈子数据
            $social_circle_users->update(array('uid' => $add_uid), array('$set' => array('user_circles' => $user_circles)));


            if ($status) {
                return $this->jsonResponse(array('status' => 1, 'message' => '删除成功'));
            } else {
                return $this->jsonResponseError('操作失败');
            }
        }

    }

    /**
     * @return 所有成员
     */
    function allMemberAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }
        $cid = $this->getParams('cid');
        $page = $this->getParams('page', 1);
        $pagenum = $this->getParams('pagenum', 10);
        $uid = (int)$this->getParams('uid', 0);
        if (!$cid) {
            return $this->jsonResponseError('操作失败!圈子不存在');
        }


        $social_circle_users = $this->_getMdbsocialCircleUsers();
        $social_friends = $this->_getMdbsocialFriends();

        $offset = ($page - 1) * $pagenum;
        $res = $social_circle_users->find(array('cid' => $cid))->skip($offset)->limit($pagenum);

        $data = array(
            'user_num' => $res->count(),
            'user' => array()
        );

        //1相互关注 2我关注的 3关注我的
        foreach ($res as $v) {
            $status = 0;
            if ($uid) {


                $res1 = $social_friends->findOne(array('$or' => array(array('uid' => $uid, 'like_uid' => $v['uid']), array('like_uid' => $uid, 'uid' => $v['uid']))));

                if ($res1) {
                    if ($res1['friends'] == 1) {
                        $status = 1;
                    } elseif ($res1['uid'] == $uid) {
                        $status = 2;
                    } else {
                        $status = 3;
                    }
                }
            }
            $data['user'][] = array(
                'uid' => $v['uid'],
                'img' => $this->getImgUrl($v['img']),
                'username' => $this->hidePhoneNumber($v['username']),
                'user_detail' => $v['user_detail'],
                'user_circles' => $v['user_circles'],
                'status' => $status,
            );
        }
        return $this->jsonResponse($data);
    }
}