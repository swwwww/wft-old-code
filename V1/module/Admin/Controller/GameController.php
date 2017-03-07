<?php

namespace Admin\Controller;

use Deyi\GetCacheData\CityCache;
use Deyi\JsonResponse;
use Deyi\Paginator;
use Deyi\OutPut;
use Zend\View\Model\ViewModel;

class GameController extends BasisController
{
    use JsonResponse;

    public function indexAction() {

        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;

        $city = $this->chooseCity();//根据管理员 或者编辑的城市取出数据

        $like = $this->getQuery('good_name', '');
        $business = $this->getQuery('business', '');
        $organizer_id = $this->getQuery('oid');
        $place_id = $this->getQuery('pid', 0);

        $together = (int)$this->getQuery('together', 0); // 是否合作
        $lately = (int)$this->getQuery('lately', 0); // 最近结束售卖
        $good_status = (int)$this->getQuery('good_status', 0); //商品状态
        $group = (int)$this->getQuery('group', 0); //是否同玩

        $order_change = (int)$this->getQuery('order_change', 0); //排序

        $where_str = ' 1=1 ';
        $time = time();
        $time86400 = $time + 86400;
        if ($order_change) {
            if ($order_change == 1) {
                $order_str = " play_organizer_game.buy_num desc, play_organizer_game.dateline desc ";
            } else {
                $order_str = " play_organizer_game.buy_num asc, play_organizer_game.dateline desc ";
            }
        } else {
            $order_str = " play_organizer_game.order desc, play_organizer_game.dateline desc ";
        }

        //放出删除的
        $heiWu = $this->getQuery('heiwu');
        if ($heiWu == 1) {
            $where_str .= " and play_organizer_game.status >= -1 ";
        } else {
            $where_str .= " and (play_organizer_game.status >= 0 or play_organizer_game.status = -2 ) ";
        }

        //默认条件
        if($city){
            $where_str .= " and play_organizer_game.city = '{$city}' ";
        }

        //是否合作
        if ($together) {
            $where_str .= " and play_organizer_game.is_together = {$together} ";
        }

        $week = $time + 3600*24*7;
        if($lately){
            $week_str = " having downtime < {$week} ";
            $week_str .= " and downtime > {$time} ";
            $join_mothed = ' INNER ';
        }else{
            $join_mothed = ' LEFT ';
        }

        //是否同玩
        if ($group) {
            if ($group == 2) {
                $where_str .= " and (play_organizer_game.g_buy = 0 OR (play_organizer_game.g_buy = 1 AND play_organizer_game.down_time < {$time86400})) ";
            } else {
                $where_str .= " and (play_organizer_game.g_buy = 1 and (play_organizer_game.down_time > {$time86400})) ";
            }

        }

        //商品状态 未发布 未开始 在售 停止售卖 停止使用
        if ($good_status) {
            if ($good_status == 1) {
                $where_str .= " and play_organizer_game.status = 0 ";
            } elseif ($good_status == 2) {// 未开始
                $where_str .= " and play_organizer_game.status = 1 and play_organizer_game.up_time > {$time} ";
            } elseif ($good_status == 3) {// 在售卖
                $where_str .= " and play_organizer_game.status = 1 and play_organizer_game.up_time < {$time} and play_organizer_game.down_time  > {$time} ";
            } elseif ($good_status == 4) {// 停止售卖
                $where_str .= "  and play_organizer_game.status = 1 and play_organizer_game.foot_time > {$time} and play_organizer_game.down_time < {$time} ";

            } elseif ($good_status == 5) {// 停止使用
                $where_str .= " and play_organizer_game.status = 1 and play_organizer_game.foot_time < {$time} and play_organizer_game.down_time < {$time} ";
            }
        }

        //名称搜索
        if ($like) {
            $where_str .= " and play_organizer_game.title like '%".$like."%' ";
        }

        // 商家的商品
        if ($organizer_id) {
            $where_str .= " and play_organizer_game.organizer_id = {$organizer_id} ";
        }

        //经办人
        if ($business) {
            $where_str .= " and play_admin.admin_name = '{$business}' ";
        }

        // 游玩地下的商品
        if ($place_id) {
            $game_ids = $this->_getPlayGameInfoTable()->fetchAll(array('shop_id' => $place_id));
            $ids = array();
            if ($game_ids->count()) {
                foreach ($game_ids as $game) {
                    if(!in_array($game->gid, $ids)) {
                        $ids[] = $game->gid;
                    }
                }
                if($ids){
                    $m = implode(',',$ids);
                }else{
                    $m = 0;
                }
                $where_str .= " and play_organizer_game.id in ({$m}) ";
            }
        }

        //因需求不要求判断是否售空，省去and play_game_info.total_num > play_game_info.buy
        $sql = "SELECT
    play_organizer_game . *, play_admin.admin_name AS business,downtime
FROM
    play_organizer_game
        LEFT JOIN
    play_contracts ON play_contracts.id = play_organizer_game.contract_id
        LEFT JOIN
    play_admin ON play_admin.id = play_contracts.business_id
        {$join_mothed} JOIN
    (select
        min(down_time) as downtime, gid
    from
        play_game_info
    where
        status = 1
            AND play_game_info.end_time > ?
            AND play_game_info.down_time > ?
    group by gid {$week_str} ) as pgi ON pgi.gid = play_organizer_game.id
where
    {$where_str}
ORDER BY {$order_str}
LIMIT {$pageSum} OFFSET {$start}";

        $db = $this->_getAdapter();
        $data = $db->query($sql,array($time,$time));

        $sql = "SELECT
    COUNT(*) as c
FROM
    play_organizer_game
        LEFT JOIN
    play_contracts ON play_contracts.id = play_organizer_game.contract_id
        LEFT JOIN
    play_admin ON play_admin.id = play_contracts.business_id
        {$join_mothed} join
    (select
        min(down_time) as downtime, gid
    from
        play_game_info
    where
        status = 1
            AND play_game_info.end_time > ?
            AND play_game_info.down_time > ?
    group by gid {$week_str} ) as pgi ON pgi.gid = play_organizer_game.id
where
    {$where_str}";

        //获得总数量
        $count = $db->query($sql,array($time,$time))->current();
        $count = ($count->c);

        //创建分页
        $url = '/wftadlogin/game';
        $pagination = new Paginator($page, $count, $pageSum, $url);

        //查询是否有 可用的现金券
        $cashCoupon = $this->_getCashCouponTable()->fetchAll(array('city'=>$this->getAdminCity(),'is_close' => 0, 'residue > 0', 'status = 1'));

        return array(
            'data' => $data,
            'pageData' => $pagination->getHtml(),
            'city' => $this->getAllCities(),
            'filtercity' => CityCache::getFilterCity($city),
            'cashCoupon' => $cashCoupon,
        );
    }

