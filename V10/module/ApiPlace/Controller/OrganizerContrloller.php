<?php

namespace ApiPlace\Controller;

use library\Fun\M;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Response;
use Deyi\JsonResponse;
use Deyi\BaseController;

class OrganizerController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    //商家详情
    public function organizerAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $id = $this->getParams('id');
        $uid = $this->getParams('uid', 0);
        $organizerData = $this->_getPlayOrganizerTable()->get(array('id' => $id, 'status > ?' => 0));
        if (!$organizerData) {
            return $this->jsonResponseError('不存在');
        }

        //统计活动组织者点击次数
        $this->_getPlayOrganizerTable()->update(array('click_num' => new Expression('click_num+1')), array('id' => $id));

        //基本信息
        $res = array(
            'cover' => $organizerData->cover,
            'name' => $organizerData->name,
            'brief' => $organizerData->brief,
            'information' => $this->_getConfig()['url'] . '/web/organizer/info?type=2&rid=' . $id,
            'address' => $organizerData->address,
            'addr_x' => $organizerData->addr_x,
            'addr_y' => $organizerData->addr_y,
            'phone' => $organizerData->phone,
            'thumb' => $this->_getConfig()['url'] . $organizerData->thumb,
            'share_img' => $this->_getConfig()['url'] . $organizerData->thumb,
            'share_title' => $organizerData->name . '欢迎你',
            'share_content' => $organizerData->brief,
            'game' => array(),
            'post_list' => array(),
            'is_collect' => 0,
        );


        if ($uid) {
            $flag = M::getPlayUserCollectTable()->getCollect($uid,'organizer',$id);
            if ($flag) {
                $res['is_collect'] = 1;
            }
        }

        //活动
        $game_id_list = array();
        $gameData = $this->_getPlayOrganizerGameTable()->fetchLimit(0, 3, array(),
            array('organizer_id' => $id, 'status > ?' => 0, 'start_time < ?' => time(), 'end_time > ?' => time()));
        foreach ($gameData as $gValue) {
            $res['game'][] = array(
                'thumb' => $this->_getConfig()['url'] . $gValue->thumb,
                'id' => $gValue->id,
                'name' => $gValue->title,
                'time' => $gValue->head_time,
                'price' => $gValue->low_price,
                'have' => ($gValue->ticket_num - $gValue->buy_num),
                'old_price' => $gValue->low_money,
                'g_buy' => (($gValue->down_time - time()) > 86400) ? $gValue->g_buy : '0',
            );
            $game_id_list[] = (int)$gValue->id;
        }

        //评论
        $where = array(
            '$or' => array(
                array('msg_type' => 2, 'object_data.object_id' => array('$in' => $game_id_list)),
                array('msg_type' => 4, 'object_data.object_id' => (int)$id)
            ),
            'status' => array('$gt' => 0)
        );
        $post_data = $this->_getMdbSocialCircleMsg()->find($where)->sort(array(
            'status' => -1,
            'dateline' => -1
        ))->limit(3);
        $res['post_number'] = $post_data->count();
        $res['post_list'] = array();
        foreach ($post_data as $v) {
            $is_like = 0;
            if ($uid) {
                $like_data = $this->_getMdbSocialPrise()->findOne(array(
                    'uid' => (int)$uid, //用户id
                    'type' => 1, //类型 圈子消息
                    'object_id' => (string)$v['_id'],
                ));

                if ($like_data) {
                    $is_like = 1;
                }
            }
            $res['post_list'][] = array(
                'mid' => (string)$v['_id'],
                'id' => $v['pid'],
                'uid' => $v['uid'],
                'author' => $this->hidePhoneNumber($v['username']),
                'like_number' => $v['like_number'],
                'reply_number' => $v['replay_number'],
                'user_detail' => $v['child'],
                'is_like' => $is_like,
                'type' => $v['msg_type'],
                'object_id' => $v['object_data']['object_id'],
                'subject' => $v['title'],
                'author_img' => $this->getImgUrl($v['img']),
                'dateline' => $v['dateline'],
                'message' => $v['msg'],
            );
        }
        $res['post_list'] = array_slice($res['post_list'], 0, 3);

        return $this->jsonResponse($res);
    }

}
