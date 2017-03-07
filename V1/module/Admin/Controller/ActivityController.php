<?php

namespace Admin\Controller;

use Deyi\BaseController;
use Deyi\GetCacheData\CityCache;
use Deyi\JsonResponse;
use Deyi\Paginator;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Deyi\ImageProcessing;
use Zend\Db\Sql\Expression;

class ActivityController extends BasisController
{
    use JsonResponse;

    //专题 展示类型
    public $viewType = array(
        '1' => '混合, 游玩地优先',
        '2' => '混合, 商品优先',
        '3' => '仅游玩地',
        '4' => '仅商品',
        '5' => '混合, 活动优先',
        '6' => '仅活动',
    );

    //专题 类型
    public $type = array(
        '1' => '一元手慢无',
        '2' => '周末去哪儿',
        '3' => '一般专题',
    );

    //专题列表
    public function indexAction() {

        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;

        $city = $this->chooseCity();

        if($city){
            $citysql =  " play_activity.ac_city = '{$city}'";
        }else{
            $citysql = ' 1=1 ';
        }

        $where =  $citysql." AND play_activity.status >= -1";
        $type = $this->getQuery('type', 0);
        $view = $this->getQuery('view', 0);
        $like = $this->getQuery('activity_name', '');
        if ($type) {
            $where = $where." AND play_activity.ac_type = {$type}";
        }

        if ($like) {
            $where = $where. " AND play_activity.ac_name like  '%".$like."%'";
        }

        if ($view) {
            $where = $where. " AND play_activity.view_type = {$view}";
        }


        $sql = "SELECT
play_activity.id,
play_activity.ac_name,
play_activity.ac_city,
play_activity.ac_type,
play_activity.view_type,
play_activity.activity_click,
play_activity.status,
play_activity.e_time,
play_activity.s_time,
play_activity.discovery
FROM
play_activity
WHERE
$where
GROUP BY
play_activity.id
ORDER BY
play_activity.id  DESC
LIMIT {$start}, {$pageSum}
";

        $data = $this->query($sql);
        $sql_count = "SELECT play_activity.id FROM play_activity WHERE $where";
        $count = $this->query($sql_count)->count();
        $url = '/wftadlogin/activity';
        $paginator = new Paginator($page, $count, $pageSum, $url);

        return array(
            'data' => $data,
            'pagedata' => $paginator->getHtml(),
            'city' => $this->getAllCities(),
            'viewType' => $this->viewType,
            'type' => $this->type,
            'filtercity' => CityCache::getFilterCity($city),
        );
    }

    public function newAction() {
        $aid = (int)$this->getQuery('aid');
        $city = $_COOKIE['city'];
        if (!in_array($city, array_flip($this->_getConfig()['city']))) {
            return $this->_Goto('非法操作');
        }
        $data = array();
        $couponData = array();
        $placeData = array();
        $gameData = array();
        $excerciseData = array();
        if ($aid) {
            $data = $this->_getPlayActivityTable()->get(array('id' => $aid));
            $placeData = $this->_getPlayActivityCouponTable()->getPlaceList($start = 0, $pagesum = 0, $columns = array(), $where = array('aid' => $aid, 'type' => 'place'), $order = array('ac_sort' => 'asc'));
            $gameData = $this->_getPlayActivityCouponTable()->getGameList($start = 0, $pagesum = 0, $columns = array(), $where = array('aid' => $aid, 'play_activity_coupon.type' => 'game'), $order = array('ac_sort' => 'asc'));
            $excerciseData = $this->_getPlayActivityCouponTable()->getExcerciseList($start = 0, $pagesum = 0, $columns = array(), $where = array('aid' => $aid, 'play_activity_coupon.type' => 'excercise'), $order = array('ac_sort' => 'asc'));
        }

        $vm = new viewModel(
            array(
                'data' => $data,
                'placeData' => $placeData,
                'gameData' => $gameData,
                'excerciseData'=>$excerciseData,
                'ac_type' => $this->_getConfig()['theme_type'],
                'view_type' => $this->viewType,
            )
        );
        return $vm;
    }

