<?php
namespace library\Service\User;

use library\Fun\M;
use library\Service\System\Cache\KeyNames;
use library\Service\System\Cache\RedCache;

class Consult
{

    /**
     * 获取未回复咨询的数量
     * @param $city
     * @return int
     */
    public static function getReplyConsult($city)
    {

        if (RedCache::get(KeyNames::CONSULT_REPLY. $city)) {
            return RedCache::get(KeyNames::CONSULT_REPLY. $city);
        }

        if ($city) {
            $where = array(
                'status' => array('$gte' => 1),
                'reply.uid' => array('$exists' => false),
                'city' => $city
            );
        } else {
            $where = array(
                'status' => array('$gte' => 1),
                'reply.uid' => array('$exists' => false),
            );
        }

        $count = M::_getMdbConsultPost()->count($where);
        RedCache::set(KeyNames::CONSULT_REPLY. $city, $count, KeyNames::CONSULT_REPLY_TTL);
        return $count;
    }


    /**
     * 更新咨询数量缓存
     * @param $city
     * @return bool
     */
    public static function delReplayConsult($city)
    {
        RedCache::del(KeyNames::CONSULT_REPLY. $city);
        return true;
    }


}