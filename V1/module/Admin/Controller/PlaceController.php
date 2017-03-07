<?php

namespace Admin\Controller;

use Deyi\GetCacheData\CityCache;
use Deyi\JsonResponse;
use Deyi\Paginator;
use Deyi\OutPut;
use Deyi\Alipay\Alipay;
use Deyi\Account\Account;
use Deyi\OrderAction\UseCode;
use library\Fun\M;
use Zend\Db\Sql\Expression;
use Deyi\ImageProcessing;
use Zend\Db\Adapter\Adapter;
use Deyi\Integral\Integral;
use Zend\View\Model\ViewModel;

class PlaceController extends BasisController
{
    use JsonResponse;
    use UseCode;

    public function indexAction()
    {
        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;

        $like = $this->getQuery('place_name', '');
        $city = $this->getAdminCity();
        $heiWu = $this->getQuery('heiwu');
        $city_code = $this->getQuery('city_code');

        if ($heiWu) {
            $where = "play_shop.shop_status >= -1";
        } else {
            $where = "play_shop.shop_status >= 0";
        }

        if ($city != 1 && !$city_code) {
            $where = $where. " AND play_shop.shop_city = '{$city}'";
        }

        if ($like) {
            $where = $where. " AND play_shop.shop_name like  '%".$like."%'";
        }

        $start = ($page - 1) * $pageSum;

        $sql = "SELECT
play_shop.shop_id,
play_shop.shop_name,
play_shop.shop_city,
play_shop.post_number,
play_shop.shop_click,
play_shop.star_num
FROM
play_shop
WHERE
$where
ORDER BY
play_shop.dateline DESC
LIMIT {$start}, {$pageSum}
";

        $result = $this->query($sql);

        $data = array();
        foreach ($result as $res) {
            $shopInfo = $this->getShopInfo($res['shop_id']);
            $data[] = array(
                'shop_id' => $res['shop_id'],
                'shop_name' => $res['shop_name'],
                'shop_city' => $res['shop_city'],
                'post_number' => $res['post_number'],
                'shop_click' => $res['shop_click'],
                'star_num' => $res['star_num'],
                'good_num' => $shopInfo['good_num'],
                'share_number' => $shopInfo['share_number'],
                'produce_integral' => $shopInfo['produce_integral'],
                'use_money' => $shopInfo['use_money'],
            );
        }

        $sql_count = "SELECT play_shop.shop_id FROM play_shop WHERE $where";
        $count = $this->query($sql_count)->count();
        $url = '/wftadlogin/place';
        $paginator = new Paginator($page, $count, $pageSum, $url);

        return array(
            'data' => $data,
            'pageData' => $paginator->getHtml(),
            'city' => $this->getAllCities(),
            'filtercity' => CityCache::getFilterCity($city),
        );
    }

