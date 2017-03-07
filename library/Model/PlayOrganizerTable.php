<?php
namespace library\Model;

use Deyi\toGBK;
use Zend\Db\Sql\Predicate\Expression;

class PlayOrganizerTable extends BaseTable {

    public function  getAccountList($start = 0, $pagesum = 0, $columns = array(), $where = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where) {
            $select->columns($columns);
            $select->join('play_organizer_account', 'play_organizer_account.organizer_id = play_organizer.id', array('bank_name'), 'left');
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
        });
        return $data;
    }

//    public function getBranchInfo($columns = array(), $where = array()){
//        $data = $this->tableGateway->select(function ($select) use ($columns, $where) {
//            $select->columns($columns);
//            $select->join('play_order_info', 'play_order_info.shop_id = play_organizer.id', array('bank_name'), 'left');
//            $select->where($where);
//        });
//        return $data;
//    }

    public function getBranchInfo($columns = array(), $where = array()){
        $data = $this->tableGateway->select(function ($select) use ($columns, $where) {
            if(!empty($columns)){
                $select->columns($columns);
            }
            $select->join('play_organizer_game','play_organizer_game.organizer_id=play_organizer.branch_id',array('on_sale_num'=>new Expression('count(play_organizer_game.id)')),'left');
            $select->join('play_order_info', 'play_order_info.shop_id = play_organizer_game.organizer_id', array('total_sale_cash'=>new Expression('sum(play_order_info.account_money+play_order_info.voucher)')), 'left');
            $select->where($where);
        })->toArray();
        return $data;
    }
}