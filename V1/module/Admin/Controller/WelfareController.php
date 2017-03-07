<?php

namespace Admin\Controller;

use Deyi\Account\Account;
use Deyi\GetCacheData\GoodCache;
use Deyi\JsonResponse;
use Zend\View\Model\ViewModel;
use Deyi\Integral\Integral;

class WelfareController extends BasisController
{
    use JsonResponse;

    //修改积分福利
    public function newAction()
    {

        $id = (int)$this->getQuery('id', 0);
        $data = $this->_getPlayWelfareIntegralTable()->get(array('id' => $id, 'status > ?' => 0));

        if (!$data) {
            return $this->_Goto('非法操作');
        }

        $vm = new viewModel(
            array(
                'data' => $data,
            )
        );
        return $vm;
    }

    //保存积分
    public function saveAction()
    {

        $object_type = (int)$this->getPost('object_type');// 类型 1游玩地 2 商品
        $welfare_type = (int)$this->getPost('welfare_type'); // 3评论 4分享
        $id = (int)$this->getPost('id', 0); //积分id
        $object_id = (int)$this->getPost('object_id'); //对象id

        if (!in_array($object_type, array(1, 2)) || !in_array($welfare_type, array(3, 4)) || !$object_id) {
            return $this->_Goto('非法操作');
        }

        $double = (int)$this->getPost('double', 1);
        $limit_num = (int)$this->getPost('limit_num', 1);
        $total_num = (int)$this->getPost('total_num', 1000);

        if ($limit_num > $total_num) {
            return $this->_Goto('单个用户的不能超过总数');
        }

        $data = array(
            'object_id' => $object_id,
            'welfare_type' => $welfare_type,
            'double' => $double,
            'limit_num' => $limit_num,
            'total_num' => $total_num,
        );

        //todo 更新积分福利 缓存

        if ($id) {
            $status = $this->_getPlayWelfareIntegralTable()->update($data, array('id' => $id));

            //更新商品缓存
            if($object_type == 2){
                $gameData = $this->_getPlayOrganizerGameTable()->get(array('id' => $object_id));
                if (!$gameData) {
                    exit('非法操作');
                }
                GoodCache::setGameTags($object_id,$gameData->post_award);
            }
            return $this->_Goto($status ? '成功' : '失败');
        } else {
            $data['object_type'] = $object_type;
            $data['status'] = 1;
            $data['dateline'] = time();
            $data['editor_id'] = $_COOKIE['id'];
            $data['editor'] = $_COOKIE['user'];
            $status = $this->_getPlayWelfareIntegralTable()->insert($data);
            return $this->_Goto($status ? '成功' : '失败');
        }

    }

    //删除积分
   /* public function deleteIntegralAction() {
        $id = (int)$this->getQuery('id');
        $status = $this->_getPlayWelfareIntegralTable()->update(array('status' => 0), array('id' => $id));
        return $this->_Goto($status ? '成功' : '失败');
    }*/

    //新建现金返利福利
    public function rebateAction() {

        $object_id = (int)$this->getQuery('gid', 0); //商品id
        $wid = (int)$this->getQuery('wid', 0); //福利id

        $gameData = $this->_getPlayOrganizerGameTable()->get(array('id' => $object_id));

        if (!$gameData) {
            exit('非法操作');
        }

        $goodInfoData = $this->_getPlayGameInfoTable()->fetchAll(array('gid' => $object_id, 'status > ?' => 0));

        if (!$goodInfoData->count()) {
            return $this->_Goto('该商品的无可用的套系');
        }

        $welfare = null;
        if ($wid) {
            $welfare = $this->_getPlayWelfareRebateTable()->get(array('id' => $wid));
        }

        $vm = new viewModel(
            array(
                'gameData' => $gameData,
                'goodInfoData' => $goodInfoData,
                'welfare' => $welfare,
            )
        );
        return $vm;
    }

