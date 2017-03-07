<?php

namespace Cmd\Controller;

use Deyi\BaseController;
use Deyi\GetCacheData\CouponCache;
use Deyi\Coupon\Coupon;
use Deyi\GetCacheData\PlaceCache;
use Deyi\GeTui\GeTui;
use Deyi\JsonResponse;
use Deyi\Account\OrganizerAccount;
use Deyi\OrderAction\OrderBack;
use Deyi\OrderAction\OrderExcerciseBack;
use Deyi\OrderAction\UseCode;
use Deyi\OutPut;
use Deyi\Request;
use Deyi\SendMessage;
use Deyi\Social\SendSocialMessage;
use Deyi\WeiXinFun;
use library\Fun\M;
use library\Service\Order\CancelOrder;
use library\Service\ServiceManager;
use library\Service\System\Cache\KeyNames;
use library\Service\System\Cache\RedCache;
use library\Service\System\Logger;

use library\Service\User\Account;
use Zend\Mvc\Controller\AbstractActionController;


class IndexController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;
    use OrderBack;
    use UseCode;

    //临时
    public function tmpAction()
    {
//        $Account=new Account();
//        $status = $Account->successful(29764, 27630, '4003342001201612051881812041', 'oMWgdwAwSv0yGWPR_382wNGcX9HI');
//
//        var_dump($status);die;
//        $db=M::getAdapter();
//
//        $res=$db->query("select play_game_code.*,play_user.phone from play_game_code LEFT JOIN play_user ON play_user.uid=play_game_code.code_uid WHERE  id>450 and code_order_id>0",array());
//
//        foreach ($res as $v){
//            echo $v->phone,"亲爱的小玩家，您购买的\"武汉极地海洋世界五周年特邀家庭年卡 2大1小家庭年卡\"1张已预约成功，您的辅助码为{$v->code}，请凭辅助码换取门票入园，如遇问题请联系玩翻天客服4008007221。立刻添加微信号：wft20160301，即可免费获取私人专享客服服务。";
//           SendMessage::Send($v->phone,"亲爱的小玩家，您购买的\"武汉极地海洋世界五周年特邀家庭年卡 2大1小家庭年卡\"1张已预约成功，您的辅助码为{$v->code}，请凭辅助码换取门票入园，如遇问题请联系玩翻天客服4008007221。立刻添加微信号：wft20160301，即可免费获取私人专享客服服务。");
//        }


    }

    //订单定时回收
    public function closeOrderAction()
    {
        while(True){
            try{
                $value = RedCache::lIndex(RedCache::get(KeyNames::CANCEL_ORDER_LIST), -1);
                if ($value) {
                    $timer = RedCache::get(RedCache::get(KeyNames::CANCEL_ORDER_ID));
                    if (!$timer || $timer < (time() - ServiceManager::getConfig('TRADE_CLOSED'))) { //兼容没去掉值


                        RedCache::rPop(RedCache::get(KeyNames::CANCEL_ORDER_LIST));
                        CancelOrder::CancelOrder($value);
                    }
                }

            }catch(\Exception $e){
                echo $e->getMessage()."\n";
            }
            sleep(1);
        }
    }

    //定时推送
    public function getuiAction()
    {
        $time = time();
        $start_time = strtotime(date("Y-m-d 00:00:00"));


        $am_time = $start_time + 3600 * 8;
        $pm_time = $start_time + 3600 * 21;

//判断时间是否大于早上8点 小于晚上9点
        if ($time < $am_time or $time > $pm_time) {
            exit;
        }

//数据库连接
        $pdo = M::getAdapter();

//查询符合条件的推送数据  +-5分钟
        $push_start_time = $time - 600;
        $push_end_time = $time + 600;
        $res = $pdo->query("SELECT play_push.* FROM play_push WHERE result=0 AND push_type=4 AND push_time>{$push_start_time} AND push_time<{$push_end_time} ORDER BY push_time ASC", [])->current();

        if (!$res) {
            echo '无数据';
            //Logger::writeLog('推送无数据' . date('Y-m-d H:i:s', $time));
            exit;
        }

        $pushData = $res;

        $count = $pdo->query("UPDATE play_push SET result=2 WHERE id ={$pushData->id} AND result=0", [])->count();
        if (!$count) {
            Logger::writeLog('更新失败' . date('Y-m-d H:i:s', $time));
            echo '更新失败';
            exit;
        }

        $content = array(
            'title' => htmlspecialchars_decode($pushData->title, ENT_QUOTES),
            'info'  => htmlspecialchars_decode($pushData->info, ENT_QUOTES),
            'type'  => (int)$pushData->link_type,
            'id'    => $pushData->link_id,
            'time'  => $time,
            'url'   => $pushData->url
        );

//推送
        $geTui = new GeTui();
        $res = $geTui->pushMessageToApp(htmlspecialchars_decode($pushData->info, ENT_QUOTES), json_encode($content, JSON_UNESCAPED_UNICODE), date('Y_m_d_H', $time), 43200000, $pushData->city);

        //已经 异步
//        if ($res['result'] === 'ok') {
//
//            if (isset($res['contentId'])) {
//                $log = $res['contentId'];
//            } else {
//                $log = '已变化';
//            }
//
//            $pdo->query("UPDATE play_push SET result=1, log='{$log}' WHERE id={$pushData->id} AND result=2", [])->count();
//        }

        $pdo->query("UPDATE play_push SET result=1, log='{异步}' WHERE id={$pushData->id} AND result=2", [])->count();

        exit;
    }

    //用户账户监控 流水与 金额 不一致
    public function checkAccountAction()
    {
        $sql = "SELECT
	SUM(
		IF (
			play_account_log.action_type = 1,
			play_account_log.flow_money,
			- play_account_log.flow_money
		)
	) AS log_money,
	play_account.now_money,
	play_account.uid
FROM
	play_account
LEFT JOIN play_account_log ON play_account_log.uid = play_account.uid
WHERE
	play_account_log.status = 1
GROUP BY
	play_account.uid
HAVING
	log_money != now_money";

        $result = M::getAdapter()->query($sql, array());
        $log = '';
        if ($result->count()) {
            foreach ($result as $value) {
                $log = $log. " 用户id:". $value['uid']. " 记录的流水钱：". $value['log_money']. " 账户的钱：". $value['now_money']. "\r\n";
            }
        }

        if ($log) {
            Logger::WriteErrorLog($log."\r\n");
        }

    }

    //根据手机号 更新用户所属城市  目前只处理南京
    public function updateUserCityAction()
    {

        $db = $this->_getAdapter();
        //$res = $db->query("select uid,phone,city from play_user WHERE phone>0 and uid<?  order BY  uid DESC ", array(194075));


        $s_time = strtotime(date('Y-m-d 00:00:00'));
        //今天所有用户
        $res = $db->query("select uid,phone,city from play_user WHERE phone>0 and dateline >?  order BY  uid DESC ", array($s_time));


        foreach ($res as $v) {
            echo "处理数据:{$v->uid} \n";
            sleep(0.2);
            $ch = curl_init();
            $url = 'http://apis.baidu.com/showapi_open_bus/mobile/find?num=' . $v->phone;
            $header = array(
                'apikey: 1c6b3fbd2dfc45aeb5c0b80c4cf4c7f0',
            );
            // 添加apikey到header
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

            // 执行HTTP请求
            curl_setopt($ch, CURLOPT_URL, $url);
            $r = curl_exec($ch);

            $p = json_decode($r)->showapi_res_body->prov;


            if ($p) {

                if ($p == '江苏' and $v->city == 'WH') {
                    $db->query("update play_user set  city = 'NJ' WHERE uid=? ", array($v->uid));
                    var_dump($p, $v->phone);  //湖北 江苏
                }

            } else {
                echo "错误 {$v->uid} {$v->phone}\n";
                var_dump(json_decode($r));
//                exit;
            }
        }


    }

    //统计本周售出数
    public function soldNumberAction()
    {
        //查询出所有在售商品
        $db = $this->_getAdapter();
        $s_t = time() - 3600 * 24 * 7; //开始时间
        $c_list = $db->query('select id from play_organizer_game WHERE `status`>0  limit 10000', array());
        foreach ($c_list as $v) {
            //统计每个商品一周售出数量
            $count = $db->query("select count(*) as c from play_order_info WHERE order_type = 2 AND coupon_id={$v->id} AND order_status>0 AND pay_status NOT IN (0,3,4) AND `dateline`>{$s_t}", array())->current()->c;
            $this->_getPlayOrganizerGameTable()->update(array('hot_number' => (int)$count), array('id' => $v->id));
        }
        exit('更新成功');

    }

    //根据套系反向更新商品
    public function updateGoodsAction()
    {
        $db = $this->_getAdapter();
        $allgoods = $db->query("select id from play_organizer_game WHERE status = 1 and is_together = 1 ", array())->toArray();
        foreach ($allgoods as $gd) {


            // 如果有套系 变 如果没有 则 更新价格
            $gameInfoData = $this->_getPlayGameInfoTable()->fetchLimit(0, 150, array('status', 'shop_circle', 'total_num', 'buy'), array('gid' => (int)$gd['id']));

            $ticket_num = 0;
            $buy_num = 0;
            $shop_address = [];
            foreach ($gameInfoData as $infoData) {
                if ($infoData->status > 0) {
                    $shop_address[] = $infoData->shop_circle;
                }
                $ticket_num += $infoData->total_num;
                $buy_num += $infoData->buy;
            }

            $shop_circle = array_unique($shop_address);

            if (count($shop_circle) > 1) {
                $shop_addr = '多个商圈';
            } else {
                $shop_addr = $shop_circle[0];
            }

            $this->_getPlayOrganizerGameTable()->update(array(
                'ticket_num' => $ticket_num,
                'shop_addr' => $shop_addr,
                'buy_num' => $buy_num,
            ), array('id' => (int)$gd['id']));
        }
    }

    private function updateComment()
    {
        //$db=$this->_getAdapter();
        //$allgoods = $db->query("select coupon_id from play_order_info WHERE status = 1 and pay_status = 5 and order_type = 3 ",array())->toArray();
    }

    //更新咨询数量
    public function updateConsultAction()
    {
        $db = $this->_getAdapter();
        $allgoods = $db->query("select id from play_organizer_game WHERE status = 1", array())->toArray();
        foreach ($allgoods as $gd) {
            $consult_num = $this->_getMdbConsultPost()->find(array(
                'status' => array('$gte' => 1),
                'type' => array('$ne' => 7),
                'object_data.object_id' => (int)$gd['id']
            ))->count();
            $this->_getPlayOrganizerGameTable()->update(array('consult_num' => $consult_num), array('id' => (int)$gd['id']));
        }

        $allbases = $db->query("select id from play_excercise_base ", array())->toArray();
        foreach ($allbases as $gd) {
            $consult_num = $this->_getMdbConsultPost()->find(array(
                'status' => array('$gte' => 1),
                'type' => 7,
                'object_data.object_bid' => (int)$gd['id']
            ))->count();
            $this->_getPlayExcerciseBaseTable()->update(array('query_number' => $consult_num), array('id' => (int)$gd['id']));
        }

        $this->_getMdbConsultPost()->update(array('type' => array('$ne' => 7)), array('$set' => array("type" => 1)));
    }

    //更新前一天的订单 计算均单值 更新用户分析表
    public function updateAnalysisAction()
    {
         $real_time = time() - 1186400;
         $sql = "SELECT user_id FROM play_order_info WHERE pay_status >= 2 AND order_status =1 AND dateline > ? GROUP BY user_id";
         $data = M::getAdapter()->query($sql, array($real_time));


        foreach ($data as $value) {

            $max_time = 0;
            $total_coupon_data = M::getAdapter()->query("SELECT SUM(real_pay) AS coupon_real_pay, SUM(account_money) AS coupon_account_money, COUNT(*) AS count_coupon_number, MAX(play_order_info.dateline) AS dateline FROM play_order_info WHERE pay_status >= 2 AND order_status =1 AND order_type = 1 AND user_id = ?", array($value->user_id))->current();
            $total_goods_data = M::getAdapter()->query("SELECT SUM(real_pay) AS goods_real_pay, SUM(account_money) AS goods_account_money, COUNT(*) AS count_goods_number, MAX(play_order_info.dateline) AS dateline FROM play_order_info WHERE pay_status >= 2 AND order_status =1 AND order_type = 2 AND user_id = ?", array($value->user_id))->current();
            $total_activity_data = M::getAdapter()->query("SELECT SUM(real_pay) AS activity_real_pay, SUM(account_money) AS activity_account_money, COUNT(*) AS count_activity_number, MAX(play_order_info.dateline) AS dateline FROM play_order_info WHERE pay_status >= 2 AND order_status =1 AND order_type = 3 AND user_id = ?", array($value->user_id))->current();

            if ($total_coupon_data) {
                $total_coupon_money = bcadd($total_coupon_data->coupon_real_pay, $total_coupon_data->coupon_account_money, 2);
                $total_coupon_money = $total_coupon_money > 0 ? $total_coupon_money : 0;
                $coupon_count_num = $total_coupon_data->count_coupon_number;
                $max_time = $total_coupon_data->dateline > $max_time ? $total_coupon_data->dateline : $max_time;
            } else {
                $total_coupon_money = 0;
                $coupon_count_num = 0;
            }

            if ($total_goods_data) {
                $total_goods_money = bcadd($total_goods_data->goods_real_pay, $total_goods_data->goods_account_money, 2);
                $total_goods_money = $total_goods_money > 0 ? $total_goods_money : 0;
                $goods_count_num = $total_goods_data->count_goods_number;
                $max_time = $total_goods_data->dateline > $max_time ? $total_goods_data->dateline : $max_time;
            } else {
                $total_goods_money = 0;
                $goods_count_num = 0;
            }

            if ($total_activity_data) {
                $total_activity_money = bcadd($total_activity_data->activity_real_pay, $total_activity_data->activity_account_money, 2);
                $total_activity_money = $total_activity_money > 0 ? $total_activity_money : 0;
                $activity_count_num = $total_activity_data->count_activity_number;
                $max_time = $total_activity_data->dateline > $max_time ? $total_activity_data->dateline : $max_time;
            } else {
                $total_activity_money = 0;
                $activity_count_num = 0;
            }

            $total_money = $total_coupon_money + $total_goods_money + $total_activity_money;
            $total_count = $coupon_count_num + $goods_count_num + $activity_count_num;

            if ($total_money && $total_count) {
                $average_money = bcdiv($total_money, $total_count, 2);
            } else {
                $average_money = 0;
            }

            $flag = M::getPlayUserAttachedTable()->get(array('user_attached_uid' => $value->user_id));
            $resData = array(
                'user_attached_average_value'=> $average_money,
                'user_attached_total_money' => $total_money,
                'user_attached_coupon_buy'=> $coupon_count_num,
                'user_attached_goods_buy' => $goods_count_num,
                'user_attached_activity_buy'=> $activity_count_num,
                'user_attached_coupon_money' => $total_coupon_money,
                'user_attached_goods_money'=> $total_goods_money,
                'user_attached_activity_money' => $total_activity_money,
                'user_attached_dateline' => $max_time,
            );

            if ($flag) {
                echo '更新';
                M::getPlayUserAttachedTable()->update($resData, array('user_attached_uid' => $value->user_id));
            } else {
                echo '插入';
                $resData['user_attached_uid'] = $value->user_id;
                M::getPlayUserAttachedTable()->insert($resData);
            }
        }

        echo 'end';
        exit;

    }

    //结算
    public function autoBalanceAction()
    {

        //获取符合条件的code
        $sql = 'SELECT
	play_organizer_code_log.id
FROM
	play_organizer_code_log
INNER JOIN play_order_info ON play_order_info.order_sn = play_organizer_code_log.order_sn
INNER JOIN play_game_info ON play_game_info.id = play_order_info.bid
INNER JOIN play_contract_link_price ON play_contract_link_price.id = play_game_info.contract_price_id
WHERE
    play_contract_link_price.status = 3
AND play_order_info.approve_status = 2
AND play_organizer_code_log.transport_status = ?';

        $adapter = $this->_getAdapter();
        $allowData = $adapter->query($sql, array(1));

        $organizerAccount = new OrganizerAccount();
        foreach ($allowData as $allow) {
            $result = $organizerAccount->transport($allow['id']);
            //todo 做记录
            var_dump($result);
        }

        //todo 同时将包销合同里面的code 设为 已结算的状态
        $sql_inventory = "SELECT
	play_inventory_log.code_id
FROM
	play_inventory_log
INNER JOIN play_coupon_code ON play_coupon_code.id = play_inventory_log.code_id
INNER JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
INNER JOIN play_game_info ON play_game_info.id = play_order_info.bid
INNER JOIN play_contract_link_price ON play_contract_link_price.id = play_game_info.contract_price_id
WHERE
	play_inventory_log.code_id > 0
AND play_inventory_log.types = 2
AND play_order_info.approve_status = 2
AND play_coupon_code.test_status = 0
AND play_contract_link_price.status = 3";

        $checkData = $adapter->query($sql_inventory, array());

        foreach ($checkData as $check) {
            $this->_getPlayCouponCodeTable()->update(array('test_status' => 5, 'account_time' => time()), array('id' => $check['code_id']));
        }
        exit;


    }

    //自动退款
    public function autoRefundAction()
    {
        //受理退款后 多长时间 退款
        $start_time = time() - 1800;

        $this->autoRefundGoods($start_time);
        $this->autoRefundActivity($start_time);

        echo 'end';

        exit;
    }

    //确定退款商品
    private function autoRefundGoods($start_time)
    {
        $adapter = $this->_getAdapter();

        $sql = 'SELECT play_coupon_code.id, play_order_info.account_type,play_order_info.dateline FROM
	play_coupon_code
INNER JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
WHERE
	play_coupon_code.force = 2
AND play_order_info.approve_status = 2
AND play_order_info.pay_status >= 2
AND play_order_info.order_status = 1
AND play_order_info.order_type = 2 AND accept_time <= ?';

        $allowDataList = $adapter->query($sql, array($start_time));
        $number = $allowDataList->count();

        if (!$number) {
            echo '无';
            return false;
        }

        foreach ($allowDataList as $allowData) {

            if (RedCache::get(KeyNames::REFUND_CODE. 'account_type'. $allowData->account_type)) {
                continue;
            }

            if (!in_array($allowData->account_type, array('weixin', 'union', 'alipay', 'account', 'new_jsapi'))) {
                continue;
            }
            //todo 同时订单的数据 判断是否符合退款条件  就是时间  支付宝 3个月 微信1年 银联1年   可以做通知
            if ($allowData->account_type == 'union' && ($allowData->dateline <  time()-31536000 || $allowData->dateline < 1455379200)) {
                continue;
            } elseif ($allowData->account_type == 'alipay' && $allowData->dateline <  time()-7776000) {
                continue;
            } elseif (($allowData->account_type == 'weixin' || $allowData->account_type == 'new_jsapi') && $allowData->dateline <  time()-31536000) {
                continue;
            }

            $res = $this->refundMoney($allowData->id);

            if ($res['status']) {
                echo '成功';
            } else {
                RedCache::set(KeyNames::REFUND_CODE. 'account_type'. $allowData->account_type, 1, KeyNames::REFUND_CODE_TTL);
                Logger::WriteErrorLog($allowData->account_type. "退款失败 ". "\n");
                echo '失败';
            }
        }

        echo '商品 end';

    }


    //确定退款活动
    private function autoRefundActivity($start_time)
    {
        $adapter = $this->_getAdapter();

        $sql = 'SELECT play_excercise_code.id, play_order_info.account_type,play_order_info.dateline FROM
	play_excercise_code
INNER JOIN play_order_info ON play_order_info.order_sn = play_excercise_code.order_sn
WHERE
	play_excercise_code.accept_status = 2
AND play_order_info.approve_status = 2
AND play_order_info.pay_status >= 2
AND play_order_info.order_status = 1
AND play_order_info.order_type = 3 AND accept_time <= ?';

        $allowDataList = $adapter->query($sql, array($start_time));
        $number = $allowDataList->count();

        if (!$number) {
            echo '无';
            return false;
        }

        $orderBack = new OrderExcerciseBack();

        foreach ($allowDataList as $allowData) {

            if (RedCache::get(KeyNames::REFUND_CODE. 'account_type'. $allowData->account_type)) {
                continue;
            }

            if (!in_array($allowData->account_type, array('weixin', 'union', 'alipay', 'account', 'new_jsapi'))) {
                continue;
            }
            //todo 同时订单的数据 判断是否符合退款条件  就是时间  支付宝 3个月 微信1年 银联1年
            if ($allowData->account_type == 'union' && ($allowData->dateline <  time()-31536000 || $allowData->dateline < 1455379200)) {
                continue;
            } elseif ($allowData->account_type == 'alipay' && $allowData->dateline <  time()-7776000) {
                continue;
            } elseif (($allowData->account_type == 'weixin' || $allowData->account_type == 'new_jsapi') && $allowData->dateline <  time()-31536000) {
                continue;
            }
            $res = $orderBack->refundMoney($allowData->id);

            if ($res['status']) {
                echo '成功';
            } else {
                RedCache::set(KeyNames::REFUND_CODE. 'account_type'. $allowData->account_type, 1, KeyNames::REFUND_CODE_TTL);
                Logger::WriteErrorLog($allowData->account_type. "退款失败 ". "\n");
                echo '失败';
            }
        }

        echo '活动end';
        exit;
    }



    //更新活动相关数据
    public function updateActivityAction()
    {

        $db = $this->_getAdapter();
        $res = $this->_getPlayExcerciseBaseTable()->fetchAll();


        foreach ($res as $v) {
            $bid = $v->id;
            $st = $this->_getPlayExcerciseEventTable()->fetchLimit(0, 1, [], ['bid' => $bid, 'sell_status > ?' => 0, 'customize' => 0], ['start_time' => 'asc'])->current();
            $et = $this->_getPlayExcerciseEventTable()->fetchLimit(0, 1, [], ['bid' => $bid, 'sell_status > ?' => 0, 'customize' => 0], ['end_time' => 'desc'])->current();
            $it = $this->_getPlayExcerciseEventTable()->fetchLimit(0, 1, [], ['bid' => $bid, 'sell_status > ?' => 0, 'customize' => 0], ['id' => 'desc'])->current();
            $count = $this->_getPlayExcerciseEventTable()->fetchCount(['bid' => $bid, 'sell_status > ?' => -1, 'customize' => 0]);
            $shop = $this->_getPlayShopTable()->get(['shop_id' => $it->shop_id]);
            $data['max_start_time'] = $st->start_time;
            $data['min_end_time'] = $et->end_time;
            $data['circle'] = CouponCache::getBusniessCircle($shop->busniess_circle);
            $data['all_number'] = $count;
            $status = $this->_getPlayExcerciseBaseTable()->update($data, ['id' => $bid]);

            //更新最低价
            $s = $db->query("
        UPDATE play_excercise_base
SET low_price = (
	SELECT
		MIN(play_excercise_price.price)
	FROM
		play_excercise_price
	WHERE
	 play_excercise_price.is_close = 0
	AND play_excercise_price.is_other = 0
	AND play_excercise_price.bid=play_excercise_base.id
)
WHERE  play_excercise_base.id=?
", array($bid));

        }


        exit('完成');
    }

    //搜索数据更新
    public function solrAction()
    {

        $db = $this->_getAdapter();
        $db->query("truncate table search_tmp;", array());
        sleep(2);
        $i = 0;
        while (1) {
            $data = '';
            $res_shop = $db->query("select * from play_shop WHERE shop_status=0 limit {$i},10", array())->toArray();

            if (count($res_shop) == 0) {
                break;
            }
            $i += 10;
            foreach ($res_shop as $s) {
                $shop_data = array(
                    'id' => (int)$s['shop_id'],
                    'cover' => $this->_getConfig()['url'] . $s['thumbnails'],
                    'circle' => CouponCache::getBusniessCircle($s['busniess_circle']),
                    'title' => $s['shop_name'],
                    'editor_word' => $s['editor_word'],
                    'addr_x' => (float)$s['addr_x'],
                    'addr_y' => (float)$s['addr_y'],
                    'city' => $s['shop_city'],
                    'key' => $s['shop_name'],
                    'dateline' => $s['dateline']
                );


                //游玩地下面的活动
                $game_info = $db->query("SELECT
	play_excercise_event.*, play_excercise_base.*, (
		SELECT
			COUNT(*)
		FROM
			play_excercise_event
		WHERE
			play_excercise_event.sell_status > 0
		AND play_excercise_event.join_number < perfect_number
		AND play_excercise_event.sell_status != 3
		AND play_excercise_event.bid = play_excercise_base.id
		AND play_excercise_event.over_time > UNIX_TIMESTAMP()
		AND play_excercise_event.open_time < UNIX_TIMESTAMP()
		
	) AS buy_ing,
	(
		SELECT
			COUNT(*)
		FROM
			play_excercise_event
		WHERE
			play_excercise_event.sell_status > 0
		AND play_excercise_event.join_number < perfect_number
		AND play_excercise_event.sell_status != 3
		AND play_excercise_event.bid = play_excercise_base.id
		AND play_excercise_event.open_time > UNIX_TIMESTAMP()
	) AS not_start
FROM
	play_excercise_event
LEFT JOIN play_excercise_shop ON play_excercise_shop.eid = play_excercise_event.id
LEFT JOIN play_excercise_base ON play_excercise_base.id = play_excercise_event.bid
WHERE
 play_excercise_event.customize = 0
AND play_excercise_event.sell_status >= 1
AND play_excercise_event.sell_status != 3
AND play_excercise_event.over_time > UNIX_TIMESTAMP()
AND play_excercise_shop.is_close = 0
AND play_excercise_shop.shopid =?
AND play_excercise_base.release_status = 1
GROUP BY
	play_excercise_event.bid
ORDER BY
	play_excercise_event.add_dateline DESC
", array($s['shop_id']));


                $activity_id_list = array();
                foreach ($game_info as $g_data) {

                    //活动动只要有在售场次，都保持可以售卖的状态

                    $shop_data['key'] .= $g_data['name'];
                    $surplus = $g_data['perfect_number'] - $g_data['join_number'];

                    if ($g_data['buy_ing'] > 0) {
                        $status = 0;
                    } else {
                        if ($g_data['not_start'] > 0) {
                            $status = 1;
                        } else {
                            $status = 2;
                        }
                    }
                    $activity_id_list[] = array(
                        'id' => (int)$g_data['bid'],
                        'ticket_name' => $g_data['name'],
                        'price' => $g_data['low_price'] ?: '0',
                        'surplus' => $surplus < 0 ? 0 : $surplus,
                        'coupon_have' => 1,
                        'g_buy' => 0,
                        'type' => 2, //1普通商品 2活动
                        'status' => $status // 售卖状态  0正在售卖 1未开始 2已售罄
                    );


                }

                //游玩地下面的商品
                $game_id_list = array();
                $game_info = $db->query("
SELECT
	*, (
		SELECT
			count(*)
		FROM
			play_game_info
		WHERE
			play_game_info.gid = play_organizer_game.id
		AND play_game_info.buy < play_game_info.total_num
		AND play_game_info.up_time < UNIX_TIMESTAMP()
		AND play_game_info.down_time > UNIX_TIMESTAMP()
		AND play_game_info.status = 1
	) AS buy_ing,
	(
		SELECT
			count(*)
		FROM
			play_game_info
		WHERE
			play_game_info.gid = play_organizer_game.id
		AND play_game_info.buy < play_game_info.total_num
		AND play_game_info.up_time > UNIX_TIMESTAMP()
		AND play_game_info.status = 1
	) AS not_start
FROM
	play_organizer_game
LEFT JOIN play_game_info ON play_organizer_game.id = play_game_info.gid
WHERE
	play_organizer_game.status = 1
AND play_organizer_game.start_time < UNIX_TIMESTAMP()
AND play_organizer_game.end_time > UNIX_TIMESTAMP()

AND play_game_info.shop_id = ?
GROUP BY  play_organizer_game.id
ORDER BY
	play_organizer_game.id DESC
", array($s['shop_id']));

                foreach ($game_info as $g_data) {

                    if ($g_data['buy_ing'] > 0) {
                        $status = 0;
                    } else {
                        if ($g_data['not_start'] > 0) {
                            $status = 1;
                        } else {
                            $status = 2;
                        }
                    }

                    $shop_data['key'] .= $g_data['title'];
                    $game_id_list[] = array(
                        'id' => (int)$g_data['gid'],
                        'ticket_name' => $g_data['title'],
                        'price' => $g_data['low_price'] ?: '0',
                        'surplus' => (int)($g_data['ticket_num'] - $g_data['buy_num']),
                        'coupon_have' => (($g_data['ticket_num'] > $g_data['buy_num']) && $g_data['foot_time'] > time() && $g_data['is_together'] === 1) ? 1 : 0,
                        'g_buy' => (($g_data['down_time'] - time()) > 86400) ? $g_data['g_buy'] : 0,
                        'type' => 1, //1普通商品 2活动
                        'status' => $status,//   售卖状态  0正在售卖 1未开始 2已售罄
                    );
                }


                $shop_data['ticket_list'] = json_encode(array_merge($activity_id_list, $game_id_list), JSON_UNESCAPED_UNICODE);
                $shop_data['ticket_num'] = count($game_id_list);

                $data .= "({$shop_data['id']},'{$shop_data['cover']}','{$shop_data['circle']}','{$shop_data['title']}','{$shop_data['editor_word']}',{$shop_data['addr_x']},{$shop_data['addr_y']},'{$shop_data['city']}','{$shop_data['key']}','{$shop_data['ticket_list']}',{$shop_data['ticket_num']},{$shop_data['dateline']}),";
            }
            $data = substr($data, 0, -1);
            $count = $db->query('INSERT INTO search_tmp (id,cover,`circle`,title,editor_word,addr_x,addr_y,city,`key`,ticket_list,ticket_num,dateline) VALUES ' . $data, array())->count();
            if (count($res_shop) < 10) {
                break;
            }
        }
        echo '完成';


    }


    //保存到文件
    private function out($file_name, $head, $content)
    {
        file_put_contents($file_name, '');
        // 打开PHP文件句柄，php://output 表示直接输出到浏览器
        $fp = fopen($file_name, "w");
        //转码 否则 excel 会乱码
        foreach ($head as $i => $row) {
            $head[$i] = mb_convert_encoding($row, 'CP936', 'UTF-8');
        }

        fputcsv($fp, $head);

        foreach ($content as $tent) {
            $outData = array();
            foreach ($tent as $k => $v) {
                array_push($outData, mb_convert_encoding($v, 'CP936', 'UTF-8'));
            }
            fputcsv($fp, $outData);
        }
        return true;
    }

    //导出只购买过一单的用户
    public function outAction()
    {
        $offset = 0;
        $data = array();
        while (true) {
            //查询所有用户订单数
            $db = $this->_getAdapter();
            $res = $db->query("select uid,username,phone,login_type,dateline from play_user ORDER  BY  uid ASC limit {$offset},10000", array());

            foreach ($res as $v) {
                echo "处理 {$v->uid}\r\n";
                $u_order = $db->query("
SELECT
	play_order_info.coupon_name,play_order_info.shop_name,play_order_info_game.type_name,real_pay,play_order_info.dateline
FROM
	play_order_info
LEFT JOIN play_order_info_game ON play_order_info_game.order_sn=play_order_info.order_sn

WHERE
	order_status = 1
AND pay_status > 1
AND user_id =?
limit 2
", array($v->uid))->toArray();

                if (count($u_order) == 1) {
                    //只有一个订单
                    $data[] = array(
                        'uid' => $v->uid,
                        'username' => $v->username,
                        'phone' => $v->phone,
                        'login_type' => $v->login_type,
                        'reg_dateline' => date('Y-m-d H:i:s', $v->dateline),
                        'coupon_name' => $u_order[0]['coupon_name'],
                        'shop_name' => $u_order[0]['shop_name'],
                        'type_name' => $u_order[0]['type_name'],
                        'real_pay' => $u_order[0]['real_pay'],
                        'order_dateline' => date('Y-m-d H:i:s', $u_order[0]['dateline'])
                    );
                }
            }

            if ($res->count() != 10000) {
                echo "完成";
                break;
            } else {
                $offset = $offset + 10000;
            }
            sleep(1);
        }
        //导出
        $head = array();
        foreach ($data[0] as $k => $v) {
            $head[] = $k;
        }
        $file_name = '/root/' . date('Y-m-d H:i:s', time()) . "_123_" . '.csv';
        $this->out($file_name, $head, $data);


    }

    //发券
    public function cashAction()
    {

        $data = "15994225894,15807155722,13507157656,18607156112,18071066511,18827387783,13807155277,13476047533,13886087383,15527159627,18627839066,15327256017,15902715689,13135693591,15392882958,13487086208,15671593050,13886090340,13072777016,13397198022,13317152113,13995513833,13971008298,13437262643,13476791262,13707109799,18064050100,13995561967,15868856533,18062669810,18607153975,13100710527,13971370437,13971515753,18062088860,13277070171,15327110301,13971095627,18986114311,18971567356,13971440063,15327309011,13667191140,17764081429,13545002743,15926402076,15071283169,18674051557,15927075252,15327270367,15327144003,13317167478,13657200231,13886193996,13006394736,13986067222,15527011604,15327338382,13871127688,15527569884,13387583726,15071462300,18995501005,15327341128,13476161480,13100712653,13871069138,15827051025,15926227475,13995511794,15327125651,18971629891,18963999169,18086685825,13554400204,18995578557,18907115288,18008631929,18062623939,15342274956,18674025819,13212779067,18963959357,13618656355,13027167323,18062529131,15871355598,18995598013,13476115360,13554467760,15926380015,15807154589,13657253311,15337169721,18571726858,13986208328,13297081135,13237185432,13995609031,13871179076,13349849345,15871432460,13995546276,13407180010,15629080189,13871310299,15972078432,13437276938,18007125520,13986137602,15994277976,18062501299,15271826216,13971133468,13437264588,13407139737,18971161015,15071476090,13657234606,13476270499,13707158914,18986068112,13720359703,18674050189,13026305888,18086048982,15391558298,13377882539,15927178867,13618653843,13808682737,15907190285,18971003735,13006385461,15392912465,18086005462,13554320522,15972087222,13260533585,15342200929,15827121616,18971557685,15927148001,18971457366,13407199018,13707195152,13517291566,18171056739,15623109032,15377542466,15972108686,15327169415,15807171492,13476165989,15342281105,15327187530,15337175032,13886088250,15827357393,13237189078,18627750669,13871065661,13628687796,13037129470,13554416270,13207133465,13871210391,13296691088,18971580720,18120558839,13071267111,13995524767,18062615505,18971340188,13995595605,15107139619,15871391239,13437132629,18986110471,15907177179,13886091179,13627286270,18086687615,13995616312,13607111397,13871522015,13657202683,18717137328,18086609669,18602746369,15102784876,13545077228,15926431534,18607194760,13260515565,15392897088,13317187937,18086022965,13971296083,13476279918,18571641818,18571720086,13476865918,13886004026,13554394073,15307114690,13720102794,15308621217,18971225382,15307100230,18971201613,18627009602,13971104478,13286163667,18672312458,13377862336,18086611569,13971157177,15377010935,13517280617,13387580550,18971504535,15827550658,13986144360,18672779612,15571573107,15871819167,13995611540,18071060605,13517218262,13971038858,13986175188,15527632634,13871215199,18062776557,18062785969,13437262643,15827248748,18086023659,13667177357,13545076921,15927400328,13607151015,13037121629,15327127315,13385289568,15827016071,18971089200,13986239598,13407151140,13971098109,13407120358,18571759799,18171234078,18086445465,18672370130,13329707203,13307128442,13971390571,18507175088,13871209395,18602703508,13385281833,18607160195,18108622009,13507142735,13971020265,13886126095,13871045787,13871267794,18108625739,13986254188,13907168801,18971314147,15927362298,13517199742,13135665648,13971148738,18995616389,13476277079,13995617209,13871593186,13871438032,15342269802,18040589778,18971356273,15342331163,18627088577,15926209831,18627792648,18062681690,15327240155,13437181408,15827390054,13707164531,15871783868,13986244399,18602756622,13317193561,18086019499,15827089883,18986256762,13296536121,18986103283,18971205918,13995611002,18007152907,18672973036,18971459358,18062568480,13995561540,15926308724,18302706732,18086082167,18627118292,18163527169,13554218686,18607146200,15327142450,13517198879,18995636380,18627965048,13986051235,13995687154,15377603710,18802773569,15927289838,13237107166,13971635391,13477070527,13797070236,18108658368,15997484234,18086608410,15926448527,18162573399,13902315150,13297937501,15827000533,18502751818,18963951818,15171454576,13477069728,18062635488,13026103550,13871417045,13971007529,15927314463,13659877520,15927521181,18672306655,18071066008,15342355866,13554073035,13207128844,13986168585,13886186320,13554111120,13720254767,13986095290,18062606717,18907177900,15007100621,15071476549,18907194929,18627111304,13545112474,15071278851,18071749817,13469991128,18986100077,15807163990,13554456994,13697349456,18571565509,13098828709,15926480387,15527011604,15172487223,18971024900,15827238242,13545396167,13923704780,13349948225,13871189267,13517283539,13545132009,18086471900,13294154150,15669818744,18607136933,13971250637,13707118760,13807119966,13995560652,13971393300,18627858319,13006113980,13971215214,13807140044,15902779009,13349952561,13886159430,13349947225,15071159110,18062768066,15392958768,18696161621,18971134077,18717120776,15327369206,13886082396,15827236628,18108659639,13971208834,13072724826,18986171675,18627739776,13638600086,13554436515,15902792738,13297066726,15337134291,13476186624,13477027641,18627739246,13871001937,13667262579,13871367667,18627004628,13886168177,15527011604,13808663943,13871170411,13971328336,18971670826,13476291433,13886035334,18502776383,13265765527,13476121732,13871561056,18627180075,18995627175,13971360988,15802799477,18602706100,13971398285,15308631522,13871022352,15072444406,13995699378,18717122687,18907177900,13971055575,18071038750,13517286567,15827390054,13971544744,15927092968,18971645212,15997494927,13667282814,15927318103,13517257162,13469988101,15327128585,18086031925,13016452024,15377036321,15927140021,13720355221,15997450230,13429878604,15342204949,13317122512,18971460795,13487080017,13971697773,13971325887,18627138733,18071073605,13971464417,18627826713,13027150797,18627096259,13995655280,13886033316,13720206714,15337277757,13006186035,18120230982,15527773320,13297033138,13545038982,18696161351,18971215515,13986291112,18971215506,18571566907,18171117520,18971589042,15377089075,18071053833,13871099669,13659889657,13697350588,15827243302,13419563090,13707139287,18062138850,18007173852,13407138776,15927178133,18907198900,18602728076,13026164708,13297973372,13476114965,13627107069,15926413380,13638659719,15926450080,18971681231,18007137683,13437193326,13517121461,18971351788,15342289128,18971213059,13554098205,15327259727,18164051187,18907119863,18907119803,15927227858,13871479699,18071740127,18986007562,15827057575,15927269899,13297997919,13545084725,13871288698,18507259880,18062075217,13212709439,13607118566,18696104360,13487088826,13871295005,18675557124,15623900125,13476272224,13018015612,13469951362,13016452024,15972204675,15827224545,18107275957,15827656115,18971650581,13476253582,18186511884,13657243830,18627894951,15902735922,13871156931,13476250452,18062088061,13971163949,13986252560,18571701852,18717133465,13397185816,15972994878,13006185388,13419666606,18062414466,13971561842,13971359980,13886187070,15972203616,13554201622,13507166339,13517293321,15342267261,18627942799,13971163058,18607102805,18086485303,18602738487,18963966406,13037191333,13995641333,18627122076,15972980128,13971001126,15072366249,13995588216,13476247752,13971482630,15623991370,13638621621,18108674561,15926275606,13437128078,13277058351,15827566467,17786041981,13006374868,15308651847,13797090186,13100697815,18971252669,13886003100,13986081230,13986296288,13986021218,13971506686,13638690494,15902710041,15827437818,13986151799,18062687755,15207160556,13871511080,17702766697,13006387173,13916892008,13469966277,18986165848,13871165949,18971129049,13554070834,13638649121,15002732637,15347104061,13871360467,13871418117,13986044776,18062666188,18674154706,18607107766,13163244673,15342254125,18627831399,13349959323,15337190271,13317144411,18986226175,13628670050,13419556802,13871552305,15927157624,18071508857,15327128770,13476062400,15337292176,18674021212,13476064533,13871485258,13476135330,15807166033,18171508016,15607158865,18986285180,18607199220,13476029429,18971113236,15327312909,15207130056,13971082587,13098802382,18607181612,15172510625,13487090763,15527291765,13886020624,18971442708,13554343034,18907195371,13871297721,13971256905,18696171296,13554142042,13507195749,13886109920,13476152265,15387129960,13135658888,18602715583,18071051802,15972188350,18627728341,18627115926,13986066737,13407106205,18171316868,13469976207,13971444031,13235558811,18971388020,13886175730,13607121045,18995533623,13886057917,13886085865,13971339197,18672750166,18971471012,13657299369,18971543929,13995666265,13995555130,13667140557,18702732757,17702709109,15392960106,18702721042,18907191370,15377678762,15902765432,18696183899,13971258948,13387533456,18086665359,13377882539,13387591665,18171280427,18986013680,15392835376,13667268167,13476090555,18627987162,15997418732,13037194707,18942943397,18672955790,15926308362,13720282274,15002766829,13871282637,15872376006,13971027446,15527829968,15671612517,15327102000,15007159005,13397126561,13545896068,13720303616,13995506205,13995511753,18062698628,15937640613,13971195488,13507165249,13971395823,18062131880,18627732577,13995600590,15927606519,13986296288,15072393113,13507111075,13707141100,18627894420,13871579788,18007178510,15926434836,18627080093,13437258966,18672967171,18986018119,18062409835,18627768122,13886162691,13100649328,13886047790,13995636021,13476266065,18627120509,13407173620,13545350935,18171173319,13627146808,18627753500,18502779839,13907188121,15327209523,15927408164,18907120776,18672332513,13908632117,18207148930,13080681186,15972045207,13006189899,18694066917,13808648982,15623018163,13667297241,13607199668,13437284691,18971096919,18571532686,17786470907,18607176055,18986119772,18607122723,15392884621,18602700256,13554672283,18672183881,15391538720,13971161804,17702709109,13871117070,13659895958,13886134624,15827109546,17702767699,13419512596,13871255505,18971456457,18602744109,15972204319,18971547327,15307150416,13871053533,13871588525,13437124069,13986192239,13667253980,13387589966,18607182360,13971149229,18672328309,15623993505,15927121001,18171332731,18171238013,18696130721,18672962890,13545345621,18971222690,18717110939,18672946969,15342267261,15071262136,13986065030,13707126318,13871279670,13971197897,15927649543,15342251508,18627736022,13707111074,13407191796,15007171677,18963989533,13808679708,13986200080,13114365978,18607147310,15307159133,18607161617,13995579720,18827618485,13971140580,18964645214,13971153505,13871338435,18971577076,15802756322,18971378300,13638671262,15007150595,13627145354,15972878007,13971121153,13476165831,13554229084,13100671148,13317117890,13477058097,15307160266,13871230602,18064014270,13871261133,13871223432,13971154096,18995619798,13986138077,18627708070,13871217693,13437281289,13972977723,18971709336,13628609111,18171401663,18171400663,18627827597,15629189999,15342336911,18607128992,13871491618,17764068737,13871289913,13971373758,13971084969,13871015819,18607110949,18971309745,15071317562,15872716960,18502741129,15926310280,15902719310,13971450142,18627722629,18086662056,13971348862,15327200135,15927192327,13477079022,13871281281,13886012022,18571536819,18807101985,17786128046,13871589221,15927400205,15827054788,15902739984,13618664876,18607161071,15827250338,15623984368,15337277820,15337252335,13027156278,13971645080,13871424784,15827106450,13871371767,13396093735,18995571192,15327282181,13995610620,15327355503,13607133833,18271399631,13016401567,18627843867,18062133537,13871189056,15827310577,13871035638,18971564928,18672339936,13349966790,18171419597,18627870431,18627981638,18971687490,18507177672,13971098831,13720252544,13871289680,18627743323,18627152255,13886105395,13517227227,13797046702,15972111176,13026161062,18601390428,18162615225,18971378659,18062669891,18971252228,13871274609,18971320233,18062159007,18971199922,17786518405,15926361199,18607133721,13627131986,13517267156,13477030688,15907142267,18062046879,18942902635,18607191586,15002766499,13986081212,13477093236,13476835699,18986218282,13871405360,13995534016,13886180275,15202777108,13886085809,18872263665,18627943309,13396051751,13476233408,13407120413,13971636454,13971687100,18086605315,18502703117,13545173900,15327177799,18963986358,15327186619,18971213568,18986055647,15902775392,18062629552,13807177107,13006129041,13871106572,13329707570,13871155426,13554306322,17099610218,13667250930,13995687606,15629110085,18971371606,13397191550,18986265915,18602740613,13554456994,13971030942,13886042083,15071162150,13871186274,13986017636,13971409970,18602757753,13720180820,13477061455,18872251945,13349890811,18571521887,13007183163,13037191303,15926373022,18108637926,15327338686,18627759203,13971390937,13971295225,15871725637,15527797938,13477099246,13507198005,13476147762,13397196578,13720345265,13797053881,18672313998,15071128066,13703000921,13355611312,13437112560,15926224403,13871260664,13377893648,15927518186,13308630471,15392892803,13419605600,15927387535,13971437196,13986109239,13317103220,13971209457,13387570001,18627769240,13720261877,15007161736,13296642646,13469956918,15972952060,15271889013,18086035956,13720129177,13476173929,18717179489,18627154863,13886123067,15337256361,18571564215,18621801985,18666668237,18162605462,13007144193,15071276241,13872114792,18062537190,13871360692,18571526587,18872259306,13871406283,15327298861,13886161608,15907130367,13871083529,18971509425,18502730055,13554400204,15926347672,13407193611,18627924860,13986278685,13628647549,13100606700,13469965934,13476259192,13317136861,13971487790,13659821635,13986269745,18971686549,18062071766,18207107075,13971677837,13667195152,13006169151,15997461180,15926348858,13018029445,18086034861,18971160830,13971692975,18971174455,18062557192,13647207022,18171480126,13477002382,13986176145,18086662031,15527501076,13797052053,13871164079,13007197121,18908653389,18271890032,18502766679,15827385520,15172477242,18971082253,15827499564,13871378478,13667169919,15337199662,13437259892,13237183392,13517274992,13407182102,18827669338,13212781829,13072720668,15972001553,15342971881,18969068505,13554467760,13407144121,13163361534,18280001414,15327152686,13260596313,13971050750,18995563733,13517283929,13971052375,13071259227";


        $phone_list = explode(',', $data);

        $no_phones = array();
        $db = $this->_getAdapter();
        foreach ($phone_list as $p) {
            if (!$p) {
                echo '手机号不存在' . "\n";
            }
            $res = $db->query("select * from play_user LEFT join play_cashcoupon_user_link on play_cashcoupon_user_link.uid=play_user.uid  WHERE play_user.phone=? AND play_cashcoupon_user_link.cid=110 limit 1", array($p))->current();

            if ($res->cid) {
                echo "已发放";
                continue;
            }

            if (!$res) {
                $no_phones[] = $p;
            } else {
                //110
                $cash = new Coupon();
                $a = $cash->addCashcoupon($res->uid, 110, 0, 4, 0, '运营搞活动07/12');
                if ($a) {
                    echo "成功\r\n";
                } else {
                    echo "失败\r\n";
                }
            }
        }

        print_r($no_phones);

    }

    /**
     * 更新主站热门推荐
     *
     *微信接口地址 http://mp.weixin.qq.com/wiki/9/d347c6ddb6f86ab11ec3b41c2729c8d9.html
     * 接口名称 获取图文群发总数据 时间跨度只能是1
     * 统计的是7天  但最多统计发表日后7天数据
     */
    public function autoNewsStatusAction()
    {
        error_reporting(-1);
        ini_set('display_errors', '1');


        $whWeiConfig = $this->_getConfig()['kaibanle_weixin'];
        $njWeiConfig = $this->_getConfig()['nj_weixin'];
        $configList = array(
            array(
                'appid' => $whWeiConfig['appid'],
                'secret' => $whWeiConfig['secret'],
                'type_name' => '武汉遛娃宝典',
                'range' => 3,
            ),
            array(
                'appid' => $njWeiConfig['appid'],
                'secret' => $njWeiConfig['secret'],
                'type_name' => '南京遛娃宝典',
                'range' => 1,
            ),
        );

        $weiXin = new WeiXinFun($this->getwxConfig());

        foreach ($configList as $conf) {
            $ACCESS_TOKEN = $weiXin->getAccessToken($conf['appid'], $conf['secret']);
            $this->updateWeiStatus($conf['type_name'], $conf['range'], $ACCESS_TOKEN);
        }

        exit;
    }

    /**
     * @param $type_name //微信公众号名称
     * @param $range //更新多少条
     * @param $accountToken //微信公众号名称对应的token
     * @return bool
     */
    private function updateWeiStatus($type_name, $range, $accountToken)
    {

        $Url = 'https://api.weixin.qq.com/datacube/getarticletotal?access_token=' . $accountToken;
        $data = array();

        $postDataArr = array();
        for ($i = 1; $i < 8; $i++) {
            $postDataArr[] = array(
                'begin_date' => date("Y-m-d", time() - 86400 * $i),
                'end_date' => date("Y-m-d", time() - 86400 * $i),
            );
        }


        foreach ($postDataArr as $postData) {
            $re = $this->http_post_data($Url, json_encode($postData));
            if ($re['0'] != 200) {
                continue;
            }
            $result = json_decode($re['1'], true);
            if (!$result) {
                continue;
            }

            foreach ($result['list'] as $value) {
                if (isset($value['details'][count($value['details']) - 1]['int_page_read_user'])) {
                    $data[$value['title']] = $value['details'][count($value['details']) - 1]['int_page_read_user'];

                }
            }
        }

        if (!count($data)) {
            return false;
        }

        $sort = arsort($data);

        if (!$sort) {
            return false;
        }

        $this->_getMdbWeiPost()->update(array('type_name' => $type_name), array('$set' => array('status' => 0)), array('multiple' => true));

        $start = 0;
        foreach ($data as $k => $val) {

            if ($range > $start) {
                $this->_getMdbWeiPost()->update(array('type_name' => $type_name, 'title' => $k, 'dateline' => array('$gt' => time() - 604800)), array('$set' => array('status' => 1)));
            }

            $start++;
        }

        return true;

    }

    private function http_post_data($url, $data_string)
    {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json; charset=utf-8',
                'Content-Length: ' . strlen($data_string))
        );
        ob_start();
        curl_exec($ch);
        $return_content = ob_get_contents();
        ob_end_clean();

        $return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return array($return_code, $return_content);
    }


    private function checkIp()
    {
        $allow = array('59.173.17.122', '127.0.0.1', '192.168.7.8');

        $ip = $_SERVER['REMOTE_ADDR'];
        if (!in_array($ip, $allow) and isset($_SERVER['REMOTE_ADDR'])) {
            exit('非法ip请求');
        }

    }

    public function infotopriceAction()
    {
        $apt = $this->_getAdapter();
        $price = $i = 1;
        while ($price) {
            $price_sql = "select id from play_game_price where id > ? order by id asc limit 1";
            $price = $apt->query($price_sql, array(0))->current();
            $info_sql = "select goods_sm,integral from play_game_info where pid = ? and status > 0 ORDER by integral desc limit 1";
            $info = $apt->query($info_sql, array($price->id))->current();

            $update_sql = "update play_game_price set goods_sm = ?,integral = ? where id = ? limit 1";
            $apt->query($update_sql, [$info->goods_sm ?: '', $info->integral ?: 0, $price->id]);
            $i++;
            echo '\n' . time();
            if ($i > 4000) {
                break;
            }
        }
    }

    /**
     * 批量随机头像
     */
    public function virfaceAction()
    {
        $apt = $this->_getAdapter();
        $user = $i = 1;

        $dirArray[] = NULL;
        $pub = explode('V1', __DIR__)[0];
        if (false !== ($handle = opendir($pub . 'public/images/gravatar'))) {
            $i = 0;
            while (false !== ($file = readdir($handle))) {

                if ($file !== '.' && $file !== '..' && (strpos($file, '.jpg') or strpos($file, '.png'))) {
                    $dirArray[$i] = $file;
                    $i++;
                }
            }
            //关闭句柄
            closedir($handle);
        }
        $count = count($dirArray);

        while ($user) {
            $rand = mt_rand(0, $count - 1);
            $path = '/images/gravatar/' . $dirArray[$rand];

            $vir_sql = "update wft.play_user set img = ? where ( img = '' or img is NULL ) and is_vir = ? and uid > ? limit 1";
            $apt->query($vir_sql, array($path, 1, $i));

            $leavesql = "select * from wft.play_user where ( img = '' or img is NULL ) and is_vir = ? limit 1";
            $user = $apt->query($leavesql, array(1))->current();
        }
    }

    public function sendMeituanCouponAction()
    {
        echo "现在开始进行美团验证码的发送！\n\r";
        $this->SendOldOrder(1987);
        $this->SendOldOrder(2091);
        echo "美团验证码已经发送完毕！\n\r";
        exit;
    }
}
