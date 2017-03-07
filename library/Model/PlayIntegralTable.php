<?php

namespace library\Model;

use Zend\Db\Sql\Expression;

class PlayIntegralTable extends BaseTable
{
    public function getIntegerByType($object_id,$type){
        $data = $this->tableGateway->select(
            function ($select) use ($object_id ,$type) {
                $select->columns(['total_score' => new Expression('sum(total_score)')])
                    ->where(
                        ['object_id' => $object_id, 'type' => $type]
                    );
            })->current();
        if($data){
            $data = $data->total_score;
        }
        return $data;
    }
}