<?php
namespace Application\Model;

use Zend\Db\Sql\Predicate\In;
use Zend\Db\Sql\Predicate\NotIn;
use Zend\Db\Sql\Select;

class PlayCouponsTable extends BaseTable
{
    //获取卡券列表
    public function  getCouponsList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function (Select $select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_coupons_linker', 'play_coupons_linker.coupon_id=play_coupons.coupon_id', array(), 'left');
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order($order);
            $select->GROUP('coupon_id');
        });
        return $data;
    }









    //v3 搜索卡券
    public function  getlikeList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function (Select $select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_coupons_linker', 'play_coupons_linker.coupon_id=play_coupons.coupon_id', array(), 'left');
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order($order);
            $select->GROUP('coupon_id');
        });
        return $data;
    }

    //v3 api 接口
    public function getApiCouponsList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_admin', 'play_admin.id = play_coupons.editor_id', array('admin_name', 'image'), 'left');
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }

    /**
     * 获取关联店铺下所有卡券
     * @param $shop_ids |jsonString
     * @param $this_coupon_id |int
     * @param $this_coupon_id |string
     * @return array
     */
    public function getShopCouponList($shop_ids, $this_coupon_id,$url)
    {
        $res = $this->tableGateway->select(function (Select $select) use ($shop_ids, $this_coupon_id) {
            $select->columns(array('coupon_id', 'coupon_name', 'coupon_price', 'coupon_originprice', 'coupon_buy', 'coupon_total','coupon_thumb','coupon_vir','coupon_join', 'coupon_endtime'));
            $select->join('play_coupons_linker', 'play_coupons_linker.coupon_id=play_coupons.coupon_id', array('id'));
            $select->join('play_shop', 'play_shop.shop_id=play_coupons_linker.shop_id', array('shop_id'));
            $select->where(array(new In('play_coupons_linker.shop_id', json_decode($shop_ids, true))));
            $select->where(array('play_coupons.coupon_status' => 1, 'shop_status' => 0, 'play_coupons_linker.coupon_id!=' . $this_coupon_id));
            $select->group('coupon_id');

        });

        $data = array();
        foreach ($res as $v) {
            $data[] = array(
                'coupon_id' => $v->coupon_id,
                'coupon_join'=>$v->coupon_join, //是否合作
                'coupon_img' => $v->coupon_thumb?$url.$v->coupon_thumb:'',
                'coupon_name' => $v->coupon_name,
                'coupon_price' => $v->coupon_price,
                'surplus' => ($v->coupon_join == 1 && (($v->coupon_buy + $v->coupon_vir) < $v->coupon_total) && $v->coupon_endtime > time()) ? 1 : 0,  //剩余  false true
                'discount' => round($v->coupon_price / $v->coupon_originprice * 10, 1),
            );
        }
        return $data;


    }

    //获取商家下最热门卡券列表
    public function getHotCoupons($offset = 0, $limit = 5, $where)
    {
        $data = $this->tableGateway->select(function (Select $select) use ($offset, $limit, $where) {
            //$select->columns();
            $select->join('play_coupons_linker','play_coupons_linker.coupon_id=play_coupons.coupon_id');
            $select->join('play_shop', 'play_shop.shop_id=play_coupons_linker.shop_id',array('shop_id','addr_x','addr_y'));
            $select->where($where);
            $select->group(array('play_coupons.coupon_id'));
            $select->order(array('play_shop.hot_count' => 'DESC'));
            $select->limit($limit)->offset($offset);
        });
        return $data;
    }



    //v4  搜索页面 卡券列表
    public function  getSearchCouponList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function (Select $select) use ($start, $pagesum, $columns, $where, $order) {
//            $select->columns($columns);
            $select->join('play_coupons_linker', 'play_coupons_linker.coupon_id=play_coupons.coupon_id', array(), 'left');
            $select->join('play_shop', 'play_shop.shop_id=play_coupons_linker.shop_id',array('shop_id','addr_x','addr_y'));
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order($order);
            $select->GROUP('play_coupons.coupon_id');
        });
        return $data;
    }


}