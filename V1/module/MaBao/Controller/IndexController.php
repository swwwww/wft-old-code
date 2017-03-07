<?php

namespace MaBao\Controller;

use Deyi\BaseController;
use Deyi\JsonResponse;
use Deyi\Mcrypt;
use library\Service\System\Cache\RedCache;
use Deyi\Upload;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Stdlib\ArrayObject;
use Deyi\SendMessage;
use Zend\View\Model\ViewModel;
use Deyi\WeiXinFun;

class IndexController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    private $wft;
    private $share = 1; //是否需要分享


    public function __construct()
    {
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'wft') === false) {
            $this->wft = 'no';
        } else {
            $this->wft = 'yes';
        }
    }


    //todo 首页
    public function indexAction()
    {
        $uid = (int)$this->getUid();

        $flag = $this->checkOwn($uid);

        $shared = 0;
        if ($this->share) {
            $tg = $this->_getPlayShareTable()->get(array('uid' => $uid, 'share_id' => 987, 'type' => 'webview', 'dateline > ?' => 1459094400));
            if ($tg) {
                $shared = 1;
            }
        } else {
            $shared = 1;
        }
        $phone = 0;
        if ($uid) {
            $phone = $this->_getPlayUserTable()->get(array('uid' => $uid))->phone;
        }
        $data = $this->_getPlayMaBaoBaoTable()->get(array('uid' => $uid));

        $one = array("title" => "咩~江城羊宝宝在哪里？", "content" => "贝乐园喊你免费来领游玩卡啦！");
        $two = array("title" => "我是羊宝宝，我要免费领贝乐园游玩卡", "content" => "江城2015年出生的羊宝宝们，都可以来领噢");

        $friendOne = "咩~江城羊宝宝在哪里？贝乐园喊你免费来领游玩卡啦！";
        $friendTwo = "我是羊宝宝，我要免费领贝乐园游玩卡！江城2015年出生的羊宝宝们，都可以来领噢";

        $toUrl = $this->_getConfig()['url'] . '/mabao/index?id=987';
        $weixin = new WeiXinFun($this->getwxConfig());
        if (!$this->checkWeiXinUser()) {
            $toUrl = $weixin->getAuthorUrl($toUrl, 'snsapi_userinfo');
        }

        $number = $this->_getPlayMaBaoBaoTable()->fetchCount();

        //点击数
        $click_number = 18563 + $this->countClick(1);

        return array(
            'uid' => $uid,
            'flag' => $flag ? 1 : 0,
            'wft' => $this->wft,
            'share' => $shared,
            'phone' => $phone,
            'check_status' => $data->check_status,
            "text" => (rand(1, 2) == 1) ? $one : $two,
            "friend" => (rand(1, 2) == 1) ? $friendOne : $friendTwo,
            'toUrl' => $toUrl,
            'jsApi' => $weixin->getsignature(),
            'number' => $number,
            'click_number' => $click_number,
        );
    }

    //todo 填写个人资料
    public function joinAction()
    {
        $vm = new ViewModel();
        $vm->setTemplate('/ma-bao/index/index');
        return $vm;

        if ($this->wft == 'no') {
            $vm = new ViewModel();
            $vm->setTemplate('/ma-bao/index/index');
            return $vm;
        }
        $uid = (int)$this->getQuery('uid');
        $data = $this->checkOwn($uid);

        if ($data && $data->check_status >= 0) {
            $vm = new ViewModel();
            $vm->setTemplate('/ma-bao/index/index');
            return $vm;
        }
        return array(
            'uid' => $uid,
            'data' => $data,
        );

    }

    //todo 保存个人资料操作
    public function confirmAction()
    {
        echo  '下线了';
        exit();
        $username = $this->getPost('username');
        $gender = $this->getPost('gender');
        $birthday = $this->getPost('birthday');
        $address = $this->getPost('area');
        $uid = (int)$this->getPost('uid');
        //$uid = 1;
        if (!$username) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写宝宝姓名'));
        }
        if (strtotime($birthday) > 1451577600 || strtotime($birthday) < 1420041600) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '宝宝生日时间不对哦'));
        }

        if (!$address) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请选择宝宝地址'));
        }
        if (!count($_POST['fileup'])) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请上传照片'));
        }
        if (count($_POST['fileup']) < 2) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请至少上传2张照片'));
        }
        if (count($_POST['fileup']) > 5) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '最多只能上传5张照片'));
        }
        $data = array(
            'username' => $username,
            'sex' => $gender,
            'birthday' => strtotime($birthday),
            'address' => $address,
        );

        $tip = $this->_getPlayMaBaoBaoTable()->get(array('uid' => $uid));
        if ($tip) {//报过名
            if ($tip->check_status == -1) {//被驳。继续报
                $data['check_status'] = 0;
                $this->_getPlayMaBaoBaoTable()->update($data, array('uid' => $uid));
                $pid = $tip->id;
            } elseif ($tip->check_status == 0) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '您已经报名了羊宝宝活动，请等待审核'));
            } elseif ($tip->check_status == 1) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '您已经参与了羊宝宝活动'));
            } elseif ($tip->check_status == 2) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '您已经领取了畅玩卡'));
            }
        } else {//第一次报名,报
            $data['uid'] = $uid;
            $flag = $this->_getPlayMaBaoBaoTable()->insert($data);
            $pid = $this->_getPlayMaBaoBaoTable()->getlastInsertValue();
            if (!$flag) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '保存失败'));
            }
        }

        if ($pid) {
            $this->_getPlayAttachTable()->delete(array('use_id' => $pid, 'use_type' => 'mabaobao', 'uid' => $uid));
        }
        $this->saveImage($_POST['fileup'], $uid, $pid);
        return $this->jsonResponsePage(array('status' => 1, 'uid' => $uid));
    }

    //todo 我的畅玩卡
    public function cardAction()
    {
        if ($this->wft == 'no') {
            $vm = new ViewModel();
            $vm->setTemplate('/ma-bao/index/index');
            return $vm;
        }
        $uid = (int)$this->getQuery('uid');
        $data = $this->checkOwn($uid);

        if (!$uid) {
            exit('请先登录');
        }

        if ($data && $data->check_status < 0) {
            $vm = new ViewModel();
            $vm->setTemplate('/ma-bao/index/index');
            return $vm;
        }
        $user = $this->_getPlayUserTable()->get(array('uid' => $uid))->username;
        $imgData = $this->_getPlayAttachTable()->fetchAll(array('uid' => $uid, 'use_id' => $data->id, 'use_type' => 'mabaobao'));
        return array(
            'data' => $data,
            'imgData' => $imgData,
            'url' => $this->_getConfig()['url'],
            'user' => $user,
        );
    }

    //todo 畅玩地
    public function placeAction()
    {
        $outer = $this->getQuery('otu');
        if ($this->wft == 'no' && !$outer) {
            $vm = new ViewModel();
            $vm->setTemplate('/ma-bao/index/index');
            return $vm;
        }
    }

    //todo 活动详情
    public function stepAction()
    {
        $outer = $this->getQuery('otu');
        if ($this->wft == 'no' && !$outer) {
            $vm = new ViewModel();
            $vm->setTemplate('/ma-bao/index/index');
            return $vm;
        } elseif ($this->wft == 'no' && $outer) {
            return array(
                'wft' => 'no',
            );
        }

        $uid = (int)$this->getQuery('uid');
        return array(
            'uid' => $uid,
            'wft' => 'yes',
        );
    }

    /**
     * 获取uid
     * @return bool|int
     */
    private function getUid()
    {

        if (!isset($_GET['p']) or !$_GET['p']) {
            return false;
        }

        $p = preg_replace(array('/-/', '/_/'), array('+', '/'), $_GET['p']);
        $encryption = new Mcrypt();
        $data = json_decode($encryption->decrypt($p));
        return (int)$data->uid;  //uid

    }

    //todo 是否得到卡券
    private function checkOwn($uid)
    {
        if (!$uid) {
            return false;
        }

        return $this->_getPlayMaBaoBaoTable()->get(array('uid' => $uid));
    }

    //todo 保存相片
    private function saveImage($file, $uid, $pid)
    {
        $url = $_SERVER['DOCUMENT_ROOT'] . '/uploads/mabaobao/' . date('Ym/d/');
        if (!is_dir($url)) {
            mkdir($url, 0777, true);
        }
        $ext = rand(10, 99) . time(); //唯一的一位数
        foreach ($file as $n => $m) {
            $file_up = file_put_contents($url . $ext . '_' . $n . '.jpg', base64_decode($m));
            if ($file_up) {
                $this->_getPlayAttachTable()->insert(array(
                        'uid' => $uid,
                        'use_id' => $pid,
                        'use_type' => 'mabaobao',
                        'dateline' => time(),
                        'url' => '/uploads/mabaobao/' . date('Ym/d/') . $ext . '_' . $n . '.jpg',
                        'is_remote' => 0,
                        'name' => '',
                    )
                );
            }
        }
    }

    //todo 下载页
    public function downloadAction()
    {
        $vm = new viewModel(array());
        $vm->setTerminal(true);
        return $vm;
    }

    //todo 检测是否分享
    public function shareAction()
    {
        $uid = (int)$this->getQuery('uid');

        $tg = $this->_getPlayShareTable()->get(array('uid' => $uid, 'share_id' => 987, 'type' => 'webview', 'dateline > ?' => 1459094400));
        if ($tg) {
            return $this->jsonResponsePage(array('status' => 1));
        } else {
            return $this->jsonResponsePage(array('status' => 0));
        }

    }

    //todo 外面提交表单页面
    public function messAction()
    {

    }

    //todo 外面提交 检测
    public function mussAction()
    {
        $username = $this->getPost('username');
        $gender = $this->getPost('gender');
        $birthday = $this->getPost('birthday');
        $address = $this->getPost('area');
        $phone = $this->getPost('phone');

        if (!preg_match('/^(13|15|18|17)\d{9}$/', $phone)) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写正确的手机号码'));
        }

        //TODO 根据手机号 查出 uid
        $user_data = $this->_getPlayUserTable()->get(array('phone' => $phone));
        if (!$user_data) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '该手机还没注册, 请先注册'));
        }

        //TODO 检测是否已经参加
        $ext = $this->_getPlayOrderInfoTable()->get(array('coupon_id' => $this->couid, 'buy_phone' => $phone));
        if ($ext) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '该手机已经参与了羊宝宝活动'));
        }

        if (!$username) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '宝宝姓名'));
        }
        if (strtotime($birthday) < 788893261 || strtotime($birthday) > 1420045261) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '宝宝生日时间不对哦'));
        }
        if (!$address) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '宝宝地址'));
        }
        if (!count($_POST['fileup'])) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '照片'));
        }

        $data = array(
            'username' => $username,
            'sex' => $gender,
            'birthday' => strtotime($birthday),
            'address' => $address,
        );

        $tip = $this->_getPlayMaBaoBaoTable()->get(array('uid' => $user_data->uid));
        if ($tip) {
            if ($tip->check_status == -1) {
                $data['check_status'] = 0;
            }
            $this->_getPlayMaBaoBaoTable()->update($data, array('uid' => $user_data->uid));
            $pid = $tip->id;
        } else {

            $data['uid'] = $user_data->uid;
            $flag = $this->_getPlayMaBaoBaoTable()->insert($data);
            $pid = $this->_getPlayMaBaoBaoTable()->getlastInsertValue();
            if (!$flag) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '保存失败'));
            }
        }

        if ($tip) {
            $this->_getPlayAttachTable()->delete(array('use_id' => $pid, 'use_type' => 'mabaobao', 'uid' => $user_data->uid));
        }
        $this->saveImage($_POST['fileup'], $user_data->uid, $pid);
        // todo 分享
        //$this->_getPlayShareTable()->insert(array('uid' => $user_data->uid, 'type' => 'activity', 'share_id' => 2, 'dateline' => time()));

        return $this->jsonResponsePage(array('status' => 1, 'message' => '成功，请等待短信审核通知'));
    }

    public function useAction()
    {
        $id = $this->getPost('id');
        $code = $this->getPost('code');
        if ($code != 1234) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '您输入验证码有误！'));
        }
        $data = $this->_getPlayMaBaoBaoTable()->get(array('id' => $id));
        if ($data->check_status == 1) {
            $result = $this->_getPlayMaBaoBaoTable()->update(array("check_status" => 2), array('id' => $id));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '用户未审核'));
        }

        if ($result) {
            return $this->jsonResponsePage(array('status' => 1, 'message' => '使用成功'));
        }
    }

    /**
     *  //返回活动统计点击数
     * @param $activity_id //wap 活动id
     * @return int
     */
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
}
