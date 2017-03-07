<?php
/**
 * 权限控制模块
 * Date: 15-12-9
 * Time: 上午10:57
 */

namespace Admin\Controller;

use Deyi\GetCacheData\CityCache;
use Deyi\GetCacheData\CouponCache;
use Deyi\JsonResponse;
use Deyi\Paginator;
use Zend\View\Model\ViewModel;

class QualifyController extends BasisController
{
    use JsonResponse;

    public function indexAction()
    {
        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;


        //资格获取方式
        $qualify_type = CouponCache::get_qualify_type();

        $begin_time = $this->getQuery('begin_time', 0);
        $end_time = $this->getQuery('end_time', 0);
        $use_stime = $this->getQuery('use_stime', 0);
        $use_etime = $this->getQuery('use_etime', 0);
        $gettype = (int)$this->getQuery('gettype', 0);
        $use_status = (int)$this->getQuery('use_status', 0);
        $month = (int)$this->getQuery('month', 0);
        $user = $this->getQuery('user', '');
        $good = $this->getQuery('good', '');
        $city = $this->chooseCity();

        $where = [];

        if((int)$begin_time > 0){//待发放
            $where['create_time > ?'] = strtotime($begin_time);
        }
        if((int)$end_time > 0){//正在发放
            $where['create_time < ?'] = strtotime($end_time)+24*3600;
        }
        if((int)$use_stime > 0){//已结束
            $where['pay_time > ?'] = strtotime($use_stime);
        }
        if((int)$use_etime > 0){//
            $where['pay_time < ?'] = strtotime($use_etime)+24*3600;
        }
        if((int)$gettype > 0){//
            $where['give_type'] = $gettype;
        }
        if((int)$use_status > 0){//
            $where['play_qualify_coupon.status'] = $use_status;
        }

        if((int)$use_status === 3){
            unset($where['play_qualify_coupon.status']);
            $where['pay_time > ?'] = 0;
        }

        if((int)$use_status === 1){
            unset($where['play_qualify_coupon.status']);
            $where['pay_time'] = 0;
        }

        if((int)$month === 1){//
            $where['create_time > ?' ] = time() - 30*24*3600;
            $where['create_time < ?' ] = time();
        }
        if((int)$month === 2){//
            $where['create_time > ?' ] = time() - 90*24*3600;
            $where['create_time < ?' ] = time();
        }
        if((int)$month === 3){//
            $where['create_time > ?' ] = time() - 180*24*3600;
            $where['create_time < ?' ] = time();
        }
        if($user !== ''){//ID/手机号/用户名
            $where[]='(play_qualify_coupon.username like "%'.$user.'%" or play_qualify_coupon.uid = '.(int)$user.' or play_qualify_coupon.phone = "'.$user.'")';
        }
        if($good !== ''){//商品名/商品ID
            $where[]='(play_organizer_game.title like "%'.$good.'%" or (pay_object_id != 0 && pay_object_id = '.(int)$good.'))';
        }
        if(!empty($city)){//
            $where['play_qualify_coupon.city'] = $city;
        }


        $data = $this->_getQualifyTable()->joinUserGoodList(($page-1)*$pageSum, $pageSum, array(), $where, array('uid' => 'DESC'))->toArray();

        $count = $this->_getQualifyTable()->joinUserGoodCount($where);
        $url = '/wftadlogin/qualify';

        $paginator = new Paginator($page, $count, $pageSum, $url);
        $vm = new viewModel(
            array(
                'data' => $data,
                'citys' => $this->getAllCities(),
                'qualify_type' => $qualify_type,
                'filtercity' => CityCache::getFilterCity($city,0,0),
                'pageData' => $paginator->getHtml(),
            )
        );

        return $vm;
    }

    public function recycleAction(){
        $id = (int)($this->getQuery('id', 0));
        $recycle = $this->getQuery('recycle', 0);

        $where['id'] = $id;
        if($this->getAdminCity() != 1){
            $where['city'] = $this->getAdminCity();
        }

        $status = $this->_getQualifyTable()->update(['status' => $recycle], $where);

        if ($status > 0) {
            return $this->_Goto('操作成功');
        }else {
            return $this->_Goto('操作失败');
        }
    }

}