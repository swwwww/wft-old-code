<?php
/**
 * Created by PhpStorm.
 * User: kylin
 * Date: 2015/12/28
 * Time: 10:35
 */
namespace Application\Model;

class InviteRuleLogTable extends BaseTable
{

    public function getByRuleId($city='WH'/*, $dbSource = false*/)
    {
        $data = $this->fetchLimit(0, 1, [], ['city' => $city])->current();
        return $data;
    }


    public function getByMyRuleId($ruleid/*, $dbSource = false*/)
    {
        $data = $this->fetchLimit(0, 1, [], ['ruleid' => $ruleid])->current();
        return $data;
    }

    public function getLogById($id/*, $dbSource = false*/)
    {
        $data = $this->fetchLimit(0, 1, [], ['id' => $id])->current();
        return $data;
    }

    /**
     * 增加一次分享查看数
     * @param $token
     */
    public function addViews($token)
    {
        $this->update(['views' => new Expression('views + 1')], ['token' => $token]);
    }

    /**
     * 增加一次分享
     * @param $token
     */
    public function addCount($token)
    {
        $this->update(['count' => new Expression('count + 1')], ['token' => $token]);
    }
} 