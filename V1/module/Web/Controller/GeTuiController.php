<?php

namespace Web\Controller;

use Deyi\BaseController;
use Deyi\JsonResponse;
use library\Service\System\Cache\RedCache;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Deyi\GeTui\GeTui;
use Deyi\SendMessage;

class GeTuiController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    public function indexAction() {

        exit('废弃 已改为Script/AutoGeTui.php');
        
        $type = (int)$this->getQuery('type');

        if (!$type) {
            $time = time();
            $start_time = strtotime(date("Y-m-d 00:00:00"));

            $am_time = $start_time + 3600 * 8;
            $pm_time = $start_time + 3600 * 20;

            //todo 判断时间是否大于早上8点 小于晚上10点
            if ($time < $am_time or $time > $pm_time) {
                exit;
            }

            //定时推送 result = 0 push_type = 4
            $time_push = $this->_getPlayPushTable()->fetchLimit(0, 1, array(),array('result' => 0, 'push_type' => 4, 'push_time > ?' => $start_time, 'push_time < ?' => $start_time + 24*3600), array('push_time' => 'ASC'))->current();

            $r = new RedCache();

            if ($time_push &&  (-300 < ($time_push->push_time - $time)) && (($time_push->push_time - $time) < 300)) {

                $rep = $this->_getPlayPushTable()->update(array('result' => 2, 'log' => 1), array('id' => $time_push->id, 'result' => 0));

                if (!$rep) {
                    $r->set('ge_tui', '失败了');
                    exit;
                }

                $content = array(
                    'title' => htmlspecialchars_decode($time_push->title, ENT_QUOTES),
                    'info'  => htmlspecialchars_decode($time_push->info, ENT_QUOTES),
                    'type'  => (int)$time_push->link_type,
                    'id'    => $time_push->link_id,
                    'time'  => $time,
                );

                $geTui = new GeTui();
                $result = $geTui->pushMessageToApp(htmlspecialchars_decode($time_push->info, ENT_QUOTES), json_encode($content), date('Y_m_d_H', $time));

                $r->set('ge_tui', json_encode($result));

                $this->_getPlayPushTable()->update(array('result' => 1), array('id' => $time_push->id));

                exit;

            } else {
                $r->set('ge_tui_r', date('Y-m-d H:i', $time). '无推送任务');
                exit;
            }


            //收藏推送
            /*$collect_start =  $start_time + 30600;//8点30
            $collect_end =  $start_time + 33000; //9点10

            if ($time >= $collect_start && $time < $collect_end) {
                //$this->getCollect();
                exit;
            }*/

        } elseif ($type) {

            switch ($type) {
                case 1: //1元秒杀
                    $this->getSecond();
                    break;
                case 2: //周末
                    $this->getWeekend();
                    break;
                case 3: //用户关注
                    $this->getCollect();
                    break;
                default:
                    exit ('请选择正确的推送方式');
            }
        }

    }

    //1元秒杀
    private function getSecond() {

        // todo 处理首页 还是 专题页面
        $timer = time();
        $where = array(
            'status' => 0,
            'ac_type' => 1,
            '(s_time = 0 OR (s_time < ? AND e_time > ?))' => array($timer, $timer),
        );

        $data = $this->_getPlayActivityTable()->fetchLimit(0, 3, array(), $where, array('id' => 'DESC'))->current();

        //推送的标题
        $title = '周三见, 这多好玩的遛娃地只要一块钱! 不抢白不抢';

        if ($data) {
            $content = array(
                'title' => '玩翻天',
                'info'  => $title,
                'type'  => 1,
                'id'    => $data->id,
                'time'  => $timer,
            );
        } else {
            $content = array(
                'title' => '玩翻天',
                'info'  => $title,
                'type'  => 6,
                'id'    => '1',
                'time'  => $timer,
            );
        }

        $geTui = new GeTui();
        $result = $geTui->pushMessageToApp($title, json_encode($content),'玩翻天_一元秒杀');

        $this->_getPlayPushTable()->insert(array(
            'title' => '玩翻天',
            'info' => $title,
            'link_type' => $content['type'],
            'push_type' => 4,
            'push_time' => $timer,
            'uptime' => $timer,
            'link_id' => $content['id'],
            'result' => ($result['result'] == 'ok') ? 1 :2,
            // 'area' => $area,
        ));

    }

    //嗨周末
    private function getWeekend() {

        // todo 处理首页 还是 专题页面
        $timer = time();
        $where = array(
            'status' => 0,
            'ac_type' => 2,
            '(s_time = 0 OR (s_time < ? AND e_time > ?))' => array($timer, $timer),
        );

        $data = $this->_getPlayActivityTable()->fetchLimit(0, 3, array(), $where, array('id' => 'DESC'))->current();

        //推送的标题
        $title = '周末不要宅, 速来看看本周末带娃怎么嗨';

        if ($data) {
            $content = array(
                'title' => '玩翻天',
                'info'  => $title,
                'type'  => 1,
                'id'    => $data->id,
                'time'  => $timer,
            );
        } else {
            $content = array(
                'title' => '玩翻天',
                'info'  => $title,
                'type'  => 6,
                'id'    => '1',
                'time'  => $timer,
            );
        }

        $geTui = new GeTui();
        $result = $geTui->pushMessageToApp($title, json_encode($content),'玩翻天_嗨周末');
        $this->_getPlayPushTable()->insert(array(
            'title' => '玩翻天',
            'info' => $title,
            'link_type' => $content['type'],
            'push_type' => 4,
            'push_time' => $timer,
            'uptime' => $timer,
            'link_id' => $content['id'],
            'result' => ($result['result'] == 'ok') ? 1 :2,
            // 'area' => $area,
        ));


    }

    //用户关注
    private function getCollect() {

        //判断有没有 收藏推送的类型
        $diy_data = $this->_getPlayPushTable()->get(array('push_type' => 3));

        if (!$diy_data) {
            exit('sorry, 收藏推送已经被删除');
        }

        $time = time();
        // 排除当天已经推送了的
        if (date('Y-m-d', $diy_data->push_time) == date('Y-m-d', $time)) {
            exit('已经推送了');
        }


        // todo 卡券状态 及 活动状态
        //收藏的shop 卡券新增
        $shop_coupon_sql = "SELECT
play_user_collect.uid,
Count(play_user_collect.uid) AS count,
play_shop.shop_name,
play_coupons_linker.coupon_id,
play_user.token
FROM
play_user_collect
LEFT JOIN play_shop ON play_user_collect.link_id = play_shop.shop_id
LEFT JOIN play_coupons_linker ON play_shop.shop_id = play_coupons_linker.shop_id
LEFT JOIN play_coupons ON play_coupons_linker.coupon_id = play_coupons.coupon_id
LEFT JOIN play_message_push ON play_coupons.coupon_id = play_message_push.lid
LEFT JOIN play_user ON play_user_collect.uid = play_user.uid
WHERE
play_user_collect.type = 'shop' AND
play_message_push.type = 'coupon' AND
play_coupons.coupon_status = 1 AND
play_message_push.deadline > ($time-86400)
GROUP BY
play_user_collect.uid";

        $shop_coupon = $this->query($shop_coupon_sql);

        //收藏的shop game新增
        $shop_game_sql = "SELECT
play_user_collect.link_id,
play_user_collect.uid,
Count(play_user_collect.uid) AS count,
play_shop.shop_name,
play_organizer_game.title,
play_organizer_game.id,
play_user.token
FROM
play_user_collect
LEFT JOIN play_shop ON play_user_collect.link_id = play_shop.shop_id
LEFT JOIN play_game_info ON play_user_collect.link_id = play_game_info.shop_id
LEFT JOIN play_organizer_game ON play_game_info.gid = play_organizer_game.id
LEFT JOIN play_message_push ON play_organizer_game.id = play_message_push.lid
LEFT JOIN play_user ON play_user_collect.uid = play_user.uid
WHERE
play_user_collect.type = 'shop' AND
play_message_push.type = 'game' AND
play_organizer_game.status > 0  AND
play_message_push.deadline > ($time-86400)
GROUP BY
play_user_collect.uid";

        $shop_game = $this->query($shop_game_sql);


        //收藏的 活动组织者 game更新
        $game_sql = "SELECT
play_user_collect.link_id,
play_organizer.name,
play_organizer_game.title,
play_user_collect.uid,
play_organizer_game.id,
play_user.token,
COUNT(play_user_collect.uid) AS count
FROM
play_user_collect
LEFT JOIN play_organizer ON play_user_collect.link_id = play_organizer.id
LEFT JOIN play_organizer_game ON play_user_collect.link_id = play_organizer_game.organizer_id
LEFT JOIN play_message_push ON play_organizer_game.id = play_message_push.lid
LEFT JOIN play_user ON play_user_collect.uid = play_user.uid
WHERE
play_user_collect.type = 'organizer' AND
play_message_push.type = 'game' AND
play_organizer_game.status > 0 AND
play_message_push.deadline > ($time-86400)
GROUP BY
play_user_collect.uid";

        $game_data = $this->query($game_sql);

        $data = array();
        foreach ($shop_coupon as $cou) {
            if (array_key_exists($cou['uid'], $data)) {
                $data[$cou['uid']]['count'] = $data[$cou['uid']]['count'] + $cou['count'];
            } else {
                $data[$cou['uid']] = array(
                    'uid' => $cou['uid'].'_'.substr($cou['token'], 0, 10),
                    'count' => $cou['count'],
                    'link_id' => $cou['coupon_id'],
                    'name' => $cou['shop_name'],
                    'type' => 2, //卡券
                );
            }
        }

        foreach ($shop_game as $ga) {
            if (array_key_exists($ga['uid'], $data)) {
                $data[$ga['uid']]['count'] = $data[$ga['uid']]['count'] + $ga['count'];
            } else {
                $data[$ga['uid']] = array(
                    'uid' => $ga['uid'].'_'.substr($ga['token'], 0, 10),
                    'count' => $ga['count'],
                    'link_id' => $ga['id'],
                    'name' => $ga['shop_name'],
                    'type' => 3, //活动
                );
            }
        }

        foreach ($game_data as $da) {
            if (array_key_exists($da['uid'], $data)) {
                $data[$da['uid']]['count'] = $data[$da['uid']]['count'] + $da['count'];
            } else {
                $data[$da['uid']] = array(
                    'uid' => $da['uid'].'_'.substr($da['token'], 0, 10),
                    'count' => $da['count'],
                    'link_id' => $da['id'],
                    'name' => $da['name'],
                    'type' => 3, //活动
                );
            }
        }

        $geTui = new GeTui();
        $content = array(
            'title' => '玩翻天',
            'info'  => '许久不见, 玩翻天又多了新板眼, 速来',
            'type'  => 6,
            'id'    => '1',
            'time'  => $time,
        );

        //var_dump($shop_coupon_sql);
        //var_dump($data);
        exit;


        $i = 1;
        foreach ($data as $val) {
            if ($val['count'] > 1) {
                $status = $geTui->Push($val['uid'], '许久不见, 玩翻天又多了新板眼, 速来', json_encode($content));
            } else {
                $status = $geTui->Push($val['uid'],  '你关注的【'. $val['name'].'】又有了新玩法, 快来低价抢吧', json_encode(array(
                    'title' => '玩翻天',
                    'info'  => '你关注的【'. $val['name'].'】又有了新玩法, 快来低价抢吧',
                    'type'  => $val['type'],
                    'id'    => $val['link_id'],
                    'time'  => $time,
                )));
            }
            if ($status) {
                $i++;
            }
        }

        if ($i > 1) {
            // push_num + 1 push_time 更新
            $this->_getPlayPushTable()->update(array('push_time' => $time, 'push_num' => new Expression('push_num + 1')), array('push_type' => 3));
        }
        exit;

    }

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

}
