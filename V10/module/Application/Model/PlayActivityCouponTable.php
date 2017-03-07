<?php

namespace Application\Model;


class PlayActivityCouponTable extends BaseTable
{
    public function  getCouponList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_coupons', 'play_coupons.coupon_id = play_activity_coupon.cid', array('coupon_id', 'coupon_name', 'coupon_endtime', 'coupon_uptime', 'coupon_status', 'coupon_marketid'), 'left');
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }

    public function  getGameList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_organizer_game', 'play_organizer_game.id = play_activity_coupon.cid');
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }

    public function  getActiveGameCount($where = array())
    {
        $where['play_organizer_game.status'] = 1;
        $data = $this->tableGateway->select(function ($select) use ($where) {
            $select->join('play_organizer_game', 'play_organizer_game.id = play_activity_coupon.cid');
            $select->where($where);
        });
        return count($data);
    }

    public function getActivePlaceCount($where = array()){
        $where['play_shop.shop_status'] = 0;
        $data = $this->tableGateway->select(function ($select) use ($where) {
            $select->join('play_shop', 'play_shop.shop_id = play_activity_coupon.cid');
            $select->where($where);
        });
        return count($data);
    }

    //api coupon
    public function  getApiCouponList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_coupons', 'play_coupons.coupon_id = play_activity_coupon.cid');
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }

    // api news
    public function  getApiNewsList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_news', 'play_news.id = play_activity_coupon.cid');
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }

            $select->order($order);
        });
        return $data;
    }


}