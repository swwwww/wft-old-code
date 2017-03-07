<?php

namespace Admin\Controller;

use Deyi\GetCacheData\CityCache;
use Deyi\JsonResponse;
use Deyi\OrderAction\OrderInfo;
use Deyi\Paginator;
use Deyi\GetCacheData\NoticeCache;
use Deyi\Coupon\Coupon;
use Deyi\Account\Account;
use Deyi\GetCacheData\UserCache;
use Deyi\Integral\Integral;
use library\Fun\M;
use library\Service\System\Cache\RedCache;
use library\Service\User\Member;
use Zend\View\Model\ViewModel;
use library\Fun\OutPut;
use Zend\Db\Sql\Predicate\In;

class UserController extends BasisController
{
    use JsonResponse;

    //用户列表
    public function indexAction()
    {

        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;
        $username = trim($this->getQuery('username', null));
        $device_type = trim($this->getQuery('device_type', null));
        $phone = trim($this->getQuery('phone', null));
        $login_type = trim($this->getQuery('login_type', null));
        $uid = (int)$this->getQuery('uid', 0);
        $sign_start = $this->getQuery('sign_start', null);
        $sign_end = $this->getQuery('sign_end', null);
        $is_vir = $this->getQuery('is_vir', 0);
        $invite_code = $this->getQuery('invite_code', null); //邀请码
        $is_vip      = $this->getQuery('is_vip', 0);
        $city = $this->chooseCity();

        $order = 'play_user.uid DESC';


        $where = 'play_user.uid > 0 ';



        if ($username) {
            $city=false;
            $where = $where. " AND play_user.username = '{$username}'";
        }

        if ($device_type) {
            $where = $where. " AND play_user.source = '{$device_type}'";
        }

        if ($phone) {
            $city=false;
            $where = $where. " AND play_user.phone = '{$phone}'";
        }

        if ($login_type) {
            $where = $where. " AND play_user.login_type = '{$login_type}'";
        }

        if ($uid) {
            $city=false;
            $where = $where. ' AND play_user.uid = '. $uid;
        }

        if ($sign_start) {
            $where = $where. ' AND play_user.dateline  > '. strtotime($sign_start);
        }

        if ($sign_end) {
            $where = $where. ' AND play_user.dateline  < '. (strtotime($sign_end) + 86400);
        }

        if ($invite_code && !$uid) {
            $invite_code = (int) (base_convert(strtolower($invite_code), 32, 10) - 123456789);

            $where = $where. ' AND play_user.uid = '. $invite_code;
        }

        if ($is_vir) {
            $where = $where. ' AND play_user.is_vir = 1';
        } else {
            $where = $where. ' AND play_user.phone AND play_user.is_vir = 0';
        }

        if($city){
            $where = $where. " AND  play_user.uid > 0 and play_user.city = '{$city}'";
        }

        if ($is_vip == 1) {
            $where = $where. " AND  (play_member.member_level = 0 OR play_member.member_level is null) ";
        } else if ($is_vip == 2) {
            $where = $where. " AND  play_member.member_level > 0 ";
        }

        $sql = "SELECT
play_user.uid,
play_user.is_vir,
play_user.username,
play_user.phone,
play_user.login_type,
play_user.is_online,
play_user.is_seller,
play_user.status,
play_user.dateline,
play_user.device_type,
play_user.city,
play_user_weixin.uid as wei_uid,
play_member.member_level
FROM play_user
LEFT JOIN play_user_weixin on (play_user.uid= play_user_weixin.uid and play_user_weixin.login_type = 'weixin_sdk')
LEFT JOIN play_member ON (play_member.member_user_id = play_user.uid)
WHERE $where ORDER BY {$order}
LIMIT {$start}, {$pageSum}";

        $data = $this->query($sql);


        $count= M::getAdapter()->query("SELECT count(*) as c FROM play_user LEFT JOIN play_member ON (play_member.member_user_id = play_user.uid) WHERE $where",array())->current()->c;

        //创建分页
        $url = '/wftadlogin/user';
        $paging = new Paginator($page, $count, $pageSum, $url);

        $userData = array();

        $login_type = array(
            'qq' => 'QQ',
            'weixin' => '微信',
            'sinaweibo' => '微博',
            'deyi' => '得意',
            'phone' => '手机',
            'weixin_wap'=>'微信网页'
        );


        foreach ($data as $da) {
            $uid = $da['uid'];
            $invite_sql = "SELECT
	play_user.username
FROM
	play_user
LEFT JOIN invite_member ON invite_member.sourceid = play_user.uid
WHERE
	invite_member. status = 1
AND invite_member.uid = {$uid}";

            $result = $this->query($invite_sql)->current();

            $integral = $this->_getPlayIntegralUserTable()->get(array('uid' => $da['uid']));

            $userData[] = array(
                'is_vir' => $da['is_vir'],
                'uid' => $da['uid'],
                'city' => $this->getAllCities()[$da['city']],
                'username' => $da['username'],
                'phone' => $da['phone'],
                'is_online' => ($da['is_online'] == '1') ? '在线' : '下线',
                'login_type' => $login_type[$da['login_type']],
                'status' => $da['status'],
                'register_time' => date('Y-m-d H:i:s', $da['dateline']),
                'device_type' => $da['device_type'],
                'invite_people' => $result ? $result['username'] : '',
                'invite_code' => strtoupper(base_convert($da['uid']+123456789,10,32)),
                'wei_uid' =>  $da['wei_uid'] ? '已绑定' : '未绑定',
                'integral' => $integral ? $integral->total : 0,
                'is_seller' => $da['is_seller'],
                'member_level' => $da['member_level'],
            );
        }

        return array(
            'data' => $userData,
            'pageData' => $paging->getHtml(),
            'filtercity' => CityCache::getFilterCity($city),
            'channel' => $this->_getConfig()['user_source'],
        );
    }


    /**
     * 用户基本信息
     */
    public function infoAction() {
        $uid = (int)$this->getQuery('uid', 0);
        $userData = $this->_getPlayUserTable()->get(array('uid' => $uid));
        if (!$userData) {
            return $this->_Goto('不存在');
        }

        $invite_sql = "SELECT
	play_user.username,
	play_user.uid
FROM
	play_user
LEFT JOIN invite_member ON invite_member.sourceid = play_user.uid
WHERE
	invite_member. status = 1
AND invite_member.uid = {$uid}";

        $inviteData = $this->query($invite_sql)->current();

        $service_member = new Member();
        $data_member = $service_member->getMemberData($uid);

        $babyData = $this->_getPlayUserBabyTable()->fetchAll(array('uid' => $uid));
        $vm = new viewModel(
            array(
                'babyData' => $babyData,
                'memberData' => $data_member,
                'userData' => $userData,
                'inviteData' => $inviteData,
                'city' => $this->getAllCities(),
            )
        );

        return $vm;
    }

