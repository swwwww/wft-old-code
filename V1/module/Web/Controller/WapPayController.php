<?php

namespace Web\Controller;

use Deyi\Account\Account;
use Deyi\BaiduLocation;
use Deyi\BaseController;
use Deyi\Coupon\Coupon;
use Deyi\GetCacheData\CityCache;
use Deyi\GetCacheData\CouponCache;
use Deyi\GetCacheData\GoodCache;
use Deyi\GetCacheData\PlaceCache;
use Deyi\GetCacheData\UserCache;
use Deyi\Integral\Integral;
use Deyi\JsonResponse;
use Deyi\OrderAction\OrderPay;
use library\Service\System\Cache\RedCache;
use Deyi\Upload;
use Deyi\WeiXinFun;
use Deyi\WeiXinPay\WeiXinPayFun;
use Deyi\WriteLog;
use Zend\Db\Sql\Expression;
use Zend\Http\Response;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Deyi\Mcrypt;

class WapPayController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    public function __construct()
    {
        //设置请求第三方时mysql断开连接的问题
        ini_set('mysql.connect_timeout', 60);
        ini_set('default_socket_timeout', 60);
    }

    public function shareinfoAction()
    {
        $weixin = new WeiXinFun($this->getwxConfig());

        $backUrl = urldecode($_GET['url']);


        if (isset($_GET['code'])) {
            $accessTokenData = $weixin->getUserAccessToken($_GET['code']);
            if (isset($accessTokenData->access_token)) {
                if ($accessTokenData->scope == 'snsapi_userinfo') {
                    $userInfo = $weixin->getUserInfo($accessTokenData->access_token);
                    if (!$userInfo) {
                        //todo 错误处理
                    } else {


//                        $postdata = http_build_query(
//                            json_encode($userInfo, JSON_UNESCAPED_UNICODE)
//                        );


                        header("Location: " . urldecode($_GET['p']) . "&unionid={$userInfo->unionid}&openid={$userInfo->openid}&img=".urlencode($userInfo->headimgurl)."&nickname=".urlencode($userInfo->nickname));
                        exit;
                    }
                }

            }
        } else {
            $url = $this->_getConfig()['url'] . '/web/wappay/shareinfo?p=' . urlencode($backUrl);
            $toUrl = $weixin->getAuthorUrl($url, 'snsapi_userinfo');
            header("Location: $toUrl");
            exit;
        }

    }

    //首页
    public function indexAction()
    {
        header('location:'.$this->_getConfig()['url'].'/web/wappay/nindex');
        exit();
        //todo 测试数据
        $ci = $this->getQuery('city');
        $city_data = $this->_getConfig()['city'];
        if (in_array($ci, $city_data)) {
            $city = array_flip($city_data)[$ci];
        } else {
            $city = 'WH';
        }
        $page = (int)$this->getQuery('page', 1);
        $pageSum = 5;
        $start = ($page - 1) * $pageSum;
        $time = time();
        $data = array(
            'maps' => array(),
            'tag' => array(),
            'list' => array(),
        );

        //todo 首页幻灯片
        $focusWhere = array(
            "block_city = '{$city}' AND link_type = 2 AND play_index_block.type != 3 AND ((play_index_block.type = 1 AND play_activity.status >= 0 AND ((play_activity.s_time < {$time} and play_activity.e_time > {$time}) or (play_activity.s_time = 0 and play_activity.e_time = 0))) OR (play_index_block.type = 2 AND (play_coupons.coupon_join = 0 OR ( play_coupons.coupon_status = 1 AND play_coupons.coupon_uptime <= {$time} AND play_coupons.coupon_starttime <= {$time} AND play_coupons.coupon_endtime >= {$time} AND play_coupons.coupon_total > play_coupons.coupon_buy))) OR  (play_index_block.type = 4 AND play_shop.shop_status >= 0) OR (play_index_block.type = 5 AND play_organizer_game.status > 0 AND play_organizer_game.start_time <= {$time} AND play_organizer_game.end_time >= {$time}))"
        );
        $focusMaps = $this->_getPlayIndexBlockTable()->getApiBlockList(0, 10, $columns = array(), $focusWhere, $order = array('block_order' => 'DESC', 'dateline' => 'DESC'));

        foreach ($focusMaps as $maps) {
            if ($maps['type'] == 1) {//专题
                $data['maps'][] = array(
                    'title' => $maps['tip'] ? $maps['tip'] : $maps['ac_name'],
                    'cover' => $this->_getConfig()['url'] . $maps['ac_cover'],
                    'type' => $maps['type'],
                    'id' => $maps['link_id'],
                );
            } elseif ($maps['type'] == 2) {//卡券
                $data['maps'][] = array(
                    'title' => $maps['tip'] ? $maps['tip'] : $maps['coupon_name'],
                    'cover' => $this->_getConfig()['url'] . $maps['coupon_cover'],
                    'type' => $maps['type'],
                    'id' => $maps['link_id'],
                );
            } elseif ($maps['type'] == 4) {//游玩地
                $data['maps'][] = array(
                    'title' => $maps['tip'] ? $maps['tip'] : $maps['shop_name'],
                    'cover' => $this->_getConfig()['url'] . $maps['cover'],
                    'type' => $maps['type'],
                    'id' => $maps['link_id'],
                );
            } elseif ($maps['type'] == 5) {//活动
                $data['maps'][] = array(
                    'title' => $maps['tip'] ? $maps['tip'] : $maps['game_name'],
                    'cover' => $this->_getConfig()['url'] . $maps['game_cover'],
                    'type' => $maps['type'],
                    'id' => $maps['link_id'],
                );
            }
        }

        //todo 首页标签
        $tagData = $this->_getPlayLabelTable()->fetchLimit(0, 100, $columns = array(), array('status >= ?' => 2, 'city' => $city), array('dateline' => 'desc'));
        foreach ($tagData as $tag) {
            $data['tag'][] = array(
                'id' => $tag['id'],
                'coin' => $tag['coin'] ? $this->_getConfig()['url'] . $tag['coin'] : '',
                'name' => $tag['tag_name'],
                'cover' => $tag['cover'] ? $this->_getConfig()['url'] . $tag['cover'] : '',
                'description' => $tag['description'],
            );
        }

        return array('data' => $data, 'url' => $this->_getConfig()['url']);
    }

    //绑定手机号前台页面
    public function bindphoneAction()
    {
        $weixin = new WeiXinFun($this->getwxConfig());
        $authorUrl = $weixin->getAuthorUrl();
        if ($this->userInit($weixin) and $this->checkWeiXinUser()) {
        } else {
            // 授权失败
            header("Location: {$authorUrl}");
            exit;
        }

        //绑定手机号页面  成功后跳转到tourl
        $uid = $this->getQuery('uid');
        $tourl = $this->getQuery('tourl');
        return array(
            'uid' => $uid,
            'tourl' => $tourl,
            'authorUrl' => $authorUrl
        );
    }

    //判断浏览器是否微信内置
    public function is_weixin()
    {
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
            return true;
        }
        return false;
    }


    //活动购买选项 数量 地点选择
    public function orderSubmitAction()
    {
        $uid = (int)$_COOKIE['uid'];
        $coupon_id = (int)$this->getQuery('id', 0);
        $order_id = (int)$this->getQuery('tid', 0);
        $g_buy = (int)$this->getQuery('g_buy', 0);//是否团购\
        $group_buy = (int)$this->getQuery('group_buy',0);//参团2
        $group_buy_id = (int)$this->getQuery('group_buy_id',0);
        $tips = (int)$this->getQuery("tips",0);
        $good_num = (int)$this->getQuery("good_num",1);

        $url = $this->_getConfig()['url'] . "/web/wappay/ordersubmit?id={$coupon_id}&tid={$order_id}&g_buy={$g_buy}&group_buy={$group_buy}&group_buy_id={$group_buy_id}";

        $weixin = new WeiXinFun($this->getwxConfig());

        if ($this->userInit($weixin) and $this->checkWeiXinUser()) {
            if(!$_COOKIE['phone']){
                $this->checkPhone($url);
            }
        } else {
            //todo 授权失败
            $toUrl = $weixin->getAuthorUrl($url, 'snsapi_userinfo');
            header("Location: $toUrl");
            exit;
        }


        if (!$uid or !$coupon_id or !$order_id) {
            return $this->_Goto('参数错误');
        }

        $post_url = $this->_getConfig()['url'].'/good/index/nselectList';
        $json = $this->post_curl($post_url,array('uid'=>$uid,'coupon_id'=>$coupon_id,'order_id'=>$order_id,'g_buy'=>$g_buy,'g_buy_id'=>$group_buy_id),$_COOKIE['sel_city'],$_COOKIE);
        $data = json_decode($json,true)['response_params'];
//        $db = $this->_getAdapter();
//        $order_data = $db->query("SELECT play_game_info.*,play_shop.busniess_circle FROM play_game_info LEFT JOIN play_shop ON play_shop.shop_id=play_game_info.shop_id WHERE gid=? and status=1 AND play_game_info.end_time>? order by id", array($coupon_id, time()));
//
//        $gameData = $this->_getPlayOrganizerGameTable()->get(array('id' => $coupon_id));
//
//        $data = array();
        $data['id'] = $coupon_id;
        $data['group_buy'] = $group_buy;
        $data['group_buy_id'] = $group_buy_id;
        $data['order_id'] = $order_id;
        $data['tip'] = $tips;
        $data['good_num'] = $good_num;
        $data['g_buy'] = $g_buy;
//        $data['game_order'] = array();
//        $data['way'] = $this->_getPlayGameInfoTable()->get(array('id'=>$order_id))->price_name;
//        $game_time=array();
//        foreach ($order_data as $v) {
//            $game_time[] = $v;
//            if($data['way']==$v->price_name){
//                $data['game_order'][] = array(
//                    "order_id" => $v->id,
//                    "way" => $v->price_name,
//                    "s_time" => $v->start_time,
//                    "e_time" => $v->end_time,
//                    "price" => $g_buy ? $gameData->g_price : $v->price,
//                    "money" => $v->money,
//                    "buy" => $v->buy,
//                    "total_num" => $v->total_num,
//                    "shop_name" => $v->shop_name,
//                    "shop_id" => $v->shop_id,
//                    "address" => CouponCache::getBusniessCircle($v->busniess_circle, 'WH'),//"汉阳 钟家村",
//                    "want_score" => $v->integral,   // 非0为所需要的积分
//                    "max_buy" => $gameData->limit_num,
//                    "min_buy" => $gameData->limit_low_num,
//                    "thumb" => $this->getImgUrl($gameData->thumb),
//                    'insure_num_per_order'=>$v->insure_num_per_order,//购买保险时返回每单保险人数
//                    'has_addr'=>$gameData->has_addr  //是否必填收货地址，0不用填，1必填
//                );
//
//                $data['has_traveller'] += $v->insure_num_per_order;
//            }
//        }
//
//
//        $phone_data = $this->_getPlayUserLinkerTable()->get(array('user_id' => $uid, 'is_default' => 1));
//
//        //最少有一个联系人,用户绑定的
//        if ($phone_data) {
//            $data['contacts'] = array(
//                'id' => $phone_data->linker_id,
//                'name' => $phone_data->linker_name,
//                'phone' => $phone_data->linker_phone,
//                'post_code'=>$phone_data->linker_post_code,   //邮编
//                'province'=>$phone_data->province, //省份
//                'city'=>$phone_data->city, //城市
//                'region'=>$phone_data->region,     //地区
//                'address'=> $phone_data->linker_addr,     //地址
//
//            );
//        } else {
//            $uid_info = $this->_getPlayUserTable()->get(array('uid' => $uid));
//
//            $data['contacts'] = array(
//                'id' => 0,
//                'name' => $uid_info->username,
//                'phone' => $uid_info->phone
//            );
//        }
//
//
//        $data['tips'] = ""; // 当前套系 所有节点最高  价格相同取购买后返利
//
//        //获取最大返利
//        $welfare = $this->_getPlayWelfareTable()->fetchAll(array('object_id' => $coupon_id, 'good_info_id' => $order_id, 'object_type' => 2, '(welfare_type=2 or welfare_type=3)', 'status' => 2), array('welfare_value' => 'desc'), 1)->current();
//        if ($welfare) {
//            $desc = $welfare->welfare_type == 2 ? '现金' : '现金券';
//            $data['tips'] = "最高返利{$welfare->welfare_value}元" . $desc;
//        }
//
//        $data['is_comments_value']=$gameData->is_comments_value;  //备注是否必填
//        $data['message'] = $gameData->comments_value; //备注
////        print_r($data['game_order']);

        $vm = new ViewModel(
            ['res' => $data]
        );
        $vm->setTerminal(true);
        return $vm;
    }

    //活动购买选项 数量 地点选择
    public function activitybuyAction()
    {

        $id = (int)$this->getQuery('id');
        $url = $this->_getConfig()['url'] . "/web/organizer/game?id={$id}";
        $weixin = new WeiXinFun($this->getwxConfig());
        if ($this->userInit($weixin) and $this->checkWeiXinUser()) {
            $this->checkPhone($url);
        } else {
            //todo 授权失败
            header("Location: $url");
            exit;
        }
        $uid = (int)$_COOKIE['uid'];


        $gameData = $this->_getPlayOrganizerGameTable()->get(array('id' => $id, 'status'));
        if (!$gameData) {
            return $this->_Goto('该活动 不存在');
        }
        //todo  统计点击次数
        $this->_getPlayOrganizerGameTable()->update(array('click_num' => new Expression('click_num+1')), array('id' => $id));

        $res = array(
            'title' => $gameData->title,
            'cover' => $this->_getConfig()['url'] . $gameData->cover,
            'editor_word' => $gameData->editor_talk,
            'age_for' => ($gameData->age_max == 100) ? ($gameData->age_min . '岁及以上') : ($gameData->age_min . '岁到' . $gameData->age_max . '岁'),
            'process' => $gameData->process,
            'information' => $this->_getConfig()['url'] . '/web/organizer/info?type=1&gid=' . $id,
            'limit_num' => $gameData->limit_num,
            'post_number' => $gameData->post_number,
            'is_share' => $gameData->share,
            'foot_time' => $gameData->foot_time,
            'head_time' => $gameData->head_time,
            'buy' => 0,
            'total_num' => 0,
            'discount' => 10,
            'price' => $gameData->low_price,
            'money' => $gameData->large_price,
            'join_way' => array(),
            'join_time' => array(),
            //'join_shop' => array(),
            'share_img' => $this->_getConfig()['url'] . $gameData->thumb,
            'share_title' => $gameData->title,
            'share_content' => $gameData->editor_talk,
            'low_price' => $gameData->low_price,
            'large_price' => $gameData->large_price
        );

        //所有可以下单的类型
        $game_info = $this->_getPlayGameInfoTable()->fetchAll(array('gid' => $id, 'status > ?' => 0));


        foreach ($game_info as $gameData) {
            $res['buy'] = $res['buy'] + $gameData->buy;
            $res['total_num'] = $res['total_num'] + $gameData->total_num;
            $res['price'] = ($gameData->price < $res['price']) ? $gameData->price : $res['price'];
            $res['money'] = ($gameData->price > $res['money']) ? $gameData->price : $res['money'];
            $res['discount'] = ($res['discount'] > round($gameData->price / $gameData->money * 10, 1)) ? round($gameData->price / $gameData->money * 10, 1) : $res['discount'];


            $res['game_order'][] = array(
                'order_id' => $gameData->id,
                'shop_id' => $gameData->shop_id,
                'shop_name' => $gameData->shop_name,
                'way' => $gameData->price_name,
                's_time' => date('Y-m-d H:i', $gameData->start_time),
                'e_time' => date('Y-m-d H:i', $gameData->end_time),
                'price' => $gameData->price,
                'money' => $gameData->money,
                'discount' => round($gameData->price / $gameData->money * 10, 1),
                'buy' => $gameData->buy,
                'total_num' => $gameData->total_num,
            );


            if (!in_array($gameData->price_name, $res['join_way'])) {
                array_push($res['join_way'], $gameData->price_name);
            }
            /*if (!in_array($gameData->shop_name, $res['join_shop'])) {
                array_push($res['join_shop'], $gameData->shop_name);
            }*/
            if (!in_array(array($gameData->start_time, $gameData->end_time), $res['join_time'])) {
                array_push($res['join_time'], array($gameData->start_time, $gameData->end_time));
            }
        }

        //更新buy_num
        if ($res['buy'] != $gameData->buy_num) {
            $this->_getPlayOrganizerGameTable()->update(array('buy_num' => $res['buy']), array('id' => $id));
        }

        //关联的游玩地
        $shop_data = $this->_getPlayGameInfoTable()->getApiGameShopList(0, 100, array(), array('play_game_info.gid' => $id));
        foreach ($shop_data as $sData) {
            $res['shop'][$sData->shop_id] = array(
                'shop_name' => $sData->shop_name,
                'shop_id' => $sData->shop_id,
                'circle' => $sData->circle,
            );
        }


        $userData = $this->_getPlayUserTable()->get(array('uid' => $uid));
        $weixin = new WeiXinFun($this->getwxConfig());
        $res['authorUrl'] = $weixin->getAuthorUrl();
        $res['userData'] = $userData;
        return $res;
    }

    //活动票生成待支付页面
    public function activitypayAction()
    {
        if ($this->is_weixin()) {
            if (!isset($_COOKIE['open_id']) or !$_COOKIE['open_id'] or !$this->checkWeiXinUser()) {
                return $this->_Goto('访问错误');
            }
        }

        $orderId = (int)$this->getQuery('orderId');
        $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $orderId));
        $orderGameData = $this->_getPlayOrderInfoGameTable()->get(array('order_sn' => $orderData->order_sn));

        $weixin = new WeiXinPayFun($this->getwxConfig());


        $respOb = $weixin->weixinPay($_COOKIE['open_id'], $orderData->coupon_name, $orderId, $orderData->real_pay);
        if ($respOb->return_code == 'FAIL' or !$respOb) {
            //todo log
            $this->getServiceLocator()
                ->get('Logger')->crit('weixin_pay_res_log:' . print_r($respOb, true));
//            WriteLog::WriteLog('生产预支付错误:' . $respOb->return_msg);
            return $this->_Goto('网络错误，生成订单失败，请返回重试', '/web/wappay/activitypay?showwxpaytitle=1&orderId=' . $orderId);
        } else {
            //todo 生成H5调起支付参数
            $payData = array(
                'appId' => $this->getwxConfig()['appid'],
                'timeStamp' => time(),
                'nonceStr' => WeiXinFun::getNonceStr(),// 随机字符串
                'package' => 'prepay_id=' . $respOb->prepay_id,
                'signType' => "MD5", //签名方式
                // 'paySign'=>''//签名
            );
            $payData['paySign'] = $weixin->getPaySignature($payData);
        }

        return array('orderData' => $orderData, 'orderGameData' => $orderGameData, 'payData' => $payData);
    }


    //个人中心  已使用
    public function orderhistoryAction()
    {

        $weixin = new WeiXinFun($this->getwxConfig());
        if (!$this->userInit($weixin)) {
            $url = $this->_getConfig()['url'] . '/web/wappay/orderhistory';
            $toUrl = $weixin->getAuthorUrl($url, 'snsapi_userinfo');
            header("Location: $toUrl");
            exit;
        }


        $uid = $_COOKIE['uid'];
        //已付款 并且已使用      付款状态 ;0未付款;1付款中;2已付款 3  退款中 4 退款成功 5已使用
        //  $res = $this->_getPlayOrderInfoTable()->getMylist(array('user_id' => $uid, 'order_status' => 1, 'use_number>0'));

        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $res = $db->query("
SELECT
play_order_info.*,
play_coupons.coupon_close AS coupon_close,
play_coupons.coupon_cover AS coupon_cover,
play_coupons.coupon_appointment AS coupon_appointment,
play_coupons.coupon_thumb AS coupon_thumb,
play_coupons.coupon_price,
play_order_info_game.end_time AS coupon_close1,
play_order_info_game.thumb AS coupon_thumb1
FROM
play_order_info
LEFT JOIN play_coupons ON (play_coupons.coupon_id = play_order_info.coupon_id AND play_order_info.order_type = 1)
LEFT JOIN play_order_info_game ON (play_order_info.order_sn = play_order_info_game.order_sn AND play_order_info.order_type = 2)
WHERE
	`user_id` = ?
AND `order_status` = ?
AND use_number > ?
ORDER BY
	`dateline` DESC
LIMIT 1000
", array($uid, 1, 0));


        $data = array();
        foreach ($res as $v) {

            //分开赋值
            if ($v->order_type == 2) {
                $v->coupon_close = $v->coupon_close1;
                $v->coupon_thumb = $v->coupon_thumb1;
            }

            $data[] = array(
                'img' => $v->coupon_thumb ? $this->_getConfig()['url'] . $v->coupon_thumb : $this->_getConfig()['url'] . $v->coupon_cover . '.thumb.jpg',
                'title' => $v->coupon_name,
                'coupon_id' => $v->coupon_id,
                'price' => $v->coupon_unit_price,
                'use_dateline' => $v->use_dateline,
                'number' => $v->buy_number,
                'use_number' => $v->use_number,
                'tag' => $v->coupon_appointment,
                'rid' => $v->order_sn, //订单id.
                'phone' => $v->buy_phone,
                'name' => $v->buy_name,
                'order_type' => $v->order_type,
            );
        }
        return array(
            'data' => $data
        );

    }

    //个人中心  退款
    public function orderrefundAction()
    {
        $weixin = new WeiXinFun($this->getwxConfig());
        if (!$this->userInit($weixin)) {
            $url = $this->_getConfig()['url'] . '/web/wappay/orderrefund';
            $toUrl = $weixin->getAuthorUrl($url, 'snsapi_userinfo');
            header("Location: $toUrl");
            exit;
        }


        $uid = $_COOKIE['uid'];
        //已付款 并且已使用      付款状态 ;0未付款;1付款中;2已付款 3  退款中 4 退款成功 5已使用 6已过期
//        $res = $this->_getPlayOrderInfoTable()->getMylist(array('user_id' => $uid, 'order_status' => 1, '(pay_status=3 or pay_status=6 or back_number>0)'));

        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $res = $db->query("
SELECT
play_order_info.*,
play_coupons.coupon_close AS coupon_close,
play_coupons.coupon_cover AS coupon_cover,
play_coupons.coupon_appointment AS coupon_appointment,
play_coupons.coupon_thumb AS coupon_thumb,
play_coupons.coupon_price,
play_order_info_game.end_time AS coupon_close1,
play_order_info_game.thumb AS coupon_thumb1
FROM
play_order_info
LEFT JOIN play_coupons ON (play_coupons.coupon_id = play_order_info.coupon_id AND play_order_info.order_type = 1)
LEFT JOIN play_order_info_game ON (play_order_info.order_sn = play_order_info_game.order_sn AND play_order_info.order_type = 2)
WHERE
	`user_id` = ?
AND `order_status` = 1
AND (pay_status=3 or pay_status=4 or pay_status=6 or back_number>0)
ORDER BY
	`dateline` DESC
LIMIT 1000
", array($uid));


        $data = array();

        /*
         * v1.7 todo
         * ① 用户提交了退款  等待受理
         * ② 客服点击了退款完成 退款完成
         * ③ 无法退款的产品 点击了退款  退款完成
         * ④ 产品已经过期 但是不能退款 已经过期 不支持退款
         * ⑤ 产品已经过期 可以退款 退款已经提交 不支持退款
         * ⑥ 产品已经过期 可以退款 退款已确认 不支持退款
         */

        foreach ($res as $v) {

            if ($v->pay_status == 3) {
                $status = '退款中';  //0;  //退款中
            } elseif ($v->pay_status == 6) {
                $status = '已过期';  // 2;  //已过期
            } else {
                $status = '已退款'; // 1; //已退款
            }

            //分开赋值
            if ($v->order_type == 2) {
                $v->coupon_close = $v->coupon_close1;
                $v->coupon_thumb = $v->coupon_thumb1;
            }


            $data[] = array(
                'img' => $v->coupon_thumb ? $this->_getConfig()['url'] . $v->coupon_thumb : $this->_getConfig()['url'] . $v->coupon_cover . '.thumb.jpg',
                'title' => $v->coupon_name,
                'coupon_id' => $v->coupon_id,
                'price' => $v->coupon_unit_price,
                'number' => $v->buy_number,
                'status' => $status,
                'tag' => $v->coupon_appointment,
                'rid' => $v->order_sn, //订单id.
                'phone' => $v->buy_phone,
                'name' => $v->buy_name,
                'order_type' => $v->order_type,
            );
        }
        return array('data' => $data, '');
    }

    //个人中心 待使用 老
    public function orderavailableAction()
    {
        header("location:".$this->_getConfig()['url'].'/web/wappay/allorder');exit();
        $weixin = new WeiXinFun($this->getwxConfig());
        if (!$this->userInit($weixin)) {
            $url = $this->_getConfig()['url'] . '/web/wappay/orderavailable';
            $toUrl = $weixin->getAuthorUrl($url, 'snsapi_userinfo');
            header("Location: $toUrl");
            exit;
        }
        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $uid = $_COOKIE['uid'];
        $weixin_data = $this->_getPlayUserWeiXinTable()->get(array('uid' => $uid, 'appid' => $weixin->getappid()));
        $open_id = $weixin_data->open_id;


        //主动更新微信用户数据
        if (!$weixin_data->country) {
            $this->updateWeiXinData($weixin, $uid, $open_id, false);
        }


        //查找当前用户open_id  通过openid 查询用户老的uid  通过uid查询老订单 2015-12-21 10:38:24
        $old_uid = 0;  //不存在
        $old_userdata = $db->query("SELECT * FROM play_user_weixin_bak where open_id=? AND uid<73970", array($open_id))->current();
        if ($old_userdata) {
            if ($old_userdata->uid != $uid) {
                $old_uid = $old_userdata->uid;  //todo 老uid
            }

        }

        $res = $db->query("
SELECT
play_order_info.*,

play_coupons.coupon_cover AS coupon_cover,
play_coupons.coupon_appointment AS coupon_appointment,
play_coupons.coupon_thumb AS coupon_thumb,

play_coupons.coupon_starttime AS coupon_starttime,
play_coupons.coupon_close AS coupon_close,
play_coupons.refund_time AS refund_time1,

play_order_info_game.thumb AS coupon_thumb1,
play_order_info_game.end_time AS coupon_close1,
play_order_info_game.type_name AS type_name,

play_order_info_game.start_time AS start_time,
play_order_info_game.end_time AS end_time,
play_order_info_game.address AS address,
play_coupon_code.id AS code_id,
play_coupon_code.password AS password,
play_coupon_code.status AS code_status,
play_organizer_game.refund_time AS refund_time2

FROM
	`play_order_info`
LEFT JOIN `play_coupons` ON (`play_coupons`.`coupon_id` = `play_order_info`.`coupon_id` and play_order_info.order_type=1)
LEFT JOIN  `play_order_info_game` ON (`play_order_info`.`order_sn`=`play_order_info_game`.`order_sn` and `play_order_info`.`order_type`=2)
LEFT JOIN  `play_organizer_game` ON (`play_organizer_game`.`id`=`play_order_info`.`coupon_id` and `play_order_info`.`order_type`=2)


LEFT JOIN `play_coupon_code` ON `play_coupon_code`.`order_sn` = `play_order_info`.`order_sn`
WHERE
	(`user_id` = ? OR `user_id`= ? OR (play_order_info.account=? AND play_order_info.account_type='jsapi'))
AND `order_status` = 1
AND pay_status >= 2
AND pay_status < 5
AND `play_coupon_code`.`status` = 0
-- GROUP BY
-- play_coupon_code.order_sn
ORDER BY
play_order_info.dateline DESC
LIMIT 1000
", array($uid, $old_uid, $open_id));
        $data = array();
        foreach ($res as $v) {
            //分开赋值
            if ($v->order_type == 2) {
                $v->coupon_close = $v->coupon_close1;
                $v->coupon_thumb = $v->coupon_thumb1;
            }

            $refund_time = $v->refund_time1 ? $v->refund_time1 : $v->refund_time2;
            $data[] = array(
                'order_sn' => $v->order_sn,
                'img' => $v->coupon_thumb ? $this->_getConfig()['url'] . $v->coupon_thumb : $this->_getConfig()['url'] . $v->coupon_cover . '.thumb.jpg',
                'title' => $v->coupon_name,
                'coupon_id' => $v->coupon_id,
                'price' => $v->coupon_unit_price,
                'number' => $v->buy_number,
                'pay_status' => $v->pay_status >= 2 ? 1 : 0,
                'tag' => $v->coupon_appointment,  //是否免预约
                'rid' => $v->order_sn, //订单id.
                'phone' => $v->buy_phone,
                'name' => $v->buy_name,
                'coupon_close' => $v->coupon_close,  //普通
                'coupon_starttime' => $v->coupon_starttime,//普通
                'status' => $v->code_status, //活动
                'start_time' => $v->start_time,  //活动
                'end_time' => $v->end_time,       //活动
                'order_type' => $v->order_type,
                'type_name' => $v->type_name,  //活动
                'address' => $v->address,  //活动
                'password' => $v->code_id . $v->password,  //password
                //是否允许退款  1 允许 2 不允许
                'refund' => $refund_time > time() ? 1 : 2,
                'refund_time' => $refund_time
            );
        }

        $weixin = new WeiXinFun($this->getwxConfig());

        return array('data' => $data, 'codeStatus' => $this->_getConfig()['coupon_code_status'], 'authorUrl' => $weixin->getAuthorUrl());
    }

    //活动票 支付后订单详情
    public function orderactivityAction()
    {

        $weixin = new WeiXinFun($this->getwxConfig());
        $orderId = (int)$this->getQuery('orderId');
        $is_pay = (int)$this->getQuery('is_pay', 0);//是否支付条转过来
        if (!$this->userInit($weixin)) {
            $url = $this->_getConfig()['url'] . '/web/wappay/orderactivity?orderId=' . $orderId;
            $toUrl = $weixin->getAuthorUrl($url, 'snsapi_userinfo');
            header("Location: $toUrl");
            exit;
        }

        $uid = $_COOKIE['uid'];
        $open_id = $_COOKIE['open_id'];

        // 检查是否关注微信
        $subscribe = 1;
        if($is_pay==1){
            $weixin_data = $this->_getPlayUserWeiXinTable()->get(array('appid' => $weixin->getappid(), 'open_id' => $open_id));
            if ($weixin_data and $weixin_data->subscribe == 1) {
                $subscribe = 1;
            } else {
                $userInfo = $weixin->getOdinaryUserInfo($open_id);

                if ($userInfo) {
                    if ($userInfo->subscribe == 1) {
                        //主动初始化微信数据
                        $subscribe = 1;
                    } else {
                        $subscribe = 0;
                    }
                    //更新用户微信数据
                    $this->updateWeiXinData($weixin, $uid, $open_id, $userInfo);
                }

            }
        }



        $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $orderId));
        $orderGameInfo = $this->_getPlayOrderInfoGameTable()->get(array('order_sn' => $orderId));
        $id = $orderData->coupon_id;
        $gameData = $this->_getPlayOrganizerGameTable()->get(array('id' => $id));

        if (!$gameData) {
            exit('<h1>活动不存在</h1>');
        }
        if ($orderData->user_id != $uid) {
            exit('<h1>未授权</h1>');
        }
        $res = array(
            'title' => $gameData->title,
            //'cover' => $this->_getConfig()['url'] . $gameData->cover,
            'editor_word' => $gameData->editor_talk,
            'age_for' => ($gameData->age_max == 100) ? ($gameData->age_min . '岁及以上') : ($gameData->age_min . '岁到' . $gameData->age_max . '岁'),
            'process' => $gameData->process,
            'information' => htmlspecialchars_decode($gameData->information),
            'limit_num' => $gameData->limit_num,
            'buy' => 0,
            'total_num' => 0,
            'discount' => 10,
            'price' => 0,
            'money' => 0,
            'refund_time' => $gameData->refund_time
        );


        $gameData = $this->_getPlayGameInfoTable()->get(array('id' => $orderGameInfo->game_info_id));
        $res['buy'] = $res['buy'] + $gameData->buy;
        $res['total_num'] = $res['total_num'] + $gameData->total_num;
        $res['price'] = $orderData->coupon_unit_price;
        $res['money'] = $gameData->money;
        $res['discount'] = $this->getDiscount($res['money'], $res['price']);


        //活动组织者
        $organizer_data = $this->_getPlayOrganizerTable()->get(array('id' => $gameData->organizer_id));
        $res['organizer'] = array(
            'organizer_id' => $organizer_data->id,
            'name' => $organizer_data->name,
            'phone' => $organizer_data->phone,
            'address' => $organizer_data->address,
            'addr_x' => $organizer_data->addr_x,
            'addr_y' => $organizer_data->addr_y,
        );

        //关联的游玩地
        $shop_data = $this->_getPlayGameTimeTable()->getAdminGameShopList(0, 100, array(), array('play_game_time.gid' => $id));
        foreach ($shop_data as $sData) {
            $res['shop'][] = array(
                'shop_name' => $sData->shop_name,
                'shop_id' => $sData->shop_id,
                'circle' => $sData->circle,
            );
        }
        $codes = $this->_getPlayCouponCodeTable()->fetchAll(array('order_sn' => $orderId));
        return array(
            'res' => $res,
            'codes' => $codes,
            'orderData' => $orderData,
            'orderGameInfo' => $orderGameInfo,
            'codeStatus' => $this->_getConfig()['coupon_code_status'],
            'subscribe' => $subscribe, //是否关注
            'is_pay' => $is_pay,
        );

    }

    //新注册
    public function registerAction()
    {
        $type = (int)$this->getQuery('type');
        $weixin = new WeiXinFun($this->getwxConfig());
        if(isset($_COOKIE['open_id']) || $_COOKIE['open_id']){
            $wei_city = $weixin->getOdinaryUserInfo($_COOKIE['open_id']);
        }
        if ($this->is_weixin()) {
            $authorUrl = $weixin->getAuthorUrl();
            if($type !=1){
                if(isset($_COOKIE['phone']) || $_COOKIE['phone']){

                    header("location:".$this->_getConfig()['url']."/web/wappay/my");
                }else{
                    if ($this->userInit($weixin) and $this->checkWeiXinUser()) {
                    } else {
                        // 授权失败
                        header("Location: {$authorUrl}");
                        exit;
                    }
                }
            }
        }


        //绑定手机号页面  成功后跳转到tourl
        $type=(int)$this->getQuery('type');
        $uid = $_COOKIE['uid'];
        $tourl = $this->getQuery('tourl') != '' ? $this->getQuery('tourl') : $this->_getConfig()['url'] . '/web/wappay/nindex';


       // echo $tourl;

        $backurl = $this->getQuery("backurl");

        $vm = new ViewModel(
            ['uid' => $uid,
                'tourl' => $tourl,
                'backurl' => $backurl,
                'ver_url' => "/user/login/register",
                'wap' => $this->is_weixin() == true ? 0 : 1,
//                'authorUrl' => $authorUrl,
                'type'=>$type,
                'city'=>$wei_city->city
            ]
        );
        $vm->setTerminal(true);
        return $vm;
    }

    //老绑定手机号
    public function checkPhone($backUrl)
    {
        if (!isset($_COOKIE['phone']) || !$_COOKIE['phone']) {
            //临时查询用户是否已绑定手机号
            $user_data = $this->_getPlayUserTable()->get(array('uid' => (int)$_COOKIE['uid']));
            if ($user_data->phone) {
                $untime = time() + 3600 * 24 * 17;  //失效时间
                setcookie('phone', $user_data->phone, $untime, '/');
                return true;
            } else {
                $url = $this->_getConfig()['url'] . "/web/wappay/register?uid={$_COOKIE['uid']}&tourl=" . urlencode($backUrl);
                header("Location: $url");
                exit;
            }

        }
    }


    private function getDiscount($price, $nowprice)
    {
        $price = floatval($price);
        $nowprice = floatval($nowprice);
        //$discount折扣计算
        if ($nowprice > 0) {
            $discount = round(10 / ($price / $nowprice), 1);
        } else {
            $discount = 0;
        }
        if ($discount <= 0) {
            //折扣超过1折
            $discount = 0;
        }
        return $discount;
    }


    // 清理用户信息
    public function cleanAction()
    {
        $untime = time();  //失效时间
        setcookie('uid', 0, $untime, '/');
        setcookie('token', 0, $untime, '/');
        setcookie('open_id', 0, $untime, '/');
        return $this->_Goto('清理成功');
    }


    //初始化用户 生成用户 生成验证信息
    public function userInit(WeiXinFun $weixin)
    {
        //检查当前cookie是否对应当前服务号
        if($_COOKIE['open_id']){
            $res=$this->_getPlayUserWeiXinTable()->get(array('appid'=>$weixin->getappid(),'open_id'=>$_COOKIE['open_id']));
            if(!$res){ //不属于此服务号
                $untime=time()-3600;
                setcookie('uid', 0,$untime , '/','.wanfantian.com');
                setcookie('token', 0, $untime, '/','.wanfantian.com');
                setcookie('open_id', 0, $untime, '/','.wanfantian.com');
                setcookie('phone', 0, $untime, '/','.wanfantian.com');
            }
        }

        if (!$this->checkWeiXinUser()) {
            if (isset($_GET['code'])) {
                //todo 封装  存储相关信息，获取用户信息，生成cookie
                $accessTokenData = $weixin->getUserAccessToken($_GET['code']);

                if (isset($accessTokenData->access_token)) {
                    $token = md5(time() . $accessTokenData->access_token);
                    //先查询用户是否存在
                    $user_data = false;
                    if (!$accessTokenData->unionid) {
                        $accessTokenData->unionid = -1;
                    }
                    $user = $this->_getPlayUserWeiXinTable()->getUserInfo("play_user_weixin.open_id='{$accessTokenData->openid}' or play_user_weixin.unionid='{$accessTokenData->unionid}'");

                    if ($user) {
                        $user_data = $this->_getPlayUserTable()->get(array('uid' => $user->uid));
                    }
                    if ($user && $user_data) {
                        //初始化当前新微信号数据
                        $weixin = $this->_getPlayUserWeiXinTable()->get(array('open_id' => $accessTokenData->openid));
                        if (!$weixin) {
                            $this->_getPlayUserWeiXinTable()->insert(array(
                                'uid' => $user->uid,
                                'appid' => $this->getwxConfig()['appid'],
                                'open_id' => $accessTokenData->openid,
                                'unionid' => isset($accessTokenData->unionid) ? $accessTokenData->unionid : '',
                                'access_token_wap' => $accessTokenData->access_token,
                                'refresh_token_wap' => $accessTokenData->refresh_token,
                                'login_type' => 'weixin_wap', //微信授权表改为通用授权表
                            ));
                        }

                        $this->setCookie($user->uid, $user->token, $accessTokenData->openid, $user_data->phone);
                        return true;
                    } else {
                        if ($accessTokenData->scope == 'snsapi_userinfo') {
                            $userInfo = $weixin->getUserInfo($accessTokenData->access_token);
                            if (!$userInfo) {
                                //todo 错误处理机制
                                WriteLog::WriteLog('获取userInfo错误:' . print_r($userInfo, true));
                                return false;
                            }
                            $username = $userInfo->nickname;
                            $img = $userInfo->headimgurl;

                        } else {
                            $username = 'WeiXin' . time();
                            $img = '';
                        }

                        $this->_getPlayUserTable()->insert(array(
                            'username' => $username ? $username : '　',//用户名不能为空的BUG
                            'password' => '',
                            'token' => $token,
                            'mark_info' => 0,
                            'login_type' => 'weixin_wap',
                            'is_online' => 1,
                            'device_type' => '',
                            'dateline' => time(),
                            'status' => 1,
                            'img' => $img,
                            'city'=>$this->getCity()
                        ));
                        $uid = $this->_getPlayUserTable()->getlastInsertValue();
                        $status = $this->_getPlayUserWeiXinTable()->insert(array(
                            'uid' => $uid,
                            'appid' => $this->getwxConfig()['appid'],
                            'open_id' => $accessTokenData->openid,
                            'unionid' => isset($accessTokenData->unionid) ? $accessTokenData->unionid : '',
                            'access_token_wap' => $accessTokenData->access_token,
                            'refresh_token_wap' => $accessTokenData->refresh_token,
                            'login_type' => 'weixin_wap', //微信授权表改为通用授权表
                        ));

                        $this->setCookie($uid, $token, $accessTokenData->openid);

                        if (!$status) {
                            return false;
                        } else {
                            return true;
                        }

                    }
                } else {
                    //todo 错误处理机制
                    WriteLog::WriteLog('获取userAccessToken错误:' . print_r($accessTokenData, true));
                    return false;
                }
            } else {
                //todo 如果用户点了拒绝
                return false;
            }
        } else {
            return true;
        }
    }

    //主动更新用户微信数据
    private function updateWeiXinData(WeiXinFun $weixin, $uid, $open_id, $weixin_data = false)
    {
        if ($weixin_data) {
            $s = $this->_getPlayUserWeiXinTable()->update(array(
                'unionid' => $weixin_data->unionid,
                'subscribe' => $weixin_data->subscribe,
                'nickname' => $weixin_data->nickname,
                'sex' => $weixin_data->sex,
                'language' => $weixin_data->language,
                'city' => $weixin_data->city,
                'province' => $weixin_data->province,
                'country' => $weixin_data->country,
                'subscribe_time' => $weixin_data->subscribe_time,
                'groupid' => $weixin_data->groupid
            ), array('uid' => $uid, 'open_id' => $open_id, 'appid' => $weixin->getappid()));
        } else {
            $weixin_data = $weixin->getOdinaryUserInfo($open_id);

            $s = $this->_getPlayUserWeiXinTable()->update(array(
                'unionid' => $weixin_data->unionid,
                'subscribe' => $weixin_data->subscribe,
                'nickname' => $weixin_data->nickname,
                'sex' => $weixin_data->sex,
                'language' => $weixin_data->language,
                'city' => $weixin_data->city,
                'province' => $weixin_data->province,
                'country' => $weixin_data->country,
                'subscribe_time' => $weixin_data->subscribe_time,
                'groupid' => $weixin_data->groupid
            ), array('uid' => $uid, 'open_id' => $open_id, 'appid' => $weixin->getappid()));

        }
        return (int)$s;
    }

    //待付款 ok
    public function orderwaitAction()
    {
        $vm = new ViewModel();
        $vm->setTerminal(true);
        return $vm;
    }

    //去支付
    public function dopayAction(){
        $order_sn = trim($this->getPost("order_sn"));
        $weixin = new WeiXinPayFun($this->getwxConfig());
        $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));
        $respOb = $weixin->weixinPay($_COOKIE['open_id'], $orderData->coupon_name, $order_sn, $orderData->real_pay);
