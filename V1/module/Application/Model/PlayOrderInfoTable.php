<?php
namespace Application\Model;

use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Select;

class PlayOrderInfoTable extends BaseTable
{

    //获取参与活动的用户列表
    function getExcerciseMembers($bid)
    {
        return $this->tableGateway->select(function (Select $select) use ($bid) {
            $select->join('play_user', 'play_user.uid=play_order_info.user_id', array('img'), 'left');

            $select->where(array('bid' => $bid,'order_status'=>1,'buy_number>(use_number+back_number+backing_number)'));

//            $select->join('play_excercise_code', 'play_excercise_code.uid=play_user.uid', array('eid'), 'left');
//            $select->where(array('play_excercise_code.eid' => $eid));

        });
    }


    //个人中心列表
    function getMylist($where)
    {
        return $this->tableGateway->select(function ($select) use ($where) {
            $select->where($where);
            $select->join('play_coupons', 'play_coupons.coupon_id=play_order_info.coupon_id', array('coupon_close', 'coupon_cover', 'coupon_appointment', 'coupon_thumb'));
            $select->order(array('dateline' => 'desc'))->limit(1000);
        });
    }


    //获取用户订单列表
    function getUserOrder($offset,$row,$column,$order_sn,$order)
    {
        return $this->tableGateway->select(function (Select $select) use ($offset,$row,$column,$order_sn,$order) {
            if ($column) {
                $select->columns($column);
            }
            $select->join('play_user', 'play_user.uid=play_order_info.user_id', array('device_type'), 'left');
            if ($row) {
                $select->limit($row)->offset($offset);
            }
            $select->where($order_sn)->order($order);

        });
    }


    public function fetchJoinLimit($offset = 0, $row = 20, $columns = array(), $where = array(), $order = array(), $like = array())
    {
        $resultSet = $this->tableGateway->select(function (Select $select) use ($columns, $where, $like, $order, $offset, $row) {
            if (!empty($columns)) {
                $select->columns($columns);
            }
            if (!empty($like) and $like[key($like)]) {
                $select->where->like('play_order_info.' . key($like), "%{$like[key($like)]}%");
            }
            if ($row) {
                $select->limit($row)->offset($offset);
            }
            $select->join('play_order_action', 'play_order_action.order_id=play_order_info.order_sn', array('action_id', 'play_status', 'back_dateline' => 'dateline'));
            $select->group(array('play_order_info.order_sn'));
            $select->where($where)->order($order);

        });

        return $resultSet;
    }

    public function getSnoopyOrder($offset = 0, $row = 20, $columns = [], $where = [], $order = [], $like = [])
    {
        $resultSet = $this->tableGateway->select(function (Select $select) use ($columns, $where, $like, $order, $offset, $row) {
            if (!empty($columns)) {
                $select->columns($columns);
            }
            if (!empty($like) and $like[key($like)]) {
                $select->where->like('play_order_info.' . key($like), "%{$like[key($like)]}%");
            }
            if ($row) {
                $select->limit($row)->offset($offset);
            }

            $action_cols = ['action_id', 'play_status', 'back_dateline' => 'dateline'];
            $vcode_cols = ['type', 'batch_name', 'verify_code', 'begin_time', 'end_time'];
            $select->join('play_order_action', 'play_order_action.order_id=play_order_info.order_sn', $action_cols);
            $select->join('activity_snoopy_verify_code', 'activity_snoopy_verify_code.verify_code=play_order_info.account', $vcode_cols);
            $select->join('play_coupon_code', 'play_coupon_code.order_sn=play_order_info.order_sn', ['id', 'password']);
            $select->group(['play_order_info.order_sn']);
            $select->where($where)->order($order);

        });

        return $resultSet;
    }

    public function getYouyouOrder($offset = 0, $row = 20, $columns = [], $where = [], $order = [], $like = [])
    {
        $resultSet = $this->tableGateway->select(function (Select $select) use ($columns, $where, $like, $order, $offset, $row) {
            if (!empty($columns)) {
                $select->columns($columns);
            }
            if (!empty($like) and $like[key($like)]) {
                $select->where->like('play_order_info.' . key($like), "%{$like[key($like)]}%");
            }
            if ($row) {
                $select->limit($row)->offset($offset);
            }

            $action_cols = ['action_id', 'play_status', 'back_dateline' => 'dateline'];
            $vcode_cols = ['type', 'batch_name', 'verify_code', 'begin_time', 'end_time'];
            $select->join('play_order_action', 'play_order_action.order_id=play_order_info.order_sn', $action_cols);
            $select->join('activity_youyou_verify_code', 'activity_youyou_verify_code.verify_code=play_order_info.account', $vcode_cols);
            $select->join('play_coupon_code', 'play_coupon_code.order_sn=play_order_info.order_sn', ['id', 'password']);
            $select->group(['play_order_info.order_sn']);
            $select->where($where)->order($order);

        });

        return $resultSet;
    }

