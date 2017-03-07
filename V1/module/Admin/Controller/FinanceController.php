<?php
/**
 * Created by PhpStorm.
 * User: qinyuan
 * Date: 2016/1/9
 * Time: 9:43
 */
namespace Admin\Controller;

use Deyi\AutoRefund;
use Deyi\Account\OrganizerAccount;
use Deyi\BaseController;
use Deyi\Contract\Contract;
use Deyi\JsonResponse;
use Deyi\OrderAction\OrderBack;
use Deyi\OrderAction\OrderInfo;
use Deyi\OrderAction\UseCode;
use Deyi\OutPut;
use Deyi\Paginator;
use Deyi\SendMessage;
use library\Fun\M;

class FinanceController extends BasisController{
    use JsonResponse;
    use OrderBack;
    use UseCode;

    private $pageSum = 10;
    //审核状态 1未审核，2已审核
    private $verifyStatus =array(
        array('text'=>'未审核','val'=>1),
        array('text'=>'已审核','val'=>2)
    );

    //订单状态
    private $orderStatus = array(
        array('text'=>'未支付','val'=>'0'),
        array('text'=>'已支付','val'=>'2'),
        array('text'=>'付款中','val'=>'1'),
        array('text'=>'已使用','val'=>'5'),
        array('text'=>'退款处理中','val'=>'3'),
        array('text'=>'已退款','val'=>'4'),
        array('text'=>'已结算','val'=>'6'),
    );
    //商品状态
    private $goodsStatus = array(
        array('text'=>'未开始','val'=>0),
        array('text'=>'在售','val'=>1),
        array('text'=>'已下架','val'=>2),
        array('text'=>'停止使用','val'=>3),
    );

//    //支付状态
    private $payType = array(
        '1'=>'alipay',
        '2'=>'union',
        '3'=>'weixin',
        '4'=>'account',
        '5'=>'weixinsdk',
    );

//    public function indexAction(){
//        return array();
//    }
    //订单相关 老订单搜索  已废弃
    public function indexAction(){
        exit('已废弃');
        //获取搜索条件
        $page = (int)$this->getQuery('p', 1);
        $param = $this->GetSearchCondition();

        $where = "play_order_info.order_status = 1 and play_order_info.order_type = 2 and pay_status>1";
        $order = "play_order_info.order_sn DESC";
        $start = ($page - 1) * $this->pageSum;

        if($param['good_name']){
            $where.= " AND (play_organizer_game.title like '%{$param['good_name']}%' OR play_organizer_game.editor_talk like '%{$param['good_name']}%')";
        }

        if($param['shop_name']){
            $where.=" AND play_order_info.shop_name like '%{$param['shop_name']}%'";
        }

        if($param['good_id']){
            $where.=" AND play_order_info.coupon_id ={$param['good_id']}";
        }

        if($param['user_name']){
            $where.=" AND play_order_info.username like '%{$param['user_name']}%'";
        }

        //购买时间筛选
        if(!empty($param["buy_start_date"]) && !empty($param["buy_end_date"])){
            $nStartDate = strtotime($param["buy_start_date"]);
            $nEndDate = strtotime($param["buy_end_date"])+86400;

            //容错起始时间大于结束时间
            if($nStartDate > $nEndDate){
                $where .= " and (play_order_info.dateline >= {$nEndDate} and play_order_info.dateline <= {$nStartDate}) ";
            }else{
                $where .= " and (play_order_info.dateline >= {$nStartDate} and play_order_info.dateline <= {$nEndDate}) ";
            }
        }else{
            if(!empty($param["buy_start_date"])){
                $nStartDate = strtotime($param["buy_start_date"]);
                $where .= " and play_order_info.dateline >= {$nStartDate} ";
            }

            if(!empty($param["buy_end_date"])){
                $nEndDate = strtotime($param["buy_end_date"])+86400;
                $where .= " and play_order_info.dateline <= {$nEndDate} ";
            }
        }

        //验证时间筛选
        if(!empty($param["ver_start_date"]) && !empty($param["ver_end_date"])){
            $nStartDate = strtotime($param["ver_start_date"]);
            $nEndDate = strtotime($param["ver_end_date"])+86400;

            //容错起始时间大于结束时间
            if($nStartDate > $nEndDate){
                $where .= " and (play_order_info.use_dateline >= {$nEndDate} and play_order_info.use_dateline <= {$nStartDate}) ";
            }else{
                $where .= " and (play_order_info.use_dateline >= {$nStartDate} and play_order_info.use_dateline <= {$nEndDate}) ";
            }
        }else{
            if(!empty($param["ver_start_date"])){
                $nStartDate = strtotime($param["ver_start_date"]);
                $where .= " and play_order_info.use_dateline >= {$nStartDate} ";
            }

            if(!empty($param["ver_end_date"])){
                $nEndDate = strtotime($param["ver_end_date"])+86400;
                $where .= " and play_order_info.use_dateline <= {$nEndDate} ";
            }
        }

        //提交退款时间筛选
        if(!empty($param["sub_back_start_date"]) && !empty($param["sub_back_end_date"])){
            $nStartDate = strtotime($param["sub_back_start_date"]);
            $nEndDate = strtotime($param["sub_back_end_date"])+86400;

            //容错起始时间大于结束时间
            if($nStartDate > $nEndDate){
                $where .= " and (play_coupon_code.back_time >= {$nEndDate} and play_coupon_code.back_time <= {$nStartDate}) ";
            }else{
                $where .= " and (play_coupon_code.back_time >= {$nStartDate} and play_coupon_code.back_time <= {$nEndDate}) ";
            }
        }else{
            if(!empty($param["sub_back_start_date"])){
                $nStartDate = strtotime($param["sub_back_start_date"]);
                $where .= " and play_coupon_code.back_time >= {$nStartDate} ";
            }

            if(!empty($param["sub_back_end_date"])){
                $nEndDate = strtotime($param["sub_back_end_date"])+86400;
                $where .= " and play_coupon_code.back_time <= {$nEndDate} ";
            }
        }

        if($param['user_phone']){
            $where .= " AND play_order_info.phone='{$param['user_phone']}'";
        }

        if($param['pay_status']){
            if($param['pay_status']==1){
                $where .= " AND play_order_info.pay_status=0";
            }else{
                if($param['pay_status']==5){
                    $where .= " AND play_coupon_code.test_status=5";
                }else{
                    $where .= " AND play_order_info.pay_status={$param['pay_status']}";
                }
            }
        }

        //经办人
        if($param['admin']){

            $where .= " AND play_admin.admin_name like '%{$param['admin']}%'";

        }

        if($param['ver_status']){
            if($param['ver_status']<2){
                $where .= " AND play_coupon_code.check_status < 2 ";
            }else{
                $where .= " AND play_coupon_code.check_status = 2 ";
            }
        }


        if($param['good_status']){
            $where .=  " AND play_organizer_game.is_together = 1";
            if ($param['good_status'] == 1) { //未开始
                $where .=  " AND play_organizer_game.status = 1 && play_organizer_game.up_time > ". time();
            } elseif ($param['good_status'] == 2) {// 在售卖
                $where .=  " AND play_organizer_game.status = 1 && play_organizer_game.up_time < ". time(). " && play_organizer_game.down_time > ". time();
            } elseif ($param['good_status'] == 4) {// 停止售卖
                $where .=  " AND play_organizer_game.status = 1 && play_organizer_game.foot_time > ". time(). " && play_organizer_game.down_time < ". time();
            } elseif ($param['good_status'] == 3) {// 停止使用
                $where .= " AND play_organizer_game.status = 1 && play_organizer_game.foot_time < ". time(). " && play_organizer_game.down_time < ". time();
            }
        }

        if($param['pay_type']){
            if ($param['pay_type'] == 1) {//支付宝
                $where .= " AND play_order_info.account_type = alipay";
            }

            if ($param['pay_type'] == 2) {//银联
                $where .= " AND play_order_info.account_type = union";
            }

            if ($param['pay_type'] == 3) {//微信网页
                $where .= " AND (play_order_info.account_type = weixin or play_order_info.account_type = jsapi)";
            }

            if ($param['pay_type'] == 4) {//账户
                $where .= " AND play_order_info.account_type = account";
            }

            if ($param['pay_type'] == 5) {//微信网页
                $where .= " AND play_order_info.account_type = weixinsdk";
            }
        }

        $data = $this->_getPlayOrderInfoTable()->getOrderList($start,$this->pageSum,array(),$where,$order,array())->toArray();
        $outdata = $this->_getPlayOrderInfoTable()->getOrderList(0,0,array(),$where,$order,array());
        $count = $outdata->count();
//        $count_e = $this->query("SELECT count(DISTINCT play_order_info.order_sn) as count_num,sum(play_order_info.coupon_unit_price*play_order_info.buy_number) as sum_income,sum(play_coupon_code.back_money) as sum_out FROM play_order_info
//            LEFT JOIN play_order_action ON  play_order_action.order_id = play_order_info.order_sn
//            LEFT JOIN play_order_otherdata ON  play_order_otherdata.order_sn = play_order_info.order_sn
//            LEFT JOIN play_coupon_code ON  play_coupon_code.order_sn = play_order_info.order_sn
//            LEFT JOIN play_order_info_game ON  play_order_info_game.order_sn = play_order_info.order_sn
//            LEFT JOIN play_organizer_game ON  play_organizer_game.id = play_order_info.coupon_id
//            LEFT JOIN play_game_info ON  play_game_info.id = play_order_info_game.game_info_id
//            LEFT JOIN play_contracts ON  play_contracts.id = play_organizer_game.contract_id
//            LEFT JOIN play_admin ON  play_admin.id = play_contracts.business_id
//            WHERE $where")->current();




//        $pay_e = $this->query("SELECT count(DISTINCT play_order_info.order_sn) as count_num FROM play_order_info
//            LEFT JOIN play_order_action ON  play_order_action.order_id = play_order_info.order_sn
//            LEFT JOIN play_order_otherdata ON  play_order_otherdata.order_sn = play_order_info.order_sn
//            LEFT JOIN play_coupon_code ON  play_coupon_code.order_sn = play_order_info.order_sn
//            LEFT JOIN play_order_info_game ON  play_order_info_game.order_sn = play_order_info.order_sn
//            LEFT JOIN play_organizer_game ON  play_organizer_game.id = play_order_info.coupon_id
//            LEFT JOIN play_game_info ON  play_game_info.id = play_order_info_game.game_info_id
//            LEFT JOIN play_contracts ON  play_contracts.id = play_organizer_game.contract_id
//            LEFT JOIN play_admin ON  play_admin.id = play_contracts.business_id
//            WHERE $where")->current();


//        $count = (int)$count_e['count_num'];
//        $sum_income = $count_e['sum_income'];
//        $out_sum = $count_e['sum_out'];
        //格式化数据
        $order_data = $this->FmtData($data);
        $out_data = $this->FmtData($outdata);
        $sum_income = $out_data['sum_income'];
        $out_sum = $out_data['sum_payment'];
        //创建分页
        $url = '/wftadlogin/finance/index';
        $paginator = new Paginator($page, $count, $this->pageSum, $url);
        return array(
            'data'=>$order_data['data'],
            'out_data'=>$out_data['data'],
            'pageData'=>$paginator->getHtml(),
            'order_sum' => $count,
            'pay_sum'=>(int)$count,
            'income'=>$sum_income,
            'outSum'=>$out_sum,
            'verifyStatus'=>$this->verifyStatus,
            'order_status'=>$this->_getConfig()['order_status'],
            'pay_type'=>$this->payType,
            'good_status'=>$this->goodsStatus,
        );

    }

