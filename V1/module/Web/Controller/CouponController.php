<?php

namespace Web\Controller;

use Deyi\BaseController;
use Deyi\JsonResponse;
use library\Service\System\Cache\RedCache;
use Deyi\Upload;
use Deyi\WeiXinFun;
use Deyi\WriteLog;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Deyi\Mcrypt;
use Zend\Db\Sql\Expression;

class CouponController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    /**
     * @param $sql
     * @return Result;
     */
    function query($sql)
    {
        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $stmt = $db->query($sql);
        $result = $stmt->execute($stmt);
        return $result;
    }


    //获取专题下的游玩地
    public function getPlace($id)
    {


        $sql = "SELECT
	play_region.`name`,
	play_shop.shop_id,
	play_shop.shop_name,
	play_shop.reticket,
	play_shop.editor_word,
	play_shop.thumbnails,
	play_shop.reference_price,
	play_shop.cover,
	count(DISTINCT play_game_info.gid) AS count_num
FROM
	play_activity_coupon
LEFT JOIN play_shop ON play_activity_coupon.cid = play_shop.shop_id
LEFT JOIN play_region ON play_shop.busniess_circle = play_region.rid
LEFT JOIN play_game_info ON play_shop.shop_id = play_game_info.shop_id
WHERE
	play_activity_coupon.aid = $id
AND play_activity_coupon.type = 'place'
GROUP BY
	play_shop.shop_id
ORDER BY
	play_shop.shop_id DESC
LIMIT 100";


        $res = $this->query($sql);
        $data = array();
        foreach ($res as $val) {
            $data[] = array(
                'place_id' => $val['shop_id'],
                'place_name' => $val['shop_name'],
                'place_cover' => $val['cover'],
                'place_price' => $val['reference_price'],
                'circle' => $val['name'],
                'editor_word' => $val['editor_word'],
                'reticket' => 'reticket'
            );
        }
        return $data;
    }

    //获取专题下的活动
    public function getCoupon($id)
    {
        $sql = "SELECT
play_organizer_game.title,
play_organizer_game.editor_talk,
play_organizer_game.organizer_id,
play_organizer_game.id as game_id,
play_organizer_game.low_price,
play_organizer_game.low_money,
play_organizer_game.ticket_num,
play_organizer_game.buy_num,
play_organizer_game.shop_addr,
play_organizer_game.thumb
FROM
play_activity_coupon
LEFT JOIN play_organizer_game ON play_activity_coupon.cid = play_organizer_game.id
WHERE
play_activity_coupon.aid = $id AND
play_activity_coupon.type = 'game'
ORDER BY
play_organizer_game.buy_num DESC
LIMIT 100
";

        $res = $this->query($sql);
        $data = array();
        foreach ($res as $val) {
            $data[] = array(
                'good_id' => $val['organizer_id'],
                'game_id' => $val['game_id'],
                'good_name' => $val['title'],
                'editor_word' => $val['editor_talk'],
                'good_price' => $val['low_price'],
                'good_cover' => $val['thumb'],
                'discount' => round($val['low_price'] / $val['low_money'] * 10, 1),
                'good_have' => $val['ticket_num'] - $val['buy_num'],
                'circle' => $val['shop_addr']
            );
        }
        return $data;
    }

    //专题下游玩地关联的活动
    public function getPlaceJoinCoupon($id)
    {


        $sql = "SELECT
play_organizer_game.title,
play_organizer_game.editor_talk,
play_organizer_game.organizer_id,
play_organizer_game.id as game_id,
play_organizer_game.low_price,
play_organizer_game.low_money,
play_organizer_game.ticket_num,
play_organizer_game.buy_num,
play_organizer_game.shop_addr,
play_organizer_game.thumb
FROM
	play_activity_coupon
LEFT JOIN play_shop ON play_activity_coupon.cid = play_shop.shop_id

LEFT JOIN play_game_info ON play_game_info.shop_id=play_shop.shop_id
LEFT JOIN play_organizer_game ON play_organizer_game.id = play_game_info.gid
WHERE
	play_activity_coupon.aid = $id
AND play_activity_coupon.type = 'place'
AND play_organizer_game.organizer_id
GROUP BY
    play_organizer_game.id
ORDER BY
	play_shop.shop_id DESC
LIMIT 100";


        $res = $this->query($sql);
        $data = array();
        foreach ($res as $val) {
            $data[] = array(
                'good_id' => $val['organizer_id'],
                'game_id' => $val['game_id'],
                'good_name' => $val['title'],
                'editor_word' => $val['editor_talk'],
                'good_price' => $val['low_price'],
                'good_cover' => $val['thumb'],
                'discount' => round($val['low_price'] / $val['low_money'] * 10, 1),
                'good_have' => $val['ticket_num'] - $val['buy_num'],
                'circle' => $val['shop_addr']
            );
        }
//        var_dump($data);exit;
        return $data;
    }

    //专题下活动关联的游玩地
    public function getCouponJoinPlace($id)
    {

        $sql = "SELECT
	play_shop.shop_id,
	play_shop.shop_name,
	play_shop.reticket,
	play_shop.editor_word,
	play_shop.thumbnails,
	play_shop.reference_price
FROM
play_activity_coupon
LEFT JOIN play_organizer_game ON play_activity_coupon.cid = play_organizer_game.id
LEFT JOIN play_game_info ON play_game_info.gid = play_organizer_game.id
LEFT JOIN play_shop ON play_shop.shop_id=play_game_info.shop_id

WHERE
play_activity_coupon.aid = $id AND
play_activity_coupon.type = 'game' AND
play_shop.shop_id
GROUP BY
play_shop.shop_id
ORDER BY
play_organizer_game.buy_num DESC
LIMIT 100
";

        $res = $this->query($sql);
        $data = array();
        foreach ($res as $val) {
            $data[] = array(
                'place_id' => $val['shop_id'],
                'place_name' => $val['shop_name'],
                'place_cover' => $val['thumbnails'],
                'place_price' => $val['reference_price'],
                'circle' => $val['name'],
                'editor_word' => $val['editor_word'],
                'reticket' => 'reticket'
            );
        }
        return $data;

    }

    //新专题分享页面
    public function newactivityAction()
    {
        $id = (int)$this->getQuery('id');//专题id

        if (!$id) {
            return $this->_Goto('该专题不存在');
        }

        //redirect - 2016-09-08 - qintao
        $target_url = $this->_getConfig()['url'] . "/web/tag/info?id={$id}";
        header("Location: {$target_url}");


        //记录专题点击次数
        $this->_getPlayActivityTable()->update(array('activity_click' => new Expression('activity_click+1')), array('id' => $id));

        //专题数据
        $activity_data = $this->_getPlayActivityTable()->get(array('play_activity.id' => $id));

        $data = array(
            'title' => $activity_data->ac_name, //标题
            'cover' => $this->_getConfig()['url'] . $activity_data->ac_cover, //图片
            'introduce' => $activity_data->introduce, //简介
            'like_number' => $activity_data->like_number,
            'is_like' => 0,
            'good_list' => array(),
            'place_list' => array(),
            'share_title' => '带着孩子一起来玩吧，' . $activity_data->ac_name,
            'share_content' => $activity_data->introduce,
            'share_image' => $this->_getConfig()['url'] . $activity_data->ac_cover,
            'view_type' => $activity_data->view_type,
        );


        if ($activity_data->view_type == 1) {
            $data['place_list'] = $this->getPlace($id);
            $data['good_list'] = $this->getPlaceJoinCoupon($id);
        } else if ($activity_data->view_type == 2) {
            $data['place_list'] = $this->getCouponJoinPlace($id);
            $data['good_list'] = $this->getCoupon($id);
        } elseif ($activity_data->view_type == 3) {
            $data['place_list'] = $this->getPlace($id);
            $data['good_list'] = $this->getPlaceJoinCoupon($id);
        } elseif ($activity_data->view_type == 4) {
            $data['place_list'] = $this->getCouponJoinPlace($id);
            $data['good_list'] = $this->getCoupon($id);
        }


        return $data;


    }

    public function indexAction()
    {

        $id = (int)$this->getQuery('id');
        $data = $this->_getPlayCouponsTable()->get(array('coupon_id' => $id));
        if (!$data) {
            exit('<h1>该商品不存在</h1>');
        }

        $res = array(
            'title' => $data->coupon_name,
            'buy' => ($data->coupon_buy + $data->coupon_vir),
            'residue' => (($data->coupon_total - $data->coupon_buy) > 0) ? ($data->coupon_total - $data->coupon_buy) : 0,
            'price' => $data->coupon_price,
            'originalprice' => $data->coupon_originprice,
            'endtime' => $data->coupon_endtime,
            'description' => htmlspecialchars_decode($data->coupon_description),
            'editor_word' => str_replace(array("<br/>", "&nbsp;", "\r\n"), array(' ', ' ', ' '), htmlspecialchars_decode($data->editor_word)),
            'age_max' => $data->age_max,
            'for_age' => ($data->age_max == 100) ? ($data->age_min . '岁及以上') : ($data->age_min . '岁到' . $data->age_max . '岁'),
            'use_time' => htmlspecialchars_decode($data->use_time),
            'attend_method' => str_replace(array("<br/>", "&nbsp;", "\r\n"), array(' ', ' ', ' '), htmlspecialchars_decode($data->attend_method)),
            'matters_attention' => str_replace(array("<br/>", "&nbsp;", "\r\n"), array(' ', ' ', ' '), htmlspecialchars_decode($data->matters_attention)),
            'discount' => round($data->coupon_price / $data->coupon_originprice * 10, 1),
            'coupon_join' => $data->coupon_join,
            'shop_list' => array(), //相关店铺
            'coupon_list' => array(), //相关卡圈
            'coupon_starttime' => $data->coupon_starttime

        );
        $res['res_time'] = ($data->coupon_endtime - time() > 0) ? $this->runTimeAction($data->coupon_endtime - time()) : '已结束';

        $shopIds = $this->_getPlayCouponsLinkerTable()->fetchAll(array('coupon_id' => $id));

        foreach ($shopIds as $value) {
            $shopData = $this->_getPlayShopTable()->get(array('shop_id' => $value->shop_id));
            if ($shopData) {
                $res['shop_list'][] = array(
                    'shopname' => $shopData->shop_name,
                    'opentime' => $shopData->shop_open,
                    'endtime' => $shopData->shop_close,
                    'address' => $shopData->shop_address,
                    'phone' => $shopData->shop_phone,
                    'shop_id' => $value->shop_id,
                );
            }
        }

        //相关票券
        $res['coupon_list'] = $this->_getPlayCouponsTable()->getAdminShopCouponList($data->coupon_shopid, $id, $this->_getConfig()['url']);


        $toUrl = $this->_getConfig()['url'] . '/web/wappay/ticketbuy?couponId=' . $id;
        $weixin = new WeiXinFun($this->getwxConfig());
        if (!$this->checkWeiXinUser()) {
            $toUrl = $weixin->getAuthorUrl($toUrl, 'snsapi_userinfo');
        }
        $vm = new viewModel(array(
            'res' => $res,
            'toUrl' => $toUrl,
            'jsApi' => $weixin->getsignature(),
            'share' => array(
                'title' => $res['share'] = ($data->coupon_join == 1) ? '惊爆价' . $res['price'] . '元 ' . '"' . $res['title'] . '"' . '-玩翻天' : $res['title'] . '-玩翻天',
                'img' => $data->coupon_thumb ? $this->_getConfig()['url'] . $data->coupon_thumb : '',
                'toUrl' => 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']
            )
        ));

        $vm->setTerminal(true);
        return $vm;
    }


    public function activityAction()
    {
        $id = (int)$this->getQuery('id');

        //专题数据
        $activity_data = $this->_getPlayActivityTable()->getList(0, 1, array(), array('play_activity.id' => $id))->current();
        if (!$activity_data) {
            exit('<h1>该专题不存在</h1>');
        }
        $order = array('ac_sort' => 'asc');

        $where = array(
            'play_coupons.coupon_status = ?' => 1,
            'play_coupons.coupon_uptime <= ?' => time(),
            'play_activity_coupon.aid' => $id,
            'play_activity_coupon.type' => 'coupon',
        );


        //专题下的卡券
        $wait = $this->_getPlayActivityCouponTable()->getApiCouponList(0, 100, array(), $where, $order);

        $wait_data = array();
        foreach ($wait as $v) {
            $wait_data[] = array(
                'coupon_id' => $v->coupon_id,
                'coupon_name' => $v->coupon_name,
                'editor_word' => $v->editor_word,  //小编说
                'coupon_price' => $v->coupon_price,
                'coupon_cover' => $v->coupon_thumb ? $this->_getConfig()['url'] . $v->coupon_thumb : $this->_getConfig()['url'] . $v->coupon_cover . '.thumb.jpg',
                'discount' => round($v->coupon_price / $v->coupon_originprice * 10, 1),
                'coupon_starttime' => $v->coupon_starttime,//开始时间
                'endtime' => $v->coupon_endtime, //结束时间
                'coupon_total' => $v->coupon_total, //卡券总数
                'coupon_buy' => $v->coupon_buy,    //购买数
            );
        }

        $data = array(
            'id' => $activity_data->id,
            'title' => $activity_data->ac_name,
            'cover' => $this->_getConfig()['url'] . $activity_data->ac_cover,
            'introduce' => $activity_data->introduce,
            'count' => $activity_data->count_number,
            'like_number' => $activity_data->like_number,
            'image' => $activity_data->image ? $this->_getConfig()['url'] . $activity_data->image : $this->_getConfig()['url'] . '/images/ico_avatar_default.png',
            'coupon_list' => $wait_data,
        );


        //专题下的资讯
        $newsWhere = array(
            'play_activity_coupon.aid' => $id,
            'play_activity_coupon.type' => 'news',
            'play_news.status' => 1

        );
        $news_res = $this->_getPlayActivityCouponTable()->getWebNewsList(0, 100, array(), $newsWhere, $order);

        $news_data = array();
        $news_id_list = array();
        foreach ($news_res as $v) {
            $news_id_list[] = $v->id;
            $news_data[] = array(
                'news_id' => $v->id,
                'news_name' => $v->title,
                'editor_word' => $v->editor_word,
                'news_cover' => $v->surface_plot ? $this->_getConfig()['url'] . $v->surface_plot : '',
                'view_nums' => $v->view_nums,
                'dateline' => $v->dateline,
            );
        }

        $data['news_list'] = $news_data;
        $vm = new viewModel(array(
            'data' => $data,
        ));
        $vm->setTerminal(true);
        return $vm;
    }


    public function newsAction()
    {
        $nid = (int)$this->getQuery('id');
        $data = $this->_getPlayNewsTable()->get(array('id' => $nid));
        if (!$data) {
            exit('<h1>该商品不存在</h1>');
        }

        $res = array(
            'nid' => $nid,
            'cover' => $this->_getConfig()['url'] . $data->cover,
            'title' => $data->title,
            'introduce' => $data->editor_word,
            'reference_price' => (float)$data->reference_price,
            'for_age' => ($data->age_max == 100) ? ($data->age_min . '岁及以上') : ($data->age_min . '岁到' . $data->age_max . '岁'),
            'information' => $data->information,
        );

        // 地址
        $address = json_decode($data->address);
        $res['address'] = array();
        if ($address->type == 1) {
            $res['address']['type'] = 1;
            $res['address']['mes'] = array(
                'name' => $address->mes->address,
                'x' => $address->mes->x,
                'y' => $address->mes->y,
            );
        } elseif ($address->type == 2) {
            $res['address']['type'] = 2;
            $res['address']['mes'] = $address->adr;
        }

        //评论
        $postData = $this->_getPlayPostTable()->fetchLimit(0, 3, array(), $where = array('object_id' => $nid, 'type' => 'news', 'displayorder > ?' => 0), $order = array('displayorder' => 'desc', 'dateline' => 'desc'))->toArray();
        $res['post_num'] = $this->_getPlayPostTable()->fetchCount(array('object_id' => $nid, 'type' => 'news', 'displayorder > ?' => 0));

        $res['post'] = array();
        foreach ($postData as $v) {
            $i_list = json_decode($v['photo_list'], true);
            $img_list = array();
            if ($i_list) {
                foreach ($i_list as $i_v) {
                    $img_list[] = $this->_getConfig()['url'] . $i_v . '.thumb.jpg';
                }
            }
            $res['post'][] = array(
                'uid' => $v['uid'],
                'author' => $v['author'],
                'author_img' => $v['img'],
                'dateline' => $this->dateTimeAction($v['dateline']),
                'message' => $v['message'],
                'img_list' => $img_list
            );
        }
        $res['post'] = array_slice($res['post'], 0, 3);
        $vm = new viewModel(array(
            'res' => $res,
        ));
        $vm->setTerminal(true);
        return $vm;
    }

    public function newerAction()
    {
        $nid = (int)$this->getQuery('id');
        $data = $this->_getPlayNewsTable()->get(array('id' => $nid));
        if (!$data) {
            exit('<h1>该商品不存在</h1>');
        }

        $res = array(
            'nid' => $nid,
            'cover' => $this->_getConfig()['url'] . $data->cover,
            'title' => $data->title,
            'introduce' => $data->editor_word,
            'reference_price' => (float)$data->reference_price,
            'for_age' => ($data->age_max == 100) ? ($data->age_min . '岁及以上') : ($data->age_min . '岁到' . $data->age_max . '岁'),
            'information' => $data->information,
        );

        // 地址
        $address = json_decode($data->address);
        $res['address'] = array();
        if ($address->type == 1) {
            $res['address']['type'] = 1;
            $res['address']['mes'] = array(
                'name' => $address->mes->address,
                'x' => $address->mes->x,
                'y' => $address->mes->y,
            );
        } elseif ($address->type == 2) {
            $res['address']['type'] = 2;
            $res['address']['mes'] = $address->adr;
        }

        //评论
        $postData = $this->_getPlayPostTable()->fetchLimit(0, 3, array(), $where = array('object_id' => $nid, 'type' => 'news', 'displayorder > ?' => 0), $order = array('displayorder' => 'desc', 'dateline' => 'desc'))->toArray();
        $res['post_num'] = $this->_getPlayPostTable()->fetchCount(array('object_id' => $nid, 'type' => 'news', 'displayorder > ?' => 0));

        $res['post'] = array();
        foreach ($postData as $v) {
            $i_list = json_decode($v['photo_list'], true);
            $img_list = array();
            if ($i_list) {
                foreach ($i_list as $i_v) {
                    $img_list[] = $this->_getConfig()['url'] . $i_v . '.thumb.jpg';
                }
            }
            $res['post'][] = array(
                'uid' => $v['uid'],
                'author' => $v['author'],
                'author_img' => $v['img'],
                'dateline' => $this->dateTimeAction($v['dateline']),
                'message' => $v['message'],
                'img_list' => $img_list
            );
        }
        $res['post'] = array_slice($res['post'], 0, 3);
        $vm = new viewModel(array(
            'res' => $res,
        ));
        $vm->setTerminal(true);
        return $vm;
    }

    private function runTimeAction($time)
    {
        $str = "";
        /*
        if($time >= 86400){
            $str = floor($time / 86400) . " ";
            $time = $time % 86400;
        }*/

        if ($time >= 3600) {
            $str .= floor($time / 3600) . ":";
            $time = $time % 3600;
        } else {
            $str .= "0:";
        }
        if ($time >= 60) {
            $str .= floor($time / 60) . ":";
            $time = $time % 60;
        } else {
            $str .= "0:";
        }

        if ($time > 0) {
            $str .= $time;
        } else {
            $str .= "0";
        }
        return $str;
    }

    private function dateTimeAction($time)
    {
        $curTime = time();
        $diff = $curTime - $time;
        // $second = $diff < 60 ? $diff : 0;
        $minute = ceil($diff / 60);
        $hour = $minute > 59 ? ceil($minute / 60) : 0;
        $day = $hour > 23 ? ceil($hour / 24) : 0;

        return $day > 0 ? $day . '天前' : ($hour > 0 ? $hour . '小时前' : ($minute > 0 ? $minute . '分钟前' : ''));
    }


    //所有票券
    public function allcouponAction(){
        $url = $this->_getConfig()['url'] . "/web/tag/allcoupon";
        $weixin = new WeiXinFun($this->getwxConfig());
        $toUrl = $weixin->getAuthorUrl($url, 'snsapi_userinfo');
        //分享
        $jswxconfig = $this->getShareInfoAction()[1];
        $share = array(
            'img'=>$this->_getConfig()['url'].'/images/80.png',
            'title'=>'【玩翻天】孩子们的游玩管家',
            'desc'=>'我发现了一个家长必备的遛娃神器！告别无趣，拯救宅神！快来带娃玩翻天！',
            'link'=>$this->_getConfig()['url'].'/web/wappay/nindex',
        );
        $vm = new ViewModel([
            'authorUrl'=>$toUrl,
            'share_type'=>'nindex',
            'share_id'=>0,
            'jsconfig'=>$jswxconfig,
            'share'=>$share,
        ]);
        $vm->setTerminal(true);
        return $vm;
    }

}
