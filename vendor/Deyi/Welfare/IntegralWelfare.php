<?php

namespace Deyi\Welfare;

use Deyi\BaseController;

trait IntegralWelfare
{
    use BaseController;

    public function __construct() {
        //todo 处理个人积分的增减
    }

    //分享 评论 产生积分
    /**
     * @param $uid //用户uid
     * @param $id //关联类型的id
     * @param $welType //产生积分的类型  1 游玩地分享 2 游玩地评论 3 商品分享 4 商品评论 5商品购买
     * @return array
     */
    public function getIntegral($uid, $id, $welType) {

        $welfare = $this->_getPlayWelfareIntegralTable()->get(array('link_id' => $id, 'status' => 2, 'welfare_type' => $welType));

        if (!$welfare) {
            return array('status' => 0, 'message' => '该对象无分享积分奖励');
        }

        if ($welfare->total_score <= $welfare->get_score || ($welfare->total_score - $welfare->get_score) < ($welfare->double * $welfare->basis_score)) {
            return array('status' => 0, 'message' => '该对象积分奖励没了');
        }

        // 判断用户领取积分记录
        $integralData = $this->_getPlayIntegralTable()->fetchAll(array('uid' => $uid, 'type' => $welType, 'object_id' => $id));
        if (($integralData->count() + 1) * $welfare->double * $welfare->basis_score > $welfare->limit_score) {
            return array('status' => 0, 'message' => '该用户积分已经领完了');
        }

        if ($welType == 1) {
            $expense = '游玩地分享';
        } elseif ($welType == 2) {
            $expense = '游玩地评论';
        } elseif ($welType == 3) {
            $expense = '商品分享';
        } elseif ($welType == 4) {
            $expense = '商品评论';
        } elseif ($welType == 5) {
            $expense = '商品购买';
        } else {
            return array('status' => 0, 'message' => '非法操作');
        }


        $data = array(
            'uid' => $uid,
            'type' => $welType,
            'total_score' => $welfare->double * $welfare->basis_score,
            'base_score' => $welfare->basis_score,
            'award_score' => $welfare->double * $welfare->basis_score - $welfare->basis_score,
            'expense' => $expense,
            'object_id' => $id,
            'link_id' => $welfare->id,
            'create_time' => time(),
            'city' => $this->getCity(),
        );

        //todo 使用事务;
        $status = $this->_getPLayIntegralTable()->insert($data);
        $get_score = $welfare->get_score + $welfare->double * $welfare->basis_score;
        $s2 = $this->_getPlayWelfareIntegralTable()->update(array('get_score' => $get_score), array('id' => $welfare->id, 'total_score >= ?' => $get_score));

        if ($s2 && $status) {
            return array('status' => 1, 'message' => '领取积分成功');
        } else {

        }return array('status' => 0, 'message' => '领取积分失败');

    }

    //编辑采纳 奖励积分
    public function acceptIntegral() {

    }

    //重大贡献 奖励积分
    public function giveIntegral() {

    }

    //todo 积分兑换减少

}