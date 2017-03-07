<?php

namespace Admin\Controller;

use Deyi\JsonResponse;
use Deyi\Paginator;
use Deyi\OutPut;
use library\Fun\M;
use Zend\View\Model\ViewModel;

class InventoryController extends BasisController
{
    use JsonResponse;

    //库存列表
    public function indexAction()
    {
        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;
        $good_id = (int)$this->getQuery('good_id',null);
        $organizer_id = (int)$this->getQuery('organizer_id',null);
        $contract_num = (int)$this->getQuery('contract_id',null);
        $inventory_id = (int)$this->getQuery('inventory_num',null);
        $good_name = trim($this->getQuery('good_name',null));


        $where = "play_inventory.inventory_status > 0"; //去掉已经删除的
        /*$city = $this->chooseCity();
        if($city){
            $where .=  " AND play_contracts.city = '{$city}' ";
        }*/

        $order = "play_inventory.id DESC";


        if($good_id){
            $where .= " AND play_inventory.good_id = '{$good_id}'";
        }

        if($organizer_id){
            $where .= " AND play_inventory.organizer_id = '{$organizer_id}'";
        }

        if($contract_num){
            $where .= " AND play_inventory.contract_number = '{$contract_num}'";
        }

        if($inventory_id){
            $where .= " AND play_inventory.id = '{$inventory_id}'";
        }

        if($good_name){
            $where .= " AND play_inventory.good_name = '{$good_name}'";
        }

        $inventory_sql = "SELECT
play_inventory.*
FROM play_inventory WHERE $where";

        $inventoryData = $this->query($inventory_sql." ORDER BY {$order} LIMIT {$start}, {$pageSum}");

        $count = M::getAdapter()->query("SELECT count(*) as c FROM play_inventory WHERE $where",array())->current()->c;

        //创建分页
        $url = '/wftadlogin/inventory';

        $paging = new Paginator($page, $count, $pageSum, $url);

        $needData = array();
        foreach ($inventoryData as $inventory) {
            $needData[] = array(
                'good_id' => $inventory['good_id'],
                'good_name' => $inventory['good_name'],
                'id' => $inventory['id'],
                'purchase_number' => $inventory['purchase_number'],
                'contract_number' => $inventory['contract_number'],
                'inventory_address' => $inventory['inventory_address'],
                'pre_money' => $inventory['pre_money'],
                'organizer_id' => $inventory['organizer_id'],
                'account_money' => $inventory['account_money'],
                'inventory_number' => $this->getConsumedNum($inventory['id']),
            );
        }


        return array(
            'data' => $needData,
            'pageData' => $paging->getHtml(),
        );
    }

    //库存明细表
    public function listAction()
    {
        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;
        $start_time = trim($this->getQuery('start_time',null));
        $end_time = trim($this->getQuery('end_time',null));
        $order_sn = trim($this->getQuery('order_sn', null));

        $id = (int)$this->getQuery('id', 0);

        $inventoryData = $this->_getPlayInventoryTable()->get(array('id' => $id));

        if (!$id || !$inventoryData) {
            $this->_Goto('非法操作');
        }

        $where = "play_inventory_log.inventory_id = {$id}";

        $order = "play_inventory_log.id DESC";

        if ($start_time && $end_time && strtotime($start_time) > strtotime($end_time)) {
            $this->_Goto('查询时间有问题');
        }


        //购买时间
        if ($start_time) {
            $start_time = strtotime($start_time);
            $where = $where. " AND play_inventory_log.dateline > ".$start_time;
        }

        if ($end_time) {
            $end_time = strtotime($end_time) + 86400;
            $where = $where. " AND play_inventory_log.dateline < ".$end_time;
        }

        if ($order_sn) {
            $order_sn = (int)preg_replace('|[a-zA-Z/]+|','',$order_sn);
            $where = $where. " AND play_inventory_log.object_id = ". $order_sn;
        }

        $inventory_log_sql = "SELECT
play_inventory_log.*
FROM play_inventory_log WHERE $where";

        $inventoryLogData = $this->query($inventory_log_sql." ORDER BY {$order} LIMIT {$start}, {$pageSum}");

        $count =  $this->query("SELECT play_inventory_log.id FROM play_inventory_log WHERE $where")->count();

        //创建分页
        $url = '/wftadlogin/inventory/list';

        $paging = new Paginator($page, $count, $pageSum, $url);


        return array(
            'inventory' => $inventoryData,
            'data' => $inventoryLogData,
            'pageData' => $paging->getHtml(),
        );
    }

    /**
     * 获取剩余数量
     * @param $inventory_id
     * @return int
     */
    private function getConsumedNum($inventory_id)
    {

        $data = $this->_getPlayInventoryLogTable()->fetchLimit(0, 1, array(), array('inventory_id' => $inventory_id), array('id' => 'DESC'))->current();
        return $data ? $data->inventory_num : 0;
    }

