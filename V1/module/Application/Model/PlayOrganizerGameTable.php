<?php
namespace Application\Model;


use Zend\Db\Sql\Expression;

class PlayOrganizerGameTable extends BaseTable {

    public function getAdminOrganizer($offset = 0, $row = 20, $where = array())
    {
        $resultSet = $this->tableGateway->select(function ($select) use ($where,$offset, $row) {
            $select->limit($row)->offset($offset);
            $select->join('play_organizer','play_organizer.id = play_organizer_game.organizer_id',array('organizer_name' => 'name'));
            $select->where($where);
        });
        return $resultSet;
    }

    public function getAdminGameList($offset = 0, $row = 20, $columns = array(), $where = array(), $order = array())
    {
        $resultSet = $this->tableGateway->select(function ($select) use ($columns, $where, $order, $offset, $row) {
            if (!empty($columns)) {
                $select->columns($columns);
            }
            $select->join('play_contracts', 'play_contracts.id = play_organizer_game.contract_id', array(), 'left');
            $select->join('play_admin', 'play_admin.id = play_contracts.business_id', array('business' => 'admin_name'), 'left');
            $select->where($where);
            if ($row) {
                $select->limit($row)->offset($offset);
            }
            $select->order($order);
        });
        //echo $this->tableGateway->getSql()->getSqlPlatform()->getSqlString();
        return $resultSet;
    }

    //获取编辑信息
    public function  getAdminEditor($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_admin', 'play_admin.id = play_organizer_game.editor_id', array('admin_name', 'image'), 'left');
            $select->where($where);
            if ($pagesum) {
                $select ->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }

    //获取商家后台产品信息
    public function getGameInfo($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array()){
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_order_info', 'play_order_info.coupon_id = play_organizer_game.id', array('use_number','sale_num'=>new Expression('buy_number')), 'left');
            $select->join('play_coupon_code', 'play_coupon_code.order_sn = play_order_info.order_sn', array('test_status'), 'left');
            $select->join('play_index_block', 'play_index_block.link_id = play_organizer_game.id', array('link_type'), 'left');
            $select->where($where);
            if ($pagesum) {
                $select ->limit($pagesum)->offset($start);
            }
            $select->group("play_order_info.coupon_id");
            $select->order($order);
        });
        return $data;
    }

    /**
     * 获得可以使用现金券的商品，排除特例商品
     * @param int $start
     * @param int $pagesum
     * @param array $columns
     * @param array $where
     * @param array $order
     * @return \Zend\Db\ResultSet\ResultSet
     */
    public function getCashGameWithInfo($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start,$pagesum,$columns,$where,$order) {
            $select->join('play_game_info', 'play_game_info.gid = play_organizer_game.id', array(), 'left');
            $select->where($where);
            $select->GROUP('play_organizer_game.id');
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });

        return $data;
    }
}