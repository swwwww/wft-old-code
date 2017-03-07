<?php

namespace Deyi\GetCacheData;

use Application\Module;
use Deyi\BaseController;
use library\Service\System\Cache\RedCache;

class GoodCache
{
    use BaseController;

    private static $_instance = null;

    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }


    /**
     *  根据套系获得奖励标签
     * @param $coupon_id
     * @return array
     */
    public static function getWelfareByInfo($gid)
    {
        $tag = self::_getInstance()->_getWelfareByInfo($gid);
        if (!$tag) {
            $tag = [];
        }

        return $tag;
    }



    /**
     * 根据套系获得奖励标签
     */
    private function _getWelfareByInfo($gid){
        //获取数据 二维数组
        $game_info = $this->_getPlayWelfareTable()->fetchLimit(0, 50, [],
            array('good_info_id' => $gid, 'object_type' => 2, 'status' => 2),
            array('welfare_value' => 'ASC'))->toArray();

        if (!$game_info) {
            return false;
        }

        $tag = [];
        $coupon = 0;
        $cc = new CouponCache();
        //福利类型 1积分 2 返利现金 3现金券
        if ($game_info) {
            foreach ($game_info as $g) {
                if ((int)$g['welfare_type'] === 2) {
                    $tag[0] = '返利' . (int)$g['welfare_value'] . '元';
                } elseif ((int)$g['welfare_type'] === 3) {
                    //判断现金券是否有效
                    if($cc->isvalid($g['welfare_link_id'])){
                        $coupon += (int)$g['welfare_value'];
                        $tag[1] = '返券' . $coupon . '元';
                    }
                }
            }
        }

        ksort($tag);

        return array_values($tag);
    }


    /**
     *  获取商品标签数据
     * @param $gid
     * @return array
     */
    private function _getWelfareTags($gid, $f)
    {
        $game = RedCache::fromCacheData('D:gtags:' . $gid, function () use ($gid,$f) {
            $data = $this->getWelfareTags($gid,$f);
            return $data;
        }, 24*3600, true);

        return $game;
    }

    public function getlastinfo($gid){
        $db = $this->_getAdapter();
        RedCache::del('G:getlastinfo:' . $gid);
        $game = RedCache::fromCacheData('G:getlastinfo:' . $gid, function () use ($gid,$db) {
            $order_data = $db->query("SELECT * FROM play_game_info
  WHERE play_game_info.gid=? AND play_game_info.end_time>?
  AND play_game_info.down_time > ?  ORDER BY play_game_info.down_time ASC limit 1",
                array($gid, time(),time()))->current();
            return $order_data;
        }, 600, true);

        if($game and $game->down_time){
            return date('Y-m-d H:i',$game->down_time);
        }else{
            return '已结束';
        }
    }

    /**
     * @param $gid //商品id
     * @param $f
     * @return array|bool
     */
    private function getWelfareTags($gid, $f)
    {
        $game = $this->_getPlayOrganizerGameTable()->fetchLimit(0, 1, [], array('id' => $gid))->toArray();

        //获取数据 二维数组
        $game_info = $this->_getPlayWelfareTable()->fetchLimit(0, 50, [],
            array('object_id' => $gid, 'object_type' => 2, 'status' => 2),
            array('welfare_value' => 'ASC'))->toArray();
        $game_integral = $this->_getPlayWelfareIntegralTable()->fetchLimit(0, 50, [],
            array('object_id' => $gid, 'object_type' => 2, 'status' => 1))->toArray();

        if (!$game) {
            return false;
        }

        $tag = [];

        //福利类型 1积分 2 返利现金 3现金券
        if ($game_info) {
            $coupon = 0;
            $cc = new CouponCache();
            foreach ($game_info as $g) {
                if ($g['welfare_type'] == 2) {
                    $tag[0] = '返利' . (int)$g['welfare_value'] . '元';
                } elseif ($g['welfare_type'] == 3) {
                    //判断现金券是否有效
                    if($cc->isvalid($g['welfare_link_id'])){
                        $coupon += (int)$g['welfare_value'];
                        $tag[1] = '返券' . $coupon . '元';
                    }
                }

                if ($g['give_time'] == 4) {
                    $tag[2] = '分享有礼';
                }
                if ($g['give_time'] == 3) {
                    $tag[3] = '点评有礼';
                }
            }
        }

        $score = 0;
        if ($game_integral) {

            foreach ($game_integral as $g) {
                if ($g['welfare_type'] == 4 and $g['total_num'] > $g['get_num']) {
                    $tag[2] = '分享有礼';
                }
                if ($g['welfare_type'] == 3 and $g['total_num'] > $g['get_num']) {
                    $tag[3] = '点评有礼';
                }

                if ($g['double'] > $score) {
                    $score = $g['double'];
                }
            }
        }

        if ($f == 2) {
            $tag[3] = '点评有礼';
        }

        if ($game[0]['buy_integral'] > $score) {
            $score = (int)$game[0]['buy_integral'];
        }

        if ($score) {
            if($score <= 1){
                //$tag[4] = $score . '倍积分';
            }else{
                $tag[4] = (int)$score . '倍积分';
            }

        }

        ksort($tag);

        $tag = array_values($tag);

        if (count($tag) === 4) {
            //array_pop($tag);
        }

        return $tag;
    }

    /**
     *  获取商品标签数据
     * @param $coupon_id
     * @return array
     */
    public static function getGameTags($gid, $f)
    {
        $tag = self::_getInstance()->_getWelfareTags($gid, $f);
        if (!$tag) {
            $tag = [];
        }

        return $tag;
    }

    /**
     * 更新商品设置时更新缓存
     * @param $gid
     * @param $f
     */
    public static function setGameTags($gid, $f)
    {
        RedCache::del('D:gtags:'.$gid);
    }

    private static function _getInstance()
    {
        if (null === static::$_instance) {
            static::$_instance = new GoodCache();
        }

        return static::$_instance;
    }

    // 获取一个商品所有套系中的最低价格
    public function getLowPrice ($coupon_id) {
        RedCache::fromCacheData('G:goodLowPrice:' . $coupon_id, function () use ($coupon_id) {
            $pdo = $this->_getAdapter();

            $sql = "SELECT * FROM play_game_info WHERE gid = ? AND (total_num > buy) AND status = 1 AND end_time > ? AND price = (SELECT MIN(price) FROM play_game_info WHERE gid = ? AND (total_num > buy) AND status = 1 AND end_time > ?) ORDER BY id DESC LIMIT 1";

            $data_return = $pdo->query($sql, array($coupon_id, time(), $coupon_id, time()))->current();

            $sql = " UPDATE play_organizer_game SET low_price = ?, low_money = ? WHERE id = ?";

            $data_return_update = $pdo->query($sql, array($data_return->price, $data_return->money, $coupon_id));

            return true;
        }, 300, true);
    }

    public function getallusetime(){

    }

    public function getallorder(){

    }






}



