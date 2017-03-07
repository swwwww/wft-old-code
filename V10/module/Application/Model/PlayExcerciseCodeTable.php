<?php
namespace Application\Model;

use Zend\Db\Sql\Predicate\In;
use Zend\Db\Sql\Predicate\NotIn;
use Zend\Db\Sql\Select;

class PlayExcerciseCodeTable extends BaseTable
{

    public function getExcercisePersonList($start = 0, $pagesum = 0, $columns = array(), $where = array())
    {

        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where) {
            $select->join('play_order_info', 'play_order_info.eid = play_excercise_code.eid', array('username', 'buy_phone', 'dateline', 'user_id'), 'left');
            $select->join('play_order_insure', 'play_order_insure.order_sn = play_order_info.order_sn', array('associates_id', 'insure_sn', 'insure_status', 'name', 'id_num', 'insure_id'), 'left');
            $select->join('play_excercise_base', 'play_excercise_base.id = play_excercise_code.eid', array('release_status', 'sell_status'), 'left');
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order("play_order_info.dateline desc");
        })->toArray();
        return $data;

    }


    //api 获取验证码列表
    public function getCodeList($order_sn)
    {

        return $this->tableGateway->select(function ($select) use ($order_sn) {
            $select->join('play_excercise_price', 'play_excercise_price.id = play_excercise_code.pid', array('price_name','is_other','person'), 'left');
            $select->where(array('order_sn' => $order_sn));
        });

    }
    

}