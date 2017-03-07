<?php

namespace library\Service\Admin\Setting;

use library\Fun\M;
use library\Service\System\Cache\KeyNames;
use library\Service\System\Cache\RedCache;

class Banner {
    public static function getBannerData ($param) {
        return M::getPlayBannerTable()->getData($param);
    }

    public static function getBannerSetting ($param) {
        return M::getPlayBannerSettingTable()->getData($param);
    }

    public static function getBannerListData ($param) {
        return M::getPlayBannerTable()->getDataList($param);
    }

    public static function getBannerSettingListData ($param) {
        return M::getPlayBannerSettingTable()->getDataList($param);
    }

    public static function updateBannerData ($data_banner) {
        $data_new   = $data_banner;
        $data_where = array(
            'banner_id' => $data_banner['banner_id'],
        );

        return M::getPlayBannerTable()->update($data_new, $data_where);
    }

    public static function bannerSettingSave ($data_banner_setting) {
        if ($data_banner_setting['banner_setting_id']) {
            $data_new   = $data_banner_setting;
            $data_where = array(
                'banner_setting_id' => $data_banner_setting['banner_setting_id'],
            );

            return M::getPlayBannerSettingTable()->update($data_new, $data_where);
        } else {
            $pdo  = M::getAdapter();
            $sql  = " INSERT INTO play_banner_setting (`banner_setting_banner_id`, `banner_setting_city`, `banner_setting_value`) value ";
            $sql .= " (" . $data_banner_setting['banner_setting_banner_id'] . ", '" . $data_banner_setting['banner_setting_city'] . "', " . $data_banner_setting['banner_setting_value'] . ") ";

            $data_result = $pdo->query($sql, array())->count();

            if ($data_result) {
                RedCache::del(KeyNames::WFT_STR_BANNER_SETTING_LIST . '_' . $data_banner_setting['banner_setting_city']);
                return true;
            } else {
                return false;
            }
        }

    }
}