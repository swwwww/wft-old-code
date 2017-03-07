<?php
/**
 * index 搜索
 */

namespace ApiSocial\Controller;

use Deyi\BaseController;

use Deyi\GetCacheData\CouponCache;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Response;
use Deyi\JsonResponse;

class IndexController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    /**
     * @return 玩伴圈动态页面
     */
    public function indexAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = (int)$this->getParams('uid'); //可选
        $mid = $this->getParams('mid', 0);//消息id 为0时获取最新
        $limit = (int)$this->getParams('pagenum', 10);
        $city = $this->getCity();

        $social_circle = $this->_getMdbSocialCircle();
        $social_circle_users = $this->_getMdbsocialCircleUsers();

        if ($mid and !$this->checkMid($mid)) {
            return $this->jsonResponseError('数据不存在!');
        }

        /*********** 将用户加入指定圈子 ***************/
        $this->autoAddMember($uid);
        /*********************  临时 随机取热门   ************************/

        $res = $social_circle->find(array('status' => 1, 'city' => $city))->sort(array('today_msg_post' => -1))->limit(10);

        $hot_data = array();

        $uids = array();
        if ($uid) {
            $joinCircleData = $this->_getMdbSocialCircleUsers()->find(array('uid' => $uid));
            foreach ($joinCircleData as $join) {
                $uids[] = $join['cid'];
            }
        }

        foreach ($res as $v) {
            if (!in_array($v['_id'], $uids)) {
                $hot_data[] = $v;
            }

        }

        $circle = array();

        if (count($hot_data)) {
            $res = $hot_data[rand(0, count($hot_data) - 1)];
            $circle = array(
                'cid' => (string)$res['_id'],
                'img' => $this->getImgUrl($res['thumb']),
                'c_name' => $res['title'],
                'c_member' => $res['people'],
                'message_num' => $res['msg_post'],
                'new_post' => $res['today_msg_post'],
            );
        }

        $mdb = $this->_getMongoDB();
        if ($uid && count($hot_data)) {
            $tmp_hot_circle = $mdb->tmp_hot_circle;
            $s = $tmp_hot_circle->findOne(array('uid' => $uid, 'cid' => (string)$res['_id']));
            if (!$s) {
                $tmp_hot_circle->insert(array(
                    'uid' => $uid,
                    'cid' => (string)$res['_id']
                ));
            }
        }


        /*********************  end   ************************/
        $social_circle_msg = $this->_getMdbsocialCircleMsg();
        $social_prise = $this->_getMdbsocialPrise();

        $where = array('status' => array('$gt' => 0, '$lt' => 3), 'city' => array('$all' => array($city)));
        if ($mid) {
            $where['_id'] = array('$lt' => new \MongoId($mid));
        }

        $data = array();
        // 全局置顶 3条  status = 3
        if ($mid == 0) {
            $upData = $social_circle_msg->find(array('status' => 3, 'city' => array('$all' => array($city))))->sort(array('_id' => -1))->limit(3);
            $upCount = $social_circle_msg->find(array('status' => 3, 'city' => array('$all' => array($city))))->count();
            $limit = ($upCount > 3) ? ($limit - 3) : $limit - $upCount;

            foreach ($upData as $up) {
                if ($uid) {
                    $like_status = $social_prise->findOne(array('uid' => $uid, 'object_id' => (string)$up['_id'])) ? 1 : 0;
                } else {
                    $like_status = 0;
                }
                $data[] = array(
                    'mid' => (string)$up['_id'],
                    'uid' => $up['uid'],
                    'img' => $this->getImgUrl($up['img']),
                    'time' => $up['dateline'],
                    'username' => $this->hidePhoneNumber($up['username']),
                    'user_detail' => $up['child'],
                    'cid' => $up['cid'] ? $up['cid'] : '',
                    'c_name' => $up['c_name'],
                    'title' => $this->hidePhoneNumber($up['title']),
                    'content' => $up['msg'] ? $up['msg'] : [],
                    'answer_num' => $up['replay_number'],  //评论数
                    'is_like' => $like_status,
                    'like_num' => $up['like_number'],
                    'object_data' => $up['object_data'],
                    'msg_type' => $up['msg_type'],
                    'answer' => $up['posts']
                );
            }
        }


        if ($uid) {
            //获取用户关注的圈子 和 用户好友圈子消息
            $cids = array();
            $res = $social_circle_users->find(array('uid' => $uid), array('cid'));
            foreach ($res as $v) {//圈子列表
                $cids[] = (string)$v['cid'];
            }

            $res2 = $this->_getMdbSocialFriends()->find(array('uid' => $uid));

            $uids = array($uid);
            foreach ($res2 as $v) {
                $uids[] = (int)$v['like_uid'];
            }

            $where['$or'] = array(array('cid' => array('$in' => $cids)), array('uid' => array('$in' => $uids)));

            // $where['city'] = array('$all'=>array($city));

            $result = $social_circle_msg->find($where)->sort(array('_id' => -1))->limit($limit);
        } else {
            //gt >   lt <
            $result = $social_circle_msg->find($where)->sort(array('_id' => -1))->limit($limit);

        }

        foreach ($result as $v) {
            if ($uid) {
                $like_status = $social_prise->findOne(array('uid' => $uid, 'object_id' => (string)$v['_id'])) ? 1 : 0;
            } else {
                $like_status = 0;
            }

            if (isset($v['object_data']['object_id'])) {
                $v['object_data']['star'] = CouponCache::getStar($v['object_data']['object_id'], $v['msg_type'] == 2 ? 1 : 3);
            }

//            if (isset($data['object_data']['order_method'])) {  //解决android bug
            $v['object_data']['order_method']= '';
//            }
            $data[] = array(
                'mid' => (string)$v['_id'],
                'uid' => $v['uid'],
                'img' => $this->getImgUrl($v['img']),
                'time' => $v['dateline'],
                'username' => (string)$this->hidePhoneNumber($v['username']),
                'user_detail' => $v['child'],
                'cid' => $v['cid'] ? $v['cid'] : '',
                'c_name' => $v['c_name'],
                'title' => $this->hidePhoneNumber($v['title']),
                'content' => $v['msg'] ? $v['msg'] : [],
                'answer_num' => $v['replay_number'],  //评论数
                'is_like' => $like_status,
                'like_num' => $v['like_number'],
                'object_data' => $v['object_data'],
                'msg_type' => $v['msg_type'],
                'answer' => $v['posts']
            );




        }

        if (count($circle)) {
            $return = array(
                'circle' => $circle,
                'message' => $data
            );
        } else {
            $return = array(
                'message' => $data
            );
        }
        return $this->jsonResponse($return);
    }

    private function autoAddMember($uid)
    {

//        $this->circleAddMember($uid, '561778aa7f8b9a3b468b4567');//约玩伴
//        $this->circleAddMember($uid, '561778897f8b9a76478b4568');//跳蚤街
//        $this->circleAddMember($uid, '561778557f8b9a07478b4568');//马上晒
//        $this->circleAddMember($uid, '560ba46c7f8b9a52768b4567');//玩攻略


        $where['type'] = 2;
        $where['city'] = $this->getCity();
        $where['pid'] = array('$in' => array('56cacb4ddd93f763c40be3aa', '56cacd12dd93f763c40be3ab', '56cacdf0dd93f763c40be3ac', '56cace58dd93f763c40be3ad'));

        $socialData = $this->_getMdbSocialCircle()->find($where);
        foreach ($socialData as $social) {
            $this->circleAddMember($uid, (string)$social['_id']);
        }
    }

    //临时添加成员
    private function circleAddMember($uid, $cid)
    {

        $social_circle = $this->_getMdbSocialCircle();
        $social_circle_users = $this->_getMdbsocialCircleUsers();

        $circle_data = $social_circle->findOne(array('_id' => new \MongoId($cid)));


        $uids = array($uid);

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
    }


}