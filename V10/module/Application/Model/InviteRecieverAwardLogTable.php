<?php
/**
 * Created by PhpStorm.
 * User: kylin
 * Date: 2015/12/28
 * Time: 16:24
 */
namespace Application\Model;

class InviteRecieverAwardLogTable extends BaseTable
{
    public function getByPhone($phone){
        $data = $this->fetchLimit(0, 1, [], ['phone' => $phone])->current();
        return $data;
    }

    //todo 获取每天因首单返红包的次数
    public function getOrderAwardPerDay($sourceid,$status,$award_type){//status  0：下载，1：注册，2：已下首单
        $data = $this->tableGateway->select(
            function ($select) use ($sourceid,$status,$award_type) {
                $select->columns(['count' => new Expression('count(*)')])
                    ->where(
                        array('dateline >= '.strtotime(date('Y-m-d'.' 00:00:00')))
                    )->where(
                        array('dateline <= '.strtotime(date('Y-m-d'.' 23:59:59')))
                    )->where(
                        ['sourceid' => $sourceid, 'status' => $status, 'award_type' => $award_type]
                    );
                $select->order(['dateline desc']);
            })->current();
        return $data;
    }

    //todo 获取查询列表
    public function getList($start = 0, $pagesum = 0, $where = array(), $begin, $end,$order = array('dateline' => 'desc')){
        $data = $this->tableGateway->select(
            function ($select) use ($where, $begin, $end, $start, $pagesum, $order) {
                if($begin && $end){
                    $select->where($begin)->where($end);
                }
                $select->where($where);
                if ($pagesum) {
                    $select ->limit($pagesum)->offset($start);
                }
                $select->order($order);
            });

        return $data;
    }

} 