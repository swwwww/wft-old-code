<?php

namespace Admin\Controller;

use Deyi\JsonResponse;
use Deyi\Paginator;
use Zend\Db\Sql\Predicate\Expression;
use Zend\View\Model\ViewModel;

class PostController extends BasisController
{
    use JsonResponse;
    //评论列表
    public function indexAction()
    {
        $id = $this->getQuery('id');// 对象id
        $type = $this->getQuery('type', ''); //类型
        $like = $this->getQuery('k', ''); //类型
        $where = array(
            'displayorder > ?' => 0
        );
        if ($id) {
            $where['object_id'] = $id;
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
        $url = '/wftadlogin/post';
        $paginator = new Paginator($page, $count, $pagesum, $url);

        return array(
            'data' => $data,
            'pagedata' => $paginator->getHtml()
        );
    }


    //添加评论
    public function addAction()
    {
        $type = $this->getQuery('type');
        $pid = $this->getPost('pid');
        $id = $this->getQuery('id');

        $bind_uid = $this->_getPlayAdminTable()->get(array('id' => $_COOKIE['id']));
        if (!$bind_uid->bind_user_id) {
            return $this->_Goto('请先通过"编辑账号管理"绑定uid');
        }

        $user_data = $this->_getPlayUserTable()->get(array('uid' => $bind_uid->bind_user_id));

        if (!$user_data or $user_data->status != 1) {
            return $this->_Goto('用户不存在或已禁用');
        }

        //添加评论
        if ($_POST['editorValue']) {

            $photo = $this->_getPlayAttachTable()->fetchCount(array('use_type' => 'post', 'use_id' => $pid));
            $uid = $bind_uid->bind_user_id;
            $tid = (int)$this->getQuery('tid', 0);// 回复帖子id todo
            $object_id = $id;  //评论对象id
            $message = htmlspecialchars($_POST['editorValue']); // 评论内容

            //对传过来的参数进行判断处理
            if (!$uid or !$object_id or !$type or !$tid) {
                return $this->_Goto('参数错误');
            }

            if (!$message) {
                return $this->_Goto('内容未填写');
            }

            if (mb_strlen($message) > 2000) {
                return $this->_Goto('亲，评论内容不能超过2000字哦');
            }

            if (!in_array($type, array('activity', 'coupon', 'news', 'shop', 'organizer', 'game'))) {
                return $this->_Goto('评论类型错误');
            }

            $author = $user_data->username;
            $img = $user_data->img;

            //todo  获取评论对象内容  判断是否允许评论
            $data = false;
            if ($type == 'activity') {
                $data = $this->_getPlayActivityTable()->get(array('id' => $object_id));
                //$subject = $data->ac_name;
            } else if ($type == 'coupon') {
                $data = $this->_getPlayCouponsTable()->get(array('coupon_id' => $object_id));
                //$subject = $data->coupon_name;
            } elseif ($type == 'news') {
                $data = $this->_getPlayNewsTable()->get(array('id' => $object_id));
                //$subject = $data->title;
            } elseif ($type == 'shop') {
                $data = $this->_getPlayShopTable()->get(array('shop_id' => $object_id));
                //$subject = $data->shop_name;
            } elseif ($type == 'organizer') {
                $data = $this->_getPlayOrganizerTable()->get(array('id' => $object_id));
                //$subject = $data->name;
            } elseif ($type == 'game') {
                $data = $this->_getPlayOrganizerGameTable()->get(array('id' => $object_id));
                //$subject = $data->title;
            }

            if (!$data) {
                return $this->_Goto('评论对象不存在或已删除');
            }

            if ($data->allow_post == 0) {
                return $this->_Goto('评论已关闭');
            }

            //回复
             $post_data = $this->_getPlayPostTable()->get(array('pid' => $tid));
             $subject =$post_data->author;

            $postData = array(
                'type' => $type,
                'object_id' => $object_id,
                'uid' => $uid,
                'author' => $author,
                'subject' => $subject,
                'message' => $message,
                'dateline' => time(),
                'replypid' => $tid,
                'userip' => $_SERVER['REMOTE_ADDR'],
                'displayorder' => 1,  //0删除 1正常  2置顶
                'img' => $img,
                'photo_number' => $photo
            );

            $status = $this->_getPlayPostTable()->update($postData, array('pid' => $pid));

            /******* 产生提醒 取出时通过pid判断是否为回复楼主 ********/
            if ($status) {
                //todo 评论成功
                if ($type == 'activity') {
                    $this->_getPlayActivityTable()->update(array('post_number' => new Expression('post_number+1')), array('id' => $object_id));
                } elseif ($type == 'coupon') {
                    $this->_getPlayCouponsTable()->update(array('post_number' => new Expression('post_number+1')), array('coupon_id' => $object_id));
                } elseif ($type == 'news') {
                    $this->_getPlayNewsTable()->update(array('post_number' => new Expression('post_number+1')), array('id' => $object_id));
                } elseif ($type == 'shop') {
                    $this->_getPlayShopTable()->update(array('post_number' => new Expression('post_number+1')), array('shop_id' => $object_id));
                } elseif ($type == 'organizer') {
                    $this->_getPlayOrganizerTable()->update(array('post_number' => new Expression('post_number+1')), array('id' => $object_id));
                } elseif ($type == 'game') {
                    $this->_getPlayOrganizerGameTable()->update(array('post_number' => new Expression('post_number+1')), array('id' => $object_id));
                }
                return $this->_Goto('添加评论成功', "/wftadlogin/post?type={$type}");
            } else {
                return $this->_Goto('添加评论失败');
            }
        } else {
            //清空无效数据
            $time = time() - 300;
            $this->_getPlayPostTable()->delete(array('message' => '', 'displayorder' => 0, "dateline<{$time}"));


            //生成一条空记录
            $postData = array(
                'type' => $type,
                'object_id' => $id,
                'uid' => $bind_uid->bind_user_id,
                'author' => $user_data->username,
                'subject' => '',
                'message' => '',
                'dateline' => time(),
                'replypid' => 0,
                'userip' => $_SERVER['REMOTE_ADDR'],
                'displayorder' => 0,  //0删除 1正常  2置顶
                'img' => $user_data->img,
            );
            $this->_getPlayPostTable()->insert($postData);
            $pid = $this->_getPlayPostTable()->getlastInsertValue();
            return array('pid' => $pid);
        }

    }

    //状态操作
    public function displayAction()
    {
        $order = $this->getQuery('order');  //状态
        $pid = $this->getQuery('pid');

        $ac = array(0 => '删除', 1 => '恢复', 2 => '置顶');

        $data = $this->_getPlayPostTable()->get(array('pid' => $pid));
        $status = $this->_getPlayPostTable()->update(array('displayorder' => $order), array('pid' => $pid));


        if ($status) {
            if ($data->type == 'activity') {
                $this->_getPlayActivityTable()->update(array('post_number' => new Expression('post_number-1')), array('id' => $data->object_id, 'post_number>0'));
            } elseif ($data->type == 'coupon') {
                $this->_getPlayCouponsTable()->update(array('post_number' => new Expression('post_number-1')), array('coupon_id' => $data->object_id, 'post_number>0'));
            } elseif ($data->type == 'news') {
                $this->_getPlayNewsTable()->update(array('post_number' => new Expression('post_number-1')), array('id' => $data->object_id, 'post_number>0'));
            }
        }


        if ($status) {
            return $this->_Goto($ac[$order] . '成功');
        } else {
            return $this->_Goto($ac[$order] . '失败');
        }

    }


}
