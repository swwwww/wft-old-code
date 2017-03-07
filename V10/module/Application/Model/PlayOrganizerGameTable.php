<?php
namespace Application\Model;

class PlayOrganizerGameTable extends BaseTable {

    public function apiFetchLimitOrganizer($offset = 0, $row = 20, $columns = array(), $where = array(), $order = array())
    {
        $resultSet = $this->tableGateway->select(function ($select) use ($columns, $where, $order, $offset, $row) {
            if (!empty($columns)) {
                $select->columns($columns);
            }
            $select->join('play_organizer','play_organizer.id = play_organizer_game.organizer_id',array('organizer_name' => 'name'), 'left');
            $select->where($where);
            if ($row) {
                $select->limit($row)->offset($offset);
            }
            $select->order($order);
        });
        return $resultSet;
    }

    //获取商品列表
    public function  getGameList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_game_info', 'play_game_info.gid=play_organizer_game.id', array(), 'left');
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order($order);
            $select->GROUP('play_organizer_game.id');
            
        });
        return $data;
    }

    //商品奖励
    public function getGameJoinWelfare($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array()){
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_welfare_cash', 'play_welfare_cash.gid=play_organizer_game.id', array('give_type'), 'left');
            $select->join('play_welfare_integral', 'play_welfare_integral.link_id=play_organizer_game.id', array('total_score','welfare_type'), 'left');
            $select->join('play_welfare_rebate', 'play_welfare_rebate.gid=play_organizer_game.id', array('single_rebate'), 'left');
            $select->join('play_cash_coupon', 'play_welfare_cash.cash_coupon_id=play_cash_coupon.id', array('price','end_time'), 'left');
            $select->where($where);
            $select->GROUP('play_organizer_game.id');
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });

        return $data;
    }

    public function getQGameWithInfo($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
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