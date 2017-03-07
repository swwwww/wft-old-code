<?php

namespace Web\Controller;


use Deyi\BaseController;
use Deyi\GetCacheData\CityCache;
use Deyi\JsonResponse;
use Deyi\Seller\Seller;
use Zend\Http\Response;
use Zend\View\Model\ViewModel;
use Zend\Mvc\Controller\AbstractActionController;

class UserController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    public function __construct()
    {

    }

    //分销员中心
    public function sellerInfoAction()
    {

        $uid = $_COOKIE['uid'];

        //判断$uid 的合法性
        $Seller = new Seller();
        if (!$uid || !$Seller->isRight($uid)) {
            header("Location: /web/wappay/nindex");
            exit;
        }

        if ($_COOKIE['sel_city'] && in_array($_COOKIE['sel_city'], CityCache::getCities())) {
            $city = $_COOKIE['sel_city'];
        } else {
            $city = '武汉';
        }

        $AccountData = $Seller->getSellerAccount($uid);

        //是否有提现中的
        $have = $this->_getPlayDistributionDetailTable()->get(array('sell_type' => 3, 'sell_status' => 1, 'sell_user_id' => $uid));
        $withdraw_now = $have ? 1 : 2;

        $data = array(
            'account_money' => $AccountData['account_money'],
            'add_up_income' => $AccountData['add_up_income'],
            'not_arrived_income' => $AccountData['not_arrived_income'],
            'withdraw_cash' => $AccountData['withdraw_cash'],
            'can_out' => $Seller->out_money,
            'withdraw_now' => $withdraw_now,
        );

        $vm = new ViewModel(array(
            'data' => $data,
            'uid' => $uid,
            'city' => array_flip(CityCache::getCities())[$city]
           ));

        $vm->setTerminal(true);

        return $vm;

    }

    //分销商品 活动列表
    public function sellGoodsAction()
    {

        $seller_id = intval($this->getQuery('seller_id', 0));//分销员id
        $get_city = $this->getQuery('city', '');

        $Seller = new Seller();

        //判断分销员的非法性
        if (!$Seller->isRight($seller_id)) {
            header("Location: /web/wappay/nindex");
            exit;
        }

        //以连接的城市为主
        if ($get_city && in_array($get_city, array_flip(CityCache::getCities()))) {
            $city = $get_city;
        } else {
            if ($_COOKIE['sel_city'] && in_array($_COOKIE['sel_city'], CityCache::getCities())) {
                $city = array_flip(CityCache::getCities())[$_COOKIE['sel_city']];
            } else {
                $city = 'WH';
            }
        }

        $result = $Seller->getGoodsListCount($city);

        $is_seller = 0; //是否当前用户是分销员
        if ($_COOKIE['uid'] && $Seller->isRight($_COOKIE['uid'])) {
            $is_seller = 1;
        }

        $data = array(
            'count_goods' => $result['count_goods'],
            'count_activity' => $result['count_activity'],
            'seller_id' => $seller_id,
            'is_seller' => $is_seller,
        );

        $vm = new ViewModel(array(
            'data'=> $data,
        ));

        $vm->setTerminal(true);
        return $vm;
    }

}