    //删除商品
    public function deleteAction() {
        $id = (int)$this->getQuery('gid');
        $status = $this->_getPlayOrganizerGameTable()->update(array('status' => -1, 'g_buy' => 0), array('id' => $id));
        //操作记录
        if ($status) {
            $this->adminLog('删除商品', 'good', $id);
        }
        return $this->_Goto($status ? '成功' : '失败');
    }

    //单个商品咨询列表
    public function consultAction() {

        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page-1)*$pageSum;
        $id = (int)$this->getQuery('gid');
        $type = (int)$this->getQuery('type', 0);

        $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $id));

        if (!$goodData) {
            return $this->_Goto('该商品不存在');
        }

        if ($type && !in_array($type, array(1, 2))) {
            return $this->_Goto('状态不正确');
        }

        $where['object_data.object_id'] = $id;

        if ($type) {
            if ($type == 1) {
                $where['reply.uid'] = array('$exists' => true);
            }

            if ($type == 2) {
                $where['reply.uid'] = array('$exists' => false);
            }
        }

        $order = array(
            'dateline' => -1,
        );

        $cursor = $this->_getMdbConsultPost()->find($where)->limit($pageSum)->skip($start)->sort($order);
        $count = $this->_getMdbConsultPost()->find($where)->count();

        //创建分页
        $url = '/wftadlogin/game/consult';
        $paging = new Paginator($page, $count, $pageSum, $url);

        return array(
            'goodData' => $goodData,
            'data' => $cursor,
            'pageData' => $paging->getHtml(),
        );
    }

    //单个商品评论列表
    public function postAction() {

        $id = (int)$this->getQuery('gid', 0); //商品id
        $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $id));
        if (!$goodData) {
            return $this->_Goto('非法操作');
        }

        $page = (int)$this->getQuery('p', 1);
        $start_time = $this->getQuery('start_time');
        $end_time = $this->getQuery('end_time');
        $accept = (int)$this->getQuery('accept');
        $uid = (int)$this->getQuery('uid');
        $username = $this->getQuery('username');

        $pageSum = 10;
        $start = ($page -1) * $pageSum;

        $order = array(
            'status' => -1,
        );

        $where = array(
            'msg_type' => 2,
            'object_data.object_id' => $id,
        );

        if ($start_time && $end_time) {
            $open = (int)strtotime($start_time);
            $end = (int)strtotime($end_time) + 86400;

            if ($end > $start) {
                $where['dateline'] = array('$gt' => $open, '$lt' => $end);
            }

        }

        if ($accept) {
            $where['accept'] = $accept;
        }

        if ($uid) {
            $where['uid'] = $uid;
        }

        if ($username) {
            $where['username'] = $username;
        }

        $data = $this->_getMdbSocialCircleMsg()->find($where)->limit($pageSum)->skip($start)->sort($order);
        $count = $this->_getMdbSocialCircleMsg()->find($where)->count();

        //创建分页
        $url = '/wftadlogin/game/post';
        $paging = new Paginator($page, $count, $pageSum, $url);

        $listData = array();
        foreach ($data as $res) {
            //现金 现金券
            $id = (string)$res['_id'];
            $rebate_sql = "SELECT SUM(play_account_log.flow_money) AS rebate_money FROM play_account_log WHERE action_type_id in (11, 6) AND object_id = '{$id}'";
            $cash_sql = "SELECT  SUM(play_cashcoupon_user_link.price) AS cash_money FROM play_cashcoupon_user_link WHERE adminid > 0 AND get_type in (2, 9) AND get_object_id = '{$id}'";
            $rebate_data = $this->query($rebate_sql)->current();
            $cash_data = $this->query($cash_sql)->current();
            //积分
            $integral_data = $this->_getPlayIntegralTable()->get(array('object_id' => $id, 'type' => 4, 'uid' => $res['uid']));

            $listData[] = array(
                '_id' => (string)$res['_id'],
                'dateline' => $res['dateline'],
                'uid' => $res['uid'],
                'username' => $res['username'],
                'msg' => $res['msg'],
                'star_num' => $res['star_num'],
                'replay_number' => $res['replay_number'],
                'like_number' => $res['like_number'],
                'accept' => $res['accept'],
                'status' => $res['status'],
                'rebate_money' => $rebate_data['rebate_money'],
                'cash_money' => $cash_data['cash_money'],
                'integral' => $integral_data ? $integral_data->total_score : 0,//$integral['status'],
            );
        }

        $vm = new viewModel(
            array(
                'goodData' => $goodData,
                'data' => $listData,
                'pageData' => $paging->getHtml(),
            )
        );
        return $vm;
    }

    //获取商品名称
    public function getGameAction() {
        $k = $this->getQuery('k');
        if ($k) {
            $where = array(
                'city = ?' => $_COOKIE['city'],
                ' title like ? or id = ? ' => array('%'.$k.'%', $k),
            );
            if($this->getAdminCity()==1){
                unset($where['']);
            }
            $data = $this->_getPlayOrganizerGameTable()->fetchLimit(0, 5, array(), $where, array());
            $res = array();
            if ($data->count()) {
                foreach ($data as $val) {
                    $res[] = array(
                        'sid' => $val->id,
                        'name' => $val->title,
                    );
                }
            }
            return $this->jsonResponsePage(array('status' => 0, 'data' => $res));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'data' => array()));
        }
    }

    //单个商品的订单列表
    public function orderAction() {

        $id = (int)$this->getQuery('good_id', 0);
        $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $id));

        if (!$goodData) {
            return $this->_Goto('异常');
        }

        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;
        $order = "play_order_info.order_sn DESC";

        $where = $this->_getWhere($id);
        $sql = "SELECT
	play_coupon_code.*,
	play_order_info.buy_phone,
	play_order_info.buy_name,
	play_order_info.user_id,
	play_order_info.buy_address,
	play_order_info.coupon_name,
	play_order_info.dateline,
	play_order_info.username,
	play_order_info.coupon_unit_price,
    play_order_info_game.type_name
