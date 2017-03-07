<?php

namespace library\Model;


class PlayActivityCouponTable extends BaseTable
{
    public function  getCouponList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
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

    public function  getPlaceList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_shop', 'play_shop.shop_id = play_activity_coupon.cid');
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }

    public function getActivePlaceCount($where = array()){
        $where['play_shop.shop_status'] = 0;
        $data = $this->tableGateway->select(function ($select) use ($where) {
            $select->join('play_shop', 'play_shop.shop_id = play_activity_coupon.cid');
            $select->where($where);
        });
        return count($data);
    }

    public function  getActivityList($start = 0, $pageSum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pageSum, $columns, $where, $order) {
            $select->join('play_activity', 'play_activity.id = play_activity_coupon.aid', array('ac_name'));
            $select->where($where);
            if ($pageSum) {
                $select->limit($pageSum)->offset($start);
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

    public function  getNewsList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_news', 'play_news.id = play_activity_coupon.cid', array('cn' => 'id', 'title', 'status'));
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }

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

    // web news
    public function  getWebNewsList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
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


    //获取奖池模型
    public function  getApiPondCouponList($where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($where, $order) {
            // $select->columns();
            $select->join('play_coupons', 'play_coupons.coupon_id = play_activity_coupon.cid', array('coupon_id','coupon_price', 'coupon_total', 'coupon_buy'));
            $select->where($where);

        });
        return $data;
    }

    //获取专题关联的活动
    public function getExcerciseList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_excercise_base', 'play_excercise_base.id = play_activity_coupon.cid');
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }

}