    //普通订单审核
    public function auditOrderAction(){
        exit('已废弃');
        //获取搜索条件
        $page = (int)$this->getQuery('p', 1);
        $param = $this->GetSearchCondition();

        $check_status = (int)$this->getQuery('check_status',0);
        $admin = $this->getQuery('admin',null);
        $pay_status = (int)$this->getQuery('pay_status',0);
        $pay_type = trim($this->getQuery('pay_type',null));
        $where = "play_order_info.order_status = 1 and play_order_info.order_type = 2 and play_order_info.pay_status>1";
        $order = "play_order_info.order_sn DESC";
        $start = ($page - 1) * $this->pageSum;

        $city = $this->getAdminCity();
        $where .= " and play_order_info.order_city = '{$city}' ";

        //支付渠道
        if($pay_type){
            if($pay_type==1){
                $where .= " and play_order_info.account_type='alipay'";
            }
            if($pay_type==2){
                $where .= " and play_order_info.account_type='union'";
            }
            if($pay_type==3){
                $where .= " and play_order_info.account_type='weixin'";
            }
            if($pay_type==4){
                $where .= " and play_order_info.account_type='account'";
            }
        }

        //经办人
        if($admin){

        }

        //审核状态
        if($check_status){
            if($check_status==1){
                $where .= " AND play_coupon_code.check_status=1";
            }

            if($check_status==2){
                $where .= " AND play_coupon_code.check_status=2";
            }

        }

        //支付类型
        if($pay_status){
            if($pay_status==1){
                $where .= " AND play_coupon_code.status=0";
            }
            if($pay_status==2){
                $where .= " AND play_coupon_code.status=1";
            }
            if($pay_status==3){
                $where .= " AND play_coupon_code.status=3";
            }
            if($pay_status==4){
                $where .= " AND play_coupon_code.status=2";
            }
            if($pay_status==5){
                $where .= " AND play_coupon_code.test_status=3";
            }
            if($pay_status==6){
                $where .= " AND play_coupon_code.test_status=4";
            }
            if($pay_status==7){
                $where .= " AND play_coupon_code.test_status=5";
            }
        }

        if($param['game_name']){
            $where.= " AND play_order_info.coupon_name like '%".$param['game_name']."%'";
        }

        if($param['shop_id']){
            $where .= " AND play_order_info.shop_id={$param['shop_id']}";
        }

        if($param['user_id']){
            $where .= " AND play_order_info.user_id={$param['user_id']}";
        }

        if($param['order_sn']){
            $where .= " AND play_order_info.order_sn = {$param['order_sn']}";
        }

        if($param['city']){
            $where .= " AND play_order_info.order_city like '%{$param['city']}%'";
        }

        if(!empty($param["sub_back_start_date"]) && !empty($param["sub_back_end_date"])){
            $nStartDate = strtotime($param["sub_back_start_date"]);
            $nEndDate = strtotime($param["sub_back_end_date"])+86400;

            //容错起始时间大于结束时间
            if($nStartDate > $nEndDate){
                $where .= "  AND (play_coupon_code.back_time > {$nEndDate} AND play_coupon_code.back_time < {$nStartDate}) ";
            }else{
                $where .= "  AND (play_coupon_code.back_time > {$nStartDate} AND play_coupon_code.back_time < {$nEndDate}) ";
            }
        }else{
            if(!empty($param["sub_back_start_date"])){
                $nStartDate = strtotime($param["sub_back_start_date"]);
                $where .= " AND play_coupon_code.back_time > {$nStartDate} ";
            }
            if(!empty($param["sub_back_end_date"])){
                $nEndDate = strtotime($param["sub_back_end_date"])+86400;
                $where .= " AND play_coupon_code.back_time < {$nEndDate} ";
            }
        }


        //交易时间
        if(!empty($param["buy_start_date"]) && !empty($param["buy_end_date"])){
            $nStartDate = strtotime($param["buy_start_date"]);
            $nEndDate = strtotime($param["buy_end_date"])+86400;

            //容错起始时间大于结束时间
            if($nStartDate > $nEndDate){
                $where .= " AND (play_order_info.dateline >= {$nEndDate} AND play_order_info.dateline <= {$nStartDate}) ";
            }else{
                $where .= " AND (play_order_info.dateline >= {$nStartDate} AND play_order_info.dateline <= {$nEndDate}) ";
            }
        }else {
            if (!empty($param["buy_start_date"])) {
                $nStartDate = strtotime($param["buy_start_date"]);
                $where .= " AND play_order_info.dateline >= {$nStartDate} ";
            }
            if(!empty($param["buy_end_date"])){
                $nEndDate = strtotime($param["buy_end_date"])+86400;
                $where .= " AND play_order_info.dateline <= {$nEndDate} ";
            }
        }

        //结算时间
        if(!empty($param["close_start_time"]) && !empty($param["close_end_time"])){
            $nStartDate = strtotime($param["close_start_time"]);
            $nEndDate = strtotime($param["close_end_time"])+86400;

            //容错起始时间大于结束时间
            if($nStartDate > $nEndDate){
                $where .= " and (play_coupon_code.account_time >= {$nEndDate} and play_coupon_code.account_time <= {$nStartDate}) ";
            }else{
                $where .= " and (play_coupon_code.account_time >= {$nStartDate} and play_coupon_code.account_time <= {$nEndDate}) ";
            }
        }else {
            if (!empty($param["close_start_time"])) {
                $nStartDate = strtotime($param["close_start_time"]);
                $where .= " and play_coupon_code.account_time >= {$nStartDate} ";
            }
            if(!empty($param["close_end_time"])){
                $nEndDate = strtotime($param["close_end_time"])+86400;
                $where .= " and play_coupon_code.account_time <= {$nEndDate} ";
            }
        }

        $data = $this->_getPlayCouponCodeTable()->getAuditOrder($start,$this->pageSum,array(),$where,$order,array());

        $count_e = $this->query("SELECT count(DISTINCT play_coupon_code.id) as count_num,sum(play_order_info.account_money) as sum_income,sum(play_coupon_code.back_money)as sum_out FROM play_coupon_code
            LEFT JOIN play_order_info ON  play_order_info.order_sn = play_coupon_code.order_sn
            LEFT JOIN play_order_info_game ON  play_order_info_game.order_sn = play_order_info.order_sn
            LEFT JOIN play_game_info ON  play_game_info.id = play_order_info_game.game_info_id
            WHERE $where")->current();

        $count = (int)$count_e['count_num'];

         //$count = $this->_getPlayCouponCodeTable()->getAuditOrder(0,0,array(),$where,$order,array())->count();

        $data = $this->FmtData($data);
        //创建分页
        $url = '/wftadlogin/finance/auditOrder';
        $paginator = new Paginator($page, $count, $this->pageSum, $url);
        $all = (int)$this->getQuery('all', 0);
        $type = (int)$this->getQuery('type', 0);
        //批量操作
        if($all){
            $res = $this->approveAllAction($where,$type);
            return $this->_Goto($res);
        }


//        var_dump($data['data']);die();
        return array(
            'data'=>$data['data'],
            'sum_income'=>$count_e['sum_income'],
            'sum_out'=>$count_e['sum_out'],
            'count'=>$count,
            'pageData'=>$paginator->getHtml(),
        );

    }
    

    //财务审核
    public function approveAction() {
        exit('已废弃');
        $type = $this->getQuery('type');
        $id = $this->getQuery('id');
        $ids = explode('@', $id);

//        if($_COOKIE['group'] !=4){
//            return $this->_Goto("没有权限进行操作");
//        }
        if (!count($ids)) {
            return $this->_Goto('请选择要操作的订单');
        }

        if (!in_array($type, array(1,2,3,4,5,6,7,8))) {
            return $this->_Goto('非法操作');
        }

        if(!isset($_COOKIE['referer_url']))   {
            setcookie('referer_url', $_SERVER["HTTP_REFERER"]);
        }

        if ($type == 1) { //审批到账 财务
            $result = $this->approveAccount($ids);
        } elseif ($type == 2) { //受理退款  财务
            $result = $this->acceptBack($ids);
        } elseif ($type == 3) { //提交结算 财务 + 客服
            $result = $this->subAccount($ids);
        } elseif ($type == 4) { // 受理结算 财务
            $result = $this->acceptAccount($ids);
        } elseif ($type == 5) { // 结算 财务
            $result = $this->account($ids);
        } elseif ($type == 6) { //提交退款 客服
            $result = $this->subBack($ids);
        } elseif ($type == 7) { //确认退款 财务
            $result = $this->confirmBack($ids);
        } elseif ($type == 8) { //确认使用 客服
            $result = $this->confirmUse($ids);
        } else {
            exit('非法操作');
        }

        if ($result) {
            $url = $_COOKIE['referer_url'] ? $_COOKIE['referer_url'] : $_SERVER["HTTP_REFERER"];
            setcookie('referer_url', '', time()-3600);
            return $this->_Goto('执行完毕', $url);
        }
        exit;


    }

    //审批到账
    public function approveAccount($ids) {

        // todo 检测 是否可以审批到账
        $codeData = $this->_getPlayCouponCodeTable()->get(array('id' => (int)$ids[0]));
        if (!$codeData) {
            $show = '该订单不存在';
        } else {
            $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $codeData->order_sn));
            if($codeData->status==3){

              //  $this->backOk($codeData->order_sn,$codeData->id.$codeData->password);  //4小时候使用脚本退订

            }
//            //商家分润
//            if($codeData->status==1){
//                $this->distribute($codeData->id);
//            }
            if ($orderData->pay_status >= 1 && $codeData->check_status == 1) {
                $show = $codeData->id. $codeData->password. '使用码正在审批到账';
                //todo  执行结算操作
                $status = $this->_getPlayCouponCodeTable()->update(array('check_status' => 2), array('id' => $ids[0]));
                if ($status) {
                    // todo 写操作日志
                    $this->doLog($codeData, 1);
                } else {
                    $show = $show.  $codeData->id. $codeData->password. '使用码审批到账失败';
                    return $this->_Goto($show);
                }
            } else {
                $show = $codeData->id. $codeData->password. '使用码不符合审批到账的条件';
                return $this->_Goto($show);
            }
        }

