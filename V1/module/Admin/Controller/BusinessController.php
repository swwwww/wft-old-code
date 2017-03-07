<?php

namespace Admin\Controller;

use Deyi\Contract\Contract;
use Deyi\JsonResponse;
use Deyi\Paginator;
use Deyi\Account\OrganizerAccount;
use Deyi\OutPut;
use Deyi\SendMessage;
use Zend\View\Model\ViewModel;

class BusinessController extends BasisController
{
    use JsonResponse;

    //商家账号
    public function accountListAction() {

        $page = (int)$this->getQuery('p',1);
        $pageNum = 10;
        $start = ($page - 1) * $pageNum;
        $organizer_name = trim($this->getQuery('organizer_name',null));
        $where = "play_organizer_account.id > 0 ";
        $order = 'play_organizer_account.id DESC';
        if($organizer_name){
            if(is_numeric($organizer_name)){
                $where .= " and play_organizer_account.organizer_id={$organizer_name}";
            }else{
                $where .= " and play_organizer.name like '%{$organizer_name}%'";
            }
        }

        $city = $this->getQuery('organizer_city', '');

        if($city){
            $where = $where. " AND play_organizer.city = '{$city}'";
        }

        $sql = "select play_organizer_account.*,
        play_organizer.name,
        play_organizer.city
        from play_organizer_account
        left join play_organizer on play_organizer.id = play_organizer_account.organizer_id
        where $where order by $order
        ";

        $organizer_account_data = $this->query($sql." limit $start , $pageNum");
        $count = $this->query($sql)->count();
        //创建分页
        $url = '/wftadlogin/business/accountList';

        $data = array();

        foreach ($organizer_account_data as $organizer_account) {

            $audit_money = $this->query("SELECT SUM(flow_money) AS audit_money from play_organizeraccount_audit where audit_type = 3 AND check_status < 3 AND organizer_id = {$organizer_account['organizer_id']}")->current();
            $data[] = array(
                'organizer_id' => $organizer_account['organizer_id'],
                'name' => $organizer_account['name'],
                'city' => $organizer_account['city'],
                'bank_user' => $organizer_account['bank_user'],
                'bank_name' => $organizer_account['bank_name'],
                'bank_address' => $organizer_account['bank_address'],
                'bank_card' => $organizer_account['bank_card'],
                'not_use_money' => $organizer_account['not_use_money'],
                'use_money' => $organizer_account['use_money'],
                'total_money' => $organizer_account['total_money'],
                'audit_money' => $audit_money['audit_money'],
            );
        }

        $paging = new Paginator($page, $count, $pageNum, $url);
        return array(
            'data'=>$data,
            'pageData'=>$paging->getHtml(),
            'city' =>  $this->getAllCities(0),
        );
    }

    //冻结商家账号
    /*public function frozenAction() {
        $type = (int)$this->getQuery('type');
        $organizer_id = (int)$this->getQuery('organizer_id');
        if (!in_array($type, array(1, 2))) {
            return $this->_Goto('非法操作');
        }

        $account = new OrganizerAccount();
        $result = $account->frozenOrganizer($organizer_id, $type);

        return $this->_Goto($result ? '成功' : '失败');

    }*/

    public function outOrganizerDataAction() {

        $organizer_name = trim($this->getQuery('organizer_name',null));
        $where = "play_organizer_account.id > 0 ";
        $order = 'play_organizer_account.id DESC';
        if($organizer_name){
            if(is_numeric($organizer_name)){
                $where .= " and play_organizer_account.organizer_id={$organizer_name}";
            }else{
                $where .= " and play_organizer.name like '%{$organizer_name}%'";
            }
        }

        $city = $this->chooseCity(1);
        if($city){
            $where = $where. " AND play_organizer.city = '{$city}'";
        }


        $sql = "select play_organizer_account.*,
        play_organizer.name,
        play_organizer.city,
        play_organizer.status
        from play_organizer_account
        left join play_organizer on play_organizer.id = play_organizer_account.organizer_id
        where $where order by $order
        ";

        $organizerAccount = $this->query($sql);

        $out = new OutPut();

        $file_name = date('Y-m-d H:i:s', time()). '_商家账号列表.csv';
        $head = array(
            '商家id',
            '商家名称',
            '商家城市',
            '开户人',
            '开户行',
            '开户支行',
            '银行卡号',
            '不可提现金额',
            '可提现金额',
            '状态',
        );

        $content = array();

        foreach ($organizerAccount as $v) {
            $content[] = array(
                $v['organizer_id'],
                $v['name'],
                $this->getAllCities(1)[$v['city']],
                $v['bank_user'],
                $v['bank_name'],
                $v['bank_address'],
                $v['bank_card'],
                $v['not_use_money'],
                $v['use_money'],
                ($v['status'] == 1) ? '正常' :  '冻结',

            );
        }

        $out->out($file_name, $head, $content);
        exit;

    }

    public function outMoneyAction() {
        $oid = (int)$this->getQuery('oid');
        return array(
            'organizer_id' => $oid,
        );
    }

    public function saveOutMoneyAction() {
        $organizer_id = $this->getPost('organizer_id');
        $reason = $this->getPost('reason');
        $money = (float)$this->getPost('money');

        if (!$organizer_id || !$reason || !$money) {
            return $this->_Goto('非法操作');
        }

        $organizerAccountData = $this->_getPlayOrganizerAccountTable()->get(array('organizer_id' => $organizer_id));

        if ($organizerAccountData->use_money < $money) {
            return $this->_Goto('钱不够');
        }

        $organizerAccount = new OrganizerAccount();
        $result = $organizerAccount->takeCrash($organizer_id, 2, $object_type = 2, $organizer_id, $money, $reason. '_后台管理员_'.$_COOKIE['id'],  $status = 2);
        return $this->_Goto($result ? '成功' : '失败');
    }