    //活动订单
    public function fetchJoinGameLimit($offset = 0, $row = 20, $columns = array(), $where = array(), $order = array(), $like = array())
    {
        $resultSet = $this->tableGateway->select(function (Select $select) use ($columns, $where, $like, $order, $offset, $row) {
            if (!empty($columns)) {
                $select->columns($columns);
            }
            if (!empty($like) and $like[key($like)]) {
                $select->where->like('play_order_info.' . key($like), "%{$like[key($like)]}%");
            }
            if ($row) {
                $select->limit($row)->offset($offset);
            }
            $select->join('play_order_action', 'play_order_action.order_id=play_order_info.order_sn', array('action_id', 'play_status', 'back_dateline' => 'dateline'));
            $select->join('play_order_info_game', 'play_order_info_game.order_sn=play_order_info.order_sn');
            $select->join('play_game_info', 'play_game_info.id=play_order_info_game.game_info_id', array('game_dizhi' => 'shop_name', 'game_taoxi' => 'price_name', 'game_start' => 'start_time', 'game_end' => 'end_time'));
            $select->group(array('play_order_info.order_sn'));
            $select->where($where)->order($order);
        });

        return $resultSet;
    }

    //财务审核相关begin
    public function getOrderList($offset = 0, $row = 20, $columns = array(), $where = array(), $order = array(), $like = array()){
        $resultSet = $this->tableGateway->select(function (Select $select) use ($columns, $where, $like, $order, $offset, $row) {
            if (!empty($columns)) {
                $select->columns($columns);
            }
            if (!empty($like) and $like[key($like)]) {
                $select->where->like('play_order_info.' . key($like), "%{$like[key($like)]}%");
            }
            if ($row) {
                $select->limit($row)->offset($offset);
            }
            $select->join('play_order_action', 'play_order_action.order_id=play_order_info.order_sn', array('play_status',  'dateline'));
            $select->join('play_order_otherdata', 'play_order_otherdata.order_sn=play_order_info.order_sn',array('message'), 'left');
            $select->join('play_coupon_code', 'play_coupon_code.order_sn=play_order_info.order_sn',array('check_status','back_money'));
            $select->join('play_order_info_game', 'play_order_info_game.order_sn=play_coupon_code.order_sn');
            $select->join('play_organizer_game', 'play_organizer_game.id=play_order_info.coupon_id',array('game_status'=>'status','is_together','foot_time'));
            $select->join('play_game_info', 'play_game_info.id=play_order_info_game.game_info_id', array('price','account_money','price_name','up_time','down_time','refund_time','end_time'));
            $select->join('play_contracts', 'play_contracts.id=play_organizer_game.contract_id', array('business_id'),'left');
            $select->join('play_admin', 'play_admin.id=play_contracts.business_id', array('id','admin_name'),'left');
            $select->group(array('play_order_info.order_sn'));
            $select->where($where)->order($order);
        });

        return $resultSet;
    }

    public function getAuditOrder($offset = 0, $row = 20, $columns = array(), $where = array(), $order = array(), $like = array()){
        $resultSet = $this->tableGateway->select(function (Select $select) use ($columns, $where, $like, $order, $offset, $row) {
            if (!empty($columns)) {
                $select->columns($columns);
            }
            if (!empty($like) and $like[key($like)]) {
                $select->where->like('play_order_info.' . key($like), "%{$like[key($like)]}%");
            }
            if ($row) {
                $select->limit($row)->offset($offset);
            }
            $select->join('play_coupon_code', 'play_coupon_code.order_sn=play_order_info.order_sn',array('check_status','id','status','test_status','force'));
            $select->join('play_order_info_game', 'play_order_info_game.order_sn=play_coupon_code.order_sn');
            $select->join('play_game_info', 'play_game_info.id=play_order_info_game.game_info_id', array('account_money'));
            $select->group(array('play_order_info.order_sn'));
            $select->where($where)->order($order);
        });

        return $resultSet;
    }
    //评论时获取用户购买过的套系信息
    public function getUserBuy($order_sn)
    {
        return $this->tableGateway->select(function (Select $select) use ($order_sn) {
            $select->join('play_order_info_game', 'play_order_info_game.order_sn=play_order_info.order_sn');
            $select->join('play_game_info', 'play_game_info.id=play_order_info_game.game_info_id',['id',
                'total_num',
                'tid',
                'pid',
                'gid',
                'buy',
                'price',
                'money',
                'start_time',
                'end_time',
                'shop_id',
                'shop_name',
                'price_name',
                'status',
                'shop_circle',
                'integral']
              );
            $select->where(array('play_order_info.order_sn' => $order_sn, 'order_status' => 1));
            $select->limit(1);
        })->current();
    }

