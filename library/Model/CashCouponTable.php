<?php
namespace library\Model;

class CashCouponTable extends BaseTable
{

    public function insert2($data){
        parent::insert($data);
        parent::getlastInsertValue();
    }

}