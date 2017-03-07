<?php

namespace Application\Model;


class PlayCouponsLinkerTable extends BaseTable
{
    public function getAdminTagCoupon($start, $pagesum, $columns, $where, $order) {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_coupons', 'play_coupons.coupon_id = play_coupons_linker.coupon_id');
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order($order);
            $select->Group(array('play_coupons_linker.coupon_id'));
        });
        return $data;
    }

    public function getTagCoupon($start, $pagesum, $columns, $where, $order) {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_coupons', 'play_coupons.coupon_id = play_coupons_linker.coupon_id');
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }



    //可用分店列表
    public function FetchShopList($coupon_id)
    {
        return $this->tableGateway->select(function ($select) use ($coupon_id) {
            $select->columns(array('coupon_id', 'shop_id'));
            $select->where(array('coupon_id' => $coupon_id));
            $select->join('play_shop', 'play_shop.shop_id=play_coupons_linker.shop_id');
        });
    }





}