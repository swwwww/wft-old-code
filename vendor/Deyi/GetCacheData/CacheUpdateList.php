<?php

namespace Deyi\GetCacheData;

use library\Service\System\Cache\RedCache;

/**
 * 缓存更新列表,在此处注册缓存被动更新,核心方法勿修改
 * Class CacheUpdatelist
 * @package Deyi\GetCacheData
 */
class CacheUpdateList
{

    /**
     * update insert delete 都会调用此操作
     * @param $table
     * @param array $data
     * @return bool
     */
    public static function upCache($table, $data = array())
    {
        switch ($table) {
            case 'play_account':
                if (isset($data['uid'])) {
                    RedCache::del('D:UserMoney:' . $data['uid']);
                    RedCache::del('D:setPass:' . $data['uid']);
                }
                break;
            case 'play_good_comment':
                if (isset($data['city'])) {
                    RedCache::del('D:GoodComm:' . $data['city']);
                }
                break;
            case 'play_market_setting':
                if (isset($data['city'])) {
                    RedCache::del('D:intSet' . $data['city']);
                }
                break;
            case 'play_user_baby':
                if (isset($data['uid'])) {
                    RedCache::del('D:UserBabys:' . $data['uid']);
                }
                break;
            case 'play_user_message':
                if (isset($data['uid'])) {
                    RedCache::del('D:NewMgsNum:' . $data['uid']);
                }
                break;
            case 'play_user':
                if (isset($data['uid'])) {
                    RedCache::del('D:UserInfo:' . $data['uid']);
                }
                break;
            case 'play_user_weixin':
                if (isset($data['uid'])) {
                    RedCache::del('D:bindWeixin:' . $data['uid']);
                }
                break;

            case 'play_invite_content':
                if (isset($data['city'])) {
                    RedCache::del('D:inviteSet:' . $data['city']);
                }
                break;
            case 'play_organizer_game':
                if (isset($data['id'])) {
                    RedCache::del('D:coupon_data:' . $data['id']);
                    RedCache::del('D:CouponLabels:' . $data['id']);
                }
                break;
            case 'play_game_info':
                if (isset($data['id'])) {
                    RedCache::del('D:coupon_game_order:' . $data['id']);
                    RedCache::del('D:coupon_game_order33_:' . $data['id']);
                }
                break;

            default:
                return false;
        }
    }
}



