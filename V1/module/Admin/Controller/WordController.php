<?php

namespace Admin\Controller;

use Deyi\JsonResponse;
use Deyi\Paginator;
use Zend\Db\Sql\Predicate\Expression;
use Zend\View\Model\ViewModel;
use Deyi\ImageProcessing;

class WordController extends BasisController
{
    use JsonResponse;

    //评论列表
    public function indexAction()
    {
        $id = $this->getQuery('id');// 对象id
        $type = $this->getQuery('type', 0); //类型
        $like = $this->getQuery('k', ''); //key
        $order = $this->getQuery('order', 0); //状态
        $uid = $this->getQuery('uid', 0); //用户

        $where = array(
            'replypid' => 0,
        );
        if ($order) {
            $where['displayorder'] = $order;

        } else {
            $where['displayorder > ?'] = 0;
        }

        if ($id) {
            $where['object_id'] = $id;
        }

        if ($uid) {
            $where['uid'] = $uid;
        }

        if ($type) {
            $where['type'] = $type;
        }

        if ($like) {
            $where['subject like ?'] = '%'. $like. '%';
        }

        $page = (int)$this->getQuery('p', 1);
        $pagesum = 10;
        $start = ($page - 1) * $pagesum;
        //获得分页数据
        //fetchLimit($offset = 0, $row = 20, $columns = array(), $where = array(), $order = array(), $like = array())
        $data = $this->_getPlayPostTable()->fetchLimit($start, $pagesum, array(), $where, array('displayorder' => 'desc', 'dateline' => 'desc'));
        //获得总数量
        $count = $this->_getPlayPostTable()->fetchCount($where);
        //创建分页
        $url = '/wftadlogin/word';
        $paginator = new Paginator($page, $count, $pagesum, $url);

        $postCat = array('activity' => '专题', 'shop' => '游玩地', 'organizer' => '商家', 'news' => '资讯', 'coupon' => '旧商品', 'game' => '商品');
        return array(
            'data' => $data,
            'pagedata' => $paginator->getHtml(),
            'postCat' => $postCat,
        );
    }

    public function newAction() {
        $pid = (int)$this->getQuery('pid');
        $postData = $this->_getPlayPostTable()->get(array('pid' => $pid, 'displayorder > ?' => 0, 'replypid' => 0));

        if (!$postData) {
          return  $this->_Goto('该消息已经删除');
        }

        $page = $this->getQuery('p', 1);
        $pageSum = 10;
        $replyData = $this->_getPlayPostTable()->fetchLimit(($page-1)*$pageSum, $pageSum, array(), array('replypid' => $pid, 'displayorder > ?' => 0));
        $count = $this->_getPlayPostTable()->fetchCount(array('replypid' => $pid, 'displayorder > ?' => 0));
        //创建分页
        $url = '/wftadlogin/word/new';
        $paginator = new Paginator($page, $count, $pageSum, $url);

        return array(
            'data' => $postData,
            'post' => $replyData,
            'pageData' => $paginator->getHtml(),
        );
    }

    public function replyAction() {
        $pid = $this->getQuery('id');
        $postData = $this->_getPlayPostTable()->get(array('pid' => $pid, 'displayorder > ?' => 0, 'replypid' => 0));

        if (!$postData) {
            return  $this->_Goto('该消息已经删除');
        }

        return array(
            'data' => $postData,
        );
    }

