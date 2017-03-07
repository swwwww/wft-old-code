<?php

namespace library\Model;


use library\Service\System\Cache\KeyNames;
use library\Service\System\Cache\RedCache;

class PlayBannerTable extends BaseTable
{
    public function getData($param) {
        if (empty($param['banner_id'])) {
            return false;
        }

        return RedCache::fromCacheData(KeyNames::WFT_STR_BANNER . $param['banner_id'], function () use ($param) {
            return $this->get($param);
        }, 5 * 60, true);
    }

    public function update($new_data = array(), $where = array()) {
        if (empty($new_data) || empty($where)) {
            return false;
        }

        $data_str_key = KeyNames::WFT_STR_BANNER . $where['banner_id'];

        $status = parent::update($new_data, $where);
        if ($status) {
            RedCache::updateCache($data_str_key, $new_data, $this->getData($where));
            return true;
        } else {
            return false;
        }
    }

    public function getDataList ($param) {
        return RedCache::fromCacheData(KeyNames::WFT_STR_BANNER_LIST, function () use ($param) {
            return $this->fetchAll($param, array('banner_sort' => 'asc'))->toArray();
        }, 5 * 60, true);
    }
}