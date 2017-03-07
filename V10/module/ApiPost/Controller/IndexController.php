<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ApiPost\Controller;

use Deyi\Account\Account;
use Deyi\BaseController;
use Deyi\Coupon\Coupon;
use Deyi\ImageProcessing;
use Deyi\Integral\Integral;
use library\Service\System\Cache\RedCache;
use Deyi\Social\SendSocialMessage;
use Deyi\SendMessage;
use library\Service\System\Logger;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Response;
use Deyi\JsonResponse;

class IndexController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;
    use SendSocialMessage;

    //发表评论
    public function indexAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        /**
         * 评论商品 评论游玩地 回复评论
         * uid 用户id
         * object_id 评论对象id
         * type      对象类型
         * message    内容
         */
        $uid = (int)$this->getParams('uid');
        $pid = $this->getParams('pid', 0);// 回复帖子id
        $object_id = (int)$this->getParams('object_id');  //评论对象id
        $order_sn = (int)$this->getParams('order_sn',0);  //评论对象id
        $type = (int)$this->getParams('type');  // 消息类型 1圈子 2评论商品 3评论游玩地 4评论商家 5评论专题 6团购分享 7活动',
        $title = $this->getParams('title', '', false);
        $message = $this->getParams('message', '', false); // 评论内容
        $cid = $this->getParams('cid', '');//圈子id
        $addr_x = $this->getParams('addr_x');
        $addr_y = $this->getParams('addr_y');

        $city = $this->getCity();

        $star = (int)$this->getParams('star', 0);//默认0 星星数

        //android bug 7/20
        if($type==5){
            $type=7;
        }

        $is_circle = $this->getParams('is_circle', 0);

        if ($is_circle) {
            $setting = $this->getMarketSetting();
            $cid = $setting->share_circle;
        }


        //对传过来的参数进行判断处理
        if (!$uid) {
            return $this->jsonResponseError('参数错误');
        }
        if (!$message) {
            return $this->jsonResponseError('内容未填写');
        }

        if (!$pid) {
            if (!in_array($type, array(2, 3, 4, 7)) or !$object_id) {
                return $this->jsonResponseError('评论类型错或对象id错误');
            }
        }

        $cid = $cid?:0;

        $uid_data = $this->_getPlayUserTable()->get(array('uid' => $uid));
        if (!$uid_data or $uid_data->status != 1) {
            return $this->jsonResponseError('用户不存在或已禁用');
        } else {
            $author = $this->hidePhoneNumber($uid_data->username);
            $img = $uid_data->img;
        }

        //频率限制
        $rt=RedCache::get('P:Social'.$uid);
        if($rt){
            return $this->jsonResponseError('频率过高');
        }else{
            RedCache::set('P:Social'.$uid,1,10); //10秒限制
        }

        if ($pid) {
            $msg_data = $this->_getMdbSocialCircleMsg()->findOne(array('_id' => new \MongoId($pid), 'status' => 1));
            $type = $msg_data['msg_type'];
            $object_id = $msg_data['object_data']['object_id'];
        }
        //  获取评论对象内容  判断是否允许评论
        $data = false;
        if($type == 7){
            $data = $this->_getPlayExcerciseEventTable()->getEventInfo(array('play_excercise_event.id' => $object_id));
            $subject = $data->name.' 第'.$data->no.'期';
        }else if ($type == 5) {
            $data = $this->_getPlayActivityTable()->get(array('id' => $object_id));
            $subject = $data->ac_name;
        } else if ($type == 'coupon') {
            $data = $this->_getPlayCouponsTable()->get(array('coupon_id' => $object_id));
            $subject = $data->coupon_name;
        } elseif ($type == 'news') {
            $data = $this->_getPlayNewsTable()->get(array('id' => $object_id));
            $subject = $data->title;
        } elseif ($type == 3) {
            $data = $this->_getPlayShopTable()->get(array('shop_id' => $object_id));
            $subject = $data->shop_name;
        } elseif ($type == 4) {
            $data = $this->_getPlayOrganizerTable()->get(array('id' => $object_id));
            $subject = $data->name;
        } elseif ($type == 2) {
            $data = $this->_getPlayOrganizerGameTable()->get(array('id' => $object_id));
            $subject = $data->title;
        }
        if (!$data) {
            return $this->jsonResponseError('评论对象不存在或已删除');
        }
        if (mb_strlen($message) > 2000) {
            return $this->jsonResponseError('亲，评论内容不能超过2000字哦');
        }
        //转换成二维数组
        $message = $this->msgToArray($message);

        /***************************** 后期可以删除 start ***************************/
        //兼容之前的type
        if ($type == 5) {
            $t = 'activity';
        } elseif ($type == 3) {
            $t = 'shop';
        } elseif ($type == 4) {
            $t = 'organizer';
        } elseif ($type == 2) {
            $t = 'game';
        } elseif($type == 7) {
            $t = 'excercise';
        } else {
            $t = $type;
        }
        //photo_number+1'), 'photo_list


        //将内容组合成字符串存入mysql
        $con = '';
        $imgs = array();
        foreach ($message as $v) {
            if ($v['t'] == 1) {
                $con .= $v['val'] . " ";
            } elseif ($v['t'] == 2) {
                $imgs[] = $v['val'];
            }
        }

        $postData = array(
            'type' => $t,
            'object_id' => $object_id,
            'uid' => $uid,
            'author' => $author,
            'subject' => $subject,
            'message' => $con,
            'dateline' => time(),
            'replypid' => $pid,
            'replyuid' => 0,
            'userip' => $_SERVER['REMOTE_ADDR'],
            'photo_number' => count($imgs),
            'photo_list' => json_encode($imgs, true),
            'displayorder' => 1,  //0删除 1正常  2置顶
            'img' => (strpos($img, '/') === 0) ? $this->_getConfig()['url'] . $img : $img,
        );
        $status = $this->_getPlayPostTable()->insert($postData);
        $insert_id = $this->_getPlayPostTable()->getlastInsertValue();

        /***************************** 后期可以删除 end ***************************/

        /******* 产生提醒 取出时通过pid判断是否为回复楼主 ********/

        // 评论成功
        if ($type == 7) {
            if($data){
                $this->_getPlayExcerciseBaseTable()->update(array('comment_number' => new Expression('comment_number+1')), array('id' => (int)$data['bid']));
                $this->_getPlayExcerciseEventTable()->update(array('comment_number' => new Expression('comment_number+1')), array('id' => $object_id));
            }
        } elseif ($type == 5) {
            $this->_getPlayActivityTable()->update(array('post_number' => new Expression('post_number+1')), array('id' => $object_id));
        } elseif ($type == 'coupon') {
            $this->_getPlayCouponsTable()->update(array('post_number' => new Expression('post_number+1')), array('coupon_id' => $object_id));
        } elseif ($type == 'news') {
            $this->_getPlayNewsTable()->update(array('post_number' => new Expression('post_number+1')), array('id' => $object_id));
        } elseif ($type == 3) {
            $this->_getPlayShopTable()->update(array('post_number' => new Expression('post_number+1')), array('shop_id' => $object_id));
        } elseif ($type == 4) {
            $this->_getPlayOrganizerTable()->update(array('post_number' => new Expression('post_number+1')), array('id' => $object_id));
        } elseif ($type == 2) {
            $this->_getPlayOrganizerGameTable()->update(array('post_number' => new Expression('post_number+1')), array('id' => $object_id));
        }

        if ($pid) {//回复
            // 插入mongodb 回复表数据
            $msg_data = $this->_getMdbSocialCircleMsg()->findOne(array('_id' => new \MongoId($pid), 'status' => 1));

            $social_circle_msg_post = $this->_getMdbsocialCircleMsgPost();

            $obj = array(
                'mid' => (string)$msg_data['_id'],//'主题消息id',
                'uid' => $uid,
                'cid' => $msg_data['cid'],
                'username' => $author,
                'first' => 0,// '是否主题贴',
                'title' => $msg_data['title'],
                'msg' => $message,//回复内容
                'img' => (strpos($img, '/') === 0) ? $this->_getConfig()['url'] . $img : $img, //用户头像
                'dateline' => time(),//时间戳
                'child' => $uid_data->user_alias,
                'addr' => array(
                    'type' => 'Point',
                    'coordinates' => array($addr_x, $addr_y)
                ),
                'status' => 1,
            );
            $status = $social_circle_msg_post->save($obj);

            //最多三条
            if (count($msg_data['posts']) < 4) {
                $msg_data['posts'][] = array('uid' => $uid, 'username' => $this->hidePhoneNumber($uid_data->username), 'message' => $message, 'dateline' => time());
            }
            $mid = (string)$msg_data['_id'];
            $this->_getMdbSocialCircleMsg()->update(array('_id' => new \MongoId($mid), 'status' => 1), array('$inc' => array('replay_number' => 1), '$set' => array('posts' => $msg_data['posts'])));

            $data_message_type = 16; // 消息类型为评论回复
            $data_inform_type  = 12; // 评论回复推送

            // 评论用户的用户信息
            $data_comment_user = $this->_getPlayUserTable()->get(array('uid' => $msg_data['uid']));

            // 评论回复推送内容
            $data_name = $msg_data['object_data']['object_title'];
            if (!empty($data_name)) {
                $data_name = '对' . $data_name;
            } else {
                $data_name = '';
            }
            $data_inform = "【玩翻天】您" . $data_name . "的评论有新回复啦，快来看看吧！";

            // 评论回复系统消息
            $data_message = "您" . $data_name . "的评论有新回复啦，快来看看吧！";

            $data_link_data = array(
                'mid'       => (string)$msg_data['_id'],
                'reply'     => (string)$obj['_id'],
                'type'      => 0,                               // 0为文字回复， 1为点赞
                'reply_uid' => $uid,
                'from_uid'  => $msg_data['uid'],
            );

            $class_sendMessage = new SendMessage();
            $class_sendMessage->sendMes($msg_data['uid'], $data_message_type, '您的评论有新回复啦', $data_message, $data_link_data);
            $class_sendMessage->sendInform($msg_data['uid'], $data_comment_user->token, $data_inform, $data_inform, '', $data_inform_type, $mid);

            return $this->jsonResponse(array('status' => 1, 'pid' => (string)$obj['_id'], 'message' => '评论成功'));

        } else {//评论
            $status = $this->SendSocialMessage($uid, $cid, $subject, $this->getParams('message', '', false), $type, $object_id, $addr_x, $addr_y, $insert_id, 0, array(), $star,$city,$order_sn);

            //记录总评分与评分次数
            if (!$pid and ($type == 2 or $type == 3 or $type == 7)) {
                $this->_getMdbGradingRecords()->update(array('object_id' => $object_id, 'type' => $type), array('$inc' => array('total_score' => $star, 'total_number' => 1)), array('upsert' => true));
                //更新评分
                if ($type == 2) {
                    $res = $this->_getMdbGradingRecords()->findOne(array('object_id' => $object_id, 'type' => 2));
                    if ($res and $res['total_score']) {
                        $ave = bcdiv($res['total_score'] , $res['total_number'], 1);
                        $this->_getPlayOrganizerGameTable()->update(array('star_num' => $ave), array('id' => $object_id));
                    }
                } elseif ($type == 3) {
                    $res = $this->_getMdbGradingRecords()->findOne(array('object_id' => $object_id, 'type' => 3));
                    if ($res and $res['total_score']) {
                        $ave = bcdiv($res['total_score'] , $res['total_number'], 1);
                        $this->_getPlayShopTable()->update(array('star_num' => $ave), array('shop_id' => $object_id));
                    }
                } elseif ($type == 7) {
                    $res = $this->_getMdbGradingRecords()->findOne(array('object_id' => $object_id, 'type' => 7));
                    if ($res and $res['total_score']) {
                        $ave = bcdiv($res['total_score'] , $res['total_number'], 1);
                        //$this->_getPlayExcerciseBaseTable()->update(array('star_num' => $ave), array('id' => array('id' => (int)$data['bid'])));
                        $this->_getPlayExcerciseEventTable()->update(array('star_num' => $ave), array('id' => $object_id));
                    }
                }

            }
            if ($status['status'] == 1) {
                //$message = json_decode($message);//限制评论奖励条件
                $len = $hsimg = 0;
                foreach($message as $m){
                    if($m['t'] == 1){
                        $len = mb_strlen($m['val']);
                    }
                    if($m['t'] == 2){
                        $hsimg = 1;
                    }
                }

                //评论成功给积分　2评论商品 3评论游玩地 4评论商家 5评论专题
                if($len >= 30 && $hsimg){
                    if ((int)$type === 2) {
                        $integral = new Integral();
                        $integral->good_comment_integral($uid,$status['lastid'], $city,$object_id,$subject);

                        if($order_sn){
                            //奖励现金券
                            $coupon = new Coupon();
                            $coupon->getCashCouponByCommend($uid,$order_sn,$status['lastid'],$city);
                            //返利
                            $cash = new Account();
                            $cash->getCashByCommend($uid,$order_sn,$status['lastid'],$city);
                        }

                    } elseif ((int)$type === 3) {
                        $integral = new Integral();
                        $integral->place_comment_integral($uid,$status['lastid'], $city,$object_id,$subject);
                    } elseif ((int)$type === 7) {
                        $integral = new Integral();
                        $integral->event_comment_integral($uid,$status['lastid'], $city,$object_id,$subject);
                    }
                }
                return $this->jsonResponse(array('status' => 1, 'pid' => $insert_id, 'message' => '评论成功'));
            } else {
                return $this->jsonResponse(array('status' => 0, 'pid' => $insert_id, 'message' => '接口错误,评论失败'));
            }
        }

    }

    //评论详情
    public function infoAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $pid = $this->getParams('pid'); //mongo _id
        $uid = $this->getParams('uid');//可选

        $last_repid = $this->getParams('last_repid');  //mongo _id
        $pagenum = $this->getParams('pagenum', 10);
        $is_like = 0;

        if (!$pid or !$this->checkMid($pid)) {
            return $this->jsonResponseError('pid不存在');
        }


        if($last_repid and !$this->checkMid($last_repid)){
            return $this->jsonResponseError('id错误');
        }
        $post_data = $this->_getMdbSocialCircleMsg()->findOne(array('_id' => new \MongoId($pid)));
        if (!$post_data) {
            return $this->jsonResponseError('数据不存在');
        }

        $where = array('mid' => (string)$post_data['_id']);
        if ($last_repid) {
            $where['_id'] = array('$gt' => new \MongoId($last_repid));
        }
        $where['status'] = array('$gt' => 0);

        $reply_data = $this->_getMdbSocialCircleMsgPost()->find($where)->sort(array('_id' => 1))->limit($pagenum);

        if ($uid) {
            $is_like = $this->_getMdbSocialPrise()->findOne(array(
                'uid' => (int)$uid, //用户id
                'type' => 1, //类型 圈子消息
                'object_id' => (string)$post_data['_id'],
            ));
            if ($is_like) {
                $is_like = 1;
            } else {
                $is_like = 0;
            }
        }


        $reply_list = array();


        foreach ($reply_data as $v) {
            $reply_list[] = array(
                'repid' => (string)$v['_id'],
                'uid' => $v['uid'],
                'username' => $this->hidePhoneNumber($v['username']),
                'img' => $this->getImgUrl($v['img']),
                'message' => $v['msg'],
                'dateline' => $v['dateline'],
            );
        }

        if ($last_repid) {
            $data = array(
                'reply_list' => $reply_list
            );
        } else {
            $data = array(
                'uid' => $post_data['uid'],
                'subject' => ($post_data['msg_type']==7)?$post_data['title'].' '.$post_data['object_no']:'',
                'username' => $this->hidePhoneNumber($post_data['username']),
                'img' => $this->getImgUrl($post_data['img']),
                'user_alias' => $post_data['child'],
                'dateline' => $post_data['dateline'],
                'like_number' => $post_data['like_number'],
                'reply_number' => $post_data['replay_number'],
                'message' => $post_data['msg'],
                'is_like' => $is_like,
                'like_list' => (isset($post_data['like_list']) && $post_data['like_list']) ? $post_data['like_list'] : array(),
                'link_name' => isset($post_data['object_data']['object_place']) ? $post_data['object_data']['object_place'] : $post_data['object_data']['object_title'],
                'star_num' => $post_data['star_num'],
                'accept' => (isset($post_data['accept']) && $post_data['accept']) ? 1 : 0,
                'reply_list' => $reply_list
            );
        }
        return $this->jsonResponse($data);
    }

    //发送附件
    public function upfileAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $post_params = array(
            'pid' => (int)$this->getParams('pid'),  //todo mongo _id 问题
            'content' => base64_decode($this->params()->fromPost('file')), //采用base64压缩，可以直接解码
        );


        /* $data = "/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEABALDA4MChAODQ4SERATGCgaGBYWGDEjJR0oOjM9PDkzODdASFxOQERXRTc4UG1RV19iZ2hnPk1xeXBkeFxlZ2MBERISGBUYLxoaL2NCOEJjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY//AABEIADIAMgMBEQACEQEDEQH/xAGiAAABBQEBAQEBAQAAAAAAAAAAAQIDBAUGBwgJCgsQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+gEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoLEQACAQIEBAMEBwUEBAABAncAAQIDEQQFITEGEkFRB2FxEyIygQgUQpGhscEJIzNS8BVictEKFiQ04SXxFxgZGiYnKCkqNTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqCg4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2dri4+Tl5ufo6ery8/T19vf4+fr/2gAMAwEAAhEDEQA/AJ9Z1G8i1e5SO6mRFbAVXIA4oAqDVL//AJ/J/wDvs0AI2qX/APz+z/8Afw0ARNq2of8AP7cf9/DQBE2sakOl/cf9/DQBC2s6mP8AmIXP/f00Aen27FreMkkkqCfyoA4HXjjW7v8A3/6CgBdK0q41QS/Z3jXy8Z3kjrn0B9KALsnhS/WNmMtudoJwGbP/AKDQBzrGgCFzQBA5oA9etv8Aj1h/3B/KgDz7xA2Ndu/9/wDoKANrwSS0V+BycJ/7NQBkTaNq0MLyyW7qiKWY7xwB170ATW+j219oEt5aySm6hB3xkgjjk44z06UAQXuk29joEN3cySi7n5jiBAAHXJ4z0/mKAOdc0wPYbX/j1h/3F/lSA858RtjX7z/f/oKANvwM37nUSOoCfyagDAl1vUZY2jkvJWRgQyluCD2oA2PCG+zgvdTncpaIm0j++Rzx9On40AJ43geZbXU4XMlq6BR6LnkH8f6UAca7UwPZbX/j1h/3F/lSA8z8TNjxDej/AG/6CmBBYaveaasq2k3liXAf5Qc4zjqPc0AUi9AFqXV7yTTlsGmH2VMEIFUe/JAyaAE/tu/XTTp/ng2pBGxkU989SM9aQGU70Ae12v8Ax6w/7i/yoA8w8UK//CR3uEYjf2HsKYGXtk/55v8AkaAArJ/zzb8jQAwrJ/zzf8jQBGySn/lm/wD3yaQDfJlP/LN/++TQB7Zag/ZYeP4F/lQA9o0LElFJ9xQAvlR/880/75FAB5Uf/PNP++RQAeVH/wA80/75FAB5Uf8AzzT/AL5FMA8qP/nmn/fIoAcBxSA//9k=";
         $img = base64_decode($data);
         $post_params['pid'] = 270;
         $post_params['content'] = $img;*/


        $post_data = $this->_getPlayPostTable()->get(array('pid' => $post_params['pid']));
        if (!$post_data) {
            return $this->jsonResponseError('对应的帖子不存在!');
        }
        $fileinfo = @getimagesizefromstring($post_params['content']);

        if (empty($fileinfo)) {
            return $this->jsonResponseError('服务器未取得图片');
        }
        //上传图片
        $filemime = in_array($fileinfo['mime'], array('image/gif', 'image/jpg', 'image/jpeg', 'image/png'));
        if (!$filemime) {
            return $this->jsonResponseError('不允许的附件类型');
        }
        switch ($fileinfo[2]) {
            case 1:
                $imginfo = '.gif';
                break;
            case 2:
                $imginfo = '.jpg';
                break;
            case 3:
                $imginfo = '.png';
                break;
            default:
                return $this->jsonResponseError('无法识别的附件类型');
        }
        //去除重复上传的图片
        $content_len = strlen($post_params['content']);
        $md5 = md5($post_params['pid'] . substr($post_params['content'], intval($content_len / 2), 300));
        $file_md5 = substr($md5, 8, 16) . $imginfo;
        $uploaded = RedCache::get($file_md5);
        if ($uploaded) {
            return $this->jsonResponse(array('status' => 1, 'message' => '重复的图片'));
        }
        $newfilename = date('His') . $file_md5;

        //图片路径

        $fiurl = $_SERVER['DOCUMENT_ROOT'] . $this->_getConfig()['upload_dir'];
        if (!is_dir($fiurl)) {
            mkdir($fiurl, 0777, true);
        }
        $file_dir = $fiurl . $newfilename;

        //图片压缩保存
        $file_put = imagejpeg(imagecreatefromstring($post_params['content']), $file_dir, 70);
        //保存原始图片
        //$file_put = file_put_contents($file_dir, $post_params['content']);

        //生成矩形缩略图
        $up_img = new ImageProcessing($file_dir);

        $up_img->MaxSquareZoomResizeImage(320)->Save($file_dir . '.thumb.jpg');

        if ($file_put) {
            //存储到,数据表
            $file_insert = $this->_getPlayAttachTable()->insert(array(
                    'uid' => $post_data->uid,
                    'use_id' => $post_params['pid'],
                    'use_type' => 'post', 'dateline' => time(),
                    'url' => $this->_getConfig()['upload_dir'] . $newfilename,
                    'is_remote' => 0,
                    'name' => '',
                    'width' => $fileinfo[0],
                    'height' => $fileinfo[1]
                )
            );

            if ($post_data->photo_number < 10) {
                $photo_list = RedCache::get('post_' . $post_params['pid']) ? json_decode(RedCache::get('post_' . $post_params['pid']), true) : [];
                $photo_list[] = $this->_getConfig()['upload_dir'] . $newfilename;
                RedCache::set('post_' . $post_params['pid'], json_encode($photo_list), 1800);

                $this->_getPlayPostTable()->update(array('photo_number' => new Expression('photo_number+1'), 'photo_list' => RedCache::get('post_' . $post_params['pid'])), array('pid' => $post_params['pid']));
            }
        } else {
            return $this->jsonResponseError('存储图片失败');
        }

        if ($file_put and $file_insert) {
            // 在规定的时间内可以避免重复上传一张图片
            RedCache::set($file_md5, true, 60 * 60 * 24);
            return $this->jsonResponse(array('status' => 1, 'message' => '上传成功', 'url' => $this->_getConfig()['upload_dir'] . $newfilename));
        } else {
            return $this->jsonResponseError('上传失败');
        }
    }

    //评论列表
    public function postlistAction()
    {
        if (!$this->pass(false)) {
            return $this->failRequest();
        }

        $id = (int)$this->getParams('object_id');
        $type = (int)$this->getParams('type');  //消息类型 1圈子 2评论商品 3评论游玩地 4评论商家 5评论专题 6团购分享 7活动',
        $uid = (int)$this->getParams('uid');  //可选
        $mid = $this->getParams('last_id', 0);  //mid
        $eid = (int)$this->getParams('eid', 0);  //eid 活动的场次id
        $pagesum = (int)$this->getParams('pagenum', 10);
        $page = (int)$this->getParams('page',1);//分页参数
        $offset = ($page-1)*$pagesum;
        $page_sum = $pagesum*$page;

//        if ($mid and !$this->checkMid($mid)) {
//            return $this->jsonResponseError('参数错误');
//        }


        if (!$id and (int)$type !== 7) {
            return $this->jsonResponseError('参数错误');
        }

        if((int)$type === 7 and (!$id and !$eid)){
            Logger::writeLog("获取评论列表数据失败, 对象id{$id},对象类型:{$type},场次id{$eid}".print_r($_SERVER,true));
            return $this->jsonResponseError('参数错误2');
        }

        if (!in_array($type, array(2, 3, 4, 5, 6, 7))) {
            return $this->jsonResponseError('参数错误3');
        }


        if ($type == 5) { //专题
            $Where = array(
                'play_coupons.coupon_status = ?' => 1,
                'play_activity_coupon.aid' => $id,
                'play_activity_coupon.type' => 'coupon'
            );
            $newsWhere = array(
                'play_activity_coupon.aid' => $id,
                'play_activity_coupon.type' => 'news',
                'play_news.status' => 1

            );
            //专题下的卡券
            $coupon_res = $this->_getPlayActivityCouponTable()->getApiCouponList(0, 100, array('play_activity_coupon.cid'), $Where);
            //专题下的资讯
            $news_res = $this->_getPlayActivityCouponTable()->getApiNewsList(0, 100, array('play_activity_coupon.cid'), $newsWhere);

            foreach ($coupon_res as $v) {
                $coupon_id_list[] = (int)$v->cid;
            }
            foreach ($news_res as $v) {
                $news_id_list[] = $v->cid;
            }

            $where = array('$or' => array(array('msg_type' => 5, 'object_data.object_id' => (int)$id), array('msg_type' => 2, 'object_data.object_id' => array('$in' => $coupon_id_list))), 'status' => array('$gt' => 0));

//            if ($mid and $this->checkMid($mid)) {
//                $where['_id'] = array('$gt' => new \MongoId($mid));
//            }


            $res = $this->_getMdbSocialCircleMsg()->find($where)->sort(array('dateline' => -1,'status' => -1))->limit($pagesum)->skip($offset);


        } elseif ($type == 7){
//            $edata = $this->_getPlayExcerciseEventTable()->getEventList(0, 100, array(),
//                array('play_excercise_event.id' => $id,
//                'play_excercise_base.release_status >= ?' => 1));

            if($eid){
                //$where = array('$or' => array(array('msg_type' => 7, 'object_data.object_id' => (int)$eid)), 'status' => array('$gt' => 0));
                $where = array(
                    'msg_type' => 7,
                    'object_data.object_id' => (int)$eid,
                    'status' => array('$gt' => 0)
                );
            }else{
                //$where = array('$or' => array(array('msg_type' => 7,'object_data.object_bid'=> (int)$id)), 'status' => array('$gt' => 0));
                $where = array(
                    'msg_type' => 7,
                    'object_data.object_bid' => (int)$id,
                    'status' => array('$gt' => 0)
                );
            }

//            if ($mid and $this->checkMid($mid)) {
//                $last_data=$this->_getMdbSocialCircleMsg()->findOne(array('_id'=>new \MongoId($mid)));  //处理使用last_id分页时 数据重复问题
//                $where['_id'] = array('$lt' => new \MongoId($mid));
//                $where['status'] = array('$lte' => $last_data['status']);
//                $where['like_number'] = array('$lte' => $last_data['like_number']);
//            }

            $res = $this->_getMdbSocialCircleMsg()->find($where)->sort(array('dateline' => -1,'status' => -1,'_id' => -1))->limit($pagesum)->skip($offset);
        } elseif ($type == 4) { //商家

            //活动
            $game_id_list = array();
            $gameData = $this->_getPlayOrganizerGameTable()->fetchLimit(0, 100, array(), array('organizer_id' => $id, 'status > ?' => 0, 'start_time < ?' => time(), 'end_time > ?' => time()));
            foreach ($gameData as $gValue) {
                $game_id_list[] = (int)$gValue->id;
            }

            $where = array('$or' => array(array('msg_type' => 2, 'object_data.object_id' => array('$in' => $game_id_list)), array('msg_type' => 4, 'object_data.object_id' => (int)$id)), 'status' => array('$gt' => 0));

//            if ($mid and $this->checkMid($mid)) {
//                $where['_id'] = array('$lt' => new \MongoId($mid));
//            }
            $res = $this->_getMdbSocialCircleMsg()->find($where)->sort(array('status' => -1, 'dateline' => -1))->limit($pagesum)->skip($offset);

        } elseif ($type == 3) {//评论游玩地


            //游玩地下所有卡券
            $timer = time();
            // $good_where = "play_game_info.shop_id = {$id} AND  play_organizer_game.end_time >= {$timer} AND play_organizer_game.start_time <= {$timer} AND play_organizer_game.status > 0";
            $good_where = "play_game_info.shop_id = {$id}";

            $good_sql = "SELECT
play_shop.label_id,
play_organizer_game.id AS gid,
play_organizer_game.thumb,
play_organizer_game.title,
play_organizer_game.low_price,
play_organizer_game.ticket_num,
play_organizer_game.buy_num,
play_organizer_game.foot_time,
play_organizer_game.is_together
FROM
play_shop
LEFT JOIN play_game_info ON play_shop.shop_id = play_game_info.shop_id
LEFT JOIN play_organizer_game ON play_game_info.gid = play_organizer_game.id
WHERE
$good_where
GROUP BY
play_organizer_game.id";

            $good_data = $this->query($good_sql);
            $game_id_list = array();

            foreach ($good_data as $g_data) {
                $game_id_list[] = (int)$g_data['gid'];
            }


            $where = array('$or' => array(array('msg_type' => 2, 'object_data.object_shop_id' => (int)$id), array('msg_type' => 3, 'object_data.object_id' => (int)$id)), 'status' => array('$gt' => 0));

//            if ($mid and $this->checkMid($mid)) {
//                $last_data=$this->_getMdbSocialCircleMsg()->findOne(array('_id'=>new \MongoId($mid)));  //处理使用last_id分页时 数据重复问题
//                $where['_id'] = array('$lt' => new \MongoId($mid));
//                $where['status'] = array('$lte' => $last_data['status']);
//                $where['like_number'] = array('$lte' => $last_data['like_number']);
//
//            }
            $res = $this->_getMdbSocialCircleMsg()->find($where)->sort(array('status' => -1,'dateline' => -1,'id' => -1))->limit($pagesum)->skip($offset);

        } elseif ($type == 2) { //商品 测试通过

            $shop_data = $this->_getPlayGameInfoTable()->getApiGameShopList(0, 100, array(), array('play_game_info.gid' => $id, 'play_game_info.status >= ?' => 1));
            $shop_id_list = array();
            foreach ($shop_data as $sData) {
                $shop_id_list[] = (int)$sData->shop_id;
            }

            $all_ids = [];//所有商品的id
            if(count($shop_id_list)){
                $allinfo = $this->_getPlayGameInfoTable()->fetchAll(['shop_id'=>$shop_id_list],[],300)->toArray();
                if(count($allinfo)){
                    $all_ids = [];//所有商品的id
                    foreach($allinfo as $a){
                        if(!in_array((int)$a['gid'],$all_ids)){
                            $all_ids[] = (int)$a['gid'];
                        }
                    }
                    if(!count($all_ids)){
                        $all_ids = [];
                    }
                }
            }

            $where = array(
                '$or' => array(
                    array('msg_type' => 2, 'object_data.object_id' => (int)$id),
                    array('msg_type' => 3, 'object_data.object_id' => array('$in' => $shop_id_list)),
                    array('msg_type' => 2, 'object_data.object_id' => array('$in' => $all_ids)),
                    array('shop_id' => array('$in' => $shop_id_list))
                ),
                'status' => array('$gt' => 0)
            );

//            if ($mid and $this->checkMid($mid)) {
//                $last_data=$this->_getMdbSocialCircleMsg()->findOne(array('_id'=>new \MongoId($mid)));  //处理使用last_id分页时 数据重复问题
//                $where['_id'] = array('$lt' => new \MongoId($mid));
//                $where['status'] = array('$lte' => $last_data['status']);
//                $where['like_number'] = array('$lte' => $last_data['like_number']);
//            }
            $res = $this->_getMdbSocialCircleMsg()->find($where)->sort(array('status' => -1, 'dateline' => -1,'id' => -1))->limit($pagesum)->skip($offset);

        } else {
            $where = array('msg_type' => $type, 'object_data.object_id' => (int)$id, 'status' => array('$gt' => 0));
//            if ($mid and $this->checkMid($mid)) {
//
//                $last_data=$this->_getMdbSocialCircleMsg()->findOne(array('_id'=>new \MongoId($mid)));
//                if($last_data){
//                    $where['_id'] = array('$lt' => $last_data['dateline']);
//                }
//
//                //$where['_id'] = array('$lt' => new \MongoId($mid));
//            }
            $res = $this->_getMdbSocialCircleMsg()->find($where)->sort(array('status' => -1, '_id' => -1))->limit($pagesum)->skip($offset);
        }

        $data = array('post' => array());
        foreach ($res as $v) {

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

            $data['post'][] = array(
                'id' => (string)$v['_id'],
                'uid' => $v['uid'],
                //'replypid' => $v['replypid'],
                'score' => $v['star_num'] ? $v['star_num'] : 0,
                'like_number' => $v['like_number'],
                'reply_number' => $v['replay_number'],
                'user_detail' => $v['child'],
                'is_like' => $is_like,
                'author' => $this->hidePhoneNumber($v['username']),
                //'object_id' => $v['object_data']['object_id'],
                'link_name' => isset($post_data['object_data']['object_place']) ? $post_data['object_data']['object_place'] : '',
                'author_img' => $this->getImgUrl($v['img']),
                'dateline' => $v['dateline'],
                'message' => $v['msg'],
                'no'=>$v['object_data']['object_no']?:0,
                'event_name'=>($v['object_data']['object_title']?:'').($type==7?(' 第'.($v['object_data']['object_no']?:'1').'期') :''),
                'accept' => (isset($v['accept']) && $v['accept']) ? 1 : 0,
                'reply_list' => $v['posts']
            );
        }

        return $this->jsonResponse($data);

    }

    //评论点赞
    public function postlikeAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $pid = $this->getParams('pid');//评论id  mongo _id
        $uid = (int)$this->getParams('uid');
        $city = $this->getCity();

        if(!$this->checkMid($pid)){
            return $this->jsonResponse(array('status' => 0, 'message' => '该评论不存在'));
        }

        $res = $this->_getMdbSocialPrise()->findOne(array('uid' => $uid, 'type' => 1, 'object_id' => (string)$pid));

        if ($res) {
            return $this->jsonResponse(array('status' => 1, 'message' => '你已经点赞了'));
        }
        //mongodb
        $data = array(
            'uid' => $uid, //用户id
            'type' => 1, //类型 圈子消息
            'object_id' => (string)$pid,//点赞对象id
            'dateline' => time()
        );
        $this->_getMdbSocialPrise()->insert($data);
        $status = $this->_getMdbSocialCircleMsg()->update(array('_id' => new \MongoId($pid)), array('$inc' => array('like_number' => 1)));

        $user_data = $this->_getPlayUserTable()->get(array('uid' => $uid));

        $arr = $this->_getMdbSocialCircleMsg()->findOne(array('_id' => new \MongoId($pid)));

        if ($arr) {
            // 从头部追加
            if (!array_key_exists('like_list', $arr)) {
                $arr['like_list'] = array();
            }
            // 数量限制
            if (count($arr['like_list']) < 10) {
                array_unshift($arr['like_list'], array('uid' => $uid, 'username' => $user_data->username, 'img' => $this->getImgUrl($user_data->img), 'dateline' => time()));
                $this->_getMdbSocialCircleMsg()->update(array('_id' => new \MongoId($pid)), array('$set' => array('like_list' => $arr['like_list'])));
            }
        } else {
            return $this->jsonResponseError('内容不存在');
        }

        //消息类型 1圈子 2评论商品 3评论游玩地 4评论商家 5评论专题 6团购分享 7活动

        if ($arr['msg_type'] == 2) {
            $integral = new Integral();
            $integral->good_comment_prize_integral($arr['uid'], $pid, $city,$arr['object_data']['object_id']);
        } elseif ($arr['msg_type'] == 3) {
            $integral = new Integral();
            $integral->place_comment_prize_integral($arr['uid'], $pid, $city,$arr['object_data']['object_id']);
        }

        if ($status) {
            $msg_data = $arr;

            // 评论用户的用户信息
            $data_comment_user = $this->_getPlayUserTable()->get(array('uid' => $msg_data['uid']));

            $data_message_type = 16; // 消息类型为评论回复
            $data_inform_type  = 12; // 评论回复推送

            // 评论回复推送内容
            $data_name = $msg_data['object_data']['object_title'];
            if (!empty($data_name)) {
                $data_name = '对' . $data_name;
            } else {
                $data_name = '';
            }
            $data_inform = "【玩翻天】您" . $data_name . "的评论有新回复啦，快来看看吧！";

            // 评论回复系统消息
            $data_message = "您" . $data_name . "的评论有新回复啦，快来看看吧！";

            $data_link_data = array(
                'mid'       => (string)$msg_data['_id'],
                'reply'     => (string)$data['_id'],
                'type'      => 1,                               // 0为文字回复， 1为点赞
                'reply_uid' => $uid,
                'from_uid'  => $msg_data['uid'],
            );

            $class_sendMessage = new SendMessage();
            $class_sendMessage->sendMes($msg_data['uid'], $data_message_type, '您的评论有新回复啦', $data_message, $data_link_data);
            $class_sendMessage->sendInform($msg_data['uid'], $data_comment_user->token, $data_inform, $data_inform, '', $data_inform_type, $msg_data['_id']);

            return $this->jsonResponse(array('status' => 1, 'message' => '点赞成功'));
        } else {

            return $this->jsonResponseError('点赞失败');
        }
    }

    //删除评论
    public function deletepostAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = $this->getParams('uid');
        $pid = $this->getParams('pid');  //mongo _id

        if (!$pid or !$this->checkMid($pid)) {
            return $this->jsonResponseError('参数错误');
        }
        $data = $this->_getMdbSocialCircleMsg()->findOne(array('_id' => new \MongoId($pid)));

        if ($data['uid'] != $uid) {
            return $this->jsonResponseError('没有删除权限');
        }
        //mongodb
        $s1 = $this->_getMdbSocialCircleMsg()->remove(array('_id' => new \MongoId($pid)));


        //  回复数 与评论数处理
