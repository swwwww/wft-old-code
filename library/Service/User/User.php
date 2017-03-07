<?php

namespace library\Service\User;

use library\Fun\M;

class User {
    public static function getCountCollect($param) {
        return M::getPlayUserCollectTable()->fetchCount($param);
    }

    public static function getCountAssociate ($param) {
        return M::getPlayUserAssociatesTable()->fetchCount($param);
    }

    static public function updateLotteryUserData ($param_data, $param_where) {
        $pdo = M::getAdapter();
        $data_lottery_user = $pdo->query(" SELECT * FROM ps_lottery_user_total WHERE user_id = ? AND lottery_id = ? ORDER BY updated DESC limit 1", array($param_where['user_id'], $param_where['lottery_id']))->current();

        if (empty($data_lottery_user)) {
            $sql = " INSERT INTO ps_lottery_user_total (log_date, lottery_id, user_id, total, op_total, may_total, created) VALUES (?, ?, ?, ?, ?, ?, ?) ";
            $data_result = $pdo->query($sql, array(
                $param_data['log_date'],
                $param_data['lottery_id'],
                $param_data['user_id'],
                $param_data['total'],
                $param_data['op_total'],
                $param_data['may_total'],
                $param_data['created']
            ))->count();
        } else {
            $sql = " UPDATE ps_lottery_user_total SET total = total + ?, may_total = may_total + ? WHERE user_id = ? AND lottery_id = ? ";
            $data_result = $pdo->query($sql, array(
                $param_data['total'],
                $param_data['may_total'],
                $param_where['user_id'],
                $param_where['lottery_id'],
                //$param_where['log_date']
            ))->count();
        }

        return $data_result;
    }
}