    //保存现金返利福利
    public function keepAction() {

        $rebate_type = (int)$this->getPost('rebate_type', 1);
        $give_type = (int)$this->getPost('give_type', 1);
        $total_num = (int)$this->getPost('total_num', 0);
        $single_rebate = $this->getPost('single_rebate', 0);
        $id = (int)$this->getPost('id', 0);
        $gid = (int)$this->getPost('gid', 0);
        $range = $this->getPost('range');

        if (!$range) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '套系'));
        }

        if ($total_num < 1) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '总次数'));
        }

        if ($single_rebate < 0.01) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '单个金额'));
        }

        if (!in_array($give_type, array(1, 2, 3))) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '返利时间'));
        }

        if (!in_array($rebate_type, array(1, 2))) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '返利类型'));
        }

        $data = array(
            'gid' => $gid,
            'single_rebate' => $single_rebate,
            'total_num' => $total_num,
            'give_type' => $give_type,
            'rebate_type' => $rebate_type,
            'use_range' => json_encode($range),
            'status' => 1,
        );


        if ($id) {
            $status = $this->_getPlayWelfareRebateTable()->update($data, array('id' => $id));
        } else {
            $data['create_time'] = time();
            $data['city'] = $_COOKIE['city'];
            $data['editor_id'] = $_COOKIE['id'];
            $data['editor'] = $_COOKIE['user'];
            $data['from_type'] = 1;
            $status = $this->_getPlayWelfareRebateTable()->insert($data);
            $id = $this->_getPlayWelfareRebateTable()->getlastInsertValue();
        }

        if ($status) {
            $this->_getPlayWelfareTable()->delete(array('welfare_type' => 2, 'object_type' => 2, 'object_id' => $gid, 'welfare_link_id' => $id));
            foreach ($range as $r => $n) {
                $this->_getPlayWelfareTable()->insert(array(
                    'object_id' => $gid,
                    'object_type' => 2,
                    'good_info_id' => $r,
                    'welfare_type' => 2,
                    'give_time' => $give_type,
                    'welfare_link_id' => $id,
                    'welfare_value' => $single_rebate,
                    'status' => 1,
                ));
            }
        }

        //更新商品缓存
        $gameData = $this->_getPlayOrganizerGameTable()->get(array('id' => $gid));
        if (!$gameData) {
            exit('非法操作');
        }
        GoodCache::setGameTags($gid,$gameData->post_award);

        //todo更新商品福利设
        return $this->jsonResponsePage(array('status' => 1));
    }

    //审批通过现金返利
    public function checkRebateAction() {
        $id = (int)$this->getQuery('id');
        $sure = (int)$this->getQuery('sure',0);
        $status = $this->_getPlayWelfareRebateTable()->update(array('status' => 2), array('id' => $id));
        if ($status) {
            $this->_getPlayWelfareTable()->update(array('status' => 2),array('welfare_link_id' => $id,'welfare_type'=>2));
            $welfare = $this->_getPlayWelfareTable()->get(array('welfare_type'=>2,'welfare_link_id' => $id));

            if($welfare && $welfare->object_type == 2){
                //更新商品缓存
                $gameData = $this->_getPlayOrganizerGameTable()->get(array('id' => $welfare->object_id));
                if (!$gameData) {
                    exit('非法操作');
                }
                GoodCache::setGameTags($welfare->object_id,$gameData->post_award);
            }else{
                $wrt = $this->_getPlayWelfareRebateTable()->get(array('id' => $id));

                if($wrt and (int)$wrt->from_type === 3){
                    $uid = $wrt->uid;
                    $cash = $wrt->single_rebate;
                    $action_type1 = $wrt->give_type;
                    $get_info = $wrt->get_info;
                    $adminer = $wrt->editor_id;
                    $city = $wrt->city;
                    $msgid = $wrt->gid;
                    $ac = new Account();
                    if($cash > 500 and $sure == 0){
                        $this->_getPlayWelfareRebateTable()->update(array('status' => 1), array('id' => $id));
                        return $this->_Goto('失败,金额超过了500');
                    }

                    $status = $ac->recharge($uid,$cash,$wrt->rebate_type-1,$get_info,$action_type1,0,false,$adminer,$city,$msgid);
                }
            }
        }
        //todo更新商品福利设置
        return $this->_Goto($status ? '成功' : '失败');
    }

    //删除现金返利福利
    public function deleteRebateAction() {
        $id = (int)$this->getQuery('id');
        $status = $this->_getPlayWelfareRebateTable()->update(array('status' => 0), array('id' => $id));
        if ($status) {
            $this->_getPlayWelfareTable()->update(array('status' => 1),array('welfare_link_id' => $id));
            $welfare = $this->_getPlayWelfareTable()->get(array('welfare_link_id' => $id));
            if($welfare && $welfare->object_type == 2){
                //更新商品缓存
                $gameData = $this->_getPlayOrganizerGameTable()->get(array('id' => $welfare->object_id));
                if (!$gameData) {
                    exit('非法操作');
                }
                GoodCache::setGameTags($welfare->object_id,$gameData->post_award);
            }
        }
        //todo更新商品福利设置
        return $this->_Goto($status ? '成功' : '失败');
    }

    //现金券
    public function cashCouponAction() {

        $object_id = (int)$this->getQuery('gid', 0); //商品id
        $wid = (int)$this->getQuery('wid', 0); //福利id

        $gameData = $this->_getPlayOrganizerGameTable()->get(array('id' => $object_id));

        if (!$gameData) {
            exit('非法操作');
        }

        $priceData = $this->_getPlayGameInfoTable()->fetchAll(array('gid' => $object_id, 'status > ?' => 0));

        if (!$priceData->count()) {
            return $this->_Goto('该商品的无可用的套系');
        }

        $welfare = null;
        if ($wid) {
            $welfare = $this->_getPlayWelfareCashTable()->get(array('id' => $wid));
        }

        //查询是否有 可用的现金券(新用户专享不参与)
        $cashCoupon = $this->_getCashCouponTable()->fetchAll(array('city'=>$this->getAdminCity(),'is_close' => 0,'new'=>0, 'residue > 0', 'status = 1'));

        $vm = new viewModel(
            array(
                'gameData' => $gameData,
                'priceData' => $priceData,
                'welfare' => $welfare,
                'cashCoupon' => $cashCoupon,
            )
        );
        return $vm;

    }

    //保存现金券
    public function holdAction() {

        $give_type = (int)$this->getPost('give_type', 1);
        $total_num = (int)$this->getPost('total_num', 0);
        $id = (int)$this->getPost('id', 0);
        $gid = (int)$this->getPost('gid', 0);
        $range = $this->getPost('range');

        $cash_coupon_id = (int)$this->getPost('cash_coupon_id', 0);
        $cashCoupon = $this->_getCashCouponTable()->get(array('id' => $cash_coupon_id));

        if (!$cashCoupon) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
        }

        if ($cashCoupon->status != 1) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '该现金券未通过审核'));
        }

        if (!$id && $total_num > $cashCoupon->residue) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '该现金券剩余数量不够'));
        }

        if (!$range) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '套系'));
        }

        if ($id) {
            $welfareCashData = $this->_getPlayWelfareCashTable()->get(array('id' => $id));
            if ($welfareCashData->give_num > $total_num) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '该现金券领取的数量超过了总数'));
            }

            if ($cashCoupon->residue < ($total_num - $welfareCashData->give_num)) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '该现金券剩余数量不够'));
            }
        }

        if ($total_num < 1) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '发放总数量'));
        }

        $data = array(
            'gid' => $gid,
            'total_num' => $total_num,
            'give_type' => $give_type,
            'use_range' => json_encode($range),
            'status' => 2,
            'cash_coupon_id' => $cash_coupon_id,
            'cash_coupon_name' => $cashCoupon->title,
            'cash_coupon_price' => $cashCoupon->price,
        );

        if ($id) {
            $status = $this->_getPlayWelfareCashTable()->update($data, array('id' => $id));

        } else {
            $data['create_time'] = time();
            $data['city'] = $_COOKIE['city'];
            $data['editor_id'] = $_COOKIE['id'];
            $data['editor'] = $_COOKIE['user'];
            $status = $this->_getPlayWelfareCashTable()->insert($data);
            $id = $this->_getPlayWelfareCashTable()->getlastInsertValue();
        }

        if ($status) {
            $this->_getPlayWelfareTable()->delete(array('welfare_type' => 3, 'object_type' => 2, 'object_id' => $gid, 'welfare_link_id' => $id));
            foreach ($range as $r => $n) {
                $this->_getPlayWelfareTable()->insert(array(
                    'object_id' => $gid,
                    'object_type' => 2,
                    'good_info_id' => $r,
                    'welfare_type' => 3,
                    'give_time' => $give_type,
                    'welfare_link_id' => $id,
                    'welfare_value' => $cashCoupon->price,
                    'status' => 2,
                ));
            }
        }

        //更新商品缓存
        $gameData = $this->_getPlayOrganizerGameTable()->get(array('id' => $gid));
        if (!$gameData) {
            exit('非法操作');
        }
        GoodCache::setGameTags($gid,$gameData->post_award);

        return $this->jsonResponsePage(array('status' => 1));
    }

    //删除现金券福利
    public function deleteCashCouponAction() {
        $id = (int)$this->getQuery('id');
        $status = $this->_getPlayWelfareCashTable()->update(array('status' => 0), array('id' => $id));
        if ($status) {
            $this->_getPlayWelfareTable()->update(array('status' => 1),array('welfare_link_id' => $id));
            $welfare = $this->_getPlayWelfareTable()->get(array('welfare_link_id' => $id));
            if($welfare && $welfare->object_type == 2){
                //更新商品缓存
                $gameData = $this->_getPlayOrganizerGameTable()->get(array('id' => $welfare->object_id));
                if (!$gameData) {
                    exit('非法操作');
                }
                GoodCache::setGameTags($welfare->object_id,$gameData->post_award);
            }
        }
        //todo更新商品福利设置
        return $this->_Goto($status ? '成功' : '失败');
    }
}
