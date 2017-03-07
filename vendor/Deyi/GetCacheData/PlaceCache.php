<?php

namespace Deyi\GetCacheData;

use Application\Module;
use Deyi\BaseController;
use library\Service\System\Cache\RedCache;

class PlaceCache
{
    use BaseController;

    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }

    public function __construct() {
        $this->cache = new RedCache();
    }

    /**
     * 获取指定游玩地的 商圈
     * @param $id
     * @return bool|string
     */
    public function getPlaceCircle($id) {

        $circle =  $this->cache->get('shop_circle_'. $id);
        if ($circle) {
            return $circle;
        }
        return $this->setCircle($id);

    }

    private function setCircle($id) {
        $rid =  $this->_getPlayShopTable()->get(array('shop_id' => $id))->busniess_circle;
        $regionData = $this->_getPlayRegionTable()->get(array('rid' => $rid));

        // 展示二级商圈
        /*if ((int)substr($rid, -2)) {
            $cid = substr($rid, 0,  -2). '00';
            $regionDataBig = $this->_getPlayRegionTable()->get(array('rid' => $cid));
            $circle = $regionDataBig->name. ' '.$regionData->name;
        } else {
            $circle = $regionData->name;
        }*/

        if(!$regionData){
            return '';
        }

        $circle = $regionData->name;
        $this->cache->set('shop_circle_'. $id, $circle, '300');
        return $circle;
    }

    private function _getWelfareTags($pid)
    {
        $key = "D:placetags:{$pid}";
        $cache_data = RedCache::get($key);
        if ($cache_data !== false) {
            return json_decode($cache_data, true);
        }
        //获取数据 二维数组
        $game_info = $this->_getPlayWelfareTable()->fetchAll(array('object_id' => $pid, 'object_type' => 1, 'status' => 2),
            array('welfare_value' => 'ASC'));

        if (!$game_info) {
            return false;
        }
        $tag = [];
        $coupon = 0;
        $cc = new CouponCache();
        //福利类型 1积分 2 返利现金 3现金券
        foreach($game_info as $g){
            if($g['welfare_type'] == 2){
                $tag[0] = '返利' . $g['welfare_value'] . '元';
            }elseif($g['welfare_type'] == 3){
                //判断现金券是否有效
                if($cc->isvalid($g['welfare_link_id'])) {
                    $coupon += (int)$g['welfare_link_id'];
                    $tag[1] = '返券' . $coupon . '元';
                }
            }elseif($g['welfare_type'] == 1){
                $tag[2] = $g['welfare_value'] . '点积分';
            }
        }

        $status = RedCache::setnx($key, json_encode($tag, JSON_UNESCAPED_UNICODE), 604800); // 缓存7日

        if ($status) {
            return $tag;
        } else {
            $cache_data = RedCache::get($key);

            return json_decode($cache_data, true);
        }
    }

    /**
     *  获取商品标签数据
     * @param $coupon_id
     * @return array
     */
    public static function getPlaceTags($pid,$f){
        $tag = self::_getInstance()->_getWelfareTags($pid);
        if(!$tag){
            $tag = [];
        }
        if($f){
            $tag[] = '点评有礼';
        }
        return $tag;
    }

    private static function _getInstance()
    {
        if (null === static::$_instance) {
            static::$_instance = new PlaceCache();
        }

        return static::$_instance;
    }

    /**
     * wjiang
     * 游玩地标签
     * @return array //点评有礼 分享有礼
     * @param $shop_id //游玩地id
     */
    public function getShopTags($shop_id) {

        $tag =  $this->cache->get('shop_tags_'. $shop_id);
        if ($tag) {
            return json_decode($tag, true);
        } else {
            return $this->_setShopTags($shop_id);
        }

    }

    /**
     * @param $shop_id
     * @return mixed
     */
    private function _setShopTags($shop_id) {
        $tag = array();
        $shopData =  $this->_getPlayShopTable()->get(array('shop_id' => $shop_id));

        if (!$shopData) {
            return $tag;
        }

        if ($shopData->post_award == 2) {
            array_push($tag, '点评有礼');
        }

        $shareWelfare = $this->_getPlayWelfareIntegralTable()->get(array('object_type' => 1, 'object_id' => $shop_id, 'welfare_type' => 3, 'status' => 1, 'total_num > get_num'));
        $postWelfare = $this->_getPlayWelfareIntegralTable()->get(array('object_type' => 1, 'object_id' => $shop_id, 'welfare_type' => 4, 'status' => 1, 'total_num > get_num'));

        if ($shareWelfare) {
            array_push($tag, '分享有礼');
        }

        if (!in_array('点评有礼', $tag) && $postWelfare) {
            array_push($tag, '点评有礼');
        }

        $this->cache->set('shop_tags_'. $shop_id, json_encode($tag), '300');

        return $tag;

    }


}