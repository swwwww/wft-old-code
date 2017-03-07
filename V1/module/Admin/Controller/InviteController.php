<?php

namespace Admin\Controller;

use Deyi\BaseController;
use Deyi\JsonResponse;
use Deyi\Paginator;
use Deyi\ImageProcessing;
use Zend\Console\ColorInterface;
use Zend\Db\Sql\Predicate\Expression;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class InviteController extends BasisController
{
    use JsonResponse;
    //use BaseController;

    private $city;
    private $rule;

    public function setEventManager(EventManagerInterface $events)
    {
        parent::setEventManager($events);

        $events->attach('dispatch', function ($e) {

            $this->city = $_COOKIE['city']?:'WH';
            if(!empty($this->city) && isset($this->city)){
                $this->rule = $this->_getInviteRule()->getByRuleId($this->city);
                if(!$this->rule){
                    //不存在其他站点,则调用WH站点的配置
                    $this->rule = $this->_getInviteRule()->getByRuleId('WH');
                }

            }else{
                //清理cookie退出
                setcookie('user', '', time() - 1, '/');
                setcookie('token', '', time() - 1, '/');
                setcookie('group', '', time() - 1, '/');
                setcookie('id', '', time() - 1, '/');
                setcookie('city', '', time() - 1, '/');
                exit('<script> window.location.href="/wftadlogin/";</script>');
            }


        }, 100);

    }

    public function indexAction()
    {
        $page = (int)$this->getQuery('p', 1);
        $invite_start = $this->getQuery('invite_start', '');
        $invite_end = $this->getQuery('invite_end', '');
        $device_type = $this->getQuery('device_type', 0);//邀约角色类型
        $action_type = $this->getQuery('action_type', -1);//默认全部
//        $siteid = $this->getQuery('siteid', 1);
        $city = $this->city;
        $token = $this->getQuery('token', '');

        $pagesum = 10;
        $start = ($page - 1) * $pagesum;
        if(empty($invite_start) && empty($invite_end)){//当天
            $invite_start = strtotime(date('Y-m-d').' 00:00:00');
            $invite_end = strtotime(date('Y-m-d').' 23:59:59');
        }else{
            $invite_start = strtotime($invite_start.' 00:00:00');
            $invite_end = strtotime($invite_end.' 23:59:59');
        }
        $where = array();
        if($device_type == 0){//查询受邀人
            $begin = array('dateline >= '.$invite_start);
            $end = array('dateline <= '.$invite_end);
            $where = array(
                'ruleid' => $this->rule->ruleid,
                //'status' => $action_type
            );
            if($action_type != -1){
                $where = array('status' => $action_type);
            }
            if(!empty($token)){
                $where = array('token' => $token);
            }
            $data = $this->_getInviteRecieverAwardLog()->getList($start, $pagesum, $where, $begin, $end)->toArray();
            //获得总数量
            $count = $this->_getInviteRecieverAwardLog()->getList(0, 0, $where, $begin, $end)->count();

        }else{//查询邀请人
            $where = array();
            $begin = array('dateline >= '.$invite_start);
            $end = array('dateline <= '.$invite_end);
            $where = array(
                'ruleid' => $this->rule->ruleid,
//                'status' => $action_type
            );
            if($action_type != -1){
                $where = array('status' => $action_type);
            }
            if(!empty($token)){
                $where = array('token' => $token);
            }
            $data = $this->_getInviteInviterAwardLog()->getList($start, $pagesum, $where, $begin, $end)->toArray();
            //获得总数量
            $count = $this->_getInviteInviterAwardLog()->getList(0, 0, $where, $begin, $end)->count();
        }

        //创建分页
        $url = '/wftadlogin/invite/index';
        $paginator = new Paginator($page, $count, $pagesum, $url);
        return array(
            'data' => $data,
            'pagedata' => $paginator->getHtml(),
        );


    }

    /**
     *邀约后台
     *
     *
     */
    public function settingAction(){
        //$playCashCoupon = $this->_getPlayCashCouponTable()->get();
//        $playCashCoupon = array(
//            array('id'=>1,'title'=>'票券1'),
//            array('id'=>2,'title'=>'票券2'),
//            array('id'=>3,'title'=>'票券3'),
//        );
        return array(
//            'playCashCoupon' => $playCashCoupon,
            'data' => $this->rule,
        );
    }

    /**
     * 邀约ｌｏｇ日志
     */
    public function logAction(){
        $page = (int)$this->getQuery('p', 1);
        $city = $this->getAdminCity();

        $pageSum = 10;
        $where = "invite_rule_log.city = '{$city}' ";

        $start = ($page - 1) * $pageSum;

        $db = $this->_getAdapter();

        $sql = "SELECT * FROM invite_rule_log WHERE $where ORDER BY invite_rule_log.id DESC LIMIT {$start}, {$pageSum}";
        $data = $db->query($sql,array())->toArray();

        $sql_count = "SELECT * FROM invite_rule_log WHERE $where";
        $count = $db->query($sql_count,array())->count();

        $url = '/wftadlogin/invite/log';
        $paging = new Paginator($page, $count, $pageSum, $url);

        return array(
            'data' => $data,
            'pageData' => $paging->getHtml(),
        );
    }

    public function logdetailAction(){
        $id = $this->getQuery('id',0);

        $detail = $this->_getInviteRuleLog()->getLogById($id);

        $city = $this->getAdminCity();

        if($detail->city != $city){
            $detail = [];
        }

        return  array(
            'data' => $detail,
        );


    }

    /**
     *邀约后台保存新设置
     *
     *
     */
    public function saveAction(){
        $post = (array)$this->getPost();
        if(!empty($post['file']) || isset($post['file'])){
            unset($post['file']);
        }

        if($post['r_inviter_type'] == 1) {
            if (!empty($post['r_inviter_couponid']) && isset($post['r_inviter_couponid'])) {
                $r_invite_res = json_decode($this->couponCheck($post['r_inviter_couponid']),true);
                if($r_invite_res['status']){
                    $r_invite_res = $r_invite_res['data'];
                    $post['r_inviter_couponid'] = json_encode($r_invite_res[0]);
//                    echo(json_encode($post['r_inviter_couponid']));exit;
                    $post['r_inviter_award'] = $r_invite_res[1];
                }else{
                    return $this->_Goto($r_invite_res['data']);
                    $post['r_inviter_couponid'] = null;
                }
            } else {
                $post['r_inviter_couponid'] = null;
            }
        } else {
            $post['r_inviter_couponid'] = null;
        }
        if($post['r_reciever_type'] == 1) {
            if (!empty($post['r_reciever_couponid']) && isset($post['r_reciever_couponid'])) {
                $r_reciever_res = json_decode($this->couponCheck($post['r_reciever_couponid']),true);
//                var_dump($r_invite_res);exit;
                if($r_reciever_res['status']){
                    $r_reciever_res = $r_reciever_res['data'];
//                    var_dump($r_invite_res);exit;
                    $post['r_reciever_couponid'] = json_encode($r_reciever_res[0]);
                    $post['r_reciever_award'] = $r_reciever_res[1];
                }else{
                    return $this->_Goto($r_reciever_res['data']);
                    $post['r_reciever_couponid'] = null;
                }
            } else {
                $post['r_reciever_couponid'] = null;
            }
        } else {
            $post['r_reciever_couponid'] = null;
        }
        if($post['d_reciever_type'] == 1) {
            if (!empty($post['d_reciever_couponid']) && isset($post['d_reciever_couponid'])) {
                $d_reciever_res = json_decode($this->couponCheck($post['d_reciever_couponid']),true);
//                var_dump($r_invite_res);exit;
                if($d_reciever_res){
                    $d_reciever_res = $d_reciever_res['data'];
//                    var_dump($r_invite_res);exit;
                    $post['d_reciever_couponid'] = json_encode($d_reciever_res[0]);
                    $post['d_reciever_award'] = $d_reciever_res[1];
                }else{
                    return $this->_Goto($d_reciever_res['data']);
                    $post['d_reciever_couponid'] = null;
                }
            } else {
                $post['d_reciever_couponid'] = null;
            }
        } else {
            $post['d_reciever_couponid'] = null;
        }
//        var_dump($post);exit;
        //extract($post);//将所传值赋予对应的键名数组
        if($post){
            $where = ['city' => $this->city];
            $exist = $this->_getInviteRule()->getByRuleId($this->city);
            if($exist) {
                $data = $this->_getInviteRule()->update($post, $where);
                $post['create_time'] = time();//规则日志
                $post['ruleid'] = $exist->ruleid;
                $post['city'] = $this->city;
                $this->_getInviteRuleLog()->insert($post);
            }else{
                $post['city'] = $this->city;
                $data = $this->_getInviteRule()->insert($post);
            }
        }else{
            return $this->_Goto('成功');
        }
        return $this->_Goto(isset($data) ? '成功' : '失败');

    }

    private function couponCheck($post_couponid){
        $post = [];
//        if($post[$act_type.'_'.$role_type.'_type'] == 1) {
//            if (!empty($post[$act_type.'_'.$role_type.'_couponid']) && isset($post[$act_type.'_'.$role_type.'_couponid'])) {
                //增加判断票券有效，并计算组合总值
                $i = 0;
                $award = 0;
                foreach ($post_couponid as $couponids) {
                    $couponid_arr = explode(',', $couponids);
                    foreach ($couponid_arr as $couponid) {
                        $res = $this->_getCashCouponTable()->get(['id' => $couponid]);
                        if (!$res) {
                            return json_encode(['status' => 0, 'data' => '票券' . $couponid . '不存在']);
                            return $this->_Goto('票券' . $couponid . '不存在');
                        }
                        if ($res->end_time <= time()) {
                            return json_encode(['status' => 0, 'data' => '票券' . $couponid . '已下架']);
                            return $this->_Goto('票券' . $couponid . '已下架');
                        }
                        if ($res->status == 0) {
                            return json_encode(['status' => 0, 'data' => '票券' . $couponid . '未审核']);
                            return $this->_Goto('票券' . $couponid . '未审核');
                        }
                        if ($res->isclose == 1) {
                            return json_encode(['status' => 0, 'data' => '票券' . $couponid . '已停止发放']);
                            return $this->_Goto('票券' . $couponid . '已停止发放');
                        }
                        if ($i == 0) {
                            $award += $res->price;
                        }
                    }
                    $i++;
                }
//                $couponid_json = json_encode($post_couponid);
//                $post[$act_type.'_'.$role_type.'_award'] = $award;
                return json_encode(['status' => 1,'data'=>[$post_couponid,$award]]);
        /*} else {
            $post[$act_type.'_'.$role_type.'_couponid'] = null;
            return null;
        }
    } else {
        $post[$act_type.'_'.$role_type.'_couponid'] = null;
        return null;
    }*/
    }

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////





    /*************  首页内容管理 *************/
    public function blockAction()
    {
        $page = (int)$this->getQuery('p', 1);
        $city = $this->getQuery('city', 'WH');
        $type = $this->getQuery('type', 1);

        if ($type == 1) {
            $pagesum = 10;
            $where = array(
                'block_city = ?' => $city,
            );
            $start = ($page - 1) * $pagesum;
            $order = array('block_order' => 'desc', 'dateline' => 'desc');

            $blockData = $this->_getPlayIndexBlockTable()->getAdminIndexBlockList($start, $pagesum, array(), $where,
                $order);
            //获得总数量
            $count = $this->_getPlayIndexBlockTable()->getAdminIndexBlockList(0, 0, array(), $where, $order)->count();
            //创建分页
            $url = '/wftadlogin/setting/block';
            $paginator = new Paginator($page, $count, $pagesum, $url);
            $data = array();
            $mid = '';
            $time = time();
            foreach ($blockData as $val) {
                if ($val->type == 1) {//专题
                    $vData = $this->_getPlayActivityTable()->getActivityList(0, 1, array(),
                        array('play_activity.id' => $val->link_id))->current();
                    $title = $vData->ac_name;
                    $m_status = ($vData->status >= 0 && (($vData->s_time < $time && $vData->e_time > $time) || ($vData->s_time == 0 && $vData->e_time == 0))) ? 1 : 0;
                } elseif ($val->type == 2) {//卡券
                    $vData = $this->_getPlayCouponsTable()->getCouponsList(0, 1, array(),
                        array('play_coupons.coupon_id' => $val->link_id))->current();
                    $title = $vData->coupon_name;
                    $mid = $vData->coupon_marketid;
                    $m_status = ($vData->coupon_status == 1 && $vData->coupon_uptime <= $time && $vData->coupon_starttime <= $time && $vData->coupon_endtime >= $time && $vData->coupon_total > $vData->coupon_buy) ? 1 : 0;
                } elseif ($val->type == 3) {//资讯
                    $vData = $this->_getPlayNewsTable()->getAdminNewsList(0, 1, array(),
                        array('play_news.id' => $val->link_id))->current();
                    $title = $vData->title;
                    $m_status = $vData->status == 1 ? 1 : 0;
                }
                $data[] = array(
                    'id' => $val->id,
                    'title' => $title,
                    'name' => $vData->admin_name,
                    'post_num' => $vData->post_number,
                    'type' => $val->type,
                    'block_order' => $val->block_order,
                    'link_id' => $val->link_id,
                    'mid' => $mid,
                    'city' => $val->block_city,
                    'flag' => $m_status,
                );
            }

            return array(
                'data' => $data,
                'pagedata' => $paginator->getHtml(),
                'cityData' => $this->_getConfig()['city'],
                'city' => $city,
            );
        } elseif ($type == 2) {
            $bid = (int)$this->getQuery('bid', 0);
            $this->_getPlayIndexBlockTable()->delete(array('id' => $bid));

            return $this->_Goto('成功');
        } elseif ($type == 3) {
            $oid = (int)$this->getQuery('oid', 1);
            $bid = (int)$this->getQuery('bid', 0);
            if ($oid > 2000) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '排序值太大'));
            }
            $stu = $this->_getPlayIndexBlockTable()->update(array('block_order' => $oid), array('id' => $bid));
            if ($stu) {
                return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));
            } else {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '没有更新排序值'));
            }
        } elseif ($type == 4) {
            $link_id = (int)$this->getQuery('lid', 0);
            $type = (int)$this->getQuery('t', 0);
            $block_city = $this->getQuery('city', 'WH');
            $data = $this->_getPlayIndexBlockTable()->get(array(
                'link_id' => $link_id,
                'type' => $type,
                'block_city' => $block_city,
            ));

            if ($data) {
                $flag = 1;
            } else {
                $flag = 0;
            }
            if ($type == 1) {//专题
                $linkData = $this->_getPlayActivityTable()->getActivityList(0, 1, array(),
                    array('play_activity.id' => $link_id))->current();
                $image = $linkData->image;
            } elseif ($type == 2) {
                $linkData = $this->_getPlayCouponsTable()->getCouponsList(0, 1, array(),
                    array('play_coupons.coupon_id' => $link_id))->current();
                $image = $linkData->image;
            } elseif ($type == 3) {
                $linkData = $this->_getPlayNewsTable()->getAdminNewsList(0, 1, array(),
                    array('play_news.id' => $link_id))->current();
                $image = $linkData->image;
            } elseif ($type == 4) {
                $linkData = $this->_getPlayShopTable()->getAdminShopList(0, 1, array(),
                    array('play_shop.shop_id' => $link_id))->current();
                $image = $linkData->image;
            } else {
                return $this->_Goto('非法操作');
            }
            if (!$flag) {
                $status = $this->_getPlayIndexBlockTable()->insert(array(
                    'link_id' => $link_id,
                    'type' => $type,
                    'block_city' => $block_city,
                    'dateline' => time(),
                    'editor_image' => $image,
                ));
            } else {
                $status = $this->_getPlayIndexBlockTable()->update(array(
                    'link_id' => $link_id,
                    'type' => $type,
                    'block_city' => $block_city,
                    'dateline' => time(),
                    'editor_image' => $image,
                ), array('link_id' => $link_id, 'type' => $type, 'block_city' => $block_city,));
            }

            if ($status) {
                return $this->_Goto('成功');
            } else {
                return $this->_Goto('失败');
            }

        }

    }
    /*************  首页内容管理end *************/


    /*************  专题标签管理 *************/
    public function activityTagAction()
    {
        $type = $this->getQuery('type', 1);

        if ($type == 1) { //专题标签列表
            $data = $this->_getPlayActivityTagTable()->fetchAll();
            $vm = new viewModel(
                array(
                    'data' => $data,
                )
            );

            return $vm;
        }

        if ($type == 2) { //专题标签删除
            $id = (int)$this->getQuery('tid');
            $status = $this->_getPlayActivityTagTable()->delete(array('id' => $id));
            if ($status) {
                return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));
            } else {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '失败'));
            }

        }

        if ($type == 3) { //专题标签添加
            $tagname = $this->getPost('tagname', 0);
            $tag = $this->_getPlayActivityTagTable()->get(array('tagname' => $tagname));
            if (!trim($tagname) || $tag) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '标签名称重复 或无'));
            }
            $status = $this->_getPlayActivityTagTable()->insert(array('tagname' => $tagname));
            if ($status) {
                return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));
            } else {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '失败'));
            }
        }

        return $this->_Goto('非法操作');

    }
    /*************  专题标签管理end   *************/

    /*************  活动分类管理 *************/
    public function gameTagAction()
    {
        $type = $this->getQuery('type', 1);

        if ($type == 1) { //专题标签列表
            $data = $this->_getPlayGameTagTable()->fetchAll();
            $vm = new viewModel(
                array(
                    'data' => $data,
                )
            );

            return $vm;
        }

        if ($type == 2) { //活动分类删除
            $id = (int)$this->getQuery('tid');
            $status = $this->_getPlayGameTagTable()->delete(array('id' => $id));
            if ($status) {
                return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));
            } else {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '失败'));
            }

        }

        if ($type == 3) { //活动分类添加
            $tagname = $this->getPost('tagname', 0);
            $tag = $this->_getPlayGameTagTable()->get(array('gameTag' => $tagname));
            if (!trim($tagname) || $tag) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '名称重复 或无'));
            }
            $status = $this->_getPlayGameTagTable()->insert(array('gameTag' => $tagname));
            if ($status) {
                return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));
            } else {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '失败'));
            }
        }

        return $this->_Goto('非法操作');

    }
    /*************  活动分类管理end   *************/

    /*************  活动属性管理 *************/
    public function gameAttributeAction()
    {
        $type = $this->getQuery('type', 1);

        if ($type == 1) {
            $data = $this->_getPlayGameAttributeTable()->fetchAll();
            $vm = new viewModel(
                array(
                    'data' => $data,
                )
            );

            return $vm;
        }

        if ($type == 2) { //活动属性删除
            $id = (int)$this->getQuery('tid');
            $status = $this->_getPlayGameAttributeTable()->delete(array('id' => $id));
            if ($status) {
                return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));
            } else {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '失败'));
            }

        }

        if ($type == 3) { //活动属性添加
            $attribute_name = $this->getPost('attribute_name', 0);
            $flag = $this->_getPlayGameAttributeTable()->get(array('good_attribute_name' => $attribute_name));
            if (!trim($attribute_name) || $flag) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '名称重复 或无'));
            }
            $status = $this->_getPlayGameAttributeTable()->insert(array('good_attribute_name' => $attribute_name));
            if ($status) {
                return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));
            } else {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '失败'));
            }
        }

        return $this->_Goto('非法操作');

    }

    /*************  活动属性管理end   *************/


    public function accountnewAction()
    {
        $city = $this->_getPlayCityTable()->fetchAll(['is_close' => 0]);

        $groups = $this->_getAuthGroupTable()->fetchAll(['status' => 1]);

        return array('group' => $groups, 'cityData' => $city);
    }

    public function editorAction()
    {
        $type = $this->getQuery('type', 1);
        $city = $this->_getPlayCityTable()->fetchAll(['is_close' => 0]);
        $group_id = $this->getQuery('group', 0);
        $city_id = $this->getQuery('city', 0);
        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;
        $cityData = [];
        foreach($city as $c){
            $cityData[$c['city']] = $c['city_name'];
        }

        $groups = $this->_getAuthGroupTable()->fetchAll(['status' => 1]);
        $where = [];
        if ($group_id) {
            $where['group'] = $group_id;
        }

        if ($city_id) {
            $where['admin_city'] = $group_id;
        }

        $data =  $this->_getPlayAdminTable()->fetchLimit($start, $pageSum, array(),$where);
        //获得总数量
        $count = $this->_getPlayAdminTable()->fetchCount($where);
        //创建分页
        $url = '/wftadlogin/setting/editor';
        $pagination = new Paginator($page, $count, $pageSum, $url);

        return $vm = new viewModel(
            array(
                'data' => $data,
                'group'=>$groups,
                'cityData' => $cityData,
                'pageData' => $pagination->getHtml(),
            )
        );

    }

    /*************  小编管理 *************/
    public function editorsaveAction()
    {
        $type = $this->getQuery('type', 1);
        if ($type == 1) {
            if ($this->getQuery('cai', 0)) {
                $where = array(
                    'status > ?' => 0,
                    'group' => 4
                );
            } else {
                $where = array(
                    'status > ?' => 0,
                    'group' => 3
                );
            }
            $data = $this->_getPlayAdminTable()->fetchAll($where);

            return $vm = new viewModel(
                array(
                    'data' => $data,
                    'cityData' => $this->_getConfig()['city'],
                )
            );

        } elseif ($type == 2) {
            $image = $this->getPost('image');
            $admin_name = $this->getPost('admin_name');
            $password = $this->getPost('password');
            $phone = $this->getPost('phone');
            $idnum = $this->getPost('idnum');

            $admin_city = $this->getPost('city');
            $group = (int)$this->getPost('group');
            if (!$image || !$admin_name || !$password) {
                return $this->_Goto('非法操作');
            }
            $str = md5(uniqid('', true));
            $salt = substr($str, -6);
            $password = md5($password . $salt);

            $oldAdmin = $this->_getPlayAdminTable()->get(array(
                'admin_name' => $admin_name,
                'admin_city' => $admin_city,
                'group' => $group
            ));
            if ($oldAdmin) {
                $this->_getPlayAdminTable()->update(array(
                    'status' => 1,
                    'image' => $image,
                    'admin_name' => $admin_name,
                    'password' => md5($password),
                    'dateline' => time()
                ), array('admin_name' => $admin_name, 'group' => $group, 'admin_city' => $admin_city));

                return $this->_Goto('该用户名已经注册, 已更新密码 图像');
            }
            $status = $this->_getPlayAdminTable()->insert(array(
                'admin_city' => $admin_city,
                'admin_name' => $admin_name,
                'image' => $image,
                'password' => md5($password),
                'dateline' => time(),
                'group' => $group,
                'shop_id' => 0,
                'phone' => $phone,
                'idnum' => $idnum,
                'status' => 1
            ));
            if ($status) {
                return $this->_Goto('成功');
            } else {
                return $this->_Goto('失败');
            }

        } elseif ($type == 3) {
            $id = $this->getQuery('id', 0);
            if ($id) {
                $status = $this->_getPlayAdminTable()->update(array('status' => 0), array('id' => $id));
                if ($status) {
                    return $this->_Goto('成功');
                } else {
                    return $this->_Goto('失败');
                }
            }

            return $this->_Goto('失败');
        }

    }

    /*************  小编管理end   *************/

    //绑定用户
    public function binduserAction()
    {

        $uid = $this->params()->fromPost('uid');
        $bind_user = $this->params()->fromPost('bind_uid');
        $user = $this->_getPlayUserTable()->get(array('uid' => $bind_user));
        if (!$user) {
            return $this->jsonResponsePage(array('status' => 0));
        }
        $status = $this->_getPlayAdminTable()->update(array('bind_user_id' => $bind_user), array('id' => $uid));
        if ($status) {
            return $this->jsonResponsePage(array('status' => 1));
        } else {
            return $this->jsonResponsePage(array('status' => 0));
        }
    }

    /*************  二级商圈 *************/
    public function circleAction()
    {

        $cy = $this->getQuery('acr', 'WH');//城市缩写
        $type = $this->getQuery('data-type');
        $rdata = $this->_getPlayRegionTable()->fetchAll(array('acr' => $cy), array('rid' => 'asc'))->toArray();
        $body = array();
        foreach ($rdata as $v) {
            if (substr((string)$v['rid'], -2, 2) == '00') {
                $body[] = $v;
            }
        }

        if ($type == 'json') {
            return $this->jsonResponsePage($body);
        }

        return array(
            'city' => $this->_getConfig()['city'],
            'menu1' => $body,
            'acr' => $cy
        );
    }

    //获取二级商圈
    public function getcircleAction()
    {
        $cy = $this->getQuery('acr', 'WH');//城市缩写
        $rid = (int)$this->getQuery('rid');
        $ridmax = $rid + 100;
        $rdata = $this->_getPlayRegionTable()->fetchAll(array('acr' => $cy, "rid>{$rid} and rid<{$ridmax}"),
            array('rid' => 'asc'))->toArray();

        return $this->jsonResponsePage($rdata);
    }

    public function addselect1Action()
    {
        $acr = $this->getQuery('acr', 'WH');//城市缩写
        $addname = $this->getQuery('addname');
        $last = $this->_getPlayRegionTable()->fetchAll(array(), array('rid' => 'desc'))->current();
        if (!$last) {
            $rid = 42011100;
        } else {
            $rid = floor(($last->rid + 100) / 100) * 100;
        }
        $status = $this->_getPlayRegionTable()->insert(array('acr' => $acr, 'rid' => $rid, 'name' => $addname));
        if ($status) {
            return $this->jsonResponsePage(array('status' => 1, 'rid' => $rid, 'name' => $addname));
        } else {
            exit;
        }

    }

    public function addselect2Action()
    {
        $acr = $this->getQuery('acr', 'WH');//城市缩写
        $addname = $this->getQuery('addname');
        $rid = (int)$this->getQuery('rid');

        $ridmax = $rid + 100;
        $last = $this->_getPlayRegionTable()->fetchAll(array("rid>={$rid} and rid<{$ridmax}"),
            array('rid' => 'desc'))->current();
        $rid = $last->rid + 1;
        $status = $this->_getPlayRegionTable()->insert(array('acr' => $acr, 'rid' => $rid, 'name' => $addname));
        if ($status) {
            return $this->jsonResponsePage(array('status' => 1, 'rid' => $rid, 'name' => $addname));
        } else {
            exit;
        }

    }

    public function deleteAction()
    {
        $rid = (int)$this->getQuery('rid');
        if ($rid % 100) {
            $status = $this->_getPlayRegionTable()->delete(array('rid' => $rid));
        } else {
            $ridmax = $rid + 100;
            $status = $this->_getPlayRegionTable()->delete(array("rid>={$rid} and rid<{$ridmax}"));
        }
        if ($status) {
            return $this->jsonResponsePage(array('status' => 1, 'rid' => $rid));
        }

    }
    /*************  二级商圈end   *************/

    //订单回收 已停用
    public function retrieveAction()
    {
        $time = time() - 1800;  //30分钟

        $data = $this->_getPlayOrderInfoTable()->fetchAll(array(
            'order_status' => 1,
            'pay_status<=1',
            'dateline<' . $time
        ));

        $count = 0;
        foreach ($data as $v) {
            $status = $this->_getPlayOrderInfoTable()->update(array('order_status' => 0),
                array('order_sn' => $v->order_sn));
            if ($status) {
                $c = $this->_getPlayCouponsTable()->update(array('coupon_buy' => new Expression('coupon_buy-' . $v->buy_number)),
                    array('coupon_id' => $v->coupon_id));
                if ($c) {
                    $count += $c;
                } else {
                    $this->_getPlayOrderInfoTable()->update(array('order_status' => 1),
                        array('order_sn' => $v->order_sn));
                }
            }
        }

        return $this->_Goto("成功回收{$count}个过期订单");
    }

    //添加搜索关键字
    public function searchkeyAction()
    {

        $action = $this->params()->fromQuery('action');


        //todo 添加关键字
        if ($this->getRequest()->isPost()) {
            $val = $this->params()->fromPost('val');
            $type = $this->params()->fromPost('type');
            $status = false;
            if ($val) {
                $status = $this->_getPlaySearchFormValueTable()->insert(array(
                    'val' => $val,
                    'dateline' => time(),
                    'status' => 1,
                    'search_type' => $type,
                ));
            }
            if ($status) {
                return $this->_Goto('添加成功');
            } else {
                return $this->_Goto('添加失败');
            }
        }


        //todo 设为过期
        if ($action == 'out') {
            $id = $this->params()->fromQuery('id');
            $status = $this->_getPlaySearchFormValueTable()->update(array('status' => 0), array('id' => $id));
            if ($status) {
                return $this->_Goto('操作成功');
            }
        }

        $row = 10;
        $page = $this->params()->fromQuery('p', 1);
        $type = (int)$this->getQuery('type');
        $searchWhere = array();
        if ($type) {
            $searchWhere['search_type'] = $type;
        }


        if ($type) {
            $where = "play_search_form_value.search_type = {$type}";
        } else {
            $where = "play_search_form_value.search_type >= 0";
        }
        $start = ($page - 1) * $row;


        $sql = "SELECT
play_search_form_value.id,
play_search_form_value.dateline,
play_search_form_value.val,
play_search_form_value.search_type,
play_search_form_value.`status`,
COUNT(DISTINCT play_search_log.id)AS click_num
FROM
play_search_form_value
LEFT JOIN play_search_log ON play_search_form_value.val = play_search_log.`key`
WHERE
$where
GROUP BY
play_search_form_value.id
ORDER BY
play_search_form_value.`status` DESC , play_search_form_value.dateline DESC
LIMIT {$start}, {$row}
";
        $data = $this->query($sql);

        $sql_count = "SELECT
play_search_form_value.id
FROM
play_search_form_value
LEFT JOIN play_search_log ON play_search_form_value.val = play_search_log.`key`
WHERE
$where
GROUP BY
play_search_form_value.id
";
        $count = $this->query($sql_count)->count();
        $url = '/wftadlogin/setting/searchkey';
        $paginator = new Paginator($page, $count, $row, $url);

        return array('data' => $data, 'page' => $paginator->getHtml());
    }

    //启动图设置
    public function installAction()
    {

        $act = $this->getQuery('act');
        $id = (int)$this->getQuery('id');

        if ($act) {
            if ($act == 'update') {
                $status = $this->_getPlayAttachTable()->update(array('dateline' => time()), array('id' => $id));

                return $this->_Goto($status ? '成功' : '失败');
            }

            if ($act == 'del') {
                $status = $this->_getPlayAttachTable()->update(array('use_id' => 1), array('id' => $id));

                return $this->_Goto($status ? '成功' : '失败');
            }

            if ($act == 'view') {
                $img_data = $this->_getPlayAttachTable()->get(array('id' => $id, 'use_type' => 'firing'));

                $imgs = json_decode($img_data->url);
                if (count($imgs)) {
                    foreach ($imgs as $img) {
                        echo '<img src="' . $this->_getConfig()['url'] . $img . '"> <br />';
                    }
                }

                exit;
            }

            if ($act == 'save') {
                $cover1 = $this->getPost('cover1');
                $cover2 = $this->getPost('cover2');
                $name = $this->getPost('val');
                $height = strtotime($this->getPost('height') . $this->getPost('heightl')); //
                $width = strtotime($this->getPost('width') . $this->getPost('widthl')); //

                if (!$cover1 || !$cover2 || !$name) {
                    return $this->_Goto('参数');
                }

                if ($width > $height) {
                    return $this->_Goto('时间错了');
                }

                $cover1_class = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $cover1);
                $cover1_status = $cover1_class->scaleResizeImage(640, 960);
                if ($cover1_status) {
                    $cover1_status->save($_SERVER['DOCUMENT_ROOT'] . $cover1);
                }

                $cover2_class = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $cover2);
                $cover2_status = $cover2_class->scaleResizeImage(720, 1280);
                if ($cover2_status) {
                    $cover2_status->save($_SERVER['DOCUMENT_ROOT'] . $cover2);
                }

                $sta = $this->_getPlayAttachTable()->insert(array(
                        'uid' => 2,
                        'use_id' => 2,
                        'use_type' => 'firing',
                        'dateline' => time(),
                        'url' => json_encode(array($cover1, $cover2)),
                        'is_remote' => 0,
                        'name' => $name,
                        'height' => $height,
                        'width' => $width
                    )
                );

                return $this->_Goto($sta ? '成功' : '失败');
            }

        }

        $data = $this->_getPlayAttachTable()->fetchLimit(0, 20, array(), array('use_type' => 'firing', 'use_id' => 2),
            array('dateline' => 'desc'));


        return array(
            'data' => $data,
        );

    }

    //
    public function marketingAction()
    {
        $data = $this->_getPlayMarketSettingTable()->get(['id' => 1]);

        return array(
            'data' => $data,
        );
    }

    public function marketsaveAction()
    {
        $data = $this->getPost();
        $status = $this->_getPlayMarketSettingTable()->update($data, ['id' => 1]);

        return $this->_Goto($status ? '成功' : '失败');
    }

    public function invitecontentAction()
    {
        $data = $this->_getPlayInviteContentTable()->get(['id' => 1]);

        return array(
            'data' => $data,
        );
    }

    //约稿
    public function invitecontentsaveAction()
    {
        $data = $this->getPost();
        $data['content'] = $data['editorValue'];
        unset($data['editorValue']);
        $status = $this->_getPlayInviteContentTable()->update($data, ['id' => 1]);

        return $this->_Goto($status ? '成功' : '失败');
    }

    //好评有礼
    public function goodcommentAction()
    {
        $data = $this->_getPlayGoodCommentTable()->get(['id' => 1]);

        return array(
            'data' => $data,
        );
    }

    public function goodcommentsaveAction()
    {
        $data = $this->getPost();

        $status = $this->_getPlayGoodCommentTable()->update($data, ['id' => 1]);

        return $this->_Goto($status ? '成功' : '失败');
    }

    public function taskAction(){
        $data = $this->_getPlayTaskIntegralTable()->get(['id' => 1]);

        return array(
            'data' => $data,
        );
    }

    public function tasksaveAction(){
        $data = $this->getPost();

        $status = $this->_getPlayTaskIntegralTable()->update($data, ['id' => 1]);

        return $this->_Goto($status ? '成功' : '失败');
    }

    /**
     * @param $sql
     * @return Result;
     */
    function query($sql)
    {
        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $stmt = $db->query($sql);
        $result = $stmt->execute($stmt);

        return $result;
    }


}