    public function saveActivityAction() {

        $aid = (int)$this->getPost('aid');
        $ac_name = $this->getPost('ac_name');
        $ac_cover = $this->getPost('ac_cover');
        $introduce = $this->getPost('introduce');
        $allow_post = $this->getPost('allow_post');
        $tags = $this->params()->fromPost('tags');

        $ac_long = $this->getPost('ac_long');
        if ($ac_long == 1) {
            $s_time = 0;
            $e_time = 0;
        } elseif($ac_long == 2) {
            $s_time = strtotime($this->getPost('s_time').$this->getPost('s_timel'));
            $e_time = strtotime($this->getPost('e_time').$this->getPost('e_timel'));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
        }
        $uid = $_COOKIE['id'];
        $ac_type = $this->getPost('ac_type');
        $view_type = $this->getPost('view_type');
        $ac_city = $this->getPost('ac_city');

        if (!$ac_name) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '专题名称'));
        }
        if (!$introduce) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '专题介绍'));
        }
        if ($e_time < $s_time) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '时间'));
        }
        if ($tags) {
            $tags = json_encode($tags);
        } else {
            $tags ='';
        }

        if (!$ac_cover) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '活动图片'));
        }
        $cover_class = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $ac_cover);
        $cover_status = $cover_class->scaleResizeImage(720, 360);
        if ($cover_status) {
            $cover_status->save($_SERVER['DOCUMENT_ROOT'] . $ac_cover);
        }

        // 生成分享图片
        $share_img = $ac_cover . '.share.jpg';
        $share_class = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $ac_cover);
        $share_status = $share_class->MaxSquareZoomResizeImage(360);
        if ($share_status) {
            $share_status->save($_SERVER['DOCUMENT_ROOT'] . $share_img);
        }

        $aData = array(
            'ac_name' => $ac_name,
            'ac_cover' => $ac_cover,
            'introduce' => $introduce,
            'ac_city' => $ac_city,
            'ac_type' => $ac_type,
            'view_type' => $view_type,
            'uid' => $uid,
            's_time' => $s_time,
            'e_time' => $e_time,
            'tags' => $tags,
            'dateline' => time(),
            'allow_post' => (int)$allow_post,
        );

        if ($aid) {
            $this->_getPlayActivityTable()->update($aData, array('id' => $aid));
        } else {
            $this->_getPlayActivityTable()->insert($aData);
        }
        return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));

    }

    //专题 上下架
    public function changeActivityAction() {
        $aid = (int)$this->getQuery('aid');
        $stu = (int)$this->getQuery('status') ? 0 : -1;
        $type = $this->getQuery('type', 1);
        if ($type ==1) {
            $status = $this->_getPlayActivityTable()->update(array('status' => $stu), array('id' => $aid));
            if ($status) {
                return $this->_Goto('成功');
            } else {
                return $this->_Goto('失败');
            }
        } elseif ($type == 2) {
            $status = $this->_getPlayActivityTable()->update(array('status' => -2), array('id' => $aid));
            if ($status) {
                return $this->_Goto('成功');
            } else {
                return $this->_Goto('失败');
            }
        }
    }

    public function doSortAction() {
        $id = $this->getQuery('id', 0);
        $ac_sort = ($this->getQuery('ac_sort')) ? 0 : 3;
        $type = $this->getQuery('type', 1);
        if ($type == 1) {
            $status = $this->_getPlayActivityCouponTable()->update(array('ac_sort' => $ac_sort), array('id' => $id));
            if ($status) {
                return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));
            } else {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '成功'));
            }
        } elseif ($type == 2) {
            $status = $this->_getPlayActivityTable()->update(array('ac_sort' => $ac_sort), array('id' => $id));
            if ($status) {
                return $this->_Goto('成功');
            } else {
                return $this->_Goto('失败');
            }
        }
    }

    private function _scaleImage($in, $outfile, $widthmax, $heightmax, $imagex, $imagey)
    {
        if ($imagex/$widthmax >= $imagey/$heightmax) {
            $imagexa = $imagey*$widthmax/$heightmax;
            $imageya = $imagey;
            $start_y=0;
            $start_x = ($imagex - $imagexa) / 2;
        } else {
            $imageya = $imagex*($heightmax/$widthmax);
            $imagexa = $imagex;
            $start_x = 0;
            $start_y = ($imagey - $imageya) / 2;
        }

        $in = imagecreatefromstring($in);
        $tc = imagecreatetruecolor($widthmax, $heightmax); //创建空白图片
        imagecopyresampled($tc, $in, 0, 0,$start_x, $start_y, $widthmax, $heightmax, $imagexa, $imageya);  //copy 图片,重新生成
        $status = imagejpeg($tc, $outfile, 100);
        return $status;
    }

    public function setScaleImage($data)
    {
        if (!is_file($_SERVER['DOCUMENT_ROOT'] . $data)) {
            return false;
        }
        $img_info = getimagesize($_SERVER['DOCUMENT_ROOT'] . $data);
        if ($img_info[0] == 2*$img_info[1]) {
            return false;
        } elseif ($img_info[0] < 2*$img_info[1]) {
            $img_x = $img_info[0];
            $img_y = 1/2*$img_info[0];
        } else {
            $img_x = 2*$img_info[1];
            $img_y = $img_info[1];
        }
        $new_image_name = $_SERVER['DOCUMENT_ROOT']. $data. '.thumb.jpg';
        $s = $this->_scaleImage(file_get_contents($_SERVER['DOCUMENT_ROOT'] . $data), $new_image_name, $img_x, $img_y, $img_info[0], $img_info[1]);
        return $s;
    }

    //删除绑定的卡券
    public function outLinkAction() {
        $aid = $this->getQuery('aid');
        $cid = $this->getQuery('cid');
        $status = $this->_getPlayActivityCouponTable()->delete(array('aid' => $aid,'cid' => $cid, 'type' => 'coupon'));
        $aData = $this->_getPlayActivityTable()->get(array('id' => $aid));
        if ($status) {
            if ($aData->count_number) {
                $this->_getPlayActivityTable()->update(array('count_number' => ($aData->count_number - 1)), array('id' => $aid));
            }
            return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '失败'));
        }
    }

    //绑定卡券列表
    public function linkerAction() {
        $page = (int)$this->getQuery('p', 1);
        $aid = $this->getQuery('aid', 0);
        if (!$aid) {
            return $this->_Goto('非法操作');
        }
        $pagesum = 10;
        $where = array();
        //搜索
        $like = $this->getQuery('k', '');
        if ($like) {
            $where['coupon_name like ? or coupon_marketname like ?'] = array('%'.$like.'%', '%'.$like.'%');
        }
        //状态
        $status = $this->getQuery('status');
        if ($status) {
            if ($status == 1) {
                //$where['coupon_endtime < ?'] = time();
                //$where['coupon_status > ?'] = -1;

                $where["coupon_status > ? && ((coupon_endtime < ? )|| (coupon_total = coupon_buy) )"] = array(0, time());
            } elseif ($status == 2) {
                $where['coupon_status = ? && coupon_total > coupon_buy  && coupon_endtime > ? && coupon_starttime < ?'] = array(1, time(), time());
            } elseif ($status == 3) {
                $where['coupon_status = ?'] = 0;
            } elseif ($status == 4) {
                $where['coupon_uptime < ?'] = time();
                $where['coupon_starttime > ?'] = time();
                $where['coupon_status > ?'] = 0;
            } elseif ($status == 5) {
                $where['coupon_uptime > ?'] = time();
                $where['coupon_status > ?'] = 0;
            }
        } else {
            $where['coupon_status > ?'] = -1;
        }

        $couponIds = $this->_getPlayActivityCouponTable()->fetchAll(array('aid' => $aid, 'type' => 'coupon'));
        $copIds = array();
        foreach ($couponIds as $k) {
            $copIds[] = $k->cid;
        }

        $start = ($page - 1) * $pagesum;
        $data =  $this->_getPlayCouponsTable()->getCouponsList($start, $pagesum, array(), $where, array('coupon_id' => 'desc'));
        //获得总数量
        $count = $this->_getPlayCouponsTable()->getCouponsList(0, 0, array(), $where, array('coupon_id' => 'desc'))->count();
        //创建分页
        $url = '/wftadlogin/activity/linker';
        $paginator = new Paginator($page, $count, $pagesum, $url);
        return array(
            'data' => $data,
            'pagedata' => $paginator->getHtml(),
            'couponIds' => $copIds,
        );

    }

    //添加绑定卡券
    public function doLinkAction() {
        $aid = $this->getQuery('aid', 0);
        $type = $this->getQuery('type', 0);
        if (!$aid || !$type) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
        }
        $coupon_id = $_COOKIE['link_coupId'.$aid];
        if (!$coupon_id) {
            if ($type == 3) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '请先添加卡券'));
            }
            $coupon_ids = array();
        } else {
            $coupon_ids =  explode(',', $coupon_id);
        }

        if ($type == 1) {
            $cid = $this->getPost('coupon_id');
            if (!in_array($cid, $coupon_ids)) {
                setcookie('link_coupId'.$aid,$coupon_id ? $coupon_id.','.$cid : $cid,time()+3600);
            }
            return $this->jsonResponsePage(array('status' => 1));
        } elseif ($type == 2) {
            $cid = $this->getPost('coupon_id');
            if (in_array($cid, $coupon_ids)) {
                setcookie('link_coupId'.$aid,implode(',', array_diff($coupon_ids, array($cid))),time()+3600);
            }
            return $this->jsonResponsePage(array('status' => 1));
        }elseif ($type == 3) {
            $act_type = $this->_getPlayActivityTable()->get(array('id' => $aid))->ac_type;
            $couponIds = $this->_getPlayActivityCouponTable()->fetchAll(array('aid' => $aid, 'type' => 'coupon'));
            $copIds = array();
            foreach ($couponIds as $k) {
                $copIds[] = $k->cid;
            }
            $cIds = array_diff($coupon_ids, $copIds);
            if (!count($cIds)) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '该卡券已绑定该专题'));
            }
            foreach ($cIds as $val) {
                $this->_getPlayActivityCouponTable()->insert(array('aid' => $aid, 'cid' => $val, 'act_type' => $act_type,'type' => 'coupon'));
            }

            /*$this->_getPlayActivityTable()->update(array('count_number' => new Expression('count_number' + count($cIds)), 'dateline' => time()), array('id' => $aid));*/
            //todo 2周后 启用上面

            $this->_getPlayActivityTable()->update(array('count_number' => ($this->_getPlayActivityCouponTable()->fetchCount(array('aid' => $aid))), 'dateline' => time()), array('id' => $aid));

            setcookie('link_coupId'.$aid,'',time()-60);
            $data = $this->_getPlayActivityTable()->get(array('id' => $aid));
            return $this->jsonResponsePage(array('status' => 1, 'href' => '/wftadlogin/activity?type='.$data->ac_type.'&city='.$data->ac_city));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
        }

    }

    //绑定资讯列表
    public function newsAction() {
        $page = (int)$this->getQuery('p', 1);
        $aid = (int)$this->getQuery('aid', 1);
        $pagesum = 10;
        $where = array();
        //搜索
        $like = $this->getQuery('k', '');
        if ($like) {
            $where['title like ?'] = '%'.$like.'%';
        }

        //状态
        $status = $this->getQuery('status');
        if ($status) {
            if ($status == 1) {
                $where['play_news.status >= ?'] = 0;
            } elseif ($status == 2) {
                $where['play_news.status = ?'] = 0;
            } elseif ($status == 3) {
                $where['play_news.status = ?'] = 1;
            } else {
                $where['play_news.status >= ?'] = 0;
            }
        } else {
            $where['play_news.status >= ?'] = 0;
        }

        $start = ($page - 1) * $pagesum;
        $order = array('dateline' => 'desc');
        $data =  $this->_getPlayNewsTable()->getAdminNewsList($start, $pagesum, array(), $where, $order);
        //获得总数量
        $count = $this->_getPlayNewsTable()->getAdminNewsList(0, 0, array(), $where, $order)->count();
        //创建分页
        $url = '/wftadlogin/news';
        $paginator = new Paginator($page, $count, $pagesum, $url);

        $nIds = $this->_getPlayActivityCouponTable()->fetchAll(array('aid' => $aid, 'type' > 'new'));
        $newsIds = array();
        foreach ($nIds as $k) {
            $newsIds[] = $k->cid;
        }

        return array(
            'data' => $data,
            'pagedata' => $paginator->getHtml(),
            'newsIds' => $newsIds,
        );
    }

    //绑定资讯操作
    public function doNewsAction() {

        $aid = $this->getQuery('aid', 0);
        $type = $this->getQuery('type', 0);
        if (!$aid || !$type) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
        }
        $coupon_id = $_COOKIE['link_newId'.$aid];
        if (!$coupon_id) {
            if ($type == 3) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '请先添加卡券'));
            }
            $coupon_ids = array();
        } else {
            $coupon_ids =  explode(',', $coupon_id);
        }

        if ($type == 1) { //选中复选框  添加cookie
            $cid = $this->getPost('news_id');
            if (!in_array($cid, $coupon_ids)) {
                setcookie('link_newId'.$aid,$coupon_id ? $coupon_id.','.$cid : $cid,time()+3600);
            }
            return $this->jsonResponsePage(array('status' => 1));
        } elseif ($type == 2) { //去掉复选框 删除cookie
            $cid = $this->getPost('news_id');
            if (in_array($cid, $coupon_ids)) {
                setcookie('link_newId'.$aid,implode(',', array_diff($coupon_ids, array($cid))),time()+3600);
            }
            return $this->jsonResponsePage(array('status' => 1));
        } elseif ($type == 3) { // 绑定资讯到专题
            $act_type = $this->_getPlayActivityTable()->get(array('id' => $aid))->ac_type;
            $couponIds = $this->_getPlayActivityCouponTable()->fetchAll(array('aid' => $aid, 'type' => 'news'));
            $copIds = array();
            foreach ($couponIds as $k) {
                $copIds[] = $k->cid;
            }
            $cIds = array_diff($coupon_ids, $copIds);
            if (!count($cIds)) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '该资讯已绑定该专题'));
            }
            foreach ($cIds as $val) {
                $this->_getPlayActivityCouponTable()->insert(array('aid' => $aid, 'cid' => $val, 'act_type' => $act_type, 'type' => 'news'));
            }

            /*$this->_getPlayActivityTable()->update(array('count_number' => new Expression('count_number' + count($cIds)), 'dateline' => time()), array('id' => $aid));*/
            //todo 2周后 启用上面

            $this->_getPlayActivityTable()->update(array('count_number' => ($this->_getPlayActivityCouponTable()->fetchCount(array('aid' => $aid))), 'dateline' => time()), array('id' => $aid));
            setcookie('link_newId'.$aid,'',time()-60);
            $data = $this->_getPlayActivityTable()->get(array('id' => $aid));
            return $this->jsonResponsePage(array('status' => 1, 'href' => '/wftadlogin/activity?type='.$data->ac_type.'&city='.$data->ac_city));
        } elseif ($type == 4) { //删除绑定
            $aid = $this->getQuery('aid');
            $cid = $this->getQuery('cid');
            $status = $this->_getPlayActivityCouponTable()->delete(array('aid' => $aid,'cid' => $cid, 'type' => 'news'));
            $aData = $this->_getPlayActivityTable()->get(array('id' => $aid));
            if ($status) {
                if ($aData->count_number) {
                    $this->_getPlayActivityTable()->update(array('count_number' => ($aData->count_number - 1)), array('id' => $aid));
                }
                return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));
            } else {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '失败'));
            }
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
        }

    }

    public function linkpAction() {

        $aid = $this->getQuery('aid', 0);
        $like = $this->getQuery('k', '');

        $where = array();
        $data = array();

        if ($like) {
            $where['shop_name like ?'] = '%'.$like.'%';
            $where['shop_status > ?'] = -1;
            $where['shop_city'] = $_COOKIE['city'];

            $data = $this->_getPlayShopTable()->fetchAll($where);
        }

        $placeIds = $this->_getPlayActivityCouponTable()->fetchAll(array('aid' => $aid, 'type' => 'place'));

        $place_ids = array();
        foreach ($placeIds as $k) {
            $place_ids[] = $k->cid;
        }

        return array(
            'data' => $data,
            'placeIds' => $place_ids,
            'aid' => $aid,
        );
    }

    public function doPlaceAction() {
        $type = $this->getQuery('type');
        $aid = (int)$this->getQuery('aid');
        $pid = $this->getQuery('pid');
        $act_type = $this->_getPlayActivityTable()->get(array('id' => $aid))->ac_type;
        $flag = $this->_getPlayActivityCouponTable()->get(array('aid' => $aid, 'cid' => $pid, 'type' => 'place'));
        if ($type == 'add') {
            if ($flag) {
                return $this->_Goto('非法操作');
            }
            $status = $this->_getPlayActivityCouponTable()->insert(array('aid' => $aid, 'cid' => $pid, 'act_type' => $act_type, 'type' => 'place'));
            return $this->_Goto($status ? '成功' : '失败');
        }

        if ($type == 'del') {
            if (!$flag) {
                return $this->_Goto('非法操作');
            }
            $status = $this->_getPlayActivityCouponTable()->delete(array('aid' => $aid, 'cid' => $pid, 'type' => 'place'));
            return $this->_Goto($status ? '成功' : '失败');
        }

        exit;
    }

    public function linkgAction() {

        $aid = $this->getQuery('aid', 0);
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

        $gameIds = $this->_getPlayActivityCouponTable()->fetchAll(array('aid' => $aid, 'type' => 'game'));

        $game_ids = array();
        foreach ($gameIds as $v) {
            $game_ids[] = $v->cid;
        }

        return array(
            'data' => $data,
            'gameIds' => $game_ids,
            'aid' => $aid,
        );
    }

    public function doGameAction() {
        $type = $this->getQuery('type');
        $aid = (int)$this->getQuery('aid');
        $gid = $this->getQuery('gid');
        $act_type = $this->_getPlayActivityTable()->get(array('id' => $aid))->ac_type;
        $flag = $this->_getPlayActivityCouponTable()->get(array('aid' => $aid, 'cid' => $gid, 'type' => 'game'));
        if ($type == 'add') {
            if ($flag) {
                return $this->_Goto('非法操作');
            }
            $status = $this->_getPlayActivityCouponTable()->insert(array('aid' => $aid, 'cid' => $gid, 'act_type' => $act_type, 'type' => 'game'));
            return $this->_Goto($status ? '成功' : '失败');
        }

        if ($type == 'del') {
            if (!$flag) {
                return $this->_Goto('非法操作');
            }
            $status = $this->_getPlayActivityCouponTable()->delete(array('aid' => $aid, 'cid' => $gid, 'type' => 'game'));
            return $this->_Goto($status ? '成功' : '失败');
        }

        exit;
    }

    public function getLinkAction() {
        $type = $this->getQuery('type', 'game');
        $id = $this->getQuery('id', 0);
        echo (int)$this->_getPlayActivityCouponTable()->fetchCount(array('type' => $type, 'aid' => $id));
        exit;
    }

    //推送发现页面
    public function findAction() {
        $aid = (int)$this->getQuery('aid');
        $cid =  (int)$this->getQuery('cid');
        $discovery = ($cid == 2) ? 1 : 2;

        $status = $this->_getPlayActivityTable()->update(array('discovery' => $discovery), array('id' => $aid));

        return $this->_Goto($status ? '成功' : '失败');

    }

    //添加活动页面
    public function linkeAction(){
        $aid = $this->getQuery('aid', 0);
        $like = $this->getQuery('k', '');

        $data = array();

        if($like){
            $where = array(
                'play_excercise_base.name like ?'=>'%'.$like.'%',
                'play_excercise_base.release_status > ?'=>-1,
                'play_excercise_base.city = ?'=>$_COOKIE['city']
            );
            $data = $this->_getPlayExcerciseBaseTable()->fetchAll($where);
        }

        $excerciseIds = $this->_getPlayActivityCouponTable()->fetchAll(array('aid' => $aid, 'type' => 'excercise'));

        $excercise_ids = array();
        foreach ($excerciseIds as $v) {
            $excercise_ids[] = $v->cid;
        }

        return array(
            'data' => $data,
            'excercise_ids' => $excercise_ids,
            'aid' => $aid,
        );
    }

    //添加活动到专题
    public function doexcerciseAction(){
        $type = $this->getQuery('type');
        $aid = (int)$this->getQuery('aid');
        $eid = (int)$this->getQuery('eid');
        $act_type = $this->_getPlayActivityTable()->get(array('id' => $aid))->ac_type;
        $flag = $this->_getPlayActivityCouponTable()->get(array('aid' => $aid, 'cid' => $eid, 'type' => 'excercise'));
        if ($type == 'add') {
            if ($flag) {
                return $this->_Goto('非法操作,已关联');
            }
            $status = $this->_getPlayActivityCouponTable()->insert(array('aid' => $aid, 'cid' => $eid, 'act_type' => $act_type, 'type' => 'excercise'));
            return $this->_Goto($status ? '成功' : '失败');
        }

        if ($type == 'del') {
            if (!$flag) {
                return $this->_Goto('非法操作');
            }
            $status = $this->_getPlayActivityCouponTable()->delete(array('aid' => $aid, 'cid' => $eid, 'type' => 'excercise'));
            return $this->_Goto($status ? '成功' : '失败');
        }

        exit;
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
