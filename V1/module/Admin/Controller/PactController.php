<?php

namespace Admin\Controller;

use Deyi\GetCacheData\CityCache;
use Deyi\JsonResponse;
use Deyi\Paginator;
use Deyi\Contract\Contract;
use Deyi\OutPut;
use library\Fun\M;
use library\Service\System\Logger;
use Zend\View\Model\ViewModel;

class PactController extends BasisController{

    use JsonResponse;

    //合同列表
    /*public function indexAction() {
        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;
        $order = "play_contracts.id DESC";

        $where = $this->getContractWhere();

        $contract_sql = "SELECT
play_contracts.id,
play_contracts.contract_no,
play_contracts.create_time,
play_admin.admin_name,
play_organizer.name AS organizer_name,
play_contracts.contracts_type,
play_contracts.contracts_status,
play_contracts.check_status,
play_contracts.status,
play_contracts.pre_money,
play_contracts.check_dateline,
play_contracts.pay_pre_status
FROM play_contracts
LEFT JOIN play_organizer ON play_organizer.id = play_contracts.mid
LEFT JOIN play_admin ON play_admin.id=play_contracts.business_id
WHERE $where";

        $count =  $this->query("SELECT play_contracts.id FROM play_contracts LEFT JOIN play_organizer ON play_organizer.id = play_contracts.mid LEFT JOIN play_admin ON play_admin.id=play_contracts.business_id WHERE $where")->count();
        $contract_data = $this->query($contract_sql." ORDER BY {$order} LIMIT {$start}, {$pageSum}");

        //创建分页
        $url = '/wftadlogin/contract';

        $paging = new Paginator($page, $count, $pageSum, $url);
        $contractData = array();
        foreach ($contract_data as $contract) {
            $saleData = $this->getContractSale($contract['id']);
            $contractData[] = array(
                'id' => $contract['id'],
                'contract_no' => $contract['contract_no'],
                'create_time' => $contract['create_time'],
                'admin_name' => $contract['admin_name'],
                'organizer_name' => $contract['organizer_name'],
                'contracts_type' => $contract['contracts_type'],
                'pre_money' => $contract['pre_money'],
                'contracts_status' => $contract['contracts_status'],
                'check_status' => $contract['check_status'],
                'status' => $contract['status'],
                'goods_num' => $saleData['goods_num'],
                'order_num' => $saleData['order_num'],
                'shop_pre_income' => $saleData['shop_pre_income'],
                'shop_real_income' => $saleData['shop_real_income'],
                'deyi_income' => $saleData['deyi_income'],
                'sub_dateline' => $contract['check_dateline'],
                'pay_pre_status'=>$contract['pay_pre_status']
            );
        }



        return array(
            'data' => $contractData,
            'pageData' => $paging->getHtml(),
            'contractStatus' => $this->contractStatus,
            'contract_type' => $this->contractType,

        );
    }*/


    //财务 合同审核列表
    public function listAction(){
        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;
        $order = "play_contracts.id DESC";

        $where = $this->getContractWhere();

        $contract_sql = "SELECT
play_contracts.id,
play_contracts.contract_no,
play_contracts.create_time,
play_contracts.business_taker,
play_contracts.organizer_name,
play_contracts.contracts_type,
play_contracts.check_status,
play_contracts.check_dateline
FROM play_contracts WHERE $where";

        $count= M::getAdapter()->query("SELECT count(*) as c FROM play_contracts WHERE $where",array())->current()->c;
        $contract_data = $this->query($contract_sql." ORDER BY {$order} LIMIT {$start}, {$pageSum}");

        //创建分页
        $url = '/wftadlogin/pact/list';

        $paging = new Paginator($page, $count, $pageSum, $url);
        $contractData = array();
        $Contract = new Contract();
        foreach ($contract_data as $contract) {
            $extInfo = $Contract->getExtInfo($contract['id']);
            $preMoney = $Contract->getPreMoney($contract['id']);
            $contractData[] = array(
                'id' => $contract['id'],
                'contract_no' => $contract['contract_no'],
                'create_time' => $contract['create_time'],
                'business_taker' => $contract['business_taker'],
                'organizer_name' => $contract['organizer_name'],
                'contracts_type' => $contract['contracts_type'],
                'check_status' => $contract['check_status'],
                'goods_num' => $extInfo['goods_num'],
                'order_num' => $extInfo['order_num'],
                'pre_money' => $preMoney
            );
        }
        return array(
            'data' => $contractData,
            'pageData' => $paging->getHtml(),
        );
    }

