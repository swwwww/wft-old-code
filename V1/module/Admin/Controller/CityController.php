<?php

namespace Admin\Controller;

use Deyi\Account\OrganizerAccount;
use Deyi\Alipay\Alipay;
use Deyi\Coupon\Good;
use Deyi\Integral\Integral;
use Deyi\JsonResponse;
use Deyi\OrderAction\OrderPay;
use Deyi\OutPut;
use Deyi\Paginator;
use Deyi\Account\Account;
use Deyi\Coupon\Coupon;
use Deyi\OrderAction\OrderBack;
use Deyi\Seller\Seller;
use Deyi\Unionpay\Unionpay;
use Deyi\WeiSdkPay\WeiPay;
use Deyi\WeiXinPay\WeiXinPayFun;
use Deyi\ZybPay\ZybPay;
use library\Fun\M;
use library\Service\System\Cache\KeyNames;
use library\Service\System\Cache\RedCache;
use Zend\Db\Sql\Expression;
use Deyi\Validation;
use Deyi\OrderAction\OrderExcerciseBack;
use Deyi\ImageProcessing;
use Zend\View\Model\ViewModel;

class CityController extends BasisController
{
    use JsonResponse;
    use OrderBack;

    public function indexAction()
    {


        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;

        $data =  $this->_getPlayCityTable()->fetchLimit($start, $pageSum, array());
        //获得总数量
        $count = $this->_getPlayCityTable()->fetchCount();
        //创建分页
        $url = '/wftadlogin/city';
        $pagination = new Paginator($page, $count, $pageSum, $url);

        return array(
            'data' => $data,
            'pageData' => $pagination->getHtml(),
        );
    }

    public function newAction() {
        $id = (int)$this->getQuery('id',0);
        $data =  $this->_getPlayCityTable()->fetchLimit(0,1,[],['id'=>$id])->current();

        return array('data' => $data,);
    }

    public function saveAction() {
        $id = (int)$this->getPost('id',0);
        $city = $this->getPost('city');
        $city_name = $this->getPost('city_name');
        $img_url = $this->getPost('cover','');
        $type = $this->getPost('type',1);
        $address = $this->getPost('address','');
        $username = $this->getPost('username','');
        $contact = $this->getPost('contact','');
        $phone = $this->getPost('phone','');
        $integral = $this->getPost('integral',0);
        $return = $this->getPost('return',0);
        $is_close = (int)$this->getPost('is_close',0);
        $city_number = $this->getPost('city_number','');

        if (!$city || !$city_name) {
            return $this->_Goto('请检查下是否有未填写的');
        }

        if (!$city_number) {
            return $this->_Goto('请检查下城市编号');
        }

        $data = array(
            'city' => $city,
            'city_name' => $city_name,
            'city_number' => $city_number,
            'city_img' => $img_url,
            'type' =>$type,
            'address' =>$address,
            'username' =>$username,
            'contact' =>$contact,
            'phone' =>$phone,
            'integral' =>$integral,
            'return' =>$return,
        );

        if($is_close === 0){
            $data['is_close'] = 0;
        }elseif($is_close === 1){
            $data['is_close'] = 1;
        }elseif($is_close === 2){
            $data['is_close'] = 2;
        }

        if($id){
            $status = $this->_getPlayCityTable()->update($data,['id'=>$id]);
        }else{
            $status = $this->_getPlayCityTable()->insert($data);
        }
        return $this->_Goto($status ? '成功' : '失败', '/wftadlogin/city');
    }

    //热门城市
    public function hotAction(){
        $id = $this->getQuery('id');
        $h = (int)$this->getQuery('h');
        $status = $this->_getPlayCityTable()->update(['is_hot'=>$h],['id'=>$id,'city <> ?'=>1]);
        return $this->_Goto($status ? '成功' : '失败', '/wftadlogin/city');
    }

    //是否关闭
    public function closeAction(){
        $id = (int)$this->getQuery('id');
        $c = (int)$this->getQuery('c');
        $status = $this->_getPlayCityTable()->update(['is_close'=>$c],['id'=>$id]);
        return $this->_Goto($status ? '成功' : '失败', '/wftadlogin/city');
    }

    //处理一个电话号码对应 多个uid的
    public function zzAction()
    {

        $sql = "SELECT phone, COUNT(phone) FROM play_user WHERE phone > 0 GROUP BY phone HAVING COUNT(phone) > 1";
        $result = M::getAdapter()->query($sql, array());

        $i = 0;
        $m = 0;
        $to_list = array();
        foreach ($result as $value) {
            $m ++;
            $userData = M::getPlayUserTable()->fetchAll(array('phone' => $value['phone']), array('uid' => 'DESC'));

            if ($userData->count() <= 1) {
                echo $value['phone']. ' 是异常';
                echo '<br />';
                continue;
            }

            echo '开始处理  电话 '. $value['phone'] . '结果是 ：';
            echo '<br />';
            if ($value['phone'] == '13397123062') {
                continue;
            }

            $top = 0;
            $v = 0;
            $first = 0;
            foreach ($userData as $user) {
                $orderData = M::getPlayOrderInfoTable()->fetchAll(array('user_id' => $user->uid));
                $accountData = M::getPlayAccountTable()->get(array('uid' => $user->uid));
                $accountLogData = M::getPlayAccountLogTable()->fetchAll(array('uid' => $user->uid));
                $money = $accountData ? $accountData->now_money : '无';
                echo '　　    uid '. $user->uid.' 状态是'. $user->status. ' 其订单有 '. $orderData->count(). "单  账户流水记录 ". $accountLogData->count(). "条 账户金额 ". $money;
                echo '<br />';

                //todo 处理无订单 无 充值流水的
                $to_list[$value['phone']][] = array(
                    'uid' => $user->uid,
                    'status' => $user->status,
                    'order_count' => $orderData->count(),
                    'account_count' => $accountLogData->count(),
                );

                if (!$orderData->count() && !$accountLogData->count()) {

                    if (!$v) {
                        $first = $user->uid;
                    } else {
                        M::getPlayUserTable()->update(array('status' => 0, 'phone' => ''), array('uid' => $user->uid, 'status' => 1));
                    }

                } else {
                    $top ++;
                    if ($first && $top == 1) {
                        M::getPlayUserTable()->update(array('status' => 0, 'phone' => ''), array('uid' => $first, 'status' => 1));
                    }
                }

                $v ++;
            }

            if ($top > 1) {
                echo '重大异常';
                echo '<br />';
            }

            $i ++;
        }


        echo '共有记录 '. $m. '条 处理'. $i. '条';
        exit;
    }

    public function setAccountAction()
    {
        $st = M::getPlayAccountLogTable()->update(array('surplus_money' => 299.80), array('id' => 28999));
        var_dump($st);
        echo 'end';
        exit;
    }


}
