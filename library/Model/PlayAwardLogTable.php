<?php

namespace library\Model;

use Zend\Mvc\Controller\AbstractActionController;

class PlayAwardLogTable extends BaseTable
{
    public function getAwardList($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_award', 'play_award.award_id = play_award_log.award_id', array('*'), 'left');
            $select->join('play_user', 'play_user.uid = play_award_log.uid', array('username', 'phone'), 'left');
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order(array("play_award_log.log_id desc"));
        });
        return $data;
    }

    public function countAward($where = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($where) {
            $select->join('play_award', 'play_award.award_id = play_award_log.award_id', array('*'), 'left');
            $select->join('play_user', 'play_user.uid = play_award_log.uid', array('username', 'phone'), 'left');
            $select->where($where);
        });
        return count($data);
    }

    public function getAll($where = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($where) {
            $select->join('play_award', 'play_award.award_id = play_award_log.award_id', array('*'), 'left');
            $select->join('play_user', 'play_user.uid = play_award_log.uid', array('username', 'phone'), 'left');
            $select->where($where);
            $select->order(array("play_award_log.log_id desc"));
        });
        return $data;
    }
}