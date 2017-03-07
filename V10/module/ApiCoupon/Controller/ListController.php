<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ApiCoupon\Controller;

use ApiTag\Controller\IndexController;
use Deyi\BaseController;
use Deyi\GetCacheData\CouponCache;
use Deyi\GetCacheData\GoodCache;
use library\Service\System\Cache\RedCache;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Response;
use Deyi\JsonResponse;

class ListController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;


    //卡券列表接口
    public function indexAction()
    {

        if (!$this->pass(false)) {
            return $this->failRequest();
        }

        $city = $this->getCity();
        $timer = time();

        $param['addr_x'] = $this->getParams('addr_x'); //坐标
        $param['addr_y'] = $this->getParams('addr_y'); //坐标
        $param['age_min'] = (int)$this->getParams('age_min', 0);  //年齡小
        $param['age_max'] = (int)$this->getParams('age_max', 100);  //年齡大
        $param['age_max'] = ($param['age_max'] > 100) ? 100 : $param['age_max'];
        $param['status'] = $this->getParams('order','new'); // 排序方式 new(最新上架) hot（最热） price_up（价格最高） price_down（价格最低）　默认new
        $param['id'] = (int)$this->getParams('id', 0); // 分类 0 所有分类 默认为0
        $param['rid'] = (int)$this->getParams('rid', 0); // 区域id 默认为0
        $param['aid'] = (int)$this->getParams('aid', 0); // 商圈id 默认为0
        $page = (int)$this->getParams('page', 1);
        $wap = (int)$this->getParams('wap', 0);//区分微信请求
        $param['limit'] = (int)$this->getParams('page_num', 5);
        $param['start'] = ($page - 1) * $param['limit'];
        $param['city'] = $city;
        $data['coupon_list'] = $this->getGood($param);

        if($wap==1){
            $tagData = $this->_getPlayLabelTable()->fetchLimit(0, 100, $columns = array(), array('status >= ?' => 2,'label_type>=2','city' => $city,'object_id is not null'), array('dateline' => 'desc'));

            foreach ($tagData as $tag) {
                $data['tag'][] = array(
                    'id' => $tag['id'],
                    'name' => $tag['tag_name'],
                );
            }
            //获取商圈
            $areaData = json_decode(RedCache::get("areaData"),true);
            if(!$areaData){
                $areaData = $this->_getPlayRegionTable()->fetchAll(array('acr'=>$city,'level'=>4),array('rid'=>'asc'))->toArray();
                $child = array();
                $time = time();
                foreach($areaData as $k=>$v){
                    $erid = $v['rid']+100;
                    $where = "acr = 'WH'
AND LEVEL = 5
AND (
	SELECT
		count(play_organizer_game.id)
	FROM
		play_organizer_game
	LEFT JOIN play_label_linker ON play_label_linker.object_id=play_organizer_game.id
	LEFT JOIN play_game_info ON play_organizer_game.id=play_game_info.gid
	LEFT JOIN play_shop ON play_game_info.shop_id = play_shop.shop_id
WHERE
	play_shop.busniess_circle BETWEEN play_region.rid
	AND play_region.rid
	AND play_organizer_game. STATUS > 0
	AND play_organizer_game.is_together = 1
	AND play_label_linker.link_type = 2
	AND play_organizer_game.city = 'WH'
) > 0
AND rid > {$v['rid']}
AND rid < {$erid}";
//
                    $child = $this->_getPlayRegionTable()->fetchAll($where)->toArray();
                    if(empty( $areaData[$k]['child'])){
                        $areaData[$k]['child'] = array();
                    }
                    $areaData[$k]['child']=$child;
                }
                RedCache::set('areaData',json_encode($areaData),86400);
            }
            $data['area'] = $areaData;

        }

        return $this->jsonResponse($data);
    }

    public function getGood($param)
    {
        $timer = time();
        $where = "play_organizer_game.status > 0 AND  play_organizer_game.is_together = 1 AND play_label_linker.link_type = 2 and play_organizer_game.city ='{$param['city']}'";

        if ($param['id']) {
            $where = $where . ' AND play_label_linker.lid = ' . $param['id'];
        }

        if ($param['age_min'] !=0 && $param['age_max']!=100) {
            $where .= " AND (({$param['age_min']} >= play_organizer_game.age_min AND {$param['age_min']} <= play_organizer_game.age_max) OR ({$param['age_max']} >= play_organizer_game.age_min AND {$param['age_max']} <= play_organizer_game.age_max) OR ({$param['age_min']} <= play_organizer_game.age_min AND {$param['age_max']} >= play_organizer_game.age_min) OR ({$param['age_min']} <= play_organizer_game.age_max AND {$param['age_max']} >= play_organizer_game.age_max))";
        }

        $order = 'play_organizer_game.click_num DESC';
        if ($param['status'] === 'new') {
            $where .= " AND play_organizer_game.down_time >= {$timer} AND play_organizer_game.up_time <= {$timer} AND play_organizer_game.ticket_num > play_organizer_game.buy_num";
            $order = 'play_game_info.id DESC';
        } elseif ($param['status'] === 'hot') {
            $where .= " AND play_organizer_game.down_time >= {$timer} AND play_organizer_game.up_time <= {$timer} AND play_organizer_game.ticket_num > play_organizer_game.buy_num";
            $order = 'play_organizer_game.buy_num+play_organizer_game.coupon_vir DESC';
        } elseif ($param['status'] === 'price_up') {
            $where .= " AND play_organizer_game.down_time >= {$timer} AND play_organizer_game.up_time <= {$timer} AND play_organizer_game.ticket_num > play_organizer_game.buy_num";
            $order = 'play_organizer_game.low_price DESC';
        } elseif ($param['status'] === 'price_down'){
            $where .= " AND play_organizer_game.down_time >= {$timer} AND play_organizer_game.up_time <= {$timer} AND play_organizer_game.ticket_num > play_organizer_game.buy_num";
            $order = 'play_organizer_game.low_price ASC';
        }elseif ($param['status'] === 'sell') {
            $where .= " AND play_organizer_game.down_time >= {$timer} AND play_organizer_game.up_time <= {$timer} AND play_organizer_game.ticket_num > play_organizer_game.buy_num";
            $order = 'play_organizer_game.buy_num DESC';
        } elseif ($param['status'] === 'soon') {
            $where .= " AND play_organizer_game.up_time >= {$timer} AND play_organizer_game.down_time >= {$timer}";
            $order = 'play_organizer_game.start_time DESC';
        } elseif ($param['status'] === 'over') {
            $where .= " AND ((play_organizer_game.down_time <= {$timer} AND play_organizer_game.foot_time > {$timer}) OR (play_organizer_game.down_time >= {$timer} AND play_organizer_game.up_time <= {$timer}  AND play_organizer_game.ticket_num = play_organizer_game.buy_num))";
            $order = 'play_organizer_game.dateline DESC';
        }

        if($param['addr_x'] && $param['addr_y']){
           $where .=RedCache::fromCacheData('D:list'.$param['addr_x'].$param['addr_y'],function()use($param){
                $param['rid']=0;
                $obj = new IndexController();
                $squares = $obj->returnSquarePoint($param['addr_x'],$param['addr_y'],1);
                $sql1 = "select name from play_region where rid in (select busniess_circle from `play_shop` where addr_x<>0 and addr_x>{$squares['right-bottom']['addr_x']} and addr_x<{$squares['left-top']['addr_x']} and addr_y>{$squares['right-bottom']['addr_y']} and addr_y<{$squares['left-top']['addr_y']} ORDER BY play_shop.addr_x DESC,play_shop.addr_y DESC)";

                $rids = $this->query($sql1);
                $info=array();
                foreach($rids as $v){
                    array_push($info,$v['name']);
                }
               return   " AND play_organizer_game.shop_addr ".$this->db_create_in($info);
            },3600*24);
        }

        if ($param['rid']){
            $where .= " AND play_organizer_game.shop_addr in (select name from play_region where rid>={$param['rid']} and rid<{$param['rid']}+100)";
        }

        if ($param['aid']){
            $where .= " AND play_organizer_game.shop_addr=(select name from play_region where rid={$param['aid']})";
        }

        $sql = "SELECT
play_label_linker.object_id,
play_organizer_game.title,
play_organizer_game.thumb,
play_organizer_game.down_time,
play_organizer_game.low_price,
play_organizer_game.low_money,
play_organizer_game.ticket_num,
play_organizer_game.buy_num,
play_organizer_game.coupon_vir,
play_organizer_game.is_together,
play_organizer_game.shop_addr,
play_organizer_game.foot_time,
play_organizer_game.g_buy,
play_organizer_game.city,
play_organizer_game.hot_number,
play_organizer_game.special_labels
FROM
play_label_linker
LEFT JOIN play_organizer_game ON play_label_linker.object_id = play_organizer_game.id
LEFT JOIN play_game_info ON play_game_info.gid =play_organizer_game.id
WHERE
{$where}
and play_game_info.up_time<{$timer} and play_game_info.status=1 AND play_game_info.end_time>{$timer} AND play_game_info.down_time > {$timer} and play_game_info.total_num > play_game_info.buy
GROUP BY
play_organizer_game.id
ORDER BY
{$order}
LIMIT {$param['start']}, {$param['limit']}
";

        /*
          $order_data = $db->query("SELECT * FROM play_game_info
  WHERE play_game_info.gid=?  and status=1 AND play_game_info.end_time>?
  AND play_game_info.down_time > ? and play_game_info.total_num > play_game_info.buy ORDER BY play_game_info.price ASC",
                array($gameData->id, time(), time()))->toArray();

        */

        $res = $this->query($sql);
        $data = array();
        $class_goodcache = new GoodCache();
        foreach ($res as $val) {
            $class_goodcache->getLowPrice($val['object_id']);
            $data_low_price = sprintf('%.2f', $val['low_price']);
            $data_low_money = sprintf('%.2f', $val['low_money']);

            $data[] = array(
                'coupon_id' => $val['object_id'],
                'cover' => $this->_getConfig()['url'] . $val['thumb'],
                'name' => $val['title'],
                'price' => $data_low_price > 0 ? $data_low_price : '0.00',
                'have' => (((int)$val['is_together'] === 1) && ($val['down_time'] > $timer) && (($val['ticket_num'] - $val['buy_num']) > 0)) ? ($val['ticket_num'] - $val['buy_num']) : 0,
                'end_time' => (int)$val['foot_time'],
                'buy' => $val['buy_num'],
                'low_money' => $data_low_money > 0 ? $data_low_money : '0:00',
                'g_buy' => (($val['down_time'] - $timer) > 86400) ? (int)$val['g_buy'] : 0,

                'residue' =>$this->getSurplusNumber($val), //(($val['ticket_num'] - $val['buy_num']) > 0) ? ($val['ticket_num'] - $val['buy_num']) : 0,
                'buy_num'=>$val['buy_num']+$val['coupon_vir'],
                'circle'=>$val['shop_addr'],
                'labels'=>CouponCache::getCouponLabels($val['object_id'],$val['hot_number'],$val['city']),
            );
        }

        return $data;
    }


    //获取商品实际剩余数,过滤了已过期的
    public function getSurplusNumber($val)
    {
        $num = RedCache::get('D:SurplusNumber:' .$val['object_id']);
        if ($num) {
            return $num;
        } else {
            return ($val['ticket_num'] - $val['buy_num']);
        }

    }

    function query($sql)
    {
        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $stmt = $db->query($sql);
        $result = $stmt->execute($stmt);
        return $result;
    }
}
