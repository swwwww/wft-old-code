<?php

namespace Admin\Controller;

use Deyi\BaseController;
use Deyi\Coupon\Coupon;
use Deyi\GetCacheData\CityCache;
use Deyi\GetCacheData\GoodCache;
use Deyi\Integral\Integral;
use Deyi\Invite\Invite;
use Deyi\JsonResponse;
use Deyi\Paginator;
use Deyi\ImageProcessing;
use Deyi\PY;
use library\Service\Admin\Setting\Banner;
use library\Service\Admin\Setting\Share;
use library\Service\System\Cache\RedCache;
use WebActivity\Model\Account;
use Zend\Console\ColorInterface;
use Zend\Db\Sql\Predicate\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class SettingController extends BasisController
{
    use JsonResponse;


    public function indexAction()
    {

    }

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

        $city = $this->getAllCities();

        if ($this->getAdminCity() != 1) {
            $city = array($this->getAdminCity() => $city[$this->getAdminCity()]);
        }else{
            $city = $this->getAllCities(1);
        }

        $groups = $this->getAllGroup();

        $id = $this->getQuery('id', 0);

        $admin = [];
        if ($id) {
            $admin = $this->_getPlayAdminTable()->get(array('id' => $id));
        }

        return array('group' => $groups, 'cityData' => $city, 'admin' => $admin);
    }

    public function editorAction()
    {
        $type = $this->getQuery('type', 1);
        $cityData = $this->getAllCities();
        $group_id = $this->getQuery('group_id', 0);
        $city_id = $this->chooseCity();
        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;
        $user = $this->getQuery('user', '');

        $where = [];
        if ($group_id) {
            $where['group'] = $group_id;
        }
        if ($user) {
            $where['admin_name like ?'] = '%' . $user . '%';
        }

        if ($city_id) {
            $where['admin_city'] = $city_id;
        }
        $groups = $this->getAllGroup();

        $data = $this->_getPlayAdminTable()->fetchLimit($start, $pageSum, array(), $where, ['id' => 'DESC']);
        //获得总数量
        $count = $this->_getPlayAdminTable()->fetchCount($where);
        //创建分页
        $url = '/wftadlogin/setting/editor';
        $pagination = new Paginator($page, $count, $pageSum, $url);

        $citys = $this->getAllCities();

        return $vm = new viewModel(
            array(
                'data' => $data,
                'group' => $groups,
                'cityData' => $cityData,
                'pageData' => $pagination->getHtml(),
                'city_select' => $citys,
                'filtercity' => CityCache::getFilterCity($city_id,2),
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
                    'cityData' => CityCache::getCities(),
                )
            );

        } elseif ($type == 2) {
            $image = $this->getPost('image');
            $admin_name = $this->getPost('admin_name');
            $password = trim($this->getPost('password'));
            $phone = $this->getPost('phone');
            $idnum = $this->getPost('idnum');

            $admin_city = $this->getPost('city');
            $group = (int)$this->getPost('group');
            if (!$image || !$admin_name || !$password) {
                return $this->_Goto('非法操作');
            }
            $str = md5(uniqid('', true));
            $salt = substr($str, -6);
            $password = md5($password);
            $password = md5($password . $salt);

            $oldAdmin = $this->_getPlayAdminTable()->get(array(
                'admin_name' => $admin_name,
                'admin_city' => $admin_city,
                'group' => $group
            ));
            if ($oldAdmin) {
                $up = array(
                    'status' => 1,
                    'image' => $image,
                    'admin_name' => $admin_name,
                    'password' => $password,
                    'dateline' => time()
                );

                if (!$image) {
                    unset($up['image']);
                }
                $this->_getPlayAdminTable()->update($up,
                    array('admin_name' => $admin_name, 'group' => $group, 'admin_city' => $admin_city));

                return $this->_Goto('该用户名已经注册, 已更新密码 图像');
            }

            $status = $this->_getPlayAdminTable()->insert(array(
                'admin_city' => $admin_city,
                'admin_name' => $admin_name,
                'image' => $image,
                'password' => $password,
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


    /**
     * 添加保存管理员
     */
    public function saveAction()
    {
        $image = $this->getPost('image');
        $admin_name = $this->getPost('admin_name');
        $password = $this->getPost('password');
        $phone = $this->getPost('phone');
        $idnum = $this->getPost('idnum');
        $limit = (int)$this->getPost('limit');
        $id = (int)$this->getPost('id', 0);
        $admin_city = $this->getPost('city');
        $group = (int)$this->getPost('group');

        $ispassword = $password;

        $password = md5($password);

        $oldAdmin = $this->_getPlayAdminTable()->get(array(
            'admin_name' => $admin_name,
            'admin_city' => $admin_city,
//            'group' => $group
        ));

        if ($oldAdmin && 0 == (int)$id) {
            return $this->_Goto('当前站点存在同名用户，请换一个用户名');
        }

        if($this->getAdminCity()!=1){
            $admin_city = $this->getAdminCity();
        }
        $admin_city=$admin_city?:$this->getAdminCity();
        if ($id) {
            $salt = $oldAdmin->salt;
            $update = array(
                'status' => 1,
                'password' => md5($password . $salt),
                'salt' => $salt,
                'phone' => $phone,
                'idnum' => $idnum,
                'limit' => $limit,
                'admin_name' => $admin_name,
                'group' => $group,
                'dateline' => time()
            );
            if (!$ispassword) {
                unset($update['password']);
            }
            if (!$admin_name){
                unset($update['admin_name']);
            }
            if ($image) {
                $update['image'] = $image;
            }

            $this->_getPlayAdminTable()->update($update,
                array('id' => $id, 'admin_city' => (string)$admin_city));

            return $this->_Goto('已成功更新');
        }
        $str = md5(uniqid('', true));
        $salt = substr($str, -6);
        $password = md5($password . $salt);
        if (!$image) {
            return $this->_Goto('非法操作,没有上传图片');
        }
        if (!$admin_name || !$ispassword) {
            return $this->_Goto('非法操作，用户名或密码为空');
        }
        $status = $this->_getPlayAdminTable()->insert(array(
            'admin_city' => $admin_city,
            'admin_name' => $admin_name,
            'image' => $image,
            'password' => $password,
            'dateline' => time(),
            'group' => $group,
            'shop_id' => 0,
            'salt' => $salt,
            'limit' => $limit,
            'phone' => $phone,
            'idnum' => $idnum,
            'status' => 1
        ));
        if ($status) {
            return $this->_Goto('成功');
        } else {
            return $this->_Goto('失败');
        }
    }

    public function editorcloseAction()
    {
        $id = (int)$this->getQuery('id');
        $isclose = (int)$this->getQuery('isclose');
        $status = $this->_getPlayAdminTable()->update(['is_closed' => $isclose], ['id' => $id]);

        return $this->_Goto($status ? '成功' : '失败', '/wftadlogin/setting/editor');
    }

    /*************  小编管理end   *************/

    //绑定用户
    public function binduserAction()
    {

        $uid = $this->params()->fromPost('uid');
        $bind_user = $this->params()->fromPost('bind_uid');
        $user = $this->_getPlayUserTable()->get(array('uid' => $bind_user));
        if (!$user) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '绑定uid无效'));
        }
        $status = $this->_getPlayAdminTable()->update(array('bind_user_id' => $bind_user), array('id' => $uid));
        if ($status) {
            return $this->jsonResponsePage(array('status' => 1));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '绑定失败！'));
        }
    }

    public function resetintegralAction(){
        exit;
        $id = (int)$this->getQuery('id', 0);
        $lx = $this->getQuery('lx', 0);
        $inte = new Integral();

        if($lx !== 'feee4bbd9b0ad243e141712352f21b727a19a2ef'){
            echo 'lxerror';
            return false;
        }

        $cc = $this->_getPlayCouponCodeTable()->fetchLimit(0,1, [],['id > ?' => $id,'status'=>1],['id'=>'asc'])->current();

        if(!$cc){
            echo '没有cc';
            return false;
        }


        $order_data = $this->_getPlayOrderInfoTable()->getUserBuy(array('order_sn' => $cc->order_sn));

        if(!$order_data){
            return false;
            $id = $cc->id;
            //echo '<meta http-equiv="refresh" content="2;url=/wftadlogin/setting/resetintegral?id='.$id.'&lx='.$lx.'">';
        }

        if(!$order_data->group_buy_id){

            $score = $inte->useGood3($order_data->user_id, $order_data->coupon_id, $order_data->coupon_unit_price, $order_data->order_city,$order_data->coupon_name,$order_data->order_sn,$cc->use_datetime);
            var_dump($score);
        }


        if($lx){
            $id = $cc->id;
            //echo '<meta http-equiv="refresh" content="2;url=/wftadlogin/setting/resetintegral?id='.$id.'&lx=1">';
        }

        exit;


    }

    /**
     * 站点管理
     * @return array
     */
    public function stationAction(){

        return false;
        $adapter = $this->_getAdapter();//111010000
        $sql = "select * from play_region where rid like '11101%' and rid like '%00' and rid not like '%0000' ;";
        $area = $adapter->query($sql, array())->toArray();

        foreach($area as $a){
            $str = $a['brid'];
            $str =  rtrim($str, "00");
            if(!$str){
                continue;
            }
            $sql = "select * from play_region_bak where rid like '{$str}%' and rid not like '%00';";
            $strees = $adapter->query($sql, array())->toArray();
            $n = 0;
            foreach($strees as $s){
                $n++;
                $data['rid'] = (int)$a['rid']+$n;
                $data['name'] = $s['name'];
                $data['acr'] = $a['acr'];
                $arr[] = $data;
                //$this->_getPlayRegionTable()->insert($data);
            }
        }
    }

    private function returnCity($tree = true){
        static $tree_nodes = array();

        if ( $tree && !empty($tree_nodes[(int)$tree]) ) {
            return $tree_nodes[$tree];
        }
        if((int)$tree){
            $list = $this->_getStationTable()->fetchLimit(0,10000,array(),array(),array('id'=>'asc'))->toArray();
            foreach ($list as $key => $value) {
                $list[$key]['url'] = $value['url'];
            }
            $nodes = $this->list_to_tree($list,$pk='id',$pid='pid',$child='operator',$root=0);
            foreach ($nodes as $key => $value) {
                if(!empty($value['operator'])){
                    $nodes[$key]['child'] = $value['operator'];
                    unset($nodes[$key]['operator']);
                }
            }
        }else{
            $nodes = $this->_getStationTable()->fetchLimit(0,10000,array(),array(),array('sort'=>'asc'))->toArray();
            foreach ($nodes as $key => $value) {
                $nodes[$key]['url'] = $value['url'];
            }
        }
        $tree_nodes[(int)$tree] = $nodes;
        return $nodes;
    }

    /**
     * 保存商圈信息
     * @return ViewModel
     */
    public function stationsaveAction() {
        $data = (array)$this->getPost();

        $close_arr = [];
        $open = $this->_getStationTable()->fetchLimit(0,10000,array(),array('is_close'=>0),array('id'=>'asc'))->toArray();
        foreach($open as $o){
            if(!in_array($o['id'],$data['city'])){
                $close_arr[] = $o['id'];
            }
        }

        if($close_arr){
            $this->_getStationTable()->update(['is_close'=>1],['id'=>$close_arr]);
        }

        if($data['city']){
            $this->_getStationTable()->update(['is_close'=>0],['id'=>$data['city']]);
        }

        return $this->_Goto( '成功', '/wftadlogin/setting/station');
    }

    /*************  二级商圈 *************/
    public function circleAction()
    {

        $cy = $this->getQuery('acr', 'WH');//城市缩写

        $local = $this->getAdminCity();

        $c = $this->_getPlayRegionTable()->fetchLimit(0,1,[],array('acr' => $local,'level' => 3))->current();

        $city = $c['rid'];
        $province = floor((int)$city/1000000)*1000000;
        $provincemax = $province + 1000000;

        $country = floor((int)$city/100000000)*100000000;
        $countrymax = $country+100000000;

        $adapter = $this->_getAdapter();//111010000
         $sql = "select * from play_region where level = 1 or ( level = 2 and rid > {$country} and rid < {$countrymax} )
                or (level = 3 and rid > {$province} and rid < {$provincemax}) or (level = 4 and acr = '{$local}');";

        $areadata = $adapter->query($sql, array())->toArray();

        $country_arr = $province_arr = $city_arr = $street_arr = [];
        foreach($areadata as $a){
            if($a['level'] == 1){
                $country_arr[] = $a;
            }
            if($a['level'] == 2){
                $province_arr[] = $a;
            }
            if($a['level'] == 3){
                $city_arr[] = $a;
            }
            if($a['level'] == 4){
                $street_arr[] = $a;
            }
        }


        return array(
            'acr' => $cy,
            'country' => $country,
            'province' =>$province,

            'country_arr' => $country_arr,
            'province_arr' => $province_arr,
            'city_arr' => $city_arr,
            'street_arr' => $street_arr,
        );
    }

    //获取二级商圈
    public function getcircleAction()
    {
        $level = (int)$this->getQuery('level', '0');//
        $rid = (int)$this->getQuery('rid', '0');//
        $ridmax = $rid + pow(100,(5-$level));
        $rdata = $this->_getPlayRegionTable()->fetchAll(array('level' => $level+1, "rid>{$rid} and rid<{$ridmax}"),
            array('rid' => 'asc'))->toArray();

        return $this->jsonResponsePage($rdata);
    }

    public function addselect1Action()
    {
        $addname = $this->getQuery('addname');
        $acr = PY::encode($addname);
        $acr = strtoupper($acr);
        $last = $this->_getPlayRegionTable()->fetchAll(array(), array('level'=>1,'rid' => 'desc'))->current();
        if (!$last) {
            $rid = 100000000;
        } else {
            $rid = $last->rid + 100000000;
        }
        $status = $this->_getPlayRegionTable()->insert(array('acr' => $acr,'level'=>1, 'rid' => $rid, 'name' => $addname));
        if ($status) {
            return $this->jsonResponsePage(array('status' => 1, 'rid' => $rid, 'name' => $addname));
        } else {
            exit;
        }

    }

    public function addselect2Action()
    {
        $addname = $this->getQuery('addname');
        $acr = PY::encode($addname);
        $acr = strtoupper($acr);
        $rid = (int)$this->getQuery('rid');
        if($rid<1000){
            return false;
        }
        $ridmax = (floor($rid/100000000)+1)*100000000;

        $last = $this->_getPlayRegionTable()->fetchAll(array("rid > {$rid} and level = 2 and rid<{$ridmax}"),
            array('rid' => 'desc'))->current();
        if($last){
            $rid = $last->rid + 1000000;
        }else{
            $pre_rid = $rid;
            $rid = $pre_rid + 1000000;
        }

        $status = $this->_getPlayRegionTable()->insert(array('acr' => $acr, 'level' => 2,'rid' => $rid, 'name' => $addname));
        if ($status) {
            return $this->jsonResponsePage(array('status' => 1, 'rid' => $rid, 'name' => $addname));
        } else {
            exit;
        }

    }

    public function addselect3Action()
    {
        $addname = $this->getQuery('addname');
        $rid = (int)$this->getQuery('rid');
        if($rid<1000){
            return false;
        }
        $acr = PY::encode($addname).time();
        $acr = strtoupper($acr);
        $ridmax = (floor($rid/1000000)+1)*1000000;
        $last = $this->_getPlayRegionTable()->fetchAll(array("rid>={$rid} and level = 3 and rid<{$ridmax}"),
            array('rid' => 'desc'))->current();
        if($last){
            $rid = $last->rid + 10000;
        }else{
            $pre_rid = $rid;
            $rid = $pre_rid + 10000;
        }
        $status = $this->_getPlayRegionTable()->insert(array('acr' => $acr,'level' => 3, 'rid' => $rid, 'name' => $addname));
        if ($status) {
            return $this->jsonResponsePage(array('status' => 1, 'rid' => $rid, 'name' => $addname));
        } else {
            exit;
        }

    }

    public function addselect4Action()
    {
        $addname = $this->getQuery('addname');
        $rid = (int)$this->getQuery('rid');
        if($rid<1000){
            return false;
        }
        $city = $this->_getPlayRegionTable()->get(['rid'=>$rid]);
        $ridmax = (floor($rid/10000)+1)*10000;
        $last = $this->_getPlayRegionTable()->fetchAll(array("rid>={$rid} and level = 4 and rid<{$ridmax}"),
            array('rid' => 'desc'))->current();
        if($last){
            $rid = $last->rid + 100;
        }else{
            $pre_rid = $rid;
            $rid = $pre_rid + 100;
        }
        $status = $this->_getPlayRegionTable()->insert(array('acr' => $city->acr, 'level' => 4,'rid' => $rid, 'name' => $addname));
        if ($status) {
            return $this->jsonResponsePage(array('status' => 1, 'rid' => $rid, 'name' => $addname));
        } else {
            exit;
        }

    }

    public function addselect5Action()
    {
        $addname = $this->getQuery('addname');
        $rid = (int)$this->getQuery('rid');
        if($rid<1000){
            return false;
        }
        $city = $this->_getPlayRegionTable()->get(['rid'=>$rid]);
        $ridmax = $rid + 100;
        $last = $this->_getPlayRegionTable()->fetchAll(array("rid>={$rid} and level = 5 and rid<{$ridmax}"),
            array('rid' => 'desc'))->current();
        if($last){
            $rid = $last->rid + 1;
        }else{
            $pre_rid = $rid;
            $rid = $pre_rid + 1;
        }
        $status = $this->_getPlayRegionTable()->insert(array('acr' => $city->acr,'level' => 5, 'rid' => $rid, 'name' => $addname));
        if ($status) {
            return $this->jsonResponsePage(array('status' => 1, 'rid' => $rid, 'name' => $addname));
        } else {
            exit;
        }

    }

    public function deleteAction()
    {
        $rid = (int)$this->getQuery('rid');
        $ridmax = 0;
         if($rid % 100000000===0) {
            $ridmax = $rid + 100000000;
            //$status = $this->_getPlayRegionTable()->delete(array("rid>={$rid} and id > 0 and rid<{$ridmax}"));
        }elseif($rid % 1000000===0) {
            $ridmax = $rid + 1000000;
            //$status = $this->_getPlayRegionTable()->delete(array("rid>={$rid} and id > 0 and rid<{$ridmax}"));
        }elseif($rid % 10000===0) {
            $ridmax = $rid + 10000;
            //$status = $this->_getPlayRegionTable()->delete(array("rid>={$rid} and id > 0 and rid<{$ridmax}"));
        }elseif ($rid % 100===0) {
             $ridmax = $rid + 100;
             //$status = $this->_getPlayRegionTable()->delete(array("rid>={$rid} and id > 0 and rid<{$ridmax}"));
         }
        $rt = $this->_getPlayRegionTable()->fetchLimit(0,1,[],array("rid >{$rid} and id > 0 and rid<{$ridmax}"))->toArray();

        if($rt){
            return $this->jsonResponsePage(array('status' => 0, 'message' => "请先删除该级下的信息"));
        }
        $status = $this->_getPlayRegionTable()->delete(array('rid' => $rid));
        if ($status) {
            return $this->jsonResponsePage(array('status' => 1, 'rid' => $rid));
        }
        exit;

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
        $city = $this->chooseCity(1);

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
                    'search_type' => 2,
                    'city' => $city,
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

        $where = "play_search_form_value.search_type = 2 AND play_search_form_value.city = '{$city}'";
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

        return array('data' => $data, 'page' => $paginator->getHtml(),'filtercity' => CityCache::getFilterCity($city,2),'city'=>$this->getAllCities());
    }

    //启动图设置
    public function installAction()
    {

        $act = $this->getQuery('act');
        $id = (int)$this->getQuery('id');
        $city = $this->getAdminCity();

        if ($act) {
            if ($act == 'update') {
                $status = $this->_getPlayAttachTable()->update(array('dateline' => time()), array('id' => $id,'city'=>$city));

                return $this->_Goto($status ? '成功' : '失败');
            }

            if ($act == 'del') {
                $status = $this->_getPlayAttachTable()->update(array('use_id' => 1), array('id' => $id,'city'=>$city));

                return $this->_Goto($status ? '成功' : '失败');
            }

            if ($act == 'view') {
                $img_data = $this->_getPlayAttachTable()->get(array('id' => $id,'city'=>$city, 'use_type' => 'firing'));
                $imgs = json_decode($img_data->url);
                if (count($imgs)) {
                    foreach ($imgs as $img) {
                        echo '<img src="' . $this->_getConfig()['url'] . $img . '"> <br />';
                    }
                }
                exit;
            }

            if ($act == 'save') {
                $cover2 = $this->getPost('cover2');
                $cover3 = $this->getPost('cover3');
                $name = $this->getPost('val');
                $height = strtotime($this->getPost('height') . $this->getPost('heightl')); //
                $width = strtotime($this->getPost('width') . $this->getPost('widthl')); //

                if (!$cover2 || !$cover3 || !$name) {
                    return $this->_Goto('图片或用户未设置');
                }

                if ($width > $height) {
                    return $this->_Goto('时间错了');
                }

                $cover2_class = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $cover2);
                $cover2_status = $cover2_class->scaleResizeImage(720, 1280);
                if ($cover2_status) {
                    $cover2_status->save($_SERVER['DOCUMENT_ROOT'] . $cover2);
                }

                $cover3_class = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $cover3);
                $cover3_status = $cover3_class->scaleResizeImage(2670, 2001);
                if ($cover3_status) {
                    $cover3_status->save($_SERVER['DOCUMENT_ROOT'] . $cover3);
                }


                $sta = $this->_getPlayAttachTable()->insert(array(
                        'uid' => 2,
                        'city' => $city,
                        'use_id' => 2,
                        'use_type' => 'firing',
                        'dateline' => time(),
                        'url' => json_encode(array('', $cover2, $cover3)),
                        'is_remote' => 0,
                        'name' => $name,
                        'height' => $height,
                        'width' => $width
                    )
                );

                return $this->_Goto($sta ? '成功' : '失败');
            }

        }

        $data = $this->_getPlayAttachTable()->fetchLimit(0, 20, array(), array('use_type' => 'firing','city'=>$city, 'use_id' => 2),
            array('dateline' => 'desc'));


        return array(
            'data' => $data,
        );

    }

    //
    public function marketingAction()
    {

        //   $i = new Integral();
//        $i->weixin_bind(181863,'WH');
//
//        $i->baby_face(181863,'WH');
//
//        $i->face_integral(181863,'WH');
//
//        $i->baby_info(181863,'WH');

        //$i->newer_task_integral(181863, 'WH');

        //$i->circle_speak(181863,'56asdfasfsdfdfdfef', 'WH');
        $uid = 181861;
        $city = 'WH';
//        var_dump(1);
//        $invite = new Invite();
//        $invite->WeiXinMsg(57870,57870,10,'buy','000159070');
//        var_dump(2);

        //$i->circle_speak_delete($uid, '5698cd487f8b9af7219b68fb', $city);

        //$i->circle_prize_integral($uid, '5698cd487f8b9af7219b68fb', $city);

        //$i->good_comment_integral($uid,8856, $city);

//        $i->good_comment_prize_integral($uid,'5698cd487f8b9af7219b68fe', $city);

        //$i->place_comment_integral($uid,'5698cd487f8b9af7219b67fe', $city);

        // $i->days_task_integral($uid, $city);

//        $i->weixin_bind(181863,'WH');
//        $order_info = new \stdClass();
//        $order_info->user_id = 181863;
//        $order_info->order_sn = 123456;
//        $order_info->coupon_id = 627;
//        $order_info->game_info_id = 1369;
//        $total_money = 100;
//        $order_info->order_city = 'WH';


//        $order_data = $this->_getPlayOrderInfoTable()->get(array('order_sn' => '000085873'));
//        $integral = new Integral();
//        $integral->returnGood($order_data->user_id,$order_data->order_sn,0,$order_data->order_city);
//


//        //支付完成奖励积分和票券
//        $integral = new Integral();
//        $integral->buyGood($order_info->user_id, $order_info->coupon_id, $total_money, $order_info->order_city);
//        //奖励现金券

//        $i->useGood($order_info->user_id, $order_info->coupon_id, $total_money, $order_info->order_city,'');
//
//        $coupon = new Coupon();
//        $coupon->getCashCouponByBuy($order_info->user_id, $order_info->coupon_id, $order_info->game_info_id,$order_info->order_sn, $order_info->order_city);
//
//        //返利
//        $cash = new Account();
//        $cash->getCashByBuy($order_info->user_id, $order_info->coupon_id, $order_info->game_info_id,$order_info->order_sn, $order_info->order_city);

        //奖励现金券
//        $coupon = new Coupon();
//        $coupon->getCashCouponByUse($order_info->user_id, $order_info->coupon_id, $order_info->game_info_id,$order_info->order_sn, $order_info->order_city);
//
//
//        //返利
//        $cash = new Account();
//        $cash->getCashByUse($order_info->user_id, $order_info->coupon_id, $order_info->game_info_id,$order_info->order_sn, $order_info->order_city);

//        //奖励现金券
//        $coupon = new Coupon();
//        $coupon->getCashCouponByCommend(181863,85859,'WH');
//
//
//        //返利
//        $cash = new Account();
//        $cash->getCashByCommend(181863,85859,'WH');

//        $coupon = new Coupon();
//        $cp = $coupon->addCashcoupon($uid,156,0,1,0,'兑换码兑换',$city);
//
//        var_dump($cp);

//        $i->good_comment_integral($uid, '56ab32497f8b9a51139b6926', $city, 612);
//        $i->place_comment_integral($uid, '56d553417f8b9adc4fd438a3', $city, 1320);

//        $i->share_good_integral($uid,612,$city);
//        $i->share_place_integral($uid,1313,$city);

//         $i->place_comment_integral($uid,'56ab32497f8b9a55639b6926',$city,550);

        // $i->place_comment_prize_integral($uid, '56b055387f8b9aef67b26834', $city,1323);

        //$i->circle_prize_integral($uid, '56ab4a3c7f8b9a1b72b2682a', $city);

//        $i->circle_speak($uid, '56ab4a3c7f8b9a1b72b2682a', $city);

        //       $i->returnGood($order_info->user_id, $order_info->coupon_id, $total_money, $order_info->order_city);

        //   $i->day_integral_delete($uid,$city);
        //   $i->day_integral_delete($uid,$city);
        //   $i->day_integral_delete($uid,$city);

        //$i->good_comment_prize_integral(39125, '56b01bc67f8b9a9074b2682b', $city,576);


        //$coupon->addQualify(10013,4,'WH',0,0,0);

        //$i->days_task_integral($uid,$city);

        $city = (string)$this->chooseCity(0);
        $cities = $this->getAllCities(1);
        if (!array_key_exists($city, $cities)) {
            return $this->_Goto('参数错误');
        }
        $data = $this->_getPlayMarketSettingTable()->get(['city' => $city]);

        $social_circle = $this->_getMdbSocialCircle();

        $cirlce = $social_circle->find(array('status' => 1,'city' => $city));

        return array(
            'data' => $data,
            'filtercity' => CityCache::getFilterCity($city, 0),
            'circle' => $cirlce
        );
    }

    public function marketsaveAction()
    {
        $data = (array)$this->getPost();

        $creater = $this->getCreater($data['city']);

        $mst = $this->_getPlayMarketSettingTable()->get(['city' => $creater]);
        if ($mst) {
            $status = $this->_getPlayMarketSettingTable()->update($data, ['city' => $creater]);
        } else {
            $data['city'] = $creater;
            $status = $this->_getPlayMarketSettingTable()->insert($data);
        }

        return $this->_Goto($status ? '成功' : '失败');
    }

    public function invitecontentAction()
    {
        $city = (string)$this->chooseCity(0);
        $cities = $this->getAllCities(1);
        if (!array_key_exists($city, $cities)) {
            return $this->_Goto('参数错误');
        }
        $data = $this->_getPlayInviteContentTable()->get(['city' => $city]);

        return array(
            'data' => $data,
            'filtercity' => CityCache::getFilterCity($city, 0),
        );
    }

    //约稿
    public function invitecontentsaveAction()
    {
        $data = (array)$this->getPost();
        $data['content'] = $data['editorValue'];
        unset($data['editorValue']);

        $creater = $this->getCreater($data['city']);

        $ic = $this->_getPlayInviteContentTable()->get(['city' => $creater]);
        if ($ic) {
            $status = $this->_getPlayInviteContentTable()->update($data, ['city' => $creater]);
        } else {
            $data['city'] = $creater;;
            $status = $this->_getPlayInviteContentTable()->insert($data);
        }

        return $this->_Goto($status ? '成功' : '失败');
    }

    //好评有礼
    public function goodcommentAction()
    {
        $city = (string)$this->chooseCity(0);
        $cities = $this->getAllCities(1);
        if (!array_key_exists($city, $cities)) {
            return $this->_Goto('参数错误');
        }
        $data = $this->_getPlayGoodCommentTable()->get(['city' => $city]);

        return array(
            'data' => $data,
            'filtercity' => CityCache::getFilterCity($city, 1),
        );
    }

    public function goodcommentsaveAction()
    {
        $data = (array)$this->getPost();

        $creater = $this->getCreater($data['city']);

        $gc = $this->_getPlayGoodCommentTable()->get(['city' => $creater]);
        if ($gc) {
            $status = $this->_getPlayGoodCommentTable()->update($data, ['city' => $creater]);
        } else {
            $data['city'] = $creater;
            $status = $this->_getPlayGoodCommentTable()->insert($data);
        }

        return $this->_Goto($status ? '成功' : '失败');
    }

    public function taskAction()
    {
        $city = (string)$this->chooseCity(0);
        $cities = $this->getAllCities(1);
        if (!array_key_exists($city, $cities)) {
            return $this->_Goto('参数错误');
        }
        $data = $this->_getPlayTaskIntegralTable()->get(['city' => $city]);

        return array(
            'data' => $data,
            'filtercity' => CityCache::getFilterCity($city, 0),
            'city' => $city
        );
    }

    public function tasksaveAction()
    {

        $origin = array(
            'alt_face' => 0,
            'b_weixin' => 0,
            'baby_info' => 0,
            'baby_face' => 0,
            'new_plus' => 0,
            'sign' => 0,
            'invite' => 0,
            'share_good' => 0,
            'share_place' => 0,
            'comm_good' => 0,
            'comm_place' => 0,
            'buy_good' => 0,
            'circle_msg' => 0,
            'day_plus' => 0
        );

        $data = (array)$this->getPost();
        foreach ($origin as $k => $d) {
            $data[$k] = $data[$k] ?: 0;
        }
        $tasks = [];
        foreach ($data as $k => $d) {
            if (!in_array($k, ['new_plus', 'day_plus'], true)) {
                $tasks[] = $d;
            }
        }

        //参与新手任务的项目
        $new_task = [11, 12, 13, 14];

        //参与每日任务的项目
        $day_task = [1, 2, 3, 4, 5, 8, 15, 22];

        $data['new_task'] = serialize(array_intersect($new_task, $tasks));
        $data['day_task'] = serialize(array_intersect($day_task, $tasks));

        $creater = $this->getCreater($data['city']);

        $ie = $this->_getPlayTaskIntegralTable()->get(['city' => $creater]);
        if ($ie) {
            $status = $this->_getPlayTaskIntegralTable()->update($data, ['city' => $creater]);
        } else {
            $data['city'] = $creater;
            $status = $this->_getPlayTaskIntegralTable()->insert($data);
        }

        return $this->_Goto($status ? '成功' : '失败');
    }



    public function groundAction(){
        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;

        $where = [];

        $where['city'] = $this->getAdminCity();

        $data = $this->_getPlayGroundTable()->fetchLimit(($page - 1) * $pageSum, $pageSum, array(), $where,
            array('id' => 'DESC'))->toArray();

        foreach($data as $d){
            $dids[] = $d['id'];
        }
        if($dids){
            $didstr = implode(',',$dids);
        }

        $count = $this->_getPlayGroundTable()->fetchCount($where);
        $url = '/wftadlogin/setting/ground';

        $adapter = $this->_getAdapter();
        //领奖人数 关注人数

        $sql = "select dtid,sum(registed) as reg from weixin_ditui_log WHERE is_new = 1 and concern_time > 1461311070 and dtid in (".$didstr.") group by dtid";

        $user = $adapter->query($sql, array())->toArray();

        $sql = "select dtid,sum(is_on) as ison from weixin_ditui_log WHERE is_on = 1 and concern_time > 1461311070 and dtid in (".$didstr.") group by dtid";

        $user2 = $adapter->query($sql, array())->toArray();

        $tj = [];
        foreach($user as $u){
            $tj[$u['dtid']]['reg'] = $u['reg'];
        }
        foreach($user2 as $u){
            $tj[$u['dtid']]['ison'] = $u['ison'];
        }

        $paginator = new Paginator($page, $count, $pageSum, $url);
        $vm = new viewModel(
            array(
                'data' => $data,
                'tj' => $tj,
                'url'=> $this->_getConfig()['url'],
                'pageData' => $paginator->getHtml(),
            )
        );

        return $vm;
    }

    public function groundnewAction(){
        $id = (int)$this->getQuery('id',0);
        $ground = $this->_getPlayGroundTable()->get(['id'=>$id]);

        if($ground){
            $options = json_decode((string)$ground->options);
            $options = (array)$options;
        }

        return new viewModel(array(
            'data' => $ground,
            'options' => $options
        ));
    }

    public function groundnumberAction(){
       $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;

        $where = [];
        $id = (int)$this->getQuery('dtid', 1);
    
        $url = '/wftadlogin/setting/groundnumber';

        $adapter = $this->_getAdapter();
        //领奖人数 关注人数

        $sql = "select dtid,registed,weixin_name,concern_time,phone from weixin_ditui_log 
        WHERE is_new = 1 and concern_time > 1461311070 and dtid = ? limit ?,?";

        $data = $adapter->query($sql, array($id,$page-1,$pageSum))->toArray();

        $sql = "select count(id) as c from weixin_ditui_log 
        WHERE is_new = 1 and concern_time > 1461311070 and dtid = ? ";

        $count = $adapter->query($sql, array($id))->current();

        $paginator = new Paginator($page, $count, $pageSum, $url);
        $vm = new viewModel(
            array(
                'data' => $data,
                'url'=> $this->_getConfig()['url'],
                'pageData' => $paginator->getHtml(),
            )
        );
        return $vm;
    }

    public function groundaddAction(){

        $old = (int)$this->getPost('old',0);
        $title = trim($this->getPost('title', ''));

        $usefor = (int)$this->getPost('usefor',0);


        if($title){

            $begin_time = trim($this->getPost('begin_time', '') . $this->getPost('begin_timel', ''));
            $end_time = trim($this->getPost('end_time', '') . $this->getPost('end_timel', ''));

            $options['cashcoupon'] = trim($this->getPost('cashcoupon', ''));
            $options['cashcoupon_no'] = trim($this->getPost('cashcoupon_no', ''));
            $options['backcash'] = trim($this->getPost('backcash', ''));
            $options['backcash_value'] = trim($this->getPost('backcash_value', ''));
            $options['backcash_max'] = trim($this->getPost('backcash_max', ''));
            $options['quailty'] = trim($this->getPost('quailty', ''));
            $options['quailty_num'] = trim($this->getPost('quailty_num', ''));
            $options['integral'] = trim($this->getPost('integral', ''));
            $options['integral_value'] = trim($this->getPost('integral_value', ''));

            $options['cashcoupon_old'] = trim($this->getPost('cashcoupon_old', ''));
            $options['cashcoupon_no_old'] = trim($this->getPost('cashcoupon_no_old', ''));
            $options['backcash_old'] = trim($this->getPost('backcash_old', ''));
            $options['backcash_value_old'] = trim($this->getPost('backcash_value_old', ''));
            $options['backcash_max_old'] = trim($this->getPost('backcash_max_old', ''));
            $options['quailty_old'] = trim($this->getPost('quailty_old', ''));
            $options['quailty_num_old'] = trim($this->getPost('quailty_num_old', ''));
            $options['integral_old'] = trim($this->getPost('integral_old', ''));
            $options['integral_value_old'] = trim($this->getPost('integral_value_old', ''));

            $end_time = strtotime($end_time);
            $begin_time = strtotime($begin_time);

            if($end_time < $begin_time){
                return $this->_Goto('开始时间大于结束时间');
            }

            if($options['cashcoupon_no']){
                $cid = explode(',', $options['cashcoupon_no']);
                if($cid[1]){
                    return $this->_Goto('请不要上传多张现金券');
                }
                $cc = $this->_getCashCouponTable()->get(['id' => $cid[0]]);
                //residue > 0 and end_time > {$time} and status = 1 and is_close = 0"
                if($cc->residue < 1 || $cc->end_time < $end_time || $cc->begin_time > $begin_time || $cc->status < 1 || $cc->is_close > 0){
                    return $this->_Goto('新用户现金券不可用');
                }
            }

            if($options['cashcoupon_no_old']){
                $cid = explode(',', $options['cashcoupon_no_old']);
                if($cid[1]){
                    return $this->_Goto('请不要上传多张现金券');
                }
                $cc = $this->_getCashCouponTable()->get(['id' => $cid[0]]);
                //residue > 0 and end_time > {$time} and status = 1 and is_close = 0"
                if($cc->residue < 1 || $cc->end_time < $end_time || $cc->begin_time > $begin_time || $cc->status < 1 || $cc->is_close > 0){
                    return $this->_Goto('老用户现金券不可用');
                }
            }

            if($options['cashcoupon'] && !$options['cashcoupon_no']){
                return $this->_Goto('没有设置现金券');
            }

            if($options['backcash'] && (!$options['backcash_value'] || !$options['backcash_max'])){
                return $this->_Goto('没有设置返利额度');
            }

            if($options['quailty'] && !$options['quailty_num']){
                return $this->_Goto('没有设置资格券数量');
            }


            $data = [];
            $data['title'] = $title;
            $data['stime'] = $begin_time;
            $data['etime'] = $end_time;
            $data['options'] = json_encode($options);
            $data['city'] = $this->getAdminCity();
            $data['old'] = $old?:0;
            if($usefor){
                $data['usefor'] = $usefor?:0;
            }

            if($data['old']){
                if(!$options['cashcoupon_old'] && !$options['backcash_old'] && !$options['quailty_old']){
                    return $this->_Goto('老用户没有设置奖品');
                }

                if($options['cashcoupon_old'] && !$options['cashcoupon_no_old']){
                    return $this->_Goto('没有设置现金券');
                }

                if($options['backcash_old'] && (!$options['backcash_value_old'] || !$options['backcash_max_old'])){
                    return $this->_Goto('没有设置返利额度');
                }

                if($options['quailty_old'] && !$options['quailty_num_old']){
                    return $this->_Goto('没有设置资格券数量');
                }
            }

            if(!$data['title']){
                return $this->_Goto('没有设置活动名');
            }

            $data['status'] = 1;
            $data['createtime'] = time();
            $status = $this->_getPlayGroundTable()->insert($data);
            return $this->_Goto($status ? '成功' : '无操作');
        }
        return new viewModel(array(

        ));
    }

    public function groundsaveAction(){
        $id = (int)$this->getPost('id',-1);
        $qid = (int)$this->getQuery('id',-1);
        $old = (int)$this->getPost('old',0);
        $usefor = (int)$this->getPost('usefor',0);
        $title = trim($this->getPost('title', ''));
        $isclose = (int)($this->getQuery('isclosed', 0));

        if($isclose > 0 && $qid > -1){
            RedCache::del('D:dtop:' . $qid);
            $isclose = ($isclose===1)?0:1;
            $status = $this->_getPlayGroundTable()->update(['status'=>$isclose],['id'=>$qid]);
            return $this->_Goto($status ? '成功' : '失败');
        }

        $begin_time = trim($this->getPost('begin_time', '') . $this->getPost('begin_timel', ''));
        $end_time = trim($this->getPost('end_time', '') . $this->getPost('end_timel', ''));

        $options['cashcoupon'] = trim($this->getPost('cashcoupon', ''));
        $options['cashcoupon_no'] = trim($this->getPost('cashcoupon_no', ''));
        $options['backcash'] = trim($this->getPost('backcash', ''));
        $options['backcash_value'] = trim($this->getPost('backcash_value', ''));
        $options['backcash_max'] = trim($this->getPost('backcash_max', ''));
        $options['quailty'] = trim($this->getPost('quailty', ''));
        $options['quailty_num'] = trim($this->getPost('quailty_num', ''));
        $options['integral'] = trim($this->getPost('integral', ''));
        $options['integral_value'] = trim($this->getPost('integral_value', ''));

        $options['cashcoupon_old'] = trim($this->getPost('cashcoupon_old', ''));
        $options['cashcoupon_no_old'] = trim($this->getPost('cashcoupon_no_old', ''));
        $options['backcash_old'] = trim($this->getPost('backcash_old', ''));
        $options['backcash_value_old'] = trim($this->getPost('backcash_value_old', ''));
        $options['backcash_max_old'] = trim($this->getPost('backcash_max_old', ''));
        $options['quailty_old'] = trim($this->getPost('quailty_old', ''));
        $options['quailty_num_old'] = trim($this->getPost('quailty_num_old', ''));
        $options['integral_old'] = trim($this->getPost('integral_old', ''));
        $options['integral_value_old'] = trim($this->getPost('integral_value_old', ''));

        $end_time = strtotime($end_time);
        $begin_time = strtotime($begin_time);

        if($end_time < $begin_time){
            return $this->_Goto('开始时间大于结束时间');
        }

        if($options['cashcoupon_no']){
            $cid = explode(',', $options['cashcoupon_no']);
            if($cid[1]){
                return $this->_Goto('请不要上传多张现金券');
            }
            $cc = $this->_getCashCouponTable()->get(['id' => $cid[0]]);
            //residue > 0 and end_time > {$time} and status = 1 and is_close = 0"
            if($cc->residue < 1 || $cc->end_time < $end_time || $cc->begin_time > $begin_time || $cc->status < 1 || $cc->is_close > 0){
                return $this->_Goto('新用户设置的现金券不可用,现金券的领取时间要在活动范围内');
            }
        }

        if($options['cashcoupon_no_old']){
            $cid = explode(',', $options['cashcoupon_no_old']);
            if($cid[1]){
                return $this->_Goto('请不要上传多张现金券');
            }
            $cc = $this->_getCashCouponTable()->get(['id' => $cid[0]]);
            //residue > 0 and end_time > {$time} and status = 1 and is_close = 0"
            if($cc->residue < 1 || $cc->end_time < $end_time || $cc->begin_time > $begin_time || $cc->status < 1 || $cc->is_close > 0){
                return $this->_Goto('老用户设置的现金券不可用,现金券的领取时间要在活动范围内');
            }
        }

        if($options['cashcoupon'] && !$options['cashcoupon_no']){
            return $this->_Goto('没有设置现金券');
        }

        if($options['backcash'] && (!$options['backcash_value'] || !$options['backcash_max'])){
            return $this->_Goto('没有设置返利额度');
        }

        if($options['quailty'] && !$options['quailty_num']){
            return $this->_Goto('没有设置资格券数量');
        }


        $data = [];
        $data['title'] = $title;
        $data['stime'] = $begin_time;
        $data['etime'] = $end_time;
        $data['options'] = json_encode($options);
        $data['city'] = $this->getAdminCity();
        $data['old'] = $old?:0;
        if($usefor){
            $data['usefor'] = $usefor?:0;
        }

        if($data['old']){
            if(!$options['cashcoupon_old'] && !$options['backcash_old'] && !$options['quailty_old']){
                return $this->_Goto('老用户没有设置奖品');
            }

            if($options['cashcoupon_old'] && !$options['cashcoupon_no_old']){
                return $this->_Goto('没有设置现金券');
            }

            if($options['backcash_old'] && (!$options['backcash_value_old'] || !$options['backcash_max_old'])){
                return $this->_Goto('没有设置返利额度');
            }

            if($options['quailty_old'] && !$options['quailty_num_old']){
                return $this->_Goto('没有设置资格券数量');
            }
        }

        if(!$data['title']){
            return $this->_Goto('没有设置活动名');
        }

        if($id > -1){
            RedCache::del('D:dtop:' . $id);
            $status = $this->_getPlayGroundTable()->update($data,['id'=>$id]);
        }else{
            $data['status'] = 1;
            $data['createtime'] = time();
            $status = $this->_getPlayGroundTable()->insert($data);
        }

        return $this->_Goto($status ? '成功' : '无修改操作');
    }

    //分享现金红包
    public function cashsharenewAction(){
        $city = $this->getAdminCity();

        $cs = $this->_getPlayCashShareTable()->get(['city'=>$city,'type'=>1]);

        return new viewModel(array(
            'data' => $cs,
            'options' => $cs?json_decode($cs->options):[],
            'messages' => $cs?json_decode($cs->messages):[],
        ));
    }

    //活动红包分享
    public function excerciseshareAction(){
        $city = $this->getAdminCity();

        $cs = $this->_getPlayCashShareTable()->get(['city'=>$city,'type'=>2]);

        return new viewModel(array(
            'data' => $cs,
            'options' => $cs?json_decode($cs->options):[],
            'messages' => $cs?json_decode($cs->messages):[],
        ));
    }

    //活动分享保存
    public function excercisesharesaveAction(){
        $id = (int)$this->getPost('id',0);

        $data = [];

        $data['isall'] = (int)$this->getPost('isall',0);
        $data['title'] = trim($this->getPost('title', ''));
        $data['content'] = trim($this->getPost('content', ''));
        $data['afterbuy'] = $this->getPost('afterbuy', '');
        $data['shareicon'] = $this->getPost('shareicon', '');
        $data['afterget'] = $this->getPost('afterget', '');

        $data['type'] = 2;

        $data['messages'] = $this->getPost('messages', '');
        $data['messages'] = json_encode($data['messages']);

        $data['city'] = $this->getAdminCity();

        $price_range = (array)$this->getPost('price_range');
        $owner = (array)$this->getPost('owner');

        $corm = (array)$this->getPost('corm');
        $geter = (array)$this->getPost('geter');

        $range = count($price_range);

        $options = [];
        for($i = 0;$i<$range;$i++){
            if(!$price_range[$i] || !$owner[$i] || !$geter[$i]){
                return $this->_Goto('价格范围不要有空值！');
            }
            $options[$i] = array($price_range[$i],$owner[$i],$corm[$i],$geter[$i]);
        }

        $data['options'] = json_encode($options);

        if($id > 0){
            RedCache::del('D:share_cash:' . $data['type'].$data['city']);
            $status = $this->_getPlayCashShareTable()->update($data,['id'=>$id]);
        }else{
            $status = $this->_getPlayCashShareTable()->insert($data);
        }

        return $this->_Goto($status ? '成功' : '无修改操作');

    }

    public function cashsharesaveAction(){
        $id = (int)$this->getPost('id',0);

        $data = [];

        $data['isall'] = (int)$this->getPost('isall',0);
        $data['title'] = trim($this->getPost('title', ''));
        $data['content'] = trim($this->getPost('content', ''));
        $data['afterbuy'] = $this->getPost('afterbuy', '');
        $data['shareicon'] = $this->getPost('shareicon', '');
        $data['afterget'] = $this->getPost('afterget', '');
        $data['type'] = 1;
        $data['messages'] = $this->getPost('messages', '');
        $data['messages'] = json_encode($data['messages']);

        $data['city'] = $this->getAdminCity();

        $price_range = (array)$this->getPost('price_range');
        $owner = (array)$this->getPost('owner');
        $geter = (array)$this->getPost('geter');

        $range = count($price_range);

        $options = [];
        for($i = 0;$i<$range;$i++){
            if(!$price_range[$i] || !$owner[$i] || !$geter[$i]){
                return $this->_Goto('价格范围不要有空值！');
            }
            $options[$i] = array($price_range[$i],$owner[$i],$geter[$i]);
        }

        $data['options'] = json_encode($options);

        if($id > 0){
            RedCache::del('D:share_cash:' . $data['type'] . $data['city']);
            $status = $this->_getPlayCashShareTable()->update($data,['id'=>$id]);
        }else{
            $status = $this->_getPlayCashShareTable()->insert($data);
        }

        return $this->_Goto($status ? '成功' : '无修改操作');

    }

    public function shareSettingAction() {
        $data_city = $this->getAdminCity();

        $data_param= array(
            'share_city' => $data_city,
        );
        $data_share = Share::getShareData($data_param);

        $data_share['share_title']    = json_decode($data_share['share_title'],   true);
        $data_share['share_content']  = json_decode($data_share['share_content'], true);
        return $vm = new viewModel(
            array(
                'data_share' => $data_share,
            )
        );
    }

    public function shareSettingSaveAction() {
        $param['share_status']  = $this->getPost('share_status', 0);
        $param['share_url']     = $this->getPost('share_url', '');
        $param['share_title']   = (array)$this->getPost('share_title', '');
        $param['share_content'] = (array)$this->getPost('share_content', '');
        $param['share_img']     = $this->getPost('share_img', '');

        $data_city              = $this->getAdminCity();
        $param['share_city']    = $data_city;

        $data_param= array(
            'share_city' => $data_city,
        );

        if (count($param['share_title']) > 0) {
            $param['share_title']    = json_encode($param['share_title'],   JSON_UNESCAPED_UNICODE);
            $param['share_content']  = json_encode($param['share_content'], JSON_UNESCAPED_UNICODE);
        } else {
            $param['share_title']    = '';
            $param['share_content']  = '';
        }

        $data_share = Share::getShareData($data_param);
        if (empty($data_share)) {
            $param['share_city'] = $data_city;
            $data_result         = Share::addShareData($param);
        } else {
            $data_result = Share::updateShareData($param);
        }


        return $this->_Goto($data_result ? '成功' : '无修改操作');
    }

    public function showBannerSettingAction () {
        $data_banner_list = Banner::getBannerListData(array(
            'banner_status' => 1,
            'banner_delete' => 0,
        ));

        if ($data_banner_list) {
            $data_city = $this->getAdminCity();
            $data_temp_banner_setting_list = Banner::getBannerSettingListData(array(
                'banner_setting_city' => $data_city,
            ));
            $data_banner_setting_list = array();
            foreach ($data_temp_banner_setting_list as $key => $val) {
                $data_banner_setting_list[$val['banner_setting_banner_id']] = $val;
            }
        }
        return $vm = new viewModel(
            array(
                'data_banner'         => $data_banner_list,
                'data_banner_setting' => $data_banner_setting_list,
            )
        );
    }

    public function bannerSettingSaveAction () {
        $param['banner_setting']    = (array)$this->getPost('banner_setting', '');
        $param['banner_setting_id'] = (array)$this->getPost('banner_setting_id', '');

        $data_banner_list = Banner::getBannerListData(array(
            'banner_status' => 1,
            'banner_delete' => 0,
        ));

        $data_city = $this->getAdminCity();

        if (!empty($data_banner_list)) {
            foreach ($data_banner_list as $key => $val) {
                $data_update_param = array(
                    'banner_setting_id'        => $param['banner_setting_id'][$key],
                    'banner_setting_banner_id' => $val['banner_id'],
                    'banner_setting_value'     => $param['banner_setting'][$key],
                    'banner_setting_city'      => $data_city
                );
                Banner::bannerSettingSave($data_update_param);
            }
        }
        return $this->_Goto(true ? '成功' : '无修改操作');
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
