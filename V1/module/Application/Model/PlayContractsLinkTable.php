<?php
/**
 * Created by PhpStorm.
 * User: dddee
 * Date: 2015/12/30
 * Time: 9:13
 */
namespace Application\Model;


class PlayContractsLinkTable extends BaseTable
{

    public function  getContractOrder($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_admin', 'play_admin.id=play_contracts.business_id', array('admin_name'), 'left');
            $select->join('play_organizer', 'play_organizer.id=play_contracts.mid', array('name'),'left');
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order($order);
        })->toArray();
        return $data;
    }

    public function getContractCouponInfo($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array()){
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_contracts_price', 'play_contracts_price.gid=play_contracts_link.id', array('price_name','price','money','total_num'), 'left');
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order($order);
        })->toArray();
        return $data;
    }
}