    //商家提现审批列表
    public function takeCrashListAction() {

        $page = (int)$this->getQuery('p', 1);
        $pageNum = 10;
        $start = ($page - 1) * $pageNum;

        $whereQuery = $this->takeCrashListWhere();

        if (!$whereQuery['status']) {
            return $this->_Goto($whereQuery['message']);
        }

        $where = $whereQuery['message'];

        $order = "play_organizeraccount_audit.id DESC";

        $sql = "SELECT
play_organizeraccount_audit.*,
play_organizer_account.bank_user,
play_organizer_account.bank_name,
play_organizer_account.bank_address,
play_organizer_account.bank_card,
play_organizer_account.total_money,
play_organizer.city,
play_organizer.name
FROM play_organizeraccount_audit
LEFT JOIN  play_organizer_account ON play_organizer_account.organizer_id = play_organizeraccount_audit.organizer_id
LEFT JOIN play_organizer ON play_organizer.id = play_organizer_account.organizer_id
WHERE  $where
ORDER BY $order LIMIT {$start}, {$pageNum}";

        $data = $this->query($sql);

        $count_sql = "SELECT
COUNT(play_organizeraccount_audit.id) AS count_num
FROM play_organizeraccount_audit
LEFT JOIN  play_organizer_account ON play_organizer_account.organizer_id=play_organizeraccount_audit.organizer_id
LEFT JOIN play_organizer ON play_organizer.id=play_organizer_account.organizer_id
WHERE $where";

        $count = $this->query($count_sql)->current()['count_num'];

        //创建分页
        $url = '/wftadlogin/business/takeCrashList';

        $paging = new Paginator($page, $count, $pageNum, $url);

        return array(
            'data'=>$data,
            'pageData'=>$paging->getHtml(),
            'city' => $this->getAllCities(0),
        );

    }

    //商家提现审批列表 where
    private function takeCrashListWhere() {
        $where = "play_organizeraccount_audit.id > 0";

        $organizer_name = $this->getQuery('organizer_name');
        $city = $this->getQuery('city', '');
        $start_time = $this->getQuery('start_time');
        $end_time = $this->getQuery('end_time');
        $transfer_start = $this->getQuery('transfer_start');
        $transfer_end = $this->getQuery('transfer_end');
        $crash_status = (int)$this->getQuery('crash_status', 0);
        $audit_type = (int)$this->getQuery('audit_type', 0);

        if($organizer_name){
            if(is_numeric($organizer_name)){
                $where .= " and play_organizer.id={$organizer_name}";
            }else{
                $where .= " and play_organizer.name like '%{$organizer_name}%'";
            }
        }

        if($city){
            $where = $where. " AND play_organizer.city = '{$city}'";
        }

        if ($crash_status) {
            $where = $where. " AND play_organizeraccount_audit.check_status = {$crash_status}";
        }

        if ($audit_type) {
            $where = $where. " AND play_organizeraccount_audit.audit_type = {$audit_type}";
        }

        if ($start_time && $end_time && strtotime($start_time) > strtotime($end_time)) {
            return array('status' => 0, 'message' => '申请时间出错');
        }

        if ($transfer_start && $transfer_end && strtotime($transfer_start) > strtotime($transfer_end)) {
            return array('status' => 0, 'message' => '到账时间出错');
        }

        //申请时间
        if ($start_time) {
            $start_time = strtotime($start_time);
            $where = $where. " AND play_organizeraccount_audit.create_time > ".$start_time;
        }

        if ($end_time) {
            $end_time = strtotime($end_time) + 86400;
            $where = $where. " AND play_organizeraccount_audit.create_time < ".$end_time;
        }

        //到账时间
        if ($transfer_start) {
            $transfer_start = strtotime($transfer_start);
            $where = $where. " AND play_organizeraccount_audit.confirm_time > ".$transfer_start;
        }

        if ($transfer_end) {
            $transfer_end = strtotime($transfer_end) + 86400;
            $where = $where. " AND play_organizeraccount_audit.confirm_time < ".$transfer_end;
        }

        return array('status' => 1, 'message' => $where);
    }

    public function  approveAuditAction()
    {
        $type = (int)$this->getQuery('approve_type', 0);

        if (!in_array($type, array(1, 2))) {
            return $this->_Goto('非法操作');
        }

        if ($type == 1) {
            $id = (int)$this->getQuery('id', 0);
            $result = $this->charge($id);
            return $this->_Goto($result['message']);
        }

        if ($type == 2) {
            $whereQuery = $this->takeCrashListWhere();

            if (!$whereQuery['status']) {
                return $this->_Goto($whereQuery['message']);
            }

            $where = $whereQuery['message'];

            $sql = "SELECT
play_organizeraccount_audit.id
FROM play_organizeraccount_audit
LEFT JOIN  play_organizer_account ON play_organizer_account.organizer_id = play_organizeraccount_audit.organizer_id
LEFT JOIN play_organizer ON play_organizer.id = play_organizer_account.organizer_id
WHERE  $where";

            $data = $this->query($sql);

            $res = '';
            foreach ($data as $val) {
                $result = $this->charge($val['id']);
                $res = $res. '编号 '. $val['id']. ' 结果 '. $result['message']. '<br />';
            }

            return $this->_Goto($res);
        }

        exit;

    }

    public function adoptAuditAction()
    {
        $type = (int)$this->getQuery('approve_type', 0);

        if (!in_array($type, array(1, 2))) {
            return $this->_Goto('非法操作');
        }

        if ($type == 1) {
            $id = (int)$this->getQuery('id', 0);
            $result = $this->chargeDo($id);
            return $this->_Goto($result['message']);
        }

        if ($type == 2) {
            $whereQuery = $this->takeCrashListWhere();

            if (!$whereQuery['status']) {
                return $this->_Goto($whereQuery['message']);
            }

            $where = $whereQuery['message'];

            $sql = "SELECT
play_organizeraccount_audit.id
FROM play_organizeraccount_audit
LEFT JOIN  play_organizer_account ON play_organizer_account.organizer_id = play_organizeraccount_audit.organizer_id
LEFT JOIN play_organizer ON play_organizer.id = play_organizer_account.organizer_id
WHERE  $where";

            $data = $this->query($sql);

            $res = '';
            foreach ($data as $val) {
                $result = $this->chargeDo($val['id']);
                $res = $res. '编号 '. $val['id']. ' 结果 '. $result['message']. '<br />';
            }

            return $this->_Goto($res);
        }

        exit;
    }

