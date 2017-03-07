<?php

namespace Seller\Controller;

use Deyi\BaseController;
use Deyi\JsonResponse;
use Deyi\OutPut;
use Deyi\Paginator;
use Deyi\OrderAction\UseCode;
use Deyi\PHPQrCode\QrCode;
use library\Service\System\Cache\RedCache;
use Deyi\SendMessage;
use Deyi\WeiXinFun;
use Deyi\WriteLog;
use Zend\Crypt\Symmetric\Mcrypt;
use Zend\Db\Sql\Predicate\Expression;
use Zend\Filter\File\Decrypt;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    use JsonResponse;
    use UseCode;
    use BaseController;

    private $sign='FUtGHgdsdfh';

    public function indexAction()
    {
        return array();
    }

    //商家wap后台登录
    public function loginAction()
    {
        $user = trim($this->getPost('mobile'));
        $pwd = trim($this->getPost('password'));

        if ($user) {
            $key = 'try_number_' . bin2hex($user);
            $try_number = (int)RedCache::get($key);
            if ($try_number) {
                if ($try_number >= 5) {
                    return $this->jsonResponsePage(array('status' => 0, 'message' => '错误次数过多请稍后再试'));
                } else {
                    RedCache::set($key, ($try_number + 1), 360);
                }
            } else {
                RedCache::set($key, 1, 360);
            }
        }

        $user_info = false;
        if ($user) {
            if(is_numeric($user)){
                $user_info = $this->_getPlayOrganizerTable()->get(array('id'=>$user, 'status' => 1));
            }else{
                $user_info = $this->_getPlayOrganizerTable()->get(array('name'=>$user, 'status' => 1));
            }
            if (!$user_info) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '商家不存在'));
            }
            if($user_info->pay_pwd != $pwd){
                if($user_info->password != md5($pwd)){
                    return $this->jsonResponsePage(array('status' => 0, 'message' => '登录密码错误'));
                }
            }
            /*if($user_info->status==0){
                return $this->jsonResponsePage(array('status' => 0, 'message' => '账号已删除或禁用'));
            }*/
        }

        if($user and $pwd){
            if($user_info){
                //生成验证信息
                $hash_data = json_encode(array('user' => $user, 'id' => (int)$user_info->id, 'group' => 2));
                $token = hash_hmac('sha1', $hash_data, $this->_getConfig()['token_key']);
                setcookie('type', 1, time() + 28800, '/');
                setcookie('user', $user, time() + 28800, '/');
                setcookie('token', $token, time() + 28800, '/');
                setcookie('group', 2, time() + 28800, '/');
                setcookie('id', (int)$user_info->id, time() + 28800, '/');
                if($user_info->phone){
                    return $this->jsonResponsePage(array('status' => 1, 'message' => '登陆成功'));
                }else{
                    return $this->jsonResponsePage(array('status' => 2, 'message' => '登陆成功'));
                }
            }else {
                header('Location: /shop', true);
            }
        }
        $v = new ViewModel();
        $v->setTerminal(true);
        return $v;
    }


    //忘记密码
    public function forgetAction(){
        $newPwd = trim($this->getPost('new_pwd'));
        $user = trim($this->getPost("name"));
        $phone = trim($this->getPost('phone'));
        $code = (int)$this->getPost('code');
        if($user and $newPwd and $phone and $code){
            if(!$user or !$phone or !$newPwd or !$code){
                return $this->jsonResponsePage(array('status' => 0, 'message' => '缺少有效参数'));
            }

            //验证用户合法性
            if(is_numeric($user)){
                $user_info = $this->_getPlayOrganizerTable()->get(array('id'=>$user));
            }else{
                $user_info = $this->_getPlayOrganizerTable()->get(array('name'=>$user));
            }
            if(empty($user_info)){
                return $this->jsonResponsePage(array('status' => 0, 'message' => '商家不存在'));
            }

            //验证手机号
            if($user_info->phone != $phone){
                return $this->jsonResponsePage(array('status' => 0, 'message' => '手机号码有误'));
            }


            if($user_info->status==0){
                return $this->jsonResponsePage(array('status' => 0, 'message' => '账号已删除或禁用'));
            }

            //验证码合法性
//            if(!$this->check_verify_code($code)){
//                $this->jsonResponse(array('status' => 0, 'message'=>'验证码错误'));
//            }

            //新密码合法性
            if(!$this->check_pwd_len($newPwd)){
                return $this->jsonResponsePage(array('status'=> 0, 'message' => '密码格式错误，密码长度必须大于6位并且小于18位'));
            }

            $status = $this->_getPlayOrganizerTable()->update(array('pay_pwd'=>$newPwd,'dateline'=>time()),array("id='$user' or name='$user'"));
            if($status){
                $this->_getPlayAuthCodeTable()->update(array('status'=>0),array('phone'=>$phone,'code'=>$code,'status'=>1));
                return $this->jsonResponsePage(array('status'=> 1, 'message' => '更新成功'));
            }else{
                return $this->jsonResponsePage(array('status'=> 0, 'message' => '更新失败'));
            }
        }

    }


    //修改密码
    public function changeAction(){
        $newPwd = trim($this->getPost('new_pwd'));
        $user = trim($this->getPost("name"));
        $pwd = trim($this->getPost('pwd'));
        if($user and $newPwd and $pwd){
            //验证用户合法性
            if(is_numeric($user)){
                $user_info = $this->_getPlayOrganizerTable()->get(array('id'=>$user));
            }else{
                $user_info = $this->_getPlayOrganizerTable()->get(array('name'=>$user));
            }
            if(empty($user_info)){
                return $this->jsonResponsePage(array('status' => 0, 'message' => '商家不存在'));
            }
            if($user_info->pay_pwd != $pwd){
                return $this->jsonResponsePage(array('status' => 0, 'message' => '原密码错误'));
            }
            if($user_info->status==0){
                return $this->jsonResponsePage(array('status' => 0, 'message' => '账号已删除或禁用'));
            }
            //验证密码长度有效性
            if(!$this->check_pwd_len($newPwd)){

                return $this->jsonResponsePage(array('status'=> 0, 'message' => '密码格式错误，密码长度必须大于6位并且小于18位'));
            }

            $status = $this->_getPlayOrganizerTable()->update(array('pay_pwd'=>$newPwd,'dateline'=>time()),array("id='$user' or name='$user'"));

            if($status){
                return $this->jsonResponsePage(array('status'=> 1, 'message' => '更新成功'));
            }else{
                return $this->jsonResponsePage(array('status'=> 0, 'message' => '更新失败'));
            }
        }

    }

    //验证用户是否有效
    public function CheckUser($user=null,$pwd=null){
        $res = $this->_getPlayOrganizerTable()->get(array('name'=>$user));
        if(empty($res)){
            return $this->jsonResponsePage(array('status' => 0, 'message' => '商家不存在'));
        }
        if($res->password != md5($pwd)){
            return $this->jsonResponsePage(array('status' => 0, 'message' => '密码错误'));
        }
        if($res->status==0){
            return $this->jsonResponsePage(array('status' => 0, 'message' => '账号已删除或禁用'));
        }
    }


    //验证密码长度
    private function check_pwd_len($pwd){
        $len = strlen($pwd);
        return ($len>=6 and $len<=18) ? true : false;
    }


    //验证码合法性
    private function check_verify_code($phone=null,$code=null){
        $status = $this->_getPlayAuthCodeTable()->fetchAll(array('phone'=>$phone,'code'=>$code,'status'=>1),array('id'=>'desc'),1)->toArray();
        return empty($status) ? false : true;
    }

    //发送手机验证码
    public function sendCodeAction(){

        $phone = trim($this->getPost('phone'));
        $user = trim($this->getPost('user'));
        if (!$phone) {
            return $this->jsonResponse(array('status' => 0, 'message' => '手机号不能为空'));
        }

        if (strlen($phone) != 11) {
            return $this->jsonResponse(array('status' => 0, 'message' => '手机号长度不正确11'));
        }


        if(is_numeric($user)){
            $user_info = $this->_getPlayOrganizerTable()->get(array('id'=>$user));
        }else{
            $user_info = $this->_getPlayOrganizerTable()->get(array('name'=>$user));
        }

        if(!$user_info){
            return $this->jsonResponse(array('status' => 0, 'message' => '手机号码有误'));
        }

        if(is_numeric($user) ? $user!= $user_info['id'] : $user != $user_info['name']){
            return $this->jsonResponse(array('status' => 0, 'message' => '用户名和手机号不匹配'));
        }

        //todo 避免同一用户并发
        if (RedCache::get('send' . $phone)) {
            //todo 间隔时间过短
            return $this->jsonResponse(array('status' => 0, 'message' => '发送频率过高，请稍后再试'));
        } else {
            RedCache::set('send' . $phone, time(), 2);
        }

        //todo 一天内 只允许5条,每条相隔1分钟
        $time = time() - 43200;
        $data = $this->_getPlayAuthCodeTable()->fetchAll(array('phone' => $phone, "time>{$time}"), array('id' => 'desc'), 5)->toArray();

        if (!empty($data) and (time() - $data[0]['time']) < 300) {
            return $this->jsonResponse(array('status' => 0, 'message' => '发送频率过高'));
        }
        if (count($data) == 5) {
            return $this->jsonResponse(array('status' => 0, 'message' => '超过每日短信限制'));
        }

//        发送验证码
        $code = SendMessage::SendAuthCode($phone);
        if (!$code) {
            return $this->jsonResponse(array('status' => 0, 'message' => SendMessage::$error));
        } else {
            $this->_getPlayAuthCodeTable()->insert(array('phone' => $phone, 'time' => time(), 'code' => $code, 'status' => 1));
            return $this->jsonResponse(array('status' => 1, 'message' => '验证码发送成功'));
        }

    }

    //二维码信息
    public function codeAction(){
        //获取商家id
        $uid = (int)$_COOKIE['id'];
        if(!$uid){
            header('Location: /shop', true);
        }

        $couponCode = trim($this->getPost('code'));

        if($couponCode){

            $id = substr($couponCode, 0, -7);
            $password = substr($couponCode, -7);
            //当前验证码数据
            $coupon_code_data = $this->_getPlayCouponCodeTable()->get(array('password' => $password, 'id' => $id));
            if (!$coupon_code_data) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '验证码不存在'));
            }

            if ($coupon_code_data->status == 1) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '该使用码已使用过了'));
            }

            if ($coupon_code_data->status == 2) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '该使用码正在退款'));
            }

            if ($coupon_code_data->status == 3) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '该使用码正在退款中'));
            }

            if($coupon_code_data->status != 0){
                return $this->jsonResponsePage(array('status' => 0, 'message' => '该使用码已经被验证'));
            }

            //查询订单数据
            $order_data = $this->_getPlayOrderInfoTable()->getUserBuy(array('order_sn' => $coupon_code_data->order_sn));

            if (!$order_data) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '订单不存在'));
            }

            if ($order_data->pay_status < 2) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '订单未付款'));
            }

            if ($order_data->order_type == 1) {//普通票
                return $this->jsonResponsePage(array('status' => 0, 'message' => '本卡券只能使用游玩地帐号登录后使用，请登录游玩地帐号后使用'));
            }

            $organizer_flag = $this->_getPlayCodeUsedTable()->get(array('organizer_id' => $uid, 'good_info_id' => $order_data->game_info_id));

            if (!$organizer_flag) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => "订单号不属于本活动组织者"));
            }


            //验证信息返回
            $data = array(
                'coupon_name'=>$order_data->coupon_name,
                'price_name'=> $order_data->type_name,
                'coupon_unit_price'=>$order_data->coupon_unit_price,
                'code' => $couponCode,
                'order_sn'=>$coupon_code_data->order_sn,
                'dateline'=>date("Y-m-d H:i:s",$order_data->dateline),
                'phone'=>$order_data->buy_phone,
                'address'=>$order_data->shop_name,
                'price'=>$order_data->coupon_unit_price,
            );

            return $this->jsonResponsePage(array('status' => 1, 'message' => '商品可以验证' ,'data'=>$data));
            exit;
        }
        //二维码信息
        $sign = $this->sign;
        $token = md5($uid.$sign);

