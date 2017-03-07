<?php
namespace Application\Model;

use Zend\Db\Sql\Select;

class PlayUserWeiXinTable extends BaseTable
{


    //获取用户信息

    public function getUserInfo($where)
    {
        return $this->tableGateway->select(function (Select $select) use ($where) {
            $select->where($where);
            $select->join('play_user', 'play_user_weixin.uid=play_user.uid');
            $select->order(array('phone'=>'desc'))->limit(1);
        })->current();
    }

}