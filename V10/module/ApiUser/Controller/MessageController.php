<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Apiuser\Controller;

use Deyi\BaseController;
use library\Service\System\Cache\RedCache;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Predicate\In;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Http\Response;
use Deyi\JsonResponse;
use Deyi\Mcrypt;

class MessageController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    public function __construct()
    {

    }

    //个人消息列表
    public function indexAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = (int)$this->getParams('uid');
        if (!$uid) {
            return $this->jsonResponseError('用户id不存在');
        }

        $mid = (int)$this->getParams('message_id', 0);
        $where =  array('uid' => $uid, 'status > ?' => 0, 'type <= 8');
        if ($mid) {
            $where['play_user_message.id < ?'] = $mid;
        }

        $messData = $this->_getPlayUserMessageTable()->fetchLimit(0, 15, array(), $where, array('id' => 'desc'));
        $res = array();
        foreach($messData as $mes) {
            if ($mes->type == 1) {
                $link_id = array(
                    'type' => 'coupon',
                    'id' => $mes->link_id,
                    'lid' => '',
                );
            } elseif ($mes->type == 2) {
                $link_id = array(
                    'type' => 'game',
                    'id' => $mes->link_id,
                    'lid' => '',
                );
            } elseif ($mes->type == 3) {
                $link_id = array(
                    'type' => 'game',
                    'id' => $mes->link_id,
                    'lid' => '',
                );
            } elseif ($mes->type == 4) {
                $link_id = array(
                    'type' => 'user',
                    'id' => '',
                    'lid' => '',
                );
            } elseif ($mes->type == 5) {
                $link_id = json_decode($mes->link_id);
            } elseif ($mes->type == 6) {
                $link_id = json_decode(urldecode($mes->link_id));
            } elseif ($mes->type == 7) {
                $link_id = json_decode(urldecode($mes->link_id));
            } elseif ($mes->type == 8) {
                $link_id = json_decode($mes->link_id);
            } else {
                continue;
            }

            $res[] = array(
                'id'      => $mes->id,
                'time'    => $mes->deadline,
                'title'   => $mes->title,
                'is_new'  => $mes->is_new,
                'type'    => $mes->type,
                'link_id' => $link_id,
                'message' => $mes->message,
            );
        }
        return $this->jsonResponse($res);
    }

    //个人消息列表 3.4版本接口
    public function nindexAction() {

        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = (int)$this->getParams('uid');
        if (!$uid) {
            return $this->jsonResponseError('用户id不存在');
        }

        $mid = (int)$this->getParams('message_id', 0);
        $where =  array(
            'uid'        => $uid,
            'status > ?' => 0,
            'type <> ?'  => 15,
            'type <> ?'  => 16,
        );
        if ($mid) {
            $where['play_user_message.id < ?'] = $mid;
        }

        $messData     = $this->_getPlayUserMessageTable()->fetchLimit(0, 15, array(), $where, array('id' => 'desc'));
        $data_message = array();
        foreach($messData as $mes) {
            if ($mes->type == 1) {
                $link_id = array(
                    'type' => 'coupon',
                    'id' => $mes->link_id,
                    'lid' => '',
                );
            } elseif ($mes->type == 2) {
                $link_id = array(
                    'type' => 'game',
                    'id' => $mes->link_id,
                    'lid' => '',
                );
            } elseif ($mes->type == 3) {
                $link_id = array(
                    'type' => 'game',
                    'id' => $mes->link_id,
                    'lid' => '',
                );
            } elseif ($mes->type == 4) {
                $link_id = array(
                    'type' => 'user',
                    'id' => '',
                    'lid' => '',
                );
            } elseif ($mes->type == 5) {
                $link_id = json_decode($mes->link_id);
            } elseif ($mes->type == 6) {
                $link_id = json_decode(urldecode($mes->link_id));
            } elseif ($mes->type == 7) {
                $link_id = json_decode(urldecode($mes->link_id));
            } elseif ($mes->type == 8) {
                $link_id = json_decode($mes->link_id);
            } elseif ($mes->type == 9) {
                $link_id = json_decode($mes->link_id);
            } elseif ($mes->type == 10) {
                $link_id = json_decode($mes->link_id);
            } elseif ($mes->type == 11) {
                $link_id = json_decode($mes->link_id);
            } elseif ($mes->type == 12) {
                $link_id = json_decode($mes->link_id);
            } elseif ($mes->type == 13) {
                $link_id = json_decode($mes->link_id);
            } elseif ($mes->type == 14) {
                $link_id = json_decode($mes->link_id);
            } elseif ($mes->type == 15) {
                continue;
            } elseif ($mes->type == 16) {
                continue;
            } else {
                 return $this->jsonResponseError('非法操作');
            }

            $data_message[] = array(
                'id'      => $mes->id,
                'time'    => $mes->deadline,
                'title'   => $mes->title,
                'is_new'  => $mes->is_new,
                'type'    => $mes->type,
                'link_id' => $link_id,
                'message' => $mes->message,
            );
        }

        $pdo = $this->_getAdapter();

        $data_return_new_message_num = $pdo->query("SELECT type, count(*) as message_num FROM play_user_message WHERE uid = ? AND status > 0 AND (type = 15 or type = 16) AND is_new = 1 GROUP BY type", array($uid));

        $data_new_message_num = array();
        if (!empty($data_return_new_message_num)) {
            foreach ($data_return_new_message_num as $key=>$val) {
                $data_new_message_num[$val->type] = $val->message_num;
            }
        }

        $res = array(
            'post_num'     => $data_new_message_num[16] ? $data_new_message_num[16] : 0,
            'consult_num'  => $data_new_message_num[15] ? $data_new_message_num[15] : 0,
            'message_list' => $data_message,
        );

        RedCache::del('D:NewMgsNum:' . $uid);

        return $this->jsonResponse($res);
    }



    // update  删除 与 已读
    public function updateAction() {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = (int)$this->getParams('uid');
        if (!$uid) {
            return $this->jsonResponseError('用户id不存在');
        }

        $act = $this->getParams('act');
        $id = urldecode($this->getParams('id'));

        if (!$id) {
            return $this->jsonResponseError($id);
        }

        if (!in_array($act, array('read', 'del'))) {
            return $this->jsonResponseError('非法操作');
        }

        $message_ids =json_decode($id);
        if (empty($message_ids) || !is_array($message_ids)) {
            return $this->jsonResponseError('没有选择消息');
        }

        if ($act == 'read') {
           $this->_getPlayUserMessageTable()->update(array('is_new'=>0),array('uid'=>$uid,new In('id',$message_ids)));
        }
        if ($act == 'del') {
            $this->_getPlayUserMessageTable()->update(array('status'=>-1),array('uid'=>$uid,new In('id',$message_ids)));
        }

        RedCache::del('D:NewMgsNum:' . $uid);

        return $this->jsonResponse(array('status' => 1, 'message' => '操作成功'));
    }

    function query($sql)
    {
        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $stmt = $db->query($sql);
        $result = $stmt->execute();
        return $result;
    }


}
