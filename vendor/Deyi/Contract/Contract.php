<?php
/**
 * Created by PhpStorm.
 * User: dddee
 * Date: 2016/1/29
 * Time: 11:21
 */
namespace Deyi\Contract;

use Application\Module;
use Deyi\BaseController;
use Zend\Db\Sql\Predicate\Expression;

class Contract
{
    use BaseController;

    //BaseController 使用
    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }


    //获取合同商品的销售情况
    public function getContractSaleData($contract_id = 0)
    {
        $where = " play_contracts.id>0";

        if ($contract_id > 0) {
            $where .= " and play_contracts.id = {$contract_id}";
        }

        $data = $this->_getPlayContractsTable()->getContractSaleData(array(
            "contract_id" => "id",
            'total_account' => new Expression('sum(play_order_info.buy_number*play_contract_link_price.account_money)'),
            'pre_income' => new Expression('sum(play_contract_link_price.price*play_contract_link_price.total_num)')
        ), $where);

        return $data;

    }


    //获取合同商品套系信息
    public function getContractSet($contract_id = 0)
    {
        $where = " play_contracts.id>0 ";

        if ($contract_id > 0) {
            $where .= " and play_contracts.id = {$contract_id}";
        }

        $data = $this->_getPlayContractsTable()->getContractSet(array('contract_id' => 'id'), $where);

        return $data;
    }

    /**
     * 添加合同使用记录
     * @param $contract_id
     * @param $type 1创建合同 2提交审批 3审批通过 4反审批 5批准预付金 6审批不通过 7添加合同套系 合同状态变为未审批
     * @param $message
     * @return int
     */
    public function addLog($contract_id, $type, $message)
    {
        //todo 如果有错 记录下来
        $data = array(
            'contract_id' => $contract_id,
            'contract_status' => $type,
            'action_user' => $_COOKIE['id'],
            'action_user_name' => $_COOKIE['user'],
            'action_note' => $message,
            'dateline' => time(),
        );

        $result = $this->_getPlayContractActionTable()->insert($data);

        return $result;
    }


    /**
     * 获取合同里面的预付金 总的预付金 及待审核的预付金
     * @param $contract_id
     * @return array
     */
    public function getPreMoney($contract_id)
    {
        $data = array(
            'total' => 0,
            'wait' => 0,
        );
        $contractData = $this->_getPlayContractsTable()->get(array('id' => $contract_id));

        if (!$contractData) {
            return $data;
        }

        $adapter = $this->_getAdapter();
        if ($contractData->contracts_type == 1) //包销
        {
            $preData = $adapter->query("SELECT
	SUM(if(play_inventory.check_pre_status = 0, play_inventory.pre_money, 0)) AS pre_money,
	SUM(play_inventory.pre_money) AS total_money
FROM
	play_inventory
WHERE
	contract_id = ?
AND inventory_status > ?", array($contract_id, 0))->current();

        }

        if ($contractData->contracts_type == 3) //代销
        {
            $preData = $adapter->query("SELECT
	SUM(if(play_contract_link_price.check_pre_status = 0, play_contract_link_price.pre_money, 0)) AS pre_money,
	SUM(play_contract_link_price.pre_money) AS total_money
FROM
	play_contract_link_price
WHERE
	contract_id = ?
AND status > ?", array($contract_id, 0))->current();

        }

        $data['total'] = $preData->total_money;
        $data['wait'] = $preData->pre_money;

        return $data;
    }

    /**
     * 获取合同的 商品个数 及 订单个数
     * @param $contract_id
     * @return array
     */

    public function getExtInfo($contract_id)
    {
        $data = array(
            'goods_num' => 0,
            'order_num' => 0,
        );

        $contractData = $this->_getPlayContractsTable()->get(array('id' => $contract_id));

        if (!$contractData) {
            return $data;
        }

        $adapter = $this->_getAdapter();

        $good_num_sql = "SELECT
id
FROM play_organizer_game
WHERE play_organizer_game.contract_id = {$contract_id}";

        $goodData = $adapter->query($good_num_sql, array());
        $data['goods_num'] = $goodData->count();

        if (!$data['goods_num']) {
            return $data;
        }

        $good_id = '';
        foreach ($goodData as $good) {
            $good_id = $good_id . $good['id'] . ',';
        }

        $good_id = trim($good_id, ',');

        $order_num_sql = "SELECT
count(play_order_info.order_sn) as order_num
FROM play_order_info
WHERE play_order_info.coupon_id in ({$good_id}) AND play_order_info.order_status = 1 AND play_order_info.pay_status > 1";

        $orderData = $adapter->query($order_num_sql, array())->current();
        $data['order_num'] = $orderData->order_num;

        return $data;
    }

    /**
     * 判断是否冲账
     * @param $order_sn
     * @return bool
     */
    public function isOffset($order_sn)
    {
        $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));

        if (!$orderData) {
            return false;
        }

        $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $orderData->coupon_id));

        if (!$goodData) {
            return false;
        }

        $res = $this->_getPlayContractLinkPriceTable()->get(array(
            'status > ?' => 0,
            'good_id' => $orderData->coupon_id,
            'contract_id' => $goodData->contract_id,
            'pre_money > ?' => 0
        ));

        if ($res) {
            return true;
        }

        return false;
    }

    /**
     * 判断合同类型
     * @param $order_sn
     * @return int
     */
    public function getContractType($order_sn)
    {
        $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));
        $gameData = $this->_getPlayOrganizerGameTable()->get(array('id' => $orderData->coupon_id));
        $contractData = $this->_getPlayContractsTable()->get(array('id' => $gameData->contract_id));

        if (!$contractData) {
            return 0;
        }

        return $contractData->contracts_type;

    }


    /**
     * 批量库存判断是否超出
     * @param $ids 时间地点套系id
     * @param $good_id
     * @param $total_num
     * @param $price
     * @param $money
     * @param $account_money
     * @return int
     */
    public function getContractLeave($ids, $good_id, $total_num,$price,$money,$account_money)
    {
        $apt = $this->_getAdapter();
        $inventoryData = $this->_getPlayInventoryTable()->fetchLimit(0, 100, ['id','purchase_number'],
            array('good_id' => $good_id))->toArray();

        foreach ($inventoryData as $id) {
            //获得一个仓库里的价格方案
            $contract_price = $this->_getPlayContractLinkPriceTable()->fetchLimit(0, 500, ['id'],
                ['inventory_id' => $id['id'], 'status > ?' => 0])->toArray();
            $inven_diff[$id['id']] = 0;
            $inven_has[$id['id']] = 0;

            //通过方案获得套系里面的存量
            foreach ($contract_price as $cp) {
                $sql = "select id,total_num,buy,status from play_game_info where contract_price_id = ? ";
                $infos = $apt->query($sql, [$cp['id']])->toArray();//一个方案下的时间地点
                $temp_id = $id['id'];//库存id
                foreach ($infos as $info) {
                    if((int)$info->total_num === (int)$total_num){//如果没有修改库存（和之前的库存一样）统计时减去已经购买的
                        $diff = 0;
                        //判断是否
                        if ($price !== '' and (float)$price !== (float)$info->price){
                            $diff = (int)$info->buy;
                        }
                        if ($money !== '' and (float)$money !== (float)$info->money){
                            $diff = (int)$info->buy;
                        }
                        if ($account_money !== '' and (float)$account_money !== (float)$info->account_money){
                            $diff = (int)$info->buy;
                        }
                    }else{
                        $diff = 0;
                    }
                    if (in_array($info['id'], $ids)) {//计算变化了的时间地点
                        $inven_diff[$temp_id] += ($total_num - $info['total_num'] - $diff);
                    }
                    if($info['status'] > 0){
                        $inven_has[$temp_id] += $info['total_num'];
                    }else{
                        $inven_has[$temp_id] += $info['buy'];
                    }
                }
            }
            if (($inven_has[$temp_id] + $inven_diff[$temp_id]) > $id['purchase_number']) {
                return 1;
            }
        }
    }

    /**
     * 获取包销合同里面的数量
     * @param $contract_link_id
     * @param int $game_info_id
     * @return int
     */
    public function getContractLimitNum($contract_link_id, $game_info_id = 0)
    {
        $contractLink = $this->_getPlayContractLinkPriceTable()->get(array('id' => $contract_link_id));

        if (!$contractLink) {
            return 0;
        }

        $inventoryData = $this->_getPlayInventoryTable()->get(array('id' => $contractLink->inventory_id));

        if (!$inventoryData) {
            return 0;
        }

        $giveNum = 0;
        $contractLinkData = $this->_getPlayContractLinkPriceTable()->fetchAll(array('inventory_id' => $contractLink->inventory_id));

        $link_id = '';
        foreach ($contractLinkData as $link) {
            $link_id = $link_id . ',' . $link['id'];
        }

        $link_id = trim($link_id, ',');
        $goodInfoData = $this->_getPlayGameInfoTable()->fetchAll(array('contract_price_id IN (' . $link_id . ')'));
        foreach ($goodInfoData as $info) {
            if ($info->id != $game_info_id) {
                if ($info->status > 0) {
                    $giveNum = $giveNum + $info->total_num;
                } else {
                    $giveNum = $giveNum + $info->buy;
                }
            }
        }

        return $inventoryData->purchase_number - $giveNum;


    }

}