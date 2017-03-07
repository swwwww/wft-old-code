<?php

namespace Admin\Controller;

use Deyi\JsonResponse;
use Deyi\OrderAction\OrderBack;
use Deyi\OrderAction\UseCode;
use library\Fun\M;
use library\Service\Order\BackOrder;
use library\Service\System\Cache\RedCache;
use Deyi\OutPut;
use Deyi\Paginator;
use Deyi\Alipay\Alipay;
use Deyi\Account\OrganizerAccount;
use Deyi\SendMessage;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class CodeController extends BasisController
{
    use JsonResponse;
    use OrderBack;
    use UseCode;

    public $tradeWay = array(
        'weixin' => '微信',
        'union' => '银联',
        'alipay' => '支付宝',
        'jsapi' => '旧微信网页',
        'nopay' => '未付款',
        'account' => '用户账户',
        'new_jsapi' => '新微信网页',
    );

    //使用码列表
    public function indexAction() {

        $page = (int)$this->getQuery('p', 1);
        $pageSum =  (int)$this->getQuery('page_num',10);
        $start = ($page - 1) * $pageSum;
        $order = "play_order_info.order_sn DESC";
        $message_type = $this->getQuery('message_type');//是否需要预约

        $where = $this->getExcelWhere();

        if (is_array($where)) {
            return false;
        }

        if (!isset($_GET['code_status'])) {
            $where = $where. ' AND play_coupon_code.`status` = 0';
        }

        if(is_numeric($message_type)){
            $where .= " and play_organizer_game.message_type = ".$message_type;
        }

        // 获取短信模板类型
        $config_message_type = array(
            array(
                'id'                => 1,
                'message_type_name' => '通用类型',
            ),
            array(
                'id'                => 2,
                'message_type_name' => '酒店类商品',
            ),
            array(
                'id'                => 3,
                'message_type_name' => '预约使用类商品',
            ),
            array(
                'id'                => 4,
                'message_type_name' => '智游宝',
            ),
            array(
                'id'                => 5,
                'message_type_name' => '拼团',
            ),
        );

        $sql = "SELECT
	play_coupon_code.*,
	play_order_info.buy_phone,
	play_order_info.coupon_name,
	play_order_info.coupon_id,
	play_order_info.dateline,
	play_order_info.username,
	play_order_info.pay_status,
	play_order_info_game.type_name,
	play_order_info_game.start_time,
	play_order_info_game.end_time,
	play_organizer_game.need_use_time,
	play_order_otherdata.message
FROM
	play_coupon_code
LEFT JOIN play_order_info ON  play_coupon_code.order_sn = play_order_info.order_sn
LEFT JOIN play_order_otherdata ON  play_coupon_code.order_sn = play_order_otherdata.order_sn
LEFT JOIN play_order_info_game ON  play_order_info_game.order_sn = play_order_info.order_sn
LEFT JOIN play_organizer_game ON  play_organizer_game.id = play_order_info.coupon_id
WHERE
	 $where
ORDER BY
	$order";
        $sql_list = $sql." LIMIT
{$start}, {$pageSum}";

        $data = $this->query($sql_list);

        $count_sql= "SELECT
	COUNT(play_coupon_code.id) AS count_num
FROM
	play_coupon_code,
	play_order_info,
	play_organizer_game
WHERE $where AND play_coupon_code.order_sn = play_order_info.order_sn AND play_order_info.coupon_id = play_organizer_game.id";

        $countData = $this->query($count_sql)->current();
        $count = $countData['count_num'];

       // $count = $this->query("SELECT play_coupon_code.id FROM play_coupon_code LEFT JOIN play_order_info ON  play_coupon_code.order_sn = play_order_info.order_sn LEFT JOIN play_organizer_game ON  play_organizer_game.id = play_order_info.coupon_id WHERE $where")->count();

        //创建分页
        $url = '/wftadlogin/code';
        $paging = new Paginator($page, $count, $pageSum, $url);

        return array(
            'data' => $data,
            'config_message_type' => $config_message_type,
            'pageData' => $paging->getHtml(),
        );
    }

    //提交退费
    public function giveBackAction() {

        $type = $this->getQuery('type');
        $id = $this->getQuery('id');
        $ids = $this->getPost('ids');
        $money = (float)$this->getPost('money', NULL);
        if (!in_array($type, array(1, 2))) {
            return $this->_Goto('非法操作');
            exit;
        }

        if ($type == 1) { //单个处理

            if (!$id) {
                return $this->_Goto('非法操作');
            }

            $codeData = $this->_getPlayCouponCodeTable()->get(array('id' => $id));
            if (!$codeData) {
                return $this->_Goto('该订单不存在');
            } else {
                $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $codeData->order_sn));
                if ($orderData->pay_status > 1 && $codeData->status == 0) {
                    $result = $this->backIng($codeData->order_sn, $codeData->id. $codeData->password, 2, $money);
                    if ($result['status'] == 1) {
                        return $this->_Goto('成功');
                    } else {
                        return $this->_Goto($result['message']);
                    }
                } else {
                    return $this->_Goto($codeData->id. $codeData->password. '使用码不符合提交退费的条件');
                }
            }

            exit;
        }

        //批量处理 orderBack 里面批量退款
        $do_id = trim($ids, ',');
        if (!$do_id) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '没有选择订单'));
        }
        $do_id = explode(',', $do_id);

        foreach ($do_id as $id) {
            $codeData = $this->_getPlayCouponCodeTable()->get(array('id' => $id));
            if (!$codeData) {
                continue;
            } else {
                $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $codeData->order_sn));
                if ($orderData->pay_status > 1 && $codeData->status == 0) {
                    $this->backIng($codeData->order_sn, $codeData->id. $codeData->password, 2, $money);
                }
            }
        }

        return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));
        exit;

    }

    //验证使用
    public function useCodeAction() {

        $type = $this->getQuery('type');
        $id = $this->getQuery('id');
        $ids = $this->getPost('ids');

        if (!in_array($type, array(1, 2))) {
            return $this->_Goto('非法操作');
            exit;
        }

        if ($type == 1) {
            if (!$id) {
                return $this->_Goto('非法操作');
            }

            $codeData = $this->_getPlayCouponCodeTable()->get(array('id' => $id));
            if (!$codeData) {
                return $this->_Goto('该订单不存在');
            } else {
                $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $codeData->order_sn));
                if ($orderData->pay_status > 1 && $codeData->status == 0) {
                    $result = $this->UseCode($_COOKIE['id'], 3, $codeData->id. $codeData->password);
                    if ($result['status'] == 1) {
                        return $this->_Goto('成功');
                    } else {
                        return $this->_Goto($result['message']);
                    }
                } else {
                    return $this->_Goto('使用码不符合确认使用的条件');
                }
            }
        }

        //批量处理 UseCode 里面批量验证使用
        $do_id = trim($ids, ',');
        if (!$do_id) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '没有选择订单'));
        }
        $do_id = explode(',', $do_id);

        foreach ($do_id as $id) {

            $codeData = $this->_getPlayCouponCodeTable()->get(array('id' => $id));
            if (!$codeData) {
                continue;
            } else {
                $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $codeData->order_sn));
                if ($orderData->pay_status > 1 && $codeData->status == 0) {
                    $this->UseCode($_COOKIE['id'], 3, $codeData->id. $codeData->password);
                }
            }
        }

        return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));
        exit;

    }

    //原路返回退款列表
    public function backAction() {

        $pageSum =  (int)$this->getQuery('page_num',10);
        $page = (int)$this->getQuery('p', 1);
        $start = ($page - 1) * $pageSum;
        $order = "play_order_info.order_sn DESC";
        $where = $this->getExcelWhere();

        if (is_array($where)) {
            return $where;
        }

        $temp_start = $this->getQuery('temp_start', null);
        $temp_end = $this->getQuery('temp_end', null);
        $temp_back_start = $this->getQuery('temp_back_start', null);
        $temp_back_end = $this->getQuery('temp_back_end', null);

        $temp = (int)$this->getQuery('temp', 2);

        //提交原路返回时间
        if ($temp_start && $temp_end && strtotime($temp_start) > strtotime($temp_end)) {
            return array('status' => 0, 'message' => '提交原路返回时间出错');
        }

        //确定原路返回时间
        if ($temp_back_start && $temp_back_end && strtotime($temp_back_start) > strtotime($temp_back_end)) {
            return array('status' => 0, 'message' => '确定原路返回时间出错');
        }

        //提交原路返回时间
        if ($temp_start) {
            $temp_start = strtotime($temp_start);
            $where = $where. " AND play_order_back_tmp.dateline > ".$temp_start;
        }

        if ($temp_end) {
            $temp_end = strtotime($temp_end) + 86400;
            $where = $where. " AND play_order_back_tmp.dateline < ". $temp_end;
        }

        //确定原路返回时间
        if ($temp_back_start) {
            $temp_back_start = strtotime($temp_back_start);
            $where = $where. " AND play_order_back_tmp.status = 3 AND play_order_back_tmp.last_dateline > ".$temp_back_start;
        }

        if ($temp_back_end) {
            $temp_back_end = strtotime($temp_back_end) + 86400;
            $where = $where. " AND play_order_back_tmp.status = 3 AND play_order_back_tmp.last_dateline < ". $temp_back_end;
        }

        if (in_array($temp, array(2, 3))) {
            $where = $where. " AND play_coupon_code.`status` = 2 AND play_order_back_tmp.status = {$temp}";
        } else {
            $where = $where. " AND play_coupon_code.`status` = 2 AND play_order_back_tmp.status >= 2";
        }

        $sql = "SELECT
	play_coupon_code.*,
	play_order_info.back_number,
	play_order_info.buy_phone,
	play_order_info.coupon_name,
	play_order_info.dateline,
	play_order_info.username,
    play_order_info.user_id,
	play_order_info.coupon_unit_price,
	play_order_info.trade_no,
	play_order_info.buy_number,
	play_order_info.account_type,
	play_order_info.shop_name,
	play_order_info.pay_status,
	play_order_back_tmp.dateline as tmp_dateline,
	play_order_back_tmp.last_dateline,
	play_order_back_tmp.status as tmp_status
FROM
	play_order_back_tmp
LEFT JOIN play_order_info ON  play_order_info.order_sn = play_order_back_tmp.order_sn
LEFT JOIN play_coupon_code ON  play_coupon_code.id = play_order_back_tmp.code_id
WHERE
	 $where
ORDER BY
	$order";
        $sql_list = $sql." LIMIT
{$start}, {$pageSum}";


        $data = $this->query($sql_list);

        $sql_count = "SELECT
	count(play_coupon_code.id) as count_num,
	SUM(play_coupon_code.back_money) as back_money
FROM
	play_order_back_tmp
LEFT JOIN play_order_info ON  play_order_info.order_sn = play_order_back_tmp.order_sn
LEFT JOIN play_coupon_code ON  play_coupon_code.id = play_order_back_tmp.code_id
WHERE
	 $where";

        $counter = $this->query($sql_count)->current();

        $count = $counter['count_num'];


        //创建分页
        $url = '/wftadlogin/code/back';
        $paging = new Paginator($page, $count, $pageSum, $url);

        $vs_sql = "SELECT
	 count(order_sn) as order_number,
 SUM(real_pay) as order_card_money,
  SUM(account_money) as order_account_money
FROM ( SELECT
	play_order_info.order_sn,
play_order_info.real_pay,
play_order_info.account_money
FROM
play_order_info
LEFT JOIN play_coupon_code ON play_coupon_code.order_sn = play_order_info.order_sn
LEFT JOIN play_order_back_tmp ON  play_order_back_tmp.order_sn = play_order_info.order_sn
WHERE $where GROUP BY play_order_info.order_sn) AS Tmp";

        $order_count = $this->query($vs_sql)->current();

        $cai = array(
            'order_number' => $order_count['order_number'],
            'order_money' => $order_count['order_card_money'] + $order_count['order_account_money'],
            'code_num' => $counter['count_num'],
            'back_money' => $counter['back_money']
        );

        return array(
            'data' => $data,
            'pageData' => $paging->getHtml(),
            'tradeWay' => $this->tradeWay,
            'count' => $cai,
        );
    }

    //确认返回退款 到用户卡上
    public function doBackAction() {      //play_order_back_tmp status 设为3
        $type = $this->getQuery('type');
        $id = $this->getQuery('id');
        $ids = $this->getPost('ids');

        if (!in_array($type, array(1, 2))) {
            return $this->_Goto('非法操作');
        }

        $adapter = $this->_getAdapter();

        if ($type == 1) {
            if (!$id) {
                return $this->_Goto('非法操作');
            }

            $codeData = $this->_getPlayCouponCodeTable()->get(array('id' => $id));

            if (!$codeData ) {
                return $this->_Goto('该订单不存在');
            }

            $order_tmp = $adapter->query("select * from play_order_back_tmp where code_id=? AND order_sn=?",array($id, $codeData->order_sn))->current();

            if (!$order_tmp) {
                return $this->_Goto('非法操作');
            }

            $res = $adapter->query('UPDATE play_order_back_tmp SET status=?, last_dateline=? WHERE order_sn=? AND code_id=? AND status=?',array(3, time(), $codeData->order_sn, $id, 2))->count();

            if ($res) {
                return $this->_Goto('成功');
            } else {
                return $this->_Goto('失败');
            }

        }

        //批量处理
        $do_id = trim($ids, ',');

        if (!$do_id) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '没有选择订单'));
        }

        $timer = time();

        $up_sql = "UPDATE play_order_back_tmp SET status = 3, last_dateline = {$timer} WHERE status = 2 AND code_id in ($do_id)";

        $up_data = $this->query($up_sql);

        if ($up_data->count()) {
            return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '失败'));
        }

        exit;

    }

    //原路返回 导出 excel
    public function outBackOrderAction() {

        $where = $this->getExcelWhere();

        if (is_array($where)) {
            return $where;
        }

        $temp_start = $this->getQuery('temp_start', null);
        $temp_end = $this->getQuery('temp_end', null);
        $temp_back_start = $this->getQuery('temp_back_start', null);
        $temp_back_end = $this->getQuery('temp_back_end', null);

        $temp = (int)$this->getQuery('temp', 0);

        //提交原路返回时间
        if ($temp_start && $temp_end && strtotime($temp_start) > strtotime($temp_end)) {
            return array('status' => 0, 'message' => '提交原路返回时间出错');
        }

        //确定原路返回时间
        if ($temp_back_start && $temp_back_end && strtotime($temp_back_start) > strtotime($temp_back_end)) {
            return array('status' => 0, 'message' => '确定原路返回时间出错');
        }

        //提交原路返回时间
        if ($temp_start) {
            $temp_start = strtotime($temp_start);
            $where = $where. " AND play_order_back_tmp.dateline > ".$temp_start;
        }

        if ($temp_end) {
            $temp_end = strtotime($temp_end) + 86400;
            $where = $where. " AND play_order_back_tmp.dateline < ". $temp_end;
        }

        //确定原路返回时间
        if ($temp_back_start) {
            $temp_back_start = strtotime($temp_back_start);
            $where = $where. " AND play_order_back_tmp.status = 3 AND play_order_back_tmp.last_dateline > ".$temp_back_start;
        }

        if ($temp_back_end) {
            $temp_back_end = strtotime($temp_back_end) + 86400;
            $where = $where. " AND play_order_back_tmp.status = 3 AND play_order_back_tmp.last_dateline < ". $temp_back_end;
        }

        if (in_array($temp, array(2, 3))) {
            $where = $where. " AND play_coupon_code.`status` = 2 AND play_order_back_tmp.status = {$temp}";
        } else {
            $where = $where. " AND play_coupon_code.`status` = 2 AND play_order_back_tmp.status >= 2";
        }

        $order = 'play_order_info.order_sn DESC';

        $sql_list = "SELECT
	play_order_info.coupon_name,
	play_order_info.order_sn,
	play_order_info.real_pay,
	play_order_info.account_money,
	play_order_info.dateline,
	play_order_info.coupon_unit_price,
	play_order_info.trade_no,
	play_order_info.account_type,
	play_order_info.account,
	play_order_info.shop_name,
    play_order_info.order_city,
	play_order_info.buy_number,
	play_order_info.use_number,
    play_order_info.coupon_id,
	play_order_info.username,
    play_order_info.user_id,
    play_order_info.voucher,
    play_order_info.buy_phone,
    SUM(if(play_order_back_tmp.status = 2, play_coupon_code.back_money, 0)) AS wait_back,
    SUM(if(play_order_back_tmp.status = 3, play_coupon_code.back_money, 0)) AS back_money,
    play_order_back_tmp.status as tmp_status
FROM
	play_order_info
LEFT JOIN play_coupon_code ON  play_coupon_code.order_sn = play_order_info.order_sn
LEFT JOIN play_order_back_tmp ON  play_order_back_tmp.code_id = play_coupon_code.id
WHERE
	 $where
GROUP BY play_order_info.order_sn
ORDER BY
	$order";

        $data = $this->query($sql_list);

        $sql_count = "SELECT
	play_coupon_code.id
FROM
	play_coupon_code
INNER JOIN play_order_info ON  play_order_info.order_sn = play_coupon_code.order_sn
INNER JOIN play_order_back_tmp ON  play_order_back_tmp.code_id = play_coupon_code.id
WHERE
	 $where";

        $count = $this->query($sql_count)->count();

        if (!$count) {
            return $this->_Goto('0条数据！');
        }

        if ($count > 30000) {
            return $this->_Goto('数据太多了, 请缩小范围');
        }

        $file_name = date('Y-m-d H:i:s', time()). '_原路返回订单列表.csv';
        $head = array(
            '城市',
            '订单号',
            '交易渠道',
            '交易时间',
            '交易号',
            '商家名称',
            '商品名称',
            '商品id',
            '用户名',
            '用户id',
            '手机号',
            '对方账户',
            '单价',
            '购买数量',
            '购买金额',
            '代金券金额',
            '等待退款金额',
            '已退款金额', //退款至支付账户途中 已退款至用户支付账户
            '类别'
        );

        $content = array();

        $city = $this->getAllCities();
        foreach ($data as $v) {



            $content[] = array(
                $city[$v['order_city']],
                'WFT' . (int)$v['order_sn'],
                $this->tradeWay[$v['account_type']],
                date('Y-m-d H:i:s', $v['dateline']),
                "\t".$v['trade_no'],
                $v['shop_name'],
                $v['coupon_name'],
                $v['coupon_id'],
                $v['username'],
                $v['user_id'],
                $v['buy_phone'],
                $v['account'],
                $v['coupon_unit_price'],
                $v['buy_number'],
                $v['real_pay'] + $v['account_money'],
                $v['voucher'],
                $v['wait_back'],
                $v['back_money'],
                '商品'
            );

        }

        $outPut = new OutPut();

        $outPut->out($file_name, $head, $content);

        exit;
    }

    //操作日志
    private function doLog($data, $type) {
        if ($type == 3) {
            $note = '使用码'. $data->password.' 批准结算';
            $play_status = 8;
        } elseif ($type == 5) {
            $note = '使用码'. $data->password.' 结算成功';
            $play_status = 10;
        } elseif ($type == 4) {
            $note = '使用码'. $data->password.' 受理结算';
            $play_status = 9;
        } elseif ($type == 2) {
            $note = '使用码'. $data->password.' 受理退款';
            $play_status = 7;
        } elseif ($type == 1) {
            $note = '使用码'. $data->password.' 审批到账';
            $play_status = 6;
        } elseif ($type == 6) {
            $note = '使用码'. $data->password.' 已使用提交退款';
            $play_status = 11;
        } elseif ($type == 7) {
            $note = '使用码'. $data->password.' 已使用退款';
            $play_status = 13;
        } elseif ($type == 8) {
            $note = '使用码'. $data->password.' 已使用受理退款';
            $play_status = 12;
        } else {
            exit;
        }

        $data = array(
            'action_user' => $_COOKIE['id'],
            'order_id' => $data->order_sn,
            'play_status' => $play_status,
            'action_note' => $note,
            'dateline' => time(),
            'action_user_name' => '管理员'. $_COOKIE['user']
        );

        return $this->_getPlayOrderActionTable()->insert($data);
    }

    //验证码管理 查询条件统一入口
    private function getExcelWhere() {

        /**
         * 查询条件
         * ①商品相关 => 商品名称 商品id
         * ③用户相关 => 用户名  用户手机号
         * ④其它  支付方式 订单号 验证码状态
         * ⑤ 时间
         */

        $where = 'play_order_info.order_status = 1 AND play_order_info.pay_status > 1 AND play_order_info.order_type = 2';

        $good_name = $this->getQuery('good_name', null);
        $good_id = (int)$this->getQuery('good_id', 0);
        //商品名称
        if ($good_name) {
            $where = $where. " AND play_order_info.coupon_name like '%".$good_name."%'";
        }
        //商品id
        if ($good_id) {
            $where = $where. " AND play_order_info.coupon_id = ". $good_id;
        }


        $organizer_name = $this->getQuery('organizer_name', null);
        $organizer_id = (int)$this->getQuery('organizer_id', 0);

        //商家id
        if ($organizer_id) {
            $where = $where. " AND (play_order_info.shop_id = {$organizer_id} AND play_order_info.order_type = 2)";
        }

        if ($organizer_name) {
            $where = $where. " AND (play_order_info.shop_name like '%".$organizer_name."%' AND play_order_info.order_type = 2)";
        }

        $user_name = $this->getQuery('user_name', null);
        $user_phone = $this->getQuery('user_phone', null);

        //用户名称
        if ($user_name) {
            $where = $where. " AND play_order_info.username like '%".$user_name."%'";
        }

        //用户手机
        if ($user_phone) {
            $where = $where. " AND play_order_info.buy_phone like '%".$user_phone."%'";
        }

        $order_id = $this->getQuery('order_id', 0);
        $code_number = $this->getQuery('code_number', null);
        $code_status = $this->getQuery('code_status', 0);
        $trade_way =  $this->getQuery('trade_way', 0);
        $is_online =  intval($this->getQuery('is_online', 0));

        if ($is_online) {
            $timer = time();
            $ali_out_time = $timer-7776000; //3个月
            $out_time = $timer-31536000; //银联 微信 是1年 2016/2/14 换过账号
            $ali_pay = "(play_order_info.account_type = 'alipay' && play_order_info.dateline < {$ali_out_time})";
            $unin_pay = "(play_order_info.account_type = 'union' && (play_order_info.dateline < {$out_time} || play_order_info.dateline < 1455379200))";
            $old_jsapi = "(play_order_info.account_type = 'jsapi')";
            $wei_pay = "((play_order_info.account_type = 'weixin' OR play_order_info.account_type = 'new_jsapi') && play_order_info.dateline < {$out_time})";

            $where = $where. " AND ({$ali_pay} OR {$unin_pay} OR {$old_jsapi} OR {$wei_pay})";
        }

        //使用码
        if ($code_number) {
            $where = $where. " AND play_coupon_code.id like '%". substr($code_number, 0, -7)."%'";
        }

        //订单id
        if ($order_id) {
            $order_id = (int)preg_replace('|[a-zA-Z/]+|','',$order_id);
            $where = $where. " AND play_order_info.order_sn = ". $order_id;
        }

        //支付方式 'weixin','union','other','jsapi','nopay','alipay', 'weixinsdk'
        if ($trade_way) {
            if ($trade_way == 1) {
                $where = $where. " AND play_order_info.account_type = 'alipay'";
            } elseif ($trade_way == 2) {
                $where = $where. " AND play_order_info.account_type = 'union'";
            } elseif ($trade_way == 3) {
                $where = $where. " AND play_order_info.account_type = 'new_jsapi'";
            } elseif ($trade_way == 4) {
                $where = $where. " AND play_order_info.account_type = 'jsapi'";
            } elseif ($trade_way == 5) {
                $where = $where. " AND play_order_info.account_type = 'weixin'";
            } elseif ($trade_way == 6) {
                $where = $where. " AND play_order_info.account_type = 'account'";
            }
        }

        //使用码状态
        if ($code_status) {
            if ($code_status == 1) {//待使用
                $where = $where. " AND play_coupon_code.`status` = 0";
            } elseif ($code_status == 2) {//已使用
                $where = $where. " AND play_coupon_code.`status` = 1 AND play_coupon_code.`force` = 0";
            } elseif ($code_status == 3) {//已提交退款
                $where = $where. " AND play_coupon_code.`status` = 3 AND play_coupon_code.`force` = 0";
            } elseif ($code_status == 4) {//已受理退款
                $where = $where. " AND play_coupon_code.`force` = 2";
            } elseif ($code_status == 5) {//已退款
                $where = $where. " AND ((play_coupon_code.`status` = 2 AND play_coupon_code.`force` = 0) OR (play_coupon_code.`force` = 3))";
            } elseif ($code_status == 6) {//已过期
                $where = $where. " AND  play_coupon_code.`status` = 2 AND play_order_info.pay_status = 6 AND play_coupon_code.back_money = 0";
            }
        }


        $buy_start = $this->getQuery('buy_start', null); //购买时间
        $buy_end = $this->getQuery('buy_end', null);
        $check_start = $this->getQuery('check_start', null);// 使用时间
        $check_end = $this->getQuery('check_end', null);
        $back_start = $this->getQuery('back_start', null); //提交退款时间
        $back_end = $this->getQuery('back_end', null);

        $draw_start = $this->getQuery('draw_start', null); //受理退款时间
        $draw_end = $this->getQuery('draw_end', null);

        $back_true_end = $this->getQuery('back_true_end', null); //确定退款时间
        $back_true_start = $this->getQuery('back_true_start', null);
        /**
         * 时间相关
         *
         */

        //购买时间
        if ($buy_start && $buy_end && strtotime($buy_start) > strtotime($buy_end)) {
            return array('status' => 0, 'message' => '购买时间出错');
        }

        //验证时间
        if ($check_start && $check_end && strtotime($check_start) > strtotime($check_end)) {
            return array('status' => 0, 'message' => '验证时间出错');
        }

        //退款时间
        if ($back_start && $back_end && strtotime($back_start) > strtotime($back_end)) {
            return array('status' => 0, 'message' => '提交退款时间出错');
        }

        //受理退款时间
        if ($draw_start && $draw_end && strtotime($draw_start) > strtotime($draw_end)) {
            return array('status' => 0, 'message' => '受理退款时间出错');
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

        //使用时间
        if ($check_start) {
            $check_start = strtotime($check_start);
            $where = $where. " AND play_coupon_code.use_datetime > ".$check_start;
        }

        if ($check_end) {
            $check_end = strtotime($check_end) + 86400;
            $where = $where. " AND play_coupon_code.use_datetime < ". $check_end;
        }

        //提交退款时间
        if ($back_start) {
            $back_start = strtotime($back_start);
            $where = $where. " AND play_coupon_code.back_time > ".$back_start;
        }

        if ($back_end) {
            $back_end = strtotime($back_end) + 86400;
            $where = $where. " AND play_coupon_code.back_time < ". $back_end;
        }

        //受理退款时间
        if ($draw_start) {
            $draw_start = strtotime($draw_start);
            $where = $where. " AND play_coupon_code.accept_time > ". $draw_start;
        }

        if ($draw_end) {
            $draw_end = strtotime($draw_end) + 86400;
            $where = $where. " AND play_coupon_code.accept_time < ". $draw_end;
        }

        //确定退款时间
        if ($back_true_start) {
            $back_true_start = strtotime($back_true_start);
            $where = $where. " AND play_coupon_code.back_money_time > ". $back_true_start;

        }

        if ($back_true_end) {
            $back_true_end = strtotime($back_true_end) + 86400;
            $where = $where. " AND play_coupon_code.back_money_time < ". $back_true_end;
        }

        $city = $this->getBackCity();
        if($city){
            $where = $where. " AND play_order_info.order_city = '{$city}'";
        }

        return $where;
    }

    //导出数据 => object code 以使用码 为单位
    public function outCodeAction()
    {

        $where = $this->getExcelWhere();

        if (is_array($where)) {
            return false;
        }

        $order = 'play_order_info.order_sn DESC';

        $temp = (int)$this->getQuery('temp', 0);

        if ($temp) {
            $where = $where. " AND play_order_back_tmp.status = {$temp}";
        }

        $is_hotal = $this->getQuery('is_hotal');//是否需要预约

        if($is_hotal === '1' or $is_hotal === '0' ){
            $where .= " and play_organizer_game.is_hotal = ".$is_hotal;
        }

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
    play_order_info_game.start_time,
    play_order_info_game.type_name,
    play_order_back_tmp.status as tmp_status
FROM
	play_coupon_code
LEFT JOIN play_order_info ON  play_coupon_code.order_sn = play_order_info.order_sn
LEFT JOIN play_order_info_game ON  play_order_info_game.order_sn = play_order_info.order_sn
LEFT JOIN play_order_back_tmp ON  play_order_back_tmp.code_id = play_coupon_code.id
LEFT JOIN play_organizer_game ON  play_organizer_game.id = play_order_info.coupon_id
WHERE
	 $where
ORDER BY
	$order";

        $data = $this->query($sql_list);

        $sql_count = "SELECT
	play_coupon_code.id
FROM
	play_coupon_code
LEFT JOIN play_order_info ON  play_coupon_code.order_sn = play_order_info.order_sn
LEFT JOIN play_order_back_tmp ON  play_order_back_tmp.code_id = play_coupon_code.id
LEFT JOIN play_organizer_game ON  play_organizer_game.id = play_order_info.coupon_id
WHERE
	 $where";

        $count = $this->query($sql_count)->count();

        if (!$count) {
            return $this->_Goto('0条数据！');
        }

        if ($count > 30000) {
            return $this->_Goto('数据太多了, 请缩小范围');
        }

        // 输出Excel文件头，可把user.csv换成你要的文件名
        $file_name = date('Y-m-d H:i:s', time()). '_订单列表.csv';
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename='. $file_name);
        header('Cache-Control: max-age=0');

        // 打开PHP文件句柄，php://output 表示直接输出到浏览器
        $fp = fopen('php://output', 'a');

        // 输出Excel列名信息
        $head = array(
            '订单号',
            '验证码',
            '交易渠道',
            '购买时间',
            '出行日期',
            '交易号',
            '商家名称',
            '商品名称',
            '套系名称',
            '商品id',
            '用户名',
            '手机号',
            '用户id',
            '验证码状态', //待使用 已使用 退款中 已退款
            '审核状态', //未审核 已审核
            '退款状态', //退款中 已退款 退款至支付账户途中 已退款至用户支付账户
            '退款金额',
            '使用时间',
            '提交退款时间',
            '购买金额',  //新版加了 使用优化券的  如何展示
        );

        //转码 否则 excel 会乱码

        foreach ($head as $i => $row) {
            $head[$i] = iconv('utf-8', 'gbk', $row);
        }

        fputcsv($fp, $head);

        foreach ($data as $v) {

            $code_status = '';
            $back_status = '';
            if ($v['status'] == 0) {
                $code_status = '待使用';
                $back_status = '';
            } elseif ($v['status'] == 1) {
                $code_status = '已使用';
                $back_status = '';
            } elseif ($v['status'] == 2) {
                $code_status = '已退款';
            } elseif ($v['status'] == 3) {
                $code_status = '退款中';
            }

            if ($v['status'] == 2) {
                if ($v['tmp_status'] == 2) {
                    $back_status = '退款至支付账户途中';
                } elseif ($v['tmp_status'] == 3) {
                    $back_status = '已退款至用户支付账户';
                }
            }

            $outData = array(
                iconv('utf-8', 'gbk', 'WFT' . (int)$v['order_sn']),
                iconv('utf-8', 'gbk', "\t".$v['id']. $v['password']),
                iconv('utf-8', 'gbk', $this->tradeWay[$v['account_type']]),
                iconv('utf-8', 'gbk', date('Y-m-d H:i:s', $v['dateline'])),
                iconv('utf-8', 'gbk', date('Y-m-d', $v['start_time'])),//出行日期
                iconv('utf-8', 'gbk', "\t".$v['trade_no']),
                iconv('utf-8', 'gbk', $v['shop_name']),
                iconv('utf-8', 'gbk', $v['coupon_name']),
                iconv('utf-8', 'gbk', $v['type_name']),
                iconv('utf-8', 'gbk', $v['coupon_id']),
                iconv('utf-8', 'gbk', $v['username']),
                iconv('utf-8', 'gbk', $v['buy_phone']),
                iconv('utf-8', 'gbk', $v['user_id']),
                iconv('utf-8', 'gbk', $code_status),
                iconv('utf-8', 'gbk', ($v['check_status'] == 2) ? '已审批' : '未审批'),
                iconv('utf-8', 'gbk', $back_status),
                iconv('utf-8', 'gbk', $v['back_money']),
                iconv('utf-8', 'gbk', $v['use_datetime'] ? date('Y-m-d H:i:s', $v['use_datetime']) : ''),
                iconv('utf-8', 'gbk', $v['back_time'] ? date('Y-m-d H:i:s', $v['back_time']) : ''),
                iconv('utf-8', 'gbk', $v['coupon_unit_price']),
            );
            fputcsv($fp, $outData);
        }
        exit;

    }

    //提交特殊退款
    public function specialRefundAction() {

        $type = (int)$this->getQuery('type', 0);

        if ($type == 1) {
            $id = (int)$this->getQuery('id', 0);
            //$money = floatval($this->getPost('money'));
            $reason = $this->getPost('reason', null);

            if (!$reason) {
                return $this->_Goto('特殊退款必填');
            }
            $result = $this->refundAction($id, $reason);
            return $this->_Goto($result['message']);

        } elseif ($type == 2) {
            $ids = $this->getPost('ids', null);

            $codeIds = explode(',', $ids);

            if (!count($codeIds)) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '没有选中的'));
            }

            foreach ($codeIds as $code) {
                $this->refundAction($code, '');
            }

            return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));
        }

        return $this->_Goto('非法操作');
    }


    /**
     * 后面放到 共有的vender 里面
     * @param $id //使用码id
     * @param string $reason  //退款金额
     * @return array
     */
    private function refundAction($id, $reason) {

        $codeData = M::getPlayCouponCodeTable()->get(array('id' => $id));
        if (!$codeData) {
            return array('status' => 0, 'message' => '非法操作！');
        }

        $BackOrder = new BackOrder();
        $re = $BackOrder->SpecialBack($codeData->order_sn, $id, $reason, 0);

        if ($re['status'] != 1) {
            return array('status' => 0, 'message' => $re['message']);
        }

        $organizerAccount = new OrganizerAccount();
        if ($codeData->status == 1) {//已使用 商家账户增加一条记录 扣钱

            //包销入库 代销扣钱
            $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $codeData->coupon_id));
            $contractData = $this->_getPlayContractsTable()->get(array('id' => $goodData->contract_id));
            if ($contractData && $contractData->contracts_type == 1) {//包销

                $game_info = $this->_getPlayGameInfoTable()->get(array('id' => $codeData->bid));
                $game_price = $this->_getPlayGamePriceTable()->get(array('id' => $game_info->pid));
                $contractLinkPrice = $this->_getPlayContractLinkPriceTable()->get(array('id' => $game_price->contract_link_id));

                $organizerAccount->inventory($contractLinkPrice->inventory_id, 1, $codeData->coupon_unit_price, 1, $codeData->order_sn, $id, '特殊退费');
            }

            if ($contractData && $contractData->contracts_type == 3) {//代销 扣结算价
                $game_info = $this->_getPlayGameInfoTable()->get(array('id' => $codeData->bid));
                if ($goodData->account_organizer == 1) {
                    $organizer_id = $contractData->mid;
                } else {

                    $organizer_flag = $this->_getPlayCodeUsedTable()->get(array('good_info_id' => $codeData->bid));
                    if (!$organizer_flag) {
                        return array('status' => 0, 'message' => "商家出错");
                    }

                    $organizer_id = $organizer_flag->organizer_id;
                }
                $organizerAccount->takeCrash($organizer_id, 2, $object_type = 3, $codeData->order_sn, $game_info->account_money, '特殊退款扣钱'. '_后台管理员_'.$_COOKIE['id'],  $status = 1, $goodData->contract_id, $id);
            }


        }

        return array('status' => 1, 'message' => '成功');

    }


    //受理退款
    public function checkAction() {

        $type = $this->getQuery('type', 0);

        if ($type == 1) {
            $id = (int)$this->getQuery('id');
            $where = "play_coupon_code.id = {$id}";
        } elseif ($type == 2) {
            $ids = trim($this->getPost('ids', null), ',');
            if (!$ids) {
                return $this->jsonResponsePage(array('status' => 1, 'message' => '没选中订单'));
            }
            $where = "play_coupon_code.id IN ({$ids})";
        } else {
            echo '非法操作';
            exit;
        }

        $up_sql = "SELECT
	play_coupon_code.id,
    play_coupon_code.password,
	play_order_info.order_sn,
	play_order_info.buy_phone,
    play_order_info.coupon_name,
    play_order_info.order_city
FROM
	play_coupon_code
LEFT JOIN play_order_info ON  play_coupon_code.order_sn = play_order_info.order_sn
WHERE $where AND play_coupon_code.`force` = 0 AND play_coupon_code.`status` = 3 AND play_coupon_code.check_status = 2";

        $up_data = $this->query($up_sql);

        if ($up_data->count() < 1) {
            if ($type == 1) {
                return $this->_Goto('没有符合的数据去处理');
            } else {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '没有符合的数据去处理'));
            }
        }

        //判断是否有智游宝的则让等待受理退款
        $zyb_sql = "SELECT
	play_coupon_code.id