//            todo 生成H5调起支付参数
        $time = time();
        $payData = array(
            'appId' => $this->getwxConfig()['appid'],
            'timeStamp' => "$time",
            'nonceStr' => WeiXinFun::getNonceStr(),// 随机字符串
            'package' => 'prepay_id=' . $respOb->prepay_id,
            'signType' => "MD5", //签名方式
            // 'paySign'=>''//签名
        );
        $payData['paySign'] = $weixin->getPaySignature($payData);
        return $this->jsonResponse(array('payData'=>$payData,'orderData'=>$orderData,'status'=>1,'respOb'=>$respOb));
    }

    //待使用订单
    public function newallordersAction()
    {
        header("Location:".$this->_getConfig()['url'].'/web/wappay/allorder');
        exit;
        $vm = new ViewModel();
        $vm->setTerminal(true);
        return $vm;

    }

    //待评价订单
    public function ordersortAction()
    {
        $vm = new ViewModel();
        $vm->setTerminal(true);
        return $vm;
    }

    //已完成订单
    public function ordersuccessAction()
    {
        $vm = new ViewModel();
        $vm->setTerminal(true);
        return $vm;
    }

    //我的同玩3个
    public function myteamAction(){
        $vm = new ViewModel();
        $vm->setTerminal(true);
        return $vm;
    }

    public function myteamfailAction(){
        $vm = new ViewModel();
        $vm->setTerminal(true);
        return $vm;
    }
    public function myteamsucAction(){
        $vm = new ViewModel();
        $vm->setTerminal(true);
        return $vm;
    }

    //mybaby
    public function mybabyAction(){
        $vm = new ViewModel();
        $vm->setTerminal(true);
        return $vm;
    }

    public function babyinfoAction(){
        $id = $this->getQuery('id');
        $act = $this->getQuery('act');
//        RedCache::del('D:BabyInfo:'.$id);
        if($id>0){
            $db = $this->_getAdapter();
            $data = $db->query("select * from play_user_baby where id=?",array($id))->toArray();
        }
        $vm = new ViewModel([
            'data'=>$data[0],
            'link_id'=>$id,
            'act'=>$act
        ]);
        $vm->setTerminal(true);
        return $vm;

    }

    //我的收藏
    public function mycollectAction(){
        $url = $this->_getConfig()['url'] . "/web/wappay/my";
        $this->WeiXinArc($url);
        //todo 获取三类收藏数据 其实用一个接口全部返回就好了 不用分次请求
        $post_array_shop = $post_array_good = $post_array_kidsplay = array('uid' => $_COOKIE['uid'], 'page' => 1);

        $post_array_shop['type'] = "shop";
        $post_array_good['type'] = "good";
        $post_array_kidsplay['type'] = "kidsplay";

        $url = $this->_getConfig()['url'] . '/user/collect/shopList';

        $vm = new ViewModel([
            'shop' => $this->post_curl($url, $post_array_shop, '', $_COOKIE),
            'good' => $this->post_curl($url, $post_array_good, '', $_COOKIE),
            'kidsplay' => $this->post_curl($url, $post_array_kidsplay, '', $_COOKIE),
        ]);
        $vm->setTerminal(true);
        return $vm;
    }

    public function mycollectTranAction(){
        $data = $this->getQuery('type'); // 评论id
//        return $data;     exit;
        $url = $this->_getConfig()['url'] . '/user/collect/shopList';

        print $data;
    }

    //秒杀
    public function seckillAction(){
        $type = (int)$this->getQuery('type',0);
        $weixin = new WeiXinFun($this->getwxConfig());
        $url = $this->_getConfig()['url'].'/web/wappay/seckill';
        $authorUrl = $weixin->getAuthorUrl($url,"snsapi_userinfo");

        $vm = new ViewModel([
            'type'=>$type,
            'authorUrl'=>$authorUrl
        ]);
        $vm->setTerminal(true);
        return $vm;
    }

    //修改个人资料
    public function editAction(){
//        $weixin = new WeiXinFun($this->getwxConfig());
//        $authorUrl = $weixin->getAuthorUrl();
//        if ($this->userInit($weixin) and $this->checkWeiXinUser()) {
//        } else {
//            // 授权失败
//            header("Location: {$authorUrl}");
//            exit;
//        }
    }

    public function editnameAction(){
        $user = trim($this->getQuery('user',null));

        $vm = new ViewModel([
            'name'=>$user
        ]);
        $vm->setTerminal(true);
        return $vm;

    }


    //订单详情
    public function orderdetailAction()
    {
        $weixin = new WeiXinFun($this->getwxConfig());
        if ($this->is_weixin()) {
            if (!$this->pass()) {
                return $this->jsonResponseError('接口验证失败', Response::STATUS_CODE_403);
            }
            if (!$this->userInit($weixin)) {
                $url = $this->_getConfig()['url'] . '/web/wappay/orderdetail';
                $toUrl = $weixin->getAuthorUrl($url, 'snsapi_userinfo');
                header("Location: $toUrl");
                exit;
            }

        }

        $uid = $_COOKIE['uid'];
        if (!$uid) {
            header("Location: /web/wappay/register?tourl=/web/wappay/orderdetail");
        }

        $orderId = $this->getQuery('orderId');

        $orderInfo = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $orderId, 'order_status' => 1));
        if (!$orderInfo || $orderInfo->user_id != $uid) {
            return $this->jsonResponseError('该订单不存在');
        }
        $coupon_id= $orderInfo->coupon_id;
        $city = $this->getCity();
        //请求的url
        $url = $this->_getConfig()['url'].'/good/index/nhave';
        $json = $this->post_curl($url,array("id"=>$coupon_id,"rid"=>$orderId),$city,$_COOKIE);
        $data = json_decode($json);
        if($data->response_params->associates){
            $ids ='';
            foreach($data->response_params->associates as $k=>$v){
                $ids.=$v->associates_id.',';
            }
        }

        //分享
        $jswxconfig = $this->getShareInfoAction()[1];
        $share = array(
            'img'=>$data->response_params->share_image,
            'title'=>$data->response_params->share_title,
            'desc'=>$data->response_params->share_content,
            'link'=>$data->response_params->share_url,
        );

        $vm = new ViewModel([
            'data'=>$data->response_params,
            'ids'=>$ids,
            'jsconfig'=>$jswxconfig,
            'share'=>$share,
            'share_type'=>'buygoods',
            'share_id'=>$orderId
        ]);
        $vm->setTerminal(true);
        return $vm;

    }


    //个人中心页面
    public function MyAction(){
        $url = $url = $this->_getConfig()['url'].'/web/wappay/my';
//        if($this->is_weixin()){
//            $this->WeiXinArc($url);
//        }
        $uid = $_COOKIE['uid'];
        $post_url = $this->_getConfig()['url'].'/user/info';
        $json = $this->post_curl($post_url,array("uid"=>$uid),"武汉",$_COOKIE,10);
        $data = json_decode($json,true);
        //分享
        $jswxconfig = $this->getShareInfoAction()[1];
        $share = array(
            'img'=>$this->_getConfig()['url'].'/images/80.png',
            'title'=>'【玩翻天】孩子们的游玩管家',
            'desc'=>'我发现了一个家长必备的遛娃神器！告别无趣，拯救宅神！快来带娃玩翻天！',
            'link'=>$this->_getConfig()['url'].'/web/wappay/nindex',
        );

        $vm = new ViewModel([
            'data'=>$data['response_params'],
            'share_type'=>'nindex',
            'share_id'=>0,
            'jsconfig'=>$jswxconfig,
            'share'=>$share,
        ]);
        $vm->setTerminal(true);
        return $vm;
    }

    //我的积分
    public function integralAction(){
        $url = $this->_getConfig()['url'] . "/web/wappay/my";
        $this->WeiXinArc($url);
        $vm = new ViewModel();
        $vm->setTerminal(true);
        return $vm;
    }

    //积分规则
    public function integralruleAction(){
        $vm = new ViewModel();
        $vm->setTerminal(true);
        return $vm;
    }

    //新收银台
    public function paymentAction(){
        if ($this->is_weixin()) {
            if (!isset($_COOKIE['open_id']) or !$_COOKIE['open_id'] or !$this->checkWeiXinUser()) {
                $weixin = new WeiXinFun($this->getwxConfig());

                if (!$this->userInit($weixin)) {

                     //刷新本页
                    $url = $_SERVER['REQUEST_URI'];
                    $config = $this->_getConfig();
                    $host = $config['url'];

                    $target = $host . $url;

                    //$url = $this->_getConfig()['url'] . '/web/wappay/payment';
                    $toUrl = $weixin->getAuthorUrl($target, 'snsapi_userinfo');
                    header("Location: $toUrl");
                    exit;
                }
            }
        }

        $share_order_sn = $_COOKIE['share_order_sn'];
        $uid = (int)$_COOKIE['uid'];
//        $uid = 70623;
        $pay_pwd = $this->_getPlayAccountTable()->get(array('uid'=>$uid))->password;
        $account = new Account();
        $surplus = $account->getUserMoney($uid);
        $coupon_id = (int)$this->getQuery('coupon_id');
        $city = urldecode($this->getQuery('city'));
        $number = (int)$this->getQuery('number');
        $name = trim($this->getQuery('name'));
        $phone = trim($this->getQuery('phone'));
        $link_address = trim($this->getQuery('address'));
        $associates_ids = trim($this->getQuery('associates_ids'));
        $order_id = (int)$this->getQuery('order_id');
        $group_buy = (int)$this->getQuery('group_buy');
        $group_buy_id = (int)$this->getQuery('group_buy_id');
        $message =trim($this->getQuery('message'));
        $cashcoupon_id = $this->getQuery('cashcoupon_id');
        $flag = $this->getQuery('flag');

//        $flag = $flag === 'null' ? -1 : intval($flag);


        //新增活动
        $sid = (int)$this->getQuery('session_id',0);//场次id
        $total = trim($this->getQuery('total',0));
        $meeting = (int)$this->getQuery('meet_id',0);

        $weixin = new WeiXinPayFun($this->getwxConfig());
        if($sid){
            $event_data = $this->_getPlayExcerciseEventTable()->get(array('id'=>$sid));
            $base_data = $this->_getPlayExcerciseBaseTable()->get(array('id'=>$event_data->bid));
            $coupon_data = (object)array();
            $orderGameData = (object)array();
            $coupon_data->title=$base_data->name;
            $coupon_data->id=$event_data->id;
            $coupon_data->base_id=$base_data->id;
            $orderGameData->start_time =$event_data->start_time;
            $orderGameData->end_time =$event_data->end_time;
            $Url = $this->_getConfig()['url']."/web/wappay/payment?session_id={$sid}&name={$name}&phone={$phone}&address={$link_address}&associates_ids={$associates_ids}&total={$total}&city={$city}";
            $authorUrl = $weixin->getAuthorUrl($Url);
            $back_url = "payment?session_id={$sid}&name={$name}&phone={$phone}&address={$link_address}&associates_ids={$associates_ids}&total={$total}&city={$city}";
        }else{
            $coupon_data = $this->_getPlayOrganizerGameTable()->get(array("id"=>$coupon_id));
            $orderGameData = $this->_getPlayGameInfoTable()->get(array('id' => $order_id));
            $Url = $this->_getConfig()['url']."/web/wappay/payment?coupon_id={$coupon_id}&number={$number}&name={$name}&phone={$phone}&order_id={$order_id}&group_buy={$group_buy}&group_buy_id={$group_buy_id}&message={$message}&city={$city}";
            $authorUrl = $weixin->getAuthorUrl($Url);
            $back_url = "payment?coupon_id={$coupon_id}&number={$number}&name={$name}&phone={$phone}&order_id={$order_id}&group_buy={$group_buy}&group_buy_id={$group_buy_id}&message={$message}&city={$city}";
        }

        //获取该订单可用的最大金额现金券
        $cashcoupon_id = (int)$this->getQuery("cashcoupon_id",0);
        $adapter = $this->_getAdapter();
        if($cashcoupon_id){
            $cashcoupon_data = $adapter->query("SELECT * FROM play_cashcoupon_user_link WHERE `id` = ?  AND  uid=?", array($cashcoupon_id,$uid))->toArray();
            $cashcoupon_data = $cashcoupon_data[0];
        }else{
            $post['uid'] = $uid;
            $post['pagenum']=1;
            $post['type']= $sid >0 ? 2 : 1;
            $post['coupon_id'] = $sid>0 ? $sid : $coupon_id;
            $post['pay_price'] = $sid>0 ? $total : $orderGameData->price;
            $url = $this->_getConfig()['url'].'/cashcoupon/index/my';
            $json = $this->post_curl($url,$post,$city,$_COOKIE,10);
            $data = json_decode($json,true);
            $cashcouponData = $data['response_params'][0];
            $City = array_flip(CityCache::getCities())[$city];
            $db = $this->_getAdapter();

            $cash_city = $db->query("select * from play_cashcoupon_user_link where id=? and uid=? AND city=?",array($cashcouponData['id'],$uid,$City))->toArray();
            if($cash_city){
                $cashcoupon_data = $cashcouponData;
            }
        }

        $vm = new ViewModel([
            'coupon_data' => $coupon_data,
            'orderGameData' => $orderGameData,
            'surplus'=>$surplus,
            'group_buy_id'=>$group_buy_id,
            'pay_pwd'=>$pay_pwd,
            'authorUrl'=>$authorUrl,
            'number'=>$number,
            'name'=>$name,
            'phone'=>$phone,
            'group_buy'=>$group_buy,
            'message'=>$message,
            'cashcoupon_data'=>$cashcoupon_data,
            'back_url'=>$back_url,
            'total'=>$sid>0 ? $total : $number*$orderGameData->price,
            'address'=>$link_address,
            'associates_ids'=>$associates_ids,
            'sid'=>$sid,
            'meet'=>$meeting,
            'city'=>$city,
            'share_order_sn'=>$share_order_sn,
            'cashcoupon_id' => $cashcoupon_id,
            'flag' => $flag
        ]);
        $vm->setTerminal(true);
        return $vm;
    }


    //已完成订单数
    private function order_complete($uid)
    {
        return 0;  //不需要

        $db = $this->_getAdapter();
        $res = $db->query("
SELECT
*
FROM
play_order_info
WHERE
	`user_id` = ?
AND `order_status` = ?
AND pay_status IN (4,5)
AND play_order_info.order_type = 2
", array($uid, 1));
        return $res->count();
    }

    //待使用
    private function getunuse_coupon($uid)
    {
        //todo 添加缓存构建层
        $db = $this->_getAdapter();

        $res = $db->query("
SELECT
*
FROM
	`play_order_info`
LEFT JOIN `play_coupon_code` ON `play_coupon_code`.`order_sn` = `play_order_info`.`order_sn`
WHERE
	`user_id` = ?
AND `order_status` = 1
AND pay_status IN (2,3)
AND play_order_info.order_type = 2
GROUP BY
play_coupon_code.order_sn
", array($uid));
        return $res->count();
    }


    //待付款
    private function getneed_pay($uid)
    {
        $db = $this->_getAdapter();
        $res = $db->query("
SELECT
*
FROM
play_order_info
WHERE
	`user_id` = ?
AND `order_status` = ?
AND pay_status <= ?
AND play_order_info.order_type = 2
", array($uid, 1, 1));
        return $res->count();
    }

    //待评价
    private function getneed_comment($uid)
    {
        $db = $this->_getAdapter();
        $res = $db->query("
SELECT
*
FROM
play_order_info
LEFT JOIN play_order_otherdata ON play_order_info.order_sn = play_order_otherdata.order_sn
WHERE
	`user_id` = ?
AND `order_status` = ?
AND `use_number` > 0
AND `comment`=0
AND play_order_info.order_type = 2
ORDER BY
	`dateline` DESC
LIMIT 1000
", array($uid, 1));

        return $res->count();
    }


    //个人账户
    public function accountAction()
    {
        $uid = $_COOKIE['uid'];
        $url = $this->_getConfig()['url'] . "/web/wappay/account";
        $this->WeiXinArc($url);
        $vm = new ViewModel();
        $vm->setTerminal(true);
        return $vm;
    }

    //wap新首页
    public function nIndexAction(){
        $weixin = new WeiXinFun($this->getwxConfig());
        if($this->is_weixin()){
            $url = $this->_getConfig()['url'] . "/web/wappay/nindex";
            $toUrl = $weixin->getAuthorUrl($url, 'snsapi_userinfo');
            if (!$this->userInit($weixin) and !$this->checkWeiXinUser()) {
                header("Location: $toUrl");
                exit;
            }
        }
        //分享
        $jswxconfig = $this->getShareInfoAction()[1];
        $share = array(
            'img'=>$this->_getConfig()['url'].'/images/80.png',
            'title'=>'【玩翻天】孩子们的游玩管家',
            'desc'=>'我发现了一个家长必备的遛娃神器！告别无趣，拯救宅神！快来带娃玩翻天！',
            'link'=>$this->_getConfig()['url'].'/web/wappay/nindex',
        );

        $vm = new ViewModel([
            'authorUrl'=>$toUrl,
            'share_type'=>'nindex',
            'share_id'=>0,
            'jsconfig'=>$jswxconfig,
            'share'=>$share,
        ]);
        $vm->setTerminal(true);
        return $vm;
    }

    //同玩订单详情
    public function groupinfoAction()
    {
        $url = $this->_getConfig()['url'] . "/web/wappay/my";
        $this->WeiXinArc($url);
        $vm = new ViewModel();
        $vm->setTerminal(true);
        return $vm;
    }

    //切换城市
    public function cityAction(){
        $data = array();
        $cityData = $this->_getPlayCityTable()->fetchAll(array('is_close' => 0, 'is_hot' => 1));
        $data['hot_city'] = array();
        foreach ($cityData as $city) {
            $data['hot_city'][] = array(
                'name' => $city->city_name,
                'city_img' => $this->_getConfig()['url']. $city->city_img,
            );
        }
        $vm = new ViewModel([
            'data'=>$data,
        ]);
        $vm->setTerminal(true);
        return $vm;
    }

    //我的现金券
    public function myCashCouponAction(){
        $url = $this->_getConfig()['url'] . "/web/wappay/my";
        $type=$this->getQuery('type');
        $tourl=$this->getQuery('tourl');
        $pay_price=$this->getQuery('pay_price');
        $sid=$this->getQuery('sid');
        $coupon_id=$this->getQuery('coupon_id');
        $city=$this->getQuery('city');
//        $this->WeiXinArc($url);
        $vm = new ViewModel([
            'type'=>$type,
            'tourl'=>$tourl,
            'pay_price'=>$pay_price,
            'sid'=>$sid,
            'coupon_id'=>$coupon_id,
            'city'=>$city,
        ]);
        $vm->setTerminal(true);
        return $vm;
    }

    function query($sql)
    {
        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $stmt = $db->query($sql);
        $result = $stmt->execute($stmt);

        return $result;
    }

    public function paycodeAction(){

    }


    //设置支付密码
    public function setpaypwdAction(){
        $url = $this->_getConfig()['url'] . "/web/wappay/setpaypwd";
        $weixin = new WeiXinFun($this->getwxConfig());
        if (!$this->userInit($weixin) and !$this->checkWeiXinUser()) {
            $toUrl = $weixin->getAuthorUrl($url, 'snsapi_userinfo');
            header("Location: $toUrl");
            exit;
        }
        $tourl = $this->getQuery('tourl');
        $vm = new ViewModel([
            'tourl'=>$tourl
        ]);
        $vm->setTerminal(true);
        return $vm;
    }


    //确认支付密码
    public function confirmpwdAction(){
        $pwd = (int)$this->getQuery("pwd");
        $tourl = $this->getQuery('tourl');
        $vm = new ViewModel([
            'tourl'=>$tourl,
            'pwd'=>$pwd
        ]);
        $vm->setTerminal(true);
        return $vm;
    }

    //3.3所有订单
    public function allorderAction(){
        $weixin = new WeiXinFun($this->getwxconfig());
        $back_url =$this->_getConfig()['url'] . '/web/wappay/allorder';
        $toUrl = $weixin->getAuthorUrl($back_url, 'snsapi_userinfo');
        if($this->is_weixin()){
            if(!$this->userInit($weixin) and !$this->checkWeiXinUser()){
                header("Location: $toUrl");
                exit;
            }

            $uid = $_COOKIE['uid'];
            if (!$uid) {
                header("Location: /web/wappay/register?tourl=/web/wappay/allorder");
            }
        }

        $order_type = (int)$this->getQuery('order_type',0);
        $order_status = (int)$this->getQuery('order_status',0);
        $post_url = $this->_getConfig()['url'].'/user/orderlist';
        //string(39) "http://test.wan.deyi.com/user/orderlist"
        $json = $this->post_curl($post_url,array("uid"=>$uid,'order_type'=>$order_type,'order_status'=>$order_status),'',$_COOKIE);
        $data = json_decode($json,true);
//        echo '<pre>';
//        var_dump($data['response_params']);
//        echo '</pre>';

        $vm = new ViewModel([
            'data'=>$data['response_params'],
            'order_status'=>$order_status,
            'authorUrl'=>$toUrl
        ]);


        $vm->setTerminal(true);
        return $vm;
    }

    //引导关注页面
    public function guideattentionAction(){

        $vm = new ViewModel([

        ]);
        $vm->setTerminal(true);
        return $vm;
    }

    //领券完成页面
    public function exchangesucAction(){
        $cid = (int)$this->getQuery('cid',0);
        $id = (int)$this->getQuery('id',0);
        $post_url = $this->_getConfig()['url'].'/cashcoupon/index/nindex';
        $json= $this->post_curl($post_url,array('cid'=>$cid,'id'=>$id,'page_num'=>10),'',$_COOKIE);
        $data = json_decode($json,true);
        $vm = new ViewModel([
            'data'=>$data['response_params'],
            'cid'=>$cid,
            'id'=>$id
        ]);
        $vm->setTerminal(true);
        return $vm;
    }
}
