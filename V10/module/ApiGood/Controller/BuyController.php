<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ApiGood\Controller;

use Deyi\Account\Account;
use Deyi\BaseController;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Db\Sql\Expression;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Http\Response;
use Deyi\JsonResponse;

class BuyController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    /**
     * 可能未使用
     */
//    public function indexAction()
//    {
//        if (!$this->pass()) {
//            return $this->failRequest();
//        }
//
//        $id = $this->getParams('id');
//        $uid = (int)$this->getParams('uid', 0);
//        $gid = (int)$this->getParams('gid', 0); //组团号
//        $addr_x = $this->getParams('addr_x',0);
//        $addr_y = $this->getParams('addr_y',0);
//
//        $gameData = $this->_getPlayOrganizerGameTable()->get(array('id' => $id, 'status > ?' => 0));
//        if (!$gameData) {
//            return $this->jsonResponseError('商品已下架');
//        }
//        //  统计点击次数
//        $this->_getPlayOrganizerGameTable()->update(array('click_num' => new Expression('click_num+1')), array('id' => $id));
//
//        $account=new Account();
//
//        $user_money=$account->getUserMoney($uid);
//        $res = array(
//            'start_time' => $gameData->up_time,
//            'end_time' => $gameData->down_time,
//            'head_time' => $gameData->head_time,
//            'foot_time' => $gameData->foot_time,
//            'time' => time().'',
//            'title' => $gameData->title,
//            'price' => $gameData->low_price,
//            'money' => $gameData->large_price,
//            'buy' => $gameData->buy_num + $gameData->coupon_vir,
//            'surplus_num' => $gameData->ticket_num - $gameData->buy_num,
//            'limit_num' => $gameData->limit_num,
//            'use_time' => $gameData->use_time,
//            'order_method' => $gameData->order_method,
//            'refund_time' => $gameData->refund_time,
//            'process' => $gameData->process,
//            'matters' => $gameData->matters,
//            'is_together' => $gameData->is_together,
//            'join_way' => array(),
//            'join_time' => array(),
//            'g_buy' => $gameData->g_buy,
//            'g_price' => $gameData->g_price,
//            'g_limit' => $gameData->g_limit,
//            'g_info_id' => '', //允许团购的套系id
//            'user_money'=> $user_money
//        );
//
//        if ($gid) {
//            $group_data = $this->_getPlayGroupBuyTable()->get(array('id' => $gid));
//            $res['join_number'] = $group_data->join_number;
//            $res['g_end_time'] = $group_data->end_time;
//            $res['group_uid'] = $group_data->uid;
//        }
//
//
//        //所有可以下单的类型
//        $game_info = $this->_getPlayGameInfoTable()->fetchAll(array('gid' => $id, 'status > ?' => 0));
//
//
//        $buy_num = 0;
//        $total_num = 0;
//        foreach ($game_info as $gData) {
//            $res['price'] = ($gData->price < $res['price']) ? $gData->price : $res['price'];
//            $res['money'] = ($gData->price > $res['money']) ? $gData->price : $res['money'];
//            $res['game_order'][] = array(
//                'order_id' => $gData->id,
//                'way' => $gData->price_name,
//                's_time' => $gData->start_time,
//                'e_time' => $gData->end_time,
//                'price' => $gData->price,
//                'money' => $gData->money,
//                'shop_id' => $gData->shop_id,
//                'buy' => $gData->buy,
//                'total_num' => $gData->total_num,
//                'shop_name' => $gData->shop_name
//            );
//
//            if (!in_array(array('name' => $gData->price_name, 'price' => $gData->price), $res['join_way'])) {
//                array_push($res['join_way'], array('name' => $gData->price_name, 'price' => $gData->price));
//            }
//            if (!in_array(array($gData->start_time, $gData->end_time), $res['join_time'])) {
//                array_push($res['join_time'], array($gData->start_time, $gData->end_time));
//            }
//
//            $buy_num = $buy_num + $gData->buy;
//            $total_num = $total_num + $gData->total_num;
//        }
//        //允许团购的 套系id 理论上只有一条数据
//        if (isset($res['game_order'][0])) {
//            $shopData = $this->_getPlayShopTable()->get(array('shop_id' => $res['game_order'][0]['shop_id']));
//            $res['g_info_id'] = $gameData->g_buy ? $res['game_order'][0]['order_id'] : '0';
//            //参与方式
//            $res['way'] = $res['game_order'][0]['way']; //参与方式
//            //有效期
//            $res['overdue_time'] = date('Y-m-d H:i', $res['game_order'][0]['s_time']) . '~' . date('Y-m-d H:i', $res['game_order'][0]['e_time']);
//            //地点
//            $res['address'] = $res['game_order'][0]['shop_name'];
//            //兑换方式
//            $res['order_method'] = $gameData->order_method;
//            //退款说明
//            $res['back_money'] = '已组团成功的团购商品，不支持退款';
//            //咨询/预约电话
//            $res['phone'] = $shopData->shop_phone;
//            $res['addr_x'] = $shopData->addr_x;
//            $res['addr_y'] = $shopData->addr_y;
//        }
//
//        if (($gameData->down_time - time()) < 86400) {
//            $res['g_buy'] = '0';
//            $res['g_info_id'] = '0';
//        }
//
//        $res['buy'] = $buy_num;
//        $res['surplus_num'] = $total_num - $buy_num;
//        $res['buy'] = $buy_num + $gameData->coupon_vir;
//
//        // 是否新用户专享
//        if ($gameData->for_new === 1 && $uid) {
//            $status = $this->_getPlayOrderInfoTable()->get(array('user_id' => $uid, 'order_status' => 1));
//            if ($status) {
//                $res['new_user'] = 1;
//            } else {
//                $res['new_user'] = 2;
//            }
//        } else {
//            $res['new_user'] = 2;
//        }
////关联的游玩地
//        $res['shop'] = array();
//        $shop_data = $this->_getPlayGameInfoTable()->getApiGameShopList(0, 100, array(), array('play_game_info.gid' => $id, 'play_game_info.status >= ?' => 1));
//        foreach ($shop_data as $sData) {
//            $res['shop'][] = array(
//                'shop_name' => $sData->shop_name,
//                'shop_id' => $sData->shop_id,
//                'circle' => $sData->circle,
//                'address' => $sData->shop_address,
//                'addr_x' => $sData->addr_x,
//                'addr_y' => $sData->addr_y,
//                'd' => sqrt(pow(abs($sData->addr_x - $addr_x),2) + pow(abs($sData->addr_y - $addr_y),2))//排序参考，不是真实距离
//            );
//        }
//
//        usort($res['shop'], function($a,$b){
//            if ($a['d'] === $b['d']) return 0;
//            return ($a['d'] < $b['d']) ? -1 : 1;
//        });
//        //$res['shop'] = count($res['shop']) ? array_slice($res['shop'], 0, 3) : array();
//
//        $res['shop_num'] = $this->_getPlayGameInfoTable()->getApiGameShopList(0, 0, array(), array('play_game_info.gid' => $id, 'play_game_info.status >= ?' => 1))->count();
//        return $this->jsonResponse($res);
//    }
//
//    /**
//     * 获取用户余额
//     * @param $uid
//     * @return float
//     */
//    public function balanceAction()
//    {
//        if (!$this->pass()) {
//            return $this->failRequest();
//        }
//        $uid = (int)$this->getParams('uid', 0);
//        $money = 0.00;
//        $data = $this->_getPlayAccountTable()->get(array('uid' => $uid));
//        if ($data) {
//            $money = $data->now_money;
//        }
//        $ye = array('ye'=>(string)((float)$money));
//        return $this->jsonResponse($ye);
//    }

}