FROM
	play_coupon_code
LEFT JOIN play_order_info ON  play_coupon_code.order_sn = play_order_info.order_sn
LEFT JOIN play_order_info_game ON play_order_info_game.order_sn = play_order_info.order_sn
LEFT JOIN play_order_insure ON play_order_insure.order_sn = play_order_info.order_sn
WHERE $where
GROUP BY play_coupon_code.id
ORDER BY $order";

        $sql_list = $sql." LIMIT
{$start}, {$pageSum}";
        $data = $this->query($sql_list);
        $count = $this->query($sql)->count();

        $url = '/wftadlogin/game/order';
        $paging = new Paginator($page, $count, $pageSum, $url);

        return array(
            'goodData' => $goodData,
            'data' => $data,
            'pageData' => $paging->getHtml(),
            'code_status' => $this->_getConfig()['coupon_code_status'],
        );
    }

    //商品的福利设置 现金券 积分 现金返利
    public function welfareAction() {

        $id = (int)$this->getQuery('gid', 0); //游玩地id
        $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $id));
        if (!$goodData) {
            return $this->_Goto('非法操作');
        }

        //积分
        $welfareData = array();
        $integral = $this->_getPlayWelfareIntegralTable()->fetchAll(array('link_id' => $id, 'welfare_type in (1, 2)', 'status > 0'));
        foreach($integral as $w) {
            $welfareData[] = array(
                'id' => $w->id,
                'object' => ($w->welfare_type == 1) ? '分享' : '评论',
                'double' => $w->double,
                'limit' => $w->limit_score,
                'total' => $w->total_score,
            );
        }

        //现金返利
        $rebateData = array();
        $rebate = $this->_getPlayWelfareRebateTable()->fetchAll(array('from_type'=>1,'status >= ?' => 1));

        foreach($rebate as $r) {
            $rebateData[] = array(
                'id' => $r->id,
                'object' => ($r->welfare_type == 1) ? '分享' : '评论',
                'double' => $r->double,
                'limit' => $r->limit_score,
                'total' => $r->total_score,
            );
        }

        //现金券
        $cashCouponData = array();
        $cashCoupon = $this->_getPlayWelfareCashTable()->fetchAll(array('status >= ?' => 1));

        foreach($cashCoupon as $c) {
            $cashCouponData[] = array(
                'id' => $c->id,
                'object' => ($c->welfare_type == 1) ? '分享' : '评论',
                'double' => $c->double,
                'limit' => $c->limit_score,
                'total' => $c->total_score,
            );
        }

        $vm = new viewModel(
            array(
                'welfareData' => $welfareData,
                'rebateData' => $rebateData,
                'cashCouponData' => $cashCouponData,
            )
        );

        return $vm;
    }


    public function hiddenAction() {
        $type = $this->getQuery('type');
        $gid = $this->getQuery('gid');

        if (!in_array($type, array(1, 2))) {
            return $this->_Goto('非法操作');
        }

        if ($type == 1) {
            $tip = -2;
        } else {
            $tip = 0;
        }

        $status = $this->_getPlayOrganizerGameTable()->update(array('status' => $tip), array('id' => $gid));

        return $this->_Goto($status ?  '成功' : '失败');

    }

    /**
     * 单个商品的
     * @param $id
     * @return string
     */
    private function _getWhere($id) {

        $user_name = $this->getQuery('user_name', null);
        $user_phone = $this->getQuery('user_phone', null);
        $buy_start = $this->getQuery('buy_start', null);
        $buy_end = $this->getQuery('buy_end', null);
        $check_start = $this->getQuery('check_start', null);
        $check_end = $this->getQuery('check_end', null);
        $code_status = $this->getQuery('code_status', null);

        $address_in = $this->getQuery('address_in', 0);
        $people_in = $this->getQuery('people_in', 0);



        $where = "play_order_info.order_type = 2 AND play_order_info.pay_status > 1 AND play_order_info.order_status = 1 AND play_order_info.coupon_id = ". $id;

        //用户名称
        if ($user_name) {
            $where = $where. " AND play_order_info.username like '%".$user_name."%'";
        }

        //用户手机
        if ($user_phone) {
            $where = $where. " AND play_order_info.buy_phone like '%".$user_phone."%'";
        }

        if ($buy_start && $buy_end && strtotime($buy_start) > strtotime($buy_end)) {
            return $this->_Goto('购买时间出错');
        }

        if ($check_start && $check_end && strtotime($check_start) > strtotime($check_end)) {
            return $this->_Goto('验证时间出错');
        }

        //购买时间
        if ($buy_start) {
            $buy_start = strtotime($buy_start);
            $where = $where. " AND play_order_info.dateline > ".$buy_start;
        }

        if ($buy_end) {
            $buy_end = strtotime($buy_end) + 86400;
            $where = $where. " AND play_order_info.dateline < ".$buy_end;
        }

        //验证时间
        if ($check_start) {
            $check_start = strtotime($check_start);
            $where = $where. " AND play_coupon_code.use_datetime > ".$check_start;
        }

        if ($check_end) {
            $check_end = strtotime($check_end) + 86400;
            $where = $where. " AND play_coupon_code.use_datetime < ". $check_end;
        }

        if ($code_status) {
            $code_status = intval($code_status) - 1;
            $where = $where. " AND play_coupon_code.status = ". $code_status;
        }

        if ($people_in) {
            $people_in = intval($people_in  -1);
            $where = $where. " AND play_order_insure.insure_status = ". $people_in;
        }

        $city = $this->getBackCity();

        if ($city) {
            $where = $where. " AND play_order_info.order_city = '{$city}'";
        }

        if ($address_in) {
            if ($address_in == 1) {
                $where = $where. " AND play_order_info.buy_address IS NOT NULL";
            } elseif ($address_in == 2) {
                $where = $where. " AND play_order_info.buy_address IS NULL";
            }

        }

        return $where;

    }

    //单个商品的订单列表
    public function outDataAction() {
        $id = (int)$this->getQuery('good_id', 0);
        $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $id));

        if (!$goodData) {
            return $this->_Goto('异常');
        }

        $where = $this->_getWhere($id);

        $sql_list = "SELECT
	play_coupon_code.*,
	play_order_info.coupon_name,
	play_order_info.dateline,
	play_order_info.coupon_unit_price,
	play_order_info.trade_no,
	play_order_info.account_type,
	play_order_info.shop_name,
	play_order_info.username,
    play_order_info.user_id,
    play_order_info.coupon_id,
    play_order_info.buy_phone,
    play_order_info.buy_name,
    play_order_otherdata.message,
    play_game_info.start_time,
    play_game_info.end_time,
    play_game_info.price_name,
    play_order_info.buy_address
