<?php

namespace WebInvite\Controller;

use Deyi\BaseController;
use Deyi\JsonResponse;
use Deyi\WeiXinFun;
use Deyi\Paginator;
use Deyi\Mcrypt;
use library\Service\System\Cache\RedCache;
use Deyi\Upload;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\EventManager\EventManagerInterface;
use Zend\Stdlib\ArrayObject;
use Zend\View\Model\ViewModel;
use Deyi\Invite\Invite;
use Deyi\SendMessage;

class IndexController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    //const ChancePerDay = 5;
    //const RemindLimitDay= 7;//


    private $_user;
    private $_uid;
    private $_username;
//    private $_typeId;        //类别id
    private $_inviteToken;   //邀约码
    private $_adminConf;     //后台配置
//    private $_siteId = 1;    //站点，默认为主站
    private $_city;    //站点，默认为主站
    private $_ruleId;    //规则id
    private $_template = 'web-invite/index/';
    private $_remindCachePrefix  = 'invite_remind_';//提醒时间戳
    private $_domain;
    private $down_url = 'http://wan.wanfantian.com/app/index.php';
    private $debug = FALSE;


    public function setEventManager(EventManagerInterface $events)
    {
        parent::setEventManager($events);

        $events->attach('dispatch', function ($e)
        {

            //todo 微信配置（分享）
            $weixin = new WeiXinFun($this->getwxConfig());

            $siteConfig=$weixin->getsignature();
            $siteConfig['nonceStr']=$siteConfig['noncestr'];
            $siteConfig['url']=$this->_getConfig()['url'];

            $this->layout()->setVariables([
                'jsconfig' => $siteConfig,
            ]);

        }, 100);

    }

    /**
     *邀约首页
     * 生成邀请码以及二维码图片
     */
    public function indexAction()
    {
        if($this->debug === TRUE){
            $this->_domain = 'http://wft.com';
            $this->_uid = 181856;
            $this->_username = '麒麟';
            $this->_city = 'WH';
            $this->_domain = $this->_getConfig()['url'];

            //获取指定站点的后台配置
            $this->_adminConf = $this->_getInviteRule()->getByRuleId($this->_city);
            if($this->_city != 'WH'){
                //如果为分站，且没有规则，则调用武汉主站规则
                if(empty($this->_adminConf)){
                    $this->_adminConf = $this->_getInviteRule()->getByRuleId('WH');
                }
            }
            if(empty($this->_adminConf)){
                exit('获取规则失败!');
            }
            @$this->_ruleId = $this->_adminConf->ruleid;//规则id
        }else{
            //判断用户状态
            //$uid = 17900;
            $uid = $this->getWapUid();

//            var_dump($uid);exit;
            if(!$uid){
                header('Location: http://wan.wanfantian.com/app/index.php');
                exit;
//                exit('未登陆啊！');
            }else{
                setcookie('uid',$uid,time()+3600*24*30,'/');
            }
            $this->_user = $this->_getPlayUserTable()->get(['uid' => $uid]);
            $this->_uid = $this->_user->uid;
            $this->_username = $this->_user->username;
            //获取站点，匹配设置
            $city = $this->_user->city;
            $this->_city = $city;
            $this->_domain = $this->_getConfig()['url'];

            //获取指定站点的后台配置
            $this->_adminConf = $this->_getInviteRule()->getByRuleId($this->_city);

            if(empty($this->_adminConf)){
                $this->_adminConf = $this->_getInviteRule()->getByRuleId('WH');
            }

            if(empty($this->_adminConf)){
                exit('获取规则失败!');
            }
            @$this->_ruleId = $this->_adminConf->ruleid;//规则id
        }
        @$bannerImg = $this->_adminConf->banner_img;//banner图
        @$rule = $this->_adminConf->banner;//banner图下活动文字说明
        @$share_img = $this->_adminConf->share_img;//分享参数集合
        @$share_title = $this->_adminConf->share_title;//分享参数集合
        @$share_desc = $this->_adminConf->share_desc;//分享参数集合

        $r_reciever_type = @$this->_adminConf->r_reciever_type == 0 ? '积分' : '元现金券';//受邀用户奖励类型(0:积分；1:现金券)
        $r_reciever_award = @$this->_adminConf->r_reciever_award;//受邀用户绑定手机号并注册的奖励
        //初始化用户邀约码token
        if ($this->_uid > 0) {
            $this->_inviteToken = $this->_getInviteToken()->getToken($this->_ruleId, $this->_uid);//判断account_token是否有这个用户，有就取shareToken
            if (!$this->_inviteToken) {//没就插入并生成inviteToken
                $this->_inviteToken = $this->_getInviteToken()->setToken($this->_ruleId, $this->_uid, $this->_username,$this->_user->img);
            }
            //邀请码
            //echo $this->_inviteToken;
            $inviteUrl = $this->_domain.'/webinvite/index/recieve?token='.$this->_inviteToken;
            //邀请码图片
            //echo '<img src="http://wft.com/application/index/phpqrcode?code='.$inviteUrl.'"/>';

            $this->layout()->setVariables([
                'token' => $this->_inviteToken,
                'share_title'   => $share_title,
                'share_img'   => $share_img,
                'share_desc'   => $share_desc,
            ]);

            $v = new ViewModel([
                'domain' => $this->_domain,
                'bannerImg' => $bannerImg,
                'rule' => $rule,
                'inviteUrl' => $inviteUrl,
                'awardWord' => $r_reciever_award.$r_reciever_type,//弹窗二维码奖品上文字
                'inviteToken' => $this->_inviteToken,
                'share_title'   => $share_title,
                'p' => $_GET['p'],
            ]);
            $v->setTerminal(true);
            return $v;
        }else{
            exit('不存在该用户');
        }
    }



    //todo 接收页面
    public function recieveAction()
    {
        $token = $this->getQuery('token');
        if(empty($token) || !$token){
            exit('参数错误！');
        }
        //todo 通过token对应的ruleid去取规则
        $inviteData = $this->_getInviteToken()->getByToken($token);//判断account_token是否有这个用户，有就取shareToken
//        var_dump($inviteData);exit;
        if($inviteData){
            //找到邀请码对应邀请用户
            //找到该站点对应的规则
            //获取指定站点的后台配置
            $this->_adminConf = $this->_getInviteRule()->getByMyRuleId($inviteData->ruleid);

            if(empty($this->_adminConf)){
                $this->_adminConf = $this->_getInviteRule()->getByRuleId('WH');
            }

            if(empty($this->_adminConf)){
                exit('获取规则失败!');
            }
            @$this->_ruleId = $this->_adminConf->ruleid;//规则id
            $r_reciever_type = @$this->_adminConf->r_reciever_type == 0 ? '积分' : '现金券';//受邀用户奖励类型(0:积分；1:现金券)
            $r_reciever_award = @$this->_adminConf->r_reciever_award;//受邀用户绑定手机号并注册的奖励
            //$r_reciever_award = $r_reciever_type == '现金券' ? $r_reciever_award.'元' : $r_reciever_award;


            $v = new ViewModel([
                'inviteUid' => @$inviteData->uid,//邀请人uid
                'inviteUsername' => @$inviteData->username,//邀请人名字
                'inviteImg' => $this->getImgUrl($inviteData->img),//邀请人头像
                'token' => $token,
                'r_reciever_type' => $r_reciever_type,
                'r_reciever_award' => $r_reciever_award,
                'in_coupon' => @$this->_adminConf->city,
            ]);
            $v->setTerminal(true);
            return $v;

        }else{
            //没找到改邀请码所对应的信息
            exit('邀请码参数错误！');
        }

    }


    //todo 绑定手机号 ajax
    public function bindAction()
    {
        $token = $this->getPost('token');
        $phone = $this->getPost('phone');
//        $token = $this->getQuery('token');
//        $phone = $this->getQuery('phone');
        if(empty($token) || !$token){
            exit('参数错误！');
        }
        if(empty($phone) || !$phone){
            exit('参数手机号！');
        }
        $inviteData = $this->_getInviteToken()->getByToken($token);//判断token是否有这个用户，有就取Token
        if($inviteData){
            //查询改手机号用户是否为已注册用户
            $oldUser = $this->_getPlayUserTable()->get(['phone' => $phone]);
            if($oldUser){
                exit(json_encode(['code' => -3, 'msg' =>'您手机已经注册过，为老用户账号']));//您已经接受过
            }
            //获取指定站点的后台配置
            $this->_adminConf = $this->_getInviteRule()->getByMyRuleId($inviteData->ruleid);

            //如果为分站，且没有规则，则调用武汉主站规则
            if(empty($this->_adminConf)){
                $this->_adminConf = $this->_getInviteRule()->getByRuleId('WH');
            }

            if(empty($this->_adminConf)){
                exit('获取规则失败!');
            }
            @$this->_ruleId = $this->_adminConf->ruleid;//规则id
            @$this->_city = $this->_adminConf->city;//规则id
            //找到邀请码对应邀请用户
            $uid = $inviteData->uid;//邀请人uid
            $ruleid = $inviteData->ruleid;//对应站点的邀约规则
            //查询该手机用户是否已经邀约
            $exist = $this->_getInviteMember()->getByPhone($phone);
            //已经存在该用户
            if(!empty($exist) && isset($exist)){
                exit(json_encode(['code' => -2, 'msg' =>'您手机已经接受过['.$inviteData->username.']的邀约']));//您已经接受过
            }

            //查询当天该邀约用户因注册（扫码）奖励的次数
/*            $awardNum = $this->_getInviteInviterAwardLog()->getOrderAwardPerDay($uid,1,$this->_adminConf->r_inviter_type);
            $awardNum = $awardNum->count;*/

            $row = [
                'phone' => $phone,
                'token' => $token,
                'sourceid' => $uid,
                'ruleid' => $ruleid,
                'status' => 0,
                'dateline' => time(),
                'remind_time' => 0
            ];
            //达到当天限定的次数,给A加积分//给b加卷(valid=1)之后调
/*            if($awardNum <= $this->_adminConf->invite_per_day){
                $row['valid'] = 1;//用户注册后就通过该字段判断是否发放现金卷
                //给a加积分，记录log
                //$Invite = new Invite();
                //$result = $Invite->InvitorAwardByRegister($uid,$phone,$this->_city);
            }*/

            //通过phone生成以手机号唯一的临时账号表(手机用户真正注册时，更新其log状态以及log表中的受邀人的uid)
            $data = $this->_getInviteMember()->insert($row);
//            $id = $this->_getInviteMember()->getlastInsertValue();
            //邀约成功
            if ($data > 0) {
                //app提醒
                $msg = '已有新伙伴领取您的邀请现金券，快提醒TA使用吧，TA使用后您将获得30积分、高达30元现金券。';
/*
                $r_reciever_award = @$this->_adminConf->r_reciever_award;//受邀用户绑定手机号并注册的奖励
                //将注册奖品写入日志(注册status为1,注册时将这个存入用户中)
                $this->_getInviteRecieverAwardLog()->insert([
                    'phone' => $phone,
                    'sourceid' => $uid,
                    'status' => 1,
                    'award_type' => @$this->_adminConf->r_reciever_type,
                    'award' => $r_reciever_award,
                    'ruleid' => $ruleid,
                    'dateline' => time()
                ]);*/
                exit(json_encode(['code' => 1, 'msg' =>'邀约成功']));
            } else {
                exit(json_encode(['code' => -1, 'msg' =>'邀约失败']));
            }

        }else{
            //没找到改邀请码所对应的信息
            exit(json_encode(['code' => -1, 'msg' =>'邀请码参数错误']));
        }

    }

    public function resultAction(){
        $token = $this->getQuery('token');
        $phone = $this->getQuery('phone');
        if(empty($token) || !$token){
            exit('参数错误！');
        }
        if(empty($phone) || !$phone){
            exit('参数手机号！');
        }
        //判断手机与该token的绑定关系以及当前状态
        $inviteData = $this->_getInviteMember()->get(['phone' => $phone, 'token' => $token]);//判断token是否有这个用户，有就取Token
        if($inviteData){
            //获取指定站点的后台配置
            $this->_adminConf = $this->_getInviteRule()->getByMyRuleId($inviteData->ruleid);

            //如果为分站，且没有规则，则调用武汉主站规则
            if(empty($this->_adminConf)){
                $this->_adminConf = $this->_getInviteRule()->getByRuleId('WH');
            }

            if(empty($this->_adminConf)){
                exit('获取规则失败!');
            }
            @$this->_ruleId = $this->_adminConf->ruleid;//规则id
            if($inviteData->status == 0){//初始绑定状态
                $r_reciever_type = @$this->_adminConf->r_reciever_type == 0 ? '积分' : '现金券';//受邀用户奖励类型(0:积分；1:现金券)
                $r_reciever_award = @$this->_adminConf->r_reciever_award;//受邀用户绑定手机号并注册的奖励
                $rule = @$this->_adminConf->banner;//banner图下活动文字说明

                $v = new ViewModel([
                    'phone' => $phone,
                    'r_reciever_type' => $r_reciever_type,
                    'r_reciever_award' => $r_reciever_award,
                    'rule' => $rule,
                    'in_coupon' => @$this->_adminConf->city,
                ]);
                $v->setTerminal(true);
                return $v;
            }else{
                //已发放
                exit;
            }

        }else{
            //未发现该对应phone/token的绑定记录
            exit;
        }


    }

    //todo 下载？何为判断依据（用app登陆？暂时不用判断，改为所有新注册都会发特权券）
    /*
                $d_reciever_type = @$this->_adminConf->d_reciever_type;//0：积分；1：现金券；2：资格券（目前只有2）
                $d_reciever_award = @$this->_adminConf->d_reciever_award;

                //todo 若此为wap版，则用户登陆到app则添加该记录
                //用户用app登陆后，将下载奖品写入日志(下载status为0,注册时将这个存入用户中)
                $this->_getInviteRecieverAwardLog()->insert([
                    'phone' => $phone,
                    'status' => 0,
                    'award_type' => @$d_reciever_type,
                    'award' => $d_reciever_award,
                    'ruleid' => $ruleid,
                    'dateline' => time()
                ]);*/


    //todo 注册 (已写)
    //1、用户注册的时候（向user表插入），需要查看用户是否在member中且status=0（仅为绑定状态），若是，需要给用户改status=1以及插入InviteRecieverAwardLog记录（十元红包）log.
    //2、为需要给邀约用户插入InviteInviterAwardLog记录log（加30积分）
    public function inviteRegisterAction(){
//        $phone = $this->getQuery('phone');
        $phone = '13545283787';
        $city = 'WH';
        $uid = 181927;
        $username = '13545283787';
        $invite = new Invite();
        $data =$invite->InvitorAwardByRegister($uid,$phone,'WH');
//        $data2 = $invite->inviteRegister($uid,$phone,$phone,'WH');
        var_dump($data);
//        var_dump($data2);exit;
        exit;
    }


    //todo 首单 (已写)
    //1、用户下单的时候，需要查看用户是否在member中且status=1（仅为注册状态），若是，则修改member中status=2（已首单），以及插入InviteInviterAwardLog中(加首单价格的30%红包，$price = first_order_price*3%;$price = $price > 30 ? 30 : $price;)//最多每日5个
    public function inviteOrderAction(){
        $adapter = $this->_getAdapter();
        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();
        $first_data = $adapter->query('SELECT * from play_order_info WHERE `user_id` = ? AND `order_status` = 1 AND `pay_status` >= 2 ORDER BY dateline ASC LIMIT 1;', array(181922))->current();

        //查询当前订单号是否为首单订单号(使用一张票券调一次该方法，一个订单中多票券的票券为同种票券)
        if($first_data->order_sn == 85896196){
            $invite = new Invite();
            //该单实际支付 = 银行卡支付+账号余额
            //每张票券价格 = 该单实际支付 / 票券数量
            $price = bcdiv(bcadd($first_data->real_pay,$first_data->account_money), $first_data->buy_number, 2);
            $s = $invite->firstOrder($first_data->user_id,$first_data->username,$first_data->phone,$first_data->order_city,$price);
            var_dump($s);exit;
        }



        //$adapter = $this->_getAdapter();
        //$conn = $adapter->getDriver()->getConnection();
        //$conn->beginTransaction();
        //$test = $adapter->query('SELECT * FROM `invite_inviter_award_log` WHERE award_type = 3 AND `status` = 2 AND sourceid NOT IN (SELECT sourceid FROM invite_inviter_award_log WHERE award_type = 3 AND `status` = 2 AND dateline < 1454471460 GROUP BY sourceid) AND dateline BETWEEN 1454471460 AND 1454471480 GROUP BY sourceid;',array())->count();
        //var_dump($test);exit;
        //查询首单记录
        //$order_data = $this->_getPlayOrderInfoTable()->get(array('order_sn' => '000085761'));
        //$first_data = $adapter->query('SELECT * from play_order_info WHERE `user_id` = ? AND `order_status` = 1 AND `pay_status` >= 2 ORDER BY dateline ASC LIMIT 1', array($order_data->user_id))->current();
        //var_dump($first_data->phone);
        $invite = new Invite();
        //$price = bcmul($order_data->coupon_unit_price, $order_data->buy_number, 2);
        $res = $invite->firstOrder(181922,13545283789,13545283789,'WH','898.80');
        var_dump($res);
        exit;
        //查询当前订单号是否为首单订单号
//        if($first_data->order_sn == $coupon_code_data->order_sn){
//            $invite = new Invite();
//            $price = bcmul($order_data->coupon_unit_price, $order_data->buy_number, 2);
//            $invite->firstOrder($order_data->user_id,$order_data->username,$order_data->phone,$order_data->order_city,$price);
//        }
        $invite = new Invite();
        $res = $invite->inviteRegister('187070','13525858965','13525858965','WH');var_dump($res);exit;
        $phone = $this->getQuery('phone');
        $uid = 70;
        $price = 300;
        $city = 'WH';
        $username = 'ceshi';
        $a=new Invite();
        $a->firstOrder($uid,$username,$phone,$city,$price);exit;
    }

    //todo 我的奖励
    public function awardAction(){
        if($this->debug === TRUE){
            $this->_domain = 'http://wft.com';
            $this->_uid = 39125;
            $this->_username = '嘛尼嘛尼哄';
            $this->_city = 'WH';
            $this->_adminConf = $this->_getInviteRule()->getByRuleId($this->_city);
            if($this->_city != 'WH'){
                //如果为分站，且没有规则，则调用武汉主站规则
                if(empty($this->_adminConf)){
                    $this->_adminConf = $this->_getInviteRule()->getByRuleId('WH');
                }
            }
        }else{
            //判断用户状态
           $uid = $this->getWapUid();

            if(!$uid){
                header('Location: http://wan.wanfantian.com/app/index.php');
                exit;
//                exit('未登陆啊！');
            }
            $this->_user = $this->_getPlayUserTable()->get(['uid' => $uid]);
            $this->_uid = $this->_user->uid;
            $this->_username = $this->_user->username;
            //获取站点，匹配设置
            $city = $this->_user->city;
            $this->_city = $city;
            $this->_domain = $this->_getConfig()['url'];
            //获取指定站点的后台配置
            $this->_adminConf = $this->_getInviteRule()->getByRuleId($this->_city);

            //如果为分站，且没有规则，则调用武汉主站规则
            if(empty($this->_adminConf)){
                $this->_adminConf = $this->_getInviteRule()->getByRuleId('WH');
            }

            if(empty($this->_adminConf)){
                exit('获取规则失败!');
            }
            @$this->_ruleId = $this->_adminConf->ruleid;//规则id
        }
        $page = (int)$this->getQuery('p', 1);
        $pagesum = 5;
        $start = ($page - 1) * $pagesum;
        //我的积分、现金卷总值
        $myTotalCredits = $this->_getInviteInviterAwardLog()->getTotalAward($this->_uid,0);

        $myTotalCashCoupon = $this->_getInviteInviterAwardLog()->getTotalAward($this->_uid,1);

        $myTotalCash = $this->_getInviteInviterAwardLog()->getTotalAward($this->_uid,3);



        //5条最新的邀约用户
        $list = $this->_getInviteMember()->getListByUid($start,$pagesum,$this->_uid,['*'])->toArray();
        $count = $this->_getInviteMember()->getListByUid(0, 0, $this->_uid, ['*'])->count();
        //创建分页
        $url = '/webinvite/index/award';
        $paginator = new Paginator($page, $count, $pagesum, $url);


        if(!empty($list) && isset($list)) {
//            $key2 = $this->_remindCachePrefix .'13545287893_remind_' . $this->_uid;
//            RedCache::set($key2,time(),60);//提醒时间是否到期
            //遍历查询该用户提醒状态
            foreach($list as &$data){
                $key = $this->_remindCachePrefix . $data['phone'] .'_remind_'. $this->_uid;//提醒时间是否到期
                $cache = RedCache::get($key);//提醒时间是否到期
                //var_dump(RedCache::ttl($key));
                if($cache === false){
                    $data['remind'] = 1;//可以提醒
                }else{
                    $data['remind'] = 0;//还未到期
                }
            }
            //var_dump($list);
//            return array(
//                'data' => $data,
//                'pagedata' => $paginator->getHtml(),
//            );
            $v = new ViewModel([
                'pagedata' => $paginator->getHtml(),
                'myTotalCredits' => $myTotalCredits,
                'myTotalCashCoupon' => $myTotalCashCoupon,
                'myTotalCash' => $myTotalCash,
                'list' => $list,
                'username' => $this->_username,
                'rule' => $this->_adminConf,
            ]);
            $v->setTerminal(true);
            return $v;
        }else{
            $v = new ViewModel;
            $v->setTemplate($this->_template. 'begin.phtml');
            return $v;
        }
    }

    //todo 发送短信提醒 redis记录时间间隔7天
    public function sendAction(){
        $id = $this->getPost('id');
        $name = $this->getPost('name');
        if(!$id || !$name){
            exit(json_encode(['code' => -2, 'msg' =>'参数缺失']));
        }
        //通过id查询用户phone
        $user = $this->_getInviteMember()->get(['id' => $id]);
        if(!$user->phone){
            exit(json_encode(['code' => -3, 'msg' =>'没有改用户']));
        }

        //获取指定站点的后台配置
        $this->_adminConf = $this->_getInviteRule()->getByMyRuleId($user->ruleid);

        //如果为分站，且没有规则，则调用武汉主站规则
        if(empty($this->_adminConf)){
            $this->_adminConf = $this->_getInviteRule()->getByRuleId('WH');
        }

        if(empty($this->_adminConf)){
            exit('获取规则失败!');
        }
        @$this->_ruleId = $this->_adminConf->ruleid;//规则id
        $phone = $user->phone;
//        $msg = 'Hi玩翻天小伙伴，我是'.$name.'别忘了我送你的'.$this->_adminConf->r_reciever_award.'元现金券哟。下载链接：<a href="'.$this->down_url.'">'.$this->down_url.'</a>';
        $msg = 'Hi玩翻天小伙伴，我是'.$name.'别忘了我送你的'.$this->_adminConf->r_reciever_award.'元现金券哟。下载链接： '.$this->down_url;
        $key = $this->_remindCachePrefix . $phone .'_remind_'. $user->sourceid;//提醒时间是否到期
        $cache = RedCache::get($key);//提醒时间是否到期
        if($cache === false){//限制时间到了
            //发短信
            SendMessage::Send($phone,$msg);
            //发送完后赋值时间
            $limitTime = $this->_adminConf->remind_per_day;//提醒间隔天数
            $tstd = date("Y-m-d", strtotime("+".$limitTime." day")); // 明天的日期d
            $remainTime = strtotime($tstd." 00:00:00")-strtotime("now");
            RedCache::set($key,time(),$remainTime);
            exit(json_encode(['code' => 1, 'msg' =>'发送提醒成功']));
        }else{
            //不能发
            exit(json_encode(['code' => -1, 'msg' =>'自邀请后每'.$this->_adminConf->remind_per_day.'天才能提醒一次噢~']));
        }
    }

}
