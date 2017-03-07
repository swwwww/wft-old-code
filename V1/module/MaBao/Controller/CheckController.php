<?php

namespace MaBao\Controller;

use Deyi\BaseController;
use Deyi\JsonResponse;
use Deyi\WriteLog;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Deyi\SendMessage;
use Zend\View\Model\ViewModel;
use Deyi\Paginator;
use Deyi\OutPut;
use Zend\EventManager\EventManagerInterface;

class CheckController extends AbstractActionController
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
            if ($_SERVER['REQUEST_URI'] != '/mabao/check/login') { //排除登陆界面
                if (isset($_COOKIE['user'])) {
                    $hash_data = json_encode(array('user' => $_COOKIE['user'], 'id' => $_COOKIE['id'], 'group' => $_COOKIE['group']));
                    $token = hash_hmac('sha1', $hash_data, $this->_getConfig()['token_key']);
                    if ($token == $_COOKIE['token']) {
                    } else {
                        header('Location: /mabao/check/login');
                        exit;
                    }
                } else {
                    header('Location: /mabao/check/login');
                    exit;
                }
            }

        }, 100);
    }


    //todo 首页
    public function indexAction()
    {
        $page = (int)$this->getQuery('p', 1);
        $like = $this->getQuery('k', '');
        $uid = $this->getQuery('uid', '');
        $phone = $this->getQuery('phone', '');
        $status = $this->getQuery('status', '');
        $baby = $this->getQuery('baby', '');
        $pagesum = 20;
        $where = array();
        if ($like) {
            $where['play_user.username like ?'] = '%' . $like . '%';
        }
        if ($uid) {
            $where['play_user.uid'] = $uid;
        }
        if ($phone) {
            $where['play_user.phone'] = $phone;
        }
        if ($status !== "" && $status < 4) {
            $where['play_mabaobao.check_status'] = $status;
        }

        if ($baby) {
            $where['play_mabaobao.username like ?'] = '%' . $baby . '%';
        }

        $start = ($page - 1) * $pagesum;
        $order = array('id' => 'DESC');

        $data = $this->_getPlayMaBaoBaoTable()->getAdminMaBaoBaoList($start, $pagesum, array(), $where, $order);
        //获得总数量
        $count = $this->_getPlayMaBaoBaoTable()->getAdminMaBaoBaoList(0, 0, array(), $where, $order)->count();
        //创建分页
        $url = '/mabao/check/index';
        $paginator = new Paginator($page, $count, $pagesum, $url);

        $vm = new viewModel(array(
            'data' => $data,
            'pagedata' => $paginator->getHtml(),
        ));
        $vm->setTerminal(true);
        return $vm;
    }

    public function infoAction()
    {

        $uid = (int)$this->getQuery('uid');
        $data = $this->_getPlayMaBaoBaoTable()->get(array('uid' => $uid));
        $imgData = $this->_getPlayAttachTable()->fetchAll(array('uid' => $uid, 'use_id' => $data->id, 'use_type' => 'mabaobao'));
        $vm = new viewModel(array(
            'imgData' => $imgData,
            'url' => $this->_getConfig()['url'],
        ));
        $vm->setTerminal(true);
        return $vm;
    }

    public function changeAction()
    {
        $type = (int)$this->getQuery('type');
        $uid = (int)$this->getQuery('uid');
        $maData = $this->_getPlayMaBaoBaoTable()->get(array('uid' => $uid));
        if (!$maData) {
            return $this->_Goto('非法操作');
        }

        if ($type == 1) {
            $status = $this->_getPlayMaBaoBaoTable()->update(array('check_status' => 1), array('uid' => $uid));
            if (!$status) {
                return $this->_Goto('失败');
            }

            // todo 更改 用户的孩子信息
            $user_data = array(
                'child_sex' => ($maData->sex == 'boy') ? 1 : 2,
                'child_old' => $maData->birthday,
            );
            $this->_getPlayUserTable()->update($user_data, array('uid' => $uid));

            return $this->_Goto('成功');


        } elseif ($type == 2) {
            $status = $this->_getPlayMaBaoBaoTable()->update(array('check_status' => -1), array('uid' => $uid));
            if ($status) {
                $user_data = $this->_getPlayUserTable()->get(array('uid' => $uid));
                $message = <<<THML
您好，您申请的羊宝宝报名材料审核不通过，请重新按要求提交资料 参加活动~
THML;
                if ($user_data->phone) {
                    SendMessage::Send($user_data->phone, $message);
                }
                return $this->_Goto('成功');
            } else {
                return $this->_Goto('失败');
            }
        } elseif ($type == 3) {
            $status = $this->_getPlayMaBaoBaoTable()->update(array('check_status' => 0), array('uid' => $uid));
            if ($status) {
                return $this->_Goto('成功');
            } else {
                return $this->_Goto('失败');
            }
        } else {
            return $this->_Goto('非法操作');
        }

    }

    public function loginAction()
    {
        $user = $this->getPost('username');
        $pwd = $this->getPost('pwd');
        $pwd = md5($pwd);
        if ($user and $pwd) {

            $data = $this->_getPlayAdminTable()->fetchAll(array('admin_name' => $user))->current();

            $password = md5($pwd . $data->salt);

            if ($data && $data->password === $password) {
                if ($data->status == 0) {
                    exit("<h1>账号已禁用</h1>");
                }


                //生成验证信息
                $hash_data = json_encode(array('user' => $user, 'id' => $data->id, 'group' => $data->group));
                $token = hash_hmac('sha1', $hash_data, $this->_getConfig()['token_key']);

                setcookie('user', $user, time() + 28800, '/');
                setcookie('token', $token, time() + 28800, '/');
                setcookie('group', $data->group, time() + 28800, '/');
                setcookie('id', $data->id, time() + 28800, '/');
                header('Location: /mabao/check/index');
            }

        }

        $v = new ViewModel();
        $v->setTerminal(true);
        return $v;
    }

    //todo 发送卡券
    private function addOrder($uid, $coupon_id)
    {

        if (!$uid or !$coupon_id) {
            return false;
        }
        $user_data = $this->_getPlayUserTable()->get(array('uid' => $uid));
        $coupon_data = $this->_getPlayCouponsTable()->get(array('coupon_id' => $coupon_id));

        //todo 更改卡券购买数量
        $m = $this->_getPlayCouponsTable()->update(
            array('coupon_buy' => new Expression("coupon_buy+1")),
            array('coupon_id' => $coupon_id)
        );

        //todo 插入订单
        if ($m) {
            $status = $this->_getPlayOrderInfoTable()->insert(array(
                'coupon_id' => $coupon_id,
                'order_status' => 1,
                'pay_status' => 2,
                'user_id' => $uid,
                'username' => $user_data->username,
                'real_pay' => 0,
                'voucher' => $coupon_data->coupon_price,  //代金券金额
                'voucher_id' => 0,  //代金券id
                'coupon_unit_price' => $coupon_data->coupon_price,
                'coupon_name' => $coupon_data->coupon_name,
                'shop_name' => $coupon_data->coupon_marketname,
                'shop_id' => $coupon_data->coupon_marketid,  //coupon_marketid  店铺id
                'buy_number' => 1,
                'use_number' => 0,
                'back_number' => 0,
                'account' => '',
                'account_type' => 'alipay',
                'buy_name' => $user_data->username,
                'buy_phone' => $user_data->phone,
                'dateline' => time(),
                'use_dateline' => 0,
            ));
        } else {
            return false;
        }

        //todo  如果失败 回退
        if (!$status) {
            $this->_getPlayCouponsTable()->update(
                array('coupon_buy' => new Expression("coupon_buy-1")),
                array('coupon_id' => $coupon_id)
            );
            return false;
        }
        $order_sn = $this->_getPlayOrderInfoTable()->getlastInsertValue();

        //todo 生成卡券密码
        $password = sprintf("%03d", 1) . mt_rand(1000, 9999);
        $this->_getPlayCouponCodeTable()->insert(array(
            'order_sn' => $order_sn,
            'sort' => 1,
            'status' => 0,
            'password' => $password,
            'use_store' => 0,
            'use_datetime' => 0
        ));
        $tou = $this->_getPlayCouponCodeTable()->getlastInsertValue();
        $message = <<<THML
【得意生活】你已经成功参加“寻找江城马宝宝”活动，兑换码为: {$tou}{$password}，
领券游玩日期3月30日-6月30日，周末仅后湖、光谷、泛海店可使用。周末全天不兑券,工作日兑券.
THML;
        if ($user_data->phone) {
            SendMessage::Send($user_data->phone, $message);
        }

        //todo 返回生成的订单 信息
        return true;
    }

    public function deleteAction() {

        $id = $this->getQuery("id");
        $result = $this->_getPlayMaBaoBaoTable()->delete(['id' => $id]);
        if ($result) {
            return $this->_Goto('操作成功', '/mabao/check/index');
        }
    }

    public function checkSomeAction() {
        $adapter = $this->_getAdapter();
        $sql = "select count(uid), uid from play_mabaobao group by uid having count(uid) >1";
        $date = $adapter->query($sql, array());

        var_dump($date->count());
        if ($date->count()) {
            foreach ($date as $val) {
                var_dump($val);
            }
        }
        exit;
    }

    public function outDataAction() {

        $status = $this->getQuery('status', 0);

        if (!$status) {
            echo 56;
            exit;
        }

        $head = array(
            'uid',
            '用户名称',
            '参与宝宝名称',
            '参与手机号',
            '是否使用',
        );

        $file_name = '马宝宝数据.csv';



        $data = $this->_getPlayMaBaoBaoTable()->getAdminMaBaoBaoList(0, 10000000, array(), array('check_status >= ?' => 0), array());

        $content = array();

        foreach ($data as $value) {
            $content[] = array(
                $value->uid,
                $value->u_username,
                $value->username,
                $value->phone,
                ($value->check_status == 2) ? '已使用' : '待使用'
            );
        }

        $outPut = new OutPut();

        $outPut->out($file_name, $head, $content);

        exit;


    }


}
