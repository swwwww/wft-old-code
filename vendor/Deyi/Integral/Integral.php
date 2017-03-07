<?php

namespace Deyi\Integral;

use Application\Model\QualifyTable;
use Application\Module;
use Deyi\BaseController;
use Deyi\Coupon\Coupon;
use Deyi\GetCacheData\CityCache;
use library\Service\System\Cache\RedCache;

/**
 * Class Integral
 * 获得积分方式(积分类型 1游玩地分享 2游玩地评论 3商品分享 4商品评论 5商品购买 6邀请好友 7完善资料,8每日签到，
 * 9连续签到，10每天任务额外,11更换头像积分,12补充宝宝资料,13上传宝宝头像,14微信号绑定,15圈子发言,
 * 16圈子发言获赞，17商品评论获赞，18游玩地评论获赞,19点击分享获积分,21新手任务额外 22 接受邀请注册 23 使用商品,
 * ,100退货扣除积分,101积分兑换资格券,102购买商品消耗积分)
 * @package Deyi\Integral
 */
class Integral
{
    use BaseController;

    function __construct()
    {
    }

    //受每日积分限制的项目
    private $limit_item = '2,3,1,15,17,18';
    private $delete_item = '100,103,104,105,106,107,108,109';

    //参与新手任务的项目
    private $new_task = [11, 12, 13, 14];

    //参与每日任务的项目
    private $day_task = [1, 2, 3, 4, 5, 8, 15, 22];

    //操作类型
    public static $tparr = [
        1 => '游玩地分享',
        2 => '游玩地评论',
        3 => '商品分享',
        4 => '商品评论',
        5 => '商品购买',
        6 => '邀请好友',
        7 => '完善资料',
        8 => '每日签到',
        9 => '连续签到',
        10 => '完成每日任务',
        11 => '更换头像积分',
        12 => '补充宝宝资料',
        13 => '上传宝宝头像',
        14 => '微信号绑定',
        15 => '圈子发言',
        16 => '圈子发言获赞',
        17 => '商品评论获赞',
        18 => '游玩地评论获赞',
        19 => '点击分享获积分',
        21 => '新手任务额外',
        22 => '接受邀请注册',
        23 => '使用订单',
        24 => '微信活动',
        25 => '接受邀请',
        26 => '活动评论',

        //23 => '',
        //扣除积分>100
        100 => '退款扣除积分',
        101 => '积分兑换资格券',
        102 => '购买商品消耗积分',
        103 => '删除圈子发言扣除积分',//用户
        104 => '删除圈子发言扣除积分',//小编
        105 => '扣除每日奖励积分',
        106 => '删除商品评论扣除积分',//用户
        107 => '删除商品评论扣除积分',//小编
        108 => '删除游玩地评论扣除积分',//用户
        109 => '删除游玩地评论扣除积分',//小编
    ];

