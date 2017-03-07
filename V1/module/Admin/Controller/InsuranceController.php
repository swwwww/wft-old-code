<?php

namespace Admin\Controller;

use Deyi\Baoyou\Baoyou;
use Deyi\Idverification;
use Deyi\JsonResponse;
use Deyi\OutPut;
use Deyi\Paginator;
use Deyi\PY;
use library\Service\System\Cache\RedCache;
use Zend\Db\Sql\Predicate\Expression;
use Zend\Db\Sql\Select;
use Zend\View\Model\ViewModel;
use Deyi\ImageProcessing;
use Deyi\SendMessage;

class InsuranceController extends BasisController
{
    use JsonResponse;


    //保险列表
    public function indexAction()
    {

        $t1 = $this->getQuery('t1');
        $t2 = $this->getQuery('t2');
        $t3 = $this->getQuery('t3');
        $t4 = $this->getQuery('t4');
        $username = $this->getQuery('username');
        $phone = $this->getQuery('phone');

        $ins_status = $this->getQuery('ins_status'); //投保状态
        $check_status = $this->getQuery('check_status'); //信息检查
        $out = $this->getQuery('out');

        $good_id = (int)$this->getQuery('good_id');
        $page = $this->getQuery('page', 1);
        $pageSum = 10;
        $offset = ($page - 1) * $pageSum;


        $where = "play_order_insure.coupon_id={$good_id} and play_order_info.pay_status>=2";

        if ($t1 and $t2) {
            $t1 = strtotime($t1);
            $t2 = strtotime($t2);
            $where = " and (play_order_info.dateline>={$t1} AND play_order_info.dateline<={$t2}) ";
        }

        if ($t3 and $t4) {
            $t3 = strtotime($t3);
            $t4 = strtotime($t4);
            $where .= " and (play_coupon_code.use_datetime>={$t3} AND play_coupon_code.use_datetime<={$t4})";
        }

        if ($username) {
            $where .= " and play_order_info.username='{$username}'";
        }
        if ($phone) {
            $where .= " and play_order_info.buy_phone='{$phone}'";
        }

        if ($check_status == -1) {
            $where = "{$where} and (play_order_insure.name='' or play_order_insure.insure_status in(4,0))";
        }

        if ($ins_status) {
            $where .= " and play_order_insure.insure_status={$ins_status} ";
        }

        if ($check_status == 1) {
            $where .= " and (play_order_insure.insure_status={$check_status} and  play_order_insure.name!='')";
        }

        $list = $this->_getPlayOrderInsureTable()->tableGateway->select(function (Select $select) use ($offset, $pageSum, $where) {
            $select->where(array($where));
            $select->join('play_order_info', 'play_order_info.order_sn=play_order_insure.order_sn', array('dateline', 'username', 'user_id', 'buy_name', 'buy_phone'), 'left');
            $select->join('play_order_info_game', 'play_order_info_game.order_sn=play_order_insure.order_sn', array('type_name'), 'left');
            $select->join('play_coupon_code', 'play_coupon_code.order_sn=play_order_insure.order_sn', array('use_datetime'), 'left');
            $select->group('play_order_insure.insure_id');
            $select->order(array('order_sn' => 'desc'));
        });

        //导出
        if ($out) {
            $out = new OutPut();
            $file_name = date('Y-m-d H:i:s', time()) . '_保险列表.csv';
            $head = array(
                '订单号',
                '下单时间',
                '套系名称',
                '用户名',
                'uid',
                '联系人',
                '手机号',
                '出行人',
                '身份证',
                '投保状态',
                '保险号',
            );
            $data = array();

            foreach ($list as $v) {
                $data[] = array(
                    $v->order_sn,
                    date('Y-m-d', $v->dateline),
                    $v->type_name,
                    $v->username,
                    $v->user_id,
                    $v->buy_name,
                    $v->buy_phone,
                    $v->name,
                    $v->id_num,
                    $v->insure_status == 3 ? "已投保" : '未投保',
                    $v->insure_sn
                );
            }
            $out->out($file_name, $head, $data);
        }


        $count = $this->_getPlayOrderInsureTable()->tableGateway->select(function (Select $select) use ($offset, $pageSum, $where) {
            $select->columns(array('count' => new Expression('count(*)')));
            $select->join('play_order_info', 'play_order_info.order_sn=play_order_insure.order_sn', array('dateline', 'username', 'user_id', 'buy_name', 'buy_phone'), 'left');
            $select->join('play_order_info_game', 'play_order_info_game.order_sn=play_order_insure.order_sn', array('type_name'), 'left');
            $select->join('play_coupon_code', 'play_coupon_code.order_sn=play_order_insure.order_sn', array('use_datetime'), 'left');
            $select->group('play_order_insure.insure_id');
            $select->where(array($where));
        })->current()->count;


        //创建分页
        $url = '/wftadlogin/Insurance';
        $pagination = new Paginator($page, $count, $pageSum, $url);

//       $aa= new Baoyou();
//
//        if($_GET['debug1']){
//            var_dump($aa->getToken(), $aa->getProductInfo('BY37543173'),$aa->GetProductRateList());
//        }
       
        return array(
            'good_id' => $good_id,
            'list' => $list,
            'pageData' => $pagination->getHtml(),
            'baoyou' => new Baoyou()
        );
    }