FROM
	play_coupon_code
INNER JOIN play_zyb_info ON play_zyb_info.code_id = play_coupon_code.id
WHERE $where AND play_coupon_code.`force` = 0 AND play_coupon_code.`status` = 3 AND play_coupon_code.check_status = 2 AND play_zyb_info.status != 4";
        $zybData = $this->query($zyb_sql);
        if ($zybData->count() > 0) {
            if ($type == 1) {
                return $this->_Goto('智游宝订单退款正在受理中');
            } else {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '有智游宝订单退款正在受理中'));
            }
        }
        $timer = time();

        $common_arr = $this->_getConfig();
        $pdo = new \PDO($common_arr['db']['dsn'], $common_arr['db']['username'], $common_arr['db']['password'], $common_arr['db']['driver_options']);

        $pdo->beginTransaction();
        //更新
        $sql = "UPDATE play_coupon_code
LEFT JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
SET play_coupon_code.`force` = 2, play_coupon_code.accept_time = {$timer}
WHERE $where AND play_coupon_code.`force` = 0 AND play_coupon_code.`status` = 3 AND play_coupon_code.check_status = 2";

        $result = $pdo->exec($sql);

        if (!$result) {
            $pdo->rollBack();
            if ($type == 1) {
                return $this->_Goto('没有符合的数据去处理!');
            } else {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '没有符合的数据去处理!'));
            }
        }

        //插入更新记录记录
        $i = 0;
        $insert_sql = "INSERT play_order_action (`action_user`, `order_id`, `play_status`, `action_note`, `dateline`, `action_user_name`, code_id) VALUES ";

        foreach ($up_data as $up) {
            $action_note = '使用码'. $up['id']. $up['password']. '受理退款';
            $action_user_name = '管理员'. $_COOKIE['user'];
            if (!$i) {
                $insert_sql = $insert_sql . "({$_COOKIE['id']}, {$up['order_sn']}, 7, '{$action_note}', {$timer}, '{$action_user_name}', {$up['id']})";
            } else {
                $insert_sql = $insert_sql . ", ({$_COOKIE['id']}, {$up['order_sn']}, 7, '{$action_note}', {$timer}, '{$action_user_name}', {$up['id']})";
            }
            $i ++;

            // SendMessage::Send19($up['buy_phone'], $up['id'].$up['password'], $up['coupon_name'],$up['order_city']);
        }

        $insert = $pdo->exec($insert_sql);
        if (!$insert) {
            $pdo->rollback();
            if ($type == 1) {
                return $this->_Goto('插入记录处理失败');
            } else {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '插入记录处理失败'));
            }
        }

        $pdo->commit();

        if ($type == 1) {
            return $this->_Goto('成功');
        } else {
            return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));
        }
    }


    //确定退款列表
    public function drawbackAction() {

        $pageSum =  (int)$this->getQuery('page_num',10);
        $page = (int)$this->getQuery('p', 1);
        $start = ($page - 1) * $pageSum;
        $order = "play_order_info.order_sn DESC";
        $where = $this->getExcelWhere();

        if (is_array($where)) {
            return $where;
        }

        $where = $where. ' AND play_coupon_code.check_status = 2';

        if (!isset($_GET['code_status'])) {
            $where = $where. " AND play_coupon_code.`force` = 2";
        }

        $abnormal = (int)$this->getQuery('abnormal', 0);

        if ($abnormal) {
            if ($abnormal == 1) {
                $where = $where. " AND (play_coupon_code.`force` = 3 OR ((play_coupon_code.`status` = 1 OR play_coupon_code.`status` = 2) AND play_coupon_code.`force` = 2))";
            } elseif ($abnormal == 2) {
                $where = $where. " AND (play_coupon_code.`force` = 0 OR (play_coupon_code.`force` = 2 AND play_coupon_code.`status` = 3))";
            }
        }

        if (isset($_GET['code_status']) && !$_GET['code_status']) {
            $where = $where. " AND (((play_coupon_code.`status` = 2 AND play_coupon_code.`force` = 0) OR (play_coupon_code.`force` = 3)) OR play_coupon_code.`force` = 2)";
        }


        $is_doing = $this->query("SELECT play_alipay_refund_log.code_id FROM play_alipay_refund_log WHERE status = 3", array());
        $is_doing_list = array();
        foreach ($is_doing as $doing) {
            $is_doing_list[] = $doing['code_id'];
        }

        if (count($is_doing_list)) {
            $doing_list = trim(implode(',', $is_doing_list), ',');
            $where =  $where. " AND play_coupon_code.id NOT IN ({$doing_list})";
        }

        $sql = "SELECT
	play_coupon_code.id,
    play_coupon_code.password,
    play_coupon_code.status,
    play_coupon_code.`force`,
    play_coupon_code.back_money,
    play_coupon_code.order_sn,
    play_coupon_code.back_money_time,
	play_order_info.buy_phone,
	play_order_info.coupon_name,
	play_order_info.dateline,
	play_order_info.username,
    play_order_info.user_id,
	play_order_info.real_pay,
    play_order_info.account_money,
    play_order_info.voucher,
	play_order_info.trade_no,
	play_order_info.account_type,
    play_order_info.buy_number,
	play_coupon_code.accept_time
FROM
	play_coupon_code
INNER JOIN play_order_info ON  play_order_info.order_sn = play_coupon_code.order_sn
WHERE $where ORDER BY $order";
        $sql_list = $sql." LIMIT
{$start}, {$pageSum}";

        $data = $this->query($sql_list);
        $countData = $this->query("SELECT
	count(order_sn) AS order_number,
	SUM(real_pay) AS order_card_money,
	SUM(account_money) AS order_account_money,
    SUM(Tmp.code_num) AS code_number,
	SUM(Tmp.w_money) AS wait_money,
	SUM(Tmp.back_money) AS back_money
FROM
	(
		SELECT
			play_order_info.order_sn,
			play_order_info.real_pay,
			play_order_info.account_money,
			count(play_coupon_code.id) as code_num,
			SUM(if(play_coupon_code.force=2, play_coupon_code.back_money, 0)) AS w_money,
			SUM(play_coupon_code.back_money) AS back_money
		FROM
			play_coupon_code
		LEFT JOIN play_order_info ON play_coupon_code.order_sn = play_order_info.order_sn
		WHERE $where
		GROUP BY
			play_order_info.order_sn
	) AS Tmp", array())->current();


        $cai = array(
            'order_number' => $countData['order_number'],
            'order_money' => $countData['order_card_money'] + $countData['order_account_money'],
            'code_num' => $countData['code_number'],
            'back_money' => $countData['wait_money'],
            'backed_money' => bcsub($countData['back_money'], $countData['wait_money'], 2),
        );

        $count = $countData['code_number'];

        //创建分页
        $url = '/wftadlogin/code/drawback';
        $paging = new Paginator($page, $count, $pageSum, $url);

        $useData = array();
        $special_reason = '';
        foreach ($data as $val) {

            if ($val['status'] == 1 || $val['status'] == 2) {
                $specialData = $this->_getPlayOrderActionTable()->get(array('play_status' => 14, 'order_id' => $val['order_sn'], 'code_id' => $val['id']));
                $special_reason = $specialData ? $specialData->action_note : '';
            }

            if ($val['force'] == 2) {
                $errorData = $this->_getPlayOrderActionTable()->get(array('play_status' => 102, 'code_id' => $val['id'], 'order_id' => $val['order_sn']));
                $error = $errorData ? 1 : 0;
            } else {
                $error = 0;
            }
            $useData[] = array(
                'id' => $val['id'],
                'password' => $val['password'],
                'status' => $val['status'],
                'force' => $val['force'],
                'back_money' => $val['back_money'],
                'order_sn' => $val['order_sn'],
                'back_time' => $val['back_money_time'] ? date('Y-m-d H:i:s', $val['back_money_time']) : '',
                'buy_phone' => $val['buy_phone'],
                'coupon_name' => $val['coupon_name'],
                'dateline' => $val['dateline'],
                'username' => $val['username'],
                'user_id' => $val['user_id'],
                'real_pay' => $val['real_pay'],
                'account_money' => $val['account_money'],
                'voucher' => $val['voucher'],
                'trade_no' => $val['trade_no'],
                'account_type' => $val['account_type'],
                'buy_number' => $val['buy_number'],
                'accept_time' => $val['accept_time'],
                'special_reason' => $special_reason,
                'error' => $error,
            );
        }
        return array(
            'data' => $useData,
            'pageData' => $paging->getHtml(),
            'tradeWay' => $this->tradeWay,
            'count' => $cai,
            'code_minute' => RedCache::get('code_draw_back') ? json_decode(RedCache::get('code_draw_back'), true) : array(),
        );
    }

    //一些特殊原因 时间过长 第三方不让接口退  旧微信网页
    public function offlineBackAction() {
        $id = (int)$this->getQuery('id', 0);

        $sql = "SELECT
	play_coupon_code.order_sn,
	play_coupon_code.id,
	play_coupon_code.password
FROM
	play_coupon_code
LEFT JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
WHERE
	play_coupon_code.id = ?
AND play_coupon_code.`force` = 2
AND play_coupon_code.check_status = 2";

        $adapter = $this->_getAdapter();
        $codeData = $adapter->query($sql, array($id))->current();

        if (!$codeData) {
            return $this->_Goto('该使用码 不符合退款条件');
        }

        $result = $this->backOk($codeData->order_sn, $codeData->id. $codeData->password, 3);
        return $this->_Goto($result['message']);


    }

    //确定退款
    public function drawAction() {
        exit;
        $type = $this->getQuery('type');

        if (!in_array($type, array(1, 2))) {
            return $this->_Goto('非法操作');
        }

        if ($type == 1) {

            $id = $this->getQuery('id');

            if (!$id) {
                return $this->_Goto('非法操作!');
            }

            $codeData = $this->_getPlayCouponCodeTable()->get(array('id' => $id));

            if (!$codeData) {
                return $this->_Goto('该订单不存在');
            }

            if ($codeData->force != 2) {
                return $this->_Goto('非法操作!!');
            }

            /**
             * 生成redis 进一步过滤
             */

            if (!RedCache::get('code_draw_back')) {
                RedCache::set('code_draw_back', json_encode(array($id)), 120);
            } else {

                if (count(array_intersect(json_decode(RedCache::get('code_draw_back'), true), array($id)))) {
                    return array('status' => 0, 'message' => '2分钟内同一code 只允许提交一次');
                } else {
                    RedCache::set('code_draw_back', json_encode(array_merge(json_decode(RedCache::get('code_draw_back'), true), array($id))), 120);
                }
            }

            //$result = $this->refundMoney($id);

            if ($result['status'] == 1) {
                return $this->_Goto('成功');
            } else {
                return $this->_Goto($result['message']);
            }
        } elseif ($type == 2) {

            $ids = trim($this->getPost('ids', ''), ',');
            if (!$ids) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '没选中'));
            }

            $codeIds = explode(',', $ids);

            if (count($codeIds) < 1) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '没有符合的数据去处理'));
            }

            $trade_type = $this->getQuery('trade_way');

            if (!$trade_type) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '请选择指定的支付方式'));
            }

            /**
             * 生成redis 进一步过滤
             */

            if (!RedCache::get('code_draw_back')) {
                RedCache::set('code_draw_back', json_encode($codeIds), 120);
            } else {

                if (count(array_intersect(json_decode(RedCache::get('code_draw_back'), true), $codeIds))) {
                    return array('status' => 0, 'message' => '2分钟内同一code 只允许提交一次');
                } else {
                    RedCache::set('code_draw_back', json_encode(array_merge(json_decode(RedCache::get('code_draw_back'), true), $codeIds)), 120);
                }
            }

            if ($trade_type != 1) {
                foreach ($codeIds as $code_id) {
                    //$this->refundMoney($code_id);
                }
                return $this->jsonResponsePage(array('status' => 1, 'message' => '已提交'));
            } else {
                //$res = $this->backAlipay($codeIds);
                return $this->jsonResponsePage($res);
            }

            exit;

        } else {
            exit('非法操作');
        }
    }

    //确定退款导出
    public function outDrawAction () {

        $where = $this->getExcelWhere();

        if (is_array($where)) {
            return $where;
        }

        $where = $where. ' AND play_coupon_code.check_status = 2';

        if (!isset($_GET['code_status'])) {
            $where = $where. " AND play_coupon_code.`force` = 2";
        }

        $abnormal = (int)$this->getQuery('abnormal', 0);

        if ($abnormal) {

            if ($abnormal == 1) {
                $where = $where. " AND (play_coupon_code.`force` = 3 OR ((play_coupon_code.`status` = 1 OR play_coupon_code.`status` = 2)  AND play_coupon_code.`force` = 2))";
            } elseif ($abnormal == 2) {
                $where = $where. " AND (play_coupon_code.`force` = 0 OR (play_coupon_code.`force` = 2 AND play_coupon_code.`status` = 3))";
            }
        }

        $code_status = $this->getQuery('code_status', 0);//退款状态

        if (!$code_status) {
            $where = $where. " AND (play_coupon_code.`force` = 2 OR (((play_coupon_code.`status` = 2 AND play_coupon_code.`force` = 0) OR (play_coupon_code.`force` = 3)) AND play_coupon_code.back_money > 0))";
        }

        $order = 'play_order_info.order_sn DESC';

        $sql = "SELECT
    play_order_info.order_sn,
	play_order_info.buy_phone,
	play_order_info.coupon_name,
	play_order_info.shop_name,
	play_order_info.dateline,
	play_order_info.username,
	play_order_info.coupon_id,
    play_order_info.trade_no,
	play_order_info.account_type,
	play_order_info.account,
	play_order_info.order_type,
	play_order_info.coupon_unit_price,
	play_order_info.buy_number,
	play_order_info.voucher,
	play_order_info.phone,
    play_order_info.order_city,
    SUM(if((play_coupon_code.status = 1 AND play_coupon_code.force = 3 ) OR play_coupon_code.status = 2, play_coupon_code.back_money, 0)) AS back_money,
    SUM(if(play_coupon_code.force = 2, play_coupon_code.back_money, 0)) AS wait_back,
    MAX(play_coupon_code.back_money_time) AS back_money_time,
	play_order_info.user_id,
    play_order_info.real_pay,
    play_order_info.account_money
FROM
	play_order_info
LEFT JOIN play_coupon_code ON play_coupon_code.order_sn = play_order_info.order_sn
WHERE
	 $where
GROUP BY
  play_order_info.order_sn
ORDER BY
	$order";

        $data = $this->query($sql);

        $count_sql = "SELECT play_order_info.order_sn
FROM play_order_info
LEFT JOIN play_coupon_code ON play_coupon_code.order_sn = play_order_info.order_sn
WHERE $where
GROUP BY play_order_info.order_sn";
        $count = $this->query($count_sql)->count();

        if (!$count) {
            return $this->_Goto('0条数据！');
        }

        if ($count > 32000) {
            return $this->_Goto('超过系统最大负荷，请把时间设在1个月内，重新搜索');
        }

        $out = new OutPut();

        $file_name = date('Y-m-d H:i:s', time()). '_订单列表.csv';
        // 输出Excel列名信息
        $head = array(
            '交易时间',
            '交易渠道',
            '交易号',
            '城市',
            '商品订单号',
            '商家名称',
            '商品名称',
            '单价',
            '购买数量',
            '购买金额',
            '代金券金额',
            '等待退款金额',
            '已退款金额',
            '对方账户',
            '用户名',
            '手机号',
            '用户id',
            '类别',
            '最近退款时间'
        );

        $content = array();
        $city = $this->getAllCities();

        foreach ($data as $v) {

            $content[] = array(
                date('Y-m-d H:i:s', $v['dateline']),
                $this->tradeWay[$v['account_type']],
                "\t".$v['trade_no'],
                $city[$v['order_city']],
                'WFT' . (int)$v['order_sn'],
                $v['shop_name'],
                $v['coupon_name'],
                $v['coupon_unit_price'],
                $v['buy_number'],
                bcadd($v['real_pay'], $v['account_money'], 2),
                $v['voucher'],
                $v['wait_back'],
                $v['back_money'],
                $v['account'],
                $v['username'],
                $v['phone'],
                $v['user_id'],
                ($v['order_type'] == 2) ? '商品' : '',
                $v['back_money_time'] ? date('Y-m-d H:i:s', $v['back_money_time']) : ''
            );
        }


        $out->out($file_name, $head, $content);
        exit;
    }

    /**
     *  单个订单 退款金额 及 等等退款金额
     * @param $order_sn
     * @param $unit_price
     * @param $where
     * @return array
     */
    private function getBackMoney($order_sn, $unit_price, $where) {
        $data = array(
            'wait' => 0, //等待退款金额
            'yes' => 0, // 已退款金额
            'account_need_num' => 0, // 需结算数量
            'account_have_num' => 0, // 已经结算数量
            'account_time' => '', //最近的结算时间
            'use_time' => '', //最近的使用时间
            'order_stu' => '', //订单状态
            'use_number' => 0, //已使用的code
        );
        $codeWhere = $where ." AND play_order_info.order_sn = {$order_sn}";

        $codeSql = "SELECT
	play_coupon_code.*
FROM
	play_coupon_code
LEFT JOIN play_order_info ON  play_coupon_code.order_sn = play_order_info.order_sn
WHERE $codeWhere";
        $codeData = $this->query($codeSql);

        foreach($codeData as $code) {

            if ($code['force'] == 2) {
                $data['wait'] = $data['wait'] + floatval($code['back_money']);
            }

            if ($code['status'] == 2 && $code['force'] == 0) {
                $data['yes'] = $data['yes'] + (floatval($code['back_money']) ? $code['back_money'] : $unit_price);
            }

            if ($code['status'] == 1 && $code['force'] == 0) {
                $data['use_number'] = $data['use_number'] + 1;
            }

            //已使用 确认退款
            if ($code['force'] == 3) {
                $data['yes'] = $data['yes'] + $code['back_money'];
            }

            if ($code['status'] == 1 || $code['status'] == 2) {
                if ($code['test_status'] == 5) {
                    $data['account_have_num'] = $data['account_have_num'] + 1;
                } elseif($code['test_status'] > 2) {
                    $data['account_need_num'] = $data['account_need_num'] + 1;
                }
            }

            if ($code['use_datetime']) {
                $data['use_time'] = $code['use_datetime'];
            }
        }

        return $data;
    }

    /* private function doDraw($id, $money) {
         $pid = pcntl_fork();
         //父进程和子进程都会执行下面代码
         if ($pid == -1) {
             //错误处理：创建子进程失败时返回-1.
             return  $this->refundmoney($id, $money);
         } else if ($pid) {
             //父进程会得到子进程号，所以这里是父进程执行的逻辑
             // pcntl_wait($status); //等待子进程中断，防止子进程成为僵尸进程。
             return true;
         } else {
             //子进程得到的$pid为0, 所以这里是子进程执行的逻辑。
             return  $this->refundmoney($id, $money);
             exit;
         }
     }*/

}