    public function outInventoryAction()
    {

        $good_id = (int)$this->getQuery('good_id',null);
        $organizer_id = (int)$this->getQuery('organizer_id',null);
        $contract_num = (int)$this->getQuery('contract_id',null);
        $inventory_id = (int)$this->getQuery('inventory_num',null);
        $good_name = trim($this->getQuery('good_name',null));

        $where = "play_inventory.inventory_status > 0"; //去掉已经删除的

        if($good_id){
            $where .= " AND play_inventory.good_id = '{$good_id}'";
        }

        if($organizer_id){
            $where .= " AND play_inventory.organizer_id = '{$organizer_id}'";
        }

        if($contract_num){
            $where .= " AND play_inventory.contract_number = '{$contract_num}'";
        }

        if($inventory_id){
            $where .= " AND play_inventory.id = '{$inventory_id}'";
        }

        if($good_name){
            $where .= " AND play_inventory.good_name = '{$good_name}'";
        }

        $inventory_sql = "SELECT play_inventory.* FROM play_inventory WHERE $where ORDER BY id DESC";

        $inventoryData = $this->query($inventory_sql);

        $count = $inventoryData->count();
        if (!$count) {
            return $this->_Goto('0条数据！');
        }

        if ($count > 30000) {
            return $this->_Goto('数据太多了, 请缩小范围');
        }

        $file_name = date('Y-m-d H:i:s', time()). '_库存列表.csv';
        $head = array(
            '商品id',
            '商品名称',
            '库存id',
            '采购数量',
            '采购价',
            '预付金',
            '商家id',
            '合同编号',
            '消耗数量',
            '库存数量',
            '库存地点'
        );

        $content = array();

        foreach ($inventoryData as $v) {
            $content[] = array(
                $v['good_id'],
                $v['good_name'],
                $v['id'],
                $v['purchase_number'],
                $v['account_money'],
                $v['pre_money'],
                $v['organizer_id'],
                $v['contract_number'],
                $v['purchase_number'] - $this->getConsumedNum($v['id']),
                $this->getConsumedNum($v['id']),
                $v['inventory_address'] == 1 ? '玩翻天仓库' : '商家仓库',
            );

        }

        $outPut = new OutPut();

        $outPut->out($file_name, $head, $content);

        exit;
    }

    public function outInventoryInfoAction()
    {

        $start_time = trim($this->getQuery('start_time',null));
        $end_time = trim($this->getQuery('end_time',null));
        $order_sn = trim($this->getQuery('order_sn', null));

        $id = (int)$this->getQuery('id', 0);

        $inventoryData = $this->_getPlayInventoryTable()->get(array('id' => $id));

        if (!$id || !$inventoryData) {
            $this->_Goto('非法操作');
        }

        $where = "play_inventory_log.inventory_id = {$id}";

        $order = "play_inventory_log.id DESC";

        if ($start_time && $end_time && strtotime($start_time) > strtotime($end_time)) {
            $this->_Goto('查询时间有问题');
        }


        //购买时间
        if ($start_time) {
            $start_time = strtotime($start_time);
            $where = $where. " AND play_inventory_log.dateline > ".$start_time;
        }

        if ($end_time) {
            $end_time = strtotime($end_time) + 86400;
            $where = $where. " AND play_inventory_log.dateline < ".$end_time;
        }

        if ($order_sn) {
            $order_sn = (int)preg_replace('|[a-zA-Z/]+|','',$order_sn);
            $where = $where. " AND play_inventory_log.object_id = ". $order_sn;
        }

        $inventory_log_sql = "SELECT play_inventory_log.* FROM play_inventory_log WHERE $where";

        $inventoryLogData = $this->query($inventory_log_sql." ORDER BY {$order}");

        $count = $inventoryLogData->count();

        if (!$count) {
            return $this->_Goto('0条数据！');
        }

        if ($count > 30000) {
            return $this->_Goto('数据太多了, 请缩小范围');
        }

        $file_name = date('Y-m-d H:i:s', time()). '_库存明细列表.csv';
        $head = array(
            '记录id',
            '记录时间',
            '类型',
            '订单号',
            '交易金额',
            '累计金额',
            '消耗数量',
            '库存数量',
            '说明'
        );

        $content = array();

        foreach ($inventoryLogData as $v) {
            $content[] = array(
                $v['id'],
                date('Y-m-d H:i:s', $v['dateline']),
                ($v['types'] == 1) ? '入库' : '出库',
                $v['object_id'],
                ($v['types'] == 1) ? '-'. $v['flow_money'] : '+'. $v['flow_money'],
                $v['inventory_money'],
                ($v['types'] == 1) ? '+'. $v['flow_num'] : '-'. $v['flow_num'],
                $v['inventory_num'],
                $v['description'],
            );
        }

        $outPut = new OutPut();

        $outPut->out($file_name, $head, $content);

        exit;

    }

}