//        1圈子 2评论商品 3评论游玩地 4评论商家 5评论专题',

        if ($data['type'] == 3) {
            $this->_getPlayShopTable()->update(array('post_number' => new Expression('post_number-1')), array('shop_id' => $data['object_data']['object_id']));
        } elseif ($data['type'] == 4) {
            $this->_getPlayOrganizerTable()->update(array('post_number' => new Expression('post_number-1')), array('id' => $data['object_data']['object_id']));
        } elseif ($data['type'] == 2) {
            $this->_getPlayOrganizerGameTable()->update(array('post_number' => new Expression('post_number-1')), array('id' => $data['object_data']['object_id']));
        }


        if ($s1) {
            return $this->jsonResponse(array('status' => 1, 'message' => '删除成功'));
        } else {
            return $this->jsonResponseError('删除失败');
        }
    }

    //取消点赞
    public function removelikeAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $pid = $this->getParams('pid');//评论id mongo _id
        $uid = (int)$this->getParams('uid');


        if (!$pid or !$uid or !$this->checkMid($pid)) {
            return $this->jsonResponseError('参数错误');
        }
        $data = $this->_getMdbSocialPrise()->findOne(array(
            'uid' => $uid, //用户id
            'type' => 1, //类型 圈子消息
            'object_id' => (string)$pid //点赞对象id
        ));

        if (!$data) {
            return $this->jsonResponseError('你还没有点赞');
        }

        //mongodb
        $arr = $this->_getMdbSocialCircleMsg()->findOne(array('_id' => new \MongoId($pid)));
        // 删除点赞信息
        foreach ($arr['like_list'] as $k => $v) {
            if ($v['uid'] == $uid) {
                unset($arr['like_list'][$k]);
                continue;
            }
        }

        $s1 = $this->_getMdbSocialCircleMsg()->update(array('_id' => new \MongoId($pid)), array('$inc' => array('like_number' => -1), '$set' => array('like_list' => $arr['like_list'])));


        $this->_getMdbSocialPrise()->remove(array(
            'uid' => $uid, //用户id
            'type' => 1, //类型 圈子消息
            'object_id' => (string)$pid //点赞对象id
        ));


        if ($s1) {

            return $this->jsonResponse(array('status' => 1, 'message' => '取消点赞成功'));
        } else {

            return $this->jsonResponseError('取消点赞失败');
        }
    }

    //获取历史购买
    public function gethistAction()
    {


        if (!$this->pass()) {
            return $this->failRequest();
        }
        $uid = $this->getParams('uid');
        $coupon_id = $this->getParams('coupon_id');
        if (!$uid or !$coupon_id) {
            return $this->jsonResponseError('参数错误');
        }

        //查询是否购买过
        $data = $this->_getPlayOrderInfoTable()->fetchAll(array('coupon_id' => $coupon_id, 'order_status' => 1), array('dateline' => 'desc'), 1)->current();

        if ($data) {
            $shop_data = $this->_getPlayShopTable()->get(array('shop_id' => $data->shop_id));
            return $this->jsonResponse(array(
                'shop_id' => $shop_data->shop_id,
                'shop_name' => $shop_data->shop_name
            ));

        } else {
            return $this->jsonResponse(array());
        }

    }

    //获取评论时的默认参数
    public function comparamAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $id = $this->getParams('id', 0);
        $type = $this->getParams('type', 2); //1游玩地 2商品


        if (!$id) {
            return $this->jsonResponseError('参数错误');
        }

        $post_word = NULL;

        if ($type == 1) {
            $placeData = $this->_getPlayShopTable()->get(array('shop_id' => $id));

            if ($placeData && $placeData->post_area_word) {
                $post_word = $placeData->post_area_word;
            }
        }

        if ($type == 2) {
            $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $id));

            if ($goodData && $goodData->post_area_word) {
                $post_word = $goodData->post_area_word;
            }
        }


        if (!$post_word) {
            $double_data = $this->_getPlayWelfareIntegralTable()->get(array('object_id' => $id, 'object_type' => $type, 'welfare_type'=>3));

            $double_num = $double_data->double ? $double_data->double : 0;

            $IntegralSetting = $this->getMarketSetting();

            if ($type == 1) {
                $integral_num = $IntegralSetting['place_comment_integral']*$double_num;
            }

            if ($type == 2) {
                $integral_num = $IntegralSetting['good_comment_integral']*$double_num;
            }

            if (!$integral_num) {
                $post_word = '提交15个字并带有插图的评论，有机会获得积分哦';
            } else {
                $post_word = '提交15个字并带有插图的评论，会获得'. $integral_num .'个积分';
            }
        }

        $data = array(
            'post_area_word' => $post_word,
        );

        return $this->jsonResponse($data);
    }

    public function postReplyListAction () {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $param['uid']      = $this->getParams('uid');
        $param['page_num'] = (int)$this->getParams('page_num', 10);
        $param['page']     = (int)$this->getParams('page',1);        //分页参数
        $param['offset']   = ($param['page'] - 1) * $param['page_num'];
        $mdb_param = array(
            'from_uid' => $param['uid'],
            'status'   => array('$gt' => 0),
        );

        $pdo       = $this->_getAdapter();

        $sql       = " SELECT * FROM play_user_message WHERE uid = ? AND type = 16 ORDER BY deadline DESC LIMIT ?,? ";
        $sql_param = array(
            $param['uid'],
            $param['offset'],
            $param['page_num']
        );
        $data_messages = $pdo->query($sql, $sql_param);

        $sql           = " UPDATE play_user_message SET is_new = 0 WHERE uid = ? AND type = 16 ";
        $sql_param     = array(
            $param['uid'],
        );
        $data_update_status = $pdo->query($sql, $sql_param) ;

        if ($data_messages) {
            $data_social_circle_msgs = array();
            $data_return = array();
            foreach ($data_messages as $key => $val) {
                $data_link_data = json_decode($val->link_id);

                if ($data_link_data) {
                    // 获取评论相关信息
                    if (empty($data_social_circle_msgs[$data_link_data->mid])) {
                        $mdb_param_social_circle_msg = array(
                            '_id' => new \MongoId($data_link_data->mid)
                        );
                        $data_social_circle_msgs[$data_link_data->mid] = $this->_getMdbSocialCircleMsg()->findOne($mdb_param_social_circle_msg);
                    }

                    // 进行评论内容的拼接
                    $data_temp_message = '';

                    if ($data_social_circle_msgs[$data_link_data->mid]['msg']) {
                        foreach ($data_social_circle_msgs[$data_link_data->mid]['msg'] as $tempp => $tempq) {
                            if ($tempq['t'] == 1) {
                                $data_temp_message .= $tempq['val'] . '。';
                            }
                        }
                    }



                    if ($data_link_data->type == 0) {
                        // 为评论回复
                        // 获取回复内容
                        $mdb_param_social_circle_msg_post = array(
                            '_id' => new \MongoId($data_link_data->reply)
                        );
                        $data_social_circle_msg_post = $this->_getMdbSocialCircleMsgPost()->findOne($mdb_param_social_circle_msg_post);

                        // 进行回复内容的拼接
                        $data_temp_reply = '';

                        if ($data_social_circle_msg_post['msg']) {
                            foreach ($data_social_circle_msg_post['msg'] as $tempp => $tempq) {
                                if ($tempq['t'] == 1) {
                                    $data_temp_reply .= $tempq['val'] . '。';
                                }
                            }
                        }

                        $data_return[] = array(
                            "id"          => (string)$data_social_circle_msgs[$data_link_data->mid]['_id'],
                            "uid"         => $data_social_circle_msg_post['uid'],
                            "username"    => $this->hidePhoneNumber($data_social_circle_msg_post['username']),
                            "avatar"      => $this->getImgUrl($data_social_circle_msg_post['img']),
                            "dateline"    => $data_social_circle_msg_post['dateline'],
                            "type"        => $data_link_data->type,
                            "reply"       => $data_temp_reply,
                            "message"     => $data_temp_message,
                            "object_type" => $data_social_circle_msgs[$data_link_data->mid]['msg_type'],
                            "object_data" => array(
                                "object_id"    => $data_social_circle_msgs[$data_link_data->mid]['object_data']['object_id'],
                                "object_title" => $data_social_circle_msgs[$data_link_data->mid]['object_data']['object_title'],
                                "object_star"  => $data_social_circle_msgs[$data_link_data->mid]['star_num'],
                                "object_img"   => $data_social_circle_msgs[$data_link_data->mid]['object_data']['object_img']
                            )
                        );
                    } elseif ($data_link_data->type == 1) {
                        // 为评论点赞
                        $data_reply_user = $this->_getPlayUserTable()->get(array('uid' => $data_link_data->reply_uid));

                        $data_return[] = array(
                            "id"          => (string)$data_social_circle_msgs[$data_link_data->mid]['_id'],
                            "uid"         => $data_link_data->reply_uid,
                            "username"    => $this->hidePhoneNumber($data_reply_user->username),
                            "avatar"      => $this->getImgUrl($data_reply_user->img),
                            "dateline"    => $val->deadline,
                            "type"        => $data_link_data->type,
                            "reply"       => '',
                            "message"     => $data_temp_message,
                            "object_type" => $data_social_circle_msgs[$data_link_data->mid]['msg_type'],
                            "object_data" => array(
                                "object_id"    => $data_social_circle_msgs[$data_link_data->mid]['object_data']['object_id'],
                                "object_title" => $data_social_circle_msgs[$data_link_data->mid]['object_data']['object_title'],
                                "object_star"  => $data_social_circle_msgs[$data_link_data->mid]['star_num'],
                                "object_img"   => $data_social_circle_msgs[$data_link_data->mid]['object_data']['object_img']
                            )
                        );
                    }
                }
            }
        }

        $data_return = array(
            'reply' => $data_return
        );

        return $this->jsonResponse($data_return);
    }

    function query($sql)
    {
        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $stmt = $db->query($sql);
        $result = $stmt->execute($stmt);
        return $result;
    }
}
