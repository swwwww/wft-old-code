<?php

namespace Deyi\Account;

use Application\Module;
use Deyi\BaseController;

class OrganizerAccount
{
    use BaseController;

    public $tradeWay = array(
        'alipay' => '支付宝',
        'union' => '银联',
        'weixin' => '微信',
        'jsapi' => '旧微信网页',
        'account' => '账户',
        'new_jsapi' => '新微信网页',
        'nopay' => '未付款',
    );

    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }

    /**
     * 商家临时收益
     * @param $organizer_id //商家id
     * @param int $object_type // 1 订单分润 2 预付款 3订单冲账 (ps : 2预付款已经去掉)
     * @param float $money //钱
     * @param int $code_id //使用码id
     * @param int $order_sn //订单号
     * @param int $contract_id //合同id
     * @return array
     */
    public function profits($organizer_id, $object_type = 1, $money = 0.00, $code_id = 0, $order_sn, $contract_id) {

        $money = (float)$money;

        if (!$organizer_id || !$object_type || !$money || !$code_id) {
            return array('status' => 0, 'message' => '参数不正确');
        }

        if (!in_array($object_type, array(1, 3))) {
            return array('status' => 0, 'message' => '类型不正确');
        }

        if ($money <= 0) {
            return array('status' => 0, 'message' => '没钱');
        }

        $account = $this->_getPlayOrganizerAccountTable()->get(array('organizer_id' => $organizer_id));
        $organizerData = $this->_getPlayOrganizerTable()->get(array('id' => $organizer_id));

        if (!$organizerData) {
            return array('status' => 0, 'message' => '商家不存在');
        }

        if (!$account) {
            $data = array(
                'organizer_id' => $organizer_id,
                'bank_user' => '',
                'bank_name' => '',
                'bank_address' => '',
                'bank_card' => '',
                'total_money' => 0,
                'use_money' => 0,
                'not_use_money' => 0,
            );

            $stu = $this->_getPlayOrganizerAccountTable()->insert($data);

            if (!$stu) {
                return array('status' => 0, 'message' => '插入商家账号失败');
            }

        }

        //如果进入结算流程 不然再次进入
        $stat = $this->_getPlayOrganizerCodeLogTable()->get(array('code_id' => $code_id, 'order_sn' => $order_sn));

        if ($stat) {
            return array('status' => 0, 'message' => '已经存在');
        }

        $adapter = $this->_getAdapter();
        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();

        //更新商家账号里面的钱
        $s1 = $adapter->query("UPDATE play_organizer_account SET  not_use_money = not_use_money + {$money} WHERE organizer_id= ?", array($organizer_id))->count();

        if (!$s1) {
            $conn->rollback();
            return array('status' => 0, 'message' => '更新商家临时账户的钱失败');
        }

        $organizerCodeLog = array(
            $code_id, //code_id
            time(), //dateline
            1, //transport_status
            $object_type, //transport_type
            $organizer_id, //organizer_id
            $money, //flow_money
            $order_sn, //order_sn
            $contract_id, //contract_id
        );

        $s2 = $adapter->query("INSERT INTO play_organizer_code_log (id, code_id, dateline, transport_status, transport_type, organizer_id, flow_money, order_sn, contract_id) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?)", $organizerCodeLog)->count();

        if (!$s2) {
            $conn->rollback();
            return array('status' => 0, 'message' => '失败');
        }

        $conn->commit();
        return array('status' => 1, 'message' => '成功');
    }


    /**
     * 将零时账户的钱放到可提现的钱
     * @param $id
     * @return bool
     */
    public function transport($id)
    {
        if (!$id) {
            return false;
        }

        $adapter = $this->_getAdapter();

        $account_code_log_data = $adapter->query("select * from play_organizer_code_log where id=?", array($id))->current();
        $organizer_data = $this->_getPlayOrganizerTable()->get(array('id' => $account_code_log_data->organizer_id));

        if (!$account_code_log_data || !$organizer_data || $account_code_log_data->transport_status != 1 || $account_code_log_data->update_time > 0) {
            return false;
        }

        $account = $this->_getPlayOrganizerAccountTable()->get(array('organizer_id' => $account_code_log_data->organizer_id));
        if (!$account) {
            return false;
        }

        $flow_money = $account_code_log_data->flow_money;
        if ($flow_money > $account->not_use_money) {
            return false;
        }

        $object_type = $account_code_log_data->transport_type;

        if (!in_array($object_type, array(1, 3))) {
            return false;
        }

        $desc = ($object_type == 1) ? '订单分润' : '订单冲账';

        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();

        //更改商家临时金额 及可提现金额
        $use_money = $account->use_money + $flow_money;
        $not_use_money = $account->not_use_money - $flow_money;
        $total_money = $account->total_money + $flow_money;
        $s1 = $adapter->query("UPDATE play_organizer_account SET use_money={$use_money}, not_use_money = {$not_use_money}, total_money = {$total_money} WHERE organizer_id= ?", array($account_code_log_data->organizer_id))->count();

        if (!$s1) {
            $conn->rollback();
            return false;
        }

        //添加纪录
        $flow_number = date('YmdHis') . mt_rand(1000, 9999);
        $s2 = $adapter->query("INSERT INTO play_organizer_account_log (id,oid,action_type,object_type,object_id,status,description,dateline,flow_money,surplus_money,sub_dateline,contract_id,organizer_name,code_id,flow_number) VALUES (NULL,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            array($account_code_log_data->organizer_id, 1, $object_type, $account_code_log_data->order_sn, 1, $desc, time(), $flow_money, $total_money, time(), $account_code_log_data->contract_id, $organizer_data->name, $account_code_log_data->code_id,$flow_number))->count();
        if (!$s2) {
            $conn->rollback();
            return false;
        }
        
        $s3 = $adapter->query("UPDATE play_organizer_code_log SET update_time=?, transport_status = 2 WHERE id= ?", array(time(), $id))->count();

        if (!$s3) {
            $conn->rollback();
            return false;
        }

        if ($object_type == 3) {
            $s4 = $adapter->query("UPDATE play_coupon_code SET test_status=5, account_time=? WHERE id=?", array(time(), $account_code_log_data->code_id))->count();
            if (!$s4) {
                $conn->rollback();
                return false;
            }
        }

        $conn->commit();
        return true;

    }


    /**
     * //提交结算 确认到账  商家账户钱变动
     * @param $organizer_id //商家id
     * @param $money //钱
     * @param $action_type //1提交结算 2确认到账
     * @param $audit_id //转账记录id
     * @param string $flow_number //流水号
     * @return bool
     */
    public function audit($organizer_id, $money, $action_type, $audit_id = 0, $flow_number = '0')
    {

        //todo 可以将使用码 结算状态变化放到这里
        if (!in_array($action_type, array(1, 2))) {
            return false;
        }

        $account = $this->_getPlayOrganizerAccountTable()->get(array('organizer_id' => $organizer_id));
        $organizerData = $this->_getPlayOrganizerTable()->get(array('id' => $organizer_id));

        if (!$account || !$organizerData) {
            return false;
        }

        $adapter = $this->_getAdapter();
        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();

        if ($action_type == 1) {
            $use_money = $account->use_money - $money;
            $s1 = $adapter->query("UPDATE play_organizer_account SET use_money = ? WHERE organizer_id = ?", array($use_money, $organizer_id))->count();
            if (!$s1) {
                $conn->rollback();
                return false;
            }
        }

        if ($action_type == 2) {
            $total_money = $account->total_money - $money;
            $s1 = $adapter->query("UPDATE play_organizer_account SET total_money = ? WHERE organizer_id = ?", array($total_money, $organizer_id))->count();
            if (!$s1) {
                $conn->rollback();
                return false;
            }

            $s2 = $adapter->query("INSERT INTO play_organizer_account_log (id,oid,action_type,object_type,object_id,status,description,flow_money,surplus_money,dateline,contract_id,organizer_name,flow_number) VALUES (NULL,?,?,?,?,?,?,?,?,?,?,?,?)",
                array($organizer_id, 2, 2, $audit_id, 1, '结算_后台管理员_' .$_COOKIE['user'] . $_COOKIE['id'], $money, $total_money, time(), 0, $organizerData->name, $flow_number))->count();
            if (!$s2) {
                $conn->rollback();
                return false;
            }
        }

        $conn->commit();
        return true;

    }



    /**
     * 商家账号冻结时 不能提现
     *
     *  //商家提现 手动提交 商家后台提交 到期结算 满额自动结算
     * @param $organizer_id //商家id
     * @param int $action_type //2  取钱
     * @param int $object_type // 2 商家提现 1预付款提现 3已使用退款  //目前只有 1 3
     * @param $object_id //操作对象id  商家id  管理员id
     * @param $contract_id  //合同id
     * @param float $money //钱
     * @param string $desc //描述
     * @param int $status //状态
     * @param int $code_id //使用码id
     * @param string $flow_number //流水号
     * @return bool
     */

    public function takeCrash($organizer_id, $action_type = 2, $object_type = 2, $object_id, $money = 0.00, $desc = '',  $status = 2, $contract_id = 0, $code_id = 0, $flow_number = '0')
    {
        $money = (float)$money;

        if (!$organizer_id || !$object_type || !$money || !$desc) {
            return false;
        }

        if ($money <= 0) {
            return false;
        }

        $account = $this->_getPlayOrganizerAccountTable()->get(array('organizer_id' => $organizer_id));
        $organizerData = $this->_getPlayOrganizerTable()->get(array('id' => $organizer_id));

        if (!$account || !$organizerData) {
            return false;
        }

        if ($account->use_money < $money) {//商户账上钱可以为负
            //return false;
        }

        $surplus_money = bcsub($account->use_money, $money, 2);

        $adapter = $this->_getAdapter();
        $conn = $adapter->getDriver()->getConnection();
        $conn->beginTransaction();

        //更新商家账号里面的钱
        $s1 = $adapter->query("UPDATE play_organizer_account SET total_money=total_money-{$money}, use_money = use_money - {$money} WHERE organizer_id=?", array($organizer_id))->count();

        if (!$s1) {
            $conn->rollback();
            return false;
        }

        //添加纪录
        $s2 = $adapter->query("INSERT INTO play_organizer_account_log (id,oid,action_type,object_type,object_id,status,description,flow_money,surplus_money,dateline,contract_id,organizer_name,code_id,flow_number) VALUES (NULL,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            array($organizer_id, $action_type, $object_type, $object_id, $status, $desc, $money, $surplus_money, time(), $contract_id, $organizerData->name, $code_id, $flow_number))->count();
        if (!$s2) {
            $conn->rollback();
            return false;
        }

        $conn->commit();
        return true;

    }

    /**
     * 包销商品 出库 入库
     * @param $inventory_id //库存id
     * @param $type //1入库 2出库
     * @param $flow_money //流水
     * @param $flow_num //数量
     * @param $object_id //关联对象id 基本是订单id
     * @param $code_id //使用码id
     * @param $description //描述
     * @return bool
     */

    public function inventory($inventory_id, $type, $flow_money, $flow_num, $object_id = 0, $code_id = 0, $description)
    {
        if (!$inventory_id || !$type) {
            return false;
        }

        $inventoryData = $this->_getPlayInventoryTable()->get(array('id' => $inventory_id));

        if (!$inventoryData) {
            return false;
        }

        $inventory_num = 0;
        $inventory_money = 0;

        $inventoryLogData = $this->_getPlayInventoryLogTable()->fetchLimit(0, 1, array(), array('inventory_id' => $inventory_id), array('id' => 'DESC'))->current();

        if ($inventoryLogData) {
            if ($type == 1) {
                $inventory_num = bcadd($inventoryLogData->inventory_num, $flow_num, 0);
                $inventory_money = bcsub($inventoryLogData->inventory_money , $flow_money, 2);
            }

            if ($type == 2) {
                $inventory_num = bcsub($inventoryLogData->inventory_num, $flow_num, 0);
                $inventory_money = bcadd($inventoryLogData->inventory_money , $flow_money, 2);
            }

        } else {
            $inventory_num = $flow_num;
            $inventory_money = $inventory_money - $flow_money;
        }

        $data = array(
            'dateline' => time(),
            'types' => $type,
            'inventory_id' => $inventory_id,
            'flow_num' => $flow_num,
            'flow_money' => $flow_money,
            'inventory_num' => $inventory_num,
            'inventory_money' => $inventory_money,
            'object_id' => $object_id,
            'code_id' => $code_id,
            'description' => $description,
        );

        $result = $this->_getPlayInventoryLogTable()->insert($data);

        /*if ($result && $code_id) {
            $this->_getPlayCouponCodeTable()->update(array('test_status' => 5), array('id' => $code_id));
        }*/

        return $result;

    }

    /**
     * 冻结账户 解冻账户
     * @param $organizer_id
     * @param int $type //1冻结 2开启
     * @return bool
     */
    public function frozenOrganizer($organizer_id, $type = 1) {

        $result = false;

        if (!in_array($type, array(1, 2))) {
            return $result;
        }
        $status = (int)($type - 1);

        $result = $this->_getPlayOrganizerAccountTable()->update(array('status' => $status), array('organizer_id' => $organizer_id));

        return $result;

    }

}



