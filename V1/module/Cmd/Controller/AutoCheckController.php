<?php

namespace Cmd\Controller;

use Deyi\BaseController;
use Deyi\GetCacheData\CouponCache;
use Deyi\Coupon\Coupon;
use Deyi\GetCacheData\PlaceCache;
use Deyi\JsonResponse;
use Deyi\Account\OrganizerAccount;
use Deyi\OrderAction\OrderBack;
use Deyi\OrderAction\OrderExcerciseBack;
use Deyi\OutPut;
use Deyi\Request;
use Deyi\Social\SendSocialMessage;
use Deyi\WeiXinFun;
use Deyi\WriteLog;
use library\Fun\M;
use Zend\Mvc\Controller\AbstractActionController;


class AutoCheckController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;
    use OrderBack;

    /*
     * 本Controller存放各种异常数据检测程序
     *
     * */
    protected $msg = '';

    //检查程序启动
    public function autoCheckAction()
    {


//        $this->tmp(); //临时
        $this->checkOrder();
        $this->checkEvent();
        $this->checkBase();
        $this->checkGameInfo();
        $this->checkGameBack();
        $this->checkBuyNumber();
        $this->checkDifferentPrice();
        $this->checkPriceNull();
        $this->checkKidsplayBack();
        $this->checkOrderStatus();
        $this->checkAultAndChild();
        $this->checkVip();
        if ($this->msg) {
            $this->errorLog($this->msg);
        }


    }

    public function tmp()
    {
        $db = $this->_getAdapter();

        $res = $db->query("SELECT * FROM `play_user` where uid=10007", array())->current();
        if ($res->token != '3348cb190d08743a3fa00094dd3f1dc3') {
            $this->msg .= "临时监控结果: uid为10007的用户token失效,现在为{$res->token}\n";
        }
    }

    //检查活动报名
    public function checkBase()
    {
        $db = $this->_getAdapter();

        $res = $db->query("SELECT * from (
SELECT join_number,play_excercise_base.id,(
	SELECT
IFNULL(sum(play_excercise_event.join_number),0) as join_number
	FROM
		play_excercise_event
	WHERE
	play_excercise_event.bid=play_excercise_base.id
) as  all_event_number  from  play_excercise_base
) as aa WHERE aa.join_number!=all_event_number
", array())->toArray();
        if (!empty($res)) {
            foreach ($res as $v) {
                $this->msg .= "发现异常活动,活动id:{$v['id']},当前报名人数为:{$v['join_number']},实际当前订单报名人数为:{$v['all_event_number']}\n";
            }
        }

    }

    //检查活动场次报名
    public function checkEvent()
    {
        $db = $this->_getAdapter();

        $res = $db->query("
SELECT * from ( select e.id, e.join_number,e.bid,(
SELECT
	IFNULL(  SUM(play_excercise_price.person) ,0)  as pepo
FROM
	play_order_info
LEFT JOIN play_excercise_code ON play_excercise_code.order_sn= play_order_info.order_sn
LEFT JOIN play_excercise_price ON play_excercise_price.id=play_excercise_code.pid
WHERE 
play_order_info.order_status = 1
AND play_order_info.order_type = 3
AND play_excercise_code.`status` in(0,1)
and play_order_info.coupon_id=e.id
AND play_excercise_price.is_other!=1
) as  now_join   from  play_excercise_event as e ) as aa where aa.join_number!=now_join
", array())->toArray();

        if (!empty($res)) {
            foreach ($res as $v) {
                $this->msg .= "发现异常活动场次,活动id:{$v['bid']},场次id:{$v['id']},当前报名人数为:{$v['join_number']},实际当前订单报名人数为:{$v['now_join']}\n";
            }

        }
    }

    //检查异常活动订单
    public function checkOrder()
    {
        $db = $this->_getAdapter();

        $res = $db->query("
SELECT
	*
FROM
	(
		SELECT
			play_order_info.order_sn,coupon_id as eid,bid,
			(
				SELECT
					SUM(play_excercise_price.person)
				FROM
					play_order_info AS b
				LEFT JOIN play_excercise_code ON play_excercise_code.order_sn = b.order_sn
				LEFT JOIN play_excercise_price ON play_excercise_price.id = play_excercise_code.pid
				WHERE
				 b.order_status = 1
				AND b.order_type = 3
				AND play_excercise_code.`status` IN (0, 1)
				AND b.order_sn = play_order_info.order_sn
				AND play_excercise_price.is_other!=1
			) AS pepo, -- 目前应该存在的出行人数 
			(
				SELECT
					count(*)
				FROM
					play_order_insure
				WHERE
					order_sn = play_order_info.order_sn
			) AS now_pepo  -- 实际存在的出行人数
		FROM
			play_order_info
		WHERE
		 play_order_info.order_status = 1
		AND play_order_info.order_type = 3
	) as aaa
where
	aaa.pepo != aaa.now_pepo
", array())->toArray();

        if (!empty($res)) {
            foreach ($res as $v) {
                $this->msg .= "发现出行人数异常订单,订单id:{$v['order_sn']},活动id:{$v['bid']},场次id: {$v['eid']},当前出行人数为:{$v['pepo']},实际当前订单出行人数为:{$v['now_pepo']}\n";
            }
        }


    }

    //检查异常套系
    public function checkGameInfo()
    {
        $db = $this->_getAdapter();

        $res = $db->query("
SELECT
	*
FROM
	(
		SELECT
			id,
			gid,
			total_num,
			buy,
			(
				SELECT
					sum(
						buy_number - (backing_number + back_number)
					)
				FROM
					play_order_info
				WHERE
					order_status = 1
				AND order_type = 2
				AND buy_number > (backing_number + back_number)
				AND play_order_info.bid = play_game_info.id
			) AS buy_all
		FROM
			play_game_info
	) AS a
WHERE
	a.buy != a.buy_all
", array())->toArray();

        if (!empty($res)) {
            foreach ($res as $v) {
                $this->msg .= "发现购买数异常套系,套系id:{$v['id']},商品id:{$v['gid']},当前购买数{$v['buy']},实际购买数{$v['buy_all']}\n";
            }
        }


    }

    //检查商品退订和退订中数量不符
    public function checkGameBack()
    {
        $db = $this->_getAdapter();

        $res = $db->query("
        SELECT
	*
FROM
	(
		SELECT
			play_order_info.order_sn,
			back_number,
			backing_number,
			sum(

				IF (
					play_coupon_code.`status` = 2,
					-- 2已退款  3 退款中
					1,
					0
				)
			) AS back_num,
			sum(

				IF (
					play_coupon_code.`status` = 3,
					-- 2已退款  3 退款中
					1,
					0
				)
			) AS backing_num
		FROM
			play_order_info
		LEFT JOIN play_coupon_code ON play_coupon_code.order_sn = play_order_info.order_sn
		WHERE
			play_order_info.order_type = 2
		GROUP BY
			play_order_info.order_sn
	) AS a
WHERE
	a.back_number != a.back_num
OR a.backing_number != a.backing_num
", array())->toArray();

        if (!empty($res)) {
            foreach ($res as $v) {
                $this->msg .= "发现商品退订数量异常订单,订单id:{$v['order_sn']}" . print_r($v, true) . " 订单号 已退款,退款中, 实际已退款,实际退款中 \n";
            }
        }


    }

    //检查活动退订和退订中数量不符
    public function checkKidsplayBack()
    {
        $db = $this->_getAdapter();

        $res = $db->query("
        SELECT
	*
FROM
	(
		SELECT
			play_order_info.order_sn,
			back_number,
			backing_number,
			sum(

				IF (
					play_excercise_code.`status` = 2,
					-- 2已退款  3 退款中
					1,
					0
				)
			) AS back_num,
			sum(

				IF (
					play_excercise_code.`status` = 3,
					-- 2已退款  3 退款中
					1,
					0
				)
			) AS backing_num
		FROM
			play_order_info
		LEFT JOIN play_excercise_code ON play_excercise_code.order_sn = play_order_info.order_sn
		WHERE
			play_order_info.order_type = 3
		GROUP BY
			play_order_info.order_sn
	) AS a
WHERE
	a.back_number != a.back_num
OR a.backing_number != a.backing_num
", array())->toArray();

        if (!empty($res)) {
            foreach ($res as $v) {
                $this->msg .= "发现商品退订数量异常订单,订单id:{$v['order_sn']}" . print_r($v, true) . " 订单号 已退款,退款中, 实际已退款,实际退款中 \n";
            }
        }


    }

    //检查订单购买数量 (商品和活动)
    public function checkOrderStatus()
    {
        $db = $this->_getAdapter();

        $res = $db->query("
        select * from play_order_info WHERE buy_number<(use_number+backing_number+back_number) AND order_status=1 AND  order_type>1
", array())->toArray();

        if (!empty($res)) {
            foreach ($res as $v) {
                $this->msg .= "发现购买数异常订单,订单类型:{$v['order_type']},订单id:{$v['order_sn']},购买数:{$v['buy_number']}使用数:{$v['use_number']}退订中数量:{$v['backing_number']}已退订数量{$v['back_number']} \n";
            }
        }


    }

    //大人小孩报名数加起来不正确 虚拟不正确
    public function checkAultAndChild()
    {
        $db = $this->_getAdapter();

        $res = $db->query("SELECT * FROM `play_excercise_base` WHERE join_number!=(join_ault+join_child);", array())->toArray();
        if (!empty($res)) {
            foreach ($res as $v) {
                $this->msg .= "发现异大人小孩报名数,活动id:{$v['id']},当前报名人数为:{$v['join_number']},大人:{$v['join_ault']},小孩:{$v['join_child']}\n";
            }
        }
        $res = $db->query("SELECT * FROM `play_excercise_event` where join_number!=(join_ault+join_child);", array())->toArray();
        if (!empty($res)) {
            foreach ($res as $v) {
                $this->msg .= "发现异大人小孩报名数,场次id:{$v['id']},当前报名人数为:{$v['join_number']},大人:{$v['join_ault']},小孩:{$v['join_child']}\n";
            }
        }


        $res = $db->query("
SELECT * from (
SELECT play_excercise_base.id,vir_number,(
	SELECT
IFNULL(sum(play_excercise_event.vir_number),0) as vir_number
	FROM
		play_excercise_event
	WHERE
	play_excercise_event.bid=play_excercise_base.id
) as  all_vir_number  from  play_excercise_base
) as aa WHERE aa.all_vir_number!=vir_number or aa.vir_number!=aa.all_vir_number;", array())->toArray();
        if (!empty($res)) {
            foreach ($res as $v) {
                $this->msg .= "发现异常虚拟票,活动id:{$v['id']},当前vir_number:{$v['vir_number']},实际:{$v['all_vir_number']}\n";
            }
        }
    }

    public function checkBuyNumber()
    {
        $db = $this->_getAdapter();

        $res = $db->query("
        SELECT * from play_game_info where total_num <buy
", array())->toArray();

        if (!empty($res)) {
            foreach ($res as $v) {
                if ($v['id'] != 11914) {
                $this->msg .= "发现超出购买数套系,套系id:{$v['id']},商品id:{$v['gid']},总数:{$v['total_num']},购买数:{$v['buy']}\n";
                }
            }
        }

        $res = $db->query("
        SELECT * from play_excercise_event WHERE join_number>most_number
", array())->toArray();

        if (!empty($res)) {
            foreach ($res as $v) {
                if ($v['id'] != 387) {
                    $this->msg .= "发现超出购买数收费项,收费项id:{$v['id']},活动id:{$v['bid']},最多数量:{$v['most_number']},购买数:{$v['join_number']}\n";
                }
            }
        }

    }
    //检查 金额相关

    //处理出行人异常的订单
    public function HandleOrderAction()
    {
        $db = $this->_getAdapter();

        $res = $db->query("
        SELECT
	*
FROM
	(
		SELECT
			play_order_info.order_sn,coupon_id as eid,
			(
				SELECT
					SUM(play_excercise_price.person)
				FROM
					play_order_info AS b
				LEFT JOIN play_excercise_code ON play_excercise_code.order_sn = b.order_sn
				LEFT JOIN play_excercise_price ON play_excercise_price.id = play_excercise_code.pid
				WHERE
				 b.order_status = 1
				AND b.order_type = 3
				AND play_excercise_code.`status` IN (0, 1)
				AND b.order_sn = play_order_info.order_sn
				AND play_excercise_price.is_other!=1
			) AS pepo, -- 目前应该存在的出行人数 
			(
				SELECT
					count(*)
				FROM
					play_order_insure
				WHERE
					order_sn = play_order_info.order_sn
			) AS now_pepo  -- 实际存在的出行人数
		FROM
			play_order_info
		WHERE
		 play_order_info.order_status = 1
		AND play_order_info.order_type = 3
	) as aaa
where
	aaa.pepo != aaa.now_pepo
	", array());


        foreach ($res as $v) {
            echo "处理 {$v->order_sn} \n";
            if ($v->pepo > $v->now_pepo) {
                $num = ($v->pepo - $v->now_pepo);
                echo '缺少' . $num . '个';
                //增加
                $insure_data = '';
                $event_data = $this->_getPlayExcerciseEventTable()->get(array('id' => $v->eid));
                for ($i = 0; $i < $num; ++$i) {

                    $insure_data .= "('{$v->order_sn}','{$v->eid}','','','','',1,'','','0',0,'{$event_data->insurance_id}'),";
                }
                $insure_data = substr($insure_data, 0, -1);
                if (!empty($insure_data)) {
                    $stmt = $db->query('INSERT INTO play_order_insure (order_sn,coupon_id,`name`,sex,birth,id_num,insure_company_id,insure_sn,baoyou_sn,insure_status,associates_id,product_code) VALUES ' . $insure_data);
                    $stmt->execute($stmt)->count();
                }


            } else {
                $num = $v->now_pepo - $v->pepo;
                //echo '多出 ' . $num . '个';
                echo "delete from play_order_insure where order_sn={$v->order_sn} ORDER BY  id_num DESC  limit {$num} ;\n";

//                $db->query('SET SQL_SAFE_UPDATES = 0;',array());
//                $a= $db->query("delete from play_order_insure where order_sn=? ORDER BY  id_num DESC  limit ?",array($v->order_sn,$num))->current();
//                echo '成功删除:';
            }
        }

//var_dump($res->toArray());

        exit;
    }

    //处理套系价格 跟 合同价格不一致的情况 暂时排除 酒店类商品
    public function checkDifferentPrice()
    {
        $Adapter = $this->_getAdapter();

        $res = $Adapter->query("SELECT
	play_game_info.id,
	play_game_info.price,
	play_game_info.money,
	play_game_info.account_money,
	play_contract_link_price.price as c_price,
	play_contract_link_price.money as c_money,
	play_contract_link_price.account_money as c_account_money,
    play_game_info.gid
FROM
	play_game_info
INNER JOIN play_contract_link_price ON play_contract_link_price.id = play_game_info.contract_price_id
INNER JOIN play_organizer_game ON play_organizer_game.id = play_game_info.gid
WHERE
play_game_info.contract_price_id > 0 AND (
	play_game_info.price != play_contract_link_price.price
OR play_game_info.money != play_contract_link_price.money
OR play_game_info.account_money != play_contract_link_price.account_money)
    AND play_organizer_game.need_use_time != 2", array());

        if ($res->count() > 0) {
            foreach ($res as $v) {
                $this->msg .= "发现商品的价格与合同价格不一致 商品id:{$v['gid']},  套系id: {$v['id']} 套系价格: {$v['price']} 套系原价: {$v['money']} 套系结算价: {$v['account_money']} 合同价格: {$v['c_price']} 合同原价: {$v['c_money']} 合同结算价: {$v['c_account_money']}\n";
            }
        }

    }

    //商品 活动的售卖价为0的
    public function checkPriceNull()
    {

        $Adapter = $this->_getAdapter();

        //已排除的价格为0的套系
        $game_info_ids = array(
            209, 337, 338, 339, 340, 341, 718, 3247, 3248, 3272, 3273, 3420, 3494, 3495,
            3496, 3538, 3563, 3565, 3582, 3631, 3632, 3633, 3636, 3693, 3694, 3727, 3739,
            3780, 3782, 3804, 3805, 3850, 3872, 3873, 3878, 3890, 3918, 3919, 3920, 3921,
            4026, 4041, 4083, 4107, 4221, 4253, 4254, 4255, 4284, 4327, 4336, 4337, 4339,
            4340, 4341, 4356, 4357, 4358, 4359, 4360, 4361, 4362, 4363, 4364, 4365, 4366,
            4367, 4368, 4369, 4370, 4406, 4484, 4552, 4567, 4569, 4743, 4744, 4890, 4960,
            4965, 4992, 5060, 5176, 5645, 5647, 13011, 13060
        );

        //已排除的价格为0的活动收费项
        $activity_price_ids = array(137, 157, 158, 201, 202, 212);

        //商品
        $res = $Adapter->query("SELECT
	play_game_info.id,
	play_game_info.gid
FROM
	play_game_info
WHERE price = 0", array());

        if ($res->count() > 0) {
            foreach ($res as $v) {
                if (!in_array($v['id'], $game_info_ids)) {
                    $this->msg .= "发现商品的价格为0 商品id:{$v['gid']},  套系id: {$v['id']}\n";
                }
            }
        }

        //活动
        $res = $Adapter->query("SELECT
	play_excercise_price.id,
	play_excercise_price.price_name,
	play_excercise_price.eid,
	play_excercise_price.bid
FROM
	play_excercise_price
WHERE price = 0", array());

        if ($res->count() > 0) {
            foreach ($res as $v) {
                if (!in_array($v['id'], $activity_price_ids)) {
                    $this->msg .= "发现活动的价格为0 活动id:{$v['bid']},  场次id: {$v['eid']}, 活动价格名称: {$v['price_name']} 活动价格id: {$v['id']}\n";
                }
            }
        }


    }

    //会员监控
    public function checkVip(){
        $db=M::getAdapter();
//        $res=$db->query("SELECT
//	uid,
//	flow_money,
//	money_service_id
//FROM
//	play_account_log
//WHERE
//	play_account_log.action_type = 1
//AND play_account_log.`status` = 1
//AND play_account_log.flow_money >= 688 AND dateline>1480124750",array());
//
//        foreach ($res as $v){
//            $this->msg .= "充值vip uid:{$v->uid}  ,金额 {$v->flow_money}, 套餐id {$v->money_service_id}\n";
//        }

        //监控会员亲子游个数与实际未使用票券数量是否相等，不相等则为异常
        $res=$db->query("SELECT
	*
FROM
	(
		SELECT
			play_member.member_user_id,
			count(play_cashcoupon_user_link.id) AS free_coupon_count,
			play_member.member_free_coupon_count_now
		FROM
			play_cashcoupon_user_link
		LEFT JOIN play_member ON play_member.member_user_id = play_cashcoupon_user_link.uid
		WHERE
			play_cashcoupon_user_link.pay_time = 0 AND play_cashcoupon_user_link.cid = 0
		GROUP BY
			play_member.member_user_id
	) as aa
WHERE
	free_coupon_count != member_free_coupon_count_now",array());

        foreach ($res as $v){
            $this->msg .= "监控会员亲子游个数与实际未使用票券数量是否相等，不相等则为异常 member_user_id:{$v->member_user_id}  ,free_coupon_count {$v->free_coupon_count}, member_free_coupon_now {$v->member_free_coupon_now}\n";
        }


        //监控亲子游报名人数与实际亲子游报名人数是否相等，不相等则为异常
        $res=$db->query("SELECT * FROM
	(
		SELECT play_excercise_price.eid, count(play_excercise_code.id) as real_free_coupon_count, play_excercise_price.free_coupon_join_count FROM play_excercise_code
		LEFT JOIN play_excercise_price ON play_excercise_code.pid = play_excercise_price.id
		LEFT JOIN play_order_info      ON play_order_info.order_sn= play_excercise_code.order_sn
		WHERE play_excercise_code.use_free_coupon > 0
		AND play_order_info.order_status = 1 AND play_excercise_code.status in (0,1)
		GROUP BY play_excercise_price.id
	) as aa
WHERE
	real_free_coupon_count != free_coupon_join_count",array());

        foreach ($res as $v){
            $this->msg .= "监控亲子游报名人数与实际亲子游报名人数是否相等，不相等则为异常 data: ".print_r($v,true)."\n";
        }


    }
}