    //预付金本身已经审批通过 结算的 未审批
    private function charge($id) {

        $accountAudit = $this->_getPlayOrganizeraccountAuditTable()->get(array('id' => $id));
        if (!$accountAudit || $accountAudit->audit_type != 3) {
            return array('status' => 0, 'message' => '非法操作');
        }

        $adapter = $this->_getAdapter();

        $s1 = $adapter->query("SELECT play_organizer_code_log.id
FROM play_organizer_code_log
INNER JOIN play_coupon_code ON play_coupon_code.id = play_organizer_code_log.code_id
WHERE play_coupon_code.test_status = 3
AND play_organizer_code_log.audit_id = ?", array($id))->count();

        if (!$s1) {
            return array('status' => 0, 'message' => '需结算的使用码不存在');
        }

        $status = $this->_getPlayOrganizeraccountAuditTable()->update(array('check_status' => 2), array('id' => $id, 'check_status' => 1, 'audit_type' => 3));

        if (!$status) {
            return array('status' => 0, 'message' => '失败');
        }

        $s2 = $adapter->query("UPDATE play_coupon_code,
play_organizer_code_log
SET play_coupon_code.test_status = 4
WHERE
play_organizer_code_log.code_id = play_coupon_code.id
AND play_coupon_code.test_status = 3
AND play_organizer_code_log.audit_id = ?", array($id))->count();

        if (!$s2) {
            return array('status' => 0, 'message' => '更新使用码状态出错,请联系技术');
        }

        return array('status' => 1, 'message' => '成功');
    }

    //商家提现 及 预付款提现 确认到账
    private function chargeDo($id)
    {
        $auditData = $this->_getPlayOrganizeraccountAuditTable()->get(array('id' => $id));
        if (!$auditData) {
            return array('status' => 0, 'message' => '非法操作');
        }

        $timer = time();
        $preLog = $this->_getPlayPreLogTable()->get(array('aduit_id' => $id));

        $money = $auditData->flow_money;
        $organizerAccount = new OrganizerAccount();

        if ($auditData->audit_type == 1) {//包销合同 预付款
            $preMoneyLog = $this->_getPlayPreMoneyLogTable()->fetchAll(array('aduit_id' => $auditData->id));
            foreach ($preMoneyLog as $mg) {//不同库存钱扣掉
                $organizerAccount->inventory($mg['object_id'], 1, $mg['pre_money'], 0, $object_id = 0, 0, '给商家预付金');
            }
        }

        if ($auditData->audit_type == 2) { //代销合同 预付款
            $result = $organizerAccount->takeCrash($auditData->organizer_id, 2, $object_type = 1, $id, $money, '预付款确认到账'.$_COOKIE['user'] . $_COOKIE['id'], 1, $auditData->contract_id, 0 , $auditData->serial_number);
            if (!$result) {
                return array('status' => 0, 'message' => '预付款到账失败');
            }
        }

        if ($auditData->audit_type == 3) { //商家结算

            $organizerCodeLog = $this->_getPlayOrganizerCodeLogTable()->fetchAll(array('audit_id' => $id));

            $codeId = '';
            foreach ($organizerCodeLog as $code_log) {
                $codeId = $codeId. ','. $code_log['code_id'];
            }
            $codeId = trim($codeId, ',');

            $result = $organizerAccount->audit($auditData->organizer_id, $money, 2, $id, $auditData->serial_number);

            if (!$result) {
                return array('status' => 0, 'message' => '结算失败');
            }

            $s7 = $this->_getPlayCouponCodeTable()->update(array('test_status' => 5, 'account_time' => time()), array('id in('. $codeId. ')'));

            if (!$s7) {
                return array('status' => 0, 'message' => '更新使用码状态出错,请联系技术');
            }
        }

        $status = $this->_getPlayOrganizeraccountAuditTable()->update(array('check_status' => 3, 'confirm_time' => $timer), array('id' => $id, 'check_status' => 2));

        if (!$status) {
            return array('status' => 0, 'message' => '失败');
        }

        if ($preLog) {
            $s1 = $this->_getPlayPreLogTable()->update(array('type' => 2, 'confirm_time' => $timer, 'confirm_id' => $_COOKIE['id'], 'comfirmer' => $_COOKIE['user']), array('aduit_id' => $id));
            if (!$s1) {
                return array('status' => 0, 'message' => '更新预付款状态出错');
            }
        }

        $organizerData = $this->_getPlayOrganizerTable()->get(array('id' => $auditData->organizer_id));
        $organizerAccountData = $this->_getPlayOrganizerAccountTable()->get(array('organizer_id' => $auditData->organizer_id));
        if ($organizerAccountData->notification_phone) {
            $card = substr($organizerAccountData->bank_card, -4, 4);
            SendMessage::Send($organizerAccountData->notification_phone, "尊敬的{$organizerData->name}合作商家, 武汉玩翻天科技有限公司已于" . date('Y-m-d H:i', time()) ."向您尾号{$card}的{$organizerAccountData->bank_name}卡内汇款{$money}元，请注意查收。如有问题请联系玩翻天");
        }

        return array('status' => 1, 'message' => '成功');

    }

    //商家提现审批 导出
    public function outDataAction() {

        $whereQuery = $this->takeCrashListWhere();
        if (!$whereQuery['status']) {
            return $this->_Goto($whereQuery['message']);
        }

        $where = $whereQuery['message'];

        $sql = "SELECT
play_organizeraccount_audit.*,
play_organizer_account.bank_user,
play_organizer_account.bank_name,
play_organizer_account.bank_address,
play_organizer_account.bank_card,
play_organizer_account.total_money,
play_organizer.city,
play_organizer.name
FROM play_organizeraccount_audit
LEFT JOIN  play_organizer_account ON play_organizer_account.organizer_id = play_organizeraccount_audit.organizer_id
LEFT JOIN play_organizer ON play_organizer.id = play_organizer_account.organizer_id
WHERE  $where
ORDER BY play_organizeraccount_audit.id DESC";

        $data = $this->query($sql);

        $out = new OutPut();

        $file_name = date('Y-m-d H:i:s', time()). '_转账审批列表.csv';
        $head = array(
            '编号id',
            '流水号',
            '申请时间',
            '商家id',
            '商家名称',
            '商家城市',
            '银行账号',
            '开户行',
            '支行',
            '开户人',
            '合同编号',
            '类型',
            '申请转账金额',
            '原因',
            '状态',
            '账户余额',
        );

        $content = array();
        $city = $this->getAllCities();

        foreach ($data as $v) {
            $audit_type = '';
            $status = '';
            if ($v['audit_type'] == 1) {
                $audit_type = '包销预付金';
            } elseif ($v['audit_type'] == 2) {
                $audit_type = '代销预付金';
            } elseif ($v['audit_type'] == 3) {
                $audit_type = '商家结算';
            }

            if ($v['check_status']==1) {
                $status = '未审批';
            } elseif($v['check_status']==2) {
                $status = '已审批';
            } elseif($v['check_status']==3) {
                $status = '已到账';
            }

            $content[] = array(
                $v['id'],
                "\t".$v['serial_number'],
                date('Y-m-d H:i:s', $v['create_time']),
                $v['organizer_id'],
                $v['name'],
                $city[$v['city']],
                "\t".$v['bank_card'],
                $v['bank_name'],
                $v['bank_address'],
                $v['bank_user'],
                "\t".$v['contract_num'],
                $audit_type,
                $v['flow_money'],
                $v['reason'],
                $status,
                $v['total_money'],
            );
        }

        $out->out($file_name, $head, $content);
        exit;
    }

    //自营合同 确认到账
    public function approveAction() {
        $id = (int)$this->getQuery('id');
        $contractData = $this->_getPlayContractsTable()->get(array('id' => $id));

        if (!$contractData || $contractData->contracts_type != 2 || $contractData->pay_pre_status != 1 || $contractData->check_status != 2 || $contractData->status != 1) {
            return $this->_Goto('非法操作');
        }

        $status = $this->_getPlayContractsTable()->update(array('pay_pre_status'=>2, 'check_dateline'=> time()),array('id'=>$id));

        return $this->_Goto($status ? '成功' : '失败');

    }

    //使用码 结算
    //todo 全部都是 没有预付款的 订单分润了的
    public function codeListAction()
    {

        $organizerId = (int)$this->getQuery('organizer_id');

        $organizerData = $this->_getPlayOrganizerTable()->get(array('id' => $organizerId));
        if (!$organizerData) {
            return $this->_Goto('非法操作');
        }

        $pageSum = (int)$this->getQuery('page_num', 10);
        $page = (int)$this->getQuery('p', 1);
        $start = ($page - 1) * $pageSum;
        $order = "play_order_info.order_sn DESC";

        $where = $this->getAuditWhere($organizerId);

        $sql = "SELECT
    play_organizer_code_log.transport_status,
	play_coupon_code.id,
    play_coupon_code.password,
    play_coupon_code.status,
    play_coupon_code.order_sn,
    play_coupon_code.check_status,
    play_coupon_code.use_datetime,
    play_coupon_code.test_status,
	play_order_info.phone,
    play_order_info.user_id,
    play_order_info.coupon_unit_price,
	play_order_info.coupon_name,
	play_order_info.dateline,
	play_order_info.username,
	play_game_info.account_money,
    play_game_info.price_name
FROM
	play_organizer_code_log
INNER JOIN play_coupon_code ON  play_coupon_code.id = play_organizer_code_log.code_id
INNER JOIN play_order_info ON  play_order_info.order_sn = play_coupon_code.order_sn
INNER JOIN play_game_info ON  play_game_info.id = play_order_info.bid
WHERE $where ORDER BY $order";
        $sql_list = $sql." LIMIT
{$start}, {$pageSum}";

        $data = $this->query($sql_list);
        $countData = $this->query("SELECT
	count(*) AS count_number
FROM
	play_organizer_code_log
LEFT JOIN play_coupon_code ON play_coupon_code.id = play_organizer_code_log.code_id
LEFT JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
LEFT JOIN play_game_info ON  play_game_info.id = play_order_info.bid
WHERE
	$where", array())->current();

        $count = $countData['count_number'];

        //创建分页
        $url = '/wftadlogin/business/codeList';
        $paging = new Paginator($page, $count, $pageSum, $url);

        return array(
            'data' => $data,
            'pageData' => $paging->getHtml(),
            'organizerData' => $organizerData,
        );
    }

    //提交结算
    public function auditAction()
    {

        $organizerId = (int)$this->getQuery('organizer_id');

        $organizerData = $this->_getPlayOrganizerTable()->get(array('id' => $organizerId));
        $accountData = $this->_getPlayOrganizerAccountTable()->get(array('organizer_id' => $organizerId));
        if (!$organizerData || !$accountData) {
            return $this->_Goto('非法操作');
        }

        if (!$_GET['check_start'] || !$_GET['check_end']) {
            return $this->_Goto('提请结算必须要使用时间');
        }

        if ($_GET['code_status'] != 1) {
            return $this->_Goto('提交结算的使用码状态不正确');
        }

        $where = $this->getAuditWhere($organizerId);

        $res = $this->checkAuditWhere($where);

        if (!$res['status']) {
            return $this->_Goto($res['message']);
        }


        $money = $this->query("SELECT
	SUM(play_organizer_code_log.flow_money) AS total_money
FROM
	play_organizer_code_log
INNER JOIN play_coupon_code ON play_coupon_code.id = play_organizer_code_log.code_id
INNER JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
INNER JOIN play_game_info ON  play_game_info.id = play_order_info.bid
WHERE
	$where AND play_organizer_code_log.transport_status = 2")->current();

        $vm = new ViewModel(array(
            'accountData' => $accountData,
            'money' => $money,
            'query' => json_encode($_GET),
        ));

        return $vm;

    }

    public function saveAuditAction()
    {
        $money = $this->getQuery('account_money', 0);
        $reason = $this->getQuery('reason', '');
        $id = (int)$this->getQuery('id', 0);
        $have_money = $this->getQuery('real_have_money', 0);

        $check_start = $this->getQuery('check_start', null);// 使用时间
        $check_end = $this->getQuery('check_end', null);

        if ($money <= 0) {
            return $this->_Goto('结算的钱不能为0');
        }

        if (!$check_start || !$check_end) {
            return $this->_Goto('提请结算必须要使用时间');
        }

        if ($_GET['code_status'] != 1) {
            return $this->_Goto('提交结算的使用码状态不正确');
        }

        $organizerData = $this->_getPlayOrganizerTable()->get(array('id' => $id));
        $accountData = $this->_getPlayOrganizerAccountTable()->get(array('organizer_id' => $id));
        if (!$organizerData || !$accountData) {
            return $this->_Goto('非法操作');
        }

        if ($id != $_GET['organizer_id']) {
            return $this->_Goto('非法操作');
        }

        $where = $this->getAuditWhere($id);

        $res = $this->checkAuditWhere($where);

        if (!$res['status']) {
            return $this->_Goto($res['message']);
        }

        //进入提交结算页面
        $moneyData = $this->query("SELECT
	SUM(play_organizer_code_log.flow_money) AS total_money
FROM
	play_organizer_code_log
INNER JOIN play_coupon_code ON play_coupon_code.id = play_organizer_code_log.code_id
INNER JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
INNER JOIN play_game_info ON  play_game_info.id = play_order_info.bid
WHERE
	$where AND play_organizer_code_log.transport_status = 2 AND play_organizer_code_log.audit_id = 0")->current();


        if (!$moneyData['total_money']) {
             return $this->_Goto('需结算的钱为0');
        }

        if ($moneyData['total_money'] != $have_money) {
            return $this->_Goto('出现异常， 请联系值班技术');
        }

        if (!$reason) {
            return $this->_Goto('请填写原因');
        }

        $reason = $reason. '_商家实际收入'. $have_money;

        if (floatval($moneyData['total_money']) < $money) {
            return $this->_Goto('大于商家实际收入, 如有问题请联系技术');
        }

        $code_id_list = $this->query("SELECT
	play_organizer_code_log.id,
	play_organizer_code_log.code_id
FROM
	play_organizer_code_log
INNER JOIN play_coupon_code ON play_coupon_code.id = play_organizer_code_log.code_id
INNER JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
INNER JOIN play_game_info ON  play_game_info.id = play_order_info.bid
WHERE
	$where AND play_organizer_code_log.transport_status = 2 AND play_organizer_code_log.audit_id = 0");


        $logId = '';
        $codeId = '';
        foreach ($code_id_list as $code_id) {
            $logId = $logId. ','. $code_id['id'];
            $codeId = $codeId. ','. $code_id['code_id'];
        }

        $logId = trim($logId, ',');
        $codeId = trim($codeId, ',');
        if (!$codeId || !$logId) {
            return $this->_Goto('请联系技术');
        }

        $data = array(
            'create_time' => time(),
            'organizer_id' => $id,
            'audit_type' => 3,
            'flow_money' => $money,
            'reason' => $reason,
            'use_start' => strtotime($check_start),
            'use_end' => strtotime($check_end),
            'serial_number' => date('YmdHis') . mt_rand(1000, 9999), //流水号
        );

        $status = $this->_getPlayOrganizeraccountAuditTable()->insert($data);
        $audit_id = $this->_getPlayOrganizeraccountAuditTable()->getlastInsertValue();

        //同时扣掉商家 可提现金额
        if ($status) {
            $organizerAccount = new OrganizerAccount();

            $mer = $organizerAccount->audit($id, $money, 1, $audit_id);

            if (!$mer) {
                return $this->_Goto('请联系技术4');
            }
        }

        if ($status && $audit_id) {

            $s1 = $this->_getPlayCouponCodeTable()->update(array('test_status' => 3), array('id in('. $codeId. ')'));
            $s2 = $this->query("UPDATE play_organizer_code_log SET play_organizer_code_log.audit_id = {$audit_id} where id in ({$logId})")->count();

            if (!$s1 || !$s2) {
                return $this->_Goto('请联系技术3'. $s1. $s2);
            }

            return $this->_Goto('成功', '/wftadlogin/business/codeList?organizer_id='. $id);
        }

        return $this->_Goto('失败', '/wftadlogin/business/codeList?organizer_id='. $id);

    }

    //查询条件统一入口
    private function getAuditWhere($organizer_id) {

        $where = 'play_organizer_code_log.organizer_id = '. $organizer_id;
        $where = $where. ' AND play_organizer_code_log.transport_type = 1';

        $good_name = $this->getQuery('good_name', null);
        $type_name = $this->getQuery('type_name', '');
        //商品名称
        if ($good_name) {
            $where = $where. " AND play_order_info.coupon_name like '%".$good_name."%'";
        }
        //套系名称
        if ($type_name) {
            $where = $where. " AND play_game_info.price_name like '%".$type_name."%'";
        }

        $order_id = $this->getQuery('order_id', 0);
        $code_number = $this->getQuery('code_number', null);
        $good_id = (int)$this->getQuery('good_id', 0);

        //使用码
        if ($code_number) {
            $code_id = (int)substr($code_number, 0, -7);
            $where = $where. " AND play_coupon_code.id = ". $code_id;
        }

        //订单id
        if ($order_id) {
            $order_id = (int)preg_replace('|[a-zA-Z/]+|','',$order_id);
            $where = $where. " AND play_order_info.order_sn = ". $order_id;
        }

        //商品id
        if ($good_id) {
            $where = $where. " AND play_order_info.coupon_id = ". $good_id;
        }

        $check_start = $this->getQuery('check_start', null);//使用时间
        $check_end = $this->getQuery('check_end', null);

        //使用时间
        if ($check_start && $check_end && strtotime($check_start) > strtotime($check_end)) {
            return array('status' => 0, 'message' => '使用时间出错');
        }

        if ($check_start) {
            $check_start = strtotime($check_start);
            $where = $where. " AND play_coupon_code.use_datetime > ".$check_start;
        }

        if ($check_end) {
            $check_end = strtotime($check_end) + 86400;
            $where = $where. " AND play_coupon_code.use_datetime < ". $check_end;
        }

        $code_status = $this->getQuery('code_status', 0);

        //使用码状态
        if ($code_status) {
            if ($code_status == 1) {//已使用
                $where = $where. " AND play_coupon_code.`test_status` = 0 AND play_coupon_code.`force` = 0";
            } elseif ($code_status == 2) {//已提交结算
                $where = $where. " AND play_coupon_code.`test_status` = 3";
            } elseif ($code_status == 3) {//已受理结算
                $where = $where. " AND play_coupon_code.`test_status` = 4";
            } elseif ($code_status == 4) {//已结算
                $where = $where. " AND play_coupon_code.`test_status` = 5";
            } elseif ($code_status == 5) {//全部
                $where = $where. " AND !(play_coupon_code.`force` = 3 && play_coupon_code.`test_status` = 0)";
            }
        } else {
            $where = $where. " AND play_coupon_code.`test_status` = 0 AND play_coupon_code.`force` = 0";
        }

        return $where;
    }

    //检验条件
    private function checkAuditWhere($where)
    {
        $checkCode = $this->query("SELECT
	play_coupon_code.id
FROM
	play_organizer_code_log
INNER JOIN play_coupon_code ON play_coupon_code.id = play_organizer_code_log.code_id
INNER JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
INNER JOIN play_game_info ON play_game_info.id = play_order_info.bid
WHERE
	$where AND play_order_info.approve_status = 1")->current();

        if ($checkCode['id']) {
            return array(
                'status' => 0,
                'message' => '你所选择的验证时间范围内，有未审核的订单，请联系财务进行审核，再进行结算',
            );
        }

        //合同未审核
        $checkContract = $this->query("SELECT
	play_game_info.price_name,
	play_order_info.coupon_id
FROM
	play_organizer_code_log
INNER JOIN play_coupon_code ON play_coupon_code.id = play_organizer_code_log.code_id
INNER JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
INNER JOIN play_game_info ON play_game_info.id = play_order_info.bid
INNER JOIN play_contract_link_price ON play_contract_link_price.id = play_game_info.contract_price_id
WHERE
	$where AND play_contract_link_price.status != 3")->current();

        if ($checkContract['price_name']) {
            return array(
                'status' => 0,
                'message' => '商品id '. $checkContract['coupon_id'] .' 相关套系【'. $checkContract['price_name'] .'】 对应的合同套系 没有审核',
            );
        }


        //已提交结算的使用码
        $testData = $this->query("SELECT
	play_coupon_code.id
FROM
	play_organizer_code_log
INNER JOIN play_coupon_code ON play_coupon_code.id = play_organizer_code_log.code_id
INNER JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
INNER JOIN play_game_info ON play_game_info.id = play_order_info.bid
WHERE
	$where AND play_coupon_code.test_status >= 3")->current();

        if ($testData['id']) {
            return array(
                'status' => 0,
                'message' => '该条件下有已提交结算的使用码',
            );
        }

        //进入提交结算页面
        $moneyData = $this->query("SELECT
	play_organizer_code_log.id
FROM
	play_organizer_code_log
INNER JOIN play_coupon_code ON play_coupon_code.id = play_organizer_code_log.code_id
INNER JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
INNER JOIN play_game_info ON play_game_info.id = play_order_info.bid
WHERE
	$where AND play_organizer_code_log.transport_status = 1")->current();

        if ($moneyData['id']) {
            return array(
                'status' => 0,
                'message' => '请等10分钟左右后再来提交结算',
            );
        }

        return array(
            'status' => 1,
            'message' => 'ok',
        );
    }

    //商家页面提交结算导出 结果
    public function outAuditAction()
    {

        $organizerId = (int)$this->getQuery('organizer_id');

        $organizerData = $this->_getPlayOrganizerTable()->get(array('id' => $organizerId));
        if (!$organizerData) {
            return $this->_Goto('非法操作');
        }

        $order = "play_order_info.order_sn DESC";
        $where = $this->getAuditWhere($organizerId);

        $sql = "SELECT
    play_organizer_code_log.transport_status,
    play_organizer_code_log.order_sn,
    play_order_info.dateline,
    play_order_info.account_type,
    play_order_info.trade_no,
    play_order_info.order_city,
    play_organizer.name AS organizer_name,
    play_order_info.coupon_unit_price,
	play_order_info.coupon_name,
	play_order_info.buy_number,
	play_order_info.real_pay,
    play_order_info.account_money AS balance_money,
    play_order_info.voucher,
    play_order_info.account,
	count(play_coupon_code.id) AS use_number,
	count(if(play_coupon_code.test_status=5,true,NULL)) AS account_number,
	MAX(play_coupon_code.use_datetime) AS use_datetime,
    MAX(play_coupon_code.account_time) AS account_time,
	play_order_info.phone,
    play_order_info.user_id,
	play_order_info.username,
	play_game_info.start_time,
	play_game_info.end_time,
	play_game_info.account_money,
    play_game_info.price_name,
    play_game_info.shop_name,
    play_contracts.business_taker
FROM
	play_organizer_code_log
INNER JOIN play_coupon_code ON play_coupon_code.id = play_organizer_code_log.code_id
INNER JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
INNER JOIN play_game_info ON play_game_info.id = play_order_info.bid
INNER JOIN play_organizer ON play_organizer.id = play_organizer_code_log.organizer_id
INNER JOIN play_contracts ON play_contracts.id = play_organizer_code_log.contract_id
WHERE $where
GROUP BY
  play_order_info.order_sn
ORDER BY
	$order";

        $data = $this->query($sql);

        $count_sql = "SELECT
COUNT(play_order_info.order_sn) AS count_num
FROM
	play_organizer_code_log
INNER JOIN play_coupon_code ON play_coupon_code.id = play_organizer_code_log.code_id
INNER JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
INNER JOIN play_game_info ON play_game_info.id = play_order_info.bid
WHERE $where
GROUP BY
  play_order_info.order_sn";

        $counter = $this->query($count_sql)->current();

        if (!$counter['count_num']) {
            return $this->_Goto('0条数据！');
        }

        if ($counter['count_num'] > 3000) {
            return $this->_Goto('超过系统最大负荷，请把时间设在1个月内，重新搜索');
        }

        $out = new OutPut();
        $file_name = date('Y-m-d H:i:s', time()). '_申请结算列表.csv';

        $tradeWay = array(
            'alipay' => '支付宝',
            'union' => '银联',
            'weixin' => '微信',
            'jsapi' => '旧微信网页',
            'account' => '账户',
            'new_jsapi' => '新微信网页',
            'nopay' => '未付款',
        );
        $city = $this->getAllCities();

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
            '已使用数',
            '已使用金额',
            '结算单价',
            '需结算数量',
            '已结算金额',
            '收益',
            '对方账户',
            '用户名',
            '手机号',
            '用户id',
            '活动地址 （那个游玩地）',
            '套系名称',
            '该套系的开始时间',
            '该套系的结束时间',
            '最近的使用时间',
            '最近的结算时间',
            '经办人',
        );


        $content = array();

        foreach ($data as $v) {

            $content[] = array(
                date('Y-m-d H:i:s', $v['dateline']),
                $tradeWay[$v['account_type']],
                "\t".$v['trade_no'],
                $city[$v['order_city']],
                'WFT' . (int)$v['order_sn'],
                $v['organizer_name'],
                $v['coupon_name'],
                $v['coupon_unit_price'],
                $v['buy_number'],
                bcadd($v['real_pay'], $v['balance_money'], 2),
                $v['voucher'],
                $v['use_number'],
                $v['use_number'] * $v['coupon_unit_price'],
                $v['account_money'],
                $v['use_number']- $v['account_number'],
                $v['account_money'] * $v['account_number'],
                '',
                $v['account'],
                $v['username'],
                $v['phone'],
                $v['user_id'],
                $v['shop_name'],
                $v['price_name'],
                date('Y-m-d H:i:s', $v['start_time']),
                date('Y-m-d H:i:s', $v['end_time']),
                date('Y-m-d H:i:s', $v['use_datetime']),
                $v['account_time'] ? date('Y-m-d H:i:s', $v['account_time']) : '',
                $v['business_taker'],
            );
        }


        $out->out($file_name, $head, $content);
        exit;
    }


    //结算的转账审批 查看明细
    public function auditInfoAction()
    {

        $good_name = trim($this->getQuery('good_name', ''));
        $good_id = (int)$this->getQuery('good_id', 0);
        $order_id = (int)$this->getQuery('order_id', 0);
        $trade_way = (int)$this->getQuery('trade_way', 0);
        $organizer_name = trim($this->getQuery('organizer_name', ''));
        $organizer_id = (int)$this->getQuery('organizer_id', 0);
        $id = (int)$this->getQuery('id', 0);

        $where = 'play_organizer_code_log.audit_id > 0';

        $organizerData = NULL;

        if ($id) {
            $auditData = $this->_getPlayOrganizeraccountAuditTable()->get(array('id' => $id));

            if (!$auditData) {
                return $this->_Goto('非法操作');
            }

            $organizerData = $this->_getPlayOrganizerTable()->get(array('id' => $auditData->organizer_id));

            if (!$organizerData) {
                return $this->_Goto('非法操作');
            }

            $where = $where. ' AND play_organizer_code_log.audit_id = '. $id;

            if ($organizer_id) {
                if ($organizerData->id != $organizer_id) {
                    return $this->_Goto('非法的结算商家');
                }
            }

            if ($organizer_name) {
                if ($organizerData->name != $organizer_name) {
                    return $this->_Goto('非法的结算商家');
                }
            }
        }

        if ($organizer_id) {
            $where = $where. ' AND play_organizer_code_log.organizer_id = '. $organizer_id;
        }

        if ($organizer_name) {
            $organizerDo = $this->_getPlayOrganizerTable()->get(array('name' => $organizer_name));

            if ($organizerDo) {
                $where = $where. ' AND play_organizer_code_log.organizer_id = '. $organizerDo->id;
            } else {
                $where = $where. ' AND play_organizer_code_log.organizer_id = 0';
            }

        }

        if ($good_name) {
            $where = $where. " AND play_order_info.coupon_name = '{$good_name}'";
        }

        if ($good_id) {
            $where = $where. ' AND play_order_info.coupon_id = '. $good_id;
        }

        if ($order_id) {
            $order_id = (int)preg_replace('|[a-zA-Z/]+|','',$order_id);
            $where = $where. " AND play_order_info.order_sn = ". $order_id;
        }

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

        $pageSum = (int)$this->getQuery('page_num', 10);
        $page = (int)$this->getQuery('p', 1);
        $start = ($page - 1) * $pageSum;
        $order = "play_organizer_code_log.id DESC";

        $codeData = $this->query("SELECT
	play_organizer_code_log.id,
    play_organizer_code_log.flow_money,
	play_organizer_code_log.code_id,
	play_order_info.dateline,
	play_order_info.account_type,
    play_order_info.trade_no,
    play_order_info.order_sn,
    play_order_info.user_id,
    play_order_info.coupon_id,
    play_order_info.real_pay,
    play_order_info.account_money,
    play_order_info.voucher,
    play_coupon_code.password,
    play_coupon_code.test_status,
    play_coupon_code.back_money
FROM
	play_organizer_code_log
INNER JOIN play_coupon_code ON play_coupon_code.id = play_organizer_code_log.code_id
INNER JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
WHERE
	$where
ORDER BY $order
LIMIT
{$start}, {$pageSum}");

        //统计数据  支付金额和代金券金额、已出账金额是订单的 其它当前条件的
        // 订单数 使用码数  支付金额 代金券金额  已出账金额 待结算金额 申请转账金额

        $countData = $this->query("SELECT
	SUM(Tmp.real_pay + Tmp.account_money) AS pay_money,
	SUM(Tmp.voucher) AS voucher_money,
	SUM(Tmp.count_number) AS count_number,
	SUM(Tmp.order_number) AS order_number,
	SUM(Tmp.need_account_money) AS need_account_money
FROM
	(
		SELECT
			play_order_info.real_pay,
			play_order_info.account_money,
			play_order_info.voucher,
			count(play_organizer_code_log.id) AS count_number,
			count(
				DISTINCT play_order_info.order_sn
			) AS order_number,
			SUM(

				IF (
					play_coupon_code.test_status = 4
					OR play_coupon_code.test_status = 3,
					play_organizer_code_log.flow_money,
					NULL
				)
			) AS need_account_money
		FROM
			play_organizer_code_log
		INNER JOIN play_coupon_code ON play_coupon_code.id = play_organizer_code_log.code_id
		INNER JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
		WHERE
			$where
		GROUP BY
			play_order_info.order_sn
	) AS Tmp")->current();

        $count = $countData['count_number'];


        $countMer = $this->query("SELECT
	SUM(
		IF (
			play_coupon_code.force = 3
			OR play_coupon_code.status = 2,
			play_coupon_code.back_money,
			NULL
		)
	) AS back_money
FROM
	play_coupon_code
WHERE
	play_coupon_code.order_sn IN (
		SELECT
			tmp.order_sn
		FROM
			(
				SELECT
					play_coupon_code.*
				FROM
					play_organizer_code_log
				INNER JOIN play_coupon_code ON play_coupon_code.id = play_organizer_code_log.code_id
				INNER JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
				WHERE
					$where
				GROUP BY
					play_order_info.order_sn
			) AS tmp
	)")->current();


        $flowData = $this->query("SELECT
	SUM(tp.flow_money) AS flow_money
FROM
	(
		SELECT
			play_organizeraccount_audit.flow_money
		FROM
			play_organizeraccount_audit
		INNER JOIN play_organizer_code_log ON play_organizer_code_log.audit_id = play_organizeraccount_audit.id
		INNER JOIN play_coupon_code ON play_coupon_code.id = play_organizer_code_log.code_id
		INNER JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
		WHERE
			$where
		GROUP BY
			play_organizeraccount_audit.id
	) AS tp")->current();

        $counter = array(
            'order_number' => $countData['order_number'],
            'code_number' => $countData['count_number'],
            'pay_money' => $countData['pay_money'],
            'voucher_money' => $countData['voucher_money'],
            'back_money' => $countMer['back_money'],
            'need_account_money' => $countData['need_account_money'],
            'out_pay_money' => $flowData['flow_money']
        );


        //创建分页
        $url = '/wftadlogin/business/auditInfo';
        $paging = new Paginator($page, $count, $pageSum, $url);
        $OrganizerAccount = new OrganizerAccount();
        $vm = new ViewModel(array(
            'codeData' => $codeData,
            'pageData' => $paging->getHtml(),
            'counter' => $counter,
            'tradeWay' => $OrganizerAccount->tradeWay,
            'organizerData' => $organizerData,
        ));

        return $vm;

    }

    public function outAuditInfoAction()
    {
        $good_name = trim($this->getQuery('good_name', ''));
        $good_id = (int)$this->getQuery('good_id', 0);
        $order_id = (int)$this->getQuery('order_id', 0);
        $trade_way = (int)$this->getQuery('trade_way', 0);
        $organizer_name = trim($this->getQuery('organizer_name', ''));
        $organizer_id = (int)$this->getQuery('organizer_id', 0);
        $id = (int)$this->getQuery('id', 0);

        $where = 'play_organizer_code_log.audit_id > 0';

        $organizerData = NULL;

        if ($id) {
            $auditData = $this->_getPlayOrganizeraccountAuditTable()->get(array('id' => $id));

            if (!$auditData) {
                return $this->_Goto('非法操作');
            }

            $organizerData = $this->_getPlayOrganizerTable()->get(array('id' => $auditData->organizer_id));

            if (!$organizerData) {
                return $this->_Goto('非法操作');
            }

            $where = $where. ' AND play_organizer_code_log.audit_id = '. $id;

            if ($organizer_id) {
                if ($organizerData->id != $organizer_id) {
                    return $this->_Goto('非法的结算商家');
                }
            }

            if ($organizer_name) {
                if ($organizerData->name != $organizer_name) {
                    return $this->_Goto('非法的结算商家');
                }
            }
        }

        if ($organizer_id) {
            $where = $where. ' AND play_organizer_code_log.organizer_id = '. $organizer_id;
        }

        if ($organizer_name) {
            $where = $where. " AND play_organizer.name = '{$organizer_name}'";
        }

        if ($good_name) {
            $where = $where. " AND play_order_info.coupon_name = '{$good_name}'";
        }

        if ($good_id) {
            $where = $where. ' AND play_order_info.coupon_id = '. $good_id;
        }

        if ($order_id) {
            $order_id = (int)preg_replace('|[a-zA-Z/]+|','',$order_id);
            $where = $where. " AND play_order_info.order_sn = ". $order_id;
        }

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

        $order = 'play_order_info.order_sn DESC';

        $sql = "SELECT
    play_order_info.order_sn,
	play_order_info.back_number,
	play_order_info.buy_phone,
	play_order_info.coupon_name,
	play_order_info.shop_name,
	play_order_info.dateline,
	play_order_info.username,
	play_order_info.coupon_id,
	play_order_info.pay_status,
    play_order_info.trade_no,
	play_order_info.account_type,
	play_order_info.account,
	play_order_info.order_type,
	play_order_info.coupon_unit_price,
	play_order_info.buy_number,
	play_order_info.voucher,
	play_order_info.phone,
    play_order_info.order_city,
	play_order_info.user_id,
    play_order_info.real_pay,
    play_order_info.account_money as balance_money,
    MAX(play_coupon_code.use_datetime) AS use_datetime,
    play_order_info.use_number,
    count(if(play_coupon_code.test_status=5, true, null)) AS account_number,
    count(play_organizer_code_log.id) AS need_account_number,
	play_game_info.shop_name AS game_dizhi,
	play_game_info.price_name AS game_taoxi,
	play_game_info.start_time AS game_start,
	play_game_info.end_time AS game_end,
	play_game_info.account_money,
	play_organizer.name,
	play_organizer_account.bank_name,
	play_organizer_account.bank_card,
	play_game_info.up_time,
	play_game_info.down_time,
	play_game_info.refund_time
FROM
	play_organizer_code_log
LEFT JOIN play_coupon_code ON play_coupon_code.id = play_organizer_code_log.code_id
LEFT JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
LEFT JOIN play_game_info ON play_game_info.id = play_order_info.bid
LEFT JOIN play_organizer ON play_organizer_code_log.organizer_id = play_organizer.id
LEFT JOIN play_organizer_account ON play_organizer_account.organizer_id = play_organizer.id
WHERE
	 $where
GROUP BY
  play_order_info.order_sn
ORDER BY
	$order";

        $data = $this->query($sql);

        $out = new OutPut();
        $tradeWay = array(
            'weixin' => '微信',
            'union' => '银联',
            'alipay' => '支付宝',
            'jsapi' => '旧微信网页',
            'account' => '用户账户',
            'new_jsapi' => '新微信网页',
        );
        $city = $this->getAllCities();

        $file_name = date('Y-m-d H:i:s', time()). '_转帐审批详情列表.csv';
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
            '已使用数',
            '已使用金额',
            '等待退款金额',
            '已退款金额',
            '已结算金额',
            '结算单价',
            '需结算数量',
            '需结算金额',
            '本次结算收益',
            '商家开户行',
            '商家账号',
            '用户账户',
            '用户名',
            '手机号',
            '用户id',
            '活动地址 （那个游玩地）',
            '套系名称',
            '该套系的开始时间',
            '该套系的结束时间',
            '最近的使用时间',
            '类别',
            '商品id',
        );

        $content = array();

        foreach ($data as $v) {
            //该导出里面 跟code 相关的 全部以  订单为单位 无视筛选条件 除开 需结算数量(已提交结算 和已受理结算的) 已当前为条件
            //等待退款的为 已受理退款的
            $order_sn = (int)$v['order_sn'];

            $codeSql = "SELECT SUM(if(play_coupon_code.force=2, play_coupon_code.back_money, null)) AS wait_money, SUM(if(play_coupon_code.force=3 OR play_coupon_code.status = 2, play_coupon_code.back_money, null)) AS back_money, COUNT(if(play_coupon_code.test_status = 5, true, NULL)) AS have_account_number from play_coupon_code where order_sn = {$order_sn}";

            $codeData = $this->query($codeSql)->current();

            $content[] = array(
                date('Y-m-d H:i:s', $v['dateline']),
                $tradeWay[$v['account_type']],
                "\t".$v['trade_no'],
                $city[$v['order_city']],
                'WFT' . $order_sn,
                $v['shop_name'],
                $v['coupon_name'],
                $v['coupon_unit_price'],
                $v['buy_number'],
                bcadd($v['real_pay'], $v['balance_money'], 2),
                $v['voucher'],
                $v['use_number'],
                $v['use_number'] * $v['coupon_unit_price'],
                $codeData['wait_money'],
                $codeData['back_money'],
                $codeData['have_account_number'] * $v['account_money'],
                $v['account_money'],
                $v['need_account_number'] - $v['account_number'],
                bcmul(($v['need_account_number'] - $v['account_number']), $v['account_money'], 2),
                bcmul(($v['need_account_number'] - $v['account_number']), bcsub($v['coupon_unit_price'], $v['account_money'], 2), 2),
                $v['bank_name'],
                $v['bank_card'],
                $v['account'],
                $v['username'],
                $v['phone'],
                $v['user_id'],
                $v['game_dizhi'],
                $v['game_taoxi'],
                date('Y-m-d H:i:s', $v['game_start']),
                date('Y-m-d H:i:s', $v['game_end']),
                date('Y-m-d H:i:s',  $v['use_datetime']),
                '商品',
                $v['coupon_id'],
            );
        }

        $out->out($file_name, $head, $content);
        exit;
    }


}