    //BaseController 使用
    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }

    /**
     * 获取设置
     * @return int
     */
    public function getSetting()
    {
        $setting = $this->getMarketSetting();

        return $setting;
    }

    /**
     * 签到积分
     * @param $uid
     * @return bool false or 5
     */
    public function sign_integral($uid, $city)
    {
        $setting = $this->getSetting();
        //判断当日是否已经签到过
        $start = strtotime(date('Y-m-d'));
        $adapter = $this->_getAdapter();
        $sql = "SELECT * FROM play_integral WHERE `uid`=? and create_time >= ?  AND `type`= ?";
        $today = $adapter->query($sql, array($uid, $start, 8))->current();

        if ($today) {
            return false;
        }

        $s1 = $this->addIntegral($uid, $setting->sign_one, 1, 8, 0, $city);
        if (!$s1) {
            return false;
        }

        $day = (int)$setting->sign_day;
//        $before = $start - ($day - 1) * 24 * 3600;
//        $sql = "SELECT * FROM play_integral WHERE `uid`=? and `type` = 8 AND create_time >= ? and create_time < ? ";
//        $days = $adapter->query($sql, array($uid, $before, $start + 24 * 3600))->count();

        $sql = "SELECT * FROM play_user WHERE `uid`=?  ";
        $days = $adapter->query($sql, array($uid))->current();

        $sign_in_days = 0;
        if($days){
            $sign_in_days = (int)$days->sign_in_days;
        }

        //每日任务奖励积分
        $this->days_task_integral($uid, $city);
        //连续签到

        if ($sign_in_days && $sign_in_days % $day === 0) {
            $s2 = $this->addIntegral($uid, $setting->sign_integral, 1, 9, 0, $city);
            if ($s2) {
                return $setting->sign_one + $setting->sign_integral;
            } else {
                return false;
            }
        } else {
            return $setting->sign_one;
        }
    }

    /**
     * 更换头像积分 type:11
     * @param $uid
     * @param $city
     * @return bool 分数
     */
    public function face_integral($uid, $city)
    {
        $setting = $this->getSetting();
        $adapter = $this->_getAdapter();
        $face = $adapter->query("SELECT * FROM play_integral WHERE `uid`=? and `type` = 11 ", array($uid))->current();
        if ($face) {
            return false;
        }

        $s1 = $this->addIntegral($uid, $setting->face_integral, 1, 11, 0, $city);
        if (!$s1) {
            return false;
        } else {
            //新手积分
            $this->newer_task_integral($uid, $city);

            return $setting->face_integral;
        }
    }

    /**
     * 补充宝宝资料积分 type:12
     * @param $uid
     * @param $city
     * @return bool
     */
    public function baby_info($uid, $city)
    {
        $setting = $this->getSetting();

        $adapter = $this->_getAdapter();
        $obj = $adapter->query("SELECT * FROM play_integral WHERE `uid`=? and `type` = 12 ", array($uid))->current();
        if ($obj) {
            return false;
        }

        $s1 = $this->addIntegral($uid, $setting->baby_info, 1, 12, 0, $city);
        if (!$s1) {
            return false;
        } else {
            //新手积分
            $this->newer_task_integral($uid, $city);

            return $setting->baby_info;
        }
    }

    /**
     * 上传宝宝头像 type 13
     * @param $uid
     * @param $city
     * @return bool
     */
    public function baby_face($uid, $city)
    {
        $setting = $this->getSetting();

        $adapter = $this->_getAdapter();
        $obj = $adapter->query("SELECT * FROM play_integral WHERE `uid`=? and `type` = 13 ", array($uid))->current();
        if ($obj) {
            return false;
        }

        $s1 = $this->addIntegral($uid, $setting->baby_face, 1, 13, 0, $city);
        if (!$s1) {
            return false;
        } else {
            //新手积分
            $this->newer_task_integral($uid, $city);

            return $setting->baby_face;
        }
    }

    /**
     * 微信号绑定 type 14
     * @param $uid
     * @param $city
     * @return bool
     */
    public function weixin_bind($uid, $city)
    {
        $setting = $this->getSetting();

        $adapter = $this->_getAdapter();
        $obj = $adapter->query("SELECT * FROM play_integral WHERE `uid`=? and `type` = 14 ", array($uid))->current();
        if ($obj) {
            return false;
        }

        $s1 = $this->addIntegral($uid, $setting->weixin_bind, 1, 14, 0, $city);
        if (!$s1) {
            return false;
        } else {
            //新手积分
            $this->newer_task_integral($uid, $city);

            return $setting->weixin_bind;
        }
    }

    /**
     * 购买商品获得积分
     * @param $uid
     * @param $price 　商品价格 ?小数如何处理
     * @param $order_sn 订单id
     * @param $game_info_id 套系id
     * @param $city
     * @return bool|int
     */
    public function buyGood($uid, $gid, $price, $city,$coupon_name='')
    {
        if (!$price || !$uid || !$gid) {
            return false;
        }


        //处理商品的使用积分
        $adapter = $this->_getAdapter();
        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();

        $desc = '购买'.($coupon_name?:'商品').'获得积分';

        $score = 0;

        $s3 = $adapter->query("INSERT INTO play_integral (id,uid,`type`,total_score,base_score,award_score,object_id,create_time,city,`desc` )
 VALUES (NULL,?,?,?,?,?,?,?,?,?)", array(
            $uid,
            5,
            $score,
            (int)$score,
            1,
            $gid,
            time(),
            $city,
            $desc
        ))->count();

        if (!$s3) {
            $conn->rollback();

            return false;
        }

        $conn->commit();
        //每日任务奖励积分
        $this->days_task_integral($uid, $city);

        return (int)$score;
    }

    /**
     * 使用商品获得积分
     * @param $uid
     * @param $gid
     * @param $price
     * @param $city
     * @param string $coupon_name
     * @return bool|int
     */
    public function useGood($uid, $gid, $price, $city,$coupon_name='',$sn)
    {
        if (!$price || !$uid || !$gid) {
            return false;
        }

        //处理商品的使用积分
        $adapter = $this->_getAdapter();

        $goods = $adapter->query("SELECT * FROM play_organizer_game WHERE `id`=?",
            array($gid))->current();

        $score = 0;
        if($goods && $goods->buy_integral){
            $score = $price * (float)$goods->buy_integral;
        }

        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();

        //购买获得积分
        $setting = $this->getSetting();

        if(!$score){
         //$score = $price * $setting->good_integral_price;
        }

        if ($score < 0.5) {
            $score = 0;
        } else {
            $score = round($score);
        }
        $score = (int)$score;

        //积分记录表添加记录
        $total_score = (int)$score;

        $desc = '使用'.($coupon_name?:'商品').'获得积分';

        $s3 = $adapter->query("INSERT INTO play_integral (id,uid,`type`,total_score,base_score,award_score,object_id,create_time,city,`desc` )
 VALUES (NULL,?,?,?,?,?,?,?,?,?)", array(
            $uid,
            23,
            $score,
            (int)$score,
            1,
            $sn,
            time(),
            $city,
            $desc
        ))->count();

        if (!$s3) {
            $conn->rollback();

            return false;
        }

        $s4 = $adapter->query("UPDATE play_integral_user SET total=total+{$total_score} WHERE uid=?",
            array($uid))->count();
        if (!$s4) {
            $conn->rollback();

            return false;
        }

        $conn->commit();
        //每日任务奖励积分
        //$this->days_task_integral($uid, $city);

        return (int)$score;
    }

    /**
     * 退货扣积分
     * @deprecate 购买不给积分了
     * @gid 订单id
     * @param $uid
     * @param $price
     * @param $city
     * @return bool|int
     */
    public function returnGood($uid,$gid, $price, $city)
    {

        if (!$uid || !$gid) {
            return false;
        }

        $score = 0;

        $s1 = $this->subIntegral($uid, 0, 1, 100, $gid, $city);
        if (!$s1) {
            return false;
        } else {
            //扣除每日任务奖励积分
            $this->day_integral_delete($uid,$city);
            return (int)$score;
        }
    }


    /**
     * 圈子发言积分 type 15
     * @param $uid
     * @param $city
     * @return bool
     */
    public function circle_speak($uid, $mid, $city)
    {
        //return false;
        $setting = $this->getSetting();
        //当日发言获得积分
        $start = strtotime(date('Y-m-d'));
        $adapter = $this->_getAdapter();
        $obj = $adapter->query("SELECT SUM(total_score) as total_score FROM play_integral WHERE `uid`=? and `type` in ({$this->limit_item}) and create_time >= ?",
            array($uid, $start))->current();

        $del = $adapter->query("SELECT SUM(total_score) as total_score FROM play_integral WHERE `uid`=? and `type` in ({$this->delete_item}) and create_time >= ? ",
            array($uid, $start))->current();

        $delscore = $del?$del->total_score:0;

        if ($obj && $setting->oneday_integral <= ($obj->total_score-$delscore) ) { //达到上限
            return false;
        }

//        $s1 = $this->addIntegral($uid, $setting->circle_speak, 1, 15, $mid, $city);
        $s1 = $this->addIntegral($uid, 0, 1, 15, $mid, $city,0,'',$mid);
        if (!$s1) {
            return false;
        } else {
            //每日任务奖励积分
            $this->days_task_integral($uid, $city);

            return $setting->circle_speak;
        }
    }

    /**
     * 如果扣了还原
     * @param $uid
     * @param $mid
     * @param $city
     * @param int $admin
     */
    public function circle_speak_reset($uid, $mid, $city){
        $adapter = $this->_getAdapter();
        $obj = $adapter->query("SELECT SUM(total_score) as total_score,`type` FROM play_integral WHERE `uid`=? and `type` > 99 and `object_id`=? ",
            array($uid,$mid))->current();
        if(!$obj){
            return false;
        }

        $new = $adapter->query("SELECT `type` FROM play_integral WHERE `uid`=? and `object_id`=? order by id desc ",
            array($uid,$mid))->current();

        if($new && $new->type < 100){
            return false;
        }

        if($obj->type==104){
            $type = 15;
            $desc = '恢复删除发言积分';
        }

        if($obj->type==107){
            $type = 4;
            $desc = $adapter->query("SELECT `type`,`desc` FROM play_integral WHERE `uid`=? and `object_id`=? and `type` = ? order by id desc ",
                array($uid,$mid,$type))->current();
            $desc = $desc?$desc->desc:'恢复商品评论';
        }

        if($obj->type==109){
            $type = 2;
            $desc = $adapter->query("SELECT `type`,`desc` FROM play_integral WHERE `uid`=? and `object_id`=? and `type` = ? order by id desc ",
                array($uid,$mid,$type))->current();
            $desc = $desc?$desc->desc:'恢复游玩地评论';
        }

        $s1 = $this->addIntegral($uid, $obj->total_score, 1, $type, $mid, $city,0,$desc,$mid);
        if (!$s1) {
            return false;
        } else {
            //每日任务奖励积分
            $this->days_task_integral($uid, $city);

            return $obj->total_score;
        }

    }

    /**
     * 删除发言扣积分
     * @param $uid
     * @param $mid
     * @param $city
     * @return bool
     */
    public function circle_speak_delete($uid, $mid, $city,$admin=0){
        $start = strtotime(date('Y-m-d'));
        $adapter = $this->_getAdapter();

        $obj = $adapter->query("SELECT SUM(total_score) as total_score,`type` FROM play_integral WHERE `uid`=? and `type` < 100 and `object_id`=? and create_time > ? ",
            array($uid,$mid,$start))->current();

        $new = $adapter->query("SELECT `type` FROM play_integral WHERE `uid`=? and `object_id`=? order by id desc ",
            array($uid,$mid))->current();

        if($new && $new->type > 99){
            return false;
        }

        if(!$obj){
            return false;
        }

        if($obj->type==4){//商品点评
            $type = $admin?107:106;
            $in1 = 107;
            $in2 = 106;
        }

        if($obj->type==2){//游玩地点评
            $type = $admin?109:108;
            $in1 = 108;
            $in2 = 109;
        }

        if($obj->type==15){//圈子发言
            $type = $admin?103:104;
            $in1 = 103;
            $in2 = 104;
        }

        if(!$type){
            return false;
        }

        $min = $adapter->query("SELECT SUM(total_score) as total_score,`type` FROM play_integral WHERE `uid`=? and `type` in (?,?) and `object_id`=? and create_time > ? ",
            array($uid,$in1,$in2,$mid,$start))->current();

        $score = $obj->total_score - $min->total_score;

        $score = $score>0?$score:0;

        $s1 = $this->subIntegral($uid, $score, 1, $type, $mid, $city,0,'',$mid);

        if (!$s1) {
            return false;
        } else {
            $this->day_integral_delete($uid,$city);
            return $obj->total_score;
        }
    }

    /**
     * 扣除每日积分任务
     * @param $uid
     * @param $city
     * @return bool
     */
    public function day_integral_delete($uid,$city){
        $adapter = $this->_getAdapter();
        $start = strtotime(date('Y-m-d'));

        $all =  $adapter->query("SELECT * FROM play_integral
      WHERE `uid`= ? and create_time >= ? ", array($uid, $start))->toArray();
        $give = $minus = 0;
        if($all){
            foreach($all as $d){
                if($d['type']==10){//给过任务额外
                    $give++;
                }
                if($d['type']==105){//扣除任务额外
                    $minus++;
                }
            }
        }

        if($give == 0 || $give <= $minus){
            return false;
        }

        $task = $adapter->query("SELECT * FROM play_task_integral WHERE `city`= ? ", array($city))->current();
        if(!$task){
            return false;
        }
        $day = unserialize($task->day_task);
        $arr = [];
        if ($day) {
            foreach ($day as $n) {
                if ((int)$n !== 22) {
                    $arr[] = $n;
                }
            }
        }

        //添加积分被扣除的情况 //4,2,5,15,100,103,104,105
        $give_goods = $minus_goods = $give_places = $minus_places = $give_buy = $minus_return = $give_circle = $minus_circle =0;
        foreach($all as $d){
            if($d['type']==4){//商品评论
                $give_goods++;
            }
            if($d['type']==106 || $d['type']==107){//删去商品评论
                $minus_goods++;
            }
            if($d['type']==2){//游玩地评论 8 9
                $give_places++;
            }
            if($d['type']==108 || $d['type']==109){//游玩地评论 8 9
                $minus_places++;
            }
            if($d['type']==5){//购买商品积分
                $give_buy++;
            }
            if($d['type']==100){//退款获得积分
                $minus_return++;
            }
            if ($d['type'] == 15) {//购买商品积分
                $give_circle++;
            }
            if($d['type']==103 || $d['type']==104){//游玩地评论 8 9
                $minus_circle++;
            }
        }
        $delete = 0;
        if($give_goods <= $minus_goods && in_array(4,$arr)){
            $delete = 1;
        }

        if($give_places <= $minus_places && in_array(2,$arr)){
            $delete = 1;
        }

        if($give_buy <= $minus_return && in_array(5,$arr)){
            $delete = 1;
        }

        if($give_circle <= $minus_circle && in_array(15,$arr)){
            $delete = 1;
        }


        if($delete){
            $s1 = $this->subIntegral($uid, $task->day_plus, 1, 105, 0, $city);

            if (!$s1) {
                return false;
            } else {
                return $task->day_plus;
            }
        }
        return false;
    }

    /**
     * 圈子点赞积分 type 16
     * @param $uid
     * @param $oid
     * @param $city
     * @return bool
     */
    public function circle_prize_integral($uid, $oid, $city)
    {
        return false;
        //判断是否到了可以奖励的整被数
        $setting = $this->getSetting();
        $count = $this->get_prizes($oid);
        if (($count > 0) && ($count % $setting->circle_prize_num) !== 0) {
            return false;
        }
        $adapter = $this->_getAdapter();

        $integral = $adapter->query("SELECT * FROM play_integral WHERE `uid`=? and `type` = ? and object_id = ?",
            array($uid, 16, $oid))->toArray();
        $icount = count($integral);
//        if ($icount > 0 && (floor($count / $setting->circle_prize_num) <= $icount)) {
//            return false;
//        }

        if ($setting->circle_prize_num > $count || $icount > 0) {
            return false;
        }

        $start = strtotime(date('Y-m-d'));
        $obj = $adapter->query("SELECT SUM(total_score) as total_score FROM play_integral WHERE `uid`=? and `type` in ({$this->limit_item}) and create_time >= ?",
            array($uid, $start))->current();

        $del = $adapter->query("SELECT SUM(total_score) as total_score FROM play_integral WHERE `uid`=? and `type` in ({$this->delete_item}) and create_time >= ? ",
            array($uid, $start))->current();

        $delscore = $del?$del->total_score:0;

        if ($obj && $setting->oneday_integral <= ($obj->total_score-$delscore) ) { //达到上限
            return false;
        }

        $s1 = $this->addIntegral($uid, $setting->circle_prize_integral, 1,
            16, $oid, $city,0,'',$oid);
        if (!$s1) {
            return false;
        } else {
            return $setting->circle_prize_integral;
        }
    }

    /**
     * 商品评论积分 type 4
     * @param $uid
     * @param $city
     * @param $oid 评论的mid
     * @return bool
     */
    public function good_comment_integral($uid, $oid, $city, $only = 0,$desc='')
    {
        if (!$only) {
            return false;
        }
        $setting = $this->getSetting();
        $adapter = $this->_getAdapter();

        //不同商品的分享积分倍数
        $obj = $adapter->query("SELECT * FROM play_welfare_integral WHERE `object_id`=? and `object_type` = 2 and status = 1 and welfare_type = 3 and total_num > get_num",
            array($only))->current();

        //判断是否超过单个商品领取的限制
        $count = $adapter->query("SELECT * FROM play_integral group by object_id HAVING `onlyid`=? and `uid` = ? and `type` = 4",
            array($only, $uid))->count();
        $flag = 0;
        if ($obj && $obj->limit_num > 0 && $count < $obj->limit_num) {
            $double = $obj->double;
            $double = $double ?: 1;
            $flag = 1;
        } else {
            $double = 0;
            if ($count > 0) {
                return false;
            }
        }

        $desc  = '评论'.$desc.'获得积分';

        $score = $setting->good_comment_integral;

        $s1 = $this->addIntegral($uid, $score, $double, 4, $oid,
            $city, $only,$desc,$oid);
        if (!$s1) {
            return false;
        } else {
            //每日任务奖励积分
            $this->days_task_integral($uid, $city);
            //减少游玩地接纳积分总数
            if ($flag) {
                $adapter->query("UPDATE play_welfare_integral SET get_num = get_num + 1 WHERE `object_id`=? and `object_type` = 2 and status = 1 and welfare_type = 3 and total_num > get_num",
                    array($oid));
            }

            return $score * $double;
        }
    }

    /**
     * 活动评论奖励
     * @param $uid
     * @param $oid
     * @param $city
     * @param int $only
     * @param string $desc
     * @return bool
     */
    public function event_comment_integral($uid, $oid, $city, $only = 0,$desc='')
    {
        if (!$only) {
            return false;
        }
        $setting = $this->getSetting();
        $adapter = $this->_getAdapter();

        //不同场次的分享积分倍数
        $obj = $adapter->query("SELECT * FROM play_excercise_event WHERE `id`=? ",
            array($only))->current();

        $flag = 0;
        if ($obj && $obj->comment_integral > 0 ) {
            $double = $obj->integral_multiple;
            $double = $double ?: 1;
            $flag = 1;
        } else {
            $double = 0;
        }

        $desc  = '评论'.$desc.'获得积分';

        $score = $setting->event_comment_integral;

        $s1 = $this->addIntegral($uid, $score, $double, 26, $oid,
            $city, $only,$desc,$oid);
        if (!$s1) {
            return false;
        } else {
            //每日任务奖励积分
            //$this->days_task_integral($uid, $city);
            //减少游玩地接纳积分总数
//            if ($flag) {
//                $adapter->query("UPDATE play_welfare_integral SET get_num = get_num + 1 WHERE `object_id`=? and `object_type` = 2 and status = 1 and welfare_type = 3 and total_num > get_num",
//                    array($oid));
//            }

            return $score * $double;
        }
    }

    /**
     * 商品评论点赞积分 type 17
     * @param $uid
     * @param $oid 评论对象id
     * @param $city
     * @param $only
     * @return bool
     */
    public function good_comment_prize_integral($uid, $oid, $city, $only = 0)
    {
        if (!$only) {
            return false;
        }
        //判断是否到了可以奖励的整被数
        $setting = $this->getSetting();
        $count = $this->get_prizes($oid);

        if ($count < 1) {
            return false;
        }
        $adapter = $this->_getAdapter();

        $integral = $adapter->query("SELECT * FROM play_integral WHERE `uid`=? and `type` = ? and onlyid = ?",
            array($uid, 17, $only))->toArray();

        if (false !== $integral && count($integral)) {
            return false;
        }

        if (($count > 0) && ($count < $setting->good_comment_prize_num)) {
            return false;
        }

//        if ($icount > 0 && (floor($count / $setting->good_comment_prize_num) <= $icount)) {
//            return false;
//        }

        $s1 = $this->addIntegral($uid, $setting->good_comment_prize_integral, 1,
            17, $oid, $city, $only,'',$oid);
        if (!$s1) {
            return false;
        } else {
            return $setting->good_comment_prize_integral;
        }
    }

    /**
     * 游玩地评论积分 type 2
     * @param $uid
     * @param $city
     * @return bool
     */
    public function place_comment_integral($uid, $mid, $city, $oid,$desc='')
    {
        $setting = $this->getSetting();
        //当日发言获得积分
        $start = strtotime(date('Y-m-d'));

        $adapter = $this->_getAdapter();
        $obj = $adapter->query("SELECT SUM(total_score) as total_score FROM play_integral WHERE `uid`=? and `type` in ({$this->limit_item}) and create_time >= ? ",
            array($uid, $start))->current();

        $del = $adapter->query("SELECT SUM(total_score) as total_score FROM play_integral WHERE `uid`=? and `type` in ({$this->delete_item}) and create_time >= ? ",
            array($uid, $start))->current();

        $delscore = $del?$del->total_score:0;

        if ($obj && $setting->oneday_integral <= ($obj->total_score-$delscore) ) { //达到上限
            return false;
        }

        //评论积分上限
        $obj = $adapter->query("SELECT SUM(total_score) as place_total_score FROM play_integral WHERE `uid`=? and `type` = 2 and create_time >= ? ",
            array($uid, $start))->current();

        $del = $adapter->query("SELECT SUM(total_score) as total_score FROM play_integral WHERE `uid`=? and `type` in (108,109) and create_time >= ? ",
            array($uid, $start))->current();
        $delscore = $del?$del->total_score:0;

        if ($obj && $setting->oneday_place_comment_integral <= ($obj->place_total_score - $delscore)) { //达到上限
            return false;
        }

        //不同游玩地的分享积分倍数
        $obj = $adapter->query("SELECT * FROM play_welfare_integral WHERE `object_id`=? and `object_type` = 1 and status = 1 and welfare_type = 3 and total_num > get_num",
            array($oid))->current();

        //判断是否超过单个游玩地领取的限制
        $count = $adapter->query("SELECT * FROM play_integral group by object_id HAVING `onlyid`=? and `uid` = ? and type = 2",
            array($oid, $uid))->count();

        if ($obj && $obj->limit_num > 0 && $count < $obj->limit_num) {
            $double = $obj->double;
            $double = $double ?: 1;
            $flag = 1;//如果超过奖励积分分数
        } else {
            $double = 0;
            if ($count > 0) {
                return false;
            }
        }

        $score = $setting->place_comment_integral;

        $desc = $desc?('评论'.$desc.'奖励'):'评论奖励';

        $s1 = $this->addIntegral($uid, $score, $double, 2, $mid,
            $city, $oid,$desc,$mid);
        if (!$s1) {
            return false;
        } else {
            //减少游玩地接纳积分总数
            if ($flag) {
                $adapter->query("UPDATE play_welfare_integral SET get_num = get_num + 1 WHERE `object_id`=? and `object_type` = 1 and status = 1 and welfare_type = 3 and total_num > get_num",
                    array($oid));
            }

            //每日任务奖励积分
            $this->days_task_integral($uid, $city);

            return $score;
        }
    }

    /**
     * 游玩地评论点赞积分 type 18
     * @param $uid
     * @param $oid
     * @param $city
     * @return bool
     */
    public function place_comment_prize_integral($uid, $oid, $city, $only)
    {

        //判断是否到了可以奖励的整被数
        $setting = $this->getSetting();
        $count = $this->get_prizes($oid);

        if (($count < $setting->place_comment_prize_num) || ($count < 1)) {
            return false;
        }

        $adapter = $this->_getAdapter();
        $start = strtotime(date('Y-m-d'));
        $obj = $adapter->query("SELECT SUM(total_score) as total_score FROM play_integral WHERE `uid`=? and `type` in ({$this->limit_item}) and create_time >= ?",
            array($uid, $start))->current();

        $del = $adapter->query("SELECT SUM(total_score) as total_score FROM play_integral WHERE `uid`=? and `type` in ({$this->delete_item}) and create_time >= ? ",
            array($uid, $start))->current();

        $delscore = $del?$del->total_score:0;

        if ($obj && $setting->oneday_integral <= ($obj->total_score-$delscore) ) { //达到上限
            return false;
        }

        $integral = $adapter->query("SELECT * FROM play_integral WHERE `uid`=? and `type` = ? and onlyid = ?",
            array($uid, 18, $only))->toArray();

        if (false !== $integral && count($integral)) {
            return false;
        }

        $s1 = $this->addIntegral($uid, $setting->place_comment_prize_integral,
            1, 18, $oid, $city, $only,$oid);
        if (!$s1) {
            return false;
        } else {
            return $setting->place_comment_prize_integral;
        }
    }

    /**
     * 商品详情获得积分倍数
     * @param $uid
     * @param $oid
     * @return int
     */
    public function get_share_good_integral($uid, $oid){
        if (!$uid || !$oid) {
            return 0;
        }
        $setting = $this->getSetting();
        //当日发言获得积分
        $start = strtotime(date('Y-m-d'));

        $adapter = $this->_getAdapter();
        $obj = $adapter->query("SELECT SUM(total_score) as total_score FROM play_integral WHERE `uid`=? and `type` in ({$this->limit_item}) and create_time >= ?",
            array($uid, $start))->current();

        $del = $adapter->query("SELECT SUM(total_score) as total_score FROM play_integral WHERE `uid`=? and `type` in ({$this->delete_item}) and create_time >= ? ",
            array($uid, $start))->current();

        $delscore = $del?$del->total_score:0;

        if ($obj && $setting->oneday_integral <= ($obj->total_score-$delscore) ) { //达到上限
            return false;
        }

        //判断是否超过单个商品领取的限制
        $count = $adapter->query("SELECT * FROM play_integral WHERE `object_id`=? and `uid` = ? and type = 3",
            array($oid, $uid))->count();

        //不同商品的分享积分倍数
        $obj = $adapter->query("SELECT * FROM play_welfare_integral WHERE `object_id`=? and `object_type` = 2 and status = 1 and welfare_type = 4 and total_num > get_num",
            array($oid))->current();

        if ($obj && $obj->limit_num > 0 && $count < $obj->limit_num) {
            $double = $obj->double;
            $double = $double ?: 0;
        } else {
            $double = 0;
        }

        return $double;
    }

    /**
     * 分享商品积分 type 3
     * @param $uid
     * @param $oid 商品id
     * @param $double 商品的额外积分倍数
     * @param $city
     * @return bool
     */
    public function share_good_integral($uid, $oid, $city)
    {
        if (!$uid || !$oid) {
            return false;
        }
        $setting = $this->getSetting();
        //当日发言获得积分
        $start = strtotime(date('Y-m-d'));

        $adapter = $this->_getAdapter();
        $obj = $adapter->query("SELECT SUM(total_score) as total_score FROM play_integral WHERE `uid`=? and `type` in ({$this->limit_item}) and create_time >= ?",
            array($uid, $start))->current();

        $del = $adapter->query("SELECT SUM(total_score) as total_score FROM play_integral WHERE `uid`=? and `type` in ({$this->delete_item}) and create_time >= ? ",
            array($uid, $start))->current();

        $delscore = $del?$del->total_score:0;

        if ($obj && $setting->oneday_integral <= ($obj->total_score-$delscore) ) { //达到上限
            return false;
        }

        //分享积分上限
        $obj = $adapter->query("SELECT SUM(total_score) as share_total_score FROM play_integral WHERE `uid`=? and `type` = 3 and create_time >= ? ",
            array($uid, $start))->current();

        if ($obj && $setting->oneday_share_good_integral <= $obj->share_total_score) { //达到上限
            return false;
        }

        //判断是否超过单个商品领取的限制
        $count = $adapter->query("SELECT * FROM play_integral WHERE `object_id`=? and `uid` = ? and type = 3",
            array($oid, $uid))->count();

        //不同商品的分享积分倍数
        $obj = $adapter->query("SELECT * FROM play_welfare_integral WHERE `object_id`=? and `object_type` = 2 and status = 1 and welfare_type = 4 and total_num > get_num",
            array($oid))->current();

        $flag = 0;
        if ($obj && $obj->limit_num > 0 && $count < $obj->limit_num) {
            $double = $obj->double;
            $double = $double ?: 1;
            $flag = 1;
        } else {
            $double = 0;
            if ($count > 0) {
                return false;
            }
        }

        //单纯分享一次的积分
        $score = $setting->share_good_integral;

        $goods = $adapter->query("SELECT * FROM play_organizer_game WHERE `id`=? ",
            array($oid))->current();

        $s1 = $this->addIntegral($uid, $score, $double, 3, $oid, $city,0,'分享商品'.($goods?$goods->title:''));

        if (!$s1) {
            return false;
        } else {
            if ($flag) {
                $adapter->query("UPDATE play_welfare_integral SET get_num = get_num + 1 WHERE `object_id`=? and `object_type` = 2 and status = 1 and welfare_type = 4 and total_num > get_num",
                    array($oid));
            }
            //每日任务奖励积分
            $this->days_task_integral($uid, $city);

            return $score * $double;
        }
    }

    /**
     * 分享游玩地积分 type 1
     * @param $uid
     * @param $oid 游玩地id
     * @param $double 商品的额外积分倍数
     * @param $city
     * @return bool
     */
    public function share_place_integral($uid, $oid, $city)
    {
        if (!$uid || !$oid) {
            return false;
        }
        $setting = $this->getSetting();
        //当日发言获得积分
        $start = strtotime(date('Y-m-d'));

        $adapter = $this->_getAdapter();
        $obj = $adapter->query("SELECT SUM(total_score) as total_score FROM play_integral WHERE `uid`=? and `type` in ({$this->limit_item}) and create_time >= ?",
            array($uid, $start))->current();
        $del = $adapter->query("SELECT SUM(total_score) as total_score FROM play_integral WHERE `uid`=? and `type` in ({$this->delete_item}) and create_time >= ? ",
            array($uid, $start))->current();

        $delscore = $del?$del->total_score:0;

        if ($obj && $setting->oneday_integral <= ($obj->total_score-$delscore) ) { //达到上限
            return false;
        }

        //分享积分上限
        $obj = $adapter->query("SELECT SUM(total_score) as share_total_score FROM play_integral WHERE `uid`=? and `type` = 1 and create_time >= ? ",
            array($uid, $start))->current();

        if ($obj && $setting->oneday_share_place_integral <= $obj->share_total_score) { //达到上限
            return false;
        }

        //判断是否超过单个游玩地领取的限制
        $count = $adapter->query("SELECT * FROM play_integral WHERE `object_id`=? and `uid` = ? and type = 1",
            array($oid, $uid))->count();

        //不同商品的分享积分倍数
        $obj = $adapter->query("SELECT * FROM play_welfare_integral WHERE `object_id`=? and `object_type` = 1 and status = 1 and welfare_type = 4 and total_num > get_num",
            array($oid))->current();
        $flag = 0;

        if ($obj && $obj->limit_num > 0 && $count < $obj->limit_num) {
            $double = $obj->double;
            $double = $double ?: 1;
            $flag = 1;
        } else {
            if ($count > 0) {
                return false;
            }
            $double = 0;
        }


        //单纯分享一次的积分
        $score = $setting->share_place_integral;

        $s1 = $this->addIntegral($uid, $score, $double, 1, $oid,
            $city);
        if (!$s1) {
            return false;
        } else {
            //每日任务奖励积分
            $this->days_task_integral($uid, $city);
            if ($flag) {
                $adapter->query("UPDATE play_welfare_integral SET get_num = get_num + 1 WHERE `object_id`=? and `object_type` = 1 and status = 1 and welfare_type = 4 and total_num > get_num",
                    array($oid));
            }

            return $score * $double;
        }
    }

    /**
     * 点击分享获得积分 type 19
     * @param $uid
     * @param $oid
     * @param $type １游玩地　２商品
     */
    public function click_share($uid, $oid, $type, $city)
    {
        $setting = $this->getSetting();

        $adapter = $this->_getAdapter();
        $obj = $adapter->query("SELECT count(*) as c FROM play_share WHERE `uid`=? and share_id = ? and `type` = 19 ",
            array($uid, $oid))->current();
        if ((int)$obj->c === 10) {
            return false;
        }

        $s1 = $this->addIntegral($uid, $setting->share_good_integral, 1, 19, 0, $city);
        if (!$s1) {
            return false;
        } else {
            return $setting->share_good_integral;
        }
    }

    /**
     * 最大限额(每月站点积分上限)
     * @param $uid
     * @param $city
     * @return bool
     */
    public function month_max($city)
    {

        //总共
        $adapter = $this->_getAdapter();
        $start = strtotime(date('Y-m'));

        $total_score = $adapter->query("SELECT sum(total_score) as total_score FROM play_integral  WHERE `type` <> 5
        and create_time >= ? and city = ?", array($start, $city))->current();

        $month_score = $adapter->query("SELECT * FROM play_city where city = ?", array($city))->current();

        if (false !== $total_score && (int)$total_score->total_score >= (int)$month_score->integral) {
            return false;
        } else {
            return true;
        }

    }

    /**
     * 获取某人的所有点赞
     * @param $uid
     * @param $obj_id
     * @return bool
     */
    public function get_prizes($obj_id)
    {
        if (!$this->checkMid($obj_id)) {
            return 0;
        }
        $post_data = $this->_getMdbSocialCircleMsg()->findOne(array('_id' => new \MongoId($obj_id)));

        return $post_data['like_number'];
    }

    /**
     * 新手任务 21新手任务额外
     * @param $uid
     * @param $city
     * @return bool
     */
    public function newer_task_integral($uid, $city)
    {
        $adapter = $this->_getAdapter();
        $new_plus = $adapter->query("SELECT * FROM play_integral
      WHERE `uid`= ? and `type` = ? ", array($uid, 21))->current();
        if ($new_plus) {
            return false;
        }

        $task = $adapter->query("SELECT * FROM play_task_integral WHERE `city`=? ", array($city))->current();
        $new = unserialize($task->new_task);

        if (0 === count($new)) {
            return false;
        }

        $news = '';
        $arr[] = $uid;

        if ($new) {
            foreach ($new as $n) {
                $news .= '`type` = ? or ';
                $arr[] = $n;
            }
            $news = rtrim($news, " or ");
        } else {
            $news = "1=1";
        }

        $new_task = $adapter->query("SELECT  count(distinct(type)) as ct  FROM play_integral
                    WHERE `uid`= ? and (" . $news . ")", $arr)->current();

        if (false === $new_task || (int)$new_task->ct === 0 || (int)$new_task->ct !== count($new)) {
            return false;
        }


        //新手额外
        $s1 = $this->addIntegral($uid, $task->new_plus, 1, 21, 0, $city);
        if (!$s1) {
            return false;
        } else {
            return $task->new_plus;
        }
    }

    /**
     * 每日积分任务
     * @param $uid
     * @param $city
     * @return bool
     */
    public function days_task_integral($uid, $city)
    {
        $adapter = $this->_getAdapter();
        $start = strtotime(date('Y-m-d'));

        $today_plus = $adapter->query("SELECT * FROM play_integral
      WHERE `uid`= ? and create_time >= ? ", array($uid, $start))->toArray();
        $dotask = $today_plus;
        if($today_plus){//判断给过今日任务，或者扣除每日任务没有给的多
            $give = $get = 0;
            foreach($today_plus as $t){
                if($t['type']==10){
                    $give++;
                }
                if($t['type']==105){
                    $get++;
                }
            }
            if( $give > $get ){
                return false;
            }
        }else{
            return false;
        }

        $task = $adapter->query("SELECT * FROM play_task_integral WHERE `city`= ? ", array($city))->current();
        if(!$task){
            return false;
        }
        $day = unserialize($task->day_task);

        $count = count($day);

        if (0 === $count || false === $day) {
            return false;
        }

        if (in_array(22, $day)) {//如果邀请注册是任务
            $invite = $adapter->query("SELECT * FROM invite_member WHERE `sourceid`= ? and register_time > ? and status > 0",
                array($uid, $start))->toArray();
            if (!$invite) {
                return false;
            }
            $count--;
        }

        $days = '';
        $arr[] = $uid;
        $arr[] = $start;

        if ($day) {
            foreach ($day as $n) {
                if ((int)$n !== 22) {
                    $days .= '`type` = ? or ';
                    $arr[] = $n;
                }
            }
        }
        if ($days === '') {
            $days = "`type`=0";
        } else {
            $days = rtrim($days, " or ");
        }
        $today_task = $adapter->query("SELECT count(distinct(`type`)) as ct FROM play_integral
      WHERE `uid`= ? and create_time >= ? and (" . $days . ")", $arr)->current();

        if (0 === $count && !in_array(22, $day)) {
            return false;
        }

        if (!$today_task || (int)$today_task->ct !== $count) {
            return false;
        }

        //添加积分被扣除的情况 //4,2,5,15,100,103,104,105
        $give_goods = $minus_goods = $give_places = $minus_places = $give_buy = $minus_return = $give_circle = $minus_circle =0;
        foreach($dotask as $d){
            if($d['type']==4){//商品评论
                $give_goods++;
            }
            if($d['type']==106 || $d['type']==107){//删去商品评论
                $minus_goods++;
            }
            if($d['type']==2){//游玩地评论 8 9
                $give_places++;
            }
            if($d['type']==108 || $d['type']==109){//游玩地评论 8 9
                $minus_places++;
            }
            if($d['type']==5){//购买商品积分
                $give_buy++;
            }
            if($d['type']==100){//退款获得积分
                $minus_return++;
            }
            if ($d['type'] == 15 ) {//圈子发言
                $give_circle++;
            }
            if($d['type']==103 || $d['type']==104){//游玩地评论 8 9
                $minus_circle++;
            }
        }

        if($give_goods <= $minus_goods && in_array(4,$arr)){
            return false;
        }

        if($give_places <= $minus_places && in_array(2,$arr)){
            return false;
        }

        if($give_buy <= $minus_return && in_array(5,$arr)){
            return false;
        }

        if($give_circle <= $minus_circle && in_array(15,$arr)){
            return false;
        }

        //今天
        $s1 = $this->addIntegral($uid, $task->day_plus, 1, 10, 0, $city);

        if (!$s1) {
            return false;
        } else {
            return $task->day_plus;
        }
    }

    /**
     * 用户积分明细分类
     * @param $uid
     * @return array
     */
    public function integralGroup($uid)
    {
        $sql = "select sum(total_score) as total_score,uid,`type` from play_integral group by `type` having uid = ?";
        $adapter = $this->_getAdapter();
        $arr = $adapter->query($sql, array($uid))->toArray();
        $inte = [];
        foreach ($arr as $a) {
            $inte[$a['type']] = $a;
        }

        return $inte;

    }

    /**
     * 积分兑换票券 type 101
     * @param $uid
     * @param $city
     * @return bool
     */
    public function inteToQualify($uid, $city)
    {
        $setting = $this->getSetting();

        $integral_quota = $setting->integral_quota;
        $integral_quota = (int)$integral_quota;

        $adapter = $this->_getAdapter();

        $u = $adapter->query("SELECT * FROM play_integral_user WHERE `uid`=?", array($uid))->current();
        if (!$u || (int)$u->total < (int)$integral_quota) {
            return false;
        }
        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();
        if (!$u) {
            $u1 = $adapter->query("INSERT INTO play_integral_user (uid,total) VALUES (?,?) ", array($uid, 0))->count();

            return false;
        } else {
            if ((int)$u->total < (int)$integral_quota) {
                $conn->rollback();

                return false;
            }
            $s1 = $adapter->query("UPDATE play_integral_user SET total=(total-?) WHERE uid=?",
                array($integral_quota, $uid))->count();
            if (!$s1) {
                $conn->rollback();

                return false;
            }
        }

        //积分记录表添加记录
        $s1 = $adapter->query("INSERT INTO play_integral (uid,`type`,total_score,base_score,award_score,object_id,create_time,city,`desc` )
 VALUES (?,?,?,?,?,?,?,?,?)",
            array($uid, 101, $integral_quota, $integral_quota, 1, 0, time(), $city, '积分兑换资格券'))->count();
        if (!$s1) {
            $conn->rollback();

            return false;
        }

        $quota_time = $setting->quota_time;
        $valid_time = time() + ((int)$quota_time * 24 * 3600);

        //票券表插入一条记录
        $s2 = $adapter->query("INSERT INTO play_qualify_coupon
(give_type,create_time, valid_time, pay_time,uid,city,integral_ratio,use_order_id,pay_object_id,from_object_id,status )
 VALUES (?,?,?,?,?,?,?,?,?,?,?)", [1, time(), $valid_time, 0, $uid, $city, $integral_quota, 0, 0, 0, 1])->count();

        if (!$s2) {
            $conn->rollback();

            return false;
        }

        $conn->commit();

        return true;
    }

    private function awardlog($id, $type, $score)
    {
        //17商品评论获赞，18游玩地评论获赞
        if ($type == 17 && $type == 18 && $this->checkMid($id)) {
            $msg_data = $this->_getMdbSocialCircleMsg()->findOne(array('_id' => new \MongoId($id)));

            if (!$msg_data) {
                return;
            }

            if (array_key_exists('integral', $msg_data)) {
                $this->_getMdbSocialCircleMsg()->update(array('_id' => new \MongoId($id)),
                    array('$inc' => array('integral' => $score)));
            } else {
                $this->_getMdbSocialCircleMsg()->update(array('_id' => new \MongoId($id)),
                    array('$set' => array('integral' => $score)));
            }

        }
    }

    /**
     * 获得积分
     * @param $uid
     * @param int $base_score 　基础积分
     * @param int $award_score 　奖励积分
     * @param int $action_type_id
     * @param int $object_id 　获得积分的对象
     * @param string $city 　城市
     * @param int $only 　商品id
     * @return bool
     */
    public function addIntegral(
        $uid,
        $base_score = 0,
        $award_score = 1,
        $action_type_id = 1,
        $object_id = 0,
        $city = 'WH',
        $only = 0,
        $desc = '',
        $msgid = 0
    ) {

        if (!$uid || !$action_type_id ) {
            return false;
        }

        if ($base_score < 0 || $award_score < 0) {
            return false;
        }

        $adapter = $this->_getAdapter();
        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();

        //积分记录表添加记录
        $total_score = (int)$base_score * (int)$award_score;

        $desc = $desc?:self::$tparr[$action_type_id];

        $s1 = $adapter->query("INSERT INTO play_integral (id,uid,`type`,total_score,base_score,award_score,object_id,create_time,city,`desc`,onlyid,msgid )
 VALUES (NULL,?,?,?,?,?,?,?,?,?,?,?)", array(
            $uid,
            $action_type_id,
            $total_score,
            (int)$base_score,
            (int)$award_score,
            $object_id,
            time(),
            $city,
            $desc,
            $only,
            $msgid
        ))->count();

        if (!$s1) {
            $conn->rollback();

            return false;
        }

        //评论添加奖励记录

        $u = $adapter->query("SELECT * FROM play_integral_user WHERE `uid`=?", array($uid))->current();

        if (!$u) {
            //初始化用户数据
            $s1 = $adapter->query("INSERT INTO play_integral_user (id,uid,total) VALUES (NULL ,?,?)",
                array($uid, $total_score))->count();
            if (!$s1) {
                $conn->rollback();

                return false;
            }
        } else {
            $s1 = $adapter->query("UPDATE play_integral_user SET total=total+{$total_score} WHERE uid=?",
                array($uid))->count();
            if (!$s1) {
                $conn->rollback();

                return false;
            }
        }

        $conn->commit();

        return true;
    }

    /**
     * 扣除积分
     * @param $uid
     * @param int $base_score 　基础积分
     * @param int $award_score 　奖励积分
     * @param int $action_type_id
     * @param int $object_id 　获得积分的对象
     * @param string $city 　城市
     * @param int $only 　商品id
     * @return bool
     */
    public function subIntegral(
        $uid,
        $base_score = 0,
        $award_score = 1,
        $action_type_id = 1,
        $object_id = 0,
        $city = 'WH',
        $only = 0,
        $desc = '',
        $msgid = 0
    ) {

        if (!$uid || !$action_type_id || $base_score < 0 || $award_score < 0) {
            return false;
        }

        $adapter = $this->_getAdapter();
        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();

        //积分记录表添加记录
        $total_score = (int)$base_score * (int)$award_score;

        $desc = $desc?:self::$tparr[$action_type_id];

        $u = $adapter->query("SELECT * FROM play_integral_user WHERE `uid`=?", array($uid))->current();

        if (!$u) {
            //初始化用户数据
            $s1 = $adapter->query("INSERT INTO play_integral_user (id,uid,total) VALUES (NULL ,?,?)",
                array($uid, 0))->count();
            if (!$s1) {
                $conn->rollback();

                return false;
            }
        } else {
            //应该扣除的积分
            if ((int)$u->total < (int)$total_score){
                $total_score = (int)$u->total;
            }

            //积分明细
            $s1 = $adapter->query("INSERT INTO play_integral (id,uid,`type`,total_score,base_score,award_score,object_id,create_time,city,`desc`,onlyid,msgid )
 VALUES (NULL,?,?,?,?,?,?,?,?,?,?,?)", array(
                $uid,
                $action_type_id,
                $total_score,
                (int)$base_score,
                (int)$award_score,
                $object_id,
                time(),
                $city,
                $desc,
                $only,
                $msgid
            ))->count();

            if (!$s1) {
                $conn->rollback();
                return false;
            }

            if($total_score){
                $s2 = $adapter->query("UPDATE play_integral_user SET total=total-{$total_score} WHERE uid=?",
                    array($uid))->count();
                if (!$s2) {
                    $conn->rollback();
                    return false;
                }
            }

        }

        $conn->commit();

        return true;
    }


    /**
     * 消费积分(包含退货扣除积分)
     * @param $uid
     * @param int $base_score 　基础积分
     * @param int $award_score 　奖励积分
     * @param int $action_type_id
     * @param int $object_id 　获得积
     * @return bool
     */
    public function useIntegral(
        $uid,
        $total_score = 0,
        $base_score = 0,
        $award_score = 1,
        $action_type_id = 1,
        $object_id = 0,
        $city = 'WH'
    ) {
        if (!$uid || !$action_type_id || !$base_score) {
            return false;
        }
        if ($award_score <= 0 || $base_score <= 0) {
            return false;
        }

        $adapter = $this->_getAdapter();

        $u = $adapter->query("SELECT * FROM play_integral_user WHERE `uid`=?", array($uid))->current();

        if (!$u) {
            $u1 = $adapter->query("INSERT INTO play_integral_user (uid,total) VALUES (?,?) ", array($uid, 0))->count();

            return false;
        } else {
            if ((int)$u->total < (int)$total_score && (int)$action_type_id !== 100) {
                return false;
            } else {
                //当买了退货积分其它地方用了，这里扣为０
                $total_score = $u->total;
            }
            $conn = $adapter->getDriver()->getConnection();
            $conn->beginTransaction();
            $s1 = $adapter->query("UPDATE play_integral_user SET total=total-{$total_score} WHERE uid=?",
                array($uid))->count();
            if (!$s1) {
                $conn->rollback();

                return false;
            }
        }
        //积分记录表添加记录
        $s1 = $adapter->query("INSERT INTO play_integral (id,uid,`type`,total_score,base_score,award_score,object_id,create_time,city,`desc` )
 VALUES (NULL,?,?,?,?,?,?,?,?,?,?,?)",
            array(
                $uid,
                $action_type_id,
                $total_score,
                $base_score,
                $award_score,
                $object_id,
                time(),
                $city,
                self::$tparr[$action_type_id]
            ))->count();
        if (!$s1) {
            $conn->rollback();

            return false;
        }

        $conn->commit();

        return true;
    }

    /**
     * @param $uid
     * @return int
     */
    public function getUserIntegral($uid)
    {
        $adapter = $this->_getAdapter();
        $integral = $adapter->query("SELECT * FROM play_integral_user WHERE `uid`=?", array($uid))->current();
        if ($integral) {
            $i = $integral->total;

            return (int)$i;
        } else {
            $adapter->query("INSERT INTO play_integral_user (uid,total) VALUES (?,?) ", array($uid, 0));

            return 0;
        }
    }

    /**
     * 获得某一项用户的获得次数
     * @param $uid
     * @param $type
     * @return int
     */
    public function getUserIngegralCountByType($uid, $type)
    {
        $adapter = $this->_getAdapter();
        $integral = $adapter->query("SELECT * FROM play_integral WHERE `uid`=? and `type` = ?",
            array($uid, $type))->current();
        if ($integral) {
            return count($integral);
        } else {
            return 0;
        }
    }

    /**
     * 邀请朋友
     * @param $uid
     * @param $phone
     * @param $city
     * @return bool
     */
    public function doInvite($uid,$phone,$city){

        $ir = RedCache::fromCacheData('I:invite:' . $city, function () use ($city) {
            return $this->_getInviteRule()->getByRuleId($city);
        }, 3600, true);

        if(!$ir){
            return false;
        }
        $ir = (object)$ir;
        $start = strtotime(date('Y-m-d'));

        $adapter = $this->_getAdapter();
        $count = $adapter->query("SELECT * FROM play_integral WHERE `uid`=? and `type` = ? and create_time > ? ",
            array($uid, 6,$start))->count();

        if($ir->invite_per_day <= $count || (int)$ir->r_inviter_type === 1){
            return false;
        }

        $s1 = $this->addIntegral($uid,$ir->r_inviter_award, 1, 6, $phone, $city);

        if (!$s1) {
            return false;
        } else {
            //新手积分
            $this->days_task_integral($uid, $city);

            return true;
        }
    }

    /**
     * 接受邀请
     */
    public function acceptInvite($uid,$phone,$city){
        $ir = RedCache::fromCacheData('I:invite:' . $city, function () use ($city) {
            return $this->_getInviteRule()->getByRuleId($city);
        }, 3600, true);

        if(!$ir){
            return false;
        }
        $ir = (object)$ir;
        $start = strtotime(date('Y-m-d'));

        $adapter = $this->_getAdapter();
        $count = $adapter->query("SELECT * FROM play_integral WHERE `uid`=? and `type` = ? and create_time > ? ",
            array($uid, 25,$start))->count();

        if(0 < $count || (int)$ir->r_inviter_type === 1){
            return false;
        }

        $s1 = $this->addIntegral($uid,$ir->r_inviter_award, 1, 25, $phone, $city);

        if (!$s1) {
            return false;
        } else {
            //新手积分
            return true;
        }
    }


    /**
     * 临时
     * @param $uid
     * @param $gid
     * @param $price
     * @param $city
     * @param string $coupon_name
     * @param $sn
     * @param $time
     * @return bool|int
     */
    public function useGood3($uid, $gid, $price, $city,$coupon_name='',$sn,$time)
    {
        if (!$price || !$uid || !$gid) {
            return false;
        }

        //处理商品的使用积分
        $adapter = $this->_getAdapter();

        $goods = $adapter->query("SELECT * FROM play_organizer_game WHERE `id`=?",
            array($gid))->current();

        $score = 0;
        if($goods && $goods->buy_integral){
            $score = $price * (float)$goods->buy_integral;
        }

        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();

        //购买获得积分
        $setting = $this->getSetting();

        if(!$score){
            //$score = $price * $setting->good_integral_price;
        }

        if ($score < 0.5) {
            $score = 0;
        } else {
            $score = round($score);
        }
        $score = (int)$score;

        //积分记录表添加记录
        $total_score = (int)$score;

        $desc = '使用'.($coupon_name?:'商品').'获得积分';

        $s1 = $adapter->query("select * from play_integral where uid = ? and `type` = 23 and object_id = ?",array($uid,$sn))->current();

        if($s1){
            return false;
        }

        $s3 = $adapter->query("INSERT INTO play_integral (id,uid,`type`,total_score,base_score,award_score,object_id,create_time,city,`desc` )
 VALUES (NULL,?,?,?,?,?,?,?,?,?)", array(
            $uid,
            23,
            $score,
            (int)$score,
            1,
            $sn,
            $time,
            $city,
            $desc
        ))->count();

        if (!$s3) {
            $conn->rollback();

            return false;
        }

        $s4 = $adapter->query("UPDATE play_integral_user SET total=total+{$total_score} WHERE uid=?",
            array($uid))->count();
        if (!$s4) {
            $conn->rollback();

            return false;
        }

        $conn->commit();
        //每日任务奖励积分
        //$this->days_task_integral($uid, $city);

        return (int)$score;
    }

}



