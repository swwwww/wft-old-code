<?php
/**
 * Created by PhpStorm.
 * User: kylin
 * Date: 2015/12/28
 * Time: 16:24
 */
namespace library\Model;


use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Where;

class InviteInviterAwardLogTable extends BaseTable
{

    //todo 获取每天因首单返红包的次数
    public function getOrderAwardPerDay($uid,$status,$award_type){//status  0：下载，1：注册，2：已下首单
        $data = $this->tableGateway->select(
            function ($select) use ($uid,$status,$award_type) {
                $select->columns(['count' => new Expression('count(*)')])
                    ->where(
                        array('dateline >= '.strtotime(date('Y-m-d'.' 00:00:00')))
                    )->where(
                        array('dateline <= '.strtotime(date('Y-m-d'.' 23:59:59')))
                    )->where(
                        ['uid' => $uid, 'status' => $status, 'award_type' => $award_type]
                    );
                $select->order(['dateline desc']);
            })->current();
        return $data;
    }

    //todo 获取一单多票用户的总值
    public function getAgainTotalAward($uid,$sourceid,$status,$award_type){//status  0：下载，1：注册，2：已下首单
        $data = $this->tableGateway->select(
            function ($select) use ($uid,$sourceid,$status,$award_type) {
                $select->columns(['sum' => new Expression('sum(`award`)')])
                    ->where(
                        ['uid' => $uid, 'sourceid' => $sourceid,'status' => $status, 'award_type' => $award_type]
                    );
                $select->group('sourceid');
            })->current();
        if($data){
            $data = $data->sum;
        }
        return $data;
    }

    //todo 获取该用户邀约所得不同奖励总值 $award_type 0 积分 ；1 现金券；2 资格券
    public function getTotalAward($uid,$award_type = 0){
        $data = $this->tableGateway->select(
            function ($select) use ($uid ,$award_type) {
                $select->columns(['award_sum' => new Expression('sum(award)')])
                    ->where(
                        ['uid' => $uid, 'award_type' => $award_type]
                    );
                $select->order(['dateline desc']);
            })->current();
        if($data){
            $data = $data->award_sum;
        }
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


/*
       $data = $this->tableGateway->select(
            function ($select) use ($uid) {
                $select->columns(['count' => new Expression('count(*)')])
                    ->where(function (Where $where) use ($uid) {
                        $where->expression('FROM_UNIXTIME(dateline,"%Y%m%d")=FROM_UNIXTIME(?,"%Y%m%d")',new Expression('sysdate()') )->equalTo('uid', $uid)->equalTo('status', 2)->equalTo('award_type', 1);
                    });

                $select->order(['dateline desc']);
            })->toArray();echo time();
var_dump($data);
        return $data;
*/