<?php
namespace Application\Model;

use Zend\Db\Sql\Predicate\In;
use Zend\Db\Sql\Predicate\NotIn;
use Zend\Db\Sql\Select;

class PlayExcerciseCodeTable extends BaseTable
{


    //锟斤拷取锟斤拷锟斤拷疃拷锟斤拷没锟斤拷斜锟?
    function getExcerciseMembers($eid)
    {
        return $this->tableGateway->select(function (Select $select) use ($eid) {
            $select->join('play_user', 'play_user.uid=play_excercise_code.uid', array('img'), 'left');
            $select->where(array('play_excercise_code.eid' => $eid));
        });
    }

    public function getExcercisePersonList($start = 0, $pagesum = 0, $columns = array(), $where = array()){

        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where) {
            $select->join('play_order_info', 'play_order_info.coupon_id = play_excercise_code.eid', array('username','buy_phone','dateline','user_id'), 'left');
            $select->join('play_order_insure', 'play_order_insure.order_sn = play_order_info.order_sn', array('associates_id','insure_sn','insure_status','name','id_num','insure_id'),'left');
            $select->join('play_excercise_base','play_excercise_base.id = play_excercise_code.eid', array('release_status','sell_status'),'left');
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order("play_order_info.dateline desc");
        })->toArray();
        return $data;

    }

//锟斤拷证锟斤拷锟斤拷息
    public function getCodeList($where = array()){

        $data = $this->tableGateway->select(function ($select) use ($where) {
            $select->join('play_excercise_price','play_excercise_price.id = play_excercise_code.pid', array('price_name','price','is_other','person'),'left');
            $select->where($where);
            $select->order("play_excercise_code.id desc");
        })->toArray();

        return $data;

    }

    public function getCodeLimit($start, $pagesum,$columns, $where){

        $data = $this->tableGateway->select(function ($select) use ($columns,$where,$pagesum,$start) {
            $select->join('play_excercise_price','play_excercise_price.id = play_excercise_code.pid', array('price_name','price','is_other'),'left');
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order("play_excercise_code.id desc");
        })->toArray();

        return $data;
    }


    public function getEventPrice($where=array()){
        $data = $this->tableGateway->select(function ($select) use ($where) {
            $select->join('play_excercise_price', 'play_excercise_price.id = play_excercise_code.pid', array('price_name','price'),'left');
            $select->join('play_order_info', 'play_order_info.order_sn = play_excercise_code.order_sn', array('username','phone'),'left');
            $select->where($where);
        })->toArray();

        return $data;
    }
}