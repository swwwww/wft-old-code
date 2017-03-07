<?php
namespace Application\Model;

use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Predicate\In;
use Zend\Db\Sql\Predicate\NotIn;
use Zend\Db\Sql\Select;

class PlayExcerciseEventTable extends BaseTable
{

    //api 获取场次列表
    public function getSession($id)
    {
        return $this->tableGateway->select(function (Select $select) use ($id) {
            $select->columns(array(
                'eid' => 'id',
                'start_time',
                'end_time',
                'sell_status',
                'over_time',
                'open_time',
                'join_number',
                'join_ault',
                'join_child',
                'least_number',
                'perfect_number',
                'most_number',
                'shop_name',
                'vir_number',
                'vir_ault',
                'vir_child',
                'low_price' =>new Expression("(select min(price) from play_excercise_price   where  `play_excercise_price`.`eid` = `play_excercise_event`.`id`)")
            ));
            $select->join('play_excercise_base', 'play_excercise_base.id=play_excercise_event.bid', array('name'), 'left');
//            $select->join('play_excercise_price', 'play_excercise_price.eid=play_excercise_event.id', array('low_price'=>new Expression('min(price)')), 'left');
            $select->where(
                array(
                    'play_excercise_event.sell_status >= 0',
                    'play_excercise_event.sell_status <> 3',
                    'play_excercise_event.customize = 0',
                    'play_excercise_event.bid' => $id,
                    'play_excercise_base.release_status' => 1,
                )
            );
            $select->order(array('play_excercise_event.start_time' => 'asc'));
        });
    }

    /**
     * @param $id 现金券id
     * @return \Zend\Db\ResultSet\ResultSet
     */
    public function getEventByCash($id,$pagesum=0,$start=0)
    {
        if(!$id){
            $id = 0;
        }
        $event = $this->tableGateway->select(function (Select $select) use ($id,$pagesum,$start) {
            $select->columns(array(
                'eid' => 'id',
                'bid',
                'start_time',
                'end_time',
                'sell_status',
                'over_time',
                'open_time',
                'join_number',
                'join_ault',
                'join_child',
                'least_number',
                'perfect_number',
                'most_number',
                'shop_name',
                'vir_number',
                'vir_ault',
                'vir_child',
            ));
            $select->join('play_excercise_base', 'play_excercise_base.id=play_excercise_event.bid', array('name','low_price'), 'left');
            $select->join('play_cashcoupon_good_link', 'play_cashcoupon_good_link.object_id=play_excercise_event.id', array('cid'), 'left');
            $select->where(
                array(
                    'play_excercise_event.sell_status >= 0',
                    //'play_excercise_event.sell_status <> 3',
                    'play_excercise_event.excepted = 0',
                    'play_excercise_event.customize = 0',
                    'play_cashcoupon_good_link.cid' => $id,
                    'play_excercise_base.release_status' => 1,
                    'play_cashcoupon_good_link.object_type' => 4,
                    'play_excercise_event.over_time >= '.time(),
                )
            );
            $select->order(array('play_excercise_event.start_time' => 'asc'));
            if($pagesum){
                //$select->limit(10);
                $select->limit($pagesum)->offset($start);
            }

        });
        return $event;
    }

    public function getEventInfo($where = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($where) {
            $select->join('play_excercise_base', 'play_excercise_base.id = play_excercise_event.bid', array('name', 'introduction', 'thumb', 'phone'), 'left');
            $select->where($where);
        })->current();

        return $data;
    }


    //获取历史场次和订单
    public function gethistoryOrder($where, $desc, $page_num, $uid = 0)
    {

        if (!$uid) {
            return $this->_getPlayExcerciseEventTable()->fetchAll($where, array('id' => 'desc'), $page_num);
        } else {
            return $this->tableGateway->select(function ($select) use ($where, $desc, $page_num, $uid) {
                $select->join('play_order_info', 'play_order_info.coupon_id = play_excercise_event.bid', array('order_sn'), 'left');
                $select->join('play_order_otherdata', 'play_order_otherdata.order_sn = play_order_info.order_sn', array('comment'), 'left');
                $select->where($where);
            })->current();

        }
    }

    //可选场次
    public function session($bid){
        $db=$this->tableGateway->getAdapter();
        $data= $db->query("select count(*) as c from play_excercise_event WHERE bid=? AND sell_status=1",array($bid))->current();
        if($data->c){
            return (int)$data->c;
        }else{
            return 0;
        }
    }

    public function  getEventList($start = 0, $pagesum = 0, $columns = array(), $where = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where) {
            $select->columns($columns);
            $select->join('play_excercise_base', 'play_excercise_base.id = play_excercise_event.bid', array('name','low_price','thumb','introduction'), 'left');
            $select->where($where);
            $select->order(['play_excercise_event.id'=>'desc']);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
        })->toArray();

        return $data;
    }

}