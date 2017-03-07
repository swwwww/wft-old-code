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

class IndexController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    private $wft;
    private $couid = 442; //活动卡券id
    private $share = 1; //是否需要分享


    public function __construct(){
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'wft') === false) {
            $this->wft = 'no';
        } else {
            $this->wft = 'yes';
        }
    }


    //todo 首页
    public function indexAction()
    {
        if ($this->wft == 'no') {
            return array(
                'wft' => 'no'
            );
        }
        $user_data = $this->getUid();

        $uid = (int)$user_data->uid;
        $flag = $this->checkOwn($uid);

        $shared = 0;
        if ($this->share) { //需要分享后才能 下马宝宝
            $tg = $this->_getPlayShareTable()->get(array('uid' => $uid, 'share_id' => 2));
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


        return array(
            'uid' => $uid,
            'flag' => $flag ? 1 : 0,
            'wft' => 'yes',
            'share' => $shared,
            'phone' => $phone,
        );
    }

    //todo 填写个人资料
    public function joinAction() {
        if ($this->wft == 'no') {
            $vm = new ViewModel();
            $vm->setTemplate('/ma-bao/index/index');
            return $vm;
        }
        $uid = (int)$this->getQuery('uid');
        $data = $this->checkOwn($uid);
        return array(
            'uid' => $uid,
            'data' => $data,
        );

    }

    //todo 保存个人资料操作
    public function confirmAction() {

        $username = $this->getPost('username');
        $gender = $this->getPost('gender');
        $birthday = $this->getPost('birthday');
        $address =  $this->getPost('area');
        $uid = (int)$this->getPost('uid');

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

        $tip = $this->_getPlayMaBaoBaoTable()->get(array('uid' => $uid));
        if ($tip) {
            if ($tip->check_status == -1) {
                $data['check_status'] = 0;
            }
            $this->_getPlayMaBaoBaoTable()->update($data, array('uid' => $uid));
            $pid =  $tip->id;
        } else {

            //TODO 限制手机号购买一次
            $user_data = $this->_getPlayUserTable()->get(array('uid' => $uid));
            $ext = $this->_getPlayOrderInfoTable()->get(array('coupon_id' => $this->couid, 'buy_phone' =>  $user_data->phone));
            if ($ext) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '该手机已经参与了马宝宝活动'));
            }

            $data['uid'] = $uid;
            $flag = $this->_getPlayMaBaoBaoTable()->insert($data);
            $pid = $this->_getPlayMaBaoBaoTable()->getlastInsertValue();
            if (!$flag) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '保存失败'));
            }
        }

        if ($tip) {
            $this->_getPlayAttachTable()->delete(array('use_id' => $pid, 'use_type' => 'mabaobao', 'uid' => $uid));
        }
        $this->saveImage($_POST['fileup'], $uid, $pid);
        return $this->jsonResponsePage(array('status' => 1, 'uid' => $uid));
    }

    //todo 我的畅玩卡
    public function cardAction() {
        if ($this->wft == 'no') {
            $vm = new ViewModel();
            $vm->setTemplate('/ma-bao/index/index');
            return $vm;
        }
        $uid = (int)$this->getQuery('uid');
        $data = $this->checkOwn($uid);
        if (!$data) {
            exit('请先登录');
        }
        $user = $this->_getPlayUserTable()->get(array('uid' => $uid))->username;
        $imgData = $this->_getPlayAttachTable()->fetchAll(array('uid' => $uid, 'use_id' => $data->id, 'use_type' => 'mabaobao'));
        $orderInfo = $this->_getPlayOrderInfoTable()->get(array('user_id' => $uid, 'coupon_id' => $this->couid, 'real_pay' => 0));
        $order = '';
        if ($orderInfo) {
            $order = $this->_getPlayCouponCodeTable()->get(array('order_sn' => $orderInfo->order_sn));
        }

        return array(
            'data' => $data,
            'imgData' => $imgData,
            'url' => $this->_getConfig()['url'],
            'user' => $user,
            'order' => $order,
        );
    }

    //todo 畅玩地
    public function placeAction() {
        $outer = $this->getQuery('otu');
        if ($this->wft == 'no' && ! $outer) {
            $vm = new ViewModel();
            $vm->setTemplate('/ma-bao/index/index');
            return $vm;
        }
    }

    //todo 活动详情
    public function stepAction() {
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

    //todo 解密uid
    private  function getUid() {
        if (!isset($_GET['p']) or !$_GET['p']) {
            return false;
        }
        $p = preg_replace(array('/-/', '/_/'), array('+', '/'), $_GET['p']);
        $encryption = new Mcrypt();
        $data = $encryption->decrypt($p);
        return json_decode($data);  //返回对象数组  uid and timestamp
    }

    //todo 是否得到卡券
    private function checkOwn($uid) {
        return $this->_getPlayMaBaoBaoTable()->get(array('uid' => $uid));
    }

    //todo 保存相片
    private function saveImage($file, $uid, $pid) {
        $url = $_SERVER['DOCUMENT_ROOT'] . '/uploads/mabaobao/' . date('Ym/d/');
        if (!is_dir($url)) {
            mkdir($url, 0777, true);
        }
        $ext = rand(10, 99).time(); //唯一的一位数
        foreach($file as $n=>$m) {
            $file_up = file_put_contents($url. $ext. '_'. $n. '.jpg', base64_decode($m));
            if($file_up) {
                 $this->_getPlayAttachTable()->insert(array(
                        'uid' => $uid,
                        'use_id' => $pid,
                        'use_type' => 'mabaobao',
                        'dateline' => time(),
                        'url' => '/uploads/mabaobao/' . date('Ym/d/'). $ext. '_'. $n. '.jpg',
                        'is_remote' => 0,
                        'name' => '',
                    )
                );
            }
        }
    }

    //todo 下载页
    public function downloadAction() {
        $vm = new viewModel(array());
        $vm->setTerminal(true);
        return $vm;
    }

    //todo 检测是否分享
    public function shareAction() {
        $uid = (int)$this->getQuery('uid');

        $tg = $this->_getPlayShareTable()->get(array('uid' => $uid, 'share_id' => 2));
        if ($tg) {
            return $this->jsonResponsePage(array('status' => 1));
        } else {
            return $this->jsonResponsePage(array('status' => 0));
        }

    }

    //todo 外面提交表单页面
    public function messAction() {

    }

    //todo 外面提交 检测
    public function mussAction() {
        $username = $this->getPost('username');
        $gender = $this->getPost('gender');
        $birthday = $this->getPost('birthday');
        $address =  $this->getPost('area');
        $phone =  $this->getPost('phone');

        if (!preg_match('/^(13|15|18|17)\d{9}$/', $phone)) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写正确的手机号码'));
        }

        //TODO 根据手机号 查出 uid
        $user_data = $this->_getPlayUserTable()->get(array('phone' => $phone));
        if (!$user_data) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '该手机还没注册, 请先注册'));
        }

        //TODO 检测是否已经参加
        $ext = $this->_getPlayOrderInfoTable()->get(array('coupon_id' => $this->couid, 'buy_phone' =>  $phone));
        if ($ext) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '该手机已经参与了马宝宝活动'));
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
            $pid =  $tip->id;
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



}
