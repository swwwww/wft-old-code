<?php

namespace Admin\Controller;

use Deyi\GetCacheData\CityCache;
use Deyi\JsonResponse;
use Deyi\Paginator;
use Deyi\Validation;
use Deyi\ImageProcessing;
use library\Fun\M;
use Zend\View\Model\ViewModel;

class OrganizerController extends BasisController
{
    use JsonResponse;

    public function indexAction()
    {
        $page = (int)$this->getQuery('p', 1);

        $city = $this->chooseCity();

        if($city){
            $citysql =  " AND play_organizer.city = '{$city}'";
        }else{
            $citysql = '';
        }

        $like = $this->getQuery('k', '');
        $pageSum = 10;
        if (isset($_GET['heiwu']) && $_GET['heiwu']) {
            $where = "play_organizer.status = 0 ".$citysql;
        } else {
            $where = "play_organizer.status > 0 ".$citysql;
        }

        if ($like) {
            $lie = (int)$like;
            $where = $where. " AND (play_organizer.name like  '%".$like."%' OR play_organizer.id = {$lie})";
        }

        $start = ($page - 1) * $pageSum;

        $sql = "SELECT
play_organizer.id,
play_organizer.name,
play_organizer.pwd,
play_organizer.city,
play_organizer.branch_id,
play_organizer.pay_pwd
FROM
play_organizer
WHERE
$where
ORDER BY
play_organizer.dateline DESC
LIMIT {$start}, {$pageSum}
";

        $data = $this->query($sql);
        $sql_count = "SELECT play_organizer.id FROM play_organizer WHERE $where";
        $count = $this->query($sql_count)->count();

        $url = '/wftadlogin/organizer';
        $paging = new Paginator($page, $count, $pageSum, $url);
        $real_data = array();
        foreach ($data as $da) {
            $game_num = $this->_getPlayOrganizerGameTable()->fetchCount(array('organizer_id' => $da['id'], 'status >= ?' => 0));

            $real_data[] = array(
                'id' => $da['id'],
                'name' => $da['name'],
                'city' => $da['city'],
                'pwd' => $da['pwd'],
                'branch_id' => $da['branch_id'],
                'pay_pwd' => $da['pay_pwd'],
                'game_num' => $game_num,
            );
        }

        return array(
            'data' => $real_data,
            'pageData' => $paging->getHtml(),
            'city' => $this->getAllCities(),
            'filtercity' => CityCache::getFilterCity($_GET['city']),
        );
    }

    public function newAction()
    {
        $data = null;
        $accountData = null;
        $oid = $this->getQuery('oid');
        if ($oid) {
            $data = $this->_getPlayOrganizerTable()->get(array('id' => $oid));
            $accountData = $this->_getPlayOrganizerAccountTable()->get(array('organizer_id' => $oid));
        }

        $vm = new ViewModel(
            array(
                'data' => $data,
                'accountData' => $accountData,
            )
        );
        return $vm;
    }

    public function saveAction()
    {

        $name = $this->getPost('name');
        $phone = $this->getPost('phone');
        $address = $this->getPost('address');
        $addr_x = $this->getPost('addr_x');
        $addr_y = $this->getPost('addr_y');
        $brief = $this->getPost('brief');
        $information = $this->getPost('editorValue');
        $city = $this->getPost('city');
        $oid = $this->getPost('oid');
        $cover = $this->getPost('cover');
        $thumb = $this->getPost('thumb');

        if (!in_array($city, array_flip($this->_getConfig()['city']))) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
        }

        $validation = new validation();
        $error = null;
        if ($validation->strLengthRange($name, 1, 30, '商家名称')) {
            $error = $error . $validation->strLengthRange($name, 1, 30, '商家名称') . "\n";
        }

        if ($validation->strLengthRange($address, 1, 100, '商家地址')) {
            $error = $error . $validation->strLengthRange($address, 1, 100, '商家地址') . "\n";
        }

        if ($validation->strLengthRange($brief, 1, 1000, '商家简介')) {
            $error = $error . $validation->strLengthRange($brief, 1, 1000, '商家简介') . "\n";
        }