        unset($ids[0]);
        if (count($ids)) {
            echo $show;
            $redirect = '/wftadlogin/finance/approve?type=1&id='. implode('@', $ids);
            echo "<script>setTimeout(function(){window.location.href='".$redirect."'}, 1000);</script>";
            return false;
        } else {
            return true;
        }
    }


    //受理退款
    public function acceptBack($ids) {

        // todo 检测 是否可以受理退款
        $codeData = $this->_getPlayCouponCodeTable()->get(array('id' => (int)$ids[0]));

        if (!$codeData) {
            $show = '该订单不存在';
        } else {
            $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $codeData->order_sn));
            if ($orderData->pay_status == 3 && $codeData->status == 3 && $codeData->test_status == 0 && $codeData->check_status == 2) {
                $show = $codeData->id. $codeData->password. '使用码正在受理退款';
                $status = $this->_getPlayCouponCodeTable()->update(array('test_status' => 1,'force'=>2), array('id' => $ids[0]));
                if ($status) {
                    // todo 写操作日志
                    $this->doLog($codeData, 2);
                } else {
                    $show = $show.  $codeData->id. $codeData->password. '使用码受理退款失败';
                }
            } elseif ($codeData->status == 1 && $codeData->test_status == 0 && $codeData->check_status == 2 && $codeData->force == 1) {
                //echo 34;
                $show = $codeData->id. $codeData->password. '使用码正在受理退款';

                $status = $this->_getPlayCouponCodeTable()->update(array('test_status' => 1, 'force' => 2), array('id' => $ids[0]));
                if ($status) {
                    // todo 写操作日志
                    $this->doLog($codeData, 8);
                } else {
                    $show = $show.  $codeData->id. $codeData->password. '使用码受理退款失败';
                }

            }  else {
                $show = $codeData->id. $codeData->password. '使用码不符合受理退款的条件';
            }
        }

        unset($ids[0]);

        if (count($ids)) {
            echo $show;
            $redirect = '/wftadlogin/finance/approve?type=2&id='. implode('@', $ids);
            echo "<script>setTimeout(function(){window.location.href='".$redirect."'}, 1000);</script>";
            return false;
        } else {
            return true;
        }
    }


    //批准结算
    public function subAccount($ids) {
        // todo 检测 是否可以批准结算
        $codeData = $this->_getPlayCouponCodeTable()->get(array('id' => (int)$ids[0]));

        if (!$codeData) {
            $show = '该订单不存在';
        } else {
            if (($codeData->status == 1 || $codeData->status == 2) && $codeData->check_status == 2) {
                $show = $codeData->id. $codeData->password. '使用码正在批准结算';
                //todo  执行结算操作
                $status = $this->_getPlayCouponCodeTable()->update(array('test_status' => 3), array('id' => $ids[0]));
                if ($status) {
                    // todo 写操作日志
                    $this->doLog($codeData, 3);
                } else {
                    $show = $show.  $codeData->id. $codeData->password. '使用码批准结算失败';
                }
            } else {
                $show = $codeData->id. $codeData->password. '使用码不符合批准结算的条件';
            }
        }

        unset($ids[0]);

        if (count($ids)) {
            echo $show;
            $redirect = '/wftadlogin/finance/approve?type=3&id='. implode('@', $ids);
            echo "<script>setTimeout(function(){window.location.href='".$redirect."'}, 1000);</script>";
            return false;
        } else {
            return true;
        }
    }


    //受理结算
    public function acceptAccount($ids){
        // todo 检测 是否可以受理结算
        $codeData = $this->_getPlayCouponCodeTable()->get(array('id' => (int)$ids[0]));

        if (!$codeData) {
            $show = '该订单不存在';
        } else {
            if ($codeData->test_status == 3) {
                $show = $codeData->id. $codeData->password. '使用码正在受理结算';
                //todo  执行结算操作
                $status = $this->_getPlayCouponCodeTable()->update(array('test_status' => 4), array('id' => $ids[0]));
                if ($status) {
                    // todo 写操作日志
                    $this->doLog($codeData, 4);
                } else {
                    $show = $show.  $codeData->id. $codeData->password. '使用码受理结算失败';
                }
            } else {
                $show = $codeData->id. $codeData->password. '使用码不符合受理结算的条件';
            }
        }

        unset($ids[0]);

        if (count($ids)) {
            echo $show;
            $redirect = '/wftadlogin/finance/approve?type=4&id='. implode('@', $ids);
            echo "<script>setTimeout(function(){window.location.href='".$redirect."'}, 1000);</script>";
            return false;
        } else {
            return true;
        }
    }


    //结算
    public function account($ids) {
        // todo 检测 是否可以结算
        $codeData = $this->_getPlayCouponCodeTable()->get(array('id' => (int)$ids[0]));

        if (!$codeData) {
            $show = '该订单不存在';
        } else {
            if ($codeData->test_status == 4) {
                $show = $codeData->id. $codeData->password. '使用码正在结算';
                //todo  执行结算操作
                $status = $this->_getPlayCouponCodeTable()->update(array('test_status' => 5, 'account_time' => time()), array('id' => $ids[0]));
                if ($status) {
                    // todo 写操作日志
                    $this->doLog($codeData, 5);
                } else {
                    $show = $show.  $codeData->id. $codeData->password. '使用码结算失败';
                }
            } else {
                $show = $codeData->id. $codeData->password. '使用码不符合结算的条件';
            }
        }

        unset($ids[0]);

        if (count($ids)) {
            echo $show;
            $redirect = '/wftadlogin/finance/approve?type=5&id='. implode('@', $ids);
            echo "<script>setTimeout(function(){window.location.href='".$redirect."'}, 1000);</script>";
            return false;
        } else {
            return true;
        }
    }


    //提交退费
    public function subBack($ids, $money = false) {

        // todo 检测 是否可以提交退费 以付款 且 待使用
        $codeData = $this->_getPlayCouponCodeTable()->get(array('id' => (int)$ids[0]));
        if (!$codeData) {
            $show = '该订单不存在';
        } else {
            $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $codeData->order_sn));

            if ($orderData->pay_status > 1 && $codeData->status == 0) {
                $show = $codeData->id. $codeData->password. '使用码正在提交退款';
                $result = $this->backIng($codeData->order_sn, $codeData->id. $codeData->password, 2, $money);
                if ($result['status'] == 0) {
                    $show = $show. $result['message'];
                }
            } else {
                $show = $codeData->id. $codeData->password. '使用码不符合提交退费的条件';
            }
        }

        unset($ids[0]);

        if (count($ids)) {
            echo $show;
            $redirect = '/wftadlogin/finance/approve?type=6&id='. implode('@', $ids);
            echo "<script>setTimeout(function(){window.location.href='".$redirect."'}, 1000);</script>";
            return false;
        } else {
            return true;
        }
    }


    //确认退款
    public function confirmBack($ids) {

        // todo 检测 是否可以确认退费 以付款 且 待使用
        $codeData = $this->_getPlayCouponCodeTable()->get(array('id' => (int)$ids[0]));
        if (!$codeData) {
            $show = '该订单不存在';
        } else {
            $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $codeData->order_sn));
            if ($orderData->pay_status == 3 && $codeData->status == 3 && $codeData->test_status == 1) {
                $show = $codeData->id. $codeData->password. '使用码正在退款';
                $result = $this->backOk($codeData->order_sn, $codeData->id. $codeData->password);
                if ($result['status'] == 0) {
                    $show = $show. $result['message'];
                }
            } elseif($codeData->status == 1 && $codeData->test_status == 1 && $codeData->check_status == 2 && $codeData->force == 2) {
                $show = $codeData->id. $codeData->password. '使用码正在退款';

                $status = $this->_getPlayCouponCodeTable()->update(array('force' => 3, 'test_status' => 2), array('id' => $ids[0]));
                if ($status) {
                    $this->backOk($codeData->order_sn,$codeData->id. $codeData->password,2);
                    // todo 写操作日志
                    $this->doLog($codeData,7);

                    // todo 发送短信
                    $use_code = $ids[0]. $codeData->password;
//                    SendMessage::Send($orderData->buy_phone, "“玩翻天”终于等到你,下次可否别放弃?!您购买的宝贝\"{$orderData->coupon_name}\"兑换码为\"{$use_code}\"的退订业务受理成功");
                } else {
                    $show = $show. '退款失败';
                }

            } else {
                $show = $codeData->id. $codeData->password. '使用码不符合退费的条件';
            }
        }

        unset($ids[0]);

        if (count($ids)) {
            echo $show;
            $redirect = '/wftadlogin/finance/approve?type=7&id='. implode('@', $ids);
            echo "<script>setTimeout(function(){window.location.href='".$redirect."'}, 1000);</script>";
            return false;
        } else {
            return true;
        }
    }


    //确认使用
    public function confirmUse($ids) {
        // todo 检测 是否可以确认使用
        $codeData = $this->_getPlayCouponCodeTable()->get(array('id' => (int)$ids[0]));
        if (!$codeData) {
            $show = '该订单不存在';
        } else {
            $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $codeData->order_sn));
            if ($orderData->pay_status > 1 && $codeData->status == 0) {
                $show = $codeData->id. $codeData->password. '使用码正在确认使用';
                $result = $this->UseCode($_COOKIE['id'], 3, $codeData->id. $codeData->password);
                if ($result['status'] == 0) {
                    $show = $show. $result['message'];
                }
            } else {
                $show = $codeData->id. $codeData->password. '使用码不符合确认使用的条件';
            }
        }

        unset($ids[0]);

        if (count($ids)) {
            echo $show;
            $redirect = '/wftadlogin/finance/approve?type=8&id='. implode('@', $ids);
            echo "<script>setTimeout(function(){window.location.href='".$redirect."'}, 1000);</script>";
            return false;
        } else {
            return true;
        }
    }


    //批量操作
    public function approveAllAction($where=array(),$type){
        $message = '非法操作';
        //批量审批
        if ($type == 1) {//审批到账

            $approve_sql = "SELECT
	play_coupon_code.id,
	play_coupon_code.password,
	play_order_info.order_sn
FROM
	play_coupon_code
LEFT JOIN play_order_info ON  play_coupon_code.order_sn = play_order_info.order_sn
WHERE
	 $where AND play_order_info.pay_status >= 2 AND play_coupon_code.check_status = 1";

            $approve_data = $this->query($approve_sql);

            if ($approve_data->count() > 3000) {
                return  '要处理的数据太多了';
            }

            if ($approve_data->count() < 1) {
                return  '没有符合的数据去处理';
            }

            $config = $this->_getConfig()['db'];
            $adapter = $this->_getAdapter();
            $conObj = $adapter->getDriver()->getConnection();
            $conObj->beginTransaction();
            //更新
            $sql = "UPDATE play_coupon_code
LEFT JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
SET play_coupon_code.check_status = 2
WHERE
$where AND play_order_info.pay_status >= 2 AND play_coupon_code.check_status = 1
";
            $res = $adapter->query($sql);

            if (!$res) {
                $conObj->rollBack();
                $message = '没有符合的数据处理';
                return $message;
            }

            //插入更新记录记录
            $timer = time();
            $i = 0;
            $insert_sql = "INSERT play_order_action (`action_user`, `order_id`, `play_status`, `action_note`, `dateline`, `action_user_name`) VALUES ";
            foreach ($approve_data as $up) {
                $action_note = '使用码'. $up['password'].' 审批到账_批量';
                $action_user_name = '管理员'. $_COOKIE['user'];
                if (!$i) {
                    $insert_sql = $insert_sql . "({$_COOKIE['id']}, {$up['order_sn']}, 6, '{$action_note}', {$timer}, '{$action_user_name}')";
                } else {
                    $insert_sql = $insert_sql . ", ({$_COOKIE['id']}, {$up['order_sn']}, 6, '{$action_note}', {$timer}, '{$action_user_name}')";
                }
                $i ++;
            }

            $insert = $adapter->query($insert_sql);
            if (!$insert) {
                $conObj->rollback();
                $message = "操作失败";
                return $message;
            }

            $conObj->commit();
            $message = '处理成功';
            return $message;
        }

        if ($type == 2) {//受理结算

            $approve_sql = "SELECT
	play_coupon_code.id,
	play_coupon_code.password,
	play_order_info.order_sn
FROM
	play_coupon_code
LEFT JOIN play_order_info ON  play_coupon_code.order_sn = play_order_info.order_sn
WHERE
	 $where AND play_coupon_code.test_status = 3";

            $approve_data = $this->query($approve_sql);

            if ($approve_data->count() > 3000) {
                return  '要处理的数据太多了';
            }

            if ($approve_data->count() < 1) {
                return  '没有符合的数据去处理';
            }

            $adapter = $this->_getAdapter();
            $conObj = $adapter->getDriver()->getConnection();
            $conObj->beginTransaction();
            //更新
            $sql = "UPDATE play_coupon_code
LEFT JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
SET play_coupon_code.test_status = 4
WHERE
  $where AND play_coupon_code.test_status = 3";
            $res = $adapter->query($sql);

            if (!$res) {
                $conObj->rollBack();
                $message = '没有符合的数据处理';
                return $message;
            }

            //插入更新记录记录
            $timer = time();
            $i = 0;
            $insert_sql = "INSERT play_order_action (`action_user`, `order_id`, `play_status`, `action_note`, `dateline`, `action_user_name`) VALUES ";
            foreach ($approve_data as $up) {
                $action_note = '使用码'. $up['password'].' 受理结算_批量';
                $action_user_name = '管理员'. $_COOKIE['user'];
                if (!$i) {
                    $insert_sql = $insert_sql . "({$_COOKIE['id']}, {$up['order_sn']}, 9, '{$action_note}', {$timer}, '{$action_user_name}')";
                } else {
                    $insert_sql = $insert_sql . ", ({$_COOKIE['id']}, {$up['order_sn']}, 9, '{$action_note}', {$timer}, '{$action_user_name}')";
                }
                $i ++;
            }

            $insert = $adapter->query($insert_sql);
            if (!$insert) {
                $conObj->rollback();
                $message = '操作失败';
                return $message;
            }

            $conObj->commit();
            $message = '处理成功';
            return $message;

        }

        if ($type == 3) {//结算成功

            $approve_sql = "SELECT
	play_coupon_code.id,
	play_coupon_code.password,
	play_order_info.order_sn
FROM
	play_coupon_code
LEFT JOIN play_order_info ON  play_coupon_code.order_sn = play_order_info.order_sn
WHERE
	 $where AND play_coupon_code.test_status = 4";

            $approve_data = $this->query($approve_sql);

            if ($approve_data->count() > 3000) {
                return  '要处理的数据太多了';
            }

            if ($approve_data->count() < 1) {
                return  '没有符合的数据去处理';
            }

            $adapter = $this->_getAdapter();
            $conObj = $adapter->getDriver()->getConnection();
            $conObj->beginTransaction();
            //更新
            $sql = "UPDATE play_coupon_code
LEFT JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
SET play_coupon_code.test_status = 5
WHERE
  $where AND play_coupon_code.test_status = 4";
            $res = $adapter->query($sql);

            if (!$res) {
                $conObj->rollBack();
                $message = '没有符合的数据处理';
                return $message;
            }

            //插入更新记录记录
            $timer = time();
            $i = 0;
            $insert_sql = "INSERT play_order_action (`action_user`, `order_id`, `play_status`, `action_note`, `dateline`, `action_user_name`) VALUES ";
            foreach ($approve_data as $up) {
                $action_note = '使用码'. $up['password'].' 结算成功_批量';
                $action_user_name = '管理员'. $_COOKIE['user'];
                if (!$i) {
                    $insert_sql = $insert_sql . "({$_COOKIE['id']}, {$up['order_sn']}, 10, '{$action_note}', {$timer}, '{$action_user_name}')";
                } else {
                    $insert_sql = $insert_sql . ", ({$_COOKIE['id']}, {$up['order_sn']}, 10, '{$action_note}', {$timer}, '{$action_user_name}')";
                }
                $i ++;
            }

            $insert = $adapter->query($insert_sql);
            if (!$insert) {
                $conObj->rollback();
                $message = '操作失败';
                return $message;
            }

            $conObj->commit();
            $message = '处理成功';
            return $message;

        }

        return $message;
    }



    //合同审核
    public function contractAction(){
        $page = (int)$this->getQuery('p', 1);
        $start = ($page - 1) * $this->pageSum;
        $params = $this->GetSearchCondition();
        $where = "play_contracts.id > 0";
        $order = "play_contracts.id DESC";

        if($params['create_time']){
            $where .=" AND play_contracts.create_time > ". strtotime($params['create_time']);
        }

        if($params['city']){
            $where .=" AND play_contracts.city = {$params['city']}";
        }

        if($params['contract_no']){
            $where .= " AND play_contracts.contract_no = '{$params['contract_no']}'";
        }

        if($params['operator']){
            $where .= " AND play_contracts.business_id in (SELECT id FROM play_admin WHERE admin_name like '%{$params['operator']}%')";
        }

        if($params['organizer']){
            $where .= " AND play_contracts.mid in (SELECT id FROM play_organizer WHERE name like '%{$params['organizer']}%')";
        }

        if($params['organizer_id']){
            $where .= " AND play_contracts.mid = '{$params['organizer_id']}'";
        }

        if($params['contract_type']){
            $where .= " AND play_contracts.contracts_type = '{$params['organizer_id']}'";
        }

        if($params['contract_status']){
            $where .= " AND play_contracts.contracts_status = '{$params['contract_status']}'";
        }

        $contract_data = $this->_getPlayContractsTable()->getContractList($start,$this->pageSum,array(),$where,$order);

        $count = M::getAdapter()->query("SELECT count(*) as c FROM play_contracts WHERE $where",array())->current()->c;

        //创建分页
        $url = '/wftadlogin/finance/contract';

        $paginator = new Paginator($page, $count, $this->pageSum, $url);

        //合同商品的销量情况
        $contractObj = new Contract();
        $order_data = $contractObj->getContractSaleData();


        foreach($contract_data as $key=>$val){
            foreach($order_data as $k=>$v){
                if($order_data[$k]['contract_id']==$contract_data[$key]['id']){
                    @$contract_data[$key]['order_num'] = $order_data[$k]['order_num'];
                    @$contract_data[$key]['shop_pre_income'] = $contract_data[$key]['contracts_type'] < 3 ? $contract_data[$key]['pre_money'] : $order_data[$k]['pre_income'];
                    @$contract_data[$key]['deyi_income'] = $contract_data[$key]['contracts_type'] < 3 ? ($order_data[$k]['total_money']-$contract_data[$key]['pre_money']) : ($order_data[$k]['total_money']-$order_data[$k]['total_account']);
                    @$contract_data[$key]['shop_real_income'] = $contract_data[$key]['contracts_type']<3 ? ($contract_data[$key]['pay_pre_status']==2 ? $contract_data[$key]['pre_money'] : 0) : $order_data[$k]['total_account'];
                }
            }
        }

        // 获取合同关联商品信息 end
        $contractData = array();
        foreach ($contract_data as $contract) {
            $contractData[] = array(
                'id' => $contract['id'],
                'contract_no' => $contract['contract_no'],
                'create_time' => $contract['create_time'],
                'admin_name' => $contract['admin_name'],
                'organizer_name' => $contract['name'],
                'contracts_type' => $contract['contracts_type'],
                'pre_money' => $contract['pre_money'],
                'contracts_status' => $contract['contracts_status'],
                'check_status' => $contract['check_status'],
                'status' => $contract['status'],
                'goods_num' => $contract['goods_num'],
                'order_num' => $contract['order_num'],
                'shop_pre_income' => $contract['shop_pre_income']!='' ? $contract['shop_pre_income'] : 0,
                'shop_real_income' => $contract['shop_real_income']!='' ? $contract['shop_real_income'] : 0,
                'deyi_income' => $contract['deyi_income']!='' ? $contract['deyi_income'] : 0,
                'sub_dateline' => $contract['check_dateline'],
                'pay_pre_status'=>$contract['pay_pre_status']
            );
        }

        return array(
            'data'=>$contractData,
            'pageData'=>$paginator->getHtml(),
            'contract_status'=>array(
                //合同状态
                'not_approved' => '未审批',
                'sub_approved'=>'已提交审批',
                'pre_money_checked'=>'预付金审批已通过 ',
                'pre_money_paid'=>'已付预付金',
                'is_act'=>'正在生效',
                'sub_end'=>'已提交结束',
                'ended'=>'已结束',
                'sub_stop'=>'已提交终止',
                'stopped'=>'已终止',
            ),
            'contract_type'=>array(
                '1'=>'包销',
                '2'=>'自营',
                '3'=>'代销'
            ),
            'stop_reason'=>array(
                '1'=>'商家违约',
                '2'=>'主动终止',
            )

        );


    }


    //审批合同
    public function approveContractAction(){
        $type = $this->getQuery('type',0);
        $id = $this->getQuery('id',0);
        //是否强制结束合同
        $force = (int)$this->getQuery('force',0);
        $reason = trim($this->getQuery('reason',null));
        if (!count($id)) {
            return $this->_Goto('请选择要操作的合同');
        }

        if (!in_array($type, array(1,2,3,4,5,6,7))) {
            return $this->_Goto('非法操作');
        }

        if(!isset($_COOKIE['referer_url']))   {
            setcookie('referer_url', $_SERVER["HTTP_REFERER"]);
        }

        switch($type){
            case 1:
                $result = $this->approveBy($id);//审批通过
            break;
            case 2:
                $result = $this->approvePre($id);//批准预付金
            break;
            case 3:
                $result = $this->approveStop($id,$reason);//审批终止
            break;
            case 4:
                $result = $this->approvePay($id);//批准结款
            break;
            case 5:
                $result = $this->subEnd($id,$force);//提交结束
            break;
            case 6:
                $result = $this->subStop($id);//提交终止
            break;
            case 7:
                $result = $this->doEnd($id,$force);//确认结束
            break;
            default:
                exit("非法操作");
        }

        if ($result) {
            return $this->_Goto($result);
        }
        exit;
    }

    //审批通过 ---代销合同
    public function approveBy($id){
        $contract_data = $this->_getPlayContractsTable()->get(array('id'=>(int)$id));
        if(!$contract_data){
            return "合同不存在";
        }else{
            if($contract_data->status==1 and $contract_data->check_status<2 and $contract_data->contracts_type==3){
                $status = $this->_getPlayContractsTable()->update(array('check_status'=>2,'contracts_status'=>'is_act','check_dateline'=>time()),array('id'=>$id));
                if($status){
                    $account_data = $this->_getPlayOrganizerAccountTable()->get(array('organizer_id'=>$contract_data->mid));
                    $this->_getPlayOrganizerAccountTable()->update(array('use_money'=>$account_data->use_money+$account_data->not_use_money,'not_use_money'=>0),array('organizer_id'=>$contract_data->mid));
                    $this->doContractLog($contract_data,2);
                   return '合同编号'.$contract_data->contract_no.'审批通过';
                }else{
                    return '合同编号'.$contract_data->contract_no.'审批未通过';
                }
            }else{
                return '合同编号'.$contract_data->contract_no.'不符合审批通过的条件';
            }
        }

    }

    //批准预付金-----自营和包销合同
    /*public function approvePre($id){
        $contract_data = $this->_getPlayContractsTable()->get(array('id'=>(int)$id));
        if(!$contract_data){
            return "合同不存在";
        }else{
            if($contract_data->status==1 and $contract_data->check_status < 2 and $contract_data->contracts_type<3 and $contract_data->pay_pre_status<2){
                $status = $this->_getPlayContractsTable()->update(array('check_status'=>2,'contracts_status'=>'pre_money_checked','check_dateline'=>time()),array('id'=>$id));

                if($status){
                    $this->doContractLog($contract_data,3);
                    if ($contract_data->contracts_type == 1) {
                        if ($contract_data->pre_money > 0) {
                            $organizerAccount = new OrganizerAccount();
                            //$tip = $organizerAccount->profits($contract_data->mid, $action_type = 1, $object_type = 2, 0, $contract_data->pre_money, $desc = '预付款到账',  $status = 1, $id);
                            if ($tip) {
                                /$organizerAccount->takeCrash($contract_data->mid, $action_type = 2, $object_type = 1, 0, $contract_data->pre_money, '预付款提现',  $status = 3, $id);
                            }
                        } else {
                            $this->_getPlayContractsTable()->update(array('pay_pre_status'=>2),array('id'=>$id));
                        }

                    }
                    return '合同编号'.$contract_data->contract_no.'批准预付金成功';
                }else{
                    return '合同编号'.$contract_data->contract_no.'批准预付金失败';
                }
            }else{
                return '合同编号'.$contract_data->contract_no.'不符合批准预付金的条件';
            }
        }

    }*/

    //提交终止-------包销
    public function subStop($id){
        $contract_data = $this->_getPlayContractsTable()->get(array('id'=>$id));
        if(!$contract_data){
            return "合同不存在";
        }else{
            if($contract_data->status==1 and $contract_data->check_status==2 and $contract_data->pay_pre_status>1 and $contract_data->contracts_type==1){
                $status = $this->_getPlayContractsTable()->update(array('status'=>4,'contracts_status'=>'sub_stop','check_dateline'=>time()),array('id'=>$id));
                if($status){
                    $this->doContractLog($contract_data,8);
                    return '合同编号'.$contract_data->contract_no.'提交终止成功';
                }else{
                    return '合同编号'.$contract_data->contract_no.'提交终止失败';
                }
            }else{
                return '合同编号'.$contract_data->contract_no.'不符合提交终止的条件';
            }
        }
    }


    //审批终止----包销
    public function approveStop($id,$reason){
        $contract_data = $this->_getPlayContractsTable()->get(array('id'=>$id));
        if(!$contract_data){
            return "合同不存在";
        }else{
            if($contract_data->status==4 and $contract_data->contracts_type==1){
                $status = $this->_getPlayContractsTable()->update(array('status'=>0,'contracts_status'=>'stopped','pay_pre_status'=>3,'stop_result'=>$reason,'check_dateline'=>time()),array('id'=>$id));
                if($status){
                    $this->doContractLog($contract_data,5);
                    return '合同编号'.$contract_data->contract_no.'审批终止成功';
                }else{
                    return '合同编号'.$contract_data->contract_no.'审批终止失败';
                }
            }else{
                return '合同编号'.$contract_data->contract_no.'不符合审批终止的条件';
            }
        }
    }


    //提交结束----
    public function subEnd($id,$force=false){
        $contract_data = $this->_getPlayContractsTable()->get(array('id'=>$id));
        if(!$contract_data){
            return "合同不存在";
        }else{
            //强制结束合同
            if($force){
                $status = $this->_getPlayContractsTable()->update(array('contracts_status'=>'sub_end','status'=>3,'check_dateline'=>time()),array('id'=>(int)$id));
                $this->doContractLog($contract_data,7);
                return '合同编号'.$contract_data->contract_no.'提交结束成功';
            }else{
                //获取该合同下商品是否已售空
                $sql = "select buy_num,ticket_num from play_organizer_game where contract_id={$id}";
                $game_data = $this->query($sql);
                $game_count=0;
                foreach($game_data as $game){
                   $game_count += $game['ticket_num']-$game['buy_num'];
                }
                if(($contract_data->status==1 and $contract_data->check_status==2 and $contract_data->pay_pre_status>1) and ($contract_data->end_time<time() or $game_count==0)){
                    $status = $this->_getPlayContractsTable()->update(array('contracts_status'=>'sub_end','status'=>3,'check_dateline'=>time()),array('id'=>(int)$id));
                    if($status){
                        $this->doContractLog($contract_data,7);
                        return '合同编号'.$contract_data->contract_no.'提交结束成功';
                    }else{
                        return '合同编号'.$contract_data->contract_no.'提交结束失败';
                    }
                }else{
                    return '合同编号'.$contract_data->contract_no.'不符合提交结束的条件';
                }
            }
        }
    }

    //确认结束------自营和代销
    public function doEnd($id,$force=null){
        $contract_data = $this->_getPlayContractsTable()->get(array('id'=>$id));
        if(!$contract_data){
            return "合同不存在";
        }else{
            //强制结束合同
            if($force){
                $status = $this->_getPlayContractsTable()->update(array('contracts_status'=>'end','status'=>2,'pay_pre_status'=>3,'check_dateline'=>time()),array('id'=>(int)$id));
                $this->doContractLog($contract_data,4);
                return '合同编号'.$contract_data->contract_no.'确认结束成功';
            }else{
                //获取该合同下商品是否已售空
                $sql = "select buy_num,ticket_num from play_organizer_game where contract_id={$id}";
                $game_data = $this->query($sql);
                $game_count=0;
                foreach($game_data as $game){
                    $game_count += $game['ticket_num']-$game['buy_num'];
                }
                if(($contract_data->status==3 and $contract_data->check_status==2) and ($contract_data->end_time<time() or $game_count==0) and $contract_data->contracts_type != 1){

                    $status = $this->_getPlayContractsTable()->update(array('contracts_status'=>'end','status'=>2,'check_dateline'=>time(),'pay_pre_status'=>3),array('id'=>(int)$id));
                    if($status){
                        $this->doContractLog($contract_data,4);
                        return '合同编号'.$contract_data->contract_no.'确认结束成功';
                    }else{
                        return '合同编号'.$contract_data->contract_no.'确认结束失败';
                    }
                }else{
                    return '合同编号'.$contract_data->contract_no.'不符合确认结束的条件';
                }
            }
        }
    }
    //批准结款----包销
    public function approvePay($id){
        $contract_data = $this->_getPlayContractsTable()->get(array('id'=>(int)$id));
        if(!$contract_data){
            return "合同不存在";
        }else{
            if($contract_data->status==3 and $contract_data->check_status==2 and $contract_data->contracts_type==1){
                $status = $this->_getPlayContractsTable()->update(array('contracts_status'=>'ended','status'=>2,'check_dateline'=>time(),'pay_pre_status'=>3),array('id'=>$id));
                if($status){
                    $this->doContractLog($contract_data,6);
                    return '合同编号'.$contract_data->contract_no.'批准结款成功';
                }else{
                    return '合同编号'.$contract_data->contract_no.'批准结款失败';
                }
            }else{
                return '合同编号'.$contract_data->contract_no.'不符合批准结款的条件';
            }
        }
    }


    //商家提现审批
    public function withdrawsAction()
    {
        $page = (int)$this->getQuery('p', 1);
        $start = ($page - 1) * $this->pageSum;
        $params = $this->GetSearchCondition();

        $where = " play_organizer_account_log.id > 0 and play_organizer_account_log.object_type=2";
        $order = " play_organizer_account_log.id DESC";

        if($params['city']){
            $where .= " and play_organizer.city='{$params['city']}'";
        }

        //申请提现时间筛选
        if(!empty($params["sub_start_time"]) && !empty($params["sub_end_time"])){
            $nStartDate = strtotime($params["sub_start_time"]);
            $nEndDate = strtotime($params["sub_end_time"])+86400;

            //容错起始时间大于结束时间
            if($nStartDate > $nEndDate){
                $where .= " and play_organizer_account_log.object_type=2 and (play_organizer_account_log.dateline >= {$nEndDate} and play_organizer_account_log.dateline <= {$nStartDate}) ";
            }else{
                $where .= " and play_organizer_account_log.object_type=2 and (play_organizer_account_log.dateline >= {$nStartDate} and play_organizer_account_log.dateline <= {$nEndDate}) ";
            }
        }else{
            if(!empty($params["sub_start_time"])){
                $nStartDate = strtotime($params["sub_start_time"]);
                $where .= " and play_organizer_account_log.object_type=2 and play_organizer_account_log.dateline >= {$nStartDate} ";
            }

            if(!empty($params["sub_end_time"])){
                $nEndDate = strtotime($params["sub_end_time"])+86400;
                $where .= " and play_organizer_account_log.object_type=2 and play_organizer_account_log.dateline <= {$nEndDate} ";
            }
        }

        if($params['shop_name']){
            if(is_numeric($params['shop_name'])){
                $where .= " and play_organizer_account_log.oid={$params['shop_name']}";
            }else{
                $where .= " and play_organizer_account_log.oid in (select id from play_organizer where name like '%{$params['shop_name']}%')";
            }
        }

        //提现时间筛选
        if(!empty($params["withdraws_start_time"]) && !empty($params["withdraws_end_time"])){
            $nStartDate = strtotime($params["withdraws_start_time"]);
            $nEndDate = strtotime($params["withdraws_end_time"])+86400;

            //容错起始时间大于结束时间
            if($nStartDate > $nEndDate){
                $where .= " and play_organizer_account_log.object_type=3 and play_organizer_account_log.status=1 and (play_organizer_account_log.dateline >= {$nEndDate} and play_organizer_account_log.dateline <= {$nStartDate}) ";
            }else{
                $where .= " and play_organizer_account_log.object_type=3 and play_organizer_account_log.status=1 and (play_organizer_account_log.dateline >= {$nStartDate} and play_organizer_account_log.dateline <= {$nEndDate}) ";
            }
        }else{
            if(!empty($params["withdraws_start_time"])){
                $nStartDate = strtotime($params["withdraws_start_time"]);
                $where .= " and play_organizer_account_log.object_type=3 and play_organizer_account_log.status=1 and play_organizer_account_log.dateline >= {$nStartDate} ";
            }

            if(!empty($params["withdraws_end_time"])){
                $nEndDate = strtotime($params["withdraws_end_time"])+86400;
                $where .= " and play_organizer_account_log.object_type=3 and play_organizer_account_log.status=1 and play_organizer_account_log.dateline <= {$nEndDate} ";
            }
        }

        $sql = "select play_organizer_account.* ,
        play_organizer_account_log.flow_money,
        play_organizer_account_log.id,
        play_organizer_account_log.object_type,
        play_organizer_account_log.status,
        play_organizer_account_log.dateline,
        play_organizer_account_log.sub_dateline,
        play_organizer.city
        from play_organizer_account_log
        left join play_organizer_account on play_organizer_account.organizer_id=play_organizer_account_log.oid
        left join play_organizer on play_organizer.id=play_organizer_account.organizer_id
        where $where order by $order
        ";

        $data = $this->query($sql."limit {$start} , {$this->pageSum}");
        $count = $this->query($sql)->count();

//        foreach($data as $v){
//            var_dump($v);
//        }
        //创建分页
        $url = '/wftadlogin/finance/withdraws';

        $paginator = new Paginator($page, $count, $this->pageSum, $url);

        return array(
            'data'=>$data,
            'status'=>array(
                '2'=>'申请审批',
                '3'=>'审批通过',
                '4'=>'已到账',
            ),
            'pageData'=>$paginator->getHtml(),
        );
    }

    //dowithdrawsAction商家提现审批

    public function doWithdrawsAction(){
        $type = (int)$this->getQuery('type',0);
        $id = $this->getQuery('id',0);
        $ids = explode('@',$id);
        if(!count($ids)){
            return $this->_Goto('请选择要操作的订单');
        }
        if(!in_array($type,array(1,2))){
            return $this->_Goto('非法操作');
        }

        if(!isset($_COOKIE['referer_url']))   {
            setcookie('referer_url', $_SERVER["HTTP_REFERER"]);
        }
        switch($type){
            case 1:
                $result = $this->approveWithdraws($ids);//审批提现通过
                break;
            case 2:
                $result = $this->cashArrival($ids);//提现到账
                break;
            default:
                $result = '非法操作';
        }

        if ($result) {
            $url = $_COOKIE['referer_url'] ? $_COOKIE['referer_url'] : $_SERVER["HTTP_REFERER"];
            setcookie('referer_url', '', time()-3600);
            return $this->_Goto('执行完毕', $url);
        }
        exit;

    }

    //审批提现通过
    public function approveWithdraws($ids){
        $account_data = $this->_getPlayOrganizerAccountLogTable()->get(array('id'=>(int)$ids[0]));
        if(!$account_data){
            $show = '数据不存在';
        }else{
            if($account_data->object_type==2 and $account_data->status==2){
                $show = "正在审批通过";
                $status = $this->_getPlayOrganizerAccountLogTable()->update(array('status'=>3),array('id'=>(int)$ids[0]));
                if($status){
                    $this->adminLog('审批商家提现','organizer_account',$ids[0]);
                }else{
                    $show = "审批失败";
                }
            }else{
                $show = "没有符合审批条件的提现数据";
            }
        }
        unset($ids[0]);

        if (count($ids)) {
            echo $show;
            $redirect = '/wftadlogin/finance/doWithdraws?type=1&id='.implode('@',$ids);
            echo "<script>setTimeout(function(){window.location.href='".$redirect."'}, 1000);</script>";
            return false;
        } else {
            return true;
        }
    }

    //商家提现到账操作
    public function cashArrival($ids){
        $account_data = $this->_getPlayOrganizerAccountLogTable()->get(array('id'=>(int)$ids[0]));
        if(!$account_data){
            $show = '数据不存在';
        }else{
            if($account_data->object_type==2 and $account_data->status==3){
                $show = "正在提现到账";
                $status = $this->_getPlayOrganizerAccountLogTable()->update(array('status'=>4),array('id'=>(int)$ids[0]));
                if($status){
                    $balance = $this->_getPlayOrganizerAccountTable()->get(array('organizer_id'=>$account_data->oid));
                    $this->_getPlayOrganizerAccountTable()->update(array('use_money'=>($balance->use_money-$account_data->flow_money),'total_money'=>$balance->total_money-$account_data->flow_money),array('organizer_id'=>$account_data->oid));
                    //todo 打款到商家账户
                    $this->adminLog('商家提现到账','organizer_account',$ids[0]);
                }else{
                    $show = "提现到账失败";
                }
            }else{
                $show = "没有符合提现到账的数据";
            }
        }
        unset($ids[0]);

        if (count($ids)) {
            echo $show;
            $redirect = '/wftadlogin/finance/doWithdraws?type=2&id='.implode('@',$ids);
            echo "<script>setTimeout(function(){window.location.href='".$redirect."'}, 1000);</script>";
            return false;
        } else {
            return true;
        }
    }


    //转账审批
    public function transferAction(){
        $page = (int)$this->getQuery('p', 1);
        $start = ($page - 1) * $this->pageSum;
        $params = $this->GetSearchCondition();

//        'not_approved' => '未审批',
//                'approved'=>'已审批',
//                'sub_approved'=>'已提交审批',
//                'sub_end'=>'已提交结束',
//                'ended'=>'已结束',
//                'sub_stop'=>'已提交终止',
//                'stopped'=>'已终止',
//                'pre_money_paid'=>'已付预付金',
//                'all_paid'=>'尾款已结',
//                'is_act'=>'正在生效',

        $where = " play_contracts.id > 0 and play_contracts.status not in (0,4) and contracts_type<3";
        $order = " play_contracts.check_dateline DESC";

        if($params['city']){
            $where .= " and play_contracts.city='{$params['city']}'";
        }

        //申请提现时间筛选
        if(!empty($params["sub_start_time"]) && !empty($params["sub_end_time"])){
            $nStartDate = strtotime($params["sub_start_time"]);
            $nEndDate = strtotime($params["sub_end_time"])+86400;

            //容错起始时间大于结束时间
            if($nStartDate > $nEndDate){
                $where .= " and (play_contracts.check_dateline >= {$nEndDate} and play_contracts.check_dateline <= {$nStartDate}) ";
            }else{
                $where .= " and (play_contracts.check_dateline >= {$nStartDate} and play_contracts.check_dateline <= {$nEndDate}) ";
            }
        }else{
            if($params["sub_start_time"]){
                $nStartDate = strtotime($params["sub_start_time"]);
                $where .= " and play_contracts.check_dateline >= {$nStartDate} ";
            }

            if(!empty($params["sub_end_time"])){
                $nEndDate = strtotime($params["sub_end_time"])+86400;
                $where .= " and play_contracts.check_dateline <= {$nEndDate} ";
            }
        }

        if($params['shop_name']){
            if(is_numeric($params['shop_name'])){
                $where .= " and (play_contracts.mid='{$params['shop_name']}')";
            }else{
                $where .= " and play_contracts.mid in (select id from play_organizer where name like '%{$params['shop_name']}%')";
            }
        }

        //到账时间筛选
        if(!empty($params["arrival_start_time"]) && !empty($params["arrival_end_time"])){
            $nStartDate = strtotime($params["arrival_start_time"]);
            $nEndDate = strtotime($params["arrival_end_time"])+86400;

            //容错起始时间大于结束时间
            if($nStartDate > $nEndDate){
                $where .= " and play_contracts.pay_pre_status=4 and (play_contracts.check_dateline >= {$nEndDate} and play_contracts.check_dateline <= {$nStartDate}) ";
            }else{
                $where .= " and play_contracts.pay_pre_status=4 and (play_contracts.check_dateline >= {$nStartDate} and play_contracts.check_dateline <= {$nEndDate}) ";
            }
        }else{
            if(!empty($params["arrival_start_time"])){
                $nStartDate = strtotime($params["arrival_start_time"]);
                $where .= " and play_contracts.pay_pre_status=4 and play_contracts.check_dateline >= {$nStartDate} ";
            }

            if(!empty($params["arrival_end_time"])){
                $nEndDate = strtotime($params["arrival_end_time"])+86400;
                $where .= " and play_contracts.pay_pre_status=4 and play_contracts.check_dateline <= {$nEndDate} ";
            }
        }


        $sql = "select play_contracts.*,
        play_organizer_account.bank_user,
        play_organizer_account.bank_card,
        sum(play_contract_link_price.account_money*play_contract_link_price.total_num) as total_account_money
        from play_contracts
        left join play_organizer_account on play_organizer_account.organizer_id = play_contracts.mid
        left join play_organizer_game on play_organizer_game.contract_id = play_contracts.id
        and play_organizer_game.status=1
        left join play_contract_link_price on play_contract_link_price.good_id = play_organizer_game.id
        and play_contract_link_price.status=1
        where $where group by play_contracts.id order by $order
        ";

//        $data = $this->_getPlayContractsTable()->getTransferList($start,$this->pageSum,array(),$where,$order);
        $data = $this->query($sql."limit {$start} , {$this->pageSum}");
//        foreach($data as $v){
//            var_dump($v);
//
//        }
        $data = $this->FmtData($data)['data'];
        $count = $this->query($sql)->count();

        $url = '/wftadlogin/finance/transfer';

        $paginator = new Paginator($page, $count, $this->pageSum, $url);

        return array(
            'data'=>$data,
            'pageData'=>$paginator->getHtml(),
        );
    }

    public function contractArrivalAction(){
        $id = trim($this->getQuery('id',0));
        $ids = explode('@',$id);
        if(!count($ids)){
            return $this->_Goto('请选择要操作的合同');
        }

        if(!isset($_COOKIE['referer_url']))   {
            setcookie('referer_url', $_SERVER["HTTP_REFERER"]);
        }
        $result = $this->contractArrival($ids);
        if ($result) {
            $url = $_COOKIE['referer_url'] ? $_COOKIE['referer_url'] : $_SERVER["HTTP_REFERER"];
            setcookie('referer_url', '', time()-3600);
            return $this->_Goto('执行完毕', $url);
        }
        exit;
    }

    //转账审批操作到账
    public function contractArrival($ids){
        $contract_data = $this->_getPlayContractsTable()->get(array('id'=>(int)$ids[0]));
        if(!$contract_data){
            $show = '合同不存在';
        }else{
            if(!in_array($contract_data->status,array(0,4)) and $contract_data->check_status==2){
                //操作预付金
                if($contract_data->status==1 and $contract_data->pay_pre_status==1){
                    $status = $this->_getPlayContractsTable()->update(array('pay_pre_status'=>2,'check_dateline'=>time()),array('id'=>(int)$ids[0]));
                    if($status){
                        $this->doContractLog($contract_data,9);
                        //记录操作
                        $show = '合同号：'.$contract_data->contract_no.'预付金到账成功';
                    }else{
                        $show =  '合同号：'.$contract_data->contract_no.'预付金到账失败';
                    }
                }
                if($contract_data->status==2 and $contract_data->pay_pre_status==3){
                    $status = $this->_getPlayContractsTable()->update(array('pay_pre_status'=>4,'check_dateline'=>time()),array('id'=>(int)$ids[0]));
                    if($status){
                        $show ='合同号：'.$contract_data->contract_no. '到账成功';
                    }else{
                        $show = '合同号：'.$contract_data->contract_no.'操作到账失败';
                    }
                }

                if($contract_data->status==3 and $contract_data->pay_pre_status==2){
                    $status = $this->_getPlayContractsTable()->update(array('pay_pre_status'=>4,'status'=>2,'check_dateline'=>time()),array('id'=>(int)$ids[0]));
                    if($status){
                        $show ='合同号：'.$contract_data->contract_no. '到账成功';
                    }else{
                        $show = '合同号：'.$contract_data->contract_no.'操作到账失败';
                    }
                }
            }else{
                $show= '没有符合提现到账的数据';
            }
        }
        unset($ids[0]);
        if (count($ids)) {
            echo $show;
            $redirect = '/wftadlogin/finance/contractArrival?id='.implode('@',$ids);
            echo "<script>setTimeout(function(){window.location.href='".$redirect."'}, 1000);</script>";
        } else {
            return $this->_Goto($show);
        }

    }


    //经费审批
    public function fundsApproveAction(){

    }

    //商家账户信息
    public function shopAccountAction(){
        $page = (int)$this->getQuery('p',1);
        $start = ($page - 1) * $this->pageSum;
        $shop_name = trim($this->getQuery('shop_name',null));
        $where = "play_organizer_account.id > 0 ";
        $order = 'play_organizer_account.id DESC';
        if($shop_name){
            if(is_numeric($shop_name)){
                $where .= " and play_organizer_account.organizer_id={$shop_name}";
            }else{
                $where .= " and play_organizer_account.organizer_id in (select id from play_organizer where name like '%{$shop_name}%')";
            }
        }

        $sql = "select play_organizer_account.*,
        play_organizer.name,
        play_organizer.city,
        play_organizer.status
        from play_organizer_account
        left join play_organizer on play_organizer.id = play_organizer_account.organizer_id
        where $where order by $order
        ";

        $organizer_account_data = $this->query($sql." limit $start , $this->pageSum");
        $count = $this->query($sql)->count();
        //创建分页
        $url = '/wftadlogin/finance/shopAccount';

        $paginator = new Paginator($page, $count, $this->pageSum, $url);
        return array(
            'data'=>$organizer_account_data,
            'pageData'=>$paginator->getHtml()
        );

    }

    //冻结商家账户
    public function closeAction()
    {
        $uid = (int)$this->getQuery('oid', 0);
        if (!$uid) {
            return $this->_Goto('参数错误');
        }
        $status = $this->_getPlayOrganizerTable()->update(array('status' => 0), array('id' => $uid));
        if ($status) {
            return $this->_Goto('账号成功冻结', 'javascript:location.href = document.referrer');
        } else {
            return $this->_Goto('未做修改，账号已是冻结状态');
        }

    }

    //开启商家账户
    public function openAction()
    {
        $uid = (int)$this->getQuery('oid', 0);
        if (!$uid) {
            return $this->_Goto('参数错误');
        }
        $status = $this->_getPlayOrganizerTable()->update(array('status' => 1), array('id' => $uid));
        if ($status) {
            return $this->_Goto('账号成功开启', 'javascript:location.href = document.referrer');
        } else {
            return $this->_Goto('未做修改，账号已是开启状态');
        }

    }

    //商家流水
    public function shopLogAction(){
        $uid = (int)$this->getQuery('oid',0);
        if (!$uid) {
            return $this->_Goto('参数错误');
        }
        $where = "play_organizer_account_log.status=4 and play_organizer_account_log.oid={$uid}  ";

        $sql = "select
        play_organizer_account_log.*,
        play_organizer_account.use_money,
        play_organizer_account.not_use_money,
        play_organizer.name
        from play_organizer_account_log
        left join play_organizer on play_organizer.id=play_organizer_account_log.oid
        left join play_organizer_account on play_organizer_account.organizer_id=play_organizer.id
        where $where
        ";
//        $log_data = $this->_getPlayAccountLogTable()->getShopLog(array('oid'=>$uid,'play_organizer_account_log.status'=>1));
//        $count =  $this->_getPlayAccountLogTable()->getShopLog(array('oid'=>$uid,'status'=>1))->count();

        $log_data = $this->query($sql);
       return array(
            'data'=>$log_data,
        );

    }

    //用户账户信息
    public function userAccountAction(){

        $page = (int)$this->getQuery('p',1);
        $start = ($page - 1) * $this->pageSum;
        $user = trim($this->getQuery('user',null));
        $where = "play_account.id > 0";
        $order = "play_account.id DESC";

        if($user){
            if(is_numeric($user)){
                $where .= " and play_account.uid={$user}";
            }else{
                $where .= " and play_user.username like '%{$user}%'";
            }
        }

        $sql = "select
        play_user.username,
        play_user.status as user_status,
        play_account.*
        from play_account
        left join play_user on play_user.uid = play_account.uid
        where $where order by $order
        ";

        $data = $this->query($sql. "limit $start , $this->pageSum");
        $count = $this->query($sql)->count();

        $url = '/wftadlogin/finance/userAccount';
        $paginator = new Paginator($page, $count, $this->pageSum, $url);

        return array(
            'data'=>$data,
            'pageData'=>$paginator->getHtml()
        );

    }

    //合同操作日志
    public function doContractLog($data,$type){
        switch($type){
            case 1:
                $node = '合同号：'.$data->contract_no.'申请审批';
                $status = 1;
                break;
            case 2:
                $node = '合同号：'.$data->contract_no.'审批通过';
                $status = 2;
                break;
            case 3:
                $node = '合同号：'.$data->contract_no.'批准预付金';
                $status = 3;
                break;
            case 4:
                $node = '合同号：'.$data->contract_no.'审批结束';
                $status = 4;
                break;
            case 5:
                $node = '合同号：'.$data->contract_no.'审批终止';
                $status = 5;
                break;
            case 6:
                $node = '合同号：'.$data->contract_no.'批准结款';
                $status = 6;
                break;
            case 7:
                $node = '合同号：'.$data->contract_no.'提交结束';
                $status = 7;
                break;
            case 8:
                $node = '合同号：'.$data->contract_no.'提交终止';
                $status = 8;
                break;
            case 9:
                if($data->status==1 and $data->pay_pre_status==1){
                    $node = '合同号：'.$data->contract_no.'预付金操作到账';
                }else{
                    $node = '合同号：'.$data->contract_no.'结款操作到账';
                }
                $status = 9;
                break;
        }

        $data = array(
            'action_user' => $_COOKIE['id'],
            'contract_id' => $data->id,
            'contract_status' => $status,
            'action_note' => $node,
            'dateline' => time(),
            'action_user_name' => '管理员'. $_COOKIE['user']
        );

        return $this->_getPlayContractActionTable()->insert($data);


    }

    //操作日志
    public function doLog($data, $type) {
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
            $play_status = 12;
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


    /**
     * 获取搜索条件
     * @return array
     */
    private function GetSearchCondition()
    {
        //订单管理；
        $map["good_name"] = trim($this->getQuery("good_name",null));
        $map["admin"] = trim($this->getQuery("admin",null));
        $map["shop_name"] = trim($this->getQuery("shop_name",null));
        $map["good_id"] = (int)$this->getQuery("good_id",0);
        $map["user_name"] = trim($this->getQuery("user_name",null));
        $map["buy_end_date"] = trim($this->getQuery("buy_end_date",null));
        $map["buy_start_date"] = trim($this->getQuery("buy_start_date",null));
        $map["ver_start_date"] = trim($this->getQuery("ver_start_date",null));
        $map["ver_end_date"] = trim($this->getQuery("ver_end_date",null));
        $map["sub_back_start_date"] = trim($this->getQuery("sub_back_start_date",null));
        $map["sub_back_end_date"] = trim($this->getQuery("sub_back_end_date",null));
        $map["user_phone"] = trim($this->getQuery("user_phone",null));
        $map["pay_status"] = (int)$this->getQuery("order_status",0);
        $map["good_status"] = (int)$this->getQuery("good_status",0);
        $map["ver_status"] = (int)$this->getQuery("check_status",0);
        $map["pay_type"] = (int)$this->getQuery("pay_type",0);

        //订单审核
        $map["trade_start_time"] = trim($this->getQuery("trade_start_time",null));
        $map["trade_end_time"] = trim($this->getQuery("trade_end_time",null));
        $map["close_start_time"] = trim($this->getQuery("close_start_time",null));
        $map["close_end_time"] = trim($this->getQuery("close_end_time",null));
        $map["city"] = trim($this->getQuery("city",null));
        $map["game_name"] = trim($this->getQuery("game_name",null));
        $map["shop_id"] = trim($this->getQuery("shop_id",null));
        $map["order_sn"] = (int)$this->getQuery("order_sn",0);
        $map["user_id"] = (int)$this->getQuery("user_id",0);

        //合同审核
        $map['create_time'] = trim($this->getQuery('create_time',null));
        $map['contract_no'] = $this->getQuery('contract_no',null);
        $map['operator'] = trim($this->getQuery('operator',null));
        $map['organizer'] = trim($this->getQuery('organizer',null));
        $map['organizer_id'] = trim($this->getQuery('organizer_id',null));
        $map['contract_type'] = trim($this->getQuery('contract_type',null));
        $map['contract_status'] = trim($this->getQuery('contract_status',null));

        //商家提现  转账审批
        $map['sub_start_time'] = trim($this->getQuery('sub_start_time',null));
        $map['sub_end_time'] = trim($this->getQuery('sub_end_time',null));
        $map['arrival_start_time'] = trim($this->getQuery('arrival_start_time',null));
        $map['arrival_end_time'] = trim($this->getQuery('arrival_end_time',null));




        return $map;
    }

    //格式化数据
    public function FmtData($data=array()){
        if(empty($data)){
            return $data;
        }
        $info=array();
        $sum_payment=0;
        $sum_income=0;
        foreach($data as $k=>$v){
            $info[$k] = $v;
            $info[$k]['income'] = ($v['coupon_unit_price']-$v['account_money'])*$v['buy_number'];
            $info[$k]['good_status'] = $this->getGoodStatus($v['is_together'],$v['up_time'],$v['down_time'],$v['foot_time'],$v['game_status']);
            $info[$k]['order_status'] = $this->_getConfig()['order_status'][$v['pay_status']];
//            $info[$k]['check_status'] = $v['check_status']==2 ? "已审核" : '未审核';
            $info[$k]['fees'] = '0.2';
            $sum_income += $v['account_money'];//合计入账
            $sum_payment += $v['back_money'];//合计出账
            $info[$k]['reason'] = $this->getReason($v['status'],$v['contracts_type'],$v['check_status']);
            $info[$k]['sub_money'] =$this->getTransferMoney($v['status'],$v['check_status'],$v['pre_money'],$v['total_account_money'],$v['pay_pre_status']);
            $info[$k]['transfer_status'] = $this->getTransferStatus($v['status'],$v['pay_pre_status'],$v['check_status']);
        }
        return array('data'=>$info,'sum_income'=>$sum_income,'sum_payment'=>$sum_payment);
    }

    private function getGoodStatus($is_together, $up_time, $down_time, $foot_time, $game_status) {
        $game_stay = '';

        if ($is_together == 1 && $game_status == 1 && $up_time > time()) { //未开始
            $game_stay = '未开始';
        } elseif ($is_together == 1 && $game_status == 1 && $up_time < time() && $down_time > time()) {// 在售卖
            $game_stay = '在售卖';
        } elseif ($is_together == 1 && $game_status == 1 && $foot_time > time() && $down_time < time()) {// 停止售卖
            $game_stay = '已下架';
        } elseif ($is_together == 1 && $game_status == 1 && $foot_time < time() && $down_time < time()) {
            $game_stay = '停止使用';
        } else {
            $game_stay = '停止使用';
        }

        return $game_stay;
    }

    //转账审批的原因
    private function getReason($status,$contracts_type,$check_status){

        $info = '';
        if($check_status<2){
            if($contracts_type==1){
                $info='包销预付金';
            }elseif($contracts_type==2){
                $info= '自营预付款';
            }
        }else{
            if($status==1){
                if($contracts_type==1){
                    $info= '包销预付金';
                }elseif($contracts_type==2){
                    $info= '自营预付金';
                }
            }
            if($status==2){
                if($contracts_type==1){
                    $info= '包销结款';
                }elseif($contracts_type==2){
                    $info= '自营结款';
                }
            }
            if($status==3){
                if($contracts_type==1){
                    $info= '包销结款';
                }elseif($contracts_type==2){
                    $info= '自营结款';
                }
            }
        }

        return $info;
    }

    //获取转账申请的金额
    private function getTransferMoney($status,$check_status,$pre_money,$total_account_money,$pay_pre_status){
        $money=0;

        if($check_status<2){
            $money = $pre_money;
        }else{
            if($status==1){
                $money = $pre_money;
            }
            if($status==2){
                $money = $total_account_money-$pre_money;
            }
            if($status==3 and $pay_pre_status==2){
                $money = $total_account_money-$pre_money;
            }
        }
        return $money;
    }

    //获取转账状态
    private function getTransferStatus($status,$pay_pre_status,$check_status){
        $info = '';
        if($check_status<2){
            $info =  '等待审批';
        }else{
            if($status==1){
                if($pay_pre_status==1){
                    $info =  '审批通过';
                }
                if($pay_pre_status==2){
                    $info =  '已到账';
                }
            }
            if($status==2 || $status==3){
                if($pay_pre_status==2){
                    $info = '审批通过';
                }
                if($pay_pre_status==3){
                    $info = '已结清';
                }
                if($pay_pre_status==4){
                    $info = '已到账';
                }
            }
        }
        return $info;
    }

    private function getWhere(){
        $param = $this->GetSearchCondition();
        //订单相关

    }

    //导出合同
    public function outDataAction(){
        $obj = $this->contractAction();
        $data = $obj['data'];
        $head = array(
            '合同编号',
            '创建时间',
            '经办人',
            '商家',
            '合同类型',
            '预付金',
            '合同状态',
            '商品数',
            '商品订单数',
            '商家预计收入',
            '商家实际收入',
            '平台收入',
        );

        $content = array();
        foreach($data as $v){
            $content[] = array(
                $v['contract_no'],
                date('Y-m-d',$v['create_time']),
                $v['admin_name'],
                $v['organizer_name'],
                $obj['contract_type'][$v['contracts_type']],
                $v['pre_money'],
                $obj['contract_status'][$v['contracts_status']],
                $v['goods_num'],
                $v['order_num'],
                $v['shop_pre_income'],
                $v['shop_real_income'],
                $v['deyi_income'],
            );
        }

        $fileName = date('Y-m-d H:i:s', time()). '_合同列表.csv';
        $out = new OutPut();
        $out->out($fileName, $head, $content);
        exit;
    }

    public function outOrderAction(){
        $obj = $this->indexAction();
        $data = $obj['out_data'];

        $tradeWay = array(
            'weixin' => '微信',
            'union' => '银联',
            'alipay' => '支付宝',
            'jsapi' => '微信网页',
            'nopay' => '未付款',
            'other' => '其它',
        );

        $head = array(
            '交易时间',
            '交易渠道',
            '交易号',
            '商品订单号',
            '商家名称',
            '商品名称',
            '单价',
            '购买数量',
            '购买金额',
            '已使用数',
            '已使用金额',
            '等待退款金额',
            '已退款金额',
            '结算单价',
            '需结算数量',
            '已结算金额',
            '收益',
            '商品状态',
            '对方账户',
            '用户名',
            '手机号',
            '用户id',
            '套系名称',
            '商品id',
            '订单状态',
            '是否可退款',
        );

        $content = array();
        foreach($data as $v){
            //使用码数据
            $code_date = $this->getBackMoney($v['order_sn'], $v['coupon_unit_price']);
            $v['use_dateline']=$v['use_dateline']>0 ? date("Y-m-d H:i:s,",$v['use_dateline']):0;
            $content[] = array(
                date('Y-m-d H:i:s', $v['dateline']),
                $tradeWay[$v['account_type']],
                "\t".$v['trade_no'],
                'WFT' . (int)$v['order_sn'],
                $v['shop_name'],
                $v['coupon_name'],
                $v['coupon_unit_price'],
                $v['buy_number'],
                $v['real_pay'],
                $v['use_number'],
                $v['use_number'] * $v['coupon_unit_price'],
                $code_date['wait'],
                $code_date['yes'],
                $v['account_money']? $v['account_money'] : $v['coupon_unit_price'],
                $code_date['account_need_num'],
                $code_date['account_have_num'] * ($v['account_money']? $v['account_money'] : $v['coupon_unit_price']),
                $v['real_pay'] -  $code_date['account_have_num'] * ($v['account_money'] ? $v['account_money'] : $v['coupon_unit_price']),
                $v['good_status'],
                $v['account'],
                $v['username'],
                $v['phone'],
                $v['user_id'],
                $v['price_name'],
                $v['coupon_id'],
                $v['order_status'],
                ($v['refund_time'] > time()) ? '可退款' : '不可退款'
            );
        }

        $fileName = date('Y-m-d H:i:s', time()). '_订单管理列表.csv';
        $out = new OutPut();
        $out->out($fileName, $head, $content);
        exit;
    }

    private function getBackMoney($order_sn, $unit_price) {
        $data = array(
            'wait' => 0, //等待退款金额
            'yes' => 0, // 已退款金额
            'account_need_num' => 0, // 需结算数量
            'account_have_num' => 0, // 已经结算数量
            'account_time' => '', //最近的结算时间
            'use_time' => '', //最近的使用时间
            'order_stu' => '', //订单状态
        );
        $codeData =  $this->_getPlayCouponCodeTable()->fetchAll(array('order_sn' => $order_sn));

        foreach($codeData as $code) {
            if ($code->status == 3) {
                $data['wait'] = $data['wait'] + (floatval($code->back_money) ? $code->back_money : $unit_price);
            }

            //已使用 等待退款
            if ($code->force == 2) {
                $data['wait'] =  $data['wait'] + $code->back_money;
            }

            if ($code->status == 2) {
                $data['yes'] = $data['yes'] + (floatval($code->back_money) ? $code->back_money : $unit_price);
            }

            //已使用 确认退款
            if ($code->force == 3) {
                $data['yes'] = $data['yes'] + $code->back_money;
            }

            if ($code->status == 1 || $code->status == 2) {
                if ($code->test_status == 5) {
                    $data['account_have_num'] = $data['account_have_num'] + 1;
                } elseif($code->test_status>2){
                    $data['account_need_num'] = $data['account_need_num'] + 1;
                }
            }

            if ($code->use_datetime) {
                $data['use_time'] = $code->use_datetime;
            }

            if ($code->account_time && $code->test_status == 5) {
                $data['account_time'] = $code->account_time;
            }

            $data['order_stu'] =  $data['order_stu'] . $this->getOrderStatus($code->check_status, $code->test_status, 2 , $code->status);

        }

        return $data;
    }

    private function getOrderStatus ($check_status, $test_status, $pay_status, $status) {
        $order_stay = '';
        if ($pay_status < 1) {
            $order_stay = '未支付';
        } else {
            if ($check_status == 1) {
                $order_stay = $this->_getConfig()['coupon_code_status'][$status];
            } else {
                if ($test_status == 3) {
                    $order_stay = '已提交结算';
                } elseif ($test_status == 4) {
                    $order_stay = '已受理结算';
                } elseif ($test_status == 5) {
                    $order_stay = '已结算';
                } else {
                    if ($status == 3) {
                        if ($test_status == 0) {
                            $order_stay = '已提交退款';
                        } elseif ($test_status == 1) {
                            $order_stay = '已受理退款';
                        }
                    } elseif($status == 1) {
                        $order_stay = '已使用';
                    } elseif ($status == 2) {
                        $order_stay = '已退款';
                    } elseif ($status == 0)  {
                        $order_stay = '待使用';
                    }
                }
            }
        }

        return $order_stay;
    }


    //商家提现审批导出
    public function outShopAccountAction(){
        $obj = $this->withdrawsAction();
        $data = $obj['data'];
        $head = array(
            '申请时间',
            '提现时间',
            '商家ID',
            '商家账户',
            '商家城市',
            '银行账号',
            '可提现余额',
            '申请余额',
            '状态',
        );

        $content = array();
        foreach($data as $v){
            $content[] = array(
                date('Y-m-d H:i',$v['sub_dateline']),
                date('Y-m-d H:i',$v['dateline']),
                $v['organizer_id'],
                $v['bank_user'],
                $v['city'],
                $v['bank_card'],
                $v['use_money'],
                $v['flow_money'],
                $obj['status'][$v['status']],
            );
        }

        $fileName = date('Y-m-d H:i:s', time()). '_商家审批列表.csv';
        $out = new OutPut();
        $out->out($fileName, $head, $content);
        exit;
    }

    //转账审批导出
    public function outTransferAction(){
        $obj = $this->transferAction();
        $data = $obj['data'];
        $head = array(
            '申请时间',
            '商家ID',
            '商家账户',
            '商家城市',
            '银行账号',
            '原因',
            '申请余额',
            '状态'
        );


        $content = array();
        foreach($data as $v){
            $content[] = array(
                $v['check_dateline']>0 ? date('Y-m-d H:i',$v['check_dateline']) : date('Y-m-d H:i',$v['create_time']),
                $v['mid'],
                $v['bank_user'],
                $v['city'],
                $v['bank_card'],
                $v['reason'],
                $v['sub_money'],
                $v['transfer_status']
            );
        }

        $fileName = date('Y-m-d H:i:s', time()). '_转账审批列表.csv';
        $out = new OutPut();
        $out->out($fileName, $head, $content);
        exit;
    }

    //商家账户导出
    public function shopAccountExportAction(){
        $obj = $this->shopAccountAction();
        $data = $obj['data'];
        $head = array(
            '商家ID',
            '商家名称',
            '商家城市',
            '提现姓名',
            '银行',
            '支行',
            '卡号',
            '可用金额',
            '可提现金额',
            '状态'
        );

        $content = array();
        foreach($data as $v){
            $content[] = array(
                $v['organizer_id'],
                $v['name'],
                $v['city'],
                $v['bank_user'],
                $v['bank_name'],
                $v['bank_address'],
                $v['bank_card'],
                $v['total_money'],
                $v['use_money'],
                $v['status']==1 ? '正常' : '冻结'
            );
        }

        $fileName = date('Y-m-d H:i:s', time()). '_商家账户列表.csv';
        $out = new OutPut();
        $out->out($fileName, $head, $content);
        exit;
    }

}