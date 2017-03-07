<?php

namespace WebInvite\Controller;

use Deyi\BaseController;
use Deyi\Coupon\Coupon;
use Deyi\Integral\Integral;
use Deyi\Invite\Invite;
use Deyi\JsonResponse;
use Deyi\WeiXinFun;
use Deyi\Paginator;
use library\Service\System\Cache\RedCache;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\EventManager\EventManagerInterface;
use Zend\View\Model\ViewModel;
use Deyi\SendMessage;

class IndexController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    //const ChancePerDay = 5;
    //const RemindLimitDay= 7;//
    private $appdown = 'http://wan.wanfantian.com/app/index.php';
    private $down_url = 'http://t.cn/RqsYpau';

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

        //判断用户状态
        $uid = $this->getWapUid();
        $city = $this->getCity();

        $domain = $this->_getConfig()['url'];

        if(!$uid or (int)$uid < 1){
            header('Location: '.$this->appdown);
            exit;
        }else{
            setcookie('uid',$uid,time()+3600*24*30,'/');
        }

        $user = $this->_getPlayUserTable()->get(['uid' => $uid]);
        if(!$user){
            header('Location: '.$this->appdown);
            exit;
        }

        //获取指定站点的后台配置(活取邀请人奖励规则)
        $ir = $this->getRuleByCity($city);

        //初始化用户邀约码token
        $inviteToken = $this->_getInviteToken()->getToken($ir->ruleid, $uid);//判断account_token是否有这个用户，有就取shareToken
        if (!$inviteToken) {//没就插入并生成inviteToken
            $inviteToken = $this->_getInviteToken()->setToken($ir->ruleid, $uid, $user->username,$user->img);
        }
        //邀请码
        $inviteUrl = $domain.'/webinvite/index/recieve?token='.$inviteToken.'&city='.$city;
        //邀请码图片
        //echo '<img src="/application/index/phpqrcode?code='.$inviteUrl.'"/>';

        if((int)$ir->r_inviter_type === 0){//邀约者因注册奖励类型(0：积分；1：现金卷)
            $inviter_tip = $ir->r_inviter_award.'积分';
        }else{
            $inviter_tip = $ir->r_inviter_award.'元现金券';
        }

        if((int)$ir->r_reciever_type === 0){//受邀人因注册奖励类型(0：积分；1：现金卷)
            $r_reciever_tip = $ir->r_reciever_award.'积分';
        }else{
            $r_reciever_tip = $ir->r_reciever_award.'元现金券';
        }

        $inviter_award_limit = $ir->f_inviter_award_limit;


        $this->layout()->setVariables([
            'token' => $inviteToken,
            'share_title'   => $ir->share_title,
            'share_img'   => $ir->share_img,
            'share_desc'   => $ir->share_desc,
            'city' => $city,
        ]);

        $v = new ViewModel([
            'token' => $inviteToken,
            'domain' => $domain,
            'rule' => $ir,
            'inviteUrl' => $inviteUrl,
            'p' => $_GET['p'],
            'inviter_tip' => $inviter_tip,
            'reciever_tip' => $r_reciever_tip,
            'inviter_award_limit' => $inviter_award_limit,

        ]);
        $v->setTerminal(true);
        return $v;

    }

    //todo 接收页面
    public function recieveAction()
    {
        unset($_GET['from']);
        $token = $this->getQuery('token');
        $city = $this->getQuery('city');
        if(empty($token) || !$token){
            exit('参数错误！');
        }

        $ctzh = $this->getAllCities()[$city];

        if(!$ctzh){
            header('Location: http://wan.wanfantian.com/app/index.php');
            exit;
        }

        //todo 通过token对应的ruleid去取规则
        $inviteData = $this->_getInviteToken()->getByToken($token);//判断account_token是否有这个用户，有就取shareToken

        if($inviteData){
            //找到该站点对应的规则
            $ir = $this->getRuleByCity($city);
            $allir = $this->getAllRules(@$inviteData->username);
            $desc = $ir->r_reciever_type?'元现金券':'积分';
            $info = '我是'.@$inviteData->username.',<br>送你玩翻天'.$ir->r_reciever_award.$desc.'，可兑换秒<br>杀资格和游玩门票哦！<br>快来领取吧！';

            $this->layout()->setVariables([
                'token' => $token,
                'share_title'   => $ir->share_title,
                'share_img'   => $ir->share_img,
                'share_desc'   => $ir->share_desc,
                'city' => $city
            ]);
            $v = new ViewModel([
                'inviteUid' => @$inviteData->uid,//邀请人uid
                'inviteUsername' => @$inviteData->username,//邀请人名字
                'inviteImg' => $inviteData->img?$this->getImgUrl($inviteData->img):'/images/invite/child.png',//邀请人头像
                'desc' => $desc,
                'ir' => $ir,
                'token' => $token,
                'city' => @$ir->city,
                'allir' => $allir,
                'info' => $info,
                'ctzh' => $ctzh,
            ]);
            $v->setTerminal(true);
            return $v;

        }else{
            //没找到改邀请码所对应的信息
            header('Location: http://wan.wanfantian.com/app/index.php');
            exit;
        }

    }


    //todo 绑定手机号 ajax
    public function bindAction()
    {
        $token = $this->getPost('token','');
        $phone = $this->getPost('phone','z');
        $city = $this->getPost('city','WH');
        $orcity = $this->getPost('orcity','WH');

        if(empty($token) || !$token){
            header('Location: http://wan.wanfantian.com/app/index.php');
            exit;
        }

        $b = preg_match_all("/^1[34578]\d{9}$/", $phone, $mobiles);

        if(strlen($phone)!== 11 or (int)$phone < 10 or !$b){
            exit(json_encode(['code' => -1, 'msg' =>'请输入正确手机号']));
        }

        $inviteData = $this->_getInviteToken()->getByToken($token);//判断token是否有这个用户，有就取Token
        if($inviteData){
            //查询该手机号用户是否为已注册用户
            $oldUser = $this->_getPlayUserTable()->get(['phone' => $phone]);
            if($oldUser){
                exit(json_encode(['code' => 0, 'msg' =>'您手机已经注册过，为老用户账号']));//您已经接受过
            }

            //注册
            $u_token = md5(md5('234323') . time());

            $status = $this->_getPlayUserTable()->insert(array(
                'username' => $phone,
                'token' => $u_token,
                'mark_info' => 0,
                'phone' => $phone,
                'login_type' => 'phone',
                'is_online' => 1,
                'device_type' => '',
                'dateline' => time(),
                'status' => 1,
                'password' => '',
                'city' => $city,
            ));

            $uid = $this->_getPlayUserTable()->getlastInsertValue();

            //获取指定站点的后台配置
            $ir = $this->getRuleByCity($city);
            $row = [
                'uid'=>$uid,
                'username'=>$phone,
                'phone' => $phone,
                'token' => $token,
                'sourceid' => $inviteData->uid,
                'ruleid' => $ir->ruleid,
                'status' => 1,
                'dateline' => time(),
                'register_time' => time(),
                'remind_time' => 0,
            ];

            //$oir = $this->getRuleByRid($inviteData->ruleid);
            $oir = $this->getRuleByCity($orcity);

            $data = 0;
            if($status){
                //邀请人的奖励
                if((int)$oir->r_inviter_type === 0){
                    $integral = new Integral();
                    $stat = $integral->doInvite($inviteData->uid,$uid,$oir->city);
                }else{
                    $cc = new Coupon();
                    $stat = $cc->getCashCouponByInverted($inviteData->uid,$uid,$oir->city);
                }

                //接受邀请的奖励
                if((int)$ir->r_reciever_type === 1){
                    $cc = new Coupon();
                    $cc->getCashCouponByReceive($uid,$inviteData->uid,$city);
                }else{
                    $integral = new Integral();
                    $integral->acceptInvite($uid,$inviteData->uid,$city);
                }

                //查询该手机用户是否已经邀约
                $exist = $this->_getInviteMember()->getByPhone($phone);
                //已经存在该用户
                if(!empty($exist) && isset($exist) && $stat){
                    $data = $this->_getInviteMember()->update(['status'=>1],['phone'=>$phone,'status'=>0]);
                }else{
                    if($stat){
                        $row['status'] = 1;
                    }else{
                        $row['status'] = 0;
                    }
                    //通过phone生成以手机号唯一的临时账号表(手机用户真正注册时，更新其log状态以及log表中的受邀人的uid)
                    $data = $this->_getInviteMember()->insert($row);
                }
            }
            //邀约成功
            if ($data > 0) {
                //app提醒
                $msg = '已有新伙伴领取您的邀请现金券，快提醒TA使用吧，TA使用后您将获得30积分、高达30元现金券。';
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
            header('Location: http://wan.wanfantian.com/app/index.php');
            exit;
        }
        if(empty($phone) || !$phone){
            header('Location: http://wan.wanfantian.com/app/index.php');
            exit;
        }
        //判断手机与该token的绑定关系以及当前状态
        $inviteData = $this->_getInviteMember()->get(['phone' => $phone, 'token' => $token]);//判断token是否有这个用户，有就取Token

        if($inviteData){
            $user = $this->_getPlayUserTable()->get(['phone'=>$phone]);

            //获取指定站点的后台配置
            $ir = $this->getRuleByRid($inviteData->ruleid);
            $inviteToken = $token;
            if(empty($ir)){
                header('Location: http://wan.wanfantian.com/app/index.php');
                exit;
            }

            if($this->is_weixin()){
                $agent = 'wx';
                //初始化用户邀约码token
                $inviteToken = $this->_getInviteToken()->getToken($inviteData->ruleid, $inviteData->uid);//判断account_token是否有这个用户，有就取shareToken
                if (!$inviteToken) {//没就插入并生成inviteToken
                    $inviteToken = $this->_getInviteToken()->setToken($inviteData->ruleid, $inviteData->uid, $user->username,$user->img);
                }

            }elseif($this->is_wft()){
                $agent = 'wft';
            }else{
                $agent = '';
            }
            $this->layout()->setVariables([
                'token' => $inviteToken,
                'share_title'   => $ir->share_title,
                'share_img'   => $ir->share_img,
                'share_desc'   => $ir->share_desc,
                'city' => $ir->city
            ]);

            if((int)$ir->r_inviter_type === 0){//邀约者因注册奖励类型(0：积分；1：现金卷)
                $inviter_tip = $ir->r_inviter_award.'积分';
            }else{
                $inviter_tip = $ir->r_inviter_award.'元现金券';
            }

            if((int)$ir->r_reciever_type === 0){//受邀人因注册奖励类型(0：积分；1：现金卷)
                $r_reciever_tip = $ir->r_reciever_award.'积分';
            }else{
                $r_reciever_tip = $ir->r_reciever_award.'元现金券';
            }

            $inviter_award_limit = $ir->f_inviter_award_limit;

            $v = new ViewModel([
                'inviteUid' => @$user->uid,//受邀uid
                'inviteUsername' => @$user->username,//受邀人名字
                'inviteImg' => $user->img?$this->getImgUrl($user->img):'/images/invite/child.png',//邀请人头像
                'desc' => $ir->r_reciever_type?'元现金券':'积分',
                'phone' => $phone,
                'ir' => $ir,
                'agent' => $agent,
                'appdown' => $this->appdown,
                'inviter_tip' => $inviter_tip,
                'reciever_tip' => $r_reciever_tip,
                'inviter_award_limit' => $inviter_award_limit,
            ]);
            $v->setTerminal(true);
            return $v;

        }else{
            //未发现该对应phone/token的绑定记录
            header('Location: /webinvite/index/recieve?token='.$token.'&city=WH');
            exit;
        }

    }

    //todo 我的奖励
    public function awardAction(){
        //判断用户状态
       $uid = $this->getWapUid();

        if(!$uid){
            header('Location: http://wan.wanfantian.com/app/index.php');
            exit;
        }
        $user = $this->_getPlayUserTable()->get(['uid' => $uid]);
        $uid = $user->uid;
        $username = $user->username;
        //获取站点，匹配设置
        $city = $this->getCity();
        $this->_domain = $this->_getConfig()['url'];
        //获取指定站点的后台配置
        $ir = $this->getRuleByCity($city);

        if(empty($ir)){
            header('Location: http://wan.wanfantian.com/app/index.php');
            exit;
        }

        $page = (int)$this->getPost('page', 1);

        $pagesum = 10;
        $start = ($page - 1) * $pagesum;
        //我的积分、现金卷总值

        $adapter = $this->_getAdapter();
        $sumint = $adapter->query("SELECT SUM(total_score) as sumint FROM play_integral WHERE `uid`=? and `type` = ? ",
            array($uid, 6))->current();
        $summoney = $adapter->query("SELECT SUM(flow_money) as summoney FROM play_account_log WHERE `uid`=? and `action_type_id` = ? ",
            array($uid, 15))->current();
        $sumprice = $adapter->query("SELECT SUM(price) as sumprice FROM play_cashcoupon_user_link WHERE `uid`=? and `get_type` = ? ",
            array($uid, 13))->current();


        //5条最新的邀约用户
        $list = $this->_getInviteMember()->getListByUid($start,$pagesum,$uid,['status','id','ruleid'])->toArray();
        $count = $this->_getInviteMember()->getListByUid(0, 0, $uid, [])->count();
        //创建分页
        $url = '/webinvite/index/award';
        $paginator = new Paginator($page, $count, $pagesum, $url);

        $allr = $this->getRulesId();

        if(!empty($list) && isset($list)) {
            foreach($list as $data){
                $key = $data['phone'] .'_'. $uid;//提醒时间是否到期
                $cache = RedCache::get($key);//提醒时间是否到期

                if($cache === false){
                    $data['remind'] = 1;//可以提醒
                }else{
                    $data['remind'] = 0;//还未到期
                }
            }

            if($page > 1){
                foreach ($list as $k => $l) {
                    $l['img'] = $l['img'] ?: '/images/invite/child.png';
                    $l['phone'] = substr_replace($l['phone'], '****', 3, 4);
                    if ($l['status'] == 0) {
                        $str = '待使用玩翻天';
                    } elseif ($l['status'] == 1) {
                        $str = '已注册，首单未开启';
                    } else {
                        $str = '推荐成功';
                    }
                    $l['str'] = $str;
                    $l['desc'] = $allr[$l['ruleid']]['v'] . $allr[$l['ruleid']]['t'];
                    if($l['status']!=2){
                        $l['tips'] = '提醒他';
                    }else{
                        $l['tips'] = '';
                    }

                    if ($l['status'] == 2) {
                        $l['clss'] = 'prise-list-suc';
                    } else {
                        $l['clss'] = 'prise-list-tips';
                    }

                    $list[$k] = $l;
                }
                echo json_encode($list);
                exit;

            }

            $v = new ViewModel([
                'pagedata' => $paginator->getHtml(),
                'sumint' => $sumint->sumint,
                'summoney' => $summoney->summoney,
                'sumprice' => $sumprice->sumprice,
                'list' => $list,
                'username' => $username,
                'rule' => $ir,
                'p' => $_GET['p'],
                'allr' => $allr,
            ]);
            $v->setTerminal(true);
            return $v;
        }elseif($page > 1){
            echo json_encode([]);
            exit;
        }else{
            $v = new ViewModel;
            $v->setTemplate('/web-invite/index/begin.phtml');
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
        $ir = $this->getRuleByRid($user->ruleid);

        if($ir->r_reciever_type==1){
            $ci = '元现金券';
        }else{
            $ci = '积分';
        }

        @$this->_ruleId = $ir->ruleid;//规则id
        $phone = $user->phone;
        //$phone = 18696158913;
//      $msg = 'Hi玩翻天小伙伴，我是'.$name.'别忘了我送你的'.$this->_adminConf->r_reciever_award.'元现金券哟。下载链接：<a href="'.$this->down_url.'">'.$this->down_url.'</a>';
        $msg = 'Hi玩翻天小伙伴，我是'.$name.'别忘了我送你的'.$ir->r_reciever_award.$ci.'哟。下载链接： '.$this->down_url;

        $cache = $user->remind_time + 3600*24*$ir->remind_per_day > time();
        if($cache === false){//限制时间到了
            //发短信
            SendMessage::Send($phone,$msg);
            //发送完后赋值时间
            $this->_getInviteMember()->update(['remind_time'=>time()],['uid'=>$user->uid]);
            exit(json_encode(['code' => 1, 'msg' =>'发送提醒成功']));
        }else{
            //不能发
            exit(json_encode(['code' => -1, 'msg' =>'<span>你已经提醒过啦！</span> <br/>自邀请后每'.$ir->remind_per_day.'天才能提醒<br/>好友一次哦～']));
        }
    }

    /**
     * 获取指定站点的后台配置(获取邀请人奖励规则)
     * @param $city
     * @return array|\ArrayObject|bool|int|mixed|null|string
     */
    private function getRuleByCity($city){
        $invite = new Invite();
        $ir = $invite->getInviteInfo($city);

        if(!$ir){
            header('Location: '.$this->appdown);
            exit;
        }
        return $ir;
    }

    /**
     * 获取指定ID的后台配置
     * @param $ruleid
     * @return array|\ArrayObject|bool|int|mixed|null|string
     */
    private function getRuleByRid($ruleid){
        RedCache::del('I:invite:' . $ruleid);
        $ir = RedCache::fromCacheData('I:invite:' . $ruleid, function () use ($ruleid) {
            return $this->_getInviteRule()->getByMyRuleId($ruleid);
        }, 3600*12, true);

        if(!$ir){
            $ir = $this->_getInviteRule()->getByRuleId('WH');
        }

        if(!$ir){
            header('Location: '.$this->appdown);
            exit;
        }
        return (object)$ir;
    }

    private function getAllRules($username){
        RedCache::del('I:invite:ALL');
        $ir = RedCache::fromCacheData('I:invite:ALL', function () {
            return $this->_getInviteRule()->fetchLimit()->toArray();
        }, 3600*12, true);

        if(!$ir){
            header('Location: '.$this->appdown);
            exit;
        }
        $prize = [];
        foreach ($ir as $i) {
            $desc = [];
            if((int)$i['r_reciever_type'] === 1){
                $desc['v'] = $i['r_reciever_award'];
                $desc['t'] = '元现金券';
                $desc['info'] = '我是'.@$username.',<br>送你玩翻天'.$desc['v'].$desc['t'].'，可兑换秒<br>杀资格和游玩门票哦！<br>快来领取吧！';

            }else{
                $desc['v'] = $i['r_reciever_award'];
                $desc['t'] = '积分';
                $desc['info'] = '我是'.@$username.',<br>送你玩翻天'.$desc['v'].$desc['t'].'，可兑换秒<br>杀资格和游玩门票哦！<br>快来领取吧！';
            }
            $prize[$i['city']] = $desc;
        }

        return $prize;
    }

    private function getRulesId(){
        RedCache::del('I:invite:ALL');
        $ir = RedCache::fromCacheData('I:invite:ALL', function () {
            return $this->_getInviteRule()->fetchLimit()->toArray();
        }, 3600*12, true);

        if(!$ir){
            header('Location: '.$this->appdown);
            exit;
        }
        $prize = [];
        foreach ($ir as $i) {
            $desc = [];
            if((int)$i['r_reciever_type'] === 1){
                $desc['v'] = $i['r_reciever_award'];
                $desc['t'] = '元现金券';
            }else{
                $desc['v'] = $i['r_reciever_award'];
                $desc['t'] = '积分';
            }
            $prize[$i['ruleid']] = $desc;
        }

        return $prize;
    }
}
