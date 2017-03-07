<?php

namespace Shop\Controller;

use Deyi\BaseController;
use Deyi\JsonResponse;
use Deyi\OrderAction\UseCode;
use Deyi\Paginator;
use library\Service\System\Cache\RedCache;
use Deyi\Upload;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Predicate\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    use JsonResponse;
    use UseCode;
    use BaseController;

    public function indexAction()
    {
        return array();
    }

    //请求订单信息
    public function getorderinfoAction()
    {
        $code = $this->getPost('name');

        if (!is_numeric($code)) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '验证码错误'));
        }
        $id = substr($code, 0, -7);
        $password = substr($code, -7);


        //todo  后期验证是否属于此商家

        $message = '';
        $codeData = $this->_getPlayCouponCodeTable()->get(array('password' => $password, 'id' => $id));
        $orderInfo = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $codeData->order_sn));

        if (!$codeData or !$orderInfo) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '验证码错误'));
        }
        if ($orderInfo->order_type == 2) {
            $gameData = $this->_getPlayOrderInfoGameTable()->get(array('order_sn' => $codeData->order_sn));
            $message = "确定使用吗?\n名称：{$orderInfo->coupon_name}\n套系：{$gameData->type_name}";
        } else {
            $message = "确定使用吗?\n名称：{$orderInfo->coupon_name}";
        }
        return $this->jsonResponsePage(array('status' => 1, 'message' => $message));
    }

    //使用卡券
    public function usecodeAction()
    {

        //店铺id
        $store_id = (int)$_COOKIE['id'];
        $type = (int)$_COOKIE['type']; //1 店铺 2活动组织者（商家）
        $code = $this->getPost('name');

        if (!isset($_COOKIE['type'])) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => "请重新登录"));
        }

        $data = $this->UseCode($store_id, $type, $code);

        return $this->jsonResponsePage($data);

    }

    //使用列表
    public function orderAction()
    {
        //店铺id
        $id = (int)$_COOKIE['id'];
        $type = (int)$_COOKIE['type']; //1 店铺 2 活动组织者
        if (!$id) {
            return $this->_Goto('商店不存在');
        }

        if (!isset($type)) {
            return $this->_Goto('请重新登录');
        }

        $page = $this->getQuery('page', 1);
        $row = 1000;
        $offset = ($page - 1) * $row;

        if ($type == 1) {
            $where = array(
                'use_type = ?' => $type,
                'use_store = ?' => $id,
                'status = ?' => 1, //使用
            );
        } else {
            $where = array(
                'play_order_info.shop_id = ?' => $id,
                'status = ?' => 1, //使用
                'play_order_info.order_type = ?' => 2
            );
        }

        $data = $this->_getPlayCouponCodeTable()->getOrderList($offset, $row, array(), $where, array('order_sn' => 'desc'));
        $count = $this->_getPlayCouponCodeTable()->getOrderList(0, 0, array(), $where, array('order_sn' => 'desc'))->count();


        $paginator = new Paginator($page, $count, $row, '/Shop/index/order');
        return array(
            'data' => $data,
            'pagedata' => $paginator->getHtml(),
        );
    }

    public function loginAction()
    {
        $user = $this->getPost('username');
        $pwd = $this->getPost('pwd');
        $type = (int)$this->getPost('type');


        //todo 使用redis 限制错误尝试次数
        if ($type) {
            $key = 'try_number_' . bin2hex($user);
            $try_number = (int)RedCache::get($key);
            if ($try_number) {
                if ($try_number >= 5) {
                    return $this->jsonResponsePage(array('status' => 0, 'message' => '错误次数过多请稍后再试'));
                } else {
                    RedCache::set($key, ($try_number + 1), 360);
                }
            } else {
                RedCache::set($key, 1, 360);
            }
        }

        $pwd = md5($pwd);
        //todo 判断是否为用户手机号登陆   查询用户表 匹配密码   店铺表 取出userbind 登陆成功
        $user_data = false;
        if (is_numeric($user) and strlen($user) == 11) {
            $user_data = $this->_getPlayUserTable()->get(array('phone' => $user, 'password' => $pwd));
            if (!$user_data) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '用户不存在或密码错误'));
            }
        }

        if ($user and $pwd) {
            if ($type == 1) {
                $data = false;
                $shopData = false;
                if ($user_data) {
                    $shopData = $this->_getPlayShopTable()->get(array('bind_uid' => $user_data->uid));
                    if ($shopData) {
                        $data = $this->_getPlayAdminTable()->get(array('admin_name' => $shopData->shop_name));
                    }
                }

                if (!$data) {
                    $data = $this->_getPlayAdminTable()->fetchAll(array('admin_name' => $user, 'password' => $pwd))->current();
                    $shopData = $this->_getPlayShopTable()->get(array('shop_name' => $user));
                }

                if (!$shopData || $shopData->shop_status == -1) {
                    return $this->jsonResponsePage(array('status' => 0, 'message' => '该店铺不存在或密码错误'));
                }

                if ($data) {
                    if ($data->status == 0) {
                        return $this->jsonResponsePage(array('status' => 0, 'message' => '账号已禁用'));
                    }
                    //生成验证信息
                    $hash_data = json_encode(array('user' => $user, 'id' => (int)$data->shop_id, 'group' => (int)$data->group));
                    $token = hash_hmac('sha1', $hash_data, $this->_getConfig()['token_key']);
                    setcookie('type', $type, time() + 28800, '/');
                    setcookie('user', $user, time() + 28800, '/');
                    setcookie('token', $token, time() + 28800, '/');
                    setcookie('group', $data->group, time() + 28800, '/');
                    setcookie('id', $data->shop_id, time() + 28800, '/');
                    return $this->jsonResponsePage(array('status' => 1, 'message' => '登陆成功'));
                } else {
                    return $this->jsonResponsePage(array('status' => 0, 'message' => '用户名或密码错误'));
                }


            } elseif ($type == 2) {

                $data = false;

                if ($user_data) {
                    $data = $this->_getPlayOrganizerTable()->get(array('bind_uid' => $user_data->uid));
                }

                if (!$data) {
                    $data = $this->_getPlayOrganizerTable()->get(array('name' => $user, 'password' => $pwd));
                }

                if (!$data) {
                    return $this->jsonResponsePage(array('status' => 0, 'message' => '活动组织者不存在或密码错误'));
                }

                if ($data) {
                    if ($data->status == 0) {
                        return $this->jsonResponsePage(array('status' => 0, 'message' => '账号已禁用'));
                    }

                    //生成验证信息
                    $hash_data = json_encode(array('user' => $user, 'id' => (int)$data->id, 'group' => 2));
                    $token = hash_hmac('sha1', $hash_data, $this->_getConfig()['token_key']);
                    setcookie('type', $type, time() + 28800, '/');
                    setcookie('user', $user, time() + 28800, '/');
                    setcookie('token', $token, time() + 28800, '/');
                    setcookie('group', 2, time() + 28800, '/');
                    setcookie('id', (int)$data->id, time() + 28800, '/');

                    return $this->jsonResponsePage(array('status' => 1, 'message' => '登陆成功'));
                } else {
                    return $this->jsonResponsePage(array('status' => 0, 'message' => '用户名或密码错误'));
                }
            } else {

                header('Location: /shop/index/login', true);

//                $this->jsonResponsePage(array('status' => 0, 'message' => '登录类型不存在'));
            }
        }
        $v = new ViewModel();
        $v->setTerminal(true);
        return $v;
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


}
