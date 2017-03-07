<?php

namespace Admin\Controller;

use Deyi\JsonResponse;
use Deyi\Paginator;
use Deyi\ImageProcessing;
use Zend\View\Model\ViewModel;

class LabelController extends BasisController
{
    use JsonResponse;

    //主站列表
    public function mainAction(){
        $page = (int)$this->getQuery('p', 1);
        $like = $this->getQuery('k', '');
        $pageSum = 10;
        $where = " 1 =1 ";

        if ($like) {
            $where = $where. " AND play_label_main.tag_name like  '%".$like."%'";
        }

        $start = ($page - 1) * $pageSum;

        $sql = "
SELECT
play_label_main.id,
play_label_main.tag_name,
play_label_main.status,
play_label_main.label_click,
play_label_main.label_type,
COUNT(DISTINCT play_shop.shop_id)AS place_num,
COUNT(DISTINCT play_organizer_game.id)AS good_num
FROM
play_label_main
LEFT JOIN play_label_linker ON play_label_main.id = play_label_linker.lid
LEFT JOIN play_shop ON play_label_linker.object_id = play_shop.shop_id AND play_label_linker.link_type = 1
LEFT JOIN play_organizer_game ON play_label_linker.object_id = play_organizer_game.id AND play_label_linker.link_type = 2
WHERE
$where
GROUP BY
play_label_main.id
ORDER BY
play_label_main.status desc, play_label_main.id DESC
LIMIT {$start}, {$pageSum}";

        $data = $this->query($sql);
        $sql_count = "SELECT play_label_main.id FROM play_label_main WHERE $where";
        $count = $this->query($sql_count)->count();
        $url = '/wftadlogin/label/main';
        $paging = new Paginator($page, $count, $pageSum, $url);


        return array(
            'data' => $data,
            'pageData' => $paging->getHtml(),
            'labelType' => array(
                '1' => '游玩地',
                '2' => '商品和游玩地',
                '3' => '商品',
            ),
        );
    }

    //分站分类列表
    public function indexAction()
    {
        $page = (int)$this->getQuery('p', 1);
        $city = $this->getAdminCity();
        $like = $this->getQuery('k', '');
        $pageSum = 10;
        $where = "play_label.city = '{$city}' AND play_label.status >= 1";

        if ($like) {
            $where = $where. " AND play_label.tag_name like  '%".$like."%'";
        }

        $start = ($page - 1) * $pageSum;

        $sql = "
SELECT
play_label.id,
play_label.tag_name,
play_label.status,
play_label.label_click,
play_label.label_type,
COUNT(DISTINCT play_shop.shop_id)AS place_num,
COUNT(DISTINCT play_organizer_game.id)AS good_num
FROM
play_label
LEFT JOIN play_label_linker ON play_label.id = play_label_linker.lid
LEFT JOIN play_shop ON play_label_linker.object_id = play_shop.shop_id AND play_label_linker.link_type = 1
LEFT JOIN play_organizer_game ON play_label_linker.object_id = play_organizer_game.id AND play_label_linker.link_type = 2
WHERE
$where
GROUP BY
play_label.id
ORDER BY
play_label.dateline DESC
LIMIT {$start}, {$pageSum}";


        $data = $this->query($sql);
        $sql_count = "SELECT play_label.id FROM play_label WHERE $where";
        $count = $this->query($sql_count)->count();
        $url = '/wftadlogin/label';
        $paging = new Paginator($page, $count, $pageSum, $url);


        return array(
            'data' => $data,
            'pageData' => $paging->getHtml(),
            'labelType' => array(
                '1' => '游玩地',
                '2' => '商品和游玩地',
                '3' => '商品',
            ),
        );
    }


