<?php
namespace Application\Model;


use Zend\Db\Sql\Predicate\Expression;

class PlayContractsTable extends BaseTable
{

    public function  getContractList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            if (!empty($columns)) {
                $select->columns($columns);
            }
            $select->join('play_admin', 'play_admin.id=play_contracts.business_id', array('admin_name'), 'left');
            $select->join('play_organizer', 'play_organizer.id=play_contracts.mid', array('name'),'left');
            $select->join('play_organizer_game', 'play_organizer_game.contract_id=play_contracts.id', array('goods_num'=>new Expression('count(play_organizer_game.id)')),'left');
//            $select->join('play_order_info', 'play_order_info.coupon_id=play_organizer_game.id', array('sale_num'=>new Expression('sum(play_order_info.buy_number)'),'order_num'=>new Expression('count(play_order_info.order_sn)'),'total_money'=>new Expression('sum(play_order_info.account_money)')),'left');

            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->group('play_contracts.id');
            $select->order($order);
        })->toArray();
        return $data;
    }

    public function getArray($where)
    {
        return $this->tableGateway->select($where)->toArray();
    }

    public function  getContractSaleData($columns = array(),$where = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($where,$columns) {
            if (!empty($columns)) {
                $select->columns($columns);
            }
            $select->join('play_organizer_game', 'play_organizer_game.contract_id=play_contracts.id',array('game_id'=>'id'),'left');
            $select->join('play_contract_link_price', 'play_contract_link_price.good_id=play_organizer_game.id',array('game_id'=>'good_id','price','total_num','account_money'),'left');
            $select->join('play_order_info', 'play_order_info.coupon_id=play_contract_link_price.good_id', array('sale_num'=>new Expression('sum(buy_number)'),'order_num'=>new Expression('count(play_order_info.order_sn)'),'total_money'=>new Expression('sum(play_order_info.account_money+play_order_info.voucher)')),'left');
//            $select->join('play_order_info_game', 'play_order_info_game.order_sn=play_order_info.order_sn',array('type_name','address','price_id'),'left');
//            $select->join('play_game_price', 'play_game_price.id=play_order_info_game.price_id', array('account_money'),'left');

            $select->where($where);

            $select->group('play_contracts.id');
        })->toArray();
        return $data;
    }


    //获取合同套系相关
    public function getContractSet($columns = array(),$where = array()){
        $data = $this->tableGateway->select(function ($select) use ($where,$columns) {
            if (!empty($columns)) {
                $select->columns($columns);
            }
            $select->join('play_organizer_game', 'play_organizer_game.contract_id=play_contracts.id',array('game_id'=>'id'),'left');
            $select->join('play_contract_link_price', 'play_contract_link_price.good_id=play_organizer_game.id',array('game_id'=>'good_id','price','total_num','account_money'),'left');

            $select->where($where);

        })->toArray();
        return $data;
    }


}