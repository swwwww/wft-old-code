<?php

namespace Application\Model;


use Zend\Db\Sql\Expression;

class PlayClickLogTable extends BaseTable
{

    public function clickRecord($type, $ob_id)
    {
        $status = $this->tableGateway->update(array('click_number' => new Expression('click_number+1')), array('object_id' => $ob_id, 'object_type' => $type,));
        if (!$status) {
            $this->tableGateway->insert(array(
                'object_id' => $ob_id,
                'object_type' => $type,
                'click_number' => 1,
                'ip' => $_SERVER['REMOTE_ADDR'],
                'dateline' => time()
            ));
        }

    }
}