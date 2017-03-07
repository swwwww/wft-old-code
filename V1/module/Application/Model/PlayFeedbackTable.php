<?php
namespace Application\Model;

class PlayFeedbackTable extends BaseTable
{

    //获取意见列表
    public function  getFeedbackList($where = array(),  $start = 0, $pagesum = 0, $order = array('id' => 'desc'))
    {
        $data = $this->tableGateway->select(function ($select) use ($where, $start, $pagesum, $order) {
            $select->where($where);
            if ($pagesum) {
                $select ->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });
        return $data;
    }

}