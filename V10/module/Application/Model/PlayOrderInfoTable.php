<?php
namespace Application\Model;

use Zend\Db\Sql\Platform\Mysql\Mysql;
use Zend\Db\Sql\Select;

class PlayOrderInfoTable extends BaseTable
{

    //获取参与活动的用户列表
    function getExcerciseMembers($bid)
    {
        return $this->tableGateway->select(function (Select $select) use ($bid) {
            $select->join('play_user', 'play_user.uid=play_order_info.user_id', array('img'), 'left');

            $select->where(array('bid' => $bid,'order_status'=>1,'buy_number>(back_number+backing_number)','order_type'=>3));

//            $select->join('play_excercise_code', 'play_excercise_code.uid=play_user.uid', array('eid'), 'left');
//            $select->where(array('play_excercise_code.eid' => $eid));

            $select->limit(100);
        });
    }


    //个人中心列表
    function getMylist($where)
    {
        return $this->tableGateway->select(function (Select $select) use ($where) {
            $select->where($where);
            $select->join('play_coupons', 'play_coupons.coupon_id=play_order_info.coupon_id', array('coupon_close', 'coupon_cover', 'coupon_appointment', 'coupon_thumb'));
            $select->order(array('dateline' => 'desc'))->limit(1000);
        });
    }


    public function fetchJoinLimit($offset = 0, $row = 20, $columns = array(), $where = array(), $order = array(), $like = array())
    {
        $resultSet = $this->tableGateway->select(function ($select) use ($columns, $where, $like, $order, $offset, $row) {
            if (!empty($columns)) {
                $select->columns($columns);
            }
            if (!empty($like) and $like[key($like)]) {
                $select->where->like(key($like), "%{$like[key($like)]}%");
            }
            if ($row) {
                $select->limit($row)->offset($offset);
            }
            $select->join('play_coupons', 'play_coupons.coupon_id=play_order_info.coupon_id', array('coupon_price'));
            $select->where($where)->order($order);
        });
        return $resultSet;
    }

    public function getBroupBuyList($status, $offset, $pagenum, $uid)
    {

        $a = $this->tableGateway->select(function (Select $select) use ($status, $offset, $pagenum, $uid) {
            if ($status == 0) { //0已解散
                $select->where(array('play_group_buy.status' => 0, 'play_order_info.group_buy_id>0', 'play_order_info.pay_status>2', 'play_order_info.user_id' => $uid));
            } elseif ($status == 3) {//3未付款
                $select->where(array('play_group_buy.status' => 1, 'play_order_info.order_status' => 1, 'play_order_info.pay_status' => 0, 'play_order_info.group_buy_id>0', 'play_order_info.user_id' => $uid));
            } else if ($status == 2) {// 2已完成
                $select->where(array('play_group_buy.status' => $status, 'play_order_info.order_status' => 1, 'play_order_info.pay_status=2', 'play_order_info.group_buy_id>0', 'play_order_info.user_id' => $uid));
            } else if ($status == 1) {//1团购中
                $select->where(array('play_group_buy.status' => $status, 'play_order_info.order_status' => 1, 'play_order_info.pay_status=7', 'play_order_info.group_buy_id>0', 'play_order_info.user_id' => $uid));
            }
            $select->join('play_group_buy', 'play_order_info.group_buy_id=play_group_buy.id', array('e_time' => 'end_time', '*'));
            $select->join('play_order_info_game', 'play_order_info.order_sn=play_order_info_game.order_sn');
            $select->join('play_organizer_game', 'play_organizer_game.id=play_order_info.coupon_id')->order(array('play_group_buy.add_time' => 'desc'));
            $select->offset($offset)->limit($pagenum);
        });

        return $a;

    }

    //获取组团用户头像列表
    public function groupBuyImgList($group_buy_id)
    {
        $res = $this->tableGateway->select(function (Select $select) use ($group_buy_id) {
            $select->join('play_user', 'play_user.uid=play_order_info.user_id', array('username', 'img'));
            $select->where(array('group_buy_id' => $group_buy_id, 'order_status' => 1));
            $select->order(array('play_order_info.order_sn' => 'asc'));
        });

        return $res;
    }

    //评论时获取用户购买过的套系信息
    public function getUserBuy($order_sn)
    {
        return $this->tableGateway->select(function (Select $select) use ($order_sn) {
            $select->join('play_order_info_game', 'play_order_info_game.order_sn=play_order_info.order_sn');
            $select->join('play_game_info', 'play_game_info.id=play_order_info.bid');
            $select->where(array('play_order_info.order_sn' => $order_sn, 'order_status' => 1));
            $select->limit(1);
        })->current();
    }

    //获取场次
    public function getUserEvent($order_sn)
    {
        return $this->tableGateway->select(function (Select $select) use ($order_sn) {
            $select->join('play_excercise_event', 'play_excercise_event.id=play_order_info.coupon_id');
            $select->where(array('play_order_info.order_sn' => $order_sn, 'order_status' => 1));
            $select->limit(1);
        })->current();
    }
}