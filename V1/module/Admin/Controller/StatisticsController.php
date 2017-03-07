<?php

namespace Admin\Controller;

use Deyi\GetCacheData\CityCache;
use Deyi\JsonResponse;
use Deyi\Paginator;
use Deyi\Account\Account;
use Deyi\OutPut;
use library\Service\System\Cache\RedCache;
use Zend\View\Model\ViewModel;

class StatisticsController extends BasisController
{
    use JsonResponse;


    //统计页面
    public function indexAction()
    {


        //每月用户对比 每月订单对比 每月流水对比 总额未显示

        $users = RedCache::fromCacheData("t:users", function () {
            return $this->getUser();
        }, 86400*5);

        $orders = RedCache::fromCacheData("t:orders", function () {
            return $this->getorders();
        }, 86400*5);

        $api_num = RedCache::fromCacheData("t:api_num", function () {
            return $this->apiNum();
        }, 86400);


        return array(
            'users' => $users,
            'orders' => $orders,
            'api_num' => $api_num
        );
    }

    //每日api接口访问量
    public function apiNum()
    {
        $times = array();//时间段 存储每个时间段 字符串
        $oneday_start = strtotime(date('Y-m-d 00:00:00'));
        $oneday_end = strtotime(date('Y-m-d 23:59:00'));
        $times['start'][] = $oneday_start;
        $times['end'][] = $oneday_end;
        //最近六天 开始结束时间
        for ($i = 0; $i < 10; $i++) {
            $oneday_start -= 86400;
            $oneday_end -= 86400;
            $times['start'][] = $oneday_start;
            $times['end'][] = $oneday_end;
        }
        $data = array();
        $count = count($times['start']) - 1;
        $m = new \MongoClient('mongodb://127.0.0.1:27017');
        $mongoDB = $m->wft_accesslog;
        for ($k = $count; $k >= 0; $k--) {
            $data['time'][] = date('Y-m-d', $times['start'][$k]);
            $data['request_count'][] = $mongoDB->log_data->find(array("dateline" => array('$gt' => $times['start'][$k], '$lte' => $times['end'][$k])))->count();
        }
        return json_encode($data);
    }

    //统计每月用户数 最近六个月
    public function getUser()
    {
        $db = $this->_getAdapter();
        $times = array();//时间段 存储每个时间段 字符串
        $now_year = date('Y', time());
        $now_month = date('m', time()) + 1;

        //最近六天 开始结束时间
        for ($i = 0; $i < 12; $i++) {
            $now_month = $now_month - 1;
            if ($now_month > 0) {
                $top = "{$now_year}-{$now_month}-1";
                $last = "{$now_year}-{$now_month}-" . date("t", strtotime($top));
                $times['start'][] = $top;
                $times['end'][] = $last;
            } else {
                $now_year -= 1;
                $now_month = 12;
                $top = "{$now_year}-{$now_month}-1";
                $last = "{$now_year}-{$now_month}-" . date("t", strtotime($top));
                $times['start'][] = $top;
                $times['end'][] = $last;
            }
        }

        $data = array();

        $count = count($times['start']) - 1;

        for ($k = $count; $k >= 0; $k--) {
            $data['time'][] = date('Y-m', strtotime($times['start'][$k]));
            $data['order_count'][] = $db->query("select count(*) as a from play_order_info WHERE  dateline>? and dateline<? AND  pay_status>=2", array(strtotime($times['start'][$k]), strtotime($times['end'][$k])))->current()->a;
            $res = $db->query("select count(*) as c from play_user WHERE  phone!=''   AND dateline>? and dateline<? ", array(strtotime($times['start'][$k]), strtotime($times['end'][$k])))->current();
            $data['user_count'][] = (int)$res->c;
        }

        return json_encode($data);
    }

    //统计每月销售额与订单量
    public function getorders()
    {
        $db = $this->_getAdapter();
        $times = array();//时间段 存储每个时间段 字符串
        $now_year = date('Y', time());
        $now_month = date('m', time()) + 1;

        //最近六个月 开始结束时间
        for ($i = 0; $i < 12; $i++) {
            $now_month = $now_month - 1;
            if ($now_month > 0) {
                $top = "{$now_year}-{$now_month}-1";
                $last = "{$now_year}-{$now_month}-" . date("t", strtotime($top));
                $times['start'][] = $top;
                $times['end'][] = $last;
            } else {
                $now_year -= 1;
                $now_month = 12;
                $top = "{$now_year}-{$now_month}-1";
                $last = "{$now_year}-{$now_month}-" . date("t", strtotime($top));
                $times['start'][] = $top;
                $times['end'][] = $last;
            }
        }

        $data = array();

        $count = count($times['start']) - 1;

        for ($k = $count; $k >= 0; $k--) {
            $data['time'][] = date('Y-m', strtotime($times['start'][$k]));
            $res = $db->query("select SUM(real_pay) as a,SUM(account_money) as b from play_order_info WHERE  dateline>? and dateline<? AND  pay_status>=2", array(strtotime($times['start'][$k]), strtotime($times['end'][$k])))->current();
            $data['order_money_count'][] = bcadd($res->a, $res->b, 2);
        }

        return json_encode($data);

    }