    public function saveReplyAction() {

        $aid = (int)$_COOKIE['id'];
        $adminData = $this->_getPlayAdminTable()->get(array('id' => $aid)); //todo 编辑状态 修改
        if (!$adminData) {
            return $this->_Goto('该编辑账号 没有绑定uid', '/wftadlogin/setting/editor');
        }

        $uid_data = $this->_getPlayUserTable()->get(array('uid' => $adminData->bind_user_id));
        if (!$uid_data or $uid_data->status != 1) {
            return $this->_Goto('用户不存在或已禁用');
        }

        $author = $this->hidePhoneNumber($uid_data->username);
        $img = $this->getImgUrl($uid_data->img);
        $uid = (int)$adminData->bind_user_id;

        $pid = (int)$this->getPost('pid', 0);// 回复帖子id
        $postData = $this->_getPlayPostTable()->get(array('pid' => $pid));

        if (!$postData) {
            return $this->_Goto('非法操作');
        }

        $post_type = $postData->type;
        $object_id = (int)$postData->object_id;  //评论对象id

        if ($post_type == 'shop') {
            $object_data = $this->_getPlayShopTable()->get(array('shop_id' => $object_id));
        } elseif ($post_type == 'game') {
            $object_data = $this->_getPlayOrganizerGameTable()->get(array('id' => $object_id));
        } elseif ($post_type == 'organizer') {
            $object_data = $this->_getPlayOrganizerTable()->get(array('id' => $object_id));
        } else {
            return $this->_Goto('游玩地 商品 活动组织者以外暂不支持回复');
        }

        if ($object_data->allow_post == 0) {
            return $this->_Goto('评论已关闭');
        }

        $message = $this->getPost('info', '', false); // 评论内容

        if (!$message) {
            return  $this->_Goto('内容未填写');
        }

        $con = htmlspecialchars($message);
        $post_Data = array(
            'type' => $post_type,
            'object_id' => $object_id,
            'uid' => $uid,
            'author' => $author,
            'subject' => $postData->author,
            'message' => $con,
            'dateline' => time(),
            'replypid' => $pid,
            'replyuid' => $postData->uid,
            'userip' => $_SERVER['REMOTE_ADDR'],
            'photo_number' => 0,
            'photo_list' => json_encode(array(), true),
            'displayorder' => 1,  //0删除 1正常  2置顶
            'img' => $img,
        );

        $status = $this->_getPlayPostTable()->insert($post_Data);
        $insert_id = $this->_getPlayPostTable()->getlastInsertValue();

        $message = array(
            array(
                't' => 1,
                'val' => $message,
            )
        );

        $addr_x = '114.274895'; //经度
        $addr_y = '30.561448'; //纬度
        if ($status) {
            //todo 评论成功 更新post_num 及 replay_list
            if ($post_type == 'shop') {
                $this->_getPlayShopTable()->update(array('post_number' => new Expression('post_number+1')), array('shop_id' => $object_id));
            } elseif ($post_type == 'game') {
                $this->_getPlayOrganizerGameTable()->update(array('post_number' => new Expression('post_number+1')), array('id' => $object_id));
            } elseif ($post_type == 'organizer') {
                $this->_getPlayOrganizerTable()->update(array('post_number' => new Expression('post_number+1')), array('id' => $object_id));
            }

            $reply_list = $postData->reply_list ? json_decode($postData->reply_list, true) : array();
            if (count($reply_list) < 3) {
                $reply_list[] = array(
                    'uid' => $uid,
                    'pid' => $insert_id,
                    'username' => $author,
                    'message' => $message  //is toArray
                );
            }

            $this->_getPlayPostTable()->update(array('reply_list' => json_encode($reply_list, JSON_UNESCAPED_UNICODE), 'reply_number' => new Expression('reply_number+1')), array('pid' => $pid));

            //todo 插入mongodb 回复表数据
            $msg_data = $this->_getMdbSocialCircleMsg()->findOne(array('pid' => $pid, 'status' => 1));
            if ($msg_data) {
                $obj = array(
                    'mid' => (string)$msg_data['_id'],//'主题消息id',
                    'uid' => $uid,
                    'cid' => $msg_data['cid'],
                    'username' => $author,
                    'first' => 0,// '是否主题贴',
                    'title' => $msg_data['title'],
                    'msg' => $message,//回复内容
                    'img' => $img, //用户头像
                    'dateline' => time(),//时间戳
                    'child' => $uid_data->user_alias,
                    'addr' => array(
                        'type' => 'Point',
                        'coordinates' => array($addr_x, $addr_y)
                    ),
                    'status' => 1,
                );
                $this->_getMdbsocialCircleMsgPost()->save($obj);

                //最多三条
                if (count($msg_data['posts']) < 4) {
                    $msg_data['posts'][] = array('uid' => $uid, 'username' => $this->hidePhoneNumber($uid_data->username), 'message' => $message, 'dateline' => time());
                }
                $mid = (string)$msg_data['_id'];
                $this->_getMdbSocialCircleMsg()->update(array('_id' => new \MongoId($mid), 'status' => 1), array('$inc' => array('replay_number' => 1), '$set' => array('posts' => $msg_data['posts'])));

            }
            return $this->_Goto('评论成功', '/wftadlogin/word/new?pid='. $pid);

        } else {
            return $this->_Goto('回复失败');
        }

    }


    //状态操作
    public function displayAction()
    {
        $type = $this->getQuery('type');//类型
        $pid = (int)$this->getQuery('pid');

        if (!$pid || !in_array($type, array('up', 'del'))) {
            return $this->_Goto('非法操作');
        }

        $postData = $this->_getPlayPostTable()->get(array('pid' => $pid, 'displayorder > ?' => 0));

        if (!$postData) {
            return $this->_Goto('非法操作');
        }

        if ($type == 'up') {//置顶及取消置顶
            $display = (int)$this->getQuery('display');
            $display = ($display == 2) ? 1 : 2;
            $status = $this->_getPlayPostTable()->update(array('displayorder' => $display), array('pid' => $pid));
            $this->_getMdbSocialCircleMsg()->update(array('pid' => $pid), array('$set' => array("status" => $display)));

            if ($status) {
                return $this->_Goto('成功');
            } else {
                return $this->_Goto('失败');
            }
        }

        if ($type == 'del') {
            $status = $this->_getPlayPostTable()->update(array('displayorder' => 0), array('pid' => $pid));
            $this->_getMdbSocialCircleMsg()->update(array('pid' => $pid), array('$set' => array("status" => -1)));

            if ($status) {
                return $this->_Goto('成功');
            } else {
                return $this->_Goto('失败');
            }
        }
        exit;
    }

    //缩略图
    public function toThumbAction() {
        $id = $this->getQuery('mid');
        $data = $this->_getMdbSocialCircleMsg()->findOne(array('_id' => new \MongoId($id)));
        foreach($data['msg'] as $k) {
            if ($k['t'] == 2) {
                $up_img = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] .$k['val']);
                $res = $up_img->MaxSquareZoomResizeImage(320)->Save($_SERVER['DOCUMENT_ROOT'] . $k['val'] . '.thumb.jpg');
                if ($res !== true) {
                    echo '3'.'<br />';
                } else {
                    echo '67'. '<br />';
                }
            }
        }
        exit;
    }

    /** 无id */

   /* public function toUidAction () {
        $uid = 578745;
        $userData = $this->_getPlayUserTable()->get(array('uid' => 13160));
        $upData = array(
            'uid' => 13160,
            'img' => $this->getImgUrl($userData->img),
            'username' => $userData->username,
            'user_datail' => $userData->user_alias,
        );
        $down = $this->_getMdbSocialCircleMsg()->update(array('uid' => $uid), array('$set' => $upData), array('multiple' => true));

        var_dump($down);
        exit;

    }*/

}
