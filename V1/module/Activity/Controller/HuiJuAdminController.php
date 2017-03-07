<?php

namespace Activity\Controller;

use Deyi\BaseController;
use Deyi\JsonResponse;
use Deyi\Paginator;
use Deyi\Account\Account;
use Deyi\Mcrypt;
use Deyi\Coupon\Coupon;
use Deyi\Integral\Integral;
use Deyi\OutPut;
use Deyi\WriteLog;
use library\Service\System\Cache\RedCache;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\EventManager\EventManagerInterface;

class HuiJuAdminController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    public function __construct()
    {

    }

    public function setEventManager(EventManagerInterface $events)
    {
        parent::setEventManager($events);
        $controller = $this;
        $events->attach('dispatch', function ($e) use ($controller) {
            if ($_SERVER['REQUEST_URI'] != '/activity/manage/login') { //排除登陆界面
                if (isset($_COOKIE['user'])) {
                    $hash_data = json_encode(array('user' => $_COOKIE['user'], 'id' => $_COOKIE['id'], 'group' => $_COOKIE['group']));
                    $token = hash_hmac('sha1', $hash_data, $this->_getConfig()['token_key']);
                    if ($token == $_COOKIE['token']) {
                    } else {
                        header('Location: /activity/manage/login');
                        exit;
                    }
                } else {
                    header('Location: /activity/manage/login');
                    exit;
                }
            }

        }, 100);
    }

    //奖品列表
    public function prizeListAction() {

        $page = (int)$this->getQuery('p', 1);
        $pageSum =  (int)$this->getQuery('page_num',10);
        $start = ($page - 1) * $pageSum;
        $adapter = $this->_getAdapter();

        $data = $adapter->query("SELECT * FROM activity_huiju_prize WHERE id > ? ORDER BY id DESC LIMIT {$start}, {$pageSum}", array(0));

        $count = $adapter->query("SELECT * FROM activity_huiju_prize WHERE id > ?", array(0))->count();

        //创建分页
        $url = '/activity/huijuadmin/prizelist';
        $paging = new Paginator($page, $count, $pageSum, $url);

        $vm = new ViewModel(array(
            'data' => $data,
            'page' => $paging->getHtml(),
        ));

        $vm->setTerminal(true);

        return $vm;
    }

    //保存奖品
    public function savePrizeAction() {
        $id = (int)$this->getPost('id');
        $prize_name = $this->getPost('prize_name');
        $total_num = $this->getPost('total_num');
        $prize_probability = $this->getPost('prize_probability');
        $prize_value = $this->getPost('prize_value');
        $prize_use_time = $this->getPost('prize_use_time');
        $prize_get_time = $this->getPost('prize_get_time');
        $prize_get_addr = $this->getPost('prize_get_addr');
        $tip = $this->getPost('tip');
        $prize_img = $this->getPost('prize_img');

        //todo


        $adapter = $this->_getAdapter();
        if ($id) {
            $data = array(
                $prize_name,
                $total_num,
                $prize_probability,
                $prize_img,
                $prize_value,
                $prize_use_time,
                $prize_get_time,
                $prize_get_addr,
                $tip,
                $id
            );
            $s = $adapter->query("UPDATE activity_huiju_prize SET prize_name=?, total_num=?, prize_probability=?, prize_img=?, prize_value=?, prize_use_time=?, prize_get_time=?, prize_get_addr=?, tip=? WHERE id=?", $data)->count();
            return $this->_Goto($s ? '成功' : '失败', '/activity/huijuadmin/prizelist');
        }

        $data = array(
            $prize_name,
            $total_num,
            0,
            1,
            $prize_probability,
            $prize_img,
            $prize_value,
            $prize_use_time,
            $prize_get_time,
            $prize_get_addr,
            $tip
        );


        $s = $adapter->query("INSERT INTO activity_huiju_prize(id, prize_name, total_num, get_num, user_limit_num, prize_probability, prize_img, prize_value, prize_use_time, prize_get_time, prize_get_addr, tip) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $data)->count();

        return $this->_Goto($s ? '成功' : '失败', '/activity/huijuadmin/prizelist');


    }

    //添加奖品
    public function prizeNewAction() {

        $id = (int)$this->getQuery('id');
        $data = null;

        if ($id) {
            $adapter = $this->_getAdapter();
            $result = $adapter->query("SELECT activity_huiju_prize.* FROM activity_huiju_prize WHERE activity_huiju_prize.id = ?", array($id))->current();
            if ($result) {
                $data = $result;
            }
        }

        $vm = new ViewModel(array(
            'data' => $data,
        ));

        $vm->setTerminal(true);

        return $vm;
    }

    //list
    public function listAction() {

        $page = (int)$this->getQuery('p', 1);
        $pageSum =  (int)$this->getQuery('page_num',10);
        $start = ($page - 1) * $pageSum;
        $adapter = $this->_getAdapter();

        $data = $adapter->query("SELECT * FROM activity_huiju_user WHERE id > ? ORDER BY id DESC LIMIT {$start}, {$pageSum}", array(0));

        $count = $adapter->query("SELECT * FROM activity_huiju_user WHERE id > ?", array(0))->count();

        //创建分页
        $url = '/activity/huijuadmin/list';
        $paging = new Paginator($page, $count, $pageSum, $url);

        $vm = new ViewModel(array(
            'data' => $data,
            'page' => $paging->getHtml(),
        ));

        $vm->setTerminal(true);

        return $vm;
    }

    public function viewAction() {

        $id = $this->getQuery('id');

        $adapter = $this->_getAdapter();
        $userInfo = $adapter->query("SELECT activity_huiju_user.* FROM activity_huiju_user WHERE activity_huiju_user.id = ?", array($id))->current();

        $vm = new ViewModel(array(
            'userInfo' => $userInfo,
        ));

        $vm->setTerminal(true);

        return $vm;
    }

    public function updateImgAction() {

        $img = $this->getPost('prize_img');
        $id = $this->getPost('id');
        $adapter = $this->_getAdapter();

        $s = $adapter->query("UPDATE activity_huiju_user SET activity_img=? WHERE id=?", array($img, $id))->count();

        return $this->_Goto($s? '成功' : '失败');
    }

    //统计相关
    public function infoAction() {



        //需要数据：总页面pv，总参与人数（包括微信、app分开的数据），各个奖的兑换数据。以上的每天数据。

        $adapter = $this->_getAdapter();
        $pv_result = $adapter->query("SELECT play_click_log.click_number FROM play_click_log WHERE object_type='wap_activity' AND object_id=?", array(2))->current();
        $pv = $pv_result ? $pv_result->click_number : 1;

        echo '总页面pv: '. $pv;

        echo '<br />';

        $user_result = $adapter->query("SELECT activity_huiju_user.id FROM activity_huiju_user", array())->count();
        echo '参加的总人数是: '. $user_result;
        echo '<br />';
        $user_weixin_result1 = $adapter->query("SELECT activity_huiju_user.id FROM activity_huiju_user where come_type = 3 AND uid = 0", array())->count();
        $user_weixin_result2 = $adapter->query("SELECT activity_huiju_user.id FROM activity_huiju_user where come_type = 2", array())->count();

        echo '微信参加的总人数是: '. ($user_weixin_result1 + $user_weixin_result2);
        echo '<br />';

        $user_app_result1 = $adapter->query("SELECT activity_huiju_user.id FROM activity_huiju_user where come_type = 3 AND uid > 0", array())->count();
        $user_app_result2 = $adapter->query("SELECT activity_huiju_user.id FROM activity_huiju_user where come_type = 1", array())->count();

        echo 'app参加的总人数是: '. ($user_app_result1 + $user_app_result2);
        echo '<br />';

        $prizeData = $adapter->query("SELECT activity_huiju_prize.* FROM activity_huiju_prize", array())->toArray();

        echo '奖品情况如下: ';
        echo '<br />';
        foreach ($prizeData as $prize) {
            if ($prize['id'] == 14) {
                $use_num = $prize['get_num'];
            } else {
                $use_num = $adapter->query("SELECT activity_huiju_user_prize.id FROM activity_huiju_user_prize where prize_id = ? AND status = 2", array($prize['id']))->count();
            }

            echo   '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . '奖品 ：'. $prize['prize_name'] . ' 数量 :' . $prize['total_num'] . ' 领取 ：' . $prize['get_num'] . ' 使用' . $use_num;
            echo '<br />';
        }

        echo '<br />';
        echo '<br />';
        echo '<br />';

        $yb_result = $adapter->query("SELECT play_mabaobao.id FROM play_mabaobao", array())->count();
        $yb_check_result = $adapter->query("SELECT play_mabaobao.id FROM play_mabaobao where check_status = 2", array())->count();

        echo '羊宝活动参加的总人数是: '. $yb_result;
        echo '<br />';

        echo '羊宝活动使用的人数是: '. $yb_check_result;
        echo '<br />';

        exit;

        /*
        $id = $this->getQuery('id');
        $adapter = $this->_getAdapter();
        $userData = $adapter->query("SELECT activity_huiju_user.* FROM activity_huiju_user WHERE activity_huiju_user.id = ?", array($id))->current();

        $vm = new ViewModel(array(
            'userData' => $userData,
        ));

        $vm->setTerminal(true);

        return $vm;*/
    }

    public function checkCashAction () {
        if ($_GET['end'] != 456) {
            echo 66;
            exit;
        }

        //uid 39215 uid 44678 uid 57016 uid 102948 uid 144968

        $arr = array(
            39215,
            44678,
            57016,
            102948,
            144968,
        );

        $crash_id = array(19, 21);

        $crash = new Coupon();
        foreach ($arr as $v) {
            foreach ($crash_id as $cid) {
                $m = $crash->addCashcoupon($v, $cid, 0, 4, 0, '荟聚活动4.20');
                var_dump($m);
                echo '<br />';
            }
        }

        echo 45;
        exit;

    }

    public function updateJiFenAction() {

        if (!$_GET['start']) {
            exit;
        }

        $uid = (int)$this->getQuery('uid'); //10019
        $order_sn = (int)$this->getQuery('order_sn'); //167439

        $Integral = new Integral();

        $orderInfo = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));

        if (!$orderInfo) {
            echo 45;
            exit;
        }

        $coupon_name = $orderInfo->coupon_name;
        $city = $orderInfo->order_city;
        $price =  $orderInfo->coupon_unit_price;
        $good_id = $orderInfo->coupon_id;

        $res = $Integral->useGood($uid, $good_id, $price, $city , $coupon_name , $order_sn);

        var_dump($res);
        exit;

    }

    //异常用户 使用且 已退款
    public function exrPlayAction() {

        if ($_GET['view'] != 1) {
            exit;
        }

        $uid = $this->getQuery('uid'); //50328
        $code_id = $this->getQuery('code_id');
        $order_sn = $this->getQuery('order_sn');//158286

        if (!$uid || !$code_id) {
            return $this->_Goto('参数');
        }


        //更改订单 状态
        $s1 = $this->_getPlayOrderInfoTable()->update(array('pay_status' => 5, 'use_number' => 2, 'back_number' => 0, 'use_dateline' => 1458976806), array('order_sn' => $order_sn));

        //更改使用码 状态

        $s2 = $this->_getPlayCouponCodeTable()->update(array('back_money' => 0, 'use_datetime' => 1458976806, 'status' => 1), array('order_sn' => $order_sn));

        //更改用户 余额

        $s3 = $this->_getPlayAccountTable()->update(array('now_money' => 0, 'can_back_money' => 0, 'total_money_flow' => 0), array('uid' => $uid));

        //用户记录 去掉
        $s4 = $this->_getPlayAccountLogTable()->update(array('flow_money' => 0, 'status' => 0, 'surplus_money' => 0), array('uid' => $uid));


        var_dump($s1, $s2, $s3, $s4);

        exit;
        //

    }

    public function bckAction () {
        if ($_GET['view'] != 1) {
            exit;
        }

        $code_id = $this->getQuery('code_id'); //175841, 187278

        if (!$code_id) {
            return $this->_Goto('参数');
        }

        $codeData = $this->_getPlayCouponCodeTable()->get(array('id' => $code_id));

        if (!$codeData) {
            return $this->_Goto('$codeData');
        }

        $order_sn = $codeData->order_sn;

        $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));

        if (!$orderData) {
            return $this->_Goto('$orderData');
        }

        $back_money = $codeData->back_money;

        /*$s5 = $this->_getPlayCouponCodeTable()->update(array('back_money' => $back_money), array('id' => $code_id));

        if (!$s5) {
            //return $this->_Goto('$s5');
        }*/

        $uid = $orderData->user_id;

        $coupon_name = $orderData->coupon_name;
        //$order_sn = 171626;
        //$code_id = 187278;

        $account = new \library\Service\User\Account();
        $rec = $account->recharge($uid, $back_money, 1, '退款'.$coupon_name, 1, $order_sn);

        $s1 = 0;
        $adapter = $this->_getAdapter();
        if($rec){ //如果没有用账户付款 且往账号充钱了
            $s1 = $adapter->query("INSERT INTO play_order_back_tmp (id, order_sn, code_id, dateline, last_dateline, status) VALUES (NULL, ?, ?, ?, ?, ?)", array($order_sn, $code_id, time(), time(), 1))->count();
        }

        var_dump($rec, $s1);

        exit;

    }

    //导出游玩地
    public function outPlaceAction() {
        if (!isset($_GET['start'])) {
            echo 2;
            exit;
        }

        $Out = new OutPut();
        $file_name = '游玩地.csv';
        $head = array(
            'id',
            '名称',
            '地址',
            '城市',
            '状态',
        );

        $content = array();

        $shopData = $this->_getPlayShopTable()->fetchAll();

        foreach ($shopData as $shop) {
            $content[] = array(
                $shop->shop_id,
                $shop->shop_name,
                $shop->shop_address,
                $shop->shop_city,
                ($shop->shop_status == 0) ? '正常' : '关闭',
            );
        }


        $Out->out($file_name, $head, $content);

        exit;


    }

    //todo 处理智游宝 退款的

    public function zybMakeAction() {


        if (!$_GET['start']) {
            echo 34;
            exit;
        }
        $Adapter = $this->_getAdapter();

        $sql = "select * from play_zyb_info";

        $result = $Adapter->query($sql, array());

        if ($result->count()) {

            foreach ($result as $res) {
                if ($res['status'] == 3) {
                    $s3 = $Adapter->query("update play_zyb_info set status = 4, back_time = ? WHERE order_sn = ? AND code_id = ?", array(time(), $res['order_sn'], $res['code_id']))->count();
                    var_dump($res['order_sn'],$s3);
                    echo '<br />';
                }

                if ($res['status'] == 2 && $res['order_sn'] == 174474) {
                    $s2 = $Adapter->query("update play_zyb_info set status = 5 WHERE order_sn = ? AND code_id = ?", array($res['order_sn'], $res['code_id']))->count();
                    var_dump($res['order_sn'],$s2);
                    echo '<br />';
                }
            }
        }
        exit;

    }

    public function updateOrderTimeAction() {

        if (!$_GET['start']) {
            echo 'end';
            exit;
        }

        $order_list = array(
            53059, //2015-12-18 22:45:39 1450449939
            48488 //2015-12-28 11:01:09 1450407669
        );

        foreach ($order_list as $order_sn) {
            $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));

            if (!$orderData || $orderData->pay_status < 2 || $orderData->order_status == 0) {
                var_dump($order_sn);
                echo '分类1';
                echo '<br />';
            }

            $codeData = $this->_getPlayCouponCodeTable()->fetchAll(array('order_sn' => $order_sn));

            foreach ($codeData as $code) {
                if ($_GET['end']) {
                    if ($order_sn == 53059) {
                        $res = $this->_getPlayCouponCodeTable()->update(array('back_time' => 1450449939), array('id' => $code['id']));
                    } elseif (48488) {
                        $res = $this->_getPlayCouponCodeTable()->update(array('back_time' => 1450407669), array('id' => $code['id']));
                    }
                } else {
                    $res = $this->_getPlayCouponCodeTable()->update(array('back_time' => 1460476800), array('id' => $code['id']));
                }
                var_dump($res);
            }
        }
        exit;
    }


}