    /**
     * 订单
     */
    public function orderAction()
    {

        $uid = (int)$this->getQuery('uid', 0);
        $userData = $this->_getPlayUserTable()->get(array('uid' => $uid));
        if (!$userData) {
            return $this->_Goto('不存在');
        }

        $coupon_name = $this->getQuery('coupon_name', '');

        $where = "play_order_info.order_status = 1 and play_order_info.pay_status > 1 AND play_order_info.user_id = {$uid}"; //正常 且 已付款

        if ($coupon_name) {
            $where = $where. " AND play_order_info.coupon_name like '%".$coupon_name."%'";
        }

        $order = "play_order_info.order_sn DESC";
        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page-1)*$pageSum;

        $sql = "SELECT
	play_order_info.order_sn,
    play_order_info.pay_status,
    play_order_info.buy_number,
    play_order_info.backing_number,
	play_order_info.back_number,
	play_order_info.use_number,
	play_order_info.order_type,
	play_order_info.buy_phone,
	play_order_info.coupon_name,
	play_order_info.order_type,
	play_order_info.dateline,
	play_order_info.coupon_id,
	play_order_info.pay_status,
	play_order_info.account_type,
	play_order_info.real_pay,
    play_order_info.account_money,
	play_order_info.voucher,
	play_order_info.bid,
	play_game_info.price_name,
	play_game_info.shop_name,
	play_excercise_event.shop_name AS event_shop_name,
	play_order_info.use_dateline
FROM
	play_order_info
LEFT JOIN play_game_info ON play_game_info.id = play_order_info.bid AND play_order_info.order_type = 2
LEFT JOIN play_excercise_event ON play_excercise_event.id = play_order_info.coupon_id AND play_order_info.order_type = 3
WHERE
	 $where
ORDER BY
	$order";
        $sql_list = $sql." LIMIT
{$start}, {$pageSum}";

        $data = $this->query($sql_list);
        $count = $this->query($sql)->count();

        $orderData = array();

        $account = array(
            'alipay' => '支付宝',
            'union' => '银联',
            'weixin' => '微信',
            'jsapi' => '微信网页',
            'account' => '用户账户',
            'new_jsapi' => '新微信网页',
        );

        $orderStatus = new OrderInfo();

        foreach ($data as $order) {

            $orderData[] = array(
                'order_sn' => $order['order_sn'],
                'coupon_id' => $order['coupon_id'],
                'coupon_name' => $order['coupon_name'],
                'order_type' => $order['order_type'],
                'bid' => $order['bid'],
                'buy_time' => date('Y-m-d H:i:s', $order['dateline']),
                'use_time' => $order['use_datetime'] ? date('Y-m-d H:i:s', $order['use_datetime']) : '',
                'good_name' => $order['coupon_name'],
                'price_name' => $order['price_name'],
                'price' => $order['coupon_unit_price'],
                'order_status' => $orderStatus::getOrderStatus($order['pay_status'], $order['buy_number'], $order['backing_number'], $order['back_number'], $order['use_number'])['desc'],
                'account_money' => $order['account_money'],
                'real_pay' => $order['real_pay'],
                'voucher' => $order['voucher'],
                'shop_name' => $order['shop_name']. $order['event_shop_name'],
                'account_type' => $account[$order['account_type']],
            );
        }

        //创建分页
        $url = '/wftadlogin/user/order';
        $paging = new Paginator($page, $count, $pageSum, $url);

        $sql = "SELECT
	SUM(Tem.account_money + Tem.real_pay) AS order_money,
	SUM(Tem.back_code_money + Tem.back_money) AS order_back
FROM
(SELECT
	play_order_info.account_money,
  play_order_info.real_pay,
	SUM(if(play_coupon_code.force = 3 OR play_coupon_code.status = 2, play_coupon_code.back_money, 0)) AS back_code_money,
  SUM(if(play_excercise_code.accept_status = 3, play_excercise_code.back_money, 0)) AS back_money
FROM
	play_order_info
LEFT JOIN play_coupon_code ON play_coupon_code.order_sn = play_order_info.order_sn
AND play_order_info.order_type = 2
LEFT JOIN play_excercise_code ON play_excercise_code.order_sn = play_order_info.order_sn
AND play_order_info.order_type = 3
WHERE $where
GROUP BY play_order_info.order_sn) AS Tem";

        $countData = $this->query($sql)->current();
        $counter = array(
            'order_num' => $count,
            'order_money' => $countData['order_money'],
            'order_back' => $countData['order_back'],
            'order_sqr' => bcdiv($countData['order_money'], $count, 2),
        );

        $vm = new viewModel(
            array(
                'userData' => $userData,
                'data' => $orderData,
                'pageData' => $paging->getHtml(),
                'count' => $counter,
            )
        );

