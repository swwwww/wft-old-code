<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ApiCoupon\Controller;

use Application\Module;
use Deyi\BaseController;
use Deyi\GetCacheData\Labels;
use library\Fun\Common;
use library\Fun\M;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Response;
use Deyi\JsonResponse;
use Deyi\GetCacheData\CouponCache;
use Deyi\GetCacheData\GoodCache;
use Deyi\GetCacheData\PlaceCache;
use Deyi\GetCacheData\UserCache;
use library\Service\System\Cache\RedCache;

class HomeController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;


    public function getweatherName($v)
    {
        if ($v == '小到中雨转暴雨') {
            return '中雨转暴雨';
        } else if ($v == '小到中雨转阵雨') {
            return '中雨转阵雨';
        } else {
            return $v;
        }

    }

    //首页接口
    public function indexAction()
    {
        if (!$this->pass(false)) {
            return $this->failRequest();
        }

        $city = $this->getCity();
        $page = $this->getParams('page', 1);
        $pageNum = $this->getParams('pagenum', 5);
        //$id = (int)$this->getParams('id', 0);
        $uid = (int)$this->getParams('uid', 0);
        $time = time();
        $WeatherAttribute = $this->getWeatherAttribute();
        $data = array();

        $page = ($page > 1) ? $page : 1;
        // 取出用户宝宝的年龄
        $userCache = new UserCache();
        //$baby_max = $userCache->getBabyAge($uid)['max'];
        //$baby_min = $userCache->getBabyAge($uid)['min'];

        //去掉年龄筛选
        $baby_max = 100;
        $baby_min = 0;


        //首页焦点图
        if ($page == 1) {
            $focus_flag = $this->getFocus($city, $time);
            if ($focus_flag) {
                $data['maps'] = $focus_flag;
            }
        }

        //图标块
        if ($page == 1) {
            $module_pic_flag = $this->getIcon($city, $time);
            if ($module_pic_flag) {
                $data['module_pic'] = $module_pic_flag;
            }
        }

        //悬浮
        if ($page == 1) {
            $float_img = $this->getFloat($city);
            if ($float_img) {
                $data['float_img'] = $float_img;
            }
        }

        //今日头条(公告)
        if ($page == 1) {
            $top_talk = $this->getNotice($city, $time);
            if ($top_talk) {
                $data['top_talk'] = $top_talk;
            }
        }

        $place_where = '';
        $good_where = '';
        if ($baby_max && $baby_min) {
            $place_where = " AND ((play_shop.age_min <= {$baby_max} and play_shop.age_max >= {$baby_min}))";
            $good_where = "  AND ((play_organizer_game.age_min <= {$baby_max} and play_organizer_game.age_max >= {$baby_min}))";
        }


        //优惠
        if ($page == 1) {
            $sale_list = $this->getOnSale($city, $time, $good_where);
            if ($sale_list) {
                $data['sale_list'] = $sale_list;
            }
        }

        //精选

        $start = ($page - 1) * $pageNum;

        $choice_list = $this->getSelected($city, $time, $place_where, $good_where, $start, $pageNum);

        if ($choice_list) {
            $data['choice_list'] = $choice_list;
        } else {
            $data['choice_list'] = array();
        }

        return $this->jsonResponse($data);
    }


    //点赞接口
    public function likeAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = (int)$this->getParams('uid');
        $like_id = (int)$this->getParams('like_id');
        $type = $this->getParams('type', 'activity');

        if (!$uid or !$like_id) {
            return $this->jsonResponseError('参数错误');
        }
        $s = $this->_getPlayLikeTable()->get(array('uid' => $uid, 'like_id' => $like_id, 'type' => $type));

        if ($s) {
            return $this->jsonResponse(array('status' => 0, 'message' => '已经赞过'));
        }

        $s1 = $this->_getPlayLikeTable()->insert(array(
            'uid' => $uid,
            'like_id' => $like_id,
            'type' => $type,
            'dateline' => time()
        ));
        if ($s1) {
            if ($type == 'activity') {
                $s2 = $this->_getPlayActivityTable()->update(array('like_number' => new Expression('like_number+1')),
                    array('id' => $like_id));
                if ($s2) {
                    return $this->jsonResponse(array('status' => 1, 'message' => '点赞成功'));
                }
            }
            // 其他类型
        }

        return $this->jsonResponse(array('status' => 0, 'message' => '点赞失败'));
    }

    //取消点赞接口
    public function removelikeAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = (int)$this->getParams('uid');
        $like_id = (int)$this->getParams('like_id');
        $type = $this->getParams('type', 'activity');

        if (!$uid or !$like_id) {
            return $this->jsonResponseError('参数错误');
        }


        $s1 = $this->_getPlayLikeTable()->delete(array('uid' => $uid, 'like_id' => $like_id, 'type' => $type));

        if ($s1) {
            if ($type == 'activity') {
                $s2 = $this->_getPlayActivityTable()->update(array('like_number' => new Expression('like_number-1')),
                    array('id' => $like_id, 'like_number>0'));
                if ($s2) {
                    return $this->jsonResponse(array('status' => 1, 'message' => '取消点赞成功'));
                }
            }
// 其他类型
        }

        return $this->jsonResponse(array('status' => 0, 'message' => '取消点赞失败'));
    }


    //type 类别 1 专题 2 卡券 3 资讯 4 游玩地 5 商品 6 html5页面 7话题  8 圈子 9邀请 10积分 11账户 12秒杀 13优惠券 14游玩地类别

    //首页焦点图 link_type = 2
    private function getFocus($city, $time)
    {
        $focus_sql = "SELECT
play_index_block.type,
play_index_block.link_img,
play_index_block.url,
play_index_block.link_id
FROM play_index_block
LEFT JOIN play_activity ON (play_activity.id = play_index_block.link_id AND play_index_block.type = 1)
LEFT JOIN play_shop ON (play_shop.shop_id = play_index_block.link_id AND play_index_block.type = 4)
LEFT JOIN play_organizer_game ON (play_organizer_game.id = play_index_block.link_id AND play_index_block.type = 5)
LEFT JOIN play_excercise_base ON (
	play_excercise_base.id = play_index_block.link_id
	AND play_index_block.type = 16
)

WHERE
play_index_block.block_city = '{$city}' AND play_index_block.link_type = 2 AND play_index_block.status > 0
AND  ((play_index_block.type = 1 AND play_activity.status >= 0 AND ((play_activity.s_time < {$time} && play_activity.e_time > {$time}) || (play_activity.s_time = 0 && play_activity.e_time = 0)))
OR  (play_index_block.type = 4 AND play_shop.shop_status >= 0)
OR  (play_index_block.type = 5 AND play_organizer_game.status > 0 && play_organizer_game.start_time < {$time} && play_organizer_game.end_time > {$time})
OR  (play_index_block.type = 6 || play_index_block.type = 7 || play_index_block.type = 8)
OR (play_index_block.type = 16	AND play_excercise_base.release_status >= 0)
)
ORDER BY
play_index_block.status DESC, play_index_block.dateline DESC
";

        $focusMaps = $this->query($focus_sql);

        $data = null;
        if (!$focusMaps->count()) {
            return $data;
        }

        foreach ($focusMaps as $maps) {
            $data[] = array(
                'url' => htmlspecialchars_decode($maps['url']),
                'cover' => $this->_getConfig()['url'] . $maps['link_img'],
                'type' => $maps['type'],
                'id' => $maps['link_id'],
            );
        }
        return $data;
    }

    //获得优惠数据
    private function getOnSale($city, $time, $good_where)
    {

        $sale_sql = "select
play_index_block.block_title,
play_index_block.link_img,
play_index_block.link_id,
play_organizer_game.low_price
from
  play_index_block
LEFT JOIN play_organizer_game ON play_index_block.link_id = play_organizer_game.id
WHERE
play_index_block.block_city = '{$city}' AND play_index_block.link_type = 4 AND play_index_block.type = 5
AND play_index_block.status > 0 AND (play_index_block.end_time = 0 OR play_index_block.end_time > {$time})
AND ((play_organizer_game.is_together = 1 AND play_organizer_game.status > 0 AND play_organizer_game.start_time < {$time} AND play_organizer_game.end_time > {$time}) OR play_organizer_game.is_together = 2)
AND ((play_index_block.is_top = 1) OR (play_index_block.is_top = 0 $good_where))
ORDER BY
play_index_block.status DESC, play_index_block.dateline DESC
";

        $saleList = $this->query($sale_sql);

        $data = null;

        if (!$saleList->count()) {
            return $data;
        }

        foreach ($saleList as $sale) {
            $data_low_money = sprintf('%.2f', $sale['low_price']);

            $data[] = array(
                'cover' => $this->_getConfig()['url'] . $sale['link_img'],
                'id' => $sale['link_id'],
                'price' => $data_low_money,
                'title' => $sale['block_title'],
            );
        }
        return $data;
    }

    //获得浮动框
    private function getFloat($city)
    {
        $floatData = $this->_getPlayIndexBlockTable()->get(array('block_city' => $city, 'link_type' => 7, 'status > 0'));
        $data = null;
        if (!$floatData) {
            return $data;
        } else {
            $cover = array();
            foreach (json_decode($floatData->mult_covers, true) as $co) {
                $cover[] = $this->_getConfig()['url'] . $co;
            }
            $data = array(
                'id' => $floatData->link_id,
                'cover' => $cover,
                'type' => $floatData->type,
                'url' => htmlspecialchars_decode($floatData->url),
            );
        }
        return $data;

    }

    //获得公告数据
    private function getNotice($city, $time)
    {
        $talk_sql = "SELECT
play_index_block.type,
play_index_block.block_title,
play_index_block.url,
play_index_block.link_id
FROM play_index_block
LEFT JOIN play_activity ON (play_activity.id = play_index_block.link_id AND play_index_block.type = 1)
LEFT JOIN play_shop ON (play_shop.shop_id = play_index_block.link_id AND play_index_block.type = 4)
LEFT JOIN play_organizer_game ON (play_organizer_game.id = play_index_block.link_id AND play_index_block.type = 5)
WHERE
play_index_block.block_city = '{$city}' AND play_index_block.link_type = 3 AND play_index_block.status > 0
AND  ((play_index_block.type = 1 AND play_activity.status >= 0 AND ((play_activity.s_time < {$time} && play_activity.e_time > {$time}) || (play_activity.s_time = 0 && play_activity.e_time = 0)))
OR  (play_index_block.type = 4 AND play_shop.shop_status >= 0)
OR  (play_index_block.type = 5 AND play_organizer_game.status > 0 && play_organizer_game.start_time < {$time} && play_organizer_game.end_time > {$time})
OR  (play_index_block.type = 6 || play_index_block.type = 7 || play_index_block.type = 8)
)
ORDER BY
play_index_block.status DESC, play_index_block.dateline DESC
";

        $topTalk = $this->query($talk_sql);

        $data = null;
        if (!$topTalk->count()) {
            return $data;
        }

        foreach ($topTalk as $talk) {
            $data[] = array(
                'id' => $talk['link_id'],
                'type' => $talk['type'],
                'title' => $talk['block_title'],
                'url' => $talk['url'],
            );
        }
        return $data;
    }

    //获取精选
    private function getSelected($city, $time, $place_where, $good_where, $start, $pageNum)
    {
        $choice_sql = "SELECT
play_index_block.id,
play_index_block.type,
play_index_block.link_id,
play_index_block.block_title,
play_index_block.tip,
play_index_block.url,
play_index_block.link_img,
play_organizer_game.low_price,
play_organizer_game.ticket_num,
play_organizer_game.buy_num,
play_organizer_game.coupon_vir,
play_organizer_game.post_award,
play_organizer_game.is_together,
play_organizer_game.special_labels as s1,
play_excercise_base.id as bid,

play_excercise_base.all_number,
play_excercise_base.join_number,
play_excercise_base.vir_number,
play_excercise_base.circle,
play_excercise_base.max_start_time,
play_excercise_base.min_end_time,
play_excercise_base.low_price as low_price2,
play_excercise_base.most_number,
play_excercise_base.custom_tags,
play_excercise_base.special_labels as s2,
play_excercise_base.free_coupon_event_count

FROM play_index_block
LEFT JOIN play_activity ON (play_activity.id = play_index_block.link_id AND play_index_block.type = 1)
LEFT JOIN play_shop ON (play_shop.shop_id = play_index_block.link_id AND play_index_block.type = 4)
LEFT JOIN play_organizer_game ON (play_organizer_game.id = play_index_block.link_id AND play_index_block.type = 5)
LEFT JOIN play_excercise_base ON (
	play_excercise_base.id = play_index_block.link_id
	AND play_index_block.type = 16
)
WHERE
play_index_block.block_city = '{$city}' AND play_index_block.link_type = 1 AND play_index_block.status > 0 AND (play_index_block.end_time = 0 OR play_index_block.end_time > {$time})
AND  ((play_index_block.type = 1 AND play_activity.status >= 0 AND ((play_activity.s_time < {$time} && play_activity.e_time > {$time}) || (play_activity.s_time = 0 && play_activity.e_time = 0)))
OR  (play_index_block.type = 4 AND play_shop.shop_status >= 0 {$place_where})
OR  (play_index_block.type = 5 AND ((play_organizer_game.is_together = 1 AND play_organizer_game.status > 0 AND play_organizer_game.start_time < {$time} AND
play_organizer_game.end_time > {$time}) OR play_organizer_game.is_together = 2) AND ((play_index_block.is_top = 1) OR (play_index_block.is_top = 0 {$good_where})))
OR  (play_index_block.type = 6 || play_index_block.type = 7 || play_index_block.type = 8)
OR (play_index_block.type = 16	AND play_excercise_base.release_status >= 0)
)
ORDER BY
play_index_block.status DESC, play_index_block.dateline DESC
LIMIT $start, $pageNum
";
        $data = null;
        $choiceData = $this->query($choice_sql);

        if (!$choiceData->count()) {
            return $data;
        }

        $placeCache = new PlaceCache();

        $class_goodcache = new GoodCache();

        foreach ($choiceData as $choice) {
            if ($choice['type'] == 16) {

                $d=$this->getActivityData($choice['link_id']);

                $tags = Labels::getLabels($choice['s2']);
                $data_tags = array();
                if (empty($choice['custom_tags'])) {
                    $data_tags = array();
                } else {
                    $data_tags = explode(',', $choice['custom_tags']);
                }

                //活动  1 活动报名中 2已有N人报名 3停止报名 4
                //商品  1.有票 2.已售n份 3.停止报名
                $data_low_money = sprintf('%.2f', $d['low_money']);

                $res=$this->getStatus($choice['link_id'],$choice['type']);

                //没有可售卖场次
                if($res['sell_num']==0){
                    $this->_getPlayIndexBlockTable()->delete(array('id'=>$choice['id']));
                }
                $data[] = array(
                    'only_id' => $choice['id'],
                    'type' => $choice['type'],
                    'id' => $choice['link_id'],
                    'title' => $choice['block_title'],
                    'cover' => $this->_getConfig()['url'] . $choice['link_img'],
                    'introduce' => $choice['tip'],
                    'url' => $choice['url'],
                    'prise' => array(),
                    'note' =>$choice['circle'] ,
                    'low_money' => $data_low_money,
                    'total_num' => $choice['most_number'],
                    'session_str' => date('m月d日',$choice['max_start_time']).'-'.date('m月d日',$choice['min_end_time']).'　'.$choice['all_number'].'场可选',
                    'buy_num' =>  (int)($choice['join_number']+$choice['vir_number']),
                    'status'=>$res['status'],
                    'tags' => $data_tags,
                    "n_tags" =>  $tags,
                    "vip_free" => (int)$choice['free_coupon_event_count']
                );
            }else{
                $class_goodcache->getLowPrice($choice['link_id']);
                $data_low_money = sprintf('%.2f', $choice['low_price']);

                $res=$this->getStatus($choice['link_id'], $choice['type']);

                //没有可售卖场次
                if($res['sell_num']==0){
                    $this->_getPlayIndexBlockTable()->delete(array('id'=>$choice['id']));
                }

                $tags = Labels::getLabels($choice['s2']);
                $old_tags=[];
                foreach ($tags as $vv) {
                    $old_tags[] = $vv['name'];
                }

                $data[] = array(
                    'only_id' => $choice['id'],
                    'type' => $choice['type'],
                    'id' => $choice['link_id'],
                    'title' => $choice['block_title'],
                    'cover' => $this->_getConfig()['url'] . $choice['link_img'],
                    'introduce' => $choice['tip'],
                    'url' => $choice['url'],
                    'prise' => ($choice['type'] == 5) ? GoodCache::getGameTags($choice['link_id'], $choice['post_award']) : (($choice['type'] == 4) ? $placeCache->getShopTags($choice['link_id']) : array()),
                    'note' => ($choice['type'] == 4) ? $placeCache->getPlaceCircle($choice['link_id']) : '',
                    'session_str' => '',
                    'low_money' => ($choice['type'] == 5) ? $data_low_money : '0',
                    'total_num' => ($choice['type'] == 5 && $choice['is_together'] == 1) ? $choice['ticket_num'] : '0',
                    'buy_num' => ($choice['type'] == 5 && $choice['is_together'] == 1) ? $choice['buy_num'] + $choice['coupon_vir'] : '0',
                    'status'=>$res['status'],
                    'tags' => $old_tags,
                    "n_tags" =>  $tags,
                    "vip_free" => 0
                );
            }
        }

        return $data;
    }

    //获取对应数据状态
    public function getStatus($id,$type){


        //活动  1 活动报名中 2已有N人报名 3停止报名
        //商品  1.有票 2.已售n份 3.停止报名

        $status_1=0;
        $status_2=0;
        $status_3=0;
        $sell_num=0;
        if($type==16){
            $db=$this->_getAdapter();
            //取活动所有场次 循环判断
            $res = $db->query("SELECT
	b.join_number,b.low_price,b.all_number,b.name,b.id AS bbid,b.min_end_time,max_start_time,thumb,cover,circle,e.open_time,e.over_time,e.join_number,e.perfect_number,e.vir_number,
	e.id as eid
FROM
	play_excercise_base AS b
LEFT JOIN play_excercise_event AS e ON e.bid = b.id
WHERE
 b.id=?
AND e.customize = 0
AND b.release_status=1
AND e.sell_status>=1
AND e.sell_status!=3
AND e.join_number<e.perfect_number", array($id))->toArray();


            if(empty($res)){
                $status_3=3;
            }else{

                foreach ($res as $v){

                    if($v['open_time']<time() and $v['over_time']>time()){
                        //可以售卖的场次数
                        $sell_num+=1;
                    }
                    //虚拟票
                    if($v['vir_number']>0 and $v['over_time']>time()){
                        $status_2=2;
                    }

                    //已售N份
                    if($v['open_time']<time() and $v['join_number']>0){
                        $status_2=2;
                    }elseif($v['over_time']>time() and $v['join_number']>0){
                        $status_2=2;
                    }

                    //活动报名中 有未开始场次 ,有进行中 ,无人报名
                    if($v['open_time']<time() and $v['join_number']==0){
                        $status_1=1;

                    }elseif($v['over_time']>time() and $v['join_number']==0){
                        $status_1=1;

                    }
                }
            }



        }elseif ($type==5){

            $db=$this->_getAdapter();
            //取商品所有套系 循环判断
            $order_data = $db->query("SELECT play_game_info.*,play_organizer_game.coupon_vir FROM play_game_info LEFT JOIN  play_organizer_game ON  play_organizer_game.id=play_game_info.gid
  WHERE play_game_info.gid=?  and play_game_info.status=1 and play_game_info.total_num > play_game_info.buy",array($id))->toArray();

            if(empty($order_data)){
                $status_3=3;
            }else{
                //1.有票 2.已售n份 3.停止报名
                foreach ($order_data as $v){


                    if($v['up_time']<time() and $v['down_time']>time()){
                        //可以售卖的场次数
                        $sell_num+=1;
                    }


                    //虚拟票
                    if($v['coupon_vir']>0 and $v['down_time']>time()){
                        $status_2=2;
                    }

                    //已售N份
                    if($v['up_time']<time() and $v['buy']>0){
                        $status_2=2;

                    }elseif($v['down_time']>time() and $v['buy']>0){
                        $status_2=2;

                    }

                    //报名中
                    if($v['up_time']<time() and $v['buy']==0){
                        $status_1=1;
                    }elseif($v['down_time']>time() and $v['buy']==0){
                        $status_1=1;
                    }



                }


            }
        }

        return array('status'=> $status_2?:$status_1?:$status_3?:3,'sell_num'=>$sell_num);
    }


    public function getActivityData($id){
        $price = $this->_getPlayExcerciseEventTable()->getSession($id);
        $p = 0;
        foreach ($price as $v) {
            if ($v['low_price'] < $p or $p == 0) {
                $p = $v['low_price'];
            }
        }
        return array(
            'low_money' => $p,
            'all_number'=>$price->count()
        );
    }
    //获得图标
    private function getIcon($city, $time)
    {
        //判断是否老版本
        $data_client_info = Common::getClientinfo();

        if($data_client_info['client'] === 'ios' || $data_client_info['client'] === 'android'){
            $ver = sprintf('%-03s', str_replace('.', '', $data_client_info['ver']));
            if ($ver < 334) {
                return $this->jsonResponseError('请到应用商店或官网下载最新版本');
            }
        } else {
            $ver = 0;
        }

        if ($ver >= 400 || $data_client_info['client'] === 'weixin' ||  $data_client_info['client'] === 0) {
            $module_pic_sql = "SELECT
play_index_block.type,
play_index_block.link_img,
play_index_block.url,
play_index_block.dateline,
play_index_block.is_top,
play_index_block.block_title,
play_index_block.link_id
FROM play_index_block
LEFT JOIN play_activity ON (play_activity.id = play_index_block.link_id AND play_index_block.type = 1)
WHERE
play_index_block.block_city = '{$city}' AND play_index_block.link_type = 6 AND play_index_block.status > 0
AND  ((play_index_block.type = 1 AND play_activity.status >= 0 AND ((play_activity.s_time < {$time} && play_activity.e_time > {$time}) || (play_activity.s_time = 0 && play_activity.e_time = 0)))
OR  (play_index_block.type = 6 || play_index_block.type = 9 || play_index_block.type = 10 || play_index_block.type = 11 || play_index_block.type = 12 || play_index_block.type = 13 || play_index_block.type = 14 || play_index_block.type = 17 || play_index_block.type = 18)
)
ORDER BY
play_index_block.status DESC, play_index_block.dateline DESC LIMIT 5
";
        } else {
            $module_pic_sql = "SELECT
play_index_block.type,
play_index_block.link_img,
play_index_block.url,
play_index_block.dateline,
play_index_block.is_top,
play_index_block.block_title,
play_index_block.link_id
FROM play_index_block
LEFT JOIN play_activity ON (play_activity.id = play_index_block.link_id AND play_index_block.type = 1)
WHERE
play_index_block.block_city = '{$city}' AND play_index_block.link_type = 6 AND play_index_block.status > 0
AND  ((play_index_block.type = 1 AND play_activity.status >= 0 AND ((play_activity.s_time < {$time} && play_activity.e_time > {$time}) || (play_activity.s_time = 0 && play_activity.e_time = 0)))
OR  (play_index_block.type = 6 || play_index_block.type = 9 || play_index_block.type = 10 || play_index_block.type = 11 || play_index_block.type = 12 || play_index_block.type = 13 || play_index_block.type = 14)
)
ORDER BY
play_index_block.status DESC, play_index_block.dateline DESC LIMIT 5
";
        }

        $modulePic = M::getAdapter()->query($module_pic_sql, array());
        $data = null;
        if (!$modulePic->count()) {
            return $data;
        }

        foreach ($modulePic as $pic) {
            $data[] = array(
                'id' => $pic['link_id'],
                'title' => $this->width_cut($pic['block_title']),
                'cover' => $this->_getConfig()['url'] . $pic['link_img'],
                'type' => $pic['type'],
                'is_new' => $pic['is_top'],
                'new_time' => $pic['dateline'],
                'url' => $pic['url'],
            );
        }
        return $data;
    }

    function query($sql)
    {
        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $stmt = $db->query($sql);
        $result = $stmt->execute($stmt);

        return $result;
    }
}