    //选择
    public function chooseAction(){
        $page = (int)$this->getQuery('p', 1);
        $like = $this->getQuery('k', '');
        $pageSum = 10;
        $where = " 1 =1 ";

        if ($like) {
            $where = $where. " AND play_label_main.tag_name like  '%".$like."%'";
        }

        $start = ($page - 1) * $pageSum;

        $sql = "
SELECT
play_label_main.id,
play_label_main.tag_name,
play_label_main.status,
play_label_main.description,
play_label_main.label_click,
play_label_main.label_type,
COUNT(DISTINCT play_shop.shop_id)AS place_num,
COUNT(DISTINCT play_organizer_game.id)AS good_num
FROM
play_label_main
LEFT JOIN play_label_linker ON play_label_main.id = play_label_linker.lid
LEFT JOIN play_shop ON play_label_linker.object_id = play_shop.shop_id AND play_label_linker.link_type = 1
LEFT JOIN play_organizer_game ON play_label_linker.object_id = play_organizer_game.id AND play_label_linker.link_type = 2
WHERE
$where
GROUP BY
play_label_main.id
ORDER BY
play_label_main.status desc, play_label_main.id DESC
LIMIT {$start}, {$pageSum}";



        $data = $this->query($sql);
        $sql_count = "SELECT play_label_main.id FROM play_label_main WHERE $where";
        $count = $this->query($sql_count)->count();
        $url = '/wftadlogin/label/choose';
        $paging = new Paginator($page, $count, $pageSum, $url);

        //获取当前站点已经使用的模板
        $use = "select * from play_label where city = '{$this->getAdminCity()}' and status > 0 ";
        $used = $this->query($use);
        $pids = [];

        foreach($used as $u){
            $pids[] = $u['pid'];
        }

        return array(
            'data' => $data,
            'pid' => $pids,
            'pageData' => $paging->getHtml(),
            'labelType' => array(
                '1' => '游玩地',
                '2' => '商品和游玩地',
                '3' => '商品',
            ),
        );
    }

    //使用
    public function douseAction(){
        $id = (int)$this->getQuery('lid', 0);

        $label = $this->_getPlayLabelMainTable()->get(['id'=>$id]);

        $label['pid'] = $id;

        $label['city'] = $this->getAdminCity();
        unset($label['id']);

        $ie = $this->_getPlayLabelTable()->get(['pid'=>$id,'city'=>$label['city']]);

        if($ie){
            return $this->_Goto('已经使用过');
        }
        $label['status'] = 1;//使用未发布
        $label = iterator_to_array($label);
        $status = $this->_getPlayLabelTable()->insert($label);
        if (!$status) {
            return $this->_Goto('失败');
        }
        return $this->_Goto('成功');
    }

    public function newAction() {
        $lid = (int)$this->getQuery('lid');
        $city = $this->getAdminCity();

        $data = array();
        $shopData = array();
        $gameData = array();
        $pdata = [];
        if ($lid) {
            if($city==1){
                $data = $this->_getPlayLabelMainTable()->get(array('id' => $lid));
            }else{
                $data = $this->_getPlayLabelTable()->get(array('id' => $lid));
                $pdata = $this->_getPlayLabelMainTable()->get(array('id' => $data->pid));
            }

           // $shopData = $this->_getPlayLabelLinkerTable()->getShopList($start = 0, $pagesum = 0, $columns = array(), $where = array('lid' => $lid, 'link_type' => 1), $order = array('sort' => 'desc'));
           // $gameData = $this->_getPlayLabelLinkerTable()->getGameList($start = 0, $pagesum = 0, $columns = array(), $where = array('lid' => $lid, 'link_type' => 2), $order = array('sort' => 'desc'));
        }

        $vm = new ViewModel(
            array(
                'data' => $data,
                'pdata' => $pdata?:[],
                'city' => $this->getAllCities()[$city],
//                'shopData' => $shopData,
//                'gameData' => $gameData,
                'labelType' => array(
                    '1' => '游玩地',
                    '2' => '商品和游玩地',
                    '3' => '商品',
                ),
            )
        );
        return $vm;
    }