        return $vm;
    }

    /**
     * 发言
     */
    public function speakAction(){

        $uid = (int)$this->getQuery('uid', 0);
        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page-1)*$pageSum;

        $userData = $this->_getPlayUserTable()->get(array('uid' => $uid));

        if (!$userData) {
            return $this->_Goto('该用户不存在');
        }

        $order = array('status' => -1, 'dateline' => -1);
        $where['uid'] = $uid;
        $mongo = $this->_getMongoDB();
        $data = $mongo->social_circle_msg->find($where)->limit($pageSum)->skip($start)->sort($order);
        $count = $mongo->social_circle_msg->find($where)->count();

        $listData = array();
        foreach ($data as $res) {

            //充值类型 4圈子发言奖励　6点评商品奖励　7点评游玩地奖励　11 后台评论管理奖励　
            $rebate_sql = "SELECT sum(flow_money) AS rebate_money FROM play_account_log WHERE action_type_id in (4,11,7, 6) AND   msgid = '".(string)$res['_id']."'";
            // 2=>'点评商品',3=>'点评游玩地' 8=>'圈子发言奖励',9=>'后台评论奖励',
            $cash_sql = "SELECT sum(price) as price FROM play_cashcoupon_user_link WHERE get_type in (2,3, 9) AND   msgid = '".(string)$res['_id']."'";
            //  2 '游玩地评论',4 => '商品评论',15 => '圈子发言',16 => '圈子发言获赞', 17 => '商品评论获赞', 18 => '游玩地评论获赞', 26 => '活动评论',
            $inte_sql = "select sum(total_score) as total_score from play_integral where `type` in (2,4,15,16,17,18,26) and  msgid = '".(string)$res['_id']."'";
            //103 => '删除圈子发言扣除积分',104 => '删除圈子发言扣除积分',106 => '删除商品评论扣除积分',107 => '删除商品评论扣除积分',//小编108 => '删除游玩地评论扣除积分',//用户 109 => '删除游玩地评论扣除积分',//小编
            $sub_inte_sql = "select sum(total_score) as total_score from play_integral where `type` in (103,104,106,107,108,109) and  msgid = '".(string)$res['_id']."'";

            $rebate_data = $this->query($rebate_sql)->current();
            $cash_data = $this->query($cash_sql)->current();
            $integral_data = $this->query($inte_sql)->current();
            $sub_integral_data = $this->query($sub_inte_sql)->current();

            $listData[] = array(
                '_id' => (string)$res['_id'],
                'dateline' => $res['dateline'],
                'uid' => $res['uid'],
                'msg_type' => $res['msg_type'],
                'object_data' => $res['object_data'],
                'object_id' => $res['object_id'],
                'object_title' => $res['object_title'],
                'username' => $res['username'],
                'msg' => $res['msg'],
                'star_num' => $res['star_num'],
                'replay_number' => $res['replay_number'],
                'like_number' => $res['like_number'],
                'status' => $res['status'],
                'rebate_money' => $rebate_data['rebate_money']?:0,
                'cash_money' => $cash_data['price']?:0,
                'integral' => ($integral_data['total_score']-$sub_integral_data['total_score'])?:0,
            );
        }

        //创建分页
        $url = '/wftadlogin/user/speak';
        $paging = new Paginator($page, $count, $pageSum, $url);
        $vm = new viewModel(
            array(
                'userData' => $userData,
                'data' => $listData,
                'pageData' => $paging->getHtml(),
            )
        );

        return $vm;
    }

    /**
     * 积分
     */
    public function integralAction(){

        $uid = (int)$this->getQuery('uid', 0);
        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page-1)*$pageSum;

        $userData = $this->_getPlayUserTable()->get(array('uid' => $uid));

        if (!$userData) {
            return $this->_Goto('该用户不存在');
        }

        $data = array();
        $integralList = $this->query("SELECT play_integral.* FROM play_integral WHERE play_integral.uid= {$uid} AND total_score > 0 ORDER BY id desc LIMIT {$start}, {$pageSum}");

        foreach ($integralList as $integral) {

            $object = '';
            if (in_array($integral['type'], array(1))) {//游玩地分享

                $placeData = $this->_getPlayShopTable()->get(array('shop_id' => $integral['object_id']));
                if ($placeData) {
                    $object = '<a href="/wftadlogin/place/new?sid='. $integral['object_id']. '">'. $placeData->shop_name. '</a>';
                }

            } elseif(in_array($integral['type'], array(3))) {//商品分享

                $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $integral['object_id']));
                if ($goodData) {
                    $object = '<a href="/wftadlogin/good/new?gid='. $integral['object_id']. '">'. $goodData->title. '</a>';
                }

            } elseif (in_array($integral['type'], array(2, 4, 17, 18, 26, 106, 107, 108, 109))) { //评论相关

                $msg = $this->_getMdbConsultPost()->findOne(array('_id' => new \MongoId($integral['object_id'])));
                if ($msg) {
                    if ($integral['type'] == 26) {//活动
                        $object = '<a href="/wftadlogin/excercise/edite?id='. $msg['object_data']['object_id']. '">'. $msg['object_data']['object_title']. '</a>';
                    }

                    if (in_array($integral['type'], array(4, 17, 106, 107))) {//商品
                        $object = '<a href="/wftadlogin/good/new?gid='. $msg['object_data']['object_id']. '">'. $msg['object_data']['object_title']. '</a>';
                    }

                    if (in_array($integral['type'], array(2, 18, 108, 109))) {//游玩地
                        $object = '<a href="/wftadlogin/place/new?sid='. $msg['object_data']['object_id']. '">'. $msg['object_data']['object_title']. '</a>';
                    }
                }

            } elseif (in_array($integral['type'], array(5, 102))) { //商品购买相关 order_sn
                $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $integral['object_id']));
                if ($orderData) {
                    $object = '<a href="/wftadlogin/good/new?gid='. $orderData->coupon_id. '">'. $orderData->coupon_name. '</a>';
                }
            }

            $data[] = array(
                'id' => $integral['id'],
                'create_time' => $integral['create_time'],
                'desc' => $integral['desc'],
                'type' => $integral['type'],
                'total_score' => $integral['total_score'],
                'object' => $object
            );

        }

        $count = $this->_getPlayIntegralTable()->fetchCount(array('uid'=>$uid, 'total_score > ?' => 0));

        //创建分页
        $url = '/wftadlogin/user/integral';

        //总积分
        $userIntegral = $this->_getPlayIntegralUserTable()->get(array('uid' => $uid));

        $paging = new Paginator($page, $count, $pageSum, $url);
        $vm = new viewModel(
            array(
                'userData' => $userData,
                'data' => $data,
                'pageData' => $paging->getHtml(),
                'totalIntegral' => $userIntegral->total
            )
        );

        return $vm;
    }

    /**
     * 余额
     */
    public function balanceAction(){
        $uid = $this->getQuery('uid', 0);
        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page-1)*$pageSum;

        $userData = $this->_getPlayUserTable()->get(array('uid' => $uid));
        if (!$userData) {
            return $this->_Goto('该用户不存在');
        }

        $data = $this->_getPlayAccountLogTable()->fetchLimit($start, $pageSum, array(), array('uid'=>$uid, 'status' => 1), array('dateline' => 'DESC'))->toArray();;
        $count = $this->_getPlayAccountLogTable()->fetchCount(array('uid'=>$uid, 'status' => 1));
        $url = '/wftadlogin/user/balance';
        $paging = new Paginator($page, $count, $pageSum, $url);
        $vm = new viewModel(
            array(
                'data' => $data,
                'pageData' => $paging->getHtml(),
                'userData' => $userData,
            )
        );

        return $vm;
    }

    /**
     * 现金券
     */
    public function cashCouponAction()
    {
        $uid = $this->getQuery('uid', 0);
        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page-1)*$pageSum;

        $userData = $this->_getPlayUserTable()->get(array('uid' => $uid));
        if (!$userData) {
            return $this->_Goto('该用户不存在');
        }

        $data = $this->query("SELECT
	play_cashcoupon_user_link.*,
	play_order_info.coupon_id,
	play_order_info.coupon_name,
	play_order_info.real_pay,
	play_order_info.account_money,
    play_order_info.order_type,
	play_order_info.voucher
FROM
	play_cashcoupon_user_link
LEFT JOIN play_order_info ON play_order_info.order_sn = play_cashcoupon_user_link.use_order_id
WHERE play_cashcoupon_user_link.uid= {$uid} order by id desc LIMIT {$start}, {$pageSum}",array());


        $count = $this->_getCashCouponUserTable()->fetchCount( array('uid'=>$uid));
        $url = '/wftadlogin/user/cashCoupon';
        $paging = new Paginator($page, $count, $pageSum, $url);
        $vm = new viewModel(
            array(
                'data' => $data,
                'pageData' => $paging->getHtml(),
                'userData' => $userData,
            )
        );

        return $vm;
    }

    /**
     * 抢购资格
     */
    public function qualifyAction(){
        $uid = (int)$this->getQuery('uid', 0);
        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page-1)*$pageSum;

        $userData = $this->_getPlayUserTable()->get(array('uid' => $uid));

        if (!$userData) {
            return $this->_Goto('该用户不存在');
        }
        $data = $this->_getQualifyTable()->fetchLimit($start, $pageSum, array(), array('uid'=>$uid), array('create_time' => 'DESC'))->toArray();;
        $count = $this->_getQualifyTable()->fetchCount( array('uid'=>$uid));
        $url = '/wftadlogin/user/qualify';
        $paging = new Paginator($page, $count, $pageSum, $url);
        $vm = new viewModel(
            array(
                'data' => $data,
                'pageData' => $paging->getHtml(),
                'userData' => $userData,
            )
        );

        return $vm;
    }

    /**
     * 玩伴
     */
    public function partnerAction() {

        $uid = (int)$this->getQuery('uid', 0);
        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page-1)*$pageSum;

        $userData = $this->_getPlayUserTable()->get(array('uid' => $uid));

        if (!$userData) {
            return $this->_Goto('该用户不存在');
        }

        $order = array('_id' => -1);

        $where = array(
            '$or' => array(
                array(//玩伴
                    'uid' => $uid,
                    'friends' => 1,
                ),
                array(//我关注别人 别人没关注我
                    'uid' => $uid,
                    'friends' => 0,
                ),
                array(//我没关注别人 别人关注了我
                    'like_uid' => $uid,
                    'friends' => 0,
                ),
            ),
        );

        $friend = $this->_getMdbSocialFriends()->find($where)->limit($pageSum)->skip($start)->sort($order);
        $count = $this->_getMdbSocialFriends()->find($where)->count();

        $friends = array();
        $uIds = array();
        foreach($friend as $fri) {
            if ($fri['uid'] == $uid) {
                if ($fri['friends'] == 1) {
                    $friends[$fri['like_uid']] = '双向关注';
                    $uIds[] = (int)$fri['like_uid'];
                } else if ($fri['friends'] == 0) {
                    $friends[$fri['like_uid']] = '关注';
                    $sta[] = array('uid' => (string)$fri['like_uid'], 'concern' => 2,);
                    $uIds[] = (int)$fri['like_uid'];
                }
            } else if ($fri['like_uid'] = $uid) {
                if ($fri['friends'] == 0) {
                    $friends[$fri['uid']] = '被关注';
                    $uIds[] = (int)$fri['uid'];
                }
            }
        }

        $where = array(
            'status' => 1,
            count($uIds) ? new In('uid', $uIds) : 0,
        );

        $result = $this->_getPlayUserTable()->fetchAll($where)->toArray();

        $data = array();
        foreach ($result as $a) {
            $data[] = array(
                'uid' => $a['uid'],
                'username' => $a['username'],
                'phone' => $a['phone'],
                'is_online' => $a['is_online'],
                'dateline' => $a['dateline'],
                'city' => $this->getAllCities()[ $a['city']],
                'friend' => $friends[$a['uid']],
            );

        }
        //创建分页
        $url = '/wftadlogin/user/partner';
        $paging = new Paginator($page, $count, $pageSum, $url);
        $vm = new viewModel(
            array(
                'userData' => $userData,
                'data' => $data,
                'pageData' => $paging->getHtml(),
            )
        );

        return $vm;
    }


    /**
     * 收藏
     */
    public function favoriteAction(){

        $uid = (int)$this->getQuery('uid', 0);
        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page-1)*$pageSum;

        $userData = $this->_getPlayUserTable()->get(array('uid' => $uid));

        if (!$userData) {
            return $this->_Goto('该用户不存在');
        }

        $data = $this->query("SELECT
	play_user_collect.*, play_shop.shop_name,
	play_organizer_game.title,
	play_excercise_base.name
FROM
	play_user_collect
LEFT JOIN play_shop ON (
	play_shop.shop_id = play_user_collect.link_id
	AND play_user_collect.type = 'shop'
)
LEFT JOIN play_organizer_game ON (
	play_organizer_game.id = play_user_collect.link_id
	AND play_user_collect.type = 'good'
)
LEFT JOIN play_excercise_base ON (
	play_excercise_base.id = play_user_collect.link_id
	AND play_user_collect.type = 'kidsplay'
)
WHERE
	play_user_collect.uid = {$uid}
LIMIT {$start}, {$pageSum}", array());


        $count = $this->_getPlayUserCollectTable()->fetchCount(array('uid'=>$uid, "type in('shop','good','kidsplay')"));

        //创建分页
        $url = '/wftadlogin/user/favorite';
        $paging = new Paginator($page, $count, $pageSum, $url);

        $cat = array(
            'shop' => '游玩地',
            'good' => '商品',
            'kidsplay' => '活动'
        );

        $vm = new viewModel(
            array(
                'userData' => $userData,
                'data' => $data,
                'pageData' => $paging->getHtml(),
                'cat' => $cat
            )
        );

        return $vm;
    }

    /**
     *  用户运营分析
     */
    public function analysisUserAction()
    {
        $put = $this->getQuery('put', 0);//是否导出
        $phone = $this->getQuery('phone', 0);//用户id 用户手机号
        $user_name = $this->getQuery('user_name', '');//用户id 用户手机号
        $total_range_start = $this->getQuery('range_start', '');//订单总金额范围 开始
        $total_range_end = $this->getQuery('range_end', '');//订单总金额范围 结束
        $average_start = $this->getQuery('average_start', '');//均单值范围 开始
        $average_end = $this->getQuery('average_end', '');//均单值范围 结束
        $activity_start = (int)$this->getQuery('activity_start', 0);//活动订单范围 开始
        $activity_end = (int)$this->getQuery('activity_end', '');//活动订单范围 结束
        $order = intval($this->getQuery('order', 0));//均单值范围 结束
        $pageSum =  (int)$this->getQuery('page_num',10);
        $page = (int)$this->getQuery('p', 1);
        $start = ($page - 1) * $pageSum;

        $city = $this->getBackCity();

        if($city){
            $where = "play_user.uid > 0 AND play_user.is_vir = 0 AND play_user.phone AND play_user.city = '{$city}'";
        } else {
            $where = "play_user.uid > 0 AND play_user.is_vir = 0 AND play_user.phone";
        }

        if ($user_name) {
            $where = $where. " AND play_user.username like '%".$user_name."%'";
        }

        if ($phone) {
            $uid = intval($phone);
            $where = $where. " AND (play_user.phone like '%".$phone."%' OR play_user.uid = {$uid})";
        }

        if ($total_range_start) {
            $where = $where. " AND play_user_attached.user_attached_total_money >= ". $total_range_start;
        }

        if ($total_range_end) {
            $where = $where. " AND play_user_attached.user_attached_total_money <= ". $total_range_end;
        }

        if ($average_start) {
            $where = $where. " AND play_user_attached.user_attached_average_value >= ". $average_start;
        }

        if ($average_end) {
            $where = $where. " AND play_user_attached.user_attached_average_value <= ". $average_end;
        }

        if ($activity_start) {
            $where = $where. " AND play_user_attached.user_attached_activity_buy >= ". $activity_start;
        }

        if ($activity_end) {
            $where = $where. " AND play_user_attached.user_attached_activity_buy <= ". $activity_end;
        }

        if ($order == 1) {
            $order = " ORDER BY play_user_attached.user_attached_goods_buy DESC";
        } elseif ($order == 2) {
            $order = " ORDER BY play_user_attached.user_attached_activity_buy DESC";
        } elseif ($order == 3) {
            $order = " ORDER BY play_user_attached.user_attached_average_value DESC";
        } else {
            $order = " ORDER BY play_user_attached.user_attached_total_money DESC";
        }


        $countDataList = M::getAdapter()->query("SELECT COUNT(*) AS count_number
              FROM play_user_attached INNER JOIN play_user ON play_user.uid = play_user_attached.user_attached_uid
              WHERE {$where}", array())->current();

        $count = $countDataList ? $countDataList->count_number : 0;

        if ($put < 2) { //列表

            $data = array();
            $url = '/wftadlogin/user/analysisUser';
            $paging = new Paginator($page, $count, $pageSum, $url);

            $dataList = M::getAdapter()->query("SELECT play_user.uid, play_user.username, play_user.phone, play_user_attached.* FROM play_user_attached INNER JOIN play_user ON play_user.uid = play_user_attached.user_attached_uid
              WHERE {$where} {$order} LIMIT {$start}, {$pageSum}", array());

            foreach ($dataList as $value) {
                $data[] = array(
                    'uid' => $value->uid,
                    'username' => $value->username,
                    'phone' => $value->phone,
                    'user_attached_total_money' => $value->user_attached_total_money,
                    'user_attached_average_value' => $value->user_attached_average_value,
                    'dateline' => $value->user_attached_dateline ? date('Y_m-d H:i:s', $value->user_attached_dateline) : '',
                    'goods_order_num' => $value->user_attached_goods_buy,
                    'activity_order_num' => $value->user_attached_activity_buy,
                );
            }

            $vm = new ViewModel(array(
                'data' => $data,
                'pageData' => $paging->getHtml(),
            ));
            return $vm;
        }

        //导出
        if ($count > 50000) {
            return $this->_Goto('超过50000条了, 请缩小范围');
        }

        $dataList = M::getAdapter()->query("SELECT play_user.uid, play_user.username, play_user.phone, play_user_attached.* FROM play_user_attached INNER JOIN play_user ON play_user.uid = play_user_attached.user_attached_uid
              WHERE {$where} {$order}", array());
        $file_name = date('Y-m-d H:i:s', time()). '用户运营分析_user_导出.csv';

        $head = array(
            '用户id',
            '用户手机号',
            '用户名',
            '最后下单时间',
            '订单总金额',
            '订单均单值',
            '购买商品数量',
            '购买活动数量',
        );

        $content = array();
        foreach ($dataList as $value) {
            $content[] = array(
                $value->uid,
                $value->username,
                $value->phone,
                $value->user_attached_dateline ? date('Y_m-d H:i:s', $value->user_attached_dateline) : '',
                $value->user_attached_total_money,
                $value->user_attached_average_value,
                $value->user_attached_goods_buy,
                $value->user_attached_activity_buy,
            );
        }

        OutPut::out($file_name, $head, $content);
        exit;
    }


    public function closeAction()
    {
        $uid = (int)$this->getQuery('uid', 0);
        if (!$uid) {
            return $this->_Goto('参数错误');
        }
        $status = $this->_getPlayUserTable()->update(array('status' => 0), array('uid' => $uid));
        if ($status) {
            return $this->_Goto('账号成功关闭', 'javascript:location.href = document.referrer');
        } else {
            return $this->_Goto('未做修改，账号已是关闭状态');
        }

    }

    public function openAction()
    {
        $uid = (int)$this->getQuery('uid', 0);
        if (!$uid) {
            return $this->_Goto('参数错误');
        }
        $status = $this->_getPlayUserTable()->update(array('status' => 1), array('uid' => $uid));

        if ($status) {
            return $this->_Goto('账号成功开启', 'javascript:location.href = document.referrer');
        } else {
            return $this->_Goto('未做修改，账号已是开启状态');
        }
    }


    public function awardAction()
    {
        $where['city'] = $_COOKIE['city'];
        $where['end_time > ?'] = time();
        $where['status > ?'] = 0;
        $where['residue > ?'] = 0;
        $where['is_close'] = 0;
        $id = $this->getQuery('id', 0);

        //$where['new'] = 0;

        $cc = $this->_getCashCouponTable()->fetchAll($where);

        $remain = $this->getEditMoney($where['city']);

        return array(
            'cc' => $cc,
            'remain' => $remain,
            'msg_id' => $id,
            'uid' => $this->getQuery('uid', 0)
        );
    }

    /**
     * 执行奖励
     */
    public function doawardAction()
    {
        $uid = $this->getPost('uid', 0);
        $type = (int)$this->getPost('type', 0);
        $action_type = (int)$this->getPost('action_type', 0);//现金券
        $action_custom = $this->getPost('action_custom', '');
        $action_type1 = (int)$this->getPost('action_type1', 0);//金额
        $action_custom1 = $this->getPost('action_custom1', '');
        $city = $this->getAdminCity();
        $cash = $this->getPost('cash', 0);
        $idcoupon = $this->getPost('coupon', '');
        $coupon = explode('#', $idcoupon);
        $withdrawal = $this->getPost('withdrawal', 0);
        $status = $remain = 0;

        //1现金券,2返现金
        if ($type === 1) {
            if(!$action_type){
                return $this->_Goto('请选择奖励理由');
            }
            $cp = new Coupon();
            $status = $cp->addCashcoupon($uid,$coupon[0],0,$action_type,$_COOKIE['id'],$action_custom,$city);
            if($status){
                $tips = '奖励成功';
            }
        } elseif ($type === 2) {//用户资金＋
            //计算已经发出的奖励
            if(!$cash){
                return $this->_Goto('请选择金额');
            }
            if(!$action_type1){
                return $this->_Goto('请选择奖励理由');
            }
            $remain = $this->getEditMoney($city);
            if($cash > $remain){
                return $this->_Goto('您当月的返利额度已不足');
            }
            //待审核
            $data['uid'] = $uid;
            $data['gid'] = 0;
            $data['give_type'] = $action_type1;
            $data['get_info'] = $action_custom1;
            $data['from_type'] = 3;
            $data['rebate_type'] = $withdrawal+1;//是否可以提现
            $data['single_rebate'] = $cash;//单笔金额
            $data['city'] = $city;
            $data['status'] = 1;
            $data['editor_id'] = $_COOKIE['id'];
            $data['editor'] = $_COOKIE['user'];
            $data['create_time'] = time();
            $data['give_num'] = 1;
            $data['total_num'] = 1;

            $info = [9=>'采纳攻略',8=>'好评玩翻天APP',19=>'延期补偿',20=>'资深玩家奖励',21=>'好想你券'];
            $get_info = $info[$action_type1];
            if((int)$action_type1 === 99){
                if(!$action_custom1){
                    return $this->_Goto('请填写奖励理由');
                }
                $get_info = $info[$action_type1]?:$action_custom1;
            }
            $data['get_info'] = $get_info;//单笔金额

            $status = $this->_getPlayWelfareRebateTable()->insert($data);
            if($status){
                $tips = '返利已申请，请及时联系财务审核员;审核此笔返利，奖励才会到用户账户.';
            }
        }

        //产生通知,存入缓存
        if($status){
            NoticeCache::setNewReward($uid);
        }
        return $this->_Goto($status?$tips:'奖励失败', '/wftadlogin/user');
    }

    //现金奖励
    public function cashReward(){

        $uid = (int)$this->getQuery('uid',0);
        $money = trim($this->getQuery('flow_money',null));
        $desc = trim($this->getQuery('description'),null);
        if(!$uid or !$money or !$desc){
            return $this->_Goto("参数错误");
        }
        if($money <= 0){
            return $this->_Goto("金额不能小于0");
        }


        $adapter = $this->_getAdapter();
        $conObj = $adapter->getDriver()->getConnection();
        $conObj->beginTransaction();

        //查询用户状态是否正确
        $user = $adapter->query("SELECT * FROM play_account WHERE `uid`= ? and `status`>0",array($uid))->current();
        if(!$user){
            $sql = $adapter->query("INSERT INTO play_account (id,uid,now_money,total_money,last_time,status) VALUES (NULL ,?,?,?,?,?)", array($uid, $money, $money, time(),1))->count();
            if(!$sql){
                $conObj->rollback();
                return $this->_Goto("奖励操作失败");
            }
            $nMoney = $money;
        }else{
            $nMoney = bcadd($user->money,$money,2);
            $sq1 = $adapter->query("UPDATE play_account SET now_money=now_money+{$money},last_time=?,total_money=total_money+{$money} WHERE uid=?", array(time(), $uid))->count();
            if(!$sq1){
                $conObj->rollback();
                return $this->_Goto("奖励操作失败");
            }
        }

        //action_type_id 3为系统现金奖励
        $SQL = $adapter->query("INSERT INTO play_account_log (id,uid,action_type,action_type_id,object_id,flow_money,surplus_money,dateline,description) VALUES (NULL ,?,?,?,?,?,?,?,?)",
            array($uid, 1, 3, 0, $money, $nMoney, time(), $desc))->count();

        if($SQL){
            $conObj->rollback();
            return $this->_Goto("奖励操作失败");
        }
        $conObj->commit();
        RedCache::del('D:UserMoney:' . $uid);

        return $this->_Goto("奖励操作成功");

    }

    //地址
    public function addressAction()
    {
        $uid = $this->getQuery('uid', 0);

        $userData = $this->_getPlayUserTable()->get(array('uid' => $uid));

        if (!$userData) {
            return $this->_Goto('用户不存在');
        }

        $phoneList = $this->_getPlayUserLinkerTable()->fetchAll(array('user_id' => $uid), array('is_default' => 'desc'));
        $dataList = array();

        foreach ($phoneList as $v) {
            $dataList[] = array(
                'id' => $v->linker_id,
                'name' => $v->linker_name,
                'phone' => $v->linker_phone,
                'post_code' => $v->linker_post_code,
                'province' => $v->province,
                'city' => $v->city,
                'region' => $v->region,
                'address' => $v->linker_addr,
                'is_default' => $v->is_default
            );
        }

        $vm = new ViewModel(array(
            'data' => $dataList,
            'userData' => $userData,
        ));

        return $vm;
    }

    //出行人
    public function travelAction()
    {

        $uid = $this->getQuery('uid', 0);

        $userData = $this->_getPlayUserTable()->get(array('uid' => $uid));

        if (!$userData) {
            return $this->_Goto('用户不存在');
        }

        $data = $this->_getPlayUserAssociatesTable()->fetchAll(array('uid' => $uid,'status'=>1))->toArray();

        $vm = new ViewModel(array(
            'data' => $data,
            'userData' => $userData,
        ));

        return $vm;
    }

    //导出数据
    public function putdataAction()
    {
        $username = trim($this->getQuery('username', null));
        $device_type = trim($this->getQuery('device_type', null));
        $phone = trim($this->getQuery('phone', null));
        $login_type = trim($this->getQuery('login_type', null));
        $uid = (int)$this->getQuery('uid', 0);
        $sign_start = $this->getQuery('sign_start', null);
        $sign_end = $this->getQuery('sign_end', null);
        $is_vir = $this->getQuery('is_vir', 0);
        $is_vip = $this->getQuery('is_vip', 0);

        $city=$_COOKIE['city'];
        $order = 'play_user.uid DESC';
        $where = "play_user.uid > 0 AND city='{$city}'";

        if ($username) {
            $where = $where. " AND play_user.username = '{$username}'";
        }

        if ($device_type) {
            $where = $where. " AND play_user.device_type = '{$device_type}'";
        }

        if ($phone) {
            $where = $where. " AND play_user.phone =   '{$phone}'";
        }

        if ($login_type) {
            $where = $where. " AND play_user.login_type = '{$login_type}'";
        }

        if ($uid) {
            $where = $where. ' AND play_user.uid = '. $uid;
        }

        if ($sign_start) {
            $where = $where. ' AND play_user.dateline  > '. strtotime($sign_start);
        }

        if ($sign_end) {
            $where = $where. ' AND play_user.dateline  < '. (strtotime($sign_end) + 86400);
        }


        if ($is_vir) {
            $where = $where. ' AND play_user.is_vir = 1';
        } else {
            $where = $where. ' AND play_user.phone AND play_user.is_vir = 0';
        }

        if ($is_vip == 2) {
            $where = $where. ' AND play_member.member_level > 0';
        } else if ($is_vip == 1) {
            $where = $where. ' AND (play_member.member_level = 0 or play_member.member_id is null)';
        }

        $sql = "SELECT
play_user.uid,
play_user.username,
play_user.phone,
play_user.login_type,
play_user.is_online,
play_user.status,
play_user.dateline,
play_user.device_type,
play_member.member_level,
play_member.member_money
FROM play_user
LEFT JOIN play_member ON play_member.member_user_id = play_user.uid
WHERE $where ORDER BY {$order}";
        $data = $this->query($sql);
        $count = $this->query("SELECT uid FROM play_user LEFT JOIN play_member ON play_member.member_user_id = play_user.uid WHERE $where")->count();
        //todo 如果导出数据
        if ($count > 4000) {
            return $this->_Goto('数据太多了，你向服务器提出了个问题，请将时间限制在一个月试试');
        }

        if (!$data->count()) {
            return $this->_Goto('0条数据！');
        }

        $head = array(
            '用户id',
            '用户名',
            '手机号',
            '会员类型',
            '绑定微信号',
            '注册邀请人',
            '注册时间',
            '设备',
            '订单数',
            '用户发言',
            '积分',
            '余额',
            '会员充值累计金额',
            '系统奖励金额',
            '现金券剩余金额',
            '抢购资格',
            '玩伴',
            '圈子',
            '收藏',
        );

        $content = array();
        foreach($data as $v){
            $userInfo = $this->getUserInfo($v['uid']);
            $content[] = array(
                $v['uid'],
                $v['username'],
                $v['phone'],
                $v['member_level'] > 0 ? 'VIP会员' : '普通会员',
                '',
                '',
                date('Y-m-d',$v['dateline']),
                $v['device_type'],
                $userInfo['order_num'],
                $userInfo['word_num'],
                $userInfo['integral'],
                $userInfo['balance'],
                (float)$v['member_money'] > 0 ? (float)$v['member_money'] : 0,
                $userInfo['system_money'],
                $userInfo['cash_coupon_money'],
                $userInfo['qualify_num'],
                $userInfo['partner_num'],
                $userInfo['circle_num'],
                $userInfo['collect_num'],
            );
        }
        $fileName = date('Y-m-d H:i:s', time()). '_用户列表.csv';
        OutPut::out($fileName, $head, $content);
        exit;
    }

    /**
     * 获取用户的信息
     * @param $uid
     * @return array
     */
    private function getUserInfo($uid) {

        $data = array(
            'order_num' => 0, //订单数
            'word_num' => 0, //发言数
            'integral'=>0, //积分数
            'balance' => 0, //余额
            'system_money'=>0, //系统奖励金额
            'cash_coupon_money' => '', //现金券剩余金额
            'qualify_num' => '', //抢购资格
            'partner_num'=>0, //玩伴
            'circle_num'=>0, //圈子
            'collect_num'=>0, //收藏
        );

        //订单
        $order_data = $this->_getPlayOrderInfoTable()->fetchAll(array('user_id' => $uid, 'order_status' => 1));
        $data['order_num'] = $order_data->count();

        //发言
        $word_data = $this->_getMdbSocialCircleMsg()->find(array('uid' => (int)$uid,  "status" => array('$gte' => 0)));
        $data['order_num'] = $word_data->count();

        //积分
        $integral = $this->_getPlayIntegralUserTable()->get(array('uid' => $uid));
        $data['integral'] = $integral ? $integral->total : 0;

        //余额
        $balance_data = $this->_getPlayAccountTable()->get(array('uid' => $uid));
        $data['balance'] = $balance_data ? $balance_data->now_money : 0;

        //系统奖励金额
        $data['system_money'] = 0;

        //现金券剩余金额
        $data['cash_coupon_money'] = 0;

        //抢购资格
        $qualify_data = $this->_getQualifyTable()->fetchAll(array('uid' => (int)$uid));
        $data['qualify_num'] = $qualify_data->count();


        //玩伴
        $partner_data = $this->_getMdbSocialFriends()->find(array('uid' => (int)$uid, 'friends' => 1));
        $data['partner_num'] = $partner_data->count();

        //圈子
        $circle_data = $this->_getMdbSocialCircleUsers()->find(array('uid' => (int)$uid));
        $data['circle_num'] = $circle_data->count();

        //收藏
        $collect = $this->_getPlayUserCollectTable()->fetchAll(array('uid'=> $uid));
        $data['collect_num'] = $collect->count();

        return $data;

    }

    /**
     * @param $order_sn
     */
    private function getOrderLink($code_id) {

        $data = array(
            'price_name' => '套系名称',
            'order_status' => '订单状态',
            'good_status' => '商品状态'
        );
        $codeData = $this->_getPlayCouponCodeTable()->get(array('id' => $code_id));
        if (!$codeData) {
            return $data;
        }

        $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $codeData->order_sn));

        if ($orderData->order_type == 1) {//旧卡券
            return array(
                'price_name' => '',
                'order_status' => 'end',
                'good_status' => '已停止'
            );
        }

        $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $orderData->coupon_id));

        //商品状态
        if ($goodData->status == 0) {
            $data['good_status'] =  '未发布';
        } elseif ($goodData->status == 1) {
            if ($goodData->up_time > time()) {
                $data['good_status'] =  '未开始';
            } elseif ($goodData->up_time < time() && $goodData->down_time > time()) {
                $data['good_status'] =  '在售卖';
            } elseif ($goodData->foot_time > time() && $goodData->down_time < time()) {
                $data['good_status'] =  '停止售卖';
            } elseif ($goodData->foot_time < time() && $goodData->down_time < time()) {
                $data['good_status'] =  '停止使用';
            } else {
                return $data;
            }
        } else {
            return $data;
        }

        //套系名称
        $orderInfoData = $this->_getPlayOrderInfoGameTable()->get(array('order_sn' => $codeData->order_sn));

        $goodInfoData = $this->_getPlayGameInfoTable()->get(array('id' => $orderInfoData->game_info_id));
        $data['price_name'] =  $goodInfoData->price_name;

        if ($codeData->status == 0) {
            $data['order_status'] = '待使用';
        } elseif ($codeData->status == 1 && $codeData->test_status <= 2) {
            if ($codeData->force == 0) {
                $data['order_status'] =  '已使用';
            } elseif ($codeData->force == 1) {
                $data['order_status'] =  '已提交退款';
            } elseif ($codeData->force == 2) {
                $data['order_status'] =  '已受理退款';
            } elseif ($codeData->force == 3) {
                $data['order_status'] =  '已退款';
            }
        } elseif ($codeData->test_status == 0 && $codeData->status == 3) {
            $data['order_status'] =  '已提交退款';
        } elseif ($codeData->test_status == 1 && $codeData->status == 3) {
            $data['order_status'] =  '已受理退款';
        } elseif ($codeData->status == 2 && $codeData->test_status < 3) {
            $data['order_status'] =  '已退款';
        } elseif ($codeData->test_status == 3) {
            $data['order_status'] =  '已提交结算';
        } elseif ($codeData->test_status == 4) {
            $data['order_status'] =  '已受理结算';
        } elseif ($codeData->test_status == 5) {
            $data['order_status'] =  '已结算';
        }

        return $data;

    }


    //添加虚拟用户
    public function addVirAction()
    {
        $uid = (int)$this->getQuery('uid', 0);
        $userData = $this->_getPlayUserTable()->get(array('uid' => $uid));
        $babyData = $this->_getPlayUserBabyTable()->get(array('uid' => $uid));
        if ($uid && (!$userData || !$userData->is_vir))
        {
            return $this->_Goto('非法操作');
        }

        $vm = new ViewModel(array(
            'userData' => $userData,
            'babyData' => $babyData,
        ));

        return $vm;

    }

    //保存虚拟用户
    public function saveVirAction()
    {
        $uid = (int)$this->getPost('uid', 0);
        $baby_sex = (int)$this->getPost('baby_sex', 1);
        $baby_img = $this->getPost('baby_img', '');
        $baby_name = $this->getPost('baby_name', '');
        $child_sex = (int)$this->getPost('child_sex', 1);
        $img = $this->getPost('img', '');
        $user_name = $this->getPost('user_name', '');
        $baby_birth = strtotime($this->getPost('baby_birth') . $this->getPost('baby_birth1')); //上架时间

        if(!$img){
            $dirArray[]=NULL;
            $pub = explode('V1',__DIR__)[0];
            if (false !== ($handle = opendir ( $pub.'public/images/gravatar' ))) {
                $i=0;
                while ( false !== ($file = readdir ( $handle )) ) {

                    if ($file !== '.' && $file !== '..' && (strpos($file,'.jpg') or strpos($file,'.png'))) {
                        $dirArray[$i]=$file;
                        $i++;
                    }
                }
                //关闭句柄
                closedir ( $handle );
            }
            $count = count($dirArray);
            $rand = mt_rand(0,$count-1);
            $img = '/images/gravatar/'.$dirArray[$rand];
        }

        if ($uid) {
            $userData = $this->_getPlayUserTable()->get(array('uid' => $uid));
            if (!$userData || !$userData->is_vir) {
                return $this->_Goto('非法操作');
            }
        }

        if ($baby_birth > time()) {
            return $this->_Goto('宝宝生日不对');
        }

        if (!in_array($baby_sex, array(1, 2)) || !in_array($child_sex, array(1, 2))) {
            return $this->_Goto('性别不正确');
        }

        if (!$user_name || !$baby_name) {
            return $this->_Goto('姓名必填');
        }

        //todo 处理图像
        $data = array(
            'child_sex' => $child_sex,
            'username' => $user_name,
            'img' => $img,
        );

        $babyData = array(
            'baby_sex' => $baby_sex,
            'baby_name' => $baby_name,
            'img' => $baby_img,
            'baby_birth' => $baby_birth
        );

        if ($uid) {
            $s1 = $this->_getPlayUserTable()->update($data, array('uid' => $uid));

            $flag = $this->_getPlayUserBabyTable()->get(array('uid' => $uid));
            if ($flag) {
                $s2 = $this->_getPlayUserBabyTable()->update($babyData, array('uid' => $uid));
            } else {
                $babyData['uid'] = $uid;
                $s2 = $this->_getPlayUserBabyTable()->insert($babyData);
            }

            $UserCache = new UserCache();
            $UserCache->setUserCache($uid, 2);
            if ($s1 || $s2) {
                $this->updateAlias($uid);
                return $this->_Goto('成功', '/wftadlogin/user/index?is_vir=1');
            }
        }

        $data['login_type'] = 'deyi';
        $data['device_type'] = 'ios';
        $data['dateline'] = time();
        $data['is_vir'] = 1;

        $status = $this->_getPlayUserTable()->insert($data);

        if (!$status) {
            return $this->_Goto('添加失败' , '/wftadlogin/user/index?is_vir=1');
        }

        $uid = $this->_getPlayUserTable()->getlastInsertValue();
        $babyData['uid'] = $uid;
        $this->_getPlayUserBabyTable()->insert($babyData);

        $UserCache = new UserCache();
        $UserCache->setUserCache($uid, 2);
        return $this->_Goto('成功' , '/wftadlogin/user/index?is_vir=1');

    }


    private function updateAlias($uid)
    {

        $uid = (int)$uid;
        $userData = $this->_getPlayUserTable()->get(array('uid' => $uid));

        if (!$userData) {
            return false;
        }

        $userCache = new UserCache();
        $age = $userCache->getBabyAge($uid);

        if ($age['max'] > 18) {
            $age['max'] = 18;
        }

        $alias = ceil($age['max']). '岁 ';


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

    public function activateFreeCouponAction () {
        $param['uid']      = (int)$_GET['uid'];
        $param['from_uid'] = (int)$_GET['from_uid'];
        $param['city']     = $_GET['city'];

        if (empty($param['uid']) || empty($param['from_uid']) || empty($param['city'])) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '参数错误'));
        }

        $service_member = new Member();

        $pdo  = M::getAdapter();
        $conn = $pdo->getDriver()->getConnection();
        $conn->beginTransaction();
        $data_result = $service_member->activateFreeCoupon($param['uid'], $param['from_uid'], $param['city'], $pdo, $conn);

        if ($data_result) {
            $conn->commit();
        }
        return $this->jsonResponsePage(array('status' => (int)$data_result, 'message' => $data_result ? '操作成功' : '操作失败'));
    }
}
