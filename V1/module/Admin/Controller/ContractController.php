<?php
/**
 * Created by PhpStorm.
 * User: dddee
 * Date: 2015/12/29
 * Time: 10:59
 */
namespace Admin\Controller;

use Deyi\Account\OrganizerAccount;
use Deyi\Coupon\Good;
use Deyi\GetCacheData\CityCache;
use Deyi\JsonResponse;
use Deyi\Contract\Contract;
use library\Fun\M;
use library\Service\System\Logger;
use Zend\Db\Sql\Predicate\In;
use Deyi\Paginator;
use Deyi\OutPut;
use Zend\View\Model\ViewModel;

class ContractController extends BasisController{

    use JsonResponse;

    //合同列表
    public function indexAction(){
        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;
        $start_time = trim($this->getQuery('start_time',null));
        $end_time = trim($this->getQuery('end_time',null));
        $contract_no = (int)$this->getQuery('contract_no',null);
        $operator = trim($this->getQuery('operator',null));
        $organizer = trim($this->getQuery('organizer',null));
        $contract_type = trim($this->getQuery('contract_type',null));
        $check_status = trim($this->getQuery('check_status', 4));

        $city = $this->chooseCity();

        $where = "play_contracts.id > 0";

        if($city){
            $where .=  " AND play_contracts.city = '{$city}' ";
        }

        $order = "play_contracts.id DESC";

        if ($start_time && $end_time && $end_time >= $start_time) {
            $where .=" AND play_contracts.create_time > ". strtotime($start_time);
            $where .=" AND play_contracts.create_time < ". (strtotime($end_time) + 86400);
        }

        if($contract_no){
            $where .= " AND play_contracts.contract_no = '{$contract_no}'";
        }

        if($operator){
            $where .= " AND play_contracts.business_taker = '{$operator}'";
        }

        if ($organizer) {
            if (is_numeric($organizer)) {
                $where .= " AND play_contracts.mid = {$organizer}";
            } else {
                $where .= " AND play_contracts.organizer_name  like '%{$organizer}%'";
            }
        }

        if($contract_type){
            $where .= " AND play_contracts.contracts_type = '{$contract_type}'";
        }

        if($check_status != 4){
            $where .= " AND play_contracts.check_status = '{$check_status}'";
        }

        $contract_sql = "SELECT
play_contracts.id,
play_contracts.contract_no,
play_contracts.create_time,
play_contracts.business_taker,
play_contracts.organizer_name,
play_contracts.contracts_type,
play_contracts.check_status
FROM play_contracts WHERE $where";


        $count= M::getAdapter()->query("SELECT count(*) as c FROM play_contracts WHERE $where",array())->current()->c;

        $contract_data = $this->query($contract_sql." ORDER BY {$order} LIMIT {$start}, {$pageSum}");

        //创建分页
        $url = '/wftadlogin/contract';

        $paging = new Paginator($page, $count, $pageSum, $url);
        $contractData = array();
        $Contract = new Contract();
        foreach ($contract_data as $contract) {
            $extInfo = $Contract->getExtInfo($contract['id']);
            $preMoney = $Contract->getPreMoney($contract['id'])['total'];
            $contractData[] = array(
                'id' => $contract['id'],
                'contract_no' => $contract['contract_no'],
                'create_time' => $contract['create_time'],
                'business_taker' => $contract['business_taker'],
                'organizer_name' => $contract['organizer_name'],
                'contracts_type' => $contract['contracts_type'],
                'check_status' => $contract['check_status'],
                'pre_money' => $preMoney,
                'goods_num' => $extInfo['goods_num'],
                'order_num' => $extInfo['order_num'],

            );
        }

        return array(
            'data' => $contractData,
            'pageData' => $paging->getHtml(),
        );
    }

    //添加 修改合同
    public function addContractAction(){

        $cid = (int)$this->getQuery('cid',null);
        $type = $this->getQuery('type');

        $marketer = $this->_getPlayAdminTable()->fetchAll(array('group' => 5, 'admin_city' => $_COOKIE['city']));

        if ($type == 'one') {
            $vm = new ViewModel(array(
                'marketer' => $marketer,
            ));
            $vm->setTemplate('admin/contract/add-baosell-contract.phtml');

            return $vm;
        }

        if ($type == 'two') {
            $vm = new ViewModel(array(
                'marketer' => $marketer,
            ));
            $vm->setTemplate('admin/contract/add-daisell-contract.phtml');
            return $vm;
        }

        if ($type == 'three') {
            $vm = new ViewModel(array(
                'marketer' => $marketer,
            ));
            $vm->setTemplate('admin/contract/add-zisell-contract.phtml');
            return $vm;
        }

        if (!$cid) {
            return $this->_Goto('非法操作');
        }

        $contractData = $this->_getPlayContractsTable()->get(array('id' => $cid));
        $organizerData = $this->_getPlayOrganizerTable()->get(array('id' => $contractData->mid));
        $organizerAccount = $this->_getPlayOrganizerAccountTable()->get(array('organizer_id' => $contractData->mid));

        return array(
            'organizerData'=> $organizerData,
            'contractData' => $contractData,
            'marketer' => $marketer,
            'organizerAccount' => $organizerAccount,
        );

    }