    public function editAction()
    {
        $insure_id = $this->getQuery('insure_id');
        $data = $this->_getPlayOrderInsureTable()->get(array('insure_id' => $insure_id));
        return array('data' => $data);
    }

    public function saveAction()
    {

        $insure_id = $this->getQuery('insure_id', 0);

        $order_sn = $this->getPost('order_sn');
        $coupon_id = $this->getPost('coupon_id', 0);
        $name = $this->getPost('name');
        // $sex = $this->getPost('sex');
        // $date = $this->getPost('date');
        $id_num = $this->getPost('id_num');


        $checkId = $this->checkCardId($id_num);

        if ($checkId['errNum'] != 0) {
            return $this->_Goto('身份证号码不合法', 'javascript:history.back(-1)');
        }

        if (strlen($id_num) != 18) {
            return $this->_Goto('身份证长度错误', 'javascript:history.back(-1)');
        }

        if (!$name) {
            return $this->_Goto('必填数据', 'javascript:history.back(-1)');
        }


        $birth = date('Ymd', strtotime($checkId['retData']['birthday']));

        if ($checkId['retData']['sex'] == 'M') {
            $sex = 1;
        } else {
            $sex = 2;
        }


        $data = array(
            'order_sn' => $order_sn,
            'coupon_id' => $coupon_id,
            'name' => $name,
            'sex' => $sex,
            'birth' => $birth,
            'id_num' => $id_num,
            'insure_status' => 1
        );

        if ($insure_id) {
            $s = $this->_getPlayOrderInsureTable()->update($data, array('insure_id' => $insure_id));
            if ($s) {
                return $this->_Goto('保存成功');
            } else {
                return $this->_Goto('保存失败');
            }

        } else {
            // $this->_getPlayOrderInsureTable()->insert($data);
        }

    }


    //投保
    public function toubaoAction()
    {
        /*
         * 已确认一个订单对应一个保单,承包人数量*单价=保险价格
         *保险状态，0信息未填写，1未投保，2投保中，3已投保 4投保失败
         * 
         * */
        $order_str = $this->getQuery('order_sn'); //投保数据id  多个使用逗号隔开  2342,235235,23523,
        $start_time = $this->getQuery('start_time');// 保险开始时间
        $end_time = $this->getQuery('end_time');//保险结束时间


        $orders = explode(',', $order_str);

        foreach ($orders as $order_sn) {
            if (!$order_sn) {
                continue;
            }

            $list = $this->_getPlayOrderInsureTable()->fetchAll(array('order_sn' => $order_sn));
            $order_data = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));