    //获取合同未提交审批的预付金金额
    private function getContractPreMoney($id)
    {
        $money = 0;
        $contractData = $this->_getPlayContractsTable()->get(array('id' => $id));

        if (!$contractData)
        {
            return $money;
        }

        $adapter = $this->_getAdapter();
        if ($contractData->contracts_type == 1) //包销
        {
            $preData = $adapter->query("select SUM(play_inventory.pre_money) as pre_money from play_inventory where contract_id = ? AND check_pre_status = ?", array($id, 0))->current();
            return $preData->pre_money;
        }

        if ($contractData->contracts_type == 3) //代销
        {
            $preData = $adapter->query("select SUM(play_contract_link_price.pre_money) as pre_money from play_contract_link_price where contract_id = ? AND check_pre_status = ?", array($id, 0))->current();
            return $preData->pre_money;
        }

        return $money;

    }


    //获取合同的销售情况
    private function getContractSale($id) {

        $data = array(
            'goods_num' => 0,
            'order_num' => 0,
            'shop_pre_income' => 0,
            'shop_real_income' => 0,
            'deyi_income' => 0,
        );

        $contractData = $this->_getPlayContractsTable()->get(array('id' => $id));

        if (!$contractData) {
            return $data;
        }

        // $goodData = $this->_getPlayOrganizerGameTable()->fetchLimit(0, 1000, array('id'), array('contract_id' => $id), array());
        $good_num_sql = "SELECT
id
FROM play_organizer_game
WHERE play_organizer_game.contract_id = {$id} AND play_organizer_game.status = 1";

        $goodData = $this->query($good_num_sql);

        $data['goods_num'] = $goodData->count();

        if (!$data['goods_num']) {
            return $data;
        }

        $good_id = '';
        foreach ($goodData as $good) {
            $good_id = $good_id. $good['id']. ',';
        }

        $good_id = trim($good_id, ',');

        //todo  订单数
        $order_num_sql = "SELECT
count(play_order_info.order_sn) as order_num
FROM play_order_info
WHERE play_order_info.coupon_id in ({$good_id}) AND play_order_info.order_status = 1 AND play_order_info.pay_status > 1";

        $orderData = $this->query($order_num_sql)->current();
        $data['order_num'] = $orderData['order_num'];

        if (!$data['order_num']) {
            return $data;
        }

        if ($contractData->contracts_type == 1) { //包销
            /*
                商家预计收入 预付款
                商家实际收入 预付款
                平台收入    订单里面 购买个数 = 使用数 + 已退款数  且 已使用数 > 0    sum使用金额（购买金额）-包销预付金 ?
            */

            $data['shop_pre_income'] = $contractData->pre_money;
            $data['shop_real_income'] = $contractData->pre_money;

            //已使用数 == 购买个数 已使用数 + 已退款数 = 购买数 团购
            $order_use_buy_sql = "SELECT
SUM((play_order_info.real_pay + play_order_info.account_money) *  play_order_info.use_number/play_order_info.buy_number) as use_buy_money
FROM play_order_info
WHERE play_order_info.coupon_id in ({$good_id}) AND play_order_info.order_status = 1 AND play_order_info.pay_status > 1 AND play_order_info.use_number > 0";

            $order_use_data = $this->query($order_use_buy_sql)->current();

            $data['deyi_income'] =$order_use_data['use_buy_money'] - $contractData->pre_money;


        } elseif ($contractData->contracts_type == 2) {//自营
            /*
                商家预计收入 预付款
                商家实际收入 预付款
                平台收入    订单里面 购买个数 = 使用数 + 已退款数  且 已使用数 > 0    sum使用金额（购买金额）-包销预付金 ?
            */

            $data['shop_pre_income'] = $contractData->pre_money;
            $data['shop_real_income'] = $contractData->pre_money;

            //已使用数 == 购买个数 已使用数 + 已退款数 = 购买数 团购
            $order_use_buy_sql = "SELECT
SUM((play_order_info.real_pay + play_order_info.account_money) *  play_order_info.use_number/play_order_info.buy_number) as use_buy_money
FROM play_order_info
WHERE play_order_info.coupon_id in ({$good_id}) AND play_order_info.order_status = 1 AND play_order_info.pay_status > 1 AND play_order_info.use_number > 0";

            $order_use_data = $this->query($order_use_buy_sql)->current();

            $data['deyi_income'] =$order_use_data['use_buy_money'] - $contractData->pre_money;

        } elseif ($contractData->contracts_type == 3) {//代销

            //代销
            /*
                商家预计收入  sum （购买数 * 结算价）
                商家实际收入  sum  使用数 * 结算价
                平台收入      sum使用金额（购买金额）- 商家实际收入
            */

            //购买数 * 结算价 使用数 * 结算价
            $order_buy_sql = "SELECT
SUM(play_game_info.account_money * play_order_info.buy_number) as  buy_money,
SUM(play_game_info.account_money * play_order_info.use_number) as  use_money
FROM play_order_info
LEFT JOIN  play_order_info_game ON play_order_info_game.order_sn = play_order_info.order_sn
LEFT JOIN  play_game_info ON play_game_info.id = play_order_info_game.game_info_id
WHERE play_order_info.coupon_id in ({$good_id}) AND play_order_info.order_status = 1 AND play_order_info.pay_status > 1";

            //sum使用金额（购买金额）
            $order_use_buy_sql = "SELECT
SUM((play_order_info.real_pay + play_order_info.account_money) *  play_order_info.use_number/play_order_info.buy_number) as use_buy_money
FROM play_order_info
WHERE play_order_info.coupon_id in ({$good_id}) AND play_order_info.order_status = 1 AND play_order_info.pay_status > 1 AND play_order_info.use_number > 0";

            $order_use_data = $this->query($order_use_buy_sql)->current();
            $order_buy_data = $this->query($order_buy_sql)->current();

            $data['shop_pre_income'] = $order_buy_data['buy_money'];
            $data['shop_real_income'] = $order_buy_data['use_money'];
            $data['deyi_income'] = $order_use_data['use_buy_money'] - $order_buy_data['use_money'];
        }

        return $data;
    }

