<?php

namespace library\Model;


use library\Service\System\Cache\KeyNames;
use library\Service\System\Cache\RedCache;

class PlayBannerSettingTable extends BaseTable
{
    public function getData($param) {
        if (empty($param['banner_setting_id'])) {
            return false;
        }

        return RedCache::fromCacheData(KeyNames::WFT_STR_BANNER_SETTING . $param['banner_setting_id'], function () use ($param) {
            return $this->get($param);
        }, 3600 * 24, true);
    }

    public function update($new_data = array(), $where = array()) {
        if (empty($new_data) || empty($where)) {
            return false;
        }

        $data_str_key = KeyNames::WFT_STR_BANNER_SETTING . $where['banner_setting_id'];

        $status = parent::update($new_data, $where);
        if ($status) {
            RedCache::updateCache($data_str_key, $new_data, $this->getData($where));
            return true;
        } else {
            return false;
        }
    }

    public function getDataList ($param) {
        $data_str_key = KeyNames::WFT_STR_BANNER_SETTING_LIST . '_' . $param['banner_setting_city'];
        return RedCache::fromCacheData($data_str_key, function () use ($param) {
            return $this->fetchAll($param)->toArray();
        }, 0, true);
    }
}