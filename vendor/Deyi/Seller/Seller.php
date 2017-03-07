<?php

namespace Deyi\Seller;

use Application\Module;
use Deyi\GetCacheData\CouponCache;
use Deyi\GetCacheData\ExcerciseCache;
use Deyi\BaseController;

class Seller
{
    use BaseController;

    //BaseController 使用
    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }

    public $out_money = 20; //最低提现金额
    public $object_type = array(
        'goods',
        'activity',
    );


    /**
     * 验证是否是分销员
     * @param $uid
     * @return bool
     */
    public function isRight($uid)
    {
        $res = $this->_getPlayUserTable()->get(array('uid' => $uid));

        if (!$res || !($res->is_seller == 1)) {
            return false;
        }

        return true;

    }


    /**
     * 返回分销人员的账户
     * @param $uid
     * @return array
     */
    public function getSellerAccount($uid)
    {
        $Adapter = $this->_getAdapter();

        $sql = "SELECT
    SUM(if(play_distribution_detail.sell_type = 1 AND play_distribution_detail.sell_status = 2, 1, 0)) AS order_number,
    SUM(if(play_distribution_detail.sell_type = 1 AND play_distribution_detail.sell_status = 2, play_distribution_detail.price, 0)) AS total_sell,
    SUM(if(play_distribution_detail.sell_type = 2 AND play_distribution_detail.sell_status = 1, play_distribution_detail.rebate, 0)) AS not_arrived_income,
    SUM(if(play_distribution_detail.sell_type = 2 AND play_distribution_detail.sell_status = 2, play_distribution_detail.rebate, 0)) AS have_arrived_income,
    SUM(if(play_distribution_detail.sell_type = 2 AND play_distribution_detail.sell_status = 3, play_distribution_detail.price, 0)) AS total_sell_back,
    SUM(if(play_distribution_detail.sell_type = 3 AND play_distribution_detail.sell_status = 2, play_distribution_detail.price, 0)) AS withdraw_cash,
    SUM(if(play_distribution_detail.sell_type = 4 AND play_distribution_detail.sell_status = 2, play_distribution_detail.price, 0)) AS deduct_cash
FROM
	play_distribution_detail
WHERE
	sell_user_id = ?";

        $account_data = $Adapter->query($sql, array($uid))->current();

        //说明 not_arrived_income 未到收益 have_arrived_income 已到收益  withdraw_cash 已提现金额 deduct_cash 管理员扣款

        $data = array(
            'account_money' => bcsub(bcsub($account_data->have_arrived_income, $account_data->withdraw_cash , 2), $account_data->deduct_cash, 2),
            'add_up_income' => bcsub(bcadd($account_data->not_arrived_income, $account_data->have_arrived_income, 2), $account_data->deduct_cash, 2),
            'not_arrived_income' => bcadd($account_data->not_arrived_income, 0, 2),
            'withdraw_cash' => bcadd($account_data->withdraw_cash, 0, 2),
            'total_sell' => bcadd($account_data->total_sell, 0, 2),
            'have_arrived_income' => bcadd($account_data->have_arrived_income, 0, 2),
            'deduct_cash' => bcadd($account_data->deduct_cash, 0 , 2),
            'order_number' => $account_data->order_number,
            'total_sell_back' => bcadd($account_data->total_sell_back, 0 , 2),
        );

        return $data;

    }


    /**
     * 获取用户的账户流水
     * @param $start
     * @param $pageNum
     * @param $uid
     * @return array
     */
    public function fetchSellFlow($start, $pageNum, $uid)
    {

        $detail = $this->_getPlayDistributionDetailTable()->fetchLimit($start, $pageNum, array(),  array('sell_user_id' => $uid, '(sell_status = 2 OR (sell_status = 3 && sell_type = ?))' => 2), array('update_time' => 'DESC'));

        return $detail->toArray();

    }

    public function getGoodsListCount($city)
    {
        $Adapter = $this->_getAdapter();
        $data = array(
            'count_goods' => 0,
            'count_activity' => 0,
        );
        $timer = time();

        $id_goods_sql = "SELECT
	play_game_price.gid
FROM
	play_game_price
INNER JOIN play_game_info ON play_game_info.pid = play_game_price.id
INNER JOIN play_organizer_game ON play_organizer_game.id = play_game_price.gid
WHERE
	play_game_price.single_income > 0
AND play_game_info.`status` = 1
AND play_game_info.buy < play_game_info.total_num
AND play_organizer_game.`status` > 0
AND play_organizer_game.is_together = 1
AND play_organizer_game.city = '{$city}'
AND play_organizer_game.down_time >= {$timer}
AND play_organizer_game.up_time <= {$timer}
GROUP BY
	play_organizer_game.id";

        //todo 限制活动条件 及 场次条件 及城市
        $id_activity_sql = "SELECT
	play_excercise_base.id
FROM
	play_excercise_price
INNER JOIN play_excercise_event ON play_excercise_event.id = play_excercise_price.eid
INNER JOIN play_excercise_base ON play_excercise_base.id = play_excercise_event.bid
WHERE
	play_excercise_price.single_income > 0
AND play_excercise_base.city = '{$city}'
AND play_excercise_base.release_status= 1
AND play_excercise_event.sell_status>=1
AND play_excercise_event.sell_status!=3
AND play_excercise_event.start_time >(UNIX_TIMESTAMP()-1209600)
AND play_excercise_event.over_time>UNIX_TIMESTAMP()
AND play_excercise_event.customize = 0
AND play_excercise_event.join_number < play_excercise_event.perfect_number
GROUP BY
	play_excercise_base.id";

        $idGoodsData = $Adapter->query($id_goods_sql, array());
        $idActivityData = $Adapter->query($id_activity_sql, array());

        $data['count_goods'] = $idGoodsData->count();
        $data['count_activity'] = $idActivityData->count();

        return $data;
    }


    /**
     * 获取分销商品 或者 活动
     * @param $start
     * @param $pageNum
     * @param $type
     * @param $city
     * @return array
     */
    public function fetchGoodsList($start, $pageNum, $type, $city)
    {
        $Adapter = $this->_getAdapter();
        $data = array();
        $timer = time();

        if (!in_array($type, $this->object_type)) {
            return $data;
        }

        $id_goods_sql = "SELECT
	play_game_price.gid
FROM
	play_game_price
INNER JOIN play_game_info ON play_game_info.pid = play_game_price.id
INNER JOIN play_organizer_game ON play_organizer_game.id = play_game_price.gid
WHERE
	play_game_price.single_income > 0
AND play_game_info.`status` = 1
AND play_game_info.buy < play_game_info.total_num
AND play_organizer_game.`status` > 0
AND play_organizer_game.is_together = 1
AND play_organizer_game.city = '{$city}'
AND play_organizer_game.down_time >= {$timer}
AND play_organizer_game.up_time <= {$timer}
GROUP BY
	play_organizer_game.id";

            //todo 限制活动条件 及 场次条件 及城市
        $id_activity_sql = "SELECT
	play_excercise_base.id
FROM
	play_excercise_price
INNER JOIN play_excercise_event ON play_excercise_event.id = play_excercise_price.eid
INNER JOIN play_excercise_base ON play_excercise_base.id = play_excercise_event.bid
WHERE
	play_excercise_price.single_income > 0
AND play_excercise_base.city = '{$city}'
AND play_excercise_base.release_status= 1
AND play_excercise_event.sell_status>=1
AND play_excercise_event.sell_status!=3
AND play_excercise_event.start_time >(UNIX_TIMESTAMP()-1209600)
AND play_excercise_event.over_time>UNIX_TIMESTAMP()
AND play_excercise_event.customize = 0
AND play_excercise_event.join_number < play_excercise_event.perfect_number
GROUP BY
	play_excercise_base.id";

        $idGoodsData = $Adapter->query($id_goods_sql, array());
        $idActivityData = $Adapter->query($id_activity_sql, array());

        if ($type == 'goods') {
            $ids = '';
            foreach ($idGoodsData as $id) {
                $ids = $ids. ','. $id['gid'];
            }

            $ids = trim($ids, ',');

            if (!$ids) {
                return $data;
            }

            $res_sql = "SELECT
play_organizer_game.*,
MAX(play_game_price.single_income) AS max_single_income,
MIN(play_game_price.single_income) AS min_single_income
FROM
	play_organizer_game
LEFT JOIN play_game_price ON play_game_price.gid = play_organizer_game.id
WHERE play_organizer_game.id in ($ids)
GROUP BY play_organizer_game.id LIMIT
{$start}, {$pageNum}";

            $result = $Adapter->query($res_sql, array());

            foreach ($result as $res) {
                $data['list'][] = array(
                    'id' => $res['id'],
                    'cover' => $this->_getConfig()['url'] . $res['thumb'],
                    'title' => $res['title'],
                    'price' => $res['low_price'],
                    'low_money' => $res['low_money'],
                    'buy_number' => $res['buy_num'],
                    'address' => $res['shop_addr'],
                    'pre_income' => array(
                        'min' => $res['min_single_income'],
                        'max' => $res['max_single_income'],
                    ),
                );
            }

        }

        if ($type == 'activity') {

            $ids = '';
            foreach ($idActivityData as $id) {
                $ids = $ids. ','. $id['id'];
            }

            $ids = trim($ids, ',');

            if (!$ids) {
                return $data;
            }

            //同时 判断要加条件 场次 正在进行或者未进行
            $res_sql = "SELECT
play_excercise_base.*,
MAX(play_excercise_price.single_income) AS max_single_income,
MIN(play_excercise_price.single_income) AS min_single_income,
COUNT(DISTINCT play_excercise_event.id) AS events_num
FROM
	play_excercise_base
LEFT JOIN play_excercise_event ON play_excercise_event.bid = play_excercise_base.id
INNER JOIN play_excercise_price ON play_excercise_price.eid = play_excercise_event.id
WHERE play_excercise_base.id in ($ids)
GROUP BY play_excercise_base.id LIMIT
{$start}, {$pageNum}";

            $result = $Adapter->query($res_sql, array());

            foreach ($result as $res) {
                $data['list'][] = array(
                    'id' => $res['id'],
                    'cover' => $this->_getConfig()['url'] . $res['thumb'],
                    'title' => $res['name'],
                    'price' => $res['low_price'],
                    'buy_number' => $res['join_number'],
                    'start_time' => $res['max_start_time'],
                    'end_time' => $res['min_end_time'],
                    'events_num' => $res['events_num'],
                    'address' => CouponCache::getBusniessCircle(ExcerciseCache::getCircleByBid($res['id'])),
                    'pre_income' => array(
                        'min' => $res['min_single_income'],
                        'max' => $res['max_single_income'],
                    ),
                );
            }
        }

        return $data;
    }

    /**
     * 下单 记录分销记录
     * @param $orderData
     * @return bool
     */
    public function fission($orderData)
    {

        if (!$orderData || !$orderData->order_sn) {
            return false;
        }

        if (!in_array($orderData->order_type, array(2, 3))) {
            return false;
        }

        //获取最近的seller_id
        $user_id = $orderData->user_id;
        $object_id = ($orderData->order_type == 2) ? $orderData->coupon_id : $orderData->bid;
        $object_type = ($orderData->order_type == 2) ? 'goods' : 'activity';

        $seller_id = $this->getEffectiveSellerId($user_id, $object_id, $object_type);

        if (!$seller_id) {
            return false;
        }

        //判断seller_id 合法性
        if (!$this->isRight($seller_id)) {
            return false;
        }

        $order_sn = $orderData->order_sn;
        $timer = time();
        $buy_user_id = $orderData->user_id;

        $description =  $this->hidePhoneNumber($orderData->username). '成功购买了'. $orderData->coupon_name;
        $price = bcadd(bcadd($orderData->account_money, $orderData->real_pay, 2), $orderData->voucher, 2);

        if ($orderData->order_type == 2) {//商品

            $gameInfo = $this->_getPlayGameInfoTable()->get(array('id' => $orderData->bid));
            $gamePrice = $this->_getPlayGamePriceTable()->get(array('id' => $gameInfo->pid));

            if ($gamePrice->single_income > 0) {
                $rebate = bcmul($gamePrice->single_income, $orderData->buy_number, 2);
                $single_income = $gamePrice->single_income;

                $codeData = $this->_getPlayCouponCodeTable()->fetchAll(array('order_sn' => $order_sn));

                $data = array(
                    'sell_type' => 1,
                    'sell_user_id' => $seller_id,
                    'buy_user_id' => $buy_user_id,
                    'order_id' => $order_sn,
                    'code_id' => 0,
                    'price' => $price,
                    'rebate_type' => 1,
                    'rebate' => $rebate,
                    'create_time' => $timer,
                    'update_time' => $timer,
                    'sell_status' => 2,
                    'description' => $description
                );

                $tip = $this->_getPlayDistributionDetailTable()->insert($data);

                foreach ($codeData as $code) {
                    $res = array(
                        'sell_type' => 2,
                        'sell_user_id' => $seller_id,
                        'buy_user_id' => $buy_user_id,
                        'order_id' => $order_sn,
                        'code_id' => $code['id'],
                        'price' => $orderData->coupon_unit_price,
                        'rebate_type' => 1,
                        'rebate' => $single_income,
                        'create_time' => $timer,
                        'sell_status' => 1,
                    );

                     $this->_getPlayDistributionDetailTable()->insert($res);
                }

                return $tip ? true : false;

            } else {
                return false;
            }

        } elseif ($orderData->order_type == 3) {//活动

            $codeData = $this->_getPlayExcerciseCodeTable()->fetchAll(array('order_sn' => $order_sn));

            $rt = 0;
            $rebate = 0;
            $res = array();
            foreach ($codeData as $code) {
                 $activityPrice = $this->_getPlayExcercisePriceTable()->get(array('id' => $code['pid']));

                 if ($activityPrice->single_income > 0) {
                     $rt ++;
                     $rebate = $rebate + $activityPrice->single_income;
                     $res[] = array(
                         'sell_type' => 2,
                         'sell_user_id' => $seller_id,
                         'buy_user_id' => $buy_user_id,
                         'order_id' => $order_sn,
                         'code_id' => $code['id'],
                         'price' => $code['price'],
                         'rebate_type' => 1,
                         'rebate' => $activityPrice->single_income,
                         'create_time' => $timer,
                         'sell_status' => 1,
                     );

                 } else {
                     continue;
                 }

            }

            if (!$rt) {
                return false;
            }

            $data = array(
                'sell_type' => 1,
                'sell_user_id' => $seller_id,
                'buy_user_id' => $buy_user_id,
                'order_id' => $order_sn,
                'code_id' => 0,
                'price' => $price,
                'rebate_type' => 1,
                'rebate' => $rebate,
                'create_time' => $timer,
                'update_time' => $timer,
                'sell_status' => 2,
                'description' => $description
            );

            $tip = $this->_getPlayDistributionDetailTable()->insert($data);

            if ($tip) {
                foreach ($res as $value) {
                    $this->_getPlayDistributionDetailTable()->insert($value);
                }
            }
            
            return $tip ? true : false;

        }

        return false;

    }


    /**
     * 使用记录
     * @param $order_id
     * @param $code_id
     * @return bool
     */
    public function used($order_id, $code_id)
    {
        $res = $this->_getPlayDistributionDetailTable()->get(array('order_id' => $order_id, 'code_id' => $code_id, 'sell_status' => 1, 'sell_type' => 2));

        if (!$res) {
            return false;
        }

        $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_id));
        $tip = $this->_getPlayDistributionDetailTable()->fetchAll(array('order_id' => $order_id, 'sell_type' => 2, 'sell_status' => 1));

        $description = $this->hidePhoneNumber($orderData->username). (($tip->count() > 1) ? '部分' : ''). '使用了'. $orderData->coupon_name;

        $result = $this->_getPlayDistributionDetailTable()->update(array('sell_status' => 2, 'update_time' => time(), 'description' => $description), array('id' => $res->id));

        return $result ? true : false;
    }

    /**
     * 退款记录
     * @param $order_id
     * @param $code_id
     * @return bool
     */
    public function back($order_id, $code_id)
    {

        $res = $this->_getPlayDistributionDetailTable()->get(array('order_id' => $order_id, 'code_id' => $code_id, 'sell_status' => 1, 'sell_type' => 2));

        if (!$res) {
            return false;
        }
        $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_id));
        $tip = $this->_getPlayDistributionDetailTable()->fetchAll(array('order_id' => $order_id, 'sell_type' => 2, 'sell_status' => 1));

        $description = $this->hidePhoneNumber($orderData->username). (($tip->count() > 1) ? '部分' : ''). '退款了'. $orderData->coupon_name;

        $result = $this->_getPlayDistributionDetailTable()->update(array('sell_status' => 3, 'update_time' => time(), 'description' => $description), array('id' => $res->id));

        return $result ? true : false;
    }

    /**
     * 提现
     * @param $uid
     * @param $money
     * @return array
     */
    public function withdraw($uid, $money)
    {
        $have = $this->_getPlayDistributionDetailTable()->get(array('sell_type' => 3, 'sell_status' => 1, 'sell_user_id' => $uid));

        if ($have) {
           return array('status' => 0, 'message' => '以前的提现正在受理中, 请等待');
        }

        if ($money < $this->out_money) {
            return array('status' => 0, 'message' => '可提现的钱不足');
        }

        $accountData = $this->getSellerAccount($uid);

        if ($accountData['account_money'] < $money) {
            return array('status' => 0, 'message' => '账户中可提现的钱不足！请多多加油哦!');
        }

        $timer = time();

        $data = array(
            'sell_type' => 3,
            'sell_user_id' => $uid,
            'buy_user_id' => 0,
            'order_id' => 0,
            'code_id' => 0,
            'price' => $money,
            'create_time' => $timer,
            'update_time' => $timer,
            'sell_status' => 1,
            'description' => '提现'
        );

        $result = $this->_getPlayDistributionDetailTable()->insert($data);

        if (!$result) {
            return array('status' => 0, 'message' => '提现失败');
        }

        return array('status' => 1, 'message' => '成功');
    }

    /**
     * 提现审批操作
     * @param $id
     * @param $type 1通过 2 不通过
     * @return bool|int
     */
    public function charge($id, $type)
    {
        $result = false;
        if ($type == 1) {
            $result = $this->_getPlayDistributionDetailTable()->update(array('sell_status' => 2, 'update_time' => time()), array('id' => $id, 'sell_status' => 1));
        }

        if ($type == 2) {
            $result = $this->_getPlayDistributionDetailTable()->update(array('sell_status' => 4, 'update_time' => time()), array('id' => $id, 'sell_status' => 1));
        }

        return $result;

    }

    /**
     * 销售员状态操作
     * @param $uid
     * @param $type 1不是销售员设为销售员 2 取消销售员 3 取消的销售员变为销售员
     * @return bool|int
     */
    public function beMan($uid, $type)
    {
        $result = false;
        if ($type == 1) {
            $result = $this->_getPlayUserTable()->update(array('is_seller' => 1), array('uid' => $uid, 'is_seller' => 0));
        }

        if ($type == 2) {
            $result = $this->_getPlayUserTable()->update(array('is_seller' => 2), array('uid' => $uid, 'is_seller' => 1));
        }

        if ($type == 3) {
            $result = $this->_getPlayUserTable()->update(array('is_seller' => 1), array('uid' => $uid, 'is_seller' => 2));
        }

        return $result;

    }

    /**
     * 后台管理员扣款
     * @param $uid
     * @param $money
     * @param $reason
     * @return array
     */
    public function deduct($uid, $money, $reason)
    {

        if ($money <= 0) {
            return array('status' => 0, 'message' => '扣的钱不能为0');
        }

        if (!$reason) {
            return array('status' => 0, 'message' => '扣款原因必填');
        }

        $accountData = $this->getSellerAccount($uid);

        if ($accountData['account_money'] < $money) {
            return array('status' => 0, 'message' => '账户中可扣除的钱不足！');
        }

        $timer = time();

        $data = array(
            'sell_type' => 4,
            'sell_user_id' => $uid,
            'buy_user_id' => 0,
            'order_id' => 0,
            'code_id' => 0,
            'price' => $money,
            'create_time' => $timer,
            'update_time' => $timer,
            'sell_status' => 2,
            'description' => $reason
        );

        $result = $this->_getPlayDistributionDetailTable()->insert($data);

        if (!$result) {
            return array('status' => 0, 'message' => '扣钱失败');
        }

        return array('status' => 1, 'message' => '成功');

    }

    //判断是否是 分销商品 活动
    public function judge($id, $type)
    {
        if (!in_array($type, $this->object_type)) {
            return false;
        }

        if ($type == 'goods') {
            $tip = $this->_getPlayGamePriceTable()->get(array('single_income > ?' => 0, 'gid' => $id));
        } elseif ($type == 'activity') {

            $Adapter = $this->_getAdapter();

            $sql = "SELECT
	play_excercise_price.id
FROM
	play_excercise_price
LEFT JOIN play_excercise_event ON play_excercise_price.eid = play_excercise_event.id
WHERE
	play_excercise_price.eid > 0
AND play_excercise_event.bid = ?
AND play_excercise_price.single_income > 0";

            $tip = $Adapter->query($sql, array($id))->count();

        } else {
            return false;
        }

        if ($tip) {
            return true;
        }

        return false;
    }

    /**
     * 记录分销员分享的 商品 及 活动 点击 日志
     * @param $seller_id //销售员id
     * @param $user_id //用户id
     * @param $object_id //对象id
     * @param $object_type //对象类型 'goods', 'activity'
     * @return bool
     */
    public function shareEffective($seller_id, $user_id, $object_id, $object_type)
    {
        if (!$seller_id || $seller_id == $user_id) {
            return false;
        }

        if (!in_array($object_type, $this->object_type)) {
            return false;
        }

        if (!$this->isRight($seller_id)) {
            return false;
        }

        if (!$this->judge($object_id, $object_type)) {
            return false;
        }

        if (!$user_id) {
            //记录点击行为
            setcookie('seller_'. $object_type. $object_id, $seller_id, 12*3600, '/');

            return true;
        }

        $data = array(
            'object_id' => $object_id,
            'object_type' => $object_type,
            'seller_id' => $seller_id,
            'user_id' => $user_id,
            'click_time' => time(),
        );

        //todo 最新的 有 更新 无 加
        $flag = $this->_getPlayDistributionLogTable()->get(array('object_id' => $object_id, 'seller_id' => $seller_id, 'object_type' => $object_type, 'user_id' => $user_id));
        if ($flag) {
            $result = $this->_getPlayDistributionLogTable()->update(array('click_time' => time()), array('id' => $flag->id));
        } else {
            $result = $this->_getPlayDistributionLogTable()->insert($data);
        }

        if (!$result) {
            return false;
        }

        return true;
    }

    /**
     * 获取该订单有效的 seller_id
     * @param $uid
     * @param $object_id
     * @param $type
     * @return int
     */
    private function getEffectiveSellerId($uid, $object_id, $type)
    {

        if (!in_array($type, $this->object_type)) {
            return false;
        }

        //7天
        $value_time = time() - 7*24*3600;
        $result = $this->_getPlayDistributionLogTable()->fetchLimit(0, 1, array(), array('user_id' => $uid, 'object_id' => $object_id, 'object_type' => $type, 'log_status' => 1, 'click_time > ?' => $value_time), array('click_time' => 'DESC'))->current();

        if ($result) {
            return $result->seller_id;
        }

        $seller_id = $_COOKIE['seller_'. $type. $object_id];

        return $seller_id ? $seller_id : false;

    }

}