    private function getContractWhere() {

        $start_time = trim($this->getQuery('start_time',null));
        $end_time = trim($this->getQuery('end_time',null));
        $contract_no = (int)$this->getQuery('contract_no',null);
        $operator = trim($this->getQuery('operator',null));
        $organizer = trim($this->getQuery('organizer',null));
        $contract_type = trim($this->getQuery('contract_type',null));
        $check_status = trim($this->getQuery('check_status', 4));
        $city = $this->chooseCity();

        $where = "play_contracts.id > 0";

        if($start_time && $end_time && $end_time >= $start_time){
            $where .=" AND play_contracts.create_time > ". strtotime($start_time);
            $where .=" AND play_contracts.create_time < ". (strtotime($end_time) + 86400);
        }

        if($contract_no){
            $where .= " AND play_contracts.contract_no = '{$contract_no}'";
        }

        if($operator){
            $where .= " AND play_contracts.business_taker = '{$operator}'";
        }

        if($organizer){
            $where .= " AND play_contracts.organizer_name = '{$organizer}'";
        }

        if($contract_type){
            $where .= " AND play_contracts.contracts_type = '{$contract_type}'";
        }

        if($check_status != 4){
            $where .= " AND play_contracts.check_status = '{$check_status}'";
        }

        if($city){
            $where .=  " AND play_contracts.city = '{$city}' ";
        }

        return $where;
    }