    //保存合同数据
    public function saveAction(){

        $cid = (int)$this->getPost('cid');  //合同id
        $mid = (int)$this->getPost('mid');  //合作商家id
        $business_id = (int)($this->getPost('business_id')); //经办人 商务组id
        $start_time = strtotime($this->getPost('start_time')); //合同生效时间
        $end_time = strtotime($this->getPost('end_time')); //合同结束
        $information = trim($this->getPost('information')); //合同描述
        $contracts_type = $this->getPost('contracts_type'); //合同类别

        if (!$mid) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '合作商家'));
        }

        if (!$start_time || !$end_time || $start_time >= $end_time) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '合同生效时间不正确'));
        }

        if (!$business_id) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '经办人'));
        }

        if (!in_array($contracts_type, array(1, 2, 3))) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '合同类型'));
        }

        if (!$information) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '合同描述'));
        }

        $data = array();
        $data['business_id'] = $business_id;
        $data['start_time'] = $start_time;
        $data['end_time'] = $end_time;
        $data['information'] = $information;
        $data['contracts_type'] = $contracts_type;
        $data['mid'] = $mid;
        $organizerData = $this->_getPlayOrganizerTable()->get(array('id' => $mid));
        $businessData = $this->_getPlayAdminTable()->get(array('id' => $business_id));
        $data['organizer_name'] =$organizerData->name;
        $data['business_taker'] = $businessData->admin_name;

       /* // 合同里面合作商家的账号 应该是完整的
        $lip = $this->_getPlayOrganizerAccountTable()->get(array('organizer_id' => $mid));
        if (!$lip || !$lip->bank_card || !$lip->bank_name || !$lip->bank_address || !$lip->bank_user) {
            $this->jsonResponsePage(array('status' => 0, 'message' => '请先完善合作商家账户信息'));
        }*/

        if (!$cid) {
            $contract_no = $this->CreateContractSn(); //合同号
            $data['editor'] = $_COOKIE['user'];
            $data['editor_id'] = $_COOKIE['id'];
            $data['create_time'] = time();
            $data['contract_no'] = $contract_no;
            $data['city'] = $_COOKIE['city'];

            $jir = $this->_getPlayContractsTable()->insert($data);

            if (!$jir) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '失败'));
            }

            $cid = $this->_getPlayContractsTable()->getlastInsertValue();

            $Contract = new Contract();

            $Contract->addLog($cid, 1, '创建合同');
        } else {

            $contractData = $this->_getPlayContractsTable()->get(array('id' => $cid));
            if (!$contractData) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
            }

            if ($contractData->check_status != 0) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '该合同状态下不允许修改'));
            }

            //修改了合作商家
            if ($contractData->mid != $mid) {
                //反审核 及 已经购买了商品的不能修改
                $back_check = $this->_getPlayContractActionTable()->fetchLimit(0, 1, array(), array('contract_id' => $cid, 'contract_status' => 4), array('action_id' => 'DESC'))->current();
                if ($back_check) {
                    return $this->jsonResponsePage(array('status' => 0, 'message' => '反审核了不允许修改商家'));
                }

                $tip = $this->query("SELECT
	play_order_info.order_sn
FROM
	play_organizer_game
INNER JOIN play_order_info ON play_order_info.coupon_id = play_organizer_game.id
WHERE
	play_organizer_game.contract_id = {$cid}
AND play_order_info.order_type = 2
AND play_order_info.pay_status >= 2
AND play_order_info.order_status = 1")->count();

                if ($tip) {
                    return $this->jsonResponsePage(array('status' => 0, 'message' => '已经售卖过商品的不允许修改'));
                }

                //未提交审核 未售卖，修改合同商家时，要判断商品里面有没有选择验证商家，如果选择了验证商家 则不让修改合同商家
                $lip = $this->query("SELECT
	play_game_info.id
FROM
	play_game_info
INNER JOIN play_organizer_game ON play_game_info.gid = play_organizer_game.id
INNER JOIN play_contracts ON play_contracts.id = play_organizer_game.contract_id
WHERE
	play_contracts.id = {$cid}
AND play_game_info.status = 1")->count();

                if ($lip) {
                    return $this->jsonResponsePage(array('status' => 0, 'message' => '选择了验证商家 不让修改合同商家'));
                }

            }

            $flag = $this->_getPlayContractsTable()->update($data, array('id' => $cid));

            if (!$flag) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '失败'));
            }
            if ($flag && $contractData->mid != $mid) {
                $this->_getPlayOrganizerGameTable()->update(array('organizer_id' => $mid), array('contract_id' => $cid));
                //修改库存
                $this->_getPlayInventoryTable()->update(array('organizer_id' => $mid), array('contract_id' => $cid));

            }
        }

        return $this->jsonResponsePage(array('status' => 1, 'message' => '保存成功', 'fid' => $cid));

    }

    //商品管理
    public function contractGoodAction() {
        $cid = (int)$this->getQuery('cid'); //合同id
        $contractData = $this->_getPlayContractsTable()->get(array('id' => $cid));

        if (!$contractData) {
            return $this->_Goto('非法操作');
        }

        //如果没有添加商品 调到添加商品页面
        $goodData = $this->_getPlayOrganizerGameTable()->get(array('contract_id' => $cid));

        if (!$goodData) {
            $vm = new ViewModel(
                array(
                    'contractData' => $contractData,
                )
            );
            $vm->setTemplate('admin/contract/good.phtml');
            return $vm;
        }

        //判断合同的类型 跳转不同的页面
        if (!in_array($contractData->contracts_type, array(1, 3))) {
            return $this->_Goto('合同类型不存在');
        }

        //包销合同
        if ($contractData->contracts_type == 1) {


            $inventoryData = $this->_getPlayInventoryTable()->fetchAll(array('contract_id' => $cid, 'inventory_status > ?' => 0));

            $contractLinkData = array();
            foreach ($inventoryData as $inventory) {
                $contractLinkData[$inventory->id]['id'] = $inventory->id;
                $contractLinkData[$inventory->id]['good_id'] = $inventory->good_id;
                $contractLinkData[$inventory->id]['good_name'] = $inventory->good_name;
                $contractLinkData[$inventory->id]['account_money'] = $inventory->account_money;
                $contractLinkData[$inventory->id]['purchase_number'] = $inventory->purchase_number;
                $contractLinkData[$inventory->id]['pre_money'] = $inventory->pre_money;
                $contractLinkData[$inventory->id]['inventory_address'] = $inventory->inventory_address;
                $contractLinkData[$inventory->id]['inventory_status'] = $inventory->inventory_status;
                $priceData = $this->_getPlayContractLinkPriceTable()->fetchAll(array('status > ?' => 0, 'inventory_id' => $inventory->id, 'good_id' => $inventory->good_id));
                if ($priceData->count()) {
                    foreach ($priceData as $tr) {
                        $contractLinkData[$inventory->id]['price'][] = array(
                            'money' => $tr->money,
                            'price' => $tr->price,
                            'id' => $tr->id,
                            'status' => $tr->status,
                        );
                    }
                }
            }

            $vm = new ViewModel(
                array(
                    'contractData' => $contractData,
                    'goodData' => $goodData,
                    'contractLinkData' => $contractLinkData
                )
            );
            return $vm;
        }

        //代销销合同
        if ($contractData->contracts_type == 3) {

            $contractLinkData = array();
            $contractAddData = $this->_getPlayOrganizerGameTable()->fetchLimit(0, 100, array(), array('contract_id' => $cid, 'status >= ?' => 0));

            foreach ($contractAddData as $ad) {
                $contractLinkData[$ad->id]['good_name'] = $ad->title;
                $contractLinkData[$ad->id]['good_id'] = $ad->id;
                $contractLinkData[$ad->id]['account_type'] = $ad->account_type;
                $contractLinkData[$ad->id]['account_organizer'] = $ad->account_organizer;
                $priceData = $this->_getPlayContractLinkPriceTable()->fetchAll(array('status > ?' => 0, 'good_id' => $ad->id));
                if ($priceData->count()) {
                    foreach ($priceData as $tr) {
                        $contractLinkData[$ad->id]['price'][] = array(
                            'account_money' => $tr->account_money,
                            'money' => $tr->money,
                            'price' => $tr->price,
                            'pre_money' => $tr->pre_money,
                            'id' => $tr->id,
                            'status' => $tr->status,
                        );
                    }
                }
            }
            $vm = new ViewModel(
                array(
                    'contractData' => $contractData,
                    'contractLinkData'=> $contractLinkData,
                )
            );

            $vm->setTemplate('admin/contract/contract-daisell-good.phtml');
            return $vm;
        }

    }

    //添加库存
    public function inventoryAction()
    {
        $contractId = (int)$this->getQuery('cid', 0);
        $goodId = (int)$this->getQuery('gid', 0);

        $contractData = $this->_getPlayContractsTable()->get(array('id' => $contractId));
        $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $goodId));

        if (!$contractData || !$goodData) {
            return $this->_Goto('非法操作');
        }

        $vm = new ViewModel(
            array(
                'contractData' => $contractData,
                'goodData' => $goodData
            )
        );
        return $vm;
    }

    //保存库存
    public function saveInventoryAction()
    {

        $goodName = $this->getPost('good_name', '');
        $goodId = (int)$this->getPost('good_id', 0);
        $contractId = (int)$this->getPost('contract_id', 0);
        $accountMoney = $this->getPost('account_money', 0);
        $preMoney = $this->getPost('pre_money', 0);
        $inventoryAddress = $this->getPost('inventory_address', 0);
        $purchaseNumber = $this->getPost('purchase_number', 0);

        if (!$goodName || !$goodId) {
            return $this->_Goto('非法操作2');
        }

        $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $goodId, 'contract_id' => $contractId));

        if (!$goodData) {
            return $this->_Goto('非法操作1');
        }

        if (!in_array($inventoryAddress, array(1, 2))) {
            return $this->_Goto('库存地点');
        }

        if (!$purchaseNumber) {
            return $this->_Goto('采购数量');
        }

        $contractData = $this->_getPlayContractsTable()->get(array('id' => $contractId));

        $data = array(
            'good_id' => $goodId,
            'good_name' => $goodName,
            'contract_id' => $contractId,
            'account_money' => $accountMoney,
            'pre_money' => $preMoney,
            'inventory_address' => $inventoryAddress,
            'purchase_number' => $purchaseNumber,
            'organizer_id' => $goodData->organizer_id,
            'contract_number' => $contractData->contract_no
        );

        $result = $this->_getPlayInventoryTable()->insert($data);

        if (!$result) {
            return $this->_Goto('失败');
        } else {
            $inventory_id = $this->_getPlayInventoryTable()->getlastInsertValue();
            $organizerAccount = new OrganizerAccount();
            $organizerAccount->inventory($inventory_id, 1, 0, $purchaseNumber, 0, 0, '新增库存');
            return $this->_Goto('成功', '/wftadlogin/contract/contractGood?cid='. $contractId);
        }

    }

    //删除库存
    public function deleteInventoryAction()
    {
        $id = (int)$this->getQuery('id', 0);

        $inventoryData = $this->_getPlayInventoryTable()->get(array('id' => $id));

        if (!$inventoryData) {
            return $this->_Goto('非法操作');
        }

        $contractData = $this->_getPlayContractsTable()->get(array('id' => $inventoryData->contract_id));

        if ($inventoryData->pre_money > 0 && $inventoryData->inventory_status > 1) {
            return $this->_Goto('有预付金 且提交审核过的不能删除');
        }

        if ($contractData->check_status > 0) {
            return $this->_Goto('该合同状态下不能执行该操作');
        }

        //判断库存售卖出去的不能删除
        $orderData = $this->_getPlayOrderInfoTable()->fetchAll(array('coupon_id' => $inventoryData->good_id, 'order_status' => 1));
        $contractPrice = $this->_getPlayContractLinkPriceTable()->fetchAll(array('inventory_id' => $id, 'status > ?' => 0));
        $price_id = array();

        foreach ($contractPrice as $price) {
            if (!in_array($price['id'], $price_id)) {
                $price_id[] = $price['id'];
            }
        }

        if ($orderData->count() && $contractPrice->count()) {
            $bid = array();
            foreach ($orderData as $order) {
                if (!in_array($order['bid'], $bid)) {
                    $bid[] = $order['bid'];
                }
            }
            $gameInfo = $this->_getPlayGameInfoTable()->fetchAll(array('gid' => $inventoryData->good_id, new In('id', $bid), new In('contract_price_id', $price_id)));

            if ($gameInfo->count()) {
                return $this->_Goto('该库存已售卖 不能删除');
            }

        }

        //如果有套系 则不能删除
        if ($contractPrice->count()) {
            $priceData = $this->_getPlayGamePriceTable()->fetchAll(array('gid' => $inventoryData->good_id, new In('contract_link_id', $price_id)));

            if ($priceData->count()) {
                return $this->_Goto('请先删除套系');
            }
        }

        $this->_getPlayContractLinkPriceTable()->update(array('status' => 0), array('inventory_id' => $id));
        $s1 = $this->_getPlayInventoryTable()->update(array('inventory_status' => 0), array('id' => $id));

        return $this->_Goto($s1 ? '成功' : '失败');

    }

    //添加包销合同的价格方案
    public function inventoryPriceAction()
    {
        $invent_id = (int)$this->getQuery('invent_id', 0);
        $good_id = (int)$this->getQuery('gid', 0);
        $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $good_id));
        $inventoryData = $this->_getPlayInventoryTable()->get(array('id' => $invent_id, 'inventory_status > ?' => 0));

        if (!$goodData || !$inventoryData)
        {
            return $this->_Goto('非法操作');
        }

        if ($goodData->contract_id != $inventoryData->contract_id)
        {
            return $this->_Goto('非法操作');
        }

        $vm = new viewModel(
            array(
                'goodData' => $goodData,
                'inventoryData' => $inventoryData,
            )
        );
        return $vm;

    }

    //保存包销合同的价格方案
    public function saveInventoryPriceAction()
    {
        $invent_id = (int)$this->getPost('inventory_id', 0);
        $good_id = (int)$this->getPost('good_id', 0);
        $money = $this->getPost('money', 0);
        $price = $this->getPost('price', 0);
        $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $good_id));
        $inventoryData = $this->_getPlayInventoryTable()->get(array('id' => $invent_id, 'inventory_status > ?' => 0));
        $contractData = M::getPlayContractsTable()->get(array('id' => $goodData->contract_id));

        if (!$goodData || !$inventoryData || $goodData->contract_id != $inventoryData->contract_id || !$contractData) {
            return $this->_Goto('非法操作');
        }

        if (!$money) {
            return $this->_Goto('原价');
        }

        if (!$price) {
            return $this->_Goto('售价');
        }

        if ($price > $money) {
            return $this->_Goto('售价不能大于原价');
        }

        $status = $this->_getPlayContractLinkPriceTable()->insert(array(
                'account_money' => $inventoryData->account_money,
                'price' => $price,
                'money' => $money,
                'good_id' => $goodData->id,
                'contract_id' => $goodData->contract_id,
                'inventory_id' => $invent_id,
            )
        );

        if (!$status) {
            return $this->_Goto('失败');
        }

        //同时更新合同的状态为未审批状态
        if ($contractData->check_status > 0) {
            $n1 = M::getPlayContractsTable()->update(array('check_status' => 0), array('id' => $goodData->contract_id));
            if ($n1) {
                $Contract = new Contract();
                $Contract->addLog($goodData->contract_id, 7, '提交新的合同套系');
            } else {
                Logger::WriteErrorLog('改变合同状态出错 合同id '. $goodData->contract_id. "\r\n");
                return $this->_Goto('出现异常 请联系技术');
            }
        }

        return $this->_Goto('成功', '/wftadlogin/contract/contractGood?cid='. $goodData->contract_id);

    }

    //删除包销合同的价格方案
    public function deleteInventoryPriceAction()
    {
        $id = (int)$this->getQuery('id', 0);

        $contractLinkPrice = $this->_getPlayContractLinkPriceTable()->get(array('id' => $id));

        if (!$contractLinkPrice) {
            return $this->_Goto('非法操作');
        }

        $contractData = $this->_getPlayContractsTable()->get(array('id' => $contractLinkPrice->contract_id));

        if ($contractData->check_status > 0) {
            return $this->_Goto('该合同状态下不能执行该操作');
        }


        //判断库存售卖出去的不能删除
        $orderData = $this->_getPlayOrderInfoTable()->fetchAll(array('coupon_id' => $contractLinkPrice->good_id, 'order_status' => 1));

        if ($orderData->count()) {
            $bid = array();
            foreach ($orderData as $order) {
                if (!in_array($order['bid'], $bid)) {
                    $bid[] = $order['bid'];
                }
            }

            $gameInfo = $this->_getPlayGameInfoTable()->fetchAll(array('gid' => $contractLinkPrice->good_id, new In('id', $bid), 'contract_price_id' => $id));

            if ($gameInfo->count()) {
                return $this->_Goto('该价格套系已售卖 不能删除');
            }

        }


        $res = $this->_getPlayContractLinkPriceTable()->update(array('status' => 0), array('id' => $id));
        return $this->_Goto($res ? '成功' : '失败');

    }


    //添加商品
    public function goodAction() {

        $cid = (int)$this->getQuery('cid'); //合同id
        $contractData = $this->_getPlayContractsTable()->get(array('id' => $cid));

        if (!$contractData) {
            return $this->_Goto('非法操作');
        }

        $vm = new viewModel(
            array(
                'contractData' => $contractData,
            )
        );
        return $vm;
    }

    //保存合同商品
    public function saveGoodAction() { //保存合同商品

        $good_name = trim($this->getPost('good_name'));
        $account_type = (int)$this->getPost('account_type');
        $cid = (int)$this->getPost('cid');
        $account_organizer = (int)$this->getPost('account_organizer', 0);

        $contractData = $this->_getPlayContractsTable()->get(array('id' => $cid));

        if (!$contractData) {
            return $this->_Goto('非法操作');
        }

        if (!in_array($account_organizer, array(1, 2))) {
            return $this->_Goto('非法操作');
        }

        if (!$good_name || mb_strlen($good_name, "UTF8") > 30) {
            return $this->_Goto('商品名称 或者商品名称太长');
        }

        if (!in_array($account_type, array(1, 2, 3))) {
            return $this->_Goto('非法操作');
        }

        if ($contractData->contracts_type == 1) {
            if ($account_organizer != 1) {
                return $this->_Goto('非法操作1');
            }
        } else {

            if ($account_type == 2 && $account_organizer != 1) {
                return $this->_Goto('代销 商品 需预付金的情况下 结算只能是合同商家');
            }

        }

        //创建商品
        $good_id = $this->addGood($cid, $contractData->mid, $good_name, $account_type, $account_organizer);

        if (!$good_id) {
            return $this->_Goto('添加商品失败');
        }

        return $this->_Goto('新建商品成功', '/wftadlogin/contract/contractGood?cid='. $cid);

    }

    //添加合同套系
    public function priceAction() {

        $gid = (int)$this->getQuery('gid'); //商品id
        $goodData = $this->_getPlayorganizerGameTable()->get(array('id' => $gid));

        if (!$goodData) {
            return $this->_Goto('非法操作');
        }

        $vm = new viewModel(
            array(
                'goodData' => $goodData,
            )
        );
        return $vm;
    }

    //保存合同商品套系
    public function savePriceAction() {

        $money = $this->getPost('money', 0);
        $account_money = $this->getPost('account_money', 0);
        $price = $this->getPost('price', 0);
        $preMoney = $this->getPost('pre_money', 0);
        $good_id = (int)$this->getPost('good_id');

        $goodData = $this->_getPlayorganizerGameTable()->get(array('id' => $good_id));
        if (!$goodData) {
            return $this->_Goto('非法操作');
        }

        $contractData = M::getPlayContractsTable()->get(array('id' => $goodData->contract_id));

        if (!$contractData) {
            return $this->_Goto('非法操作');
        }

        if ($account_money < 0) {
            return $this->_Goto('结算价格不对');
        }

        if ($price < 0 || $money < 0 || $money < $price) {
            return $this->_Goto('售价和原价不对');
        }

        $status = $this->_getPlayContractLinkPriceTable()->insert(array(
                'account_money' => $account_money,
                'pre_money' => $preMoney,
                'price' => $price,
                'money' => $money,
                'good_id' => $goodData->id,
                'contract_id' => $goodData->contract_id
            )
        );

        if (!$status) {
            return $this->_Goto('失败');
        }

        //同时更新合同的状态为未审批状态
        if ($contractData->check_status > 0) {
            $n1 = M::getPlayContractsTable()->update(array('check_status' => 0), array('id' => $goodData->contract_id));
            if ($n1) {
                $Contract = new Contract();
                $Contract->addLog($goodData->contract_id, 7, '提交新的合同套系');
            } else {
                Logger::WriteErrorLog('改变合同状态出错 合同id '. $goodData->contract_id. "\r\n");
                return $this->_Goto('出现异常 请联系技术');
            }
        }

        return $this->_Goto('成功', '/wftadlogin/contract/contractGood?cid='. $goodData->contract_id);

    }

    //获取商家
    public function getOrganizerAction() {

        $adapter = $this->_getAdapter();
        $k = $this->getQuery('k');
        if ($k) {
            $sql = "SELECT
	play_organizer.id,
	play_organizer.name,
	play_organizer_account.bank_name,
	play_organizer_account.bank_user,
	play_organizer_account.bank_address,
	play_organizer_account.bank_card
FROM
	play_organizer
LEFT JOIN play_organizer_account ON play_organizer_account.organizer_id = play_organizer.id
WHERE
	play_organizer.city = ?
AND play_organizer.status > ?
AND play_organizer.name LIKE ?";

            $data = $adapter->query($sql, array($_COOKIE['city'], 0, '%'.$k.'%'));

            $res = array();
            if ($data->count()) {
                foreach ($data as $val) {
                    $res[] = array(
                        'sid' => $val['id'],
                        'name' => $val['name'],
                        'account' => $val['bank_name'] ? '开户人: '. $val['bank_user']. '  开户行：'. $val['bank_name']. '  开户支行: '. $val['bank_address']. '  银行卡号：'. $val['bank_card'] : '',
                    );
                }
            }
            return $this->jsonResponsePage(array('status' => 1, 'data' => $res));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'data' => array()));
        }
    }

    /**
     * 创建合同号
     */
    private function CreateContractSn(){
        /* 选择一个随机的方案 */
        mt_srand((double) microtime() * 1000000);
        $ContractSn = date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', 0);

        $data = $this->_getPlayContractsTable()->get(array('contract_no'=>$ContractSn));

        if($data){
            $this->CreateContractSn();
        }

        return $ContractSn;
    }

    /**
     * 添加商品
     * @param $organizer_id //活动组织者id
     * @param $good_name //商品名称
     * @param $contract_id //合同id
     * @param $account_type //结算类型
     * @param $account_organizer //结算商家
     * @return int
     */
    private function addGood($contract_id, $organizer_id, $good_name, $account_type, $account_organizer){

        $status = $this->_getPlayOrganizerGameTable()->insert(array(
            'organizer_id' => $organizer_id,
            'title' => $good_name,
            'contract_id' => $contract_id,
            'is_together' => 1,
            'status' => 0,
            'city' => $_COOKIE['city'],
            'dateline' => time(),
            'limit_num' => 1,
            'start_time' => time(),
            'end_time' => strtotime("+10 year"),
            'refund_time' => time() - 3*24*3600,
            'account_type' => $account_type,
            'account_organizer' => $account_organizer,
            'need_use_time' => 0,
        ));

        if (!$status) {
            return false;
        }

        $goods_id =$this->_getPlayOrganizerGameTable()->getlastInsertValue();

        //同时创建默认商品积分
        $city = $this->getAdminCity();
        $timer = time();
        $sql = "INSERT INTO play_welfare_integral (
	id,
	object_id,
	object_type,
	welfare_type,
	`double`,
	limit_num,
	total_num,
	status,
	dateline,
	editor_id,
	editor,
	get_num,
	city
)
VALUES
	(NULL, $goods_id, 2, 3, 1, 1, 1000, 1, {$timer}, {$_COOKIE['id']}, '{$_COOKIE['user']}', 0, '{$city}'),
	(NULL, $goods_id, 2, 4, 1, 1, 1000, 1, {$timer}, {$_COOKIE['id']}, '{$_COOKIE['user']}', 0, '{$city}')";

        $this->query($sql);

        return $goods_id;
    }

    //提交审批
    public function approvalAction(){
        //合同id
        $contract_id = (int)$this->getQuery('cid');

        if(!$contract_id){
            return $this->_Goto('参数错误');
        }
        $contractData = $this->_getPlayContractsTable()->get(array('id' => $contract_id));

        if(!$contractData){
            return $this->_Goto('未查询到合同相关数据');
        }

        //查看商家账号是否存在
        $lip = $this->_getPlayOrganizerAccountTable()->get(array('organizer_id' => $contractData->mid));
        if (!$lip || !$lip->bank_card || !$lip->bank_name || !$lip->bank_address || !$lip->bank_user) {
            return $this->_Goto('请先完善合作商家账户信息');
        }

        //查看是否添加了合同关联的价格套系
        $gip = $this->_getPlayContractLinkPriceTable()->get(array('status' => 1, 'contract_id' => $contract_id));

        if (!$gip){
            return $this->_Goto('合同关联的商品不完善, 请去完善商品');
        }

        $status = $this->_getPlayContractsTable()->update(array('check_status' => 1), array('id' => $contract_id));

        if($status){
            $Contract = new Contract();
            $Contract->addLog($contract_id, 2, '提交审批');
            //todo 更改库存 及 关联价格的 状态
            $this->_getPlayContractLinkPriceTable()->update(array('status' => 2), array('status' => 1, 'contract_id' => $contract_id));
            if ($contractData->contracts_type == 1) {
                $this->_getPlayInventoryTable()->update(array('inventory_status' => 2), array('inventory_status' => 1, 'contract_id' => $contract_id));
            }
            return $this->_Goto("提交审批成功","javascript:location.href = document.referrer");
        }else{
            return $this->_Goto('提交审批失败');
        }

    }

    //导出合同数据
    public function exportAction(){

        $infoObj = $this->indexAction();
        $data = $infoObj['data'];

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
                $infoObj['contract_type'][$v['contracts_type']],
                $v['pre_money'],
                $infoObj['contractStatus'][$v['contracts_status']],
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


    //删除商品
    public function deleteGoodAction() {
        $id = (int)$this->getQuery('id');

        $status = $this->_getPlayOrganizerGameTable()->update(array('status' => -1), array('id' => $id));

        return $this->_Goto($status ? '成功' : '失败');
    }

    //删除商品套系
    public function deletePriceAction() {

        $id = (int)$this->getQuery('id');

        $contractLinkPrice = $this->_getPlayContractLinkPriceTable()->get(array('id' => $id));

        if (!$contractLinkPrice) {
            return $this->_Goto('非法操作');
        }

        $contractData = $this->_getPlayContractsTable()->get(array('id' => $contractLinkPrice->contract_id));

        if ($contractData->check_status > 0) {
            return $this->_Goto('该合同状态下不能执行该操作');
        }

        //如果没有对应的套系了
        $flag = $this->_getPlayGamePriceTable()->get(array('contract_link_id' => $id));

        if ($flag) {
            return $this->_Goto('请先删除掉套系');
        }

        $zp = M::getPlayGameInfoTable()->get(array('contract_price_id' => $id, 'status' => 1));

        if ($zp) {
            return $this->_Goto('请先删除掉商品套系');
        }

        if ($contractLinkPrice->check_pre_status > 0) {
            return $this->_Goto('预付金状态');
        }

        if ($contractLinkPrice->status > 1) {
            return $this->_Goto('提交审核过的不能删除');
        }

        //判断售卖出去的不能删除
        $orderData = $this->_getPlayOrderInfoTable()->fetchAll(array('coupon_id' => $contractLinkPrice->good_id, 'order_type' => 2, 'order_status' => 1));

        if ($orderData->count()) {
            $bid = array();
            foreach ($orderData as $order) {
                if (!in_array($order['bid'], $bid)) {
                    $bid[] = $order['bid'];
                }
            }

            $gameInfo = $this->_getPlayGameInfoTable()->fetchAll(array('gid' => $contractLinkPrice->good_id, new In('id', $bid), 'contract_price_id' => $id));

            if ($gameInfo->count()) {
                return $this->_Goto('该价格套系已售卖 不能删除');
            }

        }

        $status = $this->_getPlayContractLinkPriceTable()->update(array('status' => 0), array('id' => $id));

        return $this->_Goto($status ? '成功' : '失败');
    }

    //修改库存
    public function updateInventoryAction()
    {
        $id = (int)$this->getQuery('id', 0);

        $inventoryData = $this->_getPlayInventoryTable()->get(array('id' => $id, 'inventory_status > ?' => 0));

        if (!$inventoryData) {
            return $this->_Goto('非法操作');
        }

        $contractData = $this->_getPlayContractsTable()->get(array('id' => $inventoryData->contract_id));

        if (!$contractData || $contractData->check_status > 0) {
            return $this->_Goto('非法操作');
        }

        //判断是否售卖
        $buy = 0;
        $orderData = $this->_getPlayOrderInfoTable()->fetchAll(array('coupon_id' => $inventoryData->good_id, 'order_status' => 1));
        $contractPrice = $this->_getPlayContractLinkPriceTable()->fetchAll(array('inventory_id' => $id, 'status > ?' => 0));
        if ($orderData->count() && $contractPrice->count()) {
            $bid = array();
            $price_id = array();
            foreach ($orderData as $order) {
                if (!in_array($order['bid'], $bid)) {
                    $bid[] = $order['bid'];
                }
            }

            foreach ($contractPrice as $price) {
                if (!in_array($price['id'], $price_id)) {
                    $price_id[] = $price['id'];
                }
            }

            $gameInfo = $this->_getPlayGameInfoTable()->fetchAll(array('gid' => $inventoryData->good_id, new In('id', $bid), new In('contract_price_id', $price_id)));

            if ($gameInfo->count()) {
                $buy = 1;
            }
        }

        $vm = new ViewModel(
            array(
                'buy' => $buy,
                'inventoryData' => $inventoryData
            )
        );

        return $vm;

    }

    public function saveInventoryLastAction()
    {
        $id = (int)$this->getPost('id', 0);

        $inventoryData = $this->_getPlayInventoryTable()->get(array('id' => $id, 'inventory_status > ?' => 0));

        if (!$inventoryData) {
            return $this->_Goto('非法操作');
        }

        $contractData = $this->_getPlayContractsTable()->get(array('id' => $inventoryData->contract_id));

        if (!$contractData || $contractData->check_status > 0) {
            return $this->_Goto('非法操作1');
        }

        if ($inventoryData->inventory_status > 1) {
            return $this->_Goto('非法操作2');
        }

       /* //判断是否售卖
        $orderData = $this->_getPlayOrderInfoTable()->fetchAll(array('coupon_id' => $inventoryData->good_id, 'order_status' => 1));
        $contractPrice = $this->_getPlayContractLinkPriceTable()->fetchAll(array('inventory_id' => $id, 'status > ?' => 0));
        if ($orderData->count() && $contractPrice->count()) {
            $bid = array();
            $price_id = array();
            foreach ($orderData as $order) {
                if (!in_array($order['bid'], $bid)) {
                    $bid[] = $order['bid'];
                }
            }

            foreach ($contractPrice as $price) {
                if (!in_array($price['id'], $price_id)) {
                    $price_id[] = $price['id'];
                }
            }

            $gameInfo = $this->_getPlayGameInfoTable()->fetchAll(array('gid' => $inventoryData->good_id, new In('id', $bid), new In('contract_price_id', $price_id)));

            if ($gameInfo->count()) {
                 return $this->_Goto('已经售卖 不能修改');
            }
        }*/

        $inventory_address = (int)$this->getPost('inventory_address', 0);
        $purchase_number = (int)$this->getPost('purchase_number', 0);
        $account_money =  $this->getPost('account_money', 0);
        $buy =  $this->getPost('buy', 0);
        $pre_money =  $this->getPost('pre_money', 0);

        if (!in_array($inventory_address, array(1, 2))) {
             return $this->_Goto('非法操作');
        }

        if ($purchase_number < 1) {
            return $this->_Goto('采购数量不能小于1');
        }

        if ($account_money < 0 && !$buy) {
            return $this->_Goto('采购价不能小于0');
        }

        $contractLinkData = $this->_getPlayContractLinkPriceTable()->fetchAll(array('inventory_id' => $id));

        $link_id = '';
        foreach ($contractLinkData as $link) {
            $link_id = $link_id. ','. $link['id'];
        }

        $link_id = trim($link_id, ',');

        if ($purchase_number != $inventoryData->purchase_number) {

            //判断是否小于  原先限制数量
            $giveNum = 0;

            if ($link_id) {
                $goodInfoData = $this->_getPlayGameInfoTable()->fetchAll(array('contract_price_id IN ('. $link_id. ')'));
                foreach ($goodInfoData as $info) {
                    if ($info->status > 0) {
                        $giveNum = $giveNum + $info->total_num;
                    } else {
                        $giveNum = $giveNum + $info->buy;
                    }
                }
            }

            if ($purchase_number < $giveNum) {
                return $this->_Goto('数量不足 不让修改');
            }

        }

        if ($inventoryData->inventory_address != $inventory_address) {//更改库存地点
            $this->_getPlayInventoryTable()->update(array('inventory_address' => $inventory_address), array('id' => $id));
        }

        if ($purchase_number != $inventoryData->purchase_number) {//更改采购数量
            $organizerAccount = new OrganizerAccount();
            $s1 = $organizerAccount->inventory($id, 1, 0, $purchase_number - $inventoryData->purchase_number, 0, 0, '修改库存');

            if (!$s1) {
                return $this->_Goto('更改采购数量失败');
            }
            $this->_getPlayInventoryTable()->update(array('purchase_number' => $purchase_number), array('id' => $id));
        }

        if ($account_money != $inventoryData->account_money && $buy) {

            $this->_getPlayInventoryTable()->update(array('account_money' => $account_money), array('id' => $id));
            $this->_getPlayContractLinkPriceTable()->update(array('account_money' => $account_money), array('inventory_id' => $id));

            if ($link_id) {
                $this->_getPlayGamePriceTable()->update(array('account_money' => $account_money), array('contract_link_id IN ('. $link_id. ')'));
                $this->_getPlayGameInfoTable()->update(array('account_money' => $account_money), array('contract_price_id IN ('. $link_id. ')'));
            }
        }

        if ($pre_money != $inventoryData->pre_money) {
            $this->_getPlayInventoryTable()->update(array('pre_money' => $pre_money), array('id' => $id));
        }

        return $this->_Goto('成功', '/wftadlogin/contract/contractGood?cid='. $inventoryData->contract_id);

    }

    public function updatePriceAction()
    {
        $id = (int)$this->getQuery('id', 0);
        $contractPriceData = $this->_getPlayContractLinkPriceTable()->get(array('id' => $id, 'status > ?' => 0));

        if (!$contractPriceData) {
            return $this->_Goto('非法操作');
        }

        $contractData = $this->_getPlayContractsTable()->get(array('id' => $contractPriceData->contract_id));

        if (!$contractData || $contractData->check_status > 0) {
            return $this->_Goto('非法操作');
        }

        if ($contractPriceData->status > 1) {
            return $this->_Goto('该价格方案已经审核过, 不能修改');
        }

        $action_type = 0;
        //判断是否售卖
        $tip = $this->_getPlayGameInfoTable()->get(array('contract_price_id' => $id, 'buy > ?' => 0));

        $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $contractPriceData->good_id));
        if ($tip) {
            if ($contractData->contracts_type == 1) {
                return $this->_Goto('已经售卖 包销合同不能修改');
            }

            if ($contractData->contracts_type == 3 && $goodData->account_type == 3) {
                return $this->_Goto('已经售卖 代销合同无预付金 不能修改');
            }
            $action_type = 1;//代销 有预付金 已卖 //只让改预付金
        } else {

            if ($contractData->contracts_type == 1) {
                $action_type = 2;//包销  都让改  //原价 售价
            }

            if ($contractData->contracts_type == 3 && $goodData->account_type == 3) {
                $action_type = 3;//代销合同无预付金  都让改  //原价 售价 结算价
            }

            if ($contractData->contracts_type == 3 && $goodData->account_type == 2) {
                $action_type = 4;//代销合同有预付金  都让改  //原价 售价 结算价 预付金
            }
        }

        if (!in_array($action_type, array(1,  2, 3, 4))) {
            return $this->_Goto('非法操作');
        }

        //说明 未审未卖情况下 都可以修改 未审已卖的情况下 只让修改 代销 有预付金  的预付金
        $vm = new ViewModel(
            array(
                'action_type' => $action_type,
                'contractPriceData' => $contractPriceData
            )
        );
        return $vm;
    }

    public function saveUpdatePriceAction()
    {

        $action_type = (int)$this->getPost('action_type', 0);

        if (!in_array($action_type, array(1, 2, 3, 4)))
        {
            return $this->_Goto('非法操作');
        }

        $id = (int)$this->getPost('id', 0);
        $contractPriceData = $this->_getPlayContractLinkPriceTable()->get(array('id' => $id, 'status > ?' => 0));

        if (!$contractPriceData) {
            return $this->_Goto('非法操作1');
        }

        $contractData = $this->_getPlayContractsTable()->get(array('id' => $contractPriceData->contract_id));

        if (!$contractData || $contractData->check_status > 0) {
            return $this->_Goto('非法操作2');
        }

        if ($contractPriceData->status > 1 || $contractPriceData->check_pre_status > 0) {
            return $this->_Goto('非法操作3');
        }

        $tip = $this->_getPlayGameInfoTable()->get(array('contract_price_id' => $id, 'buy > ?' => 0));

        $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $contractPriceData->good_id));
        $do_type = 0;
        if ($tip) {
            if ($contractData->contracts_type == 1) {
                return $this->_Goto('非法操作4');
            }

            if ($contractData->contracts_type == 3 && $goodData->account_type == 3) {
                return $this->_Goto('非法操作5');
            }
            $do_type = 1;//代销 有预付金 已卖 //只让改预付金
        } else {

            if ($contractData->contracts_type == 1) {
                $do_type = 2;//包销  都让改  //原价 售价
            }

            if ($contractData->contracts_type == 3 && $goodData->account_type == 3) {
                $do_type = 3;//代销合同无预付金  都让改  //原价 售价 结算价
            }

            if ($contractData->contracts_type == 3 && $goodData->account_type == 2) {
                $do_type = 4;//代销合同有预付金  都让改  //原价 售价 结算价 预付金
            }
        }

        if ($do_type != $action_type) {
            return $this->_Goto('非法操作6');
        }

        //修改了原价更改商品相关信息
        $Good = new Good();

        if ($action_type == 1) {//代销 有预付金 已卖 //只让改预付金
            $pre_money = $this->getPost('pre_money', 0);
            $tip = $this->_getPlayContractLinkPriceTable()->update(array('pre_money' => $pre_money), array('id' => $id));
            if (!$tip) {
                return $this->_Goto('失败');
            }

            return $this->_Goto('成功', '/wftadlogin/contract/contractGood?cid='. $contractData->id);

        }

        if ($action_type == 2) {//包销  都让改  //原价 售价
            $price = $this->getPost('price', 0);
            $money = $this->getPost('money', 0);
            $tip = $this->_getPlayContractLinkPriceTable()->update(array('price' => $price, 'money' => $money), array('id' => $id));
            if (!$tip) {
                return $this->_Goto('失败');
            }

            //同时更新
            $this->_getPlayGamePriceTable()->update(array('price' => $price, 'money' => $money), array('contract_link_id' => $id));
            $this->_getPlayGameInfoTable()->update(array('price' => $price, 'money' => $money), array('contract_price_id' => $id));
            $Good->toRight($contractPriceData->good_id);
            return $this->_Goto('成功', '/wftadlogin/contract/contractGood?cid='. $contractData->id);
        }

        if ($action_type == 3) {//代销合同无预付金  都让改  //原价 售价 结算价
            $price = $this->getPost('price', 0);
            $money = $this->getPost('money', 0);
            $account_money = $this->getPost('account_money', 0);
            $tip = $this->_getPlayContractLinkPriceTable()->update(array('price' => $price, 'money' => $money, 'account_money' => $account_money), array('id' => $id));
            if (!$tip) {
                return $this->_Goto('失败');
            }

            //同时更新
            $this->_getPlayGamePriceTable()->update(array('price' => $price, 'money' => $money, 'account_money' => $account_money), array('contract_link_id' => $id));
            $this->_getPlayGameInfoTable()->update(array('price' => $price, 'money' => $money, 'account_money' => $account_money), array('contract_price_id' => $id));
            $Good->toRight($contractPriceData->good_id);
            return $this->_Goto('成功', '/wftadlogin/contract/contractGood?cid='. $contractData->id);

        }

        if ($action_type == 4) {//代销合同有预付金  都让改  //原价 售价 结算价 预付金
            $price = $this->getPost('price', 0);
            $money = $this->getPost('money', 0);
            $account_money = $this->getPost('account_money', 0);
            $pre_money = $this->getPost('pre_money', 0);
            $tip = $this->_getPlayContractLinkPriceTable()->update(array('pre_money' => $pre_money, 'price' => $price, 'money' => $money, 'account_money' => $account_money), array('id' => $id));
            if (!$tip) {
                return $this->_Goto('失败');
            }

            //同时更新
            $this->_getPlayGamePriceTable()->update(array('price' => $price, 'money' => $money, 'account_money' => $account_money), array('contract_link_id' => $id));
            $this->_getPlayGameInfoTable()->update(array('price' => $price, 'money' => $money, 'account_money' => $account_money), array('contract_price_id' => $id));
            $Good->toRight($contractPriceData->good_id);
            return $this->_Goto('成功', '/wftadlogin/contract/contractGood?cid='. $contractData->id);

        }

        return $this->_Goto('非法操作end');

    }

}

