<?php
/**
 * 权限控制模块
 * Date: 15-12-9
 * Time: 上午10:57
 */

namespace Admin\Controller;

use Deyi\Integral\Integral;
use Deyi\JsonResponse;
use Deyi\Paginator;
use Zend\View\Model\ViewModel;

class IntegralController extends BasisController
{
    use JsonResponse;

    /**
     * 主站的积分管理
     * @return ViewModel
     */
    public function mainAction()
    {

        $page = (int)$this->getQuery('p', 1);

        $pageSum = 10;
        $start = ($page - 1) * $pageSum;

        $sql = "SELECT city, sum(total_score) as total_score, sum(base_score)as base_score,
sum(total_score-base_score) as award_score FROM play_integral group by city LIMIT {$start}, {$pageSum};";

        $data = $this->query($sql);

        $sql_count = 'select count(*) from play_integral group by city;';

        $count = $this->query($sql_count)->count();
        $url = '/wftadlogin/integral';

        $paginator = new Paginator($page, $count, $pageSum, $url);
        $vm = new viewModel(
            array(
                'data' => $data,
                'pageData' => $paginator->getHtml(),
                'city' => $this->getAllCities(),
            )
        );

        return $vm;

    }


    public function indexAction()
    {
        $city = $this->chooseCity();
        $uid = (int)$this->getQuery('uid', 0);
        $type = (int)$this->getQuery('type', 0);
        $time = (int)$this->getQuery('time', 0);
        $page = (int)$this->getQuery('p', 1);

        $pageSum = 10;
        $start = ($page - 1) * $pageSum;

        $where = "city = '{$city}'";

        if ($uid) {
            $where .= ' AND uid =' . $uid;
        }

        if ($type) {
            $where .= ' AND type =' . $type;
        }

        if ($time === 7) {
            $where .= ' AND create_time >= ' . (time() - 7 * 24 * 3600);
        } elseif ($time === 30) {
            $where .= ' AND create_time >= ' . (time() - 30 * 24 * 3600);
        } elseif ($time === 180) {
            $where .= ' AND create_time >= ' . (time() - 180 * 24 * 3600);
        }

        $sql = "SELECT id, uid, city,`type`, total_score, base_score, award_score,object_id,create_time,`desc` FROM
 play_integral WHERE " . $where . " order by id desc LIMIT {$start}, {$pageSum} ";

        $data = $this->query($sql);
        $place_id = $good_id = $mid = [];
        $integral = [];

        foreach ($data as $d) {
            $integral[] = $d;

            if (in_array($d['type'], [1])) {//游玩地分享
                $place_id[] = (int)$d['object_id'];
            } elseif (in_array($d['type'], [3])) {//商品分享
                $good_id[] = (int)$d['object_id'];
            } elseif (in_array($d['type'], [2, 4, 17, 18])) {//评论相关
                if ($this->checkMid($d['object_id'])) {
                    $mid[] = new \MongoId($d['object_id']);
                }
            } elseif (in_array($d['type'], [5, 102])) {
                $order_sn[] = str_pad($d['object_id'], 9, "0", STR_PAD_LEFT);
            }
        }

        $sql_count = 'select count(*) as ct from play_integral WHERE ' . $where;

        $count = $this->query($sql_count)->current();
        $count = $count['ct'];
        $url = '/wftadlogin/integral/cityintegral';

        if (count($place_id) > 0) {
            $organizer = $this->_getPlayShopTable()->fetchLimit(0, 20, [], ['shop_id' => $place_id])->toArray();
        } else {
            $organizer = false;
        }
        if (count($good_id) > 0) {
            $game = $this->_getPlayOrganizerGameTable()->fetchLimit(0, 20, [], ['id' => $good_id])->toArray();
        } else {
            $game = false;
        }
        if (count($mid) > 0) {
            $msg = $this->_getMdbSocialCircleMsg()->find(array('_id' => array('$in' => $mid)));
        }

        if (count($order_sn) > 0) {
            $order = $this->_getPlayOrderInfoTable()->fetchLimit(0, 20, [], ['order_sn' => $order_sn])->toArray();
        }

        $places = $goods = [];

        if (false !== $organizer && count($organizer) > 0) {
            foreach ($organizer as $o) {
                $places[$o['id']] = $o['name'];
            }
        }

        if ($msg) {
            foreach ($msg as $m) {
                if ((int)$m['msg_type'] === 3) {//游玩地
                    $places[$m['_id'] . $id] = $m['object_data']['object_title'];
                } elseif ((int)$m['msg_type'] === 2) {//商品
                    $goods[$m['_id'] . $id] = $m['object_data']['object_title'];
                }
            }
        }


        if ($order) {
            foreach ($order as $m) {
                $goods[$m['order_sn']] = $m['coupon_name'];

            }
        }

        if (false !== $game && count($game) > 0) {
            foreach ($game as $g) {
                $goods[$g['id']] = $g['title'];
            }
        }

        $i = new Integral();
        $integral_type = $i::$tparr;

        $paginator = new Paginator($page, $count, $pageSum, $url);
        $vm = new viewModel(
            array(
                'data' => $integral,
                'pageData' => $paginator->getHtml(),
                'types' => $integral_type,
                'places' => $places,
                'goods' => $goods,
            )
        );

        return $vm;
    }

