<?php
/**
 * index 积分
 */

namespace ApiScore\Controller;

use Deyi\Account\Account;
use Deyi\BaseController;
use Deyi\Coupon\Coupon;
use Deyi\Integral\Integral;
use library\Service\System\Cache\RedCache;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Predicate\In;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Response;
use Deyi\JsonResponse;

class IndexController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;


    /**
     * @param int $days 连续签到天数
     * @param $sign_day 连续签到几天获得奖励
     * @param $sign_integral    连续签到奖励分数
     * @param $sign_one     签到一天获得分数
     * @return int
     */
    public function sign_score($days = 0, $sign_day, $sign_integral, $sign_one)
    {
        if ($days) {
            if ($days % $sign_day === 0) {
                $tomorrow_score = (int)$sign_integral+(int)$sign_one;
            } else {
                $tomorrow_score = (int)$sign_one;
            }
        } else {
            $tomorrow_score = (int)$sign_one;
        }

        return $tomorrow_score;
    }

    //我的积分
    public function indexAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = (int)$this->getParams('uid', 0);

        if (!$uid) {
            return $this->jsonResponseError('参数错误');
        }

        $user_data = $this->_getPlayUserTable()->get(array('uid' => $uid));

        if (!$user_data) {
            return $this->jsonResponseError('用户不存在');
        }


        $inte = new Integral();
        $inte_num = $inte->getUserIntegral($uid);
        $setting = $inte->getSetting();
        $s_time = strtotime(date("Y-m-d"));  //有前导零
        $page = (int)$this->getParams('page', 1);
        $pageSum = (int)$this->getParams('pagenum', 10);;
        $start = ($page - 1) * $pageSum;
        $log = $this->_getPlayIntegralTable()->get("create_time>{$s_time} and uid={$uid} and `type`=8");

        $quaily = new Coupon();
        $q = $quaily->myQualify($uid);
        $cash_coupon =$q->c;

        //昨天是否签到了
        $yes = RedCache::fromCacheData('D:yestd:' . $uid, function () use ($s_time,$uid) {
            $y_time = $s_time - 3600*24;
            $n_time = time();
            $yes = $this->_getPlayIntegralTable()->get("create_time > {$y_time} and create_time <= {$n_time} and uid={$uid} and `type`=8");
            return $yes;
        }, 24 * 3600, true);

        $sign_day = ((int)$user_data->sign_in_days < 1)?0:(int)$user_data->sign_in_days;//连续签到天数
        if(!$yes){
            $sign_day = 0;
        }

        $data = array(
            'uid' => $uid,
            'username' => $user_data->username,
            'user_detail' => $user_data->user_alias,
            'img' => $this->getImgUrl($user_data->img),
            'score' => (int)$inte_num, //我的积分
            'today_sign' => $log ? 1 : 0,//今日是否签到
            'today_score' => $this->sign_score($user_data->sign_in_days + 1, $setting->sign_day, $setting->sign_integral, $setting->sign_one),//今日签到获得积分
            'tomorrow_score' => $this->sign_score($user_data->sign_in_days + 1, $setting->sign_day, $setting->sign_integral, $setting->sign_one),// 明日签到获得积分   连续几日额外获得
            'sign_day' => $sign_day,//连续签到天数
            'cash_coupon' => (int)$cash_coupon,//已有资格券
        );

        //积分商品列表
        // $gameData = $this->_getPlayOrganizerGameTable()->fetchLimit($start, $pageSum, $columns = array(), $where = array('end_time > ?' => $s_time, 'qualified' => 2, 'status > ?' => 0), $order = array('start_time' => 'desc'));

        $data['coupon_list'] = array();
        $db = $this->_getAdapter();
        $city = $this->getCity();
        $gameData = $db->query("select g.*,MIN(i.integral) as integral from play_organizer_game as g  LEFT JOIN  play_game_info as i ON  i.gid=g.id
WHERE  g.end_time>? and i.integral!=0 AND g.status>0 and g.city = '{$city}' AND i.status>0 GROUP  BY i.gid Limit ?,?", array(time(), $start, $pageSum))->toArray();

        if (isset($gameData[0]['id'])) {
            foreach ($gameData as $g) {
                $data['coupon_list'][] = [
                    'cover' => $this->getImgUrl($g['thumb']),
                    'id' => $g['id'],
                    'title' => $g['title'],
                    'surplus_num'=>$g['ticket_num']-$g['buy_num'],
                    'price' => $g['low_price'],
                    'editor_talk' => $g['editor_talk'],
                    'begin_time' => $g['start_time'],
                    'end_time' => $g['end_time'],
                    'integral' => $g['integral']  //所需积分
                ];

            }
        }


        return $this->jsonResponse($data);
    }


    //积分中心个人 原生
    public function mycenterAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = (int)$this->getParams('uid', 0);
        $p = (int)$this->getParams('p', 1);

        $pagenum = (int)$this->getParams('pagenum', 10);

        $offset = ($p - 1) * $pagenum;
        if (!$uid) {
            return $this->jsonResponseError('参数错误');
        }

        $user_data = $this->_getPlayUserTable()->get(array('uid' => $uid));
        $inte = new Integral();
        $inte_num = $inte->getUserIntegral($uid);

        $db = $this->_getAdapter();
        $res = $db->query("select * from play_integral WHERE  uid = ? and total_score > 0 ORDER BY  create_time DESC  limit {$offset},{$pagenum}", array($uid));


        $score_log = array();
        foreach ($res as $v) {
            $score_log[] = array(
                'id' => $v->id,
                'title' => $v->desc,
                'score' => (string)($v->type < 100 ? $v->total_score : $v->total_score * -1),
                'dateline' => $v->create_time
            );
        }

        // 完善 log
        $data = array(
            'uid' => $uid,
            'username' => $user_data->username,
            'img' => $this->getImgUrl($user_data->img),
            'user_detail' => $user_data->user_alias,
            'score' => $inte_num,
            'score_log' => $score_log
        );


        return $this->jsonResponse($data);

    }

    //我要秒杀
    public function seckillAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = (int)$this->getParams('uid', 0);
        $city = $this->getCity();
        $quaily = new Coupon();
        $q = $quaily->myQualify($uid);
        $inte = new Integral();
        $inte_num = $inte->getUserIntegral($uid);
        $s = $inte->getSetting();
        $data = array(
            'qualify_num' => $q->c, //资格券数量
            'score' => (int)$inte_num, //积分
            'exchange_score' => (int)$s->integral_quota,  //兑换率
        );
        $page = (int)$this->getParams('page', 1);
        $pageSum = (int)$this->getParams('pagenum', 10);
        $start = ($page - 1) * $pageSum;


        //秒杀商品列表
        $gameData = $this->_getPlayOrganizerGameTable()->getQGameWithInfo($start, $pageSum, $columns = array(), $where = array('city'=>$city,'play_game_info.end_time > ?' => time(), 'play_game_info.qualified' => 2, 'play_game_info.status > ?' => 0), $order = array('start_time' => 'desc'));


        $data['coupon_list'] = array();


        if (false !== $gameData && count($gameData) > 0) {
            foreach ($gameData as $g) {
                $data['coupon_list'][] = [
                    'cover' => $this->getImgUrl($g['cover']),
                    'id' => $g['id'],
                    'title' => $g['title'],
                    'price' => $g['low_price'],
                    'editor_talk' => $g['editor_talk'],
                    'begin_time' => $g['start_time'],
                    'end_time' => $g['end_time'],
                ];

            }
        }

        return $this->jsonResponse($data);
    }


    //兑换积分
    public function exchangeAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $city = $this->getCity();

        $uid = $this->getParams('uid');
        if (!$uid) {
            return $this->jsonResponseError('参数错误');
        }

        $inte = new Integral();

        $status = $inte->inteToQualify($uid, $city);
        if ($status) {
            return $this->jsonResponse(array('status' => 1, 'message' => '兑换成功', 'surplus' => 1));
        }else{
            return $this->jsonResponse(array('status' => 0, 'message' => '积分不足', 'surplus' => 0));
        }
    }
}
