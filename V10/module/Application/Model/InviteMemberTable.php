<?php
/**
 * Created by PhpStorm.
 * User: kylin
 * Date: 2015/12/28
 * Time: 16:24
 */
namespace Application\Model;

class InviteMemberTable extends BaseTable
{
    public function getByPhone($phone){
        $data = $this->fetchLimit(0, 1, [], ['phone' => $phone])->current();
        return $data;
    }

    public function getListByUid($start = 0, $pagesum = 0,$uid,$columns=[]){
        $data = $this->tableGateway->select(
            function ($select) use ($start, $pagesum, $uid, $columns) {
                $select->columns($columns)
                    ->join('play_user', 'play_user.phone = invite_member.phone', ['img','username'], 'left')
                    ->where(['invite_member.sourceid' => $uid]);
                if ($pagesum) {
                    $select ->limit($pagesum)->offset($start);
                }
                $select->order(['invite_member.dateline desc']);
            });
        return $data;
    }


} 