    public function cityintegralAction()
    {
        $city = trim($this->getQuery('city', ''));
        $uid = (int)$this->getQuery('uid', 0);
        $type = (int)$this->getQuery('type', 0);
        $time = (int)$this->getQuery('time', 0);
        $page = (int)$this->getQuery('p', 1);

        $pageSum = 10;
        $start = ($page - 1) * $pageSum;

        if(!$city && $this->getAdminCity()!=1 ){
            $where = "city = '{$this->getAdminCity()}'";
        }else{
            $where = "city = '{$city}'";
        }

        if ($uid) {
            $where .= ' AND uid =' . $uid;
        }

        if ($type) {
            $where .= ' AND type =' . $type;
        }

        if ($time === 7) {
            $where .= ' AND create_time >= ' . (time() - 7 * 24 * 3600);
        } elseif ($time === 30) {
            $where .= ' AND create_time >= ' . (time() - 30 * 24 * 3600);
        } elseif ($time === 180) {
            $where .= ' AND create_time >= ' . (time() - 180 * 24 * 3600);
        }

        $sql = "SELECT id, uid, city,`type`, total_score, base_score, award_score,object_id,create_time,`desc` FROM
 play_integral WHERE " . $where . " order by id desc LIMIT {$start}, {$pageSum} ";

        $data = $this->query($sql);
        $place_id = $good_id = $mid = [];
        $integral = [];

        foreach ($data as $d) {
            $integral[] = $d;

            if (in_array($d['type'], [1])) {//游玩地分享
                $place_id[] = (int)$d['object_id'];
            } elseif (in_array($d['type'], [3])) {//商品分享
                $good_id[] = (int)$d['object_id'];
            } elseif (in_array($d['type'], [2, 4, 17, 18])) {//评论相关
                if ($this->checkMid($d['object_id'])) {
                    $mid[] = new \MongoId($d['object_id']);
                }
            } elseif (in_array($d['type'], [5, 102])) {
                $order_sn[] = str_pad($d['object_id'], 9, "0", STR_PAD_LEFT);
            }
        }

        $sql_count = 'select count(*) as ct from play_integral WHERE ' . $where;

        $count = $this->query($sql_count)->current();
        $count = $count['ct'];
        $url = '/wftadlogin/integral/cityintegral';

        if (count($place_id) > 0) {
            $organizer = $this->_getPlayShopTable()->fetchLimit(0, 20, [], ['shop_id' => $place_id])->toArray();
        } else {
            $organizer = false;
        }
        if (count($good_id) > 0) {
            $game = $this->_getPlayOrganizerGameTable()->fetchLimit(0, 20, [], ['id' => $good_id])->toArray();
        } else {
            $game = false;
        }
        if (count($mid) > 0) {
            $msg = $this->_getMdbSocialCircleMsg()->find(array('_id' => array('$in' => $mid)));
        }

        if (count($order_sn) > 0) {
            $order = $this->_getPlayOrderInfoTable()->fetchLimit(0, 20, [], ['order_sn' => $order_sn])->toArray();
        }

        $places = $goods = [];

        if (false !== $organizer && count($organizer) > 0) {
            foreach ($organizer as $o) {
                $places[$o['id']] = $o['name'];
            }
        }

        if ($msg) {
            foreach ($msg as $m) {
                if ((int)$m['msg_type'] === 3) {//游玩地
                    $places[$m['_id'] . $id] = $m['object_data']['object_title'];
                } elseif ((int)$m['msg_type'] === 2) {//商品
                    $goods[$m['_id'] . $id] = $m['object_data']['object_title'];
                }
            }
        }


        if ($order) {
            foreach ($order as $m) {
                $goods[$m['order_sn']] = $m['coupon_name'];

            }
        }

        if (false !== $game && count($game) > 0) {
            foreach ($game as $g) {
                $goods[$g['id']] = $g['title'];
            }
        }

        $i = new Integral();
        $integral_type = $i::$tparr;

        $paginator = new Paginator($page, $count, $pageSum, $url);
        $vm = new viewModel(
            array(
                'data' => $integral,
                'pageData' => $paginator->getHtml(),
                'types' => $integral_type,
                'places' => $places,
                'goods' => $goods,
            )
        );

        return $vm;
    }

    public function saveAction()
    {

        $uid = trim($this->getPost('uid', ''));
        $groupid = trim($this->getPost('group_id', ''));
        $otherauth = trim($this->getPost('other_auth', ''));

        $data = array(
            'uid' => $uid,
            'group_id' => $groupid,
            'other_auth' => $otherauth,
        );

        $flag = $this->_getAuthAccessTable()->get(['uid' => $uid]);
        if ($flag) {
            $status = $this->_getAuthAccessTable()->update($data, ['uid' => $uid]);
        } else {
            $status = $this->_getAuthAccessTable()->insert($data);
        }

        if (!$status) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '保存失败'));
        }
        return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));
    }

    public function newAction()
    {
        $uid = (int)$this->getQuery('uid');

        $data = array();

        if ($uid) {
            $data = $this->_getAuthAccessTable()->get(array('uid' => $uid));
        }

        $vm = new ViewModel(
            array(
                'data' => $data ?: [],
            )
        );

        return $vm;
    }

}