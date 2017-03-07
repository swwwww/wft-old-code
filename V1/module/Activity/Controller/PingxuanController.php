<?php

namespace Activity\Controller;

use Deyi\BaseController;
use Deyi\Integral\Integral;
use Deyi\JsonResponse;
use Deyi\Mcrypt;
use Deyi\Paginator;
use Deyi\WriteLog;
use library\Service\System\Cache\RedCache;
use Zend\Db\Sql\Expression;
use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Deyi\WeiXinFun;
use Deyi\Coupon\Coupon;
use Zend\EventManager\EventManagerInterface;

class PingxuanController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;


    private $types = array(
        '儿童乐园',
        '主题乐园',
        '儿童游泳',
        '周边景点',
        '儿童主题'
    );

    private $vote_number = 10;

    private $domain;

    public function setEventManager(EventManagerInterface $events)
    {
        parent::setEventManager($events);
        $controller = $this;
        $events->attach('dispatch', function ($e) use ($controller) {

            $this->domain = $this->_getConfig()['url'];

            $db = $this->_getAdapter();
            $setting = $db->query("select * from activity_pingxuan_setting", array())->current();


            $weixin = new WeiXinFun($this->getwxConfig());


            $share_arr = array(
                array('title' => '你最爱带娃去哪遛？投票可参与抽奖！', 'desc' => '武汉十佳遛娃地评选，投票你最爱的遛娃地！'),
                array('title' => '我的遛娃我作主！投票你最爱的遛娃地！', 'desc' => '让武汉妈妈遛娃不再入坑！投票可参与抽奖哟！'),
                array('title' => '快乐遛娃不入坑！投票你最爱的遛娃地！', 'desc' => '最佳遛娃地值得分享！投票可参与抽奖哟！'),
                array('title' => '武汉遛娃哪家强？票选最靠谱遛娃地！', 'desc' => '广告宣传通稿都是什么鬼，口碑才是硬实力 '),
            );

            $share_data = $share_arr[mt_rand(0, (count($share_arr) - 1))];

            $controller->layout()->setVariables([
                'set' => $setting,
                'jsApi' => $weixin->getsignature(),
                'share_title' => $share_data['title'],
                'share_url' => $this->_getConfig()['url'] . '/activity/pingxuan',
                'share_img' => $this->_getConfig()['url'] . '/activity/px/images/share.png',
                'share_desc' => $share_data['desc'],
            ]);
        }, 100);
    }

    public function testAction(){

        exit();

    }
    //全部商家
    public function indexAction()
    {

       
        /*儿童乐园
主题乐园
儿童游泳
周边景点
        */
        $page = (int)$this->getQuery('page', 1);
        $pageSum = (int)$this->getQuery('page_num', 10);
        $type = $this->getQuery('type', '儿童乐园');
        $start = ($page - 1) * $pageSum;
        $adapter = $this->_getAdapter();

        $like=$this->getQuery('like'); //搜素商家名称
        if($_GET['unionid'] or $_GET['p']){
            $uid=$this->getUnionid();
        }

        if (!in_array($type, $this->types)) {
           $type='儿童乐园';
        }

        $adapter->query("update  activity_pingxuan_setting set view_number=view_number+1", array(0));

        if($like){
            $data = $adapter->query("SELECT * FROM activity_pingxuan_business WHERE `name` LIKE ? or id=? ORDER BY sort DESC LIMIT {$start}, {$pageSum}", array("%{$like}%",$like));
            $count =$adapter->query("SELECT * FROM activity_pingxuan_business WHERE `name` LIKE ? or id=? ORDER BY id DESC LIMIT {$start}, {$pageSum}", array("%{$like}%",$like))->count();
        }else{
            $data = $adapter->query("SELECT * FROM activity_pingxuan_business WHERE `type`=? ORDER BY sort DESC LIMIT {$start}, {$pageSum}", array($type));
            $count = $adapter->query("SELECT * FROM activity_pingxuan_business WHERE `type`=?", array($type))->count();
        }



        //创建分页
        $url = '/activity/pingxuan/index';
        $paging = new Paginator($page, $count, $pageSum, $url);


        $vm = new  ViewModel(array(
            'page' => $paging->getHtml(2),
            'data' => $data
        ));
        $this->layout("activity/pingxuan/layout");
        return $vm;
    }

    //商家详情
    public function businfoAction()
    {

        $id = (int)$this->getQuery('id');

        $this->getBusinesstable()->update(array('view_number' => new Expression('view_number+1')), array('id' => $id));

        $data = $this->getBusinesstable()->select(array('id' => $id))->current();

        $seq = $this->getBusinesstable()->select("votes<='{$data->votes}'")->count();
        $vm = new  ViewModel(array(
            'data' => $data,
            'seq' => $seq,
            'yingxiang' => $this->getyingxiang($data->impression)
        ));
        $this->layout("activity/pingxuan/layout");
        return $vm;
    }

    //商家投票排行
    public function bustopAction()
    {

        $page = (int)$this->getQuery('page', 1);
        $pageSum = (int)$this->getQuery('page_num', 20);
        $type = $this->getQuery('type', '儿童乐园');
        $start = ($page - 1) * $pageSum;
        $adapter = $this->_getAdapter();


        if (!in_array($type, $this->types)) {
            $type='儿童乐园';
        }

        $data = $adapter->query("SELECT * FROM activity_pingxuan_business WHERE `type`=? ORDER BY votes DESC LIMIT {$start}, {$pageSum}", array($type));

        $count = $adapter->query("SELECT * FROM activity_pingxuan_business WHERE `type`=?", array($type))->count();

        //创建分页
        $url = '/activity/pingxuan/index';
        $paging = new Paginator($page, $count, $pageSum, $url);


        $vm = new  ViewModel(array(
            'page' => $paging->getHtml(2),
            'data' => $data
        ));
        $this->layout("activity/pingxuan/layout");
        return $vm;
    }

    //获取今日开始时间
    public function getStime()
    {
        return strtotime(date('Y-m-d 00:00:00'));
    }

    //清空用户昨日机会数
    public function initChance($unionid)
    {
        $user_data = $this->getUsertable()->select(array('unionid' => $unionid))->current();
        if ($user_data->chance_date < $this->getStime()) { //机会属于昨天
            $this->getUsertable()->update(array('chance_number' => 0,'chance_date'=>time()), array('unionid' => $unionid));
        }
    }

    //判断浏览器是否微信内置
    public function is_weixin()
    {
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
            return 1;
        }
        return 0;
    }

    //投票
    public function toupiaoAction()
    {
        return $this->jsonResponsePage(array('status' => 0, 'message' => '评选活动已结束'));

        if (!$_COOKIE['a_unionid']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请在微信或者app中参加活动'));
        }
        $unionid = $this->getUnionid();
        $bid = (int)$this->getPost('bid');
        $adapter = $this->_getAdapter();
        $adapter->query("update  activity_pingxuan_setting set vote_number=vote_number+1", array(0));
        $this->initChance($unionid);
        $s_time = $this->getStime();
        $count = $this->getVoteLogtable()->select(array('unionid' => $unionid, 'is_weixin' => $this->is_weixin(), "dateline>{$s_time}"))->count();


        if ($count >= $this->vote_number) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '今日投票次数已用完'));
        }

        $res = $this->getVoteLogtable()->insert(array(
            'bid' => $bid,
            'dateline' => time(),
            'unionid' => $unionid,
            'is_weixin' => (int)$this->is_weixin()
        ));

        $this->getBusinesstable()->update(array('votes' => new Expression('votes+1')), array('id' => $bid));


        $this->getUsertable()->update(array('chance_number' => new Expression('chance_number+1')), array('unionid' => $unionid));

        if ($res) {
            return $this->jsonResponsePage(array('status' => 1, 'message' => '投票成功'));
        }

        return $this->jsonResponsePage(array('status' => 0, 'message' => '今日投票次数已用完'));
    }

    //我的奖品
    public function myprizeAction()
    {
        $unionid = $this->getUnionid();


        $db = $this->_getAdapter();
        //中奖纪录
        $res = $db->query("select *,activity_pingxuan_prize.id as log_id from activity_pingxuan_prize LEFT JOIN  activity_pingxuan_prize_data ON  activity_pingxuan_prize_data.id=activity_pingxuan_prize.prize_id WHERE  activity_pingxuan_prize.status=1 AND unionid=?  ORDER BY  activity_pingxuan_prize.status DESC ", array($unionid));
        //用户数据
        $userdata = $db->query("select * from activity_pingxuan_user WHERE  unionid=?", array($unionid))->current();
        //投票数据
        $votedata = $db->query("select * from activity_pingxuan_vote_log LEFT JOIN activity_pingxuan_business ON activity_pingxuan_business.id=activity_pingxuan_vote_log.bid WHERE  unionid=? limit 10", array($unionid));


        $vm = new  ViewModel(array(
            'prizedata' => $res,
            'userdata' => $userdata,
            'votedata' => $votedata
        ));
        $this->layout("activity/pingxuan/layout");
        return $vm;

    }

    //通过unionid 获取phone
    public function getphone($unionid)
    {
        //微信用户 根据unionid 查询绑定了手机号的用户
        $db = $this->_getAdapter();


        $user=$this->getUsertable()->select(array('unionid'=>$unionid))->current();

        if($user->phone){
            return $user->phone;
        }
        $user_data = $db->query("select * from play_user_weixin  LEFT  JOIN play_user ON  play_user.uid=play_user_weixin.uid  WHERE  play_user_weixin.unionid=? AND play_user.phone!='' limit 1", array($unionid))->current();

        if ($user_data) {
            return $user_data->phone;
        } else {
            return false;
        }
    }

    //领取奖品
    public function getprizeAction()
    {

        if(!$_COOKIE['a_unionid']){
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请在微信或者app中参加活动'));
        }


        $unionid = $this->getUnionid();
        $prize_id = $this->getPost('id');
        $db = $this->_getAdapter();


        $prize_data = $db->query("select * from  activity_pingxuan_prize LEFT JOIN activity_pingxuan_prize_data ON  activity_pingxuan_prize_data.id=activity_pingxuan_prize.prize_id  WHERE activity_pingxuan_prize.id=? AND activity_pingxuan_prize.unionid=?", array($prize_id, $unionid))->current();
        $user_data = $db->query("select * from play_user_weixin  LEFT  JOIN play_user ON  play_user.uid=play_user_weixin.uid  WHERE  play_user_weixin.unionid=? AND play_user.phone!='' limit 1", array($unionid))->current();

        if ($user_data) {
            $uid = $user_data->uid;
        } else {
            $huodong_u=$this->getUsertable()->select(array('unionid'=>$unionid))->current();

            $user_data=  $this->_getPlayUserTable()->get(array('phone'=>$huodong_u->phone));
            if(!$user_data->phone){
                return $this->jsonResponsePage(array('status' => 0, 'message' => '请绑定手机号'));
            }else{
                $uid=$user_data->uid;
            }
        }


        if ($prize_data->cash_id) {
            //现金券奖品
            $res = $db->query("update activity_pingxuan_prize set prize_status=1 WHERE id=? AND unionid=?", array($prize_id, $unionid))->count();


            if(!$res){
                return $this->jsonResponsePage(array('status' => 0, 'message' => '领取失败'));
            }
            //$unionid  可能为 uid 取出uid
            if (is_int($unionid)) {
                $uid = $unionid;
            }


            //发放
            $cash_id = $prize_data->cash_id;  //  现金券奖品id
            $cc = new Coupon();
            $status = $cc->addCashcoupon($uid, $cash_id, 0, 4, 0, '商家评选活动');
            return $this->jsonResponsePage(array('status' => 1, 'message' => '领取成功'));

        } elseif($prize_data->integral) {

            //现金券奖品
            $res = $db->query("update activity_pingxuan_prize set prize_status=1 WHERE id=? AND unionid=?", array($prize_id, $unionid))->count();
            if(!$res){
                return $this->jsonResponsePage(array('status' => 0, 'message' => '领取失败'));
            }
            //$unionid  可能为 uid 取出uid
            if (is_int($unionid)) {
                $uid = $unionid;
            }


            //发放
            $ii=new Integral();
            $ii->addIntegral($uid,$prize_data->integral,1,24,0,'WH',0,'商家评选活动');
            return $this->jsonResponsePage(array('status' => 1, 'message' => '领取成功'));
        }else{
            //普通奖品
            $res = $db->query("update activity_pingxuan_prize set prize_status=1 WHERE id=? AND unionid=?", array($prize_id, $unionid))->count();
            if ($res) {
                return $this->jsonResponsePage(array('status' => 1, 'message' => '领取成功'));
            }
        }


        return $this->jsonResponsePage(array('status' => 0, 'message' => '领取失败'));
    }


    public function prizedrawAction()
    {

        $unionid = $this->getUnionid();

        $userdata = $this->getUsertable()->select(array('unionid' => $unionid))->current();


        $vm = new  ViewModel(array(
            'userdata' => $userdata,
            'phone'=>$this->getphone($unionid)
        ));

        $this->layout("activity/pingxuan/layout");
        return $vm;

    }

    //更新手机号
    public function updatephoneAction(){
        $unionid = $this->getUnionid();

        $phone=$this->getPost('phone');
        $res = $this->getUsertable()->update(array('phone'=>$phone),array('unionid'=>$unionid));
        if($res){
            return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));
        }else{
            return $this->jsonResponsePage(array('status' => 0, 'message' => '失败'));
        }
    }

    //获取unionid 或者跳转并验证
    public function getUnionid($back_url)
    {
        if ($this->is_weixin()) {
            return $this->initweixinuser($back_url);
        } else {
            return $this->initappuser();
        }
    }

    //返回unionid
    public function initweixinuser($back_url)
    {


        $unionid = $this->getQuery('unionid');
        $openid = $this->getQuery('openid');


        if ($_COOKIE['a_unionid']) {
            $user = $this->getUsertable()->select(array('unionid' => $_COOKIE['a_unionid']))->current();
            if ($user) {
                return $_COOKIE['a_unionid'];
            } else {
                exit('用户不存在');
            }
        }
        if ($unionid) {//通过回调
            $user = $this->getUsertable()->select(array('unionid' => $unionid))->current();
            //判断用户是否已存在
            if (!$user) {
                // 生成用户
                $s = $this->getUsertable()->insert(array(
                    'unionid' => $unionid,
                    'open_id' => $openid,
                    'name' => urldecode($_GET['nickname']),
                    'dateline' => time(),
                    'chance_number' => 0,
                    'img' => urldecode($_GET['img']),
                    'chance_date' => time()
                ));
            } else {
                $s = true;
            }
            // 生成cookie
            setcookie('a_unionid', $unionid, time() + 2600 * 24 * 100, '/');

            if ($s) {
                return $unionid;
            } else {
                exit('生成用户失败');
            }

        } else {
            if (!$back_url) {
                $back_url = $_SERVER['REQUEST_URI'];//  /activity/pingxuan/prizedraw
            }
            //跳转授权
            $url = 'http://wan.wanfantian.com/web/wappay/shareinfo?url=' . urlencode($this->domain . $back_url . '?a=1');//接口地址
            header('Location:' . $url);
            exit;
        }

    }

    //返回unionid
    public function initappuser()
    {
        if (!$this->is_wft()) {
            header('Location:' . $this->domain . "/activity/pingxuan");
        }


        if ($_COOKIE['a_unionid']) {
            $user = $this->getUsertable()->select(array('unionid' => $_COOKIE['a_unionid']))->current();
            if ($user) {
                return $_COOKIE['a_unionid'];
            } else {
                exit('用户不存在');
            }
        }

        $uid = $this->getWapUid();
        $user = $this->_getPlayUserTable()->get(array('uid' => $uid));

        if (!$user) {
            exit("<h1>请先登录APP</h1>");
        }
        $weixin_data = $this->_getPlayUserWeiXinTable()->fetchAll(array('uid' => $user->uid, "unionid!=''"))->current();

        if ($weixin_data) {
            $unionid = $weixin_data->unionid;
        } else {
            $unionid = $uid;   //如果有问题 直接修改奖品列表 和对应领奖操作
        }

        $a_user = $this->getUsertable()->select(array('unionid' => $unionid))->current();
        if (!$a_user) {
            // 生成用户
            $s = $this->getUsertable()->insert(array(
                'unionid' => $unionid,
                'open_id' => $unionid,
                'name' => $user->username,
                'dateline' => time(),
                'chance_number' => 0,
                'img' => $user->img,
                'chance_date' => time()
            ));

        }


        // 生成cookie
        setcookie('a_unionid', $unionid, time() + 2600 * 24 * 100, '/');

        $_COOKIE['a_unionid']=$unionid;
        return $unionid;


    }

    //印象投票
    public function yingxiangvoteAction()
    {
        return $this->jsonResponsePage(array('status' => 0, 'message' => '评选活动已结束'));
        $unionid = $this->getUnionid();

        $bid = (int)$this->getPost('bid');
        $y_name = $this->getPost('y_name');  //印象名称


        $data = $this->getBusinesstable()->select(array('id' => $bid))->current();

        $y_data = $this->getyingxiang($data->impression);


        if (!$y_data[$y_name]) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '数据错误'));
        }
        $y_data[$y_name] = $y_data[$y_name] + 1;

        //组合数据

        $this->getBusinesstable()->update(array('impression' => $this->zuhe($y_data)), array('id' => $bid));

        return $this->jsonResponsePage(array('status' => 1, 'message' => '添加印象成功！'));


    }


    //暂待使用
    public function linkAction()
    {
        $vm = new  ViewModel(array());
        $vm->setTerminal(true);
        return $vm;

    }

    //抽奖操作
    public function takePrizeAction()
    {
        return $this->jsonResponsePage(array('status' => 0, 'message' => '评选活动已结束'));
        // 获取用户的 unionid 或者 id
        //  设置无奖id 3;

        $unionid = $this->getUnionid();
        $no_prize_id = 1;

        $adapter = $this->_getAdapter();

        $userInfo = $adapter->query("SELECT activity_pingxuan_user.* FROM activity_pingxuan_user WHERE activity_pingxuan_user.unionid = ?", array($unionid))->current();

        if (!$userInfo) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请参加活动'));
        }

        if (($userInfo->chance_number) < 1) {
            return $this->jsonResponsePage(array('status' => 2, 'message' => '快投票分享获取机会吧'));
        }

        //用户中奖记录
        $user_prize_log = $adapter->query("SELECT activity_pingxuan_prize.prize_id FROM activity_pingxuan_prize WHERE activity_pingxuan_prize.unionid = ? AND status = ?", array($userInfo->unionid, 1));
        //所有奖品
        $prize_log = $adapter->query("SELECT activity_pingxuan_prize_data.* FROM activity_pingxuan_prize_data WHERE limit_num > get_num AND status = ? AND probability > ?", array(1, 0));


        /*
         * 1、每份奖每人只能领一次，但是可以领不同的奖，
        */
        $vp = 0;

        $have = array();
        $dam = array();
        foreach ($user_prize_log as $log) {
            $have[] = $log['prize_id'];
        }

        foreach ($prize_log as $prize) {
            if (!in_array($prize['id'], $have)) {
                $dam[$prize['id']] = array(
                    'id' => $prize['id'],
                    'prize_name' => $prize['name'],
                    'vp' => $prize['probability'],
                );
            } else {
                $vp = $vp + $prize['probability'];
            }

        }

        if (isset($dam[$no_prize_id])) {
            $dam[$no_prize_id] = array(
                'id' => $no_prize_id,
                'prize_name' => $dam[$no_prize_id]['prize_name'],
                'vp' => $dam[$no_prize_id]['vp'] + $vp,
            );
        }

        if (!count($dam)) { //奖品没有了 减少次数
            $adapter->query("UPDATE activity_pingxuan_user SET chance_number=chance_number-1 WHERE unionid = ? AND chance_number > ?", array($userInfo->unionid, 0))->count();
            return $this->jsonResponsePage(array('status' => 1, 'message' => '请换个姿势试试'));
        }

        $valuePrize = $this->_getUserPrizeGood($dam);

        // 用户 activity_pingxuan_user chance_number 减少 1    ||  奖品 activity_pingxuan_prize_data  get_num 增加  1 || 记录用户中将记录 activity_pingxuan_prize

        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();

        $s1 = $adapter->query("UPDATE activity_pingxuan_user SET chance_number=chance_number-1 WHERE unionid = ? AND chance_number > ?", array($userInfo->unionid, 0))->count();

        if (!$s1) {
            $conn->rollback();
            return $this->jsonResponsePage(array('status' => 0, 'message' => '抽奖失败'));
        }

        $status = ($valuePrize['pid'] == $no_prize_id) ? 0 : 1;

        $s2 = $adapter->query("INSERT INTO activity_pingxuan_prize (id, unionid, prize_id, prize_status, dateline, status) VALUES (NULL ,?, ?, ?, ?, ?)", array($userInfo->unionid, $valuePrize['pid'], 0, time(), $status))->count();

        if (!$s2) {
            $conn->rollback();
            return $this->jsonResponsePage(array('status' => 0, 'message' => '抽奖失败了'));
        }

        if ($valuePrize['pid'] == $no_prize_id) { // 没获得奖品 的 记录
            $conn->commit();
            return $this->jsonResponsePage(array('status' => 1, 'message' => $valuePrize['name']));
        }

        $s3 = $adapter->query("UPDATE activity_pingxuan_prize_data SET get_num=get_num + 1 WHERE limit_num >= get_num AND id = ?", array($valuePrize['pid']))->count();
        if (!$s3) {
            $conn->rollback();
            return $this->jsonResponsePage(array('status' => 0, 'message' => '抽奖失败 了'));
        }

        $conn->commit();
        return $this->jsonResponsePage(array('status' => 1, 'message' => $valuePrize['name']));

    }

    //获取用户 获得 奖品
    private function _getUserPrizeGood($dam)
    {

        $good = array();
        $arr = array();

        $prizeData = $dam;
        foreach ($prizeData as $ze) {
            $good[$ze['id']] = $ze['prize_name'];
        }

        foreach ($dam as $val) {
            $arr[$val['id']] = $val['vp'];
        }

        $rid = $this->_get_rand($arr);
        $prize = array(
            'pid' => $rid,
            'name' => $good[$rid],
        );

        return $prize;

    }

    //概率
    private function _get_rand($proArr)
    {
        $result = '';

        //概率数组的总概率精度
        $proSum = array_sum($proArr);

        //概率数组循环
        foreach ($proArr as $key => $proCur) {
            $randNum = mt_rand(1, $proSum);
            if ($randNum <= $proCur) {
                $result = $key;
                break;
            } else {
                $proSum -= $proCur;
            }
        }
        unset ($proArr);

        return $result;
    }


    /********* 数据表 *********/
    //商家表
    public function getBusinesstable()
    {

        $Adapter = $this->_getAdapter();
        return new TableGateway('activity_pingxuan_business', $Adapter);
    }

    //用户表
    public function getUsertable()
    {
        $Adapter = $this->_getAdapter();
        return new TableGateway('activity_pingxuan_user', $Adapter);
    }

    //投票记录表
    public function getVoteLogtable()
    {
        $Adapter = $this->_getAdapter();
        return new TableGateway('activity_pingxuan_vote_log', $Adapter);
    }

    //获取印象和对应的数值
    public function getyingxiang($string)
    {
        $d = array();
        $a = explode("\n", $string);
        foreach ($a as $v) {
            $b = explode(':', $v);
            $d[$b[0]] = $b[1];
        }
        return $d;
    }

    //组合印象
    public function zuhe($array)
    {
        $string = '';
        foreach ($array as $k => $v) {
            $string .= "{$k}:{$v}\n";
        }

        return substr($string, 0, -1);
    }

}
