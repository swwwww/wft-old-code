<?php

namespace Web\Controller;

use Deyi\Account\Account;
use Deyi\BaseController;
use Deyi\Integral\Integral;
use Deyi\JsonResponse;
use library\Service\System\Cache\RedCache;
use Deyi\Upload;
use Deyi\WeiXinFun;
use Deyi\WeiXinPay\WeiXinPayFun;
use Deyi\WriteLog;
use Zend\Db\Sql\Expression;
use Zend\Http\Response;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class H5Controller extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    // 积分中心wap
    public function integralAction()
    {

        $uid = $this->getWapUid();

        if (!$uid) {

            header('Location: http://wan.wanfantian.com/app/index.php');
        }

        $inte = new Integral();
        $acc = new Account();

        $setting = $inte->getSetting();
        $user_data = $this->_getPlayUserTable()->get(array('uid' => $uid));


        $db = $this->_getAdapter();
        $buy_name = '';
        //查询所有订单 已使用
        $res = $db->query("select * from play_order_info
LEFT JOIN  play_order_otherdata ON play_order_info.order_sn=play_order_otherdata.order_sn
WHERE  user_id=? and order_status=1 AND play_order_info.pay_status=5 ", array($uid));
        $buy_order_sn = 0;
        $buy_max_inte=0;
        foreach ($res as $v) {
            if (!isset($v->comment) or $v->comment == 0) {
                $buy_name = $v->coupon_name;
                $buy_order_sn = $v->order_sn;

                //一个商品 一个点赞能够获得的所有积分
                $log = $this->_getPlayWelfareIntegralTable()->get(array('object_id' => $v->coupon_id, 'object_type' => 2, 'welfare_type' => 3));
                if ($log) {
                    $buy_max_inte = $setting->place_comment_integral * $log->double;
                } else {
                    $buy_max_inte = $setting->place_comment_integral;
                }
                $buy_max_inte += $setting->good_comment_prize_integral;
                continue;
            }
        }

        $task = $this->_getPlayTaskIntegralTable()->get(array('city' => $this->getCity()));

        //新手任务列表
        $tmp = array(
            11 => array("更换头像", $setting->face_integral, 0),  //任务名称,积分,是否已完成  确认积分
            14 => array("微信绑定", $setting->weixin_bind, 0),
            12 => array("补充宝宝资料", $setting->baby_info, 0),
            13 => array("上传宝宝头像", $setting->baby_face, 0)
        );

        // 判断任务有哪些有效通过$task,哪些已完成
        $new_task = unserialize($task->new_task);
        $new_list = array();
        $ok_num = 0;

        $new_integral = 0;
        $day_integral = 0;

        $complate = [];
        $integral = $this->_getPlayIntegralTable()->fetchLimit(0,5,[],['uid'=>$uid,'type'=>[11,14,12,13]])->toArray();
        if($integral){
            foreach($integral as $i){
                $complate[] = $i['type'];
            }
        }

        foreach ($new_task as $k => $v) {
            $new_list[$v] = $tmp[$v];
            //todo 判断是否已完成
            $ok = false;
            if ($v == 11) {
                if ($user_data->img and in_array($v,$complate)) {
                    $ok = true;
                    $new_integral += $tmp[$v][1];
                }
            } elseif ($v == 12) {
                $res = $this->_getPlayUserBabyTable()->get(array('uid' => $uid));
                if ($res and $res->baby_name and in_array($v,$complate)) {
                    $ok = true;
                    $new_integral += $tmp[$v][1];
                }
            } elseif ($v == 13) {
                $res = $this->_getPlayUserBabyTable()->get(array('uid' => $uid));
                if ($res and $res->img and in_array($v,$complate)) {
                    $ok = true;
                    $new_integral += $tmp[$v][1];
                }
            } elseif ($v == 14) {
                $res = $this->_getPlayUserWeiXinTable()->get(array('uid' => $uid, 'login_type' => 'weixin_sdk'));
                if ($res and in_array($v,$complate)) {
                    $ok = true;
                    $new_integral += $tmp[$v][1];
                }
            }
            if ($ok) {
                $new_list[$v][2] = 1;
                $ok_num += 1;
            }
        }

        $new_number = (int)($ok_num / count($new_list) * 100);

        /************************** 每日任务 *******************************/

        $invite=$this->_getInviteRule()->get(array('city'=>$this->getCity()));
        if($invite and $invite->r_inviter_type==0){
            $invite_num=$invite->r_inviter_award;
        }else{
            $invite_num=0;
        }

        //每日任务
        $day_list = array(
            8 => array("签到", $setting->sign_one, 0),//任务名称,积分,是否已完成
            22 => array("邀请好友注册",$invite_num, 0),
            3 => array("分享商品", $setting->share_good_integral, 0),
            1 => array("分享游玩地", $setting->share_place_integral, 0),
            4 => array("点评商品", $setting->good_comment_integral, 0),
            2 => array("点评游玩地", $setting->place_comment_integral, 0),
            5 => array("购买商品", (int)$setting->good_integral_price.'倍', 0),
         //   15 => array("圈子发言", $setting->circle_speak, 0)
        );

        $day_ok_num = 0;
        $day_task = array();

        $new_task = unserialize($task->day_task);

        $s_time = strtotime(date("Y-m-d"));

        $res = $this->_getPlayIntegralTable()->fetchAll(array( 'uid' => $uid, 'create_time>=' . $s_time))->toArray();
        foreach ($new_task as $k => $v) {
            $day_task[$v] = $day_list[$v];
            //todo 判断是否已完成
            $ok = false;

            $d = $this->getIsDo($res,$v);
            if ($d['isok'] > 0) {
                $ok = true;
                //$day_integral += $d['score'];
            }

            if($v == 22){//如果是邀请，有可能是返利
                $hsinvert  = 1;
                $invert = $this->_getInviteMember()->fetchLimit(0,1,[],['sourceid'=> $uid,'register_time > ?'=>$s_time,'status > ?' =>0])->toArray();
                if ($invert) {
                    $ok = true;
                }
                $rule = $this->_getInviteRule()->fetchLimit(0,1,[],['city'=>$this->getCity()])->current();

                if($rule && $rule->r_inviter_type == 1){
                    $day_task[$v][1] = $rule?$rule->r_inviter_award.'元现金券':'现金券';
                }else{
                    $day_task[$v][1] = $rule?$rule->r_inviter_award:0;
                }
            }

            if ($ok) {
                $day_task[$v][2] = 1;
                $day_ok_num += 1;
            }
        }

        if ($user_data->img) {
            $setting->face_integral = 0;
        }

        if(!$hsinvert){
            unset($day_list[22]);
        }

        $day_number = (int)($day_ok_num / count($new_task) * 100);

        $view = new ViewModel(array(
            'integral' => $inte->getUserIntegral($uid),
            'username' => $user_data->username,
            'userimg' => $this->getImgUrl($user_data->img),
            'user_alias' => $user_data->user_alias,
          //  'buy_name' => $buy_name,
            'buy_order_sn' => $buy_order_sn,
            'buy_max_inte' => $buy_max_inte,
            'setting' => $setting,

            'task' => $task,
            //新手任务
            'new_list' => $new_list,
            'ok_num' => $ok_num,  //已完成
            'new_work_number' => $new_number, //百分比

            //每日任务
            'day_list' => $day_task,
            'day_ok_num' => $day_ok_num,
            'day_number' => $day_number,

            //已获得了
//            'new_integral'=>$new_integral,
//            'day_integral'=>$day_integral,

        ));
        $view->setTerminal(true);
        return $view;
    }

    /**
     * 返回积分任务明细
     * @param $res
     * @param $type 1,2,3,4,5,8,15,22
     */
    public function getIsDo($res,$type){
        $invert = $share_goods = $share_places = $give_goods = $minus_goods = $give_places = $minus_places = $give_buy = $minus_return = $give_circle = $minus_circle =0;
        $invert_score = $share_goods_score = $share_places_score = $give_goods_score = $minus_goods_score = $give_places_score = $minus_places_score = $give_buy_score = $minus_return_score = $give_circle_score = $minus_circle_score =0;
        $sign_score = $sign = 0;
        $integral = [];
        if($res){
            foreach($res as $d){
                if($d['type']==8 || $d['type']==9){//签到
                    $sign = 1;
                    $sign_score += $d['total_score'];
                }
                if($d['type']==4){//商品评论
                    $give_goods++;
                    $give_goods_score += $d['total_score'];
                }
                if($d['type']==106 || $d['type']==107){//删去商品评论
                    $minus_goods++;
                    $minus_goods_score += $d['total_score'];
                }
                if($d['type']==2){//游玩地评论 8 9
                    $give_places++;
                    $give_places_score += $d['total_score'];
                }
                if($d['type']==1){//游玩地分享
                    $share_places++;
                    $share_places_score += $d['total_score'];
                }
                if($d['type']==3){//商品分享
                    $share_goods++;
                    $share_goods_score += $d['total_score'];
                }
                if($d['type']==108 || $d['type']==109){//游玩地评论 8 9
                    $minus_places++;
                    $minus_places_score += $d['total_score'];
                }
                if($d['type']==5){//购买商品积分
                    $give_buy++;
                    $give_buy_score += $d['total_score'];
                }
                if($d['type']==100){//退款获得积分
                    $minus_return++;
                    $minus_return_score += $d['total_score'];
                }
                if ($d['type'] == 15) {//购买商品积分
                    $give_circle++;
                    $give_circle_score += $d['total_score'];
                }
                if($d['type']==103 || $d['type']==104){//游玩地评论 8 9
                    $minus_circle++;
                    $minus_circle_score += $d['total_score'];
                }
                if ($d['type'] == 22) {//邀请
                    $invert++;
                    $invert_score += $d['total_score'];
                }
            }

        }
        //1,2,3,4,5,8,15,22
        if($type == 1){//游玩地分享
            $integral[$type]['isok'] = $share_places;
            $integral[$type]['score'] = $share_places_score;
            return $integral[$type];
        }
        if($type == 2){//游玩地评论
            $integral[$type]['isok'] = $give_places - $minus_places;
            $integral[$type]['score'] = $give_places_score - $minus_places_score;
            return $integral[$type];
        }
        if($type == 3){//商品分享
            $integral[$type]['isok'] = $share_goods;
            $integral[$type]['score'] = $share_goods_score;
            return $integral[$type];
        }
        if($type == 4){//商品评论
            $integral[$type]['isok'] = $give_goods - $minus_goods;
            $integral[$type]['score'] = $give_goods_score - $minus_goods_score;
            return $integral[$type];
        }
        if($type == 5){//商品购买
            $integral[$type]['isok'] = $give_buy - $minus_return;
            $integral[$type]['score'] = $give_buy_score - $minus_return_score;
            return $integral[$type];
        }
        if($type == 8){//签到
            $integral[$type]['isok'] = $sign;
            $integral[$type]['score'] = $sign_score;
            return $integral[$type];
        }

        if($type == 22){//邀约
            $integral[$type]['isok'] = $invert;
            $integral[$type]['score'] = $invert_score;
            return $integral[$type];
        }

    }

    //获得目前新手任务获得的积分
    public function getNewIntegral($uid){

    }

    //获得目前每日任务获得的积分
    public function getDayIntegral($uid){

    }

    //我要资格券/介绍等 ok
    public function introduceAction($uid)
    {

        if(!$this->is_weixin()){
            if(!$this->is_wft()){
                header('Location: http://wan.wanfantian.com/app/index.php');
            }
        }

        $inte = new Integral();
        $s = $inte->getSetting();
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->score = $s->integral_quota;
        return $view;
    }

    // 积分规则
    public function scorerulesAction()
    {

        if(!$this->is_wft()){
            header('Location: http://wan.wanfantian.com/app/index.php');
        }

        $inte = new Integral();
        $s = $inte->getSetting();



        $view = new ViewModel(array(
            's' => $s
        ));
        $view->setTerminal(true);
        return $view;
    }

    //优惠券规则 ok
    public function rulesAction()
    {
        if(!$this->is_weixin()){
            if(!$this->is_wft()){
                header('Location: http://wan.wanfantian.com/app/index.php');
            }
        }

        $view = new ViewModel();
        $view->setTerminal(true);
        return $view;
    }

    //编辑约稿
    public function inviteAction()
    {
        if(!$this->is_wft()){
            header('Location: http://wan.wanfantian.com/app/index.php');
        }

        $body=$this->_getPlayInviteContentTable()->get(array('city'=>$this->getCity()));

        $view = new ViewModel(
            array('body'=>$body)
        );
        $view->setTerminal(true);
        return $view;
    }

    //好评有礼
    public function giftAction()
    {
        if(!$this->is_wft()){
            header('Location: http://wan.wanfantian.com/app/index.php');
        }
        $uid = $this->getWapUid();
        if (!$uid) {
            exit('用户信息不存在');
        }
        //获取邀请码
        $code = strtoupper(base_convert($uid + 123456789, 10, 32));
        $data = $this->_getPlayGoodCommentTable()->get(['city'=>$this->getCity()]);

        $ios_url = 'https://itunes.apple.com/cn/app/de-yi-sheng-huo-lun-tan/id950652997?mt=8';
        $android_url = $data->url;

        //获取
        $view = new ViewModel(array('code' => $code, 'data' => $data, 'ios_url' => $ios_url, 'android_url' => $android_url));
        $view->setTerminal(true);
        return $view;
    }

    // 账户使用说明
    public function accountAction()
    {
        if(!$this->is_wft()){
            header('Location: http://wan.wanfantian.com/app/index.php');
        }
        $view = new ViewModel();
        $view->setTerminal(true);
        return $view;
    }


    // 账户使用说明
    public function InsuranceAction()
    {
        if(!$this->is_wft()){
            header('Location: http://wan.wanfantian.com/app/index.php');
        }

        $view = new ViewModel();
        $view->setTerminal(true);
        return $view;
    }

    // 活动免责声明
    public function disclaimerAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        return $view;
    }

    //分销规则说明
    public function distributionAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        return $view;
    }







    /**
     * 专场活动
     */
    public function privatePartyAction()
    {
//        echo '<h1>活动登记页面</h1>';
//        exit;
//
//
//        if(!$this->is_wft()){
//            header('Location: http://wan.wanfantian.com/app/index.php');
//        }
        $coupon_id = (int)$this->getQuery('coupon_id');

        if($this->is_weixin()){
            $url = $this->_getConfig()['url'] . "/web/h5/privateparty?coupon_id={$coupon_id}";
            $weixin = new WeiXinFun($this->getwxConfig());
            if (!$this->userInit($weixin) and !$this->checkWeiXinUser()) {
                $toUrl = $weixin->getAuthorUrl($url, 'snsapi_userinfo');
                header("Location: $toUrl");
                exit;
            }
        }
        $view = new ViewModel([
            'id'=>$coupon_id
        ]);
        $view->setTerminal(true);
        return $view;
    }

    /**
     * 专场活动登记
     */
    public function privatePartyRegAction()
    {
        if($this->is_weixin()) {
            $uid = $_COOKIE['uid'];
        }else{
            $uid=$this->getWapUid();
        }
        $name=$this->getPost('name');
        $phone=$this->getPost('phone');
        $coupon_id=$this->getPost('coupon_id',0);
        $num = $this->getPost("num",1);
        if(!$uid or !$name or !$phone or !$num){
            return $this->jsonResponseError('参数错误');
        }
        if(!is_numeric($phone) or strlen($phone)!==11){
            return $this->jsonResponse(array('status'=>0,'message'=>'手机号码格式错误!'));
        }

        if(!is_numeric($num) or $num<20){
            return $this->jsonResponse(array('status'=>0,'message'=>'每场至少20个家庭!'));
        }

        if($coupon_id){
            //商品详情进来的
            $data=$this->_getPlayPrivatePartyTable()->get(array('uid'=>$uid,'coupon_id'=>$coupon_id));
            if($data){
                return $this->jsonResponse(array('status'=>0,'message'=>'您已经提交过了!'));
            }
        }

        $status=$this->_getPlayPrivatePartyTable()->insert(array('uid'=>$uid,'name'=>$name,'phone'=>$phone,'coupon_id'=>$coupon_id,'dateline'=>time(),'join_number'=>$num));
        if($status){
            return $this->jsonResponse(array('status'=>1,'message'=>'提交成功!'));
        }else{
            return $this->jsonResponse(array('status'=>0,'message'=>'提交失败!'));
        }


    }

    public function mapAction(){

    }



}
