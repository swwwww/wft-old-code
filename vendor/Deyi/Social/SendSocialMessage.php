<?php

/**
 * 生成圈子消息
 */
namespace Deyi\Social;


use Deyi\BaseController;
use Deyi\GeTui\GeTui;
use library\Service\System\Cache\RedCache;
use Zend\Db\Sql\Expression;

trait SendSocialMessage
{
    //use BaseController;

    /**
     * @param $uid
     * @param $cid
     * @param $title
     * @param $content
     * @param $msg_type
     * @param $object_id
     * @param $addr_x
     * @param $addr_y
     * @param $pid 兼容id
     * @param $order_sn 订单
     * @param $attach  //自定义 其它用户时使用 vir_post 时 虚拟评论
     * @return array
     */
    public function SendSocialMessage($uid, $cid, $title, $content, $msg_type, $object_id, $addr_x, $addr_y, $pid = 0, $group_buy_id = 0, $share_user_list = array(), $star = 0, $city = 'WH', $order_sn = 0, $attach = '')
    {

//        $uid = (int)$this->getParams('uid');
//        $cid = $this->getParams('cid');
//        $title = $this->getParams('title');
//        $content = $this->getParams('content'); //json 内容
//        $msg_type = $this->getParams('msg_type', 1);// '消息类型 1圈子 2评论商品 3评论游玩地 4评论商家 5评论专题　6团购分享 7活动',
//        $object_id = $this->getParams('object_id', 0);
//        $addr_x = $this->getParams('addr_x'); //经
//        $addr_y = $this->getParams('addr_y');//纬度


        $mdb = $this->_getMongoDB();
        $city = array($city);
//

        // 获取订阅用户列表
//        $social_friends = $mdb->social_friends;
//        $f_res = $social_friends->find(array('like_uid' => $uid));
//        $uids = array();
//        foreach ($f_res as $v) {
//            $uids[] = (int)$v['uid'];
//        }
//        $uids[] = $uid;
        // uid=$uid
        $social_circle_msg = $mdb->social_circle_msg;
        $user_data = $this->_getPlayUserTable()->get(array('uid' => $uid));


        $social_circle = $mdb->social_circle;
        if ($cid) {
            $circle_data = $social_circle->findOne(array('_id' => new \MongoId($cid)));
        } else {
            $circle_data['title'] = ''; //圈子名称
        }


        $content_arr = json_decode($content, true);
//        $img_list = array();
//        foreach ($content_arr as $v) {
//            if ($v['t'] == 2) {
//                $img_list[] = $this->_getConfig()['url'] . $v['val'] . '.thumb.jpg';
//            }
//        }

        $object_data = array(
            'object_id' => 0,//对象id
            'object_title' => '',
            'object_ticket' => 0, //'0无票  1有票',
            'object_img' => '',
        );
        // '消息类型 1圈子 2评论商品 3评论游玩地 4评论商家 5评论专题 6团购分享 7活动',
        if ($msg_type == 1) {

        } elseif ($msg_type == 7) { //活动
            $data = $this->_getPlayExcerciseEventTable()->getEventInfo(array('play_excercise_event.id' => $object_id));

            //重新定义城市、
            if($data->city){
                $city = array($data->city);
            }

            if ($data) {
                $object_data = array(
                    'object_id' => (int)$data->id,//对象id
                    'object_bid' => (int)$data->bid,
                    'object_title' => $data->name,
                    'object_no' => (int)$data->no,
                    'object_img' => $this->_getConfig()['url'] . $data->thumb,
                );

                if ($order_sn) {
                    // 更新为已评论
                    $buy = $this->_getPlayOrderInfoTable()->getUserEvent($order_sn);
                    if ($buy) {
                        RedCache::del('D:needComment:'.$uid);

                        $tmp = $this->_getPlayOrderOtherDataTable()->get(array('order_sn' => $order_sn));
                        if (!$tmp) {
                            $this->_getPlayOrderOtherDataTable()->insert(array(
                                'order_sn' => $order_sn,
                                'comment' => 1
                            ));
                        } else {
                            $this->_getPlayOrderOtherDataTable()->update(array(
                                'comment' => 1
                            ), array('order_sn' => $order_sn));
                        }
                    }
                }


            }
        } elseif ($msg_type == 2) {
            $data = $this->_getPlayOrganizerGameTable()->get(array('id' => $object_id));
            if ($data) {
                $tmp = $this->_getPlayGameInfoTable()->get(array('gid' => $object_id));
                $object_data = array(
                    'object_id' => $object_id,//对象id
                    'object_title' => $data->title,
                    'object_ticket' => $tmp ? "1" : "0", //'0无票  1有票',
                    'object_img' => $this->_getConfig()['url'] . $data->thumb,
                    'post_award' => $data->post_award
                );

                if($data->city){
                    $city = array($data->city);
                }

                if ($order_sn) {
                    // 更新为已评论
                    $buy = $this->_getPlayOrderInfoTable()->getUserBuy($order_sn);
                    if ($buy) {
                        $object_data['object_place'] = $buy->address;
                        $object_data['object_shop_id'] = $buy->shop_id;

                        RedCache::del('D:needComment:'.$uid);

                        $tmp = $this->_getPlayOrderOtherDataTable()->get(array('order_sn' => $order_sn));
                        if (!$tmp) {
                            $this->_getPlayOrderOtherDataTable()->insert(array(
                                'order_sn' => $order_sn,
                                'comment' => 1
                            ));
                        } else {
                            $this->_getPlayOrderOtherDataTable()->update(array(
                                'comment' => 1
                            ), array('order_sn' => $order_sn));
                        }
                    }
                }


            }
        } elseif ($msg_type == 3) {
            $data = $this->_getPlayShopTable()->get(array('shop_id' => $object_id));
            if ($data) {
                $object_data = array(
                    'object_id' => $object_id,//对象id
                    'object_title' => $data->shop_name,
                    'object_ticket' => $data->reticket, //'0无票  1有票',
                    'object_img' => $this->_getConfig()['url'] . $data->thumbnails,
                    'post_award' => $data->post_award
                );
                if($data->shop_city){
                    $city = array($data->shop_city);
                }
            }
        } elseif ($msg_type == 4) {
            $data = $this->_getPlayOrganizerTable()->get(array('id' => $object_id));
            if ($data) {
                $object_data = array(
                    'object_id' => $object_id,//对象id
                    'object_title' => $data->name,
                    'object_ticket' => 1, //'0无票  1有票',
                    'object_img' => $this->_getConfig()['url'] . $data->thumb,
                );
                if($data->city){
                    $city = array($data->city);
                }
            }

        } elseif ($msg_type == 5) {
            $data = $this->_getPlayActivityTable()->get(array('id' => $object_id));
            if ($data) {
                $object_data = array(
                    'object_id' => $object_id,//对象id
                    'object_title' => $data->ac_name,
                    'object_ticket' => $data->reticket, //'0无票  1有票',
                    'object_img' => $this->_getConfig()['url'] . $data->ac_name,
                );
                if($data->ac_city){
                    $city = array($data->ac_city);
                }

            }
        } elseif ($msg_type == 6) {
            $group_data = $this->_getPlayGroupBuyTable()->get(array('id' => $group_buy_id));
            $game_data = $this->_getPlayOrganizerGameTable()->get(array('id' => $group_data->game_id));
            $game_info_data = $this->_getPlayGameInfoTable()->get(array('id' => $group_data->game_info_id));
            $shopData = $this->_getPlayShopTable()->get(array('shop_id' => $game_info_data->shop_id));

            $object_data = array(
                'object_id' => (int)$group_data->game_id,//对象id
                'object_title' => $game_data->title,
                'object_ticket' => 1, //'0无票  1有票',
                'object_img' => $this->getImgUrl($game_data->thumb),
            );

            if($game_data->city){
                $city = array($game_data->city);
            }

            $object_data['group_buy_id'] = (int)$group_buy_id;
            $object_data['limit_number'] = (int)$group_data->limit_number;
            $object_data['join_number'] = (int)$group_data->join_number;
            $object_data['end_time'] = $group_data->end_time;
            // 其他
            $object_data['title'] = $game_data->title; //商品
            $object_data['price'] = $game_data->g_price; //价格
            $object_data['cover'] = $this->getImgUrl($game_data->cover);
            $object_data['type_name'] = $game_info_data->price_name; //参加方式
            $object_data['time'] = date('Y-m-d H:i', $game_info_data->start_time) . '~' . date('Y-m-d H:i', $game_info_data->end_time); //有效期
            $object_data['address'] = $game_info_data->shop_name; //地址
            $object_data['order_method'] = $game_data->order_method;//兑换方式
            //退款说明
            $object_data['back_money'] = '已组团成功的团购商品，不支持退款';
            $object_data['phone'] = $shopData->shop_phone; //联系电话
            $object_data['addr_x'] = $shopData->addr_x; //联系电话
            $object_data['addr_y'] = $shopData->addr_y; //联系电话
            $object_data['game_id'] = $group_data->game_id;
            $object_data['game_info_id'] = $group_data->game_info_id;
            $object_data['group_uid'] = $group_data->uid; //创建团uid
        }

        if (!empty($share_user_list)) {
            //推送给好友

            $uids = $this->_getPlayUserTable()->findGeTuiId($share_user_list);
            $geTui = new GeTui();

            $user = $this->_getMdbsocialChatMsg();
            $user2 = $mdb->ids;
            $content_arr = json_decode($content, true);

            foreach ($share_user_list as $v) {

                $id = $user2->findAndModify(array('table' => 'social_chat_msg'), array('$inc' => array('id' => 1)), null, array('new' => true, 'upsert' => true))['id'];
                $status = $user->insert(array(
                    'id' => (int)$id,
                    'from_uid' => (int)$uid,
                    'to_uid' => (int)$v,
                    'msg' => $content_arr,
                    'msg_type' => $msg_type,
                    'object_data' => $object_data,
                    'dateline' => time(),
                    'status' => 1,
                    'new' => 0
                ));

                $send_data = array(
                    'title' => '',
                    'info' => array('id' => (string)$id, 'uid' => (string)$uid, 'username' => $this->hidePhoneNumber($user_data->username), 'img' => $this->getImgUrl($user_data->img), 'info' => $content_arr),
                    'type' => 8,
                    'id' => '0',
                    'time' => time(),
                );

                $send_data['info']['game_title'] = $game_data->title;
                $send_data['info']['game_img'] = $this->getImgUrl($game_data->cover);
                $send_data['info']['group_buy_id'] = (int)$group_buy_id;
                $send_data['info']['limit_number'] = (int)$group_data->limit_number;
                $send_data['info']['join_number'] = (int)$group_data->join_number;
                $send_data['info']['end_time'] = $group_data->end_time;

                // 其他
                $send_data['info']['price'] = $game_data->g_price; //价格
                $send_data['info']['type_name'] = $game_info_data->price_name; //参加方式
                $send_data['info']['time'] = date('Y-m-d H:i', $game_info_data->start_time) . '~' . date('Y-m-d H:i', $game_info_data->end_time); //有效期
                $send_data['info']['address'] = $game_info_data->shop_name; //地址
                $send_data['info']['order_method'] = $game_data->order_method;//兑换方式
                //退款说明
                $send_data['info']['back_money'] = '已组团成功的团购商品，不支持退款';
                $send_data['info']['phone'] = $shopData->shop_phone; //联系电话
                $send_data['info']['game_id'] = $group_data->game_id;
                $send_data['info']['game_info_id'] = $group_data->game_info_id;
                $send_data['info']['group_uid'] = $group_data->uid; //创建团uid
                $s = $geTui->Push($uids[$v], ' ', json_encode($send_data));

            }


        }


        if ($cid or empty($share_user_list)) {
            $data = array(
                'cid' => $cid,//  '圈子id',
                'c_name' => $circle_data['title'],
                'title' => $title,
                'msg' => is_array($content_arr)?$content_arr:[],
                'msg_type' => (int)$msg_type,// '消息类型 1圈子 2评论商品 3评论游玩地 4评论商家 5评论专题  6团购分享 7活动',
                'uid' => (int)$uid,
                'img' => $user_data->img,
                'username' => $user_data->username,
                'child' => $user_data->user_alias,
                'view_number' => 0,
                'like_number' => 0,
                'share_number' => 0,
                'replay_number' => 0,
                'sub_uids' => array(),//订阅用户列表
                'dateline' => time(),//时间戳
                'status' => 1,// '状态  0删除 1正常 ',
                //  'img_list' => $img_list,
                'posts' => array( // '评论列表 只存前三条
//               array('uid' => $uid, 'username' => $user_data->username, 'message' => $data, 'dateline' => time())
//               array('uid' => $uid, 'username' => $user_data->username, 'message' => $data, 'dateline' => time());
//               array('uid' => $uid, 'username' => $user_data->username, 'message' => $data, 'dateline' => time());
                ),
                'object_data' => $object_data,
                'addr' => array(
                    'type' => 'Point',
                    'coordinates' => array($addr_x, $addr_y)
                ),
                'pid' => (int)$pid,
                'star_num' => $star,
                'city' => $city
            );

            if ($attach == 'vir_post') {
                $data['is_vir'] = 1;
            }

            $status = $social_circle_msg->insert($data);

            if ($status) {
                if ($cid) {
                    $social_circle->update(array('_id' => new \MongoId($cid)), array('$inc' => array('msg' => 1, 'today_msg' => 1)));
                    $this->_getPlayUserTable()->update(array('circle_msg' => new Expression('circle_msg+1')), array('uid' => $uid));
                }
                return (array('status' => 1, 'message' => '发送成功', 'lastid' => $data['_id']));
            } else {
                return (array('status' => 0, 'message' => '操作失败'));
            }
        }


        return (array('status' => 1, 'message' => '发送成功'));
    }

}