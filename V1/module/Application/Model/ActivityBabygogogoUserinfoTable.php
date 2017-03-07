<?php
/**
 * Created by PhpStorm.
 * User: chyxin
 * Date: 2015/9/23
 * Time: 9:41
 */

namespace Application\Model;

class ActivityBabygogogoUserinfoTable extends BaseTable
{
    /**
     * 结合场次表查找所有符合条件的用户信息
     * @param int $offset
     * @param int $row
     * @param array $columns
     * @param array $where
     * @param array $order
     *
     * @return \Zend\Db\ResultSet\ResultSet
     */
    public function getUserList($offset = 0, $row = 20, $columns = [], $where = [], $order = [])
    {
        $data = $this->tableGateway->select(function ($select) use ($offset, $row, $columns, $where, $order) {
            if (!empty($columns)) {
                $select->columns($columns);
            }

            $user_table  = 'activity_babygogogo_userinfo';
            $batch_table = 'activity_babygogogo_batch';
            $batch_cols  = ['batch_name', 'act_date', 'act_time', 'address'];
            $select->join($batch_table, "$user_table.batch_id = $batch_table.batch_id", $batch_cols, 'left');
            $select->where($where)->order($order);
            if ($row) {
                $select->limit($row)->offset($offset);
            }
        });
        return $data;
    }

    /**
     * 查找符合条件的用户数量
     * @param array $where
     *
     * @return int
     */
    public function getUserCount($where = [])
    {
        $data = $this->tableGateway->select(function ($select) use ($where) {
            $user_table  = 'activity_babygogogo_userinfo';
            $batch_table = 'activity_babygogogo_batch';
            $select->join($batch_table, "$user_table.batch_id = $batch_table.batch_id", ['batch_name'], 'left');
            $select->where($where);
        });
        return $data->count();
    }

    /**
     * 更新用户保险信息
     * @param array $where
     *
     * @return int
     */
    public function updateInsurance($where = [])
    {
        //TODO 先找出购买保险成功的用户，然后将这些用户中保险状态没更新的数据更新
        $data = $this->tableGateway->select(function ($select) use ($where) {
            $user_table  = 'activity_babygogogo_userinfo';
            $order_table = 'play_order_info';
            $select->join($order_table, "$order_table.user_id = $user_table.uid", ['order_sn'], 'left');
            $select->where($where);
        });

        //购买成功的用户id
        $uids = [];
        foreach ($data as $row) {
            $uids[] = $row->uid;
        }

        //更新用户保险状态
        $n = $this->update(['is_insure' => 1], ['is_insure' => 0, 'uid' => $uids, 'baby_identity_id != ?' => 0]);
        return $n;
    }
}