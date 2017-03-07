<?php
namespace Application\Model;

class CashCouponTable extends BaseTable
{

    public function insert2($data){
        parent::insert($data);
        parent::getlastInsertValue();
    }

}