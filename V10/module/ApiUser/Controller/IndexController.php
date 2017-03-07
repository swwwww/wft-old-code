<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ApiUser\Controller;

use Deyi\BaseController;
use Deyi\GetCacheData\GoodCache;
use Deyi\GetCacheData\Labels;
use Deyi\GetCacheData\PlaceCache;
use Deyi\Integral\Integral;
use library\Fun\M;
use library\Service\Admin\Setting\Share;
use library\Service\User\Account;
use library\Service\User\Member;
use library\Service\User\User;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Deyi\SendMessage;
use Zend\Http\Response;
use Deyi\JsonResponse;
use library\Service\System\Cache\RedCache;

class IndexController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    public function indexAction()
    {
        exit('this is api');

    }


    //签到
    public function signinAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = (int)$this->getParams('uid');
        $city = $this->getCity();
        if (!$uid) {
            return $this->jsonResponseError('参数错误');
        }


        $s_time = strtotime(date('Y-m-d'));  //有前导零
        $log = $this->_getPlayIntegralTable()->get("create_time>={$s_time} and uid={$uid} and `type`=8");
        if ($log) {
            return $this->jsonResponse(array('status' => 0, 'message' => '您已经签到过了'));
        }

        //更新连续签到
        $yesterday = $s_time - 86400;//昨天

        $yesterday_log = $this->_getPlayIntegralTable()->get("create_time>={$yesterday} and create_time<{$s_time} and `type`=8 and uid={$uid}");

        if ($yesterday_log) {
            $this->_getPlayUserTable()->update(array('sign_in_days' => new Expression('sign_in_days+1')), array('uid' => $uid));
        } else {
            $this->_getPlayUserTable()->update(array('sign_in_days' => 1), array('uid' => $uid));
        }

        $integr = new Integral();

        $s = (int)$integr->sign_integral($uid, $city);

        if ($s) {
            RedCache::del('D:yestd:' . $uid);
            return $this->jsonResponse(array('status' => 1, 'message' => '签到成功!', 'get_score' => $s));
        } else {
            return $this->jsonResponse(array('status' => 0, 'message' => '签到失败!'));
        }


    }

    public function getVipSessionAction () {
        if (!$this->pass(false)) {
            return $this->failRequest();
        }

        $service_member = new Member();
        $data_member_money_service_list = $service_member->getVipSession();

        if (empty($data_member_money_service_list)) {
            return $this->jsonResponse(array('status' => 0, 'message' => '获取VIP套餐失败！'));
        } else {
            $data_return = array();
            foreach ($data_member_money_service_list as $key => $val) {
                $data_money_service_present = json_decode($val['money_service_present'], true);

                $data_money_service_present_count = array();
                if ($data_money_service_present) {
                    foreach ($data_money_service_present as $k => $v) {
                        $data_money_service_present_count[$v['v_name']] = $v['value'];
                    }
                }

                $data_return[] = array(
                    'id'          => $val['money_service_id'],
                    'price'       => $val['money_service_now_price'],
                    'free_number' => $data_money_service_present_count['free_coupon'],
                    'free_money'  => $data_money_service_present_count['free_coupon'] * 80,
                );
            }
            return $this->jsonResponse($data_return);
        }
    }

    public function getFreeCouponAction () {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid  = (int)$this->getParams('uid');
        $city = $this->getCity();
        if (!$uid) {
            return $this->jsonResponseError('参数错误');
        }

        $service_member = new Member();
        $service_account= new Account();
        $service_share  = new Share();

        // 获取用户的VIP会员信息
        $data_member = $service_member->getMemberData($uid);

        // 获取分享数据
        $data_share_param = array(
            'share_city' => $city
        );
        $data_share  = $service_share->getShareData($data_share_param);

        if ($data_share['share_title']) {
            $data_share['share_title']   = json_decode($data_share['share_title'], true);
            $data_share['share_content'] = json_decode($data_share['share_content'], true);

            $data_content_count = count($data_share['share_title']);
        }

        if ($data_content_count > 0) {
            $n = time() % $data_content_count;
        } else {
            $n = 0;
        }

        // 获取价格最贵的VIP套餐
        $data_max_money_service = $service_member->getMaxMoneyService();

        $data_share_recharge_number = $service_account->getShareRechargeCountByUid($uid);

        if ($data_max_money_service) {
            $data_max_money_service_present = json_decode($data_max_money_service['money_service_present'], true);
            $data_max_money_service_present_temp = array();
            if ($data_max_money_service_present) {
                foreach ($data_max_money_service_present as $key => $val) {
                    $data_max_money_service_present_temp[$val['v_name']] = $val['value'];
                }
            }
        }

        $data_return = array(
            'activated_number'     => (int)$data_member['member_free_coupon_count_now'],
            'unactivated_number'   => !empty($data_member) ? (int)$data_member['member_free_activation_coupon_count'] : 3,
            'recharge_send_number' => (int)$data_max_money_service_present_temp['free_coupon'],
            'share_recharge_number'=> (int)$data_share_recharge_number,
            'is_vip'               => $data_member['member_level'] > 0 ? 1 : 0,
            'instruction'          => "· 会员免费亲子游资格有效期自发放之日起，有效期为1年\n\n· 每场活动只能使用免费资格兑换一张票，1张以上可付费购买\n\n· 每天只能使用免费资格参加1场活动，1场以上可付费购买\n\n· 分享链接给好友购买，还可以获得3次亲子游次数",
            'share_title'          => $data_share['share_title'][$n],
            'share_content'        => $data_share['share_content'][$n],
            'share_img'            => $this->getImgUrl($data_share['share_img']),
            'share_url'            => $this->getShareUrl($data_share['share_url'], $uid),
        );

        return $this->jsonResponse($data_return);
    }

    private function getShareUrl($url, $uid)
    {
        $data_array_share_url = explode('?', $url);

        if ($data_array_share_url[1]) {
            $data_array_share_url[1] .= '&';
        } else {
            $data_array_share_url[1] = '';
        }

        $data_array_share_url[1] .= 'share_user_id=' . $uid;


        return $data_array_share_url[0] . '?' . $data_array_share_url[1];
    }

    public function memberIndexAction()
    {
        if (!$this->pass(false)) {
            return $this->failRequest();
        }

        $city = $this->getCity();
        $page = $this->getParams('page', 1);
        $pageNum = $this->getParams('page_num', 5);
        $uid = (int)$this->getParams('uid', 0);
        $time = time();

        $data = array();

        $page = ($page > 1) ? $page : 1;

        //去掉年龄筛选
        $baby_max = 100;
        $baby_min = 0;

        //首页焦点图
        if ($page == 1) {
            $focus_flag = $this->getMemberFocus($city, $time);
            if ($focus_flag) {
                $data['maps'] = $focus_flag;
            }
        }

        $service_member = new Member();
        $data_member = $service_member->getMemberData($uid);
        //精选

        $start = ($page - 1) * $pageNum;

        $place_where = '';
        $good_where = '';
        if ($baby_max && $baby_min) {
            $place_where = " AND ((play_shop.age_min <= {$baby_max} and play_shop.age_max >= {$baby_min}))";
            $good_where = "  AND ((play_organizer_game.age_min <= {$baby_max} and play_organizer_game.age_max >= {$baby_min}))";
        }

        $choice_list = $this->getMemberSelected($city, $time, $place_where, $good_where, $start, $pageNum);

        if ($choice_list) {
            $data['choice_list'] = $choice_list;
        } else {
            $data['choice_list'] = array();
        }

        $data['is_vip'] = $data_member['member_level'] > 0 ? 1 : 0;
        $data['free_coupon_number'] = (int)$data_member['member_free_coupon_count_now'];

        return $this->jsonResponse($data);
    }

    // 获取会员专区亲子游精选
    private function getMemberSelected($city, $time, $place_where, $good_where, $start, $pageNum)
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
play_index_block.block_city = '{$city}' AND play_index_block.link_type = 9 AND play_index_block.status > 0 AND (play_index_block.end_time = 0 OR play_index_block.end_time > {$time})
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
        $choiceData = M::getAdapter()->query($choice_sql, array());

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
                    "vip_free" => $choice['free_coupon_event_count']
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

    //首页焦点图 link_type = 2
    private function getMemberFocus($city, $time)
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
play_index_block.block_city = '{$city}' AND play_index_block.link_type = 8 AND play_index_block.status > 0
AND  ((play_index_block.type = 1 AND play_activity.status >= 0 AND ((play_activity.s_time < {$time} && play_activity.e_time > {$time}) || (play_activity.s_time = 0 && play_activity.e_time = 0)))
OR  (play_index_block.type = 4 AND play_shop.shop_status >= 0)
OR  (play_index_block.type = 5 AND play_organizer_game.status > 0 && play_organizer_game.start_time < {$time} && play_organizer_game.end_time > {$time})
OR  (play_index_block.type = 6 || play_index_block.type = 7 || play_index_block.type = 8)
OR (play_index_block.type = 16	AND play_excercise_base.release_status >= 0)
)
ORDER BY
play_index_block.status DESC, play_index_block.dateline DESC
LIMIT 3
";

        $focusMaps = M::getAdapter()->query($focus_sql, array());

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
}
