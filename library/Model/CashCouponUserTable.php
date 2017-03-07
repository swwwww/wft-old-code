<?php
namespace library\Model;

class CashCouponUserTable extends BaseTable
{
    public function joinWithUser($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_user', 'play_cashcoupon_user_link.uid = play_user.uid', array('username', 'phone'),
                'left');
            $select->join('play_order_info', 'play_cashcoupon_user_link.use_order_id = play_order_info.order_sn',
                array('order_sn','coupon_name', 'coupon_id','bid', 'order_type', 'real_pay', 'account_money', 'total_price','coupon_unit_price'), 'left');
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order($order);
        });

        return $data;
    }

    public function joinWithUserAll($where = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($where) {
            $select->join('play_user', 'play_cashcoupon_user_link.uid = play_user.uid', array('username', 'phone'),
                'left');
            $select->join('play_order_info', 'play_cashcoupon_user_link.use_order_id = play_order_info.order_sn',
                array('order_sn','coupon_name', 'order_type', 'real_pay', 'account_money', 'total_price','coupon_unit_price'), 'left');
            $select->where($where);
            $select->order(['id'=>'desc']);
        });

        return $data;
    }

    /**
     * 现金券详情的推广累计统计
     * @param array $where
     * @return \Zend\Db\ResultSet\ResultSet
     */
    public function addupfreemoney($where = array()){
        $where['pay_time > ?'] = 0;
        $where['order_type'] = 2;

        $data = $this->tableGateway->select(function ($select) use ($where) {
            $select->join('play_user', 'play_cashcoupon_user_link.uid = play_user.uid', array('username', 'phone'),
                'left');
            $select->join('play_order_info', 'play_cashcoupon_user_link.use_order_id = play_order_info.order_sn',
                array('order_sn','coupon_name', 'order_type', 'real_pay', 'account_money', 'total_price','coupon_unit_price'), 'left');
            $select->join('play_coupon_code', 'play_coupon_code.order_sn = play_order_info.order_sn', array('force','back_money','code_status'=>'status'),
                'right');
            $select->where($where);
        });

        $free_money = 0;
        $back_money = $used_money = $in_money = $free = [];
        foreach ($data as $v) {
            $order_status = 1;
            $in_money[$v['order_sn']] = ($v['real_pay'] + $v['account_money']);

            if ($v['code_status'] == 1) {
                $used_money[$v['order_sn']] += $v['coupon_unit_price'];
                if((int)$v['force'] === 3){
                    $back_money[$v['order_sn']] += $v['back_money'];
                    $used_money[$v['order_sn']] -= $v['coupon_unit_price'];
                }
            }
            if ($v['code_status'] == 2) {
                if($v['force']!=3) {
                    $back_money[$v['order_sn']] += $v['back_money'];
                }
            }
            if ($v['code_status'] != 1 and $v['code_status'] != 2) {
                $order_status = 0;
            }
            //用户玩的商品实际需要的钱-用户使用出的钱
            $free[$v['order_sn']] = $used_money[$v['order_sn']] - ($in_money[$v['order_sn']] - $back_money[$v['order_sn']]);
            $free[$v['order_sn']] *= $order_status;
        }

        foreach($free as $f){
            $free_money += $f;
        }
        return $free_money;
    }
}