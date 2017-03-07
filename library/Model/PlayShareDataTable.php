<?php

namespace library\Model;


use library\Service\System\Cache\KeyNames;
use library\Service\System\Cache\RedCache;

class PlayShareDataTable extends BaseTable
{
    public function getData($param) {
        $param['share_city'] = empty($param['share_city']) ? 'WH' : $param['share_city'];

        $data_str_key = KeyNames::WFT_STR_SHAREDATA_KEY . '_' . $param['share_city'];

        return RedCache::fromCacheData($data_str_key, function () use ($param) {
            return $this->get($param);
        }, 0, true);
    }

    public function update($new_data = array(), $where = array()) {
        if (empty($new_data) || empty($where)) {
            return false;
        }

        $where['share_city'] = empty($where['share_city']) ? 'WH' : $where['share_city'];

        $data_str_key = KeyNames::WFT_STR_SHAREDATA_KEY . '_' . $where['share_city'];

        $status = parent::update($new_data, $where);
        if ($status) {
            RedCache::updateCache($data_str_key, $new_data, $this->getData($where));
            return true;
        } else {
            return false;
        }
    }
}