//        $qrcode = "http://10.0.18.113/application/index/phpqrcode?code=http://10.0.18.113/seller/index/pay?token=".$token.'&sid='.$uid;
        $code = $this->_getConfig()['url']."/seller/index/pay?token=".$token.'&sid='.$uid;
//        QrCode::png($code);

        $pathname = $_SERVER['DOCUMENT_ROOT']."/qrcode/$uid/";
        if(!is_dir($pathname)) { //若目录不存在则创建之
            mkdir($pathname,0777,true);
        }
        $filename = $pathname . "qrcode_" . md5($uid) . ".png";
        QrCode::png($code,$filename);
        $filename = $this->_getConfig()['url']."/qrcode/$uid/"."qrcode_" . md5($uid) . ".png";
        return new ViewModel(array(
            'qrcode'=>$filename,
            'shop_id'=>$uid
        ));
    }

    //商家流水
    public function logAction(){
        $page = $_GET['p']>0 ? $_GET['p'] : (int)$this->getQuery('page',1);
        $last_id=(int)$_GET['last_id'];


        $s_time = trim($this->getQuery("start_time"));
        $e_time = trim($this->getQuery("end_time"));
        //获取商家id
        $uid = (int)$_COOKIE['id'];
        if(!$uid){
            header('Location: /shop');
        }

        $where = "play_coupon_code.status=1 and play_order_info.order_type=2 and play_order_info.pay_status>1 and play_code_used.organizer_id={$uid}";
        //验证时间筛选
        if ($s_time) {
            $s_time = (int)strtotime($s_time);
            $where = $where. " AND play_coupon_code.use_datetime > ".$s_time;
        }

        if ($e_time) {
            $e_time = (int)(strtotime($e_time) + 86400);
            $where = $where. " AND play_coupon_code.use_datetime < ". $e_time;
        }

        if($last_id){
            $where = $where. " AND play_coupon_code.id < ". $last_id;
        }
        //获取商家信息
        $shop_data = $this->_getPlayOrganizerTable()->get(array('id'=>$uid,'status'=>1));
        if(!$shop_data){
            return $this->jsonResponsePage(array('status' => 0,'message'=> '商家信息有误'));
        }

        $row = 10;
        $offset = ($page - 1) * $row;

        $Adapter = $this->_getAdapter();

        $code_sql = "SELECT
	play_coupon_code.id,
	play_coupon_code.`password`,
	play_coupon_code.order_sn,
	play_coupon_code.use_datetime,
	play_order_info.buy_phone,
	play_order_info.coupon_name,
	play_order_info.dateline,
	play_game_info.price_name AS type_name,
	play_order_info.coupon_unit_price
FROM
	play_coupon_code
LEFT JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
LEFT JOIN play_code_used ON play_code_used.good_info_id = play_order_info.bid
LEFT JOIN play_game_info ON play_game_info.id = play_order_info.bid
WHERE
	$where
ORDER BY play_coupon_code.use_datetime DESC
LIMIT $offset, $row";

        $data = $Adapter->query($code_sql, array());

        //$data = $this->_getPlayCouponCodeTable()->getCodeLog($offset, $row, array(), $where, array('use_datetime' => 'desc'))->toArray();


//        $count = $this->_getPlayCouponCodeTable()->getCodeLog(0,0,array('num'=>new Expression('count(*)')), $where, array())->current();
        $sql = "SELECT
	count(play_coupon_code.id) AS counter
FROM
	play_coupon_code
LEFT JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
LEFT JOIN play_code_used ON play_code_used.good_info_id = play_order_info.bid
WHERE $where";

        $count = $this->query($sql)->current();
        $count = $count['counter'];

        foreach($data as $val){
            $order_info[] = array(
                'id'=>$val['id'],
                'usetime'=>date("Y-m-d H:i:s",$val['use_datetime']),
                'buy_phone'=>substr_replace($val['buy_phone'],'*****',3,5),
                'coupon_name'=>$val['coupon_name'],
                'code'=>$val['id'].$val['password'],
                'dateline'=>$val['dateline'],
                'order_sn'=>$val['order_sn'],
                'price_name'=>$val['type_name'],
                'price'=>$val['coupon_unit_price']
            );
        }

        $paginator = new Paginator($page,$count,$row,'/seller/index/order');

        if(!empty($_GET['p'])){
            $arrResult = array("error"=>0, "info"=>'', "data"=> $order_info);
            exit(json_encode($arrResult));
        }
        return array(
            'data' => empty($order_info) ? '' : $order_info,//商家流水
            'page'=> $paginator->getHtml(),
        );
    }



    //确定验证
    public function validateAction(){
        $uid = (int)$_COOKIE['id'];

        $couponCode = trim($this->getPost('code'));
        $status = $this->UseCode($uid,2,$couponCode);
        if($status['status']==0){
            return $this->jsonResponsePage(array('status' => 0, 'message' => '验证失败'));
        }else{
            return $this->jsonResponsePage(array('status' => 1, 'message' => '验证成功'));
        }
    }

    //客户扫描二维码获取商家的卡券信息
    public function payAction(){
        $token = $_GET['token'];
        $sid = $_GET['sid'];
        $uid = $this->getWapUid()>0 ? $this->getWapUid() : $_COOKIE['uid'];
        if(!$uid){
            if($this->is_weixin()){
                $url = $this->_getConfig()['url']."/seller/index/pay?token=".$token.'&sid='.$uid;
                $weixin = new WeiXinFun($this->getwxConfig());

                if ($this->userInit($weixin) and $this->checkWeiXinUser()) {
                    $this->checkPhone($url);
                } else {
                    //todo 授权失败
                    $toUrl = $weixin->getAuthorUrl($url, 'snsapi_userinfo');
                    header("Location: $toUrl");
                    exit;
                }

            }else{
                return $this->jsonResponsePage(array('status' => 0, 'message' => '请先登录'));
            }
        }
        if($token){
            $code = $this->sign;
            if($token != md5($sid.$code)){
                return $this->jsonResponsePage(array('status' => 0, 'message' => '二维码验证错误'));
            }
            //商户id
            $shop_id = $sid;
            //获取商户数据
            $shop_data = $this->_getPlayOrganizerTable()->get(array('id'=>$shop_id));

            if(!$shop_data){
                return $this->jsonResponsePage(array('status' => 0, 'message' => '商家信息错误'));
            }

            $where = array(
                'play_coupon_code.status = ?' => 0, //待使用
                'play_order_info.pay_status > ?'=> 1, //已付款
                'play_order_info.user_id = ?' => $uid, //用户id
                'play_code_used.organizer_id = ?' => $shop_id,//店铺限制
            );

            $order_data = $this->_getPlayCouponCodeTable()->getShopOrderList(0,0,array(),$where,array('order_sn'=>'desc'))->toArray();

            if(!$order_data){
                header("location:/seller/index/empty");
                exit;
            }
            foreach($order_data as $val){
                $data[] = array(
                    'coupon_name'=>$val['coupon_name'],
                    'price_name'=> $val['price_name'],
                    'coupon_unit_price'=>$val['coupon_unit_price'],
                    'code' => $val['id'].$val['password'],
                    'order_sn'=>$val['order_sn'],
                    'dateline'=>date("Y-m-d H:i:s",$val['dateline']),
                    'phone'=>$val['buy_phone'],
                    'address'=>$val['shop_name'],
                    'id'=>$val['id'],
                    'shop_id'=> $sid,
                );
            }

            return new ViewModel(array(
                'data' => $data,
            ));
        }else{
            return $this->jsonResponsePage(array('status' => 0, 'message' => '二维码缺少有效参数'));
        }
    }

    public function emptyAction(){

    }

    //支付密码验证卡券
    public function doPayAction(){
        $shop_pwd = trim($this->getPost('shop_pwd'));
        $shop_id = trim($this->getPost('shop_id'));
        $coupon_id = trim($this->getPost('coupon_id'));

//        $shop_pwd = strtolower($shop_pwd);
        //验证支付密码
        $shopData = $this->_getPlayOrganizerTable()->get(array('id'=>$shop_id));
        if(!$shopData){
            return $this->jsonResponse(array('status'=>0,'message'=>'未查询到该商家信息，请联系客服'));
        }
        if($shopData['pay_pwd'] != $shop_pwd){
            return $this->jsonResponse(array('status'=>0,'message'=>'支付密码错误，请重新输入'));
        }
        $status = $this->UseCode($shop_id,2,$coupon_id);

        if($status['status']==0){
            return $this->jsonResponse(array('status'=>0,'message'=>$status['message']));
        }else{
            return $this->jsonResponse(array('status'=>1,'message'=>$status['message']));
        }
    }

    public function doPay($shop_id,$ids){
        $status = false;
        if(!$ids[1]){
            $status = $this->UseCode($shop_id,2,$ids[0]);
            if($status['status']==0){
                return $this->jsonResponse(array('status'=>0,'message'=>$status['message']));
            }else{
                return $this->jsonResponse(array('status'=>1,'message'=>$status['message']));
            }
        }else{
            foreach($ids as $v){
                $status = $this->UseCode($shop_id,2,$v);
                if($status['status']==0){
                    return $this->jsonResponse(array('status'=>0,'message'=>$status['message']."验证码:".$v));
                }else{
                    return $this->jsonResponse(array('status'=>1,'message'=>$status['message']));
                }
            }
        }
    }

    //用户批量验证输入支付密码页面
    public function showpwdAction(){
        $id = $this->getQuery("shop_id",0);
        $shop_id = trim($this->getPost('shop_id'));

        return new ViewModel([
            'ids'=>$id,
            'shop_id'=>$shop_id
        ]);
    }

    function query($sql)
    {
        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $stmt = $db->query($sql);
        $result = $stmt->execute($stmt);
        return $result;
    }


    //初始化用户 生成用户 生成验证信息
    public function userInit(WeiXinFun $weixin)
    {


        if (!$this->checkWeiXinUser()) {
            if (isset($_GET['code'])) {
                //todo 封装  存储相关信息，获取用户信息，生成cookie
                $accessTokenData = $weixin->getUserAccessToken($_GET['code']);

                if (isset($accessTokenData->access_token)) {
                    $token = md5(time() . $accessTokenData->access_token);
                    //先查询用户是否存在
                    $user_data = false;
                    if(!$accessTokenData->unionid){
                        $accessTokenData->unionid=-1;
                    }
                    $user = $this->_getPlayUserWeiXinTable()->getUserInfo("play_user_weixin.open_id='{$accessTokenData->openid}' or play_user_weixin.unionid='{$accessTokenData->unionid}'");

                    if ($user) {
                        $user_data = $this->_getPlayUserTable()->get(array('uid' => $user->uid));
                    }
                    if ($user && $user_data) {
                        //初始化当前新微信号数据
                        $weixin=$this->_getPlayUserWeiXinTable()->get(array('open_id'=>$accessTokenData->openid));
                        if(!$weixin){
                            $this->_getPlayUserWeiXinTable()->insert(array(
                                'uid' => $user->uid,
                                'appid'=>$this->getwxConfig()['appid'],
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
                        ));
                        $uid = $this->_getPlayUserTable()->getlastInsertValue();
                        $status = $this->_getPlayUserWeiXinTable()->insert(array(
                            'uid' => $uid,
                            'appid'=>$this->getwxConfig()['appid'],
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

    public function setCookie($uid, $token, $openid, $phone = '')
    {
        $_COOKIE['uid'] = $uid;
        $_COOKIE['open_id'] = $openid;
        $untime = time() + 3600 * 24 * 17;  //失效时间
        setcookie('uid', $uid, $untime, '/');
        setcookie('token', $token, $untime, '/');
        setcookie('open_id', $openid, $untime, '/');
        setcookie('phone', $phone, $untime, '/');
    }

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

    //绑定手机号
    public function bindAction(){
        $phone = trim($this->getPost('phone'));
        $code = (int)$this->getPost('code');
        $pwd = trim($this->getPost('pwd'));
        $uid = $_COOKIE['id'];
        if(!$uid){
            header('Location: /shop', true);
        }
        $organizer_phone = $this->_getPlayOrganizerTable()->get(array('id'=>$uid))->phone;
        if($phone and $code and $pwd){

            if(!$phone or !$code or !$pwd){
                return $this->jsonResponsePage(array('status' => 0, 'message' => '缺少有效参数'));
            }

            //验证用户合法性

            $user_info = $this->_getPlayOrganizerTable()->get(array('id'=>$uid));
            if(empty($user_info)){
                return $this->jsonResponsePage(array('status' => 0, 'message' => '商家不存在'));
            }

            if($user_info->pay_pwd != $pwd){
                if($user_info->password != md5($pwd)){
                    return $this->jsonResponsePage(array('status' => 0, 'message' => '登录密码错误'));
                }
            }

            //验证码合法性
            if(!$this->check_verify_code($code)){
                $this->jsonResponse(array('status' => 0, 'message'=>'验证码错误'));
            }

            if($user_info->phone==$phone){
                $this->jsonResponse(array('status' => 0, 'message'=>'你已经绑定过该号码'));
            }


            $status = $this->_getPlayOrganizerTable()->update(array('phone'=>$phone,'dateline'=>time()),array("id"=>$uid));


            if($status){
                $this->_getPlayAuthCodeTable()->update(array('status'=>0),array('phone'=>$phone,'code'=>$code,'status'=>1));
                $organizer_phone = $phone;
                return $this->jsonResponsePage(array('status'=> 1, 'message' => '绑定成功'));
            }else{
                return $this->jsonResponsePage(array('status'=> 0, 'message' => '绑定失败'));
            }
        }

        return new ViewModel([
            'phone'=>$organizer_phone
        ]);
    }


    //商家流水导出
    public function outlogAction(){
        $s_time = trim($this->getQuery("start_time"));
        $e_time = trim($this->getQuery("end_time"));
        $uid = (int)$_COOKIE['id'];
        //验证时间筛选
        $where = "play_coupon_code.status=1 and play_order_info.order_type=2 and play_order_info.pay_status>1 and play_code_used.organizer_id={$uid}";
        if(!empty($s_time) && !empty($e_time)){
            $nStartDate = strtotime($s_time);
            $nEndDate = strtotime($e_time)+86400;

            //容错起始时间大于结束时间
            if($nStartDate > $nEndDate){
                $where .= " and (play_coupon_code.use_datetime >= {$nEndDate} and play_coupon_code.use_datetime <= {$nStartDate}) ";
            }else{
                $where .= " and (play_coupon_code.use_datetime >= {$nStartDate} and play_coupon_code.use_datetime <= {$nEndDate}) ";
            }
        }else{
            if(!empty($s_time)){
                $nStartDate = strtotime($s_time);
                $where .= " and play_coupon_code.use_datetime >= {$nStartDate} ";
            }

            if(!empty($e_time)){
                $nEndDate = strtotime($e_time)+86400;
                $where .= " and play_coupon_code.use_datetime <= {$nEndDate} ";
            }
        }

        $Adapter = $this->_getAdapter();

        $code_sql = "SELECT
	play_coupon_code.id,
	play_coupon_code.`password`,
	play_coupon_code.order_sn,
	play_coupon_code.use_datetime,
	play_order_info.buy_phone,
	play_order_info.coupon_name,
	play_game_info.price_name AS type_name,
	play_game_info.account_money
FROM
	play_coupon_code
LEFT JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
LEFT JOIN play_code_used ON play_code_used.good_info_id = play_order_info.bid
LEFT JOIN play_game_info ON play_game_info.id = play_order_info.bid
WHERE
	$where ORDER BY play_coupon_code.use_datetime DESC";

        $data = $Adapter->query($code_sql, array());

        //$data = $this->_getPlayCouponCodeTable()->getCodeLog(0, 0, array(), $where, array('use_datetime' => 'desc'))->toArray();
        $head = array(
            '用户手机号',
            '验证时间',
            '订单号',
            '验证码',
            '商品名称',
            '套系名称',
            '结算价'
        );
        $content = array();
        foreach($data as $v){
            $content[] = array(
                substr_replace($v['buy_phone'],'*****',3,5),
                date('Y-m-d H:i',$v['use_datetime']),
                'WFT' . (int)$v['order_sn'],
                "\t".$v['id'].$v['password'],
                $v['coupon_name'],
                $v['type_name'],
                $v['account_money']
            );
        }

        $fileName = date('Y-m-d H:i:s', time()). '_商家流水列表.csv';
        $out = new OutPut();
        $out->out($fileName, $head, $content);
        exit;
    }
}
