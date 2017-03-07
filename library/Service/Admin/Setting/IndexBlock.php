<?php

namespace library\Service\Admin\Setting;

use library\Fun\M;

class IndexBlock {
    public static function getIndexBlockDataList ($param = array(), $order = array(), $limit = array()) {
        return M::getPlayIndexBlockTable()->fetchAll($param, $order, $limit);
    }

    public static function setIndexBlock ($param_status, $param_where) {
        $data_index_block = M::getPlayIndexBlockTable()->get($param_where);

        if ($data_index_block) {
            return M::getPlayIndexBlockTable()->update($param_status, $param_where);
        } else {
            if ($param_status['status'] == 1) {
                $pdo = M::getAdapter();
                $sql  = " INSERT INTO play_index_block (`link_id`, `block_title`, `link_img`, `type`, `block_order`, `dateline`, `block_city`, `link_type`, `editor_id`, `editor`) VALUE ";
                $sql .= " (" . (int)$param_where['link_id'] . ",'" . $param_status['block_title'] . "','" . $param_status['link_img'] . "'," . (int)$param_where['type'] . ",1," . time() . ",'" . $param_where['block_city'] . "'," . (int)$param_where['link_type'] . "," . (int)$_COOKIE['id'] . ",'" . $_COOKIE['user'] . "') ";

                return $pdo->query($sql, array())->count();
            } else {
                return true;
            }
        }
    }
}