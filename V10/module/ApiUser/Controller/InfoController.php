<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Apiuser\Controller;

use Deyi\Account\Account;
use Deyi\BaseController;
use Deyi\Coupon\Coupon;
use Deyi\GetCacheData\NoticeCache;
use Deyi\Integral\Integral;
use library\Fun\M;
use library\Service\ServiceManager;
use library\Service\System\Cache\RedCache;
use Deyi\Seller\Seller;
use library\Service\User\Member;
use library\Service\User\User;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Response;
use Deyi\JsonResponse;

class InfoController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    //todo 添加缓存key列表与时间

    //个人首页
    public function indexAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = (int)$this->getParams('uid');
        if (!$uid) {
            return $this->jsonResponseError('用户id不存在');
        }

        //个人信息
        $userinfo = RedCache::fromCacheData('D:UserInfo:' . $uid, function () use ($uid) {
            return $this->_getPlayUserTable()->get(array('uid' => $uid));
        }, 3600 * 24, true);
        $data_lottery_count = M::getAdapter()->query('SELECT total-op_total as total FROM ps_lottery_user_total WHERE user_id = ? AND lottery_id = ? AND log_date >= ? ORDER BY updated DESC LIMIT 1', array($uid, 5, date('Y-m-d')))->current();

        $city = $this->getCity();
        if (!$userinfo) {
            return $this->jsonResponseError('用户不存在');
        } else {
            $userinfo = (object)$userinfo; //临时
        }
        $res['uid'] = $userinfo->uid;
        $res['img'] = $this->getImgUrl($userinfo->img);
        $res['username'] = $userinfo->username;
        $res['birth'] = (int)$userinfo->child_old;
        $res['phone'] = $userinfo->phone;
        $res['merchant'] = array();  //$this->QueryUserBind($userinfo->uid);//已下线
        $res['sex'] = $userinfo->child_sex ? $userinfo->child_sex : 2;
        $res['baby_flag'] = 0;
        $res['sign'] = $userinfo->sign;
        $res['post_num'] = 0; //(int)$userinfo->circle_msg;  //圈子发言数 3.0去掉
        $res['c_num'] = (int)$userinfo->join_circle;
        $res['is_seller'] = ($userinfo->is_seller == 1) ? 1 : 0;
        $res['seller_money'] = 0;

        if ($res['is_seller']) {
            $Seller = new Seller();
            $res['seller_money'] = $Seller->getSellerAccount($uid)['account_money'];
        }

        $res['baby'] = array();

        $babyData = RedCache::fromCacheData('D:UserBabys:' . $uid, function () use ($uid) {
            $babyData = $this->_getPlayUserBabyTable()->fetchAll(array('uid' => $uid))->toArray();
            if (isset($babyData[0])) {
                return $babyData;
            } else {
                return array();
            }
        }, 3600 * 24, true);

        if (count($babyData)) {
            foreach ($babyData as $baby) {
                $res['baby'][] = array(
                    'name' => $baby['baby_name'],
                    'sex' => $baby['baby_sex'],
                    'img' => $baby['img'] ? $this->_getConfig()['url'] . $baby['img'] : '',
                    'birth' => $baby['baby_birth'],
                    'id' => $baby['id'],
                );
                $res['baby_flag'] = 1; //是否绑定了baby  0 没 1有
            }
        }


        //好评有礼数据
        $good_commen = RedCache::fromCacheData('D:GoodComm:' . $city, function () use ($city) {
            return $this->_getPlayGoodCommentTable()->get(['city' => $city]);
        }, 3600 * 24, true);


        //新消息个数
        $res['news_count'] = (int)RedCache::fromCacheData('D:NewMgsNum:' . $uid, function () use ($uid) {
            return $this->_getPlayUserMessageTable()->fetchCount(array('uid' => $uid, 'status' => 1, 'is_new' => 1));
        }, 3600 * 24);

        //分享出去文字
        $res['share_word'] = $userinfo->username . ' 邀请你成为ta的玩伴，点我查看';

        //余额
        $account = new Account();
        //积分
        $integral = new Integral();
        //票券
        $conpon = new Coupon();

        //积分设置
        $setting = RedCache::fromCacheData('D:intSet', function () use ($integral) {
            return $integral->getSetting();
        }, 10, true);


        //用户余额
        $money = RedCache::fromCacheData('D:UserMoney:' . $uid, function () use ($account, $uid) {
            return $account->getUserMoney($uid);
        }, 10);
        $res['money'] = sprintf("%.2f", $money);  //保留两位小数


        //我的积分
        $res['score'] = (int)RedCache::fromCacheData('D:Userintegral:' . $uid, function () use ($integral, $uid) {
            return $integral->getUserIntegral($uid);
        }, 10);


        //约稿数据
        $invite = RedCache::fromCacheData('D:inviteSet:' . $city, function () use ($city) {
            return $this->_getPlayInviteContentTable()->get(array('city' => $city));
        }, 3600 * 24, true);


        //我的现金券
        $coupon_num = RedCache::fromCacheData('D:UserCoupons:' . $uid, function () use ($conpon, $uid) {
            return $conpon->myCashCoupon($uid);
        }, 10, true);

        // 获取该用户的会员信息
        $service_member    = new Member();
        $data_member       = $service_member->getMemberData($uid);

        $data_param_count_associates = array(
            'uid'    => $uid,
            'status' => 1,
        );
        $data_count_associate = User::getCountAssociate($data_param_count_associates);

        $data_param_count_collect = array(
            'uid'  => $uid,
            'type' => array('good', 'shop', 'kidsplay'),
        );
        $data_count_collect= User::getCountCollect($data_param_count_collect);

        $data_count_friend = $this->_getMdbsocialFriends()->find(array('uid' => $uid, 'friends' => 1))->count();

        $coupon_num              = $coupon_num ? count($coupon_num) : 0;
        $res['cashcoupon']       = $coupon_num;                                   // 现金券
        $res['need_pay']         = (int)$this->getneed_pay($uid);                 // 待付款票券
        $res['need_comment']     = (int)$this->getneed_comment($uid);             // 待评价
        $res['unuse_coupon']     = (int)$this->getunuse_coupon($uid);             // 待使用票券
        $res['order_refund']     = $this->order_refund($uid);                     // 退款与返利
        $res['new_reward']       = NoticeCache::getNewReward($uid);               // 当有编辑对发言进行了额外奖励时，显示“有待查看的奖励” 仅仅只是标识,存缓存
        $res['bind_weixin']      = $this->bind_weixin($uid);                      // 是否绑定微信号
        $res['comments_prize']   = $good_commen['tips'];                          // 好评有礼提示
        $res['invitation']       = $invite ? $invite['award'] : '';               // 小编邀约
        $res['pay_password']     = (int)$account->getPassword($uid);              // 是否设置支付密码
        $res['weixin_bind_tips'] = $setting['weixin_bind_tips'];
        $res['is_vip']           = $data_member['member_level'] > 0 ? 1 : 0;
        $res['free_number']      = (int)$data_member['member_free_coupon_count_now'];
        $res['lottery_number']   = (int)$data_lottery_count->total;
        $res['associate_number'] = $data_count_associate;
        $res['like_number']      = $data_count_collect;
        $res['friend_number']    = $data_count_friend;
        $res['icon_url']         = 'http://play.wanfantian.com/member/guide/share_user_id/' . $uid;          // 悬浮小图标的跳转地址
        $res['icon_image']       = $this->getImgUrl('/uploads/2016/11/28/a3037dc8054cf0f53243d657753e60ff.png');                     // 悬浮小图标的图片地址

        return $this->jsonResponse($res);
    }

    // 所有退款中,已退款   统计退款与返利数量  关联账户表和用户现金券表暂时只有这两种返利方式
    public function order_refund($uid)
    {
        $db = $this->_getAdapter();
        return (int)RedCache::fromCacheData('D:backcash:' . $uid, function () use ($uid, $db) {
            $res = $db->query("SELECT
	count(order_sn) as c
FROM
	play_order_info
WHERE
	`user_id` = ?
AND `order_status` = 1
AND play_order_info.order_type IN (2, 3)
AND (
	play_order_info.backing_number > 0
	OR play_order_info.back_number > 0
	OR (
		order_sn IN (
			SELECT
				object_id AS object_id
			FROM
				play_account_log
			WHERE
				play_account_log.action_type = 1
			AND play_account_log.action_type_id = 16
			AND play_account_log.uid = ?
		)
		OR order_sn IN (
			SELECT
				cash.get_object_id
			FROM
				play_cashcoupon_user_link AS cash
			WHERE
				cash.get_type = 16
			AND uid = ?
		)
	)
)", array($uid,$uid,$uid))->current();
            if ($res) {
               return  (int)($res->c);
            } else {
                return 0;
            }

        }, 10);

    }


    //是否绑定微信
    private function bind_weixin($uid)
    {
        return (int)RedCache::fromCacheData('D:bindWeixin:' . $uid, function () use ($uid) {
            $res = $this->_getPlayUserWeiXinTable()->get(array('uid' => $uid, 'login_type' => 'weixin_sdk'));
            if ($res) {
                return 1;
            } else {
                return 0;
            }
        }, 60);

    }

    //未使用  (已过期的订单保留一个月)
    private function getunuse_coupon($uid)
    {

        $db = $this->_getAdapter();
        return (int)RedCache::fromCacheData('D:unUse:' . $uid, function () use ($uid, $db) {
            $res = $db->query("SELECT
	count(*) as c
FROM
	play_order_info
WHERE
	`user_id` = ?
AND `order_status` = 1
AND play_order_info.order_type IN (2, 3)
AND (
	back_number + use_number + backing_number
) < buy_number
AND  play_order_info.pay_status <6
AND play_order_info.pay_status > 1
LIMIT 1000
", array($uid));


            if ($res->current()) {
                return (int)$res->current()->c;
            } else {
                return 0;
            }

        }, 10);

    }


    //待付款
    private function getneed_pay($uid)
    {
        $db = $this->_getAdapter();
        $timer = time() - ServiceManager::getConfig('TRADE_CLOSED');
        return (int)RedCache::fromCacheData('D:tneedPay:' . $uid, function () use ($uid, $db, $timer) {
            $res = $db->query("
SELECT
count(*) as c
FROM
play_order_info
WHERE
	`user_id` = ?
AND `order_status` = ?
AND pay_status <= ?
AND play_order_info.order_type IN (2,3)
AND play_order_info.dateline > ?
", array($uid, 1, 1, $timer));
            return (int)$res->current()->c;
        }, 10);
    }

    //待评价
    private function getneed_comment($uid)
    {
        $db = $this->_getAdapter();

        return (int)RedCache::fromCacheData('D:needComment:' . $uid, function () use ($uid, $db) {
            $res = $db->query("
SELECT
	COUNT(*) as c 
FROM
	play_order_info
LEFT JOIN play_order_otherdata ON play_order_info.order_sn = play_order_otherdata.order_sn
WHERE
	`user_id` = ?
AND `order_status` = 1
AND play_order_info.order_type IN (2, 3)
AND `use_number` > 0
AND (
	`comment` = 0
	OR ISNULL(COMMENT)
)
LIMIT 1000
", array($uid));
            return (int)$res->current()->c;
        }, 10);

    }


    //查询绑定的商家或组织者
    protected function QueryUserBind($uid)
    {
        $data = array(
            'shop_id' => 0,
            'shop_name' => '',
            'organizer_id' => 0,
            'organizer_name' => ''
        );
        $organizer_res = $this->_getPlayOrganizerTable()->get(array('bind_uid' => $uid));
        if ($organizer_res) {
            $data['organizer_id'] = $organizer_res->id;
            $data['organizer_name'] = $organizer_res->name;
        }
        $shop_res = $this->_getPlayShopTable()->get(array('bind_uid' => $uid));
        if ($shop_res) {
            $data['shop_id'] = $shop_res->shop_id;
            $data['shop_name'] = $shop_res->shop_name;
        }
        return $data;

    }


    /***************  修改个人信息  *********************/

    public function resetInfoAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = (int)$this->getParams('uid');

        $data = array();
        if ($this->getParams('username')) {
            $data['username'] = $this->getParams('username');
        }

        if (!is_null($this->getParams('child_sex'))) {
            $data['child_sex'] = (int)$this->getParams('child_sex');
        }

        if (!is_null($this->getParams('child_old'))) {
            $data['child_old'] = strtotime($this->getParams('child_old'));
        }

        if (!count($data)) {
            return $this->jsonResponse(array('status' => 0, 'message' => '请提交要修改的元素'));
        }

        $status = $this->_getPlayUserTable()->update($data, array('uid' => $uid));

        if ($status) {
            $this->updateAlias($uid);
            return $this->jsonResponse(array('status' => 1, 'message' => '修改个人信息成功'));
        } else {
            return $this->jsonResponseError('修改个人信息失败');
        }

    }

    /***************  修改个人信息 性别end *********************/


    /***************  修改个人信息new  *********************/

    public function resetNewAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = (int)$this->getParams('uid');

        $data = array();
        if ($this->getParams('username')) {
            $data['username'] = $this->getParams('username');
        }

        if ($this->getParams('sex')) {
            $data['child_sex'] = (int)$this->getParams('sex');
        }

        if ($this->getParams('birth')) {
            $data['child_old'] = (int)$this->getParams('birth');
        }


        $sign = $this->getParams('sign'); //个性签名
        if ($sign) {
            $data['sign'] = $sign;
        }


        if (!count($data)) {
            return $this->jsonResponse(array('status' => 0, 'message' => '请提交要修改的元素'));
        }

        $status = $this->_getPlayUserTable()->update($data, array('uid' => $uid));

        if ($status) {
            $this->updateAlias($uid);
            return $this->jsonResponse(array('status' => 1, 'message' => '修改个人信息成功'));
        } else {
            return $this->jsonResponseError('修改个人信息失败');
        }

    }

    /***************  修改个人信息new 性别end *********************/

    /***************  修改个人与孩子图像处理  *********************/

    public function memberImgAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $img = base64_decode($this->getParams('img'));
        $uid = (int)$this->getParams('uid');//用户id 必选
        $child_id = (int)$this->getParams('child_id'); //修改头像的孩子id 可选
        $city = $this->getCity();

        $fileinfo = @getimagesizefromstring($img);
        if (empty($fileinfo)) {
            return $this->jsonResponseError('服务器未取得图片');
        }
        //上传图片
        if (!in_array($fileinfo['mime'], array('image/gif', 'image/jpg', 'image/jpeg', 'image/png'))) {
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

        $fileName = date('His') . substr(md5($img), 8, 16);

        //图片路径
        $ImgUrl = $_SERVER['DOCUMENT_ROOT'] . $this->_getConfig()['upload_dir'];
        if (!is_dir($ImgUrl)) {
            mkdir($ImgUrl, 0777, true);
        }
        $imgFileUrl = $ImgUrl . $fileName . $imginfo;

        $imgPut = file_put_contents($imgFileUrl, $img);

        if ($imgPut) {
            //处理图片大小
            $sta = $this->_scaleImage($img, $imgFileUrl . 'uthumb.jpg', 160, 160, $fileinfo[0], $fileinfo[1]);
            if ($sta) {
                $img = $this->_getConfig()['upload_dir'] . $fileName . $imginfo . 'uthumb.jpg';
                $integral = new Integral();
                if ($child_id) {
                    //修改孩子
                    $this->_getPlayUserBabyTable()->update(array('img' => $img), array('uid' => $uid, 'id' => $child_id));
                    //奖励修改孩子头像积分
                    $integral->baby_face($uid, $city);
                } else {
                    //修改个人
                    $this->_getPlayUserTable()->update(array('img' => $img), array('uid' => $uid));
                    $this->updateAlias($uid);
                    //奖励修改个人头像积分
                    $integral->face_integral($uid, $city);
                }
                return $this->jsonResponse(array('status' => 1, 'url' => $this->_getConfig()['url'] . $this->_getConfig()['upload_dir'] . $fileName . $imginfo . 'uthumb.jpg', 'message' => '上传成功'));
            }
        } else {
            return $this->jsonResponse(array('status' => 0, 'message' => '存储图片失败'));
        }


    }

    /***************  修改个人与孩子图像处理end *********************/


    private function _scaleImage($in, $outfile, $widthmax, $heightmax, $imagex, $imagey)
    {
        if ($imagex > $imagey) {
            $start_x = ($imagex - $imagey) / 2;
            $start_y = 0;
            $imagex = $imagey;
        } else {
            $start_x = 0;
            $start_y = ($imagey - $imagex) / 2;
            $imagey = $imagex;
        }

        $in = imagecreatefromstring($in);
        $tc = imagecreatetruecolor($widthmax, $heightmax); //创建空白图片
        imagecopyresampled($tc, $in, 0, 0, $start_x, $start_y, $widthmax, $heightmax, $imagex, $imagey);  //copy 图片,重新生成
        $status = imagejpeg($tc, $outfile, 100);
        return $status;

    }

    /**
     * 用户的baby相关操作
     */

    public function babyAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = (int)$this->getParams('uid');
        $city = $this->getCity();
        if (!$uid) {
            return $this->jsonResponseError('用户id不存在');
        }

        $act = $this->getParams('act');
        if (!in_array($act, array('del', 'add', 'fix'))) {
            return $this->jsonResponseError('非法操作');
        }

        $link_id = (int)$this->getParams('link_id');

        if (in_array($act, array('del', 'fix')) && !$link_id) {
            return $this->jsonResponse(array('status' => 0, 'message' => '非法操作'));
        }
        if ($act == 'del') {
            $this->_getPlayUserBabyTable()->delete(array('uid' => $uid, 'id' => $link_id));
            $this->updateAlias($uid);
            return $this->jsonResponse(array('status' => 1, 'message' => '操作成功'));
        }

        $data = array();
        $data['baby_name'] = $this->getParams('username');
        $data['baby_sex'] = (int)$this->getParams('sex');
        $data['baby_birth'] = $this->getParams('old');

        if (!in_array($data['baby_sex'], array(1, 2))) {
            return $this->jsonResponse(array('status' => 0, 'message' => '宝宝性别'));
        }

        if ($data['baby_birth'] < 631126861 || $data['baby_birth'] > time()) {
            return $this->jsonResponse(array('status' => 0, 'message' => '宝宝年龄不正确'));
        }

        if (strlen($data['baby_name']) == 0 || strlen($data['baby_name']) > 15) {
            return $this->jsonResponse(array('status' => 0, 'message' => '宝宝名称太长'));
        }

        if ($act == 'fix') {
            $this->_getPlayUserBabyTable()->update($data, array('uid' => $uid, 'id' => $link_id));
            $this->updateAlias($uid);
            //添加修改宝宝资料积分奖励，只奖励一次
            $integral = new Integral();
            $integral->baby_info($uid, $city);
            return $this->jsonResponse(array('status' => 1, 'message' => '操作成功'));
        }

        if ($act == 'add') {
            $data['uid'] = $uid;
            $baby_count = $this->_getPlayUserBabyTable()->fetchCount(array('uid' => $uid));
            if ($baby_count > 2) {
                return $this->jsonResponse(array('status' => 0, 'message' => '宝宝太多啦'));
            }

            $this->_getPlayUserBabyTable()->insert($data);
            $baby_id = $this->_getPlayUserBabyTable()->getlastInsertValue();
            $this->updateAlias($uid);
            $integral = new Integral();
            $integral->baby_info($uid, $city);
            return $this->jsonResponse(array('status' => 1, 'message' => '操作成功', 'baby_id' => $baby_id));
        }

    }

    public function updateAlias($uid)
    {

        $uid = (int)$uid;
        $alias = '';


        $userData = $this->_getPlayUserTable()->get(array('uid' => $uid));


        if (!$userData) {
            return false;
        }
        $childData = $this->_getPlayUserBabyTable()->fetchAll(array('uid' => $uid));

        if ($childData->count()) {
            // 取年龄最小的孩子
            $low_age = 1443542400;

            foreach ($childData as $child) {
                if ($child->baby_birth < $low_age) {
                    $low_age = $child->baby_birth;
                }
            }

            $age = date('Y', time()) - date('Y', $low_age);
            $alias = $this->number2Chinese($age) . '岁 ';

        } elseif ($userData->child_old) {
            if (($userData->child_old > 970243200) && ($userData->child_old < 1443542400)) {
                $age = date('Y', time()) - date('Y', $userData->child_old);
                $alias = $this->number2Chinese($age) . '岁 ';
            } else {
                //无孩子处理
            }
        } else {
            //无孩子处理
        }


        //0 男 非0 女
        if ($userData->child_sex != 1) {
            $alias = $alias . '宝妈';
            $sex = 2;
        } else {
            $alias = $alias . '宝爸';
            $sex = 1;
        }

        //圈子消息  用户名称 与 alias 圈子用户
        //用户名称 与 alias
        $this->_getMdbSocialCircleMsg()->update(array('uid' => $uid), array('$set' => array('username' => $userData->username, 'child' => $alias, 'img' => $this->getImgUrl($userData->img))), array('multiple' => true)); // 用户名称 与 alias
        $this->_getMdbSocialCircleMsgPost()->update(array('uid' => $uid), array('$set' => array('username' => $userData->username, 'child' => $alias, 'img' => $this->getImgUrl($userData->img))), array('multiple' => true)); // 用户名称 与 alias
        $this->_getMdbSocialCircleUsers()->update(array('uid' => $uid), array('$set' => array('username' => $userData->username, 'user_detail' => $alias, 'img' => $this->getImgUrl($userData->img))), array('multiple' => true)); // 用户名称 与 alias
        $this->_getMdbConsultPost()->update(array('uid' => $uid), array('$set' => array('username' => $userData->username, 'img' => $this->getImgUrl($userData->img))), array('multiple' => true)); // 用户名称 与 alias


        return $this->_getPlayUserTable()->update(array('user_alias' => $alias, 'child_sex' => $sex), array('uid' => $uid));

    }

    private function number2Chinese($ns)
    {

        $cnums = array('零', '一', '二', '三', '四', '五', '六', '七', '八', '九');
        $num = str_split($ns);
        $str = null;

        if (count($num) == 1) {
            $str = $cnums[$ns];
        }

        if (count($num) == 2) {
            foreach ($num as $k => $v) {
                if ($k == 0) {
                    if ($v > 1) {
                        $str = $cnums[$v] . '十';
                    } else {
                        $str = '十';
                    }
                }
                if ($k == 1) {
                    if ($v >= 1) {
                        $str = $str . $cnums[$v];
                    }
                }
            }
        }
        return $str;
    }

}
