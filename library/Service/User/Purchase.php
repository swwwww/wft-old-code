<?php
namespace library\Service\User;

use library\Fun\M;

class Purchase
{

    //购买完成后 更新咨询状态
    public static function updateConsultStatus($orderData)
    {

        if (!$orderData || !in_array($orderData->order_type, array(2, 3))) {
            return false;
        }

        $coupon_id = $orderData->order_type == 2 ? intval($orderData->coupon_id) : intval($orderData->bid);
        $result = M::_getMdbConsultPost()->findOne(array('object_data.object_id' => $coupon_id,  'uid' => intval($orderData->user_id)));

        if ($result) {
            M::_getMdbConsultPost()->update(array('object_data.object_id' => $coupon_id,  'uid' => intval($orderData->user_id)), array('$set' => array('is_buy' => 2)), array('multiple' => true));
            return true;
        }

        return false;
    }


}