FROM
	play_coupon_code
LEFT JOIN play_order_info ON  play_coupon_code.order_sn = play_order_info.order_sn
LEFT JOIN play_game_info ON  play_game_info.id = play_order_info.bid
LEFT JOIN play_order_otherdata ON play_order_otherdata.order_sn = play_order_info.order_sn
LEFT JOIN play_order_insure ON play_order_insure.order_sn = play_order_info.order_sn
WHERE $where
GROUP BY play_coupon_code.id";

        $data = $this->query($sql_list);

        $sql_count = "SELECT
	play_coupon_code.id
FROM
	play_coupon_code
LEFT JOIN play_order_info ON  play_coupon_code.order_sn = play_order_info.order_sn
LEFT JOIN play_order_insure ON play_order_insure.order_sn = play_order_info.order_sn
WHERE $where
GROUP BY play_coupon_code.id";

        $count = $this->query($sql_count)->count();

        if (!$count) {
            return $this->_Goto('0条数据！');
        }

        if ($count > 30000) {
            return $this->_Goto('数据太多了, 请缩小范围');
        }

        // 输出Excel文件头，可把user.csv换成你要的文件名
        $file_name = date('Y-m-d H:i:s', time()). '_'. $goodData->title. '_订单列表.csv';

        $head = array(
            '订单号',
            '验证码',
            '交易渠道',
            '购买时间',
            '交易号',
            '商家名称',
            '商品名称',
            '套系名称',
            '商品id',
            '用户id',
            '用户名',
            '联系人名称',
            '联系人手机号',
            '验证码状态', //待使用 已使用 退款中 已退款
            '使用时间',
            '提交退款时间',
            '购买金额',
            '收货地址',
            '备注',
            '套系开始使用时间',
            '套系结束使用时间',
            '套系的游玩地',

        );

        $tradeWay = array(
            'weixin' => '微信',
            'union' => '银联',
            'alipay' => '支付宝',
            'jsapi' => '旧微信网页',
            'nopay' => '未付款',
            'weixinsdk' => '微信客户端',
            'account' => '用户账户',
            'new_jsapi' => '新微信网页',
        );

        $content = array();
        $codeStatus = $this->_getConfig()['coupon_code_status'];

        foreach ($data as $v) {
            $content[] = array(
                'WFT' . (int)$v['order_sn'],
                "\t".$v['id']. $v['password'],
                $tradeWay[$v['account_type']],
                date('Y-m-d H:i:s', $v['dateline']),
                "\t".$v['trade_no'],
                $v['shop_name'],
                $v['coupon_name'],
                $v['price_name'],
                $v['coupon_id'],
                $v['user_id'],
                $v['username'],
                $v['buy_name'],
                $v['buy_phone'],
                $codeStatus[$v['status']],
                $v['use_datetime'] ? date('Y-m-d H:i:s', $v['use_datetime']) : '',
                $v['back_time'] ? date('Y-m-d H:i:s', $v['back_time']) : '',
                $v['coupon_unit_price'],
                $v['buy_address'],
                $v['message'],
                date('Y-m-d H:i:s', $v['start_time']),
                date('Y-m-d H:i:s', $v['end_time']),
                $v['shop_name'],
            );
        }

        $out = new OutPut();
        $out->out($file_name, $head, $content);

        exit;

    }

}