    //新建或修改
    public function newAction()
    {
        $city = $this->getAdminCity();
        $shopId = (int)$this->getQuery('sid', 0);
        $marketData = null;
        $shopData = null;
        $activityData = null;
        if ($shopId) {
            $marketData = $this->_getPlayLinkOrganizerShopTable()->getOrganizerList(0,1,array(),array('shop_id' => $shopId))->current();
            $shopData = $this->_getPlayShopTable()->get(array('shop_id' => $shopId));
            $activityData = $this->_getPlayActivityCouponTable()->getActivityList(0, 40, array(), array('type' => 'place', 'cid' => $shopId));
            $city_rid = $shopData->busniess_circle;
        }else{
            $c = $this->_getPlayRegionTable()->fetchLimit(0,1,[],array('acr' => $city,'level' => 3))->current();
            if(!$c){
                $c['rid'] = 100000000;
            }
            $city_rid = $c['rid'];
        }

        //商圈
        $country = floor((int)$city_rid/100000000)*100000000;
        $countrymax = $country+100000000;

        $province = floor((int)$city_rid/1000000)*1000000;
        $provincemax = $province + 1000000;

        $c_rid = floor((int)$city_rid/10000)*10000;
        $citymax = $c_rid + 10000;
        $area_rid = floor((int)$city_rid/100)*100;
        $areamax = $area_rid+100;

        $street_rid = floor((int)$city_rid);

        if($city_rid == $country){
            $level = 1;
        }elseif($city_rid == $province){
            $level = 2;
        }elseif($city_rid == $c_rid){
            $level = 3;
        }elseif($city_rid == $area_rid){
            $level = 4;
        }elseif($city_rid == $street_rid){
            $level = 5;
        }

        $adapter = $this->_getAdapter();//111010000
        $sql = "select * from play_region where level = 1 or ( level = 2 and rid > {$country} and rid < {$countrymax} )
                or (level = 3 and rid > {$province} and rid < {$provincemax})
                or (level = 4 and rid > {$c_rid} and rid < {$citymax})
                or (level = 5 and rid > {$area_rid} and rid < {$areamax});";

        $areadata = $adapter->query($sql, array())->toArray();

        $country_arr = $province_arr = $city_arr = $area_arr = $street_arr = [];
        foreach($areadata as $a){
            if($a['level'] == 1){
                $country_arr[] = $a;
            }
            if($a['level'] == 2 and $level >= 1){
                $province_arr[] = $a;
            }
            if($a['level'] == 3 and $level >= 2){
                $city_arr[] = $a;
            }
            if($a['level'] == 4 and $level >= 3){
                $area_arr[] = $a;
            }
            if($a['level'] == 5 and $level >= 4){
                $street_arr[] = $a;
            }
        }

        //分类
        $labelData = $this->_getPlayLabelTable()->fetchAll(array('status >= ?' => 1, 'label_type <= ?' => 2, 'city' => $city));

        //标签
        $link_tag = array();
        if ($shopId) {
            $city = $shopData->shop_city;
            $link_tag = $this->_getPlayTagsLinkTable()->fetchLimit(0, 100, array('tag_id'), array('link_id' => $shopId, 'tag_type' => 2))->toArray();
        } else {
            $city = $this->getAdminCity();
        }

        $tags = $this->_getPlayTagsTable()->fetchAll(array('tag_city' => $city));

        //积分福利
        $welfareData = array(
            'share' => null,
            'post' => null,
        );

        if ($shopId) {
            $welShare = $this->_getPlayWelfareIntegralTable()->get(array('object_id' => $shopId, 'object_type' => 1, 'welfare_type' => 4, 'status > 0'));
            $welfareData['share'] = $welShare;
            $welPost = $this->_getPlayWelfareIntegralTable()->get(array('object_id' => $shopId, 'object_type' => 1, 'welfare_type' => 3, 'status > 0'));
            $welfareData['post'] = $welPost;
        }

        //攻略
        $strategyData = array();
        if ($shopId) {
            $strategy = $this->_getPlayShopStrategyTable()->fetchAll(array('sid' => $shopId));
            foreach($strategy as $s) {
                $strategyData[] = array(
                    'id' => $s->id,
                    'time' => date('Y-m-d H:i:s', $s->dateline),
                    'title' => $s->title,
                    'month' => $s->suit_month,
                    'editor' => $s->editor,
                    'username' => $s->give_username,
                    'status' => $s->status,
                );
            }
        }

        $vm = new viewModel(
            array(
                'shopData' => $shopData,
                'marketData' => $marketData,
                'city' =>  $city,
                'tags' => $tags,
                'link_tag' => $link_tag,
                'labelData' => $labelData,
                'activityData' => $activityData,
                'url' => $this->_getConfig()['url'],
                'welfare' => $welfareData,
                'strategy' => $strategyData,
                'country_arr' => $country_arr,
                'province_arr' => $province_arr,
                'country' => $country,
                'province' =>$province,
                'city_arr' => $city_arr,
                'street_arr' => $street_arr,
                'area_arr' => $area_arr,
                'c_rid' => $c_rid,
                'city_rid' => $city_rid,
                'area_rid' => $area_rid,
                'street_rid' => $street_rid,
            )
        );
        return $vm;
    }