    public function tjgoodsAction()
    {
        $m = new \MongoClient('mongodb://127.0.0.1:27017');
        $mongoDB = $m->wft_accesslog;
        $goods_log = $mongoDB->t_goods_log;
        $page = (int)$this->getQuery('p', 1);
        $title = trim($this->getQuery('goods', ''));
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;

        $city = $this->chooseCity();

        $id = $this->getQuery('goods_id');

        $where['city'] = $city;
        if ($title !== '') {
            $where['title'] = new \MongoRegex("/{$title}/");
        }

        if ($id) {
            $where['id'] = (int)$id;
        }

        $order = ['_id' => -1];

        $cursor = $goods_log->find($where)->limit($pageSum)->skip($start)->sort($order);
        $count = $goods_log->find($where)->count();


        //创建分页
        $url = '/wftadlogin/statistics/tjgoods';
        $paginator = new Paginator($page, $count, $pageSum, $url);

        //获取所有积分
        return array(
            'data' => $cursor ?: [],
            'pagedata' => $paginator->getHtml(),
            'filtercity' => CityCache::getFilterCity($city),
            'citys' => $this->getAllCities()
        );
    }

    public function tjplaceAction()
    {
        $m = new \MongoClient('mongodb://127.0.0.1:27017');
        $mongoDB = $m->wft_accesslog;
        $place_data = $mongoDB->t_places_log;
        $page = (int)$this->getQuery('p', 1);
        $title = trim($this->getQuery('title', ''));
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;
        $city = $this->chooseCity();

        $id = $this->getQuery('id');

        $where['city'] = $city;
        if ($title !== '') {
            $where['shop_name'] = new \MongoRegex("/{$title}/");
        }
        if ($id) {
            $where['id'] = (int)$id;
        }

        $order = ['_id' => -1];

        $cursor = $place_data->find($where)->limit($pageSum)->skip($start)->sort($order);
        $count = $place_data->find($where)->count();

        //创建分页
        $url = '/wftadlogin/statistics/tjplace';
        $paginator = new Paginator($page, $count, $pageSum, $url);

        //获取所有积分
        return array(
            'data' => $cursor ?: [],
            'pagedata' => $paginator->getHtml(),
            'filtercity' => CityCache::getFilterCity($city),
            'citys' => $this->getAllCities()
        );
    }

    public function tjactivityAction()
    {
        $m = new \MongoClient('mongodb://127.0.0.1:27017');
        $mongoDB = $m->wft_accesslog;
        $activity_data = $mongoDB->t_activity_log;
        $page = (int)$this->getQuery('p', 1);
        $title = trim($this->getQuery('ac_name', ''));
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;
        $city = $this->chooseCity();

        $id = $this->getQuery('ac_id');

        $where['city'] = $city;
        if ($title !== '') {
            $where['ac_name'] = new \MongoRegex("/{$title}/");
        }
        if ($id) {
            $where['id'] = (int)$id;
        }

        $order = ['_id' => -1];

        $cursor = $activity_data->find($where)->limit($pageSum)->skip($start)->sort($order);
        $count = $activity_data->find($where)->count();


        //创建分页
        $url = '/wftadlogin/statistics/tjactivity';
        $paginator = new Paginator($page, $count, $pageSum, $url);

        //专题 展示类型
        $viewType = array(
            '1' => '混合, 游玩地优先',
            '2' => '混合, 商品优先',
            '3' => '仅游玩地',
            '4' => '仅商品',
            '5' => '混合, 活动优先',
            '6' => '仅活动',
        );

        //专题 类型
        $type = array(
            '1' => '一元手慢无',
            '2' => '周末去哪儿',
            '3' => '一般专题',
        );

        //获取所有积分
        return array(
            'data' => $cursor ?: [],
            'pagedata' => $paginator->getHtml(),
            'filtercity' => CityCache::getFilterCity($city),
            'viewtype' => $viewType,
            'type' => $type,
            'citys' => $this->getAllCities()
        );
    }

    public function tjexcerciseAction()
    {
        $m = new \MongoClient('mongodb://127.0.0.1:27017');
        $mongoDB = $m->wft_accesslog;
        $excercise_data = $mongoDB->t_excercise_log;
        $page = (int)$this->getQuery('p', 1);
        $title = trim($this->getQuery('title', ''));
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;
        $city = $this->chooseCity();

        $id = $this->getQuery('id');

        $where['city'] = $city;
        if ($title !== '') {
            $where['title'] = new \MongoRegex("/{$title}/");
        }
        if ($id) {
            $where['id'] = (int)$id;
        }

        $order = ['_id' => -1];

        $cursor = $excercise_data->find($where)->limit($pageSum)->skip($start)->sort($order);
        $count = $excercise_data->find($where)->count();


        //创建分页
        $url = '/wftadlogin/statistics/tjexcercise';
        $paginator = new Paginator($page, $count, $pageSum, $url);

        //获取所有积分
        return array(
            'data' => $cursor ?: [],
            'pagedata' => $paginator->getHtml(),
            'filtercity' => CityCache::getFilterCity($city),
            'citys' => $this->getAllCities()
        );
    }

}