            $e = array();
            $Insureds = array();//被保人数据
            foreach ($list as $v) {

                //已经投保
                if ($v->insure_status == 3) {
                    continue;
                }
                if ($v->insure_status == 0 or $v->insure_status == 4) {
                    $e[] = "保险id:" . $v->insure_id;
                }


                $Insureds[] = array(
                    'CName' => $v->name,
                    'EName' => PY::encode($v->name, 'all'),
                    'CardType' => 1,
                    'Sex' => $v->sex == 1 ? 1 : 0,
                    'CardNo' => $v->id_num,
                    'Mobile' => '',
                    'BirthDay' => date('Y-m-d', strtotime($v->birth))
                );
            }


            if (!empty($e)) {
                return $this->_Goto("订单号为{$order_sn}的订单包含数据异常的出行人信息,请检查<br>" . implode('<br>', $e));
            }


            //投保人为公司
            $param = array(
                'Order' => array( //保单基本信息
                    'ProductCode' => $v->product_code,  // 保游网提供的产品编号
                    'SerialNumber' => $order_sn,   //合作伙伴生成的订单唯一
                    'StartTime' => date('Y-m-d 00:00:00', strtotime($start_time)), //"2016-06-01 00:00:00",  //  保险开始时间
                    'EndTime' => date('Y-m-d 23:59:59', strtotime($end_time)), //"2016-06-01 23:59:59", //  保险结束时间
                    'Destination' => '中国'  // 出行目的地
                ),
                'PolicyHolder' => array( //投保人信息
                    'CName' => '武汉玩翻天科技有限公司', //投保人中文名   使用年龄最大的用户
                    'EName' => PY::encode('武汉玩翻天科技有限公司', 'all'), //投保人姓名拼音
                    'CardType' => 3,  //1身份证;2护照;3其他
                    'Sex' => 1,  //0：女 1：男
                    'CardNo' => '91420105MA4KLGA8XA',  //投保人证件号码[身份证号码只需要支持18位，15位身份证已经过期]
                    'Mobile' => $order_data->buy_phone, //投保人手机号码 送投保成功短信
                    'BirthDay' => date('Y-m-d', strtotime('19900101'))// 投保人出生日期	格式yyyy-MM-dd[投保人必须年满18周岁]
                ),
                'Insureds' => $Insureds
            );

            $baoyou = new Baoyou();
            $res = $baoyou->Ins($param);


            if (!$res->IsSuccess) {
                return $this->_Goto($res->ErrorMsg);
                break;
            } else {
                // 更新保单数据
                $this->_getPlayOrderInsureTable()->update(array('insure_sn' => $res->PolicyNo, 'baoyou_sn' => $res->OrderNo, 'insure_status' => 3), array('order_sn' => $order_sn));
                //发送短信

                $baoyou = new Baoyou();
                SendMessage::Send12($order_data->buy_phone, $baoyou->getProductInfo($v->product_code)['ProductName'],count($Insureds),$res->PolicyNo);
            }
        }
        return $this->_Goto('投保成功');
    }

    //退保
    public function tuibaoAction()
    {
        
        $order_sn = $this->getQuery('order_sn');
        $b = $this->_getPlayOrderInsureTable()->get(array('order_sn' => $order_sn));


        if ($b->insure_status!= 3) {
            return $this->_Goto('未投保');
        }

        $baoyou = new Baoyou();
        $res = $baoyou->Cins($b->insure_sn,$b->baoyou_sn);

        if (!$res->IsSuccess) {
            return $this->_Goto($res->ErrorMsg);
        }
        $this->_getPlayOrderInsureTable()->update(array('insure_sn' => '', 'baoyou_sn' => '', 'insure_status' => 1), array('order_sn' => $order_sn));
        return $this->_Goto('退保成功');
    }

    public function checkCardId($code)
    {
        return Idverification::isCard($code);
        $header = 'apikey: 1c6b3fbd2dfc45aeb5c0b80c4cf4c7f0';

        $context = stream_context_create(array(
            'http' => array(
                'method' => 'GET',
                'header' => $header,
                'timeout' => 60
            )
        ));
        $data = file_get_contents("http://apis.baidu.com/apistore/idservice/id?id=" . $code, false, $context);
        if (!$data) {
            return false;
        } else {
            $data = json_decode($data, true);
            return $data;
        }
    }


}
