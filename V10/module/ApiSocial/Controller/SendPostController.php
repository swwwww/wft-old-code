<?php
namespace ApiSocial\Controller;

use Deyi\BaseController;
use Deyi\GeTui\GeTui;
use Deyi\ImageProcessing;
use library\Service\System\Cache\RedCache;
use Deyi\Social\SendSocialMessage;
use Zend\Db\Sql\Expression;
use Zend\Log\Writer\MongoDB;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Response;
use Deyi\JsonResponse;
use Deyi\Integral\Integral;

class SendPostController extends AbstractActionController
{
    use BaseController;
    use JsonResponse;
    use SendSocialMessage;

    /**
     * @return 圈子中发言
     */
    public function indexAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }
        $uid = (int)$this->getParams('uid');
        $cid = $this->getParams('cid', 0);
        $title = $this->getParams('title', '', false);
        $content = $this->getParams('content', '', false); //json 内容
        $msg_type = $this->getParams('msg_type', 1);// 消息类型 1圈子 2评论商品 3评论游玩地 4评论商家 5评论专题  6团购分享,
        $object_id = $this->getParams('object_id', 0);  //未使用
        $group_buy_id = (int)$this->getParams('group_buy_id', 0);  //团购id
        $share_user_list = $this->getParams('share_user_list', '[]', false);//json [111,222]  //分享给好友
        $addr_x = $this->getParams('addr_x'); //经
        $addr_y = $this->getParams('addr_y');//纬度
        $city = $this->getCity();
        if (!$uid) {
            return $this->jsonResponseError('请先登录!');
        }

        //频率限制
        $rt=RedCache::get('P:Social'.$uid);
        if($rt){
            return $this->jsonResponseError('频率过高');
        }else{
            RedCache::set('P:Social'.$uid,1,10); //10秒限制
        }

        if (!$cid and ($share_user_list == '[]' or !$share_user_list)) {
            return $this->jsonResponseError('圈子或用户未选择');
        }

        if (!$content) {
            return $this->jsonResponseError('内容不能为空');
        } else {
            $content_arr = json_decode($content, true);
            if (empty($content_arr)) {
                return $this->jsonResponseError('内容不能为空');
            }
        }


        if ($cid) {
            $flag = $this->_getMdbSocialCircleUsers()->findOne(array('uid' => $uid, 'cid' => $cid));
            if ($flag['status'] === 0) {
                return $this->jsonResponseError('你已经被禁言');
            }
        }

        $res = $this->SendSocialMessage($uid, $cid, $title, $content, $msg_type, $object_id, $addr_x, $addr_y, 0, $group_buy_id, json_decode($share_user_list, true),0,$city);
        //发言积分奖励



        if(isset($res['lastid'])){

            //$message = json_decode($content);//限制评论奖励条件
            $len = $hsimg = 0;
            foreach($content as $m){
                if($m['t'] == 1){
                    $len = mb_strlen($m['val']);
                }
                if($m['t'] == 2){
                    $hsimg = 1;
                }
            }
            if($len >= 30 && $hsimg){
                $integral = new Integral();
                $integral->circle_speak($uid,$res['lastid'], $city);
            }

        }

        return $this->jsonResponse($res);

    }


    /**
     * @return 删除发言
     */
    public function deleteAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }
        $uid = (int)$this->getParams('uid');
        $mid = $this->getParams('mid');
        $city = $this->getCity();

        if (!$uid or !$mid) {
            return $this->jsonResponseError('参数错误');
        }
        if(!$this->checkMid($mid)){
            return $this->jsonResponseError('数据不存在!');
        }


        $social_circle = $this->_getMdbSocialCircle();
        $social_circle_msg = $this->_getMdbsocialCircleMsg();

        $data = $social_circle_msg->findOne(array('_id' => new \MongoId($mid), 'uid' => $uid));
        if (!$data) {
            return $this->jsonResponseError('消息不存在,或无权限删除');
        }

        $state = $social_circle_msg->remove(array('_id' => new \MongoId($mid), 'uid' => $uid));
        if ($state) {

            if ($data['cid']) {
                $social_circle->update(array('_id' => new \MongoId($data['cid'])), array('$inc' => array('msg' => -1, 'today_msg' => -1)));
            }
            $this->_getPlayUserTable()->update(array('circle_msg' => new Expression('circle_msg-1')), array('uid' => $uid));

            $integral = new Integral();
            $integral->circle_speak_delete($data['uid'],$mid, $city);
            return $this->jsonResponse(array('status' => 1, 'message' => '删除成功'));
        } else {
            return $this->jsonResponseError('删除失败');
        }
    }

    /**
     * @return 点赞
     */
    public function likeAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }
        $uid = (int)$this->getParams('uid');
        $mid = $this->getParams('mid');
        $city = $this->getCity();

        if (!$uid or !$mid) {
            return $this->jsonResponseError('参数错误');
        }

        if(!$this->checkMid($mid)){
            return $this->jsonResponseError('数据不存在!');
        }

        $social_circle_msg = $this->_getMdbsocialCircleMsg();
        $social_prise = $this->_getMdbsocialPrise();

        $data = $social_circle_msg->findOne(array('_id' => new \MongoId($mid)));
        $like_data = $social_prise->findOne(array('uid' => $uid, 'type' => 1, 'object_id' => $mid));

        if (!$data) {
            return $this->jsonResponseError('消息不存在');
        }

        if ($like_data) {
            return $this->jsonResponse(array('status' => 1, 'message' => '已经点赞过了'));
        }

        $state = $social_circle_msg->update(array('_id' => new \MongoId($mid)), array('$inc' => array('like_number' => 1)));

        $social_prise->insert(array(
            'uid' => $uid, //用户id
            'type' => 1, //类型 圈子消息
            'object_id' => $mid,//点赞对象id
            'dateline' => time(),
            'city' => $city,
        ));
        if ($state) {
            //点赞积分奖励
            $integral = new Integral();
            $integral->circle_prize_integral($data['uid'],$mid, $city);
            return $this->jsonResponse(array('status' => 1, 'message' => '点赞成功'));
        } else {
            return $this->jsonResponseError('点赞失败');
        }

    }

    /**
     * @return 取消点赞
     */
    public function deletelikeAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }
        $uid = (int)$this->getParams('uid');
        $mid = $this->getParams('mid');

        if (!$uid or !$mid) {
            return $this->jsonResponseError('参数错误');
        }

        if(!$this->checkMid($mid)){
            return $this->jsonResponseError('数据不存在!');
        }


        $social_circle_msg = $this->_getMdbsocialCircleMsg();
        $social_prise = $this->_getMdbsocialPrise();

        $data = $social_circle_msg->findOne(array('_id' => new \MongoId($mid)));
        $like_data = $social_prise->findOne(array('uid' => $uid, 'type' => 1, 'object_id' => $mid));

        if (!$data) {
            return $this->jsonResponseError('消息不存在');
        }

        if (!$like_data) {
            return $this->jsonResponse(array('status' => 1, 'message' => '已经取消过了'));
        }

        $state = $social_circle_msg->update(array('_id' => new \MongoId($mid)), array('$inc' => array('like_number' => -1)));

        $social_prise->remove(array(
            'uid' => $uid, //用户id
            'type' => 1, //类型 圈子消息
            'object_id' => $mid//点赞对象id
        ));
        if ($state) {
            return $this->jsonResponse(array('status' => 1, 'message' => '取消点赞成功'));
        } else {
            return $this->jsonResponseError('取消点赞失败');
        }

    }

    /**
     * @return 上传发言图片 所有需要上传图片接口可重用此接口
     */
    public function upImgAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $data = $this->params()->fromPost('file');
        if (!$data) {
            return $this->jsonResponseError('内容为空');
        }
        $data = base64_decode($data); //采用base64压缩，可以直接解码


        /* $data = "/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEABALDA4MChAODQ4SERATGCgaGBYWGDEjJR0oOjM9PDkzODdASFxOQERXRTc4UG1RV19iZ2hnPk1xeXBkeFxlZ2MBERISGBUYLxoaL2NCOEJjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY//AABEIADIAMgMBEQACEQEDEQH/xAGiAAABBQEBAQEBAQAAAAAAAAAAAQIDBAUGBwgJCgsQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+gEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoLEQACAQIEBAMEBwUEBAABAncAAQIDEQQFITEGEkFRB2FxEyIygQgUQpGhscEJIzNS8BVictEKFiQ04SXxFxgZGiYnKCkqNTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqCg4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2dri4+Tl5ufo6ery8/T19vf4+fr/2gAMAwEAAhEDEQA/AJ9Z1G8i1e5SO6mRFbAVXIA4oAqDVL//AJ/J/wDvs0AI2qX/APz+z/8Afw0ARNq2of8AP7cf9/DQBE2sakOl/cf9/DQBC2s6mP8AmIXP/f00Aen27FreMkkkqCfyoA4HXjjW7v8A3/6CgBdK0q41QS/Z3jXy8Z3kjrn0B9KALsnhS/WNmMtudoJwGbP/AKDQBzrGgCFzQBA5oA9etv8Aj1h/3B/KgDz7xA2Ndu/9/wDoKANrwSS0V+BycJ/7NQBkTaNq0MLyyW7qiKWY7xwB170ATW+j219oEt5aySm6hB3xkgjjk44z06UAQXuk29joEN3cySi7n5jiBAAHXJ4z0/mKAOdc0wPYbX/j1h/3F/lSA858RtjX7z/f/oKANvwM37nUSOoCfyagDAl1vUZY2jkvJWRgQyluCD2oA2PCG+zgvdTncpaIm0j++Rzx9On40AJ43geZbXU4XMlq6BR6LnkH8f6UAca7UwPZbX/j1h/3F/lSA8z8TNjxDej/AG/6CmBBYaveaasq2k3liXAf5Qc4zjqPc0AUi9AFqXV7yTTlsGmH2VMEIFUe/JAyaAE/tu/XTTp/ng2pBGxkU989SM9aQGU70Ae12v8Ax6w/7i/yoA8w8UK//CR3uEYjf2HsKYGXtk/55v8AkaAArJ/zzb8jQAwrJ/zzf8jQBGySn/lm/wD3yaQDfJlP/LN/++TQB7Zag/ZYeP4F/lQA9o0LElFJ9xQAvlR/880/75FAB5Uf/PNP++RQAeVH/wA80/75FAB5Uf8AzzT/AL5FMA8qP/nmn/fIoAcBxSA//9k=";
         $img = base64_decode($data);*/


        $fileinfo = @getimagesizefromstring($data);

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
                return $this->jsonResponseError(array('status' => 0, 'message' => '无法识别的附件类型'));
        }
        //去除重复上传的图片
        $file_md5 = substr(md5($data), 0, 10) . rand(1000, 9999);
        //  $uploaded = RedCache::get('upload_' . $file_md5);
//        if ($uploaded) {
//            return $this->jsonResponse(array('status' => 1, 'message' => '重复的图片'));
//        }

        $newfilename = date('YmdHis') . $file_md5 . $imginfo;

        //图片路径

        $fiurl = $_SERVER['DOCUMENT_ROOT'] . $this->_getConfig()['upload_dir'];
        if (!is_dir($fiurl)) {
            mkdir($fiurl, 0777, true);
        }
        $file_dir = $fiurl . $newfilename;

        //图片压缩保存
        $file_put = imagejpeg(imagecreatefromstring($data), $file_dir, 70);
        //保存原始图片
        //$file_put = file_put_contents($file_dir, $data);

        //生成矩形缩略图
        $up_img = new ImageProcessing($file_dir);

        $up_img->MaxSquareZoomResizeImage(150)->Save($file_dir . '.thumb.jpg');


        if ($file_put) {
            // 在规定的时间内可以避免重复上传一张图片
            RedCache::set('upload_' . $file_md5, true, 60 * 10);
            return $this->jsonResponse(array('status' => 1, 'url' => $this->_getConfig()['upload_dir'] . $newfilename));
        } else {
            return $this->jsonResponseError('存储图片失败');
        }
    }


}