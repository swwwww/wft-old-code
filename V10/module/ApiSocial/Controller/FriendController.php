<?php
namespace ApiSocial\Controller;

use Deyi\BaseController;
use Deyi\GetCacheData\CouponCache;
use Deyi\GetCacheData\NoticeCache;
use Deyi\GeTui\GeTui;
use Zend\Code\Scanner\TokenArrayScanner;
use Zend\Crypt\PublicKey\Rsa\PublicKey;
use Zend\Db\Sql\Ddl\Column\Date;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Predicate\In;
use Zend\I18n\Validator\Int;
use Zend\I18n\View\Helper\DateFormat;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Response;
use Deyi\JsonResponse;

class FriendController extends AbstractActionController
{
    use BaseController;
    use JsonResponse;

    /**
     * @return 我的玩伴
     */
    public function indexAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }
        $uid = (int)$this->getParams('to_uid');
        $status = (int)$this->getParams('status');
        $page = (int)$this->getParams('page', 1);//页数
        $pagenum = (int)$this->getParams('pagenum', 10);
        $start = ($page - 1) * $pagenum;

        if (!$uid) {
            return $this->jsonResponseError('请先登录!');
        }

        if ($status != 0 && $status != 1 && $status != 2 && $status != 3) {
            return $this->jsonResponseError('信息出错！请重试');
        }

        $user = $this->_getMdbsocialFriends();

        //$user->remove(array('uid'=>$uid));

        $username = $this->_getPlayUserTable()->get(array('uid' => $uid))->username;
        $friends_num = $user->find(array('uid' => $uid, 'friends' => 1))->count();
        $concern_num = $user->find(array('uid' => $uid))->count();
        $concerned_num = $user->find(array('like_uid' => $uid))->count();
        $res = array();
        $sta = array();
        $uids = array();
        $result2 = array();
        if ($status == 1) {
            //相互关注
            $res = $user->find(array('uid' => $uid, 'friends' => 1), array('like_uid'))->limit($pagenum)->skip($start);
        } else if ($status == 2) {
            //我关注的
            $res = $user->find(array('uid' => $uid), array('like_uid'))->limit($pagenum)->skip($start);
        } else if ($status == 3) {
            //关注我的 粉丝
            $res = $user->find(array('like_uid' => $uid), array('uid'))->limit($pagenum)->skip($start);
        } else if ($status == 0) {
            //所有玩伴
            $res1 = $user->find(array('$or' => array(array('uid' => $uid, 'friends' => 1), array('uid' => $uid, 'friends' => 0), array('like_uid' => $uid, 'friends' => 0))))->limit($pagenum)->skip($start);
            $data = iterator_to_array($res1);

            foreach ($data as $v) {
                if ($v['uid'] == $uid) {
                    if ($v['friends'] == 1) {
                        $sta[] = array('uid' => (string)$v['like_uid'], 'concern' => 1,);
                        $uids[] = (string)$v['like_uid'];
                    } else if ($v['friends'] == 0) {
                        $sta[] = array('uid' => (string)$v['like_uid'], 'concern' => 2,);
                        $uids[] = (string)$v['like_uid'];
                    }
                } else if ($v['like_uid'] = $uid) {
                    if ($v['friends'] == 0) {
                        $sta[] = array('uid' => (string)$v['uid'], 'concern' => 3,);
                        $uids[] = (string)$v['uid'];
                    }
                }
            }
        }
        foreach ($res as $v) {
            $uids[] = $status == 3 ? (string)$v['uid'] : (string)$v['like_uid'];
        }
        $where = array(
            'status' => 1,
            $uids ? new In('uid', $uids) : 0,
        );
        $result = $this->_getPlayUserTable()->fetchLimit(0, 10, array(), $where, array(), array())->toArray();
        if ($status == 0) {
            $result2 = $this->mergeArray($result, $sta, 'uid');
        }

        $social_chat_msg = $this->_getMdbsocialChatMsg();

        $data = array();
        $count = 0;
        foreach ($status == 0 ? $result2 : $result as $a) {
            $count++;
            $data[] = array(
                'uid' => (Int)$a['uid'],
                'img' => $this->getImgUrl($a['img']),
                'username' => $this->hidePhoneNumber($a['username']),
                'user_detail' => $a['user_alias'],
                'user_circles' => $a['user_circles'] ? json_decode($a['user_circles'], true) : array(),
                'unread' => $social_chat_msg->find(array('from_uid' => (Int)$a['uid'], 'to_uid' => $uid, 'new' => 0))->count(),
                'concern' => $status == 0 ? $a['concern'] : $status,
            );
            if ($count >= 10) {
                break;
            }
        }
        return $this->jsonResponse(array(
            'username' => $this->hidePhoneNumber($username),
            'friends_num' => $friends_num,
            'concern_num' => $concern_num,
            'concerned_num' => $concerned_num,
            'friends' => $data,
        ));
    }

    /**
     * @return 聊天-玩伴消息
     */
    public function chatAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }
        $from_uid = (int)$this->getParams('uid');
        $to_uid = (int)$this->getParams('to_uid');
        $id = (int)$this->getParams('id', 0); //  3  4  5   3    2  1
        $pagenum = (int)$this->getParams('pagenum', 10);
        if (!$from_uid || !$to_uid) {
            return $this->jsonResponseError('操作出错！请先登录');
        }

        $username = $this->_getPlayUserTable()->get(array('uid' => $to_uid))->username;


        $user = $this->_getMdbsocialChatMsg();
        $res = array();
        if (!$id) {
            $result1 = $user->find(array('$or' => array(array('from_uid' => $from_uid, 'to_uid' => $to_uid, 'status' => 1), array('from_uid' => $to_uid, 'to_uid' => $from_uid, 'status' => 1))))->sort(array('id' => -1))->limit($pagenum);
        } else {
            $result1 = $user->find(array('id' => array('$lt' => $id), '$or' => array(array('from_uid' => $from_uid, 'to_uid' => $to_uid, 'status' => 1), array('from_uid' => $to_uid, 'to_uid' => $from_uid, 'status' => 1))))->sort(array('id' => -1))->limit($pagenum);
        }
        if (!$result1) {
            return $this->jsonResponseError('数据出错！');
        }
        $result2 = $user->update(array('$or' => array(array('from_uid' => $from_uid, 'to_uid' => $to_uid, 'status' => 1, 'new' => 0), array('from_uid' => $to_uid, 'to_uid' => $from_uid, 'status' => 1, 'new' => 0))), array('$set' => array('new' => 1)), array('multiple' => true));
        if (!$result2) {
            return $this->jsonResponseError('数据出错！');
        }

        $from_img = $this->_getPlayUserTable()->get(array('uid' => $from_uid))->img;
        $to_img = $this->_getPlayUserTable()->get(array('uid' => $to_uid))->img;

        foreach ($result1 as $data) {
            $res[] = array(
                'id' => $data['id'],
                'uid' => $from_uid == $data['from_uid'] ? $from_uid : $to_uid,
                'img' => $from_uid == $data['from_uid'] ? $this->getImgUrl($from_img) : $this->getImgUrl($to_img),
                'time' => $data['dateline'],
                'info' => $data['msg'],
                'new' => $data['new'],
                'msg_type' => isset($data['msg_type']) ? $data['msg_type'] : 0,
                'object_data' => isset($data['object_data']) ? $data['object_data'] : array(),
            );
        }

        $sort = array(
            'direction' => 'SORT_ASC', //排序顺序标志 SORT_DESC 降序；SORT_ASC 升序
            'field' => 'time',       //排序字段
        );
        $arrSort = array();
        foreach ($res AS $uniqid => $row) {
            foreach ($row AS $key => $value) {
                $arrSort[$key][$uniqid] = $value;
            }
        }
        if ($sort['direction']) {
            array_multisort($arrSort[$sort['field']], constant($sort['direction']), $res);
        }
        return $this->jsonResponse(array(
                'username' => $this->hidePhoneNumber($username),
                'chat' => $res,
            )
        );
    }

    /**
     * @return 发送聊天内容
     */
    public function sendChatAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }
        $from_uid = (int)$this->getParams('uid');
        $to_uid = (int)$this->getParams('to_uid');
        $content = $this->getParams('content');
        if (!$from_uid) {
            return $this->jsonResponseError('请先登录!');
        }
        if (!$to_uid) {
            return $this->jsonResponseError('该用户不存在!');
        }
        if (!$content) {
            return $this->jsonResponseError('信息不能为空!');
        }
        $content_arr = json_decode(htmlspecialchars_decode($content, ENT_QUOTES), true);

        //消息显示
        $view = ' ';
        foreach($content_arr as $v) {
            if ((int)$v['t'] === 1 && $v['val']) {
                $view = substr($v['val'], 0, 24);
                break;
            }
        }

        if (!$content_arr) {
            return $this->jsonResponseError('信息不能为空!');
        }

        $mdb = $this->_getMongoDB();
        $user = $this->_getMdbsocialChatMsg();
        $user2 = $mdb->ids;


        $id = $user2->findAndModify(array('table' => 'social_chat_msg'), array('$inc' => array('id' => 1)), null, array('new' => true, 'upsert' => true))['id'];
        $status = $user->insert(array('id' => $id, 'from_uid' => $from_uid, 'to_uid' => $to_uid, 'msg' => $content_arr, 'dateline' => time(), 'status' => 1, 'new' => 0));


        if (!$status) {
            return $this->jsonResponseError('发送消息失败!');
        } else {
            $user_data = $this->_getPlayUserTable()->get(array('uid' => $from_uid));

            $to_uid_data = $this->_getPlayUserTable()->get(array('uid' => $to_uid));


            $img = $this->getImgUrl($user_data->img);
            $send_data = array(
                'title' => '',
                'info' => array('id' => (string)$id, 'uid' => (string)$from_uid, 'username' => $this->hidePhoneNumber($user_data->username), 'img' => $this->getImgUrl($img), 'info' => $content_arr),
                'type' => 7,
                'id' => '0',
                'time' => time(),
            );


            $geTui = new GeTui();

            $str = substr($to_uid_data->token, 0, 10);
            $s = $geTui->Push($to_uid . '__' . $str, $view, json_encode($send_data));
            $tui = $s['result'];

            /*if ($s['result'] != 'ok') {
                return $this->jsonResponse(array('status' => 0, 'id' => $id, 'message' => $tui . $to_uid . '__' . $str));
            }*/
            return $this->jsonResponse(array('status' => 1, 'id' => $id, 'message' => '发送成功' . $tui));
        }
    }

    /**
     * @return 用户详情
     */
    public function detailFriendAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }
        $to_uid = (int)$this->getParams('to_uid');
        $my_uid = (int)$this->getParams('uid');
        if (!$to_uid) {
            return $this->jsonResponseError('该用户不存在!');
        }
        $like_status = 0;

        $user = $this->_getMdbsocialFriends();
        $res = $this->_getPlayUserTable()->get(array('uid' => $to_uid));

        if ($my_uid) {
            $my_like = $user->findOne(array('uid' => $my_uid, 'like_uid' => $to_uid));
            if ($my_like) {
                if ($my_like['friends'] == 1) {
                    $like_status = 3;  //互相关注
                } else {
                    $like_status = 2;//已关注
                }

            } else {
                $like_me = $user->findOne(array('uid' => $to_uid, 'like_uid' => $my_uid));
                if ($like_me) {
                    $like_status = 1;  //粉丝
                } else {
                    $like_status = 0;// 未关注
                }

            }
        }

        $baby_list = array();

        $babyData = $this->_getPlayUserBabyTable()->fetchAll(array('uid' => $to_uid));
        if ($babyData->count()) {
            foreach ($babyData as $baby) {
                $baby_list[] = array(
                    'name' => $baby->baby_name,
                    'sex' => $baby->baby_sex,
                    'img' => $this->getImgUrl($baby->img),
                    'birth' => $baby->baby_birth,
                    'id' => $baby->id,
                );
            }
        }

        return $this->jsonResponse(array(
                'uid' => $to_uid,
                'img' => $this->getImgUrl($res->img),
                'username' => $this->hidePhoneNumber($res->username),
                'user_detail' => $res->user_alias,
                'signature' => $res->sign,
                'like_status' => $like_status,
                'post_num' => (int)$res->circle_msg,
                'c_num' => (int)$res->join_circle,
                'concern_num' => (int)$res->like_user,
                'concerned_num' => (int)$res->like_me,
                'sex' => (int)$res->child_sex,
                'baby_list' => $baby_list,
            )
        );

        // 粉丝，已关注 ，互相关注
    }

    /**
     * @return 添加好友
     */
    public function addFriendAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }
        $uid = (int)$this->getParams('uid');
        $like_uid = (int)$this->getParams('like_uid');
        if (!$uid || !$like_uid) {
            return $this->jsonResponseError('操作失败！');
        }

        if ($like_uid == $uid) {
            return $this->jsonResponseError('添加失败！不能关注自己');
        }

        $like_data = $this->_getPlayUserTable()->get(array('uid' => $like_uid));

        if (!$like_data) {
            return $this->jsonResponseError("like用户不存在");
        }

        $user = $this->_getMdbsocialFriends();
        $sta = $user->findOne(array('uid' => $uid, 'like_uid' => $like_uid));

        if (!$sta) {
            //已被对方关注的情况
            $sta1 = $user->findOne(array('uid' => $like_uid, 'like_uid' => $uid, 'friends' => 0));

            if (!$sta1) {
                $status = $user->insert(array('uid' => $uid, 'like_uid' => $like_uid, 'friends' => 0, 'dateline' => time()));
                $status1 = $this->updateUser($uid, $like_uid, 0);
                if ($status && $status1) {
                    return $this->jsonResponse(array('status' => 1, 'message' => '添加成功！你已经关注了对方'));
                } else {
                    return $this->jsonResponseError('添加失败！');
                }
            } else {
                $status = $user->insert(array('uid' => $uid, 'like_uid' => $like_uid, 'friends' => 1, 'dateline' => time()));
                $status2 = $user->update(array('uid' => $like_uid, 'like_uid' => $uid), array('$set' => array('friends' => 1)));
                $status1 = $this->updateUser($uid, $like_uid, 0);
                if ($status && $status1 && $status2) {
                    return $this->jsonResponse(array('status' => 1, 'message' => '添加成功！你已经成为了对方的好友'));
                } else {
                    return $this->jsonResponseError('添加失败！');
                }
            }
        } else {
            return $this->jsonResponseError('添加失败！对方已是你的好友或者你已关注对方');
        }

    }

    /**
     * @return 删除好友
     */
    public function deleteFriendAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }
        $uid = (int)$this->getParams('uid');
        $like_uid = (int)$this->getParams('_uid');
        if (!$uid || !$like_uid) {
            return $this->jsonResponseError('操作失败！');
        }


        $user = $this->_getMdbsocialFriends();

        $sta = $user->findOne(array('uid' => $uid, 'like_uid' => $like_uid));
        if (!$sta) {
            return $this->jsonResponseError('删除失败！你还未关注对方');
        }
        $sta1 = $user->findOne(array('uid' => $uid, 'like_uid' => $like_uid, 'friends' => 1));
        if ($sta1) {
            $status = $user->remove(array('uid' => $uid, 'like_uid' => $like_uid));
            $status2 = $user->update(array('uid' => $like_uid, 'like_uid' => $uid), array('$set' => array('friends' => 0)));
            $status1 = $this->updateUser($uid, $like_uid, 1);
            if ($status && $status1 && $status2) {
                return $this->jsonResponse(array('status' => 1, 'message' => '删除成功！你已取消关注对方'));
            } else {
                return $this->jsonResponseError('删除失败！');
            }
        } else {
            $status = $user->remove(array('uid' => $uid, 'like_uid' => $like_uid));
            $status1 = $this->updateUser($uid, $like_uid, 1);
            if ($status && $status1) {
                return $this->jsonResponse(array('status' => 1, 'message' => '删除成功！你已取消关注对方'));
            } else {
                return $this->jsonResponseError('删除失败！');
            }
        }


    }

    /**
     * @return 发言列表
     */
    public function msglistAction()
    {

        if (!$this->pass()) {
            return $this->failRequest();
        }


        $uid = (int)$this->getParams('uid');
        $to_uid = (int)$this->getParams('to_uid');
        $type = (int)$this->getParams('type', 1);  //1点评(商品,游玩地) 2圈子发言
        $page = (int)$this->getParams('page', 1);
        $pagenum = (int)$this->getParams('pagenum', 10);

        if (!$to_uid) {
            return $this->jsonResponseError('用户不存在');
        }

        NoticeCache::delNewReward($to_uid); //清空新消息通知

        $social_circle_msg = $this->_getMdbsocialCircleMsg();
        $social_prise = $this->_getMdbsocialPrise();

        $offset = ($page - 1) * $pagenum;

        $where = array('uid' => $to_uid, 'status' => array('$gt' => 0));

        if ($type == 1) {
            $where['$or'] = array(array('msg_type' => 2), array('msg_type' => 3));
        } elseif($type==2){
            $where['msg_type'] = 1;
        }
        $result = $social_circle_msg->find($where)->sort(array('_id' => -1))->skip($offset)->limit($pagenum);


        //被动统计发言 临时
        $num = $this->_getMdbSocialCircleMsg()->find(array('status' => array('$gt' => 0), 'uid' => (int)$to_uid))->count();
        $this->_getPlayUserTable()->update(array('circle_msg' => $num), array('uid' => $to_uid));


        $data = array();
        foreach ($result as $v) {

            $like_status = 0;

            if ($uid) {
                $like_status = $social_prise->findOne(array('uid' => $uid, 'object_id' => (string)$v['_id'])) ? 1 : 0;
            } else {
                $like_status = 0;
            }
            if (isset($v['object_data']['object_id'])) {
                $v['object_data']['star'] = CouponCache::getStar($v['object_data']['object_id'], $v['msg_type'] == 2 ? 1 : 3);
            }

            $data[] = array(
                'mid' => (string)$v['_id'],
                'uid' => $v['uid'],
                'img' => $this->getImgUrl($v['img']),
                'time' => $v['dateline'],
                'username' => $this->hidePhoneNumber($v['username']),
                'user_detail' => $v['child'],
                'cid' => $v['cid'] ? $v['cid'] : '', //普通评论时 此id为''
                'c_name' => $v['c_name'],
                'title' => $this->hidePhoneNumber($v['title']),
                'content' => $v['msg'],
                //'img_list' => $v['img_list'],
                'answer_num' => $v['replay_number'],
                'is_like' => $like_status,
                'accept' => $v['accept'] == 2 ? 1 : 0,// 小编采纳
                'like_num' => $v['like_number'],
                'object_data' => $v['object_data'],
                'msg_type' => $v['msg_type'],
                'answer' => $v['posts']
            );
        }
        return $this->jsonResponse($data);
    }

    private function updateUser($uid, $like_uid, $status)
    {
        $sta1 = 0;
        $sta2 = 1;
        switch ($status) {
            case 0://添加关注
                $sta1 = $this->_getPlayUserTable()->update(array('like_user' => new Expression('like_user+1')), array('uid' => $uid));
                $sta2 = $this->_getPlayUserTable()->update(array('like_me' => new Expression('like_me+1')), array('uid' => $like_uid));
                break;
            case 1://取消关注
                $sta1 = $this->_getPlayUserTable()->update(array('like_user' => new Expression('like_user-1')), array('uid' => $uid));
                $sta2 = $this->_getPlayUserTable()->update(array('like_me' => new Expression('like_me-1')), array('uid' => $like_uid));
                break;
        }
        if ($sta1 && $sta2) {
            return 1;
        } else {
            return 0;
        }
    }

}
