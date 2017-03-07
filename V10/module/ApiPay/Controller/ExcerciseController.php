<?php


namespace ApiPay\Controller;

use Application\Module;
use Deyi\BaseController;
use Deyi\OrderAction\CancelOrder;
use Deyi\OrderAction\InsertExcerciseOrder;
use Deyi\OrderAction\OrderExcerciseBack;
use Deyi\OrderAction\OrderPay;
use library\Fun\Common;
use library\Service\Kidsplay\Kidsplay;
use library\Service\System\Cache\RedCache;
use Deyi\Account\Account;
use library\Service\User\Member;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Select;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Response;
use Deyi\JsonResponse;

class ExcerciseController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    //3.3遛娃活动 生成订单
    public function indexAction()
    {

        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = $this->getParams('uid'); //用户id
        $id = $this->getParams('id');  //活动id
        $session_id = $this->getParams('session_id');//场次id
        $associates_ids = json_decode($this->getParams('associates_ids', '[]', false), true); //出行人列表
        $name = $this->getParams("name", ' ');//获取购买者姓名
        $phone = $this->getParams("phone");//获取购买者手机号
        $address = $this->getParams('address'); //购买者地址
        $cash_coupon_id = (int)$this->getParams('cash_coupon_id', 0); //现金券用户表的id
        $use_account_money = $this->getParams('use_account_money', 0); //使用账户金额付款  只有当账户金额足够时
        $pwd = $this->getParams('pay_password', 0);// 支付密码 错误返回-1
        $charges = json_decode($this->getParams('charges', [], false), true);//收费项
        $message = $this->getParams('message', 0); //给卖家留言
        $meeting_id = $this->getParams('meeting_id');//集合方式id
        $share_order_sn = $this->getParams('share_order_sn', 0);//分享者订单号

        if (!$associates_ids or !is_array($associates_ids)) {
            $associates_ids = array();
        }

        $user_city = $this->getCity();

        //  检查参数是否正确
        if (!$uid or empty($charges) or !$id or !$session_id) {
            return $this->jsonResponseError('参数错误');
        }


        // 避免同一用户并发
        if (RedCache::get('userpay' . $uid)) {
            // 间隔时间过短  sleep or return
            return $this->jsonResponse(['status' => 0, 'message' => '下单频率过高，请稍后再试']);
        } else {
            RedCache::set('userpay' . $uid, time(), 2);
        }

        $db = $this->_getAdapter();

        //活动详情
        $event_data = $this->_getPlayExcerciseEventTable()->get(array('id' => $session_id));
        $base_data = $this->_getPlayExcerciseBaseTable()->get(array('id' => $id));
        $price = $this->_getPlayExcercisePriceTable()->fetchAll(array('eid' => $session_id), array('price' => 'desc'))->toArray(); //收费项
        $user_data = $this->_getPlayUserTable()->get(['uid' => $uid]);


        //套系数据
        if (!$event_data or !$base_data) {
            return $this->jsonResponse(['status' => 0, 'message' => '活动已删除或不存在']);
        }
        if ($event_data->open_time > time()) {
            return $this->jsonResponse(['status' => 0, 'message' => '未到开始售卖时间']);
        }

        $all_least = 0;
        $data_price = array();
        foreach ($price as $v) {
            if ($v['is_other'] == 0) {
                $all_least += ($v['least'] * $v['person']);
            }
            $data_price[$v['id']] = $v;
        }
        //最大剩余数量
        $surplus = $event_data->most_number - ($event_data->join_ault + $event_data->join_child);


        //判断购买数量,价格和剩余量

        /*
         * 最小 必须达到才可以
完美 停止报名
最大 最后一单允许的弹性
        */
        $people_number = 0;  //占用名额的数量（需要购买保险的数量）

        $l = array();
        $service_member = new Member();
        $service_kidsplay = new Kidsplay();
        $data_member = $service_member->getMemberData($uid);

        foreach ($charges as $k => $v) {
            if (!$v['id'] or !isset($v['buy_number'])) {
                return $this->jsonResponse(['status' => 0, 'message' => '收费项id或购买数量不存在']);
            }

            if ($v['buy_number'] == 0) {
                unset($charges[$k]);
                continue;
            }

            $data_price_item = $data_price[$v['id']];

            // 判断收费项数量
            if ($data_price_item['is_other'] != 1) {
                $people_number += bcmul($v['buy_number'], ($data_price_item['person_ault'] + $data_price_item['person_child']), 0);
            }

            $charges[$k]['price'] = $data_price_item['price'];                   //对应的价格
            $charges[$k]['title'] = $data_price_item['price_name'];              //对应的名称
            $charges[$k]['is_other'] = $data_price_item['is_other'];                //是否属于其他收费项
            $charges[$k]['person'] = (int)$data_price_item['person'];                  //出行人数
            $charges[$k]['person_ault'] = (int)$data_price_item['person_ault'];             //出行人数成人
            $charges[$k]['person_child'] = (int)$data_price_item['person_child'];            //出行人数小孩
            $charges[$k]['free_buy_number'] = (int)$v['free_buy_number'];                       //使用亲子游资格券兑换的数量
            $charges[$k]['free_coupon_need_count'] = (int)$data_price_item['free_coupon_need_count'];  //兑换一份所需亲子游资格券数量
            $charges[$k]['free_coupon_max_count'] = (int)$data_price_item['free_coupon_max_count'];  //可使用亲子游资格券兑换的份数
            $charges[$k]['free_coupon_join_count'] = (int)$data_price_item['free_coupon_join_count'];  //已经使用亲子游资格券兑换的份数

            $l[] = $charges[$k];

            $data_charges = $charges[$k];

            if ($data_price_item['most']) { //最大购买份数
                //判断已经购买数量
                $my_buy = $service_kidsplay->getCountPriceBuy($uid, $v['id']);

                if (($my_buy + $v['buy_number']) > $data_price_item['most']) {
                    return $this->jsonResponse(['status' => 0, 'message' => "您已经购买了{$my_buy}份[{$data_price_item['price_name']}],每人限购{$data_price_item['most']}份哦"]);
                }
            }

            if ($data_price_item['least']) { //最小购买份数
                if ($all_least < $surplus and $v['buy_number'] < $data_price_item['least']) {
                    return $this->jsonResponse(['status' => 0, 'message' => "最少要购买{$data_price_item['least']}份哦"]);
                }
            }

            // 是否使用亲子游资格券进行收费项兑换
            if ($data_charges['free_buy_number'] > 0) {
                // 是否已兑换过该收费项
                $data_use_free_coupon = $service_kidsplay->getCountPriceFreeBuy($uid, $v['id']);

                if ($data_use_free_coupon > 0) {
                    return $this->jsonResponse(['status' => 0, 'message' => "[{$data_price_item['price_name']}]您只能使用亲子游资格券兑换一份哦"]);
                }

                // 是否兑换过当天其他活动的亲子游收费项
                $data_start_time = $event_data->start_time;
                $data_end_time = $event_data->end_time;
                $data_use_free_coupon_day = $service_kidsplay->getCountPriceDayFreeBuy($uid, $data_start_time, $data_end_time);

                if ($data_use_free_coupon_day > 0) {
                    return $this->jsonResponse(['status' => 0, 'message' => "活动当天您已使用亲子游资格兑换过其他活动了"]);
                }

                // 用户持有的亲子游资格券是否足够
                if ($data_member['member_free_coupon_count_now'] >= $data_charges['free_buy_number'] * $data_charges['free_coupon_need_count']) {
                    // 判断可兑换名额是否足够
                    if ($data_price_item['free_coupon_max_count'] > 0 && $data_price_item['free_coupon_join_count'] + $data_charges['free_buy_number'] > $data_price_item['free_coupon_max_count']) {
                        return $this->jsonResponse(['status' => 0, 'message' => "对不起，该场次的亲子游兑换名额已满，请您尝试选择其他场次"]);
                    }
                } else {
                    return $this->jsonResponse(['status' => 0, 'message' => "您的亲子游次数不足，请可前往会员充值获取更多的亲子游次数"]);
                }
            }

        }
        //抛弃可能多余的id
        $charges = $l;


        if ($people_number == 0) {
            return $this->jsonResponse(['status' => 0, 'message' => '购买数量为0']);
        }
        if ($people_number >= 999) {
            return $this->jsonResponse(['status' => 0, 'message' => '最大购买数为999']);
        }

        if ($event_data->join_number == $event_data->perfect_number) {
            return $this->jsonResponse(['status' => 0, 'message' => "已经满员了哦"]);
        }
        $surplus = $event_data->most_number - ($event_data->join_ault + $event_data->join_child);
        if ($surplus < 0) {
            $surplus = 0;
        }
        // 判断数量
        if ($people_number > $surplus) {
            return $this->jsonResponse(['status' => 0, 'message' => "名额不够,目前只剩{$surplus}个名额"]);
        }

        $real_pay = 0;
        foreach ($charges as $v) {
            $real_pay = bcadd(bcmul($v['price'], $v['buy_number'], 2), $real_pay, 2);
        }

        //现金券是否可以使用
        if ($cash_coupon_id) {
            $ticket = $db->query("SELECT
	a.*,d.time_type,d.description
FROM
	play_cashcoupon_user_link AS a
 LEFT JOIN play_cashcoupon_good_link AS b ON a.cid = b.cid
LEFT JOIN play_cashcoupon_city AS c ON a.cid = c.cid
LEFT JOIN play_cash_coupon AS d ON a.cid = d.id
WHERE  ( d.`range` = 3
OR (d.`range` = 4 and (
  b.object_type = 4
  AND b.object_id = ? and a.id = ?
 ))
)
AND a.uid = ?
AND a.pay_time = 0
AND (
	is_main = 1
	OR (is_main = 0 AND c.city = ?)
)
AND a.use_stime <?
AND a.use_etime >?
AND a.price<=?
GROUP BY
	a.cid
ORDER BY
	a.price DESC,a.use_etime DESC ,a.create_time DESC
            ", array($session_id, $cash_coupon_id, $uid, $user_city, time(), time(), $real_pay))->current();


            if (!$ticket) {
                return $this->jsonResponse(['status' => 0, 'message' => '现金券不存在或不支持此商品']);
            }
            if ($ticket->use_stime > time()) {
                return $this->jsonResponse(['status' => 0, 'message' => '使用时间未到']);
            } elseif ($ticket->use_etime < time()) {
                return $this->jsonResponse(['status' => 0, 'message' => '优惠券使用时间已截止']);
            }
        }


        if (!$user_data) {
            return $this->jsonResponse(['status' => 0, 'message' => '用户已删除或不存在']);
        } else {
            if ($user_data->status == 0) {
                return $this->jsonResponse(['status' => 0, 'message' => '用户已禁用']);
            }
        }

        // 商品已下架
        if ($base_data->release_status < 0) {
            return $this->jsonResponse(['status' => 0, 'message' => '已删除']);
        }

        if ($base_data->release_status == 0) {
            return $this->jsonResponse(['status' => 0, 'message' => '活动未发布']);
        }


        if ($event_data->sell_status == 0) {
            return $this->jsonResponse(['status' => 0, 'message' => '已停止售卖']);
        }
        if ($event_data->join_number >= $event_data->perfect_number) {
            return $this->jsonResponse(['status' => 0, 'message' => '已满员']);
        }


        if ($event_data->sell_status < 1) {//总限制
            return $this->jsonResponse(['status' => 0, 'message' => '场次未发布或已暂停']);
        }
        if ($event_data->sell_status == 3) {//总限制
            return $this->jsonResponse(['status' => 0, 'message' => '活动已结束']);
        }
        if ($event_data->over_time < time()) {
            return $this->jsonResponse(['status' => 0, 'message' => '活动场次已截止报名']);
        }

        // 判断用户是否绑定手机号  客户端bug处理
        if (!$user_data->phone) {
            return $this->jsonResponse(['status' => 0, 'message' => '您还未绑定手机号,请绑定手机号后再来购买吧']);
        }

        if (!$name) {
            $name = $user_data->username;
        }
        if (!$phone) {
            $phone = $user_data->phone;
        }

        if ($use_account_money) {
            $pdo = $this->_getAdapter();
            $sql = " SELECT uid, `status` FROM play_account WHERE uid = ? AND `password` = md5(CONCAT(md5(?), salt)) ";
            $data_account = $pdo->query($sql, array($uid, $pwd))->current();

            if (empty($data_account)) {
                return $this->jsonResponse(['status' => -1, 'message' => "账户密码错误"]);
            } else {
                if ($data_account->status == 0) {
                    return $this->jsonResponse(['status' => 0, 'message' => "用户账户已冻结"]);
                }
            }
        }

        $city = $event_data->city;

        $insert_order = new InsertExcerciseOrder();
        $res = $insert_order->insertOrder(
            $event_data,
            $base_data,
            $charges,
            $user_data,
            $associates_ids,
            $people_number,
            $name,
            $phone,
            $address,
            $cash_coupon_id,
            $use_account_money,
            $message,
            $meeting_id,
            $city,
            $share_order_sn
        );
        return $this->jsonResponse($res);
    }


    public function updateOrderMeetingAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $order_sn = $this->getParams('order_sn');
        $meeting_id = $this->getParams('meeting_id');
        $uid = $this->getParams('order_sn');


        if (!$order_sn or !$meeting_id) {
            return $this->jsonResponseError('参数错误');
        }
        $meeting_data = $this->_getPlayExcerciseMeetingTable()->get(array('id' => $meeting_id));


        $s = $this->_getPlayOrderOtherDataTable()->update(array(
            'meeting_place' => $meeting_data->meeting_place,
            'meeting_time' => $meeting_data->meeting_time,
            'meeting_id' => $meeting_data->id
        ), array('order_sn' => $order_sn));


        if ($s) {
            return $this->jsonResponse(array('status' => 1, 'message' => '修改成功'));
        }
        return $this->jsonResponse(array('status' => 0, 'message' => '修改失败'));
    }

    //3.3提交退款
    public function backpayAction()
    {


        if (!$this->pass()) {
            return $this->failRequest();
        }
        $order_sn = (int)$this->getParams('order_sn');
        $all_back = (int)$this->getParams('all_back');

        $code = $this->getParams('code');
        $uid = (int)$this->getParams('uid');

        $insure_id = $this->getParams('insure_id', 0);  //多个使用逗号隔开  可选,如果没有选择出行人就不需要


        if (!$order_sn) {
            return $this->jsonResponseError('请求参数错误');
        }
        $back_data = array('status' => 0, 'message' => '已使用或已退款');
        if ($all_back == 1) {
            $codeData = $this->_getPlayExcerciseCodeTable()->fetchAll(array('order_sn' => $order_sn, 'status' => 0, 'uid' => $uid));

            if (!$codeData) {
                return $this->jsonResponse($back_data);
            }

            $back = new OrderExcerciseBack();
            $back_ok = false;
            foreach ($codeData as $v) {
                $back_data = $back->backIng($order_sn, $v->code, 1);
                if ($back_data['status'] == 1) {
                    $back_ok = true;
                }
            }

            //只要有退成功
            if ($back_ok) {
                //删除对应的保险
                $this->_getPlayOrderInsureTable()->delete(array('order_sn' => $order_sn));
            }


        } elseif ($code) {
            $codeData = $this->_getPlayExcerciseCodeTable()->get(array('order_sn' => $order_sn, 'status' => 0, 'code' => $code, 'uid' => $uid));

            if (!$codeData) {
                return $this->jsonResponse(array('status' => 0, 'message' => '验证码不存在或已使用'));
            }

            $price = $this->_getPlayExcercisePriceTable()->get(array('id' => $codeData->pid));
            $back = new OrderExcerciseBack();
            if ($insure_id) {
                $ins = explode(',', $insure_id);

                if (empty($ins)) {
                    return $this->jsonResponse(array('status' => 0, 'message' => '需要取消的出行人未选择'));
                } else {
                    if ($price->person != count($ins)) {
                        //判断是否老版本
                        $client_info = Common::getClientinfo();
                        if ($client_info['client'] === 'ios' or $client_info['client'] === 'android') {
                            $ver = sprintf('%-03s', str_replace('.', '', $client_info['ver']));
                            if ($ver < 333) {
                                return $this->jsonResponse(array('status' => 0, 'message' => '请升级到最新版'));
                            }
                        }
                        return $this->jsonResponse(array('status' => 0, 'message' => '选择的出行人小于收费项对应的出行人数'));
                    }
                    $back_data = $back->backIng($order_sn, $code, 1);
                    if ($back_data['status'] == 1 and $price->is_other == 0) {
                        foreach ($ins as $v) {
                            $this->_getPlayOrderInsureTable()->delete("(insure_id={$v} or associates_id={$v}) and order_sn={$order_sn}");
                        }
                    }
                }
            } else {
                $back_data = $back->backIng($order_sn, $code, 1);
                if ($back_data['status'] == 1 and $price->is_other == 0) {
                    $this->_getAdapter()->query("delete from   play_order_insure where order_sn=? order BY insure_status ASC  limit {$price->person}", array($order_sn));
                }
            }

        } else {
            return $this->jsonResponseError('参数错误');
        }

        return $this->jsonResponse($back_data);

    }


    //购买购补录出行人接口
    public function addAssociatesAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = $this->getParams('uid', 0);
        $order_sn = $this->getParams('order_sn', 0); //订单id
        $associates_ids = json_decode($this->getParams('associates_ids', null, false), true); //出行人

        $order_data = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn, 'user_id' => $uid));
        $event_data = $this->_getPlayExcerciseEventTable()->get(array('id' => $order_data->coupon_id));

        if (!$order_data or !$event_data) {
            return $this->jsonResponseError('数据不存在');
        }
        //更新已生成订单的出行人(生成保单)
        if (is_array($associates_ids)) {
            $ins_num = $this->_getPlayOrderInsureTable()->fetchCount(array('order_sn' => $order_sn));
            if ($ins_num < count($associates_ids)) {
                $associates_ids = array_slice($associates_ids, 0, $ins_num);
            }
            $s = $this->_getPlayOrderInsureTable()->delete(array('order_sn' => $order_sn)); // 并发操会导致返回失败
            if (!$s) {
                return $this->jsonResponse(['status' => 0, 'message' => "操作频率过高"]);
            }
            $adapter = $this->_getAdapter();
            $insure_data = '';
            for ($i = 0; $i < count($associates_ids); $i++) {
                $associates_info = $adapter->query("select * from play_user_associates WHERE associates_id=?", array($associates_ids[$i]))->current();

                $insure_status = 1;
                if (!$associates_info) {
                    $insure_status = 0;
                }
                $insure_data .= "('{$order_sn}','{$order_data->coupon_id}','{$associates_info->name}',
                            '{$associates_info->sex}','{$associates_info->birth}','{$associates_info->id_num}',1,'','','{$insure_status}',{$associates_ids[$i]},'{$event_data->insurance_id}'),";
            }

            if ($ins_num > count($associates_ids)) {

                for ($i = 0; $i < ($ins_num - count($associates_ids)); $i++)
                    $insure_data .= "('{$order_sn}','{$order_data->coupon_id}','','','','',1,'','','0',0,'{$event_data->insurance_id}'),";
            }

            $insure_data = substr($insure_data, 0, -1);


            if (!empty($insure_data)) {
                $stmt = $adapter->query('INSERT INTO play_order_insure (order_sn,coupon_id,`name`,sex,birth,id_num,insure_company_id,insure_sn,baoyou_sn,insure_status,associates_id,product_code) VALUES ' . $insure_data);
                $s = $stmt->execute($stmt)->count();

                if (!$s) {
                    return $this->jsonResponseError('更新失败');
                }
            }
        } else {
            return $this->jsonResponseError('参数错误');
        }

        //已填满
        if (count($associates_ids) == $ins_num) {
            $this->_getPlayOrderOtherDataTable()->update(array('full_sssociates' => 1), array('order_sn' => $order_sn));
        }

        return $this->jsonResponse(['status' => 1, 'message' => "操作成功"]);
    }


    //主动取消订单
    public function cancelOrderAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }
        $uid = (int)$this->getParams('uid');
        $order_sn = (int)$this->getParams('order_sn');
       // $type = $this->getParams('type');//订单类型  1普通订单  2活动

        $data = \library\Service\Order\CancelOrder::CancelOrder($order_sn,$uid);
        return $this->jsonResponse($data);

    }


}