    public function saveAction() {

        $tag_name = $this->getPost('tag_name');
        $description = $this->getPost('description');
        $label_type = $this->getPost('label_type');
        $lid = (int)$this->getPost('lid');

        $city = $this->getAdminCity();
        if($city!=1 && !$lid){//分站不能添加
            return $this->jsonResponsePage(array('status' => 0, 'message' => '保存失败'));
        }

        $cover = $this->getPost('cover');
        $coin = $this->getPost('surface_plot');
        if (!$cover) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '封面图片'));
        }
        if (!$coin) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请上传圆形图'));
        }
        if (!$description) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请写标签描述'));
        }
        if (!$tag_name) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请写标签名称'));
        }

        $cover_class = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $cover);
        $cover_status = $cover_class->scaleResizeImage(720,360);
        if ($cover_status) {
            $cover_status->save($_SERVER['DOCUMENT_ROOT'] . $cover);
        }

        $surface_class = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $coin);
        $surface_status = $surface_class->scaleResizeImage(140,140);
        if ($surface_status) {
            $surface_status->save($_SERVER['DOCUMENT_ROOT'] . $coin);
        }

        $data = array(
            'tag_name'  => $tag_name,
            'description' => $description,
            'dateline' => time(),
            'city' => $city,
            'cover' => $cover,
            'coin'  => $coin,
            'label_type' => $label_type,
        );

        if ($lid) {
            if($city==1){
                $status = $this->_getPlayLabelMainTable()->update($data, array('id' => $lid));
            }else{
                $status = $this->_getPlayLabelTable()->update($data, array('id' => $lid));
            }
        } else {
            $status = $this->_getPlayLabelMainTable()->insert($data);
        }

        if (!$status) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '保存失败'));
        }

        return $this->jsonResponsePage(array('status' => 1, '成功'));
    }

    //主站的编辑
    public function changeAction() {
        $type = $this->getQuery('type');
        $lid = $this->getQuery('lid');
        if ($type == 'del') {
            $ie = $this->_getPlayLabelTable()->get(array('status > ?' => 0), array('pid' => $lid));
            if($ie){
                return $this->_Goto('删除失败,请确认所有分站已取消对这个分类的使用');
            }

            $status = $this->_getPlayLabelMainTable()->update(array('status' => 0), array('id' => $lid));
            if (!$status) {
                return $this->_Goto('删除失败');
            }
            //todo 删除关联表
            $this->_getPlayLabelLinkerTable()->delete(array('lid' => $lid));
            return $this->_Goto('成功');
        }

        if ($type == 'open') {
            $status = $this->_getPlayLabelMainTable()->update(array('status' => 1), array('id' => $lid));
            if (!$status) {
                return $this->_Goto('恢复失败');
            }
            //todo 删除关联表
            $this->_getPlayLabelLinkerTable()->delete(array('lid' => $lid));
            return $this->_Goto('成功');
        }

        return $this->_Goto('非法操作');

    }

    //分站的编辑
    public function editAction() {
        $type = $this->getQuery('type');
        $lid = $this->getQuery('lid');
        if ($type == 'del') {

            $status = $this->_getPlayLabelTable()->update(array('status' => 0), array('id' => $lid));
            if (!$status) {
                return $this->_Goto('删除失败');
            }
            //todo 删除关联表
            //$this->_getPlayLabelTable()->delete(array('lid' => $lid));
            $this->_getPlayLabelLinkerTable()->delete(array('lid' => $lid));
            return $this->_Goto('成功');
        }

        if ($type == 'first') {
            $data = $this->_getPlayLabelTable()->get(array('id' => $lid));
            if (!$data) {
                return $this->_Goto('非法操作');
            }

            if ($data->status != 3) {
                $num = $this->_getPlayLabelTable()->fetchCount(array('status' => 3, 'city' => $_COOKIE['city']));
                if ($num > 7) {
                    return $this->_Goto('发现分类已经够多了');
                }
            }

            $status = $this->_getPlayLabelTable()->update(array('status' => ($data->status != 3) ? 3 : 2), array('id' => $lid));
            if (!$status) {
                return $this->_Goto('失败');
            }
            return $this->_Goto('成功');
        }

        if ($type == 'push') {
            $data = $this->_getPlayLabelTable()->get(array('id' => $lid));
            if (!$data) {
                return $this->_Goto('非法操作');
            }

            $status = $this->_getPlayLabelTable()->update(array('status' => ($data->status == 1) ? 2 : 1), array('id' => $lid));
            if (!$status) {
                return $this->_Goto('失败');
            }
            return $this->_Goto('成功');
        }

        return $this->_Goto('非法操作');

    }

    public function linkAction() {
        $page = (int)$this->getQuery('p', 1);
        $lid = (int)$this->getQuery('lid');
        if (!$lid) {
            return $this->_Goto('非法操作');
        }
        $tag_data = $this->_getPlayLabelTable()->get(array('id' => $lid));
        if (!$tag_data) {
            return $this->_Goto('非法操作');
        }

        //$link_ids = json_decode($tag_data->object_id, true);
        $link_idc = array();
        $label_linker = $this->_getPlayLabelLinkerTable()->fetchAll(array('lid' => $lid));
        foreach($label_linker as $la) {
            $link_idc[] = $la->object_id;
        }


        $pagesum = 10;
        $where = array(
            'shop_status >= ?' => 0,
        );
        //搜索
        $like = $this->getQuery('k', '');
        if ($like) {
            $where['shop_name like ?'] = '%'.$like.'%';
        }

        $start = ($page - 1) * $pagesum;
        $order = array('dateline' => 'desc');
        $data =  $this->_getPlayShopTable()->fetchLimit($start, $pagesum, array(), $where, $order);
        //获得总数量
        $count = $this->_getPlayShopTable()->fetchCount($where);
        //创建分页
        $url = '/wftadlogin/label/link';
        $paginator = new Paginator($page, $count, $pagesum, $url);
        return array(
            'data' => $data,
            'pageData' => $paginator->getHtml(),
            //'link_ids' => $link_ids ? $link_ids : array(),
            'link_ids' => $link_idc,
        );
    }

    public function linkDoAction()
    {
        $type = $this->getQuery('type');
        $lid = $this->getQuery('lid');

        if (!$lid || !$type) {
            return $this->_Goto('非法操作');
        }

        if ($type == 1) {//排序
            $shop_id = $this->getQuery('sid');
            $sort = (int)$this->getQuery('oid');
            $labelLinkData = $this->_getPlayLabelLinkerTable()->get(array('lid' => $lid, 'object_id' => $shop_id));
            if (!$labelLinkData) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
            }
            $this->_getPlayLabelLinkerTable()->update(array('sort' => $sort), array('lid' => $lid, 'object_id' => $shop_id));
            return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));

        } elseif ($type == 2) {//更新关联原因
            $shop_id = $this->getQuery('sid');
            $labelLinkData = $this->_getPlayLabelLinkerTable()->get(array('lid' => $lid, 'object_id' => $shop_id));
            if (!$labelLinkData) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
            }
            $words = $this->getPost('words');
            $this->_getPlayLabelLinkerTable()->update(array('words' => $words), array('lid' => $lid, 'object_id' => $shop_id));
            return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));

        } elseif ($type == 3) { // 绑定游玩地到标签
            //todo 更新 object_id  更新表广联表 label_link

            $shop_id = $this->getQuery('sid');
            $labelData = $this->_getPlayLabelTable()->get(array('id' => $lid));
            if (!$labelData || $labelData->status <= 0) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
            }
            $link_idc = array();
            $label_linker = $this->_getPlayLabelLinkerTable()->fetchAll(array('lid' => $lid));
            foreach($label_linker as $la) {
                $link_idc[] = $la->object_id;
            }

            //$shopIds = $labelData->object_id ? json_decode($labelData->object_id) : array();
            if (in_array($shop_id, $link_idc)) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '该游玩地已经关联了'));
            }
            array_push($link_idc, $shop_id);
            $words = $this->getPost('words');
            $this->_getPlayLabelTable()->update(array('object_id' => json_encode($link_idc)), array('id' => $lid));
            $this->_getPlayLabelLinkerTable()->insert(array(
                'lid' => $lid,
                'object_id' => $shop_id,
                'words' => $words,
            ));
            return $this->jsonResponsePage(array('status' => 1, 'message' => '成功', 'url' => '/wftadlogin/label/new?lid='.$lid));
       } elseif ($type == 4) { //删除关联
            $object_id = $this->getQuery('object_id');
            //$aData = json_decode($this->_getPlayLabelTable()->get(array('id' => $lid))->object_id);

            /*if (!in_array($object_id, $aData)) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
            }*/

            $status = $this->_getPlayLabelLinkerTable()->delete(array('lid' => $lid, 'object_id' => $object_id));
            if ($status) {
                $link_idc = array();
                $label_linker = $this->_getPlayLabelLinkerTable()->fetchAll(array('lid' => $lid));
                foreach($label_linker as $la) {
                    $link_idc[] = $la->object_id;
                }
                $this->_getPlayLabelTable()->update(array('object_id' => json_encode($link_idc)), array('id' => $lid));
                return $this->jsonResponsePage(array('status' => 1, 'message' => '成功1'));
            } else {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '失败'));
            }
        }
        return $this->_Goto('非法操作');
    }

    public function listAction() {
        $id = $this->getQuery('lid');
        $page = $this->getQuery('page' , 1);
        $pageSum = 10;
        $res = array();
        $tagData = $this->_getPlayLabelTable()->get(array('id' => $id));
        $mer = implode(',', json_decode($tagData->object_id));
        if($mer) {
            $coupon_where = array(
                "play_coupons_linker.shop_id in ($mer)",
                'play_coupons.coupon_status = ?' => 1,
                'play_coupons.coupon_starttime <= ?' => time(),
                '(play_coupons.coupon_total - play_coupons.coupon_buy) > ?' => 0,
            );
            if (count(json_decode($tagData->object_id))) {
                $couponData = $this->_getPlayCouponsLinkerTable()->getTagCoupon(($page-1)*$pageSum, $pageSum, $columns = array(), $coupon_where, $order = array());
                foreach ($couponData as $val) {
                    $res[] = array(
                        'id' => $val->coupon_id,
                        'coupon_name' => $val->coupon_name,
                        'mid' => $val->coupon_marketid,
                    );
                }
            }

            //获得总数量
            $count = $this->_getPlayCouponsLinkerTable()->getTagCoupon(0, 0, array(), $coupon_where, $order)->count();
        } else {
            $count = 0;
        }
         
        //创建分页
        $url = '/wftadlogin/label/list';
        $paginator = new Paginator($page, $count, $pageSum, $url);


        return array(
            'data' => $res,
            'pageData' => $paginator->getHtml(),
        );
    }

    public function linkgAction() {
        $lid = $this->getQuery('lid', 0);
        $like = $this->getQuery('k', '');

        $data = array();

        if ($like) {
            $where = array(
                'play_organizer_game.status >= ?' => 0,
                'play_organizer_game.city = ?' => $_COOKIE['city'],
                'play_organizer_game.title like ?' => '%'.$like.'%',
            );
            $data = $this->_getPlayOrganizerGameTable()->fetchAll($where);
        }

        $gameIds = $this->_getPlayLabelLinkerTable()->fetchAll(array('lid' => $lid, 'link_type' => 2));

        $game_ids = array();
        foreach ($gameIds as $v) {
            $game_ids[] = $v->object_id;
        }

        return array(
            'data' => $data,
            'gameIds' => $game_ids,
            'lid' => $lid,
        );
    }

    public function doGameAction() {
        $type = $this->getQuery('type');
        $lid = (int)$this->getQuery('lid');
        $gid = $this->getQuery('gid');
        $flag = $this->_getPlayLabelLinkerTable()->get(array('lid' => $lid, 'object_id' => $gid, 'link_type' => 2));
        if ($type == 'sort') {
            //todo 排序
        }

        if ($type == 'add') {
            if ($flag) {
                return $this->_Goto('非法操作');
            }
            $status = $this->_getPlayLabelLinkerTable()->insert(array('lid' => $lid, 'object_id' => $gid, 'link_type' => 2));
        }

        if ($type == 'del') {
            if (!$flag) {
                return $this->_Goto('非法操作');
            }
            $status = $this->_getPlayLabelLinkerTable()->delete(array('lid' => $lid, 'object_id' => $gid, 'link_type' => 2));
        }

        if ($status) {
            //todo 更新 label表里面的good_id
        }
        return $this->_Goto($status ? '成功' : '失败');
        exit;
    }


}

