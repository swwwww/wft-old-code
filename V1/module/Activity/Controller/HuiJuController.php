<?php

namespace Activity\Controller;

use Deyi\BaseController;
use Deyi\JsonResponse;
use Deyi\Mcrypt;
use Deyi\WriteLog;
use library\Service\System\Cache\RedCache;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Deyi\WeiXinFun;
use Deyi\Coupon\Coupon;
use Zend\EventManager\EventManagerInterface;

class HuiJuController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;


    private $noteData = array(
        '人不疯玩枉童年',
        '荟聚一周年，送礼好疯狂！',
        '小手一点，助你10分',
        '哇！每50分可抽奖一次呢！',
    );

    //检查是否结束
    public function checkEnd(){
        if(time()>strtotime('20160502')){
            return $this->jsonResponsePage(array('status' => 0, 'message' => '活动已截止'));
        }
    }
    //荟聚活动
    public function indexAction() {

        $flag = 0;
        $tip = $this->_getUserInfo();
        $login = 1;

        if ($tip) {
            if ($tip['huiju_id']) {
                $url = $this->_getConfig()['url'] . '/activity/huiju/info?id='.$tip['huiju_id'];
                header('Location: ' . $url);
                exit;
            }

            $flag = 1;
        }

        if (strpos($_SERVER['HTTP_USER_AGENT'], 'wft') !== false) {//app进来
            $flag = 1;
            if (!$tip) {
                $r = $this->_getWftUid();
                $login = $r ? 1 : 0;
            }
        }

        if (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'micromessenger') !== false) {//微信

            $flag = 1;
            if (!$tip) {
                $url = $this->_getConfig()['url'] . "/activity/huiju/index";
                $weixin = new WeiXinFun($this->getwxConfig());
                if ($this->userInit($weixin)) {
                    $this->_getWeiUserInfo();
                } else {
                    $toUrl = $weixin->getAuthorUrl($url, 'snsapi_userinfo');
                    header("Location: $toUrl");
                    exit;
                }
            }
        }

        $click_number = $this->countClick(2);

        $vm = new ViewModel(array(
            'login' => $login,
            'flag' => $flag,
            'prizeData' => $this->_getBrandData(),
            'click_number' => $click_number,
            'jsApi' => $this->_getWeiShare()['jsApi'],
            'share' => $this->_getWeiShare()['share'],
            'toUrl' => $this->_getConfig()['url'] . '/activity/huiju/index',
        ));

        $vm->setTerminal(true);

        return $vm;
    }

    //
    public function joinAction() {

        $userData = $this->_getUserInfo();

        $wx = 0;
        if (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'micromessenger') !== false) {//微信
            $wx = 1;
            if (!$userData) {
                $url = $this->_getConfig()['url'] . "/activity/huiju/index";
                $weixin = new WeiXinFun($this->getwxConfig());
                if ($this->userInit($weixin)) {
                    $this->_getWeiUserInfo();
                } else {
                    $toUrl = $weixin->getAuthorUrl($url, 'snsapi_userinfo');
                    header("Location: $toUrl");
                    exit;
                }
            }
        }

        if ($userData) {
            $adapter = $this->_getAdapter();
            $result = $adapter->query("SELECT activity_huiju_user.* FROM activity_huiju_user WHERE activity_huiju_user.id = ?", array($userData['huiju_id']))->current();
            if ($result) {
                $url = $this->_getConfig()['url'] . '/activity/huiju/info?id='.$result->id;
                header('Location: ' . $url);
                exit;
                /*$prizeData = $adapter->query("SELECT activity_huiju_prize.* FROM activity_huiju_prize WHERE activity_huiju_prize.id > ? ORDER BY activity_huiju_prize.id ASC", array(0))->toArray();
                $click_number = $this->countClick(2);
                $powerLog = $adapter->query("SELECT activity_huiju_fighting_log.* FROM activity_huiju_fighting_log WHERE activity_huiju_fighting_log.user_id = ? ORDER BY activity_huiju_fighting_log.id DESC LIMIT 0, 6", array($userData['huiju_id']))->toArray();
                $vm = new ViewModel(array(
                    'userInfo' => $result,
                    'powerLog' => $powerLog,
                    'click_number' => $click_number,
                    'prizeData' => $prizeData,
                ));
                $vm->setTemplate('activity/hui-ju/info.phtml');
                return $vm;*/
            }
        } else {
            $url = $this->_getConfig()['url'] . '/activity/huiju';
            header('Location: ' . $url);
            exit;
        }

        $vm = new ViewModel(array(
            'wx' => $wx,
            'jsApi' => $this->_getWeiShare()['jsApi'],
            'share' => $this->_getWeiShare()['share'],
            'toUrl' => $this->_getConfig()['url'] . '/activity/huiju/index',
        ));

        $vm->setTerminal(true);

        return $vm;
    }

    //保存图片及信息
    public function holdAction() {

        $this->checkEnd();

        if (!count($_POST['img'])) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请上传照片'));
        }


        $userData = $this->_getUserInfo();

        if (!$userData) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '该用户不存在'));
        }

        $adapter = $this->_getAdapter();

        if ($userData['uid']) {
            $result = $adapter->query("SELECT activity_huiju_user.* FROM activity_huiju_user WHERE activity_huiju_user.uid = ?", array($userData['uid']))->current();
            if ($result) {
                $this->_setHuiJuId($result->id);
                return $this->jsonResponsePage(array('status' => 0, 'message' => '已经参加过了', 'hid' => $result->id));
            }
        }

        if ($userData['open_id']) {
            $result = $adapter->query("SELECT activity_huiju_user.* FROM activity_huiju_user WHERE activity_huiju_user.open_id = ?", array($userData['open_id']))->current();
            if ($result) {
                $this->_setHuiJuId($result->id);
                return $this->jsonResponsePage(array('status' => 0, 'message' => '已经参加过了', 'hid' => $result->id));
            }
        }

        $imgStatus = $this->saveImage($_POST['img']);

        if (!$imgStatus) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '上传图片失败'));
        }

        $data = array(
            $userData['uid'],
            $userData['open_id'],
            $userData['name'],
            $userData['img'],
            $imgStatus,
            0,
            0,//use_num
            $userData['come_type'],//come_type
            time()//dateline
        );

        $res = $adapter->query("INSERT INTO activity_huiju_user(id, uid, open_id, user_name, user_img, activity_img, power_num, use_num, come_type, dateline) VALUES (NULL, ?, ?, ?, ?, ?, ?, ? , ? , ?)", $data)->count();

        if (!$res) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '保存失败'));
        }

        $huiju_id = $adapter->getDriver()->getLastGeneratedValue();

        $this->_setHuiJuId($huiju_id);

        return $this->jsonResponsePage(array('status' => 1, 'message' => '成功', 'hid' => $huiju_id));
    }

    //详情页面
    public function infoAction() {

        $userData = $this->_getUserInfo();
        $id = (int)$this->getQuery('id');

        if (!$userData && strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'micromessenger') !== false) {

            if ($id) {
                $url = $this->_getConfig()['url'] . "/activity/huiju/info?id=". $id;
            } else {
                $url = $this->_getConfig()['url'] . "/activity/huiju/index";
            }

            $weixin = new WeiXinFun($this->getwxConfig());

            if ($this->userInit($weixin)) {
                $this->_getWeiUserInfo();
            } else {
                $toUrl = $weixin->getAuthorUrl($url, 'snsapi_userinfo');
                header("Location: $toUrl");
                exit;
            }
        }

        /*if (!$id && $userData['huiju_id']) {
            $id = $userData['huiju_id'];
        }*/
        $adapter = $this->_getAdapter();
        $result = $adapter->query("SELECT activity_huiju_user.* FROM activity_huiju_user WHERE activity_huiju_user.id = ?", array($id))->current();
        if ($result) {
            $powerLog = $adapter->query("SELECT activity_huiju_fighting_log.* FROM activity_huiju_fighting_log WHERE activity_huiju_fighting_log.user_id = ? ORDER BY activity_huiju_fighting_log.id DESC LIMIT 0, 6", array($id))->toArray();
            $click_number = $this->countClick(2);
            $vm = new ViewModel(array(
                'userInfo' => $result,
                'powerLog' => $powerLog,
                'click_number' => $click_number,
                'prizeData' => $this->_getPrizeData(),
                'jsApi' => $this->_getWeiShare()['jsApi'],
                'share' => $this->_getWeiShare()['share'],
                'toUrl' => $this->_getConfig()['url'] . '/activity/huiju/info?id='. $id,
            ));
            //$vm->setTemplate('activity/hui-ju/info.phtml');
            $vm->setTerminal(true);
            return $vm;
        }

        $url = $this->_getConfig()['url'] . '/activity/huiju';
        header('Location: ' . $url);
        exit;

    }

    //加油操作
    public function powerAction() {

        $this->checkEnd();

        $user_id = $this->getPost('user_id');
        $userData = $this->_getUserInfo();

        if (!$userData || !$user_id) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '用户不存在'));
        }

        $adapter = $this->_getAdapter();

        if ($userData['uid']) {

            $quick_click = RedCache::get('activity_huiju_'. $user_id. $userData['uid']);
            if ($quick_click) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '已加油'));
            }

            $flag = $adapter->query("SELECT activity_huiju_fighting_log.id FROM activity_huiju_fighting_log WHERE user_id = ? AND uid = ? AND dateline > ?", array($user_id, $userData['uid'], strtotime(date('Y-m-d', time()))))->current();
        } elseif ($userData['open_id']) {

            $quick_click = RedCache::get('activity_huiju_'. $user_id. $userData['open_id']);
            if ($quick_click) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '已加油'));
            }

            $flag = $adapter->query("SELECT activity_huiju_fighting_log.id FROM activity_huiju_fighting_log WHERE user_id = ? AND open_id = ? AND dateline > ?", array($user_id, $userData['open_id'], strtotime(date('Y-m-d', time()))))->current();
        }

        if ($flag) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '已加过油啦'));
        }

        $note = $this->noteData[array_rand($this->noteData, 1)];

        $data = array(
            $userData['come_type'],
            $userData['uid'],
            $userData['open_id'],
            $userData['img'],
            $userData['name'],
            time(),
            $note,
            $user_id,
        );

        $adapter = $this->_getAdapter();

        $res = $adapter->query("INSERT INTO activity_huiju_fighting_log(id, come_type, uid, open_id, img, u_name, dateline, note, user_id) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?)", $data)->count();

        if ($res) {

            $adapter->query("UPDATE activity_huiju_user SET power_num=power_num+10 WHERE id = ?", array($user_id))->count();

            if ($userData['uid']) {
                RedCache::set('activity_huiju_'. $user_id. $userData['uid'], true, 20);
            } elseif($userData['open_id']) {
                RedCache::set('activity_huiju_'. $user_id. $userData['open_id'], true, 20);
            }

            return $this->jsonResponsePage(array('status' => 1, 'message' => $note, 'score' => 40));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '加油失败'));
        }
    }


    /**
     *
     * 抽奖 及 领奖 start
     *
     */


    //抽奖页面
    public function prizeAction() {

        $userData = $this->_getUserInfo();
        if (!$userData || !$userData['huiju_id']) {
            header("Location:/activity/huiju");
            exit;
        }

        $adapter = $this->_getAdapter();
        $userInfo = $adapter->query("SELECT activity_huiju_user.* FROM activity_huiju_user WHERE activity_huiju_user.id = ?", array($userData['huiju_id']))->current();

        if (!$userInfo) {
            header("Location:/activity/huiju");
            exit;
        }

        $vm = new ViewModel(array(
            'prizeData' => $this->_getPrizeData(),
            'userInfo' => $userInfo,
            'jsApi' => $this->_getWeiShare()['jsApi'],
            'share' => $this->_getWeiShare()['share'],
            'toUrl' => $this->_getConfig()['url'] . '/activity/huiju/info?id='. $userData['huiju_id'],
        ));

        $vm->setTerminal(true);

        return $vm;
    }

    //抽奖操作
    public function takePrizeAction() {

        $this->checkEnd();

        $userData = $this->_getUserInfo();

        if (!$userData || !$userData['huiju_id']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '抽奖失败'));
        }

        $adapter = $this->_getAdapter();

        $userInfo = $adapter->query("SELECT activity_huiju_user.* FROM activity_huiju_user WHERE activity_huiju_user.id = ?", array($userData['huiju_id']))->current();

        if (!$userInfo) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请参加活动'));
        }

        if (($userInfo->power_num) < 50) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '积分不够呢，召唤好友为你助力加分吧'));
        }

        $prize_user_log = $adapter->query("SELECT activity_huiju_user_prize.prize_id FROM activity_huiju_user_prize WHERE activity_huiju_user_prize.user_id = ?", array($userInfo->id));
        $prize_log = $adapter->query("SELECT activity_huiju_prize.* FROM activity_huiju_prize WHERE total_num > get_num AND activity_huiju_prize.id > ?", array(0));


        /*1、每份奖每人只能领一次，但是可以领不同的奖，一个人最多中8次。
        2、序号1、2、3，同一个人不能同时领超过2种。
        3、序号1、2、3，要抽奖达到50次以上，才有机会获得。
        4、序号4、10、16，同一个人不能同时领超过2种*/

        $user_prize_log = array();
        $dam = array();
        if ($prize_user_log->count() >= 8) {
            $prize = array(
                'pid' => 0,
                'name' => '唉呀~差一点呢！再抽一次吧',
            );
        } else {

            foreach ($prize_user_log as $p) {
                $user_prize_log[] = $p->prize_id;
            }

            $top = count(array_intersect(array(4, 5, 6),$user_prize_log));
            $sec = count(array_intersect(array(7, 13, 19),$user_prize_log));

            foreach ($prize_log as $log) {
                if (in_array($log->id, $user_prize_log)) {
                    continue;
                }

                if (($top || $userInfo->use_num < 200) && in_array($log->id, array(4, 5, 6))) {
                    continue;
                }

                if ($sec && in_array($log->id, array(7, 13, 19))) {
                    continue;
                }

                $dam[] = array(
                    'id' => $log->id,
                    'prize_name' => $log->prize_name,
                    'vp' => $log->prize_probability,
                );
            }

            $prize = $this->getUserPrizeGood($dam);

        }

        //减少 power_num 50 增加 use_num 50  activity_huiju_take_prize  增加记录 奖品 记录 get_num 增加  1； 记录用户中将记录

        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();

        $s1 = $adapter->query("UPDATE activity_huiju_user SET power_num=power_num-50,use_num=use_num+50 WHERE id=?", array($userData['huiju_id']))->count();

        if (!$s1) {
            $conn->rollback();
            return $this->jsonResponsePage(array('status' => 0, 'message' => '抽奖失败'));
        }

        $s2 = $adapter->query("INSERT INTO activity_huiju_take_prize (id, user_id, take_time, prize_id) VALUES (NULL ,?, ?, ?)", array($userData['huiju_id'], time(), $prize['pid']))->count();

        if (!$s2) {
            $conn->rollback();
            return $this->jsonResponsePage(array('status' => 0, 'message' => '抽奖失败'));
        }

        if (!$prize['pid']) {
            $conn->commit();
            return $this->jsonResponsePage(array('status' => 1, 'message' => $prize['name']));
        }

        $s3 = $adapter->query("UPDATE activity_huiju_prize SET get_num=get_num + 1 WHERE total_num >= get_num AND id = ?", array($prize['pid']))->count();
        if (!$s3) {
            $conn->rollback();
            return $this->jsonResponsePage(array('status' => 0, 'message' => '抽奖失败'));
        }

        $s4 = $adapter->query("INSERT INTO activity_huiju_user_prize(id, user_id, prize_id, dateline) VALUES (NULL ,?, ?, ?)", array($userData['huiju_id'], $prize['pid'], time()))->count();

        if (!$s4) {
            $conn->rollback();
            return $this->jsonResponsePage(array('status' => 0, 'message' => '抽奖失败'));
        }
        $conn->commit();
        return $this->jsonResponsePage(array('status' => 1, 'message' => $prize['name']));


    }

    //我的奖品页面
    public function myPrizeAction() {

        $userData = $this->_getUserInfo();
        if (!$userData || !$userData['huiju_id']) {
            header("Location:/activity/huiju");
            exit;
        }

        $adapter = $this->_getAdapter();
        $userInfo = $adapter->query("SELECT activity_huiju_user.* FROM activity_huiju_user WHERE activity_huiju_user.id = ?", array($userData['huiju_id']))->current();

        if (!$userInfo) {
            header("Location:/activity/huiju");
            exit;
        }

        $userPrize = $adapter->query("SELECT activity_huiju_user_prize.id, activity_huiju_user_prize.prize_id, activity_huiju_user_prize.status, activity_huiju_prize.prize_name, activity_huiju_prize.prize_img, activity_huiju_prize.prize_get_addr FROM activity_huiju_user_prize LEFT JOIN activity_huiju_prize ON activity_huiju_prize.id = activity_huiju_user_prize.prize_id WHERE activity_huiju_user_prize.user_id = ? ORDER BY activity_huiju_user_prize.id DESC", array($userData['huiju_id']))->toArray();

        $prizeData = array();
        $note = '';
        foreach ($userPrize as $prize) {

            if ($prize['prize_id'] == 14) {
                $note = $this->_getAiQiYi($userData['huiju_id']);
            }

            if ($prize['prize_id'] == 23 && $userInfo->uid && $prize['status'] == 1) {
                $this->_getCrash($userInfo->uid, $prize['id']);

                header("Location:/activity/huiju/myprize?tz=".time());
                exit;
            }

            $prizeData[] = array(
                'prize_img' => $prize['prize_get_addr'] ? $prize['prize_get_addr'] : $prize['prize_img'],
                'prize_name' => $prize['prize_name'],
                'id' => $prize['id'],
                'status' => $prize['status'],
                'note' => ($prize['prize_id'] == 14) ? '激活码：'. $note : '',
                'prize_id' => $prize['prize_id'],
            );
        }

        $vm = new ViewModel(array(
            'userInfo' => $userInfo,
            'userPrize' => $prizeData,
            'prizeData' => $this->_getPrizeData(),
            'jsApi' => $this->_getWeiShare()['jsApi'],
            'share' => $this->_getWeiShare()['share'],
            'toUrl' => $this->_getConfig()['url'] . '/activity/huiju/info?id='. $userData['huiju_id'],
        ));
        $vm->setTerminal(true);

        return $vm;
    }

    //领奖操作
    public function getPrizeAction() {

        $id = $this->getPost('id');
        $code = $this->getPost('code');

        $userData = $this->_getUserInfo();

        if (!$userData || !$userData['huiju_id']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请重新登陆'));
        }

        $adapter = $this->_getAdapter();

        $userPrize = $adapter->query("SELECT activity_huiju_user_prize.* FROM activity_huiju_user_prize  WHERE activity_huiju_user_prize.user_id = ? AND activity_huiju_user_prize.id = ?", array($userData['huiju_id'], $id))->current();

        if (!$userPrize) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '领奖异常'));
        }

        if ($userPrize->prize_id != 23 && $code != '1234') {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '验证码错误'));
        }

        if ($userPrize->status == 2) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '该奖品已经领取'));
        }

        if ($userPrize->prize_id == 23) {
            $res = $this->_getCrashCoupon($code);

            if ($res['status'] == 0) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => $res['message']));
            }
        }

        $res = $adapter->query("UPDATE activity_huiju_user_prize SET status=2 WHERE id=?", array($id))->count();

        if (!$res) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '领奖失败'));
        }

        return $this->jsonResponsePage(array('status' => 1, 'message' => 'ok'));

    }

    public function _getCrashCoupon($phone) {

        if (!$phone || strlen($phone) != 11 || !is_numeric($phone)) {
            return array('status' => 0, 'message' => '请输入正确的手机号码');
        }

        $userData = $this->_getPlayUserTable()->get(array('phone' => $phone));

        if (!$userData) {
            return array('status' => 0, 'message' => '该手机号码未绑定app用户');
        }

        $crash = new Coupon();
        $crash_id = array(16, 17, 18, 19, 21);
        foreach ($crash_id as $cid) {
            $crash->addCashcoupon($userData->uid, $cid, 0, 4, 0, '荟聚活动4.20');
        }

        return array('status' => 1, 'message' => '成功');
    }

    private function _getCrash($uid, $id) {

        $crash = new Coupon();

        $crash_id = array(16, 17, 18, 19, 21);
        foreach ($crash_id as $cid) {
            $crash->addCashcoupon($uid, $cid, 0, 4, 0, '荟聚活动4.20');
        }

        $adapter = $this->_getAdapter();
        $adapter->query("UPDATE activity_huiju_user_prize SET status=2 WHERE id=?", array($id))->count();

        return array('status' => 1, 'message' => '成功');

    }

    /**
     * 获取爱奇艺 序列号
     * @param $uid
     * @return string
     */
    private function _getAiQiYi($uid) {

        $adapter = $this->_getAdapter();
        $data = $adapter->query("SELECT activity_huiju_aiqiyi.number FROM activity_huiju_aiqiyi WHERE user_id = ?", array($uid))->current();

        if ($data) {
            return $data->number;
        }

        $ata = $adapter->query("SELECT activity_huiju_aiqiyi.* FROM activity_huiju_aiqiyi WHERE user_id = ?", array(0))->current();

        if (!$ata) {
            return '请联系工作人员';
        }

        $res = $adapter->query("UPDATE activity_huiju_aiqiyi SET user_id=? WHERE id=?", array($uid, $ata->id))->count();

        if (!$res) {
            return '异常 请联系工作人员';
        }

        return $ata->number;

    }

    /**
     *
     * 抽奖 及 领奖  end
     *
     */

    /**
     * 通用操作 start
     */

    //玩翻天获取用户id
    private function _getWftUid() {

        if (!isset($_GET['p']) or !$_GET['p']) {
            return false;
        }

        $p = preg_replace(array('/-/', '/_/'), array('+', '/'), $_GET['p']);
        $encryption = new Mcrypt();
        $data = json_decode($encryption->decrypt($p));

        $res = false;

        if ($data->uid) {
            $userData = $this->_getPlayUserTable()->get(array('uid' => $data->uid));
            $openWeiXin = $this->_getPlayUserWeiXinTable()->get(array('uid' => $data->uid));
            //todo
            $res = array(
                'uid' => $data->uid,
                'open_id' => $openWeiXin ? $openWeiXin->open_id : '',
                'name' => $userData->username,
                'img' => $this->getImgUrl($userData->img),
                'come_type' => 1,
            );

            setcookie('activity_huiju', serialize($res), time() + 3600 * 24 * 17, '/');
        }
        return $res;  //uid
    }

    //微信获取用户信息
    private function _getWeiUserInfo() {

        $res = false;
        if (isset($_COOKIE['open_id'])) {
            $res = array(
                'uid' =>  $_COOKIE['uid'],
                'open_id' => $_COOKIE['open_id'],
                'name' => $_COOKIE['user_name'],
                'img' => $this->getImgUrl($_COOKIE['user_img']),
                'come_type' => 2,
            );

            if (isset($_COOKIE['huiju_id'])) {
                $res['huiju_id'] = $_COOKIE['huiju_id'];
            }

            setcookie('activity_huiju', serialize($res), time() + 3600 * 24 * 17, '/');
        }

        return $res;
    }

    //获取当前用户信息
    private function _getUserInfo() {

        $res = false;
        if (isset($_COOKIE['activity_huiju'])) {
            $res = unserialize($_COOKIE['activity_huiju']);
        }
        return $res;
    }

    //设置 huiju_id 重新设置cookie
    private function _setHuiJuId($id) {

        if (!isset($_COOKIE['activity_huiju'])) {
            return true;
        }

        $res = unserialize($_COOKIE['activity_huiju']);
        $res['huiju_id'] = $id;

        setcookie('activity_huiju', serialize($res), time() + 3600 * 24 * 17, '/');

        return true;

    }

    //保存图片
    //todo 保存相片
    private function saveImage($file)
    {
        $url = $_SERVER['DOCUMENT_ROOT'] . '/uploads/activity/huiju/' . date('Ym/d/');
        if (!is_dir($url)) {
            mkdir($url, 0777, true);
        }
        $ext = rand(10, 99) . time(); //唯一的一位数

        $file_up = file_put_contents($url . $ext. '.jpg', base64_decode($file));

        return $file_up ? '/uploads/activity/huiju/' . date('Ym/d/') . $ext. '.jpg' : '';

    }

    //返回活动统计点击数
    private function countClick($activity_id) {

        $adapter = $this->_getAdapter();

        $result = $adapter->query("SELECT play_click_log.click_number FROM play_click_log WHERE object_type='wap_activity' AND object_id=?", array($activity_id))->current();
        if (!$result) {
            $adapter->query("INSERT INTO play_click_log(id, object_id, object_type, click_number, dateline) VALUES (NULL, ?, ?, ?, ?)", array($activity_id, 'wap_activity', 1, time()))->count();
        }
        $adapter->query("UPDATE play_click_log SET click_number=click_number+1 WHERE object_type='wap_activity' AND object_id=?", array($activity_id))->count();

        $click_num = $result ? $result->click_number : 1;
        return $click_num;
    }

    //获取奖品数据
    private function _getPrizeData() {

        $adapter = $this->_getAdapter();
        $prizeData = $adapter->query("SELECT activity_huiju_prize.* FROM activity_huiju_prize WHERE activity_huiju_prize.id > ? ORDER BY activity_huiju_prize.id ASC", array(0))->toArray();

        shuffle($prizeData);
        $res = array_slice($prizeData, (count($prizeData) - 9));

        return $res;
    }

    //获取品牌
    private function _getBrandData() {
        $res = array(
            array(
                'prize_img' => 'http://wan.wanfantian.com/uploads/2016/04/20/6028275734300da5ff0a118f0661091b.jpg',
                'prize_name' => '全明星滑冰俱乐部',
            ),
            array(
                'prize_img' => 'http://wan.wanfantian.com/uploads/2016/04/20/d92773b5eb69053df16a2cef78422b80.jpg',
                'prize_name' => 'kidsland',
            ),
            array(
                'prize_img' => 'http://wan.wanfantian.com/uploads/2016/04/20/36db5d733d9d5f16753c39855846a321.jpg',
                'prize_name' => 'babyland',
            ),
            array(
                'prize_img' => 'http://wan.wanfantian.com/uploads/2016/04/20/15be93f51f806bc994b989a8dcdc6784.jpg',
                'prize_name' => '乐友孕婴童',
            ),
            array(
                'prize_img' => 'http://wan.wanfantian.com/uploads/2016/04/20/c369542cc8b4697fff14c78a7a38c401.jpg',
                'prize_name' => '美育音乐舞蹈',
            ),
            array(
                'prize_img' => 'http://wan.wanfantian.com/uploads/2016/04/20/957b860165f1c51ac1fe21d5337ec0c6.jpg',
                'prize_name' => '爱奇艺',
            ),
            array(
                'prize_img' => 'http://wan.wanfantian.com/uploads/2016/04/20/371eb21bd3f8c6466a7b2b651c7b5b0d.jpg',
                'prize_name' => '哈你运动馆',
            ),
            array(
                'prize_img' => 'http://wan.wanfantian.com/uploads/2016/04/20/657a423745b0890657a3f07cb897af7e.jpg',
                'prize_name' => '玩具反斗城',
            ),
            array(
                'prize_img' => 'http://wan.wanfantian.com/uploads/2016/04/20/8af23bf0cc46245cfd43a52ff617cd01.jpg',
                'prize_name' => '美吉姆',
            ),
            array(
                'prize_img' => 'http://wan.wanfantian.com/uploads/2016/04/20/000b62b4343e738abaea22e213689e89.jpg',
                'prize_name' => '法国时尚童装LCDP',
            ),
            array(
                'prize_img' => 'http://wan.wanfantian.com/uploads/2016/04/20/2d319b81633a7f21e724a43705103f08.jpg',
                'prize_name' => '瑞思学科英语',
            ),
            array(
                'prize_img' => 'http://wan.wanfantian.com/uploads/2016/04/20/a519c9239cc497509674a70a4917f333.jpg',
                'prize_name' => '雅哈咖啡',
            ),
            array(
                'prize_img' => 'http://wan.wanfantian.com/uploads/2016/04/20/3be15d7583a7c03512547c35f8a1ff5c.jpg',
                'prize_name' => '玩翻天',
            ),

        );
        return $res;
    }

    //获取微信分享
    private function _getWeiShare() {

        $result = array();
        $weixin = new WeiXinFun($this->getwxConfig());

        $result['jsApi'] = $weixin->getsignature();

        $shareDataTitle = array(
            '晒宝宝疯玩照片，赢取荟聚壕礼！' => 1,
            '荟聚1周年啦！疯送四整天，还不快来！' => 2,
            '辣妈，给我们助个力吧！' => 3,
        );

        $shareDataDesc = array(
            '娃不疯玩枉童年！这张照片只有亲娘有！哈哈' => 1,
            '晒宝宝疯玩照片，赢取荟聚疯狂好礼！' => 2,
            '爱奇艺会员卡、全明星1000元卡、玩翻天现金券在等我！' => 3,
        );

        $result['share'] = array(
            'title' => array_rand($shareDataTitle, 1),
            'desc' => array_rand($shareDataDesc, 1),
            'img' => $this->_getConfig()['url']. '/activity/images/huiju_share.png',
        );

        return $result;
    }

    /**
     * 通用操作 end
     */



    //获取用户 获得 奖品
    private function getUserPrizeGood($prize_arr) {

        $prizeData = $prize_arr;
        $good = array();
        $vp = 0;
        $st = 0;
        foreach ($prize_arr as $ze) {
            $good[$ze['id']] = $ze['prize_name'];
            $vp = $vp + $ze['vp'];
            $st = ($ze['vp'] > $st) ? $ze['vp'] : $st;
        }

        Array_push($prizeData, array('id' => 0, 'prize_name' => '唉呀~差一点呢！再抽一次吧', 'vp' => $st));

        foreach ($prizeData as $key => $val) {
            $arr[$val['id']] = $val['vp'];
        }

        $rid = $this->get_rand($arr);
        $prize = array(
            'pid' => $rid,
            'name' => $rid ? $good[$rid] : '唉呀~差一点呢！再抽一次吧',
        );

        return $prize;

    }

    private function get_rand($proArr) {
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


    //初始化用户 生成用户 生成验证信息
    public function userInit(WeiXinFun $weixin)
    {
        if (isset($_GET['code'])) {
            //todo 封装  存储相关信息，获取用户信息，生成cookie
            $accessTokenData = $weixin->getUserAccessToken($_GET['code']);

            if (isset($accessTokenData->access_token)) {

                $userInfo = $weixin->getUserInfo($accessTokenData->access_token);
                if (!$userInfo) {
                    $username = 'WeiXin' . time();
                    $img = '';
                } else {
                    $username = $userInfo->nickname;
                    $img = $userInfo->headimgurl;
                }

                $openWeiXin = $this->_getPlayUserWeiXinTable()->get(array('open_id' => $accessTokenData->openid));

                if ($openWeiXin) {
                    $uid = $openWeiXin->uid;
                } else {
                    $uid = 0;
                }

                $this->setCookie($uid, $accessTokenData->openid, $username, $img);

                return true;
            } else {
                //todo 错误处理机制
                WriteLog::WriteLog('获取userAccessToken错误:' . print_r($accessTokenData, true));
                return false;
            }
        } else {
            //todo 如果用户点了拒绝
            return false;
        }

    }


    public function setCookie($uid, $openid, $user_name, $user_img)
    {
        $_COOKIE['uid'] = $uid;
        $_COOKIE['open_id'] = $openid;
        $_COOKIE['user_name'] = $user_name;
        $_COOKIE['user_img'] = $user_img;

        //todo 如果退出
        $adapter = $this->_getAdapter();

        $user = $adapter->query("SELECT activity_huiju_user.id FROM activity_huiju_user WHERE open_id = ?", array($openid))->current();

        if ($user) {
            $_COOKIE['huiju_id'] = $user->id;
        }

        $untime = time() + 3600 * 24 * 17;  //失效时间
        setcookie('uid', $uid, $untime, '/');
        setcookie('open_id', $openid, $untime, '/');
        setcookie('user_name', $user_name, $untime, '/');
        setcookie('user_img', $user_img, $untime, '/');
    }



}
