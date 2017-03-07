<?php

namespace Web\Controller;

use Deyi\BaseController;
use Deyi\JsonResponse;
use library\Service\System\Cache\RedCache;
use Deyi\Seller\Seller;
use Deyi\WeiXinFun;
use Zend\Http\Response;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class KidsplayController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    public function __construct()
    {
        //设置请求第三方时mysql断开连接的问题
        ini_set('mysql.connect_timeout', 60);
        ini_set('default_socket_timeout', 60);
    }


    //活动列表
    public function indexAction()
    {
        $weixin = new WeiXinFun($this->getwxConfig());

//        if($this->is_weixin()){
//            $url = $this->_getConfig()['url'] . '/web/kidsplay/index';
//            $toUrl = $weixin->getAuthorUrl($url, 'snsapi_userinfo');
//            if (!$this->userInit($weixin) and !$this->checkWeiXinUser()) {
//                header("Location: $toUrl");
//                exit;
//            }
//        }

        $city = $_COOKIE['sel_city'];
        //请求的url
        $sort = (int)$this->getQuery('sort',1);
//        $city = $weixin->getOdinaryUserInfo($_COOKIE['open_id'])->city;
        $url = $this->_getConfig()['url'].'/kidsplay/index/list';
        $json = $this->post_curl($url,array('sort'=>$sort),$city,$_COOKIE);
        $data = json_decode($json);

        //分享
        $jswxconfig = $this->getShareInfoAction()[1];
        $share = array(
            'img'=>$this->_getConfig()['url'].'/images/wap/kidslogo.png',
            'title'=>'【玩翻天】遛娃学院陪你嗨翻暑假',
            'desc'=>'我发现了好多不错的活动，我们一起参加吧！金牌遛娃师带孩子们玩翻天！',
            'link'=>$this->_getConfig()['url'].'/web/kidsplay/index',
        );

        $vm = new ViewModel([
            'data'=>$data->response_params,
            'city'=>$city,
            'sort'=>$sort,
//            'authorUrl'=>$toUrl,
            'share_type'=>'kidsplayindex',
            'share_id'=>0,
            'jsconfig'=>$jswxconfig,
            'share'=>$share,
        ]);
        $vm->setTerminal(true);
        return $vm;
    }

    //活动详情
    public function infoAction(){
        $id = (int)$this->getQuery('id');//活动id
        $b_channel = $this->getQuery('b_channel');//活动id
        $seller_id = (int)$this->getQuery('seller_id');//分销员的id

        if($b_channel){
            setcookie('b_channel', $b_channel, time() + 3600 * 24 * 1, '/');
            setcookie('b_id', $id, time() + 3600 * 24 * 1, '/');
        }

        if(!$id){
            exit('<h1>活动不存在</h1>');
        }

        $url = $this->_getConfig()['url'].'/web/kidsplay/info?id='.$id;
        $weixin = new WeiXinFun($this->getwxConfig());
//        if($this->is_weixin()){
//            $toUrl = $weixin->getAuthorUrl($url, 'snsapi_userinfo');
//            if (!$this->userInit($weixin) and !$this->checkWeiXinUser()) {
//                header("Location: $toUrl");
//                exit;
//            }
//        }

        $sid = (int)$this->getQuery('sid',0);
        if($sid>0){
            setcookie('share_order_sn',$sid,time() + 3600 * 24 * 7,'/');
        }


        if($_COOKIE['uid'] and $this->checkToken($_COOKIE['uid'],$_COOKIE['token'])==false){
            unset($_COOKIE['uid'],$_COOKIE['token']);
        }

//        setCookie('share_cache','',time()-1,'/');
        $url = $this->_getConfig()['url'].'/kidsplay/index/detail';  //活动详情
        $json = $this->post_curl($url,array('uid'=>$_COOKIE['uid'],'id'=>$id),'',$_COOKIE);
        $data = json_decode($json,true);
//        var_dump($data['response_params']);

        $schedule = $data['response_params']['itinerary'];

        $info = array();

        //处理时间线
        foreach($schedule as $k=>$v){
            $info[$v['day']][] = $v;
        }

//        echo '<pre>';
//        var_dump($info);
        $information=$this->_getPlayExcerciseBaseTable()->get(array('id'=>$id))->highlights;

        //分享
        $jswxconfig = $this->getShareInfoAction()[1];
        $share = array(
            'img'=>$data['response_params']['share_img'],
            'title'=>$data['response_params']['share_title'],
            'desc'=>$data['response_params']['share_content'],
            'link'=>$data['response_params']['share_url'],
        );


        if ($seller_id) {
            $Seller = new Seller();
            $Seller->shareEffective($seller_id, $_COOKIE['uid'], $id, 'activity');
        }

        //$data['response_params']['man_num'] = count($data['response_params']['members']);
        $vm = new ViewModel([
            'data'=>$data['response_params'],
            'schedule'=>$info,
            'info'=>htmlspecialchars_decode($information),
            'server_url'=>$this->_getConfig()['url'],
            'share_type'=>'buyactive',
            'share_id'=>$id,
            'jsconfig'=>$jswxconfig,
            'share'=>$share,
        ]);
        $vm->setTerminal(true);
        return $vm;

    }

    //活动成员
    public function membersAction(){
        $id = (int)$this->getQuery('id',0);
        if(!$id){
            exit("<h1>活动不存在</h1>");
        }

        $orders_array = $this->_getPlayOrderInfoTable()->getExcerciseMembers($id);

        //第一个为团长
        $members = array();
        foreach ($orders_array as $v) {
            if ($_COOKIE['uid']) {
                $friend = (int)$this->_getMdbsocialFriends()->findOne(array('uid' => $_COOKIE['uid'], 'friends' => 1, 'like_uid' => $v->user_id), array('like_uid'));
            } else {
                $friend = 0;
            }
            $members[] = array(
                "image" => $this->getImgUrl($v->img),
                "is_friend" => $friend
            );
        }
        $vm = new ViewModel([
            'members'=>$members
        ]);

        $vm->setTerminal(true);
        return $vm;
    }

    //咨询待接口完成
    public function consultAction(){
        $id = (int)$this->getQuery('id',0);//活动id
        $uid = $_COOKIE['uid'];
        $url = $this->_getConfig()['url'].'/kidsplay/consult/list';
        $back = $this->_getConfig()['url']."/web/kidsplay/consult?id={$id}";
        $weixin = new WeiXinFun($this->getwxConfig());
        $toUrl = $weixin->getAuthorUrl($back, 'snsapi_userinfo');
        if (!$this->userInit($weixin) and !$this->checkWeiXinUser()) {
            header("Location: $toUrl");
            exit;
        }


        $json = $this->post_curl($url,array('uid'=>$uid,'play_id'=>$id),'',$_COOKIE);
        $data = json_decode($json,true);

//        var_dump($data);
        $vm = new ViewModel(
            [
                'data'=>$data['response_params'],
                'id'=>$id,
                'authorUrl'=>$toUrl
            ]
        );
        $vm->setTerminal(true);
        return $vm;
    }

    //评论待接口完成
    public function commendAction(){
        $eid = (int)$this->getQuery('eid',0);//场次id
        $bid = (int)$this->getQuery('bid',0);//活动id
        $buy_log = (int)$this->getQuery('buy_log',0);//是否购买 大于 0 为购买过
        $uid = $_COOKIE['uid'];
        $weixin = new WeiXinFun($this->getwxConfig());
        $url = $this->_getConfig()['url'].'/post/index/postlist';
        $back = $this->_getConfig()['url']."/web/kidsplay/commend?eid={$eid}";
        if($this->is_weixin()){
            if(!$this->userInit($weixin) and !$this->checkWeiXinUser()){
                $toUrl = $weixin->getAuthorUrl($back, 'snsapi_userinfo');
                header("Location: $toUrl");
                exit;
            }
        }

        $json = $this->post_curl($url,array('uid'=>$uid,'object_id'=>$bid,'eid'=>$eid,'type'=>7),'',$_COOKIE);
        $data = json_decode($json,true);
//        var_dump($data);

        $vm = new ViewModel(
            [
                'data'=>$data['response_params']['post'],
                'buy_log'=>$buy_log,
                'eid'=>$eid,
                'bid'=>$bid,
                'server_url'=>$this->_getConfig()['url']
            ]
        );
        $vm->setTerminal(true);
        return $vm;

    }

    //场次选择
    public function selectlistAction(){
        $id = (int)$this->getQuery('id',0);
        $weixin = new WeiXinFun($this->getwxConfig());
        if($this->is_weixin()){
            if(!$this->userInit($weixin) and !$this->checkWeiXinUser()){
                $back_url = $url = $this->_getConfig()['url'].'/web/kidsplay/selectlist?id='.$id;
                $toUrl = $weixin->getAuthorUrl($back_url, 'snsapi_userinfo');
                header("Location: $toUrl");
                exit;
            }
        }

        $url = $this->_getConfig()['url'].'/kidsplay/index/session';
        $json = $this->post_curl($url,array('id'=>$id),'',$_COOKIE);
        $data = json_decode($json,true);
        $vm = new ViewModel([
            'data'=>$data['response_params'],
        ]);

        $vm->setTerminal(true);
        return $vm;
    }

    //下单页面
    public function orderAction(){
        $url = $this->_getConfig()['url'].$_SERVER['REQUEST_URI'];
        $weixin = new WeiXinFun($this->getwxConfig());

//        if($this->is_weixin()){
//            if ($this->userInit($weixin) and $this->checkWeiXinUser()) {
//                if(!$_COOKIE['phone']){
//                    $this->checkPhone($url);
//                }
//            } else {
//                //todo 授权失败
//                $toUrl = $weixin->getAuthorUrl($url, 'snsapi_userinfo');
//                header("Location: $toUrl");
//                exit;
//            }
//        }

        if ($this->checkWeiXinUser()) {
            $callbackUrl = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $this->checkPhone($callbackUrl);
        }

        $sid = (int)$this->getQuery('sid',0);//场次id
        $tips = (int)$this->getQuery('tips',0);
        $good_num = (int)$this->getQuery('good_num',0);
        $uid = $_COOKIE['uid'];
        $url = $this->_getConfig()['url'].'/kidsplay/index/info';
        $json = $this->post_curl($url,array('uid'=>$uid,'session_id'=>$sid),'',$_COOKIE);
        $data = json_decode($json,true);
//        var_dump($data);
        $vm = new ViewModel([
            'data'=>$data['response_params'],
            'tips'=>$tips,
            'good_num'=>$good_num,
            'sid'=>$sid,
        ]);

        $vm->setTerminal(true);
        return $vm;
    }

    //评论详情
    public function commendinfoAction(){
        $pid = trim($this->getQuery("pid",0));
        $url = $this->_getConfig()['url'].'/post/index/info';
        $uid = $_COOKIE['uid'];
        $last_id = (int)$this->getQuery("repid",0);

        $weixin = new WeiXinFun($this->getwxconfig());
        if(!$this->userInit($weixin) and !$this->checkWeiXinUser()){
            $back_url = $url = $this->_getConfig()['url'].'/web/kidsplay/commendinfo?pid='.$pid.'&repid='.$last_id;
            $toUrl = $weixin->getAuthorUrl($back_url, 'snsapi_userinfo');
            header("Location: $toUrl");
            exit;
        }

        $json = $this->post_curl($url,array("uid"=>$uid,'pid'=>$pid,'last_repid'=>$last_id,'pagenum'=>100),"",$_COOKIE);
        $data = json_decode($json,true);
        $vm = new ViewModel([
            'data'=>$data['response_params'],
            'pid'=>$pid,
            'server_url'=>$this->_getConfig()['url'],
        ]);

        $vm->setTerminal(true);
        return $vm;

    }

    //活动订单详情
    public function orderdetailAction(){
        $orderId = $this->getQuery('orderId');

        $weixin = new WeiXinFun($this->getwxconfig());
        if($this->is_weixin()){
            if(!$this->userInit($weixin) and !$this->checkWeiXinUser()){
                $back_url = $this->_getConfig()['url'] . '/web/kidsplay/orderdetail?orderId='.$orderId;
                $toUrl = $weixin->getAuthorUrl($back_url, 'snsapi_userinfo');
                header("Location: $toUrl");
                exit;
            }

            $uid = $_COOKIE['uid'];
            if (!$uid) {
                header("Location: /web/wappay/register?tourl=/web/kidsplay/orderdetail?orderId={$orderId}");
            }
        }

        $post_url = $this->_getConfig()['url'].'/kidsplay/apply/order';
        $json= $this->post_curl($post_url,array('uid'=>$uid,'order_sn'=>$orderId),'',$_COOKIE);
        $data = json_decode($json,true);
        if($data['response_params']['associates']){
            $ids ='';
            foreach($data['response_params']['associates'] as $k=>$v){
                $ids.=$v['associates_id'].',';
            }
        }

        $peopleTotal = 0;
        if($data['response_params']['member_order_list']){
            foreach($data['response_params']['member_order_list'] as $k=>$v){
                if($v['status'] == 0){
                    $peopleTotal += intval($v['people_number']);
                }
            }
        }

        //分享
        $jswxconfig = $this->getShareInfoAction()[1];
        $share = array(
            'img'=>$data['response_params']['share_image'],
            'title'=>$data['response_params']['share_title'],
            'desc'=>$data['response_params']['share_content'],
            'link'=>$data['response_params']['share_url'],
        );

        $vm = new ViewModel([
            'data'=>$data['response_params'],
            'ids'=>$ids,
            'peopleTotal' => $peopleTotal,
            'jsconfig'=>$jswxconfig,
            'share'=>$share,
            'share_type'=>'buyactive',
            'share_id'=>$orderId
        ]);

        $vm->setTerminal(true);
        return $vm;
    }

    //新订单列表
    public function allorderAction(){
        $weixin = new WeiXinFun($this->getwxconfig());
        $back_url =$this->_getConfig()['url'] . '/web/kidsplay/orderlist';
        $toUrl = $weixin->getAuthorUrl($back_url, 'snsapi_userinfo');
        if(!$this->userInit($weixin) and !$this->checkWeiXinUser()){
            header("Location: $toUrl");
            exit;
        }

        $uid = $_COOKIE['uid'];
        if (!$uid) {
            header("Location: /web/wappay/register?tourl=/web/kidsplay/orderlist");
        }

        $order_type = (int)$this->getQuery('order_type',0);
        $order_status = (int)$this->getQuery('order_status',0);
        $post_url = $this->_getConfig()['url'].'/user/orderlist';
        $json = $this->post_curl($post_url,array("uid"=>$uid,'order_type'=>$order_type,'order_status'=>$order_status),'',$_COOKIE);
        $data = json_decode($json,true);
//        var_dump($data['response_params']);
        $vm = new ViewModel([
            'data'=>$data['response_params'],
            'order_status'=>$order_status,
            'authorUrl'=>$toUrl
        ]);

        $vm->setTerminal(true);
        return $vm;
    }

    public function showcodeAction(){
        $weixin = new WeiXinFun($this->getwxConfig());
        if ($this->is_weixin()) {
            if (!$this->pass()) {
                return $this->jsonResponseError('接口验证失败', Response::STATUS_CODE_403);
            }
            if (!$this->userInit($weixin)) {
                $url = $this->_getConfig()['url'] . '/web/kidsplay/showcode';
                $toUrl = $weixin->getAuthorUrl($url, 'snsapi_userinfo');
                header("Location: $toUrl");
                exit;
            }

        }

        $uid = $_COOKIE['uid'];
        if (!$uid) {
            header("Location: /web/wappay/register?tourl=/web/kidsplay/showcode");
        }

        $title = trim($this->getQuery('title'));
        $usetime = trim($this->getQuery('usetime'));
        $start_time = trim($this->getQuery('start_time'));
        $end_time = trim($this->getQuery('end_time'));
        $useaddr = trim($this->getQuery('useaddr'));
        $method = trim($this->getQuery('method'));
        $meet = trim($this->getQuery('meet'));
        $order_type = (int)$this->getQuery('order_type','0');
        $order_sn = (int)$this->getQuery('order_sn',0);
        $post_url = $this->_getConfig()['url'].'/user/orderlist/codeinfo';
        $json = $this->post_curl($post_url,array("uid"=>$uid,'order_sn'=>$order_sn),'',$_COOKIE);
        $data = json_decode($json,true);
        $vm = new ViewModel([
            'data'=>$data['response_params'],
            'title'=>$title,
            'usetime'=>$usetime,
            'end_time'=>$end_time,
            'start_time'=>$start_time,
            'order_type'=>$order_type,
            'order_sn'=>$order_sn,
            'method'=>$method,
            'meet'=>$meet,
            'useaddr'=>$useaddr
        ]);

        $vm->setTerminal(true);
        return $vm;
    }

    public function demoAction(){}

    //存储分享过来的订单号
    private function shareCache($data)
    {
        if(!$data)
        {
            return false;
        }

        //判断cookie类里面是否有浏览记录
        if(isset($_COOKIE['share_cache']))
        {
            $shareCache = unserialize($_COOKIE['share_cache']);
            array_unshift($shareCache, $data); //在顶部加入

            /* 去除重复记录 */
            $rows = array();
            foreach ($shareCache as $v)
            {
                if(in_array($v, $rows))
                {
                    continue;
                }
                $rows[] = $v;
            }

            /* 如果记录数量多余10则去除 */
            while (count($rows) > 10)
            {
                array_pop($rows); //弹出
            }

            setcookie('share_cache',serialize($rows),time() + 3600 * 24 * 7,'/');
        }
        else
        {
            $shareCache = serialize(array($data));

            setcookie('share_cache',$shareCache,time() + 3600 * 24 * 7,'/');
        }
    }

}
