<?php
/**
 * Created by PhpStorm.
 * User: fandy
 * Date: 16-6-14
 * Time: 上午10:40
 */

namespace ApiKidsPlay\Controller;

use Deyi\Invite\Invite;
use Zend\Mvc\Controller\AbstractActionController;
use Deyi\BaseController;
use Zend\Http\Response;
use Deyi\JsonResponse;

class ApplyController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    //遛娃活动订单详情
    public function orderAction()
    {
        if (!$this->pass(false)) {
            return $this->failRequest();
        }

        $rid = $this->getParams('order_sn'); //订单id
        $uid = (int)$this->getParams('uid');//uid 用户uid
        $city = $this->getCity();

        $orderInfo = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $rid, 'order_status' => 1, 'user_id' => $uid));
        $other_data = $this->_getPlayOrderOtherDataTable()->get(array('order_sn' => $rid));

        $kpData = $this->_getPlayExcerciseEventTable()->getEventInfo(['play_excercise_event.id' => $orderInfo->coupon_id]);

        if (!$kpData || !$orderInfo) {
            return $this->jsonResponseError('该活动 或者订单不存在');
        }


        if ($orderInfo->pay_status < 2) {//未付款 //0未付款;1付款中; 2已付款 3 退款中 4 退款成功 5已使用
            return $this->jsonResponseError('订单未付款');
        }

        $codes = $this->_getPlayExcerciseCodeTable()->getCodeList($rid);


        if (!$codes) {
            return $this->jsonResponseError('没有购买这个活动');
        }

        $shop = $this->_getPlayShopTable()->get(['shop_id' => $kpData->shop_id]);
        
        $member_order_list = $other_order_list = [];
        $flag = 0;
        foreach ($codes as $c) {
            if ($c->is_other != 1) {
                $member_order_list[] = array(
                    'id' => $c->id,
                    'sn' => $c->code,
                    'info' => $c->price_name,
                    'refund_dateline' =>$kpData->back_time,
                    'status' => $c->status,
                    'people_number'=>$c->person,
                );
                if ($c->status != 3) {
                    $flag ++;
                }
            } else {
                $other_order_list[] = array(
                    'id' => $c->id,
                    'sn' => $c->code,
                    'info' => $c->price_name,
                    'refund_dateline' => $kpData->back_time,
                    'status' => $c->status
                );
            }
        }

        $price = bcadd($orderInfo->real_pay, $orderInfo->account_money, 2);
        $data = [];
        $data['id'] = $rid;//字符串	订单 ID
        $data['title'] = $kpData->name . ' 第' . $kpData->no . '期';//字符串	订单 ID
        $data['price'] = $price;//	字符串	总价
        $data['service_phone'] = $kpData->phone;//	字符串	总价

        $data['user_name'] = $orderInfo->username;
        $data['user_phone'] = $orderInfo->phone;

        $data['back_time'] = $kpData->back_time; //退款时间

        $data['status'] = !$flag ? 2 : 1;//	整型	订单状态	0:ing, 1:成功， 2：失败
        if($kpData->back_time<time()){
            //$data['status']=2;  // wwjie  7/21 修改
        }

        if ($kpData->over_time > time() && $orderInfo->order_status == 1 && $data['status']!=2 ) {
            $data['status'] = 0;
        }

        //时间截止 未达到最少人数
        if ($kpData->over_time < time() && $kpData->join_number < $kpData->least_number ) {
            $data['status'] =2;
        }


        if($kpData->sell_status==2){
            $data['status'] = 1;
        }
        if($kpData->sell_status==3 and $kpData->join_number==0){
            $data['status'] = 2;
        }


        //8/9 根据文档修改,只判断是否达到最低
        if($kpData->join_number>=$kpData->least_number){
            $data['status'] = 1;
        }

        //8/23 显示失败时客户端描述错误
        if($orderInfo->buy_number == ($orderInfo->back_number+$orderInfo->backing_number)){
            $data['status'] = 2;
        }

        $data['end_dateline'] = $kpData->end_time; //整型，结束时间缀
        $data['user_datetime'] = $kpData->start_time;//date('Y年m月d日H点i分', $kpData->start_time);    //字符串	使用时间	具体到时分

        $data['over_time'] = $kpData->over_time; //报名截止时间

        $data['join_number'] = $kpData->join_number; //已参加名额
        $data['least_number'] = $kpData->least_number;  //最少数量
        $data['perfect_number'] = $kpData->perfect_number; //完美数量
        $data['most_number'] = $kpData->most_number; //最多数量

        $data['place'] = ['id' => $shop->shop_id, 'name' => $shop->shop_name,
            'address' => $shop->shop_address, 'address_x' => $shop->addr_x, 'address_y' => $shop->addr_y];    //长度为1的列表	游玩地信息	点击跳地图


        $data['member_order_list'] = $member_order_list;    //列表	出行人订单列表

        $data['other_order_list'] = $other_order_list;    //列表	其它订单列表
        $data['pay_status'] = (int)$orderInfo->pay_status; //退款状态
        $data['back_time'] = (int)$kpData->back_time; //退款时间

        //获取已选择的出行人列表
        $associates = $this->_getPlayOrderInsureTable()->fetchLimit(0, 100, [], array('order_sn' => $rid));
        $data['associates'] = array();
        $data['lack_associates'] = 0;
        
        foreach ($associates as $v) {
            if ($v->insure_status == 0) {
                //未填写
                $data['lack_associates'] += 1;
            } else {
                $data['associates'][] = $v;
            }
        }

        //$db = $this->_getAdapter();
        //$sql = 'select * from play_cash_share where city = ? and isall = 1 and `type` = 2 limit 1';
        //$pcs = $db->query($sql, array($city))->current();

       
        $invite = new Invite();
        $data['middleware'] = $invite->middleware($orderInfo);

        if ($data['middleware']) {
            $info = $invite->EventShareInfo($rid, $uid, $city);
            $data['share_title'] = '【玩翻天】'.$info['share_title'];
            $data['share_content'] =  '我发现了一个不错的活动，'.$price.'元，我们一起报名吧。'.str_replace(array(" ","　","\t","\n","\r"),'',$info['share_content']);
            $data['share_image'] = $info['share_img'];
            $data['share_url'] = $info['share_url'];
            $data['share_info'] = $info['share_feedback'][0];
        } else {
            $info = $invite->EventShareInfo($rid, $uid, $city);
            $data['share_title'] = $info['share_title'];
            $data['share_content'] = $info['share_content'];
            $data['share_image'] = $info['share_img'];
            $data['share_url'] = $info['share_url'];
        }
        
        $data['share_type'] = [1, 2];    //列表	分享类型列表	1：微信， 2：朋友圈， 3：新浪微博， 4：QQ 参考 内流事件

        $data['meeting_id'] = (int)$other_data->meeting_id; //id
        $data['meeting_place'] = $other_data->meeting_place; //集合地点
        $data['meeting_time'] = (int)$other_data->meeting_time; //集合时间
       
        //集合方式
        $meeting = $this->_getPlayExcerciseMeetingTable()->fetchLimit(0, 100, array('id', 'meeting_place', 'meeting_time'), array('eid' => $orderInfo->coupon_id, 'is_close' => 0))->toArray();
        $data['meetings'] = $meeting;
        $data['refund_prompt'] = '财务审核后，退款将于3-5个工作日内退回至支付账户';//退款弹框提示

        return $this->jsonResponse($data);
    }

}