    //财审相关借宿


    //获取合同中商品的销售情况
    public function getContractGoodSale($columns = array(), $where = array()){
        $resultSet = $this->tableGateway->select(function (Select $select) use ($columns, $where) {
            if (!empty($columns)) {
                $select->columns($columns);
            }
            $select->join('play_coupon_code', 'play_coupon_code.order_sn=play_order_info.order_sn');
            $select->where($where);
        });


        return $resultSet;
    }


    //获取商家后台订单数据

    public function getShopAdminOrder($offset = 0, $row = 20, $where = array(), $order = array()){
        $resultSet = $this->tableGateway->select(function (Select $select) use ($where, $order, $offset, $row) {
            if ($row) {
                $select->limit($row)->offset($offset);
            }
            $select->join('play_coupon_code', 'play_coupon_code.order_sn=play_order_info.order_sn',array('check_status','status','test_status','force','use_datetime','back_time'));
            $select->join('play_order_info_game', 'play_order_info_game.order_sn=play_coupon_code.order_sn',array('type_name','address'));
            $select->where($where)->order($order);
        })->toArray();

        return $resultSet;
    }

    //获取活动订单列表
    public function getExcerciseOrderList($offset = 0, $row = 10, $where = array(), $order = array()){
        $resultSet = $this->tableGateway->select(function (Select $select) use ($where, $order, $offset, $row) {
            if ($row) {
                $select->limit($row)->offset($offset);
            }
            $select->columns(array('*','traveller_num'=>new Expression("(select count(*) from play_order_insure where play_order_insure.order_sn=play_order_info.order_sn)")));

            $select->join('play_order_insure', 'play_order_insure.order_sn=play_order_info.order_sn',array('product_code'),'left');
            $select->join('play_excercise_code', 'play_excercise_code.order_sn=play_order_insure.order_sn',array('pid'),'left');
            $select->join('play_excercise_base', 'play_excercise_base.id=play_order_info.bid',array('name','bid'=>'id'),'left');
            $select->join('play_excercise_event', 'play_excercise_event.id=play_order_info.coupon_id',array('shop_name','shopid'=>'shop_id','start_time','end_time','customize','eid'=>'id','join_number','least_number'),'left');

            $select->where($where)->group("play_order_info.order_sn")->order($order);
        })->toArray();

        return $resultSet;
    }

    public function getExcerciseOrderCount($where = array()){
        $resultSet = $this->tableGateway->select(function (Select $select) use ($where) {
            $select->columns(array('order_sn'));
            $select->join('play_order_insure', 'play_order_insure.order_sn=play_order_info.order_sn',array('product_code'),'left');
            $select->join('play_excercise_code', 'play_excercise_code.order_sn=play_order_insure.order_sn',array('pid'),'left');
            $select->join('play_excercise_base', 'play_excercise_base.id=play_order_info.bid',array('name','bid'=>'id'),'left');
            $select->join('play_excercise_event', 'play_excercise_event.id=play_order_info.coupon_id',array('shop_name','shopid'=>'shop_id','start_time','end_time','customize','eid'=>'id','join_number','least_number'),'left');
            $select->where($where)->group("play_order_info.order_sn");
        })->count();

        return $resultSet;
    }

    //用户分析列表
    public function getUserAnalysisList($offset = 0, $row = 20, $columns = array(), $where = array(), $order = array())
    {
        $resultSet = $this->tableGateway->select(function (Select $select) use ($offset, $row, $columns, $where, $order) {
            if ($row) {
                $select->limit($row)->offset($offset);
            }
            if (!empty($columns)) {
                $select->columns($columns);
            }
            $select->join('play_user_attached', 'play_user_attached.user_attached_uid = play_order_info.user_id',array('user_attached_average_value','user_attached_total_money'), 'inner');
            $select->where($where)->order($order);
        })->toArray();

        return $resultSet;
    }

}