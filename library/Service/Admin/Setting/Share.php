<?php

namespace library\Service\Admin\Setting;

use library\Fun\M;

class Share {
    public static function getShareData ($param) {
        return M::getPlayShareDataTable()->getData($param);
    }

    public static function updateShareData ($data_share) {
        $data_new   = $data_share;
        $data_where = array(
            'share_city' => $data_share['share_city'],
        );
        return M::getPlayShareDataTable()->update($data_new, $data_where);
    }

    public static function addShareData ($data_share) {
        $data_share['share_create_time'] = time();
        $data_share['share_update_time'] = time();
        return M::getPlayShareDataTable()->insert($data_share);
    }
}