    //导出数据
    public function outDataAction() {
        $where = $this->getContractWhere();

        $city = $this->chooseCity();

        if($city){
            $where .=  " AND play_contracts.city = '{$city}' ";
        }

        $contract_sql = "SELECT
play_contracts.id,
play_contracts.contract_no,
play_contracts.create_time,
play_admin.admin_name,
play_organizer.name AS organizer_name,
play_contracts.contracts_type,
play_contracts.contracts_status,
play_contracts.check_status,
play_contracts.status,
play_contracts.pre_money,
play_contracts.check_dateline,
play_contracts.pay_pre_status,
play_organizer_account.bank_name,
play_organizer_account.bank_card,
play_organizer_account.bank_user,
play_organizer_account.bank_address
FROM play_contracts
LEFT JOIN play_organizer ON play_organizer.id = play_contracts.mid
LEFT JOIN play_organizer_account ON play_organizer_account.organizer_id = play_contracts.mid
LEFT JOIN play_admin ON play_admin.id=play_contracts.business_id
WHERE $where";

        $contract_data = $this->query($contract_sql);

        $head = array(
            '合同编号',
            '创建时间',
            '经办人',
            '商家',
            '收款人',
            '开户银行',
            '开户支行',
            '开户账号',
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

        foreach($contract_data as $v){
            $saleData = $this->getContractSale($v['id']);
            $content[] = array(
                "\n".$v['contract_no'],
                date('Y-m-d',$v['create_time']),
                $v['admin_name'],
                $v['organizer_name'],
                $v['bank_user'],
                $v['bank_name'],
                $v['bank_address'],
                "\n".$v['bank_card'],
                $this->contractType[$v['contracts_type']],
                $v['pre_money'],
                $this->contractStatus[$v['contracts_status']],
                $saleData['goods_num'],
                $saleData['order_num'],
                $saleData['shop_pre_income'],
                $saleData['shop_real_income'],
                $saleData['deyi_income'],
            );
        }

        $fileName = date('Y-m-d H:i:s', time()). '_合同列表.csv';
        $out = new OutPut();
        $out->out($fileName, $head, $content);
        exit;
    }

    //合同审核
    public function approveAction()
    {
        $cid = (int)$this->getQuery('id', 0);
        $type = (int)$this->getQuery('type', 0);
        $contractData = $this->_getPlayContractsTable()->get(array('id' => $cid, 'check_status' => 1));
        $Contract = new Contract();

        if (!$contractData || !in_array($contractData->contracts_type, array(1, 3)) || !in_array($type, array(1, 2))) {
            return $this->_Goto('非法操作');
        }

        if ($type == 2) {
            $status = $this->_getPlayContractsTable()->update(array('check_status'=> 0), array('id' => $cid, 'check_status' => 1));
            if (!$status) {
                return $this->_Goto('失败');
            }

            $m1 = $this->_getPlayContractLinkPriceTable()->update(array('status' => 1), array('contract_id'=>$cid,  'status' => 2));

            if (!$m1) {
                Logger::WriteErrorLog("合同审批不通过报异常 合同id: ". $cid. "\r\n");
            }

            if ($contractData->contracts_type == 1) {//包销
                $m3 = $this->_getPlayInventoryTable()->update(array('inventory_status' => 1), array('inventory_status' => 2, 'contract_id' => $cid));
                if (!$m3) {
                    Logger::WriteErrorLog("合同审批不通过报异常 包销合同 合同id: ". $cid. "\r\n");
                }
            }

            $Contract->addLog($cid, 6, '不通过');

            return $this->_Goto('成功');
        }

        $status = $this->_getPlayContractsTable()->update(array('check_status'=>2), array('id' => $cid, 'check_status' => 1));

        if (!$status) {
            return $this->_Goto('失败');
        }

        $preMoney = $Contract->getPreMoney($cid)['wait'];
        $type = 3;
        if ($preMoney > 0) {
            $data = array(
                'create_time' => time(),
                'organizer_id' => $contractData->mid,
                'contract_id' => $cid,
                'contract_num' => $contractData->contract_no,
                'audit_type' => ($contractData->contracts_type == 1) ? 1 : 2,
                'flow_money' => $preMoney,
                'check_status' => 2,
                'reason' => ($contractData->contracts_type == 1) ? '包销预付款' : '代销预付款',
                'serial_number' => date('YmdHis') . mt_rand(1000, 9999), //流水号
            );

            $flag = $this->_getPlayOrganizeraccountAuditTable()->insert($data);

            if (!$flag) {
                Logger::WriteErrorLog("合同审批通过报异常 预付款 合同id: ". $cid. "\r\n");
                return $this->_Goto('异常 请联系技术');
            }
            $auditId = $this->_getPlayOrganizeraccountAuditTable()->getlastInsertValue();

            $preData = array();
            if ($contractData->contracts_type == 1) {//包销
                $preData = $this->_getPlayInventoryTable()->fetchAll(array('check_pre_status' => 0, 'pre_money > ?' => 0,'contract_id' => $contractData->id));
            }

            if ($contractData->contracts_type == 3) {//代销
                $preData = $this->_getPlayContractLinkPriceTable()->fetchAll(array('check_pre_status' => 0, 'pre_money > ?' => 0, 'contract_id' => $contractData->id));

            }
            //todo 优化 整体事务
            foreach ($preData as $pre) {
                if ($pre['pre_money'] > 0) {
                    $this->_getPlayPreMoneyLogTable()->insert(array(
                        'object_id' => $pre['id'],
                        'contract_id' => $cid,
                        'aduit_id' => $auditId,
                        'pre_money' => $pre['pre_money'],
                    ));
                }
            }

            $log = $this->_getPlayPreLogTable()->insert(array(
                'aduit_id' => $auditId,
                'contract_id' => $cid,
                'pre_money' => $preMoney,
                'approve_time' => time(),
                'type' => 1,
                'editor_id' => $_COOKIE['id'],
                'editor' => $_COOKIE['user']
            ));

            if (!$log) {
                return $this->_Goto('异常 请联系技术');
            }

            $tip = false;
            if ($contractData->contracts_type == 1) //包销
            {
                $tip = $this->_getPlayInventoryTable()->update(array('check_pre_status' => 1), array('check_pre_status' => 0, 'pre_money > 0', 'contract_id' => $contractData->id));
            }

            if ($contractData->contracts_type == 3) //代销
            {
                $tip = $this->_getPlayContractLinkPriceTable()->update(array('check_pre_status' => 1), array('check_pre_status' => 0, 'pre_money > 0', 'contract_id' => $contractData->id));

            }

            if (!$tip) {
                return $this->_Goto('异常 请联系技术');
            }

            $type = 5;

        }

        if ($contractData->contracts_type == 1) {//包销
            $m4 = $this->_getPlayInventoryTable()->update(array('inventory_status' => 3), array('inventory_status' => 2, 'contract_id' => $cid));
            if (!$m4) {
                Logger::WriteErrorLog("合同审批通过报异常 包销合同 合同id: ". $cid. "\r\n");
            }
        }

        $m2 = $this->_getPlayContractLinkPriceTable()->update(array('status' => 3), array('contract_id'=>$cid,  'status' => 2));

        if (!$m2) {
            Logger::WriteErrorLog("合同审批通过报异常 合同id: ". $cid. "\r\n");
        }


        $Contract->addLog($cid, $type, ($type == 3) ? '审批通过' : '审批预付金');

        return $this->_Goto('成功');

    }

    //合同反审核
    public function backApproveAction()
    {
        $cid = (int)$this->getQuery('id', 0);
        $contractData = $this->_getPlayContractsTable()->get(array('id' => $cid, 'check_status' => 2));
        if (!$contractData) {
            return $this->_Goto('非法操作');
        }

        $status = $this->_getPlayContractsTable()->update(array('check_status'=>0), array('id' => $cid, 'check_status' => 2));
        if($status){
            $Contract = new Contract();
            $Contract->addLog($cid, 4, '反审核');
            return $this->_Goto('成功');
        }

        return $this->_Goto('反审批失败');
    }

    //财务查看的合同详情
    public function infoAction()
    {
        $cid = (int)$this->getQuery('cid', 0);

        $contractData = $this->_getPlayContractsTable()->get(array('id' => $cid));
        if (!$contractData) {
            return $this->_Goto('非法操作');
        }

        //商家账户
        $accountData = $this->_getPlayOrganizerAccountTable()->get(array('organizer_id' => $contractData->mid));
        //预付金提现记录
        $auditData = $this->_getPlayPreLogTable()->fetchAll(array('contract_id' => $cid));

        //合同操作记录表
        $contractLog = $this->_getPlayContractActionTable()->fetchAll(array('contract_id' => $cid));

        //商品记录表
        //分不同合同类型的商品

        $goods = array();
        $adapter = $this->_getAdapter();
        if($contractData['contracts_type']==1) {
            //包销库存
            $inventoryData = $this->_getPlayInventoryTable()->fetchAll(array('contract_id' => $cid, 'inventory_status > ?' => 0));

            if (!$inventoryData->count()) {
                return $this->_Goto('包销合同库存不存在');
            }

            foreach ($inventoryData as $inventory) {

                $query_sql = "SELECT play_contract_link_price.*, play_game_price.name AS price_name FROM play_contract_link_price
LEFT JOIN play_game_price ON play_game_price.contract_link_id = play_contract_link_price.id
WHERE play_contract_link_price.status > 0 AND play_contract_link_price.inventory_id = {$inventory['id']} AND play_contract_link_price.good_id = {$inventory['good_id']} GROUP BY play_contract_link_price.id";
                $linkData = $adapter->query($query_sql, array())->toArray();

                $goods[] = array(
                    'good_id' => $inventory['good_id'],
                    'good_name' => $inventory['good_name'],
                    'price_name' => '',
                    'pre_money' => $inventory['pre_money'],
                    'account_money' => $inventory['account_money'],
                    'money' => '',
                    'price' => '',
                    'link_price' => $linkData,
                    'status' => null,
                );
            }

        } elseif ($contractData['contracts_type']==3){
            //代销商品
            //play_contract_link_price 价格方案  加上预付金 去掉数量
            $query_sql = "SELECT play_contract_link_price.*, play_organizer_game.title, play_game_price.name AS price_name FROM play_contract_link_price
LEFT JOIN play_organizer_game ON play_organizer_game.id = play_contract_link_price.good_id
LEFT JOIN play_game_price ON play_game_price.contract_link_id = play_contract_link_price.id
WHERE play_contract_link_price.status > 0 AND play_contract_link_price.contract_id = {$cid} GROUP BY play_contract_link_price.id";
            $linkData = $adapter->query($query_sql, array());
            foreach ($linkData as $link) {
                $goods[] = array(
                    'good_id' => $link['good_id'],
                    'good_name' => $link['title'],
                    'price_name' => $link['price_name'],
                    'pre_money' => $link['pre_money'],
                    'account_money' => $link['account_money'],
                    'money' => $link['money'],
                    'price' => $link['price'],
                    'link_price' =>  array(),
                    'status' => $link['status'],
                );
            }

        }else{
            $goods = null;
        }

        $Contract = new Contract();
        $vm = new ViewModel(array(
            'contractData' => $contractData,
            'accountData' => $accountData,
            'auditData' => $auditData,
            'contractLog' => $contractLog,
            'goods'=>$goods,
            'preMoney' => $Contract->getPreMoney($cid),
        ));
        return $vm;
    }

}

