<?php
/**
 * Created by PhpStorm.
 * User: kylin
 * Date: 2015/12/20
 * Time: 17:35
 */
namespace library\Model;

use Zend\Db\Sql\Select;


class AppGameCouponAddscorelogTable extends BaseTable {


    public function getGameAddMaxscore($gameId = 1, $accountId = null,$columns = array('*')) {
        $data = $this->tableGateway->select(
            function (Select $select) use ($accountId, $gameId, $columns) {
                $select->columns($columns)
                    ->where(['gameid' => $gameId,'accountid ' => $accountId])
                    ->order(['coupon_score desc'])
                    ->limit(1)->offset(0);
            }
        )->toArray();
        return $data;
    }

    public function getCouponAddScoreLog($start = 0, $pageSum = 0, $gameId = 1, $accountId = null, $columns = array('*')) {
        $data = $this->tableGateway->select(
            function (Select $select) use ($start, $pageSum, $gameId, $accountId, $columns) {
                $select->columns($columns)
                    ->where(['gameid' => $gameId,'accountid ' => $accountId]);
                if ($pageSum) {
                    $select->limit($pageSum)->offset($start);
                }
                $select->order(['createat desc']);
            })->toArray();
        return $data;
    }




}