        if ($phone && !$validation->isUnderlineAndNumber($phone)) {
            $error = $error . '联系电话填写错误' . "\n";
        }

        if (!$phone) {
            $phone = 4008007221; //玩翻天官方电话;
        }



        if (!$validation->isRequired($addr_x) || !$validation->isRequired($addr_y)) {
            $error = $error . '坐标没填写' . "\n";
        }

        if (!$validation->isRequired($information)) {
            $error = $error . '图文详情没填写' . "\n";
        }

        /*if (!$validation->isRequired($cover)) {
            $error = $error . '封面图片' . "\n";
        }*/

        if ($error) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => $error));
        }

        // todo 图片处理放到统一的一个方法里面；
        if ($cover) {
            $cover_class = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $cover);
            $cover_status = $cover_class->scaleResizeImage(720, 360);
            if ($cover_status) {
                $cover_status->save($_SERVER['DOCUMENT_ROOT'] . $cover);
            }
        }

        if ($thumb) {
            $surface_class = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $thumb);
            $surface_status = $surface_class->scaleResizeImage(360, 360);
            if ($surface_status) {
                $surface_status->save($_SERVER['DOCUMENT_ROOT'] . $thumb);
            }
        }

        $data = array(
            'name' => $name,
            'phone' => $phone,
            'address' => $address,
            'addr_x' => $addr_x,
            'addr_y' => $addr_y,
            'brief' => $brief,
            'information' => $information,
            'cover' => $cover,
            'thumb' => $thumb,
        );

        $flag = $this->_getPlayOrganizerTable()->get(array('name' => $name, 'city' => $city, 'status > 0'));

        if ($oid) {
            $organizerData = $this->_getPlayOrganizerTable()->get(array('id' => $oid));
            if ($organizerData->name != $name && $flag) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '该商家已经存在啦'));
            }

        } else {
            if ($flag) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '该商家已经存在'));
            }
        }


        if ($oid) {
            $status = $this->_getPlayOrganizerTable()->update($data, array('id' => $oid));
            if ($status) { //更新使用商家名称
                $this->_getPlayCodeUsedTable()->update(array('organizer_name' => $name), array('organizer_id' => $oid));
                $this->_getPlayContractsTable()->update(array('organizer_name' => $name), array('mid' => $oid));
            }

        } else {
            $data['dateline'] =  time();
            $data['city'] = $city;
            $data['pwd'] = $validation->rand_gen_code();
            $data['pay_pwd'] = $validation->rand_pay_code();
            $data['password'] = md5($data['pwd']);
            $status = $this->_getPlayOrganizerTable()->insert($data);
            $oid = $this->_getPlayOrganizerTable()->getlastInsertValue();
        }

        if ($status) {
            //操作记录
            $this->adminLog($oid ? '修改商家' : '新建商家', 'organizer', $oid);
            return $this->jsonResponsePage(array('status' => 1, 'message' => '成功', 'oid' => $oid));
        } else {
            return $this->jsonResponsePage(array('status' =>0, 'message' => '失败', 'oid' => $oid));
        }

    }

    public function changeAction()
    {
        $type = $this->getQuery('type');
        $oid = $this->getQuery('oid');

        if ($type == 1 && $oid) {//删除
            $this->_getPlayOrganizerTable()->update(array('status' => 0), array('id' => $oid));
            //操作记录
            $this->adminLog('删除商家', 'organizer', $oid);
            return $this->_Goto('成功');
        }

        if ($type == 2 && $oid) {//恢复
            $this->_getPlayOrganizerTable()->update(array('status' => 1), array('id' => $oid));
            return $this->_Goto('成功');
        }

        return $this->_Goto('非法操作');

    }

    //关联游玩地
    public function linkShopAction() {
        $mid = (int)$this->getQuery('mid');
        $sid = (int)$this->getPost('sid');
        $time = time();

        // todo 判断 mid sid的合法性
        $flag = $this->_getPlayLinkOrganizerShopTable()->get(array('shop_id' => $sid));
        if ($flag) {
            $status = $this->_getPlayLinkOrganizerShopTable()->update(array('organizer_id' => $mid, 'dateline' => $time), array('shop_id' => $sid));
        } else {
            $status = $this->_getPlayLinkOrganizerShopTable()->insert(array('organizer_id' => $mid, 'dateline' => $time, 'shop_id' => $sid));
        }
        return $this->jsonResponsePage(array('status' => $status , 'message' => $status ? '成功' : '关联失败'));
    }

    //商家联系人
    public function linkerAction() {
        $oid = $this->getQuery('oid');
        //todo 添加联系人
        if ($this->getRequest()->isPost()) {
            $name = $this->params()->fromPost('name');
            $phone = $this->params()->fromPost('phone');
            $qq = $this->params()->fromPost('qq');
            $mail = $this->params()->fromPost('mail');
            $job = $this->params()->fromPost('job');
            $id = $this->params()->fromPost('oid');
            if (!$name) {
                return $this->_Goto('联系人姓名必填');
            }
            $status = $this->_getPlayOrganizerTouchTable()->insert(array(
                'name' => $name,
                'phone' => $phone,
                'qq' => $qq,
                'mail' => $mail,
                'job' => $job,
                'oid' => $id
            ));

            return $this->_Goto($status ? '添加成功' : '添加失败');
        }


        //todo 删除
        if ($this->getQuery('act') == 'del') {
            $id = $this->getQuery('id');
            $status = $this->_getPlayOrganizerTouchTable()->delete(array('id' => $id, 'oid' => $oid));
            return $this->_Goto($status ? '删除成功' : '删除失败');
        }

        $data = $this->_getPlayOrganizerTouchTable()->fetchAll(array('oid' => $oid));

        return array(
            'data' => $data,
            'oid' => $oid,
        );
    }

    //商家合同
    public function contractAction() {

        $oid = (int)$this->getQuery('id');
        $organizer = $this->_getPlayOrganizerTable()->get(array('id' => $oid));


        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;

        $contract_data = $this->_getPlayContractsTable()->getContractList($start,$pageSum,array(),array('mid' => $oid), array());

        $count = M::getAdapter()->query("SELECT count(*) as c FROM play_contracts  WHERE play_contracts.mid = {$oid}",array())->current()->c;

        //创建分页
        $url = '/wftadlogin/organizer/contract';

        $paging = new Paginator($page, $count, $pageSum, $url);

        $contractData = array();
        foreach ($contract_data as $contract) {

            $saleData = $this->getContractSale($contract['id']);
            $contractData[] = array(
                'id' => $contract['id'],
                'contract_no' => $contract['contract_no'],
                'create_time' => $contract['create_time'],
                'admin_name' => $contract['admin_name'],
                'organizer_name' => $contract['name'],
                'contracts_type' => $contract['contracts_type'],
                'pre_money' => $contract['pre_money'],
                'contracts_status' => $contract['contracts_status'],
                'check_status' => $contract['check_status'],
                'status' => $contract['status'],
                'goods_num' => $saleData['goods_num'],
                'order_num' => $saleData['order_num'],
                'shop_pre_income' => $saleData['shop_pre_income'],
                'shop_real_income' => $saleData['shop_real_income'],
                'deyi_income' => $saleData['deyi_income'],
            );
        }

        //合同类别
        $contract_type = array(
            '1'=>'包销',
            '2'=>'自营',
            '3'=>'代销'
        );

        //合同状态
        $contractStatus = array(
            'not_approved' => '未审批',
            'sub_approved'=>'已提交审批',
            'pre_money_paid'=>'已付预付金',
            'is_act'=>'正在生效',
            'sub_end'=>'已提交结束',
            'ended'=>'已结束',
            'sub_stop'=>'已提交终止',
            'stopped'=>'已终止',
        );

        $vm = new ViewModel(array(
            'organizer' => $organizer,
            'data' => $contractData,
            'pageData' => $paging->getHtml(),
            'contract_status' => $contractStatus,
            'contract_type' => $contract_type,

        ));

        return $vm;

    }

    //商家分店
    public function branchAction() {
        $oid = (int)$this->getQuery('oid');
        $organizerData = $this->_getPlayOrganizerTable()->get(array('id' => $oid));
        if (!$organizerData || $organizerData->branch_id) {
           return $this->_Goto('该商家不是总店');
        }

        $linkData = $this->_getPlayOrganizerTable()->fetchAll(array('status > 0', 'branch_id' => $oid));

        $like = $this->getQuery('k', '');

        $likeData = null;
        if ($like) {
            $lie = (int)$like;
            $likeData = $this->_getPlayOrganizerTable()->fetchAll(array(
                'status > 0',
                "branch_id = 0",
                'city' =>$_COOKIE['city'],
                "(name like '%". $like."%' OR id = {$lie})",
                "id != {$oid}",
            ));
        }

        $vm = new ViewModel(
            array(
                'organizerData' => $organizerData,
                'linkData' => $linkData,
                'likeData' => $likeData,
            )
        );

        return $vm;
    }

    public function branchLinkAction() {
        $lid = (int)$this->getQuery('lid');
        $oid = (int)$this->getQuery('oid');

        $orL = $this->_getPlayOrganizerTable()->get(array('id' => $oid));
        $orR = $this->_getPlayOrganizerTable()->get(array('id' => $lid));

        if ($lid == $oid) {
            return $this->_Goto('商家不能关联商家');
        }

        if (!$orL || !$orR || $orL->branch_id || $orR->branch_id) {
            return $this->_Goto('非法操作');
        }

        $lCount = $this->_getPlayOrganizerTable()->fetchCount(array('branch_id' => $lid, 'status > 0'));

        if ($lCount) {
            return $this->_Goto('该商家是总店');
        }

        $this->_getPlayOrganizerTable()->update(array('branch_id' => $oid), array('id' => $lid));

        return $this->_Goto('成功');

    }

    //去掉分店
    public function deleteBranchAction() {
        $id = (int)$this->getQuery('oid');
        $status = $this->_getPlayOrganizerTable()->update(array('branch_id' => 0), array('id' => $id));
        return $this->_Goto($status ? '成功' : '失败');
    }

    //获取合同的销售情况
    private function getContractSale($id) {

        $data = array(
            'goods_num' => 0,
            'order_num' => 0,
            'shop_pre_income' => 0,
            'shop_real_income' => 0,
            'deyi_income' => 0,
        );

        $contractData = $this->_getPlayContractsTable()->get(array('id' => $id));

        if (!$contractData) {
            return $data;
        }

        $goodData = $this->_getPlayOrganizerGameTable()->fetchAll(array('contract_id' => $id));

        $data['goods_num'] = $goodData->count();

        if (!$data['goods_num']) {
            return $data;
        }

        $good_id = '';
        foreach ($goodData as $good) {
            $good_id = $good_id. $good->id. ',';
        }

        $good_id = trim($good_id, ',');

        //todo  订单id 查询价格方案id
        $order_sql = "SELECT
/*play_contract_link_price.account_money as give_money,*/
play_order_info.buy_number,
play_order_info.use_number,
play_order_info.account_money,
play_order_info.real_pay,
play_game_info.account_money as give_money,
play_game_price.id
FROM play_order_info
LEFT JOIN play_order_info_game ON play_order_info_game.order_sn = play_order_info.order_sn
LEFT JOIN play_game_info ON play_game_info.id = play_order_info_game.game_info_id
LEFT JOIN play_game_price ON play_game_price.id = play_order_info_game.price_id
/*LEFT JOIN play_contract_link_price ON play_contract_link_price.id=play_game_price.contract_link_id*/
WHERE play_order_info.coupon_id in ({$good_id}) AND play_order_info.order_status = 1 AND play_order_info.pay_status > 1";

        $orderData = $this->query($order_sql);

        foreach ($orderData as $order) {

            //商家预计收入 套系被删除
            if (!$order['id']) {
                $data['shop_pre_income'] =  $data['shop_pre_income'] + bcmul($order['use_number'], $order['give_money']) ;
            }

            //订单数量
            $data['order_num'] =  $data['order_num'] + 1;

            if ($order['use_number']) {
                //商家实际收入 使用数*结算价
                $data['shop_real_income'] =  $data['shop_real_income'] + bcmul($order['use_number'], $order['give_money']); //todo 预付款事项

                //平台收入 （使用数/购买数） * 订单收入  - 给商家结算的
                $data['deyi_income'] = $data['deyi_income'] + bcdiv(($order['account_money'] + $order['real_pay']) * $order['use_number'], $order['buy_number'], 2) - bcmul($order['use_number'], $order['give_money'], 2);

            }
        }

        //商家预计收入 套系存在
        $price_sql = "SELECT
play_game_price.total_num,
play_game_price.account_money
FROM play_game_price
WHERE play_game_price.gid in ({$good_id})";

        $priceInfo = $this->query($price_sql);

        foreach ($priceInfo as $price) {
            $data['shop_pre_income'] =  $data['shop_pre_income'] + bcmul($price['total_num'], $price['account_money']) ;
        }

        return $data;
    }


    //绑定商家手机号
    public function bindAction(){
        $phone = trim($this->getPost('phone'));
        $oid = (int)$this->getPost('pid');

        if(!$phone || !$oid){
            return $this->jsonResponsePage(array("status"=>0,'message'=>'非法参数'));
        }

        //商家信息
        $organizer_data = $this->_getPlayOrganizerTable()->get(array('id'=>$oid));
        if(!$organizer_data){
            return $this->jsonResponsePage(array("status"=>0,'message'=>'商家信息不存在'));
        }


        $status = $this->_getPlayOrganizerTable()->update(array('phone'=>$phone),array('id'=>$oid));
        if($status){
            return $this->jsonResponsePage(array("status"=>1,'message'=>'绑定成功'));
        }else{
            return $this->jsonResponsePage(array("status"=>0,'message'=>'已绑定该号码'));
        }

    }

    //商家账号
    public function accountAction() {
        $oid = $this->getQuery('oid',0);
        $organizerData = $this->_getPlayOrganizerTable()->get(array('id' => $oid));

        if (!$organizerData) {
            return $this->_Goto('该商家不存在');
        }

        $accountData = $this->_getPlayOrganizerAccountTable()->get(array('organizer_id' => $oid));

        $right = false;

        if ($_COOKIE['id'] == 2797 || $_COOKIE['id'] == 1317) {
            $right = true;
        }

        return array(
            'organizerData' => $organizerData,
            'accountData' => $accountData,
            'right' => $right,
        );

    }

    //保存商家账号
    public function saveAccountAction() {
        $organizer_id = $this->getPost('organizer_id', 0);
        $notification_phone = $this->getPost('notification_phone');
        $bank_user = $this->getPost('bank_user');
        $bank_name = $this->getPost('bank_name');
        $bank_address = $this->getPost('bank_address');
        $bank_card = $this->getPost('bank_card');

        $organizer = $this->_getPlayOrganizerTable()->get(array('id' => $organizer_id));
        $account = $this->_getPlayOrganizerAccountTable()->get(array('organizer_id' => $organizer_id));

        if (!$organizer) {
            return $this->_Goto('非法操作');
        }

        if (!$account &&  (!$bank_user || !$bank_name || !$bank_address || !$bank_card)) {
            return $this->_Goto('商家开户信息不完整');
        }

        if ($notification_phone && !preg_match("/1[34578]{1}\d{9}$/", $notification_phone)) {
            return $this->_Goto('汇款通知电话不正确');
        }

        $data = array(
            'bank_user' => $bank_user,
            'bank_name' => $bank_name,
            'bank_address' => $bank_address,
            'bank_card' => $bank_card,
            'notification_phone' => $notification_phone,
        );

        if (!$account) {
            $data['organizer_id'] = $organizer_id;
            $data['total_money'] = 0;
            $data['use_money'] = 0;
            $data['not_use_money'] = 0;
            $status = $this->_getPlayOrganizerAccountTable()->insert($data);
        } else {

            if (!in_array($_COOKIE['id'], array(2797, 1317))) {
                return $this->_Goto('非法操作！');
            }

            $status = $this->_getPlayOrganizerAccountTable()->update($data, array('organizer_id' => $organizer_id));
        }
        return $this->_Goto($status ? '成功' : '失败');


    }

}