    //保存
    public function saveAction()
    {
        $shop_id = (int)$this->getPost('shop_id');
        $share_title= $this->getPost('share_title');
        $shop_open = strtotime($this->getPost('shop_open'));
        $shop_close = strtotime($this->getPost('shop_close'));
        $shop_phone = $this->getPost('shop_phone');
        $shop_address = $this->getPost('shop_address');
        $addr_x = $this->getPost('addr_x');
        $addr_y = $this->getPost('addr_y');

        $s1 = $this->getPost('s1');
        $s2 = $this->getPost('s2');
        $s3 = $this->getPost('s3');
        $s4 = $this->getPost('s4');
        $s5 = $this->getPost('s5');
        $busniess_circle = $s5?:$s4?:$s3?:$s2?:$s1;

        $editor_word = $this->getPost('editor_word');
        //$information = $this->getPost('editorValue');
        $reference_price = $this->getPost('reference_price') ? $this->getPost('reference_price') : 0;
        $age_min = $this->getPost('age_min');
        $age_max = $this->getPost('age_max');
        $allow_post = $this->getPost('allow_post');
        $post_award = $this->getPost('post_award');
        $cover = $this->getPost('cover');
        $shop_name = $this->getPost('shop_name');
        $label_id = (int)$this->getPost('label_id');
        $open_time = $this->getPost('open_time');
        $post_area_word =  $this->getPost('post_area_word');

        if ($addr_x == '114.306655' && $addr_y == '30.571659') {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '坐标不对'));
        }

        if ($addr_x < -180 || $addr_x > 180 || $addr_y < -90 || $addr_y > 90) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '坐标不对'));
        }

        if (!$cover) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '封面图片'));
        }

        $cover_class = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $cover);

        $cover_status = $cover_class->scaleResizeImage(720, 360);
        if ($cover_status) {
            $cover_status->save($_SERVER['DOCUMENT_ROOT'] . $cover);
        }


        $thumbnails = $this->getPost('thumbnails') ? $this->getPost('thumbnails') : $this->getPost('cover') . '.min.jpg';
        $thumb = $this->getPost('thumbnails') ? $this->getPost('thumbnails') : $cover;
        $surface_class = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $thumb);

        $surface_status = $surface_class->scaleResizeImage(360, 360);

        if ($surface_status) {
            $surface_status->save($_SERVER['DOCUMENT_ROOT'] . $thumbnails);
        }

        if (!$shop_name) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '商家名称 未填写'));
        }

        $shop_exit = $this->_getPlayShopTable()->get(array('shop_name' => $shop_name, 'shop_status > ?' => -1));
        if ($shop_exit && $shop_exit->shop_id != $shop_id) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '该店铺名称已经存在'));
        }

        if (!$shop_phone) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '商家电话 未填写'));
        }

        if (!$open_time) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '开放时间'));
        }

        if (!$shop_address) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '商家地址 未填写'));
        }

        if (!$addr_x || !$addr_y) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '商家坐标 未填写'));
        }

        if (!$busniess_circle) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '商家商圈 未填写'));
        }

        if ($shop_open >= $shop_close) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '开店 关店 时间不合理'));
        }

        if (!$label_id) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请添加分类'));
        }

        /*if (!trim($information)) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '游玩地图文详情'));
        }*/

        $tagIds = $this->params()->fromPost('tag'); //属性
        if (!count($tagIds)) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '标签必填'));
        }



        $data = array(
            'shop_address' => $shop_address,
            'share_title'=>$share_title,
            'addr_x' => $addr_x,
            'addr_y' => $addr_y,
            'shop_phone' => $shop_phone,
            'shop_open' => $shop_open,
            'shop_close' => $shop_close,
            'shop_name' => $shop_name,
            'busniess_circle' => $busniess_circle,
            'editor_word' => $editor_word,
            // 'information' => $information,
            'reference_price' => $reference_price,
            'age_min' => $age_min,
            'age_max' => $age_max,
            'allow_post' => $allow_post,
            'post_award' => $post_award,
            'cover' => $cover,
            'dateline' => time(),
            'thumbnails' => $thumbnails,
            'label_id' => $label_id,
            'open_time' => $open_time,
            'post_area_word' => $post_area_word,
        );

        if ($shop_id) {
            $status = $this->_getPlayShopTable()->update($data, array('shop_id' => $shop_id));

            $this->doWithTag($shop_id, $tagIds);

            //更新分类关联
            $this->_getPlayLabelLinkerTable()->update(array('lid' => $label_id), array('link_type' => 1, 'object_id' => $shop_id));
            //跟新 game_info
            M::getPlayGameInfoTable()->update(array('shop_name'=>$shop_name),array('shop_id'=>$shop_id));
            
            //操作记录
            $this->adminLog('修改游玩地', 'place', $shop_id);

            return $this->jsonResponsePage(array('status' => 1, 'pid' => $shop_id));

            exit;
        }

        $data['shop_city'] = $this->getAdminCity();

        $status = $this->_getPlayShopTable()->insert($data);

        if ($status) {
            // 分类表关联
            $insert_id = $this->_getPlayShopTable()->getlastInsertValue();
            $this->_getPlayLabelLinkerTable()->insert(array('lid' => $label_id, 'object_id' => $insert_id, 'link_type' => 1));

            //同时创建默认游玩地积分
            $city = $data['shop_city'];
            $timer = time();
            $sql = "INSERT INTO play_welfare_integral (
	id,
	object_id,
	object_type,
	welfare_type,
	`double`,
	limit_num,
	total_num,
	status,
	dateline,
	editor_id,
	editor,
	get_num,
	city
)
VALUES
	(NULL, $insert_id, 1, 3, 1, 1, 1000, 1, {$timer}, {$_COOKIE['id']}, '{$_COOKIE['user']}', 0, '{$city}'),
	(NULL, $insert_id, 1, 4, 1, 1, 1000, 1, {$timer}, {$_COOKIE['id']}, '{$_COOKIE['user']}', 0, '{$city}')";

            $this->query($sql);

            //操作记录
            $this->doWithTag($insert_id, $tagIds);
            $this->adminLog('新建游玩地', 'place', $insert_id);
            return $this->jsonResponsePage(array('status' => 1, 'pid' => $insert_id));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '失败'));
        }
    }

    //删除 状态的变化
    public function changeAction()
    {
        $type = $this->getQuery('type');
        $sid = (int)$this->getQuery('sid');
        if ($type == 'del') {
            $status = $this->_getPlayShopTable()->update(array('shop_status' => -1), array('shop_id' => $sid));
            if ($status) {
                //todo 更改登录 shop_id group  admin_name admin_name
                $shopData = $this->_getPlayShopTable()->get(array('shop_id' => $sid));
                $this->_getPlayAdminTable()->update(array('status' => 0), array('shop_id' => $sid, 'group' => 2, 'admin_name' => $shopData->shop_name));
                //操作记录
                $this->adminLog('删除游玩地', 'place', $sid);
                return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));
            } else {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '失败'));
            }
        }

        if ($type == 'out') {
            $status =  $this->_getPlayShopTable()->get(array('shop_status < ?' => 0, 'shop_id' => $sid));
            if (!$status) {
                return $this->_goto('失败');
            }
            //更新店铺 及登陆
            $this->_getPlayShopTable()->update(array('shop_status' => 0), array('shop_id' => $sid));
            $this->_getPlayAdminTable()->update(array('status' => 1), array('shop_id' => $sid, 'group' => 2, 'admin_name' => $status->shop_name));
            return $this->_goto('成功');
        }
    }

    //处理 标签
    private function doWithTag($shop_id, $tagIds)
    {
        //游玩地的属性
        $this->_getPlayTagsLInkTable()->delete(array('link_id' => $shop_id, 'tag_type' => 2));
        $attribute = $tagIds; //属性
        if (count($attribute)) {
            $i = 1;
            $sql = "INSERT INTO play_tags_link (tag_id, link_id, tag_type) VALUES";
            foreach ($attribute as $val) {
                if ($i == 1) {
                    $sql = $sql."({$val} , {$shop_id}, 2)";
                } else {
                    $sql = $sql.", ({$val} , {$shop_id}, 2)";
                }
                $i++;
            }
            if ($i > 1) {
                $this->query($sql);
            }
        }

        return true;


    }

    public function getShopAction() {
        $k = $this->getQuery('k');
        if ($k) {
            $where = array(
                'shop_city' => $_COOKIE['city'],
                'shop_name like ?' => '%'.$k.'%',
                'shop_status >= ?' => 0,
            );
            $data = $this->_getPlayShopTable()->fetchLimit(0, 15, array(), $where, array());
            $res = array();
            if ($data->count()) {
                foreach ($data as $val) {
                    $res[] = array(
                        'sid' => $val->shop_id,
                        'name' => $val->shop_name,
                    );
                }
            }
            return $this->jsonResponsePage(array('status' => 0, 'data' => $res));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'data' => array()));
        }
    }

    //单个游玩地的积分明细
    public function integralAction() {
        $id = $this->getQuery('sid', 0); //游玩地id
        $shopData = $this->_getPlayShopTable()->get(array('shop_id' => $id));
        if (!$shopData) {
            return $this->_Goto('非法操作');
        }

        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page -1) * $pageSum;
        $integral = $this->_getPlayIntegralTable()->fetchLimit($start, $pageSum, array(), array('object_id' => $id, 'type in (1, 2, 18)'));

        $count = $this->_getPlayIntegralTable()->fetchCount(array('object_id' => $id, 'type in (1, 2, 18)'));
        $url = '/place/integral';
        $paging = new Paginator($page, $count, $pageSum, $url);

        $integral_sql = "SELECT sum(play_integral.total_score) as total_score FROM play_integral WHERE object_id = {$id} AND `type` IN (1, 2, 18)";
        $integralData = $this->query($integral_sql)->current();

        $vm = new viewModel(
            array(
                'total_score' => $integralData['total_score'],
                'shopData' => $shopData,
                'integral' => $integral,
                'pageData' => $paging->getHtml(),
                'city' => $this->getAllCities(),
            )
        );
        return $vm;
    }

    //单个游玩地的评论列表
    public function postAction() {

        $id = (int)$this->getQuery('sid', 0); //游玩地id
        $shopData = $this->_getPlayShopTable()->get(array('shop_id' => $id));
        if (!$shopData) {
            return $this->_Goto('非法操作');
        }

        $page = (int)$this->getQuery('p', 1);
        $start_time = $this->getQuery('start_time');
        $end_time = $this->getQuery('end_time');
        $accept = (int)$this->getQuery('accept');
        $uid = (int)$this->getQuery('uid');
        $username = $this->getQuery('username');

        $pageSum = 10;
        $start = ($page -1) * $pageSum;

        $order = array(
            'status' => -1,
        );

        $where = array(
            'msg_type' => 3,
            'object_data.object_id' => $id,
        );

        if ($start_time && $end_time) {
            $open = (int)strtotime($start_time);
            $end = (int)strtotime($end_time) + 86400;

            if ($end > $start) {
                $where['dateline'] = array('$gt' => $open, '$lt' => $end);
            }

        }

        if ($accept) {
            $where['accept'] = $accept;
        }

        if ($uid) {
            $where['uid'] = $uid;
        }

        if ($username) {
            $where['username'] = $username;
        }



        $data = $this->_getMdbSocialCircleMsg()->find($where)->limit($pageSum)->skip($start)->sort($order);
        $count = $this->_getMdbSocialCircleMsg()->find($where)->count();

        //创建分页
        $url = '/wftadlogin/place/post';
        $paging = new Paginator($page, $count, $pageSum, $url);


        $shopData = $this->_getPlayShopTable()->get(array('shop_id' => $id));
        $listData = array();
        foreach ($data as $res) {


            //现金 现金券
            $id = (string)$res['_id'];
            $rebate_sql = "SELECT SUM(play_account_log.flow_money) AS rebate_money FROM play_account_log WHERE action_type_id = 11 AND object_id = '{$id}'";
            $cash_sql = "SELECT  SUM(play_cashcoupon_user_link.price) AS cash_money FROM play_cashcoupon_user_link WHERE adminid > 0 AND get_type = 9 AND get_object_id = '{$id}'";
            $rebate_data = $this->query($rebate_sql)->current();
            $cash_data = $this->query($cash_sql)->current();
            //积分
            $integral_data = $this->_getPlayIntegralTable()->get(array('object_id' => $id, 'type' => 2, 'uid' => $res['uid']));

            $listData[] = array(
                '_id' => (string)$res['_id'],
                'dateline' => $res['dateline'],
                'uid' => $res['uid'],
                'username' => $res['username'],
                'msg' => $res['msg'],
                'star_num' => $res['star_num'],
                'replay_number' => $res['replay_number'],
                'like_number' => $res['like_number'],
                'accept' => $res['accept'],
                'status' => $res['status'],
                'rebate_money' => $rebate_data['rebate_money'],
                'cash_money' => $cash_data['cash_money'],
                'integral' => $integral_data ? $integral_data->total_score : 0,//$integral['status'],
            );

        }

        $vm = new viewModel(
            array(
                'shopData' => $shopData,
                'data' => $listData,
                'pageData' => $paging->getHtml(),
            )
        );
        return $vm;
    }


    /**
     * //获取单个游玩地的相关信息
     * @param $id
     * @return array
     */
    private function getShopInfo($id) {
        $data = array(
            'good_num' => 0,
            'share_number' => 0,
            'produce_integral' => 0,
            'use_money' => 0,
        );

        //分享数
        $shareData = $this->_getPlayShareTable()->fetchAll(array('type' => 'shop', 'share_id' => $id));
        $data['share_number'] = $shareData->count();

        //商品个数
        $game_ids = $this->_getPlayGameInfoTable()->fetchAll(array('shop_id' => $id));
        $where = array('play_organizer_game.status >= ?' => 0);
        $ids = array();

        if (!$game_ids->count()) {
            $data['good_num'] = 0;
        } else {
            $i=1;
            $m='';
            foreach ($game_ids as $game) {
                if(!in_array($game->gid, $ids)) {
                    $ids[] = $game->gid;
                    if ($i == 1) {
                        $m =  '?';
                    } else {
                        $m = $m.', ?';
                    }
                }
                $i++;
            }

            $where["play_organizer_game.id in ({$m})"] = $ids;
            $goodData = $this->_getPlayOrganizerGameTable()->fetchAll($where);
            $data['good_num'] = $goodData->count();
        }


        //积分数
        /*积分类型 1游玩地分享 2游玩地评论 3商品分享 4商品评论 5商品购买 6邀请好友 7完善资料,8每日签到，9连续签到，10每天任务额外,11更换头像积分,12补充宝宝资料,
13上传宝宝头像,14微信号绑定,15圈子发言,16圈子发言获赞，17商品评论获赞，18游玩地评论获赞,19点击分享获积分,21新手任务额外
这里可以区分是获得还是消费
100退货扣除积分,101积分兑换资格券,102购买商品消耗积分*/
        $integral_sql = "SELECT sum(play_integral.total_score) as total_score FROM play_integral WHERE object_id = {$id} AND `type` IN (1, 2, 18)";
        $integralData = $this->query($integral_sql)->current();
        $data['produce_integral'] = $integralData['total_score'];

        //发放金额

        //返利
        $where = array(
            'msg_type' => 3,
            'object_data.object_id' => (int)$id,
        );

        $object_data = $this->_getMdbSocialCircleMsg()->find($where);
        $object_id = '';
        if (!$object_data->count()) {
            return $data;
        }

        foreach ($object_data as $object) {
            $object_id = $object_id. "'". (string)$object['_id']. "'". ',';
        }

        $object_id = trim($object_id, ',');

        $rebate_sql = "SELECT SUM(play_account_log.flow_money) AS rebate_money FROM play_account_log WHERE action_type_id = 11 AND object_id IN ($object_id)";
        $cash_sql = "SELECT  SUM(play_cashcoupon_user_link.price) AS cash_money FROM play_cashcoupon_user_link WHERE adminid > 0 AND get_type = 9 AND get_object_id IN ($object_id)";

        $rebate_data = $this->query($rebate_sql)->current();
        $cash_data = $this->query($cash_sql)->current();

        $data['use_money'] = $rebate_data['rebate_money'] + $cash_data['cash_money'];
        return $data;
    }
    
    public function updateWeixinAction(){


        $sql = "SELECT
	play_order_action.order_id
FROM
	play_order_action
LEFT JOIN play_order_info ON play_order_action.order_id = play_order_info.order_sn
WHERE play_order_action.play_status = 4 AND play_order_action.dateline > 1458198549  ORDER BY play_order_action.dateline ASC ";

        $data = $this->query($sql);

        $fileName = date('Y-m-d H:i:s', time()). 'back_money为0.csv';
        $head = array(
            '订单id',
            '商品id',
            '商品名称',
            '用户id'
        );

        $content = array();

        foreach ($data as $value) {
            $flag = 0;
            $codeData = $this->_getPlayCouponCodeTable()->fetchAll(array('order_sn' => $value['order_id']));
            foreach ($codeData as $code) {
                $action_note = '退款成功,卡券密码'. $code['id']. $code['password']. ',金额0.00元_自动退款到账户';
                $flag = $this->_getPlayOrderActionTable()->get(array('order_id' => $value['order_id'], 'play_status' => 4, 'action_note' => $action_note));
                if ($flag) {
                    break;
                }
            }

            if ($flag) {
                $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $value['order_id']));
                $content[] = array(
                    $value['order_id'],
                    $orderData->coupon_id,
                    $orderData->coupon_name,
                    $orderData->user_id,
                );
            }
        }

        $out = new OutPut();
        $out->out($fileName, $head, $content);
        exit;

    }
}
