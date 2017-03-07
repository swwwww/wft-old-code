<?php
/**
 * Created by PhpStorm.
 * User: dddee
 * Date: 2016/1/19
 * Time: 11:56
 */
namespace SellerAdmin\Controller;

use Deyi\AutoRefund;
use Deyi\BaseController;
use Deyi\JsonResponse;
use Deyi\Paginator;
use library\Service\System\Cache\RedCache;
use Deyi\SendMessage;
use Zend\Db\Sql\Predicate\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class IndexController extends AbstractActionController{

    use JsonResponse;
    use BaseController;

    public function indexAction(){
         return array();
    }

    //商家登录
    public function loginAction(){

        $user = trim($this->getPost('user'));
        $pwd = trim($this->getPost('password'));
        $type = trim($this->getPost('type'));

//        if($type){
//            $key = 'try_number_' . bin2hex($user);
//            $try_number = (int)RedCache::get($key);
//            if ($try_number) {
//                if ($try_number >= 5) {
//                    $this->jsonResponsePage(array('status' => 0, 'message' => '错误次数过多请稍后再试'));
//                } else {
//                    RedCache::set($key, ($try_number + 1), 360);
//                }
//            } else {
//                RedCache::set($key, 1, 360);
//            }
//        }

        $pwd = md5($pwd);
        $user_info=false;
        if($user and $pwd){

            if(is_numeric($user)){
                $user_info = $this->_getPlayOrganizerTable()->get(array('id'=>$user));
            }else{
                $user_info = $this->_getPlayOrganizerTable()->get(array('name'=>$user));
            }

            if (!$user_info) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '商家不存在'));
            }

            if($user_info->password != $pwd){
                return $this->jsonResponsePage(array('status' => 0, 'message' => '密码错误'));
            }

            if($user_info->status==0){
                return $this->jsonResponsePage(array('status' => 0, 'message' => '账号已删除或禁用'));
            }

            if($user_info){
                //生成验证信息
                $hash_data = json_encode(array('user' => $user, 'id' => (int)$user_info->id, 'group' => 2));
                $token = hash_hmac('sha1', $hash_data, $this->_getConfig()['token_key']);
                setcookie('type', 1, time() + 28800, '/');
                setcookie('user', $user, time() + 28800, '/');
                setcookie('token', $token, time() + 28800, '/');
                setcookie('group', 2, time() + 28800, '/');
                setcookie('id', (int)$user_info->id, time() + 28800, '/');
                return $this->jsonResponsePage(array('status' => 1, 'message' => '登陆成功'));

            }else {
                header('Location: /seller/index/login', true);
            }
        }
        $v = new ViewModel();
        $v->setTerminal(true);
        return $v;
    }

    //忘记密码
    public function forgetAction(){
        $newPwd = trim($this->getPost('new_pwd'));
        $user = trim($this->getPost("user"));
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
            if(!$this->check_verify_code($code)){
                $this->jsonResponse(array('status' => 0, 'message'=>'验证码错误'));
            }

            //新密码合法性
            if(!$this->check_pwd_len($newPwd)){
                return $this->jsonResponsePage(array('status'=> 0, 'message' => '密码格式错误，密码长度必须大于6位并且小于18位'));
            }

            $status = $this->_getPlayOrganizerTable()->update(array('pwd'=>$newPwd,'password'=>md5($newPwd),'dateline'=>time()),array("id='$user' or name='$user'"));
            if($status){
                $this->_getPlayAuthCodeTable()->update(array('status'=>0),array('phone'=>$phone,'code'=>$code,'status'=>1));
                return $this->jsonResponsePage(array('status'=> 1, 'message' => '更新成功'));
            }else{
                return $this->jsonResponsePage(array('status'=> 0, 'message' => '更新失败'));
            }
        }
        $v = new ViewModel();
        $v->setTerminal(true);
        return $v;

    }

    //发送手机验证码
    public function sendCodeAction()
    {

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

        if (!$user_info) {
            return $this->jsonResponse(array('status' => 0, 'message' => '手机号码有误'));
        }

        if (is_numeric($user) ? $user != $user_info['id'] : $user != $user_info['name']) {
            return $this->jsonResponse(array('status' => 0, 'message' => '用户名和手机号不匹配'));
        }

        //todo 避免同一用户并发
        if (RedCache::get('send' . $phone)) {
            //todo 间隔时间过短
            // return $this->jsonResponse(array('status' => 0, 'message' => '发送频率过高，请稍后再试'));
        } else {
            RedCache::set('send' . $phone, time(), 2);
        }

        //todo 一天内 只允许5条,每条相隔1分钟
        $time = time() - 43200;
        $data = $this->_getPlayAuthCodeTable()->fetchAll(array('phone' => $phone, "time>{$time}"), array('id' => 'desc'), 5)->toArray();

        if (!empty($data) and (time() - $data[0]['time']) < 300) {
            //return $this->jsonResponse(array('status' => 0, 'message' => '发送频率过高'));
        }
        if (count($data) == 5) {
            //return $this->jsonResponse(array('status' => 0, 'message' => '超过每日短信限制'));
        }

//        发送验证码
//        $code = \Deyi\SendMessage::SendAuthCode($phone);
//        if (!$code) {
//            return $this->jsonResponse(array('status' => 0, 'message' => SendMessage::$error));
//        } else {
            $this->_getPlayAuthCodeTable()->insert(array('phone' => $phone, 'time' => time(), 'code' => '123456', 'status' => 1));
//            return $this->jsonResponse(array('status' => 1, 'message' => '验证码发送成功'));
//        }
        return $this->jsonResponse(array('status' => 1, 'message' => '验证码发送成功'));
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

    //修改密码
    public function changeAction(){
        $newPwd = trim($this->getPost('new_pwd'));
        $user = trim($this->getPost("name"));
        $pwd = trim($this->getPost('pwd'));
        $pwd = md5($pwd);
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
            if($user_info->password != $pwd){
                return $this->jsonResponsePage(array('status' => 0, 'message' => '原密码错误'));
            }
            if($user_info->status==0){
                return $this->jsonResponsePage(array('status' => 0, 'message' => '账号已删除或禁用'));
            }
            //验证密码长度有效性
            if(!$this->check_pwd_len($newPwd)){

                return $this->jsonResponsePage(array('status'=> 0, 'message' => '密码格式错误，密码长度必须大于6位并且小于18位'));
            }

            if(is_numeric($user)){
                $status = $this->_getPlayOrganizerTable()->update(array('pwd'=>$newPwd,'password'=>md5($newPwd),'dateline'=>time()),array("id='$user'"));
            }else{
                $status = $this->_getPlayOrganizerTable()->update(array('pwd'=>$newPwd,'password'=>md5($newPwd),'dateline'=>time()),array("name='$user'"));
            }
            if($status){
                return $this->jsonResponsePage(array('status'=> 1, 'message' => '更新成功'));
            }else{
                return $this->jsonResponsePage(array('status'=> 0, 'message' => '更新失败'));
            }
        }
    }


    //商家销售数据
    public function infoAction(){
        $shop_id = $_COOKIE['id'];
        $code = trim($this->getQuery('code',null));
        //昨日销量统计
        $beginYesterday=mktime(0,0,0,date('m'),date('d')-1,date('Y'));
        $endYesterday=mktime(0,0,0,date('m'),date('d'),date('Y'))-1;

        //获取商家昨天销量数据
        $s1 = "select sum(buy_number) as sale_num,sum(coupon_unit_price) as sale_cash from play_order_info where shop_id={$shop_id} and dateline between {$beginYesterday} and {$endYesterday}";

        $data = $this->query($s1);

        foreach($data as $v){
            $yesterday_data = $v;
        }
        //30天销量数据
        $btime = strtotime('-30day');
        $etime = time();
        $s2 = "select sum(buy_number) as sale_num,sum(coupon_unit_price*buy_number) as sale_cash from play_order_info where shop_id={$shop_id} and dateline between {$btime} and  {$etime}";
        $data = $this->query($s2);
        foreach($data as $v){
            $month_data = $v;
        }

        //获取商家商品信息
        $goods_data = $this->_getPlayOrganizerGameTable()->getGameInfo(0,0,array(),array('play_organizer_game.organizer_id'=>$shop_id),array())->toArray();
        $goods_data = $this->FmData($goods_data);
        return array(
            'yes_sale_num'=> $yesterday_data['sale_num']==null ? 0 : $yesterday_data['sale_num'],
            'yes_sale_cash'=> $yesterday_data['sale_cash']==null ? 0 : $yesterday_data['sale_cash'] ,
            'month_sale_num'=>$month_data['sale_num']==null ? 0 : $month_data['sale_num'],
            'month_sale_cash'=>$month_data['sale_cash']==null ? 0 : $month_data['sale_cash'],
            'data'=>$goods_data,
            'code'=>$code
        );
    }


    //订单情况
    public function orderAction(){
        $shop_id = $_COOKIE['id'];
        $page = (int)$this->getQuery('p',1);
        $pageSum = 10;
        $start = ($page-1)*$pageSum;
        $order_sn = trim($this->getQuery('order_sn',null));
        $username = trim($this->getQuery('username',null));
        $dateline = trim($this->getQuery('dateline',null));
        $order_status = (int)$this->getQuery('typeid');

        $where = "play_order_info.order_sn>0 and play_order_info.order_status=1 and pay_status>=2 and shop_id={$shop_id}";
        $order = "play_order_info.order_sn DESC ";

        if($order_sn){
            $where .= " and play_order_info.order_sn={$order_sn}";
        }

        if($username){
            $where .= " and play_order_info.username like '%{$username}%'";
        }

        if($dateline){
            $where .= " and play_order_info.dateline>=".strtotime($dateline);
        }

        if($order_status){
            if($order_status==2){
                $where .= " and play_coupon_code.status=0";
            }
            if($order_status==3){
                $where .= " and play_coupon_code.status=1";
            }
            if($order_status==4){
                $where .= " and play_coupon_code.status=2";
            }
        }

        $order_data = $this->_getPlayOrderInfoTable()->getShopAdminOrder($start,$pageSum,$where,$order);
        $sql ="select order_sn from play_order_info where play_order_info.order_sn>0 and play_order_info.order_status=1 and pay_status>=2 and shop_id={$shop_id}";

        $count = $this->query($sql)->count();

        $s1 = "select sum(account_money) as sale_cash,sum(buy_number-back_number) as sale_num from play_order_info where play_order_info.order_sn>0 and play_order_info.order_status=1 and pay_status>=2 and shop_id={$shop_id}";

        $sale_total = $this->query($s1)->current();

        $url = "/selleradmin/index/order";

        $paginator = new Paginator($page,$count,$pageSum,$url);

        return array(
            'sale_cash'=>$sale_total['sale_cash'],
            'sale_num'=>$sale_total['sale_num'],
            'data'=>$order_data,
            'pageData'=>$paginator->getHtml()
        );


    }

    public function logoutAction()
    {
        setcookie('user', '', -3600, '/');
        setcookie('token', '', -3600, '/');
        setcookie('group', '', -3600, '/');
        setcookie('id', '', -3600, '/');
        setcookie('type', '', -3600, '/');
        $view = new ViewModel(array());
        return $view->setTemplate('shop/index/login');
    }


    //分店信息
    public function shopsAction(){
        $shop_id = $_COOKIE['id'];
        $organizerData = $this->_getPlayOrganizerTable()->get(array('id'=>$shop_id));

        if (!$organizerData || $organizerData->branch_id) {
            return $this->_Goto('该商家不是总店');
        }

        //php获取今日开始时间戳和结束时间戳
        $beginToday=mktime(0,0,0,date('m'),date('d'),date('Y'));
        $endToday=mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;

        $sql = "select count(play_organizer_game.id) as on_sale_num,play_organizer.name,organizer_id,play_organizer.cover from play_organizer_game left join play_organizer on play_organizer.id=play_organizer_game.organizer_id where organizer_id in (select id from play_organizer where branch_id={$shop_id}) GROUP BY organizer_id";
        $data = $this->query($sql);
        foreach($data as $v){
            $info[] = $v;
        }
        $sql = "select sum(play_order_info.account_money+play_order_info.voucher) as total_sale_cash,shop_id from play_order_info where shop_id in(select id from play_organizer where branch_id={$shop_id}) and dateline BETWEEN {$beginToday} and {$endToday} GROUP BY shop_id
";
        $data = $this->query($sql);
        foreach($data as $v){
            $order[] = $v;
        }
        foreach($info as $k=>$v){
            $info[$k]['total_sale_cash']=0;
            foreach($order as $key=>$val){
                if($info[$k]['organizer_id']==$order[$k]['shop_id']){
                    $info[$k]['total_sale_cash'] = $order[$k]['total_sale_cash'];
                }
            }
        }
//        $organizerData = $this->_getPlayOrganizerTable()->getBranchInfo(array('name','cover','thumb'),array('branch_id' => $shop_id));

        return array(
            'data'=>$info
        );
    }


    //商家信息
    public function articleAction(){

    }

    //绑定银行信息
    public function accountAction(){
        $shop_id = $_COOKIE['id'];
        $bank_user = trim($this->getQuery('bang_user',null));
        $bank_name = trim($this->getQuery('bank_name',null));
        $bank_address = trim($this->getQuery('bank_address',null));
        $bank_card = trim($this->getQuery('bank_card',null));


        $v = new ViewModel();
        $v->setTerminal(true);
        return $v;

    }

    /**
     * @param $sql
     * @return Result;
     */
    function query($sql)
    {
        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $stmt = $db->query($sql);
        $result = $stmt->execute($stmt);
        return $result;
    }

    public function FmData($data=array()){
        $info = array();

        foreach($data as $k=>$v){
            if($v['is_together']==1 && $v['status']==1 && $v['up_time'] > time()){
                $info['not_start'][]=$v;
            }

            if($v['is_together']==1 && $v['status']==1 && $v['up_time'] < time() && $v['down_time']>time()){
               $info['on_sale'][]=$v;
            }

            if($v['test_status']==3 or $v['test_status']){
                $info['not_clean'][]=$v;
            }

            if($v['test_status']==5){
                $info['clean'][]=$v;
            }
        }
        return $info;
    }
}