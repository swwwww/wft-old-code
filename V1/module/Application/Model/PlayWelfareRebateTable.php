<?php

namespace Application\Model;


class PlayWelfareRebateTable extends BaseTable
{
    //获取合同套系相关
    public function getRebateWithGood($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array()){
        $data = $this->tableGateway->select(function ($select) use ($start,$pagesum,$columns,$where,$order) {
            if (!empty($columns)) {
                $select->columns($columns);
            }
            $select->join('play_organizer_game', 'play_organizer_game.id=play_welfare_rebate.gid',array('good_id'=>'id','title'),'left');
            $select->join('play_user', 'play_user.uid=play_welfare_rebate.uid',array('username'),'left');
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order($order);
        })->toArray();
        return $data;
    }
}